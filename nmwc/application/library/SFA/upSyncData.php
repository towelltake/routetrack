<?php
/*
 * FileName     : upSyncData.php
 * Owner        : v.nair@mirnah.com
 * Created      : 15/01/2015
 * Description  : Getting data tables from DB and Updating SAP 
 */
class SFA_upSyncData {

    private $URL = 'http://172.16.1.37:8000/sap/opu/odata/MIRNAH/UPLOAD_SRV/$batch';   
	private $uidSAP = "DRIVERRFC";
    private $pwdSAP = "ABCD1234@";

    private $routeXml = array();
    private $mainXmlData  = array();
    private $headerArray = array();
    private $detailArray = array();
    private $finalXml = "";

    private $TourId = "";
    private $RouteCode = "";
    private $RouteKey = "";
    private $status = FALSE;

    public function __construct() {
       // echo "testing construct : <br>";
        set_time_limit(0);
        $this->setStatus(FALSE);
    }
    private function setStatus($status) {
        $this->status = $status;
    }
    public function getStatus() {
        return $this->status;
    }

    public function exportData($tourID) {
        //echo "exportData<br>";
        $this->setGlobalValues($tourID);
        $this->checkRoutekey(); 
    }

    private function setGlobalValues($tourID) {
      //  echo "setGlobalValues<br>";
        $this->SFA_Comman = new SFA_Comman(); 
         $sql = "select routecode from startendday WHERE tourid = '$tourID'";
        $this->RouteCode = $this->getQueryResultRow($sql,'routecode');
        $this->TourId = $tourID;
        $this->RouteKey = "";
    }

    private function checkRoutekey() {
       // echo "checkRoutekey<br>";
        if ($this->RouteKey == '' || $this->RouteKey == 0 || $this->RouteKey ==  null) {
            $this->getafterRouteKey();
        } else {
            $this->getRouteData();
        }
    }

    private function getafterRouteKey() {
       // echo "getafterRouteKey<br>";
        $param_array = array();
        $param_array[1] = $this->TourId;
        $queryResult = $this->SFA_Comman->executequery('CALL sp_int_export_getroutekey()',$param_array);
        foreach($queryResult[0] as $row) {
            $routeKey = $row['routekey'];
            if ($routeKey > 0) {
                $this->RouteKey = $routeKey;
                $this->getRouteData();
            }
        }
    }

    private function getRouteData() {
       // echo "getRouteData<br>";
        $sql = "select routeclosed from startendday where routecode = '$this->RouteCode' and routekey = '$this->RouteKey'";
        $routeClosed = $this->getQueryResultRow($sql,'routeclosed');
			$param_array = array();			
            $param_array[1] = $this->RouteKey;
			$this->SFA_Comman->executequery('CALL sp_int_export_generatesapseq()',$param_array);
        if($routeClosed == 1) {
            $param_array = array();
            $param_array[1] = $this->RouteCode;
            $param_array[2] = $this->RouteKey;
            $queryResult = $this->SFA_Comman->executequery('CALL sp_int_export_getroutedata()',$param_array);
            foreach($queryResult[0] as $data) {
                $this->createRouteXml($data);
            }
           
        }
    }

