<?php
error_reporting(1);

session_start();

if (!$_SESSION["email"]) {
	header("Location: login.php"); //redirect to login page to secure the welcome page without login access.
}

function tonewpass($newkey, $cust)
{
	//var_dump($newkey); exit;
	//$str ='049574563';
	$new = "";
	$narr = [];
	$chars = chunk_split($newkey, 2, ",");

	$char = explode(",", $chars);
	$key = array_filter($char);

	for ($i = 0; $i < count($key); $i++) {
		//echo $key[$i]."<br>";
		$new = 100 - $key[$i];
		array_push($narr, $new);
	}
	$str = implode($narr, "");
	
	$rstr = strrev($str);

	return toAlpha($rstr, $cust);
}
function toAlpha($data, $cust)
{

	$numlen = (int) strlen($data);
	if ($numlen < 8) {
		//$data = (int) ($data . "123");
	}
	
	
	$data = $data + $cust;
	//var_dump($data);
	$alphabet = [
		"a",
		"b",
		"c",
		"d",
		"e",
		"f",
		"g",
		"h",
		"i",
		"j",
		"k",
		"l",
		"m",
		"n",
		"o",
		"p",
		"q",
		"r",
		"s",
		"t",
		"u",
		"v",
		"w",
		"x",
		"y",
		"z",
	];
	//$alphabet =   array('0','1','2','3','4','5','6','7','8','9');

	$alpha_flip = array_flip($alphabet);
	if ($data <= 25) {
		return toNumber($alphabet[$data]);
	} elseif ($data > 25) {

		$dividend = $data + 1;
		$alpha = "";
		$modulo;
		
		//echo 'before '. $dividend;
		
		while ($dividend > 0) {
			// $modulo = ($dividend - 1) % 26;	//org line sujee commented
			$modulo = fmod($dividend - 1, 26);
			$alpha = $alphabet[$modulo] . $alpha;
			$dividend = floor(($dividend - $modulo) / 26);
			//var_dump('modul0 - '.$modulo."\n");
			//var_dump('alpha - '.$alpha."\n");
			//var_dump('divident - '.$dividend."\n");
		}

		//echo $alpha;

		return toNumber($alpha);
	}
}

function toNumber($data1)
{
	$str = $data1;
	$out = "";
	$pos = "";
	for ($i = 0; $i < strlen($str); $i++) {
		switch ($str[$i]) {
			case "0":
				$pos .= "0";
				break;
			case "1":
				$pos .= "1";
				break;
			case "2":
				$pos .= "2";
				break;
			case "3":
				$pos .= "3";
				break;
			case "4":
				$pos .= "4";
				break;
			case "5":
				$pos .= "5";
				break;
			case "6":
				$pos .= "6";
				break;
			case "7":
				$pos .= "7";
				break;
			case "8":
				$pos .= "8";
				break;
			case "9":
				$pos .= "9";
				break;
			case "-":
				$pos .= "-";
				break;
			case "a":
			case "b":
			case "c":
				$pos .= "2";
				break;
			case "d":
			case "e":
			case "f":
				$pos .= "3";
				break;
			case "g":
			case "h":
			case "i":
				$pos .= "4";
				break;
			case "j":
			case "k":
			case "l":
				$pos .= "5";
				break;
			case "m":
			case "n":
			case "o":
				$pos .= "6";
				break;
			case "p":
			case "q":
			case "r":
			case "s":
				$pos .= "7";
				break;
			case "t":
			case "u":
			case "v":
				$pos .= "8";
				break;
			case "w":
			case "x":
			case "y":
			case "z":
				$pos .= "9";
				break;
		}
	}
	return $pos;
}
?>
<html>

