<?php

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

class CreateArticleRequest
{
    #[Assert\NotBlank(message: 'URL cannot be empty')]
    #[Assert\Url(message: 'The provided URL is not valid', requireTld: true)]
    public string $url = '';
}
