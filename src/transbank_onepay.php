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

require_once(DIR_SYSTEM.'library/transbank-sdk-php/init.php');
require_once(DIR_SYSTEM.'library/DiagnosticPDF.php');

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
    const PLUGIN_CODE = 'transbank_onepay'; //code of plugin for opencart
    const APP_KEY = 'D2044F06-B8AA-4653-8409-2571C2A9E273'; //app key for opencart

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
    /*
    const TRANSBANK_ONEPAY_STATUS = 'transbank_onepay_status';
    const TRANSBANK_ONEPAY_SORT_ORDER = 'transbank_onepay_sort_order';
    const TRANSBANK_ONEPAY_ORDER_STATUS_ID_PAID = 'transbank_onepay_order_status_id_paid';
    const TRANSBANK_ONEPAY_ORDER_STATUS_ID_FAILED = 'transbank_onepay_order_status_id_failed';
    const TRANSBANK_ONEPAY_ORDER_STATUS_ID_REJECTED = 'transbank_onepay_order_status_id_rejected';
    const TRANSBANK_ONEPAY_ORDER_STATUS_ID_CANCELLED = 'transbank_onepay_order_status_id_cancelled';
    const TRANSBANK_ONEPAY_ORDER_STATUS_CONFIGURED = 'transbank_onepay_order_status_configured';
    */

    function __construct (& $subject, $config) {
		parent::__construct ($subject, $config);
		$this->tableFields = array_keys($this->getTableSQLFields());
		$this->_tablepkey = 'id';
		$this->_tableId = 'id';
        $varsToPush = $this->getVarsToPush();
        $this->setConfigParameterable($this->_configTableFieldName, $varsToPush);

        $this->softwareVersion = '3.x';

        $this->logInfo('Log: ' . JFactory::getConfig()->get(self::TRANSBANK_ONEPAY_ENVIRONMENT, 'no definida'));

        if (isset($_GET['diagnostic_pdf'])) {
            $this->logInfo('Creando PDF');
            $this->createDiagnosticPdf();
        }
    }

    function getVmPluginCreateTableSQL() {
        return $this->createTableSQL('Payment Transbank Onepay Table');
    }

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
            self::TRANSBANK_ONEPAY_LOGO_URL => 'varchar(500)'
        );
        return $SQLfields;
    }

    function plgVmDeclarePluginParamsPaymentVM3(&$data) {
		return $this->declarePluginParams('payment', $data);
    }

	function plgVmSetOnTablePluginParamsPayment($name, $id, &$table) {
		return $this->setOnTablePluginParams ($name, $id, $table);
    }

    public function getEnvironment() {
        return 'not implemented';
        //return $this->config->get(self::TRANSBANK_ONEPAY_ENVIRONMENT);
    }

    public function getApiKey() {
        return 'not implemented';
        /*
        $environment = $this->getEnvironment();
        if ($environment == 'LIVE') {
            return $this->config->get(self::TRANSBANK_ONEPAY_APIKEY_LIVE);
        } else {
            return $this->config->get(self::TRANSBANK_ONEPAY_APIKEY_TEST);
        }*/
    }

    public function getSharedSecret() {
        return 'not implemented';
        /*$environment = $this->getEnvironment();
        if ($environment == 'LIVE') {
            return $this->config->get(self::TRANSBANK_ONEPAY_SHARED_SECRET_LIVE);
        } else {
            return $this->config->get(self::TRANSBANK_ONEPAY_SHARED_SECRET_TEST);
        }*/
    }

    public function getLogoUrl() {
        return 'not implemented';
        //return $this->config->get(self::TRANSBANK_ONEPAY_LOGO_URL);
    }

    public function getPluginVersion() {
        return self::PLUGIN_VERSION;
    }

    public function getSoftwareName() {
        return 'Virtuemart';
    }

    public function getSoftwareVersion() {
        return $this->softwareVersion;
    }

    public function getLogfileLocation() {
        return VMPATH_ADMINISTRATOR . '/logs/' . $this->getLogFileName() . VmConfig::LOGFILEEXT;
    }

    //Override
    function getLogFileName() {
        return self::LOG_FILENAME;
    }

    public function getOrderStatusIdPaid() {
        return 'paid';
        //return $this->config->get(self::TRANSBANK_ONEPAY_ORDER_STATUS_ID_PAID);
    }

    public function getOrderStatusIdFailed() {
        return 'failed';
        //return $this->config->get(self::TRANSBANK_ONEPAY_ORDER_STATUS_ID_FAILED);
    }

    public function getOrderStatusIdRejected() {
        return 'rejected';
        //return $this->config->get(self::TRANSBANK_ONEPAY_ORDER_STATUS_ID_REJECTED);
    }

    public function getOrderStatusIdCancelled() {
        return 'cancelled';
        //return $this->config->get(self::TRANSBANK_ONEPAY_ORDER_STATUS_ID_CANCELLED);
    }

    public function getStatusConfigured() {
        return 'not implemented';
        //return $this->config->get(self::TRANSBANK_ONEPAY_ORDER_STATUS_CONFIGURED);
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
    public function createTransaction($channel, $paymentMethod, $items) {

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
    public function commitTransaction($status, $occ, $externalUniqueNumber) {

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
    public function refundTransaction() {
        //not implemented
    }

    /**
     * create the diagnostic pdf
     */
    public function createDiagnosticPdf() {

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

        $pdf->Output();
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
