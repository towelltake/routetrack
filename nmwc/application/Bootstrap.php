<?php
class Bootstrap extends Zend_Application_Bootstrap_Bootstrap
{
    protected function _initAutoload()
    {
     
    }
    
     #stores a copy of the config object in the Registry for future references
     #!IMPORTANT: Must be runed before any other inits
    protected function _initConfig()
    {
    	Zend_Registry::set('config', new Zend_Config($this->getOptions()));
    }
	
	#Initializes the default timezone for the php ENV
    protected function _initDate()
    {
    	date_default_timezone_set(Zend_Registry::get('config')->settings
                                    ->application
                                    ->datetime);
    }
    
    function _initViewHelpers()
    {        
        //Set the layout Path
        Zend_Layout::startMvc(array('layoutPath' => APPLICATION_PATH.'/theme/default/layouts'));
        
        $path = $_SERVER['REQUEST_URI'];
		$path1 = explode('/', trim($path, '/'));
                
	    if(in_array("manage",$path1))
	    {
		Zend_Layout::startMvc(array('layoutPath' => APPLICATION_PATH.'/modules/manage/layouts'));
		$modLoader = new Zend_Application_Module_Autoloader(array(
			'namespace' => '',
			'basePath' => APPLICATION_PATH . '/manage',
			'resourceTypes' => array (
			'model' => array(
			'path'       => 'manage/models',
			'namespace'  => 'Manage_Model',
			),
		      )
		    )
		);
	    }
	  /*
	   * Added by MB [Elan] - To make seo based url for cms pages - Start
	   */
	  $ctrl  = Zend_Controller_Front::getInstance();
	  $router = $ctrl->getRouter(); // returns a rewrite router by default
	  
	  $route['page'] = new Zend_Controller_Router_Route_Regex(
				  '([^\.]+)\.html',
				  array(
				  'module' => 'pages',
				  'controller' => 'index',
				  'action'     => 'page'
				  )
			  );
	  $router->addRoute('page', $route['page']);	  
    }
    protected function _initTranslate()
    {
	$session = new Zend_Session_Namespace('SESSION');
	
	if (isset($session->lang)) {
	    # get language from session
	    $locale = $session->lang;
        }
        else if (isset($_COOKIE['lang'])) {
            # get language from cookie if no have session            
	    $locale = $_COOKIE['lang'];
        }
        else if (Zend_Locale::getBrowser()) {
	    # get language from browser if no have session and cookie
	    foreach (Zend_Locale::getBrowser() as $lang => $poid) {
		if ($poid==1){
		    $locale = $lang;
		}
	    }
	    //$locale = 'ar';
            switch ($locale) {
                case 'en':
                case 'en_US':	
                    $locale = 'en_US';
                    break;
                case 'ar':
                case 'ar_AR':	
                    $locale = 'ar_AR';
                    break;
                default://others => english
                    $locale = 'en_US';
                    break;
            }
        }
        // if 3 cases above still be empty, get from user profile 
     	//@TODO Need to update this line in case there are more than 2 langauges used in application
        if ($locale!='ar_AR' && $locale!='en_US' && $session->user_login){
            if ($session->user_login) $locale=$session->user_login->getLanguage();
        } 
        // if user profile is null, select default one
        if ($locale!='ar_AR' && $locale!='en_US'){
            $locale='en_US';
        } 
        
	#save back to session
        $session->lang = $locale;
	setcookie('lang', $locale, time() + (3600),'/');
	
	//$locale = 'ar_AR';
        #Set language to transtale
        $translate = new Zend_Translate('gettext',
	    APPLICATION_PATH . DIRECTORY_SEPARATOR . 'languages/' . ($locale) . '.mo', null,
	    array(
		'disableNotices' => false, // This is a very good idea!
		'logUntranslated' => false, // Change this if you debug
	    )
        );
		
	$registry = Zend_Registry::getInstance();
        //$registry->set('Zend_Locale', $locale);
        $registry->set('Zend_Translate', $translate);

        $loader = Zend_Loader_Autoloader::getInstance();
        $loader->registerNamespace('SFA_');
        
        
        //setup mysql query cache
        $cache_config = $this->getOption('cache');
		$frontendOptions = array('lifetime' => $cache_config['db']['adaptor']['result_lifetime'], 'automatic_serialization' => true);
		$backendOptions = array('cache_dir' => $cache_config['db']['adaptor']['path']);
		$zend_cache = Zend_Cache::factory('Core', 'File', $frontendOptions, $backendOptions);
        $registry->set('Zend_DB_Cache', $zend_cache);
        
        
        return $registry;
    }
	public function _initErrorHandler()
	{
		// make sure the frontcontroller has been setup
		$this->bootstrap('frontcontroller');
		$frontController = $this->getResource('frontcontroller');
		// option from the config file
		$pluginOptions   = $this->getOption('errorhandler');
		$className       = $pluginOptions['class'];
	
		// I'm using zend_loader::loadClass() so it will throw exception if the class is invalid.
		try {
			Zend_Loader::loadClass($className);
			$plugin          = new $className($pluginOptions['options']);
			$frontController->registerPlugin($plugin);
			return $plugin;
		} catch (Exception $e) {
			echo $e->getMessage();
			return null;
		}
	}
}