<?php
/*
 * FileName     : syncData.php
 * Owner        : v.nair@mirnah.com
 * Created      : 15/01/2015
 * Description  : Getting data tables from SAP and Updating DB 
 */
class SFA_syncData {

    private $tourIdArr = array();
    private $TourId = "";
    private $SalesManCode = "";
    private $routeExistStatus = "";
    private $loginName = "Invalid";
   // private $uidSAP = "kawish";
   // private $pwdSAP = "kawish123";
   	private $uidSAP = "DRIVERRFC";
   // private $pwdSAP = "welcome1234";
	private $pwdSAP = "ABCD1234@";
   
    private $downloadURL = 'http://172.16.4.37:8000/sap/opu/odata/MIRNAH/DOWNLOAD_SRV_Q/';   
	private $articleURL = 'http://172.16.4.37:8000/sap/opu/odata/sap/ZDSDG_ARTICLE_I_SRV_Q/';
	private $customerURL = 'http://172.16.4.37:8000/sap/opu/odata/sap/ZDSDG_GET_CUSTOMER_SRV_Q/';    
	private $tourURL = 'http://172.16.4.37:8000/sap/opu/odata/sap/ZDSDG_TOUR_I_SRV_Q/';
	private $tidURL = 'http://172.16.4.37:8000/sap/opu/odata/sap/ZDSD_TOUR_HD_I_SRV_Q/TourHeader';	
	private $openInvoiceURL = 'http://172.16.4.37:8000/sap/opu/odata/sap/ZDSDG_OPEN_INV_I_SRV_Q/';
    private $bankMasterURL = 'http://172.16.4.37:8000/sap/opu/odata/sap/ZDSDG_BANK_I_SRV_Q/BankHeaders';	
	private $promoheaderURL = 'http://172.16.4.37:8000/sap/opu/odata/sap/ZDSDG_PROMOTION_I_SRV_Q/';
	private $promoshipURL = 'http://172.16.4.37:8000/sap/opu/odata/sap/ZDSDG_PROMOTION_I_SRV_Q/';
	private $creditURL = 'http://172.16.4.37:8000/sap/opu/odata/sap/ZDSDG_CREDIT_I_SRV_Q/';
	private $QulityURL = 'http://172.16.4.37:8000/sap/opu/odata/sap/zdownload_SRV_Q/';
	
								
	

    public function __construct() {
        set_time_limit(0);
        $this->SFA_Comman = new SFA_Comman();
    }

    public function getTourIdValues() {
        return $this->tourIdArr;
    }
    public function setUserName($loginName) {
        $this->loginName = $loginName;
    }

    public function getSAPData($arrayTourID) {
        // echo "getSAPData<br>";
        date_default_timezone_set('Asia/Dubai');
        $date = date('m/d/Y h:i:s a', time());
        // echo "START : " .$date ."<br>"; 
        // for each tour id get all tables from SAP
        //foreach($this->tourIdArr as $tourId) {
        foreach($arrayTourID as $tourId) {
            $this->TourId = $tourId;
             $sql = "select importstatus,driver_id,division from tbl_touridstatus WHERE tourid = '$tourId'";
		  
			$result = $this->executeSQLQuery($sql);
			
             $importstatus = $result[0][0]['importstatus'];
			 $driverid = $result[0][0]['driver_id'];
			 $division = $result[0][0]['division'];
			
           /* if($importstatus == 1) {
                continue;
            }*/
		
			//
			$result = $this->SFA_Comman->executequery('CALL sp_post_clear_staging()', "");	
			
			$this->getSAPDataForCustomer($driverid);
			
            $this->getSAPDataAndUpdateDB($tourId, 'routeflag', 'ES_ROUTE_FLG');
            if($this->routeExistStatus != "") {
                $this->updateExitStatusInTourTable($tourId);
                continue;
            }
			
            $this->getSAPDataAndUpdateDB($tourId, 'visitplan', 'ES_VISIT_HDR');
            if($this->routeExistStatus != "") {
                $this->updateExitStatusInTourTable($tourId);
                continue;
            }
			
            /*$this->getSAPDataAndUpdateDB($tourId, 'custvisitid', 'ES_VISIT_HDR');
            if($this->routeExistStatus != "") {
                $this->updateExitStatusInTourTable($tourId);
                continue;
            }*/
			
			$this->getSAPDataAndUpdateDB($tourId, 'custmastercredit', 'CreditHeaders');
            if($this->routeExistStatus != "") {
                $this->updateExitStatusInTourTable($tourId);
                continue;
            }
			
			$this->getSAPDataAndUpdateDB($tourId, 'startingloaddetail', 'ES_DELIVERY_HDR');
            if($this->routeExistStatus != "") {
                $this->updateExitStatusInTourTable($tourId);
                continue;
            }	
			
				
			$this->getSAPDataForOpenInvoices($driverid);	
			
            $this->getSAPDataForPricing($tourId);
            if($this->routeExistStatus != "") {
                $this->updateExitStatusInTourTable($tourId);
                continue;
            }
			if($driverid != 'PF25758' )
			{
			$this->getDataForPromotionshipto($tourId);
				if($this->routeExistStatus != "") {
                $this->updateExitStatusInTourTable($tourId);
                continue;
            }
			}	
			$this->getSAPDataForPromotion($tourId);
            if($this->routeExistStatus != "") {
                $this->updateExitStatusInTourTable($tourId);
                continue;
            }	
			$result = $this->SFA_Comman->executequery('CALL sp_post_customers()', "");			
			$result = $this->SFA_Comman->executequery('CALL sp_post_startingload()', "");
			
			
			$this->getSAPDataForCustomerflag($driverid);
			
            /*$this->getSAPDataAndUpdateDB($driverid, 'customerflag', 'CustomerHeaders');
            if($this->routeExistStatus != "") {
                $this->updateExitStatusInTourTable($tourId);
                continue;
            }*/
            //$this->getSAPDataAndUpdateDB($tourId, 'customerpricingdetail', 'ES_CUST_ART_PRICE');

            /* $this->getSAPDataAndUpdateDB($tourId, 'custinvoice', 'ES_OPEN_INV');
            if($this->routeExistStatus != "") {
                $this->updateExitStatusInTourTable($tourId);
                continue;
            }
           $this->getSAPDataAndUpdateDB($tourId, 'custinvoicehht', 'ES_OPENITEM_REF');
            if($this->routeExistStatus != "") {
                $this->updateExitStatusInTourTable($tourId);
                continue;
            }*/
            //$result = $this->SFA_Comman->executequery('CALL sp_int_import_copyto_customerinvoice()', "");
            // echo "printing insert result <br>";print_r($result[0][0]);// echo "<br>";

            

           /* $this->getSAPDataAndUpdateDB($tourId, 'promoplanheader', 'ES_PROMO_CUST');
            if($this->routeExistStatus != "") 
			{
                $this->updateExitStatusInTourTable($tourId);
                continue;
            }
            $this->getSAPDataAndUpdateDB($tourId, 'promoplanheader', 'ES_PROMO_CUST_MAT');
            if($this->routeExistStatus != "") {
                $this->updateExitStatusInTourTable($tourId);
                continue;
            }*/

            //$this->getSAPDataAndUpdateDB($tourId, 'startingloaddetail', 'ES_LOAD_HDR');
            
            // update TourID Status
            $sql = "UPDATE tbl_touridstatus SET importstatus = '1' WHERE tourid = '$tourId'";
            $this->executeSQLQuery($sql);
        }
        $date = date('m/d/Y h:i:s a', time());
        
        // echo "END : " .$date ."<br>";
    }

    //*********************************************Connect to SAP and Update DB*************************************************
    /*
     * Function Name    : getSAPDataAndUpdateDB
     * Params           :
     * Params           : $tourId - the tour which need to be synced
     * Params           : $tableName - the table which need to be synced
     * Params           : $entitySet - the entity name of the table to be synced
     * Descripton       : Connect to SAP, get each table and update the Database
     */
    private function getSAPDataAndUpdateDB($tourId, $tableName, $entitySet) {
        // echo "getSAPDataAndUpdateDB<br>";
        // create the SAP address URL that need to be downloaded.
        $filter = $this->createFilterForSAPTable($tourId, $tableName);
        //$tableAddressURL = $this->downloadURL .$entitySet .$filter;
        $tableAddressURL = $this->createURLForSAP($entitySet,$filter);

         //echo "Table Addr URL : " .$tableAddressURL ." & Table Name : " .$tableName ."<br>";
		        // get the xml object from SAP for each URL
        $tableObject = $this->getSAPDBData($tableAddressURL, $this->uidSAP, $this->pwdSAP);
        $tabledata = $tableObject->d->results;

        // echo "Total Data Count : " .sizeof($tabledata) ."<br>";

        // for each entry get the table columns and values
        foreach ($tabledata as $data) {
            // update the DB with the downloaded table data values.
            $this->updateDBData($data, $tableName);
            // functions need to be called after calling main table update
           if($tableName == 'salesorderheader') {
                $orderitems = $data->DEL_HDR2ITM->results;
                foreach ($orderitems as $data)  {
                    $this->updateDBData($data, 'salesorderdetail');
                }
            } else if($tableName == 'customermessages') {
                $this->updateMessageKey($data);
            } else if($tableName == 'promoplanheader' && $entitySet == 'ES_PROMO_CUST_MAT') {
                $this->updateDBData($data, 'productgroupheader'); 
            }
        }
        //$this->sendMsg($tableName .' Updated<br>');
    }

    public function getTourIDforSelectedDate($date) {
        // echo "getTourIDforSelectedDate <br>";
        //$date = '2015-03-16';
        // for clearing the tables in DB, uncomment the below function.
      // $this->clearMasterTablesFromDB();

        /*$entitySet = 'ES_TOUR_HD';
        $filter = '?$filter=ExecutionDate%20eq%20datetime%27' .$date .'T00:00:00%27';
        $expand = '&$expand=TOUR_HDR2SA&$format=json';*/
		$filter = '?$filter=IDate%20eq%20datetime%27' .$date .'T00:00:00%27';
        $expand = '&$expand=TourSalesArea&$format=json';
        $filter = $filter .$expand;
        $tableAddressURL = $this->createURLForSAP('TourHeaders',$filter);

      // echo//  $tableAddressURL = $this->downloadURL .$entitySet .$filter .$expand;
	   // $tableAddressURL;
         
        $tableObject = $this->getSAPDBData($tableAddressURL, $this->uidSAP, $this->pwdSAP);
        $tabledata = $tableObject->d->results;
		//print_r($tabledata);
        $count = 0;
        /*foreach ($tabledata as $data) {
            $this->tourIdArr[$count++] = $data->TourId;
            $this->updateDBData($data, 'tbl_touridstatus');
			$this->updateDBData($data, 'routemaster');
             if($this->routeExistStatus != "") {
                $this->updateExitStatusInTourTable($data->TourId);
                continue;
             }
        }*/
		
        
       // $this->getSAPDataForTourList($date);
       // $this->getSAPDataForArticleItem();
    }

	//--------------------------------
	//------------------------- tour id
	 public function getTourIDselected($tid) {
		 
		 /*$baseUrl= Zend_Controller_Front::getInstance()->getBaseUrl();
				$path   = str_replace('//','/',$_SERVER['DOCUMENT_ROOT'].'/upload/');
				$path1 	= str_replace('//','/',$baseUrl.'/');		
		
				$filename = $path.'log_test/touridinner.txt';
				if (!file_exists($filename)) {
				fopen($filename,'w');
					}
				chmod($filename,0777);
		
				$current = file_get_contents($filename);
				$current .= "\n".date('h:i:s')."\n";*/
        // echo "getTourIDforSelectedDate <br>";
        //$date = '2015-03-16';
        // for clearing the tables in DB, uncomment the below function.
      // $this->clearMasterTablesFromDB();
		//http://172.16.4.37:8000/sap/opu/odata/sap/ZDSD_TOUR_HD_I_SRV/TourHeader?$filter=ITourId eq 'S0000022682'
        /*$entitySet = 'ES_TOUR_HD';
        $filter = '?$filter=ExecutionDate%20eq%20datetime%27' .$date .'T00:00:00%27';
        $expand = '&$expand=TOUR_HDR2SA&$format=json';*/
		foreach($tid as $tourId) 
			{
            $this->TourId = $tourId;
		$filter = '?$filter=ITourId%20eq%20%27' .$tourId .'%27';
		$expand = '&$expand=TourSalesArea&$format=json';
        //$expand = '&$format=json'; 
         $filter = $filter .$expand;
       // $tableAddressURL = $this->createURLForSAP('TourHeaders',$filter);
		$tableAddressURL = $this->tidURL .$entitySet .$filter ;
		
		//$current .= "\n".date('h:i:s')."\n";
		//$current .= "\n".$tableAddressURL."\n";
      // echo//  $tableAddressURL = $this->downloadURL .$entitySet .$filter .$expand;
	  //   $tableAddressURL;
         
        $tableObject = $this->getSAPDBData($tableAddressURL, $this->uidSAP, $this->pwdSAP);
        $tabledata = $tableObject->d->results;
		//print_r($tabledata);
        $count = 0;
        foreach ($tabledata as $data) {
			//$current .= "\n".$data->TourId."\n";
            $this->tourIdArr[$count++] = $data->TourId;
            $this->updateDBData($data, 'tbl_touridstatus');
			$this->updateDBData($data, 'routemaster');
             if($this->routeExistStatus != "") {
                $this->updateExitStatusInTourTable($data->TourId);
                continue;
             }
        }
		}
        //file_put_contents($filename, $current);
       // $this->getSAPDataForTourList($date);
       // $this->getSAPDataForArticleItem();
    }
	//------------------------------------
    public function getSAPDataForArticleItem($divisionval) {
		//echo $divisionval;
		if($divisionval=='0')
		{
         //echo "getSAPDataForArticleItem <br>";
				$sql = "SELECT division FROM divisionlist ORDER BY division ASC";
				$queryResult = $this->executeSQLQuery($sql);
				foreach($queryResult[0] as $row) {
					$division = $row['division'];
					$filter = '?$filter=IDivision%20eq%20%27' .$division .'%27';
					//$expand = '&$expand=ART_HDR2SALESORG,ART_HDR2ALTUOM,ART_HDR2PRICE&$format=json';
					$expand = '&$expand=ArticlePrices,ArticleAltUoms&$format=json';
					//$expand = '&$expand=ART_HDR2ALTUOM&$format=json';
					$filter = $filter .$expand;
					$tableAddressURL = $this->createURLForSAP('ArticleHeaders',$filter);
			
				//echo "Table Addr URL : " .$tableAddressURL ."<br>";
				//exit;
					//get the xml object from SAP for each URL
					$tableObject = $this->getSAPDBData($tableAddressURL, $this->uidSAP, $this->pwdSAP);
					// echo "printing art price <br>";print_r($tableObject);// echo "<br>";
					$tabledata = $tableObject->d->results;
					//print_r($tabledata);// echo "printing art price <br>";
					foreach ($tabledata as $dataArray) {
						
						 $itemdatauom =$dataArray->ArticleAltUoms->results;
		
							foreach($itemdatauom as $uom) 
							{
								$param_array = array();
								$param_array[1] = $dataArray->ArtNo;
								$param_array[2] = str_replace("'", "`", $dataArray->ArtDesc1);
								$param_array[3] = $dataArray->ArtType;
								$param_array[4] = $division;
								$param_array[5] = $dataArray->ArtGroup;
								$param_array[6] = $dataArray->BaseUom;
								$param_array[7] = $dataArray->Ean11;
								$param_array[8] = $dataArray->EanCat; 
								$param_array[9] = $dataArray->ProdHier; 
								$param_array[10] = $dataArray->FreeChar; 
								$param_array[11] = $dataArray->ArtDesc2; 
								$param_array[12] = $uom->AltUom ; 
								$param_array[13] = $uom->Cnum; 
								$param_array[14] = $uom->Cdenom; 
								$param_array[15] = $dataArray->ArtGroupDesc;
								$param_array[16] = $dataArray->ArtGroupArabicDesc;
								$result = $this->SFA_Comman->executequery('CALL sp_int_import_tmpitem()', $param_array);
							}	
							
							$itemPriceArr =$dataArray->ArticlePrices->results;
								foreach($itemPriceArr as $itemprice)
								{
												$param_array = array();
												$param_array[1] = $itemprice->ArtNo;			
												$param_array[2] = $itemprice->CondRecNo;
												$param_array[3] = $itemprice->Price;
												$param_array[4] = $itemprice->Currency;
												$param_array[5] = $itemprice->Uom;
												$param_array[6] = $this->convertJsonDateToPHP($itemprice->ValidFrom);
												$param_array[7] = $this->convertJsonDateToPHP($itemprice->ValidTo);											
												$param_array[8] = $this->TourId;		 
												$result = $this->SFA_Comman->executequery('CALL sp_int_import_statgeitemprice()', $param_array);
								 }
						 //$this->updateDBData($dataArray, 'itemmaster');
						 //$this->updateRouteItemMappingTable($dataArray);
					}
					
				}
				
				$this->SFA_Comman->executequery('CALL sp_post_items()',"");
		}else
				
				{
					$division = $divisionval;
					$filter = '?$filter=IDivision%20eq%20%27' .$division .'%27';
					//$expand = '&$expand=ART_HDR2SALESORG,ART_HDR2ALTUOM,ART_HDR2PRICE&$format=json';
					$expand = '&$expand=ArticlePrices,ArticleAltUoms&$format=json';
					
					$filter = $filter .$expand;
					$tableAddressURL = $this->createURLForSAP('ArticleHeaders',$filter);
			
					//echo "Table Addr URL : " .$tableAddressURL ."<br>";
					//get the xml object from SAP for each URL
					$tableObject = $this->getSAPDBData($tableAddressURL, $this->uidSAP, $this->pwdSAP);
					// echo "printing art price <br>";print_r($tableObject);// echo "<br>";
					$tabledata = $tableObject->d->results;
					//print_r($tabledata);// echo "printing art price <br>";
					foreach ($tabledata as $dataArray) {
							$itemdatauom =$dataArray->ArticleAltUoms->results;
		
							foreach($itemdatauom as $uom) 
							{
								$param_array = array();
								$param_array[1] = $dataArray->ArtNo;
								$param_array[2] = str_replace("'", "''", $dataArray->ArtDesc1);
								$param_array[3] = $dataArray->ArtType;
								$param_array[4] = $division;
								$param_array[5] = $dataArray->ArtGroup;
								$param_array[6] = $dataArray->BaseUom;
								$param_array[7] = $dataArray->Ean11;
								$param_array[8] = $dataArray->EanCat; 
								$param_array[9] = $dataArray->ProdHier; 
								$param_array[10] = $dataArray->FreeChar; 
								$param_array[11] = $dataArray->ArtDesc2; 
								$param_array[12] = $uom->AltUom ; 
								$param_array[13] = $uom->Cnum; 
								$param_array[14] = $uom->Cdenom; 
								$result = $this->SFA_Comman->executequery('CALL sp_int_import_tmpitem()', $param_array);
							}	
							
							$itemPriceArr =$dataArray->ArticlePrices->results;
								foreach($itemPriceArr as $itemprice)
								{
												$param_array = array();
												$param_array[1] = $itemprice->ArtNo;			
												$param_array[2] = $itemprice->CondRecNo;
												$param_array[3] = $itemprice->Price;
												$param_array[4] = $itemprice->Currency;
												$param_array[5] = $itemprice->Uom;
												$param_array[6] = $this->convertJsonDateToPHP($itemprice->ValidFrom);
												$param_array[7] = $this->convertJsonDateToPHP($itemprice->ValidTo);											
												$param_array[8] = $this->TourId;			 
												$result = $this->SFA_Comman->executequery('CALL sp_int_import_statgeitemprice()', $param_array);
								 }
						
						 //$this->updateDBData($dataArray, 'itemmaster');
						 //$this->updateRouteItemMappingTable($dataArray);
					}
					$this->SFA_Comman->executequery('CALL sp_post_items()',"");
					
				}
			//	$this->SFA_Comman->executequery('CALL sp_post_items()',"");
    }

