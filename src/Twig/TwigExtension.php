<?php

namespace DovStone\Bundle\SymfonyBlogAdminBundleQuantBased\Twig;

use Twig\Markup;
use Twig\Environment;
use Twig\TwigFunction;
use Twig\Extension\AbstractExtension;
use Symfony\Component\Filesystem\Filesystem;
use DovStone\Bundle\SymfonyBlogAdminBundleQuantBased\Service\PleaseService;

class TwigExtension extends AbstractExtension
{
    public $container;
    public $assetServ;

    public function __construct(private Environment $twig, public PleaseService $please)
    {
        $this->container = $this->please->getContainer();
        $this->assetServ = $this->please->serve('asset');
    }

    public function getFunctions()
    {
        return array(
            new TwigFunction('dd', array($this, 'dd')),
            new TwigFunction('_dump', array($this, '_dump')),
            new TwigFunction('convertToKnpPaginatorBundle', array($this, 'convertToKnpPaginatorBundle')),
            new TwigFunction('mimicKnpPaginator', array($this, 'mimicKnpPaginator')),
            new TwigFunction('getPaginationOffset', array($this, 'getPaginationOffset')),
            new TwigFunction('jasonPaginator', array($this, 'jasonPaginator')),

            new TwigFunction('getTableHeadCssLabel', array($this, 'getTableHeadCssLabel')),

            new TwigFunction('getAppEnv', array($this, 'getAppEnv')),
            new TwigFunction('getAppConfig', array($this, 'getAppConfig')),
            new TwigFunction('getUrl', array($this, 'getUrl')),
            new TwigFunction('cdnFileUrl', array($this, 'cdnFileUrl')),
            new TwigFunction('getHref', array($this, 'getHref')),
            new TwigFunction('href', array($this, 'href')),
            new TwigFunction('getCurrentUrl', array($this, 'getCurrentUrl')),
            new TwigFunction('getCurrentUrlQueryLess', array($this, 'getCurrentUrlQueryLess')),
            new TwigFunction('isProduction', array($this, 'isProduction')),

            new TwigFunction('preloader', array($this, 'preloader')),
            new TwigFunction('getCDN', array($this, 'getCDN')),
            new TwigFunction('getAsset', array($this, 'getAsset')),
            new TwigFunction('gfInput', array($this, 'gfInput')),
            new TwigFunction('gfTextarea', array($this, 'gfTextarea')),
            new TwigFunction('gfSelect', array($this, 'gfSelect')),
            new TwigFunction('bfInput', array($this, 'bfInput')),
            new TwigFunction('bfTextarea', array($this, 'bfTextarea')),
            new TwigFunction('bfSelect', array($this, 'bfSelect')),
            new TwigFunction('fcInput', array($this, 'fcInput')),
            new TwigFunction('fcTextarea', array($this, 'fcTextarea')),
            new TwigFunction('fcSelect', array($this, 'fcSelect')),
            new TwigFunction('flash', array($this, 'flash')),
            new TwigFunction('outputError', array($this, 'outputError')),
            new TwigFunction('getAppCss', array($this, 'getAppCss')),
            new TwigFunction('getLess', array($this, 'getLess')),
            new TwigFunction('getHeadAssets', array($this, 'getHeadAssets')),
            new TwigFunction('getThemeAssets', array($this, 'getThemeAssets')),
            new TwigFunction('getLogoSrc', array($this, 'getLogoSrc')),
            new TwigFunction('getImage', array($this, 'getImage')),
            new TwigFunction('smartImage', array($this, 'smartImage')),
            new TwigFunction('image', array($this, 'image')),
            new TwigFunction('resizeImage', array($this, 'resizeImage')),
            new TwigFunction('resize', array($this, 'resize')),
            new TwigFunction('getPicture', array($this, 'getPicture')),
            new TwigFunction('picture', array($this, 'picture')),
            new TwigFunction('pic', array($this, 'pic')),

            new TwigFunction('getPost', array($this, 'getPost')),
            new TwigFunction('setPost', array($this, 'setPost')),
            new TwigFunction('getProp', array($this, 'getProp')),
            new TwigFunction('prop', array($this, 'prop')),
            new TwigFunction('getUserProp', array($this, 'getUserProp')),
            new TwigFunction('userProp', array($this, 'userProp')),
            new TwigFunction('getAppConfig', array($this, 'getAppConfig')),

            new TwigFunction('findAcf', array($this, 'findAcf')),
            new TwigFunction('findPage', array($this, 'findPage')),
            new TwigFunction('countBy', array($this, 'countBy')),
            new TwigFunction('minMax', array($this, 'minMax')),
            new TwigFunction('getAttr', array($this, 'getAttr')),
            new TwigFunction('attr', array($this, 'attr')),
            new TwigFunction('acf', array($this, 'acf')),

            new TwigFunction('getKnpTplPath', array($this, 'getKnpTplPath')),
            new TwigFunction('getBundleEmptyListView', array($this, 'getBundleEmptyListView')),

            new TwigFunction('getFrenchDate', array($this, 'getFrenchDate')),
            new TwigFunction('getMonth', array($this, 'getMonth')),
            new TwigFunction('getSlug', array($this, 'getSlug')),
            new TwigFunction('getHtml2Text', array($this, 'getHtml2Text')),

            new TwigFunction('th', array($this, 'th')),
            new TwigFunction('p', array($this, 'p')),
            new TwigFunction('s', array($this, 's')),
            new TwigFunction('c', array($this, 'c')),
            new TwigFunction('l', array($this, 'l')),
            new TwigFunction('ut', array($this, 'ut')),
            new TwigFunction('getBody', array($this, 'getBody')),
            new TwigFunction('comp', array($this, 'comp')),
            new TwigFunction('getComponent', array($this, 'getComponent')),
            new TwigFunction('sc', array($this, 'sc')),
            new TwigFunction('shortcode', array($this, 'shortcode')),
            new TwigFunction('getSection', array($this, 'getSection')),
            new TwigFunction('getSections', array($this, 'getSections')),
            new TwigFunction('getUserTemplate', array($this, 'getUserTemplate')),
            new TwigFunction('view', array($this, 'view')),
            new TwigFunction('card', array($this, 'card')),
            new TwigFunction('partial', array($this, 'partial')),
            new TwigFunction('section', array($this, 'section')),
            new TwigFunction('__vendor', array($this, '__vendor')),
            new TwigFunction('xhrRendering', array($this, 'xhrRendering')),
            new TwigFunction('xhr', array($this, 'xhr')),
            new TwigFunction('skeleton', array($this, 'skeleton')),
            new TwigFunction('skel', array($this, 'skel')),
            new TwigFunction('tpl', array($this, 'tpl')),
            new TwigFunction('compress', array($this, 'compress')),
            new TwigFunction('getNav', array($this, 'getNav')),
            new TwigFunction('getBreadcrumb', array($this, 'getBreadcrumb')),
            new TwigFunction('orderBy', array($this, 'orderBy')),

            //
            new TwigFunction('getCached', array($this, 'getCached')),
            new TwigFunction('getCacheKey', array($this, 'getCacheKey')),
            new TwigFunction('getCacheRequestKey', array($this, 'getCacheRequestKey')),
            new TwigFunction('setStorage', array($this, 'setStorage')),
            new TwigFunction('getStorage', array($this, 'getStorage')),
            new TwigFunction('curl', array($this, 'curl')),
            new TwigFunction('setGlobal', array($this, 'setGlobal')),
            new TwigFunction('getGlobal', array($this, 'getGlobal')),
            new TwigFunction('unsetGlobal', array($this, 'unsetGlobal')),
            new TwigFunction('doCache', array($this, 'doCache')),

            new TwigFunction('getDateTime', array($this, 'getDateTime')),
            new TwigFunction('getTimeAgo', array($this, 'getTimeAgo')),
            new TwigFunction('getTimeRemaining', array($this, 'getTimeRemaining')),
            new TwigFunction('getDaysOptions', array($this, 'getDaysOptions')),
            new TwigFunction('getMonthsOptions', array($this, 'getMonthsOptions')),
            new TwigFunction('getYearsOptions', array($this, 'getYearsOptions')),
            new TwigFunction('ellipsisDate', array($this, 'ellipsisDate')),
            new TwigFunction('ellipsisText', array($this, 'ellipsisText')),
            new TwigFunction('millisecAsDuration', array($this, 'millisecAsDuration')),
            new TwigFunction('indexify', array($this, 'indexify')),
            new TwigFunction('json_encode', array($this, 'json_encode')),

            new TwigFunction('getUserFullName', array($this, 'getUserFullName')),
            new TwigFunction('getUserRole', array($this, 'getUserRole')),

            new TwigFunction('isHome', array($this, 'isHome')),
            new TwigFunction('isActive', array($this, 'isActive')),
            new TwigFunction('isBot', array($this, 'isBot')),
            new TwigFunction('isXHR', array($this, 'isXHR')),
            new TwigFunction('isNotXHR', array($this, 'isNotXHR')),
            new TwigFunction('gTag', array($this, 'gTag')),
            new TwigFunction('beginHTML', array($this, 'beginHTML')),
            new TwigFunction('endHTML', array($this, 'endHTML')),

            new TwigFunction('userCan', array($this, 'userCan')),
            new TwigFunction('userIs', array($this, 'userIs')),
            new TwigFunction('csrfToken', array($this, 'csrfToken')),

            new TwigFunction('fileWeightFormatShort', array($this, 'fileWeightFormatShort')),
            new TwigFunction('priceFormat', array($this, 'priceFormat')),
            new TwigFunction('countViews', array($this, 'countViews')),
            new TwigFunction('setHits', array($this, 'setHits')),
            new TwigFunction('getHits', array($this, 'getHits')),
            new TwigFunction('randomify', array($this, 'randomify')),
            new TwigFunction('getColorsGrid', array($this, 'getColorsGrid')),
            new TwigFunction('i', array($this, 'i')),
            new TwigFunction('routeActiveClass', array($this, 'routeActiveClass')),
            new TwigFunction('getCountriesOptions', array($this, 'getCountriesOptions')),
            new TwigFunction('getCountriesCallCode', array($this, 'getCountriesCallCode')),
            new TwigFunction('fancyboxData', array($this, 'fancyboxData')),
            new TwigFunction('buildTree', array($this, 'buildTree')),
            new TwigFunction('getCurrentUpdatedAt', array($this, 'getCurrentUpdatedAt')),
            new TwigFunction('getLatestModificationDate', array($this, 'getLatestModificationDate')),

            new TwigFunction('getQueryRedir', array($this, 'getQueryRedir')),
            new TwigFunction('uId', array($this, 'uId')),
            new TwigFunction('uId8', array($this, 'uId8')),

            new TwigFunction('facebookPage', array($this, 'facebookPage')),
            new TwigFunction('renderMetas', array($this, 'renderMetas')),
            new TwigFunction('disqusThread', array($this, 'disqusThread')),

            new TwigFunction('mobileDetect', array($this, 'mobileDetect')),
            new TwigFunction('isMobile', array($this, 'isMobile')),
            new TwigFunction('isTablet', array($this, 'isTablet')),
            new TwigFunction('isDesktop', array($this, 'isDesktop')),
            new TwigFunction('isNotDesktop', array($this, 'isNotDesktop')),
            new TwigFunction('isSearchEngineBot', array($this, 'isSearchEngineBot')),
            new TwigFunction('isNotSearchEngineBot', array($this, 'isNotSearchEngineBot')),

            new TwigFunction('TTL', array($this, 'TTL')),
            new TwigFunction('badge', array($this, 'badge')),
            new TwigFunction('bodyClassNames', array($this, 'bodyClassNames')),
            new TwigFunction('resetAssets', array($this, 'resetAssets')),
            new TwigFunction('internalErrorPage', array($this, 'internalErrorPage')),
            new TwigFunction('getNavigationData', array($this, 'getNavigationData')),

            new TwigFunction('getService', array($this, 'getService')),
            new TwigFunction('marketplace', array($this, 'marketplace')),
        );
    }

