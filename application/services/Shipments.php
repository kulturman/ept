<?php

class Application_Service_Shipments
{

    public function getAllShipments($parameters)
    {
        /* Array of database columns which should be read and sent back to DataTables. Use a space where
         * you want to insert a non-database field (for example a counter or static image)
         */

        //$aColumns = array('project_name','project_code','e.employee_name','client_name','architect_name','project_value','building_type_name','DATE_FORMAT(p.project_date,"%d-%b-%Y")','DATE_FORMAT(p.deadline,"%d-%b-%Y")','refered_by','emp.employee_name');
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();

        $aColumns = array("sl.scheme_name", "shipment_code", 'distribution_code', "DATE_FORMAT(distribution_date,'%d-%b-%Y')", 'number_of_samples', 's.status');
        $orderColumns = array("sl.scheme_name", "shipment_code", 'distribution_code', 'distribution_date', 'number_of_samples', 's.status');


        /* Indexed column (used for fast and accurate table cardinality) */
        $sIndexColumn = "shipment_id";


        /*
         * Paging
         */
        $sLimit = "";
        if (isset($parameters['iDisplayStart']) && $parameters['iDisplayLength'] != '-1') {
            $sOffset = $parameters['iDisplayStart'];
            $sLimit = $parameters['iDisplayLength'];
        }

        /*
         * Ordering
         */



        $sOrder = "";
        if (isset($parameters['iSortCol_0'])) {
            $sOrder = "";
            for ($i = 0; $i < intval($parameters['iSortingCols']); $i++) {
                if ($parameters['bSortable_' . intval($parameters['iSortCol_' . $i])] == "true") {
                    $sOrder .= $orderColumns[intval($parameters['iSortCol_' . $i])] . "
						" . ($parameters['sSortDir_' . $i]) . ", ";
                }
            }

            $sOrder = substr_replace($sOrder, "", -2);
        }
        /*
         * Filtering
         * NOTE this does not match the built-in DataTables filtering which does it
         * word by word on any field. It's possible to do here, but concerned about efficiency
         * on very large tables, and MySQL's regex functionality is very limited
         */

        $sWhere = "";
        if (isset($parameters['sSearch']) && $parameters['sSearch'] != "") {
            $searchArray = explode(" ", $parameters['sSearch']);
            $sWhereSub = "";
            foreach ($searchArray as $search) {
                if ($sWhereSub == "") {
                    $sWhereSub .= "(";
                } else {
                    $sWhereSub .= " AND (";
                }
                $colSize = count($aColumns);

                for ($i = 0; $i < $colSize; $i++) {
                    if ($i < $colSize - 1) {
                        $sWhereSub .= $aColumns[$i] . " LIKE '%" . ($search) . "%' OR ";
                    } else {
                        $sWhereSub .= $aColumns[$i] . " LIKE '%" . ($search) . "%' ";
                    }
                }
                $sWhereSub .= ")";
            }
            $sWhere .= $sWhereSub;
        }

        /* Individual column filtering */
        for ($i = 0; $i < count($aColumns); $i++) {
            if (isset($parameters['bSearchable_' . $i]) && $parameters['bSearchable_' . $i] == "true" && $parameters['sSearch_' . $i] != '') {
                if ($sWhere == "") {
                    $sWhere .= $aColumns[$i] . " LIKE '%" . ($parameters['sSearch_' . $i]) . "%' ";
                } else {
                    $sWhere .= " AND " . $aColumns[$i] . " LIKE '%" . ($parameters['sSearch_' . $i]) . "%' ";
                }
            }
        }

        /*
         * SQL queries
         * Get data to display
         */

        $sQuery = $db->select()->from(array('s' => 'shipment'))
            ->join(array('d' => 'distributions'), 'd.distribution_id = s.distribution_id', array('distribution_code', 'distribution_date'))
            ->joinLeft(array('spm' => 'shipment_participant_map'), 's.shipment_id = spm.shipment_id', array('total_participants' => new Zend_Db_Expr('count(map_id)'), 'reported_count' =>  new Zend_Db_Expr("SUM(shipment_test_date not like  '0000-00-00' OR is_pt_test_not_performed ='yes')"), 'last_new_shipment_mailed_on', 'new_shipment_mail_count'))
            ->join(array('sl' => 'scheme_list'), 'sl.scheme_id=s.scheme_type', array('SCHEME' => 'sl.scheme_name'))
            ->group('s.shipment_id');

        if (isset($parameters['scheme']) && $parameters['scheme'] != "") {
            $sQuery = $sQuery->where("s.scheme_type = ?", $parameters['scheme']);
        }

        if (isset($parameters['distribution']) && $parameters['distribution'] != "" && $parameters['distribution'] != 0) {
            $sQuery = $sQuery->where("s.distribution_id = ?", $parameters['distribution']);
        }

        if (isset($sWhere) && $sWhere != "") {
            $sQuery = $sQuery->where($sWhere);
        }

        if (isset($sOrder) && $sOrder != "") {
            $sQuery = $sQuery->order($sOrder);
        }

        if (isset($sLimit) && isset($sOffset)) {
            $sQuery = $sQuery->limit($sLimit, $sOffset);
        }
        // die($sQuery);

        $rResult = $db->fetchAll($sQuery);

        /* Data set length after filtering */
        $sQuery = $sQuery->reset(Zend_Db_Select::LIMIT_COUNT);
        $sQuery = $sQuery->reset(Zend_Db_Select::LIMIT_OFFSET);
        $aResultFilterTotal = $db->fetchAll($sQuery);
        $iFilteredTotal = count($aResultFilterTotal);

        /* Total data set length */
        $sQuery = $db->select()->from('shipment', new Zend_Db_Expr("COUNT('shipment_id')"));
        $aResultTotal = $db->fetchCol($sQuery);
        $iTotal = $aResultTotal[0];

        /*
         * Output
         */
        $output = array(
            "sEcho" => intval($parameters['sEcho']),
            "iTotalRecords" => $iTotal,
            "iTotalDisplayRecords" => $iFilteredTotal,
            "aaData" => array()
        );

        foreach ($rResult as $aRow) {
            $mailedOn = '';
            $row = array();
            if ($aRow['status'] == 'ready') {
                $btn = "btn-success";
            } else if ($aRow['status'] == 'pending') {
                $btn = "btn-danger";
            } else {
                $btn = "btn-primary";
            }
            if ($aRow['last_new_shipment_mailed_on'] != '') {
                $mailedOn =  explode(' ', $aRow['last_new_shipment_mailed_on']);
                $mailedOn =  Pt_Commons_General::humanDateFormat($mailedOn[0]) . ' ' . $mailedOn[1];
            }
            if ($aRow['status'] != 'finalized' && $aRow['status'] != 'ready' && $aRow['status'] != 'pending') {
                $responseSwitch = "<select onchange='responseSwitch(this.value," . $aRow['shipment_id'] . ")'>";
                $responseSwitch .= "<option value='on'" . (isset($aRow['response_switch']) && $aRow['response_switch'] == "on" ? " selected='selected' " : "") . ">On</option>";
                $responseSwitch .= "<option value='off'" . (isset($aRow['response_switch']) && $aRow['response_switch'] == "off" ? " selected='selected' " : "") . ">Off</option>";
                $responseSwitch .= "</select>";
            } else {
                $responseSwitch = '-';
            }

            //$row[] = $aRow['shipment_code'];
            $row[] = $aRow['shipment_code'];
            $row[] = $aRow['SCHEME'];
            $row[] = $aRow['distribution_code'];
            $row[] = Pt_Commons_General::humanDateFormat($aRow['distribution_date']);
            $row[] = Pt_Commons_General::humanDateFormat($aRow['lastdate_response']);
            $row[] = $aRow['number_of_samples'];
            $row[] = $aRow['total_participants'];
            $row[] = $responseSwitch;
            $row[] = ucfirst($aRow['status']);
            //             $row[] = $mailedOn;
            //             $row[] = $aRow['new_shipment_mail_count'];
            $edit = '';
            $enrolled = '';
            $delete = '';
            $announcementMail = '';
            $manageEnroll = '';

            if ($aRow['status'] != 'finalized') {
                $edit = '<br>&nbsp;<a class="btn btn-primary btn-xs" href="/admin/shipment/edit/sid/' . base64_encode($aRow['shipment_id']) . '"><span><i class="icon-edit"></i> Edit</span></a>';
            } else {
                $edit = '<br>&nbsp;<a class="btn btn-danger btn-xs disabled" href="javascript:void(0);"><span><i class="icon-check"></i> Finalized</span></a>';
            }

            if ($aRow['status'] != 'shipped' && $aRow['status'] != 'evaluated' && $aRow['status'] != 'finalized') {
                $enrolled = '<br>&nbsp;<a class="btn ' . $btn . ' btn-xs" href="/admin/shipment/ship-it/sid/' . base64_encode($aRow['shipment_id']) . '"><span><i class="icon-user"></i> Enroll</span></a>';
            } else if ($aRow['status'] == 'shipped') {
                $enrolled = '<br>&nbsp;<a class="btn btn-primary btn-xs disabled" href="javascript:void(0);"><span><i class="icon-ambulance"></i> Shipped</span></a>';
                $announcementMail = '<br>&nbsp;<a class="btn btn-warning btn-xs" href="javascript:void(0);" onclick="mailShipment(\'' . base64_encode($aRow['shipment_id']) . '\')"><span><i class="icon-bullhorn"></i> New Shipment Mail</span></a>';
            }
            if ($aRow['status'] == 'shipped' || $aRow['status'] == 'evaluated') {
                $manageEnroll = '<br>&nbsp;<a class="btn btn-info btn-xs" href="/admin/shipment/manage-enroll/sid/' . base64_encode($aRow['shipment_id']) . '/sctype/' . base64_encode($aRow['scheme_type']) . '"><span><i class="icon-gear"></i> Enrollment </span></a>';
            }

            if ($aRow['status'] != 'finalized' && ($aRow['reported_count'] == 0)) {
                $delete = '<br>&nbsp;<a class="btn btn-primary btn-xs" href="javascript:void(0);" onclick="removeShipment(\'' . base64_encode($aRow['shipment_id']) . '\')"><span><i class="icon-remove"></i> Delete</span></a>';
            }

            //           if ($aRow['status'] != null && $aRow['status'] != "" && $aRow['status'] != 'shipped' && $aRow['status'] != 'evaluated' && $aRow['status'] != 'closed' && $aRow['status'] != 'finalized') {
            //                $row[] = '<a class="btn ' . $btn . ' btn-xs" href="/admin/shipment/ship-it/sid/' . base64_encode($aRow['shipment_id']) . '"><span><i class="icon-user"></i> Enroll</span></a>'
            //                        . $edit
            //                        . '&nbsp;<a class="btn btn-primary btn-xs" href="javascript:void(0);" onclick="removeShipment(\'' . base64_encode($aRow['shipment_id']) . '\')"><span><i class="icon-remove"></i> Delete</span></a>';
            //            } else if ($aRow['status'] != null && $aRow['status'] != "" && $aRow['status'] == 'shipped' && $aRow['status'] != 'closed') {
            //                $row[] = $edit;
            //            } else {
            //                $row[] = $edit.'<a class="btn btn-primary btn-xs disabled" href="javascript:void(0);"><span><i class="icon-ambulance"></i> Shipped</span></a>';
            //            }

            $row[] = $edit . $enrolled . $delete . $announcementMail . $manageEnroll;
            $output['aaData'][] = $row;
        }

        echo json_encode($output);
    }

    public function mandatoryFieldsCheck($params, $mandatoryFields)
    {

        $errors = array();
        if (isset($params['isPtTestNotPerformed']) && $params['isPtTestNotPerformed'] == 'yes') {
            return $errors;
        }


        // let us only keep the mandatory params
        $mandatoryParams = array_intersect_key($params, array_flip($mandatoryFields));
        foreach ($mandatoryParams as $param => $val) {
            if (empty(trim($val))) {
                $errors[] = $param;
            }
        }

        return $errors;
    }

