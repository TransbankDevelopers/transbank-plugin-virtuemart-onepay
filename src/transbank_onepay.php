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

if (!class_exists('TransbankSdkOnepay')) {
    require_once(DIR_SYSTEM.'library/TransbankSdkOnepay.php');
}

/**
 * Transbank Onepay Payment plugin implementation
 * @autor vutreras (victor.utreras@continuum.cl)
 */
class plgVmPaymentTransbank_Onepay extends vmPSPlugin {

    const BASE_URL_ACTIONS = 'index.php?option=com_virtuemart&view=pluginresponse&task=pluginresponsereceived';

    function __construct (& $subject, $config) {

		parent::__construct ($subject, $config);
		$this->tableFields = array_keys($this->getTableSQLFields());
		$this->_tablepkey = 'id';
        $this->_tableId = 'id';

        if ($config['name'] == TransbankSdkOnepay::PLUGIN_CODE) {

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
            $varsToPush[TransbankSdkOnepay::TRANSBANK_ONEPAY_ORDER_STATUS_CONFIGURED] = array(implode(',', $orderStatusValues), 'char');

            $this->setConfigParameterable($this->_configTableFieldName, $varsToPush);

            $this->transbankSdkOnepay = $this->getTransbankSdkOnepay();

            //redirect to create the diagnostic pdf
            if (isset($_GET['diagnostic_pdf'])) {
                $this->transbankSdkOnepay->createDiagnosticPdf();
            }

            $this->loadOrShowSdk();
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
            'order_pass' => 'char(64)',
            'order_status' => 'varchar(10)',
			'virtuemart_paymentmethod_id' => 'mediumint(1) UNSIGNED',
            'payment_name' => 'varchar(20)',
            'payment_currency' => 'smallint(1)',
			'payment_order_total' => 'decimal(15,5) NOT NULL',
            'tax_id' => 'smallint(1)',
            'transbank_onepay_metadata' => 'varchar(2000)',
        );
        return $SQLfields;
    }

