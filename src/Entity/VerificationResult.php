<?php

namespace App\Entity;

enum VerificationResult: string
{
    case APPROVED = 'APPROVED';
    case REJECTED = 'REJECTED';
}
