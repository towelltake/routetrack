<?php
/**
* @name       IndexController
* @since
* @version    Release: 1
* @author     HD <hiren.d@elantechnologies.com>
* @copyright  Elan Technologies
* @param
*
* This controller is manage user Promotions module.
*/
class Promotions_IndexController extends Promotions_Library_Controller_Action_Abstract
{
    public $SFA_Model_Index = '';

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
		$this->view->colan		= $this->translate->_('Colan');
	
		$this->SFA_Model_Index	= new SFA_Model_Index();
		$this->SFA_Comman		= new SFA_Comman();
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
    * @name       qualificationgrpAction
    * @since      30-01-2012
    * @version    Release: 1
    * @author     HD <hiren.d@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This action is use for the display Qualification Group
    */
    public function qualificationgrpAction()
    {
		$this->view->params = $params = $this->getRequest()->getParams();
        $this->view->formdata = $formdata = $this->_request->getPost();

		if(isset($formdata["hdnid"]) && $formdata["hdnid"]>0)
			$ex_param = "/key/".$params["hdnid"];
			
		if(isset($params["id"]) && $params["id"]>0)
			$ex_param = "/key/".$params["id"];
	    
		if($formdata["hdDelete"]==1)
		{
			$ids = implode(',',$formdata['chk']);
			$param_array 	= array();
			$param_array[1]	= $ids;
			$param_array[2]	= $this->currentUser->username;
			
			$result 	= $this->SFA_Comman->executequery('CALL sp_delete_promotion_customer_qualificationgrp(?,?)',$param_array,'');
			
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

		$this->view->title	= $this->translate->_('Qualification Group');
	
		$cols_array = array('groupnumber','groupdescription','groupnumber AS edit_del_primary_id');
		$columns_show =  array($this->translate->_('Group Number'),$this->translate->_('Group Description'));
		
		if($this->css == 'ar_') {
			$cols_array[1]	= 'arbgroupdescription AS groupdescription';
		}
		
		$this->view->itemgrid  = $this->view->BaseUrl("/promotions/index/qualificationgrpitemgrid".$ex_param);
	
		$pagingparams = array(
					 "show_grid_heading" => true,
					 "grid_heading_message" => $this->translate->_('Overview'),
					 "pagename" => $this->translate->_('Qualification Group'),
					 "show_records_per_page" => isset($formdata["show_records_per_page"])?$formdata["show_records_per_page"]:10,
					 "show_searchbox" => true,
					 "searchbox_value" => isset($formdata["grid_search_text"])?$formdata["grid_search_text"]:"",
					 "show_selectbox" => true,
					 "selected_list" => $checked,
					 "show_editlink" => true,
					 "show_deletelink" => false,
					 "show_deleteall" => false,
					 "primaryid" => "groupnumber",
					 "editlink" => array("/promotions/index/addqualificationgrp/id/#pattern#/edit/yes/","#pattern#"),
					 "deletelink" => array("/account/index/authorizegroup/id/#pattern#/delete/yes/","#pattern#"),
					 "fetch_columns_inquery" => $cols_array,
					 "show_columns" => $columns_show,
					 "nodata_message" => $this->translate->_('No Record(s) Found')
					 );
	
		
		$pagingshow = new SFA_Paging($pagingparams);
		// call common function of grid class
		$get_return_vals = $pagingshow->commnfunc();
		
		$get_return_vals['where_condition'] .= " AND grouptype = 1 ";
		
		$param_array	= array();	
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
		$result = $this->SFA_Comman->executequery('CALL sp_get_promotions_customer_authorizegroup(?,?,?,?,?,?,?)',$param_array,'',$downloadCSV,$printData,2,$pagingparams);
		
		$data_arr["count"] = $result[0][0]['counter'];
		$data_arr["data"][0] = $result[1];
		
		// pass the data in summary_showdatagrid() function & create a final variable for view
		$this->view->pagegridshow =  $pagingshow->summary_showdatagrid($data_arr);
	
		$this->view->addScriptPath(APPLICATION_PATH."/theme/default/datagrid/");
    }
    /**
    * @name       addqualificationgrpAction
    * @since      26-01-2012
    * @version    Release: 1
    * @author     HD <hiren.d@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This action is use for the add qualififacation group
    */
    public function addqualificationgrpAction()
    {
		$this->view->params = $params = $this->getRequest()->getParams();
        $this->view->formdata = $formdata = $this->_request->getPost();
		
		$Settings_NameSpace = new Zend_Session_Namespace('Settings');		
		$tmp = $Settings_NameSpace->cpanel['Fixed Qualification/Fixed Assignment']['status'];
		if(!$tmp)
		$tmp = $Settings_NameSpace->cpanel['Ranged Qualification on Fixed Assignment']['status'];
		
		$this->view->add_quantity = $tmp;
		
		if(isset($formdata["hdnid"]) && $formdata["hdnid"]>0)
			$ex_param = "/key/".$params["hdnid"];
			
		if(isset($params["id"]) && $params["id"]>0)
			$ex_param = "/key/".$params["id"];
	
		$this->view->itemgrid  = $this->view->BaseUrl("/promotions/index/qualificationgrpitemgrid".$ex_param);
		
		if($params['id'] > 0){
			//Following code is for Edit
			if(!empty($formdata['txtgrp_num']) && !empty($formdata['txtgrp_desc'])){
			$param_array = array();
			//$formdata['txtgrp_num'];
			$param_array[1] = trim($formdata["hdnid"]); 		//groupnumber
			$param_array[2] = trim($formdata['txtgrp_desc']);	//groupdescription
			$param_array[3] = trim($formdata['txtgrp_arb']);	//arbgroupdescription
			$param_array[4] = $this->currentUser->username;		//modified
			
			$r_update = $this->SFA_Comman->executequery('CALL sp_edit_promotions_customer_addauthorizegroup(?,?,?,?)',$param_array,'');
			SFA_Message::setMsg($this->translate->_('Update Record'));
			$this->_helper->redirector('qualificationgrp', 'index', 'promotions');
			}
			$result  		= $this->SFA_Comman->executequery('CALL sp_get_promotions_customer_addauthorizegroup(?)',$params['id'],'');
			$res['txtgrp_num'] 	= $result[1][0]['groupnumber'];
			$res['txtgrp_desc'] = $result[1][0]['groupdescription'];
			$res['txtgrp_arb'] 	= $result[1][0]['arbgroupdescription'];
			$res['createddate'] = date("d-m-Y",strtotime($result[1][0]['cdat']));
			$this->view->formdata = $res;
			$this->view->itemcode = $result[0];
		
		}else{
			//Following code is for Add
			if(!empty($formdata['txtgrp_num']) && !empty($formdata['txtgrp_desc'])){
			$param_array = array();
			$param_array[1] = trim($params["hdnid"]); 		//groupnumber
			$param_array[2] = trim($formdata['txtgrp_desc']);	//groupdescription
			$param_array[3] = trim($formdata['txtgrp_arb']);	//arbgroupdescription
			$param_array[4] = $formdata['ddlitem_code'];		//itemcode
			$param_array[5] = 1;					//arbgroupdescription
			$param_array[6] = $this->currentUser->username;		//created
			$param_array[7] = '0';					//promopcprice
			$param_array[8] = '0';					//promocaseprice
			$param_array[9] = trim($formdata["txtquantity"]); //promocaseprice
			
			$r_update = $this->SFA_Comman->executequery('CALL sp_add_promotions_customer_addauthorizegroup(?,?,?,?,?,?,?,?)',$param_array,'');
			SFA_Message::setMsg($this->translate->_('New Record'));
			$this->_helper->redirector('qualificationgrp', 'index', 'promotions');
			}
			$table_name = 'productgroupheader';
			$result = $this->SFA_Comman->executequery('CALL sp_getcombobox_promotions_customer_addauthorizegroup(?)',$table_name,'');
			$this->view->itemcode = $result[0];
			$this->view->formdata['txtgrp_num']= ($result[1][0]['Auto_increment'] == '') ? '1' : $result[1][0]['Auto_increment'];
		}
    }


    
    /**
    * @name       qualificationgrpitemgridAction
    * @since      10-04-2012
    * @version    Release: 1
    * @author     Jinal <jinal@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This action is use for qualification group
    */
    public function qualificationgrpitemgridAction()
    {
		$this->view->params = $params = $this->getRequest()->getParams();
		$this->view->formdata = $formdata = $this->_request->getPost();
	
		// For Alternate Code Status.
		$cpanel				= $this->SFA_Comman->getaltcodestatus();
		$altcode_status		= $cpanel["Use Alternate Code"]['status'];
		
		$Settings_NameSpace = new Zend_Session_Namespace('Settings');
		$tmp = $Settings_NameSpace->cpanel['Fixed Qualification/Fixed Assignment']['status'];
		if(!$tmp)
		$tmp = $Settings_NameSpace->cpanel['Ranged Qualification on Fixed Assignment']['status'];
		
		$this->view->add_quantity = $tmp;
		
		if($altcode_status)
		{	
			$columns_array 	= array('im.alternatecode','im.itemshortdescription','primary_key AS edit_del_primary_id');
			$columns_show 	= array($this->translate->_('Alternate Code'),$this->translate->_('Item Description'));
		}
		else
		{
			$columns_array 	= array('im.actualitemcode','im.itemshortdescription','primary_key AS edit_del_primary_id');
			$columns_show 	= array($this->translate->_('Item Code'),$this->translate->_('Item Description'));
		}
		if($this->css == 'ar_') {
			$columns_array[1]	= 'im.arbitemshortdescription AS itemshortdescription';
		}
		if($tmp)
		{
			$columns_array[4] 	= 'pgd.itemqty';
			$columns_show[4]	= $this->translate->_('Item Quantity');
		}
		
		//To get value of group number and listed out inner grid.
		$ex_param = "";
		if(isset($params["key"]) && $params["key"]>0){
			$ex_param = "/key/".$params["key"];
		}
	
		if(isset($formdata["hdnid"]) && $formdata["hdnid"]>0){
			$ex_param = "/key/".$formdata["hdnid"];
			$params['key'] = $formdata["hdnid"];
		}
	
		// DELETE THE RECORD
		if($params["delete"]=="yes") {
			// itemmaster (table name), ActualItemCode (prymary key)
			$paramarry = array();
			$paramarry[1] = $params['id'];
			$r_delete = $this->SFA_Comman->executequery('CALL sp_delete_promotions_customer_authorizegroupitemgrid(?)',$paramarry,'');
	
			SFA_Message::setMsg($this->translate->_('Delete Record'));
		}
		if($formdata["add"]=="yes") {
			
			if(!empty($formdata['txtgrp_num']) && !empty($formdata['txtgrp_desc']) && !empty($formdata['ddlitem_code'])){
				$param_array = array();
			$param_array[1] = trim($formdata['txtgrp_num']); 	//groupnumber
			$param_array[2] = trim($formdata['txtgrp_desc']);	//groupdescription
			$param_array[3] = trim($formdata['txtgrp_arb']);	//arbgroupdescription
			$param_array[4] = trim($formdata['ddlitem_code']);	//itemcode - productgroupdetail
			$param_array[5] = 1;					//grouptype
			$param_array[6] = $this->currentUser->username;		//grouptype
			$param_array[7] = '0';					//promopcprice
			$param_array[8] = '0';					//promocaseprice
			$param_array[9] = trim($formdata["txtquantity"]); //promocaseprice
	
			$returnval = $this->SFA_Comman->executequery('CALL sp_add_promotions_customer_addauthorizegroup(?,?,?,?,?,?,?,?)',$param_array,'');
			$last_id = $returnval[0][0];
			
			if($last_id['duplicate'] == 'duplicate'){
					SFA_Message::setErrorMsg($this->translate->_('Item Code is already Exist.'));
			}elseif(isset($last_id['var_groupnumber']) && $last_id['var_groupnumber']>0){
				SFA_Message::setMsg($this->translate->_('New Record'));
			}
			}
		}
	
		// IF EXTRA PARAMS ARE REQUIRED
		$pagingparams = array(
						"show_grid_heading" => false,
						"grid_heading_message" => $this->translate->_('Overview'),
						"show_records_per_page" => isset($formdata["show_records_per_page"])?$formdata["show_records_per_page"]:10,
						"show_searchbox" => false,
						"show_selectbox" => false,
						"show_editlink" => false,
						"show_deletelink" => true,
						"deletelink" => array("/account/index/deletejourneyplan/id/#pattern#/delete/yes".$ex_param,"#pattern#"),
						"currentlink" => array("/promotions/customer/authorizegroupitemgrid".$ex_param),
						"deletelink" => array("/id/#pattern#/delete/yes","#pattern#"),
						"show_deleteall" => false,
						"primaryid" => "primary_key",
						"fetch_columns_inquery" => $columns_array,
						"show_columns" => $columns_show,
						//"editlink" => array("/promotions/advance/promotionrange?id&amp;#pattern#&amp;iframe=true&amp;width=600&amp;height=300&amp;edit&amp;yes","#pattern#"),
						"nodata_message" => $this->translate->_('No Record(s) Found')
					);
				
		$pagingshow = new SFA_Ajaxpaging($pagingparams);
		
		// call common function of grid class
		$get_return_vals = $pagingshow->commnfunc();
	
		$param_array = array();
		// call the stored procedure for fetch the data  
		$param_array[1] = '';
		$param_array[2] = $get_return_vals['order_columns_name'];
		$param_array[3] = $get_return_vals['order_type'];
		$param_array[4] = $get_return_vals['offset'];
		$param_array[5] = (int)$get_return_vals['show_records_per_page'];
		$param_array[6] = implode(", ",$columns_array);
		$param_array[7] = ' AND pgh.grouptype = 1 AND pgh.groupnumber ='.$params['key'].' ';
	
		// called stored procedure for counter
		$result = $this->SFA_Comman->executequery('CALL sp_get_promotions_customer_authorizegroupitemgrid(?,?,?,?,?,?,?)',$param_array,'');
	
		$data_arr["count"] = $result[0][0]['counter'];
		$data_arr["data"][0] = $result[1];
	
		$this->view->pagegridshow =  $pagingshow->summary_showdatagrid($data_arr);
		$this->view->addScriptPath(APPLICATION_PATH."/theme/default/datagrid/");
	
		$this->render("ajaxgrid");
    }


    /**
    * @name       itemgridAction
    * @since      21-02-2012
    * @version    Release: 1
    * @author     M@M <miral@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This action is use for add item grid
    */
    public function itemgridAction()
    {
	$this->view->params = $params = $this->getRequest()->getParams();

	// column to be fetched
	$columns_array 	=  array('reportitemcode','itemshortdescription','temp1','actualitemcode as edit_del_primary_id');
	
	if($this->css == 'ar_') {
		$columns_array[1]	= 'arbitemshortdescription AS itemshortdescription';
	}
	
	// column header to be displayed
	$item_code			= $this->translate->_('Item Code');
	$description			= $this->translate->_('Desciption');
	$item_qnty			= $this->translate->_('Item Quantity');

	$columns_show  = array($item_code,$description,$item_qnty);
	
	// DELETE THE RECORD
	if($params["delete"]=="yes"){
	     // sp for delete
	    $r_delete = $this->SFA_Comman->executequery('CALL sp_delete_promotions_index_itemgrid(?)',array(1=>$params["id"]),'');
            SFA_Message::setMsg($this->translate->_('Delete Record'));
	}

	// UPDATE THE RECORD
	//if($params["update"]=="yes"){
	//
	//    $updateData["ItemShortDescription"] = $_GET["ItemShortDescription"];
	//
	//    $this->SFA_Model_Index->edit_row('itemmaster','ActualItemCode',$params["id"],$updateData);
	//    SFA_Message::setMsg($this->translate->_('Update Record'));
	//}

	// IF EXTRA PARAMS ARE REQUIRED
	$ex_param = "";
	$additional_where_condition = array();
	if(isset($params["key"]) && $params["key"]>0){
	    $ex_param = "/key/".$params["key"];
	    $additional_where_condition[] = ' (itemgroupcode = "'.$params["key"].'" )';
	}

	$pagingparams = array(
			     "show_grid_heading" => false,
			     "grid_heading_message" => $this->translate->_('Overview'),
			     "show_records_per_page" => isset($formdata["show_records_per_page"])?$formdata["show_records_per_page"]:10,
			     "show_searchbox" => false,
			     "show_selectbox" => false,
			     "show_editlink" => false,
			     "show_deletelink" => true,
			     "show_deleteall" => false,
			     "primaryid" => "item.ActualItemCode",
			     "currentlink" => array("/promotions/index/itemgrid".$ex_param),
			     "deletelink" => array("/id/#pattern#/delete/yes","#pattern#"),
			     "editlink" => array("/id/#pattern#/edit/yes","#pattern#"),
			     "nodata_message" => $this->translate->_('No Record(s) Found'),
			     "fetch_columns_inquery" => $columns_array,
			     "show_columns" => $columns_show,
			     "additional_where" => $additional_where_condition
			     );

	$pagingshow = new SFA_Ajaxpaging($pagingparams);
	
	// call common function of grid class
	$get_return_vals = $pagingshow->commnfunc();
	
	// call the stored procedure for fetch the data  
	$param_array[1] = '1';
	$param_array[2] = $get_return_vals['order_columns_name'];
	$param_array[3] = $get_return_vals['order_type'];
	$param_array[4] = $get_return_vals['offset'];
	$param_array[5] = (int)$get_return_vals['show_records_per_page'];
	$param_array[6] = '';
	$param_array[7] = strlen($get_return_vals['where_condition'])>0?$get_return_vals['where_condition']:'';

	// called stored procedure for counter
	$rowcount = $this->SFA_Comman->executequery('CALL sp_get_promotions_index_itemgrid(?,?,?,?,?,?,?)',$param_array,'');
	$data_arr["count"] = $rowcount[0][0]['counter'];

	// call stored proceddure for data
	$param_array[1] = '';
	$param_array[6] = implode(", ",$pagingparams["fetch_columns_inquery"]);
	
	$data_arr["data"] = $this->SFA_Comman->executequery('CALL sp_get_promotions_index_itemgrid(?,?,?,?,?,?,?)',$param_array,'');
	
	$this->view->pagegridshow =  $pagingshow->summary_showdatagrid($data_arr);
	$this->view->addScriptPath(APPLICATION_PATH."/theme/default/datagrid/");

	$this->render("ajaxgrid");
    }

    /**
    * @name       assignmentgrpAction
    * @since      30-01-2012
    * @version    Release: 1
    * @author     HD <hiren.d@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This action is use for the display assignment Group
    */
    public function assignmentgrpAction()
    {
	$this->view->params = $params = $this->getRequest()->getParams();
        $this->view->formdata = $formdata = $this->_request->getPost();

	if($formdata["hdDelete"]==1)
	{
	    $ids = implode(',',$formdata['chk']);
	    $param_array 	= array();
	    $param_array[1]	= $ids;
	    $param_array[2]	= $this->currentUser->username;	    
	    $result 		= $this->SFA_Comman->executequery('CALL sp_delete_promotion_customer_assignmentgrp(?,?)',$param_array,'');
	    
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

	$this->view->title	= $this->translate->_('Assignment Group');

	$cols_array = array('groupnumber','groupdescription');
	$columns_show =  array($this->translate->_('Group Number'),$this->translate->_('Group Description'));
	
	if($this->css == 'ar_') {
		$cols_array[1]	= 'arbgroupdescription AS groupdescription';
	}
	
	$this->view->itemgrid  = $this->view->BaseUrl("/promotions/index/qualificationgrpitemgrid".$ex_param);

	$pagingparams = array(
			     "show_grid_heading" => true,
			     "grid_heading_message" => $this->translate->_('Overview'),
				 "pagename" => $this->translate->_('Assignment Group'),
			     "show_records_per_page" => isset($formdata["show_records_per_page"])?$formdata["show_records_per_page"]:10,
			     "show_searchbox" => true,
				 "searchbox_value" => isset($formdata["grid_search_text"])?$formdata["grid_search_text"]:"",
			     "show_selectbox" => true,
			     "selected_list" => $checked,
			     "show_editlink" => true,
			     "show_deletelink" => false,
			     "show_deleteall" => false,
			     "primaryid" => "groupnumber",
			     "editlink" => array("/promotions/index/addassignmentgrp/id/#pattern#/edit/yes/","#pattern#"),
			     "deletelink" => array("/promotions/index/addassignmentgrp/id/#pattern#/delete/yes/","#pattern#"),
			     "fetch_columns_inquery" => $cols_array,
			     "show_columns" => $columns_show,
			     "nodata_message" => $this->translate->_('No Record(s) Found')
			     );

	
	$pagingshow = new SFA_Paging($pagingparams);
	// call common function of grid class
	$get_return_vals = $pagingshow->commnfunc();
	
	$get_return_vals['where_condition'] .= " AND grouptype = 2 ";
	
	$param_array	= array();
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
	$result = $this->SFA_Comman->executequery('CALL sp_get_promotions_customer_authorizegroup(?,?,?,?,?,?,?)',$param_array,'',$downloadCSV,$printData,2,$pagingparams);
	
	$data_arr["count"] = $result[0][0]['counter'];
	$data_arr["data"][0] = $result[1];
	
	// pass the data in summary_showdatagrid() function & create a final variable for view
	$this->view->pagegridshow =  $pagingshow->summary_showdatagrid($data_arr);

	$this->view->addScriptPath(APPLICATION_PATH."/theme/default/datagrid/");
    }
    /**
    * @name       addassignmentgrpAction
    * @since      26-01-2012
    * @version    Release: 1
    * @author     HD <hiren.d@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This action is use for the add assignment group
    */
    public function addassignmentgrpAction()
    {
		$this->view->params = $params = $this->getRequest()->getParams();
        $this->view->formdata = $formdata = $this->_request->getPost();
		
		$Settings_NameSpace = new Zend_Session_Namespace('Settings');		
		$tmp = $Settings_NameSpace->cpanel['Fixed Qualification/Fixed Assignment']['status'];
		if(!$tmp)
		$tmp = $Settings_NameSpace->cpanel['Ranged Qualification on Fixed Assignment']['status'];
		
		$this->view->add_quantity = $tmp;		

	if(isset($formdata["hdnid"]) && $formdata["hdnid"]>0)
	    $ex_param = "/key/".$params["hdnid"];
	    
	if(isset($params["id"]) && $params["id"]>0)
	    $ex_param = "/key/".$params["id"];
	
	$this->view->itemgrid  = $this->view->BaseUrl("/promotions/index/assignmentitemgrid".$ex_param);
	
	if($params['id'] > 0){
	    //Following code is for Edit
	    if(!empty($formdata['txtgrp_num']) && !empty($formdata['txtgrp_desc'])){
		$param_array = array();
		//$formdata['txtgrp_num'];
		$param_array[1] = trim($formdata["hdnid"]); 		//groupnumber
		$param_array[2] = trim($formdata['txtgrp_desc']);	//groupdescription
		$param_array[3] = trim($formdata['txtgrp_arb']);	//arbgroupdescription
		$param_array[4] = $this->currentUser->username;		//modified
		$r_update = $this->SFA_Comman->executequery('CALL sp_edit_promotions_customer_addauthorizegroup(?,?,?,?)',$param_array,'');
		SFA_Message::setMsg($this->translate->_('Update Record'));
		$this->_helper->redirector('assignmentgrp', 'index', 'promotions');
	    }
	    $result  		= $this->SFA_Comman->executequery('CALL sp_get_promotions_customer_addauthorizegroup(?)',$params['id'],'');
	    $res['txtgrp_num'] 	= $result[1][0]['groupnumber'];
	    $res['txtgrp_desc'] = $result[1][0]['groupdescription'];
	    $res['txtgrp_arb'] 	= $result[1][0]['arbgroupdescription'];
	    $res['createddate'] = date("d-m-Y",strtotime($result[1][0]['cdat']));

	    $this->view->formdata = $res;
	    $this->view->itemcode = $result[0];
	    
	    
    
	}else{
	    //Following code is for Add
	    if(!empty($formdata['txtgrp_num']) && !empty($formdata['txtgrp_desc'])){
		$param_array = array();
		$param_array[1] = trim($params["hdnid"]); 		//groupnumber
		$param_array[2] = trim($formdata['txtgrp_desc']);	//groupdescription
		$param_array[3] = trim($formdata['txtgrp_arb']);	//arbgroupdescription
		$param_array[4] = $formdata['ddlitem_code'];		//itemcode
		$param_array[5] = 2;					//grouptype
		$param_array[6] = $this->currentUser->username;		//created
		$param_array[7] = $formdata['txtpromo_pc_price'];	//promopcprice
		$param_array[8] = $formdata['txtpromo_case_price'];	//promocaseprice
		$param_array[9] = trim($formdata["txtquantity"]); //promocaseprice
		
		$r_update = $this->SFA_Comman->executequery('CALL sp_add_promotions_customer_addauthorizegroup(?,?,?,?,?,?,?,?)',$param_array,'');
		SFA_Message::setMsg($this->translate->_('New Record'));
		$this->_helper->redirector('assignmentgrp', 'index', 'promotions');
	    }
	    $table_name = 'productgroupheader';
	    $result = $this->SFA_Comman->executequery('CALL sp_getcombobox_promotions_customer_addauthorizegroup(?)',$table_name,'');
	    $this->view->itemcode = $result[0];
	    $this->view->formdata['txtgrp_num']= ($result[1][0]['Auto_increment'] == '') ? '1' : $result[1][0]['Auto_increment'];
	    
	}
	$item_array 	  = $result[2];
	$array = array();
	for($i=0;$i<count($item_array);$i++)
	{
	    $array[$item_array[$i]['actualitemcode']] = $item_array[$i];
	}	
	$this->view->item_info 	= $array;	
    }
    /**
    * @name       assignmentitemgrid
    * @since      10-04-2012
    * @version    Release: 1
    * @author     Jinal <jinal@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This action is use for add item grid
    */
    public function assignmentitemgridAction()
    {
		$this->view->params = $params = $this->getRequest()->getParams();
		$this->view->formdata = $formdata = $this->_request->getPost();
	
		// For Alternate Code Status.
		$cpanel				= $this->SFA_Comman->getaltcodestatus();
		$altcode_status		= $cpanel["Use Alternate Code"]['status'];
		
		$Settings_NameSpace = new Zend_Session_Namespace('Settings');		
		$tmp = $Settings_NameSpace->cpanel['Fixed Qualification/Fixed Assignment']['status'];
		if(!$tmp)
		$tmp = $Settings_NameSpace->cpanel['Ranged Qualification on Fixed Assignment']['status'];
		
		$this->view->add_quantity = $tmp;
		
		if($altcode_status) {
			$columns_array 	= array('im.alternatecode','im.itemshortdescription','FORMAT(pgd.promocaseprice,'.$this->decimalplaces.') as promocaseprice','FORMAT(pgd.promopcprice,'.$this->decimalplaces.') as promopcprice','primary_key AS edit_del_primary_id');
			$columns_show 	=  array($this->translate->_('Alternate Code'),$this->translate->_('Item Description'),$this->translate->_('Promo Case Price'),$this->translate->_('Promo Pcs Price'));
		} else {
			$columns_array 	= array('im.actualitemcode','im.itemshortdescription','FORMAT(pgd.promocaseprice,'.$this->decimalplaces.') as promocaseprice','FORMAT(pgd.promopcprice,'.$this->decimalplaces.') as promopcprice','primary_key AS edit_del_primary_id');
			$columns_show 	=  array($this->translate->_('Item Code'),$this->translate->_('Item Description'),$this->translate->_('Promo Case Price'),$this->translate->_('Promo Pcs Price'));
		}
		if($this->css == 'ar_') {
			$columns_array[1]	= 'im.arbitemshortdescription AS itemshortdescription';
		}
		if($tmp)
		{
			$columns_array[4] 	= 'pgd.itemqty';
			$columns_show[4]	= $this->translate->_('Item Quantity');
            $columns_array[5] 	= 'primary_key AS edit_del_primary_id';
		}
		
		//To get value of group number and listed out inner grid.
		$ex_param = "";
		if(isset($params["key"]) && $params["key"]>0){
			 $ex_param = "/key/".$params["key"];
		}
	
		if(isset($formdata["hdnid"]) && $formdata["hdnid"]>0){
			 $ex_param = "/key/".$formdata["hdnid"];
			 $params['key'] = $formdata["hdnid"];
		}
	
		// DELETE THE RECORD
		if($params["delete"]=="yes"){
			// itemmaster (table name), ActualItemCode (prymary key)
			$paramarry = array();	    
			$paramarry[1] = $params['id'];
			$r_delete = $this->SFA_Comman->executequery('CALL sp_delete_promotions_customer_authorizegroupitemgrid(?)',$paramarry,'');
	
			SFA_Message::setMsg($this->translate->_('Delete Record'));
		}
		if($formdata["add"]=="yes"){
			if(!empty($formdata['txtgrp_num']) && !empty($formdata['txtgrp_desc']) && !empty($formdata['ddlitem_code'])){
			$param_array = array();
			$param_array[1] = trim($formdata['txtgrp_num']); 	//groupnumber
			$param_array[2] = trim($formdata['txtgrp_desc']);	//groupdescription
			$param_array[3] = trim($formdata['txtgrp_arb']);	//arbgroupdescription
			$param_array[4] = trim($formdata['ddlitem_code']);	//itemcode - productgroupdetail
			$param_array[5] = 2;					//grouptype
			$param_array[6] = $this->currentUser->username;		//created
			$param_array[7] = $formdata['txtpromo_pc_price'];	//promopcprice
			$param_array[8] = $formdata['txtpromo_case_price'];	//promocaseprice
			$param_array[9] = trim($formdata["txtquantity"]); //promocaseprice
	
			$returnval = $this->SFA_Comman->executequery('CALL sp_add_promotions_customer_addauthorizegroup(?,?,?,?,?,?,?,?)',$param_array,'');
			$last_id = $returnval[0][0];
			
			if($last_id['duplicate'] == 'duplicate'){
					SFA_Message::setErrorMsg($this->translate->_('Item Code is already Exist.'));
			}elseif(isset($last_id['var_groupnumber']) && $last_id['var_groupnumber']>0){
				SFA_Message::setMsg($this->translate->_('New Record'));
			}
			}
		}
		
		// IF EXTRA PARAMS ARE REQUIRED
		$pagingparams = array(
					 "show_grid_heading" => false,
					 "grid_heading_message" => $this->translate->_('Overview'),
					 "show_records_per_page" => isset($formdata["show_records_per_page"])?$formdata["show_records_per_page"]:10,
					 "show_searchbox" => false,
					 "show_selectbox" => false,
					 "show_editlink" => false,
					 "show_deletelink" => true,
					 "deletelink" => array("/account/index/deletejourneyplan/id/#pattern#/delete/yes".$ex_param,"#pattern#"),
					 "currentlink" => array("/promotions/customer/authorizegroupitemgrid".$ex_param),
					 "deletelink" => array("/id/#pattern#/delete/yes","#pattern#"),
					 "show_deleteall" => false,
					 "primaryid" => "primary_key",
					 "fetch_columns_inquery" => $columns_array,
					 "show_columns" => $columns_show,			     
					 "nodata_message" => $this->translate->_('No Record(s) Found')
					 );
					 
		$pagingshow = new SFA_Ajaxpaging($pagingparams);
		
		// call common function of grid class
		$get_return_vals = $pagingshow->commnfunc();
		
		$param_array = array();
	
		// call the stored procedure for fetch the data  
		$param_array[1] = '';
		$param_array[2] = $get_return_vals['order_columns_name'];
		$param_array[3] = $get_return_vals['order_type'];
		$param_array[4] = $get_return_vals['offset'];
		$param_array[5] = (int)$get_return_vals['show_records_per_page'];
		$param_array[6] = implode(", ",$columns_array);
		$param_array[7] = ' AND pgh.grouptype = 2 AND pgh.groupnumber ='.$params['key'].' ';
		
		//SFA_Comman::pre($param_array);
	
		// called stored procedure for counter
		$result = $this->SFA_Comman->executequery('CALL sp_get_promotions_customer_authorizegroupitemgrid(?,?,?,?,?,?,?)',$param_array,'');
	
		$data_arr["count"] = $result[0][0]['counter'];
		$data_arr["data"][0] = $result[1];
	
		$this->view->pagegridshow =  $pagingshow->summary_showdatagrid($data_arr);
		$this->view->addScriptPath(APPLICATION_PATH."/theme/default/datagrid/");
	
		$this->render("ajaxgrid");
    
    }

    /**
    * @name       customerpromoAction
    * @since      30-01-2012
    * @version    Release: 1
    * @author     HD <hiren.d@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This action is use for the display assignment Group
    */
    public function customerpromoAction()
    {
	$this->view->params = $params = $this->getRequest()->getParams();
        $this->view->formdata = $formdata = $this->_request->getPost();

	if($formdata["hdDelete"]==1)
            SFA_Message::setMsg($this->translate->_('Delete Record'));

	$this->view->title	= $this->translate->_('Customer Promotion (STD)');
	$promotion_key		= $this->translate->_('Promotion Key');
	$desc			= $this->translate->_('Description');
	$st_date		= $this->translate->_('Start Date');
	$end_date		= $this->translate->_('End Date');
	$status			= $this->translate->_('Status');

	$pagingparams = array(
			     "show_grid_heading" => true,
			     "grid_heading_message" => $this->translate->_('Overview'),
			     "show_records_per_page" => isset($formdata["show_records_per_page"])?$formdata["show_records_per_page"]:10,
			     "show_searchbox" => true, "searchbox_value" => isset($formdata["grid_search_text"])?$formdata["grid_search_text"]:"",
			     "show_selectbox" => true,
			     "show_editlink" => true,
			     "show_deletelink" => false,
			     "show_deleteall" => false,
			     "primaryid" => "promo.PromotionKey",
			     "status_cols" => array(
						    array(
							"cols_name" => "Status",
							"status_change" => array("0"=>"Inactive","1"=>"Active")
							)
						    ),
			     "editlink" => array("/promotions/index/addcustomerpromo/id/#pattern#/edit/yes/","#pattern#"),
			     "nodata_message" => $this->translate->_('No Record(s) Found')
			     );

	$pagingshow = new SFA_Pagingquery($pagingparams);
	$pagingshow->from(array('promo' => 'promotioncontrol'),
		    array('promo.PromotionKey','promo.PromotionDescription','DATE_FORMAT(promo.StartDate,"%d/%m/%Y")','DATE_FORMAT(promo.EndDate,"%d/%m/%Y")','promo.Status'));
	$pagingshow->where('promo.PromotionPlanNumber="1"');
	$columns_show  = array($promotion_key,$desc,$st_date,$end_date,$status);

	$this->view->pagegridshow =  $pagingshow->summary_showdatagrid($columns_show,'',$result);
	$this->view->addScriptPath(APPLICATION_PATH."/theme/default/datagridquery/");
    }
    /**
    * @name       addqualificationgrpAction
    * @since      26-01-2012
    * @version    Release: 1
    * @author     HD <hiren.d@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This action is use for the add qualififacation group
    */
    public function addcustomerpromoAction()
    {
	$this->view->params = $params = $this->getRequest()->getParams();
        $this->view->formdata = $formdata = $this->_request->getPost();


	$this->view->allData  =  $this->getRequest()->getParams();

	$this->view->css 		= $this->translate->_('CSS');
	$this->view->select 		= $this->translate->_('Select');
	$this->view->missonefld		= $this->translate->_('Missed One Field');
	$this->view->youmissed		= $this->translate->_('You Missed');
	$this->view->highlated		= $this->translate->_('Fields. They have been highlighted.');

	$promo_plan			= $this->translate->_('Promo Plan');
	$start_date			= $this->translate->_('Start Date');
	$end_date			= $this->translate->_('End Date');
	$desc				= $this->translate->_('Description');
	$quali_grp			= $this->translate->_('Qualification Group');
	$assign_grp			= $this->translate->_('Assignment Group');

	$promotional_plan = array();
	$promotional_plan[0]['id'] 	= 1;
	$promotional_plan[0]['val'] 	= 'Standard Promotion';
	$promotional_plan[1]['id'] 	= 2;
	$promotional_plan[1]['val'] 	= 'Fixed Promotion';
	$this->view->promotional_plan	= $promotional_plan;


	$pagingparams = array(
			     "show_grid_heading" => false,
			     "grid_heading_message" => $this->translate->_('Overview'),
			     "show_records_per_page" => isset($formdata["show_records_per_page"])?$formdata["show_records_per_page"]:10,
			     "show_searchbox" => false,
			     "show_selectbox" => false,
			     "show_editlink" => true,
			     "show_deletelink" => true,
			     "deletelink" => array("/account/index/deletejourneyplan/id/#pattern#/delete/yes/","#pattern#"),
			     "show_deleteall" => false,
			     "primaryid" => "promo.PromotionPlanNumber",
			     "editlink" => array("/promotions/index/customerpromoplan/id/#pattern#&amp;iframe=true&amp;width=700&amp;height=600&amp;edit&amp;yes/","#pattern#"),
			     "nodata_message" => $this->translate->_('No Record(s) Found')
			     );

	$pagingshow = new SFA_Pagingquery($pagingparams);
	$pagingshow->from(array('promo' => 'promotioncontrol'),
		    array('promo.PromotionPlanNumber','DATE_FORMAT(promo.StartDate,"%d/%m/%Y")','DATE_FORMAT(promo.EndDate,"%d/%m/%Y")','promo.PromotionDescription','promo.QualificationGroup','promo.AssignmentGroup'));
	$columns_show  = array($promo_plan,$start_date,$end_date,$desc,$quali_grp,$assign_grp);
	$this->view->pagegridshow =  $pagingshow->summary_showdatagrid($columns_show,'',$result);
	$this->view->addScriptPath(APPLICATION_PATH."/theme/default/datagridquery/");


	if(count($formdata) > 0) {

	    if($formdata['hdnid'] > 0)
		SFA_Message::setMsg($this->translate->_('Update Record'));
	    else
		SFA_Message::setMsg($this->translate->_('New Record'));

	    $this->_helper->redirector('customerpromo', 'index', 'promotions');
	}

    }

    /**
    * @name       customerpromoplanAction
    * @since      30-01-2012
    * @version    Release: 1
    * @author     HD <hiren.d@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This action is use for the add customer promotion
    */
    public function customerpromoplanAction()
    {
	$this->view->params = $params = $this->getRequest()->getParams();
        $this->view->formdata = $formdata = $this->_request->getPost();

	$this->_helper->layout->disableLayout();
	$this->_helper->layout->setLayout('popup');

	$this->view->itemgrid    = $this->view->BaseUrl("/promotions/index/promotionrangeitemgrid".$ex_param);

	$this->view->allData  =  $this->getRequest()->getParams();



	$dd_array = array();
	$dd_array[0]['id']  = 1;
	$dd_array[0]['val'] = '1    ----    ALL SKU(S)';
        $this->view->quali_grp = $dd_array;

        $asign_group = array();
        $asign_group[0]['id']  = 2;
	$asign_group[0]['val'] = '2    ----    ALL SKU(S)';
	$this->view->assign_grp = $asign_group;

        //promotion type array
        $promotionType = array();
        $promotionType[0]['id'] 	= 1;
	$promotionType[0]['val'] 	= '1- Amount off Line Item';
	$promotionType[1]['id'] 	= 2;
	$promotionType[1]['val'] 	= '2- Percent off Line Item';
        $promotionType[2]['id'] 	= 3;
	$promotionType[2]['val'] 	= '3- Net Price Line Item';
        $promotionType[3]['id'] 	= 4;
	$promotionType[3]['val'] 	= '5- Amount off Invoice';
        $promotionType[4]['id'] 	= 5;
	$promotionType[4]['val'] 	= '6- Percent off Invoice';
        $promotionType[5]['id'] 	= 6;
	$promotionType[5]['val'] 	= '7- Free Goods';
        $this->view->promotion_type	= $promotionType;

        $rangeBasis = array();
        $rangeBasis[0]['id'] 	= 1;
	$rangeBasis[0]['val'] 	= 'No Qualification (Default)';
	$rangeBasis[1]['id'] 	= 2;
	$rangeBasis[1]['val'] 	= 'Qualification On Quantity';
        $rangeBasis[2]['id'] 	= 3;
	$rangeBasis[2]['val'] 	= 'Qualification On Amount';
        $this->view->range_basis = $rangeBasis;

        $amountBasis = array();
        $amountBasis[0]['id'] 	= 1;
	$amountBasis[0]['val'] 	= 'Not Applicable (Default)';
	$amountBasis[1]['id'] 	= 2;
	$amountBasis[1]['val'] 	= 'Wholesale Price';
        $amountBasis[2]['id'] 	= 3;
	$amountBasis[2]['val'] 	= 'Current Net Price';
        $this->view->amount_basis = $amountBasis;

        $exclutionoption = array();
        $exclutionoption[0]['id'] 	= 1;
	$exclutionoption[0]['val'] 	= 'Not Applicable (Default)';
	$exclutionoption[1]['id'] 	= 2;
	$exclutionoption[1]['val'] 	= 'Exclude Items In Assignment Group From Further Promotion';

        $this->view->exclution_option = $exclutionoption;


	if(count($formdata) > 0) {

	    if($formdata['hdnid'] > 0)
		SFA_Message::setMsg($this->translate->_('Update Record'));
	    else
		SFA_Message::setMsg($this->translate->_('New Record'));

	    $this->_helper->redirector('addcustomerpromo', 'index', 'promotions');
	}

    }
    /**
    * @name       promotionrangeitemgridAction
    * @since      22-02-2012
    * @version    Release: 1
    * @author     HD <hiren.d@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This action is use for add item grid
    */
    public function promotionrangeitemgridAction()
    {
	$this->view->params = $params = $this->getRequest()->getParams();

	$range_low	= $this->translate->_('Range Low');
	$range_high	= $this->translate->_('Range High');
	$repeat_range	= $this->translate->_('Repeating Range');
	$promo_amt	= $this->translate->_('Promotion Amount');

	// DELETE THE RECORD
	if($params["delete"]=="yes"){
	    // itemmaste (table name), ActualItemCode (prymary key)
	    $this->SFA_Model_Index->delete_row('user','id',$params["id"]);
	    SFA_Message::setMsg($this->translate->_('Delete Record'));
	}

	// UPDATE THE RECORD
	if($params["update"]=="yes"){
	    $updateData["status"] = $_GET["status"];

	    // itemmaste (table name), ActualItemCode (prymary key)
	    $this->SFA_Model_Index->edit_row('user','id',$params["id"],$updateData);
	    SFA_Message::setMsg($this->translate->_('Update Record'));
	}

	// IF EXTRA PARAMS ARE REQUIRED
	$ex_param = "";
	if(isset($params["key"]) && $params["key"]>0)
	     $ex_param = "/key/".$params["key"];

	$pagingparams = array(
			     "show_grid_heading" => false,
			     "grid_heading_message" => $this->translate->_('Overview'),
			     "show_records_per_page" => 10,
			     "show_searchbox" => false,
			     "show_selectbox" => false,
			     "show_editlink" => true,
			     "show_deletelink" => true,
			     "deletelink" => array("/account/index/deletejourneyplan/id/#pattern#/delete/yes/","#pattern#"),
			     "currentlink" => array("/promotions/index/promotionrangeitemgrid".$ex_param),
			     "deletelink" => array("/id/#pattern#/delete/yes","#pattern#"),
			     "editlink" => array("/id/#pattern#/edit/yes","#pattern#"),
			     "show_deleteall" => false,
			     "primaryid" => "usr.id",
			     //"editlink" => array("/promotions/advance/promotionrange?id&amp;#pattern#&amp;iframe=true&amp;width=600&amp;height=300&amp;edit&amp;yes","#pattern#"),
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
		    array('usr.status','ROUND(RAND(2),2)*2','usr.id','ROUND(RAND(3),10)'));
	$columns_show  = array($range_low,$range_high,$repeat_range,$promo_amt);
	$this->view->pagegridshow =  $pagingshow->summary_showdatagrid($columns_show,'',$result);
	$this->view->addScriptPath(APPLICATION_PATH."/theme/default/datagridquery/");

	$this->render("ajaxgrid");
    }
    /**
    * @name       customerpricingAction
    * @since      31-01-2012
    * @version    Release: 1
    * @author     HD <hiren.d@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This action is use for display customer pricing
    */
    public function customerpricingAction()
    {
	$this->view->params = $params = $this->getRequest()->getParams();
        $this->view->formdata = $formdata = $this->_request->getPost();

	if($formdata["hdDelete"]==1)
            SFA_Message::setMsg($this->translate->_('Delete Record'));

	$this->view->title	= $this->translate->_('Customer Prices (STD)');
	$pricing_key		= $this->translate->_('Pricing Plan Key');
	$desc			= $this->translate->_('Description');
	$status			= $this->translate->_('Status');




	$pagingparams = array(
			     "show_grid_heading" => true,
			     "grid_heading_message" => $this->translate->_('Overview'),
			     "show_records_per_page" => isset($formdata["show_records_per_page"])?$formdata["show_records_per_page"]:10,
			     "show_searchbox" => true, "searchbox_value" => isset($formdata["grid_search_text"])?$formdata["grid_search_text"]:"",
			     "show_selectbox" => true,
			     "show_editlink" => true,
			     "show_deletelink" => false,
			     "show_deleteall" => false,
			     "primaryid" => "price.PricingPlanKey",
			//     "status_cols" => array(
			//			    array(
			//				"cols_name" => "status",
			//				"status_change" => array("0"=>"Inactive","1"=>"Active")
			//				)
			//			    ),
			     "editlink" => array("/promotions/index/addcustomerpricing/id/#pattern#/edit/yes/","#pattern#"),
			     "nodata_message" => $this->translate->_('No Record(s) Found')
			     );

	$pagingshow = new SFA_Pagingquery($pagingparams);
	$pagingshow->from(array('price' => 'customerpricingplan'),
		    array('price.PricingPlanKey','price.Description','CONCAT("Active")'));
	$columns_show  = array($pricing_key,$desc,$status);

	$this->view->pagegridshow =  $pagingshow->summary_showdatagrid($columns_show,'',$result);
	$this->view->addScriptPath(APPLICATION_PATH."/theme/default/datagridquery/");
    }
    /**
    * @name       addcustomerpricingAction
    * @since      31-01-2012
    * @version    Release: 1
    * @author     HD <hiren.d@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This action is use for display customer pricing
    */
    public function addcustomerpricingAction()
    {
	$this->view->params = $params = $this->getRequest()->getParams();
        $this->view->formdata = $formdata = $this->_request->getPost();

	$this->view->title	= $this->translate->_('Customer Pricing');
	$pricing_key		= $this->translate->_('CustomerPricing Key');
	$desc			= $this->translate->_('Description');
	$desc_arb		= $this->translate->_('Description ('.$this->sec_lang.')');



	$pagingparams = array(
			     "show_grid_heading" => false,
			     "grid_heading_message" => $this->translate->_('Overview'),
			     "show_records_per_page" => isset($formdata["show_records_per_page"])?$formdata["show_records_per_page"]:10,
			     "show_searchbox" => false,
			     "show_selectbox" => true,
			     "show_editlink" => true,
			     "show_deletelink" => true,
			     "deletelink" => array("/account/index/deletejourneyplan/id/#pattern#/delete/yes/","#pattern#"),
			     "show_deleteall" => false,
			     "primaryid" => "price.PricingPlanKey",
			    //     "status_cols" => array(
			    //			    array(
			    //				"cols_name" => "status",
			    //				"status_change" => array("0"=>"Inactive","1"=>"Active")
			    //				)
			    //			    ),
			     "editlink" => array("/promotions/index/customerpriceplan/id/#pattern#&amp;iframe=true&amp;width=800&amp;height=700&amp;edit&amp;yes/","#pattern#"),
			     "nodata_message" => $this->translate->_('No Record(s) Found')
			     );

	$pagingshow = new SFA_Pagingquery($pagingparams);

	$pagingshow->from(array('price' => 'customerpricingplan'),
		    array('price.PricingPlanKey','price.Description','price.ArbDescription'));
	$pagingshow->where('price.PricingPlanKey = 1');
	$columns_show  = array($pricing_key,$desc,$desc_arb);

	$this->view->pagegridshow =  $pagingshow->summary_showdatagrid($columns_show,'',$result);
	$this->view->addScriptPath(APPLICATION_PATH."/theme/default/datagridquery/");

	if(count($formdata) > 0) {
	     if($formdata['hdnid'] > 0){
		SFA_Message::setMsg($this->translate->_('Update Record'));
             }else{
		SFA_Message::setMsg($this->translate->_('New Record'));
             }
             $this->_helper->redirector('customerpricing', 'index', 'promotions');
	}
    }
    /**
    * @name       customerpriceplanAction
    * @since      31-01-2012
    * @version    Release: 1
    * @author     HD <hiren.d@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This action is use for the add customer price plan
    */
    public function customerpriceplanAction()
    {

	$this->view->params = $params = $this->getRequest()->getParams();
        $this->view->formdata = $formdata = $this->_request->getPost();

	$this->_helper->layout->disableLayout();
	$this->_helper->layout->setLayout('popup');

	$this->view->formdata = $formdata = $this->_request->getPost();

	$this->view->allData  =  $this->getRequest()->getParams();

	$detail_key		= $this->translate->_('Detail Key');
	$desc			= $this->translate->_('Description');
	$desc_arb		= $this->translate->_('Description ('.$this->sec_lang.')');
	$st_date		= $this->translate->_('Start Date');
	$end_date		= $this->translate->_('End Date');

	$pagingparams = array(
			     "show_grid_heading" => false,
			     "grid_heading_message" => $this->translate->_('Overview'),
			     "show_records_per_page" => 10000,
			     "show_searchbox" => false,
			     "show_selectbox" => false,
			     "show_editlink" => true,
			     "show_deletelink" => true,
			     "deletelink" => array("/account/index/deletejourneyplan/id/#pattern#/delete/yes/","#pattern#"),
			     "show_deleteall" => false,
			      "primaryid" => "price.PricingPlanKey",
			     "editlink" => array("/promotions/index/customerpriceheader/id/#pattern#&amp;iframe=true&amp;width=850&amp;height=650&amp;edit&amp;yes/","#pattern#"),
			     "nodata_message" => $this->translate->_('No Record(s) Found')
			     );

	$pagingshow = new SFA_Pagingquery($pagingparams);
	$pagingshow->from(array('price' => 'customerpricingplan'),
		    array('price.PricingPlanKey','price.Description','price.ArbDescription','DATE_FORMAT(NOW(),"%d/%m/%Y")','DATE_FORMAT(DATE_ADD(NOW(), INTERVAL 1 YEAR),"%d/%m/%Y")'));
	$pagingshow->where('price.PricingPlanKey = 1');
	$columns_show  = array($detail_key,$desc,$desc_arb,$st_date,$end_date);

	$this->view->pagegridshow =  $pagingshow->summary_showdatagrid($columns_show,'',$result);
	$this->view->addScriptPath(APPLICATION_PATH."/theme/default/datagridquery/");

	if(count($formdata) > 0) {

	    if($formdata['hdnid'] > 0)
		SFA_Message::setMsg($this->translate->_('Update Record'));
	    else
		SFA_Message::setMsg($this->translate->_('New Record'));

	    $this->_helper->redirector('customerpromo', 'index', 'promotions');
	}


    }
    /**
    * @name       customerpriceheaderAction
    * @since      31-01-2012
    * @version    Release: 1
    * @author     HD <hiren.d@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This action is use for the add customer price header
    */
    public function customerpriceheaderAction()
    {
	$this->view->params = $params = $this->getRequest()->getParams();
        $this->view->formdata = $formdata = $this->_request->getPost();

	$this->_helper->layout->disableLayout();
	$this->_helper->layout->setLayout('popup');

	$this->view->allData  =  $this->getRequest()->getParams();

	$detail_key		= $this->translate->_('Detail Key');
	$desc			= $this->translate->_('Description');
	$desc_arb		= $this->translate->_('Description ('.$this->sec_lang.')');
	$st_date		= $this->translate->_('Start Date');
	$end_date		= $this->translate->_('End Date');

	$pagingparams = array(
			     "show_grid_heading" => false,
			     "grid_heading_message" => $this->translate->_('Overview'),
			     "show_records_per_page" => 10000,
			     "show_searchbox" => false,
			     "show_selectbox" => false,
			     "show_editlink" => true,
			     "show_deletelink" => true,
			     "deletelink" => array("/account/index/deletejourneyplan/id/#pattern#/delete/yes/","#pattern#"),
			     "show_deleteall" => false,
			     "primaryid" => "usr.id",
			     "editlink" => array("/promotions/index/customerpriceplan/id/#pattern#&amp;iframe=true&amp;width=850&amp;height=650&amp;edit&amp;yes/","#pattern#"),
			     "nodata_message" => $this->translate->_('No Record(s) Found')
			     );

	$pagingshow = new SFA_Pagingquery($pagingparams);
	$pagingshow->from(array('usr' => 'user'),
		    array('usr.id','usr.first_name','usr.last_name','DATE(usr.created) as st','DATE(usr.created) as end'));
	$pagingshow->joinLeft(array('type'=>'user_type'),'usr.user_type_id = type.id',array(''));
	$pagingshow->where('type.is_admin="2"');
	$columns_show  = array($detail_key,$desc,$desc_arb,$st_date,$end_date);

	$this->view->pagegridshow =  $pagingshow->summary_showdatagrid($columns_show,'',$result);
	$this->view->addScriptPath(APPLICATION_PATH."/theme/default/datagridquery/");

	if(count($formdata) > 0) {

	    if($formdata['hdnid'] > 0)
		SFA_Message::setMsg($this->translate->_('Update Record'));
	    else
		SFA_Message::setMsg($this->translate->_('New Record'));

	    $this->_helper->redirector('customerpriceplan', 'index', 'promotions');
	}

    }
     /**
    * @name       promotionrangeAction
    * @since      14-02-2012
    * @version    Release: 1
    * @author     PM <pankit@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This action is use for the advance promotion range
    */
    public function promotionrangeAction()
    {
	$this->view->params = $params = $this->getRequest()->getParams();
        $this->view->formdata = $formdata = $this->_request->getPost();

	$this->_helper->layout->disableLayout();
	$this->_helper->layout->setLayout('popup');

	$this->view->formdata = $formdata = $this->_request->getPost();

	$this->view->allData  =  $this->getRequest()->getParams();

	$promo_low		= $this->translate->_('Range Low');
	$range_high		= $this->translate->_('Range High');
	$repeat_range		= $this->translate->_('Repeating Range');
	$promo_amt		= $this->translate->_('Promotion Amount');

        if(count($formdata) > 0) {

	    if($formdata['hdnid'] > 0)
		SFA_Message::setMsg($this->translate->_('Update Record'));
	    else
		SFA_Message::setMsg($this->translate->_('New Record'));

	    $this->_helper->redirector('customerpromoplan', 'index', 'promotions');
	}
    }
}
