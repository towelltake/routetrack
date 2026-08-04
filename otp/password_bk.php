<?php
error_reporting(0);
  
session_start();  
  
if(!$_SESSION['email'])  
{  
  
    header("Location: index.php");//redirect to login page to secure the welcome page without login access.  
}  
  
  
function toAlpha($data){
        
        $numlen = (int)strlen($data);        
        if($numlen <8) {            
            $data = (int)($data.'123');
        }
        
        $alphabet =   array('a','b','c','d','e','f','g','h','i','j','k','l','m','n','o','p','q','r','s','t','u','v','w','x','y','z');
		//$alphabet =   array('0','1','2','3','4','5','6','7','8','9');
		
        $alpha_flip = array_flip($alphabet);
        if($data <= 25){
			
          return toNumber($alphabet[$data]);
        }
        elseif($data > 25){
          $dividend = ($data + 1);		  
          $alpha = '';
          $modulo;
          while ($dividend > 0)
		  {
             $modulo = ($dividend - 1) % 26;			
             $alpha = $alphabet[$modulo] . $alpha;		
             $dividend = floor((($dividend - $modulo) / 26));
          } 
		  
          return toNumber($alpha);
        }    
    }
	
	function toNumber($data1)
	{
		$str = $data1; 
		$out = '';
		$pos = '';
			for($i = 0;$i<strlen($str);$i++) 
			{
    
				switch($str[$i]) 
				{
					case '0': $pos .="0";break;
					case '1': $pos .="1";break;
					case '2': $pos .="2";break;
					case '3': $pos .="3";break;
					case '4': $pos .="4";break;
					case '5': $pos .="5";break;
					case '6': $pos .="6";break;
					case '7': $pos .="7";break;
					case '8': $pos .="8";break;
					case '9': $pos .="9";break;
					case '-': $pos .="-";break;
					case  'a': case 'b': case 'c': $pos .="2";break;
					case  'd': case 'e': case 'f': $pos .="3";break;
					case  'g': case 'h': case 'i': $pos .="4";break;
					case  'j': case 'k': case 'l': $pos .="5";break;
					case  'm': case 'n': case 'o': $pos .="6";break;
					case  'p': case 'q': case 'r': case 's': $pos .="7";break;
					case  't': case 'u': case 'v': $pos .="8";break;
					case  'w': case 'x': case 'y': case 'z': $pos .="9";break;
				}
			}
	return $pos;
	}
?>
	<html>
<head>

<title>Password Generator</title>
<style>
.body_bg{
	background: #d1d1d1;
	background-repeat:repeat-x;
}
</style>
<script text="Javascript">
function validation()
{
	if(document.getElementById('pass').value=="")
	{
		alert("Please Enter Access Key");
		return false;
	}
	else		
	{
		return true;
	}
}
</script>

 </head>
<body class="body_bg" src="bg.png" >

<h1 align="center"> <img alt="RoutePro" src="route_logo.png"> </h1>

<h2 align="center">OTP Generator</h2>

Logged As : <?php echo $_SESSION['email']; ?>  |  <a href="logout.php">Logout</a>  

<br><br>

<form action="" method="POST" align="center">
  Access Key : <input type="text" value="<?php echo $_POST['pass'];?>" name="pass" id="pass">
  <input  type="submit" value="Password" name="submit" onclick="return validation();"/>
</form>
<div class="left" align="center">
<div id="pwd">
<?php
if($_POST['submit'])
{
	echo "OTP : <font color='red'>".toAlpha($_POST['pass'])."</font>";
}
?>
</div>
</div></body>
</html>
