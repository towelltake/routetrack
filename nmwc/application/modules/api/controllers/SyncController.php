<?php

class Api_SyncController extends Api_Library_Controller_Action_Abstract
{

    //Initilize var for App Model
    private $index = "";

    /**
    * @name       init
    * @since      16-03-2012
    * @version    Release: 1
    * @author     PM <pankit@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This is the default function for all Actions.
    *
    */
    public function init()
    {
        $this->SFA_Comman = new SFA_Comman();
	parent::init();
    }
//json
 public function jsonDecode($json)
    {
      $json = str_replace(array("\\\\", "\\\""), array("&#92;", "&#34;"), $json);
      $parts = preg_split("@(\"[^\"]*\")|([\[\]\{\},:])|\s@is", $json, -1, PREG_SPLIT_NO_EMPTY | PREG_SPLIT_DELIM_CAPTURE);
      foreach ($parts as $index => $part)
      {
          if (strlen($part) == 1)
          {
              switch ($part)
              {
                  case "[":
                  case "{":
                      $parts[$index] = "array(";
                      break;
                  case "]":
                  case "}":
                      $parts[$index] = ")";
                      break;
                  case ":":
                    $parts[$index] = "=>";
                    break;   
                  case ",":
                    break;
                  default:
                      return null;
              }
          }
          else
          {
              if ((substr($part, 0, 1) != "\"") || (substr($part, -1, 1) != "\""))
              {
                  return null;
              }
          }
      }
      $json = str_replace(array("&#92;", "&#34;", "$"), array("\\\\", "\\\"", "\\$"), implode("", $parts));
      return eval("return $json;");
    } 
//
   //Function for decode signaturedata added by vinay
    
