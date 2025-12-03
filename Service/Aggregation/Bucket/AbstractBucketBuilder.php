<?php

namespace Algolia\SearchAdapter\Service\Aggregation\Bucket;

abstract class AbstractBucketBuilder
{
    /** Applies a formatted bucket data structure to an array keyed by entity IDs */
    protected function applyBucketData(array &$data, string $entityId, int $count): void
    {
        $data[$entityId] = [
            'value' => $entityId,
            'count' => (int) $count,
        ];
    }
}
