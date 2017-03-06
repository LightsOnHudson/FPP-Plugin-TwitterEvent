<?php
// error_reporting(0);
//
//
// Version 1 for release
// TwilioVersion 2.0 = Dec 27 2016 - SQLLite messages
$CONSOLE_DEBUG = false;
$pluginName = "TwitterEvent";
$TwilioVersion = "2.0";
$myPid = getmypid ();

$messageQueue_Plugin = "MessageQueue";
$MESSAGE_QUEUE_PLUGIN_ENABLED = false;

// MATRIX ACTIVE - true / false to catch more messages if they arrive
$MATRIX_ACTIVE = false;

$skipJSsettings = 1;
include_once ("/opt/fpp/www/config.php");
include_once ("/opt/fpp/www/common.php");
include_once ("functions.inc.php");
include_once ("commonFunctions.inc.php");
include_once ("profanity.inc.php");


$logFile = $settings ['logDirectory'] . "/" . $pluginName . ".log";

$messageQueuePluginPath = $pluginDirectory . "/" . $messageQueue_Plugin . "/";

$messageQueueFile = urldecode ( ReadSettingFromFile ( "MESSAGE_FILE", $messageQueue_Plugin ) );

$profanityMessageQueueFile = $settings ['configDirectory'] . "/plugin." . $pluginName . ".ProfanityQueue";

$blacklistFile = $settings ['configDirectory'] . "/plugin." . $pluginName . ".Blacklist";

if (file_exists ( $messageQueuePluginPath . "functions.inc.php" )) {
	include $messageQueuePluginPath . "functions.inc.php";
	$MESSAGE_QUEUE_PLUGIN_ENABLED = true;
} else {
	logEntry ( "Message Queue Plugin not installed, some features will be disabled" );
}

// set up DB connection
$Plugin_DBName = $settings['configDirectory']."/FPP.".$pluginName.".db";

//echo "PLUGIN DB:NAME: ".$Plugin_DBName;

$db = new SQLite3($Plugin_DBName) or die('Unable to open database');

// logEntry("DB: ".$db);

if ($db != null) {
	//create the tables if this is the first time!!!! this is also done in the plugin-setup !
	createTwitterTables ( $db );
}

require ("lock.helper.php");

define ( 'LOCK_DIR', '/tmp/' );
define ( 'LOCK_SUFFIX', $pluginName . '.lock' );

$pluginConfigFile = $settings ['configDirectory'] . "/plugin." . $pluginName;
if (file_exists ( $pluginConfigFile ))
	$pluginSettings = parse_ini_file ( $pluginConfigFile );

$logFile = $settings ['logDirectory'] . "/" . $pluginName . ".log";
$DEBUG = urldecode ( $pluginSettings ['DEBUG'] );
//$CONSOLE_DEBUG = urldecode ( $pluginSettings ['CONSOLE_DEBUG'] );

// $CONSOLE_DEBUG = true;

$MATRIX_MESSAGE_PLUGIN_NAME = "MatrixMessage";
// page name to run the matrix code to output to matrix (remote or local);
$MATRIX_EXEC_PAGE_NAME = "matrix.php";

$PLAYLIST_NAME = urldecode ( $pluginSettings ['PLAYLIST_NAME'] );
$WHITELIST_NUMBERS = urldecode ( $pluginSettings ['WHITELIST_NUMBERS'] );
$CONTROL_NUMBERS = urldecode ( $pluginSettings ['CONTROL_NUMBERS'] );
$REPLY_TEXT = urldecode ( $pluginSettings ['REPLY_TEXT'] );
$VALID_COMMANDS = urldecode ( $pluginSettings ['VALID_COMMANDS'] );
$IMMEDIATE_OUTPUT = urldecode ( $pluginSettings ['IMMEDIATE_OUTPUT'] );
$MATRIX_LOCATION = urldecode ( $pluginSettings ['MATRIX_LOCATION'] );
$API_KEY = urldecode ( $pluginSettings ['API_KEY'] );
$API_USER_ID = urldecode ( $pluginSettings ['API_USER_ID'] );
$PROFANITY_ENGINE = urldecode ( $pluginSettings ['PROFANITY_ENGINE'] );

