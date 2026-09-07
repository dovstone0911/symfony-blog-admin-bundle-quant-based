<?php

namespace DovStone\Bundle\SymfonyBlogAdminBundleQuantBased\Controller;

use DovStone\Bundle\SymfonyBlogAdminBundleQuantBased\Service\PleaseService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/')]
class BundleSitemapController extends AbstractController
{
    public $please;

    public function __construct(PleaseService $please)
    {
        $this->please = $please;
        $this->please->serve('execute_before')->executeBefore();
    }

    #[Route('robots.txt', name: '_robots')]
    public function _robots()
    {
        return $this->_render('robots', 'plain');
    }

    #[Route('{slug}.xml', name: '_xml', requirements: ['slug' => '[a-z0-9-/]+'])]
    public function _xml($slug)
    {
        return $this->please->serve('crud')->read([
            'collections' => 'bloggies',
            'finder' => function () use ($slug) {
                $slug = str_ireplace('-sitemap', '', $slug);
                return ($slug == 'sitemap') ? true : collection('bloggies')->findOneBy(['slug' => $slug]);
            },
            'onNotFound' => function () {
                return $this->redirect($this->please->serve('url')->getHome());
            },
            'onFound' => function () {
                return $this->_render();
            }
        ]);
    }

    private function _render($filename = 'xml', $type = 'xml')
    {
        $content = $this->please->serve('template')->sanitizeView(
            $this->renderView("@DovStoneSymfonyBlogAdminBundleMoSQLBased/sitemap/$filename.html.twig")
        );

        $textResponse = new Response($content, 200);
        $textResponse->headers->set('Content-Type', "text/$type");

        return $textResponse;
    }
}
