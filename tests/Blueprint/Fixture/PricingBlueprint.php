<?php

declare(strict_types=1);

namespace Modufolio\Panel\Tests\Blueprint\Fixture;

use Modufolio\Panel\Blueprint\AbstractBlueprint;
use Modufolio\Panel\Blueprint\BlueprintBuilder;
use Modufolio\Panel\Field\StructureType;
use Modufolio\Panel\Field\TextType;

/** Conventional-name fixture: template `pricing` resolves to this class. */
final class PricingBlueprint extends AbstractBlueprint
{
    public function build(BlueprintBuilder $builder): void
    {
        $builder
            ->add('title', TextType::class, ['rules' => ['required' => true]])
            ->add('cards', StructureType::class, [
                'fields' => [
                    ['key' => 'name',  'type' => 'text', 'label' => 'Name', 'rules' => ['required' => true]],
                    ['key' => 'price', 'type' => 'text', 'label' => 'Price'],
                ],
            ]);
    }
}
