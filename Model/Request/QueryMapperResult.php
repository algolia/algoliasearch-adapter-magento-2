<?php

namespace Algolia\SearchAdapter\Model\Request;

use Algolia\AlgoliaSearch\Api\Data\SearchQueryInterface;
use Algolia\SearchAdapter\Api\Data\PaginationInfoInterface;
use Algolia\SearchAdapter\Api\Data\QueryMapperResultInterface;

class QueryMapperResult implements QueryMapperResultInterface
{
    public function __construct(
        protected ?SearchQueryInterface    $searchQuery = null,
        protected ?PaginationInfoInterface $paginationInfo = null,
    ) {}

    public function getSearchQuery(): ?SearchQueryInterface
    {
        return $this->searchQuery;
    }

    public function getPaginationInfo(): ?PaginationInfoInterface
    {
        return $this->paginationInfo;
    }

    public function setSearchQuery(SearchQueryInterface $searchQuery): self
    {
        $this->searchQuery = $searchQuery;
        return $this;
    }

    public function setPaginationInfo(PaginationInfoInterface $paginationInfo): self
    {
        $this->paginationInfo = $paginationInfo;
        return $this;
    }
}
