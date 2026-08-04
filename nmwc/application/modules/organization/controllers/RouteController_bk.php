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
class Organization_RouteController extends Organization_Library_Controller_Action_Abstract
{
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
    public $SFA_Model_Index = '';

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
		$this->view->general	= $this->translate->_('General');
		$this->view->setting1	= $this->translate->_('Settings 1');
		$this->view->setting2	= $this->translate->_('Settings 2');
		$this->view->reports	= $this->translate->_('Reports');
		$this->view->required	= $this->translate->_('Required');
		$this->view->colan	= $this->translate->_('Colan');
	
		$this->SFA_Model_Index	= new SFA_Model_Index();
		$this->SFA_Comman		= new SFA_Comman();
		$this->view->decimalplaces 	= $this->SFA_Comman->getdecimalplaces();
		$this->view->sec_lang		= $this->SFA_Comman->getsecondlanguage();
		$this->sec_lang 		= $this->view->sec_lang;
		$this->decimalplaces 		= $this->view->decimalplaces;	
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
    
    
    
    public function routeAction()
    {	
		$this->view->params = $params = $this->getRequest()->getParams();
		$this->view->formdata = $formdata = $this->_request->getPost();
		
		if($formdata["hdDelete"]==1)
		{
			$ids = implode(',',$formdata['chk']);
			$param_array 	= array();
			$param_array[1]	= $ids;
			$param_array[2]	= $this->currentUser->username;
			
			$result 	= $this->SFA_Comman->executequery('CALL sp_delete_organization_route_route(?,?)',$param_array,'');
			
			if($result[0][0]['deleted_id'] =='')
			{
				$ids		= explode(',',$ids);
				$checked 	= $ids;
				
				SFA_Message::setErrorMsg($this->translate->_('Record Already In use'));
			}
			else
			{
				$deleted_id = explode(',',$result[0][0]['deleted_id']);
				$ids		= explode(',',$ids);
				$checked 	= array_diff($ids,$deleted_id);
				
				if(count($ids) != count($deleted_id)){
					SFA_Message::setErrorMsg($this->translate->_('Record Already In use'));
				}
				
				SFA_Message::setMsg($this->translate->_('Delete Record'));
			}
		}
		
		
		$cols_array = array('route.routecode','route.routename','salesman.salesmancode','salesman.salesmanname1','subarea.subareaname','route.activestatus');
			
		$columns_show =  array($this->translate->_('Route Code'),
				$this->translate->_('Route Name'),
				$this->translate->_('Salesman Code'),
				$this->translate->_('Salesman'),
				$this->translate->_('Sub Area'),	
				$this->translate->_('Status'));
		
		if($this->css == 'ar_') {
			$cols_array[1]	= 'arbroutename';
			$cols_array[3]	= 'arbsalesmanname1';
			$cols_array[4]	= 'arbsubareaname';
		}
		
		// prepare the configuration for grid
		$pagingparams = array(
				 "show_grid_heading" => true,
				 "grid_heading_message" => $this->translate->_('Overview'),
				 "pagename" => $this->translate->_('Route'),
				 "show_records_per_page" => isset($formdata["show_records_per_page"])?$formdata["show_records_per_page"]:10,
				 "show_searchbox" => true,
				 "searchbox_value" => isset($formdata["grid_search_text"])?$formdata["grid_search_text"]:"",
				 "show_selectbox" => true,
				 "selected_list" => $checked,
				 "show_editlink" => true,
				 "show_deletelink" => false,
				 "show_deleteall" => false,
				 "primaryid" => "routecode",
				 "status_cols" => array(
							array(
							"cols_name" => "activestatus",
							"status_change" => array("0"=>"Inactive","1"=>"Active")
							)
							),
				 "editlink" => array("/organization/route/addroute/id/#pattern#/edit/yes/","#pattern#"),
				 "nodata_message" => $this->translate->_('No Record(s) Found'),
				 "fetch_columns_inquery" => $cols_array,
				 "show_columns" => $columns_show
				 
				 );
        if(!$this->checkaccess("update"))
        {
            $pagingparams["show_editlink"] = false;
        }
		// create grid class object
		$pagingshow = new SFA_Paging($pagingparams);
		
		// call common function of grid class
		$get_return_vals = $pagingshow->commnfunc();
		
		//print_r($get_return_vals['where_condition']);
		
		// call the stored procedure for fetch the data
		$param_array    = array();
		$param_array[1] = '1';
		$param_array[2] = '';
		$param_array[3] = $get_return_vals['order_columns_name'];
		$param_array[4] = $get_return_vals['order_type'];
		$param_array[5] = $get_return_vals['offset'];
		$param_array[6] = (int)$get_return_vals['show_records_per_page'];
		$param_array[7] = implode(", ",$pagingparams["fetch_columns_inquery"]);
		$param_array[8] = strlen($get_return_vals['where_condition'])>0?$get_return_vals['where_condition']:'';
		
		$downloadCSV = (isset($formdata['downloadcsv'])) ? $formdata['downloadcsv'] : $params['downloadcsv'];
    
		// Hiren Dave on 19 Nov, 2012 - START - to check if need to print data
		$printData = (isset($formdata['printData'])) ? $formdata['printData'] : $params['printData'];
		// called stored procedure for counter
		$result = $this->SFA_Comman->executequery('CALL sp_get_organization_route_route(?,?,?,?,?,?,?,?)',$param_array,'',$downloadCSV,$printData,2,$pagingparams);
		
		$data_arr["count"] 	= $result[0][0]['counter'];
		$data_arr["data"][0] 	= $result[1];
		
		// pass the data in summary_showdatagrid() function & create a final variable for view
		$this->view->pagegridshow =  $pagingshow->summary_showdatagrid($data_arr);
		
		$this->view->addScriptPath(APPLICATION_PATH."/theme/default/datagrid/");
    }
    
