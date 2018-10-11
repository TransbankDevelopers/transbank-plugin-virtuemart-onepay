<?php
/**
 * Plugin for Transbank Onepay
 * @autor vutreras (victor.utreras@continuum.cl)
 */

defined('_JEXEC') or die('Restricted access');

if (!class_exists('vmPSPlugin')) {
	require(VMPATH_PLUGINLIBS . DS . 'vmpsplugin.php');
}

defined ('DIR_SYSTEM') or define ('DIR_SYSTEM', VMPATH_PLUGINS . '/vmpayment/transbank_onepay/transbank_onepay/');

if (!class_exists('OnepayBase')) {
    require_once(DIR_SYSTEM.'library/transbank-sdk-php/init.php');
}
if (!class_exists('DiagnosticPDF')) {
    require_once(DIR_SYSTEM.'library/DiagnosticPDF.php');
}

use \Transbank\Onepay\OnepayBase;
use \Transbank\Onepay\ShoppingCart;
use \Transbank\Onepay\Item;
use \Transbank\Onepay\Transaction;
use \Transbank\Onepay\Options;
use \Transbank\Onepay\Refund;
use \Transbank\Onepay\Exceptions\TransbankException;
use \Transbank\Onepay\Exceptions\TransactionCreateException;
use \Transbank\Onepay\Exceptions\TransactionCommitException;
use \Transbank\Onepay\Exceptions\RefundCreateException;

/**
 * Transbank Onepay Payment plugin implementation
 * @autor vutreras (victor.utreras@continuum.cl)
 */
class plgVmPaymentTransbank_Onepay extends vmPSPlugin {

    const PLUGIN_VERSION = '1.0.0'; //version of plugin payment
    const PLUGIN_CODE = 'transbank_onepay'; //code of plugin for virtuemart
    const APP_KEY = 'D2044F06-B8AA-4653-8409-2571C2A9E273'; //app key for virtuemart

    const JS_SDK = 'https://cdn.rawgit.com/TransbankDevelopers/transbank-sdk-js-onepay/v1.5.4/lib/merchant.onepay.min.js';

    //constants for log handler
    const LOG_FILENAME = 'onepay-log'; //name of the log file
    const LOG_DEBUG_ENABLED = false; //enable or disable debug logs
    const LOG_INFO_ENABLED = true; //enable or disable info logs
    const LOG_ERROR_ENABLED = true; //enable or disable error logs

    //constants for keys configurations
    const TRANSBANK_ONEPAY_ENVIRONMENT = 'transbank_onepay_environment';
    const TRANSBANK_ONEPAY_APIKEY_TEST = 'transbank_onepay_apikey_test';
    const TRANSBANK_ONEPAY_SHARED_SECRET_TEST = 'transbank_onepay_shared_secret_test';
    const TRANSBANK_ONEPAY_APIKEY_LIVE = 'transbank_onepay_apikey_live';
    const TRANSBANK_ONEPAY_SHARED_SECRET_LIVE = 'transbank_onepay_shared_secret_live';
    const TRANSBANK_ONEPAY_LOGO_URL = 'transbank_onepay_logo_url';

    const TRANSBANK_ONEPAY_ORDER_STATUS_ID_PAID = 'transbank_onepay_order_status_id_paid';
    const TRANSBANK_ONEPAY_ORDER_STATUS_ID_FAILED = 'transbank_onepay_order_status_id_failed';
    const TRANSBANK_ONEPAY_ORDER_STATUS_ID_REJECTED = 'transbank_onepay_order_status_id_rejected';
    const TRANSBANK_ONEPAY_ORDER_STATUS_ID_CANCELLED = 'transbank_onepay_order_status_id_cancelled';
    const TRANSBANK_ONEPAY_ORDER_STATUS_CONFIGURED = 'transbank_onepay_order_status_configured';

