<?php

include_once 'CronInit.php';



use PhpOffice\PhpWord\TemplateProcessor;




$conf = new Zend_Config_Ini(APPLICATION_PATH . '/configs/application.ini', APPLICATION_ENV);
$common = new Application_Service_Common();
$participantsDb = new Application_Model_DbTable_Participants();
$dataManagerDb = new Application_Model_DbTable_DataManagers();
$schemesService = new Application_Service_Schemes();
$vlAssayArray = $schemesService->getVlAssay();



try {

	$db = Zend_Db::factory($conf->resources->db);
	Zend_Db_Table::setDefaultAdapter($db);

	$output = array();

	$query = $db->select()->from(array('s' => 'shipment'), array('s.shipment_id', 's.shipment_code', 's.scheme_type', 's.shipment_date',))
		->where("shipment_id IN (3,5)")
		->order("s.scheme_type");


	$shipmentResult = $db->fetchAll($query);
	$shipmentIDArray = array();
	foreach ($shipmentResult as $val) {
		$shipmentIdArray[] = $val['shipment_id'];
		$shipmentCodeArray[$val['scheme_type']][] = $val['shipment_code'];
		$impShipmentId = implode(",", $shipmentIdArray);
	}

	$sQuery = $db->select()->from(array('spm' => 'shipment_participant_map'), array('spm.map_id', 'spm.attributes', 'spm.shipment_test_report_date', 'spm.shipment_id', 'spm.participant_id', 'spm.shipment_score', 'spm.documentation_score', 'spm.final_result'))
		->join(array('s' => 'shipment'), 's.shipment_id=spm.shipment_id', array('shipment_code', 'scheme_type', 'lastdate_response'))
		->join(array('p' => 'participant'), 'p.participant_id=spm.participant_id', array('unique_identifier', 'first_name', 'last_name', 'email', 'city', 'state', 'address', 'institute_name'))
		->where("spm.shipment_test_date!='0000-00-00'")
		->order("scheme_type ASC");

	$sQuery->where('spm.shipment_id IN (' . $impShipmentId . ')');

	//Zend_Debug::dump($shipmentCodeArray);die;
	$shipmentParticipantResult = $db->fetchAll($sQuery);
	//Zend_Debug::dump($shipmentParticipantResult);die;
	$participants = array();
	foreach ($shipmentParticipantResult as $shipment) {

		//$assay = $vlAssayArray[$attribs]
		//Zend_Debug::dump($attribs);die;
		//echo count($participants);

		$participants[$shipment['unique_identifier']]['labName'] = $shipment['first_name'] . " " . $shipment['last_name'];
		$participants[$shipment['unique_identifier']]['city'] = $shipment['city'];
		//$participants[$shipment['unique_identifier']]['finalResult']=$shipment['final_result'];
		$participants[$shipment['unique_identifier']][$shipment['scheme_type']][$shipment['shipment_code']]['score'] = (float) ($shipment['shipment_score'] + $shipment['documentation_score']);
		$participants[$shipment['unique_identifier']][$shipment['scheme_type']][$shipment['shipment_code']]['result'] = $shipment['final_result'];
		$participants[$shipment['unique_identifier']][$shipment['scheme_type']][$shipment['shipment_code']]['lastdate_response'] = $shipment['lastdate_response'];
		$participants[$shipment['unique_identifier']][$shipment['scheme_type']][$shipment['shipment_code']]['shipment_test_report_date'] = $shipment['shipment_test_report_date'];
		$participants[$shipment['unique_identifier']]['attribs'] = json_decode($shipment['attributes'], true);
		//$participants[$shipment['unique_identifier']][$shipment['shipment_code']]=$shipment['shipment_score'];

	}
	//$this->generateAnnualReport($shipmentCodeArray,$participants,$startDate,$endDate);
	//Zend_Debug::dump($participants);die;
	//Zend_Debug::dump($shipmentCodeArray);die;
	foreach ($participants as $participantUID => $arrayVal) {
		foreach ($shipmentCodeArray as $schemeKey => $scheme) {
			if (isset($arrayVal[$schemeKey])) {
				$certificate = true;
                $participated = false;

				// $query = $db->select()->from('scheme_list', array('scheme_name'))->where("scheme_id=?", $schemeKey);
				// $schemeResult = $db->fetchRow($query);

				foreach ($scheme as $va) {
					if (!empty($arrayVal[$schemeKey][$va]['score']) && !empty($arrayVal[$schemeKey][$va]['shipment_test_report_date']) && $arrayVal[$schemeKey][$va]['result'] != 3) {


						if ($arrayVal[$schemeKey][$va]['result'] != 1) {
							$certificate = false;
						}

						if (!empty($arrayVal[$schemeKey][$va]['shipment_test_report_date'])) {
							$reportedDateTimeArray = explode(" ", $arrayVal[$schemeKey][$va]['shipment_test_report_date']);
							if (trim($reportedDateTimeArray[0]) != "" && $reportedDateTimeArray[0] != null && trim($reportedDateTimeArray[0]) != "0000-00-00") {

								$reportedDate = new DateTime($reportedDateTimeArray[0]);
								$lastDate = new DateTime($arrayVal[$schemeKey][$va]['lastdate_response']);
								if ($reportedDate <= $lastDate) {
									$participated = true;
								}
							}
						}
					} else {


						$certificate = false;
					}
				}

				if ($certificate && $participated) {
					$attribs = $arrayVal['attribs'];
					if ($schemeKey == 'dts') {
						$doc = new TemplateProcessor(__DIR__ . "/certificate-template/dts-e.docx");
						$doc->setValue("LABNAME", $arrayVal['labName']);
						$doc->setValue("CITY", $arrayVal['city']);
						$doc->saveAs(__DIR__ . "/certificate/dts/excellence/" . str_replace('/', '_', $participantUID) . "-" . $va . ".docx");
					} else if ($schemeKey == 'eid') {
						$doc = new TemplateProcessor(__DIR__ . "/certificate-template/eid-e.docx");
						$doc->setValue("LABNAME", $arrayVal['labName']);
						$doc->setValue("CITY", $arrayVal['city']);
						//$doc->setValue("DATE","23 December 2018");
						//$doc->saveAs("certificate/2017 Certificate - ".strtoupper($schemeKey)." for Lab ".str_replace('/', '_', $participantUID).".docx");					
						$doc->saveAs(__DIR__ . "/certificate/eid/excellence/" . str_replace('/', '_', $participantUID) . "-" . $va . ".docx");
					} else if ($schemeKey == 'vl') {
						if ($attribs["vl_assay"] == 6) {
							if (isset($attribs["other_assay"])) {
								$assay = $attribs["other_assay"];
							} else {
								$assay = "Other";
							}
						} else {
							$assay = (isset($attribs["vl_assay"]) && isset($vlAssayArray[$attribs["vl_assay"]])) ? $vlAssayArray[$attribs["vl_assay"]] : " Other ";
						}
						$doc = new TemplateProcessor(__DIR__ . "/certificate-template/vl-e.docx");
						$doc->setValue("LABNAME", $arrayVal['labName']);
						$doc->setValue("CITY", $arrayVal['city']);
						$doc->setValue("ASSAYNAME", $assay);
						//$doc->setValue("DATE","23 December 2018");
						//$doc->saveAs("certificate/2017 Certificate - ".strtoupper($schemeKey)." for Lab ".str_replace('/', '_', $participantUID).".docx");	
						$doc->saveAs(__DIR__ . "/certificate/vl/excellence/" . str_replace('/', '_', $participantUID) . "-" . $va . ".docx");
					}
				} else if ($participated) {

					$attribs = $arrayVal['attribs'];

					if ($schemeKey == 'dts') {
						$doc = new TemplateProcessor(__DIR__ . "/certificate-template/dts-p.docx");
						$doc->setValue("LABNAME", $arrayVal['labName']);
						$doc->setValue("CITY", $arrayVal['city']);
						$doc->saveAs(__DIR__ . "/certificate/dts/participation/" . str_replace('/', '_', $participantUID) . "-" . $va . ".docx");
					} else if ($schemeKey == 'eid') {
						$doc = new TemplateProcessor(__DIR__ . "/certificate-template/eid-p.docx");
						$doc->setValue("LABNAME", $arrayVal['labName']);
						$doc->setValue("CITY", $arrayVal['city']);
						//$doc->setValue("DATE","09 January 2018");
						//$doc->saveAs("certificate/2017 Certificate - ".strtoupper($schemeKey)." for Lab ".str_replace('/', '-', $participantUID).".docx");	
						$doc->saveAs(__DIR__ . "/certificate/eid/participation/" . str_replace('/', '_', $participantUID) . "-" . $va . ".docx");
					} else if ($schemeKey == 'vl') {
						if ($attribs["vl_assay"] == 6) {
							if (isset($attribs["other_assay"])) {
								$assay = $attribs["other_assay"];
							} else {
								$assay = "Other";
							}
						} else {
							$assay = (isset($attribs["vl_assay"]) && isset($vlAssayArray[$attribs["vl_assay"]])) ? $vlAssayArray[$attribs["vl_assay"]] : " Other ";
						}

						$doc = new TemplateProcessor(__DIR__ . "/certificate-template/vl-p.docx");
						$doc->setValue("LABNAME", $arrayVal['labName']);
						$doc->setValue("CITY", $arrayVal['city']);
						$doc->setValue("ASSAYNAME", $assay);
						//$doc->setValue("DATE","09 January 2018");
						//$doc->saveAs("certificate/2017 Certificate - ".strtoupper($schemeKey)." for Lab ".str_replace('/', '-', $participantUID).".docx");
						$doc->saveAs(__DIR__ . "/certificate/vl/participation/" . str_replace('/', '_', $participantUID) . "-" . $va . ".docx");
					}
				}
			}
		}
	}
} catch (Exception $e) {
	error_log($e->getMessage());
	error_log($e->getTraceAsString());
	error_log('whoops! Something went wrong in scheduled-jobs/GenerateCertificate.php');
}
