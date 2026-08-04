<?php
/**
* @name       CloudtranController
* @since
* @version    Release: 1
* @author     M@M <miral@elantechnologies.com>
* @copyright  Elan Technologies
* @param
*
* This controller is manage hhctransaction module.
*/


class Hhctransaction_ProcessloadController extends Hhctransaction_Library_Controller_Action_Abstract
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
		$this->SFA_Comman	= new SFA_Comman();
		
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
		$this->view->colan		= $this->translate->_('Colan');
		
		
		$this->decimalplaces 		= $this->SFA_Comman->getdecimalplaces();
		$this->view->decimalplaces 	= $this->SFA_Comman->getdecimalplaces();
		$this->view->sec_lang		= $this->SFA_Comman->getsecondlanguage();
		$this->sec_lang 		= $this->view->sec_lang;
		$this->view->header = $this->translate->_('Header');
		$this->view->detail = $this->translate->_('Detail');
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
    * @name       processloadAction
    * @since
    * @version    Release: 5
    * @author     HD
    * @copyright  Elan Technologies
    * @param
    *
    * This Action is use for Processload
    */
    public function processloadAction()
    {	
        $this->view->params = $params = $this->getRequest()->getParams();
        $this->view->formdata = $formdata = $this->_request->getPost();
	
        
	
		// IF EXTRA PARAMS ARE REQUIRED
		$ex_param = "";
		if(isset($params["key"]) && $params["key"]>0)
			$ex_param = "/key/".$params["key"];
	
			
	
			//declare view variables
		$this->view->title	= $this->translate->_('Process Load');
	
			//variable declaration for grid title
		$code 			= $this->translate->_('Code');	
		
		$cols_array 	= array('ith.routecode',
								'get_name_type_base(ith.routecode,3) as routename',
								'get_name_type_base(salesmancode,2) as salesmancode',
								'loadnumber',
								'FLOOR(SUM(itd.quantity/unitspercase)) as cases',
								'SUM(itd.quantity % unitspercase) AS units',
								'FORMAT(SUM((FLOOR(itd.quantity/unitspercase) * itemcaseprice)+((itd.quantity % unitspercase) * itemprice)),'.$this->decimalplaces.') AS loadvalue',
								'ith.detailkey as edit_del_primary_id');
		
		$columns_show 	= array($this->translate->_('Route Code'),
								$this->translate->_('Route Name'),
								$this->translate->_('Salesman'),
								$this->translate->_('Load Number'),
								$this->translate->_('Cases'),
								$this->translate->_('Pieces'),
								$this->translate->_('Load Value'));
		
		$Common_NameSpace = new Zend_Session_Namespace('processload');
		if($formdata['btnreset'] == 'RESET')
		{
			$formdata["txtdate"] 	= '';
			$Common_NameSpace->tdate	= '';
		}		
		if(strpos($last_url,'processload'))
		{
			$sel_date = ($formdata["txtdate"] != '') ? $formdata["txtdate"] : $Common_NameSpace->tdate;
		}
		else
		{
			$sel_date = ($formdata["txtdate"] != '') ? $formdata["txtdate"] : date('d-m-Y');
		}	
		
		// ADD DATE VALUE IN SESSION
		if($sel_date != '') {
			$Common_NameSpace->tdate 	= $sel_date;
			$this->view->date			= $sel_date;
		}
		else
		{
			$Common_NameSpace->tdate 	= date('d-m-Y');
			$this->view->date			= date('d-m-Y');
		}
	
		// ADDITIONAL WHERE CONDITION
		if($Common_NameSpace->tdate)
			$additional_where_condition[] = " (transactiondate BETWEEN \'".date("Y-m-d 00:00:00",strtotime(str_replace('/', '-', $Common_NameSpace->tdate)))."\' AND \'".date("Y-m-d 23:59:59",strtotime(str_replace('/', '-', $Common_NameSpace->tdate)))."\' )";
            $additional_where_condition[] = " ith.record_flag = \'0\' ";
		
		// prepare the configuration for grid
		$pagingparams = array(
				"show_grid_heading" => true,
				"grid_heading_message" => $this->translate->_('Overview'),
				"show_records_per_page" => isset($formdata["show_records_per_page"])?$formdata["show_records_per_page"]:10,
				"show_searchbox" => true, "searchbox_value" => isset($formdata["grid_search_text"])?$formdata["grid_search_text"]:"",
				"show_selectbox" => false,
				"show_editlink" => false,				
				"show_deletelink" => false,			
				"show_deleteall" => false,
				"primaryid" => "detailkey",
				'show_extralink' => true,
				'extralink' => array(array("View","/".$params['module']."/processload/editprocessload/id/#pattern#","#pattern#")),
				"nodata_message" => $this->translate->_('No Record(s) Found'),
				"fetch_columns_inquery" => $cols_array,
				"show_columns" => $columns_show,
				"show_columns_right_side" =>array('loadvalue'),
				"additional_where" => $additional_where_condition,
				"show_header_right_side" =>array($this->translate->_('Load Value'))
				);
		
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
		$param_array[9] = '';
		// called stored procedure for counter
		$result = $this->SFA_Comman->executequery('CALL sp_get_transaction_processload(?,?,?,?,?,?,?,?,?)',$param_array,'');
		$data_arr["count"]		= count($result[1]);	
		$data_arr["data"][0] 	= $result[1];
		
		// pass the data in summary_showdatagrid() function & create a final variable for view
		$this->view->pagegridshow =  $pagingshow->summary_showdatagrid($data_arr);
		
		$this->view->addScriptPath(APPLICATION_PATH."/theme/default/datagrid/");
    }
    
	  
    
    
    
	/**
    * @name       addprocessloadAction
    * @since
    * @version    Release: 5
    * @author     HD
    * @copyright  Elan Technologies
    * @param
    *
    * This Action is use for add Processload
    */
    public function addprocessloadAction()
    {
		$this->view->params 	= $params 	= $this->getRequest()->getParams();
        $this->view->formdata 	= $formdata = $this->_request->getPost();
		
		if(count($formdata) > 0 && isset($formdata['btnsave']))
		{
			
			 $param_array =array();
			 $param_array[1] =$this->view->formdata['ddlload'];
			 $param_array[2] =$this->view->formdata['ddlroute'];
			 $param_array[3] =$this->view->decimalplaces ;
			 $param_array[4] =$this->view->formdata['txtroutekey'];
			 $param_array[5] =$this->view->formdata['hdnsalesman'];
			  
			 $result =  $this->SFA_Comman->executequery('CALL sp_add_transaction_processload_addprocessload(?,?,?,?,?)',$param_array,'');
			SFA_Message::setMsg($this->translate->_('New Record'));
			
			 $this->_helper->redirector('processload', 'processload', 'hhctransaction');
		}
		elseif($params['id'] > 0)
		{
			
		}
		else
		{
			$res 					= $this->SFA_Comman->executequery('CALL sp_get_hhctransaction_cloudtran_addprocessload()','','');
			$this->view->routeinfo 	= $res[0];
		}
	}
    /**
    * @name       loaditemdetailAction
    * @since      20-02-2012
    * @version    Release: 8
    * @author     M@M <miral@elantechnologies.com>
    * @author     GP <gayatri@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This action is for display ajax item grid in add invoice page
    * Gpatel : added top colum array, footer colum array and right alignment array
    */
    public function loaditemdetailAction()
    {
		$params 			= $this->getRequest()->getParams();
        
		
		// Session
		
		
		
		
		// IF EXTRA PARAMS ARE REQUIRED
		$ex_param = "";
		if(isset($params["routecode"]) && $params["routecode"] != "")
			$ex_param = "/routecode/".$params["routecode"];
        if(isset($params["loadid"]) && $params["loadid"] != "")
			$ex_param = "/loadid/".$params["loadid"];
		if(isset($params["routekey"]) && $params["routekey"] != "")
			$ex_param = "/routekey/".$params["routekey"];

				
		
		//$columns_array	= array('get_item_code(itemcode) as itemcode','im.itemdescription',
		//						'FORMAT(SUM(cases),'.$this->decimalplaces.') as cases',
		//						'FORMAT(SUM(units),'.$this->decimalplaces.') as units',
		//						'unitspercase',
		//						'format(IF(sld.salesprice IS NULL ,im.defaultsalesprice,sld.salesprice ),'.$this->decimalplaces.') AS salesprice'								
		//					);
		
		$Settings_NameSpace = new Zend_Session_Namespace('Settings');
		
		if($Settings_NameSpace->cpanel['Enabled Batch And Expiry']['status'] == '1')
		{
			$columns_show  = array( $this->translate->_('Item Code'),$this->translate->_('Item Description'),$this->translate->_('UPC'),
									$this->translate->_('Batch Number'),$this->translate->_('Expiry Date'),$this->translate->_('Case'),
									$this->translate->_('Pieces'));
			
			$columns_array	= array('get_item_code(itemcode) as itemcode','im.itemdescription','unitspercase',
									'batchnumber','DATE_FORMAT(expirydate,"%d-%m-%Y") as expirydate','SUM(cases) as cases','SUM(units) as units');
		}
		else
		{
			$columns_show  = array( $this->translate->_('Item Code'),$this->translate->_('Item Description'),$this->translate->_('Case'),
									$this->translate->_('Pcs'),$this->translate->_('UPC'),$this->translate->_('Sales Price'));
			
			$columns_array	= array('get_item_code(itemcode) as itemcode','im.itemdescription',
								'SUM(cases) as cases',
								'SUM(units) as units',
								'unitspercase',
								'format(IF(sld.salesprice IS NULL ,im.defaultsalesprice,sld.salesprice ),'.$this->decimalplaces.') AS salesprice'
							);
		}
		
	    
	
	    $additional_where_condition[] = " sld.loadperiodnumber = ".$params["loadid"]." and sld.routecode = ".$params["routecode"]." and sld.ddate =DATE(NOW()) GROUP BY actualitemcode,sld.batchnumber";
		
		// prepare the configuration for grid
		$pagingparams = array(
			"show_grid_heading" => false,
			"grid_heading_message" => $this->translate->_('Overview'),
			"show_records_per_page" => isset($formdata["show_records_per_page"])?$formdata["show_records_per_page"]:25,
			"show_searchbox" => false,
			"show_selectbox" => false,
			"show_editlink" => false,
			"show_deletelink" => false,			
			"show_deleteall" => false,
			"primaryid" => "primary_key",
			"currentlink" => array("/hhctransaction/processload/loaditemdetail".$ex_param),
			"editlink" => array("/id/#pattern#/edit/yes","#pattern#"),
			"deletelink" => array("/id/#pattern#/delete/yes","#pattern#"),
			"nodata_message" => $this->translate->_('No Record(s) Found'),
			"fetch_columns_inquery" => $columns_array,			
			"show_columns" => $columns_show,			
			"additional_where" => $additional_where_condition,
			);	
        
        $pagingshow = new SFA_Ajaxpaging($pagingparams);

		// call common function of grid class
		$get_return_vals = $pagingshow->commnfunc();
		
		//print_r($get_return_vals['where_condition']);
		
		// call the stored procedure for fetch the data
		$param_array 	= array();
		$param_array[1] = '1';
		$param_array[2] = $get_return_vals['order_columns_name'];
		$param_array[3] = $get_return_vals['order_type'];
		$param_array[4] = $get_return_vals['offset'];
		$param_array[5] = (int)$get_return_vals['show_records_per_page'];
		$param_array[6] = implode(", ",$pagingparams["fetch_columns_inquery"]);
		$param_array[7] = strlen($get_return_vals['where_condition'])>0?$get_return_vals['where_condition']:'';
		//$param_array[8] = $params['routecode'];
		//$param_array[9] = $params['loadid'];
		//$param_array[10] = $params['routekey'];
	
		//echo "<pre>";
		//print_r($param_array);
		
		// called stored procedure for counter
		$result = $this->SFA_Comman->executequery('CALL sp_get_transaction_processload_loaditemdetail_grid(?,?,?,?,?,?,?)',$param_array,'');   
	
		$data_arr["count"] 	= $result[0][0]['counter'];
		$data_arr["data"][0]	= $result[1];
	
	
        $this->view->pagegridshow =  $pagingshow->summary_showdatagrid($data_arr);
        $this->view->addScriptPath(APPLICATION_PATH."/theme/default/datagrid/");

        $this->render("ajaxgrid");
    }

   public function editprocessloadAction()
   {
	$params 		= $this->getRequest()->getParams();
	$this->view->params	= $params;
        
	
	 $param_array 		=  array();
	 $param_array[1]	=  $params['id'];
	 $result 		=  $this->SFA_Comman->executequery('CALL sp_get_hhctransaction_processload_editprocessload(?)',$param_array,'');
	 $this->view->result    =  $result[0][0];
	 
	 
   }
   
}