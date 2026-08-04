<?php
/*
 * FileName     : DataSyncImport.php
 * Owner        : v.nair@mirnah.com
 * Created      : 09/09/2015
 * Description  : Getting data tables from Oracle and Updating DB 
 */
class SFA_DataSyncImport {

    private $loginName = "Invalid";
    
    public function __construct() {
        //echo "Oracle Sync Data<br>";
        set_time_limit(0);
        $this->SFA_Comman = new SFA_Comman();
    }

    public function setUserName($loginName) {
        //echo "setUserName <br>";
        $this->loginName = $loginName;
    }

    public function updateDBFromOracle() {
        //echo "updateDBFromOracle <br>";
        //echo date("d-m-Y h:i:s") . " Start Time <br>";
		
		$this->cleanTablesAndInsertDefaultEntry();
        $this->insertEmployee();
        $this->insertItemMaster();
        $this->insertPricing();
        $this->insertCustomerMaster();
        $this->insertCustomerInvoice();
        $this->insertStartingLoadDetail();		
		$this->insertDelivery();
        $this->insertWarehouseStock();
		
		//Data Audit Part.
		$this->insertTodaysInvoiceDetail();
    }
	
	public function loadFromOracle() {
       
       $this->insertStartingLoadDetail();        
    }
	
    private function cleanTablesAndInsertDefaultEntry() {
        $this->SFA_Comman->executequery('CALL int_sp_clear_tables()', null);
        //$this->SFA_Comman->executequery('CALL int_sp_default_tables()', null);
    }

    private function insertEmployee() {
        //echo date("d-m-Y h:i:s") . " insertEmployee Entry <br>";
    	$ociQuery = "SELECT count(EMPLOYEECODE) as COUNT FROM IMP_EMPLOYEE";
        $result = $this->SFA_Comman->ociexecutequery($ociQuery);
        $count = $result[0][0]['COUNT'];
        $start = 0;
        while($count > 0) {
            $sql = "";
            $start = $count - 250;
            if($start < 0 )
                $start = 0;
      $ociQuery = "SELECT * FROM (SELECT employee.* , rownum rnum FROM (SELECT EMPLOYEECODE, SUBSTR(EMPLOYEENAME,1,50) as EMPLOYEENAME, SUBSTR(ARBEMPLOYEENAME,1,50) as ARBEMPLOYEENAME, JOBTITLE, MAPPINGCODE, SUBSTR(MEMO1,1,50) as MEMO1, SUBSTR(MEMO2,1,50) as MEMO2,ACTIVESTATUS, STATUS,RECORDMODE FROM IMP_EMPLOYEE) employee WHERE rownum <= $count order by rownum ASC) WHERE rnum >= $start order by rnum ASC";
            $result = $this->SFA_Comman->ociexecutequery($ociQuery);
            $sql = "INSERT INTO imp_employee VALUES ";
            foreach($result as $row) {
                $param_array = array();
          $param_array[1] = $row[0]['EMPLOYEECODE'];
          $param_array[2] = '"' .$row[0]['EMPLOYEENAME'] .'"';
          $param_array[3] = '"' .$row[0]['ARBEMPLOYEENAME'] .'"';
          $param_array[4] = '"' .$row[0]['JOBTITLE'] .'"';
          $param_array[5] = '"' .$row[0]['MAPPINGCODE'] .'"';
          $param_array[6] = '"' .$row[0]['MEMO1'] .'"';
          $param_array[7] = '"' .$row[0]['MEMO2'] .'"';
          $param_array[8] = $row[0]['ACTIVESTATUS'];
          $param_array[9] = $row[0]['STATUS'];
          $param_array[10] = $row[0]['RECORDMODE'];
                $sqlArray = implode(",", $param_array);
                $sql = $sql ."(" .$sqlArray ."),";
            }
            $sql = rtrim($sql, ",");
            $result = $this->executeQuery($sql,'imp_employee');
            $count = $start-1;
        }
        $result = $this->SFA_Comman->executequery('CALL int_imp_Employee()',null);
        //echo date("d-m-Y h:i:s") . " insertEmployee Exit <br>";
    }
	
