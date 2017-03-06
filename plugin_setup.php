<?php


include_once "/opt/fpp/www/common.php";
include_once 'functions.inc.php';
include_once 'commonFunctions.inc.php';
$pluginName = "TwitterEvent";

$pluginVersion ="1.1";
$PLAYLIST_NAME="";
$MAJOR = "98";
$MINOR = "01";
$eventExtension = ".fevt";

//$Plugin_DBName = "/tmp/FPP.".$pluginName.".db";
$Plugin_DBName = $settings['configDirectory']."/FPP.".$pluginName.".db";

//$DB_NAME = $settings['pluginData']."/FPP.".$pluginName.".db";

//2.9 - Dec 27 2016 - SqlLite integration

//2.8 - Dec 2 2016 - do not add a message to the queue if it is profanity for those running not in immeidate mode it could send that out

//2.7 - Dec 2 2016 - Added more checking and message queue file managment
//2.6 - Dec 2 2016 - Profanity Threshold added and the ability to add your own reply text for the message your message contains profanity
//2.5 - Dec 2 2016 - added blacklist

//2.4 - Dec 2  2016 - Profanity fixes

//2.3 - Nov 30 2016 - Add default commands to new install

//2.2 - Nov 30 2016 - remove the /usr/bin/php from the header of TSMS.php - causing debugger errors on Twilio side. May need to 
//investigate providing a proper response XML to Twilio as well.


//arg0 is  the program
//arg1 is the first argument in the registration this will be --list
//$DEBUG=true;

$SMSEventFile = $eventDirectory."/".$MAJOR."_".$MINOR.$eventExtension;
$SMSGETScriptFilename = $scriptDirectory."/".$pluginName."_GET.sh";

$messageQueue_Plugin = "MessageQueue";
$MESSAGE_QUEUE_PLUGIN_ENABLED=false;


$logFile = $settings['logDirectory']."/".$pluginName.".log";



$messageQueuePluginPath = $settings['pluginDirectory']."/".$messageQueue_Plugin."/";

$messageQueueFile = urldecode(ReadSettingFromFile("MESSAGE_FILE",$messageQueue_Plugin));

if(file_exists($messageQueuePluginPath."functions.inc.php"))
{
	include $messageQueuePluginPath."functions.inc.php";
	$MESSAGE_QUEUE_PLUGIN_ENABLED=true;

} else {
	logEntry("Message Queue Plugin not installed, some features will be disabled");
}


$gitURL = "https://github.com/LightsOnHudson/FPP-Plugin-TwitterEvent.git";


$pluginUpdateFile = $settings['pluginDirectory']."/".$pluginName."/"."pluginUpdate.inc";


logEntry("plugin update file: ".$pluginUpdateFile);


if(isset($_POST['updatePlugin']))
{
	$updateResult = updatePluginFromGitHub($gitURL, $branch="master", $pluginName);

	echo $updateResult."<br/> \n";
}

if(isset($_POST['TWITTERSTREAM'])) {
	$TWITTER_STREAM_CMD = $_POST['TWITTERSTREAM'];
	//echo "Twite stream commdnad: ".$TWITTER_STREAM_CMD."<br/> \n";
	
	switch ($TWITTER_STREAM_CMD) {
		
		
		case "START":
			$CMD = "nohup /usr/bin/php -f /home/fpp/media/plugins/TwitterEvent/userstream-simple.php > /dev/null 2>&1 &";
			
			
			//$CMD = "/usr/bin/php /home/fpp/media/plugins/TwitterEvent/userstream-simple.php";
			logEntry("Start STream cmd:: ".$CMD);
			exec($CMD);
			//$forkResult = forkExec($CMD);
			//$output = shell_exec($CMD);
			
			
		break;
		
		case "STOP":
			$CMD = "/bin/ps aux | /bin/grep '/usr/bin/php -f /home/fpp/media/plugins/TwitterEvent/userstream-simple.php'";
	logEntry("is stream running cmd: ".$CMD);
	
	
	
	$output = shell_exec($CMD);
	
	$processRunning = array();
	$processRunning = explode("\n",$output);
	
	$pid = array();
	
	$processRunning[0] = trim(preg_replace('/\s\s+/', ' ', str_replace("\n", " ", $processRunning[0])));
	$pid = explode(" ",$processRunning[0]);
	
	//	echo "PID 0: ".$pid[0]."<br/> \n";
	//	echo "PID 1: ".$pid[1]."<br/> \n";
	
	//kill the first one
	$KILL_CMD = "/bin/kill ".$pid[1];
	logEntry("kill cmd: ".$KILL_CMD);
	
	shell_exec($KILL_CMD);
			
		break;
		
	}
}

