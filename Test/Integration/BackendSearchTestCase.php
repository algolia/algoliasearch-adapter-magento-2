<?php

namespace Algolia\SearchAdapter\Test\Integration;

use Algolia\AlgoliaSearch\Exception\DiagnosticsException;
use Algolia\AlgoliaSearch\Exceptions\AlgoliaException;
use Algolia\AlgoliaSearch\Helper\ConfigHelper;
use Algolia\AlgoliaSearch\Test\Integration\Indexing\Product\ProductsIndexingTest;
use Algolia\AlgoliaSearch\Test\Integration\Indexing\Product\ProductsIndexingTestCase;
use Algolia\SearchAdapter\Model\Adapter;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Search\Request\Aggregation\DynamicBucket;
use Magento\Framework\Search\Request\Aggregation\TermBucket;
use Magento\Framework\Search\Request\Dimension;
use Magento\Framework\Search\Request\Query\BoolExpression as BoolQuery;
use Magento\Framework\Search\Request\QueryInterface;
use Magento\Framework\Search\RequestInterface;
use Magento\Framework\Search\Response\QueryResponse;

class BackendSearchTestCase extends ProductsIndexingTestCase
{
    protected const DEFAULT_PAGE_SIZE = 12;
    protected const SEARCH_REQUEST_NAME = 'quick_search_container';
    protected const CATEGORY_REQUEST_NAME = 'catalog_view_container';

    protected ?Adapter $adapter = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->adapter = $this->objectManager->get(Adapter::class);
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
     * Build a search request for quick search
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

        return new SearchRequest(
            name: self::SEARCH_REQUEST_NAME,
            indexName: 'catalogsearch_fulltext',
            query: $this->buildBoolQuery($query, $filters),
            from: $from,
            size: $pageSize,
            dimensions: $this->buildDimensions(),
            aggregations: $this->buildAggregations($aggregations),
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
            $should['search'] = new SearchMatchQuery(
                name: 'search',
                value: $query,
                field: 'search_query'
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

}

/**
 * Simple implementation of RequestInterface for testing
 */
class SearchRequest implements RequestInterface
{
    public function __construct(
        private string $name,
        private string $indexName,
        private QueryInterface $query,
        private int $from,
        private int $size,
        private array $dimensions,
        private array $aggregations,
        private ?array $sort = null
    ) {}

    public function getName(): string
    {
        return $this->name;
    }

    public function getIndex(): string
    {
        return $this->indexName;
    }

    public function getDimensions(): array
    {
        return $this->dimensions;
    }

    public function getAggregation(): array
    {
        return $this->aggregations;
    }

    public function getQuery(): QueryInterface
    {
        return $this->query;
    }

    public function getFrom(): ?int
    {
        return $this->from;
    }

    public function getSize(): ?int
    {
        return $this->size;
    }

    public function getSort(): array
    {
        return $this->sort ?? [];
    }
}


/**
 * Simple implementation of MatchQuery for testing
 */
class SearchMatchQuery implements QueryInterface
{
    public function __construct(
        private string $name,
        private string $value,
        private string $field
    ) {}

    public function getType(): string
    {
        return QueryInterface::TYPE_MATCH;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getBoost(): ?float
    {
        return 1;
    }

    public function getValue(): string
    {
        return $this->value;
    }

    public function getField(): string
    {
        return $this->field;
    }

    public function getMatches(): string
    {
        return $this->field;
    }
}
