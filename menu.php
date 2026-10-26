<?php
function redirectTo($url)
{
	if (!headers_sent()) {
		header('location:'.$url);
		exit;
	} else {
		print '<script type="text/javascript">';
		print 'window.location.href="'.$url.'";';
		print '</script>';
		exit;
	}
	
}

if(empty($_SESSION['Username']))
{
	redirectTo('login.php');
}


?>

<title>Doyel Eng. | <?php echo $_SESSION['Username'] ; ?></title>
</head>
<body>
<div align="center" class="menu">
<a href="index.php">Dashboard</a> 
<a href="Sales.php">Sales</a>
<a href="purchase.php">Add to Stock</a>
<a href="list_of_item.php">Product List</a>
<a href="list_of_member.php">Customer List</a>
<a href="Payment.php">Payment</a>
<a href="Report.php">Report</a>
<a href="Accounts.php">Accounts</a>
<a href="logout.php">Logout</a>
</div>