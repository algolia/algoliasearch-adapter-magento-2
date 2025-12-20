<?php

namespace Algolia\SearchAdapter\Service\Filter;

use Algolia\AlgoliaSearch\Api\Product\RuleContextInterface;
use Magento\Framework\Search\Request\QueryInterface as RequestQueryInterface;

class CategoryFilterHandler extends AbstractFilterHandler
{
    /**
     * Apply the category filter as a facet filter to the Algolia search query parameters
     *
     * @param array<string, mixed> $params
     * @param RequestQueryInterface[] $filters
     */
    public function process(array &$params, array &$filters, ?int $storeId = null): void
    {
        $category = $this->getFilterParam($filters, 'category');
        if ($category) {
            $this->applyFilter($params, 'facetFilters', sprintf('categoryIds:%u', $category));
            $this->applyFilter($params, 'ruleContexts', RuleContextInterface::MERCH_RULE_CATEGORY_PREFIX . $category);
        }
    }
}
