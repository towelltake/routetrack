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
class Account_IndexController extends Account_Library_Controller_Action_Abstract
{

    public $common_model	= '';
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
		
		$this->css 					= $this->translate->_('CSS');
		$this->view->css 			= $this->css;
		$this->view->overview		= $this->translate->_('Overview');
		$this->view->details		= $this->translate->_('Details');
		$this->view->required		= $this->translate->_('Required');
		$this->view->colan 			= $this->translate->_('Colan');

		$this->SFA_Comman			= new SFA_Comman();
		$this->view->decimalplaces 	= $this->SFA_Comman->getdecimalplaces();
		$this->view->sec_lang		= $this->SFA_Comman->getsecondlanguage();
		$this->sec_lang 			= $this->view->sec_lang;
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
        
        if(in_array($getparams_init['action'],array("deletejourneyplan")))
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
    * @name       outletproductAction
    * @since      25-01-2012
    * @version    Release: 1
    * @author     HD <hiren.d@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This action is use display outlet product codes
    */
    public function outletproductAction()
    {
		$this->view->params = $params = $this->getRequest()->getParams();
        $this->view->formdata = $formdata = $this->_request->getPost();

		if($formdata["hdDelete"]==1)
		{
			$ids = implode(',',$formdata['chk']);
			$param_array 	= array();
			$param_array[1]	= $ids;
			$param_array[2]	= $this->currentUser->username;
			
			$result 	= $this->SFA_Comman->executequery('CALL sp_delete_account_index_outletproduct(?,?)',$param_array,'');
			
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
		
		$this->view->title	= $this->translate->_('Outlet Product Code');
		
		$cols_array 		= array('groupcode','groupname','activestatus');
		$columns_show 		= array($this->translate->_('Group Code'),$this->translate->_('Group Name'),$this->translate->_('Status'));
		
		if($this->css == 'ar_') {
			$cols_array[1]	= 'arbgroupname';
		}
		
		
		// prepare the configuration for grid
		$pagingparams = array(
				"show_grid_heading" => true,
				"grid_heading_message" => $this->translate->_('Overview'),
				"show_records_per_page" => isset($formdata["show_records_per_page"])?$formdata["show_records_per_page"]:10,
				"show_searchbox" => true,
				"searchbox_value" => isset($formdata["grid_search_text"])?$formdata["grid_search_text"]:"",
				"pagename" => $this->translate->_('Outlet Product Code'),
				"show_selectbox" => true,
				"show_editlink" => true,
				"selected_list" => $checked,
				"show_deletelink" => false,			
				"show_deleteall" => false,
				"primaryid" => "groupcode",
				"status_cols" => array(
							   array(
							   "cols_name" => "activestatus",
							   "status_change" => array("0"=>"Inactive","1"=>"Active")
							   )
							   ),
				"editlink" => array("/account/index/addoutletproduct/id/#pattern#/edit/yes/","#pattern#"),
				"nodata_message" => $this->translate->_('No Record(s) Found'),
				"fetch_columns_inquery" => $cols_array,
				"show_columns" => $columns_show
				);
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
		$result = $this->SFA_Comman->executequery('CALL sp_get_account_index_outletproduct(?,?,?,?,?,?,?,?)',$param_array,'',$downloadCSV,$printData,2,$pagingparams);
	
		$data_arr["count"]		= $result[0][0]['counter'];
		$data_arr["data"][0] 	= $result[1];
		
		// pass the data in summary_showdatagrid() function & create a final variable for view
		$this->view->pagegridshow =  $pagingshow->summary_showdatagrid($data_arr);
		
		$this->view->addScriptPath(APPLICATION_PATH."/theme/default/datagrid/");
    }
     /**
    * @name       addoutletproductAction
    * @since      25-01-2012
    * @version    Release: 1
    * @author     HD <hiren.d@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This action is use for the add outlet product code
    */
    public function addoutletproductAction()
    {
	$this->view->params = $params = $this->getRequest()->getParams();
        $this->view->formdata = $formdata = $this->_request->getPost();

        // IF EXTRA PARAMS ARE REQUIRED
	$ex_param = "";
	if(isset($params["id"]) && $params["id"]>0)
	    $ex_param = "/key/".$params["id"];

	$this->view->itemgrid    = $this->view->BaseUrl("/".$params['module']."/".$params['controller']."/outletproductgrid".$ex_param);
	

	if($formdata['txtcode'] && $formdata['txtname'])
	{
	    if($formdata['hdnid'] > 0)
	    {	    
		$param_array = array();
		$param_array[1] = trim($formdata['txtname']); 		//groupname
		$param_array[2] = trim($formdata['txtname_arb']);	//arbgroupname
		$param_array[3] = trim($formdata['ddlstatus']);		//activestatus
		$param_array[4] = $this->currentUser->username;		//modified
		$param_array[5] = $formdata['hdnid'];			//groupcode
		
		$last_id = $this->SFA_Comman->executequery('CALL sp_edit_account_index_addoutletproduct(?,?,?,?,?)',$param_array,'');
		
		SFA_Message::setMsg($this->translate->_('Update Record'));
	    }
	    else
	    {
		$param_array = array();
		$param_array[1] = trim($formdata['txtname']); 		//groupname
		$param_array[2] = trim($formdata['txtname_arb']);	//arbgroupname
		$param_array[3] = trim($formdata['ddlstatus']);		//activestatus
		$param_array[4] = $this->currentUser->username;		//created		
		
		$last_id = $this->SFA_Comman->executequery('CALL sp_add_account_index_addoutletproduct(?,?,?,?)',$param_array,'');
		
		SFA_Message::setMsg($this->translate->_('New Record'));
	    }
	    //SFA_Comman::pre($last_id);
	   //$this->_helper->redirector('addoutletproduct', 'index', 'account',array("edit"=>"yes",'id'=>$last_id[0][0]['gcode']));
	   $this->_helper->redirector('outletproduct', 'index', 'account');
	}
	elseif($params['id'] > 0)
	{
	    $result  			= $this->SFA_Comman->executequery('CALL sp_get_account_index_addoutletproduct(?)',$params['id'],'');
	    $res['txtcode'] 		= $result[0][0]['groupcode'];
	    $res['txtname'] 		= $result[0][0]['groupname'];
	    $res['txtname_arb'] 	= $result[0][0]['arbgroupname'];
	    $res['ddlstatus'] 		= $result[0][0]['activestatus'];
	    $this->view->formdata 	= $res;
	    $this->view->item_data 	= $result[1];	    
	}	
	else
	{
	    $table_name = 'groupmaster';
	    $result = $this->SFA_Comman->executequery('CALL sp_getcombobox_account_index_addoutletproduct(?)',$table_name,'');
	    $this->view->item_data = $result[0];
	    $this->view->formdata['txtcode'] = ($result[1][0]['Auto_increment'] == '') ? '1' : $result[1][0]['Auto_increment'];
	}
    }

    /**
    * @name       outletproductgridAction
    * @since      26-01-2012
    * @version    Release: 1
    * @author     HD <hiren.d@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This action is use display get outlet product data
    */
    public function outletproductgridAction(){
		
        $this->view->params = $params = $this->getRequest()->getParams();

		// For Alternate Code Status.
		$cpanel						= $this->SFA_Comman->getaltcodestatus();
		$altcode_status				= $cpanel["Use Alternate Code"]['status'];
		
		// column to be fetched
		$columns_array 	=  array('op.itemcode','itemshortdescription','outletitemcode','primary_key as edit_del_primary_id');
		
		if($this->css == 'ar_') {
			$columns_array[1]	= 'im.arbitemshortdescription AS itemshortdescription';
		}
		if($altcode_status)
		{
			$columns_array[0] = 'im.alternatecode';
		}
		
		
		// column header to be displayed	
		$item_code              = $this->translate->_('Item Code');
		$item_desc              = $this->translate->_('ItemDescription');	
		$outlet_item_code		= $this->translate->_('Outlet Product Code');
		$columns_show  			= array($item_code,$item_desc,$outlet_item_code);
		
		
		// DELETE THE RECORD
		if($params["delete"]=="yes"){
			
			$param_array 	= array();
			$param_array[1]	= $params["id"];
			$param_array[2]	= $this->currentUser->username;
			
			// sp for delete outletproductitem
			$r_delete = $this->SFA_Comman->executequery('CALL sp_delete_account_index_outletproductgrid(?,?)',$param_array,'');
			SFA_Message::setMsg($this->translate->_('Delete Record'));
		}
	
		// UPDATE THE RECORD
		if($params["update"]=="yes")
		{   
			if($params["outletitemcode"] !='')
			{
				//$updateData["1"] = $params["itemcode"];
				$updateData["1"] = $params["outletitemcode"];
				$updateData["2"] = $params["id"];
				$updateData["3"] = $params["key"];
				
				// call sp for edit outletproductitem
				$r_edit = $this->SFA_Comman->executequery('CALL sp_edit_account_index_outletproductgrid(?,?,?)',$updateData,'');
				
				if($r_edit[0][0]['result'] == 'Success')
					SFA_Message::setMsg($this->translate->_('Update Record'));
				else
					SFA_Message::setErrorMsg($this->translate->_('Duplicate Record'));
			}
			else
			{
				SFA_Message::setErrorMsg($this->translate->_('Missing Field'));
			}
		}
		
	
		// IF EXTRA PARAMS ARE REQUIRED
		$ex_param = "";
		if(isset($params["key"]) && $params["key"]>0)
			 $ex_param = "/key/".$params["key"];
	
			// ARRAY FOR GRID PAGINATION
			$pagingparams = array(
						"show_grid_heading" => false,
						"grid_heading_message" => $this->translate->_('Overview'),
						"show_records_per_page" => isset($formdata["show_records_per_page"])?$formdata["show_records_per_page"]:10,
						"show_searchbox" => false,
						"show_selectbox" => false,
						"show_editlink" => true,
						"show_deletelink" => true,
						"show_deleteall" => false,
						"noeditfields"=> array('itemshortdescription','alternatecode','op.itemcode'),						
						"primaryid" => "primary_key",
						"currentlink" => array("/".$params['module']."/".$params['controller']."/".$params['action'].$ex_param),
						"deletelink" => array("/id/#pattern#/delete/yes","#pattern#"),
						"editlink" => array("/id/#pattern#/edit/yes","#pattern#"),
						"nodata_message" => $this->translate->_('No Record(s) Found'),
						"fetch_columns_inquery" => $columns_array,
						"show_columns" => $columns_show
						);
		if(!$this->checkaccess("update"))
		{
			$pagingparams["show_editlink"] = false;
		}
		
		if(!$this->checkaccess("delete"))
		{
			$pagingparams["show_deletelink"] = false;
		}
		
		// WHEN GRID IS IN EDIT MODE
		if($params["edit"]=="yes"){
	
			$pagingparams["editmode"] = true;
			$pagingparams["editmodeid"] = $params["id"];
			$pagingparams["editmodevalue"] = "primary_key";  // put table's prymary key here
		}
	
		//$pagingshow = new SFA_Pagingquery($pagingparams);
		$pagingshow = new SFA_Ajaxpaging($pagingparams);
		
		// call common function of grid class
		$get_return_vals = $pagingshow->commnfunc();
		
		// call the stored procedure for fetch the data  
		$param_array[1] = '1';
		$param_array[2] = $get_return_vals['order_columns_name'];
		$param_array[3] = $get_return_vals['order_type'];
		$param_array[4] = $get_return_vals['offset'];
		$param_array[5] = (int)$get_return_vals['show_records_per_page'];
		$param_array[6] = implode(", ",$pagingparams["fetch_columns_inquery"]);
		$param_array[7] = strlen($get_return_vals['where_condition'])>0?$get_return_vals['where_condition']:'';
		$param_array[8] = $params["key"];
	
		
		// called stored procedure for counter
		$result = $this->SFA_Comman->executequery('CALL sp_get_account_index_outletproductgrid(?,?,?,?,?,?,?,?)',$param_array,'');    
		$data_arr["count"] 	= $result[0][0]['counter'];	
		$data_arr["data"][0] 	= $result[1];
	
		
		#echo "<pre>"; print_r($data_arr); exit;
		$this->view->pagegridshow =  $pagingshow->summary_showdatagrid($data_arr);
		$this->view->addScriptPath(APPLICATION_PATH."/theme/default/datagrid/");
		
		$this->render("ajaxgrid");
    }
    /**
    * @name       addoutletproductcodeAction
    * @since      29-03-2012
    * @version    Release: 1
    * @author     HD <hiren.d@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This action is for add putlet product code
    *
    */
    public function addoutletproductcodeAction()
    {
        $formdata = $this->_request->getPost();
		
		$param_array 	= array();
		if($formdata['hdnid'])
		{
			$param_array[1] = trim($formdata["hdnid"]);			//groupcode
			$param_array[2] = trim($formdata["ddlitem_code"]);	//itemcode
			$param_array[3] = trim($formdata["txtprod_code"]);	//outletitemcode
			$param_array[4] = $this->currentUser->username;		//createdby for log
			
			$last_id = $this->SFA_Comman->executequery('CALL sp_add_account_index_addoutletproductcode(?,?,?,?)',$param_array,'');
			echo $val = $formdata["hdnid"];
			
		}
		else
		{
			$param_array[1] = trim($formdata['txtname']); 		//groupname
			$param_array[2] = trim($formdata['txtname_arb']);	//arbgroupname
			$param_array[3] = trim($formdata['ddlstatus']);		//activestatus		
			$param_array[4] = trim($formdata["ddlitem_code"]);	//itemcode
			$param_array[5] = trim($formdata["txtprod_code"]);	//outletitemcode
			$param_array[6] = $this->currentUser->username;		//modified			
			
			$last_id = $this->SFA_Comman->executequery('CALL sp_add_account_index_addoutletproductcodegrid(?,?,?,?,?,?)',$param_array,'');			
			echo $val = $last_id[0][0]['lastid'];
		}
		
		if($last_id[0][0]['result'] == 'insert')
			SFA_Message::setMsg($this->translate->_('New Record'));
		else
			SFA_Message::setMsg($this->translate->_('Update Record'));
		exit;
    }
    /**
    * @name       expirymanagementAction
    * @since      26-01-2012
    * @version    Release: 1
    * @author     HD <hiren.d@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This action is use for the add salesman messages
    */
    public function expirymanagementAction()
    {
		$this->view->params = $params = $this->getRequest()->getParams();
        $this->view->formdata = $formdata = $this->_request->getPost();

        // IF EXTRA PARAMS ARE REQUIRED
		$ex_param = "";
		if(isset($params["id"]) && $params["id"]>0)
			$ex_param = "/key/".$params["id"];
	
		$this->view->itemgrid    = $this->view->BaseUrl("/".$params['module']."/".$params['controller']."/expirymanagementgrid".$ex_param);

		$result = $this->SFA_Comman->executequery('CALL sp_combo_routemaster()','','');
		$this->view->route_info = $result[0];		
    }

    /**
    * @name       expirymanagementgridAction
    * @since      26-01-2012
    * @version    Release: 1
    * @author     HD <hiren.d@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This action is use display get outlet product data
    */
    public function expirymanagementgridAction(){
        
		$params = $this->getRequest()->getParams();
	
		// For Alternate Code Status.
		$cpanel						= $this->SFA_Comman->getaltcodestatus();
		$altcode_status				= $cpanel["Use Alternate Code"]['status'];
		
		if($altcode_status)
		{
			$columns_array 	= array('cm.alternatecode','cm.customername','FORMAT(cm.creditlimit,'.$this->decimalplaces.') AS creditlimit','FORMAT(IFNULL(SUM(totalinvoiceamount),0),'.$this->decimalplaces.') AS netsales','FORMAT(IFNULL(cm.expirylimit,0),'.$this->decimalplaces.') AS expirylimit','cm.customercode as edit_del_primary_id');
			$columns_show  	= array($this->translate->_('Customer Code'),$this->translate->_('Customer Name'),$this->translate->_('Credit Limit'),$this->translate->_('Net Sales'),$this->translate->_('Expiry Limit'));
		}
		else
		{
			$columns_array 	= array('cm.customercode','cm.customername','FORMAT(cm.creditlimit,'.$this->decimalplaces.') AS creditlimit','FORMAT(IFNULL(SUM(totalinvoiceamount),0),'.$this->decimalplaces.') AS netsales','FORMAT(IFNULL(cm.expirylimit,0),'.$this->decimalplaces.')  AS expirylimit','cm.customercode as edit_del_primary_id');
			$columns_show  	= array($this->translate->_('Customer Code'),$this->translate->_('Customer Name'),$this->translate->_('Credit Limit'),$this->translate->_('Net Sales'),$this->translate->_('Expiry Limit'));
		}
		if($this->css == 'ar_') {
			$columns_array[1]	= 'cm.arbcustomername AS arbcustomername';
		}
		
		// UPDATE THE RECORD
		if($params["update"]=="yes")
		{
			$param_array    = array();
			$param_array[1] = 0;
			$param_array[2] = $params['expirylimit'];
			$param_array[3] = 0;
			$param_array[4] = $params["id"]; // means update from grid
			
			$result = $this->SFA_Comman->executequery('CALL sp_edit_account_index_expirymanagement(?,?,?,?)',$param_array,'');
			SFA_Message::setMsg($this->translate->_('Update Record'));
		}
		
		if($params['apply']== 'true')
		{
			$param_array    = array();
			$param_array[1] = $params['ddlroute'];
			$param_array[2] = $params['txtamount'];
			$param_array[3] = $params['chkequally'];
			$param_array[4] = 0; // means customercode 0
			
			$result = $this->SFA_Comman->executequery('CALL sp_edit_account_index_expirymanagement(?,?,?)',$param_array,'');
			SFA_Message::setMsg($this->translate->_('Update Record'));
		}
		
		// IF EXTRA PARAMS ARE REQUIRED
		$ex_param = "";
		if(isset($params["rid"]) && $params["rid"]>0){
			$additional_where_condition = array();
			$ex_param = "/rid/".$params["rid"];
			$additional_where_condition[] = ' (	cm.routecode = "'.$params['rid'].'" )';
			$additional_where_condition[] = ' (	cm.activecustomer = 1 AND cm.templateindicator = 0 )';			
		}
//'expirylimit'
		// ARRAY FOR GRID PAGINATION
		$pagingparams = array(
					"show_grid_heading" => false,
					"grid_heading_message" => $this->translate->_('Overview'),
					"show_records_per_page" => isset($formdata["show_records_per_page"])?$formdata["show_records_per_page"]:10,
					"show_searchbox" => false,
					"show_selectbox" => false,
					"show_deleteall" => false,
					"show_editlink" => true,
					"pagename" => $this->translate->_('Expiry Management'),
					"primaryid" => "customercode",
					"noeditfields" => array('alternatecode','customercode','creditlimit','customername','netsales'),
					"currentlink" => array("/".$params['module']."/".$params['controller']."/".$params['action'].$ex_param),					
					"nodata_message" => $this->translate->_('No Record(s) Found'),
					"editlink" => array("/id/#pattern#/edit/yes","#pattern#"),
					"fetch_columns_inquery" => $columns_array,
					"show_columns" => $columns_show,
					"additional_where" => $additional_where_condition,
					"show_columns_right_side"=>array('creditlimit','netsales','expirylimit'),
					"show_header_right_side"=>array($this->translate->_('Credit Limit'),$this->translate->_('Net Sales'),$this->translate->_('Expiry Limit')),
				    );
		
		// WHEN GRID IS IN EDIT MODE
		if($params["edit"]=="yes") {	
			$pagingparams["editmode"] = true;
			$pagingparams["editmodeid"] = $params["id"];
			$pagingparams["editmodevalue"] = "customercode";  // put table's prymary key here
		}

		//$pagingshow = new SFA_Pagingquery($pagingparams);
		$pagingshow = new SFA_Ajaxpaging($pagingparams);
		
		// call common function of grid class
		$get_return_vals = $pagingshow->commnfunc();
		
		// call the stored procedure for fetch the data
		$param_array 	 = array();
		$param_array[1]  = '1';
		$param_array[2]  = $get_return_vals['order_columns_name'];
		$param_array[3]  = $get_return_vals['order_type'];
		$param_array[4]  = $get_return_vals['offset'];
		$param_array[5]  = (int)$get_return_vals['show_records_per_page'];
		$param_array[6]  = implode(", ",$pagingparams["fetch_columns_inquery"]);
		$param_array[7]  = strlen($get_return_vals['where_condition'])>0?$get_return_vals['where_condition']:'';		
		
		$downloadCSV = (isset($formdata['downloadcsv'])) ? $formdata['downloadcsv'] : $params['downloadcsv'];
    
		// Hiren Dave on 19 Nov, 2012 - START - to check if need to print data
		$printData = (isset($formdata['printData'])) ? $formdata['printData'] : $params['printData'];
		
		$result = $this->SFA_Comman->executequery('CALL sp_get_account_index_expirymanagement(?,?,?,?,?,?,?)',$param_array,'',$downloadCSV,$printData,2,$pagingparams);
		
		$data_arr["count"] 		= $result[0][0]['counter'];
		$data_arr["data"][0] 	= $result[1];
		
		#echo "<pre>"; print_r($data_arr); exit;
		$this->view->pagegridshow =  $pagingshow->summary_showdatagrid($data_arr);
		$this->view->addScriptPath(APPLICATION_PATH."/theme/default/datagrid/");
	
		$this->render("ajaxgrid");
    }

    /**
    * @name       journeyplanAction
    * @since      26-01-2012
    * @version    Release: 1
    * @author     HD <hiren.d@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This action is use for journey plan credit limit
    */
    public function journeyplanAction()
    {
		$this->view->params = $params = $this->getRequest()->getParams();
        $this->view->formdata = $formdata = $this->_request->getPost();

		$this->view->show_grid = '0';


        $route_code 		= $this->translate->_('Route Code');
	    $route_name 		= $this->translate->_('Route Name');
	    $creditlimit 		= $this->translate->_('Credit Limit');
		
		$result = $this->SFA_Comman->executequery('CALL sp_get_account_index_journeyplan()','','');
		$this->view->depot_data 	= $result[0];
		$this->view->customer_data 	= $result[1];

	    $this->view->show_grid	= '1';
        
        if(!$this->checkaccess("update"))
        {
            $pagingparams["show_editlink"] = false;
        }
        
        if(!$this->checkaccess("delete"))
        {
            $pagingparams["show_deletelink"] = false;
        }
		if(count($formdata) > 0) {
			if($formdata['hdnid'] > 0)
				SFA_Message::setMsg($this->translate->_('Update Record'));
			else
				SFA_Message::setMsg($this->translate->_('New Record'));

			$this->_helper->redirector('journeyplan', 'index', 'account');
		}
    }
	/**
    * @name       getdepotcodecustomer
    * @since      16-02-2012
    * @version    Release: 1
    * @author     HD <hiren.d@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This action is use for getcustomer based on depotcode
    */
	public function getdepotcodecustomerAction()
	{
		$params = $this->getRequest()->getParams();
		$result = $this->SFA_Comman->executequery('CALL sp_combo_journeyplancustomer(?)',$params['depotcode'],'');
		echo Zend_Json::encode($result);
		exit;
	}
    /**
    * @name       journeyplangrid
    * @since      16-02-2012
    * @version    Release: 1
    * @author     HD <hiren.d@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This action is use for delete journey
    */
    public function journeyplangridAction()
    {
        $this->view->params = $params = $this->getRequest()->getParams();
		
		$columns_array 	= array('rm.routecode','rm.routename AS routename','sm.salesmanname1','FORMAT(0,'.$this->decimalplaces.') AS creditlimit_xyz ','rm.routecode as edit_del_primary_id');
		$columns_show  	= array($this->translate->_('Route Code'),$this->translate->_('Route Name'),$this->translate->_('Salesman Name'),$this->translate->_('Credit Limit'));
		
		if($this->css == 'ar_') {
			$columns_array[1]	= 'rm.arbroutename AS routename';
		}
		
		if($params['apply']== 'true') {
			$param_array    = array();
			$param_array[1] = $params['ddlcustomer'];
			$param_array[2] = $params['route_ids'];
			$param_array[3] = $params['credit_limits'];
			$param_array[4] = $params['total_record'];
			$param_array[5] = $this->currentUser->username;
			
			$result = $this->SFA_Comman->executequery('CALL sp_add_account_index_addjourneycreditplan(?,?,?,?,?)',$param_array,'');
			SFA_Message::setMsg($this->translate->_('Update Record'));
		}
		
		
		// IF EXTRA PARAMS ARE REQUIRED
		$ex_param = "";
		$additional_where_condition = array();
		if(isset($params["ddldepot"])) {			
			$ex_param = "/key1/".$params["ddldepot"]."/key2/".$params["ddlcustomer"];			
			$additional_where_condition[] = ' ( dm.depotcode = "'.$params["ddldepot"].'") ';
			if($params["ddlcustomer"]) {
				$additional_where_condition[] = ' ( cm.customercode = "'.$params["ddlcustomer"].'") ';
			}
		}
		
		// ARRAY FOR GRID PAGINATION
		$pagingparams = array (
				"show_grid_heading" => false,
				"grid_heading_message" => $this->translate->_('Overview'),
				"show_records_per_page" => isset($formdata["show_records_per_page"])?$formdata["show_records_per_page"]:10000000000000,
				"show_searchbox" => false,
				"show_selectbox" => false,
				"show_editlink" => false,
				"show_deletelink" => false,
				"show_deleteall" => false,
				"show_datasorting" => '1',
				"primaryid" => "rm.routecode",				
				"currentlink" => array("/".$params['module']."/".$params['controller']."/".$params['action'].$ex_param),
				"nodata_message" => $this->translate->_('No Record(s) Found'),
				"fetch_columns_inquery" => $columns_array,
				"show_columns" => $columns_show,
				"show_columns_right_side"=>array('creditlimit'),
				//"show_header_right_side"=>array($this->translate->_('Credit Limit')),
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
		
		if($params["ddlcustomer"] > 0){
			$param_array[8] = $params["ddldepot"];
			$param_array[9] = $params["ddlcustomer"];
			$param_array[10]= $this->decimalplaces;
			$result = $this->SFA_Comman->executequery('CALL sp_get_account_index_journeyplandetailgridbycustomer(?,?,?,?,?,?,?)',$param_array,'');
		}
		else{
			$result = $this->SFA_Comman->executequery('CALL sp_get_account_index_journeyplandetailgrid(?,?,?,?,?,?,?)',$param_array,'');
		}
		
		$data_arr["count"] 		= ($result[0][0]['counter']+$result[0][1]['counter']);
		$data_arr["data"][0] 	= $result[1];		
		
		$this->view->pagegridshow =  $pagingshow->summary_showdatagrid($data_arr);
		$this->view->addScriptPath(APPLICATION_PATH."/theme/default/datagrid/");
	
		$this->render("ajaxgrid");
    }
}