    private function getSAPDataForTourList($date) {
        // echo "getSAPDataForTourList <br>";

        $filter = '?$filter=IDate%20eq%20datetime%27' .$date .'T00:00:00%27';
        $expand = '&$format=json';
        $filter = $filter .$expand;
        $tableAddressURL = $this->createURLForSAP('TourHeaders',$filter);
    
        // echo "Table Addr URL : " .$tableAddressURL ."<br>";
        // get the xml object from SAP for each URL
        $tableObject = $this->getSAPDBData($tableAddressURL, $this->uidSAP, $this->pwdSAP);
        // echo "printing RouteList <br>";print_r($tableObject);// echo "<br>";
        $tabledata = $tableObject->d->results;
        //print_r($tabledata);// echo "printing Route data <br>";
        foreach ($tabledata as $dataArray) {
             $this->updateDBData($dataArray, 'routemaster');
             if($this->routeExistStatus != "") {
                $this->updateExitStatusInTourTable($dataArray->TourId);
                continue;
             }
             $this->getSAPDataForCustomer($dataArray->DriverId1);
        }
    }
    private function getSAPDataForCustomer($driver) {
		
        // echo "getSAPDataForCustomer <br>";
       // $filter = '?$filter=IDriverNo%20eq%20%27' .$driver .'%27';
      //  $expand = '&$expand=CUSTOMER_HDR2SA,CUSTOMER_HDR2PRICE,CUSTOMER_HDR2FLAG&$format=json';
		$filter = '?$filter=IDriverNo%20eq%20%27' .$driver .'%27';
        $expand = '&$expand=CustomerPrices,CustomerFlag,CustomerSalesAreas&$format=json';
        $filter = $filter .$expand;
        $tableAddressURL = $this->createURLForSAP('CustomerHeaders',$filter);
         // "Table Addr URL : " .$tableAddressURL ."<br>";
        // get the xml object from SAP for each URL
        $tableObject = $this->getSAPDBData($tableAddressURL, $this->uidSAP, $this->pwdSAP);
        // echo "printing Customerlist <br>";print_r($tableObject);// echo "<br>";
        $tabledata = $tableObject->d->results;
       // print_r($tabledata);// echo "printing customer data <br>";
        foreach ($tabledata as $dataArray) {
             $sql = "SELECT customercode FROM customermaster WHERE alternatecode = '".$dataArray->CustNo."'";
             $flag = $this->getInsertOrUpdateFlag($sql);
			
            $sql = $this->insertCustomerData($dataArray,$flag,$driver);
			
            $custPriceArr = $dataArray->CustomerPrices->results;
		
            foreach($custPriceArr as $custPrice) 
			{				
				//---------
					
				$param_array = array();				
				$param_array[1] = $custPrice->CustNo;
				$param_array[2] = $custPrice->ArtNo;
				$param_array[3] = $custPrice->CondRecNo;				
				$param_array[4] = $custPrice->CustPrice;				
				$param_array[5] = $custPrice->Uom;				
				$result = $this->SFA_Comman->executequery('CALL sp_int_import_statgecustprice()', $param_array);
				//---------
                 //$this->updateDBData($custPrice, 'customerpricingdetail');
            }

          /*$custFlagArr = $dataArray->CustomerFlag->results;
           foreach($custFlagArr as $custFlag) {
              $this->updateDBData($custFlag, 'customerflag');
           }*/

            $sql = "SELECT customercode FROM routesequence WHERE customercode = (select customercode from customermaster where alternatecode='$dataArray->CustNo')";
            $flag = $this->getInsertOrUpdateFlag($sql);
            $this->addRouteSequenceData($dataArray,$flag,$driver);
        }
    }
	//--------------
	private function getSAPDataForCustomerflag($driver) {
		
        // echo "getSAPDataForCustomer <br>";
       // $filter = '?$filter=IDriverNo%20eq%20%27' .$driver .'%27';
      //  $expand = '&$expand=CUSTOMER_HDR2SA,CUSTOMER_HDR2PRICE,CUSTOMER_HDR2FLAG&$format=json';
		$filter = '?$filter=IDriverNo%20eq%20%27' .$driver .'%27';
        $expand = '&$expand=CustomerPrices,CustomerFlag,CustomerSalesAreas&$format=json';
        $filter = $filter .$expand;
        $tableAddressURL = $this->createURLForSAP('CustomerHeaders',$filter);
         // "Table Addr URL : " .$tableAddressURL ."<br>";
        // get the xml object from SAP for each URL
        $tableObject = $this->getSAPDBData($tableAddressURL, $this->uidSAP, $this->pwdSAP);
        // echo "printing Customerlist <br>";print_r($tableObject);// echo "<br>";
        $tabledata = $tableObject->d->results;
       // print_r($tabledata);// echo "printing customer data <br>";
        foreach ($tabledata as $dataArray) {

          $custFlagArr = $dataArray->CustomerFlag->results;
           foreach($custFlagArr as $custFlag) {
              $this->updateDBData($custFlag, 'customerflag');
           }
            
        }
    }
	//--------------
    private function getSAPDataForPromotion($tourId) {
        // echo "getSAPDataForPromotion <br>";
        //$entitySetArr = array('ES_SHIPTO','ES_SHIPTO_ART','ES_SHIPTO_PRODHIER','ES_CUSTGRP_PRODHIER');
		//$entitySetArr = array('ShiptoHeaders');
		$entitySetArr = array('ShipToPromoHeaders');
        $filter = '?$filter=ITourId%20eq%20%27' . $tourId .'%27';
        $expand = '&$format=json';
        foreach($entitySetArr as $entitySet) {
            $count = 0;
            $tableAddressURL = $this->promoheaderURL .$entitySet .$filter .$expand;
          //  echo "tableAddressURL : " .$tableAddressURL ."<br>";
            $tableObject = $this->getSAPDBData($tableAddressURL, $this->uidSAP, $this->pwdSAP);
            $tabledata = $tableObject->d->results;
            //print_r($tabledata); // echo "<br>";
            foreach ($tabledata as $data) {
			//$rangedata =$data->ShiptoItem->results;	
				//----------------
				//foreach($rangedata as $rdata) 
				 {
			
			//-------
			$param_array = array();			
			$param_array[1] = $tourId;
			$param_array[2] = $data->SalesOrg;
			$param_array[3] = $data->DistChannel;
			$param_array[4] = $data->Division;
			$param_array[5] = $data->ShipTo;
			$param_array[6] = 0;
			$param_array[7] = 0;
			$param_array[8] = 0;
			$param_array[9] = 0;
			$param_array[10] = $data->Currency;
			$param_array[11] = $data->PercentAmt;
			$param_array[12] = $data->Amount;
				
		    $result = $this->SFA_Comman->executequery('CALL sp_int_import_stageheaderdiscount()', $param_array);
				}
				//----------------
                // echo "Loop Count : " .$count++ ."<br>";
               // print_r($data); // echo "<br>";
                //$this->updatePromotionDBData($data);
            }
        }
    }
	
	//--------------Ship to Article
	public function getDataForPromotionshipto($tourId) 
	{
       
		 $arr=array('ShiptoArticles'=>'1', 'ShiptoProductHiers'=>'2','Shiptos'=>'4','CustomerGrpProductHiers'=>'3','SEmployees'=>'5','SEmployeeArticles'=>'6');
		$arrtype=array('ShiptoArticles'=>'ZPTI', 'ShiptoProductHiers'=>'ZPTI','Shiptos'=>'ZPTI','CustomerGrpProductHiers'=>'ZPTI','SEmployees'=>'ZPRI','SEmployeeArticles'=>'ZPRI');
		$arrtourid=array('ShiptoArticles'=>'ITourid', 'ShiptoProductHiers'=>'ITourid','Shiptos'=>'ITourid','CustomerGrpProductHiers'=>'ITourid','SEmployees'=>'ITourId','SEmployeeArticles'=>'ITourId');
		$arrcustno=array('ShiptoArticles'=>'ShipTo', 'ShiptoProductHiers'=>'ShipTo','Shiptos'=>'ShipTo','CustomerGrpProductHiers'=>'ShipTo','SEmployees'=>'CustNo','SEmployeeArticles'=>'CustNo');
		//$entitySetArr = array('ShiptoArticles','ShiptoProductHiers','CustomerGrpProductHiers','Shiptos','SEmployees','SEmployeeArticles');	
		$entitySetArr = array('Shiptos','SEmployeeArticles');	
		$os = array("SEmployees");
		$descon = array("SEmployeeArticles");
		
        foreach($entitySetArr as $entitySet)
		{
            
			$filter = '?$filter='.$arrtourid[$entitySet].'%20eq%20%27'.$tourId.'%27';
			$expand = '&$format=json';
			$filter = $filter .$expand;
			$tableAddressURL = $this->createURLForSAP($entitySet,$filter);			
			//echo "tableAddressURL : " .$tableAddressURL ."<br>";
            $tableObject = $this->getSAPDBData($tableAddressURL, $this->uidSAP, $this->pwdSAP);
			
            $tabledata = $tableObject->d->results;
			 
            foreach ($tabledata as $data) 
			{
             
				if(in_array($entitySet,$os))
				{
					$uniqcombination = "@11".ltrim($data->$arrcustno[$entitySet],'0').trim($data->Division).$arr[$entitySet];
					$Artno = 0;	
					$ardesc =$data->ArabicTextCond;
					$engdesc =$data->EngTextCond;
					
				}else{
					
				if($data->ArtNo == "")
					{
						$uniqcombination = "@11".ltrim($data->$arrcustno[$entitySet],'0').trim($data->Division).$arr[$entitySet];
						$Artno = 0;	
						if(in_array($entitySet,$descon))
						{
								
							$ardesc =$data->ArabicTextCond;
							$engdesc =$data->EngTextCond;
						}else
						{
							$ardesc ="";
							$engdesc ="";
						}	
					}else
					{
						$uniqcombination = abs(intval($data->Amount))."22". ltrim($data->$arrcustno[$entitySet],'0').trim($data->Division).$arr[$entitySet];
						$Artno=$data->ArtNo;
						if(in_array($entitySet,$descon))
						{
							$ardesc =$data->ArabicTextCond;
							$engdesc =$data->EngTextCond;	
						}else
						{
							
							$ardesc ="";
							$engdesc ="";
						}
					}	
					
				}	
			
					
					
							$param_array = array();
							$param_array[1] = $tourId;
							$param_array[2] = $data->SalesOrg;
							$param_array[3] = $data->DistChannel;
							$param_array[4] = $data->Division;
							$param_array[5] = $data->$arrcustno[$entitySet];
							$param_array[6] = $Artno;
							$param_array[7] = $data->CondRec;
							$param_array[8] = $data->Amount;
							$param_array[9] = $data->PercentAmt;
							$param_array[10] = $data->Unit;
							$param_array[11] = '111';
							$param_array[12] = '111';
							$param_array[13] = $uniqcombination;
							$param_array[14] = $arr[$entitySet];
							$param_array[15] = $arrtype[$entitySet];
							$param_array[16] = $ardesc;
							$param_array[17] = $engdesc;
							
							$result = $this->SFA_Comman->executequery('CALL sp_int_import_tmppromotion()', $param_array);
				
			}
			
			
		}
		
		$resultcust =$this->SFA_Comman->executequery('CALL sp_post_promotions()',"");
		
		
	}