    /*
     * Function Name    : executeSQLQuery
     * Params           :
     * Params           : $sql - the MySQL statement to be executed
     * Descripton       : Will query to the DB and display error if occured
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
        return $result;
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
        return $val;
    }

    private function createRouteXml($data) {
      //  echo "createRouteXml<br>";
        $tourId = $this->TourId;
        $routeCode = $this->RouteCode;
        
        $startDate = str_ireplace(" 00:00:00", "", $data[routestartdate]);
        $PstartDate =  str_ireplace(":","-",$startDate) ."T" .$data[routestarttime];
        $endDate = str_ireplace(" 00:00:00", "", $data[routeenddate]);
        $PendDate =  str_ireplace(":","-",$endDate) ."T" .$data[routeendtime];
        // current date and time to be updated.
        $dateTime = date('Y-m-d') ."T" .date('h:i:s');

        $sql = "select alternateroutecode from routemaster where routecode = '$routeCode'";
        $vehicleId = $this->getQueryResultRow($sql,'alternateroutecode');

        $sql = "select alternatesalesmancode from salesman where salesmancode = (select salesmancode from routemaster where routecode = '$routeCode')";
        $salesmanCode = $this->getQueryResultRow($sql,'alternatesalesmancode');

        array_push($this->mainXmlData, "--changeset_005056A5-09B1-1ED1-BF82-409B26A80301\n");
        array_push($this->mainXmlData, "Content-Type: application/http\n");
        array_push($this->mainXmlData, "Content-Transfer-Encoding: binary\n");
        array_push($this->mainXmlData, "\n");
        array_push($this->mainXmlData, "POST I_TOUR_HDR HTTP/1.1\n");
        array_push($this->mainXmlData, "Content-Type: application/atom+xml\n");
        array_push($this->mainXmlData, "Content-Length: 200210\n\n");
        array_push($this->mainXmlData, "<atom:entry xmlns:atom='http://www.w3.org/2005/Atom' xmlns:d='http://schemas.microsoft.com/ado/2007/08/dataservices' xmlns:m='http://schemas.microsoft.com/ado/2007/08/dataservices/metadata'>\n");
        array_push($this->mainXmlData, "<atom:title>User Menu</atom:title>\n");
        array_push($this->mainXmlData, "<atom:id>urn:uuid:88df28a0-1c04-42db-a328-d0ac9154e161</atom:id>\n");
        array_push($this->mainXmlData, "<atom:updated>$dateTime-08:00</atom:updated>\n");
        array_push($this->mainXmlData, "<atom:author>\n<atom:name>$salesmanCode</atom:name>\n</atom:author>\n");
        array_push($this->mainXmlData, "<atom:content type='application/xml'>\n");
        array_push($this->mainXmlData, "<m:properties>\n<d:TourId>$tourId</d:TourId>\n<d:ShipType>$data[memo2]</d:ShipType>\n<d:DocType>10</d:DocType>\n<d:DriverId1>$salesmanCode</d:DriverId1>\n<d:DriverId2></d:DriverId2>\n<d:Carrier></d:Carrier>\n<d:VehicleId>$vehicleId</d:VehicleId>\n<d:TrailerId></d:TrailerId>\n<d:Route>$data[templatename]</d:Route>\n<d:RouteDesc></d:RouteDesc>\n<d:Distance>0</d:Distance>\n<d:BegMile>0</d:BegMile>\n<d:EndMile>0</d:EndMile>\n<d:PstartDate>$PstartDate</d:PstartDate>\n<d:PstartTime>PT12H00M00S</d:PstartTime>\n<d:PendDate>$PendDate</d:PendDate>\n<d:PendTime>PT12H00M00S</d:PendTime>\n<d:AstartDate>$PstartDate</d:AstartDate>\n<d:AstartTime>PT12H00M00S</d:AstartTime>\n<d:AendDate>$PendDate</d:AendDate>\n<d:AendTime>PT12H00M00S</d:AendTime>\n<d:TourStat></d:TourStat>\n<d:Extfld1></d:Extfld1>\n<d:Extfld2></d:Extfld2>\n<d:Extfld3></d:Extfld3>\n<d:Extfld4></d:Extfld4>\n<d:Extfld5></d:Extfld5>\n<d:Extfld6></d:Extfld6>\n<d:MeAction></d:MeAction>\n<d:DriverName1></d:DriverName1>\n<d:Exti1></d:Exti1>\n<d:TxjcdSf></d:TxjcdSf>\n<d:ReloadStatus></d:ReloadStatus>\n<d:SupplierGln></d:SupplierGln>\n</m:properties>\n");
        //  test data array_push($this->mainXmlData, "<m:properties>\n<d:TourId>$tourId</d:TourId>\n<d:ShipType>$data[memo2]</d:ShipType>\n<d:DocType>0</d:DocType>\n<d:DriverId1>$salesmanCode</d:DriverId1>\n<d:DriverId2></d:DriverId2>\n<d:Carrier></d:Carrier>\n<d:VehicleId>$vehicleId</d:VehicleId>\n<d:TrailerId></d:TrailerId>\n<d:Route>CPB100</d:Route>\n<d:RouteDesc>$data[routename]</d:RouteDesc>\n<d:Distance>0</d:Distance>\n<d:BegMile>0</d:BegMile>\n<d:EndMile>0</d:EndMile>\n<d:PstartDate>$PstartDate</d:PstartDate>\n<d:PstartTime>PT12H00M00S</d:PstartTime>\n<d:PendDate>$PendDate</d:PendDate>\n<d:PendTime>PT12H00M00S</d:PendTime>\n<d:AstartDate>$PstartDate</d:AstartDate>\n<d:AstartTime>PT12H00M00S</d:AstartTime>\n<d:AendDate>$PendDate</d:AendDate>\n<d:AendTime>PT12H00M00S</d:AendTime>\n<d:TourStat></d:TourStat>\n<d:Extfld1></d:Extfld1>\n<d:Extfld2></d:Extfld2>\n<d:Extfld3></d:Extfld3>\n<d:Extfld4></d:Extfld4>\n<d:Extfld5></d:Extfld5>\n<d:Extfld6></d:Extfld6>\n<d:MeAction></d:MeAction>\n<d:DriverName1></d:DriverName1>\n<d:Exti1></d:Exti1>\n<d:TxjcdSf></d:TxjcdSf>\n<d:ReloadStatus></d:ReloadStatus>\n<d:SupplierGln></d:SupplierGln>\n</m:properties>\n");
        array_push($this->mainXmlData, "</atom:content>\n");

        //$this->routeXml = "--changeset_005056A5-09B1-1ED1-BF82-409B26A80301--\n\n" .join("", $this->mainXmlData);
        $this->routeXml = join("", $this->mainXmlData);

        if(strpos($tourId , "V") == FALSE) {

			//-------------
			$param_array = array();
        $param_array[1] = $this->RouteKey;
        $param_array[2] = $this->RouteCode;
        $queryResult = $this->SFA_Comman->executequery('CALL sp_int_export_getdelheaderdata()',$param_array);
        $this->headerArray = $queryResult[0];
				if(count($queryResult[0])>0)
				{
					$this->getDelHeaderData();
				}
				else
				{
					$param_array = array();
					$param_array[1] = $this->RouteKey;
					$queryResult = $this->SFA_Comman->executequery('CALL sp_int_export_getcashdetaildata()',$param_array);
					$this->detailArray = $queryResult[0];
							if(count($queryResult[0])>0)
							{
								
								//array_push($this->mainXmlData, $this->routeXml."");
								array_push($this->mainXmlData, "<atom:link rel='http://schemas.microsoft.com/ado/2007/08/dataservices/related/TOUR2COLL_PAYM' type='application/atom+xml;type=feed' title='SOItems'>\n");
								array_push($this->mainXmlData, "<m:inline>\n");
								array_push($this->mainXmlData, "<atom:feed>\n");
								$this->getCashDetailData();
								
							}else
							{
								$curdate=date('Y-m-d') ."T" .date('h:i:s');
								$time =date("H:i:s");
								$curtime = explode(':', $time);
								$timeValue = "PT".$curtime[0]."H".$curtime[1]."M".$curtime[2]."S";
								   //array_push($this->mainXmlData, $this->routeXml."");		
		 array_push($this->mainXmlData, "<atom:link rel='http://schemas.microsoft.com/ado/2007/08/dataservices/related/TOUR_COCI2HDR'     type='application/atom+xml;type=feed' title='TOUR_COCI2HDR'>\n<m:inline>\n<atom:feed>\n<atom:entry>\n<atom:content type='application/xml'>\n<m:properties>\n<d:TourId>$tourId</d:TourId>\n<d:CheckId>02</d:CheckId>\n<d:CheckDirect>2</d:CheckDirect>\n<d:Checker>Mirnah</d:Checker>\n<d:CheckDate>$curdate</d:CheckDate>\n<d:CheckTime>$timeValue</d:CheckTime>\n<d:CheckType>A</d:CheckType>\n<d:Extfld1></d:Extfld1>\n<d:Extfld2></d:Extfld2>\n<d:Extfld3></d:Extfld3>\n<d:Extfld4></d:Extfld4>\n<d:Extfld5></d:Extfld5>\n<d:Extfld6></d:Extfld6>\n<d:MeAction></d:MeAction>\n<d:Plant>1000</d:Plant>\n</m:properties>\n</atom:content>\n");
								 array_push($this->mainXmlData, "<atom:link rel='http://schemas.microsoft.com/ado/2007/08/dataservices/related/COCI_HDR2ITM' type='application/atom+xml;type=feed' title='COCI_HDR2ITM'>\n");
								 array_push($this->mainXmlData, "<m:inline>\n");
								 array_push($this->mainXmlData, "<atom:feed>\n");
								 $this->getCheckinData();
								
								
							}
					// $this->getCheckinData();
					
				}
			//--------------
			
            //$this->getDelHeaderData();
        } else {
            $this->getHeaderData();
        }
    }

    private function getHeaderData() {
       // echo "getHeaderData<br>";
        $param_array = array();
        $param_array[1] = $this->RouteKey;
        $queryResult = $this->SFA_Comman->executequery('CALL sp_int_export_getheaderdata()',$param_array);
        $this->headerArray = $queryResult[0];
        $this->getHeaderDataXML();
    }

    private function getHeaderDataXML() {
      //  echo "getHeaderDataXML<br>";
        $headerData = array_shift($this->headerArray);
        $tourId = $this->TourId;

        $myArray = explode(':', $headerData[transactiontime]);
        $timeValue = "PT".$myArray[0]."H".$myArray[1]."M".$myArray[2]."S";

        array_push($this->mainXmlData, "<atom:link rel='http://schemas.microsoft.com/ado/2007/08/dataservices/related/TOUR_ORDER2HDR' type='application/atom+xml;type=feed' title='TOUR_ORDER2HDR'>\n");
        array_push($this->mainXmlData, "<m:inline>\n");
        array_push($this->mainXmlData, "<atom:feed>\n");
        array_push($this->mainXmlData, "<atom:entry>\n");
        array_push($this->mainXmlData, "<atom:content type='application/xml'>\n");
        //array_push($this->mainXmlData, "<m:properties>\n<d:OrdNo>$headerData[3]</d:OrdNo>\n<d:TourId>$tourId</d:TourId>\n<d:VisitId>$headerData[9]</d:VisitId>\n<d:ActId>$headerData[2]</d:ActId>\n<d:HhCreate>X</d:HhCreate>\n<d:CustNo>$headerData[8]</d:CustNo>\n<d:CustNoR>$headerData[8]</d:CustNoR>\n<d:DelvDate>$headerData[7]T00:00:00</d:DelvDate>\n<d:PoNo></d:PoNo>\n<d:Dat>$headerData[7]T00:00:00</d:Dat>\n<d:Time>PT00H00M00S</d:Time>\n<d:Status>0</d:Status>\n<d:Cancel></d:Cancel>\n<d:CanReas></d:CanReas>\n<d:Mod></d:Mod>\n<d:Priced>X</d:Priced>\n<d:TotAmt>$headerData[4]</d:TotAmt>\n<d:OfficialOrdNo></d:OfficialOrdNo>\n<d:Currcy>USD</d:Currcy>\n<d:Extfld1></d:Extfld1>\n<d:Extfld2></d:Extfld2>\n<d:Extfld3></d:Extfld3>\n<d:Extfld4></d:Extfld4>\n<d:Extfld5></d:Extfld5>\n<d:Extfld6></d:Extfld6>\n<d:MeAction></d:MeAction>\n<d:DocId></d:DocId>\n</m:properties>\n");
        array_push($this->mainXmlData, "<m:properties>\n<d:OrdNo>$headerData[documentnumber]</d:OrdNo>\n<d:TourId>$tourId</d:TourId>\n<d:VisitId>$headerData[visitkey]</d:VisitId>\n<d:ActId>$headerData[visitkey]</d:ActId>\n<d:HhCreate>X</d:HhCreate>\n<d:CustNo>$headerData[documentnumber]</d:CustNo>\n<d:CustNoR>$headerData[documentnumber]</d:CustNoR>\n<d:DelvDate>$headerData[transactiondate]T$headerData[transactiontime]</d:DelvDate>\n<d:PoNo></d:PoNo>\n<d:Dat>$headerData[transactiondate]T$headerData[transactiontime]</d:Dat>\n<d:Time>$timeValue</d:Time>\n<d:Status>0</d:Status>\n<d:Cancel></d:Cancel>\n<d:CanReas></d:CanReas>\n<d:Mod></d:Mod>\n<d:Priced>X</d:Priced>\n<d:TotAmt>$headerData[totalinvoiceamount]</d:TotAmt>\n<d:OfficialOrdNo></d:OfficialOrdNo>\n<d:Currcy>KWD</d:Currcy>\n<d:Extfld1></d:Extfld1>\n<d:Extfld2></d:Extfld2>\n<d:Extfld3></d:Extfld3>\n<d:Extfld4></d:Extfld4>\n<d:Extfld5></d:Extfld5>\n<d:Extfld6></d:Extfld6>\n<d:MeAction></d:MeAction>\n<d:DocId></d:DocId>\n</m:properties>\n");
        array_push($this->mainXmlData, "</atom:content>\n");

        $this->getDetailData($headerData[2]);

    }

    private function getDetailData($visitkey) {
       // echo "getDetailData<br>";
        $param_array = array();
        $param_array[1] = $this->RouteKey;
        $param_array[2] = $visitkey;
        $queryResult = $this->SFA_Comman->executequery('CALL sp_int_export_getdetaildata()',$param_array);
        $this->detailArray = $queryResult[0];
        $mainXmlData = $this->mainXmlData;
        $k = 1;
        array_push($this->mainXmlData, "<atom:link rel='http://schemas.microsoft.com/ado/2007/08/dataservices/related/ORDER_HDR2ITM' type='application/atom+xml;type=feed' title='ORDER_HDR2ITEMS'>\n");
        array_push($this->mainXmlData, "<m:inline>\n");
        array_push($this->mainXmlData, "<atom:feed>\n");

        $this->getDetailXml($k);
    }

    private function getDetailXml($k) {
       // echo "getDetailXml<br>";
        $tourId = $this->TourId;
        $detaildata = array_shift($this->detailArray);

        $itmNo = $k * 10;

        array_push($this->mainXmlData, "<atom:entry>\n");
        array_push($this->mainXmlData, "<atom:content type='application/xml'>\n");
    //    array_push($this->mainXmlData, "<m:properties>\n<d:OrdNo>$detaildata[0]</d:OrdNo>\n<d:ItmNo>$itmNo</d:ItmNo>\n<d:TourId>$tourId</d:TourId>\n<d:Uepos></d:Uepos>\n<d:ImpEmpty></d:ImpEmpty>\n<d:RtnFlag></d:RtnFlag>\n<d:HhCreate>X</d:HhCreate>\n<d:Mod></d:Mod>\n<d:ChgReas>04</d:ChgReas>\n<d:MatNo>$detaildata[2]</d:MatNo>\n<d:MatNoR>$detaildata[2]</d:MatNoR>\n<d:SactQty>$detaildata[4]</d:SactQty>\n<d:SactQtySub>$detaildata[4]</d:SactQtySub>\n<d:SactUom>$detaildata[3]</d:SactUom>\n<d:ScaleQty>$detaildata[4]</d:ScaleQty>\n<d:ScaleQtySub>0.000</d:ScaleQtySub>\n<d:ReserveQty>0.000</d:ReserveQty>\n<d:BillQty>$detaildata[4]</d:BillQty>\n<d:BillQtySub>0.000</d:BillQtySub>\n<d:BillingFlag></d:BillingFlag>\n<d:FreeQty>0.000</d:FreeQty>\n<d:PromoNo></d:PromoNo>\n<d:NetPrice>$detaildata[5]</d:NetPrice>\n<d:ExtItemValue>0.0000</d:ExtItemValue>\n<d:UntiedEmpty></d:UntiedEmpty>\n<d:Currcy>USD</d:Currcy>\n<d:Tax>0.0000</d:Tax>\n<d:PromoResult></d:PromoResult>\n<d:Extfld1></d:Extfld1>\n<d:Extfld2></d:Extfld2>\n<d:Extfld3></d:Extfld3>\n<d:Extfld4></d:Extfld4>\n<d:Extfld5></d:Extfld5>\n<d:SpecReturn></d:SpecReturn>\n<d:Extfld6></d:Extfld6>\n<d:TaxRate>0.00</d:TaxRate>\n</m:properties>\n");
        array_push($this->mainXmlData, "<m:properties>\n<d:OrdNo>$detaildata[invnumber]</d:OrdNo>\n<d:ItmNo>$itmNo</d:ItmNo>\n<d:TourId>$tourId</d:TourId>\n<d:Uepos></d:Uepos>\n<d:ImpEmpty></d:ImpEmpty>\n<d:RtnFlag></d:RtnFlag>\n<d:HhCreate>X</d:HhCreate>\n<d:Mod></d:Mod>\n<d:ChgReas>04</d:ChgReas>\n<d:MatNo>$detaildata[alternatecode]</d:MatNo>\n<d:MatNoR>$detaildata[alternatecode]</d:MatNoR>\n<d:SactQty>$detaildata[salesqty]</d:SactQty>\n<d:SactQtySub>$detaildata[salesqty]</d:SactQtySub>\n<d:SactUom>$detaildata[memo2]</d:SactUom>\n<d:ScaleQty>$detaildata[salesqty]</d:ScaleQty>\n<d:ScaleQtySub>0.000</d:ScaleQtySub>\n<d:ReserveQty>0.000</d:ReserveQty>\n<d:BillQty>$detaildata[salesqty]</d:BillQty>\n<d:BillQtySub>0.000</d:BillQtySub>\n<d:BillingFlag></d:BillingFlag>\n<d:FreeQty>0.000</d:FreeQty>\n<d:PromoNo></d:PromoNo>\n<d:NetPrice>$detaildata[SalesAmount]</d:NetPrice>\n<d:ExtItemValue>0.0000</d:ExtItemValue>\n<d:UntiedEmpty></d:UntiedEmpty>\n<d:Currcy>KWD</d:Currcy>\n<d:Tax>0.0000</d:Tax>\n<d:PromoResult></d:PromoResult>\n<d:Extfld1></d:Extfld1>\n<d:Extfld2></d:Extfld2>\n<d:Extfld3></d:Extfld3>\n<d:Extfld4></d:Extfld4>\n<d:Extfld5></d:Extfld5>\n<d:SpecReturn></d:SpecReturn>\n<d:Extfld6></d:Extfld6>\n<d:TaxRate>0.00</d:TaxRate>\n</m:properties>\n");
        array_push($this->mainXmlData, "</atom:content>\n");
        array_push($this->mainXmlData, "</atom:entry>\n");

        if(sizeof($this->detailArray) > 0) {
            $this->getDetailXml(++$k);
        } else {
            array_push($this->mainXmlData, "</atom:feed>\n");
            array_push($this->mainXmlData, "</m:inline>\n");
            array_push($this->mainXmlData, "</atom:link>\n");
            array_push($this->mainXmlData, "<atom:link rel='http://schemas.microsoft.com/ado/2007/08/dataservices/related/ORDER_HDR2COND' type='application/atom+xml;type=feed' title='ORDER_HDR2COND'>\n");
            array_push($this->mainXmlData, "<m:inline>\n");
            array_push($this->mainXmlData, "<atom:feed>\n\n");
            array_push($this->mainXmlData, "</atom:feed>\n");
            array_push($this->mainXmlData, "</m:inline>\n");
            array_push($this->mainXmlData, "</atom:link>");
            array_push($this->mainXmlData, "</atom:entry>\n");
            array_push($this->mainXmlData, "</atom:feed>\n");
            array_push($this->mainXmlData, "</m:inline>\n");
            array_push($this->mainXmlData, "</atom:link>\n");
            array_push($this->mainXmlData, "</atom:entry>\n\n");

            if(sizeof($this->headerArray) > 0) {
                array_push($this->mainXmlData, $this->routeXml ."");
                $this->getHeaderDataXML();
            } else {
                array_push($this->mainXmlData, "--changeset_005056A5-09B1-1ED1-BF82-409B26A80301--\n");
                array_push($this->mainXmlData, "--batch_005056A5-09B1-1ED1-BF82-409B26A80300--\n");
                $this->mainXmlData = join("", $this->mainXmlData);

                $this->postSAPDBData($this->URL, $this->uidSAP, $this->pwdSAP,$this->mainXmlData);

                

                $this->mainXmlData = "";
                $this->headerArray = "";
                $this->detailArray = "";
            } 
        }
    }

    //-------------------------Delivery
    private function getDelHeaderData() {
       // echo "getDelHeaderData<br>";
        $param_array = array();
        $param_array[1] = $this->RouteKey;
       // $param_array[2] = $this->RouteCode;
       // $queryResult = $this->SFA_Comman->executequery('CALL sp_int_export_getdelheaderdata()',$param_array);
	    $queryResult = $this->SFA_Comman->executequery('CALL sp_int_export_getinvheaderdata()',$param_array);
		
        $this->headerArray = $queryResult[0];
        $this->getDelHeaderDataXML();
    }

    private function getDelHeaderDataXML(){
        //echo "getDelHeaderDataXML<br>";
        $headerData = array_shift($this->headerArray);
        $tourId = $this->TourId;
        $routeCode = $this->RouteCode;
        $shipType = "";
        $activityid = "";

        $myArray = explode(':', $headerData[transactiontime]);
        $timeValue = "PT".$myArray[0]."H".$myArray[1]."M".$myArray[2]."S";

        //if($headerData[9] == '') {
            $activityid = "1";
        /* } else {
            //$activityid = $headerData[9];
              $activityid = $headerData['activityid'];
        } */
        $bedelivery = "";
        $sql = "select memo2 from routemaster where routecode = '$routeCode'";
        $shipType = $this->getQueryResultRow($sql,'memo2');
        if($shipType == "ZMDD") {
            //$bedelivery = $headerData[3];
            $bedelivery = $headerData['documentnumber'];
        }
		$sql = "select alternatesalesmancode from salesman where salesmancode = (select salesmancode from routemaster where routecode = '$routeCode')";
        $salesmanCode = $this->getQueryResultRow($sql,'alternatesalesmancode');

