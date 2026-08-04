<?php
/**
* @name       RoutedepositsummaryController
* @since      06-10-2012
* @version    Release: 1
* @author     PT <pankil@elantechnologies.com>
* @copyright  Elan Technologies
* @param
*
* This controller is manage report module.
*/
class Reports_OtherreportController extends Reports_Library_Controller_Action_Abstract
{
    /**
    * @name       init
    * @since      06-10-2012
    * @version    Release: 1
    * @author     PT <Pankil@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This is the default function for all Actions.
    *
    */
    protected $report_session ;
    public function init()
    {
        $this->translate 	= Zend_Registry::get('Zend_Translate');
        $this->view->colan	= $this->translate->_('Colan');
		$this->view->required	= $this->translate->_('Required');
        $this->SFA_Comman	= new SFA_Comman();
        
        $this->currentUser = SFA_Loginauth::getIdentity();	
        if(!isset($this->currentUser) || empty($this->currentUser))
        {
           
			$url = $this->view->baseUrl();
			echo '<script type="text/javascript">window.location="'.$url.'";</script>';
			exit;
        }
        
        $this->sec_lang 	  = $this->view->sec_lang;
        $this->decimalplaces  = $this->view->decimalplaces	= $this->SFA_Comman->getdecimalplaces();
        $this->view->sec_lang = $this->SFA_Comman->getsecondlanguage();
        
        $this->report_session = new Zend_Session_Namespace('Re_selfservicereport');
        $this->session = $this->view->session = new Zend_Session_Namespace('SESSION');
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
            if(!$this->checkaccess("read")) {
                
                $this->_forward('noaccess','aclaccess','home', array("actiontype"=>"read","modulename"=>$this->currentmodulename));
                
            }
        }
        
