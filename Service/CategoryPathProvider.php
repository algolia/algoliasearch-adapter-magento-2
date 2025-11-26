<?php

namespace Algolia\SearchAdapter\Service;

use Algolia\AlgoliaSearch\Helper\ConfigHelper;
use Magento\Catalog\Model\ResourceModel\Category\Collection as CategoryCollection;
use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory as CategoryCollectionFactory;

class CategoryPathProvider
{
    public function __construct(
        protected CategoryCollectionFactory $categoryCollectionFactory,
        protected ConfigHelper              $configHelper,
    ) {}

    /**
     * Get the category paths for the given category IDs.
     *
     * @param int[] $categoryIds
     * @param int|null $storeId
     * @return string[]
     */
    public function getCategoryPaths(array $categoryIds, ?int $storeId = null): array
    {
        $collection = $this->categoryCollectionFactory->create();
        $collection->addFieldToFilter('entity_id', ['in' => $categoryIds]);
        $categoryPaths = [];
        $parentIds = $this->extractParentCategoryIds($collection);
        $categoryMap = $this->getCategoryMap($parentIds);
        foreach ($collection->getItems() as $category) {
            $categoryPaths[$category->getId()] = $this->buildFullCategoryPath($category->getPath(), $categoryMap, $storeId);
        }
        return $categoryPaths;
    }

    protected function buildFullCategoryPath(string $path, array $categoryMap, ?int $storeId = null): string
    {
        $fullPath = '';
        $categoryNames = array_map(fn($id) => $categoryMap[$id] ?? null, explode('/', $path));
        return implode(
            $this->configHelper->getCategorySeparator($storeId),
            array_filter($categoryNames, fn($name) => !empty($name))
        );
    }

    /**
     * @param int[] $categoryIds
     * @return array<int, string>
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    protected function getCategoryMap(array $categoryIds): array
    {
        $collection = $this->categoryCollectionFactory->create();
        $collection->addAttributeToSelect('name');
        $collection->addFieldToFilter('entity_id', ['in' => $categoryIds]);
        $collection->addFieldToFilter('level', ['gt' => 1]);
        return array_map(fn($item) => $item->getName(), $collection->getItems());
    }

    /**
     * @param CategoryCollection $collection
     * @return int[]
     */
    protected function extractParentCategoryIds(CategoryCollection $collection): array
    {
        $parentIds = array_map(fn($category) => explode('/', $category->getPath()), $collection->getItems());
        // flatten and de-dupe
        return array_unique(array_merge(...$parentIds));
    }

}
