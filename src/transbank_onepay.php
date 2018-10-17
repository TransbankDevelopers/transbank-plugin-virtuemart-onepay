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

    //url of the js sdk
    const JS_SDK = 'https://cdn.rawgit.com/TransbankDevelopers/transbank-sdk-js-onepay/v1.5.5/lib/merchant.onepay.min.js';

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

    const BASE_URL_ACTIONS = 'index.php?option=com_virtuemart&view=pluginresponse&task=pluginresponsereceived';

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

            $this->loadOrShowSdk($this->getCurrentCart());
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
     * handle the actions of payments process
     * @Override
     */
    function plgVmOnPaymentResponseReceived(&$html) {

        $session = JFactory::getSession();
        $orderId = $session->get('orderId');
        $orderNumber = $session->get('orderNumber');
        $orderPass = $session->get('orderPass');
        $action = vRequest::getCmd('action');

        $modelOrder = $this->getModelOrder();
        $order = $modelOrder->getOrder($orderId);

        if ($action == 'create') {

            $channel = vRequest::getCmd('channel');
            $items = $session->get('items');

            $response = $this->createTransaction($channel, self::PLUGIN_CODE, $items);

            header('Content-Type: application/json');
            echo(json_encode($response));
            die;

        } else if ($action == 'commit') {

            $status = vRequest::getCmd('status');
            $occ = vRequest::getCmd('occ');
            $externalUniqueNumber = vRequest::getCmd('externalUniqueNumber');

            $response = $this->commitTransaction($status, $occ, $externalUniqueNumber);

            $message = $response['message'];
            $detail = $response['detail'];
            $metadata = json_encode($response['metadata']);
            $orderStatusId = $response['orderStatusId'];
            $orderComment = $message . '<hr>' . $detail;
            $orderNotifyToUser = true;

            $order['order_status'] = $orderStatusId;
            $order['customer_notified'] = $orderNotifyToUser;
            $order['comments'] = $orderComment;

            $this->logInfo('order id: ' . $orderId . ', status: ' . $orderStatusId);
            $modelOrder->updateStatusForOneOrder($orderId, $order, true);

            $app = JFactory::getApplication();

            if (isset($response['error'])) {
                $app->enqueueMessage($message, 'error');
                $app->redirect('index.php?option=com_virtuemart&view=cart');
                die;
            } else {
                $session->set('detail', $detail);
                $app->redirect(self::BASE_URL_ACTIONS . '&action=result&tid=' . microtime());
                die;
            }

        } else if ($action == 'result') {
            VmConfig::loadConfig();
            VmConfig::loadJLang('com_virtuemart');
            $link = JRoute::_('index.php?option=com_virtuemart&view=orders&layout=details&order_number=' . $orderNumber . '&order_pass=' . $orderPass, false) ;
            $detail = $session->get('detail');
            $html = $detail . '<br/><br/><a class="vm-button-correct" href="'.$link.'">'.vmText::_('COM_VIRTUEMART_ORDER_VIEW_ORDER').'</a>';
            $this->getCurrentCart()->emptyCart();
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

        $session = JFactory::getSession();
        $onepayAction = $session->get('onepayAction');
        $orderId = $order['details']['BT']->virtuemart_order_id;
        $orderNumber = $order['details']['BT']->order_number;
        $orderPass = $order['details']['BT']->order_pass;
        $items = array();

        foreach ($cart->products as $pkey => $product) {
            $items[] = array(
                'name' => htmlspecialchars($product->product_name),
                'quantity' => $product->quantity,
                'price' => !empty($product->prices['basePriceWithTax']) ? $product->prices['basePriceWithTax'] :
                                                                            $product->prices['basePriceVariant']
            );
        }

        $shippingAmount = 0;

        if (isset($cart->cartPrices) && isset($cart->cartPrices['salesPriceShipment'])) {
            $shippingAmount = $cart->cartPrices['salesPriceShipment'];
        }

        if ($shippingAmount != 0) {
            $items[] = array(
                'name' => 'Costo por envio',
                'price' => $shippingAmount,
                'quantity' => 1
            );
        }

        $session->set('onepayAction', 'show');
        $session->set('items', $items);
        $session->set('orderId', $orderId);
        $session->set('orderNumber', $orderNumber);
        $session->set('orderPass', $orderPass);
        $app = JFactory::getApplication();
        $app->redirect('index.php?option=com_virtuemart&view=cart');
        die;
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
		return $this->onSelectedCalculatePrice($cart, $cart_prices, $cart_prices_name);
	}

    /**
	 * @param $virtuemart_paymentmethod_id
	 * @param $paymentCurrencyId
	 * @return bool|null
     * @Override
	 */
    function plgVmgetPaymentCurrency($virtuemart_paymentmethod_id, &$paymentCurrencyId) {
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

    //Helpers

    /**
     * load or show js sdk
     */
    private function loadOrShowSdk(VirtueMartCart $cart) {

        if (!$this->selectedThisByMethodId($cart->virtuemart_paymentmethod_id)) {
			return NULL; // Another method was selected, do nothing
        }

        $session = JFactory::getSession();
        $onepayAction = $session->get('onepayAction');

        if ($onepayAction == 'show') {

            $session->clear('onepayAction');

            $urlCreate = self::BASE_URL_ACTIONS . '&action=create&cid=' . $cart->virtuemart_paymentmethod_id;
            $urlCommit = self::BASE_URL_ACTIONS . '&action=commit&cid=' . $cart->virtuemart_paymentmethod_id;
            $urlLogo = $this->getLogoUrl();
            $urlLogo = $urlLogo != NULL ? $urlLogo : '';

            $this->logInfo('Load JS-sdk');
            $jsSdk = self::JS_SDK;
            $jsScript =
            "<script type='text/javascript'>
                (function (o, n, e, p, a, y) {
                    var s = n.createElement(p);
                    s.type = 'text/javascript';
                    s.src = e;
                    s.onload = s.onreadystatechange = function () {
                        if (!o && (!s.readyState
                            || s.readyState === 'loaded')) {
                            y();
                        }
                    };
                    var t = n.getElementsByTagName('script')[0];
                    p = t.parentNode;
                    p.insertBefore(s, t);
                })(false, document, '{$jsSdk}',
                'script',window, function () {

                    var options = {
                        endpoint: '{$urlCreate}',
                        commerceLogo: '{$urlLogo}',
                        callbackUrl: '{$urlCommit}'
                    };
                    Onepay.checkout(options);
                });
            </script>";
            echo($jsScript);
        }
    }

    /**
     * return the current cart
     */
    private function getCurrentCart() {
        if (!class_exists('VirtueMartCart')) {
            require(JPATH_VM_SITE . DS . 'helpers' . DS . 'cart.php');
        }
        return VirtueMartCart::getCart();
    }

    /**
     * return the model orders
     */
    private function getModelOrder() {
        if (!class_exists('VirtueMartModelOrders')) {
            require(JPATH_VM_ADMINISTRATOR . DS . 'models' . DS . 'orders.php');
        }
        return new VirtueMartModelOrders();
    }

    /**
     * return array os order status with id and name fields
     *
     *  order_status_code, order_status_name
     *  'P', 'COM_VIRTUEMART_ORDER_STATUS_PENDING'
     *  'U', 'COM_VIRTUEMART_ORDER_STATUS_CONFIRMED_BY_SHOPPER'
     *  'C', 'COM_VIRTUEMART_ORDER_STATUS_CONFIRMED'
     *  'X', 'COM_VIRTUEMART_ORDER_STATUS_CANCELLED'
     *  'R', 'COM_VIRTUEMART_ORDER_STATUS_REFUNDED'
     *  'S', 'COM_VIRTUEMART_ORDER_STATUS_SHIPPED'
     *  'F', 'COM_VIRTUEMART_ORDER_STATUS_COMPLETED'
     *  'D', 'COM_VIRTUEMART_ORDER_STATUS_DENIED'
     */
    private function getOrderStatuses() {
        $orderStatuses = array();
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
            $orderStatuses[] = array('id' => $id, 'name' => $name);
        }
        return $orderStatuses;
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
