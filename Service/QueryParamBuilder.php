<?php

namespace Algolia\SearchAdapter\Service;

use Algolia\AlgoliaSearch\Api\Product\ProductRecordFieldsInterface;
use Algolia\AlgoliaSearch\Api\Product\ReplicaManagerInterface;
use Algolia\AlgoliaSearch\Helper\Configuration\InstantSearchHelper;
use Algolia\AlgoliaSearch\Service\Product\PriceKeyResolver;
use Algolia\SearchAdapter\Api\Data\PaginationInfoInterface;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Search\Request\Filter\Term;
use Magento\Framework\Search\Request\FilterInterface as RequestFilterInterface;
use Magento\Framework\Search\Request\Query\BoolExpression as BoolQuery;
use Magento\Framework\Search\Request\Query\Filter as FilterQuery;
use Magento\Framework\Search\Request\QueryInterface as RequestQueryInterface;
use Magento\Framework\Search\RequestInterface;

class QueryParamBuilder
{
    /** @var string  */
    protected const FACET_PARAM_PRICE = 'price';
    /** @var string  */
    protected const FACET_PARAM_HIERARCHICAL = 'categories';
    /**
     * Influences accuracy of product counts
     * See https://www.algolia.com/doc/api-reference/api-parameters/maxValuesPerFacet
     * @var int
     */
    public const MAX_VALUES_PER_FACET = 100;

    public function __construct(
        protected InstantSearchHelper $instantSearchHelper,
        protected StoreIdResolver     $storeIdResolver,
        protected PriceKeyResolver    $priceKeyResolver,
        protected FacetValueConverter $facetValueConverter,
    ) {}

    /**
     * Build the Algolia search query parameters
     */
    public function build(RequestInterface $request, PaginationInfoInterface $pagination): array
    {
        $storeId = $this->storeIdResolver->getStoreId($request);
        $params = [
            'hitsPerPage' => $pagination->getPageSize(),
            'page' => $pagination->getPageNumber() - 1, # Algolia pages are 0-based, Magento 1-based
            'facets' => $this->getFacetsToRetrieve($storeId),
            'maxValuesPerFacet' => $this->getMaxValuesPerFacet(),
        ];

        $filters = $this->getQueryFilters($request);

        if (empty($filters)) {
            return $params;
        }

        /** @var BoolQuery $requestQuery */
        $this->applyFilters($params, $filters, $storeId);

        return $params;
    }

    /**
     * Get filters from the search request query
     *
     * @return RequestQueryInterface[]
     */
    protected function getQueryFilters(RequestInterface $request): array
    {
        $requestQuery = $request->getQuery();
        if ($requestQuery->getType() !== RequestQueryInterface::TYPE_BOOL) {
            return [];
        }
        /** @var BoolQuery $requestQuery */
        return $requestQuery->getMust();
    }

    /**
     * Get facets to retrieve from the Algolia search request
     *
     * @return string[]
     */
    protected function getFacetsToRetrieve(int $storeId): array
    {
        try {
            $facets = array_map(
                fn($facet) => $this->transformFacetParam(
                    $facet[ReplicaManagerInterface::SORT_KEY_ATTRIBUTE_NAME],
                    $storeId
                ),
                $this->instantSearchHelper->getFacets($storeId)
            );
            // flatten all facets
            return array_merge(...$facets);
        } catch (NoSuchEntityException $e) {
            return [];
        }
    }

    /**
     * A single facet can be transformed to one or more facet attributes
     *
     * @return string[]
     * @throws NoSuchEntityException
     */
    protected function transformFacetParam(string $facet, int $storeId): array
    {
        if ($facet === self::FACET_PARAM_PRICE) {
            return [$facet . $this->priceKeyResolver->getPriceKey($storeId)];
        }

        if ($facet === self::FACET_PARAM_HIERARCHICAL) {
            return $this->splitHierarchicalFacet(self::FACET_PARAM_HIERARCHICAL);
        }

        return [$facet];
    }

    /*
     * Split the hierarchical facet into scalar levels
     *
     * @return string[]
     */
    protected function splitHierarchicalFacet(string $facet): array
    {
        $hierarchy = [];
        for ($level = 0; $level < 10; $level++) {
            $hierarchy[] = "$facet.level$level";
        }
        return $hierarchy;
    }