    private function insertItemMaster(){
        //echo date("d-m-Y h:i:s") . " insertItemMaster Entry <br>";
        $ociQuery = "SELECT count(ITEMID) as COUNT FROM IMP_ITEMMASTER";
        $result = $this->SFA_Comman->ociexecutequery($ociQuery);
        $count = $result[0][0]['COUNT'];
        $start = 0;
        while($count > 0) {
            $sql = "";
            $start = $count - 250;
            if($start < 0 )
                $start = 0;
      $ociQuery = "SELECT * FROM (SELECT itemmaster.* , rownum rnum FROM (SELECT ITEMID, ITEMCODE, SUBSTR(ITEMDESCRIPTION,1,50) as ITEMDESCRIPTION, SUBCATEGORYID, SUBCATEGORY, SUBCATEGORYNAME, CATEGORYID, CATEGORY, CATEGORYNAME, BRANDID, BRAND, BRANDNAME, BRANDGROUPCODE, BRANDGROUPNAME, ITEMPACKING, ARBITEMDESCRIPTION, UNITSPERCASE, MEMO1, MEMO2, CASEPRICE, EACHPRICE, BARCODEEACH, BARCODECARTON, OTHERBARCODE, ACTIVESTATUS, STATUS, RECORDMODE, ATTRIBUTE1 FROM IMP_ITEMMASTER) itemmaster WHERE rownum <= $count order by rownum ASC) WHERE rnum >= $start order by rnum ASC";
            $result = $this->SFA_Comman->ociexecutequery($ociQuery);
            $sql = "INSERT INTO imp_itemmaster VALUES ";
            foreach($result as $row) {
          $param_array = array();
          $param_array[1] = $row[0]['ITEMID'];
          $param_array[2] = '"' .$row[0]['ITEMCODE'] .'"';
          $param_array[3] = '"' .$row[0]['ITEMDESCRIPTION'] .'"';
          $param_array[4] = $row[0]['SUBCATEGORYID'];
          $param_array[5] = '"' .$row[0]['SUBCATEGORY'] .'"';
          $param_array[6] = '"' .$row[0]['SUBCATEGORYNAME'] .'"';
          $param_array[7] = $row[0]['CATEGORYID'];
          $param_array[8] = '"' .$row[0]['CATEGORY'] .'"';
          $param_array[9] = '"' .$row[0]['CATEGORYNAME'] .'"';
          $param_array[10] = $row[0]['BRANDID'];
          $param_array[11] = '"' .$row[0]['BRAND'] .'"';
          $param_array[12] = '"' .$row[0]['BRANDNAME'] .'"';
          $param_array[13] = '"' .$row[0]['BRANDGROUPCODE'] .'"';
          $param_array[14] = '"' .$row[0]['BRANDGROUPNAME'] .'"';
          $param_array[15] = '"' .$row[0]['ITEMPACKING'] .'"';
          $param_array[16] = '"' .$row[0]['ARBITEMDESCRIPTION'] .'"';
          $param_array[17] = $row[0]['UNITSPERCASE'];
          $param_array[18] = '"' .$row[0]['MEMO1'] .'"';
          $param_array[19] = '"' .$row[0]['MEMO2'] .'"';
          $param_array[20] = $row[0]['CASEPRICE'];
          $param_array[21] = $row[0]['EACHPRICE'];
          $param_array[22] = '"' .$row[0]['BARCODEEACH'] .'"';
          $param_array[23] = '"' .$row[0]['BARCODECARTON'] .'"';
          $param_array[24] = '"' .$row[0]['OTHERBARCODE'] .'"';
          $param_array[25] = $row[0]['ACTIVESTATUS'];
          $param_array[26] = $row[0]['STATUS'];
          $param_array[27] = $row[0]['RECORDMODE'];
		  $param_array[28] = '"' .$row[0]['ATTRIBUTE1'] .'"';
		  
                $sqlArray = implode(",", $param_array);
                $sql = $sql ."(" .$sqlArray ."),";
            }
            $sql = rtrim($sql, ",");
            $result = $this->executeQuery($sql,'imp_itemmaster');
            $count = $start-1;
        }
        $result = $this->SFA_Comman->executequery('CALL int_imp_ItemMaster()',null);
        //echo date("d-m-Y h:i:s") . " insertItemMaster Exit <br>";
    }
    