<head>

	<title>RoutePro OTP</title>
	<style>
		#container {
			width: 100%;
			margin: 10px auto;
			/* centers it */
			padding: 0;
		}

		#right {
			float: right;
		}

		#center {
			float: center;
		}

		#bottom {
			clear: both;
			padding: 10px 2%;
			margin: 0;
		}

		.body_bg {
			background: #d1d1d1;
			background-repeat: repeat-x;
			font-family: Verdana, sans-serif;
			font-size: 70%;
		}

		table,
		tr,
		td {
			border: 1px solid #000;
		}

		.heading {
			font-weight: bold;
			text-decoration: UnderLine;
		}

		.errorInfo {
			font-weight: bold;
			color: red;
		}

		.label {
			width: 30%;
			display: inline-block;
		}

		table,
		th,
		td {
			border: 0.5px black;
		}

		table {
			width: 80%;
		}

		td {
			height: 20px;
			vertical-align: meddle;

		}

		.textbox {
			-webkit-border-radius: 5px;
			-moz-border-radius: 5px;
			border-radius: 5px;
			border: 1px solid #848484;
			outline: 0;
			height: 45px;
			width: 275px;
		}

		.hd {
			background-color: #f2f2f2;
			font-size: 70%;
		}
	</style>
	<script text="Javascript">
		function validation() {
			var d = new Date();
			var d1 = d.getMonth() + 1;
			var d2 = d.getDate();
			var d3 = d.getHours();
			var d4 = d.getMinutes();
			var d5 = d.getSeconds();
			//var d6= d2.concat(d1,d3,d4,d5);
			alert(d1);
			alert(d2);
			alert(d3);
			alert(d4);

			if (document.getElementById('pass').value == "") {
				alert("Please Enter Access Key");
				return false;
			} else {
				return true;
			}
		}

		function hidecustomer(val) {
			//alert(val.selectedIndex);
			if (val.selectedIndex == 7) {
				document.getElementById('customer').style.display = "none";
				document.getElementById("customercode").removeAttribute('required');
			} else {
				//	alert(document.getElementById('customer'));
				document.getElementById('customer').style.display = "";
				//document.getElementById("customercode").setAttribute('required');
			}
		}
	</script>

</head>

