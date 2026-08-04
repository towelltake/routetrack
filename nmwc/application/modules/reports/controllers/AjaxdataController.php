<?php
/**
* @name       AjaxdataController
* @since      01-11-2012
* @version    Release: 1
* @author     PT <pankil@elantechnologies.com>
* @copyright  Elan Technologies
* @param
*
* This controller is manage report module.
*/
class Reports_AjaxdataController extends Reports_Library_Controller_Action_Abstract
{
     /**
    * @name       init
    * @since      01-11-2012
    * @version    Release: 9
    * @author     PT <pankil@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This is the default function for all Actions.
    *
    */
    protected $report_session;
    public function init()
    {
        $this->translate 	= Zend_Registry::get('Zend_Translate');
        $this->view->colan	= $this->translate->_('Colan');
        $this->SFA_Comman	= new SFA_Comman();
        
        $this->currentUser = SFA_Loginauth::getIdentity();	
        if(!isset($this->currentUser) || empty($this->currentUser))
        {
           // SFA_Message::setMsg($this->translate->_('Do Login'));
           // //$this->_helper->redirector("index", "index", "home");
			$url = $this->view->baseUrl();
			echo '<script type="text/javascript">window.location="'.$url.'";</script>';
			exit;
        }
        
        $this->sec_lang 	  = $this->view->sec_lang;
        $this->decimalplaces  = $this->view->decimalplaces	= $this->SFA_Comman->getdecimalplaces();
        $this->view->sec_lang = $this->SFA_Comman->getsecondlanguage();
        
        //$this->report_session		= new Zend_Session_Namespace('Re_customerageing');
    }
	
