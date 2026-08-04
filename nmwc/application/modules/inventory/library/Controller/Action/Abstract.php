<?php
abstract class Inventory_Library_Controller_Action_Abstract extends Custom_Controller_Action_Abstract
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