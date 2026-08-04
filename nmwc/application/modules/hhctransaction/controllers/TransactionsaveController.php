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


class Hhctransaction_TransactionsaveController extends Hhctransaction_Library_Controller_Action_Abstract
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
	    $storage 		= new Zend_Session_Namespace('Add_invoice_data');
		//for first time session array is blank
		
	    if(!isset($storage->Invoice))
	    {
		    $storage->Invoice =array();
	    }
	    
	   
	   if(isset($this->formData['hdndata']) && $this->formData['hdndata'] != "")
	   {
	  
		if(strtolower($this->formData['hdndata']) == "add_invoice")
		{
		    $check_entry_value = $storage->Invoice['0']['invoice_transaction_key'];
		  
		    $field_list = array("txt_invoice_no","txt_doc_no","ddlroute","ddlcustomer","txt_invoice_comment");
	      
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
			
			$result 		= $this->SFA_Comman->executequery('CALL sp_add_transaction_invoiceadd_detail1(?,?,?,?,?,?)',$param_array,'');
		        // $storage->Invoice    = array_merge($result[0][0],array($this->formData));
			$storage->Invoice       = array_merge($result[0],array("add_invoice"=>array($this->formData)));
			
		    }
		    else
		    {
			
			$param_array[$i+2]=$storage->Invoice[0]['invoice_transaction_key'];
			$result 		= $this->SFA_Comman->executequery('CALL sp_edit_transaction_invoiceadd_detail1(?,?,?,?,?,?,?)',$param_array,'');
			$storage->Invoice =   array();
			//print_r($result);
			$storage->Invoice    = array_merge($result[0],array("add_invoice"=>array($this->formData)));
			
		    }
		  
	   
		}
	 }
	 else
	  {
		   
		    
		    // marge session array
		    if(isset($this->formData['hdndata']))
		    {
		  
			    $storage->Invoice = array_merge($storage->Invoice,array($this->formData['hdndata']=>$this->formData));
		    }
		    else
		    {
			    $storage->Invoice = array_merge($storage->Invoice,array($this->formData));
		    }
	    
	   }
	    
	    //store value into Database
	//    if(isset($this->formData['btn_submit_save_upload']) && $this->formData['btn_submit_save_upload'] == 'Save and Upload')
	//     {
	//	Zend_Session::namespaceUnset('Tbl_album_process_create');
	//     }
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
        
        $storage 		= new Zend_Session_Namespace('Add_invoice_data');
        /**
         *  change by : Pankil Thakkar(30/10/2012)
         *  Description : Remove the entry from batchexpirydetail_temp if the case and pieces are 0. and also update batchmaster_temp for that value.
         *  ......Chages Start......
        */
        $code_arr = array();
        $type_code_name_arr = array();
        
        if(($this->formData['txt_sales_case'] == "" || $this->formData['txt_sales_case'] == 0) && ($this->formData['txt_sales_pieces'] == "" || $this->formData['txt_sales_pieces'] == 0))
        {
            $code_arr[] = 15;
            $type_code_name_arr[] = "salesquantity";
        }
        if(($this->formData['txt_freegood_case'] == "" || $this->formData['txt_freegood_case'] == 0) && ($this->formData['txt_freegood_pieces'] == "" || $this->formData['txt_freegood_pieces'] == 0))
        {
            $code_arr[] = 16;
            $type_code_name_arr[] = "freequantity";
        }
        if(($this->formData['txt_return_cases'] == "" || $this->formData['txt_return_cases'] == 0) && ($this->formData['txt_return_pieces'] == "" || $this->formData['txt_return_pieces'] == 0))
        {
            $code_arr[] = 18;
            $type_code_name_arr[] = "returnquantity";
        }
        if(($this->formData['txt_buyback_cases'] == "" || $this->formData['txt_buyback_cases'] == 0) && ($this->formData['txt_buyback_pieces'] == "" || $this->formData['txt_buyback_pieces'] == 0))
        {
            $code_arr[] = 19;
            $type_code_name_arr[] = "buybackquantity";
        }
        if(($this->formData['txt_damage_cases'] == "" || $this->formData['txt_damage_cases'] == 0) && ($this->formData['txt_damage_pieces'] == "" || $this->formData['txt_damage_pieces'] == 0))
        {
            $code_arr[] = 20;
            $type_code_name_arr[] = "damagequantity";
        }
        if(($this->formData['txt_expirey_case'] == "" || $this->formData['txt_expirey_case'] == 0) && ($this->formData['txt_expirey_pieces'] == "" || $this->formData['txt_expirey_pieces'] == 0))
        {
            $code_arr[] = 21;
            $type_code_name_arr[] = "expiryquantity";
        }
        if(($this->formData['txt_rental_case'] == "" || $this->formData['txt_rental_case'] == 0) && ($this->formData['txt_rental_pieces'] == "" || $this->formData['txt_rental_pieces'] == 0))
        {
            $code_arr[] = 22;
            $type_code_name_arr[] = "rentalquantity";
        }
        $data["itemcode"] = $this->formData['ddlitem'];
        $data["visitkey"] = $storage->Invoice[0]['visitkey'];
        
        $SFA_Model_Batch = new SFA_Model_Batch();
        $SFA_Model_Batch->update_batch_entry($data,$code_arr,$type_code_name_arr);
        /**
         *  ......Chages End......
        */
        
	    
	  if(strtolower($this->formData['hdndata']) == "add_invoice_item")
	  {
		    $param_array =array();
		   		     
		    $param_array[1] 	= $storage->Invoice[0]['invoice_transaction_key'];
		    $param_array[2] 	= $storage->Invoice[0]['routekey'];
		    $param_array[3] 	= $storage->Invoice[0]['visitkey'];
		    $param_array[4] 	= $this->formData['ddlitem'] ;
		    $param_array[5] 	= $salesqty       = (($this->formData['txt_sales_case']* $this->formData['txt_upc'])+$this->formData['txt_sales_pieces']);
		    $param_array[6] 	= $manualfreeqty  = (($this->formData['txt_freegood_case']* $this->formData['txt_upc'])+$this->formData['txt_freegood_pieces']);
	        $param_array[7]		= $returnqty      = (($this->formData['txt_return_cases']* $this->formData['txt_upc'])+$this->formData['txt_return_pieces']);
		    $param_array[8] 	= $buybackqty     = (($this->formData['txt_buyback_cases']* $this->formData['txt_upc'])+$this->formData['txt_buyback_pieces']);
		    $param_array[9]		= $damagedqty     = (($this->formData['txt_damage_cases']* $this->formData['txt_upc'])+$this->formData['txt_damage_pieces']);
		    $param_array[10] 	= $expiryqty     = (($this->formData['txt_expirey_case']* $this->formData['txt_upc'])+$this->formData['txt_expirey_pieces']);
		    $param_array[11] 	= $fixedrentqty  = (($this->formData['txt_rental_case']* $this->formData['txt_upc'])+$this->formData['txt_rental_pieces']);
		   
		    
		    $param_array[12] = $this->formData['txt_sale_price'];
		    $param_array[13] = $this->formData['txt_sales_pcs_price'];
		    $param_array[14] = $this->formData['txt_freegood_price'];
		    $param_array[15] = $this->formData['txt_freegood_pcs_price'];
		    $param_array[16] = $this->formData['txt_return_price'];
		    $param_array[17] = $this->formData['txt_return_pcs_price'];
		    
		    
		    
		    
		    $param_array[18] = (empty($this->formData['ddfreegoodreason'])) ? '0' : $this->formData['ddfreegoodreason'];
		    $param_array[19] = (empty($this->formData['ddreturnreason'])) ? '0' : $this->formData['ddreturnreason'];
		    $param_array[20] = (empty($this->formData['ddbuybackreason'])) ? '0' : $this->formData['ddbuybackreason'];
		    $param_array[21] = (empty($this->formData['dddamagereason'])) ? '0' : $this->formData['dddamagereason'];
		    $param_array[22] = (empty($this->formData['ddexpiryreason'])) ? '0' : $this->formData['ddexpiryreason'];
		    $param_array[23] = '0';
		    
		    $param_array[24] = $this->formData['txt_upc'];
			
			# @Hiren Dave === for total totalbuybackfreeamount
			if($buybackqty > 0)
			{
				$param_array[25] = (($this->formData['txt_buyback_cases']*$this->formData['txt_freegood_price'])+($this->formData['txt_buyback_pieces']*$this->formData['txt_freegood_pcs_price']));
			}
            else
            {
                $param_array[25] = 0;
            }
		   
		    $result 	= $this->SFA_Comman->executequery('CALL sp_add_transaction_invoiceadd_detail2(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',$param_array,'');
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
	
	$storage 		= new Zend_Session_Namespace('Add_invoice_data');
	
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
                //if($this->formData['promotion_return_qty'][$i] != 0)
                //{
                    $promotion_return_qty[] = $this->formData['promotion_return_qty'][$i];
                //}
                //if($this->formData['promotion_return_amt'][$i] != 0)
                //{
                    $promotion_return_amt[] = $this->formData['promotion_return_amt'][$i];
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
	//print_r($plannumber);
	//exit;
	//print_r($getvalue);
	//print_r($promotiontypecode);
	$new_obj = new SFA_Model_Promotioncalculate();
	$all_obj = new SFA_Model_Allitempromotion();
	
      // echo $storage->validation['Enable_Sales_Promotion'];exit;
	
	if($storage->validation['Enable_Sales_Promotion'] == 2 || $storage->validation['Enable_Sales_Promotion'] == 3)
	{
	    $renew_obj = new SFA_Model_Returnpromotioncalculate();
	    $reall_obj = new SFA_Model_Returnallitempromotion();
	}
	    
    
	 $data =array(
		    'customer_code'=>$this->formData['in_customercode'],
		    'visit_key'=>$storage->Invoice[0]['visitkey'],
		    'route_key'=>$storage->Invoice[0]['routekey'],
		    'transactionkey' =>$storage->Invoice[0]['invoice_transaction_key']
		    );
       $new_obj->remove_promotion($data);
       if($storage->validation['Enable_Sales_Promotion'] == 2 || $storage->validation['Enable_Sales_Promotion'] == 3)
       {
       $data =array(
		    'customer_code'=>$this->formData['in_customercode'],
		    'visit_key'=>$storage->Invoice[0]['visitkey'],
		    'route_key'=>$storage->Invoice[0]['routekey'],
		    'transactionkey' =>$storage->Invoice[0]['invoice_transaction_key']
		    );
       $renew_obj->remove_promotion($data);
       }
	if(!empty($getvalue))
	{
	    
	 
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
                'sales_amt_org' => $promotion_sales_amt[$key_id[$i]],
                'return_qnt_org' => $promotion_return_qty[$key_id[$i]],
                'return_amt_org' => $promotion_return_amt[$key_id[$i]]
				  );
			if($storage->validation['Enable_Sales_Promotion'] == 1 || $storage->validation['Enable_Sales_Promotion'] == 3)
			{ 
				if($qualificationgroup[$key_id[$i]] == "1")
				    $result =  $all_obj->net_promotion($data);
				else
		    		  $result =  $new_obj->net_promotion($data);
			    
			}
		     	if($storage->validation['Enable_Sales_Promotion'] == 2 || $storage->validation['Enable_Sales_Promotion'] == 3)
			{
			   
			    if($qualificationgroup[$key_id[$i]] == "1")
			  	$result =  $reall_obj->net_promotion($data);
			    else
			    $result =  $renew_obj->net_promotion($data);
			}
			
		    }
		 
	    }
	//echo "dfsdf";exit;
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
                'sales_amt_org' => $promotion_sales_amt[$key_id[$i]],
                'return_qnt_org' => $promotion_return_qty[$key_id[$i]],
                'return_amt_org' => $promotion_return_amt[$key_id[$i]]
			      );
		    if($storage->validation['Enable_Sales_Promotion'] == 1 || $storage->validation['Enable_Sales_Promotion'] == 3)
		    {
			if($assignmentgroup[$key_id[$i]] == "1")
				$result =  $all_obj->first_promotion($data);
			 else
				 $result =  $new_obj->first_promotion($data);
		    }
		    
		    if($storage->validation['Enable_Sales_Promotion'] == 2 || $storage->validation['Enable_Sales_Promotion'] == 3)
			{
			    if($assignmentgroup[$key_id[$i]] == "1")
				$result =  $reall_obj->first_promotion($data);
			     else
				$result =  $renew_obj->first_promotion($data);
			}
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
                'sales_amt_org' => $promotion_sales_amt[$key_id[$i]],
                'return_qnt_org' => $promotion_return_qty[$key_id[$i]],
                'return_amt_org' => $promotion_return_amt[$key_id[$i]]
				 );
		    
		   //pr($data);
