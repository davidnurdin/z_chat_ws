<?php

// Require the Composer autoloader here if needed (API Platform, Symfony, etc.)
//require __DIR__ . '/vendor/autoload.php';

function modifyMessage($message,$connectionId) {
    $stack = frankenphp_ws_getClientMessageQueue($connectionId); // devrait permettre de rejoué des requetes
    $stack = end($stack);
    $requestId = explode('|', $stack)[0];
    $message['requestId'] = $requestId;
    $message['countRequestInMemory'] = count(frankenphp_ws_getClientMessageQueue($connectionId));
    return $message;
}
function sendToClient($connectionId,$message) {
    $message['uniqId'] = uniqid();
    $message = modifyMessage($message,$connectionId);
    $msg = json_encode($message);
    frankenphp_ws_send($connectionId,$msg);
    return $msg;
}

function sendToAll($message,$fromConnectionId = null) {
    $message['uniqId'] = uniqid();
    $message = modifyMessage($message,$fromConnectionId);
    $msg = json_encode($message);
    frankenphp_ws_sendAll($msg);
}

function sendToTag($tag,$message,$fromConnectionId = null) {
    $message['uniqId'] = uniqid();
    $message = modifyMessage($message,$fromConnectionId);
    $msg = json_encode($message);
    frankenphp_ws_sendToTag($tag,$msg);
    return $msg;
}

function sendToTagExpression($expression,$message,$fromConnectionId = null) {
    $message['uniqId'] = uniqid();
    $stack = frankenphp_ws_getClientMessageQueue($fromConnectionId); // devrait permettre de rejoué des requetes
    $stack = end($stack);
    $requestId = explode('|', $stack)[0];
    $message['requestId'] = $requestId;
    $msg = json_encode($message);
    frankenphp_ws_sendToTagExpression($expression,$msg);
}

// ===== Room history helpers (per-room, keep last 20) =====
function room_history_key(string $room): string {
    return 'room_' . $room . '_history';
}
function room_history_load(string $room): array {
    $key = room_history_key($room);
    $raw = frankenphp_ws_global_get($key);
    if ($raw === '' || $raw === null) return [];
    $arr = json_decode($raw, true);
    return is_array($arr) ? $arr : [];
}
function room_history_save(string $room, array $entries): void {
    // keep only last 20 entries
    $entries = array_values(array_slice($entries, -20));
    frankenphp_ws_global_set(room_history_key($room), json_encode($entries));
}
function room_history_append(string $room, array $entry): void {
    $hist = room_history_load($room);
    $hist[] = $entry;
    room_history_save($room, $hist);
}