    /**
     * handle the actions of payments process
     * @Override
     */
    function plgVmOnPaymentResponseReceived(&$html) {

        $session = JFactory::getSession();
        $dataTransbankOnepay = $session->get('dataTransbankOnepay');
        $virtuemart_order_id = $dataTransbankOnepay['virtuemart_order_id'];
        $action = vRequest::getCmd('action');

        $modelOrder = $this->getModelOrder();
        $order = $modelOrder->getOrder($virtuemart_order_id);

        if ($action == 'create') {

            $channel = vRequest::getCmd('channel');
            $items = $session->get('items');

            $response = $this->transbankSdkOnepay->createTransaction($channel, TransbankSdkOnepay::PLUGIN_CODE, $items);

            $dataTransbankOnepay['payment_order_total'] = $response['amount'];
            $session->set('dataTransbankOnepay', $dataTransbankOnepay);

            header('Content-Type: application/json');
            echo(json_encode($response));
            die;

        } else if ($action == 'commit') {

            $status = vRequest::getCmd('status');
            $occ = vRequest::getCmd('occ');
            $externalUniqueNumber = vRequest::getCmd('externalUniqueNumber');

            $response = $this->transbankSdkOnepay->commitTransaction($status, $occ, $externalUniqueNumber);

            $message = $response['message'];
            $detail = $response['detail'];
            $metadata = json_encode($response['metadata']);
            $orderStatusId = $response['orderStatusId'];
            $orderComment = $message . '<hr>' . $detail;
            $orderNotifyToUser = true;

            $order['order_status'] = $orderStatusId;
            $order['customer_notified'] = $orderNotifyToUser;
            $order['comments'] = $orderComment;

            $modelOrder->updateStatusForOneOrder($virtuemart_order_id, $order, true);

            $app = JFactory::getApplication();

            $dataTransbankOnepay['order_status'] = $orderStatusId;
            $dataTransbankOnepay['transbank_onepay_metadata'] = $metadata;

            if (isset($response['error'])) {
                $app->enqueueMessage($message, 'error');
                $app->redirect('index.php?option=com_virtuemart&view=cart');
                die;
            } else {
                $session->set('detail', $detail);
                $this->storePSPluginInternalData($dataTransbankOnepay);
                $app->redirect(self::BASE_URL_ACTIONS . '&action=result&tid=' . microtime());
                die;
            }

        } else if ($action == 'result') {
            VmConfig::loadJLang('com_virtuemart');
            $order_number = $dataTransbankOnepay['order_number'];
            $order_pass = $dataTransbankOnepay['order_pass'];
            $link = JRoute::_('index.php?option=com_virtuemart&view=orders&layout=details&order_number=' . $order_number . '&order_pass=' . $order_pass, false) ;
            $detail = $session->get('detail');
            //COM_VIRTUEMART_ORDER_VIEW_ORDER
            $html = $detail . '<br/><br/><a class="vm-button-correct" href="'.$link.'">'.vmText::_('View your order').'</a>';
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

        $dataTransbankOnepay = array(
            'virtuemart_order_id' => $order['details']['BT']->virtuemart_order_id,
            'order_number' => $order['details']['BT']->order_number,
            'order_pass' => $order['details']['BT']->order_pass,
            'virtuemart_paymentmethod_id' => $order['details']['BT']->virtuemart_paymentmethod_id,
            'payment_name' => TransbankSdkOnepay::PLUGIN_CODE,
            'payment_currency' => $this->_currentMethod->payment_currency,
            'payment_order_total' => 0,
            'tax_id' => $this->_currentMethod->tax_id,
            'transbank_onepay_metadata' => ''
        );

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

        $session = JFactory::getSession();
        $session->set('onepayAction', 'show');
        $session->set('dataTransbankOnepay', $dataTransbankOnepay);
        $session->set('items', $items);

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

    //Override
    function getLogFileName() {
        return TransbankSdkOnepay::LOG_FILENAME;
    }

    //Helpers

    private function getTransbankSdkOnepay() {
        return new TransbankSdkOnepay($this);
    }

    /**
     * load or show js sdk
     */
    private function loadOrShowSdk() {

        $cart = $this->getCurrentCart();

        if (!$this->selectedThisByMethodId($cart->virtuemart_paymentmethod_id)) {
			return NULL; // Another method was selected, do nothing
        }

        $session = JFactory::getSession();
        $onepayAction = $session->get('onepayAction');

        if ($onepayAction == 'show') {

            $session->clear('onepayAction');

            $urlCreate = JRoute::_(self::BASE_URL_ACTIONS . '&action=create&cid=' . $cart->virtuemart_paymentmethod_id);
            $urlCommit = JRoute::_(self::BASE_URL_ACTIONS . '&action=commit&cid=' . $cart->virtuemart_paymentmethod_id);
            $urlLogo = $this->transbankSdkOnepay->getLogoUrl();
            $urlLogo = $urlLogo != NULL ? $urlLogo : '';

            $jsSdk = TransbankSdkOnepay::JS_SDK;
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
    public function getConfig($key) {
        $method = $this->getMethodPayment();
        return $method != NULL ? $method->$key : NULL;
    }

    /**
     * print DEBUG log
     */
    public function logDebug($msg) {
        if (TransbankSdkOnepay::LOG_DEBUG_ENABLED) {
            parent::logInfo($msg, 'debug', true);
        }
    }

    /**
     * print INFO log
     */
    public function logInfo($msg) {
        if (TransbankSdkOnepay::LOG_INFO_ENABLED) {
            parent::logInfo($msg, 'info', true);
        }
    }

    /**
     * print ERROR log
     */
    public function logError($msg) {
        if (TransbankSdkOnepay::LOG_ERROR_ENABLED) {
            parent::logInfo($msg, 'error', true);
        }
    }
}
