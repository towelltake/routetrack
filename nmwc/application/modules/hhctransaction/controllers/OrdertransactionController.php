<?php
/**
* @name       IndexController
* @since
* @version    Release: 8
* @author     GP<gayatri@elantechnologies.com>
* @copyright  Elan Technologies
* @param
*
* This controller is manage hhctransaction module.
* 
*/


class Hhctransaction_OrdertransactionController extends Hhctransaction_Library_Controller_Action_Abstract
{
   /**
    * @name       init
    * @since      01-02-2012
    * @version    Release: 8
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
		
	$this->sec_lang 	                        	= $this->view->sec_lang;
	$this->decimalplaces  = $this->view->decimalplaces	= $this->SFA_Comman->getdecimalplaces();
	$this->view->sec_lang	                        	= $this->SFA_Comman->getsecondlanguage();
	
	$this->view->header = $this->translate->_('Header');
	$this->view->detail = $this->translate->_('Detail');
	
	$this->view->required		= $this->translate->_('Required');
	$this->view->colan		= $this->translate->_('Colan');
	
	
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
    * @name       init
    * @since      01-02-2012
    * @version    Release: 8
    * @author     GP <gayatri@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This is action is use for add ordercollection
    *
    */
    
    public function salesorderaddAction()
    {
	
	$this->view->params = $params = $this->getRequest()->getParams();
        $this->view->formdata = $formdata = $this->_request->getPost();

	Zend_Session::namespaceUnset('Add_Sales_Order');
	
	$this->view->css 		= $this->translate->_('CSS');
	
	$Common_NameSpace = new Zend_Session_Namespace('Add_Sales_Order');
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
	
	$result_arr = $this->SFA_Comman->executequery('CALL sp_get_ordertransaction_salesorderadd();','','');
	
	$this->view->route	= $result_arr[0];
      
	$this->view->return_reason	 = $result_arr[1];
	$this->view->freegood_reason 	 = $result_arr[2];
	$this->view->bad_reason	 	 = $result_arr[3];
	$this->view->damage_reason 	 = $result_arr[3];
	$this->view->expiry_reason	 = $result_arr[3];
	
	
    }
    
    /**
    * @name       promotiondetailAction
    * @since      01-02-2012
    * @version    Release: 8
    * @author     GP <gayatri@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This is action view promotion detail
    *
    */
       
