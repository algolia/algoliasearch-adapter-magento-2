<?php

namespace Algolia\SearchAdapter\Model\Request;

use Algolia\AlgoliaSearch\Api\Data\IndexOptionsInterface;
use Algolia\AlgoliaSearch\Api\Data\SearchQueryInterface;
use Algolia\AlgoliaSearch\Api\Data\SearchQueryInterfaceFactory;
use Algolia\AlgoliaSearch\Service\Product\IndexOptionsBuilder;
use Algolia\SearchAdapter\Api\Data\PaginationInfoInterface;
use Algolia\SearchAdapter\Api\Data\PaginationInfoInterfaceFactory;
use Algolia\SearchAdapter\Api\Data\QueryMapperResultInterface;
use Algolia\SearchAdapter\Api\Data\QueryMapperResultInterfaceFactory;
use Algolia\SearchAdapter\Registry\SortState;
use Algolia\SearchAdapter\Service\QueryParamBuilder;
use Algolia\SearchAdapter\Service\StoreIdResolver;
use Magento\Framework\Api\SortOrder;
use Magento\Framework\Api\SortOrderBuilder;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Search\Request\Query\BoolExpression as BoolQuery;
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
        protected StoreIdResolver                   $storeIdResolver,
        protected IndexOptionsBuilder               $indexOptionsBuilder,
        protected SortState                         $sortState,
        protected SortOrderBuilder                  $sortOrderBuilder,
        protected QueryParamBuilder                 $queryParamBuilder,
    ) {}

    /**
     * Process the search request and return the query mapper result
     * The query mapper result contains the Algolia search query object and the derived pagination info
     * @throws NoSuchEntityException|LocalizedException
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
     * Build the Algolia search query object
     * @throws NoSuchEntityException|LocalizedException
     */
    protected function buildQueryObject(RequestInterface $request, PaginationInfoInterface $pagination): SearchQueryInterface
    {
        return $this->searchQueryFactory->create([
            'query' => $this->buildQueryString($request),
            'params' => $this->queryParamBuilder->build($request, $pagination),
            'indexOptions' => $this->buildIndexOptions($request)
        ]);
    }

    /**
     * Build the Algolia index options object
     * @throws NoSuchEntityException|LocalizedException
     */
    protected function buildIndexOptions(RequestInterface $request): IndexOptionsInterface
    {
        $storeId = $this->storeIdResolver->getStoreId($request);
        $sort = $this->getSort($request);
        return $sort
            ? $this->indexOptionsBuilder->buildReplicaIndexOptions($storeId, $sort->getField(), $sort->getDirection())
            : $this->indexOptionsBuilder->buildEntityIndexOptions($storeId);
    }

    protected function getSort(RequestInterface $request): ?SortOrder
    {
        // Magento has identified this as a deprecated method, but it is used by Elasticsearch and OpenSearch
        // To maintain compatibility we are safely calling this method until it is removed at a future time
        if (!method_exists($request, 'getSort')) {
            return $this->sortState->get();
        }

        $sort = $request->getSort(); // returns an array of sorts
        if (empty($sort)) {
            return null;
        }

        $singleSort = reset($sort); // only single sort supported
        return $this->sortOrderBuilder
            ->setField($singleSort[SortOrder::FIELD])
            ->setDirection($singleSort[SortOrder::DIRECTION])
            ->create();
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


}