    /**
    * @name       addrouterAction
    * @since      21-12-2011
    * @version    Release: 1
    * @author     HD <hiren.d@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This action is use for the add route details
    */
    public function addrouteAction()
    {
		$this->view->params = $params = $this->getRequest()->getParams();
		$this->view->formdata = $formdata = $this->_request->getPost();
		
		$print_option = array();
		$print_option[0]['val']	= 'Do Not Print';
		$print_option[1]['val']	= 'Optional, Prompt User';
		$print_option[2]['val']	= 'Force Print';
		$print_option[0]['id']	= '0';
		$print_option[1]['id']	= '1';
		$print_option[2]['id']	= '2';
		$this->view->print_option = $print_option;		
		
		//called function for setting1 values
		$this->setting1values();
		//call function for setting2 values
		$this->setting2values();
	
		//reports dropdown fill
		$print_option = array();
		$print_option[0]['val']	= 'Disable Print';
		$print_option[1]['val']	= 'Optional (User Choice)';
		$print_option[2]['val']	= 'Force Print';
		$print_option[0]['id']	= '0';
		$print_option[1]['id']	= '1';
		$print_option[2]['id']	= '2';
		$this->view->print_option = $print_option;
		
		$route_type = array();
		$route_type = array(array('id'=>0,'val'=>'0 - Enable Order And Sales Process (History & Goal For Order Only)'),
				array('id'=>1,'val'=>'1 - Enable Order And Sales Process (History & Goal For Sales Only)'),
				array('id'=>2,'val'=>'2 - Enable Order Process Only'),
				array('id'=>3,'val'=>'3 - Enable Sales Process Only'));
		$this->view->route_type	= $route_type;	
	
	
		if($formdata['hdnid'] > 0){
			$param_array[1] = $formdata['txtroute_name'];		//routename
			$param_array[2] = $formdata['txtsecond_lang'];		//arbroutename
			$param_array[3] = $formdata['ddlsubarea'];		//subareacode
			$param_array[4] = $formdata['ddlsalesman'];		//salesmancode
			$param_array[5] = $this->currentUser->username;			//modified
			$param_array[6] = $formdata[''];			//mdat
			$param_array[7] = $formdata['txtpwd1'];			//password1
			$param_array[8] = $formdata['txtpwd2'];		//password2
			$param_array[9] = $formdata['txtpwd3'];		//password3
			$param_array[10] = $formdata['txtpwd4'];		//password4
			$param_array[11] = $formdata['txtpwd5'];		//password5
			$param_array[12] = $formdata['txtdt_time'];		//passwordarray01
			$param_array[13] = $formdata['txtpr_chng'];		//passwordarray02
			$param_array[14] = $formdata['txtpromo_ovr'];		//passwordarray03
			$param_array[15] = $formdata['txtrt_setup'];		//passwordarray04
			$param_array[16] = $formdata['txttel_setup'];		//passwordarray05
			$param_array[17] = $formdata['txtload_adj'];		//passwordarray06
			$param_array[18] = $formdata['txtst_day'];		//passwordarray07
			$param_array[19] = $formdata['txtapp_exit'];		//passwordarray08
			$param_array[20] = $formdata['txtsettl'];		//passwordarray09
			$param_array[21] = $formdata['txtload_sec'];		//passwordarray10
			$param_array[22] = $formdata['txtunload'];		//passwordarray11
			$param_array[23] = $formdata['txtload_out'];		//passwordarray12
			$param_array[24] = $formdata['txtprnt_doc'];		//passwordarray13
			$param_array[25] = $formdata['txtload_trans'];		//passwordarray14
			$param_array[26] = $formdata['txtload_req'];		//passwordarray15
			$param_array[27] = $formdata['txtnewreq'];		//passwordarray16
			$param_array[28] = $formdata['chkdepot_route'];		//deliveryroute
			$param_array[29] = $formdata['chkallow_chng'];		//presalesorder
			$param_array[30] = $formdata['txtaltcode'];		//alternateroutecode			
			$param_array[31] = ($formdata['ddlbusi_type'] > 0) ? $formdata['ddlbusi_type'] : 'NULL';
			$param_array[32] = ($formdata['ddlvan'] > 0) ? $formdata['ddlvan'] : 'NULL' ; //vehicle no
			$param_array[33] = $formdata['ddlregion'];		//regioncode
			$param_array[34] = $formdata['ddlstatus'];		//Active Status
			$param_array[35] = $formdata['ddlcompany'];		//cmycode			
			$param_array[36] = $formdata['chkallow_route_startday']; //cmpycode
			$param_array[37] = $formdata['txtcode'];		//Routecode
			
			
		
			$result = $this->SFA_Comman->executequery('CALL sp_edit_organization_route_addroute(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',$param_array,'');
			if($result[0][0]['result'] == 'duplicate'){
				SFA_Message::setErrorMsg($this->translate->_('Record(s) already Exist'));
			}else{
					$param_setting1_array[1]=$formdata['hdnid']; //routecode
					$param_setting1_array[2]=$formdata['ddlunloadoversellmessage']; // unloadoversellmessage
					$param_setting1_array[3]=$formdata['ddlinventoryvalueprint']; // inventoryvalueprint
					$param_setting1_array[4]=$formdata['ddlpromptodominput']; // promptodominput
					$param_setting1_array[5]=$formdata['ddlinventorycaseinput']; // inventorycaseinput
					$param_setting1_array[6]=$formdata['ddlloadreqreportformat']; // loadreqreportformat
					$param_setting1_array[7]=$formdata['ddlautocalculateloadin']; // autocalculateloadin
					$param_setting1_array[8]=$formdata['ddlrequireloadin']; // requireloadin
					$param_setting1_array[9]=$formdata['ddlloadsheetreport']; // loadsheetreport
					$param_setting1_array[10]=$formdata['ddlamountdecimaldigits']; // amountdecimaldigits
					$param_setting1_array[11]=$formdata['ddlitemcodedisplay']; // itemcodedisplay
					$param_setting1_array[12]=$formdata['ddlusealternatecodes']; // usealternatecodes
					$param_setting1_array[13]=$formdata['ddlrouteitemgrpcode']; // routeitemgrpcode
					$param_setting1_array[14]=''; // 
					$param_setting1_array[15]=$formdata['ddlitemdescriptiondisplay']; // itemdescriptiondisplay
					$param_setting1_array[16]=$formdata['ddlenableloadtransfer']; // enableloadtransfer
					$param_setting1_array[17]=$formdata['ddlenablescanneruse']; // enablescanneruse
					$param_setting1_array[18]=$formdata['chkenableeodaddchecks']; // enableeodaddchecks
					$param_setting1_array[19]=$formdata['chkenabledelayprint']; // enabledelayprint
					$param_setting1_array[20]=$formdata['chkenableaddcustomer']; // enableaddcustomer
					$param_setting1_array[21]='';// 
					$param_setting1_array[22]=$formdata['chkenforcecallsequence']; // enforcecallsequence
					$param_setting1_array[23]=$formdata['chkenablefoclimit']; // enablefoclimit
					$param_setting1_array[24]=$formdata['chkenablescancustomer']; // enablescancustomer
					$param_setting1_array[25]=$formdata['chkloadoutadjustments']; // loadoutadjustments
					$param_setting1_array[26]=$formdata['chkenableeodexpenses']; // enableeodexpenses
					$param_setting1_array[27]=$formdata['chkenablecashonlydiscount']; // enablecashonlydiscount
					$param_setting1_array[28]=$formdata['chkenablepostvoid']; // enablepostvoid
					$param_setting1_array[29]=$formdata['chkenableeodadjchecks']; // enableeodadjchecks
					$param_setting1_array[30]=$formdata['chkspe_inv_seq']; // transactionnoseq
					$param_setting1_array[31]=$formdata['chkenablefreereason']; // enablefreereason
					$param_setting1_array[32]=$this->currentUser->username;
					$param_setting1_array[33]= $formdata['ddlprint_alternate'];					//inventoryreportcontrol				
					$param_setting1_array[34]= $formdata['chkroute_weekday'];					//enablestartdayrtewkdayedit
					$param_setting1_array[35]= $formdata['chkstartdaydate'];					//enablestartdaydatetimeedit
					$param_setting1_array[36]= $formdata['chkrouteunloadvariance'];				//enablestartdaydatetimeedit
					$param_setting1_array[37]= $formdata['ddlsalesmantargetdays'];					//salesmantargetdays
					$param_setting1_array[38]= $formdata['chkvoidoverride'];					//voidoverride
		
					$result = $this->SFA_Comman->executequery('CALL sp_edit_organization_route_setting1(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',$param_setting1_array,'');
					
					
					$param_setting2_array[1]=$formdata['hdnid']; //routecode
					$param_setting2_array[2]=$formdata['ddlenablenosale']; // enablenosale
					$param_setting2_array[3]=$formdata['ddlcashbalance']; // cashbalance
					$param_setting2_array[4]=$formdata['ddlinventoryvariance']; // inventoryvariance
					$param_setting2_array[5]=$formdata['ddlinvenoversell']; // invenoversell
					$param_setting2_array[6]=$formdata['ddlenabledamagedtrxn']; // enabledamagedtrxn
					$param_setting2_array[7]=$formdata['ddldisplayinvsummary']; // displayinvsummary
					$param_setting2_array[8]=$formdata['ddlincludeloadrequest']; // includeloadrequest
					$param_setting2_array[9]=$formdata['ddlloadreqrolluporders']; // loadreqrolluporders
					$param_setting2_array[10]=$formdata['ddldepotprinter']; // depotprinter
					$param_setting2_array[11]=$formdata['ddllockstatus']; // lockstatus
					$param_setting2_array[12]=$formdata['ddlloadreqmethod']; // loadreqmethod
					$param_setting2_array[13]=$formdata['ddlrouteprinter']; // routeprinter
					$param_setting2_array[14]=$formdata['ddlroutetype']; // routetype
					$param_setting2_array[15]=$formdata['memo1']; // memo1
					$param_setting2_array[16]=$formdata['memo2']; // memo2
					$param_setting2_array[17]=$formdata['chkenablemiddaytelecom']; // enablemiddaytelecom
					$param_setting2_array[18]=$formdata['chkallowroutestartdayflag']; // allowroutestartdayflag
					$param_setting2_array[19]=$formdata['chkallowgctocash']; // allowgctocash
					$param_setting2_array[20]=$formdata['chkenablerouteweekday']; // enablerouteweekday
					$param_setting2_array[21]=$formdata['chkenabledraftcopy']; // enabledraftcopy
					$param_setting2_array[22]=$formdata['cdcvaliditydays']; // cdcvaliditydays
					$param_setting2_array[23]=$formdata['newcustomerseqnumber']; // newcustomerseqnumber
					$param_setting2_array[24]=$formdata['creditlimit']; // creditlimit
					$param_setting2_array[25]=$formdata['routebalance']; // routebalance
					$param_setting2_array[26]=$formdata['vehicleodometer']; // vehicleodometer
					$param_setting2_array[27]=$formdata['defaultdeliverydays']; // defaultdeliverydays
					$param_setting2_array[28]=$this->currentUser->username; // modified
					$param_setting2_array[29]=$formdata['txtallowradius'];	//allowedradius
					$param_setting2_array[30]=$formdata['txpdc_threshold'];		//pdcthreshold
					$param_setting2_array[31]=$formdata['defaultrequestdays']; // defaultrequestdays
					$param_setting2_array[32]= 0; // defaultweeksetting which we have remove
					$param_setting2_array[33]= $formdata['chkenableautopostingaccount']; // enableautopostingaccount
					
					if($formdata['chkenableautopostingaccount'] == 1) {
						$param_setting2_array[34]= $formdata['ddlcustomercode']; // customercode
					} else {
						$param_setting2_array[34]= 0; // customercode
					}
					$result = $this->SFA_Comman->executequery('CALL sp_edit_organization_route_setting2(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',$param_setting2_array,'');
					
					$param_report_array = array();
					$param_report_array[1] =  $formdata['ddldeposit_rpt'];	//reqeoddepositreport//
					$param_report_array[2] =  $formdata['ddldeodsales_rpt'];	//reqeodsalesreport//
					$param_report_array[3] =  $formdata['ddlrouteact_apt'];	//reqeodrteactivreport//
					$param_report_array[4] =  $formdata['ddlsettlements_rpt'];	//reqeodrtestlmtreport//
					$param_report_array[5] =  $formdata['ddlroutereview_rpt'];	//reqeodroutereviewrpt//
					$param_report_array[6] =  $formdata['ddlrtnexch_rpt'];	//reqeodrtnexchreport//
					$param_report_array[7] =  $formdata['ddlplacements_rpt'];	//reqeodplacementsrpt//
					$param_report_array[8] =  $formdata['ddlpricechng_rpt'];	//reqeodprcchgreport//
					$param_report_array[9] =  $formdata['ddlpromotion_rpt'];	//reqeodpromosreport//
					$param_report_array[10] =  $formdata['ddlnosale_rpt'];	//reqeodnosalereport//
					$param_report_array[11] =  $formdata['ddlnondell_rpt'];	//reqeodnondelreport//
					$param_report_array[12] =  $formdata['ddleodexcp_rpt'];	//reqeodexceptionrpt//
					$param_report_array[13] =  $formdata['ddlbalance_rpt'];	//reqeodunauthbalance//
					$param_report_array[14] =  $formdata['ddleodroa_rpt'];	//reqeodroasummary//
					$param_report_array[15] =  $formdata['ddlnonscanned_rpt'];	//reqeodnonscannedreport//
					$param_report_array[16] =  $formdata['ddlodomlog_rpt'];	//reqeododomlogreport//
					$param_report_array[17] =  $formdata['hdnid'];		//reqeododomlogreport//	
					$result = $this->SFA_Comman->executequery('CALL sp_edit_organization_route_report(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',$param_report_array ,'');	
					SFA_Message::setMsg($this->translate->_('Update Record'));
				}
				$this->_helper->redirector('route', 'route', 'organization');
		}		
		elseif(!empty($formdata['txtroute_name']) && !empty($formdata['txtaltcode'])  && !empty($formdata['txtcode']))
		{
			$param_array[1] = $formdata['txtroute_name'];		//routename
			$param_array[2] = $formdata['txtsecond_lang'];		//arbroutename
			$param_array[3] = $formdata['ddlsubarea'];		//subareacode
			$param_array[4] = $formdata['ddlsalesman'];		//salesmancode
			$param_array[5] = $this->currentUser->username;			//created
			$param_array[6] = $formdata[''];			//cdat
			$param_array[7] = $this->currentUser->username;	//modified
			$param_array[8] = $formdata[''];			//mdat
			$param_array[9] = $formdata['txtpwd1'];			//password1
			$param_array[10] = $formdata['txtpwd2'];		//password2
			$param_array[11] = $formdata['txtpwd3'];		//password3
			$param_array[12] = $formdata['txtpwd4'];		//password4
			$param_array[13] = $formdata['txtpwd5'];		//password5
			$param_array[14] = $formdata['txtdt_time'];		//passwordarray01
			$param_array[15] = $formdata['txtpr_chng'];		//passwordarray02
			$param_array[16] = $formdata['txtpromo_ovr'];		//passwordarray03
			$param_array[17] = $formdata['txtrt_setup'];		//passwordarray04
			$param_array[18] = $formdata['txttel_setup'];		//passwordarray05
			$param_array[19] = $formdata['txtload_adj'];		//passwordarray06
			$param_array[20] = $formdata['txtst_day'];		//passwordarray07
			$param_array[21] = $formdata['txtapp_exit'];		//passwordarray08
			$param_array[22] = $formdata['txtsettl'];		//passwordarray09
			$param_array[23] = $formdata['txtload_sec'];		//passwordarray10
			$param_array[24] = $formdata['txtunload'];		//passwordarray11
			$param_array[25] = $formdata['txtload_out'];		//passwordarray12
			$param_array[26] = $formdata['txtprnt_doc'];		//passwordarray13
			$param_array[27] = $formdata['txtload_trans'];		//passwordarray14
			$param_array[28] = $formdata['txtload_req'];		//passwordarray15
			$param_array[29] = $formdata['txtnewreq'];		//passwordarray16
			$param_array[30] = $formdata['chkdepot_route'];		//deliveryroute
			$param_array[31] = $formdata['chkallow_chng'];		//presalesorder
			$param_array[32] = $formdata['txtaltcode'];		//alternateroutecode			
			$param_array[33] = ($formdata['ddlbusi_type'] > 0) ? $formdata['ddlbusi_type'] : 'NULL';
			$param_array[34] = ($formdata['ddlvan'] > 0) ? $formdata['ddlvan'] : 'NULL' ; //vehicle no
			$param_array[35] = $formdata['ddlregion'];		//regionmstcode
			$param_array[36] = $formdata['ddlstatus']; //status
			$param_array[37] = $formdata['ddlcompany']; //cmpycode
			$param_array[38] = $formdata['chkallow_route_startday']; //cmpycode
			
			$result = $this->SFA_Comman->executequery('CALL sp_add_organization_route_addroute(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',$param_array,'');
			if($result[0][0]['result'] == 'duplicate'){
				SFA_Message::setErrorMsg($this->translate->_('Record(s) already Exist'));
			}else{
					$routecode = $result[0][0]['result'];
					$param_setting1_array[1]=$routecode; //routecode
					$param_setting1_array[2]=$formdata['ddlunloadoversellmessage']; // unloadoversellmessage
					$param_setting1_array[3]=$formdata['ddlinventoryvalueprint']; // inventoryvalueprint
					$param_setting1_array[4]=$formdata['ddlpromptodominput']; // promptodominput
					$param_setting1_array[5]=$formdata['ddlinventorycaseinput']; // inventorycaseinput
					$param_setting1_array[6]=$formdata['ddlloadreqreportformat']; // loadreqreportformat
					$param_setting1_array[7]=$formdata['ddlautocalculateloadin']; // autocalculateloadin
					$param_setting1_array[8]=$formdata['ddlrequireloadin']; // requireloadin
					$param_setting1_array[9]=$formdata['ddlloadsheetreport']; // loadsheetreport
					$param_setting1_array[10]=$formdata['ddlamountdecimaldigits']; // amountdecimaldigits
					$param_setting1_array[11]=$formdata['ddlitemcodedisplay']; // itemcodedisplay
					$param_setting1_array[12]=$formdata['ddlusealternatecodes']; // usealternatecodes
					$param_setting1_array[13]=$formdata['ddlrouteitemgrpcode']; // routeitemgrpcode
					$param_setting1_array[14]=$formdata['']; // 
					$param_setting1_array[15]=$formdata['ddlitemdescriptiondisplay']; // itemdescriptiondisplay
					$param_setting1_array[16]=$formdata['ddlenableloadtransfer']; // enableloadtransfer
					$param_setting1_array[17]=$formdata['ddlenablescanneruse']; // enablescanneruse
					$param_setting1_array[18]=$formdata['chkenableeodaddchecks']; // enableeodaddchecks
					$param_setting1_array[19]=$formdata['chkenabledelayprint']; // enabledelayprint
					$param_setting1_array[20]=$formdata['chkenableaddcustomer']; // enableaddcustomer
					$param_setting1_array[21]=$formdata['']; // 
					$param_setting1_array[22]=$formdata['chkenforcecallsequence']; // enforcecallsequence
					$param_setting1_array[23]=$formdata['chkenablefoclimit']; // enablefoclimit
					$param_setting1_array[24]=$formdata['chkenablescancustomer']; // enablescancustomer
					$param_setting1_array[25]=$formdata['chkloadoutadjustments']; // loadoutadjustments
					$param_setting1_array[26]=$formdata['chkenableeodexpenses']; // enableeodexpenses
					$param_setting1_array[27]=$formdata['chkenablecashonlydiscount']; // enablecashonlydiscount
					$param_setting1_array[28]=$formdata['chkenablepostvoid']; // enablepostvoid
					$param_setting1_array[29]=$formdata['chkenableeodadjchecks']; // enableeodadjchecks
					$param_setting1_array[30]=$formdata['chkspe_inv_seq']; // transactionnoseq
					$param_setting1_array[31]=$formdata['chkenablefreereason']; // enablefreereason					
					$param_setting1_array[32]=$this->currentUser->username;
					$param_setting1_array[33]= $formdata['ddlalt_itm_code'];						//inventoryreportcontrol
					$param_setting1_array[34]= $formdata['chkroute_weekday'];					//enablestartdayrtewkdayedit
					$param_setting1_array[35]= $formdata['chkstartdaydate'];					//enablestartdaydatetimeedit
					$param_setting1_array[36]= $formdata['chkrouteunloadvariance'];					//enablestartdaydatetimeedit
					$param_setting1_array[37]= $formdata['ddlsalesmantargetdays'];					//salesmantargetdays
					$param_setting1_array[38]= $formdata['chkvoidoverride'];					//voidoverride
					
					$result = "";
					$result = $this->SFA_Comman->executequery('CALL sp_edit_organization_route_setting1(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',$param_setting1_array,'');
					unset($param_setting1_array);
					
					$param_setting2_array[1]=$routecode; //routecode
					$param_setting2_array[2]=$formdata['ddlenablenosale']; // enablenosale
					$param_setting2_array[3]=$formdata['ddlcashbalance']; // cashbalance
					$param_setting2_array[4]=$formdata['ddlinventoryvariance']; // inventoryvariance
					$param_setting2_array[5]=$formdata['ddlinvenoversell']; // invenoversell
					$param_setting2_array[6]=$formdata['ddlenabledamagedtrxn']; // enabledamagedtrxn
					$param_setting2_array[7]=$formdata['ddldisplayinvsummary']; // displayinvsummary
					$param_setting2_array[8]=$formdata['ddlincludeloadrequest']; // includeloadrequest
					$param_setting2_array[9]=$formdata['ddlloadreqrolluporders']; // loadreqrolluporders
					$param_setting2_array[10]=$formdata['ddldepotprinter']; // depotprinter
					$param_setting2_array[11]=$formdata['ddllockstatus']; // lockstatus
					$param_setting2_array[12]=$formdata['ddlloadreqmethod']; // loadreqmethod
					$param_setting2_array[13]=$formdata['ddlrouteprinter']; // routeprinter
					$param_setting2_array[14]=$formdata['ddlroutetype']; // routetype
					$param_setting2_array[15]=$formdata['memo1']; // memo1
					$param_setting2_array[16]=$formdata['memo2']; // memo2
					$param_setting2_array[17]=$formdata['chkenablemiddaytelecom']; // enablemiddaytelecom
					$param_setting2_array[18]=$formdata['chkallowroutestartdayflag']; // allowroutestartdayflag
					$param_setting2_array[19]=$formdata['chkallowgctocash']; // allowgctocash
					$param_setting2_array[20]=$formdata['chkenablerouteweekday']; // enablerouteweekday
					$param_setting2_array[21]=$formdata['chkenabledraftcopy']; // enabledraftcopy
					$param_setting2_array[22]=$formdata['cdcvaliditydays']; // cdcvaliditydays
					$param_setting2_array[23]=$formdata['newcustomerseqnumber']; // newcustomerseqnumber
					$param_setting2_array[24]=$formdata['creditlimit']; // creditlimit
					$param_setting2_array[25]=$formdata['routebalance']; // routebalance
					$param_setting2_array[26]=$formdata['vehicleodometer']; // vehicleodometer
					$param_setting2_array[27]=$formdata['defaultdeliverydays']; // defaultdeliverydays
					$param_setting2_array[28]=$this->currentUser->username; // modified
					$param_setting2_array[29]=$formdata['txtallowradius'];	//allowedradius
					$param_setting2_array[30]=$formdata['txpdc_threshold'];		//pdcthreshold
					$param_setting2_array[31]=$formdata['defaultrequestdays']; // defaultrequestdays
					$param_setting2_array[32]= 0; // defaultweeksetting which we have remove
					$param_setting2_array[33]= $formdata['chkenableautopostingaccount']; // enableautopostingaccount
					if($formdata['chkenableautopostingaccount'] == 1) {
						$param_setting2_array[34]= $formdata['ddlcustomercode']; // customercode
					} else {
						$param_setting2_array[34]= 0; // customercode
					}
					
					$result = "";
					$result = $this->SFA_Comman->executequery('CALL sp_edit_organization_route_setting2(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',$param_setting2_array,'');
					unset($param_setting2_array);
					
					$param_report_array = array();
					$param_report_array[1] =  $formdata['ddldeposit_rpt'];	//reqeoddepositreport//
					$param_report_array[2] =  $formdata['ddldeodsales_rpt'];	//reqeodsalesreport//
					$param_report_array[3] =  $formdata['ddlrouteact_apt'];	//reqeodrteactivreport//
					$param_report_array[4] =  $formdata['ddlsettlements_rpt'];	//reqeodrtestlmtreport//
					$param_report_array[5] =  $formdata['ddlroutereview_rpt'];	//reqeodroutereviewrpt//
					$param_report_array[6] =  $formdata['ddlrtnexch_rpt'];	//reqeodrtnexchreport//
					$param_report_array[7] =  $formdata['ddlplacements_rpt'];	//reqeodplacementsrpt//
					$param_report_array[8] =  $formdata['ddlpricechng_rpt'];	//reqeodprcchgreport//
					$param_report_array[9] =  $formdata['ddlpromotion_rpt'];	//reqeodpromosreport//
					$param_report_array[10] =  $formdata['ddlnosale_rpt'];	//reqeodnosalereport//
					$param_report_array[11] =  $formdata['ddlnondell_rpt'];	//reqeodnondelreport//
					$param_report_array[12] =  $formdata['ddleodexcp_rpt'];	//reqeodexceptionrpt//
					$param_report_array[13] =  $formdata['ddlbalance_rpt'];	//reqeodunauthbalance//
					$param_report_array[14] =  $formdata['ddleodroa_rpt'];	//reqeodroasummary//
					$param_report_array[15] =  $formdata['ddlnonscanned_rpt'];	//reqeodnonscannedreport//
					$param_report_array[16] =  $formdata['ddlodomlog_rpt'];	//reqeododomlogreport//
					$param_report_array[17] =  $routecode;		//reqeododomlogreport//			
					$result = $this->SFA_Comman->executequery('CALL sp_edit_organization_route_report(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',$param_report_array ,'');
					SFA_Message::setMsg($this->translate->_('New Record'));
				}
				$this->_helper->redirector('route', 'route', 'organization');
		}
		elseif($params['id'] > 0)
		{
			//this for edit.
			$param_array[1] = $params['id'];
			$result = $this->SFA_Comman->executequery('CALL sp_get_organization_route_addroute(?)',$param_array,'');
			$res = $result[0][0];			
			
			$viewformdata['txtcode'] 		= $params['id'];		//routecode
			$viewformdata['txtroute_name'] 	= $res['routename'];		//routename
			$viewformdata['txtsecond_lang'] = $res['arbroutename'];		//arbroutename
			$viewformdata['subarea'] 		= $res['subareacode'];		//subareacode
			$viewformdata['salesman'] 		= $res['salesmancode'];		//salesmancode
			$viewformdata['txtpwd1'] 		= $res['password1'];			//password1
			$viewformdata['txtpwd2'] 		= $res['password2'];		//password2
			$viewformdata['txtpwd3']		= $res['password3'];		//password3
			$viewformdata['txtpwd4'] 		= $res['password4'];		//password4
			$viewformdata['txtpwd5'] 		= $res['password5'];		//password5			
			$viewformdata['txtdt_time'] 	= $res['passwordarray01'];		//passwordarray01
			$viewformdata['txtpr_chng'] 	= $res['passwordarray02'];		//passwordarray02
			$viewformdata['txtpromo_ovr']	= $res['passwordarray03'];		//passwordarray03
			$viewformdata['txtrt_setup'] 	= $res['passwordarray04'];		//passwordarray04
			$viewformdata['txttel_setup']	= $res['passwordarray05'];		//passwordarray05
			$viewformdata['txtload_adj'] 	= $res['passwordarray06'];		//passwordarray06
			$viewformdata['txtst_day'] 		= $res['passwordarray07'];		//passwordarray07
			$viewformdata['txtapp_exit'] 	= $res['passwordarray08'];		//passwordarray08
			$viewformdata['txtsettl'] 		= $res['passwordarray09'];		//passwordarray09
			$viewformdata['txtload_sec'] 	= $res['passwordarray10'];		//passwordarray10
			$viewformdata['txtunload'] 		= $res['passwordarray11'];		//passwordarray11
			$viewformdata['txtload_out'] 	= $res['passwordarray12'];		//passwordarray12
			$viewformdata['txtprnt_doc'] 	= $res['passwordarray13'];		//passwordarray13
			$viewformdata['txtload_trans'] 	= $res['passwordarray14'];		//passwordarray14
			$viewformdata['txtload_req'] 	= $res['passwordarray15'];		//passwordarray15
			$viewformdata['txtnewreq'] 		= $res['passwordarray16'];		//passwordarray16
			$viewformdata['depotroute'] 	= $res['depotrouteflag'];		//deliveryroute
			$viewformdata['pre_sales_order']= $res['presalesorder'];		//presalesorder
			$viewformdata['txtaltcode'] 	= $res['alternateroutecode'];		//alternateroutecode
			$viewformdata['routecat'] 		= $res['routecatcode'];		// routecatcode
			$viewformdata['van'] 			= $res['vehiclenumber'];		// vehiclenumber
			$viewformdata['company_code'] 	= $res['cmpycode'];		// company Code
			$viewformdata['active_status']	= $res['ddlstatus'];	// activestatus
			$viewformdata['region_code'] 	= $res['regionmstcode'];	// activestatus
			$viewformdata['ddldeposit_rpt']	= $res['reqeoddepositreport'];
			$viewformdata['ddldeodsales_rpt']	= $res['reqeodsalesreport'];
			$viewformdata['ddlrouteact_apt']	= $res['reqeodrteactivreport'];
			$viewformdata['ddlsettlements_rpt']	= $res['reqeodrtestlmtreport'];
			$viewformdata['ddlroutereview_rpt']	= $res['reqeodroutereviewrpt'];
			$viewformdata['ddlrtnexch_rpt']		= $res['reqeodrtnexchreport'];
			$viewformdata['ddlplacements_rpt']	= $res['reqeodplacementsrpt'];
			$viewformdata['ddlpricechng_rpt']	= $res['reqeodprcchgreport'];
			$viewformdata['ddlpromotion_rpt']	= $res['reqeodpromosreport'];
			$viewformdata['ddlnosale_rpt']		= $res['reqeodnosalereport'];
			$viewformdata['ddlnondell_rpt']		= $res['reqeodnondelreport'];
			$viewformdata['ddleodexcp_rpt']		= $res['reqeodexceptionrpt'];
			$viewformdata['ddlbalance_rpt']		= $res['reqeodunauthbalance'];
			$viewformdata['ddleodroa_rpt']		= $res['reqeodroasummary'];
			$viewformdata['ddlnonscanned_rpt']	= $res['reqeodnonscannedreport'];
			$viewformdata['ddlodomlog_rpt']		= $res['reqeododomlogreport'];
			$viewformdata['ddlroutetype']		= $res['routetype'];
			$viewformdata['ddlvan'] 			= $res['vehiclenumber'];
			$viewformdata['ddlbusi_type']		= $res['routecatcode'];
			$viewformdata['createddate']		= date("d-m-Y",strtotime($res['cdat']));
			$viewformdata['chkallow_route_startday'] = $res['allowroutestartdayflag'];
			$this->view->formdata 	= $res;
			$this->view->new_seq_number = $res['newcustomerseqnumber'];
			
			$this->view->formdata = $viewformdata;
			if($params['tmpl'] == 1){
				$result = $this->SFA_Comman->executequery('CALL sp_getcombobox_organization_route_addroute()','','');
				$this->view->formdata['txtcode'] = ($result[0][0]['Auto_increment'] == '') ? '1' : $result[0][0]['Auto_increment'];				
			}
			$this->view->region_data = $result[1];
			$this->view->subarea_data = $result[2];
			$this->view->salesman_data = $result[3];
			$this->view->com_data = $result[4];
			$this->view->van_data = $result[5];
			$this->view->route_data = $result[6];
			$this->view->itemgroupcode =  $result[7];
			$this->view->routetemplate =  $result[8];
			$this->view->currency_info =  $result[9];
			$this->view->routecode = $params['id'];
			//$this->view->amountdecimaldigits = $result[10][0]['last_currcode'];
			$this->view->customerinfo = $result[10];
			
			/* For Setting 1 */
			$result_setting1 = $this->SFA_Comman->executequery('CALL sp_get_organization_route_setting1(?)',$param_array,'');
			$this->view->formdata_setting1 = $result_setting1[0][0];
			$this->view->itemgroupcode = $result_setting1[1];
			
			
			/* For Setting 2 */
			$result_setting2 = $this->SFA_Comman->executequery('CALL sp_get_organization_route_setting2(?)',$param_array,'');
			$this->view->formdata_setting2 						= $result_setting2[0][0];
			
			$result_report 			= $this->SFA_Comman->executequery('CALL sp_get_organization_route_report(?)',$param_array,'');
			$res['ddldeposit_rpt']	= $result_report[0][0]['reqeoddepositreport'];
			$res['ddldeodsales_rpt']	= $result_report[0][0]['reqeodsalesreport'];
			$res['ddlrouteact_apt']	= $result_report[0][0]['reqeodrteactivreport'];
			$res['ddlsettlements_rpt']	= $result_report[0][0]['reqeodrtestlmtreport'];
			$res['ddlroutereview_rpt']	= $result_report[0][0]['reqeodroutereviewrpt'];
			$res['ddlrtnexch_rpt']	= $result_report[0][0]['reqeodrtnexchreport'];
			$res['ddlplacements_rpt']	= $result_report[0][0]['reqeodplacementsrpt'];
			$res['ddlpricechng_rpt']	= $result_report[0][0]['reqeodprcchgreport'];
			$res['ddlpromotion_rpt']	= $result_report[0][0]['reqeodpromosreport'];
			$res['ddlnosale_rpt']	= $result_report[0][0]['reqeodnosalereport'];
			$res['ddlnondell_rpt']	= $result_report[0][0]['reqeodnondelreport'];
			$res['ddleodexcp_rpt']	= $result_report[0][0]['reqeodexceptionrpt'];
			$res['ddlbalance_rpt']	= $result_report[0][0]['reqeodunauthbalance'];
			$res['ddleodroa_rpt']	= $result_report[0][0]['reqeodroasummary'];
			$res['ddlnonscanned_rpt']	= $result_report[0][0]['reqeodnonscannedreport'];
			$res['ddlodomlog_rpt']	= $result_report[0][0]['reqeododomlogreport'];			
			$this->view->formdata_report 	= $res;
			
			if($params['edit'] != 'yes') {
				$result = $this->SFA_Comman->executequery('CALL sp_get_table_last_id("?")','routemaster','');
				$this->view->formdata['txtcode'] = ($result[0][0]['Auto_increment'] == '') ? '1' : $result[0][0]['Auto_increment'];
				$this->view->route_tmplval = $params['id'];
			}
		}
		else
		{
			$result = $this->SFA_Comman->executequery('CALL sp_getcombobox_organization_route_addroute()','','');
			$this->view->formdata['txtcode'] = ($result[0][0]['Auto_increment'] == '') ? '1' : $result[0][0]['Auto_increment'];
			$this->view->region_data = $result[1];
			$this->view->subarea_data = $result[2];
			$this->view->salesman_data = $result[3];
			$this->view->com_data = $result[4];
			$this->view->van_data = $result[5];
			$this->view->route_data = $result[6];
			$this->view->itemgroupcode = $result[7];
			$this->view->routetemplate = $result[8];
			$this->view->currency_info =  $result[9];
			$this->view->amountdecimaldigits = $result[10][0]['last_currcode'];
			$this->view->customerinfo = $result[11];
			$this->view->new_seq_number = $result[0][0]['Auto_increment'].'00001';			
		}
    }
    
