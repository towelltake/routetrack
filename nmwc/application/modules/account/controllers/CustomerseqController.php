<?php
/**
* @name       CustomerseqController
* @since
* @version    Release: 1
* @author     HD <hiren.d@elantechnologies.com>
* @copyright  Elan Technologies
* @param
*
* This controller for Customer sequence.
*/
class Account_CustomerseqController extends Account_Library_Controller_Action_Abstract
{

    public $sec_lang 	= '';
    /**
    * @name       init
    * @since      15-12-2011
    * @version    Release: 1
    * @author     HD <hiren.d@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This is the default function for all Actions.
    *
    */
    public function init()
    {
	$this->translate 	= Zend_Registry::get('Zend_Translate');
	
	$this->currentUser = SFA_Loginauth::getIdentity();	
	if(!isset($this->currentUser) || empty($this->currentUser))
	{
	    SFA_Message::setMsg($this->translate->_('Do Login'));
	    //$this->_helper->redirector("index", "index", "home");
			$url = $this->view->baseUrl();
			echo '<script type="text/javascript">window.location="'.$url.'";</script>';
			exit;
	}
	
	
        $this->css 				= $this->translate->_('CSS');
		$this->view->css 		= $this->css;
		$this->view->overview	= $this->translate->_('Overview');
		$this->view->details	= $this->translate->_('Details');
		$this->view->required	= $this->translate->_('Required');
		$this->view->colan	= $this->translate->_('Colan');
		
		$this->SFA_Model_Index 	= new SFA_Model_Index();
		$this->SFA_Comman	= new SFA_Comman();
		$this->view->sec_lang	= $this->SFA_Comman->getsecondlanguage();
		$this->sec_lang 	= $this->view->sec_lang;
    }
    
    
    /**
    * @name       preDispatch
    * @since      26- sep-2012
    * @version    Release: 1
    * @author     PT <pankil@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This is the default function for all Actions.
    *
    */
    
    public function preDispatch()
    {
        parent::preDispatch();
        
        /**
         *      Acl Code start
         */
        $getparams_init = $this->getRequest()->getParams();
        $getpost_init = $this->_request->getPost();
        
        if(in_array($getparams_init['action'],$this->current_read_delete_arr))
        {
            if($getpost_init["hdDelete"]==1 && !$this->checkaccess("delete")) {
            
                $this->_forward('noaccess','aclaccess','home', array("actiontype"=>"delete","modulename"=>$this->currentmodulename));
            
            } elseif(!$this->checkaccess("read")) {
                
                $this->_forward('noaccess','aclaccess','home', array("actiontype"=>"read","modulename"=>$this->currentmodulename));
                
            } else {
                
            }
        }
        elseif(in_array($getparams_init['action'],$this->current_insert_update_arr))
        {
            if($params['id'] > 0 && !$this->checkaccess("update")) {
            
                $this->_forward('noaccess','aclaccess','home', array("actiontype"=>"update","modulename"=>$this->currentmodulename));
                
            } elseif(!$this->checkaccess("insert")) {
                
                $this->_forward('noaccess','aclaccess','home', array("actiontype"=>"insert","modulename"=>$this->currentmodulename));
                
            } else {
                
            }
        }
        /**
         *      Acl Code end
         */
    }
    
    
    /**
    * @name       indexAction
    * @since      17-2-2012
    * @version    Release: 1
    * @author     PM <pankit@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This Action is for coustomer sequence flow
    */
    public function indexAction()
    {
	$this->view->params = $params = $this->getRequest()->getParams();
        $this->view->formdata = $formdata = $this->_request->getPost();       
	
	
	$result = $this->SFA_Comman->executequery('CALL sp_getcombobox_account_customerseq_index("")','','');
	
	$this->view->route 	= $result[0];
	$this->view->counter 	= $result[1][0]['counter'];	
	$route_seq 		= $result[2][0]['routesequenceplanflag'];

        $week = array();
	if($route_seq == '1')
	{
	    $week[0]['val'] = 9;
	}
	else
	{
	    $week[0]['val'] = 1;
	    $week[1]['val'] = 2;
	    $week[2]['val'] = 3;
	    $week[3]['val'] = 4;
	}	
        $this->view->week = $week;

    }

