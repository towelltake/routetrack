<?php
/**
* @name       TabletsalessummaryController
* @since      20-02-2012
* @version    Release: 1
* @author     PM <pankit@elantechnologies.com>
* @copyright  Elan Technologies
* @param
*
* This controller is manage report module.
*/
class Reports_TabletsalessummaryController extends Reports_Library_Controller_Action_Abstract
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
		$this->SFA_Comman	= new SFA_Comman();
		$this->view->colan	= $this->translate->_('Colan');
		$this->css 			= $this->translate->_('CSS');
		$this->view->css	= $this->css;
		
		$this->currentUser = SFA_Loginauth::getIdentity();	
		if(!isset($this->currentUser) || empty($this->currentUser))
		{
		    SFA_Message::setMsg($this->translate->_('Do Login'));
		    //$this->_helper->redirector("index", "index", "home");
			$url = $this->view->baseUrl();
			echo '<script type="text/javascript">window.location="'.$url.'";</script>';
			exit;
		}
		
		$this->sec_lang 		= $this->view->sec_lang;
		$this->decimalplaces  	= $this->view->decimalplaces	= $this->SFA_Comman->getdecimalplaces();
		$this->view->sec_lang	= $this->SFA_Comman->getsecondlanguage();		
		$this->report_session	= new Zend_Session_Namespace('Re_salessummary');
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
    public function salessummaryAction()
    {
	 $this->view->params 	= $params = $this->getRequest()->getParams();
         $this->view->formdata  = $formdata = $this->_request->getPost();
	 
	 $result_arr = $this->SFA_Comman->executequery('CALL sp_report_tablet_salessummary_detail()','','');
	 $this->view->route_list = $result_arr[0];
	
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
        
        $this->view->ReportTitle = $this->translate->_("Sales Summary");
        $this->view->pageHeaderTitle  = $this->translate->_("Create Date") . " :";
        $this->view->pageHeadervalue  =  date("m/d/Y h:i:s");
        $this->view->searchParams  =  array(
                                           array("title"=> "Route",
                                                 "value" => $formdata['ddlroute_selected']),
                                           array("title"=> "Start Date",
                                                 "value" => ($formdata['txt_route_start_date'] != "" ) ? date("d M Y",strtotime($formdata['txt_route_start_date'])) : ""),
                                           array("title"=> "End Date",
                                                 "value" => ($formdata['txt_route_end_date'] != "" ) ? date("d M Y",strtotime($formdata['txt_route_end_date'])) : "")
                                           );
        $this->view->xlsexport_link = "#";
        $this->view->cvsexport_link = "#";

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
      public function salessumAction()
      {
        $this->view->params = $params = $this->getRequest()->getParams();
        $this->view->formdata = $formdata = $this->_request->getPost();
        $this->view->css 		= $this->translate->_('CSS');
        $extra_where = "";
        
        if($this->report_session->post['ddlroute'] != "" )
        {
         
          $extra_where  = " and ih.routecode = ".$this->report_session->post['ddlroute'];
        }
	  
        if($this->report_session->post['txt_route_end_date'] != "" )
        {
            $extra_where  = " and ih.actualtransactiondate between ".$this->report_session->post['txt_route_start_date']." and ".$this->report_session->post['txt_route_end_date'];
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
    
        $result_arr = $this->SFA_Comman->executequery('CALL sp_report_tablet_salessummary(?,?,?,?,?)',$param_array,'');
        
        
        
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
        if(!empty($result_arr[1])){
            foreach($result_arr[1] as $row) {
               // print_r($row);
                $responce->rows[$i]['id']=$i;
				
				$routename 		= ($this->css == 'ar_') ? $row['arbroutename'] 	    : $row['routename'];
                $customername	= ($this->css == 'ar_') ? $row['arbcustomername'] 	: $row['customername'];
				
                $responce->rows[$i]['cell']=array($row['routecode']." - ".$routename,$row['mop'],$row['invoicenumber'],
                              $row['customercode'],$customername, $row['salesamount'], $row['totaldamagedamount'], $row['goodreturnamount'],$row['discountamount'], $row['invoiceamount'], $row['amountpaid'], $row['invoicebalance']);
                $i++;
            }
        }
        else
        {
            //  $responce->rows[$i]['id']=1;
            //  $responce->rows[$i]['cell']=array("","","","No Record Founds","", "");
        }
  
      //$responce->userdata['EmployeeID'] = "2000";
      //$responce->userdata['CustomerID'] = 'Total:';
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
    public function exportpdfAction()
    {
	
	
	$url = "http://localhost/sfa/ver6/reports/accountrouteageing/exportpdf/?_search=false&sidx=routecode&sord=desc";
	
	$reportheading = "googlesite";

	$html2pdf = new SFA_Html2pdf($url,$reportheading);
	
	
	die();
	
	
	
	$this->_helper->layout->setLayout('jqreport');
	$this->view->params 	= $params = $this->getRequest()->getParams();
	$this->view->formdata  = $formdata = $this->_request->getPost();
	
	$this->view->ReportTitle = $this->translate->_("Customer Pending Balance");
	$this->view->pageHeaderTitle  = $this->translate->_("Create Date") . " :";
	$this->view->pageHeadervalue  =  date("m/d/Y h:i:s");
	
	 
	 
	$this->view->params = $params = $this->getRequest()->getParams();
	$this->view->formdata = $formdata = $this->_request->getPost();
	$this->view->css 		= $this->translate->_('CSS');
	   
	  $page = 1; // get the requested page
	  $limit = 10; // get how many rows we want to have into the grid
	  $sidx = $_GET['sidx']; // get index row - i.e. user click to sort
	  $sord = $_GET['sord']; // get the direction
	  if(!$sidx) $sidx =1;
	  
	  if(empty($sidx)) {  $sidx  = "customercode";}
	  if(empty($sord)) {  $sord  = "asc";}
  
	  $param_array = array();
	  $param_array[1] = ' where 1=1 ';
	  $param_array[2] = $sidx;
	  $param_array[3] = $sord;
	  $param_array[4] = '1000';
	  $param_array[5] = $page;
  
	  $result_arr = $this->SFA_Comman->executequery('CALL sp_report_account_route_aging(?,?,?,?,?)',$param_array,'');
	  
	  //echo "<pre>";
	  //print_r($result_arr);
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
	  
	  foreach($result_arr[1] as $row) {
	     // print_r($row);
	      $responce->rows[$i]['id']=$i;
		  
			$customername	= ($this->css == 'ar_') ? $row['arbcustomername'] 	: $row['customername'];
		  
	      $responce->rows[$i]['cell']=array($row['customercode'],$customername,$row['salesname'],$row['invoicenumber'],
						$row['transactiondate'], $row['pending_amount']
						);
	      $i++;
	    
	  }
    }
}
