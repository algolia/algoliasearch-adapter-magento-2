<?php

namespace Algolia\SearchAdapter\Model;

use Algolia\AlgoliaSearch\Exceptions\AlgoliaException;
use Algolia\SearchAdapter\Model\Request\QueryMapper;
use Algolia\SearchAdapter\Model\Response\DocumentMapper;
use Algolia\SearchAdapter\Service\AlgoliaBackendConnector;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Search\AdapterInterface;
use Magento\Framework\Search\RequestInterface;
use Magento\Framework\Search\Response\QueryResponse;

// Fallback to Elasticsearch classes (also used by OpenSearch)
use Magento\Elasticsearch\SearchAdapter\Aggregation\Builder as AggregationBuilder;
use Magento\Elasticsearch\SearchAdapter\QueryContainerFactory;
use Magento\Elasticsearch\SearchAdapter\ResponseFactory;
use Magento\Elasticsearch\ElasticAdapter\SearchAdapter\Mapper;

class Adapter implements AdapterInterface
{
    public function __construct(
        protected AlgoliaBackendConnector $connector,
        protected QueryMapper             $queryMapper,
        protected DocumentMapper          $documentMapper,
        protected ResponseFactory         $responseFactory,
        protected AggregationBuilder      $aggregationBuilder,
        protected QueryContainerFactory   $queryContainerFactory,
        protected Mapper                  $mapper,
    ){}

    /**
     * @inheritDoc
     *
     * @throws NoSuchEntityException|AlgoliaException
     */
    public function query(RequestInterface $request): QueryResponse
    {
        $queryLegacy = $this->mapper->buildQuery($request);
        $query = $this->queryMapper->buildQuery($request);

        $response = $this->connector->query($query);

        $documents = $this->documentMapper->buildDocuments($response);

        // Mock response for aggregations and testing
        // TODO: Implement Algolia aggregation builder
        $mockResponse = $this->getSampleResponseData($request);
        $mockDocuments = $mockResponse['hits']['hits'] ?? [];
        $mockTotal = $mockResponse['hits']['total']['value'] ?? 0;
        $this->aggregationBuilder->setQuery($this->queryContainerFactory->create(['query' => $queryLegacy]));
        $mockAggregations = $this->aggregationBuilder->build($request, $mockResponse);
        $mockRawArray = [
            'documents' => $mockDocuments,
            'aggregations' => $mockAggregations,
            'total' => $mockTotal
        ];
        // End mocks

        $rawArray =  [
            'documents' => $documents,
            'aggregations' => $mockAggregations,
            'total' => count($documents)
        ];

        // TODO: Implement Algolia response factory as needed
        return $this->responseFactory->create(
            $rawArray
        );
    }

    /**
     * Temporary methods to simulate data shape used by Elasticsearch based implementations
     * @param RequestInterface $request
     * @return array
     */
    private function getSampleResponseData(RequestInterface $request): array
    {
        if (array_key_exists('search', $request->getQuery()->getShould())) {
            return $this->rawSearchResponseProvider();
        } else {
            return $this->rawCategoryResponseProvider();
        }
    }

