<?php
/**
* @name       advanceController
* @since
* @version    Release: 1
* @author     HD <hiren.d@elantechnologies.com>
* @copyright  Elan Technologies
* @param
*
* This controller is Promotions module.
*/
class Promotions_AdvanceController extends Promotions_Library_Controller_Action_Abstract
{
    /**
    * @name       init
    * @since      30-01-2012
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
	$this->view->colan	= $this->translate->_('Colan');

	$this->common_model	= new SFA_Model_Index();
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
    * @name       promotionplanAction
    * @since      30-01-2012
    * @version    Release: 1
    * @author     HD <hiren.d@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This action is use for the display promotion plan
    */
    public function promotionplanAction()
    {
	$this->view->params = $params = $this->getRequest()->getParams();
        $this->view->formdata = $formdata = $this->_request->getPost();

	if($formdata["hdDelete"]==1)
	{
	    $ids = implode(',',$formdata['chk']);
	    $param_array 	= array();
	    $param_array[1]	= $ids;
	    $param_array[2]	= $this->currentUser->username;
	    
	    $result 	= $this->SFA_Comman->executequery('CALL sp_delete_promotions_advance_addpromotionplan(?,?)',$param_array,'');
	    
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

	$plan_num		= $this->translate->_('Plan Number');
	$desc			= $this->translate->_('Description');
	$arb_desc		= $this->translate->_('Description ('.$this->sec_lang.')');
	$isactive		= $this->translate->_('Status');

	$columns_array = array('plannumber','plandescription','arbplandescription','activeindicator');
	$columns_show  = array($plan_num,$desc,$arb_desc,$isactive);
	
	$pagingparams = array(
			     "show_grid_heading" => true,
			     "grid_heading_message" => $this->translate->_('Overview'),
				 "pagename" => $this->translate->_('Promotion Plan'),
			     "show_records_per_page" => isset($formdata["show_records_per_page"])?$formdata["show_records_per_page"]:10,
			     "show_searchbox" => true,
			     "selected_list" => $checked,
			     "searchbox_value" => isset($formdata["grid_search_text"])?$formdata["grid_search_text"]:"",
			     "show_selectbox" => true,
			     "show_editlink" => true,
			     "show_deletelink" => false,
			     "deletelink" => array("/promotions/advance/promotionplan/id/#pattern#/delete/yes/","#pattern#"),
			     "show_deleteall" => false,
			     "primaryid" => "plannumber",
			     "status_cols" => array(
					      array(
						  "cols_name" => "activeindicator",
						  "status_change" => array("0"=>"Inactive","1"=>"Active")
						  )
					      ),
			     "editlink" => array("/promotions/advance/addpromotionplan/edit/yes/id/#pattern#","#pattern#"),
			     "fetch_columns_inquery" => $columns_array,
			     "show_columns" => $columns_show,
			     "nodata_message" => $this->translate->_('No Record(s) Found')
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
	$rowcount = $this->SFA_Comman->executequery('CALL sp_get_promotions_advance_promotionplan(?,?,?,?,?,?,?,?)',$param_array,'',$downloadCSV,$printData,2,$pagingparams);
	
	$data_arr["count"] 		= $rowcount[0][0]['counter'];
	$data_arr["data"][0] 	= $rowcount[1];
	
	// pass the data in summary_showdatagrid() function & create a final variable for view
	$this->view->pagegridshow =  $pagingshow->summary_showdatagrid($data_arr);
	
	$this->view->addScriptPath(APPLICATION_PATH."/theme/default/datagrid/");
    }
    /**
    * @name       addpromotionplanAction
    * @since      26-01-2012
    * @version    Release: 1
    * @author     HD <hiren.d@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This action is use for the add promotion plan
    */
    public function addpromotionplanAction()
    {
		$this->view->params = $params = $this->getRequest()->getParams();
		$this->view->formdata = $formdata = $this->_request->getPost();
	
		$this->view->returnUrl = $_SERVER["HTTP_REFERER"];
		
		$this->view->css = $this->translate->_('CSS');
	
		if($formdata['txt_plandescription'])
		{
			$param_array = array();		
			
			$param_array[1] = $formdata['txt_plandescription'];
			$param_array[2] = $formdata['txt_arbplandescription'];
			$param_array[3] = $formdata['ddl_promotiontypecode'];
			$param_array[4] = $formdata['ddl_activeindicator'];
			$param_array[5] = $formdata['ddl_rangebasis'];
			$param_array[6] = $formdata['ddl_amountbasis'];
			$param_array[7] = $formdata['ddl_exclusionoption']!=""?$formdata['ddl_exclusionoption']:0; //$formdata['ddl_exclusionoption'];
			$param_array[8] = $formdata['ddl_qualificationgroup'];
			$param_array[9] = $formdata['ddl_assignmentgroup'];
			$param_array[10] = $formdata['txt_assignmentnumber']!=""?$formdata['txt_assignmentnumber']:0; //$formdata['txt_assignmentnumber'];
			$param_array[11] = $formdata['chk_iscase']!=""?$formdata['chk_iscase']:0;
			$param_array[12] = $formdata['chk_onetimeuse']!=""?$formdata['chk_onetimeuse']:0;
			$param_array[13] = $formdata['chk_enforcepromotion']!=""?$formdata['chk_enforcepromotion']:0;
			$param_array[14] = $formdata['chk_repeatrange']!=""?$formdata['chk_repeatrange']:0; //$formdata['chk_repeatrange'];
			$param_array[15] = $this->currentUser->username;
		  
			if($formdata['hdnid'] > 0) {
				$param_array[16] = $formdata['txt_plannumber'];
				$result = $this->SFA_Comman->executequery('CALL sp_edit_promotions_advance_addpromotionplan',$param_array,'');		
				SFA_Message::setMsg($this->translate->_('Update Record'));
				$last_id = $formdata["txt_plannumber"];
			} else {
				$result = $this->SFA_Comman->executequery('CALL sp_add_promotions_advance_addpromotionplan',$param_array,'');
				SFA_Message::setMsg($this->translate->_('New Record'));
				$last_id = $result[0][0]['insid'];
			}
			$this->_helper->redirector('promotionplan', 'advance', 'promotions');
		}
		else
		{
			// promotion_type
			$promotion_type[] = array("id"=>"1","val"=>"1 - Amount On Item");
			$promotion_type[] = array("id"=>"2","val"=>"2 - Percentage On Item");
			$promotion_type[] = array("id"=>"0","val"=>"3 - Net Price/ Basket Price");
			//$promotion_type[] = array("id"=>"4","val"=>"Amount Off Invoice");
			$promotion_type[] = array("id"=>"5","val"=>"5 - Amount On Invoice");
			$promotion_type[] = array("id"=>"6","val"=>"6 - Percentage On Invoice");
			$promotion_type[] = array("id"=>"7","val"=>"7 - Free");
			
			$this->view->promotion_type = $promotion_type;
			
			$Settings_NameSpace = new Zend_Session_Namespace('Settings');
			// range_basis
			$range_basis[] = array("id"=>"0","val"=>"No Qualification (Default)");
			$range_basis[] = array("id"=>"1","val"=>"Qualification On Quantity");
			$range_basis[] = array("id"=>"2","val"=>"Qualification On Amount");
			if($Settings_NameSpace->cpanel['Fixed Qualification/Fixed Assignment']['status']) {
				$this->view->fixedqualification = 1;
			}
			if($Settings_NameSpace->cpanel['Ranged Qualification on Fixed Assignment']['status'])
			{
				$this->view->rangedqualification = 1;				
			}
			
			$this->view->range_basis = $range_basis;
			
			
			// amount_basis
			$amount_basis[] = array("id"=>"0","val"=>"Not Applicable (Default)");
			$amount_basis[] = array("id"=>"1","val"=>"Wholesale Price");
			$amount_basis[] = array("id"=>"2","val"=>"Current Net Price");
			
			$this->view->amount_basis = $amount_basis;
			
			// exclusion_option
			$exclusion_option[] = array("id"=>"0","val"=>"Not Applicable (Default)");
			$exclusion_option[] = array("id"=>"1","val"=>"Exclude Item in Assignment Group From Further Promotion");
			
			$this->view->exclusion_option = $exclusion_option;	
			if($params['id'] > 0)
			{
				$result  		= $this->SFA_Comman->executequery('CALL sp_get_promotions_advance_addpromotionplan(?)',$params['id'],'');
				$this->view->formdata 	= $result[0][0];
				$this->view->assignment_group = $result[1];
				$this->view->qualification_group = $result[2];		
				
				$key2 = $result[0][0]["assignmentnumber"]!=""?$result[0][0]["assignmentnumber"]:0;
			}
			else
			{
				$result  = $this->SFA_Comman->executequery('CALL sp_get_promotions_advance_addpromotionplan(?)','0','');
				$this->view->formdata['plannumber'] 	= $result[0][0]["Auto_increment"];
				$this->view->assignment_group 			= $result[1];
				$this->view->qualification_group 		= $result[2];
				$params['id']  	= 0;
				$key2 			= 0;
			}
		}
		$this->view->itemgrid   = $this->view->BaseUrl("/promotions/advance/promotionplanitemgrid/key/".$params['id']."/key2/".$key2);
    }
    
    /**
    * @name       promotionplanitemgrid
    * @since      14-04-2012
    * @version    Release: 2
    * @author     M@M <miral@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This action is use for discount item grid
    */
    public function promotionplanitemgridAction()
    {
		$this->view->params = $params = $this->getRequest()->getParams();
		$this->view->formdata = $formdata = $this->_request->getPost();
		
		
		if($params['hdnassignmentgrp'] > '0') {
			
			$columns_array 	=  array('rangelow','rangehigh','repeatingrange','FORMAT(promotionamount,'.$this->decimalplaces.') as promotionamount','range_id as edit_del_primary_id');
			$columns_show  	= array($this->translate->_('Range Low'),$this->translate->_('Range High'),$this->translate->_('Range Repeat'),$this->translate->_('Promo Value'));	    	    
		}
		else {
			$columns_array 	=  array('rangelow','rangehigh','repeatingrange','CONCAT(ph.groupnumber," -- ",groupdescription) AS groupdescription','range_id as edit_del_primary_id');
			$columns_show  	= array($this->translate->_('Range Low'),$this->translate->_('Range High'),$this->translate->_('Range Repeat'),$this->translate->_('Assignment'));	    
		}
		
		
		if($params['hdnassignmentgrp'] > '0')
		{
			$outlet_dd[0] = '0 - No';
			$outlet_dd[1] = '1 - Yes';
			
			$key_ms = "repeatingrange";
			$value_ms = $outlet_dd;
			$mastervalues = array($key_ms=>$value_ms);
		}
		else
		{
			$resultot = $this->SFA_Comman->executequery('CALL sp_combo_assignmentgroup()',"",'');	
			foreach($resultot[0] as $e_resultot)
			{
			$outlet_dd[$e_resultot['id']] = $e_resultot['val'];
			}
			
			// final array format for Assignment combo in edit mode
			$key_ms = "groupdescription";
			$value_ms = $outlet_dd;
			
			$outlet_dd1[0] = '0 - No';
			$outlet_dd1[1] = '1 - Yes';
			
			$key_ms1 = "repeatingrange";
			$value_ms1 = $outlet_dd1;
			
			
			$mastervalues = array($key_ms=>$value_ms,$key_ms1=>$value_ms1);	    
		}
		//SFA_Comman::pre($params);
		
		
		// ADDING THE RECORD
		if($params["hdnadd"]=="yes")
		{
			$paramarry 	  = array();
			$paramarry[1] = $params['txt_rangelow'];
			$paramarry[2] = $params['txt_rangehigh'];
			$paramarry[3] = $params['ddl_repeatingrange'];
			$paramarry[4] = $params['txt_promotionamount'];
			$paramarry[5] = $params['key']?$params['key']:0;
			$paramarry[6] = $params['key2']?$params['key']:0;
			$paramarry[7] = $this->currentUser->username;
			//print_r($paramarry);exit;
			if($params['hdnassignmentgrp'] == 0) {
				$paramarry[4] = $params['ddl_assignmentgrp'];
			}
			$r_add = $this->SFA_Comman->executequery('CALL sp_add_promotions_advance_promotionplanitemgrid(?,?,?,?,?,?)',$paramarry,'');
			
			if($r_add[0][0]['result'] == '0')
			{
				$range_high = $r_add[0][0]['final_range_val'];
				SFA_Message::setErrorMsg($this->translate->_('Range Low value must be greater than the '.$range_high.'.'));
				echo '<script type="text/javascript">$("#hdnrange_high").val(0);</script>';
			}
			else
			{
				echo '<script type="text/javascript">$("#hdnrange_high").val(1);</script>';
				SFA_Message::setMsg($this->translate->_('New Record'));
			}
		}
		// DELETE THE RECORD
		if($params["delete"]=="yes") {
			// sp for delete
			$r_delete = $this->SFA_Comman->executequery('CALL sp_delete_promotions_advance_promotionplanitemgrid(?)',array(1=>$params["id"]),'');
			SFA_Message::setMsg($this->translate->_('Delete Record'));
		}
		
		// UPDATE THE RECORD
		if($params["update"]=="yes")
		{
			$updateData["1"] = $params["rangelow"];
			$updateData["2"] = $params["rangehigh"];
			$updateData["3"] = $params["repeatingrange"];
			$updateData["4"] = $params["promotionamount"];
			$updateData["5"] = $params["id"];
			$updateData["6"] = $params["key"];
			$updateData["7"] = $params['hdnassignmentgrp'];
			$updateData["8"] = $this->currentUser->username;
			
			if($params['hdnassignmentgrp'] == '0') {
			$updateData["4"] = $params['groupdescription'];
			}
			//SFA_Comman::pre($updateData);
			if($params["repeatingrange"] == '0' && $params["rangehigh"] < $params["rangelow"])
			{
			SFA_Message::setErrorMsg($this->translate->_('Range High Must Be Greater Than Range Low.'));
			echo '<script type="text/javascript">$("#hdnrange_high").val(0); return false;</script>';
			}
			else
			{
			//SFA_Comman::pre($updateData);
			// call sp for edit currencydetail
			$r_edit = $this->SFA_Comman->executequery('CALL sp_edit_promotions_advance_promotionplanitemgrid(?,?,?,?,?,?,?)',$updateData,'');
			
			if($r_edit[0][0]['result'] == '0')
			{
				$range_high = $r_edit[0][0]['final_range_val'];
				SFA_Message::setErrorMsg($this->translate->_('Range Low value must be greater than the '.$range_high.'.'));
				echo '<script type="text/javascript">$("#hdnrange_high").val(0);</script>';
			}
			else
			{
				echo '<script type="text/javascript">$("#hdnrange_high").val(1);</script>';
				SFA_Message::setMsg($this->translate->_('Update Record'));
			}
			}
			
			
			
		}
	
		// IF EXTRA PARAMS ARE REQUIRED
		$ex_param = "";
		if(isset($params["key"]) && $params["key"]>0){
			$additional_where_condition = array();
			$ex_param = "/key/".$params["key"];
			//$additional_where_condition[] = ' (plannumber = "'.$params["key"].'" AND assignmentnumber = "'. $params["key2"] . '" )';
			$additional_where_condition[] = ' (plannumber = "'.$params["key"].'")';
		}
	
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
					// "noeditfields" =>array("rangelow","rangehigh","repeatingrange"),
					 "primaryid" => "range_id",
					 "currentlink" => array("/promotions/advance/promotionplanitemgrid".$ex_param),
					 "deletelink" => array("/id/#pattern#/delete/yes","#pattern#"),
					 "editlink" => array("/id/#pattern#/edit/yes","#pattern#"),
					 "nodata_message" => $this->translate->_('No Record(s) Found'),
					 "fetch_columns_inquery" => $columns_array,
					 "show_columns" => $columns_show,
					 "additional_where" => $additional_where_condition
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
			$pagingparams["editmodevalue"] = "range_id";  // put table's prymary key here
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
		$param_array[8] = $params['hdnassignmentgrp'];
	
		// called stored procedure for counter
		$result = $this->SFA_Comman->executequery('CALL sp_get_promotions_advance_promotionplanitemgrid(?,?,?,?,?,?,?,?)',$param_array,'');
		$data_arr["count"] 	= $result[0][0]['counter'];
		$data_arr["data"][0]= $result[1];
		
		#echo "<pre>"; print_r($data_arr); exit;
		
		//SFA_Comman::pre($params);
		
		$this->view->pagegridshow =  $pagingshow->summary_showdatagrid($data_arr);
		$this->view->addScriptPath(APPLICATION_PATH."/theme/default/datagrid/");
	
		$this->render("ajaxgrid");
    }
	/**
    * @name       addpromotionplanajaxAction
    * @since      02-02-2012
    * @version    Release: 1
    * @author     HD <hiren.d@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This action is use for save promotionplan throught ajax 
    */
    public function addpromotionplanajaxAction()
    {
		$formdata 			= $this->_request->getPost();	
		$this->view->css 	= $this->translate->_('CSS');
	
		if($formdata['txt_plandescription'])
		{
			$param_array = array();
			
			$param_array[1] = $formdata['txt_plandescription'];
			$param_array[2] = $formdata['txt_arbplandescription'];
			$param_array[3] = $formdata['ddl_promotiontypecode'];
			$param_array[4] = $formdata['ddl_activeindicator'];
			$param_array[5] = $formdata['ddl_rangebasis'];
			$param_array[6] = $formdata['ddl_amountbasis'];
			$param_array[7] = $formdata['ddl_exclusionoption']!=""?$formdata['ddl_exclusionoption']:0; //$formdata['ddl_exclusionoption'];
			$param_array[8] = $formdata['ddl_qualificationgroup'];
			$param_array[9] = $formdata['ddl_assignmentgroup'];
			$param_array[10] = $formdata['txt_assignmentnumber']!=""?$formdata['txt_assignmentnumber']:0; //$formdata['txt_assignmentnumber'];
			$param_array[11] = $formdata['chk_iscase']!=""?$formdata['chk_iscase']:0;
			$param_array[12] = $formdata['chk_onetimeuse']!=""?$formdata['chk_onetimeuse']:0;
			$param_array[13] = $formdata['chk_enforcepromotion']!=""?$formdata['chk_enforcepromotion']:0;
			$param_array[14] = $formdata['chk_repeatrange']!=""?$formdata['chk_repeatrange']:0; // $formdata['chk_repeatrange'];
			$param_array[15] = $this->currentUser->username;
		  
			if($formdata['hdnid'] > 0) {
				$param_array[16] = $formdata['hdnid'];
				$result = $this->SFA_Comman->executequery('CALL sp_edit_promotions_advance_addpromotionplan(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',$param_array,'');
				echo $result[0][0]['lastid'];
			} else {
				$result = $this->SFA_Comman->executequery('CALL sp_add_promotions_advance_addpromotionplan(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',$param_array,'');
				SFA_Message::setMsg($this->translate->_('New Record'));
				echo $result[0][0]['lastid'];
			}
		}
		exit;
	}
   
    /**
    * @name       groupdetailAction
    * @since      02-02-2012
    * @version    Release: 1
    * @author     HD <hiren.d@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This action is use for the group detail
    */
    public function groupdetailAction()
    {
		$this->view->params = $params = $this->getRequest()->getParams();
	
		$this->_helper->layout->disableLayout();
		$this->_helper->layout->setLayout('popup');
			
		// For Alternate Code Status.
		$cpanel				= $this->SFA_Comman->getaltcodestatus();
		$altcode_status		= $cpanel["Use Alternate Code"]['status'];
		
		$columns_array = array('im.actualitemcode','im.itemshortdescription AS itemshortdescription','itemqty','primary_key AS edit_del_primary_id');
		$columns_show =  array($this->translate->_('Item Code'),$this->translate->_('Item Description'),$this->translate->_('Quantity'));
		
		if($this->css == 'ar_') {
			$columns_array[1]	= 'im.arbitemshortdescription AS itemshortdescription';
		}
			
		if($params['grptype'] == 2)
		{
			$columns_array[3] = 'FORMAT(promocaseprice,'.$this->decimalplaces.') AS promocaseprice';
			$columns_array[4] = 'FORMAT(promopcprice,'.$this->decimalplaces.') AS promopcprice';			
			$columns_array[5] = 'primary_key AS edit_del_primary_id';
			$columns_show[3]  = $this->translate->_('Promo Case Price'); 
			$columns_show[4]  = $this->translate->_('Promo PC Price');
		}
		
		if($altcode_status)
			$columns_array[0] = 'im.alternatecode';
		
		$ex_param = "";
		if( isset($params["grptype"]) && $params["groupcode"] > 0 ) {
			$additional_where_condition = array();
			$additional_where_condition[] = ' (	grouptype = "'.$params['grptype'].'" AND pgd.groupnumber = "'.$params['groupcode'].'")';	    
	}

	
	
	// IF EXTRA PARAMS ARE REQUIRED
	$pagingparams = array(
			     "show_grid_heading" => false,
			     "grid_heading_message" => $this->translate->_('Overview'),
				 "pagename" => $this->translate->_('Group Detail'),
			     "show_records_per_page" => isset($formdata["show_records_per_page"])?$formdata["show_records_per_page"]:10,
			     "show_searchbox" => false,
			     "show_selectbox" => false,
			     "show_editlink" => false,
			     "show_deletelink" => false,			     
			     "deletelink" => array("/id/#pattern#/delete/yes","#pattern#"),			     
			     "show_deleteall" => false,
			     "primaryid" => "primary_key",
			     "fetch_columns_inquery" => $columns_array,
			     "show_columns" => $columns_show,			     
			     "nodata_message" => $this->translate->_('No Record(s) Found'),
				 "show_columns_right_side"=>array('promopcprice','promocaseprice'),
				 "show_header_right_side"=>array($this->translate->_('Promo PC Price'),$this->translate->_('Promo Case Price')),
			     "additional_where" => $additional_where_condition
			     );
	
	$pagingshow = new SFA_Paging($pagingparams);	
	$get_return_vals = $pagingshow->commnfunc();
	
	$pos = strrpos($get_return_vals['where_condition'], 'grouptype = "1"');
	if ($pos) {
	    $this->view->title = 'Qualification Group';	    
	}
	else{
	    $this->view->title = 'Assignment Group';
	}
	
	$param_array 	= array();
	$param_array[1] = '';
	$param_array[2] = $get_return_vals['order_columns_name'];
	$param_array[3] = $get_return_vals['order_type'];
	$param_array[4] = $get_return_vals['offset'];
	$param_array[5] = (int)$get_return_vals['show_records_per_page'];
	$param_array[6] = implode(", ",$pagingparams["fetch_columns_inquery"]);
	$param_array[7] = strlen($get_return_vals['where_condition'])>0?$get_return_vals['where_condition']:'';
	
	$downloadCSV = (isset($formdata['downloadcsv'])) ? $formdata['downloadcsv'] : $params['downloadcsv'];
    
    // Hiren Dave on 19 Nov, 2012 - START - to check if need to print data
	$printData = (isset($formdata['printData'])) ? $formdata['printData'] : $params['printData'];
	// called stored procedure for counter
	$result = $this->SFA_Comman->executequery('CALL sp_get_promotions_customer_authorizegroupitemgrid(?,?,?,?,?,?,?)',$param_array,'',$downloadCSV,$printData,2,$pagingparams);
	
	$data_arr["count"] 	= $result[0][0]['counter'];
	$data_arr["data"][0] 	= $result[1];
	
	// pass the data in summary_showdatagrid() function & create a final variable for view
	$this->view->pagegridshow =  $pagingshow->summary_showdatagrid($data_arr);

	$this->view->addScriptPath(APPLICATION_PATH."/theme/default/datagrid/");
    }
    /**
    * @name       promotionkeyAction
    * @since      02-02-2012
    * @version    Release: 1
    * @author     HD <hiren.d@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This action is use for the display advance key
    */
    public function promotionkeyAction()
    {
	$this->view->params = $params = $this->getRequest()->getParams();
        $this->view->formdata = $formdata = $this->_request->getPost();

	if($formdata["hdDelete"]==1)
	{
	    $ids = implode(',',$formdata['chk']);
	    $param_array 	= array();
	    $param_array[1]	= $ids;
	    $param_array[2]	= $this->currentUser->username;
	    
	    $result 	= $this->SFA_Comman->executequery('CALL sp_delete_promotions_advance_promotionkey(?,?)',$param_array,'');
	    
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

	
	$promo_key		= $this->translate->_('Promotion Key');
	$desc			= $this->translate->_('Description');
	$arb_desc		= $this->translate->_('Description ('.$this->sec_lang.')');
	$isactive		= $this->translate->_('Status');

	$columns_array = array('promotionkey','description','arbdescription','activeindicator');
	$columns_show  = array($promo_key,$desc,$arb_desc,$isactive);
	
	$pagingparams = array(
			     "show_grid_heading" => true,
			     "grid_heading_message" => $this->translate->_('Overview'),
				 "pagename" => $this->translate->_('Promotion Key'),
			     "show_records_per_page" => isset($formdata["show_records_per_page"])?$formdata["show_records_per_page"]:10,
			     "show_searchbox" => true,
			     "selected_list" => $checked,
			     "searchbox_value" => isset($formdata["grid_search_text"])?$formdata["grid_search_text"]:"",
			     "show_selectbox" => true,
			     "show_editlink" => true,
			     "show_deletelink" => false,
			     "deletelink" => array("/promotions/advance/promotionkey/id/#pattern#/delete/yes/","#pattern#"),
			     "show_deleteall" => false,
			     "primaryid" => "promotionkey",
			     "status_cols" => array(
					      array(
						  "cols_name" => "activeindicator",
						  "status_change" => array("0"=>"Inactive","1"=>"Active")
						  )
					      ),
			     "editlink" => array("/promotions/advance/addpromotionkey/edit/yes/id/#pattern#","#pattern#"),
			     "fetch_columns_inquery" => $columns_array,
			     "show_columns" => $columns_show,
			     "nodata_message" => $this->translate->_('No Record(s) Found')
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
	$rowcount = $this->SFA_Comman->executequery('CALL sp_get_promotions_advance_promotionkey(?,?,?,?,?,?,?,?)',$param_array,'',$downloadCSV,$printData,2,$pagingparams);
	$data_arr["count"] 		= $rowcount[0][0]['counter'];
	$data_arr["data"][0]	= $rowcount[1];
	
	// pass the data in summary_showdatagrid() function & create a final variable for view
	$this->view->pagegridshow =  $pagingshow->summary_showdatagrid($data_arr);
	
	$this->view->addScriptPath(APPLICATION_PATH."/theme/default/datagrid/");
    }
    /**
    * @name       addpromotionkey
    * @since      1-2-2012
    * @version    Release: 1
    * @author     HD <hiren.d@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This action is use for add promotion key
    */
    public function addpromotionkeyAction(){

	$this->view->params = $params = $this->getRequest()->getParams();
        $this->view->formdata = $formdata = $this->_request->getPost();

	$this->view->returnUrl = $_SERVER["HTTP_REFERER"];
	
	$this->view->css = $this->translate->_('CSS');

	if($formdata['txt_description'])
	{
	    $param_array = array();		
	    
	    $param_array[1] = $formdata['txt_description'];
	    $param_array[2] = $formdata['ddl_type'];
	    $param_array[3] = $formdata['txt_arbdescription'];
	    $param_array[4] = $formdata['ddl_activeindicator'];
	    $param_array[5] = $this->currentUser->username;
	    
	    if($formdata['hdnid'] > 0) {
		
		$param_array[6] = $formdata['txt_promotionkey'];
		$result = $this->SFA_Comman->executequery('CALL sp_edit_promotions_advance_addpromotionkey(?,?,?,?,?,?)',$param_array,'');
		SFA_Message::setMsg($this->translate->_('Update Record'));
		$last_id = $formdata['txt_promotionkey'];
		
		$this->_helper->redirector('promotionkey', 'advance', 'promotions');    
		
	    }
	    else
	    {
		$result = $this->SFA_Comman->executequery('CALL sp_add_promotions_advance_addpromotionkey(?,?,?,?,?)',$param_array,'');
		
		SFA_Message::setMsg($this->translate->_('New Record'));
		$last_id = $result[0][0]['insid'];
		$this->_helper->redirector('addpromotionkey', 'advance', 'promotions',array("edit"=>"yes","id"=>$last_id)); 
		
	    }
	  
	}
	else
	{
	    // promotion_type
	    $promotion_type[] = array("id"=>"1","val"=>"Standard Promotion");
	    $promotion_type[] = array("id"=>"2","val"=>"Fixed Promotion");
	    
	    $this->view->promotion_type = $promotion_type;
	    
	    if($params['id'] > 0)
	    {
		$result  		= $this->SFA_Comman->executequery('CALL sp_get_promotions_advance_addpromotionkey(?)',$params['id'],'');
		$this->view->formdata 	= $result[0][0];
		
		$this->view->itemgrid   = $this->view->BaseUrl("/promotions/advance/promotionkeyitemgrid/key/".$params['id']);
		$this->view->itemgrid2   = $this->view->BaseUrl("/promotions/advance/promotionplanselectionitemgrid/key/".$params['id']);
		
	    }
	    else
	    {
		$result  = $this->SFA_Comman->executequery('CALL sp_get_table_last_id(?)','promokeyheader','');
		$this->view->formdata['promotionkey'] = $result[0][0]["Auto_increment"];
	    }
	}
    }


    /**
    * @name       promotionkeyitemgrid
    * @since      16-04-2012
    * @version    Release: 2
    * @author     M@M <miral@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This action is use for add page of promotion key 
    */
    public function promotionkeyitemgridAction()
    {
		$this->view->params = $params = $this->getRequest()->getParams();
		$this->view->formdata = $formdata = $this->_request->getPost();
		
		// column to be fetched
		$columns_array 	=  array('p.plannumber','DATE_FORMAT(p.startdate,"%d/%m/%Y") as startdate','DATE_FORMAT(p.enddate,"%d/%m/%Y") AS enddate','pd.plandescription','CONCAT(p.qualificationgroup," --- ",pgh.groupdescription) AS qualificationgroup','CONCAT(p.assignmentgroup," --- ",pgh1.groupdescription) AS assignmentgroup','active','primary_key as edit_del_primary_id');
		
		
		if($this->css == 'ar_') {
			$columns_array[3]	= 'arbplandescription AS plandescription';
		}
		// column header to be displayed
		$promo_plan		= $this->translate->_('Promo Plan');
		$start_date		= $this->translate->_('Start Date');
		$end_date		= $this->translate->_('End Date');
		$description	= $this->translate->_('Description');
		$quali_grp		= $this->translate->_('Qualification Group');
		$assign_grp		= $this->translate->_('Assignment Group');
		$isactive		= $this->translate->_('Status');
	
		$columns_show  = array($promo_plan,$start_date,$end_date,$description,$quali_grp,$assign_grp,$isactive);
		
		// ADDING THE RECORD
		if($formdata["add"]=="yes"){
				
			$paramarry = array();
		  
			if($formdata['chk']){
			
			$planarray = array(0);
			
			if(isset($formdata['chk']) && is_array($formdata['chk']) && count($formdata['chk']))
			{
			$plan_list = implode(",",$formdata["chk"]);
			
			$paramarry[1] = $params["key"];
			$paramarry[2] = $plan_list;
			$paramarry[3] = count($formdata['chk']);
			
			#echo "<pre>"; print_r($paramarry); exit;
			$r_add = $this->SFA_Comman->executequery('CALL sp_add_promotions_advance_promotionkeyitemgrid',$paramarry,'');
			
			SFA_Message::setMsg($this->translate->_('Update Record'));
			}
		  }
	}
	
	// DELETE THE RECORD
	if($params["delete"]=="yes"){
	    
	    $param_array 	= array();
	    $param_array[1]	= $params["id"];
	    $param_array[2]	= $this->currentUser->username;
	    
	    $r_delete = $this->SFA_Comman->executequery('CALL sp_delete_promotions_advance_promotionkeyitemgrid',$param_array,'');
            SFA_Message::setMsg($this->translate->_('Delete Record'));
	}

	// UPDATE THE RECORD
	if($params["update"]=="yes"){

	    $e_startdate = str_replace('/', '-', $params["startdate"]);
	    $e_enddate = str_replace('/', '-', $params["enddate"]);
	    
	    $updateData["1"] = date("Y-m-d",strtotime($e_startdate));
	    $updateData["2"] = date("Y-m-d",strtotime($e_enddate));
	    $updateData["3"] = $params["id"];
	    
	    // call sp for edit currencydetail
	    $r_edit = $this->SFA_Comman->executequery('CALL sp_edit_promotions_advance_promotionkeyitemgrid',$updateData,'');
	    SFA_Message::setMsg($this->translate->_('Update Record'));
	}

	// IF EXTRA PARAMS ARE REQUIRED
	$ex_param = "";
	if(isset($params["key"]) && $params["key"]>0){
	    $additional_where_condition = array();
	    $ex_param = "/key/".$params["key"];
	     $additional_where_condition[] = ' (promotionkey = "'.$params["key"].'" )';
	}

	$pagingparams = array(
			     "show_grid_heading" => false,
			     "grid_heading_message" => $this->translate->_('Overview'),
			     "show_records_per_page" => isset($formdata["show_records_per_page"])?$formdata["show_records_per_page"]:10,
			     "show_searchbox" => false,
			     "show_selectbox" => false,
			     "show_editlink" => true,
			     "show_deletelink" => true,
			     "show_deleteall" => false,
			     "primaryid" => "primary_key",
			     "currentlink" => array("/promotions/advance/promotionkeyitemgrid".$ex_param),
			     "deletelink" => array("/id/#pattern#/delete/yes","#pattern#"),
			     "editlink" => array("/id/#pattern#/edit/yes","#pattern#"),
			     "noeditfields" => array("plannumber","plandescription","qualificationgroup","assignmentgroup","active"),
			     "nodata_message" => $this->translate->_('No Record(s) Found'),
			     "status_cols" => array(
					      array(
						  "cols_name" => "active",
						  "status_change" => array("0"=>"Inactive","1"=>"Active")
						  )
					      ),
			     "fetch_columns_inquery" => $columns_array,
			     "show_columns" => $columns_show,
			     "additional_where" => $additional_where_condition
			     );
	
	// WHEN GRID IS IN EDIT MODE
	if($params["edit"]=="yes"){

	    $pagingparams["editmode"] = true;
	    $pagingparams["editmodeid"] = $params["id"];
	    $pagingparams["editmodevalue"] = "primary_key";  // put table's prymary key here
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
	$result = $this->SFA_Comman->executequery('CALL sp_get_promotions_advance_promotionkeyitemgrid(?,?,?,?,?,?,?)',$param_array,'');
	$data_arr["count"] 		= $result[0][0]['counter'];	
	$data_arr["data"][0] 	= $result[1];
	
	$this->view->pagegridshow =  $pagingshow->summary_showdatagrid($data_arr);
	$this->view->addScriptPath(APPLICATION_PATH."/theme/default/datagrid/");

    }
   
   /**
    * @name       promotionplanselectionitemgridAction
    * @since      16-04-2012
    * @version    Release: 2
    * @author     M@M <miral@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This action is use for add promotion plan to promotion key item grid
    */
    public function promotionplanselectionitemgridAction()
    {
		$this->view->params = $params = $this->getRequest()->getParams();
		$this->view->formdata = $formdata = $this->_request->getPost();
	
		// column to be fetched
		$columns_array 	=  array('plannumber','plandescription','CONCAT(p.qualificationgroup," --- ",pgh.groupdescription) AS qualificationgroup','CONCAT(p.assignmentgroup," --- ",pgh1.groupdescription) AS assignmentgroup');
		
		if($this->css == 'ar_') {
			$columns_array[1]	= 'arbplandescription AS plandescription';
		}
	
		// column header to be displayed
		$promo_plan		= $this->translate->_('Promo Plan');
		$description	= $this->translate->_('Description');
		$quali_grp		= $this->translate->_('Qualification Group');
		$assign_grp		= $this->translate->_('Assignment Group');
		
		$columns_show  = array($promo_plan,$description,$quali_grp,$assign_grp);
		
		// gettting the already having the plan number into plan key detail
		$selected_plan_list = $this->SFA_Comman->executequery('CALL sp_getplanlist_promotions_advance_promotionplanselectionitemgrid',$params["key"],'');
		//echo $params["key"];exit;
		// plan numbers array
		$plan_array = explode(",",$selected_plan_list[0][0]["plans"]);
	
	
	// IF EXTRA PARAMS ARE REQUIRED
	//$ex_param = "";
	//if(isset($params["key"]) && $params["key"]>0){
	//    $additional_where_condition = array();
	//    $ex_param = "/key/".$params["key"];
	//     $additional_where_condition[] = ' (promotionkey = "'.$params["key"].'" )';
	//}

	$pagingparams = array(
			     "show_grid_heading" => false,
			     "grid_heading_message" => $this->translate->_('Overview'),
			     "show_records_per_page" => isset($formdata["show_records_per_page"])?$formdata["show_records_per_page"]:10,
			     "show_searchbox" => false,
			     "show_selectbox" => true,
			     "selected_list" => $plan_array,
			     "show_editlink" => false,
			     "show_deletelink" => false,
			     "show_deleteall" => false,
			     "primaryid" => "plannumber",
			     "currentlink" => array("/promotions/advance/promotionplanselectionitemgrid".$ex_param),
			     "deletelink" => array("/id/#pattern#/delete/yes","#pattern#"),
			     "editlink" => array("/id/#pattern#/edit/yes","#pattern#"),
			     "nodata_message" => $this->translate->_('No Record(s) Found'),
			     "fetch_columns_inquery" => $columns_array,
			     "show_columns" => $columns_show
			     );
	
	$pagingshow = new SFA_Ajaxpagingextra($pagingparams);
	
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
	$result = $this->SFA_Comman->executequery('CALL sp_get_promotions_advance_promotionplanselectionitemgrid(?,?,?,?,?,?,?)',$param_array,'');
	$data_arr["count"] 		= $result[0][0]['counter'];
	$data_arr["data"][0] 	= $result[1];
	
	$this->view->pagegridshow_4 =  $pagingshow->summary_showdatagrid($data_arr);
	$this->view->addScriptPath(APPLICATION_PATH."/theme/default/datagrid/");
    }
    
   
     /**
    * @name       promotionkeyplan
    * @since      03-02-2012
    * @version    Release: 1
    * @author     HD <hiren.d@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This action is use for the display advance key plan
    */
    public function promotionkeyplanAction()
    {
	$this->view->params = $params = $this->getRequest()->getParams();
        $this->view->formdata = $formdata = $this->_request->getPost();

	$this->_helper->layout->setLayout('popup');

        // IF EXTRA PARAMS ARE REQUIRED
	$ex_param = "";
	if(isset($params["id"]) && $params["id"]>0)
	    $ex_param = "/key/".$params["id"];

	$this->view->itemgrid    = $this->view->BaseUrl("/".$params['module']."/".$params['controller']."/promotionkeyplangrid".$ex_param);

	$this->view->title	= $this->translate->_('Advance Promotion Plan');
	$this->view->save	= $this->translate->_('SAVE');

	if(count($formdata) > 0) {
    	    SFA_Message::setMsg($this->translate->_('New Record'));
	    $this->_helper->redirector('addpromotionkey', 'advance', 'promotions');
	}
    }

    /**
    * @name       promotionkeyplangrid
    * @since      03-02-2012
    * @version    Release: 1
    * @author     HD <hiren.d@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This action is use for prmotion key plan grid
    */
    public function promotionkeyplangridAction()
    {
        $this->view->params = $params = $this->getRequest()->getParams();

        $plan			= $this->translate->_('Plan');
	$desc			= $this->translate->_('Description');
	$promo_type		= $this->translate->_('Promotion Type');
	$quali_grp		= $this->translate->_('Qualification Group');
	$assign_grp		= $this->translate->_('Assignment Group');
	$range_basis		= $this->translate->_('Range Basis');
	$amt_basis		= $this->translate->_('Amount Basis');


        // DELETE THE RECORD
        if($params["delete"]=="yes"){
            //(prymary key)
            $this->common_model->delete_row('user','id',$params["id"]);
            SFA_Message::setMsg($this->translate->_('Delete Record'));
        }

        // UPDATE THE RECORD
        if($params["update"]=="yes"){
            $updateData["first_name"] = $_GET["first_name"];
            //(prymary key)
            $this->common_model->edit_row('user','id',$params["id"],$updateData);
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
				    "show_selectbox" => true,
				    "show_editlink" => false,
				    "show_deletelink" => false,
				    "show_deleteall" => false,
				    "primaryid" => "usr.id",
                                    "currentlink" => array("/".$params['module']."/".$params['controller']."/".$params['action'].$ex_param),
                                    "deletelink" => array("/id/#pattern#/delete/yes","#pattern#"),
                                    "editlink" => array("/id/#pattern#/edit/yes","#pattern#"),
                                    "nodata_message" => $this->translate->_('No Record(s) Found')
                                    );

        // WHEN GRID IS IN EDIT MODE
        if($params["edit"]=="yes"){

            $pagingparams["editmode"] = true;
            $pagingparams["editmodeid"] = $params["id"];
            $pagingparams["editmodevalue"] = "id";  // put table's prymary key here
        }
            $pagingshow = new SFA_Ajaxgrid($pagingparams);
            $pagingshow->from(array('usr' => 'user'),
		    array('usr.id','usr.first_name','FLOOR(RAND(10)*1)','FLOOR(RAND(20)*2)','FLOOR(RAND(30)*3)','FLOOR(RAND(4)*4)','FLOOR(RAND(5)*5)'));
	$columns_show  = array($plan,$desc,$promo_type,$quali_grp,$assign_grp,$range_basis,$amt_basis);

            $this->view->pagegridshow =  $pagingshow->summary_showdatagrid($columns_show,'',$result);
            $this->view->addScriptPath(APPLICATION_PATH."/theme/default/datagridquery/");

        $this->render("ajaxgrid");
    }    
}
