<?php

class Application_Model_DbTable_DataManagers extends Zend_Db_Table_Abstract
{

    protected $_name = 'data_manager';
    protected $_primary = array('dm_id');

    public function addUser($params)
    {
        $authNameSpace = new Zend_Session_Namespace('administrators');
        $data = array(
            'first_name' => $params['fname'],
            'last_name' => $params['lname'],
            'institute' => $params['institute'],
            'phone' => $params['phone2'],
            'mobile' => $params['phone1'],
            'secondary_email' => $params['semail'],
            'primary_email' => $params['dmUserId'],
            'password' => $params['dmPassword'],
            'force_password_reset' => 1,
            'qc_access' => $params['qcAccess'],
            'enable_adding_test_response_date' => $params['receiptDateOption'],
            'enable_choosing_mode_of_receipt' => $params['modeOfReceiptOption'],
            'view_only_access' => $params['viewOnlyAccess'],
            'status' => $params['status'],
            'created_by' => $authNameSpace->admin_id,
            'created_on' => new Zend_Db_Expr('now()')
        );
        $dmId = $this->insert($data);
        if (isset($params['allparticipant']) && count($params['allparticipant'] > 0)) {
            $db = Zend_Db_Table_Abstract::getAdapter();
            $db->delete('participant_manager_map', "dm_id = " . $dmId);
            foreach ($params['allparticipant'] as $participant) {
                $db->insert('participant_manager_map', array('dm_id' => $dmId, 'participant_id' => $participant));
            }
        }
        return $dmId;
    }

    public function getAllUsers($parameters)
    {

        /* Array of database columns which should be read and sent back to DataTables. Use a space where
         * you want to insert a non-database field (for example a counter or static image)
         */

        $aColumns = array('u.institute', 'u.first_name', 'u.last_name', 'u.mobile', 'u.primary_email', 'u.secondary_email', 'p.first_name', 'u.status');

        /* Indexed column (used for fast and accurate table cardinality) */
        $sIndexColumn = "dm_id";


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
                    $sOrder .= $aColumns[intval($parameters['iSortCol_' . $i])] . "
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

        $sQuery = $this->getAdapter()->select()->from(array('u' => $this->_name))
            ->joinLeft(array('pmm' => 'participant_manager_map'), 'pmm.dm_id=u.dm_id', array())
            ->joinLeft(array('p' => 'participant'), 'p.participant_id = pmm.participant_id', array('participantCount' => new Zend_Db_Expr("SUM(IF(p.participant_id!='',1,0))"), 'p.participant_id'))
            ->group('u.dm_id');

        if (isset($sWhere) && $sWhere != "") {
            $sQuery = $sQuery->where($sWhere);
        }

        if (isset($sOrder) && $sOrder != "") {
            $sQuery = $sQuery->order($sOrder);
        }

        if (isset($sLimit) && isset($sOffset)) {
            $sQuery = $sQuery->limit($sLimit, $sOffset);
        }

        //die($sQuery);

        $rResult = $this->getAdapter()->fetchAll($sQuery);


        /* Data set length after filtering */
        $sQuery = $sQuery->reset(Zend_Db_Select::LIMIT_COUNT);
        $sQuery = $sQuery->reset(Zend_Db_Select::LIMIT_OFFSET);
        $aResultFilterTotal = $this->getAdapter()->fetchAll($sQuery);
        $iFilteredTotal = count($aResultFilterTotal);

        /* Total data set length */
        $sQuery = $this->getAdapter()->select()->from($this->_name, new Zend_Db_Expr("COUNT('" . $sIndexColumn . "')"));
        $aResultTotal = $this->getAdapter()->fetchCol($sQuery);
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
            $row = array();
            //if(isset($aRow['participant_id'])&& $aRow['participant_id']!=''){
            //$participantDetails='<a href="javascript:void(0);" onclick="layoutModal(\'/admin/participants/view-participants/id/'.$aRow['participant_id'].'\',\'980\',\'500\');" class="btn btn-primary btn-xs"><i class="icon-search"></i></a>';
            //}else{
            //$participantDetails='';
            //}
            $row[] = $aRow['institute'];
            // $row[] = $participantDetails.' '.$aRow['institute'];
            $row[] = $aRow['first_name'];
            $row[] = $aRow['last_name'];
            $row[] = $aRow['mobile'];
            $row[] = $aRow['primary_email'];
            $row[] = '<a href="javascript:void(0);" onclick="layoutModal(\'/admin/participants/view-participants/id/' . $aRow['dm_id'] . '\',\'980\',\'500\');" >' . $aRow['participantCount'] . '</a>';
            $row[] = $aRow['status'];
            $row[] = '<a href="/admin/data-managers/edit/id/' . $aRow['dm_id'] . '" class="btn btn-warning btn-xs" style="margin-right: 2px;"><i class="icon-pencil"></i> Edit</a>';

            $output['aaData'][] = $row;
        }

