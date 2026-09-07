<?php

namespace DovStone\Bundle\SymfonyBlogAdminBundleQuantBased\Controller;

use DovStone\Bundle\SymfonyBlogAdminBundleQuantBased\Service\PleaseService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

#[Route('_admin/ping')]
class BundlePingController extends AbstractController
{
    public $please;

    public function __construct(PleaseService $please)
    {
        $this->please = $please;
        $this->please->serve('execute_before')->executeBefore();
    }

    #[Route('', name: '_init')]
    public function _init()
    {
        return new JsonResponse([
            'hits' => $this->_setCurrentPostHits(),
            //'fetchallReset' => (new Cache('fetchall'))->rmdir()
        ]);
    }

    private function _setCurrentPostHits()
    {
        if ($this->please->getInput('bo') == 'true') {
            $val = 0;
        } else {
            try {
                $uid = $this->please->serve('post')->get()['id'] ?? null;
                $val = ($this->please->getRepo('bloggy')->getHits($uid)); // query the current hits
                if ($val == 0) {
                    $val = 1;
                    // insert
                    $sql = "INSERT INTO `hits` (uid, val) VALUE (?, ?)";
                    $params = [$uid, $val];
                } else {
                    // update
                    $val = $val + 1;
                    $sql = "UPDATE `hits` SET val=? WHERE uid=?";
                    $params = [$val, $uid];
                }
                h()->commit($sql, $params);
                //
            } catch (\Throwable $th) {
                $val = 0;
            }
        }
        return $val;
    }
}
