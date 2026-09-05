<?php

declare(strict_types=1);

namespace Modufolio\Panel\Tests\Blueprint;

use Modufolio\Panel\Blueprint\FormType;
use Modufolio\Panel\Field\EmailType;
use PHPUnit\Framework\TestCase;

final class FormTypeAttributeTest extends TestCase
{
    public function testItCarriesAFieldType(): void
    {
        self::assertSame(EmailType::class, (new FormType(EmailType::class))->type);
    }

    /** A class that is not a field type is refused where it is written, not when the form renders. */
    public function testItRefusesAnythingThatIsNotAFieldType(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/expects a .*FieldTypeInterface, got "stdClass"/');

        new FormType(\stdClass::class);
    }
}
