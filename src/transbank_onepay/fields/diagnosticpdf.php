<?php
defined('JPATH_BASE') or die();

jimport('joomla.form.formfield');

class JFormFieldDiagnosticPdf extends JFormField {

	/**
	 * Element name
	 *
	 * @access    protected
	 * @var        string
	 */
	var $type = 'DiagnosticPdf';

	protected function getInput() {

        $cid = vRequest::getvar('cid', NULL, 'array');
		if (is_Array($cid)) {
			$virtuemart_paymentmethod_id = $cid[0];
		} else {
			$virtuemart_paymentmethod_id = $cid;
		}

        $html = "<style>
            .diagnosticButton {
                font: bold 14px Arial;
                text-decoration: none;
                background-color: #EEEEEE;
                color: #333333;
                border-top: 1px solid #CCCCCC;
                border-right: 1px solid #333333;
                border-bottom: 1px solid #333333;
                border-left: 1px solid #CCCCCC;
                padding: 10px;
            }
            .diagnosticButton:hover {
                text-decoration: none;
            }
        </style>";

        $url = JURI::root() . "administrator/index.php?option=com_virtuemart&view=paymentmethod&task=edit&cid[]={$virtuemart_paymentmethod_id}&diagnostic_pdf=true";
        $html .= '<a href="' . $url .'" target="_blank" class="diagnosticButton">Generar PDF de Diagnóstico</a>';
		return $html;
	}

}
