<?php

/*
 * The omniplay providers the harness installs beside the core, by their
 * directory under /omniplay/plugins: their Composer name, their GitHub
 * repository, their tests' namespace, and what their tests need besides
 * (require-dev).
 */
return [
    'docker' => ['omniplay/docker', 'omniplay-docker', 'Omniplay\\Docker\\Tests\\'],
];
