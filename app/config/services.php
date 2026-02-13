<?php

declare(strict_types=1);

use App\Core\Container\Provider\AuthServiceProvider;
use App\Core\Container\Provider\ControllerServiceProvider;
use App\Core\Container\Provider\HttpServiceProvider;
use App\Core\Container\Provider\SystemServiceProvider;
use App\Core\Container\Provider\UserServiceProvider;

return array_merge(
    SystemServiceProvider::getDefinitions(),
    HttpServiceProvider::getDefinitions(),
    UserServiceProvider::getDefinitions(),
    ControllerServiceProvider::getDefinitions(),
    AuthServiceProvider::getDefinitions(),
);
