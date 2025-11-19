<?php

namespace Algolia\SearchAdapter\Model\Response;

use Algolia\SearchAdapter\Api\Data\SearchQueryResultInterface;

/**
 * DTO representing an Algolia search query result
 */
class SearchQueryResult implements SearchQueryResultInterface
{
    public function __construct(
        protected array $hits = [],
        protected array $facets = [],
        protected int $totalHits = 0,
        protected int $totalPages = 0,
        protected int $hitsPerPage = 0,
        protected int $page = 0,
    ) {}

    public function getHits(): array
    {
        return $this->hits;
    }

    public function setHits(array $hits): self
    {
        $this->hits = $hits;
        return $this;
    }

    public function getFacets(): array
    {
        return $this->facets;
    }

    public function setFacets(array $facets): self
    {
        $this->facets = $facets;
        return $this;
    }

    public function getTotalHits(): int
    {
        return $this->totalHits;
    }

    public function setTotalHits(int $totalHits): self
    {
        $this->totalHits = $totalHits;
        return $this;
    }

    public function getTotalPages(): int
    {
        return $this->totalPages;
    }

    public function setTotalPages(int $totalPages): self
    {
        $this->totalPages = $totalPages;
        return $this;
    }

    public function getHitsPerPage(): int
    {
        return $this->hitsPerPage;
    }

    public function setHitsPerPage(int $hitsPerPage): self
    {
        $this->hitsPerPage = $hitsPerPage;
        return $this;
    }

    public function getPage(): int
    {
        return $this->page;
    }

    public function setPage(int $page): self
    {
        $this->page = $page;
        return $this;
    }
}