    /** Test on Category ID 12 */
    private function rawCategoryResponseProvider(): array
    {
        return array(
            'took' => 10,
            'timed_out' => false,
            '_shards' =>
                array(
                    'total' => 1,
                    'successful' => 1,
                    'skipped' => 0,
                    'failed' => 0,
                ),
            'hits' =>
                array(
                    'total' =>
                        array(
                            'value' => 48,
                            'relation' => 'eq',
                        ),
                    'max_score' => NULL,
                    'hits' =>
                        array(
                            0 =>
                                array(
                                    '_index' => 'magento2_product_1_v59',
                                    '_score' => NULL,
                                    'fields' =>
                                        array(
                                            '_id' =>
                                                array(
                                                    0 => '254',
                                                ),
                                        ),
                                    'sort' =>
                                        array(
                                            0 => 9792,
                                            1 => 254.0,
                                        ),
                                ),
                            1 =>
                                array(
                                    '_index' => 'magento2_product_1_v59',
                                    '_score' => NULL,
                                    'fields' =>
                                        array(
                                            '_id' =>
                                                array(
                                                    0 => '238',
                                                ),
                                        ),
                                    'sort' =>
                                        array(
                                            0 => 9808,
                                            1 => 238.0,
                                        ),
                                ),
                            2 =>
                                array(
                                    '_index' => 'magento2_product_1_v59',
                                    '_score' => NULL,
                                    'fields' =>
                                        array(
                                            '_id' =>
                                                array(
                                                    0 => '622',
                                                ),
                                        ),
                                    'sort' =>
                                        array(
                                            0 => 9808,
                                            1 => 622.0,
                                        ),
                                ),
                            3 =>
                                array(
                                    '_index' => 'magento2_product_1_v59',
                                    '_score' => NULL,
                                    'fields' =>
                                        array(
                                            '_id' =>
                                                array(
                                                    0 => '222',
                                                ),
                                        ),
                                    'sort' =>
                                        array(
                                            0 => 9824,
                                            1 => 222.0,
                                        ),
                                ),
                            4 =>
                                array(
                                    '_index' => 'magento2_product_1_v59',
                                    '_score' => NULL,
                                    'fields' =>
                                        array(
                                            '_id' =>
                                                array(
                                                    0 => '430',
                                                ),
                                        ),
                                    'sort' =>
                                        array(
                                            0 => 9824,
                                            1 => 430.0,
                                        ),
                                ),
                            5 =>
                                array(
                                    '_index' => 'magento2_product_1_v59',
                                    '_score' => NULL,
                                    'fields' =>
                                        array(
                                            '_id' =>
                                                array(
                                                    0 => '606',
                                                ),
                                        ),
                                    'sort' =>
                                        array(
                                            0 => 9824,
                                            1 => 606.0,
                                        ),
                                ),
                            6 =>
                                array(
                                    '_index' => 'magento2_product_1_v59',
                                    '_score' => NULL,
                                    'fields' =>
                                        array(
                                            '_id' =>
                                                array(
                                                    0 => '206',
                                                ),
                                        ),
                                    'sort' =>
                                        array(
                                            0 => 9840,
                                            1 => 206.0,
                                        ),
                                ),
                            7 =>
                                array(
                                    '_index' => 'magento2_product_1_v59',
                                    '_score' => NULL,
                                    'fields' =>
                                        array(
                                            '_id' =>
                                                array(
                                                    0 => '414',
                                                ),
                                        ),
                                    'sort' =>
                                        array(
                                            0 => 9840,
                                            1 => 414.0,
                                        ),
                                ),
                            8 =>
                                array(
                                    '_index' => 'magento2_product_1_v59',
                                    '_score' => NULL,
                                    'fields' =>
                                        array(
                                            '_id' =>
                                                array(
                                                    0 => '590',
                                                ),
                                        ),
                                    'sort' =>
                                        array(
                                            0 => 9840,
                                            1 => 590.0,
                                        ),
                                ),
                            9 =>
                                array(
                                    '_index' => 'magento2_product_1_v59',
                                    '_score' => NULL,
                                    'fields' =>
                                        array(
                                            '_id' =>
                                                array(
                                                    0 => '190',
                                                ),
                                        ),
                                    'sort' =>
                                        array(
                                            0 => 9856,
                                            1 => 190.0,
                                        ),
                                ),
                            10 =>
                                array(
                                    '_index' => 'magento2_product_1_v59',
                                    '_score' => NULL,
                                    'fields' =>
                                        array(
                                            '_id' =>
                                                array(
                                                    0 => '398',
                                                ),
                                        ),
                                    'sort' =>
                                        array(
                                            0 => 9856,
                                            1 => 398.0,
                                        ),
                                ),
                            11 =>
                                array(
                                    '_index' => 'magento2_product_1_v59',
                                    '_score' => NULL,
                                    'fields' =>
                                        array(
                                            '_id' =>
                                                array(
                                                    0 => '574',
                                                ),
                                        ),
                                    'sort' =>
                                        array(
                                            0 => 9856,
                                            1 => 574.0,
                                        ),
                                ),
                        ),
                ),
            'aggregations' =>
                array(
                    'strap_bags_bucket' =>
                        array(
                            'doc_count_error_upper_bound' => 0,
                            'sum_other_doc_count' => 0,
                            'buckets' =>
                                array(),
                        ),
                    'purpose_bucket' =>
                        array(
                            'doc_count_error_upper_bound' => 0,
                            'sum_other_doc_count' => 0,
                            'buckets' =>
                                array(),
                        ),
                    'price_bucket' =>
                        array(
                            'count' => 48,
                            'min' => 18.0,
                            'max' => 99.0,
                            'avg' => 43.437291666667,
                            'sum' => 2084.99,
                            'sum_of_squares' => 107443.8601,
                            'variance' => 351.61544474826,
                            'variance_population' => 351.61544474826,
                            'variance_sampling' => 359.09662442376,
                            'std_deviation' => 18.751411806802,
                            'std_deviation_population' => 18.751411806802,
                            'std_deviation_sampling' => 18.949844970969,
                            'std_deviation_bounds' =>
                                array(
                                    'upper' => 80.94011528027,
                                    'lower' => 5.9344680530631,
                                    'upper_population' => 80.94011528027,
                                    'lower_population' => 5.9344680530631,
                                    'upper_sampling' => 81.336981608605,
                                    'lower_sampling' => 5.5376017247286,
                                ),
                        ),
                    'style_general_bucket' =>
                        array(
                            'doc_count_error_upper_bound' => 0,
                            'sum_other_doc_count' => 0,
                            'buckets' =>
                                array(
                                    0 =>
                                        array(
                                            'key' => '134',
                                            'doc_count' => 12,
                                        ),
                                    1 =>
                                        array(
                                            'key' => '135',
                                            'doc_count' => 12,
                                        ),
                                    2 =>
                                        array(
                                            'key' => '127',
                                            'doc_count' => 7,
                                        ),
                                    3 =>
                                        array(
                                            'key' => '119',
                                            'doc_count' => 6,
                                        ),
                                    4 =>
                                        array(
                                            'key' => '125',
                                            'doc_count' => 6,
                                        ),
                                    5 =>
                                        array(
                                            'key' => '116',
                                            'doc_count' => 5,
                                        ),
                                    6 =>
                                        array(
                                            'key' => '122',
                                            'doc_count' => 5,
                                        ),
                                    7 =>
                                        array(
                                            'key' => '123',
                                            'doc_count' => 5,
                                        ),
                                    8 =>
                                        array(
                                            'key' => '124',
                                            'doc_count' => 5,
                                        ),
                                    9 =>
                                        array(
                                            'key' => '128',
                                            'doc_count' => 5,
                                        ),
                                    10 =>
                                        array(
                                            'key' => '120',
                                            'doc_count' => 3,
                                        ),
                                    11 =>
                                        array(
                                            'key' => '121',
                                            'doc_count' => 2,
                                        ),
                                    12 =>
                                        array(
                                            'key' => '117',
                                            'doc_count' => 1,
                                        ),
                                    13 =>
                                        array(
                                            'key' => '129',
                                            'doc_count' => 1,
                                        ),
                                ),
                        ),
                    'category_gear_bucket' =>
                        array(
                            'doc_count_error_upper_bound' => 0,
                            'sum_other_doc_count' => 0,
                            'buckets' =>
                                array(),
                        ),
                    'format_bucket' =>
                        array(
                            'doc_count_error_upper_bound' => 0,
                            'sum_other_doc_count' => 0,
                            'buckets' =>
                                array(),
                        ),
                    'features_bags_bucket' =>
                        array(
                            'doc_count_error_upper_bound' => 0,
                            'sum_other_doc_count' => 0,
                            'buckets' =>
                                array(),
                        ),
                    'collar_bucket' =>
                        array(
                            'doc_count_error_upper_bound' => 0,
                            'sum_other_doc_count' => 0,
                            'buckets' =>
                                array(),
                        ),
                    'sleeve_bucket' =>
                        array(
                            'doc_count_error_upper_bound' => 0,
                            'sum_other_doc_count' => 0,
                            'buckets' =>
                                array(),
                        ),
                    'sale_bucket' =>
                        array(
                            'doc_count_error_upper_bound' => 0,
                            'sum_other_doc_count' => 0,
                            'buckets' =>
                                array(
                                    0 =>
                                        array(
                                            'key' => 0,
                                            'doc_count' => 38,
                                        ),
                                    1 =>
                                        array(
                                            'key' => 1,
                                            'doc_count' => 10,
                                        ),
                                ),
                        ),
                    'new_bucket' =>
                        array(
                            'doc_count_error_upper_bound' => 0,
                            'sum_other_doc_count' => 0,
                            'buckets' =>
                                array(
                                    0 =>
                                        array(
                                            'key' => 0,
                                            'doc_count' => 36,
                                        ),
                                    1 =>
                                        array(
                                            'key' => 1,
                                            'doc_count' => 12,
                                        ),
                                ),
                        ),
                    'category_bucket' =>
                        array(
                            'doc_count_error_upper_bound' => 0,
                            'sum_other_doc_count' => 0,
                            'buckets' =>
                                array(
                                    0 =>
                                        array(
                                            'key' => 15,
                                            'doc_count' => 13,
                                        ),
                                    1 =>
                                        array(
                                            'key' => 16,
                                            'doc_count' => 12,
                                        ),
                                    2 =>
                                        array(
                                            'key' => 17,
                                            'doc_count' => 12,
                                        ),
                                    3 =>
                                        array(
                                            'key' => 14,
                                            'doc_count' => 11,
                                        ),
                                ),
                        ),
                    'size_bucket' =>
                        array(
                            'doc_count_error_upper_bound' => 0,
                            'sum_other_doc_count' => 0,
                            'buckets' =>
                                array(
                                    0 =>
                                        array(
                                            'key' => 166,
                                            'doc_count' => 48,
                                        ),
                                    1 =>
                                        array(
                                            'key' => 167,
                                            'doc_count' => 48,
                                        ),
                                    2 =>
                                        array(
                                            'key' => 168,
                                            'doc_count' => 48,
                                        ),
                                    3 =>
                                        array(
                                            'key' => 169,
                                            'doc_count' => 48,
                                        ),
                                    4 =>
                                        array(
                                            'key' => 170,
                                            'doc_count' => 48,
                                        ),
                                ),
                        ),
                    'activity_bucket' =>
                        array(
                            'doc_count_error_upper_bound' => 0,
                            'sum_other_doc_count' => 0,
                            'buckets' =>
                                array(),
                        ),
                    'color_bucket' =>
                        array(
                            'doc_count_error_upper_bound' => 0,
                            'sum_other_doc_count' => 0,
                            'buckets' =>
                                array(
                                    0 =>
                                        array(
                                            'key' => 50,
                                            'doc_count' => 25,
                                        ),
                                    1 =>
                                        array(
                                            'key' => 49,
                                            'doc_count' => 22,
                                        ),
                                    2 =>
                                        array(
                                            'key' => 58,
                                            'doc_count' => 21,
                                        ),
                                    3 =>
                                        array(
                                            'key' => 53,
                                            'doc_count' => 17,
                                        ),
                                    4 =>
                                        array(
                                            'key' => 56,
                                            'doc_count' => 9,
                                        ),
                                    5 =>
                                        array(
                                            'key' => 60,
                                            'doc_count' => 9,
                                        ),
                                    6 =>
                                        array(
                                            'key' => 52,
                                            'doc_count' => 8,
                                        ),
                                    7 =>
                                        array(
                                            'key' => 57,
                                            'doc_count' => 6,
                                        ),
                                    8 =>
                                        array(
                                            'key' => 59,
                                            'doc_count' => 6,
                                        ),
                                    9 =>
                                        array(
                                            'key' => 51,
                                            'doc_count' => 2,
                                        ),
                                    10 =>
                                        array(
                                            'key' => 54,
                                            'doc_count' => 1,
                                        ),
                                ),
                        ),
                    'style_bottom_bucket' =>
                        array(
                            'doc_count_error_upper_bound' => 0,
                            'sum_other_doc_count' => 0,
                            'buckets' =>
                                array(),
                        ),
                    'pattern_bucket' =>
                        array(
                            'doc_count_error_upper_bound' => 0,
                            'sum_other_doc_count' => 0,
                            'buckets' =>
                                array(
                                    0 =>
                                        array(
                                            'key' => '196',
                                            'doc_count' => 43,
                                        ),
                                    1 =>
                                        array(
                                            'key' => '194',
                                            'doc_count' => 4,
                                        ),
                                    2 =>
                                        array(
                                            'key' => '198',
                                            'doc_count' => 1,
                                        ),
                                ),
                        ),
                    'style_bags_bucket' =>
                        array(
                            'doc_count_error_upper_bound' => 0,
                            'sum_other_doc_count' => 0,
                            'buckets' =>
                                array(),
                        ),
                    'erin_recommends_bucket' =>
                        array(
                            'doc_count_error_upper_bound' => 0,
                            'sum_other_doc_count' => 0,
                            'buckets' =>
                                array(
                                    0 =>
                                        array(
                                            'key' => 0,
                                            'doc_count' => 38,
                                        ),
                                    1 =>
                                        array(
                                            'key' => 1,
                                            'doc_count' => 10,
                                        ),
                                ),
                        ),
                    'gender_bucket' =>
                        array(
                            'doc_count_error_upper_bound' => 0,
                            'sum_other_doc_count' => 0,
                            'buckets' =>
                                array(),
                        ),
                    'climate_bucket' =>
                        array(
                            'doc_count_error_upper_bound' => 0,
                            'sum_other_doc_count' => 0,
                            'buckets' =>
                                array(
                                    0 =>
                                        array(
                                            'key' => '201',
                                            'doc_count' => 38,
                                        ),
                                    1 =>
                                        array(
                                            'key' => '204',
                                            'doc_count' => 35,
                                        ),
                                    2 =>
                                        array(
                                            'key' => '208',
                                            'doc_count' => 25,
                                        ),
                                    3 =>
                                        array(
                                            'key' => '203',
                                            'doc_count' => 22,
                                        ),
                                    4 =>
                                        array(
                                            'key' => '207',
                                            'doc_count' => 21,
                                        ),
                                    5 =>
                                        array(
                                            'key' => '209',
                                            'doc_count' => 18,
                                        ),
                                    6 =>
                                        array(
                                            'key' => '206',
                                            'doc_count' => 5,
                                        ),
                                    7 =>
                                        array(
                                            'key' => '205',
                                            'doc_count' => 4,
                                        ),
                                    8 =>
                                        array(
                                            'key' => '210',
                                            'doc_count' => 3,
                                        ),
                                    9 =>
                                        array(
                                            'key' => '202',
                                            'doc_count' => 2,
                                        ),
                                ),
                        ),
                    'performance_fabric_bucket' =>
                        array(
                            'doc_count_error_upper_bound' => 0,
                            'sum_other_doc_count' => 0,
                            'buckets' =>
                                array(
                                    0 =>
                                        array(
                                            'key' => 0,
                                            'doc_count' => 35,
                                        ),
                                    1 =>
                                        array(
                                            'key' => 1,
                                            'doc_count' => 13,
                                        ),
                                ),
                        ),
                    'material_bucket' =>
                        array(
                            'doc_count_error_upper_bound' => 0,
                            'sum_other_doc_count' => 0,
                            'buckets' =>
                                array(
                                    0 =>
                                        array(
                                            'key' => '38',
                                            'doc_count' => 32,
                                        ),
                                    1 =>
                                        array(
                                            'key' => '153',
                                            'doc_count' => 16,
                                        ),
                                    2 =>
                                        array(
                                            'key' => '142',
                                            'doc_count' => 11,
                                        ),
                                    3 =>
                                        array(
                                            'key' => '144',
                                            'doc_count' => 10,
                                        ),
                                    4 =>
                                        array(
                                            'key' => '37',
                                            'doc_count' => 10,
                                        ),
                                    5 =>
                                        array(
                                            'key' => '147',
                                            'doc_count' => 7,
                                        ),
                                    6 =>
                                        array(
                                            'key' => '158',
                                            'doc_count' => 7,
                                        ),
                                    7 =>
                                        array(
                                            'key' => '33',
                                            'doc_count' => 5,
                                        ),
                                    8 =>
                                        array(
                                            'key' => '152',
                                            'doc_count' => 4,
                                        ),
                                    9 =>
                                        array(
                                            'key' => '148',
                                            'doc_count' => 3,
                                        ),
                                    10 =>
                                        array(
                                            'key' => '151',
                                            'doc_count' => 3,
                                        ),
                                    11 =>
                                        array(
                                            'key' => '145',
                                            'doc_count' => 2,
                                        ),
                                    12 =>
                                        array(
                                            'key' => '150',
                                            'doc_count' => 1,
                                        ),
                                    13 =>
                                        array(
                                            'key' => '155',
                                            'doc_count' => 1,
                                        ),
                                    14 =>
                                        array(
                                            'key' => '39',
                                            'doc_count' => 1,
                                        ),
                                ),
                        ),
                    'manufacturer_bucket' =>
                        array(
                            'doc_count_error_upper_bound' => 0,
                            'sum_other_doc_count' => 0,
                            'buckets' =>
                                array(),
                        ),
                    'eco_collection_bucket' =>
                        array(
                            'doc_count_error_upper_bound' => 0,
                            'sum_other_doc_count' => 0,
                            'buckets' =>
                                array(
                                    0 =>
                                        array(
                                            'key' => 0,
                                            'doc_count' => 40,
                                        ),
                                    1 =>
                                        array(
                                            'key' => 1,
                                            'doc_count' => 8,
                                        ),
                                ),
                        ),
                ),
        );
    }

