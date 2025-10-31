<?php

namespace Algolia\SearchAdapter\Model\Response;

use Algolia\AlgoliaSearch\Api\Data\PaginationInfoInterface;
use Algolia\AlgoliaSearch\Api\Data\SearchQueryInterface;
use Algolia\AlgoliaSearch\Helper\Configuration\InstantSearchHelper;
use Algolia\AlgoliaSearch\Model\Data\PaginationInfo;
use Algolia\SearchAdapter\Api\Data\PaginatedResultInterface;
use Algolia\SearchAdapter\Api\Data\PaginatedResultInterfaceFactory;

class DocumentMapper
{
    /** @var bool  */
    private const SYNC_WITH_ALGOLIA = false;

    public function __construct(
        protected InstantSearchHelper $instantSearchHelper,
        protected PaginatedResultInterfaceFactory $paginatedResultFactory,
    ) {}

    public function process(array $searchResponse, SearchQueryInterface $searchQuery): PaginatedResultInterface
    {
        $hits = $this->getHits($searchResponse);
        $pagination = $this->getPagination($searchQuery);
        $total = count($hits);
        $totalPages = ceil($total / $pagination->getPageSize());
        return $this->paginatedResultFactory->create([
            'documents' => $this->buildDocuments(
                $this->getPagedHits(
                    $hits,
                    $pagination
                )
            ),
            'totalCount' => $total,
            'totalPages' => $totalPages,
            'pageSize' => $pagination->getPageSize(),
            'currentPage' => $pagination->getPageNumber()
        ]);
    }

    protected function buildDocuments(array $hits): array
    {
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
            $hits
        );
    }

    protected function getPagination(SearchQueryInterface $searchQuery): PaginationInfoInterface
    {
        // TODO: Should we sync with Algolia or make configurable? This choice can affect functionality of \Magento\Catalog\Helper\Product\ProductList::getAvailableLimit
        $paginationInfo = $searchQuery->getPaginationInfo();
        if (self::SYNC_WITH_ALGOLIA) {
            $paginationInfo->setPageSize($this->instantSearchHelper->getNumberOfProductResults($searchQuery->getIndexOptions()->getStoreId()));
        }
        return $paginationInfo;
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
        array $hits,
        PaginationInfoInterface $pagination
    ): array
    {
        return array_slice(
            $hits,
            $pagination->getOffset(),
            $pagination->getPageSize()
        );
    }

    public function getHitCount(array $searchResponse): int
    {
        return count($this->getHits($searchResponse));
    }
}