    /**
    * @name       useraccessgridAction
    * @since      01-11-2012
    * @version    Release: 1
    * @author     PT <pankil@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param   	
    *
    * This is the function for load value in useraccess grid.
    */
	public function useraccessgridAction()
	{
        try {
        $params = $this->getRequest()->getParams();
		
		// IF EXTRA PARAMS ARE REQUIRED
		$ex_param = "";
        $additional_where_condition = array();
		if(isset($params["key"]) && $params["key"]>0) {
			$ex_param = "/key/".$params["key"];
            if($params['key'] == 1)
            {
                $table_name = 'company';
                $additional_where_condition[] = "activestatus = 1";
                $columns_array 	= array('cmpycode AS id','CONCAT(cmpycode," -- ",`name`) AS val','CONCAT(cmpycode,"$$",`name`) as edit_del_primary_id');
            }
            //elseif($params['key'] == 2)
            //{
            //    $table_name = 'country';                
            //    $columns_array 	= array('countrycode AS id','CONCAT(countrycode," -- ",countryname) AS val','countrycode as edit_del_primary_id');
            //    $result = $this->SFA_Comman->executequery('CALL sp_combo_country()','','');
            //}
            elseif($params['key'] == 2)
            {
                $table_name = 'regionmaster';                
                $columns_array 	= array('regionmstcode AS id','CONCAT(regionmstcode," -- ",regionmstname) AS val','CONCAT(regionmstcode,"$$",regionmstname) as edit_del_primary_id');                
            }
            elseif($params['key'] == 3)
            {
                $table_name = 'depotmaster';
                $additional_where_condition[] = "activestatus = 1";
                $columns_array 	= array('depotcode AS id','CONCAT(depotcode," -- ",depotname) AS val','CONCAT(depotcode,"$$",depotname) as edit_del_primary_id');                
            }
            elseif($params['key'] == 4)
            {
                $table_name = 'areamaster';
                $additional_where_condition[] = "activestatus = 1";
                $columns_array 	= array('areacode AS id','CONCAT(areacode," -- ",areaname) AS val','CONCAT(areacode,"$$",areaname) as edit_del_primary_id');                
            }
            elseif($params['key'] == 5)
            {
                $table_name = 'subareamaster';
                $additional_where_condition[] = "activestatus = 1";
                $columns_array 	= array('subareacode AS id','CONCAT(subareacode," -- ",subareaname) AS val','CONCAT(subareacode,"$$",subareaname) as edit_del_primary_id');
            }
            elseif($params['key'] == 6)
            {
                $table_name = 'routemaster';
                $additional_where_condition[] = "activestatus = 1";
                $additional_where_condition[] = "routetmpl = 0";
                $columns_array 	= array('routecode AS id','CONCAT(routecode," -- ",routename) AS val','CONCAT(routecode,"$$",routename) as edit_del_primary_id');
            }
			elseif($params['key'] == 7)
            {
                $table_name = 'routecategory';                
                $columns_array 	= array('routecatcode AS id','CONCAT(routecatcode," -- ",routecatname) AS val','CONCAT(routecatcode,"$$",routecatname) as edit_del_primary_id');
            }
		}
        
        
		$columns_show  	= array($this->translate->_('Code'),$this->translate->_('Name'));
		
        
		// ARRAY FOR GRID PAGINATION
		$pagingparams = array(
					 "show_grid_heading" => false,
					 "grid_heading_message" => $this->translate->_('Overview'),
					 "show_records_per_page" => isset($formdata["show_records_per_page"])?$formdata["show_records_per_page"]:10000000000,
					 "show_searchbox" => false,
					 "show_selectbox" => true,
					 "show_editlink" => false,
					 "show_deletelink" => false,
					 "show_deleteall" => false,
                     "selected_list" => $checked,
					 "primaryid" => "edit_del_primary_id",
					 "currentlink" => array("/".$params['module']."/".$params['controller']."/".$params['action'].$ex_param),
					 "nodata_message" => $this->translate->_('No Record(s) Found'),
					 "fetch_columns_inquery" => $columns_array,
					 "show_columns" => $columns_show,
					 "additional_where" => $additional_where_condition,
					 );
        
		$pagingshow = new SFA_Ajaxpaging($pagingparams);
		
		// call common function of grid class
		$get_return_vals = $pagingshow->commnfunc();
		
		// call the stored procedure for fetch the data
		$param_array 	= array();
		$param_array[1] = '1';
		$param_array[2] = $get_return_vals['order_columns_name'];
		$param_array[3] = $get_return_vals['order_type'];
		$param_array[4] = $get_return_vals['offset'];
		$param_array[5] = (int)$get_return_vals['show_records_per_page'];
		$param_array[6] = implode(", ",$pagingparams["fetch_columns_inquery"]);
		$param_array[7] = strlen($get_return_vals['where_condition'])>0?$get_return_vals['where_condition']:'';
        $param_array[8] = $table_name;
	
		
		// called stored procedure for counter		
        $result = $this->SFA_Comman->executequery('CALL sp_get_admin_security_adduseraccesscode(?,?,?,?,?,?,?,?)',$param_array,'');
		$data_arr["count"] 	    = $result[0][0]['counter'];	
		$data_arr["data"][0] 	= $result[1];
		
		$this->view->pagegridshow =  $pagingshow->summary_showdatagrid($data_arr);
		$this->view->addScriptPath(APPLICATION_PATH."/theme/default/datagrid/");
	
		$this->render("ajaxgrid");
        } catch(Zend_Exception $e) {
            echo $e->getMessage();exit;
        }
	}
	
	
	/*For Items*/
	public function itemgridAction()
	{
        try {
        $params = $this->getRequest()->getParams();
		
		// IF EXTRA PARAMS ARE REQUIRED
		$ex_param = "";
        $additional_where_condition = array();
		if(isset($params["key"]) && $params["key"]>0) {
			$ex_param = "/key/".$params["key"];
            if($params['key'] == 1)
            {
                $table_name = 'companygroup';
                $additional_where_condition[] = "activestatus = 1";
                $columns_array 	= array('companygroupcode AS id','CONCAT(companygroupcode," -- ",`description`) AS val','CONCAT(companygroupcode,"$$",`description`) as edit_del_primary_id');
            }
            elseif($params['key'] == 2)
            {
                $table_name = 'majorcategory';                
                $columns_array 	= array('majorcategorycode AS id','CONCAT(majorcategorycode," -- ",description) AS val','CONCAT(majorcategorycode,"$$",description) as edit_del_primary_id');                
            }
            elseif($params['key'] == 3)
            {
                $table_name = 'submajorcategory';
                $additional_where_condition[] = "activestatus = 1";
                $columns_array 	= array('submajorcategorycode AS id','CONCAT(submajorcategorycode," -- ",description) AS val','CONCAT(submajorcategorycode,"$$",description) as edit_del_primary_id');                
            }
            elseif($params['key'] == 4)
            {
                $table_name = 'itemgroup';
                $additional_where_condition[] = "activestatus = 1";
                $columns_array 	= array('itemgroupcode AS id','CONCAT(itemgroupcode," -- ",itemgroupname) AS val','CONCAT(itemgroupcode,"$$",itemgroupname) as edit_del_primary_id');                
            }
            elseif($params['key'] == 5)
            {
                $table_name = 'itemmaster';
                $additional_where_condition[] = "activeitem = 1";
                $columns_array 	= array('actualitemcode AS id','CONCAT(actualitemcode," -- ",itemshortdescription) AS val','CONCAT(actualitemcode,"$$",itemshortdescription) as edit_del_primary_id');                
            }
           
		}
        
        
		$columns_show  	= array($this->translate->_('Code'),$this->translate->_('Name'));
		
        
		// ARRAY FOR GRID PAGINATION
		$pagingparams = array(
					 "show_grid_heading" => false,
					 "grid_heading_message" => $this->translate->_('Overview'),
					 "show_records_per_page" => isset($formdata["show_records_per_page"])?$formdata["show_records_per_page"]:10000000000,
					 "show_searchbox" => false,
					 "show_selectbox" => true,
					 "show_editlink" => false,
					 "show_deletelink" => false,
					 "show_deleteall" => false,
                     "selected_list" => $checked,
					 "primaryid" => "edit_del_primary_id",
					 "currentlink" => array("/".$params['module']."/".$params['controller']."/".$params['action'].$ex_param),
					 "nodata_message" => $this->translate->_('No Record(s) Found'),
					 "fetch_columns_inquery" => $columns_array,
					 "show_columns" => $columns_show,
					 "additional_where" => $additional_where_condition,
					 );
        
		$pagingshow = new SFA_Ajaxpagingitem($pagingparams);
		
		// call common function of grid class
		$get_return_vals = $pagingshow->commnfunc();
		
		// call the stored procedure for fetch the data
		$param_array 	= array();
		$param_array[1] = '1';
		$param_array[2] = $get_return_vals['order_columns_name'];
		$param_array[3] = $get_return_vals['order_type'];
		$param_array[4] = $get_return_vals['offset'];
		$param_array[5] = (int)$get_return_vals['show_records_per_page'];
		$param_array[6] = implode(", ",$pagingparams["fetch_columns_inquery"]);
		$param_array[7] = strlen($get_return_vals['where_condition'])>0?$get_return_vals['where_condition']:'';
        $param_array[8] = $table_name;
	
		
		// called stored procedure for counter		
        $result = $this->SFA_Comman->executequery('CALL sp_get_admin_security_adduseraccesscode(?,?,?,?,?,?,?,?)',$param_array,'');
		$data_arr["count"] 	    = $result[0][0]['counter'];	
		$data_arr["data"][0] 	= $result[1];
		
		$this->view->pagegridshow =  $pagingshow->summary_showdatagrid($data_arr);
		$this->view->addScriptPath(APPLICATION_PATH."/theme/default/datagrid_item/");
	
		$this->render("ajaxgrid");
        } catch(Zend_Exception $e) {
            echo $e->getMessage();exit;
        }
	}
	
