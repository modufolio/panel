<?php

declare(strict_types = 1);

namespace Modufolio\Panel\Blueprint;

use Modufolio\Panel\Field\FieldTypeInterface;
use Modufolio\Panel\Field\SeparatorType;
use Symfony\Component\OptionsResolver\Exception\ExceptionInterface as OptionsException;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Collects a blueprint's fields, in the order they are added.
 *
 * The counterpart to Symfony's FormBuilderInterface (see
 * App\Form\ContactFormType): `add()` names a key, a type class and the options
 * that differ from the type's own. Options passed here win over the type's
 * defaults, so `EmailType` supplies the email rule and the input type, and a
 * field can still override either.
 *
 * Options are resolved through OptionsResolver, the same component
 * ContactFormType configures. A misspelled option is therefore an error at
 * build time rather than a setting that silently does nothing.
 */
final class BlueprintBuilder
{
    /**
     * Options that describe the control itself and must reach the component as
     * props. Without this they would sit at the top level of the definition,
     * where the panel's field renderers do not look, and quietly do nothing.
     */
    private const CONTROL_OPTIONS = ['placeholder', 'disabled', 'readonly', 'required', 'autofocus', 'prefix', 'postfix'];

    /** @var list<array<string, mixed>> */
    private array $fields = [];

    /** Separators are keyed by count, since nobody names one. */
    private int $separators = 0;

    /**
     * A break between the field before and the field after: a rule, or the
     * same gap with nothing drawn in it. Takes the full row either way.
     */
    public function separator(Separator $separator = Separator::Line): self
    {
        return $this->add(
            'separator_' . ++$this->separators,
            SeparatorType::class,
            ['label' => '', 'props' => ['separator' => $separator->value]],
        );
    }

    /**
     * @param string               $type    A {@see FieldTypeInterface} implementation. Checked at
     *                                      runtime rather than trusted, because declarations also
     *                                      arrive from config arrays and guessed metadata.
     * @param array<string, mixed> $options
     */
    public function add(string $key, string $type, array $options = []): self
    {
        if (!is_a($type, FieldTypeInterface::class, true)) {
            throw new \InvalidArgumentException(sprintf(
                'Field "%s": %s is not a %s.',
                $key,
                $type,
                FieldTypeInterface::class,
            ));
        }

        $defaults = $type::defaults();

        try {
            $resolved = $this->resolver()->resolve($options);
        } catch (OptionsException $e) {
            throw new \InvalidArgumentException(
                sprintf('Field "%s" (%s): %s', $key, $type, $e->getMessage()),
                previous: $e,
            );
        }

        self::assertSaneRules($key, $resolved['rules'] ?? []);

        $requiredOptions = is_a($type, \Modufolio\Panel\Field\RequiresOptionsInterface::class, true)
            ? $type::requiredOptions()
            : [];
        foreach ($requiredOptions as $requiredOption) {
            if (!isset($resolved[$requiredOption])) {
                throw new \InvalidArgumentException(sprintf(
                    'Field "%s": %s requires the "%s" option.',
                    $key,
                    $type,
                    $requiredOption,
                ));
            }
        }

        $field = [
            'key'   => $key,
            'type'  => $type::component(),
            'label' => $resolved['label'] ?? self::humanize($key),
            // props and rules merge key-by-key rather than being replaced, so
            // adding one rule to an EmailType does not silently drop the email
            // check the type brought with it.
            'props' => array_merge(
                $defaults['props'] ?? [],
                $resolved['props'] ?? [],
                self::controlProps($resolved),
            ),
            'rules' => array_merge($defaults['rules'] ?? [], $resolved['rules'] ?? []),
        ];

        foreach (['help', 'width', 'group', 'options', 'when', 'requiredWhen', 'accessor', 'default', 'relation', 'fields'] as $option) {
            $value = $resolved[$option] ?? $defaults[$option] ?? null;

            if ($value !== null) {
                $field[$option] = $value;
            }
        }

        // `required` is also a top-level flag, because the panel renders the
        // asterisk from it rather than from the props.
        if (($resolved['required'] ?? false) === true) {
            $field['required'] = true;
            $field['rules'] = ['required' => true] + $field['rules'];
        }

        $this->fields[] = $field;

        return $this;
    }

