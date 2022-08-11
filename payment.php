<?php
namespace App\Helpers;
use Illuminate\Support\Facades\Http;
use \PayPal\Rest\ApiContext;
use \PayPal\Auth\OAuthTokenCredential;
use \PayPal\Api\Payer;
use \PayPal\Api\Item;
use \PayPal\Api\ItemList;
use \PayPal\Api\Amount;
use \PayPal\Api\Transaction;
use \PayPal\Api\RedirectUrls;
use \PayPal\Api\Payment;
use \PayPal\Exception\PayPalConnectionException;
use \PayPal\Api\Details;
use \PayPal\Api\PaymentExecution;
use TapPayments\GoSell;

class PaymentHelper
{

    public $method;
    public $amount;
    public $token;
    public $currency;
    public $sk_code;
    public $usdtoegp;
    public $PAYMOB_API_KEY;
    public $FAWRY_MERCHANT;
    public $FAWRY_SECRET;

    public function __construct($method = null, $amount = null, $token = null, $currency = null)
    {
        $this->method = $method;
        $this->amount = $this->clac_new_amount($method, $amount);
        $this->usdtoegp = \NH::charge_egp();
        $this->amount_in_egp = sprintf('%0.2f', ceil($this->amount * $this->usdtoegp));
        $this->currency = (null == $currency) ? "USD" : $currency;
        $this->PAYMOB_API_KEY = env('PAYMOB_API_KEY');
    }

    public function make_payment()
    {
        if ($this->method == "paymob")
        {
            return $this->paymob_payment();
        }
        else if ($this->method == "paypal")
        {
            return $this->paypal_payment();
        }
        else if ($this->method == "tap")
        {
            return $this->tap_payment();
        }
        else if ($this->method == "fawry")
        {
            return $this->fawry_payment();
        }
        else if ($this->method == "kashier")
        {
            return $this->kashier_payment();
        }
        else if ($this->method == "hyperpay")
        {
            return $this->kashier_payment();
        }
    }
    public function paymob_payment()
    {
        return $this->paymob_payment_init();
    }
    public function paypal_payment()
    {
        return $this->paypal_payment_init();
    }
    public function tap_payment()
    {
        return $this->tap_payment_init();
    }
    public function fawry_payment()
    {
        return $this->fawry_payment_init();
    }
    public function kashier_payment()
    {
        return $this->kashier_payment_init();
    }
    public function hyperpay_payment()
    {
        return $this->hyperpay_payment_init();
    }

    public function kashier_payment_init()
    {

        $uniqid = uniqid();
        $store_payment = $this->store_payment($payment_id = $uniqid, $amount = $this->calc_amout_after_transaction("kashier", $this->amount) , $source = "credit", $process_data = "{}", $currency_code = "USD", $status = strtoupper("PENDING") , $note = $this->amount_in_egp);

        $mid = env('KASHIER_ACCOUNT_KEY'); //your merchant id
        $amount = $this->amount_in_egp; //eg: 100
        $currency = "EGP"; //eg: "EGP"
        $orderId = $uniqid; //eg: 99, your system order ID
        $secret = env('KASHIER_IFRAME_KEY');
        $path = "/?payment=${mid}.${orderId}.${amount}.${currency}";
        $hash = hash_hmac('sha256', $path, $secret, false);

        $rendered_html = view('site-templates.kashier-response', ['mid' => $mid, 'amount' => $amount, 'currency' => $currency, 'orderId' => $orderId, 'path' => $path, 'hash' => $hash, 'redirect_back' => route('payment.success-kashier') ])->render();

        $res = ['status' => 200,
        /*'response'=>$response,*/
        'redirect' => env('KASHIER_URL') . "/payment?mid=" . $mid . "&orderId=" . $orderId . "&amount=" . $amount . "&currency=EGP&hash=" . $hash . "&merchantRedirect=" . route('payment.success-kashier') . '&lang=ar', 'render' => $rendered_html, 'message' => 'جار تحويلك إلى صفحة الدفع'];
        return $res;
        return $res;

    }
    public function kashier_payment_verify(Request $request)
    {

        if ($response["paymentStatus"] == "SUCCESS")
        {
            $queryString = "";
            $secret = env("KASHIER_IFRAME_KEY");

            foreach ($response as $key => $value)
            {
                if ($key == "signature" || $key == "mode")
                {
                    continue;
                }
                $queryString = $queryString . "&" . $key . "=" . $value;
            }

            $queryString = ltrim($queryString, $queryString[0]);
            $signature = hash_hmac('sha256', $queryString, $secret, false);
            if ($signature == $response["signature"])
            {
                //done
                
            }
            else
            {
                //not done
                
            }
        }
    }