$TSMS_account_sid = urldecode ( $pluginSettings ['TSMS_ACCOUNT_SID'] );
$TSMS_auth_token = urldecode ( $pluginSettings ['TSMS_AUTH_TOKEN'] );
$TSMS_phoneNumber = urldecode ( $pluginSettings ['TSMS_PHONE_NUMBER'] );

$playCommands = urldecode ( $pluginSettings ['PLAY_COMMANDS'] );
$stopCommands = urldecode ( $pluginSettings ['STOP_COMMANDS'] );
$repeatCommands = urldecode ( $pluginSettings ['REPEAT_COMMANDS'] );
$statusCommands = urldecode ( $pluginSettings ['STATUS_COMMANDS'] );

$REMOTE_FPP_ENABLED = urldecode ( $pluginSettings ['REMOTE_FPP_ENABLED'] );
$REMOTE_FPP_IP = urldecode ( $pluginSettings ['REMOTE_FPP_IP'] );

$MATRIX_MODE = urldecode ( $pluginSettings ['MATRIX_MODE'] );

$NAMES_PRE_TEXT = urldecode ( $pluginSettings ['NAMES_PRE_TEXT'] );

$MATRIX_ACTIVE = urldecode ( $pluginSettings ['MATRIX_ACTIVE'] );
if ($MATRIX_MODE == "") {
	// default to free text
	$MATRIX_MODE = "FREE";
}

$ENABLED = urldecode ( $pluginSettings ['ENABLED'] );
// $COMMAND_ARRAY = explode(",",trim(strtoupper($VALID_COMMANDS)));

$CONTROL_NUMBER_ARRAY = explode ( ",", $CONTROL_NUMBERS );

$WHITELIST_NUMBER_ARRAY = explode ( ",", $WHITELIST_NUMBERS );

$PROFANITY_RESPONSE = urldecode ( $pluginSettings ['PROFANITY_RESPONSE'] );

$PROFANITY_THRESHOLD = urldecode ( $pluginSettings ['PROFANITY_THRESHOLD'] );

$TSMS_from = "";
$TSMS_body = "";
$TSMS_BODY_CONTAINED_HEX = false;

if (isset ( $_POST ['From'] ) || $TSMS_from != "") {
	
	$TSMS_from = $_POST ['From'];
} elseif ($CONSOLE_DEBUG) {
	$TSMS_from = "+16195666240";
} else {
	logEntry ( "No Post data in FROM: Exiting" );
	
	lockHelper::unlock ();
	exit ( 0 );
}
if (isset ( $_POST ['Body'] ) || $TSMS_body != "") {
	$TSMS_body = $_POST ['Body'];
} elseif ($CONSOLE_DEBUG) {
	
	$TSMS_body = "bitch";
} else {
	logEntry ( "No Post data in BODY: Exiting" );
	lockHelper::unlock ();
	exit ( 0 );
}

if ($DEBUG) {
	logEntry ( "Twilio account_sid: " . $TSMS_account_sid );
	logEntry ( "Twilio account pass: " . $TSMS_auth_token );
	
	logEntry ( "TSMS message from: " . $TSMS_from );
	logEntry ( "TSMS Message body: " . $TSMS_body );
	logEntry ( "Matrix mode: " . $MATRIX_MODE );
	logEntry ( "Names pre text: " . $NAMES_PRE_TEXT );
}

// remove emoticon stuff
$TSMS_body_NEW = stripHexChars ( trim ( $TSMS_body ) );

$lenOriginal = strlen ( trim ( $TSMS_body ) );
$lenNew = strlen ( trim ( $TSMS_body_NEW ) );

if ($lenNew !== $lenOriginal) {
	
	$TSMS_BODY_CONTAINED_HEX = true;
	logEntry ( "TWILIO: TSMS Message body contained hex: " );
	if ($DEBUG) {
		logEntry ( "String length of original body: " . $lenOriginal );
		logEntry ( "string length of new body after processing: " . $lenNew );
	}
}

$TSMS_body = $TSMS_body_NEW;

