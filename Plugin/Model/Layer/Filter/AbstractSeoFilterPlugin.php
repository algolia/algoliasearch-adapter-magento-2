<?php

namespace Algolia\SearchAdapter\Plugin\Model\Layer\Filter;

use Algolia\SearchAdapter\Helper\ConfigHelper;

abstract class AbstractSeoFilterPlugin
{
    public function __construct(
        protected ConfigHelper $configHelper,
    ) {}

    /**
     * @param string|null $attributeValue
     * @param int $storeId
     * @return bool
     */
    protected function checkFilterConfig(?string $attributeValue, int $storeId): bool
    {
        if (
            !$this->configHelper->areSeoFiltersEnabled($storeId) ||
            $attributeValue === null ||
            trim($attributeValue) === ''
        ) {
            return false;
        }

        return true;
    }
}
