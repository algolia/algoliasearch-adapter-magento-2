<?php

namespace Algolia\SearchAdapter\Test\Unit\Model\Request;

use Algolia\AlgoliaSearch\Test\TestCase;
use Algolia\SearchAdapter\Model\Request\PaginationInfo;

class PaginationInfoTest extends TestCase
{
    /**
     * Test that setting page number correctly calculates offset
     *
     * @dataProvider setPageNumberCalculatesOffsetDataProvider
     */
    public function testSetPageNumberCalculatesOffset(int $pageSize, int $pageNumber, int $expectedOffset): void
    {
        $paginationInfo = new PaginationInfo(1, $pageSize, 0);
        $paginationInfo->setPageNumber($pageNumber);

        $this->assertEquals($expectedOffset, $paginationInfo->getOffset());
        $this->assertEquals($pageNumber, $paginationInfo->getPageNumber());
        $this->assertEquals($pageSize, $paginationInfo->getPageSize());
    }

    /**
     * Test that setting page size correctly calculates offset
     *
     * @dataProvider setPageSizeCalculatesOffsetDataProvider
     */
    public function testSetPageSizeCalculatesOffset(int $pageNumber, int $pageSize, int $expectedOffset): void
    {
        $paginationInfo = new PaginationInfo($pageNumber, 9, 0);
        $paginationInfo->setPageSize($pageSize);

        $this->assertEquals($expectedOffset, $paginationInfo->getOffset());
        $this->assertEquals($pageNumber, $paginationInfo->getPageNumber());
        $this->assertEquals($pageSize, $paginationInfo->getPageSize());
    }

    /**
     * Test that setting offset allows correct page number calculation
     *
     * @dataProvider setOffsetCalculatesPageNumberDataProvider
     */
    public function testSetOffsetCalculatesPageNumber(int $pageSize, int $offset, int $expectedPageNumber): void
    {
        $paginationInfo = new PaginationInfo(1, $pageSize, 0);
        $paginationInfo->setOffset($offset);

        $this->assertEquals($expectedPageNumber, $paginationInfo->getPageNumber());
        $this->assertEquals($offset, $paginationInfo->getOffset());
        $this->assertEquals($pageSize, $paginationInfo->getPageSize());
    }

    /**
     * Test that setting both page number and page size correctly calculates offset
     *
     * @dataProvider setPageNumberAndSizeCalculatesOffsetDataProvider
     */
    public function testSetPageNumberAndSizeCalculatesOffset(
        int $pageNumber,
        int $pageSize,
        int $expectedOffset
    ): void {
        $paginationInfo = new PaginationInfo(1, 9, 0);
        $paginationInfo->setPageNumber($pageNumber);
        $paginationInfo->setPageSize($pageSize);

        $this->assertEquals($expectedOffset, $paginationInfo->getOffset());
        $this->assertEquals($pageNumber, $paginationInfo->getPageNumber());
        $this->assertEquals($pageSize, $paginationInfo->getPageSize());
    }

    /**
     * Test that changing page size after setting page number recalculates offset
     *
     * @dataProvider changePageSizeAfterPageNumberDataProvider
     */
    public function testChangePageSizeAfterPageNumber(
        int $pageNumber,
        int $initialPageSize,
        int $newPageSize,
        int $expectedOffset
    ): void {
        $paginationInfo = new PaginationInfo(1, $initialPageSize, 0);
        $paginationInfo->setPageNumber($pageNumber);
        $paginationInfo->setPageSize($newPageSize);

        $this->assertEquals($expectedOffset, $paginationInfo->getOffset());
        $this->assertEquals($pageNumber, $paginationInfo->getPageNumber());
        $this->assertEquals($newPageSize, $paginationInfo->getPageSize());
    }

