<?php

namespace Algolia\SearchAdapter\Test\Unit\Service;

use Algolia\AlgoliaSearch\Api\Product\ProductRecordFieldsInterface;
use Algolia\AlgoliaSearch\Api\Product\ReplicaManagerInterface;
use Algolia\AlgoliaSearch\Helper\Configuration\InstantSearchHelper;
use Algolia\AlgoliaSearch\Service\PriceKeyResolver;
use Algolia\AlgoliaSearch\Test\TestCase;
use Algolia\SearchAdapter\Api\Data\PaginationInfoInterface;
use Algolia\SearchAdapter\Service\QueryParamBuilder;
use Algolia\SearchAdapter\Service\StoreIdResolver;
use Algolia\SearchAdapter\Test\Traits\QueryTestTrait;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Framework\Search\Request\FilterInterface as RequestFilterInterface;
use Magento\Framework\Search\Request\QueryInterface as RequestQueryInterface;
use PHPUnit\Framework\MockObject\MockObject;

class QueryParamBuilderTest extends TestCase
{
    use QueryTestTrait;
    private QueryParamBuilder $queryParamBuilder;
    private PaginationInfoInterface|MockObject $paginationInfo;
    private InstantSearchHelper|MockObject $instantSearchHelper;
    private StoreIdResolver|MockObject $storeIdResolver;
    private PriceKeyResolver|MockObject $priceKeyResolver;

    protected function setUp(): void
    {
        $this->instantSearchHelper = $this->createMock(InstantSearchHelper::class);
        $this->storeIdResolver = $this->createMock(StoreIdResolver::class);
        $this->priceKeyResolver = $this->createMock(PriceKeyResolver::class);
        $this->queryParamBuilder = new QueryParamBuilder(
            $this->instantSearchHelper,
            $this->storeIdResolver,
            $this->priceKeyResolver
        );
        $this->paginationInfo = $this->createMock(PaginationInfoInterface::class);
    }

    public function testBuildWithCategoryFilter(): void
    {
        $request = $this->createMockRequest();
        $boolQuery = $this->createMockBoolQuery();

        $request->method('getQuery')->willReturn($boolQuery);
        $boolQuery->method('getMust')->willReturn(['category' => $this->createMockFilterQuery('12')]);

        $this->paginationInfo->method('getPageSize')->willReturn(20);
        $this->paginationInfo->method('getPageNumber')->willReturn(1);
        
        $this->storeIdResolver->method('getStoreId')->willReturn(1);
        $this->instantSearchHelper->method('getFacets')->willReturn([]);

        $result = $this->queryParamBuilder->build($request, $this->paginationInfo);

        $this->assertEquals([
            'hitsPerPage' => 20,
            'page' => 0,
            'facets' => [],
            'facetFilters' => ['categoryIds:12']
        ], $result);
    }

    public function testBuildWithNonBoolQuery(): void
    {
        $request = $this->createMockRequest();
        $nonBoolQuery = $this->createMock(RequestQueryInterface::class);

        $request->method('getQuery')->willReturn($nonBoolQuery);
        $nonBoolQuery->method('getType')->willReturn(RequestQueryInterface::TYPE_MATCH);

        $this->paginationInfo->method('getPageSize')->willReturn(20);
        $this->paginationInfo->method('getPageNumber')->willReturn(1);
        
        $this->storeIdResolver->method('getStoreId')->willReturn(1);
        $this->instantSearchHelper->method('getFacets')->willReturn([]);

        $result = $this->queryParamBuilder->build($request, $this->paginationInfo);

        $this->assertEquals([
            'hitsPerPage' => 20,
            'page' => 0,
            'facets' => []
        ], $result);
    }

    public function testBuildWithoutCategoryFilter(): void
    {
        $request = $this->createMockRequest();
        $boolQuery = $this->createMockBoolQuery();

        $request->method('getQuery')->willReturn($boolQuery);
        $boolQuery->method('getMust')->willReturn(['other' => 'value']);

        $this->paginationInfo->method('getPageSize')->willReturn(20);
        $this->paginationInfo->method('getPageNumber')->willReturn(1);
        
        $this->storeIdResolver->method('getStoreId')->willReturn(1);
        $this->instantSearchHelper->method('getFacets')->willReturn([]);

        $result = $this->queryParamBuilder->build($request, $this->paginationInfo);

        $this->assertEquals([
            'hitsPerPage' => 20,
            'page' => 0,
            'facets' => []
        ], $result);
    }

