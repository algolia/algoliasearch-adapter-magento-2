<?php

namespace Algolia\SearchAdapter\Service;

class NiceScale
{
    /** Implements a nice-number interval algorithm similar to what is used by charting libraries */
    public function getNiceNumber(float $number, bool $round = false): float
    {
        if ($number <= 0) {
            throw new \InvalidArgumentException('Number must be greater than 0');
        }

        $exponent = floor(log10($number));
        $base10 = pow(10, $exponent);
        $fraction = $number / $base10;

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
                'min' => $bucketMin,
                'max' => $bucketMax,
            ];
        }

        return $buckets;
    }

    /** Calculate "nice" range */
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
