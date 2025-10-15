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

// Handler outside the loop for better performance (doing less work)
$handler = static function (array $event): array  {

    if ($event['Type'] == 'open')
    {
        file_put_contents('php://stderr', "New connection opened: " . $event['Connection'] . "\n");
        sendToClient($event['Connection'], ['type' => 'message', 'payload' => 'Welcome! Your connection ID is ' . $event['Connection']]);
        sendToAll( ['type' => 'countAll', 'count' => frankenphp_ws_getClientsCount()],$event['Connection']) ;
        frankenphp_ws_enablePing($event['Connection']);
        frankenphp_ws_enablePing($event['Connection'],30000);
        frankenphp_ws_enableQueueCounter($event['Connection'],300,2);


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
            foreach ($clients as $client)
                $list[] = frankenphp_ws_getStoredInformation($client,'login');
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
            file_put_contents('php://stderr', var_export($data,true) . "\n");

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
                    sendToClient($event['Connection'],['type' => 'auth', 'status' => 'ok', 'login' => $data['login']]);
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
                $list[] = frankenphp_ws_getStoredInformation($client,'login');
                sendToClient($client,['type' => 'userInRoom', 'room' => $data['name'] , 'user' => frankenphp_ws_getStoredInformation($event['Connection'],'login')  ]);
            }

            frankenphp_ws_tagClient($event['Connection'], 'room_' . $data['name']);
            frankenphp_ws_setStoredInformation($event['Connection'],'currentRoom',$data['name']);
            sendToClient($event['Connection'], ['type' => 'enterRoom', 'status' => 'ok' , 'name' => $data['name']]);

            // add me
            $list[] = frankenphp_ws_getStoredInformation($event['Connection'],'login');
            sendToClient($event['Connection'], ['type' => 'listUserInRoom', 'room' => $data['name'] , 'list' => $list]);

        }

        if ($data['type'] == 'writeRoom')
        {
            // broadcast to all clients in the same room
            $currentRoom = frankenphp_ws_getStoredInformation($event['Connection'],'currentRoom');
            sendToTag('room_' . $currentRoom,['type' => 'messageRoom', 'from' => frankenphp_ws_getStoredInformation($event['Connection'],'login') ,  'name' => $currentRoom, 'payload' => $data['message']],$event['Connection']);

        }

        if ($data['type'] == 'writePrivate')
        {
            $clientTo = frankenphp_ws_searchStoredInformation('login',FRANKENPHP_WS_OP_EQ,$data['to']);
            file_put_contents('php://stderr', var_export($clientTo,true) . "\n");

            if (count($clientTo) > 0)
            {
                // Send private
                sendToClient($clientTo[0],['type' => 'messagePrivate', 'from' => frankenphp_ws_getStoredInformation($event['Connection'],'login') ,  'payload' => $data['message']]);
            }

            // TODO
            // Soit on gère coté serveur via login<>ConnectionId
            // Soit on envois les ConnectionId des users dans la room (et autre)
            // Soit on expose une api pour recup les connectionID ?
            // IDK.

        }





    }


    if ($event['Type'] == 'beforeClose')
    {

        file_put_contents('php://stderr', "Connection before closed: " . $event['Connection'] . "\n");

        file_put_contents('php://stderr', var_export($event,true) . "\n");

        // si on a un currentRoom on le vide et informe les users
        $currentRoom = frankenphp_ws_getStoredInformation($event['Connection'],'currentRoom'); // debug todo : voir si on a tjr les info ici
        file_put_contents('php://stderr', "CURRENT ROOM : " . $currentRoom. "\n");

        $currentUser = frankenphp_ws_getStoredInformation($event['Connection'],'login');

        file_put_contents('php://stderr', "CURRENT USER : " . $currentUser. "\n");
        if ($currentRoom != '')
        {
            // BUG : si on lance, 3 bouton , f5 , (close) => relaunch => tt planté
            frankenphp_ws_setStoredInformation($event['Connection'],'currentRoom','');
//            file_put_contents('php://stderr', "SEND TO ROOM_" . $currentRoom. " that user : " . $currentUser. " exit ! \n");

            sendToTag('room_' . $currentRoom, ['type' => 'userOutRoom', 'room' => $currentRoom , 'user' => $currentUser],$event['Connection']);
        }


    }

    if ($event['Type'] == 'close')
    {
        file_put_contents('php://stderr', "Connection closed: " . $event['Connection'] . "\n");
        sendToAll( ['type' => 'countAll', 'count' => frankenphp_ws_getClientsCount()],$event['Connection']) ;

    }

    return ['ok' => true];
};

$maxRequests = (int)($_SERVER['MAX_REQUESTS'] ?? 0); // illimité si 0
for ($nbRequests = 0; !$maxRequests || $nbRequests < $maxRequests; ++$nbRequests) {
    $keepRunning = \frankenphp_handle_request($handler);
    gc_collect_cycles();
    if (!$keepRunning) {
      break;
    }
}
