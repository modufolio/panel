<?php

declare(strict_types = 1);

namespace Modufolio\Panel\Http;

use Modufolio\JsonApi\JsonApiSerializer;

trait JsonApiPaginationTrait
{
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
