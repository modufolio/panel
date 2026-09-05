<?php

declare(strict_types=1);

namespace Modufolio\Panel\Tests\Blueprint;

use Modufolio\Panel\Blueprint\BlueprintBuilder;
use Modufolio\Panel\Blueprint\Separator;
use Modufolio\Panel\Field\TextType;
use PHPUnit\Framework\TestCase;

/**
 * A separator is a field the client renders and nothing else: it holds its
 * place in the sequence, takes the full row, carries which kind it is, and
 * needs no name from anyone.
 */
final class SeparatorTest extends TestCase
{
    public function testASeparatorKeepsItsPlaceBetweenFields(): void
    {
        $fields = (new BlueprintBuilder())
            ->add('first_name', TextType::class)
            ->separator()
            ->add('email', TextType::class)
            ->separator(Separator::Space)
            ->add('note', TextType::class)
            ->fields();

        self::assertSame(['first_name', 'separator_1', 'email', 'separator_2', 'note'], array_column($fields, 'key'));
        self::assertSame('separator', $fields[1]['type']);
        self::assertSame('full', $fields[1]['width']);
        self::assertSame('line', $fields[1]['props']['separator']);
        self::assertSame('space', $fields[3]['props']['separator']);
    }
}
