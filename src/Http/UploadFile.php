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
     * @param string $fileName
     * @param string $uploadName
     * @param string $uploadMime_type
     * @param int $uploadError_code
     */
    public function __construct(string $fileName, string $uploadName, string $uploadMime_type, int $uploadError_code)
    {
        $this->uploadName = $uploadName;
        $this->uploadMimeType = $uploadMime_type;
        $this->uploadErrorCode = $uploadError_code;
        parent::__construct($fileName);
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
