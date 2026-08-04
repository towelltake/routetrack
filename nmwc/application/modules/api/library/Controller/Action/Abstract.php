<?php
abstract class Api_Library_Controller_Action_Abstract 	extends Custom_Controller_Action_Abstract
{

    public function init()
    {
	$this->_initView();
	/*
	    For system Setting
	*/
	parent::init();
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
        $this->_helper->layout->disableLayout();
        $this->_helper->viewRenderer->setNoRender(TRUE);
    }

    protected function _initView()
    {
    	$view = new Custom_Controller_Action_Helper_View($this->view);
	$this->view = $view->init();
    }
}