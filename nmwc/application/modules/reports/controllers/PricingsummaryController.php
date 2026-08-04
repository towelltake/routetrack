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
class Reports_PricingsummaryController extends Reports_Library_Controller_Action_Abstract
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
        
        $this->report_session = new Zend_Session_Namespace('Re_pricingsummary');
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
    public function pricingsummaryAction()
    {
        $this->view->params 	= $params = $this->getRequest()->getParams();
        $this->view->formdata  = $formdata = $this->_request->getPost();
        
        //$result_arr = $this->SFA_Comman->executequery('CALL sp_report_dailyreport_pricingsummary_detail()','','');
        //
        //$this->view->route_list = $result_arr[0];
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
        
        $this->view->ReportTitle = $this->translate->_("Pricing Summary");
        $this->view->pageHeaderTitle  = $this->translate->_('Date');
        $this->view->pageHeadervalue  =  date("m/d/Y h:i:s");
        
        $this->report_session->routecode_str = "";
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
                                            array("title"=> $this->filter_arr[$formdata['ddlfilterby']],
                                                  "value" => $title_val),
                                            array("title"=> "Route End Date - From",
                                                  "value" => ($formdata['txt_route_start_date'] != "" ) ? date("d M Y",strtotime($formdata['txt_route_start_date'])) : ""),
                                            array("title"=> "Route End Date - To",
                                                  "value" => ($formdata['txt_route_end_date'] != "" ) ? date("d M Y",strtotime($formdata['txt_route_end_date'])) : "")
                                            );
        $this->report_session->searchParams = $this->view->searchParams;
        
        $this->view->xlsexport_link = $this->view->baseUrl()."/reports/pricingsummary/export";
        $this->view->cvsexport_link = $this->view->baseUrl()."/reports/pricingsummary/exportcsv";
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
    public function pricingsummarydataAction()
    {
        $this->view->params = $params = $this->getRequest()->getParams();
        $this->view->formdata = $formdata = $this->_request->getPost();
        $this->view->css 		= $this->translate->_('CSS');
        $extra_where = "";
        
        if(isset($this->report_session->routecode_str) && $this->report_session->routecode_str != "")
        {
            $extra_where .= ' AND ih.routecode IN ('.$this->report_session->routecode_str.')';
        }
        
        if($this->report_session->post['txt_route_start_date'] != "" && $this->report_session->post['txt_route_end_date'] != "")
        {
            $extra_where  .= ' AND ih.transactiondate BETWEEN "'.date("Y-m-d",strtotime($this->report_session->post['txt_route_start_date'])).'" AND "'.date("Y-m-d",strtotime($this->report_session->post['txt_route_end_date'])).'"';
        }
        elseif($this->report_session->post['txt_route_start_date'] != "" && $this->report_session->post['txt_route_end_date'] == "")
        {
            $extra_where  .= ' AND ih.transactiondate >= "'.date("Y-m-d",strtotime($this->report_session->post['txt_route_start_date'])).'"';
        }
        elseif($this->report_session->post['txt_route_start_date'] == "" && $this->report_session->post['txt_route_end_date'] != "")
        {
            $extra_where  .= ' AND ih.transactiondate <= "'.date("Y-m-d",strtotime($this->report_session->post['txt_route_end_date'])).'"';
        }
        //if($this->report_session->post['txt_route_start_date'] != "" && $this->report_session->post['txt_route_end_date'] != "")
        //{
        //    $extra_where  .= ' and ih.transactiondate BETWEEN "'.date("Y-m-d",strtotime($this->report_session->post['txt_route_start_date'])).'" AND "'.date("Y-m-d",strtotime($this->report_session->post['txt_route_end_date'])).'"';
        //}
      
        $page = $_GET['page']; // get the requested page
        $limit = $_GET['rows']; // get how many rows we want to have into the grid
        $sidx = $_GET['sidx']; // get index row - i.e. user click to sort
        $sord = $_GET['sord']; // get the direction
        if(!$sidx) $sidx =1;
        
        if(empty($sidx)) {  $sidx  = "transactiondate";}
        if(empty($sord)) {  $sord  = "asc";}
    
        $param_array = array();
        $param_array[1] = $extra_where;
        $param_array[2] = $sidx;
        $param_array[3] = $sord;
        $param_array[4] = $limit;
        $param_array[5] = $page;
        $param_array[6] = 0;
        
        $result_arr = $this->SFA_Comman->executequery('CALL sp_report_dailyreport_pricingsummary(?,?,?,?,?,?)',$param_array,'');
        //pr($result_arr,1);
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
                $customername = ($this->css == 'ar_') ? $row['arbcustomername'] : $row['customername'];
                
                $responce->rows[$i]['id'] = $i;
                $responce->rows[$i]['cell'] = array($row['transactiondate'],$row['transactiontime'],$row['invoicenumber'],$row['code'],
                                                $customername, $row['totalinvoiceamount'],$row['totalfreesampleamount'],($row['totalinvoiceamount'] - $row['totalfreesampleamount'])
                                                );
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
      * @name       pricingsummarysubgriddataAction
      * @since      09-10-2012
      * @version    Release: 1
      * @author     PT <pankil@elantechnologies.com>
      * @copyright  Elan Technologies
      * @param
      *
      * This action fetch subgrid data when invoice number, date and time sent
      *
      */
    public function pricingsummarysubgriddataAction()
    {
        $this->view->params = $params = $this->getRequest()->getParams();
        $this->view->formdata = $formdata = $this->_request->getPost();
        $this->view->css 		= $this->translate->_('CSS');
        $extra_where = "";
        
        if($this->report_session->post['ddlroutecode'] != "" )
        {
            $extra_where  .= " and ih.routecode IN (".$this->report_session->post['ddlroutecode'].")";
        }
        if($this->report_session->post['txt_route_start_date'] != "" && $this->report_session->post['txt_route_end_date'] != "")
        {
            $extra_where  .= ' and ih.transactiondate BETWEEN "'.date("Y-m-d",strtotime($this->report_session->post['txt_route_start_date'])).'" AND "'.date("Y-m-d",strtotime($this->report_session->post['txt_route_end_date'])).'"';
        }
        if(isset($_GET['transactiondate']) && $_GET['transactiondate'] != "")
        {
            $extra_where  .= ' and ih.transactiondate = "'.date("Y-m-d",strtotime($_GET['transactiondate'])).'" ';
        }
        if(isset($_GET['transactiontime']) && $_GET['transactiontime'] != "")
        {
            $extra_where  .= ' and ih.transactiontime = "'.$_GET['transactiontime'].'" ';
        }
        if(isset($_GET['invoicenumber']) && $_GET['invoicenumber'] != "")
        {
            $extra_where  .= ' and ih.invoicenumber = "'.$_GET['invoicenumber'].'" ';
        }
        
        //pr($_GET,1);
        $page = $_GET['page']; // get the requested page
        $limit = $_GET['rows']; // get how many rows we want to have into the grid
        $sidx = $_GET['sidx']; // get index row - i.e. user click to sort
        $sord = $_GET['sord']; // get the direction
        if(!$sidx) $sidx =1;
        
        if(empty($sidx)) {  $sidx  = "transactiondate";}
        if(empty($sord)) {  $sord  = "asc";}
    
        $param_array = array();
        $param_array[1] = $extra_where;
        $param_array[2] = $sidx;
        $param_array[3] = $sord;
        
        
        $result_arr = $this->SFA_Comman->executequery('CALL sp_report_dailyreport_pricingsummary_subgrid(?,?,?)',$param_array,'');
        
        $i =0;
        if(!empty($result_arr[0])){
            foreach($result_arr[0] as $row) {
                $itemdescription = ($this->css == 'ar_') ? $row['arbitemdescription'] : $row['itemdescription'];
                
                $responce->rows[$i]['id'] = $i;
                $responce->rows[$i]['cell'] = array($row['itemcode'],$itemdescription,$row['salesqty'],round($row['salescaseprice'],2),
                                                round($row['salesprice'],2), round($row['stdsalescaseprice'],2),round($row['stdsalesprice'],2)
                                                );
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
        
        if(isset($this->report_session->routecode_str) && $this->report_session->routecode_str != "")
        {
            $extra_where .= ' AND ih.routecode IN ('.$this->report_session->routecode_str.')';
        }
        
        if($this->report_session->post['txt_route_start_date'] != "" && $this->report_session->post['txt_route_end_date'] != "")
        {
            $extra_where  .= ' AND ih.transactiondate BETWEEN "'.date("Y-m-d",strtotime($this->report_session->post['txt_route_start_date'])).'" AND "'.date("Y-m-d",strtotime($this->report_session->post['txt_route_end_date'])).'"';
        }
        elseif($this->report_session->post['txt_route_start_date'] != "" && $this->report_session->post['txt_route_end_date'] == "")
        {
            $extra_where  .= ' AND ih.transactiondate >= "'.date("Y-m-d",strtotime($this->report_session->post['txt_route_start_date'])).'"';
        }
        elseif($this->report_session->post['txt_route_start_date'] == "" && $this->report_session->post['txt_route_end_date'] != "")
        {
            $extra_where  .= ' AND ih.transactiondate <= "'.date("Y-m-d",strtotime($this->report_session->post['txt_route_end_date'])).'"';
        }
        //if($this->report_session->post['txt_route_start_date'] != "" && $this->report_session->post['txt_route_end_date'] != "")
        //{
        //    $extra_where  .= ' and ih.transactiondate BETWEEN "'.date("Y-m-d",strtotime($this->report_session->post['txt_route_start_date'])).'" AND "'.date("Y-m-d",strtotime($this->report_session->post['txt_route_end_date'])).'"';
        //}
      
        $page = $_GET['page']; // get the requested page
        $limit = $_GET['rows']; // get how many rows we want to have into the grid
        $sidx = $_GET['sidx']; // get index row - i.e. user click to sort
        $sord = $_GET['sord']; // get the direction
        if(!$sidx) $sidx =1;
        
        if(empty($sidx)) {  $sidx  = "transactiondate";}
        if(empty($sord)) {  $sord  = "asc";}
    
        $param_array = array();
        $param_array[1] = $extra_where;
        $param_array[2] = "transactiondate,".$sidx;
        $param_array[3] = $sord;
        /*$param_array[4] = $limit;
        $param_array[5] = $page;*/
		$param_array[4] = 10000000;
        $param_array[5] = 1;
        $param_array[6] = 1;
        
        $result_arr = $this->SFA_Comman->executequery('CALL sp_report_dailyreport_pricingsummary(?,?,?,?,?,?)',$param_array,'');
        
        $report_title_height = 15;
        for($i=0;$i<count($this->report_session->searchParams);$i++)
        {
            if($this->report_session->searchParams[$i]["value"] != "")
            {
                if($this->session->lang == "ar_AR") {
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
        $data_arr["columns"]["maingrid"] = array($this->translate->_('Transaction Date'),$this->translate->_('Transaction Time'),$this->translate->_('Invoice Number'),$this->translate->_('Customer Code'),$this->translate->_('Customer Name'),$this->translate->_('Sales Amount'),$this->translate->_('Free Amount'),$this->translate->_('Net Amount'));
        $data_arr["columns"]["subgrid"] = array($this->translate->_('Item Code'),$this->translate->_('Item Description'),$this->translate->_('Sales Qty'),$this->translate->_('Sales Case Price'),$this->translate->_('Sales Pcs Price'),$this->translate->_('Std.Case Price'),$this->translate->_('Std.Pcs Price'));
        $data_arr["columns_config"]["main_column"] =   array(
                                                            array("width"=>15),
                                                            array("width"=>15),
                                                            array("width"=>35),
                                                            array("width"=>20),
                                                            array("width"=>35),
                                                            array("width"=>15),
                                                            array("width"=>20),
                                                            array("width"=>15)
                                                        );
        $data_arr["columns_config"]["subgrid_column"] = array(
                                                            array("width"=>15),
                                                            array("width"=>15),
                                                            array("width"=>35),
                                                            array("width"=>20),
                                                            array("width"=>35),
                                                            array("width"=>15),
                                                            array("width"=>20),
                                                            array("width"=>15)
                                                        );
        $temp = array();
        for($i = 0; $i < count($result_arr[0]); $i++)
        {
            if(!isset($temp[$result_arr[0][$i]['transactiondate']][$result_arr[0][$i]['transactiontime']][$result_arr[0][$i]['invoicenumber']]) || $temp[$result_arr[0][$i]['transactiondate']][$result_arr[0][$i]['transactiontime']][$result_arr[0][$i]['invoicenumber']] != "1")
            {
                $customername = ($this->css == 'ar_') ? $result_arr[0][$i]['arbcustomername'] : $result_arr[0][$i]['customername'];
                $column_model_arr["maingrid"][] = array($result_arr[0][$i]['transactiondate'],$result_arr[0][$i]['transactiontime'],$result_arr[0][$i]['invoicenumber'],$result_arr[0][$i]['code'],
                                        $customername, $result_arr[0][$i]['totalinvoiceamount'],$result_arr[0][$i]['totalfreesampleamount'],
                                        ($result_arr[0][$i]['totalinvoiceamount'] - $result_arr[0][$i]['totalfreesampleamount'])
                                        );
            }
                $itemdescription = ($this->css == 'ar_') ? $result_arr[0][$i]['arbitemdescription'] : $result_arr[0][$i]['itemdescription'];
                $column_model_arr["subgrid"][$result_arr[0][$i]['transactiondate']][$result_arr[0][$i]['transactiontime']][$result_arr[0][$i]['invoicenumber']][] =
                                                array($result_arr[0][$i]['itemcode'],$itemdescription,$result_arr[0][$i]['salesqty'],$result_arr[0][$i]['salescaseprice'],
                                                    $result_arr[0][$i]['salesprice'], $result_arr[0][$i]['stdsalescaseprice'],$result_arr[0][$i]['stdsalesprice']
                                                );
                $temp[$result_arr[0][$i]['transactiondate']][$result_arr[0][$i]['transactiontime']][$result_arr[0][$i]['invoicenumber']] = '1';
            
        }
        
        $data_arr["columns_model"]          = $column_model_arr;
        $data_arr["config"]["report_title"] = $this->translate->_("Pricing Summary").$report_title;
        $data_arr["config"]["report_title_height"] = $report_title_height;
        $data_arr["config"]["file_name"]    = "PricingSummary";
        $data_arr["config"]["subgrid_level"]  = 3;
        $data_arr["config"]["total_columns"]= count($data_arr["columns"]["maingrid"]);
        $data_arr["config"]["group_total"]  = "0";
        $data_arr["config"]["main_total"]   = "0";
        
        
        $SFA_Exportxls = new SFA_Exportxlssubgrid($data_arr);
        $objPHPExcel = $SFA_Exportxls->exportxls();
        $objWriter = PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel5');
        $objWriter->save('php://output');
        exit;
    }
}
