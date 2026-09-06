<?php

declare(strict_types=1);

namespace Modufolio\Panel\Tests\Form;

use Modufolio\Panel\Blueprint\Separator;
use Modufolio\Panel\Field\TextareaType;
use Modufolio\Panel\Form\Field;
use Modufolio\Panel\Form\Fieldset;
use Modufolio\Panel\Form\Form;
use Modufolio\Panel\Form\Tab;
use PHPUnit\Framework\TestCase;

/** Three spellings of an entry, one shape out. */
final class FormTest extends TestCase
{
    public function testEntriesAreNormalisedInOrder(): void
    {
        $form = Form::make()->fields([
            'title',
            'contact' => ['width' => '1/2'],
            Field::make('notes')->textarea(),
            Separator::Line,
            'cast',
        ]);

        self::assertSame([
            ['title', []],
            ['contact', ['width' => '1/2']],
            ['notes', ['type' => TextareaType::class]],
            [Separator::Line, []],
            ['cast', []],
        ], $form->entries());

        self::assertSame(['title', 'contact', 'notes', 'cast'], $form->keys());
    }

    public function testAnEmptyFormIsADeclaredFormWithNothingInIt(): void
    {
        self::assertSame([], Form::make()->entries());
    }

    public function testAPlainEntryThatIsNotAStringIsRefused(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('a plain entry must be a field name, a Field, a Separator, a Tab or a Fieldset');

        Form::make()->fields([['label' => 'Title']]);
    }

    /** Containers flatten: every field lands in the one list, carrying its tab and its box. */
    public function testTabsAndFieldsetsFlattenIntoPlacedEntries(): void
    {
        $form = Form::make()->fields([
            'title',
            Tab::make('General')->fields([
                Fieldset::make('Name', help: 'As on the passport')->fields(['first_name', Field::make('last_name')->width('1/2')]),
                Separator::Line,
                'email' => ['width' => '1/2'],
            ]),
            Tab::make('Notes', icon: 'pencil')->fields(['note']),
        ]);

        self::assertSame([
            ['title', []],
            ['first_name', ['group' => 'general', 'fieldset' => 'name']],
            ['last_name', ['group' => 'general', 'fieldset' => 'name', 'width' => '1/2']],
            [Separator::Line, ['group' => 'general']],
            ['email', ['group' => 'general', 'width' => '1/2']],
            ['note', ['group' => 'notes']],
        ], $form->entries());

        self::assertSame([
            'tabs'      => [['key' => 'general', 'label' => 'General', 'icon' => null], ['key' => 'notes', 'label' => 'Notes', 'icon' => 'pencil']],
            'fieldsets' => [['key' => 'name', 'label' => 'Name', 'help' => 'As on the passport']],
        ], $form->layout());
    }

    public function testTabsIsFieldsSpelledForReading(): void
    {
        $form = Form::make()->tabs([Tab::make('Billing address')->fields(['street'])]);

        self::assertSame([['street', ['group' => 'billing-address']]], $form->entries());
        self::assertSame(['tabs' => [], 'fieldsets' => []], Form::make()->fields(['street'])->layout(), 'A flat form draws no containers.');
    }

    public function testATabInsideATabIsRefused(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('tabs do not nest');

        // @phpstan-ignore argument.type (the type says no; the runtime says why)
        Form::make()->tabs([Tab::make('Outer')->fields([Tab::make('Inner')->fields(['x'])])]);
    }

    public function testAFieldsetInsideAFieldsetIsRefused(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('fieldsets do not nest');

        // @phpstan-ignore argument.type (the type says no; the runtime says why)
        Form::make()->fields([Fieldset::make('Outer')->fields([Fieldset::make('Inner')->fields(['x'])])]);
    }

    public function testFieldsReplacesRatherThanAppends(): void
    {
        $form = Form::make()->fields(['title'])->fields(['year']);

        self::assertSame(['year'], $form->keys());
    }
}
