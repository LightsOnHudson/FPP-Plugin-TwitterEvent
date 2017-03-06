#!/usr/bin/php
<?
//error_reporting(0);
//
//Version 1 for release
$pluginName ="TwilioControl";
$myPid = getmypid();

$messageQueue_Plugin = "MessageQueue";
$MESSAGE_QUEUE_PLUGIN_ENABLED=false;



$skipJSsettings = 1;
include_once("/opt/fpp/www/config.php");
include_once("/opt/fpp/www/common.php");
include_once("functions.inc.php");
include_once("commonFunctions.inc.php");
include_once("profanity.inc.php");

// this line loads the library
//require('Twilio/Services/Twilio.php');
require ('Twilio/autoload.php');



$logFile = $settings['logDirectory']."/".$pluginName.".log";



$pluginConfigFile = $settings['configDirectory'] . "/plugin." .$pluginName;
if (file_exists($pluginConfigFile))
	$pluginSettings = parse_ini_file($pluginConfigFile);

	$logFile = $settings['logDirectory']."/".$pluginName.".log";
	$DEBUG=urldecode($pluginSettings['DEBUG']);
	

	$WHITELIST_NUMBERS = urldecode($pluginSettings['WHITELIST_NUMBERS']);
	$CONTROL_NUMBERS = urldecode($pluginSettings['CONTROL_NUMBERS']);
	

	$TSMS_account_sid = urldecode($pluginSettings['TSMS_ACCOUNT_SID']);
	$TSMS_auth_token = urldecode($pluginSettings['TSMS_AUTH_TOKEN']);
	$TSMS_phoneNumber = urldecode($pluginSettings['TSMS_PHONE_NUMBER']);
	
	$playCommands = urldecode($pluginSettings['PLAY_COMMANDS']);
	$stopCommands = urldecode($pluginSettings['STOP_COMMANDS']);
	$repeatCommands = urldecode($pluginSettings['REPEAT_COMMANDS']);
	$statusCommands = urldecode($pluginSettings['STATUS_COMMANDS']);
	
	$REMOTE_FPP_ENABLED = urldecode($pluginSettings['REMOTE_FPP_ENABLED']);
	$REMOTE_FPP_IP = urldecode($pluginSettings['REMOTE_FPP_IP']);
	
	
	$ENABLED = urldecode($pluginSettings['ENABLED']);
	
	if($DEBUG) {
		//echo "this plugin enabled status is: ".$ENABLED;
		logEntry("This plugin enabled status is: ".$ENABLED);
		
	}

	$TSMS_from = "";
	$TSMS_body = "";
	
	foreach($CONTROL_NUMBERS as $NOTIFY_NUMBER) {
		$TSMS_from = $NOTIFY_NUMBER;
		sendTSMSMessage($messageText);
	}
	
	WriteSettingToFile("ENABLED",urlencode("ON"),$pluginName);
	
	?>