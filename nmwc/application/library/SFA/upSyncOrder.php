<?php
/*
 * FileName     : upSyncOrder.php
 * Owner        : v.nair@mirnah.com
 * Created      : 09/03/2015
 * Description  : Getting data tables from DB and Updating SAP 
 */
class SFA_upSyncOrder {

    private $URL = 'http://172.16.1.37:8000/sap/opu/odata/sap/ZDSDG_DRIVER_ORDER_SRV/';
	private $uidSAP = "DRIVERRFC";
    private $pwdSAP = "ABCD1234@";

    private $routeXml = array();
    private $mainXmlData  = array();
    private $SOItemsArray = array();
    private $SOHeadersArray = array();
    private $finalXml = "";

    private $TourId = "";
    private $RouteCode = "";
    private $Route = "";

    public function __construct() {
      //  echo "testing construct : <br>";
        set_time_limit(0);
    }

    public function exportOrder($tourID) {
      //  echo "exportOrder<br>";
        $this->setGlobalValues($tourID);
        $this->createDriverXml();
    }
    private function setGlobalValues($tourID) {
        //echo "setGlobalValues<br>";

        $this->SFA_Comman = new SFA_Comman();
        $this->TourId = $tourID;
		
		$sql = "select routekey from startendday where tourid = '$tourID'";
        $this->RouteKey = $this->getQueryResultRow($sql,'routekey');

        $sql = "select routecode from routemaster where memo1 = '$tourID'";
        $this->RouteCode = $this->getQueryResultRow($sql,'routecode');

        $sql = "select templatename from routemaster where memo1 = '$tourID'";
        $this->Route = $this->getQueryResultRow($sql,'templatename');
    }

 /*
     * Function Name    : executeSQLQuery
     * Params           :
     * Params           : $sql - the MySQL statement to be executed
     * Descripton       : Will query to the DB and display error if occured
     */
    private function executeSQLQuery($sql) {
        //echo "executeSQLQuery : <br>";
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
        //echo "getQueryResultRow : <br>";
        $val = "";
        $result = $this->executeSQLQuery($sql);
        if($result != "") {
            $val = $result[0][0][$value];
        }
        return $val;
    }

    private function createDriverXml() {
        //echo "createDriverXml<br>";
        $param_array = array();
         $param_array[1] = $this->RouteKey;
        $queryResult = $this->SFA_Comman->executequery('CALL sp_int_export_getSOHeaders()',$param_array);
        $this->SOHeadersArray = $queryResult[0];
        while(sizeof($this->SOHeadersArray) > 0) {
            $headerData = array_shift($this->SOHeadersArray);
            $this->getSOHeadersXML($headerData);
            break; 
        }
        $this->mainXmlData = join("", $this->mainXmlData);

        $this->postSAPDBData($this->URL, $this->uidSAP, $this->pwdSAP,$this->mainXmlData);
         $my_file = 'phpDriverUpload.xml';
         $handle = fopen($my_file, 'w') or die('Cannot open file:  '.$my_file); //implicitly creates file
         fwrite($handle,$this->mainXmlData);
         fclose($handle);

        $this->mainXmlData = "";
        $this->SOHeadersArray = "";
        $this->SOItemsArray = "";
    }

