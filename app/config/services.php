<?php

declare(strict_types=1);

use App\Core\Container\Provider\ControllerServiceProvider;
use App\Core\Container\Provider\SystemServiceProvider;
use App\Core\Container\Provider\UserServiceProvider;

return array_merge(
    SystemServiceProvider::getDefinitions(),
    UserServiceProvider::getDefinitions(),
    ControllerServiceProvider::getDefinitions(),
);
