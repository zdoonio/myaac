<?php

if(PAGE !== 'news' || !$db->hasTable(TABLE_PREFIX . 'polls')) {
	return;
}

$poll = $db->query('SELECT `id`, `question` FROM `' . TABLE_PREFIX . 'polls` WHERE `hide` = 0 ORDER BY `date` DESC LIMIT 1');
if($poll->rowCount() > 0) {
	$poll = $poll->fetch(PDO::FETCH_ASSOC);
	$twig->display('poll.html.twig', array(
		'poll' => $poll
	));
}
