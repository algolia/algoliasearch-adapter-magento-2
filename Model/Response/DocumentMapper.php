<?php

namespace Algolia\SearchAdapter\Model\Response;

use Algolia\SearchAdapter\Api\Data\DocumentMapperResultInterface;
use Algolia\SearchAdapter\Api\Data\DocumentMapperResultInterfaceFactory;
use Algolia\SearchAdapter\Api\Data\PaginationInfoInterface;
use Algolia\SearchAdapter\Api\Data\SearchQueryResultInterface;

class DocumentMapper
{
    public function __construct(
        protected DocumentMapperResultInterfaceFactory $documentMapperResultFactory,
    ) {}

    public function process(SearchQueryResultInterface $result, PaginationInfoInterface $pagination): DocumentMapperResultInterface
    {
        $total = $result->getTotalHits();
        $totalPages = $result->getTotalPages() ?? (int) ceil($total / $pagination->getPageSize());
        $pageSize = $result->getHitsPerPage() ?? $pagination->getPageSize();
        return $this->documentMapperResultFactory->create([
            'documents' => $this->buildDocuments($result->getHits()),
            'totalCount' => $total,
            'totalPages' => $totalPages,
            'pageSize' => $pageSize,
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

    /** Intended for broader search scope against Algolia without paging - will likely be removed */
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
}
