<?php

namespace Algolia\SearchAdapter\Test\Integration;

use Algolia\AlgoliaSearch\Exception\DiagnosticsException;
use Algolia\AlgoliaSearch\Exceptions\AlgoliaException;
use Algolia\AlgoliaSearch\Helper\ConfigHelper;
use Algolia\AlgoliaSearch\Helper\Configuration\InstantSearchHelper;
use Algolia\AlgoliaSearch\Test\Integration\Frontend\Search\SearchTestCase;
use Algolia\AlgoliaSearch\Test\Integration\IndexCleaner;
use Algolia\AlgoliaSearch\Test\Integration\Indexing\Product\ProductsIndexingTest;
use Algolia\SearchAdapter\Model\Adapter;
use Magento\Framework\Api\Search\DocumentInterface;
use Magento\Framework\Api\SortOrder;
use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Search\Request;
use Magento\Framework\Search\Request\Aggregation\DynamicBucket;
use Magento\Framework\Search\Request\Aggregation\TermBucket;
use Magento\Framework\Search\Request\Dimension;
use Magento\Framework\Search\Request\Filter\Range;
use Magento\Framework\Search\Request\Filter\Term;
use Magento\Framework\Search\Request\Query\BoolExpression as BoolQuery;
use Magento\Framework\Search\Request\Query\Filter as FilterQuery;
use Magento\Framework\Search\Request\Query\MatchQuery;
use Magento\Framework\Search\RequestInterface;
use Magento\Framework\Search\Response\QueryResponse;
use Magento\Framework\Search\SearchResponseBuilder;

class BackendSearchTestCase extends SearchTestCase
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
     * For all searches, test against an "in stock" product use case.
     *
     * Be sure to tear down when done testing
     *
     * @see resetOutOfStockUseCase
     *
     * @throws AlgoliaException
     * @throws NoSuchEntityException
     * @throws DiagnosticsException
     */
    protected function indexAllProducts(int $storeId = 1): void
    {
        // Set one product to out of stock
        $this->setConfig(ConfigHelper::SHOW_OUT_OF_STOCK, 0);
        $this->updateStockItem(ProductsIndexingTest::OUT_OF_STOCK_PRODUCT_SKU, false);

        parent::indexAllProducts($storeId);
    }

    /**
     * Cleans up the catalog update performed while testing the "out of stock" use case.
     *
     * @see indexAllProducts
     *
     * @throws NoSuchEntityException
     */
    protected function resetOutOfStockUseCase(): void
    {
        $this->updateStockItem(ProductsIndexingTest::OUT_OF_STOCK_PRODUCT_SKU, true);
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
        $termFilter = new Term(name: 'category', value: $categoryId, field: 'category_ids');
        return new FilterQuery(
            'category',
            1,
            FilterQuery::REFERENCE_FILTER,
            $termFilter // incorrectly typed in Magento core
        );
    }

    /**
     * Build a price range filter
     */
    protected function buildPriceFilter(float $from, float $to): FilterQuery
    {
        $rangeFilter = new Range('price_filter', 'price', $from, $to);
        return new FilterQuery(
            'price',
            1,
            FilterQuery::REFERENCE_FILTER,
            $rangeFilter // incorrectly typed in Magento core
        );
    }

    /**
     * Build an attribute filter (e.g., color, size)
     */
    protected function buildAttributeFilter(string $attribute, mixed $value): FilterQuery
    {
        $termFilter = new Term(name: $attribute, value: $value, field: $attribute);
        return new FilterQuery(
            $attribute,
            1,
            FilterQuery::REFERENCE_FILTER,
            $termFilter // incorrectly typed in Magento core
        );
    }

    /**
     * Build sort array for request
     */
    protected function buildSort(string $field, string $direction = SortOrder::SORT_ASC): array
    {
        return [
            [
                SortOrder::FIELD => $field,
                SortOrder::DIRECTION => $direction
            ]
        ];
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
     * Get document IDs from the response
     *
     * @return string[]
     */
    protected function getDocumentIds(QueryResponse $response): array
    {
        $ids = [];
        foreach ($response as $document) {
            /** @var DocumentInterface $document */
            $ids[] = $document->getId();
        }
        return $ids;
    }

    /**
     * Configure sorting indices for the adapter
     */
    protected function configureSortingIndices(): void
    {
        $sortingIndices = $this->getSerializer()->serialize([
            ['attribute' => 'price', 'sort' => 'asc', 'label' => 'Price: Low to High'],
            ['attribute' => 'price', 'sort' => 'desc', 'label' => 'Price: High to Low'],
            ['attribute' => 'name', 'sort' => 'asc', 'label' => 'Name: A to Z'],
            ['attribute' => 'name', 'sort' => 'desc', 'label' => 'Name: Z to A'],
        ]);
        $this->setConfig(InstantSearchHelper::SORTING_INDICES, $sortingIndices);
    }

    /**
     * Configure default facets for testing
     */
    protected function configureFacets(): void
    {
        $facets = $this->getSerializer()->serialize([
            ['attribute' => 'price', 'type' => 'slider', 'label' => 'Price'],
            ['attribute' => 'categories', 'type' => 'conjunctive', 'label' => 'Categories'],
            ['attribute' => 'color', 'type' => 'conjunctive', 'label' => 'Color'],
            ['attribute' => 'size', 'type' => 'conjunctive', 'label' => 'Size'],
        ]);
        $this->setConfig(InstantSearchHelper::FACETS, $facets);
    }

    /**
     * Add an attribute to the OOTB instant search facets configuration.
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
