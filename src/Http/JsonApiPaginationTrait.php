<?php

declare(strict_types = 1);

namespace Modufolio\Panel\Http;

use Modufolio\JsonApi\JsonApiSerializer;

trait JsonApiPaginationTrait
{
    /**
     * @param  array<string, mixed> $queryParams
     * @return array{limit: int, offset: int, page: int, perPage: int}
     */
    protected function getJsonApiPagination(array $queryParams): array
    {
        $pagination = JsonApiSerializer::parsePaginationParams($queryParams);

        $limit = $pagination['size'];
        $offset = ($pagination['number'] - 1) * $pagination['size'];

        return [
            'limit' => $limit,
            'offset' => $offset,
            'page' => $pagination['number'],
            'perPage' => $pagination['size'],
        ];
    }

    /**
     * @param  array<int, array<string, mixed>> $data
     * @param  array<string, mixed>             $meta
     * @return array<string, mixed>
     */
    protected function wrapWithJsonApiPagination(
        array $data,
        int $totalCount,
        int $currentPage,
        int $perPage,
        ?string $type = null,
        array $meta = [],
        ?string $baseUrl = null
    ): array {
        return JsonApiSerializer::serializeCollection(
            $data,
            $totalCount,
            $currentPage,
            $perPage,
            $type,
            $meta,
            [],
            $baseUrl
        );
    }
}
