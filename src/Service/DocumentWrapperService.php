<?php

namespace DovStone\Bundle\SymfonyBlogAdminBundleQuantBased\Service;

use ArrayAccess;
use BadMethodCallException;
use Symfony\Component\String\UnicodeString;

class DocumentWrapperService implements ArrayAccess
{
    private array $data = [];

    public function __construct(array $data = [])
    {
        $this->data = $data;
    }

    /**
     * Méthode magique pour les getters/setters
     */
    public function __call(string $method, array $arguments): mixed
    {
        // Gestion des getters
        if (str_starts_with($method, 'get')) {
            $property = lcfirst(substr($method, 3));
            $snakeKey = $this->camelToSnake($property);
            return $this->data[$snakeKey] ?? null;
        }

        // Gestion des setters
        if (str_starts_with($method, 'set')) {
            if (empty($arguments)) {
                throw new BadMethodCallException("Method $method requires a value");
            }
            $property = lcfirst(substr($method, 3));
            $snakeKey = $this->camelToSnake($property);
            $this->data[$snakeKey] = $arguments[0];
            return $this; // Permet le chaînage
        }

        // Gestion des issers/hassers (pour les booléens)
        if (str_starts_with($method, 'is') || str_starts_with($method, 'has')) {
            $prefix = str_starts_with($method, 'is') ? 'is' : 'has';
            $property = lcfirst(substr($method, strlen($prefix)));
            $snakeKey = $this->camelToSnake($property);
            return isset($this->data[$snakeKey]) && $this->data[$snakeKey] === true;
        }

        throw new BadMethodCallException("Method $method not found");
    }

    /**
     * Accès comme propriété d'objet
     */
    public function __get(string $name): mixed
    {
        return $this->data[$name] ?? null;
    }

    /**
     * Définition d'une propriété comme si c'était un objet
     */
    public function __set(string $name, mixed $value): void
    {
        $this->data[$name] = $value;
    }

    /**
     * Vérification si une propriété existe
     */
    public function __isset(string $name): bool
    {
        return isset($this->data[$name]);
    }

    /**
     * Suppression d'une propriété
     */
    public function __unset(string $name): void
    {
        unset($this->data[$name]);
    }

    // ============== ArrayAccess ==============

    public function offsetExists(mixed $offset): bool
    {
        return isset($this->data[$offset]);
    }

    public function offsetGet(mixed $offset): mixed
    {
        return $this->data[$offset] ?? null;
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        if ($offset === null) {
            $this->data[] = $value;
        } else {
            $this->data[$offset] = $value;
        }
    }

    public function offsetUnset(mixed $offset): void
    {
        unset($this->data[$offset]);
    }

    // ============== Méthodes utilitaires ==============

    /**
     * Convertit camelCase en snake_case
     */
    private function camelToSnake(string $input): string
    {
        return strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $input));
    }

    /**
     * Récupère toutes les données sous forme de tableau
     */
    public function toArray(): array
    {
        return $this->data;
    }

    /**
     * Récupère une valeur avec une valeur par défaut
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return $this->data[$key] ?? $default;
    }

    /**
     * Définit une valeur
     */
    public function set(string $key, mixed $value): self
    {
        $this->data[$key] = $value;
        return $this;
    }

    /**
     * Vérifie si une clé existe
     */
    public function has(string $key): bool
    {
        return isset($this->data[$key]);
    }

    /**
     * Supprime une clé
     */
    public function remove(string $key): self
    {
        unset($this->data[$key]);
        return $this;
    }
}
