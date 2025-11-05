<?php

namespace Algolia\SearchAdapter\Api\Data;

interface DocumentMapperResultInterface
{
    public const RESPONSE_KEY_DOCUMENTS = 'documents';
    public const RESPONSE_KEY_AGGREGATIONS = 'aggregations';
    public const RESPONSE_KEY_TOTAL = 'total';

    public function getDocuments(): array;
    public function getTotalCount(): int;
    public function getTotalPages(): int;
    public function getPageSize(): int;
    public function getCurrentPage(): int; // 1-based index

    public function setDocuments(array $documents): self;
    public function setTotalCount(int $totalCount): self;
    public function setTotalPages(int $totalPages): self;
    public function setPageSize(int $pageSize): self;
    public function setCurrentPage(int $currentPage): self;
}
