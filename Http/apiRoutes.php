<?php

use Illuminate\Routing\Router;

Route::prefix('icommerceccbill/v1')->group(function (Router $router) {

    $router->get('/', [
        'as' => 'icommerceccbill.api.ccbill.init',
        'uses' => 'IcommerceCcbillApiController@init',
    ]);

    $router->post('/confirmation', [
      'as' => 'icommerceccbill.api.ccbill.confirmation',
      'uses' => 'IcommerceCcbillApiController@confirmation',
    ]);


});