<?php

namespace Algolia\SearchAdapter\Test\Unit\Plugin\Model\Layer\Filter;

use Algolia\AlgoliaSearch\Test\TestCase;
use Algolia\SearchAdapter\Helper\ConfigHelper;
use Algolia\SearchAdapter\Plugin\Model\Layer\Filter\AttributePlugin;
use Algolia\SearchAdapter\Service\FacetValueConverter;
use Magento\CatalogSearch\Model\Layer\Filter\Attribute;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;

class AttributePluginTest extends TestCase
{
    private ?AttributePlugin $plugin = null;
    private null|(ConfigHelper&MockObject) $configHelper = null;
    private null|(StoreManagerInterface&MockObject) $storeManager = null;
    private null|(FacetValueConverter&MockObject) $facetValueConverter = null;
    private null|(StoreInterface&MockObject) $store = null;
    private null|(Attribute&MockObject) $subject = null;
    private null|(HttpRequest&MockObject) $request = null;

    protected function setUp(): void
    {
        $this->configHelper = $this->createMock(ConfigHelper::class);
        $this->storeManager = $this->createMock(StoreManagerInterface::class);
        $this->facetValueConverter = $this->createMock(FacetValueConverter::class);
        $this->store = $this->createMock(StoreInterface::class);
        $this->subject = $this->createMock(Attribute::class);
        $this->request = $this->createMock(HttpRequest::class);

        $this->storeManager->method('getStore')->willReturn($this->store);
        $this->store->method('getId')->willReturn(1);

        $this->plugin = new AttributePlugin(
            $this->configHelper,
            $this->storeManager,
            $this->facetValueConverter
        );
    }

    public function testBeforeApplyReturnRequestWhenSeoFiltersDisabled(): void
    {
        $this->subject->method('getRequestVar')->willReturn('color');
        $this->request->method('getParam')->with('color')->willReturn('Red');

        $this->configHelper
            ->expects($this->once())
            ->method('areSeoFiltersEnabled')
            ->with(1)
            ->willReturn(false);

        $this->facetValueConverter
            ->expects($this->never())
            ->method('convertLabelToOptionId');

        $result = $this->plugin->beforeApply($this->subject, $this->request);

        $this->assertEquals([$this->request], $result);
    }

    public function testBeforeApplyReturnRequestWhenAttributeValueEmpty(): void
    {
        $this->subject->method('getRequestVar')->willReturn('color');
        $this->request->method('getParam')->with('color')->willReturn('');

        $this->configHelper
            ->method('areSeoFiltersEnabled')
            ->willReturn(true);

        $this->facetValueConverter
            ->expects($this->never())
            ->method('convertLabelToOptionId');

        $result = $this->plugin->beforeApply($this->subject, $this->request);

        $this->assertEquals([$this->request], $result);
    }

    public function testBeforeApplyReturnRequestWhenAttributeValueNull(): void
    {
        $this->subject->method('getRequestVar')->willReturn('color');
        $this->request->method('getParam')->with('color')->willReturn(null);

        $this->configHelper
            ->method('areSeoFiltersEnabled')
            ->willReturn(true);

        $this->facetValueConverter
            ->expects($this->never())
            ->method('convertLabelToOptionId');

        $result = $this->plugin->beforeApply($this->subject, $this->request);

        $this->assertEquals([$this->request], $result);
    }

    public function testBeforeApplyConvertsLabelToOptionIdWhenSeoFiltersEnabled(): void
    {
        $attributeCode = 'color';
        $labelValue = 'Red';
        $optionId = '49';

        $this->subject->method('getRequestVar')->willReturn($attributeCode);
        $this->request->method('getParam')->with($attributeCode)->willReturn($labelValue);

        $this->configHelper
            ->method('areSeoFiltersEnabled')
            ->willReturn(true);

        $this->facetValueConverter
            ->expects($this->once())
            ->method('convertLabelToOptionId')
            ->with($attributeCode, $labelValue)
            ->willReturn($optionId);

        $this->request
            ->expects($this->once())
            ->method('setParam')
            ->with($attributeCode, $optionId);

        $result = $this->plugin->beforeApply($this->subject, $this->request);

        $this->assertEquals([$this->request], $result);
    }

    public function testBeforeApplyDoesNotSetParamWhenOptionIdEmpty(): void
    {
        $attributeCode = 'color';
        $labelValue = 'NonExistentColor';

        $this->subject->method('getRequestVar')->willReturn($attributeCode);
        $this->request->method('getParam')->with($attributeCode)->willReturn($labelValue);

        $this->configHelper
            ->method('areSeoFiltersEnabled')
            ->willReturn(true);

        $this->facetValueConverter
            ->expects($this->once())
            ->method('convertLabelToOptionId')
            ->with($attributeCode, $labelValue)
            ->willReturn('');

        $this->request
            ->expects($this->never())
            ->method('setParam');

        $result = $this->plugin->beforeApply($this->subject, $this->request);

        $this->assertEquals([$this->request], $result);
    }

