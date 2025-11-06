<?php

namespace Algolia\SearchAdapter\Test\Unit\Model\Request;

use Algolia\AlgoliaSearch\Api\Data\IndexOptionsInterface;
use Algolia\AlgoliaSearch\Api\Data\SearchQueryInterface;
use Algolia\AlgoliaSearch\Api\Data\SearchQueryInterfaceFactory;
use Algolia\AlgoliaSearch\Exceptions\AlgoliaException;
use Algolia\AlgoliaSearch\Service\Product\IndexOptionsBuilder;
use Algolia\AlgoliaSearch\Test\TestCase;
use Algolia\SearchAdapter\Api\Data\PaginationInfoInterface;
use Algolia\SearchAdapter\Api\Data\PaginationInfoInterfaceFactory;
use Algolia\SearchAdapter\Api\Data\QueryMapperResultInterface;
use Algolia\SearchAdapter\Api\Data\QueryMapperResultInterfaceFactory;
use Algolia\SearchAdapter\Model\Request\PaginationInfo;
use Algolia\SearchAdapter\Model\Request\QueryMapper;
use Magento\Framework\App\ScopeInterface;
use Magento\Framework\App\ScopeResolverInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Search\Request\Dimension;
use Magento\Framework\Search\Request\Filter\Term;
use Magento\Framework\Search\Request\FilterInterface as RequestFilterInterface;
use Magento\Framework\Search\Request\Query\BoolExpression as BoolQuery;
use Magento\Framework\Search\Request\Query\Filter as FilterQuery;
use Magento\Framework\Search\Request\Query\MatchQuery;
use Magento\Framework\Search\Request\QueryInterface as RequestQueryInterface;
use Magento\Framework\Search\RequestInterface;
use PHPUnit\Framework\MockObject\MockObject;

class QueryMapperTest extends TestCase
{
    private QueryMapper $queryMapper;
    private QueryMapperResultInterfaceFactory|MockObject $queryMapperResultFactory;
    private SearchQueryInterfaceFactory|MockObject $searchQueryFactory;
    private PaginationInfoInterfaceFactory|MockObject $paginationInfoFactory;
    private ScopeResolverInterface|MockObject $scopeResolver;
    private IndexOptionsBuilder|MockObject $indexOptionsBuilder;
    private QueryMapperResultInterface|MockObject $queryMapperResult;
    private SearchQueryInterface|MockObject $searchQuery;
    private PaginationInfoInterface|MockObject $paginationInfo;
    private IndexOptionsInterface|MockObject $indexOptions;
    private ScopeInterface|MockObject $scope;

    protected function setUp(): void
    {
        $this->queryMapperResultFactory = $this->createMock(QueryMapperResultInterfaceFactory::class);
        $this->searchQueryFactory = $this->createMock(SearchQueryInterfaceFactory::class);
        $this->paginationInfoFactory = $this->createPaginationInfoFactoryMock();
        $this->scopeResolver = $this->createMock(ScopeResolverInterface::class);
        $this->indexOptionsBuilder = $this->createMock(IndexOptionsBuilder::class);
        $this->queryMapperResult = $this->createMock(QueryMapperResultInterface::class);
        $this->searchQuery = $this->createMock(SearchQueryInterface::class);
        $this->paginationInfo = $this->createMock(PaginationInfoInterface::class);
        $this->indexOptions = $this->createMock(IndexOptionsInterface::class);
        $this->scope = $this->createMock(ScopeInterface::class);

        $this->queryMapper = new QueryMapper(
            $this->queryMapperResultFactory,
            $this->searchQueryFactory,
            $this->paginationInfoFactory,
            $this->scopeResolver,
            $this->indexOptionsBuilder
        );
    }

    public function testProcessSuccess(): void
    {
        $request = $this->createMockRequest();

        $boolQuery = $this->createMockBoolQuery();
        $request->method('getQuery')->willReturn($boolQuery);

        $matchQuery = $this->createMockMatchQuery();
        $boolQuery->method('getShould')->willReturn(['search' => $matchQuery]);
        $boolQuery->method('getMust')->willReturn(['category' => $this->createMockFilterQuery()]);
        $matchQuery->method('getValue')->willReturn('test search');

        $this->indexOptionsBuilder->method('buildEntityIndexOptions')->with(1)->willReturn($this->indexOptions);

        $this->searchQueryFactory->method('create')->willReturn($this->searchQuery);

        $this->queryMapperResultFactory->method('create')->willReturn($this->queryMapperResult);

        $result = $this->queryMapper->process($request);

        $this->assertSame($this->queryMapperResult, $result);
    }

