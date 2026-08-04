<?php
/**
* @name       SalesmanproductivityController
* @since      15-10-2012
* @version    Release: 1
* @author     PT <pankil@elantechnologies.com>
* @copyright  Elan Technologies
* @param
*
* This controller is manage report module.
*/
class Reports_SalesmanproductivityController extends Reports_Library_Controller_Action_Abstract
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
        
        $this->report_session = new Zend_Session_Namespace('Re_salesmanproductivity');
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
    * @name       salesmanproductivityAction
    * @since      15-10-2012
    * @version    Release: 1
    * @author     PT <pankil@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This action is for display daily sales sheet report
    *
    */
    public function salesmanproductivityAction()
    {
        
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
	 
        //$this->report_session->post = $formdata;
        
        $this->view->ReportTitle = $this->translate->_("Salesman Productivity");
        $this->view->pageHeaderTitle  = $this->translate->_('Date');
        $this->view->pageHeadervalue  =  date("m/d/Y h:i:s");
        
        $param_array = array();
        $param_array[1] = 1;
        $param_array[2] = '';
        $param_array[3] = '';
        $param_array[4] = '';
        $param_array[5] = '';
        $result_arr = $this->SFA_Comman->executequery('CALL sp_report_dataanalysis_kpi_sales_productivity(?,?,?,?,?)',$param_array,'');
        
        $column = array($this->translate->_('Code'),$this->translate->_('Salesman'));
        $column_config = array('salesmancode','salesmanname1');
        $sent_column = array();
        for($i=0;$i<count($result_arr[0]);$i++)
        {
            $column[] = $result_arr[0][$i]["yearno"];
            $column_config[] = $result_arr[0][$i]["yearno"];
            $sent_column[] = $result_arr[0][$i]["yearno"];
        }
        $this->view->column = $column;
        $this->view->column_config = $column_config;
        $this->report_session->column = $sent_column;
        $this->report_session->column_config = $column;
        
        $this->view->xlsexport_link = $this->view->baseUrl()."/reports/salesmanproductivity/export";
        $this->view->cvsexport_link = $this->view->baseUrl()."/reports/salesmanproductivity/exportcsv";
    }
    /**
      * @name       salesmanproductivitydataAction
      * @since      15-10-2012
      * @version    Release: 1
      * @author     PT <pankil@elantechnologies.com>
      * @copyright  Elan Technologies
      * @param
      *
      * This action fetch customer pending request data
      *
      */
    public function salesmanproductivitydataAction()
    {
        $this->view->params = $params = $this->getRequest()->getParams();
        $this->view->formdata = $formdata = $this->_request->getPost();
        $this->view->css 		= $this->translate->_('CSS');
        $extra_where = "";
        $customertype = "";
        $customercode = "";
        
        $page = $_GET['page']; // get the requested page
        $limit = $_GET['rows']; // get how many rows we want to have into the grid
        $sidx = $_GET['sidx']; // get index row - i.e. user click to sort
        $sord = $_GET['sord']; // get the direction
        if(!$sidx) $sidx =1;
        
        if(empty($sidx)) {  $sidx  = "salesmancode";}
        if(empty($sord)) {  $sord  = "asc";}
        
        $param_array = array();
        $param_array[1] = $extra_where;
        $param_array[2] = $sidx;
        $param_array[3] = $sord;
        $param_array[4] = $limit;
        $param_array[5] = $page;
        
        $result_arr = $this->SFA_Comman->executequery('CALL sp_report_dataanalysis_kpi_sales_productivity(?,?,?,?,?)',$param_array,'');
        
        $count  = $result_arr[0][0]['counter'];
        if( $count >0 ) {
            $total_pages = ceil($count/$limit);
        } else {
            $total_pages = 0;
        }
        if ($page > $total_pages) $page = $total_pages;
        $start = $limit * $page - $limit; // do not put $limit*($page - 1)
    
        $responce->page = $page;
        $responce->total = $total_pages;
        $responce->records = $count;
        $i=0;
        $total_investment = array();
        if(!empty($result_arr[1])) {
            $newdata = array();
            foreach($result_arr[1] as $row) {
                $salesmanname = ($this->css == 'ar_') ? $row['arbsalesmanname1'] : $row['salesmanname1'];
                $newdata[$row["salesmancode"]]["salesman"] = $salesmanname;
                $newdata[$row["salesmancode"]][$row["yearno"]] = $row["salesamount"];
                $total_investment[$row["yearno"]] = (isset($total_investment[$row["yearno"]]) && $total_investment[$row["yearno"]] != "") ? $total_investment[$row["yearno"]] + $row["salesamount"] : $row["salesamount"];
            }
            
            foreach($newdata as $key=>$row)
            {
                $columnarr = array();
                $columnarr = array($key,$row["salesman"]);
                for($j=0;$j<count($this->report_session->column);$j++)
                {
                    if(isset($row[$this->report_session->column[$j]]) && $row[$this->report_session->column[$j]] != "") {
                        $columnarr[] = $row[$this->report_session->column[$j]];
                    } else {
                        $columnarr[] = 0;
                    }
                }
                $responce->rows[$i]['id'] = $i;
                $responce->rows[$i]['cell'] = $columnarr;
                $i++;
            }
        }
        else
        {
          //  $responce->rows[$i]['id']=1;
          //  $responce->rows[$i]['cell']=array("","","","No Record Founds","", "");
        }
        
        if(count($total_investment) > 0) {
            foreach($total_investment as $key => $val) {
                $responce->userdata[$key] = $val;
            }
        }
        $responce->userdata['salesmanname1'] = $this->translate->_("Total");
        echo json_encode($responce);
        exit;
    }
    
    /**
      * @name       salesmanproductivitydataAction
      * @since      15-10-2012
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
        $customertype = "";
        $customercode = "";
        
        $page = $_GET['page']; // get the requested page
        $limit = $_GET['rows']; // get how many rows we want to have into the grid
        $sidx = $_GET['sidx']; // get index row - i.e. user click to sort
        $sord = $_GET['sord']; // get the direction
        if(!$sidx) $sidx =1;
        
        if(empty($sidx)) {  $sidx  = "salesmancode";}
        if(empty($sord)) {  $sord  = "asc";}
        
        $param_array = array();
        $param_array[1] = $extra_where;
        $param_array[2] = $sidx;
        $param_array[3] = $sord;
        $param_array[4] = $limit;
        $param_array[5] = $page;
        
        $result_arr = $this->SFA_Comman->executequery('CALL sp_report_dataanalysis_kpi_sales_productivity(?,?,?,?,?)',$param_array,'');
        $column_model_arr = array();
        $newdata = array();
        $report_title_height = 15;
        
        if(count($result_arr[0]) > 0) {
            foreach($result_arr[0] as $row) {
                $salesmanname = ($this->css == 'ar_') ? $row['arbsalesmanname1'] : $row['salesmanname1'];
                
                $newdata[$row["salesmancode"]]["salesman"] = $salesmanname;
                $newdata[$row["salesmancode"]][$row["yearno"]] = $row["salesamount"];
            }
            
            foreach($newdata as $key=>$row)
            {
                $columnarr = array();
                $columnarr = array($key,$row["salesman"]);
                for($j=0;$j<count($this->report_session->column);$j++)
                {
                    if(isset($row[$this->report_session->column[$j]]) && $row[$this->report_session->column[$j]] != "") {
                        $columnarr[] = $row[$this->report_session->column[$j]];
                    } else {
                        $columnarr[] = 0;
                    }
                }
                $column_model_arr[] = $columnarr;
            }
        }
        
        $data_arr["columns"] = $this->report_session->column_config;
        $data_arr["columns_config"] =   array(
                                            array("width"=>12),
                                            array("width"=>35,"toaltext"=>$this->translate->_('Total'))
                                        );
        
        for($j=0;$j<count($this->report_session->column);$j++)
        {
            $data_arr["columns_config"][] = array("width"=>15,"total"=>"1");
        }
        
        $data_arr["columns_model"]          = $column_model_arr;
        $data_arr["config"]["report_title"] = $this->translate->_("Salesman Productivity");
        $data_arr["config"]["report_title_height"] = $report_title_height;
        $data_arr["config"]["file_name"]    = "Salesman_Productivity";
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
