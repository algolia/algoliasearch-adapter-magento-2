<?php

namespace Algolia\SearchAdapter\Model\Request;

use Algolia\AlgoliaSearch\Api\Data\IndexOptionsInterface;
use Algolia\AlgoliaSearch\Api\Data\SearchQueryInterface;
use Algolia\AlgoliaSearch\Api\Data\SearchQueryInterfaceFactory;
use Algolia\AlgoliaSearch\Exceptions\AlgoliaException;
use Algolia\AlgoliaSearch\Service\Product\IndexOptionsBuilder;
use Magento\Elasticsearch\Model\Adapter\FieldMapper\Product\FieldProvider\FieldType\ConverterInterface as FieldTypeConverterInterface;
use Magento\Framework\App\ScopeResolverInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Search\Request\FilterInterface as RequestFilterInterface;
use Magento\Framework\Search\Request\Query\BoolExpression as BoolQuery;
use Magento\Framework\Search\Request\Query\Filter as FilterQuery;
use Magento\Framework\Search\Request\Query\MatchQuery;
use Magento\Framework\Search\Request\QueryInterface as RequestQueryInterface;
use Magento\Framework\Search\RequestInterface;

class QueryMapper
{
    public function __construct(
        protected SearchQueryInterfaceFactory $searchQueryFactory,
        protected ScopeResolverInterface      $scopeResolver,
        protected IndexOptionsBuilder         $indexOptionsBuilder,
    ) {}

    /**
     * @throws NoSuchEntityException
     * @throws AlgoliaException
     */
    public function buildQuery(RequestInterface $request): SearchQueryInterface
    {
        return $this->searchQueryFactory->create([
            'indexOptions' => $this->getIndexOptions($request),
            'query' => $this->getQuery($request),
            'params' => $this->getParams($request),
        ]);
    }

    /**
     * @throws NoSuchEntityException
     * @throws AlgoliaException
     */
    protected function getIndexOptions(RequestInterface $request): IndexOptionsInterface
    {
        $dimension = current($request->getDimensions());
        $storeId = $this->scopeResolver->getScope($dimension->getValue())->getId();
        return $this->indexOptionsBuilder->buildEntityIndexOptions($storeId);
    }

    protected function getQuery(RequestInterface $request): string
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

    protected function getParams(RequestInterface $request): array
    {
        $params = [];
        $requestQuery = $request->getQuery();
        if ($requestQuery->getType() !== RequestQueryInterface::TYPE_BOOL) {
            return $params;
        }

        /** @var BoolQuery $requestQuery */
        $category = $this->getParam($requestQuery, 'category');
        if ($category) {
            $params['facetFilters'] = "categoryIds:{$category}";
        }

        // TODO: Implement visibility filters

        return $params;
    }

    protected function getParam(BoolQuery $query, string $key)
    {
        $must = $query->getMust();
        if (!array_key_exists($key, $must)) {
            return '';
        }
        $filter = $must[$key];
        if ($filter->getType() !== RequestQueryInterface::TYPE_FILTER) {
            return '';
        }
        /** @var FilterQuery $filter */
        if ($filter->getReferenceType() !== FilterQuery::REFERENCE_FILTER) {
            return '';
        }

        $term = $filter->getReference();
        return $term->getValue() !== false ? $term->getValue() : '';
    }

}
