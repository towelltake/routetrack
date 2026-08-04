<?php
/**
* @name       PromoController
* @since
* @version    Release: 1
* @author     HD <hiren.d@elantechnologies.com>
* @copyright  Elan Technologies
* @param
*
* This controller is manage links like promotion,Price, Discount, Distribution etc.
*/
class Links_PromoController extends Links_Library_Controller_Action_Abstract
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
    * @name       promotionlinkAction
    * @since      31-01-2012
    * @version    Release: 1
    * @author     HD <hiren.d@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This action is use for the assign promotion key to selected customer
    */
    public function promotionlinkAction()
    {
		$this->view->formdata = $formdata = $this->_request->getPost();
	
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
			if($formdata['chkall_cus'] !='')
			{
			$formdata['hdnselectedcustomer']	= $formdata['hdnallcust'];
			$formdata['hdncount_customer']		= substr_count($formdata['hdnselectedcustomer'], ',');
			}
			
			$param_array 	= array();
			$param_array[1]	= $formdata['ddlpromotion_key'];
			$param_array[2]	= $formdata['ddlfillby'];
			$param_array[3]	= $formdata['hdnfillbyval'];
			$param_array[4]	= $formdata['ddlcategory'];
			$param_array[5]	= substr($formdata['hdnselectedcustomer'], 0, -1);	//selected question
			$param_array[6]	= $formdata['hdncount_customer'];			// get count for add record in child table
			
			$this->SFA_Comman->executequery('CALL sp_add_link_promo_promotionlink(?,?,?,?,?)',$param_array,'');
			
			SFA_Message::setMsg($this->translate->_('Update Record'));
			$this->_helper->redirector('promotionlink', 'promo', 'links');
		}
		else
		{
			$result = $this->SFA_Comman->executequery('CALL sp_get_links_promo_promokey()','','');
			$this->view->promokey_info 		= $result[0];
			$this->view->region_info 		= $result[1];
			$this->view->depot_info 		= $result[2];
			$this->view->area_info 		= $result[3];
			$this->view->route_info 		= $result[4];
			$this->view->customercat_info 	= $result[5];
		}
    }    
    /**
    * @name       getcustomerfrompromokeyAction
    * @since      10-07-2012
    * @version    Release: 1
    * @author     HD <hiren.d@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This action is use for the get customer list from selected criteria.
    */
    public function getcustomerfrompromokeyAction()
    {
		//view variable declaration
		$params = $this->getRequest()->getParams();
		
		$param_array 	= array();	
		$param_array[1]	= $params['fillby'];
		$param_array[2]	= $params['val'];
		$param_array[3]	= $params['catid'];
		$param_array[4]	= $params['promokey'];
		
		$result = $this->SFA_Comman->executequery('CALL sp_get_customer_from_promotionkey(?,?,?,?)',$param_array,'');
		echo Zend_Json::encode($result);
		exit;
    }
     /**
    * @name       pricelinkAction
    * @since      31-01-2012
    * @version    Release: 1
    * @author     HD <hiren.d@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This action is use for the assign price link to selected customer
    */
    public function pricelinkAction()
    {
		$this->view->formdata = $formdata = $this->_request->getPost();
	
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
			$param_array 	= array();
			$param_array[1]	= $formdata['ddlpricekey'];
			$param_array[2]	= $formdata['ddlfillby'];
			$param_array[3]	= $formdata['hdnfillbyval'];
			$param_array[4]	= $formdata['ddlcategory'];
			$param_array[5]	= substr($formdata['hdnselectedcustomer'], 0, -1);	//selected question
			$param_array[6]	= $formdata['hdncount_customer'];			// get count for add record in child table
			
			$this->SFA_Comman->executequery('CALL sp_add_link_promo_pricelink(?,?,?,?,?,?)',$param_array,'');
			
			SFA_Message::setMsg($this->translate->_('Update Record'));
			$this->_helper->redirector('pricelink', 'promo', 'links');
		}
		else
		{
			$result = $this->SFA_Comman->executequery('CALL sp_get_links_promo_pricekey()','','');
			$this->view->pricekey_info 		= $result[0];
			$this->view->region_info 		= $result[1];
			$this->view->depot_info 		= $result[2];
			$this->view->area_info 			= $result[3];
			$this->view->route_info 		= $result[4];
			$this->view->customercat_info 	= $result[5];
		}
    }    
    /**
    * @name       getcustomerfrompromokeyAction
    * @since      10-07-2012
    * @version    Release: 1
    * @author     HD <hiren.d@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This action is use for the get customer list from selected criteria.
    */
    public function getcustomerfrompricekeyAction()
    {
		//view variable declaration
		$params = $this->getRequest()->getParams();
		
		$param_array 	= array();	
		$param_array[1]	= $params['fillby'];
		$param_array[2]	= $params['val'];
		$param_array[3]	= $params['catid'];
		$param_array[4]	= $params['promokey'];
		
		$result = $this->SFA_Comman->executequery('CALL sp_get_customer_from_pricekey(?,?,?,?)',$param_array,'');
		echo Zend_Json::encode($result);
		exit;
    }
    /**
    * @name       discountlinkAction
    * @since      07-02-2012
    * @version    Release: 1
    * @author     HD <hiren.d@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This action is use for the assign promotion key to selected customer
    */
    public function discountlinkAction()
    {
		$this->view->formdata = $formdata = $this->_request->getPost();
	
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
			$param_array 	= array();
			$param_array[1]	= $formdata['ddldiscount'];
			$param_array[2]	= $formdata['ddlfillby'];
			$param_array[3]	= $formdata['hdnfillbyval'];
			$param_array[4]	= $formdata['ddlcategory'];
			$param_array[5]	= substr($formdata['hdnselectedcustomer'], 0, -1);	//selected question
			$param_array[6]	= $formdata['hdncount_customer'];			// get count for add record in child table
			
			$this->SFA_Comman->executequery('CALL sp_add_link_promo_discountkey(?,?,?,?,?,?)',$param_array,'');
			
			SFA_Message::setMsg($this->translate->_('Update Record'));
			$this->_helper->redirector('discountlink', 'promo', 'links');
		}
		else
		{
			$result = $this->SFA_Comman->executequery('CALL sp_get_links_promo_discountkey()','','');
			$this->view->discountkey_info	= $result[0];
			$this->view->region_info 		= $result[1];
			$this->view->depot_info 		= $result[2];
			$this->view->area_info 			= $result[3];
			$this->view->route_info 		= $result[4];
			$this->view->customercat_info 	= $result[5];
		}
    }
    /**
    * @name       getcustomerfromdiscountkeyAction
    * @since      10-07-2012
    * @version    Release: 1
    * @author     HD <hiren.d@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This action is use for the get customer list from selected criteria.
    */
    public function getcustomerfromdiscountkeyAction()
    {
		//view variable declaration
		$params = $this->getRequest()->getParams();
		
		$param_array 	= array();	
		$param_array[1]	= $params['fillby'];
		$param_array[2]	= $params['val'];
		$param_array[3]	= $params['catid'];
		$param_array[4]	= $params['promokey'];
		
		$result = $this->SFA_Comman->executequery('CALL sp_get_customer_from_discountkey(?,?,?,?)',$param_array,'');
		echo Zend_Json::encode($result);
		exit;
    }
    /**
    * @name       distributionlinkAction
    * @since      07-02-2012
    * @version    Release: 1
    * @author     HD <hiren.d@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This action is use for the assign disctribution key to selected customer
    */
    public function distributionlinkAction()
    {
		$this->view->formdata = $formdata = $this->_request->getPost();
	
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
			$param_array 	= array();
			$param_array[1]	= $formdata['ddldistribution'];
			$param_array[2]	= $formdata['ddlfillby'];
			$param_array[3]	= $formdata['hdnfillbyval'];
			$param_array[4]	= $formdata['ddlcategory'];
			$param_array[5]	= substr($formdata['hdnselectedcustomer'], 0, -1);	//selected question
			$param_array[6]	= $formdata['hdncount_customer'];			// get count for add record in child table
			
			$this->SFA_Comman->executequery('CALL sp_add_link_promo_distributionkey(?,?,?,?,?,?)',$param_array,'');
			
			SFA_Message::setMsg($this->translate->_('Update Record'));
			$this->_helper->redirector('distributionlink', 'promo', 'links');
		}
		else
		{
			$result = $this->SFA_Comman->executequery('CALL sp_get_links_promo_distributionkey()','','');
			$this->view->distribution_info 	= $result[0];
			$this->view->region_info 		= $result[1];
			$this->view->depot_info 		= $result[2];
			$this->view->area_info 			= $result[3];
			$this->view->route_info 		= $result[4];
			$this->view->customercat_info 	= $result[5];
		}
    }
    /**
    * @name       getcustomerfromdistributionkeyAction
    * @since      10-07-2012
    * @version    Release: 1
    * @author     HD <hiren.d@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This action is use for the get customer list from selected criteria.
    */
    public function getcustomerfromdistributionkeyAction()
    {
		//view variable declaration
		$params = $this->getRequest()->getParams();
		
		$param_array 	= array();	
		$param_array[1]	= $params['fillby'];
		$param_array[2]	= $params['val'];
		$param_array[3]	= $params['catid'];
		$param_array[4]	= $params['promokey'];
		
		$result = $this->SFA_Comman->executequery('CALL sp_get_customer_from_distributionkey(?,?,?,?)',$param_array,'');
		echo Zend_Json::encode($result);
		exit;
    }
}