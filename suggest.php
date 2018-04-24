<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE,HEAD, OPTIONS');
include 'SpellCorrector.php';
$q=$_GET["q"];
$q = strtolower($q);
$arr=explode(" ", $q);
$count=count($arr);
foreach($arr as $word){
	$new_query=$new_query.SpellCorrector::correct($word)."";
}
$url="http://localhost:8983/solr/myexample/suggest?wt=json&indent=true&q=".$arr[$count-1];
$json=file_get_contents($url);
$obj = json_decode($json,true);
$obj["correction"]["term"] = $new_query;
$res = json_encode($obj);
echo $res;
?>