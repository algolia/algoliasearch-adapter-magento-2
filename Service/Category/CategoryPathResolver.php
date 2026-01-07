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
     * Based on the full category path find the *first* matching entity ID in the category tree
     *
     * WARNING: This assumes that category paths are unique.
     * If identical trees are found in the database then an incorrect entity ID may be returned.
     *
     * @return string Category ID if found or empty string if not
     * @throws LocalizedException
     */
    public function getEntityIdForPath(string $path, string $delimiter, ?int $storeId = null): string
    {
        $pathParts = array_reverse(explode($delimiter, $path)); // Process the path parts in reverse order to compare parentage progressively
        $categoryName = array_shift($pathParts);

        // Get the candidate categories
        $collection = $this->categoryCollectionFactory->create()
            ->addAttributeToFilter('name', ['eq' => $categoryName])
            ->addFieldToFilter('level', ['gt' => 1]);

        if ($storeId) {
            $collection->setStoreId($storeId);
        }

        // Evaluate the parentage until a match is found
        /** @var Category $category */
        foreach ($collection as $category) {
            $parentIds = array_reverse($category->getPathIds()); // Mirror the path parts in reverse order to compare parentage
            array_shift($parentIds); // Remove the current category ID from the path to compare parentage only
            if ($this->hasMatchingParents($pathParts, $parentIds, $storeId)) {
                return $category->getId();
            }

        }

        return '';
    }

    /**
     * Evaluates the original category path string against an array of parent ids.
     * If the category names for those parent ids match then the parentage is assumed to be the same.
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
