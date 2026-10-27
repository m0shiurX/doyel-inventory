<?php
require __DIR__ . '/../lib/bootstrap.php';
require_login();
$FormDate = is_string($_SESSION['form_date'] ?? null) ? $_SESSION['form_date'] : date('Y-m-d') . ' 00:00:00';
$ToDate = is_string($_SESSION['to_date'] ?? null) ? $_SESSION['to_date'] : date('Y-m-d') . ' 23:59:59';
$members = db_all('SELECT c.MemberID, c.MemberName, c.mobile, c.mobile2, c.address, s.dueTotal, p.paidTotal FROM canteen_customar c LEFT JOIN (SELECT CustomerName, SUM(Total) AS dueTotal FROM canteen_sales GROUP BY CustomerName) s ON s.CustomerName = c.Nickname LEFT JOIN (SELECT Description, SUM(amount) AS paidTotal FROM canteen_collection GROUP BY Description) p ON p.Description = c.Nickname ORDER BY c.MemberID');
?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<html><head><title>List of members with dues</title><meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
<link rel="stylesheet" type="text/css" href="Report.css">
<link href="../css/Style.css" rel="stylesheet" type="text/css">
<link href="../css/lessframework4.css" rel="stylesheet" type="text/css">
<div id="main" style="width: 80%; margin: 5px auto 10px auto; padding:0;">
<div class="printb"><a href="#" onClick="javascript:window.print()">Print</a></div>
<?php include __DIR__ . '/menu.php'; ?>
<h2 align="center">List Of Members With Dues</h2>
<table width="95%" border="0" cellspacing="0" cellpadding="1" id="tableGd" style="border-spacing: 0; font-size:1em; margin-bottom:10px;">
<tr bgcolor="#f1f1f1"><td width="3%"><b>ID</b></td><td width="25%"><b>Name of Member</b></td><td width="22%"><b>Mobile</b></td><td width="40%"><b>Address</b></td><td width="10%"><b>DUE</b></td></tr>
<?php
foreach ($members as $member) {
    $due = (float)($member->dueTotal ?? 0) - (float)($member->paidTotal ?? 0);
    if ($due <= 0) continue;
    echo '<tr><td>' . e($member->MemberID) . "</td>
<td>" . e($member->MemberName) . "</td>
<td>" . e($member->mobile . ', ' . ($member->mobile2 ?? '')) . "</td>
<td>" . e($member->address) . '</td>';
    echo '<td style="text-align:right; padding-right: 5px;">' . e($due) . "</td>
</tr>";
}
?></table>
<p>&nbsp;</p>
</div></body></html>
