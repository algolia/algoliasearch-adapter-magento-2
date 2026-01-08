<?php

namespace Algolia\SearchAdapter\Test\Unit\Service;

use Algolia\AlgoliaSearch\Test\TestCase;
use Algolia\SearchAdapter\Service\FacetValueConverter;
use Magento\Catalog\Model\ResourceModel\Product as ProductResourceModel;
use Magento\Eav\Api\Data\AttributeOptionInterface;
use Magento\Eav\Model\Entity\Attribute as AttributeModel;
use Magento\Eav\Model\Entity\Attribute\Source\AbstractSource;
use Magento\Eav\Model\ResourceModel\Entity\Attribute\Option\Collection as AttributeOptionCollection;
use Magento\Eav\Model\ResourceModel\Entity\Attribute\Option\CollectionFactory as AttributeOptionCollectionFactory;
use PHPUnit\Framework\MockObject\MockObject;

class FacetValueConverterTest extends TestCase
{
    private ?FacetValueConverter $converter = null;
    private null|(ProductResourceModel&MockObject) $productResourceModel = null;
    private null|(AttributeModel&MockObject) $attributeModel = null;
    private null|(AttributeOptionCollectionFactory&MockObject) $attributeOptionCollectionFactory = null;

    protected function setUp(): void
    {
        $this->productResourceModel = $this->createMock(ProductResourceModel::class);
        $this->attributeModel = $this->createMock(AttributeModel::class);
        $this->attributeOptionCollectionFactory = $this->createMock(AttributeOptionCollectionFactory::class);

        $this->converter = new FacetValueConverter(
            $this->productResourceModel,
            $this->attributeModel,
            $this->attributeOptionCollectionFactory
        );
    }

    // =========================================================================
    // convertLabelToOptionId() Tests
    // =========================================================================

    public function testConvertLabelToOptionIdWithValidAttribute(): void
    {
        $attributeCode = 'color';
        $label = 'Black';
        $expectedOptionId = '101';

        $source = $this->createMock(AbstractSource::class);
        $source->method('getOptionId')->with($label)->willReturn($expectedOptionId);

        $attribute = $this->createMock(AttributeModel::class);
        $attribute->method('usesSource')->willReturn(true);
        $attribute->method('getSource')->willReturn($source);

        $this->productResourceModel
            ->method('getAttribute')
            ->with($attributeCode)
            ->willReturn($attribute);

        $result = $this->converter->convertLabelToOptionId($attributeCode, $label);

        $this->assertEquals($expectedOptionId, $result);
    }

    public function testConvertLabelToOptionIdReturnsEmptyWhenAttributeNotFound(): void
    {
        $this->productResourceModel
            ->method('getAttribute')
            ->with('nonexistent')
            ->willReturn(false);

        $result = $this->converter->convertLabelToOptionId('nonexistent', 'SomeLabel');

        $this->assertEquals('', $result);
    }

    public function testConvertLabelToOptionIdReturnsEmptyWhenAttributeDoesNotUseSource(): void
    {
        $attribute = $this->createMock(AttributeModel::class);
        $attribute->method('usesSource')->willReturn(false);

        $this->productResourceModel
            ->method('getAttribute')
            ->with('text_attribute')
            ->willReturn($attribute);

        $result = $this->converter->convertLabelToOptionId('text_attribute', 'SomeValue');

        $this->assertEquals('', $result);
    }

    public function testConvertLabelToOptionIdReturnsEmptyWhenOptionNotFound(): void
    {
        $source = $this->createMock(AbstractSource::class);
        $source->method('getOptionId')->with('NonexistentLabel')->willReturn(null);

        $attribute = $this->createMock(AttributeModel::class);
        $attribute->method('usesSource')->willReturn(true);
        $attribute->method('getSource')->willReturn($source);

        $this->productResourceModel
            ->method('getAttribute')
            ->with('color')
            ->willReturn($attribute);

        $result = $this->converter->convertLabelToOptionId('color', 'NonexistentLabel');

        $this->assertEquals('', $result);
    }

    // =========================================================================
    // convertOptionIdToLabel() Tests
    // =========================================================================