    public function updateEidResults($params)
    {
        //Zend_Debug::dump($params);die;
        if (!$this->isShipmentEditable($params['shipmentId'], $params['participantId'])) {
            return false;
        }

        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $alertMsg = new Zend_Session_Namespace('alertSpace');

        // $mandatoryFields = array('receiptDate', 'testDate', 'sampleRehydrationDate');
        $mandatoryFields = array('receiptDate', 'testDate');

        $db->beginTransaction();
        try {

            $mandatoryCheckErrors = $this->mandatoryFieldsCheck($params, $mandatoryFields);
            if (count($mandatoryCheckErrors) > 0) {
                $userAgent = $_SERVER['HTTP_USER_AGENT'];
                $commonService = new Application_Service_Common();

                $ipAddress = $commonService->getIPAddress();
                $operatingSystem = $commonService->getOperatingSystem($userAgent);
                $browser = $commonService->getBrowser($userAgent);
                //throw new Exception('Missed mandatory fields - ' . implode(",", $mandatoryCheckErrors));
                error_log(date('Y-m-d H:i:s') . '|FORMERROR|Missed mandatory fields - ' . implode(",", $mandatoryCheckErrors) . '|' . $params['schemeCode'] . '|' . $params['participantId'] . '|' . $ipAddress . '|' . $operatingSystem . '|' . $browser  . PHP_EOL, 3, DOWNLOADS_FOLDER . " /../errors.log");
                throw new Exception('Missed mandatory fields on the form');
            }

            $shipmentParticipantDb = new Application_Model_DbTable_ShipmentParticipantMap();
            $authNameSpace = new Zend_Session_Namespace('datamanagers');
            if (isset($params['sampleRehydrationDate']) && trim($params['sampleRehydrationDate']) != "") {
                $params['sampleRehydrationDate'] = Pt_Commons_General::dateFormat($params['sampleRehydrationDate']);
            } else {
                $params['sampleRehydrationDate'] = '';
            }
            if (isset($params['extractionAssayExpiryDate']) && trim($params['extractionAssayExpiryDate']) != "") {
                $params['extractionAssayExpiryDate'] = Pt_Commons_General::dateFormat($params['extractionAssayExpiryDate']);
            } else {
                $params['extractionAssayExpiryDate'] = '';
            }
            if (isset($params['detectionAssayExpiryDate']) && trim($params['detectionAssayExpiryDate']) != "") {
                $params['detectionAssayExpiryDate'] = Pt_Commons_General::dateFormat($params['detectionAssayExpiryDate']);
            } else {
                $params['detectionAssayExpiryDate'] = '';
            }
            if (!isset($params['modeOfReceipt']) || trim($params['modeOfReceipt']) == "") {
                $params['modeOfReceipt'] = NULL;
            }

            if (isset($params['extractionAssayOther']) && $params['extractionAssayOther'] != "") {
                $dbAdapter = Zend_Db_Table_Abstract::getDefaultAdapter();
                $ifExist = $dbAdapter->fetchRow($dbAdapter->select()->from(array('rea' => 'r_eid_extraction_assay'))->where('name LIKE "' . $params['extractionAssayOther'] . '%"'));
                if ($ifExist && $ifExist['name'] != "") {
                    $dbAdapter->update(
                        'r_eid_extraction_assay',
                        array(
                            'name'        => $params['extractionAssayOther'],
                            'status'  => 'active'
                        ),
                        'id = ' . $ifExist['id']
                    );
                    $lastInsertAssayId = $ifExist['id'];
                } else {
                    $dbAdapter->insert(
                        'r_eid_extraction_assay',
                        array(
                            'name'        => $params['extractionAssayOther'],
                            'status'  => 'active'
                        )
                    );
                    $lastInsertAssayId = $dbAdapter->lastInsertId();
                }
                $params['extractionAssay'] = $lastInsertAssayId;
            }
            $attributes = array(
                "sample_rehydration_date" => $params['sampleRehydrationDate'],
                "extraction_assay" => $params['extractionAssay'],
                "detection_assay" => $params['detectionAssay'],
                "extraction_assay_expiry_date" => $params['extractionAssayExpiryDate'],
                "detection_assay_expiry_date" => $params['detectionAssayExpiryDate'],
                "extraction_assay_lot_no" => $params['extractionAssayLotNo'],
                "detection_assay_lot_no" => $params['detectionAssayLotNo'],
                "uploaded_file" => $params['uploadedFilePath']
            );

            $attributes = json_encode($attributes);
            $data = array(
                "shipment_receipt_date" => Pt_Commons_General::dateFormat($params['receiptDate']),
                "shipment_test_date" => Pt_Commons_General::dateFormat($params['testDate']),
                //"shipment_test_report_date" => new Zend_Db_Expr('now()'),
                "attributes" => $attributes,
                "supervisor_approval" => $params['supervisorApproval'],
                "participant_supervisor" => $params['participantSupervisor'],
                "user_comment" => $params['userComments'],
                "mode_id" => $params['modeOfReceipt'],
                "updated_by_user" => $authNameSpace->dm_id,
                "updated_on_user" => new Zend_Db_Expr('now()')
            );

            if (isset($params['testReceiptDate']) && trim($params['testReceiptDate']) != '') {
                $data['shipment_test_report_date'] = Pt_Commons_General::dateFormat($params['testReceiptDate']);
            } else {
                $data['shipment_test_report_date'] = new Zend_Db_Expr('now()');
            }

            if (isset($params['isPtTestNotPerformed']) && $params['isPtTestNotPerformed'] == 'yes') {
                $data['is_pt_test_not_performed'] = 'yes';
                $data['shipment_test_date'] = NULL;
                $data['vl_not_tested_reason'] = $params['vlNotTestedReason'];
                $data['pt_test_not_performed_comments'] = $params['ptNotTestedComments'];
                $data['pt_support_comments'] = $params['ptSupportComments'];
            } else {
                $data['is_pt_test_not_performed'] = NULL;
                $data['vl_not_tested_reason'] = NULL;
                $data['pt_test_not_performed_comments'] = NULL;
                $data['pt_support_comments'] = NULL;
            }

            if (isset($authNameSpace->qc_access) && $authNameSpace->qc_access == 'yes') {
                $data['qc_done'] = $params['qcDone'];
                if (isset($data['qc_done']) && trim($data['qc_done']) == "yes") {
                    $data['qc_date'] = Pt_Commons_General::dateFormat($params['qcDate']);
                    $data['qc_done_by'] = trim($params['qcDoneBy']);
                    $data['qc_created_on'] = new Zend_Db_Expr('now()');
                } else {
                    $data['qc_date'] = NULL;
                    $data['qc_done_by'] = NULL;
                    $data['qc_created_on'] = NULL;
                }
            }

            if (isset($params['labDirectorName']) && $params['labDirectorName'] != "") {
                $dbAdapter = Zend_Db_Table_Abstract::getDefaultAdapter();
                /* Shipment Participant table updation */
                $dbAdapter->update(
                    'shipment_participant_map',
                    array(
                        'lab_director_name'         => $params['labDirectorName'],
                        'lab_director_email'        => $params['labDirectorEmail'],
                        'contact_person_name'       => $params['contactPersonName'],
                        'contact_person_email'      => $params['contactPersonEmail'],
                        'contact_person_telephone'  => $params['contactPersonTelephone']
                    ),
                    'map_id = ' . $params['smid']
                );
                /* Participant table updation */
                $dbAdapter->update(
                    'participant',
                    array(
                        'lab_director_name'         => $params['labDirectorName'],
                        'lab_director_email'        => $params['labDirectorEmail'],
                        'contact_person_name'       => $params['contactPersonName'],
                        'contact_person_email'      => $params['contactPersonEmail'],
                        'contact_person_telephone'  => $params['contactPersonTelephone']
                    ),
                    'participant_id = ' . $params['participantId']
                );
            }
            $noOfRowsAffected = $shipmentParticipantDb->updateShipment($data, $params['smid'], $params['hdLastDate']);

            $eidResponseDb = new Application_Model_DbTable_ResponseEid();
            $eidResponseDb->updateResults($params);
            $db->commit();
            $alertMsg->message = "Thank you for submitting your result. We have received it and the PT Results will be published on or after the due date";
        } catch (Exception $e) {
            // If any of the queries failed and threw an exception,
            // we want to roll back the whole transaction, reversing
            // changes made in the transaction, even those that succeeded.
            // Thus all changes are committed together, or none are.
            $db->rollBack();
            $alertMsg->message = "Sorry we could not record your result. Please try again or contact the PT adminstrator";
            error_log($e->getMessage());
            error_log($e->getTraceAsString());
        }
    }

    public function updateRecencyResults($params)
    {
        //Zend_Debug::dump($params);die;
        if (!$this->isShipmentEditable($params['shipmentId'], $params['participantId'])) {
            return false;
        }

        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $alertMsg = new Zend_Session_Namespace('alertSpace');

        $mandatoryFields = array('receiptDate', 'testDate', 'sampleRehydrationDate');

        $db->beginTransaction();
        try {


            $mandatoryCheckErrors = $this->mandatoryFieldsCheck($params, $mandatoryFields);
            if (count($mandatoryCheckErrors) > 0) {
                $userAgent = $_SERVER['HTTP_USER_AGENT'];
                $commonService = new Application_Service_Common();

                $ipAddress = $commonService->getIPAddress();
                $operatingSystem = $commonService->getOperatingSystem($userAgent);
                $browser = $commonService->getBrowser($userAgent);
                //throw new Exception('Missed mandatory fields - ' . implode(",", $mandatoryCheckErrors));
                error_log(date('Y-m-d H:i:s') . '|FORMERROR|Missed mandatory fields - ' . implode(",", $mandatoryCheckErrors) . '|' . $params['schemeCode'] . '|' . $params['participantId'] . '|' . $ipAddress . '|' . $operatingSystem . '|' . $browser  . PHP_EOL, 3, DOWNLOADS_FOLDER . " /../errors.log");
                throw new Exception('Missed mandatory fields on the form');
            }

            $shipmentParticipantDb = new Application_Model_DbTable_ShipmentParticipantMap();
            $authNameSpace = new Zend_Session_Namespace('datamanagers');
            if (isset($params['sampleRehydrationDate']) && trim($params['sampleRehydrationDate']) != "") {
                $params['sampleRehydrationDate'] = Pt_Commons_General::dateFormat($params['sampleRehydrationDate']);
            } else {
                $params['sampleRehydrationDate'] = '';
            }
            if (isset($params['recencyAssayExpiryDate']) && trim($params['recencyAssayExpiryDate']) != "") {
                $params['recencyAssayExpiryDate'] = Pt_Commons_General::dateFormat($params['recencyAssayExpiryDate']);
            } else {
                $params['recencyAssayExpiryDate'] = '';
            }

            if (!isset($params['modeOfReceipt']) || trim($params['modeOfReceipt']) == "") {
                $params['modeOfReceipt'] = NULL;
            }
            $attributes = array(
                "sample_rehydration_date" => $params['sampleRehydrationDate'],
                "recency_assay" => $params['recencyAssay'],
                "recency_assay_expiry_date" => $params['recencyAssayExpiryDate'],
                "recency_assay_lot_no" => $params['recencyAssayLotNo'],
                "uploaded_file" => $params['uploadedFilePath']
            );

            $attributes = json_encode($attributes);
            $data = array(
                "shipment_receipt_date" => Pt_Commons_General::dateFormat($params['receiptDate']),
                "shipment_test_date" => Pt_Commons_General::dateFormat($params['testDate']),
                //"shipment_test_report_date" => new Zend_Db_Expr('now()'),
                "attributes" => $attributes,
                "supervisor_approval" => $params['supervisorApproval'],
                "participant_supervisor" => $params['participantSupervisor'],
                "user_comment" => $params['userComments'],
                "mode_id" => $params['modeOfReceipt'],
                "updated_by_user" => $authNameSpace->dm_id,
                "updated_on_user" => new Zend_Db_Expr('now()')
            );

            if (isset($params['testReceiptDate']) && trim($params['testReceiptDate']) != '') {
                $data['shipment_test_report_date'] = Pt_Commons_General::dateFormat($params['testReceiptDate']);
            } else {
                $data['shipment_test_report_date'] = new Zend_Db_Expr('now()');
            }

            if (isset($params['isPtTestNotPerformed']) && $params['isPtTestNotPerformed'] == 'yes') {
                $data['is_pt_test_not_performed'] = 'yes';
                $data['shipment_test_date'] = NULL;
                $data['vl_not_tested_reason'] = $params['vlNotTestedReason'];
                $data['pt_test_not_performed_comments'] = $params['ptNotTestedComments'];
                $data['pt_support_comments'] = $params['ptSupportComments'];
            } else {
                $data['is_pt_test_not_performed'] = NULL;
                $data['vl_not_tested_reason'] = NULL;
                $data['pt_test_not_performed_comments'] = NULL;
                $data['pt_support_comments'] = NULL;
            }

            if (isset($authNameSpace->qc_access) && $authNameSpace->qc_access == 'yes') {
                $data['qc_done'] = $params['qcDone'];
                if (isset($data['qc_done']) && trim($data['qc_done']) == "yes") {
                    $data['qc_date'] = Pt_Commons_General::dateFormat($params['qcDate']);
                    $data['qc_done_by'] = trim($params['qcDoneBy']);
                    $data['qc_created_on'] = new Zend_Db_Expr('now()');
                } else {
                    $data['qc_date'] = NULL;
                    $data['qc_done_by'] = NULL;
                    $data['qc_created_on'] = NULL;
                }
            }
            $noOfRowsAffected = $shipmentParticipantDb->updateShipment($data, $params['smid'], $params['hdLastDate']);

            $recencyResponseDb = new Application_Model_DbTable_ResponseRecency();
            $recencyResponseDb->updateResults($params);
            $db->commit();
            $alertMsg->message = "Thank you for submitting your result. We have received it and the PT Results will be published on or after the due date";
        } catch (Exception $e) {
            // If any of the queries failed and threw an exception,
            // we want to roll back the whole transaction, reversing
            // changes made in the transaction, even those that succeeded.
            // Thus all changes are committed together, or none are.
            $db->rollBack();
            $alertMsg->message = "Sorry we could not record your result. Please try again or contact the PT adminstrator";
            error_log($e->getMessage());
            error_log($e->getTraceAsString());
        }
    }

    public function updateDtsResults($params)
    {
        if (!$this->isShipmentEditable($params['shipmentId'], $params['participantId'])) {
            return false;
        }

        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $alertMsg = new Zend_Session_Namespace('alertSpace');

        $mandatoryFields = array('receiptDate', 'testDate', 'sampleRehydrationDate', 'algorithm');
        $db->beginTransaction();
        try {

            $mandatoryCheckErrors = $this->mandatoryFieldsCheck($params, $mandatoryFields);
            if (count($mandatoryCheckErrors) > 0) {
                $userAgent = $_SERVER['HTTP_USER_AGENT'];
                $commonService = new Application_Service_Common();

                $ipAddress = $commonService->getIPAddress();
                $operatingSystem = $commonService->getOperatingSystem($userAgent);
                $browser = $commonService->getBrowser($userAgent);
                //throw new Exception('Missed mandatory fields - ' . implode(",", $mandatoryCheckErrors));
                error_log(date('Y-m-d H:i:s') . '|FORMERROR|Missed mandatory fields - ' . implode(",", $mandatoryCheckErrors) . '|' . $params['schemeCode'] . '|' . $params['participantId'] . '|' . $ipAddress . '|' . $operatingSystem . '|' . $browser  . PHP_EOL, 3, DOWNLOADS_FOLDER . " /../errors.log");
                throw new Exception('Missed mandatory fields on the form');
            }


            $shipmentParticipantDb = new Application_Model_DbTable_ShipmentParticipantMap();
            $authNameSpace = new Zend_Session_Namespace('datamanagers');
            $attributes["sample_rehydration_date"] = Pt_Commons_General::dateFormat($params['sampleRehydrationDate']);
            $attributes["algorithm"] = $params['algorithm'];
            $attributes["condition_pt_samples"] = (isset($params['conditionOfPTSamples']) && !empty($params['conditionOfPTSamples'])) ? $params['conditionOfPTSamples'] : '';
            $attributes["refridgerator"] = (isset($params['refridgerator']) && !empty($params['refridgerator'])) ? $params['refridgerator'] : '';
            $attributes["room_temperature"] = (isset($params['roomTemperature']) && !empty($params['roomTemperature'])) ? $params['roomTemperature'] : '';
            $attributes["stop_watch"] = (isset($params['stopWatch']) && !empty($params['stopWatch'])) ? $params['stopWatch'] : '';

            $attributes = json_encode($attributes);

            $data = array(
                "shipment_receipt_date" => Pt_Commons_General::dateFormat($params['receiptDate']),
                "shipment_test_date" => Pt_Commons_General::dateFormat($params['testDate']),
                //"shipment_test_report_date" => new Zend_Db_Expr('now()'),
                "attributes" => $attributes,
                "supervisor_approval" => $params['supervisorApproval'],
                "participant_supervisor" => $params['participantSupervisor'],
                "user_comment" => $params['userComments'],
                "updated_by_user" => $authNameSpace->dm_id,
                "mode_id" => $params['modeOfReceipt'],
                "updated_on_user" => new Zend_Db_Expr('now()')
            );

            if (isset($params['testReceiptDate']) && trim($params['testReceiptDate']) != '') {
                $data['shipment_test_report_date'] = Pt_Commons_General::dateFormat($params['testReceiptDate']);
            } else {
                $data['shipment_test_report_date'] = new Zend_Db_Expr('now()');
            }

            if (isset($authNameSpace->qc_access) && $authNameSpace->qc_access == 'yes') {
                $data['qc_done'] = $params['qcDone'];
                if (isset($data['qc_done']) && trim($data['qc_done']) == "yes") {
                    $data['qc_date'] = Pt_Commons_General::dateFormat($params['qcDate']);
                    $data['qc_done_by'] = trim($params['qcDoneBy']);
                    $data['qc_created_on'] = new Zend_Db_Expr('now()');
                } else {
                    $data['qc_date'] = null;
                    $data['qc_done_by'] = null;
                    $data['qc_created_on'] = null;
                }
            }

            if (isset($params['isPtTestNotPerformed']) && $params['isPtTestNotPerformed'] == 'yes') {
                $data['is_pt_test_not_performed'] = 'yes';
                $data['shipment_test_date'] = NULL;
                $data['vl_not_tested_reason'] = $params['vlNotTestedReason'];
                $data['pt_test_not_performed_comments'] = $params['ptNotTestedComments'];
                $data['pt_support_comments'] = $params['ptSupportComments'];
            } else {
                $data['is_pt_test_not_performed'] = NULL;
                $data['vl_not_tested_reason'] = NULL;
                $data['pt_test_not_performed_comments'] = NULL;
                $data['pt_support_comments'] = NULL;
            }

            if (isset($params['customField1']) && !empty(trim($params['customField1']))) {
                $data['custom_field_1'] = trim($params['customField1']);
            }

            if (isset($params['customField2']) && !empty(trim($params['customField2']))) {
                $data['custom_field_2'] = trim($params['customField2']);
            }

            $noOfRowsAffected = $shipmentParticipantDb->updateShipment($data, $params['smid'], $params['hdLastDate']);

            $dtsResponseDb = new Application_Model_DbTable_ResponseDts();
            $dtsResponseDb->updateResults($params);
            $db->commit();
            $alertMsg->message = "Thank you for submitting your result. We have received it and the PT Results will be published on or after the due date";
        } catch (Exception $e) {
            // If any of the queries failed and threw an exception,
            // we want to roll back the whole transaction, reversing
            // changes made in the transaction, even those that succeeded.
            // Thus all changes are committed together, or none are.
            $db->rollBack();
            $alertMsg->message = "Sorry we could not record your result. Please try again or contact the PT adminstrator. \\n\\nReason: " . $e->getMessage();
            error_log($e->getMessage());
            error_log($e->getTraceAsString());
        }
    }

