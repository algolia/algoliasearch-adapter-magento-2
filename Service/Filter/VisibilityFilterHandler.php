<?php

namespace Algolia\SearchAdapter\Service\Filter;

use Algolia\AlgoliaSearch\Api\Product\ProductRecordFieldsInterface;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Framework\Search\Request\QueryInterface as RequestQueryInterface;

class VisibilityFilterHandler extends AbstractFilterHandler
{
    /**
     * Apply visibility filters to the Algolia search query parameters
     * @param array<string, mixed> $params
     * @param RequestQueryInterface[] $filters
     */
    public function process(array &$params, array &$filters, ?int $storeId = null): void
    {
        $visibility = $this->getFilterParam($filters, 'visibility');
        if ($visibility) {
            if (!is_array($visibility)) {
                $visibility = [$visibility];
            }
            if (in_array(Visibility::VISIBILITY_IN_SEARCH, $visibility)) {
                $this->applyVisibilityFilter($params, ProductRecordFieldsInterface::VISIBILITY_SEARCH);
            }
            if (in_array(Visibility::VISIBILITY_IN_CATALOG, $visibility)) {
                $this->applyVisibilityFilter($params, ProductRecordFieldsInterface::VISIBILITY_CATALOG);
            }
        }
    }

    /**
     * Apply the visibility field as a numeric filter to the Algolia search query parameters
     */
    protected function applyVisibilityFilter(array &$params, string $visibilityFilterField): void
    {
        $this->applyFilter($params, 'numericFilters', sprintf('%s=1', $visibilityFilterField));
    }
}
