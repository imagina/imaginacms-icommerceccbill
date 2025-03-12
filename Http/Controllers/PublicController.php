<?php

namespace Modules\Icommerceccbill\Http\Controllers;

// Requests & Response
use Illuminate\Http\Request;

// Base
use Modules\Core\Http\Controllers\BasePublicController;
use Modules\Icommerce\Repositories\OrderRepository;

// Services
use Modules\Icommerceccbill\Services\CcbillService;

class PublicController extends BasePublicController
{

  private $order;
  private $ccbillService;

  public function __construct(
    OrderRepository $order,
    CcbillService $ccbillService
  ) {
    $this->order = $order;
    $this->ccbillService = $ccbillService;
  }


  /**
   * Response Frontend After the Payment
   * @param  $request
   * @param  $orderId
   * @return redirect
   */
  public function response(Request $request)
  {

    //Get all data from request
    $data = $request->all();

    if (isset($data['customOrderRef'])) {

      // Get order id and transaction id from request
      $inforReference = $this->ccbillService->getInforRefCommerce($data['customOrderRef']);
      $orderId = $inforReference['orderId'];

      $order = $this->order->find($orderId);

      return redirect($order->url);
    } else {

      return redirect()->route(locale() . '.homepage');

    }

  }

}
