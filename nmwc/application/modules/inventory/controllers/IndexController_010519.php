<?php
/**
* @name       IndexController
* @since
* @version    Release: 1
* @author     HD <hiren.d@elantechnologies.com>
* @copyright  Elan Technologies
* @param
*
* This controller is manage user inventory module.
*/
class Inventory_IndexController extends Inventory_Library_Controller_Action_Abstract
{
    public $sec_lang 		= '';
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
		$this->translate = Zend_Registry::get('Zend_Translate');
		
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
		$this->view->general	= $this->translate->_('General');
		$this->view->setting1	= $this->translate->_('Settings 1');
		$this->view->details	= $this->translate->_('Details');
		$this->view->required	= $this->translate->_('Required');
		$this->view->colan	= $this->translate->_('Colan');
        

	//$this->Inventory_Model 	= new SFA_Model_Inventory();
        $this->common_model = new SFA_Model_Index();
        $this->SFA_Comman 	= new SFA_Comman();
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
    
    
    /**
    * @name       Index
    * @since
    * @version    Release: 1
    * @author     HD <hiren.d@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This Action is for register user in website
    */
    public function indexAction()
    {

    }
    /**
    * @name       companygroupAction
    * @since      28-12-2011
    * @version    Release: 1
    * @author     HD <hiren.d@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This action is use for the display company group details
    */
    public function companygroupAction()
    {
        $this->view->formdata = $formdata = $this->_request->getPost();

        if($formdata["hdDelete"]==1)
		{
			$ids = implode(',',$formdata['chk']);
			$param_array 	= array();
			$param_array[1]	= $ids;
			$param_array[2]	= $this->currentUser->username;
			
			$result 	= $this->SFA_Comman->executequery('CALL sp_delete_inventory_index_companygroup(?,?)',$param_array,'');
			
			if($result[0][0]['deleted_id'] =='')
			{
			$ids		= explode(',',$ids);
			$checked 	= $ids;
			
			SFA_Message::setErrorMsg($this->translate->_('Record Already In use'));
			}
			else
			{
			$deleted_id 	= explode(',',$result[0][0]['deleted_id']);
			$ids		= explode(',',$ids);
			$checked 	= array_diff($ids,$deleted_id);
			
			if(count($ids) != count($deleted_id)){
				SFA_Message::setErrorMsg($this->translate->_('Record Already In use'));
			}
			
			SFA_Message::setMsg($this->translate->_('Delete Record'));
			}
		}

        //declare view variables
		$this->view->title	= $this->translate->_('Company Group');

        //variable declaration for grid title
		$code 			= $this->translate->_('Code');	
		
		$cols_array 	= array('grp.description','grp.arbdescription','name','grp.activestatus as activestatus','grp.companygroupcode as edit_del_primary_id');
		$columns_show 	=  array($this->translate->_('Name'),$this->translate->_('Name ('.$this->sec_lang.')'),$this->translate->_('Parent Company'),$this->translate->_('Status'));
		
		if($this->css == 'ar_') {
			$cols_array[2]	= 'arbcompanyname';		
		}
		
		// prepare the configuration for grid
		$pagingparams = array(
				"show_grid_heading" => true,
				"grid_heading_message" => $this->translate->_('Overview'),
				"pagename" => $this->translate->_('Company Group'),
				"show_records_per_page" => isset($formdata["show_records_per_page"])?$formdata["show_records_per_page"]:10,
				"show_searchbox" => true,
				"searchbox_value" => isset($formdata["grid_search_text"])?$formdata["grid_search_text"]:"",
				"show_selectbox" => true,
				"show_editlink" => true,
				"selected_list" => $checked,
				"show_deletelink" => false,			
				"show_deleteall" => false,
				"primaryid" => "companygroupcode",
				"status_cols" => array(
							   array(
							   "cols_name" => "activestatus",
							   "status_change" => array("0"=>"Inactive","1"=>"Active")
							   )
							   ),
				"editlink" => array("/inventory/index/addcompanygroup/id/#pattern#/edit/yes/","#pattern#"),
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
		$param_array	= array();
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
		$result = $this->SFA_Comman->executequery('CALL sp_get_inventory_index_companygroup(?,?,?,?,?,?,?,?)',$param_array,'',$downloadCSV,$printData,2,$pagingparams);
		$data_arr["count"]	= $result[0][0]['counter'];	
		$data_arr["data"][0] 	= $result[1];
		
		// pass the data in summary_showdatagrid() function & create a final variable for view
		$this->view->pagegridshow =  $pagingshow->summary_showdatagrid($data_arr);
		
		$this->view->addScriptPath(APPLICATION_PATH."/theme/default/datagrid/");
    }
    /**
    * @name       addcompanygroup
    * @since      28-12-2011
    * @version    Release: 1
    * @author     HD <hiren.d@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This action is use for the add company group details
    */
    public function addcompanygroupAction()
    {
	$this->view->params = $params = $this->getRequest()->getParams();
        $this->view->formdata = $formdata = $this->_request->getPost();
	
		
	
        $this->view->returnUrl = $_SERVER["HTTP_REFERER"];
	
	if(count($formdata) > 0 && $formdata['txtcode'] !='' && $formdata['txtname'] !='' && $formdata['ddlparent_com'] !='' )
	{
	    $param_array 	= array();	    
	    $param_array[1]	= $formdata['txtaltcode'];
	    $param_array[2]	= $formdata['txtname'];
	    $param_array[3]	= $formdata['txtnamearb'];
	    $param_array[4]	= $formdata['ddlparent_com'];
	    $param_array[5]	= $formdata['ddlstatus'];	    
	    $param_array[6]	= $this->currentUser->username;
	    
	    if($formdata['hdnid'] > 0)
	    {
		$param_array[7] = $formdata['hdnid'];		
		$last_id = $this->SFA_Comman->executequery('CALL sp_edit_inventory_index_addcompanygroup(?,?,?,?,?,?)',$param_array,'');
		SFA_Message::setMsg($this->translate->_('Update Record'));		
	    }
	    else
	    {
		$last_id = $this->SFA_Comman->executequery('CALL sp_add_inventory_index_addcompanygroup(?,?,?,?,?)',$param_array,'');
		SFA_Message::setMsg($this->translate->_('New Record'));
	    }
	    if($formdata["returnUrl"]!="")
		$this->_redirect($formdata["returnUrl"]);
	    else
		 $this->_helper->redirector('companygroup', 'index', 'inventory');
	}
	elseif($params['id'] > 0)
	{
	    $result = $this->SFA_Comman->executequery('CALL sp_get_inventory_index_addcompanygroup(?)',$params['id'],'');
	    $this->view->formdata 	= $result[0][0];
	    $this->view->company_data 	= $result[1];
	    $this->view->formdata['createddate'] = date("d-m-Y",strtotime($result[0][0]['cdat']));	    
	}
	else
	{
	    $result = $this->SFA_Comman->executequery('CALL sp_get_inventory_index_addcompanygroup(?)','0','');	    
	    $this->view->formdata['companygroupcode'] = $result[0][0]['Auto_increment'];
	    $this->view->company_data 	= $result[1];
	}
	
    }

    /**
    * @name       majorcatAction
    * @since      28-12-2011
    * @version    Release: 1
    * @author     HD <hiren.d@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This action is use for the display  major category details
    */
    public function majorcatAction()
    {
         //declare view variables
        $this->view->formdata = $formdata = $this->_request->getPost();
        $this->view->title	= $this->translate->_('Major Category');

        if($formdata["hdDelete"]==1)
		{
			$ids = implode(',',$formdata['chk']);
			$param_array 	= array();
			$param_array[1]	= $ids;
			$param_array[2]	= $this->currentUser->username;
			
			$result 	= $this->SFA_Comman->executequery('CALL sp_delete_inventory_index_majorcat(?,?)',$param_array,'');
			
			if($result[0][0]['deleted_id'] =='')
			{
			$ids		= explode(',',$ids);
			$checked 	= $ids;
			
			SFA_Message::setErrorMsg($this->translate->_('Record Already In use'));
			}
			else
			{
			$deleted_id 	= explode(',',$result[0][0]['deleted_id']);
			$ids		= explode(',',$ids);
			$checked 	= array_diff($ids,$deleted_id);
			
			if(count($ids) != count($deleted_id)){
				SFA_Message::setErrorMsg($this->translate->_('Record Already In use'));
			}
			
			SFA_Message::setMsg($this->translate->_('Delete Record'));
			}
		}
	
		$cols_array 	= array('cat.majorcategorycode','cat.description AS cat_description','grp.description as grp_description','cat.activestatus as activestatus');
		$columns_show 	= array($this->translate->_('Code'),$this->translate->_('Major Category'),$this->translate->_('Company Group'),$this->translate->_('Status'));
		
		if($this->css == 'ar_') {
			$cols_array[1]	= 'cat.arbdescription AS cat_description';
			$cols_array[2]	= 'grp.arbdescription as grp_description';
		}
		// prepare the configuration for grid
		$pagingparams = array(
				"show_grid_heading" => true,
				"grid_heading_message" => $this->translate->_('Overview'),
				"pagename" => $this->translate->_('Major Category'),
				"show_records_per_page" => isset($formdata["show_records_per_page"])?$formdata["show_records_per_page"]:10,
				"show_searchbox" => true, "searchbox_value" => isset($formdata["grid_search_text"])?$formdata["grid_search_text"]:"",
				"show_selectbox" => true,
				"show_editlink" => true,
				"selected_list" => $checked,
				"show_deletelink" => false,			
				"show_deleteall" => false,
				"primaryid" => "majorcategorycode",
				"status_cols" => array(
							array(
								"cols_name" => "activestatus",
								"status_change" => array("0"=>"Inactive","1"=>"Active")
							   )
							),
				"editlink" => array("/inventory/index/addmajorcat/id/#pattern#/edit/yes/","#pattern#"),
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
		$param_array	= array();
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
		$result = $this->SFA_Comman->executequery('CALL sp_get_inventory_index_majorcat(?,?,?,?,?,?,?,?)',$param_array,'',$downloadCSV,$printData,2,$pagingparams);
		//SFA_Comman::pre($result);
		$data_arr["count"]	= $result[0][0]['counter'];	
		$data_arr["data"][0] 	= $result[1];
		
		// pass the data in summary_showdatagrid() function & create a final variable for view
		$this->view->pagegridshow =  $pagingshow->summary_showdatagrid($data_arr);
		
		$this->view->addScriptPath(APPLICATION_PATH."/theme/default/datagrid/");        
    }
    /**
    * @name       addmajorcat
    * @since      29-12-2011
    * @version    Release: 1
    * @author     HD <hiren.d@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This action is use for the add major category details
    */
    public function addmajorcatAction()
    {
        //Declare view variables
	$this->view->params 	= $params = $this->getRequest()->getParams();
        $this->view->formdata 	= $formdata = $this->_request->getPost();
	
        
	$this->view->returnUrl = $_SERVER["HTTP_REFERER"];
	
	if(count($formdata) > 0 && $formdata['txtcode'] !='' && $formdata['txtcatname'] !='')
	{
	    $param_array 	= array();	    
	    $param_array[1]	= $formdata['txtaltcode'];
	    $param_array[2]	= $formdata['ddlcomgrp'];
	    $param_array[3]	= $formdata['txtcatname'];
	    $param_array[4]	= $formdata['txtcatnamearb'];	    
	    $param_array[5]	= $formdata['ddlstatus'];	    
	    $param_array[6]	= $this->currentUser->username;
	    
	    if($formdata['hdnid'] > 0)
	    {
		$param_array[7] = $formdata['hdnid'];		
		$last_id = $this->SFA_Comman->executequery('CALL sp_edit_inventory_index_addmajorcat(?,?,?,?,?,?)',$param_array,'');
		SFA_Message::setMsg($this->translate->_('Update Record'));		
	    }
	    else
	    {
		$last_id = $this->SFA_Comman->executequery('CALL sp_add_inventory_index_addmajorcat(?,?,?,?,?)',$param_array,'');
		SFA_Message::setMsg($this->translate->_('New Record'));
	    }
	    if($formdata["returnUrl"]!="")
		$this->_redirect($formdata["returnUrl"]);
	    else
		$this->_helper->redirector('majorcat', 'index', 'inventory');
	}
	elseif($params['id'] > 0)
	{
	    $result = $this->SFA_Comman->executequery('CALL sp_get_inventory_index_addmajorcat(?)',$params['id'],'');
	    $this->view->formdata 		= $result[0][0];
	    $this->view->companygrp_data 	= $result[1];
	    $this->view->formdata['createddate'] = date("d-m-Y",strtotime($result[0][0]['cdat']));	    
	}
	else
	{
	    $result = $this->SFA_Comman->executequery('CALL sp_get_inventory_index_addmajorcat(?)','0','');	    
	    $this->view->formdata['majorcategorycode'] 	= $result[0][0]['Auto_increment'];
	    $this->view->companygrp_data		= $result[1];
	}
    }
    /**
    * @name       submajorcatAction
    * @since      28-12-2011
    * @version    Release: 1
    * @author     HD <hiren.d@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This action is use for the display sub major category details
    */
    public function submajorcatAction()
    {
        //declare view variables
        $this->view->formdata = $formdata = $this->_request->getPost();
        $this->view->title	= $this->translate->_('Sub Major Category');
	
	
	if($formdata["hdDelete"]==1)
	{
	    $ids = implode(',',$formdata['chk']);
	    $param_array 	= array();
	    $param_array[1]	= $ids;
	    $param_array[2]	= $this->currentUser->username;
	    
	    $result 	= $this->SFA_Comman->executequery('CALL sp_delete_inventory_index_submajorcat(?,?)',$param_array,'');
	    
	    if($result[0][0]['deleted_id'] =='')
	    {
		$ids		= explode(',',$ids);
		$checked 	= $ids;
		
		SFA_Message::setErrorMsg($this->translate->_('Record Already In use'));
	    }
	    else
	    {
		$deleted_id 	= explode(',',$result[0][0]['deleted_id']);
		$ids		= explode(',',$ids);
		$checked 	= array_diff($ids,$deleted_id);
		
		if(count($ids) != count($deleted_id)){
		    SFA_Message::setErrorMsg($this->translate->_('Record Already In use'));
		}
		
		SFA_Message::setMsg($this->translate->_('Delete Record'));
	    }
	}        
	
	$cols_array 	= array('subcat.submajorcategorycode','subcat.description AS subcat_description','cat.description as cat_description','subcat.activestatus');
	$columns_show 	= array($this->translate->_('Code'),$this->translate->_('Sub Major Category'),$this->translate->_('Major Category'),$this->translate->_('Status'));
	
	if($this->css == 'ar_') {
		$cols_array[1]	= 'subcat.arbdescription AS subcat_description';
		$cols_array[2]	= 'cat.arbdescription as cat_description';
	}
		
	// prepare the configuration for grid
	$pagingparams = array(
			"show_grid_heading" => true,
			"grid_heading_message" => $this->translate->_('Overview'),
			"pagename" => $this->translate->_('Sub Major Category'),
			"show_records_per_page" => isset($formdata["show_records_per_page"])?$formdata["show_records_per_page"]:10,
			"show_searchbox" => true,
			"searchbox_value" => isset($formdata["grid_search_text"])?$formdata["grid_search_text"]:"",
			"show_selectbox" => true,
			"show_editlink" => true,
			"selected_list" => $checked,
			"show_deletelink" => false,			
			"show_deleteall" => false,
			"primaryid" => "submajorcategorycode",
			"status_cols" => array(
						array(
						    "cols_name" => "activestatus",
						    "status_change" => array("0"=>"Inactive","1"=>"Active")
						   )
						),
			"editlink" => array("/inventory/index/addsubmajorcat/id/#pattern#/edit/yes/","#pattern#"),
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
	$param_array	= array();
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
	$result = $this->SFA_Comman->executequery('CALL sp_get_inventory_index_submajorcat(?,?,?,?,?,?,?,?)',$param_array,'',$downloadCSV,$printData,2,$pagingparams);
	//SFA_Comman::pre($result);
	$data_arr["count"]	= $result[0][0]['counter'];	
	$data_arr["data"][0] 	= $result[1];
	
	// pass the data in summary_showdatagrid() function & create a final variable for view
	$this->view->pagegridshow =  $pagingshow->summary_showdatagrid($data_arr);
	
	$this->view->addScriptPath(APPLICATION_PATH."/theme/default/datagrid/"); 
    }
    /**
    * @name       addsubmajorcat
    * @since      29-12-2011
    * @version    Release: 1
    * @author     HD <hiren.d@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This action is use for the add sub major category
    */
    public function addsubmajorcatAction()
    {
        //declare view variables
	$this->view->params = $params = $this->getRequest()->getParams();
        $this->view->formdata = $formdata = $this->_request->getPost();
	
	
	if(count($formdata) > 0 && $formdata['txtcode'] !='' && $formdata['txtname'] !='' && $formdata['ddlmajorcat'] !='')
	{
	    $param_array 	= array();	    
	    $param_array[1]	= $formdata['txtaltcode'];
	    $param_array[2]	= $formdata['ddlmajorcat'];
	    $param_array[3]	= $formdata['txtname'];
	    $param_array[4]	= $formdata['txtarbname'];	    
	    $param_array[5]	= $formdata['ddlstatus'];	    
	    $param_array[6]	= $this->currentUser->username;
	    
	    if($formdata['hdnid'] > 0)
	    {
		$param_array[7] = $formdata['hdnid'];		
		$last_id = $this->SFA_Comman->executequery('CALL sp_edit_inventory_index_addsubmajorcat(?,?,?,?,?,?)',$param_array,'');
		SFA_Message::setMsg($this->translate->_('Update Record'));		
	    }
	    else
	    {
		$last_id = $this->SFA_Comman->executequery('CALL sp_add_inventory_index_addsubmajorcat(?,?,?,?,?)',$param_array,'');
		SFA_Message::setMsg($this->translate->_('New Record'));
	    }
	    if($formdata["returnUrl"]!="")
		$this->_redirect($formdata["returnUrl"]);
	    else
		$this->_helper->redirector('submajorcat', 'index', 'inventory');
	}
	elseif($params['id'] > 0)
	{
	    $result = $this->SFA_Comman->executequery('CALL sp_get_inventory_index_addsubmajorcat(?)',$params['id'],'');	    
	    $this->view->formdata 	= $result[0][0];
	    $this->view->majorcat_data	= $result[1];
	    $this->view->formdata['createddate'] = date("d-m-Y",strtotime($result[0][0]['cdat']));	    
	}
	else
	{
	    $result = $this->SFA_Comman->executequery('CALL sp_get_inventory_index_addsubmajorcat(?)','0','');	    
	    $this->view->formdata['submajorcategorycode'] = $result[0][0]['Auto_increment'];
	    $this->view->majorcat_data	= $result[1];
	}
    }
    /**
    * @name       itemgrpAction
    * @since      28-12-2011
    * @version    Release: 1
    * @author     HD <hiren.d@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This action is use for the display item group details
    */
    public function itemgrpAction()
    {
		$this->view->params = $params = $this->getRequest()->getParams();
		$this->view->formdata = $formdata = $this->_request->getPost();
		
		if($formdata["hdDelete"]==1)
		{
			$ids = implode(',',$formdata['chk']);
			$param_array 	= array();
			$param_array[1]	= $ids;
			$param_array[2]	= $this->currentUser->username;
			
			$result 	= $this->SFA_Comman->executequery('CALL sp_delete_inventory_index_itemgroup(?,?)',$param_array,'');
			
			if($result[0][0]['deleted_id'] =='')
			{
			$ids		= explode(',',$ids);
			$checked 	= $ids;
			
			SFA_Message::setErrorMsg($this->translate->_('Record Already In use'));
			}
			else
			{		
			$deleted_id 	= explode(',',$result[0][0]['deleted_id']);
			$ids		= explode(',',$ids);
			$checked 	= array_diff($ids,$deleted_id);
			
			if(count($ids) != count($deleted_id)){
				SFA_Message::setErrorMsg($this->translate->_('Record Already In use'));
			}
			
			SFA_Message::setMsg($this->translate->_('Delete Record'));
			}
		}
		
		$cols_array = array('item.itemgroupcode','item.itemgroupname AS itemgroupname','sub.description AS description','item.activestatus');
		if($this->css == 'ar_')
		{
			$cols_array[1]	= 'item.arbitemgroup AS itemgroupname';
			$cols_array[2]	= 'sub.arbdescription AS description';
		}	
	
		//variable declaration for grid title
		$columns_show =  array($this->translate->_('Item Group Code'),
				$this->translate->_('Item Group'),
				$this->translate->_('Sub Major Category'),
				$this->translate->_('Status'));
	
		
	
		// prepare the configuration for grid
		$pagingparams = array(
				"show_grid_heading" => true,
				"grid_heading_message" => $this->translate->_('Overview'),
				"pagename" => $this->translate->_('Item Group'),
				"show_records_per_page" => isset($formdata["show_records_per_page"])?$formdata["show_records_per_page"]:10,
				"show_searchbox" => true,
				"searchbox_value" => isset($formdata["grid_search_text"])?$formdata["grid_search_text"]:"",
				"show_selectbox" => true,
				"show_editlink" => true,
				"show_deletelink" => false,
				"show_deleteall" => false,
				"status_cols" => array(
							array(
								"cols_name" => "activestatus",
								"status_change" => array("0"=>"Inactive","1"=>"Active")
								)
							),
				"primaryid" => "itemgroupcode",
				"editlink" => array("/inventory/index/additemgrp/id/#pattern#/edit/yes/","#pattern#"),
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
	
		// call the stored procedure for fetch the data
		$param_array	= array();
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
		$result = $this->SFA_Comman->executequery('CALL sp_get_inventory_index_itemgrp(?,?,?,?,?,?,?,?)',$param_array,'',$downloadCSV,$printData,2,$pagingparams);
	
		$data_arr["count"] 	= $result[0][0]['counter'];	
		$data_arr["data"][0] 	= $result[1];
		//SFA_Comman::pre($result);
	
		/*print_r($data_arr["data"]);
		die;*/
	
		// pass the data in summary_showdatagrid() function & create a final variable for view
		$this->view->pagegridshow =  $pagingshow->summary_showdatagrid($data_arr);
	
		$this->view->addScriptPath(APPLICATION_PATH."/theme/default/datagrid/");
    }
    /**
    * @name       additemgrp
    * @since      29-12-2011
    * @version    Release: 1
    * @author     HD <hiren.d@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This action is use for the add major category
    */
    public function additemgrpAction()
    {
        //View variable declaration
		$this->view->params = $params = $this->getRequest()->getParams();
        $this->view->formdata = $formdata = $this->_request->getPost();
		
		$this->view->select 	= $this->translate->_('Select');
		$this->view->missonefld	= $this->translate->_('Missed One Field');
		$this->view->youmissed	= $this->translate->_('You Missed');
		$this->view->highlated	= $this->translate->_('Fields. They have been highlighted.');
		$this->view->created_dt	= $this->translate->_('Created Date');
	
		$this->view->backUrl = $_SERVER["HTTP_REFERER"];
		
			if($formdata['ddlsubmajcat'] && $formdata['txtname'])
		{
				$param_array = array();
				$param_array[1]   =  trim($formdata['txtcode']);  //ItemGroupCode
				$param_array[2]   =  trim($formdata["txtaltcode"]); // AlternateItemGroupCode
				$param_array[3]   =  trim($formdata["ddlsubmajcat"]);  // SubMajorCategoryCode
				$param_array[4]   =  trim($formdata["txtname"]);  // ItemGroupName
				$param_array[5]   =  trim($formdata["txtnamearb"]);  // ARBItemGroup
				$param_array[6]   =  $formdata["ddlstatus"];  // ActiveStatus
	
	
			if($formdata['hdnid'] > 0 && $formdata['txtcode'] > 0){
			$param_array[1]  = $formdata['hdnid']; //ItemGroupCode
			$param_array[7]  =  $this->currentUser->username;  // Modified
	
					//Update Data array
					$lastid = $this->SFA_Comman->executequery('CALL sp_edit_inventory_index_additemgrp(?,?,?,?,?,?,?)',$param_array,'');
	
					if($lastid){
						SFA_Message::setMsg($this->translate->_('Update Record'));
					}
			}
			else{
				   //Insert Data Array
					$param_array[7]   =  $this->currentUser->username;  // Created
	
					 /* Insert data */
					$lastid = $this->SFA_Comman->executequery('CALL sp_add_inventory_index_additemgrp(?,?,?,?,?,?,?)',$param_array,'');
					if($lastid){
						SFA_Message::setMsg($this->translate->_('New Record'));
					}
			}
			
			$this->_redirect($formdata['backUrl']);
			
		}
		elseif($params['id'] > 0)
		{
		    $resultdata = $this->SFA_Comman->executequery('CALL sp_get_inventory_index_additemgrp(?)',$params['id'],'');            
		    $this->view->sub_major_cat = $resultdata[1];
    
		    $res['txtcode']    		= $resultdata[0][0]['itemgroupcode'];
		    $res['txtaltcode'] 		= $resultdata[0][0]['alternateitemgroupcode'];
		    $res['ddlsubmajcat'] 	= $resultdata[0][0]['submajorcategorycode'];
		    $res['txtname']      	= $resultdata[0][0]['itemgroupname'];
		    $res['txtnamearb']   	= $resultdata[0][0]['arbitemgroup'];
		    $res['ddlstatus']    	= $resultdata[0][0]['activestatus'];
		    $res['createddate']  	= date("d-m-Y",strtotime($resultdata[0][0]['cdat']));
		    $this->view->formdata	= $res;
		}
		else
		{
		    $result = $this->SFA_Comman->executequery('CALL sp_get_inventory_index_additemgrp(?)','0','');
		    $this->view->formdata['txtcode']	= ($result[0][0]['Auto_increment'] == '') ? '1' : $result[0][0]['Auto_increment'];
		    $this->view->sub_major_cat 		= $result[1];
		}
	

    }
    /**
    * @name       itemsAction
    * @since      28-12-2011
    * @version    Release: 1
    * @author     HD <hiren.d@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This action is use for the display items details
    */
    public function itemsAction()
    {
		$this->view->params = $params = $this->getRequest()->getParams();
		$this->view->formdata = $formdata = $this->_request->getPost();
		//For checking to display alternate code or not.
		$Settings_NameSpace = new Zend_Session_Namespace('Settings');
			
		if($formdata["hdDelete"]==1)
		{
			$ids = implode(',',$formdata['chk']);
			$param_array 	= array();
			$param_array[1]	= $ids;
			$param_array[2]	= $this->currentUser->username;
			
			$result 	= $this->SFA_Comman->executequery('CALL sp_delete_inventory_index_items(?,?)',$param_array,'');
			
			if($result[0][0]['deleted_id'] =='')
			{
			$ids		= explode(',',$ids);
			$checked 	= $ids;
			
			SFA_Message::setErrorMsg($this->translate->_('Record Already In use'));
			}
			else
			{		
			$deleted_id 	= explode(',',$result[0][0]['deleted_id']);
			$ids		= explode(',',$ids);
			$checked 	= array_diff($ids,$deleted_id);
			
			if(count($ids) != count($deleted_id)){
				SFA_Message::setErrorMsg($this->translate->_('Record Already In use'));
			}
			
			SFA_Message::setMsg($this->translate->_('Delete Record'));
			}
		}
		
		// For Alternate Code Status.
		$cpanel						= $this->SFA_Comman->getaltcodestatus();
		$altcode_status				= $cpanel["Use Alternate Code"]['status'];
		
		
		$cols_array = array('item.actualitemcode','item.alternatecode','item.itemshortdescription AS itemshortdescription','igrp.itemgroupname AS itemgroupname','unitspercase','item.activeitem');
		$columns_show =  array($this->translate->_('Code'),$this->translate->_('Alternate Code'),$this->translate->_('Name'),$this->translate->_('Item Group'),$this->translate->_('UPC'),$this->translate->_('Status'));
		
		if($this->css == 'ar_') {
			$cols_array[2]	= 'item.arbitemshortdescription  AS itemshortdescription';
			$cols_array[3]	= 'igrp.itemgroup AS itemgroupname';
		}
		// prepare the configuration for grid
		$pagingparams = array(
				"show_grid_heading" => true,
				"grid_heading_message" => $this->translate->_('Overview'),
				"pagename" => $this->translate->_('Items'),
				"show_records_per_page" => isset($formdata["show_records_per_page"])?$formdata["show_records_per_page"]:10,
				"show_searchbox" => true,
				"searchbox_value" => isset($formdata["grid_search_text"])?$formdata["grid_search_text"]:"",
				"show_selectbox" => true,
				"show_editlink" => true,
				 "selected_list" => $checked,
				"show_deletelink" => false,
				"show_deleteall" => false,
				"status_cols" => array(
							array(
								"cols_name" => "activeitem",
								"status_change" => array("0"=>"Inactive","1"=>"Active")
								)
							),
				"primaryid" => "actualitemcode",
				"editlink" => array("/inventory/index/additems/id/#pattern#/edit/yes/","#pattern#"),
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
	
		// call the stored procedure for fetch the data
		$param_array	= array();
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
		$result = $this->SFA_Comman->executequery('CALL sp_get_inventory_index_items(?,?,?,?,?,?,?,?)',$param_array,'',$downloadCSV,$printData,2,$pagingparams);
	
		$data_arr["count"] 	= $result[0][0]['counter'];	
		$data_arr["data"][0] 	= $result[1];
	
		/*print_r($data_arr["data"]);
		die;*/
	
		// pass the data in summary_showdatagrid() function & create a final variable for view
		$this->view->pagegridshow =  $pagingshow->summary_showdatagrid($data_arr);
	
		$this->view->addScriptPath(APPLICATION_PATH."/theme/default/datagrid/");
		
		/* Grid End */
    }
    /**
    * @name       additems
    * @since      29-12-2011
    * @version    Release: 1
    * @author     HD <hiren.d@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This action is use for the add items
    */
    public function additemsAction()
    {
		//view variable declaration
		$this->view->params 	= $params = $this->getRequest()->getParams();
			$this->view->formdata 	= $formdata = $this->_request->getPost();
		
		
		$this->view->select 	= $this->translate->_('Select');
		$this->view->missonefld	= $this->translate->_('Missed One Field');
		$this->view->youmissed	= $this->translate->_('You Missed');
		$this->view->highlated	= $this->translate->_('Fields. They have been highlighted.');
		$this->view->created_dt	= $this->translate->_('Created Date');
		
		$Settings_NameSpace = new Zend_Session_Namespace('Settings');
		$this->view->enable_cost_price = $Settings_NameSpace->cpanel['Enable Cost Price']['status'];
		if($this->view->enable_cost_price > 0) {
			$this->view->cost_price_percent = $Settings_NameSpace->cpanel['Cost Price Percent']['status'];
		}	
		/*
		* item type array
		*/
		$itemtype = array();
		$itemtype[0]['id']  	= '0';
		$itemtype[0]['val'] 	= 'None';
		$itemtype[0]['id']  	= '1';
		$itemtype[0]['val'] 	= 'Product Item';
		$itemtype[1]['id']  	= '2';
		$itemtype[1]['val'] 	= 'Containers (Crates)';
		$itemtype[2]['id']  	= '3';
		$itemtype[2]['val'] 	= 'Other Item (Not Used)';
		$itemtype[3]['id']  	= '4';
		$itemtype[3]['val'] 	= 'Competitor Item';
		$itemtype[4]['id']  	= '5';
		$itemtype[4]['val'] 	= 'Parent Item';
	
		$this->view->itemtype	= $itemtype;
		$username	= $this->currentUser->username;
        

        

		if($formdata['ddlitemgrp'] != "" && $formdata['txtitemname'] != "" && $formdata['txtdescription'] != "")
		{
			//SFA_Comman::pre($formdata);
			$param_array = array();
			//Update Data Array
			$param_array = array(
			   1    => trim($formdata['txtcode']), 		//actualitemcode
			   2    => trim($formdata["txtalphanum"]), 		//anitemcode
			   3    => trim($formdata["ddlitemgrp"]), 		//itemgroupcode
			   4    => trim($formdata["txtaltcode"]), 		//alternatecode
			   5    => trim($formdata["txtitemname"]),		//itemshortdescription
			   6    => trim($formdata["txtwarehouse"]), 	//warehousestock
			   7    => trim($formdata["txtdescription"]),	//itemdescription
			   8    => trim($formdata["txtfiltonhhc"]), 	//itemgrpCode
			   9    => trim($formdata["txtupc"]), 		//unitspercase
			   10   => trim($formdata["txtdataentry_sq"]), 	//dataentry
			   11   => trim($formdata["txtltrprcase"]), 	//liter
			   12   => trim($formdata["txtltrprunit"]), 	//literperunit
			   13   => trim(str_replace(',','',$formdata["txtsalescaseprice"])), 	//casePrice
			   14   => trim(str_replace(',','',$formdata["txtsalespcprice"])), 	//defaultsalesprice
			   15   => trim(str_replace(',','',$formdata["txtstdgdretcaseprice"])), 	//defaultgoodreturncasePrice
			   16   => trim(str_replace(',','',$formdata["txtstdgdretpcprice"])), 	//defaultgoodreturnPrice
			   17   => trim(str_replace(',','',$formdata["txtstddmgcaseprice"])), 	//returncaseprice
			   18   => trim(str_replace(',','',$formdata["txtstddmgpcprice"])), 	//defaultreturnprice
			   19   => trim($formdata["txtcaseqty"]), 		//caseuom
			   20   => $formdata["ddlstatus"], 			//activeitem
			   21   => trim($formdata["chkshelfstock"]), 	//captureshelfstock
			   22   => trim($formdata["chktcallowed"]), 	//tcallowed
			   23   => trim($formdata["chkinvopricechng"]), 	//allowinvoicepricechange
			   24   => trim($formdata["txtnamearb"]), 		//arbitemdescription
			   25   => trim($formdata["txtshortnamearb"]), 	//arbitemshortdescription
			   26   => trim(str_replace(',','',$formdata["hdntxtsalescostpcsprice"])), 		//costcaseprice
			   27   => trim(str_replace(',','',$formdata["hdntxtsalespcsprice"])), 		//defaultcostprice			   
			   28   => trim($formdata["txtbarcode1"]), 		//barcode1
			   29   => trim($formdata["txtbarcode2"]), 		//barcode2
			   30   => trim($formdata["txtbarcode3"]), 		//barcode3
			   31   => trim($formdata["txtbarcode4"]), 		//barcode4
			   32   => trim($formdata["txtbarcode5"]), 		//barcode5
			   33   => trim($formdata["txtbarcode6"]), 		//barcode6
			   34   => trim($formdata["txtbarcode7"]), 		//barcode7
			   35   => trim($formdata["txtbarcode8"]), 		//barcode8
			   36   => trim($formdata["txtbarcode9"]), 		//barcode9
			   37   => trim($formdata["txtbarcode10"]), 	//barcode10
			   38   => $username, 							//created
			   39   => $username, 							//modified			   
			   40   => ($formdata['ddlitemtaxkey1'] > 0) ? $formdata['ddlitemtaxkey1'] : 'NULL',//ItemTaxKey1
			   41   => ($formdata['ddlitemtaxkey2'] > 0) ? $formdata['ddlitemtaxkey2'] : 'NULL',//ItemTaxKey2
			   42   => ($formdata['ddlitemtaxkey3'] > 0) ? $formdata['ddlitemtaxkey3'] : 'NULL' //ItemTaxKey3
		   );
			
			if($formdata['hdnid'] > 0 && $params['id'] > 0) {
			   $param_array[1] = $formdata['hdnid']; //ItemGroupCode
				//Update Data array
				$lastid = $this->SFA_Comman->executequery('CALL sp_edit_inventory_index_additem(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',$param_array,'');
				if($lastid){
					SFA_Message::setMsg($this->translate->_('Update Record'));
				}
			}
			else
			{
				/* Insert data */
			   $lastid = $this->SFA_Comman->executequery('CALL sp_add_inventory_index_additem(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',$param_array,'');
			   if($lastid){
				   SFA_Message::setMsg($this->translate->_('New Record'));
			   }else{
			   SFA_Message::setErrorMsg($this->translate->_('Item Code Already Exists'));
			   }
			}
			
			if($lastid[0][0]['result'] > 0)
			{
				//parameter array
				$param_array = array(
				1        => $lastid[0][0]['result'], 		//actualcode
				2        => trim($formdata["txtprnt_seq_route"]), 	//PrintSequenceRoute
				3        => trim($formdata["txtprnt_seq_cust"]), 	//PrintSequenceCust				
				4        => $formdata["chkfastmovingitem"], 		//FastMovingItemFlag
				5        => $formdata['ddlcode_day_form'], 		//CodeDateFormat
				6        => trim($formdata["ddlitemtype"]), 		//ItemType
				7        => ($formdata["ddlitemtaxkey1"] > 0) ? trim($formdata["ddlitemtaxkey1"]) : 'NULL', 		//ItemTaxKey1
				8        => ($formdata["ddlitemtaxkey2"] > 0) ? trim($formdata["ddlitemtaxkey2"]) : 'NULL', 		//ItemTaxKey2
				9        => ($formdata["ddlitemtaxkey3"] > 0) ? trim($formdata["ddlitemtaxkey3"]) : 'NULL', 		//ItemTaxKey3
				10       => $formdata["ddlallowbatch"], 		//AllowBatchEntry
				11	 	 => $formdata['ddlitempkg'],			//packagecode
				12       => trim($formdata["txtmemo1"]),		//memo1
				13       => trim($formdata["txtmemo2"]),		//memo2		
				14       => $username,
				15		 => $formdata['txtavgpara']
				);
				
				$last_id = $this->SFA_Comman->executequery('CALL sp_edit_inventory_index_itemset1(?,?,?,?,?,?,?,?,?,?,?,?,?,?)',$param_array,'');
			}
			
			$this->_helper->redirector('items', 'index', 'inventory');
		}elseif($params['id'] > 0){
	
			$param_array = array();
			$param_array[1] = $params['id'];
			$resultdata = $this->SFA_Comman->executequery('CALL sp_get_inventory_index_additem(?)',$param_array,'');
			$result 	= $resultdata[0];
			$result1 	= $resultdata[4];
	
			$data = array();
			$data['txtcode'] 		= $result[0]['actualitemcode'];
			$data["txtalphanum"] 	= $result[0]['anitemcode'];
			$data["ddlitemgrp"]  	= $result[0]['itemgroupcode'];
			$data["txtaltcode"]		= $result[0]['alternatecode'];
			$data["txtitemname"]	= $result[0]['itemshortdescription'];
			$data["txtwarehouse"]	= $result[0]['warehousestock'];
			$data["txtdescription"]	= $result[0]['itemdescription'];
			$data["txtfiltonhhc"]	= $result[0]['itemgrpcode'];
			$data["txtupc"]		= $result[0]['unitspercase'];
			$data["txtdataentry_sq"]	= $result[0]['dataentry'];
			$data["txtavgpara"]	= $result[0]['offtakeparameter'];
			$data["txtltrprcase"]	= $result[0]['liter'];
			$data["txtltrprunit"]	= $result[0]['literperunit'];
			$data["txtsalescaseprice"]	= $result[0]['caseprice'];
			$data["txtsalespcprice"]	= $result[0]['defaultsalesprice'];
			$data["txtstdgdretcaseprice"]=  $result[0]['defaultgoodreturncaseprice'];
			$data["txtstdgdretpcprice"]	= $result[0]['defaultgoodreturnprice'];
			$data["txtstddmgcaseprice"]	= $result[0]['returncaseprice'];
			$data["txtstddmgpcprice"]	= $result[0]['defaultreturnprice'];
			$data["txtcaseqty"]		= $result[0]['caseuom'];
			$data["ddlstatus"] 		= $result[0]['activeitem'];
			$data["chkshelfstock"]	= $result[0]['captureshelfstock'];
			$data["chktcallowed"]	= $result[0]['tcallowed'];
			$data["txtsalespcsprice"]  	= $result[0]['defaultcostprice'];
			$data["txtsalescostpcsprice"]  = $result[0]['costcaseprice'];
			$data["chkinvopricechng"]	= $result[0]['allowinvoicepricechange'];
			$data["txtnamearb"]		= $result[0]['arbitemdescription'];
			$data["txtshortnamearb"]	= $result[0]['arbitemshortdescription'];
			$data["barcode1"]     		= $result[0]['barcode1'];
			$data["barcode2"]     		= $result[0]['barcode2'];
			$data["barcode3"]     		= $result[0]['barcode3'];
			$data["barcode4"]     		= $result[0]['barcode4'];
			$data["barcode5"]     		= $result[0]['barcode5'];
			$data["barcode6"]     		= $result[0]['barcode6'];
			$data["barcode7"]     		= $result[0]['barcode7'];
			$data["barcode8"]     		= $result[0]['barcode8'];
			$data["barcode9"]     		= $result[0]['barcode9'];
			$data["barcode10"]     		= $result[0]['barcode10'];
			$data["txtprnt_seq_route"] = $result1[0]['printsequenceroute'];
			$data["txtprnt_seq_cust"]  = $result1[0]['printsequencecust'];
			$data["chkfastmovingitem"] = $result1[0]['fastmovingitemflag'];
			$data["ddlcode_day_form"]  = $result1[0]['codedateformat'];
			$data["ddlitemtype"]       = $result1[0]['itemtype'];
			$data["ddlitemtaxkey1"]    = $result1[0]['itemtaxkey1'];
			$data["ddlitemtaxkey2"]    = $result1[0]['itemtaxkey2'];
			$data["ddlitemtaxkey3"]    = $result1[0]['itemtaxkey3'];
			$data["ddlallowbatch"]     = $result1[0]['allowbatchentry'];		
			
			$data["txtmemo1"]          = $result1[0]['memo1'];
			$data["txtmemo2"]          = $result1[0]['memo2'];
			$data['ddlitempkg']		   = $result1[0]['packagecode'];
			
	
			$this->view->formdata 		= $data;
			$this->view->itemgroup		= $resultdata[1];			
			$this->view->itempkg 		= $resultdata[2];	 
			$this->view->itemtaxkey1 	= $resultdata[3];
			$this->view->itemtaxkey2 	= $resultdata[3];
			$this->view->itemtaxkey3 	= $resultdata[3];
			
			}
		else
		{
			$result = $this->SFA_Comman->executequery('CALL sp_get_inventory_index_additem(?)','0','');
			$this->view->formdata['txtcode']	= ($result[0][0]['Auto_increment'] == '') ? '1' : $result[0][0]['Auto_increment'];
			$this->view->itemgroup 				= $result[1];
			$this->view->itempkg 				= $result[2];	 
			$this->view->itemtaxkey1 			= $result[3];
			$this->view->itemtaxkey2 			= $result[3];
			$this->view->itemtaxkey3 			= $result[3];
		}
    }

}