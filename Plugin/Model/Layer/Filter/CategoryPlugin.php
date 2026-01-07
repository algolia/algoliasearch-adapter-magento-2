<?php

namespace Algolia\SearchAdapter\Plugin\Model\Layer\Filter;

use Algolia\AlgoliaSearch\Helper\Configuration\InstantSearchHelper;
use Algolia\SearchAdapter\Helper\ConfigHelper;
use Algolia\SearchAdapter\Service\Category\CategoryPathResolver;
use Magento\CatalogSearch\Model\Layer\Filter\Category;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Store\Model\StoreManagerInterface;

class CategoryPlugin
{
    public function __construct(
        protected ConfigHelper          $configHelper,
        protected StoreManagerInterface $storeManager,
        protected CategoryPathResolver  $categoryPathResolver,
    ) {}

    /**
     * @throws LocalizedException
     */
    public function beforeApply(Category $subject, RequestInterface $request): array
    {
        $attributeCode = $subject->getRequestVar();
        $attributeValue = $request->getParam($attributeCode);
        $storeId = $this->storeManager->getStore()->getId();

        if (
            !$this->configHelper->areSeoFiltersEnabled($storeId)
            ||
            $attributeValue === null
            ||
            trim($attributeValue) === ''
        ) {
            return [$request];
        }

        if (!is_int($attributeValue)) {
            $categoryId = $this->categoryPathResolver->getEntityIdForPath(
                $attributeValue,
                InstantSearchHelper::CATEGORY_ROUTE_DELIMITER,
                $storeId
            );
            $request->setParam(
                $attributeCode,
                $categoryId
            );
        }

        return [$request];
    }
}
