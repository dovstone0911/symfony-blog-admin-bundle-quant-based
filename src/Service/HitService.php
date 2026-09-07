<?php

namespace DovStone\Bundle\SymfonyBlogAdminBundleQuantBased\Service;

use DovStone\Bundle\SymfonyBlogAdminBundleQuantBased\Service\PleaseService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class HitService extends AbstractController
{
    public $please;
    public $crud;
    public function __construct(PleaseService $please)
    {
        $this->please = $please;
        $this->crud = $this->please->serve('crud');
    }

    public function setVal($document, string $type, $frequency = 'daily')
    {
        $doc_id = $document['id'] ?? (collection('*')->find($document)['id'] ?? null);

        if ($doc_id) {

            // daily
            $date = date('Y-m-d');
            
            collection('hit')->increment(['val'], [
                'coll_name' => "hit",
                'type' => $type,
                'doc_id' => $doc_id,
                'date' => $date
            ]);

            return $this->getVal($document, $type);
        }

        return 0;
    }

    public function getVal($document, string $type, $frequency = 'all')
    {
        $doc_id = isset($document['id']) ? $document['id'] : attr(collection('*')->find($document), 'id');

        if ($doc_id) {

            // frequency
            //$date = date('Y-m-d');

            return collection('hit')->sumBy('val', [
                ['type', '=', $type],
                ['doc_id', '=', $doc_id],
                //'date' => $date
            ]);
        }
        return 0;
    }

    public function favorite($params)
    {
        $params = array_merge([
            'coll_name' => 'favorite',
            'type' => null,
            'id' => null,
            'user_id' => null,
            'action' => null
        ], $params);

        $type = $params['type'] ?? $this->please->getInput('type');
        $action = $params['action'] ?? $this->please->getInput('action');
        $id = $params['id'];
        $user_id = $params['user_id'] ?? $this->please->serve('security')->getCurrentUser()['id'] ?? null;

        if (!$type || !$id || !$user_id || !$action) {
            return;
        }

        $coll_name = $params['coll_name'];

        // Récupérer les IDs actuels
        $ids = collection($coll_name)->findOneBy([
            'type' => $type,
            'user_id' => $user_id
        ])['ids'] ?? [];
        $ids = is_string($ids) ? json_decode($ids, true) : (array)$ids;

        // Ajouter ou retirer l'ID
        if ($action == 'add') {
            $ids[] = $id;
        } else {
            unset($ids[array_search($id, $ids)]);
        }

        // Nettoyer et vérifier l'existence en BD
        $ids = array_values(array_unique($ids));

        // Vérifier que chaque ID existe encore en BD
        foreach ($ids as $key => $itemId) {
            $exists = collection($type)->find($itemId);
            if (!$exists) {
                unset($ids[$key]);
            }
        }

        // Réindexer après suppression
        $ids = array_values($ids);

        // Stocker
        $idsJson = json_encode($ids);

        $row = collection($coll_name)->updateOrInsert(['type', 'user_id'], [
            'coll_name' => $coll_name,
            'type' => $type,
            'user_id' => $user_id,
            'ids' => $idsJson
        ]);

        return $row;
    }
}
