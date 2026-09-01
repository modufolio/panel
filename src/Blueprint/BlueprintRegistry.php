<?php

declare(strict_types = 1);

namespace Modufolio\Panel\Blueprint;

/**
 * Finds the blueprint for a page template.
 *
 * Resolution is by convention — `fine-art` → `FineArtBlueprint` — with an
 * explicit map for anything that does not follow it. A template with no
 * blueprint returns null and the caller falls back to inferring fields from the
 * stored content, so adopting blueprints one template at a time is safe.
 */
final class BlueprintRegistry
{
    /** @var array<string, AbstractBlueprint|null> */
    private array $resolved = [];

    /**
     * @param string                                          $namespace Where the application keeps its
     *                                                                   blueprints. Defaults to the documented
     *                                                                   convention; pass a different namespace
     *                                                                   to house them elsewhere.
     * @param array<string, class-string<AbstractBlueprint>> $overrides Templates whose class name does not
     *                                                                   follow from their slug.
     */
    public function __construct(
        private readonly string $namespace = 'App\\Panel\\Blueprint',
        private readonly array $overrides = [],
    ) {}

    public function for(string $template): ?AbstractBlueprint
    {
        if (array_key_exists($template, $this->resolved)) {
            return $this->resolved[$template];
        }

        return $this->resolved[$template] = $this->locate($template);
    }

    private function locate(string $template): ?AbstractBlueprint
    {
        $class = $this->overrides[$template] ?? $this->conventionalClass($template);

        if ($class === null || !class_exists($class) || !is_a($class, AbstractBlueprint::class, true)) {
            return null;
        }

        return new $class();
    }

    /**
     * `fine-art` → `App\Panel\Blueprint\FineArtBlueprint`.
     *
     * Built from a restricted alphabet rather than the raw template name, so a
     * value arriving from a filename can never be coerced into naming an
     * unrelated class.
     *
     * The result is a name, not a proven class: {@see locate()} checks that it
     * exists and is a blueprint before instantiating it.
     */
    private function conventionalClass(string $template): ?string
    {
        $safe = preg_replace('/[^a-z0-9_-]/i', '', $template) ?? '';

        if ($safe === '') {
            return null;
        }

        $studly = str_replace(' ', '', ucwords(str_replace(['-', '_'], ' ', strtolower($safe))));

        return $this->namespace . '\\' . $studly . 'Blueprint';
    }
}
