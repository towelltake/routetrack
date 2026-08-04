<?php
/**
* @name       SalesmanController
* @since
* @version    Release: 1
* @author     HD <hiren.d@elantechnologies.com>
* @copyright  Elan Technologies
* @param
*
* This controller is manage salesman module.
*/
class Account_SalesmanController extends Account_Library_Controller_Action_Abstract
{

    public $common_model 	= '';
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
		$this->view->details	= $this->translate->_('Details');
		$this->view->required	= $this->translate->_('Required');
		$this->view->colan 		= $this->translate->_('Colan');
	
		$this->common_model			= new SFA_Model_Index();
		$this->SFA_Comman			= new SFA_Comman();
		// For Second Language Name.
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
    * @name       salesmanAction
    * @since      19-12-2011
    * @version    Release: 1
    * @author     HD <hiren.d@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This action is use for Display Salesman Details
    */
    public function salesmanAction()
    {
		$this->view->params = $params = $this->getRequest()->getParams();
        $this->view->formdata = $formdata = $this->_request->getPost();
		
		if($formdata["hdDelete"]==1)
		{
			$ids = implode(',',$formdata['chk']);
			$param_array 	= array();
			$param_array[1]	= $ids;
			$param_array[2]	= $this->currentUser->username;
			
			$result 	= $this->SFA_Comman->executequery('CALL sp_delete_organization_salesman_salesman(?,?)',$param_array,'');
			
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
	
		$this->view->title	= $this->translate->_('Salesman');
		
		
		$cols_array 	= array('salesmancode','alternatesalesmancode','salesmanname1','getsalesmantype(type) as type','activestatus');
		$columns_show 	= array($this->translate->_('Code'),$this->translate->_('Alternate Code'),$this->translate->_('Salesman Name'),$this->translate->_('Type'),$this->translate->_('Status'));
		
		if($this->css == 'ar_') {
			$cols_array[1]	= 'arbsalesmanname1';
		}
		
		// prepare the configuration for grid
		$pagingparams = array(
				"show_grid_heading" => true,
				"grid_heading_message" => $this->translate->_('Overview'),
				"pagename" => $this->translate->_('Salesman'),
				"show_records_per_page" => isset($formdata["show_records_per_page"])?$formdata["show_records_per_page"]:10,				
				"show_searchbox" => true,
				"searchbox_value" => isset($formdata["grid_search_text"])?$formdata["grid_search_text"]:"",
				"show_selectbox" => true,
				"show_editlink" => true,
				"show_deletelink" => false,
				"selected_list" => $checked,
				"show_deleteall" => false,
				"primaryid" => "salesmancode",
				"status_cols" => array(
								array(
								"cols_name" => "activestatus",
								"status_change" => array("0"=>"Inactive","1"=>"Active")
								)
								),
				"editlink" => array("/account/salesman/addsalesman/id/#pattern#/edit/yes/","#pattern#"),
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
		
		// Mayur Bhayani on 23 July, 2012 - START - to check if need to download CSV
		$downloadCSV = (isset($formdata['downloadcsv'])) ? $formdata['downloadcsv'] : $params['downloadcsv'];
		
		// Hiren Dave on 19 Nov, 2012 - START - to check if need to print data
		$printData = (isset($formdata['printData'])) ? $formdata['printData'] : $params['printData'];
		
		// called stored procedure for counter
		//$result = $this->SFA_Comman->executequery('CALL sp_get_account_salesman_salesman(?,?,?,?,?,?,?,?)',$param_array,'');
		
		// Mayur Bhayani on 23 July, 2012 - START - Added 2 parameters $downloadCSV and $pagingparams 
		$result = $this->SFA_Comman->executequery('CALL sp_get_account_salesman_salesman(?,?,?,?,?,?,?,?)',$param_array,'',$downloadCSV,$printData,2,$pagingparams);
	
		$data_arr["count"] 		= $result[0][0]['counter'];
		$data_arr["data"][0] 	= $result[1];
		
		// pass the data in summary_showdatagrid() function & create a final variable for view
		$this->view->pagegridshow =  $pagingshow->summary_showdatagrid($data_arr);
		
		$this->view->addScriptPath(APPLICATION_PATH."/theme/default/datagrid/");
    }
    /**
    * @name       addsalesmanAction
    * @since      15-12-2011
    * @version    Release: 1
    * @author     HD <hiren.d@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This action is use for the add Area Details
    */
    public function addsalesmanAction()
    {
	$this->view->params = $params = $this->getRequest()->getParams();
        $this->view->formdata = $formdata = $this->_request->getPost();

	
	$this->view->select 	= $this->translate->_('Select');
	$this->view->missonefld	= $this->translate->_('Missed One Field');
	$this->view->youmissed	= $this->translate->_('You Missed');
	$this->view->highlated	= $this->translate->_('Fields. They have been highlighted.');
	
	//SFA_Comman::pre($formdata);

	$type_data = array();
	$type_data[0]['id']  = 1;
	$type_data[0]['val'] = 'Salesman';
	$type_data[1]['id']  = 2;
	$type_data[1]['val'] = 'Merchandizer';
	$type_data[2]['id']  = 3;
	$type_data[2]['val'] = 'Supervisor';
	$type_data[3]['id']  = 4;
	$type_data[3]['val'] = 'Helper';



	
	$this->view->type_data	= $type_data;
	

	if($formdata['txtcode'] && $formdata['txtname'])
	{
	    if($formdata['hdnid'] > 0)
	    {
		$param_checkarray 	= array();
		$param_checkarray[1]	= $formdata['hdnid'];
		$param_checkarray[2]	= $formdata['txtsfausername'];
		
		$username = $this->SFA_Comman->executequery('CALL sp_checkusername_account_salesman_addsalesman(?,?)',$param_checkarray,'');
		//print_r($username);exit;
		
		if(($formdata['txtsfausername'] != $formdata['txtsfausername_old']) && $username[0][0]['counter'] > 0) {
		    
		    SFA_Message::setErrorMsg($this->translate->_('Username Already Assign To Other User.'));
		    
		    $result  		= $this->SFA_Comman->executequery('CALL sp_get_account_salesman_addsalesman(?)',$params['id'],'');
		    $this->view->parent_com	= $result[1];
		    $this->view->msgkey 	= $result[2];
		    
		    $res['txtcode'] 		= $formdata['txtcode'];
		    $res['txtaltcode'] 		= $formdata['txtaltcode'];
		    $res['txtname'] 		= $formdata['txtname'];
		    $res['txtname_arb'] 	= $formdata['txtname_arb'];
		    $res['ddlmsg_key'] 		= $formdata['ddlmsg_key'];
		    $res['ddltype'] 		= $formdata['ddltype'];
		    $res['ddlprnt_com'] 	= $formdata['ddlprnt_com'];
		    $res['txtsfausername'] 	= $result[0][0]['username'];
		    $res['password'] 		= $formdata['txtsfapassword'];		    
		    $res['ddlstatus'] 		= $formdata['ddlstatus'];
		    $res['createddate']		= date('d-m-Y',strtotime($result[0][0]['cdat']));
		    $this->view->formdata 	= $res;		    
		}
		else
		{
		    $param_array = array();
		    $param_array[1] = trim($formdata['txtaltcode']);	//alternatesalesmancode
		    $param_array[2] = trim($formdata['txtname']);		//salesmanname1
		    $param_array[3] = trim($formdata['txtname_arb']);	//arbsalesmanname1		    
		    $param_array[4] = ($formdata['ddlmsg_key'] > 0) ? $formdata['ddlmsg_key'] : 'NULL' ; //messagekey
		    $param_array[5] = trim($formdata['ddltype']); 		//type
		    $param_array[6] = trim($formdata['ddlprnt_com']);	//parentcompany
		    $param_array[7] = trim($formdata['txtsfausername']);	//username
		    $param_array[8] = trim($formdata['txtsfapassword']);	//userpassword
		    $param_array[9] = trim($formdata['ddlstatus']);		//activestatus
			$param_array[10] = trim($formdata['txt_contactnumber']);
		    $param_array[11] = $this->currentUser->username;	//modified
		    $param_array[12] = $formdata['hdnid'];			//salesmancode
		    
		    $last_id = $this->SFA_Comman->executequery('CALL sp_edit_account_salesman_addsalesman(?,?,?,?,?,?,?,?,?,?,?,?)',$param_array,'');
		    
		    SFA_Message::setMsg($this->translate->_('Update Record'));
		    $this->_helper->redirector('salesman', 'salesman', 'account');
		}
	    }
	    else
	    {
		
		$param_checkarray 	= array();
		$param_checkarray[1]	= '0';
		$param_checkarray[2]	= $formdata['txtsfausername'];
		
		$username = $this->SFA_Comman->executequery('CALL sp_checkusername_account_salesman_addsalesman(?,?)',$param_checkarray,'');
		
		if($username[0][0]['counter'] > 0 ) {
		    
		    SFA_Message::setErrorMsg($this->translate->_('Username Already Assign To Other User.'));
		    
		    $result  		= $this->SFA_Comman->executequery('CALL sp_get_account_salesman_addsalesman(?)','0','');
		    $this->view->formdata['txtcode'] 	= ($result[0][0]['Auto_increment'] == '') ? '1' : $result[0][0]['Auto_increment'];
		    $this->view->parent_com	= $result[1];
		    $this->view->msgkey 	= $result[2];
		}
		else
		{
		    $param_array = array();
		    $param_array[1] = trim($formdata['txtaltcode']);	//alternatesalesmancode
		    $param_array[2] = trim($formdata['txtname']);	//salesmanname1
		    $param_array[3] = trim($formdata['txtname_arb']);	//arbsalesmanname1
		    $param_array[4] = ($formdata['ddlmsg_key'] > 0) ? $formdata['ddlmsg_key'] : 'NULL' ; //messagekey
		    $param_array[5] = trim($formdata['ddltype']); 	//type
		    $param_array[6] = trim($formdata['ddlprnt_com']);	//parentcompany
		    $param_array[7] = trim($formdata['txtsfausername']);	//username
		    $param_array[8] = trim($formdata['txtsfapassword']);	//userpassword
		    $param_array[9] = trim($formdata['ddlstatus']);	//activestatus
			$param_array[10] = trim($formdata['txt_contactnumber']);
		    $param_array[11] = $this->currentUser->username;	//created
		    
		    $last_id = $this->SFA_Comman->executequery('CALL sp_add_account_salesman_addsalesman(?,?,?,?,?,?,?,?,?,?,?)',$param_array,'');
		    
		    SFA_Message::setMsg($this->translate->_('New Record'));
		    $this->_helper->redirector('salesman', 'salesman', 'account');
		}
	    }
	   
	}
	elseif($params['id'] > 0)
	{
	    $result  		= $this->SFA_Comman->executequery('CALL sp_get_account_salesman_addsalesman(?)',$params['id'],'');
	    $res['txtcode'] 	= $result[0][0]['salesmancode'];
	    $res['txtaltcode'] 	= $result[0][0]['alternatesalesmancode'];
	    $res['txtname'] 	= $result[0][0]['salesmanname1'];
	    $res['txtname_arb'] = $result[0][0]['arbsalesmanname1'];
	    $res['ddlmsg_key'] 	= $result[0][0]['messagekey'];
	    $res['ddltype'] 	= $result[0][0]['type'];
	    $res['ddlprnt_com'] = $result[0][0]['parentcompany'];
	    $res['txtsfausername'] = $result[0][0]['username'];
	    $res['password'] 	= $result[0][0]['userpassword'];
	    $res['ddlstatus'] 	= $result[0][0]['activestatus'];
		$res['salesmanname2'] 	= $result[0][0]['salesmanname2'];
	    $res['createddate']	= date('d-m-Y',strtotime($result[0][0]['cdat']));
	    $this->view->formdata = $res;
	    
	    $this->view->parent_com		= $result[1];
	    $this->view->msgkey 		= $result[2];
	}
	else
	{
	    $result = $this->SFA_Comman->executequery('CALL sp_getcombobox_account_salesman_addsalesman()','','');
	    $this->view->parent_com		= $result[0];
	    $this->view->msgkey 		= $result[1];
	    $this->view->formdata['txtcode'] 	= ($result[2][0]['Auto_increment'] == '') ? '1' : $result[2][0]['Auto_increment'];
	}
    }
    public function salesmanmsgsAction()
    {
	$this->view->params = $params = $this->getRequest()->getParams();
        $this->view->formdata = $formdata = $this->_request->getPost();

	if($formdata["hdDelete"]==1)
	{
	    $ids = implode(',',$formdata['chk']);
	    $param_array 	= array();
	    $param_array[1]	= $ids;
	    $param_array[2]	= $this->currentUser->username;
	    
	    $result 	= $this->SFA_Comman->executequery('CALL sp_delete_account_salesman_salesmanmsg(?,?)',$param_array,'');
	    
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
	
	$this->view->title	= $this->translate->_('Salesman Message');
	
	$cols_array 	= array('messagekey','messagedescription','activestatus');
	$columns_show 	=  array($this->translate->_('Message Key'),$this->translate->_('Salesman Message'),$this->translate->_('Status'));
	
	
	// prepare the configuration for grid
	$pagingparams = array(
			"show_grid_heading" => true,
			"grid_heading_message" => $this->translate->_('Overview'),
			"show_records_per_page" => isset($formdata["show_records_per_page"])?$formdata["show_records_per_page"]:10,
			"show_searchbox" => true,
			"searchbox_value" => isset($formdata["grid_search_text"])?$formdata["grid_search_text"]:"",
			"pagename" => $this->translate->_('Salesman Message'),
			"show_selectbox" => true,
			"show_editlink" => true,
			"selected_list" => $checked,
			"show_deletelink" => false,			
			"show_deleteall" => false,
			"primaryid" => "messagekey",
			"status_cols" => array(
						   array(
						   "cols_name" => "activestatus",
						   "status_change" => array("0"=>"Inactive","1"=>"Active")
						   )
						   ),
			"editlink" => array("/account/salesman/addsalesmanmsgs/id/#pattern#/edit/yes/","#pattern#"),
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
	$result = $this->SFA_Comman->executequery('CALL sp_get_account_salesman_salesmanmsg(?,?,?,?,?,?,?,?)',$param_array,'',$downloadCSV,$printData,2,$pagingparams);
	$data_arr["count"]	= $result[0][0]['counter'];	
	$data_arr["data"][0] 	= $result[1];
	
	// pass the data in summary_showdatagrid() function & create a final variable for view
	$this->view->pagegridshow =  $pagingshow->summary_showdatagrid($data_arr);
	
	$this->view->addScriptPath(APPLICATION_PATH."/theme/default/datagrid/");
	

    }
    /**
    * @name       addsalesmanmsgsAction
    * @since      26-01-2012
    * @version    Release: 1
    * @author     HD <hiren.d@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This action is use for the add salesman messages
    */
    public function addsalesmanmsgsAction()
    {
	$this->view->params = $params = $this->getRequest()->getParams();
        $this->view->formdata = $formdata = $this->_request->getPost();
	
	$this->view->returnUrl = $_SERVER["HTTP_REFERER"];

	if(count($formdata) > 0 && $formdata['txtcode'] !='' && $formdata['txtmsg_desc'] !='' )
	{
	    $param_array 	= array();
	    $param_array[1]	= $formdata['txtmsg_desc'];
	    $param_array[2]	= $formdata['txtmesg1'];
	    $param_array[3]	= $formdata['txtmesg2'];
	    $param_array[4]	= $formdata['txtmesg3'];
	    $param_array[5]	= $formdata['txtmesg4'];
	    $param_array[6]	= $formdata['txtmesg_arb1'];
	    $param_array[7]	= $formdata['txtmesg_arb2'];
	    $param_array[8]	= $formdata['txtmesg_arb3'];
	    $param_array[9]	= $formdata['txtmesg_arb4'];
	    $param_array[10]	= $formdata['ddlstatus'];
	    $param_array[11]	= $this->currentUser->username;
	    $param_array[12]	= $formdata['txtaltcode'];
	    
	    if($formdata['hdnid'] > 0)
	    {
		$param_array[13] = $formdata['hdnid'];		
		$last_id = $this->SFA_Comman->executequery('CALL sp_edit_account_salesman_salesmanmsg(?,?,?,?,?,?,?,?,?,?,?,?,?)',$param_array,'');		
		SFA_Message::setMsg($this->translate->_('Update Record'));		
	    }
	    else
	    {
		$last_id = $this->SFA_Comman->executequery('CALL sp_add_account_salesman_salesmanmsg(?,?,?,?,?,?,?,?,?,?,?,?)',$param_array,'');
		SFA_Message::setMsg($this->translate->_('New Record'));
	    }
	    if($formdata["returnUrl"]!="")
		$this->_redirect($formdata["returnUrl"]);
	    else
		$this->_helper->redirector('messages', 'customer', 'account');
	}
	elseif($params['id'] > 0)
	{
	    $result = $this->SFA_Comman->executequery('CALL sp_get_account_salesman_addsalesmanmsg(?)',$params['id'],'');
	    $this->view->formdata = $result[0][0];
	    $this->view->formdata['createddate'] = date("d-m-Y",strtotime($result[0][0]['cdat']));	    
	}
	else
	{
	    $result = $this->SFA_Comman->executequery('CALL sp_get_account_salesman_addsalesmanmsg(?)','0','');	    
	    $this->view->formdata['messagekey'] = $result[0][0]['Auto_increment'];
	}	
    }   
}