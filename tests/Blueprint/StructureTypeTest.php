<?php

declare(strict_types=1);

namespace Modufolio\Panel\Tests\Blueprint;

use Modufolio\Panel\Blueprint\BlueprintBuilder;
use Modufolio\Panel\Field\StructureType;
use Modufolio\Panel\Tests\Blueprint\Fixture\PricingBlueprint;
use PHPUnit\Framework\TestCase;

/**
 * StructureType through the builder: the serialized shape is the contract the
 * client repeater renders and the applications' page validators walk.
 */
final class StructureTypeTest extends TestCase
{
    public function testSerializesAsARepeaterCarryingItsSubFields(): void
    {
        $builder = new BlueprintBuilder();
        (new PricingBlueprint())->build($builder);

        $fields = $builder->fields();
        $cards = null;
        foreach ($fields as $field) {
            if ($field['key'] === 'cards') {
                $cards = $field;
            }
        }

        $this->assertNotNull($cards);
        $this->assertSame('repeater', $cards['type'], 'Renders through the same component as HasManyType.');
        $this->assertSame('full', $cards['width'], 'Rows need the row; the type defaults to full width.');
        $this->assertSame(['name', 'price'], array_column($cards['fields'], 'key'));
        $this->assertSame(['required' => true], $cards['fields'][0]['rules'], 'Sub-field rules survive serialization for per-row validation.');
    }

    public function testABlueprintCanOverrideTheTypeDefaults(): void
    {
        $builder = new BlueprintBuilder();
        $builder->add('rows', StructureType::class, [
            'label' => 'Rows',
            'width' => '1/2',
            'fields' => [],
        ]);

        $this->assertSame('1/2', $builder->fields()[0]['width']);
    }
}
