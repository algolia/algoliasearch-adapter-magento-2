<?php

namespace Algolia\SearchAdapter\Service\Aggregation\Bucket;

use Algolia\SearchAdapter\Service\NiceScale;
use Magento\Framework\Search\Request\Aggregation\DynamicBucket;

class PriceRangeBucketBuilder
{
    /** @var string */
    public const BUCKET_KEY_PRICE = 'price';
    /** @var string */
    public const FACET_KEY_PRICE = 'price.';

    public const MAX_STEPS = 5;

    public function __construct(
        protected NiceScale $niceScale,
    ) {}

    public function build(DynamicBucket $bucket, array $facets): array
    {
        $priceFacet = $this->getPriceFacet($facets);
        if (empty($priceFacet)) {
            return [];
        }

        return $this->getRanges($priceFacet);
    }

    protected function getRanges(array $priceFacet): array
    {
        $prices = array_keys($priceFacet);
        
        $niceBuckets = $this->niceScale->generateBuckets($prices, self::MAX_STEPS);

        $buckets = [];
        $lastKey = array_key_last($buckets);
        foreach ($niceBuckets as $key => $niceBucket) {
            $min = $niceBucket['min'];
            $max = $niceBucket['max'];
            $value = $min . '_' . $max;
            $buckets[$value] = [
                'from' => $min,
                'to' => $max,
                'count' => $this->getCountForPriceRange(
                    $priceFacet,
                    $min,
                    $lastKey != $key ? $max : $max + 1 // final bucket should include upper boundary
                ),
                'value' => $value
            ];
        }
        return $buckets;
    }

    protected function getCountForPriceRange(array $priceFacet, float $min, float $max): int
    {
        // Current bucket $max because next bucket $min
        $keysInRange = array_filter(array_keys($priceFacet), fn($key) =>  $key >= $min && $key < $max);
        $counts = array_intersect_key($priceFacet, array_flip($keysInRange));
        return array_sum($counts);
    }

    /**
     * @param array<string, array<string, int>> $facets An array of facets -> options -> counts
     *  (e.g. [ 'price.USD.default' => [ '24' => 1, '34' => 2, '39' => 5 ]] )
     * @return array<string, int>
     */
    protected function getPriceFacet(array $facets): array
    {
        foreach ($facets as $key => $facet) {
            if (str_starts_with($key, self::FACET_KEY_PRICE)) {
                return $facet;
            }
        }
        return [];
    }
}