    public function testGetFilterParamWithValidFilter(): void
    {
        $boolQuery = $this->createMockBoolQuery();

        $boolQuery->method('getMust')->willReturn(['category' => $this->createMockFilterQuery('12')]);

        $result = $this->invokeMethod($this->queryParamBuilder, 'getFilterParam', [$boolQuery, 'category']);

        $this->assertEquals('12', $result);
    }

    public function testGetFilterParamWithMissingKey(): void
    {
        $boolQuery = $this->createMockBoolQuery();

        $boolQuery->method('getMust')->willReturn(['other' => 'value']);

        $result = $this->invokeMethod($this->queryParamBuilder, 'getFilterParam', [$boolQuery, 'category']);

        $this->assertEquals(false, $result);
    }

    public function testGetFilterParamWithNonFilterType(): void
    {
        $boolQuery = $this->createMockBoolQuery();
        $nonFilterQuery = $this->createMock(RequestQueryInterface::class);

        $boolQuery->method('getMust')->willReturn(['category' => $nonFilterQuery]);
        $nonFilterQuery->method('getType')->willReturn(RequestQueryInterface::TYPE_MATCH);

        $result = $this->invokeMethod($this->queryParamBuilder, 'getFilterParam', [$boolQuery, 'category']);

        $this->assertEquals(false, $result);
    }

    public function testGetFilterParamWithNonReferenceFilter(): void
    {
        $boolQuery = $this->createMockBoolQuery();
        $filterQuery = $this->createMockFilterQuery();

        $boolQuery->method('getMust')->willReturn(['category' => $filterQuery]);

        $filterQuery->method('getType')->willReturn(RequestQueryInterface::TYPE_FILTER);
        $filterQuery->method('getReferenceType')->willReturn('other');

        $result = $this->invokeMethod($this->queryParamBuilder, 'getFilterParam', [$boolQuery, 'category']);

        $this->assertEquals(false, $result);
    }

    public function testGetFilterParamWithFalseValue(): void
    {
        $boolQuery = $this->createMockBoolQuery();

        $boolQuery->method('getMust')->willReturn(['category' => $this->createMockFilterQuery(false)]);

        $result = $this->invokeMethod($this->queryParamBuilder, 'getFilterParam', [$boolQuery, 'category']);

        $this->assertEquals(false, $result);
    }

    public function testBuildWithVisibilityFilterInSearchOnly(): void
    {
        $request = $this->createMockRequest();
        $boolQuery = $this->createMockBoolQuery();

        $request->method('getQuery')->willReturn($boolQuery);
        $boolQuery->method('getMust')->willReturn([
            'visibility' => $this->createMockFilterQuery(Visibility::VISIBILITY_IN_SEARCH)
        ]);

        $this->paginationInfo->method('getPageSize')->willReturn(20);
        $this->paginationInfo->method('getPageNumber')->willReturn(1);
        
        $this->storeIdResolver->method('getStoreId')->willReturn(1);
        $this->instantSearchHelper->method('getFacets')->willReturn([]);

        $result = $this->queryParamBuilder->build($request, $this->paginationInfo);

        $this->assertEquals([
            'hitsPerPage' => 20,
            'page' => 0,
            'facets' => [],
            'numericFilters' => [sprintf('%s=1', ProductRecordFieldsInterface::VISIBILITY_SEARCH)]
        ], $result);
    }

    public function testBuildWithVisibilityFilterInCatalogOnly(): void
    {
        $request = $this->createMockRequest();
        $boolQuery = $this->createMockBoolQuery();

        $request->method('getQuery')->willReturn($boolQuery);
        $boolQuery->method('getMust')->willReturn([
            'visibility' => $this->createMockFilterQuery(Visibility::VISIBILITY_IN_CATALOG)
        ]);

        $this->paginationInfo->method('getPageSize')->willReturn(20);
        $this->paginationInfo->method('getPageNumber')->willReturn(1);
        
        $this->storeIdResolver->method('getStoreId')->willReturn(1);
        $this->instantSearchHelper->method('getFacets')->willReturn([]);

        $result = $this->queryParamBuilder->build($request, $this->paginationInfo);

        $this->assertEquals([
            'hitsPerPage' => 20,
            'page' => 0,
            'facets' => [],
            'numericFilters' => [sprintf('%s=1', ProductRecordFieldsInterface::VISIBILITY_CATALOG)]
        ], $result);
    }

