<?php

namespace Algolia\SearchAdapter\Service;

use Algolia\SearchAdapter\Api\Data\SearchQueryResultInterface;
use Magento\Catalog\Model\ProductFactory;
use Magento\Catalog\Model\ResourceModel\Product;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Search\Adapter\Aggregation\AggregationResolverInterface;
use Magento\Framework\Search\RequestInterface;

class AggregationBuilder
{
    public function __construct(
        protected AggregationResolverInterface $aggregationResolver,
        protected Product                      $productResource,
    ) {}

    /**
     * @throws LocalizedException
     */
    public function build(RequestInterface $request, SearchQueryResultInterface $result): array
    {
        return $this->transformFacetsToBuckets($request, $result);
    }

    /**
     * @throws LocalizedException
     */
    public function transformFacetsToBuckets(RequestInterface $request, SearchQueryResultInterface $result): array
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
    protected function buildBucketData(string $attributeCode, array $options): array
    {
        $data = [];

        foreach ($options as $label => $count) {
            $optionId = $this->getOptionIdByLabel($attributeCode, $label);
            $data[$optionId] = [
                'value' => $optionId,
                'count' => (int) $count,
            ];
        }

        return $data;
    }

    /**
     * @throws LocalizedException
     */
    protected function getOptionIdByLabel(string $attributeCode, string $label): string
    {
        $attribute = $this->productResource->getAttribute($attributeCode);

        if (!$attribute || !$attribute->usesSource()) {
            return '';
        }

        return $attribute->getSource()->getOptionId($label) ?? '';
    }

}
