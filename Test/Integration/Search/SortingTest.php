<?php

namespace Algolia\SearchAdapter\Test\Integration\Search;

use Algolia\AlgoliaSearch\Helper\ConfigHelper;
use Algolia\AlgoliaSearch\Helper\Configuration\InstantSearchHelper;
use Algolia\SearchAdapter\Test\Integration\BackendSearchTestCase;
use Magento\Framework\Api\SortOrder;

/**
 * Tests for sorting functionality including price/name sorting and replica index usage
 *
 * @magentoDbIsolation disabled
 * @magentoAppIsolation enabled
 */
class SortingTest extends BackendSearchTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Configure sorting indices for testing
        $this->configureSortingIndices();
        $this->indexOncePerClass(__CLASS__ . '::indexProducts');
    }

    protected function tearDown(): void
    {
        // Prevent inherited tear down and perform after all tests have executed
    }

    /**
     * Test that price sort ascending returns products in price order
     *
     * @magentoConfigFixture default/catalog/search/engine algolia
     * @magentoConfigFixture current_store algoliasearch_instant/instant/is_instant_enabled 0
     *  Indexing must be enabled for the ReplicaManager
     * @magentoConfigFixture current_store algoliasearch_indexing_manager/algolia_indexing/enable_indexing 1
     */
    public function testPriceSortAscending(): void
    {
        $sort = $this->buildSort('price', SortOrder::SORT_ASC);
        $request = $this->buildSearchRequest(
            query: '',
            sort: $sort,
            page: 1,
            pageSize: 20
        );

        $response = $this->executeBackendSearch($request);
        $documentIds = $this->getDocumentIds($response);

        $this->assertGreaterThan(0, count($documentIds), 'Should return products');

        $this->assertPricesInOrder($documentIds, SortOrder::SORT_ASC);
    }

    /**
     * Test that price sort descending returns products in reverse price order
     *
     * @magentoConfigFixture default/catalog/search/engine algolia
     * @magentoConfigFixture current_store algoliasearch_instant/instant/is_instant_enabled 0
     * @magentoConfigFixture current_store algoliasearch_indexing_manager/algolia_indexing/enable_indexing 1
     */
    public function testPriceSortDescending(): void
    {
        $sort = $this->buildSort('price', SortOrder::SORT_DESC);
        $request = $this->buildSearchRequest(
            query: '',
            sort: $sort,
            page: 1,
            pageSize: 20
        );

        $response = $this->executeBackendSearch($request);
        $documentIds = $this->getDocumentIds($response);

        $this->assertGreaterThan(0, count($documentIds), 'Should return products');

        $this->assertPricesInOrder($documentIds, SortOrder::SORT_DESC);
    }

    /**
     * Test that name sort ascending returns products in alphabetical order
     *
     * @magentoConfigFixture default/catalog/search/engine algolia
     * @magentoConfigFixture current_store algoliasearch_instant/instant/is_instant_enabled 0
     * @magentoConfigFixture current_store algoliasearch_indexing_manager/algolia_indexing/enable_indexing 1
     */
    public function testNameSortAscending(): void
    {
        $sort = $this->buildSort('name', SortOrder::SORT_ASC);
        $request = $this->buildSearchRequest(
            query: '',
            sort: $sort,
            page: 1,
            pageSize: 20
        );

        $response = $this->executeBackendSearch($request);
        $documentIds = $this->getDocumentIds($response);

        $this->assertGreaterThan(0, count($documentIds), 'Should return products');

        $this->assertNamesInOrder($documentIds, SortOrder::SORT_ASC);
    }

    /**
     * Test that name sort descending returns products in reverse alphabetical order
     *
     * @magentoConfigFixture default/catalog/search/engine algolia
     * @magentoConfigFixture current_store algoliasearch_instant/instant/is_instant_enabled 0
     * @magentoConfigFixture current_store algoliasearch_indexing_manager/algolia_indexing/enable_indexing 1
     */
    public function testNameSortDescending(): void
    {
        $sort = $this->buildSort('name', SortOrder::SORT_DESC);
        $request = $this->buildSearchRequest(
            query: '',
            sort: $sort,
            page: 1,
            pageSize: 20
        );

        $response = $this->executeBackendSearch($request);
        $documentIds = $this->getDocumentIds($response);

        $this->assertGreaterThan(0, count($documentIds), 'Should return products');

        $this->assertNamesInOrder($documentIds, SortOrder::SORT_DESC);
    }

    /**
     * Test that sorted results are different from default (relevance) ordering
     */
    public function testSortedResultsDifferFromDefaultOrder(): void
    {
        // Get default order (no sort)
        $defaultRequest = $this->buildSearchRequest(query: '', page: 1, pageSize: 20);
        $defaultResponse = $this->executeBackendSearch($defaultRequest);
        $defaultIds = $this->getDocumentIds($defaultResponse);

        // Get price sorted order
        $priceSortRequest = $this->buildSearchRequest(
            query: '',
            sort: $this->buildSort('price', SortOrder::SORT_ASC),
            page: 1,
            pageSize: 20
        );
        $priceSortResponse = $this->executeBackendSearch($priceSortRequest);
        $priceSortIds = $this->getDocumentIds($priceSortResponse);

        // Orders should be different (unless products happen to be in price order by default)
        // We check if at least one position is different
        $isDifferent = false;
        for ($i = 0; $i < min(count($defaultIds), count($priceSortIds)); $i++) {
            if ($defaultIds[$i] !== $priceSortIds[$i]) {
                $isDifferent = true;
                break;
            }
        }

        // Note: This test may pass even if orders are the same (unlikely with real data)
        $this->assertTrue(
            $isDifferent || count($defaultIds) <= 1,
            'Sorted order should differ from default order (or have too few products to compare)'
        );
    }

    /**
     * Test sorting with filters applied
     */
    public function testSortingWithFilters(): void
    {
        $priceFilter = $this->buildPriceFilter(30.00, 80.00);
        $sort = $this->buildSort('price', SortOrder::SORT_ASC);

        $request = $this->buildSearchRequest(
            query: '',
            filters: ['price' => $priceFilter],
            sort: $sort,
            page: 1,
            pageSize: 20
        );

        $response = $this->executeBackendSearch($request);
        $documentIds = $this->getDocumentIds($response);

        $this->assertGreaterThan(0, count($documentIds), 'Should return filtered and sorted products');

        $this->assertPricesInOrder($documentIds, SortOrder::SORT_ASC);
    }

    /**
     * Test sorting with search query
     */
    public function testSortingWithSearchQuery(): void
    {
        $sort = $this->buildSort('price', SortOrder::SORT_DESC);

        $request = $this->buildSearchRequest(
            query: 'shirt',
            sort: $sort,
            page: 1,
            pageSize: 20
        );

        $response = $this->executeBackendSearch($request);
        $documentIds = $this->getDocumentIds($response);

        // Assumes shirts exist in catalog
        if (count($documentIds) > 1) {
            $this->assertPricesInOrder($documentIds, SortOrder::SORT_DESC);
        }
    }

    /**
     * Test sorting pagination maintains sort order across pages
     */
    public function testSortingPaginationMaintainsOrder(): void
    {
        $sort = $this->buildSort('price', SortOrder::SORT_ASC);
        $pageSize = 10;

        // Get page 1
        $page1Request = $this->buildSearchRequest(
            query: '',
            sort: $sort,
            page: 1,
            pageSize: $pageSize
        );
        $page1Response = $this->executeBackendSearch($page1Request);
        $page1Ids = $this->getDocumentIds($page1Response);
        $totalCount = $this->searchResponseBuilder->build($page1Response)->getTotalCount();

        // Skip if not enough products for pagination
        if ($totalCount <= $pageSize) {
            $this->markTestSkipped('Not enough products for pagination test');
        }

        // Get page 2
        $page2Request = $this->buildSearchRequest(
            query: '',
            sort: $sort,
            page: 2,
            pageSize: $pageSize
        );
        $page2Response = $this->executeBackendSearch($page2Request);
        $page2Ids = $this->getDocumentIds($page2Response);

        $allIds = array_merge($page1Ids, $page2Ids);

        $this->assertPricesInOrder($allIds, SortOrder::SORT_ASC);
    }

    /**
     * Assert that product prices are in the specified order
     */
    protected function assertPricesInOrder(array $productIds, string $direction): void
    {
        $prices = $this->getProductPrices($productIds);

        if (count($prices) < 2) {
            return; // Not enough data to verify order
        }

        $previousPrice = null;
        foreach ($prices as $id => $price) {
            if ($previousPrice !== null) {
                if ($direction === SortOrder::SORT_ASC) {
                    $this->assertGreaterThanOrEqual(
                        $previousPrice,
                        $price,
                        "Product $id price ($price) should be >= previous price ($previousPrice) for ASC sort"
                    );
                } else {
                    $this->assertLessThanOrEqual(
                        $previousPrice,
                        $price,
                        "Product $id price ($price) should be <= previous price ($previousPrice) for DESC sort"
                    );
                }
            }
            $previousPrice = $price;
        }
    }

    /**
     * Assert that product names are in the specified order
     */
    protected function assertNamesInOrder(array $productIds, string $direction): void
    {
        $names = $this->getProductNames($productIds);

        if (count($names) < 2) {
            return; // Not enough data to verify order
        }

        $previousName = null;
        foreach ($names as $id => $name) {
            if ($previousName !== null) {
                $comparison = strcasecmp($name, $previousName);
                if ($direction === SortOrder::SORT_ASC) {
                    $this->assertGreaterThanOrEqual(
                        0,
                        $comparison,
                        "Product $id name ($name) should be >= previous name ($previousName) for ASC sort"
                    );
                } else {
                    $this->assertLessThanOrEqual(
                        0,
                        $comparison,
                        "Product $id name ($name) should be <= previous name ($previousName) for DESC sort"
                    );
                }
            }
            $previousName = $name;
        }
    }

    /**
     * Get prices for products by ID
     *
     * For complex product types (configurable, grouped, bundle), this retrieves
     * the minimum price from the price index rather than the base price attribute.
     *
     * @return array<string, float>
     */
    protected function getProductPrices(array $productIds): array
    {
        $productCollection = $this->objectManager->create(
            \Magento\Catalog\Model\ResourceModel\Product\Collection::class
        );
        $productCollection->addAttributeToSelect('price');
        $productCollection->addIdFilter($productIds);
        // Add price index data to get minimal_price for configurable/grouped/bundle products
        $productCollection->addMinimalPrice();

        $prices = [];
        foreach ($productCollection as $product) {
            // Use minimal_price from price index for accurate pricing on complex products
            // Falls back to regular price if minimal_price is not available
            $minimalPrice = $product->getMinimalPrice();
            $prices[$product->getId()] = (float) ($minimalPrice ?? $product->getFinalPrice());
        }

        // Maintain the order from productIds
        $orderedPrices = [];
        foreach ($productIds as $id) {
            if (isset($prices[$id])) {
                $orderedPrices[$id] = $prices[$id];
            }
        }

        return $orderedPrices;
    }

    /**
     * Get names for products by ID
     *
     * @return array<string, string>
     */
    protected function getProductNames(array $productIds): array
    {
        $productCollection = $this->objectManager->create(
            \Magento\Catalog\Model\ResourceModel\Product\Collection::class
        );
        $productCollection->addAttributeToSelect('name');
        $productCollection->addIdFilter($productIds);

        $names = [];
        foreach ($productCollection as $product) {
            $names[$product->getId()] = $product->getName();
        }

        // Maintain the order from productIds
        $orderedNames = [];
        foreach ($productIds as $id) {
            if (isset($names[$id])) {
                $orderedNames[$id] = $names[$id];
            }
        }

        return $orderedNames;
    }
}
