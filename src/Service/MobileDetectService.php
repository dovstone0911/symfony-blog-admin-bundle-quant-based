<?php

namespace DovStone\Bundle\SymfonyBlogAdminBundleQuantBased\Service;

use DovStone\Bundle\SymfonyBlogAdminBundleQuantBased\Service\PleaseService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Detection\MobileDetect;

class MobileDetectService extends AbstractController
{
    public $please;
    public $mobileDetect;

    public function __construct(PleaseService $please)
    {
        $this->please = $please;
        $this->mobileDetect = new MobileDetect();
    }

    public function mobileDetect()
    {
        return $this->mobileDetect;
    }

    public function isMobile()
    {
        return $this->mobileDetect->isMobile() || $this->mobileDetect->isTablet();
    }

    public function isPhone()
    {
        return $this->mobileDetect->isMobile();
    }

    public function isTablet()
    {
        return $this->mobileDetect->isTablet();
    }

    public function isDesktop()
    {
        return !$this->mobileDetect->isMobile() && !$this->mobileDetect->isTablet();
    }

    public function isNotDesktop()
    {
        return !$this->isDesktop();
    }
}
