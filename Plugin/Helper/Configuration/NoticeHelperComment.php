<?php

namespace Algolia\SearchAdapter\Plugin\Helper\Configuration;

use Algolia\AlgoliaSearch\Helper\Configuration\NoticeHelper;
use Algolia\SearchAdapter\Helper\ConfigHelper;

class NoticeHelperComment
{
    public function __construct(
        protected ConfigHelper $configHelper,
    ){}

    /**
     * @param NoticeHelper $subject
     * @param $result
     * @return array
     */
    public function afterGetExtensionNotices(NoticeHelper $subject, $result): array
    {
        if (!$this->configHelper->isAlgoliaEngineSelected()) {
            return $result;
        }

        $noticeContent = '<tr>
            <td colspan="3">
                <div class="algolia_block blue icon-stars">
                These settings also affect backend-rendered product listings.<br/>
                While originally introduced for InstantSearch, faceting and sorting configurations are shared with backend search when enabled.
                </div>
            </td>
        </tr>';

        $result[] = [
            'selector' => '#row_algoliasearch_instant_instant_is_instant_enabled',
            'method' => 'before',
            'message' => $noticeContent,
        ];

        return $result;
    }
}
