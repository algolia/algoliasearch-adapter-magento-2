<?php

namespace Algolia\SearchAdapter\Test\Unit\Service;

use Algolia\AlgoliaSearch\Api\Product\ProductRecordFieldsInterface;
use Algolia\AlgoliaSearch\Test\TestCase;
use Algolia\SearchAdapter\Api\Data\PaginationInfoInterface;
use Algolia\SearchAdapter\Service\QueryParamBuilder;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Framework\Search\Request\Filter\Term;
use Magento\Framework\Search\Request\FilterInterface as RequestFilterInterface;
use Magento\Framework\Search\Request\Query\BoolExpression as BoolQuery;
use Magento\Framework\Search\Request\Query\Filter as FilterQuery;
use Magento\Framework\Search\Request\QueryInterface as RequestQueryInterface;
use Magento\Framework\Search\RequestInterface;
use PHPUnit\Framework\MockObject\MockObject;

class QueryParamBuilderTest extends TestCase
{
    private QueryParamBuilder $queryParamBuilder;
    private PaginationInfoInterface|MockObject $paginationInfo;

    protected function setUp(): void
    {
        $this->queryParamBuilder = new QueryParamBuilder();
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

        $result = $this->queryParamBuilder->build($request, $this->paginationInfo);

        $this->assertEquals([
            'hitsPerPage' => 20,
            'page' => 0,
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

        $result = $this->queryParamBuilder->build($request, $this->paginationInfo);

        $this->assertEquals([
            'hitsPerPage' => 20,
            'page' => 0
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

        $result = $this->queryParamBuilder->build($request, $this->paginationInfo);

        $this->assertEquals([
            'hitsPerPage' => 20,
            'page' => 0
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

        $result = $this->queryParamBuilder->build($request, $this->paginationInfo);

        $this->assertEquals([
            'hitsPerPage' => 20,
            'page' => 0,
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

        $result = $this->queryParamBuilder->build($request, $this->paginationInfo);

        $this->assertEquals([
            'hitsPerPage' => 20,
            'page' => 0,
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

        $result = $this->queryParamBuilder->build($request, $this->paginationInfo);

        $this->assertEquals([
            'hitsPerPage' => 20,
            'page' => 0,
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

        $result = $this->queryParamBuilder->build($request, $this->paginationInfo);

        $this->assertEquals([
            'hitsPerPage' => 20,
            'page' => 0,
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

        $result = $this->queryParamBuilder->build($request, $this->paginationInfo);

        $this->assertEquals([
            'hitsPerPage' => 20,
            'page' => 0
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

        $result = $this->queryParamBuilder->build($request, $this->paginationInfo);

        $this->assertEquals([
            'hitsPerPage' => 20,
            'page' => 0
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

    private function createMockRequest(): RequestInterface|MockObject
    {
        return $this->createMock(RequestInterface::class);
    }

    private function createMockBoolQuery(): BoolQuery|MockObject
    {
        $boolQuery = $this->createMock(BoolQuery::class);
        $boolQuery->method('getType')->willReturn(RequestQueryInterface::TYPE_BOOL);
        return $boolQuery;
    }

    /** Create mock filter with nested mock term  */
    private function createMockFilterQuery(mixed $value = null): FilterQuery|MockObject
    {
        $filterQuery = $this->createMock(FilterQuery::class);
        if ($value === null) {
            return $filterQuery;
        }
        $filterQuery->method('getType')->willReturn(RequestQueryInterface::TYPE_FILTER);
        $filterQuery->method('getReferenceType')->willReturn(FilterQuery::REFERENCE_FILTER);

        $termFilter = $this->createMockTermFilter($value);
        $filterQuery->method('getReference')->willReturn($termFilter);

        return $filterQuery;
    }

    private function createMockTermFilter(mixed $value = null): Term|MockObject
    {
        $termFilter = $this->createMock(Term::class);
        $termFilter->method('getType')->willReturn(RequestFilterInterface::TYPE_TERM);
        if ($value !== null) {
            $termFilter->method('getValue')->willReturn($value);
        }
        return $termFilter;
    }
}

