<?php

namespace Algolia\SearchAdapter\Block\Adminhtml\System\Config;

class TestConnection extends \Magento\AdvancedSearch\Block\Adminhtml\System\Config\TestConnection
{
    protected function _getFieldMapping(): array
    {
        $fields = [
            'connectTimeout' => 'catalog_search_algolia_connect_timeout',
            'readTimeout' => 'catalog_search_algolia_read_timeout',
        ];

        return array_merge(parent::_getFieldMapping(), $fields);
    }

    /** Augment AJAX requests to include website and store scope */
    protected function _getElementHtml(\Magento\Framework\Data\Form\Element\AbstractElement $element): string
    {
        $request = $this->getRequest();
        $params = [];
        if ($website = $request->getParam('website')) {
            $params['website'] = $website;
        }
        elseif ($store = $request->getParam('store')) {
            $params['store'] = $store;
        }

        $originalData = $element->getOriginalData();
        $this->addData(
            [
                'button_label' => __($originalData['button_label']),
                'html_id' => $element->getHtmlId(),
                'ajax_url' => $this->_urlBuilder->getUrl('catalog/search_system_config/testconnection', $params),
                'field_mapping' => str_replace('"', '\\"', json_encode($this->_getFieldMapping()))
            ]
        );

        return $this->_toHtml();
    }


    public function getButtonLabel(): string
    {
        return 'Test Algolia Connection';
    }
}