    public function testBuildWithVisibilityFilterBothValues(): void
    {
        $request = $this->createMockRequest();
        $boolQuery = $this->createMockBoolQuery();
        $filterQuery = $this->createMockFilterQuery([
            Visibility::VISIBILITY_IN_SEARCH,
            Visibility::VISIBILITY_IN_CATALOG
        ]);

        $request->method('getQuery')->willReturn($boolQuery);
        $boolQuery->method('getMust')->willReturn(['visibility' => $filterQuery]);

        $this->paginationInfo->method('getPageSize')->willReturn(20);
        $this->paginationInfo->method('getPageNumber')->willReturn(1);
        
        $this->storeIdResolver->method('getStoreId')->willReturn(1);
        $this->instantSearchHelper->method('getFacets')->willReturn([]);

        $result = $this->queryParamBuilder->build($request, $this->paginationInfo);

        $this->assertEquals([
            'hitsPerPage' => 20,
            'page' => 0,
            'facets' => [],
            'numericFilters' => [
                sprintf('%s=1', ProductRecordFieldsInterface::VISIBILITY_SEARCH),
                sprintf('%s=1', ProductRecordFieldsInterface::VISIBILITY_CATALOG)
            ]
        ], $result);
    }

    public function testBuildWithCategoryAndVisibilityFilters(): void
    {
        $request = $this->createMockRequest();
        $boolQuery = $this->createMockBoolQuery();

        $request->method('getQuery')->willReturn($boolQuery);
        $boolQuery->method('getMust')->willReturn([
            'category' => $this->createMockFilterQuery('12'),
            'visibility' => $this->createMockFilterQuery(Visibility::VISIBILITY_IN_SEARCH)
        ]);

        $this->paginationInfo->method('getPageSize')->willReturn(20);
        $this->paginationInfo->method('getPageNumber')->willReturn(1);
        
        $this->storeIdResolver->method('getStoreId')->willReturn(1);
        $this->instantSearchHelper->method('getFacets')->willReturn([]);

        $result = $this->queryParamBuilder->build($request, $this->paginationInfo);

        $this->assertEquals([
            'hitsPerPage' => 20,
            'page' => 0,
            'facets' => [],
            'facetFilters' => ['categoryIds:12'],
            'numericFilters' => [sprintf('%s=1', ProductRecordFieldsInterface::VISIBILITY_SEARCH)]
        ], $result);
    }

    public function testBuildWithVisibilityFilterNonMatchingValue(): void
    {
        $request = $this->createMockRequest();
        $boolQuery = $this->createMockBoolQuery();
        $request->method('getQuery')->willReturn($boolQuery);
        $boolQuery->method('getMust')->willReturn(['visibility' => $this->createMockFilterQuery(1)]);

        $this->paginationInfo->method('getPageSize')->willReturn(20);
        $this->paginationInfo->method('getPageNumber')->willReturn(1);
        
        $this->storeIdResolver->method('getStoreId')->willReturn(1);
        $this->instantSearchHelper->method('getFacets')->willReturn([]);

        $result = $this->queryParamBuilder->build($request, $this->paginationInfo);

        $this->assertEquals([
            'hitsPerPage' => 20,
            'page' => 0,
            'facets' => []
        ], $result);
    }

    public function testBuildWithoutVisibilityFilter(): void
    {
        $request = $this->createMockRequest();
        $boolQuery = $this->createMockBoolQuery();

        $request->method('getQuery')->willReturn($boolQuery);
        $boolQuery->method('getMust')->willReturn(['other' => 'value']);

        $this->paginationInfo->method('getPageSize')->willReturn(20);
        $this->paginationInfo->method('getPageNumber')->willReturn(1);
        
        $this->storeIdResolver->method('getStoreId')->willReturn(1);
        $this->instantSearchHelper->method('getFacets')->willReturn([]);

        $result = $this->queryParamBuilder->build($request, $this->paginationInfo);

        $this->assertEquals([
            'hitsPerPage' => 20,
            'page' => 0,
            'facets' => []
        ], $result);
    }

