<?php
declare(strict_types=1);

$GLOBALS['cms_hooks'] = $GLOBALS['cms_hooks'] ?? [];

function cms_add_hook(string $name, callable $callback): void
{
    $GLOBALS['cms_hooks'][$name] = $GLOBALS['cms_hooks'][$name] ?? [];
    $GLOBALS['cms_hooks'][$name][] = $callback;
}

function cms_do_hook(string $name, ...$args): void
{
    foreach ($GLOBALS['cms_hooks'][$name] ?? [] as $callback) {
        $callback(...$args);
    }
}

function cms_collect_hook_output(string $name, ...$args): string
{
    ob_start();
    cms_do_hook($name, ...$args);
    return trim((string) ob_get_clean());
}

function cms_sync_plugins(): void
{
    $pluginDirs = glob(cms_plugins_directory_path() . '/*', GLOB_ONLYDIR) ?: [];
    $db = cms_db();

    foreach ($pluginDirs as $dir) {
        $manifestFile = $dir . '/plugin.json';
        if (!is_file($manifestFile)) {
            continue;
        }

        $manifest = json_decode((string) file_get_contents($manifestFile), true);
        if (!is_array($manifest) || empty($manifest['slug']) || empty($manifest['name'])) {
            continue;
        }

        $stmt = $db->prepare('SELECT id, enabled FROM cms_plugins WHERE slug = ? LIMIT 1');
        $stmt->execute([$manifest['slug']]);
        $existing = $stmt->fetch();

        if ($existing) {
            $update = $db->prepare('UPDATE cms_plugins SET name = ?, version = ?, description = ?, entry_file = ?, homepage = ?, updated_at = CURRENT_TIMESTAMP WHERE slug = ?');
            $update->execute([
                (string) $manifest['name'],
                (string) ($manifest['version'] ?? '0.0.0'),
                (string) ($manifest['description'] ?? ''),
                (string) ($manifest['entry'] ?? 'bootstrap.php'),
                (string) ($manifest['homepage'] ?? ''),
                (string) $manifest['slug'],
            ]);
            continue;
        }

        $insert = $db->prepare('INSERT INTO cms_plugins (slug, name, version, description, entry_file, enabled, source, homepage) VALUES (?, ?, ?, ?, ?, 0, ?, ?)');
        $insert->execute([
            (string) $manifest['slug'],
            (string) $manifest['name'],
            (string) ($manifest['version'] ?? '0.0.0'),
            (string) ($manifest['description'] ?? ''),
            (string) ($manifest['entry'] ?? 'bootstrap.php'),
            (string) ($manifest['source'] ?? 'local'),
            (string) ($manifest['homepage'] ?? ''),
        ]);
    }
}

function cms_all_plugins(): array
{
    cms_sync_plugins();
    return cms_db()->query('SELECT * FROM cms_plugins ORDER BY name ASC')->fetchAll();
}

function cms_set_plugin_enabled(string $slug, bool $enabled): void
{
    cms_db()->prepare('UPDATE cms_plugins SET enabled = ?, updated_at = CURRENT_TIMESTAMP WHERE slug = ?')->execute([$enabled ? 1 : 0, $slug]);
}

function cms_load_enabled_plugins(): void
{
    cms_sync_plugins();
    $plugins = cms_db()->query('SELECT * FROM cms_plugins WHERE enabled = 1 ORDER BY name ASC')->fetchAll();
    foreach ($plugins as $plugin) {
        $path = cms_plugins_directory_path() . '/' . $plugin['slug'] . '/' . ($plugin['entry_file'] ?: 'bootstrap.php');
        if (is_file($path)) {
            require_once $path;
        }
    }
}

function cms_plugin_store_index(): array
{
    $catalog = cms_plugin_store_catalog();
    $index = [];
    foreach ($catalog as $item) {
        if (!is_array($item)) {
            continue;
        }
        $slug = trim((string) ($item['slug'] ?? ''));
        if (!preg_match('/^[a-z0-9\-]+$/', $slug)) {
            continue;
        }
        $index[$slug] = $item;
    }
    return $index;
}

