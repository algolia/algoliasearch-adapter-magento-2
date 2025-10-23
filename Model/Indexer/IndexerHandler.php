<?php

namespace Algolia\SearchAdapter\Model\Indexer;

use Magento\Framework\Indexer\SaveHandler\IndexerInterface;

class IndexerHandler implements IndexerInterface
{
    public function saveIndex($dimensions, \Traversable $documents)
    {
        // do nothing ...
    }

    public function deleteIndex($dimensions, \Traversable $documents)
    {
        // do nothing ...
    }

    public function cleanIndex($dimensions)
    {
        // do nothing ...
    }

    public function isAvailable($dimensions = [])
    {
        return true;
    }
}
