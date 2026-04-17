<?php

namespace App\Service;

use Symfony\Component\HttpFoundation\File\UploadedFile;

class CloudinaryService
{
    public function __construct(
        private string $cloudName,
        private string $apiKey,
        private string $apiSecret,
    ) {}

    public function upload(UploadedFile $file, string $folder = 'finovate'): string
    {
        $timestamp = time();
        $signature = $this->sign(['folder' => $folder, 'timestamp' => $timestamp]);
        $url       = 'https://api.cloudinary.com/v1_1/' . $this->cloudName . '/image/upload';

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_POSTFIELDS     => [
                'file'      => new \CURLFile($file->getPathname(), $file->getMimeType(), $file->getClientOriginalName()),
                'api_key'   => $this->apiKey,
                'timestamp' => $timestamp,
                'signature' => $signature,
                'folder'    => $folder,
            ],
        ]);

        $response = curl_exec($ch);
        $err      = curl_error($ch);
        curl_close($ch);

        if ($err) throw new \RuntimeException('Cloudinary cURL error: ' . $err);

        $data = json_decode($response, true);
        if (empty($data['secure_url'])) {
            throw new \RuntimeException('Cloudinary upload failed: ' . ($data['error']['message'] ?? $response));
        }

        return $data['secure_url'];
    }

    public function uploadFromUrl(string $remoteUrl, string $folder = 'finovate'): string
    {
        $timestamp = time();
        $signature = $this->sign(['folder' => $folder, 'timestamp' => $timestamp]);
        $url       = 'https://api.cloudinary.com/v1_1/' . $this->cloudName . '/image/upload';

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_POSTFIELDS     => http_build_query([
                'file'      => $remoteUrl,
                'api_key'   => $this->apiKey,
                'timestamp' => $timestamp,
                'signature' => $signature,
                'folder'    => $folder,
            ]),
        ]);

        $response = curl_exec($ch);
        curl_close($ch);

        $data = json_decode($response, true);
        return $data['secure_url'] ?? $remoteUrl;
    }

    private function sign(array $params): string
    {
        ksort($params);
        $str = '';
        foreach ($params as $k => $v) $str .= $k . '=' . $v . '&';
        return sha1(rtrim($str, '&') . $this->apiSecret);
    }
}