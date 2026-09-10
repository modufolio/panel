<?php

declare(strict_types=1);

namespace Modufolio\Panel\Metric;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Modufolio\Appkit\Security\User\UserInterface;
use Psr\Clock\ClockInterface;
use Modufolio\Panel\Resource\PanelResource;
use Modufolio\Panel\Support\Label;
use Modufolio\Panel\Table\Summary;

/**
 * Turns a resource's declared {@see Metric}s into numbers.
 *
 * Every query starts from the same place the listing does — the resource's
 * repository, narrowed by `Permissions::scope()` and with soft-deleted rows
 * left out — so a metric can never count what its viewer may not open.
 *
 * What a metric is *not* narrowed by is the table's current filters. A metric
 * describes the resource ("1,204 movies, 38 added this month"), not the
 * question the list is currently asking; a number that moved every time
 * someone typed in the search box would be a second, quieter summary row, and
 * columns already have those.
 *
 * Trends bucket in PHP rather than in SQL. Date truncation is the one thing
 * every database spells differently, and DQL has no portable form of it, so
 * the window's dates are read and grouped here — bounded by the window, which
 * is why a window is required.
 */
final class MetricCalculator
{
    /**
     * A bare entity field: letters, digits and underscores, not starting with a
     * digit — the grammar appkit's own query builder validates against.
     *
     * Nothing here comes from a request, so this is not the injection guard
     * that one is; it is what turns `->by('genre.name')` or a typo into a
     * sentence naming the metric, instead of a DQL parse error naming a token.
     */
    private const FIELD = '/^[A-Za-z_][A-Za-z0-9_]*$/';

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ClockInterface $clock,
    ) {}

    /**
     * Every metric the resource declares, computed, keyed by metric key.
     *
     * @return list<array<string, mixed>>
     */
    public function compute(PanelResource $resource, ?UserInterface $user = null): array
    {
        $computed = [];

        foreach ($resource->metrics() as $metric) {
            $metric->validate();

            foreach ([$metric->field(), $metric->dateField()] as $field) {
                if ($field !== null) {
                    self::assertField($metric, $field);
                }
            }

            if ($metric->type() === Metric::PARTITION) {
                self::assertField($metric, $metric->groupField());
            }

            $computed[] = [
                ...$metric->toArray(),
                ...match ($metric->type()) {
                    Metric::VALUE     => $this->value($resource, $metric, $user),
                    Metric::TREND     => $this->trend($resource, $metric, $user),
                    Metric::PARTITION => $this->partition($resource, $metric, $user),
                    default           => throw new \LogicException(sprintf('Unknown metric type "%s".', $metric->type())),
                },
            ];
        }

        return $computed;
    }

    // ── The three shapes ─────────────────────────────────────────────────────

    /**
     * @return array{value: float|int|null, previous?: float|int|null, change?: float|null}
     */
    private function value(PanelResource $resource, Metric $metric, ?UserInterface $user): array
    {
        $window = $metric->windowLength();
        $bucket = $metric->bucket();
        $now    = $this->clock->now();

        $from = $window !== null && $bucket !== null ? $this->windowStart($now, $bucket, $window) : null;

        $value = $this->aggregate($resource, $metric, $user, $from, $from !== null ? $now : null);

        // A comparison needs a window; validate() has already refused a
        // declaration that asks for one without it, so $from stands for both.
        if (!$metric->wantsComparison() || $from === null) {
            return ['value' => $value];
        }

        // The window before this one, of the same length and ending where this
        // one starts — so "38 this month" is compared with the month before it.
        $previousFrom = $this->windowStart($from, (string) $bucket, (int) $window);
        $previous     = $this->aggregate($resource, $metric, $user, $previousFrom, $from);

        return [
            'value'    => $value,
            'previous' => $previous,
            'change'   => self::percentageChange($value, $previous),
        ];
    }

    /**
     * @return array{value: float|int, series: list<array{label: string, value: float|int}>}
     */
    private function trend(PanelResource $resource, Metric $metric, ?UserInterface $user): array
    {
        $bucket = (string) $metric->bucket();
        $length = (int) $metric->windowLength();
        $field  = (string) $metric->dateField();
        $alias  = $resource->queryAlias();
        $now    = $this->clock->now();
        $from   = $this->windowStart($now, $bucket, $length);

        $qb = $this->scopedQuery($resource, $user);
        $qb->andWhere("{$alias}.{$field} >= :metricFrom")->setParameter('metricFrom', $from);

        $select = ["{$alias}.{$field} AS bucketDate"];

        if ($metric->field() !== null) {
            $select[] = "{$alias}.{$metric->field()} AS bucketValue";
        }

        /** @var list<array<string, mixed>> $rows */
        $rows = $qb->select(implode(', ', $select))->getQuery()->getArrayResult();

        // Every bucket in the window exists, including the empty ones: a chart
        // with three points and a gap in the middle reads as three days, and a
        // quiet Sunday is information.
        $buckets = [];

        foreach ($this->bucketLabels($now, $bucket, $length) as $label) {
            $buckets[$label] = [];
        }

        foreach ($rows as $row) {
            $date = $row['bucketDate'] ?? null;

            if (!$date instanceof \DateTimeInterface) {
                continue;
            }

            $label = $date->format($bucket === 'month' ? 'Y-m' : 'Y-m-d');

            if (!array_key_exists($label, $buckets)) {
                continue;
            }

            $buckets[$label][] = $metric->field() === null ? 1 : (float) ($row['bucketValue'] ?? 0);
        }

        $series = [];
        $total  = 0.0;

        foreach ($buckets as $label => $values) {
            $value    = self::fold($metric->aggregate(), $values);
            $total   += $metric->aggregate()->type() === Summary::AVERAGE ? 0 : $value;
            $series[] = ['label' => $label, 'value' => $value];
        }

        return [
            // The headline beside the chart: the window's own total, or its
            // average where summing averages would mean nothing. Normalised the
            // same way a value metric is, so a count reads as 3 on both cards
            // rather than as 3 on one and 3.0 on the other.
            'value'  => self::number($metric->aggregate()->type() === Summary::AVERAGE
                ? self::fold($metric->aggregate(), array_merge(...array_values($buckets)))
                : $total),
            'series' => $series,
        ];
    }

    /**
     * @return array{slices: list<array{label: string, value: float|int, color?: string}>}
     */
    private function partition(PanelResource $resource, Metric $metric, ?UserInterface $user): array
    {
        $alias = $resource->queryAlias();
        $field = $metric->groupField();
        $qb    = $this->scopedQuery($resource, $user);

        /** @var list<array<string, mixed>> $rows */
        $rows = $qb
            ->select("{$alias}.{$field} AS sliceLabel, " . $metric->aggregate()->expression(
                $metric->field() === null ? "{$alias}.id" : "{$alias}.{$metric->field()}",
            ) . ' AS sliceValue')
            ->groupBy("{$alias}.{$field}")
            ->getQuery()
            ->getArrayResult();

        $colors = $metric->colorMap();
        $slices = [];

        foreach ($rows as $row) {
            $raw = $row['sliceLabel'] ?? null;

            // An enumType column hydrates as its case; a plain one as a scalar.
            $value = $raw instanceof \BackedEnum ? (string) $raw->value : (is_scalar($raw) ? (string) $raw : '');

            $slices[] = array_filter([
                'label' => $this->sliceLabel($raw, $value),
                'value' => self::number($row['sliceValue'] ?? 0),
                'color' => $colors[$value] ?? null,
            ], static fn (mixed $item): bool => $item !== null);
        }

        // Largest first, ties broken by label. The tie-break is not cosmetic:
        // the query groups without ordering, so equal counts come back in
        // whatever order the engine chose — MySQL, PostgreSQL and SQL Server
        // each chose differently for the same rows — and a stable sort then
        // preserves that difference. Without a total order the slice order
        // varies by engine, and under a `limit()` so does *which* slices
        // survive and which are summed into "Other".
        usort(
            $slices,
            static fn (array $a, array $b): int
                => [$b['value'], $a['label']] <=> [$a['value'], $b['label']],
        );

        return ['slices' => $this->capped($slices, $metric->slices())];
    }

    // ── Querying ─────────────────────────────────────────────────────────────

    /**
     * The resource's rows, as this viewer may see them: scoped, and without
     * the soft-deleted ones a listing hides by default.
     */
    private function scopedQuery(PanelResource $resource, ?UserInterface $user): QueryBuilder
    {
        $alias = $resource->queryAlias();
        $qb    = $this->entityManager->getRepository($resource->entityClass())->createQueryBuilder($alias);

        $resource->permissions()->scope($qb, $alias, $user);

        if ($this->entityManager->getClassMetadata($resource->entityClass())->hasField('deletedAt')) {
            $qb->andWhere("{$alias}.deletedAt IS NULL");
        }

        return $qb;
    }

    private function aggregate(
        PanelResource $resource,
        Metric $metric,
        ?UserInterface $user,
        ?\DateTimeImmutable $from,
        ?\DateTimeImmutable $until,
    ): float|int|null {
        $alias = $resource->queryAlias();
        $qb    = $this->scopedQuery($resource, $user);

        if ($from !== null && $metric->dateField() !== null) {
            $qb->andWhere("{$alias}.{$metric->dateField()} >= :metricFrom")->setParameter('metricFrom', $from);
        }

        if ($until !== null && $metric->dateField() !== null) {
            $qb->andWhere("{$alias}.{$metric->dateField()} < :metricUntil")->setParameter('metricUntil', $until);
        }

        $expression = $metric->aggregate()->expression(
            $metric->field() === null ? "{$alias}.id" : "{$alias}.{$metric->field()}",
        );

        /** @var mixed $result */
        $result = $qb->select($expression)->getQuery()->getSingleScalarResult();

        return $result === null ? null : self::number($result);
    }

    // ── Windows and folding ──────────────────────────────────────────────────

    private function windowStart(\DateTimeImmutable $end, string $bucket, int $length): \DateTimeImmutable
    {
        // N buckets *including* the one in progress, so `days(7)` is this day
        // and the six before it rather than eight days on the chart.
        return $bucket === 'month'
            ? $end->modify('first day of this month')->setTime(0, 0)->modify('-' . ($length - 1) . ' months')
            : $end->setTime(0, 0)->modify('-' . ($length - 1) . ' days');
    }

    /** @return list<string> */
    private function bucketLabels(\DateTimeImmutable $end, string $bucket, int $length): array
    {
        $labels = [];
        $cursor = $this->windowStart($end, $bucket, $length);

        for ($index = 0; $index < $length; $index++) {
            $labels[] = $cursor->format($bucket === 'month' ? 'Y-m' : 'Y-m-d');
            $cursor   = $cursor->modify($bucket === 'month' ? '+1 month' : '+1 day');
        }

        return $labels;
    }

    /**
     * One bucket's rows reduced the way the metric's aggregate would have
     * reduced them in SQL.
     *
     * @param list<float|int> $values
     */
    private static function fold(Summary $aggregate, array $values): float|int
    {
        if ($values === []) {
            return 0;
        }

        return match ($aggregate->type()) {
            Summary::COUNT   => count($values),
            Summary::SUM     => array_sum($values),
            Summary::AVERAGE => round(array_sum($values) / count($values), 4),
            Summary::MIN     => min($values),
            Summary::MAX     => max($values),
            default          => count($values),
        };
    }

    /**
     * Keep the largest N slices and sum the rest, so a partition over a field
     * with a long tail stays a chart rather than a list.
     *
     * @param  list<array{label: string, value: float|int, color?: string}> $slices
     * @return list<array{label: string, value: float|int, color?: string}>
     */
    private function capped(array $slices, ?int $limit): array
    {
        if ($limit === null || count($slices) <= $limit) {
            return $slices;
        }

        $kept = array_slice($slices, 0, $limit);
        $rest = array_slice($slices, $limit);

        $kept[] = [
            'label' => 'Other',
            'value' => array_sum(array_column($rest, 'value')),
        ];

        return $kept;
    }

    /** An enum case's own label where it has one, else the humanised value. */
    private function sliceLabel(mixed $raw, string $value): string
    {
        if ($raw instanceof \BackedEnum && method_exists($raw, 'getLabel')) {
            return (string) $raw->getLabel();
        }

        return $value === '' ? '—' : Label::sentence($value);
    }

    /** @throws \LogicException when a metric names something that is not a field of the entity */
    private static function assertField(Metric $metric, string $field): void
    {
        if (preg_match(self::FIELD, $field) !== 1) {
            throw new \LogicException(sprintf(
                'Metric "%s" names "%s", which is not a field of the entity. '
                . 'A metric aggregates the entity\'s own columns; a path across a relation needs a query that joins it.',
                $metric->key(),
                $field,
            ));
        }
    }

    /**
     * A number the client can render without knowing which database answered.
     *
     * SQLite returns a count as an int, MySQL as a numeric string, and folding
     * a bucket in PHP produces a float — three spellings of 3 that would reach
     * the same card differently. A whole number comes back as an int; anything
     * with a fraction keeps it, and the metric's own `decimals` decides how it
     * is written out.
     */
    private static function number(mixed $value): float|int
    {
        if (is_int($value)) {
            return $value;
        }

        $number = is_float($value) ? $value : (float) (is_scalar($value) ? $value : 0);

        return $number === floor($number) && abs($number) < PHP_INT_MAX ? (int) $number : $number;
    }

    private static function percentageChange(float|int|null $value, float|int|null $previous): ?float
    {
        // Nothing to divide by: a rise from zero is not a percentage, and
        // saying "+100%" for the first row of data is a lie the card would
        // then have to be read around.
        if ($previous === null || (float) $previous === 0.0 || $value === null) {
            return null;
        }

        return round((((float) $value - (float) $previous) / abs((float) $previous)) * 100, 1);
    }
}
