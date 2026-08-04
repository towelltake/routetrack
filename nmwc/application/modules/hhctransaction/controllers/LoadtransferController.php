<?php
/**
* @name       LoadtransferController
* @since
* @version    Release: 1
* @author     M@M <miral@elantechnologies.com>
* @copyright  Elan Technologies
* @param
*
* This controller is manage hhctransaction module.
*/


class Hhctransaction_LoadtransferController extends Hhctransaction_Library_Controller_Action_Abstract
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
    * @name       loadtransferAction
    * @since      01-02-2012
    * @version    Release: 1
    * @author     M@M <miral@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This action is for display transfer in stock
    *
    */
    public function loadtransferAction()
    {
		$this->view->params = $params = $this->getRequest()->getParams();
        $this->view->formdata = $formdata = $this->_request->getPost();
		
		$cols_array 	= array('ith.routecode','sm.salesmanname1','rm.routename','ith.documentnumber','ith.transactiontime','ith.detailkey AS edit_del_primary_id');
		$columns_show 	= array($this->translate->_('Route Code'),$this->translate->_('Salesman'),$this->translate->_('Route Name'),$this->translate->_('Document Number'),$this->translate->_('Transaction Time'));
		
		
		$Common_NameSpace = new Zend_Session_Namespace('TransferIn');
		
		$last_url 		= htmlspecialchars($_SERVER['HTTP_REFERER']);
		$end_last_url 	= explode('/',$last_url);
		
		if(end($end_last_url) == 'loadtransfer' || strpos($last_url,'viewloadtransfer') || strpos($last_url,'/loadtransfer/' )) {
			
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
		$additional_where_condition[] = "  ith.transactiontype = 2 ";
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
				"extralink" => array(array("View","/".$params['module']."/".$params['controller']."/viewloadtransfer/id/#pattern#","#pattern#")),
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
		$param_array[9] = " AND ( itd.transactiontypecode = 2 OR itd.transactiontypecode = 3 OR itd.transactiontypecode = 4 )";
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
    * @name       viewloadtransferAction
    * @since      02-02-2012
    * @version    Release: 1
    * @author     HD <hiren.d@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This action is for add begin stock
    *
    */
    public function viewloadtransferAction()
    {
		$this->view->params = $params = $this->getRequest()->getParams();
        $this->view->formdata = $formdata = $this->_request->getPost();
		
		// Begin Stock Datagrid
		$ex_param = "";
		if(isset($params["id"]) && $params["id"]>0)
			$ex_param = "/key/".$params["id"];
	
		$this->view->itemgrid    = $this->view->BaseUrl("/".$params['module']."/".$params['controller']."/viewloadtransfergrid".$ex_param);
		
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
    public function viewloadtransfergridAction()
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
			$additional_where_condition[] = ' ( detailkey = "'.$params["key"].'" AND itd.transactiontypecode = "'.$params["key1"].'" )';
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
    * @name       damageretAction
    * @since      01-02-2012
    * @version    Release: 1
    * @author     M@M <miral@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This action is for display transfer in stock
    *
    */
    public function damageretAction()
    {
		$this->view->params = $params = $this->getRequest()->getParams();
        $this->view->formdata = $formdata = $this->_request->getPost();

		$cols_array 	= array('cm.customername','ih.invoicenumber','FORMAT(id.salescaseprice,'.$this->decimalplaces.') AS salescaseprice','FLOOR((id.damagedqty/im.unitspercase)) AS caseqty','FORMAT(id.salesprice,'.$this->decimalplaces.') AS salesprice','(id.damagedqty%im.unitspercase) AS salesqty','ih.transactionkey AS edit_del_primary_id');
		$columns_show 	= array($this->translate->_('Customer Name'),$this->translate->_('Invoice No.'),$this->translate->_('Case Price'),$this->translate->_('Case Qty.'),$this->translate->_('Sales Price'),$this->translate->_('Sales Qty.'));
		
		$Common_NameSpace = new Zend_Session_Namespace('DamageReturn');
		
		$last_url 		= htmlspecialchars($_SERVER['HTTP_REFERER']);
		$end_last_url 	= explode('/',$last_url);
		
		if(end($end_last_url) == 'damageret' || strpos($last_url,'viewdamageret') || strpos($last_url,'/damageret/' )) {
			
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
		$additional_where_condition[] = " ih.totaldamagedamount > 0";
		$additional_where_condition[] = " (ih.transactiondate BETWEEN \'".date("Y-m-d 00:00:00",strtotime(str_replace('/', '-', $Common_NameSpace->tdate)))."\' AND \'".date("Y-m-d 23:59:59",strtotime(str_replace('/', '-', $Common_NameSpace->tdate)))."\' )";
		
		if($Common_NameSpace->route > 0) {
			$additional_where_condition[] = "  ih.routecode =  ".$Common_NameSpace->route;
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
				"extralink" => array(array("View","/".$params['module']."/".$params['controller']."/viewdamageret/id/#pattern#","#pattern#")),
				"nodata_message" => $this->translate->_('No Record(s) Found'),
				"fetch_columns_inquery" => $cols_array,
				"show_columns" => $columns_show,
				"additional_where" => $additional_where_condition,
				"show_columns_right_side" => array('caseqty','salesqty','salescaseprice','salesprice'),
				"show_header_right_side"=>array($this->translate->_('Case Price'),$this->translate->_('Case Qty.'),$this->translate->_('Sales Price'),$this->translate->_('Sales Qty.')),
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
		
		// called stored procedure for counter
		$result = $this->SFA_Comman->executequery('CALL sp_get_hhctransaction_loadtransfer_damageret(?,?,?,?,?,?,?,?)',$param_array,'');
		
		$this->view->route_info 	= $result[0];
		$data_arr["count"] 			= $result[1][0]['counter'];
		$data_arr["data"][0] 		= $result[2];
		
		// pass the data in summary_showdatagrid() function & create a final variable for view
		$this->view->pagegridshow =  $pagingshow->summary_showdatagrid($data_arr);
		
		$this->view->addScriptPath(APPLICATION_PATH."/theme/default/datagrid/");
    }
	 /**
    * @name       viewdamageretAction
    * @since      02-02-2012
    * @version    Release: 1
    * @author     HD <hiren.d@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This action is for view damaged return
    *
    */
    public function viewdamageretAction()
    {
		$this->view->params = $params = $this->getRequest()->getParams();
        $this->view->formdata = $formdata = $this->_request->getPost();
		
		// Begin Stock Datagrid
		$ex_param = "";
		if(isset($params["id"]) && $params["id"]>0)
			$ex_param = "/key/".$params["id"];
	
		$this->view->itemgrid    = $this->view->BaseUrl("/".$params['module']."/".$params['controller']."/viewdamageretgrid".$ex_param);
		
		$res = $this->SFA_Comman->executequery('CALL sp_get_hhctransaction_loadtransfer_viewdamageret(?)',$params['id'],'');
		
		$this->view->formdata 	= $res[0][0];
		$this->view->route_info = $res[1];
    }
    /**
    * @name       viewdamageretgridAction
    * @since      22-02-2012
    * @version    Release: 1
    * @author     AS <alpesh@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This action is use for view item grid in viewdamageretgridAction
    */
    public function viewdamageretgridAction()
    {
		$this->view->params = $params = $this->getRequest()->getParams();
		
        // IF EXTRA PARAMS ARE REQUIRED
        $ex_param = "";
        if(isset($params["key"]) && $params["key"]>0)
                $ex_param = "/key/".$params["key"];
		$cpanel				= $this->SFA_Comman->getaltcodestatus();
		$altcode_status		= $cpanel["Use Alternate Code"]['status'];
		
		if($altcode_status)
		{
			$cols_array 	= array('ih.invoicenumber','im.alternatecode','im.itemshortdescription','im.unitspercase AS upc',
								'FORMAT(id.returncaseprice,'.$this->decimalplaces.') AS returncaseprice',
								'FORMAT(id.returnprice,'.$this->decimalplaces.') AS returnprice',								
								'FLOOR(id.damagedqty+expiryqty) AS qty',
								'FORMAT(return_amount,'.$this->decimalplaces.') AS total_amt','1 AS sett_tran_type','CONCAT(id.transactionkey,"_",id.routekey,"_",id.itemcode,"_",id.visitkey,"_001") AS edit_del_primary_id');
		}
		else
		{
			$cols_array 	= array('ih.invoicenumber','id.itemcode','im.itemshortdescription','im.unitspercase AS upc',
								'FORMAT(id.returncaseprice,'.$this->decimalplaces.') AS returncaseprice',
								'FORMAT(id.returnprice,'.$this->decimalplaces.') AS returnprice',								
								'FLOOR(id.damagedqty+expiryqty) AS qty',
								'FORMAT(return_amount,'.$this->decimalplaces.') AS total_amt','1 AS sett_tran_type','CONCAT(id.transactionkey,"_",id.routekey,"_",id.itemcode,"_",id.visitkey,"_001") AS edit_del_primary_id');			
		}
		$columns_show 	= array($this->translate->_('Invoice No.'),$this->translate->_('ItemCode'),$this->translate->_('Description'),$this->translate->_('UPC'),
								$this->translate->_('CasePrice'),$this->translate->_('PcsPrice'),$this->translate->_('Quantity'),$this->translate->_('Damaged Amount'));
		
		// IF EXTRA PARAMS ARE REQUIRED
		$ex_param = "";
		$additional_where_condition = array();
		if(isset($params["key"]) && $params["key"]>0){
			$ex_param = "/key/".$params["key"];
			$additional_where_condition[] = ' ( id.transactionkey = "'.$params["key"].'" AND id.damagedqty > 0 )';
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
					 "primaryid" => "primary_key",
					 "currentlink" => array("/".$params['module']."/".$params['controller']."/".$params['action'].$ex_param),
					 "deletelink" => array("/id/#pattern#/delete/yes/msg/curr","#pattern#"),
					 "editlink" => array("/id/#pattern#/edit/yes","#pattern#"),
					 "nodata_message" => $this->translate->_('No Record(s) Found'),
					 "fetch_columns_inquery" => $cols_array,
					 "show_columns" => $columns_show,
					 "additional_where" => $additional_where_condition,
					 "show_columns_right_side" => array('returncaseprice','returnprice','qty','total_amt'),
					 "show_header_right_side"=>array($this->translate->_('CasePrice'),$this->translate->_('PcsPrice'),$this->translate->_('Quantity'),$this->translate->_('Damaged Amount')),
					 "show_total_columns"=>true,
					 "show_total_columns_value"=>array("qty"=>"0","total_amt"=>"1"),
					 "show_total_columns_msg"=>array("upc","Total",$this->decimalplaces),
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
		$result = $this->SFA_Comman->executequery('CALL sp_get_hhctransaction_loadtransfer_viewdamageretgrid(?,?,?,?,?,?,?)',$param_array,'');    
		$data_arr["count"] 		= $result[0][0]['counter'];	
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
		if(isset($extract[4])) {
			$additional_where_condition[] = "  be.transactiontypecode = ".$extract[0];
			$additional_where_condition[] = "  be.routekey = ".$extract[1];
			$additional_where_condition[] = "  be.itemcode = ".$extract[2];
			$additional_where_condition[] = "  be.visitkey = ".$extract[3];
		}
		else {
			$additional_where_condition[] = "  be.transactiontypecode = ".$extract[0];
			$additional_where_condition[] = "  be.routekey = ".$extract[1];
			$additional_where_condition[] = "  be.itemcode = ".$extract[2];
			$additional_where_condition[] = "  be.batchdetailkey = ".$extract[3];
		}
		
		
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
}