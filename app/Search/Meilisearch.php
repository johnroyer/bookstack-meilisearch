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
        $collection = collect();

        $entityIdByTypes = [];
        $oder = [];
        foreach ($list as $index => $document) {
            [$type, $id] = explode('-', $document['id']);
            $type = strtolower($type);
            $id = (int) $id;
            $entityIdByTypes[$type][] = $id;
            $order[$type . '-' . $id] = $index;
        }

        $entityProvider = new EntityProvider();
        $visibleResualt = collect();
        foreach($entityIdByTypes as $type => $idList) {
            $modelList = $entityProvider->get($type)
                ->newQuery()
                ->scopes('visible')
                ->whereIn('id', $idList)
                ->get()
                ->toArray();
            $visibleResualt = $visibleResualt->concat($modelList);
        }

        return [
            'total' => $list,
            'count' => $list,
            'has_more' => false,
            'results' => $collection,
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