    private function insertPricing() {
        //echo date("d-m-Y h:i:s") . " insertPricing Entry <br>";
        $ociQuery = "SELECT count(ITEMID) as COUNT FROM IMP_CUSTOMERSPECIALPRICE";
        $result = $this->SFA_Comman->ociexecutequery($ociQuery);
        $count = $result[0][0]['COUNT'];
        $start = 0;$i= 0;
        while($count > 0) {
            $sql = "";
            $start = $count - 250;
            if($start < 0 )
                $start = 0;
            $ociQuery = "SELECT * FROM (SELECT specialprice.* , rownum rnum FROM (SELECT PRICINGKEY, ITEMID, SUBSTR(DESCRIPTION,1,50) as DESCRIPTION, STARTDATE, ENDDATE, EACHPRICE,PRICETYPE, STATUS, RECORDMODE FROM IMP_CUSTOMERSPECIALPRICE) specialprice WHERE rownum <= $count order by rownum ASC) WHERE rnum >= $start order by rnum ASC";
            $result = $this->SFA_Comman->ociexecutequery($ociQuery);
            $sql = "INSERT INTO imp_customerspecialprice VALUES ";
            foreach($result as $row) {
                $startDate = $row[0]['STARTDATE'];
                $startDate = "STR_TO_DATE('$startDate','%d-%b-%y')";
                $endDate = $row[0]['ENDDATE'];
                $endDate = "STR_TO_DATE('$endDate','%d-%b-%y')";
                $description = "'" .$row[0]['DESCRIPTION'] ."'";
                $pricetype = "'" .$row[0]['PRICETYPE'] ."'";
                $array = array($row[0]['PRICINGKEY'], $row[0]['ITEMID'], $description, $startDate, $endDate, $row[0]['EACHPRICE'], $pricetype, $row[0]['STATUS'], $row[0]['RECORDMODE'], '0');
                $sqlArray = implode(",", $array);
                $sql = $sql ."(" .$sqlArray ."),";
            }
            $sql = rtrim($sql, ",");
            $result = $this->executeQuery($sql,++$i);
            $count = $start-1;
        }
        $result = $this->SFA_Comman->executequery('CALL int_imp_CustomerSpecialPrice()',null);
        //echo date("d-m-Y h:i:s") . " insertPricing Exit <br>";
    }

