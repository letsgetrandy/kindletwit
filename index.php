<?php  

require_once('OAuth.php');
require_once('twitteroauth.php');

define('TWITTER_KEY',    'BJroKtTZW15fzvVdn1HQ');
define('TWITTER_SECRET', '5MuOShEOrrx8aF2XeQOc1wFG9pIwyPLK0YIx7ab2A8k');

session_start();

$user = array();
$user['otok'] = $_COOKIE['otok'];
$user['osec'] = $_COOKIE['osec'];
$user['user'] = $_COOKIE['user'];
$user['name'] = $_COOKIE['name'];

function page_args () {
	$arr = array();
	$args = array('action'=>'home', 'option'=>'');
	if ($_SERVER['REDIRECT_URL']) {
		$args = explode('/', $_SERVER['REDIRECT_URL']);
	}
	if (count($args) > 1) {
		$arr['action'] = $args[1];
		$arr['option'] = $args[2];
	}
	return $arr;
}

$page = page_args();

switch ($page['action']) {

	case 'twitter':
		switch ($page['option']) {
			case 'auth':
				
				$tok = $_COOKIE['rtok'];
				$sec = $_COOKIE['rsec'];
	
				if ($tok && $sec) {
	
					$conn = new TwitterOAuth(TWITTER_KEY, TWITTER_SECRET, $tok, $sec);
					$token = $conn->getAccessToken($_REQUEST['oauth_verifier']);
	
					if ($token['oauth_token']) {
						$user['otok'] = $token['oauth_token'];
						$user['osec'] = $token['oauth_token_secret'];
						$user['user'] = $token['user_id'];
						$user['name'] = $token['screen_name'];
	
						$expire=time()+60*60*24*30;
						setcookie("otok", $user['otok'], $expire, '/', '.kindletwit.com');
						setcookie("osec", $user['osec'], $expire, '/', '.kindletwit.com');
						setcookie("user", $user['user'], $expire, '/', '.kindletwit.com');
						setcookie("name", $user['name'], $expire, '/', '.kindletwit.com');
					} else {
						die('An error occurred');
					}
				} else {
					//header('Location: ' . $_SERVER['REQUEST_URI']);
					die('<strong>Session error.</strong><br><br>Make sure you have cookies enabled.');
				}			
				break;
	
			default:
				$conn = new TwitterOAuth(TWITTER_KEY, TWITTER_SECRET);
				$request_token = $conn->getRequestToken('http://www.kindletwit.com/twitter/auth');
		
				if($conn->http_code = '200' && is_array($request_token)) {
					$token = $request_token['oauth_token'];

					$expire=time()+60*60*24; //one day
					setcookie("rtok", $request_token['oauth_token'], $expire, '/', '.kindletwit.com');
					setcookie("rsec", $request_token['oauth_token_secret'], $expire, '/', '.kindletwit.com');
							
					$url = $conn->getAuthorizeUrl($token);
					header('Location: ' . $url);
					die();
				} else {
					$error = 'Error connecting to Twitter.';
				}
				break;
		}
		break;
	
	case 'mentions':
		if ($user['otok']) {
			$conn = new TwitterOAuth(TWITTER_KEY, TWITTER_SECRET, $user['otok'], $user['osec']);
			$mentions = $conn->get(
				'http://api.twitter.com/1/statuses/mentions.json',
				array('count'=>'10', 'include_rts'=>'true')
			);
		}
		break;
		
	case 'reply':
		if ($user['otok'] && is_numeric($page['option'])) {
			$replyto = $page['option'];
			$conn = new TwitterOAuth(TWITTER_KEY, TWITTER_SECRET, $user['otok'], $user['osec']);
			$result = $conn->get('http://api.twitter.com/1/statuses/show/' . $replyto . '.json');
			$timeline = array($result);
		}
		break;

	case 'retweet':
		if ($user['otok'] && $page['option']) {
			$conn = new TwitterOAuth(TWITTER_KEY, TWITTER_SECRET, $user['otok'], $user['osec']);
			$result = $conn->post(
				'http://api.twitter.com/1/statuses/retweet/'.$page['option'].'.json', 
				array('status'=>$status)
			);
		}
		break;

	case 'update':
		if ($user['otok'] && $_POST['status']) {

			$opts = array();
			$opts['status'] = trim($_POST['status']);
			if ($_POST['reply_to_sts']) {
				$opts['in_reply_to_status_id'] = trim($_POST['reply_to_sts']);
			}
			
			$conn = new TwitterOAuth(TWITTER_KEY, TWITTER_SECRET, $user['otok'], $user['osec']);
			$result = $conn->post('http://api.twitter.com/1/statuses/update.json', $opts);
		}
		break;
		
	case 'logout':
		$expire=time()-2000;
		setcookie("otok", '', $expire, '/', '.kindletwit.com');
		setcookie("osec", '', $expire, '/', '.kindletwit.com');
		setcookie("user", '', $expire, '/', '.kindletwit.com');
		setcookie("name", '', $expire, '/', '.kindletwit.com');
		header('Location: /');
		die();
		break;
		
	default:
		$showuser = $page['action'];
		$conn = new TwitterOAuth(TWITTER_KEY, TWITTER_SECRET, $user['otok'], $user['osec']);
		$timeline = $conn->get(
			'http://api.twitter.com/1/statuses/user_timeline.json', 
			array('screen_name'=>$showuser)
		);
		break;
}

