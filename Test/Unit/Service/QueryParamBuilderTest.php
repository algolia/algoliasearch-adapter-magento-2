<?php

namespace Algolia\SearchAdapter\Test\Unit\Service;

use Algolia\AlgoliaSearch\Api\Product\ProductRecordFieldsInterface;
use Algolia\AlgoliaSearch\Api\Product\ReplicaManagerInterface;
use Algolia\AlgoliaSearch\Helper\Configuration\InstantSearchHelper;
use Algolia\AlgoliaSearch\Service\Product\PriceKeyResolver;
use Algolia\AlgoliaSearch\Test\TestCase;
use Algolia\SearchAdapter\Api\Data\PaginationInfoInterface;
use Algolia\SearchAdapter\Service\FacetValueConverter;
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
    private FacetValueConverter|MockObject $facetValueConverter;

    protected function setUp(): void
    {
        $this->instantSearchHelper = $this->createMock(InstantSearchHelper::class);
        $this->storeIdResolver = $this->createMock(StoreIdResolver::class);
        $this->priceKeyResolver = $this->createMock(PriceKeyResolver::class);
        $this->facetValueConverter = $this->createMock(FacetValueConverter::class);
        $this->queryParamBuilder = new QueryParamBuilder(
            $this->instantSearchHelper,
            $this->storeIdResolver,
            $this->priceKeyResolver,
            $this->facetValueConverter
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
            'facetFilters' => ['categoryIds:12'],
            'maxValuesPerFacet' => 100
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
            'facets' => [],
            'maxValuesPerFacet' => 100
        ], $result);
    }

    public function testBuildWithoutCategoryFilter(): void
    {
        $request = $this->createMockRequest();
        $boolQuery = $this->createMockBoolQuery();
        $otherQuery = $this->createMock(RequestQueryInterface::class);

        $request->method('getQuery')->willReturn($boolQuery);
        $boolQuery->method('getMust')->willReturn(['other' => $otherQuery]);

        $this->paginationInfo->method('getPageSize')->willReturn(20);
        $this->paginationInfo->method('getPageNumber')->willReturn(1);

        $this->storeIdResolver->method('getStoreId')->willReturn(1);
        $this->instantSearchHelper->method('getFacets')->willReturn([]);

        $result = $this->queryParamBuilder->build($request, $this->paginationInfo);

        $this->assertEquals([
            'hitsPerPage' => 20,
            'page' => 0,
            'facets' => [],
            'maxValuesPerFacet' => 100
        ], $result);
    }

    public function testGetFilterParamWithValidFilter(): void
    {
        $filters = ['category' => $this->createMockFilterQuery('12')];

        $result = $this->invokeMethod($this->queryParamBuilder, 'getFilterParam', [&$filters, 'category']);

        $this->assertEquals('12', $result);
    }

    public function testGetFilterParamWithMissingKey(): void
    {
        $otherQuery = $this->createMock(RequestQueryInterface::class);
        $filters = ['other' => $otherQuery];

        $result = $this->invokeMethod($this->queryParamBuilder, 'getFilterParam', [&$filters, 'category']);

        $this->assertEquals(false, $result);
    }

    public function testGetFilterParamWithNonFilterType(): void
    {
        $nonFilterQuery = $this->createMock(RequestQueryInterface::class);
        $nonFilterQuery->method('getType')->willReturn(RequestQueryInterface::TYPE_MATCH);

        $filters = ['category' => $nonFilterQuery];

        $result = $this->invokeMethod($this->queryParamBuilder, 'getFilterParam', [&$filters, 'category']);

        $this->assertEquals(false, $result);
    }

    public function testGetFilterParamWithNonReferenceFilter(): void
    {
        $filterQuery = $this->createMockFilterQuery();
        $filterQuery->method('getType')->willReturn(RequestQueryInterface::TYPE_FILTER);
        $filterQuery->method('getReferenceType')->willReturn('other');

        $filters = ['category' => $filterQuery];

        $result = $this->invokeMethod($this->queryParamBuilder, 'getFilterParam', [&$filters, 'category']);

        $this->assertEquals(false, $result);
    }

    public function testGetFilterParamWithFalseValue(): void
    {
        $filters = ['category' => $this->createMockFilterQuery(false)];

        $result = $this->invokeMethod($this->queryParamBuilder, 'getFilterParam', [&$filters, 'category']);

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
            'numericFilters' => [sprintf('%s=1', ProductRecordFieldsInterface::VISIBILITY_SEARCH)],
            'maxValuesPerFacet' => 100
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
            'numericFilters' => [sprintf('%s=1', ProductRecordFieldsInterface::VISIBILITY_CATALOG)],
            'maxValuesPerFacet' => 100
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
            ],
            'maxValuesPerFacet' => 100
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
            'numericFilters' => [sprintf('%s=1', ProductRecordFieldsInterface::VISIBILITY_SEARCH)],
            'maxValuesPerFacet' => 100
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
            'facets' => [],
            'maxValuesPerFacet' => 100
        ], $result);
    }

    public function testBuildWithoutVisibilityFilter(): void
    {
        $request = $this->createMockRequest();
        $boolQuery = $this->createMockBoolQuery();
        $otherQuery = $this->createMock(RequestQueryInterface::class);

        $request->method('getQuery')->willReturn($boolQuery);
        $boolQuery->method('getMust')->willReturn(['other' => $otherQuery]);

        $this->paginationInfo->method('getPageSize')->willReturn(20);
        $this->paginationInfo->method('getPageNumber')->willReturn(1);

        $this->storeIdResolver->method('getStoreId')->willReturn(1);
        $this->instantSearchHelper->method('getFacets')->willReturn([]);

        $result = $this->queryParamBuilder->build($request, $this->paginationInfo);

        $this->assertEquals([
            'hitsPerPage' => 20,
            'page' => 0,
            'facets' => [],
            'maxValuesPerFacet' => 100
        ], $result);
    }

    public function testApplyVisibilityFiltersWithInSearchSingleValue(): void
    {
        $filters = [
            'visibility' => $this->createMockFilterQuery(Visibility::VISIBILITY_IN_SEARCH)
        ];

        $params = [];

        $this->invokeMethod($this->queryParamBuilder, 'applyVisibilityFilters', [&$params, &$filters]);

        $this->assertEquals([
            'numericFilters' => [sprintf('%s=1', ProductRecordFieldsInterface::VISIBILITY_SEARCH)]
        ], $params);

        $this->assertCount(0, $filters, 'Filters should burn down correctly');
    }

    public function testApplyVisibilityFiltersWithInCatalogSingleValue(): void
    {
        $filters = [
            'visibility' => $this->createMockFilterQuery(Visibility::VISIBILITY_IN_CATALOG)
        ];

        $params = [];

        $this->invokeMethod($this->queryParamBuilder, 'applyVisibilityFilters', [&$params, &$filters]);

        $this->assertEquals([
            'numericFilters' => [sprintf('%s=1', ProductRecordFieldsInterface::VISIBILITY_CATALOG)]
        ], $params);

        $this->assertCount(0, $filters, 'Filters should burn down correctly');
    }

    public function testApplyVisibilityFiltersWithBothValuesArray(): void
    {
        $filterQuery = $this->createMockFilterQuery([
            Visibility::VISIBILITY_IN_SEARCH,
            Visibility::VISIBILITY_IN_CATALOG
        ]);

        $filters = ['visibility' => $filterQuery];

        $params = [];

        $this->invokeMethod($this->queryParamBuilder, 'applyVisibilityFilters', [&$params, &$filters]);

        $this->assertEquals([
            'numericFilters' => [
                sprintf('%s=1', ProductRecordFieldsInterface::VISIBILITY_SEARCH),
                sprintf('%s=1', ProductRecordFieldsInterface::VISIBILITY_CATALOG)
            ]
        ], $params);

        $this->assertCount(0, $filters, 'Filters should burn down correctly');
    }

    public function testApplyVisibilityFiltersWithNoVisibilityParam(): void
    {
        $otherQuery = $this->createMock(RequestQueryInterface::class);
        $filters = ['other' => $otherQuery];

        $params = [];

        $this->invokeMethod($this->queryParamBuilder, 'applyVisibilityFilters', [&$params, &$filters]);

        $this->assertEquals([], $params);

        $this->assertCount(1, $filters, 'Filters should not be altered');
    }

    public function testApplyVisibilityFiltersWithFalseVisibilityParam(): void
    {
        $filters = ['visibility' => $this->createMockFilterQuery(false)];

        $params = [];

        $this->invokeMethod($this->queryParamBuilder, 'applyVisibilityFilters', [&$params, &$filters]);

        $this->assertEquals([], $params);

        $this->assertCount(0, $filters, 'Filters should burn down correctly');
    }

    public function testApplyVisibilityFiltersWithArrayNonMatchingValues(): void
    {
        $filterQuery = $this->createMockFilterQuery();
        $termFilter = $this->createMockTermFilter();

        $filterQuery->method('getReference')->willReturn($termFilter);

        $termFilter->method('getType')->willReturn(RequestFilterInterface::TYPE_TERM);
        $termFilter->method('getValue')->willReturn([1, 4]);

        $filters = ['visibility' => $filterQuery];

        $params = [];

        $this->invokeMethod($this->queryParamBuilder, 'applyVisibilityFilters', [&$params, &$filters]);

        $this->assertEquals([], $params);

        $this->assertCount(0, $filters, 'Filters should burn down correctly');
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
        $filters = [
            'category' => $this->createMockFilterQuery('12'),
            'visibility' => $this->createMockFilterQuery(Visibility::VISIBILITY_IN_SEARCH)
        ];

        $params = [];

        $this->invokeMethod($this->queryParamBuilder, 'applyFilters', [&$params, &$filters, 1]);

        $this->assertEquals([
            'facetFilters' => ['categoryIds:12'],
            'numericFilters' => [sprintf('%s=1', ProductRecordFieldsInterface::VISIBILITY_SEARCH)]
        ], $params);

        $this->assertCount(0, $filters, 'Filters should burn down correctly');
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

        $result = $this->invokeMethod($this->queryParamBuilder, 'getFacetsToRetrieve', [$storeId]);

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

        $result = $this->invokeMethod($this->queryParamBuilder, 'getFacetsToRetrieve', [$storeId]);

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

        $result = $this->invokeMethod($this->queryParamBuilder, 'getFacetsToRetrieve', [$storeId]);

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

        $result = $this->invokeMethod($this->queryParamBuilder, 'getFacetsToRetrieve', [$storeId]);

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
            'facets' => $expectedFacets,
            'maxValuesPerFacet' => 100
        ], $result);
    }

    /**
     * @dataProvider facetFiltersDataProvider
     */
    public function testApplyFacetFilters(
        array $filterDefinitions,
        array $configuredFacets,
        array $expectedParams,
        int $expectedRemainingFilters,
        array $facetValueConversions = []
    ): void {
        $storeId = 1;
        $params = [];

        // Build filters from definitions
        $filters = [];
        foreach ($filterDefinitions as $key => $definition) {
            if ($definition['type'] === 'facet') {
                $filters[$key] = $this->createFilterQueryForFacet($definition['field'], $definition['optionId']);
            } elseif ($definition['type'] === 'non-term') {
                $filters[$key] = $this->createNonTermFilter();
            }
        }

        $this->instantSearchHelper->method('getFacets')->with($storeId)->willReturn($configuredFacets);

        // Setup facet value converter expectations
        if (!empty($facetValueConversions)) {
            $valueMap = [];
            foreach ($facetValueConversions as $conversion) {
                $valueMap[] = [$conversion['attribute'], $conversion['optionId'], $conversion['label']];
            }
            $this->facetValueConverter
                ->method('convertOptionIdToLabel')
                ->willReturnMap($valueMap);
        }

        $this->invokeMethod($this->queryParamBuilder, 'applyFacetFilters', [&$params, &$filters, $storeId]);

        $this->assertEquals($expectedParams, $params);
        $this->assertCount($expectedRemainingFilters, $filters, 'Filters should burn down correctly');
    }

    public static function facetFiltersDataProvider(): array
    {
        return [
            'single facet filter - color' => [
                'filterDefinitions' => [
                    'color' => ['type' => 'facet', 'field' => 'color', 'optionId' => 123]
                ],
                'configuredFacets' => [
                    [ReplicaManagerInterface::SORT_KEY_ATTRIBUTE_NAME => 'color']
                ],
                'expectedParams' => [
                    'facetFilters' => ['color:Blue']
                ],
                'expectedRemainingFilters' => 0,
                'facetValueConversions' => [
                    ['attribute' => 'color', 'optionId' => 123, 'label' => 'Blue']
                ]
            ],
            'multiple facet filters' => [
                'filterDefinitions' => [
                    'color' => ['type' => 'facet', 'field' => 'color', 'optionId' => 123],
                    'size' => ['type' => 'facet', 'field' => 'size', 'optionId' => 456],
                    'style' => ['type' => 'facet', 'field' => 'style', 'optionId' => 789]
                ],
                'configuredFacets' => [
                    [ReplicaManagerInterface::SORT_KEY_ATTRIBUTE_NAME => 'color'],
                    [ReplicaManagerInterface::SORT_KEY_ATTRIBUTE_NAME => 'size'],
                    [ReplicaManagerInterface::SORT_KEY_ATTRIBUTE_NAME => 'style']
                ],
                'expectedParams' => [
                    'facetFilters' => ['color:Blue', 'size:Small', 'style:Basic']
                ],
                'expectedRemainingFilters' => 0,
                'facetValueConversions' => [
                    ['attribute' => 'color', 'optionId' => 123, 'label' => 'Blue'],
                    ['attribute' => 'size', 'optionId' => 456, 'label' => 'Small'],
                    ['attribute' => 'style', 'optionId' => 789, 'label' => 'Basic']
                ]
            ],
            'filter not in configured facets' => [
                'filterDefinitions' => [
                    'color' => ['type' => 'facet', 'field' => 'color', 'optionId' => 123],
                    'material' => ['type' => 'facet', 'field' => 'material', 'optionId' => 999]
                ],
                'configuredFacets' => [
                    [ReplicaManagerInterface::SORT_KEY_ATTRIBUTE_NAME => 'color']
                ],
                'expectedParams' => [
                    'facetFilters' => ['color:Blue']
                ],
                'expectedRemainingFilters' => 1,
                'facetValueConversions' => [
                    ['attribute' => 'color', 'optionId' => 123, 'label' => 'Blue']
                ]
            ],
            'empty filters array' => [
                'filterDefinitions' => [],
                'configuredFacets' => [
                    [ReplicaManagerInterface::SORT_KEY_ATTRIBUTE_NAME => 'color']
                ],
                'expectedParams' => [],
                'expectedRemainingFilters' => 0,
                'facetValueConversions' => []
            ],
            'no configured facets' => [
                'filterDefinitions' => [
                    'color' => ['type' => 'facet', 'field' => 'color', 'optionId' => 123]
                ],
                'configuredFacets' => [],
                'expectedParams' => [],
                'expectedRemainingFilters' => 1,
                'facetValueConversions' => []
            ],
            'mixed valid and non-term filters' => [
                'filterDefinitions' => [
                    'color' => ['type' => 'facet', 'field' => 'color', 'optionId' => 123],
                    'other' => ['type' => 'non-term']
                ],
                'configuredFacets' => [
                    [ReplicaManagerInterface::SORT_KEY_ATTRIBUTE_NAME => 'color']
                ],
                'expectedParams' => [
                    'facetFilters' => ['color:Blue']
                ],
                'expectedRemainingFilters' => 1,
                'facetValueConversions' => [
                    ['attribute' => 'color', 'optionId' => 123, 'label' => 'Blue']
                ]
            ]
        ];
    }

    /**
     * Helper method to create a properly structured filter query for facet testing
     */
    private function createFilterQueryForFacet(string $field, int $optionId): MockObject
    {
        $filterQuery = $this->createMock(\Magento\Framework\Search\Request\Query\Filter::class);
        $filterQuery->method('getType')->willReturn(RequestQueryInterface::TYPE_FILTER);

        $termFilter = $this->createMock(\Magento\Framework\Search\Request\Filter\Term::class);
        $termFilter->method('getType')->willReturn(RequestFilterInterface::TYPE_TERM);
        $termFilter->method('getField')->willReturn($field);
        $termFilter->method('getValue')->willReturn($optionId);

        $filterQuery->method('getReference')->willReturn($termFilter);

        return $filterQuery;
    }

    /**
     * Helper method to create a non-term filter for testing
     */
    private function createNonTermFilter(): MockObject
    {
        $filter = $this->createMock(RequestQueryInterface::class);
        $filter->method('getType')->willReturn(RequestQueryInterface::TYPE_MATCH);
        return $filter;
    }

    public function testApplyFacetFiltersWithExistingParams(): void
    {
        $storeId = 1;
        $params = [
            'facetFilters' => ['categoryIds:12']
        ];

        $filters = [
            'color' => $this->createFilterQueryForFacet('color', 123)
        ];

        $this->instantSearchHelper->method('getFacets')->with($storeId)->willReturn([
            [ReplicaManagerInterface::SORT_KEY_ATTRIBUTE_NAME => 'color']
        ]);

        $this->facetValueConverter
            ->method('convertOptionIdToLabel')
            ->with('color', 123)
            ->willReturn('Blue');

        $this->invokeMethod($this->queryParamBuilder, 'applyFacetFilters', [&$params, &$filters, $storeId]);

        $this->assertEquals([
            'facetFilters' => ['categoryIds:12', 'color:Blue']
        ], $params);
        $this->assertCount(0, $filters);
    }

    public function testGetFacetFilterTermWithValidFilter(): void
    {
        $filter = $this->createFilterQueryForFacet('color', 123);

        $result = $this->invokeMethod($this->queryParamBuilder, 'getFacetFilterTerm', [$filter]);

        $this->assertInstanceOf(\Magento\Framework\Search\Request\Filter\Term::class, $result);
        $this->assertEquals('color', $result->getField());
        $this->assertEquals(123, $result->getValue());
    }

    public function testGetFacetFilterTermWithNonFilterType(): void
    {
        $filter = $this->createMock(RequestQueryInterface::class);
        $filter->method('getType')->willReturn(RequestQueryInterface::TYPE_MATCH);

        $result = $this->invokeMethod($this->queryParamBuilder, 'getFacetFilterTerm', [$filter]);

        $this->assertNull($result);
    }

    public function testGetFacetFilterTermWithNonTermReference(): void
    {
        $filterQuery = $this->createMock(\Magento\Framework\Search\Request\Query\Filter::class);
        $filterQuery->method('getType')->willReturn(RequestQueryInterface::TYPE_FILTER);

        $filterQuery->method('getReference')->willReturn($this->createNonTermFilter());

        $result = $this->invokeMethod($this->queryParamBuilder, 'getFacetFilterTerm', [$filterQuery]);

        $this->assertNull($result);
    }

    public function testGetMatchedFacetFound(): void
    {
        $facets = [
            ['attribute' => 'color', 'label' => 'Color'],
            ['attribute' => 'size', 'label' => 'Size']
        ];

        $result = $this->invokeMethod($this->queryParamBuilder, 'getMatchedFacet', [&$facets, 'color']);

        $this->assertEquals(['attribute' => 'color', 'label' => 'Color'], $result);
        $this->assertCount(1, $facets, 'Matched facet should be removed from array');
    }

    public function testGetMatchedFacetNotFound(): void
    {
        $facets = [
            ['attribute' => 'color', 'label' => 'Color']
        ];

        $result = $this->invokeMethod($this->queryParamBuilder, 'getMatchedFacet', [&$facets, 'size']);

        $this->assertNull($result);
        $this->assertCount(1, $facets, 'Facets array should remain unchanged');
    }

    public function testGetMatchedFacetWithRemoveFalse(): void
    {
        $facets = [
            ['attribute' => 'color', 'label' => 'Color']
        ];

        $result = $this->invokeMethod($this->queryParamBuilder, 'getMatchedFacet', [&$facets, 'color', false]);

        $this->assertEquals(['attribute' => 'color', 'label' => 'Color'], $result);
        $this->assertCount(1, $facets, 'Facets array should NOT be modified when remove=false');
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