	private function insertDelivery() {
        //$ociQuery = "SELECT count(DELIVERYNO) as COUNT FROM IMP_DELIVERY WHERE TO_DATE(deliverydate,'DD-MM-YYYY') = TO_DATE(SYSDATE,'DD-MM-YYYY')";
		$ociQuery = "SELECT count(del.DELIVERYNO) as COUNT FROM IMP_DELIVERY del INNER JOIN IMP_EMPLOYEE emp on emp.EMPLOYEECODE = del.SALESMAN AND emp.MAPPINGCODE NOT LIKE '%VAN%'";
        $result = $this->SFA_Comman->ociexecutequery($ociQuery);
        $count = $result[0][0]['COUNT'];
        $start = 0;$i= 0;
        while($count > 0) {
            $sql = "";
            $start = $count - 250;
            if($start < 0 )
                $start = 0;
            //$ociQuery = "SELECT * FROM (SELECT delivery.* , rownum rnum FROM (SELECT DELIVERYNO,ORDERNO,CUSTOMERCODE,ROUTE,DELIVERYDATE,SALESMAN,TOTALAMOUNT,ITEMID,ORDERQTY,DELIVERQTY,ORDERFOCQTY,DELIVERFOCQTY,ATTRIBUTE1,ATTRIBUTE2,ATTRIBUTE3,ATTRIBUTE4,ATTRIBUTE5,ORDERSTATTUS FROM IMP_DELIVERY WHERE TO_DATE(deliverydate,'DD-MM-YYYY') = TO_DATE(SYSDATE,'DD-MM-YYYY')-10) delivery WHERE rownum <= $count order by rownum ASC) WHERE rnum >= $start order by rnum ASC";
			$ociQuery = "SELECT * FROM (SELECT delivery.* , rownum rnum FROM (SELECT del.DELIVERYNO,del.ORDERNO,del.CUSTOMERCODE,del.ROUTE,del.DELIVERYDATE,del.SALESMAN,del.TOTALAMOUNT,del.ITEMID,del.ORDERQTY,del.DELIVERQTY,del.ORDERFOCQTY,del.DELIVERFOCQTY,del.ATTRIBUTE1,del.ORDERSTATTUS FROM IMP_DELIVERY del INNER JOIN IMP_EMPLOYEE emp on emp.EMPLOYEECODE = del.SALESMAN AND emp.MAPPINGCODE NOT LIKE '%VAN%') delivery WHERE rownum <= $count order by rownum ASC) WHERE rnum >= $start order by rnum ASC";
            $result = $this->SFA_Comman->ociexecutequery($ociQuery);
            $sql = "INSERT INTO imp_delivery VALUES ";
            foreach($result as $row) {
                $deliverydate = $row[0]['DELIVERYDATE'];
                $deliverydate = "STR_TO_DATE('$deliverydate','%d-%b-%y')";                
                $orderstatus = "'" .$row[0]['ORDERSTATTUS'] ."'";
                $attribute1 = "'" .$row[0]['ATTRIBUTE1'] ."'";
				$attribute2 = "''";
                $array = array($row[0]['DELIVERYNO'], $row[0]['ORDERNO'], $row[0]['CUSTOMERCODE'], 0, $deliverydate, $row[0]['SALESMAN'], $row[0]['TOTALAMOUNT'], $row[0]['ITEMID'], $row[0]['ORDERQTY'], $row[0]['DELIVERQTY'], 0, 0, $attribute1, $attribute2, $attribute2, $attribute2, $attribute2, $orderstatus);
                $sqlArray = implode(",", $array);
                $sql = $sql ."(" .$sqlArray ."),";
            }
            $sql = rtrim($sql, ",");

            $result = $this->executeQuery($sql,++$i);
            $count = $start-1;
        }
		
		$result = $this->SFA_Comman->executequery('CALL int_imp_Delivery()',null);
    }
	
	private function insertWarehouseStock() {
        $ociQuery = "SELECT count(ITEMID) as COUNT FROM IMP_WHSTOCK";
        $result = $this->SFA_Comman->ociexecutequery($ociQuery);
        $count = $result[0][0]['COUNT'];
        $start = 0;$i= 0;
        while($count > 0) {
            $sql = "";
            $start = $count - 250;
            if($start < 0 )
                $start = 0;
            $ociQuery = "SELECT * FROM (SELECT wh.* , rownum rnum FROM (SELECT ITEMID,QUANTITY,ATTRIBUTE2,ATTRIBUTE3,ATTRIBUTE4,ATTRIBUTE5 FROM IMP_WHSTOCK) wh WHERE rownum <= $count order by rownum ASC) WHERE rnum >= $start order by rnum ASC";
            $result = $this->SFA_Comman->ociexecutequery($ociQuery);
            $sql = "INSERT INTO imp_whstock VALUES ";
            foreach($result as $row) {                
                $attribute1 = "'" .$row[0]['ATTRIBUTE1'] ."'";
				$attribute2 = "''";
                $array = array($row[0]['ITEMID'], $row[0]['QUANTITY'], $attribute1, $attribute1, $attribute2, $attribute2, $attribute2);
                $sqlArray = implode(",", $array);
                $sql = $sql ."(" .$sqlArray ."),";
            }
            $sql = rtrim($sql, ",");
			
            $result = $this->executeQuery($sql,++$i);
            $count = $start-1;
        }
		
		$result = $this->SFA_Comman->executequery('CALL int_imp_Warehousestock()',null);
    }
	
