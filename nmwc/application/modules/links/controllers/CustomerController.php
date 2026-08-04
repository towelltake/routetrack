<?php
/**
* @name       CustomerController
* @since
* @version    Release: 1
* @author     HD <hiren.d@elantechnologies.com>
* @copyright  Elan Technologies
* @param
*
* This controller is manage links like promotion,Price, Discount, Distribution etc.
*/
class Links_CustomerController extends Links_Library_Controller_Action_Abstract
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
    * @name       customercatlinkAction
    * @since      07-02-2012
    * @version    Release: 1
    * @author     HD <hiren.d@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This action is use for the assign customer category to selected customer
    */
    public function customercatlinkAction()
    {
        $this->view->formdata = $formdata = $this->_request->getPost();
	
	if(count($formdata) > 0)
	{	    
	    $param_array = array();
	    $param_array[1]	= $formdata['ddlcategory'];
		$param_array[2]	= $formdata['ddlroute'];
	    $param_array[3]	= substr($formdata['hdnselectedcustomer'], 0, -1);	//selected question
	    $param_array[4]	= $formdata['hdncount_customer'];			// get count for add record in child table
	    
	    $this->SFA_Comman->executequery('CALL sp_add_link_customer_customercatlink(?,?,?,?)',$param_array,'');
	    
	    SFA_Message::setMsg($this->translate->_('Update Record'));	 
	    $this->_helper->redirector('customercatlink', 'customer', 'links');
	}
	else
	{
	    $result = $this->SFA_Comman->executequery('CALL sp_get_links_customer_catomercat()','','');
	    $this->view->customercat_info 	= $result[0];
	    $this->view->route_info 		= $result[1];
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
    public function getcustomerfromrouteAction()
    {
	//view variable declaration
	$params = $this->getRequest()->getParams();
	
	$param_array 	= array();
	$param_array[1]	= $params['catid'];
	$param_array[2]	= $params['routeid'];
	
	
	$result = $this->SFA_Comman->executequery('CALL sp_get_customer_from_routecode(?,?)',$param_array,'');
	echo Zend_Json::encode($result);
	exit;
    }
    /**
    * @name       surveylinkAction
    * @since      07-02-2012
    * @version    Release: 1
    * @author     HD <hiren.d@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This action is use for the assign survey to selected customer
    */
    public function surveylinkAction()
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
			$param_array = array();
			$param_array[1]	= $formdata['ddlsurvey'];
			$param_array[2]	= $formdata['ddlfillby'];
			$param_array[3]	= $formdata['hdnfillbyval'];
			$param_array[4]	= $formdata['ddlcategory'];
			$param_array[5]	= substr($formdata['hdnselectedcustomer'], 0, -1);	//selected question
			$param_array[6]	= $formdata['hdncount_customer'];					//get count for add record in child table
			
			$this->SFA_Comman->executequery('CALL sp_add_link_customer_surveylink(?,?,?,?,?,?)',$param_array,'');
			
			SFA_Message::setMsg($this->translate->_('Update Record'));	 
			$this->_helper->redirector('surveylink', 'customer', 'links');
		}
		else
		{
			$result = $this->SFA_Comman->executequery('CALL sp_get_links_customer_survey()','','');
			$this->view->survey_info 		= $result[0];
			$this->view->region_info 		= $result[1];
			$this->view->depot_info 		= $result[2];
			$this->view->area_info 			= $result[3];
			$this->view->route_info 		= $result[4];
			$this->view->customercat_info 	= $result[5];
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
    public function getcustomerfromsurveyrouteAction()
    {
	//view variable declaration
	$params = $this->getRequest()->getParams();
	
	$param_array 	= array();	
	$param_array[1]	= $params['fillby'];
	$param_array[2]	= $params['val'];
	$param_array[3]	= $params['catid'];
	$param_array[4]	= $params['surveykey'];
	
	
	$result = $this->SFA_Comman->executequery('CALL sp_get_customer_from_surveyroutecode(?,?)',$param_array,'');
	echo Zend_Json::encode($result);
	exit;
    }
    
    /**
    * @name       distributionchecklinkAction
    * @since      09-03-2015
    * @version    Release: 1
    * @author     CS <chetan@e2logy.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This action is use for the assign survey to selected customer
    */
    public function distributionchecklinkAction()
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
			$param_array = array();
			$param_array[1]	= $formdata['ddldistribution'];
			$param_array[2]	= $formdata['ddlfillby'];
			$param_array[3]	= $formdata['hdnfillbyval'];
			$param_array[4]	= $formdata['ddlcategory'];
			$param_array[5]	= substr($formdata['hdnselectedcustomer'], 0, -1);	//selected question
			$param_array[6]	= $formdata['hdncount_customer'];					//get count for add record in child table
			
			$this->SFA_Comman->executequery('CALL sp_add_link_customer_distributionlink(?,?,?,?,?,?)',$param_array,'');
			
			SFA_Message::setMsg($this->translate->_('Update Record'));	 
			$this->_helper->redirector('distributionchecklink', 'customer', 'links');
		}
		else
		{
			$result = $this->SFA_Comman->executequery('CALL sp_get_links_customer_distribution()','','');
			$this->view->distribution_info 		= $result[0];
			$this->view->region_info 		= $result[1];
			$this->view->depot_info 		= $result[2];
			$this->view->area_info 			= $result[3];
			$this->view->route_info 		= $result[4];
			$this->view->customercat_info 	= $result[5];
		}
    }
    
    /**
    * @name       getcustomerfromdistributionroute
    * @since      09-03-2015
    * @version    Release: 1
    * @author     CS <chetan@e2logy.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This action is use for the get customer list from selected routecode.
    */
    public function getcustomerfromdistributionrouteAction()
    {
	//view variable declaration
	$params = $this->getRequest()->getParams();
	
	$param_array 	= array();	
	$param_array[1]	= $params['fillby'];
	$param_array[2]	= $params['val'];
	$param_array[3]	= $params['catid'];
	$param_array[4]	= $params['distributionkey'];
	
	$result = $this->SFA_Comman->executequery('CALL sp_get_customer_from_distributionroutecode(?,?)',$param_array,'');
	echo Zend_Json::encode($result);
	exit;
    }
    
    /**
    * @name       planogramlink
    * @since      09-03-2015
    * @version    Release: 1
    * @author     CS <chetan@e2logy.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This action is use for the assign survey to selected customer
    */
    public function planogramlinkAction()
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
			$param_array = array();
			$param_array[1]	= $formdata['ddlplanogram'];
			$param_array[2]	= $formdata['ddlfillby'];
			$param_array[3]	= $formdata['hdnfillbyval'];
			$param_array[4]	= $formdata['ddlcategory'];
			$param_array[5]	= substr($formdata['hdnselectedcustomer'], 0, -1);	//selected question
			$param_array[6]	= $formdata['hdncount_customer'];					//get count for add record in child table
			
			$this->SFA_Comman->executequery('CALL sp_add_link_customer_planogramlink(?,?,?,?,?,?)',$param_array,'');
			
			SFA_Message::setMsg($this->translate->_('Update Record'));	 
			$this->_helper->redirector('planogramlink', 'customer', 'links');
		}
		else
		{
			$result = $this->SFA_Comman->executequery('CALL sp_get_links_customer_planogram()','','');
			$this->view->planogram_info 		= $result[0];
			$this->view->region_info 		= $result[1];
			$this->view->depot_info 		= $result[2];
			$this->view->area_info 			= $result[3];
			$this->view->route_info 		= $result[4];
			$this->view->customercat_info 	= $result[5];
		}
    }
    public function planogramkeyAction()
    {
		 
        $this->view->formdata = $formdata = $this->_request->getPost();
	
	if(count($formdata) > 0)
	{	    
	    $param_array = array();
	    $param_array[1]	= $formdata['ddlcategory'];
		$param_array[2]	= $formdata['ddlroute'];
	    $param_array[3]	= substr($formdata['hdnselectedcustomer'], 0, -1);	//selected question
	    $param_array[4]	= $formdata['hdncount_customer'];			// get count for add record in child table
// print_r($param_array[3]);exit;
	  // echo $param_array[1];exit;
	    $this->SFA_Comman->executequery('CALL sp_customer_planogram_key_update(?,?,?,?)',$param_array,'');
	    
	    SFA_Message::setMsg($this->translate->_('Update Record'));	 
	    $this->_helper->redirector('planogramkey', 'customer', 'links');
	}
	else
	{
	    $result = $this->SFA_Comman->executequery('CALL sp_combo_planogramkeycall()','','');
		
	    $this->view->customercat_info 	= $result[0];
	    $this->view->route_info 		= $result[1];
	}
    }
	
	  public function getcustomerfromplanogramkeyAction()
    {
	//view variable declaration
	$params = $this->getRequest()->getParams();
	
	$param_array 	= array();
	$param_array[1]	= $params['catid'];
	$param_array[2]	= $params['routeid'];
	
	
	$result = $this->SFA_Comman->executequery('CALL sp_get_customer_from_planogramkey(?,?)',$param_array,'');
	echo Zend_Json::encode($result);
	exit;
    }
    /**
    * @name       getcustomerfromplanogramroute
    * @since      09-03-2015
    * @version    Release: 1
    * @author     CS <chetan@e2logy.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This action is use for the get customer list from selected routecode.
    */
    public function getcustomerfromplanogramrouteAction()
    {
	//view variable declaration
	$params = $this->getRequest()->getParams();
	
	$param_array 	= array();	
	$param_array[1]	= $params['fillby'];
	$param_array[2]	= $params['val'];
	$param_array[3]	= $params['catid'];
	$param_array[4]	= $params['planogramkey'];
	
	$result = $this->SFA_Comman->executequery('CALL sp_get_customer_from_planogramroutecode(?,?)',$param_array,'');
	echo Zend_Json::encode($result);
	exit;
    }
    
    /**
    * @name       advertisinglink
    * @since      09-03-2015
    * @version    Release: 1
    * @author     CS <chetan@e2logy.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This action is use for the assign survey to selected customer
    */
    public function advertisinglinkAction()
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
			$param_array = array();
			$param_array[1]	= $formdata['ddladvertising'];
			$param_array[2]	= $formdata['ddlfillby'];
			$param_array[3]	= $formdata['hdnfillbyval'];
			$param_array[4]	= $formdata['ddlcategory'];
			$param_array[5]	= substr($formdata['hdnselectedcustomer'], 0, -1);	//selected question
			$param_array[6]	= $formdata['hdncount_customer'];					//get count for add record in child table
			
			$this->SFA_Comman->executequery('CALL sp_add_link_customer_advertisinglink(?,?,?,?,?,?)',$param_array,'');
			
			SFA_Message::setMsg($this->translate->_('Update Record'));	 
			$this->_helper->redirector('advertisinglink', 'customer', 'links');
		}
		else
		{
			$result = $this->SFA_Comman->executequery('CALL sp_get_links_customer_advertising()','','');
			$this->view->advertising_info 		= $result[0];
			$this->view->region_info 		= $result[1];
			$this->view->depot_info 		= $result[2];
			$this->view->area_info 			= $result[3];
			$this->view->route_info 		= $result[4];
			$this->view->customercat_info 	= $result[5];
		}
    }
    
    /**
    * @name       getcustomerfromadvertisingroute
    * @since      09-03-2015
    * @version    Release: 1
    * @author     CS <chetan@e2logy.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This action is use for the get customer list from selected routecode.
    */
    public function getcustomerfromadvertisingrouteAction()
    {
	//view variable declaration
	$params = $this->getRequest()->getParams();
	
	$param_array 	= array();	
	$param_array[1]	= $params['fillby'];
	$param_array[2]	= $params['val'];
	$param_array[3]	= $params['catid'];
	$param_array[4]	= $params['advertisingkey'];
	
	$result = $this->SFA_Comman->executequery('CALL sp_get_customer_from_advertisingroutecode(?,?)',$param_array,'');
	echo Zend_Json::encode($result);
	exit;
    }
    
    /**
    * @name       itemmustlinkAction
    * @since      07-02-2012
    * @version    Release: 1
    * @author     HD <hiren.d@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This action is use for the assign Item Must to selected customer
    */
    public function itemmustlinkAction()
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
			
		if(count($formdata) > 0) {
			$param_array = array();
			$param_array[1]	= $formdata['ddlitemmustkey'];
			$param_array[2]	= $formdata['ddlfillby'];
			$param_array[3]	= $formdata['hdnfillbyval'];
			$param_array[4]	= $formdata['ddlcategory'];
			$param_array[5]	= substr($formdata['hdnselectedcustomer'], 0, -1);	//selected question
			$param_array[6]	= $formdata['hdncount_customer'];					//get count for add record in child table
			
			$this->SFA_Comman->executequery('CALL sp_add_link_customer_itemmustlink(?,?,?,?,?,?)',$param_array,'');
			
			SFA_Message::setMsg($this->translate->_('Update Record'));	 
			$this->_helper->redirector('itemmustlink', 'customer', 'links');
		}
		else
		{
			$result = $this->SFA_Comman->executequery('CALL sp_get_links_customer_itemmust()','','');
			$this->view->survey_info 		= $result[0];
			$this->view->region_info 		= $result[1];
			$this->view->depot_info 		= $result[2];
			$this->view->area_info 			= $result[3];
			$this->view->route_info 		= $result[4];
			$this->view->customercat_info 	= $result[5];
		}
    }
    /**
    * @name       getcustomerfromitemmustrouteAction
    * @since      10-07-2012
    * @version    Release: 1
    * @author     HD <hiren.d@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This action is use for the get customer list from selected routecode.
    */
    public function getcustomerfromitemmustrouteAction()
    {
	//view variable declaration
	$params = $this->getRequest()->getParams();
	
	$param_array 	= array();	
	$param_array[1]	= $params['fillby'];
	$param_array[2]	= $params['val'];
	$param_array[3]	= $params['catid'];
	$param_array[4]	= $params['itemmustkey'];
	
	
	$result = $this->SFA_Comman->executequery('CALL sp_get_customer_from_itemmustroutecode(?,?)',$param_array,'');
	echo Zend_Json::encode($result);
	exit;
    }
    
    /**
    * @name       pricesurveykeylinkAction
    * @since      30-04-2015
    * @version    Release: 1
    * @author     CS <chetan@e2logy.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This action is use for the assign survey to selected customer
    */
    public function pricesurveylinkAction()
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
	    $param_array = array();
	    $param_array[1]	= $formdata['ddlsurvey'];
	    $param_array[2]	= $formdata['ddlfillby'];
	    $param_array[3]	= $formdata['hdnfillbyval'];
	    $param_array[4]	= $formdata['ddlcategory'];
	    $param_array[5]	= substr($formdata['hdnselectedcustomer'], 0, -1);	//selected question
	    $param_array[6]	= $formdata['hdncount_customer'];					//get count for add record in child table
	    //var_dump($param_array);die;
	    $this->SFA_Comman->executequery('CALL sp_add_link_customer_pricesurveylink(?,?,?,?,?,?)',$param_array,'');
	    
	    SFA_Message::setMsg($this->translate->_('Update Record'));	 
	    $this->_helper->redirector('pricesurveylink', 'customer', 'links');
	}
	else
	{
	    $result = $this->SFA_Comman->executequery('CALL sp_get_links_customer_pricesurvey()','','');
	    $this->view->price_survey_info	= $result[0];
	    $this->view->region_info 		= $result[1];
	    $this->view->depot_info 		= $result[2];
	    $this->view->area_info 		= $result[3];
	    $this->view->route_info 		= $result[4];
	    $this->view->customercat_info 	= $result[5];
	}
    }
    
    /**
    * @name       getcustomerfrompricesurveyrouteAction
    * @since      30-04-2015
    * @version    Release: 1
    * @author     CS <chetan@e2logy.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This action is use for the get customer list from selected routecode.
    */
    public function getcustomerfrompricesurveyrouteAction()
    {
	//view variable declaration
	$params = $this->getRequest()->getParams();
	
	$param_array 	= array();	
	$param_array[1]	= $params['fillby'];
	$param_array[2]	= $params['val'];
	$param_array[3]	= $params['catid'];
	$param_array[4]	= $params['pricesurveykey'];
	
	
	$result = $this->SFA_Comman->executequery('CALL sp_get_customer_from_pricesurveyroutecode(?,?)',$param_array,'');
	echo Zend_Json::encode($result);
	exit;
    }
}