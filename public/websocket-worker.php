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
            // very simple auth example
            if ($data['login'] == 'user1' && $data['password'] == 'pass1')
            {
                frankenphp_ws_send($event['Connection'], json_encode(['type' => 'auth', 'status' => 'ok']));
            }
            else
            {
                frankenphp_ws_send($event['Connection'], json_encode(['type' => 'auth', 'status' => 'error']));
            }
        }

        if ($data['type'] == 'enterRoom')
        {
            // very simple auth example
            frankenphp_ws_tagClient($event['Connection'], 'room_' . $data['name']);
            // $currentRoom = frankenphp_ws_setStoredInformations($event['Connection'],'currentRoom','general');
            $currentRoom = 'general' ;
            frankenphp_ws_send($event['Connection'], json_encode(['type' => 'enterRoom', 'status' => 'ok' , 'name' => $currentRoom]));
        }

        if ($data['type'] == 'writeRoom')
        {
            // broadcast to all clients in the same room
            //$currentRoom = frankenphp_ws_getStoredInformations($event['Connection'],'currentRoom');
            $currentRoom = 'general' ;
            frankenphp_ws_sendToTag('room_' . $currentRoom, json_encode(['type' => 'messageRoom', 'from' => $event['Connection'] ,  'name' => $currentRoom, 'payload' => $data['message']]));

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