    private function insertCustomerMaster(){
        //echo date("d-m-Y h:i:s") . " insertCustomerMaster Entry <br>";
    	$ociQuery = "SELECT count(CUSTOMERCODE) as COUNT FROM IMP_CUSTOMERMASTER";
        $result = $this->SFA_Comman->ociexecutequery($ociQuery);
        $count = $result[0][0]['COUNT'];
        $start = 0;
        while($count > 0) {
            $sql = "";
            $start = $count - 50;
            if($start < 0 )
                $start = 0;
      $ociQuery = "SELECT * FROM (SELECT customermaster.* , rownum rnum FROM (SELECT CUSTOMERCODE, ALTERNATECODE, ROUTECODE, SUBSTR(CUSTOMERNAME,1,50) as CUSTOMERNAME, SUBSTR(ARBCUSTOMERNAME,1,50) as ARBCUSTOMERNAME, SUBSTR(CUSTOMERADDRESS1,1,50) as CUSTOMERADDRESS1, SUBSTR(CUSTOMERADDRESS2,1,50) as CUSTOMERADDRESS2, SUBSTR(CUSTOMERADDRESS3,1,50) as CUSTOMERADDRESS3, SUBSTR(CONTACTNAME,1,50) as CONTACTNAME, SUBSTR(PHONENUMBER,1,50) as PHONENUMBER, CUSTOMERCATEGORY, CUSTOMERCHANNEL, SUBSTR(CUSTOMERCLASS,1,50) as CUSTOMERCLASS, CUSTOMERREFERENCENO, PRICINGKEY, INVOICEPAYMENTTERMS, CREDITLIMITAMOUNT, CREDITLIMITDAYS, CREDITHOLD, SUBSTR(MEMO1,1,50) as MEMO1, SUBSTR(MEMO2,1,50) as MEMO2, ACTIVECUSTOMER, STATUS, RECORDMODE, ATTRIBUTE1, ATTRIBUTE2, ATTRIBUTE3, ATTRIBUTE4, ATTRIBUTE5 FROM IMP_CUSTOMERMASTER) customermaster WHERE rownum <= $count order by rownum ASC) WHERE rnum >= $start order by rnum ASC";
      $result = $this->SFA_Comman->ociexecutequery($ociQuery);
      $sql = "INSERT INTO imp_customermaster VALUES ";
      foreach($result as $row) {
          $param_array = array();
          $param_array[1] = $row[0]['CUSTOMERCODE'];
          $param_array[2] = '"' .$row[0]['ALTERNATECODE'] .'"';
          $param_array[3] = $row[0]['ROUTECODE'];
          $param_array[4] = '"' .str_replace("\"","`",$row[0]['CUSTOMERNAME'] ).'"';
          $param_array[5] = '"' .$row[0]['ARBCUSTOMERNAME'] .'"';
          $param_array[6] = '"' .$row[0]['CUSTOMERADDRESS1'] .'"';
          $param_array[7] = '"' .$row[0]['CUSTOMERADDRESS2'] .'"';
          $param_array[8] = '"' .$row[0]['CUSTOMERADDRESS3'] .'"';
          $param_array[9] = '"' .$row[0]['CONTACTNAME'] .'"';
          $param_array[10] = '"' .$row[0]['PHONENUMBER'] .'"';
          $param_array[11] = '"' .$row[0]['CUSTOMERCATEGORY'] .'"';
          $param_array[12] = '"' .$row[0]['CUSTOMERCHANNEL'] .'"';
          $param_array[13] = '"' .$row[0]['CUSTOMERCLASS'] .'"';
          $param_array[14] = '"' .$row[0]['CUSTOMERREFERENCENO'] .'"';
          $param_array[15] = '"' .$row[0]['PRICINGKEY'] .'"';
          $param_array[16] = $row[0]['INVOICEPAYMENTTERMS'];
          $param_array[17] = $row[0]['CREDITLIMITAMOUNT'];
          $param_array[18] = $row[0]['CREDITLIMITDAYS'];
          $param_array[19] = $row[0]['CREDITHOLD'];
          $param_array[20] = '"' .$row[0]['MEMO1'] .'"';
          $param_array[21] = '"' .$row[0]['MEMO2'] .'"';
          $param_array[22] = $row[0]['ACTIVECUSTOMER'];
          $param_array[23] = $row[0]['STATUS'];
          $param_array[24] = $row[0]['RECORDMODE'];
		  $param_array[25] = '"' .$row[0]['ATTRIBUTE1'] .'"';
		  $param_array[26] = "0";
		  $param_array[27] = "0";
		  $param_array[28] = '"' .$row[0]['ATTRIBUTE2'] .'"';
		  $param_array[29] = '"' .$row[0]['ATTRIBUTE3'] .'"';
		  $param_array[30] = '"' .$row[0]['ATTRIBUTE4'] .'"';
		  $param_array[31] = '"' .$row[0]['ATTRIBUTE5'] .'"';
		  
          $sqlArray = implode(",", $param_array);
          $sql = $sql ."(" .$sqlArray ."),";
      }
      $sql = str_replace("#",'',$sql);
      $sql = rtrim($sql, ",");
	  
      $result = $this->executeQuery($sql,'imp_customermaster');
      $count = $start-1;
        }
        $result = $this->SFA_Comman->executequery('CALL int_imp_CustomerMaster()',null);
        //echo date("d-m-Y h:i:s") . " insertCustomerMaster Exit ;
    }
    
