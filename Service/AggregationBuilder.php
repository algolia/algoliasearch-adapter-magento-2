<?php

namespace Algolia\SearchAdapter\Service;

use Algolia\SearchAdapter\Api\Data\SearchQueryResultInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Search\Request\Aggregation\TermBucket;
use Magento\Framework\Search\Request\BucketInterface;
use Magento\Framework\Search\RequestInterface;

class AggregationBuilder
{
    /** @var string */
    public const BUCKET_KEY_CATEGORIES = 'category_ids';
    public const FACET_KEY_CATEGORIES = 'categories.level';

    public function __construct(
        protected FacetValueConverter  $facetValueConverter,
        protected CategoryPathProvider $categoryPathProvider,
        protected StoreIdResolver      $storeIdResolver,
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
     * @see applyBucketData
     * @throws LocalizedException
     */
    protected function buildBucketData(BucketInterface $bucket, array $facets, ?int $storeId = null): array
    {
        $attributeCode = $bucket->getField();
        // Handle categories
        if ($attributeCode === self::BUCKET_KEY_CATEGORIES && $bucket->getType() == BucketInterface::TYPE_TERM) {
            /** @var TermBucket $bucket */
            return $this->buildBucketDataForCategories($bucket, $facets, $storeId);
        }

        // Handle everything else
        return isset($facets[$attributeCode])
            ? $this->buildBucketDataForOptions($attributeCode, $facets[$attributeCode])
            : []; // we only care about Algolia facets - but the bucket must still be registered
    }

    /**
     * @param TermBucket $bucket The category bucket to be processed (from the request)
     * @param array<string, array<string, int>> $facets An array of facets -> options -> counts
     *      (e.g. [ 'color' => [ 'Black' => 4 ]] )
     * @param int|null $storeId
     * @return array<string, array<string, mixed>> An array formatted for Magento aggregation render
     * @see applyBucketData
     */
    protected function buildBucketDataForCategories(TermBucket $bucket, array $facets, ?int $storeId = null): array
    {
        $params = $bucket->getParameters();
        $categoryIds = $params['include'] ?? [];
        $paths = $this->categoryPathProvider->getCategoryPaths($categoryIds, $storeId);
        $countMap = $this->getCategoryCountMapFromFacets($facets);
        $data = [];
        foreach ($paths as $id => $path) {
            $this->applyBucketData($data, $id, $countMap[$path]);
        }
        return $data;
    }

    /**
     * Flatten the facets from the search response into a map of full paths to counts
     * @param array<string, array<string, int>> $facets An array of facets -> options -> counts
     *      (e.g. [ 'color' => [ 'Black' => 4 ]] )
     * @return array<string, int> A map of paths to hit counts
     */
    protected function getCategoryCountMapFromFacets(array $facets): array
    {
        $categories = array_filter(
            $facets,
            fn($key) => str_starts_with($key, self::FACET_KEY_CATEGORIES),
            ARRAY_FILTER_USE_KEY
        );
        return array_merge(...array_values($categories)); //flatten
    }

    /**
     * @throws LocalizedException
     */
    protected function buildBucketDataForOptions(string $attributeCode, array $options): array
    {
        $data = [];

        foreach ($options as $label => $count) {
            $optionId = $this->facetValueConverter->covertLabelToOptionId($attributeCode, $label);
            $this->applyBucketData($data, $optionId, $count);
        }

        return $data;
    }

    /** Applies a formatted bucket data structure to an array keyed by entity IDs */
    protected function applyBucketData(array &$data, string $entityId, int $count): void
    {
        $data[$entityId] = [
            'value' => $entityId,
            'count' => (int) $count,
        ];
    }
}
