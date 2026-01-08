<?php

namespace Algolia\SearchAdapter\Test\Unit\Service\Aggregation\Bucket;

use Algolia\AlgoliaSearch\Test\TestCase;
use Algolia\SearchAdapter\Service\Aggregation\Bucket\PriceRangeBucketBuilder;
use Algolia\SearchAdapter\Service\NiceScale;
use Magento\Framework\Search\Request\Aggregation\DynamicBucket;
use PHPUnit\Framework\MockObject\MockObject;

class PriceRangeBucketBuilderTest extends TestCase
{
    private ?PriceRangeBucketBuilder $builder = null;
    private null|(NiceScale&MockObject) $niceScale = null;

    protected function setUp(): void
    {
        $this->niceScale = $this->createMock(NiceScale::class);
        $this->builder = new PriceRangeBucketBuilder($this->niceScale);
    }

    public function testBuildWithEmptyFacets(): void
    {
        $bucket = $this->createMock(DynamicBucket::class);

        $result = $this->builder->build($bucket, []);

        $this->assertEquals([], $result);
    }

    public function testBuildWithNoPriceFacet(): void
    {
        $bucket = $this->createMock(DynamicBucket::class);
        $facets = [
            'color' => ['Black' => 5],
            'categories.level0' => ['Men' => 12],
        ];

        $result = $this->builder->build($bucket, $facets);

        $this->assertEquals([], $result);
    }

    public function testBuildWithPriceFacet(): void
    {
        $bucket = $this->createMock(DynamicBucket::class);
        $facets = [
            'price.USD.default' => [24 => 1, 34 => 2, 39 => 5],
        ];

        $this->niceScale
            ->method('generateBuckets')
            ->with([24, 34, 39], 5)
            ->willReturn([
                ['min' => 0, 'max' => 50],
            ]);

        $result = $this->builder->build($bucket, $facets);

        $this->assertEquals([
            '0_50' => ['from' => 0, 'to' => 50, 'count' => 8, 'value' => '0_50'],
        ], $result);
    }

    public function testBuildWithMultipleBuckets(): void
    {
        $bucket = $this->createMock(DynamicBucket::class);
        $facets = [
            'price.USD.default' => [10 => 3, 25 => 2, 60 => 4, 90 => 1],
        ];

        $this->niceScale
            ->method('generateBuckets')
            ->willReturn([
                ['min' => 0, 'max' => 50],
                ['min' => 50, 'max' => 100],
            ]);

        $result = $this->builder->build($bucket, $facets);

        $this->assertEquals([
            '0_50'   => ['from' => 0, 'to' => 50, 'count' => 5, 'value' => '0_50'],
            '50_100' => ['from' => 50, 'to' => 100, 'count' => 5, 'value' => '50_100'],
        ], $result);
    }

    public function testBuildSkipsEmptyBuckets(): void
    {
        $bucket = $this->createMock(DynamicBucket::class);
        $facets = [
            'price.USD.default' => [10 => 3, 90 => 2],
        ];

        $this->niceScale
            ->method('generateBuckets')
            ->willReturn([
                ['min' => 0,  'max' => 30],
                ['min' => 30, 'max' => 60],  // Empty - no prices between 30-60
                ['min' => 60, 'max' => 90],
            ]);

        $result = $this->builder->build($bucket, $facets);

        $this->assertArrayHasKey('0_30', $result);
        $this->assertArrayNotHasKey('30_60', $result, 'Empty bucket should be skipped');
        $this->assertArrayHasKey('60_90', $result);
        $this->assertEquals(3, $result['0_30']['count']);
        $this->assertEquals(2, $result['60_90']['count']);
    }

    public function testBuildWithDifferentCurrencies(): void
    {
        $bucket = $this->createMock(DynamicBucket::class);
        $facets = [
            'price.EUR.default' => [15 => 2, 45 => 3],
        ];

        $this->niceScale
            ->method('generateBuckets')
            ->willReturn([
                ['min' => 0, 'max' => 50],
            ]);

        $result = $this->builder->build($bucket, $facets);

        $this->assertEquals([
            '0_50' => ['from' => 0, 'to' => 50, 'count' => 5, 'value' => '0_50'],
        ], $result);
    }

    public function testGetMaxNumberOfBuckets(): void
    {
        $this->assertEquals(5, $this->builder->getMaxNumberOfBuckets());
    }

    public function testGetPriceFacet(): void
    {
        $facets = [
            'color'             => ['Black' => 5],
            'price.USD.default' => [24 => 1, 34 => 2],
            'categories.level0' => ['Men' => 12],
        ];

        $result = $this->invokeMethod($this->builder, 'getPriceFacet', [$facets]);

        $this->assertEquals([24 => 1, 34 => 2], $result);
    }

    public function testGetPriceFacetReturnsEmptyWhenNoPriceKey(): void
    {
        $facets = [
            'color'             => ['Black' => 5],
            'categories.level0' => ['Men' => 12],
        ];

        $result = $this->invokeMethod($this->builder, 'getPriceFacet', [$facets]);

        $this->assertEquals([], $result);
    }

    public static function priceRangeDataProvider(): array
    {
        return [
            'full range' => [
                'facet'    => [10 => 1, 20 => 2, 30 => 3],
                'min'      => 0,
                'max'      => 100,
                'expected' => 6,
            ],
            'partial range' => [
                'facet'    => [10 => 1, 20 => 2, 30 => 3, 40 => 4],
                'min'      => 15,
                'max'      => 35,
                'expected' => 5, // 2 + 3 (includes 20 and 30, excludes 10 and 40)
            ],
            'no matches' => [
                'facet'    => [10 => 1, 20 => 2],
                'min'      => 50,
                'max'      => 100,
                'expected' => 0,
            ],
            'inclusive lower boundary' => [
                'facet'    => [10 => 1, 20 => 2, 30 => 3, 40 => 4],
                'min'      => 20,
                'max'      => 40,
                'expected' => 5, // 2 + 3 (includes 20 and 30, excludes 40)
            ],
            'exclusive upper boundary' => [
                'facet'    => [10 => 1, 20 => 2, 30 => 3],
                'min'      => 10,
                'max'      => 30,
                'expected' => 3, // 1 + 2 (includes 10 and 20, excludes 30)
            ],
            'narrow range' => [
                'facet'    => [10 => 1, 25 => 2, 49 => 3],
                'min'      => 20,
                'max'      => 50,
                'expected' => 5, // 2 + 3 (includes 25 and 49, excludes 10)
            ],
            'exact lower boundary match' => [
                'facet'    => [50 => 5],
                'min'      => 50,
                'max'      => 100,
                'expected' => 5,
            ],
        ];
    }

    /**
     * @dataProvider priceRangeDataProvider
     */
    public function testGetCountForPriceRange(array $facet, float $min, float $max, int $expected): void
    {
        $result = $this->invokeMethod($this->builder, 'getCountForPriceRange', [$facet, $min, $max]);
        $this->assertEquals($expected, $result);
    }
}

