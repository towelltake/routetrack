<?php
//error_reporting(E_ALL);
//ini_set('display_errors', '1');
/**
 * @package   SFA_Core_Library
 * @version    $Id: Comman.php
 * For the Global Varaibles and Site Comman Function Throught out the Site.
 */
class SFA_Comman
{
    /**
     * For the SMTP Host Address
     * @var SMTP Address
     */

    private $SmtpMailAddress="mail.gmail.com";

    /**
     * For the SMTP AUTH
     * @var SMTP AUTH
     */

    private $SmtpAUTHDet=array('auth' => 'Login',
            'username' => '',
            'password' => '',
            'email' => 'info@routepro.com');
    /**
     * For the Site URL Address
     * @var Site URL Address
     */
    private $SiteUrlAddress = "";
    /**
     * For the Site URL Address
     * @var Site URL Address
     */
    private $SiteBaseUrl = "";
    /**
     * For the Site Data adapter
     * @var Site Data adapter
     */
    private $db = "";

    public $adserver;
    private $ociConn = null;



    public function __construct()
    {
        $this->SetSiteUrlAddress();
    }


    //HC - set the Base URl and site url
    public function SetSiteUrlAddress()
    {
    $pageURL = 'http';
    if (strpos(strtolower($_SERVER["SERVER_PROTOCOL"]),"https")!==false) {$pageURL .= "s";}
        $pageURL .= "://";

    $pageURL .= $_SERVER["HTTP_HOST"];
    $REQUESTURI=explode("/",$_SERVER['REQUEST_URI']);
    $SCRIPTNAME=explode("/",$_SERVER['SCRIPT_NAME']);
    $newurladd=array_intersect_assoc($REQUESTURI, $SCRIPTNAME);
    if(count($newurladd) > 1)
    {
        $baseurlset=implode("/",$newurladd)."/";
    }
    else
    {
        $baseurlset="/";
    }
    $this->SiteBaseUrl=$baseurlset;
    $this->SiteUrlAddress=$pageURL.$baseurlset;
    }

    function getSmtpAddressUser()
    {
    return array($this->SmtpMailAddress,$this->SmtpAUTHDet);
    }
    function getSiteBaseUrl()
    {
    return $this->SiteBaseUrl;
    }
    function getdate($date)
    {
    $date_arr     = explode("/",$date);
    $date         = date("Y-m-d", mktime(0, 0, 0, $date_arr[1], $date_arr[0], $date_arr[2]));
    return $date;
    }
    function pre($arr = array())
    {
	echo '<pre>';
    print_r($arr);
	echo '</pre>';
    exit;

    }

