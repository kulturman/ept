<?php

class Reports_CommonController extends Zend_Controller_Action
{

    public function init()
    {
        $ajaxContext = $this->_helper->getHelper('AjaxContext');
        $ajaxContext->addActionContext('get-shipments-by-scheme', 'html')
            ->addActionContext('get-shipments-by-date', 'html')
            ->addActionContext('get-options-by-value', 'html')
            ->initContext();
    }

    public function indexAction()
    {
        // action body
    }

    public function getShipmentsBySchemeAction()
    {
        if ($this->getRequest()->isPost()) {
            $schemeType = $this->_getParam('schemeType');
            $startDate = $this->_getParam('startDate');
            $endDate = $this->_getParam('endDate');
            $reportService = new Application_Service_Reports();
            $response = $reportService->getShipmentsByScheme($schemeType, $startDate, $endDate);
            $this->view->shipmentList = $response;
        }
    }

    public function getShipmentsByDateAction()
    {
        if ($this->getRequest()->isPost()) {
            $schemeType = $this->_getParam('schemeType');
            $startDate = $this->_getParam('startDate');
            $endDate = $this->_getParam('endDate');
            $reportService = new Application_Service_Reports();
            $response = $reportService->getShipmentsByDate($schemeType, $startDate, $endDate);
            $this->view->shipmentList = $response;
        }
    }

    public function getOptionsByValueAction()
    {
        if ($this->getRequest()->isPost()) {
            $commonService = new Application_Service_Common();
            $params = $this->getAllParams();
            $this->view->result = $commonService->getOptionsByValue($params);
            $this->view->params = $params;
        }
    }
}
