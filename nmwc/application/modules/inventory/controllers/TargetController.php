<?php
/**
* @name       TargetController
* @since
* @version    Release: 1
* @author     PM <pankit@elantechnologies.com>
* @copyright  Elan Technologies
* @param
*
* This controller is manage user inventory module.
*/
class Inventory_TargetController extends Inventory_Library_Controller_Action_Abstract
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
	$this->decimalplaces 		= $this->SFA_Comman->getdecimalplaces();

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
    * @name       itwempackage
    * @since      6-2-2012
    * @version    Release: 1
    * @author     PM <pankit@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This action is use for deatil view of item package lists
    */
    public function itempackageAction(){
       //view variable declaration
       $this->view->formdata = $formdata = $this->_request->getPost();
       $this->view->params = $params = $this->getRequest()->getParams();
		//For checking to display alternate code or not.
		$Settings_NameSpace = new Zend_Session_Namespace('Settings');

        if($formdata["hdDelete"]==1)
		{
			$ids = implode(',',$formdata['chk']);
			$param_array 	= array();
			$param_array[1]	= $ids;
			$param_array[2]	= $this->currentUser->username;
			
			$result = $this->SFA_Comman->executequery('CALL sp_delete_inventory_target_itempackage(?,?)',$param_array,'');	    
			
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

        //variable declaration for grid title
		$code			= $this->translate->_('Code');
		$description	= $this->translate->_('Item Group Description');
		$status	        = $this->translate->_('Status');
		
		
		if($Settings_NameSpace->cpanel["Use Alternate Code"]['status']) {
			$cols_array 	= array('packagecode','alternatecode','packagedescription','activestatus');
			$columns_show 	= array($this->translate->_('Group ID'),$this->translate->_('Alternate Code'),$this->translate->_('Description'),$this->translate->_('Status'));
		}
		else
		{
			$cols_array 	= array('packagecode','packagedescription','activestatus');
			$columns_show 	= array($this->translate->_('Group ID'),$this->translate->_('Description'),$this->translate->_('Status'));
		}
		
		if($this->css == 'ar_') {
			$cols_array[2]	= 'arbpackagedescription  AS packagedescription';
		}
		
		// prepare the configuration for grid
		$pagingparams = array(
				"show_grid_heading" => true,
				"grid_heading_message" => $this->translate->_('Overview'),
				"pagename" => $this->translate->_('Item Package'),
				"show_records_per_page" => isset($formdata["show_records_per_page"])?$formdata["show_records_per_page"]:10,
				"show_searchbox" => true,
				"searchbox_value" => isset($formdata["grid_search_text"])?$formdata["grid_search_text"]:"",
				"show_selectbox" => true,
				"selected_list" => $checked,
				"show_editlink" => true,
				"show_deletelink" => false,
				"status_cols" => array(
							array(
							"cols_name" => "activestatus",
							"status_change" => array("0"=>"Inactive","1"=>"Active")
							)
							),
				"show_deleteall" => false,			
				"primaryid" => "packagecode",
				"editlink" => array("/inventory/target/additempackage/id/#pattern#/edit/yes/","#pattern#"),
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
		$result = $this->SFA_Comman->executequery('CALL sp_get_inventory_target_itempackage(?,?,?,?,?,?,?,?)',$param_array,'',$downloadCSV,$printData,2,$pagingparams);
	
		$data_arr["count"]	= $result[0][0]['counter'];
		$data_arr["data"][0]	= $result[1];
		
		// pass the data in summary_showdatagrid() function & create a final variable for view
		$this->view->pagegridshow =  $pagingshow->summary_showdatagrid($data_arr);
		
		$this->view->addScriptPath(APPLICATION_PATH."/theme/default/datagrid/");	
    }

    /**
    * @name       addbusinesstype
    * @since      6-2-2012
    * @version    Release: 1
    * @author     PM <pankit@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This action is use for deatil view of business type with add option
    */
    public function additempackageAction(){
        //view variable declaration
        $this->view->params = $params = $this->getRequest()->getParams();
        $this->view->formdata = $formdata = $this->_request->getPost();

		$this->view->returnUrl = $_SERVER["HTTP_REFERER"];
	
		if($formdata['txtdesc'] !='')
		{
			$param_array 	= array();
			$param_array[1]	= $formdata['txtdesc'];
			$param_array[2]	= $formdata['txtarbdesc'];
			$param_array[3]	= $formdata['ddlstatus'];
			$param_array[4]	= $formdata['txtaltcode'];
			$param_array[5]	= $this->currentUser->username;
			
			if($formdata['hdnid'] > 0)
			{
				$param_array[6]	= $formdata['hdnid'];
				$last_id = $this->SFA_Comman->executequery('CALL sp_edit_inventory_target_additempackage(?,?,?,?,?,?)',$param_array,'');
				SFA_Message::setMsg($this->translate->_('Update Record'));
			}
			else
			{
				$last_id = $this->SFA_Comman->executequery('CALL sp_add_inventory_target_additempackage(?,?,?,?,?)',$param_array,'');
				SFA_Message::setMsg($this->translate->_('New Record'));
			}	
			if($formdata["returnUrl"]!="")
				$this->_redirect($formdata["returnUrl"]);
			else
				$this->_helper->redirector('itempackage', 'target', 'inventory');
		}
		elseif($params['id'] > 0)
		{
			$result = $this->SFA_Comman->executequery('CALL sp_get_inventory_target_additempackage(?)',$params['id'],'');
			
			$res['txtcode']			= $result[0][0]['packagecode'];
			$res['txtaltcode']		= $result[0][0]['alternatecode'];
			$res['txtdesc']			= $result[0][0]['packagedescription'];
			$res['txtarbdesc']		= $result[0][0]['arbpackagedescription'];
			$res['ddlstatus']		= $result[0][0]['activestatus'];
			$res['createddate']		= date('d-m-Y',strtotime($result[0][0]['cdat']));
			$this->view->formdata 	= $res;	    
		}
		else
		{
			$result = $this->SFA_Comman->executequery('CALL sp_get_inventory_target_additempackage(?)','0','');
			 $auto_incr	= ($result[0][0]['Auto_increment'] == '') ? '1' : $result[0][0]['Auto_increment'];
			/* if($auto_incr=="1")
			 {
				 $auto_incr = 2;
			 }*/
			 $this->view->formdata['txtcode'] = $auto_incr;
		}
    }

     /**
    * @name       quota
    * @since      6-2-2012
    * @version    Release: 1
    * @author     PM <pankit@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This action is use for deatil view of businesstype lists
    */
    public function quotaAction()
    {
        $this->view->formdata = $formdata = $this->_request->getPost();
	$this->view->params = $params = $this->getRequest()->getParams();
	 
	if($formdata["hdDelete"]==1)
	{
	    $ids = implode(',',$formdata['chk']);
	    $param_array 	= array();
	    $param_array[1]	= $ids;
	    $param_array[2]	= $this->currentUser->username;
	    
	    $result = $this->SFA_Comman->executequery('CALL sp_delete_inventory_target_quota(?,?)',$param_array,'');
	    
	    
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

	
	$cols_array 	= array('quotacode','quotadescription','activestatus');
	$columns_show 	=  array($this->translate->_('Quota ID'),$this->translate->_('Description'),$this->translate->_('Status'));
	
	if($this->css == 'ar_') {
		$cols_array[1]	= 'arbquotadescription AS quotadescription';
	}
	

	// prepare the configuration for grid
	$pagingparams = array(
			"show_grid_heading" => true,
			"grid_heading_message" => $this->translate->_('Overview'),
			"pagename" => $this->translate->_('Quota'),
			"show_records_per_page" => isset($formdata["show_records_per_page"])?$formdata["show_records_per_page"]:10,
			"show_searchbox" => true,
			"searchbox_value" => isset($formdata["grid_search_text"])?$formdata["grid_search_text"]:"",
			"show_selectbox" => true,
			"selected_list" => $checked,
			"show_editlink" => true,
			"show_deletelink" => false,
			"show_deleteall" => false,
			"primaryid" => "quotacode",
			"status_cols" => array(
						array(
						"cols_name" => "activestatus",
						"status_change" => array("0"=>"Inactive","1"=>"Active")
						)
					    ),
			"editlink" => array("/inventory/target/addquota/id/#pattern#/edit/yes/","#pattern#"),
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
	$result = $this->SFA_Comman->executequery('CALL sp_get_inventory_target_quota(?,?,?,?,?,?,?,?)',$param_array,'',$downloadCSV,$printData,2,$pagingparams);

	$data_arr["count"]	= $result[0][0]['counter'];
	$data_arr["data"][0]	= $result[1];
	
	// pass the data in summary_showdatagrid() function & create a final variable for view
	$this->view->pagegridshow =  $pagingshow->summary_showdatagrid($data_arr);
	
	$this->view->addScriptPath(APPLICATION_PATH."/theme/default/datagrid/");
    }

    /**
    * @name       addbusinesstype
    * @since      6-2-2012
    * @version    Release: 1
    * @author     PM <pankit@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This action is use for deatil view of business type with add option
    */
    public function addquotaAction() {
       //view variable declaration
	$this->view->params = $params = $this->getRequest()->getParams();
	$this->view->formdata = $formdata = $this->_request->getPost();
	

        $this->view->returnUrl = $_SERVER["HTTP_REFERER"];

	if($formdata['txtquotaid'] !='')
	{
	    $param_array 	= array();
	    $param_array[1]	= $formdata['txtquotadesc'];
	    $param_array[2]	= $formdata['txtarbquotadesc'];
	    $param_array[3]	= $formdata['ddlstatus'];
	    $param_array[4]	= $this->currentUser->username;
	    
	    if($formdata['hdnid'] > 0)
	    {
		$param_array[5]	= $formdata['hdnid'];
		$last_id = $this->SFA_Comman->executequery('CALL sp_edit_inventory_target_addquota(?,?,?,?,?)',$param_array,'');
		SFA_Message::setMsg($this->translate->_('Update Record'));
	    }
	    else
	    {
		$last_id = $this->SFA_Comman->executequery('CALL sp_add_inventory_target_addquota(?,?,?,?)',$param_array,'');
		SFA_Message::setMsg($this->translate->_('New Record'));
	    }	
	    if($formdata["returnUrl"]!="")
		$this->_redirect($formdata["returnUrl"]);
	    else
		$this->_helper->redirector('quota', 'target', 'inventory');
	}
	elseif($params['id'] > 0)
	{
	    $result = $this->SFA_Comman->executequery('CALL sp_get_inventory_target_addquota(?)',$params['id'],'');
	    
	    $res['txtquotaid']		= $result[0][0]['quotacode'];
	    $res['txtquotadesc']	= $result[0][0]['quotadescription'];
	    $res['txtarbquotadesc']	= $result[0][0]['arbquotadescription'];
	    $res['ddlstatus']		= $result[0][0]['activestatus'];
	    $res['createddate']		= date('d-m-Y',strtotime($result[0][0]['cdat']));
	    $this->view->formdata 	= $res;	    
	}
	else
	{
	    $result = $this->SFA_Comman->executequery('CALL sp_get_inventory_target_addquota(?)','0','');
	    $this->view->formdata['txtquotaid']	= ($result[0][0]['Auto_increment'] == '') ? '1' : $result[0][0]['Auto_increment'];
	}
    }

    /**
    * @name       companymonthlytargetAction
    * @since      7-2-2012
    * @version    Release: 1
    * @author     PM <pankit@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This action is use for give company monthly target information
    */
    public function companymonthlytargetAction(){
        //view variable declaration
        $this->view->formdata = $formdata = $this->_request->getPost();

        if($formdata["hdDelete"]==1)
            SFA_Message::setMsg($this->translate->_('Delete Record'));

        //variable declaration for grid title
        $companyname		= $this->translate->_('Company Name');
	$iscases		= $this->translate->_('Is Case');
	$targetdate		= $this->translate->_('Target Date');

	// ARRAY FOR GRID PAGINATION
	$pagingparams = array(
			     "show_grid_heading" => true,
			     "grid_heading_message" => $this->translate->_('Overview'),
			     "show_records_per_page" => isset($formdata["show_records_per_page"])?$formdata["show_records_per_page"]:10,
			     "show_searchbox" => true, "searchbox_value" => isset($formdata["grid_search_text"])?$formdata["grid_search_text"]:"", "searchbox_value" => isset($formdata["grid_search_text"])?$formdata["grid_search_text"]:"",
			     "show_selectbox" => true,
			     "show_editlink" => true,
			     "show_deletelink" => false,
			     "show_deleteall" => false,
			     "primaryid" => "usr.CmpyTransCode",
			     "editlink" => array("/inventory/target/addcompanymonthlytarget/id/#pattern#/edit/yes/","#pattern#"),
                              "status_cols" => array(
						    array(
							"cols_name" => "IsCases",
							"status_change" => array("0"=>"No","1"=>"Yes")
							)
						    ),
			     "nodata_message" => $this->translate->_('No Record(s) Found')
			     );
    if(!$this->checkaccess("update"))
    {
        $pagingparams["show_editlink"] = false;
    }
    
	$pagingshow = new SFA_Pagingquery($pagingparams);
	$pagingshow->from(array('usr' => 'companymonthlytargetheader'),
		    array('type.Name','usr.IsCases','DATE_FORMAT(usr.MonthTargetDate,"%d/%m/%Y")'));
	$pagingshow->joinLeft(array('type'=>'company'),'usr.CmpyCode = type.CmpyCode',array(''));


	$columns_show  = array($companyname,$iscases,$targetdate);

	$this->view->pagegridshow =  $pagingshow->summary_showdatagrid($columns_show,'',$result);
	$this->view->addScriptPath(APPLICATION_PATH."/theme/default/datagridquery/");
    }
     /**
    * @name       addcompanymonthlytargetAction
    * @since      7-2-2012
    * @version    Release: 1
    * @author     PM <pankit@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This action is use for give company monthly target information
    */
    public function addcompanymonthlytargetAction(){
        //view variable declaration
        $this->view->params = $params = $this->getRequest()->getParams();
        $this->view->formdata = $formdata = $this->_request->getPost();

        // IF EXTRA PARAMS ARE REQUIRED
	$ex_param = "";
	if(isset($params["id"]) && $params["id"]>0)
	    $ex_param = "/key/".$params["id"];

	$this->view->itemgrid    = $this->view->BaseUrl("/".$params['module']."/".$params['controller']."/companymonthlytargetgrid".$ex_param);

         //item array
        $item = array();
        $item =  $this->common_model->getcomboboxdata('itemmaster','ActualItemCode as id' ,'ItemShortDescription as val');
	$this->view->item = $item;

        //company array
        $company = array();
        $company = $this->common_model->getcomboboxdata('company','CmpyCode as id' ,'CONCAT(CmpyCode," --- ",NAME) as val');
	$this->view->company = $company;

       if(count($formdata) > 0) {

             if($formdata['hdnid'] > 0)
		SFA_Message::setMsg($this->translate->_('Update Record'));
	    else
		SFA_Message::setMsg($this->translate->_('New Record'));
	    $this->_helper->redirector('companymonthlytarget', 'target', 'inventory');
	}
    }

     /**
    * @name       companymonthlytargetgridAction
    * @since      7-2-2012
    * @version    Release: 1
    * @author     PM <pankit@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This action is use for give company monthly target information
    */
    public function companymonthlytargetgridAction(){
            //view variable declaration
            $this->view->params = $params = $this->getRequest()->getParams();

            //variable declaration for grid title
            $companyname	= $this->translate->_('Item Code');
            $iscases            = $this->translate->_('Description');
            $jan		= $this->translate->_('January');
            $feb		= $this->translate->_('February');
            $mar		= $this->translate->_('March');
            $apr		= $this->translate->_('April');
            $may		= $this->translate->_('May');
            $june		= $this->translate->_('June');
            $july		= $this->translate->_('July');
            $aug		= $this->translate->_('August');
            $sep		= $this->translate->_('September');
            $oct		= $this->translate->_('October');
            $nov		= $this->translate->_('November');
            $dec		= $this->translate->_('November');

            // DELETE THE RECORD
            if($params["delete"]=="yes"){
                // itemmaste (table name), ActualItemCode (prymary key)
                $this->common_model->delete_row('itemmaster','ActualItemCode',$params["id"]);
                SFA_Message::setMsg($this->translate->_('Delete Record'));
            }

            // UPDATE THE RECORD
            if($params["update"]=="yes"){
                $updateData["ItemShortDescription"] = $_GET["ItemShortDescription"];

                // itemmaste (table name), ActualItemCode (prymary key)
                $this->common_model->edit_row('itemmaster','ActualItemCode',$params["id"],$updateData);
                SFA_Message::setMsg($this->translate->_('Update Record'));
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
                                         "primaryid" => "item.ActualItemCode",
                                           "currentlink" => array("/".$params['module']."/".$params['controller']."/".$params['action'].$ex_param),
                                         "deletelink" => array("/id/#pattern#/delete/yes","#pattern#"),
                                         "editlink" => array("/id/#pattern#/edit/yes","#pattern#"),
                                         "nodata_message" => $this->translate->_('No Record(s) Found')
                      );

            // WHEN GRID IS IN EDIT MODE
            if($params["edit"]=="yes"){

                $pagingparams["editmode"] = true;
                $pagingparams["editmodeid"] = $params["id"];
                $pagingparams["editmodevalue"] = "ActualItemCode";  // put table's prymary key here
            }

            $pagingshow = new SFA_Ajaxgrid($pagingparams);
            $pagingshow->from(array('item' => 'itemmaster'),
            array('item.ActualItemCode','item.ItemShortDescription',
                'usr.JanQty',
                'usr.FebQty',
                'usr.MarQty',
                'usr.AprQty',
                'usr.MayQty',
                'usr.JuneQty',
                'usr.JulyQty',
                'usr.AugQty',
                'usr.SepQty',
                'usr.OctQty',
                'usr.NovQty',
                'usr.DescQty'
                ));
        $pagingshow->joinLeft(array('usr'=>'companymonthlytargetdetail'),'usr.ItemCode = item.ActualItemCode',array(''));
	$columns_show  = array($companyname,$iscases,$jan,$feb,$mar,$apr,$may,$june,$july,$aug,$sep,$oct,$nov,$dec);

            $this->view->pagegridshow =  $pagingshow->summary_showdatagrid($columns_show,'',$result);
            $this->view->addScriptPath(APPLICATION_PATH."/theme/default/datagridquery/");

            $this->render("ajaxgrid");
    }


    /**
    * @name       regionmonthlytargetAction
    * @since      7-2-2012
    * @version    Release: 1
    * @author     PM <pankit@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This action is use for give company monthly target information
    */
    public function regionmonthlytargetAction(){
        //view variable declaration
        $this->view->formdata = $formdata = $this->_request->getPost();

        if($formdata["hdDelete"]==1)
            SFA_Message::setMsg($this->translate->_('Delete Record'));

        //variable declaration for grid title
        $regionname		= $this->translate->_('Region Name');
	$iscases		= $this->translate->_('Is Case');
	$targetdate		= $this->translate->_('Target Date');

	// ARRAY FOR GRID PAGINATION
	$pagingparams = array(
			     "show_grid_heading" => true,
			     "grid_heading_message" => $this->translate->_('Overview'),
			     "show_records_per_page" => isset($formdata["show_records_per_page"])?$formdata["show_records_per_page"]:10,
			     "show_searchbox" => true, "searchbox_value" => isset($formdata["grid_search_text"])?$formdata["grid_search_text"]:"", "searchbox_value" => isset($formdata["grid_search_text"])?$formdata["grid_search_text"]:"",
			     "show_selectbox" => true,
			     "show_editlink" => true,
			     "show_deletelink" => false,
			     "show_deleteall" => false,
			     "primaryid" => "usr.RegionTransCode",
			     "editlink" => array("/inventory/target/addregionmonthlytarget/id/#pattern#/edit/yes/","#pattern#"),
			     "nodata_message" => $this->translate->_('No Record(s) Found')
			     );

	$pagingshow = new SFA_Pagingquery($pagingparams);
	$pagingshow->from(array('usr' => 'regionmonthlytargetheader'),
		    array('type.RegionMstName','usr.IsCases','DATE_FORMAT(usr.MonthTargetDate,"%d/%m/%Y")'));
	$pagingshow->joinLeft(array('type'=>'regionmaster'),'usr.RegionMstCode = type.RegionMstCode',array(''));

	$columns_show  = array($regionname,$iscases,$targetdate);

	$this->view->pagegridshow =  $pagingshow->summary_showdatagrid($columns_show,'',$result);
	$this->view->addScriptPath(APPLICATION_PATH."/theme/default/datagridquery/");
    }
     /**
    * @name       addregionmonthlytargetAction
    * @since      7-2-2012
    * @version    Release: 1
    * @author     PM <pankit@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This action is use for give company monthly target information
    */
    public function addregionmonthlytargetAction(){
        //view variable declaration
        $this->view->params = $params = $this->getRequest()->getParams();
        $this->view->formdata = $formdata = $this->_request->getPost();

        // IF EXTRA PARAMS ARE REQUIRED
	$ex_param = "";
	if(isset($params["id"]) && $params["id"]>0)
	    $ex_param = "/key/".$params["id"];

	$this->view->itemgrid    = $this->view->BaseUrl("/".$params['module']."/".$params['controller']."/regionmonthlytargetgrid".$ex_param);

         //item array
        $item = array();
        $item =  $this->common_model->getcomboboxdata('itemmaster','ActualItemCode as id' ,'ItemShortDescription as val');
	$this->view->item = $item;

       //company array
        $company= array();
        $company = $this->common_model->getcomboboxdata('company','CmpyCode as id' ,'CONCAT(CmpyCode," --- ",NAME) as val');
	$this->view->company = $company;

        //Region array
        $region= array();
        $region = $this->common_model->getcomboboxdata('regionmaster','RegionMstCode as id' ,'CONCAT(RegionMstCode," --- ",RegionMstName) as val');
	$this->view->region = $region;

         if(count($formdata) > 0) {
             if($formdata['hdnid'] > 0)
		SFA_Message::setMsg($this->translate->_('Update Record'));
	    else
		SFA_Message::setMsg($this->translate->_('New Record'));
	    $this->_helper->redirector('regionmonthlytarget', 'target', 'inventory');
	}
    }


     /**
    * @name       regionmonthlytargetgridAction
    * @since      7-2-2012
    * @version    Release: 1
    * @author     PM <pankit@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This action is use for give company monthly target information
    */
    public function regionmonthlytargetgridAction(){
            //view variable declaration
            $this->view->params = $params = $this->getRequest()->getParams();

            //variable declaration for grid title
            $companyname	= $this->translate->_('Item Code');
            $iscases            = $this->translate->_('Description');
            $jan		= $this->translate->_('Jan');
            $feb		= $this->translate->_('Feb');
            $mar		= $this->translate->_('Mar');
            $apr		= $this->translate->_('Apr');
            $may		= $this->translate->_('May');
            $june		= $this->translate->_('June');
            $july		= $this->translate->_('July');
            $aug		= $this->translate->_('Aug');
            $sep		= $this->translate->_('Sep');
            $oct		= $this->translate->_('Oct');
            $nov		= $this->translate->_('Nov');
            $dec		= $this->translate->_('Dec');

            // DELETE THE RECORD
            if($params["delete"]=="yes"){
                // itemmaste (table name), ActualItemCode (prymary key)
                $this->common_model->delete_row('itemmaster','ActualItemCode',$params["id"]);
                SFA_Message::setMsg($this->translate->_('Delete Record'));
            }

            // UPDATE THE RECORD
            if($params["update"]=="yes"){
                $updateData["ItemShortDescription"] = $_GET["ItemShortDescription"];

                // itemmaste (table name), ActualItemCode (prymary key)
                $this->common_model->edit_row('itemmaster','ActualItemCode',$params["id"],$updateData);
                SFA_Message::setMsg($this->translate->_('Update Record'));
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
                                         "primaryid" => "item.ActualItemCode",
                                           "currentlink" => array("/".$params['module']."/".$params['controller']."/".$params['action'].$ex_param),
                                         "deletelink" => array("/id/#pattern#/delete/yes","#pattern#"),
                                         "editlink" => array("/id/#pattern#/edit/yes","#pattern#"),
                                         "nodata_message" => $this->translate->_('No Record(s) Found')
                      );

            // WHEN GRID IS IN EDIT MODE
            if($params["edit"]=="yes"){

                $pagingparams["editmode"] = true;
                $pagingparams["editmodeid"] = $params["id"];
                $pagingparams["editmodevalue"] = "ActualItemCode";  // put table's prymary key here
            }

            $pagingshow = new SFA_Ajaxgrid($pagingparams);
            $pagingshow->from(array('item' => 'itemmaster'),
            array('item.ActualItemCode','item.ItemShortDescription',
                'usr.JanQty',
                'usr.FebQty',
                'usr.MarQty',
                'usr.AprQty',
                'usr.MayQty',
                'usr.JuneQty',
                'usr.JulyQty',
                'usr.AugQty',
                'usr.SepQty',
                'usr.OctQty',
                'usr.NovQty',
                'usr.DescQty'
                ));
        $pagingshow->joinLeft(array('usr'=>'regionmonthlytargetdetail'),'usr.ItemCode = item.ActualItemCode',array(''));
	$columns_show  = array($companyname,$iscases,$jan,$feb,$mar,$apr,$may,$june,$july,$aug,$sep,$oct,$nov,$dec);

            $this->view->pagegridshow =  $pagingshow->summary_showdatagrid($columns_show,'',$result);
            $this->view->addScriptPath(APPLICATION_PATH."/theme/default/datagridquery/");

            $this->render("ajaxgrid");
    }

     /**
    * @name       depotmonthlytargetAction
    * @since      7-2-2012
    * @version    Release: 1
    * @author     PM <pankit@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This action is use for give company monthly target information
    */
    public function depotmonthlytargetAction(){
        //view variable declaration
        $this->view->formdata = $formdata = $this->_request->getPost();

        if($formdata["hdDelete"]==1)
            SFA_Message::setMsg($this->translate->_('Delete Record'));

        //variable declaration for grid title
        $companyname		= $this->translate->_('Depot Name');
	$iscases		= $this->translate->_('Is Casey');
	$targetdate		= $this->translate->_('Target Date');

	// ARRAY FOR GRID PAGINATION
	$pagingparams = array(
			     "show_grid_heading" => true,
			     "grid_heading_message" => $this->translate->_('Overview'),
			     "show_records_per_page" => isset($formdata["show_records_per_page"])?$formdata["show_records_per_page"]:10,
			     "show_searchbox" => true, "searchbox_value" => isset($formdata["grid_search_text"])?$formdata["grid_search_text"]:"", "searchbox_value" => isset($formdata["grid_search_text"])?$formdata["grid_search_text"]:"",
			     "show_selectbox" => true,
			     "show_editlink" => true,
			     "show_deletelink" => false,
			     "show_deleteall" => false,
			     "primaryid" => "usr.id",
			     "editlink" => array("/inventory/target/adddepotmonthlytarget/id/#pattern#/edit/yes/","#pattern#"),
                              "status_cols" => array(
						    array(
							"cols_name" => "status",
							"status_change" => array("0"=>"No","1"=>"Yes")
							)
						    ),
			     "nodata_message" => $this->translate->_('No Record(s) Found')
			     );


	$pagingshow = new SFA_Pagingquery($pagingparams);
	$pagingshow->from(array('usr' => 'user'),
		    array('usr.first_name','usr.status','DATE_FORMAT(usr.created,"%d/%m/%Y")'));
	$pagingshow->joinLeft(array('type'=>'user_type'),'usr.user_type_id = type.id',array(''));
	$pagingshow->where('type.is_admin="2"');
	$columns_show  = array($companyname,$iscases,$targetdate);

	$this->view->pagegridshow =  $pagingshow->summary_showdatagrid($columns_show,'',$result);
	$this->view->addScriptPath(APPLICATION_PATH."/theme/default/datagridquery/");
    }
     /**
    * @name       adddepotmonthlytargetAction
    * @since      7-2-2012
    * @version    Release: 1
    * @author     PM <pankit@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This action is use for give company monthly target information
    */
    public function adddepotmonthlytargetAction(){
        //view variable declaration
        $this->view->params = $params = $this->getRequest()->getParams();
        $this->view->formdata = $formdata = $this->_request->getPost();

        // IF EXTRA PARAMS ARE REQUIRED
	$ex_param = "";
	if(isset($params["id"]) && $params["id"]>0)
	    $ex_param = "/key/".$params["id"];

	$this->view->itemgrid    = $this->view->BaseUrl("/".$params['module']."/".$params['controller']."/depotmonthlytargetgrid".$ex_param);


         //item array
        $item = array();
        $item =  $this->common_model->getcomboboxdata('itemmaster','ActualItemCode as id' ,'ItemShortDescription as val');
	$this->view->item = $item;

       //company array
        $company= array();
        $company = $this->common_model->getcomboboxdata('company','CmpyCode as id' ,'CONCAT(CmpyCode," --- ",NAME) as val');
	$this->view->company = $company;

        //Region array
        $region= array();
        $region = $this->common_model->getcomboboxdata('regionmaster','RegionMstCode as id' ,'CONCAT(RegionMstCode," --- ",RegionMstName) as val');
	$this->view->region = $region;

        //Depot/Branch array
        $depotbranch = array();
	$depotbranch =  $this->common_model->getcomboboxdata('depotmaster','DepotCode as id' ,'CONCAT(DepotCode," --- ",DepotName) as val');
	$this->view->depotbranch = $depotbranch;

           if(count($formdata) > 0) {

             if($formdata['hdnid'] > 0)
		SFA_Message::setMsg($this->translate->_('Update Record'));
	    else
		SFA_Message::setMsg($this->translate->_('New Record'));
	    $this->_helper->redirector('depotmonthlytarget', 'target', 'inventory');
	}
    }


     /**
    * @name       depotmonthlytargetgridAction
    * @since      7-2-2012
    * @version    Release: 1
    * @author     PM <pankit@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This action is use for give company monthly target information
    */
    public function depotmonthlytargetgridAction(){
            //view varibale declaration
            $this->view->params = $params = $this->getRequest()->getParams();

            //variable declaration for grid title
            $companyname	= $this->translate->_('Item Code');
            $iscases            = $this->translate->_('Description');
            $jan		= $this->translate->_('Jan');
            $feb		= $this->translate->_('Feb');
            $mar		= $this->translate->_('Mar');
            $apr		= $this->translate->_('Apr');
            $may		= $this->translate->_('May');
            $june		= $this->translate->_('June');
            $july		= $this->translate->_('July');
            $aug		= $this->translate->_('Aug');
            $sep		= $this->translate->_('Sep');
            $oct		= $this->translate->_('Oct');
            $nov		= $this->translate->_('Nov');
            $dec		= $this->translate->_('Dec');

            // DELETE THE RECORD
            if($params["delete"]=="yes"){
                // itemmaste (table name), ActualItemCode (prymary key)
                $this->common_model->delete_row('itemmaster','ActualItemCode',$params["id"]);
                SFA_Message::setMsg($this->translate->_('Delete Record'));
            }

            // UPDATE THE RECORD
            if($params["update"]=="yes"){
                $updateData["ItemShortDescription"] = $_GET["ItemShortDescription"];

                // itemmaste (table name), ActualItemCode (prymary key)
                $this->common_model->edit_row('itemmaster','ActualItemCode',$params["id"],$updateData);
                SFA_Message::setMsg($this->translate->_('Update Record'));
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
                                         "primaryid" => "item.ActualItemCode",
                                           "currentlink" => array("/".$params['module']."/".$params['controller']."/".$params['action'].$ex_param),
                                         "deletelink" => array("/id/#pattern#/delete/yes","#pattern#"),
                                         "editlink" => array("/id/#pattern#/edit/yes","#pattern#"),
                                         "nodata_message" => $this->translate->_('No Record(s) Found')
                      );

            // WHEN GRID IS IN EDIT MODE
            if($params["edit"]=="yes"){

                $pagingparams["editmode"] = true;
                $pagingparams["editmodeid"] = $params["id"];
                $pagingparams["editmodevalue"] = "ActualItemCode";  // put table's prymary key here
            }

            $pagingshow = new SFA_Ajaxgrid($pagingparams);
            $pagingshow->from(array('item' => 'itemmaster'),
            array('item.ActualItemCode','item.ItemShortDescription',
                'usr.JanQty',
                'usr.FebQty',
                'usr.MarQty',
                'usr.AprQty',
                'usr.MayQty',
                'usr.JuneQty',
                'usr.JulyQty',
                'usr.AugQty',
                'usr.SepQty',
                'usr.OctQty',
                'usr.NovQty',
                'usr.DescQty'
                ));

         //ToDo: Make changes in query for depot monthly target
        $pagingshow->joinLeft(array('usr'=>'regionmonthlytargetdetail'),'usr.ItemCode = item.ActualItemCode',array(''));
	$columns_show  = array($companyname,$iscases,$jan,$feb,$mar,$apr,$may,$june,$july,$aug,$sep,$oct,$nov,$dec);

            $this->view->pagegridshow =  $pagingshow->summary_showdatagrid($columns_show,'',$result);
            $this->view->addScriptPath(APPLICATION_PATH."/theme/default/datagridquery/");

            $this->render("ajaxgrid");
    }


     /**
    * @name       salesmantargetAction
    * @since      7-2-2012
    * @version    Release: 1
    * @author     PM <pankit@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This action is use for give company monthly target information
    */
    public function salesmantargetAction()
    {
        //view variable declaration
        $this->view->params = $params = $this->getRequest()->getParams();
		$this->view->formdata = $formdata = $this->_request->getPost();
	
		if($formdata["hdDelete"]==1)
		{
			$ids = implode(',',$formdata['chk']);
			$param_array 	= array();
			$param_array[1]	= $ids;
			$param_array[2]	= $this->currentUser->username;
			
			$result = $this->SFA_Comman->executequery('CALL sp_delete_inventory_target_addsalesmantarget(?,?)',$param_array,'');	    
			
			if($result[0][0]['result']="Success")
			{
			$deleted_id 	= explode(',',$result[0][0]['deleted_id']);
			$ids		= explode(',',$ids);
			$checked 	= array_diff($ids,$deleted_id);
			
			if(count($ids) != count($deleted_id)) {
				SFA_Message::setErrorMsg($this->translate->_('Record Already In use'));
			}		
			SFA_Message::setMsg($this->translate->_('Delete Record'));
			}
			/*if($result[0][0]['deleted_id'] =='')
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
			}*/
		}
	
		//view variable for grid title		
		$salesmancode 	= $this->translate->_('Salesman Code');
		$salesmanname 	= $this->translate->_('Salesman Name');
		$routename 		= $this->translate->_('Route Name');
		$year	 		= $this->translate->_('Year');
		$routecode	 		= $this->translate->_('Route Code');
		//$From	 		= $this->translate->_('From');
		//$To	 			= $this->translate->_('To');
		
		$cols_array 	= array('sales.salesmancode','salesmanname1','route.routecode','routename','primary_key as edit_del_primary_id' );
		$columns_show 	= array($salesmancode,$salesmanname,$routecode,$routename);
		
		if($this->css == 'ar_') {
			$cols_array[1]	= 'arbsalesmanname1 AS salesmanname1';
			$cols_array[2]	= 'arbroutename AS routename';
		}
		
		// CREATE A SESSION NAMESPACE
		$Common_NameSpace = new Zend_Session_Namespace('SalesmanTarget');
		
		if($formdata['btnreset'] == 'RESET')
		{
			$formdata["txtdate"] 	= '';
			$Common_NameSpace->tdate	= '';
		}
		
		$sel_date = ($formdata["txtdate"] != '') ? $formdata["txtdate"] : $Common_NameSpace->tdate;
	
		// ADD DATE VALUE IN SESSION
		if($sel_date != '') {
			$Common_NameSpace->tdate 	= $sel_date;
			$this->view->date		= $sel_date;
		}
		// ADDITIONAL WHERE CONDITION
		if($Common_NameSpace->tdate)
			$additional_where_condition[] = " (yyear BETWEEN \'".$sel_date."\' AND \'".$sel_date."\' )";
		
		// prepare the configuration for grid
		$pagingparams = array(
				 "show_grid_heading" => true,
				 "grid_heading_message" => $this->translate->_('Overview'),
				 "pagename" => $this->translate->_('Salesman Target'),
				 "show_records_per_page" => isset($formdata["show_records_per_page"])?$formdata["show_records_per_page"]:10,
				 "show_searchbox" => true,
				 "searchbox_value" => isset($formdata["grid_search_text"])?$formdata["grid_search_text"]:"",
				 "show_selectbox" => true,
				 "show_editlink" => true,
				 "show_deletelink" => false,
				 "show_deleteall" => false,
				 'show_extralink' => true,
				 "editlink" => array("/inventory/target/addsalesmantarget/id/#pattern#/edit/yes/","#pattern#"),
				 "no_search_fields" => $not_in_search,
				 "primaryid" => "primary_key",			 
				 "nodata_message" => $this->translate->_('No Record(s) Found'),
				 "fetch_columns_inquery" => $cols_array,
				 "show_columns" => $columns_show
				 /*this line commented by nilesh on 6Mar2016*/
				// "additional_where" => $additional_where_condition
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
		$result 	= $this->SFA_Comman->executequery('CALL sp_get_inventory_target_salesmantarget(?,?,?,?,?,?,?,?)',$param_array,'',$downloadCSV,$printData,2,$pagingparams);
	/*count function added by nilesh 6mar2016*/
		$data_arr["count"] 	= count($result[0]);
		$data_arr["data"][0] 	= $result[1];
		
		// pass the data in summary_showdatagrid() function & create a final variable for view
		$this->view->pagegridshow =  $pagingshow->summary_showdatagrid($data_arr);
		
		$this->view->addScriptPath(APPLICATION_PATH."/theme/default/datagrid/");	
    }
     /**
    * @name       addsalesmantargetAction
    * @since      7-2-2012
    * @version    Release: 1
    * @author     PM <pankit@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This action is use for give company monthly target information
    */
    public function addsalesmantargetAction() {
        //view variable declaration
        $this->view->params = $params = $this->getRequest()->getParams();
        $this->view->formdata = $formdata = $this->_request->getPost();
	
	
		// CREATE A SESSION NAMESPACE
		$Common_NameSpace = new Zend_Session_Namespace('SalesmanTarget');
		
		$sel_date = ($Common_NameSpace->tdate != '') ? $Common_NameSpace->tdate : date("Y");
	
		// ADD DATE VALUE IN SESSION
		if($sel_date != '') {
			$Common_NameSpace->tdate 	= $sel_date;
			$this->view->date		= $sel_date;
		}
		
		$month 			= array();
		$month[1]['id']		= '1';
		$month[1]['val']	= 'January';
		$month[2]['id']		= '2';
		$month[2]['val']	= 'February';
		$month[3]['id']		= '3';
		$month[3]['val']	= 'March';
		$month[4]['id']		= '4';
		$month[4]['val']	= 'April';
		$month[5]['id']		= '5';
		$month[5]['val']	= 'May';
		$month[6]['id']		= '6';
		$month[6]['val']	= 'June';
		$month[7]['id']		= '7';
		$month[7]['val']	= 'July';
		$month[8]['id']		= '8';
		$month[8]['val']	= 'August';
		$month[9]['id']		= '9';
		$month[9]['val']	= 'September';
		$month[10]['id']	= '10';
		$month[10]['val']	= 'October';
		$month[11]['id']	= '11';
		$month[11]['val']	= 'November';
		$month[12]['id']	= '12';
		$month[12]['val']	= 'December';
		$this->view->month 	= $month;
	
			// IF EXTRA PARAMS ARE REQUIRED
		//$ex_param = "";
		//if(isset($params["id"]) && $params["id"]>0)
		//    $ex_param = "/key/".$params["id"];
	
		if(count($formdata) > 0)
		{
			$param_array 	= array();
			/*$param_array[1]	= $formdata['hdnroutecode'];
			$param_array[2]	= $formdata['ddlsalesman'];
			$param_array[3]	= $formdata['ddlitempackage'];
			$param_array[4]	= $formdata['txtdaily'];
			$param_array[5]	= $formdata['txtweekly'];
			$param_array[6]	= $formdata['txtmonthly'];
			$param_array[7]	= $formdata['txtyearly'];
			$param_array[8]	= $formdata['ddlmonth'];
			$param_array[9] = $formdata['ddltype'];
			$param_array[10] = $formdata['txtcomval1'];
			$param_array[11] = $formdata['txtinsper'];
			$param_array[12] = $formdata['txtinsamt'];*/
			$param_array[1]		= $formdata['hdnroutecode'];
			if($formdata['ddlsalesman']!="")
			{$param_array[2]		= $formdata['ddlsalesman'];}
			else{
			$param_array[2]		= $formdata['hdnsalesmancode'];}
			$param_array[3]		= $formdata['ddlitempackage'];
			
			$param_array[4]		= $formdata['txtfromdate'];
			$param_array[5]		= $formdata['txttodate'];
			$param_array[6]		= $formdata['txtquantity'];
			$param_array[7]		= $formdata['chkiscase'];
			if($formdata['ddltype']!="")
			{
			$param_array[8]  =   $formdata['ddltype'];
			}
			else
			{
			$param_array[8]  =   $formdata['hdntargetvalue'];}
			$param_array[9]		= $formdata['txtcomval1'];			
			$param_array[10] 	= $formdata['txtinsper'];
			$param_array[11] 	= $formdata['txtinsamt'];
			
			
	
				if($formdata['hdnid'] > 0) { # for edit 
			SFA_Message::setMsg($this->translate->_('Update Record'));
				}
			else {
			# for add
			if($param_array[9]!="")
			{
			$result = $this->SFA_Comman->executequery('CALL sp_add_inventory_target_addsalesmantarget(?,?,?,?,?,?,?,?,?,?,?)',$param_array,'');	
			}			
			SFA_Message::setMsg($this->translate->_('New Record'));
			//var_dump($result);
			}
			$this->_helper->redirector('salesmantarget', 'target', 'inventory');
		}
		elseif($params['id'] > 0)
		{
			$result = $this->SFA_Comman->executequery('CALL sp_get_inventory_target_addsalesmantarget(?)',$params['id'],'');
			echo $result[0][5];
			
			//print_r($result[0]);
			$this->view->formdata	= $result[0][0];
			$this->view->itempakckage	= $result[1];
			$this->view->salesman 	= $result[2];
			$this->view->quotamain 	= $result[3];
			 $this->view->targettype = $result[0]['targettype'];
			
			$this->view->itemgrid    = $this->view->BaseUrl("/".$params['module']."/".$params['controller']."/salesmanmonthlytargetgrid".$ex_param);
		}
		else
		{
			$this->view->itemgrid    = $this->view->BaseUrl("/".$params['module']."/".$params['controller']."/salesmanmonthlytargetgrid".$ex_param);
			
			$result = $this->SFA_Comman->executequery('CALL sp_get_inventory_target_addsalesmantarget(?)','0','');
			$this->view->itempakckage	= $result[1];
			$this->view->salesman 	= $result[2];
			$this->view->quotamain 	= $result[3];
		}

    }
    
     /**
    * @name       addsalesmanmonthlytargetgridAction
    * @since      7-2-2012
    * @version    Release: 1
    * @author     PM <pankit@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This action is use for add salesman monthly target information
    */
    public function addsalesmanmonthlytargetgrid($params) {	
	
		
		//$param_array[1]		= $params['hdnroutecode'];
		//$param_array[2]		= $params['ddlsalesman'];
		//$param_array[3]		= $params['ddlitempackage'];
		//$param_array[4]		= $params['txtdaily'];
		//$param_array[5]		= $params['txtweekly'];
		//$param_array[6]		= $params['txtmonthly'];
		//$param_array[7]		= $params['txtyearly'];
		//$param_array[8]		= $params['ddlmonth'];
		//$param_array[9]		= $params['chkiscase'];
		//$param_array[10]	= $this->currentUser->username;
		
		$param_array 		= array();
		$param_array[1]		= $params['hdnroutecode'];
		if($params['ddlsalesman']!="")
			{$param_array[2]		= $params['ddlsalesman'];}
			else{
			$param_array[2]		= $params['hdnsalesmancode'];}
		$param_array[3]		= $params['ddlitempackage'];
		$param_array[4]		= $params['txtfromdate'];
		$param_array[5]		= $params['txttodate'];
		$param_array[6]		= $params['txtquantity'];
		$param_array[7]		= $params['chkiscase'];
		if($params['ddltype']!="")
			{
			$param_array[8]  =   $params['ddltype'];
			}
			else
			{
			$param_array[8]  =   $params['hdntargetvalue'];}
		$param_array[9] = $params['txtcomval1'];
		$param_array[10] = $params['txtcomval2'];
		
		$result = $this->SFA_Comman->executequery('CALL sp_add_inventory_target_addsalesmantarget(?,?,?,?,?,?,?,?,?,?)',$param_array,'');
		
			if($result[0][0]['last_id'] != 'Duplicate') {
				SFA_Message::setMsg($this->translate->_('New Record'));
			}
			else {
				SFA_Message::setErrorMsg($this->translate->_('Duplicate Record'));
			}
    }

     /**
    * @name       salesmanmonthlytargetgridAction
    * @since      7-2-2012
    * @version    Release: 1
    * @author     PM <pankit@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This action is use for give salesman monthly target information
    */
    public function salesmanmonthlytargetgridAction()
    {	
		//view variable declaration
		$this->view->params = $params = $this->getRequest()->getParams();
		
		//$noeditfield = array('packagedescription','mmonth','packagenumber');
		$noeditfield = array('packagedescription','packagenumber','targettype','achieveamount');
		
		//varible declaration for grid title	
	//$code	 	= $this->translate->_('Package Code');
		
		$from_date	= $this->translate->_('From');
		$to_date	= $this->translate->_('To');
		$target_on  = $this->translate->_('Target On');
		$name       = $this->translate->_('Group');		
		$quantity	= $this->translate->_('Value');
		$com1		= $this->translate->_('Commision');
		$insentive_per	= $this->translate->_('Inc.%');
		$insentive_amt	= $this->translate->_('Incentive');
		$achieved_amt	= $this->translate->_('Achieved');
			
		
		// column to be fetched
		$columns_array 	=  array(
		'DATE_FORMAT(fromdate,"%d-%m-%Y") as fromdate','DATE_FORMAT(todate,"%d-%m-%Y") as todate','targettype','packagedescription','ROUND(quantity,'.$this->decimalplaces.') as quantity','ROUND(commision,'.$this->decimalplaces.') as commision','FORMAT(insentivepercent,'.$this->decimalplaces.') as insentivepercent','FORMAT(insentive,'.$this->decimalplaces.') as insentive','FORMAT(achieveamount,'.$this->decimalplaces.') as achieveamount','primary_key as edit_del_primary_id');
		$columns_show  	= array($from_date,$to_date,$target_on,$name,$quantity,$com1,$insentive_per,$insentive_amt,$achieved_amt);
		
		if($this->css == 'ar_') {
			$columns_array[1]	= 'arbpackagedescription AS packagedescription';
		}
		
		if($params["add"]=="yes") {
			
			$param_array 		= array();
			$param_array[1]		= $params['hdnroutecode'];
			if($params['ddlsalesman']!="")
			{$param_array[2]		= $params['ddlsalesman'];}
			else{
			$param_array[2]		= $params['hdnsalesmancode'];}				
			if($params['ddlitempackage']!="")
			{
			$param_array[3]  =   $params['ddlitempackage'];
			}
			else
			{
			$param_array[3]  =   $params['hdnpackvalue'];}
			
			$param_array[4]		= $params['txtfromdate'];
			$param_array[5]		= $params['txttodate'];
			$param_array[6]		= $params['txtquantity'];
			$param_array[7]		= $params['chkiscase'];
			if($params['ddltype']!="")
			{
			$param_array[8]  =   $params['ddltype'];
			}
			else
			{
			$param_array[8]  =   $params['hdntargetvalue'];}
			$param_array[9] =   $params['txtcomval1'];			
			$param_array[10] = $params['txtinsper'];
			$param_array[11] = $params['txtinsamt'];
			//var_dump($param_array);
			
			$result = $this->SFA_Comman->executequery('CALL sp_add_inventory_target_addsalesmantarget(?,?,?,?,?,?,?,?,?,?,?)',$param_array,'');
			
			if($result[0][0]['last_id'] != 'Duplicate') {
				echo "<script>
				$('#ddltype').val('');
				$('#ddltype').trigger('liszt:updated');
				</script>";
				
				SFA_Message::setMsg($this->translate->_('New Record'));
			}
			else {
				echo "<script>
				$('#ddltype').val('');
				$('#ddltype').trigger('liszt:updated');
				</script>";
				
				SFA_Message::setErrorMsg($this->translate->_('Duplicate Record'));
			}
		}
		
		// DELETE THE RECORD
		if($params["delete"]=="yes"){
			// sp for delete salesman target
			$r_delete = $this->SFA_Comman->executequery('CALL sp_delete_inventory_target_addsalesmantarget(?)',array(1=>$params["id"],2=>$this->currentUser->username),'');
			$rem_rec = $r_delete[1][0]['records_count'];
			
			if($rem_rec=="0")
			{
					echo "<script>
				$('#ddltype').val('');
				$('#ddltype').trigger('liszt:updated');
				</script>";
			
			}
			
			SFA_Message::setMsg($this->translate->_('Delete Record'));
		}
	
		// UPDATE THE RECORD
		if($params["update"]=="yes") {			
			$updateData["1"] = $params['fromdate'];
			$updateData["2"] = $params["todate"];
			$updateData["3"] = $params["quantity"];			
			$updateData["4"] = $params["id"];
			$updateData["5"] = $this->currentUser->username;	
			$updateData["6"] = $params["commision"];
			$updateData["7"] = $params["insentivepercent"];
			$updateData["8"] = $params["insentive"];
			
			// call sp for edit currencydetail
			$r_edit = $this->SFA_Comman->executequery('CALL sp_edit_inventory_target_addsalesmantarget(?,?,?,?,?,?,?,?)',$updateData,'');			
			if($r_edit[0][0]['last_id'] != 'Duplicate')
			{
				echo "<script>
				$('#ddltype').val('');
				$('#ddltype').trigger('liszt:updated');
				</script>";
			SFA_Message::setMsg($this->translate->_('Update Record'));}
			else{
				echo "<script>
				$('#ddltype').val('');
				$('#ddltype').trigger('liszt:updated');
				</script>";
			SFA_Message::setErrorMsg($this->translate->_('Duplicate Record'));}
		}
		
		// IF EXTRA PARAMS ARE REQUIRED
		$ex_param = "";				
		$additional_where_condition = array();
		if(isset($params["key"]) && $params["key"]>0){
			$ex_param = "/key/".$params["key"];
			$additional_where_condition[] = ' (salesmancode = "'.$params["key"].'")';
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
					 "mastervalues" => $mastervalues,
					 "noeditfields" => $noeditfield,
					 "primaryid" => "primary_key",
					 "currentlink" => array("/".$params['module']."/".$params['controller']."/".$params['action'].$ex_param),
					 "deletelink" => array("/id/#pattern#/delete/yes","#pattern#"),
					 "editlink" => array("/id/#pattern#/edit/yes","#pattern#"),
					 "nodata_message" => $this->translate->_('No Record(s) Found'),
					 "fetch_columns_inquery" => $columns_array,
					 "show_columns" => $columns_show,
					 "additional_where" => $additional_where_condition,
					 "show_columns_right_side" =>$amt_right,
					 );
	
		// WHEN GRID IS IN EDIT MODE
		if($params["edit"]=="yes"){
	
			$pagingparams["editmode"] = true;
			$pagingparams["editmodeid"] = $params["id"];
			$pagingparams["editmodevalue"] = "code";  // put table's prymary key here
		}
	
		
		$pagingshow = new SFA_Ajaxpaging($pagingparams);
		
		// call common function of grid class
		$get_return_vals = $pagingshow->commnfunc();
		$param_array = array();
		// call the stored procedure for fetch the data
		$param_array 	= array();
		$param_array[1] = '1';
		$param_array[2] = $get_return_vals['order_columns_name'];
		$param_array[3] = $get_return_vals['order_type'];
		$param_array[4] = $get_return_vals['offset'];
		$param_array[5] = (int)$get_return_vals['show_records_per_page'];
		$param_array[6] = implode(", ",$pagingparams["fetch_columns_inquery"]);
		$param_array[7] = strlen($get_return_vals['where_condition'])>0?$get_return_vals['where_condition']:'';
	
		//var_dump($param_array);
		// called stored procedure for counter
		$result = $this->SFA_Comman->executequery('CALL sp_get_inventory_target_addsalesmantargetgrid(?,?,?,?,?,?,?)',$param_array,'');    
		
		$data_arr["count"] 	= $result[0][0]['counter'];	
		$data_arr["data"][0] 	= $result[1];
		
		//$this->record_counts = $data_arr["count"];
		$this->view->pagegridshow =  $pagingshow->summary_showdatagrid($data_arr);
		$this->view->addScriptPath(APPLICATION_PATH."/theme/default/datagrid/");
	
		$this->render("ajaxgrid");
    }
    /**
    * @name       getroutenamefromsalesmanAction
    * @since      7-2-2012
    * @version    Release: 1
    * @author     PM <pankit@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This action is use for get routename from salesmancode
    */
    public function getroutenamefromsalesmanAction()
	{
		$params 	= $this->getRequest()->getParams();
		$salesmanid 	= $params['sid'];
		
		$result		= $this->SFA_Comman->executequery('CALL sp_routename_salesmancode(?)',$salesmanid,'');
		
		echo Zend_Json::encode($result);
		exit;
    }
    /**
    * @name       getstatusofupcfromitempackageAction
    * @since      7-2-2012
    * @version    Release: 1
    * @author     PM <pankit@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This action is check is upc is same for all the itempackage code
    */
    public function getstatusofupcfromitempackageAction()
	{
		$params 	= $this->getRequest()->getParams();
		$itempackageid 	= $params['ipid'];
		
		$result		= $this->SFA_Comman->executequery('CALL sp_upcstatus_itempackage(?)',$itempackageid,'');
		
		echo $result[0][0]['cnt'];
		exit;
    }
}