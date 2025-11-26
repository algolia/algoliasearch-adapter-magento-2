<?php

namespace Algolia\SearchAdapter\Service\Aggregation;

use Algolia\SearchAdapter\Service\CategoryPathProvider;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Search\Request\Aggregation\TermBucket;

class CategoryBucketBuilder extends AbstractBucketBuilder
{
    /** @var string */
    public const BUCKET_KEY_CATEGORIES = 'category_ids';
    /** @var string */
    public const FACET_KEY_CATEGORIES = 'categories.level';

    public function __construct(
        protected CategoryPathProvider $categoryPathProvider
    ) {}

    /**
     * @param TermBucket $bucket The category bucket to be processed (from the request)
     * @param array<string, array<string, int>> $facets An array of facets -> options -> counts
     *      (e.g. [ 'color' => [ 'Black' => 4 ]] )
     * @param int|null $storeId
     * @return array<string, array<string, mixed>> An array formatted for Magento aggregation render
     * @throws LocalizedException
     * @see \Algolia\SearchAdapter\Service\Aggregation\AbstractBucketBuilder::applyBucketData
     */
    public function build(TermBucket $bucket, array $facets, ?int $storeId = null): array
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
}
