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

    //Override
    function getVmPluginCreateTableSQL() {
        return $this->createTableSQL('Payment Transbank Onepay Table');
    }

    //Override
    function getTableSQLFields() {
        $SQLfields = array(
            'id'                          => 'int(1) UNSIGNED NOT NULL AUTO_INCREMENT',
			'virtuemart_order_id'         => 'int(1) UNSIGNED',
			'order_number'                => 'char(64)',
			'virtuemart_paymentmethod_id' => 'mediumint(1) UNSIGNED',
			'payment_name'                => 'varchar(5000)',
			'payment_order_total'         => 'decimal(15,5) NOT NULL DEFAULT \'0.00000\'',
			'payment_currency'            => 'char(3)',
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

    //Override
    function plgVmDeclarePluginParamsPaymentVM3(&$data) {
        $ret = $this->declarePluginParams('payment', $data);
        if ($ret == 1) {
            $this->logInfo('Configuracion guardada correctamente');
        }
        return $ret;
    }

    //Override
	function plgVmSetOnTablePluginParamsPayment($name, $id, &$table) {
		return $this->setOnTablePluginParams($name, $id, $table);
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
            parent::logInfo('DEBUG: ' . $msg, 'message', true);
        }
    }

    /**
     * print INFO log
     */
    public function logInfo($msg) {
        if (self::LOG_INFO_ENABLED) {
            parent::logInfo('INFO: ' . $msg, 'message', true);
        }
    }

    /**
     * print ERROR log
     */
    public function logError($msg) {
        if (self::LOG_ERROR_ENABLED) {
            parent::logInfo('ERROR: ' . $msg, 'message', true);
        }
    }
}
