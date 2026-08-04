<?php
/**
* @name       IndexController
* @since
* @version    Release: 1
* @author     M@M <miral@elantechnologies.com>
* @copyright  Elan Technologies
* @param
*
* This controller is manage hhctransaction module.
*/


class Hhctransaction_InvoiceController extends Hhctransaction_Library_Controller_Action_Abstract
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
	$this->SFA_Comman	= new SFA_Comman();
	
	$this->currentUser = SFA_Loginauth::getIdentity();	
	if(!isset($this->currentUser) || empty($this->currentUser))
	{
	    SFA_Message::setMsg($this->translate->_('Do Login'));
	    //$this->_helper->redirector("index", "index", "home");
			$url = $this->view->baseUrl();
			echo '<script type="text/javascript">window.location="'.$url.'";</script>';
			exit;
	}
	$this->view->overview		= $this->translate->_('Overview');
	$this->view->details		= $this->translate->_('Details');
	$this->view->required		= $this->translate->_('Required');
	$this->view->colan		= $this->translate->_('Colan');
	
	
	$this->decimalplaces 		= $this->SFA_Comman->getdecimalplaces();
	$this->view->decimalplaces 	= $this->SFA_Comman->getdecimalplaces();
	$this->view->sec_lang		= $this->SFA_Comman->getsecondlanguage();
	$this->sec_lang 		= $this->view->sec_lang;
	$this->view->header = $this->translate->_('Header');
	$this->view->detail = $this->translate->_('Detail');
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
    
    
    
    public function itempriceAction()
    {
	$this->view->params = $params = $this->getRequest()->getParams();
	$this->view->formdata = $formdata = $this->_request->getPost();
	//print_r($this->view->params);
	//exit;
	$param_array =array();
	$param_array[1] =$this->view->params['item_id'];
	$param_array[2] =$this->view->params['customer_code'];
	$result 	= $this->SFA_Comman->executequery('CALL sp_get_transaction_invoice_addinvouce_itemprice(?,?)',$param_array,'');
	$result_Arr =$result[0][0];
	echo $result_Arr['sales_price']."$::$".$result_Arr['return_price']."$::$".$result_Arr['actualitemcode']."$::$".$result_Arr['unitspercase']."$::$".$result_Arr['itemshortdescription'];
	exit;
	
    }
    
    public function invoiceAction()
    {
		$this->view->params = $params = $this->getRequest()->getParams();
		$this->view->formdata = $formdata = $this->_request->getPost();
		
		if($formdata["hdDelete"]==1)
		{
			$ids = implode(',',$formdata['chk']);
			$param_array 	= array();
			$param_array[1]	= $ids;
			$param_array[2]	= $this->currentUser->username;
			
			$result 	= $this->SFA_Comman->executequery('CALL sp_delete_hhctransaction_invoice_invoice(?,?)',$param_array,'');
			
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
		
		
		// For Alternate Code Status.
		$cpanel				= $this->SFA_Comman->getaltcodestatus();
		$altcode_status		= $cpanel["Use Alternate Code"]['status'];
		
		if($altcode_status) {
			$cols_array = array('cm.alternatecode','customername','invoicenumber','salesmanname1','IF(voidflag = 0,"NORMAL","VOIDED") AS voidflag','FORMAT(totalinvoiceamount,'.$this->decimalplaces.') AS totalinvoiceamount','transactionkey as edit_del_primary_id' );
		} else {
			$cols_array = array('cm.customercode','customername','invoicenumber','salesmanname1','IF(voidflag = 0,"NORMAL","VOIDED") AS voidflag','FORMAT(totalinvoiceamount,'.$this->decimalplaces.') AS totalinvoiceamount','transactionkey as edit_del_primary_id' );
		}
		
		
		$columns_show =  array(
			  $this->translate->_('Customer Code'),	
			  $this->translate->_('Customer'),
			  $this->translate->_('Invoice No'),
			  //$this->translate->_('Route'),
			  $this->translate->_('Salesman'),
			  $this->translate->_('Invoice Status'),			  
			  $this->translate->_('Amount'));
			
		$not_in_search 		= array();
		$not_in_search[] 	= 'transactiondate';
		
		$Common_NameSpace = new Zend_Session_Namespace('Invoice');
		
		if($formdata['btnreset'] == 'RESET')
		{
			$formdata["txtdate"] 	= '';
			$Common_NameSpace->tdate	= '';
		}
		
		$last_url = htmlspecialchars($_SERVER['HTTP_REFERER']);
		if(strpos($last_url,'invoice'))
		{
			// CREATE A SESSION NAMESPACE
			$Common_NameSpace = new Zend_Session_Namespace('Invoice');
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
			$additional_where_condition[] = " (transactiondate BETWEEN \'".date("Y-m-d 00:00:00",strtotime(str_replace('/', '-', $Common_NameSpace->tdate)))."\' AND \'".date("Y-m-d 23:59:59",strtotime(str_replace('/', '-', $Common_NameSpace->tdate)))."\' ) AND record_flag = \'1\' ";
		
		// prepare the configuration for grid
		$pagingparams = array(
				 "show_grid_heading" => true,
				 "grid_heading_message" => $this->translate->_('Overview'),
				 "show_records_per_page" => isset($formdata["show_records_per_page"])?$formdata["show_records_per_page"]:10,
				 "show_searchbox" => true,
				 "searchbox_value" => isset($formdata["grid_search_text"])?$formdata["grid_search_text"]:"",
				 "show_selectbox" => true,
				 "show_editlink" => false,
				 "show_deletelink" => false,
				 "show_deleteall" => false,
				 'show_extralink' => true,
				 'extralink' => array(array("View","/".$params['module']."/".$params['controller']."/addinvoice/id/#pattern#","#pattern#")),
				 "no_search_fields" => $not_in_search,
				 "primaryid" => "transactionkey",			 
				 "nodata_message" => $this->translate->_('No Record(s) Found'),
				 "fetch_columns_inquery" => $cols_array,
				 "show_columns" => $columns_show,
				 "additional_where" => $additional_where_condition,
				 "show_columns_right_side"=>array('totalinvoiceamount'),
				 "show_header_right_side"=>array($this->translate->_('Amount')),
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
		
		$result 	= $this->SFA_Comman->executequery('CALL sp_get_transaction_index_invoice(?,?,?,?,?,?,?,?)',$param_array,'');
		$data_arr["count"] 	= $result[0][0]['counter'];
		$data_arr["data"][0] 	= $result[1];
		
		// pass the data in summary_showdatagrid() function & create a final variable for view
		$this->view->pagegridshow =  $pagingshow->summary_showdatagrid($data_arr);
		
		$this->view->addScriptPath(APPLICATION_PATH."/theme/default/datagrid/");
    }
    /**
    * @name       addinvoiceinfoAction
    * @since      05-07-2012
    * @version    Release: 4
    * @author     HD
    * @author     GP<gayatri@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This action is for Add invoice information
    *Calculate different quantity and insert record
    */

    public function addinvoiceinfoAction()
    {
	$this->view->params = $params = $this->getRequest()->getParams();
        $this->view->formdata = $formdata = $this->_request->getPost();
	
	
	// create combo for route,customer, and item
	  $result = $this->SFA_Comman->executequery('CALL sp_get_transaction_invoice_addinvoiceinfo()','','');
	
	 $this->view->route =$result[0];
	 $this->view->customer=$result[1];
	 $this->view->item=$result[2];
	  $this->view->salesman=$result[3];
	 
	 if(count($formdata) > 0) {

            if($formdata['hdnid'] > 0)
	    {//update record
	    }
	    else
		{
		   // echo "<pre>";
		   // print_r($formdata);
		    
		    $param_array =array();
		    $param_array[1] =$formdata['txt_invoice_no'];
		    $param_array[2] =$formdata['txtdate'];
		    $param_array[3] =$formdata['txt_doc_no'];
		    $param_array[4] =$formdata['ddlroute'];
		    $param_array[5] =$formdata['ddlsalesman'];
		    $param_array[6] =$formdata['txt_hhc_invoice_no'];
		    $param_array[7] =$formdata['ddlcustomer'];
		    $param_array[8] =$formdata['txt_doc_status'];
		     
		    $param_array[9] =$route_key=1;
		    $param_array[10] =$visit_key=1;
		    $param_array[11] =$formdata['ddlitem'];
		    $param_array[12] =$salesqty=(($formdata['txt_sales_case']*$formdata['txt_upc'])+$formdata['txt_sales_pieces']);
		    $param_array[13] =$returnqty =(($formdata['txt_buyback_cases']*$formdata['txt_upc'])+$formdata['txt_buyback_pieces']);
		    $param_array[14] =$damagedqty=(($formdata['txt_damage_cases']*$formdata['txt_upc'])+$formdata['txt_damage_pieces']);
		    $param_array[15] =$freesampleqty=(($formdata['txt_promotion_cases']*$formdata['txt_upc'])+$formdata['txt_promotion_pieces']);
		    $param_array[16] =$formdata['txt_price_sale'];
		    $param_array[17] =$formdata['txt_price_return'];
		    $param_array[18] =$promoqty=(($formdata['txt_promotion_cases']*$formdata['txt_upc'])+$formdata['txt_promotion_pieces']);
		    $param_array[19] =$expiryqty=(($formdata['txt_expirey_case']*$formdata['txt_upc'])+$formdata['txt_expirey_pieces']);
		    $param_array[20] =$manualfreeqty=(($formdata['txt_freegood_cases']*$formdata['txt_upc'])+$formdata['txt_freegood_pieces']);
		    
		 
		    
		    $result 	= $this->SFA_Comman->executequery('CALL sp_add_transaction_invoice_addinvoiceinfo(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',$param_array,'');

		 

		}

	    $this->_helper->redirector('addinvoiceinfo', 'invoice', 'hhctransaction');
	 }
    }
    /**
    * @name       addinvoiceAction
    * @since      06-02-2012
    * @version    Release: 1
    * @author     M@M <miral@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This action is for add add invoice
    *
    */
    public function addinvoiceAction()
    {
		$this->view->params = $params = $this->getRequest()->getParams();
        $this->view->formdata = $formdata = $this->_request->getPost();
		$this->view->invoiceid = $params["id"];
		$this->view->css 		= $this->translate->_('CSS');
		
		$Common_NameSpace = new Zend_Session_Namespace('Invoice');
		
		
	
		$sel_date = ($formdata["txtdate"] != '') ? $formdata["txtdate"] : $Common_NameSpace->tdate;
	
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
		
		if($params['id'] > 0)
		{
			$result = $this->SFA_Comman->executequery('CALL sp_get_transaction_index_addinvoice(?)',$params['id'],'');
			
			$this->view->route_info	= $result[0];
			$this->view->formdata 	= $result[1][0];
			
			// Added By niles on 28-July-2016 for invoiceimages.
			$this->view->paymentdata 	= $result[2][0];
			$this->view->promoamount 	= $result[3][0]['promoamount'];	
			
			$baseUrl            = Zend_Controller_Front::getInstance()->getBaseUrl();
			/*$this->view->path   = str_replace('//','/',$_SERVER['DOCUMENT_ROOT'].$baseUrl.'/public/customerimage/backup/');
			$this->view->path1 	= str_replace('//','/',$baseUrl.'/public/customerimage/backup/');
			var_dump($this->view->path);
			echo "<br>";
				var_dump($this->view->path1);*/
		   $this->view->path   = "file:///d:/RoutePro_Images/customerimage/";
			$this->view->path1 	= "file:///d:/RoutePro_Images/customerimage/";
			
			$this->view->invoiceimg   =   $result[4];
		}
		
		$ex_param = "";
		if(isset($params["id"]) && $params["id"]>0)
			$ex_param = "/key/".$params["id"];
			 
			$this->view->invoiceitemgrid    = $this->view->BaseUrl("/hhctransaction/invoice/invoicedetail".$ex_param);
		
		
			
			
    }



    /**
    * @name       invoicedetail
    * @since      20-02-2012
    * @version    Release: 1
    * @author     M@M <miral@elantechnologies.com>
    * @author     GP <gayatri@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This action is for display ajax item grid in add invoice page
    * Gpatel : added top colum array, footer colum array and right alignment array
    */
    public function invoicedetailAction()
    {
	$params 		= $this->getRequest()->getParams();
        
	
	// IF EXTRA PARAMS ARE REQUIRED
	$ex_param = "";
	if(isset($params["key"]) && $params["key"]>0)
	    $ex_param = "/key/".$params["key"];

		// column header to be displayed
		$item_code		= $this->translate->_('Item Code');
		$alt_code		= $this->translate->_('Alternate Code');
		$item_name		= $this->translate->_('Item Description');
		$upc			= $this->translate->_('UPC');
		$sales_case_price	= $this->translate->_('CAS');
		$sales_pcs_price	= $this->translate->_('PCS');
		$return_case_price	= $this->translate->_('Case Price');
		$return_pcs_price	= $this->translate->_('Unit Price');
		$case_sales_qty		= $this->translate->_('CAS');
		$pcs_sales_qty		= $this->translate->_('PCS');
		$case_return_qty	= $this->translate->_('Case Price');
		$pcs_return_qty		= $this->translate->_('Unit Price');
		$case_damage_qty	= $this->translate->_('CAS');
		$pcs_damage_qty		= $this->translate->_('PCS');
		$case_free_qty		= $this->translate->_('CAS');
		$pcs_free_qty		= $this->translate->_('PCS');
		$case_free_qty		= $this->translate->_('CAS');
		$pcs_free_qty		= $this->translate->_('PCS');
		$discount			= $this->translate->_('Discount');		
		
		$tax_sales			= $this->translate->_("Sales");
		$tax_return			= $this->translate->_("Return");	
		
		$total_amount		= $this->translate->_('Total Amount');
	
		// For Alternate Code Status.
		$cpanel				= $this->SFA_Comman->getaltcodestatus();
		$altcode_status		= $cpanel["Use Alternate Code"]['status'];
		$Settings_NameSpace = new Zend_Session_Namespace('Settings');
		if($Settings_NameSpace->cpanel['Enable Tax']['status'] == 1)
		{
		
			$columns_array		= array('im.actualitemcode','im.itemshortdescription','im.unitspercase',
				'FLOOR((salesqty/im.unitspercase)) AS case_sales_qty','(salesqty%im.unitspercase) AS pcs_sales_qty',
				'FORMAT(id.salescaseprice,'.$this->decimalplaces.') AS salescaseprice','FORMAT(id.salesprice,'.$this->decimalplaces.') AS salesprice ',
				'FLOOR((returnqty/im.unitspercase)) AS case_return_qty','(returnqty%im.unitspercase) AS pcs_return_qty',
				'FORMAT(id.returncaseprice,'.$this->decimalplaces.') AS returncaseprice','FORMAT(id.returnprice,'.$this->decimalplaces.') AS returnprice',
				'FLOOR((damagedqty/im.unitspercase)) AS case_damage_qty','(damagedqty%im.unitspercase) AS pcs_damage_qty',
				'FLOOR((manualfreeqty/im.unitspercase)) AS case_manualfreeqty_qty','(manualfreeqty%im.unitspercase) AS pcs_manualfreeqty_qty',
				'FLOOR((freesampleqty/im.unitspercase)) AS casediscount','(freesampleqty%im.unitspercase) AS pcsdiscount',
				'FORMAT(promoamount,'.$this->decimalplaces.') AS discount',
				
				'FORMAT(salesitemexcisetax+salesitemgsttax+fgitemexcisetax+fgitemgsttax+promoitemexcisetax+promoitemgsttax,'.$this->decimalplaces.')AS taxsales',
				'FORMAT(returnitemexcisetax+returnitemgsttax+damageditemexcisetax+damageditemgsttax+buybackexcisetax+buybackgsttax,'.$this->decimalplaces.') AS taxreturn',
				
				
				'FORMAT((
					(((FLOOR(salesqty/im.unitspercase)*id.salescaseprice)+((salesqty%im.unitspercase)*id.salesprice))+(salesitemexcisetax+salesitemgsttax+fgitemexcisetax+fgitemgsttax+promoitemexcisetax+promoitemgsttax))-
					(((FLOOR(returnqty/im.unitspercase)*id.returncaseprice)+((returnqty%im.unitspercase)*id.returnprice))+(returnitemexcisetax+returnitemgsttax+buybackexcisetax+buybackgsttax))-
					(((FLOOR(damagedqty/im.unitspercase)*id.returncaseprice)+((damagedqty%im.unitspercase)*id.returnprice))+(damageditemexcisetax+damageditemgsttax))-
					promoamount),'.$this->decimalplaces.') AS total_amount','1 AS sett_tran_type','id.transactionkey as edit_del_primary_id'
				);
		
			$columns_show  = array($item_code,$item_name,$upc,$sales_case_price,$sales_pcs_price,$return_case_price,$return_pcs_price,$case_sales_qty,$pcs_sales_qty,$case_return_qty,$pcs_return_qty,$case_damage_qty,$pcs_damage_qty,$case_free_qty,$pcs_free_qty,$case_free_qty,$pcs_free_qty,$discount,$tax_sales,$tax_return,$total_amount);
		}else{	
		
			$columns_array		= array('im.actualitemcode','im.itemshortdescription','im.unitspercase',
				'FLOOR((salesqty/im.unitspercase)) AS case_sales_qty','(salesqty%im.unitspercase) AS pcs_sales_qty',
				'FORMAT(id.salescaseprice,'.$this->decimalplaces.') AS salescaseprice','FORMAT(id.salesprice,'.$this->decimalplaces.') AS salesprice ',
				'FLOOR((returnqty/im.unitspercase)) AS case_return_qty','(returnqty%im.unitspercase) AS pcs_return_qty',
				'FORMAT(id.returncaseprice,'.$this->decimalplaces.') AS returncaseprice','FORMAT(id.returnprice,'.$this->decimalplaces.') AS returnprice',
				'FLOOR((damagedqty/im.unitspercase)) AS case_damage_qty','(damagedqty%im.unitspercase) AS pcs_damage_qty',
				'FLOOR((manualfreeqty/im.unitspercase)) AS case_manualfreeqty_qty','(manualfreeqty%im.unitspercase) AS pcs_manualfreeqty_qty',
				'FLOOR((freesampleqty/im.unitspercase)) AS casediscount','(freesampleqty%im.unitspercase) AS pcsdiscount',
				'FORMAT(promoamount,'.$this->decimalplaces.') AS discount','FORMAT((((FLOOR(salesqty/im.unitspercase)*id.salescaseprice)+((salesqty%im.unitspercase)*id.salesprice))-((FLOOR(returnqty/im.unitspercase)*id.returncaseprice)+((returnqty%im.unitspercase)*id.returnprice))
				-((FLOOR(damagedqty/im.unitspercase)*id.returncaseprice)+((damagedqty%im.unitspercase)*id.returnprice))-promoamount),'.$this->decimalplaces.') AS total_amount','1 AS sett_tran_type','id.transactionkey as edit_del_primary_id'
				);
		
			$columns_show  = array($item_code,$item_name,$upc,$sales_case_price,$sales_pcs_price,$return_case_price,$return_pcs_price,$case_sales_qty,$pcs_sales_qty,$case_return_qty,$pcs_return_qty,$case_damage_qty,$pcs_damage_qty,$case_free_qty,$pcs_free_qty,$case_free_qty,$pcs_free_qty,$discount,$total_amount);
		}
		
		if($altcode_status) {
			$columns_array[0]	= 'im.alternatecode';
			$columns_show[0]	= $alt_code;
		}
	
		// prepare the configuration for grid
		
		if($Settings_NameSpace->cpanel['Enable Tax']['status'] == 1)
		{
			$pagingparams = array(
				"show_grid_heading" => false,
				"grid_heading_message" => $this->translate->_('Overview'),
				"show_records_per_page" => isset($formdata["show_records_per_page"])?$formdata["show_records_per_page"]:100,
				"show_searchbox" => false,
				"show_selectbox" => false,
				"show_editlink" => false,
				"show_deletelink" => false,			
				"show_deleteall" => false,
				"primaryid" => "transactionkey",
				"currentlink" => array("/hhctransaction/invoice/detailinvoice".$ex_param),
				"editlink" => array("/id/#pattern#/edit/yes","#pattern#"),
				"deletelink" => array("/id/#pattern#/delete/yes","#pattern#"),
				"nodata_message" => $this->translate->_('No Record(s) Found'),
				"fetch_columns_inquery" => $columns_array,			
				"show_columns" => $columns_show,
				"show_top_columns" => true,
				"show_top_columns_value" => array(array("3",""),array("4",$this->translate->_('Sales')),array("4",$this->translate->_('Return')),array("2",$this->translate->_('Damage')),array("2",$this->translate->_('Free')),array("3",$this->translate->_('Promotion')),array("2","Tax"),array("1",""),array("1","")),
				"show_total_columns"=>true,			
				"show_total_columns_value"=>array("case_sales_qty"=>"0","pcs_sales_qty"=>"0","case_return_qty"=>"0","pcs_return_qty"=>"0","case_damage_qty"=>"0","pcs_damage_qty"=>"0","case_manualfreeqty_qty"=>"0","pcs_manualfreeqty_qty"=>"0","total_amount"=>"1","casediscount"=>"0","pcsdiscount"=>"0"),
				"show_total_columns_msg"=>array("itemshortdescription","Total",$this->decimalplaces),
				"show_columns_right_side" =>array('salescaseprice','salesprice','returncaseprice','returnprice',"case_sales_qty","pcs_sales_qty","case_return_qty","pcs_return_qty","case_damage_qty","pcs_damage_qty","case_manualfreeqty_qty","pcs_manualfreeqty_qty","discount","taxsales","taxreturn","total_amount",'casediscount','pcsdiscount'),
				"show_header_right_side"=>array($this->translate->_("$this->lblcase Price"),$this->translate->_("$this->lblpcs Price"),$this->translate->_("$this->lblcase"),$this->translate->_("$this->lblpcs"),$this->translate->_('Discount'),$this->translate->_('Sales'),$this->translate->_('Return'),$this->translate->_('Total Amount')),
				"show_extralink_popup" => true,
				"show_extralink_popup_transaction" => true,
				"extralink" => array(array("More","/".$params['module']."/".$params['controller']."/viewbatches/id/#pattern#/?q=prettyPhoto&iframe=true&width=900&height=500","#pattern#")),
				);
		}else{
			$pagingparams = array(
				"show_grid_heading" => false,
				"grid_heading_message" => $this->translate->_('Overview'),
				"show_records_per_page" => isset($formdata["show_records_per_page"])?$formdata["show_records_per_page"]:100,
				"show_searchbox" => false,
				"show_selectbox" => false,
				"show_editlink" => false,
				"show_deletelink" => false,			
				"show_deleteall" => false,
				"primaryid" => "transactionkey",
				"currentlink" => array("/hhctransaction/invoice/detailinvoice".$ex_param),
				"editlink" => array("/id/#pattern#/edit/yes","#pattern#"),
				"deletelink" => array("/id/#pattern#/delete/yes","#pattern#"),
				"nodata_message" => $this->translate->_('No Record(s) Found'),
				"fetch_columns_inquery" => $columns_array,			
				"show_columns" => $columns_show,
				"show_top_columns" => true,
				"show_top_columns_value" => array(array("3",""),array("4","Sales"),array("4","Return"),array("2","Damage"),array("2","Free"),array("3","Promotion"),array("1",""),array("1","")),
				"show_total_columns"=>true,			
				"show_total_columns_value"=>array("case_sales_qty"=>"0","pcs_sales_qty"=>"0","case_return_qty"=>"0","pcs_return_qty"=>"0","case_damage_qty"=>"0","pcs_damage_qty"=>"0","case_manualfreeqty_qty"=>"0","pcs_manualfreeqty_qty"=>"0","total_amount"=>"1","casediscount"=>"0","pcsdiscount"=>"0"),
				"show_total_columns_msg"=>array("itemshortdescription","Total",$this->decimalplaces),
				"show_columns_right_side" =>array('salescaseprice','salesprice','returncaseprice','returnprice',"case_sales_qty","pcs_sales_qty","case_return_qty","pcs_return_qty","case_damage_qty","pcs_damage_qty","case_manualfreeqty_qty","pcs_manualfreeqty_qty","discount","total_amount",'casediscount','pcsdiscount'),
				"show_header_right_side"=>array($this->translate->_('Case Price'),$this->translate->_('Pcs Price'),$this->translate->_('CAS'),$this->translate->_('PCS'),$this->translate->_('Discount'),$this->translate->_('Total Amount')),
				"show_extralink_popup" => true,
				"show_extralink_popup_transaction" => true,
				"extralink" => array(array("More","/".$params['module']."/".$params['controller']."/viewbatches/id/#pattern#/?q=prettyPhoto&iframe=true&width=900&height=500","#pattern#")),
				);
		}
        
        $pagingshow = new SFA_Ajaxpaging($pagingparams);

	// call common function of grid class
	$get_return_vals = $pagingshow->commnfunc();
	
	//print_r($get_return_vals['where_condition']);
	
	// call the stored procedure for fetch the data  
	$param_array[1] = '1';
	$param_array[2] = $get_return_vals['order_columns_name'];
	$param_array[3] = $get_return_vals['order_type'];
	$param_array[4] = $get_return_vals['offset'];
	$param_array[5] = (int)$get_return_vals['show_records_per_page'];
	$param_array[6] = implode(", ",$pagingparams["fetch_columns_inquery"]);
	$param_array[7] = strlen($get_return_vals['where_condition'])>0?$get_return_vals['where_condition']:'';
	$param_array[8] = $params['key'];

	//echo "<pre>";
	//print_r($param_array);
	
	// called stored procedure for counter
	$result = $this->SFA_Comman->executequery('CALL sp_get_transaction_index_invoicedetailgrid(?,?,?,?,?,?,?)',$param_array,'');   

	$data_arr["count"] 	= $result[0][0]['counter'];
	$data_arr["data"][0]	= $result[1];
	
	
        $this->view->pagegridshow =  $pagingshow->summary_showdatagrid($data_arr);
        $this->view->addScriptPath(APPLICATION_PATH."/theme/default/datagrid/");

        $this->render("ajaxgrid");
    }
    
	 /**
    * @name       viewbatchesAction
    * @since      02-02-2012
    * @version    Release: 1
    * @author     HD <hiren.d@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This action is for view batches
    *
    */
    public function viewbatchesAction()
    {
		$this->view->params = $params = $this->getRequest()->getParams();
        $this->view->formdata = $formdata = $this->_request->getPost();
		$this->_helper->layout->setLayout('popup');
		
		// Begin Stock Datagrid
		$ex_param = "";
		if(isset($params["id"]) && $params["id"]>0)
			$ex_param = "/key/".$params["id"];
	
		$this->view->itemgrid    = $this->view->BaseUrl("/".$params['module']."/".$params['controller']."/viewbatchesgrid".$ex_param);
    }	
    /**
    * @name       viewbatchesgridAction
    * @since      22-02-2012
    * @version    Release: 1
    * @author     AS <alpesh@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This action is use for view item grid in viewbatchesgridAction
    */
    public function viewbatchesgridAction()
    {
		$this->view->params = $params = $this->getRequest()->getParams();
		
        // IF EXTRA PARAMS ARE REQUIRED
        $ex_param = "";
        if(isset($params["key"]) && $params["key"]>0)
                $ex_param = "/key/".$params["key"];
		
		
		$columns_show 	= array($this->translate->_('Expiry Date'),$this->translate->_('Batch Number'),$this->translate->_('Cases'),$this->translate->_('Pieces'));
		$cols_array 	= array('DATE_FORMAT(expirydate,"%d-%m-%Y") AS expirydate','batchnumber','FLOOR(quantity/im.unitspercase) AS cases','(quantity%im.unitspercase) AS pieces');
		
		// IF EXTRA PARAMS ARE REQUIRED
		$ex_param = "";
		$additional_where_condition = array();
		if(isset($params["key"]) && $params["key"]>0) {
			$ex_param = "/key/".$params["key"]."/key1/".$params["key1"];
			$additional_where_condition[] = ' id.transactionkey = "'.$params["key"].'" ';
			$additional_where_condition[] = ' transactiontypecode = "'.$params["key1"].'" ';
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
		$result = $this->SFA_Comman->executequery('CALL sp_get_transaction_invoice_batchdetail(?,?,?,?,?,?,?)',$param_array,'');    
		$data_arr["count"] 		= $result[0][0]['counter'];	
		$data_arr["data"][0] 	= $result[1];
		
		$this->view->pagegridshow =  $pagingshow->summary_showdatagrid($data_arr);
		$this->view->addScriptPath(APPLICATION_PATH."/theme/default/datagrid/");
	
		$this->render("ajaxgrid");       
    }
	
	 /**
    * @name       salesorderAction
    * @since      02-02-2012
    * @version    Release: 1
    * @author     HD <hiren.d@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This action is for sales order overview
    *
    */
 
    public function salesorderAction()
    {	
	$this->view->params = $params = $this->getRequest()->getParams();
	$this->view->formdata = $formdata = $this->_request->getPost();
	
	if($formdata["hdDelete"]==1)
	{
	    $ids = implode(',',$formdata['chk']);
	    $param_array 	= array();
	    $param_array[1]	= $ids;
	    $param_array[2]	= $this->currentUser->username;
	    
	    $result 	= $this->SFA_Comman->executequery('CALL sp_delete_hhctransaction_invoice_salesorder(?,?)',$param_array,'');
	    
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
	
	// For Alternate Code Status.
	$cpanel				= $this->SFA_Comman->getaltcodestatus();
	$altcode_status		= $cpanel["Use Alternate Code"]['status'];
	
	if($altcode_status) {
		$cols_array = array('cm.alternatecode','customername','invoicenumber','salesmanname1','IF(voidflag = 0,"NORMAL","VOIDED") AS voidflag','DATE_FORMAT(orderdeliverydate,"%d-%m-%Y") AS orderdeliverydate','FORMAT(totalinvoiceamount,'.$this->decimalplaces.') AS totalinvoiceamount','transactionkey as edit_del_primary_id' );
	} else {
		$cols_array = array('cm.customercode','customername','invoicenumber','salesmanname1','IF(voidflag = 0,"NORMAL","VOIDED") AS voidflag','DATE_FORMAT(orderdeliverydate,"%d-%m-%Y") AS orderdeliverydate','FORMAT(totalinvoiceamount,'.$this->decimalplaces.') AS totalinvoiceamount','transactionkey as edit_del_primary_id' );
	}
	$columns_show =  array (
		  $this->translate->_('Customer Code'),
		  $this->translate->_('Customer'),
		  $this->translate->_('Order No'),
		  // $this->translate->_('Route'),
		  $this->translate->_('Salesman'),
		  $this->translate->_('Order Status'),
		  $this->translate->_('Delivery Date'),
		  $this->translate->_('Amount'));
	    
	$not_in_search 		= array();
	$not_in_search[] 	= 'transactiondate';
	
	$Common_NameSpace = new Zend_Session_Namespace('Cloud_SalesOrder');
	 
	if($formdata['btnreset'] == 'RESET')
	{
	    $formdata["txtdate"] 	= '';
	    $Common_NameSpace->tdate	= '';
	}
	
	$last_url = htmlspecialchars($_SERVER['HTTP_REFERER']);
	if(strpos($last_url,'salesorder'))
	{
	    // CREATE A SESSION NAMESPACE
	   
	    $sel_date = ($formdata["txtdate"] != '') ? $formdata["txtdate"] : $Common_NameSpace->tdate;
	}
	else
	{
	    $sel_date = ($formdata["txtdate"] != '') ? $formdata["txtdate"] : date('d-m-Y');
	}	
	
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
	// ADDITIONAL WHERE CONDITION
	if($Common_NameSpace->tdate)
	    $additional_where_condition[] = " (transactiondate BETWEEN \'".date("Y-m-d 00:00:00",strtotime(str_replace('/', '-', $Common_NameSpace->tdate)))."\' AND \'".date("Y-m-d 23:59:59",strtotime(str_replace('/', '-', $Common_NameSpace->tdate)))."\' )  AND record_flag = \'1\' ";
	
	// prepare the configuration for grid
	$pagingparams = array(
			 "show_grid_heading" => true,
			 "grid_heading_message" => $this->translate->_('Overview'),
			 "show_records_per_page" => isset($formdata["show_records_per_page"])?$formdata["show_records_per_page"]:10,
			 "show_searchbox" => true,
			 "searchbox_value" => isset($formdata["grid_search_text"])?$formdata["grid_search_text"]:"",
			 "show_selectbox" => true,
			 "show_editlink" => false,
			 "show_deletelink" => false,
			 "show_deleteall" => false,
			 'show_extralink' => true,
			 'extralink' => array(array("View","/".$params['module']."/".$params['controller']."/salesorderheader/id/#pattern#","#pattern#")),
			 "no_search_fields" => $not_in_search,
			 "primaryid" => "transactionkey",			 
			 "nodata_message" => $this->translate->_('No Record(s) Found'),
			 "fetch_columns_inquery" => $cols_array,
			 "show_columns" => $columns_show,
			 "additional_where" => $additional_where_condition,
			 "show_columns_right_side"=>array('totalinvoiceamount'),
			 "show_header_right_side"=>array($this->translate->_('Amount')),
			 );
	
	// create grid class object
	$pagingshow = new SFA_Paging($pagingparams);
	
	// call common function of grid class
	$get_return_vals = $pagingshow->commnfunc();
	
	// call the stored procedure for fetch the data  
	$param_array[1] = '1';
	$param_array[2] = '';
	$param_array[3] = $get_return_vals['order_columns_name'];
	$param_array[4] = $get_return_vals['order_type'];
	$param_array[5] = $get_return_vals['offset'];
	$param_array[6] = (int)$get_return_vals['show_records_per_page'];
	$param_array[7] = implode(", ",$pagingparams["fetch_columns_inquery"]);
	$param_array[8] = strlen($get_return_vals['where_condition'])>0?$get_return_vals['where_condition']:'';	
	
	$result 	= $this->SFA_Comman->executequery('CALL sp_get_transaction_index_salesorder(?,?,?,?,?,?,?,?)',$param_array,'');
	$data_arr["count"] 	= $result[0][0]['counter'];
	$data_arr["data"][0] 	= $result[1];
	
	// pass the data in summary_showdatagrid() function & create a final variable for view
	$this->view->pagegridshow =  $pagingshow->summary_showdatagrid($data_arr);
	
	$this->view->addScriptPath(APPLICATION_PATH."/theme/default/datagrid/");
	
	if($formdata["hdDelete"]==1)
	    SFA_Message::setMsg($this->translate->_('Delete Record'));
    }   
    /**
    * @name       salesorderheader
    * @since      06-02-2012
    * @version    Release: 1
    * @author     M@M <miral@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This action is for salesorder header
    *
    */
    public function salesorderheaderAction()
    {
	$this->view->params = $params = $this->getRequest()->getParams();
        $this->view->formdata = $formdata = $this->_request->getPost();
		$this->view->salesorderid = $params["id"];

	$this->view->css 		= $this->translate->_('CSS');
	
	
	
	$Common_NameSpace = new Zend_Session_Namespace('Cloud_SalesOrder');

	$sel_date = ($formdata["txtdate"] != '') ? $formdata["txtdate"] : $Common_NameSpace->tdate;

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
	
	if($params['id'] > 0)
	{
	    $result = $this->SFA_Comman->executequery('CALL sp_get_transaction_index_salesorderheader(?)',$params['id'],'');
	    $this->view->route_info	= $result[0];
	    $this->view->formdata 	= $result[1][0];
	}
	
	$ex_param = "";
	if(isset($params["id"]) && $params["id"]>0)
	    $ex_param = "/key/".$params["id"];
	     
        $this->view->invoiceitemgrid    = $this->view->BaseUrl("/hhctransaction/invoice/salesorderdetail".$ex_param);
    }



    /**
    * @name       salesorderdetail
    * @since      20-02-2012
    * @version    Release: 1
    * @author     M@M <miral@elantechnologies.com>
    * @author     GP <gayatri@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This action is for display ajax item grid in add invoice page
    * add code of show top column array, footer total column array  and right align ment array
    *
    */
    public function salesorderdetailAction()
    {
	$params 		= $this->getRequest()->getParams();
        
	
	// IF EXTRA PARAMS ARE REQUIRED
	$ex_param = "";
	if(isset($params["key"]) && $params["key"]>0)
	    $ex_param = "/key/".$params["key"];

		// define grid's column header
		$item_code		= $this->translate->_('Item Code');
		$alt_code		= $this->translate->_('Alternate Code');
		$item_name		= $this->translate->_('Item Description');
		$upc			= $this->translate->_('UPC');
		$sales_case_price	= $this->translate->_('CAS');
		$sales_pcs_price	= $this->translate->_('PCS');
		$return_case_price	= $this->translate->_('Case Price');
		$return_pcs_price	= $this->translate->_('Unit Price');
		$case_sales_qty		= $this->translate->_('CAS');
		$pcs_sales_qty		= $this->translate->_('PCS');
		$case_return_qty	= $this->translate->_('Case Price');
		$pcs_return_qty		= $this->translate->_('Unit Price');
		$case_damage_qty	= $this->translate->_('CAS');
		$pcs_damage_qty		= $this->translate->_('PCS');
		$case_free_qty		= $this->translate->_('CAS');
		$pcs_free_qty		= $this->translate->_('PCS');
		$case_free_qty		= $this->translate->_('CAS');
		$pcs_free_qty		= $this->translate->_('PCS');
		$discount		= $this->translate->_('Discount');
		
		$tax_order			= $this->translate->_("Order");
		$tax_return			= $this->translate->_("Return");
		
		$total_amount		= $this->translate->_('Total Amount');
        
		// For Alternate Code Status.
		$cpanel				= $this->SFA_Comman->getaltcodestatus();
		$altcode_status		= $cpanel["Use Alternate Code"]['status'];
		
		$Settings_NameSpace = new Zend_Session_Namespace('Settings');
		if($Settings_NameSpace->cpanel['Enable Tax']['status'] == 1)
		{
			// column to be fetched	
			$columns_array 	=  array('im.actualitemcode','im.itemshortdescription','im.unitspercase',
						'FLOOR((salesqty/im.unitspercase)) AS case_sales_qty','(salesqty%im.unitspercase) AS pcs_sales_qty',
						'FORMAT(id.salescaseprice,'.$this->decimalplaces.') AS salescaseprice','FORMAT(id.salesprice,'.$this->decimalplaces.') AS salesprice',
						'FLOOR((returnqty/im.unitspercase)) AS case_return_qty','(returnqty%im.unitspercase) AS pcs_return_qty',
						'FORMAT(id.returncaseprice,'.$this->decimalplaces.') AS returncaseprice','FORMAT(id.returnprice,'.$this->decimalplaces.') AS returnprice',				
						'FLOOR((damagedqty/im.unitspercase)) AS case_damage_qty','(damagedqty%im.unitspercase) AS pcs_damage_qty',
						'FLOOR((manualfreeqty/im.unitspercase)) AS case_manualfreeqty_qty','(manualfreeqty%im.unitspercase) AS pcs_manualfreeqty_qty',
						'FLOOR((freesampleqty/im.unitspercase)) AS casediscount','(freesampleqty%im.unitspercase) AS pcsdiscount',				
						'FORMAT(promoamount,'.$this->decimalplaces.') AS discount',
						'FORMAT(salesorderexcisetax +salesordervat +fgexcisetax+fgvat+promoexcisetax+promovat ,'.$this->decimalplaces.')AS taxorder',
				'FORMAT(returnexcisetax +returnvat+damagedexcisetax+damagedvat,'.$this->decimalplaces.') AS taxreturn',			
				
				'FORMAT((
					(((FLOOR(salesqty/im.unitspercase)*id.salescaseprice)+((salesqty%im.unitspercase)*id.salesprice))+(salesorderexcisetax+salesordervat+fgexcisetax+fgvat+promoexcisetax+promovat))-
					(((FLOOR(returnqty/im.unitspercase)*id.returncaseprice)+((returnqty%im.unitspercase)*id.returnprice))+(returnexcisetax +returnvat))-
					(((FLOOR(damagedqty/im.unitspercase)*id.returncaseprice)+((damagedqty%im.unitspercase)*id.returnprice))+(damagedexcisetax+damagedvat))-
					promoamount),'.$this->decimalplaces.') AS total_amount'
						);
			// column header to be displayed
			$columns_show  = array($alt_code,$item_name,$upc,$sales_case_price,$sales_pcs_price,$return_case_price,$return_pcs_price,$case_sales_qty,$pcs_sales_qty,$case_return_qty,$pcs_return_qty,$case_damage_qty,$pcs_damage_qty,$case_free_qty,$pcs_free_qty,$case_free_qty,$pcs_free_qty,$discount,$tax_order,$tax_return,$total_amount);
		}
		else
		{
			// column to be fetched	
			$columns_array 	=  array('im.actualitemcode','im.itemshortdescription','im.unitspercase',
						'FLOOR((salesqty/im.unitspercase)) AS case_sales_qty','(salesqty%im.unitspercase) AS pcs_sales_qty',
						'FORMAT(id.salescaseprice,'.$this->decimalplaces.') AS salescaseprice','FORMAT(id.salesprice,'.$this->decimalplaces.') AS salesprice',
						'FLOOR((returnqty/im.unitspercase)) AS case_return_qty','(returnqty%im.unitspercase) AS pcs_return_qty',
						'FORMAT(id.returncaseprice,'.$this->decimalplaces.') AS returncaseprice','FORMAT(id.returnprice,'.$this->decimalplaces.') AS returnprice',				
						'FLOOR((damagedqty/im.unitspercase)) AS case_damage_qty','(damagedqty%im.unitspercase) AS pcs_damage_qty',
						'FLOOR((manualfreeqty/im.unitspercase)) AS case_manualfreeqty_qty','(manualfreeqty%im.unitspercase) AS pcs_manualfreeqty_qty',
						'FLOOR((freesampleqty/im.unitspercase)) AS casediscount','(freesampleqty%im.unitspercase) AS pcsdiscount',				
						'FORMAT(promoamount,'.$this->decimalplaces.') AS discount',
						'FORMAT(((((salesqty/im.unitspercase)*id.salescaseprice)+
						((salesqty%im.unitspercase)*id.salesprice))-(((returnqty/im.unitspercase)*id.returncaseprice)
						+((returnqty%im.unitspercase)*id.returnprice))-(((damagedqty/im.unitspercase)*id.returncaseprice)
						+((damagedqty%im.unitspercase)*id.returnprice))-promoamount),'.$this->decimalplaces.') AS total_amount'
						);
			// column header to be displayed
			$columns_show  = array($item_code,$item_name,$upc,$sales_case_price,$sales_pcs_price,$return_case_price,$return_pcs_price,$case_sales_qty,$pcs_sales_qty,$case_return_qty,$pcs_return_qty,$case_damage_qty,$pcs_damage_qty,$case_free_qty,$pcs_free_qty,$case_free_qty,$pcs_free_qty,$discount,$total_amount);
		}
	
		if($altcode_status) {
			$columns_array[0]	= 'im.alternatecode';
			$columns_show[0]	= $alt_code;
		}
	
	
		// prepare the configuration for grid
		if($Settings_NameSpace->cpanel['Enable Tax']['status'] == 1) {
			$pagingparams = array(
				"show_grid_heading" => false,
				"grid_heading_message" => $this->translate->_('Overview'),
				"show_records_per_page" => isset($formdata["show_records_per_page"])?$formdata["show_records_per_page"]:10,
				"show_searchbox" => false,
				"show_selectbox" => false,
				"show_editlink" => false,
				"show_deletelink" => false,			
				"show_deleteall" => false,
				"primaryid" => "actualitemcode",
				"currentlink" => array("/hhctransaction/invoice/salesorderdetail".$ex_param),
				"editlink" => array("/id/#pattern#/edit/yes","#pattern#"),
				"deletelink" => array("/id/#pattern#/delete/yes","#pattern#"),
				"nodata_message" => $this->translate->_('No Record(s) Found'),
				"fetch_columns_inquery" => $columns_array,
				"show_columns" => $columns_show,
				"show_top_columns" => true,
				"show_top_columns_value" => array(array("3",""),array("4",$this->translate->_('Order')),array("4",$this->translate->_('Return')),array("2",$this->translate->_('Damage')),array("2",$this->translate->_('Free')),array("3",$this->translate->_('Promotion')),array("2","Tax"),array("1","")),
				"show_total_columns"=>true,			
				"show_total_columns_value"=>array("casediscount"=>"0","pcsdiscount"=>"0","case_sales_qty"=>"0","pcs_sales_qty"=>"0","case_return_qty"=>"0","pcs_return_qty"=>"0","case_damage_qty"=>"0","pcs_damage_qty"=>"0","case_manualfreeqty_qty"=>"0","pcs_manualfreeqty_qty"=>"0","total_amount"=>"1","discount"=>"0","freesampleqty"=>"0","casediscount"=>"0","pcsdiscount"=>"0","taxorder"=>"0","taxreturn"=>"0"),
				"show_total_columns_msg"=>array("itemshortdescription","Total",$this->decimalplaces),
				"show_columns_right_side" =>array('salescaseprice','salesprice','returncaseprice','returnprice',"case_sales_qty","pcs_sales_qty","case_return_qty","pcs_return_qty","case_damage_qty","pcs_damage_qty","case_manualfreeqty_qty","pcs_manualfreeqty_qty","discount","total_amount","freesampleqty","casediscount","pcsdiscount","taxorder","taxreturn"),
				"show_header_right_side"=>array($this->translate->_("$this->lblcase Price"),$this->translate->_("$this->lblpcs Price"),$this->translate->_("$this->lblcase"),$this->translate->_("$this->lblpcs"),$this->translate->_('Discount'),$this->translate->_('Tax'),$this->translate->_('Total Amount')),
				);	
		} else {
			$pagingparams = array(
				"show_grid_heading" => false,
				"grid_heading_message" => $this->translate->_('Overview'),
				"show_records_per_page" => isset($formdata["show_records_per_page"])?$formdata["show_records_per_page"]:10,
				"show_searchbox" => false,
				"show_selectbox" => false,
				"show_editlink" => false,
				"show_deletelink" => false,			
				"show_deleteall" => false,
				"primaryid" => "actualitemcode",
				"currentlink" => array("/hhctransaction/invoice/salesorderdetail".$ex_param),
				"editlink" => array("/id/#pattern#/edit/yes","#pattern#"),
				"deletelink" => array("/id/#pattern#/delete/yes","#pattern#"),
				"nodata_message" => $this->translate->_('No Record(s) Found'),
				"fetch_columns_inquery" => $columns_array,
				"show_columns" => $columns_show,
				"show_top_columns" => true,
				"show_top_columns_value" => array(array("3",""),array("4","Order"),array("4","Return"),array("2"," Damage"),array("2","Free"),array("3","Promotion"),array("1","")),
				"show_total_columns"=>true,			
				"show_total_columns_value"=>array("casediscount"=>"0","pcsdiscount"=>"0","case_sales_qty"=>"0","pcs_sales_qty"=>"0","case_return_qty"=>"0","pcs_return_qty"=>"0","case_damage_qty"=>"0","pcs_damage_qty"=>"0","case_manualfreeqty_qty"=>"0","pcs_manualfreeqty_qty"=>"0","total_amount"=>"1"),
				"show_total_columns_msg"=>array("itemshortdescription","Total",$this->decimalplaces),
				"show_columns_right_side" =>array('salescaseprice','salesprice','returncaseprice','returnprice',"case_sales_qty","pcs_sales_qty","case_return_qty","pcs_return_qty","case_damage_qty","pcs_damage_qty","case_manualfreeqty_qty","pcs_manualfreeqty_qty","discount","total_amount","freesampleqty",'casediscount','pcsdiscount'),
				"show_header_right_side"=>array($this->translate->_('Case Price'),$this->translate->_('Pcs Price'),$this->translate->_('CAS'),$this->translate->_('PCS'),$this->translate->_('Discount'),$this->translate->_('Total Amount')),
				);	
		}
        $pagingshow = new SFA_Ajaxpaging($pagingparams);

	// call common function of grid class
	$get_return_vals = $pagingshow->commnfunc();
	
	//print_r($get_return_vals['where_condition']);
	
	// call the stored procedure for fetch the data  
	$param_array[1] = '1';
	$param_array[2] = $get_return_vals['order_columns_name'];
	$param_array[3] = $get_return_vals['order_type'];
	$param_array[4] = $get_return_vals['offset'];
	$param_array[5] = (int)$get_return_vals['show_records_per_page'];
	$param_array[6] = implode(", ",$pagingparams["fetch_columns_inquery"]);
	$param_array[7] = strlen($get_return_vals['where_condition'])>0?$get_return_vals['where_condition']:'';
	$param_array[8] = $params['key'];

	//echo "<pre>";
	//print_r($param_array);
	
	// called stored procedure for counter
	$result = $this->SFA_Comman->executequery('CALL sp_get_transaction_index_salesorderdetailgrid(?,?,?,?,?,?,?)',$param_array,'');
	
	

	$data_arr["count"] 	= $result[0][0]['counter'];
	$data_arr["data"][0]	= $result[1];
	
	
        $this->view->pagegridshow =  $pagingshow->summary_showdatagrid($data_arr);
        $this->view->addScriptPath(APPLICATION_PATH."/theme/default/datagrid/");

        $this->render("ajaxgrid");
    }
    /**
    * @name       arcollectionAction
    * @since      06-02-2012
    * @version    Release: 1
    * @author     M@M <miral@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This action is for ar collection
    *
    */

    public function arcollectionAction()
    {
	$this->view->params = $params = $this->getRequest()->getParams();
        $this->view->formdata = $formdata = $this->_request->getPost();
	
	if($formdata["hdDelete"]==1)
	{
	    $ids = implode(',',$formdata['chk']);
	    $param_array 	= array();
	    $param_array[1]	= $ids;
	    $param_array[2]	= $this->currentUser->username;
	    
	    $result 	= $this->SFA_Comman->executequery('CALL sp_delete_hhctransaction_invoice_arcollection(?,?)',$param_array,'');
	    
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
	
	// For Alternate Code Status.
	$cpanel				= $this->SFA_Comman->getaltcodestatus();
	$altcode_status		= $cpanel["Use Alternate Code"]['status'];
	
	if($altcode_status)
	{
		$cols_array = array('cm.alternatecode','customername','invoicenumber','routename','salesmanname1','FORMAT(amountpaid,'.$this->decimalplaces.') AS totalinvoiceamount','transactionkey as edit_del_primary_id' );
	}
	else
	{
		$cols_array = array('cm.customercode','customername','invoicenumber','routename','salesmanname1','FORMAT(amountpaid,'.$this->decimalplaces.') AS totalinvoiceamount','transactionkey as edit_del_primary_id' );
	}
	$columns_show =  array(
							$this->translate->_('Customer Code'),
							$this->translate->_('Customer'),
							$this->translate->_('Receipt No'),
							$this->translate->_('Route'),
							$this->translate->_('Salesman'),
							$this->translate->_('Receipt Amount'));
	    
	$not_in_search 		= array();
	$not_in_search[] 	= 'transactiondate';
	
	 // CREATE A SESSION NAMESPACE
	$Common_NameSpace = new Zend_Session_Namespace('ARCollection');
		
	if($formdata['btnreset'] == 'RESET')
	{
	    $formdata["txtdate"] 	= '';
	    $Common_NameSpace->tdate	= '';
	}
	
	$last_url = htmlspecialchars($_SERVER['HTTP_REFERER']);
	if(strpos($last_url,'arcollection'))
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
	    $this->view->date		= $sel_date;
	}
	else
	{
	    $Common_NameSpace->tdate 	= date('d-m-Y');
	    $this->view->date		= date('d-m-Y');
	}
	
	
	// ADDITIONAL WHERE CONDITION
	if($Common_NameSpace->tdate)
	    $additional_where_condition[] = " (transactiondate BETWEEN \'".date("Y-m-d 00:00:00",strtotime(str_replace('/', '-', $Common_NameSpace->tdate)))."\' AND \'".date("Y-m-d 23:59:59",strtotime(str_replace('/', '-', $Common_NameSpace->tdate)))."\' )   AND record_flag = \'1\' ";
	
	// prepare the configuration for grid
	$pagingparams = array(
			 "show_grid_heading" => true,
			 "grid_heading_message" => $this->translate->_('Overview'),
			 "show_records_per_page" => isset($formdata["show_records_per_page"])?$formdata["show_records_per_page"]:10,
			 "show_searchbox" => true,
			 "searchbox_value" => isset($formdata["grid_search_text"])?$formdata["grid_search_text"]:"",
			 "show_selectbox" => true,
			 "show_editlink" => false,
			 "show_deletelink" => false,
			 "show_deleteall" => false,
			 'show_extralink' => true,
			 'extralink' => array(array("View","/".$params['module']."/".$params['controller']."/arheader/id/#pattern#","#pattern#")),
			 "no_search_fields" => $not_in_search,
			 "primaryid" => "transactionkey",			 
			 "nodata_message" => $this->translate->_('No Record(s) Found'),
			 "fetch_columns_inquery" => $cols_array,
			 "show_columns" => $columns_show,
			 "additional_where" => $additional_where_condition,
			 "show_columns_right_side" =>array('totalinvoiceamount'),
			 "show_header_right_side"=>array($this->translate->_('Receipt Amount')),
			 );
	
	// create grid class object
	$pagingshow = new SFA_Paging($pagingparams);
	
	// call common function of grid class
	$get_return_vals = $pagingshow->commnfunc();
	
	// call the stored procedure for fetch the data  
	$param_array[1] = '1';
	$param_array[2] = '';
	$param_array[3] = $get_return_vals['order_columns_name'];
	$param_array[4] = $get_return_vals['order_type'];
	$param_array[5] = $get_return_vals['offset'];
	$param_array[6] = (int)$get_return_vals['show_records_per_page'];
	$param_array[7] = implode(", ",$pagingparams["fetch_columns_inquery"]);
	$param_array[8] = strlen($get_return_vals['where_condition'])>0?$get_return_vals['where_condition']:'';	
	
	$result 	= $this->SFA_Comman->executequery('CALL sp_get_transaction_index_arheader(?,?,?,?,?,?,?,?)',$param_array,'');
	$data_arr["count"] 	= $result[0][0]['counter'];
	$data_arr["data"][0] 	= $result[1];
	
	// pass the data in summary_showdatagrid() function & create a final variable for view
	$this->view->pagegridshow =  $pagingshow->summary_showdatagrid($data_arr);
	
	$this->view->addScriptPath(APPLICATION_PATH."/theme/default/datagrid/");	        
    }
    /**
    * @name       arheaderAction
    * @since      06-02-2012
    * @version    Release: 1
    * @author     M@M <miral@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This action is for salesorder header
    *
    */
    public function arheaderAction()
    {
	$this->view->params = $params = $this->getRequest()->getParams();
        $this->view->formdata = $formdata = $this->_request->getPost();

	$this->view->css 		= $this->translate->_('CSS');
	
	$Common_NameSpace = new Zend_Session_Namespace('ARCollection');

	$sel_date = ($formdata["txtdate"] != '') ? $formdata["txtdate"] : $Common_NameSpace->tdate;

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
	
	if($params['id'] > 0)
	{
	    $result = $this->SFA_Comman->executequery('CALL sp_get_transaction_index_arheaderdetail(?)',$params['id'],'');
	    $this->view->route_info	= $result[0];
	    $this->view->formdata 	= $result[1][0];
	    //$this->view->arpayment 	= $result[2][0];
	    
	    // Added By rachir on 19-July-2012 for payment mode information.
	    $this->view->paymentdata 	= $result[3][0] ;
	    
	    
	}
	
	$ex_param = "";
	if(isset($params["id"]) && $params["id"]>0)
	    $ex_param = "/key/".$params["id"];
	     
        $this->view->invoiceitemgrid    = $this->view->BaseUrl("/hhctransaction/invoice/arheaderdetail".$ex_param);
    }
    /**
    * @name       arheaderdetailAction
    * @since      20-02-2012
    * @version    Release: 1
    * @author     M@M <miral@elantechnologies.com>
    * @author     GP<gayatri@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This action is for display ajax item grid in add AR Collection
    * add show_columns_right_side array
    *
    */
    public function arheaderdetailAction()
    {
	$params 		= $this->getRequest()->getParams();
        
	
	// IF EXTRA PARAMS ARE REQUIRED
	$ex_param = "";
	if(isset($params["key"]) && $params["key"]>0)
	    $ex_param = "/key/".$params["key"];

	// define grid's column header
        $rec_num	= $this->translate->_('Invoice No');
        $rec_date	= $this->translate->_('Invoice Date');
        $rec_amt	= $this->translate->_('Invoice Amount');
	$amt_paid	= $this->translate->_('Amount Paid');
        
	
	// column to be fetched
	$columns_array 	=  array('invoicenumber','DATE_FORMAT(invoicedate,"%d-%m-%Y") AS invoicedate','FORMAT(totalinvoiceamount,'.$this->decimalplaces.') AS totalinvoiceamount','FORMAT(amountpaid,'.$this->decimalplaces.') AS amountpaid');
	
	// column header to be displayed
	$columns_show  = array($rec_num,$rec_date,$rec_amt,$amt_paid);
	
	// prepare the configuration for grid
	$pagingparams = array(
			"show_grid_heading" => false,
			"grid_heading_message" => $this->translate->_('Overview'),
			"show_records_per_page" => isset($formdata["show_records_per_page"])?$formdata["show_records_per_page"]:10,
			"show_searchbox" => false,
			"show_selectbox" => false,
			"show_editlink" => false,
			"show_deletelink" => false,			
			"show_deleteall" => false,
			"primaryid" => "actualitemcode",
			"currentlink" => array("/hhctransaction/invoice/detailinvoice".$ex_param),
			"editlink" => array("/id/#pattern#/edit/yes","#pattern#"),
			"deletelink" => array("/id/#pattern#/delete/yes","#pattern#"),
			"nodata_message" => $this->translate->_('No Record(s) Found'),
			"fetch_columns_inquery" => $columns_array,
			"show_columns" => $columns_show,
			"show_columns_right_side"=>array('totalinvoiceamount','amountpaid','balancedue'),
			"show_header_right_side"=>array($this->translate->_('Invoice Amount'),$this->translate->_('Amount Paid')),
			);	
        
        $pagingshow = new SFA_Ajaxpaging($pagingparams);

	// call common function of grid class
	$get_return_vals = $pagingshow->commnfunc();
	
	//print_r($get_return_vals['where_condition']);
	
	// call the stored procedure for fetch the data  
	$param_array[1] = '1';
	$param_array[2] = $get_return_vals['order_columns_name'];
	$param_array[3] = $get_return_vals['order_type'];
	$param_array[4] = $get_return_vals['offset'];
	$param_array[5] = (int)$get_return_vals['show_records_per_page'];
	$param_array[6] = implode(", ",$pagingparams["fetch_columns_inquery"]);
	$param_array[7] = strlen($get_return_vals['where_condition'])>0?$get_return_vals['where_condition']:'';
	$param_array[8] = $params['key'];

	//echo "<pre>";
	//print_r($param_array);
	
	// called stored procedure for counter
	$result = $this->SFA_Comman->executequery('CALL sp_get_transaction_index_arpaymentdetail(?,?,?,?,?,?,?)',$param_array,'');   

	$data_arr["count"] 	= $result[0][0]['counter'];
	$data_arr["data"][0]	= $result[1];
	
	
        $this->view->pagegridshow =  $pagingshow->summary_showdatagrid($data_arr);
        $this->view->addScriptPath(APPLICATION_PATH."/theme/default/datagrid/");

        $this->render("ajaxgrid");
    }
    /**
    * @name       advpaymentAction
    * @since      06-02-2012
    * @version    Release: 1
    * @author     M@M <miral@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This action is for adv payment
    *
    */

    public function advpaymentAction()
    {
		$this->view->params = $params = $this->getRequest()->getParams();
        $this->view->formdata = $formdata = $this->_request->getPost();
		
		if($params['delid'] > 0)
		{
			$ids = $params['delid'];
			
			$param_array 	= array();
			$param_array[1]	= $ids;
			$param_array[2]	= $this->currentUser->username;
			
			$result 	= $this->SFA_Comman->executequery('CALL sp_delete_hhctransaction_invoice_advpayment(?,?)',$param_array,'');
			
			if($result[0][0]['result'] == 'Not Found') {
				SFA_Message::setErrorMsg($this->translate->_('Record Already In use'));
			} else {
				SFA_Message::setMsg($this->translate->_('Delete Record'));	
			}
			$this->_helper->redirector("advpayment", "invoice", "hhctransaction");
		}
	
		if($formdata["hdDelete"]==1)
		{
			$ids = implode(',',$formdata['chk']);
			$param_array 	= array();
			$param_array[1]	= $ids;
			$param_array[2]	= $this->currentUser->username;
			
			$result 	= $this->SFA_Comman->executequery('CALL sp_delete_hhctransaction_invoice_advpayment(?,?)',$param_array,'');
			
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
		
		// For Alternate Code Status.
		$cpanel				= $this->SFA_Comman->getaltcodestatus();
		$altcode_status		= $cpanel["Use Alternate Code"]['status'];
		
		if($altcode_status)
		{
			$cols_array = array('cm.alternatecode','customername','invoicenumber','routename','salesmanname1','FORMAT(amountpaid,'.$this->decimalplaces.') AS totalinvoiceamount ','transactionkey as edit_del_primary_id' );
		}
		else
		{
			$cols_array = array('cm.customercode','customername','invoicenumber','routename','salesmanname1','FORMAT(amountpaid,'.$this->decimalplaces.') AS totalinvoiceamount ','transactionkey as edit_del_primary_id' );
		}
		$columns_show =  array( $this->translate->_('Customer Code'),
								$this->translate->_('Customer'),
								$this->translate->_('Receipt No'),
								$this->translate->_('Route'),
								$this->translate->_('Salesman'),
								$this->translate->_('Receipt Amount'));
			
		$not_in_search 		= array();
		$not_in_search[] 	= 'transactiondate';
		
		
		// CREATE A SESSION NAMESPACE
		$Common_NameSpace = new Zend_Session_Namespace('AdvPayment');
			
		if($formdata['btnreset'] == 'RESET')
		{
			$formdata["txtdate"] 	= '';
			$Common_NameSpace->tdate	= '';
		}
		
		$last_url = htmlspecialchars($_SERVER['HTTP_REFERER']);
		if(strpos($last_url,'advpayment'))
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
			$this->view->date		= $sel_date;
		}
		else
		{
			$Common_NameSpace->tdate 	= date('d-m-Y');
			$this->view->date		= date('d-m-Y');
		}
		
		
		
		// ADDITIONAL WHERE CONDITION
		if($Common_NameSpace->tdate)
			$additional_where_condition[] = " (transactiondate BETWEEN \'".date("Y-m-d 00:00:00",strtotime(str_replace('/', '-', $Common_NameSpace->tdate)))."\' AND \'".date("Y-m-d 23:59:59",strtotime(str_replace('/', '-', $Common_NameSpace->tdate)))."\' )   AND record_flag = \'1\' ";
		
		// prepare the configuration for grid
		$pagingparams = array(
				 "show_grid_heading" => true,
				 "grid_heading_message" => $this->translate->_('Overview'),
				 "show_records_per_page" => isset($formdata["show_records_per_page"])?$formdata["show_records_per_page"]:10,
				 "show_searchbox" => true,
				 "searchbox_value" => isset($formdata["grid_search_text"])?$formdata["grid_search_text"]:"",
				 "show_selectbox" => true,
				 "show_editlink" => false,
				 "show_deletelink" => false,
				 "show_deleteall" => false,
				 'show_extralink' => true,
				 'extralink' => array(array("View","/".$params['module']."/".$params['controller']."/advpaymentheader/id/#pattern#","#pattern#")),
				 "no_search_fields" => $not_in_search,
				 "primaryid" => "transactionkey",			 
				 "nodata_message" => $this->translate->_('No Record(s) Found'),
				 "fetch_columns_inquery" => $cols_array,
				 "show_columns" => $columns_show,
				 "additional_where" => $additional_where_condition,
				 "show_columns_right_side"=>array('totalinvoiceamount'),
				 "show_header_right_side"=>array($this->translate->_('Receipt Amount')),
				 );
		
		// create grid class object
		$pagingshow = new SFA_Paging($pagingparams);
		
		// call common function of grid class
		$get_return_vals = $pagingshow->commnfunc();
		
		// call the stored procedure for fetch the data  
		$param_array[1] = '1';
		$param_array[2] = '';
		$param_array[3] = $get_return_vals['order_columns_name'];
		$param_array[4] = $get_return_vals['order_type'];
		$param_array[5] = $get_return_vals['offset'];
		$param_array[6] = (int)$get_return_vals['show_records_per_page'];
		$param_array[7] = implode(", ",$pagingparams["fetch_columns_inquery"]);
		$param_array[8] = strlen($get_return_vals['where_condition'])>0?$get_return_vals['where_condition']:'';	
		
		$result 	= $this->SFA_Comman->executequery('CALL sp_get_transaction_index_advpayment(?,?,?,?,?,?,?,?)',$param_array,'');
		$data_arr["count"] 	= $result[0][0]['counter'];
		$data_arr["data"][0] 	= $result[1];
		
		// pass the data in summary_showdatagrid() function & create a final variable for view
		$this->view->pagegridshow =  $pagingshow->summary_showdatagrid($data_arr);
		
		$this->view->addScriptPath(APPLICATION_PATH."/theme/default/datagrid/");
    }
    /**
    * @name       advpaymentheader
    * @since      06-02-2012
    * @version    Release: 1
    * @author     M@M <miral@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This action is for salesorder header
    *
    */
    public function advpaymentheaderAction()
    {
	$this->view->params = $params = $this->getRequest()->getParams();
        $this->view->formdata = $formdata = $this->_request->getPost();

	
	
	$Common_NameSpace = new Zend_Session_Namespace('AdvPayment');

	$sel_date = ($formdata["txtdate"] != '') ? $formdata["txtdate"] : $Common_NameSpace->tdate;

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
	
	if($params['id'] > 0)
	{
	    $result = $this->SFA_Comman->executequery('CALL sp_get_transaction_index_advpaymentdetail(?)',$params['id'],'');
	    $this->view->route_info	= $result[0];
	    $this->view->formdata 	= $result[1][0];
	    
	    //$this->view->arpayment 	= $result[2][0]; // Commented by rachir for below change
	    
	    // Added By rachir on 19-July-2012 for payment mode information.
	    $this->view->paymentdata 	= $result[2][0] ;
	    
	    
	}
    }	    
}