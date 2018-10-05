<?php
/**
 * Plugin for Transbank Onepay
 * @autor vutreras (victor.utreras@continuum.cl)
 */

defined('_JEXEC') or die('Restricted access');

if (!class_exists('vmPSPlugin')) {
    require(JPATH_VM_PLUGINS . DS . 'vmpsplugin.php');
}

class plgVmPaymentTransbank_onepay extends vmPSPlugin {

    function __construct (& $subject, $config) {

        die('Plugin stuff'. json_encode($config));

		parent::__construct ($subject, $config);
        $this->_loggable = TRUE;
		$this->tableFields = array_keys($this->getTableSQLFields());
		$this->_tablepkey = 'id';
		$this->_tableId = 'id';
        //$varsToPush = $this->getVarsToPush();
        $varsToPush = array('transbank_onepay_environment' => array('', 'char'),
		                    'transbank_onepay_apikey_test' => array('', 'char'),
		                    'transbank_onepay_shared_secret_test' => array('', 'char'),
		                    'transbank_onepay_apikey_live' => array('', 'char'),
		                    'transbank_onepay_shared_secret_live' => array('', 'char'),
		                    'transbank_onepay_logo_url' => array('', 'char'));
		$this->setConfigParameterable($this->_configTableFieldName, $varsToPush);
    }

    function getVmPluginCreateTableSQL() {
        return $this->createTableSQL('Payment Transbank Onepay Table');
    }

    function getTableSQLFields() {
        return array(
            'id' => 'int(1) UNSIGNED NOT NULL AUTO_INCREMENT',
            'virtuemart_order_id' => 'int(1) UNSIGNED',
            'order_number' => 'char(64)',
            'virtuemart_paymentmethod_id' => 'mediumint(1) UNSIGNED',
            'payment_name' => 'varchar(5000)',
            'payment_order_total' => 'decimal(15,5) NOT NULL DEFAULT \'0.00000\'',
            'payment_currency' => 'char(3)',
            'email_currency' => 'char(3)',
            'cost_per_transaction' => 'decimal(10,2)',
            'cost_percent_total' => 'decimal(10,2)',
            'tax_id' => 'smallint(1)',
            'transbank_onepay_environment' => 'varchar(10)',
        );
    }

}