if (in_array ( $TSMS_from, $CONTROL_NUMBER_ARRAY )) {
	if ($DEBUG) {
		logEntry ( "Inside checking for enable / disable" );
	}
	if (trim ( strtoupper ( $TSMS_body ) ) == "ENABLE" && $ENABLED != "ON") {
		$messageText = "ENABLING VIA CONTROL NUMBER";
		
		foreach ( $CONTROL_NUMBER_ARRAY as $NOTIFY_NUMBER ) {
			$TSMS_from = $NOTIFY_NUMBER;
			logEntry ( "Sending notification to number: " . $TSMS_from );
			sendTSMSMessage ( $messageText );
		}
		
		WriteSettingToFile ( "ENABLED", urlencode ( "ON" ), $pluginName );
		logEntry ( $messageText );
		lockHelper::unlock ();
		exit ( 0 );
	}
	if (trim ( strtoupper ( $TSMS_body ) ) == "DISABLE" && $ENABLED == "ON") {
		$messageText = "DISABLING VIA CONTROL NUMBER";
		
		foreach ( $CONTROL_NUMBER_ARRAY as $NOTIFY_NUMBER ) {
			$TSMS_from = $NOTIFY_NUMBER;
			logEntry ( "Sending notification to number: " . $TSMS_from );
			sendTSMSMessage ( $messageText );
		}
		
		WriteSettingToFile ( "ENABLED", urlencode ( "OFF" ), $pluginName );
		logEntry ( $messageText );
		lockHelper::unlock ();
		exit ( 0 );
	}
	
	if (trim ( strtoupper ( $TSMS_body ) ) == "DISABLE" && $ENABLED == "OFF") {
		$messageText = "The SMS request of DISABLE was not processed, the system is currently DISABLED";
		
		// foreach($CONTROL_NUMBER_ARRAY as $NOTIFY_NUMBER) {
		// $TSMS_from = $NOTIFY_NUMBER;
		logEntry ( "Sending notification to number: " . $TSMS_from );
		sendTSMSMessage ( $messageText );
		// }
		
		// WriteSettingToFile("ENABLED",urlencode("OFF"),$pluginName);
		logEntry ( $messageText );
		lockHelper::unlock ();
		exit ( 0 );
	}
	
	if (trim ( strtoupper ( $TSMS_body ) ) == "ENABLE" && $ENABLED == "ON") {
		$messageText = "The SMS request of ENABLE was not processed, the system is currently ENABLED";
		
		// foreach($CONTROL_NUMBER_ARRAY as $NOTIFY_NUMBER) {
		// $TSMS_from = $NOTIFY_NUMBER;
		logEntry ( "Sending notification to number: " . $TSMS_from );
		sendTSMSMessage ( $messageText );
		// }
		
		// WriteSettingToFile("ENABLED",urlencode("OFF"),$pluginName);
		logEntry ( $messageText );
		lockHelper::unlock ();
		exit ( 0 );
	}
}

if (strtoupper ( $ENABLED ) != "ON") {
	$REPLY_TEXT_PLUGIN_DISABLED = "We're sorry, the system is not accepting SMS at this time";
	sendTSMSMessage ( $REPLY_TEXT_PLUGIN_DISABLED );
	logEntry ( "Plugin Status: DISABLED Please enable in Plugin Setup to use" );
	lockHelper::unlock ();
	exit ( 0 );
}

// want to reply even if locked / disabled
// if(($pid = lockHelper::lock()) === FALSE) {

// logEntry("System is busy: Matrix active status: ".$MATRIX_ACTIVE);
// exit(0);

// }

// if the command values do not have anything, set some defaults
if (trim ( $playCommands ) == "") {
	$playCommands = "PLAY";
}

if (trim ( $stopCommands ) == "") {
	$stopCommands = "TERMINATE";
}
if (trim ( $repeatCommands ) == "") {
	$repeatCommands = "REPEAT";
}
if (trim ( $statusCommands ) == "") {
	$statusCommands = "STATUS";
}

if ($DEBUG)
	print_r ( $pluginSettings );

