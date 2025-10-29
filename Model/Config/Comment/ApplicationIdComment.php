<?php

namespace Algolia\SearchAdapter\Model\Config\Comment;

use \Algolia\AlgoliaSearch\Model\Config\AbstractConfigComment;

class ApplicationIdComment extends AbstractConfigComment
{
    public function getCommentText($elementValue): string
    {
        $link = $this->getConfigLink(
            'algoliasearch_credentials',
            'algoliasearch_credentials_credentials-link',
            true
        );

        return <<<COMMENT
            Algolia's backend search supports multiple application IDs on a single Magento instance.

            <br/><br/>

            To configure credentials for your application id within a given scope, go to
            <a href="$link">Algolia Search > Credentials and Basic Setup</a>

            COMMENT;
    }
}
