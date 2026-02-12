<?php

namespace Algolia\SearchAdapter\Test\Integration\Search;

use Algolia\SearchAdapter\Test\Integration\BackendSearchTestCase;

/**
 * Tests for filtered search functionality including category, price, and attribute filters
 *
 * @magentoDbIsolation disabled
 */
class FilteredSearchTest extends BackendSearchTestCase
{
    protected int $expectedProductCount;

    protected function setUp(): void
    {
        parent::setUp();

        $this->configureFacets(); // mock facets
        $this->indexOncePerClass(__CLASS__ . '::indexProducts');

        $this->expectedProductCount = $this->assertValues->productsOnStockCount;
    }

    /**
     * Does not call parent tearDown
     * Index tear down will occur after all tests have executed for this suite
     * @see \Algolia\AlgoliaSearch\Test\Integration\Frontend\Search\SearchTestCase::tearDownAfterClass
     */
    protected function tearDown(): void
    {
        $this->runOnce(function() {
            $this->resetOutOfStockUseCase();
        }, __CLASS__ . '::tearDown');
    }

    /**
     * Test that category filter correctly filters products
     *
     * @magentoConfigFixture default/catalog/search/engine algolia
     */
    public function testCategoryFilterApplied(): void
    {
        $unfilteredRequest = $this->buildSearchRequest(query: '');
        $unfilteredResponse = $this->executeBackendSearch($unfilteredRequest);
        $unfilteredCount = $this->searchResponseBuilder->build($unfilteredResponse)->getTotalCount();

        $filteredRequest = $this->buildCategoryRequest(categoryId: self::CATEGORY_WOMEN_TOPS);
        $filteredResponse = $this->executeBackendSearch($filteredRequest);
        $filteredCount = $this->searchResponseBuilder->build($filteredResponse)->getTotalCount();

        $this->assertLessThan(
            $unfilteredCount,
            $filteredCount,
            'Category filter should reduce the number of products'
        );

        $this->assertGreaterThan(0, $filteredCount, 'Category should have products');
    }

    /**
     * Test category filter with different categories
     *
     * @dataProvider categoryProvider
     * @magentoConfigFixture default/catalog/search/engine algolia
     */
    public function testCategoryFilterWithDifferentCategories(
        int $categoryId,
        string $categoryName,
        bool $hasProducts): void
    {
        $request = $this->buildCategoryRequest(
            categoryId: $categoryId,
            pageSize: 50
        );

        $response = $this->executeBackendSearch($request);
        $documents = iterator_to_array($response);

        if ($hasProducts) {
            $this->assertGreaterThan(
                0,
                count($documents),
                "Category '$categoryName' (ID: $categoryId) should have products"
            );
        }
    }

    /**
     * Data provider for categories
     */
    public static function categoryProvider(): array
    {
        return [
            'Women Tops' => [self::CATEGORY_WOMEN_TOPS, 'Women > Tops', true],
            'Women Bottoms' => [self::CATEGORY_WOMEN_BOTTOMS, 'Women > Bottoms', true],
            'Men Tops' => [self::CATEGORY_MEN_TOPS, 'Men > Tops', true],
            'Men Bottoms' => [self::CATEGORY_MEN_BOTTOMS, 'Men > Bottoms', true],
            'Non-existent Category' => [999999, 'Non-existent Category', false],
        ];
    }

    /**
     * Test that price range filter correctly filters products
     *
     * @magentoConfigFixture default/catalog/search/engine algolia
     */
    public function testPriceRangeFilterApplied(): void
    {
        $unfilteredRequest = $this->buildSearchRequest(query: '');
        $unfilteredResponse = $this->executeBackendSearch($unfilteredRequest);
        $unfilteredCount = $this->searchResponseBuilder->build($unfilteredResponse)->getTotalCount();

        // Apply price range filter (products between $20 and $50)
        $priceFilter = $this->buildPriceFilter(20.00, 50.00);
        $filteredRequest = $this->buildSearchRequest(
            query: '',
            filters: ['price' => $priceFilter]
        );
        $filteredResponse = $this->executeBackendSearch($filteredRequest);
        $filteredCount = $this->searchResponseBuilder->build($filteredResponse)->getTotalCount();

        $this->assertLessThan(
            $unfilteredCount,
            $filteredCount,
            'Price filter should reduce the number of products'
        );

        // Should still have some results in the $20-$50 range
        $this->assertGreaterThan(0, $filteredCount, 'Price range should have products');
    }

