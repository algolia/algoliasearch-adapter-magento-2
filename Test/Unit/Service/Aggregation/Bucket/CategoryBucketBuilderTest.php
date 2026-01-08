<?php

namespace Algolia\SearchAdapter\Test\Unit\Service\Aggregation\Bucket;

use Algolia\AlgoliaSearch\Test\TestCase;
use Algolia\SearchAdapter\Service\Aggregation\Bucket\CategoryBucketBuilder;
use Algolia\SearchAdapter\Service\Category\CategoryPathProvider;
use Magento\Framework\Search\Request\Aggregation\TermBucket;
use PHPUnit\Framework\MockObject\MockObject;

class CategoryBucketBuilderTest extends TestCase
{
    private ?CategoryBucketBuilder $builder = null;
    private null|(CategoryPathProvider&MockObject) $categoryPathProvider = null;

    protected function setUp(): void
    {
        $this->categoryPathProvider = $this->createMock(CategoryPathProvider::class);
        $this->builder = new CategoryBucketBuilder($this->categoryPathProvider);
    }

    // =========================================================================
    // build() Tests
    // =========================================================================

    public function testBuildWithEmptyFacets(): void
    {
        $bucket = $this->createMock(TermBucket::class);
        $bucket->method('getParameters')->willReturn(['include' => ['10', '20']]);

        $this->categoryPathProvider
            ->method('getCategoryPaths')
            ->willReturn([
                '10' => 'Men',
                '20' => 'Women',
            ]);

        $result = $this->builder->build($bucket, [], 1);

        $this->assertEquals([], $result);
    }

    public function testBuildWithMatchingCategories(): void
    {
        $storeId = 1;
        $facets = [
            'categories.level0' => ['Men' => 12, 'Women' => 8],
        ];

        $bucket = $this->createMock(TermBucket::class);
        $bucket->method('getParameters')->willReturn(['include' => ['10', '20']]);

        $this->categoryPathProvider
            ->method('getCategoryPaths')
            ->with(['10', '20'], $storeId)
            ->willReturn([
                '10' => 'Men',
                '20' => 'Women',
            ]);

        $result = $this->builder->build($bucket, $facets, $storeId);

        $this->assertEquals([
            '10' => ['value' => '10', 'count' => 12],
            '20' => ['value' => '20', 'count' => 8],
        ], $result);
    }

    public function testBuildWithNestedCategories(): void
    {
        $storeId = 1;
        $facets = [
            'categories.level0' => ['Men' => 20],
            'categories.level1' => ['Men /// Tops' => 12],
            'categories.level2' => ['Men /// Tops /// Shirts' => 5],
        ];

        $bucket = $this->createMock(TermBucket::class);
        $bucket->method('getParameters')->willReturn(['include' => ['10', '20', '30']]);

        $this->categoryPathProvider
            ->method('getCategoryPaths')
            ->with(['10', '20', '30'], $storeId)
            ->willReturn([
                '10' => 'Men',
                '20' => 'Men /// Tops',
                '30' => 'Men /// Tops /// Shirts',
            ]);

        $result = $this->builder->build($bucket, $facets, $storeId);

        $this->assertEquals([
            '10' => ['value' => '10', 'count' => 20],
            '20' => ['value' => '20', 'count' => 12],
            '30' => ['value' => '30', 'count' => 5],
        ], $result);
    }

    public function testBuildWithPartialMatches(): void
    {
        $storeId = 1;
        $facets = [
            'categories.level0' => ['Men' => 12],
        ];

        $bucket = $this->createMock(TermBucket::class);
        $bucket->method('getParameters')->willReturn(['include' => ['10', '20']]);

        $this->categoryPathProvider
            ->method('getCategoryPaths')
            ->willReturn([
                '10' => 'Men',
                '20' => 'Women', // No matching facet
            ]);

        $result = $this->builder->build($bucket, $facets, $storeId);

        $this->assertEquals([
            '10' => ['value' => '10', 'count' => 12],
        ], $result);
    }

    public function testBuildWithNoCategoryParameters(): void
    {
        $facets = [
            'categories.level0' => ['Men' => 5],
        ];
        $bucket = $this->createMock(TermBucket::class);
        $bucket->method('getParameters')->willReturn([]);

        $this->categoryPathProvider
            ->method('getCategoryPaths')
            ->with([], null)
            ->willReturn([]);

        $result = $this->builder->build($bucket, $facets, null);

        $this->assertEquals([], $result);
    }

    public function testBuildWithNullStoreId(): void
    {
        $storeId = null;
        $facets = [
            'categories.level0' => ['Electronics' => 15],
        ];

        $bucket = $this->createMock(TermBucket::class);
        $bucket->method('getParameters')->willReturn(['include' => ['100']]);

        $this->categoryPathProvider
            ->method('getCategoryPaths')
            ->with(['100'], null)
            ->willReturn([
                '100' => 'Electronics',
            ]);

        $result = $this->builder->build($bucket, $facets, $storeId);

        $this->assertEquals([
            '100' => ['value' => '100', 'count' => 15],
        ], $result);
    }

    public function testBuildFiltersIgnoresNonCategoryFacets(): void
    {
        $storeId = 1;
        $facets = [
            'categories.level0' => ['Men' => 12],
            'color'             => ['Black' => 5], // Ignored
            'price.USD.default' => ['24' => 1],    // Ignored
        ];

        $bucket = $this->createMock(TermBucket::class);
        $bucket->method('getParameters')->willReturn(['include' => ['10']]);

        $this->categoryPathProvider
            ->method('getCategoryPaths')
            ->willReturn([
                '10' => 'Men',
            ]);

        $result = $this->builder->build($bucket, $facets, $storeId);

        $this->assertEquals([
            '10' => ['value' => '10', 'count' => 12],
        ], $result);
    }

    // =========================================================================
    // getCategoryCountMapFromFacets() Tests
    // =========================================================================

    public function testGetCategoryCountMapFromFacets(): void
    {
        $facets = [
            'categories.level0' => ['Men' => 20, 'Women' => 15],
            'categories.level1' => ['Men /// Tops' => 10, 'Women /// Dresses' => 8],
            'categories.level2' => ['Men /// Tops /// Shirts' => 5],
            'color'             => ['Black' => 5],
        ];

        $result = $this->invokeMethod($this->builder, 'getCategoryCountMapFromFacets', [$facets]);

        $expected = [
            'Men'                     => 20,
            'Women'                   => 15,
            'Men /// Tops'            => 10,
            'Women /// Dresses'       => 8,
            'Men /// Tops /// Shirts' => 5,
        ];

        $this->assertEquals($expected, $result);
    }
}