    public function updateCovid19Results($params)
    {
        if (!$this->isShipmentEditable($params['shipmentId'], $params['participantId'])) {
            return false;
        }
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $alertMsg = new Zend_Session_Namespace('alertSpace');
        $db->beginTransaction();
        try {
            $shipmentParticipantDb = new Application_Model_DbTable_ShipmentParticipantMap();
            $authNameSpace = new Zend_Session_Namespace('datamanagers');
            $attributes["sample_rehydration_date"] = Pt_Commons_General::dateFormat($params['sampleRehydrationDate']);
            $attributes["algorithm"] = $params['algorithm'];
            $attributes = json_encode($attributes);

            $data = array(
                "shipment_receipt_date" => Pt_Commons_General::dateFormat($params['receiptDate']),
                "shipment_test_date" => Pt_Commons_General::dateFormat($params['testDate']),
                //"shipment_test_report_date" => new Zend_Db_Expr('now()'),
                "attributes" => $attributes,
                "supervisor_approval" => $params['supervisorApproval'],
                "participant_supervisor" => $params['participantSupervisor'],
                "user_comment" => $params['userComments'],
                "updated_by_user" => $authNameSpace->dm_id,
                "mode_id" => $params['modeOfReceipt'],
                "number_of_tests" => $params['numberOfParticipantTest'],
                "specimen_volume" => $params['specimenVolume'],
                "updated_on_user" => new Zend_Db_Expr('now()')
            );

            if (isset($params['testReceiptDate']) && trim($params['testReceiptDate']) != '') {
                $data['shipment_test_report_date'] = Pt_Commons_General::dateFormat($params['testReceiptDate']);
            } else {
                $data['shipment_test_report_date'] = new Zend_Db_Expr('now()');
            }

            if (isset($authNameSpace->qc_access) && $authNameSpace->qc_access == 'yes') {
                $data['qc_done'] = $params['qcDone'];
                if (isset($data['qc_done']) && trim($data['qc_done']) == "yes") {
                    $data['qc_date'] = Pt_Commons_General::dateFormat($params['qcDate']);
                    $data['qc_done_by'] = trim($params['qcDoneBy']);
                    $data['qc_created_on'] = new Zend_Db_Expr('now()');
                } else {
                    $data['qc_date'] = null;
                    $data['qc_done_by'] = null;
                    $data['qc_created_on'] = null;
                }
            }

            if (isset($params['isPtTestNotPerformed']) && $params['isPtTestNotPerformed'] == 'yes') {
                $data['is_pt_test_not_performed'] = 'yes';
                $data['shipment_test_date'] = NULL;
                $data['vl_not_tested_reason'] = $params['vlNotTestedReason'];
                $data['pt_test_not_performed_comments'] = $params['ptNotTestedComments'];
                $data['pt_support_comments'] = $params['ptSupportComments'];
            } else {
                $data['is_pt_test_not_performed'] = NULL;
                $data['vl_not_tested_reason'] = NULL;
                $data['pt_test_not_performed_comments'] = NULL;
                $data['pt_support_comments'] = NULL;
            }

            if (isset($params['customField1']) && !empty(trim($params['customField1']))) {
                $data['custom_field_1'] = trim($params['customField1']);
            }

            if (isset($params['customField2']) && !empty(trim($params['customField2']))) {
                $data['custom_field_2'] = trim($params['customField2']);
            }
            $noOfRowsAffected = $shipmentParticipantDb->updateShipment($data, $params['smid'], $params['hdLastDate']);
            /* Save Gene Type */
            $geneIdentifyTypesDb = new Application_Model_DbTable_Covid19IdentifiedGenes();
            $geneIdentifyTypesDb->saveCovid19IdentifiedGenesResults($params);

            $covid19ResponseDb = new Application_Model_DbTable_ResponseCovid19();
            $covid19ResponseDb->updateResults($params);
            $db->commit();
            $alertMsg->message = "Thank you for submitting your result. We have received it and the PT Results will be published on or after the due date";
        } catch (Exception $e) {
            // If any of the queries failed and threw an exception,
            // we want to roll back the whole transaction, reversing
            // changes made in the transaction, even those that succeeded.
            // Thus all changes are committed together, or none are.
            $db->rollBack();
            $alertMsg->message = "Sorry we could not record your result. Please try again or contact the PT adminstrator";
            error_log($e->getMessage());
            error_log($e->getTraceAsString());
        }
    }

    public function removeDtsResults($mapId)
    {
        try {
            $shipmentParticipantDb = new Application_Model_DbTable_ShipmentParticipantMap();
            $authNameSpace = new Zend_Session_Namespace('datamanagers');
            $data = array(
                "shipment_receipt_date" => null,
                "shipment_test_date" => null,
                "attributes" => null,
                "shipment_test_report_date" => null,
                "supervisor_approval" => null,
                "participant_supervisor" => null,
                "user_comment" => null,
                "final_result" => null,
                "failure_reason" => null,
                "evaluation_comment" => null,
                "is_followup" => 'no',
                "is_excluded" => null,
                "is_pt_test_not_performed" => null,
                "vl_not_tested_reason" => null,
                "pt_test_not_performed_comments" => null,
                "pt_support_comments" => null,
                "updated_on_user" => null,
                "updated_by_user" =>  null,
                "qc_date" => null,
                "qc_done" => 'no',
                "qc_done_by" => null,
                "qc_created_on" => null,
                "mode_id" => null,
                "custom_field_1" => null,
                "custom_field_2" => null,
                "synced" => 'no',
                "synced_on" => null
            );
            $noOfRowsAffected = $shipmentParticipantDb->removeShipmentMapDetails($data, $mapId);

            $dtsResponseDb = new Application_Model_DbTable_ResponseDts();
            $dtsResponseDb->removeShipmentResults($mapId);
        } catch (Exception $e) {
            return ($e->getMessage());
            return "Unable to delete. Please try again later or contact system admin for help";
        }
    }

    public function removeCovid19Results($mapId)
    {
        try {
            $shipmentParticipantDb = new Application_Model_DbTable_ShipmentParticipantMap();
            $authNameSpace = new Zend_Session_Namespace('datamanagers');
            $data = array(
                "shipment_receipt_date" => null,
                "shipment_test_date" => null,
                "specimen_volume" => null,
                "attributes" => null,
                "shipment_test_report_date" => null,
                "supervisor_approval" => null,
                "participant_supervisor" => null,
                "user_comment" => null,
                "final_result" => null,
                "failure_reason" => null,
                "evaluation_comment" => null,
                "is_followup" => 'no',
                "is_excluded" => null,
                "is_pt_test_not_performed" => null,
                "vl_not_tested_reason" => null,
                "pt_test_not_performed_comments" => null,
                "pt_support_comments" => null,
                "updated_on_user" => null,
                "updated_by_user" =>  null,
                "qc_date" => null,
                "qc_done" => 'no',
                "qc_done_by" => null,
                "qc_created_on" => null,
                "mode_id" => null,
                "custom_field_1" => null,
                "custom_field_2" => null,
                "synced" => 'no',
                "synced_on" => null
            );
            $noOfRowsAffected = $shipmentParticipantDb->removeShipmentMapDetails($data, $mapId);

            $geneTypeDb = new Application_Model_DbTable_Covid19IdentifiedGenes();
            $geneTypeDb->deleteCovid19IdentifiedGenesResults($mapId);

            $covid19ResponseDb = new Application_Model_DbTable_ResponseCovid19();
            $covid19ResponseDb->removeShipmentResults($mapId);
        } catch (Exception $e) {
            return ($e->getMessage());
            return "Unable to delete. Please try again later or contact system admin for help";
        }
    }

    public function removeDtsEidResults($mapId)
    {
        try {
            $shipmentParticipantDb = new Application_Model_DbTable_ShipmentParticipantMap();
            $authNameSpace = new Zend_Session_Namespace('datamanagers');
            $data = array(
                "shipment_receipt_date" => null,
                "shipment_test_date" => null,
                "attributes" => null,
                "shipment_test_report_date" => null,
                "supervisor_approval" => null,
                "participant_supervisor" => null,
                "user_comment" => null,
                "final_result" => null,
                "failure_reason" => null,
                "is_followup" => 'no',
                "evaluation_comment" => null,
                "is_excluded" => null,
                "is_pt_test_not_performed" => null,
                "vl_not_tested_reason" => null,
                "pt_test_not_performed_comments" => null,
                "pt_support_comments" => null,
                "updated_on_user" => null,
                "updated_by_user" =>  null,
                "qc_date" => null,
                "qc_done" => 'no',
                "qc_done_by" => null,
                "qc_created_on" => null,
                "mode_id" => null,
                "custom_field_1" => null,
                "custom_field_2" => null,
                "synced" => 'no',
                "synced_on" => null
            );
            $noOfRowsAffected = $shipmentParticipantDb->removeShipmentMapDetails($data, $mapId);

            $responseDb = new Application_Model_DbTable_ResponseEid();
            $responseDb->delete("shipment_map_id=$mapId");
        } catch (Exception $e) {
            return ($e->getMessage());
            return "Unable to delete. Please try again later or contact system admin for help";
        }
    }
    public function removeRecencyResults($mapId)
    {
        try {
            $shipmentParticipantDb = new Application_Model_DbTable_ShipmentParticipantMap();
            $authNameSpace = new Zend_Session_Namespace('datamanagers');
            $data = array(
                "shipment_receipt_date" => null,
                "shipment_test_date" => null,
                "attributes" => null,
                "shipment_test_report_date" => null,
                "supervisor_approval" => null,
                "participant_supervisor" => null,
                "user_comment" => null,
                "final_result" => null,
                "failure_reason" => null,
                "is_followup" => 'no',
                "evaluation_comment" => null,
                "is_excluded" => null,
                "is_pt_test_not_performed" => null,
                "vl_not_tested_reason" => null,
                "pt_test_not_performed_comments" => null,
                "pt_support_comments" => null,
                "updated_on_user" => null,
                "updated_by_user" =>  null,
                "qc_date" => null,
                "qc_done" => 'no',
                "qc_done_by" => null,
                "qc_created_on" => null,
                "mode_id" => null,
                "custom_field_1" => null,
                "custom_field_2" => null,
                "synced" => 'no',
                "synced_on" => null
            );
            $noOfRowsAffected = $shipmentParticipantDb->removeShipmentMapDetails($data, $mapId);

            $responseDb = new Application_Model_DbTable_ResponseRecency();
            $responseDb->delete("shipment_map_id=$mapId");
        } catch (Exception $e) {
            return ($e->getMessage());
            return "Unable to delete. Please try again later or contact system admin for help";
        }
    }

    public function removeDtsVlResults($mapId)
    {
        try {
            $shipmentParticipantDb = new Application_Model_DbTable_ShipmentParticipantMap();
            $authNameSpace = new Zend_Session_Namespace('datamanagers');
            $data = array(
                "shipment_receipt_date" => null,
                "shipment_test_date" => null,
                "attributes" => null,
                "shipment_test_report_date" => null,
                "supervisor_approval" => null,
                "participant_supervisor" => null,
                "user_comment" => null,
                "final_result" => null,
                "failure_reason" => null,
                "evaluation_comment" => null,
                "is_followup" => 'no',
                "is_excluded" => null,
                "is_pt_test_not_performed" => null,
                "vl_not_tested_reason" => null,
                "pt_test_not_performed_comments" => null,
                "pt_support_comments" => null,
                "updated_on_user" => null,
                "updated_by_user" =>  null,
                "qc_date" => null,
                "qc_done" => 'no',
                "qc_done_by" => null,
                "qc_created_on" => null,
                "mode_id" => null,
                "custom_field_1" => null,
                "custom_field_2" => null,
                "synced" => 'no',
                "synced_on" => null
            );
            $noOfRowsAffected = $shipmentParticipantDb->removeShipmentMapDetails($data, $mapId);

            $responseDb = new Application_Model_DbTable_ResponseVl();
            $responseDb->delete("shipment_map_id=$mapId");
        } catch (Exception $e) {
            return ($e->getMessage());
            return "Unable to delete. Please try again later or contact system admin for help";
        }
    }

    public function updateDbsResults($params)
    {

        if (!$this->isShipmentEditable($params['shipmentId'], $params['participantId'])) {
            return false;
        }
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();

        $db->beginTransaction();
        try {
            $shipmentParticipantDb = new Application_Model_DbTable_ShipmentParticipantMap();
            $authNameSpace = new Zend_Session_Namespace('datamanagers');
            $attributes["sample_rehydration_date"] = Pt_Commons_General::dateFormat($params['sampleRehydrationDate']);
            $attributes = json_encode($attributes);
            $data = array(
                "shipment_receipt_date" => Pt_Commons_General::dateFormat($params['receiptDate']),
                "shipment_test_date" => Pt_Commons_General::dateFormat($params['testDate']),
                "attributes" => $attributes,
                //"shipment_test_report_date" => new Zend_Db_Expr('now()'),
                "supervisor_approval" => $params['supervisorApproval'],
                "participant_supervisor" => $params['participantSupervisor'],
                "user_comment" => $params['userComments'],
                "mode_id" => $params['modeOfReceipt'],
                "updated_by_user" => $authNameSpace->dm_id,
                "updated_on_user" => new Zend_Db_Expr('now()')
            );
            if (isset($params['testReceiptDate']) && trim($params['testReceiptDate']) != '') {
                $data['shipment_test_report_date'] = Pt_Commons_General::dateFormat($params['testReceiptDate']);
            } else {
                $data['shipment_test_report_date'] = new Zend_Db_Expr('now()');
            }

            if (isset($authNameSpace->qc_access) && $authNameSpace->qc_access == 'yes') {
                $data['qc_done'] = $params['qcDone'];
                if (isset($data['qc_done']) && trim($data['qc_done']) == "yes") {
                    $data['qc_date'] = Pt_Commons_General::dateFormat($params['qcDate']);
                    $data['qc_done_by'] = trim($params['qcDoneBy']);
                    $data['qc_created_on'] = new Zend_Db_Expr('now()');
                } else {
                    $data['qc_date'] = NULL;
                    $data['qc_done_by'] = NULL;
                    $data['qc_created_on'] = NULL;
                }
            }
            $noOfRowsAffected = $shipmentParticipantDb->updateShipment($data, $params['smid'], $params['hdLastDate']);

            $dbsResponseDb = new Application_Model_DbTable_ResponseDbs();
            $dbsResponseDb->updateResults($params);
            $db->commit();
        } catch (Exception $e) {
            // If any of the queries failed and threw an exception,
            // we want to roll back the whole transaction, reversing
            // changes made in the transaction, even those that succeeded.
            // Thus all changes are committed together, or none are.
            $db->rollBack();
            error_log($e->getMessage());
            error_log($e->getTraceAsString());
        }
    }

