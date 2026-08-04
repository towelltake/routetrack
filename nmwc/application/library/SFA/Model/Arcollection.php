<?php
/**
 * @name       SFA_Model_Allitempromotion
 * @since      19-09-2011
 * @version    Release: 3
 * @author     GP
 * @copyright  Elan Technologies
 * @param   	
 * This Class contains all the Promtotion and invoice realate function for all item not match product group detail
 */

class SFA_Model_Arcollection extends Zend_Db_Table_Abstract
{

   
    /**
    * @name       get_record
    * @since      05-09-2012
    * @version    Release: 8
    * @author     GP
    * @copyright  Elan Technologies
    * Get all record related to invoice
    *
    */
    public function get_record($data=array())
    {
	$select = "SELECT  transactionkey, totalinvoiceamount, invoicebalance, invoicenumber   FROM customerinvoice where transactionkey in(".$data['transaction_key'].")";
	
	$result = $this->getAdapter()->fetchAll($select);
	
	return $result;
   
    }
    
     /**
    * @name       get_record
    * @since      05-09-2012
    * @version    Release: 8
    * @author     GP
    * @copyright  Elan Technologies
    * Get all record related to invoice
    *
    */
    public function get_record1($data=array())
    {
	$select  = "SELECT  transactionkey, totalinvoiceamount, invoicebalance, invoicenumber   FROM customerinvoice where ";
	$select .= " customercode = ".$data['ddlcustomer']." AND routecode = ".$data['ddlroute']." AND salesmancode =".$data['txtsalesman_code']."
	AND TRUNCATE(invoicebalance,0) != 0  order by transactiondate,invoicenumber desc";
	
	$result = $this->getAdapter()->fetchAll($select);
	return $result;
   
    }
    
    
    /**
    * @name       first_in_first_out
    * @since      05-09-2012
    * @version    Release: 8
    * @author     GP
    * @copyright  Elan Technologies
    * apply amount first in first come record
    *
    */
    public function first_in_first_out($formdata =array(), $keydata =array())
    {
	$cust_invo_result 	 = $this->get_record1($formdata);
	
	$var_amount 	= $formdata['txtamount'];
	
	foreach($cust_invo_result as $value)
	{
	   
	    if($var_amount > 0)
	    {
		    $amount 	  = $value['invoicebalance'];
		    $calul_amount = $var_amount - $amount;
		    if($calul_amount > 0) {
				$amount 	  	= $value['invoicebalance'];
				$new_balance    = $value['invoicebalance'] - $amount;
		    } else {
				$amount 	  	= $var_amount;
				$new_balance    = $value['invoicebalance'] - $var_amount;
	    	}
		    $var_amount  = $var_amount -  $amount;
		    $insert = "INSERT INTO ardetail 
			    (routekey,visitkey,transactionkey,invoicenumber,invoicedate,totalinvoiceamount, 
			    amountpaid, invoicebalance,arcollectiontype,chequestatusindicator, sapchequestatusindicator, 
			    currencycode
			    )
    			VALUES
			(".$keydata['new_route_key'].", ".$keydata['new_visit_key'].",".$keydata['new_transaction_key'].",
			    ".$value['invoicenumber'].",DATE(NOW()),'".$value['invoicebalance']."', '".$amount."', '".$new_balance."','0','0','0', 
			get_default_currencycode())";
			
			
		     $this->_db->query($insert);
		 
		    $update = "UPDATE customerinvoice SET amountpaid = amountpaid + ".$amount.",
			invoicebalance = $new_balance,
			pdcbalance=amountpaid + ".$amount."
			WHERE transactionkey =".$value['transactionkey'];
			
		    $this->_db->query($update);
		    
		    $qry 			= "SELECT arcustomertype FROM customermaster WHERE customercode = ".$formdata['ddlcustomer'];
			$result 		= $this->getAdapter()->fetchAll($qry);	
			$customer_type 	= $result[0]['arcustomertype'];
			
			$qry1 			= "SELECT `status` FROM controlpanel WHERE flagid = 36";
			$cpanel_status	= $this->getAdapter()->fetchAll($qry1);	
			$pdc_to_cash 	= $cpanel_status[0]['status'];
			
			if($customer_type == 2 || $customer_type == 3 || ($pdc_to_cash == 1 && $customer_type == 4))
				$update = "UPDATE customermaster  SET balance  = balance  - ".$amount." WHERE customercode =".$formdata['ddlcustomer'];		
			
		    $this->_db->query($update);
	    }
	}
    }
    
