<?php

namespace DovStone\Bundle\SymfonyBlogAdminBundleQuantBased\Service;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use DovStone\Bundle\SymfonyBlogAdminBundleQuantBased\Service\PleaseService;

class SimpleCacheService extends AbstractController
{
    public $please;
    public $bRepo;
    public $uRepo;
    public $crud;
    public $sql;
    public $user;
    public $user_id;

    private $cacheDir;
    private $defaultCacheTime;

    public function __construct(PleaseService $please)
    {
        $this->please = $please;
    }

    public function setEnv(string $cacheDir = 'var/cache/simple', int $defaultCacheTime = 3600)
    {
        $this->cacheDir = $this->please->serve('dir')->getProjectPath(rtrim($cacheDir, '/') . '/') . '/';
        $this->defaultCacheTime = $defaultCacheTime; // Temps par défaut en secondes
        // Créer le répertoire de cache s'il n'existe pas
        if (!is_dir($this->cacheDir)) {
            mkdir($this->cacheDir, 0777, true);
        }
    }

    // Fonction pour générer une clé de cache unique
    private function getCacheKey($key)
    {
        $this->setEnv();
        return md5($key) . '.cache';
    }

    // Vérifier si le cache est encore valide
    private function isCacheValid($cacheFile, $ttl)
    {
        return file_exists($cacheFile) && (filemtime($cacheFile) + $ttl > time());
    }

    // Récupérer les données du cache si elles sont valides
    public function getData($key, $ttl = null)
    {
        $this->setEnv();
        $ttl = $ttl ?? $this->defaultCacheTime; // Si TTL spécifique non fourni, utiliser la valeur par défaut
        $cacheFile = $this->cacheDir . $this->getCacheKey($key);
        if ($this->isCacheValid($cacheFile, $ttl)) {
            // Lire et retourner les données mises en cache
            return unserialize(file_get_contents($cacheFile));
        }
        return false; // Cache expiré ou introuvable
    }

    // Enregistrer les données dans le cache
    public function setData($key, $data, $ttl = null)
    {
        $this->setEnv();
        $ttl = $ttl ?? $this->defaultCacheTime; // Si TTL spécifique non fourni, utiliser la valeur par défaut
        $cacheFile = $this->cacheDir . $this->getCacheKey($key);
        // Sauvegarder les données sérialisées dans le fichier de cache
        file_put_contents($cacheFile, serialize($data));
    }

    // Supprimer le cache pour une clé spécifique
    public function deleteData($key)
    {
        $this->setEnv();
        $cacheFile = $this->cacheDir . $this->getCacheKey($key);
        if (file_exists($cacheFile)) {
            unlink($cacheFile); // Supprimer le fichier de cache
        }
    }

    // Supprimer tous les fichiers de cache
    public function clear()
    {
        $this->setEnv();
        $files = glob($this->cacheDir . '*.cache');
        foreach ($files as $file) {
            unlink($file);
        }
    }
}
