<?php

if ( ! function_exists('enterRoom')) {
    function enterRoom($room, $connectionId, $login, $gender)
    {
        frankenphp_ws_tagClient($connectionId, 'room_' . $room);
        frankenphp_ws_setStoredInformation($connectionId, 'currentRoom', $room);
        frankenphp_ws_sendToTag('room_' . $room,
            json_encode([
                'type' => 'userInRoom',
                'room' => $room,
                'user' => $login,
                'gender' => $gender
            ])
        );
    }

    function auth($connectionId, $login, $gender)
    {
        frankenphp_ws_setStoredInformation($connectionId, 'gender', $gender);
        frankenphp_ws_setStoredInformation($connectionId, 'login', $login);
        frankenphp_ws_tagClient($connectionId, 'standardUser');
        frankenphp_ws_tagClient($connectionId, 'botUser');
    }

    function sendToRoom($room, $payload, $color = '#000000', $login = 'bot', $connectionId = 'bot')
    {
        // Use the provided payload as-is (no hardcoded override)
        frankenphp_ws_sendToTag('room_' . $room, json_encode([
            'type' => 'messageRoom',
            'from' => $login,
            'name' => $room,
            'payload' => $payload,
            'color' => $color
        ]), $connectionId);

        // Append to room history (keep last 20)
        $entry = [
            'from' => $login,
            'name' => $room,
            'payload' => $payload,
            'color' => $color,
            'ts' => time()
        ];
        room_history_append($room, $entry);
    }

    function room_history_save(string $room, array $entries): void
    {
        // keep only last 20 entries
        $entries = array_values(array_slice($entries, -20));
        frankenphp_ws_global_set(room_history_key($room), json_encode($entries));
    }

    function room_history_append(string $room, array $entry): void
    {
        $hist = room_history_load($room);
        $hist[] = $entry;
        room_history_save($room, $hist);
    }

    function room_history_load(string $room): array
    {
        $key = room_history_key($room);
        $raw = frankenphp_ws_global_get($key);
        if ($raw === '' || $raw === null) return [];
        $arr = json_decode($raw, true);
        return is_array($arr) ? $arr : [];
    }

    function room_history_key(string $room): string
    {
        return 'room_' . $room . '_history';
    }
}
