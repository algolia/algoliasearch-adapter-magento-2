<?php

namespace Algolia\SearchAdapter\Test\Integration;

use Algolia\AlgoliaSearch\Exception\DiagnosticsException;
use Algolia\AlgoliaSearch\Exceptions\AlgoliaException;
use Algolia\AlgoliaSearch\Helper\ConfigHelper;
use Algolia\AlgoliaSearch\Helper\Configuration\InstantSearchHelper;
use Algolia\AlgoliaSearch\Test\Integration\IndexCleaner;
use Algolia\AlgoliaSearch\Test\Integration\Indexing\Product\ProductsIndexingTest;
use Algolia\AlgoliaSearch\Test\Integration\Indexing\Product\ProductsIndexingTestCase;
use Algolia\SearchAdapter\Model\Adapter;
use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Search\Request;
use Magento\Framework\Search\Request\Aggregation\DynamicBucket;
use Magento\Framework\Search\Request\Aggregation\TermBucket;
use Magento\Framework\Search\Request\Dimension;
use Magento\Framework\Search\Request\Filter\Term;
use Magento\Framework\Search\Request\Query\BoolExpression as BoolQuery;
use Magento\Framework\Search\Request\Query\Filter as FilterQuery;
use Magento\Framework\Search\Request\Query\MatchQuery;
use Magento\Framework\Search\Request\QueryInterface;
use Magento\Framework\Search\RequestInterface;
use Magento\Framework\Search\Response\QueryResponse;
use Magento\Framework\Search\SearchResponseBuilder;

class BackendSearchTestCase extends ProductsIndexingTestCase
{
    protected const DEFAULT_PAGE_SIZE = 12;
    protected const SEARCH_REQUEST_NAME = 'quick_search_container';
    protected const CATEGORY_REQUEST_NAME = 'catalog_view_container';

    /**
     * Category IDs from sample data
     */
    protected const CATEGORY_WOMEN = 20;
    protected const CATEGORY_WOMEN_TOPS = 21;
    protected const CATEGORY_WOMEN_BOTTOMS = 22;
    protected const CATEGORY_MEN = 11;
    protected const CATEGORY_MEN_TOPS = 12;
    protected const CATEGORY_MEN_BOTTOMS = 13;

    protected static string $testSuiteIndexPrefix;

