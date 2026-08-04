<?php

class Api_CronController extends Api_Library_Controller_Action_Abstract
{

    /**
    * @name       init
    * @since      16-03-2012
    * @version    Release: 1
    * @author     Hiren <hiren.d@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    * @url 		  http://192.168.0.146/sfa/ver11/api/cron/averagesalesqty
	*
    * This is the default function for all Actions.
    */
    public function init()
    {
        $this->SFA_Comman = new SFA_Comman();
		parent::init();
    }

     /**
    * @name       averagesalesqtyAction
    * @since      07-11-2013
    * @version    Release: 1
    * @author     Jinal <jinal@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    * @example1 api/cron/averagesalesqty
    * This is the customermaster Action.
    */
    public function averagesalesqtyAction(){
		//echo "test";
        
        //Call stored procedure for get result data
        $result = $this->SFA_Comman->executequery('CALL sp_cron_average_sales_quantity("")','','');
    }
	
	
	public function importsyncAction()
    {			
        $this->view->params = $params = $this->getRequest()->getParams();
        $this->view->formdata = $formdata = $this->_request->getPost();
     
		
        $this->SFA_Comman->ociConnect();
        $this->OracleDBSync = new SFA_DataSyncImport();        
        $Common_NameSpace = new Zend_Session_Namespace('Common');		
		// $this->SFA_Comman->ociConnect();
		$this->OracleDBSync->updateDBFromOracle();
         $this->SFA_Comman->ociClose();
        echo "done";
		exit;
      
    }
	
	public function importloadAction()
    {			
        $this->view->params = $params = $this->getRequest()->getParams();
        $this->view->formdata = $formdata = $this->_request->getPost();   
        $this->SFA_Comman->ociConnect();
        $this->OracleDBSync = new SFA_DataSyncImport();        
        $Common_NameSpace = new Zend_Session_Namespace('Common');		
		// $this->SFA_Comman->ociConnect();
		$this->OracleDBSync->loadFromOracle();
         $this->SFA_Comman->ociClose();
        echo "done";
		exit;
      
    }
	public function exportstockAction()
    {			
        $this->view->params = $params = $this->getRequest()->getParams();
        $this->view->formdata = $formdata = $this->_request->getPost(); 
			
        $this->SFA_Comman->ociConnect();
		
        $this->OracleDBSync = new SFA_DataSyncExport();
			
        $Common_NameSpace = new Zend_Session_Namespace('Common');	
			
		//$this->SFA_Comman->ociConnect();
		$this->OracleDBSync->exportpresentvanstock();
         $this->SFA_Comman->ociClose();
        echo "done";
		exit;      
    }
		
	public function importspecitemAction()
    {			
        $this->view->params = $params = $this->getRequest()->getParams();
        $this->view->formdata = $formdata = $this->_request->getPost();   
        $this->SFA_Comman->ociConnect();
        $this->OracleDBSync = new SFA_DataSyncExport();        
        $Common_NameSpace = new Zend_Session_Namespace('Common');		
		// $this->SFA_Comman->ociConnect();
		$result = $this->SFA_Comman->executequery('CALL int_exp_get_routekey_rpt("")','','');
		 foreach($result[0] as $row) 
		 {
				$routeKeyArr = array($row['routekey']);
				
				$this->OracleDBSync->onlinespecdata($routeKeyArr);
		 }
         $this->SFA_Comman->ociClose();
         echo "done";
		 exit;      
    }
	
	public function pushordersAction()
    {			
        $this->view->params = $params = $this->getRequest()->getParams();
        $this->view->formdata = $formdata = $this->_request->getPost();   
        $this->SFA_Comman->ociConnect();
        $this->OracleDBSync = new SFA_DataSyncExport();        
        $Common_NameSpace = new Zend_Session_Namespace('Common');		
		// $this->SFA_Comman->ociConnect();
		$result = $this->SFA_Comman->executequery('CALL int_exp_get_order_routekey("")','','');
		 foreach($result[0] as $row) 
		 {
				$routeKeyArr = array($row['routekey']);
				
				$this->OracleDBSync->pushonlineorder($routeKeyArr);
		 }
         $this->SFA_Comman->ociClose();
         echo "done";
		 exit;      
    }
	
