<?php

namespace Algolia\SearchAdapter\Test\Integration\Search;

use Algolia\AlgoliaSearch\Exceptions\AlgoliaException;
use Algolia\AlgoliaSearch\Test\Integration\IndexCleaner;
use Algolia\SearchAdapter\Test\Integration\BackendSearchTestCase;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;

class SearchResultsTest extends BackendSearchTestCase
{
    protected int $expectedProductCount;
    protected static string $testSuiteIndexPrefix;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setupTestSuiteIndexPrefix();

        $this->runOnce(function() {
            $this->indexAllProducts();
        }, __CLASS__ . '::indexProducts');

        $this->expectedProductCount = $this->assertValues->productsOnStockCount;
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
}
