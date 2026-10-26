<?php
include '../../connect.php';

//if (isset($_POST['search_term']) == TRUE && empty($_POST['search_term']) == FALSE) {

            
    $search_term = $_POST['search_term'];

    $query = mysql_query("SELECT ItemID, ItemName FROM canteen_item WHERE ItemName LIKE '%$search_term%' LIMIT 10");
    while (($row = mysql_fetch_assoc($query))) {
        //echo '<li>'. $row['ItemName']. '</li><li>'. $row['ItemID']. '</li>';
		echo '<tr><td class="re">'. $row['ItemName']. '</td><td>'. $row['ItemID']. '</td></tr>';
    }
//}
?>