    function __construct (& $subject, $config) {
		parent::__construct ($subject, $config);
		$this->tableFields = array_keys($this->getTableSQLFields());
		$this->_tablepkey = 'id';
        $this->_tableId = 'id';

        if ($config['name'] == self::PLUGIN_CODE) {

            $varsToPush = $this->getVarsToPush();
            $orderStatuses = $this->getOrderStatuses();

            //create un array of order status original with order status configured by user, used for show it in pdf of diagnostic
            $dataPost = $_POST['params'];
            $keyBase = 'transbank_onepay_order_status_id_';
            $orderStatusValues = array();

            foreach ($dataPost as $key => $value) {
                if (strpos($key, $keyBase) !== false) {
                    $orderStatusNameOriginal = substr($key, strlen($keyBase), strlen($key));
                    $orderStatusNameConfiguredByUser = $this->getOrderStatusName($orderStatuses, $value);
                    array_push($orderStatusValues, $orderStatusNameOriginal . '(' . $orderStatusNameConfiguredByUser . ')');
                }
            }

            //add TRANSBANK_ONEPAY_ORDER_STATUS_CONFIGURED to varsToPush to save in config system
            $varsToPush[self::TRANSBANK_ONEPAY_ORDER_STATUS_CONFIGURED] = array(implode(',', $orderStatusValues), 'char');

            $this->setConfigParameterable($this->_configTableFieldName, $varsToPush);

            //redirect to create the diagnostic pdf
            if (isset($_GET['diagnostic_pdf'])) {
                $this->createDiagnosticPdf();
            }
        }
    }

    /**
     * Create the table for this plugin if it does not yet exist.
     *
     * @return bool
     * @Override
     */
    function getVmPluginCreateTableSQL() {
        return $this->createTableSQL('Payment Transbank_Onepay Table');
    }

    /**
	 * Fields to create the payment table
     *
	 * @return string SQL fields
     * @Override
	 */
    function getTableSQLFields() {
        $SQLfields = array(
            'id' => 'int(1) UNSIGNED NOT NULL AUTO_INCREMENT',
			'virtuemart_order_id' => 'int(1) UNSIGNED',
			'order_number' => 'char(64)',
			'virtuemart_paymentmethod_id' => 'mediumint(1) UNSIGNED',
			'payment_name' => 'varchar(5000)',
			'payment_order_total' => 'decimal(15,5) NOT NULL',
			'payment_currency' => 'smallint(1)',
			'email_currency' => 'smallint(1)',
			'cost_per_transaction' => 'decimal(10,2)',
			'cost_percent_total' => 'decimal(10,2)',
			'tax_id' => 'smallint(1)',
            self::TRANSBANK_ONEPAY_ENVIRONMENT => 'varchar(10)',
            self::TRANSBANK_ONEPAY_APIKEY_TEST => 'varchar(100)',
            self::TRANSBANK_ONEPAY_SHARED_SECRET_TEST => 'varchar(100)',
            self::TRANSBANK_ONEPAY_APIKEY_LIVE => 'varchar(100)',
            self::TRANSBANK_ONEPAY_SHARED_SECRET_LIVE => 'varchar(100)',
            self::TRANSBANK_ONEPAY_LOGO_URL => 'varchar(500)',
            self::TRANSBANK_ONEPAY_ORDER_STATUS_ID_PAID => 'varchar(10)',
            self::TRANSBANK_ONEPAY_ORDER_STATUS_ID_FAILED => 'varchar(10)',
            self::TRANSBANK_ONEPAY_ORDER_STATUS_ID_REJECTED => 'varchar(10)',
            self::TRANSBANK_ONEPAY_ORDER_STATUS_ID_CANCELLED => 'varchar(10)',
            self::TRANSBANK_ONEPAY_ORDER_STATUS_CONFIGURED => 'varchar(100)'
        );
        return $SQLfields;
    }