    public function testConvertOptionIdToLabelWithValidOption(): void
    {
        $attributeCode = 'color';
        $optionId = 101;
        $expectedLabel = 'Black';
        $attributeId = 42;

        $this->attributeModel
            ->method('loadByCode')
            ->with('catalog_product', $attributeCode)
            ->willReturnSelf();
        $this->attributeModel
            ->method('getAttributeId')
            ->willReturn($attributeId);

        $option = $this->createMockOption($optionId, $expectedLabel);
        $collection = $this->createMockCollection($attributeId, $optionId, $option);

        $this->attributeOptionCollectionFactory
            ->method('create')
            ->willReturn($collection);

        $result = $this->converter->convertOptionIdToLabel($attributeCode, $optionId);

        $this->assertEquals($expectedLabel, $result);
    }

    public function testConvertOptionIdToLabelReturnsEmptyWhenAttributeNotFound(): void
    {
        $this->attributeModel
            ->method('loadByCode')
            ->willReturnSelf();
        $this->attributeModel
            ->method('getAttributeId')
            ->willReturn(null);

        $result = $this->converter->convertOptionIdToLabel('nonexistent', 101);

        $this->assertEquals('', $result);
    }

    public function testConvertOptionIdToLabelReturnsEmptyWhenOptionNotFound(): void
    {
        $attributeId = 42;
        $optionId = 0; // nonexistent

        $this->attributeModel
            ->method('loadByCode')
            ->willReturnSelf();
        $this->attributeModel
            ->method('getAttributeId')
            ->willReturn($attributeId);

        $collection = $this->createMockCollection($attributeId, $optionId, null);

        $this->attributeOptionCollectionFactory
            ->method('create')
            ->willReturn($collection);

        $result = $this->converter->convertOptionIdToLabel('color', $optionId);

        $this->assertEquals('', $result);
    }

    public function testConvertOptionIdToLabelReturnsEmptyWhenOptionValueIsEmpty(): void
    {
        $attributeId = 42;
        $optionId = 101;
        $expectedLabel = '';

        $this->attributeModel
            ->method('loadByCode')
            ->willReturnSelf();
        $this->attributeModel
            ->method('getAttributeId')
            ->willReturn($attributeId);

        $option = $this->createMockOption($optionId, $expectedLabel, true, 1);
        $collection = $this->createMockCollection($attributeId, $optionId, $option);

        $this->attributeOptionCollectionFactory
            ->method('create')
            ->willReturn($collection);

        $result = $this->converter->convertOptionIdToLabel('color', $optionId);

        $this->assertEquals($expectedLabel, $result);
    }

    public function testConvertOptionIdToLabelReturnsEmptyWhenOptionValueIsNotSet(): void
    {
        $attributeId = 42;
        $optionId = 888;
        
        $this->attributeModel
            ->method('loadByCode')
            ->willReturnSelf();
        $this->attributeModel
            ->method('getAttributeId')
            ->willReturn($attributeId);

        $option = $this->createMockOption($optionId, null, false, 1);
        $collection = $this->createMockCollection($attributeId, $optionId, $option);

        $this->attributeOptionCollectionFactory
            ->method('create')
            ->willReturn($collection);

        $result = $this->converter->convertOptionIdToLabel('color', $optionId);

        $this->assertEquals('', $result);
    }

    // =========================================================================
    // Helper Methods
    // =========================================================================

    protected function createMockOption(
        string $id,
        ?string $value,
        bool $hasValue = true,
        int $expectedCallsOnValue = 2
    ): AttributeOptionInterface&\ArrayAccess&MockObject
    {
        $option = $this->createMock(\Magento\Eav\Model\Entity\Attribute\Option::class);
        $option->method('getData')->willReturn(['optionId' => $id, 'value' => $value]);
        $option->method('offsetExists')->with('value')->willReturn($hasValue);
        $option->expects($this->exactly($expectedCallsOnValue))->method('offsetGet')->with('value')->willReturn($value);
        return $option;
    }

    protected function createMockCollection(
        int                                                     $attributeId,
        int                                                     $optionId,
        null|(AttributeOptionInterface&\ArrayAccess&MockObject) $option
    ): AttributeOptionCollection&MockObject
    {
        $collection = $this->createMock(AttributeOptionCollection::class);
        $collection->method('setPositionOrder')->willReturnSelf();
        $collection->method('setAttributeFilter')->with($attributeId)->willReturnSelf();
        $collection->method('setIdFilter')->with($optionId)->willReturnSelf();
        $collection->method('setStoreFilter')->willReturnSelf();
        $collection->method('load')->willReturnSelf();

        $collection->method('getFirstItem')->willReturn($option);

        return $collection;
    }
}
