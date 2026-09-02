<?php

declare(strict_types=1);

namespace Modufolio\Panel\Tests\Database;

use Modufolio\Panel\Resource\RelationOptionResolver;
use Modufolio\Panel\Table\RelationOptions;
use Modufolio\Panel\Tests\Case\DoctrineTestCase;
use Modufolio\Panel\Tests\Fixture\Entity\Actor;
use Modufolio\Panel\Tests\Fixture\Entity\CastMember;
use Modufolio\Panel\Tests\Fixture\Entity\Credit;
use Modufolio\Panel\Tests\Fixture\Entity\Studio;
use Modufolio\Panel\Tests\Fixture\Entity\Tag;

/**
 * The resolver is where a {@see RelationOptions} declaration meets rows.
 *
 * The rule the feature turns on is pinned from both sides: a relation small
 * enough to scroll ships whole, a larger one is searched — and a search never
 * truncates silently. The LIKE escaping gets its own tests because it is the
 * kind of thing that works on one driver and quietly breaks on the next.
 */
final class RelationOptionResolverTest extends DoctrineTestCase
{
    private function resolver(): RelationOptionResolver
    {
        return new RelationOptionResolver(self::em());
    }

    private function studios(?bool $searchable = null): RelationOptions
    {
        return new RelationOptions(Studio::class, 'name', 'uuid', $searchable);
    }

    /**
     * Persist one studio per name, flushing once.
     *
     * @param list<string> $names
     *
     * @return array<string, Studio> by name
     */
    private function persistStudios(array $names): array
    {
        $studios = [];

        foreach ($names as $name) {
            $studios[$name] = (new Studio())->setName($name);
        }

        $this->persist(...array_values($studios));

        return $studios;
    }

    /** Persist `$count` studios with distinct names, flushing once. */
    private function persistManyStudios(int $count): void
    {
        $studios = [];

        for ($i = 1; $i <= $count; ++$i) {
            $studios[] = (new Studio())->setName(sprintf('Studio %03d', $i));
        }

        $this->persist(...$studios);
    }

    /**
     * @param list<array{value: string, label: string}> $options
     *
     * @return list<string>
     */
    private function labels(array $options): array
    {
        return array_column($options, 'label');
    }

    // ── all() ────────────────────────────────────────────────────────────────

    public function testAllReturnsEveryRowOrderedByLabel(): void
    {
        $studios = $this->persistStudios(['Warner Bros.', 'A24', 'Miramax']);
        $this->clear();

        $options = $this->resolver()->all($this->studios());

        self::assertSame(['A24', 'Miramax', 'Warner Bros.'], $this->labels($options));
        self::assertSame(
            $studios['A24']->getUuid()->toString(),
            $options[0]['value'],
            'The value is the declared value field, as a string.',
        );
        self::assertSame(['value', 'label'], array_keys($options[0]), 'Nothing else about the row travels.');
    }

    public function testAllIsEmptyWhenTheTableIs(): void
    {
        self::assertSame([], $this->resolver()->all($this->studios()));
    }

    // ── isSearchable() ───────────────────────────────────────────────────────

    public function testADeclarationCanForceSearchOnRegardlessOfSize(): void
    {
        self::assertTrue($this->resolver()->isSearchable($this->studios(searchable: true)));
    }

    public function testADeclarationCanForceSearchOffRegardlessOfSize(): void
    {
        $this->persistManyStudios(RelationOptions::AUTO_SEARCH_THRESHOLD + 1);

        self::assertFalse($this->resolver()->isSearchable($this->studios(searchable: false)));
    }

    /**
     * Left undecided — which is what a guessed relation gets — the row count
     * decides, and the threshold itself is still "small enough to scroll".
     */
    public function testAnUndecidedRelationIsSearchableOnlyAboveTheThreshold(): void
    {
        $resolver = $this->resolver();

        self::assertFalse($resolver->isSearchable($this->studios()), 'Empty.');

        $this->persistManyStudios(RelationOptions::AUTO_SEARCH_THRESHOLD);
        self::assertFalse($resolver->isSearchable($this->studios()), 'Exactly at the threshold.');

        $this->persist((new Studio())->setName('One more'));
        self::assertTrue($resolver->isSearchable($this->studios()), 'One past it.');
    }

    // ── search() ─────────────────────────────────────────────────────────────

    public function testSearchMatchesTheLabelCaseInsensitively(): void
    {
        $this->persistStudios(['Warner Bros.', 'A24', 'Miramax']);

        $result = $this->resolver()->search($this->studios(), 'WARNER');

        self::assertSame(['Warner Bros.'], $this->labels($result['data']));
        self::assertSame(['total' => 1, 'limit' => RelationOptions::SEARCH_LIMIT, 'truncated' => false], $result['meta']);
    }

