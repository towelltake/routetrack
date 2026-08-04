<?php
/**
* @name       IndexController
* @since      
* @version    Release: 1
* @author     HD <hiren.d@elantechnologies.com>
* @copyright  Elan Technologies
* @param       
*
* This controller is manage user Datamanagement module.
*/
class Datamanagement_IndexController extends Datamanagement_Library_Controller_Action_Abstract
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
    $this->translate = Zend_Registry::get('Zend_Translate');
    $this->SFA_Comman = new SFA_Comman();
    
    $this->currentUser = SFA_Loginauth::getIdentity();
    if(!isset($this->currentUser) || empty($this->currentUser))
    {
        SFA_Message::setMsg($this->translate->_('Do Login'));
        //$this->_helper->redirector("index", "index", "home");
            $url = $this->view->baseUrl();
            echo '<script type="text/javascript">window.location="'.$url.'";</script>';
            exit;
    }
    $this->css = $this->translate->_('CSS');
    $this->view->css = $this->css;
    $this->view->overview = $this->translate->_('Overview');
    $this->view->details = $this->translate->_('Details');
    $this->view->required = $this->translate->_('Required');
    $this->view->colan = $this->translate->_('Colan');

    $this->SFA_Comman = new SFA_Comman();
    $this->decimalplaces = $this->SFA_Comman->getdecimalplaces();
    $this->view->decimalplaces = $this->SFA_Comman->getdecimalplaces();
    $this->view->sec_lang = $this->SFA_Comman->getsecondlanguage();
    $this->sec_lang = $this->view->sec_lang;
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
        
        if(in_array($getparams_init['action'],array("deletedata","deletetrxndata")))
        {
            if(!$this->checkaccess("delete"))
                $this->_forward('noaccess','aclaccess','home', array("actiontype"=>"delete","modulename"=>$this->currentmodulename));
        }
        
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
    * @name       deletedataAction
    * @since      17/02/2012
    * @version    Release: 1
    * @author     HD <hiren.d@elantechnologies.com>
    * @author     GP <gayatri@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This action is use for the delete data
    * add form submit data code
    */
    public function deletedataAction()
    {
        $this->view->params = $params = $this->getRequest()->getParams();
        $this->view->formdata = $formdata = $this->_request->getPost();    

        $this->view->show_grid = '0';
        
        $combo_data = array();
        $combo_data[0]['val'] = 'All Transactions';
        $combo_data[1]['val'] = 'All Hierarchy';
        $combo_data[2]['val'] = 'All Customers';
        $combo_data[3]['val'] = 'All Masters';
        
        $this->view->combo_data = $combo_data;
        
        
        //Remove/truncate table of transaction related
        if(count($formdata) > 0)
        {
            if($formdata['ddldeletedata'] == "All Transactions")
            {
                $this->SFA_Comman->executequery('CALL sp_delete_datamanagement_index_deletedata()','','');
                SFA_Message::setMsg($this->translate->_('Delete Record'));
            }
            elseif($formdata['ddldeletedata'] == "All Customers")
            {
                $this->SFA_Comman->executequery('CALL sp_delete_datamanagement_deletedata_allcustomer(?)',$this->currentUser->username,'');
                SFA_Message::setMsg($this->translate->_('Delete Record'));
            }
            elseif($formdata['ddldeletedata'] == "All Masters")
            {
                $this->SFA_Comman->executequery('CALL sp_delete_datamanagement_deletedata_allmasters(?)',$this->currentUser->username,'');
                SFA_Message::setMsg($this->translate->_('Delete Record'));
            }
            elseif($formdata['ddldeletedata'] == "All Hierarchy")
            {
                $this->SFA_Comman->executequery('CALL sp_delete_datamanagement_index_allhierarchy(?)',$this->currentUser->username,'');
                SFA_Message::setMsg($this->translate->_('Delete Record'));
            }
        }
    }
    /**
    * @name       deletetrxnAction
    * @since      17/02/2012
    * @version    Release: 1
    * @author     HD <hiren.d@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This action is use for the delete transaction data
    */
    public function deletetrxndataAction()
    {
        $this->view->params = $params = $this->getRequest()->getParams();
        $this->view->formdata   = $formdata = $this->_request->getPost();
        
        if(count($formdata) > 0)
        {
            $param_array = array();
            $param_array[1] = $formdata['ddldeletetrxn'];
            $param_array[2] = date('Y-m-d h:i:s',strtotime($formdata['txtstartdate']));
            $param_array[3] = '0';
            
            $result = $this->SFA_Comman->executequery('CALL sp_delete_datamanagement_deletetransaction_route(?,?,?)',$param_array,'');            
            $this->view->route_data = $result[1];
                
            if($result[0][0]['result'] == 'Success')
                SFA_Message::setMsg($this->translate->_('Delete Record'));
        }
        else{
            $route_data = $this->SFA_Comman->executequery('CALL sp_combo_routemaster()','','');
            $this->view->route_data = $route_data[0];
        }
    }
    
    /**
    * @name       viewlogdata
    * @since      18-07-2012
    * @version    Release: 1
    * @author     Rachir <kanzaria.rachir@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This action is use for display log master data
    */
    public function viewlogdataAction()
    {
    $this->view->params = $params = $this->getRequest()->getParams();
        $this->view->formdata = $formdata = $this->_request->getPost();

    if($formdata["hdDelete"]==1)
            SFA_Message::setMsg($this->translate->_('Delete Record'));

    $this->view->title = $this->translate->_('View Activity Log');

    //$id = $this->translate->_('ID');
    $code = $this->translate->_('Code');
    $formname = $this->translate->_('Form Name');
    $tablename = $this->translate->_('Table Name');
    $recordid = $this->translate->_('Record Id');
    $created_by = $this->translate->_('Created By');
    $created_date = $this->translate->_('Created Date');
    $operationtype = $this->translate->_('Operation Type');

    $cols_array = array('code','formname','tablename', 'operation_type', 'recordid', 'created','DATE_FORMAT(cdat,"%d-%m-%Y") as createdat');
    $columns_show  =  array($code, $formname, $tablename, $operationtype, $recordid,$created_by, $created_date);
    
    $pagingparams = array(
                 "show_grid_heading" => true,
                 "grid_heading_message" => $this->translate->_('Overview'),
                 "show_records_per_page" => isset($formdata["show_records_per_page"])?$formdata["show_records_per_page"]:10,
                 "pagename" => $this->translate->_('View Activity Log'),
                 "show_searchbox" => true,
                 "searchbox_value" => isset($formdata["grid_search_text"])?$formdata["grid_search_text"]:"",
                 "show_selectbox" => true,
                 "show_editlink" => false,
                 "show_deletelink" => false,
                 "deletelink" => array(""),
                 "show_deleteall" => false,
                 "primaryid" => "code",
                 //"editlink" => array(""),
                 "fetch_columns_inquery" => $cols_array,
                 "show_columns" => $columns_show,
                 "no_search_fields" => array("createdat"),
                 "nodata_message" => $this->translate->_('No Record(s) Found')
                 );
    
    // create grid class object
    $pagingshow = new SFA_Paging($pagingparams);
    
    // call common function of grid class
    $get_return_vals = $pagingshow->commnfunc();
    
        
    // call the stored procedure for fetch the data
    $param_array = array();
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
    $rowcount = $this->SFA_Comman->executequery('CALL sp_get_datamanagement_index_viewlogdata(?,?,?,?,?,?,?,?)',$param_array,'',$downloadCSV,$printData,2,$pagingparams);

    $data_arr["count"] = $rowcount[0][0]['counter'];
    $data_arr["data"][0] = $rowcount[1];
    
    
    
    // pass the data in summary_showdatagrid() function & create a final variable for view
    $this->view->pagegridshow =  $pagingshow->summary_showdatagrid($data_arr);
    
    $this->view->addScriptPath(APPLICATION_PATH."/theme/default/datagrid/");
    
    }

    /**
     * @name       downsyncAction
     * @since      3/02/2015
     * @version    Release: 1
     * @author     Veena<v.nair@mirnah.com>
     * @copyright  Mirnah Technologies
     * @param
     *
     * This action is use for the down syncing transaction data
     */
    public function downsyncAction()
    {
        $this->view->params = $params = $this->getRequest()->getParams();
        $this->view->formdata = $formdata = $this->_request->getPost();
        $this->view->title  = $this->translate->_('Down Sync Route(s)');
        $tourIdArr = array();
        $process = FALSE;

        $Common_NameSpace = new Zend_Session_Namespace('Common');
        $last_url = htmlspecialchars($_SERVER['HTTP_REFERER']);
        $end_last_url = explode('/',$last_url);
        if(end($end_last_url) == 'downsync' || strpos($last_url,'downsync') || strpos($last_url,'/downsync/' )) {
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

        $this->DownSync_Data = new SFA_syncData();
        $this->DownSync_Data->setUserName($this->currentUser->username);
        if($process == TRUE || $formdata["hdDelete"] == 1) {
            $tourIdArr = "";
            if($formdata["hdDelete"] == 1) {
                $tourIdArr  = $formdata['chk'];
            } else {
                //$this->DownSync_Data->getTourIDforSelectedDate($date);
                $tourIdArr = $this->DownSync_Data->getTourIdValues();
            }

            $this->DownSync_Data->getSAPData($tourIdArr);
            $param_array = array();
            $param_array[1] = $date;
            $result = $this->SFA_Comman->executequery('CALL sp_int_import_downsyncresult(?)',$param_array);

            $cols_array = array('tourid','importstatus','date','route','salesman','status');
            $columns_show = array($this->translate->_('TourId'),
                $this->translate->_('Imported'),
                $this->translate->_('Date'),
                $this->translate->_('Route'),
                $this->translate->_('SalesMan'),
                $this->translate->_('Status'));
        } else {
           // $this->DownSync_Data->getTourIDforSelectedDate($date);
            $param_array = array();
            $param_array[1] = $date;
            $result = $this->SFA_Comman->executequery('CALL sp_int_import_downsyncpage(?)',$param_array);
            
            $cols_array = array('tourid','importstatus','date','status');
            $columns_show = array($this->translate->_('TourId'),
                $this->translate->_('Imported'),
                $this->translate->_('Date'),
                $this->translate->_('Status'));
        }
        $disabled = array();
        $count = 0;
        for ($val = 0; $val < sizeof($result[0]);$val++) {
            if($result[0][$val]['importstatus'] == 1) {
                $disabled[$count++] =  $result[0][$val]['tourid'];
                $param_array = array();
                $param_array[1] = $result[0][$val]['tourid'];
                $queryresult = $this->SFA_Comman->executequery('CALL sp_int_import_gettourstatus()',$param_array);
                if($queryresult[0][0]['routeclosed'] == 1) {
                    if($result[0][$val]['exportstatus'] == 1) {
                        $result[0][$val]['status'] = 'Exported';
                    } else {
                        $result[0][$val]['status'] = 'Route Closed';
                    }
                } else if($queryresult[0][0]['routeclosed'] == 0) {
                    $result[0][$val]['status'] = 'On Route';
                } else {
                    $result[0][$val]['status'] = 'Imported';
                }
            } else {
                if($result[0][$val]['status'] == "") {
                    $result[0][$val]['status'] = 'Not Imported';
                }
            }
            unset($result[0][$val]['exportstatus']);
        }
        $data_arr["count"] = sizeof($result[0]);
        $data_arr["data"][0] = $result[0];
        if($this->css == 'ar_') {
        }
        // prepare the configuration for grid
        $pagingparams = array(
             "show_grid_heading" => true,
             "grid_heading_message" => $this->translate->_('Overview'),
             "pagename" => $this->translate->_('Tour ID'),
             "show_records_per_page" => isset($formdata["show_records_per_page"])?$formdata["show_records_per_page"]:10,
             "show_searchbox" => true,
             "searchbox_value" => isset($formdata["grid_search_text"])?$formdata["grid_search_text"]:"",
             "show_selectbox" => true,
             "disabled_list" => $disabled,
        //         "selected_list" => $checked,
             "show_editlink" => false,
             "show_deletelink" => false,
             "show_deleteall" => false,
             "primaryid" => "tourid",
             "status_cols" => array(
                array(
                "cols_name" => "importstatus",
                "status_change" => array("0"=>"No","1"=>"Yes")
                )
                ),
            // "editlink" => array("/organization/route/addroute/id/#pattern#/edit/yes/","#pattern#"),
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
        
        // pass the data in summary_showdatagrid() function & create a final variable for view
        $this->view->pagegridshow =  $pagingshow->summary_showdatagrid($data_arr);
        $this->view->addScriptPath(APPLICATION_PATH."/theme/default/datagrid/");
    }
     /**
     * @name       upsyncAction
     * @since      13/01/2015
     * @version    Release: 1
     * @author     Veena<v.nair@mirnah.com>
     * @copyright  Mirnah Technologies
     * @param
     *
     * This action is use for the down syncing transaction data
     */
    public function upsyncAction()
    {
        $this->view->params = $params = $this->getRequest()->getParams();
        $this->view->formdata = $formdata = $this->_request->getPost();
        $this->view->title = $this->translate->_('Export Data');
        $process = FALSE;
        if($process == TRUE) {
            $Common_NameSpace = new Zend_Session_Namespace('Common');
            $last_url = htmlspecialchars($_SERVER['HTTP_REFERER']);
            $end_last_url = explode('/',$last_url);
            if(end($end_last_url) == 'upsync' || strpos($last_url,'upsync') || strpos($last_url,'/upsync/' )) {
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
            $dateValue = date("Y-m-d",strtotime(str_replace('/', '-', $Common_NameSpace->tdate)));
            $param_array = array();
            $param_array[1] = $dateValue;
            $result = $this->SFA_Comman->executequery('CALL sp_int_export_getroutecodes()',$param_array);
            $this->Export_Data = new SFA_upSyncData();
            foreach($result[0][0] as $tourID) {
                $this->Export_Data->exportData($tourID);
            }
        } else {
            if($formdata["hdDelete"]==1) {
                $this->Export_Data = new SFA_upSyncData();
                foreach($formdata['chk'] as $tourID) {
                    $this->Export_Data->exportData($tourID);
                }
            }
        }

        $cols_array = array('rm.memo1','rm.routename', 'sm.alternatesalesmancode', 'DATE_FORMAT(sed.routestartdate,"%d-%m-%Y") AS routestartdate', 'DATE_FORMAT(sed.routeenddate,"%d-%m-%Y") AS routeenddate','IF(sed.routeclosed = 0,"No","Yes") AS routeclosed','rm.memo1 AS edit_del_primary_id');
        $columns_show = array($this->translate->_('Tour ID'),$this->translate->_('Route Name'),$this->translate->_('Salesman Code'),$this->translate->_('Route Startdate'),$this->translate->_('Route Enddate'),$this->translate->_('Route Close'));
        $Common_NameSpace = new Zend_Session_Namespace('Common');
        $last_url = htmlspecialchars($_SERVER['HTTP_REFERER']);
        $end_last_url = explode('/',$last_url);
        if(end($end_last_url) == 'upsync' || strpos($last_url,'upsync') || strpos($last_url,'/upsync/' )) {
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
        $additional_where_condition = array();
        $additional_where_condition[] = " (DATE(sed.routestartdate) = \'".date("Y-m-d",strtotime(str_replace('/', '-', $Common_NameSpace->tdate)))."\')";
        // prepare the configuration for grid
        $pagingparams = array(
                "show_grid_heading" => true,
                "grid_heading_message" => $this->translate->_('Overview'),
                "show_records_per_page" => isset($formdata["show_records_per_page"])?$formdata["show_records_per_page"]:10,
                "show_searchbox" => true,
                "searchbox_value" => isset($formdata["grid_search_text"])?$formdata["grid_search_text"]:"",
                "show_selectbox" => true,
                "show_editlink" => false,
                "show_deletelink" => false,
                "show_deleteall" => false,
                "primaryid" => "rm.memo1",
                //"show_extralink" => true,
                //"extralink" => array(array("View","/".$params['module']."/".$params['controller']."/viewinventorysummary/id/#pattern#","#pattern#")),
                "nodata_message" => $this->translate->_('No Record(s) Found'),
                "fetch_columns_inquery" => $cols_array,
                "show_columns" => $columns_show,
                "additional_where" => $additional_where_condition
                );
        // create grid class object
        $pagingshow = new SFA_Paging($pagingparams);
        // call common function of grid class
        $get_return_vals = $pagingshow->commnfunc();
        // call the stored procedure for fetch the data
        $param_array = array();
        $param_array[1] = '1';
        $param_array[2] = '';
        $param_array[3] = $get_return_vals['order_columns_name'];
        $param_array[4] = $get_return_vals['order_type'];
        $param_array[5] = $get_return_vals['offset'];
        $param_array[6] = (int)$get_return_vals['show_records_per_page'];
        $param_array[7] = implode(", ",$pagingparams["fetch_columns_inquery"]);
        $param_array[8] = strlen($get_return_vals['where_condition'])>0?$get_return_vals['where_condition']:'';        
        // called stored procedure for counter
        $result = $this->SFA_Comman->executequery('CALL sp_int_import_upsyncpage(?,?,?,?,?,?,?,?)',$param_array,'');
        $data_arr["count"] = $result[0][0]['counter'];
        $data_arr["data"][0] = $result[1];
        // pass the data in summary_showdatagrid() function & create a final variable for view
        $this->view->pagegridshow =  $pagingshow->summary_showdatagrid($data_arr);
        $this->view->addScriptPath(APPLICATION_PATH."/theme/default/datagrid/");
    }
    function upsyncOrder() {
        $param_array = array();
        $param_array[1] = date('Y-m-d');
        $result = $this->SFA_Comman->executequery('CALL sp_int_export_getroutecodes()',$param_array);
        $this->Export_Order = new SFA_upSyncOrder();
        foreach($result[0][0] as $tourID) {
            $this->Export_Order->exportOrder($tourID);
        }
    }
}
?>