<?php

namespace support\exception;

use Throwable;

class InvalidInputTypeException extends PageNotFoundException
{

    /**
     * @var string
     */
    protected $template = '/app/view/400';

    /**
     * InvalidInputTypeException constructor.
     * @param string $message
     * @param int $code
     * @param Throwable|null $previous
     */
    public function __construct(string $message = 'Invalid type for input parameter', int $code = 400, Throwable $previous = null) {
        parent::__construct($message, $code, $previous);
    }
}