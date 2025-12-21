<?php

namespace Algolia\SearchAdapter\Service\Filter;

use Algolia\AlgoliaSearch\Api\Product\RuleContextInterface;
use Algolia\AlgoliaSearch\Helper\ConfigHelper;
use Algolia\AlgoliaSearch\Service\Category\CategoryPathProvider;
use Magento\Catalog\Api\CategoryRepositoryInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Search\Request\QueryInterface as RequestQueryInterface;

class CategoryFilterHandler extends AbstractFilterHandler
{
    public function __construct(
        protected ConfigHelper                $configHelper,
        protected CategoryPathProvider        $categoryPathProvider,
        protected CategoryRepositoryInterface $categoryRepository
    ) {}

    /**
     * Apply the category filter as a facet filter to the Algolia search query parameters
     *
     * @param array<string, mixed> $params
     * @param RequestQueryInterface[] $filters
     * @throws NoSuchEntityException
     */
    public function process(array &$params, array &$filters, ?int $storeId = null): void
    {
        $categoryId = $this->getFilterParam($filters, 'category');
        if ($categoryId) {
            // Basic category filter
            $this->applyFilter($params, 'facetFilters', sprintf('categoryIds:%u', $categoryId));

            // Legacy merch rule context
            $this->applyFilter($params, 'ruleContexts', RuleContextInterface::MERCH_RULE_CATEGORY_PREFIX . $categoryId);

            // Merch Studio support
            if ($this->configHelper->isVisualMerchEnabled($storeId)) {
                $category = $this->categoryRepository->get($categoryId);
                if ($category instanceof \Magento\Catalog\Model\Category) { // Sanity check: interface along does not contain all required methods
                    // The 'filters' parameter expects a string: https://www.algolia.com/doc/api-reference/api-parameters/filters
                    $params['filters'] = sprintf('%s:"%s"',
                        $this->configHelper->getCategoryPageIdAttributeName($storeId),
                        $this->categoryPathProvider->getCategoryPageId($category, $storeId)
                    );
                }
            }
        }
    }
}
