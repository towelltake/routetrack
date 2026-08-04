<?php
/*
 * FileName     : DataSyncExport.php
 * Owner        : v.nair@mirnah.com
 * Created      : 09/09/2015
 * Description  : Getting data tables from Oracle and Updating DB 
 */
class SFA_DataSyncExport {

    private $loginName = "Invalid";
    private $process = FALSE;
    private $routeKey = 0;
    public function __construct() 
	{
        set_time_limit(0);
        $this->SFA_Comman = new SFA_Comman();
    }
    
    public function setUserName($loginName)
	{
        $this->loginName = $loginName;
    }
	
	public function exportpresentvanstock()
	{
        
		$this->exportPresentStock();
		$this->exportTransactionSummarySales();
		$this->exportTransactionSummaryCollection();
        
    }
   public function updateOracleDBWithExportData($dataArray) 
   {
        foreach ($dataArray as $routeKey)
		{
        	$this->routeKey = $routeKey;
        	$this->exportDataTables();
        }
    }
	public function onlineexportdata($dataArray) 
	{
        foreach ($dataArray as $routeKey) 
		{
        	$this->routeKey = $routeKey;
        	$this->exportonlinetables();
        }
    }
	
	public function onlinespecdata($dataArray) 
	{		
       foreach ($dataArray as $routeKey) 
		{
        	$this->routeKey = $routeKey;
			$this->exportspectables();
        }
		
		$this->updateMissingInvoices();
		$this->updateMissingItemDetail();
		
		$this->updateMissingOrders();	
		$this->updateMissingOrderDetail();	
		
		//$this->updateUnsendInvoices();
		//$this->updateUnsendOrders();
    }
	
	public function pushonlineorder($dataArray) 
	{		
       foreach ($dataArray as $routeKey) 
		{
        	$this->routeKey = $routeKey;			
			$this->exportOrderDetail();
			$this->exportOrderHeader(); 
        }
				
		$this->updateMissingOrders();	
		$this->updateMissingOrderDetail();			
    }
	
	public function pushonlineinvoice($dataArray) 
	{		
       foreach ($dataArray as $routeKey) 
		{
        	$this->routeKey = $routeKey;
			$this->exportCashSales();
			$this->exportSalesDetail();
			$this->exportSalesHeader(); 
        }
				
		$this->updateMissingInvoices();
		$this->updateMissingItemDetail();			
    }
	
	public function pushonlinearinventory($dataArray) 
	{		
       foreach ($dataArray as $routeKey) 
		{
        	$this->routeKey = $routeKey;			
			$this->exportLoadRequest();
			$this->exportLoadTransfers();			
			$this->exportArDetail();   
			$this->exportArHeader(); 
        }			
    }
	
	 private function exportspectables() 
	 {     
		$this->exportLoadRequest();
		$this->exportLoadTransfers();
		
        $this->exportArDetail();   
        $this->exportArHeader();
		$this->exportCashSales();
		
        $this->exportSalesDetail();
        $this->exportSalesHeader();
		
		$this->exportOrderDetail();
        $this->exportOrderHeader(); 
    }
	
    private function exportDataTables()
	{
        $this->exportLoadTransfers();
		
		$this->exportOrderDetail();
        $this->exportOrderHeader(); 
		
		$this->exportArDetail();
        $this->exportArHeader(); 
		$this->exportCashSales();
		
		$this->exportSalesDetail();
        $this->exportSalesHeader();
		
		$this->exportDamageReturn();
        
        $this->exportClosingStocks();
		
        $this->exportSalesmanTarget();		

        $sql = "UPDATE startendday set exportedflag = 1 where routekey = $this->routeKey";
        $param_array = array();
        $param_array[1] = $sql;
        $this->SFA_Comman->executequery('call int_sp_execute_query()', $param_array);
    }
	
	 private function exportonlinetables() 
	 {    		
          $this->exportLoadRequest();
          $this->exportLoadTransfers();
		  $this->exportOrderDetail();
          $this->exportOrderHeader();          
    }	
 	