    public function testSearchMatchesAnywhereInTheLabel(): void
    {
        $this->persistStudios(['Warner Bros.', 'A24', 'Miramax']);

        self::assertSame(['Miramax'], $this->labels($this->resolver()->search($this->studios(), 'ram')['data']));
    }

    public function testSearchResultsAreOrderedByLabel(): void
    {
        $this->persistStudios(['Studio C', 'Studio A', 'Studio B']);

        self::assertSame(
            ['Studio A', 'Studio B', 'Studio C'],
            $this->labels($this->resolver()->search($this->studios(), 'studio')['data']),
        );
    }

    public function testAnEmptyTermReturnsEverything(): void
    {
        $this->persistStudios(['Warner Bros.', 'A24', 'Miramax']);

        $result = $this->resolver()->search($this->studios(), '');

        self::assertSame(['A24', 'Miramax', 'Warner Bros.'], $this->labels($result['data']));
        self::assertFalse($result['meta']['truncated']);
    }

    public function testNoMatchIsAnEmptyResultNotAnError(): void
    {
        $this->persistStudios(['Warner Bros.']);

        $result = $this->resolver()->search($this->studios(), 'paramount');

        self::assertSame([], $result['data']);
        self::assertSame(0, $result['meta']['total']);
    }

    /**
     * `%` is a LIKE wildcard; a user typing one means the character. Without
     * escaping, "0%" would also match "100x" (and everything else starting
     * with 0).
     */
    public function testAPercentSignInTheTermIsLiteral(): void
    {
        $this->persistStudios(['100%', '100x']);

        self::assertSame(['100%'], $this->labels($this->resolver()->search($this->studios(), '0%')['data']));
    }

    /** `_` is the single-character wildcard; same story. */
    public function testAnUnderscoreInTheTermIsLiteral(): void
    {
        $this->persistStudios(['a_b', 'axb']);

        self::assertSame(['a_b'], $this->labels($this->resolver()->search($this->studios(), '_b')['data']));
    }

    /**
     * The escape character itself must survive being typed — it is doubled
     * on the way in, or a term containing it would match nothing at all.
     */
    public function testTheEscapeCharacterItselfCanBeSearchedFor(): void
    {
        $this->persistStudios(['Wow!', 'Wow']);

        self::assertSame(['Wow!'], $this->labels($this->resolver()->search($this->studios(), '!')['data']));
    }

    /**
     * The cap is reported, not hidden: one row beyond the limit is fetched
     * to learn that more exist, and the client is told so.
     */
    public function testAResultBeyondTheLimitIsCappedAndSaysSo(): void
    {
        $this->persistManyStudios(RelationOptions::SEARCH_LIMIT + 1);

        $result = $this->resolver()->search($this->studios(), 'studio');

        self::assertCount(RelationOptions::SEARCH_LIMIT, $result['data']);
        self::assertSame(
            ['total' => RelationOptions::SEARCH_LIMIT, 'limit' => RelationOptions::SEARCH_LIMIT, 'truncated' => true],
            $result['meta'],
        );
        self::assertSame('Studio 001', $result['data'][0]['label'], 'The first page by label.');
        self::assertSame('Studio 050', $result['data'][RelationOptions::SEARCH_LIMIT - 1]['label']);
    }

    public function testAResultExactlyAtTheLimitIsNotTruncated(): void
    {
        $this->persistManyStudios(RelationOptions::SEARCH_LIMIT);

        $result = $this->resolver()->search($this->studios(), '');

        self::assertCount(RelationOptions::SEARCH_LIMIT, $result['data']);
        self::assertFalse($result['meta']['truncated']);
    }

    // ── byValues() ───────────────────────────────────────────────────────────

    /**
     * A searchable field's record carries an identifier and no label, since
     * the list was never sent; this labels exactly the selection.
     */
    public function testByValuesLabelsOnlyTheGivenIdentifiers(): void
    {
        $studios = $this->persistStudios(['Warner Bros.', 'A24', 'Miramax']);
        $this->clear();

        $options = $this->resolver()->byValues($this->studios(), [
            $studios['Miramax']->getUuid()->toString(),
            $studios['A24']->getUuid()->toString(),
        ]);

        $labels = $this->labels($options);
        sort($labels);

        self::assertSame(['A24', 'Miramax'], $labels);
    }

