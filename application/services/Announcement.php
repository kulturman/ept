<?php

class Application_Service_Announcement {
	
	public function getAllAnnouncementByGrid($params){
		$db = new Application_Model_DbTable_Announcement();
		return $db->fetchAllAnnouncementByGrid($params);
	}
    
    public function composeNewAnnouncement($params){
		$db = new Application_Model_DbTable_Announcement();
        $lastId =  $db->saveNewAnnouncement($params);
        
        if($lastId > 0){
            $commonServices = new Application_Service_Common();
            $notParticipatedMailContent = $commonServices->getEmailTemplate('not_participated');
            $db = Zend_Db_Table_Abstract::getDefaultAdapter();
            $sQuery = $db->select()->from(array('sp' => 'shipment_participant_map'), array('sp.participant_id', 'sp.map_id', 'sp.last_not_participated_mail_count', 'sp.final_result'))
                ->joinLeft(array('s' => 'shipment'), 's.shipment_id=sp.shipment_id', array('s.shipment_code', 's.shipment_code'))
                ->joinLeft(array('d' => 'distributions'), 'd.distribution_id = s.distribution_id', array('distribution_code', 'distribution_date'))
                ->joinLeft(array('p' => 'participant'), 'p.participant_id=sp.participant_id', array('p.email', 'participantName' => new Zend_Db_Expr("GROUP_CONCAT(DISTINCT p.first_name,\" \",p.last_name ORDER BY p.first_name SEPARATOR ', ')")))
                ->joinLeft(array('sl' => 'scheme_list'), 'sl.scheme_id=s.scheme_type', array('SCHEME' => 'sl.scheme_name'))
                ->where("(sp.shipment_test_date = '0000-00-00' OR sp.shipment_test_date IS NULL OR sp.shipment_test_date like '')")
                ->where("sp.participant_id IN(".implode(",", $params['participants']).")")
                ->group("sp.participant_id");
            $participantEmails = $db->fetchAll($sQuery);
            foreach ($participantEmails as $participantDetails) {
                if ($participantDetails['email'] != '') {
                    $subject        = $params['subject'];
                    $message        = $params['message'];
                    $fromEmail      = $notParticipatedMailContent['mail_from'];
                    $fromFullName   = $notParticipatedMailContent['from_name'];
                    $toEmail        = $participantDetails['email'];
                    $cc             = $notParticipatedMailContent['mail_cc'];
                    $bcc            = $notParticipatedMailContent['mail_bcc'];
                    $commonServices->insertTempMail($toEmail, $cc, $bcc, $subject, $message, $fromEmail, $fromFullName);
                }
    
            }
            // Push notification section
            $pushQuery = $db->select()
                ->from(array('s' => 'shipment'),array('s.shipment_code', 's.shipment_code'))
                ->join(array('sp'=>'shipment_participant_map'),'sp.shipment_id=s.shipment_id',array('sp.shipment_id', 'sp.participant_id', 'sp.map_id', 'sp.last_not_participated_mail_count', 'sp.final_result'))
                ->joinLeft(array('d' => 'distributions'), 'd.distribution_id = s.distribution_id', array('distribution_code', 'distribution_date'))
                ->joinLeft(array('p' => 'participant'), 'p.participant_id=sp.participant_id', array('p.email', 'participantName' => new Zend_Db_Expr("GROUP_CONCAT(DISTINCT p.first_name,\" \",p.last_name ORDER BY p.first_name SEPARATOR ', ')")))
                ->joinLeft(array('sl' => 'scheme_list'), 'sl.scheme_id=s.scheme_type', array('SCHEME' => 'sl.scheme_name'))
                ->join(array('pmm'=>'participant_manager_map'),'pmm.participant_id=sp.participant_id',array('dm_id'))
                ->join(array('dm'=>'data_manager'),'pmm.dm_id=dm.dm_id',array('primary_email', 'push_notify_token'))
                ->where("(sp.shipment_test_date = '0000-00-00' OR sp.shipment_test_date IS NULL OR sp.shipment_test_date like '')")
                ->where("sp.participant_id IN(".implode(",", $params['participants']).")")
                ->group('dm.dm_id');
            $dmDetails = $db->fetchAll($pushQuery);
            if(count($dmDetails) > 0){
                foreach($dmDetails as $dm){
                    $datamanagers[] = $dm['dm_id'];
                }
            }
            $title      = $params['subject'];
            $msgBody    = $params['message'];
            $commonServices->insertPushNotification($title,$msgBody,'','',implode(",", $datamanagers),'announcement','announcement',$lastId);
        }
        return $lastId;
	}
}

