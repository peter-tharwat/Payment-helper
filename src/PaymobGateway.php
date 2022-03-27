<?php

namespace PaymentHelper;

use PaymentHelper\Contracts\GatewayContract as GatewayContract;
use PaymentHelper\BaseGateway as BaseGateway;
use GuzzleHttp\Client as Guzzle;

class PaymobGateway extends BaseGateway implements GatewayContract {

	public $sandboxApiKey;
	public $liveApiKey;
	public $sandboxIntegrationId;
	public $liveIntegrationId;
	public $liveIframeId;
	public $sandboxIframeId;
	public $hmac;

	public function init($foo){
		// we have to accept keys like $sandboxApiKey and connect it to $this class attrubutes
		return $this;
	}

	public function process(){

		$request = new Guzzle();


		// Step 1 Getting Token That we will use in next steps 
		$tokensRequest = $request->request('POST', 'https://accept.paymobsolutions.com/api/auth/tokens', [
		    'api_key' => "" // API KEY THAT PAYMOB WILL GIVE US - live code != sandbox code
		]);
		$tokensRequest = json_decode($tokensRequest->getBody());

		// Step 2
		$ordersRequest = $request->request('POST', 'https://accept.paymobsolutions.com/api/ecommerce/orders', [
		    'auth_token' => $tokensRequest['token'], // from tokensRequest step
		    'delivery_needed'=>false, // true,false,
		    'amount_cents'=>1000.00, // 10 EGP => 1000 Cents In EGP 
		    'items'=>[ // List of itesm

		    ]
		]);
		$ordersRequest = json_decode($ordersRequest->getBody());

		// Step 3 We Have to Save Order Id From Step Number 2 To Our Database Payment 
		//
		//
		//


		$iframeKeys = $request->request('POST', 'https://accept.paymobsolutions.com/api/acceptance/payment_keys', [
			'auth_token' 	=> $tokensRequest['token'], // from tokensRequest step
			'expiration' 	=> 36000,
			'amount_cents'	=> 1000.00, // 10 EGP => 1000 Cents In EGP 
			'order_id'=> $ordersRequest['id'],
			'billing_data'=>[
				"apartment" => "NA", 
				"email" => "example@example.com", 
				"floor" => "NA", 
				"first_name" => "example", 
				"street" => "NA", 
				"building" => "NA", 
				"phone_number" => "0123456789", 
				"shipping_method" => "NA", 
				"postal_code" => "NA", 
				"city" => "NA", 
				"country" => "NA", 
				"last_name" => "example",
				"state" => "NA"
			],
			'currency'=>"EGP",
			'integration_id'=> "" // PAYMOB_LIVE_INTEGRATION_ID - live code != sandbox code
		]]);

		$iframeKeys = json_decode($iframeKeys->getBody());


		$iframeUrl = "https://accept.paymobsolutions.com/api/acceptance/iframes/" . "PAYMOB_IFRAME_ID" /*live code != sandbox code*/ . "?payment_token=" . $iframeKeys['token']
		return  $iframeUrl;

	}

	public function verify(){

		// hashing response verify it 
		$hashCode =$_GET['amount_cents'] . $_GET['created_at'] . $_GET['currency'] . 
		 		   $_GET['error_occured'] . $_GET['has_parent_transaction'] . $_GET['id'] . 
		 		   $_GET['integration_id'] . $_GET['is_3d_secure'] . $_GET['is_auth'] . 
		 		   $_GET['is_capture'] . $_GET['is_refunded'] . $_GET['is_standalone_payment'] . 
		 		   $_GET['is_voided'] . $_GET['order'] . $_GET['owner'] . 
		 		   $_GET['pending'] . $_GET['source_data_pan'] . $_GET['source_data_sub_type'] . 
		 		   $_GET['source_data_type'] . $_GET['success'];


		if (hash_hmac('sha512', $hashCode, "PAYMOB_HMAC")) /*PAYMOB_HMAC live code === sandbox code*/
			return 1;
		return 0;

	}


}	