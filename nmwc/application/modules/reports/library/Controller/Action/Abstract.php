<?php
abstract class Reports_Library_Controller_Action_Abstract extends Custom_Controller_Action_Abstract
{
	
	
	
	public function init()
	{
		
		$this->_initView();
	}
	
	/**
	 * Before dispatching the requested controller/action
	 * check to see if teh request is an AJAX request (via XMLHTTPREQUEST or $_GET['ajax']
	 * 
	 * If it is an ajax request, remove the layout
	 * 
	 * If it is not, setup the FlashMessenger
	 */
	public function preDispatch()
	{
        parent::getmodulename();
        $this->moduleacl = $this->view->moduleacl = new Zend_Session_Namespace('Acl');
    	
        $this->view->filterby = $this->filterbyarr = array();
        $this->filterbyarr[0]['val'] 	= 'Company';
        $this->filterbyarr[1]['val'] 	= 'Region';
        $this->filterbyarr[2]['val'] 	= 'Branch/Depot';
        $this->filterbyarr[3]['val'] 	= 'Area';
        $this->filterbyarr[4]['val'] 	= 'SubArea';
        $this->filterbyarr[5]['val'] 	= 'Route';		
        $this->filterbyarr[0]['id'] 	= '1';
        $this->filterbyarr[1]['id'] 	= '2';
        $this->filterbyarr[2]['id'] 	= '3';
        $this->filterbyarr[3]['id'] 	= '4';
        $this->filterbyarr[4]['id'] 	= '5';
        $this->filterbyarr[5]['id'] 	= '6';
		
		$transaction_report = array('Sales_Summary','Order_Summary','Return_Summary','FOC_Summary','Collection_Summary','Transaction_Route_View','Payment_Summary','Deposit_Summary','Pricing_Summary');
		
		if(in_array($this->currentmodulename,$transaction_report)){
			$this->filterbyarr[6]['val'] 	= 'Route Category';
			$this->filterbyarr[6]['id'] 	= '7';
		}
		
        $this->view->filterby = $this->filterbyarr;
        
        $this->filter_arr = array("1"=>"Company","2"=>"Region","3"=>"Branch/Depot","4"=>"Area","5"=>"SubArea","6"=>"Route","Route Category"=>"7");
        
        $this->view->RP_NOOFRECORDSHOW  = $this->RP_NOOFRECORDSHOW = Zend_Registry::get('config')->constants->RP_NOOFRECORDSHOW;
		$this->view->RP_ROWLISTSHOW  = $this->RP_ROWLISTSHOW = Zend_Registry::get('config')->constants->RP_ROWLISTSHOW;
		
		//if  its an AJAX request stop here
		if ($this->_request->isXmlHttpRequest() || isset($_GET['ajax'])) 
		{
			Zend_Controller_Action_HelperBroker::removeHelper('Layout');
		}
		
		//Sets the view variable $messages to contain the FlashMessenger array of messages
		$this->view->messages = $this->_helper->FlashMessenger->getMessages();
		
		
	}
	
    protected function _initView()
    {
    	$view = new Custom_Controller_Action_Helper_View($this->view);
		$this->view = $view->init();
    }
}