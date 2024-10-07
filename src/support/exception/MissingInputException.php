<?php

namespace support\exception;

use Throwable;

class MissingInputException extends PageNotFoundException
{
    /**
     * @var string
     */
    protected $template = '/app/view/400';

    /**
     * MissingInputException constructor.
     * @param string $message
     * @param int $code
     * @param Throwable|null $previous
     */
    public function __construct(string $message = 'Missing input parameter', int $code = 400, Throwable $previous = null) {
        parent::__construct($message, $code, $previous);
    }
}