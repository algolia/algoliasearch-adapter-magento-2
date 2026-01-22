<?php

namespace Algolia\SearchAdapter\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

class BackendRenderMode implements OptionSourceInterface
{
    public const BACKEND_RENDER_OFF = 0;
    public const BACKEND_RENDER_ON = 1;
    public const BACKEND_RENDER_USER_AGENTS = 2;

    public function toOptionArray(): array
    {
        return [
            [
                'value' => self::BACKEND_RENDER_OFF,
                'label' => __('No'),
            ],
            [
                'value' => self::BACKEND_RENDER_ON,
                'label' => __('Yes (for all users)'),
            ],
            [
                'value' => self::BACKEND_RENDER_USER_AGENTS,
                'label' => __('Yes, for specific User Agents'),
            ]
        ];
    }
}