    /**
     * Test that changing page number after setting page size recalculates offset
     *
     * @dataProvider changePageNumberAfterPageSizeDataProvider
     */
    public function testChangePageNumberAfterPageSize(
        int $pageSize,
        int $initialPageNumber,
        int $newPageNumber,
        int $expectedOffset
    ): void {
        $paginationInfo = new PaginationInfo($initialPageNumber, 9, 0);
        $paginationInfo->setPageSize($pageSize);
        $paginationInfo->setPageNumber($newPageNumber);

        $this->assertEquals($expectedOffset, $paginationInfo->getOffset());
        $this->assertEquals($newPageNumber, $paginationInfo->getPageNumber());
        $this->assertEquals($pageSize, $paginationInfo->getPageSize());
    }

    public static function setPageNumberCalculatesOffsetDataProvider(): array
    {
        return [
            ['pageSize' => 9, 'pageNumber' => 1, 'expectedOffset' => 0],
            ['pageSize' => 9, 'pageNumber' => 2, 'expectedOffset' => 9],
            ['pageSize' => 9, 'pageNumber' => 3, 'expectedOffset' => 18],
            ['pageSize' => 12, 'pageNumber' => 1, 'expectedOffset' => 0],
            ['pageSize' => 12, 'pageNumber' => 2, 'expectedOffset' => 12],
            ['pageSize' => 12, 'pageNumber' => 5, 'expectedOffset' => 48],
            ['pageSize' => 20, 'pageNumber' => 1, 'expectedOffset' => 0],
            ['pageSize' => 20, 'pageNumber' => 2, 'expectedOffset' => 20],
            ['pageSize' => 20, 'pageNumber' => 10, 'expectedOffset' => 180],
            ['pageSize' => 1, 'pageNumber' => 1, 'expectedOffset' => 0],
            ['pageSize' => 1, 'pageNumber' => 5, 'expectedOffset' => 4],
            ['pageSize' => 1, 'pageNumber' => 100, 'expectedOffset' => 99],
            ['pageSize' => 100, 'pageNumber' => 1, 'expectedOffset' => 0],
            ['pageSize' => 100, 'pageNumber' => 2, 'expectedOffset' => 100],
            ['pageSize' => 100, 'pageNumber' => 50, 'expectedOffset' => 4900],
        ];
    }

    public static function setPageSizeCalculatesOffsetDataProvider(): array
    {
        return [
            ['pageNumber' => 1, 'pageSize' => 9, 'expectedOffset' => 0],
            ['pageNumber' => 2, 'pageSize' => 9, 'expectedOffset' => 9],
            ['pageNumber' => 3, 'pageSize' => 9, 'expectedOffset' => 18],
            ['pageNumber' => 1, 'pageSize' => 12, 'expectedOffset' => 0],
            ['pageNumber' => 2, 'pageSize' => 12, 'expectedOffset' => 12],
            ['pageNumber' => 5, 'pageSize' => 12, 'expectedOffset' => 48],
            ['pageNumber' => 1, 'pageSize' => 20, 'expectedOffset' => 0],
            ['pageNumber' => 2, 'pageSize' => 20, 'expectedOffset' => 20],
            ['pageNumber' => 10, 'pageSize' => 20, 'expectedOffset' => 180],
            ['pageNumber' => 1, 'pageSize' => 1, 'expectedOffset' => 0],
            ['pageNumber' => 5, 'pageSize' => 1, 'expectedOffset' => 4],
            ['pageNumber' => 100, 'pageSize' => 1, 'expectedOffset' => 99],
            ['pageNumber' => 1, 'pageSize' => 100, 'expectedOffset' => 0],
            ['pageNumber' => 2, 'pageSize' => 100, 'expectedOffset' => 100],
            ['pageNumber' => 50, 'pageSize' => 100, 'expectedOffset' => 4900],
        ];
    }

