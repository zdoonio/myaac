<?php
/**
 * Commands
 *
 * Lists the in-game talkactions, read from the `myaac_commands` table. MyAAC
 * ships commands.html.twig and commands.form.html.twig, and both menu files
 * link here, but the page controller was missing from this fork -- so the
 * Commands entry in the Library menu led nowhere.
 *
 * @package   MyAAC
 * @link      https://my-aac.org
 */

defined('MYAAC') or die('Direct access not allowed!');
$title = 'Commands';

csrfProtect();

$action = $_REQUEST['action'] ?? '';
$id = $_REQUEST['id'] ?? null;

$canEdit = hasFlag(FLAG_CONTENT_COMMANDS) || superAdmin();

if($canEdit && !empty($action) && isRequestMethod('post'))
{
	$words = $_POST['words'] ?? '';
	$description = $_POST['description'] ?? '';

	$errors = array();

	if($action == 'add') {
		if(isset($words[0]) && isset($description[0])) {
			$exists = $db->query('SELECT `id` FROM `' . TABLE_PREFIX . 'commands` WHERE `name` = ' . $db->quote($words) . ' LIMIT 1;')->fetch();
			if($exists) {
				$errors[] = 'Command with this word already exists.';
			}
			else {
				$db->insert(TABLE_PREFIX . 'commands', array(
					'name' => $words,
					'text' => $description,
					'type' => 'player',
					'access' => 0,
					'hide' => 0
				));
				$words = $description = '';
				$action = '';
			}
		}
		else {
			$errors[] = 'Please fill all inputs.';
		}
	}
	else if($action == 'edit') {
		if(isset($words[0]) && isset($description[0])) {
			$db->query('UPDATE `' . TABLE_PREFIX . 'commands` SET `name` = ' . $db->quote($words)
				. ', `text` = ' . $db->quote($description) . ' WHERE `id` = ' . (int)$id . ';');
			$action = $words = $description = '';
		}
		else {
			$command = $db->query('SELECT `name`, `text` FROM `' . TABLE_PREFIX . 'commands` WHERE `id` = ' . (int)$id . ' LIMIT 1;')->fetch();
			if($command) {
				$words = $command['name'];
				$description = $command['text'];
			}
		}
	}

	if($action == 'delete') {
		$db->query('DELETE FROM `' . TABLE_PREFIX . 'commands` WHERE `id` = ' . (int)$id . ';');
	}
	else if($action == 'hide') {
		// The table has no ordering column, so moveup/movedown have nothing to
		// reorder -- the links the template renders are inert by design here.
		$db->query('UPDATE `' . TABLE_PREFIX . 'commands` SET `hide` = IF(`hide` = 1, 0, 1) WHERE `id` = ' . (int)$id . ';');
	}

	if(!empty($errors)) {
		$twig->display('error_box.html.twig', array('errors' => $errors));
	}
}

if($canEdit)
{
	$twig->display('commands.form.html.twig', array(
		'link' => getLink('commands?action=' . ($action == 'edit' ? 'edit' : 'add')),
		'action' => $action,
		'id' => $id,
		'words' => isset($words) ? $words : null,
		'description' => isset($description) ? $description : null
	));
}

// name/text in the table, words/description in the templates.
$commands = $db->query('SELECT `id`, `name` AS `words`, `text` AS `description`, `hide` FROM `'
	. TABLE_PREFIX . 'commands`'
	. ($canEdit ? '' : ' WHERE `hide` != 1')
	. ' ORDER BY `type`, `name`;')->fetchAll(PDO::FETCH_ASSOC);

$twig->display('commands.html.twig', array(
	'commands' => $commands,
	'last' => count($commands),
	'canEdit' => $canEdit
));