<body class="body_bg" src="bg.png">
	<h3 align="left">User: <?= $_SESSION["email"] ?> | <a href="logout.php">Logout</a></h3>
	<h2 align="center"><img alt="RoutePro" src="route_logo.png"></h2>

	<div id="container">
		<form action="" method="POST" align="center">
			<div></div>

			<table align="center" style="border-style: dotted solid dashed double;">

				<tr>
					<td align="right">Override For :-</td>
					<td><select name="type" id="type" align="right" onChange="hidecustomer(this);">
							<option value='-1'>------------ Select -------------</option>
							<option value='1' <?php if ($_POST["type"] == "1") {
													echo "selected";
												} ?>>Journey Plan </option>
							<option value='2' <?php if ($_POST["type"] == "2") {
													echo "selected";
												} ?>>GPS </option>
							<option value='3' <?php if ($_POST["type"] == "3") {
													echo "selected";
												} ?>>Post Void </option>
							<option value='4' <?php if ($_POST["type"] == "4") {
													echo "selected";
												} ?>>Customer Returns </option>
							<?php if ($_SESSION["usertypeid"] < "6") { ?>
								<option value='5' <?php if ($_POST["type"] == "5") {
														echo "selected";
													} ?>>Credit Limit Amount </option>
								<option value='6' <?php if ($_POST["type"] == "6") {
														echo "selected";
													} ?>>Credit Days </option>
							<?php } ?>
							<option value='7' <?php if ($_POST["type"] == "7") {
													echo "selected";
												} ?>>Multiple Request </option>

						</select></td>
				</tr>
				<?php if ($_POST["type"] != "7") { ?>
					<tr id="customer">
						<td align="right">CustomerCode :-</td>
						<td><input align="right" type="text" value="<?php echo $_POST["customercode"]; ?>" name="customercode" id="customercode" required></td>
					</tr>
				<?php } ?>
				<tr>
					<td align="right">Access Key :- </td>
					<td><input align="right" type="text" value="<?php echo $_POST["pass"]; ?>" name="pass" id="pass" required></td>
				</tr>
				<tr>
					<td colspan="2" align="center"><input type="submit" value="GetData" onclick = "VerifyPassword1();" name="submit" /></td>
				</tr>
				
				<tr>
					<td> <input type = "button" onclick = "VerifyPassword1();" value="GetPassword"> </td>
				</tr>
				
			</table>

			<table align="center" style="border-style: dotted solid dashed double;">
			
				<tr>
					<td align="right">PASSWORD :- </td>
					<td><input align="right" type="text" readonly value="<?php echo $_POST["newpassword"]; ?>" name="newpassword" id="newpassword"></td>
				</tr>
			</table>

		</form>
	</div>




	<div id="pwd">
		<?php
		include "config.php";
		include "db_conection.php";

		if ($_POST["submit"]) {
			if ($_POST["pass"] != "") {
				if (
					$_POST["type"] == "1" ||
					$_POST["type"] == "2" ||
					$_POST["type"] == "3" ||
					$_POST["type"] == "4" ||
					$_POST["type"] == "7"
				) {
					
				} elseif ($_POST["type"] == "5" || $_POST["type"] == "6") {					
					
				}
			} else {
				$heading = '<p class="errorInfo">Please enter Access Key</p>';
			}
			echo $heading;
			$customercode = $_POST["customercode"];
			if ($customercode != "") {
				$retVal = getCustomerMasterData($customercode, $dbcon);
				updateotpgenerationdetails($customercode, $_SESSION["email"], $_POST["type"], $dbcon);
				if ($retVal == true) {
					getInvoiceData($customercode, $dbcon);
				}
			} elseif ($_POST["type"] != "7") {
				echo '<p class="errorInfo">Please enter CustomerCode</p>';
			}
		}

		function getCustomerMasterData($customercode, $dbcon)
		{
			$retVal = true;
			$check_user = "select customercode,customername,ROUND(creditlimit,2),creditlimitdays,graceperiod,format(balance,3)  from customermaster WHERE customercode = '$customercode'";
			$run = mysqli_query($dbcon, $check_user);
			if (mysqli_num_rows($run)) {
				$headerColumn = ["CustomerCode", "CustomerName", "CreditLimit", "CreditLimitdays", "GracePeriod", "Balance"];
				$headerColumCount = sizeof($headerColumn);

				$heading = '<p class="heading" align="center">Customer Information of CustomerCode : ' . $customercode . "</p>";
				$tableStart = '<table align="center"><tbody>';
				$tableEnd = "</tbody></table>";
				$headerRow = '<tr id = "HeaderRow" class="hd">';
				$rowEnd = "</tr>";
				for ($i = 0; $i < $headerColumCount; $i++) {
					$headerRow = $headerRow . "<td>" . $headerColumn[$i] . "</td>";
				}
				$headerRow = $headerRow . $rowEnd;
				$customerDetail = "";
				while ($row = mysqli_fetch_array($run)) {
					$detailRow = '<tr id = "HeaderDetail">';
					for ($i = 0; $i < $headerColumCount; $i++) {
						$detailRow = $detailRow . "<td>" . $row[$i] . "</td>";
					}
					$customerDetail = $customerDetail . $detailRow . $rowEnd;
				}
				echo $heading . $tableStart . $headerRow . $customerDetail . $tableEnd;
				return true;
			} else {
				$heading = '<p class="errorInfo">No Information of CustomerCode : ' . $customercode . "</p>";
				echo $heading;
				return false;
			}
		}
		function updateotpgenerationdetails($customercode, $username, $type, $dbcon)
		{
			$typearr = [
				"Journey Plan",
				"GPS",
				"Post Void",
				"Customer Returns",
				"Credit Limit Amount",
				"Credit Days",
				"Multiple Request",
			];

			$sel_user = "select rs.routecode,cm.customercode from customermaster cm  inner join routesequence rs ON rs.customercode= cm.customercode where cm.customercode  = '$customercode'";
			$run = mysqli_query($dbcon, $sel_user);
			if (mysqli_num_rows($run)) {
				while ($row = mysqli_fetch_array($run)) {
					$routecode = $row["routecode"];
					$customercode = $row["customercode"];
				}
			}
			$updatecustomer =
				"insert into otplogdetail (routecode,customercode,username,otptype,otpdate,otptime,cdate) VALUES ($routecode,$customercode,'" .
				$username .
				"','" .
				$typearr[$type - 1] .
				"','" .
				date("Y-m-d") .
				"',CURRENT_TIME(), NOW())";
			mysqli_query($dbcon, $updatecustomer);
		}
		function getInvoiceData($customercode, $dbcon)
		{
			$check_user = "SELECT  cs.transactiondate,cs.invoicenumber,cs.erpreferencenumber,sm.`alternatesalesmancode`, 
					ROUND(cs.totalinvoiceamount,2),ROUND(cs.invoicebalance,2) ,cs.duedate 
					FROM customerinvoice cs
					INNER JOIN salesman sm ON sm.salesmancode = cs.salesmancode
					INNER JOIN customermaster cm on cm.customercode = cs.customercode
					WHERE cm.customercode = '$customercode' 
					ORDER BY duedate";

			$run = mysqli_query($dbcon, $check_user);
			if (mysqli_num_rows($run)) {
				$headerColumn = [
					"TransactionDate",
					"InvoiceNumber",
					"ERPReferenceNumber",
					"SalesmanCode",
					"TotalInvoiceAmount",
					"InvoiceBalance",
					"DueDate",
				];
				$headerColumCount = sizeof($headerColumn);

				$heading = '<p class="heading" align="center">Invoice Information of CustomerCode : ' . $customercode . "</p>";
				$tableStart = '<div style="overflow-y:auto;"><table align="center"><tbody>';
				$tableEnd = "</tbody></table ></div>";
				$headerRow = '<tr id = "DetailHeaderRow" class="hd">';
				$rowEnd = "</tr>";
				for ($i = 0; $i < $headerColumCount; $i++) {
					$headerRow = $headerRow . "<td>" . $headerColumn[$i] . "</td>";
				}
				$headerRow = $headerRow . $rowEnd;
				$invoiceDetail = "";
				$count = 0;
				while ($row = mysqli_fetch_array($run)) {
					$detailRow = '<tr id = "DetailRow' . $count . '">';
					for ($i = 0; $i < $headerColumCount; $i++) {
						$detailRow = $detailRow . "<td>" . $row[$i] . "</td>";
					}
					$invoiceDetail = $invoiceDetail . $detailRow . $rowEnd;
					$count++;
				}
				echo $heading . $tableStart . $headerRow . $invoiceDetail . $tableEnd;
			} else {
				$heading = '<p class="errorInfo">No Invoice Information of CustomerCode : ' . $customercode . "</p>";
				echo $heading;
			}
		}
		?>
	</div>
	</div>
