<?php
class Custom_View_Helper_Checkaccess extends Zend_View_Helper_Abstract
{
	public $view;
	
	public function __construct($view)
	{
		$this->view = $view;
	}
	
	public function checkaccess($action)
	{
        if(isset($this->view->moduleacl->acl[$this->view->currentmodulename][$action]))
            return $this->view->moduleacl->acl[$this->view->currentmodulename][$action];
        else
            return true;
	}
}