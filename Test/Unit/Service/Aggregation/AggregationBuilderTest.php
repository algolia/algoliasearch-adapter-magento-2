<?php

namespace Algolia\SearchAdapter\Test\Unit\Service\Aggregation;

use Algolia\AlgoliaSearch\Test\TestCase;
use Algolia\SearchAdapter\Api\Data\SearchQueryResultInterface;
use Algolia\SearchAdapter\Service\Aggregation\AggregationBuilder;
use Algolia\SearchAdapter\Service\Aggregation\Bucket\AttributeBucketBuilder;
use Algolia\SearchAdapter\Service\Aggregation\Bucket\CategoryBucketBuilder;
use Algolia\SearchAdapter\Service\Aggregation\Bucket\PriceRangeBucketBuilder;
use Algolia\SearchAdapter\Service\StoreIdResolver;
use Magento\Framework\Search\Request\Aggregation\DynamicBucket;
use Magento\Framework\Search\Request\Aggregation\TermBucket;
use Magento\Framework\Search\Request\BucketInterface;
use Magento\Framework\Search\RequestInterface;
use PHPUnit\Framework\MockObject\MockObject;

class AggregationBuilderTest extends TestCase
{
    private ?AggregationBuilder $aggregationBuilder = null;
    private null|(StoreIdResolver&MockObject) $storeIdResolver = null;
    private null|(AttributeBucketBuilder&MockObject) $attributeBucketBuilder = null;
    private null|(CategoryBucketBuilder&MockObject) $categoryBucketBuilder = null;
    private null|(PriceRangeBucketBuilder&MockObject) $priceRangeBucketBuilder = null;

    protected function setUp(): void
    {
        $this->storeIdResolver = $this->createMock(StoreIdResolver::class);
        $this->attributeBucketBuilder = $this->createMock(AttributeBucketBuilder::class);
        $this->categoryBucketBuilder = $this->createMock(CategoryBucketBuilder::class);
        $this->priceRangeBucketBuilder = $this->createMock(PriceRangeBucketBuilder::class);

        $this->aggregationBuilder = new AggregationBuilder(
            $this->storeIdResolver,
            $this->attributeBucketBuilder,
            $this->categoryBucketBuilder,
            $this->priceRangeBucketBuilder
        );
    }

    public function testBuildWithEmptyAggregation(): void
    {
        $request = $this->createMock(RequestInterface::class);
        $request->method('getAggregation')->willReturn([]);

        $result = $this->createMock(SearchQueryResultInterface::class);
        $result->method('getFacets')->willReturn([]);

        $this->storeIdResolver->method('getStoreId')->willReturn(1);

        $buckets = $this->aggregationBuilder->build($request, $result);

        $this->assertEquals([], $buckets);
    }

    public function testBuildWithCategoryBucket(): void
    {
        $storeId = 1;
        $facets = [
            'categories.level0' => ['Men' => 12, 'Women' => 8],
            'categories.level1' => ['Men /// Tops' => 5],
        ];
        $expectedCategoryData = [
            '10' => ['value' => '10', 'count' => 12],
            '20' => ['value' => '20', 'count' => 8],
        ];

        $categoryBucket = $this->createMockBucket(TermBucket::class, BucketInterface::TYPE_TERM, 'category_ids', 'category_bucket');

        $request = $this->createMock(RequestInterface::class);
        $request->method('getAggregation')->willReturn([$categoryBucket]);

        $result = $this->createMock(SearchQueryResultInterface::class);
        $result->method('getFacets')->willReturn($facets);

        $this->storeIdResolver->method('getStoreId')->willReturn($storeId);

        $this->categoryBucketBuilder
            ->expects($this->once())
            ->method('build')
            ->with($categoryBucket, $facets, $storeId)
            ->willReturn($expectedCategoryData);

        $buckets = $this->aggregationBuilder->build($request, $result);

        $this->assertEquals(['category_bucket' => $expectedCategoryData], $buckets);
    }

    public function testBuildWithPriceBucket(): void
    {
        $storeId = 1;
        $facets = [
            'price.USD.default' => ['24' => 1, '34' => 2, '39' => 5],
        ];
        $expectedPriceData = [
            '0_50' => ['from' => 0, 'to' => 50, 'count' => 8, 'value' => '0_50'],
        ];

        $priceBucket = $this->createMockBucket(DynamicBucket::class, BucketInterface::TYPE_DYNAMIC, 'price');

        $request = $this->createMock(RequestInterface::class);
        $request->method('getAggregation')->willReturn([$priceBucket]);

        $result = $this->createMock(SearchQueryResultInterface::class);
        $result->method('getFacets')->willReturn($facets);

        $this->storeIdResolver->method('getStoreId')->willReturn($storeId);

        $this->priceRangeBucketBuilder
            ->expects($this->once())
            ->method('build')
            ->with($priceBucket, $facets)
            ->willReturn($expectedPriceData);

        $buckets = $this->aggregationBuilder->build($request, $result);

        $this->assertEquals(['price_bucket' => $expectedPriceData], $buckets);
    }

