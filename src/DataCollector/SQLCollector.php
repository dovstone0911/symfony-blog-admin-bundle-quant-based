<?php

namespace DovStone\Bundle\SymfonyBlogAdminBundleQuantBased\DataCollector;

use Symfony\Component\Finder\Finder;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\DataCollector\DataCollector;
use Symfony\Bundle\FrameworkBundle\DataCollector\AbstractDataCollector;
use DovStone\Bundle\SymfonyBlogAdminBundleQuantBased\Service\PleaseService;

class SQLCollector extends AbstractDataCollector
{
    public function __construct(PleaseService $please) {}

    public function collect(Request $request, Response $response, \Throwable $exception = null)
    {
        $dir = dirname(__DIR__, 5) . '/var/cache/sql';
        $items = [];

        if (is_dir($dir)) {
            $finder = new Finder();
            $finder->files()->in($dir);

            foreach ($finder as $file) {
                $content = $file->getContents();
                $json = json_decode($content, true);

                if (json_last_error() === JSON_ERROR_NONE) {
                    $items[] = [
                        'sql' => $json['sql'] ?? '',
                        'bindings' => $json['bindings'] ?? [],
                        'path' => $file->getRelativePathname()
                    ];
                }
            }
        }

        $count = count($items);

        $this->data = [
            'method' => $request->getMethod(),
            'acceptable_content_types' => $request->getAcceptableContentTypes(),
            'User' => $_SESSION['_sf2_attributes']['User'] ?? null,
            'collection' => [
                'title' => $count . " requêtes collectées",
                'count' => $count,
                'items' => $items
            ]
        ];
    }

    public static function getTemplate(): ?string
    {
        return '_sf/collector-sql.html.twig';
    }

    public function getName(): string
    {
        return 'DovStone\Bundle\SymfonyBlogAdminBundleQuantBased\DataCollector\SQLCollector';
    }

    public function getMethod()
    {
        return $this->data['method'];
    }

    public function getAcceptableContentTypes()
    {
        return $this->data['acceptable_content_types'];
    }

    public function getCollection()
    {
        return $this->data['collection'];
    }
}
