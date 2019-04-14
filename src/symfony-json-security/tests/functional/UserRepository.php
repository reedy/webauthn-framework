<?php

declare(strict_types=1);

/*
 * The MIT License (MIT)
 *
 * Copyright (c) 2014-2019 Spomky-Labs
 *
 * This software may be modified and distributed under the terms
 * of the MIT license.  See the LICENSE file for details.
 */

namespace Webauthn\JsonSecurityBundle\Tests\Functional;

use Symfony\Component\Security\Core\User\UserInterface;

final class UserRepository
{
    /**
     * @var User[]
     */
    private $users;

    public function __construct()
    {
        $this->users = [
            'admin' => new User('uuid', 'admin', ['ROLE_ADMIN', 'ROLE_USER']),
        ];
    }

    public function findByUsername(string $username): ?UserInterface
    {
        if (\array_key_exists($username, $this->users)) {
            return $this->users[$username];
        }

        return null;
    }
}
