<?php

use App\Providers\AppServiceProvider;
use App\Providers\TenancyServiceProvider;
use Spatie\Permission\PermissionServiceProvider;
use Tymon\JWTAuth\Providers\LaravelServiceProvider;

return [
    AppServiceProvider::class,
    TenancyServiceProvider::class,
    LaravelServiceProvider::class,
    PermissionServiceProvider::class,
];
