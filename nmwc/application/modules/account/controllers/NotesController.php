<?php
/**
* @name       NotesController
* @since
* @version    Release: 1
* @author     HD <hiren.d@elantechnologies.com>
* @copyright  Elan Technologies
* @param
*
* This controller is Debit and credit Notes
*/
class Account_NotesController extends Account_Library_Controller_Action_Abstract
{
    public $sec_lang 	= '';
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
		$this->view->colan		= $this->translate->_('Colan');
	
		$this->common_model 		= new SFA_Model_Index();
		$this->SFA_Comman			= new SFA_Comman();
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
    * @name       debitcustomerAction
    * @since      25-01-2012
    * @version    Release: 1
    * @author     HD <hiren.d@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This action is use for debit customer
    */
    public function debitcustomerAction()
    {
		$this->view->params = $params = $this->getRequest()->getParams();
        $this->view->formdata = $formdata = $this->_request->getPost();
		
		
		if($params['delid'] > 0)
		{
			$ids = $params['delid'];
			
			$param_array 	= array();
			$param_array[1]	= $ids;
			$param_array[2]	= $this->currentUser->username;
			
			$result 	= $this->SFA_Comman->executequery('CALL sp_delete_account_note_debitnotecustomer(?,?)',$param_array,'');
			
			if($result[0][0]['result'] == 'Not Found') {
				SFA_Message::setErrorMsg($this->translate->_('Record Already In use'));
			} else {
				SFA_Message::setMsg($this->translate->_('Delete Record'));	
			}
			$this->_helper->redirector("debitcustomer", "notes", "account");
		}

		if($formdata["hdDelete"]==1)
		{
			$ids = implode(',',$formdata['chk']);
			
			$param_array 	= array();
			$param_array[1]	= $ids;
			$param_array[2]	= $this->currentUser->username;
			
			$result 	= $this->SFA_Comman->executequery('CALL sp_delete_account_note_debitnotecustomer(?,?)',$param_array,'');
			
			if($result[0][0]['deleted_id'] =='')
			{
				$ids		= explode(',',$ids);
				$checked 	= $ids;
				
				SFA_Message::setErrorMsg($this->translate->_('Record Already In use'));
			}
			else
			{
				$deleted_id 	= explode(',',$result[0][0]['deleted_id']);
				$ids			= explode(',',$ids);
				$checked 		= array_diff($ids,$deleted_id);
				
				if(count($ids) != count($deleted_id)){
					SFA_Message::setErrorMsg($this->translate->_('Record Already In use'));
				}
				
				SFA_Message::setMsg($this->translate->_('Delete Record'));
				
				$this->_helper->redirector("debitcustomer", "notes", "account");
			}
		}
		
		$last_url = htmlspecialchars($_SERVER['HTTP_REFERER']);
		
		$Common_NameSpace = new Zend_Session_Namespace('DebitNote_Customer');		
		if($formdata['btnreset'] == 'RESET')
		{
			$formdata["txtdate"] 	= '';
			$Common_NameSpace->tdate	= '';
		}		
		if(strpos($last_url,'debitcustomer'))
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
			$additional_where_condition[] = " (dcah.transactiondate BETWEEN \'".date("Y-m-d 00:00:00",strtotime(str_replace('/', '-', $Common_NameSpace->tdate)))."\' AND \'".date("Y-m-d 23:59:59",strtotime(str_replace('/', '-', $Common_NameSpace->tdate)))."\' )";
		
		// For Alternate Code Status.
		$cpanel				= $this->SFA_Comman->getaltcodestatus();
		$altcode_status		= $cpanel["Use Alternate Code"]['status'];
		
		if($altcode_status) {
			$cols_array = array('documentnumber','routename','salesmanname1','cm.alternatecode','cm.customername','FORMAT(amountpaid,'.$this->decimalplaces.') AS amountpaid','dcah.transactionkey as edit_del_primary_id');
		}
		else {
			$cols_array = array('documentnumber','routename','salesmanname1','dcah.customercode','cm.customername','FORMAT(amountpaid,'.$this->decimalplaces.') AS amountpaid','dcah.transactionkey as edit_del_primary_id');
		}
		$columns_show =  array($this->translate->_('Document Number'),$this->translate->_('Route'),$this->translate->_('Salesman'),$this->translate->_('Customer Code'),$this->translate->_('Customer Name'),$this->translate->_('Amount'));
		
		if($this->css == 'ar_') {
			$cols_array[1]	= 'arbroutename';
			$cols_array[2]	= 'arbsalesmanname1';
			$cols_array[4]	= 'arbcustoemrname';
		}
			
		// prepare the configuration for grid
		$pagingparams = array(
								"show_grid_heading" => true,
								"grid_heading_message" => $this->translate->_('Overview'),
								"show_records_per_page" => isset($formdata["show_records_per_page"])?$formdata["show_records_per_page"]:10,
								"show_searchbox" => true,
								"searchbox_value" => isset($formdata["grid_search_text"])?$formdata["grid_search_text"]:"",
								"pagename" => $this->translate->_('Debit Customer'),
								"show_selectbox" => true,
								"show_editlink" => false,
								"show_deletelink" => false,
								"show_deleteall" => false,
								"primaryid" => "dcah.transactionkey",								
								"show_extralink" => true,
								"extralink" => array(array("View","/".$params['module']."/".$params['controller']."/adddebitcustomer/id/#pattern#/view/yes/","#pattern#")),
								"nodata_message" => $this->translate->_('No Record(s) Found'),
								"fetch_columns_inquery" => $cols_array,
								"show_columns" => $columns_show,
								"show_columns_right_side"=>array('amountpaid'),
								"show_header_right_side"=>array($this->translate->_('Amount')),
								"additional_where" => $additional_where_condition
							);
		
		// create grid class object
		$pagingshow = new SFA_Paging($pagingparams);
		
		// call common function of grid class
		$get_return_vals = $pagingshow->commnfunc();
		
		//print_r($get_return_vals['where_condition']);
		
		// call the stored procedure for fetch the data   		
		$param_array      = array();
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
		$result = $this->SFA_Comman->executequery('CALL sp_get_account_notes_debitnotecustomer(?,?,?,?,?,?,?,?)',$param_array,'',$downloadCSV,$printData,2,$pagingparams);
		$data_arr["count"] 		= $result[0][0]['counter'];
		$data_arr["data"][0] 	= $result[1];
		
		// pass the data in summary_showdatagrid() function & create a final variable for view
		$this->view->pagegridshow =  $pagingshow->summary_showdatagrid($data_arr);
		
		$this->view->addScriptPath(APPLICATION_PATH."/theme/default/datagrid/");
		
    }
    /**
    * @name       adddebitcustomerAction
    * @since      26-01-2012
    * @version    Release: 1
    * @author     HD <hiren.d@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This action is use for add debit customer
    */
    public function adddebitcustomerAction()
    {
		$this->view->params = $params = $this->getRequest()->getParams();
        $this->view->formdata = $formdata = $this->_request->getPost();
		
		$Common_NameSpace = new Zend_Session_Namespace('DebitNote_Customer');
	
		$sel_date = $Common_NameSpace->tdate;
	
		// ADD DATE VALUE IN SESSION
		if($sel_date != '') {
			$Common_NameSpace->tdate 	= $sel_date;
			$this->view->date		= $sel_date;
		}
		else
		{
			$Common_NameSpace->tdate 	= date('d-m-Y');
			$this->view->date		= date('d-m-Y');
		}
		
		if(count($formdata) > 0)
		{
			$param_array 		= array();
			$param_array[1]		= $formdata['ddlroute'];
			$param_array[2]		= $formdata['hdnsalesman'];
			$param_array[3]		= $formdata['ddlcustomer'];
			$param_array[4]		= '2';
			$param_array[5]		= str_replace(',','',$formdata['txtamount']);
			$param_array[6]		= $formdata['txtremark1'];
			$param_array[7]		= $formdata['txtremark2'];
			$param_array[8]		= $formdata['ddlinvo_num'];
			$param_array[9] 	= $Common_NameSpace->tdate;
			$param_array[10]	= $this->currentUser->username;
			$param_array[11]	= $formdata['txterprefnum'];
			
			$result = $this->SFA_Comman->executequery('CALL sp_add_account_notes_adddebitnotecustomer(?,?,?,?,?,?,?,?,?,?,?)',$param_array,'');
			SFA_Message::setMsg($this->translate->_('New Record'));			
			$this->_helper->redirector('debitcustomer', 'notes', 'account');
		}
		elseif($params['id'] > 0)
		{
			$param_array 	= array();
			$param_array[1]	= $params['id'];
			$param_array[2]	= $Common_NameSpace->tdate;
			
			$result = $this->SFA_Comman->executequery('CALL sp_get_account_notes_adddebitcustomer(?,?)',$param_array,'');
			$this->view->route 		= $result[0];			
			$this->view->formdata 	= $result[1][0];
			$this->view->formdata['hdnmaxid']	= $params['id'];
		}
		else
		{
			$param_array 	= array();
			$param_array[1]	= '0';
			$param_array[2]	= $Common_NameSpace->tdate;
			
			$result = $this->SFA_Comman->executequery('CALL sp_get_account_notes_adddebitcustomer(?,?)',$param_array,'');
			$this->view->route 		= $result[0];			
			$this->view->formdata['hdnmaxid']	= ($result[2][0]['Auto_increment'] > 0) ? $result[2][0]['Auto_increment'] : 1;
		}
    }
    /**
    * @name       debitrouteAction
    * @since      25-01-2012
    * @version    Release: 1
    * @author     HD <hiren.d@elantechnologies.com>
    * @author     GP <gayatri@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This action is use for debit route
    * Add show column right side array(show_columns_right_side )
    */
    public function debitrouteAction()
    {
		$this->view->params = $params = $this->getRequest()->getParams();
        $this->view->formdata = $formdata = $this->_request->getPost();
		
		if($params['delid'] > 0)
		{
			$ids = $params['delid'];
			
			$param_array 	= array();
			$param_array[1]	= $ids;
			$param_array[2]	= $this->currentUser->username;
			
			$result 	= $this->SFA_Comman->executequery('CALL sp_delete_account_note_debitnoteroute(?,?)',$param_array,'');
			
			if($result[0][0]['result'] == 'Not Found') {
				SFA_Message::setErrorMsg($this->translate->_('Record Already In use'));
			} else {
				SFA_Message::setMsg($this->translate->_('Delete Record'));	
			}
			$this->_helper->redirector("debitroute", "notes", "account");
		}

		if($formdata["hdDelete"]==1)
		{
		
			$ids = implode(',',$formdata['chk']);		
				
			$param_array 	= array();
			$param_array[1]	= $ids;
			$param_array[2]	= $this->currentUser->username;
			
			$result 	= $this->SFA_Comman->executequery('CALL sp_delete_account_note_debitnoteroute(?,?)',$param_array,'');
			
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
				
				$this->_helper->redirector("debitroute", "notes", "account");
			}
		}
		
