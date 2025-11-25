<?php

namespace Algolia\SearchAdapter\Service;

use Algolia\SearchAdapter\Api\Data\SearchQueryResultInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Search\RequestInterface;

class AggregationBuilder
{
    public function __construct(
        protected FacetValueConverter $facetValueConverter,
    ) {}

    /**
     * @throws LocalizedException
     */
    public function build(RequestInterface $request, SearchQueryResultInterface $result): array
    {
        return $this->buildBuckets($request, $result);
    }

    /**
     * @throws LocalizedException
     */
    protected function buildRelevantBucks(RequestInterface $request, SearchQueryResultInterface $result): array
    {
        $buckets = [];
        foreach ($request->getAggregation() as $bucket) {
            foreach ($result->getFacets() as $facet => $options) {
                if ($bucket->getField() == $facet) {
                    $buckets[$bucket->getName()] = $this->buildBucketData($facet, $options);
                    break;
                }
            }
        }
        return $buckets;
    }

    /**
     * @throws LocalizedException
     */
    protected function buildBuckets(RequestInterface $request, SearchQueryResultInterface $result): array
    {
        $facets = $result->getFacets();
        $buckets = [];
        foreach ($request->getAggregation() as $bucket) {
            foreach ($result->getFacets() as $facet => $options) {
                if ($bucket->getField() == $facet) {
                    $buckets[$bucket->getName()] = $this->buildBucketData($facet, $options);
                    break;
                }
            }
            $fieldName = $bucket->getField();
            $buckets[$bucket->getName()] = isset($facets[$fieldName])
                ? $this->buildBucketData($fieldName, $facets[$fieldName])
                : []; // we only care about Algolia facets - but the bucket must still be registered
        }
        return $buckets;
    }

    /**
     * @throws LocalizedException
     */
    protected function buildBucketData(string $attributeCode, array $options): array
    {
        $data = [];

        foreach ($options as $label => $count) {
            $optionId = $this->facetValueConverter->covertLabelToOptionId($attributeCode, $label);
            $data[$optionId] = [
                'value' => $optionId,
                'count' => (int) $count,
            ];
        }

        return $data;
    }
}