    /**
     * Apply the filters to the Algolia search query parameters
     *
     * @param array<string, mixed> $params
     * @param RequestQueryInterface[] $filters
     * @param int $storeId
     * @return void
     */
    protected function applyFilters(array &$params, array &$filters, int $storeId): void
    {
        $this->applyCategoryFilter($params, $filters);
        $this->applyVisibilityFilters($params, $filters);
        $this->applyFacetFilters($params, $filters, $storeId);
    }

    /**
     * Apply the category filter as a facet filter to the Algolia search query parameters
     *
     * @param array<string, mixed> $params
     * @param RequestQueryInterface[] $filters
     */
    protected function applyCategoryFilter(array &$params, array &$filters): void
    {
        $category = $this->getFilterParam($filters, 'category');
        if ($category) {
            $this->applyFilter($params, 'facetFilters', sprintf('categoryIds:%u', $category));
        }
    }

    /**
     * Apply visibility filters to the Algolia search query parameters
     *
     * @param array<string, mixed> $params
     * @param RequestQueryInterface[] $filters
     */
    protected function applyVisibilityFilters(array &$params, array &$filters): void
    {
        $visibility = $this->getFilterParam($filters, 'visibility');
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

    /*
     * Apply the facet filters to the Algolia search query parameters
     * This will filter the results returned by Algolia to only include products that match the facet values applied
     *
     * @param array<string, mixed> $params
     * @param RequestQueryInterface[] $filters
     */
    protected function applyFacetFilters(array &$params, array &$filters, int $storeId): void
    {
        if (empty($filters)) {
            return;
        }

        $facets = $this->instantSearchHelper->getFacets($storeId);

        foreach ($filters as $key => $filter) {
            $term = $this->getFacetFilterTerm($filter);
            if (!$term) {
                continue;
            }
            $facetName = $term->getField();
            $facet = $this->getMatchedFacet($facets, $facetName);
            if (!$facet) {
                continue;
            }
            $value = $this->facetValueConverter->convertOptionIdToLabel($facetName, $term->getValue());
            $this->applyFilter($params, 'facetFilters', sprintf('%s:%s', $facetName, $value));

            unset($filters[$key]); // burn down filters
        }
    }

    /**
     * Apply a filter of a given type and value to the Algolia search query parameters array
     *
     * @param array<string, mixed> $params
     * @param string $filterType
     * @param string $filterValue
     */
    protected function applyFilter(array &$params, string $filterType, string $filterValue): void
    {
        if (!array_key_exists($filterType, $params)) {
            $params[$filterType] = [];
        }
        $params[$filterType][] = $filterValue;
    }

    /**
     * Obtain a parameter from the query filters in the original Magento search request for processing by a worker
     *
     * Returns false if criteria not met
     *
     * @param RequestQueryInterface[] $filters - the filters to be processed
     *     (values will be modified if $remove is set to true)
     * @param string $key - the filter to search for
     * @param bool $remove - Removes the filter if found
     *     (True by default in order to burn down the filters for processing)
     */
    protected function getFilterParam(array &$filters, string $key, bool $remove = true): string|array|false
    {
        if (!array_key_exists($key, $filters)) {
            return false;
        }
        $filter = $filters[$key];
        if ($remove) {
            unset($filters[$key]);
        }

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

    /**
     * This will return the term from the filter query if it is a term filter
     */
    protected function getFacetFilterTerm(RequestQueryInterface $filter): ?Term
    {
        if ($filter->getType() !== RequestQueryInterface::TYPE_FILTER) {
            return null;
        }
        /** @var FilterQuery $filter */
        $term = $filter->getReference();
        if ($term->getType() !== RequestFilterInterface::TYPE_TERM) {
            return null;
        }
        return $term;
    }

    /**
     * This will return the matched facet from the configured facets if it exists
     *
     * @param array<string, mixed> $facets
     * @param string $facetName
     * @param bool $remove - Removes the facet if found
     *     (True by default in order to burn down the facets for processing)
     * @return array<string, mixed>|null
     */
    protected function getMatchedFacet(array &$facets, string $facetName, bool $remove = true): ?array {
        foreach ($facets as $i => $facet) {
            if ($facet[ReplicaManagerInterface::SORT_KEY_ATTRIBUTE_NAME] === $facetName) {
                if ($remove) {
                    unset($facets[$i]);
                }
                return $facet;
            }
        }
        return null;
    }

    public function getMaxValuesPerFacet(): int
    {
        return self::MAX_VALUES_PER_FACET;
    }
}
