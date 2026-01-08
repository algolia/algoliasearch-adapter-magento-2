<?php

namespace Algolia\SearchAdapter\Test\Unit\Service\Category;

use Algolia\AlgoliaSearch\Helper\ConfigHelper;
use Algolia\AlgoliaSearch\Service\Category\CategoryPathProvider as CoreCategoryPathProvider;
use Algolia\AlgoliaSearch\Test\TestCase;
use Algolia\SearchAdapter\Service\Category\CategoryPathProvider;
use Magento\Catalog\Model\Category;
use Magento\Catalog\Model\ResourceModel\Category\Collection as CategoryCollection;
use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory as CategoryCollectionFactory;
use PHPUnit\Framework\MockObject\MockObject;

class CategoryPathProviderTest extends TestCase
{
    private ?CategoryPathProvider $provider = null;
    private null|(CategoryCollectionFactory&MockObject) $categoryCollectionFactory = null;
    private null|(ConfigHelper&MockObject) $configHelper = null;
    private null|(CoreCategoryPathProvider&MockObject) $coreCategoryPathProvider = null;

    protected function setUp(): void
    {
        $this->categoryCollectionFactory = $this->createMock(CategoryCollectionFactory::class);
        $this->configHelper = $this->createMock(ConfigHelper::class);
        $this->coreCategoryPathProvider = $this->createMock(CoreCategoryPathProvider::class);

        $this->provider = new CategoryPathProvider(
            $this->categoryCollectionFactory,
            $this->configHelper,
            $this->coreCategoryPathProvider
        );
    }

    // =========================================================================
    // getCategoryPaths() Tests
    // =========================================================================

    public function testGetCategoryPathsWithEmptyIds(): void
    {
        $collection = $this->createMockCollection([]);

        $this->categoryCollectionFactory
            ->method('create')
            ->willReturn($collection);

        $this->coreCategoryPathProvider
            ->method('getCategoryNameMap')
            ->willReturn([]);

        $result = $this->provider->getCategoryPaths([], 1);

        $this->assertEquals([], $result);
    }

    public function testGetCategoryPathsWithSingleCategory(): void
    {
        $storeId = 1;
        $categoryId = '20';
        $categoryPath = '1/2/20';

        $category = $this->createMockCategory($categoryId, $categoryPath);

        $collection = $this->createMockCollection([$category]);

        $this->categoryCollectionFactory
            ->method('create')
            ->willReturn($collection);

        $this->coreCategoryPathProvider
            ->method('getCategoryNameMap')
            ->with(['1', '2', '20'], $storeId)
            ->willReturn([
                '20' => 'Men',
            ]);

        $this->configHelper
            ->method('getCategorySeparator')
            ->with($storeId)
            ->willReturn(' /// ');

        $result = $this->provider->getCategoryPaths([$categoryId], $storeId);

        $this->assertEquals(['20' => 'Men'], $result);
    }

    public function testGetCategoryPathsWithNestedCategory(): void
    {
        $storeId = 1;
        $categoryId = '25';
        $categoryPath = '1/2/20/25';

        $category = $this->createMockCategory($categoryId, $categoryPath);

        $collection = $this->createMockCollection([$category]);

        $this->categoryCollectionFactory
            ->method('create')
            ->willReturn($collection);

        $this->coreCategoryPathProvider
            ->method('getCategoryNameMap')
            ->willReturn([
                '20' => 'Men',
                '25' => 'Tops',
            ]);

        $this->configHelper
            ->method('getCategorySeparator')
            ->willReturn(' /// ');

        $result = $this->provider->getCategoryPaths([$categoryId], $storeId);

        $this->assertEquals(['25' => 'Men /// Tops'], $result);
    }

    public function testGetCategoryPathsWithMultipleCategories(): void
    {
        $storeId = 1;

        $category1 = $this->createMockCategory('20', '1/2/20');
        $category2 = $this->createMockCategory('30', '1/2/30');

        $collection = $this->createMockCollection([$category1, $category2]);

        $this->categoryCollectionFactory
            ->method('create')
            ->willReturn($collection);

        $this->coreCategoryPathProvider
            ->method('getCategoryNameMap')
            ->willReturn([
                '20' => 'Men',
                '30' => 'Women',
            ]);

        $this->configHelper
            ->method('getCategorySeparator')
            ->willReturn(' /// ');

        $result = $this->provider->getCategoryPaths(['20', '30'], $storeId);

        $this->assertEquals([
            '20' => 'Men',
            '30' => 'Women',
        ], $result);
    }