$playCommandsArray = explode ( ",", trim ( strtoupper ( $playCommands ) ) );
$stopCommandsArray = explode ( ",", trim ( strtoupper ( $stopCommands ) ) );
$repeatCommandsArray = explode ( ",", trim ( strtoupper ( $repeatCommands ) ) );
$statusCommandsArray = explode ( ",", trim ( strtoupper ( $statusCommands ) ) );

// change the mode!

if ($DEBUG)
	logEntry ( "processing message: from: " . $TSMS_from . " Message: " . $TSMS_body );

$messageText = preg_replace ( '/\s+/', ' ', $TSMS_body );
$messageParts = explode ( " ", $messageText );

// need to reformat from ISO +1 format to local number format or use +1 in control numbers

if (in_array ( $TSMS_from, $CONTROL_NUMBER_ARRAY )) {
	// /message used is to make sure that we do not process a message twice if it is from a number that is both a whitelist AND control numbers
	$MESSAGE_USED = true;
	// change to control number = TRUE
	$CONTROL_NUMBER_USED = true;
	$WHITELIST_NUMBER_USED = false;
	
	logEntry ( "Control number found: " . $TSMS_from );
	
	if (in_array ( trim ( strtoupper ( $messageParts [0] ) ), $playCommandsArray )) {
		logEntry ( "SMS play cmd FOUND!!!" );
		$CMD = "PLAY";
	}
	
	if (in_array ( trim ( strtoupper ( $messageParts [0] ) ), $stopCommandsArray )) {
		logEntry ( "SMS stop cmd FOUND!!!" );
		$CMD = "STOP";
	}
	
	if (in_array ( trim ( strtoupper ( $messageParts [0] ) ), $repeatCommandsArray )) {
		logEntry ( "SMS repeat cmd FOUND!!!" );
		$CMD = "REPEAT";
	}
	
	if (in_array ( trim ( strtoupper ( $messageParts [0] ) ), $statusCommandsArray )) {
		logEntry ( "SMS status cmd FOUND!!!" );
		$CMD = "STATUS";
	}
	
	if (trim ( strtoupper ( $messageParts [0] ) ) == "MODE") {
		
		// find the mode
		
		$MODE = strtoupper ( $messageParts [1] );
		
		logEntry ( "Mode: " . $MODE );
		
		if ($MODE == "NAMES" || $MODE == "FREE") {
			logEntry ( "We got a mode from a control number" );
			
			WriteSettingToFile ( "MATRIX_MODE", urlencode ( $MODE ), $pluginName );
			
			$REPLY_TEXT_CMD = "Mode changed to " . $MODE . " from control number: " . $TSMS_from;
			sendTSMSMessage ( $REPLY_TEXT_CMD );
			lockHelper::unlock ();
			exit ( 0 );
		} else {
			$REPLY_TEXT_CMD = "Not a valid mode: " . $MODE . " from control number: " . $TSMS_from;
			sendTSMSMessage ( $REPLY_TEXT_CMD );
			lockHelper::unlock ();
			exit ( 0 );
		}
	}
	
	// if(in_array(trim(strtoupper($messageParts[0])),$COMMAND_ARRAY)) {
	if ($CMD != "") {
		logEntry ( "Command request: " . $messageText . " in uppercase is in control array" );
		// do we have a playlist name?
		if ($messageParts [1] != "") {
			processSMSCommand ( $TSMS_from, $CMD, $messageParts [1] );
			// processSMSCommand($from,$messageParts[0],$messageParts[1]);
		} else {
			
			// play the configured playlist@!!!! from the plugin
			processSMSCommand ( $TSMS_from, $CMD, $PLAYLIST_NAME );
			// processSMSCommand($from,$messageParts[0],$PLAYLIST_NAME);
		}
		
		$REPLY_TEXT_CMD = "Thank you - your command has been accepted from control number: " . $TSMS_from;
		sendTSMSMessage ( $REPLY_TEXT_CMD );
		
		// we do not want to do any more besides commands here
		logEntry ( "Exiting because command executed" );
		lockHelper::unlock ();
		exit ( 0 );
	} else {
		// generic message to display from control number just like a regular user
		// this just processes the message
		processSMSMessage ( $TSMS_from, $messageText );
		logEntry ( "Back from Control number adding new message" );
		
		sendTSMSMessage ( $REPLY_TEXT );
	}
}

