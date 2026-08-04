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
class Reports_AccountrouteageingController extends Reports_Library_Controller_Action_Abstract
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
        
        $this->report_session		= new Zend_Session_Namespace('Re_routeageing');
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
    public function routeageingAction()
    {
        $this->view->params 	= $params = $this->getRequest()->getParams();
        $this->view->formdata  = $formdata = $this->_request->getPost();
	 
        //$result_arr = $this->SFA_Comman->executequery('CALL sp_report_account_route_aging_detail()','','');
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
        
        $this->view->ReportTitle = $this->translate->_("Route Ageing");
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
                                                  "value" => $title_val)
                                            );
        $this->report_session->searchParams = $this->view->searchParams;
        
        $this->view->xlsexport_link = $this->view->baseUrl()."/reports/accountrouteageing/export";
        $this->view->cvsexport_link = $this->view->baseUrl()."/reports/accountrouteageing/exportcsv";
        
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
    public function routeageAction()
    {
        $this->view->params = $params = $this->getRequest()->getParams();
        $this->view->formdata = $formdata = $this->_request->getPost();
        $this->view->css 		= $this->translate->_('CSS');
        $extra_where = "";
        
        if(isset($this->report_session->routecode_str) && $this->report_session->routecode_str != "")
        {
            $extra_where .= ' AND ci.routecode IN ('.$this->report_session->routecode_str.')';
        }
        else
        {
            $extra_where  = " AND ci.routecode >0";
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
    
        $result_arr = $this->SFA_Comman->executequery('CALL sp_report_account_route_aging(?,?,?,?,?)',$param_array,'');
        //pr($result_arr,1);
	  
	  
	 // echo "<pre>";
	 // print_r($result_arr);
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
        $i=0;
        $total_age = $total_age31 = $total_age61 = $total_age91 = $total_age121 = $total_pdcamount = $total_invoicebalance = 0;
        if(!empty($result_arr[1])){
        foreach($result_arr[1] as $row) {
            $total_age += $row['age'];
            $total_age31 += $row['age31'];
            $total_age61 += $row['age61'];
            $total_age91 += $row['age91'];
            $total_age121 += $row['age121'];
            $total_pdcamount += $row['pdcamount'];
            $total_invoicebalance += $row['invoicebalance'];
            
            $routename = ($this->css == 'ar_') ? $row['arbroutename'] : $row['routename'];
            $salesmanname = ($this->css == 'ar_') ? $row['arbsalesmanname1'] : $row['salesmanname1'];
            $customername = ($this->css == 'ar_') ? $row['arbcustomername'] : $row['customername'];
            $pdcdate = ($row['pdcdate'] != "")? date("d M Y",strtotime($row['pdcdate'])):"";
            
            $responce->rows[$i]['id']=$i;
            $responce->rows[$i]['cell']=array($row['routecode']." - ".$routename,$row['salesmancode']." - ".$salesmanname,$row['transactiondate'],$row['invoicenumber'],
                                              $row['customercode'], $customername, $row['creditlimitdays'], $row['age'], $row['age31'], $row['age61'], $row['age91'], $row['age121'],
                                              $row['pdcamount'],$pdcdate , $row['invoicebalance']
                                        );
            $i++;
          
        }
        }
        else
        {
            //  $responce->rows[$i]['id']=1;
            //  $responce->rows[$i]['cell']=array("","","","No Record Founds","", "");
        }
  
        $responce->userdata['creditlimitdays'] = $this->translate->_("Total");
        $responce->userdata['age'] = $total_age;
        $responce->userdata['age31'] = $total_age31;
        $responce->userdata['age61'] = $total_age61;
        $responce->userdata['age91'] = $total_age91;
        $responce->userdata['age121'] = $total_age121;
        $responce->userdata['pdcamount'] = $total_pdcamount;
        $responce->userdata['invoicebalance'] = $total_invoicebalance;
        
        echo json_encode($responce);
        exit;
    }
      
      
       /**
    * @name       exportpdfAction
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
        
        if(isset($this->report_session->routecode_str) && $this->report_session->routecode_str != "")
        {
            $extra_where .= ' AND ci.routecode IN ('.$this->report_session->routecode_str.')';
        }
        else
        {
            $extra_where  = " AND ci.routecode >0";
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
        $param_array[2] = 'routecode,salesmancode,'.$sidx;
        $param_array[3] = $sord;
        $param_array[4] = $limit;
        $param_array[5] = $page;
        
        $result_arr = $this->SFA_Comman->executequery('CALL sp_report_account_route_aging(?,?,?,?,?)',$param_array,'');
        
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
        $data_arr["columns"] = array($this->translate->_('Route'),$this->translate->_('Salesman'),$this->translate->_('Transaction Date'),$this->translate->_('Invoice No'),$this->translate->_('Customer Code'),$this->translate->_( 'Customer Name'),$this->translate->_( 'Credit Days'),$this->translate->_('1-30'),$this->translate->_('31-60'),$this->translate->_('61-90'),$this->translate->_('91-120'),$this->translate->_('Above 120'),$this->translate->_('PDC Amount'),$this->translate->_('PDC Date'),$this->translate->_('Total'));
        $data_arr["columns_config"] = array(
                                            array("width"=>12),
                                            array("width"=>12),
                                            array("width"=>12),
                                            array("width"=>12),
                                            array("width"=>15,"toaltext"=>$this->translate->_('Total'),"group_total_text"=>$this->translate->_('Group Total')),
                                            array("width"=>13),
                                            array("width"=>15),
                                            array("width"=>12,"total"=>"1","group_total"=>"1"),
                                            array("width"=>12,"total"=>"1","group_total"=>"1"),
                                            array("width"=>12,"total"=>"1","group_total"=>"1"),
                                            array("width"=>15,"total"=>"1","group_total"=>"1"),
                                            array("width"=>15,"total"=>"1","group_total"=>"1"),
                                            array("width"=>15,"total"=>"1","group_total"=>"1"),
											array("width"=>15,"total"=>"1","group_total"=>"1"),
                                            array("width"=>15,"total"=>"1","group_total"=>"1")
                                        );
        for($i = 0; $i < count($result_arr[0]); $i++)
        {
            $routename = ($this->css == 'ar_') ? $result_arr[0][$i]['arbroutename'] : $result_arr[0][$i]['routename'];
            $salesmanname = ($this->css == 'ar_') ? $result_arr[0][$i]['arbsalesmanname1'] : $result_arr[0][$i]['salesmanname1'];
            $customername = ($this->css == 'ar_') ? $result_arr[0][$i]['arbcustomername'] : $result_arr[0][$i]['customername'];
            $pdcdate = ($result_arr[0][$i]['pdcdate'] != "") ? date("d M Y",strtotime($result_arr[0][$i]['pdcdate'])) :"";
            
            $column_model_arr[$result_arr[0][$i]['routecode']." - ".$routename][$result_arr[0][$i]['salesmancode']." - ".$salesmanname][] =array($result_arr[0][$i]['transactiondate'],$result_arr[0][$i]['invoicenumber'],
                                              $result_arr[0][$i]['customercode'],$customername, $result_arr[0][$i]['creditlimitdays'], $result_arr[0][$i]['age'], $result_arr[0][$i]['age31'], $result_arr[0][$i]['age61'], $result_arr[0][$i]['age91'],
                                              $result_arr[0][$i]['age121'], $result_arr[0][$i]['pdcamount'],$pdcdate , $result_arr[0][$i]['invoicebalance']
                                        );
        }
        //pr($column_model_arr,1);
        $data_arr["columns_model"]          = $column_model_arr;
        $data_arr["config"]["report_title"] = $this->translate->_("Route Ageing").$report_title;
        $data_arr["config"]["report_title_height"] = $report_title_height;
        $data_arr["config"]["file_name"]    = "RouteAgeing";
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
