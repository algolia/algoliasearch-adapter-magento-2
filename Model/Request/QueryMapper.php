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
use Magento\Framework\App\ScopeResolverInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Search\Request\Filter\Term;
use Magento\Framework\Search\Request\FilterInterface as RequestFilterInterface;
use Magento\Framework\Search\Request\Query\BoolExpression as BoolQuery;
use Magento\Framework\Search\Request\Query\Filter as FilterQuery;
use Magento\Framework\Search\Request\Query\MatchQuery;
use Magento\Framework\Search\Request\QueryInterface as RequestQueryInterface;
use Magento\Framework\Search\RequestInterface;

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

    public function getStoreId(RequestInterface $request): int
    {
        $dimension = current($request->getDimensions());
        return $this->scopeResolver->getScope($dimension->getValue())->getId();
    }

    /**
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
     * @throws NoSuchEntityException
     */
    protected function buildIndexOptions(RequestInterface $request): IndexOptionsInterface
    {
        return $this->indexOptionsBuilder->buildEntityIndexOptions($this->getStoreId($request));
    }

    /** Extrapolate pagination info from Magento originated search request */
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

    protected function buildQueryString(RequestInterface $request): string
    {
        $requestQuery = $request->getQuery();
        if ($requestQuery->getType() !== RequestQueryInterface::TYPE_BOOL) {
            return '';
        }

        /** @var BoolQuery $requestQuery */
        return $this->getSearchTermFromBoolQuery($requestQuery);
    }

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
        $this->applyCategoryFilter($params, $requestQuery);

        $this->applyVisibilityFilter($params, $requestQuery);

        return $params;
    }

    protected function applyCategoryFilter(array &$params, BoolQuery $boolQuery): void
    {
        $filterType = 'facetFilters';
        $category = $this->getParam($boolQuery, 'category');
        if ($category) {
            if (!array_key_exists($filterType, $params)) {
                $params[$filterType] = [];
            }
            $params[$filterType][] = "categoryIds:{$category}";
        }
    }

    protected function applyVisibilityFilter(array &$params, BoolQuery $boolQuery): void
    {
        $filterType = 'numericFilters';
        $visibility = $this->getParam($boolQuery, 'visibility');
        if ($visibility) {
            if (!is_array($visibility)) {
                $visibility = [$visibility];
            }
            if (!array_key_exists($filterType, $params)) {
                $params[$filterType] = [];
            }
            if (in_array(Visibility::VISIBILITY_IN_SEARCH, $visibility)) {
                $params[$filterType][] = sprintf('%s=1', ProductRecordFieldsInterface::VISIBILITY_SEARCH);
            }
            if (in_array(Visibility::VISIBILITY_IN_CATALOG, $visibility)) {
                $params[$filterType][] = sprintf('%s=1', ProductRecordFieldsInterface::VISIBILITY_CATALOG);
            }
        }
    }

    protected function getParam(BoolQuery $query, string $key): string|array|false
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
