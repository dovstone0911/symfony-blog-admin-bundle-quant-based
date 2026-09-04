<?php

namespace DovStone\Bundle\SymfonyBlogAdminBundleQuantBased\Service;

use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use DovStone\Bundle\SymfonyBlogAdminBundleQuantBased\Service\PleaseService;
use Symfony\Component\Mime\Part\DataPart;
use Symfony\Component\Mime\Part\Multipart\FormDataPart;

class FileTransferService extends AbstractController
{
    private $please;
    private $fileCdnOrigin;

    public function __construct(PleaseService $please)
    {
        $this->please = $please;
        $this->fileCdnOrigin = $this->please->serve('env')->getAppEnv('CDN_FILE_ORIGIN', $this->please->serve('url')->getUrl());
    }

    public function send(Request $request, $action = 'televerse'): array
    {
        $endpoint = $this->fileCdnOrigin . '/' . $action;

        // Préparer les données pour FormDataPart
        $formFields = [];

        // 1. Ajouter les champs de formulaire textuels
        foreach ($request->request->all() as $key => $value) {
            // Convertir les tableaux en JSON
            if (is_array($value)) {
                $value = json_encode($value, JSON_UNESCAPED_UNICODE);
            }
            $formFields[$key] = (string) $value;
        }

        // 2. Ajouter les fichiers avec DataPart
        foreach ($request->files->all() as $fieldName => $fileOrFiles) {
            // Si c'est un seul fichier (objet UploadedFile)
            if (is_object($fileOrFiles) && method_exists($fileOrFiles, 'isValid')) {
                if ($fileOrFiles->isValid()) {
                    $formFields[$fieldName] = DataPart::fromPath(
                        $fileOrFiles->getRealPath(),
                        $fileOrFiles->getClientOriginalName(),
                        $fileOrFiles->getMimeType()
                    );
                }
            }
            // Si c'est un tableau de fichiers
            elseif (is_array($fileOrFiles)) {
                foreach ($fileOrFiles as $index => $file) {
                    if ($file && $file->isValid()) {
                        // Nom de champ pour fichiers multiples (ex: "photos[0]", "photos[1]")
                        $fileFieldName = "{$fieldName}[{$index}]";
                        $formFields[$fileFieldName] = DataPart::fromPath(
                            $file->getRealPath(),
                            $file->getClientOriginalName(),
                            $file->getMimeType()
                        );
                    }
                }
            }
        }

        // 3. Créer le FormDataPart (génère automatiquement les boundaries)
        $formData = new FormDataPart($formFields);

        // 4. Créer le client HTTP
        $client = HttpClient::create([
            'timeout' => 60,
            'max_duration' => 120,
        ]);

        try {
            // 5. Envoyer la requête avec les headers multipart corrects
            $response = $client->request('POST', $endpoint, [
                'headers' => $formData->getPreparedHeaders()->toArray(),
                'body' => $formData->bodyToString(),
            ]);

            // 6. Récupérer et traiter la réponse
            $statusCode = $response->getStatusCode();
            $content = $response->getContent();

            // Essayer de décoder le JSON
            $data = json_decode($content, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $data = ['raw_response' => $content];
            }

            return [
                'success' => $statusCode === 200 || $statusCode === 201,
                'status' => $statusCode,
                'data' => $data,
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ];
        }
    }
}
