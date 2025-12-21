<?php

namespace Algolia\SearchAdapter\Test\Unit\Service;

use Algolia\AlgoliaSearch\Api\Product\ReplicaManagerInterface;
use Algolia\AlgoliaSearch\Api\Product\RuleContextInterface;
use Algolia\AlgoliaSearch\Helper\Configuration\InstantSearchHelper;
use Algolia\AlgoliaSearch\Service\Product\PriceKeyResolver;
use Algolia\AlgoliaSearch\Test\TestCase;
use Algolia\SearchAdapter\Api\Data\PaginationInfoInterface;
use Algolia\SearchAdapter\Api\Service\FilterHandlerInterface;
use Algolia\SearchAdapter\Service\QueryParamBuilder;
use Algolia\SearchAdapter\Service\StoreIdResolver;
use Algolia\SearchAdapter\Test\Traits\QueryTestTrait;
use Magento\Framework\Search\Request\QueryInterface as RequestQueryInterface;
use PHPUnit\Framework\MockObject\MockObject;

class QueryParamBuilderTest extends TestCase
{
    use QueryTestTrait;

    private PaginationInfoInterface|MockObject $paginationInfo;
    private InstantSearchHelper|MockObject $instantSearchHelper;
    private StoreIdResolver|MockObject $storeIdResolver;
    private PriceKeyResolver|MockObject $priceKeyResolver;

    protected function setUp(): void
    {
        $this->instantSearchHelper = $this->createMock(InstantSearchHelper::class);
        $this->storeIdResolver = $this->createMock(StoreIdResolver::class);
        $this->priceKeyResolver = $this->createMock(PriceKeyResolver::class);
        $this->paginationInfo = $this->createMock(PaginationInfoInterface::class);
    }

    /** Conditionally create a QueryParamBuilder with or without filter handlers */
    private function createQueryParamBuilder(array $filterHandlers = []): QueryParamBuilder
    {
        return new QueryParamBuilder(
            $this->instantSearchHelper,
            $this->storeIdResolver,
            $this->priceKeyResolver,
            $filterHandlers
        );
    }

    public function testBuildWithNoFilters(): void
    {
        $queryParamBuilder = $this->createQueryParamBuilder();
        $request = $this->createMockRequest();
        $boolQuery = $this->createMockBoolQuery();

        $request->method('getQuery')->willReturn($boolQuery);
        $boolQuery->method('getMust')->willReturn([]);

        $this->paginationInfo->method('getPageSize')->willReturn(20);
        $this->paginationInfo->method('getPageNumber')->willReturn(1);

        $this->storeIdResolver->method('getStoreId')->willReturn(1);
        $this->instantSearchHelper->method('getFacets')->willReturn([]);

        $result = $queryParamBuilder->build($request, $this->paginationInfo);

        $this->assertEquals([
            'hitsPerPage' => 20,
            'page' => 0,
            'facets' => [],
            'maxValuesPerFacet' => 100,
            'ruleContexts' => [RuleContextInterface::FACET_FILTERS_CONTEXT],
        ], $result);
    }

    /** We always expect a boolean query - if not supplied then the params fall back to default */
    public function testBuildWithNonBoolQuery(): void
    {
        $queryParamBuilder = $this->createQueryParamBuilder();
        $request = $this->createMockRequest();
        $nonBoolQuery = $this->createMock(RequestQueryInterface::class);

        $request->method('getQuery')->willReturn($nonBoolQuery);
        $nonBoolQuery->method('getType')->willReturn(RequestQueryInterface::TYPE_MATCH);

        $this->paginationInfo->method('getPageSize')->willReturn(20);
        $this->paginationInfo->method('getPageNumber')->willReturn(1);

        $this->storeIdResolver->method('getStoreId')->willReturn(1);
        $this->instantSearchHelper->method('getFacets')->willReturn([]);

        $result = $queryParamBuilder->build($request, $this->paginationInfo);

        $this->assertEquals([
            'hitsPerPage' => 20,
            'page' => 0,
            'facets' => [],
            'maxValuesPerFacet' => 100,
            'ruleContexts' => [RuleContextInterface::FACET_FILTERS_CONTEXT],
        ], $result);
    }

