<?php

namespace Algolia\SearchAdapter\Observer;

use Algolia\SearchAdapter\Helper\ConfigHelper;
use Algolia\SearchAdapter\Model\Source\SortParam;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;

class UpdateConfiguration  implements ObserverInterface
{
    public function __construct(
        protected ConfigHelper $configHelper
    ){}

    public function execute(Observer $observer)
    {
        $configuration = $observer->getData('configuration');

        if (!isset($configuration['sortingParameter'])) {
            $configuration['sortingParameter'] = $this->configHelper->getSortingParameter();
        }

        if (!isset($configuration['pagingParameter'])) {
            $configuration['pagingParameter'] =
                $this->configHelper->getSortingParameter() === SortParam::SORT_PARAM_MAGENTO ?
                    SortParam::PAGE_PARAM_MAGENTO :
                    SortParam::PAGE_PARAM_ALGOLIA;
        }
    }
}
