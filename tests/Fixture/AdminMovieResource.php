<?php

declare(strict_types=1);

namespace Modufolio\Panel\Tests\Fixture;

use Modufolio\Panel\Resource\Permissions;

/** The movie resource, gated on ROLE_ADMIN at the route. */
class AdminMovieResource extends MovieResource
{
    public function permissions(): Permissions
    {
        return new Permissions(['ROLE_ADMIN']);
    }
}
