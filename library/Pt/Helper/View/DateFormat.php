<?php
class  Pt_Helper_View_DateFormat extends Zend_View_Helper_Abstract
{

	public function dateFormat($dateIn, $returnTime = false)
	{
		if ($dateIn == null || $dateIn == "" || (strpos($dateIn, "00-00-0000") !== false)) {
			return null;
		} else {
			$dateObj = new DateTime($dateIn);
			$formatDate = $this->getDateFormat();
			if ($formatDate == 'dd-M-yy' || empty($formatDate)) {
				$formatDate = 'd-M-Y';
			}
			return $dateObj->format($formatDate);
		}
	}
	public function getDateFormat()
	{

		$file = APPLICATION_PATH . DIRECTORY_SEPARATOR . "configs" . DIRECTORY_SEPARATOR . "config.ini";

		// $config = new Zend_Config_Ini($file, APPLICATION_ENV, array('allowModifications'=>true, 'nestSeparator'=>"#"));
		$config = new Zend_Config_Ini($file, APPLICATION_ENV, array('allowModifications' => false));

		return $config->participant->dateformat;
	}
}
