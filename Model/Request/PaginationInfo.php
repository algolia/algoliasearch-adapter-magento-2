<?php

namespace Algolia\SearchAdapter\Model\Request;

use Algolia\SearchAdapter\Api\Data\PaginationInfoInterface;

class PaginationInfo implements PaginationInfoInterface
{
    /** @var int  */
    public const DEFAULT_PAGE_SIZE = 9;

    public function __construct(
        protected int $pageNumber = 1,
        protected int $pageSize = self::DEFAULT_PAGE_SIZE,
        protected int $offset = 0,
    ) {}

    public function getPageNumber(): int
    {
        return $this->pageNumber;
    }

    public function getPageSize(): int
    {
        return $this->pageSize;
    }

    public function getOffset(): int
    {
        return $this->offset;
    }

    public function setPageNumber(int $pageNumber): PaginationInfoInterface
    {
        $this->pageNumber = $pageNumber;
        $this->recalculateOffset();
        return $this;
    }

    public function setPageSize(int $pageSize): PaginationInfoInterface
    {
        $this->pageSize = $pageSize;
        $this->recalculateOffset();
        return $this;
    }

    /** Changes to the page size and number impact the offset (this allows for a "smart" offset) */
    protected function recalculateOffset(): int
    {
        $offset = ($this->getPageNumber() - 1) * $this->getPageSize();
        $this->offset = $offset;
        return $offset;
    }
    
    public function setOffset(int $offset): PaginationInfoInterface
    {
        $this->offset = $offset;
        $this->recalculatePageNumber();
        return $this;
    }

    protected function recalculatePageNumber(): int
    {
        $pageNumber = floor($this->getOffset() / $this->getPageSize() + 1);
        $this->pageNumber = $pageNumber;
        return $pageNumber;
    }

    public function toArray(): array
    {
        return [
            'pageNumber' => $this->getPageNumber(),
            'pageSize' => $this->getPageSize(),
            'offset' => $this->getOffset(),
        ];
    }
}
