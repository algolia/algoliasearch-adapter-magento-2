<?php

namespace Algolia\SearchAdapter\Service;

use Algolia\AlgoliaSearch\Helper\ConfigHelper;
use Magento\Catalog\Model\ResourceModel\Category\Collection as CategoryCollection;
use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory as CategoryCollectionFactory;
use Magento\Framework\Exception\LocalizedException;

class CategoryPathProvider
{
    public function __construct(
        protected CategoryCollectionFactory $categoryCollectionFactory,
        protected ConfigHelper              $configHelper,
    ) {}

    /**
     * Get the full category paths for the given category IDs.
     *
     * @param string[] $categoryIds An array of category entity IDs
     * @param int|null $storeId
     * @return array<string, string> A map of entity ID to full delimited category path
     * @throws LocalizedException
     */
    public function getCategoryPaths(array $categoryIds, ?int $storeId = null): array
    {
        $collection = $this->categoryCollectionFactory
            ->create()
            ->addFieldToFilter('entity_id', ['in' => $categoryIds])
            ->setStore($storeId);

        $categoryPaths = [];
        $parentIds = $this->extractParentCategoryIds($collection);
        $categoryMap = $this->getCategoryNameMap($parentIds);
        foreach ($collection->getItems() as $category) {
            $categoryPaths[$category->getId()] = $this->buildFullCategoryPath($category->getPath(), $categoryMap, $storeId);
        }
        return $categoryPaths;
    }

    /**
     * Returns a full category path delimited by the configured separator
     *
     * @param string $path e.g. "1/2/20/22/28"
     * @param array<string, string> $categoryMap A map of entity ID to category name
     * @param int|null $storeId
     * @return string
     */
    protected function buildFullCategoryPath(string $path, array $categoryMap, ?int $storeId = null): string
    {
        $categoryNames = array_map(fn($id) => $categoryMap[$id] ?? null, explode('/', $path));
        return implode(
            $this->configHelper->getCategorySeparator($storeId),
            array_filter($categoryNames, fn($name) => !empty($name))
        );
    }

    /**
     * Returns a map of category IDs to category names
     *
     * @param string[] $categoryIds
     * @return array<string, string> A map of entity ID to category name
     * @throws LocalizedException
     */
    protected function getCategoryNameMap(array $categoryIds): array
    {
        $collection = $this->categoryCollectionFactory->create();
        $collection->addAttributeToSelect('name');
        $collection->addFieldToFilter('entity_id', ['in' => $categoryIds]);
        $collection->addFieldToFilter('level', ['gt' => 1]);
        return array_map(fn($item) => $item->getName(), $collection->getItems());
    }

    /**
     * This method provides a complete list of entity ids extracted from each category's parent hierarchy.
     * The intent is to collect the IDs in order perform a *single query* against the database for category name retrieval.
     *
     * @param CategoryCollection $collection
     * @return string[]
     *   e.g. [ "1/2/20/21/23", "1/2/20/21/24", etc. ] -> ["1", "2", "20", "21", "23", "24", etc. ]
     */
    protected function extractParentCategoryIds(CategoryCollection $collection): array
    {
        $parentIds = array_map(fn($category) => explode('/', $category->getPath()), $collection->getItems());
        // flatten and de-dupe
        return array_values(array_unique(array_merge(...$parentIds)));
    }

}
