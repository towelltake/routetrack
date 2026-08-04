<?php
/**
* @name       TransactiondepositsummaryController
* @since      20-02-2012
* @version    Release: 1
* @author     PM <pankit@elantechnologies.com>
* @copyright  Elan Technologies
* @param
*
* This controller is manage report module.
*/
class Reports_TransactiondepositsummaryController extends Reports_Library_Controller_Action_Abstract
{
     /**
    * @name       init
    * @since      01-10-2012
    * @version    Release: 6
    * @author     Nidhi
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
		$this->css 			= $this->translate->_('CSS');
		$this->view->css	= $this->css;
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
        
        $this->report_session = new Zend_Session_Namespace('Re_depositsummary');
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
    public function depositsummaryAction()
    {
        $this->view->params 	= $params = $this->getRequest()->getParams();
        $this->view->formdata  = $formdata = $this->_request->getPost();
        
        //$result_arr = $this->SFA_Comman->executequery('CALL sp_report_tablet_freesummary_detail()','','');
        //$this->view->route_list = $result_arr[0];
        $this->view->itemgrid    = $this->view->BaseUrl("/".$params['module']."/ajaxdata/useraccessgrid");
        
        $this->report_session->post = array();
   }

    /**
    * @name       totalsalesbyhierarchyAction
    * @since      15-02-2012
    * @version    Release: 1
    * @author     PM <pankit@elantechnologies.com>
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
        
        $this->view->ReportTitle = $this->translate->_("Deposit Summary");
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
                                            array("title"=> "Start Date",
                                                 "value" => ($formdata['txt_route_start_date'] != "" ) ? date("d M Y",strtotime($formdata['txt_route_start_date'])) : ""),
                                           array("title"=> "End Date",
                                                 "value" => ($formdata['txt_route_end_date'] != "" ) ? date("d M Y",strtotime($formdata['txt_route_end_date'])) : "")
                                            );
        $this->report_session->searchParams = $this->view->searchParams;
        
        $this->view->xlsexport_link = $this->view->baseUrl()."/reports/transactiondepositsummary/export";
        $this->view->cvsexport_link = $this->view->baseUrl()."/reports/transactiondepositsummary/exportcsv";
    }
    
    /**
      * @name       custpendingbalAction
      * @since      15-02-2012
      * @version    Release: 1
      * @author     GP <gayatri@elantechnologies.com>
      * @copyright  Elan Technologies
      * @param
      *
      * This action fetch customer pending request data
      *
      */
    public function depositsumAction()
    {
        $this->view->params = $params = $this->getRequest()->getParams();
        $this->view->formdata = $formdata = $this->_request->getPost();
        $this->view->css 		= $this->translate->_('CSS');
        $extra_where = "";
        $extra_where1 = "";
        
        
        if(isset($this->report_session->routecode_str) && $this->report_session->routecode_str != "")
        {
            $extra_where  .= ' and ih.routecode IN ('.$this->report_session->routecode_str.')';
            $extra_where1  .= ' and arh.routecode IN ('.$this->report_session->routecode_str.')';
        }
        if($this->report_session->post['txt_route_start_date'] != "" && $this->report_session->post['txt_route_end_date'] != "")
        {
            $extra_where  .= ' AND ih.transactiondate BETWEEN "'.date("Y-m-d",strtotime($this->report_session->post['txt_route_start_date'])).'" AND "'.date("Y-m-d",strtotime($this->report_session->post['txt_route_end_date'])).'"';
            $extra_where1 .= ' AND arh.transactiondate BETWEEN "'.date("Y-m-d",strtotime($this->report_session->post['txt_route_start_date'])).'" AND "'.date("Y-m-d",strtotime($this->report_session->post['txt_route_end_date'])).'"';
        }
        elseif($this->report_session->post['txt_route_start_date'] != "" && $this->report_session->post['txt_route_end_date'] == "")
        {
            $extra_where  .= ' AND ih.transactiondate >= "'.date("Y-m-d",strtotime($this->report_session->post['txt_route_start_date'])).'"';
            $extra_where1 .= ' AND arh.transactiondate >= "'.date("Y-m-d",strtotime($this->report_session->post['txt_route_start_date'])).'"';
        }
        elseif($this->report_session->post['txt_route_start_date'] == "" && $this->report_session->post['txt_route_end_date'] != "")
        {
            $extra_where  .= ' AND ih.transactiondate <= "'.date("Y-m-d",strtotime($this->report_session->post['txt_route_end_date'])).'"';
            $extra_where1 .= ' AND arh.transactiondate <= "'.date("Y-m-d",strtotime($this->report_session->post['txt_route_end_date'])).'"';
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
       // $param_array[6] = $extra_where1;
       // print_r($param_array);exit;
        $result_arr = $this->SFA_Comman->executequery('CALL sp_report_transaction_deposit_summary(?,?,?,?,?,?)',$param_array,'');
        
        
        $count  = $result_arr[0][0]['in_counter'];
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
		
        if(!empty($result_arr[2])){
            foreach($result_arr[2] as $row) {
                $responce->rows[$i]['id'] = $i;
				
                $total_immediatecash += $row['immediatecash'];
                $total_immediatecheck += $row['immediatecheck'];
                $total_cnt += $row['total'];
                
                $routename 		= ($this->css == 'ar_') ? $row['arbroutename'] 	    : $row['routename'];
                $salesmanname	= ($this->css == 'ar_') ? $row['arbsalesmanname1'] 	: $row['salesmanname1'];
                
                $responce->rows[$i]['cell'] = array($row['routecode']." - ".$routename,$row['transactiondate'],$row['CustomerCode'],$row['invoicenumber'],$row['trantype'],$row['immediatecash'],$row['immediatecheck'],$row['total']);
                $i++;
            }
        }
        else
        {
            //$responce->rows[$i]['id']=1;
            //$responce->rows[$i]['cell']=array("","","","No Record Founds","", "");
        }
        
        $responce->userdata['mop'] = $this->translate->_('Total');       
        $responce->userdata['immediatecash'] = $total_immediatecash;
        $responce->userdata['immediatecheck'] = $total_immediatecheck;        
        $responce->userdata['total'] = $total_cnt;
        echo json_encode($responce);
        exit;
    }
      
      
       /**
    * @name       exportAction
    * @since      15-02-2012
    * @version    Release: 1
    * @author     HC <harsh@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This export the pdf in HTML format
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
         if(isset($this->report_session->routecode_str) && $this->report_session->routecode_str != "")
        {
            $extra_where  .= ' and ih.routecode IN ('.$this->report_session->routecode_str.')';
            $extra_where1  .= ' and arh.routecode IN ('.$this->report_session->routecode_str.')';
        }
        if($this->report_session->post['txt_route_start_date'] != "" && $this->report_session->post['txt_route_end_date'] != "")
        {
            $extra_where  .= ' AND ih.transactiondate BETWEEN "'.date("Y-m-d",strtotime($this->report_session->post['txt_route_start_date'])).'" AND "'.date("Y-m-d",strtotime($this->report_session->post['txt_route_end_date'])).'"';
            $extra_where1 .= ' AND arh.transactiondate BETWEEN "'.date("Y-m-d",strtotime($this->report_session->post['txt_route_start_date'])).'" AND "'.date("Y-m-d",strtotime($this->report_session->post['txt_route_end_date'])).'"';
        }
        elseif($this->report_session->post['txt_route_start_date'] != "" && $this->report_session->post['txt_route_end_date'] == "")
        {
            $extra_where  .= ' AND ih.transactiondate >= "'.date("Y-m-d",strtotime($this->report_session->post['txt_route_start_date'])).'"';
            $extra_where1 .= ' AND arh.transactiondate >= "'.date("Y-m-d",strtotime($this->report_session->post['txt_route_start_date'])).'"';
        }
        elseif($this->report_session->post['txt_route_start_date'] == "" && $this->report_session->post['txt_route_end_date'] != "")
        {
            $extra_where  .= ' AND ih.transactiondate <= "'.date("Y-m-d",strtotime($this->report_session->post['txt_route_end_date'])).'"';
            $extra_where1 .= ' AND arh.transactiondate <= "'.date("Y-m-d",strtotime($this->report_session->post['txt_route_end_date'])).'"';
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
        $param_array[2] = "routecode,salesmancode,trantype,".$sidx;
        $param_array[3] = $sord;
        $param_array[4] = 15000;
        $param_array[5] = 1;
        
        $result_arr = $this->SFA_Comman->executequery('CALL sp_report_transaction_deposit_summary(?,?,?,?,?,?)',$param_array,'');
        //print_r($result_arr);exit;
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
        $data_arr["columns"] = array($this->translate->_('Route'),$this->translate->_('Transaction Date'),$this->translate->_('CustomerCode'),$this->translate->_('invoicenumber'),$this->translate->_('Type'),$this->translate->_('Immediate Cash'),$this->translate->_('Immediate Cheque'),$this->translate->_('Total'));
       
        $data_arr["columns_config"] = array(array("width"=>40,"toaltext"=>$this->translate->_('Total')),
                                            array("width"=>12),
                                            array("width"=>30),
                                            array("width"=>12),
                                            array("width"=>10),
                                            array("width"=>12,"total"=>"1"),
                                            array("width"=>12,"total"=>"1"),
                                            array("width"=>12,"total"=>"1")
                                        );
     //  print("<pre>".print_r($result_arr[2],true)."</pre>");exit;
	// echo count($result_arr[2]);exit;
        for($i = 0; $i < count($result_arr[2]); $i++)
        { //print_r($result_arr[0][$i]['routecode']);exit;
            $routename = ($this->session->lang == "ar_AR") ? $result_arr[2][$i]['arbroutename'] : $result_arr[2][$i]['routename'];
            $salesmanname = ($this->session->lang == "ar_AR") ? $result_arr[0][$i]['arbsalesmanname1'] : $result_arr[0][$i]['salesmanname1'];
            
            $column_model_arr[] = array($result_arr[2][$i]['routecode']."-".$routename,$result_arr[2][$i]['transactiondate'],$result_arr[2][$i]['CustomerCode'],$result_arr[2][$i]['invoicenumber'],$result_arr[2][$i]['trantype'],
                                        $result_arr[2][$i]['immediatecash'], $result_arr[2][$i]['immediatecheck'],$result_arr[2][$i]['total']
                                        );
        }
        //echo $i;exit;
        $data_arr["columns_model"]          = $column_model_arr;
        $data_arr["config"]["report_title"] = $this->translate->_("Deposit Summary").$report_title;
        $data_arr["config"]["report_title_height"] = $report_title_height;
        $data_arr["config"]["file_name"]    = "DepositSummary";
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
    public function expor1tAction()
    {
        $this->view->params = $params = $this->getRequest()->getParams();
        $this->view->formdata = $formdata = $this->_request->getPost();
        $this->view->css 		= $this->translate->_('CSS');
        $extra_where = "";
        $extra_where1 = "";
        
        /*Following code commented and new condition added by nilesh on 04Apr2016 for filter reports*/
      /*  if(isset($this->report_session->routecode_str) && $this->report_session->routecode_str != "")
        {
            $extra_where  .= " and ih.routecode IN (".$this->report_session->post['ddlroute'].")";
            $extra_where1  .= " and arh.routecode IN (".$this->report_session->post['ddlroute'].")";
        }*/
		 if(isset($this->report_session->routecode_str) && $this->report_session->routecode_str != "")
        {
            $extra_where  .= ' and ih.routecode IN ('.$this->report_session->routecode_str.')';
            $extra_where1  .= ' and arh.routecode IN ('.$this->report_session->routecode_str.')';
        }
        if($this->report_session->post['txt_route_start_date'] != "" && $this->report_session->post['txt_route_end_date'] != "")
        {
            $extra_where  .= ' AND ih.transactiondate BETWEEN "'.date("Y-m-d",strtotime($this->report_session->post['txt_route_start_date'])).'" AND "'.date("Y-m-d",strtotime($this->report_session->post['txt_route_end_date'])).'"';
            $extra_where1 .= ' AND arh.transactiondate BETWEEN "'.date("Y-m-d",strtotime($this->report_session->post['txt_route_start_date'])).'" AND "'.date("Y-m-d",strtotime($this->report_session->post['txt_route_end_date'])).'"';
        }
        elseif($this->report_session->post['txt_route_start_date'] != "" && $this->report_session->post['txt_route_end_date'] == "")
        {
            $extra_where  .= ' AND ih.transactiondate >= "'.date("Y-m-d",strtotime($this->report_session->post['txt_route_start_date'])).'"';
            $extra_where1 .= ' AND arh.transactiondate >= "'.date("Y-m-d",strtotime($this->report_session->post['txt_route_start_date'])).'"';
        }
        elseif($this->report_session->post['txt_route_start_date'] == "" && $this->report_session->post['txt_route_end_date'] != "")
        {
            $extra_where  .= ' AND ih.transactiondate <= "'.date("Y-m-d",strtotime($this->report_session->post['txt_route_end_date'])).'"';
            $extra_where1 .= ' AND arh.transactiondate <= "'.date("Y-m-d",strtotime($this->report_session->post['txt_route_end_date'])).'"';
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
        $param_array[2] = "routecode,salesmancode,trantype,".$sidx;
        $param_array[3] = $sord;
        $param_array[4] = 15000;
        $param_array[5] = 1;
       // $param_array[6] = $extra_where1;
  
        $result_arr = $this->SFA_Comman->executequery('CALL sp_report_transaction_deposit_summary(?,?,?,?,?,?)',$param_array,'');
        //print_r($result_arr);exit;
        $report_title_height = 15;
        FOR($i=0;$i<COUNT($this->report_session->searchParams);$i++)
        {
            IF($this->report_session->searchParams[$i]["value"] != "")
            {
                IF($this->css == "ar_") {
                    $report_title .= "\r ".$this->report_session->searchParams[$i]["value"]." : ".$this->translate->_($this->report_session->searchParams[$i]["title"]);
                } ELSE {
                    $report_title .= "\r ".$this->translate->_($this->report_session->searchParams[$i]["title"]) . " : ".$this->report_session->searchParams[$i]["value"];
                }
                $report_title_height += 10;
            }
        }
        //print_r( $result_arr[2]);exit;
$result_arr[1]=$result_arr[2];
        $data = $result_arr[1];
        $data_arr = array();
        
        $column_model_arr = array();
        $data_arr["columns"] = array($this->translate->_('Route'),$this->translate->_('Transaction Date'),$this->translate->_('CustomerCode'),$this->translate->_('invoicenumber'),$this->translate->_('Type'),$this->translate->_('Immediate Cash'),$this->translate->_('Immediate Cheque'),$this->translate->_('Total'));
       
//print_r( $data_arr);exit;
//print_r( $data_arr);exit;

	   $data_arr["columns_config"] =   array(
                                            array("width"=>13),
                                            array("width"=>10), 
											array("width"=>20),
											array("width"=>10),											
                                            array("width"=>15,"toaltext"=>$this->translate->_('Total'),"group_total_text"=>$this->translate->_('Group Total')),
                                           
											array("width"=>15,"total"=>"1","group_total"=>"1"),
                                            array("width"=>15,"total"=>"1","group_total"=>"1"),
                                            array("width"=>15,"total"=>"1","group_total"=>"1")
                                        );
        for($i = 0; $i < count($result_arr[1]); $i++)
        {
            $routename 		= ($this->css == 'ar_') ? $result_arr[1][$i]['arbroutename'] 	    : $result_arr[1][$i]['routename'];
            $salesmanname	= ($this->css == 'ar_') ? $result_arr[1][$i]['arbsalesmanname1'] 	: $result_arr[1][$i]['salesmanname1'];
            
            $column_model_arr[$result_arr[1][$i]['routecode']." - ".$routename][$result_arr[1][$i]['trantype']][] = array( $result_arr[1][$i]['transactiondate'],$result_arr[1][$i]['immediatecash'],$result_arr[1][$i]['immediatecheck'],$result_arr[1][$i]['total'],$result_arr[1][$i]['immediatecheck'],$result_arr[1][$i]['total']);
        }
        //pr($column_model_arr,1);
		//print_r( $column_model_arr);exit;
        $data_arr["columns_model"]          = $column_model_arr;
        $data_arr["config"]["report_title"] = $this->translate->_("Deposit Summary").$report_title;
        $data_arr["config"]["report_title_height"] = $report_title_height;
        $data_arr["config"]["file_name"]    = "DepositSummary";
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
