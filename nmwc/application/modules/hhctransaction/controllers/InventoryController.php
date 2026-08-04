<?php
/**
* @name       InventoryController
* @since
* @version    Release: 1
* @author     M@M <miral@elantechnologies.com>
* @copyright  Elan Technologies
* @param
*
* This controller is manage hhctransaction module.
*/


class Hhctransaction_InventoryController extends Hhctransaction_Library_Controller_Action_Abstract
{
      /**
    * @name       init
    * @since      01-02-2012
    * @version    Release: 1
    * @author     M@M <miral@elantechnologies.com>
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
	
		$this->common_model			= new SFA_Model_Index();
		$this->SFA_Comman			= new SFA_Comman();
		$this->view->sec_lang		= $this->SFA_Comman->getsecondlanguage();
		$this->decimalplaces 		= $this->SFA_Comman->getdecimalplaces();
		$this->view->decimalplaces 	= $this->decimalplaces ;
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
            if(($getpost_init["hdDelete"]==1 || $getparams_init['delid'] > 0) && !$this->checkaccess("delete")) {
            
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
    * @name       inventorysummaryAction
    * @since      01-02-2012
    * @version    Release: 1
    * @author     M@M <miral@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This action is for display Inventory Summary
    *
    */
    public function inventorysummaryAction()
    {
		$this->view->params = $params = $this->getRequest()->getParams();
        $this->view->formdata = $formdata = $this->_request->getPost();
		
		$cols_array 	= array('sed.routecode','rm.routename', 'sed.salesmancode', 'DATE_FORMAT(sed.routestartdate,"%d-%m-%Y") AS routestartdate', 'DATE_FORMAT(sed.routeenddate,"%d-%m-%Y") AS routeenddate','IF(sed.routeclosed = 0,"No","Yes") AS routeclosed','sed.routekey AS edit_del_primary_id');
		$columns_show 	= array($this->translate->_('Route Code'),$this->translate->_('Route Name'),$this->translate->_('Salesman Code'),$this->translate->_('Route Startdate'),$this->translate->_('Route Enddate'),$this->translate->_('Route Close'));
		
		
		$Common_NameSpace = new Zend_Session_Namespace('InventorySummary');
		
		$last_url 		= htmlspecialchars($_SERVER['HTTP_REFERER']);
		$end_last_url 	= explode('/',$last_url);
		
		if(end($end_last_url) == 'inventorysummary' || strpos($last_url,'viewinventorysummary') || strpos($last_url,'/inventorysummary/' )) {
			
			$sel_date 	= ($formdata["txtdate"] != '') ? $formdata["txtdate"] : $Common_NameSpace->tdate;
		}
		else {
			$sel_date 	= ($formdata["txtdate"] != '') ? $formdata["txtdate"] : date('d-m-Y');
		}
		
		// ADD DATE VALUE IN SESSION
		if($sel_date != '') {
			$Common_NameSpace->tdate 	= $sel_date;
			$this->view->date			= $sel_date;
		} else {
			$Common_NameSpace->tdate 	= date('d-m-Y');
			$this->view->date			= date('d-m-Y');
		}
		
		$additional_where_condition = array();		
		$additional_where_condition[] = " (DATE(sed.routestartdate) = \'".date("Y-m-d",strtotime(str_replace('/', '-', $Common_NameSpace->tdate)))."\')";
		
		
		// prepare the configuration for grid
		$pagingparams = array(
				"show_grid_heading" => true,
				"grid_heading_message" => $this->translate->_('Overview'),
				"show_records_per_page" => isset($formdata["show_records_per_page"])?$formdata["show_records_per_page"]:10,
				"show_searchbox" => true,
				"searchbox_value" => isset($formdata["grid_search_text"])?$formdata["grid_search_text"]:"",
				"show_selectbox" => false,
				"show_editlink" => false,				
				"show_deletelink" => false,			
				"show_deleteall" => false,
				"primaryid" => "sed.routekey",
				"show_extralink" => true,
				"extralink" => array(array("View","/".$params['module']."/".$params['controller']."/viewinventorysummary/id/#pattern#","#pattern#")),
				"nodata_message" => $this->translate->_('No Record(s) Found'),
				"fetch_columns_inquery" => $cols_array,
				"show_columns" => $columns_show,
				"additional_where" => $additional_where_condition
				);
		
		// create grid class object
		$pagingshow = new SFA_Paging($pagingparams);
		
		// call common function of grid class
		$get_return_vals = $pagingshow->commnfunc();
		
		// call the stored procedure for fetch the data
		$param_array = array();
		$param_array[1] = '1';
		$param_array[2] = '';
		$param_array[3] = $get_return_vals['order_columns_name'];
		$param_array[4] = $get_return_vals['order_type'];
		$param_array[5] = $get_return_vals['offset'];
		$param_array[6] = (int)$get_return_vals['show_records_per_page'];
		$param_array[7] = implode(", ",$pagingparams["fetch_columns_inquery"]);
		$param_array[8] = strlen($get_return_vals['where_condition'])>0?$get_return_vals['where_condition']:'';		
		
		// called stored procedure for counter
		$result = $this->SFA_Comman->executequery('CALL sp_get_hhctransaction_summary_inventorysummary(?,?,?,?,?,?,?,?)',$param_array,'');
	
		$data_arr["count"] 			= $result[0][0]['counter'];
		$data_arr["data"][0] 		= $result[1];
		
		
		// pass the data in summary_showdatagrid() function & create a final variable for view
		$this->view->pagegridshow =  $pagingshow->summary_showdatagrid($data_arr);
		
		$this->view->addScriptPath(APPLICATION_PATH."/theme/default/datagrid/");
    }
     /**
    * @name       viewbeginstockAction
    * @since      02-02-2012
    * @version    Release: 1
    * @author     HD <hiren.d@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This action is for add begin stock
    *
    */
    public function viewinventorysummaryAction()
    {
		$this->view->params = $params = $this->getRequest()->getParams();
        $this->view->formdata = $formdata = $this->_request->getPost();
        
		// Begin Stock Datagrid
		$ex_param = "";
		if(isset($params["id"]) && $params["id"]>0)
			$ex_param = "/key/".$params["id"];
	
		$this->view->itemgrid    = $this->view->BaseUrl("/".$params['module']."/".$params['controller']."/viewinventorysummarygrid".$ex_param);
		
		$res = $this->SFA_Comman->executequery('CALL sp_get_hhctransaction_summary_viewinventorysummary(?)',$params['id'],'');
		
		$this->view->formdata 	= $res[0][0];
    }
	
