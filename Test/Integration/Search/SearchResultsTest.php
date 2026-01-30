<?php

namespace Algolia\SearchAdapter\Test\Integration\Search;

use Algolia\AlgoliaSearch\Exceptions\AlgoliaException;
use Algolia\AlgoliaSearch\Test\Integration\IndexCleaner;
use Algolia\SearchAdapter\Test\Integration\BackendSearchTestCase;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Search\Request\Aggregation\TermBucket;
use Magento\Framework\Search\SearchResponseBuilder;

class SearchResultsTest extends BackendSearchTestCase
{
    protected int $expectedProductCount;
    protected static string $testSuiteIndexPrefix;

    protected ?SearchResponseBuilder $searchResponseBuilder = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setupTestSuiteIndexPrefix();

        $this->runOnce(function() {
            $this->addFacet('size', 'conjunctive');
            $this->indexAllProducts();
        }, __CLASS__ . '::indexProducts');

        $this->expectedProductCount = $this->assertValues->productsOnStockCount;
        $this->searchResponseBuilder =  $this->objectManager->get(SearchResponseBuilder::class);
    }

    protected function tearDown(): void
    {
        // Prevent inherited tear down and perform after all tests have executed
    }

    /**
     * @throws NoSuchEntityException|AlgoliaException
     */
    public static function tearDownAfterClass(): void
    {
        IndexCleaner::clean(self::$testSuiteIndexPrefix);
    }

    /**
     * Removes timestamp from index prefix for index reuse.
     * For expected format see:
     * @see \Algolia\AlgoliaSearch\Test\Integration\TestCase::bootstrap
     */
    protected function setupTestSuiteIndexPrefix(): void
    {
        $this->indexPrefix = $this->simplifyIndexPrefix($this->indexPrefix);
        self::$testSuiteIndexPrefix = $this->indexPrefix; // Clear after all tests
        $this->setConfig('algoliasearch_credentials/credentials/index_prefix', $this->indexPrefix);
    }

    /**
     * In order to reuse the same index across tests strip the timestamp
     */
    protected function simplifyIndexPrefix(string $indexPrefix): string
    {
        $parts = explode('_', $this->indexPrefix);
        unset($parts[2]); // kill the timestamp
        return implode('_', array_values($parts));
    }

    /**
     * Test that a basic search returns the expected number of products on first page
     *
     * @magentoDbIsolation disabled
     * @throws AlgoliaException
     * @throws NoSuchEntityException
     * @throws LocalizedException
     */
    public function testBasicSearchReturnsFirstPageProducts(): void
    {
        $request = $this->buildSearchRequest(
            query: '',
            page: 1,
            pageSize: 12
        );

        $response = $this->executeBackendSearch($request);

        // First page should return exactly pageSize products (or less if total < pageSize)
        $documents = iterator_to_array($response);
        $this->assertLessThanOrEqual(12, count($documents));
        $this->assertGreaterThan(0, count($documents), 'Search should return at least some products');
    }

    /**
     * Test that product count matches expected total
     * @magentoDbIsolation disabled
     * @throws AlgoliaException
     * @throws NoSuchEntityException
     * @throws LocalizedException
     */
    public function testProductCountMatchesExpected(): void
    {
        $pageSize = 12;
        $request = $this->buildSearchRequest(
            pageSize: $pageSize
        );

        $response = $this->executeBackendSearch($request);

        $this->assertEquals($pageSize, $response->count(), 'Page size should match response count');

        // Use the SearchResponseBuilder to get the total count due to deprecated getTotal() method
        $searchResult = $this->searchResponseBuilder->build($response);
        $this->assertEquals(
            $this->expectedProductCount,
            $searchResult->getTotalCount(),
            'Total product count should match expected value'
        );
    }


    /**
     * @magentoDbIsolation disabled
     * @throws AlgoliaException
     * @throws NoSuchEntityException
     * @throws LocalizedException
     * Test that number of pages is correctly calculated
     */
    public function testNumberOfPagesCalculation(): void
    {
        $pageSize = 12;
        $request = $this->buildSearchRequest(
            pageSize: $pageSize
        );

        $response = $this->executeBackendSearch($request);
        $searchResult = $this->searchResponseBuilder->build($response);
        $totalCount = $searchResult->getTotalCount();

        $expectedPages = (int) ceil($totalCount / $pageSize);

        // Verify by requesting last page
        $lastPageRequest = $this->buildSearchRequest(
            page: $expectedPages,
            pageSize: $pageSize
        );

        $lastPageResponse = $this->executeBackendSearch($lastPageRequest);
        $lastPageDocuments = iterator_to_array($lastPageResponse);

        $this->assertGreaterThan(0, count($lastPageDocuments), 'Last page should have products');

        $calculatedTotal = ($expectedPages - 1) * $pageSize + count($lastPageDocuments);
        $this->assertEquals($totalCount, $calculatedTotal, 'Page calculation should account for all products');
    }

    /**
     * Test that category facets are returned in aggregations
     *
     * @magentoDbIsolation disabled
     * @throws AlgoliaException
     * @throws NoSuchEntityException
     * @throws LocalizedException
     */
    public function testCategoryFacetsReturned(): void
    {
        $request = $this->buildSearchRequest(
            pageSize: 12,
            aggregations: [
                'category_bucket' => new TermBucket(
                    name: 'category_bucket',
                    field: 'category_ids',
                    metrics: [],
                    parameters: [ 'include' => [23, 24, 25, 26]] // sample category ID filters supplied in request
                ),
            ]
        );

        $response = $this->executeBackendSearch($request);

        $this->assertBucketExists($response, 'category_bucket');
        $this->assertBucketHasValues($response, 'category_bucket');
    }

    /**
     * Test that price facets are returned in aggregations
     *
     * @magentoDbIsolation disabled
     * @throws AlgoliaException
     * @throws NoSuchEntityException
     * @throws LocalizedException
     */
    public function testPriceFacetsReturned(): void
    {
        $request = $this->buildSearchRequest(
            pageSize: 12
        );

        $response = $this->executeBackendSearch($request);

        $this->assertBucketExists($response, 'price_bucket');
        $this->assertBucketHasValues($response, 'price_bucket');
    }

    /**
     * Test that color attribute facets are returned
     *
     * @magentoDbIsolation disabled
     * @throws AlgoliaException
     * @throws NoSuchEntityException
     * @throws LocalizedException
     */
    public function testColorAttributeFacetsReturned(): void
    {
        $request = $this->buildSearchRequest(
            pageSize: 12
        );

        $response = $this->executeBackendSearch($request);

        $this->assertBucketExists($response, 'color_bucket');
        $this->assertBucketHasValues($response, 'color_bucket');
    }

    /**
     * Test that size attribute facets are returned
     *
     * @magentoDbIsolation disabled
     * @throws AlgoliaException
     * @throws NoSuchEntityException
     * @throws LocalizedException
     */
    public function testSizeAttributeFacetsReturned(): void
    {
        $request = $this->buildCategoryRequest(
            categoryId: 21,
            pageSize: 12
        );

        $response = $this->executeBackendSearch($request);

        $this->assertBucketExists($response, 'size_bucket');
        $this->assertBucketHasValues($response, 'size_bucket');
    }

    /**
     * Test search with a specific query term returns relevant products
     *
     * @magentoDbIsolation disabled
     * @throws AlgoliaException
     * @throws NoSuchEntityException
     * @throws LocalizedException
     */
    public function testSearchWithQueryTermReturnsRelevantProducts(): void
    {
        $request = $this->buildSearchRequest(
            query: 'jacket',
            page: 1,
            pageSize: 12
        );

        $response = $this->executeBackendSearch($request);

        $documents = iterator_to_array($response);
        $this->assertGreaterThan(0, count($documents), 'Search for "jacket" should return results');

        $this->assertLessThan(
            $this->expectedProductCount,
            $response->count(),
            'Filtered search should return fewer products than total'
        );
    }

    /**
     * Test that first 5 products are returned correctly
     *
     * @magentoDbIsolation disabled
     * @throws AlgoliaException
     * @throws NoSuchEntityException
     * @throws LocalizedException
     */
    public function testFirst5ProductsReturned(): void
    {
        $request = $this->buildSearchRequest(
            pageSize: 5
        );

        $response = $this->executeBackendSearch($request);
        $documents = iterator_to_array($response);

        $this->assertCount(5, $documents, 'Should return exactly 5 products');

        foreach ($documents as $document) {
            $this->assertNotEmpty($document->getId(), 'Document should have an ID');
            $this->assertIsNumeric($document->getId(), 'Document ID should be numeric (product ID)');
        }
    }

    /**
     * Test empty search results handling
     *
     * @magentoDbIsolation disabled
     * @throws AlgoliaException
     * @throws NoSuchEntityException
     * @throws LocalizedException
     */
    public function testEmptySearchResultsHandling(): void
    {
        $request = $this->buildSearchRequest(
            query: 'xyznonexistentproduct123456789',
            pageSize: 12
        );

        $response = $this->executeBackendSearch($request);

        $documents = iterator_to_array($response);
        $this->assertCount(0, $documents, 'Search with non-existent term should return no results');
        $this->assertEquals(0, $response->count(), 'Total count should be 0');
    }

    /**
     * Test different page sizes
     *
     * @magentoDbIsolation disabled
     * @throws AlgoliaException
     * @throws NoSuchEntityException
     * @throws LocalizedException
     *
     * @dataProvider pageSizeProvider
     */
    public function testDifferentPageSizes(int $pageSize): void
    {
        $request = $this->buildSearchRequest(
            page: 1,
            pageSize: $pageSize
        );

        $response = $this->executeBackendSearch($request);
        $documents = iterator_to_array($response);

        $this->assertLessThanOrEqual(
            $pageSize,
            count($documents),
            "Should return at most $pageSize products"
        );

        // If we have enough products, first page should be full
        if ($response->count() >= $pageSize) {
            $this->assertCount(
                $pageSize,
                $documents,
                "First page should be full with $pageSize products"
            );
        }
    }

    /**
     * Data provider for page sizes
     */
    public static function pageSizeProvider(): array
    {
        return [
            'page size 12' => [12],
            'page size 24' => [24],
            'page size 36' => [36],
        ];
    }
}
