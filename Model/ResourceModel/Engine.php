<?php

namespace Algolia\SearchAdapter\Model\ResourceModel;

use Magento\CatalogSearch\Model\ResourceModel\EngineInterface;
use Magento\Eav\Model\Entity\Attribute;

class Engine implements EngineInterface
{

    public function getAllowedVisibility(): array
    {
        // TODO: Implement getAllowedVisibility() method.
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
        // TODO: Implement processAttributeValue() method.
        return $value;
    }

    /**
     * @inheritDoc
     */
    public function prepareEntityIndex($index, $separator = ' '): array
    {
        // TODO: Implement prepareEntityIndex() method.
        return $index;
    }

    /** Not part of interface but called by \Magento\CatalogSearch\Model\ResourceModel\EngineProvider */
    public function isAvailable(): bool
    {
        return false; // preventing indexing for now - but ugly error
    }
}
