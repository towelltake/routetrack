<?php
/**
* @name       IndexController
* @since
* @version    Release: 1
* @author     HD <hiren.d@elantechnologies.com>
* @copyright  Elan Technologies
* @param
*
* This controller is manage user signup module.
*/
class Admin_IndexController extends Admin_Library_Controller_Action_Abstract
{
    /**
    * @name       init
    * @since      30-11-2011
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
	$this->view->required	= $this->translate->_('Required');
	$this->view->colan	= $this->translate->_('Colan');
	
	$this->SFA_Model_Index 	= new SFA_Model_Index();
        $this->SFA_Comman 	= new SFA_Comman();
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
    * @name       Index
    * @since      30-11-2011
    * @version    Release: 1
    * @author     HD <hiren.d@elantechnologies.com>
    * @changedby   PM <pankit@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This Action is for setup website setting
    */
    public function indexAction()
    {
		$this->view->params = $params = $this->getRequest()->getParams();
        $this->view->formdata = $formdata = $this->_request->getPost();

        //journey plan array
        $journeyplan = array();
        $journeyplan[0]['id'] = '1';
        $journeyplan[0]['val'] = 'Only One Day';
        $journeyplan[1]['id'] = '2';
        $journeyplan[1]['val'] = 'All Days';
        $this->view->journeyplan  = $journeyplan;

        //route sequence array
        $routseq = array();
        $routseq[0]['id'] = '1';
        $routseq[0]['val'] = 'Generic Week';
        $routseq[1]['id'] = '2';
        $routseq[1]['val'] = 'Sales Week';
		$this->view->routeseq  = $routseq;

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
        $this->view->wkdays  = $wkdays;


        //transfer inventory
        $transinvArr = array();
        $transinvArr[0]['id'] = 0;
        $transinvArr[0]['val'] = 'Disable';
        $transinvArr[1]['id'] = 1;
        $transinvArr[1]['val'] = 'Only On Routes';
        $transinvArr[2]['id'] = 2;
        $transinvArr[2]['val'] = 'Only On Depots';
        $transinvArr[3]['id'] = 3;
        $transinvArr[3]['val'] = 'On Routes/Depot';
        $this->view->transinv  = $transinvArr;

        //Convert to load
        $convertArr = array();
        $convertArr[0]['id'] = 0;
        $convertArr[0]['val'] = 'Disable';
        $convertArr[1]['id'] = 1;
        $convertArr[1]['val'] = 'Not Used';
        $convertArr[2]['id'] = 2;
        $convertArr[2]['val'] = 'Convert Suggested Load to Salesman Load';
        $this->view->convertToLoad  = $convertArr;
	
		//Multi Day Post
        $multidayArr = array();
        $multidayArr[0]['id'] = 0;
        $multidayArr[0]['val'] = 'Route Start Date';
        $multidayArr[1]['id'] = 1;
        $multidayArr[1]['val'] = 'Route End Date';
        $this->view->multidayPost  = $multidayArr;

        //check Record availability in setup table.
        $param_array = array();
        $param_array[1] = 'setup';
        $param_array[2] = 'setupID';
        $setupdata = $this->SFA_Comman->executequery('CALL sp_countdata(?,?)',$param_array,'');
        $counter = $setupdata[0][0]['counter'];


       if(count($formdata) > 0 )
	   {
           //An array for add/edit values of setup page
            $param_array = array();
            $param_array[1]   =  1;  // setupid
            $param_array[2]   =  $formdata["txtconvrate"];  // conversionrate
            $param_array[3]   =  $formdata["txtcurrencysym"];  // currencysymbol
            $param_array[4]   =  $formdata["txtarbcurrencysym"];  // arbcurrencysymbol            
            $param_array[5]   =  $formdata["ddljourneyplan"];  // journeyplanflag
            $param_array[6]   =  $formdata["ddlrouteseq"];  // routesequenceplanflag
            $param_array[7]   =  $formdata["ddlweekstday"];  // weekstartday
            $param_array[8]   =  $formdata["txtmisrpt"];  // importfilepath
            $param_array[9]   =  (!empty($formdata["chkconprepfile"]) ? 1 : 0);  // allowcontrolonpreparefilesflag
            $param_array[10]  =  (!empty($formdata["chkgchhc"]) ? 1 : 0);  // allownormalgccollectionhhcflag
            $param_array[11]  =  (!empty($formdata["chkprepfileupl"]) ? 1 : 0);  // allowpreparefilesafterupload
            $param_array[12]  =  (!empty($formdata["chkdefprepfile"]) ? 1 : 0);   // defaultsonpreparefilesflag
            $param_array[13]  =  (!empty($formdata["chkresenddataerp"]) ? 1 : 0);  //resenddatatoerpallowed
            $param_array[14]  =  (!empty($formdata["chkallowcashinvoncrcust"]) ? 1 : 0); // allowcashinvoiceoncreditcust
            $param_array[15]  =  (!empty($formdata["chhkfilebaserestri"]) ? 1 : 0);  //callrestrictiondaysflag
            $param_array[16]  =  (!empty($formdata["chksalesmancust"]) ? 1 : 0); //allowmorethanonesalesman
            $param_array[17]  =  $formdata["ddltransinv"];  //transferinventoryflag
            $param_array[18]  =  $formdata["ddlconvtload"];  // enableloadrequesttoloadout
            $param_array[19]  =  (!empty($formdata["chkresfileday"]) ? 1 : 0); // restrictprepareile
            $param_array[20]  =  $formdata["ddlmultidaypost"];  // multidaypostingdate
			$param_array[21]  =  (!empty($formdata["chkprevdayupl"]) ? 1 : 0); // previousdayuploadflag
			$param_array[22]  =  $formdata["ddltablet_synmode"]; // tabletsyncmode
			$param_array[23]  =  ($formdata["ddltablet_synmode"] ==2) ? $formdata['txttimeinterval'] : '' ; // tabletsyncmode			
			$param_array[24]  =  $this->currentUser->username;			

           if($counter > 0){
				//update logic
				$result = $this->SFA_Comman->executequery('CALL sp_edit_admin_index_index(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',$param_array,'');
				if($result){
					SFA_Message::setMsg($this->translate->_('Update Record'));					
				}
            } else {
				//add logic
				/* Insert data */
				$result = $this->SFA_Comman->executequery('CALL sp_add_admin_index_index(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',$param_array,'');
				 if($result){
					SFA_Message::setMsg($this->translate->_('New Record'));
				}
            }
			
			$Setup_NameSpace = new Zend_Session_Namespace('Setup');
			unset($Setup_NameSpace->options);
			$Setup_NameSpace->options['misreport']	= $formdata["txtmisrpt"];
	    
			/**
			 * Hiren dave on 15th may 2015
			 * Below condition is check wheather weekday is different from the existing or not if not then we have to change salescalender.	    
			*/
			if($formdata["hdnweekday"] == 0)
			{
				$day = array(1 => 'monday',2 => 'tuesday',3 => 'wednesday',4 => 'thursday',5 => 'friday',6 => 'saturday',7 => 'sunday');
				
				$params['year'] = date("Y");
				
				$day_of_week 	= $formdata['ddlweekstday'];
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
				$k = 1;
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
					if($im==1){        
					$zz = $k;
					$k++;
					}
					else{
					$zz = $k++;
					//$zz = date("W",strtotime($second));			
					//if($formdata['ddlweekstday'] < 5) {
					//    $zz = date("W", strtotime(date("Y-m-d", strtotime($second)) . " +".$formdata['ddlweekstday']." day"));
					//}
					//else {
					//    $zz = date("W", strtotime(date("Y-m-d", strtotime($first)) . " +".$formdata['ddlweekstday']." day"));
					//}
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
				
				$result = $this->SFA_Comman->executequery('CALL sp_delete_account_customerseq_salescalendar(?)',$params['year'],'');
				
				$success = $this->SFA_Model_Index->salescalendar($week_data,$params['year']);
			}
			$this->_helper->redirector("index", "admin", "home");
	
       }else{
           //for geting existing setup detail
            $resultdata = $this->SFA_Comman->executequery('CALL sp_get_admin_index_index()','','');
            $setupdata = $resultdata[0];


            $data = array();
            $data["txtsetupid"] = $setupdata[0]['setupid'];  // setupid
            $data["txtconvrate"] = $setupdata[0]['conversionrate'];  // conversionrate
            $data["txtcurrencysym"] = $setupdata[0]['currencysymbol'];  // currencycymbol
            $data["txtarbcurrencysym"] = $setupdata[0]['arbcurrencysymbol'];  // arbcurrencysymbol            
            $data["ddljourneyplan"] = $setupdata[0]['journeyplanflag'];  // journeyplanflag
            $data["ddlrouteseq"] = $setupdata[0]['routesequenceplanflag'];  // routesequenceplanflag
            $data["ddlweekstday"] = $setupdata[0]['weekstartday'];  // weekstartday
            $data["txtmisrpt"] = $setupdata[0]['importfilepath'];  // importfilepath
            $data["chkconprepfile"] = $setupdata[0]['allowcontrolonpreparefilesflag'];  // allowcontrolonpreparefilesflag
            $data["chkgchhc"]= $setupdata[0]['allownormalgccollectionhhcflag'];  // allownormalgccollectionhhcflag
            $setupdata[0]['allowpreparefilesafterupload']; $data["chkprepfileupl"] = $setupdata[0]['allowpreparefilesafterupload'];  // allowpreparefilesafterupload
            $data["chkdefprepfile"] = $setupdata[0]['defaultsonpreparefilesflag'];  // defaultsonpreparefilesflag
            $data["chkresenddataerp"] = $setupdata[0]['resenddatatoerpallowed']; //resenddatatoerpallowed
            $data["chkallowcashinvoncrcust"] = $setupdata[0]['allowcashinvoiceoncreditcust']; // allowcashinvoiceoncreditcust
            $data["chhkfilebaserestri"] = $setupdata[0]['callrestrictiondaysflag'];  //callrestrictiondaysflag
            $data["chksalesmancust"] = $setupdata[0]['allowmorethanonesalesman'];  //allowmorethanOnesalesman
            $data["ddltransinv"] = $setupdata[0]['transferinventoryflag'];  //transferinventoryflag
            $data["ddlconvtload"] = $setupdata[0]['enableloadrequesttoloadout'];  // enableloadrequesttoloadout
            $data["chkresfileday"] = $setupdata[0]['restrictpreparefile'];  // restrictprepareFile
            $data["ddlmultidaypost"] = $setupdata[0]['multidaypostingdate'];  // multidaypostingdate
            $data["chkprevdayupl"] = $setupdata[0]['previousdayuploadflag'];  // previousdayuploadflag
			$data["ddltablet_synmode"] = ($setupdata[0]['tabletsyncmode']) !='' ? $setupdata[0]['tabletsyncmode'] : 0;  // tabletsyncmode
			$data["txttimeinterval"] = $setupdata[0]['synctimeinterval'];  // synctimeinterval
			
			$data["cnt"] = $resultdata[1][0]['cnt'];  // counter
			
			$this->view->formdata = $data;
       }
    }
    /**
    * @name       Setup Setting
    * @since      30-11-2011
    * @version    Release: 1
    * @author     HD <hiren.d@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This Action is for setup setting
    */
    public function setupsetAction()
    {
		$this->view->params = $params = $this->getRequest()->getParams();
        $this->view->formdata = $formdata = $this->_request->getPost();

		$this->view->title 				= 'Setup';
		$this->view->overview 				= 'Overview';
		$this->view->setting 				= 'Setting 1';
		$this->view->transactionid 			= 'Transaction ID';
		$this->view->hhc_import_file_path		= 'HHC Import File Path';
		$this->view->hhc_download_file_path		= 'HHC Download File Path';
		$this->view->hhc_upload_file_path		= 'HHC Upload File Path';
		$this->view->hhc_export_file_path		= 'HHC Export File Path';
		$this->view->hhc_backup_file_path		= 'HHC Backup File Path';
		$this->view->copy_prepare_fileto		= 'CopyPrepare Files To';
		$this->view->copy_sesion_fileto			= 'CopySession Files To';
		$this->view->initialise_variables		= 'Initialise Application Variables';
		$this->view->back				= $this->translate->_('Back');
		$this->view->cancel				= $this->translate->_('Cancel');
		$this->view->save				= $this->translate->_('Save');
		$this->view->submit				= $this->translate->_('Submit');
		$this->view->reset				= $this->translate->_('Reset');
    }
    /**
    * @name       archivedataAction
    * @since      28-12-2011
    * @version    Release: 1
    * @author     HD <hiren.d@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This Action is for Load Archive Data
    */
    public function archivedataAction()
    {
	$this->view->params = $params = $this->getRequest()->getParams();
        $this->view->formdata = $formdata = $this->_request->getPost();


	$table_data = array();
	$table_data[0]['id'] 	= 1;
	$table_data[0]['value'] = '1 - Company';
	$table_data[1]['id'] 	= 2;
	$table_data[1]['value'] = '2 - Region';
	$table_data[2]['id'] 	= 3;
	$table_data[2]['value'] = '3 - Area';
	$table_data[3]['id'] 	= 4;
	$table_data[3]['value'] = '4 - Route';
	$table_data[4]['id'] 	= 5;
	$table_data[4]['value'] = '5 - Customer';
	$table_data[5]['id'] 	= 6;
	$table_data[5]['value'] = '6 - Salesman';
	$table_data[6]['id'] 	= 7;
	$table_data[6]['value'] = '7 - ControlPanel';
	$table_data[7]['id'] 	= 8;
	$table_data[7]['value'] = '8 - Advanced Pricing Key';
	$table_data[8]['id'] 	= 9;
	$table_data[8]['value'] = '9 - Advanced Pricing Plan';
	$this->view->table_data = $table_data;


	$user_data = array();
	$user_data[0]['id'] 	= 1;
	$user_data[0]['value'] 	= 'admin';
	$user_data[1]['id'] 	= 2;
	$user_data[1]['value'] 	= 'asghar';
	$user_data[2]['id'] 	= 3;
	$user_data[2]['value'] 	= 'Bertilla';
	$user_data[3]['id'] 	= 3;
	$user_data[3]['value'] 	= 'Freddy';
	$user_data[4]['id'] 	= 4;
	$user_data[4]['value'] 	= 'Nilesh';
	$user_data[5]['id'] 	= 5;
	$user_data[5]['value'] 	= 'Prem';
	$this->view->user_data	= $user_data;

    }
}