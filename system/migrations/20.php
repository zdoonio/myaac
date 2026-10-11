<?php

use MyAAC\Models\Settings as ModelsSettings;
use MyAAC\Settings;

function updateHighscoresIdsHidden(): void
{
	global $db;

	if (!$db->hasTable('players')) {
		return;
	}

	$query = $db->query("SELECT `id` FROM `players` WHERE (`name` = " . $db->quote("Rook Sample") . " OR `name` = " . $db->quote("Sorcerer Sample") . " OR `name` = " . $db->quote("Druid Sample") . " OR `name` = " . $db->quote("Paladin Sample") . " OR `name` = " . $db->quote("Knight Sample") . " OR `name` = " . $db->quote("Account Manager") . ") ORDER BY `id`;");

	$highscores_ignored_ids = array();
	if ($query->rowCount() > 0) {
		foreach ($query->fetchAll() as $result)
			$highscores_ignored_ids[] = (int)$result['id'];
	} else {
		$highscores_ignored_ids[] = 0;
	}

	// Add to the ids that are already hidden, never replace them. This setting is
	// curated: besides the samples above it carries the staff and the test
	// characters (Elysia hides 15 rows, written by tools/seed_site.py), and this
	// migration runs again for every database that falls behind -- replacing the
	// whole value silently put the staff back into the highscores.
	$current = ModelsSettings::where(['name' => 'core', 'key' => 'highscores_ids_hidden'])->first();
	if ($current) {
		foreach (explode(',', (string)$current->value) as $id) {
			$id = (int)trim($id);
			if ($id > 0 && !in_array($id, $highscores_ignored_ids, true)) {
				$highscores_ignored_ids[] = $id;
			}
		}
	}

	$settings = Settings::getInstance();
	$settings->updateInDatabase('core', 'highscores_ids_hidden', implode(', ', $highscores_ignored_ids));
}

$up = function () {
	updateHighscoresIdsHidden();
};

$down = function () {
	$settings = Settings::getInstance();
	$settings->updateInDatabase('core', 'highscores_ids_hidden', '0');
};
