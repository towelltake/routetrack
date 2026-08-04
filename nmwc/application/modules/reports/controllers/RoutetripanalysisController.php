<?php
/**
* @name       Reports_RoutetripanalysisController
* @since      20-02-2012
* @version    Release: 7
* @author     GP <gayatri@elantechnologies.com>
* @copyright  Elan Technologies
* @param
*
* This controller is manage report module.
*/
class Reports_RoutetripanalysisController extends Reports_Library_Controller_Action_Abstract
{
     /**
    * @name       init
    * @since      15-02-2012
    * @version    Release: 1
    * @author     M@M <miral@elantechnologies.com>
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
        
        $this->sec_lang 	                        	= $this->view->sec_lang;
        $this->decimalplaces  = $this->view->decimalplaces	= $this->SFA_Comman->getdecimalplaces();
        $this->view->sec_lang	                        	= $this->SFA_Comman->getsecondlanguage();
        
        $this->report_session		= new Zend_Session_Namespace('Re_salessummary');
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
	
    /**
    * @name       ordersummaryAction
    * @since      15-02-2012
    * @version    Release: 7
    * @author     GP <gayatri@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This action we call ordersummery data
    *
    */
    public function routetripanalysisAction()
    {
        $this->view->params 	= $params = $this->getRequest()->getParams();
        $this->view->formdata  = $formdata = $this->_request->getPost();
        $this->view->itemgrid    = $this->view->BaseUrl("/".$params['module']."/ajaxdata/useraccessgrid");
        $this->report_session->post = array();
   }

    /**
    * @name       indexAction
    * @since      15-02-2012
    * @version    Release: 7
    * @author     GP <gayatri@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This action we set report Parameter
    *
    */
    public function indexAction()
    {
        $this->_helper->layout->setLayout('jqreport');
        $this->view->params 	= $params = $this->getRequest()->getParams();
        $this->view->formdata  = $formdata = $this->_request->getPost();
        
        $this->report_session->post = $formdata;
        
        $this->view->ReportTitle = $this->translate->_("Route Trip Analysis");
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
                                            array("title"=> "Route End Date",
                                                  "value" => ($formdata['txt_route_start_date'] != "" ) ? date("d M Y",strtotime($formdata['txt_route_start_date'])) : "")
                                            );
        $this->report_session->searchParams = $this->view->searchParams;
        