    /** "Joust" */
    private function rawSearchResponseProvider(): array
    {
        return array(
            'took' => 20,
            'timed_out' => false,
            '_shards' =>
                array(
                    'total' => 1,
                    'successful' => 1,
                    'skipped' => 0,
                    'failed' => 0,
                ),
            'hits' =>
                array(
                    'total' =>
                        array(
                            'value' => 1,
                            'relation' => 'eq',
                        ),
                    'max_score' => NULL,
                    'hits' =>
                        array(
                            0 =>
                                array(
                                    '_index' => 'magento2_product_1_v59',
                                    '_score' => 138.00128,
                                    'fields' =>
                                        array(
                                            '_id' =>
                                                array(
                                                    0 => '1',
                                                ),
                                        ),
                                    'sort' =>
                                        array(
                                            0 => 138.00128,
                                            1 => 1.0,
                                        ),
                                ),
                        ),
                ),
            'aggregations' =>
                array(
                    'price_bucket' =>
                        array(
                            'count' => 1,
                            'min' => 34.0,
                            'max' => 34.0,
                            'avg' => 34.0,
                            'sum' => 34.0,
                            'sum_of_squares' => 1156.0,
                            'variance' => 0.0,
                            'variance_population' => 0.0,
                            'variance_sampling' => 'NaN',
                            'std_deviation' => 0.0,
                            'std_deviation_population' => 0.0,
                            'std_deviation_sampling' => 'NaN',
                            'std_deviation_bounds' =>
                                array(
                                    'upper' => 34.0,
                                    'lower' => 34.0,
                                    'upper_population' => 34.0,
                                    'lower_population' => 34.0,
                                    'upper_sampling' => 'NaN',
                                    'lower_sampling' => 'NaN',
                                ),
                        ),
                    'category_bucket' =>
                        array(
                            'doc_count_error_upper_bound' => 0,
                            'sum_other_doc_count' => 0,
                            'buckets' =>
                                array(
                                    0 =>
                                        array(
                                            'key' => 3,
                                            'doc_count' => 1,
                                        ),
                                ),
                        ),
                ),
        );
    }

    private function rawEmptySearchProvider(): array
    {
        return [
            "hits" => [
                "hits" => []
            ],
            "aggregations" => [
                "price_bucket" => [],
                "category_bucket" => ["buckets" => []],
            ]
        ];
    }
}
