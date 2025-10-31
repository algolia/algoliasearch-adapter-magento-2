<?php

namespace Algolia\SearchAdapter\Model\Response;

use Algolia\AlgoliaSearch\Helper\Configuration\InstantSearchHelper;

class DocumentMapper
{
    /** @var int */
    public const DEFAULT_PRODUCTS_PER_PAGE = 9;

    public function __construct(
        protected InstantSearchHelper $instantSearchHelper,
    ) {}

    public function buildDocuments(array $searchResponse, ?int $storeId = null): array
    {
        $productsPerPage = $this->instantSearchHelper->getNumberOfProductResults($storeId);
        $i = 0;
        return array_map(
            function(array $hit) use (&$i) {
                return [
                    'fields' => [
                        '_id' => [ $hit['objectID'] ],
                    ],
                    'score' => null,
                    'sort' => [ ++$i, $hit['objectID'] ]
                ];
            },
            $this->getPagedHits($searchResponse, [ 'pageNum' => 1, 'productsPerPage' => $productsPerPage ])
        );
    }

    protected function getHits(array $searchResponse): array
    {
        $hits = [];
        foreach ($searchResponse['results'] ?? [] as $result) {
            foreach ($result['hits'] ?? [] as $hit) {
                $hits[] = $hit;
            }
        }
        return $hits;
    }

    protected function getPagedHits(
        array $searchResponse,
        array $options = [ 'pageNum' => 1, 'productsPerPage' => self::DEFAULT_PRODUCTS_PER_PAGE ]
    ): array
    {
        $hits = $this->getHits($searchResponse);
        $length = $options['productsPerPage'];
        $offset = ($options['pageNum'] - 1) * $length;
        return array_slice($hits, $offset, $length);
    }

    public function getHitCount(array $searchResponse): int
    {
        return count($this->getHits($searchResponse));
    }
}
