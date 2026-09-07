<?php

namespace DovStone\Bundle\SymfonyBlogAdminBundleQuantBased\DataCollector;

use DovStone\Bundle\SymfonyBlogAdminBundleQuantBased\Service\PleaseService;
use Symfony\Bundle\FrameworkBundle\DataCollector\AbstractDataCollector;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\DataCollector\DataCollector;

class RequestCollector extends AbstractDataCollector
{
    public function __construct(PleaseService $please) {}

    public function collect(Request $request, Response $response, \Throwable $exception = null)
    {
        $this->data = [
            'method' => $request->getMethod(),
            'acceptable_content_types' => $request->getAcceptableContentTypes(),
            'User' => $_SESSION['_sf2_attributes']['User'] ?? null
        ];
    }

    public static function getTemplate(): ?string
    {
        return '_sf/template.html.twig';
    }

    public function getName(): string
    {
        return 'DovStone\Bundle\SymfonyBlogAdminBundleQuantBased\DataCollector\RequestCollector';
    }

    public function getMethod()
    {
        return $this->data['method'];
    }

    public function getAcceptableContentTypes()
    {
        return $this->data['acceptable_content_types'];
    }

    public function getUser()
    {
        return $this->data['User'];
    }
}
