<?php

namespace Algolia\SearchAdapter\Model\Request;

use Algolia\AlgoliaSearch\Api\Data\IndexOptionsInterface;
use Algolia\AlgoliaSearch\Api\Data\SearchQueryInterface;
use Algolia\AlgoliaSearch\Api\Data\SearchQueryInterfaceFactory;
use Algolia\AlgoliaSearch\Api\Product\ProductRecordFieldsInterface;
use Algolia\AlgoliaSearch\Service\Product\IndexOptionsBuilder;
use Algolia\SearchAdapter\Api\Data\PaginationInfoInterface;
use Algolia\SearchAdapter\Api\Data\PaginationInfoInterfaceFactory;
use Algolia\SearchAdapter\Api\Data\QueryMapperResultInterface;
use Algolia\SearchAdapter\Api\Data\QueryMapperResultInterfaceFactory;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Framework\Api\SortOrder;
use Magento\Framework\App\ScopeResolverInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Search\Request\Filter\Term;
use Magento\Framework\Search\Request\FilterInterface as RequestFilterInterface;
use Magento\Framework\Search\Request\Query\BoolExpression as BoolQuery;
use Magento\Framework\Search\Request\Query\Filter as FilterQuery;
use Magento\Framework\Search\Request\Query\MatchQuery;
use Magento\Framework\Search\Request\QueryInterface as RequestQueryInterface;
use Magento\Framework\Search\RequestInterface;

/**
 * QueryMapper is responsible for mapping the Magento search request to the Algolia search query
 */
class QueryMapper
{
    public function __construct(
        protected QueryMapperResultInterfaceFactory $queryMapperResultFactory,
        protected SearchQueryInterfaceFactory       $searchQueryFactory,
        protected PaginationInfoInterfaceFactory    $paginationInfoFactory,
        protected ScopeResolverInterface            $scopeResolver,
        protected IndexOptionsBuilder               $indexOptionsBuilder
    ) {}

    /**
     * Process the search request and return the query mapper result
     * The query mapper result contains the Algolia search query object and the derived pagination info
     * @throws NoSuchEntityException
     */
    public function process(RequestInterface $request): QueryMapperResultInterface
    {
        $pagination = $this->buildPaginationInfo($request);
        return $this->queryMapperResultFactory->create([
            'searchQuery' => $this->buildQueryObject($request, $pagination),
            'paginationInfo' => $pagination,
        ]);
    }

    /**
     * Get the store ID from the request
     */
    public function getStoreId(RequestInterface $request): int
    {
        $dimension = current($request->getDimensions());
        return $this->scopeResolver->getScope($dimension->getValue())->getId();
    }

    /**
     * Build the Algolia search query object
     * @throws NoSuchEntityException
     */
    protected function buildQueryObject(RequestInterface $request, PaginationInfoInterface $pagination): SearchQueryInterface
    {
        return $this->searchQueryFactory->create([
            'query' => $this->buildQueryString($request),
            'params' => $this->buildParams($request, $pagination),
            'indexOptions' => $this->buildIndexOptions($request)
        ]);
    }

    /**
     * Build the Algolia index options object
     * @throws NoSuchEntityException
     */
    protected function buildIndexOptions(RequestInterface $request): IndexOptionsInterface
    {
        $storeId = $this->getStoreId($request);
        $sort = $this->getSort($request);
        return $sort
            ? $this->indexOptionsBuilder->buildReplicaIndexOptions($storeId, $sort[SortOrder::FIELD], $sort[SortOrder::DIRECTION])
            : $this->indexOptionsBuilder->buildEntityIndexOptions($storeId);
    }

    /**
     * @return array<string,string>|null
     */
    protected function getSort(RequestInterface $request): ?array
    {
        // Magento has identified this as a deprecated method but it is used by Elasticsearch and OpenSearch
        // To maintain compatibility we are safely calling this method until it is removed at a future time
        if (!method_exists($request, 'getSort')) {
            return null;
        }

        $sort = $request->getSort();
        if (empty($sort)) {
            return null;
        }

        // only single sort supported
        return reset($sort);
    }

    /**
     * Extrapolate pagination info from Magento originated search request
     */
    protected function buildPaginationInfo(RequestInterface $request): PaginationInfoInterface
    {
        $pageSize = $request->getSize() ?? PaginationInfo::DEFAULT_PAGE_SIZE;
        $offset = $request->getFrom() ?? 0;
        return $this->paginationInfoFactory->create([
            'pageNumber' => floor($offset/$pageSize) + 1,
            'pageSize' => $pageSize,
            'offset' => $offset,
        ]);
    }

    /**
     * Build the Algolia search query string
     */
    protected function buildQueryString(RequestInterface $request): string
    {
        $requestQuery = $request->getQuery();
        if ($requestQuery->getType() !== RequestQueryInterface::TYPE_BOOL) {
            return '';
        }

        /** @var BoolQuery $requestQuery */
        return $this->getSearchTermFromBoolQuery($requestQuery);
    }

    /**
     * Get the search term from the Magento originated boolean query
     */
    protected function getSearchTermFromBoolQuery(BoolQuery $query): string
    {
        $should = $query->getShould();
        if (!$should || !array_key_exists('search', $should)) {
            return '';
        }

        /** @var MatchQuery $matchQuery */
        $matchQuery = $should['search'];
        return $matchQuery->getValue();
    }

    /**
     * Build the Algolia search query parameters
     */
    protected function buildParams(RequestInterface $request, PaginationInfoInterface $pagination): array
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
