<?php

namespace App\Exceptions;

use Symfony\Component\HttpKernel\Exception\HttpException;

class ShiftFullException extends HttpException
{
    public function __construct()
    {
        parent::__construct(422, 'Kuota shift sudah penuh.');
    }
}
