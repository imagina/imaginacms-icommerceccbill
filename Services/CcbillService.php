<?php

namespace Modules\Icommerceccbill\Services;

class CcbillService
{

  private $log = "Icommerceccbill: CcbillService|| ";

  /*
    * Get payment method by name
    */
  public function getPaymentMethod()
  {

    $paymentName = config('asgard.icommerceccbill.config.paymentName');

    $params = ['filter' => ['field' => 'name']];
    $paymentMethod = app("Modules\Icommerce\Repositories\PaymentMethodRepository")->getItem($paymentName, json_decode(json_encode($params)));

    return $paymentMethod;
  }

  /**
   * Process to get link to redirect to ccbill
   */
  public function generateLink(object $order, object $transaction, object $paymentMethod)
  {

    \Log::info($this->log . "generateLink");

    $plan = $this->getPlan($order);

    //Formats specifics
    $formatArguments = $this->formatArguments($order,$plan->frequency_id);

    $initialPeriod = $plan->frequency_id;

    //Only case recurrence
    $paramsRecurrence = $this->getParamsRecurrence($plan,$formatArguments,$initialPeriod);

    //Params
    $ccbillArgs = [
      'clientAccnum' => $paymentMethod->options->accountNumber,
      'clientSubacc' => $paymentMethod->options->subAccountNumber,
      'formName' => $paymentMethod->options->flexFormId,
      'initialPrice' => $formatArguments['price'],
      'initialPeriod' => $initialPeriod,
      'currencyCode' => $formatArguments['currencyCode'],
      'customer_fname' => $order->first_name,
      'customer_lname' => $order->last_name,
      'email' => $order->email,
      'zipcode' => $order->payment_zip_code ?? '',
      'country' => $order->payment_country,
      'city' => $order->payment_city ?? '',
      'state' => $this->getPaymentZone($order),
      'address1' => $order->payment_address_1 ?? '',
      'customOrderRef' => $this->getOrderRefCommerce($order, $transaction),
      'customPlanRecu' => $plan->is_recurring,
      'formDigest' => $this->createDigest($paymentMethod->options->saltKey, $formatArguments,null,$initialPeriod, $paramsRecurrence),
      'productDesc' => "Payment to Order Id: ".$order->id
    ];

    //Check Plan recurrence
    if(!is_null($paramsRecurrence))
      $ccbillArgs = array_merge($ccbillArgs, $paramsRecurrence);

    return $this->makeUrl($paymentMethod->options->flexFormId, $ccbillArgs);

  }

  /**
   * Fix Arguments require formats
   */
  public function formatArguments(object $order): array
  {
    //Set price format
    $price = number_format($order->total, 2, '.', '');

    //Return CCBill code
    $currencyCode = $this->getCurrencyCode($order->currency_code);

    return [
      'price' => $price,
      'currencyCode' => $currencyCode
    ];
  }

  /**
   * Get plan from order
   */
  private function getPlan($order)
  {
    $planRepository = app("Modules\Iplan\Repositories\PlanRepository");

    foreach ($order->orderItems as $item) {
      if($item->entity_type=='Modules\Iplan\Entities\Plan'){
        $planIdInOrderItem = $item->entity_id;
        $plan = $planRepository->getItem($planIdInOrderItem);
        return $plan;
      }
    }
  }

  /**
   * Create Hash
   * This used in the confirmation too
   */
  public function createDigest($salt, $formatArguments = null, $order = null, $initialPeriod, $paramsRecurrence = null): string
  {

    if (is_null($formatArguments))
      $formatArguments = $this->formatArguments($order);

    //Validation
    if(is_null($paramsRecurrence))
      $stringToHash = $formatArguments['price'] . $initialPeriod . $formatArguments['currencyCode'] . $salt;
    else
      $stringToHash = $formatArguments['price'] . $initialPeriod . $paramsRecurrence['recurringPrice'] . $paramsRecurrence['recurringPeriod'] . $paramsRecurrence['numRebills'] . $formatArguments['currencyCode'] . $salt;
    //\Log::info($this->log . "createDigest: ". $stringToHash);

    //Return MD5
    return md5($stringToHash);
  }

  /**
   * Get Currency Code to CCBill code
   */
  public function getCurrencyCode(string $currency)
  {

    $paymentCurrencies = config("asgard.icommerceccbill.config.currencies");

    if (isset($paymentCurrencies[$currency])) {
      return $paymentCurrencies[$currency];
    } else {
      throw new \Exception("Currency not supported in CCBILL");
    }
  }

  /**
   * Get Order Reference Commerce
   * @return reference
   */
  public function getOrderRefCommerce($order, $transaction): string
  {
    $reference = $order->id . "-" . $transaction->id;
    return $reference;
  }

  /**
   * Make url to reedirect
   */
  private function makeUrl($flexFormId, $ccbillArgs): string
  {

    $flexUrl = config("asgard.icommerceccbill.config.baseFlexUrl");
    $liveUrl = $flexUrl . $flexFormId;

    return $liveUrl . '?' . http_build_query($ccbillArgs);
  }

