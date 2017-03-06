<?php
//error_reporting(0);
include_once 'functions.inc.php';
$pluginName = "twitter";
//$myPid = getmypid();
$logFile = "/tmp"."/".$pluginName.".log";

printArray($_POST);

function printArray($array){
     foreach ($array as $key => $value){
        logEntry($key. " => ".$value);
        if(is_array($value)){ //If $value is an array, print it as well!
            printArray($value);
        }  
    } 
}


?>
