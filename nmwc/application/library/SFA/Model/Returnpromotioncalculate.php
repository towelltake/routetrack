<?php
/**
 * @name       SFA_Model_User
 * @since      19-09-2011
 * @version    Release: 8
 * @author     GP
 * @copyright  Elan Technologies
 * @param   	
 * This Class contains all the Promtotion and invoice realate function
 */

class SFA_Model_Returnpromotioncalculate extends Zend_Db_Table_Abstract
{

    protected $_name 	= 'user';

    /**
    * @name       remove_promotion
    * @since      06-08-2012
    * @version    Release: 8
    * @author     GP
    * @copyright  Elan Technologies
    * @param   	 Remove inserted promotion and genereate new 
    *
    */
    public function remove_promotion($data=array())
    {
	$remove_query ="DELETE  FROM promotiondetail_temp WHERE promotiondetail_temp.routekey =".$data['route_key'] ." AND 
			promotiondetail_temp.visitkey = ".$data['visit_key']." AND promotiondetail_temp.transactionkey=".$data['transactionkey']."
			and itemtransactiontype =2
			";
			
	//echo $remove_query;		exit;
	$this->_db->query($remove_query);
	
	$update_query = "UPDATE  invoicedetail_temp SET return_amount ='0.0000' ,returnpromoamount ='0' , returnpromovalue =0
		    WHERE invoicedetail_temp.routekey = ".$data['route_key'] ." AND invoicedetail_temp.visitkey = ".$data['visit_key']." and
		    invoicedetail_temp.transactionkey =".$data['transactionkey'];
		//echo $update_query;		exit;
		    $this->_db->query($update_query);
	
    }
  
    /**
    * @name       net_promotion
    * @since      06-08-2012
    * @version    Release: 8
    * @author     GP
    * @copyright  Elan Technologies
    * Apply First Net Promotion item wise
    *
    */
    public function net_promotion($data=array())
    {
	$select="SELECT IFNULL(SUM(invoicedetail_temp.returnqty),0) AS ReturnQty,
	SUM(((returnqty / itemmaster.unitspercase)  *
	( invoicedetail_temp.goodreturncaseprice )) +
	((returnqty % itemmaster.unitspercase) * invoicedetail_temp.goodreturnprice)) AS ReturnAmount 
	FROM promokeydetail
	
	INNER JOIN productgroupdetail ON  promokeydetail.qualificationgroup = productgroupdetail.groupnumber 
	INNER JOIN invoicedetail_temp ON productgroupdetail.itemcode = invoicedetail_temp.itemcode
	AND invoicedetail_temp.routekey =".$data['route_key'] ."
	AND invoicedetail_temp.visitkey =".$data['visit_key']." and invoicedetail_temp.returnqty != 0
	INNER JOIN itemmaster ON itemmaster.actualitemcode = productgroupdetail.itemcode 
	LEFT JOIN pricingdetail1 p ON p.itemcode = productgroupdetail.itemcode 
	AND p.customerpricingkey =
	(SELECT IFNULL(customerpricing1.customerpricingkey,0) FROM customerpricing1 
	JOIN customermaster ON customerpricing1.pricingplankey = customermaster.pricingkey 
	AND customercode = ".$data['customer_code'].") 
	WHERE promokeydetail.assignmentnumber = ".$data['assignment_no']." 
	AND promokeydetail.promotionkey = 
	(SELECT promotionkey FROM customermaster  WHERE customercode = ".$data['customer_code'].") 
	AND promokeydetail.plannumber =   ".$data['plannumber'] ;


	$result = $this->getAdapter()->fetchAll($select);
	
	if(!empty($result))
	{
	   foreach($result as $value)
	    {
		$value['ReturnQty'] = str_replace(",","",$value['ReturnQty']);
		$value['ReturnAmount'] = str_replace(",","",$value['ReturnAmount']);
        $data['return_qnt_org'] = ($data['return_qnt_org'] != "") ? str_replace(",","",$data['return_qnt_org']): 0;
		$data['return_amt_org'] = ($data['return_amt_org'] != "") ? str_replace(",","",$data['return_amt_org']): 0;
		$range_arr =array();
		
		if(isset($data['ragebasis']) && ($data['ragebasis'] == 1))
		{
		       $select_range ="SELECT promotionamount ,rangelow,rangehigh,repeatingrange
				     FROM promotionassignmentadvanced WHERE assignmentnumber =".$data['assignment_no']." 
				     AND IF(repeatingrange = 0 ,  ".str_replace(",","",$data['return_qnt_org'])."  BETWEEN rangelow AND rangehigh ,
				     ".str_replace(",","",$data['return_qnt_org'])."  >= rangelow ) ";
		}
		else
		{
		     $select_range ="SELECT promotionamount ,rangelow,rangehigh,repeatingrange
				     FROM promotionassignmentadvanced WHERE assignmentnumber =".$data['assignment_no']." 
				     AND IF(repeatingrange = 0 ,  ".str_replace(",","",$data['return_amt_org'])."  BETWEEN rangelow AND rangehigh ,
				     ".str_replace(",","",$data['return_amt_org'])."  >= rangelow ) ";
		}
		
		$range_arr = $this->getAdapter()->fetchAll($select_range);
		if(!empty($range_arr))
		{
		    
		    $insert_query ="INSERT INTO promotiondetail_temp
				    (routekey,visitkey,transactionkey,itemtransactiontype,itemcode,promotiontypecode,
				    promotionamount,oldpromotionamount,promotionplannumber,assignmentkey,weighted,record_flag,
				    salesamount,rangelow,repeatingrange)
				    
				   SELECT ".$data['route_key'].",".$data['visit_key'].",".$data['transactionkey'].",2, productgroupdetail.itemcode,0,
				   (((returnqty / itemmaster.unitspercase)  *
					    (invoicedetail_temp.goodreturncaseprice)) +
					    ((returnqty % itemmaster.unitspercase)  * invoicedetail_temp.goodreturnprice) -
					    ((returnqty / itemmaster.unitspercase) *  productgroupdetail.promocaseprice) +
					    ((returnqty % itemmaster.unitspercase) * productgroupdetail.promopcprice)) AS promoamount, 
				 REPLACE(FORMAT((((returnqty / itemmaster.unitspercase)  *
				     (invoicedetail_temp.goodreturncaseprice)) +
				     ((returnqty % itemmaster.unitspercase)  * invoicedetail_temp.goodreturnprice)),2),',','') AS ReturnAmount,
				  ".$data['plannumber'].",".$data['assignment_no'].",'0','1',
				    REPLACE(FORMAT( (((returnqty / itemmaster.unitspercase)  *
				    (IF (invoicedetail_temp.goodreturncaseprice IS NULL, itemmaster.caseprice ,invoicedetail_temp.goodreturncaseprice)))
				    +  ((returnqty % itemmaster.unitspercase)  * invoicedetail_temp.goodreturnprice)),2),',','') AS ReturnAmount,
				   ".$range_arr[0]['rangelow'].",".$range_arr[0]['repeatingrange']."
	
	 
			    FROM promokeydetail 
			    INNER JOIN promotionassignmentadvanced ON promotionassignmentadvanced.assignmentnumber=promokeydetail.assignmentnumber 
			    INNER JOIN productgroupdetail ON  promotionassignmentadvanced.promotionamount = productgroupdetail.groupnumber 
			    INNER JOIN invoicedetail_temp ON productgroupdetail.itemcode = invoicedetail_temp.itemcode 
							      AND invoicedetail_temp.routekey = ".$data['route_key']."
							   AND invoicedetail_temp.visitkey  = ".$data['visit_key']."
			    INNER JOIN itemmaster ON itemmaster.actualitemcode = productgroupdetail.itemcode 
			    WHERE promokeydetail.assignmentnumber = ".$data['assignment_no']." 
			    AND promokeydetail.promotionkey = (SELECT promotionkey FROM customermaster  WHERE customercode =".$data['customer_code'].") 
			    AND promokeydetail.plannumber =".$data['plannumber'];
				
			
			 $this->_db->query($insert_query);
			
			$update_query= "UPDATE promotiondetail_temp set salesamount = oldpromotionamount - promotionamount WHERE
			    promotiondetail_temp.routekey = ".$data['route_key']." and 
			    promotiondetail_temp.visitkey = ".$data['visit_key']." and
			    promotiondetail_temp.transactionkey = ".$data['transactionkey']." and
			    promotiondetail_temp.promotiontypecode= '0' ";
			  //  echo $update_query;
			    $this->_db->query($update_query);
			    
			$update_query=  "UPDATE invoicedetail_temp  ,promotiondetail_temp
					 SET invoicedetail_temp.returnpromoamount = IFNULL(invoicedetail_temp.returnpromoamount,0) + promotiondetail_temp.promotionamount,
					 return_amount = promotiondetail_temp.salesamount
					 WHERE invoicedetail_temp.itemcode = promotiondetail_temp.itemcode
					 AND invoicedetail_temp.routekey =promotiondetail_temp.routekey AND
					 invoicedetail_temp.transactionkey = promotiondetail_temp.transactionkey AND
					 promotiondetail_temp.transactionkey = ".$data['transactionkey']."
					 AND promotiondetail_temp.promotiontypecode =0";
			
			    $this->_db->query($update_query);
		
		 } 
	    }
	
	}
   
   }
    /**
    * @name       first_promotion
    * @since      06-08-2012
    * @version    Release: 8
    * @author     GP
    * @copyright  Elan Technologies
    * Apply first promotion after net promotion. this is promotion pronounce like line item applay on amount
    *
    */
    
