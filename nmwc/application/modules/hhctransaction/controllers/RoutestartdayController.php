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
class Hhctransaction_RoutestartdayController extends Hhctransaction_Library_Controller_Action_Abstract
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
		$this->view->general	= $this->translate->_('General');
		$this->view->setting1	= $this->translate->_('Settings 1');
		$this->view->details	= $this->translate->_('Details');
		$this->view->required	= $this->translate->_('Required');
		$this->view->colan	= $this->translate->_('Colan');
        
	      //$this->Inventory_Model 	= new SFA_Model_Inventory();
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
    * @name       routestartdayAction
    * @since
    * @version    Release: 5
    * @author     GP <gayatri@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This Action is for display route start day
    */
    public function routestartdayAction()
    {
		$this->view->params = $params = $this->getRequest()->getParams();
        $this->view->formdata = $formdata = $this->_request->getPost();
		$last_url = htmlspecialchars($_SERVER['HTTP_REFERER']);
		
		// IF EXTRA PARAMS ARE REQUIRED
		$ex_param = "";
		if(isset($params["key"]) && $params["key"]>0)
			$ex_param = "/key/".$params["key"];
	
		// column header to be displayed
		$routecode			= $this->translate->_('Route Code');
		$salesmancode		= $this->translate->_('Salesman Code');
		$routename			= $this->translate->_('Route Name');
		$salesman			= $this->translate->_('Salesman');
		$route_start_date	= $this->translate->_('Route Start Date');
		$route_start_time	= $this->translate->_('Route Start Time');
			
		
			
		$columns_array	= array('startendday.routecode','routename','startendday.salesmancode','salesmanname1','DATE_FORMAT(routestartdate,"%d-%m-%Y") as routestartdate',
						'routestarttime','routekey as edit_del_primary_id');
		$columns_show  = array($routecode,$routename,$salesmancode,$salesman,$route_start_date,$route_start_time);
		
		$Common_NameSpace = new Zend_Session_Namespace('RoutStartDay');
		if($formdata['btnreset'] == 'RESET')
		{
			$formdata["txtdate"] 	= '';
			$Common_NameSpace->tdate	= '';
			$formdata["chkoptions"] ="";
			$Common_NameSpace->chkoptions	= '';
		}		
		if(strpos($last_url,'routestartday') && $formdata["txtdate"] == '') {
			$sel_date = ($formdata["txtdate"] != '') ? $formdata["txtdate"] : $Common_NameSpace->tdate;
			$chkoptions = ($formdata["chkoptions"] != '') ? $formdata["chkoptions"] : $Common_NameSpace->chkoptions;
		} else {
			$sel_date = ($formdata["txtdate"] != '') ? $formdata["txtdate"] : date('d-m-Y');
			$chkoptions = ($formdata["chkoptions"] != '') ? $formdata["chkoptions"] : '';
		}
		
		// ADD DATE VALUE IN SESSION
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
		if( $Common_NameSpace->chkoptions != '') {
			$additional_where_condition[] = " ( routeenddate IS NULL )";
		} elseif($Common_NameSpace->tdate) {
			$additional_where_condition[] = " routestartdate = STR_TO_DATE(\'".$Common_NameSpace->tdate."\',\'%d-%m-%Y\') AND routeenddate IS NULL AND triptype = 1";
		}
		
		// prepare the configuration for grid
		$pagingparams = array(
				"show_grid_heading" => false,
				"grid_heading_message" => $this->translate->_('Overview'),
				"show_records_per_page" => isset($formdata["show_records_per_page"])?$formdata["show_records_per_page"]:10,
				"show_searchbox" => true,
				"show_selectbox" => false,
				"show_editlink" => false,
				"show_deletelink" => true,			
				"show_deleteall" => false,
				"primaryid" => "routekey",
				"no_search_fields" => array("routestartdate","routestarttime"),
				"currentlink" => array("/hhctransaction/routestartday/routestartday".$ex_param),
				"editlink" => array("/id/#pattern#/edit/yes","#pattern#"),
				"deletelink" => array("/hhctransaction/routestartday/deleteroute/id/#pattern#/delete/yes","#pattern#"),
				"nodata_message" => $this->translate->_('No Record(s) Found'),
				"fetch_columns_inquery" => $columns_array,			
				"show_columns" => $columns_show,
				"additional_where" => $additional_where_condition,
				"show_top_columns"=>false
				);	
			
		if(!$this->checkaccess("delete"))
		{
			$pagingparams["show_deletelink"] = false;
		}
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
	
		//echo "<pre>";
		//print_r($param_array);
		
		// called stored procedure for counter
		$result = $this->SFA_Comman->executequery('CALL sp_get_inventory_routestartday(?,?,?,?,?,?,?,?,?)',$param_array,'');
	
		$data_arr["count"] 	= $result[0][0]['counter'];
		$data_arr["data"][0]	= $result[1];
		
		
			// pass the data in summary_showdatagrid() function & create a final variable for view
		$this->view->pagegridshow =  $pagingshow->summary_showdatagrid($data_arr);	
		$this->view->addScriptPath(APPLICATION_PATH."/theme/default/datagrid/");

      //  $this->render("ajaxgrid");
    }
   /**
    * @name       addrouteAction
    * @since
    * @version    Release: 5
    * @author     GP <gayatri@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This Action is use for start day entry
    */
    public function addrouteAction()
    {
	$this->view->params = $params = $this->getRequest()->getParams();
        $this->formdata = $formdata = $this->_request->getPost();
	
	if(isset( $this->formdata['hdnid']) &&  $this->formdata['hdnid'] != "")
	{
	    $param_array[1] = $this->formdata['ddlroute'];
	    $param_array[2] = $this->formdata['txtdate'];
	    
	    
	    $result 	= $this->SFA_Comman->executequery('CALL sp_add_inventory_routestartday_addroute(?,?)',$param_array,'');
	    $this->_helper->redirector('routestartday', 'routestartday', 'hhctransaction');
	}
	// called stored procedure for counter
	$result_arr = $this->SFA_Comman->executequery('CALL sp_combo_route_startday_exclude()','','');
	$this->view->route	= $result_arr[0];
    }
     /**
    * @name       deleterouteAction
    * @since
    * @version    Release: 5
    * @author     GP <gayatri@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This Action is use delete route start record entry
    */
    public function deleterouteAction()
    {
		$params = $this->getRequest()->getParams();
        $this->formdata = $formdata = $this->_request->getPost();
	
		$param_array[1] = $params['id'];
		$result 	= $this->SFA_Comman->executequery('CALL sp_delete_inventory_routestartday_deleteroute(?)',$param_array,'');
		 
		 if($result[0][0]['allow_delete_op'] == "YES")
			SFA_Message::setMsg($this->translate->_('Delete Record'));
		 else
			SFA_Message::setErrorMsg($this->translate->_('Record Already In use'));
		 
		 $this->_helper->redirector('routestartday', 'routestartday', 'hhctransaction');
		 
		exit;
    }
	/**
    * @name       getrouteenddateAction
    * @since
    * @version    Release: 5
    * @author     HD <hiren.d@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This Action is use get latest route end date and time
    */
    public function getrouteenddateAction()
	{
		$params = $this->getRequest()->getParams();
		$result 	= $this->SFA_Comman->executequery('CALL sp_get_route_enddate_time(?)',$params['routeid'],'');
		if($result[0][0]['routeenddate'] != '')
			echo $result[0][0]['routeenddate'];
		else
			echo '----';
		exit;
	}
   
}