	public function pushinvoiceAction()
    {			
        $this->view->params = $params = $this->getRequest()->getParams();
        $this->view->formdata = $formdata = $this->_request->getPost();   
        $this->SFA_Comman->ociConnect();
        $this->OracleDBSync = new SFA_DataSyncExport();        
        $Common_NameSpace = new Zend_Session_Namespace('Common');		
		// $this->SFA_Comman->ociConnect();
		$result = $this->SFA_Comman->executequery('CALL int_exp_get_invoice_routekey("")','','');
		 foreach($result[0] as $row) 
		 {
				$routeKeyArr = array($row['routekey']);
				
				$this->OracleDBSync->pushonlineinvoice($routeKeyArr);
		 }
         $this->SFA_Comman->ociClose();
         echo "done";
		 exit;      
    }
	
	public function pusharinventoryAction()
    {			
        $this->view->params = $params = $this->getRequest()->getParams();
        $this->view->formdata = $formdata = $this->_request->getPost();   
        $this->SFA_Comman->ociConnect();
        $this->OracleDBSync = new SFA_DataSyncExport();        
        $Common_NameSpace = new Zend_Session_Namespace('Common');		
		// $this->SFA_Comman->ociConnect();
		$result = $this->SFA_Comman->executequery('CALL int_exp_get_arinventory_routekey("")','','');
		 foreach($result[0] as $row) 
		 {
				$routeKeyArr = array($row['routekey']);
				
				$this->OracleDBSync->pushonlinearinventory($routeKeyArr);
		 }
         $this->SFA_Comman->ociClose();
         echo "done";
		 exit;      
    }
		
	//------------------Tour termination
	public function terminateAction()
    {			
        $this->view->params = $params = $this->getRequest()->getParams();
        $this->view->formdata = $formdata = $this->_request->getPost();
      		
        $tourIdArr = array();
        $process = FALSE;

        $Common_NameSpace = new Zend_Session_Namespace('Common');
        $last_url = htmlspecialchars($_SERVER['HTTP_REFERER']);
        $end_last_url = explode('/',$last_url);
      
            $tourIdArr = "";
            if($params['tourid']!="")
			{				
				$param_array = array();
                $param_array[1] = $params['tourid'];
				$param_array[2] = $params['flag'];
				
				$result = $this->SFA_Comman->executequery('CALL sp_delete_tour(?,?)',$param_array);				
				
				if($result[0][0]['var_status']=='1')
                {
					$rtoken=array('result'=>sucess);
					echo json_encode($rtoken);
					exit;
				}else if($result[0][0]['var_status']=='2')
				{
					$rtoken=array('result'=>on_route);
					echo json_encode($rtoken);
					exit;
				}else if($result[0][0]['var_status']=='3')
				{
					$rtoken=array('result'=>error);
					echo json_encode($rtoken);
					exit;
				}else if($result[0][0]['var_status']=='4')
				{
					$rtoken=array('result'=>exported);
					echo json_encode($rtoken);
					exit;
				}								
			}	
          
        echo "Done";
		exit;      
    }
	
	public function loadreqAction()
    {
       $this->view->params = $params = $this->getRequest()->getParams();
        $this->view->formdata = $formdata = $this->_request->getPost();
     
		
        $this->SFA_Comman->ociConnect();
        $this->OracleDBSync = new SFA_DataSyncExport();
	    $this->OracleDBSync->loadreqexport();
        $this->SFA_Comman->ociClose();
        echo "done";
		exit;
    }
	
	function upsyncorderAction() {
		$this->view->params = $params = $this->getRequest()->getParams();
        $this->view->formdata = $formdata = $this->_request->getPost();
        $param_array = array();
        $param_array[1] = date('Y-m-d');
        $this->Export_Order = new SFA_upSyncOrder();
		 $tourID =$params['auto'];
		{			
            $this->Export_Order->exportOrder($tourID);
        }
		echo "Done";
		exit;
    }
	
