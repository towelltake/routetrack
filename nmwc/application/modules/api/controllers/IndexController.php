<?php

class Api_IndexController extends Api_Library_Controller_Action_Abstract
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

    /**
    * @name       loginAction
    * @since      16-03-2012
    * @version    Release: 1
    * @author     PM <pankit@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This is the login Action or the default Action where there is no Action specified.
    *
    */
    public function loginAction()
    {

    }


    /**
    * @name       indexAction
    * @since      16-03-2012
    * @version    Release: 1
    * @author     PM <pankit@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This is the index Action or the default Action where there is no Action specified.
    *
    */
    public function indexAction()
    {

    }


 
    /**
    * @name       itemlistbyrouteAction
    * @since      22-03-2012
    * @version    Release: 1
    * @author     PM <pankit@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param      username
    * @param      password
    * @param      createdate
    * @example1 api/index/salesmanlogin/username/dummy/password/123456/createdate/2010-10-28
    * This is the salesmanmessages Action.
    */
    public function salesmanloginAction(){
         //Request Parameter
        $this->view->params = $params = $this->getRequest()->getParams();

        $param_array = array();
	//Route id
        $param_array[1] = $params['username'];
        //for salesmanid
        $param_array[2] = trim($params['password']);
		//for deviceid
        $param_array[3] = $params['deviceid'];

	$result = $this->SFA_Comman->executequery('CALL sp_ws_salesman_login(?,?,?)',$param_array,'');
	$resultdata = (count($result[0]) > 0) ? $result[0]:array();
         //json output
	
        header("Access-Control-Allow-Origin: *");
        echo json_encode($resultdata);
    }

     /**
    * @name       companyidbydeviceAction
    * @since      23-03-2012
    * @version    Release: 1
    * @author     PM <pankit@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param      deviceid
    * @example1 api/index/companyidbydevice/deviceid/A2006
    * This is the salesmanmessages Action.
    */
    public function companyidbydeviceAction(){
        //Request Parameter
        $this->view->params = $params = $this->getRequest()->getParams();

        $param_array = array();
	//Route id
        $param_array[1] = $params['deviceid'];

	$result = $this->SFA_Comman->executequery('CALL sp_ws_companyid_device(?)',$param_array,'');
	$resultver = $this->SFA_Comman->executequery('CALL sp_ws_app_version(?)',$param_array,'');
       $resultdatacomp = $result[0];

         //json output
        /*if(count($resultdata) <= 0){
            $resultdata = array();
        }*/
		if(count($resultdatacomp) <= 0){
            $resultdata = array();
        }else{
			$resultdata[] = array("status"=>"1","url" => $resultver[0][0]['url'],"ver" => $resultver[0][0]['verno']);
		}
        header("Access-Control-Allow-Origin: *");
        echo json_encode($resultdata);
    }

    /**
    * @name       getsyncdataAction
    * @since      23-03-2012
    * @version    Release: 1
    * @author     PM <pankit@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param      userid
    * @param      deviceid
    * @param      routeid
    * @example1 api/index/getsyncdata/userid/1/deviceid/a2006/routeid/1
    * This is the salesmanmessages Action.
    */
	public function getsyncdataAction(){
         //Request Parameter
        $this->view->params = $params = $this->getRequest()->getParams();

        $param_array    = array();	
        $param_array[1] = $params['userid'];
        $param_array[2] = $params['deviceid'];
        $param_array[3] = $params['routeid'];
        $param_array[4] = $params['mdate'];
		
		$x = $params['table'];
		
		$result = array();
		
		if($x==1){ // setting
			
			$resultdata = $this->SFA_Comman->executequery('CALL sp_ws_syncicsdata_setting(?,?,?,?)',$param_array,'');
			
			$result['ControlPanel'] 			= (count($resultdata[0]) > 0) ? $resultdata[0]:array();
			$result['Setup'] 					= (count($resultdata[1]) > 0) ? $resultdata[1]:array();
			$result['companydetail']			= (count($resultdata[2]) > 0) ? $resultdata[2]:array();
			$result['SalesmanMaster'] 			= (count($resultdata[3]) > 0) ? $resultdata[3]:array();
			$result['RouteMaster'] 				= (count($resultdata[4]) > 0) ? $resultdata[4]:array();
			$result['startendday'] 				= (count($resultdata[5]) > 0) ? $resultdata[5]:array();
			$result['synctime'] 				= (count($resultdata[6]) > 0) ? $resultdata[6]:array();
			$result['CurrencyMaster'] 			= (count($resultdata[7]) > 0) ? $resultdata[7]:array();
			
			$resultdata = $this->SFA_Comman->executequery('CALL sp_ws_syncicsdata_itemmust(?,?,?,?)',$param_array,'');
			
			$result['itemmustheader'] 	= (count($resultdata[0]) > 0) ? $resultdata[0]:array();
			$result['itemmustdetail'] 	= (count($resultdata[1]) > 0) ? $resultdata[1]:array();
			/*$result['distributioncheckheader'] 	= (count($resultdata[2]) > 0) ? $resultdata[2]:array();
			$result['distributioncheckdetail'] 	= (count($resultdata[3]) > 0) ? $resultdata[3]:array();*/
			
		} elseif($x==2){ // items
			
			$resultdata = $this->SFA_Comman->executequery('CALL sp_ws_syncicsdata_items(?,?,?,?)',$param_array,'');
			
			$result['itemgroup'] 				= (count($resultdata[0]) > 0) ? $resultdata[0]:array();
			$result['ItemMaster'] 				= (count($resultdata[1]) > 0) ? $resultdata[1]:array();
			$result['itempackagemaster'] 		= (count($resultdata[2]) > 0) ? $resultdata[2]:array();
			$result['routegoal'] 				= (count($resultdata[3]) > 0) ? $resultdata[3]:array();
			$result['avgsalesqty'] 				= (count($resultdata[4]) > 0) ? $resultdata[4]:array();
			$result['outletitemcodes'] 			= (count($resultdata[5]) > 0) ? $resultdata[5]:array();
			$result['taxmaster'] 				= (count($resultdata[6]) > 0) ? $resultdata[6]:array();
			
			
		} elseif($x==3){ // Inventory
			
			$resultdata = $this->SFA_Comman->executequery('CALL sp_ws_syncicsdata_inventory(?,?,?,?)',$param_array,'');
			
			$result['startingloaddetail'] 		= (count($resultdata[0]) > 0) ? $resultdata[0]:array();
			$result['inventorysummarydetail'] 	= (count($resultdata[1]) > 0) ? $resultdata[1]:array();
			
		} elseif($x==4){ // Customers
			
			$resultdata = $this->SFA_Comman->executequery('CALL sp_ws_syncicsdata_customers(?,?,?,?)',$param_array,'');
			
			$result['CustomerMaster'] 			= (count($resultdata[0]) > 0) ? $resultdata[0]:array();
			//$result['CustomerMaster2'] 			= (count($resultdata[1]) > 0) ? $resultdata[1]:array();
			//$result['CustomerMaster']			= array_merge($result['CustomerMaster1'],$result['CustomerMaster2']);
			$result['salescalender'] 			= (count($resultdata[1]) > 0) ? $resultdata[1]:array();
			$result['routesequence'] 			= (count($resultdata[2]) > 0) ? $resultdata[2]:array();
			$result['customerinvoice'] 			= (count($resultdata[3]) > 0) ? $resultdata[3]:array();
			
		} elseif($x==5) { // Schemes
			
			$resultdata = $this->SFA_Comman->executequery('CALL sp_ws_syncicsdata_schemes(?,?,?,?)',$param_array,'');
			
			$result['discountkeyheader'] 			= (count($resultdata[0]) > 0) ? $resultdata[0]:array();
			$result['discountkeydetail'] 			= (count($resultdata[1]) > 0) ? $resultdata[1]:array();			
			$result['distributionkeydetails'] 		= (count($resultdata[2]) > 0) ? $resultdata[2]:array();
			$result['productgroupheader'] 			= (count($resultdata[3]) > 0) ? $resultdata[3]:array();
			$result['productgroupdetail'] 			= (count($resultdata[4]) > 0) ? $resultdata[4]:array();
			$result['promokeyheader'] 				= (count($resultdata[5]) > 0) ? $resultdata[5]:array();
			$result['promokeydetail'] 				= (count($resultdata[6]) > 0) ? $resultdata[6]:array();			
			$result['promoplanheader'] 				= (count($resultdata[7]) > 0) ? $resultdata[7]:array();
			$result['promoplandetail'] 				= (count($resultdata[8]) > 0) ? $resultdata[8]:array();
			$result['promotionassignmentadvanced'] 	= (count($resultdata[9]) > 0) ? $resultdata[9]:array();			
			$result['customerpricing1'] 			= (count($resultdata[10]) > 0) ? $resultdata[10]:array();
			$result['pricingdetail1'] 				= (count($resultdata[11]) > 0) ? $resultdata[11]:array();
			
		} elseif($x==6) { // Survey
			
			$resultdata = $this->SFA_Comman->executequery('CALL sp_ws_syncicsdata_survey(?,?,?,?)',$param_array,'');
			
			$result['POSmaster'] 					= (count($resultdata[0]) > 0) ? $resultdata[0]:array();
			$result['customerposinventory'] 		= (count($resultdata[1]) > 0) ? $resultdata[1]:array();
			$result['customerposlimit'] 			= (count($resultdata[2]) > 0) ? $resultdata[2]:array();
			$result['posinstructions'] 				= (count($resultdata[3]) > 0) ? $resultdata[3]:array();
			$result['customersurveyplan'] 			= (count($resultdata[4]) > 0) ? $resultdata[4]:array();
			$result['customersurveykeyplan'] 		= (count($resultdata[5]) > 0) ? $resultdata[5]:array();
			$result['customersurveykey'] 			= (count($resultdata[6]) > 0) ? $resultdata[6]:array();
			$result['customersurveydefinition'] 	= (count($resultdata[7]) > 0) ? $resultdata[7]:array();
			$result['customersurveydefassign'] 		= (count($resultdata[8]) > 0) ? $resultdata[8]:array();
			$result['lookupindexdetail'] 			= (count($resultdata[9]) > 0) ? $resultdata[9]:array();
			/*$result['visualheader'] 			= (count($resultdata[10]) > 0) ? $resultdata[10]:array();
			$result['visualdetail'] 			= (count($resultdata[11]) > 0) ? $resultdata[11]:array();
			$result['pricesurveyheader'] 			= (count($resultdata[12]) > 0) ? $resultdata[12]:array();
			$result['pricesurveydetail'] 			= (count($resultdata[13]) > 0) ? $resultdata[13]:array();
			$result['advertiseheader'] 			= (count($resultdata[14]) > 0) ? $resultdata[14]:array();
			$result['advertisedetail'] 			= (count($resultdata[15]) > 0) ? $resultdata[15]:array();*/
			
		} elseif($x==7) { // Reasons
			
			$resultdata = $this->SFA_Comman->executequery('CALL sp_ws_syncicsdata_reasons(?,?,?,?)',$param_array,'');
			
			$result['nonservreasons'] 		= (count($resultdata[0]) > 0) ? $resultdata[0]:array();
			$result['expreasons'] 			= (count($resultdata[1]) > 0) ? $resultdata[1]:array();
			$result['expiryreturnreasons'] 	= (count($resultdata[2]) > 0) ? $resultdata[2]:array();
			$result['retitmreasons'] 		= (count($resultdata[3]) > 0) ? $resultdata[3]:array();
			$result['freegoodreasons'] 		= (count($resultdata[4]) > 0) ? $resultdata[4]:array();
			$result['voidreasons'] 			= (count($resultdata[5]) > 0) ? $resultdata[5]:array();
			$result['routebook'] 			= (count($resultdata[6]) > 0) ? $resultdata[6]:array();
			$result['salestrend'] 			= (count($resultdata[7]) > 0) ? $resultdata[7]:array();
			
		} elseif($x==8) { // Others
			
			$resultdata = $this->SFA_Comman->executequery('CALL sp_ws_syncicsdata_others(?,?,?,?)',$param_array,'');
			
			$result['customermessages'] 	= (count($resultdata[0]) > 0) ? $resultdata[0]:array();
			$result['salesmanmessages'] 	= (count($resultdata[1]) > 0) ? $resultdata[1]:array();
			//$result['vanmaster'] 			= (count($resultdata[2]) > 0) ? $resultdata[2]:array();
			$result['bankmaster'] 			= (count($resultdata[3]) > 0) ? $resultdata[3]:array();
			$result['cashdesc'] 			= (count($resultdata[4]) > 0) ? $resultdata[4]:array();
			$result['inventorylocation'] 	= (count($resultdata[5]) > 0) ? $resultdata[5]:array();
			//$result['pricingdetail'] 	= (count($resultdata[6]) > 0) ? $resultdata[6]:array();
			
		} elseif($x==9) { // Order
			
			$resultdata = $this->SFA_Comman->executequery('CALL sp_ws_syncicsdata_orders(?,?,?,?)',$param_array,'');
			
			$result['salesorderheader'] 			= (count($resultdata[0]) > 0) ? $resultdata[0]:array();
			$result['salesorderdetail'] 			= (count($resultdata[1]) > 0) ? $resultdata[1]:array();
			$result['suggestedsalesinvoice'] 		= (count($resultdata[2]) > 0) ? $resultdata[2]:array();
			$result['inventorytransactiondetail'] 	= (count($resultdata[3]) > 0) ? $resultdata[3]:array();
			$result['customer_foc_balance'] 		= (count($resultdata[4]) > 0) ? $resultdata[4]:array();			
			$result['customer_foc_detail'] 			= (count($resultdata[5]) > 0) ? $resultdata[5]:array();
			$result['journeyplancreditlimit'] 		= (count($resultdata[6]) > 0) ? $resultdata[6]:array();
			//$result['batchexpirydetail'] 			= (count($resultdata[7]) > 0) ? $resultdata[7]:array();
			$result['customer_foc'] 				= (count($resultdata[8]) > 0) ? $resultdata[8]:array();
			$result['warehousestock'] 				= (count($resultdata[9]) > 0) ? $resultdata[9]:array();
			
			//For Delete
			$param_array1 	 = array();
			$param_array1[1] = $params['userid'];
			$param_array1[2] = $params['userid'];
			$param_array1[3] = $params['deviceid'];
			
			$param_array = "'customermaster','salesman','routemaster','startingloaddetail','itemgroup','salescalender','routesequence','promoplandetail','promoplanheader','promokeydetail','promokeyheader','promotionassignmentadvanced','promotioncontrol','bankmaster','productgroupheader','productgroupdetail','companydetail','posmaster','customerposinventory','customerposlimit','posinstructions','nonservreasons','expreasons','expiryreturnreasons','retitmreasons','freegoodreasons','voidreasons','customersurveyplan','customersurveykeyplan','customersurveykey,'customersurveydefinition','customersurveydefassign','lookupindexdetail','pricingdetail1','customerpricing1'";
			$resultdata3 = $this->SFA_Comman->executequery('CALL sp_ws_tablet_deletemaster(?,?,?)',$param_array1,'');
			//print_r($resultdata3);
			$result['deletemaster'] = (count($resultdata3[0]) > 0) ? $resultdata3[0]:array();
			
			/*if(count($resultdata) <= 0){
				$result = array( 'status' => 'error');
			}elseif($resultdata[0][0]['result'] != '' && $resultdata[0][0]['result'] == 0){
				$result = array( 'status' => 'error');
			}*/
		} elseif($x==10) { // Item Must list
			
			$resultdata = $this->SFA_Comman->executequery('CALL sp_ws_syncicsdata_itemmust(?,?,?,?)',$param_array,'');
			
			$result['itemmustheader'] 	= (count($resultdata[0]) > 0) ? $resultdata[0]:array();
			$result['itemmustdetail'] 	= (count($resultdata[1]) > 0) ? $resultdata[1]:array();			
		}
		
        //For cross platform verification
        header("Access-Control-Allow-Origin: *");
        //json output
	    array_walk_recursive($result,'replacenul');
        //echo json_encode($result);
		echo Zend_Json_Encoder::encode($result);
    }


	public function getsyncfulldataAction()
	{
		$this->view->params = $params = $this->getRequest()->getParams();
		$param_array    = array();	
        $param_array[1] = $params['userid'];
        $param_array[2] = $params['deviceid'];
        $param_array[3] = $params['routeid'];
        $param_array[4] = $params['mdate'];
		
		$param_array1 	 = array();
		$param_array1[1] = $params['userid'];
		$param_array1[2] = $params['userid'];
		$param_array1[3] = $params['deviceid'];
		
		$param_array2    = array();	
        $param_array2[1] = $params['userid'];
        $param_array2[2] = $params['deviceid'];
        $param_array2[3] = $params['routeid'];
		
		
        
			
			$result = array();
			$resultCount = array();
			$cntData = array();

			$resultdata = $this->SFA_Comman->executequery('CALL sp_ws_syncicsdata_setting(?,?,?,?)',$param_array,'');				
			$result['ControlPanel'] 			= (count($resultdata[0]) > 0) ? $resultdata[0]:array();
			$cntData = ["tablename"=>"ControlPanel","tablecount"=>count($resultdata[0])	];
			array_push($resultCount,$cntData);
			
			$result['Setup'] 					= (count($resultdata[1]) > 0) ? $resultdata[1]:array();
			$cntData = ["tablename"=>"Setup","tablecount"=>count($resultdata[1])];
			array_push($resultCount,$cntData);
			
			$result['companydetail']			= (count($resultdata[2]) > 0) ? $resultdata[2]:array();
			$cntData = ["tablename"=>"companydetail","tablecount"=>count($resultdata[2])	];
			array_push($resultCount,$cntData);
			
			$result['SalesmanMaster'] 			= (count($resultdata[3]) > 0) ? $resultdata[3]:array();
			$cntData = ["tablename"=>"SalesmanMaster","tablecount"=>count($resultdata[3])	];
			array_push($resultCount,$cntData);
			
			$result['RouteMaster'] 				= (count($resultdata[4]) > 0) ? $resultdata[4]:array();
			$cntData = ["tablename"=>"RouteMaster","tablecount"=>count($resultdata[4])	];
			array_push($resultCount,$cntData);
			
			$result['startendday'] 				= (count($resultdata[5]) > 0) ? $resultdata[5]:array();
			$cntData = ["tablename"=>"startendday","tablecount"=>count($resultdata[5])	];
			array_push($resultCount,$cntData);
			
			$result['synctime'] 				= (count($resultdata[6]) > 0) ? $resultdata[6]:array();
			$cntData = ["tablename"=>"synctime","tablecount"=>count($resultdata[7])	];
			array_push($resultCount,$cntData);
			
			$result['CurrencyMaster'] 			= (count($resultdata[7]) > 0) ? $resultdata[7]:array();
			$cntData = ["tablename"=>"CurrencyMaster","tablecount"=>count($resultdata[7])	];
			array_push($resultCount,$cntData);
		
		
			//--
			$resultdata = $this->SFA_Comman->executequery('CALL sp_ws_syncicsdata_itemmust(?,?,?,?)',$param_array,'');		
			$result['itemmustheader'] 	= (count($resultdata[1]) > 0) ? $resultdata[0]:array();
			$cntData = ["tablename"=>"itemmustheader","tablecount"=>count($resultdata[0])	];
			array_push($resultCount,$cntData);
			
			$result['itemmustdetail'] 	= (count($resultdata[1]) > 0) ? $resultdata[1]:array();
			$cntData = ["tablename"=>"itemmustdetail","tablecount"=>count($resultdata[1])	];
			array_push($resultCount,$cntData);
			
			
			//--
			$resultdata = $this->SFA_Comman->executequery('CALL sp_ws_syncicsdata_items(?,?,?,?)',$param_array,'');			
			$result['itemgroup'] 				= (count($resultdata[0]) > 0) ? $resultdata[0]:array();
			$cntData = ["tablename"=>"itemgroup","tablecount"=>count($resultdata[0])	];
			array_push($resultCount,$cntData);
			
			$result['ItemMaster'] 				= (count($resultdata[1]) > 0) ? $resultdata[1]:array();
			$cntData = ["tablename"=>"ItemMaster","tablecount"=>count($resultdata[1])	];
			array_push($resultCount,$cntData);
			
			$result['itempackagemaster'] 		= (count($resultdata[2]) > 0) ? $resultdata[2]:array();
			$cntData = ["tablename"=>"itempackagemaster","tablecount"=>count($resultdata[2])	];
			array_push($resultCount,$cntData);
			
			$result['routegoal'] 				= (count($resultdata[3]) > 0) ? $resultdata[3]:array();
			$cntData = ["tablename"=>"routegoal","tablecount"=>count($resultdata[3])	];
			array_push($resultCount,$cntData);
			
			$result['avgsalesqty'] 				= (count($resultdata[4]) > 0) ? $resultdata[4]:array();
			$cntData = ["tablename"=>"avgsalesqty","tablecount"=>count($resultdata[4])	];
			array_push($resultCount,$cntData);
			
			$result['outletitemcodes'] 			= (count($resultdata[5]) > 0) ? $resultdata[5]:array();
			$cntData = ["tablename"=>"outletitemcodes","tablecount"=>count($resultdata[5])	];
			array_push($resultCount,$cntData);
			
			$result['taxmaster'] 				= (count($resultdata[6]) > 0) ? $resultdata[6]:array(); 
			$cntData = ["tablename"=>"taxmaster","tablecount"=>count($resultdata[6])	];
			array_push($resultCount,$cntData);
			
			//--
			$resultdata = $this->SFA_Comman->executequery('CALL sp_ws_syncicsdata_inventory(?,?,?,?)',$param_array,'');			
			$result['startingloaddetail'] 		= (count($resultdata[0]) > 0) ? $resultdata[0]:array();
			$cntData = ["tablename"=>"startingloaddetail","tablecount"=>count($resultdata[0])	];
			array_push($resultCount,$cntData);
			
			$result['inventorysummarydetail'] 	= (count($resultdata[1]) > 0) ? $resultdata[1]:array();
			$cntData = ["tablename"=>"inventorysummarydetail","tablecount"=>count($resultdata[1])	];
			array_push($resultCount,$cntData);
			
			//--
			$resultdata = $this->SFA_Comman->executequery('CALL sp_ws_syncicsdata_customers(?,?,?,?)',$param_array,'');
			$result['CustomerMaster'] 			= (count($resultdata[0]) > 0) ? $resultdata[0]:array();	
			$cntData = ["tablename"=>"CustomerMaster","tablecount"=>count($resultdata[0])	];
			array_push($resultCount,$cntData);
			
			$result['salescalender'] 			= (count($resultdata[1]) > 0) ? $resultdata[1]:array();
			$cntData = ["tablename"=>"salescalender","tablecount"=>count($resultdata[1])	];
			array_push($resultCount,$cntData);
			
			$result['routesequence'] 			= (count($resultdata[2]) > 0) ? $resultdata[2]:array();
			$cntData = ["tablename"=>"routesequence","tablecount"=>count($resultdata[2])	];
			array_push($resultCount,$cntData);
			
			$result['customerinvoice'] 			= (count($resultdata[3]) > 0) ? $resultdata[3]:array();
			$cntData = ["tablename"=>"customerinvoice","tablecount"=>count($resultdata[3])	];
			array_push($resultCount,$cntData);
			
			//--
			$resultdata = $this->SFA_Comman->executequery('CALL sp_ws_syncicsdata_schemes(?,?,?,?)',$param_array,'');			
			$result['discountkeyheader'] 			= (count($resultdata[0]) > 0) ? $resultdata[0]:array();
			$cntData = ["tablename"=>"discountkeyheader","tablecount"=>count($resultdata[0])	];
			array_push($resultCount,$cntData);
			
			$result['discountkeydetail'] 			= (count($resultdata[1]) > 0) ? $resultdata[1]:array();	
			$cntData = ["tablename"=>"discountkeydetail","tablecount"=>count($resultdata[1])	];
			array_push($resultCount,$cntData);
			
			$result['distributionkeydetails'] 		= (count($resultdata[2]) > 0) ? $resultdata[2]:array();
			$cntData = ["tablename"=>"distributionkeydetails","tablecount"=>count($resultdata[2])	];
			array_push($resultCount,$cntData);
			
			$result['productgroupheader'] 			= (count($resultdata[3]) > 0) ? $resultdata[3]:array();
			$cntData = ["tablename"=>"productgroupheader","tablecount"=>count($resultdata[3])	];
			array_push($resultCount,$cntData);
			
			$result['productgroupdetail'] 			= (count($resultdata[4]) > 0) ? $resultdata[4]:array();
			$cntData = ["tablename"=>"productgroupdetail","tablecount"=>count($resultdata[4])	];
			array_push($resultCount,$cntData);
			
			$result['promokeyheader'] 				= (count($resultdata[5]) > 0) ? $resultdata[5]:array();
			$cntData = ["tablename"=>"promokeyheader","tablecount"=>count($resultdata[5])	];
			array_push($resultCount,$cntData);
			
			$result['promokeydetail'] 				= (count($resultdata[6]) > 0) ? $resultdata[6]:array();	
			$cntData = ["tablename"=>"promokeydetail","tablecount"=>count($resultdata[6])	];
			array_push($resultCount,$cntData);
			
			$result['promoplanheader'] 				= (count($resultdata[7]) > 0) ? $resultdata[7]:array();
			$cntData = ["tablename"=>"promoplanheader","tablecount"=>count($resultdata[7])	];
			array_push($resultCount,$cntData);
			
			$result['promoplandetail'] 				= (count($resultdata[8]) > 0) ? $resultdata[8]:array();
			$cntData = ["tablename"=>"promoplandetail","tablecount"=>count($resultdata[8])	];
			array_push($resultCount,$cntData);
			
			$result['promotionassignmentadvanced'] 	= (count($resultdata[9]) > 0) ? $resultdata[9]:array();	
			$cntData = ["tablename"=>"promotionassignmentadvanced","tablecount"=>count($resultdata[9])	];
			array_push($resultCount,$cntData);
			
			$result['customerpricing1'] 			= (count($resultdata[10]) > 0) ? $resultdata[10]:array();
			$cntData = ["tablename"=>"customerpricing1","tablecount"=>count($resultdata[10])	];
			array_push($resultCount,$cntData);
			
			$result['pricingdetail1'] 				= (count($resultdata[11]) > 0) ? $resultdata[11]:array();
			$cntData = ["tablename"=>"pricingdetail1","tablecount"=>count($resultdata[11])	];
			array_push($resultCount,$cntData);
			
			//--
			$resultdata = $this->SFA_Comman->executequery('CALL sp_ws_syncicsdata_survey(?,?,?,?)',$param_array,'');			
			$result['POSmaster'] 					= (count($resultdata[0]) > 0) ? $resultdata[0]:array();
			$cntData = ["tablename"=>"POSmaster","tablecount"=>count($resultdata[0])	];
			array_push($resultCount,$cntData);
			
			$result['customerposinventory'] 		= (count($resultdata[1]) > 0) ? $resultdata[1]:array();
			$cntData = ["tablename"=>"customerposinventory","tablecount"=>count($resultdata[1])	];
			array_push($resultCount,$cntData);
			
			$result['customerposlimit'] 			= (count($resultdata[2]) > 0) ? $resultdata[2]:array();
			$cntData = ["tablename"=>"customerposlimit","tablecount"=>count($resultdata[2])	];
			array_push($resultCount,$cntData);
			
			$result['posinstructions'] 				= (count($resultdata[3]) > 0) ? $resultdata[3]:array();
			$cntData = ["tablename"=>"posinstructions","tablecount"=>count($resultdata[3])	];
			array_push($resultCount,$cntData);
			
			$result['customersurveyplan'] 			= (count($resultdata[4]) > 0) ? $resultdata[4]:array();
			$cntData = ["tablename"=>"customersurveyplan","tablecount"=>count($resultdata[4])	];
			array_push($resultCount,$cntData);
			
			$result['customersurveykeyplan'] 		= (count($resultdata[5]) > 0) ? $resultdata[5]:array();
			$cntData = ["tablename"=>"customersurveykeyplan","tablecount"=>count($resultdata[5])	];
			array_push($resultCount,$cntData);
			
			$result['customersurveykey'] 			= (count($resultdata[6]) > 0) ? $resultdata[6]:array();
			$cntData = ["tablename"=>"customersurveykey","tablecount"=>count($resultdata[6])	];
			array_push($resultCount,$cntData);
			
			$result['customersurveydefinition'] 	= (count($resultdata[7]) > 0) ? $resultdata[7]:array();
			$cntData = ["tablename"=>"customersurveydefinition","tablecount"=>count($resultdata[7])	];
			array_push($resultCount,$cntData);
			
			$result['customersurveydefassign'] 		= (count($resultdata[8]) > 0) ? $resultdata[8]:array();
			$cntData = ["tablename"=>"customersurveydefassign","tablecount"=>count($resultdata[8])	];
			array_push($resultCount,$cntData);
			
			$result['lookupindexdetail'] 			= (count($resultdata[9]) > 0) ? $resultdata[9]:array();
			$cntData = ["tablename"=>"lookupindexdetail","tablecount"=>count($resultdata[9])	];
			array_push($resultCount,$cntData);
			
			//--
			$resultdata = $this->SFA_Comman->executequery('CALL sp_ws_syncicsdata_reasons(?,?,?,?)',$param_array,'');			
			$result['nonservreasons'] 		= (count($resultdata[0]) > 0) ? $resultdata[0]:array();
			$cntData = ["tablename"=>"nonservreasons","tablecount"=>count($resultdata[0])	];
			array_push($resultCount,$cntData);
			
			$result['expreasons'] 			= (count($resultdata[1]) > 0) ? $resultdata[1]:array();
			$cntData = ["tablename"=>"expreasons","tablecount"=>count($resultdata[1])	];
			array_push($resultCount,$cntData);
			
			$result['expiryreturnreasons'] 	= (count($resultdata[2]) > 0) ? $resultdata[2]:array();
			$cntData = ["tablename"=>"expiryreturnreasons","tablecount"=>count($resultdata[2])	];
			array_push($resultCount,$cntData);
			
			$result['retitmreasons'] 		= (count($resultdata[3]) > 0) ? $resultdata[3]:array();
			$cntData = ["tablename"=>"retitmreasons","tablecount"=>count($resultdata[3])	];
			array_push($resultCount,$cntData);
			
			$result['freegoodreasons'] 		= (count($resultdata[4]) > 0) ? $resultdata[4]:array();
			$cntData = ["tablename"=>"freegoodreasons","tablecount"=>count($resultdata[4])	];
			array_push($resultCount,$cntData);
			
			$result['voidreasons'] 			= (count($resultdata[5]) > 0) ? $resultdata[5]:array();
			$cntData = ["tablename"=>"voidreasons","tablecount"=>count($resultdata[6])	];
			array_push($resultCount,$cntData);
			
			$result['routebook'] 			= (count($resultdata[6]) > 0) ? $resultdata[6]:array();
			$cntData = ["tablename"=>"routebook","tablecount"=>count($resultdata[6])	];
			array_push($resultCount,$cntData);
			
			$result['salestrend'] 			= (count($resultdata[7]) > 0) ? $resultdata[7]:array();
			$cntData = ["tablename"=>"salestrend","tablecount"=>count($resultdata[7])	];
			array_push($resultCount,$cntData);
			
			$result['tempcustinventory'] 			= (count($resultdata[8]) > 0) ? $resultdata[8]:array();
			$cntData = ["tablename"=>"tempcustinventory","tablecount"=>count($resultdata[8])	];
			array_push($resultCount,$cntData);
			
			//--
			$resultdata = $this->SFA_Comman->executequery('CALL sp_ws_syncicsdata_others(?,?,?,?)',$param_array,'');			
			$result['customermessages'] 	= (count($resultdata[0]) > 0) ? $resultdata[0]:array();
			$cntData = ["tablename"=>"customermessages","tablecount"=>count($resultdata[0])	];
			array_push($resultCount,$cntData);
			
			$result['salesmanmessages'] 	= (count($resultdata[1]) > 0) ? $resultdata[1]:array();
			$cntData = ["tablename"=>"salesmanmessages","tablecount"=>count($resultdata[1])	];
			array_push($resultCount,$cntData);
			
			$result['vanmaster'] 			= (count($resultdata[2]) > 0) ? $resultdata[2]:array();
			$cntData = ["tablename"=>"vanmaster","tablecount"=>count($resultdata[2])	];
			array_push($resultCount,$cntData);
			
			$result['bankmaster'] 			= (count($resultdata[3]) > 0) ? $resultdata[3]:array();
			$cntData = ["tablename"=>"bankmaster","tablecount"=>count($resultdata[3])	];
			array_push($resultCount,$cntData);
			
			$result['cashdesc'] 			= (count($resultdata[4]) > 0) ? $resultdata[4]:array();
			$cntData = ["tablename"=>"cashdesc","tablecount"=>count($resultdata[4])	];
			array_push($resultCount,$cntData);
			
			$result['inventorylocation'] 	= (count($resultdata[5]) > 0) ? $resultdata[5]:array();
			$cntData = ["tablename"=>"inventorylocation","tablecount"=>count($resultdata[5])	];
			array_push($resultCount,$cntData);
			
			//--
			$resultdata = $this->SFA_Comman->executequery('CALL sp_ws_syncicsdata_orders(?,?,?,?)',$param_array,'');			
			$result['salesorderheader'] 			= (count($resultdata[0]) > 0) ? $resultdata[0]:array();
			$cntData = ["tablename"=>"salesorderheader","tablecount"=>count($resultdata[0])	];
			array_push($resultCount,$cntData);
			
			$result['salesorderdetail'] 			= (count($resultdata[1]) > 0) ? $resultdata[1]:array();
			$cntData = ["tablename"=>"salesorderdetail","tablecount"=>count($resultdata[1])	];
			array_push($resultCount,$cntData);
			
			$result['suggestedsalesinvoice'] 		= (count($resultdata[2]) > 0) ? $resultdata[2]:array();
			$cntData = ["tablename"=>"suggestedsalesinvoice","tablecount"=>count($resultdata[2])	];
			array_push($resultCount,$cntData);
			
			$result['inventorytransactiondetail'] 	= (count($resultdata[3]) > 0) ? $resultdata[3]:array();
			$cntData = ["tablename"=>"inventorytransactiondetail","tablecount"=>count($resultdata[3])	];
			array_push($resultCount,$cntData);
			
			$result['customer_foc_balance'] 		= (count($resultdata[4]) > 0) ? $resultdata[4]:array();	
			$cntData = ["tablename"=>"customer_foc_balance","tablecount"=>count($resultdata[4])	];
			array_push($resultCount,$cntData);
			
			$result['customer_foc_detail'] 			= (count($resultdata[5]) > 0) ? $resultdata[5]:array();
			$cntData = ["tablename"=>"customer_foc_detail","tablecount"=>count($resultdata[5])	];
			array_push($resultCount,$cntData);
			
			$result['journeyplancreditlimit'] 		= (count($resultdata[6]) > 0) ? $resultdata[6]:array();
			$cntData = ["tablename"=>"journeyplancreditlimit","tablecount"=>count($resultdata[6])	];
			array_push($resultCount,$cntData);
			
			$result['batchexpirydetail'] 			= (count($resultdata[7]) > 0) ? $resultdata[7]:array();
			$cntData = ["tablename"=>"batchexpirydetail","tablecount"=>count($resultdata[7])	];
			array_push($resultCount,$cntData);
			
			$result['customer_foc'] 				= (count($resultdata[8]) > 0) ? $resultdata[8]:array();
			$cntData = ["tablename"=>"customer_foc","tablecount"=>count($resultdata[8])	];
			array_push($resultCount,$cntData);
			
			//--
			$resultdata = $this->SFA_Comman->executequery('CALL sp_ws_syncicsdata_itemmust(?,?,?,?)',$param_array,'');			
			$result['itemmustheader'] 	= (count($resultdata[0]) > 0) ? $resultdata[0]:array();
			$cntData = ["tablename"=>"itemmustheader","tablecount"=>count($resultdata[0])	];
			array_push($resultCount,$cntData);
			
			$result['itemmustdetail'] 	= (count($resultdata[1]) > 0) ? $resultdata[1]:array();	
			$cntData = ["tablename"=>"itemmustdetail","tablecount"=>count($resultdata[1])	];
			array_push($resultCount,$cntData);
			
			$result['itemnrp'] 	= (count($resultdata[2]) > 0) ? $resultdata[2]:array();	
			$cntData = ["tablename"=>"itemnrp","tablecount"=>count($resultdata[2])	];
			array_push($resultCount,$cntData);
			
			$result['custnrp'] 	= (count($resultdata[3]) > 0) ? $resultdata[3]:array();	
			$cntData = ["tablename"=>"custnrp","tablecount"=>count($resultdata[3])	];
			array_push($resultCount,$cntData);
			
			//--
			$resultdata3 = $this->SFA_Comman->executequery('CALL sp_ws_tablet_deletemaster(?,?,?)',$param_array1,'');
			$result['deletemaster'] = (count($resultdata3[0]) > 0) ? $resultdata3[0]:array();
			$cntData = ["tablename"=>"deletemaster","tablecount"=>count($resultdata[0])	];
			array_push($resultCount,$cntData);
			
			//--
			$resultdata = $this->SFA_Comman->executequery('CALL sp_ws_syncicsdata_customeritemgrp(?,?,?)',$param_array2,'');
			$result['customeritemgrp'] 				= (count($resultdata[0]) > 0) ? $resultdata[0]:array();
			$cntData = ["tablename"=>"customeritemgrp","tablecount"=>count($resultdata[0])	];
			array_push($resultCount,$cntData);
			
			$result['customeritemmap'] 				= (count($resultdata[1]) > 0) ? $resultdata[1]:array();
			$cntData = ["tablename"=>"customeritemmap","tablecount"=>count($resultdata[1])	];
			array_push($resultCount,$cntData);
			
			$result['synccount'] = $resultCount;
			
			header("Access-Control-Allow-Origin: *");
        //json output
	    array_walk_recursive($result,'replacenul');
        echo json_encode($result);
		
			
	}
        /**
    * @name       updatesyncdateAction
    * @since      23-03-2012
    * @version    Release: 1
    * @author     PM <pankit@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param      userid
    * @param      deviceid
    * @example1 api/index/updatesyncdate/userid/1/deviceid/a2006
    * This is the salesmanmessages Action.
    */
    public function updatesyncdateAction(){
        //Request Parameter
        $this->view->params = $params = $this->getRequest()->getParams();
         //print_r($params);
	 
        //parameter array
        $param_array = array();
	//user id
        $param_array[1] =  $params['userid'];
        //Device id
        $param_array[2] =$params['deviceid'];
	$param_array[3] =$params['routecode'];

        //fetch result from sync web service for itemmaster, Salesman and customer information which need to update in app.
	$resultdata = $this->SFA_Comman->executequery('CALL sp_ws_updatesyncdate(?,?,?)',$param_array,'');
	$param_array1 = array();
	$param_array1[1] =  $params['userid'];       
        $param_array1[2] =$params['routecode'];
	$param_array1[3] =$params['routekey'];
	$param_array1[4] =$params['routeclosed'];
	$param_array1[5] ='1';
         $resultdata1 = $this->SFA_Comman->executequery('CALL sp_ws_instertion_tbl_synclog(?,?,?,?,?)',$param_array1,'');
	
        $result = $resultdata[0];

        $result = array( 'status' => 'success');
        //For cross platform verification
        header("Access-Control-Allow-Origin: *");
        //json output
        echo json_encode($result);
    }
    
            /**
    * @name       sendData
    * @since      23-03-2012
    * @version    Release: 1
    * @author     PM <pankit@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param      userid
    * @param      deviceid
    * @example1 api/index/updatesyncdate/userid/1/deviceid/a2006
    * This is the salesmanmessages Action.
    *
    * 
    */
    function stripslashes_deep($value)
    {
    $value = is_array($value) ?
                array_map('stripslashes_deep', $value) :
                stripslashes($value);

    return $value;
    }
}
function replacenul(&$item, $key)
    {
        if($item == null|| $item=='null')
            $item = "";
    } 