    public function testBuildWithAttributeBucket(): void
    {
        $storeId = 1;
        $facets = [
            'color' => ['Black' => 4, 'Red' => 2],
        ];
        $expectedAttributeData = [
            '101' => ['value' => '101', 'count' => 4],
            '102' => ['value' => '102', 'count' => 2],
        ];

        $attributeBucket = $this->createMockBucket(TermBucket::class, BucketInterface::TYPE_TERM, 'color');

        $request = $this->createMock(RequestInterface::class);
        $request->method('getAggregation')->willReturn([$attributeBucket]);

        $result = $this->createMock(SearchQueryResultInterface::class);
        $result->method('getFacets')->willReturn($facets);

        $this->storeIdResolver->method('getStoreId')->willReturn($storeId);

        $this->attributeBucketBuilder
            ->expects($this->once())
            ->method('build')
            ->with('color', $facets['color'])
            ->willReturn($expectedAttributeData);

        $buckets = $this->aggregationBuilder->build($request, $result);

        $this->assertEquals(['color_bucket' => $expectedAttributeData], $buckets);
    }

    public function testBuildWithMissingFacetReturnsEmptyArray(): void
    {
        $storeId = 1;
        $facets = []; // No facets returned from Algolia

        $attributeBucket = $this->createMockBucket(TermBucket::class, BucketInterface::TYPE_TERM, 'size');

        $request = $this->createMock(RequestInterface::class);
        $request->method('getAggregation')->willReturn([$attributeBucket]);

        $result = $this->createMock(SearchQueryResultInterface::class);
        $result->method('getFacets')->willReturn($facets);

        $this->storeIdResolver->method('getStoreId')->willReturn($storeId);

        $this->attributeBucketBuilder->expects($this->never())->method('build');

        $buckets = $this->aggregationBuilder->build($request, $result);

        $this->assertEquals(['size_bucket' => []], $buckets);
    }

    public function testBuildWithMultipleBuckets(): void
    {
        $storeId = 1;
        $facets = [
            'color'             => ['Black' => 4],
            'categories.level0' => ['Men' => 12],
            'price.USD.default' => ['24' => 1],
        ];

        $colorBucket = $this->createMockBucket(TermBucket::class, BucketInterface::TYPE_TERM, 'color');

        $categoryBucket = $this->createMockBucket(TermBucket::class, BucketInterface::TYPE_TERM, 'category_ids', 'category_bucket');

        $priceBucket = $this->createMockBucket(DynamicBucket::class, BucketInterface::TYPE_DYNAMIC, 'price');

        $request = $this->createMock(RequestInterface::class);
        $request->method('getAggregation')->willReturn([$colorBucket, $categoryBucket, $priceBucket]);

        $result = $this->createMock(SearchQueryResultInterface::class);
        $result->method('getFacets')->willReturn($facets);

        $this->storeIdResolver->method('getStoreId')->willReturn($storeId);

        $this->attributeBucketBuilder
            ->method('build')
            ->willReturn(['101' => ['value' => '101', 'count' => 4]]);

        $this->categoryBucketBuilder
            ->method('build')
            ->willReturn(['10' => ['value' => '10', 'count' => 12]]);

        $this->priceRangeBucketBuilder
            ->method('build')
            ->willReturn(['0_50' => ['from' => 0, 'to' => 50, 'count' => 1, 'value' => '0_50']]);

        $buckets = $this->aggregationBuilder->build($request, $result);

        $this->assertArrayHasKey('color_bucket', $buckets);
        $this->assertArrayHasKey('category_bucket', $buckets);
        $this->assertArrayHasKey('price_bucket', $buckets);
    }

    protected function createMockBucket(string $bucketClass, string $type, string $field, ?string $name = null): BucketInterface&MockObject
    {
        $name ??= "{$field}_bucket";
        $bucket = $this->createMock($bucketClass);
        if (!$bucket instanceof BucketInterface) {
            throw new \InvalidArgumentException("Bucket must implement BucketInterface");
        }
        /** @var $bucket BucketInterface&MockObject */
        $bucket->method('getName')->willReturn($name);
        $bucket->method('getField')->willReturn($field);
        $bucket->method('getType')->willReturn($type);
        return $bucket;
    }
}

