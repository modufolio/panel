<?php

declare(strict_types=1);

namespace Modufolio\Panel\Tests\Field;

use Modufolio\Panel\Field\FieldTypeInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Every field type's component key.
 *
 * These strings are half of a contract whose other half lives in another
 * repository: the client resolves them through
 * `packages/panel/src/Components/Fields/fieldRegistry.ts`, and a key with no
 * component there throws `Unknown field type "x"` when the form renders.
 *
 * That has already happened once — `TagsType` shipped emitting `tags` while
 * nothing registered it, so a blueprint using it was broken from the day it
 * was written. Pinning the strings here does not prove the client can render
 * them, but it does mean a rename cannot happen silently on this side.
 */
final class FieldTypeTest extends TestCase
{
    /** @return array<string, array{class-string<FieldTypeInterface>, string}> */
    public static function types(): array
    {
        $types = [
            \Modufolio\Panel\Field\BelongsToType::class => 'belongs-to',
            \Modufolio\Panel\Field\BuilderType::class   => 'builder',
            \Modufolio\Panel\Field\ColorType::class     => 'color',
            \Modufolio\Panel\Field\DateType::class      => 'date',
            \Modufolio\Panel\Field\DecimalType::class   => 'text',
            \Modufolio\Panel\Field\EmailType::class     => 'text',
            \Modufolio\Panel\Field\HasManyType::class   => 'repeater',
            \Modufolio\Panel\Field\ImageType::class     => 'image',
            \Modufolio\Panel\Field\ManyToManyType::class => 'multiselect',
            \Modufolio\Panel\Field\NumberType::class    => 'text',
            \Modufolio\Panel\Field\SectionsType::class  => 'sections',
            \Modufolio\Panel\Field\SelectType::class    => 'select',
            \Modufolio\Panel\Field\StructureType::class => 'repeater',
            \Modufolio\Panel\Field\TagsType::class      => 'tags',
            \Modufolio\Panel\Field\TextType::class      => 'text',
            \Modufolio\Panel\Field\TextareaType::class  => 'textarea',
            \Modufolio\Panel\Field\ToggleType::class    => 'toggle',
            \Modufolio\Panel\Field\UrlType::class       => 'text',
        ];

        $cases = [];
        foreach ($types as $class => $component) {
            $cases[substr((string) strrchr($class, '\\'), 1)] = [$class, $component];
        }

        return $cases;
    }

    #[DataProvider('types')]
    public function testTheComponentKeyIsStable(string $class, string $component): void
    {
        self::assertSame($component, $class::component());
    }

    #[DataProvider('types')]
    public function testDefaultsAreAnOptionArray(string $class, string $component): void
    {
        $defaults = $class::defaults();

        // Always asserts, including for the common empty case — a test that
        // silently does nothing is worse than no test.
        self::assertSame(
            $defaults,
            array_filter($defaults, is_string(...), ARRAY_FILTER_USE_KEY),
            sprintf('%s::defaults() must be keyed by option name.', $class),
        );
    }

    /**
     * Every type in the directory is covered above. Without this, adding a
     * type and forgetting to register its key on the client stays invisible
     * exactly as long as nobody uses it.
     */
    public function testEveryFieldTypeInThePackageIsCovered(): void
    {
        $onDisk = [];
        foreach (glob(__DIR__ . '/../../src/Field/*.php') ?: [] as $file) {
            $name = basename($file, '.php');
            if ($name === 'FieldTypeInterface') {
                continue;
            }
            $onDisk[] = $name;
        }

        $covered = array_keys(self::types());

        sort($onDisk);
        sort($covered);

        self::assertSame($onDisk, $covered, 'A field type exists that no test names.');
    }
}
