<?php

header('Access-Control-Allow-Origin: *');
class Api_WsController extends Api_Library_Controller_Action_Abstract
{

    //Initilize var for App Model
    private $index = "";

    /**
    * @name       init
    * @since      16-03-2012
    * @version    Release: 1
    * @author     PM <pankit@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This is the default function for all Actions.
    *
    */
    public function init()
    {
        $this->SFA_Comman = new SFA_Comman();
	parent::init();
    }

  
  public function senddataAction(){
        
	
	$reqval= $this->getRequest()->getParams();    
    $resultreturn = array();
	
	if(!is_null($this->_getParam('startday')))
    {
   	$pararminvoice=$this->_getParam('startday');
    $params = json_decode($pararminvoice,true);

	$ar=array();
	if(count($params)>0)
	{
	    for($i=0;$i<count($params);$i++)
	    {
			$param_array 	= array();
			$param_array[1]	= $params[$i]['routecode'];
			$param_array[2]	= $params[$i]['salesmancode'];
			$param_array[3]	= $params[$i]['routestartodometer'];
			$param_array[4]	= $params[$i]['deviceid'];
			$param_array[5]	= $params[$i]['ver'];
			
			$resultdata = $this->SFA_Comman->executequery('CALL sp_ws_stratendday(?,?,?,?,?)',$param_array,'');
		
			if($resultdata[0][0]['status'] == 1){
				$arr[]=array("status"=>1);
			}
			elseif(count($resultdata)>0) {
				$arr[]=array("status"=>0,"routekey"=>$resultdata[0][0]['routekey'],"routestartdate"=>$resultdata[0][0]['routestartdate'],"routestarttime"=>$resultdata[0][0]['routestarttime'],"routestartodometer"=>$resultdata[0][0]['routestartodometer']);
			}	    
	    }
	 
	    $resultreturn['startday'] = $arr;
	}else
	    {
		 $resultreturn['startday'] = array();
	    }
	    
    }
	
	echo json_encode($resultreturn);
	exit;
    }
    
    //For end day
    public function enddayAction()
    {
	
		$reqval= $this->getRequest()->getParams();
		$resultreturn = array();
		if(!is_null($this->_getParam('endday')))
	    {
		$pararminvoice=$this->_getParam('endday');	
		$params = json_decode($pararminvoice,true);
		$ar=array();
		if(count($params)>0)
		{
		    for($i=0;$i<count($params);$i++)
		    {
			$param_array 	= array();
			$param_array[1]	= $params[$i]['routekey'];
			$param_array[2]	= $params[$i]['routeenddate'];
			$param_array[3]	= $params[$i]['routeendtime'];
			$param_array[4]	= $params[$i]['routeendodometer'];
			$param_array[5] = ($params[$i]['totaldocuments'] <> ""?$params[$i]['totaldocuments']:0);
			$param_array[6] = ($params[$i]['totalcash'] <> ""?$params[$i]['totalcash']:0); //
			$param_array[7] = ($params[$i]['totalchecks'] <> ""?$params[$i]['totalchecks']:0); // $params[$i]['totalchecks'];			
			$param_array[8] = ($params[$i]['totalorderamount'] <> ""?$params[$i]['totalorderamount']:0); //$params[$i]['totalorderamount'];
			$param_array[9] = ($params[$i]['totalinvoiceamount'] <> ""?$params[$i]['totalinvoiceamount']:0); //$params[$i]['totalinvoiceamount'];
			$param_array[10] = ($params[$i]['totalchargesales'] <> ""?$params[$i]['totalchargesales']:0); //$params[$i]['totalchargesales'];
			$param_array[11] = ($params[$i]['totalcashsales'] <> ""?$params[$i]['totalcashsales']:0); //$params[$i]['totalcashsales'];
			$param_array[12] = ($params[$i]['totalacctsreceivable'] <> ""?$params[$i]['totalacctsreceivable']:0); //$params[$i]['totalacctsreceivable'];
			$param_array[13] = ($params[$i]['totalexpenses'] <> ""?$params[$i]['totalexpenses']:0); //$params[$i]['totalexpenses'];
			$param_array[14] = ($params[$i]['inventoryvariance'] <> ""?$params[$i]['inventoryvariance']:0); //$params[$i]['inventoryvariance'];
			$param_array[15] = ($params[$i]['cashvariance'] <> ""?$params[$i]['cashvariance']:0); //$params[$i]['cashvariance'];
			
			$resultdata = $this->SFA_Comman->executequery('CALL sp_ws_from_tablet_endday(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',$param_array,'');
     
			if(count($resultdata)>0)
			{
			    $arr[]=array("routekey"=>$resultdata[0][0]['routekey'],"routeenddate"=>$resultdata[0][0]['routeenddate'],"routeendtime"=>$resultdata[0][0]['routeendtime']);
			}		    
		    }
		 
		     $resultreturn['endday'] = $arr;
		}else
		    {
			 $resultreturn['endday'] = array();
		    }
		    
	    }
    }
    
