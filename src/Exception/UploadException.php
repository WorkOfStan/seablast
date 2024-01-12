<?php

declare(strict_types=1);

namespace Seablast\Seablast\Exception;

use Exception;

/**
 * Exception to use when handling upload errors.
 * Based on https://www.php.net/manual/en/features.file-upload.errors.php#89374
 *
 * EDIT BY danbrown AT php DOT net:
 * This code is a fixed version of a note originally submitted by (Thalent, Michiel Thalen) on 04-Mar-2009.]
 *
 * Usage:
 * if ($_FILES['fileToUpload']['error'] !== UPLOAD_ERR_OK) {
 *     throw new UploadException($_FILES['fileToUpload']['error']);
 * }
 * //uploading successfully done - continue with your code
 *
 */
class UploadException extends Exception
{
    use \Nette\SmartObject;

    /**
     *
     * @param int $code
     */
    public function __construct(int $code)
    {
        $message = $this->codeToMessage($code);
        //error_log($message, $code); // TODO address server side logging and its verbosity
        parent::__construct($message, $code);
    }

    /**
     *
     * @param int $code
     * @return string
     */
    private function codeToMessage(int $code): string
    {
        switch ($code) {
            case UPLOAD_ERR_INI_SIZE: // 1
                $message = 'The uploaded file exceeds the upload_max_filesize directive in php.ini';
                break;
            case UPLOAD_ERR_FORM_SIZE: // 2
                $message = 'The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form';
                break;
            case UPLOAD_ERR_PARTIAL: // 3
                $message = 'The uploaded file was only partially uploaded';
                break;
            case UPLOAD_ERR_NO_FILE: // 4
                $message = 'No file was uploaded';
                break;
            case UPLOAD_ERR_NO_TMP_DIR: // 6
                $message = 'Missing a temporary folder';
                break;
            case UPLOAD_ERR_CANT_WRITE: // 7
                $message = 'Failed to write file to disk';
                break;
            case UPLOAD_ERR_EXTENSION: // 8
                $message = 'File upload stopped by extension';
                break;
            default: // unknown
                $message = 'Unknown upload error';
                break;
        }
        return $message;
    }
}