    public function tap_payment_init()
    {
        GoSell::setPrivateKey(env("TAP_MODE") == "live" ? env("TAP_SK_LIVE") : env("TAP_SK_SANDBOX"));
        $charge = GoSell\Charges::create(["amount" => $this->amount, "currency" => "USD", "threeDSecure" => true, "save_card" => false, "description" => "Nafezly Cerdit", "statement_descriptor" => "Nafezly Cerdit", "reference" => ["transaction" => rand(10000, 366666666) , "order" => rand(10000, 366666666) ], "receipt" => ["email" => true, "sms" => true], "customer" => ["first_name" => (null == \Auth::user()
            ->first_name) ? \Auth::user()->name : \Auth::user()->first_name, "middle_name" => "", "last_name" => (null == \Auth::user()
            ->last_name) ? \Auth::user()->name : \Auth::user()->last_name, "email" => \Auth::user()->email, "phone" => ["country_code" => "20", "number" => "1032738088"]], "source" => ["id" => "src_all"], "post" => ["url" => route('payment.success-tap') ], "redirect" => ["url" => route('payment.success-tap') ]]);
        $charge_response_array = (array)$charge;
        $store_payment = $this->store_payment($payment_id = $charge_response_array['id'], $amount = $this->calc_amout_after_transaction("tap", $this->amount) , $source = "credit", $process_data = json_encode($charge) , $currency_code = "USD", $status = strtoupper("PENDING") , $note = $this->amount_in_egp);

        return $res = ['status' => 200,
        /*'response'=>$response,*/
        'redirect' => $charge_response_array["transaction"]->url, 'message' => 'جار تحويلك إلى صفحة الدفع'];
    }
    public function tap_payment_verify($paymentId)
    {
        GoSell::setPrivateKey(env("TAP_MODE") == "live" ? env("TAP_SK_LIVE") : env("TAP_SK_SANDBOX"));
        $retrieved_charge = GoSell\Charges::retrieve($paymentId);
        $retrieved_charge_array = (array)$retrieved_charge;
        $state = ['state' => null];
        if ($retrieved_charge_array["status"] == "CAPTURED")
        {
            $this->update_payment($retrieved_charge_array["id"], "DONE");
        }
        $state['state'] = $retrieved_charge_array["status"];
        $this->set_payment_response($paymentId, json_encode($retrieved_charge));
        return $state;
    }
    public function paymob_payment_init()
    {

        $response = Http::withHeaders(['content-type' => 'application/json'])->post('https://accept.paymobsolutions.com/api/auth/tokens', ["api_key" => $this->PAYMOB_API_KEY]);
        $json = $response->json();

        $response_final = Http::withHeaders(['content-type' => 'application/json'])->post('https://accept.paymobsolutions.com/api/ecommerce/orders', ["auth_token" => $json['token'], "delivery_needed" => "false", "amount_cents" => $this->amount_in_egp * 100, "items" => []]);

        $json_final = $response_final->json();

        $store_payment = $this->store_payment($payment_id = $json_final['id'], $amount = $this->calc_amout_after_transaction("paymob", $this->amount) , $source = "credit", $process_data = json_encode($json_final) , $currency_code = "USD", $status = strtoupper("PENDING") , $note = $this->amount_in_egp);

        $response_final_final = Http::withHeaders(['content-type' => 'application/json'])->post('https://accept.paymobsolutions.com/api/acceptance/payment_keys', ["auth_token" => $json['token'], "expiration" => 36000, "amount_cents" => $json_final['amount_cents'], "order_id" => $json_final['id'], "billing_data" => ["apartment" => "NA", "email" => \Auth::user()->email, "floor" => "NA", "first_name" => (null == \Auth::user()
            ->first_name) ? \Auth::user()->name : \Auth::user()->first_name, "street" => "NA", "building" => "NA", "phone_number" => \Auth::user()->phone, "shipping_method" => "NA", "postal_code" => "NA", "city" => "NA", "country" => "NA", "last_name" => (null == \Auth::user()
            ->last_name) ? \Auth::user()->name : \Auth::user()->last_name, "state" => "NA"], "currency" => "EGP", "integration_id" => env('PAYMOB_MODE') == "live" ? env('PAYMOB_LIVE_INTEGRATION_ID') : env('PAYMOB_SANDBOX_INTEGRATION_ID') ]);

        $response_final_final_json = $response_final_final->json();
        $res = ['status' => 200,
        /*'response'=>$response,*/
        'redirect' => "https://accept.paymobsolutions.com/api/acceptance/iframes/" . env("PAYMOB_IFRAME_ID") . "?payment_token=" . $response_final_final_json['token'], 'message' => 'جار تحويلك إلى صفحة الدفع'];

        return $res;
    }
    public function paymob_payment_verify(Request $request)
    {

        $string = $request['amount_cents'] . $request['created_at'] . $request['currency'] . $request['error_occured'] . $request['has_parent_transaction'] . $request['id'] . $request['integration_id'] . $request['is_3d_secure'] . $request['is_auth'] . $request['is_capture'] . $request['is_refunded'] . $request['is_standalone_payment'] . $request['is_voided'] . $request['order'] . $request['owner'] . $request['pending'] . $request['source_data_pan'] . $request['source_data_sub_type'] . $request['source_data_type'] . $request['success'];
        if (hash_hmac('sha512', $string, env('PAYMOB_HMAC')))
        {
            //done
            
        }
        else
        {
            // payment not completed
            
        }
    }
    public function set_payment_response($payment_id, $response)
    {
        $payment = \App\Balance_summary::where(['payment_id' => $payment_id, 'user_id' => \Auth::user()
            ->id])
            ->firstOrFail();
        $payment->update(['payment_response' => $response]);
        return 1;
    }
    protected function update_payment($payment_id, $status)
    {

        $exists = \App\Balance_summary::where(['payment_id' => $payment_id, 'user_id' => \Auth::user()->id, 'status' => "PENDING", 'type' => 'RECHARGE'])
            ->firstOrFail();

        if ($exists->source == "paypal")
        {
            return \App\Balance_summary::where(['payment_id' => $payment_id, 'user_id' => \Auth::user()->id, 'status' => "PENDING", 'type' => 'RECHARGE'])
                ->update(['status' => strtoupper($status) ]);
        }
        else if ($exists->source == "credit")
        {
            return \App\Balance_summary::where(['payment_id' => $payment_id, 'user_id' => \Auth::user()->id, 'status' => "PENDING", 'type' => 'RECHARGE'])
                ->update(['status' => strtoupper($status) ]);
        }
    }
    public function store_payment($payment_id, $amount, $source, $process_data, $currency_code, $status, $note = null)
    {
        $exists = \App\Balance_summary::where(['user_id' => \Auth::user()->id, 'payment_id' => $payment_id, ])->first();

        if ($exists == null)
        {
            $payment = \App\Balance_summary::create(["user_id" => \Auth::user()->id, 'payment_id' => $payment_id, "type" => "RECHARGE", "amount" => $amount, "status" => strtoupper($status) , "source" => $source, "currency_code" => strtoupper($currency_code) , "process_data" => (string)$process_data, "note" => $note]);
            return $payment->id;

        }
        else
        {
            return $exists->id;
        }
    }
    public function paypal_payment_verify($paymentId, $token, $PayerID)
    {

        $client = \App\Http\Controllers\PaypalControllers\PayPalClient::client();
        $apiContext = new ApiContext(new OAuthTokenCredential(env('PAYPAL_CLIENT_ID') , env('PAYPAL_SECRET')));
        $apiContext->setConfig(array(
            'log.LogEnabled' => true,
            'log.FileName' => 'PayPal.log',
            'log.LogLevel' => 'DEBUG',
            'mode' => env('PAYPAL_MODE')
        ));
        $state = ['state' => null];
        $payment_get = Payment::get($paymentId, $apiContext);
        if (isset($payment_get
            ->payer
            ->status) && $payment_get
            ->payer->status == "VERIFIED")
        {

            $execution = new PaymentExecution;
            $execution->setPayerId($PayerID);
            try
            {
                $result = $payment_get->execute($execution, $apiContext);

                $this->update_payment($payment_get->id, "DONE");
                $this->set_payment_response($paymentId, $result);
                $state['state'] = "DONE";
            }
            catch(\Exception $e)
            {
                exit(1);
                abort(404);
            }

        }
        else if (isset($payment_get->state) && $payment_get->state == "created")
        {
            $this->update_payment($payment_get->id, "PENDING");
            $this->set_payment_response($paymentId, $payment_get);
            $state['state'] = "PENDING";
        }

        return $state;
    }

