<?php
/**
* @name       DiscountsummaryController
* @since      20-02-2012
* @version    Release: 1
* @author     PM <pankit@elantechnologies.com>
* @copyright  Elan Technologies
* @param
*
* This controller is manage report module.
*/
class Reports_CustomerassetsController extends Reports_Library_Controller_Action_Abstract
{
     /**
    * @name       init
    * @since      05-10-2012
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
        $this->SFA_Comman	= new SFA_Comman();
        
        $this->currentUser = SFA_Loginauth::getIdentity();	
        if(!isset($this->currentUser) || empty($this->currentUser))
        {
           // SFA_Message::setMsg($this->translate->_('Do Login'));
           // //$this->_helper->redirector("index", "index", "home");
			$url = $this->view->baseUrl();
			echo '<script type="text/javascript">window.location="'.$url.'";</script>';
			exit;
        }
        
        $this->sec_lang 	  = $this->view->sec_lang;
        $this->decimalplaces  = $this->view->decimalplaces	= $this->SFA_Comman->getdecimalplaces();
        $this->view->sec_lang = $this->SFA_Comman->getsecondlanguage();
        
        $this->report_session = new Zend_Session_Namespace('Re_customerassets');
        $this->css 				= $this->translate->_('CSS');
		$this->view->css 		= $this->css;
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
	
  /**
    * @name       discountsummaryAction
    * @since      05-10-2012
    * @version    Release: 1
    * @author     PT <pankil@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This action is for display discount summary
    *
    */
    public function customerassetsAction()
    {
        $this->view->params 	= $params = $this->getRequest()->getParams();
        $this->view->formdata  = $formdata = $this->_request->getPost();
        
        $result_arr = $this->SFA_Comman->executequery('CALL sp_report_daily_wastagestock_detail()','','');
        
        $this->view->cusotomer_list = $result_arr[0];
        $this->view->route_list = $result_arr[1];
        $this->view->itemgrid    = $this->view->BaseUrl("/".$params['module']."/ajaxdata/useraccessgrid");
        
        $this->report_session->post = array();
   }