    /**
	 * This event is fired during the checkout process. It can be used to validate the
	 * method data as entered by the user.
	 *
	 * @return boolean True when the data was valid, false otherwise. If the plugin is not activated, it should return null.
	 * @Override
	 */
    public function plgVmOnCheckoutCheckDataPayment(VirtueMartCart $cart) {

		if (!$this->selectedThisByMethodId($cart->virtuemart_paymentmethod_id)) {
			return NULL; // Another method was selected, do nothing
		}
		if (!($this->_currentMethod = $this->getVmPluginMethod($cart->virtuemart_paymentmethod_id))) {
			return NULL;
        }

        $session = JFactory::getSession();
        $onepayAction = $session->get('onepayAction');
        if ($onepayAction == 'show' || $onepayAction == NULL) {
            $this->logInfo('Load JS-sdk');
            $jsSdk = self::JS_SDK;
            $jsScript = "<script type='text/javascript' src='{$jsSdk}'></script>";
            echo($jsScript);
        }
        return true;
	}

    function plgVmOnCheckoutAdvertise(VirtueMartCart $cart, &$payment_advertise) {

        if (!$this->selectedThisByMethodId($cart->virtuemart_paymentmethod_id)) {
			return NULL; // Another method was selected, do nothing
		}
		if (!($this->_currentMethod = $this->getVmPluginMethod($cart->virtuemart_paymentmethod_id))) {
			return NULL;
        }

        $session = JFactory::getSession();
        $onepayAction = $session->get('onepayAction');
        $this->logInfo('plgVmOnCheckoutAdvertise sesion onepayAction: ' . $onepayAction);

        if ($onepayAction == 'show') {

            $session->set('onepayAction', 'showed');

            $urlCreate = 'index.php?option=com_virtuemart&view=cart&onepayAction=create';
            $urlCommit = 'index.php?option=com_virtuemart&view=cart&onepayAction=commit';
            $urlLogo = $this->getLogoUrl();
            $urlLogo = $urlLogo != NULL ? $urlLogo : '';

            $jsScript = "<script type='text/javascript'>
                    if (Onepay != undefined) {
                        var options = {
                            endpoint: '{$urlCreate}',
                            commerceLogo: '{$urlLogo}',
                            callbackUrl: '{$urlCommit}'
                        };
                        Onepay.checkout(options);
                    } else {
                        alert('Sdk JS de Onepay no ha sido cargado, refrescar la pagina');
                    }
                </script>";

            echo($jsScript);

        } else {

            $onepayAction = vRequest::getCmd('onepayAction');
            $this->logInfo('plgVmOnCheckoutAdvertise get onepayAction: ' . $onepayAction);

            if ($onepayAction == 'create') {

                $session->set('onepayAction', 'created');

                $channel = vRequest::getCmd('channel');
                $items = array();

                $items[] = array(
                    'name' => 'Prueba',
                    'quantity' => 1,
                    'price' => 100,
                );

                $response = $this->createTransaction($channel, self::PLUGIN_CODE, $items);

                header('Content-Type: application/json');
                echo json_encode($response);
                die;

            } else if ($onepayAction == 'commit') {

                $status = vRequest::getCmd('status');
                $occ = vRequest::getCmd('occ');
                $externalUniqueNumber = vRequest::getCmd('externalUniqueNumber');

                $response = $this->commitTransaction($status, $occ, $externalUniqueNumber);

                $message = $response['message'];
                $detail = $response['detail'];
                $metadata = json_encode($response['metadata']);

                $this->logInfo(json_encode($response));

                if (isset($response['error'])) {

                    $session->set('onepayAction', 'error');
                    $app = JFactory::getApplication();
                    $app->enqueueMessage($message, 'error');
                    $app->redirect('index.php?option=com_virtuemart&view=cart');
                    die;

                } else {

                    $session->set('onepayAction', 'success');
                    $session->set('onepaySuccessDetail', $detail);

                    $jsScript = "<script type='text/javascript'>
                        jQuery(document).ready(function($) {
                            jQuery('#checkoutFormSubmit').click();
                        });
                        </script>";
                    echo($jsScript);
                }
            }
        }
    }

