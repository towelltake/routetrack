<?php
/**
* @name       Hhctransaction_ArcollectionController
* @since
* @version    Release: 5
* @author     GP<gayatri@elantechnologies.com>
* @copyright  Elan Technologies
* @param
*
* This controller is manage hhctransaction module (Advance Payment Related).
* 
*/
class Hhctransaction_ArcollectionController extends Hhctransaction_Library_Controller_Action_Abstract
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
    * @name       arcollectionaddAction
    * @since      10-9-2012
    * @version    Release: 8
    * @author     GP <gayatri@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This action is use for call Arcollection add data processing.
    * in this action we get route wise customer and it's payment information 
    *
    */
    
    public function arcollectionaddAction()
    {
	$this->view->params = $params = $this->getRequest()->getParams();
	$this->view->formdata = $formdata = $this->_request->getPost();
	
	$param_array =array();
	
	$result 	= $this->SFA_Comman->executequery('CALL sp_get_arcollection_arcollectionadd()','','');
	//echo "<pre>";
	//print_r($result );
	$this->view->route = 	$result[0];
	$this->view->bank  = 	$result[1];
		
	//CALL sp_get_arcollection_arcollectionadd();
	//above sp
	
	//gridcall parameter
	//http://localhost/sfa/ver5/hhctransaction/arcollection/gccollectiongrid/key1/12/key2/1205/key3/12
    }
    
    /**
    * @name       collectiongridAction
    * @since      10-9-2012
    * @version    Release: 8
    * @author     GP <gayatri@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This action is use for call Arcollection data grid here user have option
    * to pay amount either FIFO (First In First Out)wise or Custom payment item wise.
    */
    public function collectiongridAction(){
        $this->view->params = $params = $this->getRequest()->getParams();
		
		$columns_array 	= array('invoicenumber','DATE_FORMAT(transactiondate,"%d-%m-%Y") AS transactiondate','FORMAT(totalinvoiceamount,'.$this->decimalplaces.') as totalinvoiceamount','FORMAT(invoicebalance,'.$this->decimalplaces.') as invoicebalance','concat(transactionkey,"_",invoicenumber) as edit_del_primary_id');
		$columns_show  	= array($this->translate->_('Invoice No'),$this->translate->_('Invoice Date'),$this->translate->_('Invoice Amount'),$this->translate->_('Balance Amount'));

		// IF EXTRA PARAMS ARE REQUIRED
		$ex_param = "";
		$additional_where_condition = array();
		if(isset($params["key1"]) && $params["key1"]>0) {
			$ex_param = "/key1/".$params["key1"]."/key2/".$params["key2"]."/key3/".$params["key3"];
			$additional_where_condition[] = ' (routecode = "'.$params["key1"].'" AND customercode = "'.$params["key2"].'"
			AND salesmancode = "'.$params["key3"].'"
			AND TRUNCATE(invoicebalance,0) != 0) ';
		}
		$amt_right = array('exchangerate');
		$showtextbox = false;
		if(isset($params["key4"]) && $params["key4"] == 0)
		{
		    $showtextbox =true;
		}
	
		// ARRAY FOR GRID PAGINATION
		$pagingparams = array(
				"show_grid_heading" => false,
				"grid_heading_message" => $this->translate->_('Overview'),
				"show_records_per_page" => isset($formdata["show_records_per_page"])?$formdata["show_records_per_page"]:10,
				"show_searchbox" => false,
				"show_selectbox" => true,
				"show_editlink" => false,
				"show_deletelink" => false,
				"show_deleteall" => false,
				"show_extratextbox" => $showtextbox,
				"show_datasorting" => '1',
				"primaryid" => "transactionkey",
				"currentlink" => array("/".$params['module']."/".$params['controller']."/".$params['action'].$ex_param),
				"nodata_message" => $this->translate->_('No Record(s) Found'),
				"fetch_columns_inquery" => $columns_array,
				"show_columns" => $columns_show,
				"additional_where" => $additional_where_condition,				
				"show_columns_right_side" =>array('invoicebalance','totalinvoiceamount'),
				"show_header_right_side"=>array($this->translate->_('Balance Amount'),$this->translate->_('Invoice Amount')),
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
		$result = $this->SFA_Comman->executequery('CALL sp_get_arcollection_arcollectionadd_grid(?,?,?,?,?,?,?)',$param_array,'');    
		$data_arr["count"] 		= $result[0][0]['counter'];	
		$data_arr["data"][0] 		= $result[1];
		
		$this->view->pagegridshow =  $pagingshow->summary_showdatagrid($data_arr);
		$this->view->addScriptPath(APPLICATION_PATH."/theme/default/datagrid/");
		$this->render("ajaxgrid");
    }
    
   /**
    * @name       customerlistAction
    * @since      10-9-2012
    * @version    Release: 8
    * @author     GP <gayatri@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This action is use for list customer base on route selection.(ajax call)
    * 
    */
    
    public function customerlistAction()
    {
	try
	{
	    $this->view->params = $params = $this->getRequest()->getParams();
	    $this->view->formdata = $formdata = $this->_request->getPost();
	    
	   
	    $param_array[1] =$this->view->params['route_code'];
	    $result = $this->SFA_Comman->executequery('CALL sp_combo_customer_routewise_collection(?)',$param_array,'');
	    $this->customer	= $result[0];
	    
	    if(!empty($this->customer	))
	    {
		echo "<option value=''>--- Select ---</option>";
		foreach($this->customer	 as $value)
		{
		    $final_vla =$value['id'];
		    echo "<option value='".$final_vla."'>".$value['val']."</option>";
		}
		
	    }
	    exit;   
	}catch (Zend_Exception $e)
		{
			echo "Error message: " . $e->getMessage() . "\n";
		}
    }
       
   /**
    * @name       getroutewisedataAction
    * @since      10-9-2012
    * @version    Release: 8
    * @author     GP <gayatri@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This action is use for  collect summaries data setting related to Arcollection Module
    * 
    */
    public function getroutewisedataAction()
    {
	try
	{
	    $this->view->params = $params = $this->getRequest()->getParams();
	    $this->view->formdata = $formdata = $this->_request->getPost();
	    
	   
	    $param_array[1] =$this->view->params['route_code'];
	    $result = $this->SFA_Comman->executequery('CALL sp_get_arcollection_arcollectionadd_settings(?)',$param_array,'');
	    //print_r($result);
	   echo implode("$::$",$result[0][0]);
	   exit;   
	}catch (Zend_Exception $e)
		{
			echo "Error message: " . $e->getMessage() . "\n";
		}
    }
   /**
    * @name       savearcollectionAction
    * @since      10-9-2012
    * @version    Release: 8
    * @author     GP <gayatri@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This action is use for save arcollection data
    * amount paid is grater then 0 value then an then collection process start otherwise 
    * no any collection processs apply.
    */
    public function savearcollectionAction()
    {
	    $this->params = $params = $this->getRequest()->getParams();
	    $this->formdata = $formdata = $this->_request->getPost();
	    
	    
		$summery_arr =array();
		$transaction_key_arr =array();
		$summation  = 0;
		if(isset($this->formdata['chk']) && $this->formdata['chk'] != "")
		{
		    for($i=0;$i < count($this->formdata['chk']);$i++)
		    {
			list($number,$invoice_no) = explode("_",$this->formdata['chk'][$i]);
			$summery_arr[] = str_replace(",","",$this->formdata['extratextbox_'.$invoice_no]);
			$summation  = $summation + $this->formdata['extratextbox_'.$invoice_no];
			$transaction_key_arr[] = $number;
			
		    }
		}
		
		if(!isset($this->formdata['chkfirstout']) && empty($this->formdata['chkfirstout']))
		{
		    $this->formdata['chkfirstout'] = 0;
		    $this->formdata['txtamount'] = array_sum($summery_arr);
		}
		
	    if(isset($this->formdata['txtamount']) && $this->formdata['txtamount'] > 0   )
	    {
		if($this->formdata['txtcheckno'] == "")
		$this->formdata['txtcheckno'] = 0;
		
		 if($this->formdata['ddlbankname'] == "")
		$this->formdata['ddlbankname'] = 0;
		
		if($this->formdata['txtcheckdt'] != "")
		$this->formdata['txtcheckdt'] = date("Y-m-d 00:00:00",strtotime(str_replace('/', '-', $this->formdata['txtcheckdt'])));
		else
		$this->formdata['txtcheckdt'] = "0000-00-00";
		
		
		$SFA_Model_Arcollection = new SFA_Model_Arcollection();
		 
		$generate_key_arr = $SFA_Model_Arcollection->generate_key_data($this->formdata);
		$generate_key_arr  = $SFA_Model_Arcollection->add_arheader($this->formdata,$generate_key_arr,$transaction_key_arr,$summery_arr);
		
		if($this->formdata['chkfirstout'] == 1)
		 {
		    $new_result_set = $SFA_Model_Arcollection->first_in_first_out($this->formdata,$generate_key_arr);
		 }
		else
		 {
		    $new_result_set = $SFA_Model_Arcollection->custom_payment($transaction_key_arr,$summery_arr,$this->formdata,$generate_key_arr);
		 }
	    }
	    
	  
	exit;
    }
}