    public function getAppEnv($var = 'APP_ENV')
    {
        return $this->please->serve('env')->getAppEnv($var);
    }

    public function getUrl(string $path = '/'): string
    {
        return $this->please->serve('url')->getUrl($path);
    }

    public function cdnFileUrl(string $path = '/'): string
    {
        return $this->please->serve('url')->cdnFileUrl($path);
    }

    public function getCurrentUrl(string $suffix = ''): string
    {
        return $this->please->serve('url')->getCurrentUrl($suffix);
    }

    public function getCurrentUrlQueryLess(string $suffix = ''): string
    {
        return $this->please->serve('url')->getCurrentUrlQueryLess($suffix);
    }

    public function isProduction(): bool
    {
        return $this->please->serve('env')->isProduction();
    }

    public function getHref($post)
    {
        return $this->please->serve('url')->getHref($post);
    }

    public function href($post)
    {
        return $this->please->serve('url')->href($post);
    }

    public function getProp($post, $prop, $limit = -1, $offset = null)
    {
        return $this->please->serve('post')->getProp($post, $prop, $limit, $offset);
    }

    public function prop($post, $prop, $limit = -1, $offset = null)
    {
        return $this->getProp($post, $prop, $limit, $offset);
    }

    public function getUserProp($user, $prop, $criteria = [])
    {
        return $this->please->serve('user')->getUserProp($user, $prop, $criteria);
    }