//           echo $storage->validation['Enable_Sales_Promotion']."---";
           //echo $qualificationgroup[$key_id[$i]];
			if($storage->validation['Enable_Sales_Promotion'] == 1 || $storage->validation['Enable_Sales_Promotion'] == 3)
			{
		    		if($assignmentgroup[$key_id[$i]] == "1")
					$result =  $all_obj->second_promotion($data);
				else
				        $result =  $new_obj->second_promotion($data);
			}	
			if($storage->validation['Enable_Sales_Promotion'] == 2 || $storage->validation['Enable_Sales_Promotion'] == 3)
			{
			   
			    if($assignmentgroup[$key_id[$i]] == "1")
				$result =  $reall_obj->second_promotion($data);
			    else
			        $result =  $renew_obj->second_promotion($data);
			}
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
                        'qualification_group'=>$qualificationgroup[$key_id[$i]]
                      );
                 $data =array(
                        'customer_code'=>$this->formData['in_customercode'],
                        'visit_key'=>$storage->Invoice[0]['visitkey'],
                        'route_key'=>$storage->Invoice[0]['routekey'],
                        'transactionkey' =>$storage->Invoice[0]['invoice_transaction_key'],
                        'sales_qnt_org' => $promotion_sales_qty[$key_id[$i]],
                        'sales_amt_org' => $promotion_sales_amt[$key_id[$i]],
                        'return_qnt_org' => $promotion_return_qty[$key_id[$i]],
                        'return_amt_org' => $promotion_return_amt[$key_id[$i]]
                    );
            
             if($storage->validation['Enable_Sales_Promotion'] == 1 || $storage->validation['Enable_Sales_Promotion'] == 3)
                {
                    if($assignmentgroup[$key_id[$i]] == "1")
                        $result =  $all_obj->five_promotion($data,$promotiontypecode,$promotion5,$promotion6 = array());
                     else
                        $result =  $new_obj->five_promotion($data,$promotiontypecode,$promotion5,$promotion6 = array());
                }
             if($storage->validation['Enable_Sales_Promotion'] == 2 || $storage->validation['Enable_Sales_Promotion'] == 3)
                {
                    if($assignmentgroup[$key_id[$i]] == "1")
                        $result =  $reall_obj->five_promotion($data,$promotiontypecode,$promotion5,$promotion6 = array());
                     else
                    $result =  $renew_obj->five_promotion($data,$promotiontypecode,$promotion5,$promotion6 = array());
                }
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
                        'sales_amt_org' => $promotion_sales_amt[$key_id[$i]],
                        'return_qnt_org' => $promotion_return_qty[$key_id[$i]],
                        'return_amt_org' => $promotion_return_amt[$key_id[$i]]
                    );
                
            //echo $storage->validation['Enable_Sales_Promotion']."---".$i."---".$qualificationgroup[$key_id[$i]]."<br />";
                if($storage->validation['Enable_Sales_Promotion'] == 1 || $storage->validation['Enable_Sales_Promotion'] == 3)
                {
                    if($assignmentgroup[$key_id[$i]] == "1")
                        $result =  $all_obj->five_promotion($data,$promotiontypecode,$promotion5 = array(),$promotion6);
                     else
                        $result =  $new_obj->five_promotion($data,$promotiontypecode,$promotion5 = array(),$promotion6);
                }
                if($storage->validation['Enable_Sales_Promotion'] == 2 || $storage->validation['Enable_Sales_Promotion'] == 3)
                {
                    if($assignmentgroup[$key_id[$i]] == "1")
                        $result =  $reall_obj->five_promotion($data,$promotiontypecode,$promotion5 = array(),$promotion6);
                     else
                    $result =  $renew_obj->five_promotion($data,$promotiontypecode,$promotion5 = array(),$promotion6);
                }
            }
		//exit;
