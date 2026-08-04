<?php
/**
* @name       IndexController
* @since      
* @version    Release: 1
* @author     HD <hiren.d@elantechnologies.com>
* @copyright  Elan Technologies
* @param   	
*
* This controller is manage user signup module.
*/
class Admin_CpanelController extends Admin_Library_Controller_Action_Abstract
{
    /**
    * @name       init
    * @since      30-11-2011
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
		$this->translate 	= Zend_Registry::get('Zend_Translate');
		
		$this->currentUser = SFA_Loginauth::getIdentity();	
		if(!isset($this->currentUser) || empty($this->currentUser))
		{
			SFA_Message::setMsg($this->translate->_('Do Login'));
			//$this->_helper->redirector("index", "index", "home");
			$url = $this->view->baseUrl();
			echo '<script type="text/javascript">window.location="'.$url.'";</script>';
			exit;
		}
		$this->view->required	= $this->translate->_('Required');
		$this->view->colan		= $this->translate->_('Colan');
		
		$this->view->title 		= $this->translate->_('Control Panel');
		$this->view->general 	= $this->translate->_('General');
		$this->view->route 		= $this->translate->_('Route');
		$this->view->customer 	= $this->translate->_('Customer');
		$this->view->report 	= $this->translate->_('Report');
		
		$this->SFA_Comman = new SFA_Comman();
		$this->decimalplaces 		= $this->SFA_Comman->getdecimalplaces();
		$this->view->decimalplaces 	= $this->decimalplaces ;	
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
    * @name       Index
    * @since      30-11-2011
    * @version    Release: 1
    * @author     HD <hiren.d@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param   	
    *
    * This Action is for setup website setting
    */
    public function indexAction()
    {
		$this->view->params = $params = $this->getRequest()->getParams();
			$this->view->formdata = $formdata = $this->_request->getPost();	
			
		/**
		 * Hiren dave on 15th march 
		 * In chkcpanel & rdbtncpaneli have stored all checkbox & radio button values
		 * from that i stored in array and pass into .....
		 *
		*/
		
		if(count($formdata) > 0) {
		   
			$param_array = array();		
			$j=1;
			
			// below code for remove value from starting load method combo while we change the value from A to B			
			//$starting_load_method 		= array(72,60,47,34,54,15);
			//$hdn_starting_load_method	= $formdata['hdn_starting_load_method'];
			
			//$list 			= array();
			//$list[] 			= $starting_load_method;
			//$list[] 			= $formdata['chkcpanel'];
			//$intersect 		= call_user_func_array('array_intersect',$list);
			//$cnt_intersect 	= count($intersect);
			//$key = array_search(15,$formdata['chkcpanel']);			
			/*update records for General tab*/
			//for($i=0;$i<count($formdata['chkcpanel']);$i++){
			//	if($cnt_intersect > 1 && $hdn_starting_load_method == $formdata['chkcpanel'][$i]){					
			//		//do nothing
			//	} else {
			//		$param_array[$j] = $formdata['chkcpanel'][$i];
			//		$j++;
			//	}
			//}
			
			
			for($i=0;$i<count($formdata['chkcpanel']);$i++){
				$param_array[$j] = $formdata['chkcpanel'][$i];
				$j++;
			}
			
			//if($formdata['hdnpdcclr'])
			//	$formdata['rdbtncpanel'][1] = $formdata['hdnpdcclr'];
			
			if(count($formdata['rdbtncpanel']) > 0) {			
				foreach ($formdata['rdbtncpanel'] as $key => $val) {
					$param_array[$j] = $val;
					$j++;
				}
			}
			
			/*update records for route tab*/
			for($i=0;$i<count($formdata['chkcpanelroute']);$i++) {
				$param_array[$j] = $formdata['chkcpanelroute'][$i];
				$j++;
			}
			
			/*update records for customer tab*/
			for($i=0;$i<count($formdata['chkcpanelcustomer']);$i++){
				$param_array[$j] = $formdata['chkcpanelcustomer'][$i];
				$j++;
			}
			
			if($formdata['hdncustcode'])
				$formdata['rdbtncpanelcustomer'] = $formdata['hdncustcode'];
				
			for($i=0;$i<count($formdata['rdbtncpanelcustomer']);$i++){
				$param_array[$j] = $formdata['rdbtncpanelcustomer'][$i];
				$j++;
			}
			
			/*update records for Item tab*/
			for($i=0;$i<count($formdata['chkcpanelitem']);$i++){
				$param_array[$j] = $formdata['chkcpanelitem'][$i];
				$j++;
			}
			
			if(!empty($param_array)){
				$parameter = implode(',',$param_array);
			}
			else{
				$parameter = "''";
			}
			$form_array 	= array();
			$form_array[1]	= $parameter;
			$form_array[2]	= $formdata['txtcost_price'];
			$form_array[3]	= ($formdata['txtroundingoff']<>""?$formdata['txtroundingoff']:0);
			$form_array[4]	= $formdata['ddlsalesmanload'];
			$form_array[5]	= $formdata['closingtime'];
			
			$updated_result = $this->SFA_Comman->executequery('CALL sp_edit_admin_cpanel_general(?)',$form_array,'');
			$resultarr = $updated_result[0];
			$header_menu = array();
			for($i=0;$i<count($resultarr);$i++)
			{
				$header_menu[$resultarr[$i]['flagname']] = $resultarr[$i];
			}
			
			$Menu_NameSpace = new Zend_Session_Namespace('Menu');
			unset($Menu_NameSpace->header_menu);
			$Menu_NameSpace->header_menu	= $header_menu;
			
			$resarr = $updated_result[1];
			$settings = array();
			for($i=0;$i<count($resarr);$i++)
			{
				$settings[$resarr[$i]['flagname']] = $resarr[$i];
			}
			$Settings_NameSpace = new Zend_Session_Namespace('Settings');
			unset($Settings_NameSpace->settings);
			unset($Settings_NameSpace->cpanel);
			
			$cpanel 		= $updated_result[2];
			$cp_settings 	= array();
			for($i=0;$i<count($cpanel);$i++)
			{
				$cp_settings[$cpanel[$i]['flagname']] = $cpanel[$i];
			}
			
			
			$cp_settings[$updated_result[3][0]['flagname']] = $updated_result[3][0];
			$cp_settings[$updated_result[3][1]['flagname']] = $updated_result[3][1];
			
			$Settings_NameSpace->settings	= $settings;
			$Settings_NameSpace->cpanel		= $cp_settings;
			
			SFA_Message::setMsg($this->translate->_('Update Record'));
		}
	
		//Fetch data for All Tab
		$result = $this->SFA_Comman->executequery('CALL sp_get_admin_cpanel_general()','','');
		
		foreach($result[0] as $val)
		{
			$res[$val["flagname"]] = $val;
		}
		$res[$result[1][0]["flagname"]] = $result[1][0];
		$res[$result[1][1]["flagname"]] = $result[1][1];
		
		$this->view->cpanel_data  		= $res;
		$this->view->totalsum 			= $result[2][0]['quantity'];
		$this->view->selected_flagid  	= $result[2][0]['fgid'];
	}    
}