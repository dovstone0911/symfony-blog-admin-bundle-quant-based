<?php

namespace DovStone\Bundle\SymfonyBlogAdminBundleQuantBased\Repository;

use DovStone\Bundle\SymfonyBlogAdminBundleQuantBased\Service\PleaseService;

/**
 * @method Bloggy|null find($id, $lockMode = null, $lockVersion = null)
 * @method Bloggy|null findOneBy(array $criteria, array $orderBy = null)
 * @method Bloggy[]    fetch()
 * @method Bloggy[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class BundleUserRepository
{
    public $please;

    public function __construct(PleaseService $please)
    {
        $this->please = $please;
    }

    public function fetchEager($data = [], $orderBy = ['created_at' => 'desc'])
    {
        return $data;
        if (isset($data[0])) {
            $data_ = $data;
        } else {
            $data_[] = $data;
        }

        $ids = [];

        if ($data_) {

            foreach ($data_ as $d) {
                $ids[] = attr($d, 'id');
            }

            $users = collection('users');

            $rows = $users->whereIn('id', $ids)->orderBy($orderBy)->fetch();

            if ($rows) {
                foreach ($rows as $k => $item) {
                    // parent
                    $parentID = (int)attr($item, 'parent');
                    $rows[$k]['parent'] = $parentID ? $users->find($parentID) : null;

                    // _bloggies
                    $rows[$k]['_bloggies'] = $users->where('id', '=', attr($item, 'id'))
                        ->orderBy('created_at', 'DESC')
                        ->fetch() ?? [];
                }
            }

            return isset($data[0]) ? $rows : (isset($rows[0]) ? $rows[0] : []);
        }
        return $data;
    }
}
