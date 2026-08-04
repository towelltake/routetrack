<?php
/**
* @name       PosController
* @since
* @version    Release: 1
* @author     PM <pankit@elantechnologies.com>
* @copyright  Elan Technologies
* @param
*
* This controller is manage user inventory module.
*/
class Inventory_PosController extends Inventory_Library_Controller_Action_Abstract
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
        $this->view->general	= $this->translate->_('General');
        $this->view->setting1	= $this->translate->_('Settings 1');
        $this->view->details	= $this->translate->_('Details');
        $this->view->required	= $this->translate->_('Required');
        $this->view->colan	= $this->translate->_('Colan');
            $this->common_model 	= new SFA_Model_Index();
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
    * @name       index
    * @since      6-2-2012
    * @version    Release: 1
    * @author     PM <pankit@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This action is use for point of sale list
    */
    public function indexAction()
    {
        $this->view->formdata = $formdata = $this->_request->getPost();
		$this->view->params = $params = $this->getRequest()->getParams();
		
	 
		if($formdata["hdDelete"]==1)
		{
			$ids = implode(',',$formdata['chk']);
			$param_array 	= array();
			$param_array[1]	= $ids;
			$param_array[2]	= $this->currentUser->username;
			
			$result = $this->SFA_Comman->executequery('CALL sp_delete_inventory_pos_pos(?,?)',$param_array,'');
			
			
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
	
		
		$cols_array 	= array('itemcode','itemdescription','arbitemdescription');
		$columns_show 	=  array($this->translate->_('Code'),$this->translate->_('Name'),$this->translate->_('Name ('.$this->sec_lang.')'));
			
		// prepare the configuration for grid
		$pagingparams = array(
				"show_grid_heading" => true,
				"grid_heading_message" => $this->translate->_('Overview'),
                "pagename" => $this->translate->_('POS'),
				"show_records_per_page" => isset($formdata["show_records_per_page"])?$formdata["show_records_per_page"]:10,
				"show_searchbox" => true,
                "searchbox_value" => isset($formdata["grid_search_text"])?$formdata["grid_search_text"]:"",
				"show_selectbox" => true,
				"selected_list" => $checked,
				"show_editlink" => true,
				"show_deletelink" => false,
				"show_deleteall" => false,			
				"primaryid" => "itemcode",
				"editlink" => array("/inventory/pos/addpos/id/#pattern#/edit/yes/","#pattern#"),
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
		$result = $this->SFA_Comman->executequery('CALL sp_get_inventory_pos_pos(?,?,?,?,?,?,?,?)',$param_array,'',$downloadCSV,$printData,2,$pagingparams);
	
		$data_arr["count"]	= $result[0][0]['counter'];
		$data_arr["data"][0]	= $result[1];
		
		// pass the data in summary_showdatagrid() function & create a final variable for view
		$this->view->pagegridshow =  $pagingshow->summary_showdatagrid($data_arr);
		
		$this->view->addScriptPath(APPLICATION_PATH."/theme/default/datagrid/");
    }

    /**
    * @name       addposAction
    * @since      6-2-2012
    * @version    Release: 1
    * @author     PM <pankit@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This action is use for deatil view of point of sale
    */
    public function addposAction()
    {
        $this->view->params = $params = $this->getRequest()->getParams();
        $this->view->formdata = $formdata = $this->_request->getPost();

	$this->view->returnUrl = $_SERVER["HTTP_REFERER"];

	if($formdata['txtitemdesc'] !='')
	{
	    $param_array 	= array();
	    $param_array[1]	= $formdata['txtaltcode'];
	    $param_array[2]	= $formdata['txtitemdesc'];
	    $param_array[3]	= $formdata['txtarbitemdesc'];
	    $param_array[4]	= $formdata['txtitemvalue'];
	    $param_array[5]	= $formdata['ddlinventory_type'];	    
	    $param_array[6]	= $this->currentUser->username;
	    
	    if($formdata['hdnid'] > 0)
	    {
		$param_array[7]	= $formdata['hdnid'];
		$last_id = $this->SFA_Comman->executequery('CALL sp_edit_inventory_pos_addpos(?,?,?,?,?,?,?)',$param_array,'');
		SFA_Message::setMsg($this->translate->_('Update Record'));
	    }
	    else
	    {
		$last_id = $this->SFA_Comman->executequery('CALL sp_add_inventory_pos_addpos(?,?,?,?,?,?)',$param_array,'');
		SFA_Message::setMsg($this->translate->_('New Record'));
	    }	
	    if($formdata["returnUrl"]!="")
		$this->_redirect($formdata["returnUrl"]);
	    else
		$this->_helper->redirector('index', 'pos', 'inventory');
	}
	elseif($params['id'] > 0)
	{
	    $result = $this->SFA_Comman->executequery('CALL sp_get_inventory_pos_addpos(?)',$params['id'],'');
	    
	    $res['txtcode']		= $result[0][0]['itemcode'];
	    $res['txtaltcode']		= $result[0][0]['alternatecode'];
	    $res['txtitemdesc']		= $result[0][0]['itemdescription'];
	    $res['txtarbitemdesc']	= $result[0][0]['arbitemdescription'];
	    $res['txtitemvalue']	= number_format($result[0][0]['itemvalue'],$this->decimalplaces);
	    $res['ddlinventory_type']	= $result[0][0]['inventorytype'];	    
	    $res['createddate']		= date('d-m-Y',strtotime($result[0][0]['cdat']));
	    $this->view->formdata 	= $res;
	}
	else
	{
	    $result = $this->SFA_Comman->executequery('CALL sp_get_inventory_pos_addpos(?)','0','');
	    $this->view->formdata['txtcode']	= ($result[0][0]['Auto_increment'] == '') ? '1' : $result[0][0]['Auto_increment'];
	}
    }

     /**
    * @name       posinstructionAction
    * @since      6-2-2012
    * @version    Release: 1
    * @author     PM <pankit@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This action is use for point of sale instruction list
    */
    public function posinstructionAction(){
	
	$this->view->formdata = $formdata = $this->_request->getPost();        
	$this->view->params = $params = $this->getRequest()->getParams();
	
	if($formdata["hdDelete"]==1)
	{
	    $ids = implode(',',$formdata['chk']);
	    $param_array 	= array();
	    $param_array[1]	= $ids;
	    $param_array[2]	= $this->currentUser->username;
	    
	    $result = $this->SFA_Comman->executequery('CALL sp_delete_inventory_pos_posinstruction(?,?)',$param_array,'');
	    
	    
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

	
	$cols_array 	= array('posinstructioncode','posinstructionname','arbposinstructionname');
	$columns_show 	=  array($this->translate->_('Code'),$this->translate->_('Name'),$this->translate->_('Name ('.$this->sec_lang.')'));	

	// prepare the configuration for grid
	$pagingparams = array(
			"show_grid_heading" => true,
			"grid_heading_message" => $this->translate->_('Overview'),
			"show_records_per_page" => isset($formdata["show_records_per_page"])?$formdata["show_records_per_page"]:10,
            "pagename" => $this->translate->_('POS Instruction'),
			"show_searchbox" => true,
            "searchbox_value" => isset($formdata["grid_search_text"])?$formdata["grid_search_text"]:"",
			"show_selectbox" => true,
			"selected_list" => $checked,
			"show_editlink" => true,
			"show_deletelink" => false,
			"show_deleteall" => false,			
			"primaryid" => "posinstructioncode",
			"editlink" => array("/inventory/pos/addposinstruction/id/#pattern#/edit/yes/","#pattern#"),
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
	$result = $this->SFA_Comman->executequery('CALL sp_get_inventory_pos_posinstruction(?,?,?,?,?,?,?,?)',$param_array,'',$downloadCSV,$printData,2,$pagingparams);

	$data_arr["count"]	= $result[0][0]['counter'];
	$data_arr["data"][0]	= $result[1];
	
	// pass the data in summary_showdatagrid() function & create a final variable for view
	$this->view->pagegridshow =  $pagingshow->summary_showdatagrid($data_arr);
	
	$this->view->addScriptPath(APPLICATION_PATH."/theme/default/datagrid/");

    }

    /**
    * @name       addposinstructionAction
    * @since      6-2-2012
    * @version    Release: 1
    * @author     PM <pankit@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This action is use for deatil view of point of sale instruction
    */
    public function addposinstructionAction(){
         $this->view->params = $params = $this->getRequest()->getParams();
        $this->view->formdata = $formdata = $this->_request->getPost();

	$this->view->returnUrl = $_SERVER["HTTP_REFERER"];

	if($formdata['txtname'] !='')
	{
	    $param_array 	= array();
	    $param_array[1]	= $formdata['txtaltcode'];
	    $param_array[2]	= $formdata['txtname'];
	    $param_array[3]	= $formdata['txtarbname'];	    
	    $param_array[4]	= $this->currentUser->username;
	    
	    if($formdata['hdnid'] > 0)
	    {
		$param_array[5]	= $formdata['hdnid'];
		$last_id = $this->SFA_Comman->executequery('CALL sp_edit_inventory_pos_addposinstruction(?,?,?,?,?)',$param_array,'');
		SFA_Message::setMsg($this->translate->_('Update Record'));
	    }
	    else
	    {
		$last_id = $this->SFA_Comman->executequery('CALL sp_add_inventory_pos_addposinstruction(?,?,?,?)',$param_array,'');
		SFA_Message::setMsg($this->translate->_('New Record'));
	    }	
	    if($formdata["returnUrl"]!="")
		$this->_redirect($formdata["returnUrl"]);
	    else
		$this->_helper->redirector('posinstruction', 'pos', 'inventory');
	}
	elseif($params['id'] > 0)
	{
	    $result = $this->SFA_Comman->executequery('CALL sp_get_inventory_pos_addposinstruction(?)',$params['id'],'');
	    
	    $res['txtcode']		= $result[0][0]['posinstructioncode'];
	    $res['txtaltcode']		= $result[0][0]['alternatecode'];
	    $res['txtname']		= $result[0][0]['posinstructionname'];
	    $res['txtarbname']		= $result[0][0]['arbposinstructionname'];
	    $res['createddate']		= date('d-m-Y',strtotime($result[0][0]['cdat']));
	    $this->view->formdata 	= $res;
	}
	else
	{
	    $result = $this->SFA_Comman->executequery('CALL sp_get_inventory_pos_addposinstruction(?)','0','');
	    $this->view->formdata['txtcode']	= ($result[0][0]['Auto_increment'] == '') ? '1' : $result[0][0]['Auto_increment'];
	}
    }

    /**
    * @name       customerposlimit
    * @since      7-2-2012
    * @version    Release: 1
    * @author     PM <pankit@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This action is use for the customer pos limit list
    */
    public function customerposlimitAction(){

        $this->view->formdata = $formdata = $this->_request->getPost();

        if($formdata["hdDelete"]==1)
	{
	    $ids = implode(',',$formdata['chk']);
	    $param_array 	= array();
	    $param_array[1]	= $ids;
	    $param_array[2]	= $this->currentUser->username;
	    
	    $result = $this->SFA_Comman->executequery('CALL sp_delete_inventory_pos_customerposlimit(?,?)',$param_array,'');
	    
	    
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
	
	
	$cols_array 	= array('customername','poslimit','posbalance','primary_key as edit_del_primary_id');
	$columns_show 	=  array($this->translate->_('Customer Name'),$this->translate->_('POS Limit'),$this->translate->_('POS Balance'));
	
    if($this->css == 'ar_') {
        $cols_array[0]	= 'arbcustomername AS customername';
    }

	// prepare the configuration for grid
	$pagingparams = array(
			"show_grid_heading" => true,
			"grid_heading_message" => $this->translate->_('Overview'),
            "pagename" => $this->translate->_('Customer POS Limit'),
			"show_records_per_page" => isset($formdata["show_records_per_page"])?$formdata["show_records_per_page"]:10,
			"show_searchbox" => true,
            "searchbox_value" => isset($formdata["grid_search_text"])?$formdata["grid_search_text"]:"",
			"show_selectbox" => true,
			"selected_list" => $checked,
			"show_editlink" => true,
			"show_deletelink" => false,
			"show_deleteall" => false,			
			"primaryid" => "primary_key",
			"editlink" => array("/inventory/pos/addcustomerposlimit/id/#pattern#/edit/yes/","#pattern#"),
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
	$result = $this->SFA_Comman->executequery('CALL sp_get_inventory_pos_customerposlimit(?,?,?,?,?,?,?,?)',$param_array,'',$downloadCSV,$printData,2,$pagingparams);

	$data_arr["count"]	= $result[0][0]['counter'];
	$data_arr["data"][0]	= $result[1];
	
	// pass the data in summary_showdatagrid() function & create a final variable for view
	$this->view->pagegridshow =  $pagingshow->summary_showdatagrid($data_arr);
	
	$this->view->addScriptPath(APPLICATION_PATH."/theme/default/datagrid/");
    }

    /**
    * @name       addcustomerposlimit
    * @since      7-2-2012
    * @version    Release: 1
    * @author     PM <pankit@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This action is use for the customer pos limit list
    */
    public function addcustomerposlimitAction(){
        $this->view->params = $params = $this->getRequest()->getParams();
        $this->view->formdata = $formdata = $this->_request->getPost();
	
	$this->view->returnUrl = $_SERVER["HTTP_REFERER"];

      // IF EXTRA PARAMS ARE REQUIRED
	$ex_param = "";
	if(isset($params["id"]) && $params["id"]>0)
	    $ex_param = "/key/".$params["id"];

	
        
        if(($formdata['ddlcustomer'] > 0 || $formdata['hdncustomercode'] > 0) && $formdata['txtposlimit'] !='')
	{
	    $param_array 	= array();
	    $param_array[1] 	= $formdata['ddlcustomer'];
	    $param_array[2] 	= $formdata['txtposlimit'];
	    $param_array[3] 	= $this->currentUser->username;
            if($formdata['hdnid'] > 0)
	    {
		$param_array[1] = $formdata['hdncustomercode'];
		$param_array[4]	= $formdata['hdnid'];
		
		$this->SFA_Comman->executequery("CALL sp_edit_inventory_pos_addcustomerposlimit(?,?,?,?)",$param_array,'');
		SFA_Message::setMsg($this->translate->_('Update Record'));
		
		if($formdata["returnUrl"]!="")
		    $this->_redirect($formdata["returnUrl"]);
		else
		    $this->_helper->redirector('customerposlimit', 'pos', 'inventory');
	    }
	    else
	    {
		$last_id = $this->SFA_Comman->executequery("CALL sp_add_inventory_pos_addcustomerposlimit(?,?,?)",$param_array,'');
		SFA_Message::setMsg($this->translate->_('New Record'));
		
		$last_id = $last_id[0][0]['result'];
		
		if($formdata["returnUrl"]!="")
		    $this->_redirect($formdata["returnUrl"]);
		else
		    $this->_helper->redirector('addcustomerposlimit', 'pos', 'inventory',array('edit'=>'yes','id'=>$last_id));
	    }
	    
	    
	}
	elseif($params['id'] > 0)
	{
	    $result = $this->SFA_Comman->executequery('CALL sp_get_inventory_pos_addcustomerposlimit("?")',$params['id'],'');
	    $this->view->item_info 	= $result[0];
	    $this->view->customer_info 	= $result[1];
	    $this->view->formdata 	= $result[2][0];
	    
	   // SFA_Comman::pre($result[2][0]);
	    
	    $this->view->itemgrid    = $this->view->BaseUrl("/".$params['module']."/".$params['controller']."/customerposlimitgrid".$ex_param);
	}
	else
	{
	    $result = $this->SFA_Comman->executequery('CALL sp_combo_poscustomer("?")','?','');
	    $this->view->customer_info 	= $result[0];
	}
    }
    /**
    * @name       addcustomerposlimit
    * @since      7-2-2012
    * @version    Release: 1
    * @author     PM <pankit@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This action is use for the display detail in customer pos limit list
    */
    public function customerposlimitgridAction()
    {
        $this->view->params = $params = $this->getRequest()->getParams();

	// column to be fetched
	$item_code			= $this->translate->_('POS Code');
        $description			= $this->translate->_('POS Description');
        $quantity			= $this->translate->_('Quantity');
        $serialnumber			= $this->translate->_('Serial Number');
	
	

	// column header to be displayed
	$columns_show  	= array($item_code,$description,$quantity,$serialnumber);
	$columns_array 	= array('pos_inv.itemcode','itemdescription','quantity','serialnumber','table_pk as edit_del_primary_id');
    
    if($this->css == 'ar_') {
        $columns_array[1]	= 'arbitemdescription AS itemdescription';
    }
	
	// DELETE THE RECORD
	if($params["delete"]=="yes"){
	    // sp for delete pos
	    $param_array 	= array();
	    $param_array[1] 	= $params["id"];
	    $param_array[2] 	= $this->currentUser->username;	    
	    
	    $r_delete = $this->SFA_Comman->executequery('CALL sp_delete_inventory_pos_addcustomerposlimitgrid(?,?)',$param_array,'');
            SFA_Message::setMsg($this->translate->_('Delete Record'));
	    
	    $posbalance = $r_delete[0][0]['posbalance'];
	    
	    echo '<script type="text/javascript">
			if($("#txtposbalance")) {
			    $("#txtposbalance").attr("readOnly",false)
			    $("#txtposbalance").val('.$posbalance.');
			    $("#txtposbalance").attr("readOnly",true)
			}
		    </script>';
	}

	// UPDATE THE RECORD
	if($params["update"]=="yes"){

	    $updateData["1"] = $params["quantity"];
	    $updateData["2"] = $params["serialnumber"];
	    $updateData["3"] = $params["id"];
        $updateData["4"] = $this->currentUser->username;
	    
	    // call sp for edit pos
	    $r_edit = $this->SFA_Comman->executequery('CALL sp_edit_inventory_pos_addcustomerposlimitgrid(?,?,?,?)',$updateData,'');
	    SFA_Message::setMsg($this->translate->_('Update Record'));
	}

	// IF EXTRA PARAMS ARE REQUIRED
	$ex_param = "";
	$additional_where_condition = array();
	if(isset($params["key"]) && $params["key"]>0){
	    $ex_param = "/key/".$params["key"];
	    $additional_where_condition[] = ' (customercode = "'.$params["customercode"].'" )';
	}

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
				     "primaryid" => "table_pk",
				     "noeditfields" =>array("itemcode","itemdescription"),
				     "currentlink" => array("/".$params['module']."/".$params['controller']."/".$params['action'].$ex_param),
				     "deletelink" => array("/id/#pattern#/delete/yes","#pattern#"),
				     "editlink" => array("/id/#pattern#/edit/yes","#pattern#"),
				     "nodata_message" => $this->translate->_('No Record(s) Found'),
				     "fetch_columns_inquery" => $columns_array,
				     "show_columns" => $columns_show,
				     "additional_where" => $additional_where_condition
				     );

	// WHEN GRID IS IN EDIT MODE
	if($params["edit"]=="yes"){

	    $pagingparams["editmode"] 		= true;
	    $pagingparams["editmodeid"] 	= $params["id"];
	    $pagingparams["editmodevalue"] 	= "table_pk";  // put table's prymary key here
	}

	
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

	
	// called stored procedure for counter
	$result = $this->SFA_Comman->executequery('CALL sp_get_inventory_pos_customerposlimitgrid(?,?,?,?,?,?,?)',$param_array,'');
    
	$data_arr["count"]	= $result[0][0]['counter'];
	$data_arr["data"][0] 	= $result[1];
	
	$this->view->pagegridshow =  $pagingshow->summary_showdatagrid($data_arr);
	$this->view->addScriptPath(APPLICATION_PATH."/theme/default/datagrid/");

	$this->render("ajaxgrid");
    }
    /**
    * @name       addcustomerposlimitgridAction
    * @since      7-2-2012
    * @version    Release: 1
    * @author     PM <pankit@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This action is use for the add detail in customer pos limit list
    */
    public function addcustomerposlimitgridAction()
    {
	$formdata = $this->_request->getPost();
	$param_array 	= array();
	$param_array[1] = trim($formdata["hdncustomercode"]);	//customercode
	$param_array[2] = trim($formdata["ddlitem"]);		//itemcode
	$param_array[3] = trim($formdata["txtquantity"]);	//quantity
	$param_array[4] = trim($formdata["txtserialnumber"]);	//serialnumber
    $param_array[5] = $this->currentUser->username;
	
	
	$result = $this->SFA_Comman->executequery('CALL sp_add_inventory_pos_addcustomerposlimitgrid(?,?,?,?)',$param_array,'');
	
	if($result[0][0]['result'] == 'Duplicate'){
	    SFA_Message::setErrorMsg($this->translate->_('Duplicate entry'));
	    echo 'Duplicate';
	}
	elseif($result[0][0]['result'] == 'Empty'){
	    SFA_Message::setErrorMsg($this->translate->_('Your POS balance is Zero.'));
	    echo 'Duplicate';
	}
	else{
	    echo $result[0][0]['posbalance'];
	    SFA_Message::setMsg($this->translate->_('New Record'));
	}
	exit;
    }
    /**
    * @name       checkiteminventorytypeAction
    * @since      7-2-2012
    * @version    Release: 1
    * @author     PM <pankit@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This action is use for the add detail in customer pos limit list
    */
    public function checkiteminventorytypeAction()
    {
	$params = $this->getRequest()->getParams();
	$inventorttype = $this->SFA_Comman->executequery('CALL sp_check_inventory_pos_iteminventorytype(?)',$params['item_id'],'');
	
	if( $inventorttype[0][0]['inventorytype']=='0') { 
	    $var = '<label class="_20 margin_top_15 label_text_align">
		    '.$this->translate->_("Quantity")." ".$this->view->required.'
	    </label>
		<div class="_25">
		    <input type="text" value="" class="number" maxlength="18"  name="txtquantity" id="txtquantity">
		</div>
		<input type="hidden" id="hdnoption" name="hdnoption" value="1">';
		
	} else { 
	    $var = '<label class="_20 margin_top_17 label_text_align">
			'.$this->translate->_("Serial Number")." ".$this->view->required.'
		</label>
		<div class="_25">
			<input type="text" value="" name="txtserialnumber" maxlength="20" id="txtserialnumber">
		</div>
		<input type="hidden" id="hdnoption" name="hdnoption" value="2">';
	 }
	 echo $var;
	exit;
    }

}