    private function exportSalesHeader() 
	{             
        $param_array = array();
        $param_array[1] = $this->routeKey;
        $queryResult = $this->SFA_Comman->executequery('call int_exp_salesheader()',$param_array);
       
        foreach($queryResult[0] as $row) 
		{
			$ociSql = "INSERT  ";            
            $ociSql = $ociSql ." INTO EXP_SALESHEADER ";
            $ociSql = $ociSql ."(SEQUENCENUMBER,INVOICENUMBER, ROUTEKEY, TRANSACTIONDATE, CUSTOMERREFERENCENO, CUSTOMERCODE,ROUTECODE,SALESMANCODE, TOTALINVOICEAMOUNT, INVOICEDISCOUNT, INVOICEBALANCE, STATUS, ATTRIBUTE1)";
            $ociSql = $ociSql ." VALUES ";
            $ociSql = $ociSql ."(INVOICEHEADER_SEQ.NEXTVAL,'$row[invoiceno]', $row[routekey], TO_DATE('$row[transactiondate]','YYYY-MM-DD'),'$row[custrefno]', '$row[custcode]', '$row[routecode]', '$row[salesmancode]', $row[totalinvoiceamt], '$row[invoicediscount]', '$row[invoicebalance]', 1, '$row[attribute1]')";
			$result = $this->SFA_Comman->ociexecutequery($ociSql);
			
			$param_array1 = array();
			$param_array1[1] = $row['invoiceno'];
			$param_array1[2] = 'INH';
			$queryResult = $this->SFA_Comman->executequery('call int_exp_update_transmitindicator()',$param_array1);
        }      
    }
    
    private function exportSalesDetail() 
	{        
        $param_array = array();
        $param_array[1] = $this->routeKey;
        $queryResult = $this->SFA_Comman->executequery('call int_exp_salesdetail()',$param_array);
       
        foreach($queryResult[0] as $row) 
		{
			$ociSql = "INSERT  ";        	
            $ociSql = $ociSql ." INTO EXP_SALESDETAIL ";
            $ociSql = $ociSql ."(SEQUENCENUMBER, INVOICENUMBER, ROUTEKEY, LINENO, ITEMCODE, QUANTITY, REFERENCEINVOICENO, LINETYPE,EACHPRICE, ITEMDISCOUNT, EXPIRYDATE, STATUS, ATTRIBUTE1, ATTRIBUTE2, ATTRIBUTE3, ATTRIBUTE4)";
            $ociSql = $ociSql ." VALUES ";
			if($row['expirydate'] == "")
			{
				$ociSql = $ociSql ."(INVOICEDETAIL_SEQ.NEXTVAL, $row[invoiceno], $row[routekey], $row[lineno],'$row[itemcode]', $row[quantity],'$row[refinvoiceno]', '$row[linetype]', $row[eachprice], '$row[itemdiscount]',NULL, 1, '$row[unit]', '$row[attribute2]', $row[attribute3], $row[attribute4])";		
			}	
			else
			{
				$ociSql = $ociSql ."(INVOICEDETAIL_SEQ.NEXTVAL, $row[invoiceno], $row[routekey], $row[lineno],'$row[itemcode]', $row[quantity],'$row[refinvoiceno]', '$row[linetype]', $row[eachprice], '$row[itemdiscount]',TO_DATE('$row[expirydate]','YYYY-MM-DD'), 1, '$row[unit]', '$row[attribute2]', $row[attribute3], $row[attribute4])";
			}			
           
			$result = $this->SFA_Comman->ociexecutequery($ociSql);
        }        
    }

	private function exportCashSales() 
	{             
        $param_array = array();
        $param_array[1] = $this->routeKey;
        $queryResult = $this->SFA_Comman->executequery('call int_exp_cashinvoice()',$param_array);
      
        foreach($queryResult[0] as $row) 
		{
			$ociSql = "INSERT  ";            
            $ociSql = $ociSql ." INTO EXP_ARHEADER ";
            $ociSql = $ociSql ."(SEQUENCENUMBER, RECEIPTNUMBER, ROUTEKEY, RECEIPTDATE, CUSTOMERREFERENCENO, CUSTOMERCODE,ROUTECODE,SALESMANCODE, RECEIPTAMOUNT,MOP,CHEQUENO,CHEQUEDATE,BANKNAME,STATUS,ATTRIBUTE1)";
            $ociSql = $ociSql ." VALUES ";
            $ociSql = $ociSql ." (ARHEADER_SEQ.NEXTVAL,'$row[receiptno]', $row[routekey], TO_DATE('$row[receiptdate]','YYYY-MM-DD'),'$row[custrefno]','$row[custcode]', '$row[routecode]', '$row[salesmancode]', '$row[receiptamt]', '$row[MOP]', '$row[chequeno]', TO_DATE('$row[checkdate]','YYYY-MM-DD'), '$row[bankname]', 1, '$row[trantype]')";
			$result = $this->SFA_Comman->ociexecutequery($ociSql);
			
			$ociSql = "INSERT  ";
            $ociSql = $ociSql ." INTO EXP_ARDETAIL ";
            $ociSql = $ociSql ."(SEQUENCENUMBER, RECEIPTNUMBER,ROUTEKEY, INVOICENUMBER, ORACLEREFERENCENUMBER, AMOUNTPAID, STATUS, ATTRIBUTE1)";
            $ociSql = $ociSql ." VALUES ";
            $ociSql = $ociSql ."(ARDETAIL_SEQ.NEXTVAL, $row[receiptno], $row[routekey], '$row[receiptno]', '0', $row[receiptamt], 1, $row[custcode])";
			$result = $this->SFA_Comman->ociexecutequery($ociSql);
        }      
    }
	