    /**
	 *
	 * @param $cart
	 * @param $order
	 * @return bool|null|void
	 */
	function plgVmConfirmedOrder(VirtueMartCart $cart, $order) {

        if (!$this->selectedThisByMethodId($cart->virtuemart_paymentmethod_id)) {
			return NULL; // Another method was selected, do nothing
		}
		if (!($this->_currentMethod = $this->getVmPluginMethod($cart->virtuemart_paymentmethod_id))) {
			return NULL;
        }

        //$this->logInfo ('plgVmConfirmedOrder order number: ' . $order['details']['BT']->order_number);

        $session = JFactory::getSession();

        $onepayAction = $session->get('onepayAction');

        $this->logInfo('plgVmConfirmedOrder sesion onepayAction: ' . $onepayAction);

        if ($onepayAction == 'success') {

            $session->clear('onepayAction');

            $onepaySuccessDetail = $session->get('onepaySuccessDetail');

            $htmlSuccess = "
            <div id='system-message-container'>
                <dl id='system-message'>
                    <dd class='success message'>
                        <ul>
                            <li>{$onepaySuccessDetail}</li>
                        </ul>
                    </dd>
                </dl>
            </div>";

            vRequest::setVar('html', $htmlSuccess);

            //$cart = VirtueMartCart::getCart();
		    //$cart->emptyCart();

            return true;
        } else {
            $session->set('onepayAction', 'show');
            $app = JFactory::getApplication();
            $app->enqueueMessage('Esperando por Transbank Onepay', 'success');
            $app->redirect('index.php?option=com_virtuemart&view=cart');
            die;
        }
    }

    /**
     * return true for show the Transbank Onepay payment method in cart screen
     *
     * @param $cart
     * @param $method
     * @param $cart_prices
     * @Override
     */
    protected function checkConditions(VirtueMartCart $cart, $method, $cart_prices) {
        if (intval($cart_prices['salesPrice']) > 0) {
            return true;
        }
        return false;
    }

	/**
	 * Create the table for this plugin if it does not yet exist.
	 * This functions checks if the called plugin is active one.
	 * When yes it is calling the standard method to create the tables
     *
     * @param $jplugin_id
	 * @Override
	 */
	function plgVmOnStoreInstallPaymentPluginTable($jplugin_id) {
        //$this->logInfo('plgVmOnStoreInstallPaymentPluginTable');
		return $this->onStoreInstallPluginTable($jplugin_id);
	}

	/**
	 * This event is fired after the payment method has been selected. It can be used to store
	 * additional payment info in the cart.
     *
	 * @param VirtueMartCart $cart: the actual cart
     * @param $msg
	 * @return null if the payment was not selected, true if the data is valid, error message if the data is not valid
	 * @Override
	 */
	public function plgVmOnSelectCheckPayment(VirtueMartCart $cart,  &$msg) {
        //$this->logInfo('plgVmOnSelectCheckPayment');
		return $this->OnSelectCheck($cart);
	}

	/**
	 * This event is fired to display the pluginmethods in the cart (edit shipment/payment) for exampel
	 *
	 * @param object  $cart Cart object
	 * @param integer $selected ID of the method selected
	 * @return boolean True on success, false on failures, null when this plugin was not selected.
	 * On errors, JError::raiseWarning (or JError::raiseError) must be used to set a message.
	 * @Override
	 */
	public function plgVmDisplayListFEPayment(VirtueMartCart $cart, $selected = 0, &$htmlIn) {
        //$this->logInfo('plgVmDisplayListFEPayment');
		return $this->displayListFE($cart, $selected, $htmlIn);
	}

    /*
     * Calculate the price (value, tax_id) of the selected method
     * It is called by the calculator
     * This function does NOT to be reimplemented. If not reimplemented, then the default values from this function are taken.
     *
     * @param VirtueMartCart $cart
     * @param array          $cart_prices
     * @param                $cart_prices_name
     *
     * @return
     * @Override
     */
	public function plgVmonSelectedCalculatePricePayment(VirtueMartCart $cart, array &$cart_prices, &$cart_prices_name) {
        //$this->logInfo('plgVmonSelectedCalculatePricePayment');
		return $this->onSelectedCalculatePrice($cart, $cart_prices, $cart_prices_name);
	}

