<?php

namespace DovStone\Bundle\SymfonyBlogAdminBundleQuantBased\Service;

use DovStone\Bundle\SymfonyBlogAdminBundleQuantBased\Service\PleaseService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use League\ColorExtractor\ColorExtractor;
use League\ColorExtractor\Palette;
use League\ColorExtractor\Color;

class ImageService extends AbstractController
{
    private $please;
    private $url;
    private $fileCdnOrigin;

    public function __construct(PleaseService $please)
    {
        $this->please = $please;
        $this->url = $this->please->serve('url')->getUrl();
        $this->fileCdnOrigin = $this->please->serve('env')->getAppEnv('CDN_FILE_ORIGIN', $this->url);
    }

    public function resize(
        $src,
        $newWidth = null,
        $newHeight = null,
        $mode = 'r',
        $extension = "jpeg",
        $bgColor = "transparent"
    ): string {
        if (stripos($this->please->serve('url')->getUrl(), $this->fileCdnOrigin) !== false) {
            return $this->localResize($src, $newWidth, $newHeight, $mode, $extension, $bgColor);
        }
        $basename = basename(parse_url($src, PHP_URL_PATH));
        $filename = pathinfo($basename, PATHINFO_FILENAME);
        $remote_src = trim($this->fileCdnOrigin, "/") . "/uploads-sizes/{$filename}-{$mode}-{$newWidth}x{$newHeight}.$extension";
        return $remote_src;
    }

    private function localResize($src, $newWidth, $newHeight, $mode, $extension, $bgColor)
    {
        if (!$src) {
            return $this->please->serve('asset')->getAsset($this->please->serve('env')->getAppEnv('LAZY_LOAD_IMAGE_PLACEHOLDER'));
        }

        if (!file_exists($pathname = $this->please->serve('dir')->dirPath('public/uploads-sizes'))) {
            mkdir($pathname);
        }

        if ($mode === 'c') {
            return $this->crop($src, $newWidth, $newHeight, $extension, $bgColor);
        }

        $real_image_path = urldecode($this->getDst($src));

        if (!file_exists($real_image_path)) {
            return $src;
        }

        list($w, $h) = getimagesize($real_image_path);

        $nw = $newWidth ?? $w;
        $nh = $newHeight ?? $h;

        $ow = $nw;
        $oh = $nh;

        $ratio = $w / $h;

        if ($nw / $nh > $ratio) {
            $nw = $nh * $ratio;
        } else {
            $nh = $nw / $ratio;
        }

        $dst_x = ($ow - $nw) / 2;
        $dst_y = ($oh - $nh) / 2;

        $nw = (int) $nw;
        $nh = (int) $nh;

        $local_src = $this->getLocalSource($real_image_path, 'r', $ow, $oh, $extension);

        if (!file_exists($real_image_path)) {
            return '#';
        }
        // check if resized image already exists
        if (file_exists($this->getLocalSource($real_image_path, 'r', $nw, $nh, $extension))) {
            return $this->getHttpSource($local_src, $ow, $oh);
        }

        $dst_image = imagecreatetruecolor($ow, $oh);

        $src_image = $this->createImage($src, $real_image_path);

        imagefill($dst_image, 0, 0, $this->getBgColor($dst_image, $bgColor));
        imagecopyresampled($dst_image, $src_image, $dst_x, $dst_y, 0, 0, $nw, $nh, $w, $h);
        switch ($extension) {
            case 'jpg':
            case 'jpeg':
                imagejpeg($dst_image, $local_src, 100);
                break;
            case 'png':
                imagepng($dst_image, $local_src, 100);
                break;

            default:
                # code...
                break;
        }
        imagedestroy($dst_image);

        return $this->getFinalHttpSource($real_image_path, 'r', $ow, $oh, $extension);
    }

