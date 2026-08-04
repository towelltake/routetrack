<?php

class IndexController extends Home_Library_Controller_Action_Abstract
{
    /**
    * @name       init
    * @since      02-09-2011
    * @version    Release: 1
    * @author     HD <hiren.d@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param   	
    *
    * This is the default function for all Actions.
    *
    */
    
    //Initilize var for App Model
    private $index = "";
    
    public function init()
    {
		$this->translate 			= Zend_Registry::get('Zend_Translate');
		$this->view->required		= $this->translate->_('Required');
		$this->view->colan			= $this->translate->_('Colan');
		
		$this->SFA_Comman 			= new SFA_Comman();
    }
    
    /**
    * @name       indexAction
    * @since      02-09-2011
    * @version    Release: 1
    * @author     HD <hiren.d@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param   	
    *
    * This is the index Action or the default Action where there is no Action specified.
    *
    */
    public function indexAction()
    {
		
		$this->currentUser = SFA_Loginauth::getIdentity();	
		if(isset($this->currentUser))
		{
			$this->_helper->redirector("home", "index", "home");
		}
        $this->view->Login				= $this->translate->_('Login');
		$this->view->Username 			= $this->translate->_('Username');
		$this->view->Password 			= $this->translate->_('Password');
		$this->view->Password_Remember 	= $this->translate->_('Password Remember');
	
	
		$this->_helper->layout->disableLayout();
		$this->_helper->layout->setLayout('login');
		
		
		
		
		if($this->_request->isPost())
		{
			$this->view->formdata = $formdata = $this->_request->getPost();
			$username 	= $formdata['txtusername'];
			$password  	= $formdata['txtpassword'];
			
			$auth = new SFA_Loginauth($username, $password);
			$result = $auth->authenticate();
			
			if(count($result) > 0)
			{
				$checkUser = $auth->getIdentity();		
				if($checkUser->userid != '')
				{
					if(isset($formdata['chkautologin']) && $formdata['chkautologin'] > 0) {
						$expire=time()+60*60*24*30;
						setcookie("username", $formdata['txtusername'], $expire, "/");
						setcookie("password", $formdata['txtpassword'], $expire, "/");
					}
					
					/* Added by Hiren dave for Headar menu*/
					$main_array 	= $this->SFA_Comman->permissions();
					
					$Menu_NameSpace = new Zend_Session_Namespace('Menu');
					// For Header Menu
					$resultarr = $main_array[0];
					$header_menu = array();
					for($i=0;$i<count($resultarr);$i++)
					{
						$header_menu[$resultarr[$i]['flagname']] = $resultarr[$i];
					}					
					$Menu_NameSpace->header_menu	= $header_menu;
					
					
					$resarr = $main_array[1];
					$settings = array();
					for($i=0;$i<count($resarr);$i++)
					{
						$settings[$resarr[$i]['flagname']] = $resarr[$i];
					}
					
					//SFA_Comman::pre($main_array);
					
					$Settings_NameSpace = new Zend_Session_Namespace('Settings');
					unset($Settings_NameSpace->settings);
					unset($Settings_NameSpace->cpanel);
					
					$cpanel 		= $main_array[3];
					$cp_settings 	= array();
					for($i=0;$i<count($cpanel);$i++)
					{
						$cp_settings[$cpanel[$i]['flagname']] = $cpanel[$i];
					}
					
					$itemcostprice	= $main_array[4];
					$cp_settings[$itemcostprice[0]['flagname']] = $itemcostprice[0];
					$cp_settings[$itemcostprice[1]['flagname']] = $itemcostprice[1];
					
					$Settings_NameSpace->settings	= $settings;
					$Settings_NameSpace->cpanel		= $cp_settings;					
                    
					
					$Setup_NameSpace = new Zend_Session_Namespace('Setup');
					unset($Setup_NameSpace->options);
					$Setup_NameSpace->options['misreport']	= $main_array[2][0]["importfilepath"];
			
                    /**
                     *  Pankil Thakkar : Make Acl array -- start
                     */
                    
                    $SFA_Model_Core = new SFA_Model_Core();
                    $acldata = $SFA_Model_Core->gettypedetail($checkUser->userid,$checkUser->usertypeid);                    
                    $new_aclarr = array();
                    
                    for($i=0 ;$i < count($acldata);$i++)
                    {
                        $new_aclarr[$acldata[$i]['formname']] = $acldata[$i];
                    }					
                    $acl = new Zend_Session_Namespace('Acl');
                    $acl->acl = $new_aclarr;
                    
                    /**
                     *  Pankil Thakkar : Make Acl array -- End
                     */
					
					$com_info = $this->SFA_Comman->executequery('CALL sp_get_company_information()','','');
					$companyinfo = new Zend_Session_Namespace('Company_Info');
					$companyinfo->companyname	= $com_info[0][0]['name'];
					
					$this->_helper->redirector('home', 'index', 'home');
				}
				else
				{
					SFA_Loginauth::destroy();
					SFA_Message::setMsg($this->translate->_('Please check your Username or Password'));
				}
			}
			else
			{
				SFA_Loginauth::destroy();
				SFA_Message::setMsg($this->translate->_('Please check your Username or Password'));
			}
		}
		elseif(isset($_COOKIE['username']) && $_COOKIE['username'] !='' && isset($_COOKIE['password']) && $_COOKIE['password'] !='')
		{
			$this->view->username 	= $_COOKIE['username'];
			$this->view->password 	= $_COOKIE['password'];
			$this->view->autologin 	= 'checked';
		}
		
    }
    
    
    /**
    * @name       logoutAction
    * @since      03-04-2012
    * @version    Release: 1
    * @author     HD <hiren.d@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param   	
    * Logout     
    */
    public function logoutAction()
    {
		SFA_Loginauth::destroy();
		Zend_Session::destroy();
		//$this->_helper->redirector("index", "index", "home");
			$url = $this->view->baseUrl();
			echo '<script type="text/javascript">window.location="'.$url.'";</script>';
			exit;
    }
    /**
    * @name       homeAction
    * @since      03-04-2012
    * @version    Release: 1
    * @author     HD <hiren.d@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param   	
    * Logout     
    */
    public function homeAction()
    {
		$this->currentUser = SFA_Loginauth::getIdentity();		
		
		if(!isset($this->currentUser) || empty($this->currentUser))
		{
			SFA_Message::setMsg($this->translate->_('Do Login'));
			//$this->_helper->redirector("index", "index", "home");
			$url = $this->view->baseUrl();
			echo '<script type="text/javascript">window.location="'.$url.'";</script>';
			exit;
		}	
    }
    /**
    * @name       multispAction
    * @since      19-03-2012
    * @version    Release: 1
    * @author     HD <hiren.d@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * */
    public function multispAction()
    {
		$this->_helper->layout->disableLayout();
		$param_array = array();
		//$param_array[1] = "1";
		$data = $this->SFA_Comman->executequery('CALL sp_get_admin_cpanel_index()','','');
		
		SFA_Comman::pre($data);	
    }
    
