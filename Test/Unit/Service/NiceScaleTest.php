<?php

namespace Algolia\SearchAdapter\Test\Unit\Service;

use Algolia\AlgoliaSearch\Test\TestCase;
use Algolia\SearchAdapter\Service\NiceScale;

class NiceScaleTest extends TestCase
{
    private ?NiceScale $niceScale = null;

    protected function setUp(): void
    {
        $this->niceScale = new NiceScale();
    }

    // =========================================================================
    // getNiceNumber() Tests - Ceiling Mode (round = false)
    // =========================================================================

    public static function niceNumberCeilingProvider(): array
    {
        return [
            // Exact nice numbers should return themselves
            'exact 1' => [1, 1],
            'exact 2' => [2, 2],
            'exact 5' => [5, 5],
            'exact 10' => [10, 10],
            'exact 100' => [100, 100],
            'exact 1000' => [1000, 1000],

            // Values just above boundaries should ceil to next nice number
            'just above 1' => [1.01, 2],
            'just above 2' => [2.01, 5],
            'just above 5' => [5.01, 10],

            // Values between nice numbers
            'between 1-2' => [1.5, 2],
            'between 2-5 low' => [2.5, 5],
            'between 2-5 mid' => [3.5, 5],
            'between 2-5 high' => [4.9, 5],
            'between 5-10 low' => [6, 10],
            'between 5-10 high' => [9.9, 10],

            // Different magnitudes
            'magnitude 0.1' => [0.15, 0.2],
            'magnitude 0.01' => [0.023, 0.05],
            'magnitude 10' => [15, 20],
            'magnitude 100' => [230, 500],
            'magnitude 1000' => [1200, 2000],

            // Edge cases at boundaries
            'at boundary 1' => [1, 1],
            'at boundary 2' => [2, 2],
            'at boundary 5' => [5, 5],

            // Realistic use cases
            'price range 118' => [118, 200],  // span/maxBuckets scenario
            'price range 23.6' => [23.6, 50], // roughStep scenario
        ];
    }

    /**
     * @dataProvider niceNumberCeilingProvider
     */
    public function testGetNiceNumberCeiling(float $input, float $expected): void
    {
        $result = $this->niceScale->getNiceNumber($input, false);
        $this->assertEquals($expected, $result, "getNiceNumber($input, false) should return $expected");
    }

    // =========================================================================
    // getNiceNumber() Tests - Rounding Mode (round = true)
    // =========================================================================

    public static function niceNumberRoundingProvider(): array
    {
        return [
            // Values that round down to 1 (fraction < 1.5)
            'round to 1 exact' => [1, 1],
            'round to 1 at 1.4' => [1.4, 1],
            'round to 1 at 1.49' => [1.49, 1],

            // Values that round to 2 (1.5 <= fraction < 3)
            'round to 2 at 1.5' => [1.5, 2],
            'round to 2 at 2' => [2, 2],
            'round to 2 at 2.9' => [2.9, 2],

            // Values that round to 5 (3 <= fraction < 7)
            'round to 5 at 3' => [3, 5],
            'round to 5 at 5' => [5, 5],
            'round to 5 at 6.9' => [6.9, 5],

            // Values that round to 10 (fraction >= 7)
            'round to 10 at 7' => [7, 10],
            'round to 10 at 8' => [8, 10],
            'round to 10 at 9.9' => [9.9, 10],

            // Different magnitudes with rounding
            'round 14 to 10' => [14, 10],
            'round 15 to 20' => [15, 20],
            'round 29 to 20' => [29, 20],
            'round 30 to 50' => [30, 50],
            'round 69 to 50' => [69, 50],
            'round 70 to 100' => [70, 100],

            // Small numbers
            'round 0.14 to 0.1' => [0.14, 0.1],
            'round 0.5 to 0.5' => [0.5, 0.5],
            'round 0.69 to 0.5' => [0.69, 0.5],
            'round 0.70 to 1' => [0.70, 1],
        ];
    }

    /**
     * @dataProvider niceNumberRoundingProvider
     */
    public function testGetNiceNumberRounding(float $input, float $expected): void
    {
        $result = $this->niceScale->getNiceNumber($input, true);
        $this->assertEquals($expected, $result, "getNiceNumber($input, true) should return $expected");
    }

    // =========================================================================
    // getNiceNumber() Exception Tests
    // =========================================================================

    public function testGetNiceNumberThrowsOnZero(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->niceScale->getNiceNumber(0);
    }

    public function testGetNiceNumberThrowsOnNegative(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->niceScale->getNiceNumber(-5);
    }

    // =========================================================================
    // generateBuckets() Tests
    // =========================================================================

