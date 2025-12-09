<?php

namespace Algolia\SearchAdapter\Test\Unit\Service\Filter;

use Algolia\AlgoliaSearch\Test\TestCase;
use Algolia\SearchAdapter\Service\Filter\AbstractFilterHandler;
use Algolia\SearchAdapter\Test\Traits\QueryTestTrait;
use Magento\Framework\Search\Request\QueryInterface as RequestQueryInterface;

class AbstractFilterHandlerTest extends TestCase
{
    use QueryTestTrait;

    private AbstractFilterHandler $handler;

    protected function setUp(): void
    {
        $this->handler = $this->getMockBuilder(AbstractFilterHandler::class)
            ->disableOriginalConstructor()
            ->getMock();
    }

    public function testGetFilterParamWithValidFilter(): void
    {
        $filters = ['category' => $this->createMockFilterQuery('12')];

        $result = $this->invokeMethod($this->handler, 'getFilterParam', [&$filters, 'category']);

        $this->assertEquals('12', $result);
    }

    public function testGetFilterParamWithMissingKey(): void
    {
        $otherQuery = $this->createMock(RequestQueryInterface::class);
        $filters = ['other' => $otherQuery];

        $result = $this->invokeMethod($this->handler, 'getFilterParam', [&$filters, 'category']);

        $this->assertFalse($result);
    }
    public function testGetFilterParamWithNonReferenceFilter(): void
    {
        $filterQuery = $this->createMockFilterQuery();
        $filterQuery->method('getType')->willReturn(RequestQueryInterface::TYPE_FILTER);
        $filterQuery->method('getReferenceType')->willReturn('other');

        $filters = ['category' => $filterQuery];

        $result = $this->invokeMethod($this->handler, 'getFilterParam', [&$filters, 'category']);

        $this->assertFalse($result);
    }

    public function testGetFilterParamWithFalseValue(): void
    {
        $filters = ['category' => $this->createMockFilterQuery(false)];

        $result = $this->invokeMethod($this->handler, 'getFilterParam', [&$filters, 'category']);

        $this->assertFalse($result);
    }
}