    private function exportArHeader() 
	{             
        $param_array = array();
        $param_array[1] = $this->routeKey;
        $queryResult = $this->SFA_Comman->executequery('call int_exp_arheader()',$param_array);
      
        foreach($queryResult[0] as $row) 
		{
			$ociSql = "INSERT  ";            
            $ociSql = $ociSql ." INTO EXP_ARHEADER ";
            $ociSql = $ociSql ."(SEQUENCENUMBER, RECEIPTNUMBER, ROUTEKEY, RECEIPTDATE, CUSTOMERREFERENCENO, CUSTOMERCODE,ROUTECODE,SALESMANCODE, RECEIPTAMOUNT,MOP,CHEQUENO,CHEQUEDATE,BANKNAME,STATUS,ATTRIBUTE1)";
            $ociSql = $ociSql ." VALUES ";
            $ociSql = $ociSql ." (ARHEADER_SEQ.NEXTVAL,'$row[receiptno]', $row[routekey], TO_DATE('$row[receiptdate]','YYYY-MM-DD'),'$row[custrefno]','$row[custcode]', '$row[routecode]', '$row[salesmancode]', '$row[receiptamt]', '$row[MOP]', '$row[chequeno]', TO_DATE('$row[checkdate]','YYYY-MM-DD'), '$row[bankname]', 1, '$row[trantype]')";
			$result = $this->SFA_Comman->ociexecutequery($ociSql);
			
			$param_array1 = array();
			$param_array1[1] = $row['receiptno'];
			$param_array1[2] = 'AR';
			$queryResult = $this->SFA_Comman->executequery('call int_exp_update_transmitindicator()',$param_array1);			
        }      
    }
    
    private function exportArDetail() 
	{
        $param_array = array();
        $param_array[1] = $this->routeKey;
        $queryResult = $this->SFA_Comman->executequery('call int_exp_ardetail()',$param_array);
       
        foreach($queryResult[0] as $row)
		{
			$ociSql = "INSERT  ";
            $ociSql = $ociSql ." INTO EXP_ARDETAIL ";
            $ociSql = $ociSql ."(SEQUENCENUMBER, RECEIPTNUMBER,ROUTEKEY, INVOICENUMBER, ORACLEREFERENCENUMBER, AMOUNTPAID, STATUS, ATTRIBUTE1)";
            $ociSql = $ociSql ." VALUES ";
            $ociSql = $ociSql ."(ARDETAIL_SEQ.NEXTVAL, $row[receiptno], $row[routekey], '$row[invoiceno]', '$row[oraclerefno]', $row[amountpaid], 1, $row[attribute1])";
			$result = $this->SFA_Comman->ociexecutequery($ociSql);
        }       
    }	
    
     private function exportOrderHeader() 
	 {
        $param_array = array();
        $param_array[1] = $this->routeKey;
        $queryResult = $this->SFA_Comman->executequery('call int_exp_orderheader()',$param_array);
        
        foreach($queryResult[0] as $row) 
		{
			$ociSql = "INSERT  "; 
            $ociSql = $ociSql ." INTO EXP_ORDERHEADER ";
            $ociSql = $ociSql ."(SEQUENCENUMBER, ORDERNUMBER, ROUTEKEY, TRANSACTIONDATE, CUSTOMERREFERENCENO, CUSTOMERCODE, ROUTECODE, SALESMANCODE, TOTALORDERAMOUNT, INVOICEDISCOUNT, STATUS, ATTRIBUTE1, ATTRIBUTE2)";
            $ociSql = $ociSql ." VALUES ";
            $ociSql = $ociSql ."(ORDERHEADER_SEQ.NEXTVAL, '$row[orderno]', '$row[routekey]', TO_DATE('$row[transactiondate]','YYYY-MM-DD'), '$row[custrefno]', '$row[custcode]', '$row[routecode]', '$row[salesmancode]', $row[totalorderamt], $row[invoicediscount], 1, '$row[attribute1]', '$row[lpono]')";
			$result = $this->SFA_Comman->ociexecutequery($ociSql);			 			 
			
			$param_array1 = array();
			$param_array1[1] = $row['orderno'];
			$param_array1[2] = 'ORD';
			$queryResult = $this->SFA_Comman->executequery('call int_exp_update_transmitindicator()',$param_array1);
        }
       }
    