    /** An empty string is what a cleared control submits; it names nothing. */
    public function testByValuesIgnoresEmptyStrings(): void
    {
        $studios = $this->persistStudios(['Warner Bros.', 'A24']);

        $options = $this->resolver()->byValues($this->studios(), ['', $studios['A24']->getUuid()->toString()]);

        self::assertSame(['A24'], $this->labels($options));
        self::assertSame([], $this->resolver()->byValues($this->studios(), ['', '']));
    }

    public function testByValuesWithNothingToResolveReturnsNothing(): void
    {
        $this->persistStudios(['Warner Bros.']);

        self::assertSame([], $this->resolver()->byValues($this->studios(), []));
    }

    public function testByValuesSkipsIdentifiersThatMatchNoRow(): void
    {
        $studios = $this->persistStudios(['Warner Bros.']);

        $options = $this->resolver()->byValues($this->studios(), [
            $studios['Warner Bros.']->getUuid()->toString(),
            '00000000-0000-4000-8000-000000000000',
        ]);

        self::assertSame(['Warner Bros.'], $this->labels($options));
    }

    // ── creatableFromLabel() ─────────────────────────────────────────────────

    /** A studio needs only its name: `city` is nullable, identity is its own. */
    public function testATargetWithOnlyOptionalColumnsBesidesTheLabelIsCreatable(): void
    {
        self::assertTrue($this->resolver()->creatableFromLabel($this->studios()));
        self::assertTrue($this->resolver()->creatableFromLabel(new RelationOptions(Tag::class, 'name', 'uuid')));
        self::assertTrue($this->resolver()->creatableFromLabel(new RelationOptions(Actor::class, 'name', 'uuid')));
    }

    /** A credit cannot exist without its movie: the join column refuses null. */
    public function testATargetWithARequiredToOneIsNotCreatable(): void
    {
        self::assertFalse($this->resolver()->creatableFromLabel(new RelationOptions(Credit::class, 'note', 'id')));
    }

    /**
     * A cast member is blocked twice over — its `movie` join column refuses
     * null, and `position` is a non-nullable column without a default — and
     * either alone would do.
     */
    public function testATargetWithARequiredColumnBesidesTheLabelIsNotCreatable(): void
    {
        self::assertFalse($this->resolver()->creatableFromLabel(new RelationOptions(CastMember::class, 'character', 'uuid')));
    }

    /** A label field the entity does not map could never be set, let alone inserted. */
    public function testATargetWhoseLabelFieldIsNotMappedIsNotCreatable(): void
    {
        self::assertFalse($this->resolver()->creatableFromLabel(new RelationOptions(Studio::class, 'title', 'uuid')));
    }

    // ── findByLabel() / newFromLabel() / option() ────────────────────────────

    public function testFindByLabelReturnsTheRowCarryingExactlyThatLabel(): void
    {
        $studios = $this->persistStudios(['Warner Bros.', 'A24']);
        $this->clear();

        $found = $this->resolver()->findByLabel($this->studios(), 'A24');

        self::assertInstanceOf(Studio::class, $found);
        self::assertSame($studios['A24']->getId(), $found->getId());
    }

    public function testFindByLabelReturnsNullWhenNoRowCarriesIt(): void
    {
        $this->persistStudios(['Warner Bros.']);

        self::assertNull($this->resolver()->findByLabel($this->studios(), 'Paramount'));
    }

    /** The caller validates and decides whether it is persisted; nothing is written here. */
    public function testNewFromLabelIsAnUnflushedInstanceCarryingTheLabel(): void
    {
        $studio = $this->resolver()->newFromLabel($this->studios(), 'Paramount');

        self::assertInstanceOf(Studio::class, $studio);
        self::assertSame('Paramount', $studio->getName());
        self::assertNull($studio->getId(), 'Never flushed.');
        self::assertFalse(self::em()->contains($studio), 'Not even persisted.');
        self::assertNull($this->resolver()->findByLabel($this->studios(), 'Paramount'), 'And so not in the table.');
    }

    public function testOptionShapesOneRowTheWayTheEndpointsSpeak(): void
    {
        $studio = (new Studio())->setName('A24');

        self::assertSame(
            ['value' => $studio->getUuid()->toString(), 'label' => 'A24'],
            $this->resolver()->option($this->studios(), $studio),
        );
    }

    /** The value field is whatever the declaration names, cast to string. */
    public function testOptionUsesTheDeclaredValueField(): void
    {
        $studios = $this->persistStudios(['A24']);

        self::assertSame(
            ['value' => (string) $studios['A24']->getId(), 'label' => 'A24'],
            $this->resolver()->option(new RelationOptions(Studio::class, 'name', 'id'), $studios['A24']),
        );
    }
}
