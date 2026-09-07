<?php

namespace DovStone\Bundle\SymfonyBlogAdminBundleQuantBased\Twig;

use Twig\Extension\GlobalsInterface;
use Twig\Extension\AbstractExtension;
use DovStone\Bundle\SymfonyBlogAdminBundleQuantBased\Service\PleaseService;

class TwigGlobals extends AbstractExtension implements GlobalsInterface
{
    public $please;

    public function __construct(PleaseService $please)
    {
        $this->please = $please;
    }

    public function getGlobals(): array
    {
        return [
            '_REDIRECT' => $this->please->serve('url')->redirectUrl(),
            'navigation' => $this->please->serve('cache')->getNavigationData()
        ];
    }
}
