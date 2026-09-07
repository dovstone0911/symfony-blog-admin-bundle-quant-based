<?php

namespace DovStone\Bundle\SymfonyBlogAdminBundleQuantBased\Service;

use DovStone\Bundle\SymfonyBlogAdminBundleQuantBased\Service\PleaseService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Twig\Markup;

class DirService extends AbstractController
{
    private $fileSystem;
    private $please;
    private $rawTrees;
    private $finder;

    public function __construct(PleaseService $please)
    {
        $this->please = $please;
        $this->fileSystem = new Filesystem();
    }

    public function dirPath($dir = 'templates/layouts')
    {
        $dir_path = $this->getProjectDir() . '/' . $dir;
        return preg_replace('~/+~', '/', $dir_path);
    }

    public function asOptions($dir = '', string $format = 'string', $file_extenstion = 'html.twig')
    {
        $this->finder = new Finder();

        $options = ($format === 'array') ? [] : '<option value="default">Default</option><option disabled></option>';

        $dirToOpen = $this->dirPath($dir);

        if (!$this->fileSystem->exists($dirToOpen)) {
            $this->fileSystem->mkdir($dirToOpen, 0777);
        }

        $this->finder->files()->name('/\.' . $file_extenstion . '$/')->in($dirToOpen);

        foreach ($this->finder as $file) {

            $layout_name = str_ireplace('.' . $file_extenstion, '', $file->getFileName());

            if ($layout_name !== 'default') {
                $layout_name = str_replace('theme/', '', "$dir/$layout_name");

                if ($format === 'array') {
                    $options[$layout_name] = $layout_name;
                } else {
                    $options .= "<option value='$layout_name'>$layout_name</option>";
                }
            }
        }

        return new Markup($options, 'UTF-8');
    }

    public function listFolders($dir)
    {
        $list = [];
        if (is_dir($dir = $this->getProjectDir() . '/' . $dir)) {
            $d = dir($dir);
            while (false !== ($entry = $d->read())) {
                $entry_ = $dir . '/' . $entry;
                if (is_dir($entry_) && ($entry != '.') && ($entry != '..')) {
                    $list[] = $entry;
                }
            }
        }
        return $list;
    }

    public function listFiles($dir)
    {
        $list = [];
        if (is_dir($dir = $this->getProjectDir() . '/' . $dir)) {
            $d = dir($dir);
            while (false !== ($entry = $d->read())) {
                $entry_ = $dir . '/' . $entry;
                if (is_file($entry_) && ($entry != '.') && ($entry != '..')) {
                    $list[] = $entry;
                }
            }
        }
        return $list;
    }

    public function getTemplatesDirPath($dir = '')
    {
        return 'templates/' . $dir;
    }

    public function isHome()
    {
        $urlServ = $this->please->serve('url');
        if (
            (trim($urlServ->getCurrentUrlQueryLess(), '/') == trim($urlServ->getUrl(), '/'))
            //||
            //( $this->please->getGlobal('post') && $this->please->getGlobal('post')->getSlug() == 'home' )
        ) {
            return true;
        }
        return false;
    }

    public function getThemeDirAbsDirPath($dir = '')
    {
        return $this->dirPath('theme') . '/' . $dir;
    }

    public function getProjectDir($dir = '')
    {
        return $this->please->prevContainer->get('kernel')->getProjectDir() . '/' . $dir;
    }

    public function getProjectPath($path = null)
    {
        return $this->please->prevContainer->get('kernel')->getProjectDir() . '/' . trim(preg_replace('~/+~', '/', $path), '/');
    }

    public function buildTree($collection, $renderType = 'option', $preventId = null, $params = [], $selected = null, $parentId = null)
    {
        $view = '';

        switch ($renderType) {
            case 'option':
                $view = '<option value="null">Aucun</option><option disabled></option>';
                if ($collection) {
                    $view .= $this->_getOptionsTree($collection, null, 0, $preventId, $selected);
                }
                break;

            case 'checkbox':
                $view = '<ul>';
                if ($collection) {
                    $view .= $this->_getCheckboxesTree($collection, null, 0, $preventId);
                }
                $view .= '</ul>';
                break;

            case 'list':

                $preventUl = $params['preventUl'] ?? false;
                $mainUlId = $params['mainUlId'] ?? false;
                $mainUlClass = $params['mainUlClass'] ?? false;

                if (!$preventUl) {
                    $view = '<ul' . ($mainUlId ? ' id="' . $mainUlId . '"' : '') . '' . ($mainUlClass ? ' class="' . $mainUlClass . '"' : '') . '>';
                }

                if ($collection) {
                    $view .= $this->_getAsUnorderedListRecursively($collection, null, 0, $preventId, $params);
                }

                if (!$preventUl) {
                    $view .= '</ul>';
                }

                break;

            case 'raw':
                if ($collection) {
                    return $this->_getRawTree($collection, $parentId, 0, $preventId, $selected);
                }
                break;

            default:
                $view = '';
                break;
        }

        return new Markup($view, 'UTF-8');
    }

    public function delTree($dir)
    {
        if (file_exists($dir)) {
            $files = array_diff(scandir($dir), array('.', '..'));
            foreach ($files as $file) {
                (is_dir("$dir/$file")) ? $this->delTree("$dir/$file") : unlink("$dir/$file");
            }
            if (file_exists($dir)) {
                return rmdir($dir);
            }
        }
        return false;
    }

