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
class Reports_DiscountsummaryController extends Reports_Library_Controller_Action_Abstract
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
        
        $this->report_session = new Zend_Session_Namespace('Re_discountsummary');
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
    public function discountsummaryAction()
    {
        $this->view->params 	= $params = $this->getRequest()->getParams();
        $this->view->formdata  = $formdata = $this->_request->getPost();
        
        //$result_arr = $this->SFA_Comman->executequery('CALL sp_report_dailyreport_discountsummary_detail()','','');
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
        
        $this->view->ReportTitle = $this->translate->_("Discount Summary");
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
                                            array("title"=> "Month",
                                                  "value" => $formdata['month_selected']),
                                            array("title"=> "Year",
                                                  "value" => $formdata['year_selected'])
                                            );
        $this->report_session->searchParams = $this->view->searchParams;
        
        $this->view->xlsexport_link = $this->view->baseUrl()."/reports/discountsummary/export";
        $this->view->cvsexport_link = $this->view->baseUrl()."/reports/discountsummary/exportcsv";
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
    public function discountsummarydataAction()
    {
        $this->view->params = $params = $this->getRequest()->getParams();
        $this->view->formdata = $formdata = $this->_request->getPost();
        $this->view->css 		= $this->translate->_('CSS');
        $extra_where = "";
        
        if(isset($this->report_session->routecode_str) && $this->report_session->routecode_str != "")
        {
            $extra_where .= ' AND ih.routecode IN ('.$this->report_session->routecode_str.')';
        }
        if($this->report_session->post['month'] != "" && $this->report_session->post['month'] > 0)
        {
            $extra_where  .= " and MONTH(ih.actualtransactiondate) = ".$this->report_session->post['month'];
        }
        if($this->report_session->post['year'] != "" && $this->report_session->post['year'] > 0)
        {
            $extra_where  .= " and YEAR(ih.actualtransactiondate) = ".$this->report_session->post['year'];
        }
      
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
        
        $result_arr = $this->SFA_Comman->executequery('CALL sp_report_dailyreport_discountsummary(?,?,?,?,?)',$param_array,'');
        
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
        $totalsalesamount = $totalreturnamount = $totaldamagedamount = $totalexpiryamount = $totalfreesampleamount = $totalinvoiceamount = $totaldiscountamount = $totalnetamount = 0;
        if(!empty($result_arr[1])){
            foreach($result_arr[1] as $row) {
                $totalsalesamount += $row['salesamount'];
                $totalreturnamount += $row['goodreturnamount'];
                $totaldamagedamount += $row['totaldamagedamount'];
              //  $totalexpiryamount += $row['totalexpiryamount'];
                $totalfreesampleamount += $row['freeamount'];
                $totalinvoiceamount += $row['invoiceamount'];
                $totaldiscountamount += $row['discountamount'];
                $totalnetamount += ($row['invoiceamount']-$row['discountamount']);
                
                $customername = ($this->css == 'ar_') ? $row['arbcustomername'] : $row['customername'];
                
                $responce->rows[$i]['id'] = $i;
                $responce->rows[$i]['cell'] = array($row['transactiondate'],$row['transactiontime'],$row['invoicenumber'],$row['customercode'],
                                                $customername, $row['salesamount'],$row['goodreturnamount'], $row['totaldamagedamount'],/*$row['totalexpiryamount'],*/
                                                $row['freeamount'],$row['invoiceamount'],$row['discountamount'],($row['invoiceamount']-$row['discountamount'])
                                                );
                $i++;
            }
        }
        else
        {
          //  $responce->rows[$i]['id']=1;
          //  $responce->rows[$i]['cell']=array("","","","No Record Founds","", "");
        }
        $responce->userdata['customername'] = $this->translate->_("Total");
        $responce->userdata['salesamount'] = $totalsalesamount;
        $responce->userdata['goodreturnamount'] = $totalreturnamount;
        $responce->userdata['totaldamagedamount'] = $totaldamagedamount;
      //  $responce->userdata['totalexpiryamount'] = $totalexpiryamount;
        $responce->userdata['freeamount'] = $totalfreesampleamount;
        $responce->userdata['invoiceamount'] = $totalinvoiceamount;
        $responce->userdata['discountamount'] = $totaldiscountamount;
        $responce->userdata['netamount'] = $totalnetamount;
        
        echo json_encode($responce);
        exit;
    }
    
    
    /**
      * @name       exportAction
      * @since      15-11-2012
      * @version    Release: 9
      * @author     PT <pankil@elantechnologies.com>
      * @copyright  Elan Technologies
      * @param
      *
      * This action is for export xls
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
        if($this->report_session->post['month'] != "" && $this->report_session->post['month'] > 0)
        {
            $extra_where  .= " and MONTH(ih.actualtransactiondate) = ".$this->report_session->post['month'];
        }
        if($this->report_session->post['year'] != "" && $this->report_session->post['year'] > 0)
        {
            $extra_where  .= " and YEAR(ih.actualtransactiondate) = ".$this->report_session->post['year'];
        }
      
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
        $param_array[4] = $limit;
        $param_array[5] = $page;
        
        $result_arr = $this->SFA_Comman->executequery('CALL sp_report_dailyreport_discountsummary(?,?,?,?,?)',$param_array,'');
        
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
        $data_arr["columns"] = array($this->translate->_('Transaction Date'),$this->translate->_('Transaction Time'),$this->translate->_('Invoice Number'),$this->translate->_('Customer Code'),$this->translate->_('Customer Name'),$this->translate->_('Sales Amount'),$this->translate->_('Good Ret. Amount'),$this->translate->_('Bad Ret. Amount'),/*$this->translate->_('Expiry Ret.Amount'),*/$this->translate->_('Free Amount'),$this->translate->_('Invoice Amount'),$this->translate->_('Discount Amount'),$this->translate->_('Net Amount'));
        $data_arr["columns_config"] =   array(
                                            array("width"=>13),
                                            array("width"=>13),
                                            array("width"=>15),
                                            array("width"=>15),
                                            array("width"=>35,"toaltext"=>$this->translate->_('Total'),"group_total_text"=>$this->translate->_('Group Total')),
                                            array("width"=>15,"total"=>"1","group_total"=>"1"),
                                            array("width"=>15,"total"=>"1","group_total"=>"1"),
                                            array("width"=>15,"total"=>"1","group_total"=>"1"),
                                            array("width"=>15,"total"=>"1","group_total"=>"1"),
                                            array("width"=>15,"total"=>"1","group_total"=>"1"),
                                            array("width"=>15,"total"=>"1","group_total"=>"1"),
                                            array("width"=>15,"total"=>"1","group_total"=>"1")
                                           /* array("width"=>15,"total"=>"1","group_total"=>"1")*/
                                        );
        for($i = 0; $i < count($result_arr[0]); $i++)
        {
            $customername = ($this->css == 'ar_') ? $result_arr[0][$i]['arbcustomername'] : $result_arr[0][$i]['customername'];
            $column_model_arr[$result_arr[0][$i]['transactiondate']][] = array($result_arr[0][$i]['transactiontime'],$result_arr[0][$i]['invoicenumber'],$result_arr[0][$i]['customercode'],
                                                                $customername, $result_arr[0][$i]['salesamount'],$result_arr[0][$i]['goodreturnamount'],
                                                                $result_arr[0][$i]['totaldamagedamount'],/*$result_arr[0][$i]['totalexpiryamount'],*/
                                                                $result_arr[0][$i]['freeamount'],$result_arr[0][$i]['invoiceamount'],$result_arr[0][$i]['discountamount'],($result_arr[0][$i]['invoiceamount']-$result_arr[0][$i]['discountamount'])
                                                                );
        }
        //pr($column_model_arr,1);
        $data_arr["columns_model"]          = $column_model_arr;
        $data_arr["config"]["report_title"] = $this->translate->_("Discount Summary").$report_title;
        $data_arr["config"]["report_title_height"] = $report_title_height;
        $data_arr["config"]["file_name"]    = "DiscountSummary";
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
