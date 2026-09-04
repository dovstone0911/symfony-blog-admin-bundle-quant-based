<?php

namespace DovStone\Bundle\SymfonyBlogAdminBundleQuantBased\Service;

use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use DovStone\Bundle\SymfonyBlogAdminBundleQuantBased\Service\PleaseService;

class WatchUploadService extends AbstractController
{
    private $please;
    public $crud;
    public $url;
    private $fileCdnOrigin;
    private $breakResponse;

    public function __construct(PleaseService $please)
    {
        $this->please = $please;
        $this->crud = $this->please->serve('crud');
        $this->url = $this->please->serve('url')->getUrl();
        $this->fileCdnOrigin = $this->please->serve('env')->getAppEnv('CDN_FILE_ORIGIN', $this->url);
    }

    public function watch($request)
    {
        if (stripos($this->url, $this->fileCdnOrigin) !== false) {
            return $this->_televerse($request);
        }

        $response = $this->please->serve('file_transfer')->send($request);

        if (isset($response['success'])) {
            $swal = [
                'icon' => 'success',
                'title' => $response['message'] ?? "Données enregistrées"
            ];
        } else {
            $swal = [
                'icon' => 'error',
                'title' => $response['message'] ?? "Quelque chose s'est mal passé. Veuillez réessayer plus tard.",
                'exception' => $response['exception'] ?? ''
            ];
        }

        $folder_id = $request->get('_televerse_keys')['folder_id'] ?? null;

        return $this->please->serve('response')->jsonResponse([
            'redirect' => $this->generateUrl('exploreFiles', $folder_id ? [
                'folder_id' => $folder_id
            ] : []),
            'swal' => $swal
        ]);
    }

    public function bind($document, $input_name = null)
    {
        $doc_id = $document['id'] ?? null;
        $is_user = isset($document['roles']);
        $key = $is_user ? 'user_id' : 'doc_id';

        if ($input_name) {
            $should_be_unique = true;
            $criteria = [
                [$key, '=', $doc_id],
                $input_name ? ['input_name', '=', $input_name] : []
            ];
        } else {
            $criteria = [
                [$key, '=', $doc_id],
            ];
        }
        $asset = collection('file')
            ->where('input_name', '=', $input_name)
            ->where($key, '=', $document['id'] ?? null)
            ->orderBy('created_at', 'DESC')
            ->findOne();

        if (!$asset && $asset_id = attr($document, $input_name)) {
            $asset = collection('file')->find($asset_id);
        }

        // lets RE-delete if multiple files found
        if (isset($should_be_unique) && $asset && count($asset) > 1) {
            $assets = array_slice($asset, 1);
            $this->please->serve('file')->deepDelete($assets);
        }

        $final_asset = isset($asset[0]) ? $asset[0] : ($asset ?? null);

        return $final_asset;
    }

