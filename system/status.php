<?php
/**
 * Server status
 *
 * @package   MyAAC
 * @author    Slawkens <slawkens@gmail.com>
 * @copyright 2019 MyAAC
 * @link      https://my-aac.org
 */

use MyAAC\Cache\Cache;
use MyAAC\Models\Config;
use MyAAC\Models\PlayerOnline;
use MyAAC\Models\Player;

defined('MYAAC') or die('Direct access not allowed!');

$status = array();
$status['online'] = false;
$status['players'] = 0;
$status['playersMax'] = 0;
$status['lastCheck'] = 0;
$status['uptime'] = '0h 0m';
$status['monsters'] = 0;

if(setting('core.status_enabled') === false) {
	return;
}

/**
 * @var array $config
 */
$status_ip = $config['lua']['ip'];
if(isset($config['lua']['statusProtocolPort'])) {
	$config['lua']['loginPort'] = $config['lua']['statusProtocolPort'];
	$config['lua']['statusPort'] = $config['lua']['statusProtocolPort'];
	$status_port = $config['lua']['statusProtocolPort'];
}
else if(isset($config['lua']['status_port'])) {
	$config['lua']['loginPort'] = $config['lua']['status_port'];
	$config['lua']['statusPort'] = $config['lua']['status_port'];
	$status_port = $config['lua']['status_port'];
}

// ip check — prefer config.lua over DB default when config specifies a custom host
$settingIP = setting('core.status_ip');
if(isset($settingIP[0]) && (!isset($status_ip[0]) || $status_ip === '127.0.0.1'))
{
	$status_ip = $settingIP;
}
elseif(!isset($status_ip[0])) // try localhost if no ip specified
{
	$status_ip = '127.0.0.1';
}

// port check — same logic: prefer config.lua over DB default
$status_port = $config['lua']['statusPort'];
$settingPort = setting('core.status_port');
if(isset($settingPort[0]) && (!isset($status_port[0]) || $status_port == 7171)) {
	$status_port = $settingPort;
}
elseif(!isset($status_port[0])) // try 7171 if no port specified
{
	$status_port = 7171;
}

$fetch_from_db = true;
/**
 * @var Cache $cache
 */
if($cache->enabled())
{
	$tmp = '';
	if($cache->fetch('status', $tmp))
	{
		$status = unserialize($tmp);
		$fetch_from_db = false;
	}
}

if($fetch_from_db)
{
	$status_query = Config::where('name', 'LIKE', '%status%')->get();
	if (!$status_query || !$status_query->count()) {
		foreach($status as $key => $value) {
			registerDatabaseConfig('status_' . $key, $value);
		}
	} else {
		foreach($status_query as $tmp) {
			$status[str_replace('status_', '', $tmp->name)] = $tmp->value;
		}
	}
}

if(isset($config['lua']['statustimeout']))
	$config['lua']['statusTimeout'] = $config['lua']['statustimeout'];

// get status timeout from server config
$status_timeout = eval('return ' . $config['lua']['statusTimeout'] . ';') / 1000 + 1;
$status_interval = setting('core.status_interval');
if($status_interval && $status_timeout < $status_interval) {
	$status_timeout = $status_interval;
}

/**
 * @var int $status_timeout
 */
if($status['lastCheck'] + $status_timeout < time()) {
	updateStatus();
}

function updateStatus() {
	global $db, $cache, $config, $status, $status_ip, $status_port;

	// get server status and save it to database
	$proxyUrl = setting('core.status_proxy');
	if(!empty($proxyUrl)) {
		$timeout = max(1, intval(setting('core.status_timeout') / 1000));
		$ch = curl_init($proxyUrl . '/status');
		curl_setopt_array($ch, [
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_TIMEOUT => $timeout,
			CURLOPT_SSL_VERIFYPEER => false,
			CURLOPT_SSL_VERIFYHOST => false,
		]);
		$response = curl_exec($ch);
		$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		curl_close($ch);

		if($httpCode === 200 && $response) {
			$json = json_decode($response, true);
			if($json && isset($json['xml'])) {
				$status['lastCheck'] = time();
				$status['online'] = true;
				$serverStatus = parseOTAdminXML($json['xml']);
				if($serverStatus) {
					$status['players'] = $serverStatus['players'];
					$status['playersMax'] = $serverStatus['playersMax'];
					$status['uptime'] = $serverStatus['uptime'];
					$status['uptimeReadable'] = getStatusUptimeReadable($serverStatus['uptime']);
					$status['monsters'] = $serverStatus['monsters'];
					$status['motd'] = $serverStatus['motd'];
					$status['mapAuthor'] = $serverStatus['mapAuthor'];
					$status['mapName'] = $serverStatus['mapName'];
					$status['mapWidth'] = $serverStatus['mapWidth'];
					$status['mapHeight'] = $serverStatus['mapHeight'];
					$status['server'] = $serverStatus['server'];
					$status['serverVersion'] = $serverStatus['serverVersion'];
					$status['clientVersion'] = $serverStatus['clientVersion'];
				}
			} else {
				$status['online'] = false;
				$status['players'] = 0;
				$status['playersMax'] = 0;
			}
		} else {
			$status['online'] = false;
			$status['players'] = 0;
			$status['playersMax'] = 0;
		}
	} else {
		$serverInfo = new OTS_ServerInfo($status_ip, $status_port);
		$serverInfo->setTimeout(setting('core.status_timeout'));

		$serverStatusObj = $serverInfo->status();
		if(!$serverStatusObj)
		{
			$status['online'] = false;
			$status['players'] = 0;
			$status['playersMax'] = 0;
		}
		else
		{
			$status['lastCheck'] = time();

			$status['online'] = true;
			$status['players'] = $serverStatusObj->getOnlinePlayers();
			$status['playersMax'] = $serverStatusObj->getMaxPlayers();

			if (setting('core.online_afk'))
			{
				$status['playersTotal'] = 0;
				if($db->hasTable('players_online')) {
					$status['playersTotal'] = PlayerOnline::count();
				}
				else {
					$status['playersTotal'] = Player::online()->count();
				}
			}

			$uptime = $status['uptime'] = $serverStatusObj->getUptime();
			$status['uptimeReadable'] = getStatusUptimeReadable($uptime);

			$status['monsters'] = $serverStatusObj->getMonstersCount();
			$status['motd'] = $serverStatusObj->getMOTD();

			$status['mapAuthor'] = $serverStatusObj->getMapAuthor();
			$status['mapName'] = $serverStatusObj->getMapName();
			$status['mapWidth'] = $serverStatusObj->getMapWidth();
			$status['mapHeight'] = $serverStatusObj->getMapHeight();

			$status['server'] = $serverStatusObj->getServer();
			$status['serverVersion'] = $serverStatusObj->getServerVersion();
			$status['clientVersion'] = $serverStatusObj->getClientVersion();
		}
	}

	if($cache->enabled()) {
		$cache->set('status', serialize($status), 120);
	}

	$tmpVal = null;
	foreach($status as $key => $value) {
		if(fetchDatabaseConfig('status_' . $key, $tmpVal)) {
			updateDatabaseConfig('status_' . $key, $value);
		}
		else {
			registerDatabaseConfig('status_' . $key, $value);
		}
	}
}
