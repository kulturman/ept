<?php

class Application_Model_DbTable_ShipmentParticipantMap extends Zend_Db_Table_Abstract
{

    protected $_name = 'shipment_participant_map';
    protected $_primary = 'map_id';

    public function shipItNow($params){
        try{
            $this->getAdapter()->beginTransaction();
            $authNameSpace = new Zend_Session_Namespace('administrators');
            $this->delete('shipment_id='.$params['shipmentId']);
            foreach($params['participants'] as $participant){
                
                
                //$row = $this->fetchRow('shipment_id='.$params['shipmentId'] .' and participant_id='.$participant);
                //if($row != null && $row != ""){
                //    echo('shipment_id='.$params['shipmentId'] .' and participant_id='.$participant);
                //    $data = array('shipment_id'=>$params['shipmentId'],
                //                  'participant_id'=>$participant,
                //                  'updated_by_admin' => $authNameSpace->admin_id,
                //                  "updated_on_admin"=>new Zend_Db_Expr('now()'));
                //    $this->update($data,'shipment_id='.$params['shipmentId'] .' and participant_id='.$participant);                    
                //}else{
                    $data = array('shipment_id'=>$params['shipmentId'],
                                  'participant_id'=>$participant,
                                  'evaluation_status'=>'19901190',
                                  'created_by_admin' => $authNameSpace->admin_id,
                                  "created_on_admin"=>new Zend_Db_Expr('now()'));
                    $this->insert($data);                    
                //}

            }
            
            $shipmentDb = new Application_Model_DbTable_Shipments();
            $shipmentDb->updateShipmentStatus($params['shipmentId'],'ready');
            
            $shipmentRow = $shipmentDb->fetchRow('shipment_id='.$params['shipmentId']);
            
            $resultSet = $shipmentDb->fetchAll($shipmentDb->select()->where("status = 'pending' AND distribution_id = ".$shipmentRow['distribution_id']));
            
            if(count($resultSet) == 0){
                $distroService = new Application_Service_Distribution();
                $distroService->updateDistributionStatus($shipmentRow['distribution_id'],'configured');
            }

            $this->getAdapter()->commit();
            return true;            
        }catch(Exception $e){
            $this->getAdapter()->rollBack();
            die($e->getMessage());
            error_log($e->getTraceAsString());
            return false;
        }
    }
    
    public function updateShipment($params,$shipmentMapId,$lastDate){
        $row =  $this->fetchRow("map_id = ". $shipmentMapId);
        
        $params['evaluation_status'] = $row['evaluation_status'];
        
        // changing evaluation status 3rd character to 1 = responded
        $params['evaluation_status'][2] = 1; 
        
        // changing evaluation status 5th character to 1 = via web user
        $params['evaluation_status'][4] = 1; 
        
        // changing evaluation status 4th character to 1 = timely response or 2 = delayed response
        
        $date = new Zend_Date();
        $lastDate = new Zend_Date($lastDate, Zend_Date::ISO_8601);
        // only if current date is LATER than last date we make status = 2
        if($date->compare($lastDate) == 1){
            $params['evaluation_status'][3] = 2;
        }else{
            $params['evaluation_status'][3] = 1;  
        }        
 
        return $this->update($params,"map_id = ". $shipmentMapId);
    }
    
    public function isShipmentEditable($shipmentId,$participantId){

        $row =  $this->fetchRow("shipment_id = ". $shipmentId . " AND participant_id = ".$participantId);
        $canEdit =  substr($row['evaluation_status'],2 ,1); // getting the 3rd character
        if($canEdit == 9){
            return true;
        }else{
            return false;
        }
        
    }
}