function cms_plugin_has_update(array $plugin, array $storeItem): bool
{
    $localVersion = (string) ($plugin['version'] ?? '0.0.0');
    $remoteVersion = (string) ($storeItem['version'] ?? '0.0.0');
    return version_compare($remoteVersion, $localVersion, '>');
}

function cms_plugin_updates_map(array $plugins, array $storeIndex): array
{
    $map = [];
    foreach ($plugins as $plugin) {
        $slug = (string) ($plugin['slug'] ?? '');
        if ($slug === '' || !isset($storeIndex[$slug])) {
            continue;
        }
        $map[$slug] = [
            'has_update' => cms_plugin_has_update($plugin, $storeIndex[$slug]),
            'remote' => $storeIndex[$slug],
        ];
    }
    return $map;
}

function cms_plugin_temp_dir(): string
{
    $dir = __DIR__ . '/../storage/tmp';
    if (!is_dir($dir)) {
        mkdir($dir, 0750, true);
    }
    return $dir;
}

function cms_local_store_manifest_path(): string
{
    return __DIR__ . '/../updates/plugins.json';
}

function cms_local_store_catalog(): array
{
    $path = cms_local_store_manifest_path();
    if (!is_file($path)) {
        return [];
    }

    $decoded = json_decode((string) file_get_contents($path), true);
    if (!is_array($decoded) || !isset($decoded['plugins']) || !is_array($decoded['plugins'])) {
        return [];
    }

    return $decoded['plugins'];
}

function cms_local_store_add_plugin(array $plugin): void
{
    $slug = trim((string) ($plugin['slug'] ?? ''));
    if (!preg_match('/^[a-z0-9\-]+$/', $slug)) {
        throw new InvalidArgumentException('Nieprawidlowy slug pluginu do sklepu.');
    }

    $catalog = cms_local_store_catalog();
    $updated = false;
    foreach ($catalog as $idx => $item) {
        if ((string) ($item['slug'] ?? '') !== $slug) {
            continue;
        }
        $catalog[$idx] = array_merge($item, $plugin);
        $updated = true;
        break;
    }
    if (!$updated) {
        $catalog[] = $plugin;
    }

    $payload = [
        'plugins' => array_values($catalog),
    ];

    $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if (!is_string($json)) {
        throw new RuntimeException('Nie mozna zapisac manifestu sklepu pluginow.');
    }

    file_put_contents(cms_local_store_manifest_path(), $json . "\n");
}

function cms_rrmdir(string $dir): void
{
    if (!is_dir($dir)) {
        return;
    }
    $items = scandir($dir);
    if (!is_array($items)) {
        return;
    }
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }
        $path = $dir . '/' . $item;
        if (is_dir($path)) {
            cms_rrmdir($path);
        } else {
            @unlink($path);
        }
    }
    @rmdir($dir);
}

function cms_copy_dir(string $source, string $destination): void
{
    if (!is_dir($source)) {
        throw new RuntimeException('Brak katalogu z pluginem do skopiowania.');
    }
    if (!is_dir($destination) && !mkdir($destination, 0750, true) && !is_dir($destination)) {
        throw new RuntimeException('Nie mozna utworzyc katalogu pluginu docelowego.');
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );

    foreach ($iterator as $item) {
        $targetPath = $destination . '/' . $iterator->getSubPathName();
        if ($item->isDir()) {
            if (!is_dir($targetPath)) {
                mkdir($targetPath, 0750, true);
            }
            continue;
        }
        if (!copy((string) $item, $targetPath)) {
            throw new RuntimeException('Nie udalo sie skopiowac plikow pluginu.');
        }
    }
}

