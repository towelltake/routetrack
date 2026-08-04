<?php

class Api_ImportController extends Api_Library_Controller_Action_Abstract
{

    //Initilize variable - starts here
    private $index = "";


    public function init()
    {
        $this->SFA_Comman = new SFA_Comman();
	parent::init();
    }
  
    public function indexAction()
    {

    }



public function importbankmasterAction(){
	 
	$postvals = json_decode(file_get_contents('php://input'), true);
		foreach($postvals as $val)
		{
			
		$param_array 	= array();
		$param_array[1] = $val['name'];
		$param_array[2] = $val['code'];
		$result = $this->SFA_Comman->executequery('CALL proc_import_bankmaster(?,?)',$param_array,'');
		
		}
		//echo Zend_Json::encode($result);
		echo "Success-Jibin";
		exit;

}

 
public function importcustomermasterAction() 
{
$postvals = json_decode(file_get_contents('php://input'), true);
 foreach ($postvals as $val)
{ 
$param_array = array(); 
$param_array[1] = $val['CustomerCode']; 
$param_array[2] = $val['Type']; 
$param_array[3] = $val['SalesmanCode']; 
$param_array[4] = $val['CustomerName']; 
$param_array[5] = $val['CustomerAddress1']; 
$param_array[6] = $val['CustomerAddress2']; 
$param_array[7] = $val['CustomerAddress3']; 
$param_array[8] = $val['Contact']; 
$param_array[9] = $val['CategoryID']; 
$param_array[10] = $val['CustomerPaymentTerms']; 
$param_array[11] = $val['CreditLimit']; 
$param_array[12] = $val['CreditLimitDays']; 
$param_array[13] = $val['ActiveStatus']; 
$param_array[14] = $val['StatusFlag']; 
$param_array[15] = $val['HeadOfficeCode']; 
$param_array[16] = $val['ARType']; 
$param_array[17] = $val['NoOfBills']; 
$param_array[18] = $val['AREnable']; 
$result = $this->SFA_Comman->executequery('CALL proc_import_CustomerMaster(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',$param_array, '');
}
echo "Success - CustomerMaster";
exit;
}

public function importdepotAction() 
{
$postvals = json_decode(file_get_contents('php://input'), true);
 foreach ($postvals as $val)
{ 
$param_array = array(); 
$param_array[1] = $val['WarehouseCode']; 
$param_array[2] = $val['WarehouseName']; 
$param_array[3] = $val['ActiveStatus']; 
$result = $this->SFA_Comman->executequery('CALL proc_import_Depot(?,?,?)',$param_array, '');
}
echo "Success - Depot";
exit;
}

public function importdepottransferAction() 
{
$postvals = json_decode(file_get_contents('php://input'), true);
 foreach ($postvals as $val)
{ 
$param_array = array(); 
$param_array[1] = $val['DocumentNumber']; 
$param_array[2] = $val['Transactiondate']; 
$param_array[3] = $val['FromRoute']; 
$param_array[4] = $val['FromSalesman']; 
$param_array[5] = $val['ToDepotCode']; 
$param_array[6] = $val['FocusItemCode']; 
$param_array[7] = $val['Quantity']; 
$result = $this->SFA_Comman->executequery('CALL proc_import_DepotTransfer(?,?,?,?,?,?,?)',$param_array, '');
}
echo "Success - DepotTransfer";
exit;
}

public function importitembrandAction() 
{
$postvals = json_decode(file_get_contents('php://input'), true);
 foreach ($postvals as $val)
{ 
$param_array = array(); 
$param_array[1] = $val['ItemBrandCode']; 
$param_array[2] = $val['ItemSubGroupCode']; 
$param_array[3] = $val['ItemBrandName']; 
$param_array[4] = $val['ActiveStatus']; 
$result = $this->SFA_Comman->executequery('CALL proc_import_ItemBrand(?,?,?,?)',$param_array, '');
}
echo "Success - ItemBrand";
exit;
}

public function importitemgroupAction() 
{
$postvals = json_decode(file_get_contents('php://input'), true);
 foreach ($postvals as $val)
{ 
$param_array = array(); 
$param_array[1] = $val['ItemGroupCode']; 
$param_array[2] = $val['ItemMajorCategoryCode']; 
$param_array[3] = $val['ItemGroupName']; 
$param_array[4] = $val['ActiveStatus']; 
$result = $this->SFA_Comman->executequery('CALL proc_import_ItemGroup(?,?,?,?)',$param_array, '');
}
echo "Success - ItemGroup";
exit;
}

public function importitemmajorcategoryAction() 
{
$postvals = json_decode(file_get_contents('php://input'), true);
 foreach ($postvals as $val)
{ 
$param_array = array(); 
$param_array[1] = $val['ItemMajorCategoryCode']; 
$param_array[2] = $val['ItemMajorCategoryName']; 
$param_array[3] = $val['ActiveStatus']; 
$result = $this->SFA_Comman->executequery('CALL proc_import_ItemMajorCategory(?,?,?)',$param_array, '');
}
echo "Success - ItemMajorCategory";
exit;
}

public function importitemmasterAction() 
{
$postvals = json_decode(file_get_contents('php://input'), true);
 foreach ($postvals as $val)
{ 
$param_array = array(); 
$param_array[1] = $val['ItemCode']; 
$param_array[2] = $val['ItemBrandCode']; 
$param_array[3] = $val['ItemShortDescription']; 
$param_array[4] = $val['ItemDescription']; 
$param_array[5] = $val['UnitsPerFirst']; 
$param_array[6] = $val['DefaultSalesPrice']; 
$param_array[7] = $val['ActiveStatus']; 
$param_array[8] = $val['BaseUOM']; 
$param_array[9] = $val['UOM1']; 
$param_array[10] = $val['CasePrice']; 
$param_array[11] = $val['DefaultReturnPrice']; 
$param_array[12] = $val['ReturnCasePrice']; 
$param_array[13] = $val['BarcodePiece']; 
$param_array[14] = $val['BarcodeCarton']; 
$result = $this->SFA_Comman->executequery('CALL proc_import_ItemMaster(?,?,?,?,?,?,?,?,?,?,?,?,?,?)',$param_array, '');
}
echo "Success - ItemMaster";
exit;
}

public function importitemsubgroupAction() 
{
$postvals = json_decode(file_get_contents('php://input'), true);
 foreach ($postvals as $val)
{ 
$param_array = array(); 
$param_array[1] = $val['ItemSubGroupCode']; 
$param_array[2] = $val['ItemGroupCode']; 
$param_array[3] = $val['ItemSubGroupName']; 
$param_array[4] = $val['ActiveStatus']; 
$result = $this->SFA_Comman->executequery('CALL proc_import_ItemSubGroup(?,?,?,?)',$param_array, '');
}
echo "Success - ItemSubGroup";
exit;
}

public function importopeningstockAction() 
{
$postvals = json_decode(file_get_contents('php://input'), true);
 foreach ($postvals as $val)
{ 
$param_array = array(); 
$param_array[1] = $val['Depot']; 
$param_array[2] = $val['ItemCode']; 
$param_array[3] = $val['TotalUnits']; 
$param_array[4] = $val['ERPReferenceNumber']; 
$result = $this->SFA_Comman->executequery('CALL proc_import_OpeningStock(?,?,?,?)',$param_array, '');
}
echo "Success - OpeningStock";
exit;
}

public function importpricingmasterAction() 
{
$postvals = json_decode(file_get_contents('php://input'), true);
 foreach ($postvals as $val)
{ 
$param_array = array(); 
$param_array[1] = $val['PriceKey']; 
$param_array[2] = $val['CustomerName']; 
$param_array[3] = $val['ItemCode']; 
$param_array[4] = $val['Price']; 
$param_array[5] = $val['StartDate']; 
$param_array[6] = $val['EndDate']; 
$result = $this->SFA_Comman->executequery('CALL proc_import_PricingMaster(?,?,?,?,?,?)',$param_array, '');
}
echo "Success - PricingMaster";
exit;
}

public function importroutemasterAction() 
{
$postvals = json_decode(file_get_contents('php://input'), true);
 foreach ($postvals as $val)
{ 
$param_array = array(); 
$param_array[1] = $val['RouteCode']; 
$param_array[2] = $val['RouteName']; 
$result = $this->SFA_Comman->executequery('CALL proc_import_RouteMaster(?,?)',$param_array, '');
}
echo "Success - RouteMaster";
exit;
}

public function importsalesmanAction() 
{
$postvals = json_decode(file_get_contents('php://input'), true);
 foreach ($postvals as $val)
{ 
$param_array = array(); 
$param_array[1] = $val['SalesmanName']; 
$param_array[2] = $val['SalesmanCode']; 
$param_array[3] = $val['WarehouseCode']; 
$param_array[4] = $val['ActiveStatus']; 
$result = $this->SFA_Comman->executequery('CALL proc_import_Salesman(?,?,?,?)',$param_array, '');
}
echo "Success - Salesman";
exit;
}

 

public function importcustomercategoryAction() 
{
$postvals = json_decode(file_get_contents('php://input'), true);
 foreach ($postvals as $val)
{ 
$param_array = array(); 
$param_array[1] = $val['CategoryID']; 
$param_array[2] = $val['CategoryName']; 
$param_array[3] = $val['ActiveStatus']; 
$result = $this->SFA_Comman->executequery('CALL proc_import_CustomerCategory(?,?,?)',$param_array, '');
}
echo "Success - CustomerCategory";
exit;
}

public function importcustomerinvoiceAction() 
{
	
	
	

	
	 $baseUrl= Zend_Controller_Front::getInstance()->getBaseUrl();
        $path   = str_replace('//','/',$_SERVER['DOCUMENT_ROOT'].$baseUrl.'/');
		$path1 	= str_replace('//','/',$baseUrl.'/');		
		
		
		$filename = $path.'log/importcustomerinvoice_'.date('Ymd').'.txt';
	file_put_contents($filename, file_get_contents('php://input'));
	

	
$postvals = json_decode(file_get_contents('php://input'), true);
$filename1 = $path.'log/importcustomerinvoicecount_'.date('Ymd').'.txt';
file_put_contents($filename1,count($postvals));

$this->SFA_Comman->executeimportquery('CALL proc_import_TruncateCustomerInvoice()','', '');






$count=0;

 foreach ($postvals as $val)
{ 
$count++;
$param_array = array(); 
$param_array[1] = $val['INVOICENUMBER']; 
$param_array[2] = $val['ROUTECODE']; 
$param_array[3] = $val['SALESMANCODE']; 
$param_array[4] = $val['TRANSACTIONDATE']; 
$param_array[5] = $val['CUSTOMERCODE']; 
$param_array[6] = $val['TOTALINVOICEAMOUNT']; 
$param_array[7] = $val['INVOICEBALANCE']; 
$param_array[8] = $val['PAYMENTTYPE']; 
$param_array[9] = $val['PDCINDICATOR']; 
$param_array[10] = $val['ERPREFERENCENUMBER']; 
$param_array[11] = $val['INVOICEDUEDATE']; 

$result = $this->SFA_Comman->executeimportquery('CALL proc_import_CustomerInvoice(?,?,?,?,?,?,?,?,?,?,?)',$param_array, '');
}
$this->SFA_Comman->executeimportquery('CALL proc_insert_CustomerInvoice()','', '');
echo "Success - CustomerInvoice--".$count;
exit;
}

  


 

}
     