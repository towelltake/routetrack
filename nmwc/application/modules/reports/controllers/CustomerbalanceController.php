<?php
/**
* @name       AccountController
* @since      20-02-2012
* @version    Release: 1
* @author     PM <pankit@elantechnologies.com>
* @copyright  Elan Technologies
* @param
*
* This controller is manage report module.
*/
class Reports_CustomerbalanceController extends Reports_Library_Controller_Action_Abstract
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
        
        $this->report_session		= new Zend_Session_Namespace('Re_customerbalance');
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
    public function customerpendingbalanceAction()
    {
        $this->view->params 	= $params = $this->getRequest()->getParams();
        $this->view->formdata  = $formdata = $this->_request->getPost();
		 $param_checkarray 	= array();			
	 $param_checkarray[1]	= "ACCOUNT";
	 $result_arr = $this->SFA_Comman->executequery('CALL sp_get_year()',$param_checkarray,'');	
        $this->view->curyear =$result_arr[0][0]['cur_year'];
       // $this->view->curyear =date('Y');
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
        
        $this->view->ReportTitle = $this->translate->_("Customer Pending Balance");
        $this->view->pageHeaderTitle  = $this->translate->_('Date');
        $this->view->pageHeadervalue  =  date("m/d/Y h:i:s");
        $extra_where = "";
        if($this->report_session->post['ddlyear'] != "" )
        {
            $extra_where .= ' AND DATE_FORMAT(ci.transactiondate,"%Y") = "'.$this->report_session->post['ddlyear'].'"';
        }
        
        $param_array = array();
        $param_array[1] = $extra_where;
        $param_array[2] = '';
        $param_array[3] = '';
        $param_array[4] = '';
        $param_array[5] = '';
        $param_array[6] = '1';
        
        $result_arr = $this->SFA_Comman->executequery('CALL sp_report_account_customerpendingbalance(?,?,?,?,?,?)',$param_array,'');
        
        $column = array($this->translate->_('HOCode'),$this->translate->_('Customer'),$this->translate->_('Transaction Date'),$this->translate->_('Salesman Code'),$this->translate->_('Invoice Number'),$this->translate->_('Comments'));
        $column_config = array('hocode','customercode','transactiondate','salesmancode','invoicenumber','comments');
        $header = array();
        
        for($i=0;$i<count($result_arr[0]);$i++)
        {
            $column[] = $result_arr[0][$i]['monthtxt'];
            $postfix = '_'.$result_arr[0][$i]["monthno"].'_'.$result_arr[0][$i]["yearno"];
            $column_config[] = $result_arr[0][$i]['monthtxt'].$postfix;
            $header[] = array("title"=>$result_arr[0][$i]["yearno"],"column"=>$result_arr[0][0]['monthtxt'].$postfix);
            $headerval[] =  array("month"=>$result_arr[0][$i]["monthno"],"year" =>$result_arr[0][$i]["yearno"]);
            $sent_column[$result_arr[0][$i]["monthno"]."_".$result_arr[0][$i]["yearno"]] = $result_arr[0][$i]['monthtxt'];
            $export_header[$result_arr[0][$i]["yearno"]][] = $result_arr[0][$i]["monthtxt"];
        }
        $column[] = $this->translate->_("Total");
        $column_config[] = "total";
        $this->report_session->count_header = count($header);
        $this->view->column = $column;
        $this->view->header = $header;
        $this->report_session->header = $headerval;
        $this->view->column_config = $column_config;
        $this->report_session->column_val = $column;
        $this->view->count_col = count($result_arr[0]);
        $this->report_session->sent_column = $sent_column;
        $this->report_session->header_txt = $export_header;
        
        $this->view->searchParams  =    array(
                                            array("title"=> "Year",
                                                  "value"=> $formdata['ddlyear_selected']),
                                            array("title"=> "Customer Type",
                                                  "value"=> $formdata['ddlcustomertype_selected']),
                                            array("title"=> "Customer",
                                                  "value"=> $formdata['ddlcustomer_selected'])
                                        );
        $this->report_session->searchParams = $this->view->searchParams;
        
        $this->view->xlsexport_link = $this->view->baseUrl()."/reports/customerbalance/export";
        $this->view->cvsexport_link = $this->view->baseUrl()."/reports/customerbalance/exportcsv";
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
    public function custpendingbalAction()
    {
        $this->view->params = $params = $this->getRequest()->getParams();
        $this->view->formdata = $formdata = $this->_request->getPost();
        $this->view->css 		= $this->translate->_('CSS');
        $extra_where = "";
        
        if($this->report_session->post['ddlyear'] != "" )
        {
            $extra_where .= ' AND DATE_FORMAT(ci.transactiondate,"%Y") = "'.$this->report_session->post['ddlyear'].'"';
        }
        if($this->report_session->post['ddlcustomertype'] == 3 )
        {
            if($this->report_session->post['ddlcustomer'] !='')
                $extra_where  .= " and cm.headofficecode = ".$this->report_session->post['ddlcustomer']." and cm.type in (2,3)";
            else
                $extra_where .= " and cm.type in (2,3)";
        }
        else
        {
            if($this->report_session->post['ddlcustomer'] !='')
                $extra_where  .= " and cm.customercode=".$this->report_session->post['ddlcustomer']."";
            else
                $extra_where  .= " and cm.customercode > 0";
            if($this->report_session->post['ddlcustomertype'] !='')
                $extra_where  .= " and cm.type in (".$this->report_session->post['ddlcustomertype'].")";
        }
        
        $page = $_GET['page']; // get the requested page
        $limit = $_GET['rows']; // get how many rows we want to have into the grid
        $sidx = $_GET['sidx']; // get index row - i.e. user click to sort
        $sord = $_GET['sord']; // get the direction
        if(!$sidx) $sidx =1;
        
        if(empty($sidx)) {  $sidx  = "hocode";  }
        if(empty($sord)) {  $sord  = "asc";  }
        
        
        $param_array = array();
        $param_array[1] = $extra_where;
        $param_array[2] = ",".$sidx;
        $param_array[3] = $sord;
        $param_array[4] = $limit;
        $param_array[5] = $page;
        $param_array[6] = 0;
        
        $result_arr = $this->SFA_Comman->executequery('CALL sp_report_account_customerpendingbalance(?,?,?,?,?,?)',$param_array,'');
        
        //pr($result_arr,1);
        $count  = $result_arr[0][0]['counter'];
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
        
        $i=0;$j=0;
        $rowindex = array();
        $final_arr = array();
        $final_total_val = 0;
        if(!empty($result_arr[1])){
            foreach($result_arr[1] as $row) {
                
                $customername = ($this->css == 'ar_') ? $row['arbcustomername'] : $row['customername'];
                if(isset($rowindex[$row['hocode']." - ".$row['honame']][$row['customercode']." - ".$customername][$row['transactiondate']][$row['invoicenumber']])) {
                    $index = $rowindex[$row['hocode']." - ".$row['honame']][$row['customercode']." - ".$customername][$row['transactiondate']][$row['invoicenumber']];
                    $newrow[$index][$row['yearno']][$row['monthno']] = $row['invoicebalance'];
                } else {
                    $rowindex[$row['hocode']." - ".$row['honame']][$row['customercode']." - ".$customername][$row['transactiondate']][$row['invoicenumber']] = $j;
                    $newrow[$j] = array("hocode"=>$row['hocode']." - ".$row['honame'],"customercode" => $row['customercode']." - ".$customername,"transactiondate" => $row['transactiondate'],"salesmancode" => $row['salesmancode'],"invoicenumber" => $row['invoicenumber'],"comments" => $row['comments']);
                    $newrow[$j][$row['yearno']][$row['monthno']] = $row['invoicebalance'];
                    $j++;
                }
                if(isset($final_arr[$row['monthno']."_".$row['yearno']]) && $final_arr[$row['monthno']."_".$row['yearno']] != "")
                {
                    $final_arr[$row['monthno']."_".$row['yearno']] += $row['invoicebalance'];
                }
                else
                {
                    $final_arr[$row['monthno']."_".$row['yearno']] = $row['invoicebalance'];
                }
                $i++;
            }
            //pr($newrow,1);
            for( $i = 0 ;$i < count($newrow);$i++)
            {
                $total_monthly = 0;
                $dataarr = array();
                $dataarr = array($newrow[$i]['hocode'],$newrow[$i]['customercode'],$newrow[$i]['transactiondate'],$newrow[$i]['salesmancode'],$newrow[$i]['invoicenumber'],$newrow[$i]['comments']);
                for($j=0;$j<count($this->report_session->header);$j++)
                {
                    if(isset($newrow[$i][$this->report_session->header[$j]['year']][$this->report_session->header[$j]['month']]) && !empty($newrow[$i][$this->report_session->header[$j]['year']][$this->report_session->header[$j]['month']]))
                    {
                        $dataarr1 = round($newrow[$i][$this->report_session->header[$j]['year']][$this->report_session->header[$j]['month']],2);
                    }
                    else
                    {
                        $dataarr1 = 0;
                    }
                    $total_monthly += $dataarr1;
                    $dataarr[] = $dataarr1;
                    
                }
                $dataarr[] = $total_monthly;
                $final_total_val += $total_monthly;
                $responce->rows[$i]['id']=$i;
                $responce->rows[$i]['cell']=$dataarr;
            }
        }
        if(isset($this->report_session->sent_column) && count($this->report_session->sent_column) > 0) {
            foreach($this->report_session->sent_column as $key => $val) {
                $responce->userdata[$val.'_'.$key] = round($final_arr[$key],2);
            }
        }
        $responce->userdata['total'] = $final_total_val;
        $responce->userdata['comment'] = $this->translate->_("Total");
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
        
        if($this->report_session->post['ddlyear'] != "" )
        {
            $extra_where .= ' AND DATE_FORMAT(ci.transactiondate,"%Y") = "'.$this->report_session->post['ddlyear'].'"';
        }
        if($this->report_session->post['ddlcustomertype'] == 3 )
        {
            if($this->report_session->post['ddlcustomer'] !='')
                $extra_where  .= " and cm.headofficecode = ".$this->report_session->post['ddlcustomer']." and cm.type in (2,3)";
            else
                $extra_where  .= " and cm.type in (2,3)";
        }
        else
        {
            if($this->report_session->post['ddlcustomer'] !='')
                $extra_where  .= " and cm.customercode=".$this->report_session->post['ddlcustomer']."";
            else
                $extra_where  .= " and cm.customercode > 0";
            if($this->report_session->post['ddlcustomertype'] !='')
                $extra_where  .= " and cm.type in (".$this->report_session->post['ddlcustomertype'].")";
        }
        
        $page = $_GET['page']; // get the requested page
        $limit = $_GET['rows']; // get how many rows we want to have into the grid
        $sidx = $_GET['sidx']; // get index row - i.e. user click to sort
        $sord = $_GET['sord']; // get the direction
        if(!$sidx) $sidx =1;
        
        if(empty($sidx)) {  $sidx  = "hocode";  }
        if(empty($sord)) {  $sord  = "asc";  }
        
        $param_array = array();
        $param_array[1] = $extra_where;
        $param_array[2] = ",customercode,".$sidx;
        $param_array[3] = $sord;
        $param_array[4] = $limit;
        $param_array[5] = $page;
        $param_array[6] = 0;
        
        $result_arr = $this->SFA_Comman->executequery('CALL sp_report_account_customerpendingbalance(?,?,?,?,?,?)',$param_array,'');
        
        $report_title_height = 20;
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
        $column_model_arr = array();
        $rowindex = array();
        $j = 0;
        
        if(!empty($result_arr[0])){
            foreach($result_arr[0] as $row) {
                $customername = ($this->css == 'ar_') ? $row['arbcustomername'] : $row['customername'];
                if(isset($rowindex[$row['hocode']." - ".$row['honame']][$row['customercode']." - ".$customername][$row['transactiondate']][$row['invoicenumber']])) {
                    $index = $rowindex[$row['hocode']." - ".$row['honame']][$row['customercode']." - ".$customername][$row['transactiondate']][$row['invoicenumber']];
                    $newrow[$index][$row['yearno']][$row['monthno']] = $row['invoicebalance'];
                } else {
                    $rowindex[$row['hocode']." - ".$row['honame']][$row['customercode']." - ".$customername][$row['transactiondate']][$row['invoicenumber']] = $j;
                    $newrow[$j] = array("hocode"=>$row['hocode']." - ".$row['honame'],"customercode" => $row['customercode']." - ".$customername,"transactiondate" => $row['transactiondate'],"salesmancode" => $row['salesmancode'],"invoicenumber" => $row['invoicenumber'],"comments" => $row['comments']);
                    $newrow[$j][$row['yearno']][$row['monthno']] = $row['invoicebalance'];
                    $j++;
                }
                if(isset($final_arr[$row['monthno']."_".$row['yearno']]) && $final_arr[$row['monthno']."_".$row['yearno']] != "")
                {
                    $final_arr[$row['monthno']."_".$row['yearno']] += $row['invoicebalance'];
                }
                else
                {
                    $final_arr[$row['monthno']."_".$row['yearno']] = $row['invoicebalance'];
                }
                $i++;
            }
            
            $export_arr = array();
            for( $i = 0 ;$i < count($newrow);$i++)
            {
                $total_monthly = 0;
                $dataarr = array();
                $dataarr = array($newrow[$i]['transactiondate'],$newrow[$i]['salesmancode'],$newrow[$i]['invoicenumber'],$newrow[$i]['comments']);
                for($j=0;$j<count($this->report_session->header);$j++)
                {
                    if(isset($newrow[$i][$this->report_session->header[$j]['year']][$this->report_session->header[$j]['month']]) && !empty($newrow[$i][$this->report_session->header[$j]['year']][$this->report_session->header[$j]['month']]))
                    {
                        $dataarr1 = round($newrow[$i][$this->report_session->header[$j]['year']][$this->report_session->header[$j]['month']],2);
                    }
                    else
                    {
                        $dataarr1 = 0;
                    }
                    $total_monthly += $dataarr1;
                    $dataarr[] = $dataarr1;
                    
                }
                $dataarr[] = $total_monthly;
                $export_arr[$newrow[$i]['hocode']][$newrow[$i]['customercode']][] = $dataarr;
            }
            $column_model_arr = $export_arr;
        }
        
        $data_arr = array();
        
        $data_arr["columns"] = $this->report_session->column_val;
        $data_arr["columns_config"] =   array(
                                            array("width"=>15),
                                            array("width"=>15),
                                            array("width"=>15),
                                            array("width"=>15),
                                            array("width"=>15),
                                            array("width"=>35,"toaltext"=>$this->translate->_('Total'),"group_total_text"=>$this->translate->_('Group Total'))
                                        );
        for($j=6;$j<count($this->report_session->column_val);$j++)
        {
            $data_arr["columns_config"][] = array("width"=>15,"total"=>"1","group_total"=>"1");
        }
       // $data_arr["columns_config"][] = array("width"=>15,"total"=>"1","group_total"=>"1");
        
        $data_arr["columns_model"]          = $column_model_arr;
        $data_arr["config"]["report_title"] = $this->translate->_("Customer Pending Balance").$report_title;
        $data_arr["config"]["report_title_height"] = $report_title_height;
        $data_arr["config"]["file_name"]    = "CustomerPendingBalance";
        $data_arr["config"]["group_level"]  = 2;
        $data_arr["config"]["total_columns"]= count($data_arr["columns"]);
        $data_arr["config"]["group_total"]  = "1";
        $data_arr["config"]["main_total"]   = "1";
        
        /* ONLY for extra Heading */
        $data_arr["config"]["main_heading"] = "1";
        
        $i =0;
        foreach($this->report_session->header_txt as $key => $val) {
            $startindex = ($i==0) ? 6 : $lastindex+2;
            $lastindex = ($startindex+count($val)-1);
            $newheading_arr[] = array("title"=>$key,"start_index"=>$startindex,"last_index"=>$lastindex);
            $i++;
        }
        
        $data_arr["main_heading_arr"] = $newheading_arr;
        
        $SFA_Exportxls = new SFA_Exportxls($data_arr);
        $objPHPExcel = $SFA_Exportxls->exportxls();
        $objWriter = PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel5');
        $objWriter->save('php://output');
        exit;
    }
    
    /**
    * @name       getcustomersAction
    * @since      05-10-2012
    * @version    Release: 1
    * @author     HD <hiren.d@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This action is for getting  customer info on the basis of the customer type.
    *
    */
    public function getcustomersAction()
    {
        $params = $this->getRequest()->getParams();
		$param_array =array();
		$param_array[1] = $params['cust_type'];
        $param_array[2] = 1;
		
        $result_arr = $this->SFA_Comman->executequery('CALL sp_report_account_customerpendingbalance_detail(?,?)',$param_array,'');
        //$result_arr = $this->SFA_Comman->executequery('CALL sp_report_account_customerpendingbalance_detail(?)',$params['cust_type'],'');
        echo Zend_Json::encode($result_arr);
		exit;
	}
}