    private function insertCustomerInvoice() {
        //echo date("d-m-Y h:i:s") . " insertCustomerInvoice Entry <br>";
        $ociQuery = "SELECT count(INVOICENUMBER) as COUNT FROM IMP_CUSTOMERINVOICE";
        $result = $this->SFA_Comman->ociexecutequery($ociQuery);
        $count = $result[0][0]['COUNT'];
        $start = 0;
        while($count > 0) {
            $sql = "";
            $start = $count - 250;
            if($start < 0 )
                $start = 0;
            $ociQuery = "SELECT * FROM (SELECT invoice.* , rownum rnum FROM (SELECT INVOICENUMBER, SALESMANCODE, CUSTOMERCODE, TRANSACTIONDATE, DUEDATE, TOTALINVOICEAMOUNT, INVOICEBALANCE, ORACLEREFERENCENUMBER, STATUS, RECORDMODE FROM IMP_CUSTOMERINVOICE ORDER BY INVOICENUMBER ASC) invoice WHERE rownum <= $count order by rownum ASC) WHERE rnum >= $start order by rnum ASC";
            $result = $this->SFA_Comman->ociexecutequery($ociQuery);
            $sql = "INSERT INTO imp_customerinvoice VALUES ";
            foreach($result as $row) {
                $transactionDate = $row[0]['TRANSACTIONDATE'];
                $transactionDate = "STR_TO_DATE('$transactionDate','%d-%b-%y')";
                $dueDate = $row[0]['DUEDATE'];
                $dueDate = "STR_TO_DATE('$dueDate','%d-%b-%y')";
                $array = array($row[0]['INVOICENUMBER'], $row[0]['SALESMANCODE'], $row[0]['CUSTOMERCODE'], $transactionDate, $dueDate, $row[0]['TOTALINVOICEAMOUNT'], $row[0]['INVOICEBALANCE'], "'" .$row[0]['ORACLEREFERENCENUMBER'] . "'", $row[0]['STATUS'], $row[0]['RECORDMODE'], '0', '0', '1');
                $sqlArray = implode(",", $array);
                $sql = $sql ."(" .$sqlArray ."),";
            }
            $sql = rtrim($sql, ",");
            $result = $this->executeQuery($sql,'imp_customerinvoice');
            $count = $start-1;
        }
        $result = $this->SFA_Comman->executequery('CALL int_imp_CustomerInvoice()',null);
        //echo date("d-m-Y h:i:s") . " insertCustomerInvoice Exit <br>";
    }

