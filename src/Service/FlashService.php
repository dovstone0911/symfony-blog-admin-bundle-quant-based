<?php

namespace DovStone\Bundle\SymfonyBlogAdminBundleQuantBased\Service;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use DovStone\Bundle\SymfonyBlogAdminBundleQuantBased\Service\PleaseService;

class FlashService extends AbstractController
{
    public $please;

    public function __construct(PleaseService $please)
    {
        $this->please = $please;
    }

    public function setFlash($key, $message, int $duration = 5): void
    {
        $_SESSION['flash'][$key] = [
            'message' => $message,
            'expire_at' => time() + $duration
        ];
    }

    public function getFlash($key): ?string
    {
        if (isset($_SESSION['flash'][$key])) {
            $flash = $_SESSION['flash'][$key];
            if (time() < $flash['expire_at']) {
                unset($_SESSION['flash'][$key]);
                return $flash['message'];
            } else {
                unset($_SESSION['flash'][$key]);
            }
        }
        return null;
    }
}
