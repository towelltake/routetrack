<?php

class AclaccessController extends Home_Library_Controller_Action_Abstract
{
    /**
    * @name       init
    * @since      02-09-2011
    * @version    Release: 1
    * @author     HD <hiren.d@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param   	
    *
    * This is the default function for all Actions.
    *
    */
    
    //Initilize var for App Model
    private $index = "";
    
    public function init()
    {
        
		$this->translate 			= Zend_Registry::get('Zend_Translate');
		$this->view->required		= $this->translate->_('Required');
		$this->view->colan			= $this->translate->_('Colan');
		
		$this->SFA_Comman 			= new SFA_Comman();
    }
    
    /**
    * @name       noaccessAction
    * @since      26-sep-2012
    * @version    Release: 1
    * @author     PT <pankil@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param   	
    *
    * This is the Noaccess 
    *
    */
    public function noaccessAction()
    {
		
	}
}