    /*private function updatePromotionDBData($dataArray, $tableName) {
        // echo "updatePromotionDBData <br>" ;
        switch ($tableName) {
            case 'promotioncustomer':
                break;
            default:
                break;
        }
    }*/

    private function getSAPDataForPricing($tourId) {
        // echo "getSAPDataForPricing <br>";
        $entitySetArr = array('ES_CUSTGRP_ART_PRICE');
        $filter = '?$filter=TourId%20eq%20%27' . $tourId .'%27';
        $expand = '&$format=json';
        foreach($entitySetArr as $entitySet) {
            $count = 0;
            $tableAddressURL = $this->pricingURL .$entitySet .$filter .$expand;
            $tableObject = $this->getSAPDBData($tableAddressURL, $this->uidSAP, $this->pwdSAP);
            $tabledata = $tableObject->d->results;
            foreach ($tabledata as $data) 
			{
				$param_array = array();
				$param_array[1] = $data->CustGrp2;
				$param_array[2] = $data->ArtNo;
				$param_array[3] = $data->CondRecNo;      
				$param_array[4] = $data->CustPrice;
				$param_array[5] = $data->PercentAmt;
				$param_array[6] = $data->Uom;
				$param_array[7] = $this->convertJsonDateToPHP($data->ValidFrom);
				$param_array[8] = $this->convertJsonDateToPHP($data->ValidTo);
				$result = $this->SFA_Comman->executequery('CALL sp_int_import_statgecustgrpprice()', $param_array);
               // $this->updatePricingDBData($data);
			   
            }
        }
    }

    private function updatePricingDBData($dataArray, $tableName) {
        // echo "updatePricingDBData <br>" ;
        $sql = "SELECT pricingplankey from customerpricing1 where pricingplankey = $dataArray->CustGrp2";
        $flag = $this->getInsertOrUpdateFlag($sql);
        $this->insertCustomerGrpPricingData($dataArray,$flag);

        $sql = "SELECT primary_key from pricingdetail1 where primary_key = '$dataArray->CondRecNo'";
        $flag = $this->getInsertOrUpdateFlag($sql);
        $this->insertPricingGrpDetailData($dataArray, $flag);

        $this->insertCustGrpPriceKey($dataArray, 'UPDATE');

    }

    //*********************************************Getting XML From SAP*****************************************************
    /*
     * Function Name    : getSAPDBData
     * Params           : $url - url to get the SAP Data
     * Params           : $username - User id for SAP
     * Params           : $password - password for SAP
     * Descripton       : Connect to SAP and get the XML data
     */
    private function getSAPDBData($url, $username, $password) {
          // echo "getSAPDBData<br>";
          // echo $url ."<br>";
          $post = null;
          $ch = curl_init();
          curl_setopt($ch, CURLOPT_URL, $url);
          curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
          curl_setopt($ch, CURLOPT_USERPWD, "$username:$password");
          curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
          $result = curl_exec($ch);
          if(curl_errno($ch)){
               echo 'Curl error: ' . curl_error($ch) ."<br>";
          }
          // echo "Result: <br>";print_r($result);// echo "<br>";
          $info = curl_getinfo($ch);
          if($info['http_code'] != 200) {
           // die("Data Cannot be retrieved. Http Response Code is  :  " .$info['http_code'] .". Closing connection <br>");
          }
		  /*else if ($info['http_code'] != 202)
		  {
			  
		  }*/
          curl_close($ch);
          $jsonObj = json_decode($result);
          return $jsonObj;
    }

    //*********************************************Format the data before updatingDB************************************************
    /*
     * Function Name    : getFormattedData
     * Params           : $value - value to be formatted
     * Descripton       : removing the unwanted characters from data
     */
    private function getFormattedData($value) {
        // echo "getFormattedData<br>";
        $data = trim($value);
        $data = str_replace("'", "`",$data);
        $data = str_replace('\0', ' ', $data);
        $data = str_replace('"', ' ', $data);
        $data = str_replace(",", "", $data);
        $data = trim($data);
        $data = strtoupper($data);
        return $data;
    }

    //*********************************************Update DB*************************************************
    /*
     * Function Name    : updateDBData
     * Params           :
     * Params           : $dataArray - values that need to udated for the table
     * Params           : $tableName - Name of the table to be udpated
     * Descripton       : according to the table name insert the downloaded values.
     */
    private function updateDBData($dataArray, $tableName) {
        // echo "updateDBData<br>";
        // echo "Table Name : " .$tableName ."<br>";
        $sql = "";
        $flag = "";
        switch($tableName) {
            case 'tbl_touridstatus':
                $sql = "SELECT tourid FROM tbl_touridstatus WHERE tourid = '$dataArray->TourId'";
                $flag = $this->getInsertOrUpdateFlag($sql);
                $sql = $this->insertTourIdData($dataArray,$flag);
                break;
            case 'customermaster':
                //  handling moved as seperate function
                break;
            case 'routemaster':
				  $sql = "SELECT routecode FROM routemaster WHERE routecode = '$dataArray->Route'";
				
                 $flag = $this->getInsertOrUpdateFlag($sql);
				
                if($flag == 'UPDATE') 
				{
                      $sql = "SELECT count(exportedflag) as counter FROM startendday WHERE exportedflag = 0 and tourid = '".$dataArray->TourId."'";
					 $queryResult = $this->executeSQLQuery($sql);
                    
							if($queryResult[0][0]['counter'] > 0) 
							{
								$this->routeExistStatus = 'Driver (' .$dataArray->DriverId1 .") Already On Tour " .$dataArray->TourId;
								break;
							} else 
							{
							 $sql = "SELECT count(tourid) as counter FROM tbl_touridstatus WHERE exportstatus = '0' and driver_id = '".$dataArray->DriverId1."' ";
								$queryResult = $this->executeSQLQuery($sql);	
								if($queryResult[0][0]['counter'] > 1) 
								{	
								 	$this->routeExistStatus = 'Driver (' .$dataArray->DriverId1 .") Already On Tour " .$dataArray->TourId;
									break;
								}else
								{
									$this->routeExistStatus = "";
								}
								
							}
                    $this->updateExitStatusInTourTable($dataArray->TourId);
					
				}
				
                $sql = $this->insertRouteMasterData($dataArray,$flag);
                
            case 'itemmaster':
                $sql = "SELECT alternatecode FROM itemmaster WHERE alternatecode = '".$dataArray->ArtNo."'";
                $flag = $this->getInsertOrUpdateFlag($sql);
                $sql = $this->insertArticleItemMasterData($dataArray,$flag);

                $itemPriceArr =$dataArray->ART_HDR2PRICE->results;
                foreach($itemPriceArr as $itemprice) {
                    $this->insertArticleItemPrice($itemprice, 'UPDATE');
                }
               break;
            case 'routeflag':
                $sql = $this->insertRouteFlagData($dataArray,'UPDATE');
                break;
            case 'customerflag':
                $sql = $this->insertCustomerFlagData($dataArray,'UPDATE');
                break;
            case 'custmastercredit':
                $sql = $this->insertCustMasterCredit($dataArray,'UPDATE');
                break;
            case 'custvisitid':
                $sql = $this->insertCustVisitId($dataArray,'UPDATE');
                break;
            case 'customerpricingdetail':
                $sql = "SELECT primary_key from pricingdetail1 where primary_key = '$dataArray->CondRecNo'";
                $flag = $this->getInsertOrUpdateFlag($sql);
                $sql = $this->insertArticlePricingDetailData($dataArray,$flag);

                $sql = "SELECT primary_key from customerpricing1 where primary_key=(select customercode from customermaster where alternatecode='$dataArray->CustNo')";
                $flag = $this->getInsertOrUpdateFlag($sql);
                $sql = $this->insertCustomerPricingData($dataArray,$flag);

                $sql = $this->insertCustPriceKey($dataArray,'UPDATE');
                break;
            case 'custinvoicehht':
                $sql = "SELECT invoicenumber FROM tempcustomerinvoice WHERE invoicenumber = '$dataArray->DocNo'";
                // echo "Invoice " .$dataArray->DocNo ."hht " .$dataArray->RefDocNo  ."<br>";
                $flag = $this->getInsertOrUpdateFlag($sql);
                if($flag == 'UPDATE') {
                    $sql = $this->insertTempCustInvoice($dataArray, $flag);
                } else {
                        // check in future
                }
                break;
            case 'custinvoice':
                $sql = "SELECT invoicenumber FROM customerinvoice WHERE invoicenumber = '$dataArray->RefDocNo'";
                // echo "Invoice " .$dataArray->RefDocNo ."<br>";
                $flag = $this->getInsertOrUpdateFlag($sql);
                if($flag == 'INSERT') {
                    $sql = $this->insertTempCustInvoice($dataArray, $flag);
                } else {
                    $sql = $this->insertCustInvoice($dataArray, $flag);
                }
                break;
            case 'visitplan':
                $sql = "SELECT routecode from routesequence where routecode=(select routecode from routemaster where memo1 = '$this->TourId') and customercode=(select customercode from customermaster where alternatecode='$dataArray->CustNo') and rp32weeknumber=9";
                $flag = $this->getInsertOrUpdateFlag($sql);
                $sql = $this->insertRouteSequenceData($dataArray, $flag);
                break;
            case 'startingloaddetail':
                $sql = "";
                $this->insertStartingLoadDetail($dataArray, $flag);
                break;
            case 'salesorderheader':
                $sql = "SELECT invoicenumber from salesorderheader where invoicenumber = '$dataArray->OfficialDelvNo'";
                $flag = $this->getInsertOrUpdateFlag($sql);
                $sql = $this->insertSalesOrderHeader($dataArray, $flag);
                break;
            case 'salesorderdetail':
                $routeKey = $_SESSION['RouteKey'];
                $sql = "SELECT coopid from salesorderdetail where coopid = '$dataArray->DelvNo' and routekey = '$routeKey'";
                $flag = $this->getInsertOrUpdateFlag($sql);
                $sql = $this->insertOrderDetail($dataArray, $flag);  
                break;
            case 'customermessages':
                if($dataArray->MsgType == '02') {
                    $sql = "SELECT messagekey from customermessages where messagekey = '$dataArray->MsgSeq'";
                } else {
                    $sql = "SELECT messagekey from salesmanmessages where messagekey = '$dataArray->MsgSeq'";
                }
                $flag = $this->getInsertOrUpdateFlag($sql);
                $sql = $this->insertSalesmanMessage($dataArray, $flag);
                break;
            case 'promoplanheader':
                $sql = "SELECT plannumber from promoplanheader where plannumber = '$dataArray->ConRecType'";
                $flag = $this->getInsertOrUpdateFlag($sql);
                $sql = $this->insertPromoPlanHeaderData($dataArray, $flag);

                $sql = "SELECT plannumber from promoplandetail where plannumber = '$dataArray->ConRecType'";
                $flag = $this->getInsertOrUpdateFlag($sql);
                $sql = $this->insertPromoPlanDetail($dataArray, $flag);

                $sql = "SELECT promotionkey from promokeyheader where promotionkey = (select customercode from customermaster where alternatecode='$dataArray->CustNo')";
                $flag = $this->getInsertOrUpdateFlag($sql);
                $sql = $this->insertPromoKeyHeaderData($dataArray, $flag);

                $sql = "SELECT plannumber from promokeydetail where primary_key = '$dataArray->ConRecType'";
                $flag = $this->getInsertOrUpdateFlag($sql);
                $sql = $this->insertPromoKeyDetail($dataArray, $flag);

                $sql = "SELECT assignmentnumber from promotionassignmentadvanced where range_id = '$dataArray->ConRecType'";
                $flag = $this->getInsertOrUpdateFlag($sql);
                $sql = $this->insertPromotionAdvanceData($dataArray, $flag);

                $sql = "SELECT alternatecode from customermaster where alternatecode = '$dataArray->CustNo'";
                $flag = $this->getInsertOrUpdateFlag($sql);
                $sql = $this->insertPromotionCustomer($dataArray, $flag);
                break;
            case 'productgroupheader':
                $_SESSION['productgroupheadertype'] = 1;
                selectInsertProductGroupHeader($dataArray);
                $_SESSION['productgroupheadertype'] = 2;
                selectInsertProductGroupHeader($dataArray);

                $_SESSION['productgroupdetailtype'] = 1;
                selectInsertProductGroupDetail($dataArray);
                $_SESSION['productgroupdetailtype'] = 2;
                selectInsertProductGroupDetail($dataArray);
                break;
            default:
                break;
        }
    }

    private function selectInsertProductGroupHeader($dataArray) {
        // echo "selectInsertProductGroupDetail : <br>";
        $type = $_SESSION['productgroupheadertype'];
        $groupNumber = ltrim($dataArray['uniqcombination'],'@') ."000" .$type;
        $sql = "SELECT groupnumber from productgroupheader where groupnumber = '$groupNumber'";
        $flag = $this->getInsertOrUpdateFlag($sql);
        $sql = $this->insertProductGroupHeader($dataArray, $flag);
    }

    private function selectInsertProductGroupDetail($dataArray) {
        // echo "selectInsertProductGroupDetail : <br>";
		$param_array = array();
		$param_array[1] = ltrim($dataArray['uniqcombination'],'@');
		$resultitem =$this->SFA_Comman->executequery('CALL sp_int_import_getitemdetail_frm_tmppromotion()', $param_array);	
		
		foreach($resultitem as $itemdata)
		{
			foreach($itemdata as $idata)
			{
				
				$type = $_SESSION['productgroupdetailtype'];
				$groupNumber = $idata['uniqcombination'] ."000" .$type;
				$itemcode =$idata['itemcode'];
				// $sql = "select groupnumber from productgroupdetail where groupnumber= '$groupNumber' and itemcode=(select actualitemcode from itemmaster where alternatecode='".$idata['itemcode']."')";
				$sql = "select groupnumber from productgroupdetail where groupnumber= '$groupNumber' and itemcode=".$idata['actutalitemcode']."";
				$flag = $this->getInsertOrUpdateFlag($sql);
				$sql = $this->insertProductGroupDetail($idata, $flag);
			}
		}
    }

