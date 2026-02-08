<?php

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

class CreateArticleRequest
{
    #[Assert\NotBlank(message: 'Title cannot be empty')]
    #[Assert\Length(
        min: 3,
        max: 255,
        minMessage: 'Title must be at least {{ limit }} characters',
        maxMessage: 'Title must not exceed {{ limit }} characters'
    )]
    public string $title = '';

    #[Assert\NotBlank(message: 'URL cannot be empty')]
    #[Assert\Url(message: 'The provided URL is not valid', requireTld: true)]
    public string $url = '';
}
