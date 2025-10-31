<?php

namespace Algolia\SearchAdapter\Api\Data;

interface PaginatedResultInterface
{
    public function getDocuments(): array;
    public function getTotalCount(): int;
    public function getTotalPages(): int;
    public function getPageSize(): int;
    public function getCurrentPage(): int; // 1-based index

    public function setDocuments(array $documents): PaginatedResultInterface;
    public function setTotalCount(int $totalCount): PaginatedResultInterface;
    public function setTotalPages(int $totalPages): PaginatedResultInterface;
    public function setPageSize(int $pageSize): PaginatedResultInterface;
    public function setCurrentPage(int $currentPage): PaginatedResultInterface;
}
