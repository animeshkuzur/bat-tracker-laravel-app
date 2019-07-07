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
Route::prefix('v1')->group(function(){
	Route::post('login', 'Api\AuthController@login');
	Route::post('register', 'Api\AuthController@register');
	Route::post('refresh', 'Api\AuthController@refresh');
	Route::group(['middleware' => 'auth:api'], function(){
		Route::post('logout', 'Api\AuthController@logout');
		Route::get('otp/send', 'Api\AuthController@send_otp');
		Route::post('otp/verify', 'Api\AuthController@verify_otp');
		Route::get('profile/{phone}',['middleware'=>'user.verify','uses'=>'Api\UserController@get_profile']);
		Route::get('tracker/token',['middleware'=>'user.verify','uses'=>'Api\UserController@tracker_token']);
		Route::post('history/{phone}',['middleware'=>'user.verify','uses'=>'Api\UserController@history']);
	});
});

/*Route::middleware('auth:api')->get('/user', function (Request $request) {
    return $request->user();
});*/
