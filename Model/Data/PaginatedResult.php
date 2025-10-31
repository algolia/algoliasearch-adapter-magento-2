<?php

namespace Algolia\SearchAdapter\Model\Data;

use Algolia\SearchAdapter\Api\Data\PaginatedResultInterface;

class PaginatedResult implements PaginatedResultInterface
{

    public function __construct(
        protected array $documents = [],
        protected int $totalCount = 0,
        protected int $totalPages = 0,
        protected int $pageSize = 0,
        protected int $currentPage = 1,
    ) {}

    public function getDocuments(): array
    {
        return $this->documents;
    }

    public function setDocuments(array $documents): PaginatedResultInterface
    {
        $this->documents = $documents;
        return $this;
    }

    public function getTotalCount(): int
    {
        return $this->totalCount;
    }

    public function setTotalCount(int $totalCount): PaginatedResultInterface
    {
        $this->totalCount = $totalCount;
        return $this;
    }

    public function getTotalPages(): int
    {
        return $this->totalPages;
    }

    public function setTotalPages(int $totalPages): PaginatedResultInterface
    {
        $this->totalPages = $totalPages;
        return $this;
    }

    public function getPageSize(): int
    {
        return $this->pageSize;
    }

    public function setPageSize(int $pageSize): PaginatedResultInterface
    {
        $this->pageSize = $pageSize;
        return $this;
    }

    public function getCurrentPage(): int
    {
        return $this->currentPage;
    }

    public function setCurrentPage(int $currentPage): PaginatedResultInterface
    {
        $this->currentPage = $currentPage;
        return $this;
    }

    public function toArray(): array
    {
        return [
            'documents' => $this->getDocuments(),
            'totalCount' => $this->getTotalCount(),
            'totalPages' => $this->getTotalPages(),
            'pageSize' => $this->getPageSize(),
        ];
    }
}