    private function crop($src, $newWidth = null, $newHeight = null, $extension = "jpeg", $bgColor = "transparent")
    {
        $real_image_path = urldecode($this->getDst($src));

        if (!file_exists($real_image_path)) {
            return $src;
        }

        list($xx, $yy) = getimagesize($real_image_path);

        $nw = $newWidth ?? $xx;
        $nh = $newHeight ?? $yy;

        $ow = $nw;
        $oh = $nh;

        $ratio_thumb = $nw / $nh;

        if ($yy == 0) {
            return '#';
        }

        $ratio = $xx / $yy;

        if ($ratio >= $ratio_thumb) {
            $yo = $yy;
            $xo = ceil(($yo * $nw) / $nh);
            $xo_ini = ceil(($xx - $xo) / 2);
            $xy_ini = 0;
        } else {
            $xo = $xx;
            $yo = ceil(($xo * $nh) / $nw);
            $xy_ini = ceil(($yy - $yo) / 2);
            $xo_ini = 0;
        }

        $local_src = $this->getLocalSource($real_image_path, 'c', $ow, $oh, $extension);

        if (!file_exists($real_image_path)) {
            return '#';
        }

        // check if cropped image already exists
        if (file_exists($this->getLocalSource($real_image_path, 'c', $nw, $nh, $extension))) {
            return $this->getHttpSource($local_src, $ow, $oh);
        }

        $dst_image = imagecreatetruecolor($ow, $oh);

        $src_image = $this->createImage($src, $real_image_path);

        //$xo_ini = $xy_ini = 0;
        imagefill($dst_image, 0, 0, $this->getBgColor($dst_image, $bgColor));
        imagecopyresampled($dst_image, $src_image, 0, 0, $xo_ini, $xy_ini, $nw, $nh, $xo, $yo);
        switch ($extension) {
            case 'jpg':
            case 'jpeg':
                imagejpeg($dst_image, $local_src, 100);
                break;
            case 'png':
                imagepng($dst_image, $local_src, 100);
                break;

            default:
                # code...
                break;
        }
        imagedestroy($dst_image);

        return $this->getFinalHttpSource($real_image_path, 'c', $ow, $oh, $extension);
    }

    private function getHttpSource($real_image_path, $w, $h)
    {
        $http_src = $this->please->serve('url')->getUrl(preg_replace('/(.*)(uploads-sizes)(.*)/m', '$2$3', $real_image_path));
        return $http_src;
    }

    private function getDst($real_image_path)
    {
        $dst = $this->please->serve('dir')->dirPath('public/' . preg_replace('/(.*)(uploads)(.*)/m', '$2$3', $real_image_path));
        return $dst;
    }

    private function getLocalSource($real_image_path, $mode, $nw, $nh, $extension)
    {
        $new_file_name = $this->please->serve('string')->getSlug(preg_replace('/(.*)(uploads\/)(.*)/m', "$3", $real_image_path));
        $new_real_image_path = explode('/uploads/', $real_image_path)[0] . '/uploads-sizes/' . $new_file_name;
        $new_real_image_path = preg_replace('/(.*)(uploads-sizes\/)(.*)(-)(.*)/m', "$1$2$3.$5", $new_real_image_path);
        //$src = preg_replace('/(.+)(.)(jpg|jpeg|png|gif|webp|bmp)/m', "$1-{$mode}-{$nw}x{$nh}.$3", $new_real_image_path);
        $src = preg_replace('/(.+)(.)(jpg|jpeg|png|gif|webp|bmp)/m', "$1-{$mode}-{$nw}x{$nh}.$extension", $new_real_image_path);
        return $src;
    }

    private function getFinalHttpSource($real_image_path, $mode, $nw, $nh, $extension)
    {
        $src = $this->getLocalSource($real_image_path, $mode, $nw, $nh, $extension);
        return $this->please->serve('url')->getUrl(preg_replace('/(.*)(uploads-sizes)(.*)/m', '$2$3', $src));
    }

    private function getBgColor($dst_image, $bgColor)
    {
        if ($bgColor == 'transparent') {
            imagesavealpha($dst_image, true);
            imagealphablending($dst_image, false);
            $bgColor = imagecolorallocatealpha($dst_image, 0, 0, 0, 127);
        } elseif (is_array($bgColor) && count($bgColor) === 3) {
            $bgColor = imagecolorallocate($dst_image, $bgColor[0] ?? 255, $bgColor[1] ?? 255, $bgColor[2] ?? 255);
        } else {
            $bgColor = imagecolorallocate($dst_image, 0, 0, 0);
        }
        return $bgColor;
    }

    private function createImage($src, $real_image_path)
    {
        switch (exif_imagetype($real_image_path)) {
            case IMAGETYPE_WEBP:
                $src_image = @imagecreatefromwebp($real_image_path);
                break;
            case IMAGETYPE_JPEG:
                $src_image = @imagecreatefromjpeg($real_image_path);
                break;
            case IMAGETYPE_GIF:
                $src_image = @imagecreatefromgif($real_image_path);
                break;
            case IMAGETYPE_PNG:
                $src_image = @imagecreatefrompng($real_image_path);
                break;
            default:
                return $src;
        }
        return $src_image;
    }
}
