<?php

return [
    'auth_login' => [
        'attempts' => 5,
        'decay_minutes' => 10,
    ],
    'auth_register' => [
        'attempts' => 3,
        'decay_minutes' => 60,
    ],
    'auth_password' => [
        'attempts' => 3,
        'decay_minutes' => 60,
    ],
    'auth_verification' => [
        'attempts' => 5,
        'decay_minutes' => 60,
    ],
];