    private function exportOrderDetail()
	{ 
        $param_array = array();
        $param_array[1] = $this->routeKey;
        $queryResult = $this->SFA_Comman->executequery('call int_exp_orderdetail()',$param_array);
       
        foreach($queryResult[0] as $row) 
		{        	
			$ociSql = "INSERT  ";           
            $ociSql = $ociSql ." INTO EXP_ORDERDETAIL ";
			$ociSql = $ociSql ."(SEQUENCENUMBER, ORDERNUMBER, ROUTEKEY, LINENO, ITEMCODE, QUANTITY, LINETYPE, EACHPRICE, ITEMDISCOUNT, EXPIRYDATE, STATUS, ATTRIBUTE1, ATTRIBUTE2, ATTRIBUTE3, ATTRIBUTE4, ATTRIBUTE5)";
            $ociSql = $ociSql ." VALUES ";

			if($row['expirydate'] == "")
			{
				$ociSql = $ociSql ."(ORDERDETAIL_SEQ.NEXTVAL, $row[invoiceno], $row[routekey], $row[lineno],'$row[itemcode]', $row[quantity], '$row[linetype]', $row[eachprice], '$row[itemdiscount]',NULL, 1, '$row[unit]', '$row[attribute2]', $row[attribute3], '$row[attribute4]', '$row[attribute5]')";	
			}	
			else
			{
				$ociSql = $ociSql ."(ORDERDETAIL_SEQ.NEXTVAL, $row[invoiceno], $row[routekey], $row[lineno],'$row[itemcode]', $row[quantity], '$row[linetype]', $row[eachprice], '$row[itemdiscount]',TO_DATE('$row[expirydate]','YYYY-MM-DD'), 1, '$row[unit]', '$row[attribute2]', $row[attribute3], '$row[attribute4]', '$row[attribute5]')";
			}	
			
			$result = $this->SFA_Comman->ociexecutequery($ociSql);
        }      
    }
    
    private function exportClosingStocks() 
	{
        $param_array = array();
        $param_array[1] = $this->routeKey;
        $queryResult = $this->SFA_Comman->executequery('call int_exp_closingstocks()',$param_array);
        $ociSql = "INSERT ALL ";
        foreach($queryResult[0] as $row) 
		{           
            $ociSql = $ociSql ." INTO EXP_CLOSINGSTOCKS ";
            $ociSql = $ociSql ."(ROUTECODE, SALESMANCODE, ROUTEKEY, CLOSINGDATE, DOCUMENTNUMBER, ITEMCODE, QUANTITY,STATUS)";
            $ociSql = $ociSql ." VALUES ";
            $ociSql = $ociSql ."($row[routecode], '$row[salesmancode]','$row[routekey]',TO_DATE('$row[closingdate]','YYYY-MM-DD'), '$row[documentnumber]',$row[itemcode], $row[quantity], 1)";
        }
        $ociSql = $ociSql ." SELECT * FROM dual";
        if(sizeof($queryResult[0]) > 0)
            $result = $this->SFA_Comman->ociexecutequery($ociSql);
    }
    
    private function exportLoadRequest() 	
	{     
        $param_array = array();
        $param_array[1] = $this->routeKey;
        $queryResult = $this->SFA_Comman->executequery('call int_exp_loadrequest()',$param_array);
   
        foreach($queryResult[0] as $row) 
		{
			$ociSql = "INSERT  ";          
            $ociSql = $ociSql ." INTO EXP_LOADREQUEST ";
            $ociSql = $ociSql ."(SEQUENCENUMBER,LINENO,ROUTECODE, SALESMANCODE, TRANSACTIONDATE, REQUESTEDDATE, ROUTEKEY, DOCUMENTNUMBER, ITEMCODE, QUANTITY,STATUS,ATTRIBUTE1,ATTRIBUTE2,ATTRIBUTE3)";
            $ociSql = $ociSql ." VALUES ";
            $ociSql = $ociSql ."(LOADREQUEST_SEQ.NEXTVAL,$row[lineno],$row[routecode], '$row[salesmancode]', TO_DATE('$row[transferdate]','YYYY-MM-DD'), TO_DATE('$row[requestdate]','YYYY-MM-DD'), $row[routekey], $row[documentnumber], '$row[itemcode]',$row[quantity], 1,'$row[units]','$row[attribute2]','$row[attribute3]')";
			$result = $this->SFA_Comman->ociexecutequery($ociSql);
			
			$param_array1 = array();
			$param_array1[1] = $row['documentnumber'];
			$param_array1[2] = 'INV';
			$queryResult = $this->SFA_Comman->executequery('call int_exp_update_transmitindicator()',$param_array1);			
        }      
    }
	