    /**
	 * @param $virtuemart_paymentmethod_id
	 * @param $paymentCurrencyId
	 * @return bool|null
     * @Override
	 */
    function plgVmgetPaymentCurrency($virtuemart_paymentmethod_id, &$paymentCurrencyId) {
        //$this->logInfo('plgVmgetPaymentCurrency');
		if (!($method = $this->getVmPluginMethod($virtuemart_paymentmethod_id))) {
			return NULL;
		} // Another method was selected, do nothing

		if (!$this->selectedThisElement($method->payment_element)) {
			return FALSE;
		}

		$this->getPaymentCurrency($method);
		$paymentCurrencyId = $method->payment_currency;
    }

	/**
	 * Checks how many plugins are available. If only one, the user will not have the choice. Enter edit_xxx page
	 * The plugin must check first if it is the correct type
	 *
	 * @param VirtueMartCart cart: the cart object
	 * @return null if no plugin was found, 0 if more then one plugin was found,  virtuemart_xxx_id if only one plugin is found
	 * @Override
	 */
	function plgVmOnCheckAutomaticSelectedPayment(VirtueMartCart $cart, array $cart_prices = array(), &$paymentCounter) {
        //$this->logInfo('plgVmOnCheckAutomaticSelectedPayment');
		return $this->onCheckAutomaticSelected($cart, $cart_prices, $paymentCounter);
    }

	/**
	 * This method is fired when showing the order details in the frontend.
	 * It displays the method-specific data.
	 *
	 * @param integer $order_id The order ID
	 * @return mixed Null for methods that aren't active, text (HTML) otherwise
	 * @Override
	 */
	public function plgVmOnShowOrderFEPayment($virtuemart_order_id, $virtuemart_paymentmethod_id, &$payment_name) {
        //$this->logInfo('plgVmOnShowOrderFEPayment');
		$this->onShowOrderFE($virtuemart_order_id, $virtuemart_paymentmethod_id, $payment_name);
	}

	/**
	 * This method is fired when showing when priting an Order
	 * It displays the the payment method-specific data.
	 *
	 * @param integer $_virtuemart_order_id The order ID
	 * @param integer $method_id  method used for this order
	 * @return mixed Null when for payment methods that were not selected, text (HTML) otherwise
	 * @Override
	 */
	function plgVmonShowOrderPrintPayment($order_number, $method_id) {
		return $this->onShowOrderPrint($order_number, $method_id);
    }

    /**
	 * @param $data
	 * @return bool
     * @Override
	 */
    function plgVmDeclarePluginParamsPaymentVM3(&$data) {
        $ret = $this->declarePluginParams('payment', $data);
        if ($ret == 1) {
            $this->logInfo('Configuracion guardada correctamente');
        }
        return $ret;
    }

    /**
	 * @param $name
	 * @param $id
	 * @param $table
	 * @return bool
     * @Override
	 */
	function plgVmSetOnTablePluginParamsPayment($name, $id, &$table) {
		return $this->setOnTablePluginParams($name, $id, $table);
    }
    /*
    function plgVmOnPaymentNotification() {
        $this->logInfo('plgVmOnPaymentNotification');
        return null;
    }*/

    function plgVmOnPaymentResponseReceived(&$html) {
        $this->logInfo('plgVmOnPaymentResponseReceived');
        $cart = VirtueMartCart::getCart();
		$cart->emptyCart();
        return true;
    }

    //Helpers

    /**
     * return array os order status with id and name fields
     */
    private function getOrderStatuses() {
        $options = array();
		$db = JFactory::getDbo();
		$query = 'SELECT `order_status_code` AS value, `order_status_name` AS text
                 FROM `#__virtuemart_orderstates`
                 WHERE `virtuemart_vendor_id` = 1
                 ORDER BY `ordering` ASC ';
		$db->setQuery($query);
		$values = $db->loadObjectList();
		foreach ($values as $value) {
            $id = $value->value;
            $name = $value->text;
            $index = strrpos($name, "_");
            $name = substr($name, $index + 1, strlen($name));
            $options[] = array('id' => $id, 'name' => $name);
        }
        return $options;
    }

