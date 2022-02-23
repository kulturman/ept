<?php

include_once 'CronInit.php';

$cliOptions = getopt("s:c:");
$shipmentsToGenerate = $cliOptions['s'];
$certificateName = (!empty($cliOptions['c']) ? $cliOptions['c'] : date('Y'));

if (is_array($shipmentsToGenerate)) {
	$shipmentsToGenerate = implode(",", $shipmentsToGenerate);
}


if (empty($shipmentsToGenerate)) {
	echo ("Please specify the shipment ids with the -s flag");
	exit();
}


use PhpOffice\PhpWord\TemplateProcessor;

$certificatePaths = array();
$certificatePaths[] = $excellenceCertPath = TEMP_UPLOAD_PATH . "/certificates/$certificateName/excellence";
$certificatePaths[] = $participationCertPath = TEMP_UPLOAD_PATH . "/certificates/$certificateName/participation";

if (!file_exists($excellenceCertPath)) {
	mkdir($excellenceCertPath, 0777, true);
}
if (!file_exists($participationCertPath)) {
	mkdir($participationCertPath, 0777, true);
}



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
		->where("shipment_id IN (" . $shipmentsToGenerate . ")")
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
		// ->where("spm.final_result = 1 OR spm.final_result = 2")
		// ->where("spm.is_excluded NOT LIKE 'yes'")
		->order("unique_identifier ASC")
		->order("scheme_type ASC");

	$sQuery->where('spm.shipment_id IN (' . $impShipmentId . ')');

	//Zend_Debug::dump($shipmentCodeArray);die;
	$shipmentParticipantResult = $db->fetchAll($sQuery);
	//Zend_Debug::dump($shipmentParticipantResult);die;
	$participants = array();

	foreach ($shipmentParticipantResult as $shipment) {

		//$assay = $vlAssayArray[$attribs]
		//Zend_Debug::dump($shipment);die;
		//echo count($participants);
		$participantName['first_name'] = utf8_encode($shipment['first_name']);
		$participantName['last_name'] = utf8_encode($shipment['last_name']);

		$participants[$shipment['unique_identifier']]['labName'] = implode(" ", $participantName);
		$participants[$shipment['unique_identifier']]['city'] = $shipment['city'];
		//$participants[$shipment['unique_identifier']]['finalResult']=$shipment['final_result'];
		$participants[$shipment['unique_identifier']][$shipment['scheme_type']][$shipment['shipment_code']]['score'] = (float) ($shipment['shipment_score'] + $shipment['documentation_score']);
		$participants[$shipment['unique_identifier']][$shipment['scheme_type']][$shipment['shipment_code']]['result'] = $shipment['final_result'];
		$participants[$shipment['unique_identifier']][$shipment['scheme_type']][$shipment['shipment_code']]['lastdate_response'] = $shipment['lastdate_response'];
		$participants[$shipment['unique_identifier']][$shipment['scheme_type']][$shipment['shipment_code']]['shipment_test_report_date'] = $shipment['shipment_test_report_date'];
		$participants[$shipment['unique_identifier']]['attribs'] = json_decode($shipment['attributes'], true);
		//$participants[$shipment['unique_identifier']][$shipment['shipment_code']]=$shipment['shipment_score'];

	}

	//Zend_Debug::dump($participants);die;

	foreach ($participants as $participantUID => $arrayVal) {
		foreach ($shipmentCodeArray as $shipmentType => $shipmentsList) {
			if (isset($arrayVal[$shipmentType])) {
				$certificate = true;
				$participated = false;

				foreach ($shipmentsList as $shipmentCode) {
					$assayName = "";
					if ($shipmentType == 'vl' && !empty($arrayVal[$shipmentType][$shipmentCode]['attributes']['vl_assay'])) {
						$assayName = $vlAssayArray[$arrayVal[$shipmentType][$shipmentCode]['attributes']['vl_assay']];
					} else if ($shipmentType == 'eid' && !empty($arrayVal[$shipmentType][$shipmentCode]['attributes']['extraction_assay'])) {
						$assayName = $eidAssayArray[$arrayVal[$shipmentType][$shipmentCode]['attributes']['extraction_assay']];
					}
					$firstSheetRow[] = $assayName;
					if (!empty($arrayVal[$shipmentType][$shipmentCode]['result']) && $arrayVal[$shipmentType][$shipmentCode]['result'] != 3) {

						$firstSheetRow[] = $arrayVal[$shipmentType][$shipmentCode]['score'];

						if ($arrayVal[$shipmentType][$shipmentCode]['result'] != 1) {
							$certificate = false;
						}
					} else {
						if (!empty($arrayVal[$shipmentType][$shipmentCode]['result']) && $arrayVal[$shipmentType][$shipmentCode]['result'] == 3) {
							$firstSheetRow[] = 'Excluded';
						} else {
							$firstSheetRow[] = '-';
						}
						//$participated = false;
						$certificate = false;
					}



					if (!empty($arrayVal[$shipmentType][$shipmentCode]['shipment_test_report_date'])) {
						$reportedDateTimeArray = explode(" ", $arrayVal[$shipmentType][$shipmentCode]['shipment_test_report_date']);
						if (trim($reportedDateTimeArray[0]) != "" && $reportedDateTimeArray[0] != null && trim($reportedDateTimeArray[0]) != "0000-00-00" && trim($reportedDateTimeArray[0]) != "1970-01-01") {

							$reportedDate = new DateTime($reportedDateTimeArray[0]);
							$lastDate = new DateTime($arrayVal[$shipmentType][$shipmentCode]['lastdate_response']);
							if ($reportedDate <= $lastDate) {
								$participated = true;
							}
						}
					}
				}

				if ($certificate && $participated) {
					$attribs = $arrayVal['attribs'];

					//Zend_Debug::dump($excellenceCertPath);die;
					//Zend_Debug::dump($participationCertPath);die;

					if ($shipmentType == 'dts') {
						if (!file_exists(UPLOAD_PATH . "/certificate-templates/dts-e.docx")) continue;
						$doc = new TemplateProcessor(UPLOAD_PATH . "/certificate-templates/dts-e.docx");
						$doc->setValue("LABNAME", $arrayVal['labName']);
						$doc->setValue("CITY", $arrayVal['city']);
					} else if ($shipmentType == 'eid') {
						if (!file_exists(UPLOAD_PATH . "/certificate-templates/eid-e.docx")) continue;
						$doc = new TemplateProcessor(UPLOAD_PATH . "/certificate-templates/eid-e.docx");
						$doc->setValue("LABNAME", $arrayVal['labName']);
						$doc->setValue("CITY", $arrayVal['city']);
					} else if ($shipmentType == 'vl') {
						if (!file_exists(UPLOAD_PATH . "/certificate-templates/vl-e.docx")) continue;
						if ($attribs["vl_assay"] == 6) {
							if (isset($attribs["other_assay"])) {
								$assay = $attribs["other_assay"];
							} else {
								$assay = "Other";
							}
						} else {
							$assay = (isset($attribs["vl_assay"]) && isset($vlAssayArray[$attribs["vl_assay"]])) ? $vlAssayArray[$attribs["vl_assay"]] : " Other ";
						}
						$doc = new TemplateProcessor(UPLOAD_PATH . "/certificate-templates/vl-e.docx");
						$doc->setValue("LABNAME", $arrayVal['labName']);
						$doc->setValue("CITY", $arrayVal['city']);
						$doc->setValue("ASSAYNAME", $assay);
						//$doc->setValue("DATE","23 December 2018");
					}
					$doc->saveAs($excellenceCertPath . DIRECTORY_SEPARATOR . str_replace('/', '_', $participantUID) . "-" . strtoupper($shipmentType) . "-" . $certificateName . ".docx");
				} else if ($participated) {

					$attribs = $arrayVal['attribs'];

					if ($shipmentType == 'dts') {
						if (!file_exists(UPLOAD_PATH . "/certificate-templates/dts-p.docx")) continue;
						$doc = new TemplateProcessor(UPLOAD_PATH . "/certificate-templates/dts-p.docx");
						$doc->setValue("LABNAME", $arrayVal['labName']);
						$doc->setValue("CITY", $arrayVal['city']);
					} else if ($shipmentType == 'eid') {
						if (!file_exists(UPLOAD_PATH . "/certificate-templates/eid-p.docx")) continue;
						$doc = new TemplateProcessor(UPLOAD_PATH . "/certificate-templates/eid-p.docx");
						$doc->setValue("LABNAME", $arrayVal['labName']);
						$doc->setValue("CITY", $arrayVal['city']);
						//$doc->setValue("DATE","09 January 2018");

					} else if ($shipmentType == 'vl') {
						if (!file_exists(UPLOAD_PATH . "/certificate-templates/vl-p.docx")) continue;
						if ($attribs["vl_assay"] == 6) {
							if (isset($attribs["other_assay"])) {
								$assay = $attribs["other_assay"];
							} else {
								$assay = "Other";
							}
						} else {
							$assay = (isset($attribs["vl_assay"]) && isset($vlAssayArray[$attribs["vl_assay"]])) ? $vlAssayArray[$attribs["vl_assay"]] : " Other ";
						}

						$doc = new TemplateProcessor(UPLOAD_PATH . "/certificate-templates/vl-p.docx");
						$doc->setValue("LABNAME", $arrayVal['labName']);
						$doc->setValue("CITY", $arrayVal['city']);
						$doc->setValue("ASSAYNAME", $assay);
					}
					$doc->saveAs($participationCertPath . DIRECTORY_SEPARATOR . str_replace('/', '_', $participantUID) . "-" . strtoupper($shipmentType) . "-" . $certificateName . ".docx");
				}
			}
		}
	}
	if (!empty($certificatePaths)) {
		$certificatePaths = array_unique($certificatePaths);
		Zend_Debug::dump($certificatePaths);
		foreach ($certificatePaths as $certPath) {
			echo ("cd $certPath && /usr/bin/libreoffice --headless --convert-to pdf *.docx --outdir ./ >/dev/null 2>&1 &" . PHP_EOL);
		}
	}
} catch (Exception $e) {
	error_log($e->getMessage());
	error_log($e->getTraceAsString());
	error_log('whoops! Something went wrong in scheduled-jobs/GenerateCertificate.php');
}