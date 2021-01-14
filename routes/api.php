<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\FeedController;
use App\Http\Controllers\PostController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\UserController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::get('ping', function () {
    return ['pong' => true];
});

Route::get('/401', [AuthController::class, 'unauthorized'])->name('login');

Route::prefix('auth')->group(function () {
    Route::post('login', [AuthController::class, 'login']);
    Route::post('logout', [AuthController::class, 'logout']);
    Route::post('refresh', [AuthController::class, 'refresh']);
});

Route::prefix('user')->group(function () {
    Route::get('feed', [FeedController::class, 'userFeed']);
    Route::get('{id}/feed', [FeedController::class, 'userFeed']);

    Route::post('{id}/follow', [UserController::class, 'follow']);
    // Route::get('{id}/relations', [UserController::class, 'relations']);
    // Route::get('{id}/photos', [UserController::class, 'photos']);
    Route::post('/', [UserController::class, 'create']);
    Route::put('/', [UserController::class, 'update']);
    Route::get('/', [UserController::class, 'read']);
    Route::get('{id}', [UserController::class, 'read']);
    Route::post('avatar', [UserController::class, 'updateAvatar']);
    Route::post('cover', [UserController::class, 'updateCover']);
});

Route::prefix('feed')->group(function () {
    Route::get('/', [FeedController::class, 'read']);
    Route::post('/', [PostController::class, 'post']);
});

Route::prefix('post')->group(function () {
    Route::post('{id}/like', [PostController::class, 'like']);
    Route::post('{id}/comment', [PostController::class, 'comment']);
});

Route::get('/search', [SearchController::class, 'search']);
