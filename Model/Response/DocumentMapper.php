<?php

namespace Algolia\SearchAdapter\Model\Response;

use Algolia\AlgoliaSearch\Helper\Configuration\InstantSearchHelper;
use Algolia\SearchAdapter\Api\Data\PaginatedResultInterface;
use Algolia\SearchAdapter\Api\Data\PaginatedResultInterfaceFactory;
use Algolia\SearchAdapter\Api\Data\PaginationInfoInterface;
use Algolia\SearchAdapter\Api\Data\QueryMapperResultInterface;
use Algolia\SearchAdapter\Model\Request\QueryMapperResult;

class DocumentMapper
{
    /** @var bool  */
    private const SYNC_WITH_ALGOLIA = false;

    public function __construct(
        protected InstantSearchHelper $instantSearchHelper,
        protected PaginatedResultInterfaceFactory $paginatedResultFactory,
    ) {}

    public function process(array $searchResponse, QueryMapperResultInterface $query): PaginatedResultInterface
    {
        $hits = $this->getHits($searchResponse);
        $pagination = $this->getPagination($query);
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

    protected function getPagination(QueryMapperResult $query): PaginationInfoInterface
    {
        // TODO: Should we sync with Algolia or make configurable? This choice can affect functionality of \Magento\Catalog\Helper\Product\ProductList::getAvailableLimit
        $paginationInfo = $query->getPaginationInfo();
        if (self::SYNC_WITH_ALGOLIA) {
            $paginationInfo->setPageSize($this->instantSearchHelper->getNumberOfProductResults($query->getSearchQuery()->getIndexOptions()->getStoreId()));
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
