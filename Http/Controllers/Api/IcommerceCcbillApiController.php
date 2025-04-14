<?php

namespace Modules\Icommerceccbill\Http\Controllers\Api;

use Illuminate\Http\Request;

//Request
use Modules\Icommerceccbill\Http\Requests\InitRequest;

// Base Api
use Modules\Icommerce\Http\Controllers\Api\OrderApiController;
use Modules\Icommerce\Http\Controllers\Api\TransactionApiController;
use Modules\Ihelpers\Http\Controllers\Api\BaseApiController;

// Repositories Icommerce
use Modules\Icommerce\Repositories\TransactionRepository;
use Modules\Icommerce\Repositories\OrderRepository;

use Modules\Icommerceccbill\Repositories\IcommerceCcbillRepository;

class IcommerceCcbillApiController extends BaseApiController
{

    private $icommerceccbill;
    private $order;
    private $orderController;
    private $transaction;
    private $transactionController;
    private $ccbillService;
    private $paymentMethod;

    private $log = "Icommerceccbill: ApiController|| ";

    public function __construct(
        IcommerceCcbillRepository $icommerceccbill,
        OrderRepository $order,
        OrderApiController $orderController,
        TransactionRepository $transaction,
        TransactionApiController $transactionController
    ){
        $this->icommerceccbill = $icommerceccbill;

        $this->order = $order;
        $this->orderController = $orderController;
        $this->transaction = $transaction;
        $this->transactionController = $transactionController;
        $this->ccbillService = app("Modules\Icommerceccbill\Services\CcbillService");

        // Get Payment Method Configuration
        $this->paymentMethod = $this->ccbillService->getPaymentMethod();
    }

    /**
    * Init Calculations (Validations to checkout)
    * @param Requests request
    * @return mixed
    */
    public function calculations(Request $request)
    {

      try {

        $response = $this->icommerceccbill->calculate($request->all(), $this->paymentMethod->options);

      } catch (\Exception $e) {
        //Message Error
        $status = 500;
        $response = [
          'errors' => $e->getMessage()
        ];
      }

      return response()->json($response, $status ?? 200);

    }

    /**
     * ROUTE - Init data
     * @param Requests request
     * @param Requests orderId
     * @return route
     */
    public function init(Request $request)
    {

        try {
            //Get data request
            $data = $request->all();
            //Validation init data
            $this->validateRequestApi(new InitRequest($data));

            $orderID = $request->orderId;
            // Payment Method Configuration
            $paymentMethod = $this->paymentMethod;

            // Order
            $order = $this->order->find($orderID);
            //$order = $this->order->find(20); //ESTA ES
            //Default always pending
            $statusOrder = 1;

            // Validate minimum amount order
            if(isset($paymentMethod->options->minimunAmount) && $order->total<$paymentMethod->options->minimunAmount)
              throw new \Exception(trans("icommerceccbill::icommerceccbills.messages.minimum")." :".$paymentMethod->options->minimunAmount, 204);

            // Create Transaction
            $transaction = $this->validateResponseApi(
                $this->transactionController->create(new Request( ["attributes" => [
                    'order_id' => $order->id,
                    'payment_method_id' => $paymentMethod->id,
                    'amount' => $order->total,
                    'status' => $statusOrder
                ]]))
            );


            $redirectRoute = $this->ccbillService->generateLink($order,$transaction,$paymentMethod);

            // Response
            $response = [ 'data' => [
                  "redirectRoute" => $redirectRoute,
                  "external" => true
            ]];


        } catch (\Exception $e) {
            \Log::error($e->getMessage());
            $status = 500;
            $response = [
                'errors' => $e->getMessage()
            ];
        }


        return response()->json($response, $status ?? 200);

    }


