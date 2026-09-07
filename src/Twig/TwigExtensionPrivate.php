<?php

namespace DovStone\Bundle\SymfonyBlogAdminBundleQuantBased\Twig;

use Symfony\Component\Filesystem\Filesystem;
use DovStone\Bundle\SymfonyBlogAdminBundleQuantBased\Service\PleaseService;
use Twig\Extension\AbstractExtension;
use ScssPhp\ScssPhp\Compiler;
use Twig\TwigFunction;
use Twig\Markup;

class TwigExtensionPrivate extends AbstractExtension
{
    public $please;

    public function __construct(PleaseService $please)
    {
        $this->please = $please;
    }

    public function getFunctions()
    {
        return array(
            new TwigFunction('getAcf', array($this, 'getAcf')),
            new TwigFunction('getAcfRelatedTiles', array($this, 'getAcfRelatedTiles')),
            new TwigFunction('fetchEager', array($this, 'fetchEager')),
            new TwigFunction('userCanHandleAcf', array($this, 'userCanHandleAcf')),
            new TwigFunction('getTh', array($this, 'getTh')),
            new TwigFunction('getTd', array($this, 'getTd')),
            new TwigFunction('buildTree', array($this, 'buildTree')),
        );
    }

    public function getAcf()
    {
        return collection('acf')->fetch();
    }

    public function getAcfRelatedTiles($acf, $tilesData, $item = null)
    {
        return $this->please->serve('acf')->getAcfRelatedTiles($acf, $tilesData, $item);
    }

    public function fetchEager(?array $data = [], array $orderBy = ['created_at' => 'desc'], string $coll_name = null)
    {
        return $this->please->getRepo('bloggy')->fetchEager($data, $orderBy, $coll_name = null);
    }

    public function userCanHandleAcf($acf = [], $user = null)
    {
        return $this->please->serve('security')->userCanHandleAcf($acf, $user);
    }

    public function getTh($acfChildren, $itemsCount = 0)
    {
        return $this->getThAndTd($acfChildren, 0, $itemsCount, null);
    }

    public function getTd($acfChildren, $item, $itemsCount = 0)
    {
        return $this->getThAndTd($acfChildren, 1, $itemsCount, $item);
    }

    public function buildTree($collection, $renderType = 'option', $preventId = null, $params = [], $selected = null, $parentId = null)
    {
        return $this->please->serve('dir')->buildTree($collection, $renderType, $preventId, $params, $selected, $parentId);
    }

