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


class Hhctransaction_OrdertransactionsaveController extends Hhctransaction_Library_Controller_Action_Abstract
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
    * @name       init
    * @since      01-02-2012
    * @version    Release: 8
    * @author     GP <gayatri@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This is action save invoice data
    *
    */
    
    public function saveinvoiceAction()
    {
	try
	{
	    //get request parameter and form post data
	    $this->allData    = $this->getRequest()->getParams();
	    $this->formData   = $this->_request->getPost();
	   
	  
	    //session  process start
	    $storage 		= new Zend_Session_Namespace('Add_Sales_Order');
	    //for first time session array is blank
		
	    if(!isset($storage->Invoice))
	    {
		    $storage->Invoice =array();
	    }
	  
	   
	   if(isset($this->formData['hdndata']) && $this->formData['hdndata'] != "")
	   {
	   
		if(strtolower($this->formData['hdndata']) == "salesorderadd")
		{
		    $check_entry_value = $storage->Invoice['0']['invoice_transaction_key'];
		
		    $field_list = array("txt_order_no","txt_doc_no","ddlroute","ddlcustomer","txt_order_comment","txt_lpo_number","txt_delivery_date1");
	      
		    $param_array =array();
		    for($i=0;$i<count($field_list);$i++)
		    {
			$param_array[$i+1] =$this->formData[$field_list[$i]];
		    }
		     $sig = $_POST['output'];
		     $sig = filter_input(INPUT_POST, 'output', FILTER_UNSAFE_RAW);
		     
		    $param_array[$i+1] = $sig;
		    if(!$check_entry_value)
		    {
			
			$result 		= $this->SFA_Comman->executequery('CALL sp_add_ordertransaction_salesorderadd_detail1(?,?,?,?,?,?,?,?)',$param_array,'');
		    	$storage->Invoice       = array_merge($result[0],array("add_invoice"=>array($this->formData)));
			
		    }
		    else
		    {
			
			$param_array[$i+2]=$storage->Invoice[0]['invoice_transaction_key'];
			$result 		= $this->SFA_Comman->executequery('CALL sp_edit_ordertransaction_salesorderadd_detail1(?,?,?,?,?,?,?,?,?)',$param_array,'');
			$storage->Invoice =   array();
			$storage->Invoice    = array_merge($result[0],array("add_invoice"=>array($this->formData)));
			
		    }
		  
	   
		}
	    }
	    
	  
	    exit;
		    
	}catch (Zend_Exception $e)
		{
			echo "Error message: " . $e->getMessage() . "\n";
		}
    }
    
     /**
    * @name       init
    * @since      01-02-2012
    * @version    Release: 8
    * @author     GP <gayatri@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This is action save invoice item  data
    *
    */
    
    public function saveinvoiceitemAction()
    {
	 //get request parameter and form post data
	    $this->allData    = $this->getRequest()->getParams();
	    $this->formData   = $this->_request->getPost();
	   
	    $storage 		= new Zend_Session_Namespace('Add_Sales_Order');
	     
	  if(strtolower($this->formData['hdndata']) == "add_order_item")
	  {
		    $param_array =array();
		       
		    $param_array[1] 	= $storage->Invoice[0]['invoice_transaction_key'];
		    $param_array[2] 	= $storage->Invoice[0]['routekey'];
		    $param_array[3] 	= $storage->Invoice[0]['visitkey'];
		    $param_array[4] 	= $this->formData['ddlitem'] ;
		    
		    //$param_array[5] 	= $this->formData['txt_sale_price'];
		    //$param_array[6] 	= $this->formData['txt_return_price'];
		    //$param_array[7] 	= $this->formData['txt_sales_pcs_price'];
		    //$param_array[8] 	= $this->formData['txt_return_pcs_price'];
		    //$param_array[9] 	= $this->formData['txt_freegood_price'];
		    //$param_array[10] 	= $this->formData['txt_freegood_pcs_price'];
		    
		    $param_array[5] = $this->formData['txt_sale_price'];
		    $param_array[6] = $this->formData['txt_sales_pcs_price'];
		    $param_array[7] = $this->formData['txt_freegood_price'];
		    $param_array[8] = $this->formData['txt_freegood_pcs_price'];
		    $param_array[9] = $this->formData['txt_return_price'];
		    $param_array[10] = $this->formData['txt_return_pcs_price'];
		    
		    
		    $param_array[11] = $salesqty       = (($this->formData['txt_sales_case']* $this->formData['txt_upc'])+$this->formData['txt_sales_pieces']);
		    $param_array[12] = $returnqty      = (($this->formData['txt_return_cases']* $this->formData['txt_upc'])+$this->formData['txt_return_pieces']);
		    $param_array[13] = $damagedqty     = (($this->formData['txt_damage_cases']* $this->formData['txt_upc'])+$this->formData['txt_damage_pieces']);
		    $param_array[14] = $manualfreeqty  = (($this->formData['txt_freegood_case']* $this->formData['txt_upc'])+$this->formData['txt_freegood_pieces']);
	            $param_array[15] = $this->formData['txt_upc'];
		    
		    $result 	= $this->SFA_Comman->executequery('CALL sp_add_ordertransaction_salesorderadd_detail2(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',$param_array,'');
	  }
	    echo $storage->Invoice[0]['invoice_transaction_key'];
	  exit;
	  
    }
    /**
    * @name       promotionsaveAction
    * @since      01-02-2012
    * @version    Release: 8
    * @author     GP <gayatri@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This is action save promotion data
    *
    */
    public function promotionsaveAction()
    {
	$this->allData    = $this->getRequest()->getParams();
	$this->formData   = $this->_request->getPost();
	
	$storage 		= new Zend_Session_Namespace('Add_Sales_Order');
	
	$getvalue =array();
	$qualificationgroup =array();
	$assignmentgroup =array();
	$plannumber =array();
	$rangebasis =array();
	$primary_key =array();
	$assignmentno =array();
	$promotiontypecode =array();
	if(isset($this->formData['allow_promotion']))
	{
	    for($i=0;$i<count($this->formData['allow_promotion']); $i++)
	    {
            if($this->formData['allow_promotion'][$i]== 1)
            {
                $getvalue[] = $this->formData['allow_promotion_val'][$i];
                $explode_arr =array();
                $explode_arr = explode("_",$this->formData['allow_promotion_val'][$i]);
                $qualificationgroup[]	= $explode_arr[0];
                $assignmentno[]		= $explode_arr[1];
                $assignmentgroup[]		= $explode_arr[2];
                $plannumber[]		= $explode_arr[3];
                $rangebasis[]		= $explode_arr[4];
                $primary_key[]		= $explode_arr[5];
                $promotiontypecode[] 	= $explode_arr[6];
                
                //if($this->formData['promotion_sales_qty'][$i] != 0)
                //{
                    $promotion_sales_qty[] = $this->formData['promotion_sales_qty'][$i];
                //}
                //if($this->formData['promotion_sales_amt'][$i] != 0)
                //{
                    $promotion_sales_amt[] = $this->formData['promotion_sales_amt'][$i];
                //}
                if( $explode_arr[4] == "3") {
                    if(isset($this->formData['promotion_fixed_amt'][$i]))
                    {
                        $promotion_fixed_amt[] = $this->formData['promotion_fixed_amt'][$i];
                    }
                    if(isset($this->formData['repeatingrange_val'][$i]))
                    {
                        $repeatingrange_val[] = $this->formData['repeatingrange_val'][$i];
                    }
                    $return_promotion_amt[] = '';
                } elseif( $explode_arr[4] == "4") {
                    if(isset($this->formData['promotion_fixed_amt'][$i]))
                    {
                        $promotion_fixed_amt[] = $this->formData['promotion_fixed_amt'][$i];
                    }
                    if(isset($this->formData['repeatingrange_val'][$i]))
                    {
                        $repeatingrange_val[] = $this->formData['repeatingrange_val'][$i];
                    }
                    if(isset($this->formData['return_promotion_amt'][$i]))
                    {
                        $return_promotion_amt[] = $this->formData['return_promotion_amt'][$i];
                    }
                } else {
                    $promotion_fixed_amt[] = $repeatingrange_val[] = $return_promotion_amt[] = '';
                }
            }
	    }
	}
	//echo "<pre>";
	//print_r($qualificationgroup);
	//print_r($promotiontypecode);
	//exit;
	$new_obj = new SFA_Model_Orderpromotioncalculate();
	$all_obj = new SFA_Model_Orderallitempromotion();
	
	 $data =array(
		    'customer_code'=>$this->formData['in_customercode'],
		    'visit_key'=>$storage->Invoice[0]['visitkey'],
		    'route_key'=>$storage->Invoice[0]['routekey'],
		    'transactionkey' =>$storage->Invoice[0]['invoice_transaction_key']
		    );
	$new_obj->remove_promotion($data);
	if(!empty($getvalue))
	{
	    //print_r($getvalue);
	    //print_r($promotiontypecode);
	   
	 
	  
	   if(in_array("0",$promotiontypecode))
	    {
		 $key_id = array_keys($promotiontypecode, "0");
		 for($i=0;$i<count($key_id);$i++)
		    {
			$explode_arr= explode("_",$getvalue[$key_id[$i]]);
		    
			$data =array(
				  'plannumber'=>$plannumber[$key_id[$i]],
				  'customer_code'=>$this->formData['in_customercode'],
				  'assignment_no'=>$assignmentno[$key_id[$i]],
				 'visit_key'=>$storage->Invoice[0]['visitkey'],
			'route_key'=>$storage->Invoice[0]['routekey'],
				  'ragebasis' =>$rangebasis[$key_id[$i]],
				  'transactionkey' =>$storage->Invoice[0]['invoice_transaction_key'],
				  'qualification_group'=>$qualificationgroup[$key_id[$i]],
                  'sales_qnt_org' => $promotion_sales_qty[$key_id[$i]],
                'sales_amt_org' => $promotion_sales_amt[$key_id[$i]]
				  );
			
		      if($qualificationgroup[$key_id[$i]] == "1")
		      	$result =  $all_obj->net_promotion($data);
		      else
			$result =  $new_obj->net_promotion($data);
		    }
		 
	    }
	    
	    if(in_array("1",$promotiontypecode))
	    {
		//$promotiontypecode
		 $key_id = array_keys($promotiontypecode, "1");
		
		 for($i=0;$i<count($key_id);$i++)
		{
		    $explode_arr= explode("_",$getvalue[$key_id[$i]]);
		
		    $data =array(
			      'plannumber'=>$plannumber[$key_id[$i]],
			      'customer_code'=>$this->formData['in_customercode'],
			      'assignment_no'=>$assignmentno[$key_id[$i]],
			     'visit_key'=>$storage->Invoice[0]['visitkey'],
			'route_key'=>$storage->Invoice[0]['routekey'],
			      'ragebasis' =>$rangebasis[$key_id[$i]],
			      'transactionkey' =>$storage->Invoice[0]['invoice_transaction_key'],
			      'qualification_group'=>$qualificationgroup[$key_id[$i]],
                   'sales_qnt_org' => $promotion_sales_qty[$key_id[$i]],
                'sales_amt_org' => $promotion_sales_amt[$key_id[$i]]
			      );
		    
		   if($assignmentgroup[$key_id[$i]] == "1")
		      	$result =  $all_obj->first_promotion($data);
		      else
		    $result =  $new_obj->first_promotion($data);
		}
		 
		
	    }
	 
	    if(in_array("2",$promotiontypecode))
	    {
		//$promotiontypecode
		 $key_id = array_keys($promotiontypecode, "2");
		 for($i=0;$i<count($key_id);$i++)
		{
		    $explode_arr= explode("_",$getvalue[$key_id[$i]]);
		   
		    $data =array(
				 'plannumber'=>$plannumber[$key_id[$i]],
				 'customer_code'=>$this->formData['in_customercode'],
				 'assignment_no'=>$assignmentno[$key_id[$i]],
				'visit_key'=>$storage->Invoice[0]['visitkey'],
			'route_key'=>$storage->Invoice[0]['routekey'],
				 'ragebasis' =>$rangebasis[$key_id[$i]],
				 'transactionkey' =>$storage->Invoice[0]['invoice_transaction_key'],
				 'qualification_group'=>$qualificationgroup[$key_id[$i]],
                  'sales_qnt_org' => $promotion_sales_qty[$key_id[$i]],
                'sales_amt_org' => $promotion_sales_amt[$key_id[$i]]
				 );
		    
		     if($assignmentgroup[$key_id[$i]] == "1")
		      	$result =  $all_obj->second_promotion($data);
		      else
		   $result =  $new_obj->second_promotion($data);
		}
		 
		
	    }
	   
	    if(in_array("5",$promotiontypecode) || in_array("6",$promotiontypecode))
	    {
            //$promotiontypecode
            $key_id = array_keys($promotiontypecode, "5");
            for($i=0;$i<count($key_id);$i++)
            {
                $promotion5 =array();
                $explode_arr= explode("_",$getvalue[$key_id[$i]]);
                $promotion5[] =array(
                    'plannumber'=>$plannumber[$key_id[$i]],
                    'assignment_no'=>$assignmentno[$key_id[$i]],
                    'ragebasis' =>$rangebasis[$key_id[$i]],
                    'qualification_group'=>$qualificationgroup[$key_id[$i]],
                );
                
                $data =array(
                
                    'customer_code'=>$this->formData['in_customercode'],
                    'visit_key'=>$storage->Invoice[0]['visitkey'],
                    'route_key'=>$storage->Invoice[0]['routekey'],
                    'transactionkey' =>$storage->Invoice[0]['invoice_transaction_key'],
                    'sales_qnt_org' => $promotion_sales_qty[$key_id[$i]],
                    'sales_amt_org' => $promotion_sales_amt[$key_id[$i]]
                );
                
                if($assignmentgroup[$key_id[$i]] == "1")
                    $result =  $all_obj->five_promotion($data,$promotiontypecode,$promotion5,$promotion6 = array());
                else
                    $result =  $new_obj->five_promotion($data,$promotiontypecode,$promotion5,$promotion6 = array());
    
                
            }
            $key_id =array();
            $key_id = array_keys($promotiontypecode, "6");
            for($i=0;$i<count($key_id);$i++)
            {
                $promotion6 =array();
                $explode_arr= explode("_",$getvalue[$key_id[$i]]);
                $promotion6[] =array(
                      'plannumber'=>$plannumber[$key_id[$i]],
                      'assignment_no'=>$assignmentno[$key_id[$i]],
                      'ragebasis' =>$rangebasis[$key_id[$i]],
                      'qualification_group'=>$qualificationgroup[$key_id[$i]]
                       );
                
                 $data =array(
                     
                        'customer_code'=>$this->formData['in_customercode'],
                        'visit_key'=>$storage->Invoice[0]['visitkey'],
                        'route_key'=>$storage->Invoice[0]['routekey'],
                        'transactionkey' =>$storage->Invoice[0]['invoice_transaction_key'],
                        'sales_qnt_org' => $promotion_sales_qty[$key_id[$i]],
                        'sales_amt_org' => $promotion_sales_amt[$key_id[$i]]
                      );
             
                if($assignmentgroup[$key_id[$i]] == "1")
                    $result =  $all_obj->five_promotion($data,$promotiontypecode,$promotion5 = array(),$promotion6);
                else
                    $result =  $new_obj->five_promotion($data,$promotiontypecode,$promotion5 = array(),$promotion6);
                
                
            }
            // $data =array(
            //	     
            //	      'customer_code'=>$this->formData['in_customercode'],
            //	    'visit_key'=>$storage->Invoice[0]['visitkey'],
            //	'route_key'=>$storage->Invoice[0]['routekey'],
            //	       'transactionkey' =>$storage->Invoice[0]['invoice_transaction_key']
            //	      );
            // 
            // if($qualificationgroup[$key_id[$i]] == "1")
            //      	$result =  $all_obj->five_promotion($data,$promotiontypecode,$promotion5,$promotion6);
            //      else
            //$result =  $new_obj->five_promotion($data,$promotiontypecode,$promotion5,$promotion6);
	   }
	    
	    if(in_array("7",$promotiontypecode))
	    {
            //$promotiontypecode
            $key_id = array_keys($promotiontypecode, "7");
            
            for($i=0;$i<count($key_id);$i++)
            {
                
                $explode_arr= explode("_",$getvalue[$key_id[$i]]);
                
                if($rangebasis[$key_id[$i]] == "3" || $rangebasis[$key_id[$i]] == "4"){
                    
                    $data = array(
                            'plannumber'=>$plannumber[$key_id[$i]],
                            'customer_code'=>$this->formData['in_customercode'],
                            'assignment_no'=>$assignmentno[$key_id[$i]],
                            'visit_key'=>$storage->Invoice[0]['visitkey'],
                            'route_key'=>$storage->Invoice[0]['routekey'],
                            'ragebasis' =>$rangebasis[$key_id[$i]],
                            'transactionkey' =>$storage->Invoice[0]['invoice_transaction_key'],
                            'promotion_fixed_amt' => $promotion_fixed_amt[$key_id[$i]],
                            'repeatingrange_val' => $repeatingrange_val[$key_id[$i]],
                            'sales_qnt_org' => $promotion_sales_qty[$key_id[$i]]
                        );
                    
                    if($data["sales_qnt_org"] > 0) {
                        $result = $new_obj->seven_promotion_fixed($data);
                    }
                    
                } else {
                    $data = array(
                            'plannumber'=>$plannumber[$key_id[$i]],
                            'customer_code'=>$this->formData['in_customercode'],
                            'assignment_no'=>$assignmentno[$key_id[$i]],
                            'visit_key'=>$storage->Invoice[0]['visitkey'],
                            'route_key'=>$storage->Invoice[0]['routekey'],
                            'ragebasis' =>$rangebasis[$key_id[$i]],
                            'transactionkey' =>$storage->Invoice[0]['invoice_transaction_key'],
                            'qualification_group'=>$qualificationgroup[$key_id[$i]],
                            'sales_qnt_org' => $promotion_sales_qty[$key_id[$i]],
                            'sales_amt_org' => $promotion_sales_amt[$key_id[$i]]
                        );
                    if($assignmentgroup[$key_id[$i]] == "1")
                        $result =  $all_obj->seven_promotion($data);
                    else
                        $result =  $new_obj->seven_promotion($data);
                }
            }
	    }
        
        $data = array('visit_key'=>$storage->Invoice[0]['visitkey'],
                    'route_key'=>$storage->Invoice[0]['routekey'],
			       'transactionkey' =>$storage->Invoice[0]['invoice_transaction_key']);
        $new_obj->update_promoamount($data);
	}
	exit;
	
    }
      /**
    * @name       promotionsaveAction
    * @since      01-02-2012
    * @version    Release: 8
    * @author     GP <gayatri@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This is action save promotion review free quantity data
    *
    */
    public function promotionreviewsaveAction()
    {
	$this->allData    = $this->getRequest()->getParams();
	$this->formData   = $this->_request->getPost();
	
	$storage 		= new Zend_Session_Namespace('Add_Sales_Order');
	
	
	
	$storage->Amount     =  array("add_amount"=>array("final_total_amount_first"=>$this->formData['final_total_amount_first'],
						    "final_total_amount_last"=>$this->formData['final_total_amount_last']));
	if(isset($this->formData['txt_free_item']) && !empty($this->formData['txt_free_item']))
	{
	    $data =array( "transactionkey" =>$storage->Invoice[0]['invoice_transaction_key'],
			  "visit_key"=>$storage->Invoice[0]['visitkey'],
			  "route_key"=>$storage->Invoice[0]['routekey'],
			 );
	    
	    $new_obj = new SFA_Model_Orderpromotioncalculate();
	    
	    $new_obj->remove_promotion_free_quantity($data);
	     
	    for($i=0;$i<count($this->formData['txt_free_item']);$i++)
	    {
            if($this->formData['txt_free_item'][$i] != '' && $this->formData['txt_free_item'][$i] != 0)
            {
                $data =array("qty"=>$this->formData['txt_free_item'][$i],
                     "item_code"=>$this->formData['txt_free_item_code'][$i],
                     "transactionkey" =>$storage->Invoice[0]['invoice_transaction_key'],
                     "visit_key"=>$storage->Invoice[0]['visitkey'],
                     "route_key"=>$storage->Invoice[0]['routekey']
                     );
                
                $new_obj->free_promotion_update($data);
            }
	    }
	}
	
	echo $storage->Invoice[0]['invoice_transaction_key']."$::$".number_format($storage->Amount['add_amount']['final_total_amount_last'],$this->decimalplaces);
	exit;
    }
   
    
   
}