    public function testProcessThrowsNoSuchEntityException(): void
    {
        $request = $this->createMockRequest('invalid-id');
        $request->method('getQuery')->willReturn($this->createGenericMockQuery());

        $this->scopeResolver->method('getScope')->with('invalid-id')
            ->willThrowException(new NoSuchEntityException(__('Invalid scope')));

        $this->expectException(NoSuchEntityException::class);
        $this->queryMapper->process($request);
    }

    public function testProcessThrowsAlgoliaException(): void
    {
        $request = $this->createMockRequest();
        $request->method('getQuery')->willReturn($this->createGenericMockQuery());

        $this->indexOptionsBuilder->method('buildEntityIndexOptions')->with(1)
            ->willThrowException(new AlgoliaException('Algolia error'));

        $this->expectException(AlgoliaException::class);
        $this->queryMapper->process($request);
    }

    public function testGetStoreId(): void
    {
        $request = $this->createMockRequest(5);

        $result = $this->queryMapper->getStoreId($request);

        $this->assertEquals(5, $result);
    }

    public function testBuildIndexOptions(): void
    {
        $request = $this->createMockRequest();

        $this->indexOptionsBuilder->method('buildEntityIndexOptions')->with(1)->willReturn($this->indexOptions);

        $result = $this->invokeMethod($this->queryMapper, 'buildIndexOptions', [$request]);

        $this->assertSame($this->indexOptions, $result);
    }

    public function testBuildQueryStringWithBoolQuery(): void
    {
        $request = $this->createMockRequest();
        $boolQuery = $this->createMockBoolQuery();
        $matchQuery = $this->createMockMatchQuery();

        $request->method('getQuery')->willReturn($boolQuery);
        $boolQuery->method('getType')->willReturn(RequestQueryInterface::TYPE_BOOL);
        $boolQuery->method('getShould')->willReturn(['search' => $matchQuery]);
        $matchQuery->method('getValue')->willReturn('test search');

        $result = $this->invokeMethod($this->queryMapper, 'buildQueryString', [$request]);

        $this->assertEquals('test search', $result);
    }

    public function testBuildQueryStringWithNonBoolQuery(): void
    {
        $request = $this->createMockRequest();
        $nonBoolQuery = $this->createMock(RequestQueryInterface::class);

        $request->method('getQuery')->willReturn($nonBoolQuery);
        $nonBoolQuery->method('getType')->willReturn(RequestQueryInterface::TYPE_MATCH);

        $result = $this->invokeMethod($this->queryMapper, 'buildQueryString', [$request]);

        $this->assertEquals('', $result);
    }

    public function testGetSearchTermFromBoolQueryWithSearchTerm(): void
    {
        $boolQuery = $this->createMockBoolQuery();
        $matchQuery = $this->createMockMatchQuery();

        $boolQuery->method('getShould')->willReturn(['search' => $matchQuery]);
        $matchQuery->method('getValue')->willReturn('test search');

        $result = $this->invokeMethod($this->queryMapper, 'getSearchTermFromBoolQuery', [$boolQuery]);

        $this->assertEquals('test search', $result);
    }

    public function testGetSearchTermFromBoolQueryWithoutShould(): void
    {
        $boolQuery = $this->createMockBoolQuery();

        $boolQuery->method('getShould')->willReturn(null);

        $result = $this->invokeMethod($this->queryMapper, 'getSearchTermFromBoolQuery', [$boolQuery]);

        $this->assertEquals('', $result);
    }

    public function testGetSearchTermFromBoolQueryWithoutSearchKey(): void
    {
        $boolQuery = $this->createMockBoolQuery();

        $boolQuery->method('getShould')->willReturn(['other' => 'value']);

        $result = $this->invokeMethod($this->queryMapper, 'getSearchTermFromBoolQuery', [$boolQuery]);

        $this->assertEquals('', $result);
    }

    public function testBuildParamsWithCategoryFilter(): void
    {
        $request = $this->createMockRequest();
        $boolQuery = $this->createMockBoolQuery();
        $filterQuery = $this->createMockFilterQuery();
        $termFilter = $this->createMockTermFilter();

        $request->method('getQuery')->willReturn($boolQuery);
        $boolQuery->method('getType')->willReturn(RequestQueryInterface::TYPE_BOOL);
        $boolQuery->method('getMust')->willReturn(['category' => $filterQuery]);

        $filterQuery->method('getType')->willReturn(RequestQueryInterface::TYPE_FILTER);
        $filterQuery->method('getReferenceType')->willReturn(FilterQuery::REFERENCE_FILTER);
        $filterQuery->method('getReference')->willReturn($termFilter);

        $termFilter->method('getType')->willReturn(RequestFilterInterface::TYPE_TERM);
        $termFilter->method('getValue')->willReturn('12');

        $this->paginationInfo->method('getPageSize')->willReturn(20);
        $this->paginationInfo->method('getPageNumber')->willReturn(1);

        $result = $this->invokeMethod($this->queryMapper, 'buildParams', [$request, $this->paginationInfo]);

        $this->assertEquals([
            'hitsPerPage' => 20,
            'page' => 0,
            'facetFilters' => ['categoryIds:12']
        ], $result);
    }

