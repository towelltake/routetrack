<?php
/**
* @name       IndexController
* @since      
* @version    Release: 1
* @author     HD <hiren.d@elantechnologies.com>
* @copyright  Elan Technologies
* @param   	
*
* This controller is manage user Integration module.
*/
class Integration_IndexController extends Integration_Library_Controller_Action_Abstract
{
    /**
    * @name       init
    * @since      17/02/2012
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
	$this->SFA_Comman	= new SFA_Comman();
	
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
	$this->view->colan 		= $this->translate->_('Colan');

	$this->SFA_Comman			= new SFA_Comman();
	$this->decimalplaces 		= $this->SFA_Comman->getdecimalplaces();
	$this->view->decimalplaces 	= $this->SFA_Comman->getdecimalplaces();
	$this->view->sec_lang		= $this->SFA_Comman->getsecondlanguage();
	$this->sec_lang 			= $this->view->sec_lang;
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

  	public function routeprosyncAction() {
  		$this->view->params = $params = $this->getRequest()->getParams();
        $this->view->formdata = $formdata = $this->_request->getPost();
        $this->view->title = $this->translate->_('Import Data');
        
        //$this->OracleDBSync = new SFA_oracleSyncData();
	 $this->OracleDBSync = new SFA_DataSyncImport();
        $this->OracleDBSync->setUserName($this->currentUser->username);

        $process = FALSE;
        $Common_NameSpace = new Zend_Session_Namespace('Common');
        $last_url = htmlspecialchars($_SERVER['HTTP_REFERER']);
        $end_last_url = explode('/',$last_url);
        if(end($end_last_url) == 'routeprosync' || strpos($last_url,'routeprosync') || strpos($last_url,'/routeprosync/' )) {
            $sel_date = ($formdata["txtdate"] != '') ? $formdata["txtdate"] : $Common_NameSpace->tdate;
        } else {
            $sel_date = ($formdata["txtdate"] != '') ? $formdata["txtdate"] : date('d-m-Y');
        }
        // ADD DATE VALUE IN SESSION
        if($sel_date != '') {
            $Common_NameSpace->tdate = $sel_date;
            $this->view->date = $sel_date;
        } else {
            $Common_NameSpace->tdate = date('d-m-Y');
            $this->view->date = date('d-m-Y');
        }
        $date = date("Y-m-d",strtotime(str_replace('/', '-', $Common_NameSpace->tdate)));
        
        if($process == TRUE || $formdata["hdDelete"] == 1) {
			//echo "ociConnect!<br><br>";
            $this->SFA_Comman->ociConnect();
			//echo "updateDBFromOracle";
            $this->OracleDBSync->updateDBFromOracle();
            $this->SFA_Comman->ociClose();
        }
  		$cols_array = array('distinct tablename','DATE(cdat)');
		$columns_show =  array($this->translate->_('Table Name'),
				$this->translate->_('Date'));
		if($this->css == 'ar_') {

		}
		// prepare the configuration for grid
		$pagingparams = array(
				 "show_grid_heading" => true,
				 "grid_heading_message" => $this->translate->_('Overview'),
				 "pagename" => $this->translate->_('Tables Updated'),
				 "show_records_per_page" => isset($formdata["show_records_per_page"])?$formdata["show_records_per_page"]:10,
				 "show_searchbox" => true,
				 "searchbox_value" => isset($formdata["grid_search_text"])?$formdata["grid_search_text"]:"",
				 "show_selectbox" => false,
				 "selected_list" => $checked,
				 "show_editlink" => false,
				 "show_deletelink" => false,
				 "show_deleteall" => false,
				 "primaryid" => "tablename",
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
		$get_return_vals = $pagingshow->commnfunc();
		$downloadCSV = (isset($formdata['downloadcsv'])) ? $formdata['downloadcsv'] : $params['downloadcsv'];
		// Hiren Dave on 19 Nov, 2012 - START - to check if need to print data
		$printData = (isset($formdata['printData'])) ? $formdata['printData'] : $params['printData'];
		// called stored procedure for counter
		$param_array    = array();
		$param_array[1] = '1';
		$param_array[2] = '';
		$param_array[3] = $get_return_vals['order_columns_name'];
		$param_array[4] = $get_return_vals['order_type'];
		$param_array[5] = $get_return_vals['offset'];
		$param_array[6] = (int)$get_return_vals['show_records_per_page'];
		$param_array[7] = implode(", ",$pagingparams["fetch_columns_inquery"]);
		$param_array[8] = $date;
		$result = $this->SFA_Comman->executequery('CALL int_imp_updated_tables()',$param_array,'',$downloadCSV,$printData,2,$pagingparams);
		$data_arr["count"] 	= $result[0][0]['counter'];
		$data_arr["data"][0] 	= $result[1];
		// pass the data in summary_showdatagrid() function & create a final variable for view
		$this->view->pagegridshow =  $pagingshow->summary_showdatagrid($data_arr);
		$this->view->addScriptPath(APPLICATION_PATH."/theme/default/datagrid/");
  	}
  	
  	public function oracleupdateAction() {
  		$this->view->params = $params = $this->getRequest()->getParams();
        $this->view->formdata = $formdata = $this->_request->getPost();
        $this->view->title = $this->translate->_('Export Data To Oracle');

        $this->SFA_Comman->ociConnect();
        //$this->OracleDBSync = new SFA_oracleDBUpdate();
	$this->OracleDBSync = new SFA_DataSyncExport();
        $this->OracleDBSync->setUserName($this->currentUser->username);
        //$this->OracleDBSync->updateOracleDBWithExportData();
        $process = FALSE;
        $Common_NameSpace = new Zend_Session_Namespace('Common');
        $last_url = htmlspecialchars($_SERVER['HTTP_REFERER']);
        $end_last_url = explode('/',$last_url);
        if(end($end_last_url) == 'oracleupdate' || strpos($last_url,'oracleupdate') || strpos($last_url,'/oracleupdate/' )) {
            $sel_date = ($formdata["txtdate"] != '') ? $formdata["txtdate"] : $Common_NameSpace->tdate;
        } else {
            $sel_date = ($formdata["txtdate"] != '') ? $formdata["txtdate"] : date('d-m-Y');
        }
        // ADD DATE VALUE IN SESSION
        if($sel_date != '') {
            $Common_NameSpace->tdate = $sel_date;
            $this->view->date = $sel_date;
        } else {
            $Common_NameSpace->tdate = date('d-m-Y');
            $this->view->date = date('d-m-Y');
        }
        $date = date("Y-m-d",strtotime(str_replace('/', '-', $Common_NameSpace->tdate)));
        
        $routeKeyArr = array();
        if($process == TRUE || $formdata["hdDelete"] == 1) {
        	$i = 0;
        	if($formdata["hdDelete"] == 1) {
        		$routeKeyArr = $formdata['chk'];
        	} else {
	        	$queryResult = $this->SFA_Comman->executequery('call int_exp_get_routekey()',null);
		        foreach($queryResult[0] as $row) {
		            $routeKeyArr[$i++] = $row['routekey'];
		        }
        	}
        	print_r($routeKeyArr);echo "<br>";
        	$this->OracleDBSync->updateOracleDBWithExportData($routeKeyArr);
        	$this->SFA_Comman->ociClose();
        }
  		$cols_array = array('rm.routecode','rm.routename','sm.salesmancode','sm.salesmanname1','sed.routekey','sed.exportedflag');
		$columns_show =  array($this->translate->_('Route Code'),
				$this->translate->_('Route Name'),
				$this->translate->_('Salesman Code'),
				$this->translate->_('Salesman'),
				$this->translate->_('RouteKey'),	
				$this->translate->_('Status'));
		if($this->css == 'ar_') {
			$cols_array[1]	= 'arbroutename';
			$cols_array[3]	= 'arbsalesmanname1';
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
				 "show_editlink" => false,
				 "show_deletelink" => false,
				 "show_deleteall" => false,
				 "primaryid" => "routekey",
				 "status_cols" => array(
							array(
							"cols_name" => "exportedflag",
							"status_change" => array("0"=>"Not Exported","1"=>"Exported")
							)
							),
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
		$param_array[8] = $date;
		$downloadCSV = (isset($formdata['downloadcsv'])) ? $formdata['downloadcsv'] : $params['downloadcsv'];
		// Hiren Dave on 19 Nov, 2012 - START - to check if need to print data
		$printData = (isset($formdata['printData'])) ? $formdata['printData'] : $params['printData'];
		// called stored procedure for counter
		
		$result = $this->SFA_Comman->executequery('CALL int_exp_get_closedroutes()',$param_array,'',$downloadCSV,$printData,2,$pagingparams);
		$data_arr["count"] 	= $result[0][0]['counter'];
		$data_arr["data"][0] 	= $result[1];
		// pass the data in summary_showdatagrid() function & create a final variable for view
		$this->view->pagegridshow =  $pagingshow->summary_showdatagrid($data_arr);
		$this->view->addScriptPath(APPLICATION_PATH."/theme/default/datagrid/");
  	}  
}
?>