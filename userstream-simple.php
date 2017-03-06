<?php
error_reporting(0);
include_once "functions.inc.php";

require_once('lib/UserstreamPhirehose.php');
$CONSOLE_DEBUG = false;
$pluginName = "TwitterEvent";
$TwitterEventVersion = "2.0";
$myPid = getmypid ();
$messageQueue_Plugin = "MessageQueue";
$MESSAGE_QUEUE_PLUGIN_ENABLED = false;
$NAMES_PRE_TEXT = "Share a Coke with ";


$MATRIX_MESSAGE_PLUGIN_NAME = "MatrixMessage";
// page name to run the matrix code to output to matrix (remote or local);
$MATRIX_EXEC_PAGE_NAME = "matrix.php";

$skipJSsettings = 1;
include_once ("/opt/fpp/www/config.php");
include_once ("/opt/fpp/www/common.php");


$logFile = $settings ['logDirectory'] . "/" . $pluginName . ".log";


$pluginConfigFile = $settings ['configDirectory'] . "/plugin." . $pluginName;
if (file_exists ( $pluginConfigFile ))
	$pluginSettings = parse_ini_file ( $pluginConfigFile );

	$logFile = $settings ['logDirectory'] . "/" . $pluginName . ".log";
	$DEBUG = urldecode ( $pluginSettings ['DEBUG'] );
	//$CONSOLE_DEBUG = urldecode ( $pluginSettings ['CONSOLE_DEBUG'] );
	
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

//$OAUTH_ACCESS_TOKEN = urldecode(ReadSettingFromFile("oauth_access_token",$pluginName));
$OAUTH_ACCESS_TOKEN = urldecode($pluginSettings['oauth_access_token']);

//$OAUTH_ACCESS_TOKEN_SECRET = urldecode(ReadSettingFromFile("oauth_access_token_secret",$pluginName));
$OAUTH_ACCESS_TOKEN_SECRET = urldecode($pluginSettings['oauth_access_token_secret']);

//$CONSUMER_KEY = urldecode(ReadSettingFromFile("consumer_key",$pluginName));
$CONSUMER_KEY = urldecode($pluginSettings['consumer_key']);

//$CONSUMER_SECRET = urldecode(ReadSettingFromFile("consumer_secret",$pluginName));
$CONSUMER_SECRET = urldecode($pluginSettings['consumer_secret']);

$MATRIX_LOCATION = urldecode ( $pluginSettings ['MATRIX_LOCATION'] );

$MATRIX_MODE = urldecode ( $pluginSettings ['MATRIX_MODE'] );

$NAMES_PRE_TEXT = urldecode ( $pluginSettings ['NAMES_PRE_TEXT'] );

$IMMEDIATE_OUTPUT = urldecode ( $pluginSettings ['IMMEDIATE_OUTPUT'] );
$MATRIX_LOCATION = urldecode ( $pluginSettings ['MATRIX_LOCATION'] );


// MATRIX ACTIVE - true / false to catch more messages if they arrive
$MATRIX_ACTIVE = false;
$MATRIX_MODE = "NAMES";

$IMMEDIATE_OUTPUT = "ON";

if($DEBUG) {
	logEntry("Immediate mode: ".$IMMEDIATE_OUTPUT);
	logEntry("Matrix location: ".$MATRIX_LOCATION);
	logEntry("Matrix Mode: ".$MATRIX_MODE);
	logEntry("Matrix active: ".$MATRIX_ACTIVE);
	
}

/**
 * Barebones example of using UserstreamPhirehose.
 */
class MyUserConsumer extends UserstreamPhirehose 
{
  /**
   * First response looks like this:
   *    $data=array('friends'=>array(123,2334,9876));
   *
   * Each tweet of your friends looks like:
   *   [id] => 1011234124121
   *   [text] =>  (the tweet)
   *   [user] => array( the user who tweeted )
   *   [entities] => array ( urls, etc. )
   *
   * Every 30 seconds we get the keep-alive message, where $status is empty.
   *
   * When the user adds a friend we get one of these:
   *    [event] => follow
   *    [source] => Array(   my user   )
   *    [created_at] => Tue May 24 13:02:25 +0000 2011
   *    [target] => Array  (the user now being followed)
   *
   * @param string $status
   */
  public function enqueueStatus($status)
  {
  	
    /*
     * In this simple example, we will just display to STDOUT rather than enqueue.
     * NOTE: You should NOT be processing tweets at this point in a real application, instead they
     *  should be being enqueued and processed asyncronously from the collection process. 
     */
    $data = json_decode($status, true);
   // echo date("Y-m-d H:i:s (").strlen($status)."):".print_r($data,true)."\n";
    logEntry("inside enqueue status:");
    $EVENT = $data['event'];
    $TwitterMessage = $data['text'];
    $twittermessageid = $data['id'];
    logEntry(" EVENT: ".$EVENT);
    logEntry("twitter message: ".$TwitterMessage);
    logEntry("message id: ".$twittermessageid);
    
    if(trim($TwitterMessage) != "") {
    	include_once ("/opt/fpp/www/config.php");
    	include_once ("/opt/fpp/www/common.php");
    	
    	//$str = "Hi @xxx and @yyy and @zzz.";
    	$TwitterMessage = preg_replace('/@\w+/', '', $TwitterMessage);
    	
    	logEntry("Twitter message after removal of handle being sent: ".$TwitterMessage);
 	   
	$NAMES_PRE_TEXT = "Share a Coke with ";
 	   
 	   
 	   // MATRIX ACTIVE - true / false to catch more messages if they arrive
 	   $MATRIX_ACTIVE = false;
 	   $MATRIX_MODE = "NAMES";
 	   
 	   $IMMEDIATE_OUTPUT = "ON";
 	   $MATRIX_MESSAGE_PLUGIN_NAME = "MatrixMessage";
 	   $MATRIX_EXEC_PAGE_NAME = "matrix.php";
 	   $pluginName = "TwitterEvent";
 	   $MATRIX_LOCATION = "127.0.0.1";
 	   
 	   if($DEBUG) {
 	   	logEntry("Immediate mode: ".$IMMEDIATE_OUTPUT);
 	   	logEntry("Matrix location: ".$MATRIX_LOCATION);
 	   	logEntry("Matrix Mode: ".$MATRIX_MODE);
 	   	logEntry("Matrix active: ".$MATRIX_ACTIVE);
 	   
 	   }

    	// add the message pre text to the names before sending it to the matrix!
    	switch ($MATRIX_MODE) {
    
    		case "NAMES" :
    				
    			$messageText = $NAMES_PRE_TEXT . " " . $TwitterMessage;
    			break;
    	}
    
    	addNewMessage($messageText,"TwitterEvent",$twittermessageid,$messageFile);
    	 
    	insertTwitterMessage($messageText, "TwitterEvent", $twittermessageid);
    	
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
    
  }

}

include_once('twitter.preferences.inc');


// Start streaming
$sc = new MyUserConsumer(OAUTH_TOKEN, OAUTH_SECRET);
$sc->consume();
