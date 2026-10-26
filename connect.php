<?php
        define('HOST', 'localhost' ) ;
		define('USER', 'root' ) ;
		define('PASSWORD', '' ) ;
		define('DB_NAME', 'software' ) ;
		
        $conn=mysql_connect(HOST, USER,PASSWORD) or die ("Couldn't Connect to Database Server");
        mysql_select_db(DB_NAME, $conn) or die ("Couldn't Select Database");
			
?>