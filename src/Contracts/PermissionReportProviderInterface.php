<?php

declare(strict_types=1);

namespace Modufolio\Panel\Contracts;

use Modufolio\Panel\Inspection\PermissionReport;

/**
 * Where the panel's permission page gets its report.
 *
 * The inspector needs four things the package cannot know — the route
 * collection, how a resource class becomes an instance, which roles exist, and
 * what a user carrying one role looks like — so the application answers them
 * and hands over the finished report. {@see \Modufolio\Panel\Inspection\PermissionInspector}
 * does the work; this is only how the panel reaches it.
 *
 * A host that wires none keeps the default, which reports nothing and leaves
 * the page a 404 — the same shape {@see ExportAdapterProviderInterface} uses
 * for a host with no download formats.
 */
interface PermissionReportProviderInterface
{
    /** The report, or null when this application wires no inspector. */
    public function report(): ?PermissionReport;
}