    /**
     * filter and return order info by order status name
     */
    private function getOrderStatusId($orderStatuses, $statusName) {
        foreach ($orderStatuses as $orderStatus) {
            if (trim(strtoupper($orderStatus['name'])) == trim(strtoupper($statusName))) {
                return $orderStatus['id'];
            }
        }
        return '';
    }

    /**
     * filter and return order info by order status id
     */
    private function getOrderStatusName($orderStatuses, $statusId) {
        foreach ($orderStatuses as $orderStatus) {
            if (trim(strtoupper($orderStatus['id'])) == trim(strtoupper($statusId))) {
                return $orderStatus['name'];
            }
        }
        return '';
    }

    /**
     * return method payment from virtuemart system by id
     */
    private function getMethodPayment() {
        $cid = vRequest::getvar('cid', NULL, 'array');
        if (is_Array($cid)) {
            $virtuemart_paymentmethod_id = $cid[0];
        } else {
            $virtuemart_paymentmethod_id = $cid;
        }
        if (!($method = $this->getVmPluginMethod($virtuemart_paymentmethod_id))) {
            return NULL; // Another method was selected, do nothing
        }
        return $method;
    }

    //get configurations

    /**
     * return configuration for the plugin
     */
    private function getConfig($key) {
        $method = $this->getMethodPayment();
        return $method != NULL ? $method->$key : NULL;
    }

    public function getEnvironment() {
        return $this->getConfig(self::TRANSBANK_ONEPAY_ENVIRONMENT);
    }

    public function getApiKey() {
        $environment = $this->getEnvironment();
        if ($environment == 'LIVE') {
            return $this->getConfig(self::TRANSBANK_ONEPAY_APIKEY_LIVE);
        } else {
            return $this->getConfig(self::TRANSBANK_ONEPAY_APIKEY_TEST);
        }
    }

    public function getSharedSecret() {
        $environment = $this->getEnvironment();
        if ($environment == 'LIVE') {
            return $this->getConfig(self::TRANSBANK_ONEPAY_SHARED_SECRET_LIVE);
        } else {
            return $this->getConfig(self::TRANSBANK_ONEPAY_SHARED_SECRET_TEST);
        }
    }

    public function getLogoUrl() {
        return $this->getConfig(self::TRANSBANK_ONEPAY_LOGO_URL);
    }

    public function getPluginVersion() {
        return self::PLUGIN_VERSION;
    }

    public function getSoftwareName() {
        return vmVersion::$PRODUCT;
    }

    public function getSoftwareVersion() {
        return vmVersion::$RELEASE;
    }

    public function getLogfileLocation() {
        return VMPATH_ADMINISTRATOR . '/logs/' . $this->getLogFileName() . VmConfig::LOGFILEEXT;
    }

    //Override
    function getLogFileName() {
        return self::LOG_FILENAME;
    }

    //Order statuses
    public function getOrderStatusIdPaid() {
        return $this->getConfig(self::TRANSBANK_ONEPAY_ORDER_STATUS_ID_PAID);
    }

    public function getOrderStatusIdFailed() {
        return $this->getConfig(self::TRANSBANK_ONEPAY_ORDER_STATUS_ID_FAILED);
    }

    public function getOrderStatusIdRejected() {
        return $this->getConfig(self::TRANSBANK_ONEPAY_ORDER_STATUS_ID_REJECTED);
    }

    public function getOrderStatusIdCancelled() {
        return $this->getConfig(self::TRANSBANK_ONEPAY_ORDER_STATUS_ID_CANCELLED);
    }

    public function getStatusConfigured() {
        return $this->getConfig(self::TRANSBANK_ONEPAY_ORDER_STATUS_CONFIGURED);
    }

    /**
     * return onepay pre-configured instance
     */
    private function getOnepayOptions() {

        $apiKey = $this->getApiKey();
        $sharedSecret = $this->getSharedSecret();
        $environment = $this->getEnvironment();

        $environment = $environment != null ? $environment : 'TEST';

        OnepayBase::setApiKey($apiKey);
        OnepayBase::setSharedSecret($sharedSecret);
        OnepayBase::setCurrentIntegrationType($environment);

        $options = new Options($apiKey, $sharedSecret);

        if ($environment == 'LIVE') {
            $options->setAppKey(self::APP_KEY);
        }

        return $options;
    }