    public function testBeforeApplyDoesNotSetParamWhenOptionIdFalsyZero(): void
    {
        $attributeCode = 'size';
        $labelValue = 'InvalidSize';

        $this->subject->method('getRequestVar')->willReturn($attributeCode);
        $this->request->method('getParam')->with($attributeCode)->willReturn($labelValue);

        $this->configHelper
            ->method('areSeoFiltersEnabled')
            ->willReturn(true);

        // Return '0' which is falsy in PHP's if() check
        $this->facetValueConverter
            ->expects($this->once())
            ->method('convertLabelToOptionId')
            ->with($attributeCode, $labelValue)
            ->willReturn('0');

        // '0' is falsy, so setParam should not be called
        $this->request
            ->expects($this->never())
            ->method('setParam');

        $result = $this->plugin->beforeApply($this->subject, $this->request);

        $this->assertEquals([$this->request], $result);
    }

    public function testBeforeApplyWithDifferentStoreId(): void
    {
        $store = $this->createMock(StoreInterface::class);
        $store->method('getId')->willReturn(5);

        $storeManager = $this->createMock(StoreManagerInterface::class);
        $storeManager->method('getStore')->willReturn($store);

        $plugin = new AttributePlugin(
            $this->configHelper,
            $storeManager,
            $this->facetValueConverter
        );

        $request = $this->createMock(HttpRequest::class);
        $this->subject->method('getRequestVar')->willReturn('color');
        $request->method('getParam')->willReturn('Blue');

        $this->configHelper
            ->expects($this->once())
            ->method('areSeoFiltersEnabled')
            ->with(5)
            ->willReturn(false);

        $result = $plugin->beforeApply($this->subject, $request);

        $this->assertEquals([$request], $result);
    }

    public function testBeforeApplyWithSpecialCharactersInLabel(): void
    {
        $attributeCode = 'brand';
        $labelValue = 'Nike & Adidas';
        $optionId = '101';

        $this->subject->method('getRequestVar')->willReturn($attributeCode);
        $this->request->method('getParam')->with($attributeCode)->willReturn($labelValue);

        $this->configHelper
            ->method('areSeoFiltersEnabled')
            ->willReturn(true);

        $this->facetValueConverter
            ->expects($this->once())
            ->method('convertLabelToOptionId')
            ->with($attributeCode, $labelValue)
            ->willReturn($optionId);

        $this->request
            ->expects($this->once())
            ->method('setParam')
            ->with($attributeCode, $optionId);

        $result = $this->plugin->beforeApply($this->subject, $this->request);

        $this->assertEquals([$this->request], $result);
    }

    public function testBeforeApplyWithNumericLabelValue(): void
    {
        $attributeCode = 'category';
        $labelValue = '123';
        $optionId = '456';

        $this->subject->method('getRequestVar')->willReturn($attributeCode);
        $this->request->method('getParam')->with($attributeCode)->willReturn($labelValue);

        $this->configHelper
            ->method('areSeoFiltersEnabled')
            ->willReturn(true);

        $this->facetValueConverter
            ->expects($this->once())
            ->method('convertLabelToOptionId')
            ->with($attributeCode, $labelValue)
            ->willReturn($optionId);

        $this->request
            ->expects($this->once())
            ->method('setParam')
            ->with($attributeCode, $optionId);

        $result = $this->plugin->beforeApply($this->subject, $this->request);

        $this->assertEquals([$this->request], $result);
    }

    public function testBeforeApplyWithZeroAsStringValue(): void
    {
        $attributeCode = 'rating';
        $labelValue = '0'; // 0 could be a conceivable param value
        $optionId = '101';

        $this->subject->method('getRequestVar')->willReturn($attributeCode);
        $this->request->method('getParam')->with($attributeCode)->willReturn($labelValue);

        $this->configHelper
            ->method('areSeoFiltersEnabled')
            ->willReturn(true);

        // Since empty('0') is true, conversion should NOT happen
        $this->facetValueConverter
            ->expects($this->once())
            ->method('convertLabelToOptionId')
            ->with($attributeCode, $labelValue)
            ->willReturn($optionId);

        $this->request
            ->expects($this->once())
            ->method('setParam');

        $result = $this->plugin->beforeApply($this->subject, $this->request);

        $this->assertEquals([$this->request], $result);
    }
}

