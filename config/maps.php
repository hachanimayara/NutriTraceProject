<?php

return [
    'tile_url' => env('MAP_TILE_URL', 'https://tile.openstreetmap.org/{z}/{x}/{y}.png'),
    'tile_attribution' => env('MAP_TILE_ATTRIBUTION', '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'),
    'routing_url' => env('OSRM_ROUTING_URL', 'https://router.project-osrm.org/route/v1/driving'),
];