     /**
    * @name       custom_payment
    * @since      05-09-2012
    * @version    Release: 8
    * @author     GP
    * @copyright  Elan Technologies
    * Apply custom payment record wise
    *
    */
    public function custom_payment($transaction_key_arr= array(),$amount_arr=array(),$formdata =array(), $keydata =array())
    {
	$data = array();
	$data['transaction_key'] = implode(",",$transaction_key_arr);
	$cust_invo_result = $this->get_record($data);
	
	if(!empty($cust_invo_result))
	{
	    foreach($cust_invo_result as $value)
	    {
		
		$amount  		= 0;
		$key_id  		= array_keys($transaction_key_arr,$value['transactionkey']);
		$amount  		= str_replace(",","",$amount_arr[$key_id[0]]);
		$new_balance 	= str_replace(",","",$value['invoicebalance']) - $amount;
		$pdcbal			= 0;
		
		if($formdata['ddlpaymode'] == 1){
			
			$pdcbal = $formdata['txtamount'];
			
			$insert = "INSERT INTO ardetail 
			    (routekey,visitkey,transactionkey,invoicenumber,invoicedate,totalinvoiceamount, 
			    pdcbalance, invoicebalance,arcollectiontype,chequestatusindicator, sapchequestatusindicator, 
			    currencycode
			    )
    			VALUES
			(".$keydata['new_route_key'].", ".$keydata['new_visit_key'].",".$keydata['new_transaction_key'].",
			    ".$value['invoicenumber'].",DATE(NOW()),'".$value['invoicebalance']."', '".$pdcbal."', '".$new_balance."','0','0','0', 
			get_default_currencycode())";
			
		} else {
			$insert = "INSERT INTO ardetail 
			    (routekey,visitkey,transactionkey,invoicenumber,invoicedate,totalinvoiceamount, 
			    amountpaid, invoicebalance,arcollectiontype,chequestatusindicator, sapchequestatusindicator, 
			    currencycode
			    )
    			VALUES
			(".$keydata['new_route_key'].", ".$keydata['new_visit_key'].",".$keydata['new_transaction_key'].",
			    ".$value['invoicenumber'].",DATE(NOW()),'".$value['invoicebalance']."', '".$amount."', '".$new_balance."','0','0','0', 
			get_default_currencycode())";	
		}
		
		
			
		 $this->_db->query($insert);
		 
		 
		 
		$update = "UPDATE customerinvoice SET amountpaid = amountpaid + ".$amount.",
			invoicebalance = $new_balance,
			pdcbalance=amountpaid + ".$amount."
			WHERE transactionkey =".$value['transactionkey'];
			
		$this->_db->query($update);
		
		$update = "UPDATE customermaster  SET balance  = balance  - ".$amount."
			WHERE customercode =".$formdata['ddlcustomer'];
		
		    $this->_db->query($update);
	    }
	}
	
    }
    
    /**
    * @name       generate_key_data
    * @since      05-09-2012
    * @version    Release: 8
    * @author     GP
    * @copyright  Elan Technologies
    * this function call generate doucument no and routekey function and return array of all type of key
    *
    */
    public function generate_key_data($formdata =array())
    {
	$key_data  = $this->genereate_document_no($formdata);
	$key_data1 = $this->generate_routekey($formdata,$key_data);
	
	return $key_data1;
    }
    
    /**
    * @name       genereate_document_no
    * @since      05-09-2012
    * @version    Release: 8
    * @author     GP
    * @copyright  Elan Technologies
    * generate document number and collection number
    *
    */
    public function genereate_document_no($formdata =array())
    {
	$update = "UPDATE routemaster SET  boordseq = IFNULL(boordseq,0) + 1, bodocseq = IFNULL(bodocseq,0) + 1
		    WHERE  routecode =".$formdata['ddlroute'];
		    
	$this->_db->query($update);
	
	$select = "SELECT get_number_type_base('".$formdata['ddlroute']."',4) as invoice_seq,get_number_type_base('".$formdata['ddlroute']."',2) as document_seq";
	
	$result = $this->getAdapter()->fetchAll($select);
	
	$keydata['new_document_no'] = $result[0]['document_seq'];
	$keydata['new_invoice_no'] =  $result[0]['invoice_seq'];
	
	return $keydata;
	
    }
    