if(isset($_POST['submit']))
{
	


//	echo "Writring config fie <br/> \n";

	WriteSettingToFile("LAST_READ",urlencode($_POST["LAST_READ"]),$pluginName);
	WriteSettingToFile("API_USER_ID",urlencode($_POST["API_USER_ID"]),$pluginName);
	WriteSettingToFile("API_KEY",urlencode($_POST["API_KEY"]),$pluginName);
	//WriteSettingToFile("IMMEDIATE_OUTPUT",urlencode($_POST["IMMEDIATE_OUTPUT"]),$pluginName);
	WriteSettingToFile("MATRIX_LOCATION",urlencode($_POST["MATRIX_LOCATION"]),$pluginName);
	
	WriteSettingToFile("PROFANITY_ENGINE",urlencode($_POST["PROFANITY_ENGINE"]),$pluginName);
	


	
	WriteSettingToFile("REMOTE_FPP_IP",urlencode($_POST["REMOTE_FPP_IP"]),$pluginName);
	
	WriteSettingToFile("MATRIX_MODE",urlencode($_POST["MATRIX_MODE"]),$pluginName);
	WriteSettingToFile("NAMES_PRE_TEXT",urlencode($_POST["NAMES_PRE_TEXT"]),$pluginName);
	WriteSettingToFile("oauth_access_token",urlencode($_POST["oauth_access_token"]),$pluginName);
	WriteSettingToFile("oauth_access_token_secret",urlencode($_POST["oauth_access_token_secret"]),$pluginName);
	WriteSettingToFile("consumer_key",urlencode($_POST["consumer_key"]),$pluginName);
	WriteSettingToFile("consumer_secret",urlencode($_POST["consumer_secret"]),$pluginName);
	
}

	

	$DEBUG = urldecode($pluginSettings['DEBUG']);
	
	
	$MATRIX_MODE = urldecode($pluginSettings['MATRIX_MODE']);
	
	$NAMES_PRE_TEXT = urldecode($pluginSettings['NAMES_PRE_TEXT']);
	
	


$Plugin_DBName = $settings['configDirectory']."/FPP.".$pluginName.".db";

//echo "PLUGIN DB:NAME: ".$Plugin_DBName;

$db = new SQLite3($Plugin_DBName) or die('Unable to open database');

//create the default tables if they do not exist!
createTwitterTables($db);


	
	if((int)$LAST_READ == 0 || $LAST_READ == "") {
		$LAST_READ=0;
	}

?>

<html>
<head>
</head>

<div id="TwitterEvent" class="settings">
<fieldset>
<legend><?php echo $pluginName." Version: ".$pluginVersion;?> Status</legend>



<form method="post" action="http://<? echo $_SERVER['SERVER_ADDR'];?>/plugin.php?plugin=<?echo $pluginName;?>&page=plugin_setup.php">


<?




//check to see if the streamer is running
isTwitterStreamRunning();

function isTwitterStreamRunning() {
	
	
	$CMD = "/bin/ps aux | /bin/grep '/usr/bin/php -f /home/fpp/media/plugins/TwitterEvent/userstream-simple.php'";
	logEntry("is stream running cmd: ".$CMD);
	
	$output = shell_exec($CMD);
	
	//echo "<pre> \n";
	//echo $output;
	//echo "</pre> \n";
	
	$processRunning = array();
	$processRunning = explode("\n",$output);
	
	$pid = array();
	
	//echo "count: ".count($processRunning);
	//echo "process 0 :".$processRunning[0]. "<br/> \n";
	//if the count is 3 then the process is running!!!
	//need to get a better checker for this
	echo "<table> \n";
	echo "<tr> \n";
	
	if(count($processRunning) == 4) {
		echo "<td bgcolor=\"green\"> \n";
		echo "Twitter User Stream is running <br/> \n";
		echo "<td> \n";
		echo "<input type=\"submit\" name=\"TWITTERSTREAM\" value=\"STOP\"> \n";
		echo "</td> \n";
	} else {
		echo "<td bgcolor=\"red\"> \n";
		echo "Twitter User Stream is not running <br/>\n";
		echo "<td> \n";
		echo "<input type=\"submit\" name=\"TWITTERSTREAM\" value=\"START\"> \n";
		echo "</td> \n";
	}
	echo "</tr> \n";
	echo "</table> \n";
	//for($x=0;$x<=count($processRunning);$x++) {
		
	//	echo "process ".$x." ".$processRunning[$x]."<br/> \n";
		
		
	//}
}
?>
</form>




<p>To report a bug, please file it against the project on Git: <? echo $gitURL;?> 
</fieldset>
</div>
<br />
</html>