        $this->view->xlsexport_link = $this->view->baseUrl()."/reports/routetripanalysis/export";
        $this->view->cvsexport_link = $this->view->baseUrl()."/reports/routetripanalysis/exportcsv";

    }
    /**
      * @name       ordersummarydataAction
      * @since      15-02-2012
      * @version    Release: 1
      * @author     GP <gayatri@elantechnologies.com>
      * @copyright  Elan Technologies
      * @param
      *
      * This action Actually bind datasource
      *
      */
    public function routetripanalysisdataAction()
    {
        $this->view->params = $params = $this->getRequest()->getParams();
        $this->view->formdata = $formdata = $this->_request->getPost();
        $this->view->css 		= $this->translate->_('CSS');
        
        $page = $_GET['page']; // get the requested page
        $limit = $_GET['rows']; // get how many rows we want to have into the grid
        $sidx = $_GET['sidx']; // get index row - i.e. user click to sort
        $sord = $_GET['sord']; // get the direction
        if(!$sidx) $sidx =1;
        
        if(empty($sidx)) {  $sidx  = "routecode";}
        if(empty($sord)) {  $sord  = "asc";}
        
        $date = ($this->report_session->post['txt_route_start_date'] != "") ?date("Y-m-d",strtotime($this->report_session->post['txt_route_start_date'])):"";
        
        $param_array = array();
        $param_array[1] = $date;
        $param_array[2] = $this->report_session->routecode_str;
        $param_array[3] = $sidx;
        $param_array[4] = $sord;
        
        $result_arr = $this->SFA_Comman->executequery('CALL sp_report_dailyreport_dailyroutetripanalysis(?,?,?,?)',$param_array,'');
        
        $count  = count($result_arr[0]);
        if( $count >0 ) {
        $total_pages = ceil($count/$limit);
        } else {
        $total_pages = 0;
        }
        if ($page > $total_pages) $page=$total_pages;
        $start = $limit*$page - $limit; // do not put $limit*($page - 1)
        
        $responce->page = $page;
        $responce->total = $total_pages;
        $responce->records = $count;
        $i=0;
        $total_invoiceamt = 0;
        if(!empty($result_arr[0])){
            foreach($result_arr[0] as $row) {
                $responce->rows[$i]['id']=$i;
                $total_invoiceamt += $row['totalinvoiceamount'];
                $cpanel				= $this->SFA_Comman->getaltcodestatus();
                $altcode_status		= $cpanel["Use Alternate Code"]['status'];
                if($altcode_status)
                    $customercode = $row['reportcustcode'];
                else
                    $customercode = $row['customercode'];
                
                $routename = ($this->session->lang == "ar_AR") ? $row['arbroutename'] : $row['routename'];
                $salesmanname = ($this->session->lang == "ar_AR") ? $row['arbsalesmanname1'] : $row['salesmanname1'];
                $customername = ($this->session->lang == "ar_AR") ? $row['arbcustomername'] : $row['customername'];
            
                $responce->rows[$i]['cell']=array($row['routecode']." - ".$routename,$row['dayname'],$row['visitsequence'],$customercode,
                                                  $customername,$row['mop'],$row['visitsheduled'], $row['visitstarttime'],
                                                  $row['visitendtime'],$row['visitstatus'],$row['totalinvoiceamount'],$row['reason'],
                                                  date("d-M-Y",strtotime($row['lastsales'])), $row['lastorder']);
                $i++;
            }
        }
        else
        {
            //  $responce->rows[$i]['id']=1;
            //  $responce->rows[$i]['cell']=array("","","","No Record Founds","", "");
        }
        $responce->userdata['totalinvoiceamount'] = $total_invoiceamt;
        $responce->userdata['visitstatus'] = $this->translate->_("Total");
        echo json_encode($responce);
        exit;
    }
    
    
    /**
      * @name       exportAction
      * @since      15-02-2012
      * @version    Release: 1
      * @author     GP <gayatri@elantechnologies.com>
      * @copyright  Elan Technologies
      * @param
      *
      * This action Actually bind datasource
      *
      */
    public function exportAction()
    {
       $this->view->params = $params = $this->getRequest()->getParams();
        $this->view->formdata = $formdata = $this->_request->getPost();
        $this->view->css 		= $this->translate->_('CSS');
        
        $page = $_GET['page']; // get the requested page
        $limit = $_GET['rows']; // get how many rows we want to have into the grid
        $sidx = $_GET['sidx']; // get index row - i.e. user click to sort
        $sord = $_GET['sord']; // get the direction
        if(!$sidx) $sidx =1;
        
        if(empty($sidx)) {  $sidx  = "routecode";}
        if(empty($sord)) {  $sord  = "asc";}
        
        
        $param_array = array();
        $param_array[1] = date("Y-m-d",strtotime($this->report_session->post['txt_route_start_date']));
        $param_array[2] = $this->report_session->routecode_str;
        $param_array[3] = "routecode,dayname,".$sidx;
        $param_array[4] = $sord;
        
        $result_arr = $this->SFA_Comman->executequery('CALL sp_report_dailyreport_dailyroutetripanalysis(?,?,?,?)',$param_array,'');
        
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
        $data_arr["columns"] = array('Route','Week Day','Visit Sequ.','Customer Code','Customer','Payment Mode','Customer Visit Schedule','Visit Start Time','Visit End Time','Visit Status','Transaction Amount','No Transaction Reason','Last Invoiced Date','Last Order Taken On');
        $data_arr["columns_config"] =   array(
                                            array("width"=>15),
                                            array("width"=>15),
                                            array("width"=>13),
                                            array("width"=>13),
                                            array("width"=>35),
                                            array("width"=>13),
                                            array("width"=>13),
                                            array("width"=>13),
                                            array("width"=>13),
                                            array("width"=>15,"toaltext"=>$this->translate->_('Total'),"group_total_text"=>$this->translate->_('Group Total')),
                                            array("width"=>15,"total"=>"1","group_total"=>"1"),
                                            array("width"=>30),
                                            array("width"=>13),
                                            array("width"=>15)
                                        );
        for($i = 0; $i < count($result_arr[0]); $i++)
        {
            $cpanel				= $this->SFA_Comman->getaltcodestatus();
            $altcode_status		= $cpanel["Use Alternate Code"]['status'];
            if($altcode_status)
                $customercode = $result_arr[0][$i]['reportcustcode'];
            else
                $customercode = $result_arr[0][$i]['customercode'];
            
            $routename = ($this->session->lang == "ar_AR") ? $result_arr[0][$i]['arbroutename'] : $result_arr[0][$i]['routename'];
            $salesmanname = ($this->session->lang == "ar_AR") ? $result_arr[0][$i]['arbsalesmanname1'] : $result_arr[0][$i]['salesmanname1'];
            $customername = ($this->session->lang == "ar_AR") ? $result_arr[0][$i]['arbcustomername'] : $result_arr[0][$i]['customername'];
            
            $column_model_arr[$result_arr[0][$i]['routecode']." - ".$routename][$result_arr[0][$i]['dayname']][] =
                array($result_arr[0][$i]['visitsequence'],$customercode,
                    $customername,$result_arr[0][$i]['mop'],$result_arr[0][$i]['visitsheduled'], $result_arr[0][$i]['visitstarttime'],
                    $result_arr[0][$i]['visitendtime'],$result_arr[0][$i]['visitstatus'],$result_arr[0][$i]['totalinvoiceamount'],$result_arr[0][$i]['reason'],
                    date("d-M-Y",strtotime($result_arr[0][$i]['lastsales'])), $result_arr[0][$i]['lastorder']);
        }
        
        $data_arr["columns_model"]          = $column_model_arr;
        $data_arr["config"]["report_title"] = $this->translate->_("Route Trip Analysis").$report_title;
        $data_arr["config"]["report_title_height"] = $report_title_height;
        $data_arr["config"]["file_name"]    = "RouteTripAnalysis";
        $data_arr["config"]["group_level"]  = 2;
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
