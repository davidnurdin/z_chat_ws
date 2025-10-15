<?php

// Require the Composer autoloader here if needed (API Platform, Symfony, etc.)
//require __DIR__ . '/vendor/autoload.php';

// Handler outside the loop for better performance (doing less work)
$handler = static function (array $event): array  {

    if ($event['Type'] == 'open')
    {
        file_put_contents('php://stderr', "New connection opened: " . $event['Connection'] . "\n");
        frankenphp_ws_send($event['Connection'], json_encode(['type' => 'message', 'payload' => 'Welcome! Your connection ID is ' . $event['Connection']]));
    }

    if ($event['Type'] == 'message')
    {
        $data = json_decode($event['Payload'],true);
        if ($data['type'] == 'auth')
        {
            file_put_contents('php://stderr', var_export($data,true) . "\n");

            // very simple auth example
            if ($data['password'] == 'pass')
            {
                frankenphp_ws_setStoredInformation($event['Connection'],'login',$data['login']);
                frankenphp_ws_send($event['Connection'], json_encode(['type' => 'auth', 'status' => 'ok' , 'login' => $data['login']]));
            }
            else
            {
                frankenphp_ws_send($event['Connection'], json_encode(['type' => 'auth', 'status' => 'error']));
            }
        }

        if (frankenphp_ws_getStoredInformation($event['Connection'],'login') == '')
        {
            frankenphp_ws_send($event['Connection'], json_encode(['type' => 'notauth', 'status' => 'error' , 'message' => 'You must be connected to use this service']));
            return ['ok' => true];
        }

        if ($data['type'] == 'getRoomsList')
        {
            $rooms = [];
            $clients = frankenphp_ws_getClientsByTagExpression('room_*');
            foreach ($clients as $client) {
                if (!isset($rooms[frankenphp_ws_getStoredInformation($client,'currentRoom')]))
                    $rooms[frankenphp_ws_getStoredInformation($client,'currentRoom')] = 0 ;

                $rooms[frankenphp_ws_getStoredInformation($client, 'currentRoom')]++;
            }

            frankenphp_ws_send($event['Connection'], json_encode(['type' => 'roomsList', 'rooms' => $rooms]));

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
                frankenphp_ws_sendToTag('room_' . $oldRoom, json_encode(['type' => 'userOutRoom', 'room' => $currentRoom , 'user' => frankenphp_ws_getStoredInformation($event['Connection'],'login')]));
            }

            $clients = frankenphp_ws_getClientsByTag('room_' . $data['name']);
            $list = [];
            foreach ($clients as $client)
            {
                $list[] = frankenphp_ws_getStoredInformation($client,'login');
                frankenphp_ws_send($client, json_encode(['type' => 'userInRoom', 'room' => $data['name'] , 'user' => frankenphp_ws_getStoredInformation($event['Connection'],'login')  ]));
            }

            frankenphp_ws_tagClient($event['Connection'], 'room_' . $data['name']);
            frankenphp_ws_setStoredInformation($event['Connection'],'currentRoom',$data['name']);
            frankenphp_ws_send($event['Connection'], json_encode(['type' => 'enterRoom', 'status' => 'ok' , 'name' => $data['name']]));

            // add me
            $list[] = frankenphp_ws_getStoredInformation($event['Connection'],'login');
            frankenphp_ws_send($event['Connection'], json_encode(['type' => 'listUserInRoom', 'room' => $data['name'] , 'list' => $list]));

        }

        if ($data['type'] == 'writeRoom')
        {
            // broadcast to all clients in the same room
            $currentRoom = frankenphp_ws_getStoredInformation($event['Connection'],'currentRoom');
            frankenphp_ws_sendToTag('room_' . $currentRoom, json_encode(['type' => 'messageRoom', 'from' => frankenphp_ws_getStoredInformation($event['Connection'],'login') ,  'name' => $currentRoom, 'payload' => $data['message']]));

        }

        if ($data['type'] == 'writePrivate')
        {
            // FRANKENPHP_WS_OP_EQ << voir pourquoi la constante ne fonctionne pas
            $clientTo = frankenphp_ws_searchStoredInformation('login','eq',$data['to']);
            file_put_contents('php://stderr', var_export($clientTo,true) . "\n");

            if (count($clientTo) > 0)
            {
                // Send private
                frankenphp_ws_send($clientTo[0],json_encode(['type' => 'messagePrivate', 'from' => frankenphp_ws_getStoredInformation($event['Connection'],'login') ,  'payload' => $data['message']]));
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

            // cette ligne pose pb ! (on envoi sur un tag qui est en cours de fermeture)
            frankenphp_ws_sendToTag('room_' . $currentRoom, json_encode(['type' => 'userOutRoom', 'room' => $currentRoom , 'user' => $currentUser]));
        }


    }

    if ($event['Type'] == 'close')
    {
        file_put_contents('php://stderr', "Connection closed: " . $event['Connection'] . "\n");
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
