<?php
/**
* @name       LoadController
* @since
* @version    Release: 1
* @author     M@M <miral@elantechnologies.com>
* @copyright  Elan Technologies
* @param
*
* This controller is manage hhctransaction module.
*/


class Hhctransaction_LoadstockController extends Hhctransaction_Library_Controller_Action_Abstract
{
      /**
    * @name       init
    * @since      01-02-2012
    * @version    Release: 1
    * @author     M@M <miral@elantechnologies.com>
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
        $this->css 				= $this->translate->_('CSS');
		$this->view->css 		= $this->css;
		$this->view->overview	= $this->translate->_('Overview');
		$this->view->details	= $this->translate->_('Details');
		$this->view->required	= $this->translate->_('Required');
		$this->view->colan	= $this->translate->_('Colan');
	
		$this->common_model			= new SFA_Model_Index();
		$this->SFA_Comman			= new SFA_Comman();
		$this->view->sec_lang		= $this->SFA_Comman->getsecondlanguage();
		$this->decimalplaces 		= $this->SFA_Comman->getdecimalplaces();
		$this->view->decimalplaces 	= $this->decimalplaces ;
		$this->sec_lang 			= $this->view->sec_lang;	    
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
            if(($getpost_init["hdDelete"]==1 || $getparams_init['delid'] > 0) && !$this->checkaccess("delete")) {
            
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
    * @name       servayAction
    * @since      01-02-2012
    * @version    Release: 1
    * @author     M@M <miral@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This action is for display begin stock
    *
    */
    public function beginstockAction()
    {
		$this->view->params = $params = $this->getRequest()->getParams();
        $this->view->formdata = $formdata = $this->_request->getPost();
		
		$cols_array 	= array('ith.routecode','sm.salesmanname1','rm.routename','ith.documentnumber','ith.transactiontime','ith.detailkey AS edit_del_primary_id');
		$columns_show 	= array($this->translate->_('Route Code'),$this->translate->_('Salesman'),$this->translate->_('Route Name'),$this->translate->_('Document Number'),$this->translate->_('Transaction Time'));
		
		
		$Common_NameSpace = new Zend_Session_Namespace('BeginStock');
		
		$last_url 		= htmlspecialchars($_SERVER['HTTP_REFERER']);
		$end_last_url 	= explode('/',$last_url);
		
		if(end($end_last_url) == 'beginstock' || strpos($last_url,'viewbeginstock') || strpos($last_url,'/beginstock/' )) {
			
			$sel_date 	= ($formdata["txtdate"] != '') ? $formdata["txtdate"] : $Common_NameSpace->tdate;
			$sel_route 	= ($formdata["ddlroute"] != '') ? $formdata["ddlroute"] : $Common_NameSpace->route;
		}
		else {
			$sel_date 	= ($formdata["txtdate"] != '') ? $formdata["txtdate"] : date('d-m-Y');
			$sel_route 	= ($formdata["ddlroute"] != '') ? $formdata["ddlroute"] : '0';
		}
		
		// ADD DATE VALUE IN SESSION
		if($sel_date != '') {
			$Common_NameSpace->tdate 	= $sel_date;
			$this->view->date			= $sel_date;
			$Common_NameSpace->route 	= $sel_route;
			$this->view->route			= $sel_route;
		}
		else
		{
			$Common_NameSpace->tdate 	= date('d-m-Y');
			$this->view->date			= date('d-m-Y');
			$Common_NameSpace->route 	= '0';
			$this->view->route			= '0';
		}
		
		$additional_where_condition = array();
		$additional_where_condition[] = "  ith.transactiontype = 1 ";
		$additional_where_condition[] = " (ith.transactiondate BETWEEN \'".date("Y-m-d 00:00:00",strtotime(str_replace('/', '-', $Common_NameSpace->tdate)))."\' AND \'".date("Y-m-d 23:59:59",strtotime(str_replace('/', '-', $Common_NameSpace->tdate)))."\' )";
		
		if($Common_NameSpace->route > 0) {
			$additional_where_condition[] = "  ith.routecode =  ".$Common_NameSpace->route;
		}
		
		// prepare the configuration for grid
		$pagingparams = array(
				"show_grid_heading" => true,
				"grid_heading_message" => $this->translate->_('Overview'),
				"show_records_per_page" => isset($formdata["show_records_per_page"])?$formdata["show_records_per_page"]:10,
				"show_searchbox" => true,
				"searchbox_value" => isset($formdata["grid_search_text"])?$formdata["grid_search_text"]:"",
				"show_selectbox" => false,
				"show_editlink" => false,				
				"show_deletelink" => false,			
				"show_deleteall" => false,
				"primaryid" => "detailkey",
				"show_extralink" => true,
				"extralink" => array(array("View","/".$params['module']."/".$params['controller']."/viewbeginstock/id/#pattern#","#pattern#")),
				"nodata_message" => $this->translate->_('No Record(s) Found'),
				"fetch_columns_inquery" => $cols_array,
				"show_columns" => $columns_show,
				"additional_where" => $additional_where_condition
				);
		
		// create grid class object
		$pagingshow = new SFA_Paging($pagingparams);
		
		// call common function of grid class
		$get_return_vals = $pagingshow->commnfunc();
		
		// call the stored procedure for fetch the data
		$param_array = array();
		$param_array[1] = '1';
		$param_array[2] = '';
		$param_array[3] = $get_return_vals['order_columns_name'];
		$param_array[4] = $get_return_vals['order_type'];
		$param_array[5] = $get_return_vals['offset'];
		$param_array[6] = (int)$get_return_vals['show_records_per_page'];
		$param_array[7] = implode(", ",$pagingparams["fetch_columns_inquery"]);
		$param_array[8] = strlen($get_return_vals['where_condition'])>0?$get_return_vals['where_condition']:'';
		$param_array[9] = " AND itd.transactiontypecode = 12";
		
		// called stored procedure for counter
		$result = $this->SFA_Comman->executequery('CALL sp_get_hhctransaction_stock_beginstock(?,?,?,?,?,?,?,?)',$param_array,'');
	
		$this->view->route_info 	= $result[0];
		$data_arr["count"] 			= $result[1][0]['counter'];
		$data_arr["data"][0] 		= $result[2];
		
		
		// pass the data in summary_showdatagrid() function & create a final variable for view
		$this->view->pagegridshow =  $pagingshow->summary_showdatagrid($data_arr);
		
		$this->view->addScriptPath(APPLICATION_PATH."/theme/default/datagrid/");
    }
     /**
    * @name       viewbeginstockAction
    * @since      02-02-2012
    * @version    Release: 1
    * @author     HD <hiren.d@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This action is for add begin stock
    *
    */
    public function viewbeginstockAction()
    {
		$this->view->params = $params = $this->getRequest()->getParams();
        $this->view->formdata = $formdata = $this->_request->getPost();

        // Begin Stock Datagrid
		$ex_param = "";
		if(isset($params["id"]) && $params["id"]>0)
			$ex_param = "/key/".$params["id"];
	
		$this->view->itemgrid    = $this->view->BaseUrl("/".$params['module']."/".$params['controller']."/viewbeginstockgrid".$ex_param);
		
		$res = $this->SFA_Comman->executequery('CALL sp_get_hhctransaction_stock_viewbeginstock(?)',$params['id'],'');
		
		$this->view->formdata 	= $res[0][0];
		$this->view->route_info = $res[1];
    }
    /**
    * @name       viewbeginstockgridAction
    * @since      22-02-2012
    * @version    Release: 1
    * @author     AS <alpesh@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This action is use for view item grid in viewbeginstockAction
    */
    public function viewbeginstockgridAction()
    {
		$this->view->params = $params = $this->getRequest()->getParams();
		
        // IF EXTRA PARAMS ARE REQUIRED
        $ex_param = "";
        if(isset($params["key"]) && $params["key"]>0)
                $ex_param = "/key/".$params["key"];
		
		$columns_show  	= array(
									$this->translate->_('Item Code'),$this->translate->_('Item Name'),$this->translate->_('UPC'),
									$this->translate->_('Case Price'),$this->translate->_('Pcs Price'),$this->translate->_('Case'),$this->translate->_('Pcs'),
									$this->translate->_('Total Amount')
								);
		$cpanel				= $this->SFA_Comman->getaltcodestatus();
		$altcode_status		= $cpanel["Use Alternate Code"]['status'];
		
		if($altcode_status)
		{
			$columns_array 	= array('im.alternatecode','im.itemdescription','im.unitspercase','FORMAT(itd.itemcaseprice,'.$this->decimalplaces.') as itemcaseprice','FORMAT(itd.itemprice,'.$this->decimalplaces.') as itemprice','FLOOR(itd.quantity/im.unitspercase) AS cases','(itd.quantity%im.unitspercase) AS pieces','FORMAT(((itd.itemcaseprice)*FLOOR(itd.quantity/im.unitspercase))+(itd.itemprice*(itd.quantity%im.unitspercase)),'.$this->decimalplaces.') AS total_amt','1 AS sett_tran_type','CONCAT(transactiontypecode,"_",routekey,"_",itemcode,"_",itd.batchdetailkey) AS edit_del_primary_id');
		}
		else
		{
			$columns_array 	= array('itd.itemcode','im.itemdescription','im.unitspercase','FORMAT(itd.itemcaseprice,'.$this->decimalplaces.') as itemcaseprice','FORMAT(itd.itemprice,'.$this->decimalplaces.') as itemprice','FLOOR(itd.quantity/im.unitspercase) AS cases','(itd.quantity%im.unitspercase) AS pieces','FORMAT(((itd.itemcaseprice)*FLOOR(itd.quantity/im.unitspercase))+(itd.itemprice*(itd.quantity%im.unitspercase)),'.$this->decimalplaces.') AS total_amt','1 AS sett_tran_type','CONCAT(transactiontypecode,"_",routekey,"_",itemcode,"_",itd.batchdetailkey) AS edit_del_primary_id');
		}
		
		// IF EXTRA PARAMS ARE REQUIRED
		$ex_param = "";
		$additional_where_condition = array();
		if(isset($params["key"]) && $params["key"]>0){
			$ex_param = "/key/".$params["key"];
			$additional_where_condition[] = ' ( detailkey = "'.$params["key"].'" AND itd.transactiontypecode = 12 )';
		}
		
		$amt_right = array('exchangerate');
	
		// ARRAY FOR GRID PAGINATION
		$pagingparams = array(
					 "show_grid_heading" => false,
					 "grid_heading_message" => $this->translate->_('Overview'),
					 "show_records_per_page" => isset($formdata["show_records_per_page"])?$formdata["show_records_per_page"]:10,
					 "show_searchbox" => false,
					 "show_selectbox" => false,
					 "show_editlink" => false,
					 "show_deletelink" => false,
					 "show_deleteall" => false,
					 "primaryid" => "transactiontypecode",
					 "currentlink" => array("/".$params['module']."/".$params['controller']."/".$params['action'].$ex_param),
					 "deletelink" => array("/id/#pattern#/delete/yes/msg/curr","#pattern#"),
					 "editlink" => array("/id/#pattern#/edit/yes","#pattern#"),
					 "nodata_message" => $this->translate->_('No Record(s) Found'),
					 "fetch_columns_inquery" => $columns_array,
					 "show_columns" => $columns_show,
					 "additional_where" => $additional_where_condition,
					 "show_columns_right_side" => array('itemprice','itemcaseprice','total_amt','cases','pieces'),
					 "show_header_right_side"=>array($this->translate->_('Case Price'),$this->translate->_('Pcs Price'),$this->translate->_('Case'),$this->translate->_('Pcs'),$this->translate->_('Total Amount')),
					 "show_total_columns"=>true,
					 "show_total_columns_value"=>array("cases"=>"0","pieces"=>"0","total_amt"=>"1"),
					 "show_total_columns_msg"=>array("itemdescription","Total",$this->decimalplaces),
					 "show_extralink_popup" => true,
					 "show_extralink_popup_transaction" => true,
					 "extralink" => array(array("More","/".$params['module']."/".$params['controller']."/viewbatchdetail/id/#pattern#/?q=prettyPhoto&iframe=true&width=900&height=500","#pattern#")),
					 );

		
		$pagingshow = new SFA_Ajaxpaging($pagingparams);
		
		// call common function of grid class
		$get_return_vals = $pagingshow->commnfunc();
		
		// call the stored procedure for fetch the data
		$param_array 	= array();
		$param_array[1] = '1';
		$param_array[2] = $get_return_vals['order_columns_name'];
		$param_array[3] = $get_return_vals['order_type'];
		$param_array[4] = $get_return_vals['offset'];
		$param_array[5] = (int)$get_return_vals['show_records_per_page'];
		$param_array[6] = implode(", ",$pagingparams["fetch_columns_inquery"]);
		$param_array[7] = strlen($get_return_vals['where_condition'])>0?$get_return_vals['where_condition']:'';	
		
		
		// called stored procedure for counter
		$result = $this->SFA_Comman->executequery('CALL sp_get_hhctransaction_stock_viewbeginstockgrid(?,?,?,?,?,?,?)',$param_array,'');    
		$data_arr["count"] 	= $result[0][0]['counter'];	
		$data_arr["data"][0] 	= $result[1];
		
		$this->view->pagegridshow =  $pagingshow->summary_showdatagrid($data_arr);
		$this->view->addScriptPath(APPLICATION_PATH."/theme/default/datagrid/");
	
		$this->render("ajaxgrid");       
    }