    private function getSOHeadersXML($headerData) {
       // echo "getSOHeadersXML<br>";
        
        $myArray = explode(' ', $headerData[requestdate]);
        $DocumentDate = $myArray[0] ."T". $myArray[1];

        array_push($this->mainXmlData, "<?xml version=\"1.0\" encoding=\"utf-8\"?>\n");
        array_push($this->mainXmlData, "<atom:entry xml:base=\"http://vsECI606.pro.coil:8000/sap/opu/sdata/MIRNAH/DRIVER_ORDER_SRV/\" xmlns:atom=\"http://www.w3.org/2005/Atom\" xmlns:d=\"http://schemas.microsoft.com/ado/2007/08/dataservices\" xmlns:m=\"http://schemas.microsoft.com/ado/2007/08/dataservices/metadata\" xmlns:sap=\"http://www.sap.com/Protocols/SAPData\">\n");
        array_push($this->mainXmlData, "<atom:author/>\n");
        array_push($this->mainXmlData, "<atom:category term=\"DRIVER_ORDER_SRV.SOHeader\" scheme=\"http://schemas.microsoft.com/ado/2007/08/dataservices/scheme\"/>\n");
        array_push($this->mainXmlData, "<atom:content type='application/xml'>\n");
        array_push($this->mainXmlData, "<m:properties>\n<d:OrderId></d:OrderId>\n<d:DocumentType>ZVDL</d:DocumentType>\n<d:DocumentDate m:type=\"Edm.DateTime\">$DocumentDate</d:DocumentDate>\n<d:CustomerId>$headerData[alternatesalesmancode]</d:CustomerId>\n<d:SalesOrg>1000</d:SalesOrg>\n<d:DistChannel>20</d:DistChannel>\n<d:Division>$headerData[division]</d:Division>\n<d:PurchaseNum>$this->TourId</d:PurchaseNum>\n<d:OrderValue m:type=\"Edm.Decimal\">0.00</d:OrderValue>\n<d:Currency>KWD</d:Currency>\n</m:properties>\n");
        array_push($this->mainXmlData, "</atom:content>\n");
        array_push($this->mainXmlData, "<atom:id>http://vsECI606.pro.coil:8000/sap/opu/sdata/MIRNAH/DRIVER_ORDER_SRV/SOHeaders()</atom:id>\n");
        array_push($this->mainXmlData, "<atom:link href=\"SOHeaders()\" rel=\"edit\" type=\"application/atom+xml;type=entry\"/>\n");
        array_push($this->mainXmlData, "<atom:link href=\"SOHeaders()/SOItems\" rel=\"http://schemas.microsoft.com/ado/2007/08/dataservices/related/SOItems\" type=\"application/atom+xml;type=feed\" title=\"SOItems\">\n");
        array_push($this->mainXmlData, "<m:inline>\n");
        array_push($this->mainXmlData, "<atom:feed xml:base='" .$this->URL ."'>\n");
        array_push($this->mainXmlData, "<atom:id>" .$this->URL ."SOItems</atom:id>\n");
        array_push($this->mainXmlData, "<atom:link href='SOItems' rel='self' type='application/atom+xml;type=feed' title='SOItems'/>\n");
        array_push($this->mainXmlData, "<atom:title>SOItems</atom:title>\n");
        array_push($this->mainXmlData, "<atom:updated>2015-03-05T12:20:23Z</atom:updated>\n");
        $docnumber="";
        $this->getSOItems($headerData[detailkey],$headerData[documentnumber]);
        
        array_push($this->mainXmlData, "</atom:feed>\n");
        array_push($this->mainXmlData, "</m:inline>\n");
        array_push($this->mainXmlData, "</atom:link>\n");
        array_push($this->mainXmlData, "<atom:title>SOHeader</atom:title>\n");
        array_push($this->mainXmlData, "<atom:updated>2015-03-05T12:20:23Z</atom:updated>\n");
        array_push($this->mainXmlData, "</atom:entry>\n");
    }
    
    private function getSOItems($detailkey,$orderid) {
		$docnumber=$orderid;
        //echo "getSOItems<br>";
        $param_array = array();
        $param_array[1] = $detailkey;
        $queryResult = $this->SFA_Comman->executequery('CALL sp_int_export_getSOItems()',$param_array);
        $this->SOItemsArray = $queryResult[0];
        $count = 1;
        while(sizeof($this->SOItemsArray) > 0) {
            $detailData = array_shift($this->SOItemsArray);
            $this->getSOItemsXML($detailData,$orderid,$count++);
        }
    }

