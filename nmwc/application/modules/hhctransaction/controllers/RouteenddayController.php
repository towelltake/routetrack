<?php
/**
* @name       IndexController
* @since
* @version    Release: 1
* @author     HD <hiren.d@elantechnologies.com>
* @copyright  Elan Technologies
* @param
*
* This controller is manage user hhctransaction module.
*/
class Hhctransaction_RouteenddayController extends Hhctransaction_Library_Controller_Action_Abstract
{
    public $sec_lang 		= '';
    /**
    * @name       init
    * @since      15-12-2011
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
			
        $this->css 				= $this->translate->_('CSS');
		$this->view->css 		= $this->css;
		$this->view->overview	= $this->translate->_('Overview');
		$this->view->details	= $this->translate->_('Details');
		$this->view->required	= $this->translate->_('Required');
		$this->view->colan	= $this->translate->_('Colan');
        
	      //$this->Hhctransaction_Model 	= new SFA_Model_Hhctransaction();
		  $this->common_model = new SFA_Model_Index();
		$this->SFA_Comman 	= new SFA_Comman();
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
        
        if(in_array($getparams_init['action'],array("deleteroute")))
        {
            if(!$this->checkaccess("delete"))
                $this->_forward('noaccess','aclaccess','home', array("actiontype"=>"delete","modulename"=>$this->currentmodulename));
        }
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
    * @since
    * @version    Release: 1
    * @author     HD <hiren.d@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This Action is for register user in website
    */
    public function indexAction()
    {
	
    }
     /**
    * @name       routeenddayAction
    * @since
    * @version    Release: 5
    * @author     GP <gayatri@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This Action is use for display that Route started but not stop and use can stop that
    * route form here.
    */
    public function routeenddayAction()
    {
		$this->view->params = $params = $this->getRequest()->getParams();
        $this->view->formdata = $formdata = $this->_request->getPost();
		$last_url = htmlspecialchars($_SERVER['HTTP_REFERER']);
		
		// IF EXTRA PARAMS ARE REQUIRED
		$ex_param = "";
		if(isset($params["key"]) && $params["key"]>0)
			$ex_param = "/key/".$params["key"];
			
		
		//variable declaration for grid title
		$code 			= $this->translate->_('Code');	
		
		$cols_array 	= array('routename','salesmanname1','DATE_FORMAT(routestartdate,"%d-%m-%Y") as routestartdate',
						'routestarttime','DATE_FORMAT(routeenddate,"%d-%m-%Y") AS routeenddate','routekey AS edit_del_primary_id');
		$columns_show 	=  array($this->translate->_('Route Name'),$this->translate->_('Salesman'),$this->translate->_('Route Start Date'),$this->translate->_('Route Start Time'),$this->translate->_('Route End Date'));
		
		$Common_NameSpace = new Zend_Session_Namespace('RoutEndDay');
		
		if($formdata['btnreset'] == 'RESET') {
			$formdata["txtdate"] 			= '';
			$Common_NameSpace->tdate		= '';
			$formdata["chkoptions"] ="";
			$Common_NameSpace->chkoptions	= '';
		}
		if(strpos($last_url,'routeendday') && $formdata["txtdate"] == '') {
			$sel_date = ($formdata["txtdate"] != '') ? $formdata["txtdate"] : $Common_NameSpace->tdate;
			$chkoptions = ($formdata["chkoptions"] != '') ? $formdata["chkoptions"] : $Common_NameSpace->chkoptions;
		} else {
			$sel_date = ($formdata["txtdate"] != '') ? $formdata["txtdate"] : date('d-m-Y');
			$chkoptions = ($formdata["chkoptions"] != '') ? $formdata["chkoptions"] : '';
		}		
		
		if($sel_date != '') {
			$Common_NameSpace->tdate 	= $sel_date;
			$this->view->date			= $sel_date;
		} else {
			$Common_NameSpace->tdate 	= date('d-m-Y');
			$this->view->date			= date('d-m-Y');
		}
		if($chkoptions != '') {
			$Common_NameSpace->chkoptions 	= $chkoptions;
			$this->view->chkoptions			= $chkoptions;
		} else {
			$Common_NameSpace->chkoptions 	= '';
			$this->view->chkoptions			= '';
		}		
		// ADDITIONAL WHERE CONDITION
		if($Common_NameSpace->chkoptions != '') {
			$additional_where_condition[] 	= " 1=1 ";
		} elseif($Common_NameSpace->tdate) {
			$additional_where_condition[] 	= " DATE(routestartdate) = STR_TO_DATE(\'".$Common_NameSpace->tdate."\',\'%d-%m-%Y\') AND routeenddate IS NULL";
		}
		// prepare the configuration for grid
		$pagingparams = array(
				"show_grid_heading" => true,
				"grid_heading_message" => $this->translate->_('Overview'),
				"show_records_per_page" => isset($formdata["show_records_per_page"])?$formdata["show_records_per_page"]:10,
				"show_searchbox" => true,
				"searchbox_value" => isset($formdata["grid_search_text"])?$formdata["grid_search_text"]:"",
				"show_selectbox" => true,
				"show_editlink" => false,
				"selected_list" => $checked,
				"show_deletelink" => false,			
				"show_deleteall" => false,
				"primaryid" => "routekey ",
				"editlink" => array("/hhctransaction/routeendday/editroute/id/#pattern#/edit/yes/","#pattern#"),
				'show_extralink' => true,
				//'extralink' => array(array("End Route","/".$params['module']."/".$params['controller']."/editroute/edit/yes/id/#pattern#","#pattern#")),
				"nodata_message" => $this->translate->_('No Record(s) Found'),
				"fetch_columns_inquery" => $cols_array,
				"show_columns" => $columns_show,
				"additional_where" => $additional_where_condition,
				);
		
		// create grid class object
		$pagingshow = new SFA_Paging($pagingparams);
		
		// call common function of grid class
		$get_return_vals = $pagingshow->commnfunc();
		
		//print_r($get_return_vals['where_condition']);
		
		// call the stored procedure for fetch the data  
		$param_array[1] = '1';
		$param_array[2] = '';
		$param_array[3] = $get_return_vals['order_columns_name'];
		$param_array[4] = $get_return_vals['order_type'];
		$param_array[5] = $get_return_vals['offset'];
		$param_array[6] = (int)$get_return_vals['show_records_per_page'];
		$param_array[7] = implode(", ",$pagingparams["fetch_columns_inquery"]);
		$param_array[8] = strlen($get_return_vals['where_condition'])>0?$get_return_vals['where_condition']:'';
		$param_array[9] = '';
		// called stored procedure for counter
		$result = $this->SFA_Comman->executequery('CALL sp_get_inventory_routestartday(?,?,?,?,?,?,?,?,?)',$param_array,'');
		$this->view->total_record 	= $result[0][0]['counter'];
		$data_arr["count"]			= $result[0][0]['counter'];	
		$data_arr["data"][0] 		= $result[1];
		
		// pass the data in summary_showdatagrid() function & create a final variable for view
		$this->view->pagegridshow =  $pagingshow->summary_showdatagrid($data_arr);
		
		$this->view->addScriptPath(APPLICATION_PATH."/theme/default/datagrid/");
    }
   /**
    * @name       editrouteAction
    * @since
    * @version    Release: 5
    * @author     GP <gayatri@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This Action Close The route.
    */
    public function editrouteAction()
    {
	$this->view->params = $params = $this->getRequest()->getParams();
        $this->formdata = $formdata = $this->_request->getPost();
	$param_array    = array();
	$param_array[1] = (str_replace('_',',',substr($params['ids'],0,-1)));
	$param_array[2] = '';	    
       
	$result 	= $this->SFA_Comman->executequery('CALL sp_edit_inventory_routeendday_editroute(?,?)',$param_array,'');
	$this->_helper->redirector('routeendday', 'routeendday', 'hhctransaction');
	exit;
    }
   
}