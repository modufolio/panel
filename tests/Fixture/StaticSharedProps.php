<?php

declare(strict_types=1);

namespace Modufolio\Panel\Tests\Fixture;

use Modufolio\Panel\Contracts\SharedPropsInterface;

/** The props every page carries, as a fixed array a test can look for. */
final class StaticSharedProps implements SharedPropsInterface
{
    /** @param array<string, mixed> $props */
    public function __construct(private readonly array $props = ['auth' => ['user' => null], 'flash' => []])
    {
    }

    public function create(): array
    {
        return $this->props;
    }
}