    public function userProp($user, $prop, $criteria = [])
    {
        return $this->getUserProp($user, $prop, $criteria);
    }

    public function preloader($v = 1, $icon = null, $maxWidth = '100%', $className = null)
    {
        $content = $this->please->serve('cache')->do(function () use ($v, $icon, $maxWidth, $className) {
            $hiddenClass = $this->please->isXHR() ? 'hidden' : '';
            $icon = '<img style="height:40px;margin:55px auto 0" src="' . ($icon ? $this->assetServ->getAsset($icon) : $this->assetServ->getCDN('swagg/assets/img/loading-spinner.gif')) . '?v=' . uniqid() . '">';
            return '<div class="app-preloader-wrapper ' . $className . $hiddenClass . '">
            <div style="text-align:center">
                <div class="logo-wrapper"><img style="margin:auto;width:' . $maxWidth . ';border-radius:4px" class="logo" src="' . $this->getLogoSrc($v) . '"></div>
                ' . $icon . '
            </div>
        </div>';
        }, [$v, $icon, $maxWidth, $className]);

        return new Markup($content, 'UTF-8');
    }

    public function getCDN($path = '/', $extension = 'css')
    {
        return new Markup($this->assetServ->getCDN($path, $extension), 'UTF-8');
    }

    public function getHeadAssets($arr = [], $reset = false, $prevent = [])
    {
        return new Markup($this->assetServ->getHeadAssets($arr, $reset, $prevent), 'UTF-8');
    }

    public function getAsset($asset, $attr = '')
    {
        return $this->assetServ->getAsset($asset, $attr);
    }

    public function getThemeAssets($assets, $extension = 'css')
    {
        return $this->assetServ->getThemeAssets($assets, $extension);
    }

    public function getLogoSrc($v = 1, $ext = 'png', $path = null): string
    {
        return $this->assetServ->getLogoSrc($v, $ext, $path);
    }

    public function getImage(
        string $alt = '',
        string $src,
        $width = null,
        $height = null,
        $mode = 'r',
        $classNames = '',
        $lazy = true,
        $attr = null,
        $extension = 'jpeg',
        $backgroundColor = [255, 255, 255]
    ): string {
        return $this->assetServ->getImage(
            $alt,
            $src,
            $width,
            $height,
            $mode,
            $classNames,
            $lazy,
            $attr,
            $extension,
            $backgroundColor
        );
    }

