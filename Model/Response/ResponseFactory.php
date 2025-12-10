<?php

namespace Algolia\SearchAdapter\Model\Response;

use Magento\Framework\Api\Search\Document;
use Magento\Framework\Api\Search\DocumentInterface;
use Magento\Framework\ObjectManagerInterface;
use Magento\Framework\Search\Response\Aggregation;
use Magento\Framework\Search\Response\Aggregation\Value;
use Magento\Framework\Search\Response\Bucket;
use Magento\Framework\Search\Response\QueryResponse;

class ResponseFactory
{
    public function __construct(
        protected ObjectManagerInterface $objectManager
    ) {}

    public function create(array $rawResponse): QueryResponse
    {
        $documents = $this->buildDocuments($rawResponse['documents'] ?? []);
        $aggregations = $this->buildAggregations($rawResponse['aggregations'] ?? []);

        return $this->objectManager->create(
            QueryResponse::class,
            [
                'documents' => $documents,
                'aggregations' => $aggregations,
                'total' => $rawResponse['total'] ?? 0
            ]
        );
    }

    /**
     * Replace deprecated DocumentFactory
     * @param array<array<string,mixed>> $rawDocuments
     * @return Document[]
     * @see \Magento\Elasticsearch\SearchAdapter\DocumentFactory
     */
    private function buildDocuments(array $rawDocuments): array
    {
        return array_reduce($rawDocuments, function(array $documents, array $rawDocument): array {
            $id = $rawDocument['_id'] ?? $rawDocument['fields']['_id'][0] ?? null;
            if ($id !== null) {
                // Document has no injectable dependencies, new is fine here
                // (matches Magento's own DocumentFactory pattern)
                $documents[] = new Document([
                    DocumentInterface::ID => $id,
                    /**
                     * Score not utilized
                     * @see DocumentMapper::buildDocuments
                     */
                ]);
            }
            return $documents;
        }, []);
    }

    /**
     * Replace deprecated AggregationFactory
     * @param array<array<string,array<string,array>>> $rawAggregations
     * @return Aggregation
     * @see \Magento\Elasticsearch\SearchAdapter\AggregationFactory
     */
    private function buildAggregations(array $rawAggregations): Aggregation
    {
        $buckets = [];
        foreach ($rawAggregations as $name => $rawBucket) {
            $buckets[$name] = $this->objectManager->create(
                Bucket::class,
                [
                    'name' => $name,
                    'values' => $this->prepareValues($rawBucket)
                ]
            );
        }
        return $this->objectManager->create(
            Aggregation::class,
            ['buckets' => $buckets]
        );
    }

    private function prepareValues(array $values): array
    {
        $valueObjects = [];
        foreach ($values as $valueName => $metrics) {
            $valueObjects[] = $this->objectManager->create(
                Value::class,
                [
                    'value' => (string) $valueName,
                    'metrics' => $metrics,
                ]
            );
        }
        return $valueObjects;
    }
}
