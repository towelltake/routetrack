<?php
/**
* @name       Basic_IndexController
* @since      13 Oct, 2011
* @version    Release: 1.0
* @author     HD <hiren.d@elantechnologies.com>
* @copyright  2011 Elan Technologies
* @param
*
*
*/

class Basic_IndexController extends Basic_Library_Controller_Action_Abstract
{
    public $sec_lang 	= '';
	public $css			= '';

    /**
    * @name       init
    * @since      30-11-2011
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
		$this->view->colan			= $this->translate->_('Colan');
		$this->SFA_Comman 			= new SFA_Comman();
		$this->decimalplaces 		= $this->SFA_Comman->getdecimalplaces();
		$this->view->decimalplaces 	= $this->decimalplaces ;
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
    * @name       bankAction
    * @since      5-12-2011
    * @version    Release: 1
    * @author     HD <hiren.d@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This action is for display bank details
    *
    */
    public function bankAction()
    {
		$this->view->params = $params = $this->getRequest()->getParams();
        $this->view->formdata = $formdata = $this->_request->getPost();
	
		if($formdata["hdDelete"]==1)
		{
			$ids = implode(',',$formdata['chk']);
			$param_array 	= array();
			$param_array[1]	= $ids;
			$param_array[2]	= $this->currentUser->username;
			
			$result = $this->SFA_Comman->executequery('CALL sp_delete_basic_index_bankmaster(?,?)',$param_array,'');
			
			
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
	
		$this->view->title	= $this->translate->_('Bank Master');
		
		$this->view->missonefld	= $this->translate->_('Missed One Field');
		$this->view->youmissed	= $this->translate->_('You Missed');
		$this->view->highlated	= $this->translate->_('Fields. They have been highlighted.');
	
		
		$cols_array 	= array('bankname','arbbankname','activestatus', 'bankcode as edit_del_primary_id' );
		$columns_show 	=  array($this->translate->_('Name'),$this->translate->_('Name ('.$this->sec_lang.')'),$this->translate->_('Status'));
		
		 
		// prepare the configuration for grid
		$pagingparams = array(
				"show_grid_heading" => true,
				"grid_heading_message" => $this->translate->_('Overview'),
				"show_records_per_page" => isset($formdata["show_records_per_page"])?$formdata["show_records_per_page"]:10,
				"show_searchbox" => true,
				"searchbox_value" => isset($formdata["grid_search_text"])?$formdata["grid_search_text"]:"",
				"pagename" => $this->translate->_('Bank Master'),
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
				"primaryid" => "bankcode",
				"editlink" => array("/basic/index/addbank/id/#pattern#/edit/yes/","#pattern#"),
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
		$result = $this->SFA_Comman->executequery('CALL sp_get_basic_index_bank(?,?,?,?,?,?,?,?)',$param_array,'',$downloadCSV,$printData,2,$pagingparams);
	
		$data_arr["count"] 		= $result[0][0]['counter'];	
		$data_arr["data"][0] 	= $result[1];
		
		// pass the data in summary_showdatagrid() function & create a final variable for view
		$this->view->pagegridshow =  $pagingshow->summary_showdatagrid($data_arr);
		
		$this->view->addScriptPath(APPLICATION_PATH."/theme/default/datagrid/");
    } 
    /**
    * @name       addbankAction
    * @since      5-12-2011
    * @version    Release: 1
    * @author     HD <hiren.d@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This action is for add bank details
    *
    */
    public function addbankAction()
    {
	$this->view->params = $params = $this->getRequest()->getParams();
        $this->view->formdata = $formdata = $this->_request->getPost();

	
	$this->view->submit	= $this->translate->_('Submit');
	$this->view->reset	= $this->translate->_('Reset');
	
	$this->view->missonefld	= $this->translate->_('Missed One Field');
	$this->view->youmissed	= $this->translate->_('You Missed');
	$this->view->highlated	= $this->translate->_('Fields. They have been highlighted.');

	if(count($formdata) > 0)
	{
	    if($formdata['hdnid'] > 0 && $formdata['txtcode'] && $formdata['txtname']){
		
		$param_array = array();
		$param_array[1] = trim($formdata['txtname']); 		//BankName
		$param_array[2] = trim($formdata['txtname_arb']);	//ArbBankName
		$param_array[3] = '';					//BankBalance
		$param_array[4] = $this->currentUser->username;		//Modified
		$param_array[5] = '';					//Modified Date
		$param_array[6] = trim($formdata['ddlstatus']);		//ActiveStatus
		$param_array[7] = trim($formdata['txtaltcode']);	//AlternateCode
		$param_array[8] = trim($formdata['ddltype']);		//Type
		$param_array[9] = trim($formdata['txtacno']);		//ACNumber
		$param_array[10] = trim($formdata['hdnid']);		//BankCode
		
		$last_id = $this->SFA_Comman->executequery('CALL sp_edit_basic_index_addbank(?,?,?,?,?,?,?,?,?,?)',$param_array,'');
		
		SFA_Message::setMsg($this->translate->_('Update Record'));
	    }
	    else if($formdata['txtcode'] && $formdata['txtaltcode'] && $formdata['txtname'])
	    {
		$param_array = array();
		$param_array[1] = trim($formdata['txtname']); 		//BankName
		$param_array[2] = trim($formdata['txtname_arb']);	//ArbBankName
		$param_array[3] = 0;					//BankBalance
		$param_array[4] = $this->currentUser->username;		//Modified
		$param_array[5] = '';					//Modified Date
		$param_array[6] = trim($formdata['ddlstatus']);		//ActiveStatus
		$param_array[7] = trim($formdata['txtaltcode']);	//AlternateCode
		$param_array[8] = trim($formdata['ddltype']);		//Type
		$param_array[9] = trim($formdata['txtacno'])?trim($formdata['txtacno']):0;		//ACNumber
		
		$last_id = $this->SFA_Comman->executequery('CALL sp_add_basic_index_addbank(?,?,?,?,?,?,?,?,?)',$param_array,'');
		
		SFA_Message::setMsg($this->translate->_('New Record'));
	    }
	    else
	    {
		SFA_Message::setMsg($this->translate->_('Please Enter all required data'));
	    }
	    if($last_id > 0){
		$this->_helper->redirector('bank', 'index', 'basic');
	    }
	}
	elseif($params['id'] > 0)
	{
	    $result  		= $this->SFA_Comman->executequery('CALL sp_get_basic_index_addbank(?)',$params['id'],'');	    
	    $res['txtcode'] 	= $result[0][0]['bankcode'];
	    $res['txtaltcode'] 	= $result[0][0]['alternatecode'];
	    $res['txtname'] 	= $result[0][0]['bankname'];
	    $res['txtname_arb'] = $result[0][0]['arbbankname'];	    
	    $res['ddltype'] 	= $result[0][0]['type'];
	    $res['txtacno'] 	= $result[0][0]['acnumber'];
	    $res['ddlstatus'] 	= $result[0][0]['activestatus'];
		$res['createddate'] = date('d-m-Y',strtotime($result[0][0]['cdat']));
	    $this->view->formdata = $res;	    
	}
	else
	{
	    $table_name = 'bankmaster';
	    $code = $this->SFA_Comman->executequery('CALL sp_get_table_last_id(?)',$table_name,'');	    
	    $this->view->formdata['txtcode'] = ($code[0][0]['Auto_increment'] == '') ? '1' : $code[0][0]['Auto_increment'];
	}
    }
    /**
    * @name       cashdescAction
    * @since      30-11-2011
    * @version    Release: 1
    * @author     HD <hiren.d@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This action is for display cash description
    *
    */
    public function cashdescAction()
    {
		$this->view->params 	= $params 	= $this->getRequest()->getParams();
        $this->view->formdata 	= $formdata = $this->_request->getPost();
	
		if($formdata["hdDelete"]==1)
		{
			$ids = implode(',',$formdata['chk']);
			$param_array 	= array();
			$param_array[1]	= $ids;
			$param_array[2]	= $this->currentUser->username;
			
			$result 	= $this->SFA_Comman->executequery('CALL sp_delete_basic_index_cashdesc(?,?)',$param_array,'');
			
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
		
		$this->view->title	= $this->translate->_('Cash Description');
		
		$cols_array 	= array('code','description','arbdescription');
		$columns_show 	=  array($this->translate->_('Code'),$this->translate->_('Description'),$this->translate->_('Description ('.$this->sec_lang.')'));
		
		
		
		// ADDITIONAL WHERE CONDITION
		if($Common_NameSpace->tdate)
			$additional_where_condition[] = " (TransactionDate BETWEEN '".date("Y-m-d 00:00:00",strtotime(str_replace('/', '-', $Common_NameSpace->tdate)))."' AND '".date("Y-m-d 23:59:59",strtotime(str_replace('/', '-', $Common_NameSpace->tdate)))."' )";
		  
		// prepare the configuration for grid
		$pagingparams = array(
				"show_grid_heading" => true,
				"grid_heading_message" => $this->translate->_('Overview'),
				"show_records_per_page" => isset($formdata["show_records_per_page"])?$formdata["show_records_per_page"]:10,
				"show_searchbox" => true,
				"searchbox_value" => isset($formdata["grid_search_text"])?$formdata["grid_search_text"]:"",
				"pagename" => $this->translate->_('Cash Description'),
				"show_selectbox" => true,
				"show_editlink" => true,
				"selected_list" => $checked,
				"show_deletelink" => false,			
				"show_deleteall" => false,
				"primaryid" => "code",
				"editlink" => array("/basic/index/addcashdesc/id/#pattern#/edit/yes/","#pattern#"),
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
		$result = $this->SFA_Comman->executequery('CALL sp_get_basic_index_cashdesc(?,?,?,?,?,?,?,?)',$param_array,'',$downloadCSV,$printData,2,$pagingparams);
	
		$data_arr["count"] 		= $result[0][0]['counter'];
		$data_arr["data"][0] 	= $result[1];
		
		// pass the data in summary_showdatagrid() function & create a final variable for view
		$this->view->pagegridshow =  $pagingshow->summary_showdatagrid($data_arr);
		
		$this->view->addScriptPath(APPLICATION_PATH."/theme/default/datagrid/");
    }

    /**
    * @name       addcashdescAction
    * @since      30-11-2011
    * @version    Release: 1
    * @author     HD <hiren.d@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This action is for add cash description
    *
    */
    public function addcashdescAction()
    {
		$this->view->params 	= $params = $this->getRequest()->getParams();
        $this->view->formdata 	= $formdata = $this->_request->getPost();

		$this->view->missonefld	= $this->translate->_('Missed One Field');
		$this->view->youmissed	= $this->translate->_('You Missed');
		$this->view->highlated	= $this->translate->_('Fields. They have been highlighted.');
	
		if($formdata['txtcode'] && $formdata['txtdesc'] && $formdata['txtdesc_arb'])
		{
			if($formdata['hdnid'] > 0)
			{	    
			$param_array = array();
			$param_array[1] = trim($formdata['txtdesc']); 		//Description
			$param_array[2] = trim($formdata['txtdesc_arb']);	//ArbDescription
			$param_array[3] = '';								//hhcdescription
			$param_array[4] = $this->currentUser->username;		//modified
			$param_array[5] = $formdata['hdnid'];				//Code
			$param_array[6] = $formdata['txtaltcode'];			//alternatecode
			
			$last_id = $this->SFA_Comman->executequery('CALL sp_edit_basic_index_addcashdesc(?,?,?,?,?,?)',$param_array,'');
			
			SFA_Message::setMsg($this->translate->_('Update Record'));
			}
			else
			{
			$param_array = array();
			$param_array[1] = trim($formdata['txtdesc']); 		//Description
			$param_array[2] = trim($formdata['txtdesc_arb']);	//ArbDescription
			$param_array[3] = $this->currentUser->username;		//created
			$param_array[4] = $formdata['txtaltcode'];			//alternatecode
			
			$last_id = $this->SFA_Comman->executequery('CALL sp_add_basic_index_addcashdesc(?,?,?,?)',$param_array,'');
			
			SFA_Message::setMsg($this->translate->_('New Record'));	
			}
			$this->_helper->redirector('cashdesc', 'index', 'basic');
		}
		elseif($params['id'] > 0)
		{
			$result  			= $this->SFA_Comman->executequery('CALL sp_get_basic_index_addcashdesc(?)',$params['id'],'');
			$res['txtcode'] 	= $result[0][0]['code'];
			$res['txtdesc'] 	= $result[0][0]['description'];
			$res['txtdesc_arb'] = $result[0][0]['arbdescription'];
			$res['createddate'] = date('d-m-Y',strtotime($result[0][0]['cdat']));
			$res['txtaltcode']	= $result[0][0]['alternatecode'];
			$this->view->formdata = $res;	    
		}
		else
		{
			$table_name = 'cashdesc';
			$code = $this->SFA_Comman->executequery('CALL sp_get_table_last_id(?)',$table_name,'');
			$this->view->formdata['txtcode'] = ($code[0][0]['Auto_increment'] == '') ? '1' : $code[0][0]['Auto_increment'];
		}
    }
    /**
    * @name       comapnyAction
    * @since      18-12-2011
    * @version    Release: 1
    * @author     HD <hiren.d@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This action is for display company information
    *
    */
    public function companyAction()
    {
	$this->view->params = $params = $this->getRequest()->getParams();
        $this->view->formdata = $formdata = $this->_request->getPost();

	if($formdata["hdDelete"]==1)
	{
	    $ids = implode(',',$formdata['chk']);
	    $param_array 	= array();
	    $param_array[1]	= $ids;
	    $param_array[2]	= $this->currentUser->username;
	    
	    $result 	= $this->SFA_Comman->executequery('CALL sp_delete_basic_index_company(?,?)',$param_array,'');
	    
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
	
	$this->view->title	= $this->translate->_('Company');	
	
	$cols_array 	= array('cmpycode','name','arbcompanyname','countryname','activestatus');
	$columns_show 	=  array($this->translate->_('Code'),$this->translate->_('Company Name'),$this->translate->_('Company Name ('.$this->sec_lang.')'),$this->translate->_('Country Name'),$this->translate->_('Status'));
	
	if($this->css == 'ar_') {
		$cols_array[3]	= 'arbcountryname';
	}
	
	// prepare the configuration for grid
	$pagingparams = array(
			"show_grid_heading" => true,
			"grid_heading_message" => $this->translate->_('Overview'),
			"show_records_per_page" => isset($formdata["show_records_per_page"])?$formdata["show_records_per_page"]:10,
			"pagename" => $this->translate->_('Company'),
			"show_searchbox" => true,
			"searchbox_value" => isset($formdata["grid_search_text"])?$formdata["grid_search_text"]:"",
			"show_selectbox" => true,
			"show_editlink" => true,
			"selected_list" => $checked,
			"show_deletelink" => false,
			"show_deleteall" => false,
			"primaryid" => "cmpycode",
			"status_cols" => array(
						    array(
							"cols_name" => "activestatus",
							"status_change" => array("0"=>"Inactive","1"=>"Active")
							)
						    ),
			"editlink" => array("/basic/index/addcompany/id/#pattern#/edit/yes/","#pattern#"),
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
	$result = $this->SFA_Comman->executequery('CALL sp_get_basic_index_company(?,?,?,?,?,?,?,?)',$param_array,'',$downloadCSV,$printData,2,$pagingparams);

	$data_arr["count"] 		= $result[0][0]['counter'];
	$data_arr["data"][0] 	= $result[1];	
	
	// pass the data in summary_showdatagrid() function & create a final variable for view
	$this->view->pagegridshow =  $pagingshow->summary_showdatagrid($data_arr);
	
	$this->view->addScriptPath(APPLICATION_PATH."/theme/default/datagrid/");
    }

    
    
    /**
    * @name       addcomapnyAction
    * @since      30-11-2011
    * @version    Release: 1
    * @author     HD <hiren.d@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This action is for add cash description
    *
    */
    public function addcompanyAction()
    {
	$this->view->params = $params = $this->getRequest()->getParams();
        $this->view->formdata = $formdata = $this->_request->getPost();

	
		
	$this->view->missonefld	= $this->translate->_('Missed One Field');
	$this->view->youmissed	= $this->translate->_('You Missed');
	$this->view->highlated	= $this->translate->_('Fields. They have been highlighted.');
	
	if($formdata['txtcode'] && $formdata['txtcom_name'] && $formdata['txtaddress'] && $formdata['txttel_num'])
	{
	    $parent_company = $formdata['rdtype'] == '2' ? $formdata['ddlparent_com'] : $formdata['hdnid'];
	    
	    if($formdata['hdnid'] > 0)
	    {
			
		$param_array = array();
		$param_array[1]  = trim($formdata['txtaltcode']); 	//alternatecmpycode
		$param_array[2]  = trim($formdata['txtcom_name']);	//name
		$param_array[3]  = trim($formdata['txtcom_arab']); 	//arbcompanyname
		$param_array[4]  = $parent_company;			//parentcompany
		$param_array[5]  = trim($formdata['txtcont_name']); 	//contactname
		$param_array[6]  = trim($formdata['txtaddress']);	//address
		$param_array[7]  = trim($formdata['txttel_num']); 	//telephone
		$param_array[8]  = trim($formdata['txtfax']);		//fax
		$param_array[9]  = trim($formdata['txtzipcode']); 	//zipcode
		$param_array[10] = trim($formdata['txtcnt_code']);	//countrycode
		$param_array[11] = trim($formdata['txtcnt_name']); 	//countryname
		$param_array[12] = trim($formdata['txttaxregistration']);	//txttaxregistration
		$param_array[13] = trim($formdata['txtdis_code']); 	//distributorcode2
		$param_array[14] = trim($formdata['ddlstatus']);	//activestatus
		$param_array[15] = $this->currentUser->username;	//modified
		$param_array[16] = $formdata['hdnid'];			//cmpycode
		
		$last_id = $this->SFA_Comman->executequery('CALL sp_edit_basic_index_addcompany(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',$param_array,'');
		
		SFA_Message::setMsg($this->translate->_('Update Record'));
	    }
	    else
	    {
		$param_array 	 = array();
		$param_array[1]  = trim($formdata['txtaltcode']); 	//alternatecmpycode
		$param_array[2]  = trim($formdata['txtcom_name']);	//name
		$param_array[3]  = trim($formdata['txtcom_arab']); 	//arbcompanyname
		$param_array[4]  = $parent_company;			//parentcompany
		$param_array[5]  = trim($formdata['txtcont_name']); 	//contactname
		$param_array[6]  = trim($formdata['txtaddress']);	//address
		$param_array[7]  = trim($formdata['txttel_num']); 	//telephone
		$param_array[8]  = trim($formdata['txtfax']);		//fax
		$param_array[9]  = trim($formdata['txtzipcode']); 	//zipcode
		$param_array[10] = trim($formdata['txtcnt_code']);	//countrycode
		$param_array[11] = trim($formdata['txtcnt_name']); 	//countryname
		$param_array[12] = trim($formdata['txttaxregistration']);	//txttaxregistration
		$param_array[13] = trim($formdata['txtdis_code']); 	//distributorcode2
		$param_array[14] = trim($formdata['ddlstatus']);	//activestatus
		$param_array[15] = $this->currentUser->username;	//created
		
		$last_id = $this->SFA_Comman->executequery('CALL sp_add_basic_index_addcompany(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',$param_array,'');
		
		SFA_Message::setMsg($this->translate->_('New Record'));
	    }
	    $this->_helper->redirector('company', 'index', 'basic');
	}
	elseif($params['id'] > 0)
	{
	    $result  			= $this->SFA_Comman->executequery('CALL sp_get_basic_index_addcompany(?)',$params['id'],'');
	    $res['txtcode'] 		= $result[0][0]['cmpycode'];
	    $res['txtaltcode'] 		= $result[0][0]['alternatecmpycode'];
	    $res['txtcom_name'] 	= $result[0][0]['name'];
	    $res['txtcom_arab'] 	= $result[0][0]['arbcompanyname'];
	    $res['ddlparent_com'] 	= $result[0][0]['parentcompany'];
	    $res['txtcont_name']	= $result[0][0]['contactname'];
	    $res['txtaddress'] 		= $result[0][0]['address'];	    
	    $res['txttel_num'] 		= $result[0][0]['telephone'];
	    $res['txtfax'] 		= $result[0][0]['fax'];
	    $res['txtzipcode'] 		= $result[0][0]['zipcode'];
	    $res['txtcnt_code'] 	= $result[0][0]['countrycode'];
	    $res['txtcnt_name'] 	= $result[0][0]['countryname'];	    
	    $res['txttaxregistration'] 	= $result[0][0]['txttaxregistration'];
	    $res['txtdis_code'] 	= $result[0][0]['distributorcode'];
	    $res['ddlstatus'] 		= $result[0][0]['activestatus'];
	    $res['createddate'] 	= date('d-m-Y',strtotime($result[0][0]['cdat']));
	    $this->view->formdata 	= $res;
	    $this->view->parent_com	= $result[1];
	}	
	else
	{
	    $result = $this->SFA_Comman->executequery('CALL sp_getcombobox_basic_index_addcompany()','','');
	    $this->view->parent_com		= $result[0];
	    $this->view->formdata['txtcode'] 	= ($result[1][0]['Auto_increment'] == '') ? '1' : $result[1][0]['Auto_increment'];
	}
    }

   
    /**
    * @name       currencyAction
    * @since      18-12-2011
    * @version    Release: 1
    * @author     HD <hiren.d@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This action is for display currency information
    *
    */
    public function currencyAction()
    {
		$this->view->params = $params = $this->getRequest()->getParams();
        $this->view->formdata = $formdata = $this->_request->getPost();		
		
		if($formdata["hdDelete"]==1)
		{
			$ids = implode(',',$formdata['chk']);
			$param_array 	= array();
			$param_array[1]	= $ids;
			$param_array[2]	= $this->currentUser->username;
			
			$result 	= $this->SFA_Comman->executequery('CALL sp_delete_basic_index_currency(?,?)',$param_array,'');
			
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
	
		$this->view->title	= $this->translate->_('Currency');	
		
		$cols_array 	= array('currencycode','currencyname','defaultcurrency');
		$columns_show 	= array($this->translate->_('Currency Code'),$this->translate->_('Currency Name'),$this->translate->_('Default Currency'));
		
		if($this->css == 'ar_'){
			$cols_array[1]	= 'arbcurrencyname';
		}
		
		// prepare the configuration for grid
		$pagingparams = array(
				"show_grid_heading" => true,
				"grid_heading_message" => $this->translate->_('Overview'),
				"show_records_per_page" => isset($formdata["show_records_per_page"])?$formdata["show_records_per_page"]:10,
				"show_searchbox" => true,
				"searchbox_value" => isset($formdata["grid_search_text"])?$formdata["grid_search_text"]:"",
				"pagename" => $this->translate->_('Currency'),
				"show_selectbox" => true,
				"show_editlink" => true,
				"show_deletelink" => false,
				"show_deleteall" => false,
				"selected_list" => $checked,				
				"status_cols" => array(
						array (
							"cols_name" => "defaultcurrency",
							"status_change" => array("0"=>"No","1"=>"Yes")
						)
					),
				"primaryid" => "currencycode",
				"editlink" => array("/basic/index/addcurrency/id/#pattern#/edit/yes/","#pattern#"),
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
		$result = $this->SFA_Comman->executequery('CALL sp_get_basic_index_currency(?,?,?,?,?,?,?,?)',$param_array,'',$downloadCSV,$printData,2,$pagingparams);
	
		$data_arr["count"] 		= $result[0][0]['counter'];
		$data_arr["data"][0] 	= $result[1];
		
		// pass the data in summary_showdatagrid() function & create a final variable for view
		$this->view->pagegridshow =  $pagingshow->summary_showdatagrid($data_arr);
		
		$this->view->addScriptPath(APPLICATION_PATH."/theme/default/datagrid/");
    }
   
    
    /**
    * @name       addcurrencyAction
    * @since      15-12-2011
    * @version    Release: 1
    * @author     HD <hiren.d@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This action is for add cash description
    *
    */
    public function addcurrencyAction()
    {
	$this->view->params = $params = $this->getRequest()->getParams();
        $this->view->formdata = $formdata = $this->_request->getPost();
	
	
	// IF EXTRA PARAMS ARE REQUIRED
	$ex_param = "";
	if(isset($params["id"]) && $params["id"]>0)
		$ex_param = "/key/".$params["id"];

	
	
	$this->view->back	= $this->translate->_('Back');
	$this->view->cancel	= $this->translate->_('Cancel');
	$this->view->save	= $this->translate->_('Save');
	$this->view->add 	= $this->translate->_('ADD');
	
	$this->view->select 	= $this->translate->_('Select');
	$this->view->missonefld	= $this->translate->_('Missed One Field');
	$this->view->youmissed	= $this->translate->_('You Missed');
	$this->view->highlated	= $this->translate->_('Fields. They have been highlighted.');
	

	if($formdata['txtcurrname'] !='' )
	{
	    $Settings_NameSpace = new Zend_Session_Namespace('Settings');
	    $Settings_NameSpace->decimal	= $formdata['txtdecplace'];
	    
	    if($formdata['hdnid'] > 0)
	    {	
		$param_array 	= array();
		$param_array[1] = $formdata['hdnid'];				//CurrencyCode
		$param_array[2] = trim($formdata["txtcurrname"]);		//CurrencyName
		$param_array[3] = trim($formdata["txtarbcurname"]);		//ArbCurrencyName		
		$param_array[4] = $formdata['txtcurrsymbol'];			//currencysymbol
		$param_array[5] = $formdata['txtarbcurrsymbol'];		//arbcurrencysymbol
		$param_array[6] = $this->currentUser->username;			//modified
		$param_array[7] = $formdata['txtaltcode'];			//alternatecode
		$param_array[8] = $formdata['chkdefault'];			//defaultcurrency
		$param_array[9] = $formdata['txtdecplace'];			//decimalplaces
		
		$last_id = $this->SFA_Comman->executequery('CALL sp_edit_basic_index_addcurrency(?,?,?,?,?,?,?,?,?)',$param_array,'');
		
		SFA_Message::setMsg($this->translate->_('Update Record'));
		$this->_helper->redirector('currency', 'index', 'basic');
	    }
	    elseif($formdata['hdnid'] == 0)
	    {
		$param_array 	= array();		
		$param_array[1] = trim($formdata["txtcurrname"]);		//CurrencyName
		$param_array[2] = trim($formdata["txtarbcurname"]);		//ArbCurrencyName		
		$param_array[3] = $formdata['txtcurrsymbol'];			//currencysymbol
		$param_array[4] = $formdata['txtarbcurrsymbol'];		//arbcurrencysymbol
		$param_array[5] = $this->currentUser->username;			//created
		$param_array[6] = $formdata['txtaltcode'];			//alternatecode				
		$param_array[7] = $formdata['chkdefault'];			//defaultcurrency
		$param_array[8] = $formdata['txtdecplace'];			//decimalplaces
		
		$last_id = $this->SFA_Comman->executequery('CALL sp_add_basic_index_addcurrency(?,?,?,?,?,?,?,?)',$param_array,'');
		$last_id = $last_id[0][0]['last_id'];
	
		SFA_Message::setMsg($this->translate->_('New Record'));
		$this->_helper->redirector('addcurrency', 'index', 'basic',array('id'=>$last_id,'edit'=>'yes'));
	    }
	    else
	    {
		SFA_Message::setMsg($this->translate->_('Please Enter all required data'));
	    }
	}
	elseif($params['id'] > 0)
	{
	    $result  					= $this->SFA_Comman->executequery('CALL sp_get_basic_index_addcurrency(?)',$params['id'],'');
		
	    $res['txtcurrcode'] 			= $result[1][0]['currencycode'];
	    $res['txtcurrname'] 			= $result[1][0]['currencyname'];
	    $res['txtarbcurname'] 			= $result[1][0]['arbcurrencyname'];	    
	    $res['txtcurrsymbol']			= $result[1][0]['currencysymbol'];
	    $res['txtarbcurrsymbol']			= $result[1][0]['arbcurrencysymbol'];
	    $res['txtaltcode']				= $result[1][0]['alternatecode'];
	    $res['chkdefault']				= $result[1][0]['defaultcurrency'];
	    $res['txtdecplace']				= $result[1][0]['decimalplaces'];	    
	    $res['createddate']				= $result[1][0]['cdat'];
	    $this->view->formdata 			= $res;
	    $this->view->formdata['curr_code'] 		= $result[3][0]['currencycode'];
	    $this->view->formdata['curr_name'] 		= $result[3][0]['currencyname'];
	    $this->view->formdata['curr_symbol'] 	= $result[3][0]['currencysymbol'];
	    $this->view->currency_data			= $result[0];
	    
	    $this->view->itemgrid    = $this->view->BaseUrl("/".$params['module']."/".$params['controller']."/addcurrencydetailgrid".$ex_param);
	}
	else
	{
	    
	    $result = $this->SFA_Comman->executequery('CALL sp_get_basic_index_addcurrency(?)','0','');
	    $this->view->currency_data			= $result[0];
	    $this->view->formdata['txtcurrcode']	= ($result[1][0]['Auto_increment'] == '') ? '1' : $result[1][0]['Auto_increment'];
	    $this->view->formdata['curr_code'] 		= $result[3][0]['currencycode'];
	    $this->view->formdata['curr_name'] 		= $result[3][0]['currencyname'];
	    $this->view->formdata['curr_symbol'] 	= $result[3][0]['currencysymbol'];
	}
    }
    /**
    * @name       addcurrencydetailgrid
    * @since      16-03-2012
    * @version    Release: 1
    * @author     HD <hiren.d@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This action is use display and edit currency detail data.
    */
    public function addcurrencydetailgridAction()
    {
        $this->view->params = $params = $this->getRequest()->getParams();

		// gathering combo value of itemcode while in editmode
		$resultot = $this->SFA_Comman->executequery('CALL sp_combo_currency_filter(?)',$params["key"],'');
		
		// prepare the proper format of array for currency combo in edit mode
		foreach($resultot[0] as $e_resultot)
		{
			$outlet_dd[$e_resultot['id']] = $e_resultot['val'];
		}
		
		// final array format for itemcode combo in edit mode
		$key_ms = "currencydetailcode";
		$value_ms = $outlet_dd;
		$mastervalues = array($key_ms=>$value_ms);	
	
		// column header to be displayed	
		$curr_det_code	= $this->translate->_('Exchange Currency');
		$curr_name		= $this->translate->_('Currency Name');
		$exc_rate 		= $this->translate->_('Exchange Rate');
		$desc 			= $this->translate->_('Description');
		
		$currency_name 	= $resultot["1"]["0"]["currencyname"];
		$columns_show  	= array($curr_det_code,$curr_name,$exc_rate,$desc);
		

		$columns_array 	= array('t2.currencydetailcode','t1.currencyname AS currencyname','FORMAT(t2.exchangerate,'.$this->decimalplaces.') as exchangerate','code as edit_del_primary_id');
		
		if($this->css == 'ar_') {
			$columns_array[1]	= 't1.arbcurrencyname AS currencyname';
		}
		
		// DELETE THE RECORD
		if($params["delete"]=="yes"){
			// sp for delete currencydetail
			$r_delete = $this->SFA_Comman->executequery('CALL sp_delete_basic_index_addcurrencydetailgrid(?)',array(1=>$params["id"]),'');
				SFA_Message::setMsg($this->translate->_('Delete Record'));
		}
	
		// UPDATE THE RECORD
		if($params["update"]=="yes"){
			
			$updateData["1"] = $params["exchangerate"];
			$updateData["2"] = $params["id"];
			$updateData["3"] = $params["key"];	    
			
			// call sp for edit currencydetail
			$r_edit = $this->SFA_Comman->executequery('CALL sp_edit_basic_index_addcurrencydetailgrid(?,?,?)',$updateData,'');
			
			SFA_Message::setMsg($this->translate->_('Update Record'));			
		}
	
		// IF EXTRA PARAMS ARE REQUIRED
		$ex_param = "";
		$additional_where_condition = array();
		if(isset($params["key"]) && $params["key"]>0){
			$ex_param = "/key/".$params["key"];
			$additional_where_condition[] = ' (t2.currencycode = "'.$params["key"].'" )';
		}
		
		$amt_right = array('exchangerate');
	
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
					 "noeditfields" => array('currencydetailcode','currencyname','description'),
					 "primaryid" => "code",
					 "currentlink" => array("/".$params['module']."/".$params['controller']."/".$params['action'].$ex_param),
					 "deletelink" => array("/id/#pattern#/delete/yes/msg/curr","#pattern#"),
					 "editlink" => array("/id/#pattern#/edit/yes","#pattern#"),
					 "nodata_message" => $this->translate->_('No Record(s) Found'),
					 "fetch_columns_inquery" => $columns_array,
					 "show_columns" => $columns_show,
					 "additional_where" => $additional_where_condition,
					 "show_columns_right_side" =>$amt_right,
					 "show_header_right_side"=>array($this->translate->_('Exchange Rate')),
					 );
	
		// WHEN GRID IS IN EDIT MODE
		if($params["edit"]=="yes"){
	
			$pagingparams["editmode"] = true;
			$pagingparams["editmodeid"] = $params["id"];
			$pagingparams["editmodevalue"] = "code";  // put table's prymary key here
		}
	
		$extra_field = ',CONCAT("<b>1 '.$currency_name.' = ",get_deciaml_palces(t2.exchangerate,'.$this->decimalplaces.')," ",t1.currencyname,"</b>") AS description';
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
		$param_array[8] = $extra_field;
	
		
		// called stored procedure for counter
		$result = $this->SFA_Comman->executequery('CALL sp_get_basic_index_addcurrencydetailgrid(?,?,?,?,?,?,?)',$param_array,'');    
		$data_arr["count"] 	= $result[0][0]['counter'];	
		$data_arr["data"][0] 	= $result[1];
		
		$this->view->pagegridshow =  $pagingshow->summary_showdatagrid($data_arr);
		$this->view->addScriptPath(APPLICATION_PATH."/theme/default/datagrid/");
	
		$this->render("ajaxgrid");
    }
    /**
    * @name       addcurrencydetail
    * @since      18-12-2011
    * @version    Release: 1
    * @author     HD <hiren.d@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This action is for add currency detail information
    *
    */
    public function addcurrencydetailAction()
    {
        $formdata = $this->_request->getPost();
		$param_array 	= array();		
		$param_array[1] = trim($formdata["hidnCurrencyCode"]);	//CurrencyName
		$param_array[2] = trim($formdata["ddlexchcode"]);	//Detail Code
		$param_array[3] = trim($formdata["txtexcrate"]);	//Exchange Rate
		$param_array[4] = $this->currentUser->username;		//Exchange Rate
		
		$last_id = $this->SFA_Comman->executequery('CALL sp_add_basic_index_addcurrencydetail(?,?,?,?)',$param_array,'');
		
		if($last_id)
			SFA_Message::setMsg($this->translate->_('New Record'));
		exit;
    }    

    /**
    * @name       regionmanagerAction
    * @since      13-02-2012
    * @version    Release: 1
    * @author     HD <hiren.d@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This action is for display regional manager
    *
    */
    public function regionmanagerAction()
    {
		$this->view->params = $params = $this->getRequest()->getParams();
        $this->view->formdata = $formdata = $this->_request->getPost();

		if($formdata["hdDelete"]==1)
		{
			$ids = implode(',',$formdata['chk']);
			$param_array 	= array();
			$param_array[1]	= $ids;
			$param_array[2]	= $this->currentUser->username;
			
			$result 	= $this->SFA_Comman->executequery('CALL sp_delete_basic_index_regionmanager(?,?)',$param_array,'');
			
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
	
		$this->view->title	= $this->translate->_('Region Manager');
	
		$cols_array 	= array('regionmanagercode','regionmanagername','name','region.activestatus');
		$columns_show 	=  array($this->translate->_('Code'),$this->translate->_('Region Manager'),$this->translate->_('Parent Company'),$this->translate->_('Status'));
		
		if($this->css == 'ar_') {
			$cols_array[1]	= 'arbregionmanagername';
			$cols_array[2]	= 'arbcompanyname';
		}
	
		// prepare the configuration for grid
		$pagingparams = array(
				"show_grid_heading" => true,
				"grid_heading_message" => $this->translate->_('Overview'),
				"show_records_per_page" => isset($formdata["show_records_per_page"])?$formdata["show_records_per_page"]:10,
				"show_searchbox" => true,
				"searchbox_value" => isset($formdata["grid_search_text"])?$formdata["grid_search_text"]:"",
				"pagename" => $this->translate->_('Region Manager'),
				"show_selectbox" => true,
				"selected_list" => $checked,
				"show_editlink" => true,
				"show_deletelink" => false,
				"show_deleteall" => false,
				"status_cols" => array(
							array (
								"cols_name" => "activestatus",
								"status_change" => array("0"=>"Inactive","1"=>"Active")
							)
							),
				"primaryid" => "regionmanagercode",
				"editlink" => array("/basic/index/addregionmanager/id/#pattern#/edit/yes/","#pattern#"),
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
		$result = $this->SFA_Comman->executequery('CALL sp_get_basic_index_regionmaster(?,?,?,?,?,?,?,?)',$param_array,'',$downloadCSV,$printData,2,$pagingparams);
	
		$data_arr["count"]	= $result[0][0]['counter'];		
		$data_arr["data"][0]= $result[1];
		
		// pass the data in summary_showdatagrid() function & create a final variable for view
		$this->view->pagegridshow =  $pagingshow->summary_showdatagrid($data_arr);
		
		$this->view->addScriptPath(APPLICATION_PATH."/theme/default/datagrid/");
    }
    /**
    * @name       addregionmanagerAction
    * @since      15-12-2011
    * @version    Release: 1
    * @author     HD <hiren.d@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This action is for add regional manager
    *
    */
    public function addregionmanagerAction()
    {
	$this->view->params = $params = $this->getRequest()->getParams();
        $this->view->formdata = $formdata = $this->_request->getPost();		
	
	$this->view->returnUrl = $_SERVER["HTTP_REFERER"];

	if(count($formdata) > 0)
	{
	    $param_array 	= array();
	    $param_array[1]	= $formdata['txtaltcode'];
	    $param_array[2]	= $formdata['txtcom_name'];
	    $param_array[3]	= $formdata['txtcom_arab'];
	    $param_array[4]	= $formdata['ddlparent_com'];
	    $param_array[5]	= $formdata['ddlstatus'];
	    $param_array[6]	= $this->currentUser->username;
	    
	    if($formdata['hdnid'] > 0)
	    {
		$param_array[7]	= $formdata['hdnid'];
		$last_id = $this->SFA_Comman->executequery('CALL sp_edit_basic_index_addregionmanager(?,?,?,?,?,?,?)',$param_array,'');
		SFA_Message::setMsg($this->translate->_('Update Record'));
	    }
	    else
	    {
		$last_id = $this->SFA_Comman->executequery('CALL sp_add_basic_index_addregionmanager(?,?,?,?,?,?)',$param_array,'');
		SFA_Message::setMsg($this->translate->_('New Record'));
	    }	
	    if($formdata["returnUrl"]!="")
		$this->_redirect($formdata["returnUrl"]);
	    else
		$this->_helper->redirector('regionmanager', 'index', 'basic');
	}
	elseif($params['id'] > 0)
	{
	    $result = $this->SFA_Comman->executequery('CALL sp_get_basic_index_addregionmanager(?)',$params['id'],'');
	    
	    $res['txtcode']		= $result[0][0]['regionmanagercode'];
	    $res['txtaltcode']		= $result[0][0]['alternatecode'];
	    $res['txtcom_name']		= $result[0][0]['regionmanagername'];
	    $res['txtcom_arab']		= $result[0][0]['arbregionmanagername'];
	    $res['ddlparent_com']	= $result[0][0]['parentcompany'];
	    $res['ddlstatus']		= $result[0][0]['activestatus'];
	    $res['createddate']		= date('d-m-Y',strtotime($result[0][0]['cdat']));
	    
	    $this->view->formdata 	= $res;
	    $this->view->parent_company_data	= $result[1];
	}
	else
	{
	    $result = $this->SFA_Comman->executequery('CALL sp_get_basic_index_addregionmanager(?)','0','');
	    $this->view->formdata['txtcode']	= ($result[0][0]['Auto_increment'] == '') ? '1' : $result[0][0]['Auto_increment'];
	    $this->view->parent_company_data	= $result[1];
	}
    }
    /**
    * @name       depotmanagerAction
    * @since      13-02-2012
    * @version    Release: 1
    * @author     HD <hiren.d@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This action is for display branch/depot manager
    *
    */
    public function depotmanagerAction()
    {
		$this->view->params = $params = $this->getRequest()->getParams();
        $this->view->formdata = $formdata = $this->_request->getPost();
	
		if($formdata["hdDelete"]==1)
		{
			$ids = implode(',',$formdata['chk']);
			$param_array 	= array();
			$param_array[1]	= $ids;
			$param_array[2]	= $this->currentUser->username;
			
			$result 	= $this->SFA_Comman->executequery('CALL sp_delete_basic_index_depotmanager(?,?)',$param_array,'');
			
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

		$this->view->title	= $this->translate->_('Branch/Depot Manager');
		
		$cols_array 	= array('branchmanagercode','branchmanagername','name','branch.activestatus');
		$columns_show 	=  array($this->translate->_('Code'),$this->translate->_('Branch/Depot Manager'),$this->translate->_('Parent Company'),$this->translate->_('Status'));
		
		if($this->css == 'ar_') {
			$cols_array[1]	= 'arbbranchmanagername';
			$cols_array[2]	= 'arbcompanyname';
		}
	

		// prepare the configuration for grid
		$pagingparams = array(
				"show_grid_heading" => true,
				"grid_heading_message" => $this->translate->_('Overview'),
				"show_records_per_page" => isset($formdata["show_records_per_page"])?$formdata["show_records_per_page"]:10,
				"show_searchbox" => true,
				"searchbox_value" => isset($formdata["grid_search_text"])?$formdata["grid_search_text"]:"",
				"pagename" => $this->translate->_('Depot Manager'),
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
				"primaryid" => "branchmanagercode",
				"editlink" => array("/basic/index/adddepotmanager/id/#pattern#/edit/yes/","#pattern#"),
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
		$result = $this->SFA_Comman->executequery('CALL sp_get_basic_index_depotmanager(?,?,?,?,?,?,?,?)',$param_array,'',$downloadCSV,$printData,2,$pagingparams);
	
		$data_arr["count"]	= $result[0][0]['counter'];
		$data_arr["data"][0]	= $result[1];
		
		// pass the data in summary_showdatagrid() function & create a final variable for view
		$this->view->pagegridshow =  $pagingshow->summary_showdatagrid($data_arr);
		
		$this->view->addScriptPath(APPLICATION_PATH."/theme/default/datagrid/");
    }
    /**
    * @name       adddepotmanagerAction
    * @since      15-12-2011
    * @version    Release: 1
    * @author     HD <hiren.d@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This action is for add branch/depot manager
    *
    */
    public function adddepotmanagerAction()
    {
	$this->view->params = $params = $this->getRequest()->getParams();
        $this->view->formdata = $formdata = $this->_request->getPost();		
	
	$this->view->returnUrl = $_SERVER["HTTP_REFERER"];

	if(count($formdata) > 0)
	{
	    $param_array 	= array();
	    $param_array[1]	= $formdata['txtaltcode'];
	    $param_array[2]	= $formdata['txtname'];
	    $param_array[3]	= $formdata['txtcom_arab'];
	    $param_array[4]	= $formdata['ddlparent_com'];
	    $param_array[5]	= $formdata['ddlstatus'];
	    $param_array[6]	= $this->currentUser->username;
	    
	    if($formdata['hdnid'] > 0)
	    {
		$param_array[7]	= $formdata['hdnid'];
		$last_id = $this->SFA_Comman->executequery('CALL sp_edit_basic_index_adddepotmanager(?,?,?,?,?,?,?)',$param_array,'');
		SFA_Message::setMsg($this->translate->_('Update Record'));
	    }
	    else
	    {
		$last_id = $this->SFA_Comman->executequery('CALL sp_add_basic_index_adddepotmanager(?,?,?,?,?,?)',$param_array,'');
		SFA_Message::setMsg($this->translate->_('New Record'));
	    }	
	    if($formdata["returnUrl"]!="")
		$this->_redirect($formdata["returnUrl"]);
	    else
		$this->_helper->redirector('depotmanager', 'index', 'basic');
	}
	elseif($params['id'] > 0)
	{
	    $result = $this->SFA_Comman->executequery('CALL sp_get_basic_index_adddepotmanager(?)',$params['id'],'');
	    
	    $res['txtcode']		= $result[0][0]['branchmanagercode'];
	    $res['txtaltcode']		= $result[0][0]['alternatebranchmanagercode'];
	    $res['txtname']		= $result[0][0]['branchmanagername'];
	    $res['txtcom_arab']		= $result[0][0]['arbbranchmanagername'];
	    $res['ddlparent_com']	= $result[0][0]['parentcompany'];
	    $res['ddlstatus']		= $result[0][0]['activestatus'];
	    $res['createddate']		= date('d-m-Y',strtotime($result[0][0]['cdat']));
	    
	    $this->view->formdata 	= $res;
	    $this->view->parent_company_data	= $result[1];
	}
	else
	{
	    $result = $this->SFA_Comman->executequery('CALL sp_get_basic_index_adddepotmanager(?)','0','');
	    $this->view->formdata['txtcode']	= ($result[0][0]['Auto_increment'] == '') ? '1' : $result[0][0]['Auto_increment'];
	    $this->view->parent_company_data	= $result[1];
	}
	
	
	
	
    }
    /**
    * @name       areamanagerAction
    * @since      13-02-2012
    * @version    Release: 1
    * @author     HD <hiren.d@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This action is for display branch/depot manager
    *
    */
    public function areamanagerAction()
    {
		$this->view->params = $params = $this->getRequest()->getParams();
        $this->view->formdata = $formdata = $this->_request->getPost();
		
		if($formdata["hdDelete"]==1)
		{
			$ids = implode(',',$formdata['chk']);
			$param_array 	= array();
			$param_array[1]	= $ids;
			$param_array[2]	= $this->currentUser->username;
			
			$result = $this->SFA_Comman->executequery('CALL sp_delete_basic_index_areamanager(?,?)',$param_array,'');
			
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
	
		$this->view->title	= $this->translate->_('Area Manager');
		$cols_array 		= array('areamanagercode','areamanagername','name','area.activestatus');
		$columns_show 		= array($this->translate->_('Code'),$this->translate->_('Area Manager'),$this->translate->_('Parent Company'),$this->translate->_('Status'));
		
		if($this->css == 'ar_') {
			$cols_array[1]	= 'arbareamanagername';
			$cols_array[2]	= 'arbcompanyname';
		}
	
		// prepare the configuration for grid
		$pagingparams = array(
				"show_grid_heading" => true,
				"grid_heading_message" => $this->translate->_('Overview'),
				"show_records_per_page" => isset($formdata["show_records_per_page"])?$formdata["show_records_per_page"]:10,
				"show_searchbox" => true,
				"searchbox_value" => isset($formdata["grid_search_text"])?$formdata["grid_search_text"]:"",
				"pagename" => $this->translate->_('Area Manager'),
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
				"primaryid" => "areamanagercode",
				"editlink" => array("/basic/index/addareamanager/id/#pattern#/edit/yes/","#pattern#"),
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
		$result = $this->SFA_Comman->executequery('CALL sp_get_basic_index_areamanager(?,?,?,?,?,?,?,?)',$param_array,'',$downloadCSV,$printData,2,$pagingparams);
	
		$data_arr["count"]	= $result[0][0]['counter'];
		$data_arr["data"][0]	= $result[1];
		
		// pass the data in summary_showdatagrid() function & create a final variable for view
		$this->view->pagegridshow =  $pagingshow->summary_showdatagrid($data_arr);
		
		$this->view->addScriptPath(APPLICATION_PATH."/theme/default/datagrid/");
    }
    /**
    * @name       addareamanagerAction
    * @since      15-12-2011
    * @version    Release: 1
    * @author     HD <hiren.d@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This action is for add branch/depot manager
    *
    */
    public function addareamanagerAction()
    {
	$this->view->params = $params = $this->getRequest()->getParams();
        $this->view->formdata = $formdata = $this->_request->getPost();
	
	$this->view->returnUrl = $_SERVER["HTTP_REFERER"];

	if(count($formdata) > 0)
	{
	    $param_array 	= array();
	    $param_array[1]	= $formdata['txtaltcode'];
	    $param_array[2]	= $formdata['txtname'];
	    $param_array[3]	= $formdata['txtcom_arab'];
	    $param_array[4]	= $formdata['ddlparent_com'];
	    $param_array[5]	= $formdata['ddlstatus'];
	    $param_array[6]	= $this->currentUser->username;
	    
	    if($formdata['hdnid'] > 0)
	    {
		$param_array[7]	= $formdata['hdnid'];
		$last_id = $this->SFA_Comman->executequery('CALL sp_edit_basic_index_addareamanager(?,?,?,?,?,?,?)',$param_array,'');
		SFA_Message::setMsg($this->translate->_('Update Record'));
	    }
	    else
	    {
		$last_id = $this->SFA_Comman->executequery('CALL sp_add_basic_index_addareamanager(?,?,?,?,?,?)',$param_array,'');
		SFA_Message::setMsg($this->translate->_('New Record'));
	    }	
	    if($formdata["returnUrl"]!="")
		$this->_redirect($formdata["returnUrl"]);
	    else
		$this->_helper->redirector('areamanager', 'index', 'basic');
	}
	elseif($params['id'] > 0)
	{
	    $result = $this->SFA_Comman->executequery('CALL sp_get_basic_index_addareamanager(?)',$params['id'],'');
	    
	    $res['txtcode']		= $result[0][0]['areamanagercode'];
	    $res['txtaltcode']		= $result[0][0]['alternateareamanagercode'];
	    $res['txtname']		= $result[0][0]['areamanagername'];
	    $res['txtcom_arab']		= $result[0][0]['arbareamanagername'];
	    $res['ddlparent_com']	= $result[0][0]['parentcompany'];
	    $res['ddlstatus']		= $result[0][0]['activestatus'];
	    $res['createddate']		= date('d-m-Y',strtotime($result[0][0]['cdat']));
	    
	    $this->view->formdata 	= $res;
	    $this->view->parent_company_data	= $result[1];
	}
	else
	{
	    $result = $this->SFA_Comman->executequery('CALL sp_get_basic_index_addareamanager(?)','0','');
	    $this->view->formdata['txtcode']	= ($result[0][0]['Auto_increment'] == '') ? '1' : $result[0][0]['Auto_increment'];
	    $this->view->parent_company_data	= $result[1];	    
	}
    }
    /**
    * @name       supervisorAction
    * @since      17-02-2012
    * @version    Release: 1
    * @author     HD <hiren.d@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This action is for display supervisor
    *
    */
    public function supervisorAction()
    {
	$this->view->params = $params = $this->getRequest()->getParams();
        $this->view->formdata = $formdata = $this->_request->getPost();
	
	if($formdata["hdDelete"]==1)
	{
	    $ids = implode(',',$formdata['chk']);
	    $param_array 	= array();
	    $param_array[1]	= $ids;
	    $param_array[2]	= $this->currentUser->username;
	    
	    $result 	= $this->SFA_Comman->executequery('CALL sp_delete_basic_index_supervisor(?,?)',$param_array,'');
	    
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

	$this->view->title	= $this->translate->_('Supervisor');
	$cols_array 	= array('supervisorcode','supervisorname','arbsupervisorname','name','supervisor.activestatus');
	$columns_show 	=  array($this->translate->_('Code'),$this->translate->_('Name'),$this->translate->_('Name ('.$this->sec_lang.')'),$this->translate->_('Parent Company'),$this->translate->_('Status'));
	

	// prepare the configuration for grid
	$pagingparams = array(
			"show_grid_heading" => true,
			"grid_heading_message" => $this->translate->_('Overview'),
			"show_records_per_page" => isset($formdata["show_records_per_page"])?$formdata["show_records_per_page"]:10,
			"show_searchbox" => true,
			"searchbox_value" => isset($formdata["grid_search_text"])?$formdata["grid_search_text"]:"",
			"pagename" => $this->translate->_('Supervisor'),
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
			"primaryid" => "supervisorcode",
			"editlink" => array("/basic/index/addsupervisor/id/#pattern#/edit/yes/","#pattern#"),
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
	$result = $this->SFA_Comman->executequery('CALL sp_get_basic_index_supervisor(?,?,?,?,?,?,?,?)',$param_array,'',$downloadCSV,$printData,2,$pagingparams);
	

	$data_arr["count"]	= $result[0][0]['counter'];
	$data_arr["data"][0]	= $result[1];
	
	// pass the data in summary_showdatagrid() function & create a final variable for view
	$this->view->pagegridshow =  $pagingshow->summary_showdatagrid($data_arr);
	
	$this->view->addScriptPath(APPLICATION_PATH."/theme/default/datagrid/");

        
    }
    /**
    * @name       addareamanagerAction
    * @since      15-12-2011
    * @version    Release: 1
    * @author     HD <hiren.d@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This action is for add branch/depot manager
    *
    */
    public function addsupervisorAction()
    {
	$this->view->params = $params = $this->getRequest()->getParams();
        $this->view->formdata = $formdata = $this->_request->getPost();

	$this->view->returnUrl = $_SERVER["HTTP_REFERER"];

	if(count($formdata) > 0)
	{
	    $param_array 	= array();
	    $param_array[1]	= $formdata['txtaltcode'];
	    $param_array[2]	= $formdata['txtname'];
	    $param_array[3]	= $formdata['txtcom_arab'];
	    $param_array[4]	= $formdata['ddlparent_com'];
	    $param_array[5]	= $formdata['ddlstatus'];
		$param_array[6]	= $formdata['txt_contactnumber'];
	    $param_array[7]	= $this->currentUser->username;
	    
	    if($formdata['hdnid'] > 0)
	    {
		$param_array[8]	= $formdata['hdnid'];
		$last_id = $this->SFA_Comman->executequery('CALL sp_edit_basic_index_addsupervisor(?,?,?,?,?,?,?,?)',$param_array,'');
		SFA_Message::setMsg($this->translate->_('Update Record'));
	    }
	    else
	    {
		$last_id = $this->SFA_Comman->executequery('CALL sp_add_basic_index_addsupervisor(?,?,?,?,?,?,?)',$param_array,'');
		SFA_Message::setMsg($this->translate->_('New Record'));
	    }	
	    if($formdata["returnUrl"]!="")
		$this->_redirect($formdata["returnUrl"]);
	    else
		$this->_helper->redirector('supervisor', 'index', 'basic');
	}
	elseif($params['id'] > 0)
	{
	    $result = $this->SFA_Comman->executequery('CALL sp_get_basic_index_addsupervisor(?)',$params['id'],'');
	    
	    $res['txtcode']		= $result[0][0]['supervisorcode'];
	    $res['txtaltcode']		= $result[0][0]['alternatesupervisorcode'];
	    $res['txtname']		= $result[0][0]['supervisorname'];
	    $res['txtcom_arab']		= $result[0][0]['arbsupervisorname'];
	    $res['ddlparent_com']	= $result[0][0]['parentcompany'];
	    $res['ddlstatus']		= $result[0][0]['activestatus'];
		$res['contactno']		= $result[0][0]['contactno'];
	    $res['createddate']		= date('d-m-Y',strtotime($result[0][0]['cdat']));
	    
	    $this->view->formdata 	= $res;
	    $this->view->parent_company_data	= $result[1];
	}
	else
	{
	    $result = $this->SFA_Comman->executequery('CALL sp_get_basic_index_addsupervisor(?)','0','');
	    $this->view->formdata['txtcode']	= ($result[0][0]['Auto_increment'] == '') ? '1' : $result[0][0]['Auto_increment'];
	    $this->view->parent_company_data	= $result[1];	    
	}	
    }
    /**
    * @name       inventorylocAction
    * @since      17/02/2012
    * @version    Release: 1
    * @author     HD <hiren.d@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This action is for display inventory location
    *
    */
    public function inventorylocAction()
    {
		$this->view->params = $params = $this->getRequest()->getParams();
        $this->view->formdata = $formdata = $this->_request->getPost();

		if($formdata["hdDelete"]==1)
		{
			$ids = implode(',',$formdata['chk']);
			$param_array 	= array();
			$param_array[1]	= $ids;
			$param_array[2]	= $this->currentUser->username;
			
			$result 	= $this->SFA_Comman->executequery('CALL sp_delete_basic_index_inventoryloc(?,?)',$param_array,'');
			
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
	

		$this->view->title 		= $this->translate->_('Inventory Location');		
		$this->view->missonefld	= $this->translate->_('Missed One Field');
		$this->view->youmissed	= $this->translate->_('You Missed');
		$this->view->highlated	= $this->translate->_('Fields. They have been highlighted.');
	
	
	
	$cols_array 	= array('code','description',);
	$columns_show 	=  array($this->translate->_('Code'),$this->translate->_('Description'));
	
	if($this->css == 'ar_'){
		$cols_array[1]	= 'arbdescription';
	}
	
	 
	// prepare the configuration for grid
	$pagingparams = array(
			"show_grid_heading" => true,
			"grid_heading_message" => $this->translate->_('Overview'),
			"show_records_per_page" => isset($formdata["show_records_per_page"])?$formdata["show_records_per_page"]:10,
			"show_searchbox" => true,
			"searchbox_value" => isset($formdata["grid_search_text"])?$formdata["grid_search_text"]:"",
			"pagename" => $this->translate->_('Inventory Location'),
			"show_selectbox" => true,
			"selected_list" => $checked,
			"show_editlink" => true,
			"show_deletelink" => false,			
			"show_deleteall" => false,
			"primaryid" => "code",
			"editlink" => array("/basic/index/addinventoryloc/id/#pattern#/edit/yes/","#pattern#"),
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
	$result = $this->SFA_Comman->executequery('CALL sp_get_basic_index_inventoryloc(?,?,?,?,?,?,?,?)',$param_array,'',$downloadCSV,$printData,2,$pagingparams);

	$data_arr["count"] 	= $result[0][0]['counter'];	
	$data_arr["data"][0] 	= $result[1];
	
	// pass the data in summary_showdatagrid() function & create a final variable for view
	$this->view->pagegridshow =  $pagingshow->summary_showdatagrid($data_arr);
	
	$this->view->addScriptPath(APPLICATION_PATH."/theme/default/datagrid/");
    }
    /**
    * @name       addinventorylocAction
    * @since      17/02/2012
    * @version    Release: 1
    * @author     HD <hiren.d@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This action is for add inventoryloc
    *
    */
    public function addinventorylocAction()
    {
	$this->view->params 	= $params = $this->getRequest()->getParams();
        $this->view->formdata 	= $formdata = $this->_request->getPost();	
	$this->view->returnUrl 	= $_SERVER["HTTP_REFERER"];	

	if($formdata['txtcode']!='' && $formdata['txtdesc'] && $formdata['txtdesc_arb'])
	{
	    $param_array 	= array();
	    $param_array[1] = $formdata['txtdesc'];
	    $param_array[2] = $formdata['txtdesc_arb'];
	    $param_array[3] = $formdata['txtaltcode'];
	    $param_array[4] = $this->currentUser->username;;
	    
	    if($formdata['hdnid'] > 0)
	    {
		$param_array[5] = $formdata['hdnid'];
		$result = $this->SFA_Comman->executequery('CALL sp_edit_basic_index_addinventoryloc(?,?,?)',$param_array,'');		    
		SFA_Message::setMsg($this->translate->_('Update Record'));
	    }
	    else
	    {
		$result = $this->SFA_Comman->executequery('CALL sp_add_basic_index_addinventoryloc(?,?)',$param_array,'');
		SFA_Message::setMsg($this->translate->_('New Record'));
	    }
	    if($formdata["returnUrl"]!="")
		$this->_redirect($formdata["returnUrl"]);
	    else
		$this->_helper->redirector('inventoryloc', 'index', 'basic');
	}
	elseif($params['id'] > 0)
	{
	    $result = $this->SFA_Comman->executequery('CALL sp_get_basic_index_addinventoryloc(?)',$params['id'],'');
	    $res['txtcode'] 	= $result[0][0]['code'];
	    $res['txtdesc'] 	= $result[0][0]['description'];
	    $res['txtdesc_arb'] = $result[0][0]['arbdescription'];
	    $res['txtaltcode'] 	= $result[0][0]['alternatecode'];
	    $res['createddate'] = date('d-m-Y',strtotime($result[0][0]['cdat']));
	    
	    $this->view->formdata = $res;
	}
	else
	{
	    $code = $this->SFA_Comman->executequery('CALL sp_get_basic_index_addinventoryloc(?)','0','');
	    $this->view->formdata['txtcode'] = ($code[0][0]['Auto_increment'] == '') ? '1' : $code[0][0]['Auto_increment'];
	}
    }
     /**
    * @name       salesmanagerAction
    * @since      13-02-2012
    * @version    Release: 1
    * @author     HD <hiren.d@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This action is for display national sales manager
    *
    */
    public function salesmanagerAction()
    {
	$this->view->params = $params = $this->getRequest()->getParams();
	$this->view->formdata = $formdata = $this->_request->getPost();
	
	if($formdata["hdDelete"]==1)
	{
	    $ids = implode(',',$formdata['chk']);
	    $param_array 	= array();
	    $param_array[1]	= $ids;
	    $param_array[2]	= $this->currentUser->username;
	    
	    $result 	= $this->SFA_Comman->executequery('CALL sp_delete_basic_index_salesmanager(?,?)',$param_array,'');
	    
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

	$cols_array 	= array('manager.nationalsalesmanagercode','nationalsalesmanagername','name','manager.activestatus');
	$columns_show 	=  array($this->translate->_('Code'),$this->translate->_('National Sales Manager'),$this->translate->_('Parent Company'),$this->translate->_('Status'));
	
	if($this->css == 'ar_'){
		$cols_array[1]	= 'arbnationalsalesmanagername';
		$cols_array[2]	= 'arbcompanyname';
	}

	$this->view->title	= $this->translate->_('National Sales Manager');
	
	// prepare the configuration for grid
	   $pagingparams = array(
			   "show_grid_heading" => true,
			   "grid_heading_message" => $this->translate->_('Overview'),
			   "show_records_per_page" => isset($formdata["show_records_per_page"])?$formdata["show_records_per_page"]:10,
			   "show_searchbox" => true,
			   "searchbox_value" => isset($formdata["grid_search_text"])?$formdata["grid_search_text"]:"",
			   "pagename" => $this->translate->_('National Sales Manager'),
			   "show_selectbox" => true,
			   "selected_list" => $checked,
			   "show_editlink" => true,
			   "show_deletelink" => false,
			   "show_deleteall" => false,
			   "primaryid" => "nationalsalesmanagercode",
			   "status_cols" => array(
						    array(
						    "cols_name" => "activestatus",
						    "status_change" => array("0"=>"Inactive","1"=>"Active")
						    )
						),
			   "editlink" => array("/basic/index/addsalesmanager/id/#pattern#/edit/yes/","#pattern#"),
			   "nodata_message" => $this->translate->_("No Record(s) Found"),
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
        $result	= $this->SFA_Comman->executequery('CALL sp_get_basic_index_salesmanager(?,?,?,?,?,?,?,?)',$param_array,'',$downloadCSV,$printData,2,$pagingparams);
        
       // SFA_Comman::pre($result);
    
        $data_arr["count"] 	= $result[0][0]['counter'];       
        $data_arr["data"][0] = $result[1];
        
        // pass the data in summary_showdatagrid() function & create a final variable for view
        $this->view->pagegridshow =  $pagingshow->summary_showdatagrid($data_arr);
	   
        $this->view->addScriptPath(APPLICATION_PATH."/theme/default/datagrid/");
    }
    /**
    * @name       addsalesmanagerAction
    * @since      15-12-2011
    * @version    Release: 1
    * @author     HD <hiren.d@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This action is for add salesman manager
    *
    */
    public function addsalesmanagerAction()
    {
		$this->view->params 	= $params = $this->getRequest()->getParams();
		$this->view->formdata 	= $formdata = $this->_request->getPost();
	
		if($formdata['txtcode'] !='' && $formdata['txtcom_name'] !='' && $formdata['txtcom_arab'] !='')
		{
			$param_array 	= array();
			$param_array[1]	= $formdata['txtcom_name'];
			$param_array[2]	= $formdata['txtcom_arab'];
			$param_array[3]	= $formdata['ddlparent_com'];
			$param_array[4]	= $formdata['ddlstatus'];
			$param_array[5]	= $this->currentUser->username;
			$param_array[6]	= $formdata['txtaltcode'];
	
			if($formdata['hdnid'] > 0)
			{
				$param_array[7]	= $formdata['hdnid'];
				$result = $this->SFA_Comman->executequery('CALL sp_edit_basic_index_addsalesmanager(?,?,?,?,?,?,?)',$param_array,'');
				SFA_Message::setMsg($this->translate->_('Update Record'));
			}
			else
			{
				$result = $this->SFA_Comman->executequery('CALL sp_add_basic_index_addsalesmanager(?,?,?,?,?,?)',$param_array,'');
				SFA_Message::setMsg($this->translate->_('New Record'));
			}
			$this->_helper->redirector('salesmanager', 'index', 'basic');
		}
		elseif($params['id'] > 0)
		{
			$result = $this->SFA_Comman->executequery('CALL sp_get_basic_index_addsalesmanager(?)',$params['id'],'');
			$res['txtcode'] 		= $result[0][0]['nationalsalesmanagercode'];
			$res['txtaltcode'] 		= $result[0][0]['alternatecode'];
			$res['ddlparent_com'] 	= $result[0][0]['parentcompany'];
			$res['txtcom_name'] 	= $result[0][0]['nationalsalesmanagername'];
			$res['txtcom_arab'] 	= $result[0][0]['arbnationalsalesmanagername'];
			$res['ddlstatus'] 		= $result[0][0]['activestatus'];
			$res['createddate'] 	= date('d-m-Y',strtotime($result[0][0]['cdat']));
			
			$this->view->formdata 		= $res;
			$this->view->parent_company_data 	= $result[1];
		}
		else
		{
			$result = $this->SFA_Comman->executequery('CALL sp_get_basic_index_addsalesmanager(?)','0','');
			$this->view->formdata['txtcode'] 	= ($result[0][0]['Auto_increment'] == '') ? '1' : $result[0][0]['Auto_increment'];
			$this->view->parent_company_data 	= $result[1];
		}
    }
}