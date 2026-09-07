<?php

namespace DovStone\Bundle\SymfonyBlogAdminBundleQuantBased\Service;

use DovStone\Bundle\SymfonyBlogAdminBundleQuantBased\Service\PleaseService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Filesystem\Filesystem;

class ShortcodeService extends AbstractController
{
    public $please;
    public $filesystem;
    public $shortCodeString;

    public function __construct(PleaseService $please)
    {
        $this->please = $please;
        //
        $this->filesystem = new Filesystem();
    }
    
    /* called with templateService->sanitizeView */
    public function sanitizeShortcodes($shortcode)
    {
        $name = '';

        $splitted = preg_split('/\s+/', $shortcode);

        // name
        if( $splitted ){
            $name = $splitted[0];
        }

        $params = [];
        // params
        preg_match_all("/([a-z_A-Z]+)(=)(\")([\s\p{L}0-9_-]+)(\")/ui", trim(str_ireplace($name, '', $shortcode)), $matches1, PREG_PATTERN_ORDER);
        if( $matches1 ){
            foreach ($matches1[0] as $matchString) {
                preg_match_all("/([a-z_A-Z]+)(=)(\")([\s\p{L}0-9_-]+)(\")/ui", $matchString, $matches2, PREG_PATTERN_ORDER);
                if( $matches2 ){
                    if( 
                        (isset($matches2[1]) && isset($matches2[1][0]))
                        &&
                        (isset($matches2[4]) && isset($matches2[4][0]))
                        ){
                            $params[ $matches2[1][0] ] = $matches2[4][0];
                        }
                    }
                }
        }

        $this->register($name, $shortcode, $params);

        return $this->getRendered($name);
    }

    /* called with App\Service\HookService->__registerShortsCodes */
    public function process($name, callable $callback)
    {   
        $shortCodeKey = '__shortCode__'.$name;
        if( isset($this->$shortCodeKey) && is_string($rendered=$callback($this->$shortCodeKey)) ){
            $this->$shortCodeKey = $rendered;
        }
    }

    /* called with App\Service\HookService->__registerShortsCodes */
    public function getFile($file, $params=[])
    {
        return $this->container->get('twig')->render("shortcodes/$file.html.twig", $params);
    }

    private function register($name, $shortCodeString, $params=null)
    {
        $shortCodeKey = '__shortCode__'.$name;

        $this->$shortCodeKey = $params;
        $this->shortCodeString = $shortCodeString;

        if ($this->filesystem->exists($this->please->prevContainer->get('kernel')->getProjectDir() . '/src/Service/HookService.php')) {
            if (method_exists(\App\Service\HookService::class, '__registerShortsCodes') && $this->please->prevContainer->has('hook')) {
                $this->please->prevContainer->get('hook')->__registerShortsCodes($this);
            }
        }
    }

    private function getRendered($name)
    {
        $shortCodeKey = '__shortCode__'.$name;
        return '>'.(is_string($this->$shortCodeKey) ? $this->$shortCodeKey : $this->shortCodeString).'</div>';
    }
}
