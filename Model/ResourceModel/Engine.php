<?php

namespace Algolia\SearchAdapter\Model\ResourceModel;

use Magento\CatalogSearch\Model\ResourceModel\EngineInterface;

class Engine implements EngineInterface
{

    public function getAllowedVisibility(): array
    {
        return [];
    }

    /**
     * @inheritDoc
     */
    public function allowAdvancedIndex(): bool
    {
        return false;
    }

    /**
     * @inheritDoc
     */
    public function processAttributeValue($attribute, $value): mixed
    {
        return $value;
    }

    /**
     * @inheritDoc
     */
    public function prepareEntityIndex($index, $separator = ' '): array
    {
        return $index;
    }

    /** Not part of interface but called by \Magento\CatalogSearch\Model\ResourceModel\EngineProvider */
    public function isAvailable(): bool
    {
        return true;
    }
}