    /**
    * @name       changelangAction
    * @since      02-09-2011
    * @version    Release: 1
    * @author     HD <hiren.d@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param   	
    *
    * This is the function for changing a language of a website.
    */
    
    public function changelangAction()
    {
		$this->_helper->layout()->disableLayout();
		
		$this->_helper->viewRenderer->setNoRender(true);
		
		$parm_val = $this->getRequest()->getParams();
		
		$lasturl = $_SERVER['HTTP_REFERER'];
		
		$baseurl = Zend_Controller_Front::getInstance()->getBaseUrl();
		
		$redirect = explode($baseurl,$lasturl);
		
		$session = new Zend_Session_Namespace('SESSION');
		
		$session->lang = $parm_val['lang'];
		
		setcookie('lang', $parm_val['lang'], time() + (3600),'/');
		
		$this->_redirect($redirect[1]);	
    }
	/**
    * @name       getsalesmanfromroutecode
    * @since      03-08-2012
    * @version    Release: 1
    * @author     HD <hiren.d@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param   	
    *
    * This is the function for getting salesman name from route code
    */
	public function getsalesmanfromroutecodeAction()
	{
		$params = $this->getRequest()->getParams();
		$result = $this->SFA_Comman->executequery('CALL sp_get_salesman_customer_from_routecode(?)',$params['routeid'],'');
		echo Zend_Json::encode($result);
		exit;
	}
	/**
    * @name       getopeningsalesmanfromroutecode
    * @since      03-08-2012
    * @version    Release: 1
    * @author     HD <hiren.d@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param   	
    *
    * This is the function for getting salesman name from route code
    */
	public function getopeningsalesmanfromroutecodeAction()
	{
		$params = $this->getRequest()->getParams();
		$result = $this->SFA_Comman->executequery('CALL sp_get_opensalesman_customer_from_routecode(?)',$params['routeid'],'');
		echo Zend_Json::encode($result);
		exit;
	}
	/**
    * @name       getgccustomerfromroutecode
    * @since      03-08-2012
    * @version    Release: 1
    * @author     HD <hiren.d@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param   	
    *
    * This is the function for getting salesman name from route code
    */
	public function getgccustomerfromroutecodeAction()
	{
		$params = $this->getRequest()->getParams();
		$result = $this->SFA_Comman->executequery('CALL sp_get_salesman_gccustomer_from_routecode(?)',$params['routeid'],'');
		echo Zend_Json::encode($result);
		exit;
	}	
	/**
    * @name       gethocustomerfromroutecode
    * @since      03-08-2012
    * @version    Release: 1
    * @author     HD <hiren.d@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param   	
    *
    * This is the function for getting salesman name from route code
    */
	public function gethocustomerfromroutecodeAction()
	{
		$params = $this->getRequest()->getParams();
		$result = $this->SFA_Comman->executequery('CALL sp_get_salesman_hocustomer_from_routecode(?)',$params['routeid'],'');
		echo Zend_Json::encode($result);
		exit;
	}
	/**
    * @name       getdccustomerfromroutecode
    * @since      03-08-2012
    * @version    Release: 1
    * @author     HD <hiren.d@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param   	
    *
    * This is the function for getting salesman name from route code
    */
	public function getdccustomerfromroutecodeAction()
	{
		$params = $this->getRequest()->getParams();
		$result = $this->SFA_Comman->executequery('CALL sp_get_salesman_dccustomer_from_routecode(?)',$params['routeid'],'');
		echo Zend_Json::encode($result);
		exit;
	}
	/**
    * @name       getinvoicesfromcustomercodeAction
    * @since      28-08-2012
    * @version    Release: 1
    * @author     HD <hiren.d@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param   	
    *
    * This is the function for getting invoices from customercode and transaction date
    */
	public function getinvoicesfromcustomercodeAction()
	{
		$params = $this->getRequest()->getParams();
		$param_array 	= array();
		$param_array[1] = $params['customerid'];
		$param_array[2] = $params['trandate'];
		
		$result = $this->SFA_Comman->executequery('CALL sp_combo_invoice_customerocde(?,?)',$param_array,'');
		echo Zend_Json::encode($result);
		exit;
	}
	/**
    * @name       getcustomerfromroutecode
    * @since      03-08-2012
    * @version    Release: 1
    * @author     HD <hiren.d@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param   	
    *
    * This is the function for getting customer values from route code
    */
	public function getcustomerfromroutecodeAction()
	{
		$params = $this->getRequest()->getParams();
		$result = $this->SFA_Comman->executequery('CALL sp_combo_customermaster_routecode(?)',$params['routeid'],'');
		echo Zend_Json::encode($result);
		exit;
	}
	/**
    * @name       getiteminfoAction
    * @since      03-08-2012
    * @version    Release: 1
    * @author     HD <hiren.d@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param   	
    *
    * This is the function for getting customer values from route code
    */
	public function getiteminfoAction()
	{
		$params = $this->getRequest()->getParams();
		$result = $this->SFA_Comman->executequery('CALL sp_get_iteminfo_itemcode(?)',$params['itemid'],'');		
		echo Zend_Json::encode($result);
		exit;
	}
	/**
    * @name       getitembatchinfoAction
    * @since      03-08-2012
    * @version    Release: 1
    * @author     HD <hiren.d@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param   	
    *
    * This is the function for getting batch value
    */
	public function getitembatchinfoAction()
	{
		$params = $this->getRequest()->getParams();
		$result = $this->SFA_Comman->executequery('CALL sp_get_inventory_transaction_batchinfo(?)',$params['depotinvkey'],'');
		echo Zend_Json::encode($result);
		exit;
	}
	/**
    * @name       getiteminfobatchexpAction
    * @since      03-08-2012
    * @version    Release: 1
    * @author     HD <hiren.d@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param   	
    *
    * This is the function for getting batch value
    */
	public function getiteminfobatchexpAction()
	{
		$params = $this->getRequest()->getParams();
		$result = $this->SFA_Comman->executequery('CALL sp_get_inventory_transaction_batchinfo_depotexp(?)',$params['depotinvkey'],'');
		echo Zend_Json::encode($result);
		exit;
	}
	/**
    * @name       getitemquantityinfoAction
    * @since      03-08-2012
    * @version    Release: 1
    * @author     HD <hiren.d@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param   	
    *
    * This is the function for getting customer values from route code
    */
	public function getitemquantityinfoAction()
	{
		$params = $this->getRequest()->getParams();
		
		$param_array 	= array();
		$param_array[1] = $params['routecode'];
		$param_array[2] = $params['itemid'];
		
		if($params['ddate'] !='' && $params['loadno'])
		{
			$param_array[3] = $params['ddate'];
			$param_array[4] = $params['loadno'];
			
			$result = $this->SFA_Comman->executequery('CALL sp_get_inventory_transaction_dailysalesmanload_load_request(?,?,?,?)',$param_array,'');	
		}
		else
		{
			$result = $this->SFA_Comman->executequery('CALL sp_get_inventory_transaction_dailysalesmanload_quantity(?,?)',$param_array,'');	
		}
		echo Zend_Json::encode($result);
		exit;
	}
	/**
    * @name       getinvoicedetailAction
    * @since      03-08-2012
    * @version    Release: 1
    * @author     HD <hiren.d@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param   	
    *
    * This is the function for getting salesman name from route code
    */
	public function getinvoicedetailAction()
	{
		$params = $this->getRequest()->getParams();
		$result = $this->SFA_Comman->executequery('CALL sp_get_invocedetail(?)',$params['invoiceid'],'');
		echo Zend_Json::encode($result);
		exit;
	}
	/**
    * @name       getcustomerdepotcodeAction
    * @since      25-10-2012
    * @version    Release: 8
    * @author     HD <hiren.d@elantechnologies.com>
    * @copyright  Elan Technologies
    * @param
    *
    * This is the function for getting custoemr from depotcode
    */
	public function getcustomerdepotcodeAction()
	{
		$params = $this->getRequest()->getParams();
		$result = $this->SFA_Comman->executequery('CALL sp_combo_customermaster_depotcode(?)',$params['depotid'],'');
		echo Zend_Json::encode($result);
		exit;
	}
	public function createclientdbAction(){
		echo SFA_Comman::installClientDatabase(2);
		exit;
	}
}