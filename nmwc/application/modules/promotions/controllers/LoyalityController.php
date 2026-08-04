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
class Promotions_LoyalityController extends Promotions_Library_Controller_Action_Abstract
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
	
	$type_data 		= array();
	$type_data[0]['id']	= 0;
	$type_data[0]['val']	= 'Amount';
	$type_data[1]['id']	= 1;
	$type_data[1]['val']	= 'Qty';
	
	$this->view->type_data= $type_data;
	
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
    * @name       pricingplanAction
    * @since      03-02-2012
    * @version    Release: 1
    * @author     HD <hiren.d@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This action is use for the display promotion plan
    */
    public function loyalityplanAction()
    {
	$this->view->params = $params = $this->getRequest()->getParams();
        $this->view->formdata = $formdata = $this->_request->getPost();
	$Common_NameSpace = new Zend_Session_Namespace('Commonpricingplan');
	
	if($formdata["hdDelete"]==1)
	{
	    $ids = implode(',',$formdata['chk']);
	    $param_array 	= array();
	    $param_array[1]	= $ids;
	    $param_array[2]	= $this->currentUser->username;
	    
	    $result 	= $this->SFA_Comman->executequery('CALL sp_delete_promotions_advancepricing_pricingplan(?,?)',$param_array,'');
	    
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
	
	$filter_data 		= array();
	$filter_data[0]['id']	= 0;
	$filter_data[0]['val']	= 'All';
	$filter_data[1]['id']	= 1;
	$filter_data[1]['val']	= 'Special Price';
	$filter_data[2]['id']	= 2;
	$filter_data[2]['val']	= 'Customer';
	$filter_data[3]['id']	= 3;
	$filter_data[3]['val']	= 'Salesman';
	$filter_data[4]['id']	= 4;
	$filter_data[4]['val']	= 'Depot';
	$this->view->filter_data= $filter_data;
	
	$columns_array = array('loyaltyplanid','description','arbdescription','active');
	$columns_show  = array($this->translate->_('Loyality Plan'),$this->translate->_('Description'),$this->translate->_('Description ('.$this->sec_lang.')'),$this->translate->_('Status'));
	
	// IF EXTRA PARAMS ARE REQUIRED
	if($params["ddlfilter"] > 0)
	    $ddl_filter = $params["ddlfilter"];
	elseif($formdata["ddlfilter"] > 0)
	    $ddl_filter = $formdata["ddlfilter"];
	
	$ex_param = "";
	//unset filter values
	if(strpos($_SERVER['HTTP_REFERER'],("/".$params['module']."/".$params['controller']."/".$params['action']))===false){
	    Zend_Session:: namespaceUnset('Commonpricingplan');
	}

	if($Common_NameSpace->ddlfilter > 0){
	    if(isset($formdata["ddlfilter"])){
		$this->view->formdata['ddlfilter']= $formdata["ddlfilter"];
	    }else{
		$this->view->formdata['ddlfilter'] = $Common_NameSpace->ddlfilter;
	    }
	}

	if($ddl_filter > 0){
	    $additional_where_condition = array();
	    $ex_param = "/ddlfilter/".$ddl_filter;
	    $additional_where_condition[] = ' (	type = "'.$ddl_filter.'" )';
	    $this->view->formdata['ddlfilter'] = $ddl_filter;
	    
	    $Common_NameSpace->ddlfilter = $ddl_filter;
	    $Common_NameSpace->ddlfilter;
	}
	
	$pagingparams = array(
			    "show_grid_heading" => true,
			    "grid_heading_message" => $this->translate->_('Overview'),
				"pagename" => $this->translate->_('Loyality Plan'),
			    "show_records_per_page" => isset($formdata["show_records_per_page"])?$formdata["show_records_per_page"]:10,
			    "show_searchbox" => true,			    
			    "searchbox_value" => isset($formdata["grid_search_text"])?$formdata["grid_search_text"]:"",
			    "show_selectbox" => true,
			    "show_editlink" => true,
			    "selected_list" => $checked,
			    "show_deletelink" => false,
			    "deletelink" => array("/promotions/loyality/loyalityplan/id/#pattern#/delete/yes/","#pattern#"),
			    "show_deleteall" => false,
			    "primaryid" => "loyaltyplanid",
			    "status_cols" => array(
						   array(
						       "cols_name" => "active",
						       "status_change" => array("0"=>"Inactive","1"=>"Active")
						       )
						   ),
			    "editlink" => array("/promotions/loyality/addloyalityplan/id/#pattern#/edit/yes/","#pattern#"),
			    "fetch_columns_inquery" => $columns_array,
			    "show_columns" => $columns_show,
			    "nodata_message" => $this->translate->_('No Record(s) Found'),
			    "additional_where" => $additional_where_condition
			    );
	
     if(!$this->checkaccess("update"))
    {
        $pagingparams["show_editlink"] = false;
    }
	/*if(count($additional_where_condition) > 0){
	    $get_return_vals['where_condition'] = " AND type = ".$ddl_filter."'";
	}*/
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
	$result	= $this->SFA_Comman->executequery('CALL sp_get_promotions_loyality_loyalityplan(?,?,?,?,?,?,?,?)',$param_array,'',$downloadCSV,$printData,2,$pagingparams);
	
	$data_arr["count"] 	= $result[0][0]['counter'];
	$data_arr["data"][0] 	= $result[1];
	
	//echo "<pre>"; print_r($data_arr); exit;
	// pass the data in summary_showdatagrid() function & create a final variable for view
	$this->view->pagegridshow =  $pagingshow->summary_showdatagrid($data_arr);
	
	$this->view->addScriptPath(APPLICATION_PATH."/theme/default/datagrid/");
	
    }
    /**
    * @name       addpricingplanAction
    * @since      26-01-2012
    * @version    Release: 1
    * @author     HD <hiren.d@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This action is use for the add promotion plan
    */
    public function addloyalityplanAction()
    {
	$this->view->params = $params = $this->getRequest()->getParams();
        $this->view->formdata = $formdata = $this->_request->getPost();

	$this->view->returnUrl = $_SERVER["HTTP_REFERER"];
	
	
	$filter_data 		= array();
	$filter_data[0]['id']	= 0;
	$filter_data[0]['val']	= 'All';
	$filter_data[1]['id']	= 1;
	$filter_data[1]['val']	= 'Special Price';
	$filter_data[2]['id']	= 2;
	$filter_data[2]['val']	= 'Customer';
	$filter_data[3]['id']	= 3;
	$filter_data[3]['val']	= 'Salesman';
	$filter_data[4]['id']	= 4;
	$filter_data[4]['val']	= 'Depot';
	$this->view->filter_data= $filter_data;

	$ex_param = "";
	if(isset($params["id"]) && $params["id"]>0)
	    $ex_param = "/key/".$params["id"];
	


	if($formdata['txtdesc'] != '')
	{
	    $param_array = array();
	    
	    $param_array[1] = $formdata['txtdesc'];	//description
	    $param_array[2] = $formdata['txtdesc_arb'];	//arbdescription
	    $param_array[3] = $formdata['txtrmarks'];	//type
	    $param_array[4] = $formdata['chkstatus'];	//active
	    $param_array[5] = $this->currentUser->username;
	    
	    if($formdata['hdnid'] > 0){
		$param_array[6] = $formdata['hdnid'];	//customerpricingkey
		$result 	= $this->SFA_Comman->executequery('CALL sp_edit_promotion_loyality_addloyalityplan(?,?,?,?,?,?)',$param_array,'');
		$last_id 	= $result[0][0]['last_id'];
		
		SFA_Message::setMsg($this->translate->_('Update Record'));
		
		
			$this->_helper->redirector('loyalityplan', 'loyality', 'promotions');
	    }
	    else{
		$result 	= $this->SFA_Comman->executequery('CALL sp_add_promotion_loyality_addloyalityplan(?,?,?,?,?)',$param_array,'');
	
		echo $last_id 	= $result[0][0]['last_id'];
		
		SFA_Message::setMsg($this->translate->_('New Record'));
		$this->_helper->redirector('addloyalityplan', 'loyality', 'promotions',array('edit'=>'yes',"id"=>$last_id));
	    }

	    
	     
			
	}				
	elseif($params['id'] > 0)
	{
	    $result 	= $this->SFA_Comman->executequery('CALL sp_get_promotion_loyality_addloyalityplan(?)',$params['id'],'');
	    $this->view->country_info	= $result[0];
	    
	    $res['txtplan_key']		= $result[1][0]['customerpricingkey'];
	    $res['txtdesc']		= $result[1][0]['description'];
	    $res['txtdesc_arb']		= $result[1][0]['arbdescription'];
	    $res['ddltype']		= $result[1][0]['type'];
	    $res['chkstatus']		= $result[1][0]['active'];
	    $res['ddlcountry']		= $result[1][0]['country'];
	    $this->view->formdata	= $res;
	    
	    $this->view->item_master_data = $result[2];
	    $item_array 		  = $result[3];
	
	    $array = array();
	    for($i=0;$i<count($item_array);$i++)
	    {
		$array[$item_array[$i]['actualitemcode']] = $item_array[$i];
	    }
	    
	    $this->view->item_info 	= $array;
	    
	    $this->view->itemgrid    = $this->view->BaseUrl("/promotions/loyality/loyalityplanitemgrid".$ex_param);
	    
	}
	else
	{
	    $result 	= $this->SFA_Comman->executequery('CALL sp_get_promotion_loyality_addloyalityplan(?)','0','');
	    $this->view->formdata['txtplan_key'] = ($result[1][0]['Auto_increment'] == '') ? '1' : $result[1][0]['Auto_increment'];
	    $this->view->country_info		 = $result[0];
	    $item_array 			 = $result[2];
	
	    $array = array();
	    for($i=0;$i<count($item_array);$i++)
	    {
		$array[$item_array[$i]['actualitemcode']] = $item_array[$i];
	    }
	    
	    $this->view->item_info 	= $array;
	}
    }
    /**
    * @name       itemgridAction
    * @since      22-02-2012
    * @version    Release: 1
    * @author     HD <hiren.d@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This action is use for add item grid
    */
    public function loyalityplanitemgridAction()
    {
	
	$this->view->params = $params = $this->getRequest()->getParams();
	$this->view->formdata = $formdata = $this->_request->getPost();
	
	// For Alternate Code Status.
	$cpanel				= $this->SFA_Comman->getaltcodestatus();
	$altcode_status		= $cpanel["Use Alternate Code"]['status'];
	
	$item_code			= $this->translate->_('Item Code');
	$qly_code			= $this->translate->_('Qulification Group');
	$type				= $this->translate->_('Type');
	$value				= $this->translate->_('Value');
	$points			= $this->translate->_('Points');
	
	
	
	$columns_array 	=  array('qualificationgroup','type','value','points','primarykey as edit_del_primary_id');
	$columns_show  	= array($qly_code,$type,$value,$points);
	/*if($altcode_status)
	{
		// column to be fetched
		$columns_array 	=  array('i.alternatecode','i.itemshortdescription as itemshortdescription','p.unitspercase as unitspercase','FORMAT(p.salescaseprice,'.$this->decimalplaces.') as salescaseprice','FORMAT(p.salesprice,'.$this->decimalplaces.') as salesprice','FORMAT(p.returncaseprice,'.$this->decimalplaces.') as returncaseprice','FORMAT(p.returnprice,'.$this->decimalplaces.') as returnprice','p.primary_key as edit_del_primary_id');
		$columns_show  	= array($alt_code,$desc,$upc,$sales_price,$sales_case_price,$return_price,$return_case_price);
	}
	else
	{
		// column to be fetched
		$columns_array 	=  array('i.actualitemcode as actualitemcode','i.itemshortdescription as itemshortdescription','p.unitspercase as unitspercase','FORMAT(p.salescaseprice,'.$this->decimalplaces.') as salescaseprice','FORMAT(p.salesprice,'.$this->decimalplaces.') as salesprice','FORMAT(p.returncaseprice,'.$this->decimalplaces.') as returncaseprice','FORMAT(p.returnprice,'.$this->decimalplaces.') as returnprice','p.primary_key as edit_del_primary_id');
		$columns_show  	= array($item_code,$desc,$upc,$sales_price,$sales_case_price,$return_price,$return_case_price);
	}
	
	if($this->css == 'ar_') {
		$columns_array[1]	= 'i.arbitemshortdescription AS itemshortdescription';
	}*/

	
	
	
	// ADDING THE RECORD
	if($formdata["add"]=="yes") {
		
	    $paramarry = array();
	  
	    if(isset($formdata['ddlitem_code']) && isset($formdata['txtstd_value']))
	    {
			$paramarry[1] = $formdata["ddlitem_code"];
			$paramarry[2] = $formdata["ddltype_code"];			
			$paramarry[3] = $formdata["txtstd_value"]; //- 5
			$paramarry[4] = $formdata["txtstd_point"]; //-4 			
			$paramarry[5] = $params["key"];
			$paramarry[6] = $this->currentUser->username;
			
			$r_add = $this->SFA_Comman->executequery('CALL sp_add_promotions_loyality_loyalityplanitemgrid(?,?,?,?,?,?)',$paramarry,'');
			
			if($r_add[0][0]["result"]=="added")
				SFA_Message::setMsg($this->translate->_('New Record'));
			else
				SFA_Message::setErrorMsg($this->translate->_('Duplicate Record'));
	    }
	}
	
	// DELETE THE RECORD
	if($params["delete"]=="yes"){
	    
	     // sp for delete
	    $r_delete = $this->SFA_Comman->executequery('CALL sp_delete_promotions_loyality_loyalityplanitemgrid(?)',array(1=>$params["id"]),'');
            SFA_Message::setMsg($this->translate->_('Delete Record'));
	}

	// UPDATE THE RECORD
	if($params["update"]=="yes"){

	    $updateData["1"] = $params["value"];
	    $updateData["2"] = $params["points"];	    
	    $updateData["3"] = $params["id"];
		$updateData["4"] = $this->currentUser->username;
	    
	    // call sp for edit currencydetail
	    $r_edit = $this->SFA_Comman->executequery('CALL sp_edit_promotions_loyality_loyalityplanitemgrid(?,?,?,?)',$updateData,'');
	    SFA_Message::setMsg($this->translate->_('Update Record'));
	}

	// IF EXTRA PARAMS ARE REQUIRED
	$ex_param = "";
	if(isset($params["key"]) && $params["key"]>0){
	    $additional_where_condition = array();
	    $ex_param = "/key/".$params["key"];
	     $additional_where_condition[] = ' (loyaltyplanid = "'.$params["key"].'" )';
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
			     "primaryid" => "primarykey",
			     "currentlink" => array("/promotions/advance/pricingkeyitemgrid".$ex_param),
			     "deletelink" => array("/id/#pattern#/delete/yes","#pattern#"),
			     "editlink" => array("/id/#pattern#/edit/yes","#pattern#"),
			     "noeditfields" => array('qualificationgroup','type'),
			     "nodata_message" => $this->translate->_('No Record(s) Found'),
			     "fetch_columns_inquery" => $columns_array,
			     "show_columns" => $columns_show,
			     "additional_where" => $additional_where_condition
			     );
	
	// WHEN GRID IS IN EDIT MODE
	if($params["edit"]=="yes"){

	    $pagingparams["editmode"] = true;
	    $pagingparams["editmodeid"] = $params["id"];
	    $pagingparams["editmodevalue"] = "loyaltyplanid";  // put table's prymary key here
	}
	
	$pagingshow = new SFA_Ajaxpaging($pagingparams);
	
	// call common function of grid class
	$get_return_vals = $pagingshow->commnfunc();
	
	//column to be fetched
	
	// call the stored procedure for fetch the data  
	$param_array[1] = '1';
	$param_array[2] = $get_return_vals['order_columns_name'];
	$param_array[3] = $get_return_vals['order_type'];
	$param_array[4] = $get_return_vals['offset'];
	$param_array[5] = (int)$get_return_vals['show_records_per_page'];
	$param_array[6] = '';
	$param_array[7] = strlen($get_return_vals['where_condition'])>0?$get_return_vals['where_condition']:'';

	// called stored procedure for counter
	$rowcount = $this->SFA_Comman->executequery('CALL sp_get_promotions_loyality_loyalityplanitemgrid(?,?,?,?,?,?,?)',$param_array,'');
	$data_arr["count"] = $rowcount[0][0]['counter'];

	// call stored proceddure for data
	$param_array[1] = '';
	$param_array[6] = implode(", ",$pagingparams["fetch_columns_inquery"]);
	
	$data_arr["data"] = $this->SFA_Comman->executequery('CALL sp_get_promotions_loyality_loyalityplanitemgrid(?,?,?,?,?,?,?)',$param_array,'');
	
	$this->view->pagegridshow =  $pagingshow->summary_showdatagrid($data_arr);
	$this->view->addScriptPath(APPLICATION_PATH."/theme/default/datagrid/");

	$this->render("ajaxgrid");
	
    }
    /**
    * @name       pricingkeyAction
    * @since      03-02-2012
    * @version    Release: 1
    * @author     HD <hiren.d@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This action is use for the display oricing key
    */
    public function loyalitykeyAction()
    {
	$this->view->params = $params = $this->getRequest()->getParams();
        $this->view->formdata = $formdata = $this->_request->getPost();

	$columns_array = array('loyaltykeyid','description','arabicdescription','active');
	$columns_show  = array($this->translate->_('Loyality Key'),$this->translate->_('Description'),$this->translate->_('Description ('.$this->sec_lang.')'),$this->translate->_('Status'));
	
	if($formdata["hdDelete"]==1)
	{
	    $ids = implode(',',$formdata['chk']);
	    $param_array 	= array();
	    $param_array[1]	= $ids;
	    $param_array[2]	= $this->currentUser->username;
	    
	    $result 	= $this->SFA_Comman->executequery('CALL sp_delete_promotions_advancepricing_pricingkey(?,?)',$param_array,'');
	    
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

	$pricing_key		= $this->translate->_('Loyalityg Key');
	$description		= $this->translate->_('Description');
	$arb_desc		= $this->translate->_('Description ('.$this->sec_lang.')');
	$status			= $this->translate->_('Status');
    
	$pagingparams = array(
			    "show_grid_heading" => true,
			    "grid_heading_message" => $this->translate->_('Overview'),
			    "show_records_per_page" => isset($formdata["show_records_per_page"])?$formdata["show_records_per_page"]:10,
				"pagename" => $this->translate->_('Loyality Key'),
			    "show_searchbox" => true,
			    "selected_list" => $checked,
			    "searchbox_value" => isset($formdata["grid_search_text"])?$formdata["grid_search_text"]:"",
			    "show_selectbox" => true,
			    "show_editlink" => true,
			    "show_deletelink" => false,
			    "deletelink" => array("/promotions/loyality/loyalityplan/id/#pattern#/delete/yes/","#pattern#"),
			    "show_deleteall" => false,
			    "primaryid" => "loyaltykeyid",
			    "status_cols" => array(
						   array(
						       "cols_name" => "active",
						       "status_change" => array("0"=>"Inactive","1"=>"Active")
						       )
						   ),
			    "editlink" => array("/promotions/loyality/addloyalitykey/id/#pattern#/edit/yes/","#pattern#"),
			    "fetch_columns_inquery" => $columns_array,
			    "show_columns" => $columns_show,
			    "nodata_message" => $this->translate->_('No Record(s) Found'),
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
	$result 	= $this->SFA_Comman->executequery('CALL sp_get_promotions_loyality_loyalitykey(?,?,?,?,?,?,?,?)',$param_array,'',$downloadCSV,$printData,2,$pagingparams);
	$data_arr["count"] 	= $result[0][0]['counter'];
	$data_arr["data"][0] 	= $result[1];
	
	// pass the data in summary_showdatagrid() function & create a final variable for view
	$this->view->pagegridshow =  $pagingshow->summary_showdatagrid($data_arr);
	
	$this->view->addScriptPath(APPLICATION_PATH."/theme/default/datagrid/");	




    }
    /**
    * @name       addpricingkey
    * @since      1-2-2012
    * @version    Release: 1
    * @author     HD <Hiren.d@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This action is use for add pricing key
    */
    public function addloyalitykeyAction()
    {
		$this->view->params = $params = $this->getRequest()->getParams();
        $this->view->formdata = $formdata = $this->_request->getPost();

		$this->view->returnUrl = $_SERVER["HTTP_REFERER"];
	
		$ex_param = "";
		if(isset($params["id"]) && $params["id"]>0)
			$ex_param = "/key/".$params["id"];
		//$this->view->itemgrid    = $this->view->BaseUrl("/promotions/advancepricing/pricingplanitemgrid/".$ex_param);
	
	
		if($formdata['txtdesc'] != '')
		{
			$param_array = array();
			
			$param_array[1] = $formdata['txtdesc'];	//description
			$param_array[2] = $formdata['txtdesc_arb'];	//arbdescription
			$param_array[3] = $formdata['ddlindicator'];//activeindicater
			$param_array[4] = $formdata['txtremark'];//remark
			$param_array[5] = $this->currentUser->username;
			
			if($formdata['hdnid'] > 0){
				$param_array[6] = $formdata['hdnid'];	//customerpricingkey
				
				$result 	= $this->SFA_Comman->executequery('CALL sp_edit_promotions_loyality_addloyalitykey(?,?,?,?,?,?)',$param_array,'');
				$last_id 	= $params["id"];
				SFA_Message::setMsg($this->translate->_('Update Record'));
				
				
				$this->_helper->redirector('loyalitykey', 'loyality', 'promotions');
			}
			else
			{		
				#echo "<pre>"; print_r($param_array); exit;
			
				$result 	= $this->SFA_Comman->executequery('CALL sp_add_promotions_loyality_addloyalitykey(?,?,?,?,?)',$param_array,'');
				$last_id 	= $result[0][0]['result'];
				SFA_Message::setMsg($this->translate->_('New Record'));
				$this->_helper->redirector('addloyalitykey', 'loyality', 'promotions',array('edit'=>'yes',"id"=>$last_id));	
			}
		}				
		elseif($params['id'] > 0)
		{
			$result 	= $this->SFA_Comman->executequery('CALL sp_get_promotions_loyality_addloyalitykey(?)',$params['id'],'');
			$res['txtplan_key']		= $result[0][0]['loyalitykeyid'];
			$res['txtdesc']			= $result[0][0]['description'];
			$res['txtdesc_arb']		= $result[0][0]['arbdescription'];
			$res['ddlindicator']	= $result[0][0]['activeindicator'];
			$this->view->formdata	= $res;
			
			$this->view->itemgrid   = $this->view->BaseUrl("/promotions/loyality/loyalitykeyitemgrid/key/".$params['id']);
			$this->view->itemgrid2   = $this->view->BaseUrl("/promotions/loyality/loyalityplanselectionitemgrid/key/".$params['id']);
			
		   
		}
		else
		{
			$result 	= $this->SFA_Comman->executequery('CALL sp_get_promotions_loyality_addloyalitykey(?)','0','');
			$this->view->formdata['txtplan_key'] = ($result[0][0]['Auto_increment'] == '') ? '1' : $result[0][0]['Auto_increment'];
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
    public function loyalitykeyitemgridAction()
    {
	$this->view->params = $params = $this->getRequest()->getParams();
	$this->view->formdata = $formdata = $this->_request->getPost();
	
	// column to be fetched
	$columns_array 	=  array('l.loyaltyplanid','description','arbdescription','DATE_FORMAT(startdate,"%d-%m-%Y") as startdate','DATE_FORMAT(enddate,"%d-%m-%Y") AS enddate','primarykey as edit_del_primary_id');
	
	$plan_no		= $this->translate->_('Plan #');
	$description		= $this->translate->_('Description');
	$arb_desc		= $this->translate->_('Description ('.$this->sec_lang.')');
	$start_date		= $this->translate->_('Start Date');
	$end_date		= $this->translate->_('End Date');
  	
	$columns_show  = array($plan_no,$description,$arb_desc,$start_date,$end_date);
	
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
		$paramarry[4] = $this->currentUser->username;
		
		$r_add = $this->SFA_Comman->executequery('CALL sp_add_promotions_loyality_loyalitykeyitemgrid(?,?,?,?)',$paramarry,'');
	    
		SFA_Message::setMsg($this->translate->_('Update Record'));
	    }
	  }
	}
	
	// DELETE THE RECORD
	if($params["delete"]=="yes"){
	    
	     // sp for delete
	    $r_delete = $this->SFA_Comman->executequery('CALL sp_delete_promotions_loyality_loyalitykeyitemgrid',array(1=>$params["id"]),'');
            SFA_Message::setMsg($this->translate->_('Delete Record'));
	}

	// UPDATE THE RECORD
	if($params["update"]=="yes"){

	    $updateData["1"] = date("Y-m-d",strtotime($params["startdate"]));
	    $updateData["2"] = date("Y-m-d",strtotime($params["enddate"]));
	    $updateData["3"] = $params["id"];
		$updateData["4"] = $this->currentUser->username;
	    
	    // call sp for edit currencydetail
	    $r_edit = $this->SFA_Comman->executequery('CALL sp_edit_promotions_loyality_loyalitykeyitemgrid(?,?,?,?)',$updateData,'');
	    SFA_Message::setMsg($this->translate->_('Update Record'));
	}

	// IF EXTRA PARAMS ARE REQUIRED
	$ex_param = "";
	if(isset($params["key"]) && $params["key"]>0){
	    $additional_where_condition = array();
	    $ex_param = "/key/".$params["key"];
	     $additional_where_condition[] = ' (loyaltykeyid = "'.$params["key"].'" )';
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
			     "primaryid" => "primarykey",
			     "currentlink" => array("/promotions/loyality/loyalitykeyitemgrid".$ex_param),
			     "deletelink" => array("/id/#pattern#/delete/yes","#pattern#"),
			     "editlink" => array("/id/#pattern#/edit/yes","#pattern#"),
			     "noeditfields" => array('loyaltykeyid','description','arbdescription'),
			     "nodata_message" => $this->translate->_('No Record(s) Found'),
			      "fetch_columns_inquery" => $columns_array,
			     "show_columns" => $columns_show,
			     "additional_where" => $additional_where_condition
			     );
	
	// WHEN GRID IS IN EDIT MODE
	if($params["edit"]=="yes"){

	    $pagingparams["editmode"] = true;
	    $pagingparams["editmodeid"] = $params["id"];
	    $pagingparams["editmodevalue"] = "primarykey";  // put table's prymary key here
	}
	
	$pagingshow = new SFA_Ajaxpaging($pagingparams);
	
	// call common function of grid class
	$get_return_vals = $pagingshow->commnfunc();
	
	// call the stored procedure for fetch the data
	$param_array	= array();
	$param_array[1] = '1';
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
	$rowcount = $this->SFA_Comman->executequery('CALL sp_get_promotions_loyality_loyalitykeyitemgrid(?,?,?,?,?,?,?)',$param_array,'',$downloadCSV,$printData,2,$pagingparams);
	$data_arr["count"] 		= $rowcount[0][0]['counter'];
	$data_arr["data"][0]	= $rowcount[1];
	
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
    public function loyalityplanselectionitemgridAction()
    {
	$this->view->params = $params = $this->getRequest()->getParams();
	$this->view->formdata = $formdata = $this->_request->getPost();
	
	// column to be fetched
	$columns_array 	=  array('loyaltyplanid','description','loyaltyplanid as  edit_del_primary_id');
	
	// column header to be displayed
	$pricing_plan		= $this->translate->_('Loyality Plan');
	$description		= $this->translate->_('Description');
	
	
	$columns_show  = array($pricing_plan,$description);
	
	// gettting the already having the plan number into price key detail
	$selected_price_list = $this->SFA_Comman->executequery('CALL sp_getpricinglist_promotions_loyality_loyalityplanselection',$params["key"],'');
		// price numbers array
	$price_array = explode(",",$selected_price_list[0][0]["plans"]);
	
	// IF EXTRA PARAMS ARE REQUIRED
	/*$ex_param = "";
	if(isset($params["key"]) && $params["key"]>0){
	   $additional_where_condition = array();
	   $ex_param = "/key/".$params["key"];
	    $additional_where_condition[] = ' (primarykey = "'.$params["key"].'" )';
	}*/

	$pagingparams = array(
			     "show_grid_heading" => false,
			     "grid_heading_message" => $this->translate->_('Overview'),
			     "show_records_per_page" => isset($formdata["show_records_per_page"])?$formdata["show_records_per_page"]:10,
			     "show_searchbox" => false,
			     "show_selectbox" => true,
			     "selected_list" => $price_array,
			     "show_editlink" => false,
			     "show_deletelink" => false,
			     "show_deleteall" => false,
			     "primaryid" => "loyalitykey",
			     "currentlink" => array("/promotions/loyalty/loyaltyplanselectionitemgrid".$ex_param),
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
	$param_array	= array();
	$param_array[1] = '1';
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
	$rowcount = $this->SFA_Comman->executequery('CALL sp_get_promotions_loyality_loyalityplanselectionitemgrid(?,?,?,?,?,?,?)',$param_array,'',$downloadCSV,$printData,2,$pagingparams);
	$data_arr["count"] 		= $rowcount[0][0]['counter'];
	$data_arr["data"][0]	= $rowcount[1];
	
	$this->view->pagegridshow_4 =  $pagingshow->summary_showdatagrid($data_arr);
	$this->view->addScriptPath(APPLICATION_PATH."/theme/default/datagrid/");
    }
    
   
    
    /**
    * @name       pricingkeyplan
    * @since      1-2-2012
    * @version    Release: 1
    * @author     HD <Hiren.d@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This action is use for add pricing key plan
    */
    public function loyalitykeyplanAction()
    {
	$this->view->params = $params = $this->getRequest()->getParams();
        $this->view->formdata = $formdata = $this->_request->getPost();

	$this->_helper->layout->setLayout('popup');

	$plan		= $this->translate->_('Plan');
	$description	= $this->translate->_('Description');

	$pagingparams = array(
		     "show_grid_heading" => false,
		     "grid_heading_message" => $this->translate->_('Overview'),
		     "show_records_per_page" => 10,
		     "show_searchbox" => false,
		     "show_selectbox" => true,
		     "show_editlink" => false,
		     "show_deletelink" => false,
		     "show_deleteall" => false,
		     "primaryid" => "usr.id",
		     "nodata_message" => $this->translate->_('No Record(s) Found')
		     );

	$pagingshow = new SFA_Pagingquery($pagingparams);
	$pagingshow->from(array('usr' => 'user'),
		    array('usr.id','usr.first_name'));
	$columns_show  = array($plan,$description);

	$this->view->pagegridshow =  $pagingshow->summary_showdatagrid($columns_show,'',$result);
	$this->view->addScriptPath(APPLICATION_PATH."/theme/default/datagridquery/");
    }
	
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
		
		$this->view->itemgrid  = $this->view->BaseUrl("/promotions/loyality/qualificationgrpitemgrid".$ex_param);
	
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
					 "editlink" => array("/promotions/loyality/addqualificationgrp/id/#pattern#/edit/yes/","#pattern#"),
					 "deletelink" => array("/account/loyality/authorizegroup/id/#pattern#/delete/yes/","#pattern#"),
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
		$result = $this->SFA_Comman->executequery('CALL sp_get_promotions_loyality_customer_authorizegroup(?,?,?,?,?,?,?)',$param_array,'',$downloadCSV,$printData,2,$pagingparams);
		
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
	
		$this->view->itemgrid  = $this->view->BaseUrl("/promotions/loyality/qualificationgrpitemgrid".$ex_param);
		
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
			$this->_helper->redirector('qualificationgrp', 'loyality', 'promotions');
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
			
			$r_update = $this->SFA_Comman->executequery('CALL sp_add_promotions_loyality_customer_addauthorizegroup(?,?,?,?,?,?,?,?)',$param_array,'');
			SFA_Message::setMsg($this->translate->_('New Record'));
			$this->_helper->redirector('qualificationgrp', 'loyality', 'promotions');
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
}
