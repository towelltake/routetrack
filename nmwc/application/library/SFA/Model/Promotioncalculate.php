<?php
/**
 * @name       SFA_Model_User
 * @since      19-09-2011
 * @version    Release: 3
 * @author     GP
 * @copyright  Elan Technologies
 * @param   	
 * This Class contains all the Promtotion and invoice realate function
 */

class SFA_Model_Promotioncalculate extends Zend_Db_Table_Abstract
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
                and itemtransactiontype =1
                ";
                
        //echo $remove_query;		exit;
        $this->_db->query($remove_query);
        
        $update_query = "UPDATE  invoicedetail_temp SET sales_amount ='0' ,promoamount ='0',promoqty = '0',freesampleqty = '0', promovalue = '0',
                                                        returnpromovalue = '0', returnpromoamount = '0', returnfreesampleqty = '0'
                WHERE invoicedetail_temp.routekey = ".$data['route_key'] ." AND invoicedetail_temp.visitkey = ".$data['visit_key']." and
                invoicedetail_temp.transactionkey =".$data['transactionkey'];
            //echo $update_query;		exit;
                $this->_db->query($update_query);
        
        $this->remove_promotion_free_quantity($data);
    }
   /**
    * @name       generate_invoice_number
    * @since      06-08-2012
    * @version    Release: 8
    * @author     GP
    * @copyright  Elan Technologies
    * @param   	 Autogenerate invoice nuber and document number for display only
    *
    */
    public function generate_invoice_number($data=array())
    {
	//IFNULL(boinvseq,0) AS  invoice_seq, IFNULL(bodocseq,0) AS document_seq, IFNULL(boordseq,0) AS order_seq
	$select1 = "SELECT get_number_type_base('".$data['route_code']."',1) as invoice_seq,
			get_number_type_base('".$data['route_code']."',2) as document_seq";
	$result1 = $this->getAdapter()->fetchAll($select1);
	
	return $result1;
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
	$select="SELECT IFNULL(SUM(invoicedetail_temp.salesqty),0) AS SalesQty,
	SUM(((salesqty / itemmaster.unitspercase)  *
	( invoicedetail_temp.salescaseprice )) +
	((salesqty % itemmaster.unitspercase) * invoicedetail_temp.salesprice)) AS SalesAmount 
	FROM promokeydetail
	
	INNER JOIN productgroupdetail ON  promokeydetail.qualificationgroup = productgroupdetail.groupnumber 
	INNER JOIN invoicedetail_temp ON productgroupdetail.itemcode = invoicedetail_temp.itemcode
	AND invoicedetail_temp.routekey =".$data['route_key'] ."
	AND invoicedetail_temp.visitkey =".$data['visit_key']."
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
//echo $select;

	$result = $this->getAdapter()->fetchAll($select);
	
	if(!empty($result))
	{
	   foreach($result as $value)
	    {
		$value['SalesQty'] = str_replace(",","",$value['SalesQty']);
		$value['SalesAmount'] = str_replace(",","",$value['SalesAmount']);
        $data['sales_qnt_org'] = ($data['sales_qnt_org'] != "") ? str_replace(",","",$data['sales_qnt_org']):0;
        $data['sales_amt_org'] = ($data['sales_amt_org'] != "") ? str_replace(",","",$data['sales_amt_org']):0;
		$range_arr =array();
		if(isset($data['ragebasis']) && ($data['ragebasis'] == 1))
		{
		       $select_range ="SELECT promotionamount ,rangelow,rangehigh,repeatingrange
				     FROM promotionassignmentadvanced WHERE assignmentnumber =".$data['assignment_no']." 
				     AND IF(repeatingrange = 0 ,  ".str_replace(",","",$data['sales_qnt_org'])." BETWEEN rangelow AND rangehigh ,
				     ".str_replace(",","",$data['sales_qnt_org'])."  >= rangelow ) ";
		}
		else
		{
		     $select_range ="SELECT promotionamount ,rangelow,rangehigh,repeatingrange
				     FROM promotionassignmentadvanced WHERE assignmentnumber =".$data['assignment_no']." 
				     AND IF(repeatingrange = 0 ,  ".str_replace(",","",$data['sales_amt_org'])." BETWEEN rangelow AND rangehigh ,
				     ".str_replace(",","",$data['sales_amt_org'])."  >= rangelow ) ";
		}
		
		$range_arr = $this->getAdapter()->fetchAll($select_range);
		if(!empty($range_arr))
		{
		    
		    $insert_query ="INSERT INTO promotiondetail_temp
				    (routekey,visitkey,transactionkey,itemtransactiontype,itemcode,promotiontypecode,
				    promotionamount,oldpromotionamount,promotionplannumber,assignmentkey,weighted,record_flag,
				    salesamount,rangelow,repeatingrange)
				    
				   SELECT ".$data['route_key'].",".$data['visit_key'].",".$data['transactionkey'].",1, productgroupdetail.itemcode,0,
				   (((salesqty / itemmaster.unitspercase)  *
					    (invoicedetail_temp.salescaseprice)) +
					    ((salesqty % itemmaster.unitspercase)  * invoicedetail_temp.salesprice) -
					    ((salesqty / itemmaster.unitspercase) *  productgroupdetail.promocaseprice) +
					    ((salesqty % itemmaster.unitspercase) * productgroupdetail.promopcprice)) AS promoamount, 
				 REPLACE(FORMAT((((salesqty / itemmaster.unitspercase)  *
				     (invoicedetail_temp.salescaseprice)) +
				     ((salesqty % itemmaster.unitspercase)  * invoicedetail_temp.salesprice)),2),',','') AS SalesAmount,
				  ".$data['plannumber'].",".$data['assignment_no'].",'0','1',
				    REPLACE(FORMAT( (((salesqty / itemmaster.unitspercase)  *
				    (IF (invoicedetail_temp.salescaseprice IS NULL, itemmaster.caseprice ,invoicedetail_temp.salescaseprice)))
				    +  ((salesqty % itemmaster.unitspercase)  * invoicedetail_temp.salesprice)),2),',','') AS SalesAmount,
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
				
			//echo $insert_query;	
			 $this->_db->query($insert_query);
			
			$update_query= "UPDATE promotiondetail_temp set salesamount = oldpromotionamount - promotionamount WHERE
			    promotiondetail_temp.routekey = ".$data['route_key']." and 
			    promotiondetail_temp.visitkey = ".$data['visit_key']." and
			    promotiondetail_temp.transactionkey = ".$data['transactionkey']." and
			    promotiondetail_temp.promotiontypecode= '0' ";
			    
			    $this->_db->query($update_query);
			    
			$update_query=  "UPDATE invoicedetail_temp  ,promotiondetail_temp
					 SET invoicedetail_temp.promoamount = IFNULL(invoicedetail_temp.promoamount,0) + promotiondetail_temp.promotionamount,
					 promovalue = IFNULL(promovalue,0) + promotiondetail_temp.promotionamount ,
					 sales_amount = promotiondetail_temp.salesamount
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
	$select  ="    SELECT  IF(sales_amount = '0.0000', FORMAT(((salesqty / itemmaster.unitspercase) *
			invoicedetail_temp.salescaseprice
			+ ((salesqty % itemmaster.unitspercase) * invoicedetail_temp.salesprice)),4) ,sales_amount)AS SalesAmount,
			productgroupdetail.itemcode, IFNULL(invoicedetail_temp.salesqty,0) AS SalesQty
			
			FROM promokeydetail
			INNER JOIN productgroupdetail ON  promokeydetail.assignmentgroup = productgroupdetail.groupnumber 
			INNER JOIN invoicedetail_temp ON productgroupdetail.itemcode = invoicedetail_temp.itemcode 
    				 AND invoicedetail_temp.routekey =".$data['route_key'] ."
				 AND invoicedetail_temp.visitkey  =".$data['visit_key']."
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
		$value['SalesQty'] = str_replace(",","",$value['SalesQty']);
		$value['SalesAmount'] = str_replace(",","",$value['SalesAmount']);
        $data['sales_qnt_org'] = ($data['sales_qnt_org'] != "") ? str_replace(",","",$data['sales_qnt_org']):0;
        $data['sales_amt_org'] = ($data['sales_amt_org'] != "") ? str_replace(",","",$data['sales_amt_org']):0;
		$range_arr =array();
		if(isset($data['ragebasis']) && ($data['ragebasis'] == 1))
		{
		       $select_range ="SELECT promotionamount ,rangelow,rangehigh,repeatingrange
				     FROM promotionassignmentadvanced WHERE assignmentnumber =".$data['assignment_no']." 
				     AND IF(repeatingrange = 0 ,  ".str_replace(",","",$data['sales_qnt_org'])."  BETWEEN rangelow AND rangehigh ,
				     ".str_replace(",","",$data['sales_qnt_org'])." >= rangelow ) ";
		}
		else
		{
		     $select_range ="SELECT promotionamount ,rangelow,rangehigh,repeatingrange
				     FROM promotionassignmentadvanced WHERE assignmentnumber =".$data['assignment_no']." 
				     AND IF(repeatingrange = 0 ,  ".str_replace(",","",$data['sales_amt_org'])." BETWEEN rangelow AND rangehigh ,
				     ".str_replace(",","",$data['sales_amt_org'])."  >= rangelow ) ";
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
			
			$prmotion_cal 		= $data['sales_amt_org']/$range_arr[0]['rangelow'];
			$prmotion_calculate_arr = explode(".",$prmotion_cal);
			$promotion_amount 	=  ($prmotion_calculate_arr[0] * $range_arr[0]['promotionamount']);
			
			$insert_query ="INSERT INTO promotiondetail_temp
				    (routekey,visitkey,transactionkey,itemtransactiontype,itemcode,promotiontypecode,
				    promotionamount,oldpromotionamount,promotionplannumber,assignmentkey,weighted,record_flag,
				    salesamount,rangelow,repeatingrange) 
				values(".$data['route_key'].",".$data['visit_key'].",".$data['transactionkey'].",'1',".$value['itemcode'].",1,
				".$promotion_amount.",".$value['SalesAmount'].",".$data['plannumber'].",".$data['assignment_no'].",
				'0','1',".($value['SalesAmount'] - $promotion_amount).",".$range_arr[0]['rangelow'].",
				".$range_arr[0]['repeatingrange']."
				)";
			//echo $insert_query;exit;
            	$this->_db->query($insert_query);
				
				//update Invoice detail
			$invoice_detail ="UPDATE invoicedetail_temp  SET promoamount = promoamount + ".$promotion_amount.",
							    promovalue = promovalue + ".$promotion_amount.",
							    sales_amount ='".($value['SalesAmount'] - $promotion_amount)."'
							    
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
				values(".$data['route_key'].",".$data['visit_key'].",".$data['transactionkey'].",'1',".$value['itemcode'].",1,
				".$range_arr[0]['promotionamount'].",".$value['SalesAmount'].",".$data['plannumber'].",".$data['assignment_no'].",
				'0','1',".($value['SalesAmount'] - $range_arr[0]['promotionamount']).",".$range_arr[0]['rangelow'].",
				".$range_arr[0]['repeatingrange']."
				)";
				
			 $this->_db->query($insert_query);
			
			//update Invoice detail
			$invoice_detail ="UPDATE invoicedetail_temp  SET promoamount = promoamount + ".$range_arr[0]['promotionamount'].",
							    promovalue = promovalue + ".$range_arr[0]['promotionamount'].",
							    sales_amount ='".($value['SalesAmount'] - $range_arr[0]['promotionamount'])."'
							    
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
	$select  ="    SELECT  IF(sales_amount = '0.0000', FORMAT(((salesqty / itemmaster.unitspercase) *
			invoicedetail_temp.salescaseprice 
			+ ((salesqty % itemmaster.unitspercase) * invoicedetail_temp.salesprice)),4) ,sales_amount)AS SalesAmount,
			productgroupdetail.itemcode, IFNULL(invoicedetail_temp.salesqty,0) AS SalesQty
			
			FROM promokeydetail
			INNER JOIN productgroupdetail ON  promokeydetail.assignmentgroup = productgroupdetail.groupnumber 
			INNER JOIN invoicedetail_temp ON productgroupdetail.itemcode = invoicedetail_temp.itemcode 
    				 AND invoicedetail_temp.routekey =".$data['route_key'] ."
				 AND invoicedetail_temp.visitkey  =".$data['visit_key']."
				INNER JOIN itemmaster ON itemmaster.actualitemcode = productgroupdetail.itemcode 
				
			WHERE promokeydetail.assignmentnumber = ".$data['assignment_no']." 
			AND promokeydetail.promotionkey = (SELECT promotionkey FROM customermaster WHERE customercode = ".$data['customer_code'].") 
		       AND promokeydetail.plannumber = ".$data['plannumber'] ;
		       
		
	$result = $this->getAdapter()->fetchAll($select);
	if(!empty($result))
	{
	    foreach($result as $value)
	    {
		$value['SalesQty'] = str_replace(",","",$value['SalesQty']);
		$value['SalesAmount'] = str_replace(",","",$value['SalesAmount']);
        $data['sales_qnt_org'] = ($data['sales_qnt_org'] != "") ? str_replace(",","",$data['sales_qnt_org']):0;
        $data['sales_amt_org'] = ($data['sales_amt_org'] != "") ? str_replace(",","",$data['sales_amt_org']):0;
		$range_arr =array();
		if(isset($data['ragebasis']) && ($data['ragebasis'] == 1))
		{
		       $select_range ="SELECT promotionamount ,rangelow,rangehigh,repeatingrange
				     FROM promotionassignmentadvanced WHERE assignmentnumber =".$data['assignment_no']." 
				     AND IF(repeatingrange = 0 ,  ".str_replace(",","",$data['sales_qnt_org'])." BETWEEN rangelow AND rangehigh ,
				     ".str_replace(",","",$data['sales_qnt_org'])."  >= rangelow ) ";
		}
		else
		{
		     $select_range ="SELECT promotionamount ,rangelow,rangehigh,repeatingrange
				     FROM promotionassignmentadvanced WHERE assignmentnumber =".$data['assignment_no']." 
				     AND IF(repeatingrange = 0 ,   ".str_replace(",","",$data['sales_amt_org'])."  BETWEEN rangelow AND rangehigh ,
				     ".str_replace(",","",$data['sales_amt_org'])."  >= rangelow ) ";
		}
		
		$range_arr = $this->getAdapter()->fetchAll($select_range);
		//print_r($range_arr);
		if(!empty($range_arr))
		{
		    $promotion_amount = "";
		    $prmotion_cal = "";
		    $prmotion_calculate_arr =array();
		    $promotion ="";
            //pr($range_arr);
		     if($range_arr[0]['repeatingrange'] == 1)
		    {
			
			$prmotion_cal 		= $value['SalesAmount']/$range_arr[0]['rangelow'];
			$prmotion_calculate_arr = explode(".",$prmotion_cal);
			//pr($prmotion_calculate_arr,1);
            $promotion_amount 	=  $range_arr[0]['rangelow'] * ($prmotion_calculate_arr[0] * ($range_arr[0]['promotionamount']/100));
			$promotion = $promotion_amount;
			//$promotion  = (($value['SalesAmount'] * $promotion_amount ));
			//$promotion  = (($value['SalesAmount'] * $range_arr[0]['promotionamount']) )/$prmotion_calculate_arr[0] ;
			$range_arr[0]['promotionamount'] = $promotion;
			$insert_query ="INSERT INTO promotiondetail_temp
					(routekey,visitkey,transactionkey,itemtransactiontype,itemcode,promotiontypecode,
					promotionamount,oldpromotionamount,promotionplannumber,assignmentkey,weighted,record_flag,
					salesamount,rangelow,repeatingrange) 
				    values(".$data['route_key'].",".$data['visit_key'].",".$data['transactionkey'].",'1',".$value['itemcode'].",2,
				    ".$range_arr[0]['promotionamount'].",".$value['SalesAmount'].",".$data['plannumber'].",".$data['assignment_no'].",
				    '0','1',".($value['SalesAmount'] - $range_arr[0]['promotionamount']).",".$range_arr[0]['rangelow'].",
				    ".$range_arr[0]['repeatingrange']."
				    )";
				    //echo $insert_query;
				    
			     $this->_db->query($insert_query);
			    
			//update Invoice detail
			$invoice_detail ="UPDATE invoicedetail_temp  SET promoamount = promoamount + ".$range_arr[0]['promotionamount'].",
							    promovalue = promovalue + ".$range_arr[0]['promotionamount'].",
							    sales_amount ='".($value['SalesAmount'] - $range_arr[0]['promotionamount'])."'
							    
							    WHERE routekey = ".$data['route_key']." AND visitkey = ".$data['visit_key']." AND itemcode = ".$value['itemcode'];
							    //echo $invoice_detail;exit;
			$this->_db->query($invoice_detail);
		    }
		    else{
			$promotion  = (($value['SalesAmount'] * ($range_arr[0]['promotionamount']/100) ));
			//$prmotion_cal 		= $value['SalesAmount']/$range_arr[0]['rangelow'];
			//$prmotion_calculate_arr = explode(".",$prmotion_cal);
			//$promotion  = (($value['SalesAmount'] * $range_arr[0]['promotionamount'] )/100);
			//$promotion  = (($value['SalesAmount'] * $range_arr[0]['promotionamount']) )/$prmotion_calculate_arr[0] ;
			$range_arr[0]['promotionamount'] = $promotion;
			$insert_query ="INSERT INTO promotiondetail_temp
					(routekey,visitkey,transactionkey,itemtransactiontype,itemcode,promotiontypecode,
					promotionamount,oldpromotionamount,promotionplannumber,assignmentkey,weighted,record_flag,
					salesamount,rangelow,repeatingrange) 
				    values(".$data['route_key'].",".$data['visit_key'].",".$data['transactionkey'].",'1',".$value['itemcode'].",2,
				    ".$range_arr[0]['promotionamount'].",".$value['SalesAmount'].",".$data['plannumber'].",".$data['assignment_no'].",
				    '0','1',".($value['SalesAmount'] - $range_arr[0]['promotionamount']).",".$range_arr[0]['rangelow'].",
				    ".$range_arr[0]['repeatingrange']."
				    )";
				   
			     $this->_db->query($insert_query);
			    
			//update Invoice detail
			$invoice_detail ="UPDATE invoicedetail_temp  SET promoamount = promoamount + ".$range_arr[0]['promotionamount'].",
							    promovalue = promovalue + ".$range_arr[0]['promotionamount'].",
							    sales_amount ='".($value['SalesAmount'] - $range_arr[0]['promotionamount'])."'
							    
							    WHERE routekey = ".$data['route_key']." AND visitkey = ".$data['visit_key']." AND itemcode = ".$value['itemcode'];
							    //echo $invoice_detail;exit;
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
        
   //pr($promotion6,1);
	//$select = "SELECT GROUP_CONCAT(invoicedetail_temp.itemcode) as itemcode,
	//	FORMAT(SUM( IF(sales_amount = '0.0000', ((salesqty / itemmaster.unitspercase) *
	//	invoicedetail_temp.salescaseprice
	//	+ ((salesqty % itemmaster.unitspercase) * invoicedetail_temp.salesprice)) ,sales_amount)),4) AS SalesAmount,
	//	SUM(IFNULL(invoicedetail_temp.salesqty,0)) AS SalesQty
	//
	//	FROM invoicedetail_temp
	//	INNER JOIN itemmaster ON itemmaster.actualitemcode  = invoicedetail_temp.itemcode
	//						   
	//	WHERE 		 invoicedetail_temp.routekey =".$data['route_key'] ."
	//			 AND invoicedetail_temp.visitkey  =".$data['visit_key'];
    
    
	//print_r($result);
	//exit;
	
	    
	    
	    if(in_array("5",$promotiontypecode))
	    {
		foreach($promotion5 as $promot5val)
		{
            $final_amount ="0";
            if($promot5val['qualification_group'] == 1) {
                $select = "SELECT GROUP_CONCAT(invoicedetail_temp.itemcode) as itemcode,
                                IFNULL(FORMAT(SUM( IF(sales_amount = '0.0000', (FLOOR(salesqty / itemmaster.unitspercase) *
                                invoicedetail_temp.salescaseprice
                                + ((salesqty % itemmaster.unitspercase) * invoicedetail_temp.salesprice)) ,sales_amount)),4),0) AS SalesAmount,
                                SUM(IFNULL(invoicedetail_temp.salesqty,0)) AS SalesQty
                            FROM invoicedetail_temp
                            INNER JOIN itemmaster ON itemmaster.actualitemcode  = invoicedetail_temp.itemcode
                                                   
                            WHERE 		 invoicedetail_temp.routekey =".$data['route_key'] ."
                                     AND invoicedetail_temp.visitkey  =".$data['visit_key'];
            } else { 
                $select =  "SELECT GROUP_CONCAT(productgroupdetail.itemcode) AS itemcode,
                                IFNULL(FORMAT(SUM( IF(sales_amount = '0.0000', (FLOOR(salesqty / itemmaster.unitspercase) *
                                invoicedetail_temp.salescaseprice
                                + ((salesqty % itemmaster.unitspercase) * invoicedetail_temp.salesprice)) ,sales_amount)),4),0) AS SalesAmount,
                                SUM(IFNULL(invoicedetail_temp.salesqty,0)) AS SalesQty
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
           //  exit;           
            $result = $this->getAdapter()->fetchAll($select);
            $result[0]['SalesQty'] = str_replace(",","",$result[0]['SalesQty']);
            $result[0]['SalesAmount'] = str_replace(",","",$result[0]['SalesAmount']);
            $data['sales_qnt_org'] = ($data['sales_qnt_org'] != "") ? str_replace(",","",$data['sales_qnt_org']):0;
            $data['sales_amt_org'] = ($data['sales_amt_org'] != "") ? str_replace(",","",$data['sales_amt_org']):0;
            $range_arr = array();
		    if(isset($promot5val['ragebasis']) && ($promot5val['ragebasis'] == 1))
		    {
			   $select_range ="SELECT promotionamount ,rangelow,rangehigh,repeatingrange
					 FROM promotionassignmentadvanced WHERE assignmentnumber =".$promot5val['assignment_no']." 
					 AND IF(repeatingrange = 0 ,   ".str_replace(",","",$data['sales_qnt_org'])."  BETWEEN rangelow AND rangehigh ,
					 ".str_replace(",","",$data['sales_qnt_org'])."  >= rangelow ) ";
		    }
		    else
		    {
			 $select_range ="SELECT promotionamount ,rangelow,rangehigh,repeatingrange
					 FROM promotionassignmentadvanced WHERE assignmentnumber =".$promot5val['assignment_no']." 
					 AND IF(repeatingrange = 0 ,  ".str_replace(",","",$data['sales_amt_org'])."  BETWEEN rangelow AND rangehigh ,
					 ".str_replace(",","",$data['sales_amt_org'])."  >= rangelow ) ";
		    }
		    
		    $range_arr = $this->getAdapter()->fetchAll($select_range);
		   // print_r($range_arr);exit;
		    if(!empty($range_arr))
		    {
			$promotion_amount = "";
			$prmotion_cal = "";
			$prmotion_calculate_arr =array();
			$final_amount = "";
			 if($range_arr[0]['repeatingrange'] == 1)
			{
			    $per_value  		= $range_arr[0]['promotionamount'];
			    $prmotion_cal 		= $data['sales_amt_org']/$range_arr[0]['rangelow'];
			    $prmotion_calculate_arr = explode(".",$prmotion_cal);
			    $promotion_amount 	=  ($prmotion_calculate_arr[0] * $range_arr[0]['promotionamount']);
			    /**
                 *  Developed By Pankil :- Comment below line for the issue of qulified amount was wrong
                 */
                //$final_amount = $result[0]['SalesAmount'] - $promotion_amount;
                $final_amount = $result[0]['SalesAmount'];
			    
			    $insert_query ="INSERT INTO promotiondetail_temp
					(routekey,visitkey,transactionkey,itemtransactiontype,itemcode,promotiontypecode,
					promotionamount,oldpromotionamount,promotionplannumber,assignmentkey,weighted,record_flag,
					salesamount,rangelow,repeatingrange,promotionper) 
				    values(".$data['route_key'].",".$data['visit_key'].",".$data['transactionkey'].",'1','".$result[0]['itemcode']."',5,
				    ".$range_arr[0]['promotionamount'].",".$result[0]['SalesAmount'].",".$promot5val['plannumber'].",".$promot5val['assignment_no'].",
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
                //$final_amount = $result[0]['SalesAmount'] - $range_arr[0]['promotionamount'];
                $final_amount = $result[0]['SalesAmount'];
			    $insert_query ="INSERT INTO promotiondetail_temp
					    (routekey,visitkey,transactionkey,itemtransactiontype,itemcode,promotiontypecode,
					    promotionamount,oldpromotionamount,promotionplannumber,assignmentkey,weighted,record_flag,
					    salesamount,rangelow,repeatingrange,promotionper) 
					values(".$data['route_key'].",".$data['visit_key'].",".$data['transactionkey'].",'1','".$result[0]['itemcode']."',5,
					".$range_arr[0]['promotionamount'].",".$result[0]['SalesAmount'].",".$promot5val['plannumber'].",".$promot5val['assignment_no'].",
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
                                FORMAT(SUM( IF(sales_amount = '0.0000', (FLOOR(salesqty / itemmaster.unitspercase) *
                                invoicedetail_temp.salescaseprice
                                + ((salesqty % itemmaster.unitspercase) * invoicedetail_temp.salesprice)) ,sales_amount)),4) AS SalesAmount,
                                SUM(IFNULL(invoicedetail_temp.salesqty,0)) AS SalesQty
                            FROM invoicedetail_temp
                            INNER JOIN itemmaster ON itemmaster.actualitemcode  = invoicedetail_temp.itemcode
                                                   
                            WHERE 		 invoicedetail_temp.routekey =".$data['route_key'] ."
                                     AND invoicedetail_temp.visitkey  =".$data['visit_key'];
            } else {
                $select =  "SELECT GROUP_CONCAT(productgroupdetail.itemcode) AS itemcode,
                                FORMAT(SUM( IF(sales_amount = '0.0000', (FLOOR(salesqty / itemmaster.unitspercase) *
                                invoicedetail_temp.salescaseprice
                                + ((salesqty % itemmaster.unitspercase) * invoicedetail_temp.salesprice)) ,sales_amount)),4) AS SalesAmount,
                                SUM(IFNULL(invoicedetail_temp.salesqty,0)) AS SalesQty
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
            $result[0]['SalesQty'] = str_replace(",","",$result[0]['SalesQty']);
            $result[0]['SalesAmount'] = str_replace(",","",$result[0]['SalesAmount']);
            $data['sales_qnt_org'] = ($data['sales_qnt_org'] != "") ? str_replace(",","",$data['sales_qnt_org']):0;
            $data['sales_amt_org'] = ($data['sales_amt_org'] != "") ? str_replace(",","",$data['sales_amt_org']):0;
            
            if($final_amount == 0 )
                $final_amount = $result[0]['SalesAmount'];
		
		    if(isset($promot6val['ragebasis']) && ($promot6val['ragebasis'] == 1))
			{
			       $select_range ="SELECT promotionamount ,rangelow,rangehigh,repeatingrange
					     FROM promotionassignmentadvanced WHERE assignmentnumber =".$promot6val['assignment_no']." 
					     AND IF(repeatingrange = 0 ,  ".str_replace(",","",$data['sales_qnt_org'])."  BETWEEN rangelow AND rangehigh ,
					     ".str_replace(",","",$data['sales_qnt_org'])."  >= rangelow ) ";
			}
			else
			{
			     $select_range ="SELECT promotionamount ,rangelow,rangehigh,repeatingrange
					     FROM promotionassignmentadvanced WHERE assignmentnumber =".$promot6val['assignment_no']." 
					     AND IF(repeatingrange = 0 ,  ".str_replace(",","",$data['sales_amt_org'])."  BETWEEN rangelow AND rangehigh ,
					     ".str_replace(",","",$data['sales_amt_org'])."  >= rangelow ) ";
			}
			
			
			
			
			$range_arr = $this->getAdapter()->fetchAll($select_range);
			//print_r($range_arr);exit;
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
			//	$final_amount1 = $final_amount -  $promotion_amount;
                $final_amount1 = $final_amount;
				$range_arr[0]['promotionamount'] = $promotion_amount;
			 
				$insert_query ="INSERT INTO promotiondetail_temp
					       (routekey,visitkey,transactionkey,itemtransactiontype,itemcode,promotiontypecode,
					       promotionamount,oldpromotionamount,promotionplannumber,assignmentkey,weighted,record_flag,
					       salesamount,rangelow,repeatingrange,promotionper) 
					   values(".$data['route_key'].",".$data['visit_key'].",".$data['transactionkey'].",'1','".$result[0]['itemcode']."',6,
					   ".$range_arr[0]['promotionamount'].",".$final_amount.",".$promot6val['plannumber'].",".$promot6val['assignment_no'].",
					   '0','1',".$final_amount1.",".$range_arr[0]['rangelow'].",
					   ".$range_arr[0]['repeatingrange'].",".$per_value."
					   )";
					   //echo $insert_query;exit;
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
						values(".$data['route_key'].",".$data['visit_key'].",".$data['transactionkey'].",'1','".$result[0]['itemcode']."',6,
						".$range_arr[0]['promotionamount'].",".$final_amount.",".$promot6val['plannumber'].",".$promot6val['assignment_no'].",
						'0','1',".$final_amount1.",".$range_arr[0]['rangelow'].",
						".$range_arr[0]['repeatingrange'].",".$per_value."
						)";
						//echo $insert_query;exit;
					 $this->_db->query($insert_query);
			    }
			    
			 $range_arr =array();
			 $final_amount = $final_amount1;
			}
			
		    
		    
		}
		
		
	    }
	     /**
         *      Changes by Pankil Thakkar : For not updating total Promotion Amount while every promotion type code
         */
         
	//    $update_query ="update invoiceheader_temp set totalpromoamount = (SELECT SUM(promotionamount) FROM promotiondetail_temp where promotiondetail_temp.itemtransactiontype = 1 and promotiondetail_temp.routekey =".$data['route_key'] ." AND 
	//		promotiondetail_temp.visitkey = ".$data['visit_key']." AND promotiondetail_temp.transactionkey=".$data['transactionkey']." )
	//    where invoiceheader_temp.transactionkey  =".$data['transactionkey']." and visitkey = ".$data['visit_key'];
	//    
	//   
	//     $this->_db->query($update_query);
	//     
	    
	    
	   	  
	
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
	 IF(sales_amount = '0.0000', FORMAT(((salesqty / itemmaster.unitspercase) *
			IF(p.salescaseprice IS NULL , itemmaster.caseprice,  p.salescaseprice) 
			+ ((salesqty % itemmaster.unitspercase) * invoicedetail_temp.salesprice)),4) ,sales_amount)AS SalesAmount,
			productgroupdetail.itemcode, IFNULL(invoicedetail_temp.salesqty,0) AS SalesQty
	*/
    
    /*
        changed by pankil thakkar (10-12-2012)
            - item code from invoicedetail_temp to productgroupdetail
            - INNER JOIN changed to LEFT JOIN for invoicedetail_temp
    */
	$select  ="    SELECT  productgroupdetail.itemcode as itemcode,
			IFNULL(FORMAT(( IF(sales_amount = '0.0000', ((salesqty / itemmaster.unitspercase) *
			invoicedetail_temp.salescaseprice
		+ ((salesqty % itemmaster.unitspercase) * invoicedetail_temp.salesprice)) ,sales_amount)),4),0) AS SalesAmount,
		(IFNULL(invoicedetail_temp.salesqty,0)) AS SalesQty
			FROM promokeydetail
			INNER JOIN productgroupdetail ON  promokeydetail.assignmentgroup = productgroupdetail.groupnumber 
			LEFT JOIN invoicedetail_temp ON productgroupdetail.itemcode = invoicedetail_temp.itemcode 
    				 AND invoicedetail_temp.routekey =".$data['route_key'] ."
				 AND invoicedetail_temp.visitkey  =".$data['visit_key']."
				INNER JOIN itemmaster ON itemmaster.actualitemcode = productgroupdetail.itemcode 
				
			WHERE promokeydetail.assignmentnumber = ".$data['assignment_no']." 
			AND promokeydetail.promotionkey = (SELECT promotionkey FROM customermaster WHERE customercode = ".$data['customer_code'].") 
		       AND promokeydetail.plannumber = ".$data['plannumber'];
	
	$result = $this->getAdapter()->fetchAll($select);
    
	if(!empty($result))
	{
	    foreach($result as $value)
	    {
            $data['sales_qnt_org'] = ($data['sales_qnt_org'] != "") ? str_replace(",","",$data['sales_qnt_org']):0;
            $data['sales_amt_org'] = ($data['sales_amt_org'] != "") ? str_replace(",","",$data['sales_amt_org']):0;			
			$value['SalesAmount'] = str_replace(",","",$value['SalesAmount']);
		
            $range_arr =array();
            if(isset($data['ragebasis']) && ($data['ragebasis'] == 1))
            {
                   $select_range ="SELECT promotionamount ,rangelow,rangehigh,repeatingrange
                         FROM promotionassignmentadvanced WHERE assignmentnumber =".$data['assignment_no']." 
                         AND IF(repeatingrange = 0 ,  ".str_replace(",","",$data['sales_qnt_org'])."  BETWEEN rangelow AND rangehigh ,
                         ".str_replace(",","",$data['sales_qnt_org'])."  >= rangelow ) ";
            }
            else
            {
                 $select_range ="SELECT promotionamount ,rangelow,rangehigh,repeatingrange
                         FROM promotionassignmentadvanced WHERE assignmentnumber =".$data['assignment_no']." 
                         AND IF(repeatingrange = 0 ,  ".str_replace(",","",$data['sales_amt_org'])."  BETWEEN rangelow AND rangehigh ,
                         ".str_replace(",","",$data['sales_amt_org'])."  >= rangelow ) ";
            }
            
            $range_arr = $this->getAdapter()->fetchAll($select_range);
            
            if(!empty($range_arr))
            {
                $promotion_amount = "";
                $prmotion_cal = "";
                $prmotion_calculate_arr =array();
                $promotion_amount ="";
                $final_amount ="";
                if($range_arr[0]['repeatingrange'] == 1)
                {
                
                    $prmotion_cal 		= $data['sales_qnt_org']/$range_arr[0]['rangelow'];
                    $prmotion_calculate_arr = explode(".",$prmotion_cal);
                    $promotion_amount 	=  ($prmotion_calculate_arr[0] * $range_arr[0]['promotionamount']);
                    $range_arr[0]['promotionamount']  =$promotion_amount ;
                    
                    $insert_query ="INSERT INTO promotiondetail_temp
                                            (routekey,visitkey,transactionkey,itemtransactiontype,itemcode,promotiontypecode,
                                            promotionquantity,oldpromotionamount,promotionplannumber,assignmentkey,weighted,record_flag,
                                            salesamount,rangelow,repeatingrange) 
                                    values(".$data['route_key'].",".$data['visit_key'].",".$data['transactionkey'].",'1','".$value['itemcode']."',7,
                                    ".round($range_arr[0]['promotionamount']).",".$value['SalesAmount'].",".$data['plannumber'].",".$data['assignment_no'].",
                                    '0','1',".($value['SalesAmount']).",".$range_arr[0]['rangelow'].",
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
                                    values(".$data['route_key'].",".$data['visit_key'].",".$data['transactionkey'].",'1','".$value['itemcode']."',7,
                                    ".round($range_arr[0]['promotionamount']).",".$value['SalesAmount'].",".$data['plannumber'].",".$data['assignment_no'].",
                                    '0','1',".($value['SalesAmount']).",".$range_arr[0]['rangelow'].",
                                    ".$range_arr[0]['repeatingrange']."
                                    )";
                    
                    $this->_db->query($insert_query);
                }
              // echo $insert_query;
            
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
    {	//echo "<pre>";
        //print_r($data);
        $select = "Select * from invoicedetail_temp WHERE invoicedetail_temp.routekey = ".$data['route_key'] ."	AND invoicedetail_temp.visitkey = ".$data['visit_key']." and
                invoicedetail_temp.transactionkey =".$data['transactionkey']." and itemcode ='".$data['item_code']."'";
        $range_arr = $this->getAdapter()->fetchAll($select);
        
        if(!empty($range_arr)) {
            if(isset($data['item_transaction_type']) && $data['item_transaction_type'] =="1" )
            {
                $update_query = "UPDATE invoicedetail_temp SET freesampleqty = freesampleqty + ".$data['qty'].",
                        promoqty = ifnull(promoqty,0) + ".$data['qty']."
                    WHERE invoicedetail_temp.routekey = ".$data['route_key'] ."	AND invoicedetail_temp.visitkey = ".$data['visit_key']." and
                    invoicedetail_temp.transactionkey =".$data['transactionkey']." and itemcode ='".$data['item_code']."'";
            }
            else
            {
                $update_query = "UPDATE  invoicedetail_temp SET returnfreesampleqty = returnfreesampleqty + ".$data['qty']."
                    WHERE invoicedetail_temp.routekey = ".$data['route_key'] ."	AND invoicedetail_temp.visitkey = ".$data['visit_key']." and
                    invoicedetail_temp.transactionkey =".$data['transactionkey']." and itemcode ='".$data['item_code']."'";
            }
            $this->_db->query($update_query);
        } else {
            $select =   "SELECT itemmaster.defaultsalesprice AS sales_price,
                                itemmaster.defaultreturnprice AS return_price,
                                itemmaster.caseprice AS sales_case_price,
                                itemmaster.returncaseprice AS return_case_price,
                                itemmaster.defaultgoodreturnprice, 
                                itemmaster.defaultgoodreturncaseprice
                        FROM itemmaster
                        WHERE itemmaster.actualitemcode ='".$data['item_code']."'";
            $itemmaster = $this->getAdapter()->fetchAll($select);
            
            $select =   "SELECT batchdetailkey FROM batchexpirydetail_temp WHERE visitkey = ".$data['visit_key']." LIMIT 1";
            $batchdetailkey = $this->getAdapter()->fetchAll($select);
            
            $insert ="INSERT INTO invoicedetail_temp SET 
                            routekey 		= ".$data['route_key'] .",
                            visitkey 		= ".$data['visit_key'].",
                            transactionkey 	= ".$data['transactionkey'].",
                            itemcode 		= '".$data['item_code']."',
                            freesampleqty 	= ".$data['qty'].",
                            salesprice 		= ".$itemmaster[0]["sales_price"].",
                            returnprice 	= ".$itemmaster[0]["return_price"].",
                            promoqty 		= ".$data['qty'].",
                            salescaseprice	= ".$itemmaster[0]["sales_case_price"].",
                            returncaseprice = ".$itemmaster[0]["return_case_price"].",
                            record_flag		= 1,
                            mdat  			= NOW(),
                            goodreturncaseprice = ".$itemmaster[0]["defaultgoodreturncaseprice"].",
                            goodreturnprice		= ".$itemmaster[0]["defaultgoodreturnprice"].",
                            stdreturncaseprice	= ".$itemmaster[0]["return_case_price"].",
                            stdgoodreturnprice 	= ".$itemmaster[0]["defaultgoodreturnprice"].",
                            stdreturnprice		= ".$itemmaster[0]["return_price"].",
                            stdsalescaseprice	= ".$itemmaster[0]["sales_case_price"].",
                            stdsalesprice		= ".$itemmaster[0]["sales_price"].",
                            stdgoodreturncaseprice	= ".$itemmaster[0]["defaultgoodreturncaseprice"].",
                            batchdetailkey 		= ".$batchdetailkey[0]["batchdetailkey"];
            $this->_db->query($insert);
        }
        
        //echo "------<br/>".$update_query."<br/>"    ;exit;
        
    }
 
    public function remove_invoice_item($data=array())
    {
        $select1 = "SELECT itemcode from invoicedetail_temp WHERE invoicedetail_temp.primary_key = ".$data['invoice_primary_key'];
        $result1 = $this->getAdapter()->fetchAll($select1);
	
    
        $delete_query = "DELETE FROM invoicedetail_temp WHERE invoicedetail_temp.primary_key = ".$data['invoice_primary_key'];
    
            //echo $delete_query;exit;
        $this->_db->query($delete_query);
        
        $update_query = "UPDATE invoiceheader_temp,
            (SELECT 
            SUM((FLOOR(id.salescaseprice) * FLOOR(salesqty/im.unitspercase))+(ROUND(id.salesprice,3) * ROUND((salesqty%im.unitspercase),3))) AS total_sales,
            SUM((FLOOR(id.returncaseprice) * FLOOR(damagedqty/im.unitspercase))+(ROUND(id.returncaseprice,3) * ROUND((damagedqty%im.unitspercase),3))) AS total_damage,
            SUM((FLOOR(id.goodreturncaseprice) * FLOOR(returnqty/im.unitspercase))+(ROUND(id.goodreturnprice,3) * ROUND((returnqty%im.unitspercase),3))) AS total_buyback,
            SUM((FLOOR(id.salescaseprice) * FLOOR(promoqty/im.unitspercase))+(ROUND(id.salesprice,3) * ROUND((promoqty%im.unitspercase),3))) AS total_promo,
            SUM((FLOOR(id.salescaseprice) * FLOOR(freesampleqty/im.unitspercase))+(ROUND(id.salesprice,3) * ROUND((freesampleqty%im.unitspercase),3))) AS total_free_sample_qty,
            SUM((FLOOR(id.salescaseprice) * FLOOR(fixedrentqty/im.unitspercase))+(ROUND(id.salesprice,3) * ROUND((fixedrentqty%im.unitspercase),3))) AS total_manual_free_sample_qty,
            SUM((FLOOR(id.returncaseprice) * FLOOR(expiryqty/im.unitspercase))+(ROUND(id.returncaseprice,3) * ROUND((expiryqty%im.unitspercase),3))) AS total_expiry_amount
            FROM invoicedetail_temp AS id
            LEFT JOIN itemmaster AS im  ON im.actualitemcode =id.itemcode
            JOIN invoiceheader_temp  AS ih
            WHERE ih.transactionkey = id.transactionkey
            AND ih.transactionkey =".$data['transaction_key']."
        ) AS a
            
            SET invoiceheader_temp.totalsalesamount =IFNULL(a.total_sales,0) ,
            -- invoiceheader.totalinvoiceamount = (IFNULL(a.total_sales,0)-IFNULL(a.totalreturnamount,0)-IFNULL(a.total_damage,0)),
            invoiceheader_temp.totalreturnamount =a.total_buyback,
            invoiceheader_temp.totaldamagedamount =a.total_damage,
            invoiceheader_temp.totalexpiryamount =a.total_expiry_amount,
            invoiceheader_temp.totalfreesampleamount =total_manual_free_sample_qty,
            invoiceheader_temp.totalmanualfree =a.total_free_sample_qty
            WHERE invoiceheader_temp.transactionkey =".$data['transaction_key'];
        
        $this->_db->query($update_query);
        
        $update_query = "UPDATE invoiceheader_temp SET 
		totalinvoiceamount=(IFNULL(totalsalesamount,0)-IFNULL(totalreturnamount,0)-IFNULL(totalpromoamount,0)-IFNULL(totaldamagedamount,0)),
		invoicebalance=(IFNULL(totalsalesamount,0)-IFNULL(totalreturnamount,0)-IFNULL(totalpromoamount,0)-IFNULL(totaldamagedamount,0))
		WHERE invoiceheader_temp.transactionkey =".$data['transaction_key'];
        
        $this->_db->query($update_query);
    /**
     *  Changes Pankil Thakkar (For updating the qnt in batchmaster_temp table when item deleted)
     */
    
    $select_query = "SELECT quantity,transactiontypecode,batchnumber FROM batchexpirydetail_temp WHERE itemcode = '".$result1[0]['itemcode']."' AND routekey = '".$data["routekey"]."' AND visitkey = '".$data["visitkey"]."'";
    $batchdetail = $this->getAdapter()->fetchAll($select_query);
    $newbatch_arr = array();
    
    for($i=0;$i<count($batchdetail);$i++)
    {
        if($batchdetail[$i]["transactiontypecode"] == "15")
            $newbatch_arr[$batchdetail[$i]["batchnumber"]]["salesqnt"] = $batchdetail[$i]["quantity"];
        if($batchdetail[$i]["transactiontypecode"] == "16")
            $newbatch_arr[$batchdetail[$i]["batchnumber"]]["freeqnt"] = $batchdetail[$i]["quantity"];
        if($batchdetail[$i]["transactiontypecode"] == "17")
            $newbatch_arr[$batchdetail[$i]["batchnumber"]]["promoqnt"] = $batchdetail[$i]["quantity"];
        if($batchdetail[$i]["transactiontypecode"] == "18")
            $newbatch_arr[$batchdetail[$i]["batchnumber"]]["returnqnt"] = $batchdetail[$i]["quantity"];
        if($batchdetail[$i]["transactiontypecode"] == "19")
            $newbatch_arr[$batchdetail[$i]["batchnumber"]]["buybackqnt"] = $batchdetail[$i]["quantity"];
        if($batchdetail[$i]["transactiontypecode"] == "20")
            $newbatch_arr[$batchdetail[$i]["batchnumber"]]["damageqnt"] = $batchdetail[$i]["quantity"];
        if($batchdetail[$i]["transactiontypecode"] == "21")
            $newbatch_arr[$batchdetail[$i]["batchnumber"]]["expiryqnt"] = $batchdetail[$i]["quantity"];
        if($batchdetail[$i]["transactiontypecode"] == "22")
            $newbatch_arr[$batchdetail[$i]["batchnumber"]]["rentalqnt"] = $batchdetail[$i]["quantity"];
    }
    
    if(count($newbatch_arr) > 0){
        foreach($newbatch_arr as $key => $val) {
            $salesqnt = (isset($newbatch_arr[$key]['salesqnt']) && $newbatch_arr[$key]['salesqnt'] != "") ? $newbatch_arr[$key]['salesqnt'] : 0;
            $freeqnt  = (isset($newbatch_arr[$key]['freeqnt']) && $newbatch_arr[$key]['freeqnt'] != "") ? $newbatch_arr[$key]['freeqnt'] : 0;
            $promoqnt = (isset($newbatch_arr[$key]['promoqnt']) && $newbatch_arr[$key]['promoqnt'] != "") ? $newbatch_arr[$key]['promoqnt'] : 0;
            $returnqnt = (isset($newbatch_arr[$key]['returnqnt']) && $newbatch_arr[$key]['returnqnt'] != "") ? $newbatch_arr[$key]['returnqnt'] : 0;
            $buybackqnt = (isset($newbatch_arr[$key]['buybackqnt']) && $newbatch_arr[$key]['buybackqnt'] != "") ? $newbatch_arr[$key]['buybackqnt'] : 0;
            $damageqnt = (isset($newbatch_arr[$key]['damageqnt']) && $newbatch_arr[$key]['damageqnt'] != "") ? $newbatch_arr[$key]['damageqnt'] : 0;
            $expiryqnt = (isset($newbatch_arr[$key]['expiryqnt']) && $newbatch_arr[$key]['expiryqnt'] != "") ? $newbatch_arr[$key]['expiryqnt'] : 0;
            $rentalqnt = (isset($newbatch_arr[$key]['rentalqnt']) && $newbatch_arr[$key]['rentalqnt'] != "") ? $newbatch_arr[$key]['rentalqnt'] : 0;
            $batchnum = $key;
            
            $update_query = "UPDATE batchmaster_temp SET
                                salesquantity = salesquantity - ".$salesqnt.",
                                freequantity = freequantity - ".$freeqnt.",
                                promoquantity = promoquantity - ".$promoqnt.",
                                returnquantity = returnquantity - ".$returnqnt.",
                                buybackquantity = buybackquantity - ".$buybackqnt.",
                                damagequantity = damagequantity - ".$damageqnt.",
                                expiryquantity = expiryquantity - ".$expiryqnt.",
                                rentalquantity = rentalquantity - ".$rentalqnt."
                            WHERE
                                visitkey = '".$data["visitkey"]."' AND
                                itemcode = '".$result1[0]['itemcode']."' AND
                                batchnumber = '".$batchnum."'
                            ";
            $this->_db->query($update_query);
        }
    }
    //$update_query = "UPDATE batchmaster_temp,(SELECT 
    //                    CASE WHEN transactiontypecode = 15 THEN quantity ELSE 0 END salesqnt,
    //                    CASE WHEN transactiontypecode = 16 THEN quantity ELSE 0 END freeqnt,
    //                    CASE WHEN transactiontypecode = 17 THEN quantity ELSE 0 END promoqnt,
    //                    CASE WHEN transactiontypecode = 18 THEN quantity ELSE 0 END returnqnt,
    //                    CASE WHEN transactiontypecode = 19 THEN quantity ELSE 0 END buybackqnt,
    //                    CASE WHEN transactiontypecode = 20 THEN quantity ELSE 0 END damageqnt,
    //                    CASE WHEN transactiontypecode = 21 THEN quantity ELSE 0 END expiryqnt,
    //                    CASE WHEN transactiontypecode = 22 THEN quantity ELSE 0 END rentalqnt,
    //                    batchnumber
    //                FROM batchexpirydetail_temp
    //                WHERE batchexpirydetail_temp.itemcode = '".$result1[0]['itemcode']."'
    //                    AND batchexpirydetail_temp.routekey = '".$data["routekey"]."'
    //                    AND batchexpirydetail_temp.visitkey = '".$data["visitkey"]."'
    //                ) t 
    //                SET salesquantity = salesquantity - t.salesqnt,
    //                    returnquantity = returnquantity - t.returnqnt,
    //                    expiryquantity = expiryquantity - t.expiryqnt,
    //                    freequantity = freequantity - t.freeqnt,
    //                    buybackquantity = buybackquantity - t.buybackqnt,
    //                    rentalquantity = rentalquantity - t.rentalqnt,
    //                    damagequantity = damagequantity - t.damageqnt,
    //                    promoquantity = promoquantity - t.promoqnt
    //                WHERE batchmaster_temp.itemcode = '".$result1[0]['itemcode']."' AND batchmaster_temp.visitkey = '".$data["visitkey"]."'
    //                AND t.batchnumber = batchmaster_temp.batchnumber";
    //$this->_db->query($update_query);
    
	$delete_query = "DELETE FROM batchexpirydetail_temp WHERE itemcode = '".$result1[0]['itemcode']."' AND routekey = '".$data["routekey"]."' AND visitkey = '".$data["visitkey"]."'";
	
		//echo $delete_query;exit;
	$this->_db->query($delete_query);
	
	
    }
    
    public function add_batch_entry($data)
    {
	$insert_query ="INSERT INTO batchexpirydetail_temp 
	(routekey,batchdetailkey, batchnumber,itemcode, quantity,transactiontypecode,expirydate,visitkey,transactionkey)values
	(".$data['routekey'].",".$data['batchdetailkey'].",'".$data['batchnumber']."',".$data['itemcode'].",
	".$data['quantity'].",15,'".$data['expirydate']."',".$data['visitkey'].",".$data['transactionkey'].")";
	
	$this->_db->query($insert_query);
    }
    
    public function return_add_batch_entry($data)
    {
        $select_query = "SELECT count(*) as cnt FROM batchexpirydetail_temp where batchnumber = '".$data['batchnumber']."' and itemcode = '".$data['itemcode']."' and transactiontypecode = '".$data['trans_type_code']."' and visitkey = '".$data['visitkey']."'";
        $result = $this->getAdapter()->fetchAll($select_query);
        if($result[0]['cnt'] > 0)
        {
            $delete_query ="DELETE FROM
                                batchexpirydetail_temp
                            WHERE  
                                transactiontypecode = '".$data['trans_type_code']."'
                                AND batchnumber = '".$data['batchnumber']."'
                                AND itemcode = ".$data['itemcode']."
                                AND visitkey = '".$data['visitkey']."'";
                            
            $this->_db->query($delete_query);
        }
        
        if($data['quantity'] > 0){
            $select_query = "SELECT batchdetailkey FROM batchexpirydetail_temp WHERE visitkey = '".$data['visitkey']."'";
            $result = $this->getAdapter()->fetchAll($select_query);
            
            if(isset($result[0]) && !empty($result[0]) && $result[0]['batchdetailkey'] != "")
            {
                $batchdetailkey = $result[0]['batchdetailkey'];
            }
            else
            {
                $select1 = "SELECT IFNULL(MAX(batchdetailkey),0)  as batchdetailkey FROM batchexpirydetail";
                $result1 = $this->getAdapter()->fetchAll($select1);
                $batchdetailkey = $result1[0]['batchdetailkey'] + 1;
            }
            $insert_query ="INSERT INTO batchexpirydetail_temp 
                        (routekey,batchdetailkey, batchnumber,itemcode, quantity,transactiontypecode,expirydate,visitkey,transactionkey)values
                        (".$data['routekey'].",'".$batchdetailkey."','".$data['batchnumber']."',".$data['itemcode'].",
                        ".$data['quantity'].",".$data['trans_type_code'].",'".$data['expirydate']."',".$data['visitkey'].",".$data['transactionkey'].")";
                        
            $this->_db->query($insert_query);
        }
        
        $update_query = "UPDATE batchmaster_temp SET ".$data['trans_type_val']."quantity = '".$data['quantity']."'
                            WHERE batchnumber = '".$data['batchnumber']."'
                            AND visitkey = '".$data['visitkey']."'";
        $this->_db->query($update_query);
    }
    
    
    public function return_add_batch_entry_manual($data)
    {
        //echo "<pre>";
        $select_query = "SELECT batchdetailkey FROM batchexpirydetail_temp WHERE visitkey = '".$data['visitkey']."'";
        $result = $this->getAdapter()->fetchAll($select_query);
        if(isset($result[0]) && !empty($result[0]) && $result[0]['batchdetailkey'] != "")
        {
            $batchdetailkey = $result[0]['batchdetailkey'];
        }
        else
        {
            $select1 = "SELECT IFNULL(MAX(batchdetailkey),0)  as batchdetailkey FROM batchexpirydetail";
            $result1 = $this->getAdapter()->fetchAll($select1);
            $batchdetailkey = $result1[0]['batchdetailkey'] + 1;
        }
        
        //$select1 = "select IFNULL(MAX(batchdetailkey),0)  as batchdetailkey FROM batchexpirydetail WHERE transactiontypecode = 1";
        //$result1 = $this->getAdapter()->fetchAll($select1);
        //$result1[0]['batchdetailkey'] = $result1[0]['batchdetailkey'] + 1;
        
        //print_r($result1);
        
        $insert_query ="INSERT INTO batchexpirydetail_temp 
        (routekey,batchdetailkey, batchnumber,itemcode, quantity,transactiontypecode,expirydate,visitkey,transactionkey)values
        (".$data['routekey'].",'".$batchdetailkey."','".$data['batchnumber']."',".$data['itemcode'].",
        ".$data['quantity'].",".$data['trans_type_code'].",'".$data['expirydate']."',".$data['visitkey'].",".$data['transactionkey'].")";
        //echo $insert_query;exit;
        $this->_db->query($insert_query);
        
        $insert_query ="INSERT INTO batchmaster_temp
        (quantity,batchdetailkey, batchnumber,expirydate,itemcode,".$data['trans_type_val']."quantity,visitkey,routekey)values
        (0,'".$batchdetailkey."','".$data['batchnumber']."','".$data['expirydate']."',
        ".$data['itemcode'].",".$data['quantity'].",".$data['visitkey'].",".$data['routekey'].")";
        //echo $insert_query;exit;
        $this->_db->query($insert_query);
    
    }
    
    
    /**
    * @name       blur_add_batch_entry
    * @since      26-10-2012
    * @version    Release: 8
    * @author     PT
    * @copyright  Elan Technologies
    * @param   	  add on blur add batch entry
    *
    */
    public function blur_add_batch_entry($data)
    {
        $select_query = "SELECT batchdetailkey FROM batchexpirydetail_temp WHERE visitkey = '".$data['visitkey']."'";
        $result = $this->getAdapter()->fetchAll($select_query);
        if(isset($result[0]) && !empty($result[0]) && $result[0]['batchdetailkey'] != "")
        {
            $batchdetailkey = $result[0]['batchdetailkey'];
        }
        else
        {
            $select1 = "SELECT IFNULL(MAX(batchdetailkey),0)  as batchdetailkey FROM batchexpirydetail";
            $result1 = $this->getAdapter()->fetchAll($select1);
            $batchdetailkey = $result1[0]['batchdetailkey'] + 1;
        }
        
		if($data['quantity'] > 0) {
			$insert_query ="INSERT INTO batchexpirydetail_temp 
			(routekey,batchdetailkey, batchnumber,itemcode, quantity,transactiontypecode,expirydate,visitkey,transactionkey)values
			(".$data['routekey'].",'".$batchdetailkey."','".$data['batchnumber']."',".$data['itemcode'].",
			".$data['quantity'].",'".$data['transactiontypecode']."','".$data['expirydate']."',".$data['visitkey'].",".$data['transactionkey'].")";
			
			$this->_db->query($insert_query);
		}
    }
    
    /**
    * @name       delete_batch_entry
    * @since      26-10-2012
    * @version    Release: 8
    * @author     PT
    * @copyright  Elan Technologies
    * @param   	  Delete batch entry
    *
    */
    public function delete_batch_entry($data)
    {
        $delete_query ="DELETE FROM batchexpirydetail_temp WHERE routekey = '".$data['routekey']."' AND visitkey = '".$data['visitkey']."' AND itemcode = '".$data['itemcode']."' AND transactiontypecode = '".$data['transactiontypecode']."'";
        $this->_db->query($delete_query);
    }
    
    /**
    * @name       batch_master_temp insert
    * @since      26-10-2012
    * @version    Release: 8
    * @author     PT
    * @copyright  Elan Technologies
    * @param   	  Delete batch entry
    *
    */
    public function insert_batchmaster_temp($data)
    {
        $select_query = "SELECT count(*) as cnt FROM batchmaster_temp where batchnumber = '".$data['batchnumber']."' AND visitkey = '".$data['visitkey']."'";
        $result = $this->getAdapter()->fetchAll($select_query);
        
        if($result[0]["cnt"] > 0)
        {
            $update_query ="UPDATE batchmaster_temp SET ".$data["type"]."quantity = '".$data['qnt']."' WHERE batchnumber = '".$data['batchnumber']."' AND visitkey = '".$data['visitkey']."'";
            $this->_db->query($update_query);
        }
        else
        {
            $select_query = "SELECT batchdetailkey FROM batchexpirydetail_temp WHERE visitkey = '".$data['visitkey']."'";
            $result = $this->getAdapter()->fetchAll($select_query);
            if(isset($result[0]) && !empty($result[0]) && $result[0]['batchdetailkey'] != "")
            {
                $batchdetailkey = $result[0]['batchdetailkey'];
            }
            else
            {
                $select1 = "SELECT IFNULL(MAX(batchdetailkey),0)  as batchdetailkey FROM batchexpirydetail";
                $result1 = $this->getAdapter()->fetchAll($select1);
                $batchdetailkey = $result1[0]['batchdetailkey'] + 1;
            }
            $insert_query ="INSERT INTO batchmaster_temp
                            (quantity,batchdetailkey,batchnumber,expirydate,itemcode,".$data["type"]."quantity,visitkey,routekey) VALUES
                            ('".$data['quantity']."','".$batchdetailkey."','".$data['batchnumber']."','".$data['expirydate']."',
                            '".$data['itemcode']."','".$data['qnt']."','".$data['visitkey']."','".$data['routekey']."')";
            $this->_db->query($insert_query);
        }
    }
    
    /**
    * @name       checkbatch
    * @since      29-10-2012
    * @version    Release: 8
    * @author     PT
    * @copyright  Elan Technologies
    * @param   	  check batch entry
    *
    */
    public function checkbatch($batches)
    {
        $select_query = "SELECT count(*) as cnt FROM batchmaster_temp WHERE batchnumber IN ($batches)";
        $result = $this->getAdapter()->fetchAll($select_query);
        return $result;
    }
    
    /**
   * Date : 5 Dec 2012
   * Pankil thakkar
   * Desc : For updating the invoice header temp for promo amount
   *
   */
    public function update_promoamount($data=array())
    {
	    $update_query ="UPDATE invoiceheader_temp
                            SET totalpromoamount = (SELECT SUM(promotionamount)
                                                    FROM promotiondetail_temp
                                                    WHERE promotiondetail_temp.itemtransactiontype = 1
                                                        AND promotiondetail_temp.routekey =".$data['route_key'] ."
                                                        AND promotiondetail_temp.visitkey = ".$data['visit_key']."
                                                        AND promotiondetail_temp.transactionkey=".$data['transactionkey']."
                                                    )
                        WHERE invoiceheader_temp.transactionkey  =".$data['transactionkey']."
                            AND visitkey = ".$data['visit_key'];
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
				     AND IF(repeatingrange = 0 ,  ".str_replace(",","",$data['SalesQty'])."  BETWEEN rangelow AND rangehigh ,
				     ".str_replace(",","",$data['SalesQty'])."  >= rangelow ) ";
		}
		else
		{
		     $select_range ="SELECT promotionamount ,rangelow,rangehigh,repeatingrange,".str_replace(",","",$data['SalesAmount'])." as amount
				     FROM promotionassignmentadvanced WHERE assignmentnumber =".$data['assignment_no']." 
				     AND IF(repeatingrange = 0 ,  ".str_replace(",","",$data['SalesAmount'])."  BETWEEN rangelow AND rangehigh ,
				     ".str_replace(",","",$data['SalesAmount'])."  >= rangelow ) ";
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
    public function create_newbatch($data=array())
    {
        $select_query = "SELECT batchdetailkey FROM batchexpirydetail_temp WHERE visitkey = '".$data['visitkey']."'";
        $result = $this->getAdapter()->fetchAll($select_query);
        if(isset($result[0]) && !empty($result[0]) && $result[0]['batchdetailkey'] != "")
        {
            $batchdetailkey = $result[0]['batchdetailkey'];
        }
        else
        {
            $select1 = "SELECT IFNULL(MAX(batchdetailkey),0)  as batchdetailkey FROM batchexpirydetail";
            $result1 = $this->getAdapter()->fetchAll($select1);
            $batchdetailkey = $result1[0]['batchdetailkey'] + 1;
        }
        
        $insert_query ="INSERT INTO batchexpirydetail_temp 
        (routekey,batchdetailkey, batchnumber,itemcode, quantity,transactiontypecode,expirydate,visitkey,transactionkey)values
        (".$data['routekey'].",'".$batchdetailkey."','NONE',".$data['itemcode'].",
        ".$data['quantity'].",".$data['trans_type_code'].",'2099-12-31',".$data['visitkey'].",".$data['transactionkey'].")";
        
        $this->_db->query($insert_query);
        
        $insert_query ="INSERT INTO batchmaster_temp
        (quantity,batchdetailkey, batchnumber,expirydate,itemcode,".$data['trans_type_val']."quantity,visitkey,routekey)values
        (0,'".$batchdetailkey."','NONE','2099-12-31',
        ".$data['itemcode'].",".$data['quantity'].",".$data['visitkey'].",".$data['routekey'].")";
        
        $this->_db->query($insert_query);
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
            $pqty = "(".$data["promotion_fixed_amt"]." *productgroupdetail.itemqty)";
        }
        
	    $select = "select productgroupdetail.itemcode,".$pqty." As NewInvoiceAmount, (itemqty * itemmaster.defaultsalesprice) As SalesAmount,ifnull(itemqty,0) As   SalesQty,
                    (itemqty * productgroupdetail.promopcprice) As promoamount,promocaseprice,promopcprice
                from promokeydetail
                inner join productgroupdetail
                    on  promokeydetail.assignmentgroup = productgroupdetail.groupnumber
                inner join inventorysummarydetail
                    on productgroupdetail.itemcode = inventorysummarydetail.itemcode and inventorysummarydetail.routekey = " .$data["route_key"]. " inner join itemmaster
                    on itemmaster.actualitemcode = productgroupdetail.itemcode
                where promokeydetail.assignmentnumber = " .$data["assignment_no"]. "
                    and promokeydetail.promotionkey = (select promotionkey from customermaster where customercode = " .$data["customer_code"]. ")
                and promokeydetail.plannumber = " .$data["plannumber"];
        $result = $this->getAdapter()->fetchAll($select);
        
        for($i=0;$i<count($result);$i++) {
            $insert_query ="INSERT INTO promotiondetail_temp
                                    (routekey,visitkey,transactionkey,itemtransactiontype,itemcode,promotiontypecode,
                                    promotionquantity,oldpromotionamount,promotionplannumber,assignmentkey,weighted,record_flag,
                                    salesamount,rangelow,repeatingrange) 
                            values(".$data['route_key'].",".$data['visit_key'].",".$data['transactionkey'].",'1','".$result[$i]["itemcode"]."',7,
                            ".round($result[$i]["NewInvoiceAmount"]).",".$result[$i]['SalesAmount'].",".$data['plannumber'].",".$data['assignment_no'].",
                            '0','1',".($result[$i]['SalesAmount']).",0,
                            ".$data["repeatingrange_val"]."
                            )";
            
            $this->_db->query($insert_query);
        }
    }
    
}