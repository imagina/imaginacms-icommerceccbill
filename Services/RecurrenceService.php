<?php

namespace Modules\Icommerceccbill\Services;

class RecurrenceService
{

  private $log = "Icommerceccbill: RecurrenceService||";

  /**
   * Cancel Subscription
  */
  public function cancelSubscription(object $order,object $paymentMethod)
  {

    \Log::info($this->log . "cancelSubscription");

    //Prepare arguments
    $cancelUrl = config("asgard.icommerceccbill.config.cancelSubscription");
    $ccbillArgs = $this->getCcbillArg($order,$paymentMethod);

    //Send Request
    $response = $this->sendRequest($cancelUrl,$ccbillArgs);
    \Log::info($this->log . "cancelSubscription|Response: ". json_encode($response));

    // Convertir XML and get specific result
    $xml = simplexml_load_string($response['body']);
    $results = (string) $xml[0];

    //Validate response
    if($results =="1")
      return ['success' => true,'response' => $response];

    //If an error occurs, check de README
    return ['success' => false, 'response' => $response];

  }

  /**
   * Fix argunments
   */
  private function getCcbillArg(object $order, object $paymentMethod)
  {

    return [
      "clientAccnum" => $paymentMethod->options->accountNumber,
      "username" => $paymentMethod->options->userName,
      "password" => $paymentMethod->options->pass,
      "action" => "cancelSubscription",
      "returnXML" => 1,
      "subscriptionId" => $order->options->external_subscription_id
    ];

  }

  /**
   * send request to ccbill
   */
  private function sendRequest(string $endpoint,$ccbillArgs)
  {

    $client = new \GuzzleHttp\Client();
    $response = $client->request('GET', $endpoint, [
        'query' => $ccbillArgs
    ]);

    return [
      'status' => $response->getStatusCode(),
      'body' => $body = $response->getBody()->getContents()
    ];

  }

}
