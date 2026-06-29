<?php

namespace BookStack\Search;

use BookStack\Entities\EntityProvider;
use BookStack\Entities\Models\Entity;
use Illuminate\Support\Collection;
use Meilisearch\Client;

class Meilisearch
{
    private $client;

    private $indexName = '';

    public function __construct(Client $client, string $indexName)
    {
        $this->client = $client;
        $this->indexName = $indexName;
    }

    public function initialize()
    {
        try {
            $index = $this->client->getIndex($this->indexName);

            // delete if exists
            $index->delete();
        } catch (\Exception $exception) {
            // index is not exists
            // do nothing
        }

        $index = $this->client->getIndex($this->indexName);

        // update index attributes
        $index->updateSearchableAttributes(
            $this->getIndexSettings()['searchableAttributes']
        );
        $index->updateDisplayedAttributes(
            $this->getIndexSettings()['displayedAttributes']
        );
    }

    public function addIndex(Entity $entity)
    {
        $entityInfo = $entity->toArray();
        $className = get_class($entity);
        $entityId = $entityInfo['id'];
        $index = $this->client->index($this->indexName);
        $data = $className::find($entityId);

        // create ID for Meilisearch
        // string of ID is "{entity-type}-{entity-id}"
        $id = class_basename($entity) . '-' . $entityInfo['id'];

        // remove exists data first by entity ID
        $index->deleteDocument($id);

        // index entity content
        $doc = [
            'id' => $id,
            'name' => $data->name,
            'description' => $data->description,
            'text' => $data->text,
            'tags' => $data['tag']
        ];
        $index->addDocuments($doc);
    }

    /**
     * @param string $keyword
     * @return array{total: int, count: int, has_more: bool, results: Collection<Entity>}
     * @see "SearchRunner::searchEntities()"
     */
    public function search(string $keyword): array
    {
        $index = $this->client->index($this->indexName);
        $list = $index->search($keyword)->getHits();

        $entityIdByTypes = [];
        $order = [];
        foreach ($list as $index => $document) {
            // convert from meilisearch document ID to entity information
            [$type, $id] = explode('-', $document['id']);
            $type = strtolower($type);
            $id = (int) $id;
            $entityIdByTypes[$type][] = $id;
            // save the order of meilisearch result
            $order[$type . '-' . $id] = $index;
        }

        // Filter out entities that the user does not have permission to view
        $entityProvider = new EntityProvider();
        $visibleResault = collect();
        foreach ($entityIdByTypes as $type => $idList) {
            $modelList = $entityProvider->get($type)
                ->newQuery()
                ->scopes('visible')
                ->whereIn('id', $idList)
                ->get();
            $visibleResault = $visibleResault->concat($modelList);
        }

        // create entity list and order from meilisearch result
        $visibleResault = $visibleResault
            ->sortBy(function ($entity) use ($order) {
                $key = $entity->type . '-' . $entity->id;

                if (array_key_exists($key, $order)) {
                    return $order[$key];
                } else {
                    // entity not in meilisearch result
                    // make it sort to end of the list
                    return PHP_INT_MAX;
                }
            })
            ->values();

        return [
            'total' => $visibleResault->count(),
            'count' => $visibleResault->count(),
            'has_more' => false,
            'results' => $visibleResault,
        ];
    }

    public function getIndexSettings(): array
    {
        /**
         * Meiliserch index attributes
         *
         * while index data is:
         *   [
         *       'id' => 'entity ID',
         *       'content' => 'entity text without HTML'
         *   ]
         */
        return [
            'displayedAttributes' => [
                'id',
                'entityType',
                'entityId',
            ],
            'searchableAttributes' => [
                'name',
                'description',
                'text',
                'tags',
            ],
            'filterableAttributes' => [
            ],
            'sortableAttributes' => [
            ],
        ];
    }
}
