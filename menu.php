<?php
require_once __DIR__ . '/lib/bootstrap.php';
require_login();
?>
<title>Doyel Eng. | <?= e(current_user()) ?></title>
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
<form id="logout_form" method="post" action="<?= e(app_relative_path('logout.php')) ?>" hidden><?php csrf_field(); ?></form><button type="submit" form="logout_form" class="battun">Logout</button>
</div>