     /**
    * @name       loadAction
    * @since      01-02-2012
    * @version    Release: 1
    * @author     M@M <miral@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This action is for display load
    *
    */
    public function loadAction()
    {
		$this->view->params = $params = $this->getRequest()->getParams();
        $this->view->formdata = $formdata = $this->_request->getPost();
		
		$cols_array 	= array('ith.routecode','sm.salesmanname1','rm.routename','ith.documentnumber','ith.transactiontime','ith.detailkey AS edit_del_primary_id');
		$columns_show 	= array($this->translate->_('Route Code'),$this->translate->_('Salesman'),$this->translate->_('Route Name'),$this->translate->_('Document Number'),$this->translate->_('Transaction Time'));
		
		$Common_NameSpace = new Zend_Session_Namespace('Load');
		
		$last_url 		= htmlspecialchars($_SERVER['HTTP_REFERER']);
		$end_last_url 	= explode('/',$last_url);
		
		if(end($end_last_url) == 'load' || strpos($last_url,'viewload') || strpos($last_url,'/load/' )) {
			
			$sel_date 	= ($formdata["txtdate"] != '') ? $formdata["txtdate"] : $Common_NameSpace->tdate;
			$sel_route 	= ($formdata["ddlroute"] != '') ? $formdata["ddlroute"] : $Common_NameSpace->route;
		}
		else {
			$sel_date 	= ($formdata["txtdate"] != '') ? $formdata["txtdate"] : date('d-m-Y');
			$sel_route 	= ($formdata["ddlroute"] != '') ? $formdata["ddlroute"] : '0';
		}
		
		// ADD DATE VALUE IN SESSION
		if($sel_date != '') {
			$Common_NameSpace->tdate 	= $sel_date;
			$this->view->date			= $sel_date;
			$Common_NameSpace->route 	= $sel_route;
			$this->view->route			= $sel_route;
		}
		else
		{
			$Common_NameSpace->tdate 	= date('d-m-Y');
			$this->view->date			= date('d-m-Y');
			$Common_NameSpace->route 	= '0';
			$this->view->route			= '0';
		}
		
		$additional_where_condition = array();
		$additional_where_condition[] = "  ith.transactiontype = 1 ";
		$additional_where_condition[] = " (ith.transactiondate BETWEEN \'".date("Y-m-d 00:00:00",strtotime(str_replace('/', '-', $Common_NameSpace->tdate)))."\' AND \'".date("Y-m-d 23:59:59",strtotime(str_replace('/', '-', $Common_NameSpace->tdate)))."\' )";
		$additional_where_condition[] = " ith.record_flag = \'1\' ";
		
		if($Common_NameSpace->route > 0) {
			$additional_where_condition[] = "  ith.routecode =  ".$Common_NameSpace->route;
		}
		
		
		
		// prepare the configuration for grid
		$pagingparams = array(
				"show_grid_heading" => true,
				"grid_heading_message" => $this->translate->_('Overview'),
				"show_records_per_page" => isset($formdata["show_records_per_page"])?$formdata["show_records_per_page"]:10,
				"show_searchbox" => true,
				"searchbox_value" => isset($formdata["grid_search_text"])?$formdata["grid_search_text"]:"",
				"show_selectbox" => false,
				"show_editlink" => false,				
				"show_deletelink" => false,			
				"show_deleteall" => false,
				"primaryid" => "detailkey",
				"show_extralink" => true,
				"extralink" => array(array("View","/".$params['module']."/".$params['controller']."/viewload/id/#pattern#","#pattern#")),
				"nodata_message" => $this->translate->_('No Record(s) Found'),
				"fetch_columns_inquery" => $cols_array,
				"show_columns" => $columns_show,
				"additional_where" => $additional_where_condition
				);
		
		// create grid class object
		$pagingshow = new SFA_Paging($pagingparams);
		
		// call common function of grid class
		$get_return_vals = $pagingshow->commnfunc();
		
		// call the stored procedure for fetch the data
		$param_array = array();
		$param_array[1] = '1';
		$param_array[2] = '';
		$param_array[3] = $get_return_vals['order_columns_name'];
		$param_array[4] = $get_return_vals['order_type'];
		$param_array[5] = $get_return_vals['offset'];
		$param_array[6] = (int)$get_return_vals['show_records_per_page'];
		$param_array[7] = implode(", ",$pagingparams["fetch_columns_inquery"]);
		$param_array[8] = strlen($get_return_vals['where_condition'])>0?$get_return_vals['where_condition']:'';
		$param_array[9] = " AND itd.transactiontypecode = 1";
		
		// called stored procedure for counter
		$result = $this->SFA_Comman->executequery('CALL sp_get_hhctransaction_stock_beginstock(?,?,?,?,?,?,?,?)',$param_array,'');
	
		$this->view->route_info 	= $result[0];
		$data_arr["count"] 			= $result[1][0]['counter'];
		$data_arr["data"][0] 		= $result[2];
		
		// pass the data in summary_showdatagrid() function & create a final variable for view
		$this->view->pagegridshow =  $pagingshow->summary_showdatagrid($data_arr);
		
		$this->view->addScriptPath(APPLICATION_PATH."/theme/default/datagrid/");
    }
     /**
    * @name       viewload
    * @since      02-02-2012
    * @version    Release: 1
    * @author     HD <hiren.d@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This action is for view load
    *
    */
    public function viewloadAction()
    {
		$this->view->params = $params = $this->getRequest()->getParams();
        $this->view->formdata = $formdata = $this->_request->getPost();

        // Begin Stock Datagrid
		$ex_param = "";
		if(isset($params["id"]) && $params["id"]>0)
			$ex_param = "/key/".$params["id"];
	
		$this->view->itemgrid    = $this->view->BaseUrl("/".$params['module']."/".$params['controller']."/viewloadgrid".$ex_param);
		
		$res = $this->SFA_Comman->executequery('CALL sp_get_hhctransaction_stock_viewbeginstock(?)',$params['id'],'');
		
		$this->view->formdata 	= $res[0][0];
		$this->view->route_info = $res[1];
    }

