<?php

/*
 * The omniplay/* providers in PHP the harness installs beside the core: their
 * tests' namespace, and what their tests need besides (require-dev). Those
 * that run inside the game server (dotnet, mirror, netcode, godot, node) are
 * not PHP and have their own tests.
 */
return [
    'steam' => ['Omniplay\\Steam\\Tests\\'],
    'gog' => ['Omniplay\\Gog\\Tests\\'],
    'itch' => ['Omniplay\\Itch\\Tests\\'],
    'igdb' => ['Omniplay\\Igdb\\Tests\\'],
    'rawg' => ['Omniplay\\Rawg\\Tests\\'],
    'docker' => ['Omniplay\\Docker\\Tests\\'],
    'edgegap' => ['Omniplay\\Edgegap\\Tests\\'],
];