    public static function setOffsetCalculatesPageNumberDataProvider(): array
    {
        return [
            ['pageSize' => 9, 'offset' => 0, 'expectedPageNumber' => 1],
            ['pageSize' => 9, 'offset' => 9, 'expectedPageNumber' => 2],
            ['pageSize' => 9, 'offset' => 18, 'expectedPageNumber' => 3],
            ['pageSize' => 12, 'offset' => 0, 'expectedPageNumber' => 1],
            ['pageSize' => 12, 'offset' => 12, 'expectedPageNumber' => 2],
            ['pageSize' => 12, 'offset' => 48, 'expectedPageNumber' => 5],
            ['pageSize' => 20, 'offset' => 0, 'expectedPageNumber' => 1],
            ['pageSize' => 20, 'offset' => 20, 'expectedPageNumber' => 2],
            ['pageSize' => 20, 'offset' => 180, 'expectedPageNumber' => 10],
            ['pageSize' => 1, 'offset' => 0, 'expectedPageNumber' => 1],
            ['pageSize' => 1, 'offset' => 4, 'expectedPageNumber' => 5],
            ['pageSize' => 1, 'offset' => 99, 'expectedPageNumber' => 100],
            ['pageSize' => 100, 'offset' => 0, 'expectedPageNumber' => 1],
            ['pageSize' => 100, 'offset' => 100, 'expectedPageNumber' => 2],
            ['pageSize' => 100, 'offset' => 4900, 'expectedPageNumber' => 50],
            ['pageSize' => 12, 'offset' => 13, 'expectedPageNumber' => 2],
            ['pageSize' => 20, 'offset' => 25, 'expectedPageNumber' => 2],
            ['pageSize' => 24, 'offset' => 50, 'expectedPageNumber' => 3],
            ['pageSize' => 12, 'offset' => 1200, 'expectedPageNumber' => 101],
            ['pageSize' => 20, 'offset' => 2000, 'expectedPageNumber' => 101],
            ['pageSize' => 24, 'offset' => 2400, 'expectedPageNumber' => 101],
        ];
    }

    public static function setPageNumberAndSizeCalculatesOffsetDataProvider(): array
    {
        return [
            ['pageNumber' => 1, 'pageSize' => 9, 'expectedOffset' => 0],
            ['pageNumber' => 2, 'pageSize' => 9, 'expectedOffset' => 9],
            ['pageNumber' => 3, 'pageSize' => 12, 'expectedOffset' => 24],
            ['pageNumber' => 5, 'pageSize' => 20, 'expectedOffset' => 80],
            ['pageNumber' => 10, 'pageSize' => 25, 'expectedOffset' => 225],
            ['pageNumber' => 1, 'pageSize' => 1, 'expectedOffset' => 0],
            ['pageNumber' => 100, 'pageSize' => 1, 'expectedOffset' => 99],
            ['pageNumber' => 1, 'pageSize' => 100, 'expectedOffset' => 0],
            ['pageNumber' => 50, 'pageSize' => 100, 'expectedOffset' => 4900],
        ];
    }

    public static function changePageSizeAfterPageNumberDataProvider(): array
    {
        return [
            ['pageNumber' => 2, 'initialPageSize' => 9, 'newPageSize' => 12, 'expectedOffset' => 12],
            ['pageNumber' => 3, 'initialPageSize' => 9, 'newPageSize' => 20, 'expectedOffset' => 40],
            ['pageNumber' => 5, 'initialPageSize' => 12, 'newPageSize' => 24, 'expectedOffset' => 96],
            ['pageNumber' => 10, 'initialPageSize' => 20, 'newPageSize' => 10, 'expectedOffset' => 90],
        ];
    }

    public static function changePageNumberAfterPageSizeDataProvider(): array
    {
        return [
            ['pageSize' => 12, 'initialPageNumber' => 1, 'newPageNumber' => 2, 'expectedOffset' => 12],
            ['pageSize' => 20, 'initialPageNumber' => 1, 'newPageNumber' => 3, 'expectedOffset' => 40],
            ['pageSize' => 24, 'initialPageNumber' => 1, 'newPageNumber' => 5, 'expectedOffset' => 96],
            ['pageSize' => 10, 'initialPageNumber' => 1, 'newPageNumber' => 10, 'expectedOffset' => 90],
        ];
    }
}

