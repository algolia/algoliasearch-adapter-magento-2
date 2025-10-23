<?php

declare(strict_types=1);

namespace Algolia\SearchAdapter\Controller\Adminhtml\Search\System\Config;

use Magento\AdvancedSearch\Model\Client\ClientResolver;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Filter\StripTags;

class TestConnection extends Action implements HttpPostActionInterface
{
    /**
     * Authorization level of a basic admin session.
     *
     * @see _isAllowed()
     */
    public const ADMIN_RESOURCE = 'Magento_Catalog::config_catalog';

    public function __construct(
        protected ClientResolver $clientResolver,
        protected JsonFactory    $resultJsonFactory,
        protected StripTags      $tagFilter,
        Context                  $context,
    ) {
        parent::__construct($context);
    }

    /**
     * Check for connection to server
     */
    public function execute(): Json
    {
        $result = [
            'success' => false,
            'errorMessage' => '',
        ];
        $options = $this->getRequest()->getParams();

        try {
            if (empty($options['engine'])) {
                throw new LocalizedException(
                    __('Missing search engine parameter.')
                );
            }
            $response = $this->clientResolver->create($options['engine'], $options)->testConnection();
            if ($response) {
                $result['success'] = true;
            }
        } catch (LocalizedException $e) {
            $result['errorMessage'] = $e->getMessage();
        } catch (\Exception $e) {
            $message = __($e->getMessage());
            $result['errorMessage'] = $this->tagFilter->filter($message);
        }

        $resultJson = $this->resultJsonFactory->create();
        return $resultJson->setData($result);
    }
}
