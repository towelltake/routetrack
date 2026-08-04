<?php

/**
* @name       Abstract Class
* @since      17-10-2011
* @version    Release: 1
* @author     HD <hiren.d@elantechnologies.com>
* @copyright  Elan Technologies
* @param
* 
* 
*/
abstract class Custom_Controller_Action_Abstract extends Zend_Controller_Action 
{
    public $sys;
    public $acl_modulename;
    public $currentmodule;
    public $moduleacl;
    public $current_read_delete_arr;
    public $current_insert_update_arr;
    /**
    * @name       init
    * @since      17-10-2011
    * @version    Release: 1
    * @author     HD <hiren.d@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This action is a default action for front module and in this we have define system settings
    * 
    */
    public function init()
    {
        
    }
    
    
     /**
    * @name       init
    * @since      24-sep-2012
    * @version    Release: 1
    * @author     PT <pankil@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This action is a to get the current module name
    * 
    */
    function getmodulename()
    {
        $current_module     = $this->_getParam('module');
        $current_controller = $this->_getParam('controller');
        $current_action     = $this->_getParam('action');
    	
        $SFA_Moduleacl = new SFA_Moduleacl($current_module , $current_controller ,$current_action);
        $this->view->currentmodulename = $this->currentmodulename = $SFA_Moduleacl->acl_module;
        
        $this->view->current_read_delete_arr = $this->current_read_delete_arr = $SFA_Moduleacl->acl_read_delete_arr;
        $this->view->current_insert_update_arr = $this->current_insert_update_arr = $SFA_Moduleacl->acl_insert_update_arr;
    }
    
    
    /**
    * @name       checkaccess
    * @since      17-10-2011
    * @version    Release: 1
    * @author     PT <pankil@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This action is to checkaccess of the current module
    * 
    */
    function checkaccess($action)
    {
        if(isset($this->moduleacl->acl[$this->currentmodulename][$action]))
            return $this->moduleacl->acl[$this->currentmodulename][$action];
        else
            return true;
    }
    
    /**
     * Helper method to redirect to a specific action or controller from a
     * specific module, via a specified route(or not) with specified parameters
     *
     * @param string $controller / $url which contains http in its composition
     * @param string $action
     * @param string $module
     * @param array  $params
     * @param string $route
     * @param boolean $reset
     */
    public function redirect($controller = 'index', $action = 'index', $module = 'home', $params = array(), $route = null, $reset = true )
    {
    	$this->_redirect = $this->_helper->getHelper('Redirector');
    	
    	$current_controller = $this->_getParam('controller');
    	$current_action     = $this->_getParam('action');
    	$current_module     = $this->_getParam('module');

    	if ($current_controller == $controller && 
    		$current_action == $action && 
    		$current_module == $module)
    	{
    		return TRUE;
    	}
    	
    	if (strstr($controller, 'http'))
    	{
    		if (DEBUG && (!$this->_request->isXmlHttpRequest() && !isset($_GET['ajax'])))
    		{
				debug_redirect($controller);
    		}
    		else
    		{
	    		return $this->_redirect($controller, array('code' => 301));
    		}
    	}
    	
    	if (DEBUG && (!$this->_request->isXmlHttpRequest() && !isset($_GET['ajax'])))
    	{
    		$url = 'http://' . $_SERVER['HTTP_HOST']
    			   . $this->view->url(array_merge(array('controller' => $controller, 'action' => $action, 'module' => $module), $params), $route, $reset);
    		debug_redirect($url);
    	}
    	else
    	{
    		if ($route !== null)
    		{
    			$params = array_merge(array('action'     => $action,
							'controller' => $controller,
                                   			'module'     => null), $params);
    			
    			return $this->_redirect->setCode(301)
    			                       ->setExit(true)
    			                       ->gotoRoute($params, $route, $reset);
    		}
    		
	    	return $this->_redirect->setCode(301)
	    				    	   ->setExit(true)
	                      		   ->gotoSimpleAndExit($action,
	                                             	   $controller,
	                                             	   $module,
	                                             	   $params);
    	}
    }
}