    public function image(
        string $alt = '',
        string $src,
        $width = null,
        $height = null,
        $mode = 'r',
        $classNames = '',
        $lazy = true,
        $attr = null,
        $extension = 'jpeg',
        $backgroundColor = [255, 255, 255]
    ): string {
        $image = $this->assetServ->getImage(
            $alt,
            $src,
            $width,
            $height,
            $mode,
            $classNames,
            $lazy,
            $attr,
            $extension,
            $backgroundColor
        );
        return new Markup($image, 'UTF-8');
    }

    public function resizeImage(
        string $src,
        $width = null,
        $height = null,
        $mode = 'r',
        $extension = 'jpeg',
        $backgroundColor = [255, 255, 255]
    ): string {
        return $this->assetServ->resizeImage($src, $width, $height, $mode, $extension, $backgroundColor);
    }

    public function smartImage(
        $item = null,
        $filename = 'uploads/placeholder.png',
        $width = null,
        $height = null,
        $mode = 'r',
        $backgroundColor = [255, 255, 255]
    ): string {
        $filename = $filename === true ? 'uploads/placeholder.png' : $filename;
        $image = $this->assetServ->smartImage($item, $filename, $width, $height, $mode, $backgroundColor);
        return new Markup($image, 'UTF-8');
    }

    public function resize(string $src, $width = null, $height = null, $mode = 'r', $backgroundColor = [255, 255, 255]): string
    {
        return $this->assetServ->resizeImage($src, $width, $height, $mode, $backgroundColor);
    }

    public function getPicture(
        string $alt = '',
        ?string $src = null,
        array $mediumSizes,
        ?array $querySizes = null,
        $mode = 'r',
        $classNames = '',
        $lazy = true,
        $backgroundColor = [255, 255, 255]
    ): string {
        return $this->assetServ->getPicture($alt, $src, $mediumSizes, $querySizes, $mode, $classNames, $lazy, $backgroundColor);
    }

    public function picture(
        string $alt = '',
        ?string $src = null,
        array $mediumSizes,
        ?array $querySizes = null,
        $mode = 'r',
        $classNames = '',
        $lazy = true,
        $backgroundColor = [255, 255, 255]
    ): string {
        return $this->assetServ->getPicture($alt, $src, $mediumSizes, $querySizes, $mode, $classNames, $lazy, $backgroundColor);
    }

    public function pic(
        string $alt = '',
        ?string $src = null,
        array $mediumSizes,
        ?array $querySizes = null,
        $mode = 'r',
        $classNames = '',
        $lazy = true,
        $backgroundColor = [255, 255, 255]
    ): string {
        return $this->assetServ->getPicture($alt, $src, $mediumSizes, $querySizes, $mode, $classNames, $lazy, $backgroundColor);
    }

    public function gfInput($label, $name, $value = '', $attr = '', $icon = null, $em = ''): string
    {
        return new Markup($this->please->serve('template')->gfInput($label, $name, $value, $attr, $icon, $em), 'UTF-8');
    }

    public function gfTextarea($label, $name, $value = '', $attr = '', $icon = null, $em = ''): string
    {
        return new Markup($this->please->serve('template')->gfTextarea($label, $name, $value, $attr, $icon, $em), 'UTF-8');
    }

    public function gfSelect($label, $name, $selected = '', $options = [], $attr = '', $icon = null): string
    {
        return new Markup($this->please->serve('template')->gfSelect($label, $name, $selected, $options, $attr, $icon), 'UTF-8');
    }

    public function bfInput($label, $name, $value = '', $attr = '', $icon = null): string
    {
        return new Markup($this->please->serve('template')->bfInput($label, $name, $value, $attr, $icon), 'UTF-8');
    }

    public function bfTextarea($label, $name, $value = '', $attr = '', $icon = null): string
    {
        return new Markup($this->please->serve('template')->bfTextarea($label, $name, $value, $attr, $icon), 'UTF-8');
    }

    public function bfSelect($label, $name, $selected = '', $options = [], $attr = '', $icon = null): string
    {
        return new Markup($this->please->serve('template')->bfSelect($label, $name, $selected, $options, $attr, $icon), 'UTF-8');
    }

    public function fcInput($label, $name, $value = '', $attr = '', $icon = null): string
    {
        return new Markup($this->please->serve('template')->fcInput($label, $name, $value, $attr, $icon), 'UTF-8');
    }

    public function fcTextarea($label, $name, $value = '', $attr = '', $icon = null): string
    {
        return new Markup($this->please->serve('template')->fcTextarea($label, $name, $value, $attr, $icon), 'UTF-8');
    }

    public function fcSelect($label, $name, $selected = '', $options = [], $attr = '', $icon = null): string
    {
        return new Markup($this->please->serve('template')->fcSelect($label, $name, $selected, $options, $attr, $icon), 'UTF-8');
    }

    public function flash(string $flashKey, string $name, string $className = ''): string
    {
        $flashes = $this->please->getGlobal($flashKey);
        if (is_string($flashes)) {
            $message = $flashes;
            $className = $name;
            $this->please->unsetGlobal($flashKey);
        } elseif (isset($flashes[$name])) {
            $message = $flashes[$name];
            unset($flashes[$name]);
            $this->please->setFlash([$flashKey => $flashes]);
        }
        if (isset($message)) {
            return new Markup('<div class="' . $className . '">' . $message . '</div>', 'UTF-8');
        }
        return '';
    }

    public function getAppCss($reset = false)
    {
        return $this->assetServ->getAppCss($reset);
    }

