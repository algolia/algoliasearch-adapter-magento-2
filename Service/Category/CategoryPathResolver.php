<?php

namespace Algolia\SearchAdapter\Service\Category;

use Magento\Catalog\Model\Category;
use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory as CategoryCollectionFactory;
use Magento\Framework\Exception\LocalizedException;

class CategoryPathResolver
{
    public function __construct(
        protected CategoryCollectionFactory $categoryCollectionFactory,
        protected \Algolia\AlgoliaSearch\Service\Category\CategoryPathProvider $categoryPathProvider,
    ) {}

    /**
     * @return string Category ID if found or empty string if not
     * @throws LocalizedException
     */
    public function getEntityIdForPath(string $path, string $delimiter, ?int $storeId = null): string
    {
        $pathParts = array_reverse(explode($delimiter, $path));
        $categoryName = array_shift($pathParts);

        $collection = $this->categoryCollectionFactory->create()
            ->addAttributeToFilter('name', ['eq' => $categoryName])
            ->addFieldToFilter('level', ['gt' => 1]);

        if ($storeId) {
            $collection->setStoreId($storeId);
        }

        /** @var Category $category */
        foreach ($collection as $category) {
            $parentIds = array_reverse($category->getPathIds());
            array_shift($parentIds);
            if ($this->hasMatchingParents($pathParts, $parentIds, $storeId)) {
                return $category->getId();
            }

        }

        return '';
    }

    /**
     * @throws LocalizedException
     */
    protected function hasMatchingParents(array $pathParts, array $parentIds, ?int $storeId = null): bool
    {
        $categoryNameMap = $this->categoryPathProvider->getCategoryNameMap($parentIds, $storeId);

        for ($i = 0; $i < count($pathParts); $i++) {
            $parentCategoryName = $categoryNameMap[$parentIds[$i]] ?? null;
            if ($parentCategoryName !== $pathParts[$i]) {
                return false;
            }
        }

        return true;
    }
}