    //*********************************************Filter creation for SAP URL*************************************************
    private function createURLForSAP($entitySet,$filter) {
        $urlAddress = "";
         switch($entitySet) {
             case 'ES_ART_PRICE':
             case 'ES_CUST_ART_PRICE':
             case 'ES_CUSTGRP_ART_PRICE':
                $urlAddress = $this->pricingURL .$entitySet .$filter;
                 break;
			case 'CreditHeaders':
                 $urlAddress = $this->creditURL .$entitySet .$filter;
                 break;
             case 'ES_ART_HDR':
             case 'ArticleHeaders':
                 $urlAddress = $this->articleURL .$entitySet .$filter;
                 break;
             case 'CustomerHeaders':
                 $urlAddress = $this->customerURL .$entitySet .$filter;
                 break;
             case 'TourHeaders':
                 $urlAddress = $this->tourURL .$entitySet .$filter;
                 break;
			 case 'OpenInvoices':
                 $urlAddress = $this->openInvoiceURL .$entitySet .$filter;
                 break;
			case 'ES_SHIPTO_ART':
                 $urlAddress = $this->promoheaderURL.$entitySet .$filter;
                 break;	
			case 'ShiptoArticles':
                 $urlAddress = $this->promoshipURL.$entitySet .$filter;
                 break;
			case 'ShiptoProductHiers':
                 $urlAddress = $this->promoshipURL.$entitySet .$filter;
                 break;	
			case 'SEmployees':
                 $urlAddress = $this->promoshipURL.$entitySet .$filter;
                 break;		
			case 'SEmployeeArticles':
                 $urlAddress = $this->promoshipURL.$entitySet .$filter;
                 break;		
			case 'CustomerGrpProductHiers':
                 $urlAddress = $this->promoshipURL.$entitySet .$filter;
                 break;
			case 'Shiptos':
                 $urlAddress = $this->promoshipURL.$entitySet .$filter;
                 break;
			case 'ES_VISIT_HDR':
                 $urlAddress = $this->QulityURL.$entitySet .$filter;
                 break;
			case 'ES_ROUTE_FLG':
                 $urlAddress = $this->QulityURL.$entitySet .$filter;
                 break;	 
			case 'ES_DELIVERY_HDR':
                 $urlAddress = $this->QulityURL.$entitySet .$filter;
                 break;				 
             default:
                 $urlAddress = $this->downloadURL .$entitySet .$filter;
                 break;
              
         }
         return $urlAddress;
    }
    /*
     * Function Name    : createFilterForSAPTable
     * Params           : $tourId - the id of tour which need to be synced
     * Params           : $tableName - Name of the table to be synced
     * Descripton       : to create the filter data to extract the xml from SAP
     */
    private function createFilterForSAPTable($tourId, $tableName) {
        // echo "createFilterForSAPTable : " .$tableName ."<br>";
        $filter = "";
        $expand = "";
        switch($tableName) {
            case 'customermaster':
                $filter = '?$filter=ITourid%20eq%20%27' . $tourId .'%27';
                $expand = '&$expand=CUSTOMER_HDR2ITM,CUSTOMER_HDR2SA&$format=json';
                break; 
            case 'routemaster':
                $filter = '?$filter=ITourid%20eq%20%27' . $tourId .'%27&$format=json';
                break;
            case 'itemmaster':
                $filter = '?$filter=ITourid%20eq%20%27' . $tourId .'%27';
                $expand = '&$expand=MAT_HDR2SALESORG,MAT_HDR2ALTUOM,MAT_HDR2TAX,MAT_HDR2EMPTIES&$format=json';
                break;
            case 'customerflag':
                //$filter = '?$filter=CTourid%20eq%20%27' . $tourId .'%27&$format=json';
				$filter = '?$filter=IDriverNo%20eq%20%27'.$tourId.'%27&$expand=CustomerPrices,CustomerFlag,CustomerSalesAreas&$format=json';
                break;
            case 'customerpricingdetail':
            case 'customerpricing':
            case 'customerpricekey': 
                //$filter = '?$filter=CTourid%20eq%20%27' . $tourId .'%27&$format=json';
                $filter = '?$filter=TourId%20eq%20%27' . $tourId .'%27&$format=json';
                break;
            case 'custmasterflag':
                $filter = '?$filter=CTourid%20eq%20%27' . $tourId .'%27&$format=json';
                break;
            case 'custmastercredit': 
                $filter = '?$filter=ITourId%20eq%20%27' . $tourId .'%27&$format=json';
                break;
            case 'visitplan':
                $filter = '?$filter=ITourid%20eq%20%27' . $tourId .'%27';
                $expand = '&$expand=VISIT_HDR2ACTIVITY&$format=json';
                break;
            case 'custvisitid': 
                $filter = '?$filter=ITourid%20eq%20%27' . $tourId .'%27&$format=json';
                break;
            case 'custinvoice': 
				$filter = '?$filter=ITourid%20eq%20%27' . $tourId .'%27';
                $expand = '&$expand=OPEN_INV_SAP2HHT&$format=json';
				break;
            case 'custinvoicehht':
                $filter = '?$filter=ITourid%20eq%20%27' . $tourId .'%27&$format=json';
                break;
            case 'startingloaddetail': 
                $filter = '?$filter=ITourid%20eq%20%27' . $tourId .'%27';
                //$expand = '&$expand=LOAD_HDR2MAT,LOAD_HDR2CG&$format=json';
                $expand = '&$expand=DEL_HDR2ITM&$format=json';
                break;
            case 'customermessages':
                $filter = '?$filter=ITourid%20eq%20%27' . $tourId .'%27&$format=json';
                break;
            case 'routeflag': 
                $filter = '?$filter=CTourid%20eq%20%27' . $tourId .'%27&$format=json';
                break;
            case 'salesorderheader':
                $filter = '?$filter=ITourid%20eq%20%27' .$tourId .'%27';
                $expand = '&$expand=DEL_HDR2ITM,DEL_HDR2COND&$format=json';
                break;
            case 'promoplanheader':
            case 'promoplandetail':
            case 'promokeyheader':
            case 'promokeydetail':
            case 'promotionassignmentadvanced':
            case 'promotioncustomer':
            case 'productgroupheader':
            case 'productgroupdetail':
                $filter = '?$filter=CTourid%20eq%20%27' . $tourId .'%27&$format=json';
                break;
        }
        return $filter.$expand;
    }

    //*********************************************Update DB*************************************************
    private function insertTourIdData($dataArray,$flag) {
        // echo "insertTourIdData <br>";
        $dateValue = $this->convertJsonDateToPHP($dataArray->PstartDate);
        $param_array = array();
        $param_array[1] = $dataArray->TourId;
        if($flag == 'INSERT') {
			
             $param_array[2] = $dateValue;
             $param_array[3] = $dataArray->TourSalesArea->results[0]->Division;
             $param_array[4] = $this->loginName;
			 $param_array[5] = $dataArray->DriverId1;
			 $param_array[6]=  str_replace("'", "`", $dataArray->RouteDesc);	
             $result = $this->SFA_Comman->executequery('CALL sp_int_import_tourid()', $param_array);
        } else {
            //$param_array[2] = $dataArray->importStatus;
            //$param_array[3] = $dataArray->exportStatus;
            //$this->SFA_Comman->executequery('CALL sp_int_import_update_tourid()', $param_array);
        }
        // echo "printing insert result <br>";print_r($result[0][0]);// echo "<br>";
    }
    /*
     * Function Name    : insertCustomerData
     * Params           :
     * Params           : $dataArray - values that need to udated for the table
     * Params           : $flag - Whether to insert or update to DB
     * Descripton       : insert the downloaded values to customermaster
     */
    private function insertCustomerData($dataArray, $flag,$driver) 
	{
		
		$hdr2sa = $dataArray->CustomerSalesAreas->results;
        $custGrp = $hdr2sa[0]->CustomerGroup2;
		
		$param_array = array();
        $param_array[1] = $driver;
        $param_array[2] = $dataArray->CustNo;
        $param_array[3] = str_replace("'", "`", $dataArray->Name1);
        $param_array[4] = $dataArray->Adrnr;
        $param_array[5] = $dataArray->Name3;
		$param_array[6] = $dataArray->Telf1;
		$param_array[7] = $hdr2sa[0]->Division;
        $param_array[8] =  $custGrp;
		$param_array[9] =  $dataArray->Street2;
		$param_array[10] = $dataArray->Street3;
		$param_array[11] = $dataArray->Street4;
		$param_array[12] = 	$dataArray->Street5;
		$param_array[13] = $dataArray->HomeCity;
		$param_array[14] = $dataArray->Building;
		$param_array[15] = $dataArray->Floor;
		$param_array[16] = $dataArray->Roomnumber;
		$param_array[17] = $dataArray->Name2;
		$param_array[18] = $dataArray->Name4;
		$param_array[19] = $dataArray->OrderBlock;
		        
        $result = $this->SFA_Comman->executequery('CALL sp_int_import_statgecustdetail()', $param_array);
		
     
    }

    /*
     * Function Name    : insertRouteMasterData
     * Params           :
     * Params           : $dataArray - values that need to udated for the table
     * Params           : $flag - Whether to insert or update to DB
     * Descripton       : insert the downloaded values to routemaster
     */
    private function insertRouteMasterData($dataArray, $flag) {
        // echo "insertRouteMasterData : <br>";
        $sql = "";
        $subareacode = "2";
         $this->SalesManCode = $dataArray->DriverId1;
         $sql = "select salesmancode from salesman where alternatesalesmancode='".$this->SalesManCode."'";
        $this->insertSalesData($dataArray, $this->getInsertOrUpdateFlag($sql));
		
        $param_array = array();
		
        if($flag == 'INSERT') {
            /*$sql = "select max(routecode) from routemaster";
            $routeCode = $this->getQueryResultRow($sql,'max(routecode)');
            if($routeCode == "" || $routeCode < 10) {
                $routeCode = 10;
            } else {
                $routeCode = $routeCode + 1;
            }*/
    
            

            $param_array[1] = $dataArray->Route;
            $param_array[2] = str_replace("'", "`", $dataArray->RouteDesc);
            $param_array[3] = "";
            $param_array[4] = $subareacode;
            $param_array[5] = $dataArray->DriverId1;
            $param_array[6] = $dataArray->TourId;
            $param_array[7] = $dataArray->ShipType;
            $param_array[8] = $dataArray->BegMile;
            $param_array[9] = $dataArray->VehicleId;
            $param_array[10] = $dataArray->Route;
            $param_array[11] = $dataArray->TourSalesArea->results[0]->Division;
            $param_array[12] = $this->loginName;
            $result = $this->SFA_Comman->executequery('CALL sp_int_import_routemaster()', $param_array);
          //  exit;
        } else {
            $param_array[1] = $str_replace("'", "`", $dataArray->RouteDesc);
            $param_array[2] = $dataArray->ShipType;
            $param_array[3] = $dataArray->BegMile;
            $param_array[4] = $dataArray->VehicleId;
            $param_array[5] = $dataArray->TourId;
            $param_array[6] = $dataArray->Route;
			$param_array[7] = $dataArray->TourSalesArea->results[0]->Division;
			$param_array[8] = $dataArray->DriverId1;
            $result = $this->SFA_Comman->executequery('CALL sp_int_import_update_routemaster()', $param_array);
        }
        // echo "printing insert result <br>";print_r($result[0][0]);// echo "<br>";
    }
    private function updateArticleItemUPC($dataArray, $flag)
	{
        $iupc = 1;
        if($dataArray->AltUom == 'OUT') {
            $iupc = $uom->Cnum;
        }
        $param_array = array();
        $param_array[1] = $iupc;
        $param_array[2] = $dataArray->ArtNo;
        if($flag == 'UPDATE') {
            $result = $this->SFA_Comman->executequery('CALL sp_int_import_update_itemmasterupc()', $param_array);
        }
        // echo "printing insert result <br>";print_r($result[0][0]);// echo "<br>";
    }
    private function insertArticleItemMasterData($dataArray, $flag)
	{
        // echo "insertArticleItemMasterData : <br>";
        $sql = "";
        $iupc = 1;
        $itemdatauom =$dataArray->ART_HDR2ALTUOM->results;
		
        foreach($itemdatauom as $uom) {
			
			
			$param_array = array();
			$param_array[1] = $dataArray->ArtNo;
			$param_array[2] = str_replace("'", "''", $dataArray->ArtDesc1);
			$param_array[3] = $dataArray->ArtType;
			$param_array[4] = $dataArray->Division;
			$param_array[5] = $dataArray->MatGroup;
			$param_array[6] = $dataArray->BaseUom;
			$param_array[7] = $dataArray->Ean11;
			$param_array[8] = $dataArray->EanCat; 
			$param_array[9] = $dataArray->ProdHier; 
			$param_array[10] = $dataArray->FreeChar; 
			$param_array[11] = $dataArray->MatDesc2; 
			$param_array[12] = $uom->AltUom ; 
			$param_array[13] = $uom->Cnum; 
			$param_array[14] = $uom->Cdenom; 
			$result = $this->SFA_Comman->executequery('CALL sp_int_import_tmpitem()', $param_array);
			
		
           /* if($uom->AltUom =='OUT') {
                $iupc = $uom->Cnum;
            }
			
		$param_array = array();
        $param_array[1] = str_replace("'", "''", $dataArray->ArtDesc1);
        $param_array[2] = $iupc;
        $param_array[3] = $dataArray->ArtNo;
        $param_array[4] = $uom->AltUom;
        $param_array[5] = $dataArray->Division;
		$param_array[6] = $dataArray->FreeChar;
		$param_array[7] = $dataArray->MatDesc2;
		$param_array[8] = $dataArray->Ean11;		 
		 
		
        if($flag == 'INSERT') {
				
            $result = $this->SFA_Comman->executequery('CALL sp_int_import_itemmasterarticle()', $param_array);
			$result = $this->SFA_Comman->executequery('CALL sp_int_update_itemupc()', $param_array);
			
        } else {
            $result = $this->SFA_Comman->executequery('CALL sp_int_import_itemmasterarticle()', $param_array);
			$result = $this->SFA_Comman->executequery('CALL sp_int_update_itemupc()', $param_array);
        }*/
        }
       
        // echo "printing insert result <br>";print_r($result[0][0]);// echo "<br>";
    }
    /*
     * Function Name    : insertItemMasterData
     * Params           :
     * Params           : $dataArray - values that need to udated for the table
     * Params           : $flag - Whether to insert or update to DB
     * Descripton       : insert the downloaded values to itemmaster
     */
    private function insertItemMasterData($dataArray, $flag) 
	{
        // echo "insertItemMasterData : <br>";
        $sql = "";
        $iupc = 1;
        $itemdatauom =$dataArray->MAT_HDR2ALTUOM->results;
        foreach($itemdatauom as $uom) {
            if($uom->AltUom =='OUT') {
                $iupc = $uom->Cnum;
            }
        }
        $param_array = array();
        $param_array[1] = $dataArray->MatDesc1;
        $param_array[2] = $dataArray->MatDesc2;
        $param_array[3] = $iupc;
        if($flag == 'INSERT') {
            $sql = "select division from tbl_touridstatus WHERE tourid = '$this->TourId'";
            $division = $this->getQueryResultRow($sql,'division');

            $param_array[4] = "";
            $param_array[5] = "";
            $param_array[6] = $dataArray->MatNo;
            $param_array[7] = $dataArray->BaseUom;
            $param_array[8] = $division;
            $param_array[9] = $this->loginName;
            $result = $this->SFA_Comman->executequery('CALL sp_int_import_itemmaster()', $param_array);
        } else {
            $param_array[4] = $dataArray->BaseUom;
            $param_array[5] = $dataArray->MatNo;
            $result = $this->SFA_Comman->executequery('CALL sp_int_import_update_itemmaster()', $param_array);
        }
        // echo "printing insert result <br>";print_r($result[0][0]);// echo "<br>";
    }