    public static function bucketProvider(): array
    {
        return [
            'empty values' => [
                'values' => [],
                'maxBuckets' => 5,
                'expectedBuckets' => [],
            ],
            'single value' => [
                'values' => [50],
                'maxBuckets' => 5,
                'expectedBuckets' => [],
            ],
            'all same values' => [
                'values' => [15, 15, 15, 15],
                'maxBuckets' => 5,
                'expectedBuckets' => [],
            ],
            'simple range 0-100' => [
                'values' => [0, 100],
                'maxBuckets' => 5,
                'expectedBuckets' => [
                    ['min' => 0, 'max' => 20],
                    ['min' => 20, 'max' => 40],
                    ['min' => 40, 'max' => 60],
                    ['min' => 60, 'max' => 80],
                    ['min' => 80, 'max' => 100],
                ],
            ],
            'price range 12-130' => [
                // span=118, roughStep=23.6, niceStep=50
                // start=0, end=150
                'values' => [12, 18, 20, 95, 100, 130],
                'maxBuckets' => 5,
                'expectedBuckets' => [
                    ['min' => 0, 'max' => 50],
                    ['min' => 50, 'max' => 100],
                    ['min' => 100, 'max' => 150],
                ],
            ],
            'price range 24-75' => [
                // span=51, roughStep=10.2, niceStep=20
                // start=20, end=80
                'values' => [24, 29, 32, 34, 39, 42, 55, 69, 75],
                'maxBuckets' => 5,
                'expectedBuckets' => [
                    ['min' => 20, 'max' => 40],
                    ['min' => 40, 'max' => 60],
                    ['min' => 60, 'max' => 80],
                ],
            ],
            'large range 1-985' => [
                // span=984, roughStep=196.8, niceStep=200
                // start=0, end=1000
                'values' => [1, 13, 32, 34, 39, 42, 155, 169, 175, 212, 250, 303, 521, 985],
                'maxBuckets' => 5,
                'expectedBuckets' => [
                    ['min' => 0, 'max' => 200],
                    ['min' => 200, 'max' => 400],
                    ['min' => 400, 'max' => 600],
                    ['min' => 600, 'max' => 800],
                    ['min' => 800, 'max' => 1000],
                ],
            ],
            'small numbers' => [
                // span=0.8, roughStep=0.16, niceStep=0.2
                // start=0, end=1
                'values' => [0.1, 0.3, 0.5, 0.9],
                'maxBuckets' => 5,
                'expectedBuckets' => [
                    ['min' => 0.0, 'max' => 0.2],
                    ['min' => 0.2, 'max' => 0.4],
                    ['min' => 0.4, 'max' => 0.6],
                    ['min' => 0.6, 'max' => 0.8],
                    ['min' => 0.8, 'max' => 1.0],
                ],
            ],
            'requesting 10 buckets' => [
                // span=100, roughStep=10, niceStep=10
                'values' => [0, 100],
                'maxBuckets' => 10,
                'expectedBuckets' => [
                    ['min' => 0, 'max' => 10],
                    ['min' => 10, 'max' => 20],
                    ['min' => 20, 'max' => 30],
                    ['min' => 30, 'max' => 40],
                    ['min' => 40, 'max' => 50],
                    ['min' => 50, 'max' => 60],
                    ['min' => 60, 'max' => 70],
                    ['min' => 70, 'max' => 80],
                    ['min' => 80, 'max' => 90],
                    ['min' => 90, 'max' => 100],
                ],
            ],
            'requesting 3 buckets' => [
                // span=100, roughStep=33.3, niceStep=50
                'values' => [0, 100],
                'maxBuckets' => 3,
                'expectedBuckets' => [
                    ['min' => 0, 'max' => 50],
                    ['min' => 50, 'max' => 100],
                ],
            ],
        ];
    }

    /**
     * @dataProvider bucketProvider
     */
    public function testGenerateBuckets(array $values, int $maxBuckets, array $expectedBuckets): void
    {
        $result = $this->niceScale->generateBuckets($values, $maxBuckets);
        $this->assertEquals($expectedBuckets, $result);
    }

    public function testGenerateBucketsAlwaysCoversDataRange(): void
    {
        $values = [17, 23, 45, 67, 89, 123];
        $buckets = $this->niceScale->generateBuckets($values, 5);

        $min = min($values);
        $max = max($values);
        $bucketMin = $buckets[0]['min'];
        $bucketMax = end($buckets)['max'];

        $this->assertLessThanOrEqual($min, $bucketMin, 'Bucket range should start at or before data minimum');
        $this->assertGreaterThanOrEqual($max, $bucketMax, 'Bucket range should end at or after data maximum');
    }