function cms_find_plugin_root_in_extracted(string $extractDir, string $expectedSlug = ''): string
{
    if (is_file($extractDir . '/plugin.json')) {
        $manifest = json_decode((string) file_get_contents($extractDir . '/plugin.json'), true);
        if (!is_array($manifest) || $expectedSlug === '' || (string) ($manifest['slug'] ?? '') === $expectedSlug) {
            return $extractDir;
        }
    }

    $firstValidCandidate = '';

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($extractDir, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );

    foreach ($iterator as $item) {
        if (!$item->isFile() || $item->getFilename() !== 'plugin.json') {
            continue;
        }

        $manifestPath = (string) $item->getPathname();
        $manifest = json_decode((string) file_get_contents($manifestPath), true);
        if (!is_array($manifest) || empty($manifest['slug']) || empty($manifest['name'])) {
            continue;
        }

        $candidateRoot = dirname($manifestPath);
        $candidateSlug = (string) ($manifest['slug'] ?? '');

        if ($expectedSlug !== '' && $candidateSlug === $expectedSlug) {
            return $candidateRoot;
        }

        if ($firstValidCandidate === '') {
            $firstValidCandidate = $candidateRoot;
        }
    }

    if ($firstValidCandidate !== '') {
        return $firstValidCandidate;
    }

    throw new RuntimeException('Paczka pluginu nie zawiera poprawnego pliku plugin.json.');
}

function cms_install_or_update_plugin_from_zip_path(string $zipPath, string $expectedSlug = ''): array
{
    if (!is_file($zipPath)) {
        throw new RuntimeException('Brak paczki ZIP pluginu.');
    }

    if (!class_exists('ZipArchive')) {
        throw new RuntimeException('Brak rozszerzenia ZipArchive na serwerze.');
    }

    $extractDir = cms_plugin_temp_dir() . '/plugin-upload-' . bin2hex(random_bytes(4));
    mkdir($extractDir, 0750, true);

    $zip = new ZipArchive();
    if ($zip->open($zipPath) !== true) {
        cms_rrmdir($extractDir);
        throw new RuntimeException('Paczka pluginu nie jest poprawnym ZIP.');
    }

    for ($i = 0; $i < $zip->numFiles; $i++) {
        $entryName = (string) $zip->getNameIndex($i);
        if ($entryName === '') {
            continue;
        }
        if (str_starts_with($entryName, '/') || str_contains($entryName, '..')) {
            $zip->close();
            cms_rrmdir($extractDir);
            throw new RuntimeException('Paczka pluginu zawiera niedozwolone sciezki.');
        }
    }

    $zip->extractTo($extractDir);
    $zip->close();

    try {
        $pluginRoot = cms_find_plugin_root_in_extracted($extractDir, $expectedSlug);
        $manifest = json_decode((string) file_get_contents($pluginRoot . '/plugin.json'), true);
        if (!is_array($manifest) || empty($manifest['slug']) || empty($manifest['name'])) {
            throw new RuntimeException('plugin.json w paczce jest niepoprawny.');
        }

        $slug = (string) $manifest['slug'];
        if ($expectedSlug !== '' && $slug !== $expectedSlug) {
            throw new RuntimeException('Slug pluginu w paczce nie zgadza sie z oczekiwanym slugiem.');
        }

        $pluginsDir = cms_plugins_directory_path();
        if (!is_dir($pluginsDir)) {
            mkdir($pluginsDir, 0750, true);
        }

        $targetDir = $pluginsDir . '/' . $slug;
        $backupDir = $pluginsDir . '/.' . $slug . '-backup-' . bin2hex(random_bytes(3));
        $hadPrevious = is_dir($targetDir);
        if ($hadPrevious && !rename($targetDir, $backupDir)) {
            throw new RuntimeException('Nie mozna przygotowac backupu pluginu do aktualizacji.');
        }

        try {
            cms_copy_dir($pluginRoot, $targetDir);
            if ($hadPrevious) {
                cms_rrmdir($backupDir);
            }
        } catch (Throwable $e) {
            cms_rrmdir($targetDir);
            if ($hadPrevious) {
                @rename($backupDir, $targetDir);
            }
            throw $e;
        }

        cms_sync_plugins();

        cms_db()->prepare('UPDATE cms_plugins SET source = ?, homepage = ?, updated_at = CURRENT_TIMESTAMP WHERE slug = ?')->execute([
            (string) ($manifest['source'] ?? 'upload'),
            (string) ($manifest['homepage'] ?? ''),
            $slug,
        ]);

        return [
            'slug' => $slug,
            'name' => (string) $manifest['name'],
            'version' => (string) ($manifest['version'] ?? '0.0.0'),
            'updated' => $hadPrevious,
        ];
    } finally {
        cms_rrmdir($extractDir);
    }
}

