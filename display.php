<?php


require_once('db.inc.php');



if (array_key_exists('method',$_POST) && is_numeric($_POST['method']) && $_POST['method'])
{
    $buysell="buy";
}
else
{
    $buysell="sell";
}


$regionid=10000002;
if (array_key_exists('region',$_POST) && is_numeric($_POST['region']))
{
    $regionid=$_POST['region'];
}
else if (array_key_exists('region',$_GET) &&is_numeric($_GET['region']))
{
    $regionid=$_GET['region'];
}

if ($regionid==10000002)
{
    $region="forge";
}
else
{
    $region=$regionid;
}



if (array_key_exists('entries',$_POST))
{
    $entries=explode("\n",$_POST['entries']);
}
else
{
    if (array_key_exists('entries',$_GET))
    {
        $entries=explode("\n",$_GET['entries']);
    }
    else
    {
        echo "No Entries provided";
        exit;
    }
}


$pricetype='redis';
#$pricetype='memcache';
#$pricetype='marketdata';

require_once($pricetype.'price.php');

$sql='select typename,typeid from invTypes where invTypes.published=1 and marketgroupid is not null';

$stmt = $dbh->prepare($sql);

$stmt->execute();
$typeidlookup=array();
while ($row = $stmt->fetchObject()){
$typeidlookup[$row->typename]=$row->typeid;
}


$inventory=array();

foreach ($entries as $entry)
{
   if (preg_match("/^(\d+) (.*)$/",trim($entry),$matches))
   {
       if(isset($typeidlookup[$matches[2]]))
       {
           if(isset($inventory[$typeidlookup[$matches[2]]]))
           {
               $inventory[$typeidlookup[$matches[2]]]+=$matches[1];
           }
           else
           {
               $inventory[$typeidlookup[$matches[2]]]=$matches[1];
           }
       }
    }
   else if (preg_match("/^(.*)\t([\d.,]+)\t/",trim($entry),$matches))
   {
       if(isset($typeidlookup[$matches[1]]))
       {


           $quantity=str_replace(',','',str_replace(',','',$matches[2]));
           if(isset($inventory[$typeidlookup[$matches[1]]]))
           {
               $inventory[$typeidlookup[$matches[1]]]+=$quantity;
           }
           else
           {
               $inventory[$typeidlookup[$matches[1]]]=$quantity;
           }
       }
    }
    else if (preg_match("/^\[(.*),.*]/",trim($entry),$matches))
   {
       if(isset($typeidlookup[$matches[1]]))
       {
           $quantity=1;
           if(isset($inventory[$typeidlookup[$matches[1]]]))
           {
               $inventory[$typeidlookup[$matches[1]]]+=$quantity;
           }
           else
           {
               $inventory[$typeidlookup[$matches[1]]]=$quantity;
           }
       }
    }
    else if (preg_match("/^(.*), Qty: (\d+)/",trim($entry),$matches))
   {
       if(isset($typeidlookup[$matches[1]]))
       {
           $quantity=$matches[2];
           if(isset($inventory[$typeidlookup[$matches[1]]]))
           {
               $inventory[$typeidlookup[$matches[1]]]+=$quantity;
           }
           else
           {
               $inventory[$typeidlookup[$matches[1]]]=$quantity;
           }
       }
    }
    else if (preg_match("/^.*\t(.*)\t.*/",trim($entry),$matches))
   {
       if(isset($typeidlookup[$matches[1]]))
       {
           $quantity=1;
           if(isset($inventory[$typeidlookup[$matches[1]]]))
           {
               $inventory[$typeidlookup[$matches[1]]]+=$quantity;
           }
           else
           {
               $inventory[$typeidlookup[$matches[1]]]=$quantity;
           }
       }
    }

    else if (preg_match("/^(.*)/",trim($entry),$matches))
   {
       if(isset($typeidlookup[$matches[1]]))
       {
           $quantity=1;
           if(isset($inventory[$typeidlookup[$matches[1]]]))
           {
               $inventory[$typeidlookup[$matches[1]]]+=$quantity;
           }
           else
           {
               $inventory[$typeidlookup[$matches[1]]]=$quantity;
           }
       }
    }







}











?>
<html>
<head>
<title>Evaluator</title>
  <link href="//ajax.googleapis.com/ajax/libs/jqueryui/1.10.3/themes/base/jquery-ui.css" rel="stylesheet" type="text/css"/>
  <script src="//ajax.googleapis.com/ajax/libs/jquery/1.10.2/jquery.min.js"></script>
  <script src="//ajax.googleapis.com/ajax/libs/jqueryui/1.10.3/jquery-ui.min.js"></script>

  <link href="//ajax.aspnetcdn.com/ajax/jquery.dataTables/1.9.4/css/jquery.dataTables.css" rel="stylesheet" type="text/css"/>
  <script type="text/javascript" src="//ajax.aspnetcdn.com/ajax/jquery.dataTables/1.9.4/jquery.dataTables.min.js"></script>
<script>
jQuery.extend( jQuery.fn.dataTableExt.oSort, {
    "currency-pre": function ( a ) {
        a = (a==="-") ? 0 : a.replace( /[^\d\-\.]/g, "" );
        return parseFloat( a );
    },
 
    "currency-asc": function ( a, b ) {
        return a - b;
    },
 
    "currency-desc": function ( a, b ) {
        return b - a;
    }
} );


$(document).ready(function()
    {
        var oTable = $("#evaluation").dataTable({
           "bPaginate": false,
            "bFilter": false,
            "bInfo": false,
            "bAutoWidth": false,
            "aoColumns":[null,null,{ "sType": "currency" },{ "sType": "currency" },{ "sType": "currency" },{ "sType": "currency" },{ "sType": "currency" }]
});
    }
);
</script>

<?php include('/home/web/fuzzwork/htdocs/bootstrap/header.php'); ?>
</head>
<body>
<?php include('/home/web/fuzzwork/htdocs/menu/menubootstrap.php'); ?>
<div class="container">




<table border=1 id="evaluation" class="tablesorter">
<thead>
<tr><th>id</th><th>Name</th><th>Quantity</th><th>Volume</th><th>ISK/m3</th><th>PPU</th><th>total value</th></tr>
</thead>
<tbody>
<?
$sql='select typename,typeid,volume from invTypes where typeid in ('.join(",",array_keys($inventory)).') order by typename desc';

$stmt = $dbh->prepare($sql);

$stmt->execute();
$total=0;
$totalvolume=0;
while ($row = $stmt->fetchObject()){
list($price,$buyprice)=returnprice($row->typeid,$region);
if ($buysell=='buy'){$price=$buyprice;}
echo "<tr><td>".$row->typeid."</td><td>".$row->typename."</td><td align=right>".number_format($inventory[$row->typeid])."</td><td align=right>".number_format($row->volume*$inventory[$row->typeid],2)."</td><td align=right>".number_format($price/$row->volume,2)."</td><td align=right>".number_format($price,2)."</td><td align=right>".number_format($inventory[$row->typeid]*$price,2)."</td></tr>";
$total+=$inventory[$row->typeid]*$price;
$totalvolume+=$row->volume*$inventory[$row->typeid];
}



?>

</tbody>
<tfoot>
<tr><th colspan=2>Totals</th><th></th><th><? echo number_format($totalvolume,2);?></th><th colspan=2></th><th><? echo number_format($total,2);?></th></tr>
</tfoot>
</table>

</div>
<?php include('/home/web/fuzzwork/htdocs/bootstrap/footer.php'); ?>

</body>
</html>