    private function getSOItemsXML($detailData,$orderid,$itemCode) {
        //echo "getSOItemsXML<br>";
		$itemCode = $itemCode * 10;
        $itemCode = str_pad($itemCode,6,'0',STR_PAD_LEFT);
		//$a="JOHNDOESMITH";   
		//$b=str_pad($itemCode,6,'X',STR_PAD_LEFT);
		//$itemCode = $itemCode * 10;
        $value = $detailData[quantity] * $detailData[itemprice];

        array_push($this->mainXmlData, "<atom:entry>\n");
        array_push($this->mainXmlData, "<atom:author/>\n");
        array_push($this->mainXmlData, "<atom:category term='DRIVER_ORDER_SRV.SOItem' scheme='http://schemas.microsoft.com/ado/2007/08/dataservices/scheme'/>\n");
        array_push($this->mainXmlData, "<atom:content type='application/xml'>\n");
        array_push($this->mainXmlData, "<m:properties>\n<d:OrderId></d:OrderId>\n<d:Item>$itemCode</d:Item>\n<d:Material>$detailData[alternatecode]</d:Material>\n<d:Description></d:Description>\n<d:Plant>1000</d:Plant>\n<d:Quantity m:type=\"Edm.Decimal\">$detailData[quantity]</d:Quantity>\n<d:ItemValue m:type=\"Edm.Decimal\">0.0</d:ItemValue>\n<d:UoM>$detailData[memo1]</d:UoM>\n<d:Value m:type=\"Edm.Decimal\">0.0</d:Value>\n<d:Storagelocation>1000</d:Storagelocation>\n<d:Route>$this->Route</d:Route>\n</m:properties>\n");
        array_push($this->mainXmlData, "</atom:content>\n");
        array_push($this->mainXmlData, "<atom:id>http://vsECI606.pro.coil:8000/sap/opu/sdata/MIRNAH/DRIVER_ORDER_SRV/SOItems(OrderId='',Item='$itemCode')</atom:id>\n");
        array_push($this->mainXmlData, "<atom:link href=\"SOItems(OrderId='',Item='$itemCode')\" rel=\"edit\" type=\"application/atom+xml;type=entry\"/>\n");
        array_push($this->mainXmlData, "<atom:link href=\"SOItems(OrderId='',Item='$itemCode')/SOHeader\" rel=\"http://schemas.microsoft.com/ado/2007/08/dataservices/related/SOHeader\" type=\"application/atom+xml;type=entry\" title=\"SOHeader\"/>\n");
        array_push($this->mainXmlData, "<atom:title>SOItem</atom:title>\n");
        array_push($this->mainXmlData, "<atom:updated>2015-03-05T12:20:23Z</atom:updated>\n");
        array_push($this->mainXmlData, "</atom:entry>\n");
    }

    /*
     * Function Name    : postSAPDBData
     * Params           : $url - url to get the SAP Data
     * Params           : $username - User id for SAP
     * Params           : $password - password for SAP
     * Descripton       : Connect to SAP and post the XML data
     */
    private function postSAPDBData($url, $username, $password, $xml_data) {
       // echo "postSAPDBData <br>";
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
        //$xml_data = file_get_contents ("DriverInsert.xml");

        $host = parse_url($url, PHP_URL_HOST);
        $headerArr = array();
        $headerArr[] = "x-csrf-token:" .$csrfToken;
        $headerArr[] = "X-Requested-With:XMLHttpRequest";
        $headerArr[] = "Content-Type:application/xml";
        $headerArr[] = "Content-Length:" .strlen($xml_data);
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
        curl_setopt($ch, CURLOPT_POSTFIELDS, $xml_data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_URL, $url."SOHeaders");

        $result = curl_exec($ch);
        if(curl_errno($ch)){
            echo 'Curl error: ' . curl_error($ch) ."<br>";
        }
    //echo "<br>Result: <br>".print_r($result)."<br>";

        $info = curl_getinfo($ch);
  //echo "<br>Info: <br>";print_r($info);//echo "<br>";
		if($info['http_code'] == '201')
		{	 $param_array = array();
			 $param_array[1] = $docnumber;
			 $queryResult = $this->SFA_Comman->executequery('CALL sp_int_export_update_invheader()',$param_array);		
			
		}
      //echo  "<br>Http Response Code is  :  " .$info['http_code'] ."<br>";
			 $param_array = array();
			   $param_array[1] =  $this->TourId;
			   $param_array[2] = $info['http_code'];
			   $param_array[3] = base64_encode($result);
			   $param_array[4] = 'ORD';
			 $queryResult = $this->SFA_Comman->executequery('CALL sp_int_export_orderlog()',$param_array);
	 
        curl_close($ch);
    }

    private function http_parse_headers($rawHeaders) {
        $headers = "";
     //  print_r($rawHeaders);echo "<br><br>";
        foreach (explode("\n", $rawHeaders) as $i => $h) {
            $h = explode(':', $h, 2);
            if (isset($h[1])) {
                $headers[$h[0]] = trim($h[1]);
            }
        }
       // print_r($headers);echo "<br><br>";
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
        $csrfHeaderArr[] = 'DataServiceVersion: 2.0;';

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
        if(curl_errno($ch)){
            echo 'Curl error: ' . curl_error($ch) ."<br>";
        }
        curl_close($curl_get);
        $headers = $this->http_parse_headers($rawHeaders);
        return $headers;
    }
}
?>