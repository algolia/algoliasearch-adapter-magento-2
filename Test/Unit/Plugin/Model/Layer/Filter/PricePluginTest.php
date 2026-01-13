<?php

namespace Algolia\SearchAdapter\Test\Unit\Plugin\Model\Layer\Filter;

use Algolia\AlgoliaSearch\Test\TestCase;
use Algolia\SearchAdapter\Helper\ConfigHelper;
use Algolia\SearchAdapter\Plugin\Model\Layer\Filter\PricePlugin;
use Algolia\SearchAdapter\Service\Product\MaxPriceProvider;
use Magento\CatalogSearch\Model\Layer\Filter\Price;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;

class PricePluginTest extends TestCase
{
    private ?PricePlugin $plugin = null;
    private null|(ConfigHelper&MockObject) $configHelper = null;
    private null|(StoreManagerInterface&MockObject) $storeManager = null;
    private null|(MaxPriceProvider&MockObject) $maxPriceProvider = null;
    private null|(StoreInterface&MockObject) $store = null;
    private null|(Price&MockObject) $subject = null;
    private null|(HttpRequest&MockObject) $request = null;

    const MAX_PRICE = 500.00;

    protected function setUp(): void
    {
        $this->configHelper = $this->createMock(ConfigHelper::class);
        $this->storeManager = $this->createMock(StoreManagerInterface::class);
        $this->maxPriceProvider = $this->createMock(MaxPriceProvider::class);

        $this->maxPriceProvider->method('getCatalogMaxPrice')->willReturn(self::MAX_PRICE);

        $this->store = $this->createMock(StoreInterface::class);
        $this->subject = $this->createMock(Price::class);
        $this->request = $this->createMock(HttpRequest::class);

        $this->storeManager->method('getStore')->willReturn($this->store);
        $this->store->method('getId')->willReturn(1);

        $this->plugin = new PricePlugin(
            $this->storeManager,
            $this->maxPriceProvider,
            $this->configHelper,
        );
    }

    public function testBeforeApplyReturnRequestWhenSeoFiltersDisabled(): void
    {
        $this->subject->method('getRequestVar')->willReturn('price');
        $this->request->method('getParam')->with('price')->willReturn('40-60');

        $this->configHelper
            ->expects($this->once())
            ->method('areSeoFiltersEnabled')
            ->with(1)
            ->willReturn(false);

        $this->maxPriceProvider
            ->expects($this->never())
            ->method('getCatalogMaxPrice');

        $result = $this->plugin->beforeApply($this->subject, $this->request);

        $this->assertEquals([$this->request], $result);
    }

    public function testBeforeApplyReturnRequestWhenAttributeValueEmpty(): void
    {
        $this->subject->method('getRequestVar')->willReturn('price');
        $this->request->method('getParam')->with('price')->willReturn('');

        $this->configHelper
            ->method('areSeoFiltersEnabled')
            ->willReturn(true);

        $this->maxPriceProvider
            ->expects($this->never())
            ->method('getCatalogMaxPrice');

        $result = $this->plugin->beforeApply($this->subject, $this->request);

        $this->assertEquals([$this->request], $result);
    }

    public function testBeforeApplyReturnRequestWhenAttributeValueNull(): void
    {
        $this->subject->method('getRequestVar')->willReturn('price');
        $this->request->method('getParam')->with('price')->willReturn(null);

        $this->configHelper
            ->method('areSeoFiltersEnabled')
            ->willReturn(true);

        $this->maxPriceProvider
            ->expects($this->never())
            ->method('getCatalogMaxPrice');

        $result = $this->plugin->beforeApply($this->subject, $this->request);

        $this->assertEquals([$this->request], $result);
    }

    /**
     * @dataProvider beforeApplyDefineBoundariesValuesDataProvider
     */
    public function testBeforeApplyDefineBoundariesValues($paramValue, $expectedBoundaries): void
    {
        $this->assertEquals($this->plugin->defineBoundaries($paramValue, 1), $expectedBoundaries);
    }

    static public function beforeApplyDefineBoundariesValuesDataProvider(): array
    {
        return [
            ['paramValue' => "40-60", 'expectedBoundaries' => [40, 60]],
            ['paramValue' => "10-60", 'expectedBoundaries' => [10, 60]],
            ['paramValue' => "-60", 'expectedBoundaries' => [0, 60]],
            ['paramValue' => "40-", 'expectedBoundaries' => [40, self::MAX_PRICE]],
        ];
    }
}