    public function first_promotion($data=array())
    {
	$select  ="    SELECT  IF(return_amount = '0.0000', FORMAT(((returnqty / itemmaster.unitspercase) *
			invoicedetail_temp.goodreturncaseprice
			+ ((returnqty % itemmaster.unitspercase) * invoicedetail_temp.goodreturnprice)),4) ,return_amount)AS ReturnAmount,
			productgroupdetail.itemcode, IFNULL(invoicedetail_temp.returnqty,0) AS ReturnQty
			
			FROM promokeydetail
			INNER JOIN productgroupdetail ON  promokeydetail.assignmentgroup = productgroupdetail.groupnumber 
			INNER JOIN invoicedetail_temp ON productgroupdetail.itemcode = invoicedetail_temp.itemcode 
    				 AND invoicedetail_temp.routekey =".$data['route_key'] ."
				 AND invoicedetail_temp.visitkey  =".$data['visit_key']." and invoicedetail_temp.returnqty != 0
				INNER JOIN itemmaster ON itemmaster.actualitemcode = productgroupdetail.itemcode 
				
			WHERE promokeydetail.assignmentnumber = ".$data['assignment_no']." 
			AND promokeydetail.promotionkey = (SELECT promotionkey FROM customermaster WHERE customercode = ".$data['customer_code'].") 
		       AND promokeydetail.plannumber = ".$data['plannumber'] ;
	
	
	$result = $this->getAdapter()->fetchAll($select);
	
	//return result set to controller
	//SFA_Comman::pre($result);
	if(!empty($result))
	{
	    foreach($result as $value)
	    {
		$value['ReturnQty'] = str_replace(",","",$value['ReturnQty']);
		$value['ReturnAmount'] = str_replace(",","",$value['ReturnAmount']);
        $data['return_qnt_org'] = ($data['return_qnt_org'] != "") ? str_replace(",","",$data['return_qnt_org']): 0;
		$data['return_amt_org'] = ($data['return_amt_org'] != "") ? str_replace(",","",$data['return_amt_org']): 0;
		$range_arr =array();
		if(isset($data['ragebasis']) && ($data['ragebasis'] == 1))
		{
		       $select_range ="SELECT promotionamount ,rangelow,rangehigh,repeatingrange
				     FROM promotionassignmentadvanced WHERE assignmentnumber =".$data['assignment_no']." 
				     AND IF(repeatingrange = 0 ,  ".str_replace(",","",$data['return_qnt_org'])."  BETWEEN rangelow AND rangehigh ,
				     ".str_replace(",","",$data['return_qnt_org'])."  >= rangelow ) ";
		}
		else
		{
		     $select_range ="SELECT promotionamount ,rangelow,rangehigh,repeatingrange
				     FROM promotionassignmentadvanced WHERE assignmentnumber =".$data['assignment_no']." 
				     AND IF(repeatingrange = 0 ,  ".str_replace(",","",$data['return_amt_org'])."  BETWEEN rangelow AND rangehigh ,
				     ".str_replace(",","",$data['return_amt_org'])."  >= rangelow ) ";
		}
		
		$range_arr = $this->getAdapter()->fetchAll($select_range);
		//print_r($range_arr);
		if(!empty($range_arr))
		{
		    $promotion_amount = "";
		    $prmotion_cal = "";
		    $prmotion_calculate_arr =array();
		    
			
		    if($range_arr[0]['repeatingrange'] == 1)
		    {
			
			$prmotion_cal 		= $data['return_amt_org']/$range_arr[0]['rangelow'];
			$prmotion_calculate_arr = explode(".",$prmotion_cal);
			$promotion_amount 	=  ($prmotion_calculate_arr[0] * $range_arr[0]['promotionamount']);
			
			$insert_query ="INSERT INTO promotiondetail_temp
				    (routekey,visitkey,transactionkey,itemtransactiontype,itemcode,promotiontypecode,
				    promotionamount,oldpromotionamount,promotionplannumber,assignmentkey,weighted,record_flag,
				    salesamount,rangelow,repeatingrange) 
				values(".$data['route_key'].",".$data['visit_key'].",".$data['transactionkey'].",'2',".$value['itemcode'].",1,
				".$promotion_amount.",".$value['ReturnAmount'].",".$data['plannumber'].",".$data['assignment_no'].",
				'0','1',".($value['ReturnAmount'] - $promotion_amount).",".$range_arr[0]['rangelow'].",
				".$range_arr[0]['repeatingrange']."
				)";
				$this->_db->query($insert_query);
				
				//update Invoice detail
			$invoice_detail ="UPDATE invoicedetail_temp  SET returnpromoamount = returnpromoamount + ".$promotion_amount.",
							    returnpromovalue = returnpromovalue + ".$promotion_amount.",
							    return_amount ='".($value['ReturnAmount'] - $promotion_amount)."'
							    
							    WHERE routekey = ".$data['route_key']." AND visitkey = ".$data['visit_key']." AND itemcode = ".$value['itemcode'];
							    //echo $invoice_detail;exit;
			    $this->_db->query($invoice_detail);
		    }
		    else
		    {
			$insert_query ="INSERT INTO promotiondetail_temp
				    (routekey,visitkey,transactionkey,itemtransactiontype,itemcode,promotiontypecode,
				    promotionamount,oldpromotionamount,promotionplannumber,assignmentkey,weighted,record_flag,
				    salesamount,rangelow,repeatingrange) 
				values(".$data['route_key'].",".$data['visit_key'].",".$data['transactionkey'].",'2',".$value['itemcode'].",1,
				".$range_arr[0]['promotionamount'].",".$value['ReturnAmount'].",".$data['plannumber'].",".$data['assignment_no'].",
				'0','1',".($value['ReturnAmount'] - $range_arr[0]['promotionamount']).",".$range_arr[0]['rangelow'].",
				".$range_arr[0]['repeatingrange']."
				)";
				
			 $this->_db->query($insert_query);
			
			//update Invoice detail
			$invoice_detail ="UPDATE invoicedetail_temp  SET returnpromoamount = returnpromoamount + ".$range_arr[0]['promotionamount'].",
							    returnpromovalue = returnpromovalue + ".$range_arr[0]['promotionamount'].",
							    return_amount ='".($value['ReturnAmount'] - $range_arr[0]['promotionamount'])."'
							    
							    WHERE routekey = ".$data['route_key']." AND visitkey = ".$data['visit_key']." AND itemcode = ".$value['itemcode'];
							   // echo $invoice_detail;exit;
			    $this->_db->query($invoice_detail);
		    }
		    
		}
		
	    }
	   	  
	}
	
	
	
    }
   /**
    * @name       second_promotion
    * @since      06-08-2012
    * @version    Release: 8
    * @author     GP
    * @copyright  Elan Technologies
    * Apply second promotion after net and first promotion. this is promotion pronounce like line item apply on amount but percentage wise
    *
    */
  
     public function second_promotion($data=array())
     {
	$select  ="    SELECT  IF(return_amount = '0.0000', FORMAT(((returnqty / itemmaster.unitspercase) *
			invoicedetail_temp.goodreturncaseprice 
			+ ((returnqty % itemmaster.unitspercase) * invoicedetail_temp.goodreturnprice)),4) ,return_amount)AS ReturnAmount,
			productgroupdetail.itemcode, IFNULL(invoicedetail_temp.returnqty,0) AS ReturnQty
			
			FROM promokeydetail
			INNER JOIN productgroupdetail ON  promokeydetail.assignmentgroup = productgroupdetail.groupnumber 
			INNER JOIN invoicedetail_temp ON productgroupdetail.itemcode = invoicedetail_temp.itemcode 
    				 AND invoicedetail_temp.routekey =".$data['route_key'] ."
				 AND invoicedetail_temp.visitkey  =".$data['visit_key']." and invoicedetail_temp.returnqty != 0
				INNER JOIN itemmaster ON itemmaster.actualitemcode = productgroupdetail.itemcode 
				
			WHERE promokeydetail.assignmentnumber = ".$data['assignment_no']." 
			AND promokeydetail.promotionkey = (SELECT promotionkey FROM customermaster WHERE customercode = ".$data['customer_code'].") 
		       AND promokeydetail.plannumber = ".$data['plannumber'] ;
		//echo $select;       
		
	$result = $this->getAdapter()->fetchAll($select);
	if(!empty($result))
	{
	    foreach($result as $value)
	    {
		$value['ReturnQty'] = str_replace(",","",$value['ReturnQty']);
		$value['ReturnAmount'] = str_replace(",","",$value['ReturnAmount']);
		$data['return_qnt_org'] = ($data['return_qnt_org'] != "") ? str_replace(",","",$data['return_qnt_org']): 0;
		$data['return_amt_org'] = ($data['return_amt_org'] != "") ? str_replace(",","",$data['return_amt_org']): 0;
		//echo $value['ReturnAmount'];
		
		$range_arr =array();
		if(isset($data['ragebasis']) && ($data['ragebasis'] == 1))
		{
		       $select_range ="SELECT promotionamount ,rangelow,rangehigh,repeatingrange
				     FROM promotionassignmentadvanced WHERE assignmentnumber =".$data['assignment_no']." 
				     AND IF(repeatingrange = 0 ,  ".str_replace(",","",$data['return_qnt_org'])."  BETWEEN rangelow AND rangehigh ,
				     ".str_replace(",","",$data['return_qnt_org'])."  >= rangelow ) ";
		}
		else
		{
		     $select_range ="SELECT promotionamount ,rangelow,rangehigh,repeatingrange
				     FROM promotionassignmentadvanced WHERE assignmentnumber =".$data['assignment_no']." 
				     AND IF(repeatingrange = 0 ,  ".str_replace(",","",$data['return_amt_org'])."  BETWEEN rangelow AND rangehigh ,
				     ".str_replace(",","",$data['return_amt_org'])."  >= rangelow ) ";
		}
		
		$range_arr = $this->getAdapter()->fetchAll($select_range);
		
		if(!empty($range_arr))
		{
		    $promotion_amount = "";
		    $prmotion_cal = "";
		    $prmotion_calculate_arr =array();
		    $promotion ="";
		    
		     if($range_arr[0]['repeatingrange'] == 1)
		    {
			
			$prmotion_cal 		= $value['ReturnAmount']/$range_arr[0]['rangelow'];
			$prmotion_calculate_arr = explode(".",$prmotion_cal);
			$promotion_amount 	=  $range_arr[0]['rangelow'] * ($prmotion_calculate_arr[0] * ($range_arr[0]['promotionamount']/100));
			$promotion = $promotion_amount;
			//$promotion  = (($value['ReturnAmount'] * $promotion_amount ));
			//$promotion  = (($value['ReturnAmount'] * $range_arr[0]['promotionamount']) )/$prmotion_calculate_arr[0] ;
			$range_arr[0]['promotionamount'] = $promotion;
			$insert_query ="INSERT INTO promotiondetail_temp
					(routekey,visitkey,transactionkey,itemtransactiontype,itemcode,promotiontypecode,
					promotionamount,oldpromotionamount,promotionplannumber,assignmentkey,weighted,record_flag,
					salesamount,rangelow,repeatingrange) 
				    values(".$data['route_key'].",".$data['visit_key'].",".$data['transactionkey'].",'2',".$value['itemcode'].",2,
				    ".$range_arr[0]['promotionamount'].",".$value['ReturnAmount'].",".$data['plannumber'].",".$data['assignment_no'].",
				    '0','1',".($value['ReturnAmount'] - $range_arr[0]['promotionamount']).",".$range_arr[0]['rangelow'].",
				    ".$range_arr[0]['repeatingrange']."
				    )";
				    ////echo $insert_query;
			     $this->_db->query($insert_query);
			    
			//update Invoice detail
			$invoice_detail ="UPDATE invoicedetail_temp  SET returnpromoamount = returnpromoamount + ".$range_arr[0]['promotionamount'].",
							    returnpromovalue = returnpromovalue + ".$range_arr[0]['promotionamount'].",
							    return_amount ='".($value['ReturnAmount'] - $range_arr[0]['promotionamount'])."'
							    
							    WHERE routekey = ".$data['route_key']." AND visitkey = ".$data['visit_key']." AND itemcode = ".$value['itemcode'];
							    //echo $invoice_detail;exit;
			$this->_db->query($invoice_detail);
		    }
		    else{
			
			 $promotion  = (($value['ReturnAmount'] * ($range_arr[0]['promotionamount']/100) ));
			//$prmotion_cal 		= $value['ReturnAmount']/$range_arr[0]['rangelow'];
			//$prmotion_calculate_arr = explode(".",$prmotion_cal);
			//$promotion  = (($value['ReturnAmount'] * $range_arr[0]['promotionamount'] )/100);
			//$promotion  = (($value['ReturnAmount'] * $range_arr[0]['promotionamount']) )/$prmotion_calculate_arr[0] ;
			$range_arr[0]['promotionamount'] = $promotion;
			$insert_query ="INSERT INTO promotiondetail_temp
					(routekey,visitkey,transactionkey,itemtransactiontype,itemcode,promotiontypecode,
					promotionamount,oldpromotionamount,promotionplannumber,assignmentkey,weighted,record_flag,
					salesamount,rangelow,repeatingrange) 
				    values(".$data['route_key'].",".$data['visit_key'].",".$data['transactionkey'].",'2',".$value['itemcode'].",2,
				    ".$range_arr[0]['promotionamount'].",".$value['ReturnAmount'].",".$data['plannumber'].",".$data['assignment_no'].",
				    '0','1',".($value['ReturnAmount'] - $range_arr[0]['promotionamount']).",".$range_arr[0]['rangelow'].",
				    ".$range_arr[0]['repeatingrange']."
				    )";
			//echo $insert_query;	   
			     $this->_db->query($insert_query);
			    
			//update Invoice detail
			$invoice_detail ="UPDATE invoicedetail_temp  SET returnpromoamount = returnpromoamount + ".$range_arr[0]['promotionamount'].",
							    returnpromovalue = returnpromovalue + ".$range_arr[0]['promotionamount'].",
							    return_amount ='".($value['ReturnAmount'] - $range_arr[0]['promotionamount'])."'
							    
							    WHERE routekey = ".$data['route_key']." AND visitkey = ".$data['visit_key']." AND itemcode = ".$value['itemcode'];
							   
			$this->_db->query($invoice_detail);
			
		    }
		    
		    
		}
		
	    }
	   	  
	}
	
	
    }
   /**
    * @name       five_promotion
    * @since      06-08-2012
    * @version    Release: 8
    * @author     GP
    * @copyright  Elan Technologies
    * Apply five and six promotion on invoice amount.
    *
    */
     
    public function five_promotion($data=array(),$promotiontypecode=array(),$promotion5=array(),$promotion6=array())
    {  
   
	//$select = "SELECT GROUP_CONCAT(invoicedetail_temp.itemcode) as itemcode,
	//	FORMAT(SUM( IF(return_amount = '0.0000', ((returnqty / itemmaster.unitspercase) *
	//	invoicedetail_temp.goodreturncaseprice
	//	+ ((returnqty % itemmaster.unitspercase) * invoicedetail_temp.goodreturnprice)) ,return_amount)),4) AS ReturnAmount,
	//	SUM(IFNULL(invoicedetail_temp.returnqty,0)) AS ReturnQty
	//
	//	FROM invoicedetail_temp
	//	INNER JOIN itemmaster ON itemmaster.actualitemcode  = invoicedetail_temp.itemcode
	//						   
	//	WHERE 		 invoicedetail_temp.routekey =".$data['route_key'] ."
	//			 and invoicedetail_temp.returnqty != 0
	//			 AND invoicedetail_temp.visitkey  =".$data['visit_key'];
		
	//$result = $this->getAdapter()->fetchAll($select);
	//print_r($result);
	//exit;
	//if(!empty($result) && $result[0]['ReturnQty'] != '')
	//{ 
	   
	    if(in_array("5",$promotiontypecode))
	    {
		foreach($promotion5 as $promot5val)
		{
            $final_amount = 0;
            if($promot5val['qualification_group'] == 1) {
                $select = "SELECT GROUP_CONCAT(invoicedetail_temp.itemcode) as itemcode,
                                    FORMAT(SUM( IF(return_amount = '0.0000', (FLOOR(returnqty / itemmaster.unitspercase) *
                                    invoicedetail_temp.goodreturncaseprice
                                    + ((returnqty % itemmaster.unitspercase) * invoicedetail_temp.goodreturnprice)) ,return_amount)),4) AS ReturnAmount,
                                    SUM(IFNULL(invoicedetail_temp.returnqty,0)) AS ReturnQty
                            FROM invoicedetail_temp
                            INNER JOIN itemmaster ON itemmaster.actualitemcode  = invoicedetail_temp.itemcode
                                                   
                            WHERE 		 invoicedetail_temp.routekey =".$data['route_key'] ."
                                     AND invoicedetail_temp.visitkey  =".$data['visit_key'];
            } else { 
                $select =  "SELECT GROUP_CONCAT(productgroupdetail.itemcode) AS itemcode,
                                    FORMAT(SUM( IF(return_amount = '0.0000', (FLOOR(returnqty / itemmaster.unitspercase) *
                                    invoicedetail_temp.goodreturncaseprice
                                    + ((returnqty % itemmaster.unitspercase) * invoicedetail_temp.goodreturnprice)) ,return_amount)),4) AS ReturnAmount,
                                    SUM(IFNULL(invoicedetail_temp.returnqty,0)) AS ReturnQty
                            FROM promokeydetail 
                            INNER JOIN productgroupdetail ON  promokeydetail.assignmentgroup = productgroupdetail.groupnumber 
                            INNER JOIN invoicedetail_temp ON productgroupdetail.itemcode = invoicedetail_temp.itemcode
                                AND invoicedetail_temp.routekey = ".$data['route_key'] ."
                                AND invoicedetail_temp.visitkey  = ".$data['visit_key']." 
                            INNER JOIN itemmaster ON itemmaster.actualitemcode = productgroupdetail.itemcode 
                            WHERE
                                promokeydetail.assignmentnumber = ".$promot5val['assignment_no']." AND
                                promokeydetail.promotionkey = (SELECT promotionkey FROM customermaster 
                                                                    WHERE customercode = " .$data['customer_code']. ")
                            AND promokeydetail.plannumber = ".$promot5val['plannumber']." 
                            GROUP BY promokeydetail.plannumber";
            }
            
            $result = $this->getAdapter()->fetchAll($select);
            $result[0]['ReturnQty'] = str_replace(",","",$result[0]['ReturnQty']);
            $result[0]['ReturnAmount'] = str_replace(",","",$result[0]['ReturnAmount']);
            $data['return_qnt_org'] = ($data['return_qnt_org'] != "") ? str_replace(",","",$data['return_qnt_org']): 0;
            $data['return_amt_org'] = ($data['return_amt_org'] != "") ? str_replace(",","",$data['return_amt_org']): 0;
		    if(isset($promot5val['ragebasis']) && ($promot5val['ragebasis'] == 1))
		    {
			   $select_range ="SELECT promotionamount ,rangelow,rangehigh,repeatingrange
					 FROM promotionassignmentadvanced WHERE assignmentnumber =".$promot5val['assignment_no']." 
					 AND IF(repeatingrange = 0 ,  ".str_replace(",","",$data['return_qnt_org'])."  BETWEEN rangelow AND rangehigh ,
					 ".str_replace(",","",$data['return_qnt_org'])."  >= rangelow ) ";
		    }
		    else
		    {
			 $select_range ="SELECT promotionamount ,rangelow,rangehigh,repeatingrange
					 FROM promotionassignmentadvanced WHERE assignmentnumber =".$promot5val['assignment_no']." 
					 AND IF(repeatingrange = 0 ,  ".str_replace(",","",$data['return_amt_org'])."  BETWEEN rangelow AND rangehigh ,
					 ".str_replace(",","",$data['return_amt_org'])."  >= rangelow ) ";
		    }
		    
		    $range_arr = $this->getAdapter()->fetchAll($select_range);
		   // print_r($range_arr);exit;
		    if(!empty($range_arr))
		    {
			$promotion_amount = "";
			$prmotion_cal = "";
			$prmotion_calculate_arr =array();
			$final_amount ="";
			 if($range_arr[0]['repeatingrange'] == 1)
			{
			    $per_value  		= $range_arr[0]['promotionamount'];
			    $prmotion_cal 		= $data['return_amt_org']/$range_arr[0]['rangelow'];
			    $prmotion_calculate_arr 	= explode(".",$prmotion_cal);
			    $promotion_amount 		=  ($prmotion_calculate_arr[0] * $range_arr[0]['promotionamount']);
			    /**
                 *  Developed By Pankil :- Comment below line for the issue of qulified amount was wrong
                 */
                //$final_amount 		= $result[0]['ReturnAmount'] - $promotion_amount;
			    $final_amount 		= $result[0]['ReturnAmount'];
			    $insert_query ="INSERT INTO promotiondetail_temp
					(routekey,visitkey,transactionkey,itemtransactiontype,itemcode,promotiontypecode,
					promotionamount,oldpromotionamount,promotionplannumber,assignmentkey,weighted,record_flag,
					salesamount,rangelow,repeatingrange,promotionper) 
				    values(".$data['route_key'].",".$data['visit_key'].",".$data['transactionkey'].",'2','".$result[0]['itemcode']."',5,
				    ".$range_arr[0]['promotionamount'].",".$result[0]['ReturnAmount'].",".$promot5val['plannumber'].",".$promot5val['assignment_no'].",
				    '0','1',".$final_amount.",".$range_arr[0]['rangelow'].",
				    ".$range_arr[0]['repeatingrange'].",".$per_value."
				    )";
				    //echo $insert_query;exit;
			     $this->_db->query($insert_query);
			}
			else
			{
			    $per_value  		= $range_arr[0]['promotionamount'];
			    /**
                 *  Developed By Pankil :- Comment below line for the issue of qulified amount was wrong
                 */
                //$final_amount 		= $result[0]['ReturnAmount'] - $range_arr[0]['promotionamount'];
                $final_amount 		= $result[0]['ReturnAmount'];
			    $insert_query ="INSERT INTO promotiondetail_temp
					    (routekey,visitkey,transactionkey,itemtransactiontype,itemcode,promotiontypecode,
					    promotionamount,oldpromotionamount,promotionplannumber,assignmentkey,weighted,record_flag,
					    salesamount,rangelow,repeatingrange,promotionper) 
					values(".$data['route_key'].",".$data['visit_key'].",".$data['transactionkey'].",'2','".$result[0]['itemcode']."',5,
					".$range_arr[0]['promotionamount'].",".$result[0]['ReturnAmount'].",".$promot5val['plannumber'].",".$promot5val['assignment_no'].",
					'0','1',".$final_amount.",".$range_arr[0]['rangelow'].",
					".$range_arr[0]['repeatingrange'].",".$per_value."
					)";
					//echo $insert_query;
				 $this->_db->query($insert_query);
			}
			
		    }
		    
		    $range_arr =array();
		}
		
		//echo "ddd";exit;
	    }
	   
	    if(in_array("6",$promotiontypecode))
	    {
		
		
		foreach($promotion6 as $promot6val)
		{
		    $final_amount =0;
            if($promot6val['qualification_group'] == 1) {
                $select = "SELECT GROUP_CONCAT(invoicedetail_temp.itemcode) as itemcode,
                                    FORMAT(SUM( IF(return_amount = '0.0000', (FLOOR(returnqty / itemmaster.unitspercase) *
                                    invoicedetail_temp.goodreturncaseprice
                                    + ((returnqty % itemmaster.unitspercase) * invoicedetail_temp.goodreturnprice)) ,return_amount)),4) AS ReturnAmount,
                                    SUM(IFNULL(invoicedetail_temp.returnqty,0)) AS ReturnQty
                            FROM invoicedetail_temp
                            INNER JOIN itemmaster ON itemmaster.actualitemcode  = invoicedetail_temp.itemcode
                                                   
                            WHERE 		 invoicedetail_temp.routekey =".$data['route_key'] ."
                                     AND invoicedetail_temp.visitkey  =".$data['visit_key'];
            } else {
                $select =  "SELECT GROUP_CONCAT(productgroupdetail.itemcode) AS itemcode,
                                    FORMAT(SUM( IF(return_amount = '0.0000', (FLOOR(returnqty / itemmaster.unitspercase) *
                                    invoicedetail_temp.goodreturncaseprice
                                    + ((returnqty % itemmaster.unitspercase) * invoicedetail_temp.goodreturnprice)) ,return_amount)),4) AS ReturnAmount,
                                    SUM(IFNULL(invoicedetail_temp.returnqty,0)) AS ReturnQty
                            FROM promokeydetail 
                            INNER JOIN productgroupdetail ON  promokeydetail.assignmentgroup = productgroupdetail.groupnumber 
                            INNER JOIN invoicedetail_temp ON productgroupdetail.itemcode = invoicedetail_temp.itemcode
                                AND invoicedetail_temp.routekey = ".$data['route_key'] ."
                                AND invoicedetail_temp.visitkey  = ".$data['visit_key']." 
                            INNER JOIN itemmaster ON itemmaster.actualitemcode = productgroupdetail.itemcode 
                            WHERE
                                promokeydetail.assignmentnumber = ".$promot6val['assignment_no']." AND
                                promokeydetail.promotionkey = (SELECT promotionkey FROM customermaster 
                                                                    WHERE customercode = " .$data['customer_code']. ")
                            AND promokeydetail.plannumber = ".$promot6val['plannumber']." 
                            GROUP BY promokeydetail.plannumber";
            }
            
            $result = $this->getAdapter()->fetchAll($select);
            $result[0]['ReturnQty'] = str_replace(",","",$result[0]['ReturnQty']);
            $result[0]['ReturnAmount'] = str_replace(",","",$result[0]['ReturnAmount']);
            $data['return_qnt_org'] = ($data['return_qnt_org'] != "") ? str_replace(",","",$data['return_qnt_org']): 0;
            $data['return_amt_org'] = ($data['return_amt_org'] != "") ? str_replace(",","",$data['return_amt_org']): 0;
            if($final_amount == 0 )
                $final_amount = $result[0]['ReturnAmount'];
            
		    if(isset($promot6val['ragebasis']) && ($promot6val['ragebasis'] == 1))
			{
			       $select_range ="SELECT promotionamount ,rangelow,rangehigh,repeatingrange
					     FROM promotionassignmentadvanced WHERE assignmentnumber =".$promot6val['assignment_no']." 
					     AND IF(repeatingrange = 0 ,  ".str_replace(",","",$data['return_qnt_org'])."  BETWEEN rangelow AND rangehigh ,
					     ".str_replace(",","",$data['return_qnt_org'])."  >= rangelow ) ";
			}
			else
			{
			     $select_range ="SELECT promotionamount ,rangelow,rangehigh,repeatingrange
					     FROM promotionassignmentadvanced WHERE assignmentnumber =".$promot6val['assignment_no']." 
					     AND IF(repeatingrange = 0 ,  ".str_replace(",","",$data['return_amt_org'])."  BETWEEN rangelow AND rangehigh ,
					     ".str_replace(",","",$data['return_amt_org'])."  >= rangelow ) ";
			}
			
			
			
			
			$range_arr = $this->getAdapter()->fetchAll($select_range);
			//print_r($range_arr);
			if(!empty($range_arr))
			{
			    $promotion_amount = "";
			    $prmotion_cal = "";
			    $prmotion_calculate_arr =array();
			    
			    if($range_arr[0]['repeatingrange'] == 1)
			    {
				$per_value  		= $range_arr[0]['promotionamount'];
				$prmotion_cal 		= $final_amount/$range_arr[0]['rangelow'];
				$prmotion_calculate_arr = explode(".",$prmotion_cal);
				$promotion_amount 	= $range_arr[0]['rangelow'] * ($prmotion_calculate_arr[0] * ($range_arr[0]['promotionamount']/100));
				/**
                 *  Developed By Pankil :- Comment below line for the issue of qulified amount was wrong
                 */
				//$final_amount1 = $final_amount -  $promotion_amount;
                $final_amount1 = $final_amount;
			 
				$range_arr[0]['promotionamount'] = $promotion_amount;
			 
				$insert_query ="INSERT INTO promotiondetail_temp
					       (routekey,visitkey,transactionkey,itemtransactiontype,itemcode,promotiontypecode,
					       promotionamount,oldpromotionamount,promotionplannumber,assignmentkey,weighted,record_flag,
					       salesamount,rangelow,repeatingrange,promotionper) 
					   values(".$data['route_key'].",".$data['visit_key'].",".$data['transactionkey'].",'2','".$result[0]['itemcode']."',6,
					   ".$range_arr[0]['promotionamount'].",".$final_amount.",".$promot6val['plannumber'].",".$promot6val['assignment_no'].",
					   '0','1',".$final_amount1.",".$range_arr[0]['rangelow'].",
					   ".$range_arr[0]['repeatingrange'].",".$per_value."
					   )";
					  //echo $insert_query."3<br />";
				    $this->_db->query($insert_query);
			    }
			    else
			    {
				$per_value  		= $range_arr[0]['promotionamount'];
				$prmotion_cal 		= ($final_amount * $range_arr[0]['promotionamount']/100);
				/**
                 *  Developed By Pankil :- Comment below line for the issue of qulified amount was wrong
                 */
                //$final_amount1 = $final_amount - $prmotion_cal;
                $final_amount1 = $final_amount;
			 
				$range_arr[0]['promotionamount'] = $prmotion_cal;
				 
				 $insert_query ="INSERT INTO promotiondetail_temp
						    (routekey,visitkey,transactionkey,itemtransactiontype,itemcode,promotiontypecode,
						    promotionamount,oldpromotionamount,promotionplannumber,assignmentkey,weighted,record_flag,
						    salesamount,rangelow,repeatingrange,promotionper) 
						values(".$data['route_key'].",".$data['visit_key'].",".$data['transactionkey'].",'2','".$result[0]['itemcode']."',6,
						".$range_arr[0]['promotionamount'].",".$final_amount.",".$promot6val['plannumber'].",".$promot6val['assignment_no'].",
						'0','1',".$final_amount1.",".$range_arr[0]['rangelow'].",
						".$range_arr[0]['repeatingrange'].",".$per_value."
						)";
						//echo $insert_query."4<br />";
					 $this->_db->query($insert_query);
			    }
			    
			 $range_arr =array();
			 $final_amount = $final_amount1;
			}
			
		    
		    
		}
		
		
	    }
	  
	    $update_query ="update invoiceheader_temp set totalreturnpromoamount = (SELECT SUM(promotionamount) FROM promotiondetail_temp where promotiondetail_temp.itemtransactiontype =2 and  promotiondetail_temp.routekey =".$data['route_key'] ." AND 
			promotiondetail_temp.visitkey = ".$data['visit_key']." AND promotiondetail_temp.transactionkey=".$data['transactionkey']." )
	    where invoiceheader_temp.transactionkey  =".$data['transactionkey']." and visitkey = ".$data['visit_key'];
	    
	  // echo $update_query;exit;
	     $this->_db->query($update_query);
	     
	    
	    
	   	  
	//}
    }
  /**
   * @name       seven_promotion
   * @since      06-08-2012
   * @version    Release: 8
   * @author     GP
   * @copyright  Elan Technologies
   * Apply seven promotion. free quantity
   *
   */
     
     
    public function seven_promotion($data=array())
    {
	/*
	 IF(return_amount = '0.0000', FORMAT(((returnqty / itemmaster.unitspercase) *
			IF(p.goodreturncaseprice IS NULL , itemmaster.caseprice,  p.goodreturncaseprice) 
			+ ((returnqty % itemmaster.unitspercase) * invoicedetail_temp.goodreturnprice)),4) ,return_amount)AS ReturnAmount,
			productgroupdetail.itemcode, IFNULL(invoicedetail_temp.returnqty,0) AS ReturnQty
	*/
	$select  ="    SELECT  productgroupdetail.itemcode as itemcode,
			IFNULL(FORMAT(( IF(return_amount = '0.0000', ((returnqty / itemmaster.unitspercase) *
			invoicedetail_temp.goodreturncaseprice
		+ ((returnqty % itemmaster.unitspercase) * invoicedetail_temp.goodreturnprice)) ,return_amount)),4),0) AS ReturnAmount,
		(IFNULL(invoicedetail_temp.returnqty,0)) AS ReturnQty
			FROM promokeydetail
			INNER JOIN productgroupdetail ON  promokeydetail.assignmentgroup = productgroupdetail.groupnumber 
			LEFT JOIN invoicedetail_temp ON productgroupdetail.itemcode = invoicedetail_temp.itemcode 
    				 AND invoicedetail_temp.routekey =".$data['route_key'] ."
				 AND invoicedetail_temp.visitkey  =".$data['visit_key']." and
				 invoicedetail_temp.returnqty != 0
				INNER JOIN itemmaster ON itemmaster.actualitemcode = productgroupdetail.itemcode 
				
			WHERE promokeydetail.assignmentnumber = ".$data['assignment_no']." 
			AND promokeydetail.promotionkey = (SELECT promotionkey FROM customermaster WHERE customercode = ".$data['customer_code'].") 
		       AND promokeydetail.plannumber = ".$data['plannumber'] ;
		       
		     //echo $select;exit;
	$result = $this->getAdapter()->fetchAll($select);
	if(!empty($result))
	{
	    foreach($result as $value)
	    {
		$data['return_qnt_org'] = ($data['return_qnt_org'] != "") ? str_replace(",","",$data['return_qnt_org']): 0;
		$data['return_amt_org'] = ($data['return_amt_org'] != "") ? str_replace(",","",$data['return_amt_org']): 0;
		$range_arr =array();
		if(isset($data['ragebasis']) && ($data['ragebasis'] == 1))
		{
		       $select_range ="SELECT promotionamount ,rangelow,rangehigh,repeatingrange
				     FROM promotionassignmentadvanced WHERE assignmentnumber =".$data['assignment_no']." 
				     AND IF(repeatingrange = 0 ,  ".str_replace(",","",$data['return_qnt_org'])."  BETWEEN rangelow AND rangehigh ,
				     ".str_replace(",","",$data['return_qnt_org'])."  >= rangelow ) ";
		}
		else
		{
		     $select_range ="SELECT promotionamount ,rangelow,rangehigh,repeatingrange
				     FROM promotionassignmentadvanced WHERE assignmentnumber =".$data['assignment_no']." 
				     AND IF(repeatingrange = 0 ,  ".str_replace(",","",$data['return_amt_org'])."  BETWEEN rangelow AND rangehigh ,
				     ".str_replace(",","",$data['return_amt_org'])."  >= rangelow ) ";
		}
		
		$range_arr = $this->getAdapter()->fetchAll($select_range);
		
		if(!empty($range_arr))
		{
			$promotion_amount = "";
			$prmotion_cal = "";
			$prmotion_calculate_arr =array();
			$promotion_amount ="";
			$final_amount ="";
			// By Hiren Dave remove Comma(,) from sales & return amount for fixing insert issue.
			$value['ReturnAmount'] = str_replace(',','',$value['ReturnAmount']);
			if($range_arr[0]['repeatingrange'] == 1)
			    {
				
				$prmotion_cal 		= $data['return_qnt_org']/$range_arr[0]['rangelow'];
				$prmotion_calculate_arr = explode(".",$prmotion_cal);
				$promotion_amount 	=  ($prmotion_calculate_arr[0] * $range_arr[0]['promotionamount']);
				$range_arr[0]['promotionamount']  =$promotion_amount ;
				
				 $insert_query ="INSERT INTO promotiondetail_temp
				    (routekey,visitkey,transactionkey,itemtransactiontype,itemcode,promotiontypecode,
				    promotionquantity,oldpromotionamount,promotionplannumber,assignmentkey,weighted,record_flag,
				    salesamount,rangelow,repeatingrange) 
				values(".$data['route_key'].",".$data['visit_key'].",".$data['transactionkey'].",'2','".$value['itemcode']."',7,
				".round($range_arr[0]['promotionamount']).",".$value['ReturnAmount'].",".$data['plannumber'].",".$data['assignment_no'].",
				'0','1',".($value['ReturnAmount']).",".$range_arr[0]['rangelow'].",
				".$range_arr[0]['repeatingrange']."
				)";
				
			 $this->_db->query($insert_query);
				
			    }
			    else
			    {
				 $insert_query ="INSERT INTO promotiondetail_temp
				    (routekey,visitkey,transactionkey,itemtransactiontype,itemcode,promotiontypecode,
				    promotionquantity,oldpromotionamount,promotionplannumber,assignmentkey,weighted,record_flag,
				    salesamount,rangelow,repeatingrange) 
				values(".$data['route_key'].",".$data['visit_key'].",".$data['transactionkey'].",'2','".$value['itemcode']."',7,
				".round($range_arr[0]['promotionamount']).",".$value['ReturnAmount'].",".$data['plannumber'].",".$data['assignment_no'].",
				'0','1',".($value['ReturnAmount']).",".$range_arr[0]['rangelow'].",
				".$range_arr[0]['repeatingrange']."
				)";
				
			 $this->_db->query($insert_query);
			    }
		   
		
		    //update Invoice detail
		  /*  $invoice_detail ="UPDATE invoicedetail_temp  SET promoqty = ifnull(promoqty,0) + ".round($range_arr[0]['promotionamount'])."
							WHERE routekey = ".$data['route_key']." AND visitkey = ".$data['visit_key']."
							AND itemcode = ".$value['itemcode'];
							//echo $invoice_detail;exit;
							echo $invoice_detail;exit;
			$this->_db->query($invoice_detail);*/
		}
		
	    }
	   	  
	}
	
	
    }
  /**
   * @name       remove_promotion_free_quantity
   * @since      06-08-2012
   * @version    Release: 8
   * @author     GP
   * @copyright  Elan Technologies
   * remove promotion quantity data
   *
   */
    
    public function remove_promotion_free_quantity($data=array())
    {
	$update_query = "UPDATE  invoicedetail_temp SET freesampleqty ='0',promoqty ='0',returnfreesampleqty ='0'
		    WHERE invoicedetail_temp.routekey = ".$data['route_key'] ."	AND invoicedetail_temp.visitkey = ".$data['visit_key']." and
		    invoicedetail_temp.transactionkey =".$data['transactionkey'];
		    $this->_db->query($update_query);
            
	$delete_query = "DELETE
                        FROM invoicedetail_temp 
                        WHERE 
                            (salesqty = 0 OR salesqty IS NULL OR salesqty = '')
                            AND (returnqty = 0 OR returnqty IS NULL OR returnqty = '')
                            AND (damagedqty = 0 OR damagedqty IS NULL OR damagedqty = '')
                            AND (expiryqty = 0 OR expiryqty IS NULL OR expiryqty = '')
                            AND (freesampleqty = 0 OR freesampleqty IS NULL OR freesampleqty = '')
                            AND (promoqty = 0 OR promoqty IS NULL OR promoqty = '')
                            AND (returnfreeqty = 0 OR returnfreeqty IS NULL OR returnfreeqty = '')
                            AND (manualfreeqty = 0 OR manualfreeqty IS NULL OR manualfreeqty = '')
                            AND (limitedfreeqty = 0 OR limitedfreeqty IS NULL OR limitedfreeqty = '')
                            AND (fixedrentqty = 0 OR fixedrentqty IS NULL OR fixedrentqty = '')
                            AND (returnfreesampleqty = 0 OR returnfreesampleqty IS NULL OR returnfreesampleqty = '')
                            AND invoicedetail_temp.routekey = ".$data['route_key'] ."	AND invoicedetail_temp.visitkey = ".$data['visit_key']." and
                            invoicedetail_temp.transactionkey =".$data['transactionkey'];
                            
        $this->_db->query($delete_query);
    }
  /**
   * @name       free_promotion_update
   * @since      06-08-2012
   * @version    Release: 8
   * @author     GP
   * @copyright  Elan Technologies
   * Update promotion quantity
   *
   */
     public function free_promotion_update($data)
     {
	$update_query = "UPDATE  invoicedetail_temp SET returnfreesampleqty = returnfreesampleqty + ".$data['qty']."
			  
		    WHERE invoicedetail_temp.routekey = ".$data['route_key'] ."	AND invoicedetail_temp.visitkey = ".$data['visit_key']." and
		    invoicedetail_temp.transactionkey =".$data['transactionkey']." and itemcode ='".$data['item_code']."'";
		    $this->_db->query($update_query);
		    
	
    }
  
  
   /**
   * Date : 10 Dec 2012
   * Pankil thakkar
   * Desc : for checking range
   *
   */
    public function checkrange($data=array())
    {   
	    if(isset($data['range']) && ($data['range'] == 1))
		{
            $select_range ="SELECT promotionamount ,rangelow,rangehigh,repeatingrange
                  FROM promotionassignmentadvanced WHERE assignmentnumber =".$data['assignment_no']." 
                  AND IF(repeatingrange = 0 ,  ".str_replace(",","",$data['ReturnQty'])."  BETWEEN rangelow AND rangehigh ,
                  ".str_replace(",","",$data['ReturnQty'])."  >= rangelow ) ";
		}
		else
		{
            $select_range ="SELECT promotionamount ,rangelow,rangehigh,repeatingrange,".str_replace(",","",$data['ReturnAmount'])." as amount
                    FROM promotionassignmentadvanced WHERE assignmentnumber =".$data['assignment_no']." 
                    AND IF(repeatingrange = 0 ,  ".str_replace(",","",$data['ReturnAmount'])."  BETWEEN rangelow AND rangehigh ,
                    ".str_replace(",","",$data['ReturnAmount'])."  >= rangelow ) ";
		}
	    $result = $this->getAdapter()->fetchAll($select_range);
        return $result;
    }
      /**
   * Date : 10 Dec 2012
   * Pankil thakkar
   * Desc : for checking range
   *
   */
    public function seven_promotion_fixed($data=array())
    {
        if($data["repeatingrange_val"] == "0"){
            $pqty = "productgroupdetail.itemqty";
        } else {
            if($data["ragebasis"] == "4"){
                $pqty = "(".$data["return_promotion_amt"]." *productgroupdetail.itemqty)";
            } else {
                $pqty = "(".$data["promotion_fixed_amt"]." *productgroupdetail.itemqty)";
            }
            
        }
        
	    $select = "select productgroupdetail.itemcode,".$pqty." As NewInvoiceAmount, (itemqty * itemmaster.defaultreturnprice) As ReturnAmount,ifnull(itemqty,0) As   ReturnQty,
                    (itemqty * productgroupdetail.promopcprice) As promoamount,promocaseprice,promopcprice
                from promokeydetail
                inner join productgroupdetail
                    on  promokeydetail.assignmentgroup = productgroupdetail.groupnumber
                inner join inventorysummarydetail
                    on productgroupdetail.itemcode = inventorysummarydetail.itemcode and inventorysummarydetail.routekey = " .$data["route_key"]. "
                inner join itemmaster
                    on itemmaster.actualitemcode = productgroupdetail.itemcode
                where promokeydetail.assignmentnumber = " .$data["assignment_no"]. "
                    and promokeydetail.promotionkey = (select promotionkey from customermaster where customercode = " .$data["customer_code"]. ")
                and promokeydetail.plannumber = " .$data["plannumber"];
        $result = $this->getAdapter()->fetchAll($select);
        
        $insert_query ="INSERT INTO promotiondetail_temp
                                (routekey,visitkey,transactionkey,itemtransactiontype,itemcode,promotiontypecode,
                                promotionquantity,oldpromotionamount,promotionplannumber,assignmentkey,weighted,record_flag,
                                salesamount,rangelow,repeatingrange) 
                        values(".$data['route_key'].",".$data['visit_key'].",".$data['transactionkey'].",'2','".$result[0]["itemcode"]."',7,
                        ".round($result[0]["NewInvoiceAmount"]).",".$result[0]['ReturnAmount'].",".$data['plannumber'].",".$data['assignment_no'].",
                        '0','1',".($result[0]['ReturnAmount']).",0,
                        ".$data["repeatingrange_val"]."
                        )";
        
        $this->_db->query($insert_query);
        
    }
}