    public function updateTbResults($params)
    {

        if (!$this->isShipmentEditable($params['shipmentId'], $params['participantId'])) {
            return false;
        }

        $db = Zend_Db_Table_Abstract::getDefaultAdapter();

        $db->beginTransaction();
        try {
            $shipmentParticipantDb = new Application_Model_DbTable_ShipmentParticipantMap();
            $authNameSpace = new Zend_Session_Namespace('datamanagers');
            $attributes = array(
                "sample_rehydration_date" => Pt_Commons_General::dateFormat($params['sampleRehydrationDate']),
                "mtb_rif_kit_lot_no" => $params['mtbRifKitLotNo'],
                "expiry_date" => $params['expiryDate']
            );
            $attributes = json_encode($attributes);
            $data = array(
                "shipment_receipt_date" => Pt_Commons_General::dateFormat($params['receiptDate']),
                "shipment_test_date" => Pt_Commons_General::dateFormat($params['testDate']),
                "attributes" => $attributes,
                //"shipment_test_report_date" => new Zend_Db_Expr('now()'),
                "supervisor_approval" => $params['supervisorApproval'],
                "participant_supervisor" => $params['participantSupervisor'],
                "user_comment" => $params['userComments'],
                "mode_id" => $params['modeOfReceipt'],
                "updated_by_user" => $authNameSpace->dm_id,
                "updated_on_user" => new Zend_Db_Expr('now()')
            );

            if (isset($params['testReceiptDate']) && trim($params['testReceiptDate']) != '') {
                $data['shipment_test_report_date'] = Pt_Commons_General::dateFormat($params['testReceiptDate']);
            } else {
                $data['shipment_test_report_date'] = new Zend_Db_Expr('now()');
            }

            if (isset($authNameSpace->qc_access) && $authNameSpace->qc_access == 'yes') {
                $data['qc_done'] = $params['qcDone'];
                if (isset($data['qc_done']) && trim($data['qc_done']) == "yes") {
                    $data['qc_date'] = Pt_Commons_General::dateFormat($params['qcDate']);
                    $data['qc_done_by'] = trim($params['qcDoneBy']);
                    $data['qc_created_on'] = new Zend_Db_Expr('now()');
                } else {
                    $data['qc_date'] = NULL;
                    $data['qc_done_by'] = NULL;
                    $data['qc_created_on'] = NULL;
                }
            }
            $noOfRowsAffected = $shipmentParticipantDb->updateShipment($data, $params['smid'], $params['hdLastDate']);

            $tbResponseDb = new Application_Model_DbTable_ResponseTb();
            $tbResponseDb->updateResults($params);
            $db->commit();
        } catch (Exception $e) {
            // If any of the queries failed and threw an exception,
            // we want to roll back the whole transaction, reversing
            // changes made in the transaction, even those that succeeded.
            // Thus all changes are committed together, or none are.
            $db->rollBack();
            error_log($e->getMessage());
            error_log($e->getTraceAsString());
        }
    }

    public function updateVlResults($params)
    {
        //print_r($params);die;
        if (!$this->isShipmentEditable($params['shipmentId'], $params['participantId'])) {
            return false;
        }

        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $alertMsg = new Zend_Session_Namespace('alertSpace');

        // $mandatoryFields = array('receiptDate', 'testDate', 'vlAssay', 'assayExpirationDate', 'assayLotNumber');
        $mandatoryFields = array('receiptDate', 'testDate', 'vlAssay');

        $db->beginTransaction();
        try {

            $mandatoryCheckErrors = $this->mandatoryFieldsCheck($params, $mandatoryFields);
            if (count($mandatoryCheckErrors) > 0) {

                $userAgent = $_SERVER['HTTP_USER_AGENT'];
                $commonService = new Application_Service_Common();

                $ipAddress = $commonService->getIPAddress();
                $operatingSystem = $commonService->getOperatingSystem($userAgent);
                $browser = $commonService->getBrowser($userAgent);
                //throw new Exception('Missed mandatory fields - ' . implode(",", $mandatoryCheckErrors));
                error_log(date('Y-m-d H:i:s') . '|FORMERROR|Missed mandatory fields - ' . implode(",", $mandatoryCheckErrors) . '|' . $params['schemeCode'] . '|' . $params['participantId'] . '|' . $ipAddress . '|' . $operatingSystem . '|' . $browser  . PHP_EOL, 3, DOWNLOADS_FOLDER . " /../errors.log");
                throw new Exception('Missed mandatory fields on the form');
            }
            $shipmentParticipantDb = new Application_Model_DbTable_ShipmentParticipantMap();
            $authNameSpace = new Zend_Session_Namespace('datamanagers');
            if (isset($params['sampleRehydrationDate']) && trim($params['sampleRehydrationDate']) != "") {
                $params['sampleRehydrationDate'] = Pt_Commons_General::dateFormat($params['sampleRehydrationDate']);
            }
            if (isset($params['assayExpirationDate']) && trim($params['assayExpirationDate']) != "") {
                $params['assayExpirationDate'] = Pt_Commons_General::dateFormat($params['assayExpirationDate']);
            }

            $attributes = array(
                "sample_rehydration_date" => (isset($params['sampleRehydrationDate']) && !empty($params['sampleRehydrationDate'])) ? $params['sampleRehydrationDate'] : '',
                "vl_assay" => (isset($params['vlAssay']) && !empty($params['vlAssay'])) ? (int)$params['vlAssay'] : '',
                "assay_lot_number" => (isset($params['assayLotNumber']) && !empty($params['assayLotNumber'])) ? $params['assayLotNumber'] : '',
                "assay_expiration_date" => (isset($params['assayExpirationDate']) && !empty($params['assayExpirationDate'])) ? $params['assayExpirationDate'] : '',
                "specimen_volume" => (isset($params['specimenVolume']) && !empty($params['specimenVolume'])) ? $params['specimenVolume'] : '',
                "uploaded_file" => (isset($params['uploadedFilePath']) && !empty($params['uploadedFilePath'])) ? $params['uploadedFilePath'] : ''
            );

            if (isset($params['otherAssay']) && $params['otherAssay'] != "") {
                $attributes['other_assay'] = htmlentities($params['otherAssay']);
            }

            if (!isset($params['modeOfReceipt'])) {
                $params['modeOfReceipt'] = NULL;
            }
            $attributes = Zend_Json::encode($attributes);
            $data = array(
                "shipment_receipt_date" => Pt_Commons_General::dateFormat($params['receiptDate']),
                "shipment_test_date" => Pt_Commons_General::dateFormat($params['testDate']),
                "attributes" => $attributes,
                //"shipment_test_report_date" => new Zend_Db_Expr('now()'),
                "supervisor_approval" => $params['supervisorApproval'],
                "participant_supervisor" => $params['participantSupervisor'],
                "user_comment" => $params['userComments'],
                "updated_by_user" => $authNameSpace->dm_id,
                "mode_id" => $params['modeOfReceipt'],
                "updated_on_user" => new Zend_Db_Expr('now()')
            );
            if (isset($params['testReceiptDate']) && trim($params['testReceiptDate']) != '') {
                $data['shipment_test_report_date'] = Pt_Commons_General::dateFormat($params['testReceiptDate']);
            } else {
                $data['shipment_test_report_date'] = new Zend_Db_Expr('now()');
            }

            if (isset($params['isPtTestNotPerformed']) && $params['isPtTestNotPerformed'] == 'yes') {
                $data['is_pt_test_not_performed'] = 'yes';
                $data['shipment_test_date'] = NULL;
                $data['vl_not_tested_reason'] = $params['vlNotTestedReason'];
                $data['pt_test_not_performed_comments'] = $params['ptNotTestedComments'];
                $data['pt_support_comments'] = $params['ptSupportComments'];
            } else {
                $data['is_pt_test_not_performed'] = NULL;
                $data['vl_not_tested_reason'] = NULL;
                $data['pt_test_not_performed_comments'] = NULL;
                $data['pt_support_comments'] = NULL;
            }

            if (isset($authNameSpace->qc_access) && $authNameSpace->qc_access == 'yes') {
                $data['qc_done'] = $params['qcDone'];
                if (isset($data['qc_done']) && trim($data['qc_done']) == "yes") {
                    $data['qc_date'] = Pt_Commons_General::dateFormat($params['qcDate']);
                    $data['qc_done_by'] = trim($params['qcDoneBy']);
                    $data['qc_created_on'] = new Zend_Db_Expr('now()');
                } else {
                    $data['qc_date'] = NULL;
                    $data['qc_done_by'] = NULL;
                    $data['qc_created_on'] = NULL;
                }
            }
            if (isset($params['labDirectorName']) && $params['labDirectorName'] != "") {
                $dbAdapter = Zend_Db_Table_Abstract::getDefaultAdapter();
                /* Shipment Participant table updation */
                $dbAdapter->update(
                    'shipment_participant_map',
                    array(
                        'lab_director_name'         => $params['labDirectorName'],
                        'lab_director_email'        => $params['labDirectorEmail'],
                        'contact_person_name'       => $params['contactPersonName'],
                        'contact_person_email'      => $params['contactPersonEmail'],
                        'contact_person_telephone'  => $params['contactPersonTelephone']
                    ),
                    'map_id = ' . $params['smid']
                );
                /* Participant table updation */
                $dbAdapter->update(
                    'participant',
                    array(
                        'lab_director_name'         => $params['labDirectorName'],
                        'lab_director_email'        => $params['labDirectorEmail'],
                        'contact_person_name'       => $params['contactPersonName'],
                        'contact_person_email'      => $params['contactPersonEmail'],
                        'contact_person_telephone'  => $params['contactPersonTelephone']
                    ),
                    'participant_id = ' . $params['participantId']
                );
            }
            $noOfRowsAffected = $shipmentParticipantDb->updateShipment($data, $params['smid'], $params['hdLastDate']);

            $vlResponseDb = new Application_Model_DbTable_ResponseVl();
            $vlResponseDb->updateResults($params);
            $db->commit();
            $alertMsg->message = "Thank you for submitting your result. We have received it and the PT Results will be published on or after the due date";
        } catch (Exception $e) {
            // If any of the queries failed and threw an exception,
            // we want to roll back the whole transaction, reversing
            // changes made in the transaction, even those that succeeded.
            // Thus all changes are committed together, or none are.
            $db->rollBack();
            $alertMsg->message = "Sorry we could not record your result. Please try again or contact the PT adminstrator";
            error_log($e->getMessage());
        }
    }

