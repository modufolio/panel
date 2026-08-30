<?php

declare(strict_types=1);

namespace Modufolio\Panel\Tests\Blueprint;

use Modufolio\Panel\Blueprint\BlueprintBuilder;
use Modufolio\Panel\Field\SectionsType;
use Modufolio\Panel\Field\TextType;
use PHPUnit\Framework\TestCase;

/**
 * SectionsType through the builder: patterns travel as `options`, the
 * per-section settings form as `fields` — the serialized shape the client
 * sections editor and the applications' renderers both consume.
 */
final class SectionsTypeTest extends TestCase
{
    public function testSerializesPatternsAndSettings(): void
    {
        $builder = new BlueprintBuilder();
        $builder->add('sections', SectionsType::class, [
            'options' => ['1/1', '1/2, 1/2', '1/3, 2/3'],
            'fields' => [
                ['key' => 'class', 'type' => 'text', 'label' => 'CSS class'],
            ],
        ]);

        $field = $builder->fields()[0];

        $this->assertSame('sections', $field['type']);
        $this->assertSame('full', $field['width']);
        $this->assertSame(['1/1', '1/2, 1/2', '1/3, 2/3'], $field['options']);
        $this->assertSame('class', $field['fields'][0]['key']);
    }

    public function testDefaultsToOneFullWidthPattern(): void
    {
        $builder = new BlueprintBuilder();
        $builder->add('sections', SectionsType::class);

        $this->assertSame(['1/1'], $builder->fields()[0]['options']);
    }
}
