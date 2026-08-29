<?php

declare(strict_types = 1);

namespace Modufolio\Panel\Field;

/**
 * A field type a blueprint can `add()`.
 *
 * Modelled on Symfony's form types (see App\Form\ContactFormType): the type
 * class names the control *and* carries the options that always come with it,
 * so `EmailType` means "a text control that must contain an email" in one
 * token rather than a component name plus hand-repeated props and rules.
 */
interface FieldTypeInterface
{
    /** The panel component key this type renders as. */
    public static function component(): string;

    /**
     * Options that come with the type, merged beneath whatever the blueprint
     * passes — so a blueprint can always override them.
     *
     * @return array<string, mixed>
     */
    public static function defaults(): array;
}