    /**
     * Field definitions, in declaration order.
     *
     * Empty `props`/`rules` are dropped so the payload sent to the panel — and
     * anything comparing definitions in a test — stays free of noise.
     *
     * @return list<array<string, mixed>>
     */
    public function fields(): array
    {
        return array_map(
            static fn (array $field): array => array_filter(
                $field,
                static fn (mixed $value, string $key): bool => !in_array($key, ['props', 'rules'], true) || $value !== [],
                ARRAY_FILTER_USE_BOTH,
            ),
            $this->fields,
        );
    }

    /**
     * Rule combinations that can never pass are declaration bugs; surface
     * them where the blueprint is written, not on the first submission.
     *
     * @param array<string, mixed> $rules
     */
    private static function assertSaneRules(string $key, array $rules): void
    {
        if (isset($rules['min'], $rules['max']) && (int) $rules['min'] > (int) $rules['max']) {
            throw new \InvalidArgumentException(sprintf(
                'Field "%s": min (%d) exceeds max (%d) — no value can satisfy both.',
                $key,
                (int) $rules['min'],
                (int) $rules['max'],
            ));
        }

        if (isset($rules['pattern']) && \is_string($rules['pattern']) && false === @preg_match($rules['pattern'], '')) {
            throw new \InvalidArgumentException(sprintf(
                'Field "%s": "%s" is not a valid regular expression.',
                $key,
                $rules['pattern'],
            ));
        }
    }

    /**
     * @param  array<string, mixed> $resolved
     * @return array<string, mixed>
     */
    private static function controlProps(array $resolved): array
    {
        $props = [];

        foreach (self::CONTROL_OPTIONS as $option) {
            if (($resolved[$option] ?? null) !== null) {
                $props[$option] = $resolved[$option];
            }
        }

        return $props;
    }

    private function resolver(): OptionsResolver
    {
        $resolver = new OptionsResolver();

        $resolver->setDefined([
            // Presentation
            'label', 'help', 'width', 'placeholder',
            // Inline affordances around the control (units, URL stems) and
            // the editor tab this field renders under.
            'prefix', 'postfix', 'group',
            // Behaviour
            'required', 'disabled', 'readonly', 'autofocus', 'default',
            // Data
            'options', 'rules', 'props',
            // Conditional visibility, evaluated in the panel against the form
            // AND re-evaluated by FieldValidator on the server.
            'when',
            // Conditionally required — same condition shape as `when`.
            'requiredWhen',
            // A ComputedType's server-side source: the method on the record
            // (or presenter) whose return value the field displays.
            'accessor',
            // A BelongsTo field's related entity, held as data (RelationOptions)
            // and resolved to a flat option list where the EntityManager lives.
            'relation',
            // A HasMany field's sub-field declarations — built with a nested
            // BlueprintBuilder; the child entity itself is never named here,
            // Doctrine's association mapping answers that.
            'fields',
        ]);

        $resolver->setAllowedTypes('label', 'string');
        $resolver->setAllowedTypes('help', 'string');
        $resolver->setAllowedTypes('width', 'string');
        $resolver->setAllowedTypes('placeholder', 'string');
        $resolver->setAllowedTypes('prefix', 'string');
        $resolver->setAllowedTypes('postfix', 'string');
        $resolver->setAllowedTypes('group', 'string');
        $resolver->setAllowedTypes('required', 'bool');
        $resolver->setAllowedTypes('disabled', 'bool');
        $resolver->setAllowedTypes('readonly', 'bool');
        $resolver->setAllowedTypes('autofocus', 'bool');
        $resolver->setAllowedTypes('options', 'array');
        $resolver->setAllowedTypes('rules', 'array');
        $resolver->setAllowedTypes('props', 'array');
        $resolver->setAllowedTypes('when', 'array');
        $resolver->setAllowedTypes('requiredWhen', 'array');
        $resolver->setAllowedTypes('accessor', 'string');
        $resolver->setAllowedTypes('relation', \Modufolio\Panel\Table\RelationOptions::class);
        $resolver->setAllowedTypes('fields', 'array');

        // The panel lays fields out on a twelve-column grid.
        $resolver->setAllowedValues('width', ['1/4', '1/3', '1/2', '2/3', '3/4', 'full']);

        return $resolver;
    }

    private static function humanize(string $key): string
    {
        return ucwords(str_replace('_', ' ', $key));
    }
}