	/*For Customer*/
	public function customergridAction()
	{
        try {
        $params = $this->getRequest()->getParams();
		
		// IF EXTRA PARAMS ARE REQUIRED
		$ex_param = "";
        $additional_where_condition = array();
		if(isset($params["key"]) && $params["key"]>0) {
			$ex_param = "/key/".$params["key"];
            if($params['key'] == 1)
            {
                $table_name = 'categorymaster';
                $additional_where_condition[] = "activestatus = 1";
                $columns_array 	= array('categoryid AS id','CONCAT(categoryid," -- ",COALESCE(categoryname,"")) AS val','CONCAT(categoryid,"$$",COALESCE(categoryname,"")) as edit_del_primary_id');
            }
            elseif($params['key'] == 2)
            {
                $table_name = 'channelmaster';                
                $columns_array 	= array('channelcode AS id','CONCAT(channelcode," -- ",COALESCE(channelname,"")) AS val','CONCAT(channelcode,"$$",COALESCE(channelname,"")) as edit_del_primary_id');                
            }
            elseif($params['key'] == 3)
            {
                $table_name = 'customermaster';
                $additional_where_condition[] = "activecustomer = 1";
                $columns_array 	= array('customercode AS id','CONCAT(customercode," -- ",customername) AS val','CONCAT(customercode,"$$",customername) as edit_del_primary_id');                
            }
		}
        
		$columns_show  	= array($this->translate->_('Code'),$this->translate->_('Name'));
		
        
		// ARRAY FOR GRID PAGINATION
		$pagingparams = array(
					 "show_grid_heading" => false,
					 "grid_heading_message" => $this->translate->_('Overview'),
					 "show_records_per_page" => isset($formdata["show_records_per_page"])?$formdata["show_records_per_page"]:10000000000,
					 "show_searchbox" => false,
					 "show_selectbox" => true,
					 "show_editlink" => false,
					 "show_deletelink" => false,
					 "show_deleteall" => false,
                     "selected_list" => $checked,
					 "primaryid" => "edit_del_primary_id",
					 "currentlink" => array("/".$params['module']."/".$params['controller']."/".$params['action'].$ex_param),
					 "nodata_message" => $this->translate->_('No Record(s) Found'),
					 "fetch_columns_inquery" => $columns_array,
					 "show_columns" => $columns_show,
					 "additional_where" => $additional_where_condition,
					 );
        
		$pagingshow = new SFA_Ajaxpagingcustomer($pagingparams);
		
		// call common function of grid class
		$get_return_vals = $pagingshow->commnfunc();
		
		// call the stored procedure for fetch the data
		$param_array 	= array();
		$param_array[1] = '1';
		$param_array[2] = $get_return_vals['order_columns_name'];
		$param_array[3] = $get_return_vals['order_type'];
		$param_array[4] = $get_return_vals['offset'];
		$param_array[5] = (int)$get_return_vals['show_records_per_page'];
		$param_array[6] = implode(", ",$pagingparams["fetch_columns_inquery"]);
		$param_array[7] = strlen($get_return_vals['where_condition'])>0?$get_return_vals['where_condition']:'';
        $param_array[8] = $table_name;
	
		
		// called stored procedure for counter		
        $result = $this->SFA_Comman->executequery('CALL sp_get_admin_security_adduseraccesscode(?,?,?,?,?,?,?,?)',$param_array,'');
		$data_arr["count"] 	    = $result[0][0]['counter'];	
		$data_arr["data"][0] 	= $result[1];
		
		$this->view->pagegridshow =  $pagingshow->summary_showdatagrid($data_arr);
		$this->view->addScriptPath(APPLICATION_PATH."/theme/default/datagrid_customer/");
	
		$this->render("ajaxgrid");
        } catch(Zend_Exception $e) {
            echo $e->getMessage();exit;
        }
	}
}