    public function testApplyVisibilityFiltersWithInSearchSingleValue(): void
    {
        $boolQuery = $this->createMockBoolQuery();

        $boolQuery->method('getMust')->willReturn([
            'visibility' => $this->createMockFilterQuery(Visibility::VISIBILITY_IN_SEARCH)
        ]);

        $params = [];

        $this->invokeMethod($this->queryParamBuilder, 'applyVisibilityFilters', [&$params, $boolQuery]);

        $this->assertEquals([
            'numericFilters' => [sprintf('%s=1', ProductRecordFieldsInterface::VISIBILITY_SEARCH)]
        ], $params);
    }

    public function testApplyVisibilityFiltersWithInCatalogSingleValue(): void
    {
        $boolQuery = $this->createMockBoolQuery();

        $boolQuery->method('getMust')->willReturn([
            'visibility' => $this->createMockFilterQuery(Visibility::VISIBILITY_IN_CATALOG)
        ]);

        $params = [];

        $this->invokeMethod($this->queryParamBuilder, 'applyVisibilityFilters', [&$params, $boolQuery]);

        $this->assertEquals([
            'numericFilters' => [sprintf('%s=1', ProductRecordFieldsInterface::VISIBILITY_CATALOG)]
        ], $params);
    }

    public function testApplyVisibilityFiltersWithBothValuesArray(): void
    {
        $boolQuery = $this->createMockBoolQuery();
        $filterQuery = $this->createMockFilterQuery([
            Visibility::VISIBILITY_IN_SEARCH,
            Visibility::VISIBILITY_IN_CATALOG
        ]);

        $boolQuery->method('getMust')->willReturn(['visibility' => $filterQuery]);

        $params = [];

        $this->invokeMethod($this->queryParamBuilder, 'applyVisibilityFilters', [&$params, $boolQuery]);

        $this->assertEquals([
            'numericFilters' => [
                sprintf('%s=1', ProductRecordFieldsInterface::VISIBILITY_SEARCH),
                sprintf('%s=1', ProductRecordFieldsInterface::VISIBILITY_CATALOG)
            ]
        ], $params);
    }

    public function testApplyVisibilityFiltersWithNoVisibilityParam(): void
    {
        $boolQuery = $this->createMockBoolQuery();

        $boolQuery->method('getMust')->willReturn(['other' => 'value']);

        $params = [];

        $this->invokeMethod($this->queryParamBuilder, 'applyVisibilityFilters', [&$params, $boolQuery]);

        $this->assertEquals([], $params);
    }

    public function testApplyVisibilityFiltersWithFalseVisibilityParam(): void
    {
        $boolQuery = $this->createMockBoolQuery();

        $boolQuery->method('getMust')->willReturn(['visibility' => $this->createMockFilterQuery(false)]);

        $params = [];

        $this->invokeMethod($this->queryParamBuilder, 'applyVisibilityFilters', [&$params, $boolQuery]);

        $this->assertEquals([], $params);
    }

    public function testApplyVisibilityFiltersWithArrayNonMatchingValues(): void
    {
        $boolQuery = $this->createMockBoolQuery();
        $filterQuery = $this->createMockFilterQuery();
        $termFilter = $this->createMockTermFilter();

        $boolQuery->method('getMust')->willReturn(['visibility' => $filterQuery]);

        $filterQuery->method('getReference')->willReturn($termFilter);

        $termFilter->method('getType')->willReturn(RequestFilterInterface::TYPE_TERM);
        $termFilter->method('getValue')->willReturn([1, 4]);

        $params = [];

        $this->invokeMethod($this->queryParamBuilder, 'applyVisibilityFilters', [&$params, $boolQuery]);

        $this->assertEquals([], $params);
    }

    public function testApplyVisibilityFilter(): void
    {
        $params = [];

        $this->invokeMethod($this->queryParamBuilder, 'applyVisibilityFilter', [
            &$params,
            ProductRecordFieldsInterface::VISIBILITY_SEARCH
        ]);

        $this->assertEquals([
            'numericFilters' => [sprintf('%s=1', ProductRecordFieldsInterface::VISIBILITY_SEARCH)]
        ], $params);
    }

