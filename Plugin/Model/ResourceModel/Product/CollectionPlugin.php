<?php

namespace Algolia\SearchAdapter\Plugin\Model\ResourceModel\Product;

use Algolia\SearchAdapter\Helper\ConfigHelper;
use Magento\Catalog\Model\Category;
use Magento\Catalog\Model\ResourceModel\Product\Collection as ProductCollection;

class CollectionPlugin
{
    public function __construct(
        protected ConfigHelper $configHelper
    ) {}

    public function aroundAddCategoryFilter(ProductCollection $subject, callable $proceed, Category $category) {
        if ($this->configHelper->isAlgoliaEngineSelected()) {
            // Let Algolia filter the collection via AdapterInterface
            $subject->addFieldToFilter('category_ids', $category->getId());
            return $subject;
        }

        return $proceed($category);
    }
}
