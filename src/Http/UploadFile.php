<?php
/**
 * This file is part of webman.
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the MIT-LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @author    walkor<walkor@workerman.net>
 * @copyright walkor<walkor@workerman.net>
 * @link      http://www.workerman.net/
 * @license   http://www.opensource.org/licenses/mit-license.php MIT License
 */

namespace Webman\Http;

use Webman\File;

/**
 * Class UploadFile
 * @package Webman\Http
 */
class UploadFile extends File
{
    /**
     * @var string
     */
    protected $uploadName = null;

    /**
     * @var string
     */
    protected $uploadMimeType = null;

    /**
     * @var int
     */
    protected $uploadErrorCode = null;

    /**
     * UploadFile constructor.
     *
     * @param string $file_name
     * @param string $upload_name
     * @param string $upload_mime_type
     * @param int $upload_error_code
     */
    public function __construct(string $file_name, string $upload_name, string $upload_mime_type, int $upload_error_code)
    {
        $this->uploadName = $upload_name;
        $this->uploadMimeType = $upload_mime_type;
        $this->uploadErrorCode = $upload_error_code;
        parent::__construct($file_name);
    }

    /**
     * GetUploadName
     * @return string
     */
    public function getUploadName(): ?string
    {
        return $this->uploadName;
    }

    /**
     * GetUploadMimeType
     * @return string
     */
    public function getUploadMimeType(): ?string
    {
        return $this->uploadMimeType;
    }

    /**
     * GetUploadExtension
     * @return mixed
     */
    public function getUploadExtension()
    {
        return \pathinfo($this->uploadName, PATHINFO_EXTENSION);
    }

    /**
     * GetUploadErrorCode
     * @return int
     */
    public function getUploadErrorCode(): ?int
    {
        return $this->uploadErrorCode;
    }

    /**
     * IsValid
     * @return bool
     */
    public function isValid(): bool
    {
        return $this->uploadErrorCode === UPLOAD_ERR_OK;
    }

    /**
     * GetUploadMineType
     * @deprecated
     * @return string
     */
    public function getUploadMineType(): ?string
    {
        return $this->uploadMimeType;
    }
}
