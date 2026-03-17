<?php

namespace App\Exception;

class InvalidArticleInputException extends \InvalidArgumentException
{
    public function __construct(string $message = 'Invalid article input')
    {
        parent::__construct($message);
    }
}
