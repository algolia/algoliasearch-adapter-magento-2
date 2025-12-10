<?php

namespace Algolia\SearchAdapter\Model;

use Algolia\AlgoliaSearch\Exceptions\AlgoliaException;
use Algolia\SearchAdapter\Api\Data\DocumentMapperResultInterface;
use Algolia\SearchAdapter\Model\Request\QueryMapper;
use Algolia\SearchAdapter\Model\Response\DocumentMapper;
use Algolia\SearchAdapter\Model\Response\ResponseFactory;
use Algolia\SearchAdapter\Model\Response\SearchQueryResultFactory;
use Algolia\SearchAdapter\Service\Aggregation\AggregationBuilder;
use Algolia\SearchAdapter\Service\AlgoliaBackendConnector;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Search\AdapterInterface;
use Magento\Framework\Search\RequestInterface;
use Magento\Framework\Search\Response\QueryResponse;

class Adapter implements AdapterInterface
{
    public function __construct(
        protected AlgoliaBackendConnector  $connector,
        protected QueryMapper              $queryMapper,
        protected DocumentMapper           $documentMapper,
        protected SearchQueryResultFactory $searchQueryResultFactory,
        protected AggregationBuilder       $aggregationBuilder,
        protected ResponseFactory          $responseFactory
    ){}

    /**
     * @inheritDoc
     *
     * @throws NoSuchEntityException|AlgoliaException|LocalizedException
     */
    public function query(RequestInterface $request): QueryResponse
    {
        $query = $this->queryMapper->process($request);
        $response = $this->connector->query($query->getSearchQuery());
        $search = $this->searchQueryResultFactory->create($response);
        $aggregations = $this->aggregationBuilder->build($request, $search);
        $result = $this->documentMapper->process($search, $query->getPaginationInfo());

        $data =  [
            DocumentMapperResultInterface::RESPONSE_KEY_DOCUMENTS => $result->getDocuments(),
            DocumentMapperResultInterface::RESPONSE_KEY_AGGREGATIONS => $aggregations,
            DocumentMapperResultInterface::RESPONSE_KEY_TOTAL => $result->getTotalCount()
        ];

        return $this->responseFactory->create(
            $data
        );
    }
}
