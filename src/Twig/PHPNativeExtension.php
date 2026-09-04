<?php

namespace DovStone\Bundle\SymfonyBlogAdminBundleQuantBased\Twig;

use DovStone\Bundle\SymfonyBlogAdminBundleQuantBased\Service\PleaseService;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;
use Twig\Markup;

class PHPNativeExtension extends AbstractExtension
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
        return array(
            new TwigFunction('jsonDecode', array($this, 'jsonDecode')),
            new TwigFunction('arrayfy', array($this, 'arrayfy')),
            new TwigFunction('intfy', array($this, 'intfy')),
            new TwigFunction('floatfy', array($this, 'floatfy')),
            new TwigFunction('md5', array($this, 'md5')),
            new TwigFunction('sha1', array($this, 'sha1')),
            new TwigFunction('strpos', array($this, 'strpos')),
            new TwigFunction('substr', array($this, 'substr')),
            new TwigFunction('is_string', array($this, 'is_string')),
            new TwigFunction('array_merge_values', array($this, 'array_merge_values')),
            new TwigFunction('arr_merge_val', array($this, 'arr_merge_val')),
            new TwigFunction('ceil', array($this, 'ceil')),
            new TwigFunction('shuffle_assoc', array($this, 'shuffle_assoc')),
            new TwigFunction('str_repeat', array($this, 'str_repeat')),
            new TwigFunction('list_files', array($this, 'list_files')),
            new TwigFunction('microtime', array($this, 'microtime')),
            new TwigFunction('crc32', array($this, 'crc32')),
            new TwigFunction('file_get_contents', array($this, 'file_get_contents')),
            new TwigFunction('file_put_contents', array($this, 'file_put_contents')),
            new TwigFunction('sprintf', array($this, 'sprintf')),
        );
    }

    public function shuffle_assoc($array, $limit = null)
    {
        $new = [];
        $keys = array_keys($array ?? []);
        shuffle($keys);
        foreach ($keys as $key) {
            $new[$key] = $array[$key];
        }
        return $limit ? array_slice($new, 0, $limit) : $new;
    }

    public function str_repeat($string, $limit = 0): string
    {
        return new Markup(str_repeat($string, $limit), 'UTF-8');
    }

    public function listFiles(string $path = 'var/cache/', string $orderBy = 'name', string $direction = 'asc'): array
    {
        $files = glob($path . '/*');
        $filesData = [];

        foreach ($files as $filePath) {
            if (is_file($filePath)) {
                $filesData[] = [
                    'name' => basename($filePath),
                    'content' => file_get_contents($filePath),
                    'modified' => filemtime($filePath)
                ];
            }
        }

        usort($filesData, function ($a, $b) use ($orderBy, $direction) {
            $valueA = $a[$orderBy];
            $valueB = $b[$orderBy];

            if ($valueA == $valueB) {
                return 0;
            }

            $result = $valueA <=> $valueB;
            return $direction === 'asc' ? $result : -$result;
        });

        return $filesData;
    }

    public function jsonDecode(string $json, ?bool $associative = null, int $depth = 512, int $flags = 0)
    {
        return json_decode($json, $associative, $depth, $flags);
    }

    public function arrayfy($data)
    {
        return (array) $data;
    }

    public function intfy($val)
    {
        return (int) $val;
    }

    public function floatfy($val)
    {
        return (float) $val;
    }

    public function md5($value = null)
    {
        return md5($value);
    }

    public function sha1($value = null)
    {
        return sha1($value);
    }

    public function strpos($haystack, $needle)
    {
        return strpos($haystack, $needle);
    }

    public function substr($string, $start = 0, $length = null)
    {
        return substr($this->please->serve('string')->getAccentsLess($string), $start, $length);
    }

    public function is_string($var)
    {
        return is_string($var);
    }

    public function array_merge_values($arrays)
    {
        $merged = [];
        if ($arrays) {
            foreach ($arrays as $array) {
                if ($array) {
                    foreach ($array as $arr) {
                        $merged[] = $arr;
                    }
                }
            }
        }
        return $merged;
    }

    public function arr_merge_val($arrays)
    {
        return $this->array_merge_values($arrays);
    }

    public function ceil($value): float
    {
        return ceil($value);
    }

    public function array_fill(int $start_index, int $num, $value): array
    {
        return array_fill($start_index, $num, $value);
    }

    public function microtime($get_as_float = false): string|float
    {
        return microtime($get_as_float);
    }

    public function crc32(string $string): int
    {
        return crc32($string);
    }

    public function file_get_contents(string $filename): string|false
    {
        return file_get_contents($filename);
    }

    public function file_put_contents(string $filename, $data): void
    {
        file_put_contents($filename, $data);
    }

    public function sprintf(string $format, mixed ...$values): string
    {
        return sprintf($format, ...$values);
    }
}
