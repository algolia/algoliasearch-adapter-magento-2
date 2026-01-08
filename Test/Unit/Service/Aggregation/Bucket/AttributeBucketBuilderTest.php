<?php

namespace Algolia\SearchAdapter\Test\Unit\Service\Aggregation\Bucket;

use Algolia\AlgoliaSearch\Test\TestCase;
use Algolia\SearchAdapter\Service\Aggregation\Bucket\AttributeBucketBuilder;
use Algolia\SearchAdapter\Service\FacetValueConverter;
use PHPUnit\Framework\MockObject\MockObject;

class AttributeBucketBuilderTest extends TestCase
{
    private ?AttributeBucketBuilder $builder = null;
    private null|(FacetValueConverter&MockObject) $facetValueConverter = null;

    protected function setUp(): void
    {
        $this->facetValueConverter = $this->createMock(FacetValueConverter::class);
        $this->builder = new AttributeBucketBuilder($this->facetValueConverter);
    }

    public function testBuildWithEmptyOptions(): void
    {
        $result = $this->builder->build('color', []);
        $this->assertEquals([], $result);
    }

    public function testBuildWithSingleOption(): void
    {
        $attributeCode = 'color';
        $options = ['Black' => 4];

        $this->facetValueConverter
            ->method('convertLabelToOptionId')
            ->with($attributeCode, 'Black')
            ->willReturn('101');

        $result = $this->builder->build($attributeCode, $options);

        $this->assertEquals([
            '101' => ['value' => '101', 'count' => 4],
        ], $result);
    }

    public function testBuildWithMultipleOptions(): void
    {
        $attributeCode = 'color';
        $options = [
            'Black' => 4,
            'Red'   => 2,
            'Blue'  => 7,
        ];

        $this->facetValueConverter
            ->method('convertLabelToOptionId')
            ->willReturnCallback(fn($code, $label) => match ($label) {
                'Black' => '101',
                'Red'   => '102',
                'Blue'  => '103',
                default => '',
            });

        $result = $this->builder->build($attributeCode, $options);

        $this->assertEquals([
            '101' => ['value' => '101', 'count' => 4],
            '102' => ['value' => '102', 'count' => 2],
            '103' => ['value' => '103', 'count' => 7],
        ], $result);
    }

    public function testBuildWithDifferentAttributeValues(): void
    {
        $attributeCode = 'size';
        $options = ['Small' => 10, 'Large' => 5];

        $this->facetValueConverter
            ->method('convertLabelToOptionId')
            ->willReturnCallback(fn($code, $label) => match ([$code, $label]) {
                ['size', 'Small'] => '201',
                ['size', 'Large'] => '202',
                default           => '',
            });

        $result = $this->builder->build($attributeCode, $options);

        $this->assertEquals([
            '201' => ['value' => '201', 'count' => 10],
            '202' => ['value' => '202', 'count' => 5],
        ], $result);
    }

    public function testBuildWithEmptyOptionIdReturnsEmptyResult(): void
    {
        $attributeCode = 'manufacturer';
        $options = ['Unknown Brand' => 3];

        $this->facetValueConverter
            ->method('convertLabelToOptionId')
            ->with($attributeCode, 'Unknown Brand')
            ->willReturn(''); 

        $result = $this->builder->build($attributeCode, $options);

        $this->assertEquals([], $result);
    }

    public function testBuildWithZeroCount(): void
    {
        $attributeCode = 'color';
        $options = ['White' => 0];

        $this->facetValueConverter
            ->method('convertLabelToOptionId')
            ->with($attributeCode, 'White')
            ->willReturn('104');

        $result = $this->builder->build($attributeCode, $options);

        $this->assertEquals([], $result);
    }
}

