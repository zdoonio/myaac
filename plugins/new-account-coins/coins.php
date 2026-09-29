<?php
/**
 * Starting balance of Tibia coins for every newly created account.
 *
 * Hooks into HOOK_ACCOUNT_CREATE_AFTER_SAVED, which fires right after MyAAC
 * stores the account (system/pages/account/create.php). The hook runner
 * extract()s the parameters, so $account is the freshly saved account and $db
 * is the database layer.
 */

defined('MYAAC') or die('Direct access not allowed!');

if (!isset($account) || !$account->isLoaded()) {
	return;
}

// Kept in a setting so it can be changed from the admin panel without touching
// this file; MyAAC has no such setting by default, hence the fallback.
$coins = (int)setting('core.new_account_coins');
if ($coins <= 0) {
	$coins = 1000;
}

$accountId = (int)$account->getId();
$current = $db->query('SELECT `tibia_coins` FROM `accounts` WHERE `id` = ' . $accountId)->fetchColumn();

$account->setCustomField('tibia_coins', (int)$current + $coins);

$account->logAction('Received ' . $coins . ' tibia coins as a new account.');