    /**
     * Test different price ranges
     *
     * @dataProvider priceRangeProvider
     * @magentoConfigFixture default/catalog/search/engine algolia
     */
    public function testDifferentPriceRanges(float $from, float $to, bool $expectResults): void
    {
        $priceFilter = $this->buildPriceFilter($from, $to);
        $request = $this->buildSearchRequest(
            query: '',
            filters: ['price' => $priceFilter]
        );

        $response = $this->executeBackendSearch($request);
        $documents = iterator_to_array($response);

        if ($expectResults) {
            $this->assertGreaterThan(
                0,
                count($documents),
                "Price range \$$from - \$$to should have products"
            );
        } else {
            $this->assertCount(
                0,
                $documents,
                "Price range \$$from - \$$to should have no products"
            );
        }
    }

    /**
     * Data provider for price ranges
     */
    public static function priceRangeProvider(): array
    {
        return [
            'low price range' => [0.00, 30.00, true],
            'mid price range' => [30.00, 60.00, true],
            'high price range' => [60.00, 100.00, true],
            'very high price range' => [100.00, 500.00, false],
            'unrealistic price range' => [10000.00, 20000.00, false],
        ];
    }

    /**
     * Test that attribute filter (color) correctly filters products
     *
     * @magentoConfigFixture default/catalog/search/engine algolia
     */
    public function testColorAttributeFilterApplied(): void
    {
        $unfilteredRequest = $this->buildCategoryRequest(categoryId: self::CATEGORY_MEN_TOPS);
        $unfilteredResponse = $this->executeBackendSearch($unfilteredRequest);
        $unfilteredCount = $this->searchResponseBuilder->build($unfilteredResponse)->getTotalCount();

        // Get available color values from facets to use a valid color
        $colorValues = $this->getBucketValues($unfilteredResponse, 'color_bucket');

        // Skip test if no colors are available
        if (empty($colorValues)) {
            $this->markTestSkipped('No color facet values available in test data');
        }

        // Use the first available color
        $colorToFilter = array_key_first($colorValues);

        // Apply color filter
        $colorFilter = $this->buildAttributeFilter('color', $colorToFilter);
        $filteredRequest = $this->buildCategoryRequest(
            categoryId: self::CATEGORY_MEN_TOPS,
            filters: ['color' => $colorFilter]
        );

        $filteredResponse = $this->executeBackendSearch($filteredRequest);
        $filteredCount = $this->searchResponseBuilder->build($filteredResponse)->getTotalCount();

        $this->assertLessThan(
            $unfilteredCount,
            $filteredCount,
            "Color filter '$colorToFilter' should reduce the number of products"
        );

        $this->assertGreaterThan(0, $filteredCount, "Color '$colorToFilter' should have products");
    }

    /**
     * Test that attribute filter (size) correctly filters products
     *
     * @magentoConfigFixture default/catalog/search/engine algolia
     */
    public function testSizeAttributeFilterApplied(): void
    {
        $unfilteredRequest = $this->buildCategoryRequest(categoryId: self::CATEGORY_WOMEN_BOTTOMS);
        $unfilteredResponse = $this->executeBackendSearch($unfilteredRequest);
        $unfilteredCount = $this->searchResponseBuilder->build($unfilteredResponse)->getTotalCount();

        // Get available size values from facets
        $sizeValues = $this->getBucketValues($unfilteredResponse, 'size_bucket');

        // Skip test if no sizes are available
        if (empty($sizeValues)) {
            $this->markTestSkipped('No size facet values available in test data');
        }

        // Use the first available size
        $sizeToFilter = array_key_first($sizeValues);

        // Apply size filter
        $sizeFilter = $this->buildAttributeFilter('size', $sizeToFilter);
        $filteredRequest = $this->buildCategoryRequest(
            categoryId: self::CATEGORY_WOMEN_BOTTOMS,
            filters: ['size' => $sizeFilter]
        );

        $filteredResponse = $this->executeBackendSearch($filteredRequest);
        $filteredCount = $this->searchResponseBuilder->build($filteredResponse)->getTotalCount();
        $this->assertEquals(
            $unfilteredCount,
            $filteredCount,
            "All products in category should have the size filter '$sizeToFilter'"
        );

        $this->assertGreaterThan(0, $filteredCount, "Size '$sizeToFilter' should have products");
    }

