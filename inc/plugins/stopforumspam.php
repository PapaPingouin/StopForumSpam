<?php
if(!defined("IN_MYBB"))
{
	die("Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.");
}

global $mybb;
function stopforumspam_info()
{
	return array(
		'name'			=> 'Stop Forum Spam',
		'description'	=> 'Prevents users who are listed at http://www.stopforumspam.com from registering.',
		'website'		=> 'https://github.com/tommm/stopforumspam',
		'author'		=> "<a href='https://github.com/Tim-B'>Tim B.</a> and <a href='https://github.com/tommm/'>Tomm</a> and <a href='https://github.com/PapaPingouin/'>PapaPingouin</a>",
		'authorsite'	=> '',
		'version'		=> '1.4.2',
		'guid'			=> 'cd4d9e2f4a6975562887ee6edffb984e',
		'compatibility' => '1*'
	);
}

if(defined('IN_ADMINCP'))
{
	require_once MYBB_ROOT.'inc/plugins/stopforumspam/stopforumspam_acp.php';
	return;
}

$plugins->add_hook('member_do_register_start', 'stopforumspam');

function stopforumspam()
{
	global $details, $lang, $mybb, $session;

	$lang->load('stopforumspam');
	$details = array('username' => $mybb->input['username'], 'email' => $mybb->input['email'], 'ip' => $session->ipaddress, 'f' => 'json');

	if( $mybb->settings['sp_noipv6'] && strpos( $details['ip'] ,':' ) !== false ) // ipv6 are invalid on SFS
		unset($details['ip']); // delete ip from request (to not generate error from SFS)
	
	$context = stream_context_create(array(
		'http' => array(
			'method' => 'GET'
		)
	));

	$data = @file_get_contents("http://www.stopforumspam.com/api?".http_build_query($details), false, $context);
    $data = json_decode($data);

	function sp_log($log)
	{
		$logmessage = '';
		$logfile = 'sfs_log.php';

		if(!file_exists($logfile))
		{
			$logmessage = '<?php die(); ?>';
		}

		$logmessage .= "\n";
		$logmessage .= $log;

		@file_put_contents($logfile, $logmessage, FILE_APPEND);
	}

	if(isset($data->error) or !isset($data))
	{
		if($mybb->settings['sp_log'])
		{
			if(!isset($data))
			{
				$error = $lang->spam_contact_error;
			}
			else
			{
				$error = $data->error;
			}

			$logstring = 'Error: '. $error;
			$logstring .= " / Time: ".date(DATE_RSS, time());
			$logstring .= ' / Username: '.htmlspecialchars_uni($details['username']);
			$logstring .= ' / Email: '.htmlspecialchars_uni($details['email']);
			$logstring .= ' / IP: '.htmlspecialchars_uni($details['ip']);

			sp_log($logstring);
		}

		if($mybb->settings['sp_fail'])
		{
			return;
		}

		error($lang->spam_error);
	}

	function sp_logsfs( $confidence, $blocked)
	{
		global $details, $lang, $mybb;

		$logstring = "Time: ".date(DATE_RSS, time());
		$logstring .= ' / Username: '.htmlspecialchars_uni($details['username']);
		$logstring .= ' / Email: '.htmlspecialchars_uni($details['email']);
		$logstring .= ' / IP: '.htmlspecialchars_uni($details['ip']);
		$logstring .= ' / confidence: '.$confidence;
		$logstring .= ' => '.($blocked ? 'BLOCKED' : 'OK' );
		
		sp_log($logstring);

		
	}

	$confidence = 0;
	$settings = explode(',', $mybb->settings['sp_check']);

	if($settings[0] && $data->username->appears)
	{
		$confidence += $data->username->confidence;
	}

	if($settings[1] && $data->email->appears)
	{
		$confidence += $data->email->confidence;
	}

	if($settings[2] && $data->ip->appears)
	{
		$confidence += $data->ip->confidence;
	}

	$blocked = $confidence > $mybb->settings['sp_confidence'];
		
	if( $blocked )
	{
		if( $mybb->settings['sp_log'] ) // log error requests
			sp_logsfs( $confidence, $blocked ); 
		error($lang->spam_blocked);
	}
	else
	{
		if( $mybb->settings['sp_log_pass'] ) // log passed requests
			sp_logsfs( $confidence, $blocked );
	}
}
?>
