<?php

namespace Algolia\SearchAdapter\Api\Data;

use Algolia\AlgoliaSearch\Api\Data\SearchQueryInterface;

interface QueryMapperResultInterface
{
    public function getSearchQuery(): ?SearchQueryInterface;

    public function setSearchQuery(SearchQueryInterface $searchQuery): self;

    /**
     * Get the pagination info
     */
    public function getPaginationInfo(): ?PaginationInfoInterface;

    /**
     * Set the pagination info
     */
    public function setPaginationInfo(PaginationInfoInterface $paginationInfo): self;
}
