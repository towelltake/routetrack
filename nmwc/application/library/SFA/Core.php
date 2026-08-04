<?php
/**
 * @name       Tbl_Core
 * @since      19-09-2011
 * @version    Release: 1
 * @author     HD
 * @copyright  Elan Technologies
 * @param
 * This Class contains all the General functions which are used through out the site.
 */

class SFA_Core
{
	/**
	* @name       __construct
	* @since      20-09-2011
	* @version    Release: 1
	* @author     HD
	* @copyright  Elan Technologies
	* This is used to define all the settings from the setting table.
	*/
	public function __construct()
	{

	}

	/**
	* @name       random_generator
	* @since      10-10-2011
	* @version    Release: 1
	* @author     HD
	* @copyright  Elan Technologies
	* This is used to create random number for mail
	*/
	public function random_generator($digits)
	{
	    srand ((double) microtime() * 10000000);
	    //Array of alphabets
	    $input = array ("A", "B", "C", "D", "E","F","G","H","I","J","K","L","M","N","O","P","Q",
	    "R","S","T","U","V","W","X","Y","Z");

	    $random_generator="";// Initialize the string to store random numbers
	    for($i=1;$i<$digits+1;$i++){ // Loop the number of times of required digits

	    if(rand(1,2) == 1){// to decide the digit should be numeric or alphabet
	    // Add one random alphabet
	    $rand_index = array_rand($input);
	    $random_generator .=$input[$rand_index]; // One char is added

	    }else{

	    // Add one numeric digit between 1 and 10
	    $random_generator .=rand(1,10); // one number is added
	    } // end of if else

	    } // end of for loop

	    return $random_generator;
	}



	/**
	* @name       SetSiteUrlAddress
	* @since      10-10-2011
	* @version    Release: 1
	* @author     HD
	* @copyright  Elan Technologies
	* This is used send mail url contains upto project name and confirmation key
	*/
	public function SetSiteUrlAddress()
	{
	    $request = Zend_Controller_Front::getInstance()->getRequest();
	    $pageURL = $request->getScheme() . '://' . $request->getHttpHost();

	    $REQUESTURI=explode("/",$_SERVER['REQUEST_URI']);
	    $SCRIPTNAME=explode("/",$_SERVER['SCRIPT_NAME']);
	    $newurladd=array_intersect_assoc($REQUESTURI, $SCRIPTNAME);
	    if(count($newurladd) > 1)
	    {
		$baseurlset=implode("/",$newurladd)."/";
	    }
	    else
	    {
		$baseurlset="/";
	    }
	    return $pageURL.$baseurlset;
	}

	public function fullUrl($url)
	{
	    $request = Zend_Controller_Front::getInstance()->getRequest();
	    $url = $request->getScheme() . '://' . $request->getHttpHost() . $url;
	    return $url;
	}

        /**
	* @name       urlpaths
	* @since      29-02-2012
	* @version    Release: 1
	* @author     AS <alpesh@elantechnologies.com>
	* @copyright  Elan Technologies
	* This is used to define various urlpaths
	*/
        public static function urlpaths()
	{
		$patharray["FullPath"] 		= str_replace("\\","/",dirname(APPLICATION_PATH));
		$patharray["ProjectDir"] 	= basename($patharray["FullPath"]);		
		if($_SERVER['HTTP_HOST'] == '23.21.226.54')
			$patharray["FullUrl"] 		= "http://".$_SERVER['HTTP_HOST']."/php/".$patharray["ProjectDir"];
		else
			$patharray["FullUrl"] 		= "http://".$_SERVER['HTTP_HOST']."/".$patharray["ProjectDir"];

		return $patharray;
	}


    /**
    * @name       isEmpty
    * @since      10-10-2011
    * @version    Release: 2
    * @author     HD
    * @copyright  Elan Technologies
    * This is used to check whether the folder is empty
    */
    public function isEmpty($dir)
    {
	    if ($dh = @opendir($dir))
	    {
		    while ($file = readdir($dh))
		    {
			    if ($file != '.' && $file != '..')
			    {
				    closedir($dh);
				    return false;
			    }
		    }
		    closedir($dh);
		    return true;
	    }
	    else return false; // whatever the reason is : no such dir, not a dir, not readable
    }

    public function recursive_remove_directory($directory, $empty=FALSE)
    {
	    if(substr($directory,-1) == '/')
	    {
		    $directory = substr($directory,0,-1);
	    }
	    if(!file_exists($directory) || !is_dir($directory))
	    {
		    return FALSE;
	    }
	    elseif(is_readable($directory))
	    {
		    $handle = opendir($directory);
		    while (FALSE !== ($item = readdir($handle)))
		    {
			    if($item != '.' && $item != '..')
			    {
				    $path = $directory.'/'.$item;
				    if(is_dir($path))
				    {
					    recursive_remove_directory($path);
				    }
				    else
				    {
				    unlink($path);
				    }
			    }
		    }
		    closedir($handle);
		    if($empty == FALSE)
		    {
			    if(!rmdir($directory))
			    {
				    return FALSE;
			    }
		    }
	    }
	    return TRUE;
    }
}