//		 $data =array(
//			        'customer_code'=>$this->formData['in_customercode'],
//                    'visit_key'=>$storage->Invoice[0]['visitkey'],
//                    'route_key'=>$storage->Invoice[0]['routekey'],
//                    'transactionkey' =>$storage->Invoice[0]['invoice_transaction_key'],
//                    'sales_qnt_org' => $promotion_sales_qty[$key_id[$i]],
//                    'sales_amt_org' => $promotion_sales_amt[$key_id[$i]],
//                    'return_qnt_org' => $promotion_return_qty[$key_id[$i]],
//                    'return_amt_org' => $promotion_return_amt[$key_id[$i]]
//			    );
//        
//		 if($storage->validation['Enable_Sales_Promotion'] == 1 || $storage->validation['Enable_Sales_Promotion'] == 3)
//			{
//			    if($qualificationgroup[$key_id[$i]] == "1")
//			     	$result =  $all_obj->five_promotion($data,$promotiontypecode,$promotion5,$promotion6);
//			     else
//			    	$result =  $new_obj->five_promotion($data,$promotiontypecode,$promotion5,$promotion6);
//			}
//		 if($storage->validation['Enable_Sales_Promotion'] == 2 || $storage->validation['Enable_Sales_Promotion'] == 3)
//			{
//			    if($qualificationgroup[$key_id[$i]] == "1")
//			    	$result =  $reall_obj->five_promotion($data,$promotiontypecode,$promotion5,$promotion6);
//			     else
//				$result =  $renew_obj->five_promotion($data,$promotiontypecode,$promotion5,$promotion6);
//			}
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
                        'return_promotion_amt' => $return_promotion_amt[$key_id[$i]],
                        'repeatingrange_val' => $repeatingrange_val[$key_id[$i]],
                        'sales_qnt_org' => $promotion_sales_qty[$key_id[$i]],
                        'return_qnt_org' => $promotion_return_qty[$key_id[$i]]
                    );
                //pr($data,1);
                if($data["sales_qnt_org"] > 0) {
                    if($storage->validation['Enable_Sales_Promotion'] == 1 || $storage->validation['Enable_Sales_Promotion'] == 3)
                    {
                        $result = $new_obj->seven_promotion_fixed($data);
                    }
                }
                if($data["return_qnt_org"] > 0) {
                    if($storage->validation['Enable_Sales_Promotion'] == 2 || $storage->validation['Enable_Sales_Promotion'] == 3)
                    {
                        $result = $renew_obj->seven_promotion_fixed($data);
                    }
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
                        'sales_amt_org' => $promotion_sales_amt[$key_id[$i]],
                        'return_qnt_org' => $promotion_return_qty[$key_id[$i]],
                        'return_amt_org' => $promotion_return_amt[$key_id[$i]]
                    );
            
                if($storage->validation['Enable_Sales_Promotion'] == 1 || $storage->validation['Enable_Sales_Promotion'] == 3)
                {
                    if($assignmentgroup[$key_id[$i]] == "1")
                        $result =  $all_obj->seven_promotion($data);
                    else
                        $result =  $new_obj->seven_promotion($data);
                } 
                 if($storage->validation['Enable_Sales_Promotion'] == 2 || $storage->validation['Enable_Sales_Promotion'] == 3)
                {
                    if($assignmentgroup[$key_id[$i]] == "1")
                        $result =  $reall_obj->seven_promotion($data);
                    else
                        $result =  $renew_obj->seven_promotion($data);
                }
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
	
	$storage 		= new Zend_Session_Namespace('Add_invoice_data');
	$storage->Amount     =  array("add_amount"=>array("final_total_amount_first"=>$this->formData['final_total_amount_first'],
						    "final_total_amount_last"=>$this->formData['final_total_amount_last']));
	if(isset($this->formData['txt_free_item']) && !empty($this->formData['txt_free_item']))
	{
	    $data =array( "transactionkey" =>$storage->Invoice[0]['invoice_transaction_key'],
			  "visit_key"=>$storage->Invoice[0]['visitkey'],
			  "route_key"=>$storage->Invoice[0]['routekey'],
			 );
	    
	    $new_obj = new SFA_Model_Promotioncalculate();
	    
	    $new_obj->remove_promotion_free_quantity($data);
        
	    for($i=0;$i<count($this->formData['txt_free_item']);$i++)
	    {
            
            if($this->formData['txt_free_item'][$i] != '' && $this->formData['txt_free_item'][$i] != 0)
            {
                $data1 = $this->onbluravailableqnt(array("itemcode"=>$this->formData['txt_free_item_code'][$i],"qty"=>$this->formData['txt_free_item'][$i],"type"=>"promo"));
                $data =array("qty"          	=>	$this->formData['txt_free_item'][$i],
                     "item_code"  		=>	$this->formData['txt_free_item_code'][$i],
                     "transactionkey" 	=>	$storage->Invoice[0]['invoice_transaction_key'],
                     "visit_key"		=>	$storage->Invoice[0]['visitkey'],
                     "route_key"		=>	$storage->Invoice[0]['routekey'],
                     "item_transaction_type" =>    $this->formData['txt_transaction_type'][$i]
                     );
                
                $new_obj->free_promotion_update($data);
            }
	    }
	}
	
	echo $storage->Invoice[0]['invoice_transaction_key']."$::$".number_format($storage->Amount['add_amount']['final_total_amount_last'],$this->decimalplaces);
	exit;
    }
   /**
    * @name       cashcollectionsavecashAction
    * @since      01-02-2012
    * @version    Release: 8
    * @author     GP <gayatri@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This is action save cashcollection data
    *
    */
    public function cashcollectionsavecashAction()
    {
	$this->allData    = $this->getRequest()->getParams();
	$this->formData   = $this->_request->getPost();
	
	$storage 		= new Zend_Session_Namespace('Add_invoice_data');
	
	if(isset($this->formData['ddl_payment_mode']) && $this->formData['ddl_payment_mode'] != "" )
	{
	    if($this->formData['ddl_payment_mode'] == 0)
	    {
		    $param_array[1] =$storage->Invoice[0]['routekey'];
		    $param_array[2] =$storage->Invoice[0]['visitkey'];
		    $param_array[3] =$this->formData['txt_user_amount_due'] ;
		    $param_array[4] ="";
		    $param_array[5] ="0";
		    $param_array[6] ="0";
		    $param_array[7] ="0" ; //for chsh pass 0 vlaue
	    }
	    else{
		    $param_array[1] =$storage->Invoice[0]['routekey'];
		    $param_array[2] =$storage->Invoice[0]['visitkey'];
		    $param_array[3] =$this->formData['txt_user_amount_due'] ;
		    $param_array[4] =$this->formData['txt_cheques'];
		    $param_array[5] =$this->formData['txt_date'];
		    $param_array[6] =$this->formData['ddl_bank'];
		    $param_array[7] ="1" ;
	    }
	    
	    $result 	= $this->SFA_Comman->executequery('CALL sp_add_transaction_invoiceadd_cashcollection(?,?,?,?,?,?,?)',$param_array,'');
	}
	
	exit;
    }
    
     /**
    * @name       signatureAction
    * @since      01-02-2012
    * @version    Release: 8
    * @author     GP <gayatri@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This is action save signature data
    *
    */
    public function signaturedataAction()
    {
		$this->allData  = $this->getRequest()->getParams();
		$this->formData = $this->_request->getPost();		
		$storage 		= new Zend_Session_Namespace('Add_invoice_data');
		
		// Hiren Dave on 26th Nov 2012 open the below condition to fix signature issue in invoice.
		if(!empty($this->formData['output']))
		{
			$sig = $_POST['output'];
			
			// or the better way, using PHP filters
			$sig = filter_input(INPUT_POST, 'output', FILTER_UNSAFE_RAW);
			
			$parma[1] = $storage->Invoice[0]['visitkey'];
			$parma[2] = $storage->Invoice[0]['routekey'];
			$parma[3] = $storage->Invoice[0]['invoice_transaction_key'];
			$parma[4] = $storage->Invoice['add_invoice'][0]['ddlcustomer'];
			$parma[5] = $this->formData['txt_amount_due'];
			$parma[6] = $sig;
			
			$result   = $this->SFA_Comman->executequery('CALL sp_add_transaction_invoiceadd_signaturedata(?,?,?,?,?,?)',$parma,'');
		}
		exit;
    }
    
    function onbluravailableqnt1($params = array()) {
        
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
        }
        return 1;
    }
    function onbluravailableqnt($params = array()) {
        
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
        }
        else
        {
            echo "error";
        }
    }
}