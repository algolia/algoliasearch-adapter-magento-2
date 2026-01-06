<?php

namespace Algolia\SearchAdapter\Plugin\Model\Layer\Filter;

use Algolia\SearchAdapter\Helper\ConfigHelper;
use Magento\CatalogSearch\Model\Layer\Filter\Category;
use Magento\Framework\App\RequestInterface;
use Magento\Store\Model\StoreManagerInterface;

class CategoryPlugin
{
    public function __construct(
        protected ConfigHelper          $configHelper,
        protected StoreManagerInterface $storeManager,
    ) {}

    public function beforeApply(Category $subject, RequestInterface $request): array
    {
        $attributeCode = $subject->getRequestVar();
        $attributeValue = $request->getParam($attributeCode);

        if (
            !$this->configHelper->areSeoFiltersEnabled($this->storeManager->getStore()->getId())
            ||
            $attributeValue === null
            ||
            trim($attributeValue) === ''
        ) {
            return [$request];
        }

        if (!is_int($attributeValue)) { 
            $request->setParam($attributeCode, ''); // TODO: Resolve friendly category slug to category ID
        }

        return [$request];
    }
}
