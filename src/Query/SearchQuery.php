<?php

declare(strict_types=1);

namespace Modufolio\Panel\Query;

use Doctrine\ORM\QueryBuilder;

/**
 * A case-insensitive `LIKE` across the given paths, OR-ed — once per word
 * of the search, AND-ed, so every word has to be found somewhere.
 *
 * A path is an entity property (`title`) or one step into a to-one relation
 * (`studio.name`), which is left-joined under a stable alias so the listing
 * and its count share one predicate. A to-one join cannot multiply rows, so
 * the count stays correct; a to-many path would, and is refused.
 *
 * One of the objects {@see DerivedListQuery} chains; usable by hand in a
 * list query class exactly like {@see FilterTrashedQuery}.
 */
final class SearchQuery extends AbstractQuery
{
    /**
     * @param list<string> $paths
     */
    public function __construct(
        private readonly ?string $term,
        private readonly array $paths,
    ) {
    }

    public function apply(QueryBuilder $qb): QueryBuilder
    {
        $terms = self::terms((string) $this->term);

        if ($terms === [] || $this->paths === []) {
            return $qb;
        }

        $alias = $this->getRootAlias($qb);

        // Every word must match somewhere, in any column: "john smith" finds
        // a first name in one column and a last name in another. A quoted
        // phrase stays one term.
        foreach ($terms as $index => $term) {
            $or = $qb->expr()->orX();

            foreach ($this->paths as $path) {
                $or->add($qb->expr()->like('LOWER(' . $this->column($qb, $alias, $path) . ')', ':search_' . $index));
            }

            $qb->andWhere($or)->setParameter('search_' . $index, '%' . mb_strtolower($term) . '%');
        }

        return $qb;
    }

    /**
     * The words of a search, lower-cased and de-duplicated; a phrase in
     * double quotes is one word. Shared with anything else that searches
     * the way the listing does.
     *
     * @return list<string>
     */
    public static function terms(string $search): array
    {
        $search = trim($search);

        if ($search === '') {
            return [];
        }

        $words = str_getcsv($search, ' ', '"', '\\');
        $terms = [];

        foreach ($words as $word) {
            $word = trim((string) $word);

            if ($word !== '' && !in_array($word, $terms, true)) {
                $terms[] = $word;
            }
        }

        return $terms;
    }

    private function column(QueryBuilder $qb, string $alias, string $path): string
    {
        $segments = explode('.', $path);

        if (count($segments) === 1) {
            return "{$alias}.{$path}";
        }

        if (count($segments) > 2) {
            throw new \InvalidArgumentException(sprintf(
                'SearchQuery: "%s" crosses more than one relation; search paths reach one step into a to-one relation.',
                $path,
            ));
        }

        [$relation, $field] = $segments;
        $joinAlias          = 'search_' . $relation;

        if (!in_array($joinAlias, $qb->getAllAliases(), true)) {
            $qb->leftJoin("{$alias}.{$relation}", $joinAlias);
        }

        return "{$joinAlias}.{$field}";
    }
}
