<?php
/**
 * Discord interactions endpoint (T498).
 *
 * Discord POSTs here whenever somebody uses one of our slash commands; this
 * file is the whole answer for them (the status poster is a separate service:
 * tools/discord_status_bot.py). Two rules shape it:
 *
 *  - Every request must carry a valid Ed25519 signature from Discord. Without
 *    that check anyone could forge a command answer, so unsigned requests are
 *    rejected before anything else runs.
 *  - Discord wants an answer within three seconds. The status query therefore
 *    has its own short timeout, and every other command answers from something
 *    local: settings, data files or the database.
 *
 * The public key is the Discord app's own, kept in `core.discord_public_key`
 * (written by tools/discord_setup.py --wire-interactions, which also points
 * Discord at this URL). Command definitions live in tools/discord_status_bot.py.
 */

ini_set('display_errors', '0'); // a stray warning would break the JSON Discord expects

require_once 'common.php';
require_once SYSTEM . 'functions.php';
require_once SYSTEM . 'init.php';

const DISCORD_PING = 1;
const DISCORD_APPLICATION_COMMAND = 2;
const DISCORD_RESPONSE_PONG = 1;
const DISCORD_RESPONSE_REPLY = 4;
const DISCORD_FLAG_EPHEMERAL = 64; // only the player who asked sees the answer

function discord_respond(array $payload, int $status = 200): void
{
	http_response_code($status);
	header('Content-Type: application/json');
	echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
	exit;
}

function discord_signature_valid(string $body): bool
{
	$publicKey = trim((string) setting('core.discord_public_key'));
	$signature = $_SERVER['HTTP_X_SIGNATURE_ED25519'] ?? '';
	$timestamp = $_SERVER['HTTP_X_SIGNATURE_TIMESTAMP'] ?? '';
	if ($publicKey === '' || $signature === '' || $timestamp === '') {
		return false;
	}

	// Stale timestamps are refused too, so a captured request cannot be replayed.
	if (abs(time() - (int) $timestamp) > 300) {
		return false;
	}

	try {
		return sodium_crypto_sign_verify_detached(
			sodium_hex2bin($signature),
			$timestamp . $body,
			sodium_hex2bin($publicKey)
		);
	}
	catch (SodiumException $e) {
		return false; // malformed hex is simply an invalid signature
	}
}

function discord_server_status(): ?array
{
	global $config;

	$proxy = setting('core.status_proxy');
	if (empty($proxy)) {
		return null;
	}

	// 2 s: Discord drops the whole interaction at 3 s, and a busy proxy must
	// not eat the budget of the answer.
	$curl = curl_init(rtrim($proxy, '/') . '/status');
	curl_setopt_array($curl, [
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_TIMEOUT => 2,
		CURLOPT_CONNECTTIMEOUT => 2,
	]);
	$body = curl_exec($curl);
	curl_close($curl);

	if (!$body) {
		return null;
	}

	$payload = json_decode($body, true);
	if (!is_array($payload) || empty($payload['xml'])) {
		return null;
	}

	return parseOTAdminXML($payload['xml']);
}

function discord_rates_xml(): ?DOMDocument
{
	global $config;

	$file = $config['data_path'] . 'XML/elysia_rates.xml';
	if (!file_exists($file)) {
		return null;
	}

	$document = new DOMDocument();
	return $document->load($file) ? $document : null;
}

function command_online(): string
{
	$status = discord_server_status();
	if ($status === null) {
		return "The server looks **offline** right now (or the status service did not answer). Try again in a minute.";
	}

	return sprintf(
		"**%d** / %d players online\nUptime: **%s** | Monsters: **%s**\n<https://elysia.pl/?subtopic=online>",
		$status['players'],
		$status['playersMax'],
		getStatusUptimeReadable($status['uptime']),
		number_format($status['monsters'], 0, ',', ' ')
	);
}

