<?php

namespace DovStone\Bundle\SymfonyBlogAdminBundleQuantBased\Twig;

use Twig\TwigFunction;
use Quant\Query\Builder;
use Quant\Collection\Collection;
use Twig\Extension\AbstractExtension;
use DovStone\Bundle\SymfonyBlogAdminBundleQuantBased\Service\PleaseService;

class QuantTwigExtension extends AbstractExtension
{
    public $please;
    public $container;

    public function __construct(PleaseService $please)
    {
        $this->please = $please;
        $this->container = $this->please->getContainer();
    }

    public function getFunctions()
    {
        return [
            // Query Builder - minuscule
            new TwigFunction('quant', array($this, 'quant')),
            new TwigFunction('collection', array($this, 'collection')),

            // Query Builder - majuscule (alias)
            new TwigFunction('Quant', array($this, 'quant')),
            new TwigFunction('Collection', array($this, 'collection')),

            // Raw queries
            new TwigFunction('raw_query', array($this, 'rawQuery')),
            new TwigFunction('raws_elect', array($this, 'rawSelect')),
            new TwigFunction('raw_insert', array($this, 'rawInsert')),
            new TwigFunction('rawu_pdate', array($this, 'rawUpdate')),
            new TwigFunction('raw_delete', array($this, 'rawDelete')),

            new TwigFunction('quant_db', array($this, 'quantDb')),
            new TwigFunction('collect', array($this, 'collect')),
            new TwigFunction('now', array($this, 'now')),
            new TwigFunction('like_escape', array($this, 'likeEscape')),

            new TwigFunction('quant_last_updated_at', array($this, 'quantLastUpdatedAt')),
        ];
    }

    /**
     * Crée un Query Builder
     * 
     * @param string $collection Nom de la table
     * @return Builder
     */
    public function quant(string $collection): Builder
    {
        return \quant($collection);
    }

    /**
     * Alias de quant()
     * 
     * @param string $collection Nom de la table
     * @return Builder
     */
    public function collection(string $collection): Builder
    {
        return \collection($collection);
    }

    /**
     * Exécute une requête SQL brute
     * 
     * @param string $sql
     * @param array $bindings
     * @return array|int
     */
    public function rawQuery(string $sql, array $bindings = []): array|int
    {
        return \rawQuery($sql, $bindings);
    }

    /**
     * Exécute une requête SELECT brute
     * 
     * @param string $sql
     * @param array $bindings
     * @return array
     */
    public function rawSelect(string $sql, array $bindings = []): array
    {
        return \rawSelect($sql, $bindings);
    }

    /**
     * Exécute une requête INSERT brute
     * 
     * @param string $sql
     * @param array $bindings
     * @return int
     */
    public function rawInsert(string $sql, array $bindings = []): int
    {
        return \rawInsert($sql, $bindings);
    }

    /**
     * Exécute une requête UPDATE brute
     * 
     * @param string $sql
     * @param array $bindings
     * @return int
     */
    public function rawUpdate(string $sql, array $bindings = []): int
    {
        return \rawUpdate($sql, $bindings);
    }

    /**
     * Exécute une requête DELETE brute
     * 
     * @param string $sql
     * @param array $bindings
     * @return int
     */
    public function rawDelete(string $sql, array $bindings = []): int
    {
        return \rawDelete($sql, $bindings);
    }

    /**
     * Retourne la connexion PDO
     * 
     * @return \PDO
     */
    public function quantDb(): \PDO
    {
        return \quant_db();
    }

    /**
     * Crée une Collection
     * 
     * @param array $items
     * @return Collection
     */
    public function collect(array $items = []): Collection
    {
        return \collect($items);
    }

    /**
     * Retourne la date courante
     * 
     * @param string $format
     * @return string
     */
    public function now(string $format = 'Y-m-d H:i:s'): string
    {
        return \now($format);
    }

    /**
     * Échappe une chaîne pour SQL LIKE
     * 
     * @param string $value
     * @param string $escape
     * @return string
     */
    public function likeEscape(string $value, string $escape = '\\'): string
    {
        return \like_escape($value, $escape);
    }

    /**
     * Récupère la dernière date de mise à jour
     * 
     * @param array $fieldNames Liste des champs (ex: ['user_id', 'author_id'])
     * @param int|array $id ID unique ou liste d'IDs
     * @param string $logic 'AND' ou 'OR'
     * @return string|null
     * 
     * @example
     * // Un seul ID
     * $lastUpdate = quant_last_updated_at(['user_id', 'author_id'], 456);
     * 
     * // Plusieurs IDs
     * $lastUpdate = quant_last_updated_at(['user_id', 'author_id'], [456, 789]);
     */
    function quantLastUpdatedAt(array $fieldNames, int|array $id, string $logic = 'AND'): ?string
    {
        return \quant_last_updated_at($fieldNames, $id, $logic);
    }
}