    //HC - Return whole url Address of Site
    public function getSiteUrlAddress()
    {
    return $this->SiteUrlAddress;
    }
    public function ociConnect() {
       // echo str_repeat(" ",1024*1024*4) ."ociConnect!<br>";ob_flush();
        if($this->ociConn == null) {
			         
			// $this->ociConn = oci_connect('routepro', 'password123', '(DESCRIPTION = (ADDRESS = (PROTOCOL = TCP)(HOST = 192.168.0.3)(PORT = 1521))(CONNECT_DATA = (SERVICE_NAME = PROD)))');
			$this->ociConn = oci_connect('routepro', 'password123', '(DESCRIPTION=(ADDRESS=(PROTOCOL=TCP)(HOST=192.168.22.46)(PORT=1521))(CONNECT_DATA=(SERVICE_NAME=PROD)))');
			
			//$this->ociConn = oci_connect('STAGING', 'STAGING', '192.168.1.39');
			
			$m = oci_error();
		
            if (!$this->ociConn) {
				$m = oci_error();
				//echo "oci_connect Error" .htmlentities($m['message']) ."<br>";
				$this->logOciError($m);
				//exit;
            }
            else {
				//echo "Connected to Enhance!<br><br>";
				//exit;
            }
        }
        return $this->ociConn;
    }
    public function ociClose() {
        // echo str_repeat(" ",1024*1024*4) ."ociClose!<br>";ob_flush();
        if($this->ociConn) { 
            oci_close($this->ociConn);
            $this->ociConn = null;
        }
    }
    private function logOciError($m) {
        $param_array = array();
        $param_array[1] = $m['code'];
        $param_array[2] = $m['message'];
        //$param_array[3] = substr($m['sqltext'],0,500);
		$param_array[3] = str_replace("'", "\'", $m['sqltext']);
					/*$to       = 'vin2bhagat@gmail.com';
					$subject  = 'Special Price Date Expiry!';
					$message  = '';
					$message = '<html><body>';
					$message .= '<h1>Following Pricing Key Going To Expiry From Tomorrow Kindly Update..</h1>';
					$message .= '<table rules="all" style="border-color: #666;" cellpadding="10">';
					$message .= "<tr style='background: #eee;'><td><strong>Pricing Key :</strong> </td><td><strong>Start Date</strong></td><td><strong>Expiry Date</strong></td></tr>";
					 foreach($result[0] as $row){
					$message .= "<tr><td>".$m['code']." </td><td>" . $m['message'] . "</td><td>" . $m['sqltext'] . "</td></tr>";
					 }
					$message .= "</table>";
					$message .= "</body></html>";
					$headers  = 'From: support@mirnah.com' . "\r\n" .
						'MIME-Version: 1.0' . "\r\n" .
						'Content-type: text/html; charset=utf-8';
					if(mail($to, $subject, $message, $headers))
					{
						echo "Email sent";
					}	
						else
					{	
						echo "Email sending failed";
					}*/
		//str_replace(' ', '_', $text)
        $this->executequery('CALL int_sp_exp_error_logging()', $param_array);
    }
    public function ociexecutequery($sp_statement) {
         //echo str_repeat(" ",1024*1024*4) ."ociexecutequery!<br><br>";ob_flush();
        // echo "connection - ";print_r($this->ociConn);echo "<br>";
        if($this->ociConn == null) {
            $this->ociConn = $this->ociConnect();
        }
       // printf("<p>Veena Test : Query : %s</p>" , $sp_statement);
        $parseSP = oci_parse($this->ociConn, $sp_statement);
        if (!$parseSP) {
           $m = oci_error($this->ociConn);
           //echo "oci_parse Error : " .$m['message'] ."<br>";
           $this->logOciError($m);
        }
        $result = oci_execute($parseSP);
        if (!$result) {
           $m = oci_error($parseSP);
           //echo "oci_execute Error : " .$m['message'] ."<br>";
           $this->logOciError($m);
        }
        $content = array();
        $i = 0;
        while($row=oci_fetch_array($parseSP)) {
            $content[$i][] = $row;
            $i++;
            //echo str_repeat(" ",1024*1024*4) ."Query Result <br>";print_r($row);//echo "<br>";ob_flush();
        }
        return $content;
    }
    public function executequery($sp_statement,$param_array = array(),$query_type = '', $exportCsv = 0,$printData = 0,$printformat, $pagingParams = array())
    {
        // Hiren Dave on 26th July
        // Below code for accesing the variables from the config file.
        
        $db_name     = Zend_Registry::get('config')->resources->multidb->front_db->dbname;
        $username     = Zend_Registry::get('config')->resources->multidb->front_db->username;
        $password     = Zend_Registry::get('config')->resources->multidb->front_db->password;
        $host         = Zend_Registry::get('config')->resources->multidb->front_db->host;
		$port         = Zend_Registry::get('config')->resources->multidb->front_db->port;
        
        // Mayur Bhayani on 23 July, 2012 - START - To remove limit and add limit to 10 lac.
        if(isset($exportCsv) && $exportCsv ==1)
        {
            $param_array[5] = 0;
            $param_array[6] = 1000000;
        }
        // Mayur Bhayani on 23 July, 2012 - END
    
        // Set your credentials as per your system.        
        $mysqli = new mysqli($host,$username , $password, $db_name);
        //$mysqli = new mysqli($host,$username , $password, $db_name, $port);
        if (mysqli_connect_errno()) {
            printf("Connect failed: <p>%s</p>", mysqli_connect_error());
            exit();
        }
   // mysqli_set_charset('utf8');
	mysqli_query("SET NAMES 'utf8'"); 
	mysqli_query('SET CHARACTER SET utf8');
	//$sSQL= 'SET CHARACTER SET utf8'; 

		//mysqli_query($MySQL_Handle,$sSQL)
        if(!empty($param_array) && is_array($param_array)) {
            for($i=1;$i<=count($param_array);$i++)
            {
                //$sp_inputs .= "'".$param_array[$i]."'";
                if($param_array[$i] == 'NULL') {
                    $sp_inputs .= $param_array[$i];
                } else {
                    $sp_inputs .= "'".$param_array[$i]."'";
                }
                $sp_inputs .= $i != (count($param_array)) ? ',' : '';
            }
    
        }
        elseif($param_array!='' ) {
            $sp_inputs =  '"'.$param_array.'"';
        }
    
    
        $sp_name = explode('(',$sp_statement);
        $sp_name = $sp_name[0];
    
        $call_sp = $sp_name.'('.$sp_inputs.')';

        $baseUrl= Zend_Controller_Front::getInstance()->getBaseUrl();
        $path   = str_replace('//','/',$_SERVER['DOCUMENT_ROOT'].$baseUrl.'/');
		$path1 	= str_replace('//','/',$baseUrl.'/');		
		
		
		$filename = $path.'log/sp_log_'.date('Ymd').'.txt';
        if (!file_exists($filename)) {
            fopen($filename,'w');
        }
        chmod($filename,0777);
        
        $current = file_get_contents($filename);
        $current .= "\n".$call_sp."\n";
        file_put_contents($filename, $current);
       /* */
		
        ////echo $call_sp;exit;
       // printf("<p>Veena Test : Query : %s</p>" , $call_sp);
		//echo str_repeat(" ",1024*1024*4) ."<br>";ob_flush();
        if($mysqli->multi_query($call_sp)) {
    
            $content = array();
            $i = 0;
            do {
            if ($result = $mysqli->store_result()) {
                while ($row = $result->fetch_assoc())  {
                $content[$i][] = $row;
                }
                $result->free();
                $i++;
            }
            }  while ($mysqli->next_result());
        }
        //---------------Sp log Start
        //if($sp_statement == 'CALL sp_ws_getdata_from_customeroperationcontrol(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)' || $sp_statement == 'CALL sp_ws_getdata_invoiceheader(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)' ||$sp_statement == 'CALL sp_ws_getdata_from_tablet(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)' ||$sp_statement == 'CALL sp_ws_getdata_from_invoicerxddetail(?,?,?,?,?,?,?,?,?,?,?,?)' ||$sp_statement == 'CALL sp_ws_getdata_from_promotiondetail(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)' ||$sp_statement == 'CALL sp_ws_getdata_from_arheader(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)' ||$sp_statement == 'CALL sp_ws_getdata_from_ardetail(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)' ||$sp_statement == 'CALL sp_ws_getdata_from_cashcheckdetail(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)' ||$sp_statement == 'CALL sp_ws_getdata_from_customerinvoice(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)' ||$sp_statement == 'CALL sp_ws_getdata_from_inventorytransactionheader(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)' ||$sp_statement == 'CALL sp_ws_getdata_from_inventorytransactiondetail(?,?,?,?,?,?,?,?,?,?)')
        {
            ////echo $filename;exit;
        
            ////echo $call_sp;exit;
        }
        //------------------- End
        
         // Mayur Bhayani on 23 July, 2012 - START - To download CSV
        if(isset($exportCsv) && $exportCsv ==1)
        {
            $this->exportCsv($content, $pagingParams);
        }
        // Mayur Bhayani on 23 July, 2012 - END
        
        // Hiren Dave on 19 Nov, 2012 - START - To print data
        if(isset($printData) && $printData ==1)
        {
            $this->printData($content, $pagingParams,$printformat);
        }
        // Hiren Dave on 19 Nov, 2012 - END
        
        return $content;
    
    }
	public function executeimportquery($sp_statement,$param_array = array(),$query_type = '', $exportCsv = 0,$printData = 0,$printformat, $pagingParams = array())
    {
        // Hiren Dave on 26th July
        // Below code for accesing the variables from the config file.
        
        $db_name     = Zend_Registry::get('config')->resources->multidb->front_db->dbname;
        $username     = Zend_Registry::get('config')->resources->multidb->front_db->username;
        $password     = Zend_Registry::get('config')->resources->multidb->front_db->password;
        $host         = Zend_Registry::get('config')->resources->multidb->front_db->host;
        
        // Mayur Bhayani on 23 July, 2012 - START - To remove limit and add limit to 10 lac.
        if(isset($exportCsv) && $exportCsv ==1)
        {
            $param_array[5] = 0;
            $param_array[6] = 1000000;
        }
        // Mayur Bhayani on 23 July, 2012 - END
    
        // Set your credentials as per your system.        
        $mysqli = new mysqli($host,$username , $password, $db_name);
       // $mysqli = new mysqli($host,$username , $password, $db_name, $port);
        if (mysqli_connect_errno()) {
            printf("Connect failed: <p>%s</p>", mysqli_connect_error());
            exit();
        }
   // mysqli_set_charset('utf8');
	mysqli_query("SET NAMES 'utf8'"); 
	mysqli_query('SET CHARACTER SET utf8');
	//$sSQL= 'SET CHARACTER SET utf8'; 

		//mysqli_query($MySQL_Handle,$sSQL)
        if(!empty($param_array) && is_array($param_array)) {
            for($i=1;$i<=count($param_array);$i++)
            {
                //$sp_inputs .= "'".$param_array[$i]."'";
                if($param_array[$i] == 'NULL') {
                    $sp_inputs .= $param_array[$i];
                } else {
                    $sp_inputs .= "'".$param_array[$i]."'";
                }
                $sp_inputs .= $i != (count($param_array)) ? ',' : '';
            }
    
        }
        elseif($param_array!='' ) {
            $sp_inputs =  '"'.$param_array.'"';
        }
    
    
        $sp_name = explode('(',$sp_statement);
        $sp_name = $sp_name[0];
    
        $call_sp = $sp_name.'('.$sp_inputs.')';

        $baseUrl= Zend_Controller_Front::getInstance()->getBaseUrl();
        $path   = str_replace('//','/',$_SERVER['DOCUMENT_ROOT'].$baseUrl.'/');
		$path1 	= str_replace('//','/',$baseUrl.'/');		
		
		
		$filename = $path.'log/sp_log_'.date('Ymd').'.txt';
        if (!file_exists($filename)) {
            fopen($filename,'w');
        }
        chmod($filename,0777);
        
        $current = file_get_contents($filename);
        $current .= "\n".$call_sp."\n";
       // file_put_contents($filename, $current);
       /* */
		
        ////echo $call_sp;exit;
       // printf("<p>Veena Test : Query : %s</p>" , $call_sp);
		//echo str_repeat(" ",1024*1024*4) ."<br>";ob_flush();
        if($mysqli->multi_query($call_sp)) {
    
            $content = array();
            $i = 0;
            do {
            if ($result = $mysqli->store_result()) {
                while ($row = $result->fetch_assoc())  {
                $content[$i][] = $row;
                }
                $result->free();
                $i++;
            }
            }  while ($mysqli->next_result());
        }
        //---------------Sp log Start
        //if($sp_statement == 'CALL sp_ws_getdata_from_customeroperationcontrol(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)' || $sp_statement == 'CALL sp_ws_getdata_invoiceheader(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)' ||$sp_statement == 'CALL sp_ws_getdata_from_tablet(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)' ||$sp_statement == 'CALL sp_ws_getdata_from_invoicerxddetail(?,?,?,?,?,?,?,?,?,?,?,?)' ||$sp_statement == 'CALL sp_ws_getdata_from_promotiondetail(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)' ||$sp_statement == 'CALL sp_ws_getdata_from_arheader(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)' ||$sp_statement == 'CALL sp_ws_getdata_from_ardetail(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)' ||$sp_statement == 'CALL sp_ws_getdata_from_cashcheckdetail(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)' ||$sp_statement == 'CALL sp_ws_getdata_from_customerinvoice(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)' ||$sp_statement == 'CALL sp_ws_getdata_from_inventorytransactionheader(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)' ||$sp_statement == 'CALL sp_ws_getdata_from_inventorytransactiondetail(?,?,?,?,?,?,?,?,?,?)')
        {
            ////echo $filename;exit;
        
            ////echo $call_sp;exit;
        }
        //------------------- End
        
         // Mayur Bhayani on 23 July, 2012 - START - To download CSV
        if(isset($exportCsv) && $exportCsv ==1)
        {
            $this->exportCsv($content, $pagingParams);
        }
        // Mayur Bhayani on 23 July, 2012 - END
        
        // Hiren Dave on 19 Nov, 2012 - START - To print data
        if(isset($printData) && $printData ==1)
        {
            $this->printData($content, $pagingParams,$printformat);
        }
        // Hiren Dave on 19 Nov, 2012 - END
        
        return $content;
    
    }
    function permissions()
    {
        $main_array = array();
        $checkpermission = $this->executequery('CALL sp_web_permissions()','','');
        
        return $checkpermission;
    }
    /*
    Hiren dave on 5th June
    This function is create for getting 2nd language name.
    */
    function getsecondlanguage()
    {
    if(!defined(SECOND_LANG))
    {
        /* we use query over here*/
        define(SECOND_LANG,'Arabic');
    }
    
    return SECOND_LANG;
    }
    /*
    Hiren dave on 2nd July
    This function is create for getting Decimal places.
    */
    function getdecimalplaces()    
    {
    $Settings_NameSpace = new Zend_Session_Namespace('Settings');
    if($Settings_NameSpace->decimal == '')
    {
        $decimal         = $this->executequery('CALL sp_get_decimal_places()','','');
        $decimalplaces     = $decimal[0][0]['decimalplaces'];
        $Settings_NameSpace->decimal    = $decimalplaces;
    }
    else
    {
        $decimalplaces    = $Settings_NameSpace->decimal;
    }
    return $decimalplaces;
    }
     /*
    Hiren dave on 31st July
    This function is create for getting status of alternate code.
    */
    function getaltcodestatus()
    {
        $Settings_NameSpace = new Zend_Session_Namespace('Settings');
        
        if($Settings_NameSpace->cpanel     == '')
        {
            $cpanel = $this->executequery('CALL sp_get_admin_cpanel_general();()','','');
            
            $resarr = $cpanel[0];
            $cpanel = array();
            for($i=0;$i<count($resarr);$i++)
            {
                $cpanel[$resarr[$i]['flagname']] = $resarr[$i];
            }            
            $Settings_NameSpace->cpanel    = $cpanel;
        }
        else
        {
            $cpanel    = $Settings_NameSpace->cpanel;
        }
        return $cpanel;
    }
    /*
    Mayur Bhayani on 23 July, 2012
    This function is create for exporting data in CSV file.
    */
    function exportCsv($resultData, $pagingParams = array())
    {
        $out = '';
        
        $columnNames = $pagingParams['show_columns'];
        $statusCols = $pagingParams['status_cols'];
        
        if(isset($columnNames[0]) && $columnNames[0] != "")
        {
            foreach($columnNames as $key => $val)
            {
                $out .= $val;
                $out .= ",";
            }
            $out .= "\n";
        }
        
        if(isset($resultData[1]) && count($resultData[1]) > 0)
        {
            foreach($resultData[1] as $key => $val)
            {
                foreach($val as $k=>$v)
                {
                    $temp = 0;
                    for($i=0; $i<count($statusCols); $i++)
                    {
                        if(isset($statusCols[$i]) && $statusCols[$i]['cols_name'] != "")
                        {
                            if($statusCols[$i]['cols_name'] == $k) {
                                $out .= $statusCols[$i]['status_change'][$v];
                                $temp = 1;
                            }
                        }
                    }
                    
                    if($temp == 0)
                    {
                        $out .= $v;
                        $out .= ",";
                    }
                }
                $out .= "\n";
            }
        }
        
        $filename = time().'.csv';
        
        header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
        header("Content-Length: " . strlen($out));
        header("Content-type: text/x-csv");
        //header("Content-type: text/csv");
        //header("Content-type: application/csv");
        header("Content-Disposition: attachment; filename=$filename");
        echo $out;
        exit;
    }
    /*
    Hiren Dave on 19 Nov, 2012
    This function is create for print.
    */
    function printData($resultData, $pagingParams = array(),$printformat = 2)
    {
        $time = $this->executequery('CALL sp_get_server_time()','','');
        $time = $time[0][0]['currentdate'];
        
        $out = '';
        $out .= '<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01//EN" "http://www.w3.org/TR/html4/strict.dtd">';
        $out .= '<html lang="en">';
        $out .= '<head><link href="'.$this->getSiteBaseUrl().'public/css/style.css" media="screen" rel="stylesheet" type="text/css" ></head>';
        $out .= '<body>';        
        
        $out .= '<div id="noprint" name="noprint" style="float:left;margin-top:20px;margin-left:10px">';
        $out .= '<input class="border_none" type="button" value="Print" name="btnprintform" id="btnprintform" onclick="document.getElementById(\'noprint\').style.display = \'none\';window.print();window.close();">';        
        $out .= '</div>';
        
        
        if($printformat == 1)
        {
            $out .= '<div style="float:right;margin-top:20px;margin-right:20px">';
            $out .= '<b>Created Date :-</b> '.$time;        
            $out .= '</div>';
            $out .= '<div style="width:100%;clear:both;">';
            $out .= '<div style="float:right;width:55%;margin-top:20px;margin-left:10px">';
            $out .= '<b>'.$pagingParams['pagename'].'</b>';
            $out .= '</div>';
            $out .= '</div>';
            $out .= '<div style="width:100%;clear:both;"></br></br>&nbsp;</div>';
            //$out .= '<div style="clear:both;overflow-y:auto; width:900px; height:250px;">';
            $out .= '<div style="clear:both;">';
            $out .= '<table id="table-example" class="table" width="100%">';            
            
            $columnNames    = $pagingParams['show_columns'];
            $statusCols     = $pagingParams['status_cols'];
            
            if(isset($resultData[1]) && count($resultData[1]) > 0)
            {
                foreach($resultData[1] as $key => $val)
                {
                    $j = 0;
                    foreach($val as $k=>$v)
                    {
                        if($k != 'edit_del_primary_id' && $k != 'sett_tran_type')
                        {
                            if($j%2 == 0) {
                                $out .= '<tr>';
                                $out .= '<td style="width:2%">&nbsp;</td>';
                            }
                            
                            $temp = 0;
                            for($i=0; $i<count($statusCols); $i++)
                            {
                                if(isset($statusCols[$i]) && $statusCols[$i]['cols_name'] != "")
                                {
                                    if($statusCols[$i]['cols_name'] == $k) {                                        
                                        
                                        $out .= '<td align="left" style="width:24%">';
                                        $out .= '<b>'.$columnNames[$j].'</b> :';
                                        $out .= '</td>';
                                        
                                        $out .= '<td style="width:24%">';
                                        $out .= $statusCols[$i]['status_change'][$v];
                                        $temp = 1;
                                        $out .= '</td>';
                                    }
                                }
                            }
                            if($temp == 0)
                            {
                                $out .= '<td align="left" style="width:24%">';
                                $out .= '<b>'.$columnNames[$j].'</b> :';
                                $out .= '</td>';
                            
                                $out .= '<td align="left" style="width:24%">';
                                $out .= trim($v);                                
                                $out .= '</td>';
                            }
                            $j++;
                            if($j%2 == 0) {
                                $out .= '<td style="width:2%">&nbsp;</td>';
                                $out .= '</tr>';                                
                            }
                        }
                    }
                    $out .= '<tr><td colspan="6"><hr></td></tr>';
                }
            }            
            $out .= '</table>';
            $out .= '</div>';        
        }
        elseif($printformat == 2)
        {
            $out .= '<div style="float:right;margin-top:20px;margin-right:20px">';
            $out .= '<b>Created Date :-</b> '.$time;
            $out .= '</div>';
            $out .= '<div style="width:100%;clear:both;">';
            $out .= '<div style="float:left;margin-top:20px;margin-left:10px;">';
            $out .= '<b>Print :- '.$pagingParams['pagename'].'</b>';
            $out .= '</div>';
            $out .= '</div>';
            $out .= '<div style="clear:both;overflow-y:auto; width:900px; height:250px;">';            
            $out .= '<table id="table-example" class="table" width="100%">';        
            $out .= '<tr><td><br><br></td></tr>';
            
            $columnNames    = $pagingParams['show_columns'];
            $statusCols     = $pagingParams['status_cols'];
            
            if(isset($columnNames[0]) && $columnNames[0] != "") {
                $out .= '<tr>';
                $out .= '<th width="3">';
                $out .= '</th>';
                foreach($columnNames as $key => $val) {
                    $out .= '<th align="left">';
                    $out .= $val;
                    $out .= '</th>';
                }
                $out .= '</tr>';
                $out .= "\n";
            }
            if(isset($resultData[1]) && count($resultData[1]) > 0)
            {
                foreach($resultData[1] as $key => $val)
                {
                    $out .= '<tr>';
                    $out .= '<td width="3"></td>';
                    foreach($val as $k=>$v)
                    {
                        if($k != 'edit_del_primary_id' && $k != 'sett_tran_type')
                        {
                            $temp = 0;
                            for($i=0; $i<count($statusCols); $i++)
                            {
                                if(isset($statusCols[$i]) && $statusCols[$i]['cols_name'] != "")
                                {
                                    if($statusCols[$i]['cols_name'] == $k) {
                                        $out .= '<td>';
                                        $out .= $statusCols[$i]['status_change'][$v];
                                        $temp = 1;
                                        $out .= '</td>';
                                    }
                                }
                            }
                            if($temp == 0)
                            {
                                $out .= '<td align="left">';
                                $out .= trim($v);
                                //$out .= ",";
                                $out .= '</td>';
                            }
                        }
                    }                    
                    $out .= '</tr>';
                }
            }
            $out .= '<tr><td><br></td></tr>';        
            $out .= '</table>';
            $out .= '</div>';
        }
        $out .= '</body>';
        $out .= '</html>';
        
        echo $out;
        exit;
    }
    