	public function syncdivdataAction()
    {			
        $this->view->params = $params = $this->getRequest()->getParams();
        $this->view->formdata = $formdata = $this->_request->getPost();
		
        $Common_NameSpace = new Zend_Session_Namespace('Common');
        $last_url = htmlspecialchars($_SERVER['HTTP_REFERER']);
        $end_last_url = explode('/',$last_url);
        if(end($end_last_url) == 'downsync' || strpos($last_url,'downsync') || strpos($last_url,'/downsync/' )) {
            $sel_date = ($formdata["txtdate"] != '') ? $formdata["txtdate"] : $Common_NameSpace->tdate;
        } else {
            $sel_date = ($formdata["txtdate"] != '') ? $formdata["txtdate"] : date('d-m-Y');
        }
        // ADD DATE VALUE IN SESSION
        if($sel_date != '') {
            $Common_NameSpace->tdate = $sel_date;
            $this->view->date = $sel_date;
        } else {
            $Common_NameSpace->tdate = date('d-m-Y');
            $this->view->date = date('d-m-Y');
        }
        $date = date("Y-m-d",strtotime(str_replace('/', '-', $Common_NameSpace->tdate)));
		
       $this->DownSync_Data = new SFA_syncData();
		
       $this->DownSync_Data->setUserName($this->currentUser->username);
		$valdiv=0;
		//$tourId=22620;		
		$this->DownSync_Data->getSAPDataForBankMaster();
		$this->DownSync_Data->getSAPDataForArticleItem($valdiv);
				
		echo "done";
		exit;
      
    }
	
	public function sendalertAction()
	{
		//------------------------
		$this->view->params = $params = $this->getRequest()->getParams();
        $this->view->formdata = $formdata = $this->_request->getPost();
     
        $process = FALSE;
        $Common_NameSpace = new Zend_Session_Namespace('Common');
		
		$this->SFA_Comman->executequery('CALL sp_set_password("")','','');	
		
		$param_array1 = array();
		$param_array1[1] = "0";		
		
        $result = $this->SFA_Comman->executequery('CALL sp_get_changed_password()','','');
		if($result[0]>0)
		{
					$subject  = 'RoutePro Alert!!!';
					$message  = '';
					$message  = '<html><body>';
					$message .= '<h4>Please Find Below Password Change.</h4>';
					$message .= '<table rules="all" style="border-color: #666;" cellpadding="10">';
					$message .= "<tr style='background: #eee;'>
						<td><strong>Password Type</strong> </td>
						<td><strong>New Password</strong></td>
						<td><strong>Old Password</strong></td>
						<td><strong>Applied Date</strong></td></tr>";
					 foreach($result[0] as $row)
					 {
						$param_array1[1] = $row['idno'];		
						$message .= "<tr>
							<td>".strip_tags($row['passtype'])." </td>
							<td>".strip_tags($row['newpassword'])."</td>
							<td>".strip_tags($row['oldpassword'])."</td>
							<td>".strip_tags($row['changeddate'])."</td></tr>";
					 }
					$message .= "</table>";
					$message .= "</body></html>";
					$headers  = 'From: support@mirnah.com' . "\r\n" .
						'MIME-Version: 1.0' . "\r\n" .
						'Content-type: text/html; charset=utf-8';

					$to = 'enhvancashier@enhanceuae.com,hinshinm@enhanceuae.com,a.nair@mirnah.com,hinshin.sg@gmail.com';
					$success = mail($to, $subject, $message, $headers);
					
					$this->SFA_Comman->executequery('CALL sp_set_password_send_status()',$param_array1);	
		}
		exit;
	}	

	public function posttargetAction()
	{
		//------------------------
		$this->view->params = $params = $this->getRequest()->getParams();
        $this->view->formdata = $formdata = $this->_request->getPost();
     
        $process = FALSE;
        $Common_NameSpace = new Zend_Session_Namespace('Common');
		
		$this->SFA_Comman->executequery('CALL post_suggested_qty("")','','');		
		
		$this->SFA_Comman->executequery('CALL post_target_achievement("")','','');	
		
		/*
		$subject  = 'Suggested Quantity & Achievements Updated.';
		$message  = '';
		$message  = '<html><body>';
		$message .= '<h4>RoutePro Salesman Achievement Updated.</h4>';					
		$message .= "</body></html>";
		$headers  = 'From: routepro.sfa@gmail.com' . "\r\n" .
			'MIME-Version: 1.0' . "\r\n" .
			'Content-type: text/html; charset=utf-8';
		
		$to       = 'a.nair@mirnah.com';
		$success = mail($to, $subject, $message, $headers);	        
		*/
		exit;
	}	
}