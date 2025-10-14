<?php

namespace Algolia\SearchAdapter\SearchAdapter;

use Magento\Framework\Search\AdapterInterface;
use Magento\Framework\Search\RequestInterface;
use Magento\Framework\Search\Response\QueryResponse;

// Fallback to Elasticsearch classes (also used by OpenSearch) until approach finalized
use Magento\Elasticsearch\SearchAdapter\Aggregation\Builder as AggregationBuilder;
use Magento\Elasticsearch\SearchAdapter\QueryContainerFactory;
use Magento\Elasticsearch\SearchAdapter\ResponseFactory;
use Magento\Elasticsearch\ElasticAdapter\SearchAdapter\Mapper;

class Adapter implements AdapterInterface
{
    public function __construct(
        protected ResponseFactory $responseFactory,
        protected AggregationBuilder $aggregationBuilder,
        protected QueryContainerFactory $queryContainerFactory,
        protected Mapper $mapper,
    ) {}

    /**
     * @inheritDoc
     */
    public function query(RequestInterface $request): QueryResponse
    {
        // Fallback to OpenSearch impl to stub returning a valid QueryResponse
        // TODO: Determine whether adapter should be bypassed straight to search client

        $query = $this->mapper->buildQuery($request);

        // Empty response - TODO: Implement search client
        $rawResponse = [
            "hits" => [
                "hits" => []
            ],
            "aggregations" => [
                "price_bucket" => [],
                "category_bucket" => ["buckets" => []],
            ]
        ];

        $aggregationBuilder = $this->aggregationBuilder;

        $rawDocuments = $rawResponse['hits']['hits'] ?? [];
        $this->aggregationBuilder->setQuery($this->queryContainerFactory->create(['query' => $query]));

        return $this->responseFactory->create(
            [
                'documents' => [$rawDocuments],
                'aggregations' => $aggregationBuilder->build($request, $rawResponse),
                'total' => $rawResponse['hits']['total']['value'] ?? 0
            ]
        );
    }
}