    private function getThAndTd($acfChildren, $index, $itemsCount, $item = null)
    {
        $timeServ = $this->please->serve('time');
        $t = '';
        foreach ($acfChildren as $needle => $columns) {

            if (in_array($needle, ['readListColumns', 'readListToggleActions'])) {
                preg_match_all("#([a-zA-Z]+\s*[a-zA-Z0-9,()_].*)#", $columns, $m, PREG_PATTERN_ORDER);
                if ($m) {
                    $columns = $m[0];
                    foreach ($columns as $c) {
                        $data = explode('@', $c);
                        if (in_array(count($data), [2, 3, 4, 5])) {

                            $tag = $index == 0 ? 'th' : 'td';
                            $pathO = trim(preg_replace('/\s+/', '', $data[1]));
                            $path = explode('::', $pathO)[0];
                            $ternary = attr($item, trim($path), trim($data[3] ?? ''));

                            // lets retrieve dynamic document prop
                            if (strpos($pathO, '[') !== false) {
                                $xpl1 = explode('[', $pathO);
                                $xpl2 = explode(']', $xpl1[1]);
                                $prop = $xpl2[0] ?? '';
                                $collectionName = strtolower($xpl2[1] ?? 'b');
                                if (in_array($collectionName, ['b', 'u'])) {
                                    $document = $collectionName()->find(attr($item, $xpl1[0], 0));
                                    $ternary = attr($document, trim($prop), trim($data[3] ?? '-'));
                                }
                            }

                            // checking ternary formats
                            $classData = explode('->', explode('::', $pathO)[1] ?? null);
                            if (is_countable($classData) && count($classData) === 2) {
                                $service = $classData[0];
                                $meth = $classData[1];
                                if ($service == 'time') {
                                    if ($timeServ->isCorrectDateFormat($ternary)) {
                                        if (method_exists($timeServ, $meth)) {
                                            $ternary = $timeServ->$meth($ternary);
                                        }
                                    }
                                } else {
                                    $ternary = $this->please->serve($service)->$meth($ternary);
                                }
                            }

                            $val = ($index == 0) ? $data[0] : $ternary;

                            if ($needle == 'readListColumns') {
                                $isImage =    stripos($val, '.jpg')  !== false
                                    || stripos($val, '.jpeg') !== false
                                    || stripos($val, '.png')  !== false
                                    || stripos($val, '.webp') !== false
                                    || stripos($val, '.gif')  !== false;

                                $cls = $tag == 'td' ? 'class="' . ($data[4] ?? '-') . '"' : '';

                                if ($isImage) {

                                    $t .= "<$tag data-minmax=" . ($data[2] ?? 130) . " $cls>";
                                    $diapowl = ["image" => $val, "caption" => $val];
                                    $t .= '<a 
                                            data-diapowl="' . htmlentities(json_encode($diapowl)) . '" 
                                            href="#" data-js="bo={click:initDiapOwl}">
                                                <img 
                                                    src="' . $val . '" 
                                                    class="img-responsive mg-auto" 
                                                    style="height:46px;border:1px solid #cfcfcf;padding:1px;border-radius:4px" />
                                            </a>';
                                } else {

                                    switch ($path) {
                                        case 'rank':

                                            $t .= "<$tag title=\"$val\" data-minmax=" . (90) . " $cls>";
                                            if (($index == 0)) {
                                                $t .= "<div class=\"text-center\">$val</div>";
                                            } else {
                                                $t .= '<form 
                                                                action="' . $this->please->serve('url')->getUrl("_admin/bloggy/{$item['id']}/basic-update") . '"
                                                                type="post"
                                                                data-js="bo={submit:submitBasicUpdate}"
                                                            >
                                                            <select style="border:0;width:100%;text-align:center" name="rank" onchange=\'$(this).parents("form").trigger("submit")\'>';
                                                $t .= '<option value="">-</option>';
                                                for ($i = 1; $i < $itemsCount + 1; $i++) {
                                                    $t .= '<option value="' . $i . '" ' . ($i == $val ? 'selected' : '') . '>' . $i . '</option>';
                                                }
                                                $t .= '</select>
                                                        </form>';
                                            }


                                            break;
                                        default:
                                            $t .= "<$tag title=\"$val\" data-minmax=" . ($data[2] ?? 130) . " $cls>";
                                            $t .= $val;
                                            break;
                                    }
                                }
                            } else {
                                if ($index == 0) {

                                    $t .= "<$tag title=\"$val\" data-minmax=" . ($data[2] ?? 130) . ">";
                                    $t .= $val;
                                } else {

                                    $path = trim(preg_replace('/\s+/', '', $data[2]));
                                    $val = attr($item, $path, $data[4] ?? 'off');
                                    $t .= "<$tag title=\"$val\" data-minmax=" . ($data[3] ?? 130) . ">";

                                    $t .= '<form 
                                                action="' . $this->please->serve('url')->getUrl("_admin/bloggy/{$item['id']}/basic-update") . '"
                                                type="post"
                                                data-js="bo={submit:submitBasicUpdate}"
                                            >
                                            <div class="pretty p-switch p-outline">
                                                <input
                                                    type="checkbox"
                                                    data-js="bo={click:toggleCheckboxOnOff}"
                                                    value="' . $val . '"
                                                    ' . ($val == 'on' ? 'checked' : '') . '
                                                >
                                                <div class="state"><label>' . $data[1] . '</label></div>
                                                <input type="hidden" name="' . $data[2] . '" value="' . $val . '">
                                            </div>
                                        </form>';
                                }
                            }
                            $t .= "</$tag>";
                        }
                    }
                }
            }
        }
        return new Markup($t, 'UTF-8');
    }
}