    /**
    * @name       salescalender
    * @since      17-2-2012
    * @version    Release: 1
    * @author     PM <pankit@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This Action is for sales calender
    */
    public function salescalenderAction()
    {	
        $this->view->params = $params = $this->getRequest()->getParams();
        $this->view->formdata = $formdata = $this->_request->getPost();
		
		
		$wkdays = array();
        $wkdays[0]['id'] = 1;
        $wkdays[0]['val'] = 'Monday';
        $wkdays[1]['id'] = 2;
        $wkdays[1]['val'] = 'Tuesday';
        $wkdays[2]['id'] = 3;
        $wkdays[2]['val'] = 'Wednesday';
        $wkdays[3]['id'] = 4;
        $wkdays[3]['val'] = 'Thursday';
        $wkdays[4]['id'] = 5;
        $wkdays[4]['val'] = 'Friday';
        $wkdays[5]['id'] = 6;
        $wkdays[5]['val'] = 'Saturday';
        $wkdays[6]['id'] = 7;
        $wkdays[6]['val'] = 'Sunday';
		
		$this->view->wkday = $wkdays;

        // IF EXTRA PARAMS ARE REQUIRED
        $ex_param = "";        
        $ex_param = "/default/true";
	
        $this->view->itemgrid    = $this->view->BaseUrl("/".$params['module']."/".$params['controller']."/salescalendargrid".$ex_param);

        $this->_helper->layout->setLayout('popup');
	
		$result = $this->SFA_Comman->executequery('CALL sp_getcombobox_account_customerseq_index("")','','');
		$this->view->counter 	= $result[1][0]['counter'];
		$this->view->startday 	= $result[3][0]['weekstartday'];

        //array for give list of years
        $fromyear = array();
        $start=1900;
        $noofyears = 300;
        $upto = $start+$noofyears;
        $j=0;
        for($i=$start;$i<=$upto; $i++){
            $fromyear[$j]['id'] = $i;
            $fromyear[$j]['val'] = $i;
            $j++;
        }
        $this->view->year = $fromyear;
    }

   /**
    * @name       salescalendargridAction
    * @since      23-02-2012
    * @version    Release: 1
    * @author     AS <alpesh@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This action is use for Sales Calendar Action
    */
    public function salescalendargridAction()
    {	
        $this->view->params = $params = $this->getRequest()->getParams();
	
		// column to be fetched
		$columns_array 	=  array('weekstartdate','weekenddate','weeknumber','salesperiod','rp32weeknumber');
	
		//column name variables
		$startdate 	= $this->translate->_('Start Date');
		$enddate 	= $this->translate->_('End Date');
		$week    	= $this->translate->_('Calendar Week');
		$period  	= $this->translate->_('Period');
		$rp32week 	= $this->translate->_('RoutePro Week');
		
		$columns_show  = array($startdate,$enddate,$week,$period,$rp32week);
		
		if($params['loaddata']) 
		{
			$result = $this->SFA_Comman->executequery('CALL sp_get_account_customerseq_salescalendar(?)',$params['year'],'');
			$data_arr["count"]= (count($result) > 0) ? 1000 : 0;
			$data_arr["data"] = $result;
		}
		else if($params['delete'])
		{
			$result = $this->SFA_Comman->executequery('CALL sp_delete_account_customerseq_salescalendar(?)',$params['year'],'');
			if(count($result) > 0)
			SFA_Message::setMsg($this->translate->_('Delete Record'));
		}	
		else if($params['auto'] || $params['save'] == true)
		{
			$day = array(1 => 'monday',2 => 'tuesday',3 => 'wednesday',4 => 'thursday',5 => 'friday',6 => 'saturday',7 => 'sunday');
		
			$result 	= $this->SFA_Comman->executequery('CALL sp_get_weekstart_day("")','','');			
			$day_of_week 	= $result[0][0]['weekstartday'];
			$day_of_week 	= ($day_of_week-1);
			$day_of_week	= ($day_of_week == 0) ? 7 : $day_of_week;
			$dayname     	= $day[$day_of_week];
			
			//SFA_Comman::pre($params);    
			
			// current day - it should be fetch through mysql
			$selected_year = '01-01-'.$params['year'];
			
			define("DFORMAT","d-m-Y");
			
			$params['default'] = false;	    
			$today = date(DFORMAT, strtotime($selected_year));
			
			// current year
			$cYear = date("Y",strtotime($selected_year));	
			$week_data = array();
			$i = 1;
			$j=0;
			$k=1;
			
			for($im=1; $yy<=date("Y",strtotime($selected_year)); $im++)
			{
				if($im==1){
					// at very first find the last day of the week
					$first = $today;	       
					if(date("N",strtotime($selected_year))==$day_of_week)
						$second = date(DFORMAT,strtotime($selected_year));
					else
						$second = date(DFORMAT, strtotime("next ".$dayname, strtotime($selected_year)));
				}
				else{					
					// then every weeks first & last day
					$first = date(DFORMAT, strtotime(date("Y-m-d", strtotime($second)) . " +1 day"));
					$second = date(DFORMAT, strtotime(date("Y-m-d", strtotime($first)) . " +6 day"));
				}
				   // get the week number of the year
				if($im==1) {
					$zz = $k;
					$k++;
				}
				else{
					$zz = $k++;					
				}
				
				// get the day number of first day    
				$fdm = date("m",strtotime($first));
				
				// get the day number of first day    
				$sdm = date("m",strtotime($second));
				
				if($i== 4) {
					$wn = $i;
					$i = 1;
				}
				else
				{
					$wn = $i;
					$i++;
				}
				
				   
				// get first & last day's year
				$yy = date("Y",strtotime($first));
				$dx = date("Y",strtotime($second));
				   
				   // get month number
				   $mn = date("m",strtotime($first));
				   
				// if the first day's year is next year then loop over
				if($yy > $cYear)
					break;
				   
				// if the last day's year is next year then stop at the last day of this year
				if($dx > $cYear)
				{
					   $second = date(DFORMAT, strtotime(date("Y-m-d", mktime(0,0,0,12,31,$cYear) )));
					  
					   if($zz=='01')
					$zz = 53;
				}
				  
				$week_data[$j]['weekstartdate'] = $first;
				$week_data[$j]['weekenddate'] = $second;
				$week_data[$j]['weeknumber'] = $zz;
				$week_data[$j]['salesperiod'] = $mn;
				$week_data[$j]['rp32weeknumber'] = $wn;
				
				$j++;	   
			}
			
			$data_arr["count"]= 1000;
			$x[0] = $week_data;
			$data_arr["data"] = $x;
			
			if($params['save']== true)
			{
				$result = $this->SFA_Comman->executequery('CALL sp_checksalesyear_account_customerseq_salescalendar(?)',$params['year'],'');
				if($result[0][0]['counter'] == 0) {
					$success = $this->SFA_Model_Index->salescalendar($week_data,$params['year'],$this->currentUser->username);
					SFA_Message::setMsg($this->translate->_('New Record'));
				}
				else
				{
					SFA_Message::setErrorMsg($this->translate->_('Duplicate Record'));
				}
			}	    
		}	
		else
		{
			if($params['year'] == '') {
			$this->view->current_year = $params['year'] = date("Y");
			}
			
			$result = $this->SFA_Comman->executequery('CALL sp_get_account_customerseq_salescalendar(?)',$params['year'],'');
			$data_arr["count"]= (count($result) > 0) ? 1000 : 0;
			$data_arr["data"] = $result;
		}
		
	
		// ARRAY FOR GRID PAGINATION
			//start grid code for get list by search criteria
		$pagingparams = array(
					"show_grid_heading" => false,
					"grid_heading_message" => $this->translate->_('Sales Calendar'),
					"show_records_per_page" => 1000,
					"show_searchbox" => false,
					"show_selectbox" => false,
					"show_editlink" => false,
					"show_deletelink" => false,
					"show_deleteall" => false,
					"primaryid" => "",
					"currentlink" => array("/".$params['module']."/".$params['controller']."/".$params['action'].$ex_param),
					"show_columns" => $columns_show,
					"fetch_columns_inquery" => $cols_array,
					"nodata_message" => $this->translate->_('No Record(s) Found')
		);
		
		
		
		
	
		$pagingshow = new SFA_Ajaxpaging($pagingparams);
		
		$this->view->pagegridshow =  $pagingshow->summary_showdatagrid($data_arr);
		$this->view->addScriptPath(APPLICATION_PATH."/theme/default/datagrid/");
	
		$this->render("ajaxgrid");
    }

