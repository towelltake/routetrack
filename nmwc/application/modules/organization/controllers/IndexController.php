<?php
/**
* @name       IndexController
* @since
* @version    Release: 1
* @author     HD <hiren.d@elantechnologies.com>
* @copyright  Elan Technologies
* @param
*
* This controller is manage user Organization module.
*/
class Organization_IndexController extends Organization_Library_Controller_Action_Abstract
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
		$this->view->colan		= $this->translate->_('Colan');
		
		$this->SFA_Comman 			= new SFA_Comman();
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
    * @name       areaaction
    * @since      19-12-2011
    * @version    Release: 1
    * @author     HD <hiren.d@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This action is use for the display Area Details
    */
    public function areaAction()
    {
		$this->view->params = $params = $this->getRequest()->getParams();
        $this->view->formdata = $formdata = $this->_request->getPost();
	

		if($formdata["hdDelete"]==1)
		{
			$ids = implode(',',$formdata['chk']);
			$param_array 	= array();
			$param_array[1]	= $ids;
			$param_array[2]	= $this->currentUser->username;
			
			$result 	= $this->SFA_Comman->executequery('CALL sp_delete_organization_index_area(?,?)',$param_array,'');
			
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
	
		$this->view->title	= $this->translate->_('Area');
		
		$cols_array = array('area.areacode','area.areaname','am.areamanagername','depot.depotname','area.activestatus');	
		$columns_show =  array($this->translate->_('Code'),
				$this->translate->_('Area Name'),
				$this->translate->_('Area Manager'),
				$this->translate->_('Branch/Depot'),	
				$this->translate->_('Status'));
		
		if($this->css == 'ar_') {
			$cols_array[1]	= 'arbareaname';
			$cols_array[2]	= 'arbareamanagername';
			$cols_array[3]	= 'arbdepotname';
		}
		
		$pagingparams = array(
				 "show_grid_heading" => true,
				 "grid_heading_message" => $this->translate->_('Overview'),
				 "pagename" => $this->translate->_('Area'),
				 "show_records_per_page" => isset($formdata["show_records_per_page"])?$formdata["show_records_per_page"]:10,
				 "show_searchbox" => true,
				 "searchbox_value" => isset($formdata["grid_search_text"])?$formdata["grid_search_text"]:"",
				 "show_selectbox" => true,
				 "selected_list" => $checked,
				 "show_editlink" => true,
				 "show_deletelink" => false,
				 "show_deleteall" => false,
				"status_cols" => array(
						  array(
						  "cols_name" => "activestatus",
						  "status_change" => array("0"=>"Inactive","1"=>"Active")
						  )
						  ),
				 "primaryid" => "areacode",
				 "editlink" => array("/organization/index/addarea/id/#pattern#/edit/yes/","#pattern#"),
				 "fetch_columns_inquery" => $cols_array,
				 "show_columns" => $columns_show,
				 "nodata_message" => $this->translate->_('No Record(s) Found')
				 );
			
		if(!$this->checkaccess("update"))
		{
			$pagingparams["show_editlink"] = false;
		}
		$pagingshow = new SFA_Paging($pagingparams);
		
		// call common function of grid class
		$get_return_vals = $pagingshow->commnfunc();
		
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
		$rowcount = $this->SFA_Comman->executequery('CALL sp_get_organization_index_area(?,?,?,?,?,?,?,?)',$param_array,'',$downloadCSV,$printData,2,$pagingparams);

		$data_arr["count"] 		= $rowcount[0][0]['counter'];
		$data_arr["data"][0]	= $rowcount[1];
		
		// pass the data in summary_showdatagrid() function & create a final variable for view
		$this->view->pagegridshow =  $pagingshow->summary_showdatagrid($data_arr);
		
		$this->view->addScriptPath(APPLICATION_PATH."/theme/default/datagrid/");
    }
    /**
    * @name       addareaaction
    * @since      15-12-2011
    * @version    Release: 1
    * @author     HD <hiren.d@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This action is use for the add Area Details
    */
    public function addareaAction()
    {
	$this->view->params = $params = $this->getRequest()->getParams();
        $this->view->formdata = $formdata = $this->_request->getPost();

	
	$this->view->select 	= $this->translate->_('Select');
	$this->view->missonefld	= $this->translate->_('Missed One Field');
	$this->view->youmissed	= $this->translate->_('You Missed');
	$this->view->highlated	= $this->translate->_('Fields. They have been highlighted.');


	if(isset($formdata['txtcode']) && isset($formdata['txtname']) && isset($formdata['ddlbrnch_depot']) && isset($formdata['ddlarea_mngr'])){
		$param_array = array();
		$param_array[1] = trim($formdata['hdnid']); 		//areacode
		$param_array[2] = trim($formdata['txtname']);		//areaname
		$param_array[3] = trim($formdata['txtname_arb']);	//arbareaname
		$param_array[4] = trim($formdata['ddlbrnch_depot']);	//depotcode
		$param_array[5] = trim($formdata['ddlarea_mngr']);	//areamanagercode
		$param_array[6] = 0;					//areaprefix
		$param_array[7] = $this->currentUser->username;		//Modified
		$param_array[8] = trim($formdata['txtaltcode']);	//alternateareacode
		$param_array[9] = trim($formdata['ddlstatus']);		//ActiveStatus

		//condition for add and edit.
		if(isset($params['id']) && $params['id'] > 0 && $formdata['hdnid'] > 0){
		    $last_id = $this->SFA_Comman->executequery('CALL sp_edit_organization_index_addarea(?,?,?,?,?,?,?,?,?)',$param_array,'');
		    SFA_Message::setMsg($this->translate->_('Update Record'));
		}else{
		    $param_array[10] = $this->currentUser->username;	//Created
		    
		    $last_id = $this->SFA_Comman->executequery('CALL sp_add_organization_index_addarea(?,?,?,?,?,?,?,?,?,?)',$param_array,'');		    
		    SFA_Message::setMsg($this->translate->_('New Record'));
		}
	    if($last_id > 0){
		$this->_helper->redirector('area', 'index', 'organization');
	    }
	}elseif($params['id'] > 0){
	    $result  		= $this->SFA_Comman->executequery('CALL sp_get_organization_index_addarea(?)',$params['id'],'');	    
	    $res['txtcode'] 	= $result[2][0]['areacode'];
	    $res['txtaltcode'] 	= $result[2][0]['alternateareacode'];
	    $res['txtname'] 	= $result[2][0]['areaname'];
	    $res['txtname_arb'] = $result[2][0]['arbareaname'];	    
	    $res['ddlbrnch_depot'] = trim($result[2][0]['depotcode']);
	    $res['ddlarea_mngr']= $result[2][0]['areamanagercode'];
	    $res['ddlstatus'] 	= $result[2][0]['activestatus'];
	    $res['createddate'] = date('d-m-Y',strtotime($result[2][0]['cdat']));
	    $this->view->formdata = $res;
	    $this->view->branch = $result[0];
	    $this->view->area	= $result[1];
	}
	else
	{
	    $tblname = 'areamaster';
	    $result = $this->SFA_Comman->executequery('CALL sp_getcombobox_organization_index_addarea(?)',$tblname,'');

	    $this->view->branch = $result[0];
	    $this->view->area	= $result[1];
	    $this->view->formdata['txtcode']= ($result[2][0]['Auto_increment'] == '') ? '1' : $result[2][0]['Auto_increment'];
	}

    }
    
     /**
    * @name       subareaaction
    * @since      19-12-2011
    * @version    Release: 1
    * @author     HD <hiren.d@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This action is use for the display Sub Area Details
    */
    public function subareaAction()
    {
	
		$this->view->params = $params = $this->getRequest()->getParams();
        $this->view->formdata = $formdata = $this->_request->getPost();

		$this->view->title	= $this->translate->_('Sub Area');
	

		if($formdata["hdDelete"]==1)
		{
			$ids = implode(',',$formdata['chk']);
			$param_array 	= array();
			$param_array[1]	= $ids;
			$param_array[2]	= $this->currentUser->username;
			
			$result 	= $this->SFA_Comman->executequery('CALL sp_delete_organization_index_subarea(?,?)',$param_array,'');
			
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
			
		$cols_array = array('sm.subareacode','sm.subareaname','sp.supervisorname','am.areaname','dm.depotname','sm.activestatus');
		$columns_show =  array($this->translate->_('Sub Area Code'),
					$this->translate->_('Sub Area'),
					$this->translate->_('Supervisor'),
					$this->translate->_('Area Name'),
					$this->translate->_('Branch/Depot'),
					$this->translate->_('Status'));
		
		if($this->css == 'ar_') {
			$cols_array[1]	= 'arbsubareaname';
			$cols_array[2]	= 'arbsupervisorname';
			$cols_array[3]	= 'arbareaname';			
			$cols_array[3]	= 'arbdepotname';
		}
		
		$pagingparams = array(
					"show_grid_heading" => true,
					"grid_heading_message" => $this->translate->_('Overview'),
					"pagename" => $this->translate->_('Sub Area'),
					"show_records_per_page" => isset($formdata["show_records_per_page"])?$formdata["show_records_per_page"]:10,
					"show_searchbox" => true,
					"searchbox_value" => isset($formdata["grid_search_text"])?$formdata["grid_search_text"]:"",
					"show_selectbox" => true,
					"selected_list" => $checked,
					"show_editlink" => true,
					"show_deletelink" => false,
					"show_deleteall" => false,
					"primaryid" => "subareacode",
					"status_cols" => array(
							   array(
								   "cols_name" => "activestatus",
								   "status_change" => array("0"=>"Inactive","1"=>"Active")
								   )
							   ),
					"editlink" => array("/organization/index/addsubarea/id/#pattern#/edit/yes/","#pattern#"),
					"fetch_columns_inquery" => $cols_array,
					"show_columns" => $columns_show,
					"nodata_message" => $this->translate->_('No Record(s) Found')
					);
			
		if(!$this->checkaccess("update"))
		{
			$pagingparams["show_editlink"] = false;
		}
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
		$rowcount = $this->SFA_Comman->executequery('CALL sp_get_organization_index_subarea(?,?,?,?,?,?,?,?)',$param_array,'',$downloadCSV,$printData,2,$pagingparams);
		
	
		$data_arr["count"] 	 = $rowcount[0][0]['counter'];
		$data_arr["data"][0] = $rowcount[1];
		
		$this->view->pagegridshow =  $pagingshow->summary_showdatagrid($data_arr);
		
		$this->view->addScriptPath(APPLICATION_PATH."/theme/default/datagrid/");
    }
    /**
    * @name       addsubareaAction
    * @since      15-12-2011
    * @version    Release: 1
    * @author     HD <hiren.d@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This action is use for the add sub Area Details
    */
    public function addsubareaAction()
    {
	$this->view->params = $params = $this->getRequest()->getParams();
        $this->view->formdata = $formdata = $this->_request->getPost();

	
	$this->view->select 	= $this->translate->_('Select');
	$this->view->missonefld	= $this->translate->_('Missed One Field');
	$this->view->youmissed	= $this->translate->_('You Missed');
	$this->view->highlated	= $this->translate->_('Fields. They have been highlighted.');
	
	if(isset($formdata['txtcode']) && isset($formdata['txtname']) && isset($formdata['ddlarea']) && isset($formdata['ddlsupervisor'])){
		$param_array = array();
		$param_array[1] = trim($formdata['hdnid']); 		//subareacode
		$param_array[2] = trim($formdata['txtname']);		//subareaname
		$param_array[3] = trim($formdata['txtname_arb']);	//arbsubareaname
		$param_array[4] = trim($formdata['ddlarea']);		//areacode
		$param_array[5] = trim($formdata['ddlsupervisor']);	//supervisorcode		
		$param_array[6] = trim($formdata['txtaltcode']);	//alternatesubareacode
		$param_array[7] = trim($formdata['ddlstatus']);		//activestatus
		$param_array[8] = $this->currentUser->username;		//Modified or created

		//condition for add and edit.
		if(isset($params['id']) && $params['id'] > 0 && $formdata['hdnid'] > 0){
		    $last_id = $this->SFA_Comman->executequery('CALL sp_edit_organization_index_addsubarea(?,?,?,?,?,?,?,?)',$param_array,'');
		    SFA_Message::setMsg($this->translate->_('Update Record'));
		}else{		    
		    $last_id = $this->SFA_Comman->executequery('CALL sp_add_organization_index_addsubarea(?,?,?,?,?,?,?,?)',$param_array,'');
		    SFA_Message::setMsg($this->translate->_('New Record'));
		}
	    if($last_id > 0){
		$this->_helper->redirector('subarea', 'index', 'organization');
	    }
	}elseif($params['id'] > 0){
	    $result  			= $this->SFA_Comman->executequery('CALL sp_get_organization_index_addsubarea(?)',$params['id'],'');	    
	    $res['txtcode'] 		= $result[2][0]['subareacode'];
	    $res['txtaltcode'] 		= $result[2][0]['alternatesubareacode'];
	    $res['txtname'] 		= $result[2][0]['subareaname'];
	    $res['txtname_arb'] 	= $result[2][0]['arbsubareaname'];	    
	    $res['ddlarea'] 		= $result[2][0]['areacode'];
	    $res['ddlsupervisor'] 	= $result[2][0]['supervisorcode'];
	    $res['ddlstatus'] 		= $result[2][0]['activestatus'];
	    $res['createddate'] 	= date('d-m-Y',strtotime($result[2][0]['cdat']));
	    $this->view->formdata 	= $res;	  
	    $this->view->area 		= $result[0];
	    $this->view->super		= $result[1];
	}
	else
	{
	    $result = $this->SFA_Comman->executequery('CALL sp_getcombobox_organization_index_addsubarea()','','');
    
	    $this->view->area 	= $result[0];
	    $this->view->super	= $result[1];
	    $this->view->formdata['txtcode']= ($result[2][0]['Auto_increment'] == '') ? '1' : $result[2][0]['Auto_increment'];	    
	}

    }
    
    /**
    * @name       Country
    * @since      27-12-2011
    * @version    Release: 1
    * @author     HD <hiren.d@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This action is use for the display Country Details
    */
    public function countryAction()
    {
		$this->view->params = $params = $this->getRequest()->getParams();
        $this->view->formdata = $formdata = $this->_request->getPost();

		if($formdata["hdDelete"]==1)
		{
			$ids = implode(',',$formdata['chk']);
			$param_array 	= array();
			$param_array[1]	= $ids;
			$param_array[2]	= $this->currentUser->username;
			
			$result = $this->SFA_Comman->executequery('CALL sp_delete_organization_index_country(?,?)',$param_array,'');
			
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
		
		$this->view->title	= $this->translate->_('Country');	
		$cols_array 	= array('countrycode','countryname','currencyname');
		$columns_show 	=  array($this->translate->_('Country Code'),$this->translate->_('Country Name'),$this->translate->_('Currency Name'));
		
		if($this->css == 'ar_') {
			$cols_array[1]	= 'arbcountryname';
			$cols_array[2]	= 'arbcurrencyname';
		}
		
		// prepare the configuration for grid
		$pagingparams = array(
				"show_grid_heading" => true,
				"grid_heading_message" => $this->translate->_('Overview'),
				"pagename" => $this->translate->_('Country'),
				"show_records_per_page" => isset($formdata["show_records_per_page"])?$formdata["show_records_per_page"]:10,
				"show_searchbox" => true,
				"searchbox_value" => isset($formdata["grid_search_text"])?$formdata["grid_search_text"]:"",
				"show_selectbox" => true,
				"selected_list" => $checked,
				"show_editlink" => true,
				"show_deletelink" => false,			
				"show_deleteall" => false,
				"primaryid" => "countrycode",
				"editlink" => array("/organization/index/addcountry/id/#pattern#/edit/yes/","#pattern#"),
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
		$result = $this->SFA_Comman->executequery('CALL sp_get_organization_index_country(?,?,?,?,?,?,?,?)',$param_array,'',$downloadCSV,$printData,2,$pagingparams);
		
	
		$data_arr["count"]	= $result[0][0]['counter'];
		$data_arr["data"][0] 	= $result[1];
		
		// pass the data in summary_showdatagrid() function & create a final variable for view
		$this->view->pagegridshow =  $pagingshow->summary_showdatagrid($data_arr);
		
		$this->view->addScriptPath(APPLICATION_PATH."/theme/default/datagrid/");
    }
    /**
    * @name       Country
    * @since      27-12-2011
    * @version    Release: 1
    * @author     HD <hiren.d@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This action is use for the add information of Country
    */
    public function addcountryAction()
    {
	$this->view->params = $params = $this->getRequest()->getParams();
        $this->view->formdata = $formdata = $this->_request->getPost();
	
	$this->view->returnUrl = $_SERVER["HTTP_REFERER"];

	if($formdata['txtcode']!='' && $formdata['txtcntname'] !='' && $formdata['ddlcurrency'] > 0 && $formdata['ddlcompany'] > 0)
	{
	    $param_array = array();
	    $param_array[1] = $formdata['txtaltcode'];
	    $param_array[2] = $formdata['txtcntname'];
	    $param_array[3] = $formdata['txtarbcntname'];
	    $param_array[4] = $formdata['ddlcurrency'];
	    $param_array[5] = $formdata['ddlcompany'];
	    $param_array[6] = $formdata['txtprice_chng'];
	    $param_array[7] = $formdata['ddlsales_mng'];
	    $param_array[8] = $this->currentUser->username;
		
	    if($formdata['hdnid'] > 0)
	    {
		$param_array[9] = $formdata['hdnid'];
		$this->SFA_Comman->executequery('CALL sp_edit_organization_index_addcountry(?,?,?,?,?,?,?,?,?)',$param_array,'');		
		SFA_Message::setMsg($this->translate->_('Update Record'));
	    }
	    else{
		
		$this->SFA_Comman->executequery('CALL sp_add_organization_index_addcountry(?,?,?,?,?,?,?,?)',$param_array,'');
		SFA_Message::setMsg($this->translate->_('New Record'));
	    }
	    
	    if($formdata["returnUrl"]!="")
		$this->_redirect($formdata["returnUrl"]);
	    else
		$this->_helper->redirector('country', 'index', 'organization');
	}
	elseif($params['id'] > 0)
	{
	    /* display data for edit */
	    $result = $this->SFA_Comman->executequery('CALL sp_get_organization_index_addcountry(?)',$params['id'],'');
	    $info[0] = $result[0][0];
	    $res['txtcode'] 		= $info[0]['countrycode'];
	    $res['txtaltcode'] 		= $info[0]['alternatecode'];
	    $res['txtcntname'] 		= $info[0]['countryname'];
	    $res['txtarbcntname'] 	= $info[0]['arbcountryname'];
	    $res['ddlcurrency']		= $info[0]['currencycode'];
	    $res['ddlcompany'] 		= $info[0]['cmpycode'];
	    $res['ddlsales_mng'] 	= $info[0]['nationalsalesmanagercode'];
	    $res['txtprice_chng'] 	= $info[0]['pricechangevariance'];
	    $res['createddate'] 	= date("d-m-Y",strtotime($info[0]['cdat']));
	    
	    $this->view->formdata		= $res;
	    $this->view->company_data		= $result[1];
	    $this->view->currency_data		= $result[2];
	    $this->view->sales_mng_data		= $result[3];
	}
	else
	{
	    $result = $this->SFA_Comman->executequery('CALL sp_get_organization_index_addcountry(?)','0','');
	    
	    $this->view->formdata['txtcode'] 	= ($result[0][0]['Auto_increment'] == '') ? '1' : $result[0][0]['Auto_increment'];
	    $this->view->company_data		= $result[1];
	    $this->view->currency_data		= $result[2];
	    $this->view->sales_mng_data		= $result[3];
	}
    }

    /**
    * @name       regionmaster
    * @since      27-12-2011
    * @version    Release: 1
    * @author     HD <hiren.d@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This action is use for the display region Details
    */
    public function regionAction()
    {
		$this->view->params = $params = $this->getRequest()->getParams();
        $this->view->formdata = $formdata = $this->_request->getPost();
	
	
		if($formdata["hdDelete"]==1)
		{
			$ids = implode(',',$formdata['chk']);
			$param_array 	= array();
			$param_array[1]	= $ids;
			$param_array[2]	= $this->currentUser->username;
			
			$result 	= $this->SFA_Comman->executequery('CALL sp_delete_organization_index_regionmaster(?,?)',$param_array,'');
			
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
	
	
		$this->view->title	= $this->translate->_('Region');
		$cols_array 	= array('regionmstcode','regionmstname','countryname');
		$columns_show 	=  array($this->translate->_('Region Code'),$this->translate->_('Region Name'),$this->translate->_('Country'));
		
		if($this->css == 'ar_') {
			$cols_array[1]	= 'arbregionmstname';
			$cols_array[2]	= 'arbcountryname';
		}
		
		// prepare the configuration for grid
		$pagingparams = array(
				"show_grid_heading" => true,
				"grid_heading_message" => $this->translate->_('Overview'),
				"pagename" => $this->translate->_('Region'),
				"show_records_per_page" => isset($formdata["show_records_per_page"])?$formdata["show_records_per_page"]:10,
				"show_searchbox" => true,
				"searchbox_value" => isset($formdata["grid_search_text"])?$formdata["grid_search_text"]:"",
				"show_selectbox" => true,
				"selected_list" => $checked,
				"show_editlink" => true,
				"show_deletelink" => false,
				"show_deleteall" => false,			
				"primaryid" => "regionmstcode",
				"editlink" => array("/organization/index/addregion/id/#pattern#/edit/yes/","#pattern#"),
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
	    $result = $this->SFA_Comman->executequery('CALL sp_get_organization_index_regionmaster(?,?,?,?,?,?,?,?)',$param_array,'',$downloadCSV,$printData,2,$pagingparams);
    
	    $data_arr["count"]		= $result[0][0]['counter'];
	    $data_arr["data"][0]	= $result[1];
	    
	    // pass the data in summary_showdatagrid() function & create a final variable for view
	    $this->view->pagegridshow =  $pagingshow->summary_showdatagrid($data_arr);
	    
	    $this->view->addScriptPath(APPLICATION_PATH."/theme/default/datagrid/");

    }
    /**
    * @name       addregion
    * @since      27-12-2011
    * @version    Release: 1
    * @author     HD <hiren.d@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This action is use for the add information of region
    */
    public function addregionAction()
    {
	$this->view->params = $params = $this->getRequest()->getParams();
        $this->view->formdata = $formdata = $this->_request->getPost();
	
	$this->view->returnUrl = $_SERVER["HTTP_REFERER"];

	if($formdata['txtregname'] !='' && $formdata['ddlcountry'] !='')
	{
	    $param_array = array();
	    $param_array[1] = $formdata['txtaltcode'];
	    $param_array[2] = $formdata['txtregname'];
	    $param_array[3] = $formdata['txtarbregname'];
	    $param_array[4] = $formdata['ddlcountry'];
	    $param_array[5] = $formdata['ddlreg_mng'];
	    $param_array[6] = $this->currentUser->username;
	    
	    if($formdata['hdnid'] > 0)
	    {
		$param_array[7] = $formdata['hdnid'];
		$this->SFA_Comman->executequery('CALL sp_edit_organization_index_addregionmaster(?,?,?,?,?,?,?)',$param_array,'');
		SFA_Message::setMsg($this->translate->_('Update Record'));
	    }
	    else
	    {
		$this->SFA_Comman->executequery('CALL sp_add_organization_index_addregionmaster(?,?,?,?,?,?)',$param_array,'');
		SFA_Message::setMsg($this->translate->_('New Record'));
	    }
	    
	    if($formdata["returnUrl"]!="")
		$this->_redirect($formdata["returnUrl"]);
	    else
		$this->_helper->redirector('region', 'index', 'organization');
	}
	elseif($params['id'] > 0)
	{
	    $result = $this->SFA_Comman->executequery('CALL sp_get_organization_index_addregionmaster(?)',$params['id'],'');
	    
	    $info[0] = $result[0][0];
	    $res['txtcode']		= $info[0]['regionmstcode'];
	    $res['txtaltcode']		= $info[0]['alternatecode'];
	    $res['txtregname']		= $info[0]['regionmstname'];
	    $res['txtarbregname']	= $info[0]['arbregionmstname'];
	    $res['ddlcountry']		= $info[0]['countrycode'];
	    $res['ddlreg_mng']		= $info[0]['regionmanagercode'];
	    $res['createddate']		= date("d-m-Y",strtotime($info[0]['cdat']));
	    
	    $this->view->formdata	= $res;
	    $this->view->country_data	= $result[1];
	    $this->view->region_mng	= $result[2];
	    
	}
	else
	{
	    $result = $this->SFA_Comman->executequery('CALL sp_get_organization_index_addregionmaster(?)','0','');
	    $this->view->formdata['txtcode'] 	= ($result[0][0]['Auto_increment'] == '') ? '1' : $result[0][0]['Auto_increment'];
	    $this->view->country_data		= $result[1];
	    $this->view->region_mng		= $result[2];
	}
	
    }

    /**
    * @name       branch
    * @since      27-12-2011
    * @version    Release: 1
    * @author     HD <hiren.d@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This action is use for the display branch/depot Details
    */
    public function branchAction()
    {
		$this->view->params = $params = $this->getRequest()->getParams();
        $this->view->formdata = $formdata = $this->_request->getPost();
	
	

		if($formdata["hdDelete"]==1)
		{
			$ids = implode(',',$formdata['chk']);
			$param_array 	= array();
			$param_array[1]	= $ids;
			$param_array[2]	= $this->currentUser->username;
			
			$result 	= $this->SFA_Comman->executequery('CALL sp_delete_organization_index_depotmaster(?,?)',$param_array,'');
			
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
	
	
		$this->view->title	= $this->translate->_('Branch/Depot');
		$cols_array 	= array('depotcode','depotname','branchmanagername','name','depot.activestatus');
		$columns_show 	=  array($this->translate->_('Branch/Depot Code'),$this->translate->_('Branch/Depot Name'),$this->translate->_('Branch/Depot Manager'),$this->translate->_('Company'),$this->translate->_('Status'));
		
		if($this->css == 'ar_') {
			$cols_array[1]	= 'arbdepotname';
			$cols_array[2]	= 'arbbranchmanagername';
			$cols_array[3]	= 'arbcompanyname';
		}
		
	
		// prepare the configuration for grid
		$pagingparams = array(
				"show_grid_heading" => true,
				"grid_heading_message" => $this->translate->_('Overview'),
				"pagename" => $this->translate->_('Branch/Depot'),
				"show_records_per_page" => isset($formdata["show_records_per_page"])?$formdata["show_records_per_page"]:10,
				"show_searchbox" => true,
				"searchbox_value" => isset($formdata["grid_search_text"])?$formdata["grid_search_text"]:"",
				"show_selectbox" => true,
				"selected_list" => $checked,
				"show_editlink" => true,
				"show_deletelink" => false,
				"show_deleteall" => false,
				"status_cols" => array	(
								array(
								"cols_name" => "activestatus",
								"status_change" => array("0"=>"Inactive","1"=>"Active")
								)
							),
				"primaryid" => "depotcode",
				"editlink" => array("/organization/index/addbranch/id/#pattern#/edit/yes/","#pattern#"),
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
		$result = $this->SFA_Comman->executequery('CALL sp_get_organization_index_depotmaster(?,?,?,?,?,?,?,?)',$param_array,'',$downloadCSV,$printData,2,$pagingparams);
	
		$data_arr["count"]	= $result[0][0]['counter'];
		$data_arr["data"][0]	= $result[1];
		
		// pass the data in summary_showdatagrid() function & create a final variable for view
		$this->view->pagegridshow =  $pagingshow->summary_showdatagrid($data_arr);
		
		$this->view->addScriptPath(APPLICATION_PATH."/theme/default/datagrid/");
    }
    /**
    * @name       addbranch
    * @since      27-12-2011
    * @version    Release: 1
    * @author     HD <hiren.d@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This action is use for the add information of region
    */
    public function addbranchAction()
    {
	$this->view->params = $params = $this->getRequest()->getParams();
        $this->view->formdata = $formdata = $this->_request->getPost();
	
	$this->view->returnUrl = $_SERVER["HTTP_REFERER"];
	

	if($formdata['txtbrnch_name'] != '' && $formdata['ddlbrnch_mng'] != '' && $formdata['ddlcompany'] != '' && $formdata['ddlregion'] != '')
	{
	    $param_array 	= array();
	    $param_array[1]	= $formdata['txtaltcode'];
	    $param_array[2]	= $formdata['txtbrnch_name'];
	    $param_array[3]	= $formdata['txtarbbrnch_name'];
	    $param_array[4]	= $formdata['ddlcompany'];	    
	    $param_array[5]	=   ($formdata['chkcentral_wh'] > 0) ? $formdata['chkcentral_wh'] : '0'; //  $formdata['chkcentral_wh'];
	    $param_array[6]	= $formdata['txtbrnch_prefix'];
	    $param_array[7]	= $formdata['ddlbrnch_mng'];
	    $param_array[8]	= $formdata['ddlregion'];
	    $param_array[9]	=  ($formdata['ddlprice_key'] > 0) ? $formdata['ddlprice_key'] : '0'; // $formdata['ddlprice_key']; 
	    $param_array[10]	= $formdata['ddlstatus'];
	    $param_array[11]	= $this->currentUser->username;
	    
	    if($formdata['hdnid'] > 0)
	    {
		$param_array[12]	= $formdata['hdnid'];
		$last_id = $this->SFA_Comman->executequery('CALL sp_edit_organization_index_adddepotmaster(?,?,?,?,?,?,?,?,?,?,?,?)',$param_array,'');
		SFA_Message::setMsg($this->translate->_('Update Record'));
	    }
	    else
	    {
		$last_id = $this->SFA_Comman->executequery('CALL sp_add_organization_index_adddepotmaster(?,?,?,?,?,?,?,?,?,?)',$param_array,'');
		SFA_Message::setMsg($this->translate->_('New Record'));
	    }	
	    if($formdata["returnUrl"]!="")
		$this->_redirect($formdata["returnUrl"]);
	    else
		$this->_helper->redirector('branch', 'index', 'organization');
	}
	elseif($params['id'] > 0)
	{
	    $result = $this->SFA_Comman->executequery('CALL sp_get_organization_index_adddepotmaster(?)',$params['id'],'');
	    
	    $info[0] 			= $result[0][0];	    
	    $res['txtcode'] 		= $info[0]['depotcode'];
	    $res['chkcentral_wh'] 	= $info[0]['centralwh'];
	    $res['txtaltcode'] 		= $info[0]['alternatedepotcode'];
	    $res['txtbrnch_prefix'] 	= $info[0]['depotprefix'];
	    $res['txtbrnch_name'] 	= $info[0]['depotname'];
	    $res['txtarbbrnch_name'] 	= $info[0]['arbdepotname'];
	    $res['ddlbrnch_mng'] 	= $info[0]['branchmanagercode'];
	    $res['ddlcompany'] 		= $info[0]['cmpycode'];
	    $res['ddlregion'] 		= $info[0]['regionmstcode'];
	    $res['ddlprice_key'] 	= $info[0]['pricingkey'];
	    $res['ddlstatus'] 		= $info[0]['activestatus'];
	    $res['createddate'] 	= date("d-m-Y",strtotime($info[0]['cdat']));
	    
	    
	    $this->view->formdata 		= $res;
	    $this->view->branch_mng		= $result[1];
	    $this->view->company_data		= $result[2];
	    $this->view->region_data		= $result[3];
	    $this->view->pricekey_data		= $result[4];
	}
	else
	{
	    $result = $this->SFA_Comman->executequery('CALL sp_get_organization_index_adddepotmaster(?)','0','');
	    
	    $this->view->formdata['txtcode'] 	= ($result[0][0]['Auto_increment'] == '') ? '1' : $result[0][0]['Auto_increment'];
	    $this->view->branch_mng		= $result[1];
	    $this->view->company_data		= $result[2];
	    $this->view->region_data		= $result[3];
	    $this->view->pricekey_data		= $result[4];
	}
    }
    /**
    * @name       branch
    * @since      27-12-2011
    * @version    Release: 1
    * @author     HD <hiren.d@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This action is use for the display van Details
    */
    public function vanAction()
    {
		$this->view->params = $params = $this->getRequest()->getParams();
        $this->view->formdata = $formdata = $this->_request->getPost();

		$this->view->title	= $this->translate->_('Van');
		$code			= $this->translate->_('Van Code');
		$name			= $this->translate->_('Van Description');
		$status			= $this->translate->_('Status');
		
		if($formdata["hdDelete"]==1)
		{
			$ids = implode(',',$formdata['chk']);
			$param_array 	= array();
			$param_array[1]	= $ids;
			$param_array[2]	= $this->currentUser->username;
			
			$result = $this->SFA_Comman->executequery('CALL sp_delete_organization_index_van(?,?)',$param_array,'');
			
			
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
			
			if(count($ids) != count($deleted_id)) {
				SFA_Message::setErrorMsg($this->translate->_('Record Already In use'));
			}		
			SFA_Message::setMsg($this->translate->_('Delete Record'));
			}
		}
	
		
		$cols_array 	= array('vancode','vandescription','activestatus');
		$columns_show 	=  array($this->translate->_('Code'),$this->translate->_('Van Description'),$this->translate->_('Status'));
		
		if($this->css == 'ar_') {
			$cols_array[1]	= 'arbvandescription';
		}
		
	
		// prepare the configuration for grid
		$pagingparams = array(
				"show_grid_heading" => true,
				"grid_heading_message" => $this->translate->_('Overview'),
				"pagename" => $this->translate->_('Van'),
				"show_records_per_page" => isset($formdata["show_records_per_page"])?$formdata["show_records_per_page"]:10,
				"show_searchbox" => true,
				"searchbox_value" => isset($formdata["grid_search_text"])?$formdata["grid_search_text"]:"",
				"show_selectbox" => true,
				"selected_list" => $checked,
				"show_editlink" => true,
				"show_deletelink" => false,
				"show_deleteall" => false,
				"status_cols" => array	(
								array(
								"cols_name" => "activestatus",
								"status_change" => array("0"=>"Inactive","1"=>"Active")
								)
							),
				"primaryid" => "vancode",
				"editlink" => array("/organization/index/addvan/id/#pattern#/edit/yes/","#pattern#"),
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
		$result = $this->SFA_Comman->executequery('CALL sp_get_organization_index_van(?,?,?,?,?,?,?,?)',$param_array,'',$downloadCSV,$printData,2,$pagingparams);
	
		$data_arr["count"]	= $result[0][0]['counter'];
		$data_arr["data"][0]	= $result[1];
		
		// pass the data in summary_showdatagrid() function & create a final variable for view
		$this->view->pagegridshow =  $pagingshow->summary_showdatagrid($data_arr);
		
		$this->view->addScriptPath(APPLICATION_PATH."/theme/default/datagrid/");
    }
    /**
    * @name       addbranch
    * @since      27-12-2011
    * @version    Release: 1
    * @author     HD <hiren.d@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This action is use for the add information of van
    */
    public function addvanAction()
    {
	$this->view->params = $params = $this->getRequest()->getParams();
        $this->view->formdata = $formdata = $this->_request->getPost();
	
	$this->view->returnUrl = $_SERVER["HTTP_REFERER"];

	if($formdata['txtvanregno'] !='' && $formdata['txtvandesc'] !='')
	{
	    $param_array 	= array();
	    $param_array[1]	= $formdata['txtaltcode'];
	    $param_array[2]	= $formdata['txtvanregno'];
	    $param_array[3]	= $formdata['txtvanmodel'];
	    $param_array[4]	= $formdata['txtvantype'];
	    $param_array[5]	= $formdata['txtvandesc'];
	    $param_array[6]	= $formdata['txtarbvandesc'];
	    $param_array[7]	= $formdata['ddlstatus'];
	    $param_array[8]	= $this->currentUser->username;
	    
	    if($formdata['hdnid'] > 0)
	    {
		$param_array[9]	= $formdata['hdnid'];
		$last_id = $this->SFA_Comman->executequery('CALL sp_edit_organization_index_addvan(?,?,?,?,?,?,?,?,?)',$param_array,'');
		SFA_Message::setMsg($this->translate->_('Update Record'));
	    }
	    else
	    {
		$last_id = $this->SFA_Comman->executequery('CALL sp_add_organization_index_addvan(?,?,?,?,?,?,?,?)',$param_array,'');
		SFA_Message::setMsg($this->translate->_('New Record'));
	    }	
	    if($formdata["returnUrl"]!="")
		$this->_redirect($formdata["returnUrl"]);
	    else
		$this->_helper->redirector('van', 'index', 'organization');
	}
	elseif($params['id'] > 0)
	{
	    $result = $this->SFA_Comman->executequery('CALL sp_get_organization_index_addvan(?)',$params['id'],'');
	    
	    $res['txtcode']		= $result[0][0]['vancode'];
	    $res['txtaltcode']		= $result[0][0]['alternatecode'];
	    $res['txtvanregno']		= $result[0][0]['vanregno'];
	    $res['txtvanmodel']		= $result[0][0]['vanmodel'];
	    $res['txtvantype']		= $result[0][0]['vantype'];
	    $res['txtvandesc']		= $result[0][0]['vandescription'];
	    $res['txtarbvandesc']	= $result[0][0]['arbvandescription'];
	    $res['ddlstatus']		= $result[0][0]['activestatus'];
	    $res['createddate']		= date('d-m-Y',strtotime($result[0][0]['cdat']));
	    $this->view->formdata 	= $res;
	    
	}
	else
	{
	    $result = $this->SFA_Comman->executequery('CALL sp_get_organization_index_addvan(?)','0','');
	    $this->view->formdata['txtcode']	= ($result[0][0]['Auto_increment'] == '') ? '1' : $result[0][0]['Auto_increment'];
	}
    }
    /**
    * @name       routecat
    * @since      6-2-2012
    * @version    Release: 1
    * @author     PM <pankit@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This action is use for deatil view of routecat lists
    */
    public function routecatAction(){
        //view variable declaration
        $this->view->params = $params = $this->getRequest()->getParams();
		$this->view->formdata = $formdata = $this->_request->getPost();

        if($formdata["hdDelete"]==1)
		{
			$ids = implode(',',$formdata['chk']);
			$param_array 	= array();
			$param_array[1]	= $ids;
			$param_array[2]	= $this->currentUser->username;
			
			$result 	= $this->SFA_Comman->executequery('CALL sp_delete_organization_index_routecat(?,?)',$param_array,'');
			
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
        //variable declaration for grid title
		$cols_array = array('routecatcode','routecatname','arbroutecatname');
		$columns_show =  array($this->translate->_('Route Category Code'),$this->translate->_('Route Category Name'),$this->translate->_('Route Category Name ('.$this->sec_lang.')'));	
		
		// prepare the configuration for grid
		$pagingparams = array(
				 "show_grid_heading" => true,
				 "grid_heading_message" => $this->translate->_('Overview'),
				 "pagename" => $this->translate->_('Route Category'),
				 "show_records_per_page" => isset($formdata["show_records_per_page"])?$formdata["show_records_per_page"]:10,
				 "show_searchbox" => true,
				 "searchbox_value" => isset($formdata["grid_search_text"])?$formdata["grid_search_text"]:"",
				 "show_selectbox" => true,
				 "selected_list" => $checked,
				 "show_editlink" => true,
				 "show_deletelink" => false,
				 "show_deleteall" => false,
				 "primaryid" => "routecatcode",				 
				 "editlink" => array("/organization/index/addroutecat/id/#pattern#/edit/yes/","#pattern#"),
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
		$result = $this->SFA_Comman->executequery('CALL sp_get_organization_index_routecat(?,?,?,?,?,?,?,?)',$param_array,'',$downloadCSV,$printData,2,$pagingparams);
		
		$data_arr["count"] 	= $result[0][0]['counter'];
		$data_arr["data"][0] 	= $result[1];
		
		// pass the data in summary_showdatagrid() function & create a final variable for view
		$this->view->pagegridshow =  $pagingshow->summary_showdatagrid($data_arr);
		
		$this->view->addScriptPath(APPLICATION_PATH."/theme/default/datagrid/");	
    }

    /**
    * @name       addroutecat
    * @since      6-2-2012
    * @version    Release: 1
    * @author     PM <pankit@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This action is use for deatil view of business type with add option
    */
    public function addroutecatAction(){
        //view variable declaration
        $this->view->params = $params = $this->getRequest()->getParams();
        $this->view->formdata = $formdata = $this->_request->getPost();
		
		
	
		$this->view->returnUrl = $_SERVER["HTTP_REFERER"];

		if($formdata['txtbtypename']!='')
		{
			$param_array 		= array();
			$param_array[1] 	= $formdata['txtbtypename'];
			$param_array[2] 	= $formdata['txtarbtypename'];
			$param_array[3] 	= $this->currentUser->username;
			
			if($formdata['hdnid'] > 0)
			{
				$param_array[4] = $formdata['hdnid'];
				$result = $this->SFA_Comman->executequery('CALL sp_edit_organisation_index_addrotuecat(?,?,?,?,?)',$param_array,'');
			
				SFA_Message::setMsg($this->translate->_('Update Record'));
			}
			else
			{
				$result = $this->SFA_Comman->executequery('CALL sp_add_organisation_index_addrotuecat(?,?,?)',$param_array,'');
				SFA_Message::setMsg($this->translate->_('New Record'));
			}
			if($formdata["returnUrl"]!="")
				$this->_redirect($formdata["returnUrl"]);
			else
				$this->_helper->redirector('routecat', 'index', 'organization');
		}
		elseif($params['id'] > 0)
		{
			$result = $this->SFA_Comman->executequery('CALL sp_get_organisation_index_addrotuecat(?)',$params['id'],'');			
			$this->view->formdata = $result[0][0];
			$this->view->formdata['createddate'] = date('d-m-Y',strtotime($result[0][0]['cdat']));
		}
		else
		{
			$last_id = $this->SFA_Comman->executequery('CALL sp_get_organisation_index_addrotuecat(?)','0','');
			$this->view->formdata['routecatcode'] = ($last_id[0][0]['Auto_increment'] == '') ? '1' : $last_id[0][0]['Auto_increment'];			
		}
	    
	}    
}