<?php

namespace Algolia\SearchAdapter\Test\Integration\Search;

use Algolia\SearchAdapter\Test\Integration\BackendSearchTestCase;

class FilteredSearchTest extends BackendSearchTestCase
{
    protected int $expectedProductCount;

    protected function setUp(): void
    {
        parent::setUp();
        $this->indexOncePerClass(__CLASS__ . '::indexProducts');
        $this->expectedProductCount = $this->assertValues->productsOnStockCount;
    }

    protected function tearDown(): void
    {
        // Prevent inherited tear down and perform after all tests have executed
    }

    /**
     * Test that category filter correctly filters products
     *
     * @magentoDbIsolation disabled
     */
    public function testCategoryFilterApplied(): void
    {
        $unfilteredRequest = $this->buildSearchRequest(query: '', pageSize: 12);
        $unfilteredResponse = $this->executeBackendSearch($unfilteredRequest);
        $unfilteredCount = $this->searchResponseBuilder->build($unfilteredResponse)->getTotalCount();

        $filteredRequest = $this->buildCategoryRequest(
            categoryId: self::CATEGORY_WOMEN_TOPS,
            pageSize: 50
        );
        $filteredResponse = $this->executeBackendSearch($filteredRequest);
        $filteredCount = $this->searchResponseBuilder->build($filteredResponse)->getTotalCount();

        $this->assertLessThan(
            $unfilteredCount,
            $filteredCount,
            'Category filter should reduce the number of products'
        );

        $this->assertGreaterThan(0, $filteredCount, 'Category should have products');
    }
}
