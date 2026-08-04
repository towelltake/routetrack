<?php
/**
* @name       Hhctransaction_AjaxdataController
* @since
* @version    Release: 8
* @author     GP<gayatri@elantechnologies.com>
* @copyright  Elan Technologies
* @param
*
* This controller is manage hhctransaction module.
* 
*/


class Hhctransaction_AjaxdataController extends Hhctransaction_Library_Controller_Action_Abstract
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
	
	
    }

    
     /**
    * @name       customerlistAction
    * @since      01-02-2012
    * @version    Release: 8
    * @author     GP <gayatri@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This action Load  customer combobox data base on route selection
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
    * @name       checkprocessloadAction
    * @since      01-02-2012
    * @version    Release: 8
    * @author     HD <hiren.d@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This action for check process load based on the routecode
    *
    */
    
    public function checkprocessloadAction()
    {
		$params = $this->getRequest()->getParams();
        $paramsdata[1] = $params['routeid'];
		$result = $this->SFA_Comman->executequery('CALL sp_get_hhctransaction_transaction_checkprocessload(?)',$paramsdata,'');
	    echo $result[0][0]['counter'];
		exit;		
	}
    
    /**
    * @name       itemlistAction
    * @since      01-02-2012
    * @version    Release: 8
    * @author     GP <gayatri@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This action Load  item combobox
    *
    */
    
    public function itemlistAction()
    {
	try
	{
	    $this->view->params = $params = $this->getRequest()->getParams();
	    $this->view->formdata = $formdata = $this->_request->getPost();
	    
	    $param_array[1] =$this->view->params['route_code'];
	    $result = $this->SFA_Comman->executequery('CALL sp_get_items_routeitemgroupcode(?)',$param_array,'');
	    $this->customer	= $result[0];
	    
	    if(!empty($this->customer	))
	    {
		echo "<option value=''>--- Select ---</option>";
		foreach($this->customer	 as $value)
		{
		    echo "<option value='".$value['id']."'>".substr($value['val'],0,40)."</option>";
		}
	    }
	     exit;
	}catch (Zend_Exception $e)
		{
			echo "Error message: " . $e->getMessage() . "\n";
		}
    }
  
    /**
    * @name       itempriceinfoAction
    * @since      01-02-2012
    * @version    Release: 8
    * @author     GP <gayatri@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This is action collect item price base on customerwise.
    * customer have special price then it's load special price othewise load itemmaster prices
    *
    */
    
    public function itempriceinfoAction()
    {
        try
        {
            $this->view->params = $params = $this->getRequest()->getParams();
            $this->view->formdata = $formdata = $this->_request->getPost();
            $storage 		= new Zend_Session_Namespace('Add_invoice_data');
            $SFA_Model_Batch = new SFA_Model_Batch();
            $result = $SFA_Model_Batch->check_item_exist(array("itemcode"=>$this->view->params['item_id'],"visitkey"=>$storage->Invoice[0]["visitkey"]));
            
            if($result[0]['cnt'] > 0)
            {
                echo "exist";
            }
            else
            {
                $param_array    = array();
                $param_array[1] = $this->view->params['item_id'];
                $param_array[2] = $this->view->params['customer_code'];
                $param_array[3] = $this->decimalplaces;
                $param_array[4] = $storage->Invoice[0]["routekey"];
                
                $result 	    = $this->SFA_Comman->executequery('CALL sp_get_transaction_invoiceadd_itemprice(?,?,?,?)',$param_array,'');
                
                $result_Arr     = $result[0][0];
                $allowbatchentry = $result[1][0]["allowbatchentry"];
                $result_Arr[] = $allowbatchentry;
                $storage->Allowitemadd   = 0;
                $storage->Rallowitemadd  = 0;
                echo implode("$::$",$result_Arr);
            }
            exit;
        }
        catch (Zend_Exception $e)
		{
			echo "Error message: " . $e->getMessage() . "\n";
		}
    }
    
    /**
    * @name       checkpromotionAction
    * @since      01-02-2012
    * @version    Release: 8
    * @author     GP <gayatri@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This is action check/verify only promotion and promotion list. if promotion is qualified or not.
    *
    */
    public function checkpromotionAction()
    {
	try
	{
	    $this->params = $params = $this->getRequest()->getParams();
	    $this->formdata = $formdata = $this->_request->getPost();
	    
	    $storage 		= new Zend_Session_Namespace('Add_invoice_data');
	    
	    $param_array 	=	array();
	    $param_array1[1] 	=	$param_array[1] =$storage->Invoice[0]['routekey'];
	    $param_array1[2] 	=	$param_array[2] =$storage->Invoice[0]['visitkey'];
	    $param_array[3] 	=	$this->params['in_customercode'];
	    $param_array[4] 	=	$this->params['assignment_no'];
	    $param_array[5] 	=	$this->params['plan_no'];
	    $param_array[6] 	=	$this->params['range'];
	    $param_array[7] 	=	$this->params['in_promotiontypecode'];
	    $param_array[8] 	=	$storage->Invoice[0]['invoice_transaction_key'];
	    $param_array1[3] 	=  	$this->params['qualification_group_no'];
	    
        $param_array2[1] = $this->params['assignment_no'];
        $param_array2[2] = $this->params['in_customercode'];
	    $param_array2[3] = $storage->Invoice[0]['routekey'];
        $param_array2[4] = $storage->Invoice[0]['visitkey'];
        $param_array2[5] = $this->params['plan_no'];
        $param_array2[6] = $storage->validation['Enable_Sales_Promotion'];
        
        if($this->params['range'] == 3) {
            $result 	= $this->SFA_Comman->executequery('CALL sp_check_transaction_invoiceadd_promotionqualified_fixed(?,?,?,?,?,?)',$param_array2,'');
        } else {
            $result 	= $this->SFA_Comman->executequery('CALL sp_check_transaction_invoiceadd_promotionqualified(?,?,?)',$param_array1,'');
        }
	    //pr($result,1);
        
        $data = array("assignment_no"=>$this->params['assignment_no'],
                      "SalesQty"=> $result[0][0]["SalesQty"],
                      "SalesAmount"=> $result[0][0]["SalesAmount"],
                      "ReturnQty"=> $result[0][0]["ReturnQty"],
                      "ReturnAmount"=> $result[0][0]["ReturnAmount"],
                      "range"=>$this->params['range']
                      );
        
        $SFA_Model_Promotioncalculate = new SFA_Model_Promotioncalculate();
        $SFA_Model_Returnpromotioncalculate = new SFA_Model_Returnpromotioncalculate();
        $total_sales_qnt = $total_ret_qnt = 0;
        if($storage->validation['Enable_Sales_Promotion'] == 1 || $storage->validation['Enable_Sales_Promotion'] == 3)
        {
            if($this->params['range'] == "3") {
                $pAmount=1;
                for($j=0;$j<count($result[0]);$j++)
                {
                    $salesqty = $result[0][$j]["SalesQty"];
                    $total_sales_qnt += ($salesqty != "" && $salesqty >0) ? $salesqty : 0;
                    $salesamount = $result[0][$j]["SalesAmount"];
                    $itemcode = $result[0][$j]["itemcode"];
                    $itemqty = $result[0][$j]["itemqty"];
                    $repeatingrange = $result[0][$j]["onetimeuse"];
                    
                    if($salesqty%$itemqty == 0)
                    {
                        $pRange = $salesqty/$itemqty;
                        $flag = true;   
                        if($j==0)
                        {
                            $pAmount=$pRange;
                        }
                        else
                        {
                            if($pRange < $pAmount)
                            {
                                $pAmount=$pRange;
                            }
                        }
                       
                    }
                }
            } else {
                $result1 = $SFA_Model_Promotioncalculate->checkrange($data);
            }
        }
        if($storage->validation['Enable_Sales_Promotion'] == 2 || $storage->validation['Enable_Sales_Promotion'] == 3)
        {
            if($this->params['range'] == "3") {
                $pAmount=1;
                for($j=0;$j<count($result[0]);$j++)
                {
                    $returnqty = $result[0][$j]["ReturnQty"];
                    $total_ret_qnt += ($returnqty != "" && $returnqty >0) ? $returnqty : 0;
                    $returnamount = $result[0][$j]["ReturnAmount"];
                    $itemcode = $result[0][$j]["itemcode"];
                    $itemqty = $result[0][$j]["itemqty"];
                    $repeatingrange = $result[0][$j]["onetimeuse"];
                    
                    if($returnqty%$itemqty == 0)
                    {
                        $pRange = $returnqty/$itemqty;
                        $flag = true;   
                        if($j==0)
                        {
                            $pAmount=$pRange;
                        }
                        else
                        {
                            if($pRange < $pAmount)
                            {
                                $pAmount=$pRange;
                            }
                        }
                       
                    }
                }
            } else {
                $result2 = $SFA_Model_Returnpromotioncalculate->checkrange($data);
            }
        }
        
        
        if($this->params['range'] == "3") {
            if(count($result[0]) > 0)
            {
                $newarr = array("range_status"=>"1","promotionamount"=>$pAmount,"repeatingrange"=>$repeatingrange,"sales_qty"=>$total_sales_qnt,"sales_amt"=>0,"return_qty"=>$total_ret_qnt,"return_amt"=>0,"returnpromotionamt"=>0);
            }
            else
            {
                $newarr = array("range_status"=>"0");
            }
        } else {
            if(!empty($result1) || !empty($result2))
            {
                if($this->params['range'] == "4") {
                    
                    $pAmount = $returnpAmount = 0;
                    if($result1[0]["repeatingrange"] == "1"){
                        $pAmount = (int)($result1[0]["amount"] / $result1[0]["rangelow"]);
                    }
                    if($result2[0]["repeatingrange"] == "1"){
                        $returnpAmount = (int)($result2[0]["amount"] / $result2[0]["rangelow"]);
                    }
                    if(!empty($result1[0])){
                        $repeatingrange = $result1[0]["repeatingrange"];
                    }
                    if(!empty($result2[0])){
                        $repeatingrange = $result2[0]["repeatingrange"];
                    }
                    $newarr = array("range_status"=>"1","sales_qty"=>$result[0][0]["SalesQty"],"sales_amt"=>$result[0][0]["SalesAmount"],"return_qty"=>$result[0][0]["ReturnQty"],"return_amt"=>$result[0][0]["ReturnAmount"],"promotionamount"=>$pAmount,"repeatingrange"=>$repeatingrange,"returnpromotionamt"=>$returnpAmount);
                } else {
                    $newarr = array("range_status"=>"1","sales_qty"=>$result[0][0]["SalesQty"],"sales_amt"=>$result[0][0]["SalesAmount"],"return_qty"=>$result[0][0]["ReturnQty"],"return_amt"=>$result[0][0]["ReturnAmount"],"promotionamount"=>0,"repeatingrange"=>0,"returnpromotionamt"=>0);
                }
            }
            else
            {
                $newarr = array("range_status"=>"0");
            }
        }
        
        echo json_encode($newarr);
	    exit;
	   
	}catch (Zend_Exception $e)
		{
			echo "Error message: " . $e->getMessage() . "\n";exit;
		}
    }
    /**
    * @name       generateinvoicenoAction
    * @since      01-02-2012
    * @version    Release: 8
    * @author     GP <gayatri@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This is action generate invoicenumber base on routemaster with routewise
    *
    */ 
  public function generateinvoicenoAction()
  {
    $this->allData    = $this->getRequest()->getParams();
    $this->formData   = $this->_request->getPost();
	
     $new_obj = new SFA_Model_Promotioncalculate();
     $result = $new_obj->generate_invoice_number(array("route_code" =>$this->allData['route_code']));
     
     
    $document_no = ($result[0]['document_seq']+1);
    $invoice_no =  ($result[0]['invoice_seq']+1);
     
    echo $invoice_no." -- ".$document_no;
    exit;
     
  }
   /**
    * @name       checkpromotionreviewAction
    * @since      01-02-2012
    * @version    Release: 8
    * @author     GP <gayatri@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This is action display applied promtoin in Review screen
    * here user can see applied promotion.
    * 
    */   
    
    public function checkpromotionreviewAction()
    {
	$storage = new Zend_Session_Namespace('Add_invoice_data');
	
	$data[1] = $storage->Invoice[0]['visitkey'];
	$data[2] = $storage->Invoice[0]['routekey'];
	$data[3] = $storage->Invoice[0]['invoice_transaction_key'];
	$data[4] = $storage->Invoice['add_invoice'][0]['ddlcustomer'];
	$result[2] =array();
	$result[3] =array();
        $result = $this->SFA_Comman->executequery('CALL sp_get_transaction_invoiceadd_promotion_review(?,?,?,?)',$data,'');
	//pr($result);
	//echo "<pre>";
	//print_r($result[2]);
	//pr($result);
	$this->view->promtoiontype =$result[0];
	$free_item_list =array();
    $freeitem = array();
    $returnfreeitem = array();
	
	//echo '<pre>';
	//print_r($result[2]);
	//exit;
	foreach($result[1] as $value) {
	   
	    $finalarr[$value['promotiontypecode']][$value['promotionplannumber']][] = $value;
	    if($value['promotiontypecode'] == 7) {
            $finalarr[$value['promotiontypecode']][$value['promotionplannumber']][$value['itemtransactiontype']][] = $value;
			
			
        }
        if($value['promotiontypecode'] == 7)
        {
            foreach($result[2] as $vla)
            {  
                if($vla['plan_no'] == $value['promotionplannumber'] && $value['itemtransactiontype'] == $vla['itemtransactiontype'] && $value['itemcode'] == $vla['itemcode1'] )
                {
		    if($vla['rangebasis']==3 || $vla['rangebasis']==4){
			
			   
			    if($value['itemtransactiontype'] == "1") {
				$freeitem[$value['promotionplannumber']] += $value["promotionquantity"];
			    } else {
				$returnfreeitem[$value['promotionplannumber']] += $value["promotionquantity"];
			    }
			
			
		    }
		    else{
			if($value['itemtransactiontype'] == "1") {
				$freeitem[$value['promotionplannumber']] = $value["promotionquantity"];
			} else {
				$returnfreeitem[$value['promotionplannumber']] = $value["promotionquantity"];
			}
		    }
		    
                    $vla["promotionquantity"] = $value["promotionquantity"];
                    $free_item_list[$value['promotionplannumber']][] =$vla;
                }
            }
        }
	}
//	echo '<pre>';
//    print_r($freeitem);
//	exit;
    //pr($finalarr,1);
    $this->view->freeitem = $freeitem;
    $this->view->returnfreeitem = $returnfreeitem;
	$this->view->final_amount =$result[0][0]['total_amount'];
	$this->view->new_amount =$result[0][0]['total_amount'];
	$this->view->final_arr = $finalarr;
	$this->view->previous = 0;
	$this->view->next = 0;
	$this->view->plan_numner ="";
	$this->view->free_item_list  =$free_item_list;
	
	
	if(empty($this->view->final_amount) && empty($this->view->new_amount) && empty($this->view->free_item_list))
	{
	    echo "$::$";
	    exit;
	}
	
    }
   /**
    * @name       generateinvoicenumberAction
    * @since      01-02-2012
    * @version    Release: 8
    * @author     GP <gayatri@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This is action genreate numner and update invoice data and throw url into list page
    * 
    */  
    public function generateinvoicenumberAction()
    {
		$this->allData    = $this->getRequest()->getParams();
		$this->formData   = $this->_request->getPost();
		   
		$storage = new Zend_Session_Namespace('Add_invoice_data');
	   
		$param_array =array();
		$param_array[1] = $storage->Invoice[0]['invoice_transaction_key'];
		$param_array[2] = $storage->Invoice[0]['routekey'];
		$param_array[3] = $storage->Invoice[0]['visitkey'];
		$param_array[4] = $storage->Invoice['add_invoice'][0]['ddlroute'];
		$param_array[5] = $this->allData['GCPaymentType'];
		$param_array[6] = $this->allData['paymenttype'];	
	
	    $result 	= $this->SFA_Comman->executequery('CALL sp_trasfer_transaction_invoiceadd_tempdata(?,?,?,?,?,?)',$param_array,'');
	
	// $new_obj = new SFA_Model_Promotioncalculate();
	 
	// $new_obj->generate_invoice_no($data);
	Zend_Session::namespaceUnset('Add_invoice_data');
	
	exit;
    }
     /**
    * @name       getvalidationAction
    * @since      01-02-2012
    * @version    Release: 8
    * @author     GP <gayatri@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This is action Get all Setting vlaue of invoice transaction Related
    * Base on this settings we can show hide promotion and otherscreen visa versa
    * 
    */  
    public function getvalidationAction()
    {
	    $this->allData    = $this->getRequest()->getParams();
	    $this->formData   = $this->_request->getPost();
	    
	    $storage = new Zend_Session_Namespace('Add_invoice_data');
	    
	    $param_array =array();
	    $param_array[1] =$this->allData['customer_code'];
	    $param_array[2] =$this->decimalplaces;
	    $result 	= $this->SFA_Comman->executequery('CALL sp_get_transaction_invoiceadd_settings(?,?)',$param_array,'');
	    
	    $storage->validation =   array();
	    $storage->validation    =$result[0][0];
	    
	    $storage->settings = $result[0][0];
	    $this->view->result = $result[0][0];
	   
	    
    }
   /**
    * @name       invoicesummeryAction
    * @since      01-02-2012
    * @version    Release: 8
    * @author     GP <gayatri@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This action display invoice summery informationin signature page
    * 
    */  
    public function invoicesummeryAction()
    {
        $this->allData    = $this->getRequest()->getParams();
        $this->formData   = $this->_request->getPost();
        
        $storage = new Zend_Session_Namespace('Add_invoice_data');
        
        $param_array =array();
        $param_array[1] =$storage->Invoice[0]['routekey'];
        $param_array[2] =$storage->Invoice[0]['invoice_transaction_key'];
        
        $result 	= $this->SFA_Comman->executequery('CALL sp_get_transaction_invoiceadd_invoicesummery(?,?)',$param_array,'');
        
        $this->view->result = $result[0];
        $this->view->result1 = $result[1];
        $this->view->result2 = $result[2];
    }
  /**
    * @name       cashcollectionAction
    * @since      01-02-2012
    * @version    Release: 8
    * @author     GP <gayatri@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This action Dispaly Cashcollection entry in cash screen of invoice transaction table 
    * 
    */ 
   public function cashcollectionAction()
    {
	$this->allData    = $this->getRequest()->getParams();
	$this->formData   = $this->_request->getPost();
	    
	$storage = new Zend_Session_Namespace('Add_invoice_data');
	
	$param_array =array();
	$param_array[1] =$storage->Invoice[0]['routekey'];
	$param_array[2] =$storage->Invoice[0]['visitkey'];
	
	
	$result 	= $this->SFA_Comman->executequery('CALL sp_get_transaction_invoiceadd_cashcollection(?,?)',$param_array,'');
	
	$this->view->result = $result[0];
	
    }
    /**
    * @name       getamountnontcitemAction
    * @since      01-02-2012
    * @version    Release: 8
    * @author     GP <gayatri@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This action Calculate minimum Amount pay by user.
    * if user purchase TC item then we calulate only non-Tc item and it's amount and use must have to
    * pay minimum amoun.
    * 
    */
    public function getamountnontcitemAction()
    {
	$this->allData    = $this->getRequest()->getParams();
	$this->formData   = $this->_request->getPost();
	
	$storage = new Zend_Session_Namespace('Add_invoice_data');
	
	$param_array =array();
	$param_array[1] =$storage->Invoice[0]['invoice_transaction_key'];
	$param_array[2] =$storage->Invoice[0]['routekey'];
	$param_array[3] =$this->decimalplaces;
	$result 	= $this->SFA_Comman->executequery('CALL sp_get_transaction_invoiceadd_payamountbyuser(?,?,?)',$param_array,'');
	echo $result[0][0]['mini_amount'];
	exit;
    }
    
    public function availableqtyAction()
    {
	$params =$this->view->params	= $this->getRequest()->getParams();
	$storage 			= new Zend_Session_Namespace('Add_invoice_data');
	
	$param_array[1] = $params['plannumber'];
	$this->_helper->layout->setLayout('popup');
	
	$this->view->closedat ="0";
	
	
	if(isset($params['salesqty']) &&  $params['salesqty'] != ""  &&  isset($params['itemcode']) &&  $params['itemcode'] != "")
	 {
	    
	    $param_array = array();
	    $param_array[1] = $params['itemcode'];
	    $param_array[2] = $storage->Invoice['add_invoice'][0]['ddlcustomer'];
	    $result 	= $this->SFA_Comman->executequery('CALL sp_get_transaction_invoiceadd_available_qty(?,?)',$param_array,'');
	    $this->view->result = $result[0];
	    $this->view->result1 = $result[1];
	    
	    
	 }
	 else
	 {
	    $this->view->closedat ="1";
	 }
  }
  
  
   public function addbatchcancelAction()
    {
	$this->allData    = $this->getRequest()->getParams();
	$this->formData   = $this->_request->getPost();
	
	 $storage 		  = new Zend_Session_Namespace('Add_invoice_data');
	 $storage->Allowitemadd   = 0;
	 exit;
    }
    
   public function addbatchAction()
    {
	$this->allData    = $this->getRequest()->getParams();
	$this->formData   = $this->_request->getPost();
	
	$storage 		  = new Zend_Session_Namespace('Add_invoice_data');
	$storage->Allowitemadd    = 1;
	$routekey =$storage->Invoice[0]['routekey'];
	$visitkey =$storage->Invoice[0]['visitkey'];
	$transactionkey =  $storage->Invoice[0]['invoice_transaction_key'];
	 $SFA_Model_Promotioncalculate = new SFA_Model_Promotioncalculate();
	for($i=0 ; $i<count($this->formData['avail_qty']); $i++)
	{
	   
	    $data['routekey']       = $routekey;
	    $data['batchdetailkey'] = $this->formData['batchdetailkey'][$i];
	    $data['batchnumber']    = $this->formData['batchnumber'][$i] ;
	    $data['itemcode']       = $this->formData['itemcode'][$i];
	    $data['quantity']       = $this->formData['avail_qty'][$i];
	    $data['expirydate']     = $this->formData['expirydate1'][$i];
	    $data['visitkey']       = $visitkey;
	    $data['transactionkey'] = $transactionkey;
	    $SFA_Model_Promotioncalculate->add_batch_entry($data);
	    
	}
	
	 exit;
    }
    public function checkbatchaddedAction()
    {
        $this->allData    = $this->getRequest()->getParams();
        $this->formData   = $this->_request->getPost();
        
        $storage 		  = new Zend_Session_Namespace('Add_invoice_data');
        
        echo $storage->Allowitemadd."$::$".$storage->Rallowitemadd ;
        exit;
    }
    
    public function returnavailableqtyAction()
    {
        $params = $this->view->params = $this->getRequest()->getParams();
        
        $storage = new Zend_Session_Namespace('Add_invoice_data');
        
        $param_array[1] = $params['plannumber'];
        $this->_helper->layout->setLayout('popup');
        
        $this->view->closedat = "0";
        
        $storage->Rallowitemadd = 0;
        $trans_type_code = array("sales" => 15,"freegood" => 16,"promo"=>17,"return"=>18,"buyback"=>19,"damage"=>20,"expirey"=>21,"rental"=>22);
        $this->view->trans_type_val = $trans_type_val = array("sales" => "Sales","freegood" => "Free","promo"=>"Promo","return"=>"Return","buyback"=>"BuyBack","damage"=>"Damage","expirey"=>"Expiry","rental"=>"Rental");
        if(isset($params['salesqty']) && $params['salesqty'] != "" && isset($params['itemcode']) && $params['itemcode'] != "")
        {
            $param_array        = array();
            $param_array[1]     = $params['itemcode'];
            $param_array[2]     = $storage->Invoice['add_invoice'][0]['ddlcustomer'];
            $param_array[3]     = $trans_type_code[$params['type']];
            $param_array[4]     = $storage->Invoice[0]['visitkey'];
            $param_array[5]     = $storage->Invoice[0]['routekey'];
            
            $result 	        = $this->SFA_Comman->executequery('CALL sp_get_transaction_invoiceadd_returnavailable_qty(?,?,?,?,?)',$param_array,'');
            
            $this->view->result = $result[0];
            $this->view->result1= $result[1];
            $this->view->type   = $trans_type_code[$params['type']];
            $this->view->allowbatchentry   = $result[2][0]["allowbatchentry"];
        }
        else
        {
            $this->view->closedat ="1";
        }
    }
    
    public function returnaddbatchcancelAction()
    {
	
	$this->allData    = $this->getRequest()->getParams();
	$this->formData   = $this->_request->getPost();
	
	 $storage 		  = new Zend_Session_Namespace('Add_invoice_data');
	 $storage->Rallowitemadd   = 0;
	 exit;
    }
    
     public function returnaddbatchAction()
    {
	
	$this->allData    = $this->getRequest()->getParams();
	$this->formData   = $this->_request->getPost();
	
	//print_r($this->formData );
	//exit;
	$storage 		  = new Zend_Session_Namespace('Add_invoice_data');
	$storage->Rallowitemadd    = 1;
	$routekey =$storage->Invoice[0]['routekey'];
	$visitkey =$storage->Invoice[0]['visitkey'];
	$transactionkey =  $storage->Invoice[0]['invoice_transaction_key'];
	
    $SFA_Model_Promotioncalculate = new SFA_Model_Promotioncalculate();
    //pr($this->formData,1);
	if(!empty($this->formData['case'])){
	    for($i=0 ; $i<count($this->formData['case']); $i++)
	    {
		
            $data['routekey']       = $routekey;
            $data['batchdetailkey'] = $this->formData['batchdetailkey'][$i];
            $data['batchnumber']    = $this->formData['batchnumber'][$i] ;
            $data['itemcode']       = $this->formData['itemcode'][$i];
            $data['quantity']       = (($this->formData['case'][$i] * $this->formData['units'])+ $this->formData['pcs'][$i]);
            $data['expirydate']     = $this->formData['expirydate1'][$i];
            $data['visitkey']       = $visitkey;
            $data['transactionkey'] = $transactionkey;
            $data['trans_type_code']= $this->formData['type'];
            
            if($this->formData['type_val'] == "freegood") {
                $typeval = "free";
            } else if($this->formData['type_val'] == "expirey") {
                $typeval = "expiry";
            } else {
                $typeval = $this->formData['type_val'];
            }
            $data['trans_type_val'] = $typeval;
            
            $SFA_Model_Promotioncalculate->return_add_batch_entry($data);
	    }
	}
	
	if(!empty($this->formData['case1'])){
	    for($i=0 ; $i<count($this->formData['case1']); $i++)
	    {
            list($day,$month,$year) = explode("-",$this->formData['txt_delivery_date1'][$i]);
            $data['routekey']       = $routekey;
            $data['batchdetailkey'] = '';
            $data['batchnumber']    = $this->formData['batchnumber1'][$i] ;
            $data['itemcode']       = $this->formData['item_code'];
            $data['quantity']       = (($this->formData['case1'][$i] * $this->formData['units'])+ $this->formData['pcs1'][$i]);
            $data['expirydate']     = $year."-".$month."-".$day;
            $data['visitkey']       = $visitkey;
            $data['transactionkey'] = $transactionkey;
            $data['trans_type_code']= $this->formData['type'];
            if($this->formData['type_val'] == "freegood")
                $typeval = "free";
            elseif($this->formData['type_val'] == "expirey")
                $typeval = "expiry";
            else
                $typeval = $this->formData['type_val'];
                
            $data['trans_type_val'] = $typeval;
            $SFA_Model_Promotioncalculate->return_add_batch_entry_manual($data);
	    }
	}
	//return_add_batch_entry_manual
	
	 exit;
    }
    
    /**
    * @name       onblurreturnavailableqtyAction
    * @since      25-10-2012
    * @version    Release: 8
    * @author     PT <pankil@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This action is to insert or update in batchmasterdetail_temp table
    *
    */
    public function onblurreturnavailableqtyAction()
    {
        $params =$this->view->params	= $this->getRequest()->getParams();
        $storage 			= new Zend_Session_Namespace('Add_invoice_data');
        $trans_type_code = array("sales" => 15,"freegood" => 16,"rental" => 22,"promo" => 17);
       
        if(isset($params['qty']) &&  $params['qty'] != ""  &&  isset($params['itemcode']) &&  $params['itemcode'] != "")
        {
            $param_array    = array();
            $param_array[1] = $params['itemcode'];
            $param_array[2] = $storage->Invoice['add_invoice'][0]['ddlcustomer'];
            $param_array[3] = $trans_type_code[$params['type']];
            $param_array[4] = $storage->Invoice[0]['visitkey'];
            $param_array[5]     = $storage->Invoice[0]['routekey'];
            $result 	    = $this->SFA_Comman->executequery('CALL sp_get_transaction_invoiceadd_returnavailable_qty(?,?,?,?,?)',$param_array,'');
            $SFA_Model_Promotioncalculate = new SFA_Model_Promotioncalculate();
            $visitkey = $storage->Invoice[0]['visitkey'];
            $routekey = $storage->Invoice[0]['routekey'];
            $transactionkey =  $storage->Invoice[0]['invoice_transaction_key'];
            $itemcode = $params['itemcode'];
            $qnt = $params['qty'];
            $trans_type_code_val = $trans_type_code[$params['type']];
            $updated_qnt = 0;
            
            if(count($result[0]) > 0) {
                // delete code -- routekey, visitkey, itemcode
                $SFA_Model_Promotioncalculate->delete_batch_entry(array("routekey"=>$routekey,"visitkey"=>$visitkey,"itemcode"=>$itemcode,"transactiontypecode"=>$trans_type_code_val));
            }
            //pr($result[0],1);
            for($i=0;$i<count($result[0]);$i++)
            {
                $batchqnt = $result[0][$i]["quantity"];
                if($qnt > $batchqnt){
                    $updated_qnt = $qnt;
                    $qnt = $result[0][$i]["quantity"];
                }
                
                $data['routekey']           = $routekey;
                $data['batchdetailkey']     = $result[0][$i]["batchdetailkey"];
                $data['batchnumber']        = $result[0][$i]["batchnumber"];
                $data['itemcode']           = $itemcode;
                $data['quantity']           = $qnt;
                $data['transactiontypecode']= $trans_type_code_val;
                $data['expirydate']         = $result[0][$i]['expirydate1'];
                $data['visitkey']           = $visitkey;
                $data['transactionkey']     = $transactionkey;
                
                // insert code
                $SFA_Model_Promotioncalculate->blur_add_batch_entry($data);
                
                $qnt = $updated_qnt;
                if($qnt > $batchqnt){
                    $qnt -= $result[0][$i]["quantity"];
                } else {
                    break;
                }
            }
            
            $qnt = $params['qty'];
            $updated_qnt = 0;
            
            for($i=0;$i<count($result[0]);$i++)
            {
                $batchqnt = $result[0][$i]["quantity"];
                if($qnt > $batchqnt){
                    $updated_qnt = $qnt;
                    $qnt = $result[0][$i]["quantity"];
                }
                
                $data['batchdetailkey']     = $result[0][$i]["batchdetailkey"];
                $data['batchnumber']        = $result[0][$i]["batchnumber"];
                $data['itemcode']           = $itemcode;
                $data['quantity']           = $result[0][$i]["quantity"];
                $data['expirydate']         = $result[0][$i]['expirydate1'];
                $data['itemcode']           = $itemcode;
                $data['type']               = ($params['type'] == "freegood") ? "free" : $params['type'];
                $data['qnt']                = ($qnt != "" && $qnt > 0)?$qnt:0;
                $data['visitkey']           = $visitkey;
                $data['routekey']           = $routekey;
                // insert code
                $SFA_Model_Promotioncalculate->insert_batchmaster_temp($data);
                
                $qnt = $updated_qnt;
                if($qnt > $batchqnt){
                    $qnt -= $result[0][$i]["quantity"];
                } else {
                    break;
                }
            }
            exit;
        }
        else
        {
            $this->view->closedat ="1";exit;
        }
    }
    
     /**
    * @name       checkbatchAction
    * @since      29-10-2012
    * @version    Release: 8
    * @author     PT <pankil@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This action is to check whether the batch is already exist or not
    *
    */
    public function checkbatchAction()
    {
        $params = $this->getRequest()->getParams();
        for($i=0;$i<count($params["newvbatcharr"]);$i++)
        {
            $batches = "'".implode("','",$params["newvbatcharr"])."'";
        }
        $SFA_Model_Promotioncalculate = new SFA_Model_Promotioncalculate();
        $result = $SFA_Model_Promotioncalculate->checkbatch($batches);
        if($result[0]['cnt'] > 0)
        {
            echo "exist";
        }
        exit;
    }
    
    /**
    * @name       addpromotionvalAction
    * @since      29-10-2012
    * @version    Release: 8
    * @author     PT <pankil@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This action is to add the promotion val
    *
    */
    public function addpromotionvalAction()
    {
        $params =$this->view->params	= $this->getRequest()->getParams();
        $storage 			= new Zend_Session_Namespace('Add_invoice_data');
        $trans_type_code = array("promotion" => 15);
       
        if(isset($params['qty']) &&  $params['qty'] != ""  &&  isset($params['itemcode']) &&  $params['itemcode'] != "")
        {
            $param_array    = array();
            $param_array[1] = $params['itemcode'];
            $param_array[2] = $storage->Invoice['add_invoice'][0]['ddlcustomer'];
            $param_array[3] = $trans_type_code[$params['type']];
            $param_array[4] = $storage->Invoice[0]['visitkey'];
            $param_array[5]     = $storage->Invoice[0]['routekey'];
            $result 	    = $this->SFA_Comman->executequery('CALL sp_get_transaction_invoiceadd_returnavailable_qty(?,?,?,?,?)',$param_array,'');
        }
    }
    
    /**
    * @name       checkcreditlimit
    * @since      29-10-2012
    * @version    Release: 8
    * @author     PT <pankil@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This action is to check credit limit is exceed or not
    *
    */
    public function checkcreditlimitAction()
    {
        $params = $this->view->params	= $this->getRequest()->getParams();
        $storage 			= new Zend_Session_Namespace('Add_invoice_data');
        
        $param_array    = array();
        $param_array[1] = $params['customerbal'];
        $param_array[2] = $storage->Invoice['add_invoice'][0]['ddlcustomer'];
        
        $result = $this->SFA_Comman->executequery('CALL sp_check_transaction_customer_creditlimit(?,?)',$param_array,'');
        echo (isset($result[0][0]["status_creditlimit"]) && $result[0][0]["status_creditlimit"] != "") ? $result[0][0]["status_creditlimit"] : 1;
        exit;
    }
    
    
    /**
    * @name       onblurnewreturnavailableqty
    * @since      29-10-2012
    * @version    Release: 8
    * @author     PT <pankil@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This action is to add return damage expiry and buyback onfly if setting of batch is 0
    *
    */
    public function onblurnewreturnavailableqtyAction()
    {
        $params =$this->view->params	= $this->getRequest()->getParams();
        $storage 			= new Zend_Session_Namespace('Add_invoice_data');
        $trans_type_code = array("sales" => 15,"freegood" => 16,"rental" => 22,"promo" => 17,"return"=>18,"buyback"=>19,"damage"=>20,"expirey"=>21);
       
        if(isset($params['qty']) &&  $params['qty'] != ""  &&  isset($params['itemcode']) &&  $params['itemcode'] != "")
        {
            $param_array    = array();
            $param_array[1] = $params['itemcode'];
            $param_array[2] = $storage->Invoice['add_invoice'][0]['ddlcustomer'];
            $param_array[3] = $trans_type_code[$params['type']];
            $param_array[4] = $storage->Invoice[0]['visitkey'];
            $param_array[5]     = $storage->Invoice[0]['routekey'];
            
            $result 	    = $this->SFA_Comman->executequery('CALL sp_get_transaction_invoiceadd_returnavailable_qty(?,?,?,?,?)',$param_array,'');
            $SFA_Model_Promotioncalculate = new SFA_Model_Promotioncalculate();
            
            $visitkey = $storage->Invoice[0]['visitkey'];
            $routekey = $storage->Invoice[0]['routekey'];
            $transactionkey =  $storage->Invoice[0]['invoice_transaction_key'];
            $itemcode = $params['itemcode'];
            $qnt = $params['qty'];
            
            $trans_type_code_val = $trans_type_code[$params['type']];
            $updated_qnt = 0;
            
            if(count($result[0]) > 0) {
                // delete code -- routekey, visitkey, itemcode
                $SFA_Model_Promotioncalculate->delete_batch_entry(array("routekey"=>$routekey,"visitkey"=>$visitkey,"itemcode"=>$itemcode,"transactiontypecode"=>$trans_type_code_val));
            
                //pr($result[0],1);
                for($i=0;$i<count($result[0]);$i++)
                {
                    //$batchqnt = $result[0][$i]["quantity"];
                    //if($qnt > $batchqnt){
                    //    $updated_qnt = $qnt;
                    //    $qnt = $result[0][$i]["quantity"];
                    //}
                    
                    $data['routekey']           = $routekey;
                    $data['batchdetailkey']     = $result[0][$i]["batchdetailkey"];
                    $data['batchnumber']        = $result[0][$i]["batchnumber"];
                    $data['itemcode']           = $itemcode;
                    $data['quantity']           = $qnt;
                    $data['transactiontypecode']= $trans_type_code_val;
                    $data['expirydate']         = $result[0][$i]['expirydate1'];
                    $data['visitkey']           = $visitkey;
                    $data['transactionkey']     = $transactionkey;
                    
                    // insert code
                    $SFA_Model_Promotioncalculate->blur_add_batch_entry($data);
                    
                    //$qnt = $updated_qnt;
                    //if($qnt > $batchqnt){
                    //    $qnt -= $result[0][$i]["quantity"];
                    //} else {
                    //    break;
                    //}
                }
                
                $qnt = $params['qty'];
                $updated_qnt = 0;
                
                for($i=0;$i<count($result[0]);$i++)
                {
                    //$batchqnt = $result[0][$i]["quantity"];
                    //if($qnt > $batchqnt){
                    //    $updated_qnt = $qnt;
                    //    $qnt = $result[0][$i]["quantity"];
                    //}
                    
                    $data['batchdetailkey']     = $result[0][$i]["batchdetailkey"];
                    $data['batchnumber']        = $result[0][$i]["batchnumber"];
                    $data['itemcode']           = $itemcode;
                    $data['quantity']           = $result[0][$i]["quantity"];
                    $data['expirydate']         = $result[0][$i]['expirydate1'];
                    $data['itemcode']           = $itemcode;
                    $data['type']               = ($params['type'] == "freegood") ? "free" : ($params['type'] == "expirey") ? "expiry": $params['type'];
                    $data['qnt']                = ($qnt != "" && $qnt > 0)?$qnt:0;
                    $data['visitkey']           = $visitkey;
                    $data['routekey']           = $routekey;
                    // insert code
                    $SFA_Model_Promotioncalculate->insert_batchmaster_temp($data);
                    
                    //$qnt = $updated_qnt;
                    //if($qnt > $batchqnt){
                    //    $qnt -= $result[0][$i]["quantity"];
                    //} else {
                    //    break;
                    //}
                }
            } else {
                $data["routekey"] = $routekey;
                $data["itemcode"] = $itemcode;
                $data["transactionkey"] = $transactionkey;
                $data["quantity"] = $qnt;
                $data["visitkey"] = $visitkey;
                $data["trans_type_code"] = $trans_type_code_val;
                $data["trans_type_val"] = $params['type'];
                $SFA_Model_Promotioncalculate->create_newbatch($data);
            }
            exit;
        }
        else
        {
            $this->view->closedat ="1";exit;
        }
    }
    
     /**
    * @name       deletetempdata
    * @since      18-01-2013
    * @version    Release: 9
    * @author     PT <pankil@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This action is to delete the temp data if previous button pressed
    *
    */
    public function deletetempdataAction()
    {
        $storage 			= new Zend_Session_Namespace('Add_invoice_data');
        
        $param_array    = array();
        $param_array[1] = $storage->Invoice[0]['visitkey'];
        $param_array[2] = $storage->Invoice[0]['routekey'];
        $result = $this->SFA_Comman->executequery('CALL sp_delete_transaction_tempdata(?,?)',$param_array,'');
        unset($storage->Invoice[0]);
        //Zend_Session::namespaceUnset('Add_invoice_data');
        exit;
    }
}