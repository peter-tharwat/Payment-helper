<?php

namespace PaymentHelper\Contracts;

interface GatewayContract
{	
	public function init();
	public function process();
	public function verify();
}