function cms_render_plugin_slot(string $pluginSlug, array $page): string
{
    $pluginSlug = trim($pluginSlug);
    if ($pluginSlug === '') {
        return '';
    }

    $output = cms_collect_hook_output('plugin_render', $pluginSlug, $page, 'builder_slot');
    if ($output !== '') {
        return $output;
    }

    return '<div class="plugin-note"><strong>'
        . htmlspecialchars($pluginSlug, ENT_QUOTES, 'UTF-8')
        . '</strong>Brak renderu dla pluginu w kontenerze buildera.</div>';
}

function cms_install_or_update_plugin_from_store(string $slug): array
{
    $slug = trim($slug);
    if (!preg_match('/^[a-z0-9\-]+$/', $slug)) {
        throw new InvalidArgumentException('Nieprawidlowy slug pluginu.');
    }

    $store = cms_plugin_store_index();
    if (!isset($store[$slug])) {
        throw new RuntimeException('Plugin nie istnieje w katalogu sklepu.');
    }

    $downloadUrl = trim((string) ($store[$slug]['download_url'] ?? ''));
    if (!preg_match('#^https?://#i', $downloadUrl)) {
        throw new RuntimeException('Brak poprawnego download_url dla pluginu.');
    }

    if (!class_exists('ZipArchive')) {
        throw new RuntimeException('Brak rozszerzenia ZipArchive na serwerze.');
    }

    $context = stream_context_create([
        'http' => [
            'timeout' => 20,
            'user_agent' => 'PortfolioCMS/1.0',
        ],
        'ssl' => [
            'verify_peer' => true,
            'verify_peer_name' => true,
        ],
    ]);

    $zipRaw = @file_get_contents($downloadUrl, false, $context);
    if ($zipRaw === false || $zipRaw === '') {
        throw new RuntimeException('Nie udalo sie pobrac paczki pluginu z serwera.');
    }

    $tmpBase = cms_plugin_temp_dir() . '/plugin-' . $slug . '-' . bin2hex(random_bytes(4));
    $zipFile = $tmpBase . '.zip';
    $extractDir = $tmpBase . '-extract';
    file_put_contents($zipFile, $zipRaw);
    mkdir($extractDir, 0750, true);

    $zip = new ZipArchive();
    if ($zip->open($zipFile) !== true) {
        @unlink($zipFile);
        cms_rrmdir($extractDir);
        throw new RuntimeException('Pobrana paczka pluginu nie jest poprawnym ZIP.');
    }
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $entryName = (string) $zip->getNameIndex($i);
        if ($entryName === '') {
            continue;
        }
        if (str_starts_with($entryName, '/') || str_contains($entryName, '..')) {
            $zip->close();
            @unlink($zipFile);
            cms_rrmdir($extractDir);
            throw new RuntimeException('Paczka pluginu zawiera niedozwolone sciezki.');
        }
    }
    $zip->extractTo($extractDir);
    $zip->close();
    @unlink($zipFile);

    try {
        $pluginRoot = cms_find_plugin_root_in_extracted($extractDir, $slug);
        $manifest = json_decode((string) file_get_contents($pluginRoot . '/plugin.json'), true);
        if (!is_array($manifest) || empty($manifest['slug']) || empty($manifest['name'])) {
            throw new RuntimeException('plugin.json w paczce jest niepoprawny.');
        }
        if ((string) $manifest['slug'] !== $slug) {
            throw new RuntimeException('Slug pluginu w paczce nie zgadza sie z wybranym pluginem.');
        }

        $pluginsDir = cms_plugins_directory_path();
        if (!is_dir($pluginsDir)) {
            mkdir($pluginsDir, 0750, true);
        }

        $targetDir = $pluginsDir . '/' . $slug;
        $backupDir = $pluginsDir . '/.' . $slug . '-backup-' . bin2hex(random_bytes(3));
        $hadPrevious = is_dir($targetDir);
        if ($hadPrevious && !rename($targetDir, $backupDir)) {
            throw new RuntimeException('Nie mozna przygotowac backupu pluginu do aktualizacji.');
        }

        try {
            cms_copy_dir($pluginRoot, $targetDir);
            if ($hadPrevious) {
                cms_rrmdir($backupDir);
            }
        } catch (Throwable $e) {
            cms_rrmdir($targetDir);
            if ($hadPrevious) {
                @rename($backupDir, $targetDir);
            }
            throw $e;
        }

        cms_sync_plugins();

        $db = cms_db();
        $stmt = $db->prepare('UPDATE cms_plugins SET source = ?, homepage = ?, updated_at = CURRENT_TIMESTAMP WHERE slug = ?');
        $stmt->execute([
            (string) ($manifest['source'] ?? 'store'),
            (string) ($manifest['homepage'] ?? ($store[$slug]['repository'] ?? '')),
            $slug,
        ]);

        return [
            'slug' => $slug,
            'name' => (string) $manifest['name'],
            'version' => (string) ($manifest['version'] ?? '0.0.0'),
            'updated' => $hadPrevious,
        ];
    } finally {
        cms_rrmdir($extractDir);
    }
}

