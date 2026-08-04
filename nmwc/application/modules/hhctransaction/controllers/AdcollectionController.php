<?php
/**
* @name       Hhctransaction_AdcollectionController
* @since
* @version    Release: 5
* @author     GP<gayatri@elantechnologies.com>
* @copyright  Elan Technologies
* @param
*
* This controller is manage hhctransaction module (Advance Payment Related).
* 
*/
class Hhctransaction_AdcollectionController extends Hhctransaction_Library_Controller_Action_Abstract
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
	$this->sec_lang 	                        	= $this->view->sec_lang;
	$this->decimalplaces  = $this->view->decimalplaces	= $this->SFA_Comman->getdecimalplaces();
	$this->view->sec_lang	                        	= $this->SFA_Comman->getsecondlanguage();
	$this->view->required		= $this->translate->_('Required');
	$this->view->colan		= $this->translate->_('Colan');
    }
    
   /**
    * @name       adcollectionaddAction
    * @since      10-9-2012
    * @version    Release: 8
    * @author     GP <gayatri@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This action is use for advance payment add call.
    *
    */
    public function adcollectionaddAction()
    {
	$this->view->params = $params = $this->getRequest()->getParams();
	$this->view->formdata = $formdata = $this->_request->getPost();
	
	$param_array =array();
	
	$result 	= $this->SFA_Comman->executequery('CALL sp_get_adcollection_adcollectionadd()','','');
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
    * This action is load customer wise invoice information.
    *
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
    * This action load customer list base on route selection wise.
    *
    */    
    public function customerlistAction()
    {
	try
	{
	    $this->view->params = $params = $this->getRequest()->getParams();
	    $this->view->formdata = $formdata = $this->_request->getPost();
	    
	   
	    $param_array[1] =$this->view->params['route_code'];
	    $result = $this->SFA_Comman->executequery('CALL sp_combo_customer_routewise(?)',$param_array,'');
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
    * This action load advance payment settings value. this information is help into show hide option
    *
    */    
    public function getroutewisedataAction()
    {
	try
	{
	    $this->view->params = $params = $this->getRequest()->getParams();
	    $this->view->formdata = $formdata = $this->_request->getPost();
	    
	   
	    $param_array[1] =$this->view->params['route_code'];
	    $result = $this->SFA_Comman->executequery('CALL sp_get_adcollection_adcollectionadd_settings(?)',$param_array,'');
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
    * This action save Advancepayment information into arheader table and update customer credit limit
    *
    */    
    public function savearcollectionAction()
    {
	    $this->params = $params = $this->getRequest()->getParams();
	    $this->formdata = $formdata = $this->_request->getPost();
	    
	    if(isset($this->formdata['txtamount']) && $this->formdata['txtamount'] > 0 )
	    {
			if($this->formdata['ddlbankname'] == "")
			{
				$this->formdata['ddlbankname']=0;
			}
			if($this->formdata['txtcheckno'] == "")
			{
				$this->formdata['txtcheckno']=0;
			}
			$param_array 		= array();
			$param_array[1] 	= $this->formdata['ddlcustomer'];
			$param_array[2] 	= $this->formdata['ddlroute'];
			$param_array[3] 	= $this->formdata['txtsalesman_code'];
			$param_array[4] 	= $this->formdata['txtremark1'];
			$param_array[5] 	= $this->formdata['ddlpaymode'];
			$param_array[6] 	= $this->formdata['txtamount'];
			$param_array[7] 	= $this->formdata['txtcheckno'];
			$param_array[8] 	= $this->formdata['txtcheckdt'];
			$param_array[9] 	= $this->formdata['ddlbankname'];
			$param_array[10]	= "0";
			$result 			= $this->SFA_Comman->executequery('CALL sp_add_adcollection_adcollectionadd(?,?,?,?,?,?,?,?,?,?)',$param_array,'');	
	    }
		exit;
    }
}