if (in_array ( $TSMS_from, $WHITELIST_NUMBER_ARRAY ) && ! $CONTROL_NUMBER_USED) 

{
	$WHITELIST_NUMBER_USED = true;
	logEntry ( $messageText . " is from a white listed number" );
	
	// this just writes the message to the database!
	processSMSMessage ( $TSMS_from, $messageText );
}

if (! $WHITELIST_NUMBER_USED && ! $CONTROL_NUMBER_USED) {
	
	// not from a white listed or a control number so just a regular user
	
	// check the datbase for the user in the blaclist
	$blackListed = checkBlacklist ( $TSMS_from );
	
	if ($blackListed != null) {
		
		if ($DEBUG) {
			logEntry ( "TSMS from: " . $TSMS_from );
			logEntry ( "Blacklist found: " . $blacklistPhonenumber );
		}
		logEntry ( $TSMS_from . " is in the blacklist since date: " . date('d M Y H:i:s',$blackListed) );
		$REPLY_TEXT = "You have been placed on our blacklist due to profanity since ". date('d M Y H:i:s',$blackListed);// since ".$blackListed;// TODO: since: ".$blacklistDate;
		                                                                        
		// add the message anyway to the message queu - to show that they were blacklisted and tried
		                                                                        // to send more messages :0
		                                                                        // their number will appear as blacklisted for all messages
		insertBlacklistMessage ( $messageText, $pluginName, $TSMS_from );
		
		// addNewMessage($messageText,$pluginName,$TSMS_from,$messageQueueFile);
		
		// also check for profanity since we are going to exit anyway
		// profanity checker API
		switch ($PROFANITY_ENGINE) {
			
			case "NEUTRINO" :
				$profanityCheck = check_for_profanity_neutrinoapi ( $messageText );
				break;
			
			case "WEBPURIFY" :
				$profanityCheck = check_for_profanity_WebPurify ( $messageText );
				break;
			
			default :
				// default turn off profanity check
				$profanityCheck == false;
				$NO_PROFANITY_FILTER = true;
				
				break;
		}
		
		if (! $profanityCheck) {
			
			// passed...
			
			if ($NO_PROFANITY_FILTER) {
				logEntry ( "TWILIO: NO PROFANITY CHECKER CONFIGURED!!" );
				logEntry ( "TWILIO: Unable to check message: " . $messageText );
			} else {
				logEntry ( "TWILIO: Message: " . $messageText . " PASSED" );
			}
		} else {
			logEntry ( "TWILIO: message: " . $messageText . " FAILED" );
			
			insertProfanityMessage ( $messageText, $pluginName, $TSMS_from );
		}
		
		sendTSMSMessage ( $REPLY_TEXT );
		
		lockHelper::unlock ();
		exit ( 0 );
	}
	
	// not a white list, not on black list - contineu to check for profanity
	
	logEntry ( "TWILIO: No blacklist" );
	
	logEntry ( "TWILIO: Continuing to check for Profanity" );
	// need to check for profanity
	// profanity checker API
	switch ($PROFANITY_ENGINE) {
		
		case "NEUTRINO" :
			$profanityCheck = check_for_profanity_neutrinoapi ( $messageText );
			break;
		
		case "WEBPURIFY" :
			$profanityCheck = check_for_profanity_WebPurify ( $messageText );
			break;
		
		default :
			// default turn off profanity check
			$profanityCheck == false;
			$NO_PROFANITY_FILTER = true;
			
			break;
	}
	
	if (! $profanityCheck) {
		
		if ($NO_PROFANITY_FILTER) {
			logEntry ( "TWILIO: NO PROFANITY CHECKER CONFIGURED!!" );
			logEntry ( "TWILIO: Unable to check message: " . $messageText );
		} else {
			logEntry ( "TWILIO: Message: " . $messageText . " PASSED" );
		}
		
		processSMSMessage ( $TSMS_from, $messageText, $messageQueueFile );
		sendTSMSMessage ( $REPLY_TEXT );
	} else {
		logEntry ( "message: " . $messageText . " FAILED" );
		// $REPLY_TEXT = "Your message contains Profanity, Sorry. More messages like this will ban your phone number";
		
		sendTSMSMessage ( $PROFANITY_RESPONSE );
		insertProfanityMessage ( $messageText, $pluginName, $TSMS_from );
		// addProfanityMessage($messageText,$pluginName,$pluginData=$TSMS_from);
		// add to regular file as well
		// cannot add the message to the file - as if you are running the RUN-MATRIX it would dump this message!! would
		// have to scan for profanity again!
		
		// addNewMessage($messageText,$pluginName,$TSMS_from,$messageQueueFile);
		
		logEntry ( "Added message to profanity queue file: " . $profanityMessageQueueFile );
		
		// check the threshold and
		// alert the control number(s) that there was profanity
		
		$profanityCount = checkProfanityCount ( $TSMS_from );
		
		if ($profanityCount >= $PROFANITY_THRESHOLD) {
			$messageText = "Number: " . $TSMS_from . " has reached the profanity threshold";
			foreach ( $CONTROL_NUMBER_ARRAY as $NOTIFY_NUMBER ) {
				$TSMS_from = $NOTIFY_NUMBER;
				
				logEntry ( "TWILIO: Sending profanity threshold notification to number: " . $TSMS_from );
				sendTSMSMessage ( $messageText );
			}
		}
		
		lockHelper::unlock ();
		exit ( 0 );
	}
}