    public function testGenerateBucketsHasConsecutiveRanges(): void
    {
        $values = [10, 50, 90, 150, 200];
        $buckets = $this->niceScale->generateBuckets($values, 5);

        for ($i = 1; $i < count($buckets); $i++) {
            $this->assertEquals(
                $buckets[$i - 1]['max'],
                $buckets[$i]['min'],
                'Buckets should be consecutive with no gaps'
            );
        }
    }

    public function testGenerateBucketsNeverExceedsMaxBuckets(): void
    {
        // Test various scenarios to ensure we never exceed maxBuckets
        $testCases = [
            [[0, 100], 5],
            [[1, 999], 3],
            [[0.01, 0.99], 7],
            [[50, 5000], 10],
        ];

        foreach ($testCases as [$values, $maxBuckets]) {
            $buckets = $this->niceScale->generateBuckets($values, $maxBuckets);
            $this->assertLessThanOrEqual(
                $maxBuckets,
                count($buckets),
                sprintf(
                    'Bucket count should not exceed maxBuckets=%d for range %s',
                    $maxBuckets,
                    json_encode($values)
                )
            );
        }
    }

    // =========================================================================
    // getNiceRange() Tests
    // =========================================================================

    public static function niceRangeProvider(): array
    {
        return [
            'all same values' => [
                'values' => [15, 15, 15, 15],
                'maxSteps' => 5,
                'expectedMin' => 15,
                'expectedMax' => 15,
                'expectedStep' => 0,
                'expectedBuckets' => 0,
            ],
            'range 12-130 with 5 steps' => [
                // span=118, range=200, step=50
                // niceMin=0, niceMax=150, buckets=3
                'values' => [12, 18, 20, 95, 100, 130],
                'maxSteps' => 5,
                'expectedMin' => 0,
                'expectedMax' => 150,
                'expectedStep' => 50,
                'expectedBuckets' => 3,
            ],
            'range 24-75 with 5 steps' => [
                // span=51, range=100, step=20
                // niceMin=20, niceMax=80, buckets=3
                'values' => [24, 29, 32, 34, 39, 42, 55, 69, 75],
                'maxSteps' => 5,
                'expectedMin' => 20,
                'expectedMax' => 80,
                'expectedStep' => 20,
                'expectedBuckets' => 3,
            ],
            'range 1-985 with 5 steps' => [
                // span=984, range=1000, step=200
                // niceMin=0, niceMax=1000, buckets=5
                'values' => [1, 13, 32, 34, 39, 42, 155, 169, 175, 212, 250, 303, 521, 985],
                'maxSteps' => 5,
                'expectedMin' => 0,
                'expectedMax' => 1000,
                'expectedStep' => 200,
                'expectedBuckets' => 5,
            ],
            'range 0-100 with 10 steps' => [
                // span=100, range=100, step=10
                'values' => [0, 100],
                'maxSteps' => 10,
                'expectedMin' => 0,
                'expectedMax' => 100,
                'expectedStep' => 10,
                'expectedBuckets' => 10,
            ],
            'range 0-73 with 10 steps' => [
                // span=73, range=100, step=10
                // niceMin=0, niceMax=80
                'values' => [0, 73],
                'maxSteps' => 10,
                'expectedMin' => 0,
                'expectedMax' => 80,
                'expectedStep' => 10,
                'expectedBuckets' => 8,
            ],
        ];
    }

    /**
     * @dataProvider niceRangeProvider
     */
    public function testGetNiceRange(
        array $values,
        int $maxSteps,
        float $expectedMin,
        float $expectedMax,
        float $expectedStep,
        int $expectedBuckets
    ): void {
        $result = $this->niceScale->getNiceRange($values, $maxSteps);

        $this->assertEquals($expectedMin, $result['min'], 'niceMin mismatch');
        $this->assertEquals($expectedMax, $result['max'], 'niceMax mismatch');
        $this->assertEquals($expectedStep, $result['step'], 'step mismatch');
        $this->assertEquals($expectedBuckets, $result['buckets'], 'buckets mismatch');
    }

    public function testGetNiceRangeAlwaysCoversDataRange(): void
    {
        $testCases = [
            [[17, 89], 5],
            [[0.5, 9.7], 8],
            [[100, 999], 6],
        ];

        foreach ($testCases as [$values, $maxSteps]) {
            $min = min($values);
            $max = max($values);
            $result = $this->niceScale->getNiceRange($values, $maxSteps);

            $this->assertLessThanOrEqual($min, $result['min'], 'niceMin should be <= data min');
            $this->assertGreaterThanOrEqual($max, $result['max'], 'niceMax should be >= data max');
        }
    }
}
