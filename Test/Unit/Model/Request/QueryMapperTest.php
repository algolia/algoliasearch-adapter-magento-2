<?php

namespace Algolia\SearchAdapter\Test\Unit\Model\Request;

use Algolia\AlgoliaSearch\Api\Data\IndexOptionsInterface;
use Algolia\AlgoliaSearch\Api\Data\SearchQueryInterface;
use Algolia\AlgoliaSearch\Api\Data\SearchQueryInterfaceFactory;
use Algolia\AlgoliaSearch\Exceptions\AlgoliaException;
use Algolia\AlgoliaSearch\Service\Product\IndexOptionsBuilder;
use Algolia\SearchAdapter\Model\Request\QueryMapper;
use Algolia\AlgoliaSearch\Test\TestCase;
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
    private SearchQueryInterfaceFactory|MockObject $searchQueryFactory;
    private ScopeResolverInterface|MockObject $scopeResolver;
    private IndexOptionsBuilder|MockObject $indexOptionsBuilder;
    private SearchQueryInterface|MockObject $searchQuery;
    private IndexOptionsInterface|MockObject $indexOptions;
    private ScopeInterface|MockObject $scope;

    protected function setUp(): void
    {
        $this->searchQueryFactory = $this->createMock(SearchQueryInterfaceFactory::class);
        $this->scopeResolver = $this->createMock(ScopeResolverInterface::class);
        $this->indexOptionsBuilder = $this->createMock(IndexOptionsBuilder::class);
        $this->searchQuery = $this->createMock(SearchQueryInterface::class);
        $this->indexOptions = $this->createMock(IndexOptionsInterface::class);
        $this->scope = $this->createMock(ScopeInterface::class);

        $this->queryMapper = new QueryMapper(
            $this->searchQueryFactory,
            $this->scopeResolver,
            $this->indexOptionsBuilder
        );
    }

    public function testBuildQuerySuccess(): void
    {
        $request = $this->createMockRequest();
        $dimension = $this->createMockDimension();
        $boolQuery = $this->createMockBoolQuery();
        $matchQuery = $this->createMockMatchQuery();

        $request->method('getDimensions')->willReturn([$dimension]);
        $request->method('getQuery')->willReturn($boolQuery);

        $dimension->method('getValue')->willReturn('default');
        $this->scopeResolver->method('getScope')->with('default')->willReturn($this->scope);
        $this->scope->method('getId')->willReturn(1);

        $this->indexOptionsBuilder->method('buildEntityIndexOptions')->with(1)->willReturn($this->indexOptions);

        $boolQuery->method('getType')->willReturn(RequestQueryInterface::TYPE_BOOL);
        $boolQuery->method('getShould')->willReturn(['search' => $matchQuery]);
        $boolQuery->method('getMust')->willReturn(['category' => $this->createMockFilterQuery()]);

        $matchQuery->method('getValue')->willReturn('test search');

        $this->searchQueryFactory->method('create')->willReturn($this->searchQuery);

        $result = $this->queryMapper->buildQuery($request);

        $this->assertSame($this->searchQuery, $result);
    }

    public function testBuildQueryThrowsNoSuchEntityException(): void
    {
        $request = $this->createMockRequest();
        $dimension = $this->createMockDimension();

        $request->method('getDimensions')->willReturn([$dimension]);
        $dimension->method('getValue')->willReturn('invalid');

        $this->scopeResolver->method('getScope')->with('invalid')
            ->willThrowException(new NoSuchEntityException(__('Invalid scope')));

        $this->expectException(NoSuchEntityException::class);
        $this->queryMapper->buildQuery($request);
    }

    public function testBuildQueryThrowsAlgoliaException(): void
    {
        $request = $this->createMockRequest();
        $dimension = $this->createMockDimension();

        $request->method('getDimensions')->willReturn([$dimension]);
        $dimension->method('getValue')->willReturn('default');

        $this->scopeResolver->method('getScope')->with('default')->willReturn($this->scope);
        $this->scope->method('getId')->willReturn(1);

        $this->indexOptionsBuilder->method('buildEntityIndexOptions')->with(1)
            ->willThrowException(new AlgoliaException('Algolia error'));

        $this->expectException(AlgoliaException::class);
        $this->queryMapper->buildQuery($request);
    }

    public function testGetIndexOptions(): void
    {
        $request = $this->createMockRequest();
        $dimension = $this->createMockDimension();

        $request->method('getDimensions')->willReturn([$dimension]);
        $dimension->method('getValue')->willReturn('default');

        $this->scopeResolver->method('getScope')->with('default')->willReturn($this->scope);
        $this->scope->method('getId')->willReturn(1);

        $this->indexOptionsBuilder->method('buildEntityIndexOptions')->with(1)->willReturn($this->indexOptions);

        $result = $this->invokeMethod($this->queryMapper, 'getIndexOptions', [$request]);

        $this->assertSame($this->indexOptions, $result);
    }

    public function testGetQueryWithBoolQuery(): void
    {
        $request = $this->createMockRequest();
        $boolQuery = $this->createMockBoolQuery();
        $matchQuery = $this->createMockMatchQuery();

        $request->method('getQuery')->willReturn($boolQuery);
        $boolQuery->method('getType')->willReturn(RequestQueryInterface::TYPE_BOOL);
        $boolQuery->method('getShould')->willReturn(['search' => $matchQuery]);
        $matchQuery->method('getValue')->willReturn('test search');

        $result = $this->invokeMethod($this->queryMapper, 'getQuery', [$request]);

        $this->assertEquals('test search', $result);
    }

    public function testGetQueryWithNonBoolQuery(): void
    {
        $request = $this->createMockRequest();
        $nonBoolQuery = $this->createMock(RequestQueryInterface::class);

        $request->method('getQuery')->willReturn($nonBoolQuery);
        $nonBoolQuery->method('getType')->willReturn(RequestQueryInterface::TYPE_MATCH);

        $result = $this->invokeMethod($this->queryMapper, 'getQuery', [$request]);

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

    public function testGetParamsWithCategoryFilter(): void
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

        $result = $this->invokeMethod($this->queryMapper, 'getParams', [$request]);

        $this->assertEquals(['facetFilters' => 'categoryIds:12'], $result);
    }

    public function testGetParamsWithNonBoolQuery(): void
    {
        $request = $this->createMockRequest();
        $nonBoolQuery = $this->createMock(RequestQueryInterface::class);

        $request->method('getQuery')->willReturn($nonBoolQuery);
        $nonBoolQuery->method('getType')->willReturn(RequestQueryInterface::TYPE_MATCH);

        $result = $this->invokeMethod($this->queryMapper, 'getParams', [$request]);

        $this->assertEquals([], $result);
    }

    public function testGetParamsWithoutCategoryFilter(): void
    {
        $request = $this->createMockRequest();
        $boolQuery = $this->createMockBoolQuery();

        $request->method('getQuery')->willReturn($boolQuery);
        $boolQuery->method('getType')->willReturn(RequestQueryInterface::TYPE_BOOL);
        $boolQuery->method('getMust')->willReturn(['other' => 'value']);

        $result = $this->invokeMethod($this->queryMapper, 'getParams', [$request]);

        $this->assertEquals([], $result);
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

    private function createMockRequest(): RequestInterface|MockObject
    {
        return $this->createMock(RequestInterface::class);
    }

    private function createMockDimension(): Dimension|MockObject
    {
        return $this->createMock(Dimension::class);
    }

    private function createMockBoolQuery(): BoolQuery|MockObject
    {
        return $this->createMock(BoolQuery::class);
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
}