    public function getLess(array $files = [], bool $raw = false, bool $backOffice = false)
    {
        return $this->assetServ->getLess($files, $raw, $backOffice);
    }

    public function getAppConfig($jsonify = true)
    {

        return $this->assetServ->getAppConfig($jsonify);
    }

    public function getPost()
    {
        return $this->please->serve('post')->get();
    }

    public function setPost($post): void
    {
        $this->please->serve('post')->set($post);
    }

    public function findAcf(string $type, int $limit = -1, array $orderBy = ['created_at' => 'desc'], int $offset = 0)
    {
        return $this->please->getRepo('bloggy')->findAcf($type, $limit, $orderBy, $offset);
    }

    public function findPage(string $slug)
    {
        return collection('page')->findOneBy(['slug' => $slug]);
    }

    public function countBy(string $repo = 'b', array $criteria = [])
    {
        return $repo()->countBy($criteria);
    }

    public function minMax(string $repo = 'b', string $prop = '', array $criteria = [])
    {
        return $repo()->minMax($prop, $criteria);
    }

    public function acf(?array $data, string $fieldsPath, $onNull = null, $isEmptiale = true)
    {
        return $this->please->serve('acf')->getAcf($data, $fieldsPath, $onNull, $isEmptiale);
    }

    public function getAttr($data, $attributePath, $onNull = null, $isEmptiale = true)
    {
        return attr($data, $attributePath, $onNull, $isEmptiale);
    }

    public function attr($data, $fieldsPath, $onNull = null)
    {
        return attr($data, $fieldsPath, $onNull);
    }

    public function getKnpTplPath()
    {
        return '@DovStoneSymfonyBlogAdminBundleQuantBased/partials/twitter_bootstrap_v4_pagination.html.twig';
    }

    public function getBundleEmptyListView(string $message = 'Le dossier est vide.', string $icon = 'ban')
    {
        return new Markup($this->please->serve('template')->getBundleEmptyListView($message, $icon), 'UTF-8');
    }

    public function getFrenchDate($dateTime = null, string $format = "D/d/M/Y")
    {
        return $this->please->serve('time')->getFrenchDate($dateTime, $format);
    }

    public function getMonth($dateTime = null, $type = null, $months_prefixed = null, $ellipsis = null)
    {
        return $this->please->serve('time')->getMonth($dateTime, $type, $months_prefixed, $ellipsis);
    }

    public function getSlug(string $string, $replacement = '-', $lowercase = true)
    {
        return $this->please->serve('string')->getSlug($string, $replacement, $lowercase);
    }

    public function getHtml2Text(string $html = '')
    {
        return $this->please->serve('string')->getHtml2Text($html);
    }

    public function getSections(array $sections = [], $isSgSection = true)
    {
        $view = '';
        foreach ($sections as $section) {
            $view .= $this->section($section[0], $section[1] ?? [], $isSgSection);
        }
        return $view;
    }

    /**
     * @param string|array $filepaths
     */
    public function view($filepaths, array $params = []): Markup
    {
        if (!is_string($filepaths) && !is_array($filepaths)) {
            throw new \InvalidArgumentException('First parameter must be string or array');
        }

        $shortcuts = [
            'comp/' => 'components/',
            'l/'    => 'layouts/',
            'p/'    => 'partials/',
            's/'    => 'sections/',
            'c/'    => 'cards/',
        ];

        $content = '';
        $filepaths = (array) $filepaths; // Cast fonctionne avec string et array en PHP 7

        foreach ($filepaths as $filepath) {
            if (!is_string($filepath)) {
                continue; // ou throw une exception
            }

            $filepath = trim($filepath, '/');

            foreach ($shortcuts as $short => $long) {
                if (strpos($filepath, $short) === 0) {
                    $filepath = $long . substr($filepath, strlen((string)$short));
                } else {
                    $filepath = str_replace('/' . $short, '/' . $long, $filepath);
                }
            }

            $content .= $this->twig->render("{$filepath}.html.twig", $params);
        }

        return new Markup($content, 'UTF-8');
    }

    public function th($filepath, array $params = [])
    {
        return $this->theme($filepath, $params);
    }

    public function section($section, array $params = [], $isSgSection = true)
    {
        $ctn = $this->twig->render("sections/$section.html.twig", $params);
        return new Markup($isSgSection ? '<div sg-section="' . $section . '">' . $ctn . '</div>' : $ctn, 'UTF-8');
    }

    public function s($section, array $params = [], $isSgSection = true)
    {
        return $this->section($section, $params, $isSgSection);
    }

    public function partial($partial, array $params = [])
    {
        return new Markup($this->twig->render("partials/$partial.html.twig", $params), 'UTF-8');
    }

    public function p($partial, array $params = [])
    {
        return $this->partial($partial, $params);
    }

    public function card($card, array $params = [])
    {
        return new Markup($this->twig->render("cards/$card.html.twig", $params), 'UTF-8');
    }

    public function c($card, array $params = [])
    {
        return $this->card($card, $params);
    }

    public function __vendor($vendor, array $params = [])
    {
        return new Markup($this->twig->render("__vendor/$vendor.html.twig", $params), 'UTF-8');
    }

    public function __v($vendor, array $params = [])
    {
        return $this->__vendor($vendor, $params);
    }

    public function layout($filename, array $params = [])
    {
        return new Markup($this->twig->render("layouts/$filename.html.twig", $params), 'UTF-8');
    }

