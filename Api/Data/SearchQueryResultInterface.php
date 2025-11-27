<?php

namespace Algolia\SearchAdapter\Api\Data;

/**
 * Represents the result of an Algolia search query
 */
interface SearchQueryResultInterface
{
    /**
     * Get the hits (results) from the search
     *
     * @return array
     */
    public function getHits(): array;

    /**
     * Get the facets from the search
     *
     * @return array
     */
    public function getFacets(): array;

    /**
     * Get the total number of hits
     *
     * @return int
     */
    public function getTotalHits(): int;

    /**
     * Get the total number of pages
     *
     * @return int|null
     */
    public function getTotalPages(): ?int;

    /**
     * Get the number of hits per page
     *
     * @return int|null
     */
    public function getHitsPerPage(): ?int;

    /**
     * Get the current page number (0-based, Algolia convention)
     *
     * @return int
     */
    public function getPage(): int;

    /**
     * Set the hits
     *
     * @param array $hits
     * @return self
     */
    public function setHits(array $hits): self;

    /**
     * Set the facets
     *
     * @param array $facets
     * @return self
     */
    public function setFacets(array $facets): self;

    /**
     * Set the total number of hits
     *
     * @param int $totalHits
     * @return self
     */
    public function setTotalHits(int $totalHits): self;

    /**
     * Set the total number of pages
     *
     * @param int|null $totalPages
     * @return self
     */
    public function setTotalPages(?int $totalPages): self;

    /**
     * Set the number of hits per page
     *
     * @param int|null $hitsPerPage
     * @return self
     */
    public function setHitsPerPage(?int $hitsPerPage): self;

    /**
     * Set the current page number
     *
     * @param int $page
     * @return self
     */
    public function setPage(int $page): self;
}

