<?php

namespace Algolia\SearchAdapter\Model\Config\Backend;

use Algolia\AlgoliaSearch\Exceptions\AlgoliaException;
use Algolia\SearchAdapter\Helper\ConfigHelper;
use Algolia\SearchAdapter\Service\BackendRenderingResolver;
use Algolia\SearchAdapter\Model\Config\Source\BackendRenderMode as BackendRenderModeSource;
use Magento\Config\Model\ResourceModel\Config\Data\CollectionFactory as ConfigCollectionFactory;

class Engine extends \Magento\Framework\App\Config\Value
{
    public function __construct(
        protected ConfigCollectionFactory                        $configCollectionFactory,
        \Magento\Framework\Model\Context                         $context,
        \Magento\Framework\Registry                              $registry,
        \Magento\Framework\App\Config\ScopeConfigInterface       $config,
        \Magento\Framework\App\Cache\TypeListInterface           $cacheTypeList,
        ?\Magento\Framework\Model\ResourceModel\AbstractResource $resource = null,
        ?\Magento\Framework\Data\Collection\AbstractDb           $resourceCollection = null,
        array                                                    $data = []
    ) {
        return parent::__construct($context, $registry, $config, $cacheTypeList, $resource, $resourceCollection, $data);
    }

    public function beforeSave(): self
    {
        $value = $this->getValue();

        if ($value != ConfigHelper::ALGOLIA_ENGINE && $this->isBackendRenderConfigured()) {
            throw new AlgoliaException(
                __(BackendRenderMode::VALIDATION_MSG)
            );
        }

        return parent::beforeSave();
    }

    /** Check if backend render is configured for any scope - if so the Algolia engine is required */
    protected function isBackendRenderConfigured(): bool
    {
        $collection = $this->configCollectionFactory->create()
            ->addFieldToFilter('path', BackendRenderingResolver::BACKEND_RENDER_MODE)
            ->addFieldToFilter(
                'value',
                ['gt' => BackendRenderModeSource::BACKEND_RENDER_OFF]
            );
        return (bool) $collection->getSize();
    }
}
