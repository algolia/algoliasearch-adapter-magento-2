<?php

namespace Algolia\SearchAdapter\Service\Aggregation;

use Algolia\SearchAdapter\Api\Data\SearchQueryResultInterface;
use Algolia\SearchAdapter\Service\StoreIdResolver;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Search\Request\Aggregation\TermBucket;
use Magento\Framework\Search\Request\BucketInterface;
use Magento\Framework\Search\RequestInterface;

class AggregationBuilder
{
    public function __construct(
        protected StoreIdResolver        $storeIdResolver,
        protected AttributeBucketBuilder $attributeBucketBuilder,
        protected CategoryBucketBuilder  $categoryBucketBuilder,
    ) {}

    /**
     * @throws LocalizedException
     */
    public function build(RequestInterface $request, SearchQueryResultInterface $result): array
    {
        return $this->buildBuckets($request, $result);
    }

    /**
     * Build data to render all buckets from the request
     *
     * @throws LocalizedException
     */
    protected function buildBuckets(RequestInterface $request, SearchQueryResultInterface $result): array
    {
        $facets = $result->getFacets();
        $storeId = $this->storeIdResolver->getStoreId($request);
        $buckets = [];
        foreach ($request->getAggregation() as $bucket) {
            $buckets[$bucket->getName()] = $this->buildBucketData($bucket, $facets, $storeId);
        }
        return $buckets;
    }

    /**
     * @param TermBucket $bucket The attribute bucket to be processed (from the request)
     * @param array<string, array<string, int>> $facets An array of facets -> options -> counts
     *      (e.g. [ 'color' => [ 'Black' => 4 ]] )
     * @param int|null $storeId
     * @return array<string, array<string, mixed>> An array formatted for Magento aggregation render
     * @throws LocalizedException
     * @see \Algolia\SearchAdapter\Service\Aggregation\AbstractBucketBuilder::applyBucketData
     */
    protected function buildBucketData(BucketInterface $bucket, array $facets, ?int $storeId = null): array
    {
        $attributeCode = $bucket->getField();
        // Handle categories
        if ($attributeCode === CategoryBucketBuilder::BUCKET_KEY_CATEGORIES
            && $bucket->getType() === BucketInterface::TYPE_TERM) {
            /** @var TermBucket $bucket */
            return $this->categoryBucketBuilder->build($bucket, $facets, $storeId);
        }

        // Handle pricing - TODO

        // Handle everything else
        return isset($facets[$attributeCode])
            ? $this->attributeBucketBuilder->build($attributeCode, $facets[$attributeCode])
            : []; // we only care about Algolia facets - but the bucket must still be registered
    }
}
