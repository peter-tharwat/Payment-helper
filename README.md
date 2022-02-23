## About:

this repo to help laravel users with the integration of payment gateways in there projects

## Supported gateways

- [PayPal](https://paypal.com/)
- [PayMob](https://paymob.com/)
- [WeAccept](https://paymob.com/)
- [Kashier](https://kashier.io/)
- [Fawry](https://fawry.com/)
- [HyperPay](https://www.hyperpay.com/)
- [TAP](https://www.tap.company/)

## Pre requirements

### For Paypal Gateway please install PayPal SDK

```bash
composer require paypal/rest-api-sdk-php
```

### For Tap Gateway Please Install Formal Package

```bash
composer require tappayments/gosell
```

## Make Sure to make 2 Tables on Database 
( orders & payments )

### Orders Table

```bash
Schema::create('orders', function (Blueprint $table) {
		$table->bigIncrements('id');
		$table->unsignedBigInteger('user_id');
		$table->foreign('user_id')->references('id')->on("users")->onDelete('cascade'); 
		$table->string('type')->default("ORDER");  # type of order , can be course,product,service,etc
		$table->unsignedBigInteger('type_id')->nullable(); # the id of the type , for example : if the product id is 10 then type=product & type_id=10
		$table->string('status')->default("PENDING"); #PENDING,CANCELED,DONE
		$table->timestamps();
}
```

### Payments Table

```bash
Schema::create('payments', function (Blueprint $table) {   
    $table->bigIncrements('id');
    $table->unsignedBigInteger('user_id');
    $table->foreign('user_id')->references('id')->on("users")->onDelete('cascade');  
    $table->unsignedBigInteger('order_id'); # FROM THE PREVIOUS TABLE
    $table->foreign('order_id')->references('id')->on("orders")->onDelete('cascade'); 
    $table->string('type')->default('RECHARGE');  #RECHARGE,REFUND,WITHDRAW,..etc
    $table->string('status'); #PENDING,CANCELED,DONE
    $table->float('amount')->default(0); 
    $table->string('source')->nullable(); #PAYMENT GATEWAY LIKE : PAYPAL,KASHIER,PAYMOB,FAWRY,..etc
    $table->string('payment_id')->nullable(); #UNIQUE ID YOU CAN GENERATE ONE , SOMETIMES RETURNED FROM GATEWAY TO TRACE YOUR PAYMENT
    $table->json('process_data')->nullable(); #THE RESPONSE OF SERVER OF GATEWAY
    $table->text('description')->nullable();
    $table->timestamps();
});
```

## ENV KEYS

```bash
#TAP
TAP_FIXED_FEE=
TAP_SK_LIVE=
TAP_SK_SANDBOX=
TAP_PERCENTAGE_FEE=

#PAYMOB & WEACCEPT
PAYMOB_FIXED_FEE=
PAYMOB_API_KEY=
PAYMOB_MOOD=
PAYMOB_LIVE_INTEGRATION_ID=
PAYMOB_SANDBOX_INTEGRATION_ID=
PAYMOB_PERCENTAGE_FEE=
PAYMOB_MODE=

#PAYPAL
PAYPAL_FIXED_FEE=
PAYPAL_PERCENTAGE_FEE=
PAYPAL_CLIENT_ID=
PAYPAL_SECRET=
PAYPAL_MODE=

#FAWRY
FAWRY_FIXED_FEE=
FAWRY_PERCENTAGE_FEE=
FAWRY_MERCHANT=
FAWRY_SECRET=
FAWRY_LIVE_MERCHANT=
FAWRY_LIVE_SECRET=
FAWRY_LIVE_URL=
FAWRY_URL=
FAWRY_MODE=

#HYPER_PAY
HYPERPAY_TOKEN=
HYPERPAY_CREDIT_ID=
HYPERPAY_MADA_ID=
HYPERPAY_BASE_URL=
HYPERPAY_TOKEN=

#KASHIER
KASHIER_FIXED_FEE=
KASHIER_PERCENTAGE_FEE=
KASHIER_ACCOUNT_KEY=
KASHIER_IFRAME_KEY=
KASHIER_URL=
```

## Helpers functions

you can control the fees that you need to apply on the payment ( recharging fees to client )

```bash
public function clac_new_amount($method,$amount){
    if($method=='paypal'){
        return floatval($amount+($amount*env('PAYPAL_PERCENTAGE_FEE'))+env('PAYPAL_FIXED_FEE'));
    }else if($method=='paymob'){
        return floatval($amount+($amount*env('PAYMOB_PERCENTAGE_FEE'))+env('PAYMOB_FIXED_FEE'));
    }else if($method=='tap'){
        return floatval($amount+($amount*env('TAP_PERCENTAGE_FEE'))+env('TAP_FIXED_FEE'));
    }else if($method=='fawry'){
        return floatval($amount+($amount*env('FAWRY_PERCENTAGE_FEE'))+env('FAWRY_FIXED_FEE'));
    }else if($method=='kashier'){
        return floatval($amount+($amount*env('KASHIER_PERCENTAGE_FEE'))+env('KASHIER_FIXED_FEE'));
    }else if($method=='hyperpay'){
        return floatval($amount+($amount*env('HYPERPAY_PERCENTAGE_FEE'))+env('HYPERPAY_FIXED_FEE'));
    }
}
```
