<?php

namespace App\Exception;

class CorruptedArticleContentException extends \RuntimeException
{
    public function __construct(string $message = 'The downloaded content appears to be empty or corrupted')
    {
        parent::__construct($message);
    }
}
