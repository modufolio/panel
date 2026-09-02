<?php

declare(strict_types=1);

namespace Modufolio\Panel\Tests\Fixture;

use Modufolio\Panel\Contracts\PageRendererInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * The host's renderer, reduced to what a test needs: it remembers which
 * component was asked for and with which props, and hands back whatever
 * response it was given. The panel's contract ends at the props.
 */
final class CapturingRenderer implements PageRendererInterface
{
    public ?string $component = null;

    /** @var array<string, mixed> */
    public array $props = [];

    public function __construct(private readonly ResponseInterface $response)
    {
    }

    public function render(string $component, array $props, ServerRequestInterface $request): ResponseInterface
    {
        $this->component = $component;
        $this->props     = $props;

        return $this->response;
    }
}
