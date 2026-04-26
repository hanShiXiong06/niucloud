<?php

return [
    'bind' => [

    ],
    'listen' => [
        'WapIndex' => [ 'addon\wj_books\app\listener\WapIndexListener' ],
        'BottomNavigation' => ['addon\wj_books\app\listener\BottomNavigationListener'],

    ],
    'subscribe' => [
    ],
];