    /*
     * Function Name    : insertRouteFlagData
     * Params           :
     * Params           : $dataArray - values that need to udated for the table
     * Params           : $flag - Whether to insert or update to DB
     * Descripton       : insert the downloaded values to routemaster
     */
    private function insertRouteFlagData($dataArray, $flag) 
	{
       // echo "insertRouteFlagData : <br>";
       $sql = "select routecode from routemaster where memo1 = '$this->TourId'";
       $routeCode = $this->getQueryResultRow($sql,'routecode');

        $param_array = array();
        if($flag == 'UPDATE') 
		{
            $param_array[1] = $dataArray->Enablescanneruse;
            $param_array[2] = $dataArray->Password1;
            $param_array[3] = $dataArray->Password2;
            $param_array[4] = $dataArray->Password3;
            $param_array[5] = $dataArray->Password4;
            $param_array[6] = $dataArray->Password5;
            $param_array[7] = $dataArray->Passwordarray1;
            $param_array[8] = $dataArray->Passwordarray2;
            $param_array[9] = $dataArray->Passwordarray3;
            $param_array[10] = $dataArray->Passwordarray4;
            $param_array[11] = $dataArray->Passwordarray5;
            $param_array[12] = $dataArray->Passwordarray6;
            $param_array[13] = $dataArray->Passwordarray7;
            $param_array[14] = $dataArray->Passwordarray8;
            $param_array[15] = $dataArray->Passwordarray9;
            $param_array[16] = $dataArray->Passwordarray10;
            $param_array[17] = $dataArray->Passwordarray11;
            $param_array[18] = $dataArray->Passwordarray12;
            $param_array[19] = $dataArray->Passwordarray13;
            $param_array[20] = $dataArray->Passwordarray14;
            $param_array[21] = $dataArray->Passwordarray15;
            $param_array[22] = $dataArray->Passwordarray16;
            $param_array[23] = $dataArray->Promptodominput;
            $param_array[24] = $dataArray->Enableeodexpenses;
            $param_array[25] = $dataArray->Enableeodadjchecks;
            $param_array[26] = $dataArray->Enableeodaddchecks;
            $param_array[27] = $dataArray->Reqeoddepositreport;
            $param_array[28] = $dataArray->Reqeodsalesreport;
            $param_array[29] = $dataArray->Reqeodrteactivreport;
            $param_array[30] = $dataArray->Reqeodrtestlmtreport;
            $param_array[31] = $dataArray->Reqeodroutereviewrpt;
            $param_array[32] = $dataArray->Reqeodrtnexchreport;
            $param_array[33] = $dataArray->Reqeodplacementsrpt;
            $param_array[34] = $dataArray->Reqeodprcchgreport;
            $param_array[35] = $dataArray->Reqeodpromosreport;
            $param_array[36] = $dataArray->Reqeodnosalereport;
            $param_array[37] = $dataArray->Reqeodnondelreport;
            $param_array[38] = $dataArray->Reqeodexceptionrpt;
            $param_array[39] = $dataArray->Reqeodunauthbalance;
            $param_array[40] = $dataArray->Reqeodroasummary;
            $param_array[41] = $dataArray->Loadoutadjustments;
            $param_array[42] = $dataArray->Autocalculateloadin;
            $param_array[43] = $dataArray->Inventoryvariance;
            $param_array[44] = $dataArray->Enablenosale;
            $param_array[45] = $dataArray->Enablepostvoid;
            $param_array[46] = $dataArray->Cashbalance;
            $param_array[47] = $dataArray->Amountdecimaldigits;
            $param_array[48] = $dataArray->Displayinvsummary;
            $param_array[49] = $dataArray->Enabledamagedtrxn;
            $param_array[50] = $dataArray->Reqeodnonscannedreport;
            $param_array[51] = $dataArray->Reqeododomlogreport;
            $param_array[52] = $dataArray->Routecreditlimit;
            $param_array[53] = $dataArray->Allowgctocash;
            $param_array[54] = $dataArray->Usesalesdate;
            $param_array[55] = $dataArray->Lastcustomersequence;
            $param_array[56] = $dataArray->Enableloadtransfer;
            $param_array[57] = $dataArray->Routetype;
            $param_array[58] = $dataArray->Enforcecallsequence;
            $param_array[59] = $dataArray->Enablefoclimit;
            $param_array[60] = $dataArray->Usealternatecodes;
            $param_array[61] = $dataArray->Allowedradius;
            $param_array[62] = $dataArray->Expirylimit;
            $param_array[63] = $dataArray->Maxsavegpsallowed;
            $param_array[64] = $routeCode;
            $result = $this->SFA_Comman->executequery('CALL sp_int_import_routeflag()', $param_array);
        }
        // echo "printing insert result <br>";print_r($result[0][0]);// echo "<br>";
    }

    /*
     * Function Name    : insertCustomerFlagData
     * Params           :
     * Params           : $dataArray - values that need to udated for the table
     * Params           : $flag - Whether to insert or update to DB
     * Descripton       : insert the downloaded values to customermaster
     */
    private function insertCustomerFlagData($dataArray, $flag) {
        // echo "insertCustomerFlagData : <br>";
		$custFlagArr = $dataArray->CustomerFlag->results;
        $param_array = array();
      // if($flag == 'UPDATE')
			{
			   $sql = "SELECT customercode FROM customermaster where customercode = '".$dataArray->CustNo."'";
		$customercode = $this->getQueryResultRow($sql,'customercode');	
				
            $param_array[1] = $dataArray->Authorizeditemlistkey;
            $param_array[2] = $dataArray->Paymenttype;
            $param_array[3] = $dataArray->Invoicecopies;
            $param_array[4] = $dataArray->Authorizeditemlistctl;
            $param_array[5] = $dataArray->Enabledelayprint;
            $param_array[6] = $dataArray->Enablepriceeditinvs;
            $param_array[7] = $dataArray->Enablesuggestsales;
            $param_array[8] = $dataArray->Enableautofillreturns;
            $param_array[9] = $dataArray->Enableautofilldamaged;
            $param_array[10] = $dataArray->Enablesigcapture;
            $param_array[11] = $dataArray->Enablereturnstrxn;
            $param_array[12] = $dataArray->Enabledamagedreturns+1;
            $param_array[13] = $dataArray->Enablearcollection;
            $param_array[14] = $dataArray->Enablesurveyaudit;
            $param_array[15] = $dataArray->Enableinvoicecomment;
            $param_array[16] = $dataArray->Forcestockcapture;
            $param_array[17] = $dataArray->Allowcashoncreditexceed;
            $param_array[18] = $dataArray->Templateindicator;
            $param_array[19] = $dataArray->Arcustomertype;
            $param_array[20] = $dataArray->Invoiceformat;
            $param_array[21] = $dataArray->Enableposequipment;
            $param_array[22] = $dataArray->Enablesalestrxn;
            $param_array[23] = $dataArray->Thresholdlimit;
            $param_array[24] = $dataArray->Enforcepromotion;
            $param_array[25] = $dataArray->Enabledraftcopy;
            $param_array[26] = $dataArray->Enablebuybackfree;
            $param_array[27] = $dataArray->Verfiygpsdata;
            $param_array[28] = $dataArray->Fixedlatitude;
            $param_array[29] = $dataArray->Fixedlongitude;
            $param_array[30] = $dataArray->Autosettlecollection;
            $param_array[31] = $dataArray->Enableinvoicecopy;
            $param_array[32] = $dataArray->Redistributionkey;
            $param_array[33] = $dataArray->Gpssavecount;
            $param_array[34] = $dataArray->Enablerentaltrxn;
			$param_array[35] = substr($dataArray->ExceptionStartDate,0,4)."-".substr($dataArray->ExceptionStartDate,4,2)."-".substr($dataArray->ExceptionStartDate,6,2);
			$param_array[36] = substr($dataArray->ExceptionEndDate,0,4)."-".substr($dataArray->ExceptionEndDate,4,2)."-".substr($dataArray->ExceptionEndDate,6,2);
			$param_array[37] = $dataArray->CreditDays;
			$param_array[38] = $dataArray->BillToBill;
            $param_array[39] = $customercode;
            $result = $this->SFA_Comman->executequery('CALL sp_int_import_customerflag()', $param_array);
        }
		
		
        // echo "printing insert result <br>";print_r($result[0][0]);// echo "<br>";
    }

    /*
     * Function Name    : insertCustVisitId
     * Params           :
     * Params           : $dataArray - values that need to udated for the table
     * Params           : $flag - Whether to insert or update to DB
     * Descripton       : insert the downloaded values to customermaster
     */
    private function insertCustVisitId($dataArray, $flag) {
        // echo "insertCustVisitId : <br>";
        $param_array = array();
        if($flag == 'UPDATE') {
            $param_array[1] = $dataArray->VisitId;
            $param_array[2] = $dataArray->CustNo;
            $result = $this->SFA_Comman->executequery('CALL sp_int_import_custvisitid()', $param_array);
        }
        // echo "printing insert result <br>";print_r($result[0][0]);// echo "<br>";
    }

/*
     * Function Name    : insertTempCustInvoice
     * Params           :
     * Params           : $dataArray - values that need to udated for the table
     * Params           : $flag - Whether to insert or update to DB
     * Descripton       : insert the downloaded values to customerinvoice
     */
    private function insertTempCustInvoice($dataArray, $flag) {
        // echo "insertTempCustInvoice : <br>";
        $param_array = array();
        if($flag == 'INSERT') {
            $sql = "select salesmancode from salesman where alternatesalesmancode='$this->SalesManCode'";
            $salesmanCode = $this->getQueryResultRow($sql,'salesmancode');
            $param_array[1] = $dataArray->DocNo;
            $param_array[2] = $dataArray->RefDocNo;
            $param_array[3] = $dataArray->CustNo;
            $param_array[4] = $this->TourId;
            $param_array[5] = $salesmanCode;
            $param_array[6] = $dataArray->AmtDoccur;
            $param_array[7] = $this->loginName;
            $result = $this->SFA_Comman->executequery('CALL sp_int_import_tempcustomerinvoice()', $param_array);
        } else {
            $param_array[1] = $dataArray->DocNo;
            $param_array[2] = $dataArray->RefDocNo;
            $result = $this->SFA_Comman->executequery('CALL sp_int_import_update_tempcustomerinvoice()', $param_array);
        }
        // echo "printing insert result <br>";print_r($result[0][0]);// echo "<br>";
    }
    /*
     * Function Name    : insertCustInvoice
     * Params           :
     * Params           : $dataArray - values that need to udated for the table
     * Params           : $flag - Whether to insert or update to DB
     * Descripton       : insert the downloaded values to customerinvoice
     */
    private function insertCustInvoice($dataArray, $flag) {
        // echo "insertCustInvoice : <br>";
        $param_array = array();
        if($flag == 'INSERT') {
            $sql = "select salesmancode from salesman where alternatesalesmancode='$this->SalesManCode'";
            $salesmanCode = $this->getQueryResultRow($sql,'salesmancode');
            $param_array[1] = $dataArray->DocNo;
            $param_array[2] = $dataArray->RefDocNo;
            $param_array[3] = $dataArray->CustNo;
            $param_array[4] = $this->TourId;
            $param_array[5] = $salesmanCode;
            $param_array[6] = $dataArray->AmtDoccur;
            $result = $this->SFA_Comman->executequery('CALL sp_int_import_customerinvoice()', $param_array);
        } else {
            $param_array[1] = $dataArray->AmtDoccur;
            $param_array[2] = $dataArray->RefDocNo;
            $param_array[3] = $dataArray->CustNo;
            $result = $this->SFA_Comman->executequery('CALL sp_int_import_update_customerinvoice()', $param_array);
        }
        // echo "printing insert result <br>";print_r($result[0][0]);// echo "<br>";
    }

    /*
     * Function Name    : insertCustMasterCredit
     * Params           :
     * Params           : $dataArray - values that need to udated for the table
     * Params           : $flag - Whether to insert or update to DB
     * Descripton       : insert the downloaded values to customermaster
     */
   private function insertCustMasterCredit($dataArray, $flag) {
        // echo "insertCustMasterCredit : <br>";
			$param_array = array();
			$param_array[1] = $dataArray->CustNo;
            $param_array[2] = $dataArray->CredLimit;
            $param_array[3] = $dataArray->CCtrArea;
			$param_array[4] = $dataArray->OpenItems;
            $param_array[5] = $dataArray->DueItems;
			$param_array[6] = $dataArray->RiskCateg;
            $param_array[7] = $dataArray->MeAction;			
			$result = $this->SFA_Comman->executequery('CALL sp_int_import_statgecreditdata()', $param_array);
       /* if($flag == 'UPDATE') {
            $param_array[1] = $dataArray->OpenItems;
            $param_array[2] = $dataArray->CredLimit;
            $param_array[3] = $dataArray->CustNo;
            $result = $this->SFA_Comman->executequery('CALL sp_int_import_custmastercredit()', $param_array);
        }*/
        // echo "printing insert result <br>";print_r($result[0][0]);// echo "<br>";
    }

    private function insertArticleItemPrice($dataArray, $flag) {
        // echo "insertArticleItemPrice : <br>";
        //$param_array = array();
		{
			$param_array = array();
			$param_array[1] = $dataArray->ArtNo;			
			$param_array[2] = $dataArray->CondRecNo;
			$param_array[3] = $dataArray->Price;
			$param_array[4] = $dataArray->Currency;
			$param_array[5] = $dataArray->Uom;
			$param_array[6] = $this->TourId;			 
			$result = $this->SFA_Comman->executequery('CALL sp_int_import_statgeitemprice()', $param_array);
			
		 }	
        /*if ($flag == 'UPDATE') {
            $param_array[1] = $dataArray->Uom;
            $param_array[2] = $dataArray->Price;
            $param_array[3] = $dataArray->ArtNo;
            $result = $this->SFA_Comman->executequery('CALL sp_int_import_update_itemprice()', $param_array);
        }*/
        // echo "printing insert result <br>";print_r($result[0][0]);// echo "<br>";
    }
    /*
     * Function Name    : insertItemPrice
     * Params           :
     * Params           : $dataArray - values that need to udated for the table
     * Params           : $flag - Whether to insert or update to DB
     * Descripton       : insert the downloaded values to itemmaster
     */
    private function insertItemPrice($dataArray, $flag) {
        // echo "insertItemPrice : <br>";
        $param_array = array();
        if ($flag == 'INSERT') {
            $param_array[1] = $dataArray->MatDesc1;
            $param_array[2] = $dataArray->MatDesc2;
            $param_array[3] = $dataArray->MatNo;
            $result = $this->SFA_Comman->executequery('CALL sp_int_import_itemprice()', $param_array);
        } else {
            $param_array[1] = $dataArray->PricingUnit;
            $param_array[2] = $dataArray->MatDefaultPrice;
            $param_array[3] = $dataArray->MatNo;
            
            $result = $this->SFA_Comman->executequery('CALL sp_int_import_update_itemprice()', $param_array);
        }
        // echo "printing insert result <br>";print_r($result[0][0]);// echo "<br>";
    }

    /*
     * Function Name    : insertSalesOrderHeader
     * Params           :
     * Params           : $dataArray - values that need to udated for the table
     * Params           : $flag - Whether to insert or update to DB
     * Descripton       : insert the downloaded values to salesorderheader
     */
    private function insertSalesOrderHeader($dataArray, $flag) {
        // echo "insertSalesOrderHeader : <br>";
        $routeKey = "null";
        $visitKey = "null";

        $param_array = array();
        if ($flag == 'INSERT') {
            $param_array[1] = $dataArray->OfficialDelvNo;
            $param_array[2] = $routeKey;
            $param_array[3] = $visitKey;
            $param_array[4] = $dataArray->CustNo;
            $param_array[5] = $this->TourId;
            $param_array[6] = $this->SalesManCode;
            $param_array[7] = $this->loginName;
            $result = $this->SFA_Comman->executequery('CALL sp_int_import_salesorderheader()', $param_array);
        } else {
            $param_array[1] = $dataArray->OfficialDelvNo;
            $param_array[2] = $dataArray->VisitId;
            $param_array[3] = $dataArray->TotAmt;
            $result = $this->SFA_Comman->executequery('CALL sp_int_import_update_salesorderheader()', $param_array);
        }
        // echo "printing insert result <br>";print_r($result[0][0]);// echo "<br>";
    }