    /**
     * Test multiple filters applied together
     *
     * @magentoConfigFixture default/catalog/search/engine algolia
     */
    public function testMultipleFiltersApplied(): void
    {
        // Get unfiltered count
        $unfilteredRequest = $this->buildSearchRequest(query: '');
        $unfilteredResponse = $this->executeBackendSearch($unfilteredRequest);
        $unfilteredCount = $this->searchResponseBuilder->build($unfilteredResponse)->getTotalCount();

        // Apply category filter alone first
        $categoryOnlyRequest = $this->buildCategoryRequest(
            categoryId: self::CATEGORY_WOMEN_TOPS
        );
        $categoryOnlyResponse = $this->executeBackendSearch($categoryOnlyRequest);
        $categoryOnlyCount = $this->searchResponseBuilder->build($categoryOnlyResponse)->getTotalCount();

        // Now apply category + price filter
        $priceFilter = $this->buildPriceFilter(20.00, 80.00);
        $categoryWithPriceRequest = $this->buildCategoryRequest(
            categoryId: self::CATEGORY_WOMEN_TOPS,
            filters: ['price' => $priceFilter]
        );
        $categoryWithPriceResponse = $this->executeBackendSearch($categoryWithPriceRequest);
        $categoryWithPriceCount = $this->searchResponseBuilder->build($categoryWithPriceResponse)->getTotalCount();

        // Multiple filters should be more restrictive
        $this->assertLessThanOrEqual(
            $categoryOnlyCount,
            $categoryWithPriceCount,
            'Adding price filter to category filter should reduce or maintain results'
        );

        // Category alone should be less than unfiltered
        $this->assertLessThan(
            $unfilteredCount,
            $categoryOnlyCount,
            'Category filter alone should reduce results'
        );
    }

    /**
     * Test category filter combined with search query
     *
     * @magentoConfigFixture default/catalog/search/engine algolia
     */
    public function testCategoryFilterWithSearchQuery(): void
    {
        // Search for "shirt" without category filter
        $unfilteredSearchRequest = $this->buildSearchRequest(query: 'shirt');
        $unfilteredSearchResponse = $this->executeBackendSearch($unfilteredSearchRequest);
        $unfilteredSearchCount = $this->searchResponseBuilder->build($unfilteredSearchResponse)->getTotalCount();

        // Search for "shirt" with Women's Tops category filter
        $categoryFilter = $this->buildCategoryFilter(self::CATEGORY_WOMEN_TOPS);
        $filteredSearchRequest = $this->buildSearchRequest(
            query: 'shirt',
            filters: ['category' => $categoryFilter]
        );

        $filteredSearchResponse = $this->executeBackendSearch($filteredSearchRequest);
        $filteredSearchCount = $this->searchResponseBuilder->build($filteredSearchResponse)->getTotalCount();

        // Combined should be more restrictive (or equal if all shirts are in that category)
        $this->assertLessThanOrEqual(
            $unfilteredSearchCount,
            $filteredSearchCount,
            'Category filter combined with search should reduce or maintain results'
        );
    }

    /**
     * Test that facet counts update when filters are applied
     *
     * @magentoConfigFixture default/catalog/search/engine algolia
     */
    public function testFacetCountsUpdateWithFilters(): void
    {
        // Get facets without filters
        $unfilteredRequest = $this->buildSearchRequest(query: '');
        $unfilteredResponse = $this->executeBackendSearch($unfilteredRequest);
        $unfilteredColorFacets = $this->getBucketValues($unfilteredResponse, 'color_bucket');
        $totalUnfilteredColorCount = $this->getTotalFilteredCount($unfilteredColorFacets);

        // Apply price filter
        $priceFilter = $this->buildPriceFilter(50.00, 100.00);
        $filteredRequest = $this->buildSearchRequest(
            query: '',
            filters: ['price' => $priceFilter]
        );
        $filteredResponse = $this->executeBackendSearch($filteredRequest);
        $filteredColorFacets = $this->getBucketValues($filteredResponse, 'color_bucket');

        // Facet counts should potentially differ when filters are applied
        // (unless price filter doesn't affect categories)
        // At minimum, total of all filtered color counts should be <= total unfiltered color counts
        $totalFilteredColorCount = $this->getTotalFilteredCount($filteredColorFacets);

        $this->assertLessThanOrEqual(
            $totalUnfilteredColorCount,
            $totalFilteredColorCount,
            'Filtered color counts should be less than or equal to unfiltered counts'
        );
    }

    /**
     * For a given array of facets, where each facet contains a count of matches, get the sum of all of these counts.
     */
    protected function getTotalFilteredCount(array $bucketValues): int
    {
        return array_sum(array_map(
            fn($metrics) => $metrics['count'] ?? 0,
            $bucketValues
        ));
    }
}
