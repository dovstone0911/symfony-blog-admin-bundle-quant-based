<?php

namespace DovStone\Bundle\SymfonyBlogAdminBundleQuantBased\Service;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use DovStone\Bundle\SymfonyBlogAdminBundleQuantBased\Service\PleaseService;

class ValidPropsService extends AbstractController
{
    public $please;

    private static $tableColumns = [];

    private static $excludedColumns = [
        'roles',
        'role',
        'password',
        'old_password',
        'username',
        'username_slugged',
        'forgot_token',
        'mle',
        'lastname',
        'firstname',
        'telephone',
        'email',
        'activated',
        'user_id',
        'created_at',
        'updated_at',
        'title',
        'parent_id',
        'second_title',
        'third_title',
        'message',
        'post_id',
        'href',
        'doc_id',
        'date',
        'val',
        'meta',
        'job',
        'status',
        'url',
        'val',
        'disk_folder',
        'priority',
        'description',
        'custom_href',
        'slug',
        'keywords',
        'name',
        'link_type',
        'acf_type',
        'layout',
        'layout_single',
        'in_menu',
        'published',
        'allow_comments',
        'extra_data',
        'id',
        'uid',
        'back_layout',
        'icon',
        'fields_list',
        'create_via_drawer',
        'only_title',
        'single_doc',
        'coll_name',
        'parent',
        'front_layout',
        'name_alias',
        'rank',
        'acf_parent_id',
        'acf',
        'image_id',
        'input_name',
        'ids',
        'human_type',
        'folder_id',
        'human_type',
        'params',
        'type',
        'action',
        'controller',
        'route_params',
        'document_id',
        'document',
        'line',
        'data',
        'post',
        'file',
        'previous',
        'path',
        'enabled',
        'granted',
        'authenticated_at',
        'preferred_theme',
        'reservedProps',
        'textOverlay',
        'ident',
        'stay_connected',
        'theme',
        'hide_slug',
        'titles_alias',
        'date_of_birth',
        'city_of_birth',
        'profile_photo'
    ];

    public function __construct(PleaseService $please)
    {
        $this->please = $please;
    }

    /**
     * Retourne les colonnes d'une table spécifique
     * 
     * @param string $tableName Nom de la table
     * @return array Les colonnes de la table ou tableau vide si la table n'existe pas
     */
    public function getColumnsForTable(string $tableName): array
    {
        return self::$tableColumns[$tableName] ?? [];
    }

    /**
     * Liste toutes les tables disponibles
     * 
     * @return array Liste des tables
     */
    public function getAllTables(): array
    {
        return array_keys(self::$tableColumns);
    }

    /**
     * Vérifie si une table existe
     * 
     * @param string $tableName Nom de la table
     * @return bool
     */
    public function tableExists(string $tableName): bool
    {
        return isset(self::$tableColumns[$tableName]);
    }

    /**
     * Vérifie si une colonne existe dans une table
     * 
     * @param string $tableName Nom de la table
     * @param string $columnName Nom de la colonne
     * @return bool
     */
    public function columnExists(string $tableName, string $columnName): bool
    {
        if (!isset(self::$tableColumns[$tableName])) {
            return false;
        }

        return in_array($columnName, self::$tableColumns[$tableName]);
    }

    /**
     * Récupère toutes les colonnes de toutes les tables
     * 
     * @return array Structure complète [table => [colonne1, colonne2, ...]]
     */
    public function getAllColumns(): array
    {
        return self::$tableColumns;
    }

    /**
     * Récupère le nombre de tables
     * 
     * @return int
     */
    public function getTablesCount(): int
    {
        return count(self::$tableColumns);
    }

    /**
     * Récupère le nombre total de colonnes
     * 
     * @return int
     */
    public function getTotalColumnsCount(): int
    {
        $total = 0;
        foreach (self::$tableColumns as $columns) {
            $total += count($columns);
        }
        return $total;
    }

    /**
     * Récupère les tables qui contiennent une colonne spécifique
     * 
     * @param string $columnName Nom de la colonne à rechercher
     * @return array Liste des tables contenant cette colonne
     */
    public function getTablesContainingColumn(string $columnName): array
    {
        $tables = [];
        foreach (self::$tableColumns as $table => $columns) {
            if (in_array($columnName, $columns)) {
                $tables[] = $table;
            }
        }
        return $tables;
    }

    /**
     * Récupère la liste des champs exclus de la base de données
     * 
     * @return array Liste des colonnes exclues
     */
    public function getExcludedColumns(): array
    {
        return self::$excludedColumns;
    }

    /**
     * Vérifie si une colonne est exclue
     * 
     * @param string $columnName Nom de la colonne à vérifier
     * @return bool
     */
    public function isColumnExcluded(string $columnName): bool
    {
        return in_array(strtolower($columnName), array_map('strtolower', self::$excludedColumns));
    }

    /**
     * Récupère les métadonnées de génération
     * 
     * @return array
     */
    public function getMetadata(): array
    {
        return [
            'database' => '24_matrimoon',
            'generated_at' => '2026-01-15 14:04:20',
            'tables_count' => 17,
            'columns_count' => 75,
            'excluded_columns_count' => 86,
        ];
    }
}

// Documentation
// Base de données : 24_matrimoon
// Généré le : 2026-01-15 14:04:20
// Tables traitées : 17
// Colonnes totales : 75
// Colonnes exclues : 86
