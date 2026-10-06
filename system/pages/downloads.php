<?php
/**
 * Downloads — client packages served from this stack.
 *
 * The menu already linked here; the page itself never existed, so /downloads was
 * a 404. Builds live in client-downloads/, a sibling of index.php: the site
 * container bind-mounts the whole myaac/ directory, so a VPS deployment carries
 * them along with the site and nothing else has to be wired.
 *
 * The page reads a manifest instead of hardcoding filenames, so publishing a
 * build is copying a file plus one entry written by tools/pack_client.sh —
 * no markup changes, no release commit. A build listed in the manifest whose
 * file is gone is reported instead of offered, because humans move files.
 *
 * @package   MyAAC
 */

defined('MYAAC') or die('Direct access not allowed!');

$title = 'Downloads';

$dir = BASE . 'client-downloads/';
$manifestPath = $dir . 'manifest.json';
$builds = [];
$errors = [];

if (is_file($manifestPath)) {
	$decoded = json_decode((string)file_get_contents($manifestPath), true);
	if (is_array($decoded)) {
		$builds = $decoded;
	} else {
		$errors[] = 'client-downloads/manifest.json nie jest poprawnym JSON-em.';
	}
} else {
	$errors[] = 'Brak opublikowanych paczek klienta (client-downloads/manifest.json nie istnieje).';
}

// MyAAC has no size formatter; the page needs one line of it.
$humanSize = static function (int $bytes): string {
	$units = ['B', 'KB', 'MB', 'GB'];
	$i = 0;
	while ($bytes >= 1024 && $i < count($units) - 1) {
		$bytes /= 1024;
		$i++;
	}
	return ($i === 0 ? $bytes : round($bytes, 1)) . ' ' . $units[$i];
};

$available = [];
foreach ($builds as $build) {
	if (empty($build['file']) || empty($build['name'])) {
		continue;
	}

	$path = $dir . basename($build['file']);
	if (!is_file($path)) {
		$errors[] = sprintf('%s jest w manifeście, ale pliku %s nie ma w client-downloads/.',
			$build['name'], basename($build['file']));
		continue;
	}

	$build['size'] = $humanSize((int)filesize($path));
	$build['date'] = date('Y-m-d', filemtime($path));
	$build['url'] = BASE_URL . 'client-downloads/' . rawurlencode(basename($build['file']));
	$available[] = $build;
}

$webclient = trim((string)setting('core.webclient_url'));

$twig->display('downloads.html.twig', [
	'builds' => $available,
	'errors' => $errors,
	'webclient_url' => $webclient,
]);
