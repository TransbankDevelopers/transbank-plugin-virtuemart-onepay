<?php
defined('JPATH_BASE') or die();

jimport('joomla.form.formfield');

class JFormFieldOnepayLogo extends JFormField {

	/**
	 * Element name
	 *
	 * @access    protected
	 * @var        string
	 */
	var $type = 'OnepayLogo';

	protected function getInput() {

        $html = "
        <style>
            .onepayLogo {
                background-color: #EEEEEE;
            }
            .diagnosticButton:hover {
                text-decoration: none;
            }
        </style>";

        $url = 'https://web2desa.test.transbank.cl/tbk-ewallet-client-portal/static/images/logo-tarjetas.png';
        $html .= '<img src="' . $url .'" class="onepayLogo" width="150" height="65"/>';
		return $html;
	}

}