    public function l($filename, array $params = [])
    {
        return $this->layout($filename, $params);
    }

    public function component($filename, array $params = [])
    {
        return new Markup($this->twig->render("components/$filename.html.twig", $params), 'UTF-8');
    }

    public function comp($filename, array $params = [])
    {
        return $this->component($filename, $params);
    }

    public function shortcode($filename, array $params = [])
    {
        return new Markup($this->twig->render("shortcodes/$filename.html.twig", $params), 'UTF-8');
    }

    public function sc($filename, array $params = [])
    {
        return $this->shortcode($filename, $params);
    }

    public function getUserTemplate($filename, array $params = [])
    {
        return new Markup($this->twig->render("user/$filename.html.twig", $params), 'UTF-8');
    }

    public function ut($filename, array $params = [])
    {
        return $this->getUserTemplate($filename, $params);
    }

    public function xhrRendering($filename, array $params = [])
    {
        if (isset($params['noXHR']) && $params['noXHR'] === true) {
            return new Markup($this->twig->render("xhr-rendering/$filename.html.twig", $params), 'UTF-8');
        }
        return $this->please->serve('xhr')->rendering($filename, $params);
    }

    public function xhr($filename, array $params = [])
    {
        return $this->xhrRendering($filename, $params);
    }

    public function skeleton($filename, array $params = [])
    {
        return new Markup($this->twig->render("skeletons/$filename.html.twig", $params), 'UTF-8');
    }

    public function skel($filename, array $params = [])
    {
        return $this->skeleton($filename, $params);
    }

    public function tpl($filename, array $params = [])
    {
        return new Markup($this->twig->render("tpl/$filename.html.twig", $params), 'UTF-8');
    }

    public function compress($content)
    {
        return $this->please->compress($content);
    }

    public function getBody($default = null, $persistHtml = false)
    {
        $post = $this->please->getGlobal('post');
        $html = $post['html'] ?? '';
        return new Markup('<div id="swagg_main" sg-persist-html="' . ($persistHtml ? 'true' : 'false') . '">' . ($html && $html !== '' ? $html : $default ?? '<div sg-empty></div>') . '</div>', 'UTF-8');
    }

    public function getNav($params = [])
    {
        return new Markup($this->please->serve('nav')->getNav($params), 'UTF-8');
    }

    public function getBreadcrumb($params = [])
    {
        return new Markup($this->please->serve('nav')->getBreadcrumb($params), 'UTF-8');
    }

    public function orderBy($items = [], $field)
    {
        return $this->please->serve('nav')->orderBy($items, $field);
    }

    public function path($path, array $params = [])
    {
        return $this->please->serve('url')->getPath($path, $params);
    }

    public function setStorage($bigData, $bundleStorage = null, $sessionIdRelated = null)
    {
        return $this->please->setStorage($bigData, $bundleStorage, $sessionIdRelated);
    }

    public function getStorage($fileName, $bundleStorage = null)
    {
        return $this->please->getStorage($fileName, $bundleStorage);
    }

    public function curl($routeParameters)
    {
        return $this->please->curl($routeParameters);
    }

    public function setGlobal($bigData)
    {
        return $this->please->setGlobal($bigData);
    }

    public function getGlobal($globalName, $default = null)
    {
        return $this->please->getGlobal($globalName, $default);
    }

    public function unsetGlobal($globalName)
    {
        return $this->please->unsetGlobal($globalName);
    }

    public function doCache(callable $onSuccess, $cacheKey, $ttl = "1 month", $byPassDoCache = false)
    {
        return $this->please->serve('cache')->do($onSuccess, $cacheKey, $ttl, $byPassDoCache);
    }

    public function getCached($path)
    {
        return $this->please->serve('cache')->getCached($path);
    }

    public function getCacheKey(mixed $data, string $ttl = "1 day", string $maxJitter = "0 second")
    {
        return $this->please->serve('cache')->getCacheKey($data, $ttl, $maxJitter);
    }

    public function getCacheRequestKey($cachekey = null)
    {
        return $this->please->serve('cache')->getCacheRequestKey($cachekey);
    }

    public function getDateTime($datetime = null)
    {
        return $this->please->serve('time')->getDateTime($datetime);
    }

    public function getTimeAgo($datetime = null)
    {
        return $this->please->serve('time')->getTimeAgo($datetime);
    }

    public function getTimeRemaining($future_date, $format = "%D jours, %H heures, %I minutes, %S secondes")
    {
        return $this->please->serve('time')->getTimeRemaining($future_date, $format);
    }

    public function getDaysOptions($label = 'Jour', $selected = null, $required = true)
    {
        return new Markup($this->please->serve('time')->getDaysOptions($label, $selected, $required), 'UTF-8');
    }

    public function getMonthsOptions($label = 'Mois', $selected = null, $required = true, $short = null)
    {
        return new Markup($this->please->serve('time')->getMonthsOptions($label, $selected, $required, $short), 'UTF-8');
    }

    public function getYearsOptions($label = 'Années', $selected = null, $required = true, $from = null, $to = 1950, $order = 'desc')
    {
        return new Markup($this->please->serve('time')->getYearsOptions($label, $selected, $required, $from, $to, $order), 'UTF-8');
    }

    public function ellipsisDate($datetime, $format = "D d M Y")
    {
        return $this->please->serve('time')->ellipsisDate($datetime, $format);
    }

