<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<html>
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
        <title>Age Calculatiton</title>
                <script language="JavaScript" src="CSS/calendar_eu.js"></script>
		<link rel="stylesheet" href="CSS/calendar.css">
                <style type="text/css">
body {
    width: 600px;
    margin-left:auto;
    margin-right:auto;
}

#main {
    width: 600px;
    margin-top: 10px;
    padding: 6px;
    border: 1px solid #CCC;
    background-color: #fff;
    border:2px solid aqua;
}

.msg{
    font-weight: bold;
    color: red;
    font-size: 25px;
}

.hed{
    font-weight: bold;
    color: green;
    font-size: 35px;
  }

  hr{
      color: #CCC;
       
}
                </style>
    </head>
    <body>
        <div align="center"><div id="main">
                <div class="hed">Date Calculator</div><hr/>

        <?php

        if (!empty ($_POST['start_date']) && !empty ($_POST['end_date'])){

            $startdate= $_POST['start_date']; $enddate=$_POST['end_date'];
            GetAge($startdate,$enddate);
        }

        ?>


                
        <form action="index.php" method="POST" name="form" >
            <table cellpadding="0" cellspacing="20" border="0">
                <tr><td>
                        Start Date </td><td> <input name="start_date" type="text" id="start_date"  value="<?php echo $startdate ?>"/>
	<script language="JavaScript">
	new tcal ({
		// form name
		'formname': 'form',
		// input name
		'controlname': 'start_date'
	});
        </script>
                    </td></tr><tr><td>
                        End Date </td><td><input name="end_date" type="text" id="end_date" value="<?php echo $enddate ?>" />
	<script language="JavaScript">
	new tcal ({
		// form name
		'formname': 'form',
		// input name
		'controlname': 'end_date'
	});
	</script>
                    </td></tr><tr><td> </td><td>
        <input type="submit" name="submit" value="Submit" />
                    </td></tr>
            </table>
        </form>Design By <b>Moshiur</b>
            </div></div>


        
        <?php
        function GetAge($startdate,$enddate)
{

//$rest = substr("$startdate", -5, 1);

        // Explode the date into meaningful variables
        list($startDay,$startMonth,$startYear) = split('[/.-]', $startdate);
        list($endDay,$endMonth,$endYear) = split('[/.-]', $enddate);
        //list($startDay,$startMonth,$startYear) = explode("$rest", $startdate);
        //list($endDay,$endMonth,$endYear) = explode("$rest", $enddate);
        // Find the differences
        if ($endDay>=$startDay){
        $DayDiff = $endDay - $startDay;
        }else{
            $endDay=$endDay+30;
            $endMonth--;
            $DayDiff = $endDay - $startDay;
        }

        if ($endMonth>=$startMonth){
        $MonthDiff = $endMonth - $startMonth;
        }else{
            $endMonth=$endMonth+12;
            $endYear--;
            $MonthDiff = $endMonth - $startMonth;
        }
        if ($endYear>=$startYear){
        $YearDiff = $endYear - $startYear;

        echo '<div class="msg">Age are : '. $YearDiff.' Year '.$MonthDiff.' Month '.$DayDiff.' Day</div>';

        }else{
        echo '<div class="msg">Your Input Data Invalid Try Again</div>';
        }

        }
        ?>
    </body>
</html>
