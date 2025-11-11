<?php

namespace Algolia\SearchAdapter\Registry;

use Magento\Framework\Api\SortOrder;
use Magento\Framework\Api\SortOrderBuilder;

/**
 * There is no sort handling contract in \Magento\Framework\Search\RequestInterface
 * and \Magento\Framework\Search\Request::getSort is marked as deprecated
 *
 * Although getSort is still being used to maintain compatibility with the Elasticsearch / OpenSearch
 * implementation of \Magento\Framework\Search\AdapterInterface, this externally managed state is
 * provided purely as a fallback in an attempt to future proof the backend render functionality.
 */
class SortState
{
    private ?SortOrder $sortOrder;

    public function __construct(
        protected SortOrderBuilder $sortOrderBuilder,
    ) {}

    public function set(SortOrder $sortOrder): void
    {
        $this->sortOrder = $sortOrder;
    }

    public function get(): SortOrder
    {
        return $this->sortOrder ?? $this->sortOrderBuilder->create();
    }
}
