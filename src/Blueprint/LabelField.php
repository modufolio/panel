<?php

declare(strict_types=1);

namespace Modufolio\Panel\Blueprint;

use Attribute;

/**
 * Mark the property a record is referred to by.
 *
 * When another entity's form offers this one in a lookup, the option's label
 * has to come from somewhere. The guesser tries `name`, `title` and `label`,
 * which is right often enough to be the default and wrong in two ways worth
 * naming: an entity with none of them (a Contact has first and last names),
 * and an entity whose `label` column means something else (an Address's
 * "Office"). Either way the answer is a fact about *this* entity, true for
 * every form that refers to it — so it is declared here, once:
 *
 *     #[ORM\Column(length: 150)]
 *     #[LabelField]
 *     private ?string $addressLine1 = null;
 *
 * It must be a mapped column: the options endpoint orders and searches by it
 * in DQL, so a computed getter cannot serve. Read by {@see FormFieldGuesser}
 * through the metadata's reflection; Doctrine itself never sees it.
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
final class LabelField
{
}
