<?php

declare(strict_types=1);

namespace Modufolio\Panel\Field;

/**
 * Pick a template file — "this page renders with layout X". Renders as an
 * ordinary select; the option list comes from the filesystem via
 * {@see optionsFromDirectory()}, called where the blueprint is built:
 *
 *     $fields->add('template', TemplateSelectType::class, [
 *         'options' => TemplateSelectType::optionsFromDirectory(
 *             BASE_DIR.'/site/templates', '/^(?!_)/'
 *         ),
 *     ]);
 *
 * A helper rather than a lazy resolver on purpose: blueprints are also built
 * at boot by the route loader, and a glob is cheap enough to run there —
 * unlike the database lookups BelongsToType defers.
 */
final class TemplateSelectType implements FieldTypeInterface
{
    public static function component(): string
    {
        return 'select';
    }

    public static function defaults(): array
    {
        return [];
    }

    /**
     * Template files in $directory whose basename matches $filter, as
     * value => label options. The extension is stripped from both.
     *
     * @return array<string, string>
     */
    public static function optionsFromDirectory(string $directory, string $filter = '//', string $extension = 'php'): array
    {
        $options = [];

        foreach (glob($directory.'/*.'.$extension) ?: [] as $file) {
            $name = basename($file, '.'.$extension);
            if (preg_match($filter, $name) !== 1) {
                continue;
            }

            $options[$name] = ucwords(str_replace(['-', '_'], ' ', $name));
        }

        ksort($options);

        return $options;
    }
}