    public function promotiondetailAction()
    {
	$this->_helper->layout->setLayout('popup');
    }
   
   
   /**
    * @name       invoicedetail
    * @since      20-02-2012
    * @version    Release: 8
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
        
	
	$storage 		= new Zend_Session_Namespace('Add_Sales_Order');
	
	if(isset($params['delete']) && $params['delete'] == 'yes')
	{
	    $new_obj = new SFA_Model_Orderpromotioncalculate();
	    $new_obj->remove_invoice_item(array("invoice_primary_key"=>$params['id'],"transaction_key"=>$storage->Invoice[0]['invoice_transaction_key']));
	}
    
    $additional_where_condition = array();
	$additional_where_condition[] = ' ( salesqty > 0 || returnqty > 0 || damagedqty > 0 || manualfreeqty > 0 ) ';
		
    
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
		$return_pcs_price	= $this->translate->_('Pcs Price');
		$case_sales_qty		= $this->translate->_('CAS');
		$pcs_sales_qty		= $this->translate->_('PCS');
		$case_return_qty	= $this->translate->_('Case Price');
		$pcs_return_qty		= $this->translate->_('Pcs Price');
		$case_damage_qty	= $this->translate->_('CAS');
		$pcs_damage_qty		= $this->translate->_('PCS');
		$case_damage_price	= $this->translate->_('Case Price');
		$pcs_damage_price	= $this->translate->_('Pcs Price');
		$case_free_qty		= $this->translate->_('CAS');
		$pcs_free_qty		= $this->translate->_('PCS');
		$total_amount		= $this->translate->_('Total Amount');
	
		// For Alternate Code Status.
		$cpanel				= $this->SFA_Comman->getaltcodestatus();
		$altcode_status		= $cpanel["Use Alternate Code"]['status'];
		
		if($altcode_status)
		{
			$columns_array		= array('primary_key as edit_del_primary_id','im.alternatecode','im.itemshortdescription','im.unitspercase',
							'FLOOR((salesqty/im.unitspercase)) AS case_sales_qty','(salesqty%im.unitspercase) AS pcs_sales_qty',
							'FORMAT(id.salescaseprice,'.$this->decimalplaces.') AS salescaseprice','FORMAT(id.salesprice,'.$this->decimalplaces.') AS salesprice ',
							'FLOOR((returnqty/im.unitspercase)) AS case_return_qty','(returnqty%im.unitspercase) AS pcs_return_qty',
							'FORMAT(id.goodreturncaseprice,'.$this->decimalplaces.') AS goodreturncaseprice','FORMAT(id.goodreturnprice,'.$this->decimalplaces.') AS goodreturnprice',
							'FLOOR((damagedqty/im.unitspercase)) AS case_damage_qty','(damagedqty%im.unitspercase) AS pcs_damage_qty',
							'FORMAT(id.returncaseprice,'.$this->decimalplaces.') AS returncaseprice','FORMAT(id.returnprice,'.$this->decimalplaces.') AS returnprice',
							'FLOOR((manualfreeqty/im.unitspercase)) AS case_manualfreeqty_qty','(manualfreeqty%im.unitspercase) AS pcs_manualfreeqty_qty',
							'FORMAT((((FLOOR(salesqty/im.unitspercase)*id.salescaseprice)+
						(FLOOR(salesqty%im.unitspercase)*id.salesprice))-((FLOOR(returnqty/im.unitspercase)*id.goodreturncaseprice)
						+(FLOOR(returnqty%im.unitspercase)*id.goodreturnprice))-((FLOOR(damagedqty/im.unitspercase)*id.returncaseprice)
						+(FLOOR(damagedqty%im.unitspercase)*id.returnprice))-promoamount),'.$this->decimalplaces.') AS total_amount'
							);
			$columns_show  = array($alt_code,$item_name,$upc,$sales_case_price,$sales_pcs_price,$return_case_price,$return_pcs_price,$case_sales_qty,$pcs_sales_qty,$case_return_qty,$pcs_return_qty,$case_damage_qty,$pcs_damage_qty,$case_damage_price ,$pcs_damage_price,$case_free_qty,$pcs_free_qty,$total_amount);
		}
		else  
		{
			$columns_array		= array('primary_key as edit_del_primary_id','im.actualitemcode','im.itemshortdescription','im.unitspercase',
					'FLOOR((salesqty/im.unitspercase)) AS case_sales_qty','(salesqty%im.unitspercase) AS pcs_sales_qty',
					'FORMAT(id.salescaseprice,'.$this->decimalplaces.') AS salescaseprice','FORMAT(id.salesprice,'.$this->decimalplaces.') AS salesprice ',
					'FLOOR((returnqty/im.unitspercase)) AS case_return_qty','(returnqty%im.unitspercase) AS pcs_return_qty',
					'FORMAT(id.goodreturncaseprice,'.$this->decimalplaces.') AS goodreturncaseprice','FORMAT(id.goodreturnprice,'.$this->decimalplaces.') AS goodreturnprice',
					'FLOOR((damagedqty/im.unitspercase)) AS case_damage_qty','(damagedqty%im.unitspercase) AS pcs_damage_qty',
					'FORMAT(id.returncaseprice,'.$this->decimalplaces.') AS returncaseprice','FORMAT(id.returnprice,'.$this->decimalplaces.') AS returnprice',
					'FLOOR((manualfreeqty/im.unitspercase)) AS case_manualfreeqty_qty','(manualfreeqty%im.unitspercase) AS pcs_manualfreeqty_qty',
					'FORMAT((((FLOOR(salesqty/im.unitspercase)*id.salescaseprice)+
						(FLOOR(salesqty%im.unitspercase)*id.salesprice))-((FLOOR(returnqty/im.unitspercase)*id.goodreturncaseprice)
						+(FLOOR(returnqty%im.unitspercase)*id.goodreturnprice))-((FLOOR(damagedqty/im.unitspercase)*id.returncaseprice)
						+(FLOOR(damagedqty%im.unitspercase)*id.returnprice))-promoamount),'.$this->decimalplaces.') AS total_amount'
					);
			$columns_show  = array($alt_code,$item_name,$upc,$sales_case_price,$sales_pcs_price,$return_case_price,$return_pcs_price,$case_sales_qty,$pcs_sales_qty,$case_return_qty,$pcs_return_qty,$case_damage_qty,$pcs_damage_qty,$case_damage_price ,$pcs_damage_price,$case_free_qty,$pcs_free_qty,$total_amount);
		}
	// prepare the configuration for grid
	$pagingparams = array(
			"show_grid_heading" => false,
			"grid_heading_message" => $this->translate->_('Overview'),
			"show_records_per_page" => isset($formdata["show_records_per_page"])?$formdata["show_records_per_page"]:25,
			"show_searchbox" => false,
			"show_selectbox" => false,
			"show_editlink" => false,
			"show_deletelink" => true,			
			"show_deleteall" => false,
			"primaryid" => "primary_key",
			"currentlink" => array("/hhctransaction/ordertransaction/detailinvoice".$ex_param),
			"editlink" => array("/id/#pattern#/edit/yes","#pattern#"),
			"deletelink" => array("/id/#pattern#/delete/yes","#pattern#"),
			"nodata_message" => $this->translate->_('No Record(s) Found'),
			"fetch_columns_inquery" => $columns_array,			
			"show_columns" => $columns_show,
			"show_top_columns" => true,
			"show_top_columns_value" => array(array("3",""),array("4","Order"),array("4","Return"),array("4","Damage"),array("2","Free"),array("2","")),
			"show_total_columns"=>true,			
			"show_total_columns_value"=>array("case_sales_qty"=>"0","pcs_sales_qty"=>"0","case_return_qty"=>"0","pcs_return_qty"=>"0","case_damage_qty"=>"0","pcs_damage_qty"=>"0","case_manualfreeqty_qty"=>"0","pcs_manualfreeqty_qty"=>"0","total_amount"=>"1"),
			"show_total_columns_msg"=>array("itemshortdescription","Total",$this->decimalplaces),
			"show_columns_right_side" =>array('salescaseprice','salesprice','returncaseprice','returnprice','goodreturnprice','goodreturncaseprice',"case_sales_qty","pcs_sales_qty","case_return_qty","pcs_return_qty","case_damage_qty","pcs_damage_qty","case_manualfreeqty_qty","pcs_manualfreeqty_qty","total_amount"),
			"show_header_right_side"=>array($this->translate->_('Case Price'),$this->translate->_('Pcs Price'),$this->translate->_('CAS'),$this->translate->_('PCS'),$this->translate->_('Discount'),$this->translate->_('Total Amount')),
			"additional_where" => $additional_where_condition,
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
	$result = $this->SFA_Comman->executequery('CALL sp_get_ordertransaction_salesorderadd_salesorderdetailgrid(?,?,?,?,?,?,?)',$param_array,'');   

	$data_arr["count"] 	= $result[0][0]['counter'];
	$data_arr["data"][0]	= $result[1];
	
	$this->view->result_arr =$data_arr["data"][0];
	//$this->view->tc_amount_value = $result[2][0]['tc_amount'];
	$this->view->pagegridshow =  $pagingshow->summary_showdatagrid($data_arr);
        $this->view->addScriptPath(APPLICATION_PATH."/theme/default/datagrid/");
	$this->render("ajaxgridinvoice");
    }
    /**
    * @name       promotionplanAction
    * @since      01-02-2012
    * @version    Release: 8
    * @author     GP <gayatri@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This is action display promotion plan
    *
    */
    public function promotionplanAction()
    {
	
	$params 		= $this->getRequest()->getParams();
	$storage 		= new Zend_Session_Namespace('Add_Sales_Order');
	
	
        $this->view->check_payment_terms  = $storage->validation['enforcepromotion'];
	$param_array[1] = $params['customer_code'];
	$param_array[2] = $storage->Invoice[0]['visitkey'];
	$param_array[3] = $storage->Invoice[0]['routekey'];
	
	
	
	// called stored procedure for counter
	$result = $this->SFA_Comman->executequery('CALL sp_get_ordertransaction_salesorderadd_promotion_plan(?,?,?)',$param_array,'');
	
	$counter=0;
	foreach($result[1] as $val)
	{
	    if($val['qualified_promotion'] == 1)
	    {
		$counter=1;
	    }
	}
	
	    $this->view->promotion_data= $result[0];
	    $this->view->in_routekey = $param_array[3];
	    $this->view->in_visitkey = $param_array[2];
	    $this->view->in_customercode =$params['customer_code'];
	    $this->view->counter_val = $counter;
	    
	
	
	
	
	
	//print_r($this->view->promotion_data);exit;
	
	
    }
     /**
    * @name       promotiondetailviewAction
    * @since      01-02-2012
    * @version    Release: 8
    * @author     GP <gayatri@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This is action display promotion detail screen plan
    *
    */
    public function promotiondetailviewAction()
    {
	$params 		= $this->getRequest()->getParams();
	 $storage 		= new Zend_Session_Namespace('Add_Sales_Order');
	
	$param_array[1] = $params['plannumber'];
	$this->_helper->layout->setLayout('popup');

	//SFA_Comman::pre($param_array);exit;
	
	// called stored procedure for counter
	$result = $this->SFA_Comman->executequery('CALL sp_get_ordertransaction_salesorderadd_promotion_detail(?)',$param_array,'');   
	$this->view->promotion_data= $result[0];
	
    }
   
}