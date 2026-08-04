<?php
/**
* @name       AveragedropsizeController
* @since      15-10-2012
* @version    Release: 1
* @author     PT <pankil@elantechnologies.com>
* @copyright  Elan Technologies
* @param
*
* This controller is manage report module.
*/
class Reports_AveragedropsizeController extends Reports_Library_Controller_Action_Abstract
{
    /**
    * @name       init
    * @since      15-10-2012
    * @version    Release: 1
    * @author     PT <pankil@elantechnologies.com>
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
        
        $this->report_session = new Zend_Session_Namespace('Re_averagedropsize');
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
    * @name       averagedropsizeAction
    * @since      15-10-2012
    * @version    Release: 1
    * @author     PT <pankil@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This action is for display daily sales sheet report
    *
    */
    public function averagedropsizeAction()
    {
        $this->view->params 	= $params = $this->getRequest()->getParams();
        $this->view->formdata  = $formdata = $this->_request->getPost();
		$this->view->itemgrid    = $this->view->BaseUrl("/".$params['module']."/ajaxdata/useraccessgrid");
        
        $this->report_session->post = array();
	}

    /**
    * @name       indexAction
    * @since      15-10-2012
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
        
        $this->view->ReportTitle = $this->translate->_("Average Drop Size");
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
                                           array("title"=> "Route Start Date - Month",
                                                 "value" => $formdata['month_selected']),
                                           array("title"=> "Route Start Date - Year",
                                                 "value" => $formdata['year_selected'])
                                           );
        $this->report_session->searchParams = $this->view->searchParams;
        $this->view->xlsexport_link = $this->view->baseUrl()."/reports/averagedropsize/export";
        $this->view->cvsexport_link = $this->view->baseUrl()."/reports/averagedropsize/exportcsv";
    }
    /**
      * @name       averagedropsizedataAction
      * @since      15-10-2012
      * @version    Release: 1
      * @author     PT <pankil@elantechnologies.com>
      * @copyright  Elan Technologies
      * @param
      *
      * This action fetch customer pending request data
      *
      */
    public function averagedropsizedataAction()
    {
        $this->view->params = $params = $this->getRequest()->getParams();
        $this->view->formdata = $formdata = $this->_request->getPost();
        $this->view->css 		= $this->translate->_('CSS');
        $extra_where = "";
        $customertype = "";
        $customercode = "";
        
        if(isset($this->report_session->post['year']) && $this->report_session->post['year'] != "")
        {
            $extra_where  .= " AND YEAR(routestartdate) = ".$this->report_session->post['year'];
        }
        if(isset($this->report_session->post['month']) && $this->report_session->post['month'] != "")
        {
            $extra_where  .= " AND MONTH(routestartdate) = ".$this->report_session->post['month'];
        }
      
        $page = $_GET['page']; // get the requested page
        $limit = $_GET['rows']; // get how many rows we want to have into the grid
        $sidx = $_GET['sidx']; // get index row - i.e. user click to sort
        $sord = $_GET['sord']; // get the direction
        if(!$sidx) $sidx =1;
        
        if(empty($sidx)) {  $sidx  = "YEAR(routestartdate), MONTH(routestartdate), routecode";}
        if(empty($sord)) {  $sord  = "asc";}
    
        $param_array = array();
        $param_array[1] = $extra_where;
        $param_array[2] = $sidx;
        $param_array[3] = $sord;
        $param_array[4] = $limit;
        $param_array[5] = $page;
        
        //pr($param_array);
        $result_arr = $this->SFA_Comman->executequery('CALL sp_report_dataanalysis_kpi_averagedropsize(?,?,?,?,?)',$param_array,'');
        
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
        
        $no_of_invoice = $sale_qty = $free_qty = $promo_qty = $return_qty = $buyback_qty = $net_sales = 0;
        if(!empty($result_arr[1])){
            foreach($result_arr[1] as $row) {
                $responce->rows[$i]['id'] = $i;
                
                $no_of_invoice += $row['no_of_invoice'];
                $sale_qty += $row['sale_qty'];
                $free_qty += $row['free_qty'];
                $promo_qty += $row['promo_qty'];
                $return_qty += $row['return_qty'];
                $buyback_qty += $row['buyback_qty'];
                $net_sales += $row['net_sales'];
                
                $salesmanname = ($this->css == 'ar_') ? $row['arbsalesmanname1'] : $row['salesmanname1'];
                
                $responce->rows[$i]['cell'] = array($row['salesmancode']." - ".$salesmanname,$row['no_of_invoice'],$row['sale_qty'],$row['free_qty'],
                                                $row['promo_qty'], $row['return_qty'],$row['buyback_qty'],
                                                $row['net_sales'],$row['qty_drop_size'],$row['value_drop_size']
                                                );
                $i++;
              
            }
        }
        else
        {
          //  $responce->rows[$i]['id']=1;
          //  $responce->rows[$i]['cell']=array("","","","No Record Founds","", "");
        }
        $responce->userdata['salesmancode'] = $this->translate->_("Total");
        $responce->userdata['no_of_invoice'] = $no_of_invoice;
        $responce->userdata['sale_qty'] = $sale_qty;
        $responce->userdata['free_qty'] = $free_qty;
        $responce->userdata['promo_qty'] = $promo_qty;
        $responce->userdata['return_qty'] = $return_qty;
        $responce->userdata['buyback_qty'] = $buyback_qty;
        $responce->userdata['net_sales'] = $net_sales;
        
        echo json_encode($responce);
        exit;
    }
    
    
    /**
    * @name       exportxls
    * @since      24 Sep, 2012
    * @version    Release: 1
    * @author     Pankil Thakkar <pankil@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This function is used to export excelsheet
    *
    */
    public function exportAction()
    {
        $this->view->params = $params = $this->getRequest()->getParams();
        $this->view->formdata = $formdata = $this->_request->getPost();
        $this->view->css 		= $this->translate->_('CSS');
        $extra_where = "";
        $customertype = "";
        $customercode = "";
        
        if(isset($this->report_session->post['year']) && $this->report_session->post['year'] != "")
        {
            $extra_where  .= " AND YEAR(routestartdate) = ".$this->report_session->post['year'];
        }
        if(isset($this->report_session->post['month']) && $this->report_session->post['month'] != "")
        {
            $extra_where  .= " AND MONTH(routestartdate) = ".$this->report_session->post['month'];
        }
      
        $page = $_GET['page']; // get the requested page
        $limit = $_GET['rows']; // get how many rows we want to have into the grid
        $sidx = $_GET['sidx']; // get index row - i.e. user click to sort
        $sord = $_GET['sord']; // get the direction
        if(!$sidx) $sidx =1;
        
        if(empty($sidx)) {  $sidx  = "YEAR(routestartdate), MONTH(routestartdate), routecode";}
        if(empty($sord)) {  $sord  = "asc";}
    
        $param_array = array();
        $param_array[1] = $extra_where;
        $param_array[2] = $sidx;
        $param_array[3] = $sord;
        $param_array[4] = $limit;
        $param_array[5] = $page;
        
        //pr($param_array);
        $result_arr = $this->SFA_Comman->executequery('CALL sp_report_dataanalysis_kpi_averagedropsize(?,?,?,?,?)',$param_array,'');
        
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
        $data_arr["columns"] = array($this->translate->_('Salesman'),$this->translate->_('No Of Invoices'),$this->translate->_('Sales Qty'),$this->translate->_('Free Qty'),$this->translate->_('Promo Qty'),$this->translate->_('Return Qty'),$this->translate->_('BuyBack Qty'),$this->translate->_('Net Sales'),$this->translate->_('Quantity Drop Size'),$this->translate->_('Value Drop Size'));
        $data_arr["columns_config"] =   array(
                                            array("width"=>35,"toaltext"=>$this->translate->_('Total'),"group_total_text"=>$this->translate->_('Group Total')),
                                            array("width"=>15,"total"=>"1","group_total"=>"1"),
                                            array("width"=>15,"total"=>"1","group_total"=>"1"),
                                            array("width"=>15,"total"=>"1","group_total"=>"1"),
                                            array("width"=>15,"total"=>"1","group_total"=>"1"),
                                            array("width"=>15,"total"=>"1","group_total"=>"1"),
                                            array("width"=>15,"total"=>"1","group_total"=>"1"),
                                            array("width"=>15,"total"=>"1","group_total"=>"1"),
                                            array("width"=>15),
                                            array("width"=>15)
                                        );
        for($i = 0; $i < count($result_arr[0]); $i++)
        {
            $salesmanname = ($this->css == 'ar_') ? $result_arr[0][$i]['arbsalesmanname1'] : $result_arr[0][$i]['salesmanname1'];
            
            $column_model_arr[] = array($result_arr[0][$i]['salesmancode']." - ".$salesmanname,$result_arr[0][$i]['no_of_invoice'],$result_arr[0][$i]['sale_qty'],$result_arr[0][$i]['free_qty'],
                                                $result_arr[0][$i]['promo_qty'], $result_arr[0][$i]['return_qty'],$result_arr[0][$i]['buyback_qty'],
                                                $result_arr[0][$i]['net_sales'],$result_arr[0][$i]['qty_drop_size'],$result_arr[0][$i]['value_drop_size']
                                                );
        }
        
        $data_arr["columns_model"]          = $column_model_arr;
        $data_arr["config"]["report_title"] = $this->translate->_("KPI Average Drop Size").$report_title;
        $data_arr["config"]["report_title_height"] = $report_title_height;
        $data_arr["config"]["file_name"]    = "Average_Drop_Size";
        $data_arr["config"]["group_level"]  = 0;
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