    /**
    * @name       generate_routekey
    * @since      05-09-2012
    * @version    Release: 8
    * @author     GP
    * @copyright  Elan Technologies
    * generate routekey and visit key
    *
    */
    public function generate_routekey($formdata =array(),$key_data1=array())
    {
	//$key_data =array();
	//$key_data = array_merge($key_data,$key_data1);
	
	$select = "SELECT routekey  FROM startendday  WHERE startendday.routecode =".$formdata['ddlroute']."
		    AND startendday.routeclosed = 0 ORDER BY routekey DESC  LIMIT 1	";
	  
	$result = $this->getAdapter()->fetchAll($select);
	
	$route_key  =$result[0]['routekey'];
	$select = "SELECT IFNULL(MAX(customeroperationscontrol.visitkey),0) as in_visit_key1 FROM customeroperationscontrol
		    WHERE  customeroperationscontrol.routekey =".$route_key;
		
	$result1 = $this->getAdapter()->fetchAll($select);
	
	$in_visit_key1 =$result1[0]['in_visit_key1'] + 1;
	
	
	$insert = "INSERT INTO customeroperationscontrol (visitkey,routekey,customercode,salesmancode,routecode,visitstartdate,
	visitstarttime,visitenddate,visitendtime,totaltransactions,voidflag)
	VALUE(".$in_visit_key1.",".$route_key.",'".$formdata['ddlcustomer']."',".$formdata['txtsalesman_code'].",".$formdata['ddlroute'].",DATE(NOW()),TIME(NOW()),
	DATE(NOW()),TIME(NOW()),1,0)";
	
	$this->_db->query($insert);
	
	$key_data1['new_visit_key'] = $in_visit_key1;
	$key_data1['new_route_key'] = $route_key;
	
	return $key_data1;
    }
    
    /**
    * @name       add_arheader
    * @since      05-09-2012
    * @version    Release: 8
    * @author     GP
    * @copyright  Elan Technologies
    * add entry into arheader table
    *
    */
    public function add_arheader($formdata=array(),$generate_key_arr=array(),$customer_invoice_arr=array(),$summery_arr=array())
    {
	
	$insert = "INSERT INTO arheader 
	(	routekey,visitkey,documentnumber,transactiondate,transactiontime,customercode, 
		routecode,salesmancode,voidflag,totalinvoiceamount,amountpaid, 
		invoicebalance,invoicenumber,currencycode,comments,advancepaymentflag
	)
	VALUES
	(".$generate_key_arr['new_route_key'].",".$generate_key_arr['new_visit_key'].",".$generate_key_arr['new_document_no'].",
	    date(NOW()),TIME(NOW()),'".$formdata['ddlcustomer']."','".$formdata['ddlroute']."', 
	'".$formdata['txtsalesman_code']."','0','".$formdata['txtamount']."','".$formdata['txtamount']."','".$formdata['txtamount']."', 
	".$generate_key_arr['new_invoice_no'].",get_default_currencycode(),'".$formdata['txtremark1']."',0)";
	
	
	$this->_db->query($insert);
	$transaction_key =  $this->_db->lastInsertId();

	$generate_key_arr['new_transaction_key'] = $transaction_key;
	
	$insert ="INSERT INTO cashcheckdetail
	(		routekey,visitkey,typecode,checknumber,amount,checkdate,bankcode,currencycode,
			hhctransactionkey,transactiontype)
	    	VALUES(	".$generate_key_arr['new_route_key'].",".$generate_key_arr['new_visit_key'].",".$formdata['ddlpaymode'].",
		    '".$formdata['txtcheckno']."','".$formdata['txtamount']."',
		'".$formdata['txtcheckdt']."',
		'".$formdata['ddlbankname']."',get_default_currencycode(),$transaction_key,2)";
	
	$this->_db->query($insert);
	return $generate_key_arr;

    }
    
}