    public function testBuildCallsFilterHandlers(): void
    {
        $filterHandler1 = $this->createMock(FilterHandlerInterface::class);
        $filterHandler2 = $this->createMock(FilterHandlerInterface::class);

        $filterHandler1->expects($this->once())
            ->method('process')
            ->with(
                $this->isType('array'),
                $this->isType('array'),
                $this->equalTo(1)
            );

        $filterHandler2->expects($this->once())
            ->method('process')
            ->with(
                $this->isType('array'),
                $this->isType('array'),
                $this->equalTo(1)
            );

        $queryParamBuilder = $this->createQueryParamBuilder([$filterHandler1, $filterHandler2]);

        $request = $this->createMockRequest();
        $boolQuery = $this->createMockBoolQuery();
        $otherQuery = $this->createMock(RequestQueryInterface::class);

        $request->method('getQuery')->willReturn($boolQuery);
        $boolQuery->method('getMust')->willReturn(['other' => $otherQuery]);

        $this->paginationInfo->method('getPageSize')->willReturn(20);
        $this->paginationInfo->method('getPageNumber')->willReturn(1);

        $this->storeIdResolver->method('getStoreId')->willReturn(1);
        $this->instantSearchHelper->method('getFacets')->willReturn([]);

        $queryParamBuilder->build($request, $this->paginationInfo);
    }

    public function testBuildDoesNotCallFilterHandlersWhenNoFilters(): void
    {
        $filterHandler = $this->createMock(FilterHandlerInterface::class);

        $filterHandler->expects($this->never())
            ->method('process');

        $queryParamBuilder = $this->createQueryParamBuilder([$filterHandler]);

        $request = $this->createMockRequest();
        $boolQuery = $this->createMockBoolQuery();

        $request->method('getQuery')->willReturn($boolQuery);
        $boolQuery->method('getMust')->willReturn([]);

        $this->paginationInfo->method('getPageSize')->willReturn(20);
        $this->paginationInfo->method('getPageNumber')->willReturn(1);

        $this->storeIdResolver->method('getStoreId')->willReturn(1);
        $this->instantSearchHelper->method('getFacets')->willReturn([]);

        $queryParamBuilder->build($request, $this->paginationInfo);
    }

    public function testBuildWithPagination(): void
    {
        $queryParamBuilder = $this->createQueryParamBuilder();
        $request = $this->createMockRequest();
        $boolQuery = $this->createMockBoolQuery();

        $request->method('getQuery')->willReturn($boolQuery);
        $boolQuery->method('getMust')->willReturn([]);

        $this->paginationInfo->method('getPageSize')->willReturn(10);
        $this->paginationInfo->method('getPageNumber')->willReturn(3);

        $this->storeIdResolver->method('getStoreId')->willReturn(1);
        $this->instantSearchHelper->method('getFacets')->willReturn([]);

        $result = $queryParamBuilder->build($request, $this->paginationInfo);

        $this->assertEquals(10, $result['hitsPerPage']);
        $this->assertEquals(2, $result['page']); // 0-based: page 3 becomes 2
    }

    /**
     * @dataProvider facetTransformDataProvider
     */
    public function testTransformFacetParam(string $facetName, ?string $priceKey, array $expected): void
    {
        $queryParamBuilder = $this->createQueryParamBuilder();
        $storeId = 1;

        if ($priceKey !== null) {
            $this->priceKeyResolver->method('getPriceKey')->with($storeId)->willReturn($priceKey);
        }

        $result = $this->invokeMethod($queryParamBuilder, 'transformFacetParam', [$facetName, $storeId]);

        $this->assertEquals($expected, $result);
    }

    public static function facetTransformDataProvider(): array
    {
        return [
            [
                'facetName' => 'price',
                'priceKey' => '.USD.default',
                'expected' => ['price.USD.default']
            ],
            [
                'facetName' => 'price',
                'priceKey' => '.EUR.group_2',
                'expected' => ['price.EUR.group_2']
            ],
            [
                'facetName' => 'categories',
                'priceKey' => null,
                'expected' => [
                    'categories.level0',
                    'categories.level1',
                    'categories.level2',
                    'categories.level3',
                    'categories.level4',
                    'categories.level5',
                    'categories.level6',
                    'categories.level7',
                    'categories.level8',
                    'categories.level9'
                ]
            ],
            [
                'facetName' => 'color',
                'priceKey' => null,
                'expected' => ['color']
            ],
            [
                'facetName' => 'size',
                'priceKey' => null,
                'expected' => ['size']
            ]
        ];
    }