    protected ?Adapter $adapter = null;
    protected ?InstantSearchHelper $instantSearchHelper = null;
    protected ?SearchResponseBuilder $searchResponseBuilder = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->adapter = $this->objectManager->get(Adapter::class);
        $this->instantSearchHelper = $this->objectManager->get(InstantSearchHelper::class);
        $this->searchResponseBuilder =  $this->objectManager->get(SearchResponseBuilder::class);
    }

    /**
     * @param string $key - a unique key for the operationi
     * @return void
     * @throws AlgoliaException
     * @throws DiagnosticsException
     * @throws NoSuchEntityException
     */
    protected function indexOncePerClass(string $key): void
    {
        $this->setupTestSuiteIndexPrefix();

        $this->runOnce(function() {
            $this->indexAllProducts();
        }, $key);
    }

    /**
     * @throws NoSuchEntityException|AlgoliaException
     */
    public static function tearDownAfterClass(): void
    {
        if (self::$testSuiteIndexPrefix) {
            IndexCleaner::clean(self::$testSuiteIndexPrefix);
        }
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
     * For all searches, test against an "in stock" product use case.
     *
     * @throws AlgoliaException
     * @throws NoSuchEntityException
     * @throws DiagnosticsException
     */
    protected function indexAllProducts(int $storeId = 1): void
    {
        $this->setConfig(ConfigHelper::SHOW_OUT_OF_STOCK, 0);
        $this->updateStockItem(ProductsIndexingTest::OUT_OF_STOCK_PRODUCT_SKU, false);

        $this->productBatchQueueProcessor->processBatch($storeId);
        $this->algoliaConnector->waitLastTask();
    }

    /**
     * Build request for "quick search"
     */
    protected function buildSearchRequest(
        string $query = '',
        array  $filters = [],
        ?array $sort = null,
        int    $page = 1,
        int    $pageSize = self::DEFAULT_PAGE_SIZE,
        array  $aggregations = []
    ): RequestInterface {
        $from = ($page - 1) * $pageSize;

        return new Request(
            name: self::SEARCH_REQUEST_NAME,
            indexName: 'catalogsearch_fulltext',
            query: $this->buildBoolQuery($query, $filters),
            from: $from,
            size: $pageSize,
            dimensions: $this->buildDimensions(),
            buckets: $this->buildAggregations($aggregations),
            sort: $sort
        );
    }

    /**
     * Build request for category page browse
     */
    protected function buildCategoryRequest(
        int $categoryId,
        array $filters = [],
        ?array $sort = null,
        int $page = 1,
        int $pageSize = self::DEFAULT_PAGE_SIZE
    ): RequestInterface {
        // Add category filter to the filters array
        $filters['category'] = $this->buildCategoryFilter($categoryId);

        $from = ($page - 1) * $pageSize;

        return new Request(
            name: self::CATEGORY_REQUEST_NAME,
            indexName: 'catalogsearch_fulltext',
            query: $this->buildBoolQuery('', $filters),
            from: $from,
            size: $pageSize,
            dimensions: $this->buildDimensions(),
            buckets: $this->buildAggregations(),
            sort: $sort
        );
    }

    /**
     * Execute a backend search via the Algolia adapter
     *
     * @throws AlgoliaException
     * @throws NoSuchEntityException
     * @throws LocalizedException
     */
    protected function executeBackendSearch(RequestInterface $request): QueryResponse
    {
        return $this->adapter->query($request);
    }

    protected function buildBoolQuery(string $query, array $filters): BoolQuery
    {
        $should = [];
        $must = [];

        // Add search term if provided
        if ($query !== '') {
            $should['search'] = new MatchQuery(
                name: 'search',
                value: $query,
                boost: 1,
                matches: []
            );
        }

        // Add filters
        foreach ($filters as $name => $filter) {
            $must[$name] = $filter;
        }

        return new BoolQuery(
            name: 'bool',
            boost: 1,
            must: $must,
            should: $should,
            not: []
        );
    }

    /**
     * Build dimensions for the search request
     */
    protected function buildDimensions(int $storeId = 1): array
    {
        return [
            'scope' => new Dimension('scope', $storeId),
        ];
    }

    /**
     * Build default aggregations (buckets) for faceted search
     */
    protected function buildAggregations(array $aggregations = []): array
    {
        $defaultAggregations = [
            'price_bucket' => new DynamicBucket('price_bucket', 'price', 'auto'),
            'category_bucket' => new TermBucket('category_bucket', 'category_ids', []),
            'color_bucket' => new TermBucket('color_bucket', 'color', []),
            'size_bucket' => new TermBucket('size_bucket', 'size', []),
        ];
        return array_merge($defaultAggregations, $aggregations);
    }

    protected function buildCategoryFilter(int $categoryId): FilterQuery
    {
        $termFilter = new Term('category', $categoryId, 'category_ids');
        return new FilterQuery(
            'category',
            1,
            FilterQuery::REFERENCE_FILTER,
            $termFilter // incorrectly typed in Magento core
        );
    }

    protected function assertBucketExists(QueryResponse $response, string $bucketName): void
    {
        $aggregations = $response->getAggregations();
        $this->assertTrue(
            $aggregations->getBucket($bucketName) !== null,
            "Bucket '$bucketName' not found in aggregations"
        );
    }

    protected function assertBucketHasValues(QueryResponse $response, string $bucketName): void
    {
        $bucket = $response->getAggregations()->getBucket($bucketName);
        $this->assertNotNull($bucket, "Bucket '$bucketName' not found");
        $this->assertNotEmpty($bucket->getValues(), "Bucket '$bucketName' has no values");
    }

    /**
     * Get bucket values as an associative array
     *
     * @return array<string, array<string, mixed>>
     */
    protected function getBucketValues(QueryResponse $response, string $bucketName): array
    {
        $bucket = $response->getAggregations()->getBucket($bucketName);
        if (!$bucket) {
            return [];
        }

        $values = [];
        foreach ($bucket->getValues() as $value) {
            $values[$value->getValue()] = $value->getMetrics();
        }
        return $values;
    }

    /**
     * Add an attribute to the instant search facets configuration.
     *
     * @param string $attribute The attribute code to add as a facet
     * @param string $type The facet type: 'conjunctive', 'disjunctive', 'slider', or 'other'
     * @param string $label The display label for the facet
     * @param string $searchable Whether the facet is searchable: '1' = yes, '2' = no
     * @param string $createRule Whether to create a merchandising rule: '1' = yes, '2' = no
     * @param bool $persistToDb Should this config write to database
     */
    protected function addFacet(
        string $attribute,
        string $type = 'disjunctive',
        string $label = '',
        string $searchable = '2',
        string $createRule = '2',
        bool   $persistToDb = false): void
    {
        $serializer = $this->getSerializer();

        $currentFacets = $this->instantSearchHelper->getFacets();

        // Check if attribute already exists
        $existingIndex = null;
        foreach ($currentFacets as $index => $facet) {
            if ($facet['attribute'] === $attribute) {
                $existingIndex = $index;
                break;
            }
        }

        $facetConfig = [
            'attribute' => $attribute,
            'type' => $type,
            'label' => $label ?: ucfirst($attribute),
            'searchable' => $searchable,
            'create_rule' => $createRule,
        ];

        if ($existingIndex !== null) {
            $currentFacets[$existingIndex] = $facetConfig;
        } else {
            $currentFacets[] = $facetConfig;
        }

        if ($persistToDb) {
            /** @var WriterInterface $configWriter */
            $configWriter = $this->objectManager->get(WriterInterface::class);

            $configWriter->save(InstantSearchHelper::FACETS, $serializer->serialize($currentFacets));

            $this->refreshConfigFromDb();
        } else {
            $this->setConfig(InstantSearchHelper::FACETS, $serializer->serialize($currentFacets));
        }
    }
}
