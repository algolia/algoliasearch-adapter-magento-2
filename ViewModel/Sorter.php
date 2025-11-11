<?php

namespace Algolia\SearchAdapter\ViewModel;

use Algolia\AlgoliaSearch\Helper\Configuration\InstantSearchHelper;
use Magento\Framework\View\Element\Block\ArgumentInterface;

class Sorter implements ArgumentInterface
{
    public const SORT_PARAM_DELIMITER = '~';
    public const SORT_PARAM_DEFAULT = 'relevance';
    public function __construct(
        protected InstantSearchHelper $instantSearchHelper,
    ) {}

    public function getSortingOptions(): array
    {
        return [
            ['key' => self::SORT_PARAM_DEFAULT, 'label' => __('Relevance') ],
            ...$this->transformSortingOptions($this->instantSearchHelper->getSorting())
        ];
    }

    /**
     * Algolia maps replicas to a field name / sort direction combination
     * Independent sort direction handling is not supported
     * This widget sends a composite param value to the backend search (field~direction)
     * which will be parsed later by the SearchCriteriaResolver
     */
    protected function transformSortingOptions(array $sortingOptions): array
    {
        return array_values(array_map(
            fn($sort) => [
                'key' => $sort['attribute'] . self::SORT_PARAM_DELIMITER . $sort['sort'],
                'label' => $sort['sortLabel']
            ],
            $sortingOptions
        ));
    }
}