    /*
     * Function Name    : insertOrderDetail
     * Params           :
     * Params           : $dataArray - values that need to udated for the table
     * Params           : $flag - Whether to insert or update to DB
     * Descripton       : insert the downloaded values to salesorderdetail
     */
    private function insertOrderDetail($dataArray, $flag) {
        // echo "insertOrderDetail : <br>";
        $routeKey = $_SESSION['RouteKey'];

        $param_array = array();
        $param_array[1] = $dataArray->DplnUom;
        $param_array[2] = $routeKey;
        $param_array[3] = $dataArray->DelvNo;
        $param_array[4] = $dataArray->MatNo;
        $param_array[5] = $dataArray->DplnQty;
        if ($flag == 'INSERT') {
            $param_array[6] = $this->loginName;
            $result = $this->SFA_Comman->executequery('CALL sp_int_import_salesorderdetail()', $param_array);
        } else {
            $param_array[6] = $dataArray->NetPrice;
            $result = $this->SFA_Comman->executequery('CALL sp_int_import_update_salesorderdetail()', $param_array);
        }
        // echo "printing insert result <br>";print_r($result[0][0]);// echo "<br>";
    }

    /*
     * Function Name    : insertSalesmanMessage
     * Params           :
     * Params           : $dataArray - values that need to udated for the table
     * Params           : $flag - Whether to insert or update to DB
     * Descripton       : insert the downloaded values to customermessages
     */
    private function insertSalesmanMessage($dataArray, $flag) {
        // echo "insertSalesmanMessage : <br>";

        $param_array = array();
        $param_array[1] = $dataArray->MsgSeq;
        $param_array[2] = $dataArray->MsgText;
        if($flag == 'INSERT') {
            $param_array[3] = $this->loginName;
            if($dataArray->MsgType == '02') {
                $result = $this->SFA_Comman->executequery('CALL sp_int_import_customermessages()', $param_array);
            } else {
                $result = $this->SFA_Comman->executequery('CALL sp_int_import_salesmanmessages()', $param_array);
            }
        } else {
            if($dataArray->MsgType == '02') {
                $result = $this->SFA_Comman->executequery('CALL sp_int_import_update_customermessages()', $param_array);
            } else {
                $result = $this->SFA_Comman->executequery('CALL sp_int_import_update_salesmanmessages()', $param_array);
            }
        }
        // echo "printing insert result <br>";print_r($result[0][0]);// echo "<br>";
    }
    /*
     * Function Name    : updateMessageKey
     * Params           :
     * Params           : $dataArray - values that need to udated for the table
     * Descripton       : Update Customermaster or saleman after updating messages.
     */
    private function updateMessageKey($dataArray) {
        // echo "updateMessageKey : <br>";

        $param_array = array();
        $param_array[1] = $dataArray->MsgType;
        $param_array[2] = $dataArray->MsgSeq;
        $param_array[3] = $dataArray->CustNo;
        if ($flag == 'UPDATE') {
            $result = $this->SFA_Comman->executequery('CALL sp_int_import_update_messagekey()', $param_array);
        }
        // echo "printing insert result <br>";print_r($result[0][0]);// echo "<br>";
    }

    /*
     * Function Name    : insertPromoPlanHeaderData
     * Params           :
     * Params           : $dataArray - values that need to udated for the table
     * Params           : $flag - Whether to insert or update to DB
     * Descripton       : insert the downloaded values to promoplanheader
     */
    private function insertPromoPlanHeaderData($dataArray, $flag) {
        // echo "insertPromoPlanHeaderData : <br>";

        $param_array = array();
        $param_array[1] = $dataArray->PromoPlnNo;
        $param_array[2] = $dataArray->ShipTo;
		$param_array[3] = $dataArray->PromoType;
        if ($flag == 'INSERT') {
            $param_array[4] = $this->loginName;
            $result = $this->SFA_Comman->executequery('CALL sp_int_import_promoplanheader()', $param_array);
        } else {
            $result = $this->SFA_Comman->executequery('CALL sp_int_import_update_promoplanheader()', $param_array);
        }
        // echo "printing insert result <br>";print_r($result[0][0]);// echo "<br>";
    }
	//------------Ship to article
	private function insertPromoPlanHeadershiptoData($dataArray,$flag) {
        // echo "insertPromoPlanHeaderData : <br>";
		/*$findme   = '@';
		if(strpos($dataArray['uniqcombination'], $findme)=== false)
		{
		  $promotiontypecode = 2;	
		}else
		{
			$promotiontypecode = 6;
		}*/	
		
        $param_array = array();	
        $param_array[1] = ltrim($dataArray['uniqcombination'],'@');
        $param_array[2] = $dataArray['shipto'];
		$param_array[3] = 2;
        if ($flag == 'INSERT') {
            $param_array[4] = $this->loginName;
            $result = $this->SFA_Comman->executequery('CALL sp_int_import_promoplanheader()', $param_array);
			
        } else {
            $result = $this->SFA_Comman->executequery('CALL sp_int_import_update_promoplanheader()', $param_array);
        }
        // echo "printing insert result <br>";print_r($result[0][0]);// echo "<br>";
    }
    /*
     * Function Name    : insertPromoPlanDetail
     * Params           :
     * Params           : $dataArray - values that need to udated for the table
     * Params           : $flag - Whether to insert or update to DB
     * Descripton       : insert the downloaded values to promoplandetail
     */
    private function insertPromoPlanDetail($dataArray, $flag) {
        // echo "insertPromoPlanDetail : <br>";
        $sql = "";
        $assign="1";
        $qualification="1";
        $promotiontypecode=$dataArray->PromoType;
        $rangebasis=2;

        /*if($dataArray->ConditionType=='K007') {
            $assign = "1";
            $qualification = "1";
            $promotiontypecode = "2";
            $rangebasis = "1";
        } else {
            $assign = $dataArray->ConRecType ."0001";
            $qualification = $dataArray->ConRecType ."0002";
            $promotiontypecode = "1";
            $rangebasis = "1";
        }*/

       $param_array = array();
       $param_array[1] = $dataArray->PromoPlnNo;
       $param_array[2] = $dataArray->ShipTo;
       $param_array[3] = $promotiontypecode;
	   $param_array[4] = $dataArray->CondType;
       if ($flag == 'INSERT') {
           $param_array[5] = $qualification;
           $param_array[6] = $assign;
           $param_array[7] = $rangebasis;
           $param_array[8] = $this->loginName;
           $result = $this->SFA_Comman->executequery('CALL sp_int_import_promoplandetail()', $param_array);
       } else {
           $result = $this->SFA_Comman->executequery('CALL sp_int_import_update_promoplandetail()', $param_array);
       }
       // echo "printing insert result <br>";print_r($result[0][0]);// echo "<br>";
    }
	private function insertPromoPlanDetailshipto($dataArray, $flag) {
        // echo "insertPromoPlanDetail : <br>";
        $sql = "";
		$findme   = '@';
		if(strpos($dataArray['uniqcombination'], $findme)=== false)
		{
			$assign = ltrim($dataArray['uniqcombination'],'@')."0001";
            $qualification = ltrim($dataArray['uniqcombination'],'@')."0002";
            $promotiontypecode = "2";
            $rangebasis = "1";
			
		}else
		{
			$assign = 1;
            $qualification = 1;
            $promotiontypecode = "2";
            $rangebasis = "1";	
		}
           
        
       $param_array = array();
       $param_array[1] = ltrim($dataArray['uniqcombination'],'@');
       $param_array[2] = $dataArray['shipto'];
       $param_array[3] = $promotiontypecode;
	   $param_array[4] = $dataArray->condrec;
       if ($flag == 'INSERT') {
           $param_array[5] = $qualification;
           $param_array[6] = $assign;
           $param_array[7] = $rangebasis;
           $param_array[8] = $this->loginName;
           $result = $this->SFA_Comman->executequery('CALL sp_int_import_promoplandetail()', $param_array);
       } else {
           $result = $this->SFA_Comman->executequery('CALL sp_int_import_update_promoplandetail()', $param_array);
       }
       // echo "printing insert result <br>";print_r($result[0][0]);// echo "<br>";
    }
    /*
     * Function Name    : insertPromoKeyHeaderData
     * Params           :
     * Params           : $dataArray - values that need to udated for the table
     * Params           : $flag - Whether to insert or update to DB
     * Descripton       : insert the downloaded values to promokeyheader
     */
    private function insertPromoKeyHeaderData($dataArray, $flag) {
        // echo "insertPromoKeyHeaderData : <br>";
		$sql = "select customercode from customermaster where alternatecode ='".$dataArray->ShipTo."'";
            $customercodeno = $this->getQueryResultRow($sql,'customercode');
       $param_array = array();
       $param_array[1] = $customercodeno;
	   $param_array[2] = ltrim($dataArray->ShipTo,'0');
       if ($flag == 'INSERT') {
           
           $result = $this->SFA_Comman->executequery('CALL sp_int_import_promokeyheader()', $param_array);
       } else {
           $result = $this->SFA_Comman->executequery('CALL sp_int_import_update_promokeyheader()', $param_array);
       }
       // echo "printing insert result <br>";print_r($result[0][0]);// echo "<br>";
    }
	private function insertPromoKeyHeaderDatashipto($dataArray, $flag) {
        // echo "insertPromoKeyHeaderData : <br>";
			$sql = "select customercode from customermaster where alternatecode ='".$dataArray['shipto']."'";
            $customercodeno = $this->getQueryResultRow($sql,'customercode');
       $param_array = array();
       $param_array[1] = $customercodeno;
	   $param_array[2] = $dataArray['shipto'];
       if ($flag == 'INSERT') {
           $param_array[3] = $this->loginName;
           $result = $this->SFA_Comman->executequery('CALL sp_int_import_promokeyheader()', $param_array);
       } 
	   /*else {
           $result = $this->SFA_Comman->executequery('CALL sp_int_import_update_promokeyheader()', $param_array);
       }*/
       // echo "printing insert result <br>";print_r($result[0][0]);// echo "<br>";
    }
	

    /*
     * Function Name    : insertPromoKeyDetail
     * Params           :
     * Params           : $dataArray - values that need to udated for the table
     * Params           : $flag - Whether to insert or update to DB
     * Descripton       : insert the downloaded values to promokeydetail
     */
    private function insertPromoKeyDetail($dataArray, $flag) {
        // echo "insertPromoKeyDetail : <br>";
        $sql = "select customercode from customermaster where alternatecode='$dataArray->ShipTo'";
        $customercode = $this->getQueryResultRow($sql,'customercode');
		
        $assign="1";
        $qualification="1";
        $promotiontypecode=$dataArray->PromoType;
        $rangebasis=$dataArray->RgBasis;

        /*if($dataArray->ConditionType=='K007') {
            $assign = "1";
            $qualification = "1";
            $promotiontypecode = "2";
            $rangebasis = "1";
        } else {
            $assign = $dataArray->ConRecType ."0001";
            $qualification = $dataArray->ConRecType ."0002";
            $promotiontypecode = "1";
            $rangebasis = "1";
        }*/

       $param_array = array();
       $param_array[1] = $dataArray->PromoPlnNo;
       $param_array[2] = ltrim($dataArray->ShipTo,'0');
	   $param_array[3] = $customercode;
       $param_array[4] = $promotiontypecode;
       $param_array[5] = $qualification;
       $param_array[6] = $assign;
       $param_array[7] = $rangebasis;
       $param_array[8] = "";
       $param_array[9] = "";
       if ($flag == 'INSERT') {
           $param_array[10] = $this->loginName;
           $result = $this->SFA_Comman->executequery('CALL sp_int_import_promokeydetail()', $param_array);
       } else {
           $result = $this->SFA_Comman->executequery('CALL sp_int_import_update_promokeydetail()', $param_array);
       }
       // echo "printing insert result <br>";print_r($result[0][0]);// echo "<br>";
    }
	private function insertPromoKeyDetailshipto($dataArray, $flag) {
        // echo "insertPromoKeyDetail : <br>";
        $sql = "select customercode from customermaster where alternatecode='".$dataArray['shipto']."'";
        $customercode = $this->getQueryResultRow($sql,'customercode');
		
        $findme   = '@';
		if(strpos($dataArray['uniqcombination'], $findme)=== false)
		{
			$assign = ltrim($dataArray['uniqcombination'],'@')."0001";
            $qualification = ltrim($dataArray['uniqcombination'],'@')."0002";
            $promotiontypecode = "2";
            $rangebasis = "1";
			
		}else
		{
			$assign = 1;
            $qualification = 1;
            $promotiontypecode = "2";
            $rangebasis = "1";	
			
		}
            
        

       $param_array = array();
       $param_array[1] = ltrim($dataArray['uniqcombination'],'@');
       $param_array[2] = $dataArray['shipto'];
	   $param_array[3] = $customercode;
       $param_array[4] = $promotiontypecode;
       $param_array[5] = $qualification;
       $param_array[6] = $assign;
       $param_array[7] = $rangebasis;
       $param_array[8] = "";
       $param_array[9] = "";
       if ($flag == 'INSERT') 
	   {
           $param_array[10] = $this->loginName;
           $result = $this->SFA_Comman->executequery('CALL sp_int_import_promokeydetail()', $param_array);
       } else {
           $result = $this->SFA_Comman->executequery('CALL sp_int_import_update_promokeydetail()', $param_array);
       }
       // echo "printing insert result <br>";print_r($result[0][0]);// echo "<br>";
    }

    /*
     * Function Name    : insertPromotionAdvanceData
     * Params           :
     * Params           : $dataArray - values that need to udated for the table
     * Params           : $flag - Whether to insert or update to DB
     * Descripton       : insert the downloaded values to promotionassignmentadvanced
     */

    private function insertPromotionAdvanceData($dataArray) {
        // echo "insertPromotionAdvanceData : <br>";
		$rangedata =$dataArray->SHIPTO_HDR2ITM->results;
        foreach($rangedata as $rdata) {
			 $sql = "SELECT assignmentnumber from promotionassignmentadvanced where range_id = ".$rdata->PromoPlnNo.trim($rdata->ItmNo)."";
			 $flag = $this->getInsertOrUpdateFlag($sql);
			//-------
			$param_array = array();
			$param_array[1] = $rdata->PromoPlnNo.trim($rdata->ItmNo);
			$param_array[2] = $dataArray->PromoPlnNo;
			$param_array[3] = $rdata->PercentCap;
			$param_array[4] = $rdata->ScaleLow;
			$param_array[5] = $rdata->ScaleHigh;
			$param_array[6] = '0';
				if ($flag == 'INSERT') {
				   $result = $this->SFA_Comman->executequery('CALL sp_int_import_promotionassignmentadvanced()', $param_array);
			   } else {
				   $result = $this->SFA_Comman->executequery('CALL sp_int_import_update_promotionassignmentadvanced()', $param_array);
			   }
			
            
        }
       
       // echo "printing insert result <br>";print_r($result[0][0]);// echo "<br>";
    }
	private function insertPromotionAdvanceDatashipto($dataArray,$flag) {
        // echo "insertPromotionAdvanceData : <br>";
		
		{
			 
			//-------
			$param_array = array();
			$param_array[1] = ltrim($dataArray['uniqcombination'],'@');
			$param_array[2] = ltrim($dataArray['uniqcombination'],'@');
			$param_array[3] = $dataArray['amount'];
			$param_array[4] = 1;
			$param_array[5] = 9999999;
			$param_array[6] = 1;
				if ($flag == 'INSERT') {
				   $result = $this->SFA_Comman->executequery('CALL sp_int_import_promotionassignmentadvanced()', $param_array);
			   } else {
				   $result = $this->SFA_Comman->executequery('CALL sp_int_import_update_promotionassignmentadvanced()', $param_array);
			   }
			
            
        }
       
       // echo "printing insert result <br>";print_r($result[0][0]);// echo "<br>";
    }
    /*
     * Function Name    : insertPromotionCustomer
     * Params           :
     * Params           : $dataArray - values that need to udated for the table
     * Params           : $flag - Whether to insert or update to DB
     * Descripton       : Update Customermaster with promotion details.
     */
    private function insertPromotionCustomer($dataArray, $flag) {
        // echo "insertPromotionCustomer : <br>";
        $sql = "select customercode from customermaster where alternatecode='$dataArray->ShipTo'";
        $customercode = $this->getQueryResultRow($sql,'customercode');

       $param_array = array();
       $param_array[1] = $customercode;
       $param_array[2] = ltrim($dataArray->ShipTo,'0');
       if($flag == 'UPDATE') {
           $result = $this->SFA_Comman->executequery('CALL sp_int_import_update_promotioncustomer()', $param_array);
       }
       // echo "printing insert result <br>";print_r($result[0][0]);// echo "<br>";
    }
	private function insertPromotionCustomershipto($dataArray, $flag) {
        // echo "insertPromotionCustomer : <br>";
        $sql = "select customercode from customermaster where alternatecode='".$dataArray['shipto']."'";
        $customercode = $this->getQueryResultRow($sql,'customercode');

       $param_array = array();
       $param_array[1] = $customercode;
       $param_array[2] = $dataArray['shipto'];
       if($flag == 'UPDATE') {
           $result = $this->SFA_Comman->executequery('CALL sp_int_import_update_promotioncustomer()', $param_array);
       }
       // echo "printing insert result <br>";print_r($result[0][0]);// echo "<br>";
    }

