var xmlhttp;

function showPrice(str,str2)
{
xmlhttp=GetXmlHttpObject();
if (xmlhttp==null)
  {
  alert ("Browser does not support HTTP Request");
  return;
  }
var url="dictionary/select.php";
url=url+"?q="+str;
url=url+"&sid="+Math.random();

var intype=str2;
if(intype=='Pur'){
 xmlhttp.onreadystatechange=stateChanged_pur;   
}else{
 xmlhttp.onreadystatechange=stateChanged_sales;   
}

xmlhttp.open("GET",url,true);
xmlhttp.send(null);
}

function stateChanged_pur()
{
if (xmlhttp.readyState==4)
{
//document.getElementById("txtHint").innerHTML=xmlhttp.responseText;
var value=xmlhttp.responseText;

var value1 = value.split("/");
var ItemID = value1[0];
var rate = value1[2];

//purchage
document.dm.rate.value=rate;
document.dm.ItemID.value=ItemID;
//var amt=qnty*rate;
document.dm.qnty.value=1;
document.dm.amount.value=rate;

document.dm.rate.focus();
document.dm.rate.select();

}
}


function stateChanged_sales()
{
if (xmlhttp.readyState==4)
{
//document.getElementById("txtHint").innerHTML=xmlhttp.responseText;
var value=xmlhttp.responseText;

var value1 = value.split("/");
var ItemID = value1[0];
var TQnty = value1[1];
var PurAmt = value1[2];
var rate = value1[3];


//salse
document.dm.rate.value=rate;
document.dm.PurAmt.value=PurAmt;
document.dm.ItemID.value=ItemID;
document.dm.TQnty.value=TQnty;
document.dm.qnty.value=1;

//var amt=qnty*rate;
document.dm.amount.value=rate;
document.dm.qnty.focus();
document.dm.qnty.select();

}
}


function GetXmlHttpObject()
{
if (window.XMLHttpRequest)
  {
  // code for IE7+, Firefox, Chrome, Opera, Safari
  return new XMLHttpRequest();
  }
if (window.ActiveXObject)
  {
  // code for IE6, IE5
  return new ActiveXObject("Microsoft.XMLHTTP");
  }
return null;
}

//############################################################