	 /**
    * @name       viewinventorysummarygridAction
    * @since      22-02-2012
    * @version    Release: 1
    * @author     AS <alpesh@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This action is use for view inventory summary detail
    */
    public function viewinventorysummarygridAction()
    {
		$this->view->params = $params = $this->getRequest()->getParams();
		
        // IF EXTRA PARAMS ARE REQUIRED
        $ex_param = "";
        if(isset($params["key"]) && $params["key"]>0)
                $ex_param = "/key/".$params["key"];
		
		$columns_show  	= array(
									$this->translate->_('Item Code'),$this->translate->_('Item Description'),
									$this->translate->_('OUT'),$this->translate->_('PCS'),$this->translate->_('OUT'),$this->translate->_('PCS'),
									$this->translate->_('OUT'),$this->translate->_('PCS'),$this->translate->_('OUT'),$this->translate->_('PCS'),
									$this->translate->_('OUT'),$this->translate->_('PCS'),$this->translate->_('OUT'),$this->translate->_('PCS'),
									$this->translate->_('OUT'),$this->translate->_('PCS'),$this->translate->_('OUT'),$this->translate->_('PCS'),
									$this->translate->_('OUT'),$this->translate->_('PCS'),$this->translate->_('OUT'),$this->translate->_('PCS'),
									$this->translate->_('Opening Value'),$this->translate->_('Loaded Value'),$this->translate->_('Truck Stock Value'),$this->translate->_('Closing Value'));
		
		$cpanel				= $this->SFA_Comman->getaltcodestatus();
		$altcode_status		= $cpanel["Use Alternate Code"]['status'];
		
		//$columns_array 	= array('im.actualitemcode','im.itemdescription',
		//				'IFNULL(FLOOR(isd.beginstockqty/im.unitspercase ),0) AS beginqtycase',
		//				'IFNULL(CASE WHEN isd.beginstockqty % im.unitspercase <> 0 THEN isd.beginstockqty % im.unitspercase ELSE 0 END,0) AS  beginqtypcs',
		//				'IFNULL(FLOOR(isd.loadqty/im.unitspercase ),0) AS loadqtycase',
		//				'IFNULL(CASE WHEN isd.loadqty % im.unitspercase <> 0 THEN isd.loadqty % im.unitspercase ELSE 0 END,0) AS  loadqtypcs',
		//				'IFNULL(FLOOR(isd.loadaddqty/im.unitspercase ),0) AS  loadaddqtycase',
		//				'IFNULL(CASE WHEN isd.loadaddqty % im.unitspercase <> 0 THEN isd.loadaddqty % im.unitspercase ELSE 0 END,0) AS loadaddqtypcs',
		//				'IFNULL(FLOOR(isd.loadcutqty/im.unitspercase ),0) AS loadcutqtycase',
		//				'IFNULL(CASE WHEN isd.loadcutqty % im.unitspercase <> 0 THEN isd.loadcutqty % im.unitspercase ELSE 0 END,0) AS loadcutqtypcs',
		//				'IFNULL(FLOOR(sales.salesqty/im.unitspercase ),0) AS salesqtycase',
		//				'IFNULL(CASE WHEN sales.salesqty % im.unitspercase <> 0 THEN sales.salesqty % im.unitspercase ELSE 0 END,0) AS salesqtypcs',
		//				'IFNULL(FLOOR(sales.returnqty/im.unitspercase ),0) AS returnqtycase',
		//				'IFNULL(CASE WHEN sales.returnqty % im.unitspercase <> 0 THEN sales.returnqty % im.unitspercase ELSE 0 END,0) AS returnqtypcs',
		//				'IFNULL(FLOOR(sales.damagedqty/im.unitspercase ),0) AS damagedqtycase',
		//				'IFNULL(CASE WHEN sales.damagedqty % im.unitspercase <> 0 THEN sales.damagedqty % im.unitspercase ELSE 0 END,0) AS damagedqtypcs',
		//				'IFNULL(FLOOR(sales.expiryqty/im.unitspercase ),0) AS expiryqtycase',
		//				'IFNULL(CASE WHEN sales.expiryqty % im.unitspercase <> 0 THEN sales.expiryqty % im.unitspercase ELSE 0 END,0) AS expiryqtypcs',
		//				'IFNULL(FLOOR(sales.freesampleqty/im.unitspercase ),0) AS freesampleqtycase',
		//				'IFNULL(CASE WHEN sales.freesampleqty % im.unitspercase <> 0 THEN sales.freesampleqty % im.unitspercase ELSE 0 END,0) AS freesampleqtypcs',
		//				'IFNULL(FLOOR(sales.manualfreeqty/im.unitspercase ),0) AS manualfreeqtycase',
		//				'IFNULL(CASE WHEN sales.manualfreeqty % im.unitspercase <> 0 THEN sales.manualfreeqty % im.unitspercase ELSE 0 END,0) AS manualfreeqtypcs',
		//				'IFNULL(FLOOR((isd.endstockqty + isd.unloadqty)/im.unitspercase ),0) AS endqtycase',
		//				'IFNULL(CASE WHEN ((isd.endstockqty + isd.unloadqty) % im.unitspercase) <> 0 THEN (isd.endstockqty + isd.unloadqty) % im.unitspercase ELSE 0 END,0) AS endqtypcs',
		//				'FORMAT((FLOOR((isd.beginstockqty/im.unitspercase) * isd.stdsalescaseprice)+ CASE WHEN (isd.beginstockqty % im.unitspercase) <> 0 THEN ((isd.beginstockqty % im.unitspercase) * isd.stdsalesprice) ELSE 0 END),'.$this->decimalplaces.')  AS openingstockvalue', 
		//				'FORMAT((FLOOR((isd.loadqty/im.unitspercase) * isd.stdsalescaseprice) + CASE WHEN (isd.loadqty% im.unitspercase) <> 0 THEN ((isd.loadqty % im.unitspercase) * isd.stdsalesprice) ELSE 0 END),'.$this->decimalplaces.')  AS loadedvalue',						
		//				'FORMAT((FLOOR(((isd.beginstockqty + isd.loadqty + isd.loadaddqty)-isd.loadcutqty)/im.unitspercase ) * isd.stdsalescaseprice) + (CASE WHEN ((isd.beginstockqty + isd.loadqty + isd.loadaddqty)-isd.loadcutqty) % im.unitspercase <> 0 THEN
		//				(((isd.beginstockqty + isd.loadqty + isd.loadaddqty)-isd.loadcutqty) % im.unitspercase) * isd.stdsalesprice ELSE 0 END),'.$this->decimalplaces.')  AS  truckstockvalue',
		//				'FORMAT((FLOOR((isd.endstockqty + isd.unloadqty)/im.unitspercase ) * isd.stdsalescaseprice) + (CASE WHEN (isd.endstockqty + isd.unloadqty) % im.unitspercase <> 0 THEN ((isd.endstockqty + isd.unloadqty) % im.unitspercase) * isd.stdsalesprice ELSE 0 END),'.$this->decimalplaces.')  AS  endstockvalue');
		//
		//
				
		$columns_array 	= array('im.alternatecode','im.itemdescription',
						'IFNULL(FLOOR(isd.beginstockqty/im.unitspercase ),0) AS beginqtycase',
						'IFNULL(CASE WHEN isd.beginstockqty % im.unitspercase <> 0 THEN isd.beginstockqty % im.unitspercase ELSE 0 END,0) AS beginqtypcs',
						'IFNULL(FLOOR(isd.loadqty/im.unitspercase ),0) AS loadqtycase',
						'IFNULL(CASE WHEN isd.loadqty % im.unitspercase <> 0 THEN isd.loadqty % im.unitspercase ELSE 0 END,0) AS loadqtypcs',
						'IFNULL(FLOOR(isd.loadaddqty/im.unitspercase ),0) AS loadaddqtycase',
						'IFNULL(CASE WHEN isd.loadaddqty % im.unitspercase <> 0 THEN isd.loadaddqty % im.unitspercase ELSE 0 END,0) AS loadaddqtypcs',
						'IFNULL(FLOOR(isd.loadcutqty/im.unitspercase ),0) AS loadcutqtycase',
						'IFNULL(CASE WHEN isd.loadcutqty % im.unitspercase <> 0 THEN isd.loadcutqty % im.unitspercase ELSE 0 END,0) AS loadcutqtypcs',
						'IFNULL(FLOOR(isd.saleqty/im.unitspercase ),0) AS salesqtycase',
						'IFNULL(CASE WHEN isd.saleqty % im.unitspercase <> 0 THEN isd.saleqty % im.unitspercase ELSE 0 END,0) AS salesqtypcs',
						'IFNULL(FLOOR(isd.returnqty/im.unitspercase ),0) AS returnqtycase',
						'IFNULL(CASE WHEN isd.returnqty % im.unitspercase <> 0 THEN isd.returnqty % im.unitspercase ELSE 0 END,0) AS returnqtypcs',
						'IFNULL(FLOOR(isd.damageqty/im.unitspercase ),0) AS damagedqtycase',
						'IFNULL(CASE WHEN isd.damageqty % im.unitspercase <> 0 THEN isd.damageqty % im.unitspercase ELSE 0 END,0) AS damagedqtypcs',
						'IFNULL(FLOOR(isd.freesampleqty/im.unitspercase ),0) AS freesampleqtycase',
						'IFNULL(CASE WHEN isd.freesampleqty % im.unitspercase <> 0 THEN isd.freesampleqty % im.unitspercase ELSE 0 END,0) AS freesampleqtypcs',
						'IFNULL(FLOOR(isd.freesampleqty/im.unitspercase ),0) AS manualfreeqtycase',
						'IFNULL(CASE WHEN isd.freesampleqty % im.unitspercase <> 0 THEN isd.freesampleqty % im.unitspercase ELSE 0 END,0) AS manualfreeqtypcs',
						'IFNULL(FLOOR((isd.vanstock)/im.unitspercase ),0) AS endqtycase',
						'IFNULL(CASE WHEN ((isd.vanstock) % im.unitspercase) <> 0 THEN (isd.vanstock) % im.unitspercase ELSE 0 END,0) AS endqtypcs',
						'FORMAT((FLOOR((isd.beginstockqty/im.unitspercase) * isd.stdsalescaseprice)+ CASE WHEN (isd.beginstockqty % im.unitspercase) <> 0 THEN ((isd.beginstockqty % im.unitspercase) * isd.stdsalesprice) ELSE 0 END),'.$this->decimalplaces.') AS openingstockvalue',
						'FORMAT((FLOOR((isd.loadqty/im.unitspercase) * isd.stdsalescaseprice) + CASE WHEN (isd.loadqty% im.unitspercase) <> 0 THEN ((isd.loadqty % im.unitspercase) * isd.stdsalesprice) ELSE 0 END),'.$this->decimalplaces.') AS loadedvalue',
						'FORMAT((FLOOR((isd.vanstock)/im.unitspercase) * isd.stdsalescaseprice) + (CASE WHEN (isd.vanstock) % im.unitspercase <> 0 THEN ((isd.vanstock) % im.unitspercase) * isd.stdsalesprice ELSE 0 END),'.$this->decimalplaces.') AS truckstockvalue',
						'FORMAT((FLOOR((isd.endstockqty + isd.unloadqty)/im.unitspercase ) * isd.stdsalescaseprice) + (CASE WHEN (isd.endstockqty + isd.unloadqty) % im.unitspercase <> 0 THEN ((isd.endstockqty + isd.unloadqty) % im.unitspercase) * isd.stdsalesprice ELSE 0 END),'.$this->decimalplaces.') AS endstockvalue');
		
		if($altcode_status) {
			$columns_array[0] =  'im.alternatecode';
		}
		else {
			$columns_array[0] =  'im.actualitemcode';
		}
		
		
		// IF EXTRA PARAMS ARE REQUIRED
		$ex_param = "";
		$additional_where_condition = array();
		if(isset($params["key"]) && $params["key"]>0) {
			$ex_param = "/key/".$params["key"];
			$additional_where_condition[] = ' ( sed.routekey = "'.$params["key"].'" )';
		}
		
		// ARRAY FOR GRID PAGINATION
		$pagingparams = array(
					 "show_grid_heading" => false,
					 "grid_heading_message" => $this->translate->_('Overview'),
					 "show_records_per_page" => isset($formdata["show_records_per_page"])?$formdata["show_records_per_page"]:100,
					 "show_searchbox" => false,
					 "show_selectbox" => false,
					 "show_editlink" => false,
					 "show_deletelink" => false,
					 "show_deleteall" => false,
					 "show_datasorting" => '1',
					 "primaryid" => "sed.routekey",
					 "currentlink" => array("/".$params['module']."/".$params['controller']."/".$params['action'].$ex_param),					 
					 "nodata_message" => $this->translate->_('No Record(s) Found'),
					 "fetch_columns_inquery" => $columns_array,
					 "show_columns" => $columns_show,					 
					 "additional_where" => $additional_where_condition,
					 "show_top_columns" => true,
					 "show_top_columns_value" => array(array("2",""),array("2","Opening Qty"),array("2","Load Qty"),array("2","Transfer In Qty"),array("2","Transfer Out Qty"),array("2","Sales Qty"),array("2","Good Return Qty"),array("2","Damaged Qty"),array("2","Promo Qty"),array("2","Free Qty"),array("2","Closing Qty"),array("1",""),array("1",""),array("1",""),array("1","")),
					 "show_columns_right_side" => array("beginqtycase","beginqtypcs","loadqtycase","loadqtypcs","loadaddqtycase","loadaddqtypcs","loadcutqtycase","loadcutqtypcs","salesqtycase","salesqtypcs","returnqtycase","returnqtypcs","damagedqtycase","damagedqtypcs","expiryqtycase","expiryqtypcs","freesampleqtycase","freesampleqtypcs","manualfreeqtycase","manualfreeqtypcs","endqtycase","endqtypcs","openingstockvalue","loadedvalue","truckstockvalue","endstockvalue"),
					 //'openingstockvalue','loadedvalue','truckstockvalue','endstockvalue'
					 "show_header_right_side"=>array($this->translate->_('Truck Stock Value'),$this->translate->_('Opening Value'),$this->translate->_('Loaded Value'),$this->translate->_('Closing Value')),
					 "show_total_columns"=>true,
					 "show_total_columns_value"=>array("beginqtycase"=>"0","beginqtypcs"=>"0","loadqtycase"=>"0","loadqtypcs"=>"0","loadaddqtycase"=>"0","loadaddqtypcs"=>"0","loadcutqtycase"=>"0","loadcutqtypcs"=>"0","salesqtycase"=>"0","salesqtypcs"=>"0","returnqtycase"=>"0","returnqtypcs"=>"0","damagedqtycase"=>"0","damagedqtypcs"=>"0","expiryqtycase"=>"0","expiryqtypcs"=>"0","freesampleqtycase"=>"0","freesampleqtypcs"=>"0","manualfreeqtycase"=>"0","manualfreeqtypcs"=>"0","endqtycase"=>0,"endqtypcs"=>0,"openingstockvalue"=>"1","loadedvalue"=>"1","truckstockvalue"=>1,"endstockvalue"=>1),
					 "show_total_columns_msg"=>array("itemdescription","Total",$this->decimalplaces),
					 );

		
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
	
		
		// called stored procedure for counter
		$result = $this->SFA_Comman->executequery('CALL sp_get_hhctransaction_summary_viewinventorysummarygrid(?,?,?,?,?,?,?)',$param_array,'');    
		$data_arr["count"] 		= $result[0][0]['counter'];	
		$data_arr["data"][0] 	= $result[1];
		
		$this->view->pagegridshow =  $pagingshow->summary_showdatagrid($data_arr);
		$this->view->addScriptPath(APPLICATION_PATH."/theme/default/datagrid/");
	
		$this->render("ajaxgrid");
    }
	/**
    * @name       customerinventoryAction
    * @since      01-02-2012
    * @version    Release: 1
    * @author     M@M <miral@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This action is for display Customer Inventory Summary
    *
    */
    public function customerinventoryAction()
    {
		$this->view->params = $params = $this->getRequest()->getParams();
        $this->view->formdata = $formdata = $this->_request->getPost();
		
		$cols_array 	= array('DISTINCT rm.routecode','rm.routename','rm.salesmancode','DATE_FORMAT(coc.visitstartdate,"%Y-%m-%d") visitdate','cm.customercode','cm.alternatecode','cm.customername','coc.primary_id AS edit_del_primary_id');
		$columns_show 	= array($this->translate->_('Route Code'),$this->translate->_('Route Name'),$this->translate->_('Salesman Code'),$this->translate->_('Visit Date'),$this->translate->_('Customer Code'),$this->translate->_('Alternate Code'),$this->translate->_('Customer Name'));
		
		
		$Common_NameSpace = new Zend_Session_Namespace('CustomerInventory');
		
		$last_url 		= htmlspecialchars($_SERVER['HTTP_REFERER']);
		$end_last_url 	= explode('/',$last_url);
		
		if(end($end_last_url) == 'customerinventory' || strpos($last_url,'viewcustomerinventory') || strpos($last_url,'/customerinventory/' )) {
			
			$sel_date 	= ($formdata["txtdate"] != '') ? $formdata["txtdate"] : $Common_NameSpace->tdate;
		}
		else {
			$sel_date 	= ($formdata["txtdate"] != '') ? $formdata["txtdate"] : date('d-m-Y');
		}
		
		// ADD DATE VALUE IN SESSION
		if($sel_date != '') {
			$Common_NameSpace->tdate 	= $sel_date;
			$this->view->date			= $sel_date;
		} else {
			$Common_NameSpace->tdate 	= date('d-m-Y');
			$this->view->date			= date('d-m-Y');
		}
		
		$additional_where_condition = array();		
		$additional_where_condition[] = " coc.visitstartdate = \'".date("Y-m-d",strtotime(str_replace('/', '-', $Common_NameSpace->tdate)))."\'";
		
		
		// prepare the configuration for grid
		$pagingparams = array(
				"show_grid_heading" => true,
				"grid_heading_message" => $this->translate->_('Overview'),
				"show_records_per_page" => isset($formdata["show_records_per_page"])?$formdata["show_records_per_page"]:10,
				"show_searchbox" => true,
				"searchbox_value" => isset($formdata["grid_search_text"])?$formdata["grid_search_text"]:"",
				"show_selectbox" => false,
				"show_editlink" => false,				
				"show_deletelink" => false,			
				"show_deleteall" => false,
				"primaryid" => "coc.customercode",
				"show_extralink" => true,
				"extralink" => array(array("View","/".$params['module']."/".$params['controller']."/viewcustomerinventory/id/#pattern#","#pattern#")),
				"nodata_message" => $this->translate->_('No Record(s) Found'),
				"fetch_columns_inquery" => $cols_array,
				"show_columns" => $columns_show,
				"additional_where" => $additional_where_condition
				);
		
		// create grid class object
		$pagingshow = new SFA_Paging($pagingparams);
		
		// call common function of grid class
		$get_return_vals = $pagingshow->commnfunc();
		
		// call the stored procedure for fetch the data
		$param_array = array();
		$param_array[1] = '1';
		$param_array[2] = '';
		$param_array[3] = $get_return_vals['order_columns_name'];
		$param_array[4] = $get_return_vals['order_type'];
		$param_array[5] = $get_return_vals['offset'];
		$param_array[6] = (int)$get_return_vals['show_records_per_page'];
		$param_array[7] = implode(", ",$pagingparams["fetch_columns_inquery"]);
		$param_array[8] = strlen($get_return_vals['where_condition'])>0?$get_return_vals['where_condition']:'';		
		
		// called stored procedure for counter
		$result = $this->SFA_Comman->executequery('CALL sp_get_hhctransaction_summary_customerinventory(?,?,?,?,?,?,?,?)',$param_array,'');
	
		$data_arr["count"] 			= $result[0][0]['counter'];
		$data_arr["data"][0] 		= $result[1];
		
		
		// pass the data in summary_showdatagrid() function & create a final variable for view
		$this->view->pagegridshow =  $pagingshow->summary_showdatagrid($data_arr);
		
		$this->view->addScriptPath(APPLICATION_PATH."/theme/default/datagrid/");
    }
	 /**
    * @name       viewcustomerinventory
    * @since      02-02-2012
    * @version    Release: 1
    * @author     HD <hiren.d@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This action is for add begin stock
    *
    */
    public function viewcustomerinventoryAction()
    {
		$this->view->params = $params = $this->getRequest()->getParams();
		
		$Common_NameSpace = new Zend_Session_Namespace('CustomerInventory');
        
		// Begin Stock Datagrid
		$ex_param = "";
		if(isset($params["id"]) && $params["id"]>0)
			$ex_param = "/key/".$params["id"]."/key1/".$Common_NameSpace->tdate;
	
		$this->view->itemgrid    = $this->view->BaseUrl("/".$params['module']."/".$params['controller']."/viewcustomerinventorygrid".$ex_param);
	}
	
