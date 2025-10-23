<?php

namespace Algolia\SearchAdapter\Model;

use Algolia\SearchAdapter\Helper\ConfigHelper;
use Magento\AdvancedSearch\Model\Client\ClientOptionsInterface;

class Config implements ClientOptionsInterface
{
    const ALGOLIA_DEFAULT_TIMEOUT = 300;

    public function __construct(
        protected ConfigHelper  $configHelper
    ) {}

    /**
     * @inheritdoc
     */
    public function prepareClientOptions($options = [])
    {
        $storeId = $options['store'] ?? null;
        $websiteId = $options['website'] ?? null;

        $defaultOptions = [
            'applicationId' => $this->configHelper->getApplicationId($websiteId, $storeId),
            'apiKey' => $this->configHelper->getApiKey($websiteId, $storeId),
            'connectTimeout' => self::ALGOLIA_DEFAULT_TIMEOUT,
            'readTimeout' => self::ALGOLIA_DEFAULT_TIMEOUT
        ];
        $options = array_merge($defaultOptions, $options);
        $allowedOptions = array_merge(array_keys($defaultOptions), ['engine']);

        return array_filter(
            $options,
            function (string $key) use ($allowedOptions) {
                return in_array($key, $allowedOptions);
            },
            ARRAY_FILTER_USE_KEY
        );
    }
}