    public function loadreqexport() 	
	{     
        $param_array = array();
        $param_array[1] = $this->routeKey;
        $queryResult = $this->SFA_Comman->executequery('call int_exp_loadrequest_scheduler()',null);
   
        foreach($queryResult[0] as $row) 
		{
			$ociSql = "INSERT  ";          
            $ociSql = $ociSql ." INTO EXP_LOADREQUEST ";
            $ociSql = $ociSql ."(SEQUENCENUMBER,LINENO,ROUTECODE, SALESMANCODE, TRANSACTIONDATE, REQUESTEDDATE, ROUTEKEY, DOCUMENTNUMBER, ITEMCODE, QUANTITY,STATUS,ATTRIBUTE1,ATTRIBUTE2,ATTRIBUTE3)";
            $ociSql = $ociSql ." VALUES ";
            $ociSql = $ociSql ."(LOADREQUEST_SEQ.NEXTVAL,$row[lineno],$row[routecode], '$row[salesmancode]', TO_DATE('$row[transferdate]','YYYY-MM-DD'), TO_DATE('$row[requestdate]','YYYY-MM-DD'), $row[routekey], $row[documentnumber], '$row[itemcode]',$row[quantity], 1,'$row[units]','$row[attribute2]','$row[attribute3]')";
			$result = $this->SFA_Comman->ociexecutequery($ociSql);
			
			$param_array1 = array();
			$param_array1[1] = $row['documentnumber'];
			$param_array1[2] = 'INV';
			$queryResult = $this->SFA_Comman->executequery('call int_exp_update_transmitindicator()',$param_array1);			
        }      
    }
   
    private function exportLoadTransfers()
	{     
        $param_array = array();
        $param_array[1] = $this->routeKey;
        $queryResult = $this->SFA_Comman->executequery('call int_exp_loadtransfers()',$param_array);
       
        foreach($queryResult[0] as $row) 
		{
			$ociSql = "INSERT  ";          
            $ociSql = $ociSql ." INTO EXP_LOADTRANSFERS ";
            $ociSql = $ociSql ."(SEQUENCENUMBER,LINENO,ROUTECODE, SALESMANCODE, TRANSFERDATE, ROUTEKEY, DOCUMENTNUMBER, ITEMCODE, TRANSACTIONTYPE, QUANTITY, EXPIRYDATE, STATUS, ATTRIBUTE1, ATTRIBUTE2, ATTRIBUTE3)";
            $ociSql = $ociSql ." VALUES ";
            $ociSql = $ociSql ."(LOADTRANSFER_SEQ.NEXTVAL,$row[lineno],$row[routecode], '$row[salesmancode]', TO_DATE('$row[transferdate]','YYYY-MM-DD'), $row[routekey], $row[documentnumber], '$row[itemcode]', '$row[transactiontype]', '$row[quantity]', TO_DATE('$row[expirydate]','YYYY-MM-DD'), 1, '$row[units]', '$row[attribute2]', '$row[attribute3]')";
						
			$result = $this->SFA_Comman->ociexecutequery($ociSql);
			
			$param_array1 = array();
			$param_array1[1] = $row['documentnumber'];
			$param_array1[2] = 'INV';
			$queryResult = $this->SFA_Comman->executequery('call int_exp_update_transmitindicator()',$param_array1);
        }      
    }
	
	private function exportPresentStock() 
	{ 
		$ociSql = "DELETE FROM RECO_PRESENTSTOCK WHERE TO_DATE(POSTINGDATE,'DD-MM-YYYY') = TO_DATE(SYSDATE,'DD-MM-YYYY')";
        $this->SFA_Comman->ociexecutequery($ociSql);
		
        $param_array = array();
        $queryResult = $this->SFA_Comman->executequery('call int_reco_presentstocks()',null);
       
        foreach($queryResult[0] as $row) 
		{
			 $ociSql = "INSERT  ";        	
            $ociSql = $ociSql ." INTO RECO_PRESENTSTOCK ";
            $ociSql = $ociSql ."(POSTINGDATE, ROUTE, SALESMAN, ITEMCODE, ITEMID, OPENING, LOADED, TRANSFEROUT, TRANSFERIN, SALES, FREE, GRETURN, GRETURNFREE, VANSTOCK)";
            $ociSql = $ociSql ." VALUES ";
			$ociSql = $ociSql ."(TO_DATE('$row[SystemDate]','YYYY-MM-DD'), $row[Route], '$row[Salesman]', '$row[ItemCode]', $row[ItemID], $row[Opening], $row[Loaded], $row[TransferOut], $row[TransferIn], $row[Sales], $row[Free], $row[GReturn], $row[GReturnFree], $row[VanStock])";	
			
			$result = $this->SFA_Comman->ociexecutequery($ociSql);
        }        
    }
	
