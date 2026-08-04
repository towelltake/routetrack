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


class Hhctransaction_OrderajaxdataController extends Hhctransaction_Library_Controller_Action_Abstract
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
    * This action Load  customer combobox
    *
    */
    
    public function customerlistAction()
    {
	try
	{
	    $this->view->params   = $params     = $this->getRequest()->getParams();
	    $this->view->formdata = $formdata   = $this->_request->getPost();
	    
	   
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
		$result = $this->SFA_Comman->executequery('CALL sp_check_isroute_start(?)',$params['routeid'],'');
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
    * This is action collect item price base on customerwise
    *
    */
    
    public function itempriceinfoAction()
    {
	try
	{
	    $this->view->params = $params = $this->getRequest()->getParams();
	    $this->view->formdata = $formdata = $this->_request->getPost();
        $storage 		= new Zend_Session_Namespace('Add_Sales_Order');
        $SFA_Model_Batch = new SFA_Model_Batch();
        $result = $SFA_Model_Batch->check_salesorder_item_exist(array("itemcode"=>$this->view->params['item_id'],"visitkey"=>$storage->Invoice[0]['visitkey']));
        
        if($result[0]['cnt'] > 0)
        {
            echo "exist";
        }
        else
        {
            $param_array =array();
            $param_array[1] = $this->view->params['item_id'];
            $param_array[2] = $this->view->params['customer_code'];
            $param_array[3] = $this->decimalplaces;
            $result 	= $this->SFA_Comman->executequery('CALL sp_get_transaction_salesorderadd_itemprice(?,?,?)',$param_array,'');
            $result_Arr =$result[0][0];
            echo implode("$::$",$result_Arr);
        }
	    exit;
	    
		    
	}catch (Zend_Exception $e)
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
    * This is action check/verify only promotion and promotion list
    *
    */
    public function checkpromotionAction()
    {
	try
	{
	    $this->params = $params = $this->getRequest()->getParams();
	    $this->formdata = $formdata = $this->_request->getPost();
	    
	    $storage 		= new Zend_Session_Namespace('Add_Sales_Order');
	    
	    $param_array =array();
	    $param_array1[1] = $param_array[1] =$storage->Invoice[0]['routekey'];
	    $param_array1[2] = $param_array[2] =$storage->Invoice[0]['visitkey'];
	    $param_array[3] =$this->params['in_customercode'];
	    $param_array[4] =$this->params['assignment_no'];
	    $param_array[5] =$this->params['plan_no'];
	    $param_array[6] =$this->params['range'];
	    $param_array[7] =$this->params['in_promotiontypecode'];
	    $param_array[8] = $storage->Invoice[0]['invoice_transaction_key'];
	    
	    $param_array1[3] =  $this->params['qualification_group_no'];
	    
	    $param_array2[1] = $this->params['assignment_no'];
        $param_array2[2] = $this->params['in_customercode'];
	    $param_array2[3] = $storage->Invoice[0]['routekey'];
        $param_array2[4] = $storage->Invoice[0]['visitkey'];
        $param_array2[5] = $this->params['plan_no'];
        
        if($this->params['range'] == 3) {
            $result 	= $this->SFA_Comman->executequery('CALL sp_check_ordertransaction_salesorderadd_promotionqualified_fixed(?,?,?,?,?)',$param_array2,'');
        } else {
            $result 	= $this->SFA_Comman->executequery('CALL sp_check_ordertransaction_salesorderadd_promotionqualified(?,?,?)',$param_array1,'');
        }
	    
	    
        $data = array("assignment_no"=>$this->params['assignment_no'],
                      "SalesQty"=> $result[0][0]["SalesQty"],
                      "SalesAmount"=> $result[0][0]["SalesAmount"],
                      "range"=>$this->params['range']
                      );
        $total_sales_qnt = 0;
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
            $SFA_Model_Orderpromotioncalculate = new SFA_Model_Orderpromotioncalculate();
            $result1 = $SFA_Model_Orderpromotioncalculate->checkrange($data);
        }
        
        
        if($this->params['range'] == "3") {
            if(count($result[0]) > 0)
            {
                $newarr = array("range_status"=>"1","promotionamount"=>$pAmount,"repeatingrange"=>$repeatingrange,"sales_qty"=>$total_sales_qnt,"sales_amt"=>0,"returnpromotionamt"=>0);
            }
            else
            {
                $newarr = array("range_status"=>"0");
            }
        } else {
            if(!empty($result1))
            {
                if($this->params['range'] == "4") {
                    $pAmount = $returnpAmount = 0;
                    if($result1[0]["repeatingrange"] == "1"){
                        $pAmount = (int)($result1[0]["amount"] / $result1[0]["rangelow"]);
                    }
                    if(!empty($result1[0])){
                        $repeatingrange = $result1[0]["repeatingrange"];
                    }
                    $newarr = array("range_status"=>"1","sales_qty"=>$result[0][0]["SalesQty"],"sales_amt"=>$result[0][0]["SalesAmount"],"promotionamount"=>$pAmount,"repeatingrange"=>$repeatingrange);
                } else {
                    $newarr = array("range_status"=>"1","sales_qty"=>$result[0][0]["SalesQty"],"sales_amt"=>$result[0][0]["SalesAmount"],"promotionamount"=>0,"repeatingrange"=>0);
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
			echo "Error message: " . $e->getMessage() . "\n";
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
    * This is action generate invoicenumber
    *
    */ 
  public function generateinvoicenoAction()
  {
    $this->allData    = $this->getRequest()->getParams();
    $this->formData   = $this->_request->getPost();
	
     $new_obj = new SFA_Model_Orderpromotioncalculate();
     $result = $new_obj->generate_invoice_number(array("route_code" =>$this->allData['route_code']));
     
     $tomorrow  = date("d-m-Y", mktime(0, 0, 0, date("m")  , date("d")+$result[0]['days'], date("Y")));
     
     $document_no = ($result[0]['document_seq']+1);
     $order_no    = ($result[0]['order_seq']+1);

     
    echo $order_no." -- ".$document_no." -- ".$tomorrow;

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
    * This is action check applied promtoin review
    * 
    */   
    
    public function checkpromotionreviewAction()
    {
	$storage 		= new Zend_Session_Namespace('Add_Sales_Order');
	
	$data[1] = $storage->Invoice[0]['visitkey'];
	$data[2] = $storage->Invoice[0]['routekey'];
	$data[3] = $storage->Invoice[0]['invoice_transaction_key'];
	$data[4] = $storage->Invoice['add_invoice'][0]['ddlcustomer'];
	
	
        $result 	= $this->SFA_Comman->executequery('CALL sp_get_ordertransaction_salesorderadd_promotion_review(?,?,?,?)',$data,'');
	   
	$this->view->promtoiontype =$result[0];
	//$this->view->free_item_list =$result[2];
	 $free_item_list =array();
     $freeitem = array();
    // echo '<pre>';
	//print_r($result[1]);
	//exit;
	foreach($result[1] as $value)
	{
	    
        $finalarr[$value['promotiontypecode']][$value['promotionplannumber']][] = $value;
        if($value['promotiontypecode'] == 7) {
			
            
            $item_arr1 =array();$item_arr =array();
            $item_arr1 = explode(",",$value['itemcode']);
            $item_arr = array_unique($item_arr1);
            
            //print_r($itmeval);
            foreach($item_arr as $itmeval) {
                foreach($result[2] as $vla) {
                    //if($vla['itemcode'] == $itmeval )
		if($vla['promotionplannumber'] == $value['promotionplannumber'] && $value['itemcode'] == $vla['itemcode'] ) {
						if($vla['rangebasis']==3 || $vla['rangebasis']==4){
						$freeitem[$value['promotionplannumber']] += $value["promotionquantity"];
						}
						else
						{
						    $freeitem[$value['promotionplannumber']] = $value["promotionquantity"];
						}
                        $vla["promotionquantity"] = $value["promotionquantity"];
                        $free_item_list[$value['promotionplannumber']][] =$vla;
                    }                   
                }
            }
        }
	}
	//echo '<pre>';
	//print_r($freeitem);
	//exit;
    
    $this->view->freeitem = $freeitem;
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
    * @name       checkpromotionreviewAction
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
	    
	$storage = new Zend_Session_Namespace('Add_Sales_Order');
	
	
	$param_array =array();
	$param_array[1] = $storage->Invoice[0]['invoice_transaction_key'];
	$param_array[2] = $storage->Invoice[0]['routekey'];
	$param_array[3] = $storage->Invoice[0]['visitkey'];
	$param_array[4] = $storage->Invoice['add_invoice'][0]['ddlroute'];
	
	
	print_r($param_array);
	    $result 	= $this->SFA_Comman->executequery('CALL sp_trasfer_ordertransaction_salesorderadd_tempdata(?,?,?,?)',$param_array,'');
	
	 //$new_obj = new SFA_Model_Orderpromotioncalculate();
	 //
	 //$new_obj->generate_invoice_no($data);
	Zend_Session::namespaceUnset('Add_Sales_Order');
	
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
	    
	    $storage = new Zend_Session_Namespace('Add_Sales_Order');
	    
	    $param_array =array();
	    $param_array[1] =$this->allData['customer_code'];
	    $result 	= $this->SFA_Comman->executequery('CALL sp_get_ordertransaction_salesorderadd_settings(?)',$param_array,'');
	    
	    $storage->validation =   array();
	    $storage->validation    =$result[0][0];
	   
	   
	    $storage->settings  = $result[0][0];
	    $this->view->result = $result[0][0];
	   
	  // print_r($storage->validation);exit;
	   
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
	    
	$storage = new Zend_Session_Namespace('Add_Sales_Order');
	
	$param_array =array();
	$param_array[1] =$storage->Invoice[0]['routekey'];
	$param_array[2] =$storage->Invoice[0]['invoice_transaction_key'];
	
	
	$result 	= $this->SFA_Comman->executequery('CALL sp_get_ordertransaction_salesorderadd_invoicesummery(?,?)',$param_array,'');
	
	$this->view->result  = $result[0];
	$this->view->result1 = $result[1];
	
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
        $storage 		= new Zend_Session_Namespace('Add_Sales_Order');
        $param_array    = array();
        $param_array[1] = $storage->Invoice[0]['visitkey'];
        $param_array[2] = $storage->Invoice[0]['routekey'];
        $result = $this->SFA_Comman->executequery('CALL sp_delete_salesorder_tempdata(?,?)',$param_array,'');
        unset($storage->Invoice[0]);
        //Zend_Session::namespaceUnset('Add_Sales_Order');
        exit;
   }
 
}