    /**
    * @name       setting1values
    * @since      14-04-2012
    * @version    Release: 1
    * @author     Jinal <jinal@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This functiion is ussed to define setting1 tab cobmo values.
    */
    public function setting1values(){
		$inv_print_data = array();
		$inv_print_data = array(array('id'=>0,'val'=>'Do not Print Inventory Values on Load or Reload Reports'),
				   array('id'=>1,'val'=>'Include Inventory Values on Detail Lines and Report Totals'));
		$this->view->inv_print_data = $inv_print_data;
	
		$odometer = array();
		$odometer = array(array('id'=>0,'val'=>'No Prompt'),
				array('id'=>1,'val'=>'Prompt at Start/End Day'),
				array('id'=>2,'val'=>'Prompt at Start/End Day + Each Visit'));
		$this->view->odometer	= $odometer;
	
		$inv_case_input = array();
		$inv_case_input = array(array('id'=>0,'val'=>'Inventory By Units Only'),
								array('id'=>1,'val'=>'Inventory By Cases/Units'));
		$this->view->inv_case_input = $inv_case_input;
	
		$load_req_report = array();
		$load_req_report = array(array('id'=>0,'val'=>'Disable Load Request'),
				array('id'=>1,'val'=>'Request Qty Only Report'),
				array('id'=>2,'val'=>'Add On Qty Report'));
		$this->view->load_req_report = $load_req_report;
	
	
		$auto_calculated = array();
		$auto_calculated = array(array('id'=>0,'val'=>'Disable'),
				array('id'=>1,'val'=>'Calc Unload Qtys; Disable Chgs'),
				array('id'=>2,'val'=>'Calc Unload Qtys; Enable Chgs'),
				array('id'=>3,'val'=>'Calc End Inv. Qtys; Disable Chgs'),
				array('id'=>4,'val'=>'Calc End Inv. Qtys; Enable Chgs'));
		$this->view->auto_calculated = $auto_calculated;
	
		$req_load_in = array();
		$req_load_in = array(array('id'=>0,'val'=>'Disable'),
				array('id'=>1,'val'=>'Prompt Before Settlement'),
				array('id'=>2,'val'=>'Require Before Settlement'));
		$this->view->req_load_in = $req_load_in;
	
		$hhc_item_data = array();
		$hhc_item_data = array(array('id'=>0,'val'=>'Display ActualItemCode'),
				array('id'=>1,'val'=>'Display AlternateCode'));
		$this->view->hhc_item_data = $hhc_item_data;
	
	
		$print_alternate = array();
		$print_alternate = array(array('id'=>0,'val'=>'Disabled'),
				array('id'=>1,'val'=>'Print AlternateCode And ActualItemCode'),
				array('id'=>2,'val'=>'Print AlternateCode Only'));
		$this->view->print_alternate = $print_alternate;
	
		$display_item_desc = array();
		$display_item_desc = array(array('id'=>0,'val'=>'Display Item Description'),
				array('id'=>1,'val'=>'Display Item Long Description'));
		$this->view->display_item_desc = $display_item_desc;
	
	
		$use_alt_code = array();
		$use_alt_code = array(array('id'=>0,'val'=>'Display ActualCustomerCode'),
				array('id'=>1,'val'=>'Display AlternateCode'));
		$this->view->use_alt_code = $use_alt_code;
	
		$ena_load_trans = array();
		$ena_load_trans = array(array('id'=>0,'val'=>'Disable'),
				array('id'=>1,'val'=>'Transfer In Only'),
				array('id'=>2,'val'=>'Transfer Out Only'),
				array('id'=>3,'val'=>'Damage Only'),
				array('id'=>4,'val'=>'Transfer In And Transfer Out'),
				array('id'=>5,'val'=>'Transfer In And Damage'),
				array('id'=>6,'val'=>'Transfer Out And Damage'),
				array('id'=>7,'val'=>'Enable All'));
		$this->view->ena_load_trans 	= $ena_load_trans;
	
		$ena_scan_use = array();
		$ena_scan_use = array(array('id'=>0,'val'=>'Manual'),
				array('id'=>1,'val'=>'Manual/Scanning'),
				array('id'=>2,'val'=>'Scanning'));
		$this->view->ena_scan_use = $ena_scan_use;
    }