    /**
    * @name       arrangecustomer
    * @since      17-2-2012
    * @version    Release: 1
    * @author     PM <pankit@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This Action is for coustomer sequence flow
    */
    public function arrangecustomerAction()
    {    
		$this->_helper->layout->setLayout('popup');
		
        $this->view->params = $params = $this->getRequest()->getParams();
        $this->view->formdata = $formdata = $this->_request->getPost();
		
		// For Alternate Code Status.
		$cpanel							= $this->SFA_Comman->getaltcodestatus();
		$this->view->altcode_status	= $cpanel["Use Alternate Code"]['status'];
		
		
		$this->view->selected_week 	= $params['week'] !='' ? $params['week'] : $formdata['week'];
		$this->view->selected_route	= $params['route'] !='' ? $params['route'] : $formdata['route'];
		
		echo "d";
	//print_r($user_permissions);exit;
	$userid = $this->currentUser->userid;
	//echo $userid;exit;
	$this->view->updatepermission 	=1;
	$this->view->removepermission 	=1;
	if($userid!=1)
	{
		$user_permissions = $this->SFA_Comman->checkpermission("3","33",$userid);	
		$this->view->updatepermission 	= $user_permissions[0][0]["updatedata"];
		$this->view->removepermission 	= $user_permissions[0][0]["deletedata"];
	}
	 		
//print_r($this->view->updatepermissions);exit;					
				/*	if( ($user_permissions[0] == NULL)||( $user_permissions[0][0]["readdata"] == 1 || $user_permissions[0][0]["updatedata"] == 1 || $user_permissions[0][0]["insertdata"] == 1 || $user_permissions[0][0]["deletedata"] == 1))
						{
						}*/
						
						
						
		
		//SFA_Comman::pre($result);
		$week_start		= $this->SFA_Comman->executequery('CALL sp_get_weekstart_day()','','');
		$day_of_week		= $week_start[0][0]['weekstartday'];
		
		$day_of_week = 1;
		$start_of_day_week 	= $day_of_week;
		$end_of_day_week 	= $day_of_week+7;
		
		$day_name = array();
		
		for($i=$start_of_day_week;$i<$end_of_day_week;$i++)
		{
			$datashow = $i;
			if($i > 7)
			{
				$datashow = $i % 7;
			}
			
			$day_name[] = substr(date("l",mktime(0,0,0,10,$datashow,2012)), 0, 3);
		}
		$this->view->day_name = $day_name;
		
		/* For Delete customer */
		if($formdata['hdnremovecustomer'] == '1')
		{
			foreach($formdata['chk'] as $key => $val)
			{
			$customer_code .= $val.",";
			}
			
			$customer_code = substr($customer_code,0,-1);
			
			$param_array = array();
			//SFA_Comman::pre($formdata);
			$param_array[1] = $formdata['hdnroute'];
			$param_array[2] = $formdata['hdnweek'];
			$param_array[3] = $customer_code;
			$param_array[4] = $this->currentUser->username;
			
			$result = $this->SFA_Comman->executequery('CALL sp_delete_account_customerseq_arrangecustomer(?,?,?,?)',$param_array,'');
			SFA_Message::setMsg($this->translate->_('Delete Record'));	    
		}
		elseif($formdata['save'])/* For Save customer */
		{
			$day_array 	= array();
			$user_array	= array();
			$unique_user_array = array();
			
			for($i=0;$i<count($day_name);$i++)
			{
				$day_array[] = $formdata[strtolower($day_name[$i])];
			}
			
			//SFA_Comman::pre($formdata);
			for($i=0;$i<count($day_array);$i++)
			{
				for($j=0;$j<count($day_array[$i]);$j++)
				{
					if(!in_array($day_array[$i][$j],$unique_user_array))
					$unique_user_array[] = $day_array[$i][$j];
				}
			}
			
			for($i=0;$i<count($day_array);$i++)
			{
				$new_array = array();
				for($j=0;$j<count($day_array[$i]);$j++)
				{
					if(in_array($day_array[$i][$j], $unique_user_array)){
						$user_array[$day_array[$i][$j]] .= "1,";
						$new_array[] =$day_array[$i][$j];
					}
				}
				for($k=0; $k<count($unique_user_array);$k++) {
					if(!in_array($unique_user_array[$k], $new_array)){
						$user_array[$unique_user_array[$k]] .= "0,";
					}
				}
			}
			
			foreach($user_array as $key => $val)
			{
				$days = array();
				$days = explode(',',$val);
				/*Updated sp added for add customer sequence by nilesh on 15Mar2016 */
				$param_array[1]	= $formdata['hdnroute'];	//	IN route_code BIGINT,
				$param_array[2]	= $formdata['hdnweek'];	//IN week_no INT,
				$param_array[3] = "rp32weeknumber=".$formdata['hdnweek'].",routecode=".$formdata['hdnroute'].",customercode=".$key.",callrestrictiondays1=".$days[0].",callrestrictiondays2=".$days[1].",callrestrictiondays3=".$days[2].",callrestrictiondays4=".$days[3].",callrestrictiondays5=".$days[4].",callrestrictiondays6=".$days[5].",callrestrictiondays7=".$days[6];//IN input_text TEXT,	  
				$param_array[4]	= $key; //IN customer_code TEXT,
				$param_array[5] = $this->currentUser->username; //IN createdby VARCHAR(30)
				$result = $this->SFA_Comman->executequery('CALL sp_add_account_customerseq_arrangecustomer(?,?,?,?,?)',$param_array,'');
			}		
			SFA_Message::setMsg($this->translate->_('Save Record'));
		}
		
		$param_array = array();
		$param_array[1]	= $this->view->selected_route;
		$param_array[2]	= $this->view->selected_week;
		
		$result 			= $this->SFA_Comman->executequery('CALL sp_getcombobox_account_customerseq_arrangecustomer(?,?)',$param_array,'');
		$this->view->route_info		= $result[0];
		$this->view->customer_info 	= $result[1];
		
		$week = array();
		$week[0]['val'] = 1;
		$week[1]['val'] = 2;
		$week[2]['val'] = 3;
		$week[3]['val'] = 4;
		$week[4]['val'] = 9;
		$this->view->week = $week;        
		// printing arrange customer
        if($formdata["hdDelete"] == 2 || $formdata["hdDelete"] == 3) {
			$pagename = "Arrange Customer";
			$columns = array();
			array_push($columns, "customercode","alternatecode", "customername");
			array_push($columns,"callrestrictiondays1","callrestrictiondays2","callrestrictiondays3");
			array_push($columns,"callrestrictiondays4","callrestrictiondays5","callrestrictiondays6","callrestrictiondays7");
			$columnNames = array();
			array_push($columnNames, "Code","Alternate Code","Customer Name","Monday","Tuesday","Wednesday","Thursday","Friday","Saturday", "Sunday");
			$this->createPrintingData($result[1],$columns, $columnNames, $pagename,$formdata["hdDelete"]);
		}
    }
    /**
    * @name       addcustomer
    * @since      17-2-2012
    * @version    Release: 1
    * @author     PM <pankit@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This Action is for coustomer sequence flow
    */
    public function addcustomerAction()
    {
        $this->view->params = $params = $this->getRequest()->getParams();
        $this->view->formdata = $formdata = $this->_request->getPost();
		$this->_helper->layout->setLayout('popup');
	
		if($formdata['add'])
		{
			foreach($formdata['chk'] as $key => $val)
			{
			$customer_code .= $val.'$';
			}
			
			$param_array 	= array();
			$param_array[1]	= substr($customer_code,0,-1);
			$param_array[2]	= $formdata['hdnroute_code'];
			$param_array[3]	= $formdata['hdnweek_no'];
			$param_array[4]	= count($formdata['chk']);
			$param_array[5]	= $this->currentUser->username;
			
			$result = $this->SFA_Comman->executequery('CALL sp_add_account_customersequence_addcustomer(?,?,?,?,?)',$param_array,'');
			SFA_Message::setMsg($this->translate->_('New Record'));
			
			$this->_helper->redirector('arrangecustomer', 'customerseq', 'account',array('week'=>$formdata['hdnweek_no'],'route'=>$formdata['hdnroute_code']));
		}
	
			// for redict on main route id which we select from arrance customer
		$main_route_id = (isset($params['main_route_id']) && $params['main_route_id'] > 0) ? $params['main_route_id'] : $params['route'];
		
		$this->view->main_route_id 	= $main_route_id;
		$this->view->route_val 		= $params['route'];
		
		// For Alternate Code Status.
		$cpanel				= $this->SFA_Comman->getaltcodestatus();
		$altcode_status		= $cpanel["Use Alternate Code"]['status'];
		
		$cols_array 	= array('customercode','alternatecode','customername','customercode AS edit_del_primary_id');
		$columns_show 	=  array($this->translate->_('Customer Code'),$this->translate->_('Alternate Code'),$this->translate->_('Customer Name'));
		
		// prepare the configuration for grid
		$pagingparams = array(
				"show_grid_heading" => false,
				"grid_heading_message" => $this->translate->_('Overview'),
				"show_records_per_page" => isset($formdata["show_records_per_page"])?$formdata["show_records_per_page"]:10,
				"show_searchbox" => true,
				"searchbox_value" => isset($formdata["grid_search_text"])?$formdata["grid_search_text"]:"",
				"show_selectbox" => true,
				"show_editlink" => false,
				"show_deletelink" => false,			
				"show_deleteall" => false,
				"primaryid" => "customercode",			
				"editlink" => array("/account/customer/addcustcat/id/#pattern#/edit/yes/","#pattern#"),
				"nodata_message" => $this->translate->_('No Record(s) Found'),
				"fetch_columns_inquery" => $cols_array,
				"show_columns" => $columns_show
				);				
		// create grid class object
		$pagingshow = new SFA_Paging($pagingparams);
		
		// call common function of grid class
		$get_return_vals = $pagingshow->commnfunc();	
		
		// call the stored procedure for fetch the data
		$param_array 		= array();
		$param_array[1] 	= '1';
		$param_array[2] 	= '';
		$param_array[3] 	= $get_return_vals['order_columns_name'];
		$param_array[4] 	= $get_return_vals['order_type'];
		$param_array[5] 	= $get_return_vals['offset'];
		$param_array[6] 	= (int)$get_return_vals['show_records_per_page'];
		$param_array[7] 	= implode(", ",$pagingparams["fetch_columns_inquery"]);
		$param_array[8] 	= strlen($get_return_vals['where_condition'])>0?$get_return_vals['where_condition']:'';
		$param_array[9] 	= $params['route'];
		$param_array[10] 	= $params['week'];
		$param_array[11] 	= $main_route_id;
		
		// called stored procedure for counter
		$result = $this->SFA_Comman->executequery('CALL sp_get_account_customerseq_addcustomer(?,?,?,?,?,?,?,?,?,?,?)',$param_array,'');
	
		$data_arr["count"] 			= $result[0][0]['counter'];
		$data_arr["data"][0]  		= $result[1];
		$this->view->route_info 	= $result[2];		
		$this->view->more_salesman 	= $result[3][0]['allowmorethanonesalesman'];
		
		$this->view->show_add_button = $result[0][0]['counter'];
		//SFA_Comman::pre($result);
		
		// pass the data in summary_showdatagrid() function & create a final variable for view
		$this->view->pagegridshow =  $pagingshow->summary_showdatagrid($data_arr);
		
		$this->view->addScriptPath(APPLICATION_PATH."/theme/default/datagrid/");	
    }
    /**
    * @name       arrangecustomer
    * @since      17-2-2012
    * @version    Release: 1
    * @author     PM <pankit@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This Action is for coustomer sequence flow
    */
    public function routesequenceAction()
    {
		$this->_helper->layout->setLayout('popup');
	
        $this->view->params 		= $params = $this->getRequest()->getParams();
        $this->view->formdata 		= $formdata = $this->_request->getPost();
		
		
		// For Alternate Code Status.
		$cpanel							= $this->SFA_Comman->getaltcodestatus();
		$this->view->altcode_status	= $cpanel["Use Alternate Code"]['status'];
	
		$this->view->selected_week 	= $params['week'] !='' ? $params['week'] : $formdata['week'];
		$this->view->selected_route	= $params['route'] !='' ? $params['route'] : $formdata['route'];
		$day_of_week = '';
		
		if($formdata['save'])
		{
			$seprate_day	= explode('_',$formdata['ddlday']);
			$dayname 		= strtolower($seprate_day[0]);
			
			$customer 		= array();
			$customer 		= explode(',',$formdata['hdncustomer_code']);
			
			if($formdata['hdncustomer_code'] !='' )
			{
			$param_array 	= array();
			$param_array[1] = $formdata['hdnroute'];
			$param_array[2] = $dayname.'seq';
			$param_array[3] = $formdata['hdnweek'];
			$param_array[4] = $formdata['hdncustomer_code'];
			$param_array[5] = count($customer);
			$param_array[6] = 'callrestrictiondays'.$seprate_day[1];			
			
			$result = $this->SFA_Comman->executequery('CALL sp_add_account_customerseq_routeseq(?,?,?,?,?,?)',$param_array,'');
			
			if($result[0][0]['val'] > 0)
				SFA_Message::setMsg($this->translate->_('Save Record'));
			else
				SFA_Message::setErrorMsg($this->translate->_("You Did Not Selected User Record(s) In Rotue Sequence."));
			}
			else
			{
				SFA_Message::setMsg($this->translate->_('Save Record'));
			}	
		
			$this->view->selected_dayname 	= $formdata['ddlday'];
			$this->view->selected_route		= $formdata['hdnroute'];
			
			$paramarray = array();
			$paramarray[1] = $formdata['hdnroute'];
			$paramarray[2] = $formdata['hdnweek'];
			$paramarray[3] = 'callrestrictiondays'.$seprate_day[1];
			$paramarray[4] = $seprate_day[0].'seq';
			
			$res 			= $this->SFA_Comman->executequery('CALL sp_get_account_customerseq_routesequence(?,?,?,?)',$paramarray,'');
			$this->view->route_data	= $res[0];
			$day_of_week		= $res[1][0]['weekstartday'];
			$this->view->customer_info	= $res[2];
			$this->view->show_button	= count($res[2]);
		}
		elseif($formdata['loaddata'] || $formdata["hdDelete"] == 1)
		{
			$day = explode('_',$formdata['ddlday']);
			
			$param_array    	= array();
			$param_array[1] 	= $formdata['hdnroute'];
			$param_array[2] 	= $formdata['hdnweek'];	    
			$param_array[3] 	= 'callrestrictiondays'.$day[1];
			$param_array[4] 	= $day[0].'seq';					
			$result 		= $this->SFA_Comman->executequery('CALL sp_get_account_customerseq_routesequence(?,?,?,?)',$param_array,'');
			$day_of_week	= $result[1][0]['weekstartday'];
			/*sss*/
			$start_of_day_week 		= $day_of_week;
			$end_of_day_week 		= $day_of_week+7;
			
			$day_name = array();
			$j=0;
			for($i=$start_of_day_week;$i<$end_of_day_week;$i++)
				{
					$datashow = $i;
					if($i > 7)
					{
					$datashow = $i % 7;
					}
				$dayname 		 = date("l",mktime(0,0,0,10,$datashow,2012));
				$day_name[$j]['val'] = $dayname;
				$day_name[$j]['id']  = strtolower(substr($dayname,0,3))."_".($j+1);
				$j++;
				}
			 $show_day = $day_name[$day[1]-1]['val'];
			
			/*sss*/
			$this->view->show_button		= count($result[2]);
			$this->view->selected_dayname 	= $formdata['ddlday'];
			$this->view->route_data		= $result[0];
			$this->view->customer_info		= $result[2];
			if($formdata["hdDelete"] == 1) {
			/*new sp added for print on 25Feb2016 by ng*/
			$param_array    	= array();
			$param_array[1] 	= $formdata['hdnroute'];
			$param_array[2] 	= $formdata['hdnweek'];	    
			$param_array[3] 	= 'callrestrictiondays'.$day[1];
			$param_array[4] 	= $day[0].'seq';
			$param_array[5] 	= 0;
			if($formdata['printallday']=="on")
				{
				$show_day="All Days";
				$param_array[5] 	= 1;
				}
			$print_result 		= $this->SFA_Comman->executequery('CALL sp_get_account_customerseq_routesequence_print(?,?,?,?,?)',$param_array,'');
		//	var_dump($print_result);
				$pagename = "Route Sequence";
				$columns = array();
				array_push($columns, "customercode","alternatecode","dayname", "customername","customeraddress1");
				$columnNames = array();
				array_push($columnNames, "Customer ID","Customer Code","Visit Day", "Customer Name","Address");
				$this->createPrintingData($print_result[2],$columns, $columnNames, $pagename,$formdata["hdDelete"],$show_day);
				
			}			
		}
		else
		{
			$week_start		= $this->SFA_Comman->executequery('CALL sp_get_weekstart_day()','','');
			$day_of_week	= $week_start[0][0]['weekstartday'];
			$dayname		= strtolower(substr(date("l",mktime(0,0,0,10,$day_of_week,2012)),0,3));
		
			$param_array    = array();
			$param_array[1] = $params['route'];
			$param_array[2] = $params['week'];
			$param_array[3] = 'callrestrictiondays1';
			$param_array[4] = $dayname.'seq';
			
			$result = $this->SFA_Comman->executequery('CALL sp_get_account_customerseq_routesequence(?,?,?,?)',$param_array,'');
			
			$this->view->show_button		= count($result[2]);
			$this->view->selected_dayname 	= $dayname."_1";
			$this->view->route_data		= $result[0];
			$this->view->customer_info		= $result[2];
		}	
		
		$start_of_day_week 		= $day_of_week;
		$end_of_day_week 		= $day_of_week+7;
		
		$day_name = array();
		$j=0;
		for($i=$start_of_day_week;$i<$end_of_day_week;$i++)
		{
			$datashow = $i;
			if($i > 7)
			{
			$datashow = $i % 7;
			}
			$dayname 		 = date("l",mktime(0,0,0,10,$datashow,2012));
			$day_name[$j]['val'] = $dayname;
			$day_name[$j]['id']  = strtolower(substr($dayname,0,3))."_".($j+1);
			$j++;
		}
		$this->view->day_name = $day_name;
	
    }