    public function testSplitHierarchicalFacet(): void
    {
        $queryParamBuilder = $this->createQueryParamBuilder();
        $result = $this->invokeMethod($queryParamBuilder, 'splitHierarchicalFacet', ['categories']);

        $expected = [
            'categories.level0',
            'categories.level1',
            'categories.level2',
            'categories.level3',
            'categories.level4',
            'categories.level5',
            'categories.level6',
            'categories.level7',
            'categories.level8',
            'categories.level9'
        ];

        $this->assertEquals($expected, $result);
    }

    public function testGetFacetsWithEmptyConfig(): void
    {
        $queryParamBuilder = $this->createQueryParamBuilder();
        $storeId = 1;

        $this->instantSearchHelper->method('getFacets')->with($storeId)->willReturn([]);

        $result = $this->invokeMethod($queryParamBuilder, 'getFacetsToRetrieve', [$storeId]);

        $this->assertEquals([], $result);
    }

    public function testGetFacetsWithPriceAndCategories(): void
    {
        $queryParamBuilder = $this->createQueryParamBuilder();
        $storeId = 1;

        $this->instantSearchHelper->method('getFacets')->with($storeId)->willReturn([
            [ReplicaManagerInterface::SORT_KEY_ATTRIBUTE_NAME => 'price'],
            [ReplicaManagerInterface::SORT_KEY_ATTRIBUTE_NAME => 'categories']
        ]);
        $this->priceKeyResolver->method('getPriceKey')->with($storeId)->willReturn('.USD.default');

        $result = $this->invokeMethod($queryParamBuilder, 'getFacetsToRetrieve', [$storeId]);

        $expected = [
            'price.USD.default',
            'categories.level0',
            'categories.level1',
            'categories.level2',
            'categories.level3',
            'categories.level4',
            'categories.level5',
            'categories.level6',
            'categories.level7',
            'categories.level8',
            'categories.level9'
        ];

        $this->assertEquals($expected, $result);
    }

    public function testGetFacetsWithAttributes(): void
    {
        $queryParamBuilder = $this->createQueryParamBuilder();
        $storeId = 1;

        $this->instantSearchHelper->method('getFacets')->with($storeId)->willReturn([
            [ReplicaManagerInterface::SORT_KEY_ATTRIBUTE_NAME => 'color'],
            [ReplicaManagerInterface::SORT_KEY_ATTRIBUTE_NAME => 'size'],
            [ReplicaManagerInterface::SORT_KEY_ATTRIBUTE_NAME => 'material']
        ]);

        $result = $this->invokeMethod($queryParamBuilder, 'getFacetsToRetrieve', [$storeId]);

        $this->assertEquals(['color', 'size', 'material'], $result);
    }

    public function testGetFacetsWithExceptionReturnsEmpty(): void
    {
        $queryParamBuilder = $this->createQueryParamBuilder();
        $storeId = 1;

        $this->priceKeyResolver->method('getPriceKey')
            ->willThrowException(new \Magento\Framework\Exception\NoSuchEntityException(__('Store not found')));

        $this->instantSearchHelper->method('getFacets')->with($storeId)->willReturn([
            [ReplicaManagerInterface::SORT_KEY_ATTRIBUTE_NAME => 'price']
        ]);

        $result = $this->invokeMethod($queryParamBuilder, 'getFacetsToRetrieve', [$storeId]);

        $this->assertEquals([], $result);
    }

    /**
     * @dataProvider fullFacetScenarioDataProvider
     */
    public function testBuildWithFacets(
        array $configuredFacets,
        ?string $priceKey,
        array $expectedFacets
    ): void {
        $queryParamBuilder = $this->createQueryParamBuilder();
        $storeId = 1;
        $request = $this->createMockRequest();
        $boolQuery = $this->createMockBoolQuery();

        $request->method('getQuery')->willReturn($boolQuery);
        $boolQuery->method('getMust')->willReturn([]);

        $this->paginationInfo->method('getPageSize')->willReturn(20);
        $this->paginationInfo->method('getPageNumber')->willReturn(1);

        $this->storeIdResolver->method('getStoreId')->willReturn($storeId);
        $this->instantSearchHelper->method('getFacets')->with($storeId)->willReturn($configuredFacets);

        if ($priceKey !== null) {
            $this->priceKeyResolver->method('getPriceKey')->with($storeId)->willReturn($priceKey);
        }

        $result = $queryParamBuilder->build($request, $this->paginationInfo);

        $this->assertEquals([
            'hitsPerPage' => 20,
            'page' => 0,
            'facets' => $expectedFacets,
            'maxValuesPerFacet' => 100,
            'ruleContexts' => [RuleContextInterface::FACET_FILTERS_CONTEXT],
        ], $result);
    }

