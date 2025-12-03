<?php

namespace Algolia\SearchAdapter\Service\Aggregation\Bucket;

use Algolia\SearchAdapter\Service\FacetValueConverter;
use Magento\Framework\Exception\LocalizedException;

class AttributeBucketBuilder extends AbstractBucketBuilder
{
    public function __construct(
        protected FacetValueConverter $facetValueConverter,
    ) {}

    /**
     * @return array<string, array<string, mixed>> An array formatted for Magento aggregation render
     * @throws LocalizedException
     * @see \Algolia\SearchAdapter\Service\Aggregation\AbstractBucketBuilder::applyBucketData
     */
    public function build(string $attributeCode, array $options): array
    {
        $data = [];

        foreach ($options as $label => $count) {
            $optionId = $this->facetValueConverter->convertLabelToOptionId($attributeCode, $label);
            $this->applyBucketData($data, $optionId, $count);
        }

        return $data;
    }
}
