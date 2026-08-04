<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

require __DIR__ . '/api/player.php';
require __DIR__ . '/api/admin.php';

Route::get('/health', fn () => response()->json(['status' => 'ok']));
