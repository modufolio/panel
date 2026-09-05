<?php

declare(strict_types=1);

namespace Modufolio\Panel\Contracts;

/**
 * The formats a host offers for downloading a listing.
 *
 * The generated export route asks for the format the request names; an
 * unknown one is refused with the exception's message, so the host decides
 * the wording as well as the set.
 */
interface ExportAdapterProviderInterface
{
    /** @throws \InvalidArgumentException for a format the host does not offer */
    public function get(string $format): ExportAdapterInterface;
}
