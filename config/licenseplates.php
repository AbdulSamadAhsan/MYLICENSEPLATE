<?php


return[
    'province_logos' => [
        'Punjab' => 'glogo/punjab.jpeg',
        'Sindh' => 'glogo/sindh.png',
        'KPK' => 'glogo/KP_logo.png',
        'Balochistan' => 'glogo/balochistan.jpeg',
        'Gilgit' => 'glogo/gilgit.png',
        'IC' => 'glogo/ict.png',
         "Kashmir"=>"glogo/azad.png"
    ],
    'paths' => [
        'challans' => 'challans',
        'plates' => 'plates',
        'pdfs' => 'pdfs',
        'imports' => 'imports',
    ],
    'method' => env('PAYMENT_METHOD', 'stripe'),
    "penalty"=> env("PENALTY",500),
];