    /**
    * @name       totalsalesbyhierarchyAction
    * @since      04-10-2012
    * @version    Release: 1
    * @author     PT <pankil@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This action is for display daily sales sheet report
    *
    */
    public function indexAction()
    {
        $this->_helper->layout->setLayout('jqreport');
        $this->view->params 	= $params = $this->getRequest()->getParams();
        $this->view->formdata  = $formdata = $this->_request->getPost();
        
        $this->report_session->post = $formdata;
        
        $this->view->ReportTitle = $this->translate->_("Customer Assets");
        $this->view->pageHeaderTitle  = $this->translate->_('Date');
        $this->view->pageHeadervalue  =  date("m/d/Y h:i:s");
        
	if($formdata['ddlfilterby'] != "") {
            if(isset($formdata['chkall']) && $formdata['chkall'] == "on") {
                $this->report_session->routecode_str = "";
            } else {
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
                
                $this->report_session->routecode_str = $routecode_str;
            }
        }
        if($formdata['chkall'] == "on") {
            $title_val = "All ".$this->filter_arr[$formdata['ddlfilterby']];
        } elseif(isset($name_arr) && !empty($name_arr)) {
            $title_val = implode(", ",$name_arr);
        } else {
            $title_val = "";
        }
	
       $this->view->searchParams  =  array(
                                            array("title"=> "Customer",
                                                  "value" => $formdata['ddlcustomer_selected']),
					    array("title"=> $this->filter_arr[$formdata['ddlfilterby']],
                                                  "value" => $title_val),
                                            array("title"=> "From Date",
                                                  "value" => ($formdata['txt_route_start_date'] != "" ) ? date("d M Y",strtotime($formdata['txt_route_start_date'])) : ""),
                                            array("title"=> "To Date",
                                                  "value" => ($formdata['txt_route_end_date'] != "" ) ? date("d M Y",strtotime($formdata['txt_route_end_date'])) : "")
                                            );
        $this->report_session->searchParams = $this->view->searchParams;
        
        $this->view->xlsexport_link = $this->view->baseUrl()."/reports/customerassets/export";
        $this->view->cvsexport_link = $this->view->baseUrl()."/reports/customerassets/exportcsv";
    }
    /**
      * @name       discountsummarydataAction
      * @since      05-10-2012
      * @version    Release: 1
      * @author     PT <pankil@elantechnologies.com>
      * @copyright  Elan Technologies
      * @param
      *
      * This action fetch customer pending request data
      *
      */
    public function customerassetsdataAction()
    {
        $this->view->params = $params = $this->getRequest()->getParams();
        $this->view->formdata = $formdata = $this->_request->getPost();
        $this->view->css 		= $this->translate->_('CSS');
        $extra_where = "";
        
        if($this->report_session->post['ddlcustomer'] != "" )
        {
            $extra_where  .= " and coc.customercode = ".$this->report_session->post['ddlcustomer'];
        }
         if(isset($this->report_session->routecode_str) && $this->report_session->routecode_str != "")
        {
            $extra_where .= ' AND sed.routecode IN ('.$this->report_session->routecode_str.')';
        } 
        
        
        if($this->report_session->post['txt_route_start_date'] != "" && $this->report_session->post['txt_route_end_date'] != "")
        {
            $extra_where  .= ' AND sed.routeenddate BETWEEN "'.date("Y-m-d",strtotime($this->report_session->post['txt_route_start_date'])).'" AND "'.date("Y-m-d",strtotime($this->report_session->post['txt_route_end_date'])).'"';
        }
        elseif($this->report_session->post['txt_route_start_date'] != "" && $this->report_session->post['txt_route_end_date'] == "")
        {
            $extra_where  .= ' AND sed.routeenddate >= "'.date("Y-m-d",strtotime($this->report_session->post['txt_route_start_date'])).'"';
        }
        elseif($this->report_session->post['txt_route_start_date'] == "" && $this->report_session->post['txt_route_end_date'] != "")
        {
            $extra_where  .= ' AND sed.routeenddate <= "'.date("Y-m-d",strtotime($this->report_session->post['txt_route_end_date'])).'"';
        }
        
        $page = $_GET['page']; // get the requested page
        $limit = $_GET['rows']; // get how many rows we want to have into the grid
        $sidx = $_GET['sidx']; // get index row - i.e. user click to sort
        $sord = $_GET['sord']; // get the direction
        if(!$sidx) $sidx =1;
        
        if(empty($sidx)) {  $sidx  = "routecode";}
        if(empty($sord)) {  $sord  = "asc";}
    
        $param_array = array();
        $param_array[1] = $extra_where;
        $param_array[2] = $sidx;
        $param_array[3] = $sord;
        $param_array[4] = $limit;
        $param_array[5] = $page;
        $param_array[6] = 0;		
        $result_arr = $this->SFA_Comman->executequery('CALL sp_report_dailyreport_customerassets(?,?,?,?,?)',$param_array,'');
        
        $count  = $result_arr[0][0]['counter'];
        if( $count >0 ) {
            $total_pages = ceil($count/$limit);
        } else {
            $total_pages = 0;
        }
        if ($page > $total_pages) $page = $total_pages;
        $start = $limit*$page - $limit; // do not put $limit*($page - 1)
    
        $responce->page = $page;
        $responce->total = $total_pages;
        $responce->records = $count;
        $i=0;
        
        if(!empty($result_arr[1])){
            foreach($result_arr[1] as $row) {
                
                
                $responce->rows[$i]['id'] = $i;
                $responce->rows[$i]['cell'] = array($row['routecode'].' - '.$row['routename'],$row['VisitDate'],$row['VisitTime'],$row['CustomerCode'],$row['Customer'],
                                                 $row['POSCode'],$row['POS'],$row['SerialNo'],$row['POSStatus'],$row['Remarks']);
                $i++;

            }
        }
        else
        {
          //  $responce->rows[$i]['id']=1;
          //  $responce->rows[$i]['cell']=array("","","","No Record Founds","", "");
        }
        
        echo json_encode($responce);
        exit;
    }
    
    
   
    
    /**
      * @name       exportAction
      * @since      30-11-2012
      * @version    Release: 1
      * @author     PT <pankil@elantechnologies.com>
      * @copyright  Elan Technologies
      * @param
      *
      * This action is for export of the pricing summary
      *
      */
    public function exportAction()
    {
        $this->view->params = $params = $this->getRequest()->getParams();
        $this->view->formdata = $formdata = $this->_request->getPost();
        $this->view->css 		= $this->translate->_('CSS');
        $extra_where = "";
        
        if($this->report_session->post['ddlcustomer'] != "" )
        {
            $extra_where  .= " and coc.customercode = ".$this->report_session->post['ddlcustomer'];
        }
        if(isset($this->report_session->routecode_str) && $this->report_session->routecode_str != "")
        {
            $extra_where .= ' AND sed.routecode IN ('.$this->report_session->routecode_str.')';
        } 
        
        
        if($this->report_session->post['txt_route_start_date'] != "" && $this->report_session->post['txt_route_end_date'] != "")
        {
            $extra_where  .= ' AND sed.routeenddate BETWEEN "'.date("Y-m-d",strtotime($this->report_session->post['txt_route_start_date'])).'" AND "'.date("Y-m-d",strtotime($this->report_session->post['txt_route_end_date'])).'"';
        }
        elseif($this->report_session->post['txt_route_start_date'] != "" && $this->report_session->post['txt_route_end_date'] == "")
        {
            $extra_where  .= ' AND sed.routeenddate >= "'.date("Y-m-d",strtotime($this->report_session->post['txt_route_start_date'])).'"';
        }
        elseif($this->report_session->post['txt_route_start_date'] == "" && $this->report_session->post['txt_route_end_date'] != "")
        {
            $extra_where  .= ' AND sed.routeenddate <= "'.date("Y-m-d",strtotime($this->report_session->post['txt_route_end_date'])).'"';
        }
        
        $page = $_GET['page']; // get the requested page
        $limit = $_GET['rows']; // get how many rows we want to have into the grid
        $sidx = $_GET['sidx']; // get index row - i.e. user click to sort
        $sord = $_GET['sord']; // get the direction
        if(!$sidx) $sidx =1;
        
        if(empty($sidx)) {  $sidx  = "sed.routecode";}
        if(empty($sord)) {  $sord  = "asc";}
    
        $param_array = array();
        $param_array[1] = $extra_where;
        $param_array[2] = "sed.routecode,".$sidx;
        $param_array[3] = $sord;
        $param_array[4] = $limit;
        $param_array[5] = $page;
        $param_array[6] = 1;
        $result_arr = $this->SFA_Comman->executequery('CALL sp_report_dailyreport_customerassets(?,?,?,?,?)',$param_array,'');
       $report_title_height = 15;
        for($i=0;$i<count($this->report_session->searchParams);$i++)
        {
            if($this->report_session->searchParams[$i]["value"] != "")
            {
                if($this->css == 'ar_') {
                    $report_title .= "\r ".$this->report_session->searchParams[$i]["value"]." : ".$this->translate->_($this->report_session->searchParams[$i]["title"]);
                } else {
                    $report_title .= "\r ".$this->translate->_($this->report_session->searchParams[$i]["title"]) . " : ".$this->report_session->searchParams[$i]["value"];
                }
                $report_title_height += 10;
            }
        }
        
        $data = $result_arr[0];
        $data_arr = array();
        
        $column_model_arr = array();
        $data_arr["columns"] = array($this->translate->_('Route'),$this->translate->_('Visit Date'),$this->translate->_('Visit Time'),$this->translate->_('Customer Code'),$this->translate->_('Customer Name'),$this->translate->_('Pos Code'),$this->translate->_('POS'),$this->translate->_('SerialNo'),$this->translate->_('POSStatus'),$this->translate->_('Remarks'));
        $data_arr["columns_config"] =   array(
                                            array("width"=>20),
                                            array("width"=>20),
                                            array("width"=>20),
                                            array("width"=>20),
					    array("width"=>20),
					    array("width"=>20),
					    array("width"=>20),
					    array("width"=>20),
					    array("width"=>20),
					    array("width"=>20),
					    array("width"=>20),
					    array("width"=>20),
					    array("width"=>20)
                                        );
        for($i = 0; $i < count($result_arr[0]); $i++)
        {
           
            
           
           
            $column_model_arr[$result_arr[0][$i]['routecode'].' - '.$result_arr[0][$i]['routename']][] =  array($result_arr[0][$i]['VisitDate'],
                                        $result_arr[0][$i]['VisitTime'],$result_arr[0][$i]['CustomerCode'],$result_arr[0][$i]['Customer'],
										$result_arr[0][$i]['POSCode'],$result_arr[0][$i]['POS'],$result_arr[0][$i]['SerialNo'],$result_arr[0][$i]['POSStatus'],$result_arr[0][$i]['Remarks'] );
        }
        
        $data_arr["columns_model"]          = $column_model_arr;
        $data_arr["config"]["report_title"] = $this->translate->_("Customer Assets").$report_title;
        $data_arr["config"]["report_title_height"] = $report_title_height;
        $data_arr["config"]["file_name"]    = "Customer_Assets";
        $data_arr["config"]["group_level"]  = 1;
        $data_arr["config"]["total_columns"]= count($data_arr["columns"]);
        $data_arr["config"]["group_total"]  = "1";
        $data_arr["config"]["main_total"]   = "1";
        
        
        $SFA_Exportxls = new SFA_Exportxls($data_arr);
        $objPHPExcel = $SFA_Exportxls->exportxls();
        $objWriter = PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel5');
        $objWriter->save('php://output');
        exit;
    }
}