    /**
    * @name       setting2values
    * @since      14-04-2012
    * @version    Release: 1
    * @author     Jinal <jinal@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This functiion is ussed to define setting2 tab cobmo values.
    */
    public function setting2values(){

		$ena_no_sale = array();
		$ena_no_sale = array(array('id'=>0,'val'=>'Disable'),
				array('id'=>1,'val'=>'Enable No Sale at Point of Sale'),
				array('id'=>2,'val'=>'Enable No Sale at Point of Sale with Printing of Unserviced Customers'));
		$this->view->ena_no_sale = $ena_no_sale;
	
		$cash_bal = array();
		$cash_bal = array(array('id'=>0,'val'=>'Balance Should be 0.00'),
				array('id'=>1,'val'=>'Allow Over/Short Deposit Report'));
		$this->view->cash_bal = $cash_bal;
	
		$inv_vari = array();
		$inv_vari = array(array('id'=>0,'val'=>'Disable'),
				array('id'=>1,'val'=>'Variance not Due at End Day'),
				array('id'=>2,'val'=>'Variance Due at End Day'),
				array('id'=>3,'val'=>'Shortages Only Due at End Day'));
		$this->view->inv_vari = $inv_vari;
	
		$inv_over_sell = array();
		$inv_over_sell = array(array('id'=>0,'val'=>'Disable'),
				array('id'=>1,'val'=>'Enable Over Sell of Inventory'));
		$this->view->inv_over_sell = $inv_over_sell;
	
		$ena_dmg_trxn = array();
		$ena_dmg_trxn = array(array('id'=>0,'val'=>'Disable'),
				array('id'=>1,'val'=>'Allow unloading damaged return in the middle of the day.'));
		$this->view->ena_dmg_trxn = $ena_dmg_trxn;
	
		$disp_inv_summ = array();
		$disp_inv_summ = array(array('id'=>0,'val'=>'Disable'),
				array('id'=>1,'val'=>'Display after invoice exit'),
				array('id'=>2,'val'=>'Display after each transaction and invoice exit'));
		$this->view->disp_inv_summ = $disp_inv_summ;
	
		$inc_load_req = array();
		$inc_load_req = array(array('id'=>0,'val'=>'Disable'),				
				array('id'=>1,'val'=>'Enable (Include Load Request)'));
		$this->view->inc_load_req	= $inc_load_req;
	
		$load_req_roll_up = array();
		$load_req_roll_up = array(array('id'=>0,'val'=>'Add Load Request'),
								  array('id'=>1,'val'=>'Suggested Load Request from Average Sales'),
								  array('id'=>2,'val'=>'Roll up Orders for Next Load Request'));
		$this->view->load_req_roll_up	= $load_req_roll_up;
	
	
		$depot_print = array();
		$depot_print =array(array('id'=>0,'val'=>'Disable'));
		$this->view->depot_print = $depot_print;
	
		$load_req_method = array();
		$load_req_method = array(array('id'=>0,'val'=>'View only'),
				array('id'=>1,'val'=>'Editable'));
		$this->view->load_req_method = $load_req_method;
	
		$route_print = array();
		$route_print =array(array('id'=>1,'val'=>'Zebra RW420'),
							array('id'=>2,'val'=>'Intermec PB42'),
							array('id'=>3,'val'=>'Intermec 6822'));
		$this->view->route_print = $route_print;
    }