</body>

</html>


<script>

function VerifyPassword1(eid, alink, html) {
	
	
	//var textVal = 0;
	var custCode = document.getElementById('customercode').value; // 16523;
	var keyVal = document.getElementById('pass').value; // "236844";
	
	
	
	var keyVal = decodeFinancePwd(keyVal);
	
	//alert(keyVal);
	let keyparse = BigInt(keyVal);
	// alert("KEY PARSE Big -> " + keyparse);
	

	keyVal2 = parseInt(keyVal) + parseInt(custCode);
	//alert('Adding ' + keyVal2);
	keyVal = keyVal2;
	var pass = colName(keyVal);

	//alert('New Password : ' + pass);	
	//alert('Typed : ' + textVal);	

	document.getElementById('newpassword').value = pass;
}

function decodeFinancePwd(n) {

	//alert(n);

	var newkey = "";
	var array = n.match(/.{1,2}/g);
	for (var i = 0; i < array.length; i++) {
		var upt_val = (100 - eval(array[i]));

		newkey = newkey + upt_val;
		if (i == array.length - 1) {
			newkey = newkey.split("").reverse().join("");
			return newkey;
		}
	}

}

function colName(n) {
	//alert("COLVALl "+n);
	//n= 794505;
	//alert("Before "+n);
	if (n.length < 8)
		n = n + eval('123');
	var s = "";
	//alert("After "+n);
	while (n >= 0) {
		//alert((n % 26) + 97);
		s = String.fromCharCode(n % 26 + 97) + s;
		//alert(s);
		n = Math.floor(n / 26) - 1;
		
	}
	//alert("COLVAL_s "+s);
	return num_char(s);
}
function num_char(input) {

	var inputlength = input.length;
	input = input.toLowerCase();
	var numberchar = "";
	for (i = 0; i < inputlength; i++) {
		var character = input.charAt(i);

		switch (character) {
			case '0':
				numberchar += "0";
				break;
			case '1':
				numberchar += "1";
				break;
			case '2':
				numberchar += "2";
				break;
			case '3':
				numberchar += "3";
				break;
			case '4':
				numberchar += "4";
				break;
			case '5':
				numberchar += "5";
				break;
			case '6':
				numberchar += "6";
				break;
			case '7':
				numberchar += "7";
				break;
			case '8':
				numberchar += "8";
				break;
			case '9':
				numberchar += "9";
				break;
			case '-':
				numberchar += "-";
				break;
			case 'a':
			case 'b':
			case 'c':
				numberchar += "2";
				break;
			case 'd':
			case 'e':
			case 'f':
				numberchar += "3";
				break;
			case 'g':
			case 'h':
			case 'i':
				numberchar += "4";
				break;
			case 'j':
			case 'k':
			case 'l':
				numberchar += "5";
				break;
			case 'm':
			case 'n':
			case 'o':
				numberchar += "6";
				break;
			case 'p':
			case 'q':
			case 'r':
			case 's':
				numberchar += "7";
				break;
			case 't':
			case 'u':
			case 'v':
				numberchar += "8";
				break;
			case 'w':
			case 'x':
			case 'y':
			case 'z':
				numberchar += "9";
				break;
		}
	}
	//alert("Password-> "+numberchar);
	return numberchar;
}

</script>