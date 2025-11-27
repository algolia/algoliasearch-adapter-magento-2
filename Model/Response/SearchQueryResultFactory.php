<?php

namespace Algolia\SearchAdapter\Model\Response;

use Algolia\SearchAdapter\Api\Data\SearchQueryResultInterface;
use Algolia\SearchAdapter\Api\Data\SearchQueryResultInterfaceFactory;

/**
 * Factory for creating SearchQueryResult instances from raw Algolia API responses
 */
class SearchQueryResultFactory
{
    public function __construct(
        protected SearchQueryResultInterfaceFactory $searchQueryResultFactory
    ) {}

    /**
     * Create a SearchQueryResult from raw Algolia API response array
     *
     * @param array $response The raw response from Algolia API
     * @return SearchQueryResultInterface
     */
    public function create(array $response): SearchQueryResultInterface
    {
        // Algolia returns results in a 'results' array, we take the first element
        // since multi query is not supported for backend search
        $result = $response['results'][0] ?? [];

        return $this->searchQueryResultFactory->create([
            'hits'        => $result['hits'] ?? [],
            'facets'      => $result['facets'] ?? [],
            'totalHits'   => $result['nbHits'] ?? 0,
            'totalPages'  => $result['nbPages'] ?? null,
            'hitsPerPage' => $result['hitsPerPage'] ?? null,
            'page'        => $result['page'] ?? 0,
        ]);
    }
}

