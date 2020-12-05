<?php

return [
    'redis' => [
        'class' => 'yii\redis\Connection',
        'hostname' => '127.0.0.1',
        'port' => 6379,
        'password' => null,
    ],
    'queue' => [
        'class' => \yii\queue\redis\Queue::class,
        'channel' => 'zjhj_bd_586',
    ],
];
