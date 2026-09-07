<?php

namespace DovStone\Bundle\SymfonyBlogAdminBundleQuantBased\Repository;

use DovStone\Bundle\SymfonyBlogAdminBundleQuantBased\Service\PleaseService;

/**
 * @method Bloggy|null find($id, $lockMode = null, $lockVersion = null)
 * @method Bloggy|null findOneBy(array $criteria, array $orderBy = null)
 * @method Bloggy[]    fetch()
 * @method Bloggy[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class BundleBloggyRepository
{
    public $please;

    public function __construct(PleaseService $please)
    {
        $this->please = $please;
    }

    public function fetchEager(array|null $items_ = [], array $orderBy = ['created_at' => 'desc'], ?string $coll_name = null)
    {
        if (is_array($items_)) {
            if ($items_) {
                if (isset($items_[0])) {
                    $items = $items_;
                } else {
                    $findOne = true;
                    $items[] = $items_;
                }

                foreach ($items as $k => $item) {

                    $parentID = (int) attr($item, 'parent_id');
                    $coll_name = attr($item, 'coll_name', $coll_name);
                    $items[$k]['parent'] = $parentID ? collection('*')->find($parentID) : null;
                    // $items[$k]['href'] = $this->please->serve('url')->getHref($item);

                    /*

                    $items[$k]['children'] = collection($coll_name)
                        ->where('parent', '=', attr($item, 'id'))
                        ->where('published', '=', 'on')
                        ->where('type', '!=', 'acf')
                        ->orderBy('created_at', 'DESC')
                        ->fetch() ?? [];

                    $items[$k]['pages'] = collection($coll_name)
                        ->where('parent', '=', attr($item, 'id'))
                        ->where('published', '=', 'on')
                        ->where('type', '!=', 'acf')
                        ->where('link_type', '=', 'page')
                        ->orderBy('created_at', 'DESC')
                        ->fetch() ?? [];

                    $items[$k]['articles'] = collection($coll_name)
                        ->where('parent', '=', attr($item, 'id'))
                        ->where('published', '=', 'on')
                        ->where('type', '!=', 'acf')
                        ->where('link_type', '=', 'article')
                        ->orderBy('created_at', 'DESC')
                        ->fetch() ?? [];

                    $items[$k]['childrenInMenu'] = collection($coll_name)
                        ->where('parent', '=', attr($item, 'id'))
                        ->where('published', '=', 'on')
                        ->where('type', '!=', 'acf')
                        ->whereIn('in_menu', ['on', 'yes'])
                        ->orderBy('created_at', 'DESC')
                        ->fetch() ?? [];
                */
                }
                $items = array_values($items);
                return isset($findOne) && isset($items[0]) ? $items[0] : $items;
            }
            return $items_;
        }
        return [];
    }

    public function findAcf(string $coll_name, int $limit = -1, array $orderBy = ['created_at' => 'desc'], int $offset = 0)
    {
        $items = collection($coll_name)
            ->where('published', '=', 'on')
            ->orderBy($orderBy)
            ->limit($limit)
            ->offset($offset)
            ->fetch();

        if ($items) {

            if (!isset($items[0])) {
                $items = [$items];
            }

            foreach ($items as $item) {
                $parentID = (int) attr($item, 'parent_id');
                $item['parent'] = $parentID ? collection('*')->find($parentID) : null;
            }
        }

        return $items && $limit == 1 && isset($items[0]) ? $items[0] : $items;
    }

    public function incrementHits($uid)
    {
        $uid = $uid['id'] ?? $uid;
        file_get_contents($this->please->serve('url')->getUrl("_admin/hits/$uid/set"));
        return null;
    }
}
