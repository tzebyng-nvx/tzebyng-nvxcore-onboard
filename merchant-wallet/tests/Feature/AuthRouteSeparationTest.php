<?php

use Illuminate\Support\Facades\Route;

it('separates player and admin JWT route guards', function () {
    $playerMe = Route::getRoutes()->getByName('player.me');
    $adminMe = Route::getRoutes()->getByName('admin.me');

    expect($playerMe)->not->toBeNull()
        ->and($adminMe)->not->toBeNull()
        ->and($playerMe->gatherMiddleware())->toContain('auth:api')
        ->and($adminMe->gatherMiddleware())->toContain('auth:admin');
});