    /**
     * create a transaction in onepay
     */
    private function createTransaction($channel, $paymentMethod, $items) {

        if ($channel == null) {
            return $this->failCreate('Falta parámetro channel');
        }

        if ($paymentMethod != self::PLUGIN_CODE) {
            return $this->failCreate('Método de pago no es Transbank Onepay');
        }

        try {

            $options = $this->getOnepayOptions();

            $cart = new ShoppingCart();

            foreach($items as $qItem) {
                $item = new Item($qItem['name'], intval($qItem['quantity']), intval($qItem['price']));
                $cart->add($item);
            }

            $transaction = Transaction::create($cart, $channel, $options);

            $amount = $cart->getTotal();
            $occ = $transaction->getOcc();
            $ott = $transaction->getOtt();
            $externalUniqueNumber = $transaction->getExternalUniqueNumber();
            $issuedAt = $transaction->getIssuedAt();

            $response = array(
                'amount' => $amount,
                'occ' => $occ,
                'ott' => $ott,
                'externalUniqueNumber' => $externalUniqueNumber,
                'issuedAt' => $issuedAt,
                'qrCodeAsBase64' => $transaction->getQrCodeAsBase64()
            );

            $this->logDebug('Transacción creada: ' . json_encode($response));

            return $response;

        } catch (TransbankException $transbankException) {
            return $this->failCreate($transbankException->getMessage());
        }
    }

    private function failCreate($message) {
        $this->logError('Transacción fallida: ' . $message);
        return array('error' => true, 'message' => $message);
    }

    /**
     * commit a transaction in onepay
     */
    private function commitTransaction($status, $occ, $externalUniqueNumber) {

        $options = $this->getOnepayOptions();

        $orderStatusPaid = $this->getOrderStatusIdPaid();
        $orderStatusFailed = $this->getOrderStatusIdFailed();
        $orderStatusRejected = $this->getOrderStatusIdRejected();
        $orderStatusCancelled = $this->getOrderStatusIdCancelled();

        $detail = "<b>Estado:</b> {$status}
                <br><b>OCC:</b> {$occ}
                <br><b>N&uacute;mero de carro:</b> {$externalUniqueNumber}";

        $metadata = array('status' => $status,
                        'occ' => $occ,
                        'externalUniqueNumber' => $externalUniqueNumber);

        if ($status == null || $occ == null || $externalUniqueNumber == null) {
            return $this->failCommit($orderStatusCancelled, 'Parametros inválidos', $detail, $metadata);
        }

        if ($status == 'PRE_AUTHORIZED') {

            try {

                $options = $this->getOnepayOptions();

                $transactionCommitResponse = Transaction::commit($occ, $externalUniqueNumber, $options);

                if ($transactionCommitResponse->getResponseCode() == 'OK') {

                    $amount = $transactionCommitResponse->getAmount();
                    $buyOrder = $transactionCommitResponse->getBuyOrder();
                    $authorizationCode = $transactionCommitResponse->getAuthorizationCode();
                    $description = $transactionCommitResponse->getDescription();
                    $issuedAt = $transactionCommitResponse->getIssuedAt();
                    $dateTransaction = date('Y-m-d H:i:s', $issuedAt);

                    $detail = "<b>Detalles del pago con Onepay:</b>
                                <br><b>Fecha de Transacci&oacute;n:</b> {$dateTransaction}
                                <br><b>OCC:</b> {$occ}
                                <br><b>N&uacute;mero de carro:</b> {$externalUniqueNumber}
                                <br><b>C&oacute;digo de Autorizaci&oacute;n:</b> {$authorizationCode}
                                <br><b>Orden de Compra:</b> {$buyOrder}
                                <br><b>Estado:</b> {$description}
                                <br><b>Monto de la Compra:</b> {$amount}";

                    $installmentsNumber = $transactionCommitResponse->getInstallmentsNumber();

                    if ($installmentsNumber == 1) {

                        $detail = $detail . "<br><b>N&uacute;mero de cuotas:</b> Sin cuotas";

                    } else {

                        $installmentsAmount = $transactionCommitResponse->getInstallmentsAmount();

                        $detail = $detail . "<br><b>N&uacute;mero de cuotas:</b> {$installmentsNumber}
                                            <br><b>Monto cuota:</b> {$installmentsAmount}";
                    }

                    $metadata = array('orderStatusOriginal' => 'paid',
                                    'orderStatus' => $orderStatusPaid,
                                    'amount' => $amount,
                                    'authorizationCode' => $authorizationCode,
                                    'occ' => $occ,
                                    'externalUniqueNumber' => $externalUniqueNumber,
                                    'issuedAt' => $issuedAt);

                    return $this->successCommit($orderStatusPaid, 'Pago exitoso', $detail, $metadata);
                } else {
                    return $this->failCommit($orderStatusFailed, 'Tu pago ha fallado. Vuelve a intentarlo más tarde.', $detail, $metadata);
                }

            } catch (TransbankException $transbankException) {
                return $this->failCommit($orderStatusFailed, $transbankException->getMessage(), $detail, $metadata);
            }

        } else if($status == 'REJECTED') {
            return $this->failCommit($orderStatusRejected, 'Tu pago ha fallado. Pago rechazado.', $detail, $metadata);
        } else {
            return $this->failCommit($orderStatusCancelled, 'Tu pago ha fallado. Compra cancelada.', $detail, $metadata);
        }
    }