    public function testGetCategoryPathsWithNullStoreId(): void
    {
        $category = $this->createMockCategory('20', '1/2/20');

        $collection = $this->createMock(CategoryCollection::class);
        $collection->method('addFieldToFilter')->willReturnSelf();
        $collection->method('setStore')->with(null)->willReturnSelf();
        $collection->method('getItems')->willReturn([$category]);

        $this->categoryCollectionFactory
            ->method('create')
            ->willReturn($collection);

        $this->coreCategoryPathProvider
            ->method('getCategoryNameMap')
            ->with(['1', '2', '20'], null)
            ->willReturn([
                '20' => 'Electronics',
            ]);

        $this->configHelper
            ->method('getCategorySeparator')
            ->with(null)
            ->willReturn(' /// ');

        $result = $this->provider->getCategoryPaths(['20'], null);

        $this->assertEquals(['20' => 'Electronics'], $result);
    }

    public function testGetCategoryPathsWithCustomSeparator(): void
    {
        $storeId = 2;
        $category = $this->createMockCategory('25', '1/2/20/25');

        $collection = $this->createMockCollection([$category]);

        $this->categoryCollectionFactory
            ->method('create')
            ->willReturn($collection);

        $this->coreCategoryPathProvider
            ->method('getCategoryNameMap')
            ->willReturn([
                '20' => 'Clothing',
                '25' => 'Shirts',
            ]);

        $this->configHelper
            ->method('getCategorySeparator')
            ->with($storeId)
            ->willReturn(' > ');

        $result = $this->provider->getCategoryPaths(['25'], $storeId);

        $this->assertEquals(['25' => 'Clothing > Shirts'], $result);
    }

    // =========================================================================
    // buildFullCategoryPath() Tests
    // =========================================================================

    public function testBuildFullCategoryPath(): void
    {
        $path = '1/2/20/25/30';
        $categoryMap = [
            '1'  => '',
            '2'  => '',
            '20' => 'Men',
            '25' => 'Tops',
            '30' => 'Shirts',
        ];

        $this->configHelper
            ->method('getCategorySeparator')
            ->willReturn(' /// ');

        $result = $this->invokeMethod($this->provider, 'buildFullCategoryPath', [$path, $categoryMap, 1]);

        $this->assertEquals('Men /// Tops /// Shirts', $result);
    }

    public function testBuildFullCategoryPathWithMissingCategories(): void
    {
        $path = '1/2/20/25';
        $categoryMap = [
            '20' => 'Men',
            // 25 is missing from the map
        ];

        $this->configHelper
            ->method('getCategorySeparator')
            ->willReturn(' /// ');

        $result = $this->invokeMethod($this->provider, 'buildFullCategoryPath', [$path, $categoryMap, 1]);

        $this->assertEquals('Men', $result);
    }

    // =========================================================================
    // extractParentCategoryIds() Tests
    // =========================================================================

    public function testExtractParentCategoryIds(): void
    {
        $category1 = $this->createMockCategory('20', '1/2/20');
        $category2 = $this->createMockCategory('25', '1/2/20/25');

        $collection = $this->createMock(CategoryCollection::class);
        $collection->method('getItems')->willReturn([$category1, $category2]);

        $result = $this->invokeMethod($this->provider, 'extractParentCategoryIds', [$collection]);

        // Should flatten and de-dupe: 1, 2, 20, 25
        $this->assertCount(4, $result);
        $this->assertContains('1', $result);
        $this->assertContains('2', $result);
        $this->assertContains('20', $result);
        $this->assertContains('25', $result);
    }

    public function testExtractParentCategoryIdsWithEmptyCollection(): void
    {
        $collection = $this->createMock(CategoryCollection::class);
        $collection->method('getItems')->willReturn([]);

        $result = $this->invokeMethod($this->provider, 'extractParentCategoryIds', [$collection]);

        $this->assertEquals([], $result);
    }

    public function testExtractParentCategoryIdsDedupesOverlappingPaths(): void
    {
        $category1 = $this->createMockCategory('25', '1/2/20/25');
        $category2 = $this->createMockCategory('26', '1/2/20/26');

        $collection = $this->createMock(CategoryCollection::class);
        $collection->method('getItems')->willReturn([$category1, $category2]);

        $result = $this->invokeMethod($this->provider, 'extractParentCategoryIds', [$collection]);

        // Should have unique IDs only: 1, 2, 20, 25, 26
        $this->assertCount(5, $result);
        $this->assertEquals(count(array_unique($result)), count($result), 'IDs should be unique');
    }

    // =========================================================================
    // Helper Methods
    // =========================================================================

    protected function createMockCategory(string $id, string $path): Category&MockObject
    {
        $category = $this->createMock(Category::class);
        $category->method('getId')->willReturn($id);
        $category->method('getPath')->willReturn($path);
        return $category;
    }

    protected function createMockCollection(array $categories): CategoryCollection&MockObject
    {
        $collection = $this->createMock(CategoryCollection::class);
        $collection->method('addFieldToFilter')->willReturnSelf();
        $collection->method('setStore')->willReturnSelf();
        $collection->method('getItems')->willReturn($categories);
        return $collection;
    }

}