		$last_url = htmlspecialchars($_SERVER['HTTP_REFERER']);
		
		$Common_NameSpace = new Zend_Session_Namespace('DebitNote_Route');		
		if($formdata['btnreset'] == 'RESET')
		{
			$formdata["txtdate"] 	= '';
			$Common_NameSpace->tdate	= '';
		}		
		if(strpos($last_url,'debitroute'))
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
		
		$cols_array = array('invoicenumber','documentnumber','routename','salesmanname1','FORMAT(amountpaid,'.$this->decimalplaces.') AS amountpaid','transactionkey as edit_del_primary_id');
		$columns_show =  array($this->translate->_('Invoice Number'),$this->translate->_('Document Number'),$this->translate->_('Route'),$this->translate->_('Salesman'),$this->translate->_('Amount'));
		
		if($this->css == 'ar_') {
			$cols_array[2]	= 'arbroutename';
			$cols_array[3]	= 'arbsalesmanname1';
		}
		$notinsearch  	= array();
		$notinsearch[] 	= 'FORMAT(amountpaid,'.$this->decimalplaces.') AS amountpaid';
	
		// prepare the configuration for grid
		$pagingparams = array(
								"show_grid_heading" => true,
								"grid_heading_message" => $this->translate->_('Overview'),
								"show_records_per_page" => isset($formdata["show_records_per_page"])?$formdata["show_records_per_page"]:10,
								"show_searchbox" => true,
								"pagename" => $this->translate->_('Debit Route'),
								"searchbox_value" => isset($formdata["grid_search_text"])?$formdata["grid_search_text"]:"",
								"show_selectbox" => true,
								"show_editlink" => false,
								"show_deletelink" => false,
								"show_deleteall" => false,
								"primaryid" => "transactionkey",
								"no_search_fields" => $notinsearch,								
								"show_extralink" => true,
								"extralink" => array(array("View","/".$params['module']."/".$params['controller']."/adddebitroute/id/#pattern#/view/yes/","#pattern#")),
								"nodata_message" => $this->translate->_('No Record(s) Found'),
								"fetch_columns_inquery" => $cols_array,
								"show_columns" => $columns_show,
								"show_columns_right_side"=>array('amountpaid'),
								"show_header_right_side"=>array($this->translate->_('Amount')),
								"additional_where" => $additional_where_condition
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
		
		$downloadCSV = (isset($formdata['downloadcsv'])) ? $formdata['downloadcsv'] : $params['downloadcsv'];
    
    // Hiren Dave on 19 Nov, 2012 - START - to check if need to print data
	$printData = (isset($formdata['printData'])) ? $formdata['printData'] : $params['printData'];
		// called stored procedure for counter
		$result = $this->SFA_Comman->executequery('CALL sp_get_account_notes_debitnoteroute(?,?,?,?,?,?,?,?)',$param_array,'',$downloadCSV,$printData,2,$pagingparams);
		$data_arr["count"] 		= $result[0][0]['counter'];
		$data_arr["data"][0] 	= $result[1];
		
		// pass the data in summary_showdatagrid() function & create a final variable for view
		$this->view->pagegridshow =  $pagingshow->summary_showdatagrid($data_arr);
		
		$this->view->addScriptPath(APPLICATION_PATH."/theme/default/datagrid/");
    }
    /**
    * @name       adddebitrouteAction
    * @since      26-01-2012
    * @version    Release: 1
    * @author     HD <hiren.d@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This action is use for add debit route
    */
    public function adddebitrouteAction()
    {
		$this->view->params = $params = $this->getRequest()->getParams();
        $this->view->formdata = $formdata = $this->_request->getPost();
		
		$Common_NameSpace = new Zend_Session_Namespace('DebitNote_Route');
	
		$sel_date = $Common_NameSpace->tdate;
	
		// ADD DATE VALUE IN SESSION
		if($sel_date != '') {
			$Common_NameSpace->tdate 	= $sel_date;
			$this->view->date		= $sel_date;
		}
		else
		{
			$Common_NameSpace->tdate 	= date('d-m-Y');
			$this->view->date		= date('d-m-Y');
		}

		if(count($formdata) > 0)
		{
			$param_array 	= array();			
			$param_array[1]	= $formdata['ddlroute'];
			$param_array[2]	= $formdata['hdnsalesman'];			
			$param_array[3]	= '2';
			$param_array[4]	= str_replace(',','',$formdata['txtamount']);
			$param_array[5]	= $formdata['txtremark1'];
			$param_array[6]	= $formdata['txtremark2'];
			$param_array[7] = $Common_NameSpace->tdate;
			$param_array[8]	= $this->currentUser->username;
			$param_array[9]	= $formdata['txterprefnum'];
			
			$result = $this->SFA_Comman->executequery('CALL sp_add_account_notes_adddebitnoteroute(?,?,?,?,?,?,?,?,?)',$param_array,'');
			SFA_Message::setMsg($this->translate->_('New Record'));			
			$this->_helper->redirector('debitroute', 'notes', 'account');
		}
		elseif($params['id'] > 0)
		{
			$result = $this->SFA_Comman->executequery('CALL sp_get_account_notes_adddebitnoteroute(?)',$params['id'],'');
			$this->view->route 		= $result[0];
			$this->view->formdata 	= $result[1][0];
			$this->view->formdata['hdnmaxid']	= $params['id'];
		}
		else
		{
			$result = $this->SFA_Comman->executequery('CALL sp_get_account_notes_adddebitnoteroute(?)','0','');
			$this->view->route 		= $result[0];
			$this->view->formdata['hdnmaxid']	= ($result[2][0]['Auto_increment'] > 0) ? $result[2][0]['Auto_increment'] : 1;
		}	
    }
    /**
    * @name       creditcustomerAction
    * @since      27-01-2012
    * @version    Release: 1
    * @author     HD <hiren.d@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This action is use for the display credit customer
    */
    public function creditcustomerAction()
    {
		$this->view->params = $params = $this->getRequest()->getParams();
        $this->view->formdata = $formdata = $this->_request->getPost();
		
		if($params['delid'] > 0)
		{
			$ids = $params['delid'];
			
			$param_array 	= array();
			$param_array[1]	= $ids;
			$param_array[2]	= $this->currentUser->username;
			
			$result 	= $this->SFA_Comman->executequery('CALL sp_delete_account_notes_creditnotecustomer(?,?)',$param_array,'');
			
			if($result[0][0]['result'] == 'Not Found') {
				SFA_Message::setErrorMsg($this->translate->_('Record Already In use'));
			} else {
				SFA_Message::setMsg($this->translate->_('Delete Record'));	
			}
			$this->_helper->redirector("creditcustomer", "notes", "account");
		}

		if($formdata["hdDelete"]==1)
		{
			
			$ids = implode(',',$formdata['chk']);
				
			$param_array 	= array();
			$param_array[1]	= $ids;
			$param_array[2]	= $this->currentUser->username;
			
			$result 	= $this->SFA_Comman->executequery('CALL sp_delete_account_notes_creditnotecustomer(?,?)',$param_array,'');
			
			if($result[0][0]['deleted_id'] =='')
			{
				$ids		= explode(',',$ids);
				$checked 	= $ids;
				
				SFA_Message::setErrorMsg($this->translate->_('Record Already In use'));
			}
			else
			{
				$deleted_id 	= explode(',',$result[0][0]['deleted_id']);
				$ids			= explode(',',$ids);
				$checked 		= array_diff($ids,$deleted_id);
				
				if(count($ids) != count($deleted_id)){
					SFA_Message::setErrorMsg($this->translate->_('Record Already In use'));
				}
				
				SFA_Message::setMsg($this->translate->_('Delete Record'));
				
				$this->_helper->redirector("creditcustomer", "notes", "account");
			}
		}				
				
		$Common_NameSpace = new Zend_Session_Namespace('CreditNote_Customer');
		if($formdata['btnreset'] == 'RESET')
		{
			$formdata["txtdate"] 	= '';
			$Common_NameSpace->tdate	= '';
		}
		$last_url = htmlspecialchars($_SERVER['HTTP_REFERER']);
		if(strpos($last_url,'creditcustomer'))
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
		
		// For Alternate Code Status.
		$cpanel				= $this->SFA_Comman->getaltcodestatus();
		$altcode_status		= $cpanel["Use Alternate Code"]['status'];
		
		if($altcode_status) {
			$cols_array = array('documentnumber','routename','cm.alternatecode','cm.customername','FORMAT(totalinvoiceamount,'.$this->decimalplaces.') AS totalinvoiceamount','FORMAT(amountpaid,'.$this->decimalplaces.') AS amountpaid','transactionkey as edit_del_primary_id');
		}
		else {
			$cols_array = array('documentnumber','routename','dcah.alternatecode','cm.customername','FORMAT(totalinvoiceamount,'.$this->decimalplaces.') AS totalinvoiceamount','FORMAT(amountpaid,'.$this->decimalplaces.') AS amountpaid','transactionkey as edit_del_primary_id');
		}
		$columns_show =  array($this->translate->_('Document No'),$this->translate->_('Route Name'),$this->translate->_('Customer Code'),$this->translate->_('Customer Name'),$this->translate->_('Invoice Amount'),$this->translate->_('Amount Paid'));
		
		if($this->css == 'ar_') {
			$cols_array[1]	= 'arbroutename';
			$cols_array[3]	= 'arbcustomername';
		}
	
		$notinsearch  	= array();
		$notinsearch[] 	= 'FORMAT(totalinvoiceamount,'.$this->decimalplaces.') AS totalinvoiceamount';
		$notinsearch[] 	= 'FORMAT(amountpaid,'.$this->decimalplaces.') AS amountpaid';
		$notinsearch[] 	= 'FORMAT(invoicebalance,'.$this->decimalplaces.') AS invoicebalance';
		
		// prepare the configuration for grid
		$pagingparams = array(
								"show_grid_heading" => true,
								"grid_heading_message" => $this->translate->_('Overview'),
								"show_records_per_page" => isset($formdata["show_records_per_page"])?$formdata["show_records_per_page"]:10,
								"show_searchbox" => true,
								"searchbox_value" => isset($formdata["grid_search_text"])?$formdata["grid_search_text"]:"",
								"pagename" => $this->translate->_('Credit Customer'),
								"show_selectbox" => true,
								"show_editlink" => false,
								"show_deletelink" => false,			
								"show_deleteall" => false,
								"primaryid" => "transactionkey",
								"no_search_fields" => $notinsearch,
								"show_extralink" => true,
								"extralink" => array(array("View","/".$params['module']."/".$params['controller']."/addcreditcustomer/id/#pattern#/view/yes/","#pattern#")),
								"nodata_message" => $this->translate->_('No Record(s) Found'),
								"fetch_columns_inquery" => $cols_array,
								"show_columns" => $columns_show,
								"show_header_right_side"=>array($this->translate->_('Invoice Amount'),$this->translate->_('Amount Paid')),
								"show_columns_right_side"=>array('totalinvoiceamount','amountpaid','invoicebalance'),
								"additional_where" => $additional_where_condition
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
		
		$downloadCSV = (isset($formdata['downloadcsv'])) ? $formdata['downloadcsv'] : $params['downloadcsv'];
    
    // Hiren Dave on 19 Nov, 2012 - START - to check if need to print data
	$printData = (isset($formdata['printData'])) ? $formdata['printData'] : $params['printData'];
		// called stored procedure for counter
		$result = $this->SFA_Comman->executequery('CALL sp_get_account_notes_creditnotecustomer(?,?,?,?,?,?,?,?)',$param_array,'',$downloadCSV,$printData,2,$pagingparams);
		$data_arr["count"] 		= $result[0][0]['counter'];
		$data_arr["data"][0] 	= $result[1];

		// pass the data in summary_showdatagrid() function & create a final variable for view
		$this->view->pagegridshow =  $pagingshow->summary_showdatagrid($data_arr);
		
		$this->view->addScriptPath(APPLICATION_PATH."/theme/default/datagrid/");
    }
    /**
    * @name       addcreditcustomerAction
    * @since      27-01-2012
    * @version    Release: 1
    * @author     HD <hiren.d@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This action is use for the add credit customer
    */
    public function addcreditcustomerAction()
    {
		$this->view->params = $params = $this->getRequest()->getParams();
        $this->view->formdata = $formdata = $this->_request->getPost();
		
		// IF EXTRA PARAMS ARE REQUIRED
		$ex_param = "";		
		if(isset($params["id"]) && $params["id"]>0) {
			$ex_param 	= "/key/".$params["id"];
			$ex_param 	.= "/view/yes";
		}
		
		$this->view->itemgrid    = $this->view->BaseUrl("/".$params['module']."/".$params['controller']."/creditcustomergrid".$ex_param);
		
		$Common_NameSpace = new Zend_Session_Namespace('CreditNote_Customer');
	
		$sel_date = $Common_NameSpace->tdate;
	
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
		
		$ttype = array();
		$ttype[0]['id']  = 0;
		$ttype[0]['val'] = 'Cash';
		$ttype[1]['id']  = 1;
		$ttype[1]['val'] = 'Cheque';
		$this->view->payment_mode = $ttype;

		
		if(count($formdata) > 0)
		{
			$param_array 		= array();
			$param_array[1]		= $formdata['ddlroute'];
			$param_array[2]		= $formdata['hdnsalesman'];
			$param_array[3]		= $formdata['ddlcustomer'];
			$param_array[4]		= '4';
			$param_array[5]		= str_replace(',','',$formdata['hdntxtamount']);
			$param_array[6]		= str_replace(',','',$formdata['hdntotalinvoamt']);
			$param_array[7]		= $formdata['txtremark1'];
			$param_array[8]		= $formdata['txtremark2'];
			$param_array[9]		= $formdata['hdninvono'];
			$param_array[10]	= $formdata['hdncheckinvo'];
			$param_array[11]	= (!$formdata['ddlpaymode']) ? 0 : 1;
			$param_array[12]	= $formdata['txtcheckno'];
			$param_array[13]	= $formdata['txtcheckdt'];
			$param_array[14]	= $formdata['ddlbankname'];
			$param_array[15] 	= $Common_NameSpace->tdate;
			$param_array[16]	= $this->currentUser->username;
			$param_array[17]	= $formdata['txterprefnum'];
			$param_array[18]	= $formdata['hdninvoamt'];
			$param_array[19]	= $formdata['chkfirstout'];			
			
			$result = $this->SFA_Comman->executequery('CALL sp_add_account_notes_addcreditnotecustomer(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',$param_array,'');
			SFA_Message::setMsg($this->translate->_('New Record'));			
			$this->_helper->redirector('creditcustomer', 'notes', 'account');
		}
		elseif($params['id'] > 0)
		{
			$param_array 		= array();
			$param_array[1]		= $params['id'];
			//$param_array[2]	= $Common_NameSpace->tdate;
			
			$result = $this->SFA_Comman->executequery('CALL sp_get_account_notes_addcreditnotecustomer(?)',$param_array,'');
			$this->view->route 			= $result[0];
			$this->view->bank 			= $result[1];
			$this->view->formdata 		= $result[2][0];
			$this->view->paymentdata	= $result[3][0];
			$this->view->formdata['hdnmaxid']	= $params['id'];
		}
		else
		{
			$param_array 	= array();
			$param_array[1]	= '0';
			//$param_array[2]	= $Common_NameSpace->tdate;
			
			$result = $this->SFA_Comman->executequery('CALL sp_get_account_notes_addcreditnotecustomer(?)',$param_array,'');
			$this->view->route 	= $result[0];
			$this->view->bank 	= $result[1];
			$this->view->formdata['hdnmaxid']	= ($result[2][0]['Auto_increment'] > 0) ? $result[2][0]['Auto_increment'] : 1;
		}
    }
	

     /**
    * @name       creditcustomergridAction
    * @since      27-01-2012
    * @version    Release: 1
    * @author     HD <hiren.d@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This action is use for the credit customer
    */
    public function creditcustomergridAction() {
		
        $this->view->params = $params = $this->getRequest()->getParams();
		
		if(isset($params["view"]) && $params["view"] == 0) {
			$columns_array 	= array('t1.invoicenumber','DATE_FORMAT(t1.invoicedate,"%d-%m-%Y") AS trandate','FORMAT(t1.totalinvoiceamount,'.$this->decimalplaces.') as totalinvoiceamount','FORMAT(t1.amountpaid,'.$this->decimalplaces.') as amountpaid','FORMAT(t2.invoicebalance,'.$this->decimalplaces.') as invoicebalance','FORMAT(pdcbalance,'.$this->decimalplaces.') as pdcbalance','t1.transactionkey as edit_del_primary_id');
			$columns_show  	= array($this->translate->_('Invoice No'),$this->translate->_('Invoice Date'),$this->translate->_('Invoice Amount'),$this->translate->_('Amount Paid'),$this->translate->_('Present Balance'),$this->translate->_('PDC Balance'));
		}
		else {
			$columns_array 	= array('t1.invoicenumber','DATE_FORMAT(t1.transactiondate,"%d-%m-%Y") AS trandate','FORMAT(t1.totalinvoiceamount,'.$this->decimalplaces.') as totalinvoiceamount','FORMAT(t1.amountpaid,'.$this->decimalplaces.') as amountpaid','FORMAT(t1.invoicebalance,'.$this->decimalplaces.') as invoicebalance','FORMAT(pdcbalance,'.$this->decimalplaces.') as pdcbalance','t1.transactionkey as edit_del_primary_id');
			$columns_show  	= array($this->translate->_('Invoice No'),$this->translate->_('Invoice Date'),$this->translate->_('Invoice Amount'),$this->translate->_('Amount Paid'),$this->translate->_('Present Balance'),$this->translate->_('PDC Balance'));
		}

		// IF EXTRA PARAMS ARE REQUIRED
		$ex_param = "";
		$additional_where_condition = array();
		if(isset($params["key1"]) && $params["key1"]>0) {
			$ex_param = "/key1/".$params["key1"]."/key2/".$params["key2"];
			$additional_where_condition[] = ' ( t1.customercode = "'.$params["key2"].'") ';
			$additional_where_condition[] = ' ( t1.invoicebalance <> 0 ) ';
		}
		
		if(isset($params["view"]) && $params["view"] == 0) {
			$additional_where_condition[]  = ' (t1.transactionkey = "'.$params["key"].'") ';
			$additional_where_condition[1] = ' 1 = 1 ';
		}
	
		// ARRAY FOR GRID PAGINATION
		$pagingparams = array(
					"show_grid_heading" => false,
					"grid_heading_message" => $this->translate->_('Overview'),
					"show_records_per_page" => isset($formdata["show_records_per_page"])?$formdata["show_records_per_page"]:5000,
					"show_searchbox" => false,
					"show_selectbox" => true,
					"show_editlink" => false,
					"show_deletelink" => false,
					"show_deleteall" => false,
					"show_extratextbox" => true,
					"show_datasorting" => '1',
					"primaryid" => "transactionkey",
					"currentlink" => array("/".$params['module']."/".$params['controller']."/".$params['action'].$ex_param),
					"nodata_message" => $this->translate->_('No Record(s) Found'),
					"fetch_columns_inquery" => $columns_array,
					"show_columns" => $columns_show,
					"show_columns_right_side"=>array('totalinvoiceamount','amountpaid','invoicebalance','pdcbalance'),
					"show_header_right_side"=>array($this->translate->_('Invoice Amount'),$this->translate->_('Amount Paid'),$this->translate->_('Present Balance'),$this->translate->_('Present Balance'),$this->translate->_('PDC Balance')),
					"additional_where" => $additional_where_condition,
				);		
		// for disable textbox in grid
		if(isset($params["view"]) && $params["view"] == 0) {
			$pagingparams['show_extratextbox'] 	= 0;
			$pagingparams['show_selectbox'] 	= false;
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
		if(isset($params["view"]) && $params["view"] == 0) {
			$result = $this->SFA_Comman->executequery('CALL sp_get_account_notes_addcreditcustomerinvogrid(?,?,?,?,?,?)',$param_array,'');
		}else {
			$result = $this->SFA_Comman->executequery('CALL sp_get_account_notes_addcreditcustomergrid(?,?,?,?,?,?,?)',$param_array,'');    
		}
		
		$data_arr["count"] 		= $result[0][0]['counter'];	
		$data_arr["data"][0] 	= $result[1];
		
		if($data_arr["count"] > 0 && (!isset($params["view"]))) {
			echo '<script type="text/javascript">$("#showbtn").show();</script>';
		}
		else {
			echo '<script type="text/javascript">$("#showbtn").hide();</script>';
		}
		
		$this->view->pagegridshow =  $pagingshow->summary_showdatagrid($data_arr);
		$this->view->addScriptPath(APPLICATION_PATH."/theme/default/datagrid/");
	
		$this->render("ajaxgrid");
    }	
	/**
    * @name       getinvoicetotal
    * @since      09-08-2012
    * @version    Release: 1
    * @author     HD <hiren.d@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param   	
    *
    * This is the function for getting invoice total
    */
	public function getinvoicetotalAction()
	{
		$params = $this->getRequest()->getParams();
		$ids = str_replace('$',',',$params['invoiceid']);		
		$result = $this->SFA_Comman->executequery('CALL sp_get_account_notes_invoicetotal(?)',$ids,'');
		$res[0] = number_format($result[0][0]['invoiceamt'],$this->decimalplaces);
		$res[1] = number_format($result[0][0]['amountpaid'],$this->decimalplaces);
		$res[2] = number_format($result[0][0]['balanceamt'],$this->decimalplaces);
		echo Zend_Json::encode($res);
		exit;
	}

    /**
    * @name       creditrouteAction
    * @since      27-01-2012
    * @version    Release: 1
    * @author     HD <hiren.d@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This action is use for the display credit route
    */
    public function creditrouteAction()
    {
		$this->view->params = $params = $this->getRequest()->getParams();
        $this->view->formdata = $formdata = $this->_request->getPost();
		
		if($params['delid'] > 0)
		{
			$ids = $params['delid'];
			
			$param_array 	= array();
			$param_array[1]	= $ids;
			$param_array[2]	= $this->currentUser->username;
			
			$result 	= $this->SFA_Comman->executequery('CALL sp_delete_account_notes_creditnoteroute(?,?)',$param_array,'');
			
			if($result[0][0]['result'] == 'Not Found') {
				SFA_Message::setErrorMsg($this->translate->_('Record Already In use'));
			} else {
				SFA_Message::setMsg($this->translate->_('Delete Record'));	
			}
			$this->_helper->redirector("creditroute", "notes", "account");
		}

		if($formdata["hdDelete"]==1)
		{
			
			$ids = implode(',',$formdata['chk']);
			
			$param_array 	= array();
			$param_array[1]	= $ids;
			$param_array[2]	= $this->currentUser->username;
			
			$result 	= $this->SFA_Comman->executequery('CALL sp_delete_account_notes_creditnoteroute(?,?)',$param_array,'');
			
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
				
				$this->_helper->redirector("creditroute", "notes", "account");
			}
		}
		
		$last_url = htmlspecialchars($_SERVER['HTTP_REFERER']);
		
		$Common_NameSpace = new Zend_Session_Namespace('CreditNote_Route');		
		if($formdata['btnreset'] == 'RESET')
		{
			$formdata["txtdate"] 	= '';
			$Common_NameSpace->tdate	= '';
		}		
		if(strpos($last_url,'creditroute'))
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
		
		
		$cols_array = array('invoicenumber','documentnumber','routename','salesmanname1','FORMAT(amountpaid,'.$this->decimalplaces.') AS amountpaid','transactionkey as edit_del_primary_id');
		$columns_show =  array($this->translate->_('Invoice Number'),$this->translate->_('Document Number'),$this->translate->_('Route'),$this->translate->_('Salesman'),$this->translate->_('Amount'));
		
		$notinsearch  	= array();
		$notinsearch[] 	= 'FORMAT(amountpaid,'.$this->decimalplaces.') AS amountpaid';
		
		if($this->css == 'ar_') {
			$cols_array[2]	= 'arbroutename';
			$cols_array[3]	= 'arbsalesmanname1';
		}
	
		// prepare the configuration for grid
		$pagingparams = array(
								"show_grid_heading" => true,
								"grid_heading_message" => $this->translate->_('Overview'),
								"show_records_per_page" => isset($formdata["show_records_per_page"])?$formdata["show_records_per_page"]:10,
								"show_searchbox" => true,
								"searchbox_value" => isset($formdata["grid_search_text"])?$formdata["grid_search_text"]:"",
								"pagename" => $this->translate->_('Credit Route'),
								"show_selectbox" => true,
								"show_editlink" => false,
								"show_deletelink" => false,			
								"show_deleteall" => false,
								"primaryid" => "transactionkey",
								"no_search_fields" => $notinsearch,
								"show_extralink" => true,
								"extralink" => array(array("View","/".$params['module']."/".$params['controller']."/addcreditroute/id/#pattern#/view/yes/","#pattern#")),
								"nodata_message" => $this->translate->_('No Record(s) Found'),
								"fetch_columns_inquery" => $cols_array,
								"show_columns" => $columns_show,
								"show_columns_right_side"=>array('amountpaid'),
								"show_header_right_side"=>array($this->translate->_('Amount')),
								"additional_where" => $additional_where_condition
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
		
		$downloadCSV = (isset($formdata['downloadcsv'])) ? $formdata['downloadcsv'] : $params['downloadcsv'];
    
    // Hiren Dave on 19 Nov, 2012 - START - to check if need to print data
	$printData = (isset($formdata['printData'])) ? $formdata['printData'] : $params['printData'];
		// called stored procedure for counter
		$result = $this->SFA_Comman->executequery('CALL sp_get_account_notes_creditnoteroute(?,?,?,?,?,?,?,?)',$param_array,'',$downloadCSV,$printData,2,$pagingparams);
		$data_arr["count"] 		= $result[0][0]['counter'];
		$data_arr["data"][0] 	= $result[1];
		
		// pass the data in summary_showdatagrid() function & create a final variable for view
		$this->view->pagegridshow =  $pagingshow->summary_showdatagrid($data_arr);
		
		$this->view->addScriptPath(APPLICATION_PATH."/theme/default/datagrid/");
    }
    /**
    * @name       addcreditrouteAction
    * @since      27-01-2012
    * @version    Release: 1
    * @author     HD <hiren.d@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This action is use for the add credit route
    */
    public function addcreditrouteAction()
    {
		$this->view->params = $params = $this->getRequest()->getParams();
        $this->view->formdata = $formdata = $this->_request->getPost();
		
		$Common_NameSpace = new Zend_Session_Namespace('CreditNote_Route');
	
		$sel_date = $Common_NameSpace->tdate;
	
		// ADD DATE VALUE IN SESSION
		if($sel_date != '') {
			$Common_NameSpace->tdate 	= $sel_date;
			$this->view->date		= $sel_date;
		}
		else
		{
			$Common_NameSpace->tdate 	= date('d-m-Y');
			$this->view->date		= date('d-m-Y');
		}

		
		if(count($formdata) > 0)
		{
			$param_array 	= array();			
			$param_array[1]	= $formdata['ddlroute'];
			$param_array[2]	= $formdata['hdnsalesman'];			
			$param_array[3]	= '4';
			$param_array[4]	= str_replace(',','',$formdata['txtamount']);
			$param_array[5]	= $formdata['txtremark1'];
			$param_array[6]	= $formdata['txtremark2'];
			$param_array[7] = $Common_NameSpace->tdate;
			$param_array[8]	= $this->currentUser->username;
			$param_array[9]	= $formdata['txterprefnum'];
			
			$result = $this->SFA_Comman->executequery('CALL sp_add_account_notes_addcreditnoteroute(?,?,?,?,?,?,?,?,?)',$param_array,'');
			SFA_Message::setMsg($this->translate->_('New Record'));			
			$this->_helper->redirector('creditroute', 'notes', 'account');
		}
		elseif($params['id'] > 0)
		{
			$result = $this->SFA_Comman->executequery('CALL sp_get_account_notes_addcreditnoteroute(?)',$params['id'],'');
			
			$this->view->route 		= $result[0];
			$this->view->formdata 	= $result[1][0];
			$this->view->formdata['hdnmaxid']	= $params['id'];
		}
		else
		{
			$result = $this->SFA_Comman->executequery('CALL sp_get_account_notes_addcreditnoteroute(?)','0','');
			$this->view->route 		= $result[0];
			$this->view->formdata['hdnmaxid']	= ($result[2][0]['Auto_increment'] > 0) ? $result[2][0]['Auto_increment'] : 1;
		}
    }
}