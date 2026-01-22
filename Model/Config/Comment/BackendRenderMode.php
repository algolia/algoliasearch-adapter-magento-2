<?php

namespace Algolia\SearchAdapter\Model\Config\Comment;

use Algolia\AlgoliaSearch\Model\Config\AbstractConfigComment;

class BackendRenderMode extends AbstractConfigComment
{
    public function getCommentText($elementValue): string
    {
        $link = $this->getConfigLink(
            'catalog',
            'catalog_search-head'
        );

        return <<<COMMENT
            Backend rendering can be enabled for all users, or selectively for specific user agents
            (for example, search engine bots), while regular shoppers continue to use InstantSearch.
            <br><br>
            <strong>Important:</strong> Enabling backend rendering may result in additional Algolia API calls.
            It is strongly recommended to enable Magento’s Full Page Cache to avoid duplicate requests
            and increased search usage.
            <br><br>
            <aside class="algolia_dashboard_warning">
                <p>
                    Backend rendering requires the Magento search engine to be set to
                    <strong>Algolia Backend Search</strong>.
                </p>
                <p>
                    You can configure this in:
                    <a href="$link">Stores &gt; Configuration &gt; Catalog &gt; Catalog &gt; Catalog Search</a>
                </p>
            </aside>
        COMMENT;
    }
}
