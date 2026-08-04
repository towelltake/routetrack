<?php
/**
* @name       TransactionController
* @since
* @version    Release: 1
* @author     HD <hiren.d@elantechnologies.com>
* @copyright  Elan Technologies
* @param
*
* This controller is manage user inventory module.
*/
class Inventory_TransactionController extends Inventory_Library_Controller_Action_Abstract
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
		$this->translate 	= Zend_Registry::get('Zend_Translate');		
		$this->currentUser 	= SFA_Loginauth::getIdentity();		
		
		if(!isset($this->currentUser) || empty($this->currentUser)) {
			SFA_Message::setMsg($this->translate->_('Do Login'));
			//$this->_helper->redirector("index", "index", "home");
			$url = $this->view->baseUrl();
			echo '<script type="text/javascript">window.location="'.$url.'";</script>';
			exit;
		}
		$this->css 					= $this->translate->_('CSS');
		$this->view->css 			= $this->css;
		$this->view->overview		= $this->translate->_('Overview');
		$this->view->general		= $this->translate->_('General');
		$this->view->setting1		= $this->translate->_('Settings 1');
		$this->view->details		= $this->translate->_('Details');
		$this->view->required		= $this->translate->_('Required');
		$this->view->colan			= $this->translate->_('Colan');		
		$this->SFA_Comman 			= new SFA_Comman();
		$this->view->decimalplaces 	= $this->SFA_Comman->getdecimalplaces();
		$this->view->sec_lang		= $this->SFA_Comman->getsecondlanguage();
		$this->sec_lang 			= $this->view->sec_lang;
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
         *   Acl Code start
         */
        $getparams_init = $this->getRequest()->getParams();
        $getpost_init = $this->_request->getPost();
        
        if(in_array($getparams_init['action'],$this->current_read_delete_arr))
        {
            if($getpost_init["hdDelete"]==1 && !$this->checkaccess("delete")) {            
                $this->_forward('noaccess','aclaccess','home', array("actiontype"=>"delete","modulename"=>$this->currentmodulename));            
            } elseif(!$this->checkaccess("read")) {                
                $this->_forward('noaccess','aclaccess','home', array("actiontype"=>"read","modulename"=>$this->currentmodulename));                
            }
        }
        elseif(in_array($getparams_init['action'],$this->current_insert_update_arr))
        {
            if($params['id'] > 0 && !$this->checkaccess("update")) {            
                $this->_forward('noaccess','aclaccess','home', array("actiontype"=>"update","modulename"=>$this->currentmodulename));                
            } elseif(!$this->checkaccess("insert")) {                
                $this->_forward('noaccess','aclaccess','home', array("actiontype"=>"insert","modulename"=>$this->currentmodulename));                
            }
        }
		/**
         *   Acl Code end
         */
    }
    
    /**
    * @name       dailysalesmanload
    * @since      1-2-2012
    * @version    Release: 1
    * @author     PM <pankit@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This action is use for the daily salesman load report
    */
	public function dailysalesmanloadAction()
    {
		//view variable declaration
		$this->view->params 	= $params 	= $this->getRequest()->getParams();
		$this->view->formdata 	= $formdata 	= $this->_request->getPost();
		
		if($params['chngst'] == 1) {	    
			$param_array    = array();
			$param_array[1] = (str_replace('_',',',substr($params['ids'],0,-1)));
		   
			$result 	= $this->SFA_Comman->executequery('CALL sp_edit_inventory_transaction_changestatus(?,?)',$param_array,'');
			SFA_Message::setMsg($this->translate->_('Load Status Changed Sucessfully.'));
			$this->_helper->redirector('dailysalesmanload', 'transaction', 'inventory');
		}
		// CREATE A SESSION NAMESPACE
		$Common_NameSpace = new Zend_Session_Namespace('Daily_Salesman_Load');
		
		if($formdata['btnreset'] == 'RESET') {
			$formdata["txtdate"] 	= '';
			$Common_NameSpace->tdate	= '';
		}
		
		$sel_date = ($formdata["txtdate"] != '') ? $formdata["txtdate"] : $Common_NameSpace->tdate;
	
		// ADD DATE VALUE IN SESSION
		if($sel_date != '') {
			$Common_NameSpace->tdate 	= $sel_date;
			$this->view->date			= $sel_date;
		}
		
		//SFA_Comman::pre($formdata);
		if($formdata["hdDelete"]==1)
		{
			$ids = implode(',',$formdata['chk']);
			$param_array 	= array();
			$param_array[1]	= $ids;
			$param_array[2]	= $this->currentUser->username;
			$param_array[3] = $Common_NameSpace->tdate;
			
			$result 	= $this->SFA_Comman->executequery('CALL sp_delete_inventory_transaction_dailysalesmanload(?,?,?)',$param_array,'');
			
			if($result[0][0]['deleted_id'] =='')
			{
				$ids	= explode(',',$ids);
				$checked 	= $ids;
			
				//SFA_Message::setErrorMsg($this->translate->_('Record Already In use'));
			}
			else
			{
				$deleted_id = explode(',',$result[0][0]['deleted_id']);
				$ids		= explode(',',$ids);
				$checked 	= array_diff($ids,$deleted_id);
			
			if(count($ids) != count($deleted_id)){
				//SFA_Message::setErrorMsg($this->translate->_('Record Already In use'));
			}
			
			SFA_Message::setMsg($this->translate->_('Delete Record'));
			}
		}
	
		//variable declaration for grid title
		//$columns_show 	= array($this->translate->_('Route'),$this->translate->_('Route Name'),$this->translate->_('Salesman'),
			//		$this->translate->_('Case'),$this->translate->_('Pcs'),$this->translate->_('Load No.'),
				//	$this->translate->_('Reference No.'),$this->translate->_('Status'));
		$columns_show 	= array($this->translate->_('Route'),$this->translate->_('Route Name'),$this->translate->_('Salesman'),
					$this->translate->_('Load No.'),
					$this->translate->_('Reference No.'),$this->translate->_('Status'));			
		
		//$cols_array 	= array('loaddetail.routecode','routename','salesmanname1','SUM(cases) AS cases','SUM(units) AS units','loadperiodnumber','FORMAT(SUM((`cases` * caseprice)+(units * salesprice)),'.$this->decimalplaces.') AS loadvalue','`status` AS loadstatus','loaddetailcode as edit_del_primary_id');
		$cols_array 	= array('loaddetail.routecode','routename','salesmanname1','loadperiodnumber','erpreferencenumber','`status` AS loadstatus','loaddetailcode as edit_del_primary_id');
			
		if($this->css == 'ar_') {
			$cols_array[1]	= 'arbroutename AS routename';
			$cols_array[2]	= 'arbsalesmanname1 AS salesmanname1';
		}
		
		$not_in_search 		= array();
		//$not_in_search[] 	= 'SUM(cases) AS cases';
		//$not_in_search[] 	= 'SUM(units) AS units';
		//$not_in_search[] 	= 'FORMAT(SUM((`cases` * caseprice)+(units * salesprice)),'.$this->decimalplaces.') AS loadvalue';
		$not_in_search[] 	= 'loaddetailcode as edit_del_primary_id';	    
			
		// ADDITIONAL WHERE CONDITION
		if($Common_NameSpace->tdate)
			$additional_where_condition[] = " (ddate = STR_TO_DATE(\'".$Common_NameSpace->tdate."\',\'%d-%m-%Y\'))";
			// ARRAY FOR GRID PAGINATION
		$pagingparams = array(
			"show_grid_heading" => true,
			"grid_heading_message" => $this->translate->_('Overview'),
			"pagename" => $this->translate->_('Daily Salesman Load'),
			"show_records_per_page" => isset($formdata["show_records_per_page"])?$formdata["show_records_per_page"]:10,
			"show_searchbox" => true,
			"searchbox_value" => isset($formdata["grid_search_text"])?$formdata["grid_search_text"]:"",
			"show_selectbox" => true,            
			"show_deletelink" => false,
			"status_cols" => array(
						array(
							"cols_name" => "loadstatus",
							"status_change" => array("0"=>"Not Used","1"=>"Used")
						)
						  ),
			"no_search_fields" => $not_in_search,
			"selected_list" => $checked,
			"show_deleteall" => false,
			"primaryid" => "loaddetailcode",
			"nodata_message" => $this->translate->_('No Record(s) Found'),
			"fetch_columns_inquery" => $cols_array,
			"show_columns" => $columns_show,
			"show_columns_right_side" => array('loadvalue'),
			"show_header_right_side"=>array($this->translate->_('Load Value')),
			"additional_where" => $additional_where_condition
		);
		
		
		/* Added By Hiren dave if load from erp is checked in control panel then data would be show only view purpose. */
		$Settings_NameSpace = new Zend_Session_Namespace('Settings');		
		if($Settings_NameSpace->settings['Load From ERP']['status'] == 1)
		{
			$pagingparams["show_extralink"] = true;
			$pagingparams["extralink"] 		= array(array("View","/inventory/transaction/adddailysalesmanload/id/#pattern#/view/yes/","#pattern#"));
			$this->view->disableadd 		= true;	
		}
		else
		{
			$pagingparams["editlink"] 		= array("/inventory/transaction/adddailysalesmanload/id/#pattern#/edit/yes/","#pattern#");
			$pagingparams["show_editlink"] 	= true;
			$this->view->disableadd 		= false;	
		}
		
	
		// create grid class object
		$pagingshow = new SFA_Paging($pagingparams);
		
		// call common function of grid class
		$get_return_vals = $pagingshow->commnfunc();
			
		//print_r($get_return_vals['where_condition']);
		
			
		// call the stored procedure for fetch the data
		$param_array	= array();
		$param_array[1] = '1';
		$param_array[2] = '';
		$param_array[3] = $get_return_vals['order_columns_name'];
		$param_array[4] = $get_return_vals['order_type'];
		$param_array[5] = $get_return_vals['offset'];
		$param_array[6] = (int)$get_return_vals['show_records_per_page'];
		$param_array[7] = implode(", ",$pagingparams["fetch_columns_inquery"]);
		$param_array[8] = strlen($get_return_vals['where_condition'])>0?$get_return_vals['where_condition']:'';
	
		$downloadCSV = (isset($formdata['downloadcsv'])) ? $formdata['downloadcsv'] : $params['downloadcsv'];
	
		// Hiren Dave on 19 Nov, 2012 - START - to check if need to print data
		$printData = (isset($formdata['printData'])) ? $formdata['printData'] : $params['printData'];
		
		// called stored procedure for counter
		$result = $this->SFA_Comman->executequery('CALL sp_get_inventory_transaction_dailysalesmanload(?,?,?,?,?,?,?,?)',$param_array,'',$downloadCSV,$printData,2,$pagingparams);
		
		$this->view->total_record	= $result[0][0]['counter'];
		$data_arr["count"] 		= $result[0][0]['counter'];
		$data_arr["data"][0] 		= $result[1];
			
		// pass the data in summary_showdatagrid() function & create a final variable for view
		$this->view->pagegridshow =  $pagingshow->summary_showdatagrid($data_arr);
		
		$this->view->addScriptPath(APPLICATION_PATH."/theme/default/datagrid/");
    }

    
    /**
    * @name       adddailysalesmanload
    * @since      1-2-2012
    * @version    Release: 1
    * @author     PM <pankit@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This action is use for deatil view of daily salesman report
    */
    public function adddailysalesmanloadAction()
	{
        //view variable declaration
        $this->view->params = $params = $this->getRequest()->getParams();
        $this->view->formdata = $formdata = $this->_request->getPost();
		
		$Menu_NameSpace = new Zend_Session_Namespace('Menu');
		$menu_array     = $Menu_NameSpace->header_menu;
		
		
		if($menu_array['Standard Depot Inventory']['status'] == 1 ) {		
			$this->view->depotinv_status = 14;
		} elseif($menu_array['Advanced Depot Inventory']['status'] == 1) {
			$this->view->depotinv_status = 64;
		} else {
			$this->view->depotinv_status = 0;
		}

		// IF EXTRA PARAMS ARE REQUIRED
		$ex_param = "";
		if(isset($params["id"]) && $params["id"]>0)
			$ex_param = "/key/".$params["id"];
		
		$Common_NameSpace = new Zend_Session_Namespace('Daily_Salesman_Load');
		
		$sel_date = ($formdata["txtdate"] != '') ? $formdata["txtdate"] : $Common_NameSpace->tdate;
	
		// ADD DATE VALUE IN SESSION
		if($sel_date != '') {
			$Common_NameSpace->tdate 	= $sel_date;
			$this->view->date		= $sel_date;
		}	
		
		$this->view->itemgrid   = $this->view->BaseUrl("/".$params['module']."/".$params['controller']."/dailysalesmanloadgrid".$ex_param);
		
		/* Added By Hiren dave if load from erp is checked in control panel then data would be show only view purpose. */
		$Settings_NameSpace = new Zend_Session_Namespace('Settings');
		
		if($Settings_NameSpace->settings['Load From ERP']['status'] == 1)
		{
			$this->view->disableadd = true;	
		}
		else
		{
			$this->view->disableadd = false;
		}
		
		$id = $params['id'] > 0 ? $params['id'] : '0';
		
		$combo_data		= $this->SFA_Comman->executequery('CALL sp_get_inventory_transaction_adddailysalesmanload(?)',$id,'');
	
		$this->view->item 			= $combo_data[0];
		$this->view->route 			= $combo_data[1];
		$this->view->salesman 		= $combo_data[2];	
		$item_array 				= $combo_data[3];
		$this->view->batch_status 	= $combo_data[4][0]['status'];
		$this->view->load_method 	= $combo_data[5][0]['status'];
		$this->view->flagname 		= $combo_data[5][0]['flagname'];
		$this->view->loadtime 		= $combo_data[5][0]['loadtime'];
		
		
		
		if(count($formdata) > 0) {
			 if($formdata['hdnid'] > 0){
			SFA_Message::setMsg($this->translate->_('Update Record'));
				 }else{
			SFA_Message::setMsg($this->translate->_('New Record'));
				 }
				 $this->_helper->redirector('adddailysalesmanload', 'transaction', 'inventory');
		}
		elseif($params['id'] > 0)
		{
			$data						= $combo_data[5];
			$res['txtload']				= $data[0]['loadperiodnumber'];
			$res['ddlroute']			= $data[0]['routecode'];
			$res['txtsalesman']			= $data[0]['salesmanname1'];
			$res['hdnsalesman']			= $data[0]['salesmancode'];
			$res['txterprefno']			= $data[0]['erpreferencenumber'];
			$this->view->date			= $data[0]['ddate'];
			$this->view->load_status	= $data[0]['load_status'];
			
			$this->view->formdata = $res;
			
			$this->view->flagname 		= $combo_data[7][0]['flagname'];
		}
    }
	/**/
	
	
	
	
	
	
	public function deliveryAction()
    {
		//view variable declaration
		$this->view->params 	= $params 	= $this->getRequest()->getParams();
		$this->view->formdata 	= $formdata 	= $this->_request->getPost();
		
		// CREATE A SESSION NAMESPACE
		$Common_NameSpace = new Zend_Session_Namespace('Daily_Salesman_Load');
		
		if($formdata['btnreset'] == 'RESET') {
			$formdata["txtdate"] 	= '';
			$Common_NameSpace->tdate	= '';
		}
		
		$sel_date = ($formdata["txtdate"] != '') ? $formdata["txtdate"] : $Common_NameSpace->tdate;
	
		// ADD DATE VALUE IN SESSION
		if($sel_date != '') {
			$Common_NameSpace->tdate 	= $sel_date;
			$this->view->date			= $sel_date;
		}
		
		//SFA_Comman::pre($formdata);
		if($formdata["hdDelete"]==1)
		{
			$ids = implode(',',$formdata['chk']);
			$param_array 	= array();
			$param_array[1]	= $ids;			
			$result 	= $this->SFA_Comman->executequery('CALL sp_delete_inventory_transaction_deliveryheader(?,?,?)',$param_array,'');
			
				$deleted_id = explode(',',$result[0][0]['deleted_id']);
				$ids		= explode(',',$ids);
				$checked 	= array_diff($ids,$deleted_id);
			
			SFA_Message::setMsg($this->translate->_('Delete Record'));
			
		}$columns_show 	= array($this->translate->_('Route'),$this->translate->_('Route Name'),$this->translate->_('Salesman'),
					$this->translate->_('Delivery No.'),
					$this->translate->_('Customer code'),$this->translate->_('Status'));			
		/*loaddetail.deliveryroute, routename, salesmanname1, deliveryno, loaddetail.customercode, customername,`delivered` AS loadstatus, deliveryno as edit_del_primary_id'
*/
		$cols_array 	= array('loaddetail.deliveryroute','routename','salesmanname1','deliveryno','loaddetail.customercode','loadsheetnumber','deliveryno as edit_del_primary_id');
			
		if($this->css == 'ar_') {
			$cols_array[1]	= 'arbroutename AS routename';
			$cols_array[2]	= 'arbsalesmanname1 AS salesmanname1';
		}
		
		//$not_in_search 		= array();
		//$not_in_search[] 	= 'deliveryno as edit_del_primary_id';	    
			
		// ADDITIONAL WHERE CONDITION
		if($Common_NameSpace->tdate)
			$additional_where_condition[] = " (deliverydate = STR_TO_DATE(\'".$Common_NameSpace->tdate."\',\'%d-%m-%Y\'))";
			// ARRAY FOR GRID PAGINATION
		$pagingparams = array(
			"show_grid_heading" => true,
			"grid_heading_message" => $this->translate->_('Overview'),
			"pagename" => $this->translate->_('Delivery'),
			"show_records_per_page" => isset($formdata["show_records_per_page"])?$formdata["show_records_per_page"]:10,
			"show_searchbox" => true,
			"searchbox_value" => isset($formdata["grid_search_text"])?$formdata["grid_search_text"]:"",
			"show_selectbox" => true,            
			"show_deletelink" => false,
			/*
			"status_cols" => array(
						array(
							"cols_name" => "delivered",
							"status_change" => array("0"=>"Not Delivered","1"=>"Delivered","2"=>"Partial Delivered")
						)
						  ), */
			"no_search_fields" => $not_in_search,
			"selected_list" => $checked,
			"show_deleteall" => false,
			"primaryid" => "deliveryno",
			"nodata_message" => $this->translate->_('No Record(s) Found'),
			"fetch_columns_inquery" => $cols_array,
			"show_columns" => $columns_show,			
			"additional_where" => $additional_where_condition
		);
			
			
			$pagingparams["editlink"] 		= array("/inventory/transaction/adddelivery/id/#pattern#/edit/yes/","#pattern#");
			if($this->checkaccess("update"))			
			{$pagingparams["show_editlink"] 	= true;}
			else
			{$pagingparams["show_editlink"] 	= false;}
		
			$this->view->disableadd 		= false;	
		
		
	
		// create grid class object
		$pagingshow = new SFA_Paging($pagingparams);
		
		// call common function of grid class
		$get_return_vals = $pagingshow->commnfunc();
			
		//print_r($get_return_vals['where_condition']);
		
			
		// call the stored procedure for fetch the data
		$param_array	= array();
		$param_array[1] = '1';
		$param_array[2] = '';
		$param_array[3] = $get_return_vals['order_columns_name'];
		$param_array[4] = $get_return_vals['order_type'];
		$param_array[5] = $get_return_vals['offset'];
		$param_array[6] = (int)$get_return_vals['show_records_per_page'];
		$param_array[7] = implode(", ",$pagingparams["fetch_columns_inquery"]);
		$param_array[8] = strlen($get_return_vals['where_condition'])>0?$get_return_vals['where_condition']:'';
		
		//var_dump($param_array);
		$downloadCSV = (isset($formdata['downloadcsv'])) ? $formdata['downloadcsv'] : $params['downloadcsv'];
	
		// Hiren Dave on 19 Nov, 2012 - START - to check if need to print data
		$printData = (isset($formdata['printData'])) ? $formdata['printData'] : $params['printData'];
		
		// called stored procedure for counter
		$result = $this->SFA_Comman->executequery('CALL sp_get_inventory_transaction_headerdeliveryload(?,?,?,?,?,?,?,?)',$param_array,'',$downloadCSV,$printData,2,$pagingparams);
		
		$this->view->total_record	= $result[0][0]['counter'];
		$data_arr["count"] 		= $result[0][0]['counter'];
		$data_arr["data"][0] 		= $result[1];
			
		// pass the data in summary_showdatagrid() function & create a final variable for view
		$this->view->pagegridshow =  $pagingshow->summary_showdatagrid($data_arr);
		
		$this->view->addScriptPath(APPLICATION_PATH."/theme/default/datagrid/");
    }

    
    /**
    * @name       adddailysalesmanload
    * @since      1-2-2012
    * @version    Release: 1
    * @author     PM <pankit@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This action is use for deatil view of daily salesman report
    */
    public function adddeliveryAction()
	{
        //view variable declaration
        $this->view->params = $params = $this->getRequest()->getParams();
        $this->view->formdata = $formdata = $this->_request->getPost();
		
		$Menu_NameSpace = new Zend_Session_Namespace('Menu');
		$menu_array     = $Menu_NameSpace->header_menu;
		
		
		

		
		
		
		$Common_NameSpace = new Zend_Session_Namespace('Daily_Salesman_Load');
		
		$sel_date = ($formdata["txtdate"] != '') ? $formdata["txtdate"] : $Common_NameSpace->tdate;
	
		// ADD DATE VALUE IN SESSION
		if($sel_date != '') {
			$Common_NameSpace->tdate 	= $sel_date;
			$this->view->date		= $sel_date;
		}	
		
		$this->view->itemgrid   = $this->view->BaseUrl("/".$params['module']."/".$params['controller']."/deliveryloadgrid".$ex_param);
		
		/* Added By Hiren dave if load from erp is checked in control panel then data would be show only view purpose. */
		//$Settings_NameSpace = new Zend_Session_Namespace('Settings');
		
		
		
		
			$this->view->disableadd = false;
		
		
		$id = $params['id'] > 0 ? $params['id'] : '0';
		
		$combo_data		= $this->SFA_Comman->executequery('CALL sp_get_inventory_transaction_adddeliveryload(?)',$id,'');
		
		$this->view->item 			= $combo_data[0];
		$this->view->route 			= $combo_data[1];
		$this->view->salesman 		= $combo_data[2];	
		$item_array 				= $combo_data[3];
		//$this->view->batch_status 	= $combo_data[4][0]['status'];
	//	$this->view->load_method 	= $combo_data[5][0]['status'];
		$this->view->flagname 		= $combo_data[5][0]['flagname'];
		$this->view->loadtime 		= $combo_data[5][0]['loadtime'];
		
		
		
		if(count($formdata) > 0) {
			 if($formdata['hdnid'] > 0){
			SFA_Message::setMsg($this->translate->_('Update Record'));
				 }else{
			SFA_Message::setMsg($this->translate->_('New Record'));
				 }
				 $this->_helper->redirector('adddailysalesmanload', 'transaction', 'inventory');
		}
		elseif($params['id'] > 0)
		{	//var_dump($combo_data[5]);
			$data						= $combo_data[5];
			$res['txtload']				= $data[0]['deliveryno'];
			$res['ddlroute']			= $data[0]['deliveryroute'];
			$res['txtsalesman']			= $data[0]['salesmanname1'];
			$res['hdnsalesman']			= $data[0]['drivercode'];
			$res['txterprefno']			= $data[0]['referenceno'];
			$res['ddlcustomer']			= $data[0]['customercode'];
			$res['txterordno']			= $data[0]['orderno'];
			
			$this->view->date			= $data[0]['ddate'];
			$this->view->load_status	= $data[0]['load_status'];
			
			$this->view->formdata = $res;
			
			$this->view->flagname 		= $combo_data[7][0]['flagname'];
		}
    }
	
	
	
	
	
	
	/**/
    /**
    * @name       getrouteinfoAction
    * @since      16-04-2012
    * @version    Release: 1
    * @author     Hiren Dave
    * @copyright  Elan Technologies
    * @param
    *
    * This action is use for get route information
    */
    public function getrouteinfoAction()
    {
		//view variable declaration
		$params = $this->getRequest()->getParams();
		
		$param_array 	= array();
		$param_array[1]	= $params['id'];
		$param_array[2]	= $params['ddate'];
		$param_array[3] = ($params['mthd'] <> ""?$params['mthd']:0);
		
		$route_info = $this->SFA_Comman->executequery('CALL sp_get_inventory_transaction_getrouteinfo(?,?)',$param_array,'');
		
		//SFA_Comman::pre($route_info);
		if($route_info[0][0]['loadperiodnumber'] == '0') {
			$route_info[0][0]['loadperiodnumber'] = 1;
		} else {
			$route_info[0][0]['loadperiodnumber'] = ($route_info[0][0]['loadperiodnumber']+1);
		}
		//echo $route_info[0][0]['salesmancode']."#".$route_info[0][0]['salesmanname1']."#".$route_info[0][0]['vehiclenumber']."#".$load;
		//echo Zend_Json::encode($route_info);
		echo Zend_Json_Encoder::encode($route_info);
		exit;
    }
	
	    public function getdupldelidinfoAction()
    {
		//view variable declaration
		$params = $this->getRequest()->getParams();
		
		$param_array 	= array();
		$param_array[1]	= $params['id'];	
		
		$del_info = $this->SFA_Comman->executequery('CALL sp_get_inventory_transaction_getduplicatedelivery(?)',$param_array,'');
		//var_dump($del_info);
		echo $del_info[0][0]['result'];
		//echo json_encode($route_info);
		exit;
    }
	
	/*New action added by nilesh on 13Apr2016 for convert load request salesman load*/
	
	 public function convloadreqtosalesmanloadAction()
    {
		$params = $this->getRequest()->getParams();
		
		$param_array 	= array();
		$param_array[1]	= $params['id'];
		$param_array[2]	= $params['ddate'];		
		
		$load_info = $this->SFA_Comman->executequery('CALL sp_convert_loadrequesttosalesmanload(?,?)',$param_array,'');
		//echo Zend_Json::encode($load_info);
		echo Zend_Json_Encoder::encode($load_info);
		exit;
    }
	
	
	/**/
    /**
    * @name       addrouteloaddetailAction
    * @since      16-04-2012
    * @version    Release: 1
    * @author     Hiren Dave
    * @copyright  Elan Technologies
    * @param
    *
    * This action is use for save route load detail
    */
    public function addrouteloaddetailAction()
    {
		//view variable declaration
		$params = $this->getRequest()->getParams();
		
		$param_array = array();
		$param_array[1]  = $params['ddlitem'];
		$param_array[2]  = $params['hdnroute'];
		$param_array[3]  = ($params['txtcases'] <> ""?$params['txtcases']:0);
		$param_array[4]  = $params['txtupc'];
		$param_array[5]  = ($params['txtpieces'] <> ""?$params['txtpieces']:0);
		$param_array[6]  = $params['hdnsalesman'];
		$param_array[7]  = $params['txtsalesprice'];
		$param_array[8]  = $params['hdnbatchstatus'] > 0 ? $params['txtbatchno'] : 'NONE';
		$param_array[9]  = ($param_array[8] == 'NONE') ? '31-12-2099' : $params['txtexpdate'];
		$param_array[10] = $params['txtcaseprice'];
		$param_array[11] = str_replace('/','-',$params['txtdate']);		
		$param_array[12] = $params['txtload'];
		$param_array[13] = $params['hdnbatchstatus'];
		$param_array[14] = $params['txterprefno'];
		$param_array[15] = $params['hdnretprice'];
		$param_array[16] = $params['hdnretcaseprice'];
		$param_array[17] = $params['hdndepotinvstatus'];
		
		$return = $this->SFA_Comman->executequery('CALL sp_add_inventory_transaction_dailysalesmanloadgrid(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',$param_array,'');
		
		if($return[0][0]['result'] == 'Duplicate') {
			SFA_Message::setErrorMsg($this->translate->_('Item Already Added To The Load.'));
		}
		else {
			SFA_Message::setMsg($this->translate->_('New Record'));
		}
		exit;
    }
	/*New action added by nilesh for delivery load*/
	public function adddeliveryloaddetailAction()
    {
		//view variable declaration
		$params = $this->getRequest()->getParams();
		$header_det ="";		
		if($params['hdndelno']==null)
		{
		$param_array_header = array();
		$param_array_header[1]  = $params['hdnroute'];
		$param_array_header[2]  = $params['hdnsalesman'];
		$param_array_header[3]  = $params['txtload'];//delvery no
		$param_array_header[4] =  $params['txtdate'];
		$param_array_header[5]  = $params['txterprefno'];		
		$param_array_header[6] =  $params['ddlcustomer'];
		$param_array_header[7] =  $params['txterordno'];
		
		$return = $this->SFA_Comman->executequery('CALL sp_add_inventory_transaction_deliveryheader(?,?,?,?)',$param_array_header,'');
			if($return[0][0]['result'] == 'Duplicate') {
				//SFA_Message::setErrorMsg($this->translate->_('Delivery Number Is Already Assigned'));
			}
			else {
				$header_det = "added";
				SFA_Message::setMsg($this->translate->_('New Record'));
			}
		}
		//var_dump($params['edit']);
		if($params['hdndelno']!=null || $header_det=="added" || $params['edit']=="yes")
		{
		$param_array = array();
		
		$param_array[1]  = $params['txtload'];//delvery no
		$param_array[2]  = $params['ddlitem'];
		$param_array[3]  = $params['txtupc'];
		$param_array[4]  = $params['txtcaseprice'];		
		$param_array[5]  = $params['txtsalesprice'];
		$param_array[6]  = $params['txtcases'];	//Delivery Quantity	cases
		$param_array[7]  = $params['txtpieces']; //Delivery Quantity pieces//units
		$param_array[8]  = $params['freetxtcases'];	//Free cases
		$param_array[9]  = $params['freetxtpieces']; //Free pieces //units
		
		$return = $this->SFA_Comman->executequery('CALL sp_add_inventory_transaction_deliveryloadgrid(?,?,?,?,?,?,?,?,?)',$param_array,'');			
		if($return[0][0]['result'] == 'Duplicate') {
			SFA_Message::setErrorMsg($this->translate->_('Item Already Added To The Load.'));
			
		}
		else {
			if($return[1][0]['result_count'] > 0)
			{			
				echo $hdndel_no_count = $return[1][0]['result_count'];			
			}
			SFA_Message::setMsg($this->translate->_('New Record'));
		}
	}
		exit;
    }
	
	
	
	/*This action for delivery load grid*/
	 public function deliveryloadgridAction()
    {
        //view variable declaration
		$this->view->params = $params = $this->getRequest()->getParams();
		
        //variable declaration for grid title
		$item_code		= $this->translate->_('Item Code');
		$alt_code		= $this->translate->_('Alternate Code');
		$description	= $this->translate->_('Description');
        $cases		 	= $this->translate->_('Case');
        $pieces		 	= $this->translate->_('Pcs');
        $upc		 	= $this->translate->_('UPC');
        $sales_price	= $this->translate->_('Pcs Price');
		$batch_number	= $this->translate->_('Batch Number');
		$expiry_date	= $this->translate->_('Expiry Date');
		
		$deliveryno 	= $this->translate->_('Delivery no.');
		$case_price		= $this->translate->_('Case Price');
		$qcases		 	= $this->translate->_('Delivery Case');
		$qpcs		 	= $this->translate->_('Delivery Pcs');
		$fcases		 	= $this->translate->_('Free Case');
		$fpcs		 	= $this->translate->_('Free Pcs');
		
		// For Alternate Code Status.
		$cpanel						= $this->SFA_Comman->getaltcodestatus();
		$altcode_status				= $cpanel["Use Alternate Code"]['status'];
			$chk_dupl=$params["dupl"];			
			
				if($altcode_status)
				{	
					//'alternatecode, itemdescription, deliveryno,im.unitspercase, sld.caseprice,sld.salesprice,FLOOR(salesqty/im.unitspercase) cas, salesqty MOD im.unitspercase pcs,  FLOOR(focqty/im.unitspercase) freecas, focqty MOD im.unitspercase freepcs, deliveryindex as edit_del_primary_id'
				$columns_array 	= array('alternatecode','itemdescription','im.unitspercase','ROUND(sld.caseprice,'.$this->decimalplaces.') caseprice','ROUND(sld.salesprice,'.$this->decimalplaces.') salesprice','FLOOR(salesqty/im.unitspercase) cas','salesqty MOD im.unitspercase pcs','FLOOR(focqty/im.unitspercase) freecas','focqty MOD im.unitspercase freepcs','deliveryindex as edit_del_primary_id',);
				
				$columns_show  	= array($item_code,$description,$upc,$case_price,$sales_price,$qcases,$qpcs,$fcases,$fpcs);
				
				}
				else
				{
				$columns_array 	= array('itemcode','itemdescription','im.unitspercase','ROUND(sld.caseprice,'.$this->decimalplaces.')','ROUND(sld.salesprice,'.$this->decimalplaces.')','FLOOR(salesqty/im.unitspercase) cas','salesqty MOD im.unitspercase pcs','FLOOR(focqty/im.unitspercase) freecas','focqty MOD im.unitspercase freepcs','deliveryindex as edit_del_primary_id',);
				$columns_show  	= array($item_code,$description,$upc,$case_price,$sales_price,$qcases,$qpcs,$fcases,$fpcs);
				
				}
			$noeditarray = array("alternatecode","unitspercase","itemcode","itemdescription");
			if($this->css == 'ar_') {
			$columns_array[1]	= 'arbitemdescription AS itemdescription';
			}
		
			
		
		
		
		// DELETE THE RECORD
		if($params["delete"]=="yes"){
			// sp for delete outletproductitem			
			$r_delete = $this->SFA_Comman->executequery('CALL sp_delete_inventory_transaction_deliverydetailload(?)',array(1=>$params["id"]),'');
			SFA_Message::setMsg($this->translate->_('Delete Record'));
		}

		// UPDATE THE RECORD
		if($params["update"]=="yes")
		{	
			
			$updateData[1] = $params["id"];
			$updateData[2] = $params["caseprice"];
			$updateData[3] = $params["salesprice"];
			$updateData[4] = $params["cas"];//delivery cases or qty cases
			$updateData[5] = $params["pcs"];//delivery pcs or pcs cases
			$updateData[6] = $params["freecas"];
			$updateData[7] = $params["freepcs"];
			
			// call sp for edit outletproductitem
			$r_edit = $this->SFA_Comman->executequery('CALL sp_edit_inventory_transaction_deliveryloadgrid(?,?,?,?,?,?)',$updateData,'');
			
			if($r_edit[0][0]['result'] == 'Success') {
				SFA_Message::setMsg($this->translate->_('Update Record'));
			}
			else {
				SFA_Message::setErrorMsg($this->translate->_('Duplicate Record'));
			}
		}

		// ARRAY FOR GRID PAGINATION
		$pagingparams = array(
					"show_grid_heading" => false,
					"grid_heading_message" => $this->translate->_('Overview'),
					"show_records_per_page" => isset($formdata["show_records_per_page"])?$formdata["show_records_per_page"]:50,
					"show_searchbox" => false,
					"show_selectbox" => false,
					"show_deleteall" => false,
					"mastervalues" => $mastervalues,
					"noeditfields" => $noeditarray,
					"primaryid" => "loaddetailcode",
					"currentlink" => array("/".$params['module']."/".$params['controller']."/".$params['action'].$ex_param),					
					"nodata_message" => $this->translate->_('No Record(s) Found'),
					"fetch_columns_inquery" => $columns_array,
					"show_columns" => $columns_show,
					"show_columns_right_side" => array('caseprice','salesprice'),
					"show_header_right_side"=>array($this->translate->_('Case Price'),$this->translate->_('Pcs Price')),
					"additional_where" => $additional_where_condition
				    );
		
		/* Added By Hiren dave if load from erp is checked in control panel then data would be show only view purpose. */
		
		if($params['delstatus']=="1" || $params['delstatus']=="2" ){
			
		$pagingparams["show_editlink"] 		= false;
		$pagingparams["show_deletelink"] 	= false;
			
		$pagingparams["deletelink"] 		= array("/id/#pattern#/delete/yes","#pattern#");
		$pagingparams["editlink"]			= array("/id/#pattern#/edit/yes","#pattern#");
		}
		else{
		$pagingparams["show_editlink"] 		= true;
		$pagingparams["show_deletelink"] 	= true;
			
		$pagingparams["deletelink"] 		= array("/id/#pattern#/delete/yes","#pattern#");
		$pagingparams["editlink"]			= array("/id/#pattern#/edit/yes","#pattern#");
			
		}

		// WHEN GRID IS IN EDIT MODE
		if($params["edit"]=="yes"){
	
			$pagingparams["editmode"] = true;
			$pagingparams["editmodeid"] = $params["id"];
			$pagingparams["editmodevalue"] = "loaddetailcode";  // put table's prymary key here
		}
	
		//$pagingshow = new SFA_Pagingquery($pagingparams);
		$pagingshow = new SFA_Ajaxpaging($pagingparams);
		
		// call common function of grid class
		$get_return_vals = $pagingshow->commnfunc();
		
		if($params["order"] !='' || $params["page"] > 0 || $params["cancel"]=="1" || $params["delete"]=="yes" || $params['add'] =="yes" || $params["edit"]=="yes" || $params["update"]=="yes") {
		    $key	= 1;
		}
		else {
		    $key 	= $params["key"] == '' ? 0 : 1;
		}
		$duplicate = $params["dupl"];
		
		// call the stored procedure for fetch the data
		$param_array 	 = array();
		$param_array[1]  = '1';
		$param_array[2]  = $get_return_vals['order_columns_name'];
		$param_array[3]  = $get_return_vals['order_type'];
		$param_array[4]  = $get_return_vals['offset'];
		$param_array[5]  = (int)$get_return_vals['show_records_per_page'];
		$param_array[6]  = implode(", ",$pagingparams["fetch_columns_inquery"]);
		$param_array[7]  =$params["loadno"];		
		//var_dump($param_array);
		$result = $this->SFA_Comman->executequery('CALL sp_get_inventory_transaction_deliveryloadgrid(?,?,?,?,?,?,?,?,?,?,?,?)',$param_array,'');
		if($duplicate !="duplicate")
		{
			$data_arr["count"] 		= $result[0][0]['counter'];
			$data_arr["data"][0] 	= $result[1];}
		else{
			$data_arr["count"] 		= "";
			$data_arr["data"][0] 	= "";
			}
		
		if($data_arr["count"]== 0 )
		{
			/*echo "<script>
				$('#ddlroute').attr('disabled',false);
				$('#ddlroute').trigger('liszt:updated');
				</script>";*/
		}
		#echo "<pre>"; print_r($data_arr); exit;
		$this->view->pagegridshow =  $pagingshow->summary_showdatagrid($data_arr);
		$this->view->addScriptPath(APPLICATION_PATH."/theme/default/datagrid/");
	
		$this->render("ajaxgrid");
    }
	
	/**/
	/**
    * @name       addrouteloadrequestdetailAction
    * @since      16-04-2012
    * @version    Release: 1
    * @author     Hiren Dave
    * @copyright  Elan Technologies
    * @param
    *
    * This action is use for save route load detail
    */
    public function addrouteloadrequestdetailAction()
    {
		//view variable declaration
		
		$params = $this->getRequest()->getParams();
		
		$param_array = array();
		$param_array[1]  = $params['ddlitem'];
		$param_array[2]  = $params['hdnid'];
		$param_array[3]  = $params['txtcases'];
		$param_array[4]  = $params['txtupc'];
		$param_array[5]  = $params['txtpieces'];
		$param_array[6]  = $params['txtsalesprice'];
		$param_array[7]  = $params['txtcaseprice'];
		
		
		$return = $this->SFA_Comman->executequery('CALL sp_add_inventory_transaction_loadrequestgrid(?,?,?,?,?,?,?)',$param_array,'');
		
		if($return[0][0]['result'] == 'Duplicate') {
			SFA_Message::setErrorMsg($this->translate->_('Item Already Added To The Load Request.'));
		}
		else {
			SFA_Message::setMsg($this->translate->_('New Record'));
		}
		exit;
    }
    /**
    * @name       dailysalesmanloadgridAction
    * @since      21-02-2012
    * @version    Release: 1
    * @author     M@M <miral@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This action is use for add item grid
    */
	
    public function dailysalesmanloadgridAction()
    {
        //view variable declaration
		$this->view->params = $params = $this->getRequest()->getParams();

        //variable declaration for grid title
		$item_code		= $this->translate->_('Item Code');
		$alt_code		= $this->translate->_('Alternate Code');
		$description	= $this->translate->_('Description');
        $cases		 	= $this->translate->_('Case');
        $pieces		 	= $this->translate->_('Pcs');
        $upc		 	= $this->translate->_('UPC');
        $sales_price	= $this->translate->_('Pcs Price');
		$batch_number	= $this->translate->_('Batch Number');
		$expiry_date	= $this->translate->_('Expiry Date');
		
		// For Alternate Code Status.
		$cpanel						= $this->SFA_Comman->getaltcodestatus();
		$altcode_status				= $cpanel["Use Alternate Code"]['status'];
	
	
		if($params['batch_st'] > 0) {
			if($altcode_status)
			{
				$columns_array 	= array('alternatecode','itemdescription','cases','units','upc','FORMAT(salesprice,'.$this->decimalplaces.') as salesprice','batchnumber','DATE_FORMAT(expirydate,"%d-%m-%Y") as expirydate','loaddetailcode as edit_del_primary_id');
				$columns_show  	= array($alt_code,$description,$cases,$pieces,$upc,$sales_price,$batch_number,$expiry_date);				
			}
			else
			{
				$columns_array 	= array('itemcode','itemdescription','cases','units','upc','FORMAT(salesprice,'.$this->decimalplaces.') as salesprice','batchnumber','DATE_FORMAT(expirydate,"%d-%m-%Y") as expirydate','loaddetailcode as edit_del_primary_id');
				$columns_show  	= array($item_code,$description,$cases,$pieces,$upc,$sales_price,$batch_number,$expiry_date);				
			}
			$noeditarray = array("alternatecode","upc","itemcode","itemdescription","salesprice");			
		}
		else
		{
			if($altcode_status)
			{
				$columns_array 	= array('alternatecode','itemdescription','cases','units','upc','FORMAT(salesprice,'.$this->decimalplaces.') as salesprice','loaddetailcode as edit_del_primary_id',);
				$columns_show  	= array($alt_code,$description,$cases,$pieces,$upc,$sales_price);
			}
			else
			{
				$columns_array 	= array('itemcode','itemdescription','cases','units','upc','FORMAT(salesprice,'.$this->decimalplaces.') as salesprice','loaddetailcode as edit_del_primary_id',);
				$columns_show  	= array($item_code,$description,$cases,$pieces,$upc,$sales_price);			
			}
			$noeditarray = array("alternatecode","upc","itemcode","itemdescription","salesprice","batchnumber","expirydate");
		}
		
		if($this->css == 'ar_') {
			$columns_array[1]	= 'arbitemdescription AS itemdescription';
		}
		// DELETE THE RECORD
		if($params["delete"]=="yes"){
			// sp for delete outletproductitem			
			$r_delete = $this->SFA_Comman->executequery('CALL sp_delete_inventory_transaction_dailysalesmangrid(?)',array(1=>$params["id"]),'');
			SFA_Message::setMsg($this->translate->_('Delete Record'));
		}

		// UPDATE THE RECORD
		if($params["update"]=="yes")
		{
			$updateData[1] = $params["id"];
			$updateData[2] = $params["cases"];
			$updateData[3] = $params["units"];
			$updateData[4] = 'NONE';
			$updateData[5] = '31-12-2099';
			$updateData[6] = $params["batch_st"];
			$updateData[7] = $params["ddate"];
			
			if($params['batch_st'] > 0 && $params["batchnumber"] !='NONE' ) {
				$updateData[4] = $params["batchnumber"];
				$updateData[5] = $params["expirydate"];
			}
			
			// call sp for edit outletproductitem
			$r_edit = $this->SFA_Comman->executequery('CALL sp_edit_inventory_transaction_dailysalesmanloadgrid(?,?,?,?,?,?)',$updateData,'');
			
			if($r_edit[0][0]['result'] == 'Success') {
				SFA_Message::setMsg($this->translate->_('Update Record'));
			}
			else {
				SFA_Message::setErrorMsg($this->translate->_('Duplicate Record'));
			}
		}

		// IF EXTRA PARAMS ARE REQUIRED
		$ex_param = "";
		if(isset($params["rid"]) && $params["rid"]>0){
			$additional_where_condition = array();
			$ex_param = "/rid/".$params["rid"];
			$additional_where_condition[] = ' (	routecode = "'.$params['rid'].'" )';			 
		}

		// ARRAY FOR GRID PAGINATION
		$pagingparams = array(
					"show_grid_heading" => false,
					"grid_heading_message" => $this->translate->_('Overview'),
					"show_records_per_page" => isset($formdata["show_records_per_page"])?$formdata["show_records_per_page"]:50,
					"show_searchbox" => false,
					"show_selectbox" => false,
					"show_deleteall" => false,
					"mastervalues" => $mastervalues,
					"noeditfields" => $noeditarray,
					"primaryid" => "loaddetailcode",
					"currentlink" => array("/".$params['module']."/".$params['controller']."/".$params['action'].$ex_param),					
					"nodata_message" => $this->translate->_('No Record(s) Found'),
					"fetch_columns_inquery" => $columns_array,
					"show_columns" => $columns_show,
					"show_columns_right_side" => array('salesprice'),
					"show_header_right_side"=>array($this->translate->_('Sales Price')),
					"additional_where" => $additional_where_condition
				    );
		
		/* Added By Hiren dave if load from erp is checked in control panel then data would be show only view purpose. */
		$Settings_NameSpace = new Zend_Session_Namespace('Settings');
		
		$Menu_NameSpace = new Zend_Session_Namespace('Menu');
		$menu_array     = $Menu_NameSpace->header_menu;		
		
		if($Settings_NameSpace->settings['Load From ERP']['status'] == 0) {
			$pagingparams["show_deletelink"] 	= true;			
			$pagingparams["deletelink"] 		= array("/id/#pattern#/delete/yes","#pattern#");
		}
		
		if ($menu_array['Standard Depot Inventory']['status'] == 0  && $menu_array['Advanced Depot Inventory']['status'] == 0) {
			$pagingparams["show_editlink"] 		= true;
			$pagingparams["show_deletelink"] 	= true;
			
			$pagingparams["deletelink"] 		= array("/id/#pattern#/delete/yes","#pattern#");
			$pagingparams["editlink"]			= array("/id/#pattern#/edit/yes","#pattern#");
		}

		// WHEN GRID IS IN EDIT MODE
		if($params["edit"]=="yes"){
	
			$pagingparams["editmode"] = true;
			$pagingparams["editmodeid"] = $params["id"];
			$pagingparams["editmodevalue"] = "loaddetailcode";  // put table's prymary key here
		}
	
		//$pagingshow = new SFA_Pagingquery($pagingparams);
		$pagingshow = new SFA_Ajaxpaging($pagingparams);
		
		// call common function of grid class
		$get_return_vals = $pagingshow->commnfunc();
		
		if($params["order"] !='' || $params["page"] > 0 || $params["cancel"]=="1" || $params["delete"]=="yes" || $params['add'] =="yes" || $params["edit"]=="yes" || $params["update"]=="yes") {
		    $key	= 1;
		}
		else {
		    $key 	= $params["key"] == '' ? 0 : 1;
		}
		
		// call the stored procedure for fetch the data
		$param_array 	 = array();
		$param_array[1]  = '1';
		$param_array[2]  = $get_return_vals['order_columns_name'];
		$param_array[3]  = $get_return_vals['order_type'];
		$param_array[4]  = $get_return_vals['offset'];
		$param_array[5]  = (int)$get_return_vals['show_records_per_page'];
		$param_array[6]  = implode(", ",$pagingparams["fetch_columns_inquery"]);
		$param_array[7]  = strlen($get_return_vals['where_condition'])>0?$get_return_vals['where_condition']:'';
		$param_array[8]  = $params["ddate"];
		$param_array[9]  = $params["loadno"];
		$param_array[10] = $key;
		$param_array[11] = $params["rid"];
		$param_array[12] = ($params['rtstatus'] <>"" && $params['rtstatus'] <>"undefined")? $params['rtstatus']:0;
		
		$result = $this->SFA_Comman->executequery('CALL sp_get_inventory_transaction_dailysalesmanloadgrid(?,?,?,?,?,?,?,?,?,?,?,?)',$param_array,'');
		
		$data_arr["count"] 		= $result[0][0]['counter'];
		$data_arr["data"][0] 	= $result[1];
		if($data_arr["count"]== 0 )
		{
			echo "<script>
				$('#ddlroute').attr('disabled',false);
				$('#ddlroute').trigger('liszt:updated');
				</script>";
		}
		#echo "<pre>"; print_r($data_arr); exit;
		$this->view->pagegridshow =  $pagingshow->summary_showdatagrid($data_arr);
		$this->view->addScriptPath(APPLICATION_PATH."/theme/default/datagrid/");
	
		$this->render("ajaxgrid");
    }
	/**
    * @name       viewinventorystock
    * @since      30-01-2012
    * @version    Release: 1
    * @author     HD <hiren.d@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This action is use for the display stock information on basis of the route selection and we can also select record to save data.
    */
    public function viewinventorystockAction()
    {
		$this->view->params = $params = $this->getRequest()->getParams();
		
		$this->_helper->layout->setLayout('popup');		
		
		$this->view->title	= $this->translate->_('Batch Detail');		
		
		$columns_show = array($this->translate->_('ItemCode'),$this->translate->_('Description'),$this->translate->_('UPC'),
							  $this->translate->_('Req. Case'),$this->translate->_('Req. Pcs'),
							  $this->translate->_('Allow. Case'),$this->translate->_('Allow. Pcs'),
							  $this->translate->_('Batch'),$this->translate->_('Expiry Date'));
		
		$additional_where_condition 	= array();
		$additional_where_condition[] 	= ' ( routecode = "'.$params['routecode'].'" ) ';
		
		if($params['mthd'] == 4) {
			$columns_array = array('sld.itemcode','im.itemshortdescription','im.unitspercase','sld.cases AS reqcases','sld.units  AS reqpieces',
								   'IFNULL(FLOOR(ds.quantity/im.unitspercase),0) AS allowcases','IFNULL((ds.quantity%im.unitspercase),0) AS allowpieces',
								   'ds.batchno AS batchno','DATE_FORMAT(ds.expirydate,"%d-%m-%Y") AS expirydate','sld.loaddetailcode AS edit_del_primary_id');
			
			$primarykey = 'sld.loaddetailcode';
			$additional_where_condition[] 	= ' ( loadperiodnumber = "'.$params['loadno'].'" ) ';
			
		} elseif($params['mthd'] == 2) {
			$columns_array = array('itd.itemcode','im.itemshortdescription','im.unitspercase',
								   'IFNULL(FLOOR(sum(itd.quantity)/im.unitspercase),0) AS reqcases','IFNULL((sum(itd.quantity)%im.unitspercase),0) AS reqpieces',
								   'IFNULL(FLOOR(ds.quantity/im.unitspercase),0) AS allowcases','IFNULL((ds.quantity%im.unitspercase),0) AS allowpieces',
								   'ds.batchno AS batchno','DATE_FORMAT(ds.expirydate,"%d-%m-%Y") AS expirydate','itd.primary_key AS edit_del_primary_id');
			
			$primarykey = 'itd.primary_key';
		} elseif($params['mthd'] == 3) {	
			$columns_array = array('sod.itemcode','im.itemshortdescription','im.unitspercase',
								   'IFNULL(FLOOR(sod.salesqty/im.unitspercase),0) AS reqcases','IFNULL((sod.salesqty%im.unitspercase),0) AS reqpieces',
								   'IFNULL(FLOOR(ds.quantity/im.unitspercase),0) AS allowcases','IFNULL((ds.quantity%im.unitspercase),0) AS allowpieces',
								   'ds.batchno AS batchno','DATE_FORMAT(ds.expirydate,"%d-%m-%Y") AS expirydate','sod.primary_key AS edit_del_primary_id');
			
			$primarykey = 'sod.primary_key';		
		} elseif($params['mthd'] == 5) {
			$columns_array = array('ssi.itemcode','im.itemshortdescription','im.unitspercase',
								   'IFNULL(FLOOR(ssi.currentvisitsales/im.unitspercase),0) AS reqcases','IFNULL((ssi.currentvisitsales%im.unitspercase),0) AS reqpieces',
								   'IFNULL(FLOOR(ds.quantity/im.unitspercase),0) AS allowcases','IFNULL((ds.quantity%im.unitspercase),0) AS allowpieces',
								   'ds.batchno AS batchno','DATE_FORMAT(ds.expirydate,"%d-%m-%Y") AS expirydate','ssi.primary_key AS edit_del_primary_id');
			
			$primarykey = 'ssi.primary_key';
		}
		
		
		
		$Menu_NameSpace = new Zend_Session_Namespace('Menu');
		$menu_array     = $Menu_NameSpace->header_menu;		
		
		if($menu_array['Standard Depot Inventory']['status'] == 1 ) {
			$tablename 						= 'depotstock';
			$additional_where_condition[] 	= '(IFNULL(FLOOR(ds.quantity/im.unitspercase),0) > 0 OR IFNULL((ds.quantity%im.unitspercase),0) > 0)';
			$groupby 						= 'group by ds.batchno,ds.expirydate';
		} elseif($menu_array['Advanced Depot Inventory']['status'] == 1) {
			$columns_array[5] 				= 'FLOOR(ds.avlquantity/im.unitspercase) AS allowcases';
			$columns_array[6] 				= 'IFNULL((ds.avlquantity%im.unitspercase),0) AS allowpieces';
			$tablename 						= 'tbldepotstock';
			$additional_where_condition[] 	= '(IFNULL(FLOOR(ds.quantity/im.unitspercase),0) > 0 OR IFNULL((ds.quantity%im.unitspercase),0) > 0)';
			$groupby 						= 'group by ds.batchno,ds.expirydate';
		} elseif($menu_array['Standard Depot Inventory']['status'] == 0 && $menu_array['Advanced Depot Inventory']['status'] == 0) {
			$columns_array[5] 	= '0 AS allowcases';
			$columns_array[6] 	= '0 AS allowpieces';
			if($params['mthd'] != 4) {
				$columns_array[7] 	= 'NULL AS batchno';
				$columns_array[8] 	= 'NULL AS expirydate';
			} else {
				$columns_array[7] 	= 'sld.batchnumber AS batchno';
				$columns_array[8] 	= 'DATE_FORMAT(sld.expirydate,"%d-%m-%Y") AS expirydate';
			}
			
			$groupby = '';
			if($params['mthd'] == 4) {
				$groupby = 'group by sld.itemcode';
				
			} elseif($params['mthd'] == 2) {
				$groupby = 'group by itd.itemcode';
				
			} elseif($params['mthd'] == 3) {	
				$groupby = 'group by sod.itemcode';
				
			} elseif($params['mthd'] == 5) {
				$groupby = 'group by ssi.itemcode';
			}
			
			$tablename = '';
		}
		
		if($params['nodata'] == 1) {
			reset($additional_where_condition,$columns_array,$columns_show);
			$columns_array 		= array();
			if($params['mthd'] == 4) {
				$columns_array[0]	= 'sld.itemcode';
			} elseif($params['mthd'] == 2) {
				$columns_array[0]	= 'itd.itemcode';
			} elseif($params['mthd'] == 3) {
				$columns_array[0]	= 'sod.itemcode';
			} elseif($params['mthd'] == 5) {
				$columns_array[0]	= 'ssi.itemcode';
			}
			$columns_array[1]	= 'itemshortdescription';
			$columns_array[2]	= 'im.unitspercase';
			
			$columns_show = array($this->translate->_('ItemCode'),$this->translate->_('Description'),$this->translate->_('UPC'));
			
			$additional_where_condition 	= array();
			$additional_where_condition[] 	= ' ( routecode = "'.$params['routecode'].'" ) ';
			$additional_where_condition[] 	= ' ( ds.batchno IS NULL ) ';
			$additional_where_condition[] 	= ' ( ds.expirydate IS NULL ) ';
		}
		
		$cpanel				= $this->SFA_Comman->getaltcodestatus();
		$altcode_status		= $cpanel["Use Alternate Code"]['status'];
		
		if($altcode_status) {
			$columns_array[0] = 'im.alternatecode';
		}
		
		//echo 'asdasd';exit;
	
		// ARRAY FOR GRID PAGINATION
		$pagingparams = array(
			"show_grid_heading" => true,
			"grid_heading_message" => $this->translate->_('Overview'),			
			"show_records_per_page" => isset($formdata["show_records_per_page"])?$formdata["show_records_per_page"]:100000,
			"show_searchbox" => false,
			"searchbox_value" => isset($formdata["grid_search_text"])?$formdata["grid_search_text"]:"",
			"show_selectbox" => true,            
			"show_deletelink" => false,
			"inventory_method" => $params['mthd'],
			"no_search_fields" => $not_in_search,
			"selected_list" => $checked,			
			"show_deleteall" => false,
			"primaryid" => $primarykey,
			"nodata_message" => $this->translate->_('No Record(s) Found'),
			"fetch_columns_inquery" => $columns_array,
			"show_columns" => $columns_show,
			"additional_where" => $additional_where_condition,
			"show_top_columns" => false,
			"show_top_columns_value" => array(array("3",""),array("2","Requested Qty"),array("2","Allowed Qty"),array("2","")),
		);
		$this->view->dontshow 	= 2;
		$this->view->inventory_method = $params['mthd'];
		$this->view->tablename 	= $tablename;
		
		if($params['nodata'] == 1) {
			$pagingparams['show_selectbox'] = false;
			$this->view->dontshow = 1;
		}
		if($tablename == '' && $params['mthd'] != 4) {
			$pagingparams['show_extratextbox'] = true;
			$pagingparams['inventory_setting'] = 1;
		}
		
		
		// create grid class object
		$pagingshow = new SFA_Paging($pagingparams);
		
		// call common function of grid class
		$get_return_vals = $pagingshow->commnfunc();
		
		// call the stored procedure for fetch the data
		$param_array	 = array();
		$param_array[1]  = '1';
		$param_array[2]  = '';
		$param_array[3]  = $get_return_vals['order_columns_name'];
		$param_array[4]  = $get_return_vals['order_type'];
		$param_array[5]  = $get_return_vals['offset'];
		$param_array[6]  = (int)$get_return_vals['show_records_per_page'];
		$param_array[7]  = implode(", ",$pagingparams["fetch_columns_inquery"]);
		$param_array[8]  = strlen($get_return_vals['where_condition'])>0?$get_return_vals['where_condition']:'';
		$param_array[9]  = $tablename;
		$param_array[10] = $params['routecode'];
		$param_array[11] = $params['nodata'] == 1 ? '' : $groupby;
		$param_array[12] = $params['expdate'];
		$param_new_array	 = array();
		$param_new_array[1] = $params['routecode'];
		$param_new_array[2] = $params['expdate'];
		
		$result = $this->SFA_Comman->executequery('CALL sp_convert_loadrequesttosalesmanload(?,?)',$param_new_array,'');
		
		/*if($params['mthd'] == 4) {			
			$param_array[13] = $params['loadno'];
			$result = $this->SFA_Comman->executequery('CALL sp_get_inventory_transaction_dailysalesmanload_previousday(?,?,?,?,?,?,?,?,?,?,?)',$param_array,'');
		} elseif($params['mthd'] == 2) {
			$result = $this->SFA_Comman->executequery('CALL sp_get_inventory_transaction_dailysalesmanload_requesttoload(?,?,?,?,?,?,?,?)',$param_array,'');
		} elseif($params['mthd'] == 3) {
			$result = $this->SFA_Comman->executequery('CALL sp_get_inventory_transaction_dailysalesmanload_ordertoload(?,?,?,?,?,?,?,?)',$param_array,'');
		} elseif($params['mthd'] == 5) {
			$result = $this->SFA_Comman->executequery('CALL sp_get_inventory_transaction_dailysalesmanload_suggestedsales(?,?,?,?,?,?,?,?)',$param_array,'');
		}*/
		
		
		$this->view->datacounter= $result[0][0]['counter'];
		$data_arr["count"] 		= $result[0][0]['counter'];
		$data_arr["data"][0]	= $result[1];
		
		// pass the data in summary_showdatagrid() function & create a final variable for view
		$this->view->pagegridshow =  $pagingshow->summary_showdatagrid($data_arr);
		
		$this->view->addScriptPath(APPLICATION_PATH."/theme/default/datagrid/");
	}
	/**
    * @name       addinventorystockAction
    * @since      30-01-2012
    * @version    Release: 1
    * @author     HD <hiren.d@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This action is use for the addstock information
    */
    public function addinventorystockAction()
    {
		$params = $this->getRequest()->getParams();
		
		$Menu_NameSpace = new Zend_Session_Namespace('Menu');
		$menu_array     = $Menu_NameSpace->header_menu;
		
		$tablename 		= '';
		if($menu_array['Standard Depot Inventory']['status'] == 1 ) {
			$tablename 	= 'depotstock';
		} elseif($menu_array['Advanced Depot Inventory']['status'] == 1) {
			$tablename 	= 'tbldepotstock';
		}
		
		$Common_NameSpace 	= new Zend_Session_Namespace('Daily_Salesman_Load');
		
		$param_array 	= array();
		$param_array[1]	= substr($params['depotinvkey'],0,-1);
		$param_array[2]	= $tablename;
		$param_array[3]	= (!$Common_NameSpace->tdate) ? date('d-m-Y') : $Common_NameSpace->tdate;
		$param_array[4] = $params['loadno'];
		$param_array[5]	= $params['routecode'];
		
		if($tablename == '') {
			$param_array[6]		= substr($params['allowcases'],0,-1);
			$param_array[7]		= substr($params['allowpieces'],0,-1);
			$param_array[8]		= substr($params['batchno'],0,-1);
			$param_array[9]		= substr($params['expirydate'],0,-1);
			$param_array[10]	= substr_count(($params['expirydate']),'_');
		} else {
			$param_array[6]		= '';
			$param_array[7]		= '';
			$param_array[8]		= '';
			$param_array[9]		= '';
			$param_array[10]	= '';
		}
		
		if($params['mthd'] == 2) {
			$param_array[3] = $params['loadno'];
			$result 		= $this->SFA_Comman->executequery('CALL sp_add_inventory_transaction_dailysalesmanload_requesttoload(?,?,?,?)',$param_array,'');
		} elseif($params['mthd'] == 4) {
			$result 		= $this->SFA_Comman->executequery('CALL sp_add_inventory_transaction_dailysalesmanload_previousday(?,?,?,?)',$param_array,'');
		} elseif($params['mthd'] == 3) {
			$param_array[3] = $params['loadno'];
			$result 		= $this->SFA_Comman->executequery('CALL sp_add_inventory_transaction_dailysalesmanload_ordertoload(?,?,?,?)',$param_array,'');
		} elseif($params['mthd'] == 5) {
			$param_array[3] = $params['loadno'];
			$result 		= $this->SFA_Comman->executequery('CALL sp_add_inventory_transaction_dailysalesmanload_suggestedsales(?,?,?,?)',$param_array,'');
		}
		//SFA_Comman::pre($result);
		if($result[0][0]['lastid'] > 0) {
			echo 1;
		} else {
			echo 0;
		}
		exit;
	}
	/**
    * @name       viewbatchinfoAction
    * @since      30-01-2012
    * @version    Release: 1
    * @author     PM <pankit@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This action is use for the display batchinfo
    */
    public function viewbatchinfoAction()
    {
		$params = $this->getRequest()->getParams();
		
		$this->_helper->layout->setLayout('popup');		
		
		$this->view->title	= $this->translate->_('Batch Detail');
		
		
		$columns_show = array($this->translate->_('Batch'),$this->translate->_('Expiry Date'),$this->translate->_('Case'),$this->translate->_('Pcs'));
		
		$tablename = '';
		$additional_where_condition 	= array();
		
		if($params['invtype'] == 14) {
			$columns_array 	= array('ds.batchno','DATE_FORMAT(ds.expirydate,"%d-%m-%Y") AS expirydate','IFNULL(FLOOR(ds.quantity/unitspercase),0) AS cases','IFNULL((ds.quantity%unitspercase),0) AS pieces','depotinventorykey as edit_del_primary_id');
			$tablename = 'depotstock';
			$additional_where_condition[] 	= ' ds.quantity <> 0 ';
		} elseif($params['invtype'] == 64) {
			$columns_array 	= array('batchno','DATE_FORMAT(expirydate,"%d-%m-%Y") AS expirydate','IFNULL(FLOOR(ds.avlquantity/unitspercase),0) AS cases','IFNULL((ds.avlquantity%unitspercase),0) AS pieces','depotinventorykey as edit_del_primary_id');
			$additional_where_condition[] 	= ' ds.avlquantity <> 0 ';
			$tablename = 'tbldepotstock';
		}
		
		
		if($params['itemid'])
			$additional_where_condition[] 	= ' ( itemcode 		= "'.$params['itemid'].'" ) ';		
		if($params['routecode'] && $params['add'] != 'yes')
			$additional_where_condition[] 	= ' ( routecode 	= "'.$params['routecode'].'" ) ';
		if($params['ddate'])
			$additional_where_condition[] 	= ' ( ddate 		= "'.$params['ddate'].'" ) ';
		if($params['depotcode']) {
			$additional_where_condition[] 	= ' ( depotcode 	= "'.$params['depotcode'].'" )';
		} else {
			$additional_where_condition[] 	= ' ( depotcode 	= " get_depotcode_routecode('.$params['routecode'].')" )';
		}
		
	
		// ARRAY FOR GRID PAGINATION
		$pagingparams = array(
			"show_grid_heading" => true,
			"grid_heading_message" => $this->translate->_('Overview'),			
			"show_records_per_page" => isset($formdata["show_records_per_page"])?$formdata["show_records_per_page"]:10,
			"show_searchbox" => false,
			"searchbox_value" => isset($formdata["grid_search_text"])?$formdata["grid_search_text"]:"",
			"show_selectbox" => true,
			"show_datasorting" => false,
			"show_deletelink" => false,
			"no_search_fields" => $not_in_search,
			"selected_list" => $checked,
			"show_deleteall" => false,
			"primaryid" => "depotinventorykey",
			"nodata_message" => $this->translate->_('No Record(s) Found'),
			"fetch_columns_inquery" => $columns_array,
			"show_columns" => $columns_show,			
			"additional_where" => $additional_where_condition,
		);
		
		// create grid class object
		$pagingshow = new SFA_Paging($pagingparams);
		
		// call common function of grid class
		$get_return_vals = $pagingshow->commnfunc();
		
		// call the stored procedure for fetch the data
		$param_array	= array();
		$param_array[1] = '1';
		$param_array[2] = '';
		$param_array[3] = $get_return_vals['order_columns_name'];
		$param_array[4] = $get_return_vals['order_type'];
		$param_array[5] = $get_return_vals['offset'];
		$param_array[6] = (int)$get_return_vals['show_records_per_page'];
		$param_array[7] = implode(", ",$pagingparams["fetch_columns_inquery"]);
		$param_array[8] = strlen($get_return_vals['where_condition'])>0?$get_return_vals['where_condition']:'';
		$param_array[9] = $tablename;
	
		
		if($params['invtype'] > 0) {
			$result = $this->SFA_Comman->executequery('CALL sp_get_inventory_transaction_dailysalesmanload_batchinfo(?,?,?,?,?,?,?,?,?)',$param_array,'');
		}
		
		$this->view->datacounter= $result[0][0]['counter'];
		$data_arr["count"] 		= $result[0][0]['counter'];
		$data_arr["data"][0]	= $result[1];
		
		// pass the data in summary_showdatagrid() function & create a final variable for view
		$this->view->pagegridshow =  $pagingshow->summary_showdatagrid($data_arr);
		
		$this->view->addScriptPath(APPLICATION_PATH."/theme/default/datagrid/");
	}
    /**
    * @name       endofday
    * @since      1-2-2012
    * @version    Release: 1
    * @author     PM <pankit@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This action is use for the start of the day report
    */
    public function endofdayAction(){

    }

    /**
    * @name       routeitemgroupAction
    * @since      6-2-2012
    * @version    Release: 1
    * @author     PM <pankit@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This action is use for give list of route item group
    */
    public function routeitemgroupAction(){

	$this->view->params = $params = $this->getRequest()->getParams();
	$this->view->formdata = $formdata = $this->_request->getPost();
	
	if($formdata["hdDelete"]==1)
	{
	    $ids = implode(',',$formdata['chk']);
	    $param_array 	= array();
	    $param_array[1]	= $ids;
	    $param_array[2]	= $this->currentUser->username;
	    
	    $result 	= $this->SFA_Comman->executequery('CALL sp_delete_inventory_transaction_routeitemgroup(?,?)',$param_array,'');
	    
	    if($result[0][0]['deleted_id'] =='')
	    {
		$ids	= explode(',',$ids);
		$checked 	= $ids;
		
		SFA_Message::setErrorMsg($this->translate->_('Record Already In use'));
	    }
	    else
	    {
		$deleted_id = explode(',',$result[0][0]['deleted_id']);
		$ids		= explode(',',$ids);
		$checked 	= array_diff($ids,$deleted_id);
		
		if(count($ids) != count($deleted_id)){
		    SFA_Message::setErrorMsg($this->translate->_('Record Already In use'));
		}
		
		SFA_Message::setMsg($this->translate->_('Delete Record'));
	    }
	}
	
	
	$cols_array = array('routeitemgrpcode','description','transferstatus');
	$columns_show =  array($this->translate->_('Code'),$this->translate->_('Description'),$this->translate->_('Status'));
	  
	// prepare the configuration for grid
	$pagingparams = array(
				"show_grid_heading" => true,
				"grid_heading_message" => $this->translate->_('Overview'),
				"pagename" => $this->translate->_('Route Item Group'),
				"show_records_per_page" => isset($formdata["show_records_per_page"])?$formdata["show_records_per_page"]:10,
				"show_searchbox" => true,
				"searchbox_value" => isset($formdata["grid_search_text"])?$formdata["grid_search_text"]:"",
				"show_selectbox" => true,
				"selected_list" => $checked,
				"show_editlink" => true,
				"show_deletelink" => false,
				"show_deleteall" => false,
				"primaryid" => "routeitemgrpcode",
				"status_cols" => array(
				array(
					"cols_name" => "transferstatus",
					"status_change" => array("0"=>"Inactive","1"=>"Active")
				    )
				),
				"editlink" => array("/inventory/transaction/addrouteitemgroup/id/#pattern#/edit/yes/","#pattern#"),
				"nodata_message" => $this->translate->_('No Record(s) Found'),
				"fetch_columns_inquery" => $cols_array,
				"show_columns" => $columns_show			 
			    );
		
    if(!$this->checkaccess("update"))
    {
        $pagingparams["show_editlink"] = false;
    }
	// create grid class object
	$pagingshow = new SFA_Paging($pagingparams);
	
	// call common function of grid class
	$get_return_vals = $pagingshow->commnfunc();
	
	//print_r($get_return_vals['where_condition']);
	
	// call the stored procedure for fetch the data
	$param_array	= array();
	$param_array[1] = '1';
	$param_array[2] = '';
	$param_array[3] = $get_return_vals['order_columns_name'];
	$param_array[4] = $get_return_vals['order_type'];
	$param_array[5] = $get_return_vals['offset'];
	$param_array[6] = (int)$get_return_vals['show_records_per_page'];
	$param_array[7] = implode(", ",$pagingparams["fetch_columns_inquery"]);
	$param_array[8] = strlen($get_return_vals['where_condition'])>0?$get_return_vals['where_condition']:'';
	
	$downloadCSV = (isset($formdata['downloadcsv'])) ? $formdata['downloadcsv'] : $params['downloadcsv'];
    
    // Hiren Dave on 19 Nov, 2012 - START - to check if need to print data
	$printData = (isset($formdata['printData'])) ? $formdata['printData'] : $params['printData'];
	// called stored procedure for counter
	$result = $this->SFA_Comman->executequery('CALL sp_get_inventory_transaction_routeitemgroup(?,?,?,?,?,?,?,?)',$param_array,'',$downloadCSV,$printData,2,$pagingparams);
	
	$data_arr["count"] 	= $result[0][0]['counter'];
	$data_arr["data"][0] 	= $result[1];
	
	// pass the data in summary_showdatagrid() function & create a final variable for view
	$this->view->pagegridshow =  $pagingshow->summary_showdatagrid($data_arr);
	
	$this->view->addScriptPath(APPLICATION_PATH."/theme/default/datagrid/");

    }

   /**
    * @name       addrouteitemgroupAction
    * @since      6-2-2012
    * @version    Release: 1
    * @author     PM <pankit@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This action is use for add route group item
    */
    public function  addrouteitemgroupAction()
    {        
		$this->view->params = $params = $this->getRequest()->getParams();
		$this->view->formdata = $formdata = $this->_request->getPost();
		
		$selectedval = substr($formdata['hdnselecteditem'], 0, -1);
		$oldvalue = explode(',',$formdata['hdnoldselecteditem']);
		$newvalue = explode(',',$selectedval);		
		//$difference = array_diff(array_merge($oldvalue, $newvalue), array_intersect($oldvalue, $newvalue));
		$difference = array_diff($oldvalue, $newvalue);
		$diff_cnt 	= count($difference);
		$difference = implode(',',$difference);
		
		if($formdata['txtdescription'] !='')
		{
			$param_array 	= array();
			$param_array[1] = $formdata['txtdescription'];
			$param_array[2] = $formdata['ddlitem_group'];
			$param_array[3] = $formdata['ddlstatus'];
			$param_array[4]	= $selectedval;	//selected question
			$param_array[5]	= $formdata['hdncount_items'];			//get count for add record in child table
			$param_array[6]	= $difference;
			$param_array[7]	= $diff_cnt;
			$param_array[8]	= $this->currentUser->username;			
			
			if($formdata['hdnid'] > 0)
			{
				$param_array[9] = $formdata['hdnid'];
				$result = $this->SFA_Comman->executequery("CALL sp_edit_inventory_transaction_addrouteitemgroup(?,?,?,?,?,?,?,?,?)",$param_array,'');
				SFA_Message::setMsg($this->translate->_('Update Record'));
			}
			else
			{
				$result = $this->SFA_Comman->executequery("CALL sp_add_inventory_transaction_addrouteitemgroup(?,?,?,?,?,?,?,?)",$param_array,'');
				SFA_Message::setMsg($this->translate->_('New Record'));
			}
			$this->_helper->redirector('routeitemgroup', 'transaction', 'inventory');
		}
		elseif($params['id'] > 0)
		{
			$result = $this->SFA_Comman->executequery("CALL sp_get_inventory_transaction_addrouteitemgroup('?')",$params['id'],'');
			$this->view->items_data 		= $result[0];
			$this->view->item_group_data	= $result[1];
			$this->view->formdata 		= $result[2][0];
			$this->view->selected_items		= $result[3];
		}
		else
		{
			$result = $this->SFA_Comman->executequery("CALL sp_get_inventory_transaction_addrouteitemgroup('?')",'0','');
			$this->view->items_data 			= $result[0];
			$this->view->item_group_data 		= $result[1];
			$this->view->formdata['routeitemgrpcode'] 	= ($result[2][0]['Auto_increment'] == '' ) ? '1' : $result[2][0]['Auto_increment'];
		}
    }
	function array_diff_($old_array,$new_array) {
		foreach($new_array as $i=>$l) {
			if($old_array[$i] != $l) {
				$r[$i]=$l;
			}
		}
		//adding deleted values
		foreach($old_array as $i=>$l){
			if(!$new_array[$i]){
				$r[$i]="";
			}
		}
        return $r;
	}
	/**
    * @name       getitemfromitemgrpAction
    * @since      10-07-2012
    * @version    Release: 1
    * @author     HD <hiren.d@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This action is use for the get item list from selected criteria.
    */
    public function getitemfromitemgrpAction()
    {
		//view variable declaration
		$params = $this->getRequest()->getParams();
		$param_array[1] = $params['item_grp'];
		$param_array[2] = $params['route_grp'];
		
		$result = $this->SFA_Comman->executequery('CALL sp_get_items_itemgroupcode(?,?)',$param_array,'');
		echo Zend_Json_Encoder::encode($result);
		exit;
    }

    /**
    * @name       addrouteitemgroupAction
    * @since      6-2-2012
    * @version    Release: 1
    * @author     PM <pankit@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This action is use for toa add additional item barcode
    */
    public function additionalitembarcodeAction(){
		//view variable declaration
		$this->view->params = $params = $this->getRequest()->getParams();
		$this->view->formdata = $formdata = $this->_request->getPost();
		
		if(count($formdata) > 0) {
			
			$param_array 	= array();
			$param_array[1]	= $formdata['ddlitemcode'];
			$param_array[2]	= $formdata['txtbarcode3'];
			$param_array[3]	= $formdata['txtbarcode4'];
			$param_array[4]	= $formdata['txtbarcode5'];
			$param_array[5]	= $formdata['txtbarcode6'];
			$param_array[6]	= $formdata['txtbarcode7'];
			$param_array[7]	= $formdata['txtbarcode8'];
			$param_array[8]	= $formdata['txtbarcode9'];
			$param_array[9]	= $formdata['txtbarcode10'];
			$param_array[10]	= $this->currentUser->username;
			
			if($formdata['hdnid'] > 0)
			{
				$last_id = $this->SFA_Comman->executequery('CALL sp_edit_inventory_target_additionalbarcode(?,?,?,?,?,?,?,?,?)',$param_array,'');
				
				if($last_id[0][0]['last_id'] > 0)
					SFA_Message::setMsg($this->translate->_('Update Record'));
				else {
					$res['itemcode'] 	= $formdata['ddlitemcode'];
					$res['barcode3'] 	= $formdata['txtbarcode3'];
					$res['barcode4'] 	= $formdata['txtbarcode4'];
					$res['barcode5'] 	= $formdata['txtbarcode5'];
					$res['barcode6'] 	= $formdata['txtbarcode6'];
					$res['barcode7'] 	= $formdata['txtbarcode7'];
					$res['barcode8'] 	= $formdata['txtbarcode8'];
					$res['barcode9'] 	= $formdata['txtbarcode9'];
					$res['barcode10']	= $formdata['txtbarcode10'];
					$this->view->formdata = $res;
					SFA_Message::setErrorMsg($this->translate->_('Duplicate Record'));
				}
			}
			else
			{
				$last_id = $this->SFA_Comman->executequery('CALL sp_add_inventory_target_additionalbarcode(?,?,?,?,?,?,?,?,?)',$param_array,'');
				
				if($last_id[0][0]['last_id'] > 0)
					SFA_Message::setMsg($this->translate->_('New Record'));
				else{
					$res['itemcode'] 	= $formdata['ddlitemcode'];
					$res['barcode3'] 	= $formdata['txtbarcode3'];
					$res['barcode4'] 	= $formdata['txtbarcode4'];
					$res['barcode5'] 	= $formdata['txtbarcode5'];
					$res['barcode6'] 	= $formdata['txtbarcode6'];
					$res['barcode7'] 	= $formdata['txtbarcode7'];
					$res['barcode8'] 	= $formdata['txtbarcode8'];
					$res['barcode9'] 	= $formdata['txtbarcode9'];
					$res['barcode10']	= $formdata['txtbarcode10'];
					$this->view->formdata = $res;
					SFA_Message::setErrorMsg($this->translate->_('Duplicate Record'));		    
				}
			}
			$this->view->itemcodes = $last_id[1];
		}
		elseif($params['id'] > 0)
		{
			$result = $this->SFA_Comman->executequery('CALL sp_get_inventory_transaction_additionalbarcodes(?)',$params['id'],'');			
			if(count($result[0][0]) > 0)
			{
				$this->view->formdata 	= $result[0][0];
				$this->view->itemcodes 	= $result[1];
			}
			else
			{
				SFA_Message::setErrorMsg($this->translate->_('No Record(s) Found'));
				$this->_helper->redirector('additionalitembarcode', 'transaction', 'inventory');
			}
		}
		else
		{
			$result = $this->SFA_Comman->executequery('CALL sp_get_inventory_transaction_additionalbarcodes(?)','0','');
			$this->view->itemcodes = $result[1];	    
		}
    }

    /**
    * @name       depotdamageexpiry
    * @since      7-2-2012
    * @version    Release: 1
    * @author     PM <pankit@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This action is use for the depot damage expiry
    */
    public function depotdamageexpiryAction() {
        //view variable declaration
        $this->view->formdata = $formdata = $this->_request->getPost();

        if($formdata["hdDelete"]==1)
		{
			$ids = implode(',',$formdata['chk']);
			$param_array 	= array();
			$param_array[1]	= $ids;
			$param_array[2]	= $this->currentUser->username;
			
			$result 	= $this->SFA_Comman->executequery('CALL sp_delete_inventory_transaction_depotdamageexpiry(?,?)',$param_array,'');
			
			if($result[0][0]['deleted_id'] =='')
			{
				$ids		= explode(',',$ids);
				$checked 	= $ids;
				
				SFA_Message::setErrorMsg($this->translate->_('Record Already In use'));
			}
			else
			{
				$deleted_id 	= explode(',',$result[0][0]['deleted_id']);
				$ids		= explode(',',$ids);
				$checked 	= array_diff($ids,$deleted_id);
				
				if(count($ids) != count($deleted_id)){
					SFA_Message::setErrorMsg($this->translate->_('Record Already In use'));
				}
				
				SFA_Message::setMsg($this->translate->_('Delete Record'));
			}
		}
		$Common_NameSpace = new Zend_Session_Namespace('DepotDamageExpiry');
		if($formdata['btnreset'] == 'RESET')
		{
			$formdata["txtdate"] 	= '';
			$Common_NameSpace->tdate	= '';
		}
		$last_url = htmlspecialchars($_SERVER['HTTP_REFERER']);
		if(strpos($last_url,'adddepotdamageexpiry'))
		{
			$sel_date = ($formdata["txtdate"] != '') ? $formdata["txtdate"] : $Common_NameSpace->tdate;
		}
		else
		{
			$sel_date = ($formdata["txtdate"] != '') ? $formdata["txtdate"] : date('d-m-Y');
		}	
		
		// ADD DATE VALUE IN SESSION
		if($sel_date != '') {
			$Common_NameSpace->tdate 	= $sel_date;
			$this->view->date			= $sel_date;
		}
		else
		{
			$Common_NameSpace->tdate 	= date('d-m-Y');
			$this->view->date			= date('d-m-Y');
		}
	
		// ADDITIONAL WHERE CONDITION
		if($Common_NameSpace->tdate)
			$additional_where_condition[] = " (transactiondate BETWEEN \'".date("Y-m-d 00:00:00",strtotime(str_replace('/', '-', $Common_NameSpace->tdate)))."\' AND \'".date("Y-m-d 23:59:59",strtotime(str_replace('/', '-', $Common_NameSpace->tdate)))."\' )";
		
		
        //variable declaration for grid title
		$columns_show 	=  array($this->translate->_('Tran. ID'),$this->translate->_('Reference No.'),
								 $this->translate->_('From Date'),$this->translate->_('To Date'),
								 $this->translate->_('Depot Code'));
		
		$cols_array 	= array('headerid','referenceno','DATE_FORMAT(fromdate,"%d-%m-%Y") AS fromdate','DATE_FORMAT(todate,"%d-%m-%Y") AS todate',
								'depotcode','headerid as edit_del_primary_id');	
		
		// ARRAY FOR GRID PAGINATION
		$pagingparams = array(
			"show_grid_heading" => true,
			"grid_heading_message" => $this->translate->_('Overview'),
			"show_records_per_page" => isset($formdata["show_records_per_page"])?$formdata["show_records_per_page"]:10,
			"show_searchbox" => true,
			"searchbox_value" => isset($formdata["grid_search_text"])?$formdata["grid_search_text"]:"",
			"pagename" => $this->translate->_('Depot Inventory'),
			"show_selectbox" => true,
			"show_editlink" => true,
			"selected_list" => $checked,
			"show_deletelink" => false,			
			"show_deleteall" => false,
			"primaryid" => "headerid",			
			"editlink" => array("/inventory/transaction/adddepotdamageexpiry/id/#pattern#/edit/yes/","#pattern#"),
			"nodata_message" => $this->translate->_('No Record(s) Found'),
			"fetch_columns_inquery" => $cols_array,
			"show_columns" => $columns_show,
			"additional_where" => $additional_where_condition,			
		);

		if(!$this->checkaccess("update"))
		{
			$pagingparams["show_editlink"] = false;
		}
		
		// create grid class object
		$pagingshow = new SFA_Paging($pagingparams);
		
		// call common function of grid class
		$get_return_vals = $pagingshow->commnfunc();
		
		//print_r($get_return_vals['where_condition']);
		
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
		
		$downloadCSV = (isset($formdata['downloadcsv'])) ? $formdata['downloadcsv'] : $params['downloadcsv'];    
        
        // Hiren Dave on 19 Nov, 2012 - START - to check if need to print data
        $printData = (isset($formdata['printData'])) ? $formdata['printData'] : $params['printData'];
    
		// called stored procedure for counter
		$result = $this->SFA_Comman->executequery('CALL sp_get_inventory_transaction_depotdamageexpiry(?,?,?,?,?,?,?,?)',$param_array,'',$downloadCSV,$printData,1,$pagingparams);
	
		$data_arr["count"] 		= $result[0][0]['counter'];
		$data_arr["data"][0] 	= $result[1];
		
		// pass the data in summary_showdatagrid() function & create a final variable for view
		$this->view->pagegridshow =  $pagingshow->summary_showdatagrid($data_arr);
		
		$this->view->addScriptPath(APPLICATION_PATH."/theme/default/datagrid/");
    }

    /**
    * @name       adddepotdamageexpiry
    * @since      7-2-2012
    * @version    Release: 1
    * @author     PM <pankit@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This action is use for the customer pos limit list
    */
    public function adddepotdamageexpiryAction(){
        //view variable declaration
        $this->view->params = $params = $this->getRequest()->getParams();
        $this->view->formdata = $formdata = $this->_request->getPost();
		
		$Common_NameSpace = new Zend_Session_Namespace('DepotDamageExpiry');
	
		$sel_date = $Common_NameSpace->tdate;
	
		// ADD DATE VALUE IN SESSION
		if($sel_date != '') {
			$Common_NameSpace->tdate 	= $sel_date;
			$this->view->date			= $sel_date;
			$this->view->formdata['transactiondate'] = $sel_date;
		}
		else
		{
			$Common_NameSpace->tdate 	= date('d-m-Y');
			$this->view->date			= date('d-m-Y');
			$this->view->formdata['transactiondate'] = date('d-m-Y');
		}

        // IF EXTRA PARAMS ARE REQUIRED
		$ex_param = "";
		if(isset($params["id"]) && $params["id"]>0) {
			$ex_param = "/key/".$params["id"];
		}
		
		$this->view->itemgrid    = $this->view->BaseUrl("/".$params['module']."/".$params['controller']."/depotdamageexpirygrid".$ex_param);
		
        if(count($formdata) > 0) {
			
			if(isset($formdata['btnpost'])) {
				
				$res = $this->SFA_Comman->executequery('CALL sp_edit_inventory_transaction_adddepotdamageexpiry(?)',$formdata["hdnid"],'');
				SFA_Message::setMsg($this->translate->_('Update Record'));             
			}
			$this->_helper->redirector('depotdamageexpiry', 'transaction', 'inventory');
		}
		elseif($params["id"]) {
			$result = $this->SFA_Comman->executequery('CALL sp_get_inventory_transaction_adddepotdamageexpiry(?)',$params["id"],'');			
			$this->view->item 		= $result[0];
			$this->view->depot 		= $result[1];
			$this->view->formdata 	= $result[2][0];			
		}
		else {
			$result = $this->SFA_Comman->executequery('CALL sp_get_inventory_transaction_adddepotdamageexpiry(?)','0','');
			$this->view->item 		= $result[0];
			$this->view->depot 		= $result[1];
			$this->view->formdata['headerid'] = ($result[2][0]['Auto_increment'] == '' ) ? '1' : $result[2][0]['Auto_increment'];
		}
    }
	/**
    * @name       depotdamageexpirygrid
    * @since      7-2-2012
    * @version    Release: 1
    * @author     PM <pankit@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This action is use for depot inventory grid
    */
    public function adddepotdamageexpirygridAction() {
		
		$params = $this->_request->getPost();
		
		if($params["add"]=="yes") { 
			
			$param_array 	 = array();
			$param_array[1]  = $params['ddldepot'];
			$param_array[2]  = $params['txtreferanceno'];
			$param_array[3]  = $params['txttransactiondate'];
			$param_array[4]  = $params['txtstartdate'];
			$param_array[5]  = $params['txtenddate'];
			$param_array[6]  = $params['ddlitem'];
			$param_array[7]  = $params['txtpieces'];
			$param_array[8]  = $params['txtcases'];
			$param_array[9]  = $params['txtbatchno'];
			$param_array[10] = $params['txtexpdate'];
			$param_array[11] = $params['txtupc'];
			$param_array[12] = $params['ddlreason'];
			$param_array[13] = (isset($params['hdnid'])) ? $params['hdnid'] : 0;
			
			$returnval = $this->SFA_Comman->executequery('CALL sp_add_inventory_transaction_adddepotdamageexpiry(?,?,?,?,?,?,?,?,?,?,?,?)',$param_array,'');
			$last_id = $returnval[0][0]['lastid'];
			
			if($returnval[0][0]['dupli'] == 'Duplicate'){
				SFA_Message::setErrorMsg($this->translate->_('Item Already Added..'));
				echo $last_id;exit;
			}elseif(isset($last_id) && $last_id>0) {
				SFA_Message::setMsg($this->translate->_('New Record'));
				echo $last_id;exit;
			}
		}
	}

    /**
    * @name       depotdamageexpirygrid
    * @since      7-2-2012
    * @version    Release: 1
    * @author     PM <pankit@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This action is use for depot inventory grid
    */
    public function depotdamageexpirygridAction() {
        
        $params = $this->getRequest()->getParams();
		
		// For Alternate Code Status.
		$cpanel				= $this->SFA_Comman->getaltcodestatus();
		$altcode_status		= $cpanel["Use Alternate Code"]['status'];
    
        $columns_array = array('im.actualitemcode','im.itemshortdescription','im.unitspercase as upc','ded.batchno',
							   'DATE_FORMAT(ded.expirydate, "%d-%m-%Y") as expirydate','IFNULL(FLOOR(ded.depotqty/im.unitspercase),0) as cases',
							   'IFNULL((ded.depotqty%im.unitspercase),0) as pieces','ded.detailid AS edit_del_primary_id');
		//CONCAT(did.depotinvtranskey,".",im.actualitemcode)
        $columns_show =  array($this->translate->_('Item Code'),$this->translate->_('Item Name'),
							   $this->translate->_('UPC'),$this->translate->_('Batch No'),
							   $this->translate->_('Expiry Date'),$this->translate->_('Case'),
							   $this->translate->_('Pcs'));
		
		if($this->css == 'ar_') {
			$columns_array[1]	= 'im.arbitemshortdescription AS itemshortdescription';
		}
		if($altcode_status)
            $columns_array[0] = 'im.alternatecode';		

		// DELETE THE RECORD
		if($params["delete"]=="yes") {
			
			$param_array 		= array();
			$param_array[1] 	= $params["id"];
			$param_array[2] 	= $params["key"];
			
			$this->SFA_Comman->executequery('CALL sp_delete_inventory_transaction_depotdamageexpirygrid(?,?,?)',$param_array,'');
			SFA_Message::setMsg($this->translate->_('Delete Record'));
		}
	
		// UPDATE THE RECORD
		if($params["update"]=="yes") {
			
			$updateData			= array();	
			$updateData["1"] 	= $params["batchno"];
			$updateData["2"] 	= $params["expirydate"];
			$updateData["3"] 	= $params["cases"];
			$updateData["4"] 	= $params["pieces"];
			$updateData["5"] 	= $params["id"];
			$updateData["6"] 	= $params["key"];
			
			$this->SFA_Comman->executequery('CALL sp_edit_inventory_transaction_depotdamageexpirygrid(?,?,?,?,?,?)',$updateData,'');
			SFA_Message::setMsg($this->translate->_('Update Record'));
		}

		// IF EXTRA PARAMS ARE REQUIRED
		$ex_param = "";
		if(isset($params["key"]) && $params["key"]>0) {
			$ex_param = "/key/".$params["key"];
		}

			
		// ARRAY FOR GRID PAGINATION
		$pagingparams = array(
			"show_grid_heading" => false,
			"grid_heading_message" => $this->translate->_('Overview'),
			"show_records_per_page" => isset($formdata["show_records_per_page"])?$formdata["show_records_per_page"]:10,
			"show_searchbox" => false,
			"show_selectbox" => false,
			"show_editlink" => false,
			"show_deletelink" => true,
			"currentlink" => array("/".$params['module']."/".$params['controller']."/".$params['action'].$ex_param),
			"deletelink" => array("/id/#pattern#/delete/yes/","#pattern#"),
			"editlink" => array("/id/#pattern#/edit/yes","#pattern#"),
			"show_deleteall" => true,
			"primaryid" => "ded.detailid",
			"noeditfields" => array('actualitemcode','alternatecode','itemshortdescription','upc','batchno','expirydate'),
			"fetch_columns_inquery" => $columns_array,
			"show_columns" => $columns_show,
			"nodata_message" => $this->translate->_('No Record(s) Found')
			);

		// WHEN GRID IS IN EDIT MODE
		if($params["edit"]=="yes") {
			$pagingparams["editmode"] = true;
			$pagingparams["editmodeid"] = $params["id"];
			$pagingparams["editmodevalue"] = "id";
		}
		
		//$isposted = $this->SFA_Comman->executequery('CALL sp_get_inventory_transaction_adddepotdamageexpiry(?)',$params["key"],'');
		//
		//if(isset($params["key"]) && $params["key"]>0) {
		//	$pagingparams["show_editlink"] 	 = false;
		//	$pagingparams["show_deletelink"] = false;
		//}
	
		$pagingshow = new SFA_Ajaxpaging($pagingparams);
		
		$get_return_vals = $pagingshow->commnfunc();
		
		$param_array 	= array();
		$param_array[1] = '';
		$param_array[2] = $get_return_vals['order_columns_name'];
		$param_array[3] = $get_return_vals['order_type'];
		$param_array[4] = $get_return_vals['offset'];
		$param_array[5] = (int)$get_return_vals['show_records_per_page'];
		$param_array[6] = implode(", ",$columns_array);
		$param_array[7] = ' AND ded.headerid ='.$params['key'].' ';
	
		// called stored procedure for counter
		$result = $this->SFA_Comman->executequery('CALL sp_get_inventory_transaction_depotdamageexpirygrid(?,?,?,?,?,?,?)',$param_array,'');
		$data_arr["count"] = $result[0][0]['counter'];
		$data_arr["data"][0] = $result[1];
	
		$this->view->pagegridshow =  $pagingshow->summary_showdatagrid($data_arr);
		$this->view->addScriptPath(APPLICATION_PATH."/theme/default/datagrid/");
	
		$this->render("ajaxgrid");	
    }
	# -----------------------------------

    /**
    * @name       depotinventory
    * @since      7-2-2012
    * @version    Release: 1
    * @author     PM <pankit@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This action is use for the customer pos limit list
    */
    public function depotinventoryAction(){
        //view variable declaration
        $this->view->formdata = $formdata = $this->_request->getPost();

        if($formdata["hdDelete"]==1)
		{
			$ids = implode(',',$formdata['chk']);
			$param_array 	= array();
			$param_array[1]	= $ids;
			$param_array[2]	= $this->currentUser->username;
			
			$result 	= $this->SFA_Comman->executequery('CALL sp_delete_inventory_transaction_depotinventory(?,?)',$param_array,'');
			
			if($result[0][0]['deleted_id'] =='')
			{
				$ids		= explode(',',$ids);
				$checked 	= $ids;
				
				SFA_Message::setErrorMsg($this->translate->_('Record Already In use'));
			}
			else
			{
				$deleted_id 	= explode(',',$result[0][0]['deleted_id']);
				$ids		= explode(',',$ids);
				$checked 	= array_diff($ids,$deleted_id);
				
				if(count($ids) != count($deleted_id)){
					SFA_Message::setErrorMsg($this->translate->_('Record Already In use'));
				}
				
				SFA_Message::setMsg($this->translate->_('Delete Record'));
			}
		}
		
		$Common_NameSpace = new Zend_Session_Namespace('DepotInventory');
		if($formdata['btnreset'] == 'RESET')
		{
			$formdata["txtdate"] 	= '';
			$Common_NameSpace->tdate	= '';
		}
		$last_url = htmlspecialchars($_SERVER['HTTP_REFERER']);
		if(strpos($last_url,'adddepotinventory'))
		{
			$sel_date = ($formdata["txtdate"] != '') ? $formdata["txtdate"] : $Common_NameSpace->tdate;
		}
		else
		{
			$sel_date = ($formdata["txtdate"] != '') ? $formdata["txtdate"] : date('d-m-Y');
		}	
		
		// ADD DATE VALUE IN SESSION
		if($sel_date != '') {
			$Common_NameSpace->tdate 	= $sel_date;
			$this->view->date			= $sel_date;
		}
		else
		{
			$Common_NameSpace->tdate 	= date('d-m-Y');
			$this->view->date			= date('d-m-Y');
		}
	
		// ADDITIONAL WHERE CONDITION
		if($Common_NameSpace->tdate)
			$additional_where_condition[] = " (transactiondate BETWEEN \'".date("Y-m-d 00:00:00",strtotime(str_replace('/', '-', $Common_NameSpace->tdate)))."\' AND \'".date("Y-m-d 23:59:59",strtotime(str_replace('/', '-', $Common_NameSpace->tdate)))."\' )";
		

        //variable declaration for grid title
		$columns_show 	=  array($this->translate->_('Inventory No.'),$this->translate->_('From Depot'),$this->translate->_('To Depot'),
								 $this->translate->_('Transaction Date'),$this->translate->_('IsPosted'));
		
		$cols_array 	= array('depotinvtranskey','CONCAT(fromdepot," - ",dm.depotname) as fromdepot','CONCAT(todepot," - ",dm1.depotname) as todepot','DATE_FORMAT(transactiondate,"%d-%m-%Y") AS transactiondate',
								'isposted','depotinvtranskey as edit_del_primary_id');
		
		// ARRAY FOR GRID PAGINATION
		$pagingparams = array(
			"show_grid_heading" => true,
			"grid_heading_message" => $this->translate->_('Overview'),
			"show_records_per_page" => isset($formdata["show_records_per_page"])?$formdata["show_records_per_page"]:10,
			"show_searchbox" => true,
			"searchbox_value" => isset($formdata["grid_search_text"])?$formdata["grid_search_text"]:"",
			"pagename" => $this->translate->_('Depot Inventory'),
			"show_selectbox" => true,
			"show_editlink" => true,
			"selected_list" => $checked,
			"show_deletelink" => false,			
			"show_deleteall" => false,
			"primaryid" => "depotinvtranskey",
			"status_cols" => array(
								array(
										"cols_name" => "isposted",
										"status_change" => array("0"=>"Not Posted","1"=>"Posted")
									)
								),
			"editlink" => array("/inventory/transaction/adddepotinventory/id/#pattern#/edit/yes/","#pattern#"),
			"nodata_message" => $this->translate->_('No Record(s) Found'),
			"fetch_columns_inquery" => $cols_array,
			"show_columns" => $columns_show,
			"additional_where" => $additional_where_condition
		);

		if(!$this->checkaccess("update"))
		{
			$pagingparams["show_editlink"] = false;
		}
		
		// create grid class object
		$pagingshow = new SFA_Paging($pagingparams);
		
		// call common function of grid class
		$get_return_vals = $pagingshow->commnfunc();
		
		//print_r($get_return_vals['where_condition']);
		
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
		
		$downloadCSV = (isset($formdata['downloadcsv'])) ? $formdata['downloadcsv'] : $params['downloadcsv'];    
        
        // Hiren Dave on 19 Nov, 2012 - START - to check if need to print data
        $printData = (isset($formdata['printData'])) ? $formdata['printData'] : $params['printData'];
    
		// called stored procedure for counter
		$result = $this->SFA_Comman->executequery('CALL sp_get_inventory_transaction_depotinventory(?,?,?,?,?,?,?,?)',$param_array,'',$downloadCSV,$printData,1,$pagingparams);
	
		$data_arr["count"] 		= $result[0][0]['counter'];
		$data_arr["data"][0] 	= $result[1];
		
		// pass the data in summary_showdatagrid() function & create a final variable for view
		$this->view->pagegridshow =  $pagingshow->summary_showdatagrid($data_arr);
		
		$this->view->addScriptPath(APPLICATION_PATH."/theme/default/datagrid/");
    }

    /**
    * @name       adddepotinventory
    * @since      7-2-2012
    * @version    Release: 1
    * @author     PM <pankit@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This action is use for the customer pos limit list
    */
    public function adddepotinventoryAction(){
        //view variable declaration
        $this->view->params = $params = $this->getRequest()->getParams();
        $this->view->formdata = $formdata = $this->_request->getPost();

        // IF EXTRA PARAMS ARE REQUIRED
		$ex_param = "";
		if(isset($params["id"]) && $params["id"]>0)
			$ex_param = "/key/".$params["id"];
		
		$Settings_NameSpace = new Zend_Session_Namespace('Settings');
		$this->view->batch_status = $Settings_NameSpace->cpanel['Enabled Batch And Expiry']['status'];
		
		$Common_NameSpace = new Zend_Session_Namespace('DepotInventory');
	
		$sel_date = $Common_NameSpace->tdate;
	
		// ADD DATE VALUE IN SESSION
		if($sel_date != '') {
			$Common_NameSpace->tdate 	= $sel_date;
			$this->view->date			= $sel_date;
			$this->view->formdata['transactiondate'] = $sel_date;
		}
		else
		{
			$Common_NameSpace->tdate 	= date('d-m-Y');
			$this->view->date			= date('d-m-Y');
			$this->view->formdata['transactiondate'] = date('d-m-Y');
		}
	
		$this->view->itemgrid    = $this->view->BaseUrl("/".$params['module']."/".$params['controller']."/depotinventorygrid".$ex_param);
		
        if(count($formdata) > 0) {            
			if(isset($formdata['btndatapost'])) {
				
				$res = $this->SFA_Comman->executequery('CALL sp_edit_inventory_transaction_adddepotinventory(?)',$formdata["hdnid"],'');
				SFA_Message::setMsg($this->translate->_('Update Record'));             
			}
			$this->_helper->redirector('depotinventory', 'transaction', 'inventory');
		}
		elseif($params["id"])
		{
			$result = $this->SFA_Comman->executequery('CALL sp_get_inventory_transaction_adddepotinventory(?)',$params["id"],'');
			//item array
			$this->view->item 		= $result[0];
			// All the Depots (with Central WH flag not activated) defined in the system will be displayed in the drop-down.
			$this->view->todepot 	= $result[1];
			// All the Depots (with Central WH flag activated) defined in the system will be displayed in the drop-down.
			$this->view->fromdepot 	= $result[2];
			$this->view->formdata 	= $result[3][0];
		}
		else
		{
			$result = $this->SFA_Comman->executequery('CALL sp_get_inventory_transaction_adddepotinventory(?)','0','');
			//item array
			$this->view->item 		= $result[0];
			// All the Depots (with Central WH flag not activated) defined in the system will be displayed in the drop-down.
			$this->view->todepot 	= $result[1];
			// All the Depots (with Central WH flag activated) defined in the system will be displayed in the drop-down.
			$this->view->fromdepot 	= $result[2];
			$this->view->formdata['depotinvtranskey'] = ($result[3][0]['Auto_increment'] == '' ) ? '1' : $result[3][0]['Auto_increment'];
		}
    }
	/**
    * @name       depotinventorygrid
    * @since      7-2-2012
    * @version    Release: 1
    * @author     PM <pankit@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This action is use for depot inventory grid
    */
    public function adddepotinventorygridAction() {
		
		$params = $this->getRequest()->getParams();
		
		if($params["add"]=="yes") {
			
			$param_array 	 = array();
			$param_array[1]  = $params['ddlfromdepot'];
			$param_array[2]  = $params['ddltodepot'];
			$param_array[3]  = $params['txttransactiondate'];
			$param_array[4]  = $params['ddlitem'];
			$param_array[5]  = $params['txtpieces'];
			$param_array[6]  = $params['txtcases'];
			$param_array[7]  = $params['txtbatchno'];			
			$param_array[8]  = ($param_array[7] == 'NONE') ? '31-12-2099' : $params['txtexpdate'];
			$param_array[9]  = $params['txtupc'];
			$param_array[10] = (isset($params['hdnid'])) ? $params['hdnid'] : 0;
			
			$returnval = $this->SFA_Comman->executequery('CALL sp_add_inventory_transaction_adddepotinventory(?,?,?,?,?,?,?,?,?,?)',$param_array,'');
			$last_id = $returnval[0][0]['lastid'];
			
			if($returnval[0][0]['dupli'] == 'Duplicate'){
				SFA_Message::setErrorMsg($this->translate->_('Item Already Added..'));
				echo $last_id;exit;
			}elseif(isset($last_id) && $last_id>0) {
				SFA_Message::setMsg($this->translate->_('New Record'));
				echo $last_id;exit;
			}
		}
	}

    /**
    * @name       depotinventorygrid
    * @since      7-2-2012
    * @version    Release: 1
    * @author     PM <pankit@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This action is use for depot inventory grid
    */
    public function depotinventorygridAction() {
        
        $params = $this->getRequest()->getParams();
		
		// For Alternate Code Status.
		$cpanel				= $this->SFA_Comman->getaltcodestatus();
		$altcode_status		= $cpanel["Use Alternate Code"]['status'];
    
        $columns_array = array('im.actualitemcode','im.itemshortdescription','im.unitspercase as upc','did.batchno',
							   'DATE_FORMAT(did.expirydate, "%d-%m-%Y") as expirydate','IFNULL(FLOOR(did.quantity/im.unitspercase),0) as cases',
							   'IFNULL((did.quantity%im.unitspercase),0) as pieces','primary_key AS edit_del_primary_id');
		//CONCAT(did.depotinvtranskey,".",im.actualitemcode)
        $columns_show =  array($this->translate->_('Item Code'),$this->translate->_('Item Name'),
							   $this->translate->_('UPC'),$this->translate->_('Batch No'),
							   $this->translate->_('Expiry Date'),$this->translate->_('Case'),
							   $this->translate->_('Pcs'));
		
		if($this->css == 'ar_') {
			$columns_array[1]	= 'im.arbitemshortdescription AS itemshortdescription';
		}
		
		if($altcode_status)
            $columns_array[0] = 'im.alternatecode';		

		// DELETE THE RECORD
		if($params["delete"]=="yes") {
			
			$param_array 		= array();
			$param_array[1] 	= $params["id"];
			$param_array[2] 	= $params["key"];
			
			$this->SFA_Comman->executequery('CALL sp_delete_inventory_transaction_depotinventorygrid(?,?)',$param_array,'');
			SFA_Message::setMsg($this->translate->_('Delete Record'));
		}
	
		// UPDATE THE RECORD
		if($params["update"]=="yes") {
			
			$updateData			= array();	
			$updateData["1"] 	= $params["batchno"];
			$updateData["2"] 	= $params["expirydate"];
			$updateData["3"] 	= $params["cases"];
			$updateData["4"] 	= $params["pieces"];
			$updateData["5"] 	= $params["id"];
			$updateData["6"] 	= $params["key"];
			
			$returnval 	= $this->SFA_Comman->executequery('CALL sp_edit_inventory_transaction_depotinventorygrid(?,?,?,?,?,?)',$updateData,'');
			$last_id	= $returnval[0][0]['lastid'];
			
			if($returnval[0][0]['dupli'] == 'Duplicate'){
				SFA_Message::setErrorMsg($this->translate->_('Item Already Added..'));				
			}elseif(isset($last_id) && $last_id>0) {
				SFA_Message::setMsg($this->translate->_('Update Record'));
			}
			
		}

		// IF EXTRA PARAMS ARE REQUIRED
		$ex_param = "";
		if(isset($params["key"]) && $params["key"]>0)
			$ex_param = "/key/".$params["key"];

			
		// ARRAY FOR GRID PAGINATION
		$pagingparams = array(
			"show_grid_heading" => false,
			"grid_heading_message" => $this->translate->_('Overview'),
			"show_records_per_page" => isset($formdata["show_records_per_page"])?$formdata["show_records_per_page"]:10,
			"show_searchbox" => false,
			"show_selectbox" => false,
			"show_editlink" => true,			
			"show_deletelink" => true,
			"currentlink" => array("/".$params['module']."/".$params['controller']."/".$params['action'].$ex_param),
			"deletelink" => array("/id/#pattern#/delete/yes/","#pattern#"),
			"editlink" => array("/id/#pattern#/edit/yes","#pattern#"),
			"show_deleteall" => true,
			"primaryid" => "primary_key",
			"noeditfields" => array('actualitemcode','alternatecode','itemshortdescription','upc'),
			"fetch_columns_inquery" => $columns_array,
			"show_columns" => $columns_show,
			"nodata_message" => $this->translate->_('No Record(s) Found')
			);

		// WHEN GRID IS IN EDIT MODE
		if($params["edit"]=="yes") {
			$pagingparams["editmode"] = true;
			$pagingparams["editmodeid"] = $params["id"];
			$pagingparams["editmodevalue"] = "id";
		}
		
		$isposted = $this->SFA_Comman->executequery('CALL sp_get_inventory_transaction_adddepotinventory(?)',$params["key"],'');
		
		if($isposted[3][0]['isposted'] == 1) {
			$pagingparams["show_editlink"] 	 = false;
			$pagingparams["show_deletelink"] = false;
		}
	
		$pagingshow = new SFA_Ajaxpaging($pagingparams);
		
		$get_return_vals = $pagingshow->commnfunc();
		
		$param_array 	= array();
		$param_array[1] = '';
		$param_array[2] = $get_return_vals['order_columns_name'];
		$param_array[3] = $get_return_vals['order_type'];
		$param_array[4] = $get_return_vals['offset'];
		$param_array[5] = (int)$get_return_vals['show_records_per_page'];
		$param_array[6] = implode(", ",$columns_array);
		$param_array[7] = ' AND did.depotinvtranskey ='.$params['key'].' ';
	
		// called stored procedure for counter
		$result = $this->SFA_Comman->executequery('CALL sp_get_inventory_transaction_depotinventorygrid(?,?,?,?,?,?,?)',$param_array,'');
		$data_arr["count"] = $result[0][0]['counter'];
		$data_arr["data"][0] = $result[1];
	
		$this->view->pagegridshow =  $pagingshow->summary_showdatagrid($data_arr);
		$this->view->addScriptPath(APPLICATION_PATH."/theme/default/datagrid/");
	
		$this->render("ajaxgrid");	
    }


    /**
    * @name       depotstock
    * @since      7-2-2012
    * @version    Release: 1
    * @author     PM <pankit@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This action is use for give depot stock information
    */
    public function depotstockAction()
	{   
		$this->view->formdata = $formdata = $this->_request->getPost();
		
		$depot = $this->SFA_Comman->executequery('CALL sp_combo_depotmaster()','','');
		$this->view->depot = $depot[0];

        $columns_show = array (
								$this->translate->_('Item Code'),$this->translate->_('Description'),
								$this->translate->_('Available Case'),$this->translate->_('Available Pcs'),
								$this->translate->_('Damaged Case'),$this->translate->_('Damaged Pcs'),
								$this->translate->_('Expiry Case'),$this->translate->_('Expiry Pcs')
							  );		
		
		$cols_array 	= array('ds.itemcode','im.itemdescription AS description',
								'SUM(IFNULL(FLOOR(ds.quantity/im.unitspercase),0)) AS qtycases','SUM(IFNULL((ds.quantity%im.unitspercase),0)) AS qtypieces',
								'SUM(IFNULL(FLOOR(ds.damagedqty/im.unitspercase),0)) AS dmgcases','SUM(IFNULL((ds.damagedqty%im.unitspercase),0)) AS dmgpieces',
								'SUM(IFNULL(FLOOR(ds.expiryqty/im.unitspercase),0)) AS expcases','SUM(IFNULL((ds.expiryqty%im.unitspercase),0)) AS exppieces',
								'ds.itemcode AS edit_del_primary_id');
								//'CONCAT(ds.itemcode,"_",ds.batchno) AS edit_del_primary_id');
		
		
		if($this->css == 'ar_') {
			$cols_array[1]	= 'im.arbitemdescription AS description';
		}
		
		// For Alternate Code Status.
		$cpanel				= $this->SFA_Comman->getaltcodestatus();
		$altcode_status		= $cpanel["Use Alternate Code"]['status'];
		
		if($altcode_status) {
			$cols_array[0] 	= 'im.alternatecode AS itemcode';
		}
		$Common_DepotCode = new Zend_Session_Namespace('DepotStock');
		
		$sel_depotcode = ($formdata["ddldepot"] != '') ? $formdata["ddldepot"] : $Common_DepotCode->depotcode;
		
		
		// ADD DATE VALUE IN SESSION
		if($sel_depotcode != '') {
			$Common_DepotCode->depotcode 	= $sel_depotcode;
			$this->view->depotcode			= $sel_depotcode;
		} else {
			$Common_DepotCode->depotcode 	= 0;
			$this->view->depotcode			= 0;
		}
		
		// ADDITIONAL WHERE CONDITION
		if($Common_DepotCode->depotcode > 0) {
			$additional_where_condition[] = " ds.depotcode 	= ".$Common_DepotCode->depotcode;
		}
		
		// ARRAY FOR GRID PAGINATION
		$pagingparams = array(
			"show_grid_heading" => true,
			"grid_heading_message" => $this->translate->_('Overview'),
			"show_records_per_page" => isset($formdata["show_records_per_page"])?$formdata["show_records_per_page"]:10,
			"show_searchbox" => false,
			"searchbox_value" => isset($formdata["grid_search_text"])?$formdata["grid_search_text"]:"",
			"pagename" => $this->translate->_('Depot Stock'),
			"show_selectbox" => false,
			"show_editlink" => false,
			"selected_list" => $checked,
			"show_deletelink" => false,			
			"show_deleteall" => false,			
			"nodata_message" => $this->translate->_('No Record(s) Found'),
			"fetch_columns_inquery" => $cols_array,
			"show_columns" => $columns_show,
			"additional_where" => $additional_where_condition,
			"show_extralink" => true,
			"extralink" 	=> array(array("View","/inventory/transaction/depotstockdetail/id/#pattern#/view/yes/","#pattern#")),
		);
		
		// create grid class object
		$pagingshow = new SFA_Paging($pagingparams);
		
		// call common function of grid class
		$get_return_vals = $pagingshow->commnfunc();
		
		//print_r($get_return_vals['where_condition']);
		
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
		$param_array[9] = " GROUP BY ds.itemcode ";
		
		$downloadCSV = (isset($formdata['downloadcsv'])) ? $formdata['downloadcsv'] : $params['downloadcsv'];    
        
        // Hiren Dave on 19 Nov, 2012 - START - to check if need to print data
        $printData = (isset($formdata['printData'])) ? $formdata['printData'] : $params['printData'];
    
		// called stored procedure for counter
		$result = $this->SFA_Comman->executequery('CALL sp_get_inventory_transaction_depotstock(?,?,?,?,?,?,?,?,?)',$param_array,'',$downloadCSV,$printData,1,$pagingparams);
	
		$data_arr["count"] 		= $result[0][0]['counter'];
		$data_arr["data"][0] 	= $result[1];
		
		// pass the data in summary_showdatagrid() function & create a final variable for view
		$this->view->pagegridshow =  $pagingshow->summary_showdatagrid($data_arr);
		
		$this->view->addScriptPath(APPLICATION_PATH."/theme/default/datagrid/");
    }
	/**
    * @name       depotstockdetailAction
    * @since      7-2-2012
    * @version    Release: 1
    * @author     PM <pankit@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This action is use for display depot stock information
    */
    public function depotstockdetailAction()
	{   
		$this->view->formdata = $formdata = $this->_request->getPost();		
		$this->view->params = $params = $this->getRequest()->getParams();
		
		$depot = $this->SFA_Comman->executequery('CALL sp_combo_depotmaster()','','');
		$this->view->depot = $depot[0];

        $columns_show = array (
								$this->translate->_('Batch Number'),$this->translate->_('Expiry Date'),
								$this->translate->_('Available Case'),$this->translate->_('Available Pcs'),
								$this->translate->_('Damaged Case'),$this->translate->_('Damaged Pcs'),
								$this->translate->_('Expiry Case'),$this->translate->_('Expiry Pcs')								
							);		
		
		$cols_array 	= array('ds.batchno','DATE_FORMAT(ds.expirydate,"%d-%m-%Y") AS expirydate',
								'IFNULL(FLOOR(ds.quantity/im.unitspercase),0) AS qtycases','IFNULL((ds.quantity%im.unitspercase),0) AS qtypieces',
								'IFNULL(FLOOR(ds.damagedqty/im.unitspercase),0) AS dmgcases','IFNULL((ds.damagedqty%im.unitspercase),0) AS dmgpieces',
								'IFNULL(FLOOR(ds.expiryqty/im.unitspercase),0) AS expcases','IFNULL((ds.expiryqty%im.unitspercase),0) AS exppieces'								
								);		
		
		
		//$str = explode('_',$params['id']);
		
		$Common_DepotCode = new Zend_Session_Namespace('DepotStock');
		if($Common_DepotCode->depotcode > 0) {
			$additional_where_condition[] 	= " ds.depotcode 	= ".$Common_DepotCode->depotcode;
			$this->view->depotcode 			= $Common_DepotCode->depotcode;
		}
		$additional_where_condition[] = " ds.itemcode 	= ".$params['id'];
		//$additional_where_condition[] = " ds.batchno 	= \'".$str[1]."\'";
		
		// ARRAY FOR GRID PAGINATION
		$pagingparams = array(
			"show_grid_heading" => false,
			"grid_heading_message" => $this->translate->_('Overview'),
			"show_records_per_page" => isset($formdata["show_records_per_page"])?$formdata["show_records_per_page"]:10,
			"show_searchbox" => false,
			"searchbox_value" => isset($formdata["grid_search_text"])?$formdata["grid_search_text"]:"",
			"pagename" => $this->translate->_('Depot Stock'),
			"show_selectbox" => false,
			"show_editlink" => false,
			"show_deletelink" => false,
			"show_deleteall" => false,
			"primaryid" => "depotinventorykey",
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
		$param_array 	= array();
		$param_array[1] = '1';
		$param_array[2] = '';
		$param_array[3] = $get_return_vals['order_columns_name'];
		$param_array[4] = $get_return_vals['order_type'];
		$param_array[5] = $get_return_vals['offset'];
		$param_array[6] = (int)$get_return_vals['show_records_per_page'];
		$param_array[7] = implode(", ",$pagingparams["fetch_columns_inquery"]);
		$param_array[8] = strlen($get_return_vals['where_condition'])>0?$get_return_vals['where_condition']:'';
		$param_array[9] = " ";
		
		$downloadCSV = (isset($formdata['downloadcsv'])) ? $formdata['downloadcsv'] : $params['downloadcsv'];    
        
        // Hiren Dave on 19 Nov, 2012 - START - to check if need to print data
        $printData = (isset($formdata['printData'])) ? $formdata['printData'] : $params['printData'];
    
		// called stored procedure for counter
		$result = $this->SFA_Comman->executequery('CALL sp_get_inventory_transaction_depotstock(?,?,?,?,?,?,?,?,?)',$param_array,'',$downloadCSV,$printData,1,$pagingparams);
	
		$data_arr["count"] 		= $result[0][0]['counter'];
		$data_arr["data"][0] 	= $result[1];
		
		// pass the data in summary_showdatagrid() function & create a final variable for view
		$this->view->pagegridshow =  $pagingshow->summary_showdatagrid($data_arr);
		
		$this->view->addScriptPath(APPLICATION_PATH."/theme/default/datagrid/");
    }

     /**
    * @name       GRN
    * @since      13-2-2012
    * @version    Release: 1
    * @author     PM <pankit@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This action is use for the Good receipt note list
    */
    public function grnAction(){
        //view variable declaration
        $this->view->formdata = $formdata = $this->_request->getPost();
		
		if($formdata["hdDelete"]==1)
		{
			$ids = implode(',',$formdata['chk']);
			$param_array 	= array();
			$param_array[1]	= $ids;
			$param_array[2]	= $this->currentUser->username;
			
			$result 	= $this->SFA_Comman->executequery('CALL sp_delete_inventory_transaction_grn(?,?)',$param_array,'');
			
			if($result[0][0]['deleted_id'] =='')
			{
				$ids		= explode(',',$ids);
				$checked 	= $ids;
				
				SFA_Message::setErrorMsg($this->translate->_('Record Already In use'));
			}
			else
			{
				$deleted_id 	= explode(',',$result[0][0]['deleted_id']);
				$ids		= explode(',',$ids);
				$checked 	= array_diff($ids,$deleted_id);
				
				if(count($ids) != count($deleted_id)){
					SFA_Message::setErrorMsg($this->translate->_('Record Already In use'));
				}
				
				SFA_Message::setMsg($this->translate->_('Delete Record'));
			}
		}

        
		$Common_NameSpace = new Zend_Session_Namespace('GRN');
		if($formdata['btnreset'] == 'RESET')
		{
			$formdata["txtdate"] 	= '';
			$Common_NameSpace->tdate	= '';
		}
		$last_url = htmlspecialchars($_SERVER['HTTP_REFERER']);
		if(strpos($last_url,'addgrn'))
		{
			$sel_date = ($formdata["txtdate"] != '') ? $formdata["txtdate"] : $Common_NameSpace->tdate;
		}
		else
		{
			$sel_date = ($formdata["txtdate"] != '') ? $formdata["txtdate"] : date('d-m-Y');
		}	
		
		// ADD DATE VALUE IN SESSION
		if($sel_date != '') {
			$Common_NameSpace->tdate 	= $sel_date;
			$this->view->date			= $sel_date;
		}
		else
		{
			$Common_NameSpace->tdate 	= date('d-m-Y');
			$this->view->date			= date('d-m-Y');
		}
	
		// ADDITIONAL WHERE CONDITION
		if($Common_NameSpace->tdate)
			$additional_where_condition[] = " (date BETWEEN \'".date("Y-m-d 00:00:00",strtotime(str_replace('/', '-', $Common_NameSpace->tdate)))."\' AND \'".date("Y-m-d 23:59:59",strtotime(str_replace('/', '-', $Common_NameSpace->tdate)))."\' )";
		

        //variable declaration for grid title
		$columns_show 	=  array($this->translate->_('GRN No'),$this->translate->_('From'),$this->translate->_('To'),$this->translate->_('Status'));
		
		$cols_array 	= array('grheaderid','CONCAT(fromdepot," - ",dm.depotname) as fromdepot','CONCAT(todepot," - ",dm1.depotname) as todepot','grtype','grheaderid as edit_del_primary_id');
		
		// ARRAY FOR GRID PAGINATION
		$pagingparams = array(
			"show_grid_heading" => true,
			"grid_heading_message" => $this->translate->_('Overview'),
			"show_records_per_page" => isset($formdata["show_records_per_page"])?$formdata["show_records_per_page"]:10,
			"show_searchbox" => true,
			"searchbox_value" => isset($formdata["grid_search_text"])?$formdata["grid_search_text"]:"",
			"pagename" => $this->translate->_('GRN'),
			"show_selectbox" => true,
			"show_editlink" => true,
			"selected_list" => $checked,
			"show_deletelink" => false,
			"show_deleteall" => false,
			"primaryid" => "grheaderid",
			"status_cols" => array(
								array(
										"cols_name" => "grtype",
										"status_change" => array("0"=>"False","1"=>"True")
									)
								),
			"editlink" => array("/inventory/transaction/addgrn/id/#pattern#/edit/yes/","#pattern#"),
			"nodata_message" => $this->translate->_('No Record(s) Found'),
			"fetch_columns_inquery" => $cols_array,
			"show_columns" => $columns_show,
			"additional_where" => $additional_where_condition
		);

		if(!$this->checkaccess("update"))
		{
			$pagingparams["show_editlink"] = false;
		}
		
		// create grid class object
		$pagingshow = new SFA_Paging($pagingparams);
		
		// call common function of grid class
		$get_return_vals = $pagingshow->commnfunc();
		
		//print_r($get_return_vals['where_condition']);
		
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
		
		$downloadCSV = (isset($formdata['downloadcsv'])) ? $formdata['downloadcsv'] : $params['downloadcsv'];    
        
        // Hiren Dave on 19 Nov, 2012 - START - to check if need to print data
        $printData = (isset($formdata['printData'])) ? $formdata['printData'] : $params['printData'];
    
		// called stored procedure for counter
		$result = $this->SFA_Comman->executequery('CALL sp_get_inventory_transaction_grn(?,?,?,?,?,?,?,?)',$param_array,'',$downloadCSV,$printData,1,$pagingparams);
	
		$data_arr["count"] 		= $result[0][0]['counter'];
		$data_arr["data"][0] 	= $result[1];
		
		// pass the data in summary_showdatagrid() function & create a final variable for view
		$this->view->pagegridshow =  $pagingshow->summary_showdatagrid($data_arr);
		
		$this->view->addScriptPath(APPLICATION_PATH."/theme/default/datagrid/");
    }

    /**
    * @name       addgrn
    * @since      13-2-2012
    * @version    Release: 1
    * @author     PM <pankit@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This action is use for the customer pos limit list
    */
    public function addgrnAction(){
        //view variable declaration
        $this->view->params = $params = $this->getRequest()->getParams();
        $this->view->formdata = $formdata = $this->_request->getPost();

        // IF EXTRA PARAMS ARE REQUIRED
		$ex_param = "";
		if(isset($params["id"]) && $params["id"]>0)
			$ex_param = "/key/".$params["id"];
	
		$this->view->itemgrid    = $this->view->BaseUrl("/".$params['module']."/".$params['controller']."/grngrid".$ex_param);

		$Settings_NameSpace = new Zend_Session_Namespace('Settings');
		$this->view->batch_status = $Settings_NameSpace->cpanel['Enabled Batch And Expiry']['status'];
		
		$Common_NameSpace = new Zend_Session_Namespace('GRN');
	
		$sel_date = $Common_NameSpace->tdate;
	
		// ADD DATE VALUE IN SESSION
		if($sel_date != '') {
			$Common_NameSpace->tdate 		= $sel_date;
			$this->view->date				= $sel_date;
			$this->view->formdata['date'] 	= $sel_date;
		}
		else
		{
			$Common_NameSpace->tdate 		= date('d-m-Y');
			$this->view->date				= date('d-m-Y');
			$this->view->formdata['date'] 	= date('d-m-Y');
		}
		
        if(count($formdata) > 0) {
            
			if(isset($formdata['btnpost'])) {
				
				$res = $this->SFA_Comman->executequery('CALL sp_edit_inventory_transaction_adddepotinventory(?)',$formdata["hdnid"],'');
				SFA_Message::setMsg($this->translate->_('Update Record'));             
			}
			$this->_helper->redirector('grn', 'transaction', 'inventory');
		}
		elseif($params["id"])
		{
			$result = $this->SFA_Comman->executequery('CALL sp_get_inventory_transaction_addgrn(?)',$params["id"],'');
			// All the Depots (with Central WH flag not activated) defined in the system will be displayed in the drop-down.
			$this->view->todepot 	= $result[0];
			// All the Depots (with Central WH flag activated) defined in the system will be displayed in the drop-down.
			$this->view->fromdepot 	= $result[1];
			$this->view->formdata 	= $result[2][0];
		}
		else
		{
			$result = $this->SFA_Comman->executequery('CALL sp_get_inventory_transaction_addgrn(?)','0','');
			// All the Depots (with Central WH flag not activated) defined in the system will be displayed in the drop-down.
			$this->view->todepot 	= $result[0];
			// All the Depots (with Central WH flag activated) defined in the system will be displayed in the drop-down.
			$this->view->fromdepot 	= $result[1];
			$this->view->formdata['grheaderid'] = ($result[2][0]['Auto_increment'] == '' ) ? '1' : $result[2][0]['Auto_increment'];
		}
	}
    /**
    * @name       grngrid
    * @since      7-2-2012
    * @version    Release: 1
    * @author     PM <pankit@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This action is use for depot inventory grid
    */
    public function grngridAction(){
        
		$params = $this->getRequest()->getParams();
		
		// For Alternate Code Status.
		$cpanel				= $this->SFA_Comman->getaltcodestatus();
		$altcode_status		= $cpanel["Use Alternate Code"]['status'];
    
        $columns_array = array('im.actualitemcode','im.itemshortdescription',
							   'IFNULL(FLOOR(tgd.relquantity/im.unitspercase),0) AS availablecases','IFNULL((tgd.relquantity%im.unitspercase),0) AS availablepieces',
							   'IFNULL(FLOOR(tgd.hldquantity/im.unitspercase),0) AS holdcases','IFNULL((tgd.hldquantity%im.unitspercase),0) AS holdpieces',
							   'itemcode as edit_del_primary_id'
							   );
		//'batchno','DATE_FORMAT(expirydate,"%d-%m-%Y") as expirydate',		
        $columns_show =  array($this->translate->_('Item Code'),$this->translate->_('Item Name'),
							   $this->translate->_('Released Case'),$this->translate->_('Released Pcs'),
							   $this->translate->_('Hold Case'),$this->translate->_('Hold Pcs'));
		//,$this->translate->_('Batch No.'),$this->translate->_('Expiry Date')
		
		if($this->css == 'ar_') {
			$columns_array[1]	= 'im.arbitemshortdescription AS itemshortdescription';
		}
		if($altcode_status)
            $columns_array[0] = 'im.alternatecode';		

		// DELETE THE RECORD
		if($params["delete"]=="yes") {
			
			$param_array 		= array();
			$param_array[1] 	= $params["id"];
			$param_array[2] 	= $params["key"];
			
			$this->SFA_Comman->executequery('CALL sp_delete_inventory_transaction_grngrid(?,?)',$param_array,'');
			SFA_Message::setMsg($this->translate->_('Delete Record'));
		}
	
		// UPDATE THE RECORD
		if($params["update"]=="yes") {
			
			$updateData			= array();	
			//$updateData["1"] 	= $params["batchno"];
			//$updateData["2"] 	= $params["expirydate"];
			$updateData["1"] 	= $params["availablecases"];
			$updateData["2"] 	= $params["availablepieces"];
			$updateData["3"] 	= $params["holdcases"];
			$updateData["4"] 	= $params["holdpieces"];
			$updateData["5"] 	= $params["id"];
			$updateData["6"] 	= $params["key"];
			
			$this->SFA_Comman->executequery('CALL sp_edit_inventory_transaction_grngrid(?,?,?,?,?,?)',$updateData,'');
			SFA_Message::setMsg($this->translate->_('Update Record'));
		}

		// IF EXTRA PARAMS ARE REQUIRED
		$ex_param = "";
		if(isset($params["key"]) && $params["key"]>0)
			$ex_param = "/key/".$params["key"];

			
		// ARRAY FOR GRID PAGINATION
		$pagingparams = array(
			"show_grid_heading" => false,
			"grid_heading_message" => $this->translate->_('Overview'),
			"show_records_per_page" => isset($formdata["show_records_per_page"])?$formdata["show_records_per_page"]:10,
			"show_searchbox" => false,
			"show_selectbox" => false,
			"show_editlink" => true,			
			"show_deletelink" => true,
			"currentlink" => array("/".$params['module']."/".$params['controller']."/".$params['action'].$ex_param),
			"deletelink" => array("/id/#pattern#/delete/yes/","#pattern#"),
			"editlink" => array("/id/#pattern#/edit/yes","#pattern#"),
			"show_deleteall" => true,
			"primaryid" => "itemcode",
			"noeditfields" => array('actualitemcode','alternatecode','itemshortdescription'),
			"fetch_columns_inquery" => $columns_array,
			"show_columns" => $columns_show,
			"nodata_message" => $this->translate->_('No Record(s) Found')
			);

		// WHEN GRID IS IN EDIT MODE
		if($params["edit"]=="yes") {
			$pagingparams["editmode"] = true;
			$pagingparams["editmodeid"] = $params["id"];
			$pagingparams["editmodevalue"] = "id";
		}
	
		$pagingshow = new SFA_Ajaxpaging($pagingparams);
		
		$get_return_vals = $pagingshow->commnfunc();
		
		$param_array 	= array();
		$param_array[1] = '';
		$param_array[2] = $get_return_vals['order_columns_name'];
		$param_array[3] = $get_return_vals['order_type'];
		$param_array[4] = $get_return_vals['offset'];
		$param_array[5] = (int)$get_return_vals['show_records_per_page'];
		$param_array[6] = implode(", ",$columns_array);
		$param_array[7] = ' AND tgd.grheaderid ='.$params['key'].' ';
	
		// called stored procedure for counter
		$result = $this->SFA_Comman->executequery('CALL sp_get_inventory_transaction_grngrid(?,?,?,?,?,?,?)',$param_array,'');
		$data_arr["count"] = $result[0][0]['counter'];
		$data_arr["data"][0] = $result[1];
	
		$this->view->pagegridshow =  $pagingshow->summary_showdatagrid($data_arr);
		$this->view->addScriptPath(APPLICATION_PATH."/theme/default/datagrid/");
	
		$this->render("ajaxgrid");	
    }
	/**
    * @name       addgrngridAction
    * @since      7-2-2012
    * @version    Release: 1
    * @author     HD <hiren.d@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This action is use for grn grid
    */
    public function addgrngridAction() {
		
		$params = $this->getRequest()->getParams();
		
		if($params["add"]=="yes") {
			
			$param_array 	 = array();
			$param_array[1]  = $params['ddlfromdepot'];
			$param_array[2]  = $params['ddltodepot'];
			$param_array[3]  = $params['txtdate'];
			$param_array[4]  = $params['txtremark'];
			$param_array[5]  = $params['ddlitem'];
			$param_array[6]  = $params['txtrelcases'];
			$param_array[7]  = $params['txtrelpcs'];
			$param_array[8]  = $params['txtholdcases'];
			$param_array[9]  = $params['txtholdpcs'];
			$param_array[10] = $params['txtbatchno'];			
			$param_array[11] = ($param_array[10] == 'NONE') ? '31-12-2099' : $params['txtexpdate'];
			$param_array[12] = $params['txtupc'];
			$param_array[13] = $this->currentUser->username;
			$param_array[14] = (isset($params['hdnid'])) ? $params['hdnid'] : 0;
			
			$returnval = $this->SFA_Comman->executequery('CALL sp_add_inventory_transaction_addgrn(?,?,?,?,?,?,?,?,?,?,?,?,?)',$param_array,'');
			$last_id = $returnval[0][0]['lastid'];
			
			if($returnval[0][0]['dupli'] == 'Duplicate'){
				SFA_Message::setErrorMsg($this->translate->_('Item Already Added.'));
				echo $last_id;exit;
			}elseif(isset($last_id) && $last_id>0) {
				SFA_Message::setMsg($this->translate->_('New Record'));
				echo $last_id;exit;
			}
		}
	}
	/**
    * @name       fillitemcomboAction
    * @since      10-12-2012
    * @version    Release: 1
    * @author     HD <hiren.d@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This action is use for getting fill item combo
    */
    public function fillitemcomboAction() {
		
		$params = $this->getRequest()->getParams();
		$result = $this->SFA_Comman->executequery('CALL sp_combo_itemmaster_depotcode_grn(?)',$params['depotid'],'');
		//echo Zend_Json::encode($result);
		echo Zend_Json_Encoder::encode($result);
		exit;		
	}
	/**
    * @name       getitemqtyinfoAction
    * @since      10-12-2012
    * @version    Release: 1
    * @author     HD <hiren.d@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This action is use for getting item and quantity info
    */
    public function getitemqtyinfoAction() {
		
		$params = $this->getRequest()->getParams();
		$params_array = array();
		$params_array[1] = $params['depotid'];
		$params_array[2] = $params['itemid'];
		
		$result = $this->SFA_Comman->executequery('CALL sp_get_itemqtyinfo_itemcode(?,?)',$params_array,'');		
		//echo Zend_Json::encode($result);
		echo Zend_Json_Encoder::encode($result);
		exit;		
	}
}