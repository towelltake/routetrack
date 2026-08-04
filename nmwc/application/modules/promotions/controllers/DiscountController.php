<?php
/**
* @name       DiscountController
* @since
* @version    Release: 1
* @author     HD <hiren.d@elantechnologies.com>
* @copyright  Elan Technologies
* @param
*
* This controller manages the discount & disctribution data .
*/
class Promotions_DiscountController extends Promotions_Library_Controller_Action_Abstract
{
    /**
    * @name       init
    * @since      6-02-2012
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
    * @since      27- sep-2012
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
    * @name       discountkey
    * @since      6-02-2012
    * @version    Release: 1
    * @author     HD <Hiren.d@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This action is use for display display key
    */
    public function discountkeyAction()
    {
		$this->view->params = $params = $this->getRequest()->getParams();
        $this->view->formdata = $formdata = $this->_request->getPost();

		if($formdata["hdDelete"]==1)
		{
			$ids = implode(',',$formdata['chk']);
			$param_array 	= array();
			$param_array[1]	= $ids;
			$param_array[2]	= $this->currentUser->username;
			
			$result 	= $this->SFA_Comman->executequery('CALL sp_delete_promotion_dicount_discountkey(?,?)',$param_array,'');
			
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

		$this->view->title 	= $this->translate->_('Discount Key');
	
		$discount_key		= $this->translate->_('Discount Key');
		$description		= $this->translate->_('Description');
		$start_date		= $this->translate->_('Start Date');
		$end_date		= $this->translate->_('End Date');
		$status		= $this->translate->_('Status');
	
		$columns_array 	= array('discountkey','description','DATE_FORMAT(startdate,"%d-%m-%Y") AS startdate','DATE_FORMAT(enddate,"%d-%m-%Y") AS enddate','active');
		$columns_show  = array($discount_key,$description,$start_date,$end_date,$status);
		
		$pagingparams = array(
					 "show_grid_heading" => true,
					 "grid_heading_message" => $this->translate->_('Overview'),
					 "show_records_per_page" => isset($formdata["show_records_per_page"])?$formdata["show_records_per_page"]:10,
					 "show_searchbox" => true,
					 "pagename" => $this->translate->_('Discount Key'),
					 "searchbox_value" => isset($formdata["grid_search_text"])?$formdata["grid_search_text"]:"",
					 "show_selectbox" => true,
					 "show_editlink" => true,
					 "selected_list" => $checked,
					 "show_deletelink" => false,
					 "deletelink" => array("/promotions/discount/adddiscountkey/id/#pattern#/delete/yes/","#pattern#"),
					 "show_deleteall" => false,
					 "primaryid" => "discountkey",
						  "status_cols" => array(
									array(
									"cols_name" => "active",
									"status_change" => array("0"=>"Inactive","1"=>"Active")
									)
									),
					 "editlink" => array("/promotions/discount/adddiscountkey/edit/yes/id/#pattern#","#pattern#"),
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
		$rowcount = $this->SFA_Comman->executequery('CALL sp_get_promotions_discount_discountkey(?,?,?,?,?,?,?,?)',$param_array,'',$downloadCSV,$printData,2,$pagingparams);
	
		$data_arr["count"] 	= $rowcount[0][0]['counter'];
		$data_arr["data"][0] 	= $rowcount[1];
		
		#echo "<pre>"; print_r($data_arr); exit;
		
		
		// pass the data in summary_showdatagrid() function & create a final variable for view
		$this->view->pagegridshow =  $pagingshow->summary_showdatagrid($data_arr);
		
		$this->view->addScriptPath(APPLICATION_PATH."/theme/default/datagrid/");
    }
    /**
    * @name       adddiscountkeyAction
    * @since      06-02-2012
    * @version    Release: 1
    * @author     HD <hiren.d@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This action is use for add discount key
    */
    public function adddiscountkeyAction()
    {
		$this->view->params = $params = $this->getRequest()->getParams();
        $this->view->formdata = $formdata = $this->_request->getPost();

		$this->view->returnUrl = $_SERVER["HTTP_REFERER"];
	
		
		
		
		$ex_param = "";
		if(isset($params["id"]) && $params["id"]>0){	
			$ex_param = "/key/".$params["id"];
		}
		else
		{
			$ex_param = "/key/100000000000000000";
		}
		$this->view->itemgrid    = $this->view->BaseUrl("/promotions/discount/discountkeyitemgrid".$ex_param);
	
	
		if($formdata['txtstartdate'] && $formdata['txtenddate'])
		{
			$param_array = array();		
			
			$param_array[1] = $formdata['txt_description'];
			$param_array[2] = $formdata['txt_arbdescription'];
			$param_array[3] = date("Y-m-d",strtotime(str_replace('/', '-', $formdata['txtstartdate'])));
			$param_array[4] = date("Y-m-d",strtotime(str_replace('/', '-', $formdata['txtenddate'])));
			$param_array[5] = $formdata['ddlstatus'];
			$param_array[6] = $formdata['txt_discountkey'];
			
	
			if($formdata['hdnid'] > 0) {
			$param_array[7] = $this->currentUser->username;
			$result = $this->SFA_Comman->executequery('CALL sp_edit_promotions_discount_adddiscountkey(?,?,?,?,?,?,?)',$param_array,'');		
			SFA_Message::setMsg($this->translate->_('Update Record'));
			$last_id = $formdata['hdnid'];
			
			}
			else
			{
			$param_array[6] = $this->currentUser->username;
			$result = $this->SFA_Comman->executequery('CALL sp_add_promotions_discount_adddiscountkey(?,?,?,?,?,?)',$param_array,'');		
			SFA_Message::setMsg($this->translate->_('New Record'));
			$last_id = $result[0][0]["insid"];
			}	    
		   
			#$this->_helper->redirector('adddiscountkey', 'discount', 'promotions',array("edit"=>"yes","id"=>$last_id));
			if($formdata["returnUrl"]!="")
				$this->_redirect($formdata["returnUrl"]);
			else
			$this->_helper->redirector('discountkey', 'discount', 'promotions'); 
		}
		elseif($params['id'] > 0)
		{	    
			$result  		= $this->SFA_Comman->executequery('CALL sp_get_promotions_discount_adddiscountkey(?)',$params['id'],'');
			
			$res['discountkey'] 	= $result[1][0]['discountkey'];
			$res['description'] 	= $result[1][0]['description'];
			$res['arbdescription']	= $result[1][0]['arbdescription'];
			$res['startdate']		= $result[1][0]['startdate'];
			$res['enddate']		= $result[1][0]['enddate'];
			$res['active'] 		= $result[1][0]['active'];
			
			
			$this->view->formdata 		= $res;
			$this->view->item_master_data = $result[0]; 
		}
		else
		{
			$result  = $this->SFA_Comman->executequery('CALL sp_get_promotions_discount_adddiscountkey(?)','0','');			
			$this->view->item_master_data = $result[0];
			$this->view->formdata['discountkey'] = ($result[1][0]['Auto_increment'] == '') ? '1' : $result[1][0]['Auto_increment'];
		}
	}
	public function adddiscountkeygridAction()
	{
		$this->view->formdata = $formdata = $this->_request->getPost();
		
		$paramarry = array();
	  
		// if the discount for all item then 0 will be dropped as 		
		$paramarry[1]  = $formdata['ddlitem_code'];		
		$paramarry[2]  = $formdata['txtmins_disc'];
		$paramarry[3]  = $formdata['txtmax_disc'];
		$paramarry[4]  = $formdata['txt_description'];
		$paramarry[5]  = $formdata['txt_arbdescription'];
		$paramarry[6]  = date("Y-m-d",strtotime(str_replace('/', '-', $formdata['txtstartdate'])));
		$paramarry[7]  = date("Y-m-d",strtotime(str_replace('/', '-', $formdata['txtenddate'])));
		$paramarry[8]  = $formdata['ddlstatus'];
		$paramarry[9]  = $formdata['hdnid'];
		$paramarry[10] = $this->currentUser->username;
		
	
		$r_add = $this->SFA_Comman->executequery('CALL sp_add_promotions_discount_discountkeyitemgrid(?,?,?,?,?,?,?,?,?,?)',$paramarry,'');
	
		if($r_add[0][0]["result"]=="added")
			SFA_Message::setMsg($this->translate->_('New Record'));
		else
			SFA_Message::setErrorMsg($this->translate->_('Duplicate Record'));
		$last_id		= $r_add[1][0]["lastid"];
		$params['key'] 	= $last_id;
		//echo '<script type="text/javascript">  $("#hdnid").val('.$last_id.'); </script>';
		echo $last_id;
		exit;
	}
    /**
    * @name       discountkeyitemgridAction
    * @since      22-02-2012
    * @version    Release: 1
    * @author     HD <hiren.d@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This action is use for discount item grid
    */
    public function discountkeyitemgridAction()
    {
		$this->view->params = $params = $this->getRequest()->getParams();
		$this->view->formdata = $formdata = $this->_request->getPost();
		
		
		// column header to be displayed
		$item_code	= $this->translate->_('Item Code');
		$alt_code	= $this->translate->_('Alternate Code');
		$desc		= $this->translate->_('Description');
		$min_dis	= $this->translate->_('Min Discount');
		$max_dis	= $this->translate->_('Max Discount');
		
		// For Alternate Code Status.
		$cpanel				= $this->SFA_Comman->getaltcodestatus();
		$altcode_status		= $cpanel["Use Alternate Code"]['status'];
		
		if($altcode_status)
		{
			// column to be fetched
			$columns_array 	=  array('alternatecode','itemshortdescription','mindiscount','maxdiscount','d.actualitemcode as edit_del_primary_id');
			$columns_show  = array($alt_code,$desc,$min_dis,$max_dis);
		}
		else
		{
			// column to be fetched
			$columns_array 	=  array('d.actualitemcode as actualitemcode','itemshortdescription','mindiscount','maxdiscount','d.actualitemcode as edit_del_primary_id');
			$columns_show  = array($item_code,$desc,$min_dis,$max_dis);
		}
		if($this->css == 'ar_') {
			$columns_array[1]	= 'arbitemshortdescription AS itemshortdescription';
		}
		
		// DELETE THE RECORD
		if($params["delete"]=="yes"){
			
			$paramarry = array();
			$paramarry[1] = $params['key'];
			$paramarry[2] = $params['id'];
			
			$r_delete = $this->SFA_Comman->executequery('CALL sp_delete_promotions_discount_discountkeyitemgrid(?,?)',$paramarry,'');
			SFA_Message::setMsg($this->translate->_('Delete Record'));
		}
	
		if($params["update"]=="yes"){
		
			$updateData["1"] = $params["mindiscount"];
			$updateData["2"] = $params["maxdiscount"];
			$updateData["3"] = $params["key"];
			$updateData["4"] = $params["id"];
			
			$r_edit = $this->SFA_Comman->executequery('CALL sp_edit_promotions_discount_discountkeyitemgrid(?,?,?,?)',$updateData,'');
	
				SFA_Message::setMsg($this->translate->_('Update Record'));
			}
	
		// IF EXTRA PARAMS ARE REQUIRED
		$ex_param = "";
		if(isset($params["key"]) && $params["key"]>0){
			$additional_where_condition = array();
			$ex_param = "/key/".$params["key"];
			$additional_where_condition[] = ' (discountkey = "'.$params["key"].'" )';
		}
	
		$pagingparams = array(
					 "show_grid_heading" => false,
					 "grid_heading_message" => $this->translate->_('Overview'),
					 "show_records_per_page" => isset($formdata["show_records_per_page"])?$formdata["show_records_per_page"]:10,
					 "show_searchbox" => false,
					 "show_selectbox" => false,
					 "show_editlink" => true,
					 "show_deletelink" => true,
					 "currentlink" => array("/promotions/discount/discountkeyitemgrid".$ex_param),
					 "deletelink" => array("/id/#pattern#/delete/yes","#pattern#"),
					 "editlink" => array("/id/#pattern#/edit/yes","#pattern#"),
					 "show_deleteall" => false,
					 "primaryid" => "actualitemcode",
					 //"mastervalues" => array("mindiscount"=>array("34"=>34,"35"=>35,"36"=>36)),
					 "noeditfields"=> array("actualitemcode","itemshortdescription",'alternatecode'),
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
				$pagingparams["editmodevalue"] = "actualitemcode";
			}
	
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
		$param_array[6] = '';
		$param_array[7] = strlen($get_return_vals['where_condition'])>0?$get_return_vals['where_condition']:'';
	
		
		// called stored procedure for counter
		$rowcount = $this->SFA_Comman->executequery('CALL sp_get_promotions_discount_discountkeyitemgrid(?,?,?,?,?,?,?)',$param_array,'');
		
		$data_arr["count"] = $rowcount[0][0]['counter'];
		
		// call stored proceddure for data
		$param_array[1] = '';
		$param_array[6] = implode(", ",$pagingparams["fetch_columns_inquery"]);
		$tmp_data_arr = $this->SFA_Comman->executequery('CALL sp_get_promotions_discount_discountkeyitemgrid(?,?,?,?,?,?,?)',$param_array,'');
		
		foreach($tmp_data_arr[0] as $da)
		{
			if($da["actualitemcode"]==0 && $da["alternatecode"]=='') {
				$da["itemshortdescription"] = "-- ALL ITEM --";
			}
			
			$data_arr["data"][0][] = $da;
		}
		
		$this->view->pagegridshow =  $pagingshow->summary_showdatagrid($data_arr);
		$this->view->addScriptPath(APPLICATION_PATH."/theme/default/datagrid/");
	
		$this->render("ajaxgrid");
    }
    /**
    * @name       distributionkey
    * @since      7-02-2012
    * @version    Release: 1
    * @author     HD <Hiren.d@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This action is use for display distribution key
    */
    public function distributionkeyAction()
    {
	
		$this->view->params = $params = $this->getRequest()->getParams();
        $this->view->formdata = $formdata = $this->_request->getPost();

		if($formdata["hdDelete"]==1)
		{
			$ids = implode(',',$formdata['chk']);
			$param_array 	= array();
			$param_array[1]	= $ids;
			$param_array[2]	= $this->currentUser->username;
			
			$result 	= $this->SFA_Comman->executequery('CALL sp_delete_promotion_dicount_distributionkey(?,?)',$param_array,'');
			
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

	$this->view->title 	= $this->translate->_('Distribution Key');

	$discount_key		= $this->translate->_('Distribution Key');
	$description		= $this->translate->_('Description');
	$status			= $this->translate->_('Status');

	$columns_array 	= array('distributionkey','description','active');
	$columns_show  = array($discount_key,$description,$status);
	
	$pagingparams = array(
			     "show_grid_heading" => true,
			     "grid_heading_message" => $this->translate->_('Overview'),
				 "pagename" => $this->translate->_('Distribution Key'),
			     "show_records_per_page" => isset($formdata["show_records_per_page"])?$formdata["show_records_per_page"]:10,
			     "show_searchbox" => true,
			     "searchbox_value" => isset($formdata["grid_search_text"])?$formdata["grid_search_text"]:"",
			     "show_selectbox" => true,
			     "show_editlink" => true,
			     "show_deletelink" => false,
			     "deletelink" => array("/promotions/discount/distributionkey/id/#pattern#/delete/yes/","#pattern#"),
			     "show_deleteall" => false,
			     "primaryid" => "distributionkey",
			     "status_cols" => array(
						array(
						    "cols_name" => "active",
						    "status_change" => array("0"=>"Inactive","1"=>"Active")
						    )
						),
			     "editlink" => array("/promotions/discount/adddistributionkey/edit/yes/id/#pattern#","#pattern#"),
			     "nodata_message" => $this->translate->_('No Record(s) Found'),
			     "fetch_columns_inquery" => $columns_array,
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
	$rowcount = $this->SFA_Comman->executequery('CALL sp_get_promotions_discount_distributionkey(?,?,?,?,?,?,?,?)',$param_array,'',$downloadCSV,$printData,2,$pagingparams);

	$data_arr["count"] 		= $rowcount[0][0]['counter'];	
	$data_arr["data"][0] 	= $rowcount[1];	
	
	// pass the data in summary_showdatagrid() function & create a final variable for view
	$this->view->pagegridshow =  $pagingshow->summary_showdatagrid($data_arr);
	
	$this->view->addScriptPath(APPLICATION_PATH."/theme/default/datagrid/");
    }
    /**
    * @name       adddistributionkeyAction
    * @since      06-02-2012
    * @version    Release: 1
    * @author     HD <hiren.d@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This action is use for add distribution key
    */
    public function adddistributionkeyAction()
    {
	
		$this->view->params = $params = $this->getRequest()->getParams();
        $this->view->formdata = $formdata = $this->_request->getPost();
	
		$this->view->returnUrl = $_SERVER["HTTP_REFERER"];
		
		
		
		$ex_param = "";
		if(isset($params["id"]) && $params["id"]>0){	
			$ex_param = "/key/".$params["id"];
		}
		else {
			$ex_param = "/key/100000000000000000";
		}
		$this->view->itemgrid    = $this->view->BaseUrl("/promotions/discount/distributionkeyitemgrid".$ex_param);

	if($formdata['txt_description'])
	{
	    $param_array = array();		
	    
	    $param_array[1] = $formdata['txt_description'];
	    $param_array[2] = $formdata['txt_arbdescription'];
	    $param_array[3] = $formdata['ddlstatus'];
	    $param_array[4] = $formdata['txt_distributionkey'];
	   
	    if($formdata['hdnid'] > 0) {
		$param_array[5] = $this->currentUser->username;
		$result = $this->SFA_Comman->executequery('CALL sp_edit_promotions_discount_adddistributionkey(?,?,?,?,?)',$param_array,'');
		SFA_Message::setMsg($this->translate->_('Update Record'));
		$last_id = $formdata['hdnid'];
	    }
	    else
	    {
		$param_array[4] = $this->currentUser->username;
		$result = $this->SFA_Comman->executequery('CALL sp_add_promotions_discount_adddistributionkey(?,?,?,?,?)',$param_array,'');	
		SFA_Message::setMsg($this->translate->_('New Record'));
		$last_id = $result[0][0]["insid"];
	    }	    
	   
		$this->_helper->redirector('distributionkey', 'discount', 'promotions'); 
	}
	elseif($params['id'] > 0)
	{	    
	    $result  			= $this->SFA_Comman->executequery('CALL sp_get_promotions_discount_adddistributionkey(?)',$params['id'],'');
	    
	    $res['distributionkey'] 	= $result[1][0]['distributionkey'];
	    $res['description'] 	= $result[1][0]['description'];
	    $res['arbdescription']	= $result[1][0]['arbdescription'];
	    $res['active'] 		= $result[1][0]['active'];
	    
	    $this->view->formdata 		= $res;
	    $this->view->item_master_data = $result[0]; 
	}
	else
	{
	    $result  			= $this->SFA_Comman->executequery('CALL sp_get_promotions_discount_adddistributionkey(?)','0','');
	    $this->view->item_master_data = $result[0];
	    $this->view->formdata['distributionkey'] = ($result[1][0]['Auto_increment'] == '') ? '1' : $result[1][0]['Auto_increment'];
	}
	
    
    }
    /**
    * @name       discountkeyitemgridAction
    * @since      22-02-2012
    * @version    Release: 1
    * @author     HD <hiren.d@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This action is use for discount item grid
    */
    public function distributionkeyitemgridAction()
    {
	$this->view->params = $params = $this->getRequest()->getParams();
	$this->view->formdata = $formdata = $this->_request->getPost();

	// column to be fetched
	$columns_array 	=  array('item','itemshortdescription','FORMAT(caseprice,'.$this->decimalplaces.')  AS caseprice','FORMAT(defaultsalesprice,'.$this->decimalplaces.') AS defaultsalesprice','FORMAT(value,'.$this->decimalplaces.') as value','item as edit_del_primary_id');
	
	// For Alternate Code Status.
	$cpanel				= $this->SFA_Comman->getaltcodestatus();
	$altcode_status		= $cpanel["Use Alternate Code"]['status'];
	
	if($this->css == 'ar_') {
		$columns_array[1]	= 'arbitemshortdescription AS itemshortdescription';
	}
	if($altcode_status)
	{
		$columns_array[0] = 'alternatecode';
	}
	
	// column header to be displayed
	$columns_show  = array($this->translate->_('Item Code'),$this->translate->_('Description'),$this->translate->_('Case Price'),$this->translate->_('Sales Price'),$this->translate->_('Value'));

	
	
	// DELETE THE RECORD
	if($params["delete"]=="yes"){
	    
	    $paramarry = array();
	    $paramarry[1] = $params['key'];
	    $paramarry[2] = $params['id'];
	    
	    $r_delete = $this->SFA_Comman->executequery('CALL sp_delete_promotions_discount_adddistributionkeygrid(?,?)',$paramarry,'');
	    SFA_Message::setMsg($this->translate->_('Delete Record'));
	}

	// IF EXTRA PARAMS ARE REQUIRED
	$ex_param = "";
	if(isset($params["key"]) && $params["key"]>0){
	    $additional_where_condition = array();
	    $ex_param = "/key/".$params["key"];
	    $additional_where_condition[] = ' (distributionkey = "'.$params["key"].'" )';
	}

	$pagingparams = array(
			     "show_grid_heading" => false,
			     "grid_heading_message" => $this->translate->_('Overview'),
			     "show_records_per_page" => isset($formdata["show_records_per_page"])?$formdata["show_records_per_page"]:10,
			     "show_searchbox" => false,
			     "show_selectbox" => false,
			     "show_editlink" => false,
			     "show_deletelink" => true,
			     "currentlink" => array("/promotions/discount/distributionkeyitemgrid".$ex_param),
			     "deletelink" => array("/id/#pattern#/delete/yes","#pattern#"),
			     "show_deleteall" => false,
			     "primaryid" => "usr.id",
			     "nodata_message" => $this->translate->_('No Record(s) Found'),
			     "fetch_columns_inquery" => $columns_array,
			     "show_columns" => $columns_show,
			     "additional_where" => $additional_where_condition,				 
				 "show_columns_right_side"=>array('caseprice','defaultsalesprice','value'),
				 "show_header_right_side"=>array($this->translate->_('Case Price'),$this->translate->_('Sales Price'),$this->translate->_('Value')),
			     );

    
    if(!$this->checkaccess("delete"))
    {
        $pagingparams["show_deletelink"] = false;
    }

	$pagingshow = new SFA_Ajaxpaging($pagingparams);
	
	// call common function of grid class
	$get_return_vals = $pagingshow->commnfunc();
	
	// call the stored procedure for fetch the data
	$param_array    = array();
	$param_array[1] = '1';
	$param_array[2] = $get_return_vals['order_columns_name'];
	$param_array[3] = $get_return_vals['order_type'];
	$param_array[4] = $get_return_vals['offset'];
	$param_array[5] = (int)$get_return_vals['show_records_per_page'];
	$param_array[6] = '';
	$param_array[7] = strlen($get_return_vals['where_condition'])>0?$get_return_vals['where_condition']:'';

	
	// called stored procedure for counter
	$rowcount = $this->SFA_Comman->executequery('CALL sp_get_promotions_discount_adddistributionkeygrid(?,?,?,?,?,?,?)',$param_array,'');
    
	$data_arr["count"] = $rowcount[0][0]['counter'];
	
	// call stored proceddure for data
	$param_array[1] = '';
	$param_array[6] = implode(", ",$pagingparams["fetch_columns_inquery"]);
	$data_arr["data"] = $this->SFA_Comman->executequery('CALL sp_get_promotions_discount_adddistributionkeygrid(?,?,?,?,?,?,?)',$param_array,'');
	
	
	$this->view->pagegridshow =  $pagingshow->summary_showdatagrid($data_arr);
	$this->view->addScriptPath(APPLICATION_PATH."/theme/default/datagrid/");

	$this->render("ajaxgrid");
    }
	public function adddistributionkeyitemgridAction()
	{
		$this->view->formdata = $formdata = $this->_request->getPost();
		
		$paramarry = array();
		$paramarry[1] = $formdata['ddlitem_code'];
		$paramarry[2] = $formdata['txtvalue'];			
		$paramarry[3] = $formdata['txt_description'];
		$paramarry[4] = $formdata['txt_arbdescription'];
		$paramarry[5] = $formdata['ddlstatus'];
		$paramarry[6] = $formdata['hdnid'];
		$paramarry[7] = $this->currentUser->username;
	  
		$r_add = $this->SFA_Comman->executequery('CALL sp_add_promotions_discount_adddistributionkeygrid(?,?,?,?,?,?,?)',$paramarry,'');
		
		if($r_add[0][0]["result"]=="added")
		SFA_Message::setMsg($this->translate->_('New Record'));
		else
		SFA_Message::setErrorMsg($this->translate->_('Duplicate Record'));
		
		$last_id		= $r_add[1][0]["lastid"];
		echo $last_id;
		exit;
	}
}