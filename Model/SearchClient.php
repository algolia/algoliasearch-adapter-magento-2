<?php

declare(strict_types=1);

namespace Algolia\SearchAdapter\Model;

use Algolia\AlgoliaSearch\Api\SearchClient as AlgoliaSearchClient;
use Algolia\AlgoliaSearch\Configuration\SearchConfig;
use Algolia\AlgoliaSearch\Exceptions\AlgoliaException;
use Algolia\AlgoliaSearch\Logger\DiagnosticsLogger;
use Magento\AdvancedSearch\Model\Client\ClientInterface;

class SearchClient implements ClientInterface
{
    public function __construct(
        protected DiagnosticsLogger    $logger,
        protected array                $options = [],
        protected ?AlgoliaSearchClient $client = null,
        protected ?int                 $clientPid = null
    ) {}

    public function getSearchClient(): AlgoliaSearchClient
    {
        $pid = getmypid();
        if ($this->client === null || $this->clientPid !== $pid) {
            $this->client = $this->buildClient();
            $this->clientPid = $pid;
        }
        return $this->client;
    }

    /** Builds client for the search adapter admin config */
    protected function buildClient(): AlgoliaSearchClient
    {
        $config = SearchConfig::create(
            $this->options['applicationId'],
            $this->options['apiKey']
        );

        $config->setConnectTimeout($this->options['connectTimeout']);
        $config->setReadTimeout($this->options['readTimeout']);
        return AlgoliaSearchClient::createWithConfig($config);
    }

    /**
     * Validate connection params for Algolia
     */
    public function testConnection(): bool
    {
        try {
            $indices = $this->getSearchClient()->listIndices();
        } catch (AlgoliaException $e) {
            $context = array_merge($this->options, ['exception' => $e]);
            unset($context['apiKey']); //do not persist API keys to logs
            $this->logger->error("Unable to verify connection to Algolia for backend search.", $context);
            return false;
        }

        return true;
    }

}

