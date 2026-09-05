<?php

declare(strict_types=1);

namespace Modufolio\Panel\Tests\Form;

use Modufolio\Panel\Field\ComputedType;
use Modufolio\Panel\Field\SetType;
use Modufolio\Panel\Field\TextareaType;
use Modufolio\Panel\Form\Field;
use PHPUnit\Framework\TestCase;

/** A Field is the `key => [options]` entry with autocomplete: nothing more travels. */
final class FieldTest extends TestCase
{
    public function testAFieldIsItsOptions(): void
    {
        $field = Field::make('notes')->textarea()->label('Notes')->width('1/2')->help('Internal')->required();

        self::assertSame('notes', $field->key());
        self::assertSame([
            'type'     => TextareaType::class,
            'label'    => 'Notes',
            'width'    => '1/2',
            'help'     => 'Internal',
            'required' => true,
        ], $field->toArray());
    }

    public function testAFieldWithNothingDeclaredLeavesEverythingToTheGuess(): void
    {
        self::assertSame([], Field::make('title')->toArray());
    }

    public function testComputedNamesItsAccessor(): void
    {
        self::assertSame(
            ['type' => ComputedType::class, 'accessor' => 'daysUntil'],
            Field::make('days_until')->computed('daysUntil')->toArray(),
        );
    }

    /** Sub-fields take the same three spellings the form does, and land as the nested map the builder has always read. */
    public function testNestedFieldsAreNormalisedToAMap(): void
    {
        $field = Field::make('address')->set([
            'street',
            'zip'  => ['width' => '1/3'],
            Field::make('city')->width('2/3'),
        ]);

        self::assertSame([
            'type'   => SetType::class,
            'fields' => [
                'street' => [],
                'zip'    => ['width' => '1/3'],
                'city'   => ['width' => '2/3'],
            ],
        ], $field->toArray());
    }

    public function testEveryBuilderCallReturnsANewField(): void
    {
        $base   = Field::make('title');
        $labelled = $base->label('Title');

        self::assertNotSame($base, $labelled);
        self::assertSame([], $base->toArray());
    }
}