    /**
    * @name       viewloadgridAction
    * @since      22-02-2012
    * @version    Release: 1
    * @author     AS <alpesh@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This action is use for view item grid in viewloadAction
    */
    public function viewloadgridAction()
    {
		$this->view->params = $params = $this->getRequest()->getParams();
		
        // IF EXTRA PARAMS ARE REQUIRED
        $ex_param = "";
        if(isset($params["key"]) && $params["key"]>0)
                $ex_param = "/key/".$params["key"];
		
		$columns_show  	= array(
									$this->translate->_('Item Code'),$this->translate->_('Item Name'),$this->translate->_('UPC'),
									$this->translate->_('Case Price'),$this->translate->_('Pcs Price'),$this->translate->_('Case'),$this->translate->_('Pcs'),
									$this->translate->_('Total Amount')
								);
		$cpanel				= $this->SFA_Comman->getaltcodestatus();
		$altcode_status		= $cpanel["Use Alternate Code"]['status'];
		
		if($altcode_status) {
			$columns_array 	= array('im.alternatecode','im.itemdescription','im.unitspercase','FORMAT(itd.itemcaseprice,'.$this->decimalplaces.') as itemcaseprice','FORMAT(itd.itemprice,'.$this->decimalplaces.') as itemprice','FLOOR(itd.quantity/im.unitspercase) AS cases','(itd.quantity%im.unitspercase) AS pieces','FORMAT(((itd.itemcaseprice)*FLOOR(itd.quantity/im.unitspercase))+(itd.itemprice*(itd.quantity%im.unitspercase)),'.$this->decimalplaces.') AS total_amt','1 AS sett_tran_type','CONCAT(transactiontypecode,"_",routekey,"_",itemcode,"_",itd.batchdetailkey) AS edit_del_primary_id');
		}
		else {
			$columns_array 	= array('itd.itemcode','im.itemdescription','im.unitspercase','FORMAT(itd.itemcaseprice,'.$this->decimalplaces.') as itemcaseprice','FORMAT(itd.itemprice,'.$this->decimalplaces.') as itemprice','FLOOR(itd.quantity/im.unitspercase) AS cases','(itd.quantity%im.unitspercase) AS pieces','FORMAT(((itd.itemcaseprice)*FLOOR(itd.quantity/im.unitspercase))+(itd.itemprice*(itd.quantity%im.unitspercase)),'.$this->decimalplaces.') AS total_amt','1 AS sett_tran_type','CONCAT(transactiontypecode,"_",routekey,"_",itemcode,"_",itd.batchdetailkey) AS edit_del_primary_id');
		}
		
		// IF EXTRA PARAMS ARE REQUIRED
		$ex_param = "";
		$additional_where_condition = array();
		if(isset($params["key"]) && $params["key"]>0){
			$ex_param = "/key/".$params["key"];
			$additional_where_condition[] = ' ( detailkey = "'.$params["key"].'" AND itd.transactiontypecode = 1 )';
		}
		
		// ARRAY FOR GRID PAGINATION
		$pagingparams = array(
					 "show_grid_heading" => false,
					 "grid_heading_message" => $this->translate->_('Overview'),
					 "show_records_per_page" => isset($formdata["show_records_per_page"])?$formdata["show_records_per_page"]:10,
					 "show_searchbox" => false,
					 "show_selectbox" => false,
					 "show_editlink" => false,
					 "show_deletelink" => false,
					 "show_deleteall" => false,
					 "show_datasorting" => '1',
					 "primaryid" => "transactiontypecode",
					 "currentlink" => array("/".$params['module']."/".$params['controller']."/".$params['action'].$ex_param),					 
					 "nodata_message" => $this->translate->_('No Record(s) Found'),
					 "fetch_columns_inquery" => $columns_array,
					 "show_columns" => $columns_show,					 
					 "additional_where" => $additional_where_condition,					 
					 "show_columns_right_side" => array('itemprice','itemcaseprice','total_amt','cases','pieces'),
					 "show_header_right_side"=>array($this->translate->_('Case Price'),$this->translate->_('Pcs Price'),$this->translate->_('Case'),$this->translate->_('Pcs'),$this->translate->_('Total Amount')),
					 "show_total_columns"=>true,
					 "show_total_columns_value"=>array("cases"=>"0","pieces"=>"0","total_amt"=>"1"),
					 "show_total_columns_msg"=>array("itemdescription","Total",$this->decimalplaces),
					 "show_extralink_popup" => true,
					 "show_extralink_popup_transaction" => true,
					 "extralink" => array(array("More","/".$params['module']."/".$params['controller']."/viewbatchdetail/id/#pattern#/?q=prettyPhoto&iframe=true&width=900&height=500","#pattern#")),
					 );

		
		$pagingshow = new SFA_Ajaxpaging($pagingparams);
		
		// call common function of grid class
		$get_return_vals = $pagingshow->commnfunc();
		
		// call the stored procedure for fetch the data
		$param_array 	= array();
		$param_array[1] = '1';
		$param_array[2] = $get_return_vals['order_columns_name'];
		$param_array[3] = $get_return_vals['order_type'];
		$param_array[4] = $get_return_vals['offset'];
		$param_array[5] = (int)$get_return_vals['show_records_per_page'];
		$param_array[6] = implode(", ",$pagingparams["fetch_columns_inquery"]);
		$param_array[7] = strlen($get_return_vals['where_condition'])>0?$get_return_vals['where_condition']:'';	
	
		
		// called stored procedure for counter
		$result = $this->SFA_Comman->executequery('CALL sp_get_hhctransaction_stock_viewbeginstockgrid(?,?,?,?,?,?,?)',$param_array,'');    
		$data_arr["count"] 		= $result[0][0]['counter'];	
		$data_arr["data"][0] 	= $result[1];
		
		$this->view->pagegridshow =  $pagingshow->summary_showdatagrid($data_arr);
		$this->view->addScriptPath(APPLICATION_PATH."/theme/default/datagrid/");
	
		$this->render("ajaxgrid");
    }
	/**
    * @name       loadrequestAction
    * @since      01-02-2012
    * @version    Release: 1
    * @author     M@M <miral@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This action is for display loadrequest
    *
    */
    public function loadrequestAction()
    {
		$this->view->params = $params = $this->getRequest()->getParams();
        $this->view->formdata = $formdata = $this->_request->getPost();
		
		$cols_array 	= array('ith.routecode','sm.salesmanname1','rm.routename','ith.documentnumber','ith.transactiontime','ith.detailkey AS edit_del_primary_id');
		$columns_show 	= array($this->translate->_('Route Code'),$this->translate->_('Salesman'),$this->translate->_('Route Name'),$this->translate->_('Document Number'),$this->translate->_('Transaction Time'));
		
		$Common_NameSpace = new Zend_Session_Namespace('LoadRequest');
		
		$last_url 		= htmlspecialchars($_SERVER['HTTP_REFERER']);
		$end_last_url 	= explode('/',$last_url);
		
		if(end($end_last_url) == 'loadrequest' || strpos($last_url,'viewloadrequest') || strpos($last_url,'/loadrequest/' )) {
			
			$sel_date 	= ($formdata["txtdate"] != '') ? $formdata["txtdate"] : $Common_NameSpace->tdate;
			$sel_route 	= ($formdata["ddlroute"] != '') ? $formdata["ddlroute"] : $Common_NameSpace->route;
		}
		else {
			$sel_date 	= ($formdata["txtdate"] != '') ? $formdata["txtdate"] : date('d-m-Y');
			$sel_route 	= ($formdata["ddlroute"] != '') ? $formdata["ddlroute"] : '0';
		}
		
		// ADD DATE VALUE IN SESSION
		if($sel_date != '') {
			$Common_NameSpace->tdate 	= $sel_date;
			$this->view->date			= $sel_date;
			$Common_NameSpace->route 	= $sel_route;
			$this->view->route			= $sel_route;
		}
		else
		{
			$Common_NameSpace->tdate 	= date('d-m-Y');
			$this->view->date			= date('d-m-Y');
			$Common_NameSpace->route 	= '0';
			$this->view->route			= '0';
		}
		
		$additional_where_condition = array();
		$additional_where_condition[] = "  ith.transactiontype = 4 ";
		$additional_where_condition[] = " (ith.transactiondate BETWEEN \'".date("Y-m-d 00:00:00",strtotime(str_replace('/', '-', $Common_NameSpace->tdate)))."\' AND \'".date("Y-m-d 23:59:59",strtotime(str_replace('/', '-', $Common_NameSpace->tdate)))."\' )";
		
		if($Common_NameSpace->route > 0) {
			$additional_where_condition[] = "  ith.routecode =  ".$Common_NameSpace->route;
		}
		
		// prepare the configuration for grid
		$pagingparams = array(
				"show_grid_heading" => true,
				"grid_heading_message" => $this->translate->_('Overview'),
				"show_records_per_page" => isset($formdata["show_records_per_page"])?$formdata["show_records_per_page"]:10,
				"show_searchbox" => true,
				"searchbox_value" => isset($formdata["grid_search_text"])?$formdata["grid_search_text"]:"",
				"show_selectbox" => false,
				"show_editlink" => false,				
				"show_deletelink" => false,			
				"show_deleteall" => false,
				"primaryid" => "detailkey",
				"show_extralink" => true,
				"extralink" => array(array("View","/".$params['module']."/".$params['controller']."/viewloadrequest/id/#pattern#","#pattern#")),
				"nodata_message" => $this->translate->_('No Record(s) Found'),
				"fetch_columns_inquery" => $cols_array,
				"show_columns" => $columns_show,
				"additional_where" => $additional_where_condition
				);
		
		// create grid class object
		$pagingshow = new SFA_Paging($pagingparams);
		
		// call common function of grid class
		$get_return_vals = $pagingshow->commnfunc();
		
		// call the stored procedure for fetch the data
		$param_array = array();
		$param_array[1] = '1';
		$param_array[2] = '';
		$param_array[3] = $get_return_vals['order_columns_name'];
		$param_array[4] = $get_return_vals['order_type'];
		$param_array[5] = $get_return_vals['offset'];
		$param_array[6] = (int)$get_return_vals['show_records_per_page'];
		$param_array[7] = implode(", ",$pagingparams["fetch_columns_inquery"]);
		$param_array[8] = strlen($get_return_vals['where_condition'])>0?$get_return_vals['where_condition']:'';
		$param_array[9] = " AND itd.transactiontypecode = 8 ";
		
		// called stored procedure for counter
		$result = $this->SFA_Comman->executequery('CALL sp_get_hhctransaction_stock_beginstock(?,?,?,?,?,?,?,?)',$param_array,'');
		
		$this->view->route_info 	= $result[0];
		$data_arr["count"] 			= $result[1][0]['counter'];
		$data_arr["data"][0] 		= $result[2];
		
		
		// pass the data in summary_showdatagrid() function & create a final variable for view
		$this->view->pagegridshow =  $pagingshow->summary_showdatagrid($data_arr);
		
		$this->view->addScriptPath(APPLICATION_PATH."/theme/default/datagrid/");
	}
     /**
    * @name       viewloadrequest
    * @since      02-02-2012
    * @version    Release: 1
    * @author     HD <hiren.d@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This action is for view load
    *
    */
    public function viewloadrequestAction()
    {
		$this->view->params = $params = $this->getRequest()->getParams();
        $this->view->formdata = $formdata = $this->_request->getPost();

		$reqdate = date("Y-m-d",strtotime($formdata['txtreqdate']));
		
		if($formdata['hdnid']!=''){
		$param_array_header = array();		
		$param_array_header[1]  = $formdata['hdnid'];		
		$param_array_header[2]  = $formdata['chkisurgent'];
		$param_array_header[3]  = $formdata['ddldepotroute'];
		$param_array_header[4]  = $reqdate;	
	
		$return_header = $this->SFA_Comman->executequery('CALL sp_edit_loadstock_inventorytransactionheader(?,?,?,?)',$param_array_header,'');
		SFA_Message::setMsg($this->translate->_('Update Record'));
		$this->_helper->redirector("loadrequest", "loadstock", "hhctransaction");
		}
		
		
     
		$res = $this->SFA_Comman->executequery('CALL sp_get_hhctransaction_stock_viewbeginstock(?)',$params['id'],'');		
		$item = $this->SFA_Comman->executequery('CALL sp_get_items_routeitemgroupcode(?)',$res[0][0]["routecode"],'');
		
		$this->view->formdata 	= $res[0][0];
		$this->view->route_info = $res[1];
		$this->view->item 		= $item[0];
		$this->view->detailkey = $params["id"];
		$this->view->depot_route_info = $res[3];
		
		   // Begin Stock Datagrid
		  
		$ex_param = "";
		if(isset($params["id"]) && $params["id"]>0)
			$ex_param = "/key/".$params["id"]."/transind/".$res[0][0]["transmitindicator"];
	
		$this->view->itemgrid    = $this->view->BaseUrl("/".$params['module']."/".$params['controller']."/viewloadrequestgrid".$ex_param);
		
    }

