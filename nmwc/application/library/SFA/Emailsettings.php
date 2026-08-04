<?php
/**
 * @name       Tbl_Imageprocess
 * @since      21-09-2011
 * @version    Release: 1
 * @author     HD
 * @copyright  Elan Technologies
 * @param      NULL	
 * This Class contains all the Functions Related to Image Processing using ImageMagick
 */

class SFA_Emailsettings
{
	private $SmtpMailAddress;
	private $SmtpAUTHDet;
	private $db = "";
	private $_dbAdapter;
	
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
		try
		{
		    $this->SmtpMailAddress = "mail.elantechnologies.com";
		    
		    $this->SmtpAUTHDet = array('auth' => 'Login',
				    'username' => 'hiren.d@elantechnologies.com',
				    'password' => 'hiren@123',
				    'email' => 'info@materialmix.com');
		}
		catch (Zend_Exception $e)
		{
		    echo "Error message: " . $e->getMessage() . "\n";
		}
	}
	
	/**
	* @name       parseTemplate
	* @since      26-09-2011
	* @version    Release: 1
	* @author     HD
	* @copyright  Elan Technologies	
	* This is used for parsing the template for sending the Email, according to the template_id specified.
	*/
	public function parseTemplate($TemplateVars,$TemplateName)
	{
	    $html = $this->getEmailTemplate($TemplateName);	
	    
	    if(count($TemplateVars)>0){
		foreach($TemplateVars as $k=>$v){
		$html=str_replace($k,$v,$html);
		}
	    }
	    return $html;
	}
	
	
	
	/**
	* @name       getEmailTemplateSubject
	* @since      07-10-2011
	* @version    Release: 1
	* @author     HD
	* @copyright  Elan Technologies	
	* This is used for getting subject of email template.
	*/
	public function getEmailTemplateSubject($key_id)
	{
	    $db=$this->getDataUseradapter();
	    $sql="SELECT subject FROM email_template WHERE id ='".$key_id."'";
	    return $html = $db->fetchOne($sql);
	}
	
	
	/**
	* @name       getEmailTemplate
	* @since      07-10-2011
	* @version    Release: 1
	* @author     HD
	* @copyright  Elan Technologies	
	* This is used for Getting the Email Template Information
	*/
	public function getEmailTemplate($key_id)
	{
	    $db=$this->getDataUseradapter();
	    $sql="SELECT message FROM email_template WHERE id ='".$key_id."'";
	    return $html = $db->fetchOne($sql);
	}

	/**
	* @name       sendMail
	* @since      07-10-2011
	* @version    Release: 1
	* @author     HD
	* @copyright  Elan Technologies
	* 
	* params	
	*
	* $template 		=> Template_id for recognizing the template to be used
	* $template_vars	=> Template Vars for replacing the static string with the array of the dynamic value
	* $receipents		=> Array like ("Name" => "Email_Address"), containing the list of the receipents of the email
	* $sender		=> Array like ("Name" => "Email_Address"), containing the Sender Information
	* $postdata		=> Array Containing the Custom Template Information from the posted data
	* $subject_vars 	=> Subject Vars for replacing the static string with the array of the dynamic value in the Email Subject
	*
	* This is used for Sending the Email as per the parameters specified.
	*/
	public function sendMail($data)
	{
	    $transport = new Zend_Mail_Transport_Smtp($this->SmtpMailAddress, $this->SmtpAUTHDet);
	    $mail = new Zend_Mail();				
	    $mail->setBodyHtml($data['html']);
	    $mail->setFrom($data['from'], $data['from_name']);
	    $mail->addTo($data['to'], $data['to_name']);
	    $mail->setSubject($data['subject']);
	    $mail->send($transport);
	    return true;
	}
	
	public function getDataUseradapter()
	{
		$this->db = Zend_Db_Table::getDefaultAdapter();
		return $this->db;
	}
}