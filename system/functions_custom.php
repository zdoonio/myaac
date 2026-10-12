<?php
/**
 * Custom functions
 *
 * @package   MyAAC
 * @author    Slawkens <slawkens@gmail.com>, Lee
 * @copyright 2020 MyAAC
 * @link      https://my-aac.org
 */

// Insert your custom functions here.

/**
 * Members currently online on our Discord, from the guild's public widget (T491).
 *
 * The invite and the guild id live in the site settings, so the Discord page,
 * the clients' cacheinfo endpoint and the in-game notice all read one source.
 * The answer is cached: every client calls cacheinfo from its login screen, and
 * Discord should see a couple of requests per minute, not one per player.
 * Returns null when the widget is disabled or unreachable -- callers then simply
 * leave the field out instead of failing.
 */
function discord_widget_presence($ttl = 60)
{
	$guildId = setting('core.discord_guild_id');
	if (empty($guildId)) {
		return null;
	}

	return Cache::remember('discord_widget_presence_' . $guildId, $ttl, function () use ($guildId) {
		$url = 'https://discord.com/api/guilds/' . rawurlencode($guildId) . '/widget.json';
		$body = null;

		if (function_exists('curl_init')) {
			$curl = curl_init($url);
			curl_setopt_array($curl, [
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_TIMEOUT => 2,
				CURLOPT_CONNECTTIMEOUT => 2,
				CURLOPT_USERAGENT => 'Elysia-AAC',
			]);
			$body = curl_exec($curl);
			curl_close($curl);
		}
		else {
			$context = stream_context_create(['http' => ['timeout' => 2, 'user_agent' => 'Elysia-AAC']]);
			$body = @file_get_contents($url, false, $context);
		}

		if (!$body) {
			return null;
		}

		$data = json_decode($body, true);
		return isset($data['presence_count']) ? (int) $data['presence_count'] : null;
	});
}
