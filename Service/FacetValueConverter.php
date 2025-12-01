<?php

namespace Algolia\SearchAdapter\Service;

use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\ResourceModel\Product as ProductResourceModel;
use Magento\Eav\Api\Data\AttributeOptionInterface;
use Magento\Eav\Model\Entity\Attribute as AttributeModel;
use Magento\Eav\Model\ResourceModel\Entity\Attribute\Option\CollectionFactory as AttributeOptionCollectionFactory;
use Magento\Framework\Exception\LocalizedException;

class FacetValueConverter
{
    public function __construct(
        protected ProductResourceModel             $productResourceModel,
        protected AttributeModel                   $attributeModel,
        protected AttributeOptionCollectionFactory $attributeOptionCollectionFactory
    ) {}

    /**
     * @throws LocalizedException
     */
    public function convertLabelToOptionId(string $attributeCode, string $label): string
    {
        $attribute = $this->productResourceModel->getAttribute($attributeCode);

        if (!$attribute || !$attribute->usesSource()) {
            return '';
        }

        return $attribute->getSource()->getOptionId($label) ?? '';
    }

    /**
     * @throws LocalizedException
     */
    public function convertOptionIdToLabel(string $attributeCode, int $value): string
    {
        $attrInfo = $this->attributeModel->loadByCode(Product::ENTITY, $attributeCode);

        if (!$attrInfo->getAttributeId()) {
            return '';
        }

        $option = $this->getAttributeOptionById($attrInfo->getAttributeId(), $value);

        if (!$option || !$option['value'] || !is_array($option->getData())) {
            return '';
        }

        return $option['value'];
    }

    protected function getAttributeOptionById(int $attributeId, int $optionId): ?AttributeOptionInterface
    {
        $attributeOptionCollection = $this->attributeOptionCollectionFactory->create();

        /** @var AttributeOptionInterface|null $item */
        $item = $attributeOptionCollection
            ->setPositionOrder('asc')
            ->setAttributeFilter($attributeId)
            ->setIdFilter($optionId)
            ->setStoreFilter()
            ->load()
            ->getFirstItem();

        return $item;
    }
}