    /**
    * @name       copysequence
    * @since      17-2-2012
    * @version    Release: 1
    * @author     PM <pankit@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This Action is for coustomer sequence flow
    */
    public function copysequenceAction(){
        $this->view->params = $params = $this->getRequest()->getParams();
        $this->view->formdata = $formdata = $this->_request->getPost();

        $this->_helper->layout->setLayout('popup');
	
		$result = $this->SFA_Comman->executequery('CALL sp_get_admin_index_index()','','');	
		$route_seq = $result[0][0]['routesequenceplanflag'];
		$week = array();
		if($route_seq == 1)
		{
			$week[0]['id'] = 9;
			$week[0]['val'] = 9;
		}
		else
		{
			$week[0]['val'] = 1;
			$week[1]['val'] = 2;
			$week[2]['val'] = 3;
			$week[3]['val'] = 4;
			$week[0]['id'] = 1;
			$week[1]['id'] = 2;
			$week[2]['id'] = 3;
			$week[3]['id'] = 4;
		}	
			$this->view->week = $week;
		
		$day_of_week		= $result[0][0]['weekstartday'];
		$start_of_day_week 	= $day_of_week;
		$end_of_day_week 	= $day_of_week+7;
		
		$day_name = array();
		$j=0;
		for($i=$start_of_day_week;$i<$end_of_day_week;$i++)
		{
			$datashow = $i;
			if($i > 7)
			{
			$datashow = $i % 7;
			}
			
			$day_name[$j]['val'] = date("l",mktime(0,0,0,10,$datashow,2012));
			$day_name[$j]['id']  = ($j+1);
			$j++;
		}
		$this->view->day_name = $day_name;
		
		$this->view->selected_route	= $params['route'] > 0 ? $params['route'] : $formdata['hdnroute'];
	
		if(isset($formdata['copyseq']))
		{
			$param_array	= array();
			$param_array[1]	= $formdata['ddlfromweek'];
			$param_array[2]	= $formdata['ddltoweek'];
			$param_array[3]	= $formdata['ddlfromday'];
			$param_array[4]	= $formdata['ddltoday'];
			$param_array[5]	= $formdata['hdnroute'];	    
			$param_array[6]	= $this->currentUser->username;
	
			if($formdata['ddlfromday'] == '8') {			
				$result = $this->SFA_Comman->executequery('CALL sp_add_account_customerseq_copysequenceall(?,?,?,?,?,?)',$param_array,'');
			}
			elseif($formdata['ddlfromday'] < '8' && $formdata['ddltoday'] == '8') {			
				$result = $this->SFA_Comman->executequery('CALL sp_add_account_customerseq_copysequence_toall(?,?,?,?,?,?)',$param_array,'');
			}
			else {			
				$result = $this->SFA_Comman->executequery('CALL sp_add_account_customerseq_copysequence(?,?,?,?,?,?)',$param_array,'');
			}			
			if($result[0][0]['result'] > 0) {
				SFA_Message::setMsg($this->translate->_('Copy sequence successfully.'));
			}
			else {
				SFA_Message::setErrorMsg($this->translate->_('From Week record not found in Route Sequence.'));
			}
		}
    }
    private function createPrintingData($result,$columns,$columnNames,$pagename,$action,$show_day) {
    	$resultData = array();
		for($i=0;$i < sizeof($result);$i++) {
			for($j=0;$j<sizeof($columns);$j++) {
				$dataArr[$columns[$j]] = $result[$i][$columns[$j]];
			}
			$resultData[$i] = $dataArr;
		}
		if($action == 2 || $action == 1) {
	        $this->printData($resultData, $columnNames,$pagename,$show_day);
		} else if($action == 3) {
        	$this->exportData($resultData, $columnNames,$pagename);
		}
    }
    private function printData($resultData, $columnNames,$pagename,$show_day)
    {
        $time = $this->SFA_Comman->executequery('CALL sp_get_server_time()','','');
        $time = $time[0][0]['currentdate'];
        $out = '';
        $out .= '<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01//EN" "http://www.w3.org/TR/html4/strict.dtd">';
        $out .= '<html lang="en">';
        $out .= '<head><link href="'.$this->SFA_Comman->getSiteBaseUrl().'public/css/style.css" media="screen" rel="stylesheet" type="text/css" ></head>';
        $out .= '<body>';
        $out .= '<div id="noprint" name="noprint" style="float:left;margin-top:20px;margin-left:10px">';
        $out .= '<input class="border_none" type="button" value="Print" name="btnprintform" id="btnprintform" onclick="document.getElementById(\'noprint\').style.display = \'none\';window.print();window.close();">';        
        $out .= '</div>';
	
        $out .= '<div style="float:right;margin-top:20px;margin-right:20px">';
        $out .= '<b>Created Date :-</b> '.$time;
        $out .= '</div>';
		
        $out .= '<div style="width:100%;clear:both;">';
        $out .= '<div style="float:left;margin-top:20px;margin-left:10px;">';
        $out .= '<b>Print :- '.$pagename .'</b>';
        $out .= '</div>';
		/*by ng */
		if($show_day!='')
		{
		$out .= '<div style="float:left;margin-top:20px;margin-left:10px;">';
        $out .= '<b>Day :- </b>'.$show_day ;
        $out .= '</div>';
		}
		/*end by ng*/
        $out .= '</div>';
        $out .= '<div style="clear:both;overflow-y:auto; width:100%; height:Auto;">';
        $out .= '<table id="table-example" class="table" width="100%">';        
        $out .= '<tr><td><br><br></td></tr>';
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
        if(isset($resultData) && sizeof($resultData) > 0)
        {
            foreach($resultData as $key => $val)
            {
                $out .= '<tr>';
                $out .= '<td width="3"></td>';
                foreach($val as $k=>$v)
                {
                    $out .= '<td align="left">';
                    $out .= trim($v);
                    $out .= '</td>';
                }
                $out .= '</tr>';
            }
        }
        $out .= '<tr><td><br></td></tr>';        
        $out .= '</table>';
        $out .= '</div>';
        $out .= '</body>';
        $out .= '</html>';
        echo $out;
        exit;
    }
    function exportData($resultData, $columnNames, $pagename)
    {
        $out = '';
        if(isset($columnNames[0]) && $columnNames[0] != "")
        {
            foreach($columnNames as $key => $val)
            {
                $out .= $val;
                $out .= ",";
            }
            $out .= "\n";
        }
        if(isset($resultData) && sizeof($resultData) > 0)
        {
            foreach($resultData as $key => $val)
            {
                foreach($val as $k=>$v)
                {
                    $v = str_replace(",", "", $v);
                    $out .= trim($v);
                    $out .= ",";
                }
                $out .= "\n";
            }
        }
        $filename = time().'.csv';
        header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
        header("Content-Length: " . strlen($out));
        header("Content-type: text/x-csv");
        header("Content-Disposition: attachment; filename=$filename");
        echo $out;
        exit;
    }
}