  public function senddataAction()
  {
        
    $reqval= $this->getRequest()->getParams();

    $resultreturn = array();    
		
		$baseUrl= Zend_Controller_Front::getInstance()->getBaseUrl();
		$path   = str_replace('//','/',$_SERVER['DOCUMENT_ROOT'].'/routepro/');		                         
		
		$filename = $path.'log/sync_log_'.date('Ymd').'.txt';
		$trtype ="";
		
		
		if (!file_exists($filename)) {
			fopen($filename,'a');
		}
		chmod($filename,0777);
		
		$current = file_get_contents($filename);
		$current .= "\n";
		$post = $this->getRequest()->getParams();
		$current .= "\n".print_r($post,true)."\n";
		file_put_contents($filename, $current);
		
		
    //For cusotmeroperation control
    if(!is_null($this->_getParam('customeroperationscontrol')))
    {
   	$pararminvoice=$this->_getParam('customeroperationscontrol');	
	$withstrip=(stripslashes($pararminvoice));
        $params = json_decode($withstrip,true);	
	$ar=array();
	$arr22=array();
	//print_r($params);
	//echo count($params);
	if(count($params)>0)
	{
	    for($i=0;$i<count($params);$i++)
	    {
		    $param_array 	= array();
		   
		    $param_array[1] = $params[$i]['visitkey'];
		    $param_array[2] = $params[$i]['routekey'];
		    $param_array[3] = $params[$i]['customercode'];
		    $param_array[4] = $params[$i]['routecode'];
		    $param_array[5] = $params[$i]['salesmancode'];
		    $param_array[6] = $params[$i]['odometerreading'];
		    $param_array[7] = $params[$i]['visitstartdate'];
		    $param_array[8] = $params[$i]['visitstarttime'];
		    $param_array[9] = $params[$i]['visitenddate'];
		    $param_array[10] = $params[$i]['visitendtime'];
		    $param_array[11] = $params[$i]['totaltransactions'];
		    $param_array[12] = $params[$i]['addedcustomer'];
		    $param_array[13] = $params[$i]['voidflag'];
		    $param_array[14] = $params[$i]['scannerindicator'];
		    $param_array[15] = $params[$i]['reasoncode'];
		    $param_array[16] = $params[$i]['latitude'];
		    $param_array[17] = $params[$i]['longitude'];
		    $param_array[18] = $params[$i]['radius'];
		
		$resultdata = $this->SFA_Comman->executequery('CALL sp_ws_getdata_from_customeroperationcontrol(?)',$param_array,'');
      
		if($resultdata[0][0]['lastid']>0)
		{
		   // $arr19[]=array("itemcode"=>$param_array[1]);
		    $arr22[]=array("routekey"=>$param_array[2],"visitkey"=>$param_array[1],"customercode"=>$param_array[3]);
		    //$arr22[]=array();
		  
		}
	    
	    }
	 
	  $resultreturn['customeroperationscontrol'] = $arr22;
	  
	}else
	{
		 $resultreturn['customeroperationscontrol'] = array();
	}
    }
   
    //End
    //For Customer master
     if(!is_null($this->_getParam('customermaster')))
    {
   	$pararminvoice=$this->_getParam('customermaster');	
	$withstrip=(stripslashes($pararminvoice));
        $params = json_decode($withstrip,true);
	//print_r($params);
	$ar=array();
	$arr19=array();
	//echo count($params);
	if(count($params)>0)
	{
	    for($i=0;$i<count($params);$i++)
	    {
		    $param_array 	= array();
		    $param_array[1] = $params[$i]['customercode'];
		    $param_array[2] = $params[$i]['type'];
		    $param_array[3] = $params[$i]['headofficecode'];
		    $param_array[4] = $params[$i]['routecode'];
		    $param_array[5] = $params[$i]['streetcode'];
		    $param_array[6] = $params[$i]['districtcode'];
		    $param_array[7] = $params[$i]['locationcode'];
		    $param_array[8] = $params[$i]['customersequence'];
		    $param_array[9] = $params[$i]['customername'];
		    $param_array[10] = $params[$i]['customeraddress1'];
		    $param_array[11] = $params[$i]['customeraddress2'];
		    $param_array[12] = $params[$i]['customerphone'];
		    $param_array[13] = $params[$i]['balance'];
		    $param_array[14] = $params[$i]['customercategory'];
		    $param_array[15] = $params[$i]['pricingkey'];
		    $param_array[16] = $params[$i]['promotionkey'];
		    $param_array[17] = $params[$i]['authorizeditemgrpkey'];
		    $param_array[18] = $params[$i]['messagekey1'];
		    $param_array[19] = $params[$i]['messagekey2'];
		    $param_array[20] = $params[$i]['invoicepaymentterms'];
		    $param_array[21] = $params[$i]['invoiceretailoption'];
		    $param_array[22] = $params[$i]['invoicepriceoverride'];
		    $param_array[23] = $params[$i]['invoiceretailoverride'];
		    $param_array[24] = $params[$i]['invoiceformatoption'];
		    $param_array[25] = $params[$i]['invoiceextensionopt'];
		    $param_array[26] = $params[$i]['invoicedsdpromptopt'];
		    $param_array[27] = $params[$i]['invoicecopies'];
		    $param_array[28] = $params[$i]['salesinputoprion'];
		    $param_array[29] = $params[$i]['returnsinputoption'];
		    $param_array[30] = $params[$i]['invoiceinputstyle'];
		    $param_array[31] = $params[$i]['onhandspromptopt'];
		    $param_array[32] = $params[$i]['inventoryselectopt'];
		    $param_array[33] = $params[$i]['invencontaineropt'];
		    $param_array[34] = $params[$i]['queuedreportoption'];
		    $param_array[35] = $params[$i]['surveykey'];
		    $param_array[36] = $params[$i]['contactname'];
		    $param_array[37] = $params[$i]['customertype'];
		    $param_array[38] = $params[$i]['callfrequency'];
		    $param_array[39] = $params[$i]['routenumber'];
		    $param_array[40] = $params[$i]['arbcustomernameshort'];
		    $param_array[41] = $params[$i]['arbcustomername'];
		    $param_array[42] = $params[$i]['arbcustomeraddress1'];
		    $param_array[43] = $params[$i]['arbcustomeraddress2'];
		    $param_array[44] = $params[$i]['hhccustomernameshort'];
		    $param_array[45] = $params[$i]['hhccustomername'];
		    $param_array[46] = $params[$i]['hhccustomeraddress1'];
		    $param_array[47] = $params[$i]['hhccustomeraddress2'];
		    $param_array[48] = $params[$i]['allowbeyondlimit'];
		    $param_array[49] = $params[$i]['tclimit'];
		    $param_array[50] = $params[$i]['activecustomer'];
		    $param_array[51] = $params[$i]['creditlimitdays'];
		    $param_array[52] = $params[$i]['created'];
		    $param_array[53] = $params[$i]['cdat'];
		    $param_array[54] = $params[$i]['modified'];
		    $param_array[55] = $params[$i]['mdat'];
		    $param_array[56] = $params[$i]['forcehand'];
		    $param_array[57] = $params[$i]['renteddisplay'];
		    $param_array[58] = $params[$i]['installedchiller'];
		    $param_array[59] = $params[$i]['monthlydepreciation'];
		    $param_array[60] = $params[$i]['typeofgiveaway'];
		    $param_array[61] = $params[$i]['giveawayflag'];
		    $param_array[62] = $params[$i]['lastvisiteddate'];
		    $param_array[63] = $params[$i]['memo1'];
		    $param_array[64] = $params[$i]['memo2'];
		    $param_array[65] = $params[$i]['tcsubtype'];
		    $param_array[66] = $params[$i]['rentperc'];
		    $param_array[67] = $params[$i]['customeraddress3'];
		    $param_array[68] = $params[$i]['customercity'];
		    $param_array[69] = $params[$i]['customerstate'];
		    $param_array[70] = $params[$i]['customerzip'];
		    $param_array[71] = $params[$i]['authorizeditemlistctl'];
		    $param_array[72] = $params[$i]['invoicepriceprint'];
		    $param_array[73] = $params[$i]['messagekey3'];
		    $param_array[74] = $params[$i]['messagekey4'];
		    $param_array[75] = $params[$i]['messagekey5'];
		    $param_array[76] = $params[$i]['messagekey6'];
		    $param_array[77] = $params[$i]['orderformat'];
		    $param_array[78] = $params[$i]['enableupcprint'];
		    $param_array[79] = $params[$i]['enabledelayprint'];
		    $param_array[80] = $params[$i]['printsequence'];
		    $param_array[81] = $params[$i]['enablepriceeditinvs'];
		    $param_array[82] = $params[$i]['enablesellprevious'];
		    $param_array[83] = $params[$i]['enablesuggestsales'];
		    $param_array[84] = $params[$i]['enableautofillreturns'];
		    $param_array[85] = $params[$i]['enableautofilldamaged'];
		    $param_array[86] = $params[$i]['enablesigcapture'];
		    $param_array[87] = $params[$i]['enablereturnstrxn'];
		    $param_array[88] = $params[$i]['enableexchangetrxn'];
		    $param_array[89] = $params[$i]['enabledamagedreturns'];
		    $param_array[90] = $params[$i]['enablearcollection'];
		    $param_array[91] = $params[$i]['enablesurveyaudit'];
		    $param_array[92] = $params[$i]['enabledelivinstruct'];
		    $param_array[93] = $params[$i]['enableinvoicecomment'];
		    $param_array[94] = $params[$i]['invoicedetailentry'];
		    $param_array[95] = $params[$i]['orderdetailentry'];
		    $param_array[96] = $params[$i]['forcestockcapture'];
		    $param_array[97] = $params[$i]['enablepromotrxn'];
		    $param_array[98] = $params[$i]['alternatecode'];
		    $param_array[99] = $params[$i]['creditlimit'];
		    $param_array[100] = $params[$i]['allowcashoncreditexceed'];
		    $param_array[101] = $params[$i]['arbcustomeraddress3'];
		    $param_array[102] = $params[$i]['templateindicator'];
		    $param_array[103] = $params[$i]['templatename'];
		    $param_array[104] = $params[$i]['arbcontactname'];
		    $param_array[105] = $params[$i]['printlanguageflag'];
		    $param_array[106] = $params[$i]['quantumno'];
		    $param_array[107] = $params[$i]['lostplacementdelivs'];
		    $param_array[108] = $params[$i]['newplacementdelivs'];
		    $param_array[109] = $params[$i]['currencycode'];
		    $param_array[110] = $params[$i]['histmaxdeliveries'];
		    $param_array[111] = $params[$i]['arcustomertype'];
		    $param_array[112] = $params[$i]['custtaxkey1'];
		    $param_array[113] = $params[$i]['custtaxkey2'];
		    $param_array[114] = $params[$i]['custtaxkey3'];
		    $param_array[115] = $params[$i]['customertaxid'];
		    $param_array[116] = $params[$i]['customertaxidoptions'];
		    $param_array[117] = $params[$i]['outletsubtype'];
		    $param_array[118] = $params[$i]['volume'];
		    $param_array[119] = $params[$i]['enablegovtaxnote'];
		    $param_array[120] = $params[$i]['forwardcoverfactor'];
		    $param_array[121] = $params[$i]['enablepromoeditinvs'];
		    $param_array[122] = $params[$i]['enableaddlpromoinvs'];
		    $param_array[123] = $params[$i]['badcreditcustomer'];
		    $param_array[124] = $params[$i]['enableduplicateprinting'];
		    $param_array[125] = $params[$i]['numoutstandinginv'];
		    $param_array[126] = $params[$i]['enablefocprinting'];
		    $param_array[127] = $params[$i]['promooptions'];
		    $param_array[128] = $params[$i]['groupcode'];
		    $param_array[129] = $params[$i]['forceposcheck'];
		    $param_array[130] = $params[$i]['ancustomercode'];
		    $param_array[131] = $params[$i]['printoutletitemcode'];
		    $param_array[132] = $params[$i]['reportprintcontrol'];
		    $param_array[133] = $params[$i]['invoicelimiter'];
		    $param_array[134] = $params[$i]['exclusiveopmode'];
		    $param_array[135] = $params[$i]['returnpromotionkey'];
		    $param_array[136] = $params[$i]['invoiceformat'];
		    $param_array[137] = $params[$i]['liquorlicprint'];
		    $param_array[138] = $params[$i]['enablepromoeditords'];
		    $param_array[139] = $params[$i]['enableaddlpromoords'];
		    $param_array[140] = $params[$i]['enableaddlpromoinvoices'];
		    $param_array[141] = $params[$i]['enableposequipment'];
		    $param_array[142] = $params[$i]['enablesalestrxn'];
		    $param_array[143] = $params[$i]['enableadvancepayment'];
		    $param_array[144] = $params[$i]['printcheckdetails'];
		    $param_array[145] = $params[$i]['tcspecialdiscount'];
		    $param_array[146] = $params[$i]['spldiscountdays'];
		    $param_array[147] = $params[$i]['arabiccustomercity'];
		    $param_array[148] = $params[$i]['threshholdlimit'];
		    $param_array[149] = $params[$i]['discountkey'];
		    $param_array[150] = $params[$i]['enforcepromotion'];
		    $param_array[151] = $params[$i]['gpcustcode'];
		    $param_array[152] = $params[$i]['cashonlypromo'];
		    $param_array[153] = $params[$i]['roundnetamount'];
		    $param_array[154] = $params[$i]['partialcollection'];
		    $param_array[155] = $params[$i]['transactiontype'];
		    $param_array[156] = $params[$i]['enabledraftcopy'];
		    $param_array[157] = $params[$i]['enablebuybackfree'];
		    $param_array[158] = $params[$i]['enablecpd'];
		    $param_array[159] = $params[$i]['enablepaymentsel'];
		    $param_array[160] = $params[$i]['gpsdata'];
		    $param_array[161] = $params[$i]['fixedlatitude'];
		    $param_array[162] = $params[$i]['fixedlongitude'];
		    $param_array[163] = $params[$i]['rentkey'];
		    $param_array[164] = $params[$i]['startdate'];
		    $param_array[165] = $params[$i]['enddate'];
		    $param_array[166] = $params[$i]['definitionvalue'];
		    $param_array[167] = $params[$i]['runningvalue'];
		    $param_array[168] = $params[$i]['rentcontrol'];
		    $param_array[169] = $params[$i]['disablebalanceupdate'];
		    $param_array[170] = $params[$i]['enablecreditlimit'];
		    $param_array[171] = $params[$i]['autosettlecollection'];
		    $param_array[172] = $params[$i]['enableinvoicecopy'];
		    $param_array[173] = $params[$i]['pobox'];
		    $param_array[174] = $params[$i]['shoptelephonenumber'];
		    $param_array[175] = $params[$i]['shopfaxnumber'];
		    $param_array[176] = $params[$i]['ownername'];
		    $param_array[177] = $params[$i]['ownerlandlinenumber'];
		    $param_array[178] = $params[$i]['ownermobilenumber'];
		    $param_array[179] = $params[$i]['contactpersonlandlinenumber'];
		    $param_array[180] = $params[$i]['contactpersonmobilenumber'];
		    $param_array[181] = $params[$i]['contactpersonemail'];
		    $param_array[182] = $params[$i]['purchasemanagername'];
		    $param_array[183] = $params[$i]['purchasemanagerlandlinenumber'];
		    $param_array[184] = $params[$i]['purchasemanagermobilenumber'];
		    $param_array[185] = $params[$i]['purchasemanageremail'];
		    $param_array[186] = $params[$i]['warehousemanagername'];
		    $param_array[187] = $params[$i]['warehousemanagerlandlinenumber'];
		    $param_array[188] = $params[$i]['warehousemanagermobilenumber'];
		    $param_array[189] = $params[$i]['warehousemanageremail'];
		    $param_array[190] = $params[$i]['expirylimit'];
		    $param_array[191] = $params[$i]['exprunningvalue'];
		    $param_array[192] = $params[$i]['distributionkey'];
		    $param_array[193] = $params[$i]['gpssavecount'];
		    $param_array[194] = $params[$i]['graceperiod'];
		    $param_array[195] = $params[$i]['reportcustcode'];
		    $param_array[196] = $params[$i]['enablerental'];
		    $param_array[197] = '';
		    $param_array[198] = '';
		//print_r($param_array);
		$resultdata = $this->SFA_Comman->executequery('CALL sp_ws_get_from_customermaster(?)',$param_array,'');
		//print_r($resultdata);
		//exit;
      
		if($resultdata[0][0]['lastid']>0)
		{
		   $arr19[]=array("customercode"=>$param_array[1]);	   

		}
	    
	    }
	 
	  $resultreturn['customermaster'] = $arr19;
	  
	}else
	{
		 $resultreturn['customermaster'] = array();
	}
	    
    }
    //End
	//For routemaste
    if(!is_null($this->_getParam('routemaster')))
    {
   	$pararminvoice=$this->_getParam('routemaster');	
	$withstrip=(stripslashes($pararminvoice));
        $params = json_decode($withstrip,true);
	
	$ar=array();
	$arr21=array();
	//print_r($params);
	//echo count($params);
	if(count($params)>0)
	{
	    for($i=0;$i<count($params);$i++)
	    {
		    $param_array 	= array();
		   
			$param_array[1] = $params[$i]['routecode'];
			$param_array[2] = $params[$i]['mdat'];
			$param_array[3] = $params[$i]['hhcordseq'];
			$param_array[4] = $params[$i]['hhcinvseq'];
			$param_array[5] = $params[$i]['hhccshseq'];
			$param_array[6] = $params[$i]['hhcivtseq'];
			$param_array[7] = $params[$i]['bodocseq'];
			$param_array[8] = $params[$i]['cashbalance'];
			$param_array[9] = $params[$i]['creditlimit'];
			$param_array[10] = $params[$i]['routebalance'];
			$param_array[11] = $params[$i]['hhcarseq'];
			$param_array[12] = $params[$i]['hhcloadseq'];
			$param_array[13] = $params[$i]['hhcappversion'];
			$param_array[14] = $params[$i]['hhcinvretseq'];
		
		$resultdata = $this->SFA_Comman->executequery('CALL sp_ws_getdata_from_routemaster(?)',$param_array,'');
      
		if($resultdata[0][0]['lastid']>0)
		{
		  // $arr19[]=array("itemcode"=>$param_array[1]);
		    $arr21[]=array("routecode"=>$param_array[1]);
		  
		}
	    
	    }
	 
	  $resultreturn['routemaster'] = $arr21;
	  
	}else
	{
		 $resultreturn['routemaster'] = array();
	}
    }
    //End
    //echo json_encode($resultreturn);
    //exit;
    //Start invoice header
     if(!is_null($this->_getParam('invoiceheader')))
    {
	//echo "test";
      	$pararminvoice=$reqval['invoiceheader'];
	
	$withstrip=(stripslashes($pararminvoice));
        $params = json_decode($withstrip,true);
	//print_r($params);
	//$resultreturn = array();
	$ar=array();
	  $arr2=array();
	//echo count($params);
	if(count($params)>0)
	{
	    for($i=0;$i<count($params);$i++)
	    {
		//echo $i;
		$param_array 	= array();
		$param_array[1] = $params[$i]['transactionkey'];
		$param_array[2] = $params[$i]['routekey'];
		$param_array[3] = $params[$i]['visitkey'];
		$param_array[4] = $params[$i]['documentnumber'];
		$param_array[5] = $params[$i]['invoicenumber'];
		$param_array[6] = $params[$i]['transactiondate'];
		$param_array[7] = $params[$i]['transactiontime'];
		$param_array[8] = $params[$i]['dsdnumber'];
		$param_array[9] = $params[$i]['ponumber'];
		$param_array[10] = $params[$i]['customercode'];
		$param_array[11] = $params[$i]['routecode'];
		$param_array[12] = $params[$i]['salesmancode'];
		$param_array[13] = $params[$i]['presoldordernumber'];
		$param_array[14] = $params[$i]['presalesmancode'];
		$param_array[15] = $params[$i]['presalesroutecode'];
		$param_array[16] = ($params[$i]['orderdeliverydate'] <> 0 ? $params[$i]['orderdeliverydate']:date('Y-m-d'));
		$param_array[17] = $params[$i]['orderdeliveryroutecode'];
		$param_array[18] = $params[$i]['totalinvoiceamount'];
		$param_array[19] = $params[$i]['totalsalesamount'];
		$param_array[20] = $params[$i]['totalreturnamount'];
		$param_array[21] = $params[$i]['totaldamagedamount'];
		$param_array[22] = $params[$i]['totalfreesampleamount'];
		$param_array[23] = $params[$i]['immediatepaid'];
		$param_array[24] = $params[$i]['amountpaid'];
		$param_array[25] = $params[$i]['invoicebalance'];
		$param_array[26] = $params[$i]['dexflag'];
		$param_array[27] = $params[$i]['dexg86signature'];
		$param_array[28] = $params[$i]['paymenttype'];
		$param_array[29] = $params[$i]['splittransaction'];
		$param_array[30] = $params[$i]['voidflag'];
		$param_array[31] = $params[$i]['transmitindicator'];
		$param_array[32] = $params[$i]['paymentstatus'];
		$param_array[33] = $params[$i]['hhcinvoicenumber'];
		$param_array[34] = $params[$i]['totalpromoamount'];
		$param_array[35] = $params[$i]['gcpaymenttype'];
		$param_array[36] = $params[$i]['hhcdocumentnumber'];
		$param_array[37] = $params[$i]['inventorykey'];
		$param_array[38] = $params[$i]['totaltaxesamount'];
		$param_array[39] = $params[$i]['itemlinetaxamount'];
		$param_array[40] = $params[$i]['totaldiscountamount'];
		$param_array[41] = $params[$i]['voidreasoncode'];
		$param_array[42] = $params[$i]['totalexpiryamount'];
		$param_array[43] = $params[$i]['currencycode'];
		$param_array[44] = $params[$i]['totalmanualfree'];
		$param_array[45] = $params[$i]['totallimitedfree'];
		$param_array[46] = $params[$i]['totalrebaterent'];
		$param_array[47] = $params[$i]['totalfixedrent'];
		$param_array[48] = $params[$i]['actualtransactiondate'];
		$param_array[49] = $params[$i]['boentry'];
		$param_array[50] = $params[$i]['hhctransactionkey'];
		$param_array[51] = $params[$i]['data'];
		$param_array[52] = $params[$i]['comments'];
		$param_array[53] = $params[$i]['totaldiscdistributionamount'];
		$param_array[54] = $params[$i]['totalreplacementamount'];
		$param_array[55] = $params[$i]['comments2'];
		$param_array[56] = ($params[$i]['lineitemdiscount'] <> ""?$params[$i]['lineitemdiscount']:0);
		$param_array[57] = ($params[$i]['totalreturnpromoamount'] <> ""?$params[$i]['totalreturnpromoamount']:0);
		$param_array[58] = ($params[$i]['returnlineitemdiscount'] <> ""?$params[$i]['returnlineitemdiscount']:0);
		$param_array[59] = $params[$i]['totalbuybackfreeamount'];
		$param_array[60] = $params[$i]['roundtotalsalesamount'];
		$param_array[61] = $params[$i]['diffround'];
        $param_array[62] = $params[$i]['deliverycharge'];
		$param_array[63] = $params[$i]['onlineorder'];
		$param_array[64] = $params[$i]['couponsale'];
		$param_array[65] =  ($params[$i]['onlinestatus'] <> ""?$params[$i]['onlinestatus']:0) ;

		//print_r($param_array);
		$resultdata = $this->SFA_Comman->executequery('CALL sp_ws_getdata_invoiceheader(?)',$param_array,'');
      
		if($resultdata[0][0]['lastid']>0)
		{
		    
			if($params[$i]['onlineorder'] > 0 ){
			   
			   if($params[$i]['onlinestatus'] == 2 ){ 
			   
			    $deliveryurl = "https://nmwc.ukwest.cloudapp.azure.com:7000/api/deliverorder/routecode/".$params[$i]['routecode']."/routekey/".$params[$i]['routekey']."/orderid/".$params[$i]['onlineorder']."/invoicenumber/".$params[$i]['invoicenumber'];
			   }else {
				    $deliveryurl = "https://nmwc.ukwest.cloudapp.azure.com:8085/api/deliverorder/routecode/".$params[$i]['routecode']."/routekey/".$params[$i]['routekey']."/orderid/".$params[$i]['onlineorder']."/invoicenumber/".$params[$i]['invoicenumber']; 
			   }
			 
		
				$curl = curl_init();
				curl_setopt_array($curl, [
				CURLOPT_RETURNTRANSFER => 1,
				CURLOPT_URL => $deliveryurl,
				CURLOPT_USERAGENT => 'Codular Sample cURL Request',
				CURLOPT_SSL_VERIFYPEER => false,
			   
				]);
				$resp = curl_exec($curl);
				curl_close($curl);
			}
			
			
			//echo "yes";
		    $arr2[]=array("routekey"=>$param_array[2],"visitkey"=>$param_array[3]);
		    $arr_trn[number_format($params[$i]['transactionkey'],0)][]=($resultdata[0][0]['lastid']);
			
			// log added by Hiren Dave
			// Start
		   
			$baseUrl= Zend_Controller_Front::getInstance()->getBaseUrl();
			$path   = str_replace('//','/',$_SERVER['DOCUMENT_ROOT'].$baseUrl.'/');
			
			chmod($path.'api_log.txt',777);
			$current = file_get_contents($path.'api_log.txt');
			//$current .= '**********************************************************';
			//$current .= "\n".$arr2."\n";
			//$current .= '----------------------------------------------------------';
			//$current .= "\n".$arr_trn."\n";
			//$current .= '----------------------------------------------------------';
			//$current .= "\n".$this->getPost()->getParams()."\n";			
			//$current .= '**********************************************************';
			
			file_put_contents($path.'api_log.txt', $resultdata[0][0]['lastid']);
			// End
		}
	    
	    }
	//print_r($arr2);
	  $resultreturn['invoiceheader'] = $arr2;
	  
	}else
	{
	$resultreturn['invoiceheader'] = array();
	}
	    
    }
    //End
    if(!is_null($this->_getParam('invoicedetail')))
    {
   	$pararminvoice=$reqval['invoicedetail'];	
	$withstrip=(stripslashes($pararminvoice));
        $params = json_decode($withstrip,true);
	//print_r($params);
	
	$ar=array();
	$arr=array();
	//echo count($params);
	if(count($params)>0)
	{
	    for($i=0;$i<count($params);$i++)
	    {
			
			
			
			
		$param_array 	= array();		
		$param_array[1] = $params[$i]['routekey'];
		$param_array[2] = $params[$i]['visitkey'];
		$param_array[3] = $arr_trn[number_format($params[$i]['transactionkey'],0)][0];
		$param_array[4] = $params[$i]['itemcode'];
		$param_array[5] = $params[$i]['salesqty'];
		$param_array[6] = $params[$i]['returnqty'];
		$param_array[7] = $params[$i]['damagedqty'];
		$param_array[8] = $params[$i]['freesampleqty'];
		$param_array[9] = $params[$i]['salesprice'];
		$param_array[10] = $params[$i]['returnprice'];
		$param_array[11] = $params[$i]['stdsalesprice'];
		$param_array[12] = $params[$i]['stdreturnprice'];
		$param_array[13] = $params[$i]['promoqty'];
		$param_array[14] = $params[$i]['salesitemexcisetax'];
		$param_array[15] = $params[$i]['salesitemgsttax'];
		$param_array[16] = $params[$i]['returnitemexcisetax'];
		$param_array[17] = $params[$i]['returnitemgsttax'];
		$param_array[18] = $params[$i]['damageditemexcisetax'];
		$param_array[19] = $params[$i]['damageditemgsttax'];
		$param_array[20] = $params[$i]['fgitemexcisetax'];
		$param_array[21] = $params[$i]['fgitemgsttax'];
		$param_array[22] = $params[$i]['promoitemexcisetax'];
		$param_array[23] = $params[$i]['promoitemgsttax'];
		$param_array[24] = $params[$i]['coopid'];
		$param_array[25] = $params[$i]['batchdetailkey'];
		$param_array[26] = $params[$i]['salescaseprice'];
		$param_array[27] = $params[$i]['returncaseprice'];
		$param_array[28] = $params[$i]['stdsalescaseprice'];
		$param_array[29] = $params[$i]['stdreturncaseprice'];
		$param_array[30] = $params[$i]['goodreturnprice'];
		$param_array[31] = $params[$i]['goodreturncaseprice'];
		$param_array[32] = $params[$i]['stdgoodreturncaseprice'];
		$param_array[33] = $params[$i]['stdgoodreturnprice'];
		$param_array[34] = $params[$i]['expiryqty'];
		$param_array[35] = $params[$i]['currencycode'];
		$param_array[36] = $params[$i]['returnfreeqty'];
		$param_array[37] = $params[$i]['manualfreeqty'];
		$param_array[38] = $params[$i]['limitedfreeqty'];
		$param_array[39] = $params[$i]['rebaterentqty'];
		$param_array[40] = $params[$i]['fixedrentqty'];
		$param_array[41] = $params[$i]['pricechgindicator'];
		$param_array[42] = $params[$i]['discountamount'];
		$param_array[43] = $params[$i]['discountpercentage'];
		$param_array[44] = $params[$i]['promoamount'];
		$param_array[45] = $params[$i]['replacementqty'];
		$param_array[46] = $params[$i]['replacementprice'];
		$param_array[47] = $params[$i]['replacementcaseprice'];
		$param_array[48] = $params[$i]['promovalue'];
		$param_array[49] = $params[$i]['mdat'];
		$param_array[50] = ($params[$i]['sales_amount']<>""?$params[$i]['sales_amount']:0);
		$param_array[51] = $params[$i]['returnpromovalue'];
		$param_array[52] = $params[$i]['returnpromoamount'];
		$param_array[53] = ($params[$i]['return_amount'] <> ""?$params[$i]['return_amount']:0);
		$param_array[54] = ($params[$i]['returnfreesampleqty'] <> ""?$params[$i]['returnfreesampleqty']:0);
		$param_array[55] = $params[$i]['roundsalesamount'];
		$param_array[56] = $params[$i]['diffround'];
		$param_array[57] = $params[$i]['amount'];
		$param_array[58] = $params[$i]['buybackexcisetax'];
		$param_array[59] = $params[$i]['buybackgsttax'];
		
		$resultdata = $this->SFA_Comman->executequery('CALL sp_ws_getdata_from_tablet(?)',$param_array,'');
      
		if($resultdata[0][0]['lastid']>0)
		{
		    $arr[]=array("routekey"=>$param_array[1],"visitkey"=>$param_array[2],"itemcode"=>$param_array[4]);	   

		}
	    
	    }
	 
	     $resultreturn['invoicedetail'] = $arr;
	}else
	    {
		 $resultreturn['invoicedetail'] = array();
	    }
	    
    }
    //For invoice header
    
   // if($value1[0] == 'invoiceheader')
    
    
    //For invoicerxddetail
    
    if(!is_null($this->_getParam('invoicerxddetail')))
    {
   	$pararminvoice=$reqval['invoicerxddetail'];	
	$withstrip=(stripslashes($pararminvoice));
        $params = json_decode($withstrip,true);
	
	$ar=array();
	$arr3=array();
	//echo count($params);
	if(count($params)>0)
	{
	    for($i=0;$i<count($params);$i++)
	    {
		$param_array 	= array();
		$param_array[1] = $params[$i]['routekey'];
		$param_array[2] = $params[$i]['visitkey'];
		$param_array[3] = $arr_trn[number_format($params[$i]['transactionkey'],0)][0];
		$param_array[4] = $params[$i]['itemtransactiontype'];
		$param_array[5] = $params[$i]['itemcode'];
		$param_array[6] = $params[$i]['itemtranstypeseq'];
		$param_array[7] = $params[$i]['reasoncode'];
		$param_array[8] = $params[$i]['quantity'];
		$param_array[9] = $params[$i]['catchweightqty'];
		$param_array[10] = $params[$i]['weighted'];
		$param_array[11] = $params[$i]['instructioncode'];
		$param_array[12] = $params[$i]['currencycode'];
		$param_array[13] = $params[$i]['expirydate'];
		$param_array[14] = $params[$i]['invoicenumber'];
		
		$resultdata = $this->SFA_Comman->executequery('CALL sp_ws_getdata_from_invoicerxddetail(?)',$param_array,'');
      
		if($resultdata[0][0]['lastid']>0)
		{
		    $arr3[]=array("routekey"=>$param_array[1],"visitkey"=>$param_array[2],"itemcode"=>$param_array[5]);
		   

		}
	    
	    }
	 
	  $resultreturn['invoicerxddetail'] = $arr3;
	  
	}else
	{
		 $resultreturn['invoicerxddetail'] = array();
	}
	    
    }
    //For salesorderheader
    if(!is_null($this->_getParam('salesorderheader'))) 
    {
   	$pararminvoice=$reqval['salesorderheader'];	
	$withstrip=(stripslashes($pararminvoice));
        $params = json_decode($withstrip,true);
	
	$ar=array();
	$arr10=array();
	//echo count($params);
	if(count($params)>0)
	{
	    for($i=0;$i<count($params);$i++)
	    {
		    $param_array 	= array();
		   $param_array[1] = $params[$i]['transactionkey'];
		    $param_array[2] = $params[$i]['routekey'];
		    $param_array[3] = $params[$i]['visitkey'];
		    $param_array[4] = $params[$i]['documentnumber'];
		    $param_array[5] = $params[$i]['invoicenumber'];
		    $param_array[6] = $params[$i]['transactiondate'];
		    $param_array[7] = $params[$i]['transactiontime'];
		    $param_array[8] = $params[$i]['dsdnumber'];
		    $param_array[9] = $params[$i]['ponumber'];
		    $param_array[10] = $params[$i]['customercode'];
		    $param_array[11] = $params[$i]['routecode'];
		    $param_array[12] = $params[$i]['salesmancode'];
		    $param_array[13] = $params[$i]['orderdeliveryroutecode'];
		    $param_array[14] = $params[$i]['orderdeliverydate'];
		    $param_array[15] = $params[$i]['totalinvoiceamount'];
		    $param_array[16] = $params[$i]['totalsalesamount'];
		    $param_array[17] = $params[$i]['totalreturnamount'];
		    $param_array[18] = $params[$i]['totaldamagedamount'];
		    $param_array[19] = $params[$i]['dexflag'];
		    $param_array[20] = $params[$i]['splittransaction'];
		    $param_array[21] = $params[$i]['voidflag'];
		    $param_array[22] = $params[$i]['transmitindicator'];
		    $param_array[23] = $params[$i]['hhcinvoicenumber'];
		    $param_array[24] = $params[$i]['paymenttype'];
		    $param_array[25] = $params[$i]['hhcdocumentnumber'];
		    $param_array[26] = $params[$i]['voidreasoncode'];
		    $param_array[27] = $params[$i]['advanceused'];
		    $param_array[28] = $params[$i]['paymentstatus'];
		    $param_array[29] = $params[$i]['advancebalance'];		    
		    $param_array[30] = $params[$i]['advancereceived'];
		    $param_array[31] = $params[$i]['currencycode'];
		    $param_array[32] = $params[$i]['status'];
		    $param_array[33] = $params[$i]['refnumber'];
		    $param_array[34] = $params[$i]['totalfreesampleamount'];
		    $param_array[35] = $params[$i]['deliverystatus'];
		    $param_array[36] = $params[$i]['data'];
		    $param_array[37] = $params[$i]['comments'];
		    $param_array[38] = $params[$i]['actualtransactiondate'];
		    $param_array[39] = $params[$i]['comments2'];
		    $param_array[40] = $params[$i]['hhctransactionkey'];
		    //$param_array[41] = $params[$i]['mdat'];
		    $param_array[41] = $params[$i]['totalpromoamount'];
			$param_array[42] = $params[$i]['totallineitemtax'];
		
		$resultdata = $this->SFA_Comman->executequery('CALL sp_ws_getdata_from_salesorderheader(?)',$param_array,'');
      
		if($resultdata[0][0]['lastid']>0)
		{
		   $arr10[]=array("routekey"=>$param_array[2],"visitkey"=>$param_array[3]);
		   $arr_ordtrn[number_format($params[$i]['transactionkey'],0)][]=($resultdata[0][0]['lastid']);

		}
	    
	    }
	 
	  $resultreturn['salesorderheader'] = $arr10;
	  
	}else
	{
		 $resultreturn['salesorderheader'] = array();
	}
	    
    }
     //For salesorderdetail
    
    if(!is_null($this->_getParam('salesorderdetail'))) 
    {
   	$pararminvoice=$reqval['salesorderdetail'];	
	$withstrip=(stripslashes($pararminvoice));
        $params = json_decode($withstrip,true);
	
	$ar=array();
	$arr11=array();
	//echo count($params);
	if(count($params)>0)
	{
	    for($i=0;$i<count($params);$i++)
	    {
		    $param_array 	= array();
		  $param_array[1] = $params[$i]['routekey'];
		  $param_array[2] = $params[$i]['visitkey'];
		  $param_array[3] = $arr_ordtrn[number_format($params[$i]['transactionkey'],0)][0];
		  $param_array[4] = $params[$i]['itemcode'];
		  $param_array[5] = $params[$i]['salesqty'];
		  $param_array[6] = $params[$i]['returnqty'];
		  $param_array[7] = $params[$i]['damagedqty'];
		  $param_array[8] = $params[$i]['freesampleqty'];
		    $param_array[9] = $params[$i]['salesprice'];
		    $param_array[10] = $params[$i]['returnprice'];
		    $param_array[11] = $params[$i]['stdsalesprice'];
		    $param_array[12] = $params[$i]['stdreturnprice'];
		    $param_array[13] = $params[$i]['coopid'];
		    $param_array[14] = $params[$i]['salescaseprice'];
		    $param_array[15] = $params[$i]['returncaseprice'];
		    $param_array[16] = $params[$i]['stdsalescaseprice'];
		    $param_array[17] = $params[$i]['stdreturncaseprice'];
		    $param_array[18] = $params[$i]['currencycode'];
		    $param_array[19] = $params[$i]['allocated'];
		    $param_array[20] = $params[$i]['freegoodcases'];
		    $param_array[21] = $params[$i]['freegoodpcs'];
		    $param_array[22] = $params[$i]['salespcs'];
		    $param_array[23] = $params[$i]['allocatedcases'];
		    $param_array[24] = $params[$i]['salescases'];
		    $param_array[25] = $params[$i]['allocatedpcs'];
		    $param_array[26] = $params[$i]['returncases'];
		    $param_array[27] = $params[$i]['returnpcs'];
			$param_array[28] = $params[$i]['manualfreeqty'];
			//$param_array[28] = "0";
			$param_array[29] = $params[$i]['salesordervat'];
			$param_array[30] = $params[$i]['salesorderexcisetax'];
			$param_array[31] = $params[$i]['returnvat'];
			$param_array[32] = $params[$i]['returnexcisetax'];
			$param_array[33] = $params[$i]['damagedvat'];
			$param_array[34] = $params[$i]['damagedexcisetax'];
			$param_array[35] = $params[$i]['promovat'];
			$param_array[36] = $params[$i]['promoexcisetax'];
			$param_array[37] = $params[$i]['fgvat'];
			$param_array[38] = $params[$i]['fgexcisetax'];
			$param_array[39] = $params[$i]['promoamount'];
			$param_array[40] = $params[$i]['promovalue'];
		
		$resultdata = $this->SFA_Comman->executequery('CALL sp_ws_getdata_from_salesorderdetail(?)',$param_array,'');
      
		if($resultdata[0][0]['lastid']>0)
		{
		   $arr11[]=array("routekey"=>$param_array[1],"visitkey"=>$param_array[2],"itemcode"=>$param_array[4]);
		  
		  
		}
	    
	    }
	 
	  $resultreturn['salesorderdetail'] = $arr11;
	  
	}else
	{
		 $resultreturn['salesorderdetail'] = array();
	}
	    
    }
	
	
	//For orderrxddetail
    
    if(!is_null($this->_getParam('orderrxddetail')))
    {
		$pararminvoice=$reqval['orderrxddetail'];	
		$withstrip=(stripslashes($pararminvoice));
        $params = json_decode($withstrip,true);
	
		$ar=array();
		$arr30=array();
		//echo count($params);
		if(count($params)>0)
		{
	    for($i=0;$i<count($params);$i++)
	    {
			$param_array 	= array();
			$param_array[1] = $params[$i]['routekey'];
			$param_array[2] = $params[$i]['visitkey'];
			$param_array[3] = $arr_ordtrn[number_format($params[$i]['transactionkey'],0)][0];
			$param_array[4] = $params[$i]['itemtransactiontype'];
			$param_array[5] = $params[$i]['itemcode'];
			$param_array[6] = $params[$i]['itemtranstypeseq'];
			$param_array[7] = $params[$i]['reasoncode'];
			$param_array[8] = $params[$i]['quantity'];
			$param_array[9] = $params[$i]['catchweightqty'];
			$param_array[10] = $params[$i]['weighted'];
			$param_array[11] = $params[$i]['instructioncode'];
			$param_array[12] = $params[$i]['expirydate'];
		
			$resultdata = $this->SFA_Comman->executequery('CALL sp_ws_getdata_from_orderrxddetail(?)',$param_array,'');
      
			if($resultdata[0][0]['lastid']>0)
			{
				$arr30[]=array("routekey"=>$param_array[1],"visitkey"=>$param_array[2],"itemcode"=>$param_array[5]); 
			}
	    
	    }
	 
	  $resultreturn['orderrxddetail'] = $arr30;
	  
		}else
		{
			 $resultreturn['orderrxddetail'] = array();
		}
	}
	
	
	
    //For promotiondetail
    
   
    if(!is_null($this->_getParam('promotiondetail')))
    {
   	$pararminvoice=$reqval['promotiondetail'];	
	$withstrip=(stripslashes($pararminvoice));
        $params = json_decode($withstrip,true);
	
	$ar=array();
	 $arr4=array();
	//echo count($params);
	if(count($params)>0)
	{
	    for($i=0;$i<count($params);$i++)
	    {
			$param_array 	= array();		  
		    if($params[$i]['itemtransactiontype'] =='4')
		    {
		       $transctionkey=$arr_ordtrn[number_format($params[$i]['transactionkey'],0)][0]; 
		    }
		    else
		    {
				$transctionkey=$arr_trn[number_format($params[$i]['transactionkey'],0)][0];
		    }
        	$param_array[1] = $params[$i]['routekey'];
		    $param_array[2] = $params[$i]['visitkey'];
		    $param_array[3] = $transctionkey;
		    $param_array[4] = $params[$i]['itemtransactiontype'];
		    $param_array[5] = $params[$i]['itemcode'];
		    $param_array[6] = $params[$i]['promotiontypecode'];
		    $param_array[7] = $params[$i]['promotionamount'];
		    $param_array[8] = $params[$i]['promotionquantity'];
		    $param_array[9] = $params[$i]['catchweightqty'];
		    $param_array[10] = $params[$i]['weighted'];
		    $param_array[11] = $params[$i]['promotionplannumber'];
		    $param_array[12] = $params[$i]['assignmentkey'];
		    $param_array[13] = $params[$i]['exclusionoption'];
		    $param_array[14] = $params[$i]['promochgindicator'];
		    $param_array[15] = $params[$i]['oldpromotionamount'];
		    $param_array[16] = $params[$i]['performindicator'];
		    $param_array[17] = $params[$i]['performcriteriakey'];
		    $param_array[18] = $params[$i]['promotioncaseprice'];
		    $param_array[19] = $params[$i]['currencycode'];
		    $param_array[20] = $params[$i]['memo1'];
		
		$resultdata = $this->SFA_Comman->executequery('CALL sp_ws_getdata_from_promotiondetail(?)',$param_array,'');
      
		if($resultdata[0][0]['lastid']>0)
		{
		    $arr4[]=array("routekey"=>$param_array[1],"visitkey"=>$param_array[2],"itemcode"=>$param_array[5]);
		   

		}
	    
	    }
	 
	  $resultreturn['promotiondetail'] = $arr4;
	  
	}else
	{
		 $resultreturn['promotiondetail'] = array();
	}
	    
    }
      //For batchdetail
    
   //For batchdetail
    
    if(!is_null($this->_getParam('batchexpirydetail'))) 
    {
   	$pararminvoice=$reqval['batchexpirydetail'];	
	$withstrip=(stripslashes($pararminvoice));
        $params = json_decode($withstrip,true);
	
	$ar=array();
	$arr5=array();
	//echo count($params);
	if(count($params)>0)
	{
	    for($i=0;$i<count($params);$i++)
	    {
		    $param_array = array();
		    $param_array[1] = $params[$i]['routekey'];
		    $param_array[2] = $params[$i]['batchdetailkey'];
		    $param_array[3] = $params[$i]['batchnumber'];
		    $param_array[4] = $params[$i]['itemcode'];
		    $param_array[5] = $params[$i]['quantity'];
		    $param_array[6] = $params[$i]['transactiontypecode'];
		    $param_array[7] = $params[$i]['expirydate'];
		    $param_array[8] = $params[$i]['visitkey'];
				    
		$resultdata = $this->SFA_Comman->executequery('CALL sp_ws_getdata_from_batchdetail(?)',$param_array,'');
      
		if($resultdata[0][0]['lastid']>0)
		{
		    $arr5[]=array("routekey"=>$param_array[1],"visitkey"=>$param_array[8],"batchdetailkey"=>$param_array[2]);
		   
		}
	    
	    }
	 
	  $resultreturn['batchexpirydetail'] = $arr5;
	  
	}else
	{
		 $resultreturn['batchexpirydetail'] = array();
	}
	    
    }
     
     
     
      //For arheader
      
    if(!is_null($this->_getParam('arheader')))   
    {
   	$pararminvoice=$reqval['arheader'];		
	$withstrip=(stripslashes($pararminvoice));	
	$params = json_decode($withstrip,true);
	//$params = Zend_Json_Encoder::encode($withstrip);
	
	$ar=array();
	$arr6=array();
	//print_r(params);
	//echo count($params);
	if(count($params)>0)
	{
	    for($i=0;$i<count($params);$i++)
	    {
		    $param_array 	= array();
		    $param_array[1] = $params[$i]['transactionkey'];
		    $param_array[2] = $params[$i]['routekey'];
		    $param_array[3] = $params[$i]['visitkey'];
		    $param_array[4] = $params[$i]['documentnumber'];
		    $param_array[5] = $params[$i]['transactiondate'];
		    $param_array[6] = $params[$i]['transactiontime'];
		    $param_array[7] = $params[$i]['customercode'];
		    $param_array[8] = $params[$i]['routecode'];
		    $param_array[9] = $params[$i]['salesmancode'];
		    $param_array[10] = $params[$i]['voidflag'];
		    $param_array[11] = $params[$i]['splittransaction'];
		    $param_array[12] = $params[$i]['transmitindicator'];
		    $param_array[13] = $params[$i]['totalinvoiceamount'];
		    $param_array[14] = $params[$i]['amountpaid'];
		    $param_array[15] = $params[$i]['invoicebalance'];
		    $param_array[16] = $params[$i]['invoicenumber'];
		    $param_array[17] = $params[$i]['hhcdocumentnumber'];
		    $param_array[18] = $params[$i]['hhcinvoicenumber'];
		    $param_array[19] = $params[$i]['voidreasoncode'];
		    $param_array[20] = $params[$i]['chequecollection'];
		    $param_array[21] = $params[$i]['currencycode'];
		    $param_array[22] = $params[$i]['hhctransactionkey'];
		    $param_array[23] = $params[$i]['data'];
		    $param_array[24] = $params[$i]['comments'];			
		    $param_array[25] = $params[$i]['advancepaymentflag'];
		    $param_array[26] = $params[$i]['excesspayment'];
		    $param_array[27] = $params[$i]['comments2'];
		
		$resultdata = $this->SFA_Comman->executequery('CALL sp_ws_getdata_from_arheader(?)',$param_array,'');
      
		if($resultdata[0][0]['lastid']>0)
		{
		    $arr6[]=array("routekey"=>$param_array[2],"visitkey"=>$param_array[3]);
		   $arr_artrn[number_format($params[$i]['transactionkey'],0)][]=($resultdata[0][0]['lastid']);

		}
	    
	    }
	 
	  $resultreturn['arheader'] = $arr6;
	  
	}else
	{
		 $resultreturn['arheader'] = array();
	}
	    
    }
     
    //For ardetail
    if(!is_null($this->_getParam('ardetail')))    
    {
   	$pararminvoice=$reqval['ardetail'];	
	$withstrip=(stripslashes($pararminvoice));
        $params = json_decode($withstrip,true);
	
	$ar=array();
	$arr7=array();
	//echo count($params);
	if(count($params)>0)
	{
	    for($i=0;$i<count($params);$i++)
	    {
		    $param_array 	= array();
		    $param_array[1] = $params[$i]['routekey'];
		    $param_array[2] = $params[$i]['visitkey'];
		    $param_array[3] = $arr_artrn[number_format($params[$i]['transactionkey'],0)][0];
		    $param_array[4] = $params[$i]['invoicenumber'];
		    $param_array[5] = $params[$i]['invoicedate'];
		    $param_array[6] = $params[$i]['totalinvoiceamount'];
		    $param_array[7] = $params[$i]['onacctreasoncode'];
		    $param_array[8] = $params[$i]['amountpaid'];
		    $param_array[9] = $params[$i]['invoicebalance'];
		    $param_array[10] = $params[$i]['arcollectiontype'];
		    $param_array[11] = $params[$i]['chequestatusindicator'];
		    $param_array[12] = $params[$i]['sapchequestatusindicator'];
		    $param_array[13] = $params[$i]['currencycode'];
		    $param_array[14] = $params[$i]['pdcbalance'];
		    $param_array[15] = $params[$i]['alternateinvoicenumber'];
		    $param_array[16] = $params[$i]['salesmancode'];
		
		$resultdata = $this->SFA_Comman->executequery('CALL sp_ws_getdata_from_ardetail(?)',$param_array,'');
      
		if($resultdata[0][0]['lastid']>0)
		{
		    $arr7[]=array("routekey"=>$param_array[1],"visitkey"=>$param_array[2],"transactionkey"=>$param_array[3]);		   

		}
	    
	    }
	 
	  $resultreturn['ardetail'] = $arr7;
	  
	}else
	{
		 $resultreturn['ardetail'] = array();
	}
	    
    }
    //
     //For cashcheckdetail
    if(!is_null($this->_getParam('cashcheckdetail')))   
    {
   	$pararminvoice=$reqval['cashcheckdetail'];	
	$withstrip=(stripslashes($pararminvoice));
        $params = json_decode($withstrip,true);
	
	$ar=array();
	$arr8=array();
	//echo count($params);
	if(count($params)>0)
	{
	    for($i=0;$i<count($params);$i++)
	    {
		    $param_array 	= array();
		    $param_array[1] = $params[$i]['routekey'];
		   $param_array[2] = $params[$i]['visitkey'];
		   $param_array[3] = $params[$i]['typecode'];
		   $param_array[4] = $params[$i]['paymenttype'];
		   $param_array[5] = $params[$i]['checknumber'];
		   $param_array[6] = $params[$i]['amount'];
		   $param_array[7] = $params[$i]['updateindicator'];
		   $param_array[8] = date('Y-m-d',strtotime($params[$i]['checkdate']));
		   $param_array[9] = $params[$i]['bankcode'];
		   $param_array[10] = $params[$i]['checkstatus'];
		   $param_array[11] = $params[$i]['branchcode'];
		   $param_array[12] = $params[$i]['drawercode'];
		   $param_array[13] = $params[$i]['chequestatusindicator'];
		   $param_array[14] = $params[$i]['sapchequestatusindicator'];
		   $param_array[15] = $params[$i]['currencycode'];
		   $param_array[16] = $params[$i]['hhctransactionkey'];
		   $param_array[17] = $params[$i]['transactiontype'];
		   $param_array[18] = $params[$i]['checktype'];
				   
		$resultdata = $this->SFA_Comman->executequery('CALL sp_ws_getdata_from_cashcheckdetail(?)',$param_array,'');
      
		if($resultdata[0][0]['lastid']>0)
		{
		   $arr8[]=array("routekey"=>$param_array[1],"visitkey"=>$param_array[2]);
		   

		}
	    
	    }
	 
	  $resultreturn['cashcheckdetail'] = $arr8;
	  
	}else
	{
		 $resultreturn['cashcheckdetail'] = array();
	}
	    
    }
    
    //
     //For customerinvoice
    if(!is_null($this->_getParam('customerinvoice')))   
    {
   	$pararminvoice=$this->_getParam('customerinvoice');	
	$withstrip=(stripslashes($pararminvoice));
        $params = json_decode($withstrip,true);
	
	$ar=array();
	$arr9=array();
	//echo count($params);
	if(count($params)>0)
	{
	    for($i=0;$i<count($params);$i++)
	    {
		    $param_array 	= array();
		    $param_array[1] = $params[$i]['transactionkey'];
		    $param_array[2] = $params[$i]['transactiontype'];
		    $param_array[3] = $params[$i]['documentnumber'];
		    $param_array[4] = $params[$i]['invoicenumber'];
		    $param_array[5] = $params[$i]['transactiondate'];
		    $param_array[6] = $params[$i]['transactiontime'];
		    $param_array[7] = $params[$i]['customercode'];
		    $param_array[8] = $params[$i]['routecode'];
		    $param_array[9] = $params[$i]['salesmancode'];
		    $param_array[10] = $params[$i]['totalinvoiceamount'];
		    $param_array[11] = $params[$i]['totalsalesamount'];
		    $param_array[12] = $params[$i]['totalreturnamount'];
		    $param_array[13] = $params[$i]['totaldamagedamount'];
		    $param_array[14] = $params[$i]['totalfreesampleamount'];
		    $param_array[15] = $params[$i]['immediatepaid'];
		    $param_array[16] = $params[$i]['amountpaid'];
		    $param_array[17] = $params[$i]['dnamountpaid'];
		    $param_array[18] = $params[$i]['cnamountpaid'];
		    $param_array[19] = $params[$i]['invoicebalance'];
		    $param_array[20] = $params[$i]['paymenttype'];
		    $param_array[21] = $params[$i]['voidflag'];
		    $param_array[22] = $params[$i]['paymentstatus'];
		    $param_array[23] = $params[$i]['hhcinvoicenumber'];
		    $param_array[24] = $params[$i]['remarks1'];
		    $param_array[25] = $params[$i]['remarks2'];
		    $param_array[26] = $params[$i]['routestartdate'];
		    $param_array[27] = $params[$i]['erpreferencenumber'];
		    $param_array[28] = $params[$i]['mdat'];
		    $param_array[29] = $params[$i]['totalpromoamount'];
		    $param_array[30] = $params[$i]['gcpaymenttype'];
		    $param_array[31] = $params[$i]['totaltaxesamount'];
		    $param_array[32] = $params[$i]['itemlinetaxamount'];
		    $param_array[33] = $params[$i]['totaldiscountamount'];
		    $param_array[34] = $params[$i]['pdcindicator'];
		    $param_array[35] = $params[$i]['chequecollection'];
		    $param_array[36] = $params[$i]['totalexpiryamount'];
		    $param_array[37] = $params[$i]['currencycode'];
		    $param_array[38] = $params[$i]['pdcbalance'];
		    $param_array[39] = $params[$i]['totalmanualfree'];
		    $param_array[40] = $params[$i]['totallimitedfree'];
		    $param_array[41] = $params[$i]['totalrebaterent'];
		    $param_array[42] = $params[$i]['totalfixedrent'];
		    $param_array[43] = $params[$i]['data'];
		    $param_array[44] = $params[$i]['totaldiscdistributionamount'];
		    $param_array[45] = $params[$i]['totalreplacementamount'];
		    $param_array[46] = $params[$i]['pdcdate'];
		    $param_array[47] = $params[$i]['totalbuybackfreeamount'];
			$param_array[48] = $params[$i]['duedate'];
		
		$resultdata = $this->SFA_Comman->executequery('CALL sp_ws_getdata_from_customerinvoice(?)',$param_array,'');
      
		if($resultdata[0][0]['lastid']>0)
		{
		   $arr9[]=array("transactionkey"=>$param_array[1]);
		   

		}
	    
	    }
	 
	  $resultreturn['customerinvoice'] = $arr9;
	  
	}else
	{
		 $resultreturn['customerinvoice'] = array();
	}
	    
    }   
	
   
     //For inventorytransactionheader
  //  if($value11[0] == 'inventorytransactionheader')
    if(!is_null($this->_getParam('inventorytransactionheader')))
    {
   	$pararminvoice=$this->_getParam('inventorytransactionheader');	
	$withstrip=(stripslashes($pararminvoice));
        $params = json_decode($withstrip,true);
	
	//print_r($params);
	$ar=array();
	$arr12=array();
	//echo count($params);
	if(count($params)>0)
	{
	    for($i=0;$i<count($params);$i++)
	    {
		$trtype=$params[$i]['transactiontype'];	
		    $param_array 	= array();
		    $param_array[1] = $params[$i]['inventorykey'];		    
		    $param_array[2] = $params[$i]['routekey'];
		    $param_array[3] = $params[$i]['transactiontype'];
		    $param_array[4] = $params[$i]['routecode'];
		    $param_array[5] = $params[$i]['salesmancode'];
		    $param_array[6] = $params[$i]['transactiondate'];
		    $param_array[7] = $params[$i]['transactiontime'];
		    $param_array[8] = $params[$i]['documentnumber'];
		    $param_array[9] = $params[$i]['odometerreading'];
		    $param_array[10] = $params[$i]['transferlocationcode'];
		    $param_array[11] = $params[$i]['referencenumber'];
		    $param_array[12] = ($params[$i]['requestdate'] <= 0?date('Y-m-d'):$params[$i]['requestdate']);
		    $param_array[13] = $params[$i]['securitycode'];
		    $param_array[14] = $params[$i]['transmitindicator'];
		    $param_array[15] = $params[$i]['voidflag'];
		    $param_array[16] = ($params[$i]['hhcdocumentnumber'] <>""?$params[$i]['hhcdocumentnumber']:$params[$i]['documentnumber']);
		    $param_array[17] = $params[$i]['loadnumber'];
		    $param_array[18] = $params[$i]['refdocumentnumber'];
		    $param_array[19] = $params[$i]['currencycode'];
		    $param_array[20] = $params[$i]['actualtransactiondate'];
		    $param_array[21] = $params[$i]['inventorynumber'];
		    $param_array[22] = $params[$i]['data'];
			$param_array[23] = $params[$i]['isurgent'];
		
		$resultdata = $this->SFA_Comman->executequery('CALL sp_ws_getdata_from_inventorytransactionheader(?)',$param_array,'');
      
		if($resultdata[0][0]['lastid']>0)
		{
		   $arr12[]=array("routekey"=>$param_array[2],"detailkey"=>$params[$i]['detailkey'],"inventorykey"=>$param_array[1]);
		    $arr_inv[number_format($params[$i]['detailkey'],0)][]=($resultdata[0][0]['lastid']);
		  
		}
	    
	    }
		
	  $resultreturn['inventorytransactionheader'] = $arr12;
	  
	}else
	{
		 $resultreturn['inventorytransactionheader'] = array();
	}
	    
    }
     //For inventorytransactiondetail
    if(!is_null($this->_getParam('inventorytransactiondetail')))
    {
   	$pararminvoice=$this->_getParam('inventorytransactiondetail');	
	$withstrip=(stripslashes($pararminvoice));
        $params = json_decode($withstrip,true);
	
	$ar=array();
	$arr13=array();
	//echo count($params);
	if(count($params)>0)
	{
	    for($i=0;$i<count($params);$i++)
	    {
		    $param_array 		= array();
		    $param_array[1] 	= $params[$i]['routekey'];
		    $param_array[2] 	= $arr_inv[number_format($params[$i]['detailkey'],0)][0];
		    $param_array[3] 	= $params[$i]['transactiontypecode'];
		    $param_array[4] 	= $params[$i]['itemcode'];
		    $param_array[5] 	= $params[$i]['quantity'];			
		    $param_array[6] 	= $params[$i]['weighted'];
		    $param_array[7] 	= $params[$i]['itemprice'];
		    $param_array[8] 	= $params[$i]['batchdetailkey'];
		    $param_array[9] 	= $params[$i]['itemcaseprice'];
		    $param_array[10]	= $params[$i]['currencycode'];
			$param_array[11]	= $params[$i]['quantity'];
			$param_array[12]	= ($params[$i]['expirydate']<=0? '1900-01-01':$params[$i]['expirydate']);
			$param_array[13]	= $params[$i]['reasoncode'];
		
		$resultdata = $this->SFA_Comman->executequery('CALL sp_ws_getdata_from_inventorytransactiondetail(?)',$param_array,'');
      
		if($resultdata[0][0]['lastid']>0)
		{
		   $arr13[]=array("routekey"=>$param_array[1],"detailkey"=>$params[$i]['detailkey'],"itemcode"=>$param_array[4]);
		  
		}
	    
	    }
		if($params[0]['routekey'] > 0) {
			$param 		= array();
			$param[1]	= $params[0]['routekey'];
			$param[2]	= $params[0]['currencycode'];
			
			$resultdata = $this->SFA_Comman->executequery('CALL sp_ws_getdata_from_inventoryvariance(?)',$param,'');
		}
		
	 
	  $resultreturn['inventorytransactiondetail'] = $arr13;
	  
	}else
	{
		 $resultreturn['inventorytransactiondetail'] = array();
	}
	    
    }
    //For inventorysummarydetail
    if(!is_null($this->_getParam('inventorysummarydetail')))
    {
   	$pararminvoice=$this->_getParam('inventorysummarydetail');	
	$withstrip=(stripslashes($pararminvoice));
        $params = json_decode($withstrip,true);
	
	$ar=array();
	$arr14=array();
	//echo count($params);
	//print_r($params);
	if(count($params)>0)
	{
	    for($i=0;$i<count($params);$i++)
	    {
		    $param_array 	= array();
		    $param_array[1] = $params[$i]['inventorykey'];
		    $param_array[2] = $params[$i]['itemcode'];
		    $param_array[3] = $params[$i]['routekey'];
		    $param_array[4] = $params[$i]['weighted'];
		    $param_array[5] = $params[$i]['beginstockqty'];
		    $param_array[6] = $params[$i]['loadqty'];
		    $param_array[7] = $params[$i]['loadaddqty'];
		    $param_array[8] = $params[$i]['loadcutqty'];
		    $param_array[9] = $params[$i]['loadreqqty'];
		    $param_array[10] = $params[$i]['saleqty'];
		    $param_array[11] = $params[$i]['returnqty'];
		    $param_array[12] = $params[$i]['damagedaddqty'];
		    $param_array[13] = $params[$i]['damagedcutqty'];
		    $param_array[14] = $params[$i]['endstockqty'];
		    $param_array[15] = $params[$i]['unloadqty'];
		    $param_array[16] = $params[$i]['damagedunloadqty'];
		    $param_array[17] = $params[$i]['freesampleqty'];
		    $param_array[18] = $params[$i]['truckdamagedunloadqty'];
		    $param_array[19] = $params[$i]['stdsalesprice'];
		    $param_array[20] = $params[$i]['stdreturnprice'];
		    $param_array[21] = $params[$i]['cashsalesqty'];
		    $param_array[22] = $params[$i]['cashsalesvalue'];
		    $param_array[23] = $params[$i]['tcsalesqty'];
		    $param_array[24] = $params[$i]['tcsalesvalue'];
		    $param_array[25] = $params[$i]['gcsalesqty'];
		    $param_array[26] = $params[$i]['gcsalesvalue'];
		    $param_array[27] = $params[$i]['cashdamagedqty'];
		    $param_array[28] = $params[$i]['cashdamagedvalue'];
		    $param_array[29] = $params[$i]['tcdamagedqty'];
		    $param_array[30] = $params[$i]['tcdamagedvalue'];
		    $param_array[31] = $params[$i]['gcdamagedqty'];
		    $param_array[32] = $params[$i]['gcdamagedvalue'];
		    $param_array[33] = $params[$i]['cashreturnqty'];
		    $param_array[34] = $params[$i]['cashreturnvalue'];
		    $param_array[35] = $params[$i]['tcreturnqty'];
		    $param_array[36] = $params[$i]['tcreturnvalue'];
		    $param_array[37] = $params[$i]['gcreturnqty'];
		    $param_array[38] = $params[$i]['gcreturnvalue'];
		    $param_array[39] = $params[$i]['promoqty'];
		    $param_array[40] = $params[$i]['cashsalesitemexcisetax'];
		    $param_array[41] = $params[$i]['cashsalesitemgsttax'];
		    $param_array[42] = $params[$i]['cashreturnitemexcisetax'];
		    $param_array[43] = $params[$i]['cashreturnitemgsttax'];
		    $param_array[44] = $params[$i]['cashdamageditemexcisetax'];
		    $param_array[45] = $params[$i]['cashdamageditemgsttax'];
		    $param_array[46] = $params[$i]['cashfgitemexcisetax'];
		    $param_array[47] = $params[$i]['cashfgitemgsttax'];
		    $param_array[48] = $params[$i]['cashpromoitemexcisetax'];
		    $param_array[49] = $params[$i]['cashpromoitemgsttax'];
		    $param_array[50] = $params[$i]['tcsalesitemexcisetax'];
		    $param_array[51] = $params[$i]['tcsalesitemgsttax'];
		    $param_array[52] = $params[$i]['tcreturnitemexcisetax'];
		    $param_array[53] = $params[$i]['tcreturnitemgsttax'];
		    $param_array[54] = $params[$i]['tcdamageditemexcisetax'];
		    $param_array[55] = $params[$i]['tcdamageditemgsttax'];
		    $param_array[56] = $params[$i]['tcfgitemexcisetax'];
		    $param_array[57] = $params[$i]['tcfgitemgsttax'];
		    $param_array[58] = $params[$i]['tcpromoitemexcisetax'];
		    $param_array[59] = $params[$i]['tcpromoitemgsttax'];
		    $param_array[60] = $params[$i]['gcsalesitemexcisetax'];
		    $param_array[61] = $params[$i]['gcsalesitemgsttax'];
		    $param_array[62] = $params[$i]['gcreturnitemexcisetax'];
		    $param_array[63] = $params[$i]['gcreturnitemgsttax'];
		    $param_array[64] = $params[$i]['gcdamageditemexcisetax'];
		    $param_array[65] = $params[$i]['gcdamageditemgsttax'];
		    $param_array[66] = $params[$i]['gcfgitemexcisetax'];
		    $param_array[67] = $params[$i]['gcfgitemgsttax'];
		    $param_array[68] = $params[$i]['gcpromoitemexcisetax'];
		    $param_array[69] = $params[$i]['gcpromoitemgsttax'];
		    $param_array[70] = $params[$i]['batchdetailkey'];
		    $param_array[71] = $params[$i]['stdsalescaseprice'];
		    $param_array[72] = $params[$i]['stdreturncaseprice'];
		    $param_array[73] = $params[$i]['expiryqty'];
		    $param_array[74] = $params[$i]['stdgoodreturncaseprice'];
		    $param_array[75] = $params[$i]['stdgoodreturnprice'];
		    $param_array[76] = $params[$i]['currencycode'];
		    $param_array[77] = $params[$i]['returnfreeqty'];
		    $param_array[78] = $params[$i]['damageqty'];
		    $param_array[79] = $params[$i]['expdmgfreeqty'];
		    $param_array[80] = $params[$i]['expunloadqty'];
		    $param_array[81] = $params[$i]['dmgunloadqty'];
		    $param_array[82] = $params[$i]['expdmgfreeunloadqty'];
		    $param_array[83] = $params[$i]['rentqty'];
		    $param_array[84] = $params[$i]['mdat'];
		    $param_array[85] = $params[$i]['loadadjustqty'];
		
		$resultdata = $this->SFA_Comman->executequery('CALL sp_ws_getdata_from_inventorysummarydetail(?)',$param_array,'');
      
		if($resultdata[0][0]['lastid']>0)
		{
		   $arr14[]=array("routekey"=>$param_array[3],"itemcode"=>$param_array[2],"inventorykey"=>$param_array[1]);
		  
		}
	    
	    }
	 
	  $resultreturn['inventorysummarydetail'] = $arr14;
	  
	}else
	{
		 $resultreturn['inventorysummarydetail'] = array();
	}
	    
    }
    //For nonservicedcustomer
    if(!is_null($this->_getParam('nonservicedcustomer')))
    {
   	$pararminvoice=$this->_getParam('nonservicedcustomer');	
	$withstrip=(stripslashes($pararminvoice));
        $params = json_decode($withstrip,true);
	
	$ar=array();
	 $arr15=array();
	//echo count($params);
	if(count($params)>0)
	{
	    for($i=0;$i<count($params);$i++)
	    {
		    $param_array 	= array();
		    $param_array[1] = $params[$i]['routekey'];
		    $param_array[2] = $params[$i]['customercode'];
		    $param_array[3] = $params[$i]['reasoncode'];
		
		$resultdata = $this->SFA_Comman->executequery('CALL sp_ws_getdata_from_nonservicedcustomer(?)',$param_array,'');
      
		if($resultdata[0][0]['lastid']>0)
		{
		   $arr15[]=array("routekey"=>$param_array[1],"customercode"=>$param_array[2]);
		  
		}
	    
	    }
	 
	  $resultreturn['nonservicedcustomer'] = $arr15;
	  
	}else
	{
		 $resultreturn['nonservicedcustomer'] = array();
	}
	    
    }
     //For surveyauditdetail
    if(!is_null($this->_getParam('surveyauditdetail')))
    {
   	$pararminvoice=$reqval['surveyauditdetail'];	
	$withstrip=(stripslashes($pararminvoice));
        $params = json_decode($withstrip,true);
	
	$ar=array();
	$arr16=array();
	//echo count($params);
	if(count($params)>0)
	{
	    for($i=0;$i<count($params);$i++)
	    {
		    $param_array 	= array();
		    $param_array[1] = $params[$i]['routekey'];
		    $param_array[2] = $params[$i]['visitkey'];
		    $param_array[3] = $params[$i]['surveydefkey'];
		    $param_array[4] = $params[$i]['surveypage'];
		    $param_array[5] = $params[$i]['surveyindex'];
		    $param_array[6] = $params[$i]['surveyrectype'];
		    $param_array[7] = $params[$i]['lookuptype'];
		    $param_array[8] = $params[$i]['surveyresponse'];
		
		$resultdata = $this->SFA_Comman->executequery('CALL sp_ws_getdata_from_surveyauditdetail(?)',$param_array,'');
      
		if($resultdata[0][0]['lastid']>0)
		{
		   $arr16[]=array("routekey"=>$param_array[1],"visitkey"=>$param_array[2],"surveydefkey"=>$param_array[3]);
		  
		}
	    
	    }
	 
	  $resultreturn['surveyauditdetail'] = $arr16;
	  
	}else
	{
		 $resultreturn['surveyauditdetail'] = array();
	}
	    
    }
    //For posequipmentdetail
    if(!is_null($this->_getParam('posequipmentchangedetail')))
    {
   	$pararminvoice=$reqval['posequipmentchangedetail'];	
	$withstrip=(stripslashes($pararminvoice));
        $params = json_decode($withstrip,true);
	
	$ar=array();
	 $arr17=array();
	//echo count($params);
	if(count($params)>0)
	{
	    for($i=0;$i<count($params);$i++)
	    {
		    $param_array 	= array();
		    $param_array[1] = $params[$i]['routekey'];
		    $param_array[2] = $params[$i]['visitkey'];
		    $param_array[3] = $params[$i]['posaction'];
		    $param_array[4] = $params[$i]['itemcode'];
		    $param_array[5] = $params[$i]['quantity'];
		    $param_array[6] = $params[$i]['serialnumber'];
		    $param_array[7] = $params[$i]['instructioncode'];
		
		$resultdata = $this->SFA_Comman->executequery('CALL sp_ws_getdata_from_posequipmentchangedetail(?)',$param_array,'');
      
		if($resultdata[0][0]['lastid']>0)
		{
		    $arr17[]=array("routekey"=>$param_array[1],"visitkey"=>$param_array[2],"itemcode"=>$param_array[4]);
		  
		}
	    
	    }
	 
	  $resultreturn['posequipmentchangedetail'] = $arr17;
	  
	}else
	{
		 $resultreturn['posequipmentchangedetail'] = array();
	}
	    
    }
    //For posmaster
    if(!is_null($this->_getParam('posmaster')))
    {
   	$pararminvoice=$reqval['posmaster'];	
	$withstrip=(stripslashes($pararminvoice));
        $params = json_decode($withstrip,true);
	
	$ar=array();
	$arr18=array();
	//echo count($params);
	if(count($params)>0)
	{
	    for($i=0;$i<count($params);$i++)
	    {
		    $param_array 	= array();
		    $param_array[1] = $params[$i]['itemcode'];
		    $param_array[2] = $params[$i]['itemdescription'];
		    $param_array[3] = $params[$i]['arbitemdescription'];
		    $param_array[4] = $params[$i]['itemvalue'];
		    $param_array[5] = $params[$i]['inventorytype'];
		    $param_array[6] = $params[$i]['created'];
		    $param_array[7] = $params[$i]['cdat'];
		    $param_array[8] = $params[$i]['modified'];
		    $param_array[9] = $params[$i]['mdat'];
		    $param_array[10] = $params[$i]['activestatus'];
		
		$resultdata = $this->SFA_Comman->executequery('CALL sp_ws_getdata_from_posmaster(?)',$param_array,'');
      
		if($resultdata[0][0]['lastid']>0)
		{
		    $arr18[]=array("itemcode"=>$param_array[1]);
		  
		}
	    
	    }
	 
	  $resultreturn['posmaster'] = $arr18;
	  
	}else
	{
		 $resultreturn['posmaster'] = array();
	}
	    
    }
    
    
    
    if(!is_null($this->_getParam('sigcapturedata')))
    {
	
   	$pararminvoice=$this->_getParam('sigcapturedata');	
	$withstrip=(stripslashes($pararminvoice));
       // print_r($withstrip);
	$params = $this->jsonDecode($pararminvoice,true);
	$arr20=array();
	if(count($params)>0)
	{
	  
	    for($i=0;$i<count($params);$i++)
	    {
		    $param_array 	= array();
		     if($params[$i]['transaction_type'] =='4')
		{
		   $transctionkey=$arr_ordtrn[number_format($params[$i]['transactionkey'],0)][0]; 
		}
		else
		{
		    $transctionkey=$arr_trn[number_format($params[$i]['transactionkey'],0)][0];
		}
		    $param_array[1] = $params[$i]['routekey'];
		    $param_array[2] = $params[$i]['visitkey'];
		    $param_array[3] = $transctionkey;
		    $param_array[4] = $params[$i]['customercode'];
		    $param_array[5] = $params[$i]['documentnumber'];
		    $param_array[6] = $params[$i]['transactiondate'];
		    $param_array[7] = $params[$i]['transactiontime'];
		    $param_array[8] = $params[$i]['balancedueamount'];
		    //$param_array[9] = $params[$i]['balancedueamount'];
		    
		   $param_array[9] = $params[$i]['signaturedata'];
		    $param_array[10] = $params[$i]['transaction_type'];
		    //$param_array[9]=filter_input(INPUT_POST, 'signaturedata', FILTER_UNSAFE_RAW);
		   
		    $resultdata = $this->SFA_Comman->executequery('CALL sp_ws_getdata_from_sigcaptchdata(?)',$param_array,'');
		    
		if($resultdata[0][0]['lastid']>0)
		{
		    $arr20[]=array("routekey"=>$param_array[1],"visitkey"=>$param_array[2],"transactionkey"=>$params[$i]['transactionkey']);
		
		}
		    
	    }
	     $resultreturn['sigcapturedata'] = $arr20;
	}
	else
	{
		 $resultreturn['sigcapturedata'] = array();
	}
    }
    
    
    
    
    //For customerinventory
    if(!is_null($this->_getParam('customerinventorydetail')))
    {
   	$pararminvoice=$this->_getParam('customerinventorydetail');	
	$withstrip=(stripslashes($pararminvoice));
        $params = json_decode($withstrip,true);
	
	$ar=array();
	 $arr23=array();
	//print_r($params);
	//echo count($params);
	if(count($params)>0)
	{
	    for($i=0;$i<count($params);$i++)
	    {
		    $param_array 	= array();
		   
		    $param_array[1] = $params[$i]['routekey'];
		    $param_array[2] = $params[$i]['visitkey'];
		    $param_array[3] = $params[$i]['itemcode'];
		    $param_array[4] = $params[$i]['weighted'];
		    $param_array[5] = $params[$i]['qtyloc1case'];
		    $param_array[6] = $params[$i]['catchweightqtyloc1'];
		    $param_array[7] = $params[$i]['qtyloc1each'];
		    $param_array[8] = $params[$i]['qtyloc2case'];
		    $param_array[9] = $params[$i]['catchweightqtyloc2'];
		    $param_array[10] = $params[$i]['qtyloc2each'];
		    $param_array[11] = $params[$i]['qtyloc3case'];
		    $param_array[12] = $params[$i]['catchweightqtyloc3'];
		    $param_array[13] = $params[$i]['qtyloc3each'];
		    $param_array[14] = $params[$i]['shelfstockcase'];
		    $param_array[15] = $params[$i]['shelfstockcatchweightqty'];
		    $param_array[16] = $params[$i]['shelfstockeach'];
		    $param_array[17] = $params[$i]['oldestcode'];
				    
		$resultdata = $this->SFA_Comman->executequery('CALL sp_ws_get_from_customerinventorydetail(?)',$param_array,'');
      
		if($resultdata[0][0]['lastid']>0)
		{
		   // $arr19[]=array("itemcode"=>$param_array[1]);
		    $arr23[]=array("routekey"=>$param_array[1],"visitkey"=>$param_array[2],"itemcode"=>$param_array[3]);
		    //$arr22[]=array();
		  
		}
	    
	    }
	 
	  $resultreturn['customerinventorydetail'] = $arr23;
	  
	}else
	{
		 $resultreturn['customerinventorydetail'] = array();
	}
    }
    //End
    //For routesequencecustomerstatus
    if(!is_null($this->_getParam('routesequencecustomerstatus')))
    {
   	$pararminvoice=$this->_getParam('routesequencecustomerstatus');	
	$withstrip=(stripslashes($pararminvoice));
        $params = json_decode($withstrip,true);
	
	$ar=array();
	$arr24=array();
	//print_r($params);
	//echo count($params);
	if(count($params)>0)
	{
	    for($i=0;$i<count($params);$i++)
	    {
		    $param_array 	= array();		   
		    $param_array[1] = $params[$i]['routekey'];
		    $param_array[2] = $params[$i]['seqweeknumber'];
		    $param_array[3] = $params[$i]['seqweekday'];
		    $param_array[4] = $params[$i]['routecode'];
		    $param_array[5] = $params[$i]['customercode'];
		    $param_array[6] = $params[$i]['sequencenumber'];
		    $param_array[7] = $params[$i]['schelduledflag'];
		    $param_array[8] = $params[$i]['servicedflag'];
		    $param_array[9] = $params[$i]['scannedflag'];
			//print_r($param_array);	    
		$resultdata = $this->SFA_Comman->executequery('CALL sp_ws_get_from_routesequencecustomerstatus(?)',$param_array,'');
      //print_r($resultdata);
		if($resultdata[0][0]['lastid']>0)
		{
		   // $arr19[]=array("itemcode"=>$param_array[1]);
		    $arr24[]=array("routekey"=>$param_array[1],"customercode"=>$param_array[5]);
		    //$arr22[]=array();
		  
		}
	    
	    }
	 
	
	  $resultreturn['routesequencecustomerstatus'] = $arr24;
	  
	}else
	{
		 $resultreturn['routesequencecustomerstatus'] = array();
	}
    }
    //End
    //For nosalesheader
    if(!is_null($this->_getParam('nosalesheader')))
    {
	//echo "<pre>";
   	$pararminvoice=$this->_getParam('nosalesheader');	
	$withstrip=(stripslashes($pararminvoice));
        $params = json_decode($withstrip,true);
	//print_r($params);
	$ar=array();
	$arr25=array();
	//print_r($params);
	//echo count($params);
	if(count($params)>0)
	{
	    for($i=0;$i<count($params);$i++)
	    {
		    $param_array 	= array();	   
		    $param_array[1] = $params[$i]['transactionkey'];
		    $param_array[2] = $params[$i]['routekey'];
		    $param_array[3] = $params[$i]['visitkey'];
		    $param_array[4] = $params[$i]['documentnumber'];
		    $param_array[5] = $params[$i]['invoicenumber'];
		    $param_array[6] = $params[$i]['routecode'];
		    $param_array[7] = $params[$i]['salesmancode'];
		    $param_array[8] = $params[$i]['transactiondate'];
		    $param_array[9] = $params[$i]['transactiontime'];
		    $param_array[10] = $params[$i]['nosalereasoncode'];
		    $param_array[11] = $params[$i]['voidflag'];
		    $param_array[12] = $params[$i]['transmitindicator'];
		    $param_array[13] = $params[$i]['customercode'];
		    $param_array[14] = $params[$i]['hhcdocumentnumber'];
		    $param_array[15] = $params[$i]['hhcinvoicenumber'];
		    $param_array[16] = $params[$i]['data'];
				    
		$resultdata = $this->SFA_Comman->executequery('CALL sp_ws_get_from_nosalesheader(?)',$param_array,'');
      
		if($resultdata[0][0]['lastid']>0)
		{
		   // $arr19[]=array("itemcode"=>$param_array[1]);
		    $arr25[]=array("transactionkey"=>$param_array[1]);
		    //$arr22[]=array();
		  
		}
	    
	    }
	// print_r($arr25);
	  $resultreturn['nosalesheader'] = $arr25;
	  
	}else
	{
		 $resultreturn['nosalesheader'] = array();
	}
    }
    //End
    //For routegoal
    if(!is_null($this->_getParam('routegoal')))
    {
   	$pararminvoice=$this->_getParam('routegoal');	
	$withstrip=(stripslashes($pararminvoice));
        $params = json_decode($withstrip,true);
	
	$ar=array();
	$arr26=array();
	//print_r($params);
	//echo count($params);
	if(count($params)>0)
	{
	    for($i=0;$i<count($params);$i++)
	    {
		    $param_array 	= array();
		    $param_array[1] = $params[$i]['primary_key'];
		    $param_array[2] = $params[$i]['routecode'];
		    $param_array[3] = $params[$i]['salesmancode'];
		    $param_array[4] = $params[$i]['packagenumber'];
		    $param_array[5] = $params[$i]['todaysgoal'];
		    $param_array[6] = $params[$i]['todaysachieve'];
		    $param_array[7] = $params[$i]['quotadesckey1'];
		    $param_array[8] = $params[$i]['quotagoal1'];
		    $param_array[9] = $params[$i]['quotaachieve1'];
		    $param_array[10] = $params[$i]['quotareset1'];
		    $param_array[11] = $params[$i]['quotadesckey2'];
		    $param_array[12] = $params[$i]['quotagoal2'];
		    $param_array[13] = $params[$i]['quotaachieve2'];
		    $param_array[14] = $params[$i]['quotareset2'];
		    $param_array[15] = $params[$i]['quotadesckey3'];
		    $param_array[16] = $params[$i]['quotagoal3'];
		    $param_array[17] = $params[$i]['quotaachieve3'];
		    $param_array[18] = $params[$i]['quotareset3'];
		    $param_array[19] = $params[$i]['created'];
		    $param_array[20] = $params[$i]['cdat'];
		    $param_array[21] = $params[$i]['modified'];
		    $param_array[22] = $params[$i]['mdat'];
		    $param_array[23] = $params[$i]['mmonth'];
		    $param_array[24] = $params[$i]['yyear'];
			$param_array[25] = $params[$i]['fromdate'];
			$param_array[26] = $params[$i]['todate'];
			$param_array[27] = $params[$i]['quantity'];
			$param_array[28] = $params[$i]['achievequantity'];
				    
		$resultdata = $this->SFA_Comman->executequery('CALL sp_ws_get_from_rotuegoal(?)',$param_array,'');
      
		if($resultdata[0][0]['lastid']>0)
		{
		   // $arr19[]=array("itemcode"=>$param_array[1]);
		    $arr26[]=array("primary_key"=>$param_array[1]);
		    //$arr22[]=array();
		  
		}
	    
	    }
	 
	  $resultreturn['routegoal'] = $arr26;
	  
	}else
	{
		 $resultreturn['routegoal'] = array();
	}
    }
    //End
    //For routegoal
    if(!is_null($this->_getParam('customer_foc_balance')))
    {
   	$pararminvoice=$this->_getParam('customer_foc_balance');	
	$withstrip=(stripslashes($pararminvoice));
        $params = json_decode($withstrip,true);
	
	$ar=array();
	$arr27=array();
	//print_r($params);
	//echo count($params);
	if(count($params)>0)
	{
	    for($i=0;$i<count($params);$i++)
	    {
		    $param_array 	= array();
		    //$param_array[1] = $params[$i]['contractid'];
		    //$param_array[2] = $params[$i]['customercode'];
		    //$param_array[3] = $params[$i]['itemcode'];
		    //$param_array[4] = $params[$i]['freequantity'];
		    //$param_array[5] = $params[$i]['editdate'];
		    //$param_array[6] = $params[$i]['remarks'];
			
			$param_array[1] = $params[$i]['customercode'];
		    $param_array[2] = $params[$i]['itemcode'];
		    $param_array[3] = $params[$i]['originalqty'];
		    $param_array[4] = $params[$i]['balanceqty'];
		    $param_array[5] = $params[$i]['contractid'];
		    $param_array[6] = $params[$i]['startdate'];
				    
			// customercode,itemcode,originalqty,balanceqty,contractid,startdate
		$resultdata = $this->SFA_Comman->executequery('CALL sp_ws_getfrom_customer_foc_balance(?)',$param_array,'');
      
		if($resultdata[0][0]['customercode']>0)
		{
		   // $arr19[]=array("itemcode"=>$param_array[1]);
		    $arr27[]=array("customercode"=>$param_array[2],"itemcode"=>$param_array[3]);
		    //$arr22[]=array();
		  
		}
	    
	    }
	 
	  $resultreturn['customer_foc_balance'] = $arr27;
	  
	}else
	{
		 $resultreturn['customer_foc_balance'] = array();
	}
    }
    //For enddaydeail
    if(!is_null($this->_getParam('enddaydetail')))
    {
   	$pararminvoice=$this->_getParam('enddaydetail');	
	$withstrip=(stripslashes($pararminvoice));
        $params = json_decode($withstrip,true);
	
	$ar=array();
	$arr28=array();
	//print_r($params);
	//echo count($params);
	if(count($params)>0)
	{
	    for($i=0;$i<count($params);$i++)
	    {
		    $param_array 	= array();
		    $param_array[1] = $params[$i]['routekey'];
		    $param_array[2] = $params[$i]['detailtypecode'];
		    $param_array[3] = $params[$i]['listtypecode'];
		    $param_array[4] = $params[$i]['amount'];
		    $param_array[5] = $params[$i]['currencycode'];		    
				    
		$resultdata = $this->SFA_Comman->executequery('CALL sp_ws_getfrom_table_enddaydetail(?)',$param_array,'');
      
		if($resultdata[0][0]['lastid']>0)
		{
		   // $arr19[]=array("itemcode"=>$param_array[1]);
		    $arr28[]=array("routekey"=>$param_array[1]);
		    //$arr22[]=array();
		  
		}
	    
	    }
	 
	  $resultreturn['enddaydetail'] = $arr28;
	  
	}else
	{
		 $resultreturn['enddaydetail'] = array();
	}
    }
    //End
	//Customer Images
	if(!is_null($this->_getParam('customerimages'))) {
		$pararminvoice=$this->_getParam('customerimages');	
		$withstrip=(stripslashes($pararminvoice));
			$params = json_decode($withstrip,true);
		
		$ar=array();
		$arr_custimage=array();
		//print_r($params);
		//echo count($params);
		if(count($params)>0)
		{
			for($i=0;$i<count($params);$i++)
			{
				$param_array 	= array();
				$param_array[1] = $params[$i]['imagename'];
				$param_array[2] = $params[$i]['customercode'];
				$param_array[3] = $params[$i]['imageno'];
				$param_array[4] = $params[$i]['imagepath'];
				$param_array[5] = $params[$i]['routecode'];
				$param_array[6] = $params[$i]['routekey'];
				$param_array[7] = $params[$i]['transactiondate'];
				$param_array[8] = $params[$i]['transactiontime'];
				$param_array[9] = $params[$i]['visitkey'];
				
				$resultdata = $this->SFA_Comman->executequery('CALL sp_add_merchandize_index_addcustomerimages(?)',$param_array,'');
		  
				if($resultdata[0][0]['lastid']>0) {
					// $arr19[]=array("itemcode"=>$param_array[1]);
					$arr_custimage[]=array("routekey"=>$param_array[1]);
					//$arr22[]=array();
				}
			}			 
			$resultreturn['customerimages'] = $arr_custimage;
		} else {
			$resultreturn['customerimages'] = array();
		}
	}
	
	//For posmaster
    if(!is_null($this->_getParam('t_access_override_log'))) {
		
		$override_log	=	$reqval['t_access_override_log'];
		$withstrip		=	(stripslashes($override_log));
		$params 		= 	json_decode($withstrip,true);
	
		$ar=array();
		$arr23=array();
		//echo count($params);
		if(count($params)>0)
		{
			for($i=0;$i<count($params);$i++)
			{
				$param_array 	= array();
				$param_array[1] = $params[$i]['routekey'];
				$param_array[2] = $params[$i]['visitkey'];
				$param_array[3] = $params[$i]['type'];
				$param_array[4] = $params[$i]['routecode'];
				$param_array[5] = $params[$i]['customercode'];
				$param_array[6] = $params[$i]['salesmancode'];
				$param_array[7] = $params[$i]['featureid'];
				$param_array[8] = $params[$i]['accesskey'];
				$param_array[9] = $params[$i]['accesstime'];
				$param_array[10] = $params[$i]['voidflag'];
				$param_array[11] = $params[$i]['validflag'];
			
			$resultdata = $this->SFA_Comman->executequery('CALL sp_ws_getdata_from_t_access_override_log(?)',$param_array,'');
		  
			if($resultdata[0][0]['lastid']>0)
			{
				$arr23[]=array("routekey"=>$param_array[1],"visitkey"=>$param_array[2],"featureid"=>$param_array[7]);
			  
			}
			
			}
		 
		  $resultreturn['t_access_override_log'] = $arr23;
		  
		} else {
			$resultreturn['t_access_override_log'] = array();
		}
    }
	
	//customer distribution check
    if(!is_null($this->_getParam('customerdistributioncheck')))
    {
		$pararminvoice=$this->_getParam('customerdistributioncheck');	
		$withstrip=(stripslashes($pararminvoice));
			$params = json_decode($withstrip,true);
		
		$ar=array();
		$arr24=array();
		//print_r($params);
		//echo count($params);
		if(count($params)>0)
		{
			for($i=0;$i<count($params);$i++)
			{
				$param_array 	= array();
				$param_array[1] = $params[$i]['routekey'];		    
				$param_array[2] = $params[$i]['customercode'];
				$param_array[3] = $params[$i]['visitkey'];		  
				$param_array[4] = $params[$i]['itemcode'];
				$param_array[5] = $params[$i]['qty'];
				$param_array[6] = $params[$i]['distributionkey'];
						
				$resultdata = $this->SFA_Comman->executequery('CALL sp_ws_get_from_customerdistributioncheck(?)',$param_array,'');
		  
				if($resultdata[0][0]['lastid']>0)
				{
					$arr24[]=array("routekey"=>$param_array[1],"customercode"=>$param_array[2],"visitkey"=>$param_array[3],"itemcode"=>$param_array[4]);
				   
				}
			
			}
		 
		  $resultreturn['customerdistributioncheck'] = $arr24;
		  
		}else
		{
			 $resultreturn['customerdistributioncheck'] = array();
		}
    }
	
	if(!is_null($this->_getParam('customerinventorycheck')))
    {
   	$pararminvoice=$this->_getParam('customerinventorycheck');	
	$withstrip=(stripslashes($pararminvoice));
        $params = json_decode($withstrip,true);
	
	$ar=array();
	$arr25=array();
	//print_r($params);
	//echo count($params);
	if(count($params)>0)
	{
	    for($i=0;$i<count($params);$i++)
	    {
		    $param_array 	= array();
		    $param_array[1] = $params[$i]['routekey'];
		    $param_array[2] = $params[$i]['visitkey'];
		    $param_array[3] = $params[$i]['itemcode'];
		    $param_array[4] = $params[$i]['weighted'];
		    $param_array[5] = $params[$i]['qtyloc1case'];
		    $param_array[6] = $params[$i]['catchweightqtyloc1'];
		    $param_array[7] = $params[$i]['qtyloc1each'];
		    $param_array[8] = $params[$i]['qtyloc2case'];
		    $param_array[9] = $params[$i]['catchweightqtyloc2'];
		    $param_array[10] = $params[$i]['qtyloc2each'];
		    $param_array[11] = $params[$i]['qtyloc3case'];
		    $param_array[12] = $params[$i]['catchweightqtyloc3'];
		    $param_array[13] = $params[$i]['qtyloc3each'];
		    $param_array[14] = $params[$i]['shelfstockcase'];
		    $param_array[15] = $params[$i]['shelfstockcatchweightqty'];
		    $param_array[16] = $params[$i]['shelfstockeach'];
		    $param_array[17] = $params[$i]['oldestcode'];
		     $param_array[18] = $params[$i]['expiry_date'];
				    
		$resultdata = $this->SFA_Comman->executequery('CALL sp_ws_get_from_customerinventorycheck(?)',$param_array,'');
      
		if($resultdata[0][0]['lastid']>0)
		{
		   // $arr19[]=array("itemcode"=>$param_array[1]);
		    $arr25[]=array("routekey"=>$param_array[1],"visitkey"=>$param_array[2],"itemcode"=>$param_array[3]);
		    //$arr22[]=array();
		  
		}
	    
	    }
	 
	  $resultreturn['customerinventorycheck'] = $arr25;
	  
	}else
	{
		 $resultreturn['customerinventorycheck'] = array();
	}
    }
	
    //Sync log
		    $param_array 	= array();
		    $param_array[1] = $this->_getParam('userid');
		    $param_array[2] = $this->_getParam('routecode');
		    $param_array[3] = $this->_getParam('routekey');
		    $param_array[4] = $this->_getParam('routeclosed');
		    $param_array[5] = '2';
		//print_r($param_array);
		    $resultdata = $this->SFA_Comman->executequery('CALL sp_ws_instertion_tbl_synclog(?)',$param_array,'');
			
			$result_route_close = $this->SFA_Comman->executequery('CALL sp_check_isroute_closed(?)',$this->_getParam('routekey'),'');
			$isclosed 			= $result_route_close[0][0]['counter'];
		
			if($isclosed > 0)
				{
				
				// call suggestedsales procedure
				$suggestedsales = $this->SFA_Comman->executequery('CALL recallpostsuggestedsalesinvoiceafterroutefilter(?)',$this->_getParam('routecode'),'');
				
				// call averagesales
				$averagesales 	= $this->SFA_Comman->executequery('CALL sp_cron_average_sales_quantity("?")',$this->_getParam('routecode'),'');
				}
			
	echo json_encode($resultreturn);

	/*
	if($isclosed > 0)
	{
		$routeKeyArr= array($this->_getParam('routekey'));
		$this->SFA_Comman->ociConnect();
        $this->OracleDBSync = new SFA_DataSyncExport();
	    $this->OracleDBSync->updateOracleDBWithExportData($routeKeyArr);
        $this->SFA_Comman->ociClose();
	}
	*/
	
	exit;
    }
	   
    function stripslashes_deep($value)
    {
	if(is_array($value))
	{
	    echo "array";
	}else
	{
	    echo "not array";
	}
    $value = is_array($value) ?array_map('stripslashes_deep', $value) : stripslashes($value);

    return $value;
    }
    
    
    //End 
}