     public function logoutAction()
    {	
		$reqval= $this->getRequest()->getParams();
	    $resultreturn = array();
		 if(!is_null($this->_getParam('logout')))
	    {
		$pararminvoice=$this->_getParam('logout');	
		$params = json_decode($pararminvoice,true);
		$ar=array();
		
		if(count($params)>0)
		{
		    for($i=0;$i<count($params);$i++)
		    {
			$param_array 	= array();
			$param_array[1]	= $params[$i]['routekey'];
			$param_array[2]	= $params[$i]['routecode'];
			
			$resultdata = $this->SFA_Comman->executequery('CALL sp_ws_delete_routekey(?,?)',$param_array,'');		    
		    }
		 
		}else
		{
		// $resultreturn['endday'] = array();
		}
		    
	    }
    }
    
     public function checkloadAction()
    {	
		$reqval= $this->getRequest()->getParams();
		$this->view->params = $params = $this->getRequest()->getParams();

        $param_array = array();
		$param_array[1] =  $params['userid'];
        $param_array[2] = $params['routeid'];
	 
        $resultdata = $this->SFA_Comman->executequery('CALL sp_ws_tablet_check_load(?,?)',$param_array,'');

	if ($resultdata[0][0]['cnt']>0)
	{
	  echo  $flag='1';
	}else
	{
	   echo  $flag='0';
	}
	
    }
    
	//GPS //disabled due to server low performance
	public function routetrackl12Action()
	{	
		$reqval= $this->getRequest()->getParams();
		$params = array();
		
		if(!is_null($this->_getParam('gpstrack')))
		{					
			$gpstrack=$this->_getParam('gpstrack');			
			$params = json_decode($gpstrack,true);
			
			if(count($params)>0)
			{
				for($i=0;$i<count($params);$i++)
				{
					$param_array = array();
					$param_array[1] = $params[$i]['lat'];
					$param_array[2] = $params[$i]['log'];
					$param_array[3] = $params[$i]['deviceid'];
					
					$result = $this->SFA_Comman->executequery('CALL sp_ws_getdata_from_routetrack(?,?,?)',$param_array,'');
				}
				
				$resultreturn['gpstrack'] = '1';
			}
			else
			{
			$resultreturn['gpstrack'] = '0';
			}
		} 	
		
		echo $resultdatacomp = $result[0];
		exit;
	}
	
    public function getdeliveryAction()
    {
		$reqval= $this->getRequest()->getParams();
		$resultreturn = array();
		$ar=array();
		
		if(!is_null($this->_getParam('delivery')))
	    {
			$pararminvoice=$this->_getParam('delivery');	
			$params = json_decode($pararminvoice,true);
		
			if(count($params)>0)
			{
				$param_array 	= array();
				$param_array[1]	= $params[0]['customercode'];
				$param_array[2]	= $params[0]['orderdate'];
				$param_array[3]	= $params[0]['orderno'];
				$param_array[4]	= $params[0]['lpono'];
				$param_array[5]	= $params[0]['routecode'];
				
				$resultdata = $this->SFA_Comman->executequery('CALL sp_ws_getdata_delivery(?,?,?,?,?)',$param_array,'');			 
							
				$ar['deliveryheader'] = (count($resultdata[0]) > 0) ? $resultdata[0]:array();
				$ar['deliverydetail'] = (count($resultdata[1]) > 0) ? $resultdata[1]:array();
				
				//$resultreturn['getdelivery'] = $ar;			
				array_walk_recursive($ar,'getdelivery');
				echo json_encode($ar);				
				
			}else
		    {
			 $resultreturn['getdelivery'] = array();
		    }
		    
	    }		
    }
	
	public function getwhstockAction()
    {
		$reqval= $this->getRequest()->getParams();
		$resultreturn = array();
		$ar=array();
		
		if(!is_null($this->_getParam('whstock')))
	    {
			$pararminvoice=$this->_getParam('whstock');	
			$params = json_decode($pararminvoice,true);
		
			if(count($params)>0)
			{
				$param_array 	= array();
				$param_array[1]	= $params[0]['routecode'];
				
				$resultdata = $this->SFA_Comman->executequery('CALL sp_ws_getdata_whstock(?)',$param_array,'');			 
							
				$ar['whstock'] = (count($resultdata[0]) > 0) ? $resultdata[0]:array();
						
				array_walk_recursive($ar,'getwhstock');
				echo json_encode($ar);				
				
			}else
		    {
			 $resultreturn['getwhstock'] = array();
		    }
		    
	    }		
    }
	
