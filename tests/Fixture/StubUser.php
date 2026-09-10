<?php

declare(strict_types=1);

namespace Modufolio\Panel\Tests\Fixture;

use Modufolio\Appkit\Security\User\UserInterface;

/**
 * A user that implements nothing beyond the interface — enough for any
 * test that needs to pass a user without caring what it carries.
 */
final class StubUser implements UserInterface
{
    /** @param list<string> $roles */
    public function __construct(
        private readonly array $roles = ['ROLE_USER'],
        public readonly ?string $role = null,
        public readonly bool $admin = false,
    ) {
    }

    public function getId(): mixed
    {
        return 1;
    }

    public function getEmail(): string
    {
        return 'test@example.com';
    }

    /** @return list<string> */
    public function getRoles(): array
    {
        return $this->roles;
    }

    public function eraseCredentials(): void
    {
    }

    public function getUserIdentifier(): string
    {
        return 'test';
    }

    public function isEnabled(): bool
    {
        return true;
    }
}