    public function millisecAsDuration(float $milliseconds)
    {
        return $this->please->serve('time')->millisecAsDuration($milliseconds);
    }

    public function ellipsisText(string $string, int $max = 100, string $append = '...')
    {
        return $this->please->serve('string')->ellipsisText($string, $max, $append);
    }

    public function indexify($zeroIndexedKey, $limit)
    {
        $page = $this->please->getInput('page', 1);
        $page = $page < 1 ? 1 : $page;
        $index = '<span class="d-inline d-lg-none">#</span>' . (($page * $limit + $zeroIndexedKey) - 1) - $limit + 2;
        // $index = (($page * $limit + $zeroIndexedKey) - 1) - $limit + 2;
        return new Markup($index, 'UTF-8');
    }

    public function json_encode($value, $fags = 0, $depth = 512)
    {
        return json_encode($value, $fags, $depth);
    }

    public function getUserRole($user = null)
    {
        return $this->please->serve('security')->getUserRole($user);
    }

    public function getUserFullName($user = null)
    {
        return $this->please->serve('user')->getUserFullName($user);
        /*$user = $user ?? $this->please->getCurrentUser() ?? null;
        if($user){
            return attr($user, 'lastname').' '.attr($user, 'firstname');
        }
        return null;*/
    }

    public function isHome()
    {
        return $this->please->serve('dir')->isHome();
    }

    public function priceFormat($number, int $decimals = 0, string $dec_point = ',', string $thousands_sep = ' ')
    {
        return $this->please->serve('string')->priceFormat($number, $decimals, $dec_point, $thousands_sep);
    }

    public function countViews($number = null, $viewsText = ' vues', $noViewsText = 'Aucune vue')
    {
        return $this->please->serve('string')->countViews($number, $viewsText, $noViewsText);
    }

    public function setHits($document, string $type, $frequency = 'daily')
    {
        $this->please->serve('hit')->setVal($document, $type, $frequency);
    }

    public function getHits($document, $type, $frequency = 'all')
    {
        return $this->please->serve('hit')->getVal($document, $type, $frequency);
    }

    public function isActive($post)
    {
        return $this->please->serve('nav')->isActive($post);
    }

    public function routeActiveClass($routeName, $activeClass = 'active')
    {
        return $this->please->serve('nav')->routeActiveClass($routeName, $activeClass);
    }

    public function getCountriesOptions($selected = "Côte d'Ivoire", $countries = null)
    {
        return $this->please->serve('mix')->getCountriesOptions($selected, $countries);
    }

    public function getCountriesCallCode($selected = 225, $countries = null)
    {
        return $this->please->serve('mix')->getCountriesCallCode($selected, $countries);
    }

    public function fancyboxData($big_items, $title = null)
    {
        return $this->please->serve('mix')->fancyboxData($big_items, $title);
    }

    public function buildTree($collection, $renderType = 'option', $preventId = null, $params = [])
    {
        return $this->please->serve('dir')->buildTree($collection, $renderType, $preventId, $params);
    }

    public function getCurrentUpdatedAt(array|string|null $params = null): ?string
    {
        return $this->please->serve('cache')->getCurrentUpdatedAt($params);
    }

    public function getLatestModificationDate(string $dir = 'theme'): string
    {
        return $this->please->serve('time')->getLatestModificationDate($dir);
    }

    public function getQueryRedir(): string
    {
        return $this->please->serve('url')->getQueryRedir();
    }

    public function uId($length = 8, $prefix = ''): string
    {
        return $this->please->serve('string')->uId($length, $prefix);
    }

    public function uId8(): string
    {
        return $this->please->serve('string')->uId8();
    }

    public function facebookPage($pagename): string
    {
        return new Markup($this->please->serve('string')->facebookPage($pagename), 'UTF-8');
    }

    public function renderMetas($keys = []): string
    {
        return new Markup($this->please->serve('string')->renderMetas($keys), 'UTF-8');
    }

    public function disqusThread($page_url = null, $page_identifier = null): string
    {
        return new Markup($this->please->serve('string')->disqusThread($page_url, $page_identifier), 'UTF-8');
    }

    public function mobileDetect()
    {
        return $this->please->serve('mobile_detect')->mobileDetect();
    }

    public function isMobile()
    {
        return $this->please->serve('mobile_detect')->isMobile();
    }

    public function isTablet()
    {
        return $this->please->serve('mobile_detect')->isTablet();
    }

    public function isDesktop()
    {
        return $this->please->serve('mobile_detect')->isDesktop();
    }

    public function isNotDesktop()
    {
        return $this->please->serve('mobile_detect')->isNotDesktop();
    }

    public function isSearchEngineBot(): bool
    {
        return $this->please->serve('mix')->isSearchEngineBot();
    }

    public function isNotSearchEngineBot(): bool
    {
        return $this->please->serve('mix')->isNotSearchEngineBot();
    }

    public function TTL(string $humanTime = "1 day", string $maxJitter = "0 second"): int
    {
        return $this->please->serve('time')->getTTL($humanTime, $maxJitter);
    }

    public function badge(array $data, $separator = '|')
    {
        return $this->please->serve('mix')->badge($data, $separator);
    }

    public function bodyClassNames($showPreloader = true)
    {
        return $this->please->serve('mix')->bodyClassNames($showPreloader);
    }

    public function resetAssets()
    {
        return $this->please->serve('asset')->resetAssets();
    }