    /**
    * @name       viewloadrequestgridAction
    * @since      22-02-2012
    * @version    Release: 1
    * @author     AS <alpesh@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This action is use for view item grid in viewloadrequest
    */
    public function viewloadrequestgridAction()
    {
		$this->view->params = $params = $this->getRequest()->getParams();
		
		$columns_show  	= array(
									$this->translate->_('Item Code'),$this->translate->_('Item Name'),$this->translate->_('UPC'),
									$this->translate->_('Case Price'),$this->translate->_('Pcs Price'),$this->translate->_('Case'),$this->translate->_('Pcs'),
									$this->translate->_('Total Amount')
								);
		$cpanel				= $this->SFA_Comman->getaltcodestatus();
		$altcode_status		= $cpanel["Use Alternate Code"]['status'];
		
		
		//$columns_array 	= array('itd.itemcode','im.itemdescription','im.unitspercase','FORMAT(itd.itemcaseprice,'.$this->decimalplaces.') as itemcaseprice','FORMAT(itd.itemprice,'.$this->decimalplaces.') as itemprice','FLOOR(itd.quantity/im.unitspercase) AS cases','(itd.quantity%im.unitspercase) AS pieces','FORMAT(((itd.itemcaseprice)*FLOOR(itd.quantity/im.unitspercase))+(itd.itemprice*(itd.quantity%im.unitspercase)),'.$this->decimalplaces.') AS total_amt','1 AS sett_tran_type','CONCAT(transactiontypecode,"_",routekey,"_",itemcode,"_",itd.batchdetailkey,"_",primary_key) AS edit_del_primary_id');
		$columns_array 	= array('itd.itemcode','im.itemdescription','im.unitspercase','FORMAT(itd.itemcaseprice,'.$this->decimalplaces.') as itemcaseprice','FORMAT(itd.itemprice,'.$this->decimalplaces.') as itemprice','FLOOR(itd.quantity/im.unitspercase) AS cases','(itd.quantity%im.unitspercase) AS pieces','FORMAT(((itd.itemcaseprice)*FLOOR(itd.quantity/im.unitspercase))+(itd.itemprice*(itd.quantity%im.unitspercase)),'.$this->decimalplaces.') AS total_amt','1 AS sett_tran_type','primary_key AS edit_del_primary_id');
		
		if($altcode_status)
			$columns_array[0] 	= 'im.alternatecode';
		
		// UPDATE THE RECORD
		if($params["update"]=="yes"){
			
			$updateData["1"] = $params["cases"];
			$updateData["2"] = $params["pieces"];
			//$updateData["3"] = substr($params["id"], strrpos( $params["id"], '_' )+1);
			$updateData["3"] = $params["id"];
			
			// call sp for edit currencydetail
			$r_edit = $this->SFA_Comman->executequery('CALL sp_edit_hhctransaction_loadstock_viewloadrequestgrid(?,?,?)',$updateData,'');
			
			SFA_Message::setMsg($this->translate->_('Update Record'));			
		}
		
		// IF EXTRA PARAMS ARE REQUIRED
		$ex_param = "";
		$additional_where_condition = array();
		if(isset($params["key"]) && $params["key"]>0){
			$ex_param = "/key/".$params["key"];
			$additional_where_condition[] = ' ( detailkey = "'.$params["key"].'" AND itd.transactiontypecode = 8 )';
		}
		
		// ARRAY FOR GRID PAGINATION
		$pagingparams = array(
					 "show_grid_heading" => false,
					 "grid_heading_message" => $this->translate->_('Overview'),
					 "show_records_per_page" => isset($formdata["show_records_per_page"])?$formdata["show_records_per_page"]:10,
					 "show_searchbox" => false,
					 "show_selectbox" => false,
					 "show_editlink" => true,
					 "show_deletelink" => false,
					 "show_deleteall" => false,
					 "primaryid" => "transactiontypecode",
					 "noeditfields" => array('alternatecode','itemcode','itemdescription','unitspercase','itemcaseprice','itemprice','total_amt'),
					 "currentlink" => array("/".$params['module']."/".$params['controller']."/".$params['action'].$ex_param),
					 "deletelink" => array("/id/#pattern#/delete/yes/msg/curr","#pattern#"),
					 "editlink" => array("/id/#pattern#/edit/yes","#pattern#"),
					 "nodata_message" => $this->translate->_('No Record(s) Found'),
					 "fetch_columns_inquery" => $columns_array,
					 "show_columns" => $columns_show,
					 "additional_where" => $additional_where_condition,
					 "show_columns_right_side" => array('itemprice','itemcaseprice','total_amt','cases','pieces'),
					 "show_header_right_side"=>array($this->translate->_('Case Price'),$this->translate->_('Pcs Price'),$this->translate->_('Case'),$this->translate->_('Pcs'),$this->translate->_('Total Amount')),
					 "show_total_columns"=>true,
					 "show_total_columns_value"=>array("cases"=>"0","pieces"=>"0","total_amt"=>"1"),
					 "show_total_columns_msg"=>array("itemdescription","Total",$this->decimalplaces),					 
					 );
		//$params['transind'] is transmitindicator if it is 1 then records should not delete
		if(!$this->checkaccess("update")|| $params['transind'] == "1")
        {
            $pagingparams["show_editlink"] = false;
        }
		if($params["edit"]=="yes"){
	
			$pagingparams["editmode"] = true;
			$pagingparams["editmodeid"] = $params["id"];
			$pagingparams["editmodevalue"] = "primary_key";  // put table's prymary key here
		}

		
		$pagingshow = new SFA_Ajaxpaging($pagingparams);
		
		// call common function of grid class
		$get_return_vals = $pagingshow->commnfunc();
		
		// call the stored procedure for fetch the data
		$param_array 	= array();
		$param_array[1] = '1';
		$param_array[2] = $get_return_vals['order_columns_name'];
		$param_array[3] = $get_return_vals['order_type'];
		$param_array[4] = $get_return_vals['offset'];
		$param_array[5] = (int)$get_return_vals['show_records_per_page'];
		$param_array[6] = implode(", ",$pagingparams["fetch_columns_inquery"]);
		$param_array[7] = strlen($get_return_vals['where_condition'])>0?$get_return_vals['where_condition']:'';	
		
		
		// called stored procedure for counter
		$result = $this->SFA_Comman->executequery('CALL sp_get_hhctransaction_stock_viewbeginstockgrid(?,?,?,?,?,?,?)',$param_array,'');    
		$data_arr["count"] 	= $result[0][0]['counter'];	
		$data_arr["data"][0] 	= $result[1];		
		$this->view->pagegridshow =  $pagingshow->summary_showdatagrid($data_arr);
		$this->view->addScriptPath(APPLICATION_PATH."/theme/default/datagrid/");
	
		$this->render("ajaxgrid");
    }
	/**
    * @name       viewbatchdetailAction
    * @since      27-01-2012
    * @version    Release: 1
    * @author     HD <hiren.d@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This action is use for the display batch detail record
    */
	public function viewbatchdetailAction()
	{
		$this->view->params = $params = $this->getRequest()->getParams();
		$this->view->formdata = $formdata = $this->_request->getPost();
		$this->_helper->layout->setLayout('popup');		
		
		$columns_show 	= array($this->translate->_('Expiry Date'),$this->translate->_('Batch Number'),$this->translate->_('Case'),$this->translate->_('Pcs'));
		$cols_array 	= array('DATE_FORMAT(expirydate,"%d-%m-%Y") AS expirydate','batchnumber','FLOOR(quantity/im.unitspercase) AS cases','(quantity%im.unitspercase) AS pieces');
		
		$extract = explode('_',$params['id']);
		
		$additional_where_condition = array();
		$additional_where_condition[] = "  be.transactiontypecode = ".$extract[0];
		$additional_where_condition[] = "  be.routekey = ".$extract[1];
		$additional_where_condition[] = "  be.itemcode = ".$extract[2];
		$additional_where_condition[] = "  be.batchdetailkey = ".$extract[3];
		
		// prepare the configuration for grid
		$pagingparams = array(
				"show_grid_heading" => true,
				"grid_heading_message" => $this->translate->_('Overview'),
				"show_records_per_page" => isset($formdata["show_records_per_page"])?$formdata["show_records_per_page"]:10,
				"show_searchbox" => false,
				"searchbox_value" => isset($formdata["grid_search_text"])?$formdata["grid_search_text"]:"",
				"show_selectbox" => false,
				"show_editlink" => false,
				"show_deletelink" => false,
				"show_deleteall" => false,
				"primaryid" => "routekey",
				"nodata_message" => $this->translate->_('No Record(s) Found'),
				"fetch_columns_inquery" => $cols_array,
				"show_columns" => $columns_show,
				"additional_where" => $additional_where_condition
				);
			// create grid class object
		$pagingshow = new SFA_Paging($pagingparams);
		
		// call common function of grid class
		$get_return_vals = $pagingshow->commnfunc();	
		
		// call the stored procedure for fetch the data
		$param_array 	= array();
		$param_array[1] = '1';
		$param_array[2] = '';
		$param_array[3] = $get_return_vals['order_columns_name'];
		$param_array[4] = $get_return_vals['order_type'];
		$param_array[5] = $get_return_vals['offset'];
		$param_array[6] = (int)$get_return_vals['show_records_per_page'];
		$param_array[7] = implode(", ",$pagingparams["fetch_columns_inquery"]);
		$param_array[8] = strlen($get_return_vals['where_condition'])>0?$get_return_vals['where_condition']:'';		
		
		// called stored procedure for counter
		$result = $this->SFA_Comman->executequery('CALL sp_get_hhctransaction_batchdetail(?,?,?,?,?,?,?,?,?,?)',$param_array,'');
	
		$data_arr["count"] 	= $result[0][0]['counter'];
		$data_arr["data"][0]  	= $result[1];
		$this->view->route_info = $result[2];
		
		// pass the data in summary_showdatagrid() function & create a final variable for view
		$this->view->pagegridshow =  $pagingshow->summary_showdatagrid($data_arr);		
		$this->view->addScriptPath(APPLICATION_PATH."/theme/default/datagrid/");
	}
	/**/
	
