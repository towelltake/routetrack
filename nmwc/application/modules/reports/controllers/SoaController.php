<?php
/**
* @name       SoaController
* @since      03-10-2012
* @version    Release: 1
* @author     PT <pankil@elantechnologies.com>
* @copyright  Elan Technologies
* @param
*
* This controller is manage report module.
*/
class Reports_SoaController extends Reports_Library_Controller_Action_Abstract
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
        
        $this->report_session = new Zend_Session_Namespace('Re_soa');
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
    * @name       soaAction
    * @since      03-10-2012
    * @version    Release: 1
    * @author     PT <pankil@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This action is for display daily sales sheet report
    *
    */
    public function soaAction()
    {
        $this->view->params 	= $params = $this->getRequest()->getParams();
        $this->view->formdata  = $formdata = $this->_request->getPost();
        
        $result_arr = $this->SFA_Comman->executequery('CALL sp_report_account_soa_detail()','','');
        
        $this->view->cusotomer_list = $result_arr[0];
        $this->report_session->post = array();
   }

    /**
    * @name       indexAction
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
        
        $this->view->ReportTitle = $this->translate->_("Statement Of Account");
        $this->view->pageHeaderTitle  = $this->translate->_('Date');
        $this->view->pageHeadervalue  =  date("m/d/Y h:i:s");
        $this->view->searchParams  =  array(
                                            array("title"=> "Customer",
                                                  "value" => $formdata['ddlcustomer_selected']),
                                            array("title"=> "From Date",
                                                  "value" => ($formdata['txt_route_start_date'] != "" ) ? date("d M Y",strtotime($formdata['txt_route_start_date'])) : ""),
                                            array("title"=> "To Date",
                                                  "value" => ($formdata['txt_route_end_date'] != "" ) ? date("d M Y",strtotime($formdata['txt_route_end_date'])) : "")
                                            );
        $this->report_session->searchParams = $this->view->searchParams;
        
        $this->view->xlsexport_link = $this->view->baseUrl()."/reports/soa/export";
        $this->view->cvsexport_link = $this->view->baseUrl()."/reports/soa/exportcsv";
    }
    /**
      * @name       soadataAction
      * @since      3-10-2012
      * @version    Release: 1
      * @author     PT <pankil@elantechnologies.com>
      * @copyright  Elan Technologies
      * @param
      *
      * This action fetch customer pending request data
      *
      */
    public function soadataAction()
    {
        $this->view->params = $params = $this->getRequest()->getParams();
        $this->view->formdata = $formdata = $this->_request->getPost();
        $this->view->css 		= $this->translate->_('CSS');
        
        $param_array[1] = ($this->report_session->post['ddlcustomer'] ) ? $this->report_session->post['ddlcustomer']  : "0";
        $param_array[2] = ($this->report_session->post['txt_route_start_date']) ? date('Y-m-d',strtotime($this->report_session->post['txt_route_start_date']))  : "";
        $param_array[3] = ($this->report_session->post['txt_route_end_date']) ? date('Y-m-d',strtotime($this->report_session->post['txt_route_end_date']))  : "";
        
        $result_arr = $this->SFA_Comman->executequery('CALL sp_report_account_soa(?,?,?)',$param_array,'');
        
        $i=0;
        if(!empty($result_arr[0])){
            foreach($result_arr[0] as $row) {
                $customername = ($this->css == 'ar_') ? $row['arbcustomername'] : $row['customername'];
                
                $responce->rows[$i]['id'] = $i;
                $date = ($row['transactiondate'] != '0000-00-00 00:00:00')? date('d M Y',strtotime($row['transactiondate'])):"";
                $responce->rows[$i]['cell'] = array($row['customercode'].' - '.$customername,$row['detailtype'],$row['detailtext'],$date, $row['amount']);
                $i++;
            }
        }
        else
        {
            //  $responce->rows[$i]['id']=1;
            //  $responce->rows[$i]['cell']=array("","","","No Record Founds","", "");
        }
        
        echo json_encode($responce);
        exit;
    }
    
    
     /**
      * @name       exportxlsAction
      * @since      5-10-2012
      * @version    Release: 1
      * @author     PT <pankil@elantechnologies.com>
      * @copyright  Elan Technologies
      * @param
      *
      * This action for export to xls
      *
      */
    public function exportAction()
    {
        $this->view->params = $params = $this->getRequest()->getParams();
        $this->view->formdata = $formdata = $this->_request->getPost();
        $this->view->css 		= $this->translate->_('CSS');
        
        $param_array[1] = ($this->report_session->post['ddlcustomer'] ) ? $this->report_session->post['ddlcustomer']  : "0";
        $param_array[2] = ($this->report_session->post['txt_route_start_date']) ? date('Y-m-d',strtotime($this->report_session->post['txt_route_start_date']))  : "";
        $param_array[3] = ($this->report_session->post['txt_route_end_date']) ? date('Y-m-d',strtotime($this->report_session->post['txt_route_end_date']))  : "";
        
        $result_arr = $this->SFA_Comman->executequery('CALL sp_report_account_soa(?,?,?)',$param_array,'');
        
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
        $data_arr["columns"] = array($this->translate->_('Customer Code'),$this->translate->_('Detail Type'),$this->translate->_('Detail Text'),$this->translate->_('Transaction Date'),$this->translate->_('Amount'));
        $data_arr["columns_config"] =   array(
                                            array("width"=>12),
                                            array("width"=>15),
                                            array("width"=>35),
                                            array("width"=>15,"toaltext"=>$this->translate->_('Total'),"group_total_text"=>$this->translate->_('Group Total')),
                                            array("width"=>15,"total"=>"1","group_total"=>"1")
                                        );
        for($i = 0; $i < count($result_arr[0]); $i++)
        {
            $customername = ($this->css == 'ar_') ? $result_arr[0][$i]['arbcustomername'] : $result_arr[0][$i]['customername'];
            $date = ($result_arr[0][$i]['transactiondate'] != '0000-00-00 00:00:00')? date('d M Y',strtotime($result_arr[0][$i]['transactiondate'])):"";
            $column_model_arr[$result_arr[0][$i]['customercode'].' - '.$customername][] = array($result_arr[0][$i]['detailtype'],$result_arr[0][$i]['detailtext'],
                                                                                    $date, $result_arr[0][$i]['amount']
                                                                                  );
        }
        
        $data_arr["columns_model"]          = $column_model_arr;
        $data_arr["config"]["report_title"] = $this->translate->_("Statement Of Account").$report_title;
        $data_arr["config"]["report_title_height"] = $report_title_height;
        $data_arr["config"]["file_name"]    = "SOA";
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
