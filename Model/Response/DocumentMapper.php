<?php

namespace Algolia\SearchAdapter\Model\Response;

class DocumentMapper
{
    public function buildDocuments(array $searchResponse): array
    {
        $i = 0;
        return array_map(
            function(array $hit) use (&$i) {
                return [
                    'fields' => [
                        '_id' => [ $hit['objectID'] ],
                    ],
                    'score' => null,
                    'sort' => [ ++$i, $hit['objectID'] ]
                ];
            },
            $this->extractHits($searchResponse)
        );
    }

    protected function extractHits(array $searchResponse): array
    {
        $hits = [];
        foreach ($searchResponse['results'] ?? [] as $result) {
            foreach ($result['hits'] ?? [] as $hit) {
                $hits[] = $hit;
            }
        }
        return $hits;
    }
}
