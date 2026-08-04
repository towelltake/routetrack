<?php
/**
* @name       RoutesummaryController
* @since      05-10-2012
* @version    Release: 1
* @author     PT <pankil@elantechnologies.com>
* @copyright  Elan Technologies
* @param
*
* This controller is manage report module.
*/
class Reports_RoutesummaryController extends Reports_Library_Controller_Action_Abstract
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
            SFA_Message::setMsg($this->translate->_('Do Login'));
            //$this->_helper->redirector("index", "index", "home");
			$url = $this->view->baseUrl();
			echo '<script type="text/javascript">window.location="'.$url.'";</script>';
			exit;
        }
        
        $this->sec_lang 	  = $this->view->sec_lang;
        $this->decimalplaces  = $this->view->decimalplaces	= $this->SFA_Comman->getdecimalplaces();
        $this->view->sec_lang = $this->SFA_Comman->getsecondlanguage();
        
        $this->report_session = new Zend_Session_Namespace('Re_routesummary');
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
    * @name       routesummaryAction
    * @since      05-10-2012
    * @version    Release: 1
    * @author     PT <pankil@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This action is for display discount summary
    *
    */
    public function routesummaryAction()
    {
        $this->view->params 	= $params = $this->getRequest()->getParams();
        $this->view->formdata  = $formdata = $this->_request->getPost();
        
        //$result_arr = $this->SFA_Comman->executequery('CALL sp_report_dailyreport_routesummary_detail()','','');
        
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
        
        $this->view->ReportTitle = $this->translate->_("Route Summary");
        $this->view->pageHeaderTitle  = $this->translate->_('Date');
        $this->view->pageHeadervalue  =  date("m/d/Y h:i:s");
        //$this->view->searchParams  =  array(
        //                                    array("title"=> "Route End Date",
        //                                          "value" => ($formdata['txt_route_end_date'] != "" ) ? date("d M Y",strtotime($formdata['txt_route_end_date'])) : "")
        //                                    );
        
        $this->view->xlsexport_link = $this->view->baseUrl()."/reports/routesummary/export";
        $this->view->cvsexport_link = $this->view->baseUrl()."/reports/routesummary/exportcsv";
        
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
                                                  "value" => ($formdata['txt_route_end_date'] != "" ) ? date("d M Y",strtotime($formdata['txt_route_end_date'])) : "")
                                            );
        $this->report_session->searchParams = $this->view->searchParams;
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
    public function routesummarydataAction()
    {
        $this->view->params = $params = $this->getRequest()->getParams();
        $this->view->formdata = $formdata = $this->_request->getPost();
        $this->view->css 		= $this->translate->_('CSS');
        $extra_where = "";
        
        //if($this->report_session->post['txt_route_end_date'] != "" )
        //{
        //    $extra_where  .= " and sed.routeenddate = ".$this->report_session->post['txt_route_end_date'];
        //}
        if($this->report_session->post['txt_route_end_date'] != "" )
        {
            $extra_where  .= ' AND  DATE_FORMAT(sed.routeenddate,"%Y-%m-%d")  = "'.date('Y-m-d',strtotime($this->report_session->post['txt_route_end_date'])).'"';
        }
        if(isset($this->report_session->routecode_str) && $this->report_session->routecode_str != "")
        {
            $extra_where .= ' AND sed.routecode IN ('.$this->report_session->routecode_str.')';
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
        
        $result_arr = $this->SFA_Comman->executequery('CALL sp_report_dailyreport_routesummary(?,?,?,?,?)',$param_array,'');
        
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
        $totalinvdocuments = $totalinvretdocuments = $totalcashsales = $totalgcsales = $totaltcsales = $totalinvoiceamount = $totalardocuments =
        $totalacctsreceivable = $totalcash = $totalchecks = $totalorderamount = $totalexpenses = $inventoryvariance = $cashvariance = 0;
        if(!empty($result_arr[1])){
            foreach($result_arr[1] as $row) {
                $totalinvdocuments += $row['totalinvdocuments'];
                $totalinvretdocuments += $row['totalinvretdocuments'];
                $totalcashsales += $row['totalcashsales'];
                $totalgcsales += $row['totalgcsales'];
                $totaltcsales += $row['totaltcsales'];
                $totalinvoiceamount += $row['totalinvoiceamount'];
                $totalardocuments += $row['totalardocuments'];
                $totalacctsreceivable += $row['totalacctsreceivable'];
                $totalcash += $row['totalcash'];
                $totalchecks += $row['totalchecks'];
                $totalorderamount += $row['totalorderamount'];
                $totalexpenses += $row['totalexpenses'];
                $inventoryvariance += $row['inventoryvariance'];
                $cashvariance += $row['cashvariance'];
                
                $routename = ($this->session->lang == "ar_AR") ? $row['arbroutename'] : $row['routename'];
                $salesmanname = ($this->session->lang == "ar_AR") ? $row['arbsalesmanname1'] : $row['salesmanname1'];
                
                $responce->rows[$i]['id'] = $i;
                $responce->rows[$i]['cell'] = array($row['routecode']."-".$routename." (".$salesmanname.")",$row['routestartdate'],$row['routestarttime'],
                                                    $row['routeenddate'],$row['routeendtime'],$row['totalinvdocuments'], $row['totalinvretdocuments'],
                                                    $row['totalcashsales'],$row['totalgcsales'],$row['totaltcsales'],$row['totalinvoiceamount'],
                                                    $row['totalardocuments'],$row['totalacctsreceivable'],$row['totalcash'],$row['totalchecks'],
                                                    $row['totalorderamount'],$row['totalexpenses'],$row['inventoryvariance'],$row['cashvariance']
                                                );
                $i++;
            }
        }
        else
        {
          //  $responce->rows[$i]['id']=1;
          //  $responce->rows[$i]['cell']=array("","","","No Record Founds","", "");
        }
        $responce->userdata['routes'] = $this->translate->_("Total");
        $responce->userdata['totalinvdocuments'] = $totalinvdocuments;
        $responce->userdata['totalinvretdocuments'] = $totalinvretdocuments;
        $responce->userdata['totalcashsales'] = $totalcashsales;
        $responce->userdata['totalgcsales'] = $totalgcsales;
        $responce->userdata['totaltcsales'] = $totaltcsales;
        $responce->userdata['totalinvoiceamount'] = $totalinvoiceamount;
        $responce->userdata['totalardocuments'] = $totalardocuments;
        $responce->userdata['totalacctsreceivable'] = $totalacctsreceivable;
        $responce->userdata['totalcash'] = $totalcash;
        $responce->userdata['totalchecks'] = $totalchecks;
        $responce->userdata['totalorderamount'] = $totalorderamount;
        $responce->userdata['totalexpenses'] = $totalexpenses;
        $responce->userdata['inventoryvariance'] = $inventoryvariance;
        $responce->userdata['cashvariance'] = $cashvariance;
        
        echo json_encode($responce);
        exit;
    }
    
    
    /**
      * @name       exportAction
      * @since      19-10-2012
      * @version    Release: 1
      * @author     PT <pankil@elantechnologies.com>
      * @copyright  Elan Technologies
      * @param
      *
      * This action fetch customer pending request data
      *
      */
    public function exportAction()
    {
        $this->view->params = $params = $this->getRequest()->getParams();
        $this->view->formdata = $formdata = $this->_request->getPost();
        $this->view->css 		= $this->translate->_('CSS');
        $extra_where = "";
        
        //if($this->report_session->post['txt_route_end_date'] != "" )
        //{
        //    $extra_where  .= " and sed.routeenddate = ".$this->report_session->post['txt_route_end_date'];
        //}
        if($this->report_session->post['txt_route_end_date'] != "" )
        {
            $extra_where  .= ' AND  DATE_FORMAT(sed.routeenddate,"%Y-%m-%d")  = "'.date('Y-m-d',strtotime($this->report_session->post['txt_route_end_date'])).'"';
        }
        if(isset($this->report_session->routecode_str) && $this->report_session->routecode_str != "")
        {
            $extra_where .= ' AND sed.routecode IN ('.$this->report_session->routecode_str.')';
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
        /*$param_array[4] = $limit;
        $param_array[5] = $page;*/
		$param_array[4] = 10000000;
        $param_array[5] = 1;
        
        $result_arr = $this->SFA_Comman->executequery('CALL sp_report_dailyreport_routesummary(?,?,?,?,?)',$param_array,'');//print_r($result_arr);exit;
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
        $data_arr["columns"] = array($this->translate->_('Route (Salesman)'),$this->translate->_('Route Start Date'),$this->translate->_('Route Start Time'),$this->translate->_('Route End Date'),$this->translate->_('Route End Time'),$this->translate->_('Total Sales Documents'),$this->translate->_('Total Return Documents'),$this->translate->_('Total Cash Sales'),$this->translate->_('Total GC Sales'),$this->translate->_('Total TC Sales'),$this->translate->_('Total Invoiced Amount'),$this->translate->_('Total Receipt Documents'),$this->translate->_('Total Receipt Amount'),$this->translate->_('Total Cash'),$this->translate->_('Total Cheques'),$this->translate->_('Total Order Amount'),$this->translate->_('Total Expenses'),$this->translate->_('Inventory Variance'),$this->translate->_('Cash Variance'));
        
        $data_arr["columns_config"] = array(array("width"=>50,"toaltext"=>$this->translate->_('Total')),
                                            array("width"=>12),
                                            array("width"=>10),
                                            array("width"=>12),
                                            array("width"=>10),
                                            array("width"=>12,"total"=>"1"),
                                            array("width"=>12,"total"=>"1"),
                                            array("width"=>12,"total"=>"1"),
                                            array("width"=>12,"total"=>"1"),
                                            array("width"=>12,"total"=>"1"),
                                            array("width"=>12,"total"=>"1"),
                                            array("width"=>12,"total"=>"1"),
                                            array("width"=>12,"total"=>"1"),
                                            array("width"=>12,"total"=>"1"),
                                            array("width"=>12,"total"=>"1"),
                                            array("width"=>10,"total"=>"1"),
                                            array("width"=>12,"total"=>"1"),
                                            array("width"=>12,"total"=>"1"),
                                            array("width"=>12,"total"=>"1")
                                        );
       // echo '<pre>',print_r($result_arr,1),'</pre>';exit;
        for($i = 0; $i < count($result_arr[1]); $i++)
        {  
            $routename = ($this->session->lang == "ar_AR") ? $result_arr[1][$i]['arbroutename'] : $result_arr[1][$i]['routename'];
            $salesmanname = ($this->session->lang == "ar_AR") ? $result_arr[1][$i]['arbsalesmanname1'] : $result_arr[1][$i]['salesmanname1'];
            
            $column_model_arr[] = array($result_arr[1][$i]['routecode']."-".$routename." (".$salesmanname.")",$result_arr[1][$i]['routestartdate'],$result_arr[1][$i]['routestarttime'],$result_arr[1][$i]['routeenddate'],$result_arr[1][$i]['routeendtime'],
                                        $result_arr[1][$i]['totalinvdocuments'], $result_arr[1][$i]['totalinvretdocuments'],$result_arr[1][$i]['totalcashsales'],
                                        $result_arr[1][$i]['totalgcsales'],$result_arr[1][$i]['totaltcsales'],$result_arr[1][$i]['totalinvoiceamount'],$result_arr[1][$i]['totalardocuments'],
                                        $result_arr[1][$i]['totalacctsreceivable'],$result_arr[1][$i]['totalcash'],$result_arr[1][$i]['totalchecks'],$result_arr[1][$i]['totalorderamount'],
                                        $result_arr[1][$i]['totalexpenses'],$result_arr[1][$i]['inventoryvariance'],$result_arr[1][$i]['cashvariance']
                                        );
										//echo '<pre>',print_r($result_arr,1),'</pre>';exit;
										 
        }
        
		/* for($i = 0; $i < count($result_arr[0]); $i++)
        {  
            $routename = ($this->session->lang == "ar_AR") ? $result_arr[0][$i]['arbroutename'] : $result_arr[0][$i]['routename'];
            $salesmanname = ($this->session->lang == "ar_AR") ? $result_arr[0][$i]['arbsalesmanname1'] : $result_arr[0][$i]['salesmanname1'];
            
            $column_model_arr[] = array($result_arr[0][$i]['routecode']."-".$routename." (".$salesmanname.")",$result_arr[0][$i]['routestartdate'],$result_arr[0][$i]['routestarttime'],$result_arr[0][$i]['routeenddate'],$result_arr[0][$i]['routeendtime'],
                                        $result_arr[0][$i]['totalinvdocuments'], $result_arr[0][$i]['totalinvretdocuments'],$result_arr[0][$i]['totalcashsales'],
                                        $result_arr[0][$i]['totalgcsales'],$result_arr[0][$i]['totaltcsales'],$result_arr[0][$i]['totalinvoiceamount'],$result_arr[0][$i]['totalardocuments'],
                                        $result_arr[0][$i]['totalacctsreceivable'],$result_arr[0][$i]['totalcash'],$result_arr[0][$i]['totalchecks'],$result_arr[0][$i]['totalorderamount'],
                                        $result_arr[0][$i]['totalexpenses'],$result_arr[0][$i]['inventoryvariance'],$result_arr[0][$i]['cashvariance']
                                        );
										//echo '<pre>',print_r($result_arr,1),'</pre>';exit;
										 
        }*/
		
		
		
        $data_arr["columns_model"]          = $column_model_arr;
        $data_arr["config"]["report_title"] = $this->translate->_("Route Summary").$report_title;
        $data_arr["config"]["report_title_height"] = $report_title_height;
        $data_arr["config"]["file_name"]    = "RouteSummary";
        $data_arr["config"]["group_level"]  = 0;
        $data_arr["config"]["total_columns"]= count($data_arr["columns"]);
        $data_arr["config"]["group_total"]  = "0";
        $data_arr["config"]["main_total"]   = "1";
        $data_arr["config"]["row_height"]   = 20;
        
        
        
        $SFA_Exportxls = new SFA_Exportxls($data_arr);
        $objPHPExcel = $SFA_Exportxls->exportxls();
        $objWriter = PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel5');
        $objWriter->save('php://output');
        exit;
    }
}