    private function insertStartingLoadDetail(){
        //echo date("d-m-Y h:i:s") . " insertStartingLoadDetail Entry <br>";
    	$ociQuery = "SELECT count(LOADDATE) as COUNT FROM IMP_DAILYSALESMANLOAD WHERE TO_DATE(LOADDATE,'DD-MM-YYYY') = TO_DATE(SYSDATE,'DD-MM-YYYY')";
        $result = $this->SFA_Comman->ociexecutequery($ociQuery);
        $count = $result[0][0]['COUNT'];
        $start = 0;
        while($count > 0) {
            $sql = "";
            $start = $count - 250;
            if($start < 0 )
                $start = 0;
      $ociQuery = "SELECT * FROM (SELECT dailysalesmanload.* , rownum rnum FROM (SELECT LOADDATE, ROUTECODE, SALESMANCODE, ITEMID, LOADNO, REFERENCENO, EACHQTY, STATUS, RECORDMODE FROM IMP_DAILYSALESMANLOAD WHERE TO_DATE(LOADDATE,'DD-MM-YYYY') = TO_DATE(SYSDATE,'DD-MM-YYYY')) dailysalesmanload WHERE rownum <= $count order by rownum ASC) WHERE rnum >= $start order by rnum ASC";
            $result = $this->SFA_Comman->ociexecutequery($ociQuery);
            $sql = "INSERT INTO imp_dailysalesmanload VALUES ";
            foreach($result as $row) {
            	$loadDate = $row[0]['LOADDATE'];
                $loadDate = "STR_TO_DATE('$loadDate','%d-%b-%y')";
                $array = array($loadDate, $row[0]['ROUTECODE'], $row[0]['SALESMANCODE'], $row[0]['ITEMID'], $row[0]['LOADNO'], $row[0]['REFERENCENO'], $row[0]['EACHQTY'], $row[0]['STATUS'], $row[0]['RECORDMODE']);
                $sqlArray = implode(",", $array);
                $sql = $sql ."(" .$sqlArray ."),";
            }            
			$sql = rtrim($sql, ",");
			//echo $sql;
            $result = $this->executeQuery($sql,'imp_dailysalesmanload');
            $count = $start-1;
        }
        $result = $this->SFA_Comman->executequery('CALL int_imp_StartingloadDetail()',null);
        //echo date("d-m-Y h:i:s") . " insertStartingLoadDetail Exit <br>";
    }
	
	private function insertTodaysInvoiceDetail(){ 
		$sql="DELETE FROM exp_salesheader WHERE transactiondate = curdate() ";
		$result = $this->executeQuery($sql,'exp_salesheader');
		
    	$ociQuery = "SELECT COUNT(INVOICENUMBER) as COUNT FROM EXP_SALESHEADER WHERE TO_DATE(TRANSACTIONDATE,'DD-MM-YYYY') = TO_DATE(SYSDATE,'DD-MM-YYYY')";
        $result = $this->SFA_Comman->ociexecutequery($ociQuery);
        $count = $result[0][0]['COUNT'];
        $start = 0;
        while($count > 0) {
            $sql = "";
            $start = $count - 250;
            if($start < 0 )
                $start = 0;
      $ociQuery = "SELECT * FROM (SELECT EXP_SALESHEADER.* , rownum rnum FROM (SELECT INVOICENUMBER, ROUTEKEY, CUSTOMERCODE, ROUTECODE, SALESMANCODE, TOTALINVOICEAMOUNT, INVOICEDISCOUNT, INVOICEBALANCE FROM EXP_SALESHEADER WHERE TO_DATE(TRANSACTIONDATE,'DD-MM-YYYY') = TO_DATE(SYSDATE,'DD-MM-YYYY')) EXP_SALESHEADER WHERE rownum <= $count order by rownum ASC) WHERE rnum >= $start ORDER BY rnum ASC";
            $result = $this->SFA_Comman->ociexecutequery($ociQuery);
            $sql = "INSERT INTO exp_salesheader VALUES ";
            foreach($result as $row) {        
                $array = array($row[0]['INVOICENUMBER'], $row[0]['ROUTEKEY'], "CURDATE()", $row[0]['CUSTOMERCODE'], $row[0]['ROUTECODE'], $row[0]['SALESMANCODE'], $row[0]['TOTALINVOICEAMOUNT'], $row[0]['INVOICEDISCOUNT'], $row[0]['INVOICEBALANCE']);
                $sqlArray = implode(",", $array);
                $sql = $sql ."(" .$sqlArray ."),";				
            }            
			$sql = rtrim($sql, ",");
						
            $result = $this->executeQuery($sql,'exp_salesheader');
            $count = $start-1;
        }        
    }
    
    private function executeQuery($sql,$comments) {
        //echo "executeQuery : <br>";
        $result = "";
        if($sql != "") {
            $sql = str_replace("'", "\'", $sql);
            $param_array = array();
            $param_array[1] = $sql;
            $param_array[2] = $comments;
            $result = $this->SFA_Comman->executequery('CALL int_sp_execute_query()', $param_array);
        }
        return $result;
    }
}
?>