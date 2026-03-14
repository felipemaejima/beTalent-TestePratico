<?php

return [

    'gateway1' => [
        'url'   => env('GATEWAY1_URL', 'http://localhost:3001'),
        'email' => env('GATEWAY1_EMAIL', 'dev@betalent.tech'),
        'token' => env('GATEWAY1_TOKEN', 'FEC9BB078BF338F464F96B48089EB498'),
    ],

    'gateway2' => [
        'url'         => env('GATEWAY2_URL', 'http://localhost:3002'),
        'auth_token'  => env('GATEWAY2_AUTH_TOKEN', 'tk_f2198cc671b5289fa856'),
        'auth_secret' => env('GATEWAY2_AUTH_SECRET', '3d15e8ed6131446ea7e3456728b1211f'),
    ],

];
