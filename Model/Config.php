<?php

namespace Algolia\SearchAdapter\Model;

use Algolia\SearchAdapter\Helper\ConfigHelper;
use Magento\AdvancedSearch\Model\Client\ClientOptionsInterface;

class Config implements ClientOptionsInterface
{
    const DEFAULT_TIMEOUT_CONNECT = 2;
    const DEFAULT_TIMEOUT_READ = 5;

    public function __construct(
        protected ConfigHelper  $configHelper
    ) {}

    /**
     * @inheritdoc
     */
    public function prepareClientOptions($options = []): array
    {
        $storeId = $options['store'] ?? null;
        $websiteId = $options['website'] ?? null;

        $defaultOptions = [
            'applicationId' => $this->configHelper->getApplicationId($websiteId, $storeId),
            'apiKey' => $this->configHelper->getApiKey($websiteId, $storeId),
            'connectTimeout' => self::DEFAULT_TIMEOUT_CONNECT,
            'readTimeout' => self::DEFAULT_TIMEOUT_READ
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