function command_rates(): string
{
	$document = discord_rates_xml();
	if ($document === null) {
		return 'The rate ladders are on the site: <https://elysia.pl/?subtopic=exp-stages>';
	}

	$sections = [
		'experience' => 'Experience',
		'skill' => 'Skills',
		'magic' => 'Magic level',
	];

	$lines = [];
	foreach ($sections as $nodeName => $label) {
		$node = $document->getElementsByTagName($nodeName)->item(0);
		if (!$node) {
			continue;
		}

		$rungs = [];
		foreach ($node->getElementsByTagName('stage') as $stage) {
			/** @var DOMElement $stage */
			$max = $stage->getAttribute('maxlevel');
			$rungs[] = sprintf('%sx (%s%s)',
				$stage->getAttribute('multiplier'),
				$stage->getAttribute('minlevel'),
				$max !== '' ? '-' . $max : '+'
			);
		}
		if ($rungs) {
			$lines[] = "**{$label}:** " . implode(' · ', $rungs);
		}
	}

	if (!$lines) {
		return 'The rate ladders are on the site: <https://elysia.pl/?subtopic=exp-stages>';
	}

	return implode("\n", $lines)
		. "\nAll ladders: <https://elysia.pl/?subtopic=exp-stages>";
}

function command_season(): string
{
	$date = trim((string) setting('core.season_date'));
	$pretty = $date;
	if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $date, $parts)) {
		$pretty = "{$parts[3]}.{$parts[2]}.{$parts[1]}";
	}

	if ($pretty === '') {
		return 'Season news always lands on the site first - and the account, premium time and Tibia Coins stay.';
	}

	return "The new season starts on **{$pretty}**: characters begin from zero, while your account, premium "
		. 'time and Tibia Coins stay with you.'
		. "\nEvery account created before the season gets **free premium and 1,000 Tibia Coins**."
		. "\nMore: <https://elysia.pl/?subtopic=news>";
}

function command_connect(): string
{
	$host = trim((string) setting('core.connect_host'));
	$port = trim((string) setting('core.connect_port'));
	$browser = trim((string) setting('core.webclient_url'));

	$lines = [];
	if ($host !== '') {
		$lines[] = "**Client (OTClient v8):** `{$host}`" . ($port !== '' ? " port `{$port}`" : '');
	}
	if ($browser !== '') {
		$lines[] = "**Browser - nothing to install:** {$browser}";
	}
	$lines[] = '**Downloads:** <https://elysia.pl/?subtopic=downloads>';

	return implode("\n", $lines);
}

function command_top(): string
{
	$players = getTopPlayers(5);
	if (!$players) {
		return 'No ranked characters yet.';
	}

	// `vocation` is an id; the names live in one setting, indexed by that id
	// (the same list the online page shows - tools/seed_site.py seeds it).
	$vocations = setting('core.vocations');

	$lines = [];
	foreach ($players as $index => $player) {
		$vocationId = (int) $player['vocation'];
		$vocation = is_array($vocations) && isset($vocations[$vocationId])
			? $vocations[$vocationId]
			: 'vocation ' . $vocationId;

		$lines[] = sprintf('%d. **%s** - level %d, %s',
			$index + 1,
			$player['name'],
			(int) $player['level'],
			$vocation
		);
	}

	return implode("\n", $lines)
		. "\nFull ranking: <https://elysia.pl/?subtopic=highscores>";
}

function discord_handle_command(string $name): string
{
	switch ($name) {
		case 'online':
			return command_online();
		case 'rates':
			return command_rates();
		case 'season':
			return command_season();
		case 'connect':
			return command_connect();
		case 'top':
			return command_top();
		default:
			return 'Unknown command - try again after the commands are registered.';
	}
}

$body = file_get_contents('php://input');
if ($body === false || $body === '') {
	discord_respond(['error' => 'empty request'], 400);
}

// Anything unsigned is not from Discord: answer 401 and stop here.
if (!discord_signature_valid($body)) {
	discord_respond(['error' => 'invalid request signature'], 401);
}

$interaction = json_decode($body, true);
if (!is_array($interaction)) {
	discord_respond(['error' => 'malformed interaction'], 400);
}

$type = (int) ($interaction['type'] ?? 0);
if ($type === DISCORD_PING) {
	discord_respond(['type' => DISCORD_RESPONSE_PONG]);
}

if ($type !== DISCORD_APPLICATION_COMMAND) {
	discord_respond(['error' => 'unsupported interaction type'], 400);
}

$name = (string) ($interaction['data']['name'] ?? '');
try {
	$answer = discord_handle_command($name);
}
catch (Throwable $e) {
	$answer = 'Something went wrong on our side - please try again in a moment.';
}

discord_respond([
	'type' => DISCORD_RESPONSE_REPLY,
	'data' => [
		'content' => $answer,
		'flags' => DISCORD_FLAG_EPHEMERAL,
	],
]);
