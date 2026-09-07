<?php

declare(strict_types=1);

namespace Modufolio\Panel\Export;

use Modufolio\Panel\Contracts\ExportAdapterInterface;
use Modufolio\Panel\Contracts\ExportAdapterProviderInterface;

/**
 * The download formats of an application that offers none.
 *
 * {@see \Modufolio\Panel\PanelModule} registers this as the default provider
 * so the controller always has one to ask; a host that ships formats
 * overrides the id in its own config/services.php. Every request for a
 * format is refused the way an unknown format is, and the controller turns
 * that into the same 422.
 */
final class NoExportAdapters implements ExportAdapterProviderInterface
{
    public function get(string $format): ExportAdapterInterface
    {
        throw new \InvalidArgumentException('Export is not configured for this application.');
    }
}