    /*
    pankil thakkar 24 Sep
    This function is return the filename list with in the folder
    */
    function show_dir_file_array($maindirpath,$filedisplayarr)
    {
        $cnt=0;
        $maindir=dir($maindirpath);
        
        $dumytxt = explode("/",$maindir->path);
        $dumytxt = $dumytxt[count($dumytxt)-1];
        $filedisplayarr['main']=$dumytxt;
        
        while (false !== ($entry = $maindir->read())) {
            
            if(is_dir($maindir->path."/".$entry) && $entry != "." && $entry != ".." && $entry != ".svn" )
            {
                $filedisplayarr[$cnt]=array();
                $filedisplayarr[$cnt]=show_dir_file_array($maindir->path."/".$entry,$filedisplayarr[$cnt]);
                $cnt++;
            }
            
            if(is_file($maindir->path."/".$entry) )
            {
                $filedisplayarr[$cnt]=$entry;
                $cnt++;
            }
        }
        $maindir->close();
        return $filedisplayarr;
    }
    
    /*
    pankil thakkar 24 Sep
    This function is return the array index after searching string in the array
    */
    function search_array($array, $term)
    {
        $searchkey = array();
        foreach ($array AS $key => $value) {
            if (stristr($value, $term) === FALSE) {
                continue;
            } else {
                $searchkey[] = $key;
            }
        }
        return $searchkey;
    }
	/*New function for reports by nilesh on 13Mar2016*/
	function get_php_reports($rep_name,$sp_name_par,$header_name,$dt_lbl,$today_date)
	{
	
					$fileLocation = "C:/wamp/www/php_reports/sample_reports/mysql/$header_name.sql";
						$file = fopen($fileLocation,"w");

					
					$rep_name = "-- $rep_name";
					$decs ="-- $dt_lbl:$today_date";				
				$head= '-- FILTER: { 
--      column: "Customer Name", 
--      filter: " ",
-- }';
					
				$content = "$rep_name \n$decs \n$head \n\n\n\n  CALL $sp_name_par;";
				$ser_host= $_SERVER['HTTP_HOST'];
				fwrite($file,$content);
				fclose($file);
				   header("location: http://$ser_host/php_reports/report/html/?report=mysql/$header_name.sql");
	}
	 /*
	Nilesh Gotre on 10th July 2016
    This function is get the permissions of login users.
    */
	public function checkpermission($moduleid,$formid,$useid)
	{					
						$param_array = array();
						$param_array[1] = $moduleid; // module id
						$param_array[2] = $formid;//form id
						$param_array[3] = $useid;					
						return $user_permissions = $this->executequery('CALL sp_get_userwisepermission(?,?,?)',$param_array,'');						
						
	}
	
	function getcasepcslabel()    
    {
        $decimal         = $this->executequery('CALL sp_get_case_pcs_label()','','');
        $getcaselabel     = $decimal[0][0]['cases'];
		$getpcslabel      = $decimal[0][0]['pcs'];
        $getcasepcslabel[]    = $getcaselabel;
		$getcasepcslabel[]    = $getpcslabel;
    	return $getcasepcslabel;
    }
	
}