    private function successCommit($orderStatusId, $message, $detail, $metadata) {
        $this->logDebug('Transacción confirmada: orderStatusId: ' . $orderStatusId . ', ' . json_encode($metadata));
        return array('success' => true, 'orderStatusId' => $orderStatusId, 'message' => $message, 'detail' => $detail, 'metadata' => $metadata);
    }

    private function failCommit($orderStatusId, $message, $detail, $metadata) {
        $this->logError('Transacción no confirmada: orderStatusId: ' . $orderStatusId . ', ' . json_encode($metadata));
        return array('error' => true, 'orderStatusId' => $orderStatusId, 'message' => $message, 'detail' => $detail, 'metadata' => $metadata);
    }

    /**
     * refund a transaction in onepay
     */
    private function refundTransaction() {
        //not implemented
    }

    /**
     * create the diagnostic pdf
     */
    private function createDiagnosticPdf() {

        $pdf = new DiagnosticPDF($this);

        $pdf->AliasNbPages();
        $pdf->AddPage();
        $pdf->SetFont('Times','',12);

        // Add a title for the section
        $pdf->Cell(60,15,utf8_decode('Server summary'),0,0,'L');
        $pdf->Ln(15);
        // Add php version
        $pdf->addPHPVersion();
        // Add server software
        $pdf->addServerApi();
        // Add addEcommerceInfo and plugin info
        $pdf->addEcommerceInfo();
        // Add merchant info
        $pdf->addMerchantInfo();
        //Add extension info
        $pdf->addExtensionsInfo();
        $pdf->addLogs();

        //Some tricks for FPDF to work with joomla.
        ob_start(); //needed to prevent the error(FPDF error: Some data has already been output, can't send PDF file)
        $pdf->Output();
        die(); //needed to break process render of joomla and download the file pdf correctly
    }

    /**
     * print DEBUG log
     */
    public function logDebug($msg) {
        if (self::LOG_DEBUG_ENABLED) {
            parent::logInfo($msg, 'debug', true);
        }
    }

    /**
     * print INFO log
     */
    public function logInfo($msg) {
        if (self::LOG_INFO_ENABLED) {
            parent::logInfo($msg, 'info', true);
        }
    }

    /**
     * print ERROR log
     */
    public function logError($msg) {
        if (self::LOG_ERROR_ENABLED) {
            parent::logInfo($msg, 'error', true);
        }
    }
}
