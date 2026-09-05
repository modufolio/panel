<?php

declare(strict_types=1);

namespace Modufolio\Panel\Blueprint;

use Attribute;
use Modufolio\Panel\Field\FieldTypeInterface;

/**
 * Tell the guesser which field type a property edits as.
 *
 * Doctrine's column type answers most of it — a text column is a textarea, a
 * boolean a toggle — but a `string` column is only ever a text input from the
 * mapping's point of view, and some strings are emails, URLs or colours. That
 * is a fact about the property, true for every form over the entity, so it is
 * declared beside the column rather than repeated in each resource:
 *
 *     #[ORM\Column(length: 200, nullable: true)]
 *     #[FormType(EmailType::class)]
 *     private ?string $email = null;
 *
 * The attribute names the type and nothing else. Layout, access and
 * conditions describe one form, not the property, and stay in the resource.
 *
 * Read by {@see FormFieldGuesser} through the metadata's reflection: Doctrine
 * itself never sees it. Precedence is the attribute over the column mapping,
 * and an explicit declaration in the resource over both.
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
final class FormType
{
    /** @var class-string<FieldTypeInterface> */
    public readonly string $type;

    /** @param string $type a {@see FieldTypeInterface} class; anything else is refused here */
    public function __construct(string $type)
    {
        if (!is_a($type, FieldTypeInterface::class, true)) {
            throw new \InvalidArgumentException(sprintf(
                '#[FormType] expects a %s, got "%s".',
                FieldTypeInterface::class,
                $type,
            ));
        }

        $this->type = $type;
    }
}