	/**
    * @name       routetmplAction
    * @since      26-07-2011
    * @version    Release: 1
    * @author     HD <hiren.d@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This action is use for the display route template details
    */
	public function routetmplAction()
    {	
		
		$this->view->params = $params = $this->getRequest()->getParams();
		$this->view->formdata = $formdata = $this->_request->getPost();
		
		if($formdata["hdDelete"]==1)
		{
			$ids = implode(',',$formdata['chk']);
			$param_array 	= array();
			$param_array[1]	= $ids;
			$param_array[2]	= $this->currentUser->username;
			
			$result 	= $this->SFA_Comman->executequery('CALL sp_delete_organization_route_route(?,?)',$param_array,'');
			
			if($result[0][0]['deleted_id'] =='')
			{
				$ids		= explode(',',$ids);
				$checked 	= $ids;
				
				SFA_Message::setErrorMsg($this->translate->_('Record Already In use'));
			}
			else
			{
				$deleted_id = explode(',',$result[0][0]['deleted_id']);
				$ids		= explode(',',$ids);
				$checked 	= array_diff($ids,$deleted_id);
				
				if(count($ids) != count($deleted_id)){
					SFA_Message::setErrorMsg($this->translate->_('Record Already In use'));
				}
				
				SFA_Message::setMsg($this->translate->_('Delete Record'));
			}
		}
		
		
		$cols_array 	= array('route.routecode','route.templatename');
		$columns_show 	=  array($this->translate->_('Route Code'),$this->translate->_('Route Template Name'));				
	
		  
		// prepare the configuration for grid
		$pagingparams = array(
				 "show_grid_heading" => true,
				 "grid_heading_message" => $this->translate->_('Overview'),
				 "pagename" => $this->translate->_('Route Template'),
				 "show_records_per_page" => isset($formdata["show_records_per_page"])?$formdata["show_records_per_page"]:10,
				 "show_searchbox" => true,
				 "searchbox_value" => isset($formdata["grid_search_text"])?$formdata["grid_search_text"]:"",
				 "show_selectbox" => true,
				 "selected_list" => $checked,
				 "show_editlink" => true,
				 "show_deletelink" => false,
				 "show_deleteall" => false,
				 "primaryid" => "routecode",				 
				 "editlink" => array("/organization/route/addroutetmpl/id/#pattern#/edit/yes/","#pattern#"),
				 "nodata_message" => $this->translate->_('No Record(s) Found'),
				 "fetch_columns_inquery" => $cols_array,
				 "show_columns" => $columns_show
				 
				 );
        if(!$this->checkaccess("update"))
        {
            $pagingparams["show_editlink"] = false;
        }
		// create grid class object
		$pagingshow = new SFA_Paging($pagingparams);
		
		// call common function of grid class
		$get_return_vals = $pagingshow->commnfunc();
		
		//print_r($get_return_vals['where_condition']);
		
		// call the stored procedure for fetch the data
		$param_array 	= array();
		$param_array[1] = '1';
		$param_array[2] = '';
		$param_array[3] = $get_return_vals['order_columns_name'];
		$param_array[4] = $get_return_vals['order_type'];
		$param_array[5] = $get_return_vals['offset'];
		$param_array[6] = (int)$get_return_vals['show_records_per_page'];
		$param_array[7] = implode(", ",$pagingparams["fetch_columns_inquery"]);
		$param_array[8] = strlen($get_return_vals['where_condition'])>0?$get_return_vals['where_condition']:'';
		
		$downloadCSV = (isset($formdata['downloadcsv'])) ? $formdata['downloadcsv'] : $params['downloadcsv'];
    
    // Hiren Dave on 19 Nov, 2012 - START - to check if need to print data
	$printData = (isset($formdata['printData'])) ? $formdata['printData'] : $params['printData'];
		// called stored procedure for counter
		$result = $this->SFA_Comman->executequery('CALL sp_get_organization_route_routetmplinfo(?,?,?,?,?,?,?,?)',$param_array,'',$downloadCSV,$printData,2,$pagingparams);
		
		$data_arr["count"] 	= $result[0][0]['counter'];
		$data_arr["data"][0] 	= $result[1];
		
		// pass the data in summary_showdatagrid() function & create a final variable for view
		$this->view->pagegridshow =  $pagingshow->summary_showdatagrid($data_arr);
		
		$this->view->addScriptPath(APPLICATION_PATH."/theme/default/datagrid/");
    }
    /**
    * @name       addroutetmpl
    * @since      21-12-2011
    * @version    Release: 1
    * @author     HD <hiren.d@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This action is use for the add route template details
    */
    public function addroutetmplAction()
    {
		$this->view->params = $params = $this->getRequest()->getParams();
        $this->view->formdata = $formdata = $this->_request->getPost();

		$this->view->routecode 		= $params['id'];		
		$this->view->css 			= $this->translate->_('CSS');
		
		$print_option = array();
		$print_option[0]['val']	= 'Do Not Print';
		$print_option[1]['val']	= 'Optional, Prompt User';
		$print_option[2]['val']	= 'Force Print';
		$print_option[0]['id']	= '0';
		$print_option[1]['id']	= '1';
		$print_option[2]['id']	= '2';
		$this->view->print_option = $print_option;		
		
		//called function for setting1 values
		$this->setting1values();
		//call function for setting2 values
		$this->setting2values();
	
		//reports dropdown fill
		$print_option = array();
		$print_option[0]['val']	= 'Disable Print';
		$print_option[1]['val']	= 'Optional (User Choice)';
		$print_option[2]['val']	= 'Force Print';
		$print_option[0]['id']	= '0';
		$print_option[1]['id']	= '1';
		$print_option[2]['id']	= '2';
		$this->view->print_option = $print_option;
		
		$route_type = array();
		$route_type = array(array('id'=>0,'val'=>'0 - Enable Order And Sales Process (History & Goal For Order Only)'),
				array('id'=>1,'val'=>'1 - Enable Order And Sales Process (History & Goal For Sales Only)'),
				array('id'=>2,'val'=>'2 - Enable Order Process Only'),
				array('id'=>3,'val'=>'3 - Enable Sales Process Only'));
		$this->view->route_type	= $route_type;	
	
		if(count($formdata) > 0) {
			
			// Add General Information
			$param_array 	= array();
			$param_array[1]	= $this->currentUser->username;						//created & Modified
			$param_array[2]	= $formdata['txtpwd1'];								//password1
			$param_array[3]	= $formdata['txtpwd2'];								//password2
			$param_array[4]	= $formdata['txtpwd3'];								//password3
			$param_array[5]	= $formdata['txtpwd4'];								//password4
			$param_array[6]	= $formdata['txtpwd5'];								//password5
			$param_array[7] = $formdata['txtdt_time'];							//passwordarray01
			$param_array[8] = $formdata['txtpr_chng'];				//passwordarray02
			$param_array[9] = $formdata['txtpromo_ovr'];		//passwordarray03
			$param_array[10] = $formdata['txtrt_setup'];		//passwordarray04
			$param_array[11] = $formdata['txttel_setup'];		//passwordarray05
			$param_array[12] = $formdata['txtload_adj'];		//passwordarray06
			$param_array[13] = $formdata['txtst_day'];		//passwordarray07
			$param_array[14] = $formdata['txtapp_exit'];		//passwordarray08
			$param_array[15] = $formdata['txtsettl'];		//passwordarray09
			$param_array[16] = $formdata['txtload_sec'];		//passwordarray10
			$param_array[17] = $formdata['txtunload'];		//passwordarray11
			$param_array[18] = $formdata['txtload_out'];		//passwordarray12
			$param_array[19] = $formdata['txtprnt_doc'];		//passwordarray13
			$param_array[20] = $formdata['txtload_trans'];		//passwordarray14
			$param_array[21] = $formdata['txtload_req'];		//passwordarray15
			$param_array[22] = $formdata['txtnewreq'];		//passwordarray16			
			$param_array[23] =($formdata['ddlbusi_type'] > 0) ? $formdata['ddlbusi_type'] : 'NULL';
			$param_array[24]= $formdata['ddlstatus'];							//activestatus
			$param_array[25]= $formdata['chkdepot_route'];						//depotroute
			$param_array[26]= $formdata['chkallow_chng'];						//presalesorder
			$param_array[27]= $formdata['txttemplatename'];						//presalesorder
			
			
			if($formdata['hdnid'] > 0){
				$param_array[28]	= $formdata['hdnid'];
				$lastid				= $this->SFA_Comman->executequery('CALL sp_edit_organization_route_addroute_template(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',$param_array,'');
				SFA_Message::setMsg($this->translate->_('Update Record'));
				$lastid = $lastid[0][0]['result'];
			}
			else{				
				$lastid				= $this->SFA_Comman->executequery('CALL sp_add_organization_route_addroute_template(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',$param_array,'');
				SFA_Message::setMsg($this->translate->_('New Record'));
				$lastid = $lastid[0][0]['result'];
			}
			
			if($lastid > 0)
			{
				$param_setting1_array[1]=$lastid; //routecode
				$param_setting1_array[2]=$formdata['ddlunloadoversellmessage']; // unloadoversellmessage
				$param_setting1_array[3]=$formdata['ddlinventoryvalueprint']; // inventoryvalueprint
				$param_setting1_array[4]=$formdata['ddlpromptodominput']; // promptodominput
				$param_setting1_array[5]=$formdata['ddlinventorycaseinput']; // inventorycaseinput
				$param_setting1_array[6]=$formdata['ddlloadreqreportformat']; // loadreqreportformat
				$param_setting1_array[7]=$formdata['ddlautocalculateloadin']; // autocalculateloadin
				$param_setting1_array[8]=$formdata['ddlrequireloadin']; // requireloadin
				$param_setting1_array[9]=$formdata['ddlloadsheetreport']; // loadsheetreport
				$param_setting1_array[10]=$formdata['ddlamountdecimaldigits']; // amountdecimaldigits
				$param_setting1_array[11]=$formdata['ddlitemcodedisplay']; // itemcodedisplay
				$param_setting1_array[12]=$formdata['ddlusealternatecodes']; // usealternatecodes
				$param_setting1_array[13]=$formdata['ddlrouteitemgrpcode']; // routeitemgrpcode
				$param_setting1_array[14]=''; // 
				$param_setting1_array[15]=$formdata['ddlitemdescriptiondisplay']; // itemdescriptiondisplay
				$param_setting1_array[16]=$formdata['ddlenableloadtransfer']; // enableloadtransfer
				$param_setting1_array[17]=$formdata['ddlenablescanneruse']; // enablescanneruse
				$param_setting1_array[18]=$formdata['chkenableeodaddchecks']; // enableeodaddchecks
				$param_setting1_array[19]=$formdata['chkenabledelayprint']; // enabledelayprint
				$param_setting1_array[20]=$formdata['chkenableaddcustomer']; // enableaddcustomer
				$param_setting1_array[21]='';// 
				$param_setting1_array[22]=$formdata['chkenforcecallsequence']; // enforcecallsequence
				$param_setting1_array[23]=$formdata['chkenablefoclimit']; // enablefoclimit
				$param_setting1_array[24]=$formdata['chkenablescancustomer']; // enablescancustomer
				$param_setting1_array[25]=$formdata['chkloadoutadjustments']; // loadoutadjustments
				$param_setting1_array[26]=$formdata['chkenableeodexpenses']; // enableeodexpenses
				$param_setting1_array[27]=$formdata['chkenablecashonlydiscount']; // enablecashonlydiscount
				$param_setting1_array[28]=$formdata['chkenablepostvoid']; // enablepostvoid
				$param_setting1_array[29]=$formdata['chkenableeodadjchecks']; // enableeodadjchecks
				$param_setting1_array[30]=$formdata['chkspe_inv_seq']; // transactionnoseq
				$param_setting1_array[31]=$formdata['chkenablefreereason']; // enablefreereason
				$param_setting1_array[32]=$this->currentUser->username;
				$param_setting1_array[33]= $formdata['ddlprint_alternate'];						//inventoryreportcontrol				
				$param_setting1_array[34]= $formdata['chkroute_weekday'];					//enablestartdayrtewkdayedit
				$param_setting1_array[35]= $formdata['chkstartdaydate'];					//enablestartdaydatetimeedit
				$param_setting1_array[36]= $formdata['chkrouteunloadvariance'];					//enablestartdaydatetimeedit
				$param_setting1_array[37]= $formdata['ddlsalesmantargetdays'];					//salesmantargetdays
				$param_setting1_array[38]= $formdata['chkvoidoverride'];					//voidoverride
	
				$result = $this->SFA_Comman->executequery('CALL sp_edit_organization_route_setting1(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',$param_setting1_array,'');
				
				
				$param_setting2_array[1]=$lastid; //routecode
				$param_setting2_array[2]=$formdata['ddlenablenosale']; // enablenosale
				$param_setting2_array[3]=$formdata['ddlcashbalance']; // cashbalance
				$param_setting2_array[4]=$formdata['ddlinventoryvariance']; // inventoryvariance
				$param_setting2_array[5]=$formdata['ddlinvenoversell']; // invenoversell
				$param_setting2_array[6]=$formdata['ddlenabledamagedtrxn']; // enabledamagedtrxn
				$param_setting2_array[7]=$formdata['ddldisplayinvsummary']; // displayinvsummary
				$param_setting2_array[8]=$formdata['ddlincludeloadrequest']; // includeloadrequest
				$param_setting2_array[9]=$formdata['ddlloadreqrolluporders']; // loadreqrolluporders
				$param_setting2_array[10]=$formdata['ddldepotprinter']; // depotprinter
				$param_setting2_array[11]=$formdata['ddllockstatus']; // lockstatus
				$param_setting2_array[12]=$formdata['ddlloadreqmethod']; // loadreqmethod
				$param_setting2_array[13]=$formdata['ddlrouteprinter']; // routeprinter
				$param_setting2_array[14]=$formdata['ddlroutetype']; // routetype
				$param_setting2_array[15]=$formdata['memo1']; // memo1
				$param_setting2_array[16]=$formdata['memo2']; // memo2
				$param_setting2_array[17]=$formdata['chkenablemiddaytelecom']; // enablemiddaytelecom
				$param_setting2_array[18]=$formdata['chkallowroutestartdayflag']; // allowroutestartdayflag
				$param_setting2_array[19]=$formdata['chkallowgctocash']; // allowgctocash
				$param_setting2_array[20]=$formdata['chkenablerouteweekday']; // enablerouteweekday
				$param_setting2_array[21]=$formdata['chkenabledraftcopy']; // enabledraftcopy
				$param_setting2_array[22]=$formdata['cdcvaliditydays']; // cdcvaliditydays
				$param_setting2_array[23]=$formdata['newcustomerseqnumber']; // newcustomerseqnumber
				$param_setting2_array[24]=$formdata['creditlimit']; // creditlimit
				$param_setting2_array[25]=$formdata['routebalance']; // routebalance
				$param_setting2_array[26]=$formdata['vehicleodometer']; // vehicleodometer
				$param_setting2_array[27]=$formdata['defaultdeliverydays']; // defaultdeliverydays
				$param_setting2_array[28]=$this->currentUser->username; // modified
				$param_setting2_array[29]=$formdata['txtallowradius'];	//allowedradius
				$param_setting2_array[30]=$formdata['txpdc_threshold'];		//pdcthreshold
				$param_setting2_array[31]=$formdata['defaultrequestdays']; // defaultrequestdays
				$param_setting2_array[32]= 0; // defaultweeksetting which we have remove
				$param_setting2_array[33]= $formdata['chkenableautopostingaccount']; // enableautopostingaccount
				
				if($formdata['chkenableautopostingaccount'] == 1) {
					$param_setting2_array[34]= $formdata['ddlcustomercode']; // customercode
				} else {
					$param_setting2_array[34]= 0; // customercode
				}
				
				$result = $this->SFA_Comman->executequery('CALL sp_edit_organization_route_setting2(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',$param_setting2_array,'');
			}
			$this->_helper->redirector('routetmpl', 'route', 'organization');
		}
		elseif($params['id'] > 0)
		{
			$result = $this->SFA_Comman->executequery('CALL sp_get_organization_route_addroutetmpl(?)',$params['id'],'');
			
			$this->view->tmpl_data 			= $result[1];
			$this->view->itemgroupcode 		= $result[2];
			$this->view->currency_info 		= $result[3];
			$this->view->route_data 		= $result[4];
			$this->view->routetmpl_val		= $params['id'];
			
			$viewformdata['txtpwd1'] 		= $result[0][0]['password1'];			//password1
			$viewformdata['txtpwd2'] 		= $result[0][0]['password2'];			//password2
			$viewformdata['txtpwd3']		= $result[0][0]['password3'];			//password3
			$viewformdata['txtpwd4'] 		= $result[0][0]['password4'];			//password4
			$viewformdata['txtpwd5'] 		= $result[0][0]['password5'];			//password5			
			$viewformdata['txtdt_time'] 	= $result[0][0]['passwordarray01'];		//passwordarray01
			$viewformdata['txtpr_chng'] 	= $result[0][0]['passwordarray02'];		//passwordarray02
			$viewformdata['txtpromo_ovr']	= $result[0][0]['passwordarray03'];		//passwordarray03
			$viewformdata['txtrt_setup'] 	= $result[0][0]['passwordarray04'];		//passwordarray04
			$viewformdata['txttel_setup']	= $result[0][0]['passwordarray05'];		//passwordarray05
			$viewformdata['txtload_adj'] 	= $result[0][0]['passwordarray06'];		//passwordarray06
			$viewformdata['txtst_day'] 		= $result[0][0]['passwordarray07'];		//passwordarray07
			$viewformdata['txtapp_exit'] 	= $result[0][0]['passwordarray08'];		//passwordarray08
			$viewformdata['txtsettl'] 		= $result[0][0]['passwordarray09'];		//passwordarray09
			$viewformdata['txtload_sec'] 	= $result[0][0]['passwordarray10'];		//passwordarray10
			$viewformdata['txtunload'] 		= $result[0][0]['passwordarray11'];		//passwordarray11
			$viewformdata['txtload_out'] 	= $result[0][0]['passwordarray12'];		//passwordarray12
			$viewformdata['txtprnt_doc'] 	= $result[0][0]['passwordarray13'];		//passwordarray13
			$viewformdata['txtload_trans'] 	= $result[0][0]['passwordarray14'];		//passwordarray14
			$viewformdata['txtload_req'] 	= $result[0][0]['passwordarray15'];		//passwordarray15
			$viewformdata['txtnewreq'] 		= $result[0][0]['passwordarray16'];		//passwordarray16
			$viewformdata['createddate']	= date("d-m-Y",strtotime($result[0][0]['cdat']));
			$this->view->formdata = array_merge($result[0][0],$viewformdata);			
			
			/* For Setting 1 */
			$result_setting1 = $this->SFA_Comman->executequery('CALL sp_get_organization_route_setting1(?)',$params['id'],'');
			$this->view->formdata_setting1 = $result_setting1[0][0];
			$this->view->itemgroupcode = $result_setting1[1];
			
			
			/* For Setting 2 */
			$result_setting2 = $this->SFA_Comman->executequery('CALL sp_get_organization_route_setting2(?)',$params['id'],'');
			$this->view->formdata_setting2 	= $result_setting2[0][0];			
		}		
		else
		{
			$result = $this->SFA_Comman->executequery('CALL sp_get_organization_route_addroutetmpl(?)','0','');
			$this->view->tmpl_data 			= $result[0];
			$this->view->routeitemgrp_data 	= $result[1];
			$this->view->itemgroupcode 		= $result[2];
			$this->view->currency_info 		= $result[3];
			$this->view->route_data 		= $result[4];
		}
    }
    /**
    * @name       tmplsetting1values
    * @since      27-12-2011
    * @version    Release: 1
    * @author     HD <hiren.d@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This action is use for the add settings of route template
    */
    public function tmplsetting1values()
    {
		$print_option = array();
		$print_option[0]['id']	= '0';
		$print_option[1]['id']	= '1';
		$print_option[2]['id']	= '2';
		$print_option[0]['val']	= 'Do Not Print';
		$print_option[1]['val']	= 'Optional, Prompt User';
		$print_option[2]['val']	= 'Force Print';
		$this->view->print_option	= $print_option;
	
		$prompt_odometer = array();
		$prompt_odometer[0]['id']	= '0';
		$prompt_odometer[1]['id']	= '1';
		$prompt_odometer[2]['id']	= '2';
		$prompt_odometer[0]['val']	= 'No Prompt';
		$prompt_odometer[1]['val']	= 'Prompt at Start/End Day';
		$prompt_odometer[2]['val']	= 'Prompt at Start/End Day + Each Visit';
		$this->view->prompt_odometer	= $prompt_odometer;
	
		$inv_case_input = array();
		$inv_case_input[0]['id'] 	= '0';
		$inv_case_input[1]['id'] 	= '1';
		$inv_case_input[2]['id'] 	= '2';
		$inv_case_input[0]['val'] 	= 'Inventory By Units Only';
		$inv_case_input[1]['val'] 	= 'Enter By Units, Print by Cases/Units';
		$inv_case_input[2]['val'] 	= 'Inventory By Cases/Units';
		$this->view->inv_case_input	= $inv_case_input;
	
		$load_req_report = array();
		$load_req_report[0]['id'] 	= '0';
		$load_req_report[1]['id'] 	= '1';
		$load_req_report[2]['id'] 	= '2';
		$load_req_report[0]['val'] 	= 'Disable Load Request';
		$load_req_report[1]['val'] 	= 'Request Qty Only Report';
		$load_req_report[2]['val'] 	= 'Add On Qty Report';
		$this->view->load_req_report 	= $load_req_report;
	
	
		$auto_calculated = array();
		$auto_calculated[0]['id'] 	= '0';
		$auto_calculated[1]['id'] 	= '1';
		$auto_calculated[2]['id'] 	= '2';
		$auto_calculated[3]['id'] 	= '3';
		$auto_calculated[4]['id'] 	= '4';
		$auto_calculated[0]['val'] 	= 'Disable';
		$auto_calculated[1]['val'] 	= 'Calc Unload Qtys; Disable Chgs';
		$auto_calculated[2]['val'] 	= 'Calc Unload Qtys; Enable Chgs';
		$auto_calculated[3]['val'] 	= 'Calc End Inv. Qtys; Disable Chgs';
		$auto_calculated[4]['val'] 	= 'Calc End Inv. Qtys; Enable Chgs';
		$this->view->auto_calculated 	= $auto_calculated;
	
		$req_load_in = array();
		$req_load_in[0]['id'] 	= '0';
		$req_load_in[1]['id'] 	= '1';
		$req_load_in[2]['id'] 	= '2';
		$req_load_in[0]['val'] 	= 'Disable';
		$req_load_in[1]['val'] 	= 'Prompt Before Settlement';
		$req_load_in[2]['val'] 	= 'Require Before Settlement';
		$this->view->req_load_in 	= $req_load_in;
	
		$load_sheet_data = array();
		$load_sheet_data[0]['id'] 	= '0';
		$load_sheet_data[1]['id'] 	= '1';
		$load_sheet_data[0]['val'] 	= 'Disable';
		$load_sheet_data[1]['val'] 	= 'Print After Reload Report Printing';
		$this->view->load_sheet_data 	= $load_sheet_data;
	
	
		$inv_print_data = array();
		$inv_print_data[0]['id'] 	= '0';
		$inv_print_data[1]['id'] 	= '1';
		$inv_print_data[0]['val'] 	= 'Do not Print Inventory Values on Load or Reload Reports';
		$inv_print_data[1]['val'] 	= 'Include Inventory Values on Detail Lines and Report Totals';
		$this->view->inv_print_data	= $inv_print_data;
		
	
		$hhc_item_data = array();
		$hhc_item_data[0]['id'] 	= '0';
		$hhc_item_data[1]['id'] 	= '1';
		$hhc_item_data[0]['val'] 	= 'Display ANItemCode(8)';
		$hhc_item_data[1]['val'] 	= 'Display AlternateItemCode(25)';
		$this->view->hhc_item_data 	= $hhc_item_data;
		
		$print_alternate = array();
		$print_alternate = array(array('id'=>0,'val'=>'Disabled'),
				array('id'=>1,'val'=>'Print AlternateCode And ActualItemCode'),
				array('id'=>2,'val'=>'Print AlternateCode Only'));
		$this->view->print_alternate = $print_alternate;
	
		$display_item_desc = array();
		$display_item_desc[0]['id'] 	= '0';
		$display_item_desc[1]['id'] 	= '1';
		$display_item_desc[0]['val'] 	= 'Display Item Description';
		$display_item_desc[1]['val'] 	= 'Display Item Long Description';
		$this->view->display_item_desc 	= $display_item_desc;
	
	
		$ena_load_trans = array();
		$ena_load_trans[0]['id']  	= 0;
		$ena_load_trans[0]['val'] 	= 'Disable';
		$ena_load_trans[1]['id']  	= 1;
		$ena_load_trans[1]['val'] 	= 'Transfer In Only';
		$ena_load_trans[2]['id']  	= 2;
		$ena_load_trans[3]['val'] 	= 'Transfer Out Only';
		$ena_load_trans[4]['id']  	= 3;
		$ena_load_trans[4]['val'] 	= 'Damage Only';
		$ena_load_trans[4]['id']  	= 4;
		$ena_load_trans[4]['val'] 	= 'Transfer In And Transfer Out';
		$ena_load_trans[2]['id']  	= 5;
		$ena_load_trans[3]['val'] 	= 'Transfer In And Damage';
		$ena_load_trans[4]['id']  	= 6;
		$ena_load_trans[4]['val'] 	= 'Transfer Out And Damage';
		$ena_load_trans[4]['id']  	= 7;
		$ena_load_trans[4]['val'] 	= 'Enable All';
		$this->view->ena_load_trans = $ena_load_trans;
	
		$scan_data = array();
		$scan_data[0]['id']  		= 0;
		$scan_data[0]['val'] 		= 'Manual';
		$scan_data[1]['id']  		= 1;
		$scan_data[1]['val'] 		= 'Manual/Scanning';
		$scan_data[2]['id']  		= 2;
		$scan_data[2]['val'] 		= 'Scanning Only';
		$this->view->scan_data	 	= $scan_data;
    }
    /**
    * @name       tmplsetting2values
    * @since      27-12-2011
    * @version    Release: 1
    * @author     HD <hiren.d@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This action is use for the add settings of route template
    */
    public function tmplsetting2values()
    {
		$print_option = array();
		$print_option[0]['val']	= 'Do Not Print';
		$print_option[1]['val']	= 'Optional, Prompt User';
		$print_option[2]['val']	= 'Force Print';
		$print_option[0]['id']	= 0;
		$print_option[1]['id']	= 1;
		$print_option[2]['id']	= 2;
		$this->view->print_option	= $print_option;
	
		$ena_no_sale = array();
		$ena_no_sale[0]['val'] 	= 'Disable';
		$ena_no_sale[1]['val'] 	= 'Enable No Sale at Point of Sale';
		$ena_no_sale[2]['val'] 	= 'Enable No Sale at Point of Sale with Printing of Unserviced Customers';
		$ena_no_sale[0]['id']	= 0;
		$ena_no_sale[1]['id']	= 1;
		$ena_no_sale[2]['id']	= 2;
		$this->view->ena_no_sale	= $ena_no_sale;
	
		$cash_bal = array();
		$cash_bal[0]['val'] 	= 'Balance Should be 0.00';
		$cash_bal[1]['val'] 	= 'Allow Over/Short Deposit Report';
		$cash_bal[0]['id']		= 0;
		$cash_bal[1]['id']		= 1;
		
		$this->view->cash_bal		= $cash_bal;
	
		$inv_variance = array();
		$inv_variance[0]['val'] 	= 'Disable';
		$inv_variance[1]['val'] 	= 'Variance not Due at End Day';
		$inv_variance[2]['val'] 	= 'Variance Due at End Day';
		$inv_variance[3]['val'] 	= 'Shortages Only Due at End Day';
		$inv_variance[0]['id']		= 0;
		$inv_variance[1]['id']		= 1;
		$inv_variance[2]['id']		= 2;
		$inv_variance[3]['id']		= 3;
		$this->view->inv_variance 	= $inv_variance;
	
		$inv_over_sell = array();
		$inv_over_sell[0]['val']	= 'Disable';
		$inv_over_sell[1]['val']	= 'Enable Over Sell of Inventory';
		$inv_over_sell[0]['id']		= 0;
		$inv_over_sell[1]['id']		= 1;
		
		$this->view->inv_over_sell	= $inv_over_sell;
	
	
		$ena_dmg_trxn = array();
		$ena_dmg_trxn[0]['val']	= 'Disable';
		$ena_dmg_trxn[1]['val']	= 'Allow unloading damaged return in the middle of the day.';
		$ena_dmg_trxn[0]['id']		= 0;
		$ena_dmg_trxn[1]['id']		= 1;		
		$this->view->ena_dmg_trxn	= $ena_dmg_trxn;
	
		$disp_inv_summ = array();
		$disp_inv_summ[0]['val']	= 'Disable';
		$disp_inv_summ[1]['val']	= 'Display after invoice exit';
		$disp_inv_summ[2]['val']	= 'Display after each transaction and invoice exit';
		$disp_inv_summ[0]['id']		= 0;
		$disp_inv_summ[1]['id']		= 1;
		$disp_inv_summ[2]['id']		= 2;
		$this->view->disp_inv_summ	= $disp_inv_summ;
	
		$load_req_roll_up = array();
		$load_req_roll_up = array(array('id'=>0,'val'=>'Add Load Request'),
								  array('id'=>1,'val'=>'Suggested Load Request from Average Sales'),
								  array('id'=>2,'val'=>'Roll up Orders for Next Load Request'));
		$this->view->load_req_roll_up	= $load_req_roll_up;		
	
		$depot_print = array();
		$depot_print[0]['val']	= '1 - Printer1=6820 Cabled,80,Printer6820Cabled';
		$depot_print[1]['val']	= '2 - Printer2=6820 IrDA,80,Printer6820IrDA';
		$depot_print[2]['val']	= '3 - Printer3=6804T Cabled,40,Printer6804TCabled';
		$depot_print[3]['val']	= '4 - Printer4=6804T IrDA,40,Printer6804TIrDA';
		$depot_print[4]['val']	= '5 - Printer5=6804DM Cabled,40,Printer6804DMCabled';
		$depot_print[5]['val']	= '6 - Printer6=6804DM IrDA,40,Printer6804DMIrDA';
		$depot_print[6]['val']	= '7 - Printer7=6805A,40,Printer6805A';
		$depot_print[7]['val']	= '8 - Printer8=6806,40,Printer6806';
		$depot_print[0]['id']	= 0;
		$depot_print[1]['id']	= 1;
		$depot_print[2]['id']	= 2;
		$depot_print[3]['id']	= 3;
		$depot_print[4]['id']	= 4;
		$depot_print[5]['id']	= 5;
		$depot_print[6]['id']	= 6;
		$depot_print[7]['id']	= 7;
		$this->view->depot_print	= $depot_print;
	
	
		$load_req_method = array();		
		$load_req_method[1]['val']	= 'View only';
		$load_req_method[2]['val']	= 'Editable';		
		$load_req_method[1]['id']	= 0;
		$load_req_method[2]['id']	= 1;		
		$this->view->load_req_method	= $load_req_method;
	
	
		$route_type = array();
		$route_type[0]['val']		= '0 - both advance and route sales enabled;history and goals achievement maintained for orders only';
		$route_type[1]['val']		= '1 - both advance and route sales enabled;history and goals achievement maintained for invoices only';
		$route_type[2]['val']		= '2 - advance sales only';
		$route_type[3]['val']		= '3 - route sales only';
		$route_type[0]['id']		= 0;
		$route_type[1]['id']		= 1;
		$route_type[2]['id']		= 2;
		$route_type[3]['id']		= 3;
		$this->view->route_type		= $route_type;
    }
}