<?php
/**
* @name       SettlementController
* @since
* @version    Release: 1
* @author     HD <hiren.d@elantechnologies.com>
* @copyright  Elan Technologies
* @param
*
* This controller is settlement
*/
class Account_SettlementController extends Account_Library_Controller_Action_Abstract
{

    public $common_model = '';
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
    * @name       cashreceiptAction
    * @since      27-01-2012
    * @version    Release: 1
    * @author     HD <hiren.d@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This action is use for the display cash receipt
    */
    public function cashreceiptAction()
    {
		$this->view->params = $params = $this->getRequest()->getParams();
        $this->view->formdata = $formdata = $this->_request->getPost();
		
		if($params['delid'] > 0)
		{
			$ids = $params['delid'];
			
			$param_array 	= array();
			$param_array[1]	= $ids;
			$param_array[2]	= $this->currentUser->username;
			
			$result 	= $this->SFA_Comman->executequery('CALL sp_delete_account_settlement_cashreceipt(?,?)',$param_array,'');
			
			if($result[0][0]['result'] == 'Not Found') {
				$checked 	= $ids;
				SFA_Message::setErrorMsg($this->translate->_('Record Already In use'));
			} else {
				SFA_Message::setMsg($this->translate->_('Delete Record'));	
			}
			$this->_helper->redirector("cashreceipt", "settlement", "account");
		}
		
		if($formdata["hdDelete"]==1)
		{
			$ids = implode(',',$formdata['chk']);
			$param_array 	= array();
			$param_array[1]	= $ids;
			$param_array[2]	= $this->currentUser->username;
			
			$result 	= $this->SFA_Comman->executequery('CALL sp_delete_account_settlement_cashreceipt(?,?)',$param_array,'');
			//SFA_Comman::pre($result);
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
	
		$this->view->title	= $this->translate->_('Cashier Receipt');

		$cols_array 	= array('cr.salesmancode','sm.salesmanname1 AS salesmanname','cr.routecode','rm.routename AS routename','bankname','DATE_FORMAT(`date`,"%d-%m-%Y") as trandate','FORMAT(total,'.$this->decimalplaces.') AS total','cr.documentnumber AS edit_del_primary_id');
		$columns_show 	= array($this->translate->_('Salesman Code'),$this->translate->_('Salesman Name'),$this->translate->_('Route Code'),$this->translate->_('Route Name'),$this->translate->_('Bank Name'),$this->translate->_('Date'),$this->translate->_('Total'));
		
		if($this->css == 'ar_') {
			$cols_array[1]	= 'sm.arbsalesmanname1 AS salesmanname';
			$cols_array[3]	= 'arbroutename AS routecode';
			$cols_array[4]	= 'arbbankname AS bankname';
		}
		
		$Common_NameSpace = new Zend_Session_Namespace('CashierReceipt');
		if($formdata['btnreset'] == 'RESET')
		{
			$formdata["txtdate"] 	= '';
			$Common_NameSpace->tdate	= '';
		}
		$last_url = htmlspecialchars($_SERVER['HTTP_REFERER']);
		if(strpos($last_url,'addcashreceipt'))
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
			$additional_where_condition[] = " (`date` BETWEEN \'".date("Y-m-d 00:00:00",strtotime(str_replace('/', '-', $Common_NameSpace->tdate)))."\' AND \'".date("Y-m-d 23:59:59",strtotime(str_replace('/', '-', $Common_NameSpace->tdate)))."\' )";
		
		// prepare the configuration for grid
		$pagingparams = array(
				"show_grid_heading" => true,
				"grid_heading_message" => $this->translate->_('Overview'),
				"show_records_per_page" => isset($formdata["show_records_per_page"])?$formdata["show_records_per_page"]:10,
				"show_searchbox" => true,
				"searchbox_value" => isset($formdata["grid_search_text"])?$formdata["grid_search_text"]:"",
				"pagename" => $this->translate->_('Cashier Receipt'),
				"show_selectbox" => true,				
				"selected_list" => $checked,
				"show_deletelink" => false,			
				"show_deleteall" => false,
				"primaryid" => "documentnumber",
				"show_extralink" => true,
				"extralink" => array(array("View","/".$params['module']."/".$params['controller']."/addcashreceipt/id/#pattern#/view/yes/","#pattern#")),
				"nodata_message" => $this->translate->_('No Record(s) Found'),
				"fetch_columns_inquery" => $cols_array,
				"show_columns" => $columns_show,
				"show_columns_right_side" =>array('total'),
				"show_header_right_side"=>array($this->translate->_('Total')),
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
		
		$downloadCSV = (isset($formdata['downloadcsv'])) ? $formdata['downloadcsv'] : $params['downloadcsv'];
    
		// Hiren Dave on 19 Nov, 2012 - START - to check if need to print data
		$printData = (isset($formdata['printData'])) ? $formdata['printData'] : $params['printData'];
		
		// called stored procedure for counter
		$result = $this->SFA_Comman->executequery('CALL sp_get_account_settlement_cashreceipt(?,?,?,?,?,?,?,?)',$param_array,'',$downloadCSV,$printData,1,$pagingparams);
		
		$data_arr["count"] 		= $result[0][0]['counter'];
		$data_arr["data"][0] 	= $result[1];
		
		// pass the data in summary_showdatagrid() function & create a final variable for view
		$this->view->pagegridshow =  $pagingshow->summary_showdatagrid($data_arr);
		
		$this->view->addScriptPath(APPLICATION_PATH."/theme/default/datagrid/");
    }
    /**
    * @name       addcashreceiptAction
    * @since      27-01-2012
    * @version    Release: 1
    * @author     HD <hiren.d@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This action is use for the add cash receipt
    */
    public function addcashreceiptAction()
    {
		$this->view->formdata = $formdata = $this->_request->getPost();
		$this->view->params = $params = $this->getRequest()->getParams();

		$this->view->itemgrid    = $this->view->BaseUrl("/".$params['module']."/".$params['controller']."/cashreceiptgrid");	
		
		$Common_NameSpace = new Zend_Session_Namespace('CashierReceipt');
	
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
		
		if($formdata['ddlroute'] > 0)
		{
			$param_array 		= array();
			$param_array[1]		= $formdata['ddlroute'];
			$param_array[2]		= $formdata['hdnsalesmancode'];
			$param_array[3]		= str_replace(',','',$formdata['txtcashamt']);
			$param_array[4]		= str_replace(',','',$formdata['txtrcpamt']);
			$param_array[5]		= str_replace(',','',$formdata['txtchqamt']);
			$param_array[6]		= str_replace(',','',$formdata['txttotal']);
			$param_array[7]		= $formdata['txtdate'];
			$param_array[8]		= $formdata['ddlbank'];
			$param_array[9]		= $formdata['txtslipno'];
			$param_array[10]	= $this->currentUser->username;
			
			$res = $this->SFA_Comman->executequery('CALL sp_add_account_settlement_addcashreceipt(?,?,?,?,?,?,?,?,?,?)',$param_array,'');
			
			SFA_Message::setMsg($this->translate->_('New Record'));
	
			$this->_helper->redirector('cashreceipt', 'settlement', 'account');
		}		
		elseif($params['id'] > 0)
		{
			$result = $this->SFA_Comman->executequery('CALL sp_get_account_settlement_addcashreceipt(?)',$params['id'],'');
			$this->view->routeinfo	= $result[0];
			$this->view->bankinfo 	= $result[1];
			$this->view->result 	= $result[2][0];
		}
		else		
		{
			
			$result = $this->SFA_Comman->executequery('CALL sp_get_account_settlement_addcashreceipt(?)','0','');
			$this->view->routeinfo	= $result[0];
			$this->view->bankinfo 	= $result[1];			
		}
    }
	
	/**
    * @name       getrotuestatusAction
    * @since      22-02-2012
    * @version    Release: 1
    * @author     PM <pankit@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This action is use for the cash receipt grid
    */
    public function getrotuestatusAction()
    {
		$params = $this->getRequest()->getParams();
		$param_array 	= array();
		$param_array[1] = $params['routeid'];
		$param_array[2] = $params['routedate'];
		$result = $this->SFA_Comman->executequery('CALL sp_get_route_status_startendday(?,?)',$param_array,'');
		echo $result[0][0]['cnt'];
		exit;
	}
    /**
    * @name       cashreceiptgridAction
    * @since      22-02-2012
    * @version    Release: 1
    * @author     PM <pankit@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This action is use for the cash receipt grid
    */
    public function cashreceiptgridAction()
    {
		$params 			= $this->getRequest()->getParams();
		
        $trxnno			= $this->translate->_('Trxn No.');
		$trxndate		= $this->translate->_('Trxn Date');
		$type			= $this->translate->_('Type');		
		$chequeno		= $this->translate->_('Cheque No.');
		$chequedate		= $this->translate->_('Cheque Date');
		$bank			= $this->translate->_('Bank Name');
		$inv_amt		= $this->translate->_('Inv. Amt');
		$amt_paid		= $this->translate->_('Amt.Paid');
		$bal_amt		= $this->translate->_('Bal.Amt');		
		
		$cols_array = array('ah.invoicenumber AS invoicenumber','DATE_FORMAT(ah.transactiondate,"%d-%m-%Y") AS transactiondate','"Receipt" as paymenttype','ccd.checknumber AS checknumber',
						'DATE_FORMAT(ccd.checkdate,"%d-%m-%Y") AS checkdate','bm.bankname AS bankname','FORMAT(ad.totalinvoiceamount,'.$this->decimalplaces.') AS totalinvoiceamount','FORMAT(ad.amountpaid,'.$this->decimalplaces.') AS amountpaid',
						'FORMAT(ad.invoicebalance,'.$this->decimalplaces.') AS invoicebalance','1 AS sett_tran_type','ah.transactionkey  AS edit_del_primary_id');
		
		if($this->css == 'ar_') {			
			$cols_array[5]	= 'arbbankname AS bankname';
		}
		$amt_right 	= array('amountpaid','totalinvoiceamount','invoicebalance');
		$cols_show  = array($trxnno,$trxndate,$type,$chequeno,$chequedate,$bank,$inv_amt,$amt_paid,$bal_amt);
		
		// SFA_Comman::pre($params);
		// IF EXTRA PARAMS ARE REQUIRED
		$ex_param = "";
		if(isset($params["key"]) && $params["key"]>0) {
			$additional_where_condition = array();
			$ex_param = "/key/".$params["key"];
			$date = date("Y-m-d",strtotime(str_replace('/', '-', $params["key2"])));
			$additional_where_condition[] = ' ah.routecode = "'.$params["key"].'" AND ah.salesmancode = "'.$params["key1"].'"';
			$additional_where_condition[] = ' ah.transactiondate = "'.$date.'" ';
			$extra_cond	= ' AND  ih.routecode = "'.$params["key"].'" AND ih.salesmancode = "'.$params["key1"].'"  AND ih.transactiondate = "'.$date.'" ';
			$where_cond = ' routecode = "'.$params["key"].'" AND paymentdate = "'.$date.'" ';
		}
		
		// ARRAY FOR GRID PAGINATION
		$pagingparams = array(
					 "show_grid_heading" => false,
					 "grid_heading_message" => $this->translate->_('Overview'),
					 "show_records_per_page" => isset($formdata["show_records_per_page"])?$formdata["show_records_per_page"]:10000,
					 "show_searchbox" => false,
					 "show_selectbox" => false,
					 "show_editlink" => false,
					 "show_deletelink" => false,
					 "show_deleteall" => false,
					 "show_datasorting" => '1',
					 "primaryid" => 'edit_del_primary_id',
					 "currentlink" => array("/".$params['module']."/".$params['controller']."/".$params['action'].$ex_param),					 
					 "nodata_message" => $this->translate->_('No Record(s) Found'),
					 "fetch_columns_inquery" => $cols_array,
					 "show_columns" => $cols_show,					 
					 "additional_where" => $additional_where_condition,
					 "show_columns_right_side" =>$amt_right,
					 "show_header_right_side"=>array($this->translate->_('Inv. Amt'),$this->translate->_('Amt Paid'),$this->translate->_('Bal. Amt')),
					 "show_extralink_popup" => true,
					 "extralink" => array(array("More","/".$params['module']."/".$params['controller']."/cashierreceiptdetail/id/#pattern#/?q=prettyPhoto&iframe=true&width=900&height=500","#pattern#")),
				);
		//SFA_Comman::pre($pagingparams);
		// WHEN GRID IS IN EDIT MODE
		if($params["edit"]=="yes") {
		
			$pagingparams["editmode"] = true;
			$pagingparams["editmodeid"] = $params["id"];
			$pagingparams["editmodevalue"] = $primaryid;  // put table's prymary key here
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
		$param_array[8] = $extra_cond;
		$param_array[9] = $this->decimalplaces;
		$param_array[10]= $where_cond;
		$param_array[11]= $params['view'];
		
		
		// called stored procedure for counter
		$result = $this->SFA_Comman->executequery('CALL sp_get_account_settlement_addcashreceiptgrid(?,?,?,?,?,?,?,?,?,?,?)',$param_array,'');
		
		if($result[0][0]['counter'] =='Exist')
		{
			SFA_Message::setErrorMsg($this->translate->_('Receipt Already Done For This Route.'));
			echo '<script type="text/javascript"> clearcontrol(); </script>';	
		}
		else
		{
			$data_arr["count"] 		= ($result[0][0]['counter']+$result[0][1]['counter']);
			$data_arr["data"][0] 	= $result[1];
		//SFA_Comman::pre($result);
			//$cashamt = number_format(($result[2][0]['sum_amtpaid']+$result[2][2]['sum_amtpaid']),$this->decimalplaces);
			//$bankamt = number_format(($result[2][1]['sum_amtpaid']+$result[2][3]['sum_amtpaid']),$this->decimalplaces);
			//$totpamt = number_format(($result[3][0]['sum_totinvo']+$result[3][1]['sum_totinvo']),$this->decimalplaces);
			//$recamt  = number_format(($result[3][0]['sum_invobal']+$result[3][1]['sum_invobal']),$this->decimalplaces);
			
			$recamt  = number_format(($result[2][0]['sum_amtpaid']),$this->decimalplaces);
			$cashamt = number_format(($result[2][1]['sum_amtpaid']),$this->decimalplaces);
			$bankamt = number_format(($result[2][2]['sum_amtpaid']),$this->decimalplaces);
			$totpamt = number_format(($result[2][0]['sum_amtpaid']+$result[2][1]['sum_amtpaid']+$result[2][2]['sum_amtpaid']),$this->decimalplaces);
			
			echo '	<script>
						$("#txtcashamt").val("'.$cashamt.'");
						$("#txtchqamt").val("'.$bankamt.'");
						$("#txttotal").val("'.$totpamt.'");
						$("#txtrcpamt").val("'.$recamt.'");
					</script>';
		}
		
		$this->view->pagegridshow =  $pagingshow->summary_showdatagrid($data_arr);
		$this->view->addScriptPath(APPLICATION_PATH."/theme/default/datagrid/");		
		$this->render("ajaxgrid");
    }
	/**
    * @name       cashierreceiptdetail
    * @since      22-02-2012
    * @version    Release: 1
    * @author     HD <hiren.d@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This action is use for the cheque information
    */
    public function cashierreceiptdetailAction()
    {
		$params 			= $this->getRequest()->getParams();
		
		$menu_array     	= $Menu_NameSpace->header_menu;
		
		$this->_helper->layout->setLayout('popup');
		$this->view->title	= $this->translate->_('Cashier Receipt');
		
		$columns_show = array($this->translate->_('Invoice No.'),$this->translate->_('Invoice Date'),$this->translate->_('Route Code'),
							  $this->translate->_('Salesman Code'),$this->translate->_('Total Invoice Amount'),$this->translate->_('Amount Paid (With Cheque)'),
							  $this->translate->_('Present Balance'),$this->translate->_('PDC Balance'));
		
		$columns_array 	= array('ah.invoicenumber','DATE_FORMAT(ah.transactiondate,"%d-%m-%Y") AS transactiondate','ah.routecode','ah.salesmancode',
							'FORMAT(ad.totalinvoiceamount,'.$this->decimalplaces.') AS totalinvoiceamount','FORMAT(ad.amountpaid,'.$this->decimalplaces.') as amountpaid',
							'FORMAT(ad.invoicebalance,'.$this->decimalplaces.') as invoicebalance','FORMAT(ad.pdcbalance,'.$this->decimalplaces.') AS pdcbalance','ah.transactionkey as edit_del_primary_id');
		
		$additional_where_condition = array();
		$additional_where_condition[] = ' ah.transactionkey = "'.$params['id'].'"';		
		
		$amt_right = array('pdcbalance','pdcbalance','totalinvoiceamount','invoicebalance','amountpaid');
		
		// prepare the configuration for grid
		$pagingparams = array(
				"show_grid_heading" => true,
				"grid_heading_message" => '',
				"show_records_per_page" => isset($formdata["show_records_per_page"])?$formdata["show_records_per_page"]:10,
				"show_searchbox" => false,
				"searchbox_value" => isset($formdata["grid_search_text"])?$formdata["grid_search_text"]:"",
				"show_selectbox" => false,
				"show_editlink" => false,				
				"show_deletelink" => false,			
				"show_deleteall" => false,
				"primaryid" => 'ah.transactionkey',				
				"nodata_message" => $this->translate->_('No Record(s) Found'),
				"fetch_columns_inquery" => $columns_array,
				"show_columns" => $columns_show,
				"show_columns_right_side" =>$amt_right,
				"show_header_right_side"=>array($this->translate->_('Total Invoice Amount'),$this->translate->_('Amount Paid (With Cheque)'),$this->translate->_('Present Balance'),$this->translate->_('PDC Balance')),
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
		$param_array[2] = $get_return_vals['order_columns_name'];
		$param_array[3] = $get_return_vals['order_type'];
		$param_array[4] = $get_return_vals['offset'];
		$param_array[5] = (int)$get_return_vals['show_records_per_page'];
		$param_array[6] = implode(", ",$pagingparams["fetch_columns_inquery"]);
		$param_array[7] = strlen($get_return_vals['where_condition'])>0?$get_return_vals['where_condition']:'';		
		
		
		// called stored procedure for counter
		$result = $this->SFA_Comman->executequery('CALL sp_get_account_settlement_addcashreceiptpopupgrid(?,?,?,?,?,?,?,?)',$param_array,'');
		
		$data_arr["count"] 		= $result[0][0]['counter'];
		$data_arr["data"][0] 	= $result[1];
		
		// pass the data in summary_showdatagrid() function & create a final variable for view
		$this->view->pagegridshow =  $pagingshow->summary_showdatagrid($data_arr);
		
		$this->view->addScriptPath(APPLICATION_PATH."/theme/default/datagrid/");
    }
    /**
    * @name       pdcAction
    * @since      27-01-2012
    * @version    Release: 1
    * @author     HD <hiren.d@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This action is use for the display pdc cheque
    */
    public function pdcAction()
    {
		$this->view->params = $params = $this->getRequest()->getParams();
        $this->view->formdata = $formdata = $this->_request->getPost();
		
		$this->view->title	= $this->translate->_('PDC Clearance');
		
		$Common_NameSpace = new Zend_Session_Namespace('PDCClearance');
		if($formdata['btnreset'] == 'RESET')
		{
			$formdata["txtdate"] 	= '';
			$Common_NameSpace->tdate	= '';
		}
		$last_url = htmlspecialchars($_SERVER['HTTP_REFERER']);
		if(strpos($last_url,'addpdc'))
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
			$additional_where_condition[] = " (`transactiondate`  BETWEEN \'".date("Y-m-d 00:00:00",strtotime(str_replace('/', '-', $Common_NameSpace->tdate)))."\' AND \'".date("Y-m-d 23:59:59",strtotime(str_replace('/', '-', $Common_NameSpace->tdate)))."\' )";
			
		$cols_array 	= array('pdc.routecode','rm.routename as routename','cm.customername AS customername','sm.salesmanname1 as salesmanname1','bankname','FORMAT(pdc.chequeamount,'.$this->decimalplaces.') AS chequeamount','pdc.transactionkey as edit_del_primary_id');
		$columns_show 	= array($this->translate->_('Route Code'),$this->translate->_('Route Name'),$this->translate->_('Customer Name'),$this->translate->_('Salesman Name'),$this->translate->_('Bank Name'),$this->translate->_('Amount'));
		
		if($this->css == 'ar_') {
			$cols_array[1]	= 'arbroutename AS routecode';
			$cols_array[2]	= 'arbcustomername AS customername';
			$cols_array[3]	= 'sm.arbsalesmanname1 AS salesmanname1';
			$cols_array[4]	= 'arbbankname AS bankname';
		}
		
		// prepare the configuration for grid
		$pagingparams = array (
			"show_grid_heading" => true,
			"grid_heading_message" => $this->translate->_('Overview'),
			"show_records_per_page" => isset($formdata["show_records_per_page"])?$formdata["show_records_per_page"]:10,
			"show_searchbox" => true,
			"searchbox_value" => isset($formdata["grid_search_text"])?$formdata["grid_search_text"]:"",
			"show_selectbox" => false,
			"pagename" => $this->translate->_('PDC Clearance'),
			"show_editlink" => false,
			"show_deletelink" => false,
			"show_deleteall" => false,
			"primaryid" => 'transactionkey',
			"editlink" => $edit_link,
			"nodata_message" => $this->translate->_('No Record(s) Found'),
			"fetch_columns_inquery" => $cols_array,
			"show_columns" => $columns_show,
			"show_columns_right_side" =>array('chequeamount'),
			"show_header_right_side"=>array($this->translate->_('Amount')),
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
		
		$downloadCSV = (isset($formdata['downloadcsv'])) ? $formdata['downloadcsv'] : $params['downloadcsv'];
    
    // Hiren Dave on 19 Nov, 2012 - START - to check if need to print data
	$printData = (isset($formdata['printData'])) ? $formdata['printData'] : $params['printData'];
		
		$result 				= $this->SFA_Comman->executequery('CALL sp_get_account_settlement_pdcclearance(?,?,?,?,?,?,?,?)',$param_array,'',$downloadCSV,$printData,2,$pagingparams);
		$data_arr["count"] 		= $result[0][0]['counter'];
		$data_arr["data"][0] 	= $result[1];
		
		// pass the data in summary_showdatagrid() function & create a final variable for view
		$this->view->pagegridshow =  $pagingshow->summary_showdatagrid($data_arr);
		
		$this->view->addScriptPath(APPLICATION_PATH."/theme/default/datagrid/");
    }
    /**
    * @name       addpdcAction
    * @since      27-01-2012
    * @version    Release: 1
    * @author     HD <hiren.d@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This action is use for the add pdc cheque information
    */
    public function addpdcAction()
    {
		$this->view->params = $params = $this->getRequest()->getParams();
        $this->view->formdata = $formdata = $this->_request->getPost();
		
		$Common_NameSpace = new Zend_Session_Namespace('PDCClearance');
	
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
		
		$Menu_NameSpace = new Zend_Session_Namespace('Menu');
		$menu_array     = $Menu_NameSpace->header_menu;
		
        // IF EXTRA PARAMS ARE REQUIRED
		$ex_param = "";
		if(isset($params["id"]) && $params["id"]>0) {
			$ex_param = "/key/".$params["id"]."/key1/".$params["trantype"];
		}
		$this->view->itemgrid    = $this->view->BaseUrl("/".$params['module']."/".$params['controller']."/pdcgrid".$ex_param);

		$this->view->css = $this->translate->_('CSS');
		
		if($params['id'] > 0)
		{
			
			$param_array 	= array();
			$param_array[1] = $params['id'];
			$param_array[2] = $params['trantype'];
			
			if($menu_array['PDC Clearance With CashierReceipt']['status'] == 1 ) {				
				$result = $this->SFA_Comman->executequery('CALL sp_get_route_customer_from_routecode_crheader(?,?)',$param_array,'');
			}
			else {
				$result = $this->SFA_Comman->executequery('CALL sp_get_route_customer_from_routecode(?,?)',$param_array,'');
			}
			
			//SFA_Comman::pre($result);
			$this->view->routeinfo = $result[0][0];
			$this->view->salesinfo = $result[1][0];
			$this->view->custinfo  = $result[2];
			$this->view->bankinfo  = $result[3];
			$this->view->custcode  = $result[4][0]['custcode'];
		}
		else
		{
			$result = $this->SFA_Comman->executequery('CALL sp_get_account_settlement_addcashreceipt(?)','0','');			
			$this->view->routeinfo	= $result[0];
			$this->view->bankinfo 	= $result[1];			
		}
    }

    /**
    * @name       pdcgridAction
    * @since      22-02-2012
    * @version    Release: 1
    * @author     PM <pankit@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This action is use for the cheque information
    */
    public function pdcgridAction()
    {
		$params 			= $this->getRequest()->getParams();
		$Menu_NameSpace 	= new Zend_Session_Namespace('Menu');
		$menu_array     	= $Menu_NameSpace->header_menu;
		
		//Invoice Number, Invoice Date, Route Code, Salesman Code, Total Invoice Amount, Amount Paid (with Cheque), Invoice Balance.
		
        $columns_show = array($this->translate->_('Transaction No.'),$this->translate->_('Customer Code'),$this->translate->_('Cheque No.'),
							  $this->translate->_('Cheque Date'),$this->translate->_('Cheque Amount'),$this->translate->_('Bank Name'));
		
		if($params['bounce'] == 'yes')
		{
			$param_array 	= array();
			$param_array[1]	= $params['hdntrancode'];
			$param_array[2]	= $params['hdncnttrancode'];
			
			$lastid = $this->SFA_Comman->executequery('CALL sp_add_account_settlement_bounce_pdccheque(?,?)',$param_array,'');
			
			if($lastid[0][0]['last_id'] > 0) {
				SFA_Message::setMsg($this->translate->_('Cheque Bounce Successfully.'));
			}
			
			$params["key"]  = $params["ddlroute"];
			$params["key1"] = $params["hdnsalesmancode"];
			$params["key2"] = $params["txtdate"];
			$params["key3"] = $params["ddlcustomer"];
		}
		
		if($params['add'] == 'yes')
		{
			$param_array 	 = array();
			$param_array[1]	 = $params['ddlroute'];
			$param_array[2]	 = $params['hdnsalesmancode'];
			$param_array[3]	 = $params['txtdate'];
			$param_array[4]	 = $params['ddlbank'];
			$param_array[5]	 = $params['txterprefno'];
			$param_array[6]	 = $params['txtremark'];
			$param_array[7]	 = $params['txtdate_below'];
			$param_array[8]	 = $params['hdntrancode'];
			$param_array[9]	 = $params['hdncnttrancode'];			
			$param_array[10] = $this->currentUser->username;
			$param_array[11] = $params['txtbankrefno'];
			
			if($menu_array['PDC Clearance With CashierReceipt']['status'] == 1 ) {
				$lastid = $this->SFA_Comman->executequery('CALL sp_add_account_settlement_addpdcgrid_2(?,?,?,?,?,?,?,?,?,?,?)',$param_array,'');
			}
			else {
				$lastid = $this->SFA_Comman->executequery('CALL sp_add_account_settlement_addpdcgrid_1(?,?,?,?,?,?,?,?,?,?,?)',$param_array,'');				
			}
			
			//if($lastid[0][0]['last_id'] > 0)
			//{
			//	SFA_Message::setMsg($this->translate->_('Update Record'));
			//}
			
			$params["key"]  = $params["ddlroute"];
			$params["key1"] = $params["hdnsalesmancode"];
			$params["key2"] = $params["txtdate"];
			$params["key3"] = $params["ddlcustomer"];
		}
		
		
		
		if($menu_array['PDC Clearance With CashierReceipt']['status'] == 1 ) {
			$columns_array 	= array('crd.transactionno','crd.customercode','crd.checkno','DATE_FORMAT(crd.checkdate,"%d-%m-%Y") AS checkdate',
								'FORMAT(crd.checkamount,'.$this->decimalplaces.') AS checkamount','bm.bankname','1 AS sett_tran_type',								
								'CONCAT(crd.transactionkey,"_crd_",crd.transactionno) AS edit_del_primary_id');
		}
		else {
			//'CASE ccd.checkstatus WHEN NULL THEN "Process" WHEN 1 THEN "Cleared" WHEN 2 THEN "Bounce" END AS checkstatus',
			$columns_array 	= array('ah.invoicenumber','ah.customercode','ccd.checknumber','DATE_FORMAT(ccd.checkdate,"%d-%m-%Y") AS checkdate',
									'FORMAT(ccd.amount,'.$this->decimalplaces.') AS checkamount','bm.bankname','1 AS sett_tran_type',
									'CONCAT(ah.transactionkey,"_ah_",ah.invoicenumber) AS edit_del_primary_id');
		}
	
		$amt_right = array('checkamount');
		
		
		//SFA_Comman::pre($params);
		// IF EXTRA PARAMS ARE REQUIRED
		$ex_param = "";		
		if(isset($params["key"]) && $params["key"]>0) {
			$additional_where_condition = array();
			$ex_param = "/key/".$params["key"];
			$date = date("Y-m-d", strtotime($params["key2"]));
			
			
			if($menu_array['PDC Clearance With CashierReceipt']['status'] == 1 ) {				
				$additional_where_condition[] = ' ( crh.routecode = "'.$params["key"].'" ) ';
				$additional_where_condition[] = ' ( crh.salesmancode = "'.$params["key1"].'" ) ';
				$additional_where_condition[] = ' ( crd.transactiondate = "'.$date.'" ) ';
				$additional_where_condition[] = ' ( crd.type = 1 ) ';
				if($params["key3"] > 0) {
					$additional_where_condition[] = ' ( crd.customercode = "'.$params["key3"].'" ) ';
				}
				
				$primaryid 	= 'crd.transactionno';
				
				$extra_cond  = '';
				// baerheader
				if($params["key3"] > 0) {
					$extra_cond	.= ' AND bh.customercode = "'.$params["key3"].'" ';
				}
				$extra_cond	.= ' AND  bh.routecode = "'.$params["key"].'" AND bh.salesmancode = "'.$params["key1"].'"  AND bh.transactiondate = "'.$date.'"  AND bcd.checkstatus NOT IN (1,2) ';
				//seperator
				$extra_cond .='$$';
				// dcarheader
				if($params["key3"] > 0) {
					$extra_cond	.= ' AND dh.customercode = "'.$params["key3"].'" ';
				}
				$extra_cond	.= ' AND  dh.routecode = "'.$params["key"].'" AND dh.salesmancode = "'.$params["key1"].'"  AND dh.transactiondate = "'.$date.'" AND dcd.checkstatus NOT IN (1,2) ';
				
				//$where_cond = ' AND routecode = "'.$params["key"].'" AND transactiondate = "'.$date.'" ';
			}
			else {
				$primaryid 	= 'ah.transactionkey';				
				// for arheader
				$additional_where_condition[] = ' ah.routecode = "'.$params["key"].'"';
				$additional_where_condition[] = ' ah.salesmancode = "'.$params["key1"].'"';
				$additional_where_condition[] = ' ah.transactiondate = "'.$date.'" ';
				$additional_where_condition[] = ' ccd.checkstatus NOT IN (1,2) ';
				
				if($params["key3"] > 0) {
					$additional_where_condition[] = ' ( ah.customercode = "'.$params["key3"].'" ) ';
				}
				
				$extra_cond  = '';
				// for invoiceheader
				if($params["key3"] > 0) {
					$extra_cond	.= ' AND ih.customercode = "'.$params["key3"].'" ';
				}
				$extra_cond	.= ' AND  ih.routecode = "'.$params["key"].'" AND ih.salesmancode = "'.$params["key1"].'"  AND ih.transactiondate = "'.$date.'" AND ccd.checkstatus NOT IN (1,2) ';
				$extra_cond .='$$';
				// baerheader
				if($params["key3"] > 0) {
					$extra_cond	.= ' AND bh.customercode = "'.$params["key3"].'" ';
				}
				$extra_cond	.= ' AND  bh.routecode = "'.$params["key"].'" AND bh.salesmancode = "'.$params["key1"].'"  AND bh.transactiondate = "'.$date.'" AND bcd.checkstatus NOT IN (1,2) ';
				//seperator
				$extra_cond .='$$';
				// dcarheader
				if($params["key3"] > 0) {
					$extra_cond	.= ' AND dh.customercode = "'.$params["key3"].'" ';
				}
				$extra_cond	.= ' AND  dh.routecode = "'.$params["key"].'" AND dh.salesmancode = "'.$params["key1"].'"  AND dh.transactiondate = "'.$date.'"  AND dcd.checkstatus NOT IN (1,2) ';
				
				// check record is
				$where_cond = ' routecode = "'.$params["key"].'" AND transactiondate = "'.$date.'" ';
			}
		}
		
		// ARRAY FOR GRID PAGINATION
		$pagingparams = array(
					 "show_grid_heading" => false,
					 "grid_heading_message" => $this->translate->_('Overview'),
					 "show_records_per_page" => isset($formdata["show_records_per_page"])?$formdata["show_records_per_page"]:1000000,
					 "show_searchbox" => false,
					 "show_selectbox" => true,
					 "show_editlink" => false,
					 "show_deletelink" => false,
					 "show_deleteall" => false,
					 "primaryid" => $primaryid,
					 "currentlink" => array("/".$params['module']."/".$params['controller']."/".$params['action'].$ex_param),
					 "nodata_message" => $this->translate->_('No Record(s) Found'),					 
					 "fetch_columns_inquery" => $columns_array,
					 "show_columns" => $columns_show,
					 "additional_where" => $additional_where_condition,
					 "show_columns_right_side" =>$amt_right,
					 "show_header_right_side"=>array($this->translate->_('Cheque Amount')),
					 "show_extralink_popup" => true,
					 "extralink" => array(array("More","/".$params['module']."/".$params['controller']."/pdcgriddetail/id/#pattern#/?q=prettyPhoto&iframe=true&width=1000&height=500","#pattern#")),
					 );
		
	
		// WHEN GRID IS IN EDIT MODE
		if($params["edit"]=="yes"){		
			$pagingparams["editmode"] = true;
			$pagingparams["editmodeid"] = $params["id"];
			$pagingparams["editmodevalue"] = $primaryid;  // put table's prymary key here
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
		$param_array[8] = $extra_cond;
		$param_array[9] = $this->decimalplaces;
		$param_array[10]= $where_cond;
		
		// called stored procedure for counter
		if($menu_array['PDC Clearance With CashierReceipt']['status'] == 1 ) {
			$result = $this->SFA_Comman->executequery('CALL sp_get_account_settlement_addpdcgrid_2(?,?,?,?,?,?,?,?)',$param_array,'');
			
			$data_arr["count"] 		= (count($result[0]));
			$data_arr["data"][0] 	= $result[0];
		}
		else { 
			$result = $this->SFA_Comman->executequery('CALL sp_get_account_settlement_addpdcgrid_1(?,?,?,?,?,?,?,?)',$param_array,'');
			$data_arr["count"] 		= ($result[0][0]['counter']+$result[0][1]['counter']+$result[0][2]['counter']);
			$data_arr["data"][0] 	= $result[1];
		}
		
		$this->view->pagegridshow =  $pagingshow->summary_showdatagrid($data_arr);
		$this->view->addScriptPath(APPLICATION_PATH."/theme/default/datagrid/");
		
		$this->render("ajaxgrid");
    }
	/**
    * @name       pdcgriddetailAction
    * @since      22-02-2012
    * @version    Release: 1
    * @author     HD <hiren.d@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This action is use for the cheque information
    */
    public function pdcgriddetailAction()
    {
		$params 			= $this->getRequest()->getParams();
		
		$menu_array     	= $Menu_NameSpace->header_menu;
		
		$this->_helper->layout->setLayout('popup');
		$this->view->title	= $this->translate->_('PDC Clearance');
		
		$query_str = explode('_',$params['id']);
		
		$additional_where_condition = array();
		
		$columns_show = array($this->translate->_('Invoice No.'),$this->translate->_('Invoice Date'),$this->translate->_('Route Code'),
							  $this->translate->_('Salesman Code'),$this->translate->_('Total Invoice Amount'),$this->translate->_('Amount Paid (With Cheque)'),
							  $this->translate->_('Present Balance'),$this->translate->_('PDC Balance'));
		
		if($query_str[1] == 'crd')
		{
			$columns_array 	= array('crd.transactionno','DATE_FORMAT(crd.transactiondate,"%d-%m-%Y") AS transactiondate','crh.routecode','crh.salesmancode',
							'FORMAT(crd.invoiceamount,'.$this->decimalplaces.') AS totalinvoiceamount','FORMAT(crd.paid,'.$this->decimalplaces.') as amountpaid',
							'FORMAT(crd.balance,'.$this->decimalplaces.') as invoicebalance','FORMAT(crd.pdcbalance,'.$this->decimalplaces.') AS pdcbalance','crd.transactionno as edit_del_primary_id');
			
			$primaryid 	= 'crd.transactionno';
			$extra_cond	= 'crd';
			
			$additional_where_condition[] = ' ( crd.transactionno = "'.$query_str[2].'" ) ';			
			$additional_where_condition[] = ' ( crd.type = 1 ) ';
		}
		elseif($query_str[1] == 'ah')
		{
			$columns_array 	= array('ah.invoicenumber','DATE_FORMAT(ah.transactiondate,"%d-%m-%Y") AS transactiondate','ah.routecode','ah.salesmancode',
							'FORMAT(ci.totalinvoiceamount,'.$this->decimalplaces.') AS totalinvoiceamount','FORMAT(ci.amountpaid,'.$this->decimalplaces.') as amountpaid',
							'FORMAT(ci.invoicebalance,'.$this->decimalplaces.') as invoicebalance','FORMAT(ci.pdcbalance,'.$this->decimalplaces.') AS pdcbalance','ah.transactionkey as edit_del_primary_id');

			$primaryid 	= 'ah.transactionkey';
			$extra_cond	= 'ah';
			
			$additional_where_condition[] = ' ah.transactionkey = "'.$query_str[0].'"';
		}
		elseif($query_str[1] == 'ih')
		{
			$columns_array 	= array('ih.invoicenumber AS invoicenumber','DATE_FORMAT(ih.transactiondate,"%d-%m-%Y") AS transactiondate','ih.routecode','ih.salesmancode',
							'FORMAT(ci.totalinvoiceamount,'.$this->decimalplaces.') AS totalinvoiceamount','FORMAT(ci.amountpaid,'.$this->decimalplaces.') AS amountpaid',
							'FORMAT(ci.invoicebalance,'.$this->decimalplaces.') AS invoicebalance','FORMAT(ci.pdcbalance,'.$this->decimalplaces.') AS pdcbalance','ih.transactionkey  AS edit_del_primary_id');
			
			$primaryid 	= 'ih.transactionkey';
			$extra_cond	= 'ih';
			
			$additional_where_condition[] = ' ih.transactionkey = "'.$query_str[0].'"';
			//$additional_where_condition[] = ' ah.salesmancode = "'.$params["key1"].'"';
		}
		elseif($query_str[1] == 'be')
		{
			$columns_array 	= array('bd.invoicenumber','DATE_FORMAT(bh.transactiondate,"%d-%m-%Y") AS transactiondate','bh.routecode','bh.salesmancode',
							'FORMAT(bd.totalinvoiceamount,'.$this->decimalplaces.') AS totalinvoiceamount','FORMAT(bd.amountpaid,'.$this->decimalplaces.') AS amountpaid',
							'FORMAT(bd.invoicebalance,'.$this->decimalplaces.') AS invoicebalance','FORMAT(ci.pdcbalance,'.$this->decimalplaces.') AS pdcbalance','bh.transactionkey AS edit_del_primary_id');
			
			$primaryid 	= 'bh.transactionkey';
			$extra_cond	= 'be';
			
			$additional_where_condition[] = ' bh.transactionkey = "'.$query_str[0].'"';
			//$additional_where_condition[] = ' bd.invoicenumber = "'.$query_str[2].'"';
		}
		elseif($query_str[1] == 'dc')
		{
			$columns_array 	= array('dd.invoicenumber','DATE_FORMAT(dh.transactiondate,"%d-%m-%Y") AS transactiondate','dh.routecode','dh.salesmancode',
							'FORMAT(dd.totalinvoiceamount,'.$this->decimalplaces.') AS totalinvoiceamount','FORMAT(dd.amountpaid,'.$this->decimalplaces.') AS amountpaid',
							'FORMAT(dd.invoicebalance,'.$this->decimalplaces.') AS invoicebalance','FORMAT(ci.pdcbalance,'.$this->decimalplaces.') AS pdcbalance','dh.transactionkey AS edit_del_primary_id');
			
			$primaryid 	= 'dh.transactionkey';
			$extra_cond	= 'dc';
			
			$additional_where_condition[] = ' dh.transactionkey = "'.$query_str[0].'"';			
		}
	
		$amt_right = array('pdcbalance','pdcbalance','totalinvoiceamount','invoicebalance','amountpaid');
		
		// prepare the configuration for grid
		$pagingparams = array(
				"show_grid_heading" => true,
				"grid_heading_message" => '',
				"show_records_per_page" => isset($formdata["show_records_per_page"])?$formdata["show_records_per_page"]:10,
				"show_searchbox" => false,
				"searchbox_value" => isset($formdata["grid_search_text"])?$formdata["grid_search_text"]:"",
				"show_selectbox" => false,
				"show_editlink" => false,				
				"show_deletelink" => false,			
				"show_deleteall" => false,
				"primaryid" => $primaryid,				
				"nodata_message" => $this->translate->_('No Record(s) Found'),
				"fetch_columns_inquery" => $columns_array,
				"show_columns" => $columns_show,
				"show_columns_right_side" =>$amt_right,
				"show_header_right_side"=>array($this->translate->_('Total Invoice Amount'),$this->translate->_('Amount Paid (With Cheque)'),$this->translate->_('Present Balance'),$this->translate->_('PDC Balance')),
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
		$param_array[2] = $get_return_vals['order_columns_name'];
		$param_array[3] = $get_return_vals['order_type'];
		$param_array[4] = $get_return_vals['offset'];
		$param_array[5] = (int)$get_return_vals['show_records_per_page'];
		$param_array[6] = implode(", ",$pagingparams["fetch_columns_inquery"]);
		$param_array[7] = strlen($get_return_vals['where_condition'])>0?$get_return_vals['where_condition']:'';
		$param_array[8] = $extra_cond;
		
		
		// called stored procedure for counter
		$result = $this->SFA_Comman->executequery('CALL sp_get_account_settlement_addpdcdetailgrid(?,?,?,?,?,?,?,?)',$param_array,'');
		
		$data_arr["count"] 		= $result[0][0]['counter'];
		$data_arr["data"][0] 	= $result[1];
		
		// pass the data in summary_showdatagrid() function & create a final variable for view
		$this->view->pagegridshow =  $pagingshow->summary_showdatagrid($data_arr);
		
		$this->view->addScriptPath(APPLICATION_PATH."/theme/default/datagrid/");
    }
}