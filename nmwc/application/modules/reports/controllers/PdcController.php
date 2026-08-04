<?php
/**
* @name       PdcController
* @since      03-10-2012
* @version    Release: 1
* @author     PT <pankil@elantechnologies.com>
* @copyright  Elan Technologies
* @param
*
* This controller is manage report module.
*/
class Reports_PdcController extends Reports_Library_Controller_Action_Abstract
{
    /**
    * @name       init
    * @since      03-10-2012
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
        
        $this->report_session = new Zend_Session_Namespace('Re_pdc');
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
    * @name       getAction
    * @since      03-10-2012
    * @version    Release: 1
    * @author     PT <pankil@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This action is for display daily sales sheet report
    *
    */
    public function pdcAction()
    {
        $this->view->params 	= $params = $this->getRequest()->getParams();
        $this->view->formdata  = $formdata = $this->_request->getPost();
		
        $this->report_session->post = array();
	}

    /**
    * @name       totalsalesbyhierarchyAction
    * @since      3-10-2012
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
        
        $this->view->ReportTitle = $this->translate->_("PDC");
        $this->view->pageHeaderTitle  = $this->translate->_('Date');
        $this->view->pageHeadervalue  =  date("m/d/Y h:i:s");
        $this->view->searchParams  =  array(
                                            array("title"=> "Customer Type",
                                                  "value" => $formdata['ddlcustomertype_selected']),
                                            array("title"=> "Customer",
                                                  "value" => $formdata['ddlcustomer_selected'])
                                            );
        $this->report_session->searchParams = $this->view->searchParams;
        $this->view->xlsexport_link = $this->view->baseUrl()."/reports/pdc/export";
        $this->view->cvsexport_link = $this->view->baseUrl()."/reports/pdc/exportcsv";
    }
    /**
      * @name       pdcdataAction
      * @since      3-10-2012
      * @version    Release: 1
      * @author     PT <pankil@elantechnologies.com>
      * @copyright  Elan Technologies
      * @param
      *
      * This action fetch customer pending request data
      *
      */
    public function pdcdataAction()
    {
        $this->view->params = $params = $this->getRequest()->getParams();
        $this->view->formdata = $formdata = $this->_request->getPost();
        $this->view->css 		= $this->translate->_('CSS');
        $extra_where = "";
        $customertype = "";
        $customercode = "";
        
        if($this->report_session->post['ddlcustomer'] > 0)
        {
            if($this->report_session->post['ddlcustomertype'] == 3)
                $extra_where  .= " and cm.headofficecode = ".$this->report_session->post['ddlcustomer'];
            else
                $extra_where .= " and cm.customercode = ".$this->report_session->post['ddlcustomer'];
        }
        if($this->report_session->post['ddlcustomertype'] == 3)
            $extra_where  .= " and cm.type IN (2,3) ";
        else
            $extra_where .= " and cm.type IN (". $this->report_session->post['ddlcustomertype'] . ")";
      
        $page = $_GET['page']; // get the requested page
        $limit = $_GET['rows']; // get how many rows we want to have into the grid
        $sidx = $_GET['sidx']; // get index row - i.e. user click to sort
        $sord = $_GET['sord']; // get the direction
        if(!$sidx) $sidx =1;
        
        if(empty($sidx)) {  $sidx  = "cm.customercode";}
        if(empty($sord)) {  $sord  = "asc";}
    
        $param_array = array();
        $param_array[1] = ' WHERE 1=1 AND cm.customercode = ci.customercode AND rm.routecode = ci.routecode AND sm.salesmancode = ci.salesmancode '.$extra_where;
        $param_array[2] = $sidx;
        $param_array[3] = $sord;
        $param_array[4] = $limit;
        $param_array[5] = $page;
        
        $result_arr = $this->SFA_Comman->executequery('CALL sp_report_account_pdc(?,?,?,?,?)',$param_array,'');
        
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
        $totalsalesamount = $totalreturnamount = $totaldamagedamount = $totalexpiryamount = $totalfreesampleamount = 0;
        if(!empty($result_arr[1])){
            foreach($result_arr[1] as $row) {
                $routename = ($this->css == 'ar_') ? $row['arbroutename'] : $row['routename'];
                $customername = ($this->css == 'ar_') ? $row['arbcustomername'] : $row['customername'];
                $salesman = ($this->css == 'ar_') ? $row['arbsalesmanname1'] : $row['salesmanname1'];
                
                $total_pdc_bal += $row['pdcbalance'];
                $responce->rows[$i]['id'] = $i;
                $responce->rows[$i]['cell'] = array($row['routecode'].' - '.$routename,$row['salesmancode'].' - '.$salesman,
                                                    $row['hocode'].' - '.$row['honame'],$row['transactiondate'], $row['invoicenumber'],$row['customercode'],
                                                    $customername,$row['creditlimitdays'],$row['pdcbalance'], $row['pdcdate']
                                                );
                $i++;
            }
        }
        else
        {
          //  $responce->rows[$i]['id']=1;
          //  $responce->rows[$i]['cell']=array("","","","No Record Founds","", "");
        }
        
        $responce->userdata['pdcbalance'] = $total_pdc_bal;
        
        $responce->userdata['creditlimitdays'] = 'Total';
        
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
        
        if($this->report_session->post['ddlcustomer'] > 0)
        {
            if($this->report_session->post['ddlcustomertype'] == 3)
                $extra_where  .= " and cm.headofficecode = ".$this->report_session->post['ddlcustomer'];
            else
                $extra_where .= " and cm.customercode = ".$this->report_session->post['ddlcustomer'];
        }
        if($this->report_session->post['ddlcustomertype'] == 3)
            $extra_where  .= " and cm.type IN (2,3) ";
        else
            $extra_where .= " and cm.type IN (". $this->report_session->post['ddlcustomertype'] . ")";
      
        $page = $_GET['page']; // get the requested page
        $limit = $_GET['rows']; // get how many rows we want to have into the grid
        $sidx = $_GET['sidx']; // get index row - i.e. user click to sort
        $sord = $_GET['sord']; // get the direction
        if(!$sidx) $sidx =1;
        
        if(empty($sidx)) {  $sidx  = "cm.customercode";}
        if(empty($sord)) {  $sord  = "asc";}
    
        $param_array = array();
        $param_array[1] = ' WHERE 1=1 AND cm.customercode = ci.customercode AND rm.routecode = ci.routecode AND sm.salesmancode = ci.salesmancode '.$extra_where;
        $param_array[2] = "routecode,salesmancode,hocode,".$sidx;
        $param_array[3] = $sord;
        $param_array[4] = $limit;
        $param_array[5] = $page;
        
        $result_arr = $this->SFA_Comman->executequery('CALL sp_report_account_pdc(?,?,?,?,?)',$param_array,'');
        
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
        $data_arr["columns"] = array($this->translate->_('Route'),$this->translate->_('Salesman'),$this->translate->_('HOCode'),$this->translate->_('Transaction Date'),$this->translate->_('Invoice Number'),$this->translate->_('Customer Code'),$this->translate->_('Customer Name'),$this->translate->_('Credit Days'),$this->translate->_('PDC Amount'),$this->translate->_('PDC Date'));
        $data_arr["columns_config"] =   array(
                                            array("width"=>15),
                                            array("width"=>15),
                                            array("width"=>15),
                                            array("width"=>13),
                                            array("width"=>15),
                                            array("width"=>15),
                                            array("width"=>35),
                                            array("width"=>13,"toaltext"=>$this->translate->_('Total'),"group_total_text"=>$this->translate->_('Group Total')),
                                            array("width"=>15,"total"=>"1","group_total"=>"1"),
                                            array("width"=>15)
                                        );
        for($i = 0; $i < count($result_arr[0]); $i++)
        {
            $routename = ($this->css == 'ar_') ? $result_arr[0][$i]['arbroutename'] : $result_arr[0][$i]['routename'];
            $customername = ($this->css == 'ar_') ? $result_arr[0][$i]['arbcustomername'] : $result_arr[0][$i]['customername'];
            $salesman = ($this->css == 'ar_') ? $result_arr[0][$i]['arbsalesmanname1'] : $result_arr[0][$i]['salesmanname1'];
            
            $column_model_arr[$result_arr[0][$i]['routecode'].' - '.$routename][$result_arr[0][$i]['salesmancode'].' - '.$salesman][$result_arr[0][$i]['hocode'].' - '.$result_arr[0][$i]['honame']][] =
                                  array($result_arr[0][$i]['transactiondate'], $result_arr[0][$i]['invoicenumber'],$result_arr[0][$i]['customercode'],
                                        $customername,$result_arr[0][$i]['creditlimitdays'],$result_arr[0][$i]['pdcbalance'], $result_arr[0][$i]['pdcdate']
                                    );
        }
        
        $data_arr["columns_model"]          = $column_model_arr;
        $data_arr["config"]["report_title"] = $this->translate->_("PDC").$report_title;
        $data_arr["config"]["report_title_height"] = $report_title_height;
        $data_arr["config"]["file_name"]    = "PDC";
        $data_arr["config"]["group_level"]  = 3;
        $data_arr["config"]["total_columns"]= count($data_arr["columns"]);
        $data_arr["config"]["group_total"]  = "1";
        $data_arr["config"]["main_total"]   = "1";
        
        
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
		
        $result_arr = $this->SFA_Comman->executequery('CALL sp_report_account_pdc_detail(?)',$params['cust_type'],'');
        echo Zend_Json::encode($result_arr);
		exit;
	}
    
}
