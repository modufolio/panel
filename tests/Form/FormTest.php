<?php

declare(strict_types=1);

namespace Modufolio\Panel\Tests\Form;

use Modufolio\Panel\Blueprint\Separator;
use Modufolio\Panel\Field\TextareaType;
use Modufolio\Panel\Form\Field;
use Modufolio\Panel\Form\Form;
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
        $this->expectExceptionMessage('a plain entry must be a field name, a Field or a Separator');

        Form::make()->fields([['label' => 'Title']]);
    }

    public function testFieldsReplacesRatherThanAppends(): void
    {
        $form = Form::make()->fields(['title'])->fields(['year']);

        self::assertSame(['year'], $form->keys());
    }
}