// Handler outside the loop for better performance (doing less work)
$handler = static function (array $event): array  {

    if ($event['Type'] == 'open')
    {
        sendToClient($event['Connection'], ['type' => 'message', 'payload' => 'Welcome! Your connection ID is ' . $event['Connection']]);
        sendToAll( ['type' => 'countAll', 'count' => frankenphp_ws_getClientsCount()],$event['Connection']) ;
        frankenphp_ws_enablePing($event['Connection']);
        frankenphp_ws_enablePing($event['Connection'],5000);
//        frankenphp_ws_enableQueueCounter($event['Connection'],300,2);


        return ['ok' => true];
    }

    if ($event['Type'] == 'message')
    {
        $data = json_decode($event['Payload'],true);

        if ($data['type'] == 'listAllUsers')
        {

            // get all users with tag standardUser
            $clients = frankenphp_ws_getClientsByTag($data['userType']);
            $list = [];
            foreach ($clients as $client) {
                $list[] = [
                    'login' => frankenphp_ws_getStoredInformation($client,'login'),
                    'gender' => frankenphp_ws_getStoredInformation($client,'gender') ?: 'X'
                ];
            }
            sendToClient($event['Connection'],['type' => 'listAllUsers', 'list' => $list]);
            return ['ok' => true];

        }

        if ($data['type'] == 'getTimePing') {
            //frankenphp_ws_enablePing($event['Connection']);
            sendToClient($event['Connection'],['type' => 'getTimePing', 'time' => frankenphp_ws_getClientPingTime($event['Connection'])]);
            return ['ok' => true];
        }

        if ($data['type'] == 'auth')
        {

            if ($data['login'] == "ban")
            {
                frankenphp_ws_killConnection($event['Connection']);
            }
            // very simple auth example
            if ($data['password'] == 'pass')
            {

                // search if login is already used
                $client = frankenphp_ws_searchStoredInformation('login',FRANKENPHP_WS_OP_EQ,$data['login']);
                if (count($client) > 0)
                {
                    sendToClient($event['Connection'],['type' => 'auth', 'status' => 'error', 'reason' => 'login already used']);
                }
                else {
                    frankenphp_ws_setStoredInformation($event['Connection'], 'login', $data['login']);
                    $gender = isset($data['gender']) ? $data['gender'] : 'X';
                    frankenphp_ws_setStoredInformation($event['Connection'], 'gender', $gender);
                    sendToClient($event['Connection'],['type' => 'auth', 'status' => 'ok', 'login' => $data['login'], 'gender' => $gender]);
                    // add a tag standard user
                    frankenphp_ws_tagClient($event['Connection'], 'standardUser');;
                }
            }
            else
            {
                sendToClient($event['Connection'],['type' => 'auth', 'status' => 'error', 'reason' => 'wrong password']);
            }
        }

        if (frankenphp_ws_getStoredInformation($event['Connection'],'login') == '')
        {
            sendToClient($event['Connection'],['type' => 'notauth', 'status' => 'error' , 'message' => 'You must be connected to use this service']);
            return ['ok' => true];
        }

        if ($data['type'] == 'listRoom')
        {
            $allTags = frankenphp_ws_getTags() ;
            $rooms = [];
            foreach ($allTags as $tag)
            {
                if (substr($tag,0,5) == 'room_')
                    $rooms[substr($tag,5)] = frankenphp_ws_getTagCount($tag) ;
            }

            sendToClient($event['Connection'],['type' => 'roomsList', 'rooms' => $rooms]);

        }
        if ($data['type'] == 'enterRoom')
        {

            // If user is in room , disconnect it and inform users
            $currentRoom = frankenphp_ws_getStoredInformation($event['Connection'],'currentRoom');
            if ($currentRoom != '')
            {
                $oldRoom = $currentRoom;
                frankenphp_ws_setStoredInformation($event['Connection'],'currentRoom','');
                frankenphp_ws_untagClient($event['Connection'], 'room_' . $oldRoom);
                sendToTag('room_' . $oldRoom, ['type' => 'userOutRoom', 'room' => $currentRoom , 'user' => frankenphp_ws_getStoredInformation($event['Connection'],'login')],$event['Connection']);

            }

            $clients = frankenphp_ws_getClientsByTag('room_' . $data['name']);
            $list = [];
            foreach ($clients as $client)
            {
                $list[] = [
                    'login' => frankenphp_ws_getStoredInformation($client,'login'),
                    'gender' => frankenphp_ws_getStoredInformation($client,'gender') ?: 'X'
                ];
                sendToClient($client,[
                    'type' => 'userInRoom',
                    'room' => $data['name'],
                    'user' => frankenphp_ws_getStoredInformation($event['Connection'],'login'),
                    'gender' => frankenphp_ws_getStoredInformation($event['Connection'],'gender') ?: 'X'
                ]);
            }

            frankenphp_ws_tagClient($event['Connection'], 'room_' . $data['name']);
            frankenphp_ws_setStoredInformation($event['Connection'],'currentRoom',$data['name']);
            sendToClient($event['Connection'], ['type' => 'enterRoom', 'status' => 'ok' , 'name' => $data['name']]);

            // add me
            $list[] = [
                'login' => frankenphp_ws_getStoredInformation($event['Connection'],'login'),
                'gender' => frankenphp_ws_getStoredInformation($event['Connection'],'gender') ?: 'X'
            ];
            sendToClient($event['Connection'], ['type' => 'listUserInRoom', 'room' => $data['name'] , 'list' => $list]);

            // Send last 20 history messages for this room to the entering client
            $history = room_history_load($data['name']);
            sendToClient($event['Connection'], ['type' => 'roomHistory', 'room' => $data['name'], 'list' => $history]);

        }

        if ($data['type'] == 'writeRoom')
        {
            // broadcast to all clients in the same room
            $currentRoom = frankenphp_ws_getStoredInformation($event['Connection'],'currentRoom');
            $color = '#000000';
            if (isset($data['color']) && is_string($data['color']) && preg_match('/^#[0-9a-fA-F]{6}$/', $data['color'])) {
                $color = $data['color'];
            }
            $fromLogin = frankenphp_ws_getStoredInformation($event['Connection'],'login');
            $payload = isset($data['message']) ? (string)$data['message'] : '';
            sendToTag('room_' . $currentRoom,[
                'type' => 'messageRoom',
                'from' => $fromLogin,
                'name' => $currentRoom,
                'payload' => $payload,
                'color' => $color
            ],$event['Connection']);

            // Append to room history (keep last 20)
            $entry = [
                'from' => $fromLogin,
                'name' => $currentRoom,
                'payload' => $payload,
                'color' => $color,
                'ts' => time()
            ];
            room_history_append($currentRoom, $entry);

        }

        if ($data['type'] == 'writePrivate')
        {
            $clientTo = frankenphp_ws_searchStoredInformation('login',FRANKENPHP_WS_OP_EQ,$data['to']);

            if (count($clientTo) > 0)
            {
                // Optional color validation for private messages
                $color = '#000000';
                if (isset($data['color']) && is_string($data['color']) && preg_match('/^#[0-9a-fA-F]{6}$/', $data['color'])) {
                    $color = $data['color'];
                }
                // Send private
                sendToClient($clientTo[0],[
                    'type' => 'messagePrivate',
                    'from' => frankenphp_ws_getStoredInformation($event['Connection'],'login'),
                    'payload' => $data['message'],
                    'color' => $color
                ]);
            }

        }





    }


    if ($event['Type'] == 'beforeClose')
    {



        $currentRoom = frankenphp_ws_getStoredInformation($event['Connection'],'currentRoom'); // debug todo : voir si on a tjr les info ici
        $currentUser = frankenphp_ws_getStoredInformation($event['Connection'],'login');

        if ($currentRoom != '')
        {
            frankenphp_ws_setStoredInformation($event['Connection'],'currentRoom','');
            sendToTag('room_' . $currentRoom, ['type' => 'userOutRoom', 'room' => $currentRoom , 'user' => $currentUser],$event['Connection']);
        }


    }

    if ($event['Type'] == 'close')
    {
        sendToAll( ['type' => 'countAll', 'count' => frankenphp_ws_getClientsCount()],$event['Connection']) ;

    }

    return ['ok' => true];
};

$_SERVER['MAX_REQUESTS'] = 0 ;
$maxRequests = (int)($_SERVER['MAX_REQUESTS'] ?? 0); // illimité si 0
for ($nbRequests = 0; !$maxRequests || $nbRequests < $maxRequests; ++$nbRequests) {
    $keepRunning = \frankenphp_handle_request($handler);
    gc_collect_cycles();
    if (!$keepRunning) {
      break;
    }
}