    public function addShipment($params)
    {

        $scheme = $params['schemeId'];
        $authNameSpace = new Zend_Session_Namespace('administrators');
        $db = new Application_Model_DbTable_Shipments();
        $distroService = new Application_Service_Distribution();
        $distro = $distroService->getDistribution($params['distribution']);
        $file = APPLICATION_PATH . DIRECTORY_SEPARATOR . "configs" . DIRECTORY_SEPARATOR . "config.ini";
        $config = new Zend_Config_Ini($file, null, array('allowModifications' => true));
        $sec = APPLICATION_ENV;

        $controlCount = 0;
        foreach ($params['control'] as $control) {
            if ($control == 1) {
                $controlCount += 1;
            }
        }

        $shipmentAttributes = array();

        if (isset($params['dtsSampleType']) && !empty($params['dtsSampleType'])) {
            $shipmentAttributes['sampleType'] = $params['dtsSampleType'];
        }
        /* Method Of Evaluation for vl form */
        if (isset($params['methodOfEvaluation']) && !empty($params['methodOfEvaluation'])) {
            $shipmentAttributes['methodOfEvaluation'] = $params['methodOfEvaluation'];
        }
        if (isset($params['screeningTest']) && !empty($params['screeningTest'])) {
            $shipmentAttributes['screeningTest'] = $params['screeningTest'];
        }
        if (isset($params['enableSyphilis']) && !empty($params['enableSyphilis'])) {
            $shipmentAttributes['enableSyphilis'] = $params['enableSyphilis'];
        }
        if (isset($params['enableRtri']) && !empty($params['enableRtri'])) {
            $shipmentAttributes['enableRtri'] = $params['enableRtri'];
        }

        if (isset($config->$sec->evaluation->dts->dtsSchemeType) && $config->$sec->evaluation->dts->dtsSchemeType != "") {
            $shipmentAttributes['dtsSchemeType'] = $config->$sec->evaluation->dts->dtsSchemeType;
        } else {
            $shipmentAttributes['dtsSchemeType'] = 'standard';
        }
        $data = array(
            'shipment_code'         => $params['shipmentCode'],
            'shipment_attributes'   => empty($shipmentAttributes) ? null : json_encode($shipmentAttributes),
            'distribution_id'       => $params['distribution'],
            'scheme_type'           => $scheme,
            'shipment_date'         => $distro['distribution_date'],
            'number_of_samples'     => count($params['sampleName']) - $controlCount,
            'number_of_controls'    => $controlCount,
            'pt_co_ordinator_name'  => $params['PtCoOrdinatorName'],
            'lastdate_response'     => Pt_Commons_General::dateFormat($params['lastDate']),
            'created_on_admin'      => new Zend_Db_Expr('now()'),
            'created_by_admin'      => $authNameSpace->primary_email
        );
        $lastId = $db->insert($data);
        if ($lastId > 0) {
            $authNameSpace = new Zend_Session_Namespace('administrators');
            $auditDb = new Application_Model_DbTable_AuditLog();
            $auditDb->addNewAuditLog("Created new shipment " . $params['shipmentCode'], "shipment");
        }

        $dbAdapter = Zend_Db_Table_Abstract::getDefaultAdapter();
        $size = count($params['sampleName']);

        if ($params['schemeId'] == 'eid') {
            for ($i = 0; $i < $size; $i++) {
                $dbAdapter->insert(
                    'reference_result_eid',
                    array(
                        'shipment_id' => $lastId,
                        'sample_id' => ($i + 1),
                        'sample_label' => $params['sampleName'][$i],
                        'reference_result' => $params['possibleResults'][$i],
                        'reference_hiv_ct_od' => $params['hivCtOd'][$i],
                        'reference_ic_qs' => $params['icQs'][$i],
                        'control' => $params['control'][$i],
                        'mandatory' => $params['mandatory'][$i],
                        'sample_score' => 1
                    )
                );
            }
        } else if ($params['schemeId'] == 'vl') {
            //Zend_Debug::dump($params['vlRef']);die;
            for ($i = 0; $i < $size; $i++) {
                $dbAdapter->insert(
                    'reference_result_vl',
                    array(
                        'shipment_id' => $lastId,
                        'sample_id' => ($i + 1),
                        'sample_label' => $params['sampleName'][$i],
                        //'reference_result' => $params['vlResult'][$i],
                        'control' => $params['control'][$i],
                        'mandatory' => $params['mandatory'][$i],
                        'sample_score' => 1
                    )
                );
                if (isset($params['vlRef'][$i + 1]['assay'])) {
                    $assaySize = count($params['vlRef'][$i + 1]['assay']);;
                    for ($e = 0; $e < $assaySize; $e++) {
                        if (trim($params['vlRef'][$i + 1]['assay'][$e]) != "" && trim($params['vlRef'][$i + 1]['value'][$e]) != "") {
                            $dbAdapter->insert(
                                'reference_vl_methods',
                                array(
                                    'shipment_id' => $lastId,
                                    'sample_id' => ($i + 1),
                                    'assay' => $params['vlRef'][$i + 1]['assay'][$e],
                                    'value' => $params['vlRef'][$i + 1]['value'][$e]
                                )
                            );
                        }
                    }
                }
            }
        } else if ($params['schemeId'] == 'dts') {
            for ($i = 0; $i < $size; $i++) {
                $refResulTDTSData = array(
                    'shipment_id'               => $lastId,
                    'sample_id'                 => ($i + 1),
                    'sample_label'              => $params['sampleName'][$i],
                    'reference_result'          => $params['possibleResults'][$i],
                    'control'                   => $params['control'][$i],
                    'syphilis_reference_result' => $params['possibleSyphilisResults'][$i] ?? null,
                    'dts_rtri_reference_result' => (isset($params['possibleRTRIResults'][$i]) && !empty($params['possibleRTRIResults'][$i])) ? $params['possibleRTRIResults'][$i] : null,
                    'mandatory'                 => $params['mandatory'][$i],
                    'sample_score'              => ($params['control'][$i] == 1 ? 0 : 1) // 0 for control, 1 for normal sample
                );
                if (isset($params['possibleSyphilisResults'][$i]) && trim($params['possibleSyphilisResults'][$i]) != "") {
                    $refResulTDTSData['syphilis_reference_result'] = $params['possibleSyphilisResults'][$i];
                }

                $lastRefId = $dbAdapter->insert('reference_result_dts', $refResulTDTSData);
                // <------ Insert reference_dts_eia table
                if (isset($params['eia'][$i + 1]['eia'])) {
                    $eiaSize = sizeof($params['eia'][$i + 1]['eia']);
                    for ($e = 0; $e < $eiaSize; $e++) {
                        if (isset($params['eia'][$i + 1]['eia'][$e]) && trim($params['eia'][$i + 1]['eia'][$e]) != "") {
                            $expDate = '';
                            if (trim($params['eia'][$i + 1]['expiry'][$e]) != "") {
                                $expDate = Pt_Commons_General::dateFormat($params['eia'][$i + 1]['expiry'][$e]);
                            }

                            $dbAdapter->insert(
                                'reference_dts_eia',
                                array(
                                    'shipment_id' => $lastId,
                                    'sample_id' => ($i + 1),
                                    'eia' => $params['eia'][$i + 1]['eia'][$e],
                                    'lot' => $params['eia'][$i + 1]['lot'][$e],
                                    'exp_date' => $expDate,
                                    'od' => $params['eia'][$i + 1]['od'][$e],
                                    'cutoff' => $params['eia'][$i + 1]['cutoff'][$e],
                                    'result' => $params['eia'][$i + 1]['result'][$e]
                                )
                            );
                        }
                    }
                }
                //------------->
                // <------ Insert reference_dts_wb table
                if (isset($params['wb'][$i + 1]['wb'])) {
                    $wbSize = sizeof($params['wb'][$i + 1]['wb']);
                    for ($e = 0; $e < $wbSize; $e++) {
                        if (isset($params['wb'][$i + 1]['wb'][$e]) && trim($params['wb'][$i + 1]['wb'][$e]) != "") {
                            $expDate = '';
                            if (trim($params['wb'][$i + 1]['expiry'][$e]) != "") {
                                $expDate = Pt_Commons_General::dateFormat($params['wb'][$i + 1]['expiry'][$e]);
                            }
                            $dbAdapter->insert(
                                'reference_dts_wb',
                                array(
                                    'shipment_id' => $lastId,
                                    'sample_id' => ($i + 1),
                                    'wb' => $params['wb'][$i + 1]['wb'][$e],
                                    'lot' => $params['wb'][$i + 1]['lot'][$e],
                                    'exp_date' => $expDate,
                                    '160' => $params['wb'][$i + 1]['160'][$e],
                                    '120' => $params['wb'][$i + 1]['120'][$e],
                                    '66' => $params['wb'][$i + 1]['66'][$e],
                                    '55' => $params['wb'][$i + 1]['55'][$e],
                                    '51' => $params['wb'][$i + 1]['51'][$e],
                                    '41' => $params['wb'][$i + 1]['41'][$e],
                                    '31' => $params['wb'][$i + 1]['31'][$e],
                                    '24' => $params['wb'][$i + 1]['24'][$e],
                                    '17' => $params['wb'][$i + 1]['17'][$e],
                                    'result' => $params['wb'][$i + 1]['result'][$e]
                                )
                            );
                        }
                    }
                }
                // ------------------>
                // <------ Insert reference_dts_rapid_hiv table
                if (isset($params['rhiv'][$i + 1]['kit'])) {
                    $eiaSize = sizeof($params['rhiv'][$i + 1]['kit']);
                    for ($e = 0; $e < $eiaSize; $e++) {
                        if (isset($params['rhiv'][$i + 1]['kit'][$e]) && trim($params['rhiv'][$i + 1]['kit'][$e]) != "") {
                            $expDate = '';
                            if (trim($params['rhiv'][$i + 1]['expiry'][$e]) != "") {
                                $expDate = Pt_Commons_General::dateFormat($params['rhiv'][$i + 1]['expiry'][$e]);
                            }

                            $dbAdapter->insert(
                                'reference_dts_rapid_hiv',
                                array(
                                    'shipment_id' => $lastId,
                                    'sample_id' => ($i + 1),
                                    'testkit' => $params['rhiv'][$i + 1]['kit'][$e],
                                    'lot_no' => $params['rhiv'][$i + 1]['lot'][$e],
                                    'expiry_date' => $expDate,
                                    'result' => $params['rhiv'][$i + 1]['result'][$e]
                                )
                            );
                        }
                    }
                }
                // ------------------>

                // <------ Insert reference_dts_geenius table
                if (isset($params['geenius'][$i + 1]['expiry'])) {
                    $geeniusSize = sizeof($params['geenius'][$i + 1]['expiry']);
                    for ($e = 0; $e < $geeniusSize; $e++) {
                        if (isset($params['geenius'][$i + 1]['expiry'][$e]) && trim($params['geenius'][$i + 1]['expiry'][$e]) != "") {
                            $expDate = '';
                            if (trim($params['geenius'][$i + 1]['expiry'][$e]) != "") {
                                $expDate = Pt_Commons_General::dateFormat($params['geenius'][$i + 1]['expiry'][$e]);
                            }

                            $id = $dbAdapter->insert(
                                'reference_dts_geenius',
                                array(
                                    'shipment_id' => $lastId,
                                    'sample_id' => ($i + 1),
                                    'lot_no' => $params['geenius'][$i + 1]['lot'][$e],
                                    'expiry_date' => $expDate,
                                    'result' => $params['geenius'][$i + 1]['result'][$e]
                                )
                            );
                        }
                    }
                }
                // ------------------>
            }
        } else if ($params['schemeId'] == 'dbs') {

            for ($i = 0; $i < $size; $i++) {
                if (isset($params['score'][$i]) && $params['score'][$i] != null && $params['score'][$i] != "") {
                    $sampScore = $params['score'][$i];
                } else {
                    $sampScore = 1;
                }
                $dbAdapter->insert(
                    'reference_result_dbs',
                    array(
                        'shipment_id' => $lastId,
                        'sample_id' => ($i + 1),
                        'sample_label' => $params['sampleName'][$i],
                        'reference_result' => $params['possibleResults'][$i],
                        'control' => $params['control'][$i],
                        'mandatory' => $params['mandatory'][$i],
                        'sample_score' => $sampScore
                    )
                );
                // <------ Insert reference_dbs_eia table
                if (isset($params['eia'][$i + 1]['eia'])) {
                    $eiaSize = sizeof($params['eia'][$i + 1]['eia']);
                    for ($e = 0; $e < $eiaSize; $e++) {
                        if (isset($params['eia'][$i + 1]['eia'][$e]) && trim($params['eia'][$i + 1]['eia'][$e]) != "") {
                            $expDate = '';
                            if (trim($params['eia'][$i + 1]['expiry'][$e]) != "") {
                                $expDate = Pt_Commons_General::dateFormat($params['eia'][$i + 1]['expiry'][$e]);
                            }

                            $dbAdapter->insert(
                                'reference_dbs_eia',
                                array(
                                    'shipment_id' => $lastId,
                                    'sample_id' => ($i + 1),
                                    'eia' => $params['eia'][$i + 1]['eia'][$e],
                                    'lot' => $params['eia'][$i + 1]['lot'][$e],
                                    'exp_date' => $expDate,
                                    'od' => $params['eia'][$i + 1]['od'][$e],
                                    'cutoff' => $params['eia'][$i + 1]['cutoff'][$e]
                                )
                            );
                        }
                    }
                }
                //------------->
                // <------ Insert reference_dbs_wb table
                if (isset($params['wb'][$i + 1]['wb'])) {
                    $wbSize = sizeof($params['wb'][$i + 1]['wb']);
                    for ($e = 0; $e < $wbSize; $e++) {
                        if (isset($params['wb'][$i + 1]['wb'][$e]) && trim($params['wb'][$i + 1]['wb'][$e]) != "") {
                            $expDate = '';
                            if (trim($params['wb'][$i + 1]['expiry'][$e]) != "") {
                                $expDate = Pt_Commons_General::dateFormat($params['wb'][$i + 1]['expiry'][$e]);
                            }
                            $dbAdapter->insert(
                                'reference_dbs_wb',
                                array(
                                    'shipment_id' => $lastId,
                                    'sample_id' => ($i + 1),
                                    'wb' => $params['wb'][$i + 1]['wb'][$e],
                                    'lot' => $params['wb'][$i + 1]['lot'][$e],
                                    'exp_date' => $expDate,
                                    '160' => $params['wb'][$i + 1]['160'][$e],
                                    '120' => $params['wb'][$i + 1]['120'][$e],
                                    '66' => $params['wb'][$i + 1]['66'][$e],
                                    '55' => $params['wb'][$i + 1]['55'][$e],
                                    '51' => $params['wb'][$i + 1]['51'][$e],
                                    '41' => $params['wb'][$i + 1]['41'][$e],
                                    '31' => $params['wb'][$i + 1]['31'][$e],
                                    '24' => $params['wb'][$i + 1]['24'][$e],
                                    '17' => $params['wb'][$i + 1]['17'][$e]
                                )
                            );
                        }
                    }
                }
                // ------------------>
            }
        } else if ($params['schemeId'] == 'tb') {
            for ($i = 0; $i < $size; $i++) {
                $dbAdapter->insert(
                    'reference_result_tb',
                    array(
                        'shipment_id' => $lastId,
                        'sample_id' => ($i + 1),
                        'sample_label' => $params['sampleName'][$i],
                        'mtb_detected' => $params['mtbDetected'][$i],
                        'rif_resistance' => $params['rifResistance'][$i],
                        'probe_d' => $params['probeD'][$i],
                        'probe_c' => $params['probeC'][$i],
                        'probe_e' => $params['probeE'][$i],
                        'probe_b' => $params['probeB'][$i],
                        'spc' => $params['spc'][$i],
                        'probe_a' => $params['probeA'][$i],
                        'control' => $params['control'][$i],
                        'mandatory' => $params['mandatory'][$i],
                        'sample_score' => (isset($params['score'][$i]) ? $params['score'][$i] : 0)
                    )
                );
            }
        } else if ($params['schemeId'] == 'recency') {
            for ($i = 0; $i < $size; $i++) {
                $dbAdapter->insert(
                    'reference_result_recency',
                    array(
                        'shipment_id' => $lastId,
                        'sample_id' => ($i + 1),
                        'sample_label' => $params['sampleName'][$i],
                        'reference_result' => $params['possibleResults'][$i],
                        'reference_control_line' => $params['controlLine'][$i],
                        'reference_diagnosis_line' => $params['verificationLine'][$i],
                        'reference_longterm_line' => $params['longtermLine'][$i],
                        'control' => $params['control'][$i],
                        'mandatory' => $params['mandatory'][$i],
                        'sample_score' => 1
                    )
                );

                // <------ Insert reference_recency_assay table
                if (isset($params['assay'][$i + 1]['assay'])) {
                    $assaySize = sizeof($params['assay'][$i + 1]['assay']);
                    for ($e = 0; $e < $assaySize; $e++) {
                        if (isset($params['assay'][$i + 1]['assay'][$e]) && trim($params['assay'][$i + 1]['assay'][$e]) != "") {
                            $expDate = '';
                            if (trim($params['assay'][$i + 1]['expiry'][$e]) != "") {
                                $expDate = Pt_Commons_General::dateFormat($params['assay'][$i + 1]['expiry'][$e]);
                            }

                            $dbAdapter->insert(
                                'reference_recency_assay',
                                array(
                                    'shipment_id' => $lastId,
                                    'sample_id' => ($i + 1),
                                    'assay' => $params['assay'][$i + 1]['assay'][$e],
                                    'lot_no' => $params['assay'][$i + 1]['lot'][$e],
                                    'expiry_date' => $expDate,
                                    'result' => $params['assay'][$i + 1]['result'][$e]
                                )
                            );
                        }
                    }
                }
                // ------------------>
            }
        } else if ($params['schemeId'] == 'covid19') {
            for ($i = 0; $i < $size; $i++) {
                $dbAdapter->insert(
                    'reference_result_covid19',
                    array(
                        'shipment_id' => $lastId,
                        'sample_id' => ($i + 1),
                        'sample_label' => $params['sampleName'][$i],
                        'reference_result' => $params['possibleResults'][$i],
                        'control' => $params['control'][$i],
                        'mandatory' => $params['mandatory'][$i],
                        'sample_score' => 1
                    )
                );
            }

            // <------ Insert reference_dts_rapid_hiv table
            if (isset($params['rtype'][$i + 1]['type'])) {
                $eiaSize = sizeof($params['rtype'][$i + 1]['type']);
                for ($e = 0; $e < $eiaSize; $e++) {
                    if (isset($params['rtype'][$i + 1]['type'][$e]) && trim($params['rtype'][$i + 1]['type'][$e]) != "") {
                        $expDate = '';
                        if (trim($params['rtype'][$i + 1]['expiry'][$e]) != "") {
                            $expDate = Pt_Commons_General::dateFormat($params['rtype'][$i + 1]['expiry'][$e]);
                        }

                        $dbAdapter->insert(
                            'reference_covid19_test_type',
                            array(
                                'shipment_id' => $lastId,
                                'sample_id' => ($i + 1),
                                'test_type' => $params['rtype'][$i + 1]['type'][$e],
                                'lot_no' => $params['rtype'][$i + 1]['lot'][$e],
                                'expiry_date' => $expDate,
                                'result' => $params['rtype'][$i + 1]['result'][$e]
                            )
                        );
                    }
                }
            }

            // ------------------>
        }

        $distroService->updateDistributionStatus($params['distribution'], 'pending');
    }

    public function getShipment($sid)
    {
        $db = new Application_Model_DbTable_Shipments();
        return $db->fetchRow($db->select()->where("shipment_id = ?", $sid));
    }

    public function shipItNow($params)
    {
        $db = new Application_Model_DbTable_ShipmentParticipantMap();
        return $db->shipItNow($params);
    }

    public function removeShipment($sid)
    {
        try {

            $shipmentDb = new Application_Model_DbTable_Shipments();
            $row = $shipmentDb->fetchRow('shipment_id=' . $sid);
            $db = Zend_Db_Table_Abstract::getDefaultAdapter();
            if ($row['scheme_type'] == 'dts') {
                $db->delete('reference_dts_eia', 'shipment_id=' . $sid);
                $db->delete('reference_dts_wb', 'shipment_id=' . $sid);
                $db->delete("reference_result_dts", 'shipment_id=' . $sid);
            } else if ($row['scheme_type'] == 'dbs') {
                $db->delete('reference_dbs_eia', 'shipment_id=' . $sid);
                $db->delete('reference_dbs_wb', 'shipment_id=' . $sid);
                $db->delete("reference_result_dbs", 'shipment_id=' . $sid);
            } else if ($row['scheme_type'] == 'vl') {
                $db->delete("reference_result_vl", 'shipment_id=' . $sid);
            } else if ($row['scheme_type'] == 'eid') {
                $db->delete("reference_result_eid", 'shipment_id=' . $sid);
            }

            $shipmentParticipantMap = new Application_Model_DbTable_ShipmentParticipantMap();
            $shipmentParticipantMap->delete('shipment_id=' . $sid);



            $shipmentDb->delete('shipment_id=' . $sid);

            return "Shipment deleted.";
        } catch (Exception $e) {
            return ($e->getMessage());
            return "c Unable to delete. Please try again later or contact system admin for help";
        }
    }

    public function isShipmentEditable($shipmentId = NULL, $participantId = NULL)
    {
        $authNameSpace = new Zend_Session_Namespace('datamanagers');
        if ($authNameSpace->view_only_access == 'yes') {
            return false;
        }

        $spMap = new Application_Model_DbTable_ShipmentParticipantMap();
        return $spMap->isShipmentEditable($shipmentId, $participantId);
    }

    public function checkParticipantAccess($participantId)
    {
        $participantDb = new Application_Model_DbTable_Participants();
        return $participantDb->checkParticipantAccess($participantId);
    }