if ($IMMEDIATE_OUTPUT != "ON") {
	logEntry ( "TWILIO: NOT immediately outputting to matrix" );
	// } elseif(!$MATRIX_ACTIVE) {
	} else {
	// add the message pre text to the names before sending it to the matrix!
	switch ($MATRIX_MODE) {
		
		case "NAMES" :
			
			$messageText = $NAMES_PRE_TEXT . " " . $messageText;
			break;
	}
	
	logEntry ( "IMMEDIATE OUTPUT ENABLED" );
	
	// write high water mark, so that if run-matrix is run it will not re-run old messages
	
	$pluginLatest = time ();
	
	// logEntry("message queue latest: ".$pluginLatest);
	// logEntry("Writing high water mark for plugin: ".$pluginName." LAST_READ = ".$pluginLatest);
	
	// file_put_contents($messageQueuePluginPath.$pluginSubscriptions[$pluginIndex].".lastRead",$pluginLatest);
	// WriteSettingToFile("LAST_READ",urlencode($pluginLatest),$pluginName);
	
	// do{
	
	logEntry ( "Matrix location: " . $MATRIX_LOCATION );
	logEntry ( "Matrix Exec page: " . $MATRIX_EXEC_PAGE_NAME );
	$MATRIX_ACTIVE = true;
	WriteSettingToFile ( "MATRIX_ACTIVE", urlencode ( $MATRIX_ACTIVE ), $pluginName );
	logEntry ( "MATRIX ACTIVE: " . $MATRIX_ACTIVE );
	
	$curlURL = "http://" . $MATRIX_LOCATION . "/plugin.php?plugin=" . $MATRIX_MESSAGE_PLUGIN_NAME . "&page=" . $MATRIX_EXEC_PAGE_NAME . "&nopage=1&subscribedPlugin=" . $pluginName . "&onDemandMessage=" . urlencode ( $messageText );
	if ($DEBUG)
		logEntry ( "MATRIX TRIGGER: " . $curlURL );
	
	$ch = curl_init ();
	curl_setopt ( $ch, CURLOPT_URL, $curlURL );
	
	curl_setopt ( $ch, CURLOPT_RETURNTRANSFER, true );
	curl_setopt ( $ch, CURLOPT_WRITEFUNCTION, 'do_nothing' );
	curl_setopt ( $ch, CURLOPT_VERBOSE, false );
	
	$result = curl_exec ( $ch );
	logEntry ( "Curl result: " . $result ); // $result;
	curl_close ( $ch );
	
	$MATRIX_ACTIVE = false;
	WriteSettingToFile ( "MATRIX_ACTIVE", urlencode ( $MATRIX_ACTIVE ), $pluginName );
	
	// } while (count(getNewPluginMessages($pluginName)) >0);
}

// sleep(1);

lockHelper::unlock ();
exit ( 0 );

?>
