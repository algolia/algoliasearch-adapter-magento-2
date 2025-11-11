<?php

namespace Algolia\SearchAdapter\Plugin\Search;

use Algolia\SearchAdapter\Helper\ConfigHelper;
use Magento\Framework\Search\Request\Builder;

class RequestBuilderPlugin
{
    public function __construct(
        protected ConfigHelper $configHelper,
    ) {}

    /**
     * Due to deprecation warnings in core regarding sorting with the AdapterInterface
     * a fallback is supplied as an attempt to "future proof" the implementation
     *
     * @param \Magento\Framework\Api\SortOrder[] $sort
     */
    public function afterSetSort(Builder $subject, Builder $result, array $sort): Builder
    {
        if (!$this->configHelper->isAlgoliaEngineSelected()) {
            return $result;
        }

        if (!empty($sort)) {
            $result->bindDimension('algolia_sort', reset($sort));
        }

        return $result;
    }
}
