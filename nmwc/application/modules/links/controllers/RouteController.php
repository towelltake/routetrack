<?php
/**
* @name       RouteController
* @since
* @version    Release: 1
* @author     HD <hiren.d@elantechnologies.com>
* @copyright  Elan Technologies
* @param
*
* This controller is manage links like ROute Item Group etc.
*/
class Links_RouteController extends Links_Library_Controller_Action_Abstract
{
    public $sec_lang 	= '';
    
    /**
    * @name       init
    * @since      31-01-2012
    * @version    Release: 1
    * @author     HD <hiren.d@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This is the default function for all Actions.
    *
    */
    public function init()
    {
        $this->translate = Zend_Registry::get('Zend_Translate');
        
        $this->currentUser = SFA_Loginauth::getIdentity();
        
        
        if(!isset($this->currentUser) || empty($this->currentUser))
        {
            SFA_Message::setMsg($this->translate->_('Do Login'));
            //$this->_helper->redirector("index", "index", "home");
			$url = $this->view->baseUrl();
			echo '<script type="text/javascript">window.location="'.$url.'";</script>';
			exit;
        }
        $this->view->required		= $this->translate->_('Required');
        $this->view->colan		= $this->translate->_('Colan');        
        $this->SFA_Comman 		= new SFA_Comman();
        $this->view->decimalplaces 	= $this->SFA_Comman->getdecimalplaces();
        $this->view->sec_lang		= $this->SFA_Comman->getsecondlanguage();
        $this->sec_lang 		= $this->view->sec_lang;
        $this->decimalplaces 		= $this->view->decimalplaces;
    }
    
     /**
    * @name       preDispatch
    * @since      26- sep-2012
    * @version    Release: 1
    * @author     PT <pankil@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This is the default function for all Actions.
    *
    */
    
    public function preDispatch()
    {
        parent::preDispatch();
        
        /**
         *      Acl Code start
         */
        $getparams_init = $this->getRequest()->getParams();
        $getpost_init = $this->_request->getPost();
        
        if(in_array($getparams_init['action'],$this->current_read_delete_arr))
        {
            if($getpost_init["hdDelete"]==1 && !$this->checkaccess("delete")) {
            
                $this->_forward('noaccess','aclaccess','home', array("actiontype"=>"delete","modulename"=>$this->currentmodulename));
            
            } elseif(!$this->checkaccess("read")) {
                
                $this->_forward('noaccess','aclaccess','home', array("actiontype"=>"read","modulename"=>$this->currentmodulename));
                
            } else {
                
            }
        }
        elseif(in_array($getparams_init['action'],$this->current_insert_update_arr))
        {
            if($params['id'] > 0 && !$this->checkaccess("update")) {
            
                $this->_forward('noaccess','aclaccess','home', array("actiontype"=>"update","modulename"=>$this->currentmodulename));
                
            } elseif(!$this->checkaccess("insert")) {
                
                $this->_forward('noaccess','aclaccess','home', array("actiontype"=>"insert","modulename"=>$this->currentmodulename));
                
            } else {
                
            }
        }
        /**
         *      Acl Code end
         */
    }
    
    /**
    * @name       routeitemlinkAction
    * @since      07-02-2012
    * @version    Release: 1
    * @author     HD <hiren.d@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This action is use for the assign category to selected route
    */
    public function routeitemlinkAction()
    {
        $this->view->formdata = $formdata = $this->_request->getPost();
	
        $fill_by = array();
            $fill_by[0]['id']  = 1;
        $fill_by[0]['val'] = 'By Region';
        $fill_by[1]['id']  = 2;
        $fill_by[1]['val'] = 'By Depot';
        $fill_by[2]['id']  = 3;
        $fill_by[2]['val'] = 'By Area';        
        $this->view->fill_by = $fill_by;
        
        if(count($formdata) > 0)
        {
            if($formdata['chkall_route'] !='')
            {
                $formdata['ddlselectedroute']	= $formdata['hdnallroute'];
                $formdata['hdncount_route']	= substr_count($formdata['hdnselectedroute'], ',');	
            }
            
            $param_array = array();
            $param_array[1]	= $formdata['ddlrouteitem'];
            $param_array[2]	= $formdata['ddlfillby'];
            $param_array[3]	= $formdata['hdnfillbyval'];
            $param_array[4]	= substr($formdata['hdnselectedroute'], 0, -1);	//selected question
            $param_array[5]	= $formdata['hdncount_route'];			// get count for add record in child table
            
            $this->SFA_Comman->executequery('CALL sp_add_link_route_routeitemgroup(?,?,?,?,?)',$param_array,'');
            
            SFA_Message::setMsg($this->translate->_('Update Record'));	 
            $this->_helper->redirector('routeitemlink', 'route', 'links');
        }
        else
        {
            $result = $this->SFA_Comman->executequery('CALL sp_get_links_route_routeitemlink()','','');
            $this->view->routeitem_info 	= $result[0];
            $this->view->region_info 		= $result[1];
            $this->view->depot_info 		= $result[2];
            $this->view->area_info 		= $result[3];
        }
    }
    /**
    * @name       getcustomerfromrouteAction
    * @since      10-07-2012
    * @version    Release: 1
    * @author     HD <hiren.d@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This action is use for the get customer list from selected routecode.
    */
    public function getroutefromcriteriaAction()
    {
		//view variable declaration
		$params = $this->getRequest()->getParams();
		
		$param_array 	= array();
		$param_array[1]	= $params['fillby'];		
		$param_array[2]	= $params['val'];
		$param_array[3]	= $params['routeitmgrp'];
		
		
		$result = $this->SFA_Comman->executequery('CALL sp_get_route_from_criteria(?,?,?)',$param_array,'');
		echo Zend_Json::encode($result);
		exit;
    }
    /**
    * @name       activenonactive
    * @since      1-2-2012
    * @version    Release: 1
    * @author     PM <pankit@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This action is use for the active inactive item
    */
    public function activenonactiveAction()
    {
        $this->view->formdata = $formdata = $this->_request->getPost();
		if(count($formdata) > 0)
		{
			$param_array = array();
			$param_array[1]	= $formdata['ddlitemgrp'];
			$param_array[2]	= substr($formdata['hdnselecteditem'], 0, -1);	//selected question
			$param_array[3]	= $formdata['hdncount_items'];			// get count for add record in child table
			
			$this->SFA_Comman->executequery('CALL sp_edit_links_route_activenonactiveitem(?,?,?)',$param_array,'');
			
			SFA_Message::setMsg($this->translate->_('Update Record'));	 
			$this->_helper->redirector('activenonactive', 'route', 'links');
		}
		else
		{
			$result = $this->SFA_Comman->executequery('CALL sp_combo_itemgroup()','','');
			$this->view->itemgroup_info 	= $result[0];			
		}
    }
	/**/
	
