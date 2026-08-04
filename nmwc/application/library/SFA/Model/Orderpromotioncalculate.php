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

class SFA_Model_Orderpromotioncalculate extends Zend_Db_Table_Abstract
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
			and itemtransactiontype =4
			";
			
	//echo $remove_query;		exit;
	$this->_db->query($remove_query);
	
	$update_query = "UPDATE  salesorderdetail_temp SET sales_amount ='0.0000' ,promoamount ='0'
		    WHERE salesorderdetail_temp.routekey = ".$data['route_key'] ." AND salesorderdetail_temp.visitkey = ".$data['visit_key']." and
		    salesorderdetail_temp.transactionkey =".$data['transactionkey'];
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
	$select1 = "SELECT get_number_type_base('".$data['route_code']."',3) as order_seq,
			get_number_type_base('".$data['route_code']."',2) as document_seq , defaultdeliverydays as days from routemaster where routecode =".$data['route_code'];
			
	
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
	$select="SELECT IFNULL(SUM(salesorderdetail_temp.salesqty),0) AS SalesQty,
	SUM(((salesqty / itemmaster.unitspercase)  *
	( salesorderdetail_temp.salescaseprice )) +
	((salesqty % itemmaster.unitspercase) * salesorderdetail_temp.salesprice)) AS SalesAmount 
	FROM promokeydetail
	
	INNER JOIN productgroupdetail ON  promokeydetail.qualificationgroup = productgroupdetail.groupnumber 
	INNER JOIN salesorderdetail_temp ON productgroupdetail.itemcode = salesorderdetail_temp.itemcode
	AND salesorderdetail_temp.routekey =".$data['route_key'] ."
	AND salesorderdetail_temp.visitkey =".$data['visit_key']."
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
		    
		    $insert_query ="INSERT INTO promotiondetail_temp
				    (routekey,visitkey,transactionkey,itemtransactiontype,itemcode,promotiontypecode,
				    promotionamount,oldpromotionamount,promotionplannumber,assignmentkey,weighted,record_flag,
				    salesamount,rangelow,repeatingrange)
				    
				   SELECT ".$data['route_key'].",".$data['visit_key'].",".$data['transactionkey'].",4, productgroupdetail.itemcode,0,
				   (((salesqty / itemmaster.unitspercase)  *
					    (salesorderdetail_temp.salescaseprice)) +
					    ((salesqty % itemmaster.unitspercase)  * salesorderdetail_temp.salesprice) -
					    ((salesqty / itemmaster.unitspercase) *  productgroupdetail.promocaseprice) +
					    ((salesqty % itemmaster.unitspercase) * productgroupdetail.promopcprice)) AS promoamount, 
				 REPLACE(FORMAT((((salesqty / itemmaster.unitspercase)  *
				     (salesorderdetail_temp.salescaseprice)) +
				     ((salesqty % itemmaster.unitspercase)  * salesorderdetail_temp.salesprice)),2),',','') AS SalesAmount,
				  ".$data['plannumber'].",".$data['assignment_no'].",'0','1',
				    REPLACE(FORMAT( (((salesqty / itemmaster.unitspercase)  *
				    (IF (salesorderdetail_temp.salescaseprice IS NULL, itemmaster.caseprice ,salesorderdetail_temp.salescaseprice)))
				    +  ((salesqty % itemmaster.unitspercase)  * salesorderdetail_temp.salesprice)),2),',','') AS SalesAmount,
				   ".$range_arr[0]['rangelow'].",".$range_arr[0]['repeatingrange']."
	
	 
			    FROM promokeydetail 
			    INNER JOIN promotionassignmentadvanced ON promotionassignmentadvanced.assignmentnumber=promokeydetail.assignmentnumber 
			    INNER JOIN productgroupdetail ON  promotionassignmentadvanced.promotionamount = productgroupdetail.groupnumber 
			    INNER JOIN salesorderdetail_temp ON productgroupdetail.itemcode = salesorderdetail_temp.itemcode 
							      AND salesorderdetail_temp.routekey = ".$data['route_key']."
							   AND salesorderdetail_temp.visitkey  = ".$data['visit_key']."
			    INNER JOIN itemmaster ON itemmaster.actualitemcode = productgroupdetail.itemcode 
			    WHERE promokeydetail.assignmentnumber = ".$data['assignment_no']." 
			    AND promokeydetail.promotionkey = (SELECT promotionkey FROM customermaster  WHERE customercode =".$data['customer_code'].") 
			    AND promokeydetail.plannumber =".$data['plannumber'];
				
				
			 $this->_db->query($insert_query);
			
			$update_query= "UPDATE promotiondetail_temp set salesamount = oldpromotionamount - promotionamount WHERE
			    promotiondetail_temp.routekey = ".$data['route_key']." and 
			    promotiondetail_temp.visitkey = ".$data['visit_key']." and
			    promotiondetail_temp.transactionkey = ".$data['transactionkey']." and
			    promotiondetail_temp.promotiontypecode= '0'
			    and itemtransactiontype = 4";
			  
			    $this->_db->query($update_query);
			    
			$update_query=  "UPDATE salesorderdetail_temp  ,promotiondetail_temp
					 SET salesorderdetail_temp.promoamount = IFNULL(salesorderdetail_temp.promoamount,0) + promotiondetail_temp.promotionamount
					 , promovalue = IFNULL(promovalue,0) + promotiondetail_temp.promotionamount ,
					 sales_amount = promotiondetail_temp.salesamount
					 WHERE salesorderdetail_temp.itemcode = promotiondetail_temp.itemcode
					 AND salesorderdetail_temp.routekey =promotiondetail_temp.routekey AND
					 salesorderdetail_temp.transactionkey = promotiondetail_temp.transactionkey AND
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
			salesorderdetail_temp.salescaseprice
			+ ((salesqty % itemmaster.unitspercase) * salesorderdetail_temp.salesprice)),4) ,sales_amount)AS SalesAmount,
			productgroupdetail.itemcode, IFNULL(salesorderdetail_temp.salesqty,0) AS SalesQty
			
			FROM promokeydetail
			INNER JOIN productgroupdetail ON  promokeydetail.assignmentgroup = productgroupdetail.groupnumber 
			INNER JOIN salesorderdetail_temp ON productgroupdetail.itemcode = salesorderdetail_temp.itemcode 
    				 AND salesorderdetail_temp.routekey =".$data['route_key'] ."
				 AND salesorderdetail_temp.visitkey  =".$data['visit_key']."
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
				values(".$data['route_key'].",".$data['visit_key'].",".$data['transactionkey'].",'4',".$value['itemcode'].",1,
				".$promotion_amount.",".$value['SalesAmount'].",".$data['plannumber'].",".$data['assignment_no'].",
				'0','1',".($value['SalesAmount'] - $promotion_amount).",".$range_arr[0]['rangelow'].",
				".$range_arr[0]['repeatingrange']."
				)";
				$this->_db->query($insert_query);
				
				//update Invoice detail
			$invoice_detail ="UPDATE salesorderdetail_temp  SET promoamount = promoamount + ".$promotion_amount.",
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
				values(".$data['route_key'].",".$data['visit_key'].",".$data['transactionkey'].",'4',".$value['itemcode'].",1,
				".$range_arr[0]['promotionamount'].",".$value['SalesAmount'].",".$data['plannumber'].",".$data['assignment_no'].",
				'0','1',".($value['SalesAmount'] - $range_arr[0]['promotionamount']).",".$range_arr[0]['rangelow'].",
				".$range_arr[0]['repeatingrange']."
				)";
				
			 $this->_db->query($insert_query);
			
			//update Invoice detail
			$invoice_detail ="UPDATE salesorderdetail_temp  SET promoamount = promoamount + ".$range_arr[0]['promotionamount'].",
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
			salesorderdetail_temp.salescaseprice 
			+ ((salesqty % itemmaster.unitspercase) * salesorderdetail_temp.salesprice)),4) ,sales_amount)AS SalesAmount,
			productgroupdetail.itemcode, IFNULL(salesorderdetail_temp.salesqty,0) AS SalesQty
			
			FROM promokeydetail
			INNER JOIN productgroupdetail ON  promokeydetail.assignmentgroup = productgroupdetail.groupnumber 
			INNER JOIN salesorderdetail_temp ON productgroupdetail.itemcode = salesorderdetail_temp.itemcode 
    				 AND salesorderdetail_temp.routekey =".$data['route_key'] ."
				 AND salesorderdetail_temp.visitkey  =".$data['visit_key']."
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
		//print_r($range_arr);
		if(!empty($range_arr))
		{
		    $promotion_amount = "";
		    $prmotion_cal = "";
		    $prmotion_calculate_arr =array();
		    $promotion ="";
		     if($range_arr[0]['repeatingrange'] == 1)
		    {
			
			$prmotion_cal 		= $value['SalesAmount']/$range_arr[0]['rangelow'];
			$prmotion_calculate_arr = explode(".",$prmotion_cal);
			$promotion_amount 	=  $range_arr[0]['rangelow'] * ($prmotion_calculate_arr[0] * ($range_arr[0]['promotionamount']/100));
			$promotion = $promotion_amount;
			//$promotion  = (($value['SalesAmount'] * $promotion_amount ));
			//$promotion  = (($value['SalesAmount'] * $range_arr[0]['promotionamount']) )/$prmotion_calculate_arr[0] ;
			$range_arr[0]['promotionamount'] = $promotion;
			$insert_query ="INSERT INTO promotiondetail_temp
					(routekey,visitkey,transactionkey,itemtransactiontype,itemcode,promotiontypecode,
					promotionamount,oldpromotionamount,promotionplannumber,assignmentkey,weighted,record_flag,
					salesamount,rangelow,repeatingrange) 
				    values(".$data['route_key'].",".$data['visit_key'].",".$data['transactionkey'].",'4',".$value['itemcode'].",2,
				    ".$range_arr[0]['promotionamount'].",".$value['SalesAmount'].",".$data['plannumber'].",".$data['assignment_no'].",
				    '0','1',".($value['SalesAmount'] - $range_arr[0]['promotionamount']).",".$range_arr[0]['rangelow'].",
				    ".$range_arr[0]['repeatingrange']."
				    )";
				    //echo $insert_query;
			     $this->_db->query($insert_query);
			    
			//update Invoice detail
			$invoice_detail ="UPDATE salesorderdetail_temp  SET promoamount = promoamount + ".$range_arr[0]['promotionamount'].",
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
				    values(".$data['route_key'].",".$data['visit_key'].",".$data['transactionkey'].",'4',".$value['itemcode'].",2,
				    ".$range_arr[0]['promotionamount'].",".$value['SalesAmount'].",".$data['plannumber'].",".$data['assignment_no'].",
				    '0','1',".($value['SalesAmount'] - $range_arr[0]['promotionamount']).",".$range_arr[0]['rangelow'].",
				    ".$range_arr[0]['repeatingrange']."
				    )";
				   
			     $this->_db->query($insert_query);
			    
			//update Invoice detail
			$invoice_detail ="UPDATE salesorderdetail_temp  SET promoamount = promoamount + ".$range_arr[0]['promotionamount'].",
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
   
	//$select = "SELECT GROUP_CONCAT(salesorderdetail_temp.itemcode) as itemcode,
	//	FORMAT(SUM( IF(sales_amount = '0.0000', ((salesqty / itemmaster.unitspercase) *
	//	salesorderdetail_temp.salescaseprice
	//	+ ((salesqty % itemmaster.unitspercase) * salesorderdetail_temp.salesprice)) ,sales_amount)),4) AS SalesAmount,
	//	SUM(IFNULL(salesorderdetail_temp.salesqty,0)) AS SalesQty
	//
	//	FROM salesorderdetail_temp
	//	INNER JOIN itemmaster ON itemmaster.actualitemcode  = salesorderdetail_temp.itemcode
	//						   
	//	WHERE 		 salesorderdetail_temp.routekey =".$data['route_key'] ."
	//			 AND salesorderdetail_temp.visitkey  =".$data['visit_key'];
	//	
	//$result = $this->getAdapter()->fetchAll($select);
	
	//if(!empty($result))
	//{
	    
	   
	    if(in_array("5",$promotiontypecode))
	    {
		foreach($promotion5 as $promot5val)
		{
            $final_amount ="0";
            if($promot5val['qualification_group'] == 1) {
                $select = "SELECT GROUP_CONCAT(salesorderdetail_temp.itemcode) as itemcode,
                                	FORMAT(SUM( IF(sales_amount = '0.0000', (FLOOR(salesqty / itemmaster.unitspercase) *
                                	salesorderdetail_temp.salescaseprice
                                	+ ((salesqty % itemmaster.unitspercase) * salesorderdetail_temp.salesprice)) ,sales_amount)),4) AS SalesAmount,
                                	SUM(IFNULL(salesorderdetail_temp.salesqty,0)) AS SalesQty
                            FROM salesorderdetail_temp
                            INNER JOIN itemmaster ON itemmaster.actualitemcode  = salesorderdetail_temp.itemcode
                            WHERE salesorderdetail_temp.routekey =".$data['route_key'] ."
                                	AND salesorderdetail_temp.visitkey  =".$data['visit_key'];
            } else { 
                $select =  "SELECT GROUP_CONCAT(productgroupdetail.itemcode) AS itemcode,
                                FORMAT(SUM( IF(sales_amount = '0.0000', (FLOOR(salesqty / itemmaster.unitspercase) *
                                salesorderdetail_temp.salescaseprice
                                + ((salesqty % itemmaster.unitspercase) * salesorderdetail_temp.salesprice)) ,sales_amount)),4) AS SalesAmount,
                                SUM(IFNULL(salesorderdetail_temp.salesqty,0)) AS SalesQty
                        FROM promokeydetail 
                        INNER JOIN productgroupdetail ON  promokeydetail.assignmentgroup = productgroupdetail.groupnumber 
                        INNER JOIN salesorderdetail_temp ON productgroupdetail.itemcode = salesorderdetail_temp.itemcode
                            AND salesorderdetail_temp.routekey = ".$data['route_key'] ."
                            AND salesorderdetail_temp.visitkey  = ".$data['visit_key']." 
                        INNER JOIN itemmaster ON itemmaster.actualitemcode = productgroupdetail.itemcode 
                        WHERE
                            promokeydetail.assignmentnumber = ".$promot5val['assignment_no']." AND
                            promokeydetail.promotionkey = (SELECT promotionkey FROM customermaster 
                                                                WHERE customercode = " .$data['customer_code']. ")
                        AND promokeydetail.plannumber = ".$promot5val['plannumber']." 
                        GROUP BY promokeydetail.plannumber";
            }
            
            $result = $this->getAdapter()->fetchAll($select);
            $result[0]['SalesQty'] = str_replace(",","",$result[0]['SalesQty']);
            $result[0]['SalesAmount'] = str_replace(",","",$result[0]['SalesAmount']);
            $data['sales_qnt_org'] = ($data['sales_qnt_org'] != "") ? str_replace(",","",$data['sales_qnt_org']):0;
            $data['sales_amt_org'] = ($data['sales_amt_org'] != "") ? str_replace(",","",$data['sales_amt_org']):0;
		    if(isset($promot5val['ragebasis']) && ($promot5val['ragebasis'] == 1))
		    {
			   $select_range ="SELECT promotionamount ,rangelow,rangehigh,repeatingrange
					 FROM promotionassignmentadvanced WHERE assignmentnumber =".$promot5val['assignment_no']." 
					 AND IF(repeatingrange = 0 ,  ".str_replace(",","",$data['sales_qnt_org'])."  BETWEEN rangelow AND rangehigh ,
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
			$final_amount ="";
			 $per_value  			= $range_arr[0]['promotionamount'];
			 if($range_arr[0]['repeatingrange'] == 1)
			{
			   
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
				    values(".$data['route_key'].",".$data['visit_key'].",".$data['transactionkey'].",'4','".$result[0]['itemcode']."',5,
				    ".$range_arr[0]['promotionamount'].",".$result[0]['SalesAmount'].",".$promot5val['plannumber'].",".$promot5val['assignment_no'].",
				    '0','1',".$final_amount.",".$range_arr[0]['rangelow'].",
				    ".$range_arr[0]['repeatingrange'].",".$per_value."
				    )";
				    //echo $insert_query;exit;
			     $this->_db->query($insert_query);
			}
			else
			{
                /**
                 *  Developed By Pankil :- Comment below line for the issue of qulified amount was wrong
                 */
			    //$final_amount = $result[0]['SalesAmount'] - $range_arr[0]['promotionamount'];
                $final_amount = $result[0]['SalesAmount'];
			    $insert_query ="INSERT INTO promotiondetail_temp
					    (routekey,visitkey,transactionkey,itemtransactiontype,itemcode,promotiontypecode,
					    promotionamount,oldpromotionamount,promotionplannumber,assignmentkey,weighted,record_flag,
					    salesamount,rangelow,repeatingrange,promotionper) 
					values(".$data['route_key'].",".$data['visit_key'].",".$data['transactionkey'].",'4','".$result[0]['itemcode']."',5,
					".$range_arr[0]['promotionamount'].",".$result[0]['SalesAmount'].",".$promot5val['plannumber'].",".$promot5val['assignment_no'].",
					'0','1',".$final_amount.",".$range_arr[0]['rangelow'].",
					".$range_arr[0]['repeatingrange'].",".$per_value."
					)";
					//echo $insert_query;
				 $this->_db->query($insert_query);
			}
			//echo $insert_query;
		    }
		    
		    $range_arr =array();
		}
		
		//echo "ddd";exit;
	    }
	   
	    if(in_array("6",$promotiontypecode))
	    {
		
		//if($final_amount == 0 )
		//$final_amount = $result[0]['SalesAmount'];
		
		foreach($promotion6 as $promot6val)
		{
		    $final_amount = 0;
            if($promot6val['qualification_group'] == 1) {
                $select = "SELECT GROUP_CONCAT(salesorderdetail_temp.itemcode) as itemcode,
                                	FORMAT(SUM( IF(sales_amount = '0.0000', (FLOOR(salesqty / itemmaster.unitspercase) *
                                	salesorderdetail_temp.salescaseprice
                                	+ ((salesqty % itemmaster.unitspercase) * salesorderdetail_temp.salesprice)) ,sales_amount)),4) AS SalesAmount,
                                	SUM(IFNULL(salesorderdetail_temp.salesqty,0)) AS SalesQty
                            FROM salesorderdetail_temp
                            INNER JOIN itemmaster ON itemmaster.actualitemcode  = salesorderdetail_temp.itemcode
                            WHERE salesorderdetail_temp.routekey =".$data['route_key'] ."
                                	AND salesorderdetail_temp.visitkey  =".$data['visit_key'];
            } else {
                $select =  "SELECT GROUP_CONCAT(productgroupdetail.itemcode) AS itemcode,
                                FORMAT(SUM( IF(sales_amount = '0.0000', (FLOOR(salesqty / itemmaster.unitspercase) *
                                salesorderdetail_temp.salescaseprice
                                + ((salesqty % itemmaster.unitspercase) * salesorderdetail_temp.salesprice)) ,sales_amount)),4) AS SalesAmount,
                                SUM(IFNULL(salesorderdetail_temp.salesqty,0)) AS SalesQty
                        FROM promokeydetail 
                        INNER JOIN productgroupdetail ON  promokeydetail.assignmentgroup = productgroupdetail.groupnumber 
                        INNER JOIN salesorderdetail_temp ON productgroupdetail.itemcode = salesorderdetail_temp.itemcode
                            AND salesorderdetail_temp.routekey = ".$data['route_key'] ."
                            AND salesorderdetail_temp.visitkey  = ".$data['visit_key']." 
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
			     $per_value  			= $range_arr[0]['promotionamount'];
			    if($range_arr[0]['repeatingrange'] == 1)
			    {
				
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
					   values(".$data['route_key'].",".$data['visit_key'].",".$data['transactionkey'].",'4','".$result[0]['itemcode']."',6,
					   ".$range_arr[0]['promotionamount'].",".$final_amount.",".$promot6val['plannumber'].",".$promot6val['assignment_no'].",
					   '0','1',".$final_amount1.",".$range_arr[0]['rangelow'].",
					   ".$range_arr[0]['repeatingrange'].",".$per_value."
					   )";
					   //echo $insert_query;exit;
				    $this->_db->query($insert_query);
			    }
			    else
			    {
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
						values(".$data['route_key'].",".$data['visit_key'].",".$data['transactionkey'].",'4','".$result[0]['itemcode']."',6,
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
	//    $update_query ="update salesorderheader_temp set totalpromoamount = (SELECT SUM(promotionamount) FROM promotiondetail_temp where  promotiondetail_temp.routekey =".$data['route_key'] ." AND 
	//		promotiondetail_temp.visitkey = ".$data['visit_key']." AND promotiondetail_temp.transactionkey=".$data['transactionkey']." )
	//    where salesorderheader_temp.transactionkey  =".$data['transactionkey']." and visitkey = ".$data['visit_key'];
	//    
	//   
	//     $this->_db->query($update_query);
	     
	    
	    
	   	  
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
        changed by pankil thakkar (10-12-2012)
            - item code from invoicedetail_temp to productgroupdetail
            - INNER JOIN changed to LEFT JOIN for invoicedetail_temp
    */
	$select  =  "SELECT  productgroupdetail.itemcode as itemcode,
                            IFNULL(FORMAT(( IF(sales_amount = '0.0000', ((salesqty / itemmaster.unitspercase) *
                            salesorderdetail_temp.salescaseprice
                            + ((salesqty % itemmaster.unitspercase) * salesorderdetail_temp.salesprice)) ,sales_amount)),4),0) AS SalesAmount,
                            (IFNULL(salesorderdetail_temp.salesqty,0)) AS SalesQty
                FROM promokeydetail
                INNER JOIN productgroupdetail ON  promokeydetail.assignmentgroup = productgroupdetail.groupnumber 
                LEFT JOIN salesorderdetail_temp ON productgroupdetail.itemcode = salesorderdetail_temp.itemcode 
                         AND salesorderdetail_temp.routekey =".$data['route_key'] ."
                     AND salesorderdetail_temp.visitkey  =".$data['visit_key']."
                    INNER JOIN itemmaster ON itemmaster.actualitemcode = productgroupdetail.itemcode 
                    
                WHERE promokeydetail.assignmentnumber = ".$data['assignment_no']." 
                AND promokeydetail.promotionkey = (SELECT promotionkey FROM customermaster WHERE customercode = ".$data['customer_code'].") 
                   AND promokeydetail.plannumber = ".$data['plannumber'];
		       
		  //   echo $select;
	$result = $this->getAdapter()->fetchAll($select);
    
	if(!empty($result))
	{
	    foreach($result as $value)
	    {
            $data['sales_qnt_org'] = ($data['sales_qnt_org'] != "") ? str_replace(",","",$data['sales_qnt_org']):0;
            $data['sales_amt_org'] = ($data['sales_amt_org'] != "") ? str_replace(",","",$data['sales_amt_org']):0;
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
				// By Hiren Dave remove Comma(,) from sales & return amount for fixing insert issue.
				$value['SalesAmount'] = str_replace(',','',$value['SalesAmount']);
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
                    values(".$data['route_key'].",".$data['visit_key'].",".$data['transactionkey'].",'4','".$value['itemcode']."',7,
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
                    values(".$data['route_key'].",".$data['visit_key'].",".$data['transactionkey'].",'4','".$value['itemcode']."',7,
                    ".round($range_arr[0]['promotionamount']).",".$value['SalesAmount'].",".$data['plannumber'].",".$data['assignment_no'].",
                    '0','1',".($value['SalesAmount']).",".$range_arr[0]['rangelow'].",
                    ".$range_arr[0]['repeatingrange']."
                    )";
                    
                 $this->_db->query($insert_query);
                    }
               
            //echo $insert_query;
             
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
	
	
        $update_query = "UPDATE  salesorderdetail_temp SET freesampleqty ='0'
		    WHERE salesorderdetail_temp.routekey = ".$data['route_key'] ."	AND salesorderdetail_temp.visitkey = ".$data['visit_key']." and
		    salesorderdetail_temp.transactionkey =".$data['transactionkey'];
		    
		   // echo $update_query;exit;
		$this->_db->query($update_query);
        
        $delete_query = "DELETE
                        FROM salesorderdetail_temp 
                        WHERE 
                            (salesqty = 0 OR salesqty IS NULL OR salesqty = '')
                            AND (returnqty = 0 OR returnqty IS NULL OR returnqty = '')
                            AND (damagedqty = 0 OR damagedqty IS NULL OR damagedqty = '')
                            AND (freesampleqty = 0 OR freesampleqty IS NULL OR freesampleqty = '')
                            AND (manualfreeqty = 0 OR manualfreeqty IS NULL OR manualfreeqty = '')
                            AND salesorderdetail_temp.routekey = ".$data['route_key'] ."	AND salesorderdetail_temp.visitkey = ".$data['visit_key']." and
                            salesorderdetail_temp.transactionkey =".$data['transactionkey'];
                            
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
        $select = "SELECT * FROM salesorderdetail_temp
                    WHERE salesorderdetail_temp.routekey = ".$data['route_key'] ."
                    AND salesorderdetail_temp.visitkey = ".$data['visit_key']."
                    AND salesorderdetail_temp.transactionkey =".$data['transactionkey']."
                    AND itemcode ='".$data['item_code']."'";
        $range_arr = $this->getAdapter()->fetchAll($select);
        
        if(!empty($range_arr)) {
            
                $update_query = "UPDATE salesorderdetail_temp SET freesampleqty = freesampleqty + ".$data['qty']."
                    WHERE salesorderdetail_temp.routekey = ".$data['route_key'] ."	AND salesorderdetail_temp.visitkey = ".$data['visit_key']." and
                    salesorderdetail_temp.transactionkey =".$data['transactionkey']." and itemcode ='".$data['item_code']."'";
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
            
            $insert ="INSERT INTO salesorderdetail_temp SET 
                            routekey 		= ".$data['route_key'] .",
                            visitkey 		= ".$data['visit_key'].",
                            transactionkey 	= ".$data['transactionkey'].",
                            itemcode 		= '".$data['item_code']."',
                            freesampleqty 	= ".$data['qty'].",
                            salesprice 		= ".$itemmaster[0]["sales_price"].",
                            returnprice 	= ".$itemmaster[0]["return_price"].",
                            salescaseprice	= ".$itemmaster[0]["sales_case_price"].",
                            returncaseprice = ".$itemmaster[0]["return_case_price"].",
                            goodreturncaseprice = ".$itemmaster[0]["defaultgoodreturncaseprice"].",
                            goodreturnprice		= ".$itemmaster[0]["defaultgoodreturnprice"].",
                            stdreturncaseprice	= ".$itemmaster[0]["return_case_price"].",
                            stdreturnprice		= ".$itemmaster[0]["return_price"].",
                            stdsalescaseprice	= ".$itemmaster[0]["sales_case_price"].",
                            stdsalesprice		= ".$itemmaster[0]["sales_price"];
            
            $this->_db->query($insert);
        }
        
//        $update_query = "UPDATE  salesorderdetail_temp SET freesampleqty = freesampleqty + ".$data['qty']."
//			  
//		    WHERE salesorderdetail_temp.routekey = ".$data['route_key'] ."	AND salesorderdetail_temp.visitkey = ".$data['visit_key']." and
//		    salesorderdetail_temp.transactionkey =".$data['transactionkey']." and itemcode ='".$data['item_code']."'";
//		    $this->_db->query($update_query);
	
    }

    public function remove_invoice_item($data=array())
    {
	$delete_query = "DELETE FROM salesorderdetail_temp WHERE salesorderdetail_temp.primary_key = ".$data['invoice_primary_key'];

		
	$this->_db->query($delete_query);
	
	$update_query ='UPDATE salesorderheader_temp, 
	( SELECT 
	SUM((FLOOR(id.salescaseprice) * FLOOR(salesqty/im.unitspercase))+(ROUND(id.salesprice,3) * REPLACE(ROUND((salesqty%im.unitspercase),3),",",""))) AS total_sales, 
	SUM((FLOOR(id.returncaseprice) * FLOOR(damagedqty/im.unitspercase))+(ROUND(id.returncaseprice,3) * REPLACE(ROUND((damagedqty%im.unitspercase),3),",",""))) AS total_damage, 
	SUM((FLOOR(id.goodreturncaseprice) * FLOOR(returnqty/im.unitspercase))+(ROUND(id.goodreturnprice,3) * REPLACE(ROUND((returnqty%im.unitspercase),3),",",""))) AS total_buyback, 
	SUM((FLOOR(id.salescaseprice) * FLOOR(freesampleqty/im.unitspercase))+(ROUND(id.salesprice,3) * REPLACE(ROUND((freesampleqty%im.unitspercase),3),",",""))) AS total_free_sample_qty 
	FROM salesorderdetail_temp AS id 
	LEFT JOIN itemmaster AS im ON im.actualitemcode =id.itemcode 
	JOIN salesorderheader_temp AS ih 
	WHERE ih.transactionkey = id.transactionkey AND ih.transactionkey = '.$data['transaction_key'].' ) AS a
	
	 SET salesorderheader_temp.totalsalesamount =IFNULL(a.total_sales,0) ,
	  salesorderheader_temp.totalinvoiceamount = (IFNULL(a.total_sales,0)-IFNULL(a.total_damage,0)), 
	  salesorderheader_temp.totalreturnamount =a.total_buyback, 
	  salesorderheader_temp.totaldamagedamount =a.total_damage
	WHERE salesorderheader_temp.transactionkey ='.$data['transaction_key'];
	   
	   
	$this->_db->query($update_query);
	
	
	
    }
  
  
  /**
   * Date : 5 Dec 2012
   * Pankil thakkar
   * Desc : For updating the sales header temp for promo amount
   *
   */
    public function update_promoamount($data=array())
    {
        $update_query ="UPDATE salesorderheader_temp SET totalpromoamount = (SELECT SUM(promotionamount) FROM promotiondetail_temp where  promotiondetail_temp.routekey =".$data['route_key'] ." AND 
                            promotiondetail_temp.visitkey = ".$data['visit_key']." AND promotiondetail_temp.transactionkey=".$data['transactionkey']." )
                        WHERE salesorderheader_temp.transactionkey  =".$data['transactionkey']." and visitkey = ".$data['visit_key'];
        
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
    public function seven_promotion_fixed($data=array())
    {
        if($data["repeatingrange_val"] == "0"){
            $pqty = "productgroupdetail.itemqty";
        } else {
            $pqty = "(".$data["promotion_fixed_amt"]." *productgroupdetail.itemqty)";
        }
/**
inner join inventorysummarydetail on productgroupdetail.itemcode = inventorysummarydetail.itemcode and inventorysummarydetail.routekey = " .$data["route_key"]. " 
*/      
	    $select = "select productgroupdetail.itemcode,".$pqty." As NewInvoiceAmount, (itemqty * itemmaster.defaultsalesprice) As SalesAmount,ifnull(itemqty,0) As   SalesQty,
                    (itemqty * productgroupdetail.promopcprice) As promoamount,promocaseprice,promopcprice
                from promokeydetail
                inner join productgroupdetail
                    on  promokeydetail.assignmentgroup = productgroupdetail.groupnumber
                inner join itemmaster
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
                            values(".$data['route_key'].",".$data['visit_key'].",".$data['transactionkey'].",'4','".$result[$i]["itemcode"]."',7,
                            ".round($result[$i]["NewInvoiceAmount"]).",".$result[$i]['SalesAmount'].",".$data['plannumber'].",".$data['assignment_no'].",
                            '0','1',".($result[$i]['SalesAmount']).",0,
                            ".$data["repeatingrange_val"]."
                            )";
            
            $this->_db->query($insert_query);
        }
    }
    
}