	    public function addloadrequestAction()
    {
		$this->view->params = $params = $this->getRequest()->getParams();
        $this->view->formdata = $formdata = $this->_request->getPost();

		$reqdate = date("Y-m-d",strtotime($formdata['txtreqdate']));
		
		if($formdata['txtreqdate'] != ''){
			
			$param_array 	= array();
			$param_array[1] = $reqdate;
			$param_array[2] = $formdata['hdnid'];			
			
			$this->SFA_Comman->executequery('CALL sp_edit_hhctransaction_stock_viewbeginstock(?,?)',$param_array,'');
			
			$this->_helper->redirector("loadrequest", "loadstock", "hhctransaction");
		}
		
	
		$this->view->itemgrid    = $this->view->BaseUrl("/".$params['module']."/".$params['controller']."/viewloadrequestgrid");
		
		$combo_data		= $this->SFA_Comman->executequery('CALL sp_combo_routemaster_loadrequest(?)','','');
		$this->view->route_info 	= $combo_data[0];
		$this->view->depot_route_info = $combo_data[1];
				
    }
	
	public function addloadrequestdetailAction()
    {
		
		$params = $this->getRequest()->getParams();
	
		if($params['hdndetailkey']=="")
		{
		$param_array = array();
		$param_array[1]  = $params['ddlroute'];
		$param_array[2]  = $params['hdnsalesman'];		
		$param_array[3]  = date('Y-m-d',strtotime($params['txtreqdate']));
		$param_array[4]  = $params['chkisurgent'];
		$param_array[5]  = $params['ddldepotroute'];	
		
		$return_header = $this->SFA_Comman->executequery('CALL sp_add_loadstock_inventorytransactionheader(?,?,?)',$param_array,'');
		}		
		if($return_header[0][0]['result']!=null || $params['hdndetailkey']!="")
		{
			$param_array = array();
			$param_array[1]  = $params['ddlitem'];
			if($params['hdndetailkey']=="")
			{
			echo $param_array[2]  = $return_header[0][0]['result'];}
			else
			{echo $param_array[2]  = $params['hdndetailkey'];}
			$param_array[3]  = $params['txtcases'];
			$param_array[4]  = $params['txtupc'];
			$param_array[5]  = $params['txtpieces'];
			$param_array[6]  = $params['txtsalesprice'];
			$param_array[7]  = $params['txtcaseprice'];
			
			//var_dump($param_array);
			//die;
			$return = $this->SFA_Comman->executequery('CALL sp_add_inventory_transaction_loadrequestgrid(?,?,?,?,?,?,?)',$param_array,'');
			
			if($return[0][0]['result'] == 'Duplicate') {
				SFA_Message::setErrorMsg($this->translate->_('Item Already Added To The Load Request.'));
			}
			else {
				
				$routekey = $return_header[0][0]['result'];
				SFA_Message::setMsg($this->translate->_('New Record'));
			}
		}
	else{
		echo "Not Inserted";
	}		
		exit;
    }
	 public function getrouteinfoAction()
    {
		$params = $this->getRequest()->getParams();
		
		$param_array 	= array();
		$param_array[1]	= $params['id'];		
		$route_info = $this->SFA_Comman->executequery('CALL sp_get_add_loadstock_getrouteinfo(?)',$param_array,'');		
		echo Zend_Json_Encoder::encode($route_info);
		exit;
    }
	