    public static function fullFacetScenarioDataProvider(): array
    {
        return [
            [
                'configuredFacets' => [
                    [ReplicaManagerInterface::SORT_KEY_ATTRIBUTE_NAME => 'price']
                ],
                'priceKey' => '.USD.default',
                'expectedFacets' => ['price.USD.default']
            ],
            [
                'configuredFacets' => [
                    [ReplicaManagerInterface::SORT_KEY_ATTRIBUTE_NAME => 'price']
                ],
                'priceKey' => '.EUR.group_2',
                'expectedFacets' => ['price.EUR.group_2']
            ],
            [
                'configuredFacets' => [
                    [ReplicaManagerInterface::SORT_KEY_ATTRIBUTE_NAME => 'categories']
                ],
                'priceKey' => null,
                'expectedFacets' => [
                    'categories.level0',
                    'categories.level1',
                    'categories.level2',
                    'categories.level3',
                    'categories.level4',
                    'categories.level5',
                    'categories.level6',
                    'categories.level7',
                    'categories.level8',
                    'categories.level9'
                ]
            ],
            [
                'configuredFacets' => [
                    [ReplicaManagerInterface::SORT_KEY_ATTRIBUTE_NAME => 'color'],
                    [ReplicaManagerInterface::SORT_KEY_ATTRIBUTE_NAME => 'size'],
                    [ReplicaManagerInterface::SORT_KEY_ATTRIBUTE_NAME => 'material']
                ],
                'priceKey' => null,
                'expectedFacets' => ['color', 'size', 'material']
            ],
            [
                'configuredFacets' => [
                    [ReplicaManagerInterface::SORT_KEY_ATTRIBUTE_NAME => 'price'],
                    [ReplicaManagerInterface::SORT_KEY_ATTRIBUTE_NAME => 'categories'],
                    [ReplicaManagerInterface::SORT_KEY_ATTRIBUTE_NAME => 'color'],
                    [ReplicaManagerInterface::SORT_KEY_ATTRIBUTE_NAME => 'size'],
                    [ReplicaManagerInterface::SORT_KEY_ATTRIBUTE_NAME => 'brand']
                ],
                'priceKey' => '.USD.default',
                'expectedFacets' => [
                    'price.USD.default',
                    'categories.level0',
                    'categories.level1',
                    'categories.level2',
                    'categories.level3',
                    'categories.level4',
                    'categories.level5',
                    'categories.level6',
                    'categories.level7',
                    'categories.level8',
                    'categories.level9',
                    'color',
                    'size',
                    'brand'
                ]
            ],
            [
                'configuredFacets' => [
                    [ReplicaManagerInterface::SORT_KEY_ATTRIBUTE_NAME => 'price'],
                    [ReplicaManagerInterface::SORT_KEY_ATTRIBUTE_NAME => 'categories'],
                    [ReplicaManagerInterface::SORT_KEY_ATTRIBUTE_NAME => 'color'],
                    [ReplicaManagerInterface::SORT_KEY_ATTRIBUTE_NAME => 'size'],
                    [ReplicaManagerInterface::SORT_KEY_ATTRIBUTE_NAME => 'material'],
                    [ReplicaManagerInterface::SORT_KEY_ATTRIBUTE_NAME => 'brand']
                ],
                'priceKey' => '.GBP.group_5',
                'expectedFacets' => [
                    'price.GBP.group_5',
                    'categories.level0',
                    'categories.level1',
                    'categories.level2',
                    'categories.level3',
                    'categories.level4',
                    'categories.level5',
                    'categories.level6',
                    'categories.level7',
                    'categories.level8',
                    'categories.level9',
                    'color',
                    'size',
                    'material',
                    'brand'
                ]
            ]
        ];
    }

    public function testGetMaxValuesPerFacet(): void
    {
        $queryParamBuilder = $this->createQueryParamBuilder();
        $this->assertEquals(100, $queryParamBuilder->getMaxValuesPerFacet());
    }
}
