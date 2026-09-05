<?php

declare(strict_types=1);

namespace Modufolio\Panel\Tests\Field;

use Modufolio\Panel\Field\FieldComponents;
use Modufolio\Panel\Field\FieldTypeInterface;
use PHPUnit\Framework\TestCase;

/**
 * The contract across the PHP/Vue boundary for field components: what the
 * client ships, what a host must register, and how a form's needs are read.
 */
final class FieldComponentsTest extends TestCase
{
    /** The manifest is the one file both registries answer to. */
    public function testBuiltInMatchesTheClientManifest(): void
    {
        $manifest = json_decode((string) file_get_contents(__DIR__ . '/../../ui/src/Components/Fields/fieldTypes.json'), true);

        self::assertSame($manifest, FieldComponents::BUILT_IN, 'src/Field/FieldComponents::BUILT_IN and ui/src/Components/Fields/fieldTypes.json disagree.');
    }

    /**
     * Every field type this package ships names a component the client ships,
     * except the ones a host provides on purpose — heavy editors and the media
     * library's picker. Adding a type with a new component means adding it to
     * one of these two lists, deliberately.
     */
    public function testEveryFieldTypeNamesAShippedOrHostProvidedComponent(): void
    {
        $hostProvided = ['builder', 'image', 'sections'];

        $components = [];
        foreach (glob(__DIR__ . '/../../src/Field/*Type.php') ?: [] as $file) {
            $class = 'Modufolio\\Panel\\Field\\' . basename($file, '.php');

            if (is_a($class, FieldTypeInterface::class, true)) {
                $components[$class] = $class::component();
            }
        }

        self::assertNotSame([], $components);

        foreach ($components as $class => $component) {
            self::assertTrue(
                in_array($component, [...FieldComponents::BUILT_IN, ...$hostProvided], true),
                sprintf('%s renders as "%s", which the client neither ships nor a host is expected to provide.', $class, $component),
            );
        }
    }

    public function testMissingWalksSubFieldsAndHonoursTheHostsRegistrations(): void
    {
        $fields = [
            ['key' => 'title', 'type' => 'text'],
            ['key' => 'cover', 'type' => 'image'],
            ['key' => 'venue', 'type' => 'set', 'fields' => [
                ['key' => 'name', 'type' => 'text'],
                ['key' => 'map', 'type' => 'geo-point'],
            ]],
            ['key' => 'body', 'type' => 'markdown'],
        ];

        self::assertSame(['text', 'image', 'set', 'geo-point', 'markdown'], FieldComponents::used($fields));
        self::assertSame(['image', 'geo-point', 'markdown'], FieldComponents::missing($fields));
        self::assertSame(['geo-point', 'markdown'], FieldComponents::missing($fields, ['image']));
        self::assertSame([], FieldComponents::missing($fields, ['image', 'geo-point', 'markdown']));
    }
}
