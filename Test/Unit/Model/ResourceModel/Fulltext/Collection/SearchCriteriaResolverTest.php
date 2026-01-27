<?php

namespace Algolia\SearchAdapter\Test\Unit\Model\ResourceModel\Fulltext\Collection;

use Algolia\AlgoliaSearch\Test\TestCase;
use Algolia\SearchAdapter\Model\ResourceModel\Fulltext\Collection\SearchCriteriaResolver;
use Algolia\SearchAdapter\Registry\SortState;
use Algolia\SearchAdapter\ViewModel\Sorter as SorterViewModel;
use Magento\Framework\Api\Search\SearchCriteria;
use Magento\Framework\Api\Search\SearchCriteriaBuilder;
use Magento\Framework\Api\SortOrder;
use Magento\Framework\Api\SortOrderBuilder;
use PHPUnit\Framework\MockObject\MockObject;

class
SearchCriteriaResolverTest extends TestCase
{
    private ?SearchCriteriaResolver $resolver = null;
    private null|(SearchCriteriaBuilder&MockObject) $searchCriteriaBuilder = null;
    private null|(SortOrderBuilder&MockObject) $sortOrderBuilder = null;
    private null|(SortState&MockObject) $sortState = null;
    private null|(SorterViewModel&MockObject) $sorterViewModel = null;
    private null|(SearchCriteria&MockObject) $searchCriteria = null;

    protected function setUp(): void
    {
        $this->searchCriteriaBuilder = $this->createMock(SearchCriteriaBuilder::class);
        $this->sortOrderBuilder = $this->createMock(SortOrderBuilder::class);
        $this->sortState = $this->createMock(SortState::class);
        $this->sorterViewModel = $this->createMock(SorterViewModel::class);
        $this->searchCriteria = $this->createMock(SearchCriteria::class);

        $this->searchCriteriaBuilder->method('create')->willReturn($this->searchCriteria);
        $this->sorterViewModel->method('getSortParamDelimiter')->willReturn('~');
    }

    protected function createResolver(
        string $searchRequestName = 'quick_search_container',
        int $currentPage = 1,
        int $size = 10,
        ?array $orders = null
    ): SearchCriteriaResolver {
        return new SearchCriteriaResolver(
            $this->searchCriteriaBuilder,
            $this->sortOrderBuilder,
            $this->sortState,
            $this->sorterViewModel,
            $searchRequestName,
            $currentPage,
            $size,
            $orders
        );
    }

    public function testResolveSetsRequestName(): void
    {
        $searchRequestName = 'catalog_view_container';
        $resolver = $this->createResolver(searchRequestName: $searchRequestName);

        $this->searchCriteria
            ->expects($this->once())
            ->method('setRequestName')
            ->with($searchRequestName);

        $resolver->resolve();
    }

    public function testResolveSetsCurrentPageWithZeroOffset(): void
    {
        $currentPage = 3;
        $resolver = $this->createResolver(currentPage: $currentPage);

        $this->searchCriteria
            ->expects($this->once())
            ->method('setCurrentPage')
            ->with($currentPage - 1);

        $resolver->resolve();
    }

    public function testResolveSetsPageSizeWhenProvided(): void
    {
        $size = 25;
        $resolver = $this->createResolver(size: $size);

        $this->searchCriteria
            ->expects($this->once())
            ->method('setPageSize')
            ->with($size);

        $resolver->resolve();
    }

    public function testResolveDoesNotSetPageSizeWhenZero(): void
    {
        $resolver = $this->createResolver(size: 0);

        $this->searchCriteria
            ->expects($this->never())
            ->method('setPageSize');

        $resolver->resolve();
    }

    public function testResolveReturnsSearchCriteria(): void
    {
        $resolver = $this->createResolver();

        $result = $resolver->resolve();

        $this->assertSame($this->searchCriteria, $result);
    }

    public function testResolveWithNoOrdersSetsSortOrdersToEmptyArray(): void
    {
        $resolver = $this->createResolver(orders: null);

        $this->searchCriteria
            ->expects($this->once())
            ->method('setSortOrders')
            ->with([]);

        $this->sortState
            ->expects($this->never())
            ->method('set');

        $resolver->resolve();
    }

    public function testResolveWithEmptyOrdersSetsSortOrdersToEmptyArray(): void
    {
        $resolver = $this->createResolver(orders: []);

        $this->searchCriteria
            ->expects($this->once())
            ->method('setSortOrders')
            ->with([]);

        $this->sortState
            ->expects($this->never())
            ->method('set');

        $resolver->resolve();
    }

    public function testResolveWithValidAlgoliaSortOrder(): void
    {
        $orders = ['price~asc' => 'ASC'];
        $sortOrder = $this->createMock(SortOrder::class);

        $this->sortOrderBuilder
            ->expects($this->once())
            ->method('setField')
            ->with('price')
            ->willReturnSelf();

        $this->sortOrderBuilder
            ->expects($this->once())
            ->method('setDirection')
            ->with(SortOrder::SORT_ASC)
            ->willReturnSelf();

        $this->sortOrderBuilder
            ->expects($this->once())
            ->method('create')
            ->willReturn($sortOrder);

        $this->searchCriteria
            ->expects($this->once())
            ->method('setSortOrders')
            ->with([$sortOrder]);

        $this->sortState
            ->expects($this->once())
            ->method('set')
            ->with($sortOrder);

        $resolver = $this->createResolver(orders: $orders);
        $resolver->resolve();
    }

    public function testResolveWithDescendingSortDirection(): void
    {
        # Core direction value (e.g. `ASC`) is intentionally ignored because of composite param
        $orders = ['created_at~desc' => 'ASC'];
        $sortOrder = $this->createMock(SortOrder::class);

        $this->sortOrderBuilder
            ->expects($this->once())
            ->method('setField')
            ->with('created_at')
            ->willReturnSelf();

        $this->sortOrderBuilder
            ->expects($this->once())
            ->method('setDirection')
            ->with(SortOrder::SORT_DESC)
            ->willReturnSelf();

        $this->sortOrderBuilder
            ->expects($this->once())
            ->method('create')
            ->willReturn($sortOrder);

        $resolver = $this->createResolver(orders: $orders);
        $resolver->resolve();
    }

    public function testResolveFiltersOutNonAlgoliaParams(): void
    {
        $orders = [
            'entity_id' => 'ASC',
        ];

        $this->sortOrderBuilder
            ->expects($this->never())
            ->method('setField');

        $this->searchCriteria
            ->expects($this->once())
            ->method('setSortOrders')
            ->with([]);

        $resolver = $this->createResolver(orders: $orders);
        $resolver->resolve();
    }

    public function testResolveWithMixedOrdersFiltersCorrectly(): void
    {
        $orders = [
            'name~asc' => 'ASC',
            'entity_id' => 'ASC', // Should be filtered
        ];
        $sortOrder = $this->createMock(SortOrder::class);

        $this->sortOrderBuilder
            ->expects($this->once())
            ->method('setField')
            ->with('name')
            ->willReturnSelf();

        $this->sortOrderBuilder
            ->method('setDirection')
            ->willReturnSelf();

        $this->sortOrderBuilder
            ->expects($this->once())
            ->method('create')
            ->willReturn($sortOrder);

        $this->searchCriteria
            ->expects($this->once())
            ->method('setSortOrders')
            ->with([$sortOrder]);

        $resolver = $this->createResolver(orders: $orders);
        $resolver->resolve();
    }

    /**
     * @dataProvider sortDirectionDataProvider
     */
    public function testParseSortParamDirectionHandling(string $direction, string $expectedDirection): void
    {
        $orders = ["price~$direction" => 'ASC']; // direction value ignored
        $sortOrder = $this->createMock(SortOrder::class);

        $this->sortOrderBuilder
            ->method('setField')
            ->willReturnSelf();

        $this->sortOrderBuilder
            ->expects($this->once())
            ->method('setDirection')
            ->with($expectedDirection)
            ->willReturnSelf();

        $this->sortOrderBuilder
            ->method('create')
            ->willReturn($sortOrder);

        $resolver = $this->createResolver(orders: $orders);
        $resolver->resolve();
    }

    public static function sortDirectionDataProvider(): array
    {
        return [
            'lowercase asc' => ['asc', SortOrder::SORT_ASC],
            'uppercase ASC' => ['ASC', SortOrder::SORT_ASC],
            'mixed case AsC' => ['AsC', SortOrder::SORT_ASC],
            'lowercase desc' => ['desc', SortOrder::SORT_DESC],
            'uppercase DESC' => ['DESC', SortOrder::SORT_DESC],
            'mixed case DeSc' => ['DeSc', SortOrder::SORT_DESC],
            'unknown defaults to asc' => ['unknown', SortOrder::SORT_ASC],
        ];
    }

    public function testResolveSetsOnlyFirstSortOrderInSortState(): void
    {
        $orders = [
            'price~asc' => 'ASC',
            'name~desc' => 'ASC', // direction value ignored
        ];

        $sortOrder1 = $this->createMock(SortOrder::class);
        $sortOrder2 = $this->createMock(SortOrder::class);

        $this->sortOrderBuilder
            ->method('setField')
            ->willReturnSelf();

        $this->sortOrderBuilder
            ->method('setDirection')
            ->willReturnSelf();

        $this->sortOrderBuilder
            ->method('create')
            ->willReturnOnConsecutiveCalls($sortOrder1, $sortOrder2);

        $this->sortState
            ->expects($this->once())
            ->method('set')
            ->with($sortOrder1);

        $resolver = $this->createResolver(orders: $orders);
        $resolver->resolve();
    }
}

