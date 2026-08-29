<?php

declare(strict_types=1);

namespace Modufolio\Panel\Contracts;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Turns a client-side component name and its props into a response.
 *
 * The panel emits a schema as data and names the component that should render
 * it; *how* that reaches the browser — Inertia, an HTML shell with a particular
 * template and asset-integrity map, something else entirely — belongs to the
 * host application. Depending on this rather than on a concrete Inertia class
 * is what lets the panel travel between applications that answer that question
 * differently.
 */
interface PageRendererInterface
{
    /**
     * @param array<string, mixed> $props
     */
    public function render(
        string $component,
        array $props,
        ServerRequestInterface $request,
    ): ResponseInterface;
}
