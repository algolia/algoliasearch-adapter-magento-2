<?php

namespace Algolia\SearchAdapter\Model\Config\Backend;

use Algolia\AlgoliaSearch\Exceptions\AlgoliaException;
use Algolia\SearchAdapter\Helper\ConfigHelper;

class BackendRenderMode extends \Magento\Framework\App\Config\Value
{
    public const VALIDATION_MSG = 'The Algolia Backend Search engine must be enabled in order to use backend rendering with InstantSearch.';

    public function __construct(
        protected ConfigHelper                                   $configHelper,
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

    /**
     * @throws AlgoliaException
     */
    public function beforeSave(): self
    {
        $value = (int) $this->getValue();

        if ($value && !$this->configHelper->isAlgoliaEngineSelected()) {
            throw new AlgoliaException(
                __(self::VALIDATION_MSG)
            );
        }

        return parent::beforeSave();
    }
}
