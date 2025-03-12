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

    $formatArguments = $this->formatArguments($order);

    //Params
    $ccbillArgs = [
      'clientAccnum' => $paymentMethod->options->accountNumber,
      'clientSubacc' => $paymentMethod->options->subAccountNumber,
      'formName' => $paymentMethod->options->flexFormId,
      'initialPrice' => $formatArguments['price'],
      'initialPeriod' => $formatArguments['initialPeriod'],
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
      'formDigest' => $this->createDigest($paymentMethod->options->saltKey, $formatArguments),
      'productDesc' => "Payment to Order Id: ".$order->id
    ];

    return $this->makeUrl($paymentMethod->options->flexFormId, $ccbillArgs);
  }

  /**
   * Fix Arguments require formats
   */
  public function formatArguments(object $order): array
  {
    //Set price format
    $price = number_format($order->total, 2, '.', '');
    //An integer representing the length, in days, of the initial billing period. By default, the value for non-recurring prices is between 2 and 365.
    $initialPeriod = 2;
    //Return CCBill code
    $currencyCode = $this->getCurrencyCode($order->currency_code);

    return [
      'price' => $price,
      'initialPeriod' => $initialPeriod,
      'currencyCode' => $currencyCode
    ];
  }

  /**
   * Create Hash
   */
  public function createDigest($salt, $formatArguments = null, $order = null): string
  {

    if (is_null($formatArguments))
      $formatArguments = $this->formatArguments($order);

    //Union
    $stringToHash = $formatArguments['price'] . $formatArguments['initialPeriod'] . $formatArguments['currencyCode'] . $salt;
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

    if($transactionState=="NewSaleSuccess"){
      $codTransactionState =  "transactionId: ".$data['transactionId'];
    }else{
      if(isset($data['transactionId']))
        $codTransactionState =  "transactionId: ".$data['transactionId']." - Reason: ".$data['failureReason'];
      else
        $codTransactionState =  "Reason: ".$data['failureReason']; //Because to mode sandbox if the ip is not register not exis transaction
    }

    \Log::info($this->log.'codTransactionState: '.$codTransactionState);

    return $codTransactionState;
  }

}
