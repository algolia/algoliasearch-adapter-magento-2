<?php

namespace Algolia\SearchAdapter\ViewModel;

use Algolia\AlgoliaSearch\Helper\Configuration\InstantSearchHelper;
use Magento\Framework\View\Element\Block\ArgumentInterface;

class Sorter implements ArgumentInterface
{
    public function __construct(
        protected InstantSearchHelper $instantSearchHelper,
    ) {}

    public function getSortingOptions(): array
    {
        return [
            ['key' => 'relevance', 'label' => 'Relevance' ],
            ...$this->transformSortingOptions($this->instantSearchHelper->getSorting())
        ];
    }

    protected function transformSortingOptions(array $sortingOptions): array
    {
        return array_values(array_map(
            fn($sort) => [
                'key' => $sort['attribute'] . '_' . $sort['sort'],
                'label' => $sort['sortLabel']
            ],
            $sortingOptions
        ));
    }
}