    public function testApplyVisibilityFilterWithExistingNumericFilters(): void
    {
        $params = [
            'numericFilters' => ['price>100']
        ];

        $this->invokeMethod($this->queryParamBuilder, 'applyVisibilityFilter', [
            &$params,
            ProductRecordFieldsInterface::VISIBILITY_CATALOG
        ]);

        $this->assertEquals([
            'numericFilters' => [
                'price>100',
                sprintf('%s=1', ProductRecordFieldsInterface::VISIBILITY_CATALOG)
            ]
        ], $params);
    }

    public function testApplyFilters(): void
    {
        $boolQuery = $this->createMockBoolQuery();

        $boolQuery->method('getMust')->willReturn([
            'category' => $this->createMockFilterQuery('12'),
            'visibility' => $this->createMockFilterQuery(Visibility::VISIBILITY_IN_SEARCH)
        ]);

        $params = [];

        $this->invokeMethod($this->queryParamBuilder, 'applyFilters', [&$params, $boolQuery]);

        $this->assertEquals([
            'facetFilters' => ['categoryIds:12'],
            'numericFilters' => [sprintf('%s=1', ProductRecordFieldsInterface::VISIBILITY_SEARCH)]
        ], $params);
    }

    /**
     * @dataProvider facetTransformDataProvider
     */
    public function testTransformFacetParam(string $facetName, ?string $priceKey, array $expected): void
    {
        $storeId = 1;
        
        if ($priceKey !== null) {
            $this->priceKeyResolver->method('getPriceKey')->with($storeId)->willReturn($priceKey);
        }

        $result = $this->invokeMethod($this->queryParamBuilder, 'transformFacetParam', [$facetName, $storeId]);

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
        $result = $this->invokeMethod($this->queryParamBuilder, 'splitHierarchicalFacet', ['categories']);

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
        $storeId = 1;

        $this->instantSearchHelper->method('getFacets')->with($storeId)->willReturn([]);

        $result = $this->invokeMethod($this->queryParamBuilder, 'getFacets', [$storeId]);

        $this->assertEquals([], $result);
    }

    public function testGetFacetsWithPriceAndCategories(): void
    {
        $storeId = 1;

        $this->instantSearchHelper->method('getFacets')->with($storeId)->willReturn([
            [ReplicaManagerInterface::SORT_KEY_ATTRIBUTE_NAME => 'price'],
            [ReplicaManagerInterface::SORT_KEY_ATTRIBUTE_NAME => 'categories']
        ]);
        $this->priceKeyResolver->method('getPriceKey')->with($storeId)->willReturn('.USD.default');

        $result = $this->invokeMethod($this->queryParamBuilder, 'getFacets', [$storeId]);

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
        $storeId = 1;

        $this->instantSearchHelper->method('getFacets')->with($storeId)->willReturn([
            [ReplicaManagerInterface::SORT_KEY_ATTRIBUTE_NAME => 'color'],
            [ReplicaManagerInterface::SORT_KEY_ATTRIBUTE_NAME => 'size'],
            [ReplicaManagerInterface::SORT_KEY_ATTRIBUTE_NAME => 'material']
        ]);

        $result = $this->invokeMethod($this->queryParamBuilder, 'getFacets', [$storeId]);

        $this->assertEquals(['color', 'size', 'material'], $result);
    }

    public function testGetFacetsWithExceptionReturnsEmpty(): void
    {
        $storeId = 1;

        $this->priceKeyResolver->method('getPriceKey')
            ->willThrowException(new \Magento\Framework\Exception\NoSuchEntityException(__('Store not found')));

        $this->instantSearchHelper->method('getFacets')->with($storeId)->willReturn([
            [ReplicaManagerInterface::SORT_KEY_ATTRIBUTE_NAME => 'price']
        ]);

        $result = $this->invokeMethod($this->queryParamBuilder, 'getFacets', [$storeId]);

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

        $result = $this->queryParamBuilder->build($request, $this->paginationInfo);

        $this->assertEquals([
            'hitsPerPage' => 20,
            'page' => 0,
            'facets' => $expectedFacets
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
}