        /**
         *      Acl Code end
         */
    }
	
    public function otherreportAction()
    {
		
        $this->view->params 	= $params = $this->getRequest()->getParams();
        $this->view->formdata  = $formdata = $this->_request->getPost();
		
		 $Menu_NameSpace     = new Zend_Session_Namespace('Menu');
		 $menu_array         = $Menu_NameSpace->header_menu;
		 if($menu_array['Enabled Channel Master']['status'] == 1 ) {
			 $this->view->showchannel = "true";
		 }
		 else{
			 $this->view->showchannel = "false";
		 }
		
		/*Filter By Customer*/
		  
		$this->view->filterbycustomer = $this->filterbycustomer_arr = array();
        $this->filterbycustomer_arr[0]['val'] 	= 'Customer Category';
		$this->filterbycustomer_arr[1]['val'] 	= 'Customer Channel';
		$this->filterbycustomer_arr[2]['val'] 	= 'Customer';
		$this->filterbycustomer_arr[0]['id'] 	= '1';
		$this->filterbycustomer_arr[1]['id'] 	= '2';
        $this->filterbycustomer_arr[2]['id'] 	= '3';
		
		
		$this->view->filterbycustomer = $this->filterbycustomer_arr;
		
		
		
		/*Filter By Items*/
		$this->view->filterbyitem = $this->filterbyitem = array();
        $this->filterbyitem[0]['val'] 	= 'Company Group';
        $this->filterbyitem[1]['val'] 	= 'Major Category';
        $this->filterbyitem[2]['val'] 	= 'Sub Major Category';
        $this->filterbyitem[3]['val'] 	= 'Item Groups';
        $this->filterbyitem[4]['val'] 	= 'Items';
        $this->filterbyitem[0]['id'] 	= '1';
        $this->filterbyitem[1]['id'] 	= '2';
        $this->filterbyitem[2]['id'] 	= '3';
        $this->filterbyitem[3]['id'] 	= '4';
        $this->filterbyitem[4]['id'] 	= '5';
       
		
		 $this->view->filterbyitem = $this->filterbyitem;
		
		/**/
		
        $this->view->routegrid       = $this->view->BaseUrl("/".$params['module']."/ajaxdata/useraccessgrid");
		$this->view->itemgrid        = $this->view->BaseUrl("/".$params['module']."/ajaxdata/itemgrid");
		$this->view->customergrid    = $this->view->BaseUrl("/".$params['module']."/ajaxdata/customergrid");
		 
        $reportname = $this->SFA_Comman->executequery('CALL sp_combo_get_sfareportname()','','');
		
		$this->view->ddlfilterbyreport = $reportname[0];
		
		 $this->report_session->post = $formdata;
		
		 
		 if($formdata['ddlfilterby'] != "") 
		 {
				$all_search = $formdata["chk"];
		
                for($i=0;$i<count($all_search);$i++)
                {
                    $search_arr = explode("$$",$all_search[$i]);
                    $chk_arr[] = $search_arr[0];
                    $name_arr[] = $search_arr[1];
                }
				   $routecode_str = "";
                if($formdata['ddlfilterby'] != 6) {
                    $param_array = array();
                    $param_array[1] = $formdata['ddlfilterby'];
                    $param_array[2] = implode(",",$chk_arr);
                    
                    $result_arr = $this->SFA_Comman->executequery('CALL sp_report_common_get_report(?,?)',$param_array,'');
                    
                    $routecode_arr = array();
                    
                    for($i=0;$i<count($result_arr[0]);$i++)
                    {
                        $routecode_arr[] = $result_arr[0][$i]["routecode"];
                    }
                    if(!empty($routecode_arr)) {
                        $routecode_str = implode(",",$routecode_arr);
                    }
                } else {
                    $routecode_str = implode(",",$chk_arr);
                }
		 }
		
		   if($formdata['ddlfilterbycustomer'] != "") 
		 {
			
				$all_search = $formdata["chk_customer"];
		
                for($i=0;$i<count($all_search);$i++)
                {
                    $search_arr_customer = explode("$$",$all_search[$i]);
                    $chk_arr_customer[] = $search_arr_customer[0];
                    $name_arr_item[] = $search_arr_customer[1];
                }
				   $customercode_str = "";
                if($formdata['ddlfilterbycustomer'] != 3) {
                    $param_array = array();
                    $param_array[1] = $formdata['ddlfilterbycustomer'];
                    $param_array[2] = implode(",",$chk_arr_customer);  
								
                    $result_arr_customer = $this->SFA_Comman->executequery('CALL sp_report_common_get_report_customercode(?,?)',$param_array,'');
                    
                    $customercode_arr = array();
                    
                    for($i=0;$i<count($result_arr_customer[0]);$i++)
                    {
                        $customercode_arr[] = $result_arr_customer[0][$i]["customercode"];
                    }
                    if(!empty($customercode_arr)) {
                        $customercode_str = implode(",",$customercode_arr);
                    }
                } else {
                    $customercode_str = implode(",",$chk_arr_customer);
                }
		 }
		 
		  if($formdata['ddlfilterbyitem'] != "") 
		 {
				
				$all_search = $formdata["chk_item"];
		
                for($i=0;$i<count($all_search);$i++)
                {
                    $search_arr_item = explode("$$",$all_search[$i]);
                    $chk_arr_item[] = $search_arr_item[0];
                    $name_arr_item[] = $search_arr_item[1];
                }
				   $itemcode_str = "";
                if($formdata['ddlfilterbyitem'] != 5) {
                    $param_array = array();
                    $param_array[1] = $formdata['ddlfilterbyitem'];
                    $param_array[2] = implode(",",$chk_arr_item);                   
                    $result_arr_item = $this->SFA_Comman->executequery('CALL sp_report_common_get_report_itemcode(?,?)',$param_array,'');
                    
                    $itemcode_arr = array();
                    
                    for($i=0;$i<count($result_arr_item[0]);$i++)
                    {
                        $itemcode_arr[] = $result_arr_item[0][$i]["actualitemcode"];
                    }
                    if(!empty($itemcode_arr)) {
                        $itemcode_str = implode(",",$itemcode_arr);
                    }
                } else {
                    $itemcode_str = implode(",",$chk_arr_item);
                }
				
		 }
		 if(count($formdata) > 0){
		 $spname = $formdata['hdnspname'];		
		 $report_name = $formdata['hdnreportname'];

		 
		 if($formdata['hdncount_route'] != "1"){
			 if($routecode_str==""){$routecode = "All";}			 
				else{ $routecode = $routecode_str;}
			 
		 }else{$routecode = "hidden";}
		 
		  if($formdata['hdncount_customer'] != "1"){
			  if($customercode_str==""){$customercode = "All";}			 
				else{  $customercode = $customercode_str;}
			
		 }else{$customercode = "hidden";}
		 
		 if($formdata['hdncount_item'] != "1"){
			  if($itemcode_str==""){$itemcode = "All";}			 
				else{  $itemcode = $itemcode_str;}
			
		 }else{$itemcode = "hidden";}
		 
		  if($formdata['hdncount_from'] != "1"){
			 $fromdate = date("Y-m-d",strtotime($formdata['txt_route_start_date']));
		 }else{$fromdate = "hidden";}
		 
		 if($formdata['hdncount_to'] != "1"){
			  $todate = date("Y-m-d",strtotime($formdata['txt_route_end_date']));
		 }else{$todate = "hidden";}
		 
		  if($formdata['hdncount_groupby'] != "1"){
			   $groupby = $formdata['hdngroupby'];
		 }else{$groupby = "hidden";}
		 
				
		 $this->generatereport($spname,$routecode,$customercode,$itemcode,$fromdate,$todate,$groupby,$report_name);
		 }
    }
	 public function reportparamlistAction()
    {
		//view variable declaration
		$params = $this->getRequest()->getParams();
		
		$param_array 	= array();
		$param_array[1]	= $params['id'];
        $result_arr = $this->SFA_Comman->executequery('CALL sp_report_otherreport_paramlist(?)',$param_array,'');
                   
		echo Zend_Json_Encoder::encode($result_arr[0][0]);
		exit;
    }
	public function generatereport($spname,$routecode,$customercode,$itemcode,$fromdate,$todate,$groupby,$report_name)
	{
		$param_array = array();
        $param_array[1] = $routecode;
        $param_array[2] = $customercode;
        $param_array[3] = $itemcode;
        $param_array[4] = $fromdate;
        $param_array[5] = $todate;
		$param_array[6] = $groupby;

		$result_arr = $this->SFA_Comman->executequery("CALL $spname(?,?,?,?,?,?)",$param_array,'');
		
		
		/*foreach($result_arr[0] as $key => $val){
		$sheet[]=$val;
		$keys=array_keys($val);
		}
		
		
		$out = '';
       
            foreach($keys as $key => $val)
            {
                $out .= $val;
                $out .= ",";
            }
            $out .= "\n";
       
        if(isset($result_arr[0]) && sizeof($result_arr[0]) > 0)
        {
            foreach($result_arr[0] as $key => $val)
            {
                foreach($val as $k=>$v)
                {
                    $v = str_replace(",", "", $v);
                    $out .= trim($v);
                    $out .= ",";
                }
                $out .= "\n";
            }
        }
		
        $filename = time().'.csv';
        header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
        header("Content-Length: " . strlen($out));
		header('Content-Type: text/csv; charset=utf-8; encoding=UTF-8');
		header("Cache-Control: cache, must-revalidate");
		header("Pragma: public");     
        header("Content-Disposition: attachment; filename=$filename");
		echo "\xEF\xBB\xBF";
        echo $out;
        exit;*/
		
		foreach($result_arr[0] as $key => $val){
		$sheet[]=$val;
		$keys=array_keys($val);
	}
	
	if($result_arr[0]!=NULL)
	{
		
	 $report_name = str_replace(' ', '_', $report_name);
	 $objPHPExcel = new PHPExcel();
     $objPHPExcel->setActiveSheetIndex(0); 
	 $worksheet = $objPHPExcel->getActiveSheet();

	 $objPHPExcel->getActiveSheet()->fromArray($keys, null, 'A1');
	 $objPHPExcel->getActiveSheet()->fromArray($sheet, null, 'A2');
	 $objPHPExcel->getActiveSheet()->getStyle("A1:AP1")->getFont()->setBold(true);
	 
	 /*For Width*/
	 $sheet = $objPHPExcel->getActiveSheet();
	$cellIterator = $sheet->getRowIterator()->current()->getCellIterator();
	$cellIterator->setIterateOnlyExistingCells( true );

	foreach( $cellIterator as $cell ) {
			$sheet->getColumnDimension( $cell->getColumn() )->setAutoSize( true );
	}
	/*End For Width*/
	
	 //$objPHPExcel->getActiveSheet()->getColumnDimension()->setAutoSize(true);
	// $objPHPExcel->getActiveSheet()->getColumnDimension()->setWidth("30");
	$objPHPExcel->getActiveSheet()->setTitle($report_name);
	 
	header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
	header('Content-Type: application/vnd.ms-excel; charset=utf-8; encoding=UTF-8');
	header("Cache-Control: cache, must-revalidate");
	header("Pragma: public");
	header("Content-Disposition: attachment;filename=".$report_name.".xls");
	
	$objWriter = \PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel5');
    $objWriter->save('php://output');
    exit;	
	}
	else{
		$this->_helper->redirector('noreport', 'otherreport', 'reports');
	}
	
		
	}
	public function noreportAction()
    {
		
		// prepare the configuration for grid
		$pagingparams = array(
				"show_grid_heading" => true,
				"grid_heading_message" => $this->translate->_('Overview'),
				
				"show_records_per_page" => isset($formdata["show_records_per_page"])?$formdata["show_records_per_page"]:10,				
				"show_searchbox" => true,
				"searchbox_value" => isset($formdata["grid_search_text"])?$formdata["grid_search_text"]:"",
				"show_selectbox" => true,
				"show_editlink" => true,
				"show_deletelink" => false,
				"selected_list" => $checked,
				"show_deleteall" => false,
				"primaryid" => "salesmancode",
				"status_cols" => array(
								array(
								"cols_name" => "activestatus",
								"status_change" => array("0"=>"Inactive","1"=>"Active")
								)
								),
				"editlink" => array("/account/salesman/addsalesman/id/#pattern#/edit/yes/","#pattern#"),
				"nodata_message" => $this->translate->_('No Record(s) Found'),
				"fetch_columns_inquery" => $cols_array,
				"show_columns" => $columns_show
				);

       
		// create grid class object
		$pagingshow = new SFA_Paging($pagingparams);
		
		
		$param_array 	= array();
		$result = $this->SFA_Comman->executequery('CALL sp_get_account_noreport(?,?,?,?,?,?,?,?)',$param_array,'',$downloadCSV,$printData,2,$pagingparams);
	
		$data_arr["count"] 		= $result[0][0]['counter'];
		$data_arr["data"][0] 	= $result[1];
		
		// pass the data in summary_showdatagrid() function & create a final variable for view
		$this->view->pagegridshow =  $pagingshow->summary_showdatagrid($data_arr);
		
		$this->view->addScriptPath(APPLICATION_PATH."/theme/default/datagrid/");
    }
		
}