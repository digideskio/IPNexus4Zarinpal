<?php
/**
 * @brief		Zarinpal Gateway
 * @author		<a href='http://skinod.com.com'>Skinod</a>
 * @copyright	(c) 2015 Skinod.com
 */

namespace IPS\nexus\Gateway;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * PayPal Gateway
 */
class _Zarinpal extends \IPS\nexus\Gateway
{
	// URL also Can be https://ir.zarinpal.com/pg/services/WebGate/wsdl
	const ZARINPAL_URL = 'https://de.zarinpal.com/pg/services/WebGate/wsdl';

	/**
	 * Check the gateway can process this...
	 *
	 * @param	$amount			\IPS\nexus\Money	The amount
	 * @param	$billingAddress	\IPS\GeoLocation	The billing address
	 * @return	bool
	 */
	public function checkValidity( \IPS\nexus\Money $amount, \IPS\GeoLocation $billingAddress )
	{
		// only accept IRR
		if ($amount->currency != 'IRR')
		{
			return FALSE;
		}
				
		return parent::checkValidity( $amount, $billingAddress );
	}
		
	/* !Payment Gateway */
		
	/**
	 * Authorize
	 *
	 * @param	\IPS\nexus\Transaction					$transaction	Transaction
	 * @param	array|\IPS\nexus\Customer\CreditCard	$values			Values from form OR a stored card object if this gateway supports them
	 * @param	\IPS\nexus\Fraud\MaxMind\Request|NULL	$maxMind		*If* MaxMind is enabled, the request object will be passed here so gateway can additional data before request is made	
	 * @return	\IPS\DateTime|NULL		Auth is valid until or NULL to indicate auth is good forever
	 * @throws	\LogicException			Message will be displayed to user
	 */
	public function auth( \IPS\nexus\Transaction $transaction, $values, \IPS\nexus\Fraud\MaxMind\Request $maxMind = NULL )
	{
		$transaction->save();

		$data = array(
			'Amount' 				=> $transaction->amount->amount / 10,
			'Description' 			=> $transaction->invoice->title,
			'Email' 				=> $transaction->member->email,
			'Mobile' 				=> $transaction->member->cm_phone,
			'CallbackURL' 			=> (string) \IPS\Settings::i()->base_url . 'applications/nexus/interface/gateways/zarinpal.php?nexusTransactionId=' . $transaction->id
		);

		$res = $this->api($data);

		if($res['Status'] == 100) {
			$settings = json_decode( $this->settings, TRUE );
			\IPS\Output::i()->redirect( \IPS\Http\Url::external( 'https://www.zarinpal.com/pg/StartPay/'.$res['Authority'].''.($settings['zarin_gate']?'/ZarinGate':'') ) );
		}

		throw new \RuntimeException;
	}
	
	/**
	 * Capture
	 *
	 * @param	\IPS\nexus\Transaction	$transaction	Transaction
	 * @return	void
	 * @throws	\LogicException
	 */
	public function capture( \IPS\nexus\Transaction $transaction ) {

	}
				
	// 	throw new \RuntimeException;
	// }
		
	/* !ACP Configuration */
	
	/**
	 * Settings
	 *
	 * @param	\IPS\Helpers\Form	$form	The form
	 * @return	void
	 */
	public function settings( &$form )
	{
		$settings = json_decode( $this->settings, TRUE );
		$form->add( new \IPS\Helpers\Form\Text( 'zarinpal_merchant_id', $this->id ?$settings['merchant_id']:'', TRUE ) );
		$form->add( new \IPS\Helpers\Form\YesNo( 'zarinpal_zarin_gate', $this->id ?$settings['zarin_gate']:'', TRUE ) );
	}
	
	/**
	 * Test Settings
	 *
	 * @param	array	$settings	Settings
	 * @return	array
	 * @throws	\InvalidArgumentException
	 */
	public function testSettings( $settings )
	{		
		return $settings;
	}

	/* !Utility Methods */
	
	/**
	 * Send API Request
	 *
	 * @param	array	$data	The data to send
	 * @return	array
	 */
	public function api( $data, $verify = FALSE )
	{
		$settings = json_decode( $this->settings, TRUE );

		$data['MerchantID'] = $settings['merchant_id'];

		$func = $verify?'PaymentVerification':'PaymentRequest';

		if(class_exists('SoapClient')) {
			$client = new \SoapClient(self::ZARINPAL_URL, array('encoding' => 'UTF-8')); 
			$result = $client->$func($data);
		}else{
			require_once \IPS\ROOT_PATH . "/system/3rd_party/nusoap/nusoap.php";
			$client = new \nusoap_client(self::ZARINPAL_URL, 'wsdl'); 
			$client->soap_defencoding = 'UTF-8';
			$result = $client->call($func, array($data));
		}

		return (array) $result;
	}

}