    public function getShipmentForEdit($sid)
    {

        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $query = $db->select()->from(array('s' => 'shipment'))

            ->join(array('scheme' => 'scheme_list'), 'scheme.scheme_id=s.scheme_type', array('scheme_name'))
            ->join(array('d' => 'distributions'), 'd.distribution_id = s.distribution_id', array('distribution_code', 'distribution_date'))
            ->where("s.shipment_id = ?", $sid);
        // die($query);
        $shipment = $db->fetchRow($query);


        $eia = '';
        $wb = '';
        $rhiv = '';
        $geenius = '';
        $recencyAssay = '';

        $returnArray = array();

        if ($shipment['scheme_type'] == 'dts') {
            $reference = $db->fetchAll($db->select()->from(array('s' => 'shipment'))
                ->join(array('ref' => 'reference_result_dts'), 'ref.shipment_id=s.shipment_id')
                ->where("s.shipment_id = ?", $sid));
            $schemeService = new Application_Service_Schemes();
            $possibleResults = $schemeService->getPossibleResults('dts');

            $eia = $db->fetchAll($db->select()->from('reference_dts_eia')->where("shipment_id = ?", $sid));
            $wb = $db->fetchAll($db->select()->from('reference_dts_wb')->where("shipment_id = ?", $sid));
            $rhiv = $db->fetchAll($db->select()->from('reference_dts_rapid_hiv')->where("shipment_id = ?", $sid));
            $geenius = $db->fetchAll($db->select()->from('reference_dts_geenius')->where("shipment_id = ?", $sid));
            $returnArray['eia'] = $eia;
            $returnArray['wb'] = $wb;
            $returnArray['rhiv'] = $rhiv;
            $returnArray['geenius'] = $geenius;
        } else if ($shipment['scheme_type'] == 'dbs') {

            $reference = $db->fetchAll($db->select()->from(array('s' => 'shipment'))
                ->join(array('ref' => 'reference_result_dbs'), 'ref.shipment_id=s.shipment_id')
                ->where("s.shipment_id = ?", $sid));
            $schemeService = new Application_Service_Schemes();
            $possibleResults = $schemeService->getPossibleResults('dbs');

            $eia = $db->fetchAll($db->select()->from('reference_dbs_eia')->where("shipment_id = ?", $sid));
            $wb = $db->fetchAll($db->select()->from('reference_dbs_wb')->where("shipment_id = ?", $sid));
            $returnArray['eia'] = $eia;
            $returnArray['wb'] = $wb;
        } else if ($shipment['scheme_type'] == 'eid') {
            $reference = $db->fetchAll($db->select()->from(array('s' => 'shipment'))
                ->join(array('ref' => 'reference_result_eid'), 'ref.shipment_id=s.shipment_id')
                ->where("s.shipment_id = ?", $sid));
            $schemeService = new Application_Service_Schemes();
            $possibleResults = $schemeService->getPossibleResults('eid');
        } else if ($shipment['scheme_type'] == 'vl') {
            $reference = $db->fetchAll($db->select()->from(array('s' => 'shipment'))
                ->join(array('ref' => 'reference_result_vl'), 'ref.shipment_id=s.shipment_id')
                ->where("s.shipment_id = ?", $sid));
            $possibleResults = "";

            $returnArray['vlReferenceMethods'] = $db->fetchAll($db->select()->from('reference_vl_methods')->where("shipment_id = ?", $sid));
        } else if ($shipment['scheme_type'] == 'tb') {
            $reference = $db->fetchAll($db->select()->from(array('s' => 'shipment'))
                ->join(array('ref' => 'reference_result_tb'), 'ref.shipment_id=s.shipment_id')
                ->where("s.shipment_id = ?", $sid));
            $possibleResults = "";
        } else if ($shipment['scheme_type'] == 'recency') {
            $recencyAssay = $db->fetchAll($db->select()->from('reference_recency_assay')->where("shipment_id = ?", $sid));
            $returnArray['recencyAssay'] = $recencyAssay;
            $reference = $db->fetchAll($db->select()->from(array('s' => 'shipment'))
                ->join(array('ref' => 'reference_result_recency'), 'ref.shipment_id=s.shipment_id')
                ->where("s.shipment_id = ?", $sid));
            $schemeService = new Application_Service_Schemes();
            $possibleResults = $schemeService->getPossibleResults('recency');
        } else if ($shipment['scheme_type'] == 'covid19') {
            $reference = $db->fetchAll($db->select()->from(array('s' => 'shipment'))
                ->join(array('ref' => 'reference_result_covid19'), 'ref.shipment_id=s.shipment_id')
                ->where("s.shipment_id = ?", $sid));
            $schemeService = new Application_Service_Schemes();
            $possibleResults = $schemeService->getPossibleResults('covid19');
        } else {
            return false;
        }

        $returnArray['shipment'] = $shipment;
        $returnArray['reference'] = $reference;
        $returnArray['possibleResults'] = $possibleResults;

        return $returnArray;
    }

    public function updateShipment($params)
    {
        // Zend_Debug::dump($params);die;

        $dbAdapter = Zend_Db_Table_Abstract::getDefaultAdapter();
        $shipmentRow = $dbAdapter->fetchRow($dbAdapter->select()->from(array('s' => 'shipment'))->where('shipment_id = ' . $params['shipmentId']));
        $file = APPLICATION_PATH . DIRECTORY_SEPARATOR . "configs" . DIRECTORY_SEPARATOR . "config.ini";
        $config = new Zend_Config_Ini($file, null, array('allowModifications' => true));
        $sec = APPLICATION_ENV;
        $scheme = $shipmentRow['scheme_type'];

        $size = count($params['sampleName']);


        $controlCount = 0;
        foreach ($params['control'] as $control) {
            if ($control == 1) {
                $controlCount += 1;
            }
        }

        //$size = $size - $controlCount;

        if ($scheme == 'eid') {
            $dbAdapter->delete('reference_result_eid', 'shipment_id = ' . $params['shipmentId']);
            for ($i = 0; $i < $size; $i++) {
                $dbAdapter->insert(
                    'reference_result_eid',
                    array(
                        'shipment_id'               => $params['shipmentId'],
                        'sample_id'                 => ($i + 1),
                        'sample_label'              => $params['sampleName'][$i],
                        'reference_result'          => $params['possibleResults'][$i],
                        'reference_hiv_ct_od'       => $params['hivCtOd'][$i],
                        'reference_ic_qs'           => $params['icQs'][$i],
                        'control'                   => $params['control'][$i],
                        'mandatory'                 => $params['mandatory'][$i],
                        'sample_score'              => 1
                    )
                );
            }
        } else if ($scheme == 'vl') {
            //var_dump($params['vlRef']);die;
            $dbAdapter->delete('reference_result_vl', 'shipment_id = ' . $params['shipmentId']);
            $dbAdapter->delete('reference_vl_methods', 'shipment_id = ' . $params['shipmentId']);
            for ($i = 0; $i < $size; $i++) {
                $dbAdapter->insert(
                    'reference_result_vl',
                    array(
                        'shipment_id' => $params['shipmentId'],
                        'sample_id' => ($i + 1),
                        'sample_label' => $params['sampleName'][$i],
                        //'reference_result' => $params['vlResult'][$i],
                        'control' => $params['control'][$i],
                        'mandatory' => $params['mandatory'][$i],
                        'sample_score' => 1
                    )
                );

                if (isset($params['vlRef'][$i + 1]['assay'])) {
                    $assaySize = count($params['vlRef'][$i + 1]['assay']);;
                    for ($e = 0; $e < $assaySize; $e++) {
                        if (trim($params['vlRef'][$i + 1]['assay'][$e]) != "" && trim($params['vlRef'][$i + 1]['value'][$e]) != "") {
                            $dbAdapter->insert(
                                'reference_vl_methods',
                                array(
                                    'shipment_id' => $params['shipmentId'],
                                    'sample_id' => ($i + 1),
                                    'assay' => $params['vlRef'][$i + 1]['assay'][$e],
                                    'value' => $params['vlRef'][$i + 1]['value'][$e]
                                )
                            );
                        }
                    }
                }
            }
        } else if ($scheme == 'tb') {
            $dbAdapter->delete('reference_result_tb', 'shipment_id = ' . $params['shipmentId']);
            for ($i = 0; $i < $size; $i++) {
                $dbAdapter->insert(
                    'reference_result_tb',
                    array(
                        'shipment_id' => $params['shipmentId'],
                        'sample_id' => ($i + 1),
                        'sample_label' => $params['sampleName'][$i],
                        'mtb_detected' => $params['mtbDetected'][$i],
                        'rif_resistance' => $params['rifResistance'][$i],
                        'probe_d' => $params['probeD'][$i],
                        'probe_c' => $params['probeC'][$i],
                        'probe_e' => $params['probeE'][$i],
                        'probe_b' => $params['probeB'][$i],
                        'spc' => $params['spc'][$i],
                        'probe_a' => $params['probeA'][$i],
                        'control' => $params['control'][$i],
                        'mandatory' => $params['mandatory'][$i],
                        'sample_score' => (isset($params['score'][$i]) ? $params['score'][$i] : 0)
                    )
                );
            }
        } else if ($scheme == 'dts') {
            $dbAdapter->delete('reference_result_dts', 'shipment_id = ' . $params['shipmentId']);
            $dbAdapter->delete('reference_dts_eia', 'shipment_id = ' . $params['shipmentId']);
            $dbAdapter->delete('reference_dts_wb', 'shipment_id = ' . $params['shipmentId']);
            $dbAdapter->delete('reference_dts_rapid_hiv', 'shipment_id = ' . $params['shipmentId']);
            $dbAdapter->delete('reference_dts_geenius', 'shipment_id = ' . $params['shipmentId']);
            /* Zend_Debug::dump($params);
            die; */
            for ($i = 0; $i < $size; $i++) {
                $refResulTDTSData = array(
                    'shipment_id' => $params['shipmentId'],
                    'sample_id' => ($i + 1),
                    'sample_label' => $params['sampleName'][$i],
                    'reference_result' => $params['possibleResults'][$i],
                    'syphilis_reference_result' => (isset($params['possibleSyphilisResults'][$i]) && !empty($params['possibleSyphilisResults'][$i])) ? $params['possibleSyphilisResults'][$i] : null,
                    'dts_rtri_reference_result' => (isset($params['possibleRTRIResults'][$i]) && !empty($params['possibleRTRIResults'][$i])) ? $params['possibleRTRIResults'][$i] : null,
                    'control' => $params['control'][$i],
                    'mandatory' => $params['mandatory'][$i],
                    // 'sample_score' => (isset($params['score'][$i]) && !empty($params['score'][$i])) ? $params['score'][$i] : null
                );
                $dbAdapter->insert('reference_result_dts', $refResulTDTSData);
                if (isset($params['eia'][$i + 1]['eia'])) {
                    $eiaSize = sizeof($params['eia'][$i + 1]['eia']);
                    for ($e = 0; $e < $eiaSize; $e++) {
                        if (isset($params['eia'][$i + 1]['eia'][$e]) && trim($params['eia'][$i + 1]['eia'][$e]) != "") {
                            $expDate = '';
                            if (trim($params['eia'][$i + 1]['expiry'][$e]) != "") {
                                $expDate = Pt_Commons_General::dateFormat($params['eia'][$i + 1]['expiry'][$e]);
                            }
                            $dbAdapter->insert(
                                'reference_dts_eia',
                                array(
                                    'shipment_id' => $params['shipmentId'],
                                    'sample_id' => ($i + 1),
                                    'eia' => $params['eia'][$i + 1]['eia'][$e],
                                    'lot' => $params['eia'][$i + 1]['lot'][$e],
                                    'exp_date' => $expDate,
                                    'od' => $params['eia'][$i + 1]['od'][$e],
                                    'cutoff' => $params['eia'][$i + 1]['cutoff'][$e]
                                )
                            );
                        }
                    }
                }

                // <------ Insert reference_dbs_wb table
                if (isset($params['wb'][$i + 1]['wb'])) {
                    $wbSize = sizeof($params['wb'][$i + 1]['wb']);
                    for ($e = 0; $e < $wbSize; $e++) {
                        if (isset($params['wb'][$i + 1]['wb'][$e]) && trim($params['wb'][$i + 1]['wb'][$e]) != "") {
                            $expDate = '';
                            if (trim($params['wb'][$i + 1]['expiry'][$e]) != "") {
                                $expDate = Pt_Commons_General::dateFormat($params['wb'][$i + 1]['expiry'][$e]);
                            }
                            $dbAdapter->insert(
                                'reference_dts_wb',
                                array(
                                    'shipment_id' => $params['shipmentId'],
                                    'sample_id' => ($i + 1),
                                    'wb' => $params['wb'][$i + 1]['wb'][$e],
                                    'lot' => $params['wb'][$i + 1]['lot'][$e],
                                    'exp_date' => $expDate,
                                    '160' => $params['wb'][$i + 1]['160'][$e],
                                    '120' => $params['wb'][$i + 1]['120'][$e],
                                    '66' => $params['wb'][$i + 1]['66'][$e],
                                    '55' => $params['wb'][$i + 1]['55'][$e],
                                    '51' => $params['wb'][$i + 1]['51'][$e],
                                    '41' => $params['wb'][$i + 1]['41'][$e],
                                    '31' => $params['wb'][$i + 1]['31'][$e],
                                    '24' => $params['wb'][$i + 1]['24'][$e],
                                    '17' => $params['wb'][$i + 1]['17'][$e],
                                    'result' => $params['wb'][$i + 1]['result'][$e]
                                )
                            );
                        }
                    }
                }
                // ------------------>
                // <------ Insert reference_dts_rapid_hiv table
                if (isset($params['rhiv'][$i + 1]['kit'])) {
                    $eiaSize = sizeof($params['rhiv'][$i + 1]['kit']);
                    for ($e = 0; $e < $eiaSize; $e++) {
                        if (isset($params['rhiv'][$i + 1]['kit'][$e]) && trim($params['rhiv'][$i + 1]['kit'][$e]) != "") {
                            $expDate = '';
                            if (trim($params['rhiv'][$i + 1]['expiry'][$e]) != "") {
                                $expDate = Pt_Commons_General::dateFormat($params['rhiv'][$i + 1]['expiry'][$e]);
                            }

                            $dbAdapter->insert(
                                'reference_dts_rapid_hiv',
                                array(
                                    'shipment_id' => $params['shipmentId'],
                                    'sample_id' => ($i + 1),
                                    'testkit' => $params['rhiv'][$i + 1]['kit'][$e],
                                    'lot_no' => $params['rhiv'][$i + 1]['lot'][$e],
                                    'expiry_date' => $expDate,
                                    'result' => $params['rhiv'][$i + 1]['result'][$e]
                                )
                            );
                        }
                    }
                }

                // ------------------>

                // <------ Insert reference_dts_geenius table
                if (isset($params['geenius'][$i + 1]['expiry'])) {
                    $geeniusSize = sizeof($params['geenius'][$i + 1]['expiry']);
                    for ($e = 0; $e < $geeniusSize; $e++) {
                        if (isset($params['geenius'][$i + 1]['expiry'][$e]) && trim($params['geenius'][$i + 1]['expiry'][$e]) != "") {
                            $expDate = '';
                            if (trim($params['geenius'][$i + 1]['expiry'][$e]) != "") {
                                $expDate = Pt_Commons_General::dateFormat($params['geenius'][$i + 1]['expiry'][$e]);
                            }

                            $id = $dbAdapter->insert(
                                'reference_dts_geenius',
                                array(
                                    'shipment_id' => $params['shipmentId'],
                                    'sample_id' => ($i + 1),
                                    'lot_no' => $params['geenius'][$i + 1]['lot'][$e],
                                    'expiry_date' => $expDate,
                                    'result' => $params['geenius'][$i + 1]['result'][$e]
                                )
                            );
                        }
                    }
                }
                // ------------------>
            }
        } else if ($scheme == 'covid19') {
            $dbAdapter->delete('reference_result_covid19', 'shipment_id = ' . $params['shipmentId']);
            for ($i = 0; $i < $size; $i++) {
                $dbAdapter->insert(
                    'reference_result_covid19',
                    array(
                        'shipment_id' => $params['shipmentId'],
                        'sample_id' => ($i + 1),
                        'sample_label' => $params['sampleName'][$i],
                        'reference_result' => $params['possibleResults'][$i],
                        'control' => $params['control'][$i],
                        'mandatory' => $params['mandatory'][$i],
                        'sample_score' => (isset($params['score'][$i]) && !empty($params['score'][$i])) ? $params['score'][$i] : null
                    )
                );
            }
        } else if ($scheme == 'dbs') {
            $dbAdapter->delete('reference_result_dbs', 'shipment_id = ' . $params['shipmentId']);
            $dbAdapter->delete('reference_dbs_eia', 'shipment_id = ' . $params['shipmentId']);
            $dbAdapter->delete('reference_dbs_wb', 'shipment_id = ' . $params['shipmentId']);
            for ($i = 0; $i < $size; $i++) {
                $dbAdapter->insert(
                    'reference_result_dbs',
                    array(
                        'shipment_id' => $params['shipmentId'],
                        'sample_id' => ($i + 1),
                        'sample_label' => $params['sampleName'][$i],
                        'reference_result' => $params['possibleResults'][$i],
                        'control' => $params['control'][$i],
                        'mandatory' => $params['mandatory'][$i],
                        'sample_score' => (isset($params['score'][$i]) && !empty($params['score'][$i])) ? $params['score'][$i] : null
                    )
                );
                if (isset($params['eia'][$i + 1]['eia'])) {
                    $eiaSize = sizeof($params['eia'][$i + 1]['eia']);
                    for ($e = 0; $e < $eiaSize; $e++) {
                        if (isset($params['eia'][$i + 1]['eia'][$e]) && trim($params['eia'][$i + 1]['eia'][$e]) != "") {
                            $expDate = '';
                            if (trim($params['eia'][$i + 1]['expiry'][$e]) != "") {
                                $expDate = Pt_Commons_General::dateFormat($params['eia'][$i + 1]['expiry'][$e]);
                            }
                            $dbAdapter->insert(
                                'reference_dbs_eia',
                                array(
                                    'shipment_id' => $params['shipmentId'],
                                    'sample_id' => ($i + 1),
                                    'eia' => $params['eia'][$i + 1]['eia'][$e],
                                    'lot' => $params['eia'][$i + 1]['lot'][$e],
                                    'exp_date' => $expDate,
                                    'od' => $params['eia'][$i + 1]['od'][$e],
                                    'cutoff' => $params['eia'][$i + 1]['cutoff'][$e]
                                )
                            );
                        }
                    }
                }
                // <------ Insert reference_dbs_wb table
                if (isset($params['wb'][$i + 1]['wb'])) {
                    $wbSize = sizeof($params['wb'][$i + 1]['wb']);
                    for ($e = 0; $e < $wbSize; $e++) {
                        if (isset($params['wb'][$i + 1]['wb'][$e]) && trim($params['wb'][$i + 1]['wb'][$e]) != "") {
                            $expDate = '';
                            if (trim($params['wb'][$i + 1]['expiry'][$e]) != "") {
                                $expDate = Pt_Commons_General::dateFormat($params['wb'][$i + 1]['expiry'][$e]);
                            }
                            $dbAdapter->insert(
                                'reference_dbs_wb',
                                array(
                                    'shipment_id' => $params['shipmentId'],
                                    'sample_id' => ($i + 1),
                                    'wb' => $params['wb'][$i + 1]['wb'][$e],
                                    'lot' => $params['wb'][$i + 1]['lot'][$e],
                                    'exp_date' => $expDate,
                                    '160' => $params['wb'][$i + 1]['160'][$e],
                                    '120' => $params['wb'][$i + 1]['120'][$e],
                                    '66' => $params['wb'][$i + 1]['66'][$e],
                                    '55' => $params['wb'][$i + 1]['55'][$e],
                                    '51' => $params['wb'][$i + 1]['51'][$e],
                                    '41' => $params['wb'][$i + 1]['41'][$e],
                                    '31' => $params['wb'][$i + 1]['31'][$e],
                                    '24' => $params['wb'][$i + 1]['24'][$e],
                                    '17' => $params['wb'][$i + 1]['17'][$e]
                                )
                            );
                        }
                    }
                }
                // ------------------>
            }
        } else if ($scheme == 'recency') {
            $dbAdapter->delete('reference_result_recency', 'shipment_id = ' . $params['shipmentId']);
            $dbAdapter->delete('reference_recency_assay', 'shipment_id = ' . $params['shipmentId']);
            for ($i = 0; $i < $size; $i++) {
                $dbAdapter->insert(
                    'reference_result_recency',
                    array(
                        'shipment_id' => $params['shipmentId'],
                        'sample_id' => ($i + 1),
                        'sample_label' => $params['sampleName'][$i],
                        'reference_result' => $params['possibleResults'][$i],
                        'reference_control_line' => $params['controlLine'][$i],
                        'reference_diagnosis_line' => $params['verificationLine'][$i],
                        'reference_longterm_line' => $params['longtermLine'][$i],
                        'control' => $params['control'][$i],
                        'mandatory' => $params['mandatory'][$i],
                        'sample_score' => 1
                    )
                );
                // <------ Insert reference_recency_assay table
                if (isset($params['assay'][$i + 1]['assay'])) {
                    $assaySize = sizeof($params['assay'][$i + 1]['assay']);
                    for ($e = 0; $e < $assaySize; $e++) {
                        if (isset($params['assay'][$i + 1]['assay'][$e]) && trim($params['assay'][$i + 1]['assay'][$e]) != "") {
                            $expDate = '';
                            if (trim($params['assay'][$i + 1]['expiry'][$e]) != "") {
                                $expDate = Pt_Commons_General::dateFormat($params['assay'][$i + 1]['expiry'][$e]);
                            }

                            $dbAdapter->insert(
                                'reference_recency_assay',
                                array(
                                    'shipment_id' => $params['shipmentId'],
                                    'sample_id' => ($i + 1),
                                    'assay' => $params['assay'][$i + 1]['assay'][$e],
                                    'lot_no' => $params['assay'][$i + 1]['lot'][$e],
                                    'expiry_date' => $expDate,
                                    'result' => $params['assay'][$i + 1]['result'][$e]
                                )
                            );
                        }
                    }
                }
                // ------------------>
            }
        }
        $shipmentAttributes = array();

        if (isset($params['dtsSampleType']) && !empty($params['dtsSampleType'])) {
            $shipmentAttributes['sampleType'] = $params['dtsSampleType'];
        }
        if (isset($params['screeningTest']) && !empty($params['screeningTest'])) {
            $shipmentAttributes['screeningTest'] = $params['screeningTest'];
        }
        if (isset($params['enableSyphilis']) && !empty($params['enableSyphilis'])) {
            $shipmentAttributes['enableSyphilis'] = $params['enableSyphilis'];
        }
        if (isset($config->$sec->evaluation->dts->dtsSchemeType) && $config->$sec->evaluation->dts->dtsSchemeType != "") {
            $shipmentAttributes['dtsSchemeType'] = $config->$sec->evaluation->dts->dtsSchemeType;
        } else {
            $shipmentAttributes['dtsSchemeType'] = "standard";
        }
        if (isset($params['enableRtri']) && !empty($params['enableRtri'])) {
            $shipmentAttributes['enableRtri'] = $params['enableRtri'];
        }
        /* Method Of Evaluation for vl form */
        if (isset($params['methodOfEvaluation']) && !empty($params['methodOfEvaluation'])) {
            $shipmentAttributes['methodOfEvaluation'] = $params['methodOfEvaluation'];
        }
        $dbAdapter->update(
            'shipment',
            array(
                'number_of_samples'     => $size - $controlCount,
                'shipment_attributes'   => empty($shipmentAttributes) ? null : json_encode($shipmentAttributes),
                'number_of_controls'    => $controlCount,
                'shipment_code'         => $params['shipmentCode'],
                'pt_co_ordinator_name'  => $params['PtCoOrdinatorName'],
                'lastdate_response'     => Pt_Commons_General::dateFormat($params['lastDate'])
            ),
            'shipment_id = ' . $params['shipmentId']
        );
    }

