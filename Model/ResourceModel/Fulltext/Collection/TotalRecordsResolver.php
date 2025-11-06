<?php

namespace Algolia\SearchAdapter\Model\ResourceModel\Fulltext\Collection;

use Magento\CatalogSearch\Model\ResourceModel\Fulltext\Collection\TotalRecordsResolverInterface;
use Magento\Framework\Api\Search\SearchResultInterface;

class TotalRecordsResolver implements TotalRecordsResolverInterface
{
    public function __construct(
        protected SearchResultInterface $searchResult
    ) { }

    /**
     * @inheritdoc
     */
    public function resolve(): ?int
    {
        return $this->searchResult->getTotalCount();
    }
}