if ($page['action'] != 'logout') {
	if ($user['otok']) {
		$expire=time()+60*60*24*30;
		setcookie("otok", $user['otok'], $expire, '/', '.kindletwit.com');
		setcookie("osec", $user['osec'], $expire, '/', '.kindletwit.com');
		setcookie("user", $user['user'], $expire, '/', '.kindletwit.com');
		setcookie("name", $user['name'], $expire, '/', '.kindletwit.com');
	}
}

function show_head ($subhead='')
{
	global $user,$page;
	
	if ($subhead) $subhead = ": <em>{$subhead}</em>";
	echo "<h1>KindleTwit{$subhead}</h1>\n";
	$links = array();
	if ($user['otok'] && $user['name']) {
		if ($page['action'])
			$links['Home'] = '/';
		else
			$links['Refresh'] = '/';
		$links['Mentions'] = '/mentions/';
		$links['Logout'] = '/logout/';
	} else
		$links['Login'] = '/twitter/';
	
	echo "<div><em>";
	foreach ($links as $k=>$v) {
		echo "<a href=\"$v\">$k</a> &nbsp; &nbsp; ";
	}
	echo "</em></div>";
	?>
	<?php
}
function show_form ($txt='', $msgid='')
{
	$txt = ($txt) ? trim($txt) . ' ' : '';
	$len = 140 - strlen($txt);
	?>
	<form action="/update/" method="post">
	<input type="hidden" name="reply_to_sts" value="<?php echo $msgid; ?>">
	<p>Status: &nbsp; &nbsp; <span id="charcount"><?php echo $len; ?></span> characters remaining<br>
	<textarea name="status" id="twsts" cols="40" rows="3" onkeyup="updatecount(this);"><?php
		echo $txt; ?></textarea>
	<input type="submit" value="Update"></p>
	</form>
	<?php
}
function show_timeline($tl)
{

	if ($_REQUEST['test']=='y') {
		print_r($tl);
		die();
	}
	
	if (empty($tl) or count($tl)==0)
		return;
	
	for ($i=0; $i<count($tl); $i++)
	{
		$_name = $tl[$i]->user->screen_name;
		$_text = $tl[$i]->text;
		$_id   = $tl[$i]->id;
		
		$_text = preg_replace('@(https?://([-\w\.]+)+(:\d+)?(/([\w/_\.]*(\?\S+)?)?)?)@', '<a href="$1">$1</a>', $_text);
		$_text = preg_replace("/@(\w+)/", "<a href=\"/$1/\">@$1</a>", $_text);
		
		print "<p><strong><span style=\"font-size:110%\"><a href=\"/{$_name}/\">{$_name}</a></span></strong><br>";
		print "<em><span class=\"tweet\">{$_text}</span></em><br>";
		print "<span style=\"font-size:90%\"><a href=\"/reply/{$_id}/\">Reply</a>";
		print " &nbsp; <a href=\"/retweet/{$_id}/\">Retweet</a></span></p>\n";
	}
}

?>
<html>
<head>
<meta content="text/html; charset=utf-8" http-equiv="Content-Type">
<meta content="en-us" http-equiv="Content-Language">
<meta content="KindleTwit - A Twitter client that happens to work well on your e-reader device!" name="description">
<meta content="wb8TKFQ2Jmu7HUmgZ36aNznCsblghWMRjPp7cycwO6Y" name="google-site-verification">
<title>KindleTwit</title>
<style type="text/css">
textarea {font-size:1em;}
</style>
<script type="text/javascript">
function updatecount(el) {
	document.getElementById('charcount').innerHTML = (140 - parseInt(el.value.length,10));
}
</script>
<script type="text/javascript">
  var _gaq = _gaq || [];
  _gaq.push(['_setAccount', 'UA-15024826-9']);
  _gaq.push(['_trackPageview']);
  (function() {
    var ga = document.createElement('script'); ga.type = 'text/javascript'; ga.async = true;
    ga.src = ('https:' == document.location.protocol ? 'https://ssl' : 'http://www') + '.google-analytics.com/ga.js';
    var s = document.getElementsByTagName('script')[0]; s.parentNode.insertBefore(ga, s);
  })();
</script>
</head>

<body>
<?php

if ($replyto) {

	show_head('reply');
	show_form('@'.$result->user->screen_name, $replyto);
	show_timeline($timeline);
	
} else
if ($showuser) {

	show_head($showuser);
	show_timeline($timeline);
	
} else 
if ($mentions) {

	show_head('mentions');
	show_form();
	show_timeline($mentions);

} else
if ($user['otok'] && $user['name']) {	

	$conn = new TwitterOAuth(TWITTER_KEY, TWITTER_SECRET, $user['otok'], $user['osec']);
	$timeline = $conn->get(
		'http://api.twitter.com/1/statuses/friends_timeline.json', 
		array('user_id'=>$user['user'], 'screen_name'=>$user['name'])
	);
	show_head($user['name']);
	show_form();
	show_timeline($timeline);

} else {
	
	show_head();
	
	?><p>A minimal Twitter client that happens to work well on your e-reader device.</p><?php
}
?>
<p>&nbsp;</p>
<p style="font-size:.8em">&copy; 2010 Randy Hunt, <a href="http://www.bbqiguana.com/kindletwit/">BBQIguana.com</a><br>
This site is not affiliated in any way with "Kindle," a registered trademark of Amazon, Inc.</p>
</body>
</html>