	public function exportpdfAction()
	{
		$this->view->params = $params = $this->getRequest()->getParams();
      
        $params['id'];
        $param_array = array();
        $param_array[1] = $params['id'];
		$res = $this->SFA_Comman->executequery('CALL sp_get_hhctransaction_stock_viewbeginstock(?)',$params['id'],'');		
		$res[0][0];
		/**/
		$cpanel				= $this->SFA_Comman->getaltcodestatus();
		$altcode_status		= $cpanel["Use Alternate Code"]['status'];
		
		
		//$columns_array 	= array('itd.itemcode','im.itemdescription','im.unitspercase','FORMAT(itd.itemcaseprice,'.$this->decimalplaces.') as itemcaseprice','FORMAT(itd.itemprice,'.$this->decimalplaces.') as itemprice','FLOOR(itd.quantity/im.unitspercase) AS cases','(itd.quantity%im.unitspercase) AS pieces','FORMAT(((itd.itemcaseprice)*FLOOR(itd.quantity/im.unitspercase))+(itd.itemprice*(itd.quantity%im.unitspercase)),'.$this->decimalplaces.') AS total_amt','1 AS sett_tran_type','CONCAT(transactiontypecode,"_",routekey,"_",itemcode,"_",itd.batchdetailkey,"_",primary_key) AS edit_del_primary_id');
		$columns_array 	= array('itd.itemcode','im.itemdescription','im.unitspercase','FORMAT(itd.itemcaseprice,'.$this->decimalplaces.') as itemcaseprice','FORMAT(itd.itemprice,'.$this->decimalplaces.') as itemprice','FLOOR(itd.quantity/im.unitspercase) AS cases','(itd.quantity%im.unitspercase) AS pieces','FORMAT(((itd.itemcaseprice)*FLOOR(itd.quantity/im.unitspercase))+(itd.itemprice*(itd.quantity%im.unitspercase)),'.$this->decimalplaces.') AS total_amt');
		$text_alt = 'itemcode';
		if($altcode_status){
			$text_alt = 'alternatecode';
			$columns_array[0] 	= 'im.alternatecode';}
			
			$additional_where_condition = ' AND ( detailkey = "'.$params['id'].'" AND itd.transactiontypecode = 8 )';
		
		// call the stored procedure for fetch the data
		$param_array 	= array();
		
		$param_array[1] = '';
		$param_array[2] ='desc';
		$param_array[3] = 0;
		$param_array[4] = 100;
		$param_array[5] = implode(", ",$columns_array);
		$param_array[6] = strlen($additional_where_condition)>0?$additional_where_condition:'';	
		
		// called stored procedure for counter
		$result = $this->SFA_Comman->executequery('CALL sp_get_hhctransaction_stock_viewbeginstockgrid_print(?,?,?,?,?,?,?)',$param_array,'');    
		$column_model_arr 	= $result[0];
				
		/**/
	  if($res[0][0]['isurgent'] == '0') 
	  { $text = 'No';} else { $text = 'Yes';}
	$html ="";
	$html .= "<head><link href='../../../../public/images/favicon.ico' type='image/x-icon' rel='shortcut icon'>";
	$html .= "<title>Load Request Detail</title>";
	$html .= "<meta charset='utf-8'></head>";
	$html .="<body style='font-family: DejaVuSansCondensed, sans-serif;'>";
	$html .="<h3 style='text-align:center'>Load Request Detail</h3>";
	$html .='<table >';
	  $html .='<tr >';
	  $html .=' <td >';
	  $html .= "<b>Transaction Date : </b>".$res[0][0]['transactiondate'];
	  $html .=' </td>';
	   $html .=' <td >';
	  $html .= "<b>Print Date :</b>"."".date('d-m-Y h:i:sa');
	  $html .=' </td>';
	  $html .='</tr>';
	  $html .='<tr>';
	  $html .=' <td >';
	  $html .= "<b>Document No. : </b>".$res[0][0]['documentnumber'];
	  $html .=' </td>';
	  $html .=' <td >';
	  $html .= "<b>Request Date : </b>".$res[0][0]['requestdate'];
	  $html .=' </td>';
	  $html .=' <td >';
	  $html .= "<b>Is Urgent : </b>".$text;
	  $html .=' </td>';	
	  $html .='</tr>';
	  $html .='<tr>';
	  $html .=' <td >';
	  $html .= "<b>Route Code: </b>".$res[0][0]['routecode'];
	  $html .=' </td>';
	  $html .=' <td >';
	  $html .= "<b>Route Name: </b>".$res[0][0]['routename'];
	  $html .=' </td>';
	  $html .=' <td >';
	  $html .= "<b>Salesman : </b>".$res[0][0]['salesmanname1'];
	  $html .=' </td>';	 
	  $html .='</tr>';
	  $html .='<tr>'; 
	  $html .='</tr>';
	 
	  $html .='</table>';

	  $html .='<table style="border-collapse: collapse;">';
	  $html .='<tr style=" border: dotted; border-width: 1px 0;">';		
      $html .=' <th style="height:20px;text-align:left;">';
      $html .=$this->translate->_('Item Code');
      $html .='  </th>';	
	  $html .=' <th style="height:20px;width:200px;text-align:left;">';
      $html .=$this->translate->_('Description');
      $html .='  </th>';
	  $html .=' <th style="height:20px;width:50px;">';
      $html .=$this->translate->_('UPC');
      $html .='  </th>';
	  $html .=' <th style="height:20px;width:70px;text-align:right;">';
      $html .=$this->translate->_('Case Price');
      $html .='  </th>';
	  $html .=' <th style="height:20px;width:70px;text-align:right;">';
      $html .=$this->translate->_('Pcs Price');
      $html .='  </th>';
	  $html .=' <th style="height:20px;width:70px;">';
      $html .=$this->translate->_('Case')."/".$this->translate->_('Pcs');
	  $html .='  </th>';	  
	  $html .=' <th style="height:20px;text-align:right;">';
      $html .=$this->translate->_('Amount');
      $html .='  </th>';
	  $html .='</tr>';
	
		/**/
		
		for($j=0;$j<count($column_model_arr);$j++){
			$html .='<tr>';	
			$html .=' <td style="text-align:center;text-align:left;">';			
			$html .=  $column_model_arr[$j][$text_alt];			
			$html .='  </td>';
			$html .=' <td style="text-align:center;text-align:left;">';			
			$html .=  $column_model_arr[$j]['itemdescription'];			
			$html .='  </td>';
			$html .=' <td style="text-align:center">';			
			$html .=  $column_model_arr[$j]['unitspercase'];			
			$html .='  </td>';
			$html .=' <td style="text-align:right">';			
			$html .=  $column_model_arr[$j]['itemcaseprice'];			
			$html .='  </td>';
			$html .=' <td style="text-align:right">';			
			$html .=  $column_model_arr[$j]['itemprice'];			
			$html .='  </td>';
			$html .=' <td style="text-align:center">';			
			$html .=  $column_model_arr[$j]['cases']."/".$column_model_arr[$j]['pieces'];
			$tot_cases +=$column_model_arr[$j]['cases'];
			$tot_pieces +=$column_model_arr[$j]['pieces'];			
			$html .='  </td>';
			$html .=' <td style="text-align:right">';			
			//$html .=  $column_model_arr[$j]['total_amt'];			 
            $html .=$numberString = str_replace(',','',$column_model_arr[$j]['total_amt']);			
			$tot_amt += $numberString;			
			$html .='  </td>';
			$html .='</tr>';			
		}
		/**/
			$html .='<tr style=" border: dotted; border-width: 1px 0;">';	
			$html .=' <td colspan="2" style="text-align:center">';			
			$html .= ' <b>Total</b>';			
			$html .='  </td>';
			$html .=' <td  style="text-align:center">';			
			$html .=  "";			
			$html .='  </td>';
			$html .=' <td  style="text-align:center">';			
			$html .=  "";			
			$html .='  </td>';	
			$html .=' <td  style="text-align:center">';			
			$html .=  "";			
			$html .='  </td>';
			$html .=' <td  style="text-align:center">';			
			$html .=  $tot_cases."/".$tot_pieces;
			$html .='  </td>';	
			$html .=' <td  style="text-align:right">';			
			$html .= str_replace(',','',number_format($tot_amt, $this->decimalplaces));			
			$html .='  </td>';				
			$html .='</tr>';
		/**/
			$html .='</table>';
			$html .="</body>";
	require_once('mpdf/mpdf.php');
	
    $mpdf=new mPDF('c','A4',9,'',32,25,27,25,16,13);	
	
    $mpdf->setFooter('{PAGENO}');
    $mpdf->WriteHTML($html);
    $mpdf->Output('loadrequest.pdf','I');
    exit;   
	}
	
	
}