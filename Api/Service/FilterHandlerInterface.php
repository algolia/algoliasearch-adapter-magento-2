<?php

namespace Algolia\SearchAdapter\Api\Service;

use Magento\Framework\Search\Request\QueryInterface as RequestQueryInterface;

interface FilterHandlerInterface
{
    /**
     * Apply filters from the Magento search request object to the Algolia search query parameters.
     *
     * @param array<string, mixed> $params The params array to modify
     * @param RequestQueryInterface[] $filters The Magento search request filters
     * @param int|null $storeId
     */
    public function process(array &$params, array &$filters, ?int $storeId = null): void;
}
