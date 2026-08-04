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
class Reports_DatamonthlyrevenueController extends Reports_Library_Controller_Action_Abstract
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
        
        $this->report_session		= new Zend_Session_Namespace('Re_monthlyrevenue');
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
    public function datamonthlyrevenueAction()
    {
        $this->view->params 	= $params = $this->getRequest()->getParams();
        $this->view->formdata  = $formdata = $this->_request->getPost();
		$param_checkarray 	= array();			
		$param_checkarray[1]	= "DATA";
		$result_arr = $this->SFA_Comman->executequery('CALL sp_get_year()',$param_checkarray,'');	
        $this->view->curyear =$result_arr[0];
       // $this->view->curyear =date('Y');
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
        
        $this->view->ReportTitle = $this->translate->_("Route Monthly Revenue");
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
                                            array("title" => "Year",
                                                  "value" => $formdata['ddlyear'])
                                            );
        $this->report_session->searchParams = $this->view->searchParams;
        
        $this->view->xlsexport_link = $this->view->baseUrl()."/reports/datamonthlyrevenue/export";
        $this->view->cvsexport_link = $this->view->baseUrl()."/reports/datamonthlyrevenue/exportcsv";


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
    public function datamonthlyrevenuedataAction()
    {
        $this->view->params = $params = $this->getRequest()->getParams();
        $this->view->formdata = $formdata = $this->_request->getPost();
        $this->view->css 		= $this->translate->_('CSS');
        $extra_where = "";
        
        
        if($this->report_session->post['ddlyear'] != "" )
        {
            $extra_where  = ' AND DATE_FORMAT(ih.transactiondate,"%Y") = '.$this->report_session->post['ddlyear'];	    
        }
        if(isset($this->report_session->routecode_str) && $this->report_session->routecode_str != "")
        {
            $extra_where .= ' AND ih.routecode IN ('.$this->report_session->routecode_str.')';
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
       
        // pr($param_array);
        $result_arr = $this->SFA_Comman->executequery('CALL sp_report_data_monthly_revenue(?,?,?,?,?)',$param_array,'');
        
        $count  = $result_arr[0][0]['counter'];
        // $count  = !empty($result_arr[0]) ? count($result_arr[0]) : 0;
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
        $newarr = array();
        
        foreach($result_arr[1] as $row) {
            $routename = ($this->css == 'ar_') ? $row['arbroutename'] : $row['routename'];
            $newarr[$row['routecode']]["routename"] = $routename;
            $newarr[$row['routecode']][$row['nomonth']] = $row['salesamt'];
            
        }
        
        $jan_total = $feb_total = $mar_total = $apr_total = $may_total = $jun_total = $jul_total = $aug_total = $sep_total = $oct_total = $nov_total = $dec_total = $final_total = 0;
        
        if(!empty($newarr)){
            foreach($newarr as $key => $row) {
                
                $jan = (isset($row['01'])) ? $row['01'] : 0; $jan_total += $jan;
                $feb = (isset($row['02'])) ? $row['02'] : 0; $feb_total += $feb;
                $mar = (isset($row['03'])) ? $row['03'] : 0; $mar_total += $mar;
                $apr = (isset($row['04'])) ? $row['04'] : 0; $apr_total += $apr;
                $may = (isset($row['05'])) ? $row['05'] : 0; $may_total += $may;
                $jun = (isset($row['06'])) ? $row['06'] : 0; $jun_total += $jun;
                $jul = (isset($row['07'])) ? $row['07'] : 0; $jul_total += $jul;
                $aug = (isset($row['08'])) ? $row['08'] : 0; $aug_total += $aug;
                $sep = (isset($row['09'])) ? $row['09'] : 0; $sep_total += $sep;
                $oct = (isset($row['10'])) ? $row['10'] : 0; $oct_total += $oct;
                $nov = (isset($row['11'])) ? $row['11'] : 0; $nov_total += $nov;
                $dec = (isset($row['12'])) ? $row['12'] : 0; $dec_total += $dec;
                $total = $jan + $feb + $mar + $apr + $may + $jun + $jul + $aug + $sep + $oct + $nov + $dec;
                $final_total += $total;
                $responce->rows[$i]['id']=$i;
                $responce->rows[$i]['cell'] = array($key,$row['routename'],$jan,$feb,$mar,$apr,$may,$jun,$jul,$aug,$sep,$oct,$nov,$dec,$total);
                $i++;
            }
        }
        else
        {
        //  $responce->rows[$i]['id']=1;
          //  $responce->rows[$i]['cell']=array("","","","No Record Founds","", "");
        }
    
        //$responce->userdata['EmployeeID'] = "2000";
        $responce->userdata['january'] = $jan_total;
        $responce->userdata['february'] = $feb_total;
        $responce->userdata['march'] = $mar_total;
        $responce->userdata['april'] = $apr_total;
        $responce->userdata['may'] = $may_total;
        $responce->userdata['june'] = $jun_total;
        $responce->userdata['july'] = $jul_total;
        $responce->userdata['august'] = $aug_total;
        $responce->userdata['september'] = $sep_total;
        $responce->userdata['october'] = $oct_total;
        $responce->userdata['november'] = $nov_total;
        $responce->userdata['december'] = $dec_total;
        $responce->userdata['total'] = $final_total;
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
      * This action fetch customer pending request data
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
            $extra_where  = ' AND DATE_FORMAT(ih.transactiondate,"%Y") = '.$this->report_session->post['ddlyear'];
        }
        if(isset($this->report_session->routecode_str) && $this->report_session->routecode_str != "")
        {
            $extra_where .= ' AND ih.routecode IN ('.$this->report_session->routecode_str.')';
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
       
        // pr($param_array);
        $result_arr = $this->SFA_Comman->executequery('CALL sp_report_data_monthly_revenue(?,?,?,?,?)',$param_array,'');
        
        foreach($result_arr[1] as $row) {
            $routename = ($this->css == 'ar_') ? $row['arbroutename'] : $row['routename'];
            $newarr[$row['routecode']]["routename"] = $routename;
            $newarr[$row['routecode']][$row['nomonth']] = $row['salesamt'];
        }
        
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
        $data_arr["columns"] = array($this->translate->_('Route Code'),$this->translate->_('Route Name'),$this->translate->_('January'),$this->translate->_('February'),$this->translate->_('March'),$this->translate->_('April'),$this->translate->_('May'),$this->translate->_('June'),$this->translate->_('July'),$this->translate->_('August'),$this->translate->_('September'),$this->translate->_('October'),$this->translate->_('November'),$this->translate->_('December'),$this->translate->_('Total'));
        $data_arr["columns_config"] =   array(
                                            array("width"=>12),
                                            array("width"=>35,"toaltext"=>$this->translate->_('Total')),
                                            array("width"=>15,"total"=>"1","group_total"=>"1"),
                                            array("width"=>15,"total"=>"1","group_total"=>"1"),
                                            array("width"=>15,"total"=>"1","group_total"=>"1"),
                                            array("width"=>15,"total"=>"1","group_total"=>"1"),
                                            array("width"=>15,"total"=>"1","group_total"=>"1"),
                                            array("width"=>15,"total"=>"1","group_total"=>"1"),
                                            array("width"=>15,"total"=>"1","group_total"=>"1"),
                                            array("width"=>15,"total"=>"1","group_total"=>"1"),
                                            array("width"=>15,"total"=>"1","group_total"=>"1"),
                                            array("width"=>15,"total"=>"1","group_total"=>"1"),
                                            array("width"=>15,"total"=>"1","group_total"=>"1"),
                                            array("width"=>15,"total"=>"1","group_total"=>"1"),
                                            array("width"=>15,"total"=>"1","group_total"=>"1")
                                        );
        if(!empty($newarr)){
            foreach($newarr as $key => $row) {
                $jan = (isset($row['01'])) ? $row['01'] : 0;
                $feb = (isset($row['02'])) ? $row['02'] : 0;
                $mar = (isset($row['03'])) ? $row['03'] : 0;
                $apr = (isset($row['04'])) ? $row['04'] : 0;
                $may = (isset($row['05'])) ? $row['05'] : 0;
                $jun = (isset($row['06'])) ? $row['06'] : 0;
                $jul = (isset($row['07'])) ? $row['07'] : 0;
                $aug = (isset($row['08'])) ? $row['08'] : 0;
                $sep = (isset($row['09'])) ? $row['09'] : 0;
                $oct = (isset($row['10'])) ? $row['10'] : 0;
                $nov = (isset($row['11'])) ? $row['11'] : 0;
                $dec = (isset($row['12'])) ? $row['12'] : 0;
                $total = $jan + $feb + $mar + $apr + $may + $jun + $jul + $aug + $sep + $oct + $nov + $dec;
                $column_model_arr[] = array($key,$row['routename'],$jan,$feb,$mar,$apr,$may,$jun,$jul,$aug,$sep,$oct,$nov,$dec,$total);
            }
        }
        
        $data_arr["columns_model"]          = $column_model_arr;
        $data_arr["config"]["report_title"] = $this->translate->_("Route Monthly Revenue").$report_title;
        $data_arr["config"]["report_title_height"] = $report_title_height;
        $data_arr["config"]["file_name"]    = "Route_Monthly_Revenue";
        $data_arr["config"]["group_level"]  = 0;
        $data_arr["config"]["total_columns"]= count($data_arr["columns"]);
        $data_arr["config"]["group_total"]  = "0";
        $data_arr["config"]["main_total"]   = "1";
        
        
        $SFA_Exportxls = new SFA_Exportxls($data_arr);
        $objPHPExcel = $SFA_Exportxls->exportxls();
        $objWriter = PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel5');
        $objWriter->save('php://output');
        exit;
    }
}
