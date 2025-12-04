<?php

namespace Algolia\SearchAdapter\Test\Unit\Service;

use Algolia\AlgoliaSearch\Test\TestCase;
use Algolia\SearchAdapter\Service\NiceScale;

class NiceScaleTest extends TestCase
{
    private NiceScale $niceScale;

    protected function setUp(): void
    {
        $this->niceScale = new NiceScale();
    }

    public static function niceScaleProvider(): array
    {
        return [
            [
                'values' => [15, 15, 15, 15],
                'expectedNumberOfBuckets' => 0
            ],
            [
                'values' => [12, 18, 20, 95, 100, 130],
                'expectedNumberOfBuckets' => 3
            ],
            [
                'values' => [24, 29, 32, 34, 39, 42, 55, 69, 75],
                'expectedNumberOfBuckets' => 3
            ],
            [
                'values' => [1, 13, 32, 34, 39, 42, 155, 169, 175, 212, 250, 303, 521, 985],
                'expectedNumberOfBuckets' => 5
            ],
        ];
    }

    /**
     * @dataProvider niceScaleProvider
     */
    public function testBuckets($values, $expectedNumberOfBuckets): void
    {
        $range = $this->niceScale->generateBuckets($values, 5);
        $this->assertCount($expectedNumberOfBuckets, $range);
    }

    /**
     * @dataProvider niceScaleProvider
     */
    public function testNiceRange($values, $expectedNumberOfBuckets): void
    {
        $range = $this->niceScale->getNiceRange($values, 5);
        $this->assertEquals($expectedNumberOfBuckets, $range['buckets']);
    }
}
