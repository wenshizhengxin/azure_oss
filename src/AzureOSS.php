<?php

namespace wenshizhengxin\azure_oss;

use cmq2080\mime_type_getter\MIMEType;
use MicrosoftAzure\Storage\Blob\Models\CreateBlobOptions;
use WindowsAzure\Common\ServicesBuilder;

class AzureOSS
{
    private static $storageName = '';
    private static $containerName = '';
    private static $accountName = '';
    private static $accountKey = '';

    public static function setConfig($storageName, $containerName = '', $accountName = '', $accountKey = '')
    {
        if (is_array($storageName)) {
            $config = $storageName;
            self::$storageName = $config['storage_name'] ?? '';
            self::$containerName = $config['container_name'] ?? '';
            self::$accountName = $config['account_name'] ?? '';
            self::$accountKey = $config['account_key'] ?? '';
        } else {
            self::$storageName = $storageName;
            self::$containerName = $containerName;
            self::$accountName = $accountName;
            self::$accountKey = $accountKey;
        }
    }

    public static function uploadFiles()
    {
        $uploadPaths = [];
        foreach ($_FILES as $file) {
            $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
            // $uploadPath = self::uploadFileByPath($file['tmp_name'], $extension);
            $result = self::uploadFileByBase64(base64_encode(file_get_contents($file['tmp_name'])), $extension);

            $uploadPaths[] = $result['path'];
        }

        return ['path' => implode(',', $uploadPaths)];
    }

    public static function uploadFileByPath($localFilepath, $extension = null, $prefix = 'uploads')
    {
        if (!$extension) {
            $extension = array_reverse(explode('.', $localFilepath))[0];
        }
        $extension = ltrim($extension, '.');
        $prefix = rtrim($prefix, DIRECTORY_SEPARATOR);
        $content = file_get_contents($localFilepath);

        return self::uploadFileByContent($content, $extension, $prefix);
    }

    public static function uploadFileByContent($content, $extension, $prefix = 'uploads')
    {
        $prefix = rtrim($prefix, DIRECTORY_SEPARATOR);

        $blobClient = self::createBlobClient(self::$storageName);
        $blobOptions = new CreateBlobOptions();
        $contentType = MIMEType::getMIMETypeByExtension($extension);
        if ($contentType) {
            $blobOptions->setContentType($contentType);
        }

        $destFilename = self::generateRandomFilename(substr($content, 0, 64), $extension);
        $filepath = $prefix . DIRECTORY_SEPARATOR . date('Ym') . DIRECTORY_SEPARATOR . $destFilename;
        $filepath = str_replace(DIRECTORY_SEPARATOR, '/', $filepath);

        $blobClient->createBlockBlob(self::$containerName, $filepath, $content, $blobOptions);

        return ['path' => $filepath];
    }

    public static function uploadFileByBase64($base64, $extension, $prefix = 'uploads')
    {
        return self::uploadFileByContent(base64_decode($base64), $extension, $prefix);
    }

    private static function getBlobConnectionString($storageName)
    {
        $blobConnectionArray = [
            'BlobEndpoint=' . "http://{$storageName}.blob.core.chinacloudapi.cn/",
            'QueueEndpoint=' . "http://{$storageName}.queue.core.chinacloudapi.cn/",
            'TableEndpoint=' . "http://{$storageName}.table.core.chinacloudapi.cn/",
            'AccountName=' . self::$accountName,
            'AccountKey=' . self::$accountKey,
        ];
        // $blobConnectionString =
        //     "BlobEndpoint=http://{$storageName}.blob.core.chinacloudapi.cn/;QueueEndpoint=http://{$storageName}.queue.core.chinacloudapi.cn/;TableEndpoint=http://{$storageName}.table.core.chinacloudapi.cn/;" .
        //     "AccountName=" . self::$accountName . ";AccountKey=" . self::$accountKey;
        $blobConnectionString = implode(";", $blobConnectionArray);

        return $blobConnectionString;
    }

    public static function createBlobClient($storageName)
    {
        $connectionString = self::getBlobConnectionString($storageName);

        // Create blob client.
        $blobClient = ServicesBuilder::getInstance()->createBlobService($connectionString);

        return $blobClient;
    }

    public static function generateRandomFilename($filepath, $extension = null)
    {
        $filename = md5($filepath);
        if ($extension) {
            $extension = ltrim($extension, '.');
            $filename .= '.' . $extension;
        }

        return $filename;
    }

    public static function readUploadedFile($filepath, $extension = null)
    {
        if (!$extension) {
            $extension = array_reverse(explode('.', $filepath))[0];
        }
        $extension = ltrim($extension, '.');

        $content = self::getContent($filepath);
        $contentType = MIMEType::getMIMETypeByExtension($extension);
        if (!$contentType) {
            $contentType = 'application/octet-stream';
        }

        header('Content-Type:' . $contentType);

        fpassthru($content);
    }

    public static function getContent($filepath)
    {
        $blobClient = self::createBlobClient(self::$storageName);
        return $blobClient->getBlob(self::$containerName, $filepath)->getContentStream();
    }
}
