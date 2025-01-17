<?php

class TbController extends Zend_Controller_Action
{

    public function init()
    {
        $ajaxContext = $this->_helper->getHelper('AjaxContext');
        $ajaxContext->addActionContext('index', 'html')
            ->addActionContext('assay-formats', 'html')
            ->initContext();
    }

    public function indexAction()
    {
        // action body
    }

    public function responseAction()
    {

        $schemeService = new Application_Service_Schemes();
        $shipmentService = new Application_Service_Shipments();

        if ($this->getRequest()->isPost()) {

            $data = $this->getRequest()->getPost();

            //Zend_Debug::dump($data);die;

            $shipmentService->updateTbResults($data);
            $this->redirect("/participant/dashboard");

            //die;            
        } else {
            $sID = $this->getRequest()->getParam('sid');
            $pID = $this->getRequest()->getParam('pid');
            $eID = $this->getRequest()->getParam('eid');

            $participantService = new Application_Service_Participants();
            $this->view->participant = $participantService->getParticipantDetails($pID);
            $this->view->allSamples = $schemeService->getTbSamples($sID, $pID);
            $shipment = $schemeService->getShipmentData($sID, $pID);
            $shipment['attributes'] = json_decode($shipment['attributes'], true);
            $this->view->shipment = $shipment;
            $this->view->shipId = $sID;
            $this->view->participantId = $pID;
            $this->view->eID = $eID;

            $this->view->isEditable = $shipmentService->isShipmentEditable($sID, $pID);

            $commonService = new Application_Service_Common();
            $this->view->modeOfReceipt = $commonService->getAllModeOfReceipt();
            $this->view->globalQcAccess = $commonService->getConfig('qc_access');
        }
    }

    public function assayFormatsAction()
    {
        $sID = $this->getRequest()->getParam('sid');
        $pID = $this->getRequest()->getParam('pid');
        $eID = $this->getRequest()->getParam('eid');
        $type = $this->getRequest()->getParam('type');

        $schemeService = new Application_Service_Schemes();
        $shipmentService = new Application_Service_Shipments();

        $participantService = new Application_Service_Participants();
        $this->view->participant = $participantService->getParticipantDetails($pID);
        $this->view->allSamples = $schemeService->getTbSamples($sID, $pID);
        $shipment = $schemeService->getShipmentData($sID, $pID);
        $shipment['attributes'] = json_decode($shipment['attributes'], true);
        $this->view->shipment = $shipment;
        $this->view->shipId = $sID;
        $this->view->participantId = $pID;
        $this->view->eID = $eID;
        $this->view->type = $type;

        $this->view->isEditable = $shipmentService->isShipmentEditable($sID, $pID);

        $commonService = new Application_Service_Common();
        $this->view->modeOfReceipt = $commonService->getAllModeOfReceipt();
        $this->view->globalQcAccess = $commonService->getConfig('qc_access');
    }

    public function downloadAction()
    {
        $this->_helper->layout()->disableLayout();
        $sID = $this->getRequest()->getParam('sid');
        $pID = $this->getRequest()->getParam('pid');
        $eID = $this->getRequest()->getParam('eid');

        $reportService = new Application_Service_Reports();
        $this->view->header = $reportService->getReportConfigValue('report-header');
        $this->view->logo = $reportService->getReportConfigValue('logo');
        $this->view->logoRight = $reportService->getReportConfigValue('logo-right');


        $file = APPLICATION_PATH . DIRECTORY_SEPARATOR . "configs" . DIRECTORY_SEPARATOR . "config.ini";
        $this->view->config = new Zend_Config_Ini($file, APPLICATION_ENV);


        $participantService = new Application_Service_Participants();
        $this->view->participant = $participantService->getParticipantDetails($pID);
        $schemeService = new Application_Service_Schemes();
        $this->view->referenceDetails = $schemeService->getDtsReferenceData($sID);

        $shipment = $schemeService->getShipmentData($sID, $pID);
        $shipment['attributes'] = json_decode($shipment['attributes'], true);
        $this->view->shipment = $shipment;
    }
}