        echo json_encode($output);
    }

    public function getUserDetails($userId)
    {
        return $this->fetchRow("primary_email = '" . $userId . "'")->toArray();
    }

    public function getUserDetailsBySystemId($userSystemId)
    {
        return $this->fetchRow("dm_id = '" . $userSystemId . "'")->toArray();
    }

    public function updateUser($params)
    {
        $authNameSpace = new Zend_Session_Namespace('administrators');
        $data = array(
            'first_name' => $params['fname'],
            'last_name' => $params['lname'],
            'phone' => $params['phone2'],
            'mobile' => $params['phone1'],
            'secondary_email' => $params['semail'],
            'updated_by' => $authNameSpace->admin_id,
            'updated_on' => new Zend_Db_Expr('now()')
        );

        if (isset($params['institute']) && $params['institute'] != "") {
            $data['institute'] = $params['institute'];
        }
        if (isset($params['qcAccess']) && $params['qcAccess'] != "") {
            $data['qc_access'] = $params['qcAccess'];
        }
        if (isset($params['receiptDateOption']) && $params['receiptDateOption'] != "") {
            $data['enable_adding_test_response_date'] = $params['receiptDateOption'];
        }
        if (isset($params['modeOfReceiptOption']) && $params['modeOfReceiptOption'] != "") {
            $data['enable_choosing_mode_of_receipt'] = $params['modeOfReceiptOption'];
        }
        if (isset($params['viewOnlyAccess']) && $params['viewOnlyAccess'] != "") {
            $data['view_only_access'] = $params['viewOnlyAccess'];
        }
        if (isset($params['userId']) && $params['userId'] != "") {
            $data['primary_email'] = $params['userId'];
        }
        if (isset($params['password']) && $params['password'] != "") {
            $data['password'] = $params['password'];
            $data['force_password_reset'] = 1;
        }
        if (isset($params['status']) && $params['status'] != "") {
            $data['status'] = $params['status'];
        }
        $dmId = $params['userSystemId'];
        $this->update($data, "dm_id = " . $params['userSystemId']);
        if (isset($params['allparticipant']) && count($params['allparticipant'] > 0)) {
            $db = Zend_Db_Table_Abstract::getAdapter();
            $db->delete('participant_manager_map', "dm_id = " . $dmId);
            foreach ($params['allparticipant'] as $participant) {
                $db->insert('participant_manager_map', array('dm_id' => $dmId, 'participant_id' => $participant));
            }
        }
        return $dmId;
    }

    public function resetpasswordForEmail($email)
    {
        $row = $this->fetchRow("primary_email = '" . $email . "'");
        if ($row != null && count($row) == 1) {
            $randompassword = Application_Service_Common::getRandomString(15);
            $row->password = $randompassword;
            $row->force_password_reset = 1;
            $row->save();
            return $randompassword;
        } else {
            return false;
        }
    }

    public function getAllDataManagers($active = true)
    {
        $sql = $this->select()->order("first_name");
        if ($active) {
            $sql = $sql->where("status='active'");
        }
        return $this->fetchAll($sql);
    }

    public function updatePassword($oldpassword, $newpassword)
    {
        $authNameSpace = new Zend_Session_Namespace('datamanagers');
        $email = $authNameSpace->email;
        $noOfRows = $this->update(array('password' => $newpassword, 'force_password_reset' => 0), "primary_email = '" . $email . "' and password = '" . $oldpassword . "'");
        if ($noOfRows != null && count($noOfRows) == 1) {
            $authNameSpace->force_password_reset = 0;
            return true;
        } else {
            return false;
        }
    }

    public function updateLastLogin($dmId)
    {

        $noOfRows = $this->update(array('last_login' => new Zend_Db_Expr('now()')), "dm_id = " . $dmId);
        if ($noOfRows != null && count($noOfRows) == 1) {
            return true;
        } else {
            return false;
        }
    }

    public function fetchParticipantDatamanagerSearch($searchParams)
    {
        $sql = $this->select();
        //$searchParams = explode(" ", $searchParams);
        //foreach($searchParams as $s){
        $sql =  $sql->where("primary_email LIKE '%" . $searchParams . "%'")
            ->orWhere("first_name LIKE '%" . $searchParams . "%'")
            ->orWhere("last_name LIKE '%" . $searchParams . "%'")
            ->orWhere("institute LIKE '%" . $searchParams . "%'");
        //}

        return $this->fetchAll($sql);
    }

    public function saveNewPassword($params){
        // Zend_Debug::dump($params);die;
        $noOfRows = $this->update(array('password' => $params['password']), "primary_email = '" . $params['registeredEmail'] ."'");
        if ($noOfRows != null && count($noOfRows) == 1) {
            return true;
        } else {
            return false;
        }
    }

    public function fetchEmailById($email){
        return $this->fetchRow("primary_email = '" . base64_decode($email) . "'");
    }

    public function loginDatamanagerByAPI($params)
    {
        /* Check the app versions */
        if (!isset($params['appVersion'])) {
            return array('status' =>'version-fail','message'=>'App Version Failed.');
        }
        if (!isset($params['userId']) && !isset($params['key'])) {
            return array('status' =>'fail','message'=>'Please enter the login credentials');
        }
        /* Check the login credential */
        $result = $this->fetchRow("primary_email='" . $params['userId'] . "' AND password='" . $params['key'] . "'");
        if (!isset($result['dm_id']) && $result['dm_id'] == "") {
            return array('status' =>'fail','message'=>'Your username or password is incorrect');
        }
        /* Check the status for data manager */
        if (isset($result['status']) && $result['status'] != "active") {
            return array('status' =>'fail','message'=>'You are not activated. Kindly contact admin');
        }
        /* Update the new auth token */
        $common = new Application_Service_Common();
        $params['authToken'] = $common->getRandomString(6);
        $params['download_link'] = $common->getRandomString(9);
        $this->update(array('auth_token' => $params['authToken'], 'download_link' => $params['download_link'],'last_login' => new Zend_Db_Expr('now()')), "dm_id = " . $result['dm_id']);
        $aResult = $this->fetchAuthToken($params);
        /* App version check */
        if ($aResult == 'app-version-failed') {
            return array('status' =>'version-fail','message'=>'App Version Failed.');
        }
        /* Validate new auth token and app-version */
        if(!$aResult){
            return array('status' =>'auth-fail','message'=>'Something went wrong. Please log in again');
        }

        /* Create a new response to the API service */
        $resultData = array(
            'id'                            => $result['dm_id'],
            'authToken'                     => $params['authToken'],
            'viewOnlyAccess'                => (isset($aResult['view_only_access']) && $aResult['view_only_access'] != "") ? $aResult['view_only_access'] : 'no',
            'qcAccess'                      => (isset($aResult['qc_access']) && $aResult['qc_access'] != "") ? $aResult['qc_access'] : 'no',
            'enableAddingTestResponseDate'  => (isset($aResult['enable_adding_test_response_date']) && $aResult['enable_adding_test_response_date'] != "") ? $aResult['enable_adding_test_response_date'] : 'no',
            'enableChoosingModeOfReceipt'   => (isset($aResult['enable_choosing_mode_of_receipt']) && $aResult['enable_choosing_mode_of_receipt'] != "") ? $aResult['enable_choosing_mode_of_receipt'] : 'no',
            'name'                          => $result['first_name'] . ' ' . $result['last_name'],
            'phone'                         => $result['phone'],
            'appVersion'                    => $aResult['app_version']
        );
        /* Finalizing the response data and return */
        if(!isset($resultData) && trim($resultData['authToken']) == ''){
            return array('status' =>'fail','message'=>'Something went wrong please try again later');
        }else{
            return array('status' =>'success','data'=>$resultData);
        }
    }

    public function fetchLoggedInDetails($params)
    {
        /* Check the app versions */
        if (!isset($params['appVersion'])) {
            return array('status' =>'version-fail','message'=>'App Version Failed.');
        }
        if (!isset($params['authToken'])) {
            return array('status' =>'auth-fail','message'=>'Something went wrong. Please log in again');
        }
        /* Check the login credential */
        $result = $this->fetchRow("auth_token='" . $params['authToken'] . "'");

        $aResult = $this->fetchAuthToken($params);
        /* App version check */
        if ($aResult == 'app-version-failed') {
            return array('status' =>'version-fail','message'=>'App Version Failed.');
        }
        /* Validate new auth token and app-version */
        if(!$aResult){
            return array('status' =>'auth-fail','message'=>'Something went wrong. Please log in again');
        }

        /* Create a new response to the API service */
        $resultData = array(
            'id'                            => $result['dm_id'],
            'authToken'                     => $params['authToken'],
            'viewOnlyAccess'                => (isset($aResult['view_only_access']) && $aResult['view_only_access'] != "") ? $aResult['view_only_access'] : 'no',
            'qcAccess'                      => (isset($aResult['qc_access']) && $aResult['qc_access'] != "") ? $aResult['qc_access'] : 'no',
            'enableAddingTestResponseDate'  => (isset($aResult['enable_adding_test_response_date']) && $aResult['enable_adding_test_response_date'] != "") ? $aResult['enable_adding_test_response_date'] : 'no',
            'enableChoosingModeOfReceipt'   => (isset($aResult['enable_choosing_mode_of_receipt']) && $aResult['enable_choosing_mode_of_receipt'] != "") ? $aResult['enable_choosing_mode_of_receipt'] : 'no',
            'name'                          => $result['first_name'] . ' ' . $result['last_name'],
            'phone'                         => $result['phone'],
            'appVersion'                    => $aResult['app_version']
        );
        /* Finalizing the response data and return */
        if(!isset($resultData) && trim($resultData['authToken']) == ''){
            return array('status' =>'fail','message'=>'Something went wrong please try again later');
        }else{
            return array('status' =>'success','data'=>$resultData);
        }
    }

    public function fetchAuthToken($params)
    {
        /* Check the app versions */
        $configDb = new Application_Model_DbTable_SystemConfig();
        $appVersion = $configDb->getValue($params['appVersion']);
        if (!$appVersion) {
            return 'app-version-failed';
        }
        /* Check the token  */
        $db = Zend_Db_Table_Abstract::getAdapter();
        $sQuery = $db->select()->from(array('dm' => 'data_manager'), array('dm.dm_id', 'view_only_access', 'qc_access', 'enable_adding_test_response_date', 'enable_choosing_mode_of_receipt'))
            ->join(array('pmm' => 'participant_manager_map'), 'pmm.dm_id=dm.dm_id')
            ->join(array('p' => 'participant'), 'p.participant_id=pmm.participant_id', array('p.unique_identifier', 'p.first_name', 'p.last_name', 'p.state'))
            ->where("dm.auth_token=?", $params['authToken']);
        $aResult = $db->fetchRow($sQuery);
        if (!isset($aResult['dm_id'])){
            return false;
        }
        /* Return the response data */
        return  array(
            'dm_id'                             => $aResult['dm_id'],
            'view_only_access'                  => $aResult['view_only_access'],
            'qc_access'                         => $aResult['qc_access'],
            'enable_adding_test_response_date'  => $aResult['enable_adding_test_response_date'],
            'enable_choosing_mode_of_receipt'   => $aResult['enable_choosing_mode_of_receipt'],
            'participant_id'                    => $aResult['participant_id'],
            'unique_identifier'                 => $aResult['unique_identifier'],
            'first_name'                        => $aResult['first_name'],
            'last_name'                         => $aResult['last_name'],
            'state'                             => $aResult['state'],
            'app_version'                       => $appVersion['value']
        );
    }

    public function changePasswordDatamanagerByAPI($params){
        /* Check the app versions */
        if (!isset($params['appVersion'])) {
            return array('status' =>'version-fail','message'=>'App Version Failed.');
        }
        /* App version check */
        $aResult = $this->fetchAuthToken($params);
        if ($aResult == 'app-version-failed') {
            return array('status' =>'version-fail','message'=>'App Version Failed.');
        }
        if(!$aResult){
            return array('status' =>'auth-fail','message'=>'Something went wrong. Please log in again');
        }

        $oldPassResult = $this->fetchRow("password='" . $params['oldPassword'] . "' AND auth_token = '".$params['authToken']."'");
        if(!$oldPassResult){
            return array('status' =>'fail','message'=>'Your old password is incorrect');
        }
        /* Update the new password to the server */
        $update = $this->update(array('password' => $params['password']), array('dm_id = ?' => (int)$aResult['dm_id']));
        if($update < 1){
            return array('status' =>'fail','message'=>'You have entered old password');
        }
        $this->update(array('updated_on'=>new Zend_Db_Expr('now()')), array('dm_id = ?' => $aResult['dm_id']));
        return array('status' =>'success','message'=>'Password Updated Successfully');
    }
    
    public function setForgetPasswordDatamanagerAPI($params){
        /* Check the app versions */
        if (!isset($params['appVersion'])) {
            return array('status' =>'version-fail','message'=>'App Version Failed.');
        }
        /* App version check */
        $aResult = $this->fetchRow("primary_email='" . $params['email'] . "'");
        if(!$aResult){
            return array('status' =>'fail','message'=>'You have entered email not found');
        }
        /* Update the new password to the server */
        $update = $this->update(array('password' => $params['password']), array('dm_id = ?' => $aResult['dm_id']));
        if($update < 1){
            return array('status' =>'fail','message'=>'You have entered old password');
        }
        $this->update(array('updated_on'=>new Zend_Db_Expr('now()')), array('dm_id = ?' => $aResult['dm_id']));
        return array('status' =>'success','message'=>'Password Updated Successfully');
    }

    public function fetchAuthTokenByToken($params){
        return $this->fetchRow("auth_token='" . $params['authToken'] . "'");
    }
}