    private function _televerse($request)
    {
        $file_serv = $this->please->serve('file');

        $document = input('document', '[]');
        $televerse_keys = input('_televerse_keys', '[]');
        $reservedProps = input('reservedProps', '[]');

        $document = is_array($document) ? $document : @json_decode($document, true);
        $keys = is_array($televerse_keys) ? $televerse_keys : @json_decode($televerse_keys, true);
        $reservedProps = is_array($reservedProps) ? $reservedProps : @json_decode($reservedProps, true);

        $inputName = input('input_name');
        $random_input_name = input('_random_input_name');
        $folder_id = input('folder_id') ?? ($keys['folder_id'] ?? null);

        $is_user = isset($document['roles']);
        $user_id = input('user_id', $this->please->serve('security')->getCurrentUser()['id'] ?? null);
        $doc_key = $is_user ? 'user_id' : 'doc_id';

        $files = $request->files->all();

        if (isset($files['files']) && is_array($files['files'])) {
            foreach ($files['files'] as $file) {
                $newKey = '_file_' . bin2hex(random_bytes(8));
                $files[$newKey] = $file;
            }
            unset($files['files']);
        }

        $limit = count($files);
        $err_count = 0;
        $i = 0;

        foreach ($files as $input_name => $file) {

            // Convertir en UploadedFile si ce n'est pas déjà le cas
            if (is_array($file) && isset($file['tmp_name'])) {
                $file = $this->_convertToUploadedFile($file);
            }

            if ($file instanceof UploadedFile && $file->getError() === 0) {
                $original_input_name = $input_name;

                if ($inputName) {
                    $input_name = $inputName;
                } elseif (is_int($original_input_name)) {
                    $input_name = $file->getClientOriginalName();
                }

                // ignorons les thumbnails à cet stade
                if (preg_match('/thumbnail|thumb|minia|miniature/i', $input_name)) {
                    continue;
                }

                $this->_delete($doc_key, $input_name, $document, function () use (
                    $keys,
                    $file,
                    $file_serv,
                    $doc_key,
                    $input_name,
                    $original_input_name,
                    $random_input_name,
                    $folder_id,
                    $user_id,
                    $document,
                    $reservedProps
                ) {
                    $filename = ($document['id'] ?? uniqid()) . '-' . substr(md5($input_name . uniqid()), 0, 8);

                    // Convertir UploadedFile en tableau pour compatibilité
                    $fileArray = $file_serv->convertUploadedFileToArray($file, $original_input_name);

                    $file_serv->uploadFile([
                        'inputName' => $input_name,
                        'file' => $fileArray,
                        'fileName' => $filename,
                        'onSuccess' => function ($f) use (
                            $fileArray,
                            $keys,
                            $doc_key,
                            $folder_id,
                            $user_id,
                            $input_name,
                            $original_input_name,
                            $random_input_name,
                            $document,
                            $reservedProps
                        ) {
                            $this->crud->basicCreate([
                                'csrfVerif' => false,
                                'collection' => 'file',
                                'reservedProps' => $reservedProps,
                                'sanitizeRequest' => false,
                                'sanitizeData' => false,
                                'callHook' => false,
                                'isValid' => true,
                                'sanitizer' => function () use (
                                    $fileArray,
                                    $keys,
                                    $doc_key,
                                    $folder_id,
                                    $user_id,
                                    $input_name,
                                    $original_input_name,
                                    $random_input_name,
                                    $document,
                                    $f
                                ) {
                                    $data = [
                                        'title' => $f->filename,
                                        'coll_name' => 'file',
                                        'type' => $keys['type'] ?? ($f->is_image ? 'image' : $f->extension),
                                        'input_name' => isset($fileArray['input_name']) && !is_numeric($fileArray['input_name'])
                                            ? $fileArray['input_name']
                                            : ($random_input_name ? uniqid() : $input_name),
                                        'human_type' => $f->human_type,
                                        'meta' => (function () use ($f) {
                                            unset(
                                                $f->success,
                                                $f->type,
                                                $f->is_image,
                                                $f->human_type,
                                                $f->absolute_path,
                                                $f->relative_path
                                            );
                                            return json_encode($f);
                                        })(),
                                        'folder_id' => $folder_id,
                                        'user_id' => $user_id
                                    ];
                                    if (isset($document['id'])) {
                                        $data[$doc_key] = $document['id'];
                                    }

                                    foreach ($keys as $key => $value) {
                                        if ($value) {
                                            $data[$key] = $value;
                                        }
                                    }

                                    return $data;
                                }
                            ]);
                        },
                        'onError' => function ($err) {
                            $this->breakResponse = $this->please->serve('response')->jsonResponse([
                                'swal' => [
                                    'icon' => 'error',
                                    'title' =>  $err
                                ]
                            ]);
                        }
                    ]);
                });
            } else {
                $err_count++;
            }

            if ($i == $limit - 1) {
                $success_count = count($files) - $err_count;
                $s = $success_count > 1 ? 's' : '';

                if ($err_count > 0) {
                    return $this->breakResponse ?? $this->please->serve('response')->jsonResponse([
                        'swal' => [
                            'icon' => $err_count == $limit ? 'error' : 'success',
                            'title' =>  $success_count .  " fichier$s téléversé$s sur " . $limit
                        ]
                    ]);
                }

                $redir = input('_redirect');
                $res = [
                    'swal' => [
                        'icon' => 'success',
                        'title' => "Fichier$s téléversé$s"
                    ]
                ];

                if ($redir) {
                    $res['redirect'] = $redir;
                } else {
                    $res['reload'] = true;
                }

                return $this->breakResponse ?? $this->please->serve('response')->jsonResponse($res);
            }

            $i++;
        }

        return $this->breakResponse;
    }

    private function _delete($doc_key, $input_name, $document, $then): void
    {
        $this->crud->read([
            'collection' => 'file',
            'finder' => function () use ($doc_key, $input_name, $document) {
                return collection('file')->findOneBy([
                    'input_name' => $input_name,
                    $doc_key => $document['id'] ?? null
                ]);
            },
            'onFound' => function ($file_saved) use ($then) {
                $this->please->serve('file')->deepDelete($file_saved, $then);
            },
            'onNotFound' => function () use ($then) {
                $then();
            }
        ]);
    }

    /**
     * Convertit un tableau de fichier en objet UploadedFile
     */
    private function _convertToUploadedFile(array $fileData): UploadedFile
    {
        return new UploadedFile(
            $fileData['tmp_name'],
            $fileData['name'] ?? basename($fileData['tmp_name']),
            $fileData['type'] ?? mime_content_type($fileData['tmp_name']),
            $fileData['error'] ?? UPLOAD_ERR_OK,
            true // Désactive la suppression automatique pour le contrôle manuel
        );
    }
}
