<?php

use Illuminate\Routing\Router;

Route::prefix('icommerceccbill')->group(function (Router $router){

  $router->get('/payment/response', [
    'as' => 'icommerceccbill.response',
    'uses' => 'PublicController@response',
  ]);

});