        array_push($this->mainXmlData, "<atom:link rel='http://schemas.microsoft.com/ado/2007/08/dataservices/related/TOUR_DEL2HDR' type='application/atom+xml;type=feed' title='TOUR_DEL2HDR'>\n");
        array_push($this->mainXmlData, "<m:inline>\n");
        array_push($this->mainXmlData, "<atom:feed>\n");
        array_push($this->mainXmlData, "<atom:entry>\n");
        array_push($this->mainXmlData, "<atom:title>User Menu</atom:title>\n");
        array_push($this->mainXmlData, "<atom:id>urn:uuid:88df28a0-1c04-42db-a328-d0ac9154e161</atom:id>\n");
        array_push($this->mainXmlData, "<atom:updated>2014-02-17T00:19:37-08:00</atom:updated>\n");
        array_push($this->mainXmlData, "<atom:author>\n<atom:name>$salesmanCode</atom:name>\n</atom:author>\n");
        array_push($this->mainXmlData, "<atom:content type='application/xml'>\n");
//      array_push($this->mainXmlData, "<m:properties>\n<d:TourId>$tourId</d:TourId>\n<d:VisitId>$headerData[seq]</d:VisitId>\n<d:ActiId>$activityid</d:ActiId>\n<d:DelvNo>$headerData[invoicenumber]</d:DelvNo>\n<d:Doctyp></d:Doctyp>\n<d:CustNo>$headerData[ccode]</d:CustNo>\n<d:CustNoR>$headerData[ccode]</d:CustNoR>\n<d:HhCreate>X</d:HhCreate>\n<d:DelvPrio>02</d:DelvPrio>\n<d:DelvDate>$headerData[transactiondate]T00:00:00</d:DelvDate>\n<d:PoNo></d:PoNo>\n<d:Dat>$headerData[transactiondate]T00:00:00</d:Dat>\n<d:Time>PT00H00M00S</d:Time>\n<d:Status>1</d:Status>\n<d:Cancel></d:Cancel>\n<d:CanReas></d:CanReas>\n<d:Mod></d:Mod>\n<d:Invoiced></d:Invoiced>\n<d:TotAmt>$headerData[totalinvoiceamount]</d:TotAmt>\n<d:OfficialDelvNo>$headerData[invoicenumber]</d:OfficialDelvNo>\n<d:Currcy>USD</d:Currcy>\n<d:Extfld1></d:Extfld1>\n<d:Extfld2></d:Extfld2>\n<d:Extfld3></d:Extfld3>\n<d:Extfld4></d:Extfld4>\n<d:Extfld5></d:Extfld5>\n<d:Extfld6></d:Extfld6>\n<d:MeAction></d:MeAction>\n<d:Plant>CPB1</d:Plant>\n<d:BeDelivery>$bedelivery</d:BeDelivery>\n</m:properties>\n");
        array_push($this->mainXmlData, "<m:properties>\n<d:TourId>$tourId</d:TourId>\n<d:VisitId>$headerData[seq]</d:VisitId>\n<d:ActiId>$headerData[row_number]</d:ActiId>\n<d:DelvNo>$headerData[documentnumber]</d:DelvNo>\n<d:Doctyp></d:Doctyp>\n<d:CustNo>$headerData[ccode]</d:CustNo>\n<d:CustNoR>$headerData[ccode]</d:CustNoR>\n<d:HhCreate>X</d:HhCreate>\n<d:DelvPrio>02</d:DelvPrio>\n<d:DelvDate>$headerData[transactiondate]T$headerData[transactiontime]</d:DelvDate>\n<d:PoNo></d:PoNo>\n<d:Dat>$headerData[transactiondate]T$headerData[transactiontime]</d:Dat>\n<d:Time>$timeValue</d:Time>\n<d:Status>0</d:Status>\n<d:Cancel></d:Cancel>\n<d:CanReas></d:CanReas>\n<d:Mod></d:Mod>\n<d:Invoiced></d:Invoiced>\n<d:TotAmt>$headerData[totalinvoiceamount]</d:TotAmt>\n<d:OfficialDelvNo>$headerData[documentnumber]</d:OfficialDelvNo>\n<d:Currcy>KWD</d:Currcy>\n<d:Extfld1></d:Extfld1>\n<d:Extfld2></d:Extfld2>\n<d:Extfld3></d:Extfld3>\n<d:Extfld4></d:Extfld4>\n<d:Extfld5></d:Extfld5>\n<d:Extfld6></d:Extfld6>\n<d:MeAction></d:MeAction>\n<d:Plant>1000</d:Plant>\n<d:BeDelivery>$bedelivery</d:BeDelivery>\n</m:properties>\n");
    //  array_push($this->mainXmlData, "<m:properties>\n<d:TourId>$tourId</d:TourId>\n<d:VisitId>$headerData[8]</d:VisitId>\n<d:ActiId>$activityid</d:ActiId>\n<d:DelvNo>$headerData[3]</d:DelvNo>\n<d:Doctyp></d:Doctyp>\n<d:CustNo>$headerData[7]</d:CustNo>\n<d:CustNoR>$headerData[7]</d:CustNoR>\n<d:HhCreate>X</d:HhCreate>\n<d:DelvPrio>02</d:DelvPrio>\n<d:DelvDate>$headerData[5]T00:00:00</d:DelvDate>\n<d:PoNo></d:PoNo>\n<d:Dat>$headerData[5]T00:00:00</d:Dat>\n<d:Time>PT00H00M00S</d:Time>\n<d:Status>1</d:Status>\n<d:Cancel></d:Cancel>\n<d:CanReas></d:CanReas>\n<d:Mod></d:Mod>\n<d:Invoiced></d:Invoiced>\n<d:TotAmt>$headerData[4]</d:TotAmt>\n<d:OfficialDelvNo>$headerData[3]</d:OfficialDelvNo>\n<d:Currcy>USD</d:Currcy>\n<d:Extfld1></d:Extfld1>\n<d:Extfld2></d:Extfld2>\n<d:Extfld3></d:Extfld3>\n<d:Extfld4></d:Extfld4>\n<d:Extfld5></d:Extfld5>\n<d:Extfld6></d:Extfld6>\n<d:MeAction></d:MeAction>\n<d:Plant>CPB1</d:Plant>\n<d:BeDelivery>$bedelivery</d:BeDelivery>\n</m:properties>\n");
        array_push($this->mainXmlData, "</atom:content>\n");
        array_push($this->mainXmlData, "<atom:link rel='http://schemas.microsoft.com/ado/2007/08/dataservices/related/DEL_HDR2ITM' type='application/atom+xml;type=feed' title='DEL_HDR2ITM'>\n");
        array_push($this->mainXmlData, "<m:inline>\n");
        array_push($this->mainXmlData, "<atom:feed>\n");

