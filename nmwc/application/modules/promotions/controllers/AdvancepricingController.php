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
class Promotions_AdvancepricingController extends Promotions_Library_Controller_Action_Abstract
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
    * @name       pricingplanAction
    * @since      03-02-2012
    * @version    Release: 1
    * @author     HD <hiren.d@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This action is use for the display promotion plan
    */
    public function pricingplanAction()
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
	
	$columns_array = array('customerpricingkey','description','arbdescription','customer_pricing_type(type) as type','active');
	$columns_show  = array($this->translate->_('CustomerPricing Key'),$this->translate->_('Description'),$this->translate->_('Description ('.$this->sec_lang.')'),$this->translate->_('Type'),$this->translate->_('Status'));
	
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
				"pagename" => $this->translate->_('Pricing Plan'),
			    "show_records_per_page" => isset($formdata["show_records_per_page"])?$formdata["show_records_per_page"]:10,
			    "show_searchbox" => true,			    
			    "searchbox_value" => isset($formdata["grid_search_text"])?$formdata["grid_search_text"]:"",
			    "show_selectbox" => true,
			    "show_editlink" => true,
			    "selected_list" => $checked,
			    "show_deletelink" => false,
			    "deletelink" => array("/promotions/advancepricing/promotionplan/id/#pattern#/delete/yes/","#pattern#"),
			    "show_deleteall" => false,
			    "primaryid" => "customerpricingkey",
			    "status_cols" => array(
						   array(
						       "cols_name" => "active",
						       "status_change" => array("0"=>"Inactive","1"=>"Active")
						       )
						   ),
			    "editlink" => array("/promotions/advancepricing/addpricingplan/id/#pattern#/edit/yes/","#pattern#"),
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
	$result 	= $this->SFA_Comman->executequery('CALL sp_get_promotions_advancepricing_pricingplan(?,?,?,?,?,?,?,?)',$param_array,'',$downloadCSV,$printData,2,$pagingparams);
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
    public function addpricingplanAction()
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
	


	if($formdata['txtdesc'] != '' && $formdata['ddltype'] != '' && $formdata['ddlcountry'] != '' )
	{
	    $param_array = array();
	    
	    $param_array[1] = $formdata['txtdesc'];	//description
	    $param_array[2] = $formdata['txtdesc_arb'];	//arbdescription
	    $param_array[3] = $formdata['ddltype'];	//type
	    $param_array[4] = $formdata['chkstatus'];	//active
	    $param_array[5] = $formdata['ddlcountry'];	//country
	    $param_array[6] = $this->currentUser->username;
	    
	    if($formdata['hdnid'] > 0){
		$param_array[7] = $formdata['hdnid'];	//customerpricingkey
		$result 	= $this->SFA_Comman->executequery('CALL sp_edit_promotion_advancepricing_addpricingplan(?,?,?,?,?,?,?)',$param_array,'');
		$last_id 	= $result[0][0]['last_id'];
		
		SFA_Message::setMsg($this->translate->_('Update Record'));
		
		
			$this->_helper->redirector('pricingplan', 'advancepricing', 'promotions');
	    }
	    else{
		$result 	= $this->SFA_Comman->executequery('CALL sp_add_promotion_advancepricing_addpricingplan(?,?,?,?,?,?)',$param_array,'');
		$last_id 	= $result[0][0]['last_id'];
		SFA_Message::setMsg($this->translate->_('New Record'));
		$this->_helper->redirector('addpricingplan', 'advancepricing', 'promotions',array('edit'=>'yes',"id"=>$last_id));
	    }

	    
	     
			
	}				
	elseif($params['id'] > 0)
	{
	    $result 	= $this->SFA_Comman->executequery('CALL sp_get_promotion_advancepricing_addpricingplan(?)',$params['id'],'');
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
	    
	    $this->view->itemgrid    = $this->view->BaseUrl("/promotions/advancepricing/pricingplanitemgrid".$ex_param);
	    
	}
	else
	{
	    $result 	= $this->SFA_Comman->executequery('CALL sp_get_promotion_advancepricing_addpricingplan(?)','0','');
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
    public function pricingplanitemgridAction()
    {
	
	$this->view->params = $params = $this->getRequest()->getParams();
	$this->view->formdata = $formdata = $this->_request->getPost();
	
	// For Alternate Code Status.
	$cpanel				= $this->SFA_Comman->getaltcodestatus();
	$altcode_status		= $cpanel["Use Alternate Code"]['status'];
	
	$item_code			= $this->translate->_('Item Code');
	$alt_code			= $this->translate->_('Alternate Code');
	$desc				= $this->translate->_('Description');
	$upc				= $this->translate->_('UPC');
	$sales_price			= $this->translate->_('New Price Case');
	$sales_case_price		= $this->translate->_('New Price Pcs');
	$return_price			= $this->translate->_('New Price Case(R)');
	$return_case_price		= $this->translate->_('New Price Pcs(R)');
	
	if($altcode_status)
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
	}

	
	
	
	// ADDING THE RECORD
	if($formdata["add"]=="yes") {
		
	    $paramarry = array();
	  
	    if(isset($formdata['ddlitem_code']) && isset($formdata['txtuni_per_case']))
	    {
			$paramarry[1] = $formdata["ddlitem_code"];
			$paramarry[2] = $formdata["txtuni_per_case"];
			$paramarry[3] = $formdata["txtstd_pcs_price"]; //- 3
			$paramarry[5] = $formdata["txtstd_pcs_return"]; //- 5
			$paramarry[4] = $formdata["txtstd_case_price1"]; //-4 
			$paramarry[6] = $formdata["txtstd_case_return_price"]; // -6
			$paramarry[7] = $formdata["txtsales_price"]; //- 7
			$paramarry[8] = $formdata["txtstd_case_price"]; //- 8
			$paramarry[9] = $formdata["txtreturn_price"]; //- 9
			$paramarry[10] = $formdata["txtcase_return_price"]; //- 10
			$paramarry[11] = $params["key"];
			$paramarry[12] = $this->currentUser->username;
			
			$r_add = $this->SFA_Comman->executequery('CALL sp_add_promotions_advancepricing_pricingplanitemgrid(?,?,?,?,?,?,?,?,?,?,?,?)',$paramarry,'');
			
			if($r_add[0][0]["result"]=="added")
				SFA_Message::setMsg($this->translate->_('New Record'));
			else
				SFA_Message::setErrorMsg($this->translate->_('Duplicate Record'));
	    }
	}
	
	// DELETE THE RECORD
	if($params["delete"]=="yes"){
	    
	     // sp for delete
	    $r_delete = $this->SFA_Comman->executequery('CALL sp_delete_promotions_advancepricing_pricingplanitemgrid(?)',array(1=>$params["id"]),'');
            SFA_Message::setMsg($this->translate->_('Delete Record'));
	}

	// UPDATE THE RECORD
	if($params["update"]=="yes"){

	    $updateData["1"] = $params["unitspercase"];
	    $updateData["2"] = $params["salesprice"];
	    $updateData["3"] = $params["salescaseprice"];
	    $updateData["4"] = $params["returnprice"];
	    $updateData["5"] = $params["returncaseprice"];
	    $updateData["6"] = $params["id"];
		$updateData["7"] = $this->currentUser->username;
	    
	    // call sp for edit currencydetail
	    $r_edit = $this->SFA_Comman->executequery('CALL sp_edit_promotions_advancepricing_pricingplanitemgrid(?,?,?,?,?,?,?)',$updateData,'');
	    SFA_Message::setMsg($this->translate->_('Update Record'));
	}

	// IF EXTRA PARAMS ARE REQUIRED
	$ex_param = "";
	if(isset($params["key"]) && $params["key"]>0){
	    $additional_where_condition = array();
	    $ex_param = "/key/".$params["key"];
	     $additional_where_condition[] = ' (customerpricingkey = "'.$params["key"].'" )';
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
			     "currentlink" => array("/promotions/advance/pricingkeyitemgrid".$ex_param),
			     "deletelink" => array("/id/#pattern#/delete/yes","#pattern#"),
			     "editlink" => array("/id/#pattern#/edit/yes","#pattern#"),
			     "noeditfields" => array('actualitemcode','alternatecode','itemshortdescription'),
			     "nodata_message" => $this->translate->_('No Record(s) Found'),
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
	$param_array[6] = '';
	$param_array[7] = strlen($get_return_vals['where_condition'])>0?$get_return_vals['where_condition']:'';

	// called stored procedure for counter
	$rowcount = $this->SFA_Comman->executequery('CALL sp_get_promotions_advancepricing_pricingplanitemgrid(?,?,?,?,?,?,?)',$param_array,'');
	$data_arr["count"] = $rowcount[0][0]['counter'];

	// call stored proceddure for data
	$param_array[1] = '0';
	$param_array[6] = implode(", ",$pagingparams["fetch_columns_inquery"]);
	
	$data_arr["data"] = $this->SFA_Comman->executequery('CALL sp_get_promotions_advancepricing_pricingplanitemgrid(?,?,?,?,?,?,?)',$param_array,'');
	
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
    public function pricingkeyAction()
    {
	$this->view->params = $params = $this->getRequest()->getParams();
        $this->view->formdata = $formdata = $this->_request->getPost();

	$columns_array = array('pricingplankey','description','arbdescription','activeindicator');
	$columns_show  = array($this->translate->_('Pricing Key'),$this->translate->_('Description'),$this->translate->_('Description ('.$this->sec_lang.')'),$this->translate->_('Status'));
	
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

	$pricing_key		= $this->translate->_('Pricing Key');
	$description		= $this->translate->_('Description');
	$arb_desc		= $this->translate->_('Description ('.$this->sec_lang.')');
	$status			= $this->translate->_('Status');
    
	$pagingparams = array(
			    "show_grid_heading" => true,
			    "grid_heading_message" => $this->translate->_('Overview'),
			    "show_records_per_page" => isset($formdata["show_records_per_page"])?$formdata["show_records_per_page"]:10,
				"pagename" => $this->translate->_('Pricing Key'),
			    "show_searchbox" => true,
			    "selected_list" => $checked,
			    "searchbox_value" => isset($formdata["grid_search_text"])?$formdata["grid_search_text"]:"",
			    "show_selectbox" => true,
			    "show_editlink" => true,
			    "show_deletelink" => false,
			    "deletelink" => array("/promotions/advancepricing/promotionplan/id/#pattern#/delete/yes/","#pattern#"),
			    "show_deleteall" => false,
			    "primaryid" => "pricingplankey",
			    "status_cols" => array(
						   array(
						       "cols_name" => "activeindicator",
						       "status_change" => array("0"=>"Inactive","1"=>"Active")
						       )
						   ),
			    "editlink" => array("/promotions/advancepricing/addpricingkey/id/#pattern#/edit/yes/","#pattern#"),
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
	$result 	= $this->SFA_Comman->executequery('CALL sp_get_promotions_advancepricing_pricingkey(?,?,?,?,?,?,?,?)',$param_array,'',$downloadCSV,$printData,2,$pagingparams);
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
    public function addpricingkeyAction()
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
			$param_array[4] = $this->currentUser->username;
			
			if($formdata['hdnid'] > 0){
				$param_array[5] = $formdata['hdnid'];	//customerpricingkey
				
				$result 	= $this->SFA_Comman->executequery('CALL sp_edit_promotions_advancepricing_addpricingkey(?,?,?,?,?)',$param_array,'');
				$last_id 	= $params["id"];
				SFA_Message::setMsg($this->translate->_('Update Record'));
				
				
				$this->_helper->redirector('pricingkey', 'advancepricing', 'promotions');
			}
			else
			{		
				#echo "<pre>"; print_r($param_array); exit;
			
				$result 	= $this->SFA_Comman->executequery('CALL sp_add_promotions_advancepricing_addpricingkey(?,?,?,?)',$param_array,'');
				$last_id 	= $result[0][0]['result'];
				SFA_Message::setMsg($this->translate->_('New Record'));
				$this->_helper->redirector('addpricingkey', 'advancepricing', 'promotions',array('edit'=>'yes',"id"=>$last_id));	
			}
		}				
		elseif($params['id'] > 0)
		{
			$result 	= $this->SFA_Comman->executequery('CALL sp_get_promotions_advancepricing_addpricingkey(?)',$params['id'],'');
			$res['txtplan_key']		= $result[0][0]['pricingplankey'];
			$res['txtdesc']			= $result[0][0]['description'];
			$res['txtdesc_arb']		= $result[0][0]['arbdescription'];
			$res['ddlindicator']	= $result[0][0]['activeindicator'];
			$this->view->formdata	= $res;
			
			$this->view->itemgrid   = $this->view->BaseUrl("/promotions/advancepricing/pricingkeyitemgrid/key/".$params['id']);
			$this->view->itemgrid2   = $this->view->BaseUrl("/promotions/advancepricing/pricingplanselectionitemgrid/key/".$params['id']);
			
		   
		}
		else
		{
			$result 	= $this->SFA_Comman->executequery('CALL sp_get_promotions_advancepricing_addpricingkey(?)','0','');
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
    public function pricingkeyitemgridAction()
    {
	$this->view->params = $params = $this->getRequest()->getParams();
	$this->view->formdata = $formdata = $this->_request->getPost();
	
	// column to be fetched
	$columns_array 	=  array('customerpricingkey','description','arbdescription','DATE_FORMAT(startdate,"%d-%m-%Y") as startdate','DATE_FORMAT(enddate,"%d-%m-%Y") AS enddate','contractno','active','primary_key as edit_del_primary_id');
	
	$plan_no		= $this->translate->_('Plan #');
	$description		= $this->translate->_('Description');
	$arb_desc		= $this->translate->_('Description ('.$this->sec_lang.')');
	$start_date		= $this->translate->_('Start Date');
	$end_date		= $this->translate->_('End Date');
        $contact		= $this->translate->_('Contact No');
	$status			= $this->translate->_('Status');

	$columns_show  = array($plan_no,$description,$arb_desc,$start_date,$end_date,$contact,$status);
	
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
		
		$r_add = $this->SFA_Comman->executequery('CALL sp_add_promotions_advancepricing_pricingkeyitemgrid(?,?,?,?)',$paramarry,'');
	    
		SFA_Message::setMsg($this->translate->_('Update Record'));
	    }
	  }
	}
	
	// DELETE THE RECORD
	if($params["delete"]=="yes"){
	    
	     // sp for delete
	    $r_delete = $this->SFA_Comman->executequery('CALL sp_delete_promotions_advancepricing_pricingkeyitemgrid',array(1=>$params["id"]),'');
            SFA_Message::setMsg($this->translate->_('Delete Record'));
	}

	// UPDATE THE RECORD
	if($params["update"]=="yes"){

	    $updateData["1"] = date("Y-m-d",strtotime($params["startdate"]));
	    $updateData["2"] = date("Y-m-d",strtotime($params["enddate"]));
	    $updateData["3"] = $params["id"];
		$updateData["4"] = $this->currentUser->username;
	    
	    // call sp for edit currencydetail
	    $r_edit = $this->SFA_Comman->executequery('CALL sp_edit_promotions_advancepricing_pricingkeyitemgrid(?,?,?,?)',$updateData,'');
	    SFA_Message::setMsg($this->translate->_('Update Record'));
	}

	// IF EXTRA PARAMS ARE REQUIRED
	$ex_param = "";
	if(isset($params["key"]) && $params["key"]>0){
	    $additional_where_condition = array();
	    $ex_param = "/key/".$params["key"];
	     $additional_where_condition[] = ' (pricingplankey = "'.$params["key"].'" )';
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
			     "currentlink" => array("/promotions/advance/pricingkeyitemgrid".$ex_param),
			     "deletelink" => array("/id/#pattern#/delete/yes","#pattern#"),
			     "editlink" => array("/id/#pattern#/edit/yes","#pattern#"),
			     "noeditfields" => array('customerpricingkey','description','arbdescription','contractno','active'),
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
	$rowcount = $this->SFA_Comman->executequery('CALL sp_get_promotions_advancepricing_pricingkeyitemgrid(?,?,?,?,?,?,?)',$param_array,'',$downloadCSV,$printData,2,$pagingparams);
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
    public function pricingplanselectionitemgridAction()
    {
	$this->view->params = $params = $this->getRequest()->getParams();
	$this->view->formdata = $formdata = $this->_request->getPost();
	
	// column to be fetched
	$columns_array 	=  array('customerpricingkey','description');
	
	// column header to be displayed
	$pricing_plan		= $this->translate->_('Pricing Plan');
	$description		= $this->translate->_('Description');
	
	
	$columns_show  = array($pricing_plan,$description);
	
	// gettting the already having the plan number into price key detail
	$selected_price_list = $this->SFA_Comman->executequery('CALL sp_getpricinglist_promotions_advancepricing_pricingplanselection',$params["key"],'');
	
	// price numbers array
	$price_array = explode(",",$selected_price_list[0][0]["plans"]);
	
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
			     "selected_list" => $price_array,
			     "show_editlink" => false,
			     "show_deletelink" => false,
			     "show_deleteall" => false,
			     "primaryid" => "customerpricingkey",
			     "currentlink" => array("/promotions/advance/pricingplanselectionitemgrid".$ex_param),
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
	$rowcount = $this->SFA_Comman->executequery('CALL sp_get_promotions_advancepricing_pricingplanselectionitemgrid(?,?,?,?,?,?,?)',$param_array,'',$downloadCSV,$printData,2,$pagingparams);
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
    public function pricingkeyplanAction()
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
}
