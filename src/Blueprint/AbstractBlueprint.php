<?php

declare(strict_types = 1);

namespace Modufolio\Panel\Blueprint;

/**
 * Describes how a content type is edited in the panel.
 *
 * Shaped like a Symfony form type (App\Form\ContactFormType): declare the
 * fields in build(), and the same declaration drives the panel's controls, the
 * server-side validation and the client-side checks.
 *
 *     final class FineArtBlueprint extends AbstractBlueprint
 *     {
 *         public function build(BlueprintBuilder $builder): void
 *         {
 *             $builder
 *                 ->add('title', TextType::class, ['rules' => ['required' => true]])
 *                 ->add('email', EmailType::class, ['label' => 'Contact email']);
 *         }
 *     }
 */
abstract class AbstractBlueprint
{
    abstract public function build(BlueprintBuilder $builder): void;

    /**
     * The declared fields.
     *
     * @return list<array<string, mixed>>
     */
    final public function definitions(): array
    {
        $builder = new BlueprintBuilder();
        $this->build($builder);

        return $builder->fields();
    }
}