    public function internalErrorPage($options = [], $data = [])
    {
        return $this->please->serve('log')->internalErrorPage($options, $data);
    }

    public function getNavigationData()
    {
        return $this->please->serve('cache')->getNavigationData();
    }

    public function getService($service_name)
    {
        return $this->please->serve($service_name);
    }

    public function marketplace()
    {
        return $this->please->serve('marketplace');
    }

    public function isBot(): bool
    {
        return $this->please->isBot();
    }

    public function isXHR()
    {
        return $this->please->isXHR();
    }

    public function isNotXHR()
    {
        return !$this->please->isXHR();
    }

    public function beginHTML($params = [], $cache = true)
    {
        return $this->please->serve('template')->beginHTML($params, $cache);
    }

    public function endHTML($params = [], $cache = true)
    {
        return $this->please->serve('template')->endHTML($params, $cache);
    }

    public function userCan($role = 'admin', $strictRole = true, $user = null)
    {
        return $this->please->serve('security')->userCan($role, $strictRole, $user);
    }

    public function userIs($role = 'admin', $strictRole = true, $user = null)
    {
        return $this->please->serve('security')->userCan($role, $strictRole, $user);
    }

    public function csrfToken(bool $input = true)
    {
        $token = $this->please->serve('security')->getCsrfToken();
        if (!$input) {
            return $token;
        }
        return new Markup('<input type="hidden" name="_token" value="' . $token . '">', 'UTF-8');
    }

    public function fileWeightFormatShort($n)
    {
        return $this->please->serve('string')->fileWeightFormatShort($n);
    }

    public function randomify($list, $limit = null)
    {
        return $this->please->serve('mix')->randomify($list, $limit);
    }

    public function getColorsGrid()
    {
        return [
            ['#ff281b', '#ffb88e'],
            ['#89b85a', '#005048'],
            ['#98f0e2', ''],
            ['#b49ac9', '#4a2964'],
            ['#925a9f', '#e7e0ec'],
            ['#f1af21', '#005804'],
            ['#f137a6', '#ffeb3b'],
            ['#509bf6', '#e9ceff']
        ];
    }

    public function i($code, $classNames = '')
    {
        return new Markup($this->please->serve('string')->i($code, $classNames), 'UTF-8');
    }

    public function gTag($id)
    {
        return $this->please->serve('template')->gTag($id);
    }

    public function convertToKnpPaginatorBundle($items = [], $perPage = 15)
    {
        return $this->please->convertToKnpPaginatorBundle($items, $perPage);
    }

    public function jasonPaginator($items = [], $perPage = 15)
    {
        return $this->please->jasonPaginator($items, $perPage);
    }

    public function mimicKnpPaginator(int $total, $perPage = 15)
    {
        return $this->please->mimicKnpPaginator($total, $perPage);
    }

    public function getPaginationOffset(int $limit, string $pageQuery = 'page')
    {
        return $this->please->getPaginationOffset($limit, $pageQuery);
    }

    public function getTableHeadCssLabel($params = [])
    {
        $params = (object) array_merge([
            'labels' => [],
            'multiCheck' => true,
            'maxWidth' => 992,
            'selector' => 'table',
            'labelWidth' => 90,
            'labelAutoLineHeight' => null,
            'height' => null,
            'minHeight' => 65,
            'display' => 'block',
            'evenBgColor' => 'transparent',
            'cssRules' => null,
            'labelCssRules' => null,
            'tableSkeleton' => null
        ], $params);

        $params->height = $params->height ? ";height:{$params->height}px;" : "";
        $cssRules = $params->cssRules ?? null;
        $labelCssRules = $params->labelCssRules ? $params->selector . " td::before{{$params->labelCssRules}}" : "";
        $multiCheck = $params->multiCheck ?? false;

        $css = "<style>
        @media screen and (max-width:{$params->maxWidth}px){
            $params->selector thead{display:none}
            $params->selector tbody td{display:$params->display;$params->height;position:relative;align-items:center}    
            $params->selector tbody td:nth-child(2n+1){background-color:$params->evenBgColor}
            $params->selector $cssRules
            $labelCssRules
        ";
        if ($multiCheck) {
            array_splice($params->labels, 1, 0, '');
        }
        foreach ($params->labels as $i => $label) {
            ++$i;
            $labelAutoLineHeight = $params->labelAutoLineHeight ? ";line-height:" . ($params->minHeight - 17) . "px;" : "";
            $css .= $params->selector . ' td:nth-of-type(' . $i . ')::before{content:"' . $label . '";width:' . $params->labelWidth . 'px' . $labelAutoLineHeight . ';display:table;text-align:left}';
        }
        $css .= '}</style>';

        if ($params->tableSkeleton) {
            list($hiddenElementSelector, $height, $cssClass) = $params->tableSkeleton;
            $css .= '<style>' . $hiddenElementSelector . '{display:none}</style><div class="table-skeleton ' . $cssClass . '" style="height:' . $height . '"></div><script type="text/javascript">setTimeout(()=>{document.querySelectorAll(\'' . $hiddenElementSelector . '\').forEach(el=>el.style.display=\'block\');document.querySelector(\'.table-skeleton\').style.display=\'none\'},600)</script>';
        }

        return new Markup($css, 'UTF-8');
    }

    public function dd($data)
    {
        dd($data);
    }

    public function _dump($data)
    {
        dump($data);
    }
}