  /**
   * Get payment zone
   */
  private function getPaymentZone($order): string
  {
    //Fix payment zone
    $paymentZone = "";
    if (!empty($order->payment_zone)) {
      $result = explode('-', $order->payment_zone);
      $paymentZone = $result[1] ?? '';
    }
    return $paymentZone;
  }

  /**
   * Get Infor Reference From Commerce
   * @param $reference
   * @return array
   */
  public function getInforRefCommerce($reference): array
  {

    $result = explode('-', $reference);

    $infor['orderId'] = $result[0];
    $infor['transactionId'] = $result[1];

    \Log::info($this->log . 'OrderId: ' . $infor['orderId']);
    \Log::info($this->log . 'TransactionId: ' . $infor['transactionId']);

    return $infor;
  }


  /**
   * Get Status to Order
   * @param String cod
   * @return Int
   */
  public function getStatusOrder(string $cod): int
  {

    switch ($cod) {

      case "NewSaleSuccess":
        $newStatus = 13; //processed
        break;

      case "NewSaleFailure":
        $newStatus = 7; //failed
        break;

      case "RenewalSuccess":
          $newStatus = 13; //processed
          break;

      case "RenewalFailure":
          $newStatus = 7; //failed
          break;

      case "Cancellation":
        $newStatus = 3; //cancelled
        break;

      case "Expiration":
        $newStatus = 14; //expired
        break;

      case "Chargeback":
        $newStatus = 10; //Chargeback
        break;

      case "Return":
        $newStatus = 3; //cancelled
        break;

      case "Refund":
        $newStatus = 8; //refunded
        break;

      case "Void":
        $newStatus = 12; //voided
        break;

      default:
        $newStatus = 1; //Pending
    }

    \Log::info($this->log . 'getStatusOrder|NewStatus: ' . $newStatus);

    return $newStatus;
  }

  /**
   * Validations to cod transactions
   */
  public function getCodtransactionState(string $transactionState, array $data): string
  {

    $codTransactionState = "";

    if ($transactionState == "NewSaleSuccess") {
      $codTransactionState = "transactionId: " . $data['transactionId'];
    } else {
        $failureReason = $data['failureReason'] ?? '';
        if (isset($data['transactionId'])) {
            $codTransactionState = "transactionId: " . $data['transactionId'] . " - Reason: " . (!empty($failureReason) ? $failureReason : "");
        } else {
            $codTransactionState = "Reason: " . (!empty($failureReason) ? $failureReason : ""); // Para modo sandbox si la IP no está registrada y no existe transacción
        }
    }

    \Log::info($this->log.'codTransactionState: '.$codTransactionState);

    return $codTransactionState;
  }

  /**
   * Get params, before to go payment and after (In confirmation)
   */
  public function getParamsRecurrence($plan=null,$formatArguments=null,$initialPeriod=null,$dataFromResponse=null)
  {

    //Set params to recurrence before send to ccbill
    if(!is_null($plan) && $plan->is_recurring){
      return [
        'recurringPrice' => $formatArguments['price'],
        'recurringPeriod' => $initialPeriod,
        'numRebills' => 99
      ];
    }

    //Get params recurrence from CCBILL
    if(!is_null($dataFromResponse) && $dataFromResponse['X-customPlanRecu']){
      return [
        'recurringPrice' => $dataFromResponse['subscriptionRecurringPrice'],
        'recurringPeriod' => $dataFromResponse['recurringPeriod'],
        'numRebills' => 99
      ];
    }

    return null;
  }

  /**
   * @param data (Response from CCBILL)
   * Update the comment, save subscription id in options (Order and OrderStatusHistory)
   */
  public function saveExtraDataInOptions($data,$order,&$dataToUpdateOrder,&$optionsHistory)
  {

    //Save subscriptionId Only to recurrence plan
    if($data['X-customPlanRecu']){

      //Update Options in Order
      $optionsArray = json_decode(json_encode($order->options), true);
      $optionsArray['external_subscription_id'] = $data['subscriptionId'];
      $dataToUpdateOrder['options'] = $optionsArray;

      //Update Comment
      $dataToUpdateOrder["comment"] .= " -- External SubscriptionId: ".$data['subscriptionId'];

      //Add subscription ID to options history
      $optionsHistory["external_subscription_id"] = $data['subscriptionId'];

    }

  }

  /**
   * Status to Cancel an subscription | Utilizado cuando es una renovacion en teoria desde el panel administrador de CCBIll o automatica
   * @newStatusOrder (puede originarse de varios estados)
   */
  public function cancelSubscriptionFromStatus(string $cod, $newStatusOrder): int
  {

    if($newStatusOrder==7 || $cod=="Cancellation" || $cod=="Expiration" || $cod=="Refund" || $cod=="Void")
      return true;
    else
      return false;

  }

}