    public function getShipmentOverview($parameters)
    {
        $shipmentDb = new Application_Model_DbTable_Shipments();
        return $shipmentDb->getShipmentOverviewDetails($parameters);
    }

    public function getShipmentCurrent($parameters)
    {
        $shipmentDb = new Application_Model_DbTable_Shipments();
        return $shipmentDb->getShipmentCurrentDetails($parameters);
    }

    public function getShipmentDefault($parameters)
    {
        $shipmentDb = new Application_Model_DbTable_Shipments();
        return $shipmentDb->getShipmentDefaultDetails($parameters);
    }

    public function getShipmentAll($parameters)
    {
        $shipmentDb = new Application_Model_DbTable_Shipments();
        return $shipmentDb->getShipmentAllDetails($parameters);
    }

    public function getindividualReport($parameters)
    {
        $shipmentDb = new Application_Model_DbTable_Shipments();
        return $shipmentDb->getindividualReportDetails($parameters);
    }

    public function getCorrectiveActionReport($parameters)
    {
        $shipmentDb = new Application_Model_DbTable_Shipments();
        return $shipmentDb->fetchCorrectiveActionReport($parameters);
    }
    public function getSummaryReport($parameters)
    {
        $shipmentDb = new Application_Model_DbTable_Shipments();
        return $shipmentDb->getSummaryReportDetails($parameters);
    }

    public function getShipmentInReports($distributionId)
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $sql = $db->select()->from(array('s' => 'shipment', array('shipment_id', 'shipment_code', 'status', 'number_of_samples')))
            ->join(array('d' => 'distributions'), 'd.distribution_id=s.distribution_id', array('distribution_code', 'distribution_date'))
            ->join(array('sp' => 'shipment_participant_map'), 'sp.shipment_id=s.shipment_id', array('report_generated', 'participant_count' => new Zend_Db_Expr('count("participant_id")'), 'reported_count' => new Zend_Db_Expr("SUM((shipment_test_date not like '0000-00-00' AND shipment_test_date is NOT NULL AND shipment_test_date not like '') OR is_pt_test_not_performed ='yes')"), 'number_passed' => new Zend_Db_Expr("SUM(final_result = 1)")))
            ->join(array('sl' => 'scheme_list'), 'sl.scheme_id=s.scheme_type', array('scheme_name'))
            ->joinLeft(array('rr' => 'r_results'), 'sp.final_result=rr.result_id')
            ->where("s.distribution_id = ?", $distributionId)
            ->group('s.shipment_id');



