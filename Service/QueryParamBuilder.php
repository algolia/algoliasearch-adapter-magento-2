<?php

namespace Algolia\SearchAdapter\Service;

use Algolia\AlgoliaSearch\Api\Product\ProductRecordFieldsInterface;
use Algolia\AlgoliaSearch\Api\Product\ReplicaManagerInterface;
use Algolia\AlgoliaSearch\Helper\Configuration\InstantSearchHelper;
use Algolia\SearchAdapter\Api\Data\PaginationInfoInterface;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Framework\Search\Request\FilterInterface as RequestFilterInterface;
use Magento\Framework\Search\Request\Query\BoolExpression as BoolQuery;
use Magento\Framework\Search\Request\Query\Filter as FilterQuery;
use Magento\Framework\Search\Request\QueryInterface as RequestQueryInterface;
use Magento\Framework\Search\RequestInterface;

class QueryParamBuilder
{
    public function __construct(
        protected InstantSearchHelper $instantSearchHelper,
        protected StoreIdResolver     $storeIdResolver,
    ) {}

    /**
     * Build the Algolia search query parameters
     */
    public function build(RequestInterface $request, PaginationInfoInterface $pagination): array
    {
        $params = [
            'hitsPerPage' => $pagination->getPageSize(),
            'page' => $pagination->getPageNumber() - 1 # Algolia pages are 0-based, Magento 1-based
        ];

        $requestQuery = $request->getQuery();
        if ($requestQuery->getType() !== RequestQueryInterface::TYPE_BOOL) {
            return $params;
        }

        /** @var BoolQuery $requestQuery */
        $this->applyFilters($params, $requestQuery);

        return $params;
    }

    protected function getFacets(RequestInterface $request): array
    {
        $storeId = $this->storeIdResolver->getStoreId($request);
        return array_map(
            fn($facet) =>  $facet[ReplicaManagerInterface::SORT_KEY_ATTRIBUTE_NAME],
            $this->instantSearchHelper->getFacets($storeId)
        );
    }

    /**
     * Apply the filters to the Algolia search query parameters
     */
    protected function applyFilters(array &$params, BoolQuery $boolQuery): void
    {
        $this->applyCategoryFilter($params, $boolQuery);
        $this->applyVisibilityFilters($params, $boolQuery);
    }

    /**
     * Apply the category filter as a facet filter to the Algolia search query parameters
     */
    protected function applyCategoryFilter(array &$params, BoolQuery $boolQuery): void
    {
        $category = $this->getFilterParam($boolQuery, 'category');
        if ($category) {
            $this->applyFilter($params, 'facetFilters', sprintf('categoryIds:%u', $category));
        }
    }

    /**
     * Apply visibility filters to the Algolia search query parameters
     */
    protected function applyVisibilityFilters(array &$params, BoolQuery $boolQuery): void
    {
        $visibility = $this->getFilterParam($boolQuery, 'visibility');
        if ($visibility) {
            if (!is_array($visibility)) {
                $visibility = [$visibility];
            }
            if (in_array(Visibility::VISIBILITY_IN_SEARCH, $visibility)) {
                $this->applyVisibilityFilter($params, ProductRecordFieldsInterface::VISIBILITY_SEARCH);
            }
            if (in_array(Visibility::VISIBILITY_IN_CATALOG, $visibility)) {
                $this->applyVisibilityFilter($params, ProductRecordFieldsInterface::VISIBILITY_CATALOG);
            }
        }
    }

    /**
     * Apply the visibility field as a numeric filter to the Algolia search query parameters
     */
    protected function applyVisibilityFilter(array &$params, string $visibilityFilterField): void
    {
        $this->applyFilter($params, 'numericFilters', sprintf('%s=1', $visibilityFilterField));
    }

    /**
     * Apply a filter of a given type and value to the Algolia search query parameters array
     */
    protected function applyFilter(array &$params, string $filterType, string $filterValue): void
    {
        if (!array_key_exists($filterType, $params)) {
            $params[$filterType] = [];
        }
        $params[$filterType][] = $filterValue;
    }

    /**
     * Get a parameter from the boolean query in the original Magento search request
     */
    protected function getFilterParam(BoolQuery $query, string $key): string|array|false
    {
        $must = $query->getMust();
        if (!array_key_exists($key, $must)) {
            return false;
        }
        $filter = $must[$key];
        if ($filter->getType() !== RequestQueryInterface::TYPE_FILTER) {
            return false;
        }
        /** @var FilterQuery $filter */
        if ($filter->getReferenceType() !== FilterQuery::REFERENCE_FILTER) {
            return false;
        }

        $term = $filter->getReference();
        if ($term->getType() !== RequestFilterInterface::TYPE_TERM) {
            return false;
        }

        return $term->getValue();
    }
}