    public function testBuildParamsWithNonBoolQuery(): void
    {
        $request = $this->createMockRequest();
        $nonBoolQuery = $this->createMock(RequestQueryInterface::class);

        $request->method('getQuery')->willReturn($nonBoolQuery);
        $nonBoolQuery->method('getType')->willReturn(RequestQueryInterface::TYPE_MATCH);

        $this->paginationInfo->method('getPageSize')->willReturn(20);
        $this->paginationInfo->method('getPageNumber')->willReturn(1);

        $result = $this->invokeMethod($this->queryMapper, 'buildParams', [$request, $this->paginationInfo]);

        $this->assertEquals([
            'hitsPerPage' => 20,
            'page' => 0
        ], $result);
    }

    public function testBuildParamsWithoutCategoryFilter(): void
    {
        $request = $this->createMockRequest();
        $boolQuery = $this->createMockBoolQuery();

        $request->method('getQuery')->willReturn($boolQuery);
        $boolQuery->method('getType')->willReturn(RequestQueryInterface::TYPE_BOOL);
        $boolQuery->method('getMust')->willReturn(['other' => 'value']);

        $this->paginationInfo->method('getPageSize')->willReturn(20);
        $this->paginationInfo->method('getPageNumber')->willReturn(1);

        $result = $this->invokeMethod($this->queryMapper, 'buildParams', [$request, $this->paginationInfo]);

        $this->assertEquals([
            'hitsPerPage' => 20,
            'page' => 0
        ], $result);
    }

    public function testGetParamWithValidFilter(): void
    {
        $boolQuery = $this->createMockBoolQuery();
        $filterQuery = $this->createMockFilterQuery();
        $termFilter = $this->createMockTermFilter();

        $boolQuery->method('getMust')->willReturn(['category' => $filterQuery]);

        $filterQuery->method('getType')->willReturn(RequestQueryInterface::TYPE_FILTER);
        $filterQuery->method('getReferenceType')->willReturn(FilterQuery::REFERENCE_FILTER);
        $filterQuery->method('getReference')->willReturn($termFilter);

        $termFilter->method('getType')->willReturn(RequestFilterInterface::TYPE_TERM);
        $termFilter->method('getValue')->willReturn('12');

        $result = $this->invokeMethod($this->queryMapper, 'getParam', [$boolQuery, 'category']);

        $this->assertEquals('12', $result);
    }

    public function testGetParamWithMissingKey(): void
    {
        $boolQuery = $this->createMockBoolQuery();

        $boolQuery->method('getMust')->willReturn(['other' => 'value']);

        $result = $this->invokeMethod($this->queryMapper, 'getParam', [$boolQuery, 'category']);

        $this->assertEquals('', $result);
    }

    public function testGetParamWithNonFilterType(): void
    {
        $boolQuery = $this->createMockBoolQuery();
        $nonFilterQuery = $this->createMock(RequestQueryInterface::class);

        $boolQuery->method('getMust')->willReturn(['category' => $nonFilterQuery]);
        $nonFilterQuery->method('getType')->willReturn(RequestQueryInterface::TYPE_MATCH);

        $result = $this->invokeMethod($this->queryMapper, 'getParam', [$boolQuery, 'category']);

        $this->assertEquals('', $result);
    }

    public function testGetParamWithNonReferenceFilter(): void
    {
        $boolQuery = $this->createMockBoolQuery();
        $filterQuery = $this->createMockFilterQuery();

        $boolQuery->method('getMust')->willReturn(['category' => $filterQuery]);

        $filterQuery->method('getType')->willReturn(RequestQueryInterface::TYPE_FILTER);
        $filterQuery->method('getReferenceType')->willReturn('other');

        $result = $this->invokeMethod($this->queryMapper, 'getParam', [$boolQuery, 'category']);

        $this->assertEquals('', $result);
    }

    public function testGetParamWithFalseValue(): void
    {
        $boolQuery = $this->createMockBoolQuery();
        $filterQuery = $this->createMockFilterQuery();
        $termFilter = $this->createMockTermFilter();

        $boolQuery->method('getMust')->willReturn(['category' => $filterQuery]);

        $filterQuery->method('getType')->willReturn(RequestQueryInterface::TYPE_FILTER);
        $filterQuery->method('getReferenceType')->willReturn(FilterQuery::REFERENCE_FILTER);
        $filterQuery->method('getReference')->willReturn($termFilter);

        $termFilter->method('getType')->willReturn(RequestFilterInterface::TYPE_TERM);
        $termFilter->method('getValue')->willReturn(false);

        $result = $this->invokeMethod($this->queryMapper, 'getParam', [$boolQuery, 'category']);

        $this->assertEquals('', $result);
    }