function cms_enabled_plugins(): array
{
    cms_sync_plugins();
    return cms_db()->query('SELECT * FROM cms_plugins WHERE enabled = 1 ORDER BY name ASC')->fetchAll();
}

function cms_page_plugin_placements(int $pageId): array
{
    $stmt = cms_db()->prepare('SELECT * FROM cms_plugin_placements WHERE page_id = ? ORDER BY sort_order ASC, id ASC');
    $stmt->execute([$pageId]);
    return $stmt->fetchAll();
}

function cms_save_page_plugin_placements(int $pageId, array $placements): void
{
    if ($pageId <= 0) {
        throw new InvalidArgumentException('Nieprawidlowe ID strony.');
    }

    $enabled = [];
    foreach (cms_enabled_plugins() as $plugin) {
        $enabled[(string) $plugin['slug']] = true;
    }

    $normalized = [];
    foreach ($placements as $idx => $item) {
        if (!is_array($item)) {
            continue;
        }
        $slug = trim((string) ($item['slug'] ?? ''));
        if ($slug === '' || !isset($enabled[$slug])) {
            continue;
        }
        $position = (string) ($item['position'] ?? 'after_content');
        $position = in_array($position, ['before_content', 'after_content'], true) ? $position : 'after_content';
        $normalized[] = [
            'slug' => $slug,
            'position' => $position,
            'sort_order' => $idx,
        ];
    }

    $db = cms_db();
    $db->prepare('DELETE FROM cms_plugin_placements WHERE page_id = ?')->execute([$pageId]);

    if ($normalized === []) {
        return;
    }

    $stmt = $db->prepare('INSERT INTO cms_plugin_placements (page_id, plugin_slug, position, sort_order) VALUES (?, ?, ?, ?)');
    foreach ($normalized as $row) {
        $stmt->execute([$pageId, $row['slug'], $row['position'], $row['sort_order']]);
    }
}

function cms_render_page_plugins(array $page, string $position = 'after_content'): string
{
    $pageId = (int) ($page['id'] ?? 0);
    if ($pageId <= 0) {
        return '';
    }

    $position = in_array($position, ['before_content', 'after_content'], true) ? $position : 'after_content';
    $placements = cms_page_plugin_placements($pageId);
    if ($placements === []) {
        return '';
    }

    $pluginNames = [];
    foreach (cms_all_plugins() as $plugin) {
        $pluginNames[(string) $plugin['slug']] = (string) $plugin['name'];
    }

    ob_start();
    foreach ($placements as $placement) {
        if ((string) ($placement['position'] ?? '') !== $position) {
            continue;
        }
        $slug = (string) ($placement['plugin_slug'] ?? '');
        if ($slug === '') {
            continue;
        }

        $output = cms_collect_hook_output('plugin_render', $slug, $page, $position);
        if ($output !== '') {
            echo $output;
            continue;
        }

        $name = $pluginNames[$slug] ?? $slug;
        echo '<div class="plugin-note"><strong>' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . '</strong>Plugin jest umieszczony na stronie, ale nie dostarcza renderu dla hooka plugin_render.</div>';
    }

    return trim((string) ob_get_clean());
}
