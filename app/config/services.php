<?php

declare(strict_types=1);

use App\Core\Container\Provider\SystemServiceProvider;
use App\Core\Container\Provider\UserServiceProvider;
use App\Core\Container\Provider\ControllerServiceProvider;

return array_merge(
    SystemServiceProvider::getDefinitions(),
    UserServiceProvider::getDefinitions(),
    ControllerServiceProvider::getDefinitions(),
);