        //getDelDetailData($headerData[2]);
        $this->getDelDetailData($headerData['visitkey']);
    }

    private function getDelDetailData($visitkey) {
        //echo "getDelDetailData<br>";
        $param_array = array();
        $param_array[1] = $this->RouteKey;
        $param_array[2] = $visitkey;
        $queryResult = $this->SFA_Comman->executequery('CALL sp_int_export_getdeldetaildata()',$param_array);
		//$queryResult = $this->SFA_Comman->executequery('CALL sp_int_export_getinvdetaildata()',$param_array);
		
        $this->detailArray = $queryResult[0];
        $k = 1;
        $this->getDelDetailXml($k);
    }

    private function getDelDetailXml($k) {
       // echo "getDelDetailXml<br>";
	   //print_r($this->detailArray);
	   
        $detaildata = array_shift($this->detailArray);
		
        $tourId = $this->TourId;
        $itmNo = $k * 10;

        array_push($this->mainXmlData, "<atom:entry>\n");
        array_push($this->mainXmlData, "<atom:content type='application/xml'>\n");
        
        array_push($this->mainXmlData, "<m:properties>\n<d:DelvNo>$detaildata[documentnumber]</d:DelvNo>\n<d:ItmNo>$itmNo</d:ItmNo>\n<d:TourId>$tourId</d:TourId>\n<d:Pstyv></d:Pstyv>\n<d:Uepos>000000</d:Uepos>\n<d:ImpEmpty></d:ImpEmpty>\n<d:RtnFlag>$detaildata[flg]</d:RtnFlag>\n<d:HhCreate>X</d:HhCreate>\n<d:Mod></d:Mod>\n<d:ChgReas>$detaildata[res]</d:ChgReas>\n<d:MatNo>$detaildata[alternatecode]</d:MatNo>\n<d:MatNoR>$detaildata[alternatecode]</d:MatNoR>\n<d:PlantCode>1000</d:PlantCode>\n<d:StorageLoc></d:StorageLoc>\n<d:DplnQty>$detaildata[salesqty]</d:DplnQty>\n<d:DplnQtySub>0.000</d:DplnQtySub>\n<d:DplnUom>$detaildata[memo2]</d:DplnUom>\n<d:DactQty>$detaildata[salesqty]</d:DactQty>\n<d:DactQtySub>0.000</d:DactQtySub>\n<d:DactUom>$detaildata[memo2]</d:DactUom>\n<d:ScaleQty>$detaildata[salesqty]</d:ScaleQty>\n<d:ScaleQtySub>0.000</d:ScaleQtySub>\n<d:BillQty>$detaildata[salesqty]</d:BillQty>\n<d:BillQtySub>0.000</d:BillQtySub>\n<d:BillingFlag>X</d:BillingFlag>\n<d:PromoNo></d:PromoNo>\n<d:NetPrice>0.000</d:NetPrice>\n<d:ExtItemValue>0.0000</d:ExtItemValue>\n<d:Currcy>KWD</d:Currcy>\n<d:Condtype></d:Condtype>\n<d:Extfld1></d:Extfld1>\n<d:Extfld2></d:Extfld2>\n<d:Extfld3></d:Extfld3>\n<d:Extfld4></d:Extfld4>\n<d:Extfld5></d:Extfld5>\n<d:Extfld6></d:Extfld6>\n<d:Batch></d:Batch>\n<d:Bwtar></d:Bwtar>\n<d:Plant>1000</d:Plant>\n<d:SpecReturn></d:SpecReturn>\n<d:PromoResult></d:PromoResult>\n<d:UntiedEmpty></d:UntiedEmpty>\n</m:properties>\n");
        array_push($this->mainXmlData, "</atom:content>\n");
        array_push($this->mainXmlData, "</atom:entry>\n");

        if(sizeof($this->detailArray) > 0){
            $this->getDelDetailXml(++$k);
        } else {
			
				$param_array = array();			
			 $param_array[1] = $detaildata[documentnumber];			
			$querydiscount = $this->SFA_Comman->executequery('CALL sp_int_export_getinvheaderdiscount()',$param_array);
						
            array_push($this->mainXmlData, "</atom:feed>\n");
            array_push($this->mainXmlData, "</m:inline>\n");
            array_push($this->mainXmlData, "</atom:link>\n");
            array_push($this->mainXmlData, "<atom:link rel='http://schemas.microsoft.com/ado/2007/08/dataservices/related/DEL_HDR2COND' type='application/atom+xml;type=feed' title='DEL_HDR2COND'>\n");
            array_push($this->mainXmlData, "<m:inline>\n");
            array_push($this->mainXmlData, "<atom:feed>\n");
           
	    for($i=0;$i<count($querydiscount[0]);$i++)
	    	{
			if($querydiscount[0][$i][delitem] == '101')
			{
					
					array_push($this->mainXmlData, "<atom:entry>\n");
					array_push($this->mainXmlData, "<atom:content type='application/xml'>\n");
					array_push($this->mainXmlData, "<m:properties>\n<d:TourId>$tourId</d:TourId>\n<d:VisitId>1</d:VisitId>\n<d:DelvNo>1920000124</d:DelvNo>\n<d:DelvItm>101</d:DelvItm>\n<d:CondType></d:CondType>\n<d:Amount>0.0000</d:Amount>\n<d:Currency></d:Currency>\n<d:PercentageType></d:PercentageType>\n<d:PromoDiscount></d:PromoDiscount>\n<d:CondCount></d:CondCount>\n<d:Pronr></d:Pronr>\n</m:properties>\n");	
					array_push($this->mainXmlData, "</atom:content>\n");
					array_push($this->mainXmlData, "</atom:entry>\n");
				
				}else
				{
					
					array_push($this->mainXmlData, "<atom:entry>\n");
					array_push($this->mainXmlData, "<atom:content type='application/xml'>\n");
					array_push($this->mainXmlData, "<m:properties>\n<d:TourId>$tourId</d:TourId>\n<d:VisitId>".$querydiscount[0][$i][seq]."</d:VisitId>\n<d:DelvNo>".$querydiscount[0][$i][documentnumber]."</d:DelvNo>\n<d:DelvItm>".$querydiscount[0][$i][delitem]."</d:DelvItm>\n<d:CondType>".$querydiscount[0][$i][condtype]."</d:CondType>\n<d:Amount>".$querydiscount[0][$i][promovalue]."</d:Amount>\n<d:Currency></d:Currency>\n<d:PercentageType>".$querydiscount[0][$i][pertype]."</d:PercentageType>\n<d:PromoDiscount></d:PromoDiscount>\n<d:CondCount>".$querydiscount[0][$i][condcount]."</d:CondCount>\n<d:Pronr></d:Pronr>\n</m:properties>\n");
					array_push($this->mainXmlData, "</atom:content>\n");
					array_push($this->mainXmlData, "</atom:entry>\n");
					
				}
			}			
            //array_push($this->mainXmlData, "<m:properties>\n<d:TourId>$tourId</d:TourId>\n<d:VisitId>$discountdata[seq]</d:VisitId>\n<d:DelvNo>$discountdata[documentnumber]</d:DelvNo>\n<d:DelvItm>$discountdata[delitem]</d:DelvItm>\n<d:CondType>$discountdata[condtype]</d:CondType>\n<d:Amount>$discountdata[promovalue]</d:Amount>\n<d:Currency></d:Currency>\n<d:PercentageType>$discountdata[pertype]</d:PercentageType>\n<d:PromoDiscount></d:PromoDiscount>\n<d:CondCount>$discountdata[condcount]</d:CondCount>\n<d:Pronr></d:Pronr>\n</m:properties>\n");
		    
            
	    array_push($this->mainXmlData, "</atom:feed>\n");
            array_push($this->mainXmlData, "</m:inline>\n");
            array_push($this->mainXmlData, "</atom:link>\n");
            array_push($this->mainXmlData, "</atom:entry>\n");
            array_push($this->mainXmlData, "</atom:feed>\n");
            array_push($this->mainXmlData, "</m:inline>\n");
            array_push($this->mainXmlData, "</atom:link>\n");
            array_push($this->mainXmlData, "</atom:entry>\n\n");

            if(sizeof($this->headerArray) > 0) {
                array_push($this->mainXmlData, $this->routeXml ."");
                $this->getDelHeaderDataXML();
            } else {
				
				//---------------------------------
		$param_array = array();
        $param_array[1] = $this->RouteKey;
        //$queryResult = $this->SFA_Comman->executequery('CALL sp_int_export_getinvheaderdata()',$param_array);
		$queryResult = $this->SFA_Comman->executequery('CALL sp_int_export_getinvheaderdatadelivery()',$param_array);
		$j = 1;
        $this->headerArray = $queryResult[0];		
				
		if(count($queryResult[0])>0)
		{	
				array_push($this->mainXmlData, $this->routeXml ."");
                $this->getInvHeaderData();
       
		}else{	
			
				$param_array = array();
				$param_array[1] = $this->RouteKey;
				$queryResult = $this->SFA_Comman->executequery('CALL sp_int_export_getcashdetaildata()',$param_array);
				$this->detailArray = $queryResult[0];
				if(count($queryResult[0])>0)
				{
					
					array_push($this->mainXmlData, $this->routeXml."");
					array_push($this->mainXmlData, "<atom:link rel='http://schemas.microsoft.com/ado/2007/08/dataservices/related/TOUR2COLL_PAYM' type='application/atom+xml;type=feed' title='SOItems'>\n");
					array_push($this->mainXmlData, "<m:inline>\n");
					array_push($this->mainXmlData, "<atom:feed>\n");
					$this->getCashDetailData();
					
				}else
				{
					$curdate=date('Y-m-d') ."T" .date('h:i:s');
					$time =date("H:i:s");
								$curtime = explode(':', $time);
								$timeValue = "PT".$curtime[0]."H".$curtime[1]."M".$curtime[2]."S";
					  array_push($this->mainXmlData, $this->routeXml."");
					  array_push($this->mainXmlData, "<atom:link rel='http://schemas.microsoft.com/ado/2007/08/dataservices/related/TOUR_COCI2HDR' type='application/atom+xml;type=feed' title='TOUR_COCI2HDR'>\n<m:inline>\n<atom:feed>\n<atom:entry>\n<atom:content type='application/xml'>\n<m:properties>\n<d:TourId>$tourId</d:TourId>\n<d:CheckId>02</d:CheckId>\n<d:CheckDirect>2</d:CheckDirect>\n<d:Checker>Mirnah</d:Checker>\n<d:CheckDate>$curdate</d:CheckDate>\n<d:CheckTime>$timeValue</d:CheckTime>\n<d:CheckType>A</d:CheckType>\n<d:Extfld1></d:Extfld1>\n<d:Extfld2></d:Extfld2>\n<d:Extfld3></d:Extfld3>\n<d:Extfld4></d:Extfld4>\n<d:Extfld5></d:Extfld5>\n<d:Extfld6></d:Extfld6>\n<d:MeAction></d:MeAction>\n<d:Plant>1000</d:Plant>\n</m:properties>\n</atom:content>\n");
					 array_push($this->mainXmlData, "<atom:link rel='http://schemas.microsoft.com/ado/2007/08/dataservices/related/COCI_HDR2ITM' type='application/atom+xml;type=feed' title='COCI_HDR2ITM'>\n");
					 array_push($this->mainXmlData, "<m:inline>\n");
					 array_push($this->mainXmlData, "<atom:feed>\n");
					 $this->getCheckinData();
					
					
				}	
			
			}
				//---------------------------------
                
            } 
        }
    }

    //-------------------------Invoice
    private function getInvHeaderData() {
       // echo "getInvHeaderData<br>";
		$param_array = array();
        $param_array[1] = $this->RouteKey;
        //$queryResult = $this->SFA_Comman->executequery('CALL sp_int_export_getinvheaderdata()',$param_array);
		$queryResult = $this->SFA_Comman->executequery('CALL sp_int_export_getinvheaderdatadelivery()',$param_array);
		$j = 1;
        $this->headerArray = $queryResult[0];
        $this->getInvHeaderDataXML($j);
        
    }

    private function getInvHeaderDataXML($j){
       // echo "getInvHeaderDataXML<br>";
        $headerData = array_shift($this->headerArray);
        $tourId = $this->TourId;
        $routeCode = $this->RouteCode;
        //$invNo = "0" .$routeCode ."0" .$j;
		$invNo = $headerData[invoicenumber];

        $myArray = explode(':', $headerData[transactiontime]);
        $timeValue = "PT".$myArray[0]."H".$myArray[1]."M".$myArray[2]."S";

        array_push($this->mainXmlData, "<atom:link rel='http://schemas.microsoft.com/ado/2007/08/dataservices/related/TOUR_INV2HDR' type='application/atom+xml;type=feed' title='TOUR_INV2HDR'>\n");
        array_push($this->mainXmlData, "<m:inline>\n");
        array_push($this->mainXmlData, "<atom:feed>\n");
        array_push($this->mainXmlData, "<atom:entry>\n");
        array_push($this->mainXmlData, "<atom:content type='application/xml'>\n");
        array_push($this->mainXmlData, "<m:properties>\n<d:TourId>$tourId</d:TourId>\n");
        //array_push($this->mainXmlData, "<d:VisitId>$headerData[8]</d:VisitId>\n");
        array_push($this->mainXmlData, "<d:VisitId>$headerData[seq]</d:VisitId>\n");
        array_push($this->mainXmlData, "<d:InvNo>$invNo</d:InvNo>\n");
        array_push($this->mainXmlData, "<d:ActiId>1</d:ActiId>\n<d:InvDesc></d:InvDesc>\n");
        array_push($this->mainXmlData, "<d:OfficialInvNo>$invNo</d:OfficialInvNo>\n");
    //  array_push($this->mainXmlData, "<d:HhCreate>X</d:HhCreate>\n<d:InvDate>$headerData[5]T00:00:00</d:InvDate>\n<d:Dat>$headerData[5]T$headerData[6]</d:Dat>\n<d:Time>PT00H00M00S</d:Time>\n<d:PrintDate>$headerData[5]T$headerData[6]</d:PrintDate>\n<d:PrintTime>PT12H22M22S</d:PrintTime>\n<d:CanReas></d:CanReas>\n<d:ControlFrom></d:ControlFrom>\n<d:ControlTo></d:ControlTo>\n</m:properties>\n");
    //    array_push($this->mainXmlData, "<d:HhCreate>X</d:HhCreate>\n<d:InvDate>$headerData[transactiondate]T00:00:00</d:InvDate>\n<d:Dat>$headerData[transactiondate]T$headerData[transactiontime]</d:Dat>\n<d:Time>PT00H00M00S</d:Time>\n<d:PrintDate>$headerData[transactiondate]T$headerData[transactiontime]</d:PrintDate>\n<d:PrintTime>PT12H22M22S</d:PrintTime>\n<d:CanReas></d:CanReas>\n<d:ControlFrom></d:ControlFrom>\n<d:ControlTo></d:ControlTo>\n</m:properties>\n");
        array_push($this->mainXmlData, "<d:HhCreate>X</d:HhCreate>\n<d:InvDate>$headerData[transactiondate]T$headerData[transactiontime]</d:InvDate>\n<d:Dat>$headerData[transactiondate]T$headerData[transactiontime]</d:Dat>\n<d:Time>$timeValue</d:Time>\n<d:PrintDate>$headerData[transactiondate]T$headerData[transactiontime]</d:PrintDate>\n<d:PrintTime>$timeValue</d:PrintTime>\n<d:CanReas></d:CanReas>\n<d:ControlFrom></d:ControlFrom>\n<d:ControlTo></d:ControlTo>\n</m:properties>\n");
        array_push($this->mainXmlData, "</atom:content>\n");
		//-----------------
		
		
        array_push($this->mainXmlData, "<atom:link rel='http://schemas.microsoft.com/ado/2007/08/dataservices/related/INV_HDR2ITM' type='application/atom+xml;type=feed' title='INV_HDR2ITM'>\n");
        array_push($this->mainXmlData, "<m:inline>\n");
        array_push($this->mainXmlData, "<atom:feed>\n");
        
		//----------------------------Start
		$param_array = array();
		 $param_array[1] = $this->RouteKey;
         $param_array[2] = $headerData[visitkey];
		 $queryResult = $this->SFA_Comman->executequery('CALL sp_int_export_getdeldetaildata()',$param_array);
				
		
		for($i=1;$i<=count($queryResult[0]);$i++)
		{	
		$item=$i*10;	
		array_push($this->mainXmlData, "<atom:entry>\n");	
        array_push($this->mainXmlData, "<atom:content type='application/xml'>\n");
    //  array_push($this->mainXmlData, "<m:properties>\n<d:TourId>$tourId</d:TourId>\n<d:InvNo>$invNo</d:InvNo>\n<d:InvItmNo>10</d:InvItmNo>\n<d:DelvNo>$headerData[3]</d:DelvNo>\n<d:OfficialDelvNo>$headerData[3]</d:OfficialDelvNo>\n</m:properties>\n");
        array_push($this->mainXmlData, "<m:properties>\n<d:TourId>$tourId</d:TourId>\n<d:InvNo>$invNo</d:InvNo>\n<d:InvItmNo>$item</d:InvItmNo>\n<d:DelvNo>$headerData[documentnumber]</d:DelvNo>\n<d:OfficialDelvNo>$headerData[documentnumber]</d:OfficialDelvNo>\n</m:properties>\n");
        array_push($this->mainXmlData, "</atom:content>\n");
		array_push($this->mainXmlData, "</atom:entry>\n");
		}
		//------------------------------End
        
        array_push($this->mainXmlData, "</atom:feed>\n");	
        array_push($this->mainXmlData, "</m:inline>\n");
        array_push($this->mainXmlData, "</atom:link>\n");
		
        array_push($this->mainXmlData, "</atom:entry>\n");
        array_push($this->mainXmlData, "</atom:feed>\n");
        array_push($this->mainXmlData, "</m:inline>\n");
        array_push($this->mainXmlData, "</atom:link>\n");
        array_push($this->mainXmlData, "</atom:entry>\n\n");

        if(sizeof($this->headerArray) > 0) {
            array_push($this->mainXmlData, $this->routeXml."");
            $this->getInvHeaderDataXML(++$j);
        } else {
			//--------------
				$param_array = array();
				$param_array[1] = $this->RouteKey;
				$queryResult = $this->SFA_Comman->executequery('CALL sp_int_export_getcashdetaildata()',$param_array);
				$this->detailArray = $queryResult[0];
				if(count($queryResult[0])>0)
				{
					
					array_push($this->mainXmlData, $this->routeXml."");
					array_push($this->mainXmlData, "<atom:link rel='http://schemas.microsoft.com/ado/2007/08/dataservices/related/TOUR2COLL_PAYM' type='application/atom+xml;type=feed' title='SOItems'>\n");
					array_push($this->mainXmlData, "<m:inline>\n");
					array_push($this->mainXmlData, "<atom:feed>\n");
					$this->getCashDetailData();
					
				}else
				{
					$curdate=date('Y-m-d') ."T" .date('h:i:s');
					$time =date("H:i:s");
								$curtime = explode(':', $time);
								$timeValue = "PT".$curtime[0]."H".$curtime[1]."M".$curtime[2]."S";
					  array_push($this->mainXmlData, $this->routeXml."");
					  array_push($this->mainXmlData, "<atom:link rel='http://schemas.microsoft.com/ado/2007/08/dataservices/related/TOUR_COCI2HDR' type='application/atom+xml;type=feed' title='TOUR_COCI2HDR'>\n<m:inline>\n<atom:feed>\n<atom:entry>\n<atom:content type='application/xml'>\n<m:properties>\n<d:TourId>$tourId</d:TourId>\n<d:CheckId>02</d:CheckId>\n<d:CheckDirect>2</d:CheckDirect>\n<d:Checker>Mirnah</d:Checker>\n<d:CheckDate>$curdate</d:CheckDate>\n<d:CheckTime>$timeValue</d:CheckTime>\n<d:CheckType>A</d:CheckType>\n<d:Extfld1></d:Extfld1>\n<d:Extfld2></d:Extfld2>\n<d:Extfld3></d:Extfld3>\n<d:Extfld4></d:Extfld4>\n<d:Extfld5></d:Extfld5>\n<d:Extfld6></d:Extfld6>\n<d:MeAction></d:MeAction>\n<d:Plant>1000</d:Plant>\n</m:properties>\n</atom:content>\n");
					 array_push($this->mainXmlData, "<atom:link rel='http://schemas.microsoft.com/ado/2007/08/dataservices/related/COCI_HDR2ITM' type='application/atom+xml;type=feed' title='COCI_HDR2ITM'>\n");
					 array_push($this->mainXmlData, "<m:inline>\n");
					 array_push($this->mainXmlData, "<atom:feed>\n");
					 $this->getCheckinData();
					
					
				}
			//--------------
           /* array_push($this->mainXmlData, $this->routeXml ."");
            array_push($this->mainXmlData, "<atom:link rel='http://schemas.microsoft.com/ado/2007/08/dataservices/related/TOUR2COLL_PAYM' type='application/atom+xml;type=feed' title='SOItems'>\n");
            array_push($this->mainXmlData, "<m:inline>\n");
            array_push($this->mainXmlData, "<atom:feed>\n");
            $this->getCashDetailData();*/
            // getInvDetailData($headerData[2],$j); //check why this is not calling at this point.
        }
    }

    private function getInvDetailData($visitkey, $j) {
       // echo "getInvDetailData<br>";
        $param_array = array();
        $param_array[1] = $this->RouteKey;
        $queryResult = $this->SFA_Comman->executequery('CALL sp_int_export_getinvdetaildata()',$param_array);
        $k = 1;
        $this->detailArray = $queryResult[0];
        $this->getInvDetailXml($k,$j);
    }

    private function getInvDetailXml($k, $j) {
        //echo "getInvDetailXml<br>";
        $detaildata = array_shift($this->detailArray);
        $tourId = $this->TourId;
        $routeCode = $this->RouteCode;
        //$invNo = "0" .$routeCode ."0" .$j;
		$invNo = $detaildata['invoicenumber'];
        $invItmNo = $k * 10;
        //$delvNo = "00" .$detaildata[3];
        $delvNo = $detaildata['documentnumber'];

        array_push($this->mainXmlData, "<atom:link rel='http://schemas.microsoft.com/ado/2007/08/dataservices/related/INV_HDR2ITM' type='application/atom+xml;type=feed' title='INV_HDR2ITM'>\n");
        array_push($this->mainXmlData, "<m:inline>\n");
        array_push($this->mainXmlData, "<atom:feed>\n");
        array_push($this->mainXmlData, "<atom:entry>\n");
        array_push($this->mainXmlData, "<atom:content type='application/xml'>\n");
        array_push($this->mainXmlData, "<m:properties>\n<d:TourId>$tourId</d:TourId>\n<d:InvNo>$invNo</d:InvNo>\n<d:InvItmNo>$invItmNo</d:InvItmNo>\n<d:DelvNo>$delvNo</d:DelvNo>\n<d:OfficialDelvNo>$delvNo</d:OfficialDelvNo>\n</m:properties>\n");
        array_push($this->mainXmlData, "</atom:content>\n");
        array_push($this->mainXmlData, "</atom:entry>\n");
        array_push($this->mainXmlData, "</atom:feed>\n");
        array_push($this->mainXmlData, "</m:inline>\n");
        array_push($this->mainXmlData, "</atom:link>\n");

        if(sizeof($this->detailArray) > 0){
            $this->getInvDetailXml(++$k,$j);
        } else {
            array_push($this->mainXmlData, "</atom:entry>\n");
            array_push($this->mainXmlData, "</atom:feed>\n");
            array_push($this->mainXmlData, "</m:inline>\n");
            array_push($this->mainXmlData, "</atom:link>\n");
            array_push($this->mainXmlData, "</atom:entry>\n\n");

            if(sizeof($this->headerArray) > 0) {
                array_push($this->mainXmlData, $this->routeXml."");
                $this->getInvHeaderDataXML(++$j);
            } else {
				
				//------------------
				$param_array = array();
				$param_array[1] = $this->RouteKey;
				$queryResult = $this->SFA_Comman->executequery('CALL sp_int_export_getcashdetaildata()',$param_array);
				$this->detailArray = $queryResult[0];
				if(count($queryResult[0])>0)
				{
					
					array_push($this->mainXmlData, $this->routeXml."");
					array_push($this->mainXmlData, "<atom:link rel='http://schemas.microsoft.com/ado/2007/08/dataservices/related/TOUR2COLL_PAYM' type='application/atom+xml;type=feed' title='SOItems'>\n");
					array_push($this->mainXmlData, "<m:inline>\n");
					array_push($this->mainXmlData, "<atom:feed>\n");
					$this->getCashDetailData();
					
				}else
				{
					$curdate=date('Y-m-d') ."T" .date('h:i:s');
					$time =date("H:i:s");
								$curtime = explode(':', $time);
								$timeValue = "PT".$curtime[0]."H".$curtime[1]."M".$curtime[2]."S";
					   array_push($this->mainXmlData, $this->routeXml."");		
					  array_push($this->mainXmlData, "<atom:link rel='http://schemas.microsoft.com/ado/2007/08/dataservices/related/TOUR_COCI2HDR' type='application/atom+xml;type=feed' title='TOUR_COCI2HDR'>\n<m:inline>\n<atom:feed>\n<atom:entry>\n<atom:content type='application/xml'>\n<m:properties>\n<d:TourId>$tourId</d:TourId>\n<d:CheckId>02</d:CheckId>\n<d:CheckDirect>2</d:CheckDirect>\n<d:Checker>Mirnah</d:Checker>\n<d:CheckDate>$curdate</d:CheckDate>\n<d:CheckTime>$timeValue</d:CheckTime>\n<d:CheckType>A</d:CheckType>\n<d:Extfld1></d:Extfld1>\n<d:Extfld2></d:Extfld2>\n<d:Extfld3></d:Extfld3>\n<d:Extfld4></d:Extfld4>\n<d:Extfld5></d:Extfld5>\n<d:Extfld6></d:Extfld6>\n<d:MeAction></d:MeAction>\n<d:Plant>1000</d:Plant>\n</m:properties>\n</atom:content>\n");
					 array_push($this->mainXmlData, "<atom:link rel='http://schemas.microsoft.com/ado/2007/08/dataservices/related/COCI_HDR2ITM' type='application/atom+xml;type=feed' title='COCI_HDR2ITM'>\n");
					 array_push($this->mainXmlData, "<m:inline>\n");
					 array_push($this->mainXmlData, "<atom:feed>\n");
					 $this->getCheckinData();
					
					
				}
				//------------------
                
            }
        }
    }

    private function getCashDetailData() {
       // echo "getCashDetailData<br>";
        $param_array = array();
        $param_array[1] = $this->RouteKey;
        $queryResult = $this->SFA_Comman->executequery('CALL sp_int_export_getcashdetaildata()',$param_array);
        $this->detailArray = $queryResult[0];
        $this->getCashDetailXml();
    }

    private function getCashDetailXml() {
       // echo "getCashDetailXml<br>";
        $detaildata = array_shift($this->detailArray);
        $tourId = $this->TourId;

        array_push($this->mainXmlData, "<atom:entry>\n");
        array_push($this->mainXmlData, "<atom:content type='application/xml'>\n");
        array_push($this->mainXmlData, "<m:properties>\n<d:TourId>$tourId</d:TourId>\n");
        //array_push($this->mainXmlData, "<d:VisitId>$detaildata[1]</d:VisitId>\n");
        array_push($this->mainXmlData, "<d:VisitId>$detaildata[seq]</d:VisitId>\n");
        array_push($this->mainXmlData, "<d:ActiId>$detaildata[row_number]</d:ActiId>\n");
        array_push($this->mainXmlData, "<d:CashId>$detaildata[row_number]</d:CashId>\n");
        array_push($this->mainXmlData, "<d:PymtType>$detaildata[paytype]</d:PymtType>\n");
        //array_push($this->mainXmlData, "<d:Amount>$detaildata[2]</d:Amount>\n");
        array_push($this->mainXmlData, "<d:Amount>$detaildata[cash]</d:Amount>\n");
        array_push($this->mainXmlData, "<d:PyCNum></d:PyCNum>\n");
        array_push($this->mainXmlData, "<d:PyCType></d:PyCType>\n");
		//if($detaildata[chkdate]!="")
		//{	
		array_push($this->mainXmlData, "<d:PyCExp>$detaildata[chkdate]T00:00:00</d:PyCExp>\n");
		array_push($this->mainXmlData, "<d:PaymtDescr>$detaildata[invno]</d:PaymtDescr>\n");
		//}		
		//array_push($this->mainXmlData, "<d:PaymtDescr>$detaildata[invno]</d:PaymtDescr>\n");
        array_push($this->mainXmlData, "<d:CheckNo>$detaildata[checknumber]</d:CheckNo>\n");
		array_push($this->mainXmlData, "<d:BkKey>$detaildata[bankkey]</d:BkKey>\n");
        
        array_push($this->mainXmlData, "<d:Extfld1></d:Extfld1>\n<d:Extfld2></d:Extfld2>\n<d:Extfld3></d:Extfld3>\n<d:Extfld4></d:Extfld4>\n<d:Extfld5></d:Extfld5>\n<d:Extfld6></d:Extfld6>\n<d:CollectId></d:CollectId>\n</m:properties>\n");
        array_push($this->mainXmlData, "</atom:content>\n");
        array_push($this->mainXmlData, "</atom:entry>\n");

        if(sizeof($this->detailArray) > 0) {
            $this->getCashDetailXml();
        }else{
            array_push($this->mainXmlData, "</atom:feed>\n");
            array_push($this->mainXmlData, "</m:inline>\n");
            array_push($this->mainXmlData, "</atom:link>\n");
            array_push($this->mainXmlData, "</atom:entry>\n");  
             array_push($this->mainXmlData, $this->routeXml ."");
            array_push($this->mainXmlData, "<atom:link rel='http://schemas.microsoft.com/ado/2007/08/dataservices/related/TOUR2COLL_CLEAR' type='application/atom+xml;type=feed' title='SOItems'>\n<m:inline>\n<atom:feed>\n");
            $this->getcollectinginvData();
       }
    }

	//---------------------------------------------Chekcinpayment
	
	//--------------------------------------------------
    //---------------Checking
    private function getCheckinData() {
       // echo "getCheckinData<br>";
        $param_array = array();
        $param_array[1] = $this->RouteKey;
        $queryResult = $this->SFA_Comman->executequery('CALL sp_int_export_getcheckindata()',$param_array);
        $l=1;
        $this->detailArray = $queryResult[0];
        $this->getCheckinDataxml($l);
    }

    private function getCheckinDataxml($l) {
      //  echo "getCheckinDataxml<br>";
        $detaildata = array_shift($this->detailArray);  
        $tourId = $this->TourId;
        $itmNo = $l * 10;

        array_push($this->mainXmlData, "<atom:entry>\n");
        array_push($this->mainXmlData, "<atom:content type='application/xml'>\n");
        array_push($this->mainXmlData, "<m:properties>\n<d:TourId>$tourId</d:TourId>\n");
        array_push($this->mainXmlData, "<d:CheckId>02</d:CheckId>\n");
        array_push($this->mainXmlData, "<d:ItmNo>$itmNo</d:ItmNo>\n");
        //array_push($this->mainXmlData, "<d:MatNo>$detaildata[0]</d:MatNo>\n");
        array_push($this->mainXmlData, "<d:MatNo>$detaildata[acode]</d:MatNo>\n");
        array_push($this->mainXmlData, "<d:MatNoR>$detaildata[acode]</d:MatNoR>\n");
        array_push($this->mainXmlData, "<d:Uom>$detaildata[memo]</d:Uom>\n");		
		array_push($this->mainXmlData, "<d:Rotruck>$detaildata[trantype]</d:Rotruck>\n");
        //array_push($this->mainXmlData, "<d:PlanQty>$detaildata[2]</d:PlanQty>\n");
        array_push($this->mainXmlData, "<d:PlanQty>$detaildata[qty]</d:PlanQty>\n");
    //  array_push($this->mainXmlData, "<d:ActQty>$detaildata[2]</d:ActQty>\n");
        array_push($this->mainXmlData, "<d:ActQty>$detaildata[qty]</d:ActQty>\n");
        array_push($this->mainXmlData, "<d:CociReason></d:CociReason>\n");		
        array_push($this->mainXmlData, "<d:Extfld1></d:Extfld1>\n<d:Extfld2></d:Extfld2>\n<d:Extfld3></d:Extfld3>\n<d:Extfld4></d:Extfld4>\n<d:Extfld5></d:Extfld5>\n<d:Extfld6></d:Extfld6>\n<d:Batch>DSDBATCH</d:Batch>\n<d:Bwtar></d:Bwtar>\n<d:Plant>1000</d:Plant>\n<d:SpecReturn>$detaildata[specrtrn]</d:SpecReturn>\n</m:properties>\n");
        array_push($this->mainXmlData, "</atom:content>\n");
        array_push($this->mainXmlData, "</atom:entry>\n");

        if(sizeof($this->detailArray) > 0) {
            $this->getCheckinDataxml(++$l);
        } else {
           
			
			//------------------------------------------------------------------------------------
					$param_array = array();
					$param_array[1] = $this->RouteKey;
					$queryResult = $this->SFA_Comman->executequery('CALL sp_int_export_getcashdetaildata()',$param_array);

					if(count($queryResult[0])>0)
					{
						  array_push($this->mainXmlData, "</atom:feed>\n");
							array_push($this->mainXmlData, "</m:inline>\n");
							array_push($this->mainXmlData, "</atom:link>\n");
							array_push($this->mainXmlData, "<atom:link rel='http://schemas.microsoft.com/ado/2007/08/dataservices/related/COCI_HDR2PAY' type='application/atom+xml;type=feed' title='COCI_HDR2PAY'>\n<m:inline>\n<atom:feed>\n");	
						//------------------Checking payment --open
						$this->getCheckinpaymentData();
						//-------------------Cheking payment --End
						
						
					}else
					{
						$curdate=date('Y-m-d') ."T" .date('h:i:s');
						$time =date("H:i:s");
								$curtime = explode(':', $time);
								$timeValue = "PT".$curtime[0]."H".$curtime[1]."M".$curtime[2]."S";
						array_push($this->mainXmlData, "</atom:feed>\n");
						array_push($this->mainXmlData, "</m:inline>\n");
						array_push($this->mainXmlData, "</atom:link>\n");
						array_push($this->mainXmlData, "<atom:link rel='http://schemas.microsoft.com/ado/2007/08/dataservices/related/COCI_HDR2PAY' type='application/atom+xml;type=feed' title='COCI_HDR2PAY'>\n<m:inline>\n<atom:feed />\n</m:inline>\n</atom:link>\n</atom:entry>\n</atom:feed>\n</m:inline>\n</atom:link>\n");
						array_push($this->mainXmlData, "</atom:entry>\n\n");
						array_push($this->mainXmlData, $this->routeXml ."");            
						array_push($this->mainXmlData, "<atom:link rel='http://schemas.microsoft.com/ado/2007/08/dataservices/related/TOUR_COCI2HDR' type='application/atom+xml;type=feed' title='TOUR_COCI2HDR'>\n<m:inline>\n<atom:feed>\n<atom:entry>\n<atom:content type='application/xml'>\n<m:properties>\n<d:TourId>$tourId</d:TourId>\n<d:CheckId>01</d:CheckId>\n<d:CheckDirect>1</d:CheckDirect>\n<d:Checker>Mirnah</d:Checker>\n<d:CheckDate>$curdate</d:CheckDate>\n<d:CheckTime>$timeValue</d:CheckTime>\n<d:CheckType>A</d:CheckType>\n<d:Extfld1></d:Extfld1>\n<d:Extfld2></d:Extfld2>\n<d:Extfld3></d:Extfld3>\n<d:Extfld4></d:Extfld4>\n<d:Extfld5></d:Extfld5>\n<d:Extfld6></d:Extfld6>\n<d:MeAction></d:MeAction>\n<d:Plant>1000</d:Plant>\n</m:properties>\n</atom:content>\n");
						array_push($this->mainXmlData, "<atom:link rel='http://schemas.microsoft.com/ado/2007/08/dataservices/related/COCI_HDR2ITM' type='application/atom+xml;type=feed' title='COCI_HDR2ITM'>\n");
						array_push($this->mainXmlData, "<m:inline>\n");
						array_push($this->mainXmlData, "<atom:feed>\n");	
						$this->getCheckoutData();
						
					}
			//------------------------------------------------------------------------------------
			
			 
        }
    }
	
	//------------------------------------------------------------checkin payment
		private function getCheckinpaymentData() {
       // echo "getCheckinData<br>";
        $param_array = array();
        $param_array[1] = $this->RouteKey;
        $queryResult = $this->SFA_Comman->executequery('CALL sp_int_export_getcheckinamt()',$param_array);
        $l=1;
        $this->detailArray = $queryResult[0];
        $this->getCheckinpaymentxml($l);
    }

    private function getCheckinpaymentxml($l) {
      //  echo "getCheckinDataxml<br>";
        $detaildata = array_shift($this->detailArray);  
        $tourId = $this->TourId;
        $itmNo = $l * 10;

        array_push($this->mainXmlData, "<atom:entry>\n");
        array_push($this->mainXmlData, "<atom:content type='application/xml'>\n");
        array_push($this->mainXmlData, "<m:properties>\n<d:TourId>$tourId</d:TourId>\n");
        array_push($this->mainXmlData, "<d:CheckId>02</d:CheckId>\n");
        array_push($this->mainXmlData, "<d:ItmNo>$itmNo</d:ItmNo>\n");
		array_push($this->mainXmlData, "<d:InvType></d:InvType>\n");		
        array_push($this->mainXmlData, "<d:PymtType>$detaildata[paytype]</d:PymtType>\n");
		array_push($this->mainXmlData, "<d:Currency>KWD</d:Currency>\n");
		array_push($this->mainXmlData, "<d:PlanAmount>$detaildata[cash]</d:PlanAmount>\n");
		array_push($this->mainXmlData, "<d:ActAmount>$detaildata[cash]</d:ActAmount>\n");    
        array_push($this->mainXmlData, "<d:CociReason></d:CociReason>\n");
        array_push($this->mainXmlData, "<d:Extfld1></d:Extfld1>\n<d:Extfld2></d:Extfld2>\n<d:Extfld3></d:Extfld3>\n<d:Extfld4></d:Extfld4>\n<d:Extfld5></d:Extfld5>\n<d:Extfld6></d:Extfld6>\n<d:Plant></d:Plant>\n</m:properties>\n");
        array_push($this->mainXmlData, "</atom:content>\n");
        array_push($this->mainXmlData, "</atom:entry>\n");

        if(sizeof($this->detailArray) > 0) {
            $this->getCheckinpaymentxml(++$l);
        } else {
            array_push($this->mainXmlData, "</atom:feed>\n");
            array_push($this->mainXmlData, "</m:inline>\n");
            array_push($this->mainXmlData, "</atom:link>\n");           
            array_push($this->mainXmlData, "</atom:entry>\n\n");
			array_push($this->mainXmlData,"</atom:feed>\n");
			array_push($this->mainXmlData,"</m:inline>\n");
			array_push($this->mainXmlData,"</atom:link>\n");
			array_push($this->mainXmlData,"</atom:entry>\n");
			//---------
			$curdate=date('Y-m-d') ."T" .date('h:i:s');
			$time =date("H:i:s");
								$curtime = explode(':', $time);
								$timeValue = "PT".$curtime[0]."H".$curtime[1]."M".$curtime[2]."S";
						array_push($this->mainXmlData, $this->routeXml ."");            
						array_push($this->mainXmlData, "<atom:link rel='http://schemas.microsoft.com/ado/2007/08/dataservices/related/TOUR_COCI2HDR' type='application/atom+xml;type=feed' title='TOUR_COCI2HDR'>\n<m:inline>\n<atom:feed>\n<atom:entry>\n<atom:content type='application/xml'>\n<m:properties>\n<d:TourId>$tourId</d:TourId>\n<d:CheckId>01</d:CheckId>\n<d:CheckDirect>1</d:CheckDirect>\n<d:Checker>Mirnah</d:Checker>\n<d:CheckDate>$curdate</d:CheckDate>\n<d:CheckTime>$timeValue</d:CheckTime>\n<d:CheckType>A</d:CheckType>\n<d:Extfld1></d:Extfld1>\n<d:Extfld2></d:Extfld2>\n<d:Extfld3></d:Extfld3>\n<d:Extfld4></d:Extfld4>\n<d:Extfld5></d:Extfld5>\n<d:Extfld6></d:Extfld6>\n<d:MeAction></d:MeAction>\n<d:Plant>1000</d:Plant>\n</m:properties>\n</atom:content>\n");
						array_push($this->mainXmlData, "<atom:link rel='http://schemas.microsoft.com/ado/2007/08/dataservices/related/COCI_HDR2ITM' type='application/atom+xml;type=feed' title='COCI_HDR2ITM'>\n");
						array_push($this->mainXmlData, "<m:inline>\n");
						array_push($this->mainXmlData, "<atom:feed>\n");	
						$this->getCheckoutData();
			 
        }
    }
		//-------------------------------------------------------------------
	
	
		//--------Check output
	 private function getCheckoutData() {
     //   echo "getCheckinData<br>";
        $param_array = array();
        $param_array[1] = $this->RouteKey;
        $queryResult = $this->SFA_Comman->executequery('CALL sp_int_export_getcheckoutdata()',$param_array);
        $l=1;
        $this->detailArray = $queryResult[0];
        $this->getCheckoutDataxml($l);
    }

    private function getCheckoutDataxml($l) {
      //  echo "getCheckinDataxml<br>";
        $detaildata = array_shift($this->detailArray);  
        $tourId = $this->TourId;
        $itmNo = $l * 10;

        array_push($this->mainXmlData, "<atom:entry>\n");
        array_push($this->mainXmlData, "<atom:content type='application/xml'>\n");
        array_push($this->mainXmlData, "<m:properties>\n<d:TourId>$tourId</d:TourId>\n");
        array_push($this->mainXmlData, "<d:CheckId>01</d:CheckId>\n");
        array_push($this->mainXmlData, "<d:ItmNo>$itmNo</d:ItmNo>\n");
        //array_push($this->mainXmlData, "<d:MatNo>$detaildata[0]</d:MatNo>\n");
        array_push($this->mainXmlData, "<d:MatNo>$detaildata[acode]</d:MatNo>\n");
        array_push($this->mainXmlData, "<d:MatNoR>$detaildata[acode]</d:MatNoR>\n");
        array_push($this->mainXmlData, "<d:Uom>$detaildata[memo]</d:Uom>\n");
		array_push($this->mainXmlData, "<d:Rotruck>$detaildata[trantype]</d:Rotruck>\n");
        //array_push($this->mainXmlData, "<d:PlanQty>$detaildata[2]</d:PlanQty>\n");
        array_push($this->mainXmlData, "<d:PlanQty>$detaildata[qty]</d:PlanQty>\n");
    //  array_push($this->mainXmlData, "<d:ActQty>$detaildata[2]</d:ActQty>\n");
        array_push($this->mainXmlData, "<d:ActQty>$detaildata[qty]</d:ActQty>\n");
        array_push($this->mainXmlData, "<d:CociReason></d:CociReason>\n");
        array_push($this->mainXmlData, "<d:Extfld1></d:Extfld1>\n<d:Extfld2></d:Extfld2>\n<d:Extfld3></d:Extfld3>\n<d:Extfld4></d:Extfld4>\n<d:Extfld5></d:Extfld5>\n<d:Extfld6></d:Extfld6>\n<d:Batch>DSDBATCH</d:Batch>\n<d:Bwtar></d:Bwtar>\n<d:Plant>1000</d:Plant>\n<d:SpecReturn></d:SpecReturn>\n</m:properties>\n");
        array_push($this->mainXmlData, "</atom:content>\n");
        array_push($this->mainXmlData, "</atom:entry>\n");

        if(sizeof($this->detailArray) > 0) {
            $this->getCheckoutDataxml(++$l);
        } else {
            array_push($this->mainXmlData, "</atom:feed>\n");
            array_push($this->mainXmlData, "</m:inline>\n");
            array_push($this->mainXmlData, "</atom:link>\n");
            array_push($this->mainXmlData, "<atom:link rel='http://schemas.microsoft.com/ado/2007/08/dataservices/related/COCI_HDR2PAY' type='application/atom+xml;type=feed' title='COCI_HDR2PAY'>\n<m:inline>\n<atom:feed />\n</m:inline>\n</atom:link>\n</atom:entry>\n</atom:feed>\n</m:inline>\n</atom:link>\n");
            array_push($this->mainXmlData, "</atom:entry>\n\n");
			//$this->getVisitData();	
			//-----------------
			$param_array = array();
			$param_array[1] = $this->RouteKey;
			$queryResult = $this->SFA_Comman->executequery('CALL sp_int_export_gettranscationdetail()',$param_array);
			if(count($queryResult)>0)
			{
				$this->getVisitData();
			
			}else{
				
				array_push($this->mainXmlData, "--changeset_005056A5-09B1-1ED1-BF82-409B26A80301--\n");
				array_push($this->mainXmlData, "--batch_005056A5-09B1-1ED1-BF82-409B26A80300--\n");
				$this->mainXmlData = join("", $this->mainXmlData);

				$this->postSAPDBData($this->URL, $this->uidSAP, $this->pwdSAP,$this->mainXmlData);
				//  echo "<br>Finished<br>";

					$this->mainXmlData = "";
					$this->headerArray = "";
					$this->detailArray = "";
				
			}
			//-------------			
            
        }
    }

	//-------End
    //-----------Collection against inv
    private function getcollectinginvData() {
       // echo "getcollectinginvData<br>";
        $param_array = array();
        $param_array[1] = $this->RouteKey;
        $queryResult = $this->SFA_Comman->executequery('CALL sp_int_export_getcollectinginvdata()',$param_array);
        $l=1;
        $this->detailArray = $queryResult[0];
        $this->getcollectinginvDataxml($l);
    }

    private function getcollectinginvDataxml($l) {
      //  echo "getcollectinginvDataxml<br>";

        $detaildata = array_shift($this->detailArray);
        $tourId = $this->TourId;

        array_push($this->mainXmlData, "<atom:entry>\n");
        array_push($this->mainXmlData, "<atom:content type='application/xml'>\n");
        array_push($this->mainXmlData, "<m:properties>\n<d:TourId>$tourId</d:TourId>\n");
        //array_push($this->mainXmlData, "<d:VisitId>$detaildata[4]</d:VisitId>\n");
        array_push($this->mainXmlData, "<d:VisitId>$detaildata[seq]</d:VisitId>\n");
        array_push($this->mainXmlData, "<d:ActiId>$detaildata[row_number]</d:ActiId>\n");
        array_push($this->mainXmlData, "<d:CashId>$detaildata[row_number]</d:CashId>\n");
        array_push($this->mainXmlData, "<d:CustNo>$detaildata[CODE]</d:CustNo>\n");
        array_push($this->mainXmlData, "<d:CustNoT>$detaildata[CODE]</d:CustNoT>\n");
    //  array_push($this->mainXmlData, "<d:DocNo>00$detaildata[2]</d:DocNo>\n");
    //  array_push($this->mainXmlData, "<d:DocNo>00$detaildata[invno]</d:DocNo>\n");
        array_push($this->mainXmlData, "<d:DocNo>$detaildata[invno]</d:DocNo>\n");
    //  array_push($this->mainXmlData, "<d:DocType>$detaildata[0]</d:DocType>\n");
        array_push($this->mainXmlData, "<d:DocType>$detaildata[TYPE]</d:DocType>\n");
        array_push($this->mainXmlData, "<d:Discount>$detaildata[amounttype]</d:Discount>\n");
    //  array_push($this->mainXmlData, "<d:AssAmount>$detaildata[3]</d:AssAmount>\n");
        array_push($this->mainXmlData, "<d:AssAmount>$detaildata[amountpaid]</d:AssAmount>\n");
        array_push($this->mainXmlData, "<d:Extfld1></d:Extfld1>\n<d:Extfld2></d:Extfld2>\n<d:Extfld3></d:Extfld3>\n<d:Extfld4></d:Extfld4>\n<d:Extfld5></d:Extfld5>\n<d:Extfld6></d:Extfld6>\n<d:CompCode></d:CompCode>\n<d:FiscYear></d:FiscYear>\n<d:CollectId></d:CollectId>\n<d:DocId>$detaildata[invno]</d:DocId>\n<d:Buzei>001</d:Buzei>\n</m:properties>\n");
        array_push($this->mainXmlData, "</atom:content>\n");
        array_push($this->mainXmlData, "</atom:entry>\n");

        if(sizeof($this->detailArray) > 0) {
            $this->getcollectinginvDataxml(++$l);
        } else {
            array_push($this->mainXmlData, "</atom:feed>\n");
            array_push($this->mainXmlData, "</m:inline>\n");
            array_push($this->mainXmlData, "</atom:link>\n");
            array_push($this->mainXmlData, "</atom:entry>\n\n");
            array_push($this->mainXmlData, $this->routeXml ."");
            $curdate=date('Y-m-d') ."T" .date('h:i:s');
			$time =date("H:i:s");
			$curtime = explode(':', $time);
			$timeValue = "PT".$curtime[0]."H".$curtime[1]."M".$curtime[2]."S";
            array_push($this->mainXmlData, "<atom:link rel='http://schemas.microsoft.com/ado/2007/08/dataservices/related/TOUR_COCI2HDR' type='application/atom+xml;type=feed' title='TOUR_COCI2HDR'>\n<m:inline>\n<atom:feed>\n<atom:entry>\n<atom:content type='application/xml'>\n<m:properties>\n<d:TourId>$tourId</d:TourId>\n<d:CheckId>02</d:CheckId>\n<d:CheckDirect>2</d:CheckDirect>\n<d:Checker>Mirnah</d:Checker>\n<d:CheckDate>$curdate</d:CheckDate>\n<d:CheckTime>$timeValue</d:CheckTime>\n<d:CheckType>A</d:CheckType>\n<d:Extfld1></d:Extfld1>\n<d:Extfld2></d:Extfld2>\n<d:Extfld3></d:Extfld3>\n<d:Extfld4></d:Extfld4>\n<d:Extfld5></d:Extfld5>\n<d:Extfld6></d:Extfld6>\n<d:MeAction></d:MeAction>\n<d:Plant>1000</d:Plant>\n</m:properties>\n</atom:content>\n");
            array_push($this->mainXmlData, "<atom:link rel='http://schemas.microsoft.com/ado/2007/08/dataservices/related/COCI_HDR2ITM' type='application/atom+xml;type=feed' title='COCI_HDR2ITM'>\n");
            array_push($this->mainXmlData, "<m:inline>\n");
            array_push($this->mainXmlData, "<atom:feed>\n");
            $this->getCheckinData();

        }
    }
