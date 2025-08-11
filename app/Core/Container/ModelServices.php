<?php

namespace App\Core\Container;

use App\Model\UserModel;

/**
 * Provides factory methods to create model instances.
 *
 * This class centralizes the creation of model objects, injecting
 * their required system-level dependencies such as database access and logging.
 */
class ModelServices
{
    /**
     * Constructor.
     *
     * @param SystemServices $system Service provider for system-level dependencies.
     */
    public function __construct(private SystemServices $system)
    {
    }

    /**
     * Creates and returns the UserModel instance.
     *
     * @return UserModel
     */
    public function getUserModel(): UserModel
    {
        return new UserModel(
            $this->system->getSqlHelper(),
            $this->system->getLogger('user')
        );
    }
}
