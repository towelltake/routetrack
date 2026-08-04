<?php  
session_start();//session starts here  
error_reporting(0);
  
?>  
  
  
  
<html>  
<head lang="en">  
    <meta charset="UTF-8">  
    <link type="text/css" rel="stylesheet" href="bootstrap-3.2.0-dist\css\bootstrap.css">  
    <title>RoutePro OTP</title>  
</head>  
<style>  
.tableheader {
background-color: #fafafa;
color:#000;
font-weight:bold;

}
.tablerow {
background-color: #fafafa;
color:#000;
}
.message {
color: #000000;
font-weight: bold;
text-align: center;
width: 100%;
}
.body_bg{
	background: #d1d1d1;
	background-repeat:repeat-x;
}


    .login-panel {  
        margin-top: 60px; 
				}		
  
</style>  
  
<body class="body_bg" src="bg.png">  
  
  <h1 align="center">
<img alt="RoutePro" src="route_logo.png">
</h1>
<div class="container">  
    <div class="row">  
        <div class="col-md-4 col-md-offset-4">  
            <div class="login-panel panel panel-success">  
                 
                <div class="panel-body">  
                    <form role="form" method="post" action="index.php">  
					<table border="0" cellpadding="10" cellspacing="1" width="500" align="center">
<tr class="tableheader">
<td align="center" colspan="2">Enter Login Details</td>
</tr>
<tr class="tablerow">
<td align="right">Username</td>
<td><input type="text" name="email"></td>
</tr>
<tr class="tablerow">
<td align="right">Password</td>
<td><input type="password" name="pass"></td>
</tr>
<tr class="tableheader">
<td align="center" colspan="2"><input type="submit" name="login" value="Submit"></td>
</tr>
</table>
                    </form>  
                </div>  
            </div>  
        </div>  
    </div>  
</div>  
  
  
</body>  
  
</html>  
  
<?php  
  
include("db_conection.php");  
  
if(isset($_POST['login']))  
{  
    $user_email=$_POST['email'];  
    $user_pass=$_POST['pass'];  
  
    $check_user="select username,usertypeid from usermaster WHERE username='$user_email' AND password='$user_pass'";  
  
    $run=mysqli_query($dbcon,$check_user);  
	$row=mysqli_fetch_array($run);
    if(mysqli_num_rows($run))  
    {  
        echo "<script>window.open('password.php','_self')</script>";  
  
        $_SESSION['email']=$user_email;//here session is used and value of $user_email store in $_SESSION.  
		$_SESSION['usertypeid']=$row["usertypeid"];
    }  
    else  
    {  
      echo "<script>alert('Username or password is incorrect!')</script>";  
    }  
}  
?>