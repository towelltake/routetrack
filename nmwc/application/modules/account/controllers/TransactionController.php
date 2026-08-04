<?php
/**
* @name       TransactionController
* @since
* @version    Release: 1
* @author     HD <hiren.d@elantechnologies.com>
* @copyright  Elan Technologies
* @param
*
* This controller for transaction sub menu.
*/
class Account_TransactionController extends Account_Library_Controller_Action_Abstract
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
		$this->css 					= $this->translate->_('CSS');
		$this->view->css 			= $this->css;
		$this->view->overview		= $this->translate->_('Overview');
		$this->view->details		= $this->translate->_('Details');
		$this->view->required		= $this->translate->_('Required');
		$this->view->colan			= $this->translate->_('Colan');
		$this->SFA_Model_Index 		= new SFA_Model_Index();
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
    * @name       monthcloseinfoAction
    * @since      25-01-2012
    * @version    Release: 1
    * @author     HD <hiren.d@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This action is use for the display Month close
    */
    public function monthcloseinfoAction()
    {
		$this->view->params = $params = $this->getRequest()->getParams();
        $this->view->formdata = $formdata = $this->_request->getPost();
		
		$cols_array = array('mc.routecode','rm.routename','byear','month_name(bmonth) AS bmonth','CONCAT(mc.routecode,"_",bmonth,"_",byear) AS edit_del_primary_id');
		$columns_show =  array($this->translate->_('Route Code'),$this->translate->_('Route Name'),$this->translate->_('Year'),$this->translate->_('Month'));
		
		if($this->css == 'ar_') {
			$cols_array[1]	= 'arbroutename';
		}
		$fromyear = array();
        $start=2000;
        $noofyears = 50;
        $upto = $start+$noofyears;
        $j=0;
        for($i=$start;$i<=$upto; $i++){
            $fromyear[$j]['id'] = $i;
            $fromyear[$j]['val'] = $i;
            $j++;
        }
        $this->view->years = $fromyear;
		
		$months = array();
        $months[0]['id'] = 1;
        $months[0]['val'] = 'January';
        $months[1]['id'] = 2;
        $months[1]['val'] = 'February';
        $months[2]['id'] = 3;
        $months[2]['val'] = 'March';
        $months[3]['id'] = 4;
        $months[3]['val'] = 'April';
        $months[4]['id'] = 5;
        $months[4]['val'] = 'May';
        $months[5]['id'] = 6;
        $months[5]['val'] = 'June';
        $months[6]['id'] = 7;
        $months[6]['val'] = 'July';
		$months[7]['id'] = 8;
        $months[7]['val'] = 'August';
        $months[8]['id'] = 9;
        $months[8]['val'] = 'September';
        $months[9]['id'] = 10;
        $months[9]['val'] = 'Octomber';
        $months[10]['id'] = 11;
        $months[10]['val'] = 'November';
        $months[11]['id'] = 12;
        $months[11]['val'] = 'December';
		
		$this->view->months = $months;
		
		
		
		
		$Common_NameSpace = new Zend_Session_Namespace('MonthClose');
		if($formdata['btnreset'] == 'RESET')
		{
			$formdata["ddlmonth"] 		= '';
			$formdata["ddlyear"] 		= date('Y');
			$Common_NameSpace->tmonth	= '';
			$Common_NameSpace->tyear	= '';
		}
		$last_url = htmlspecialchars($_SERVER['HTTP_REFERER']);
		if(strpos($last_url,'monthcloseinfo')) {
			$month 	= ($formdata["ddlmonth"] != '') ? $formdata["ddlmonth"] : $Common_NameSpace->tmonth;
			$year 	= ($formdata["ddlyear"] != '') ? $formdata["ddlyear"] : $Common_NameSpace->tyear;
		} else {
			$month 	= ($formdata["ddlmonth"] != '') ? $formdata["ddlmonth"] : '';
			$year 	= ($formdata["ddlyear"]  != '') ? $formdata["ddlyear"]  : '';
		}
		
		// ADD DATE VALUE IN SESSION
		if($month != '' || $year != '' ) {
			$this->view->month 	= $Common_NameSpace->tmonth	= $month;
			$this->view->year 	= $Common_NameSpace->tyear	= $year;
		} else {
			$this->view->month 	= $Common_NameSpace->tmonth	= '';
			$this->view->year 	= $Common_NameSpace->tyear	= date('Y');
		}
		
		// ADDITIONAL WHERE CONDITION		
		if($Common_NameSpace->tmonth) {
			$additional_where_condition[] = " bmonth = ".$Common_NameSpace->tmonth;
		}
		if($Common_NameSpace->tyear) {
			$additional_where_condition[] = " byear = ".$Common_NameSpace->tyear;
		}
		
		// prepare the configuration for grid
		$pagingparams = array(
								"show_grid_heading" => true,
								"grid_heading_message" => $this->translate->_('Overview'),
								"show_records_per_page" => isset($formdata["show_records_per_page"])?$formdata["show_records_per_page"]:10,
								"show_searchbox" => true,
								"searchbox_value" => isset($formdata["grid_search_text"])?$formdata["grid_search_text"]:"",
								"pagename" => $this->translate->_('Month Close'),
								"show_selectbox" => false,
								"show_editlink" => false,
								"show_deletelink" => false,
								"show_deleteall" => false,
								"nodata_message" => $this->translate->_('No Record(s) Found'),
								"fetch_columns_inquery" => $cols_array,
								"show_columns" => $columns_show,
								"additional_where" => $additional_where_condition,
								"show_extralink" => true,
								"extralink" => array(array("View","/".$params['module']."/".$params['controller']."/monthcloseview/id/#pattern#/","#pattern#")),
							);
		
		// create grid class object
		$pagingshow 		= new SFA_Paging($pagingparams);
		$get_return_vals 	= $pagingshow->commnfunc();

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
		$result = $this->SFA_Comman->executequery('CALL sp_get_account_transaction_monthclose(?,?,?,?,?,?,?,?)',$param_array,'',$downloadCSV,$printData,2,$pagingparams);
		$data_arr["count"] 		= $result[0][0]['counter'];
		$data_arr["data"][0] 	= $result[1];
		
		// pass the data in summary_showdatagrid() function & create a final variable for view
		$this->view->pagegridshow =  $pagingshow->summary_showdatagrid($data_arr);
		
		$this->view->addScriptPath(APPLICATION_PATH."/theme/default/datagrid/");		
    }
	/**
    * @name       monthcloseviewAction
    * @since      25-01-2012
    * @version    Release: 1
    * @author     HD <hiren.d@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This action is use for the display Month and Year wise information.
    */
    public function monthcloseviewAction()
    {
		$this->view->params = $params = $this->getRequest()->getParams();
        $this->view->formdata = $formdata = $this->_request->getPost();
		
		$cols_array = array('itemcode','itemshortdescription','upc',
							'IFNULL(quantitybegininventory,0) AS quantitybegininventory','IFNULL(quantityload,0) AS quantityload',
							'IFNULL(quantityloadadjust,0) AS quantityloadadjust','IFNULL(quantitytransfer,0) AS quantitytransfer',
							'IFNULL(quantitysales,0) AS quantitysales','IFNULL(quantitybuybacks,0) AS quantitybuybacks',
							'IFNULL(quantityreturnscredited,0) AS quantityreturnscredited','IFNULL(quantitytruckspoilage,0) AS quantitytruckspoilage',
							'IFNULL(quantityfreegood,0) AS quantityfreegood','IFNULL(quantitybuybackfree,0) AS quantitybuybackfree',
							'IFNULL(quantitygiveaway,0) AS quantitygiveaway','IFNULL(quantityendinginventory,0) AS quantityendinginventory',
							'IFNULL(quantitydamage,0) AS quantitydamage','IFNULL(valueendinginventory,0) AS valueendinginventory'
						   );
		
		$columns_show =  array($this->translate->_('Item Code'),$this->translate->_('Item Name'),$this->translate->_('UPC'),
							   $this->translate->_('Begin Inv. Qty.'),$this->translate->_('Qty. Load'),$this->translate->_('Qty. Load Adjust'),
							   $this->translate->_('Qty. Transfer'),$this->translate->_('Qty. Sales'),$this->translate->_('Qty. Buyback'),
							   $this->translate->_('Qty. Returen Credited'),$this->translate->_('Qty. Truck Spoil'),$this->translate->_('Qty. Free Goods'),
							   $this->translate->_('Qty. Buyback Free'),$this->translate->_('Qty. Give Way'),$this->translate->_('Qty. Ending Inv.'),
							   $this->translate->_('Qty. Damage'),$this->translate->_('Value Ending Inv.')
							   );
		
		if($this->css == 'ar_') {
			$cols_array[1]	= 'arbitemshortdescription';
		}
		
		$cpanel				= $this->SFA_Comman->getaltcodestatus();
		$altcode_status		= $cpanel["Use Alternate Code"]['status'];
		
		if($altcode_status)
		{
			$cols_array[0] = 'alternatecode';
		}
		
		
		$str 	= explode('_',$params['id']);
		$route  = $str[0];
		$month  = $str[1];
		$year   = $str[2];
		
		$Common_NameSpace = new Zend_Session_Namespace('MonthClose');
		$Common_NameSpace->troute	= $route;
		$this->view->month 	= $Common_NameSpace->tmonth	= $month;
		$this->view->year 	= $Common_NameSpace->tyear	= $year;
		
		
		// ADDITIONAL WHERE CONDITION
		if($Common_NameSpace->tmonth) {
			$additional_where_condition[] = " bmonth = ".$Common_NameSpace->tmonth;
		}
		if($Common_NameSpace->tyear) {
			$additional_where_condition[] = " byear = ".$Common_NameSpace->tyear;
		}
		if($Common_NameSpace->troute) {
			$additional_where_condition[] = " routecode = ".$Common_NameSpace->troute;
		}
		
		// prepare the configuration for grid
		$pagingparams = array(
							"show_grid_heading" => true,
							"show_records_per_page" => isset($formdata["show_records_per_page"])?$formdata["show_records_per_page"]:10,
							"show_searchbox" => false,
							"pagename" => $this->translate->_('Month Close'),
							"show_selectbox" => false,
							"show_editlink" => false,
							"show_deletelink" => false,
							"show_deleteall" => false,
							"nodata_message" => $this->translate->_('No Record(s) Found'),
							"fetch_columns_inquery" => $cols_array,
							"show_columns" => $columns_show,
							"additional_where" => $additional_where_condition,
							"show_top_columns" => false,
							"show_top_columns_value" => array(array("3",""),array("3",$this->translate->_('Quantity')),array("4",$this->translate->_('Quantity')),array("6",$this->translate->_("Quantity")),array("1","")),
							);
		
		// create grid class object
		$pagingshow 		= new SFA_Paging($pagingparams);
		$get_return_vals 	= $pagingshow->commnfunc();

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
		$result = $this->SFA_Comman->executequery('CALL sp_get_account_transaction_monthcloseview(?,?,?,?,?,?,?,?)',$param_array,'',$downloadCSV,$printData,2,$pagingparams);
		$data_arr["count"] 		= $result[0][0]['counter'];
		$data_arr["data"][0] 	= $result[1];
		
		// pass the data in summary_showdatagrid() function & create a final variable for view
		$this->view->pagegridshow =  $pagingshow->summary_showdatagrid($data_arr);
		
		$this->view->addScriptPath(APPLICATION_PATH."/theme/default/datagrid/");		
    }
    /**
    * @name       monthclose
    * @since      17-2-2012
    * @version    Release: 1
    * @author     PM <pankit@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This Action is for coustomer sequence flow
    */
    public function monthcloseAction() {
		
		$formdata = $this->_request->getPost();
		
		if($formdata['ddlmonth'] !='' && $formdata['ddlyear'] !='') {
			
			$param_array 	= array();
			$param_array[1] = $formdata['ddlmonth'];
			$param_array[2] = $formdata['ddlyear'];
			
			$message = $this->SFA_Comman->executequery('CALL sp_add_account_transaction_monthclose(?)',$param_array,'');
			if($message[0][0]['msg'] == 'Failed') {
				SFA_Message::setErrorMsg($this->translate->_('This Month Transaction Was Already Close.'));				
			}
			else {
				SFA_Message::setMsg($this->translate->_('New Record'));
				$this->_helper->redirector("monthcloseinfo", "transaction", "account");
			}
		}
		
		$fromyear = array();
        $start=2000;
        $noofyears = 50;
        $upto = $start+$noofyears;
        $j=0;
        for($i=$start;$i<=$upto; $i++){
            $fromyear[$j]['id'] = $i;
            $fromyear[$j]['val'] = $i;
            $j++;
        }
        $this->view->years = $fromyear;
		
		$months = array();
        $months[0]['id'] = 1;
        $months[0]['val'] = 'January';
        $months[1]['id'] = 2;
        $months[1]['val'] = 'February';
        $months[2]['id'] = 3;
        $months[2]['val'] = 'March';
        $months[3]['id'] = 4;
        $months[3]['val'] = 'April';
        $months[4]['id'] = 5;
        $months[4]['val'] = 'May';
        $months[5]['id'] = 6;
        $months[5]['val'] = 'June';
        $months[6]['id'] = 7;
        $months[6]['val'] = 'July';
		$months[7]['id'] = 8;
        $months[7]['val'] = 'August';
        $months[8]['id'] = 9;
        $months[8]['val'] = 'September';
        $months[9]['id'] = 10;
        $months[9]['val'] = 'Octomber';
        $months[10]['id'] = 11;
        $months[10]['val'] = 'November';
        $months[11]['id'] = 12;
        $months[11]['val'] = 'December';
		
		$this->view->months = $months;
		//else {
		//	$message = $this->SFA_Comman->executequery('CALL sp_add_account_transaction_monthclose(?)',date('d-m-Y'),'');			
		//	if($message[0][0]['msg'] == 'Failed') {
		//		SFA_Message::setErrorMsg($this->translate->_('This Month Transaction Was Already Close.'));
		//	}
		//}
    }

    /**
    * @name       gccollectionAction
    * @since      25-01-2012
    * @version    Release: 1
    * @author     HD <hiren.d@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This action is use for the display GC Collection
    */
    public function gccollectionAction()
    {
		$this->view->params = $params = $this->getRequest()->getParams();
        $this->view->formdata = $formdata = $this->_request->getPost();
		
		if($params['delid'] > 0)
		{
			$ids = $params['delid'];
			
			$param_array 	= array();
			$param_array[1]	= $ids;
			$param_array[2]	= $this->currentUser->username;
			
			$result 	= $this->SFA_Comman->executequery('CALL sp_delete_account_transaction_gccollection(?,?)',$param_array,'');
			
			if($result[0][0]['result'] == 'Not Found') {
				SFA_Message::setErrorMsg($this->translate->_('Record Already In use'));
			} else {
				SFA_Message::setMsg($this->translate->_('Delete Record'));	
			}
			$this->_helper->redirector("gccollection", "transaction", "account");
		}

		if($formdata["hdDelete"]==1)
		{		
			$ids = implode(',',$formdata['chk']);
			
			$param_array 	= array();
			$param_array[1]	= $ids;
			$param_array[2]	= $this->currentUser->username;
			
			$result 	= $this->SFA_Comman->executequery('CALL sp_delete_account_transaction_gccollection(?,?)',$param_array,'');
			
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
				
				$this->_helper->redirector("gccollection", "transaction", "account");
			}
		}				
				
		$Common_NameSpace = new Zend_Session_Namespace('GCCollection');
		if($formdata['btnreset'] == 'RESET')
		{
			$formdata["txtdate"] 	= '';
			$Common_NameSpace->tdate	= '';
		}
		$last_url = htmlspecialchars($_SERVER['HTTP_REFERER']);
		if(strpos($last_url,'gccollection'))
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
		
		if($altcode_status)
		{	
			$cols_array = array('documentnumber','routename','alternatecode','customername','FORMAT(totalinvoiceamount,'.$this->decimalplaces.') AS totalinvoiceamount','FORMAT(amountpaid,'.$this->decimalplaces.') AS amountpaid','transactionkey as edit_del_primary_id');
			$columns_show =  array($this->translate->_('Document No'),$this->translate->_('Route Name'),$this->translate->_('Customer Code'),$this->translate->_('Customer Name'),$this->translate->_('Invoice Amount'),$this->translate->_('Amount Paid'));
		}
		else
		{
			$cols_array = array('documentnumber','routename','cm.customercode','customername','FORMAT(totalinvoiceamount,'.$this->decimalplaces.') AS totalinvoiceamount','FORMAT(amountpaid,'.$this->decimalplaces.') AS amountpaid','transactionkey as edit_del_primary_id');
			$columns_show =  array($this->translate->_('Document No'),$this->translate->_('Route Name'),$this->translate->_('Customer Code'),$this->translate->_('Customer Name'),$this->translate->_('Invoice Amount'),$this->translate->_('Amount Paid'));
		}
		
		// prepare the configuration for grid
		$pagingparams = array(
								"show_grid_heading" => true,
								"grid_heading_message" => $this->translate->_('Overview'),
								"show_records_per_page" => isset($formdata["show_records_per_page"])?$formdata["show_records_per_page"]:10,
								"show_searchbox" => true, "searchbox_value" => isset($formdata["grid_search_text"])?$formdata["grid_search_text"]:"",
								"show_selectbox" => true,
								"show_editlink" => false,								
								"show_deletelink" => false,			
								"show_deleteall" => false,
								"primaryid" => "transactionkey",
								"pagename" => $this->translate->_('GC Collection'),
								"show_extralink" => true,
								"extralink" => array(array("View","/".$params['module']."/".$params['controller']."/addgccollection/id/#pattern#/view/yes/","#pattern#")),
								"nodata_message" => $this->translate->_('No Record(s) Found'),
								"fetch_columns_inquery" => $cols_array,
								"show_columns" => $columns_show,
								"show_columns_right_side"=>array('totalinvoiceamount','amountpaid'),
								"show_header_right_side"=>array($this->translate->_('Invoice Amount'),$this->translate->_('Amount Paid')),
								"additional_where" => $additional_where_condition
							);
		
		// create grid class object
		$pagingshow = new SFA_Paging($pagingparams);
		
		// call common function of grid class
		$get_return_vals = $pagingshow->commnfunc();
		
		//print_r($get_return_vals['where_condition']);
		
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
		
		$downloadCSV = (isset($formdata['downloadcsv'])) ? $formdata['downloadcsv'] : $params['downloadcsv'];
    
    // Hiren Dave on 19 Nov, 2012 - START - to check if need to print data
	$printData = (isset($formdata['printData'])) ? $formdata['printData'] : $params['printData'];
		// called stored procedure for counter
		$result = $this->SFA_Comman->executequery('CALL sp_get_account_transaction_gccollection(?,?,?,?,?,?,?,?)',$param_array,'',$downloadCSV,$printData,2,$pagingparams);
		$data_arr["count"] 		= $result[0][0]['counter'];
		$data_arr["data"][0] 	= $result[1];
		
		// pass the data in summary_showdatagrid() function & create a final variable for view
		$this->view->pagegridshow =  $pagingshow->summary_showdatagrid($data_arr);
		
		$this->view->addScriptPath(APPLICATION_PATH."/theme/default/datagrid/");		
    }
    /**
    * @name       addgccollectionAction
    * @since      26-01-2012
    * @version    Release: 1
    * @author     HD <hiren.d@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This action is use for the add GC Collection
    */
    public function addgccollectionAction()
    { //$this->_helper->redirector('gccollection', 'transaction', 'account');
		$this->view->params = $params = $this->getRequest()->getParams();
        $this->view->formdata = $formdata = $this->_request->getPost();
		
		// IF EXTRA PARAMS ARE REQUIRED
		$ex_param = "";		
		if(isset($params["id"]) && $params["id"]>0) {
			$ex_param 	= "/key/".$params["id"];
			$ex_param 	.= "/view/yes";
		}
		
		$this->view->itemgrid    = $this->view->BaseUrl("/".$params['module']."/".$params['controller']."/gccollectiongrid".$ex_param);
		
		$Common_NameSpace = new Zend_Session_Namespace('GCCollection');
	
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
		$ttype[0]['id']  = 2;
		$ttype[0]['val'] = 'Cash/Cheque';		
		$this->view->ddltrxn_type = $ttype;
		
		$ttype = array();
		$ttype[0]['id']  = 0;
		$ttype[0]['val'] = 'Cash';
		$ttype[1]['id']  = 1;
		$ttype[1]['val'] = 'Cheque';
		$this->view->payment_mode = $ttype;

		
		if(count($formdata) > 0)
		{
			$formdata['hdninvono'] = ($formdata['hdncheckinvo'] == 1)  ? $formdata['hdninvono']."$" : $formdata['hdninvono'];			
			
			$param_array 		= array();
			$param_array[1]		= $formdata['ddlroute'];
			$param_array[2]		= $formdata['hdnsalesman'];
			$param_array[3]		= $formdata['ddlcustomer'];
			$param_array[4]		= '2';
			$param_array[5]		= str_replace(',','',$formdata['hdntxtamount']);
			$param_array[6]		= str_replace(',','',$formdata['hdntotalinvoamt']);
			$param_array[7]		= $formdata['hdninvono'];
			$param_array[8]		= $formdata['hdncheckinvo'];
			$param_array[9]		= (!$formdata['ddlpaymode']) ? 0 : 1;
			$param_array[10]	= $formdata['txtcheckno'];
			$param_array[11]	= $formdata['txtcheckdt'];
			$param_array[12]	= $formdata['ddlbankname'];
			$param_array[13] 	= $Common_NameSpace->tdate;
			$param_array[14]	= $this->currentUser->username;
			$param_array[15]	= $formdata['txterprefnum'];
			$param_array[16]	= $formdata['hdninvoamt'];
			$param_array[17]	= $formdata['chkfirstout'];
			
			$result = $this->SFA_Comman->executequery('CALL sp_add_account_transaction_addgccollection(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',$param_array,'');
			SFA_Message::setMsg($this->translate->_('New Record'));			
			$this->_helper->redirector('gccollection', 'transaction', 'account');
		}
		elseif($params['id'] > 0)
		{
			$param_array 		= array();
			$param_array[1]		= $params['id'];
			//$param_array[2]	= $Common_NameSpace->tdate;
			
			$result = $this->SFA_Comman->executequery('CALL sp_get_account_transaction_addgccolletion(?)',$param_array,'');
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
			
			$result = $this->SFA_Comman->executequery('CALL sp_get_account_transaction_addgccolletion(?)',$param_array,'');
			$this->view->route 	= $result[0];
			$this->view->bank 	= $result[1];
			$this->view->formdata['hdnmaxid']	= ($result[2][0]['Auto_increment'] > 0) ? $result[2][0]['Auto_increment'] : 1;
		}
    }	

    /**
    * @name       gccollectiongridAction
    * @since      26-01-2012
    * @version    Release: 1
    * @author     PM <pankit@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This action is use for the add GC Collection
    */
    public function gccollectiongridAction(){
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
			$additional_where_condition[] = ' (t1.customercode = "'.$params["key2"].'") ';
			$additional_where_condition[] = ' (t1.invoicebalance <> 0) ';			
		}
		
		if(isset($params["view"]) && $params["view"] == 0) {
			$additional_where_condition[]  = ' (t1.transactionkey = "'.$params["key"].'") ';
			$additional_where_condition[1] = ' 1 = 1  ';
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
					"show_calculate_button" => true,
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
			$result = $this->SFA_Comman->executequery('CALL sp_get_account_transaction_addgccollectioninvogrid(?,?,?,?,?,?,?)',$param_array,'');
		}
		else {
			$result = $this->SFA_Comman->executequery('CALL sp_get_account_transaction_addgccollectiongrid(?,?,?,?,?,?,?)',$param_array,'');
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
		$result = $this->SFA_Comman->executequery('CALL sp_get_account_transaction_invoicetotal(?)',$ids,'');
		$res[0] = number_format($result[0][0]['invoiceamt'],$this->decimalplaces);
		$res[1] = number_format($result[0][0]['amountpaid'],$this->decimalplaces);
		$res[2] = number_format($result[0][0]['balanceamt'],$this->decimalplaces);
		echo Zend_Json::encode($res);
		exit;
	}
	
	/**
    * @name       hocollectionAction
    * @since      25-01-2012
    * @version    Release: 1
    * @author     HD <hiren.d@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This action is use for the display GC Collection
    */
    public function hocollectionAction()
    {
		$this->view->params = $params = $this->getRequest()->getParams();
        $this->view->formdata = $formdata = $this->_request->getPost();
		
		if($params['delid'] > 0)
		{
			$ids = $params['delid'];
			
			$param_array 	= array();
			$param_array[1]	= $ids;
			$param_array[2]	= $this->currentUser->username;
			
			$result 	= $this->SFA_Comman->executequery('CALL sp_delete_account_transaction_hocollection(?,?)',$param_array,'');
			
			if($result[0][0]['result'] == 'Not Found') {
				SFA_Message::setErrorMsg($this->translate->_('Record Already In use'));
			} else {
				SFA_Message::setMsg($this->translate->_('Delete Record'));	
			}
			$this->_helper->redirector("hocollection", "transaction", "account");
		}

		if($formdata["hdDelete"]==1)
		{
			$ids = implode(',',$formdata['chk']);
				
			$param_array 	= array();
			$param_array[1]	= $ids;
			$param_array[2]	= $this->currentUser->username;
			
			$result 	= $this->SFA_Comman->executequery('CALL sp_delete_account_transaction_hocollection(?,?)',$param_array,'');
			
			
			
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
				
				$this->_helper->redirector("hocollection", "transaction", "account");
			}
		}				
				
		$Common_NameSpace = new Zend_Session_Namespace('HOCollection');
		if($formdata['btnreset'] == 'RESET')
		{
			$formdata["txtdate"] 	= '';
			$Common_NameSpace->tdate	= '';
		}
		$last_url = htmlspecialchars($_SERVER['HTTP_REFERER']);
		if(strpos($last_url,'hocollection'))
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
		
		if($altcode_status)
		{	
			$cols_array = array('documentnumber','routename','alternatecode','customername','FORMAT(totalinvoiceamount,'.$this->decimalplaces.') AS totalinvoiceamount','FORMAT(amountpaid,'.$this->decimalplaces.') AS amountpaid','transactionkey as edit_del_primary_id');
			$columns_show =  array($this->translate->_('Document No'),$this->translate->_('Route Name'),$this->translate->_('Customer Code'),$this->translate->_('Customer Name'),$this->translate->_('Invoice Amount'),$this->translate->_('Amount Paid'));
		}
		else
		{
			$cols_array = array('documentnumber','routename','cm.customercode','customername','FORMAT(totalinvoiceamount,'.$this->decimalplaces.') AS totalinvoiceamount','FORMAT(amountpaid,'.$this->decimalplaces.') AS amountpaid','transactionkey as edit_del_primary_id');
			$columns_show =  array($this->translate->_('Document No'),$this->translate->_('Route Name'),$this->translate->_('Customer Code'),$this->translate->_('Customer Name'),$this->translate->_('Invoice Amount'),$this->translate->_('Amount Paid'));
		}
	
		if($this->css == 'ar_') {
			$cols_array[1]	= 'arbroutename';
			$cols_array[3]	= 'arbcustoemrname';
		}
		
		// prepare the configuration for grid
		$pagingparams = array(
								"show_grid_heading" => true,
								"grid_heading_message" => $this->translate->_('Overview'),
								"show_records_per_page" => isset($formdata["show_records_per_page"])?$formdata["show_records_per_page"]:10,
								"show_searchbox" => true, "searchbox_value" => isset($formdata["grid_search_text"])?$formdata["grid_search_text"]:"",
								"show_selectbox" => true,
								"show_editlink" => false,
								"show_deletelink" => false,			
								"show_deleteall" => false,
								"primaryid" => "transactionkey",
								"pagename" => $this->translate->_('HO Collection'),
								"show_extralink" => true,
								"extralink" => array(array("View","/".$params['module']."/".$params['controller']."/addhocollection/id/#pattern#/view/yes/","#pattern#")),
								"nodata_message" => $this->translate->_('No Record(s) Found'),
								"fetch_columns_inquery" => $cols_array,
								"show_columns" => $columns_show,
								"show_columns_right_side"=>array('totalinvoiceamount','amountpaid','invoicebalance'),
								"show_header_right_side"=>array($this->translate->_('Invoice Amount'),$this->translate->_('Amount Paid')),
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
		$result = $this->SFA_Comman->executequery('CALL sp_get_account_transaction_hocollection(?,?,?,?,?,?,?,?)',$param_array,'',$downloadCSV,$printData,2,$pagingparams);
		$data_arr["count"] 		= $result[0][0]['counter'];
		$data_arr["data"][0] 	= $result[1];
		
		// pass the data in summary_showdatagrid() function & create a final variable for view
		$this->view->pagegridshow =  $pagingshow->summary_showdatagrid($data_arr);
		
		$this->view->addScriptPath(APPLICATION_PATH."/theme/default/datagrid/");		
    }
    /**
    * @name       addhocollectionAction
    * @since      26-01-2012
    * @version    Release: 1
    * @author     HD <hiren.d@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This action is use for the add GC Collection
    */
    public function addhocollectionAction()
    { //$this->_helper->redirector('hocollection', 'transaction', 'account');
		$this->view->params = $params = $this->getRequest()->getParams();
        $this->view->formdata = $formdata = $this->_request->getPost();
		
		// IF EXTRA PARAMS ARE REQUIRED
		$ex_param = "";		
		if(isset($params["id"]) && $params["id"]>0) {
			$ex_param 	= "/key/".$params["id"];
			$ex_param 	.= "/view/yes";
		}		
		
		$this->view->itemgrid    = $this->view->BaseUrl("/".$params['module']."/".$params['controller']."/hocollectiongrid".$ex_param);
		
		$Common_NameSpace = new Zend_Session_Namespace('HOCollection');
	
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
		$ttype[0]['id']  = 2;
		$ttype[0]['val'] = 'Cash/Cheque';		
		$this->view->ddltrxn_type = $ttype;
		
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
			$param_array[4]		= '1';
			$param_array[5]		= str_replace(',','',$formdata['hdntxtamount']);
			$param_array[6]		= str_replace(',','',$formdata['hdntotalinvoamt']);			
			$param_array[7]		= $formdata['hdninvono'];
			$param_array[8]		= $formdata['hdncheckinvo'];
			$param_array[9]		= (!$formdata['ddlpaymode']) ? 0 : 1;
			$param_array[10]	= $formdata['txtcheckno'];
			$param_array[11]	= $formdata['txtcheckdt'];
			$param_array[12]	= $formdata['ddlbankname'];
			$param_array[13] 	= $Common_NameSpace->tdate;
			$param_array[14]	= $this->currentUser->username;
			$param_array[15]	= $formdata['txterprefnum'];
			$param_array[16]	= $formdata['hdninvoamt'];
			$param_array[17]	= $formdata['chkfirstout'];
			
			
			$result = $this->SFA_Comman->executequery('CALL sp_add_account_transaction_addhocollection(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',$param_array,'');
			SFA_Message::setMsg($this->translate->_('New Record'));
			
			$this->_helper->redirector('hocollection', 'transaction', 'account');
		}
		elseif($params['id'] > 0)
		{
			$param_array 		= array();
			$param_array[1]		= $params['id'];
			
			$result = $this->SFA_Comman->executequery('CALL sp_get_account_transaction_addhocollection(?)',$param_array,'');
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
			
			$result = $this->SFA_Comman->executequery('CALL sp_get_account_transaction_addhocollection(?)',$param_array,'');			
			$this->view->route 	= $result[0];
			$this->view->bank 	= $result[1];
			$this->view->formdata['hdnmaxid']	= ($result[2][0]['Auto_increment'] > 0) ? $result[2][0]['Auto_increment'] : 1;
		}
    }

    /**
    * @name       hocollectiongridAction
    * @since      26-01-2012
    * @version    Release: 1
    * @author     PM <pankit@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This action is use for the add GC Collection
    */
    public function hocollectiongridAction(){
        $this->view->params = $params = $this->getRequest()->getParams();
		
		if(isset($params["view"]) && $params["view"] == 0) {			
			$columns_array 	= array('t1.invoicenumber','DATE_FORMAT(t1.invoicedate,"%d-%m-%Y") AS trandate','t2.customercode','FORMAT(t1.totalinvoiceamount,'.$this->decimalplaces.') as totalinvoiceamount','FORMAT(t1.amountpaid,'.$this->decimalplaces.') as amountpaid','FORMAT(t3.invoicebalance,'.$this->decimalplaces.') as invoicebalance','FORMAT(pdcbalance,'.$this->decimalplaces.') as pdcbalance','t1.transactionkey as edit_del_primary_id');
			$columns_show  	= array($this->translate->_('Invoice No'),$this->translate->_('Invoice Date'),$this->translate->_('Customer Code'),$this->translate->_('Invoice Amount'),$this->translate->_('Amount Paid'),$this->translate->_('Present Balance'),$this->translate->_('PDC Balance'));
		}
		else {
			$columns_array 	= array('t1.invoicenumber','DATE_FORMAT(t1.transactiondate,"%d-%m-%Y") AS trandate','t2.customercode','FORMAT(t1.totalinvoiceamount,'.$this->decimalplaces.') as totalinvoiceamount','FORMAT(t1.amountpaid,'.$this->decimalplaces.') as amountpaid','FORMAT(t1.invoicebalance,'.$this->decimalplaces.') as invoicebalance','FORMAT(pdcbalance,'.$this->decimalplaces.') as pdcbalance','t1.transactionkey as edit_del_primary_id');
			$columns_show  	= array($this->translate->_('Invoice No'),$this->translate->_('Invoice Date'),$this->translate->_('Customer Code'),$this->translate->_('Invoice Amount'),$this->translate->_('Amount Paid'),$this->translate->_('Present Balance'),$this->translate->_('PDC Balance'));
		}
		
		// IF EXTRA PARAMS ARE REQUIRED
		$ex_param = "";
		$additional_where_condition = array();
		if(isset($params["key1"]) && $params["key1"]>0) {
			$ex_param = "/key1/".$params["key1"]."/key2/".$params["key2"];
			$additional_where_condition[] = ' (t2.headofficecode = "'.$params["key2"].'") ';
			$additional_where_condition[] = ' (t1.invoicebalance <> 0) ';
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
					"show_extratextboxtitle" => $this->translate->_('Current Amount'),
					"show_datasorting" => '1',
					"primaryid" => "transactionkey",
					"currentlink" => array("/".$params['module']."/".$params['controller']."/".$params['action'].$ex_param),
					"nodata_message" => $this->translate->_('No Record(s) Found'),
					"fetch_columns_inquery" => $columns_array,
					"show_columns" => $columns_show,
					"show_columns_right_side"=>array('totalinvoiceamount','amountpaid','invoicebalance','pdcbalance'),
					"show_header_right_side"=>array($this->translate->_('Invoice Amount'),$this->translate->_('Amount Paid'),$this->translate->_('Present Balance'),$this->translate->_('Present Balance'),$this->translate->_('PDC Balance')),
					"additional_where" => $additional_where_condition,
					"show_calculate_button" => true,
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
			$result = $this->SFA_Comman->executequery('CALL sp_get_account_transaction_addhocollectioninvogrid(?,?,?,?,?,?)',$param_array,'');
		}else {
			$result = $this->SFA_Comman->executequery('CALL sp_get_account_transaction_addhocollectiongrid(?,?,?,?,?,?)',$param_array,'');			
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
    * @name       openingbalAction
    * @since      27-01-2012
    * @version    Release: 1
    * @author     HD <hiren.d@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This action is use for the Display opening balance
    */
    public function openingbalAction()
    {
		$this->view->params = $params = $this->getRequest()->getParams();
        $this->view->formdata = $formdata = $this->_request->getPost();

		if($params['delid'] > 0)
		{
			$ids = $params['delid'];
			
			$param_array 	= array();
			$param_array[1]	= $ids;
			$param_array[2]	= $this->currentUser->username;
			
			$result 	= $this->SFA_Comman->executequery('CALL sp_delete_account_transaction_openingbal(?,?)',$param_array,'');
			
			if($result[0][0]['result'] == 'Not Found') {
				SFA_Message::setErrorMsg($this->translate->_('Record Already In use'));
			} else {
				SFA_Message::setMsg($this->translate->_('Delete Record'));	
			}
			
			$this->_helper->redirector("openingbal", "transaction", "account");
		}
		
		if($formdata["hdDelete"]==1)
		{
			
			$ids = implode(',',$formdata['chk']);			
				
			$param_array 	= array();
			$param_array[1]	= $ids;
			$param_array[2]	= $this->currentUser->username;
			
			$result 	= $this->SFA_Comman->executequery('CALL sp_delete_account_transaction_openingbal(?,?)',$param_array,'');
			
			$deleted_id 	= explode(',',$result[0][0]['deleted_id']);
			$ids			= explode(',',$ids);
			$checked 		= array_diff($ids,$deleted_id);
			
			if(count($ids) != count($deleted_id)) {					
				SFA_Message::setErrorMsg($this->translate->_('Record Already In use'));
				SFA_Message::setMsg($this->translate->_('Delete Record'));
			}
			elseif($result[0][0]['result'] == 'Not Found') {
				if(empty($checked))
					$checked = $deleted_id;
				SFA_Message::setErrorMsg($this->translate->_('Record Already In use'));
			}
			else				
				SFA_Message::setMsg($this->translate->_('Delete Record'));			
			
			$this->_helper->redirector("openingbal", "transaction", "account");
		}
		
		$last_url = htmlspecialchars($_SERVER['HTTP_REFERER']);
		
		$Common_NameSpace = new Zend_Session_Namespace('Opening_Balance');		
		if($formdata['btnreset'] == 'RESET')
		{
			$formdata["txtdate"] 	= '';
			$Common_NameSpace->tdate	= '';
		}		
		if(strpos($last_url,'openingbal'))
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
		
		
		$cpanel				= $this->SFA_Comman->getaltcodestatus();
		$altcode_status		= $cpanel["Use Alternate Code"]['status'];
		
		$cols_array = array('invoicenumber','rm.routecode','routename','cm.customercode','customername','FORMAT(totalinvoiceamount,'.$this->decimalplaces.') AS amountpaid','transactionkey as edit_del_primary_id');
		$columns_show =  array($this->translate->_('Invoice Number'),$this->translate->_('Route Code'),$this->translate->_('Route Name'),$this->translate->_('Customer Code'),$this->translate->_('Customer Name'),$this->translate->_('Amount'));
		
		if($this->css == 'ar_') {
			$cols_array[2]	= 'arbroutename';
			$cols_array[4]	= 'arbcustomername';
		}
		
		if($altcode_status)
			$cols_array[3] 		= 'cm.alternatecode';
	
		// prepare the configuration for grid
		$pagingparams = array(
								"show_grid_heading" => true,
								"grid_heading_message" => $this->translate->_('Overview'),
								"show_records_per_page" => isset($formdata["show_records_per_page"])?$formdata["show_records_per_page"]:10,
								"show_searchbox" => true, "searchbox_value" => isset($formdata["grid_search_text"])?$formdata["grid_search_text"]:"",
								"show_selectbox" => true,
								"show_editlink" => false,
								"show_deletelink" => false,
								"pagename" => $this->translate->_('Opening Balance'),
								"selected_list" => $checked,
								"show_deleteall" => false,
								"primaryid" => "transactionkey",
								"show_extralink" => true,
								"extralink" => array(array("View","/".$params['module']."/".$params['controller']."/addopeningbal/id/#pattern#/view/yes/","#pattern#")),
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
		$result = $this->SFA_Comman->executequery('CALL sp_get_inventory_transaction_openingbalance(?,?,?,?,?,?,?,?)',$param_array,'',$downloadCSV,$printData,2,$pagingparams);
		$data_arr["count"] 		= $result[0][0]['counter'];
		$data_arr["data"][0] 	= $result[1];
		
		// pass the data in summary_showdatagrid() function & create a final variable for view
		$this->view->pagegridshow =  $pagingshow->summary_showdatagrid($data_arr);
		
		$this->view->addScriptPath(APPLICATION_PATH."/theme/default/datagrid/");
    }
    /**
    * @name       addopeningbalAction
    * @since      27-01-2012
    * @version    Release: 1
    * @author     HD <hiren.d@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This action is use for the add opening balance
    */
    public function addopeningbalAction()
    {
		$this->view->params = $params = $this->getRequest()->getParams();
        $this->view->formdata = $formdata = $this->_request->getPost();
		
		$Common_NameSpace 	= new Zend_Session_Namespace('Opening_Balance');
		$sel_date 			= $Common_NameSpace->tdate;
	
		// ADD DATE VALUE IN SESSION
		if($sel_date != '') {
			$Common_NameSpace->tdate 	= $sel_date;
			$this->view->date			= $sel_date;
		} else {
			$Common_NameSpace->tdate 	= date('d-m-Y');
			$this->view->date			= date('d-m-Y');
		}
		
		if(count($formdata) > 0)
		{
			$param_array 		= array();			
			$param_array[1]		= $formdata['ddlroute'];
			$param_array[2]		= $formdata['hdnsalesman'];
			$param_array[3]		= $formdata['ddlcustomer'];
			$param_array[4]		= 1;
			$param_array[5]		= str_replace(',','',$formdata['txtamount']);
			$param_array[6]		= $formdata['txtremark1'];
			$param_array[7]		= $formdata['txtremark2'];
			$param_array[8] 	= $Common_NameSpace->tdate;
			$param_array[9] 	= $formdata['txterpno'];
			$param_array[10]	= $this->currentUser->username;			
			
			$result = $this->SFA_Comman->executequery('CALL sp_add_account_transaction_addopeningbal(?,?,?,?,?,?,?,?,?,?)',$param_array,'');
			SFA_Message::setMsg($this->translate->_('New Record'));
			
			$this->_helper->redirector('openingbal', 'transaction', 'account');
		}
		elseif($params['id'] > 0)
		{
			$result = $this->SFA_Comman->executequery('CALL sp_get_account_transaction_addopeningbalance(?)',$params['id'],'');
			$this->view->route 		= $result[0];			
			$this->view->formdata 	= $result[1][0];
		}
		else
		{
			$result = $this->SFA_Comman->executequery('CALL sp_get_account_transaction_addopeningbalance(?)','0','');
			$this->view->route 					= $result[0];
		}
    }
}