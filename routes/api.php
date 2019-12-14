<?php

use Illuminate\Http\Request;

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

Route::post('store', 'BotController@store');
Route::post('chat', 'BotController@chat');

Route::post('test', 'BotController@dialog');
Route::post('area', 'BotController@area');


