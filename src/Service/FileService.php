<?php

namespace DovStone\Bundle\SymfonyBlogAdminBundleQuantBased\Service;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use DovStone\Bundle\SymfonyBlogAdminBundleQuantBased\Service\PleaseService;
use Symfony\Component\HttpFoundation\Request;

class FileService extends AbstractController
{
    private $please;
    private $uploadsDir;
    private $publicRoot;
    private $dirPath;
    public $url;
    private $fileCdnOrigin;

    private $allowedImageFormats = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'svg'];
    private $allowedFileTypes = [
        'document' => ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt', 'csv'],
        'video'    => ['mp4', 'avi', 'mov', 'wmv', 'flv', 'mkv', 'webm'],
        'audio'    => ['mp3', 'wav', 'ogg', 'aac', 'flac'],
        'archive'  => ['zip', 'rar', '7z', 'tar', 'gz'],
    ];

    public function __construct(PleaseService $please)
    {
        $this->please = $please;
        $this->publicRoot = rtrim($this->please->serve('dir')->dirPath('public'), DIRECTORY_SEPARATOR);
        $this->uploadsDir = $this->publicRoot . DIRECTORY_SEPARATOR . 'uploads';
        $this->dirPath = $this->uploadsDir;
        $this->url = $this->please->serve('url')->getUrl();
        $this->fileCdnOrigin = $this->please->serve('env')->getAppEnv('CDN_FILE_ORIGIN', $this->url);
    }

    public function uploadFile(array $params)
    {
        $params = array_merge([
            'file'        => null,
            'fileName'    => uniqid(),
            'dirPath'     => '',
            'convertTo'   => 'jpeg',
            'quality'     => 90,
            'maxFileSize' => 100, // 100MB
            'maxWidth'    => 1920,
            'maxHeight'   => 1080,
            'textOverlay' => null,
            'onSuccess'   => function () {},
            'onError'     => function () {},
        ], $params);

        $maxFileSize = $params['maxFileSize'] * 1024 * 1024;

        try {
            if (!isset($params['file']['tmp_name'])) {
                throw new \Exception('Aucun fichier fourni');
            }

            $file = $params['file'];
            $fileSize = isset($file['size']) ? (int)$file['size'] : filesize($file['tmp_name']);

            if ($fileSize > $maxFileSize) {
                throw new \Exception("Fichier trop volumineux");
            }

            $destDir = $this->uploadsDir;
            if (!empty($params['dirPath'])) {
                $destDir .= DIRECTORY_SEPARATOR . trim($params['dirPath'], DIRECTORY_SEPARATOR);
            }

            if (!is_dir($destDir) && !mkdir($destDir, 0777, true)) {
                throw new \Exception("Impossible de créer le dossier: {$destDir}");
            }

            $result = $this->isImage($file) ? $this->uploadImage($params, $destDir) : $this->uploadRegularFile($params, $destDir);

            if (is_callable($params['onSuccess'])) {
                return call_user_func($params['onSuccess'], $result);
            }
            return $result;
        } catch (\Exception $e) {
            return call_user_func($params['onError'], $e->getMessage());
        }
    }

    private function uploadImage($params, $destDir)
    {
        $file = $params['file'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

        $canWebp = function_exists('imagewebp');
        $targetFormat = $params['convertTo'] && $canWebp ? strtolower($params['convertTo']) : $ext;

        if (!in_array($ext, $this->allowedImageFormats)) {
            throw new \Exception("Format d'image non supporté: {$ext}");
        }

        $img = $this->loadImage($file['tmp_name'], $ext);
        if (!$img) {
            throw new \Exception("Impossible de charger l'image (format: {$ext})");
        }

        $img = $this->resizeImage($img, $params['maxWidth'], $params['maxHeight']);

        if (!empty($params['textOverlay']) && !empty($params['textOverlay']['text'])) {
            $img = $this->addTextToImage($img, $params['textOverlay']['text'], $params['textOverlay']);
        }

        $baseName = $this->sanitizeFileName($params['fileName']);
        $finalName = $baseName . '.' . $targetFormat;
        $filePath = $destDir . DIRECTORY_SEPARATOR . $finalName;

        $saved = $this->saveImage($img, $filePath, $targetFormat, $params['quality']);
        $width  = imagesx($img);
        $height = imagesy($img);
        imagedestroy($img);

        if (!$saved) {
            throw new \Exception("Impossible de sauvegarder l'image (format: {$targetFormat})");
        }

        $relative_path = str_replace($this->dirPath, 'uploads/', $destDir) . '/' . $finalName;
        $relativeUrl = trim(preg_replace('~/+~', '/', $relative_path), '/');

        return (object) [
            'success'           => true,
            'type'              => 'image',
            'human_type'        => $this->humanType($targetFormat),
            'is_image'          => true,
            'extension'         => $targetFormat,
            'file_size'         => filesize($filePath),
            'filename'          => $baseName,
            'extended_filename' => $finalName,
            'relative_url'      => $relativeUrl,
            'absolute_url'      => $this->please->serve('url')->getUrl($relativeUrl),
            'absolute_path'     => $filePath,
            'relative_path'     => $relativeUrl,
            'width'             => $width,
            'height'            => $height
        ];
    }

    private function uploadRegularFile($params, $destDir)
    {
        $file = $params['file'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

        if (!$this->isAllowedFileType($ext)) {
            throw new \Exception("Type de fichier non autorisé: {$ext}");
        }

        $baseName = $this->sanitizeFileName($params['fileName']);
        $finalName = $baseName . '.' . $ext;
        $filePath = $destDir . DIRECTORY_SEPARATOR . $finalName;

        if (file_exists($filePath)) {
            $finalName = $baseName . '_' . uniqid() . '.' . $ext;
            $filePath = $destDir . DIRECTORY_SEPARATOR . $finalName;
        }

        move_uploaded_file($file['tmp_name'], $filePath);

        $relative_path = str_replace($this->dirPath, 'uploads/', $destDir) . '/' . $finalName;
        $relativeUrl = trim(preg_replace('~/+~', '/', $relative_path), '/');
        $humanType = $this->humanType($ext);
        $fileData = [
            'success'           => true,
            'type'              => $this->getFileCategory($ext),
            'human_type'        => $humanType,
            'is_image'          => false,
            'extension'         => $ext,
            'file_size'         => filesize($filePath),
            'filename'          => $baseName,
            'extended_filename' => $finalName,
            'relative_url'      => $relativeUrl,
            'absolute_url'      => $this->please->serve('url')->getUrl($relativeUrl),
            'absolute_path'     => $filePath,
            'relative_path'     => $relativeUrl,
        ];

        if ($humanType == 'video') {
            $thumbnail = $this->extractVideoThumbnail($file, $baseName);
            unset($thumbnail->absolute_path, $thumbnail->relative_path);
            $fileData['thumbnail'] = $thumbnail;
        }

        return (object) $fileData;
    }

    private function addTextToImage($img, $text, $options = [])
    {
        $defaults = [
            'font_size' => 20,
            'font_color' => [255, 255, 255],
            'opacity' => 0, // 0 = transparent, 100 = opaque
            'position' => 'middle', // top-left, top-center, top-right, middle, bottom-left, etc.
            'font_path' => dirname(__DIR__) . DIRECTORY_SEPARATOR . 'Fonts' . DIRECTORY_SEPARATOR . 'DMSans-Medium.ttf',
            'shadow' => false,
            'margin' => 10, // marge par rapport aux bords
        ];

        $opts = array_merge($defaults, $options);

        // Vérifie police
        if (!file_exists($opts['font_path'])) {
            throw new \Exception("Police introuvable : " . $opts['font_path']);
        }

        // Conversion de l'opacité
        $alpha = 127 - round($opts['opacity'] * 127 / 100);
        $color = imagecolorallocatealpha($img, $opts['font_color'][0], $opts['font_color'][1], $opts['font_color'][2], $alpha);

        // Taille du texte
        $bbox = imagettfbbox($opts['font_size'], 0, $opts['font_path'], $text);
        $textWidth = abs($bbox[2] - $bbox[0]);
        $textHeight = abs($bbox[7] - $bbox[1]);

        $imgWidth = imagesx($img);
        $imgHeight = imagesy($img);

        // Calcul position X
        switch ($opts['position']) {
            case 'top-left':
            case 'middle-left':
            case 'bottom-left':
                $x = $opts['margin'];
                break;
            case 'top-center':
            case 'middle':
            case 'bottom-center':
                $x = ($imgWidth - $textWidth) / 2;
                break;
            case 'top-right':
            case 'middle-right':
            case 'bottom-right':
                $x = $imgWidth - $textWidth - $opts['margin'];
                break;
            default:
                $x = $opts['margin'];
        }

        // Calcul position Y
        switch ($opts['position']) {
            case 'top-left':
            case 'top-center':
            case 'top-right':
                $y = $opts['margin'] + $textHeight;
                break;
            case 'middle-left':
            case 'middle':
            case 'middle-right':
                $y = ($imgHeight / 2) + ($textHeight / 2);
                break;
            case 'bottom-left':
            case 'bottom-center':
            case 'bottom-right':
                $y = $imgHeight - $opts['margin'];
                break;
            default:
                $y = $opts['margin'] + $textHeight;
        }

        // Ombre si demandée
        if ($opts['shadow']) {
            $shadowAlpha = min(127, $alpha + 40);
            $shadowColor = imagecolorallocatealpha($img, 0, 0, 0, $shadowAlpha);
            imagettftext($img, $opts['font_size'], 0, $x + 2, $y + 2, $shadowColor, $opts['font_path'], $text);
        }

        // Texte principal
        imagettftext($img, $opts['font_size'], 0, $x, $y, $color, $opts['font_path'], $text);

        return $img;
    }

    private function isImage($file)
    {
        return @getimagesize($file['tmp_name']) !== false;
    }

    private function isAllowedFileType($ext)
    {
        foreach ($this->allowedFileTypes as $types) {
            if (in_array($ext, $types)) return true;
        }
        return false;
    }

    private function getFileCategory($ext)
    {
        foreach ($this->allowedFileTypes as $cat => $types) {
            if (in_array($ext, $types)) return $cat;
        }
        return 'other';
    }

    private function loadImage($path, $ext)
    {
        switch ($ext) {
            case 'jpg':
            case 'jpeg':
                return imagecreatefromjpeg($path);
            case 'png':
                return imagecreatefrompng($path);
            case 'gif':
                return imagecreatefromgif($path);
            case 'webp':
                if (!function_exists('imagecreatefromwebp')) {
                    return false; // Extension non disponible
                }
                // Ajouter un @ pour supprimer les warnings/errors
                return @imagecreatefromwebp($path);
            default:
                return false;
        }
    }

    private function resizeImage($img, $maxW, $maxH)
    {
        $w = imagesx($img);
        $h = imagesy($img);
        if (!$maxW && !$maxH) return $img;
        $ratio = $w / $h;

        if ($maxW && $w > $maxW) {
            $w = $maxW;
            $h = $maxW / $ratio;
        }
        if ($maxH && $h > $maxH) {
            $h = $maxH;
            $w = $maxH * $ratio;
        }

        $dst = imagecreatetruecolor($w, $h);
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
        imagecopyresampled($dst, $img, 0, 0, 0, 0, $w, $h, imagesx($img), imagesy($img));
        imagedestroy($img);
        return $dst;
    }

    private function saveImage($img, $path, $ext, $q)
    {
        switch ($ext) {
            case 'jpg':
            case 'jpeg':
                return imagejpeg($img, $path, $q);
            case 'png':
                return imagepng($img, $path, (int)floor((100 - $q) / 10));
            case 'gif':
                return imagegif($img, $path);
            case 'webp':
                return function_exists('imagewebp') ? imagewebp($img, $path, $q) : false;
            default:
                return false;
        }
    }

    private function sanitizeFileName($n)
    {
        $n = iconv('UTF-8', 'ASCII//TRANSLIT', $n);
        $n = preg_replace('/[^A-Za-z0-9_\-]/', '_', $n);
        return substr(trim($n, '_') ?: uniqid('file_'), 0, 200);
    }

    private function humanType($extension)
    {
        $imageExtensions = ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp', 'avif', 'svg'];
        $videoExtensions = ['mp4', 'mkv', 'avi', 'mov', 'wmv', 'flv', 'webm'];
        $audioExtensions = ['mp3', 'wav', 'ogg', 'flac', 'aac', 'm4a', 'wma'];
        $pdfExtensions = ['pdf'];
        $wordExtensions = ['doc', 'docx'];
        $excelExtensions = ['xls'];

        if (in_array($extension, $imageExtensions)) return 'image';
        if (in_array($extension, $videoExtensions)) return 'video';
        if (in_array($extension, $pdfExtensions)) return 'pdf';
        if (in_array($extension, $wordExtensions)) return 'word';
        if (in_array($extension, $audioExtensions)) return 'audio';
        if (in_array($extension, $excelExtensions)) return 'excel';
        return $extension;
    }

    public function beautifyFilesArray($files)
    {
        $names = array('name' => 1, 'type' => 1, 'tmp_name' => 1, 'error' => 1, 'size' => 1);

        foreach ($files as $key => $part) {
            $key = (string) $key;
            if (isset($names[$key]) && is_array($part)) {
                foreach ($part as $position => $value) {
                    $files[$position][$key] = $value;
                }
                unset($files[$key]);
            }
        }

        return $files;
    }

    public function deepDelete(array $files, ?callable $then = null)
    {
        $files = [];
        if (!isset($file[0])) {
            $files[] = $files;
        }

        foreach ($files as $file) {
            // deletion from DB
            if (isset($file['id'])) {
                collection('file')->delete($file['id']);
            }
            // deletion from storages
            if (isset($file['meta'])) {
                $this->deleteFromFolder($file['meta']);
            }
        }

        if (is_callable($then)) {
            $then($files);
        }

        return null;
    }

    public function deleteFromFolder(array $meta)
    {
        if (stripos($this->url, $this->fileCdnOrigin) !== false) {
            // Suppression locale
            $dir = $this->please->serve('dir');
            $relativePath = $dir->dirPath("public/" . $meta["relative_url"]);
            $sizesPath = $dir->dirPath("public/uploads-sizes/");
            $filename = $meta["filename"];

            // Supprimer d'abord la vignette si elle existe
            if (isset($meta['thumbnail']) && !empty($meta['thumbnail'])) {
                $thumbnailPath = $dir->dirPath("public/" . $meta['thumbnail']["relative_url"]);
                if (file_exists($thumbnailPath)) {
                    unlink($thumbnailPath);
                }
            }

            // Supprimer le fichier principal
            if (file_exists($relativePath)) {
                unlink($relativePath);
            }

            // Supprimer les fichiers de tailles
            foreach (['webp', 'jpeg'] as $ext) {
                foreach (['-c-*', '-r-*'] as $pattern) {
                    foreach (glob($sizesPath . $filename . $pattern . ".$ext") as $file) {
                        unlink($file);
                    }
                }
            }

            $swal = [
                'icon' => 'success',
                'title' => "Données supprimées"
            ];
        } else {
            // Suppression distante
            $request = $this->please->getRequest();
            $request->request->add(['meta' => $meta]);
            $response = $this->please->serve('file_transfer')->send($request, '_files/delete-from-folder');

            if (isset($response['success'])) {
                $swal = [
                    'icon' => 'success',
                    'title' => $response['message'] ?? "Données supprimées"
                ];
            } else {
                $swal = [
                    'icon' => 'error',
                    'title' => $response['message'] ?? "Quelque chose s'est mal passé. Veuillez réessayer plus tard.",
                    'exception' => $response['exception'] ?? ''
                ];
            }
        }

        return $this->please->serve('response')->jsonResponse([
            'redirect' => false,
            'swal' => $swal
        ]);
    }

    public function cleanUnaccesibleFiles($logToFile = false)
    {
        // Initialiser le logging
        $log = [];
        $log[] = "[" . date('Y-m-d H:i:s') . "] Début du nettoyage";

        $batchSize = 100;
        $skip = 0;
        $totalDeleted = 0;
        $totalProcessed = 0;

        do {
            $files = collection('file')->limit($batchSize)->offset($skip)->fetch();

            if (empty($files)) {
                break;
            }

            foreach ($files as $file) {
                $totalProcessed++;
                $fileId = (string)$file['id'];
                $absoluteUrl = $file['meta']['absolute_url'] ?? null;

                if (!$absoluteUrl) {
                    $log[] = "Fichier ID $fileId ignoré (pas d'URL)";
                    continue;
                }

                // Vérifier l'accessibilité
                $accessible = $this->checkUrlAccessibility($absoluteUrl);

                if (!$accessible) {
                    try {
                        collection('file')->delete($fileId);
                        $totalDeleted++;
                        $log[] = "SUPPRIMÉ - ID: $fileId, URL: $absoluteUrl";
                    } catch (\Exception $e) {
                        $log[] = "ERREUR suppression ID $fileId: " . $e->getMessage();
                    }
                }
            }

            $skip += $batchSize;
            $log[] = "Lot traité: $skip fichiers analysés";
        } while (!empty($files));

        // Résumé final
        $log[] = "=== RÉSUMÉ ===";
        $log[] = "Total traité: $totalProcessed";
        $log[] = "Total supprimé: $totalDeleted";
        $log[] = "Fin du nettoyage: " . date('Y-m-d H:i:s');

        if ($logToFile) {
            file_put_contents('cleanup_log_' . date('Ymd_His') . '.txt', implode("\n", $log));
        }

        return new JsonResponse([
            'success' => true,
            'log' => $log
        ]);
    }

    public function convertUploadedFileToArray($file, $input_name)
    {
        $fileArray = [
            'name' => $file->getClientOriginalName(),
            'type' => $file->getClientMimeType(),
            'tmp_name' => $file->getPathname(),
            'error' => $file->getError(),
            'size' => $file->getSize(),
            'input_name' => $input_name
        ];

        return $fileArray;
    }

    private function checkUrlAccessibility($url, $timeout = 5)
    {
        // Validation basique de l'URL
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return false;
        }

        $headers = @get_headers($url, 1);

        if ($headers === false) {
            // Essayer avec cURL si get_headers échoue
            return $this->checkUrlWithCurl($url, $timeout);
        }

        $statusCode = is_array($headers[0]) ? $headers[0][0] : $headers[0];

        return strpos($statusCode, '200') !== false ||
            strpos($statusCode, '301') !== false ||
            strpos($statusCode, '302') !== false;
    }

    private function checkUrlWithCurl($url, $timeout)
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_NOBODY => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => false,
        ]);

        curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return $httpCode >= 200 && $httpCode < 400;
    }

    /**
     * Extrait et sauvegarde la thumbnail d'une vidéo
     */
    private function extractVideoThumbnail($videoFile, $fileName = null)
    {
        // Extraire le baseName de la vidéo (sans l'extension)
        $videoBaseName = pathinfo($videoFile['input_name'], PATHINFO_FILENAME);

        // Supprimer tous les fichiers vidéo de $allFiles pour ne comparer qu'avec les images 
        // seuls les thumbnails nous intéresse
        $allFiles = $this->removeVideoFilesFromAllFiles();

        // Chercher la thumbnail correspondant à cette vidéo
        $thumbnailFile = null;
        $originalInputName = null;

        foreach ($allFiles as $filename => $file) {
            // Vérifier si la clé contient le baseName de la vidéo ET "thumbnail"
            if ($this->checkUploaderMatch($filename, $videoBaseName)) {
                $originalInputName = $filename;
                $thumbnailFile = $file;
                break;
            }
        }

        // Si une thumbnail est trouvée, la traiter
        if ($thumbnailFile !== null) {
            // Supprimer la thumbnail de $allFiles pour ne pas la traiter à nouveau
            unset($allFiles[$originalInputName]);

            $fileArray = $this->convertUploadedFileToArray($thumbnailFile, $originalInputName);

            $result = $this->uploadImage([
                'file' => $fileArray,
                'fileName' => ($fileName ?? uniqid()) . '-thumbnail',
                'convertTo'   => 'jpeg',
                'quality'     => 90,
                'maxFileSize' => 100,
                'maxWidth'    => 1920,
                'maxHeight'   => 1080,
                'textOverlay' => null,
            ], $this->uploadsDir);

            return $result;
        }

        return null;
    }

    /**
     * Supprime tous les fichiers vidéo de $allFiles
     */
    private function removeVideoFilesFromAllFiles()
    {
        $allFiles = $this->please->getRequest()->files->all();
        $videoExtensions = ['mp4', 'avi', 'mov', 'mkv', 'wmv', 'flv', 'webm', 'mpeg', 'mpg'];

        foreach ($allFiles as $filename => $file) {
            $extension = strtolower(pathinfo($file->getClientOriginalName(), PATHINFO_EXTENSION));

            // Vérifier si l'extension correspond à une vidéo
            if (in_array($extension, $videoExtensions)) {
                unset($allFiles[$filename]);
            }
        }

        return $allFiles;
    }

    /**
     * Supprime tous les fichiers thumbnail de $files
     */
    public function removeThumbnailFiles($files)
    {
        $thumbnailRegex = '/(thumbnail|thumb|minia|miniature|preview)/i';

        foreach ($files as $filename => $file) {
            $originalName = $file->getClientOriginalName();

            if (preg_match($thumbnailRegex, $originalName)) {
                unset($files[$filename]);
            }
        }

        return $files;
    }

    private function checkUploaderMatch($str1, $str2)
    {
        preg_match('/uploader-([a-z0-9]+)/', $str1, $match1);
        preg_match('/uploader-([a-z0-9]+)/', $str2, $match2);

        if (!empty($match1[1]) && !empty($match2[1])) {
            return $match1[1] === $match2[1];
        }

        return false;
    }
}