	 public function itemgroupAction()
    {
        $this->view->formdata = $formdata = $this->_request->getPost();
		if(count($formdata) > 0)
		{
			$param_array = array();
			$param_array[1]	= $formdata['ddlitemgrpto'];
			$param_array[2]	= substr($formdata['hdnselecteditem'], 0, -1);	//selected question			
			$this->SFA_Comman->executequery('CALL sp_edit_links_route_itemsgropuchange(?,?)',$param_array,'');			
			SFA_Message::setMsg($this->translate->_('Update Record'));	 
			$this->_helper->redirector('itemgroup', 'route', 'links');
		}
		else
		{
			$result = $this->SFA_Comman->executequery('CALL sp_combo_itemgroup()','','');
			$this->view->itemgroup_info 	= $result[0];			
		}
    }
	/**/
	/**
    * @name       getitemfromitemgrpAction
    * @since      13-02-2012
    * @version    Release: 1
    * @author     HD <hiren.d@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This action is use for the getting item's on the selection of the item group.
    */
	public function getitemfromitemgrpAction()
	{
		//view variable declaration
		$params = $this->getRequest()->getParams();
		
		$param_array 	= array();
		$param_array[1]	= $params['itemgrp'];		
		
		$result = $this->SFA_Comman->executequery('CALL sp_get_links_route_activenonactiveitem(?)',$param_array,'');
		echo Zend_Json_Encoder::encode($result);
		exit;
	}
	
    /**
    * @name       outletproductlinkAction
    * @since      13-02-2012
    * @version    Release: 1
    * @author     HD <hiren.d@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This action is use for the assign outlet item link to selected customer
    */
    public function outletproductlinkAction()
    {
		$this->view->formdata 		= $formdata = $this->_request->getPost();
		
        $fill_by = array();
        $fill_by[0]['id']  = 1;
		$fill_by[0]['val'] = 'By Region';
		$fill_by[1]['id']  = 2;
		$fill_by[1]['val'] = 'By Depot';
		$fill_by[2]['id']  = 3;
		$fill_by[2]['val'] = 'By Area';
		$fill_by[3]['id']  = 4;
		$fill_by[3]['val'] = 'By Route';
		$this->view->fill_by = $fill_by;	

		if(count($formdata) > 0)
		{
			$param_array = array();
			
			
			$param_array[1]	= $formdata['ddloutletprod'];
			$param_array[2]	= $formdata['ddlfillby'];
			$param_array[3]	= $formdata['hdnfillbyval'];
			$param_array[4]	= $formdata['ddlcategory'];			
			$param_array[5]	= substr($formdata['hdnselectedcustomer'], 0, -1);	//selected question
			$param_array[6]	= $formdata['hdncount_customer'];			// get count for add record in child table
			
			$this->SFA_Comman->executequery('CALL sp_add_link_route_outletproductcode(?,?,?,?,?,?)',$param_array,'');
			
			SFA_Message::setMsg($this->translate->_('Update Record'));	 
			$this->_helper->redirector('outletproductlink', 'route', 'links');
		}
		else
		{
			$result = $this->SFA_Comman->executequery('CALL sp_get_links_route_outletitemlink()','','');
			
			$this->view->outletproductcode	= $result[0];
			$this->view->regionmaster		= $result[1];
			$this->view->depotmaster 		= $result[2];
			$this->view->areamaster			= $result[3];
			$this->view->routemaster 		= $result[4];
			$this->view->customercategory	= $result[6];
		}	
    }
    /**
    * @name       getcustomerfromoutletproductcodeAction
    * @since      10-07-2012
    * @version    Release: 1
    * @author     HD <hiren.d@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This action is use for the get customer list from selected criteria.
    */
    public function getcustomerfromoutletproductcodeAction()
    {
		//view variable declaration
		$params = $this->getRequest()->getParams();
		
		$param_array 	= array();
		$param_array[1]	= $params['outletprod'];
		$param_array[2]	= $params['catid'];
		$param_array[3]	= $params['fillby'];
		$param_array[4]	= $params['val'];
		$param_array[5]	= $params['country'];
		
		$result = $this->SFA_Comman->executequery('CALL sp_get_customer_from_outletproductcode(?,?,?,?,?,?)',$param_array,'');
		echo Zend_Json::encode($result);
		exit;
    }
}