//-------------Visit
 private function getVisitData() {
      //  echo "getInvHeaderData<br>";
        $param_array = array();
        $param_array[1] = $this->RouteKey;
        $queryResult = $this->SFA_Comman->executequery('CALL sp_int_export_getvisitseqdata()',$param_array);
        $j = 1;
        $this->headerArray = $queryResult[0];
        $this->getVisitDataXML($j);
    }

    private function getVisitDataXML($j){
        //echo "getInvHeaderDataXML<br>";
        $headerData = array_shift($this->headerArray);
        $tourId = $this->TourId;
        $routeCode = $this->RouteCode;
        //$invNo = "0" .$routeCode ."0" .$j;
		$invNo = $headerData[invoicenumber];

        $myArray = explode(':', $headerData[transactiontime]);
        $timeValue = "PT".$myArray[0]."H".$myArray[1]."M".$myArray[2]."S";
		array_push($this->mainXmlData, $this->routeXml ."");
        array_push($this->mainXmlData, "<atom:link rel='http://schemas.microsoft.com/ado/2007/08/dataservices/related/TOUR_VISIT2HDR' type='application/atom+xml;type=feed' title='TOUR_VISIT2HDR'>\n");
        array_push($this->mainXmlData, "<m:inline>\n");
        array_push($this->mainXmlData, "<atom:feed>\n");
        array_push($this->mainXmlData, "<atom:entry>\n");
        array_push($this->mainXmlData, "<atom:content type='application/xml'>\n");
        array_push($this->mainXmlData, "<m:properties>\n<d:TourId>$tourId</d:TourId>\n");
        //array_push($this->mainXmlData, "<d:VisitId>$headerData[8]</d:VisitId>\n");
        array_push($this->mainXmlData, "<d:VisitId>$headerData[seq]</d:VisitId>\n");
        array_push($this->mainXmlData, "<d:AseqId>$headerData[seq]</d:AseqId>\n");
        array_push($this->mainXmlData, "<d:CustNo>$headerData[customercode]</d:CustNo>\n");
        array_push($this->mainXmlData, "<d:CustNoR>$headerData[customercode]</d:CustNoR>\n");
        array_push($this->mainXmlData, "<d:Cpd></d:Cpd>\n<d:ActStatReas></d:ActStatReas>\n<d:HhCreate></d:HhCreate>\n<d:VaStat></d:VaStat>\n<d:PstartTime>$timeValue</d:PstartTime>\n<d:PendTime>$timeValue</d:PendTime>\n<d:Mile></d:Mile>\n<d:Uod></d:Uod>\n<d:DistType></d:DistType>\n<d:TimeType></d:TimeType>\n<d:Extfld1></d:Extfld1>\n<d:Extfld2></d:Extfld2>\n<d:Extfld3></d:Extfld3>\n<d:Extfld4></d:Extfld4>\n<d:Extfld5></d:Extfld5>\n<d:Extfld6></d:Extfld6>\n<d:MeAction></d:MeAction>\n<d:Parvw></d:Parvw>\n<d:Parnr></d:Parnr>\n<d:UnlPoint></d:UnlPoint>\n<d:Timfr1>$timeValue</d:Timfr1>\n<d:Timto1>$timeValue</d:Timto1>\n<d:Timfr2>$timeValue</d:Timfr2>\n<d:Timto2>$timeValue</d:Timto2>\n<d:CustNoSa></d:CustNoSa>\n</m:properties>\n");
        array_push($this->mainXmlData, "</atom:content>\n");
        array_push($this->mainXmlData, "</atom:entry>\n");
        array_push($this->mainXmlData, "</atom:feed>\n");
        array_push($this->mainXmlData, "</m:inline>\n");		
        array_push($this->mainXmlData, "</atom:link>\n");
		 array_push($this->mainXmlData, "</atom:entry>\n\n");
       

        if(sizeof($this->headerArray) > 0) {
               $this->getVisitDataXML(++$j);
        } else {

           
			array_push($this->mainXmlData, "--changeset_005056A5-09B1-1ED1-BF82-409B26A80301--\n");
            array_push($this->mainXmlData, "--batch_005056A5-09B1-1ED1-BF82-409B26A80300--\n");
            $this->mainXmlData = join("", $this->mainXmlData);

            $this->postSAPDBData($this->URL, $this->uidSAP, $this->pwdSAP,$this->mainXmlData);
          //  echo "<br>Finished<br>";

            $this->mainXmlData = "";
            $this->headerArray = "";
            $this->detailArray = "";
            /*array_push($this->mainXmlData, $this->routeXml ."");
            array_push($this->mainXmlData, "<atom:link rel='http://schemas.microsoft.com/ado/2007/08/dataservices/related/TOUR2COLL_PAYM' type='application/atom+xml;type=feed' title='SOItems'>\n");
            array_push($this->mainXmlData, "<m:inline>\n");
            array_push($this->mainXmlData, "<atom:feed>\n");
            $this->getCashDetailData();*/
            // getInvDetailData($headerData[2],$j); //check why this is not calling at this point.
        }
    }
    /*
     * Function Name    : postSAPDBData
     * Params           : $url - url to get the SAP Data
     * Params           : $username - User id for SAP
     * Params           : $password - password for SAP
     * Descripton       : Connect to SAP and post the XML data
     */
    private function postSAPDBData($url, $username, $password, $xml_data) {
        //echo "postSAPDBData <br>";
        $postData = "--batch_005056A5-09B1-1ED1-BF82-409B26A80300\n";
        $postData = $postData ."Content-Type: multipart/mixed; boundary=changeset_005056A5-09B1-1ED1-BF82-409B26A80301";
        $postData = $postData ."\n\n" .$xml_data;

         // Save file for verification enable below comments
		 $path   = str_replace('//','/',$_SERVER['DOCUMENT_ROOT'].'/upload/');
         $my_file = $path.$this->TourId.'.xml';
         $handle = fopen($my_file, 'w') or die('Cannot open file:  '.$my_file); //implicitly creates file
         fwrite($handle,$postData);
         fclose($handle);

        // to print within the html page enable below comments.
        //$dataval = htmlspecialchars($postData);
        //echo "XML Data : " .$dataval ."<br>";

        $headers = $this->getSapHeaders($url, $username, $password);
        $csrfToken = "";
        $cookie = "";
        foreach($headers AS $k => $v){
            $key = strtolower($k);
            if ($key == strtolower("Set-Cookie")) {
                $cookie = $v;
            } elseif ($key == strtolower("X-CSRF-Token")) {
                $csrfToken = $v;
            }
            else {
                // handle
            }
        }
       // echo "Cookie : " .$cookie ."<br>";
       // echo "CSRF Token : " .$csrfToken ."<br>";

        //  to load from file and test enable below comments.
        //$xml_data = file_get_contents ("OriginalNew.xml");
        //$xml_data = htmlspecialchars($xml_data);
        //echo "XML Data UploadFile: " .$xml_data ."<br>";

        $host = parse_url($url, PHP_URL_HOST);
        $headerArr = array();
        $headerArr[] = "x-csrf-token:" .$csrfToken;
        $headerArr[] = "X-Requested-With:XMLHttpRequest";
        $headerArr[] = "Content-Type:multipart/mixed; boundary=batch_005056A5-09B1-1ED1-BF82-409B26A80300";
        $headerArr[] = "Content-Length;" .strlen($postData);
        $headerArr[] = "Host:".$host;
        $headerArr[] = "Cookie:" .$cookie;
        $headerArr[] = "X-SMP-SC:Mirnah_Sec";
        $headerArr[] = "X-SMP-APPCID:229edb35-f7b2-4a3b-9ce3-ab706102f35c";
        $headerArr[] = "Accept:application/xml,application/atom+xml";
        $headerArr[] = "DataServiceVersion: 2.0;";

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        curl_setopt($ch, CURLOPT_USERPWD, "$username:$password");
        curl_setopt($ch, CURLOPT_SSLVERSION,3);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headerArr);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_URL, $url);

        $result = curl_exec($ch);
        if(curl_errno($ch)){
           // echo 'Curl error: ' . curl_error($ch) ."<br>";
        }
		//print_r($result);
		//	echo "Result: <br>".print_r($result)."<br>";

        $info = curl_getinfo($ch);
  

   
        if($info['http_code'] == '202') {
           //$this->setStatus(TRUE);;
           $this->updateStatusFlags();
        }
			$param_array = array();
			   $param_array[1] =  $this->TourId;	
			   $param_array[2] = $info['http_code'];
			   $param_array[3] = base64_encode($result);
			   $param_array[4] = 'STL';
			 $queryResult = $this->SFA_Comman->executequery('CALL  sp_int_export_orderlog()',$param_array);
		
        curl_close($ch);
    }

    private function updateStatusFlags() {
        //echo "updateStatusFlags <br>";
        $sql = "UPDATE tbl_touridstatus SET exportstatus = '1' WHERE tourid = '$this->TourId'";
        $this->executeSQLQuery($sql);

        $param_array = array();
        $param_array[1] = $this->RouteCode;
        $queryResult = $this->SFA_Comman->executequery('CALL sp_int_export_getroutekey()',$param_array);
        foreach($queryResult[0] as $row) {
            $routeKey = $row['routekey'];
            if ($routeKey > 0) {
                $sql = "UPDATE startendday SET exportedflag = '1' where routecode = '$this->RouteCode' and routekey = '$routeKey'";
                $this->executeSQLQuery($sql);  
				$param_array = array();
				$param_array[1] = $this->RouteCode;
				$queryResult = $this->SFA_Comman->executequery('CALL sp_int_export_tmpcustomerinvoice()',$param_array);
            }
        }
    }

    private function http_parse_headers($rawHeaders) {
        $headers = "";
        foreach (explode("\n", $rawHeaders) as $i => $h) {
            $h = explode(':', $h, 2);
            if (isset($h[1])) {
                $headers[$h[0]] = trim($h[1]);
            }
        }
        return $headers;
    }

    private function getSapHeaders($url, $username, $password) {
        $hostAddr = parse_url($url, PHP_URL_HOST);
        $csrfHeaderArr = array();
        $csrfHeaderArr[] = 'Content-Type : application/xml';
        $csrfHeaderArr[] = 'Host :' .$hostAddr;
        $csrfHeaderArr[] = 'X-CSRF-Token:Fetch';
        $csrfHeaderArr[] = 'X-Requested-With:XMLHttpRequest';
        $csrfHeaderArr[] = 'X-SMP-SC:Mirnah_Sec';
        $csrfHeaderArr[] = 'X-SMP-APPCID:229edb35-f7b2-4a3b-9ce3-ab706102f35c';
        $csrfHeaderArr[] = "DataServiceVersion: 2.0;";

        $curl_get = curl_init();
        curl_setopt_array($curl_get, array(
            CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_URL => $url,
            CURLOPT_USERPWD => "$username:$password",
            CURLOPT_HEADER => true,
            CURLOPT_HTTPHEADER => $csrfHeaderArr,
        ));
        $rawHeaders = curl_exec($curl_get);
        curl_close($curl_get);
        $headers = $this->http_parse_headers($rawHeaders);
        return $headers;
    }
}
?>