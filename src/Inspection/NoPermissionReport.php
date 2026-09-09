<?php

declare(strict_types=1);

namespace Modufolio\Panel\Inspection;

use Modufolio\Panel\Contracts\PermissionReportProviderInterface;

/**
 * The default: no inspector wired, so no permission page.
 *
 * Building a report means naming this application's roles and standing in for
 * a user carrying each one — decisions the package must not guess. A host that
 * wants the page implements {@see PermissionReportProviderInterface} and
 * registers it under that id.
 */
final class NoPermissionReport implements PermissionReportProviderInterface
{
    public function report(): ?PermissionReport
    {
        return null;
    }
}
