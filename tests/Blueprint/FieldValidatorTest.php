<?php

declare(strict_types=1);

namespace Modufolio\Panel\Tests\Blueprint;

use Modufolio\Panel\Blueprint\BlueprintBuilder;
use Modufolio\Panel\Blueprint\Defaults;
use Modufolio\Panel\Blueprint\FieldAccess;
use Modufolio\Panel\Blueprint\FieldValidator;
use Modufolio\Panel\Field\TextType;
use Modufolio\Panel\Resource\Permissions;
use PHPUnit\Framework\TestCase;

final class FieldValidatorTest extends TestCase
{
    public function testCustomMessagesOverrideTheBuiltInWording(): void
    {
        $blueprint = [[
            'key' => 'title',
            'label' => 'Title',
            'rules' => ['max' => 5, 'messages' => ['max' => 'Keep titles short.']],
        ]];

        $errors = FieldValidator::validate($blueprint, ['title' => 'much too long']);

        $this->assertSame('Keep titles short.', $errors['title']);
    }

    public function testAFieldHiddenByWhenIsNeitherValidatedNorAccepted(): void
    {
        $blueprint = [[
            'key' => 'depublish_at',
            'label' => 'Depublish at',
            'when' => ['status', 'published'],
            'rules' => ['required' => true],
        ]];

        // Hidden (status != published): required does not fire…
        $this->assertSame([], FieldValidator::validate($blueprint, ['status' => 'draft']));
        // …and a smuggled value is stripped before persisting.
        $stripped = FieldValidator::stripHidden($blueprint, ['status' => 'draft', 'depublish_at' => 'x']);
        $this->assertArrayNotHasKey('depublish_at', $stripped);

        // Visible: the rule decides again, and the value survives.
        $this->assertArrayHasKey('depublish_at', FieldValidator::validate($blueprint, ['status' => 'published']));
        $kept = FieldValidator::stripHidden($blueprint, ['status' => 'published', 'depublish_at' => 'x']);
        $this->assertSame('x', $kept['depublish_at']);
    }

    public function testRequiredWhenMakesEmptinessConditional(): void
    {
        $blueprint = [[
            'key' => 'published_at',
            'label' => 'Published at',
            'requiredWhen' => ['status', 'scheduled'],
        ]];

        $this->assertSame([], FieldValidator::validate($blueprint, ['status' => 'draft']));
        $this->assertSame(
            ['published_at' => 'Published at is required.'],
            FieldValidator::validate($blueprint, ['status' => 'scheduled']),
        );
    }

    public function testInsaneRuleCombinationsFailAtDeclarationTime(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/min \(10\) exceeds max \(3\)/');

        (new BlueprintBuilder())->add('title', TextType::class, ['rules' => ['min' => 10, 'max' => 3]]);
    }

    public function testAnInvalidPatternFailsAtDeclarationTime(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/not a valid regular expression/');

        (new BlueprintBuilder())->add('slug', TextType::class, ['rules' => ['pattern' => '/[unclosed']]);
    }

    public function testDefaultsResolveSentinels(): void
    {
        $now = new \DateTimeImmutable('2026-09-01 12:00:00');
        $blueprint = [
            ['key' => 'published_at', 'default' => '@now'],
            ['key' => 'day', 'default' => '@today'],
            ['key' => 'status', 'default' => 'draft'],
            ['key' => 'title', 'default' => 'Untitled'],
        ];

        $values = Defaults::resolve($blueprint, ['title' => 'Kept'], $now);

        $this->assertSame('2026-09-01 12:00:00', $values['published_at']);
        $this->assertSame('2026-09-01', $values['day']);
        $this->assertSame('draft', $values['status']);
        $this->assertSame('Kept', $values['title'], 'A submitted value beats the default.');
    }

    public function testFieldAccessHidesReadDeniedAndStripsWriteDenied(): void
    {
        $builder = new BlueprintBuilder();
        $builder->add('title', TextType::class);
        $builder->add('internal_notes', TextType::class);
        $builder->add('slug', TextType::class);

        $permissions = new class extends Permissions {
            public function readable(string $field, ?object $user, ?object $record = null): bool
            {
                return $field !== 'internal_notes' || ($user instanceof FlaggedUser && $user->admin);
            }

            public function writable(string $field, ?object $user, ?object $record = null): bool
            {
                return $field !== 'slug';
            }
        };

        $admin = new FlaggedUser(admin: true);
        $editor = new FlaggedUser(admin: false);

        $forAdmin = FieldAccess::resolve($builder->fields(), $permissions, $admin);
        $forEditor = FieldAccess::resolve($builder->fields(), $permissions, $editor);

        $this->assertContains('internal_notes', array_column($forAdmin, 'key'));
        $this->assertNotContains('internal_notes', array_column($forEditor, 'key'), 'Hidden means never shipped.');

        $slug = array_values(array_filter($forAdmin, static fn (array $f): bool => 'slug' === $f['key']))[0];
        $this->assertTrue($slug['props']['disabled'], 'Write-denied renders disabled.');

        $values = FieldAccess::stripDenied($builder->fields(), $permissions, [
            'title' => 'ok', 'internal_notes' => 'smuggled', 'slug' => 'hacked',
        ], $editor);

        $this->assertSame(['title' => 'ok'], $values, 'Disabling the input is presentation; this is the guard.');
    }
}

/** A user whose only trait is whether it is an admin. */
final class FlaggedUser
{
    public function __construct(public readonly bool $admin) {}
}