    /*
     * Function Name    : insertProductGroupHeader
     * Params           :
     * Params           : $dataArray - values that need to udated for the table
     * Params           : $flag - Whether to insert or update to DB
     * Descripton       : insert the downloaded values to productgroupheader
     */
    private function insertProductGroupHeader($dataArray, $flag) {
       // echo "insertProductGroupHeader : <br>";

       $type = $_SESSION['productgroupheadertype'];
       $groupNumber = ltrim($dataArray['uniqcombination'],'@') ."000" .$type;

       $param_array = array();
       $param_array[1] = $groupNumber;
	   $param_array[2] =$dataArray['condrec'];
       $param_array[3] = $type;
       if ($flag == 'INSERT') {
           $param_array[4] = $this->loginName;
           $result = $this->SFA_Comman->executequery('CALL sp_int_import_productgroupheader()', $param_array);
       } else {
           $result = $this->SFA_Comman->executequery('CALL sp_int_import_update_productgroupheader()', $param_array);
       }
       // echo "printing insert result <br>";print_r($result[0][0]);// echo "<br>";
    } 

    /*
     * Function Name    : insertProductGroupDetail
     * Params           :
     * Params           : $dataArray - values that need to udated for the table
     * Params           : $flag - Whether to insert or update to DB
     * Descripton       : insert the downloaded values to productgroupdetail
     */
    private function insertProductGroupDetail($dataArray, $flag) {
        // echo "insertProductGroupDetail : <br>";
        $type = $_SESSION['productgroupdetailtype'];
        $groupNumber = ltrim($dataArray['uniqcombination'],'@') ."000" .$type;
		$dataArray['itemcode'];
		//---
		/*if($dataArray['itemcode']!='0')
		{
		 $sql = "SELECT actualitemcode FROM itemmaster WHERE alternatecode ='".$dataArray['itemcode']."'";
        $actualitemcode = $this->getQueryResultRow($sql,'actualitemcode');
		}else
		{
			$actualitemcode = '0';
		}*/			
        $param_array = array();
        $param_array[1] = $groupNumber;
        $param_array[2] = $dataArray['actutalitemcode'];
        if ($flag == 'INSERT') {
            $param_array[3] = $this->loginName;
            $result = $this->SFA_Comman->executequery('CALL sp_int_import_productgroupdetail()', $param_array);
        } else {
            $result = $this->SFA_Comman->executequery('CALL sp_int_import_update_productgroupdetail()', $param_array);
        }
        // echo "printing insert result <br>";print_r($result[0][0]);// echo "<br>";
    }

    /*
     * Function Name    : insertSalesData
     * Params           :
     * Params           : $dataArray - values that need to udated for the table
     * Params           : $flag - Whether to insert or update to DB
     * Descripton       : insert the downloaded values to salesman
     */
    private function insertSalesData($dataArray, $flag) {
        // echo "insertSalesData : <br>";
        //$_SESSION['RouteKey'] = "20"; // temp data
        //$_SESSION['VisitKey'] = "10"; // temp data
		
       $param_array = array();
       if($flag == 'INSERT') {
           $param_array[1] = str_replace("'", "`", $dataArray->DriverName1);
           $param_array[2] = "";
           $param_array[3] = $dataArray->DriverId1;
           $param_array[4] = $this->loginName;
           $result = $this->SFA_Comman->executequery('CALL sp_int_import_salesman()', $param_array);
       } else {
           // nothing to update check again.
       }
       // echo "printing insert result <br>";print_r($result[0][0]);// echo "<br>";
    }

    /*
     * private function Name    : insertStartingLoadDetail
     * Params           :
     * Params           : $dataArray - values that need to udated for the table
     * Params           : $flag - Whether to insert or update to DB
     * Descripton       : insert the downloaded values to startingloaddetail
     */
    private function insertStartingLoadDetail($dataArray, $flag) {
       // echo "insertStartingLoadDetail : <br>";
      // if(strpos($this->TourId ,"V") == FALSE)
			{
             $periodNo = $this->getRouteCodeFromTourId($this->TourId);
			
            //$loaddatadetailarr = $dataArray->LOAD_HDR2MAT->results;
            $loaddatadetailarr = $dataArray->DEL_HDR2ITM->results;
            foreach($loaddatadetailarr as $data) {
				
				$param_array = array();
				$param_array[1] = $this->TourId;
				$param_array[2] = $data->MatNo;
				$param_array[3] = $data->DactQty;
				$param_array[4] = $data->DactUom;
				$param_array[5] = $data->DelvNo;
		
		$this->SFA_Comman->executequery('CALL sp_int_import_stageloaddetail()', $param_array);
				
               /* $sql = "SELECT itemcode from startingloaddetail where itemcode=(select actualitemcode from itemmaster where alternatecode='$data->MatNo') and routecode=(select routecode from routemaster where memo1 = '$this->TourId') and loadperiodnumber='$periodNo' and ddate=(select date from tbl_touridstatus where tourid = '$this->TourId')";
                $flag = $this->getInsertOrUpdateFlag($sql);
                $sql = $this->insertStartLoadData($data, $flag);*/
				
            }
			
        }
    }

    /*
     * Function Name    : insertStartLoadData
     * Params           :
     * Params           : $dataArray - values that need to udated for the table
     * Params           : $flag - Whether to insert or update to DB
     * Descripton       : insert the downloaded values to startingloaddetail
     */
    private function insertStartLoadData($dataArray, $flag) {
		
		 $param_array = array();
		$param_array[1] = $this->TourId;
        $param_array[2] = $dataArray->MatNo;
        $param_array[3] = $dataArray->DactQty;
        $param_array[4] = $dataArray->DactUom;
		
		$this->SFA_Comman->executequery('CALL sp_int_import_stageloaddetail()', $param_array);
		
		/*
        // echo "insertStartLoadDatasss : <br>";
        $param_array = array();
        $param_array[1] = $dataArray->DactUom;
        $param_array[2] = $dataArray->MatNo;
        $param_array[3] = $this->TourId;
        $param_array[4] = $dataArray->DactQty;
		 $sql = "select salesmancode from routemaster where memo1='$this->TourId'";
            $salesmanCode = $this->getQueryResultRow($sql,'salesmancode');
			
        if($flag == 'INSERT') {
             $sql = "select date from tbl_touridstatus where tourid = '$this->TourId'";
             $dateVal = $this->getQueryResultRow($sql,'date');
            $sql = "select salesmancode from routemaster where memo1='$this->TourId'";
            $salesmanCode = $this->getQueryResultRow($sql,'salesmancode');
			
            $param_array[5] = $salesmanCode;
            $param_array[6] = $this->getRouteCodeFromTourId($this->TourId);
            $param_array[7] = $dateVal;
            $param_array[8] = $this->loginName;
            $result = $this->SFA_Comman->executequery('CALL sp_int_import_startingloaddetail()', $param_array);
        } else {
            $result = $this->SFA_Comman->executequery('CALL sp_int_import_update_startingloaddetail()', $param_array);
        }
        // echo "printing insert result <br>";print_r($result[0][0]);// echo "<br>";
		*/
    }
 
    private function insertPricingGrpDetailData($dataArray, $flag) {
        // echo "insertPricingGrpDetailData : <br>";

       $param_array = array();
       $param_array[1] = $dataArray->CustGrp2;
       $param_array[2] = $dataArray->ArtNo;
       $param_array[3] = $dataArray->CustPrice;
       $param_array[4] = $dataArray->CondRecNo;
       $param_array[5] = $dataArray->Uom;
       if($flag == 'INSERT') {
           $param_array[6] = $this->loginName;
           $result = $this->SFA_Comman->executequery('CALL sp_int_import_pricingdetailgrp()', $param_array);
       } else {
           $result = $this->SFA_Comman->executequery('CALL sp_int_import_update_pricingdetailgrp()', $param_array);
       }
       // echo "printing insert result <br>";print_r($result[0][0]);// echo "<br>";
    }
    /*
     * Function Name    : insertArticlePricingDetailData
     * Params           :
     * Params           : $dataArray - values that need to udated for the table
     * Params           : $flag - Whether to insert or update to DB
     * Descripton       : insert the downloaded values to pricingdetail1
     */
    private function insertArticlePricingDetailData($dataArray, $flag) {
        // echo "insertArticlePricingDetailData : <br>";

       $param_array = array();
       $param_array[1] = $dataArray->CustNo;
       $param_array[2] = $dataArray->ArtNo;
       $param_array[3] = $dataArray->CustPrice;
       $param_array[4] = $dataArray->CondRecNo;
       $param_array[5] = $dataArray->Uom;
       if($flag == 'INSERT') {
           $param_array[6] = $this->loginName;
           $result = $this->SFA_Comman->executequery('CALL sp_int_import_pricingdetail()', $param_array);
       } else {
           $result = $this->SFA_Comman->executequery('CALL sp_int_import_update_pricingdetail()', $param_array);
       }
       // echo "printing insert result <br>";print_r($result[0][0]);// echo "<br>";
    }

    private function insertCustomerGrpPricingData($dataArray, $flag) {
        // echo "insertCustomerGrpPricingData : <br>";
        $startDate = $this->convertJsonDateToPHP($dataArray->ValidFrom);
        $endDate = $this->convertJsonDateToPHP($dataArray->ValidTo);
        $param_array = array();
        $param_array[1] = $dataArray->CustGrp2;
        $param_array[2] = $startDate;
        $param_array[3] = $endDate;
        if($flag == 'INSERT') {
           $param_array[4] = $dataArray->ArtNo;
           $param_array[5] = $this->loginName;
           $result = $this->SFA_Comman->executequery('CALL sp_int_import_customerpricinggrp()', $param_array);
       } else {
           $result = $this->SFA_Comman->executequery('CALL sp_int_import_update_customerpricinggrp()', $param_array);
       }
       // echo "printing insert result <br>";print_r($result[0][0]);// echo "<br>";
    }
    /*
     * Function Name    : insertCustomerPricingData
     * Params           :
     * Params           : $dataArray - values that need to udated for the table
     * Params           : $flag - Whether to insert or update to DB
     * Descripton       : insert the downloaded values to customerpricing1
     */
    private function insertCustomerPricingData($dataArray, $flag) {
        // echo "insertCustomerPricingData : <br>";

        $param_array = array();
        $param_array[1] = $dataArray->CustNo;
        if($flag == 'INSERT') {
            $param_array[2] = $this->loginName;
            $result = $this->SFA_Comman->executequery('CALL sp_int_import_customerpricing()', $param_array);
        } else {
            $result = $this->SFA_Comman->executequery('CALL sp_int_import_update_customerpricing()', $param_array);
        }
        // echo "printing insert result <br>";print_r($result[0][0]);// echo "<br>";
    }

    /*
     * Function Name    : insertCustPriceKey
     * Params           :
     * Params           : $dataArray - values that need to udated for the table
     * Params           : $flag - Whether to insert or update to DB
     * Descripton       : insert the downloaded values to customerpricing1
     */
    private function insertCustPriceKey($dataArray, $flag) {
        // echo "insertCustPriceKey : <br>";

        $param_array = array();
        $param_array[1] = $dataArray->CustNo;
        if($flag == 'UPDATE') {
            $result = $this->SFA_Comman->executequery('CALL sp_int_import_update_pricingkey()', $param_array);
        }
        // echo "printing insert result <br>";print_r($result[0][0]);// echo "<br>";
    }
    private function insertCustGrpPriceKey($dataArray, $flag) {
        // echo "insertCustPriceKey : <br>";

        $param_array = array();
        $param_array[1] = $dataArray->CustGrp2;
        if($flag == 'UPDATE') {
            $result = $this->SFA_Comman->executequery('CALL sp_int_import_update_customerpricingkey()', $param_array);
        }
        // echo "printing insert result <br>";print_r($result[0][0]);// echo "<br>";
    }

    /*
     * Function Name    : insertRouteSequenceData
     * Params           :
     * Params           : $dataArray - values that need to udated for the table
     * Params           : $flag - Whether to insert or update to DB
     * Descripton       : insert the downloaded values to routesequence
     */
    private function insertRouteSequenceData($dataArray, $flag) {
        // echo "insertRouteSequenceData : <br>";
        $param_array = array();
        $param_array[1] = $dataArray->CustNo;
        $param_array[2] = $this->TourId;
		$param_array[3] = $dataArray->VisitId;
		$param_array[4] = $dataArray->AseqId;
		
		$result = $this->SFA_Comman->executequery('CALL sp_int_import_stagecustseq()', $param_array);
        
		  /*$param_array = array();
        $param_array[1] = $dataArray->CustNo;
        $param_array[2] = $this->TourId;
		$param_array[3] = $dataArray->VisitId;
		
		$result = $this->SFA_Comman->executequery('CALL sp_int_import_stagecustseq()', $param_array);
        //if($flag == 'INSERT') 
		{
            $param_array[4] = $this->loginName;
            $result = $this->SFA_Comman->executequery('CALL sp_int_import_routesequence()', $param_array);
        } */
		/*else {
            $result = $this->SFA_Comman->executequery('CALL sp_int_import_update_routesequence()', $param_array);
        }*/
        // echo "printing insert result <br>";print_r($result[0][0]);// echo "<br>";
    }
    private function addRouteSequenceData($dataArray,$flag,$driver) {
        // echo "addRouteSequenceData : <br>";
        $param_array = array();
        $param_array[1] = $dataArray->CustNo;
        $param_array[2] = $this->TourId;
		$param_array[3] = $dataArray->VisitId;
        //if($flag == 'INSERT') 
		{
            $param_array[4] = $this->loginName;
            $result = $this->SFA_Comman->executequery('CALL sp_int_import_customerroutesequence()', $param_array);
        } 
        
    }
    /*
     * Function Name    : updateRouteItemMappingTable
     * Params           :
     * Params           : $dataArray - values that need to udated for the table
     * Descripton       : Insert routeitemmapping after updating itemmaster itemcode.
     */
    public function updateRouteItemMappingTable($dataArray) {
        // echo "updateRouteItemMappingTable : <br>";
        $param_array = array();
        $param_array[1] = $dataArray->ArtNo;
        $param_array[2] = $this->loginName;
		$param_array[3] = $dataArray->Division;
        $result = $this->SFA_Comman->executequery('CALL sp_int_import_routeitemmapping()', $param_array);
        // echo "printing insert result <br>";print_r($result[0][0]);// echo "<br>";
    }
	