    public function rename($oldfile, $newfile)
    {
        if (!rename($oldfile, $newfile)) {
            if (copy($oldfile, $newfile)) {
                unlink($oldfile);
                return true;
            }
            return false;
        }
        return true;
    }

    public function _getRawTree($collection, $parentId, $depth, $preventId, $selected)
    {
        foreach ($collection as $collectId => $collect) {

            $parent = (int) ($collect['parent_id'] ?? $collect['parent']['id'] ?? $collect['parent'] ?? null);
            $id = (int) attr($collect, 'id');

            if ((int) $preventId !== $id && $parent == (int) $parentId) {
                $collect['_depth'] = $depth;
                $this->rawTrees[$collect['id']] = $collect;
                $this->_getRawTree($collection, $id, $depth + 1, $preventId, $selected);
            }
        }
        return array_values($this->rawTrees ?? []);
    }

    protected function _getOptionsTree($collection, $parentId, $depth, $preventId, $selected)
    {
        $html = '';
        foreach ($collection as $collectId => $collect) {

            $parent = (int) ($collect['parent_id'] ?? $collect['parent']['id'] ?? $collect['parent'] ?? null);
            $id = (int) attr($collect, 'id');
            $title = attr($collect, 'title');

            if ((int) $preventId !== $id && $parent == (int) $parentId) {
                $html .= '<option value="' . $id . '" ' . ($id == $selected ? 'selected' : '') . '>';
                $html .= str_repeat("--", $depth);
                $html .= $title;
                $html .= $this->_getOptionsTree($collection, $id, $depth + 1, $preventId, $selected);
                $html .= '</option>';
            }
        }
        return $html;
    }

    protected function _getCheckboxesTree($collection, $parentId, $depth, $preventId)
    {
        $html = '';
        foreach ($collection as $collect) {

            $parent = attr($collect, 'parent');
            $id = attr($collect, 'id');
            $title = attr($collect, 'title');
            $rank = attr($collect, 'rank');

            if ((int) $preventId !== $id && (int) $parent == (int) $parentId) {

                if ($depth == 0) {
                    $html .= '<li data-id="' . $id . '">';
                    $html .= '<div class="pretty p-switch p-outline">
                                <input type="checkbox" data-js="navs={click:appendToNav}" value="' . $id . '" />
                                <div class="state">
                                    <label><span>' . $title . '</span></label>
                                </div>
                            </div>';
                }
                if ($depth == 1) {
                    $html .= '<div class="depths">';
                }

                if ($depth >= 1) {
                    $html .= '<div class="depth depth-' . $depth . '">';
                    $html .= str_repeat("<i style='color:#ccc'>--</i>", $depth);
                    $html .= '<input type="hidden" name="ranks[' . $id . ']" value="' . ($rank ?? uniqid()) . '">';
                    $html .= ' <em>' . $title . '</em>';
                    $html .= '</div>';
                }
                $html .= $this->_getCheckboxesTree($collection, $id, $depth + 1, $preventId);

                if ($depth == 1) {
                    $html .= '</div>';
                }
                if ($depth == 0) {
                    $html .= '</li>';
                }
            }
        }
        return $html;
    }

    protected function _getAsUnorderedListRecursively($collection, $parentId, $depth, $preventId, $params)
    {
        $looop0 = 0;
        $looop1 = 0;
        $loop = ['index0' => 0, 'index' => 1];
        $html = '';
        $urlServ = $this->please->serve('url');

        foreach ($collection as $index => $collect) {

            $parent = attr($collect, 'parent');
            $id = attr($collect, 'id');
            $title = attr($collect, 'title');
            $children = attr($collect, 'children');

            // lets sanitize $children by retrieving published=='off' or in_menu=="off"
            if ($children) {
                foreach ($children as $key => $child) {
                    if (
                        attr($params, 'nav')
                        &&
                        (attr($child, 'published') == 'off'
                            ||
                            attr($child, 'in_menu') == 'off'
                            ||
                            attr($child, 'type') == 'acf'
                            ||
                            attr($child, 'link_type') == 'article'
                        )
                    ) {
                        unset($children[$key]);
                    }
                    // lets re-assign children
                    $collect['children'] = $children;
                }
            }

            if ((int) $preventId !== $id && (int) $parent == (int) $parentId) {
                $navPath = 'navs/' . ($params['structure'] ?? 'default') . '.html.twig';
                $item = $collect;
                if (
                    attr($params, 'nav')
                    &&
                    (attr($collect, 'published') == 'off' || attr($collect, 'in_menu') == 'off')
                ) {
                    continue;
                } else {
                    $loop['index0'] = $looop0++;
                    $loop['index'] = ++$looop1;

                    $item['href'] = trim($urlServ->getHref($item), '/');
                    $currentHref = trim($this->please->serve('url')->getCurrentUrlQueryLess(), '/');
                    if (
                        $item['href'] == $currentHref
                        || ($item['href'] !== trim($this->please->serve('url')->getUrl(), '/') && strpos($currentHref, $item['href']) !== false)
                        || ($item['href'] == $currentHref && strpos($currentHref, $item['slug']) !== false)
                    ) {
                        $item['isActive'] = true;
                    }
                    $v = $this->renderView($navPath, compact('item', 'loop'));
                    $html .= $v;
                }
            }
        }
        return $html;
    }
}