	/**
    * @name       viewcustomerinventorygridAction
    * @since      22-02-2012
    * @version    Release: 1
    * @author     AS <alpesh@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This action is use for view inventory summary detail
    */
    public function viewcustomerinventorygridAction()
    {
		$this->view->params = $params = $this->getRequest()->getParams();
		
        // IF EXTRA PARAMS ARE REQUIRED
        $ex_param = "";
        if(isset($params["key"]) && $params["key"]>0)
            $ex_param = "/key/".$params["key"]."/key1/".$params["key1"];
		
		$columns_show  	= array($this->translate->_('Item Code'),$this->translate->_('Item Description'),$this->translate->_('UPC'),
								$this->translate->_('Location1'),$this->translate->_('Location2'),$this->translate->_('Location3'));
		
		$cpanel				= $this->SFA_Comman->getaltcodestatus();
		$altcode_status		= $cpanel["Use Alternate Code"]['status'];
		
		$columns_array 	= array('im.alternatecode','im.itemdescription','im.unitspercase','CONCAT(cid.qtyloc1case,"/",cid.qtyloc1each) location1','CONCAT(cid.qtyloc2case,"/",cid.qtyloc2each) location2','CONCAT(cid.qtyloc3case,"/",cid.qtyloc3each) location3');
		
		if($altcode_status) {
			$columns_array[0] =  'im.alternatecode';
		}
		else {
			$columns_array[0] =  'im.actualitemcode';
		}
		
		
		// IF EXTRA PARAMS ARE REQUIRED
		$ex_param = "";
		$additional_where_condition = array();
		if(isset($params["key"]) && $params["key"]>0){
			$date  = date("Y-m-d",strtotime(str_replace('/', '-', $params["key1"])));
			$additional_where_condition[] = ' ( coc.primary_id = "'.$params["key"].'" )';
			$additional_where_condition[] = ' ( coc.visitstartdate = "'.$date.'" )';
		}
		
		// ARRAY FOR GRID PAGINATION
		$pagingparams = array(
					 "show_grid_heading" => false,
					 "grid_heading_message" => $this->translate->_('Overview'),
					 "show_records_per_page" => isset($formdata["show_records_per_page"])?$formdata["show_records_per_page"]:10,
					 "show_searchbox" => false,
					 "show_selectbox" => false,
					 "show_editlink" => false,
					 "show_deletelink" => false,
					 "show_deleteall" => false,
					 "show_datasorting" => '1',
					 "primaryid" => "coc.primary_id",
					 "currentlink" => array("/".$params['module']."/".$params['controller']."/".$params['action'].$ex_param),					 
					 "nodata_message" => $this->translate->_('No Record(s) Found'),
					 "fetch_columns_inquery" => $columns_array,
					 "show_columns" => $columns_show,					 
					 "additional_where" => $additional_where_condition,
					 "show_top_columns" => false,
					 "show_top_columns_value" => array(array("2",""),array("2","Opening Qty"),array("2","Load Qty"),array("2","Transfer In Qty"),array("2","Transfer Out Qty"),array("2","Sales Qty"),array("2","Good Return Qty"),array("2","Damaged Qty"),array("2","Expiry Qty"),array("2","Promo Qty"),array("2","Free Qty"),array("2","Closing Qty"),array("1",""),array("1",""),array("1",""),array("1","")),
					 "show_columns_right_side" => array('openingstockvalue','loadedvalue','truckstockvalue','endstockvalue'),
					 "show_header_right_side"=>array($this->translate->_('Truck Stock Value'),$this->translate->_('Opening Value'),$this->translate->_('Loaded Value'),$this->translate->_('Closing Value')),
					 "show_total_columns"=>false,
					 "show_total_columns_value"=>array("beginqtycase"=>"1","beginqtypcs"=>"1","loadqtycase"=>"1","loadqtypcs"=>"1","loadaddqtycase"=>"1","loadaddqtypcs"=>"1","loadcutqtycase"=>"1","loadcutqtypcs"=>"1","salesqtycase"=>"1","salesqtypcs"=>"1","returnqtycase"=>"1","returnqtypcs"=>"1","damagedqtycase"=>"1","damagedqtypcs"=>"1","expiryqtycase"=>"1","expiryqtypcs"=>"1","freesampleqtycase"=>"1","freesampleqtypcs"=>"1","manualfreeqtycase"=>"1","manualfreeqtypcs"=>"1","endqtycase"=>1,"endqtypcs"=>1,"openingstockvalue"=>"1","loadedvalue"=>"1","truckstockvalue"=>1,"endstockvalue"=>1),
					 "show_total_columns_msg"=>array("itemdescription","Total",$this->decimalplaces),
					 );

		
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
	
		
		// called stored procedure for counter
		//$result = $this->SFA_Comman->executequery('CALL sp_get_hhctransaction_summary_viewinventorysummarygrid(?,?,?,?,?,?,?)',$param_array,''); 
$result = $this->SFA_Comman->executequery('CALL sp_get_hhctransaction_summary_viewcustoemrinventorygrid(?,?,?,?,?,?,?)',$param_array,'');			
		$data_arr["count"] 		= $result[0][0]['counter'];	
		$data_arr["data"][0] 	= $result[1];
		
		$this->view->pagegridshow =  $pagingshow->summary_showdatagrid($data_arr);
		$this->view->addScriptPath(APPLICATION_PATH."/theme/default/datagrid/");
	
		$this->render("ajaxgrid");
    }
}