     /**
     * Response Api Method - Confirmation
     * @param Requests request
     * @return route
     */
    public function confirmation(Request $request)
    {

      \Log::info($this->log . 'Confirmation|INIT|' . time());
      $response = ['msj' => "OK"];

      try {

        $data = $request->all();
        \Log::info($this->log . 'Confirmation|DATA: '.json_encode($data));

        if (isset($data['X-formDigest'])){

          // Get order id and transaction id from request
          $inforReference = $this->ccbillService->getInforRefCommerce($data['X-customOrderRef']);

          $order = $this->order->find($inforReference['orderId']);
          \Log::info($this->log.'Order Status Id: '.$order->status_id);

          // Status Order 'pending' or 'failed' (The platform allows you to retry the payment right there)
          //if ($order->status_id == 1 || $order->status_id == 7) {

            // Default Status Order
            $newStatusOrder = 7; // Status Order Failed

            //Result Transaction
            $transactionState = $data['eventType'] ?? null;
            \Log::info($this->log.'trasanctionState: '.$transactionState);

            //Information about codTransactionState to save in comment later
            $codTransactionState = $this->ccbillService->getCodtransactionState($transactionState,$data);

            //Get Params from Recurrence
            $paramsRecurrence = $this->ccbillService->getParamsRecurrence(null,null,null,$data);

            // Get Digest | formatArguments null and send order to this case
            $digest = $this->ccbillService->createDigest($this->paymentMethod->options->saltKey,null,$order,$data['initialPeriod'],$paramsRecurrence);

            // Check signatures
            if ($digest == $data['X-formDigest']) {

              $newStatusOrder =  $this->ccbillService->getStatusOrder($transactionState);

              // Update Transaction
              $transactionUp = $this->validateResponseApi(
                $this->transactionController->update($inforReference['transactionId'],new Request(["attributes" => [
                  'payment_method_id' => $this->paymentMethod->id,'amount' => $order->total,'status' => $newStatusOrder,'external_status' => $transactionState,'external_code' => $codTransactionState
                  ]
                ]))
              );
              //\Log::info($this->log.'Transaction External Status: '.$transactionUp->external_status);

              //Comment Base + Information about codTransactionState
              $comment =  trans('icommerce::paymentmethods.messages.update by',['paymentMethod'=>'CCBill'])." --- ".$codTransactionState;

              //Data Update order
              $dataToUpdateOrder =['order_id' => $order->id,'status_id' => $newStatusOrder,"comment" => $comment];

              //Add Atributtes to Options in Order Status History
              $optionsHistory = ["transactionId" => $data['transactionId'] ?? null];

              //Save subscriptionId  and other data
              $this->ccbillService->saveExtraDataInOptions($data,$order,$dataToUpdateOrder,$optionsHistory);

              //Add options history to save later
              $dataToUpdateOrder["optionsHistory"] = $optionsHistory;

              //Update Order
              $orderUP = $this->validateResponseApi(
                $this->orderController->update($order->id, new Request(["attributes" => $dataToUpdateOrder]))
              );


              //Si la informacion que llega es: Plan Recurrente, Orden ya tiene un subscription ID, Y cumple que con algun estado que se deba cancelar
              $cancelSubscription =  $this->ccbillService->cancelSubscriptionFromStatus($transactionState,$newStatusOrder);
              if($data['X-customPlanRecu'] && $order->suscription_id && $cancelSubscription)
              {
                $subscriptionService = app("Modules\Iplan\Services\SubscriptionService");
                $subscriptionService->setSubscritionToInactive((int)$order->suscription_id);
              }


            }else{
              throw new \Exception("ERROR - Wrong Digest", 401); //401 Unauthorized
            }

          //}

        }else{
          throw new \Exception("WARNING - X-formDigest not found", 401);
        }

        \Log::info($this->log . 'Confirmation - END');


      } catch (\Exception $e) {
        \Log::error($this->log . ' Message: ' . $e->getMessage() . ' File: ' . $e->getFile() . ' Line: ' . $e->getLine());
        \Log::error($this->log . 'Code: ' . $e->getCode());
      }


      return response()->json($response, $status ?? 200);

    }



}
