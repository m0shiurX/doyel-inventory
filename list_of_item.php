<?php
require __DIR__ . '/lib/bootstrap.php';
require_login();
?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<html>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
<link href="css/Style.css" rel="stylesheet" type="text/css">
<link href="css/lessframework4.css" rel="stylesheet" type="text/css">
<script type="text/javascript" src="codes/jquery-1.5.min.js"></script>

    <div id="main">

	<?php include("menu.php"); ?>
	<h2>List of Item</h2>

	<form name="form" method="post" action="">
		<table width="100%" border="0" cellspacing="0" cellpadding="0">
	      <tr>
	        <td width="11%"><strong>Search Item &nbsp;</strong><input onKeyPress="return dg(event);" name="ItemName" type="text" id="ItemName"></td>
	        <td width="23%"><a href="add_item.php">Add New Item</a>	</td>
	      </tr>
	    </table>
	</form>


	<div  id="userGrid" class="scrollbar">
    <form id="grid_form_id">
	<table width="100%" border="0" cellspacing="1" cellpadding="1" id="tableGd">
        <tr>
			<th width="4%"><b>ID</b></th>
			<th width="4%"><b>Edit</b></th>
			<th width="20%"><b>Name of Item </b></th>
			<!-- <th width="12%"><b>Supplier Name</b></th> -->
			<th width="12%"><b>Available</b></th>
			<th width="12%"><b>Min Quantity</b></th>
			<th width="12%"><b>Max Quantity</b></th>
			<th width="12%"><b>Purchase Price</b></th>
			<th width="12%"><b>Sales Price</b></th>
		</tr>

        <?php foreach (db_all('SELECT ItemID,ItemName,Qnty,MinimumQuantity,MaximumQuantity,PurPrice,SalesPrice FROM canteen_item WHERE D=? ORDER BY ItemID',[0]) as $row): ?>
            <tr id="<?= e($row->ItemID) ?>">
                <td align="center"><?= e($row->ItemID) ?></td>
                <td><a href="add_item.php?ID=<?= e(urlencode($row->ItemID)) ?>">Edit</a></td>
                <td id="ItemName"><?= e($row->ItemName) ?></td>
                <td align="center"><?= e(fmt_numeric($row->Qnty)) ?></td>
                <td align="center"><?= e($row->MinimumQuantity) ?></td>
                <td align="center"><?= e($row->MaximumQuantity) ?></td>
                <td align="center"><?= e(fmt_numeric($row->PurPrice)) ?></td>
                <td align="center"><?= e(fmt_numeric($row->SalesPrice)) ?></td>
            </tr>
        <?php endforeach; ?>
	</table>
    </form>
	</div>
	<?php include 'footer.php'; ?>
	</div>



<!-- For Searching Products -->
<script type="text/javascript">
	$(function() {
		grid = $('#userGrid');

		// handle search fields of products key up event
		$('#ItemName').keyup(function(e) {
			text = $(this).val(); // grab search term

			if(text.length > 1) {
				grid.find('tr:has(td)').hide(); // hide data rows, leave header row showing

				// iterate through all grid rows
				grid.find('tr').each(function(i) {
					// check to see if search term matches Name column
					if($(this).find('#ItemName').text().toUpperCase().match(text.toUpperCase()))
						$(this).show(); // show matching row
				});
			}
			else
				grid.find('tr').show(); // if no matching name is found, show all rows
		});

	});
</script>
</body>
</html>
