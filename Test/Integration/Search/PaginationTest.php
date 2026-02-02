<?php

namespace Algolia\SearchAdapter\Test\Integration\Search;

use Algolia\SearchAdapter\Test\Integration\BackendSearchTestCase;

/**
 * Tests for pagination functionality including page navigation and page size handling
 *
 * @magentoDbIsolation disabled
 * @magentoAppIsolation enabled
 * @magentoConfigFixture default/catalog/search/engine algolia
 * @magentoConfigFixture current_store algoliasearch_instant/instant/is_instant_enabled 0
 */
class PaginationTest extends BackendSearchTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->indexOncePerClass(__CLASS__ . '::indexProducts');
    }

    protected function tearDown(): void
    {
        // Prevent inherited tear down and perform after all tests have executed
    }

    /**
     * Test that page 2 returns different results than page 1
     */
    public function testSecondPageReturnsDifferentResults(): void
    {
        $pageSize = 12;

        $page1Request = $this->buildSearchRequest(query: '', page: 1, pageSize: $pageSize);
        $page1Response = $this->executeBackendSearch($page1Request);
        $page1Ids = $this->getDocumentIds($page1Response);

        $page2Request = $this->buildSearchRequest(query: '', page: 2, pageSize: $pageSize);
        $page2Response = $this->executeBackendSearch($page2Request);
        $page2Ids = $this->getDocumentIds($page2Response);

        $overlap = array_intersect($page1Ids, $page2Ids);
        $this->assertEmpty($overlap, 'Page 1 and Page 2 should not have overlapping products');

        $this->assertNotEmpty($page1Ids, 'Page 1 should have products');
        $this->assertNotEmpty($page2Ids, 'Page 2 should have products');
    }

    /**
     * Test that page size is correctly respected
     */
    public function testPageSizeRespected(): void
    {
        $pageSize = 10;

        $request = $this->buildSearchRequest(query: '', page: 1, pageSize: $pageSize);
        $response = $this->executeBackendSearch($request);
        $totalCount = $this->searchResponseBuilder->build($response)->getTotalCount();
        $documents = iterator_to_array($response);

        if ($response->count() >= $pageSize) {
            $this->assertCount(
                $pageSize,
                $documents,
                "Should return exactly $pageSize products when total exceeds page size"
            );
        } else {
            $this->assertCount(
                $totalCount,
                $documents,
                "Should return all products when total is less than page size"
            );
        }
    }

    /**
     * Test total pages calculation is correct
     */
    public function testTotalPagesCalculation(): void
    {
        $pageSize = 12;

        $request = $this->buildSearchRequest(query: '', page: 1, pageSize: $pageSize);
        $response = $this->executeBackendSearch($request);
        $totalCount = $this->searchResponseBuilder->build($response)->getTotalCount();

        $expectedPages = (int) ceil($totalCount / $pageSize);

        // Verify expected page count by checking that last page exists and has products
        $lastPageRequest = $this->buildSearchRequest(query: '', page: $expectedPages, pageSize: $pageSize);
        $lastPageResponse = $this->executeBackendSearch($lastPageRequest);
        $lastPageDocuments = iterator_to_array($lastPageResponse);

        $this->assertGreaterThan(0, count($lastPageDocuments), 'Last page should have at least 1 product');

        $beyondLastRequest = $this->buildSearchRequest(query: '', page: $expectedPages + 1, pageSize: $pageSize);
        $beyondLastResponse = $this->executeBackendSearch($beyondLastRequest);
        $beyondLastDocuments = iterator_to_array($beyondLastResponse);

        $this->assertEmpty($beyondLastDocuments, 'Page beyond last should be empty');
    }

    /**
     * Test pagination maintains consistent ordering
     */
    public function testPaginationMaintainsConsistentOrdering(): void
    {
        $pageSize = 10;
        $totalPages = 3;
        $allIds = [];

        // Collect IDs from first few pages
        for ($page = 1; $page <= $totalPages; $page++) {
            $request = $this->buildSearchRequest(query: '', page: $page, pageSize: $pageSize);
            $response = $this->executeBackendSearch($request);
            $pageIds = $this->getDocumentIds($response);
            $allIds = array_merge($allIds, $pageIds);
        }

        $uniqueIds = array_unique($allIds);
        $this->assertCount(
            count($allIds),
            $uniqueIds,
            'Products should not repeat across different pages'
        );

        // Total collected should match expected
        $firstPageRequest = $this->buildSearchRequest(query: '', page: 1, pageSize: $pageSize * $totalPages);
        $firstPageResponse = $this->executeBackendSearch($firstPageRequest);
        $totalCount = $firstPageResponse->count();

        $this->assertEquals($totalCount, count($allIds), 'Should collect expected number of products');
    }

    /**
     * Test large page sizes
     */
    public function testLargePageSize(): void
    {
        $pageSize = 100;

        $request = $this->buildSearchRequest(query: '', page: 1, pageSize: $pageSize);
        $response = $this->executeBackendSearch($request);
        $documents = iterator_to_array($response);
        $totalCount = $this->searchResponseBuilder->build($response)->getTotalCount();

        // Should return products up to pageSize or total, whichever is smaller
        $expectedCount = min($totalCount, $pageSize);
        $this->assertCount($expectedCount, $documents, 'Should return correct number for large page size');
    }

    /**
     * Test small page sizes
     *
     * @dataProvider smallPageSizeProvider
     */
    public function testSmallPageSizes(int $pageSize): void
    {
        $request = $this->buildSearchRequest(query: '', page: 1, pageSize: $pageSize);
        $response = $this->executeBackendSearch($request);
        $documents = iterator_to_array($response);
        $totalCount = $this->searchResponseBuilder->build($response)->getTotalCount();

        // Should return products up to pageSize
        $expectedCount = min($totalCount, $pageSize);
        $this->assertCount($expectedCount, $documents, "Should return $pageSize products");
    }

    /**
     * Data provider for small page sizes
     */
    public static function smallPageSizeProvider(): array
    {
        return [
            'page size 1' => [1],
            'page size 2' => [2],
            'page size 5' => [5],
            'page size 10' => [10],
            'page size 20' => [20]
        ];
    }

    /**
     * Test page navigation through all results
     *
     * @dataProvider pageNavigationProvider
     */
    public function testPageNavigation(int $pageSize, int $maxPagesToTest): void
    {
        // Get total count first
        $firstRequest = $this->buildSearchRequest(query: '', page: 1, pageSize: $pageSize);
        $firstResponse = $this->executeBackendSearch($firstRequest);
        $totalCount = $this->searchResponseBuilder->build($firstResponse)->getTotalCount();

        $totalPages = (int) ceil($totalCount / $pageSize);
        $pagesToTest = min($maxPagesToTest, $totalPages);

        $previousIds = [];

        for ($page = 1; $page <= $pagesToTest; $page++) {
            $request = $this->buildSearchRequest(query: '', page: $page, pageSize: $pageSize);
            $response = $this->executeBackendSearch($request);
            $currentIds = $this->getDocumentIds($response);

            $overlap = array_intersect($currentIds, $previousIds);
            $this->assertEmpty(
                $overlap,
                "Page $page should not overlap with previous pages"
            );

            // If not the last page, should have full pageSize
            if ($page < $totalPages) {
                $this->assertCount(
                    $pageSize,
                    $currentIds,
                    "Page $page (not last) should have $pageSize products"
                );
            }

            $previousIds = array_merge($previousIds, $currentIds);
        }
    }

    /**
     * Data provider for page navigation
     */
    public static function pageNavigationProvider(): array
    {
        return [
            'page size 12, 5 pages' => [12, 5],
            'page size 24, 3 pages' => [24, 3],
            'page size 36, 2 pages' => [36, 2],
        ];
    }

    /**
     * Test that offset calculations are correct for Magento 1-based to Algolia 0-based conversion
     */
    public function testOffsetCalculationFromPageNumber(): void
    {
        $pageSize = 12;

        // Page 1 should start at offset 0
        $page1Request = $this->buildSearchRequest(query: '', page: 1, pageSize: $pageSize);
        $this->assertEquals(0, $page1Request->getFrom(), 'Page 1 should have offset 0');

        // Page 2 should start at offset pageSize
        $page2Request = $this->buildSearchRequest(query: '', page: 2, pageSize: $pageSize);
        $this->assertEquals($pageSize, $page2Request->getFrom(), 'Page 2 should have offset 12');

        // Page 3 should start at offset pageSize * 2
        $page3Request = $this->buildSearchRequest(query: '', page: 3, pageSize: $pageSize);
        $this->assertEquals($pageSize * 2, $page3Request->getFrom(), 'Page 3 should have offset 24');
    }

    /**
     * Test pagination with filtered results
     */
    public function testPaginationWithFilteredResults(): void
    {
        $pageSize = 5;

        // Apply a price filter to reduce results
        $priceFilter = $this->buildPriceFilter(20.00, 60.00);

        // Get filtered page 1
        $page1Request = $this->buildSearchRequest(
            query: '',
            filters: ['price' => $priceFilter],
            page: 1,
            pageSize: $pageSize
        );
        $page1Response = $this->executeBackendSearch($page1Request);
        $page1Ids = $this->getDocumentIds($page1Response);
        $filteredTotal = $this->searchResponseBuilder->build($page1Response)->getTotalCount();

        // Skip if not enough filtered results for pagination
        if ($filteredTotal <= $pageSize) {
            $this->markTestSkipped('Not enough filtered results to test pagination');
        }

        // Get filtered page 2
        $page2Request = $this->buildSearchRequest(
            query: '',
            filters: ['price' => $priceFilter],
            page: 2,
            pageSize: $pageSize
        );
        $page2Response = $this->executeBackendSearch($page2Request);
        $page2Ids = $this->getDocumentIds($page2Response);

        // Pages should not overlap
        $overlap = array_intersect($page1Ids, $page2Ids);
        $this->assertEmpty($overlap, 'Filtered page 1 and 2 should not overlap');

        // Both pages should have products
        $this->assertNotEmpty($page1Ids, 'Filtered page 1 should have products');
        $this->assertNotEmpty($page2Ids, 'Filtered page 2 should have products');
    }
}