	public function getSAPDataForBankMaster(){
        // echo "getSAPDataForBankMaster <br>";
        $tableAddressURL = $this->bankMasterURL .'?&$format=json';
        $tableObject = $this->getSAPDBData($tableAddressURL, $this->uidSAP, $this->pwdSAP);
        $tabledata = $tableObject->d->results;
        foreach ($tabledata as $dataArray) {
            $sql = "SELECT bankcode FROM bankmaster WHERE alternatecode = '$dataArray->BankCode'";
            $flag = $this->getInsertOrUpdateFlag($sql);
            $this->insertBankMasterData($dataArray,$flag);
        }
    }
	
	private function insertBankMasterData($dataArray,$flag) {
        // echo "insertBankMasterData : <br>";
        $param_array = array();
        $param_array[1] = $dataArray->BankCode;
        $param_array[2] = $dataArray->BankName;
		$param_array[3] = $dataArray->BankKey;
        if($flag == 'INSERT') {
            
            $result = $this->SFA_Comman->executequery('CALL sp_int_import_bankmaster()', $param_array);
        } else {
            $result = $this->SFA_Comman->executequery('CALL sp_int_import_update_bankmaster()', $param_array);
        }
    } 
	private function getSAPDataForOpenInvoices($driver) {
       // echo "getSAPDataForOpenInvoices <br>";
        $filter = '?$filter=IDriverNo%20eq%20%27' .$driver .'%27';
        $expand = '&$format=json';
        $filter = $filter .$expand;
       $tableAddressURL = $this->createURLForSAP('OpenInvoices',$filter);
		
        $tableObject = $this->getSAPDBData($tableAddressURL, $this->uidSAP, $this->pwdSAP);
        $tabledata = $tableObject->d->results;
		
        foreach ($tabledata as $dataArray) {
			
			//echo $driver;
			$param_array = array();
			$param_array[1] = $driver;
			$param_array[2] = $dataArray->Customer;
			$param_array[3] = $this->convertJsonDateToPHP($dataArray->DocDate);			
			$param_array[4] = $this->convertJsonDateToPHP($dataArray->DueDate);
			$param_array[5] = trim($dataArray->DocType);
			$param_array[6] = trim($dataArray->DocNo);
			$param_array[7] = trim($dataArray->DocNo2);
			$param_array[8] = $dataArray->Amount;
			$param_array[9] = $dataArray->DebitCreditInd;
			$param_array[10] = $dataArray->DocNumber;

			$result = $this->SFA_Comman->executequery('CALL sp_int_import_statgeopenitems()', $param_array);
			
           /* $sql = "SELECT invoicenumber FROM customerinvoice WHERE invoicenumber = '$dataArray->RefDocNo'";
            $flag = $this->getInsertOrUpdateFlag($sql);
            $this->insertCustomerOpenInvoices($dataArray,$flag,$driver);*/
        }
    }
	/*
     * Function Name    : insertCustomerOpenInvoices
     * Params           : $driver - driver id to get routecode and salesmancode
     * Params           : $dataArray - values that need to udated for the table
     * Params           : $flag - Whether to insert or update to DB
     * Descripton       : insert the downloaded values to customerinvoice
     */
    private function insertCustomerOpenInvoices($dataArray,$flag,$driver) {
        // echo "insertCustomerOpenInvoices : <br>";
        $param_array = array();
        $param_array[1] = $dataArray->AmtDoccur;
        $param_array[2] = $dataArray->RefDoc;
        $param_array[3] = ltrim($dataArray->Customer,'0');
        if($flag == 'INSERT') {
            $sap2hht = $dataArray->OPEN_INV_SAP2HHT->results;
            $hhtDocNo = $sap2hht[0]->RefDocNo;

            $param_array[4] = $driver;
            $param_array[5] = $hhtDocNo;
            $param_array[6] = $dataArray->DocNo;
            $param_array[7] = $this->loginName;
            $result = $this->SFA_Comman->executequery('CALL sp_int_import_customerinvoice()', $param_array);
        } else {
            $result = $this->SFA_Comman->executequery('CALL sp_int_import_update_customerinvoice()', $param_array);
        }
    }

    //*********************************************Cear Tables in DB*************************************************
    /*
     * Function Name    : clearMasterTablesFromDB
     * Params           :
     * Descripton       : Empty the tables from DB
     */
    private function clearMasterTablesFromDB() {
        // echo "clearMasterTablesFromDB : <br>";
        $result = $this->SFA_Comman->executequery('CALL sp_int_import_cleartablesfromDB()', "");
        // echo "printing insert result <br>";print_r($result[0][0]);// echo "<br>";
    }
    
    //*********************************************execute the SQL Query*************************************************
    /*
     * Function Name    : executeSQLQuery
     * Params           :
     * Params           : $sql - the MySQL statement to be executed
     * Descripton       : Will query to the DB
     */
    private function executeSQLQuery($sql) {
        // echo "executeSQLQuery : <br>";
        $result = "";
        if($sql != "") {
            $sql = str_replace("'", "\'", $sql);
            $param_array = array();
            $param_array[1] = $sql;
            $result = $this->SFA_Comman->executequery('CALL sp_int_import_executesqlquery()', $param_array);
        }
        //$this->sendMsg("executeSQLQuery : " .$sql ."<br>");
        return $result;
    }

    //*********************************************Extract routeCode from Tour Id*************************************************
    /*
     * Function Name    : getRouteCodeFromTourId
     * Params           : $tourId - Specifies the tour id
     * Descripton       : remove the non numeric characters and convert to Int.
     */
    private function getRouteCodeFromTourId($tourId) {
        // echo "getRouteCodeFromTourId : <br>";
        $routeCode = "";
        $routeCode = (int)preg_replace('(\D+)', '', $tourId);
        return $routeCode;
    }
    
    /*
     * Function Name    : getInsertOrUpdateFlag
     * Params           : $sql - Query to be executed
     * Descripton       : execute the query to find whether data to be inserted or updated
     */
    private function getInsertOrUpdateFlag($sql) {
       // echo "getInsertOrUpdateFlag : <br>";
       $flag = "";
       $count = 0;
       $result = $this->executeSQLQuery($sql);
       if($result != "") {
           $count = sizeof($result[0][0]);
           if($count == 0) {
                // insert new entry
               $flag = 'INSERT';
           } else {
                // updaate data here
                $flag = 'UPDATE';
           }
        }
       return $flag;
    }
    /*
     * Function Name    : getQueryResultRow
     * Params           : $value - result value to be found
     * Params           : $sql - Query to be executed
     * Descripton       : execute the query to find whether data to be inserted or updated
     */
     private function getQueryResultRow($sql,$value) {
        // echo "getQueryResultRow : <br>";
        $val = "";
        $result = $this->executeSQLQuery($sql);
        if($result != "") {
            $val = $result[0][0][$value];
        }
        //$this->sendMsg("getQueryResultRow : " .$val ."<br>");
        return $val;
    }

    private function convertJsonDateToPHP($jSondate) {
				//$baseUrl= Zend_Controller_Front::getInstance()->getBaseUrl();
				//$path   = str_replace('//','/',$_SERVER['DOCUMENT_ROOT'].'/upload/');
				//$path1 	= str_replace('//','/',$baseUrl.'/');		
		
				//$filename = $path.'log_test/dateissue.txt';
				//if (!file_exists($filename)) {
				//fopen($filename,'w');
				//	}
				//chmod($filename,0777);
				//$current = file_get_contents($filename);
				//$current .= "\n".$jSondate."\n";		
				//preg_match( "#/Date\((\d{10})\d{3}(.*?)\)/#", $jSondate, $match );
				//$phpDate = date('Y-m-d', $match[1]);
				$malevals = array('Date', '/', '(',  ')');
				$malestr = str_replace($malevals, '', $jSondate);
				$phpDate = date('Y-m-d', preg_replace('/[^\d]/','', $malestr)/1000);  
				//$current .= "\n".$phpDate."\n";
				//file_put_contents($filename, $current);
				//$current = file_get_contents($filename);
        return $phpDate;
    }
    private function updateExitStatusInTourTable($tourId) {
        if($this->routeExistStatus != "") {
             $sql = "UPDATE tbl_touridstatus SET status = ".$this->routeExistStatus." WHERE tourid = '".$tourId."'";
            $this->executeSQLQuery($sql);
            $this->routeExistStatus = "";
        }
    }

    function sendMsg($msg) {
      //header('Content-Type: text/event-stream');
      //header('Cache-Control: no-cache'); // recommended to prevent caching of event data.
      // echo str_repeat(" ",1024*1024*4);
      //$msg =  "data: " .$msg ."<br><br>";
      //$msg =  "<script type='text/javascript'>document.getElementById('result').innerHTML += '$msg<br>'</script>";
      // echo $msg;
      //$_POST['result'] = $msg;
      //header('Location: /ver11/application/modules/datamanagement/views/scripts/index/downsync.phtml?result='.$msg);
      //ob_flush();
      //flush();
      //sleep(1);
    }
	
	//------------------------Divsion
	/*public function getSAPDataForArticleItem($divList) {
        echo "getSAPDataForArticleItem <br>";
        if($divList == null) {
            $divList = array();
            $count = 0;
            $sql = "select division from divisionlist";
            $queryResult = $this->executeSQLQuery($sql);
            foreach($queryResult[0] as $row) {
                $divList[$count++] = $row['division'];
            }
        }
        foreach($divList as $division) {
            $filter = '?$filter=IDivision%20eq%20%27' .$division .'%27';
            $expand = '&$expand=ART_HDR2SALESORG,ART_HDR2ALTUOM,ART_HDR2PRICE&$format=json';
            $filter = $filter .$expand;
            $tableAddressURL = $this->createURLForSAP('ES_ART_HDR',$filter);
            $tableObject = $this->getSAPDBData($tableAddressURL, $this->uidSAP, $this->pwdSAP);
            $tabledata = $tableObject->d->results;
            foreach ($tabledata as $dataArray) {
                $this->updateDBData($dataArray, 'itemmaster');
            }
             // update division Status
            $sql = "UPDATE divisionlist SET status = TRUE,date=curdate() WHERE division = '$division'";
            $this->executeSQLQuery($sql);
        }
    }*/
	
	//-----------Promotion header
	public function updatePromotionDBData($dataArray) {
		
        //echo "updatePromotionDBData <br>" ;
        
        $CondRecDiv = $dataArray->CondRec .$dataArray->Division;

         $sql = "SELECT plannumber from promoplanheader where plannumber = ".$dataArray->PromoPlnNo." and plantypecode = 6";
        $flag = $this->getInsertOrUpdateFlag($sql);
        $sql = $this->insertPromoPlanHeaderData($dataArray, $flag);
		
        $sql = "SELECT plannumber from promoplandetail where plannumber = ".$dataArray->PromoPlnNo." and promotiontypecode = 6";
        $flag = $this->getInsertOrUpdateFlag($sql);
        $sql = $this->insertPromoPlanDetail($dataArray, $flag);

        $sql = "SELECT promotionkey from promokeyheader where promotionkey = '".ltrim($dataArray->ShipTo,'0')."'";
        $flag = $this->getInsertOrUpdateFlag($sql);
        $sql = $this->insertPromoKeyHeaderData($dataArray, $flag);

        $sql = "SELECT plannumber from promokeydetail where plannumber = ".$dataArray->PromoPlnNo." ";
        $flag = $this->getInsertOrUpdateFlag($sql);
        $sql = $this->insertPromoKeyDetail($dataArray, $flag);

         //$sql = "SELECT assignmentnumber from promotionassignmentadvanced where range_id = ".$dataArray->PromoPlnNo.$dataArray->ItmNo."";
       // $flag = $this->getInsertOrUpdateFlag($sql);
        $sql = $this->insertPromotionAdvanceData($dataArray);

        $sql = "SELECT alternatecode from customermaster where alternatecode = '$dataArray->ShipTo'";
        $flag = $this->getInsertOrUpdateFlag($sql);
        //$sql = $this->insertPromotionCustomer($dataArray, $flag);
        
      /*  $_SESSION['productgroupheadertype'] = 1;
        $this->selectInsertProductGroupHeader($dataArray);
        $_SESSION['productgroupheadertype'] = 2;
        $this->selectInsertProductGroupHeader($dataArray);

        $_SESSION['productgroupdetailtype'] = 1;
        $this->selectInsertProductGroupDetail($dataArray);
        $_SESSION['productgroupdetailtype'] = 2;
        $this->selectInsertProductGroupDetail($dataArray);*/
    }
	
	   //Ship to article
	
		public function updatePromotionshiptoDBData($dataArray) {	
      // echo "updatePromotionDBData <br>" ;
			
		
		$findme   = '@';
		//$pos = strpos($dataArray['uniqcombination'], $findme);
		
       //$CondRecDiv = $dataArray .$dataArray->division;

         $sql = "SELECT plannumber from promoplanheader where plannumber = '".ltrim($dataArray['uniqcombination'],'@')."' ";
        $flag = $this->getInsertOrUpdateFlag($sql);
        $sql = $this->insertPromoPlanHeadershiptoData($dataArray, $flag);
		
        $sql = "SELECT plannumber from promoplandetail where plannumber =".ltrim($dataArray['uniqcombination'],'@')."";
        $flag = $this->getInsertOrUpdateFlag($sql);
        $sql = $this->insertPromoPlanDetailshipto($dataArray, $flag);
		
        $sql = "SELECT promotionkey from promokeyheader where promotionkey = (select customercode from customermaster where alternatecode= '".$dataArray['shipto']."')";
        $flag = $this->getInsertOrUpdateFlag($sql);
        $sql = $this->insertPromoKeyHeaderDatashipto($dataArray, $flag);
		
       $sql = "SELECT plannumber from promokeydetail where primary_key =".ltrim($dataArray['uniqcombination'],'@')."";
        $flag = $this->getInsertOrUpdateFlag($sql);
        $sql = $this->insertPromoKeyDetailshipto($dataArray, $flag);
		
         $sql = "SELECT assignmentnumber from promotionassignmentadvanced where ".ltrim($dataArray['uniqcombination'],'@')."";
        $flag = $this->getInsertOrUpdateFlag($sql);
        $sql = $this->insertPromotionAdvanceDatashipto($dataArray);
		
       $sql = "SELECT alternatecode from customermaster where alternatecode = '".$dataArray['shipto']."'";
        $flag = $this->getInsertOrUpdateFlag($sql);
        $sql = $this->insertPromotionCustomershipto($dataArray, $flag);
		
         if(strpos($dataArray['uniqcombination'], $findme) === false)
			{	
					$_SESSION['productgroupheadertype'] = 1;
					$this->selectInsertProductGroupHeader($dataArray);
					
					$_SESSION['productgroupheadertype'] = 2;
					$this->selectInsertProductGroupHeader($dataArray);
					
					$_SESSION['productgroupdetailtype'] = 1;
					$this->selectInsertProductGroupDetail($dataArray);
					
					$_SESSION['productgroupdetailtype'] = 2;
					$this->selectInsertProductGroupDetail($dataArray);
			}
    }
}
?>