        return $db->fetchAll($sql);
    }

    public function getParticipantCountBasedOnScheme()
    {
        $resultArray = array();
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();

        $sQuery = $db->select()->from(array('s' => 'shipment'), array())
            ->join(array('sp' => 'shipment_participant_map'), 'sp.shipment_id=s.shipment_id', array('participantCount' => new Zend_Db_Expr("count(sp.participant_id)")))
            ->join(array('sl' => 'scheme_list'), 'sl.scheme_id=s.scheme_type', array('SCHEME' => 'sl.scheme_id'))
            ->where("s.scheme_type = sl.scheme_id")
            ->where("s.status!='pending'")
            ->group('s.scheme_type')
            ->order("sl.scheme_id");
        $resultArray = $db->fetchAll($sQuery);
        //Zend_Debug::dump($resultArray);die;
        return $resultArray;
    }

    public function getParticipantCountBasedOnShipment()
    {
        $resultArray = array();
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();

        $sQuery = $db->select()->from(array('s' => 'shipment'), array('s.shipment_code', 's.scheme_type', 's.lastdate_response'))
            ->join(array('sp' => 'shipment_participant_map'), 'sp.shipment_id=s.shipment_id', array('participantCount' => new Zend_Db_Expr("count(sp.participant_id)"), 'receivedCount' => new Zend_Db_Expr("SUM(sp.shipment_test_date not like '0000-00-00')")))
            ->where("s.status='shipped' OR s.status='evaluated' OR s.status='finalized'")
            //->where("YEAR(s.shipment_date) = YEAR(CURDATE())")
            ->where("s.shipment_date > DATE_SUB(now(), INTERVAL 24 MONTH)")
            ->group('s.shipment_id')
            ->order("s.shipment_id");
        $resultArray = $db->fetchAll($sQuery);
        //echo($sQuery);die;
        return $resultArray;
    }

    public function removeShipmentParticipant($mapId, $sId = "")
    {

        try {
            $db = Zend_Db_Table_Abstract::getDefaultAdapter();
            $responseTable = array('response_result_dbs', 'response_result_dts', 'response_result_eid', 'response_result_recency', 'response_result_tb', 'response_result_vl');
            foreach ($responseTable as $response) {
                //   $shipment = $db->fetchRow($db->select()->from($response, array('shipment_map_id'))->where('shipment_map_id =?', $mapId)->where('sample_id =?', $sId));
                //   if ($shipment) {
                $db->delete($response, "shipment_map_id = " . $mapId);
                //   }
            }
            return  $db->delete('shipment_participant_map', "map_id = " . $mapId);
        } catch (Exception $e) {
            return ($e->getMessage());
            return "Unable to delete. Please try again later or contact system admin for help";
        }
    }

    public function addEnrollements($params)
    {
        $db = new Application_Model_DbTable_ShipmentParticipantMap();
        return $db->addEnrollementDetails($params);
    }

    public function getShipmentCode($sid, $count = null)
    {
        $code = '';
        $month = date("m");
        $year = date("y");

        if ($count == null) {
            $db = Zend_Db_Table_Abstract::getDefaultAdapter();
            $sQuery = $db->select()->from('shipment')->where("scheme_type = ?", $sid)->where("MONTH(DATE(created_on_admin))= ?", $month);
            $resultArray = $db->fetchAll($sQuery);

            $count = count($resultArray) + 1;
        }

        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $code = '';
        if ($sid == 'dts') {
            $code = 'DTS' . $month . $year . '-' . $count;
        } else if ($sid == 'vl') {
            $code = 'VL' . $month . $year . '-' . $count;
        } else if ($sid == 'eid') {
            $code = 'EID' . $month . $year . '-' . $count;
        } else if ($sid == 'dbs') {
            $code = 'DBS' . $month . $year . '-' . $count;
        } else if ($sid == 'tb') {
            $code = 'TB' . $month . $year . '-' . $count;
        } else if ($sid == 'recency') {
            $code = 'REC' . $month . $year . '-' . $count;
        } else if ($sid == 'covid19') {
            $code = 'C19' . $month . $year . '-' . $count;
        }
        $sQuery = $db->select()->from('shipment')->where("shipment_code = ?", $code);
        $resultArray = $db->fetchAll($sQuery);
        if (count($resultArray) > 0) {
            // looks like this shipment code exists so let us try again by
            // incrementing the count
            $count++;
            $code = $this->getShipmentCode($sid, $count);
        }
        return $code;
    }

    public function getShipmentReport($parameters)
    {
        $shipmentDb = new Application_Model_DbTable_Shipments();
        return $shipmentDb->getShipmentReportDetails($parameters);
    }
    public function sendShipmentMailAlertToParticipants($sid)
    {
        $commonServices = new Application_Service_Common();
        $general = new Pt_Commons_General();
        $newShipmentMailContent = $commonServices->getEmailTemplate('new_shipment');
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $return = 0;
        $sQuery = $db->select()->from(array('sp' => 'shipment_participant_map'), array('sp.participant_id', 'sp.map_id', 'sp.new_shipment_mail_count'))
            ->join(array('s' => 'shipment'), 's.shipment_id=sp.shipment_id', array('s.shipment_code', 's.shipment_code'))
            ->join(array('d' => 'distributions'), 'd.distribution_id = s.distribution_id', array('distribution_code', 'distribution_date'))
            ->join(array('p' => 'participant'), 'p.participant_id=sp.participant_id', array('p.email', 'participantName' => new Zend_Db_Expr("GROUP_CONCAT(DISTINCT p.first_name,\" \",p.last_name ORDER BY p.first_name SEPARATOR ', ')")))
            ->join(array('sl' => 'scheme_list'), 'sl.scheme_id=s.scheme_type', array('SCHEME' => 'sl.scheme_name'))
            ->where("sp.shipment_id = ?", $sid)
            ->group("p.participant_id");
        // echo $sQuery;
        // die;
        $participantEmails = $db->fetchAll($sQuery);

        foreach ($participantEmails as $participantDetails) {
            if ($participantDetails['email'] != '') {
                $surveyDate = $general->humanDateFormat($participantDetails['distribution_date']);
                $search = array('##NAME##', '##SHIPCODE##', '##SHIPTYPE##', '##SURVEYCODE##', '##SURVEYDATE##',);
                $replace = array($participantDetails['participantName'], $participantDetails['shipment_code'], $participantDetails['SCHEME'], $participantDetails['distribution_code'], $surveyDate);
                $content = $newShipmentMailContent['mail_content'];
                $message = str_replace($search, $replace, $content);
                // $subject = $newShipmentMailContent['mail_subject'];
                $subject = str_replace($search, $replace, $newShipmentMailContent['mail_subject']);
                $message = $message;
                $fromEmail = $newShipmentMailContent['mail_from'];
                $fromFullName = $newShipmentMailContent['from_name'];
                $toEmail = $participantDetails['email'];
                $cc = $newShipmentMailContent['mail_cc'];
                $bcc = $newShipmentMailContent['mail_bcc'];
                $commonServices->insertTempMail($toEmail, $cc, $bcc, $subject, $message, $fromEmail, $fromFullName);
                $count = $participantDetails['new_shipment_mail_count'] + 1;
                $return = $db->update('shipment_participant_map', array('last_new_shipment_mailed_on' => new Zend_Db_Expr('now()'), 'new_shipment_mail_count' => $count), 'map_id = ' . $participantDetails['map_id']);
            }
        }
        return $return;
    }
    public function getShipmentNotParticipated($sid)
    {

        $commonServices = new Application_Service_Common();
        $general = new Pt_Commons_General();
        $notParticipatedMailContent = $commonServices->getEmailTemplate('not_participated');
        $pushContent = $commonServices->getPushTemplateByPurpose('not-participated');
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $return = 0;
        $sQuery = $db->select()->from(array('sp' => 'shipment_participant_map'), array('sp.participant_id', 'sp.map_id', 'sp.last_not_participated_mail_count', 'sp.final_result'))
            ->joinLeft(array('s' => 'shipment'), 's.shipment_id=sp.shipment_id', array('s.shipment_code', 's.shipment_code'))
            ->joinLeft(array('d' => 'distributions'), 'd.distribution_id = s.distribution_id', array('distribution_code', 'distribution_date'))
            ->joinLeft(array('p' => 'participant'), 'p.participant_id=sp.participant_id', array('p.email', 'participantName' => new Zend_Db_Expr("GROUP_CONCAT(DISTINCT p.first_name,\" \",p.last_name ORDER BY p.first_name SEPARATOR ', ')")))
            ->joinLeft(array('sl' => 'scheme_list'), 'sl.scheme_id=s.scheme_type', array('SCHEME' => 'sl.scheme_name'))
            ->where("(sp.shipment_test_date = '0000-00-00' OR sp.shipment_test_date IS NULL OR sp.shipment_test_date like '')")
            ->where("sp.shipment_id = ?", $sid)
            ->group("sp.participant_id");
        $participantEmails = $db->fetchAll($sQuery);
        foreach ($participantEmails as $participantDetails) {
            if ($participantDetails['email'] != '') {
                $surveyDate = $general->humanDateFormat($participantDetails['distribution_date']);
                $search = array('##NAME##', '##SHIPCODE##', '##SHIPTYPE##', '##SURVEYCODE##', '##SURVEYDATE##',);
                $replace = array($participantDetails['participantName'], $participantDetails['shipment_code'], $participantDetails['SCHEME'], $participantDetails['distribution_code'], $surveyDate);
                $content = $notParticipatedMailContent['mail_content'];
                $message = str_replace($search, $replace, $content);
                // $subject = $notParticipatedMailContent['mail_subject'];
                $subject = str_replace($search, $replace, $notParticipatedMailContent['mail_subject']);
                $message = $message;
                $fromEmail = $notParticipatedMailContent['mail_from'];
                $fromFullName = $notParticipatedMailContent['from_name'];
                $toEmail = $participantDetails['email'];
                $cc = $notParticipatedMailContent['mail_cc'];
                $bcc = $notParticipatedMailContent['mail_bcc'];
                $commonServices->insertTempMail($toEmail, $cc, $bcc, $subject, $message, $fromEmail, $fromFullName);
                $count = $participantDetails['last_not_participated_mail_count'] + 1;
                $return = $db->update('shipment_participant_map', array('last_not_participated_mailed_on' => new Zend_Db_Expr('now()'), 'last_not_participated_mail_count' => $count), 'map_id = ' . $participantDetails['map_id']);
            }

            // Push notification section
            $pushQuery = $db->select()
                ->from(array('s' => 'shipment'), array('s.shipment_code', 's.shipment_code'))
                ->join(array('sp' => 'shipment_participant_map'), 'sp.shipment_id=s.shipment_id', array('sp.shipment_id', 'sp.participant_id', 'sp.map_id', 'sp.last_not_participated_mail_count', 'sp.final_result'))
                ->joinLeft(array('d' => 'distributions'), 'd.distribution_id = s.distribution_id', array('distribution_code', 'distribution_date'))
                ->joinLeft(array('p' => 'participant'), 'p.participant_id=sp.participant_id', array('p.email', 'participantName' => new Zend_Db_Expr("GROUP_CONCAT(DISTINCT p.first_name,\" \",p.last_name ORDER BY p.first_name SEPARATOR ', ')")))
                ->joinLeft(array('sl' => 'scheme_list'), 'sl.scheme_id=s.scheme_type', array('SCHEME' => 'sl.scheme_name'))
                ->join(array('pmm' => 'participant_manager_map'), 'pmm.participant_id=sp.participant_id', array('dm_id'))
                ->join(array('dm' => 'data_manager'), 'pmm.dm_id=dm.dm_id', array('primary_email', 'push_notify_token'))
                ->where("(sp.shipment_test_date = '0000-00-00' OR sp.shipment_test_date IS NULL OR sp.shipment_test_date like '')")
                ->where("s.shipment_id=?", $sid)
                ->group('dm.dm_id');
            // die($pushQuery);
            $dmDetails = $db->fetchAll($pushQuery);
            // Zend_Debug::dump($dmDetails);die;
            if (count($dmDetails) > 0) {
                foreach ($dmDetails as $dm) {
                    $surveyDate = $general->humanDateFormat($dm['distribution_date']);
                    $search = array('##NAME##', '##SHIPCODE##', '##SHIPTYPE##', '##SURVEYCODE##', '##SURVEYDATE##',);
                    $replace = array($dm['participantName'], $dm['shipment_code'], $dm['SCHEME'], $participantDetails['distribution_code'], $surveyDate);
                    $title = str_replace($search, $replace, $pushContent['notify_title']);
                    $msgBody = str_replace($search, $replace, $pushContent['notify_body']);
                    if (isset($pushContent['data_msg']) && $pushContent['data_msg'] != '') {
                        $dataMsg = str_replace($search, $replace, $pushContent['data_msg']);
                    } else {
                        $dataMsg = '';
                    }
                    $commonServices->insertPushNotification($title, $msgBody, $dataMsg, $pushContent['icon'], $dm['shipment_id'], 'not-responder-participants', 'shipment');
                    $db->update('data_manager', array('push_status' => 'pending'), 'dm_id = ' . $dm['dm_id']);
                }
            }
        }
        return $return;
    }
    public function enrollShipmentParticipant($shipmentId, $participantId)
    {
        $db = new Application_Model_DbTable_ShipmentParticipantMap();
        return $db->enrollShipmentParticipant($shipmentId, $participantId);
    }
    public function getShipmentRowData($shipmentId)
    {
        $shipmentDb = new Application_Model_DbTable_Shipments();
        return $shipmentDb->getShipmentRowInfo($shipmentId);
    }
    public function getAllShipmentForm($parameters)
    {
        $shipmentDb = new Application_Model_DbTable_Shipments();
        return $shipmentDb->getAllShipmentFormDetails($parameters);
    }

    public function getAllFinalizedShipments($parameters)
    {
        $shipmentDb = new Application_Model_DbTable_Shipments();
        return $shipmentDb->fecthAllFinalizedShipments($parameters);
    }

    public function responseSwitch($shipmentId, $switchStatus)
    {
        $shipmentDb = new Application_Model_DbTable_Shipments();
        return $shipmentDb->responseSwitch($shipmentId, $switchStatus);
    }

    public function getFinalizedShipmentInReports($distributionId)
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $sql = $db->select()->from(array('s' => 'shipment', array('shipment_id', 'shipment_code', 'status', 'number_of_samples')))
            ->join(array('d' => 'distributions'), 'd.distribution_id=s.distribution_id', array('distribution_code', 'distribution_date'))
            ->join(array('sp' => 'shipment_participant_map'), 'sp.shipment_id=s.shipment_id', array('participant_count' => new Zend_Db_Expr('count("participant_id")'), 'reported_count' => new Zend_Db_Expr("SUM(shipment_test_date not like  '0000-00-00' OR is_pt_test_not_performed ='yes')"), 'number_passed' => new Zend_Db_Expr("SUM(final_result = 1)")))
            ->join(array('sl' => 'scheme_list'), 'sl.scheme_id=s.scheme_type', array('scheme_name'))
            ->joinLeft(array('rr' => 'r_results'), 'sp.final_result=rr.result_id')
            ->where("s.status='finalized'")
            ->where("s.distribution_id = ?", $distributionId)
            ->group('s.shipment_id');

        return $db->fetchAll($sql);
    }


    public function addQcDetails($params)
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $db->beginTransaction();
        try {
            $shipmentParticipantDb = new Application_Model_DbTable_ShipmentParticipantMap();
            $noOfRowsAffected = $shipmentParticipantDb->addQcInfo($params);
            if ($noOfRowsAffected > 0) {
                $db->commit();
                return $noOfRowsAffected;
            }
        } catch (Exception $e) {
            $db->rollBack();
            error_log($e->getMessage());
            error_log($e->getTraceAsString());
        }
    }

    public function getParticipantShipments($pId)
    {
        $shipmentParticipantDb = new Application_Model_DbTable_ShipmentParticipantMap();
        return $shipmentParticipantDb->fetchParticipantShipments($pId);
    }
    public function getAllShipmentCode()
    {
        $shipmentDb = new Application_Model_DbTable_Shipments();
        return $shipmentDb->fetchUniqueShipmentCode();
    }

    public function getShipmentDetailsInAPI($params, $type = "")
    {
        $shipmentDb = new Application_Model_DbTable_Shipments();
        return $shipmentDb->fetchShipmentDetailsInAPI($params, $type);
    }

    public function getIndividualReportAPI($params)
    {
        $shipmentDb = new Application_Model_DbTable_Shipments();
        return $shipmentDb->fetchIndividualReportAPI($params);
    }
    public function getSummaryReportAPI($params)
    {
        $shipmentDb = new Application_Model_DbTable_Shipments();
        return $shipmentDb->fetchSummaryReportAPI($params);
    }

    public function saveShipmentsFormByAPI($params)
    {
        $shipmentDb = new Application_Model_DbTable_Shipments();
        return $shipmentDb->saveShipmentsFormDetailsByAPI($params);
    }

    public function getShipmentListBasedOnParticipant($params)
    {
        $response = array();
        $shipmentDate = explode(" ", $params['shipmentDate']);
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        if ($params['type'] == 'array') {
            $participantIds = implode(',', $params['participants']);
        } else {
            $participantIds = $params['participants'];
        }

        // $monthYear = Pt_Commons_General::getMonthsInRange($shipmentDate[0], $shipmentDate[1], 'dashboard');

        // Zend_Debug::dump($monthYear);die;
        // if (count($monthYear) > 0) {
        // foreach ($monthYear as $monthIndex => $monthYr) {

        $sQuery = $db->select()->from(array('s' => 'shipment'), array('s.shipment_code', 's.scheme_type', 's.lastdate_response', 'max_score', 'average_score'))
            ->join(array('sp' => 'shipment_participant_map'), 'sp.shipment_id=s.shipment_id', array('shipment_score' => new Zend_Db_Expr("SUM(sp.shipment_score)"), 'documentation_score' => new Zend_Db_Expr("SUM(sp.documentation_score)"), 'participantCount' => new Zend_Db_Expr("count(sp.participant_id)"), 'receivedCount' => new Zend_Db_Expr("SUM(sp.shipment_test_date not like '0000-00-00')")))
            // ->join(array('p' => 'participant'), 'sp.participant_id=p.participant_id', array('unique_identifier', 'participantName' => new Zend_Db_Expr("CONCAT(COALESCE(p.first_name,''),' ', COALESCE(p.last_name,''))")))
            ->where("s.status='finalized'")
            ->where("sp.participant_id IN(" . $participantIds . ")")
            ->where("s.scheme_type = '" . $params['shipmentType'] . "'")
            ->group('s.shipment_id')
            // ->group("DATE_FORMAT(s.shipment_code,'%b-%Y')")
            ->order("s.shipment_id");
        if (isset($shipmentDate[0]) && $shipmentDate[0] != "") {
            $sQuery->where('s.shipment_date >="' . date('Y-m-01', strtotime($shipmentDate[0])) . '"');
        }
        if (isset($shipmentDate[1]) && $shipmentDate[1] != "") {
            $sQuery->where('s.shipment_date <="' . date('Y-m-t', strtotime($shipmentDate[1])) . '"');
        }
        // die($sQuery);
        $result =  $db->fetchAll($sQuery);
        if (count($result) > 0) {

            foreach ($result as $key => $row) {
                $response[$row['shipment_code']] = array(
                    'shipment_code'         => $row['shipment_code'],
                    // 'participantName'       => $row['participantName'],
                    'shipment_score'        => $row['shipment_score'],
                    'documentation_score'   => $row['documentation_score'],
                    'participantCount'      => $row['participantCount'],
                    'scheme_type'           => $row['scheme_type']
                );
            }
        } else {
            $response[] = null;
        }
        // }
        // }
        return array('result' => $response, 'monthRange' => $params['shipmentType']);
    }

    public function getShipmentListBasedOnScheme()
    {
        $total = $name = $response = array();
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $sQuery = $db->select()->from(array('s' => 'shipment'), array('s.shipment_code', 's.scheme_type', 's.lastdate_response', 'max_score', 'average_score'))
            ->join(array('sp' => 'shipment_participant_map'), 'sp.shipment_id=s.shipment_id', array('shipment_score' => new Zend_Db_Expr("SUM(sp.shipment_score)"), 'documentation_score' => new Zend_Db_Expr("SUM(sp.documentation_score)"), 'participantCount' => new Zend_Db_Expr("count(sp.participant_id)"), 'receivedCount' => new Zend_Db_Expr("SUM(sp.shipment_test_date not like '0000-00-00')")))
            ->join(array('sl' => 'scheme_list'), 's.scheme_type=sl.scheme_id', array('scheme_name'))
            ->where("s.status != 'pending'")
            ->group("s.shipment_id")
            ->order("s.shipment_id");
        $result =  $db->fetchAll($sQuery);


        if (count($result) > 0) {

            foreach ($result as $key => $row) {
                $response[$row['scheme_type']][$key] = array(
                    'shipment_code'         => $row['shipment_code'],
                    'shipment_score'        => (isset($row['shipment_score']) && ($row['shipment_score']) > 0) ? $row['shipment_score'] : 0,
                    'documentation_score'   => $row['documentation_score'],
                    'participantCount'      => $row['participantCount'],
                    'receivedCount'         => (isset($row['receivedCount']) && ($row['receivedCount']) > 0) ? $row['receivedCount'] : 0,
                    'scheme_type'           => $row['scheme_type']
                );

                $total['participants'] = $total['participants'] ?? array();
                $total['received'] = $total['participants'] ?? array();
                $name[$row['scheme_type']] = $name[$row['scheme_type']] ?? array();

                $total['participants'][$row['scheme_type']] = $total['participants'][$row['scheme_type']] ?? 0;
                $total['received'][$row['scheme_type']] = $total['received'][$row['scheme_type']] ?? 0;

                $total['participants'][$row['scheme_type']] += $row['participantCount'];
                $total['received'][$row['scheme_type']] += (isset($row['receivedCount']) && ($row['receivedCount']) > 0) ? $row['receivedCount'] : 0;
                $name[$row['scheme_type']] = $row['scheme_name'];
            }
        }
        return array('result' => $response, 'total' => $total, 'name' => $name);
    }
}