        /**
     * @dataProvider paginationDataProvider
     */
    public function testBuildPaginationInfo(int $size, int $from, int $page): void
    {
        $request = $this->createMockRequest();
        $request->method('getSize')->willReturn($size);
        $request->method('getFrom')->willReturn($from);

        $result = $this->invokeMethod($this->queryMapper, 'buildPaginationInfo', [$request]);

        $this->assertSame($size, $result->getPageSize());
        $this->assertSame($from, $result->getOffset());
        $this->assertSame($page, $result->getPageNumber());
    }

    private function createPaginationInfoFactoryMock(): PaginationInfoInterfaceFactory|MockObject
    {
        $factory = $this->createMock(PaginationInfoInterfaceFactory::class);
        $factory->method('create')
            ->willReturnCallback(function(array $data = []) {
               return new PaginationInfo(
                   $data['pageNumber'],
                   $data['pageSize'],
                   $data['offset']
               );
            });
        return $factory;
    }

    private function createMockRequest(string $storeId = "1"): RequestInterface|MockObject
    {
        $request = $this->createMock(RequestInterface::class);
        $dimension = $this->createMockDimension($storeId);
        $request->method('getDimensions')->willReturn([$dimension]);

        return $request;
    }

    private function createMockDimension(string $storeId = "1"): Dimension|MockObject
    {
        $dimension = $this->createMock(Dimension::class);
        $dimension->method('getValue')->willReturn($storeId);
        $this->scopeResolver->method('getScope')->with($storeId)->willReturn($this->scope);
        $this->scope->method('getId')->willReturn((int) $storeId);
        return $dimension;
    }

    private function createGenericMockQuery(): RequestQueryInterface|MockObject
    {
        return $this->createMock(RequestQueryInterface::class);
    }

    private function createMockBoolQuery(): BoolQuery|MockObject
    {
        $boolQuery = $this->createMock(BoolQuery::class);
        $boolQuery->method('getType')->willReturn(RequestQueryInterface::TYPE_BOOL);
        return $boolQuery;
    }

    private function createMockMatchQuery(): MatchQuery|MockObject
    {
        return $this->createMock(MatchQuery::class);
    }

    private function createMockFilterQuery(): FilterQuery|MockObject
    {
        return $this->createMock(FilterQuery::class);
    }

    private function createMockTermFilter(): Term|MockObject
    {
        return $this->createMock(Term::class);
    }

    public static function paginationDataProvider(): array
    {
        return [
            // Standard page sizes
            [
                'size' => 12,
                'from' => 0,
                'page' => 1
            ],
            [
                'size' => 24,
                'from' => 0,
                'page' => 1
            ],
            [
                'size' => 36,
                'from' => 0,
                'page' => 1
            ],
            [
                'size' => 12,
                'from' => 12,
                'page' => 2
            ],

            [
                'size' => 24,
                'from' => 24,
                'page' => 2
            ],
            [
                'size' => 36,
                'from' => 36,
                'page' => 2
            ],
            [
                'size' => 12,
                'from' => 24,
                'page' => 3
            ],
            [
                'size' => 24,
                'from' => 48,
                'page' => 3
            ],
            [
                'size' => 12,
                'from' => 60,
                'page' => 6
            ],
            [
                'size' => 24,
                'from' => 120,
                'page' => 6
            ],
            // Edge cases - very small page size
            [
                'size' => 1,
                'from' => 0,
                'page' => 1
            ],
            [
                'size' => 1,
                'from' => 5,
                'page' => 6
            ],
            [
                'size' => 1,
                'from' => 10,
                'page' => 11
            ],
            // Edge cases - very large page size
            [
                'size' => 100,
                'from' => 0,
                'page' => 1
            ],
            [
                'size' => 100,
                'from' => 100,
                'page' => 2
            ],
            [
                'size' => 100,
                'from' => 500,
                'page' => 6
            ],
            // Edge cases - non-standard offsets (offsets that don't align perfectly)
            [
                'size' => 12,
                'from' => 13,
                'page' => 2
            ],
            [
                'size' => 20,
                'from' => 25,
                'page' => 2
            ],
            [
                'size' => 24,
                'from' => 50,
                'page' => 3
            ],
            // Edge cases - large offsets
            [
                'size' => 12,
                'from' => 1200,
                'page' => 101
            ],
            [
                'size' => 20,
                'from' => 2000,
                'page' => 101
            ],
            [
                'size' => 24,
                'from' => 2400,
                'page' => 101
            ]
        ];
    }
}
