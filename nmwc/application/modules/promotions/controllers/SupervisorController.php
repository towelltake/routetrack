<?php
/**
* @name       CustomerController
* @since
* @version    Release: 1
* @author     HD <hiren.d@elantechnologies.com>
* @copyright  Elan Technologies
* @param
*
* This controller is Promotions module.
*/
class Promotions_SupervisorController extends Promotions_Library_Controller_Action_Abstract
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
    * @name       pricingkeyplan
    * @since      6-02-2012
    * @version    Release: 1
    * @author     HD <Hiren.d@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This action is use for display cistomer free goods
    */
    public function freegoodsAction()
    {
	$this->view->params = $params = $this->getRequest()->getParams();
        $this->view->formdata = $formdata = $this->_request->getPost();

	if($formdata["hdDelete"]==1)
	{
		$ids = implode(',',$formdata['chk']);
		$param_array 	= array();
		$param_array[1]	= $ids;
		$param_array[2]	= $this->currentUser->username;
		
		$result 	= $this->SFA_Comman->executequery('CALL sp_delete_promotions_supervisor_freegoods(?,?)',$param_array,'');
		
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

	$this->view->title 	= $this->translate->_('Supervisor Free Contract');

	$id				= $this->translate->_('Contract ID');
	$code			= $this->translate->_('Code');
	$custoemr		= $this->translate->_('Supervisor');
	$created		= $this->translate->_('Start Date');
	$enddate		= $this->translate->_('End Date');
	$status			= $this->translate->_('Status');
	
	$cpanel				= $this->SFA_Comman->getaltcodestatus();
	$altcode_status		= $cpanel["Use Alternate Code"]['status'];
	
	

	$cols_array    = array('contractid','cf.supervisorcode','cm.supervisorname AS supervisorname','DATE_FORMAT(cf.creationdate,"%d-%m-%Y") as creationdate','DATE_FORMAT(cf.enddate,"%d-%m-%Y") AS enddate','CASE WHEN CURDATE() < DATE(cf.startdate) THEN "Pending" WHEN DATE(cf.startdate) <= CURDATE() AND DATE(cf.enddate) >= CURDATE() THEN "Running" WHEN CURDATE() >  DATE(cf.enddate) THEN "Ended"  END AS `status`');
	
	if($this->css == 'ar_') {
		$cols_array[2]	= 'arbsupervisorname AS customername';
	}
	if($altcode_status)
	{
		$cols_array[1]	= 'cm.supervisorcode';
	}
	$columns_show  =  array($id,$code,$custoemr,$created,$enddate,$status);
	
	$pagingparams = array(
			     "show_grid_heading" => true,
			     "grid_heading_message" => $this->translate->_('Overview'),
				 "pagename" => $this->translate->_('Supervisor Free Contract'),
			     "show_records_per_page" => isset($formdata["show_records_per_page"])?$formdata["show_records_per_page"]:10,
			     "show_searchbox" => true,
				 "selected_list" => $checked,
			     "searchbox_value" => isset($formdata["grid_search_text"])?$formdata["grid_search_text"]:"",
			     "show_selectbox" => true,
			     "show_editlink" => true,
			     "show_deletelink" => false,
			     "deletelink" => array("/promotions/supervisor/freegoods/id/#pattern#/delete/yes/","#pattern#"),
			     "show_deleteall" => false,
			     "primaryid" => "contractid",
			     "editlink" => array("/promotions/supervisor/addfreegoods/edit/yes/id/#pattern#","#pattern#"),
			     "fetch_columns_inquery" => $cols_array,
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
	
	// called stored procedure for counter
	$downloadCSV = (isset($formdata['downloadcsv'])) ? $formdata['downloadcsv'] : $params['downloadcsv'];
    
  //  print_r($param_array);exit;
	$printData = (isset($formdata['printData'])) ? $formdata['printData'] : $params['printData'];
	$rowcount = $this->SFA_Comman->executequery('CALL sp_get_promotions_supervisor_freegoods(?,?,?,?,?,?,?,?)',$param_array,'',$downloadCSV,$printData,2,$pagingparams);

	$data_arr["count"] 		= $rowcount[0][0]['counter'];
	$data_arr["data"][0] 	= $rowcount[1];
	
	
	
	
	// pass the data in summary_showdatagrid() function & create a final variable for view
	$this->view->pagegridshow =  $pagingshow->summary_showdatagrid($data_arr);
	
	$this->view->addScriptPath(APPLICATION_PATH."/theme/default/datagrid/");
	
    }
    /**
    * @name       addfreegoodsAction
    * @since      06-02-2012
    * @version    Release: 1
    * @author     HD <hiren.d@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This action is use for add Customer Free Contract
    */
    public function addfreegoodsAction()
    {
		$this->view->params = $params = $this->getRequest()->getParams();
		$this->view->formdata = $formdata = $this->_request->getPost();
	
		$this->view->returnUrl = $_SERVER["HTTP_REFERER"];
		
		
		$this->view->title 	= $this->translate->_('Supervisor Free Contract');
		
		$ex_param = "";
		if(isset($params["id"]) && $params["id"]>0)
			$ex_param = "/key/".$params["id"];
			
		$this->view->itemgrid    = $this->view->BaseUrl("/".$params['module']."/".$params['controller']."/freegoodsgrid".$ex_param);
	
		if($formdata['txtstartdate']!="" && $formdata['txtenddate']!="")
		{
			
			$param_array = array();
			
			$param_array[1] = date("Y-m-d",strtotime(str_replace('/', '-', $formdata['txtstartdate'])));
			$param_array[2] = $formdata['txt_remarks'];
			$param_array[3] = date("Y-m-d",strtotime(str_replace('/', '-', $formdata['txtstartdate'])));
			$param_array[4] = date("Y-m-d",strtotime(str_replace('/', '-', $formdata['txtenddate'])));
			$param_array[5] = $formdata['chk_active'];
			$param_array[6] = $formdata['ddl_depotcode'];		
			
			if($formdata['hdnid'] > 0) {
			
			$param_array[7] = $formdata['txt_contractid'];
			$param_array[8] = $this->currentUser->username;
			$result = $this->SFA_Comman->executequery('CALL sp_edit_promotions_supervisor_addfreegoods(?,?,?,?,?,?,?,?)',$param_array,'');
			SFA_Message::setMsg($this->translate->_('Update Record'));
			$last_id = $formdata['txt_contractid'];
			}
			else
			{
			$param_array[7] = $formdata['ddl_customercode'];
			$param_array[8] = $this->currentUser->username;
			
			$result = $this->SFA_Comman->executequery('CALL sp_add_promotions_supervisor_addfreegoods(?,?,?,?,?,?,?,?)',$param_array,'');
			SFA_Message::setMsg($this->translate->_('New Record'));
			$last_id = $result[0][0]["insid"];
			
			}
			
			#$this->_helper->redirector('addfreegoods', 'customer', 'promotions',array("edit"=>"yes","id"=>$last_id));
			if($formdata["returnUrl"]!="")
				$this->_redirect($formdata["returnUrl"]);
			else
			$this->_helper->redirector('freegoods', 'supervisor', 'promotions'); 
		   
		  
		}
		elseif($params['id'] > 0)
		{
			$result  			= $this->SFA_Comman->executequery('CALL sp_get_promotions_supervisor_addfreegoods(?)',$params['id'],'');
			
			$res['contractid'] 		= $result[0][0]['contractid'];
			$res['supervisorcode'] 	= $result[0][0]['supervisorcode'];
			$res['creationdate']	= str_replace('/', '-', $result[0][0]['creationdate']);
			$res['enddate']			= str_replace('/', '-', $result[0][0]['enddate']);
			$res['active'] 			= $result[0][0]['active'];
			$res['depotcode'] 		= $result[0][0]['depotcode'];
			$res['remarks'] 		= $result[0][0]['remarks'];
			
			$this->view->formdata 	= $res;
			//$this->view->customer_data 	= $result[1];
			$this->view->depot_data 	= $result[2];
			$this->view->item_master_data = $result[3];
		}
		else
		{
			$result  			= $this->SFA_Comman->executequery('CALL sp_get_promotions_supervisor_addfreegoods(?)','0','');
			$this->view->formdata['contractid'] = ($result[0][0]['Auto_increment'] == '') ? '1' : $result[0][0]['Auto_increment'];	    
			//$this->view->customer_data 	= $result[1];
			$this->view->depot_data 	= $result[2];
			$this->view->item_master_data = $result[3];			
		}
    }

     /**
    * @name       freegoodsAction
    * @since      06-02-2012
    * @version    Release: 1
    * @author     HD <hiren.d@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This action is use for add Customer Free Contract
    */
    public function freegoodsgridAction()
    {
		$this->view->params = $params = $this->getRequest()->getParams();
		$this->view->formdata = $formdata = $this->_request->getPost();
		
		// column to be fetched
		$columns_array 	=  array('cf.itemcode','itemshortdescription','unitspercase','freequantity','(cb.originalqty - cb.balanceqty) as used_foc','balanceqty','cf.itemcode as edit_del_primary_id');
		
		$cpanel						= $this->SFA_Comman->getaltcodestatus();
		$altcode_status				= $cpanel["Use Alternate Code"]['status'];
		
		if($this->css == 'ar_') {
			$columns_array[1]	= 'arbitemshortdescription AS itemshortdescription';
		}
		
		if($altcode_status)
		{
			$columns_array[0]	= 'alternatecode';
		}
		
		// column header to be displayed
		$columns_show  = array($this->translate->_('Item Code'),$this->translate->_('Description'),$this->translate->_('UPC'),
							   $this->translate->_('Free Qty'),$this->translate->_('Given Qty'),$this->translate->_('Balance Qty'));
		
		// UPDATE THE RECORD
		if($params["update"]=="yes") {
			
			$paramarry 	  = array();
			$paramarry[1] = $params['freequantity'];
			$paramarry[2] = $params['key'];
			$paramarry[3] = $params['id'];
			$paramarry[4] = $params['foc'];
			$paramarry[4] = 0;
			if($params['freequantity']>=$params['foc'])
			{
			$r_add = $this->SFA_Comman->executequery('CALL sp_edit_promotions_supervisor_freegoodsgrid(?,?,?,?)',$paramarry,'');
			                                               
			SFA_Message::setMsg($this->translate->_('Update Record'));
			}else{
			
			SFA_Message::setErrorMsg($this->translate->_('Free Qty Can Not Be Less Than Given Qty'));
			
			}
			
		}
	
		// ADDING THE RECORD
		if($params["add"]=="yes") {
			
			$params["add"]="";
			$paramarry = array();
		  
		  if($formdata['ddl_itemcode'] && $formdata['txt_freequantity']){
			if($formdata['hdnid'] > 0 || $params['key'] > 0)
			{
				$paramarry[1] = $formdata['ddl_customercode'];
				$paramarry[2] = $formdata['ddl_itemcode'];
				$paramarry[3] = $formdata['txt_freequantity'];
				$paramarry[4] = ($params['key'] > 0) ? $params['key'] : $formdata['hdnid'];
				
				$params['key'] = $paramarry[4];
				$r_add = $this->SFA_Comman->executequery('CALL sp_add_promotions_supervisor_freegoodsgrid(?,?,?,?)',$paramarry,'');
				
				echo '	<script>$("#hdnid").val('.$paramarry[4].');</script>';
			}
			else
			{
				$paramarry[1]  = date("Y-m-d",strtotime(str_replace('/', '-', $formdata['txtstartdate'])));
				$paramarry[2]  = $formdata['txt_remarks'];
				$paramarry[3]  = date("Y-m-d",strtotime(str_replace('/', '-', $formdata['txtstartdate'])));
				$paramarry[4]  = date("Y-m-d",strtotime(str_replace('/', '-', $formdata['txtenddate'])));
				$paramarry[5]  = $formdata['chk_active']==""?1:$formdata['chk_active'];
				$paramarry[6]  = $formdata['ddl_depotcode']==""?0:$formdata['ddl_depotcode'];
				$paramarry[7]  = $formdata['ddl_customercode'];				
				$paramarry[8]  = $formdata['ddl_itemcode'];
				$paramarry[9]  = $formdata['txt_freequantity'];
				$paramarry[10] = $this->currentUser->username;
				
				$r_add = $this->SFA_Comman->executequery('CALL sp_add_promotions_supervisor_addfreegoodsgriddetail(?,?,?,?,?,?,?,?,?,?)',$paramarry,'');
				
				$val 			= $r_add[0][0]['lastid'];
				$params["key"]	= $val;
				echo '	<script>$("#hdnid").val('.$val.');</script>';
			}			
			$duplicate=0;
			if($r_add[0][0]["result"]=="added")
			SFA_Message::setMsg($this->translate->_('New Record'));
			else{
				$duplicate=1;
			SFA_Message::setErrorMsg($this->translate->_('Duplicate Record'));}
			}
		}
	
	// DELETE THE RECORD
	if($params["delete"]=="yes"){
	    
	    $paramarry = array();
	    $paramarry[1] = $params['key'];
	    $paramarry[2] = $params['id'];
	    
	    $r_delete = $this->SFA_Comman->executequery('CALL sp_delete_promotions_supervisor_freegoodsgrid(?,?)',$paramarry,'');
		
		if($r_delete[0][0]['result'] == 'Success')
			SFA_Message::setMsg($this->translate->_('Delete Record'));
		else
			SFA_Message::setErrorMsg($this->translate->_('Contract Already Started For This Item; Cant Delete Now.'));
	}

	// IF EXTRA PARAMS ARE REQUIRED
	$ex_param = "";
	
	if(isset($params["key"]) && $params["key"]>0){
	    $additional_where_condition = array();
	    $ex_param = "/key/".$params["key"];
	    $additional_where_condition[] = ' (	cf.contractid = "'.$params["key"].'" )';
	}
	
	$pagingparams = array(
			     "show_grid_heading" => false,
			     "grid_heading_message" => $this->translate->_('Overview'),
			     "show_records_per_page" => isset($formdata["show_records_per_page"])?$formdata["show_records_per_page"]:10,
			     "show_searchbox" => false,
			     "show_selectbox" => false,
			     "show_editlink" => true,
			     "show_deletelink" => true,
			     "currentlink" => array("/promotions/customer/freegoodsgrid".$ex_param),
			     "deletelink" => array("/id/#pattern#/delete/yes","#pattern#"),
			     "editlink" => array("/id/#pattern#/edit/yes","#pattern#"),
			     "show_deleteall" => false,
			     "primaryid" => "itemcode",
			     "nodata_message" => $this->translate->_('No Record(s) Found'),
			     "fetch_columns_inquery" => $columns_array,
			     "show_columns" => $columns_show,
				 "noeditfields"=> array('itemshortdescription','alternatecode','itemcode','unitspercase','used_foc','balanceqty'),
			     "additional_where" => $additional_where_condition
			     );
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
	//cm.alternatecode
	// call the stored procedure for fetch the data
	$param_array	= array();
	$param_array[1] = '1';
	$param_array[2] = $get_return_vals['order_columns_name'];
	$param_array[3] = $get_return_vals['order_type'];
	$param_array[4] = $get_return_vals['offset'];
	$param_array[5] = (int)$get_return_vals['show_records_per_page'];
	$param_array[6] = '';
	$param_array[7] = strlen($get_return_vals['where_condition'])>0?$get_return_vals['where_condition']:'';

	
	// called stored procedure for counter
	$rowcount = $this->SFA_Comman->executequery('CALL sp_get_promotions_supervisor_freegoodsgrid(?,?,?,?,?,?,?)',$param_array,'');
    
	$data_arr["count"] = $rowcount[0][0]['counter'];
	
	// call stored proceddure for data
	$param_array[1] = '2';
	$param_array[6] = implode(", ",$pagingparams["fetch_columns_inquery"]);
	
	$data_arr["data"] = $this->SFA_Comman->executequery('CALL sp_get_promotions_supervisor_freegoodsgrid(?,?,?,?,?,?,?)',$param_array,'');
	
	$this->view->pagegridshow =  $pagingshow->summary_showdatagrid($data_arr);
	$this->view->addScriptPath(APPLICATION_PATH."/theme/default/datagrid/");

	$this->render("ajaxgrid");
	
    }
	
	 /**
    * @name       addfreegoodsgridAction
    * @since      06-02-2012
    * @version    Release: 1
    * @author     HD <hiren.d@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This action is use for add Customer Free Contract
    */
	public function addfreegoodsgridAction()
	{
		$params = $this->getRequest()->getParams();
		$formdata = $this->_request->getPost();//print_r($formdata );exit;
		$paramarry = array();
		  
		if($formdata['ddl_itemcode'] && $formdata['txt_freequantity']){
		  if($formdata['hdnid'] > 0 || $params['key'] > 0)
		  {
			  $paramarry[1] = $formdata['ddl_customercode'];
			  $paramarry[2] = $formdata['ddl_itemcode'];
			  $paramarry[3] = $formdata['txt_freequantity'];
			  $paramarry[4] = ($params['key'] > 0) ? $params['key'] : $formdata['hdnid'];
			//  print_r($paramarry);exit;
			 echo $paramarry[4];
			  $r_add = $this->SFA_Comman->executequery('CALL sp_add_promotions_supervisor_freegoodsgrid(?,?,?,?)',$paramarry,'');
			  
			 
		  }
		  else
		  {
			$paramarry[1]  = date("Y-m-d",strtotime(str_replace('/', '-', $formdata['txtstartdate'])));
			$paramarry[2]  = $formdata['txt_remarks'];
			$paramarry[3]  = date("Y-m-d",strtotime(str_replace('/', '-', $formdata['txtstartdate'])));
			$paramarry[4]  = date("Y-m-d",strtotime(str_replace('/', '-', $formdata['txtenddate'])));
			$paramarry[5]  = $formdata['chk_active']==""?1:$formdata['chk_active'];
			$paramarry[6]  = $formdata['ddl_depotcode']==""?0:$formdata['ddl_depotcode'];
			$paramarry[7]  = $formdata['ddl_customercode'];				
			$paramarry[8]  = $formdata['ddl_itemcode'];
			$paramarry[9]  = $formdata['txt_freequantity'];
			$paramarry[10] = $this->currentUser->username;
			
			$r_add = $this->SFA_Comman->executequery('CALL sp_add_promotions_supervisor_addfreegoodsgriddetail(?,?,?,?,?,?,?,?,?,?)',$paramarry,'');
			
			echo $r_add[0][0]['lastid'];
		  }			
		  
		  if($r_add[0][0]["result"]=="added")
			SFA_Message::setMsg($this->translate->_('New Record'));
		  else
			SFA_Message::setErrorMsg($this->translate->_('Duplicate Record'));
		}
		exit;
	}
	/*new action added by nilesh on 23May2016*/
	public function getcustomerroutecodeAction()
	{
		$params = $this->getRequest()->getParams();
		//$result = $this->SFA_Comman->executequery('CALL sp_combo_customermaster_sequence(?)',$params['depotid'],'');
		$result = $this->SFA_Comman->executequery('CALL sp_combo_supervisor()','','');
		echo Zend_Json::encode($result);
		exit;
	}
	
	/**/
    /**
    * @name       customerfreegoodsdetails
    * @since      06-02-2012
    * @version    Release: 1
    * @author     HD <hiren.d@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This action is use for add Customer Free Contract details
    */
    public function customerfreegoodsdetailsAction()
    {
	$this->view->params = $params = $this->getRequest()->getParams();
        $this->view->formdata = $formdata = $this->_request->getPost();

	$this->_helper->layout->setLayout('popup');

	$this->view->title	= $this->translate->_('Customer Free Goods Item Details');

	$item_code		= $this->translate->_('Item Code');
	$item			= $this->translate->_('Item');
	$free_item		= $this->translate->_('Free Item');
	$unit_price		= $this->translate->_('Unit Price');
	$item_price		= $this->translate->_('Item Price');




	$pagingparams = array(
			     "show_grid_heading" => false,
			     "grid_heading_message" => $this->translate->_('Overview'),
			     "show_records_per_page" => 5,
			     "show_searchbox" => false,
			     "show_selectbox" => true,
			     "show_editlink" => true,
			     "show_deletelink" => false,
			     "show_deleteall" => false,
			     "primaryid" => "usr.id",
			    //     "status_cols" => array(
			    //			    array(
			    //				"cols_name" => "status",
			    //				"status_change" => array("0"=>"Inactive","1"=>"Active")
			    //				)
			    //			    ),
			     "editlink" => array("/promotions/customer/customerpriceplan/id/#pattern#&amp;iframe=true&amp;width=850&amp;height=650&amp;edit&amp;yes/","#pattern#"),
			     "nodata_message" => $this->translate->_('No Record(s) Found')
			     );
     
	$pagingshow = new SFA_Pagingquery($pagingparams);
	$pagingshow->from(array('usr' => 'user'),
		    array('usr.id','usr.first_name','(usr.id+1) as a','FLOOR(RAND(5)*2500)','FLOOR(RAND(5)*5000)'));
	$columns_show  = array($item_code,$item,$free_item,$unit_price,$item_price);

	$this->view->pagegridshow =  $pagingshow->summary_showdatagrid($columns_show,'',$result);
	$this->view->addScriptPath(APPLICATION_PATH."/theme/default/datagridquery/");
    }
    
    
}
