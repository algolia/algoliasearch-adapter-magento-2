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
        $vizFilter = $this->getFilterParam($filters, 'visibility');
        if ($vizFilter) {
            if (!is_array($vizFilter)) {
                $vizFilter = [$vizFilter];
            }

            /*
             *  If viz is for both treat as an OR condition
             *  OR is handled as a nested array per https://www.algolia.com/doc/api-reference/api-parameters/numericFilters#usage
             */
            if (in_array(Visibility::VISIBILITY_BOTH, $vizFilter)) {
                $this->applyVisibilityFilter(
                    $params,
                    [
                        ProductRecordFieldsInterface::VISIBILITY_CATALOG,
                        ProductRecordFieldsInterface::VISIBILITY_SEARCH
                    ]
                );
                return;
            }

            if (in_array(Visibility::VISIBILITY_IN_SEARCH, $vizFilter)) {
                $this->applyVisibilityFilter($params, ProductRecordFieldsInterface::VISIBILITY_SEARCH);
            }
            if (in_array(Visibility::VISIBILITY_IN_CATALOG, $vizFilter)) {
                $this->applyVisibilityFilter($params, ProductRecordFieldsInterface::VISIBILITY_CATALOG);
            }
        }
    }

    protected function shouldApplyVisibilityFilter(array $filterParams, int $visibility): bool
    {
        $possibleValues = [
            Visibility::VISIBILITY_BOTH,
            $visibility
        ];
        return !empty(array_intersect($possibleValues, $filterParams));
    }

    /**
     * Apply the visibility field as a numeric filter to the Algolia search query parameters
     * @param array<string, mixed> $params
     * @param string|string[] $visibilityFilter
     */
    protected function applyVisibilityFilter(array &$params, string|array $visibilityFilter): void
    {
        $format = '%s=1';
        if (is_array($visibilityFilter)) {
            $this->applyFilter($params, 'numericFilters', array_map(fn($visibility) => sprintf($format, $visibility), $visibilityFilter));
        } else {
            $this->applyFilter($params, 'numericFilters', sprintf($format, $visibilityFilter));
        }
    }
}
