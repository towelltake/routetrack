<?php
/**
 * @name       SFA_Model_Allitempromotion
 * @since      19-09-2011
 * @version    Release: 8
 * @author     GP
 * @copyright  Elan Technologies
 * @param   	
 * This Class contains all the Promtotion and invoice realate function for all item not match product group detail
 */

class SFA_Model_Orderallitempromotion extends Zend_Db_Table_Abstract
{

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
	FROM promokeydetail,itemmaster
	
	INNER JOIN salesorderdetail_temp ON itemmaster.actualitemcode = salesorderdetail_temp.itemcode
	AND salesorderdetail_temp.routekey =".$data['route_key'] ."
	AND salesorderdetail_temp.visitkey =".$data['visit_key']."
	WHERE
	itemmaster.actualitemcode = salesorderdetail_temp.itemcode  and 
	promokeydetail.assignmentnumber = ".$data['assignment_no']." 
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
				    
				   SELECT ".$data['route_key'].",".$data['visit_key'].",".$data['transactionkey'].",4, salesorderdetail_temp.itemcode,0,
				   (((salesqty / itemmaster.unitspercase)  *
					    (salesorderdetail_temp.salescaseprice)) +
					    ((salesqty % itemmaster.unitspercase)  * salesorderdetail_temp.salesprice)) AS promoamount, 
				 REPLACE(FORMAT((((salesqty / itemmaster.unitspercase)  *
				     (salesorderdetail_temp.salescaseprice)) +
				     ((salesqty % itemmaster.unitspercase)  * salesorderdetail_temp.salesprice)),2),',','') AS SalesAmount,
				  ".$data['plannumber'].",".$data['assignment_no'].",'0','1',
				    REPLACE(FORMAT( (((salesqty / itemmaster.unitspercase)  *
				    (IF (salesorderdetail_temp.salescaseprice IS NULL, itemmaster.caseprice ,salesorderdetail_temp.salescaseprice)))
				    +  ((salesqty % itemmaster.unitspercase)  * salesorderdetail_temp.salesprice)),2),',','') AS SalesAmount,
				   ".$range_arr[0]['rangelow'].",".$range_arr[0]['repeatingrange']."
	
	 
			    FROM promokeydetail ,itemmaster
			    INNER JOIN salesorderdetail_temp ON itemmaster.actualitemcode = salesorderdetail_temp.itemcode 
							      AND salesorderdetail_temp.routekey = ".$data['route_key']."
							   AND salesorderdetail_temp.visitkey  = ".$data['visit_key']."
			    WHERE
			    itemmaster.actualitemcode = salesorderdetail_temp.itemcode  and 
			    promokeydetail.assignmentnumber = ".$data['assignment_no']." 
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
			    
			$update_query=  "UPDATE salesorderdetail_temp  ,promotiondetail_temp
					 SET salesorderdetail_temp.promoamount = IFNULL(salesorderdetail_temp.promoamount,0) + promotiondetail_temp.promotionamount,
					 promovalue = IFNULL(promovalue,0) + promotiondetail_temp.promotionamount ,
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
			salesorderdetail_temp.itemcode, IFNULL(salesorderdetail_temp.salesqty,0) AS SalesQty
			
			FROM promokeydetail,itemmaster
			
			INNER JOIN salesorderdetail_temp ON itemmaster.actualitemcode = salesorderdetail_temp.itemcode 
    				 AND salesorderdetail_temp.routekey =".$data['route_key'] ."
				 AND salesorderdetail_temp.visitkey  =".$data['visit_key']."
				
				
			WHERE
			itemmaster.actualitemcode = salesorderdetail_temp.itemcode  and 
			promokeydetail.assignmentnumber = ".$data['assignment_no']." 
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
			salesorderdetail_temp.itemcode, IFNULL(salesorderdetail_temp.salesqty,0) AS SalesQty
			
			FROM promokeydetail,itemmaster
			
			INNER JOIN salesorderdetail_temp ON itemmaster.actualitemcode = salesorderdetail_temp.itemcode 
    				 AND salesorderdetail_temp.routekey =".$data['route_key'] ."
				 AND salesorderdetail_temp.visitkey  =".$data['visit_key']."
				
				
			WHERE
			itemmaster.actualitemcode = salesorderdetail_temp.itemcode  and 
			promokeydetail.assignmentnumber = ".$data['assignment_no']." 
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
			$promotion_amount 	= $range_arr[0]['rangelow'] * ($prmotion_calculate_arr[0] * ($range_arr[0]['promotionamount']/100));
			
			$promotion  = $promotion_amount;
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
			//$prmotion_cal 		= $value['SalesAmount']/$range_arr[0]['rangelow'];
			//$prmotion_calculate_arr = explode(".",$prmotion_cal);
			$promotion  = (($value['SalesAmount'] * ($range_arr[0]['promotionamount']/100) ));
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
   
