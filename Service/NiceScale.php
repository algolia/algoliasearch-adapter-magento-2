<?php

namespace Algolia\SearchAdapter\Service;

/**
 * A service to generically provide "nice number" scales
 * @see \Magento\Framework\Search\Dynamic\Algorithm\Auto::getRange
 */
class NiceScale
{
    /**
     * Necessary for correcting binary math errors
     * 1. Stay below IEEE-754 ~15-16 significant digit limit
     * 2. Stay high enough to not lose meaningful precision in calculations
     * e.g.`6.999999999999999`-> `7.0`
     */
    protected const DECIMAL_PRECISION_SCALE = 8;

    /** Implements a nice-number interval algorithm similar to what is used by charting libraries */
    public function getNiceNumber(float $number, bool $round = false): float
    {
        if ($number <= 0) {
            throw new \InvalidArgumentException('Number must be greater than 0');
        }

        $exponent = floor(log10($number));
        $base10 = pow(10, $exponent);
        $fraction = round($number / $base10, self::DECIMAL_PRECISION_SCALE);

        if ($round) {
            if ($fraction < 1.5) {
                $nice = 1;
            } elseif ($fraction < 3) {
                $nice = 2;
            } elseif ($fraction < 7) {
                $nice = 5;
            } else {
                $nice = 10;
            }
        } else {
            if ($fraction <= 1) {
                $nice = 1;
            } elseif ($fraction <= 2) {
                $nice = 2;
            } elseif ($fraction <= 5) {
                $nice = 5;
            } else {
                $nice = 10;
            }
        }

        return $nice * $base10;
    }

    /**
     * Generate range buckets based on "nice numbers" (rounded to ints / no decimals) up to a maximum number of buckets
     *
     * @param float[] $values
     * @param int $maxBuckets
     * @return array<array<string, int>>
     */
    public function generateBuckets(array $values, int $maxBuckets): array
    {
        if (empty($values)) {
            return [];
        }

        $min = min($values);
        $max = max($values);
        $span = $max - $min;

        if ($span == 0) {
            return [];
        }

        $roughStep = $span / $maxBuckets;
        $step = $this->getNiceNumber($roughStep);

        $start = floor($min / $step) * $step;
        $end = ceil($max / $step) * $step;

        $buckets = [];
        for ($current = $start; $current < $end; $current += $step) {
            $bucketMin = $current;
            $bucketMax = $current + $step;
            $buckets[] = [
                'min' => round($bucketMin, self::DECIMAL_PRECISION_SCALE),
                'max' => round($bucketMax, self::DECIMAL_PRECISION_SCALE),
            ];
        }

        return $buckets;
    }

    /**
     * Calculate "nice" range
     * Experimental approach - will likely be removed
     */
    public function getNiceRange(array $values, int $maxSteps): array
    {
        $min = min($values);
        $max = max($values);
        $span = $max - $min;

        if ($span == 0) {
            return [
                'min' => $min,
                'max' => $max,
                'step' => 0,
                'buckets' => 0,
            ];
        }

        $range = $this->getNiceNumber($span, false);
        $step = $this->getNiceNumber($range / ($maxSteps - 1), true);
        $niceMin = floor($min / $step) * $step;
        $niceMax = ceil($max / $step) * $step;
        return [
            'min' => $niceMin,
            'max' => $niceMax,
            'step' => $step,
            'buckets' => ceil($span / $step),
        ];
    }
}
