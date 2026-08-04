<?php
/**
* @name       Basic_ReasonsController
* @since      13 Oct, 2011
* @version    Release: 1.0
* @author     HD <hiren.d@elantechnologies.com>
* @copyright  2011 Elan Technologies
* @param
*
*
*/

class Basic_ReasonsController extends Basic_Library_Controller_Action_Abstract
{
    /**
    * @name       init
    * @since      02-09-2011
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
    * @name       badreturnAction
    * @since      30-11-2011
    * @version    Release: 1
    * @author     HD <hiren.d@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This action is for display bad reason
    *
    */
    public function badreturnAction()
    {

	$this->view->params = $params = $this->getRequest()->getParams();
        $this->view->formdata = $formdata = $this->_request->getPost();
	
	
	if($formdata["hdDelete"]==1)
	{
	    $ids = implode(',',$formdata['chk']);
	    $param_array 	= array();
	    $param_array[1]	= $ids;
	    $param_array[2]	= $this->currentUser->username;
	    
	    $result 	= $this->SFA_Comman->executequery('CALL sp_delete_basic_reason_badreturn(?,?)',$param_array,'');
	    
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
	$this->view->title 	= $this->translate->_('Bad Return ');
	
	
	$cols_array 	= array('code','description','arbdescription');
	$columns_show 	=  array($this->translate->_('Code'),$this->translate->_('Description'),$this->translate->_('Description ('.$this->sec_lang.')'));
    
	
       // prepare the configuration for grid
       $pagingparams = array(
		       "show_grid_heading" => true,
		       "grid_heading_message" => $this->translate->_('Overview'),
		       "show_records_per_page" => isset($formdata["show_records_per_page"])?$formdata["show_records_per_page"]:10,
		       "show_searchbox" => true,
			   "searchbox_value" => isset($formdata["grid_search_text"])?$formdata["grid_search_text"]:"",
			   "pagename" => $this->translate->_('Bad Return'),
		       "show_selectbox" => true,
		       "selected_list" => $checked,
		       "show_editlink" => true,
		       "show_deletelink" => false,
		       "show_deleteall" => false,
		       "primaryid" => "code",
		        "editlink" => array("/basic/reasons/addbadreturn/id/#pattern#/edit/yes/","#pattern#"),
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
		$result = $this->SFA_Comman->executequery('CALL sp_get_basic_reason_badreturn(?,?,?,?,?,?,?,?)',$param_array,'',$downloadCSV,$printData,2,$pagingparams);
		
		$data_arr["count"] 		= $result[0][0]['counter'];
		$data_arr["data"][0] 	= $result[1];
       
       // pass the data in summary_showdatagrid() function & create a final variable for view
       $this->view->pagegridshow =  $pagingshow->summary_showdatagrid($data_arr);
       
       $this->view->addScriptPath(APPLICATION_PATH."/theme/default/datagrid/");
    }
    /**
    * @name       addbadreturnAction
    * @since      30-11-2011
    * @version    Release: 1
    * @author     HD <hiren.d@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This action is for add bad reason
    *
    */
    public function addbadreturnAction()
    {
	$this->view->params = $params = $this->getRequest()->getParams();
	$this->view->formdata = $formdata = $this->_request->getPost();

	
	$this->view->missonefld	= $this->translate->_('Missed One Field');
	$this->view->youmissed	= $this->translate->_('You Missed');
	$this->view->highlated	= $this->translate->_('Fields. They have been highlighted.');

	if($formdata['txtcode'] && $formdata['txtdesc'])
	{
		if($formdata['hdnid'] > 0)
		{
			$param_array = array();		    
			$param_array[1] = trim($formdata['txtdesc']);		//Description
			$param_array[2] = trim($formdata['txtdesc_arb']);	//ArbDescription
			$param_array[3] = $formdata['hdnid'];				//Code
			$param_array[4] = $this->currentUser->username;		//modified
			$param_array[5] = $formdata['txtaltcode'];			//alternatecode
			
			$last_id = $this->SFA_Comman->executequery('CALL sp_edit_basic_reason_addbadreturn(?,?,?,?,?)',$param_array,'');
			
			SFA_Message::setMsg($this->translate->_('Update Record'));
		}
		else{
			$param_array = array();
			$param_array[1] = trim($formdata['txtdesc']);		//Description
			$param_array[2] = trim($formdata['txtdesc_arb']);	//ArbDescription		    
			$param_array[3] = $this->currentUser->username;		//created
			$param_array[4] = $formdata['txtaltcode'];			//alternatecode
			
			$last_id = $this->SFA_Comman->executequery('CALL sp_add_basic_reason_addbadreturn(?,?,?,?)',$param_array,'');
			
			SFA_Message::setMsg($this->translate->_('New Record'));
		}
		$this->_helper->redirector('badreturn', 'reasons', 'basic');
	}
	elseif($params['id'] > 0)
	{
		$result  			= $this->SFA_Comman->executequery('CALL sp_get_basic_reason_addbadreturn(?)',$params['id'],'');
		$res['txtcode'] 	= $result[0][0]['code'];
		$res['txtdesc'] 	= $result[0][0]['description'];
		$res['txtdesc_arb'] = $result[0][0]['arbdescription'];
		$res['createddate'] = date('d-m-Y',strtotime($result[0][0]['cdat']));
		$res['txtaltcode']	= $result[0][0]['alternatecode'];
		$this->view->formdata = $res;
	}
	else
	{
		$table_name = 'expiryreturnreasons';
		$code = $this->SFA_Comman->executequery('CALL sp_get_table_last_id(?)',$table_name,'');	    
		$this->view->formdata['txtcode'] = ($code[0][0]['Auto_increment'] == '') ? '1' : $code[0][0]['Auto_increment'];
	}
    }

    

    /**
    * @name       goodreturnAction
    * @since      30-11-2011
    * @version    Release: 1
    * @author     HD <hiren.d@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This action is for display bad reason
    *
    */
    public function goodreturnAction()
    {
	$this->view->params = $params = $this->getRequest()->getParams();
	$this->view->formdata = $formdata = $this->_request->getPost();
	
	$this->view->title 	= $this->translate->_('Good Return');	
	
	if($formdata["hdDelete"]==1)
	{
	    $ids = implode(',',$formdata['chk']);
	    $param_array 	= array();
	    $param_array[1]	= $ids;
	    $param_array[2]	= $this->currentUser->username;
	    
	    $result 	= $this->SFA_Comman->executequery('CALL sp_delete_basic_reason_goodreturn(?,?)',$param_array,'');
	    
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
	
	
	$cols_array 	= array('code','description','arbdescription');
	$columns_show 	=  array($this->translate->_('Code'),$this->translate->_('Description'),$this->translate->_('Description ('.$this->sec_lang.')'));
	
	
	// prepare the configuration for grid
	$pagingparams = array(
			"show_grid_heading" => true,
			"grid_heading_message" => $this->translate->_('Overview'),
			"show_records_per_page" => isset($formdata["show_records_per_page"])?$formdata["show_records_per_page"]:10,
			"show_searchbox" => true,
			"searchbox_value" => isset($formdata["grid_search_text"])?$formdata["grid_search_text"]:"",
			"pagename" => $this->translate->_('Good Return'),
			"show_selectbox" => true,
			"selected_list" => $checked,
			"show_editlink" => true,
			"show_deletelink" => false,
			"show_deleteall" => false,
			"primaryid" => "code",
			 "editlink" => array("/basic/reasons/addgoodreturn/id/#pattern#/edit/yes/","#pattern#"),
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
	$result = $this->SFA_Comman->executequery('CALL sp_get_basic_reason_goodreturn(?,?,?,?,?,?,?,?)',$param_array,'',$downloadCSV,$printData,2,$pagingparams);
	$data_arr["count"] 		= $result[0][0]['counter'];
	$data_arr["data"][0] 	= $result[1];
	
	
	// pass the data in summary_showdatagrid() function & create a final variable for view
	$this->view->pagegridshow =  $pagingshow->summary_showdatagrid($data_arr);
	
	$this->view->addScriptPath(APPLICATION_PATH."/theme/default/datagrid/");
    }
    /**
    * @name       addgoodreturnAction
    * @since      30-11-2011
    * @version    Release: 1
    * @author     HD <hiren.d@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This action is for add bad reason
    *
    */
    public function addgoodreturnAction()
    {

	$this->view->params = $params = $this->getRequest()->getParams();
	$this->view->formdata = $formdata = $this->_request->getPost();
	
	
	$this->view->missonefld	= $this->translate->_('Missed One Field');
	$this->view->youmissed	= $this->translate->_('You Missed');
	$this->view->highlated	= $this->translate->_('Fields. They have been highlighted.');

	if($formdata['txtcode'] && $formdata['txtdesc'])
	{
	    if($formdata['hdnid'] > 0)
	    {   
		    $param_array = array();		    
		    $param_array[1] = trim($formdata['txtdesc']);	//Description
		    $param_array[2] = trim($formdata['txtdesc_arb']);	//ArbDescription
		    $param_array[3] = $formdata['hdnid'];		//Code
		    $param_array[4] = $this->currentUser->username;		//modified
		    $param_array[5] = $formdata['txtaltcode'];			//alternatecode
		    $last_id = $this->SFA_Comman->executequery('CALL sp_edit_basic_reason_addgoodreturn(?,?,?,?,?)',$param_array,'');
		    
		    SFA_Message::setMsg($this->translate->_('Update Record'));		
	    }
	    else{
		    $param_array = array();
		    $param_array[1] = trim($formdata['txtdesc']);		//Description
		    $param_array[2] = trim($formdata['txtdesc_arb']);	//ArbDescription		    
		    $param_array[3] = $this->currentUser->username;		//created
		    $param_array[4] = $formdata['txtaltcode'];			//alternatecode
		    
		    $last_id = $this->SFA_Comman->executequery('CALL sp_add_basic_reason_addgoodreturn(?,?,?,?)',$param_array,'');				
		    
		    SFA_Message::setMsg($this->translate->_('New Record'));
	    }
	    $this->_helper->redirector('goodreturn', 'reasons', 'basic');
	}
	elseif($params['id'] > 0)
	{
	    $result  		= $this->SFA_Comman->executequery('CALL sp_get_basic_reason_addgoodreturn(?)',$params['id'],'');
	    $res['txtcode'] 	= $result[0][0]['code'];
	    $res['txtdesc'] 	= $result[0][0]['description'];
	    $res['txtdesc_arb'] = $result[0][0]['arbdescription'];
	    $res['createddate'] = date('d-m-Y',strtotime($result[0][0]['cdat']));
	    $res['txtaltcode']	= $result[0][0]['alternatecode'];
	    $this->view->formdata = $res;
	}
	else
	{
	    $table_name = 'retitmreasons';
	    $code = $this->SFA_Comman->executequery('CALL sp_get_table_last_id(?)',$table_name,'');
	    $this->view->formdata['txtcode'] = ($code[0][0]['Auto_increment'] == '') ? '1' : $code[0][0]['Auto_increment'];
	}
    }   
    /**
    * @name       foxreasonAction
    * @since      12-12-2011
    * @version    Release: 1
    * @author     HD <hiren.d@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This action is for display foc reason
    *
    */
    public function focreasonAction()
    {
	$this->view->params = $params = $this->getRequest()->getParams();
	$this->view->formdata = $formdata = $this->_request->getPost();
	
	$this->view->title 	= $this->translate->_('Free');
	
	if($formdata["hdDelete"]==1)
	{
	    $ids = implode(',',$formdata['chk']);
	    $param_array 	= array();
	    $param_array[1]	= $ids;
	    $param_array[2]	= $this->currentUser->username;
	    
	    $result 	= $this->SFA_Comman->executequery('CALL sp_delete_basic_reason_focreason(?,?)',$param_array,'');
	    
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
	
	$cols_array 	= array('reason_code','reason_desc','reason_arb_desc');
	$columns_show 	=  array($this->translate->_('Code'),$this->translate->_('Description'),$this->translate->_('Description ('.$this->sec_lang.')'));
	

	// prepare the configuration for grid
	$pagingparams = array(
			"show_grid_heading" => true,
			"grid_heading_message" => $this->translate->_('Overview'),
			"show_records_per_page" => isset($formdata["show_records_per_page"])?$formdata["show_records_per_page"]:10,
			"show_searchbox" => true,
			"searchbox_value" => isset($formdata["grid_search_text"])?$formdata["grid_search_text"]:"",
			"pagename" => $this->translate->_('Free'),
			"show_selectbox" => true,
			"selected_list" => $checked,
			"show_editlink" => true,
			"show_deletelink" => false,
			"show_deleteall" => false,
			"primaryid" => "reason_code",
			"editlink" => array("/basic/reasons/addfocreason/id/#pattern#/edit/yes/","#pattern#"),
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
	$result = $this->SFA_Comman->executequery('CALL sp_get_basic_reason_focreason(?,?,?,?,?,?,?,?)',$param_array,'',$downloadCSV,$printData,2,$pagingparams);
	
	$data_arr["count"] 		= $result[0][0]['counter'];
	$data_arr["data"][0] 	= $result[1];
	
	
	// pass the data in summary_showdatagrid() function & create a final variable for view
	$this->view->pagegridshow =  $pagingshow->summary_showdatagrid($data_arr);
	
	$this->view->addScriptPath(APPLICATION_PATH."/theme/default/datagrid/");
    }
     /**
    * @name       addfocreturn Action
    * @since      30-11-2011
    * @version    Release: 1
    * @author     HD <hiren.d@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This action is for add foc
    *
    */
    public function addfocreasonAction()
    {
		$this->view->params = $params = $this->getRequest()->getParams();
		$this->view->formdata = $formdata = $this->_request->getPost();
	
		
		
		$this->view->missonefld	= $this->translate->_('Missed One Field');
		$this->view->youmissed	= $this->translate->_('You Missed');
		$this->view->highlated	= $this->translate->_('Fields. They have been highlighted.');
	
		if($formdata['txtcode'] && $formdata['txtdesc'])
		{
			if($formdata['hdnid'] > 0)
			{		
				$param_array = array();
				$param_array[1] = $formdata['txtaltcode'];		//Alternatecode
				$param_array[2] = trim($formdata['txtdesc']);	//Description
				$param_array[3] = trim($formdata['txtdesc_arb']);	//ArbDescription
				$param_array[4] = $formdata['hdnid'];		//Code
				$param_array[5] = $this->currentUser->username;		//modified
				
				$last_id = $this->SFA_Comman->executequery('CALL sp_edit_basic_reason_addfocreason(?,?,?,?,?)',$param_array,'');
				
				SFA_Message::setMsg($this->translate->_('Update Record'));
			
			}
			else{
				$param_array = array();
				$param_array[1] = $formdata['txtaltcode'];			//Alternatecode
				$param_array[2] = trim($formdata['txtdesc']);		//Description
				$param_array[3] = trim($formdata['txtdesc_arb']);	//ArbDescription
				$param_array[4] = $this->currentUser->username;		//created
				
				$last_id = $this->SFA_Comman->executequery('CALL sp_add_basic_reason_addfocreason(?,?,?,?)',$param_array,'');
			
				SFA_Message::setMsg($this->translate->_('New Record'));
			}
			$this->_helper->redirector('focreason', 'reasons', 'basic');
		}
		elseif($params['id'] > 0)
		{
			$result  		= $this->SFA_Comman->executequery('CALL sp_get_basic_reason_addfocreason(?)',$params['id'],'');
			$res['txtcode'] 	= $result[0][0]['reason_code'];
			$res['txtdesc'] 	= $result[0][0]['reason_desc'];
			$res['txtdesc_arb'] = $result[0][0]['reason_arb_desc'];
			$res['createddate'] = date('d-m-Y',strtotime($result[0][0]['cdat']));
			$res['txtaltcode']	= $result[0][0]['alternatereasoncode'];
			$this->view->formdata = $res;
		}
		else
		{
			$table_name = 'freegoodreasons';
			$code = $this->SFA_Comman->executequery('CALL sp_get_table_last_id(?)',$table_name,'');	    
			$this->view->formdata['txtcode'] = ($code[0][0]['Auto_increment'] == '') ? '1' : $code[0][0]['Auto_increment'];
		}
    }
	/**
    * @name       expensereasonAction
    * @since      17/02/2012
    * @version    Release: 1
    * @author     HD <hiren.d@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This action is for display non service reason
    *
    */
    public function expensereasonAction()
    {
		$this->view->params = $params = $this->getRequest()->getParams();
		$this->view->formdata = $formdata = $this->_request->getPost();
		
		if($formdata["hdDelete"]==1)
		{
			$ids = implode(',',$formdata['chk']);
			$param_array 	= array();
			$param_array[1]	= $ids;
			$param_array[2]	= $this->currentUser->username;
			
			$result 	= $this->SFA_Comman->executequery('CALL sp_delete_basic_reason_expensereason(?,?)',$param_array,'');
			
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
	
		$cols_array 	= array('code','description');
		$columns_show 	=  array($this->translate->_('Code'),$this->translate->_('Expenses Description'));
		
		if($this->css == 'ar_') {
			$cols_array[1]	= 'arbdescription';
		}
		
		$this->view->title 	= $this->translate->_('Expenses');
	
		// prepare the configuration for grid
		$pagingparams = array(
				"show_grid_heading" => true,
				"grid_heading_message" => $this->translate->_('Overview'),
				"show_records_per_page" => isset($formdata["show_records_per_page"])?$formdata["show_records_per_page"]:10,
				"show_searchbox" => true,
				"searchbox_value" => isset($formdata["grid_search_text"])?$formdata["grid_search_text"]:"",
				"pagename" => $this->translate->_('Expenses'),
				"show_selectbox" => true,
				"selected_list" => $checked,
				"show_editlink" => true,
				"show_deletelink" => false,
				"show_deleteall" => false,
				"primaryid" => "code",
				"editlink" => array("/basic/reasons/addexpensereason/id/#pattern#/edit/yes/","#pattern#"),
				"nodata_message" => "No Record(s) Found.",
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
		$result	= $this->SFA_Comman->executequery('CALL sp_get_basic_reason_expensereason(?,?,?,?,?,?,?,?)',$param_array,'',$downloadCSV,$printData,2,$pagingparams);
	
		$data_arr["count"] 	= $result[0][0]['counter'];       
		$data_arr["data"][0] = $result[1];		
		
		// pass the data in summary_showdatagrid() function & create a final variable for view
		$this->view->pagegridshow =  $pagingshow->summary_showdatagrid($data_arr);
		
		$this->view->addScriptPath(APPLICATION_PATH."/theme/default/datagrid/");
    }
    /**
    * @name       addexpensereasonAction
    * @since      17/02/2012
    * @version    Release: 1
    * @author     HD <hiren.d@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This action is for add expensereason
    *
    */
    public function addexpensereasonAction()
    {

	$this->view->params 	= $params = $this->getRequest()->getParams();
        $this->view->formdata 	= $formdata = $this->_request->getPost();	
		

	if($formdata['txtcode']!='')
	{
	    $param_array 	= array();
	    $param_array[1] 	= $formdata['txtdesc'];
	    $param_array[2] 	= $formdata['txtdesc_arb'];
	    $param_array[3] 	= $formdata['txtaltcode'];
	    $param_array[4] 	= $this->currentUser->username;
	    
	    
	    if($formdata['hdnid'] > 0)
	    {			
		$param_array[5] = $formdata['hdnid'];
		$result = $this->SFA_Comman->executequery('CALL sp_edit_basic_reason_addexpensesreasons(?,?,?,?,?)',$param_array,'');
	
		SFA_Message::setMsg($this->translate->_('Update Record'));
	    }
	    else
	    {
		$result = $this->SFA_Comman->executequery('CALL sp_add_basic_reason_addexpensesreasons(?,?,?,?)',$param_array,'');
		SFA_Message::setMsg($this->translate->_('New Record'));
	    }
	    if($formdata["returnUrl"]!="")
		$this->_redirect($formdata["returnUrl"]);
	    else
		$this->_helper->redirector('expensereason', 'reasons', 'basic');
	}
	elseif($params['id'] > 0)
	{
	    $result = $this->SFA_Comman->executequery('CALL sp_get_basic_reason_addexpensereason(?)',$params['id'],'');
	    $res['txtcode'] 	= $result[0][0]['code'];
	    $res['txtdesc'] 	= $result[0][0]['description'];
	    $res['txtdesc_arb'] = $result[0][0]['arbdescription'];
	    $res['createddate'] = date('d-m-Y',strtotime($result[0][0]['cdat']));
	    $this->view->formdata = $res;
	}
	else
	{
	    $last_id = $this->SFA_Comman->executequery('CALL sp_get_table_last_id(?)','expreasons','');
	    $this->view->formdata['txtcode'] = ($last_id[0][0]['Auto_increment'] == '') ? '1' : $last_id[0][0]['Auto_increment'];
	}
    }
    
     /**
    * @name       nonserviceAction
    * @since      17/02/2012
    * @version    Release: 1
    * @author     HD <hiren.d@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This action is for display non service reason
    *
    */
    public function nonserviceAction()
    {
		$this->view->params = $params = $this->getRequest()->getParams();
		$this->view->formdata = $formdata = $this->_request->getPost();
	
		$cols_array 	= array('code','description');
		$columns_show 	=  array($this->translate->_('Code'),$this->translate->_('Description'));
		
		if($this->css == 'ar_') {
			$cols_array[1]	= 'arbdescription';
		}
		
		
		if($formdata["hdDelete"]==1)
		{
			$ids = implode(',',$formdata['chk']);
			$param_array 	= array();
			$param_array[1]	= $ids;
			$param_array[2]	= $this->currentUser->username;
			
			$result 	= $this->SFA_Comman->executequery('CALL sp_delete_basic_reason_nonservice(?,?)',$param_array,'');
			
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
		
		$this->view->title 	= $this->translate->_('Non Serviced');
	
		// prepare the configuration for grid
		$pagingparams = array(
				"show_grid_heading" => true,
				"grid_heading_message" => $this->translate->_('Overview'),
				"show_records_per_page" => isset($formdata["show_records_per_page"])?$formdata["show_records_per_page"]:10,
				"show_searchbox" => true,
				"searchbox_value" => isset($formdata["grid_search_text"])?$formdata["grid_search_text"]:"",
				"pagename" => $this->translate->_('Non Serviced'),
				"show_selectbox" => true,
				"selected_list" => $checked,
				"show_editlink" => true,
				"show_deletelink" => false,
				"show_deleteall" => false,
				"primaryid" => "code",
				"editlink" => array("/basic/reasons/addnonservice/id/#pattern#/edit/yes/","#pattern#"),
				"nodata_message" => "No Record(s) Found.",
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
		$result	= $this->SFA_Comman->executequery('CALL sp_get_basic_reason_nonservreasons(?,?,?,?,?,?,?,?)',$param_array,'',$downloadCSV,$printData,2,$pagingparams);
	
		$data_arr["count"] 	= $result[0][0]['counter'];       
		$data_arr["data"][0] 	= $result[1];
		
		// pass the data in summary_showdatagrid() function & create a final variable for view
		$this->view->pagegridshow =  $pagingshow->summary_showdatagrid($data_arr);
		
		$this->view->addScriptPath(APPLICATION_PATH."/theme/default/datagrid/");
    }
    /**
    * @name       addnonserviceAction
    * @since      17/02/2012
    * @version    Release: 1
    * @author     HD <hiren.d@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This action is for add nonservice
    *
    */
    public function addnonserviceAction()
    {

	$this->view->params 	= $params = $this->getRequest()->getParams();
	$this->view->formdata 	= $formdata = $this->_request->getPost();
	$this->view->css 		= $this->translate->_('CSS');
	
	$this->view->returnUrl = $_SERVER["HTTP_REFERER"];	
	
	if($formdata['txtcode']!='')
	{
	    $param_array 	= array();
	    $param_array[1] 	= $formdata['txtdesc'];
	    $param_array[2] 	= $formdata['txtdesc_arb'];
	    $param_array[3] 	= $formdata['txtaltcode'];
	    $param_array[4] 	= $this->currentUser->username;
	    
	    if($formdata['hdnid'] > 0){
	    
		$param_array[5] = $formdata['hdnid'];
		$result = $this->SFA_Comman->executequery('CALL sp_edit_basic_reason_addnonservreasons(?,?,?)',$param_array,'');
		
		SFA_Message::setMsg($this->translate->_('Update Record'));
	    }
	    else{
		$result = $this->SFA_Comman->executequery('CALL sp_add_basic_reason_addnonservreasons(?,?)',$param_array,'');
		SFA_Message::setMsg($this->translate->_('New Record'));	
	    }
	    if($formdata["returnUrl"]!="")
		$this->_redirect($formdata["returnUrl"]);
	    else
		$this->_helper->redirector('nonservice', 'reasons', 'basic');
	}
	elseif($params['id'] > 0)
	{
	    $result = $this->SFA_Comman->executequery('CALL sp_get_basic_reason_addnonservreasons(?)',$params['id'],'');
	    $res['txtcode'] 	= $result[0][0]['code'];
	    $res['txtdesc'] 	= $result[0][0]['description'];
	    $res['txtdesc_arb'] = $result[0][0]['arbdescription'];
	    $res['txtaltcode'] 	= $result[0][0]['alternatecode'];
	    $res['createddate'] = date('d-m-Y',strtotime($result[0][0]['cdat']));
	    $this->view->formdata = $res;
	}
	else
	{
	    $last_id = $this->SFA_Comman->executequery('CALL sp_get_basic_reason_addnonservreasons(?)','0','');
	    $this->view->formdata['txtcode'] = ($last_id[0][0]['Auto_increment'] == '') ? '1' : $last_id[0][0]['Auto_increment'];	    
	}
	
    }
    
    /**
    * @name       voidreasonAction
    * @since      17/02/2012
    * @version    Release: 1
    * @author     HD <hiren.d@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This action is for display non service reason
    *
    */
    public function voidreasonAction()
    {
		$this->view->params = $params = $this->getRequest()->getParams();
		$this->view->formdata = $formdata = $this->_request->getPost();
	
		$cols_array 	= array('code','description');
		$columns_show 	=  array($this->translate->_('Code'),$this->translate->_('Void Description'));
		
		if($this->css == 'ar_') {
			$cols_array[1]	= 'arbdescription';
		}
		
		if($formdata["hdDelete"]==1)
		{
			$ids = implode(',',$formdata['chk']);
			$param_array 	= array();
			$param_array[1]	= $ids;
			$param_array[2]	= $this->currentUser->username;
			
			$result 	= $this->SFA_Comman->executequery('CALL sp_delete_basic_reason_voidreason(?,?)',$param_array,'');
			
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
		$this->view->title 	= $this->translate->_('Void/Cancel');
		// prepare the configuration for grid
		$pagingparams = array(
				"show_grid_heading" => true,
				"grid_heading_message" => $this->translate->_('Overview'),
				"show_records_per_page" => isset($formdata["show_records_per_page"])?$formdata["show_records_per_page"]:10,
				"pagename" => $this->translate->_('Void/Cancel'),
				"show_searchbox" => true,
				"searchbox_value" => isset($formdata["grid_search_text"])?$formdata["grid_search_text"]:"",
				"show_selectbox" => true,
				"selected_list" => $checked,
				"show_editlink" => true,
				"show_deletelink" => false,
				"show_deleteall" => false,
				"primaryid" => "code",
				"editlink" => array("/basic/reasons/addvoidreason/id/#pattern#/edit/yes/","#pattern#"),
				"nodata_message" => "No Record(s) Found.",
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
		$result	= $this->SFA_Comman->executequery('CALL sp_get_basic_reason_voidreasons(?,?,?,?,?,?,?,?)',$param_array,'',$downloadCSV,$printData,2,$pagingparams);
	
		$data_arr["count"] 	= $result[0][0]['counter'];       
		$data_arr["data"][0] = $result[1];
		
		// pass the data in summary_showdatagrid() function & create a final variable for view
		$this->view->pagegridshow =  $pagingshow->summary_showdatagrid($data_arr);
		
		$this->view->addScriptPath(APPLICATION_PATH."/theme/default/datagrid/");
    }
    /**
    * @name       addvoidreasonAction
    * @since      17/02/2012
    * @version    Release: 1
    * @author     HD <hiren.d@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This action is for add voidreason
    *
    */
    public function addvoidreasonAction()
    {
        $this->view->params 	= $params = $this->getRequest()->getParams();
        $this->view->formdata 	= $formdata = $this->_request->getPost();		
        
        
        $this->view->returnUrl = $_SERVER["HTTP_REFERER"];	
    
        if($formdata['txtcode']!='')
        {
            $param_array 		= array();
            $param_array[1] 	= $formdata['txtdesc'];
            $param_array[2] 	= $formdata['txtdesc_arb'];
            $param_array[3] 	= $formdata['txtaltcode'];
            $param_array[4] 	= $this->currentUser->username;
            
            if($formdata['hdnid'] > 0)
            {
                $param_array[5] = $formdata['hdnid'];
                $result = $this->SFA_Comman->executequery('CALL sp_edit_basic_reason_addvoidreasons(?,?,?,?,?)',$param_array,'');
            
                SFA_Message::setMsg($this->translate->_('Update Record'));
            }
            else
            {
                $result = $this->SFA_Comman->executequery('CALL sp_add_basic_reason_addvoidreasons(?,?,?,?)',$param_array,'');
                SFA_Message::setMsg($this->translate->_('New Record'));
            }
            if($formdata["returnUrl"]!="")
                $this->_redirect($formdata["returnUrl"]);
            else
                $this->_helper->redirector('voidreason', 'reasons', 'basic');
        }
        elseif($params['id'] > 0)
        {
            $result = $this->SFA_Comman->executequery('CALL sp_get_basic_reason_addvoidreasons(?)',$params['id'],'');
            $res['txtcode'] 	= $result[0][0]['code'];
            $res['txtdesc'] 	= $result[0][0]['description'];
            $res['txtdesc_arb'] = $result[0][0]['arbdescription'];
            $res['txtaltcode'] 	= $result[0][0]['alternatecode'];
            $res['createddate'] = date('d-m-Y',strtotime($result[0][0]['cdat']));
            $this->view->formdata = $res;
        }
        else
        {
            $last_id = $this->SFA_Comman->executequery('CALL sp_get_basic_reason_addvoidreasons(?)','0','');
            $this->view->formdata['txtcode'] = ($last_id[0][0]['Auto_increment'] == '') ? '1' : $last_id[0][0]['Auto_increment'];			
        }
    }
    
    
    
   
}