	private function exportTransactionSummarySales() 
	{ 
		$ociSql = "DELETE FROM RECO_SALESSUMMARY WHERE TO_DATE(POSTINGDATE,'DD-MM-YYYY') = TO_DATE(SYSDATE,'DD-MM-YYYY')";
        $this->SFA_Comman->ociexecutequery($ociSql);
		
        $param_array = array();
        $queryResult = $this->SFA_Comman->executequery('call int_reco_salessummary()',null);
       
        foreach($queryResult[0] as $row) 
		{
			 $ociSql = "INSERT ";        	
            $ociSql = $ociSql ." INTO RECO_SALESSUMMARY ";
            $ociSql = $ociSql ."(POSTINGDATE, TRIPID, ROUTECODE, SALESMANCODE, CUSTOMERID, CUSTOMERCODE, INVOICENUMBER, TOTALINVOICEAMOUNT, INVOICEDISCOUNT, ITEMID, ITEMCODE, UPC, SALEQTY, RETURNQTY, DAMAGEQTY, FREEQTY, RETURNFREEQTY, CASEPRICE, PCSPRICE, LINETOTAL)";
            $ociSql = $ociSql ." VALUES ";
			$ociSql = $ociSql ."(TO_DATE('$row[PostingDate]','YYYY-MM-DD'), $row[TripID], $row[RouteCode], '$row[SalesmanCode]', $row[CustomerID], '$row[CustomerCode]', $row[InvoiceNumber], $row[TotalInvoiceAmount], $row[InvoiceDiscount], $row[ItemID], '$row[ItemCode]', $row[Upc], $row[SaleQty], $row[ReturnQty], $row[DamageQty], $row[FreeQty], $row[ReturnFreeQty], $row[CasePrice], $row[PcsPrice], $row[LineTotal])";	

			$result = $this->SFA_Comman->ociexecutequery($ociSql);
        }        
    }

	private function exportTransactionSummaryCollection() 
	{ 
		$ociSql = "DELETE FROM RECO_COLLECTIONSUMMARY WHERE TO_DATE(POSTINGDATE,'DD-MM-YYYY') = TO_DATE(SYSDATE,'DD-MM-YYYY')";
        $this->SFA_Comman->ociexecutequery($ociSql);
		
        $param_array = array();
        $queryResult = $this->SFA_Comman->executequery('call int_reco_collectionsummary()',null);
       
        foreach($queryResult[0] as $row) 
		{
			 $ociSql = "INSERT ";        	
            $ociSql = $ociSql ." INTO RECO_COLLECTIONSUMMARY ";
            $ociSql = $ociSql ."(POSTINGDATE, TRIPID, ROUTECODE, SALESMANCODE, CUSTOMERID, CUSTOMERCODE, RECEIPTNUMBER, RECEIPTAMOUNT, INVOICENUMBER, AMOUNTPAID, TRANTYPE, CHEQUENO)";
            $ociSql = $ociSql ." VALUES ";
			$ociSql = $ociSql ."(TO_DATE('$row[PostingDate]','YYYY-MM-DD'), $row[TripID], $row[RouteCode], '$row[SalesmanCode]', $row[CustomerID], '$row[CustomerCode]', $row[ReceiptNumber], $row[ReceiptAmount], $row[InvoiceNumber], $row[AmountPaid], '$row[TranType]', $row[checknumber])";	

			$result = $this->SFA_Comman->ociexecutequery($ociSql);
        }        
    }
	
	private function exportDamageReturn()
	{     
        $param_array = array();
        $param_array[1] = $this->routeKey;
        $queryResult = $this->SFA_Comman->executequery('call int_exp_damages()',$param_array);
       
        foreach($queryResult[0] as $row) 
		{
			$ociSql = "INSERT  ";          
            $ociSql = $ociSql ." INTO EXP_LOADTRANSFERS ";
            $ociSql = $ociSql ."(SEQUENCENUMBER,LINENO,ROUTECODE, SALESMANCODE, TRANSFERDATE, ROUTEKEY, DOCUMENTNUMBER, ITEMCODE, TRANSACTIONTYPE, QUANTITY, EXPIRYDATE, STATUS,ATTRIBUTE1,ATTRIBUTE2)";
            $ociSql = $ociSql ." VALUES ";
            $ociSql = $ociSql ."(LOADTRANSFER_SEQ.NEXTVAL,$row[lineno],$row[routecode], '$row[salesmancode]', TO_DATE('$row[enddate]','YYYY-MM-DD'), $row[routekey], $row[documentnumber], '$row[itemcode]', '$row[transactiontype]', '$row[quantity]', TO_DATE('$row[expirydate]','YYYY-MM-DD'), 1,'$row[units]','$row[attribute2]')";
			$result = $this->SFA_Comman->ociexecutequery($ociSql);
			
			$param_array1 = array();
			$param_array1[1] = $row['documentnumber'];
			$param_array1[2] = 'INV';
			$queryResult = $this->SFA_Comman->executequery('call int_exp_update_transmitindicator()',$param_array1);
        }      
    }	

