<?php

namespace App\Contracts;

interface GoogleAccessTokenProvider
{
    public function accessToken(): string;
}
