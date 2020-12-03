<?php

/**
 * This is publicly accessible
 */
Route::group(['middleware' => []], function() {
    Route::post('/frame.php', 'ApiController@frame');
    Route::get('/frame.php', 'ApiController@frame');
});
/**
 * This is required to have a valid API key
 */
Route::group(['middleware' => [
    'api.auth'
]], function() {
    Route::get('/hello', 'ApiController@hello');
});
