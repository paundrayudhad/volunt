<?php

namespace App\Exceptions;

use Symfony\Component\HttpKernel\Exception\HttpException;

class QuotaFullException extends HttpException
{
    public function __construct()
    {
        parent::__construct(422, 'Kuota role sudah penuh.');
    }
}