	$select = "SELECT GROUP_CONCAT(salesorderdetail_temp.itemcode) as itemcode,
		FORMAT(SUM( IF(sales_amount = '0.0000', (FLOOR(salesqty / itemmaster.unitspercase) *
		salesorderdetail_temp.salescaseprice
		+ ((salesqty % itemmaster.unitspercase) * salesorderdetail_temp.salesprice)) ,sales_amount)),4) AS SalesAmount,
		SUM(IFNULL(salesorderdetail_temp.salesqty,0)) AS SalesQty

		FROM salesorderdetail_temp
		INNER JOIN itemmaster ON itemmaster.actualitemcode  = salesorderdetail_temp.itemcode
		
								   
		WHERE 		 salesorderdetail_temp.routekey =".$data['route_key'] ."
				 AND salesorderdetail_temp.visitkey  =".$data['visit_key'];
		
	$result = $this->getAdapter()->fetchAll($select);
	
	if(!empty($result))
	{
	    $final_amount ="0";
	    $result[0]['SalesQty'] = str_replace(",","",$result[0]['SalesQty']);
	    $result[0]['SalesAmount'] = str_replace(",","",$result[0]['SalesAmount']);
	   
	    if(in_array("5",$promotiontypecode))
	    {
		foreach($promotion5 as $promot5val)
		{
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
			 if($range_arr[0]['repeatingrange'] == 1)
			{
			   $per_value  			= $range_arr[0]['promotionamount'];
			    $prmotion_cal 		= $data['sales_amt_org']/$range_arr[0]['rangelow'];
			    $prmotion_calculate_arr = explode(".",$prmotion_cal);
			    $promotion_amount 	=  ($prmotion_calculate_arr[0] * $range_arr[0]['promotionamount']);
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
			    $per_value  			= $range_arr[0]['promotionamount'];
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
			
		    }
		   // echo $insert_query;
		    $range_arr =array();
		}
		
		//echo "ddd";exit;
	    }
	   
	    if(in_array("6",$promotiontypecode))
	    {
		
		if($final_amount == 0 )
		$final_amount = $result[0]['SalesAmount'];
		
		foreach($promotion6 as $promot6val)
		{
		    $data['sales_qnt_org'] = ($data['sales_qnt_org'] != "") ? str_replace(",","",$data['sales_qnt_org']):0;
            $data['sales_amt_org'] = ($data['sales_amt_org'] != "") ? str_replace(",","",$data['sales_amt_org']):0;
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
	    
	    $update_query ="update salesorderheader_temp set totalpromoamount = (SELECT SUM(promotionamount) FROM promotiondetail_temp where  promotiondetail_temp.routekey =".$data['route_key'] ." AND 
			promotiondetail_temp.visitkey = ".$data['visit_key']." AND promotiondetail_temp.transactionkey=".$data['transactionkey']." )
	    where salesorderheader_temp.transactionkey  =".$data['transactionkey']." and visitkey = ".$data['visit_key'];
	    
	   
	     $this->_db->query($update_query);
	     
	    
	    
	   	  
	}
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
			+ ((salesqty % itemmaster.unitspercase) * salesorderdetail_temp.salesprice)),4) ,sales_amount)AS SalesAmount,
			productgroupdetail.itemcode, IFNULL(salesorderdetail_temp.salesqty,0) AS SalesQty
	*/
	$select  ="    SELECT salesorderdetail_temp.itemcode as itemcode,
			FORMAT(( IF(sales_amount = '0.0000', ((salesqty / itemmaster.unitspercase) *
		salesorderdetail_temp.salescaseprice 
		+ ((salesqty % itemmaster.unitspercase) * salesorderdetail_temp.salesprice)) ,sales_amount)),4) AS SalesAmount,
		(IFNULL(salesorderdetail_temp.salesqty,0)) AS SalesQty
			FROM promokeydetail,itemmaster
			
			INNER JOIN salesorderdetail_temp ON itemmaster.actualitemcode = salesorderdetail_temp.itemcode 
    				 AND salesorderdetail_temp.routekey =".$data['route_key'] ."
				 AND salesorderdetail_temp.visitkey  =".$data['visit_key']."
				
			WHERE
			itemmaster.actualitemcode = salesorderdetail_temp.itemcode  and 
			promokeydetail.assignmentnumber = ".$data['assignment_no']." 
			AND promokeydetail.promotionkey = (SELECT promotionkey FROM customermaster WHERE customercode = ".$data['customer_code'].") 
		       AND promokeydetail.plannumber = ".$data['plannumber'];
		       
		     //echo $select;exit;
	$result = $this->getAdapter()->fetchAll($select);
	if(!empty($result))
	{
	    foreach($result as $value)
	    {
            $data['sales_qnt_org'] = ($data['sales_qnt_org'] != "") ? str_replace(",","",$data['sales_qnt_org']):0;
            $data['sales_amt_org'] = ($data['sales_amt_org'] != "") ? str_replace(",","",$data['sales_amt_org']):0;
            $value['SalesQty'] = str_replace(",","",$value['SalesQty']);
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
		   
		
		}
		
	    }
	   	  
	}
	
	
    }
  
}