    private function exportSalesmanTarget() 
	{       
        $param_array = array();
        $param_array[1] = $this->routeKey;
        $queryResult = $this->SFA_Comman->executequery('call int_exp_salemantarget()',$param_array);
        $ociSql = "INSERT ALL ";
        foreach($queryResult[0] as $row) 
		{           
            $ociSql = $ociSql ." INTO EXP_SALESMANTARGET ";
            $ociSql = $ociSql ."(SALESMANCODE, ROUTECODE, FROMDATE, TODATE, ITEMGROUP, TARGETQTY, TARGETAMOUNT, STATUS)";
            $ociSql = $ociSql ." VALUES ";
            $ociSql = $ociSql ."('$row[salesmancode]',$row[routecode],TO_DATE('$row[FromDate]','YYYY-MM-DD'), TO_DATE('$row[ToDate]','YYYY-MM-DD'),'$row[itemgrp]','$row[targetqty]', $row[targetamt], 1)";
        }
        $ociSql = $ociSql ." SELECT * FROM dual";
        if(sizeof($queryResult[0]) > 0)
            $result = $this->SFA_Comman->ociexecutequery($ociSql);
    }
	
	private function updateMissingInvoices() {
		$ociQuery = "SELECT HE.INVOICENUMBER FROM EXP_SALESHEADER HE LEFT OUTER JOIN EXP_SALESDETAIL DT ON DT.INVOICENUMBER = HE.INVOICENUMBER WHERE TO_DATE(HE.CREATIONDATE, 'DD-MM-YYYY') = TO_DATE(SYSDATE,'DD-MM-YYYY') GROUP BY HE.INVOICENUMBER, HE.CREATIONDATE HAVING COALESCE(COUNT(DT.ITEMCODE),0) = 0";
		$result = $this->SFA_Comman->ociexecutequery($ociQuery);

		if(count($result)>0)
		{
			$sql = "INSERT INTO imp_missinginvoices VALUES ";
			foreach($result as $row)
			{
				$param_array = array();
				$param_array[1] = $row[0]['INVOICENUMBER'];
				$sqlArray = implode(",", $param_array);
				$sql = $sql ."(" .$sqlArray ."),";
			}
			$sql = rtrim($sql, ",");
			
			$result = $this->executeQuery($sql,'imp_missinginvoices');			
			$result = $this->SFA_Comman->executequery('CALL int_imp_MissingInvoices()',null);
		
			$ociQuery = "DELETE FROM EXP_SALESHEADER WHERE INVOICENUMBER IN (SELECT HE.INVOICENUMBER FROM EXP_SALESHEADER HE LEFT OUTER JOIN EXP_SALESDETAIL DT ON DT.INVOICENUMBER = HE.INVOICENUMBER WHERE TO_DATE(HE.CREATIONDATE, 'DD-MM-YYYY') = TO_DATE(SYSDATE,'DD-MM-YYYY') GROUP BY HE.INVOICENUMBER, HE.CREATIONDATE HAVING COALESCE(COUNT(DT.ITEMCODE),0) = 0)";
			$result = $this->SFA_Comman->ociexecutequery($ociQuery);
		}
    }
	
	private function updateMissingItemDetail() {
		$ociQuery = "SELECT IH.invoicenumber FROM EXP_SALESHEADER IH LEFT OUTER JOIN EXP_Salesdetail ID ON ID.invoicenumber = IH.invoicenumber WHERE TO_DATE(IH.CREATIONDATE, 'DD-MM-YYYY') = TO_DATE(SYSDATE,'DD-MM-YYYY') GROUP BY ih.transactiondate, IH.invoicenumber, ABS(IH.TOTALINVOICEAMOUNT) HAVING ABS(IH.TOTALINVOICEAMOUNT) <> SUM(ID.QUANTITY * ID.EACHPRICE)";
		$result = $this->SFA_Comman->ociexecutequery($ociQuery);

		if(count($result)>0)
		{
			$sql = "INSERT INTO imp_missinginvoices VALUES ";
			foreach($result as $row)
			{
				$param_array = array();
				$param_array[1] = $row[0]['INVOICENUMBER'];
				$sqlArray = implode(",", $param_array);
				$sql = $sql ."(" .$sqlArray ."),";
			}
				$sql = rtrim($sql, ",");
				
				$result = $this->executeQuery($sql,'imp_missinginvoices');
				
				$result = $this->SFA_Comman->executequery('CALL int_imp_MissingInvoices()',null);
		}
    }
	
	private function updateUnsendInvoices() {
		$ociQuery = "SELECT INVOICENUMBER FROM EXP_SALESHEADER WHERE TO_DATE(CREATIONDATE, 'DD-MM-YYYY') = TO_DATE(SYSDATE,'DD-MM-YYYY')";
		$result = $this->SFA_Comman->ociexecutequery($ociQuery);

		if(count($result)>0)
		{
			$sql = "INSERT INTO imp_missinginvoices VALUES ";
			foreach($result as $row)
			{
				$param_array = array();
				$param_array[1] = $row[0]['INVOICENUMBER'];
				$sqlArray = implode(",", $param_array);
				$sql = $sql ."(" .$sqlArray ."),";
			}
			$sql = rtrim($sql, ",");
			
			$result = $this->executeQuery($sql,'imp_missinginvoices');				
			$result = $this->SFA_Comman->executequery('CALL int_imp_UnsendInvoices()',null);
		}
    }
	
