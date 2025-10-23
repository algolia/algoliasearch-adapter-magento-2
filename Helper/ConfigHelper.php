<?php

namespace Algolia\SearchAdapter\Helper;

use Magento\Framework\Search\EngineResolverInterface;

class ConfigHelper
{
    public const ALGOLIA_ENGINE = "algolia";

    public function __construct(
        protected EngineResolverInterface $engineResolver
    ){}

    /**
     * @return bool
     */
    public function isAlgoliaEngineSelected(): bool
    {
        return $this->engineResolver->getCurrentSearchEngine() === self::ALGOLIA_ENGINE;
    }
}