    public static function calc_amout_after_transaction($method, $amount)
    {
        if ($method == 'paypal')
        {
            return floatval(($amount - env('PAYPAL_FIXED_FEE')) / (1 + env('PAYPAL_PERCENTAGE_FEE')));
        }
        else if ($method == 'paymob')
        {
            return floatval(($amount - env('PAYMOB_FIXED_FEE')) / (1 + env('PAYMOB_PERCENTAGE_FEE')));
        }
        else if ($method == 'tap')
        {
            return floatval(($amount - env('TAP_FIXED_FEE')) / (1 + env('TAP_PERCENTAGE_FEE')));
        }
        else if ($method == 'fawry')
        {
            return floatval(($amount - env('FAWRY_FIXED_FEE')) / (1 + env('FAWRY_PERCENTAGE_FEE')));
        }
        else if ($method == 'kashier')
        {
            return floatval(($amount - env('KASHIER_FIXED_FEE')) / (1 + env('KASHIER_PERCENTAGE_FEE')));
        }
        else if ($method == 'hyperpay')
        {
            return floatval(($amount - env('HYPERPAY_FIXED_FEE')) / (1 + env('HYPERPAY_PERCENTAGE_FEE')));
        }
    }
    public function clac_new_amount($method, $amount)
    {
        if ($method == 'paypal')
        {
            return floatval($amount + ($amount * env('PAYPAL_PERCENTAGE_FEE')) + env('PAYPAL_FIXED_FEE'));
        }
        if ($method == 'paymob')
        {
            return floatval($amount + ($amount * env('PAYMOB_PERCENTAGE_FEE')) + env('PAYMOB_FIXED_FEE'));
        }
        else if ($method == 'tap')
        {
            return floatval($amount + ($amount * env('TAP_PERCENTAGE_FEE')) + env('TAP_FIXED_FEE'));
        }
        else if ($method == 'fawry')
        {
            return floatval($amount + ($amount * env('FAWRY_PERCENTAGE_FEE')) + env('FAWRY_FIXED_FEE'));
        }
        else if ($method == 'kashier')
        {
            return floatval($amount + ($amount * env('KASHIER_PERCENTAGE_FEE')) + env('KASHIER_FIXED_FEE'));
        }
        else if ($method == 'hyperpay')
        {
            return floatval($amount + ($amount * env('HYPERPAY_PERCENTAGE_FEE')) + env('HYPERPAY_FIXED_FEE'));
        }
    }
    public function paypal_payment_init()
    {

        $apiContext = new ApiContext(new OAuthTokenCredential(env('PAYPAL_CLIENT_ID') , env('PAYPAL_SECRET')));
        $apiContext->setConfig(array(
            'log.LogEnabled' => true,
            'log.FileName' => 'PayPal.log',
            'log.LogLevel' => 'DEBUG',
            'mode' => env('PAYPAL_MODE')
        ));
        $payer = new Payer();
        $payer->setPaymentMethod("paypal");
        $item = new Item();
        $item->setName('Nafezly Credit')
            ->setCurrency($this->currency)
            ->setQuantity(1)
            ->setPrice($this->amount);
        $itemList = new ItemList();
        $itemList->setItems(array(
            $item
        ));
        $details = new Details();
        $details->setSubtotal($this->amount);
        $amount = new Amount();
        $amount->setCurrency($this->currency)
            ->setTotal($this->amount)
            ->setDetails($details);
        $transaction = new Transaction();
        $transaction->setAmount($amount)->setItemList($itemList)->setDescription("Nafezly Credit")
            ->setInvoiceNumber(uniqid());
        $redirectUrls = new RedirectUrls();
        $redirectUrls->setReturnUrl(route('payment.success'))
            ->setCancelUrl(route('balance'));
        $payment = new Payment();
        $payment->setIntent("sale")
            ->setPayer($payer)->setRedirectUrls($redirectUrls)->setTransactions(array(
            $transaction
        ));
        $counter = 0;
        
            try
            {
                $payment->create($apiContext);
                $approvalUrl = $payment->getApprovalLink();
                $res = ['status' => 200, 'redirect' => $approvalUrl, 'message' => 'خطأ اثناء التنفيذ برجاء الرجوع للبنك الخاص بك او التأكد من سلامة البيانات المدخلة'];

                $store_payment = $this->store_payment($payment_id = $payment->id, $amount = $this->calc_amout_after_transaction("paypal", $payment->transactions[0]
                    ->amount
                    ->total) , $source = "paypal", $process_data = $payment, $currency_code = strtoupper($payment->transactions[0]
                    ->amount
                    ->currency) , $status = strtoupper("PENDING"));

                return $res;
            }
            catch(\Exception $e)
            {
                $counter += 1;
                if ($counter < 3) gotoout_tyr;
            }

            $res = ['status' => 200, 'redirect' => route('balance') , 'message' => 'خطأ اثناء التنفيذ برجاء المحاولة مرة أخرى لاحقاً'];

            return $res;
        }
    public function fawry_payment_init()
    {

        $ref_id = uniqid();
        $process_data = ['FAWRY_MERCHANT' => $this->FAWRY_MERCHANT, 'user_id' => \Auth::user()->id, 'ref_id' => $ref_id, 'item_id' => 1, 'item_quantity' => 1, 'amount' => $this->amount, 'amount_in_egp' => $this->amount_in_egp, 'FAWRY_SECRET' => $this->FAWRY_SECRET];
        $sec = $this->FAWRY_MERCHANT . $ref_id . \Auth::user()->id . 1 . 1 . $this->amount_in_egp . $this->FAWRY_SECRET;
        $hashed_sec = hash('sha256', $sec);
        $payment = $this->store_payment($payment_id = $hashed_sec, $amount = $this->calc_amout_after_transaction("fawry", $this->amount) , $source = "credit", $process_data = json_encode($process_data) , $currency_code = "USD", $status = "PENDING");
        $res = ['status' => '200', 'redirect' => route('balance') , 'append' => view('site-templates.fawry-response', ['ref_num' => $payment, 'price' => $this->amount_in_egp, 'sec' => $hashed_sec])->render() , 'message' => 'جار تحويلك إلى صفحة الدفع'];
        return $res;

    }
    public function fawry_payment_verify($nafezly_payment_id)
    {

        $FAWRY_MERCHANT = (env('FAWRY_MODE') == 'live' ? env('FAWRY_LIVE_MERCHANT') : env('FAWRY_MERCHANT'));
        $FAWRY_SECRET = (env('FAWRY_MODE') == 'live' ? env('FAWRY_LIVE_SECRET') : env('FAWRY_SECRET'));

        $hash = hash('sha256', $FAWRY_MERCHANT . $nafezly_payment_id . $FAWRY_SECRET);
        $payment = \App\Balance_summary::where('id', $nafezly_payment_id)->firstOrFail();
        $url = env('FAWRY_MODE') == "live" ? env("FAWRY_LIVE_URL") : env("FAWRY_URL");
        $response = Http::get($url . 'ECommerceWeb/Fawry/payments/status?merchantCode=' . $FAWRY_MERCHANT . '&merchantRefNumber=' . $nafezly_payment_id . '&signature=' . $hash);
        $state = ['state' => null];
        $local_res = (array)json_decode(($payment->process_data));

        if ($response->offsetGet('statusCode') == 200 && $response->offsetGet('paymentStatus') == "PAID" && (int)$local_res['amount_in_egp'] == (int)$response->offsetGet('paymentAmount'))
        {

            $this->update_payment($payment->payment_id, "DONE");
            $state['state'] = "DONE";
        }
        else if ($response->offsetGet('statusCode') != 200)
        {
            $state['state'] = "CANCELED";
        }
        $this->set_payment_response($payment->payment_id, $response->body());
        return $state;
    }
    public function hyperpay_payment_init(Request $request)
    {

        $order = \App\Models\Order::create(['user_id' => auth()->user()->id, 'course_id' => $course->id, 'type' => $type, 'status' => 'PENDING']);
        $payment = \App\Models\Payment::create(['user_id' => auth()->user()->id, 'order_id' => $order->id, 'type' => $order->type, 'status' => 'PENDING', 'amount' => $course->price, 'source' => $request->source
        #CREDIT or MADA
        ]);

        $url = env('HYPERPAY_URL');
        $data = "entityId=DB" . "&amount=" . $course->price . "&currency=SAR" . "&paymentType=" . $paymentType . "&merchantTransactionId=" . $order->id . //your unique id from the dataBase
        //"&testMode=EXTERNAL".
        "&billing.street1=riyadh" . "&billing.city=riyadh" . "&billing.state=riyadh" . "&billing.country=SA" . "&billing.postcode=123456" . "&customer.email=" . auth()
            ->user()->email . "&customer.givenName=" . current(explode(' ', auth()
            ->user()
            ->name)) . "&customer.surname=" . array_slice(explode(' ', auth()
            ->user()
            ->name) , -1) [0];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Authorization:Bearer ' . env('HYPERPAY_TOKEN')
        ));
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // this should be set to true in production
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $responseData = curl_exec($ch);
        if (curl_errno($ch))
        {
            return curl_error($ch);
        }
        curl_close($ch);

        $payment_id = json_decode($responseData)->id;
        $payment->update(['payment_id' => $payment_id]);

        return view('pages.checkout-final', compact('payment_id', 'payment'));

    }
    public function hyperpay_payment_verify(Request $request)
    {

        //dd($request->all());
        $payment = \App\Models\Payment::where('payment_id', $request->id)
            ->firstOrFail();

        if ($payment->status == "PENDING" && auth()
            ->user()->id == $payment->user_id)
        {
            $order = \App\Models\Order::where('id', $payment->order_id)
                ->firstOrFail();

            //$entityId="";
            if ($payment->source == "CREDIT") $entityId = env('HYPERPAY_CREDIT_ID');
            else if ($payment->source == "MADA") $entityId = env('HYPERPAY_MADA_ID');

            $url = env("HYPERPAY_BASE_URL") . "/v1/checkouts/" . $request['id'] . "/payment";
            $url .= "?entityId=" . $entityId;

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                'Authorization: Bearer ' . env('HYPERPAY_TOKEN')
            ));
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // this should be set to true in production
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            $responseData = curl_exec($ch);
            if (curl_errno($ch))
            {
                return curl_error($ch);
            }
            curl_close($ch);
            $final_result = (array)json_decode($responseData, true);

            if ($final_result["result"]["code"] == "000.000.000")
            {
                $payment->update(['status' => "DONE", 'process_data' => json_encode(json_decode($responseData, true)) ]);
                \App\Models\Order::where('id', $payment->order_id)
                    ->update(['status' => "DONE"]);
                emotify('success', 'تمت عملية الدفع بنجاح');
                return redirect('/');

            }
            else
            {
                emotify('error', 'حدث خطأ أثناء عملية الدفع راجع البنك الخاص بك ' . $final_result["result"]["description"]);
                return redirect('/');
            }
            return 0;

        }
    }
}