	private function updateMissingOrders() {
		$ociQuery = "SELECT HE.ORDERNUMBER FROM EXP_ORDERHEADER HE LEFT OUTER JOIN EXP_ORDERDETAIL DT ON DT.ORDERNUMBER = HE.ORDERNUMBER WHERE TO_DATE(HE.CREATIONDATE, 'DD-MM-YYYY') = TO_DATE(SYSDATE,'DD-MM-YYYY') GROUP BY HE.ORDERNUMBER, HE.CREATIONDATE HAVING COALESCE(COUNT(DT.ITEMCODE),0) = 0";
		$result = $this->SFA_Comman->ociexecutequery($ociQuery);

		if(count($result)>0)
		{
			$sql = "INSERT INTO imp_missingorders VALUES ";
			foreach($result as $row)
			{
				$param_array = array();
				$param_array[1] = $row[0]['ORDERNUMBER'];
				$sqlArray = implode(",", $param_array);
				$sql = $sql ."(" .$sqlArray ."),";
			}
			$sql = rtrim($sql, ",");
			
			$result = $this->executeQuery($sql,'imp_missingorders');				
			$result = $this->SFA_Comman->executequery('CALL int_imp_missingorders()',null);
			
			$ociQuery = "DELETE FROM EXP_ORDERHEADER WHERE ORDERNUMBER IN (SELECT HE.ORDERNUMBER FROM EXP_ORDERHEADER HE LEFT OUTER JOIN EXP_ORDERDETAIL DT ON DT.ORDERNUMBER = HE.ORDERNUMBER WHERE TO_DATE(HE.CREATIONDATE, 'DD-MM-YYYY') = TO_DATE(SYSDATE,'DD-MM-YYYY') GROUP BY HE.ORDERNUMBER, HE.CREATIONDATE HAVING COALESCE(COUNT(DT.ITEMCODE),0) = 0)";
			$result = $this->SFA_Comman->ociexecutequery($ociQuery);
		}
    }
	
	private function updateMissingOrderDetail() {
		$ociQuery = "SELECT IH.ORDERNUMBER FROM EXP_ORDERHEADER IH LEFT OUTER JOIN EXP_ORDERDETAIL ID ON ID.ORDERNUMBER = IH.ORDERNUMBER WHERE TO_DATE(IH.CREATIONDATE, 'DD-MM-YYYY') = TO_DATE(SYSDATE,'DD-MM-YYYY') GROUP BY ih.TRANSACTIONDATE, IH.ORDERNUMBER, ABS(IH.TOTALORDERAMOUNT) HAVING ABS(IH.TOTALORDERAMOUNT) <> SUM(ID.QUANTITY * ID.EACHPRICE)";
		$result = $this->SFA_Comman->ociexecutequery($ociQuery);

		if(count($result)>0)
		{
			$sql = "INSERT INTO imp_missingorders VALUES ";
			foreach($result as $row)
			{
				$param_array = array();
				$param_array[1] = $row[0]['ORDERNUMBER'];
				$sqlArray = implode(",", $param_array);
				$sql = $sql ."(" .$sqlArray ."),";
			}
			$sql = rtrim($sql, ",");
			
			$result = $this->executeQuery($sql,'imp_missingorders');				
			$result = $this->SFA_Comman->executequery('CALL int_imp_missingorders()',null);
		}
    }
	
	private function updateUnsendOrders() {
		$ociQuery = "SELECT ORDERNUMBER FROM EXP_ORDERHEADER WHERE TO_DATE(CREATIONDATE, 'DD-MM-YYYY') = TO_DATE(SYSDATE,'DD-MM-YYYY')";
		$result = $this->SFA_Comman->ociexecutequery($ociQuery);

		if(count($result)>0)
		{
			$sql = "INSERT INTO imp_missingorders VALUES ";
			foreach($result as $row)
			{
				$param_array = array();
				$param_array[1] = $row[0]['ORDERNUMBER'];
				$sqlArray = implode(",", $param_array);
				$sql = $sql ."(" .$sqlArray ."),";
			}
			$sql = rtrim($sql, ",");
			
			$result = $this->executeQuery($sql,'imp_missingorders');				
			$result = $this->SFA_Comman->executequery('CALL int_imp_UnsendOrders()',null);
		}
    }
	
	private function executeQuery($sql,$comments) 
	{
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