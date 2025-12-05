<?php

return [

    // Pull everything for now — global domination mode.
    'only' => ['*'],

    'except' => [],

    // Later we can define smaller groups, but right now: EVERYTHING.
    'groups' => [
        'admin' => ['admin.*'],
    ],
];