	public function getcustomerbalanceAction()
    {
		$reqval= $this->getRequest()->getParams();
		$resultreturn = array();
		$ar=array();
				
		if(!is_null($this->_getParam('customerbalance')))
	    {
			$pararminvoice=$this->_getParam('customerbalance');	
			$params = json_decode($pararminvoice,true);			
		
			if(count($params)>0)
			{
				$param_array 	= array();
				$param_array[1]	= $params[0]['routecode'];
				$param_array[2]	= $params[0]['customercode'];				
				
				$resultdata = $this->SFA_Comman->executequery('CALL sp_ws_getcustomer_balance(?)',$param_array,'');			 
							
				$ar['customerbalance'] = (count($resultdata[0]) > 0) ? $resultdata[0]:array();
				
				
				array_walk_recursive($ar,'customerbalance');
				echo json_encode($ar);				
				
			}else
		    {
			 $resultreturn['customerbalance'] = array();
		    }
		    
	    }		
    }
	
	public function getroutebalanceAction()
    {
		$reqval= $this->getRequest()->getParams();
		$resultreturn = array();
		$ar=array();
		
		if(!is_null($this->_getParam('routebalance')))
	    {
			$pararminvoice=$this->_getParam('routebalance');	
			$params = json_decode($pararminvoice,true);
		
			if(count($params)>0)
			{
				$param_array 	= array();
				$param_array[1]	= $params[0]['routecode'];
				
				$resultdata = $this->SFA_Comman->executequery('CALL sp_ws_getroute_balance(?)',$param_array,'');			 
							
				$ar['routebalance'] = (count($resultdata[0]) > 0) ? $resultdata[0]:array();
						
				array_walk_recursive($ar,'routebalance');
				echo json_encode($ar);				
				
			}else
		    {
			 $resultreturn['routebalance'] = array();
		    }
		    
	    }		
    }
	
		public function getwarehousestockAction()
	{
		$result = array();
	
			
			 $reqval= $this->getRequest()->getParams();
			$this->view->params = $params = $this->getRequest()->getParams();

			$param_array = array();
			
			$param_array[1] =  $params['userid'];
			$param_array[2] = $params['routeid'];
			
			$resultdata = $this->SFA_Comman->executequery('CALL sp_ws_tablet_get_warehousestock(?,?)',$param_array,'');
			
			$result['warehousestock']=  $resultdata[0];
			
            echo json_encode($result); 
   
	}
	public function getsupervisorfocAction()
	{
		$result = array();
	
			
			 $reqval= $this->getRequest()->getParams();
			$this->view->params = $params = $this->getRequest()->getParams();

			$param_array = array();			
			$param_array[1] =  $params['routeid'];
			
			
			$resultdata = $this->SFA_Comman->executequery('CALL sp_ws_tablet_get_supervisor_foc(?)',$param_array,'');
			
			$result['supervisorfoc']=  $resultdata[0];
			
            echo json_encode($result); 
   
	}
	public function updatesupervisorfocAction()
	{
		$formdata = $this->_request->getPost();
		 	//print_r($formdata['items');exit;	
		 $supervisorcode=$formdata['supervisorcode'];
		 $itemArray = json_decode($formdata['itemcode'], true);
		 
// echo count($itemArray)."-".$supervisorcode;exit;
		if(count($itemArray)>0 && $supervisorcode)
	    { 
			for($i=0;$i<count($itemArray);$i++)
			{
				$itemcode=$itemArray[$i]['itemcode'];
				$foc=$itemArray[$i]['manualfreeqty'];
				$param_array = array();			
			    $param_array[1] =  $itemcode;
				$param_array[2] =  $foc;
				$param_array[3] =  $supervisorcode;
				
				$resultdata = $this->SFA_Comman->executequery('CALL sp_ws_tablet_update_supervisor_foc(?,?,?)',$param_array,'');
				if(count($itemArray)==$i+1)
				{
					echo "Success";exit;
				}
			}	
						
		}
		else
		{
			echo "Invalid Input";exit;
		}
	 
   
	}
    function stripslashes_deep($value)
    {
	if(is_array($value))
	{
	    echo "array";
	}else
	{
	    echo "not array";
	}
    $value = is_array($value) ?array_map('stripslashes_deep', $value) : stripslashes($value);

    return $value;
    }
}