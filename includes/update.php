<?php
declare(strict_types=1);

const CMS_DEFAULT_UPDATE_MANIFEST_URL = 'https://raw.githubusercontent.com/mijsys/MikroCMS/main/updates/cms-update.json';
const CMS_DEFAULT_STORE_MANIFEST_URL = 'https://raw.githubusercontent.com/mijsys/MikroCMS/main/updates/store-db.json';
const CMS_DEFAULT_PLUGIN_MANIFEST_URL = 'https://raw.githubusercontent.com/mijsys/MikroCMS/main/updates/plugins.json';
const CMS_CODE_VERSION = '1.0.8';

function cms_sanitize_remote_manifest_url(string $url): string
{
    $url = trim($url);
    return preg_match('#^https?://#i', $url) ? $url : '';
}

function cms_update_sources(): array
{
    $cmsManifestUrl = cms_sanitize_remote_manifest_url(cms_get_setting('cms_update_manifest_url', CMS_DEFAULT_UPDATE_MANIFEST_URL));
    $storeDbManifestUrl = cms_sanitize_remote_manifest_url(cms_get_setting('store_db_manifest_url', CMS_DEFAULT_STORE_MANIFEST_URL));
    $pluginStoreManifestUrl = cms_sanitize_remote_manifest_url(cms_get_setting('plugin_store_manifest_url', CMS_DEFAULT_PLUGIN_MANIFEST_URL));
    $pluginCatalogKey = trim(cms_get_setting('plugin_store_catalog_key', 'plugins'));
    $pluginCatalogKey = preg_match('/^[a-zA-Z0-9_\-\.]+$/', $pluginCatalogKey) ? $pluginCatalogKey : 'plugins';

    $pluginsDirectory = trim(cms_get_setting('plugin_store_directory', 'plugins'));
    $pluginsDirectory = trim(str_replace('\\', '/', $pluginsDirectory), '/');
    if ($pluginsDirectory === '' || str_contains($pluginsDirectory, '..')) {
        $pluginsDirectory = 'plugins';
    }

    return [
        'cms_update_manifest_url' => $cmsManifestUrl,
        'store_db_manifest_url' => $storeDbManifestUrl,
        'plugin_store_manifest_url' => $pluginStoreManifestUrl,
        'plugin_store_catalog_key' => $pluginCatalogKey,
        'plugin_store_directory' => $pluginsDirectory,
    ];
}

function cms_fetch_remote_json(string $url, int $timeout = 5): ?array
{
    $url = cms_sanitize_remote_manifest_url($url);
    if ($url === '') {
        return null;
    }

    // Unikamy stalego cache CDN/proxy dla manifestow aktualizacji.
    $cacheBustedUrl = $url
        . (str_contains($url, '?') ? '&' : '?')
        . 'cb=' . rawurlencode(CMS_CODE_VERSION . '-' . gmdate('YmdHi'));

    $context = stream_context_create([
        'http' => [
            'timeout' => max(2, $timeout),
            'user_agent' => 'PortfolioCMS/1.0',
            'header' => "Cache-Control: no-cache\r\nPragma: no-cache\r\n",
        ],
        'ssl' => [
            'verify_peer' => true,
            'verify_peer_name' => true,
        ],
    ]);

    $raw = @file_get_contents($cacheBustedUrl, false, $context);
    if ($raw === false || trim($raw) === '') {
        return null;
    }

    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : null;
}

function cms_ensure_plugins_directory(): void
{
    $pluginsDir = cms_plugins_directory_path();
    if (!is_dir($pluginsDir)) {
        mkdir($pluginsDir, 0750, true);
    }
}

function cms_plugins_directory_path(): string
{
    $sources = cms_update_sources();
    return __DIR__ . '/../' . $sources['plugin_store_directory'];
}

function cms_normalize_version_string(string $version, string $fallback = '0.0.0'): string
{
    $version = trim($version);
    if ($version === '') {
        return $fallback;
    }
    return preg_match('/^[0-9A-Za-z\.\-\+_]+$/', $version) ? $version : $fallback;
}

function cms_core_version(): string
{
    $settingVersion = cms_normalize_version_string(cms_get_setting('cms_core_version', CMS_CODE_VERSION), CMS_CODE_VERSION);
    $codeVersion = cms_normalize_version_string(CMS_CODE_VERSION, '0.0.0');

    // Chroni przed starym wpisem w DB po wdrozeniu nowej wersji kodu.
    if (version_compare($codeVersion, $settingVersion, '>')) {
        cms_set_setting('cms_core_version', $codeVersion);
        return $codeVersion;
    }

    return $settingVersion;
}

function cms_core_update_info(): array
{
    $sources = cms_update_sources();
    $currentVersion = cms_core_version();
    $manifest = cms_fetch_remote_json((string) ($sources['cms_update_manifest_url'] ?? ''), 4);

    if (!is_array($manifest)) {
        return [
            'checked' => false,
            'has_update' => false,
            'current_version' => $currentVersion,
            'remote_version' => $currentVersion,
            'manifest' => null,
        ];
    }

    $remoteVersionRaw = (string) ($manifest['latest_version'] ?? ($manifest['version'] ?? $currentVersion));
    $remoteVersion = cms_normalize_version_string($remoteVersionRaw, $currentVersion);

    return [
        'checked' => true,
        'has_update' => version_compare($remoteVersion, $currentVersion, '>'),
        'current_version' => $currentVersion,
        'remote_version' => $remoteVersion,
        'manifest' => $manifest,
    ];
}

function cms_core_update_download_url(array $coreUpdateInfo): string
{
    $manifest = $coreUpdateInfo['manifest'] ?? null;
    if (!is_array($manifest)) {
        return '';
    }

    $url = (string) ($manifest['download_url'] ?? '');
    return cms_sanitize_remote_manifest_url($url);
}

function cms_core_temp_dir(): string
{
    $dir = __DIR__ . '/../storage/tmp';
    if (!is_dir($dir)) {
        mkdir($dir, 0750, true);
    }
    return $dir;
}

function cms_core_delete_path(string $path): void
{
    if (is_link($path) || is_file($path)) {
        @unlink($path);
        return;
    }

    if (!is_dir($path)) {
        return;
    }

    $items = scandir($path);
    if (!is_array($items)) {
        return;
    }

    foreach ($items as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }
        cms_core_delete_path($path . '/' . $item);
    }

    @rmdir($path);
}

function cms_core_copy_path(string $source, string $destination): void
{
    if (is_file($source)) {
        $destDir = dirname($destination);
        if (!is_dir($destDir) && !mkdir($destDir, 0750, true) && !is_dir($destDir)) {
            throw new RuntimeException('Nie mozna utworzyc katalogu docelowego aktualizacji.');
        }
        if (!copy($source, $destination)) {
            throw new RuntimeException('Nie mozna skopiowac pliku aktualizacji core.');
        }
        return;
    }

    if (!is_dir($source)) {
        throw new RuntimeException('Brak zrodla do kopiowania aktualizacji core.');
    }

    if (!is_dir($destination) && !mkdir($destination, 0750, true) && !is_dir($destination)) {
        throw new RuntimeException('Nie mozna utworzyc katalogu aktualizacji core.');
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
        $targetDir = dirname($targetPath);
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0750, true);
        }
        if (!copy((string) $item, $targetPath)) {
            throw new RuntimeException('Nie mozna skopiowac plikow aktualizacji core.');
        }
    }
}

function cms_core_find_project_root_in_extracted(string $extractDir): string
{
    if (is_file($extractDir . '/index.php') && is_file($extractDir . '/includes/bootstrap.php')) {
        return $extractDir;
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($extractDir, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );

    foreach ($iterator as $item) {
        if (!$item->isDir()) {
            continue;
        }
        $candidate = (string) $item->getPathname();
        if (is_file($candidate . '/index.php') && is_file($candidate . '/includes/bootstrap.php')) {
            return $candidate;
        }
    }

    throw new RuntimeException('Paczka aktualizacji CMS nie zawiera poprawnego rdzenia projektu.');
}

function cms_install_or_update_core_from_manifest(): array
{
    $coreUpdate = cms_core_update_info();
    if (empty($coreUpdate['has_update'])) {
        throw new RuntimeException('Brak nowej aktualizacji CMS do instalacji.');
    }

    $downloadUrl = cms_core_update_download_url($coreUpdate);
    if ($downloadUrl === '') {
        throw new RuntimeException('Manifest aktualizacji nie zawiera poprawnego download_url.');
    }

    if (!class_exists('ZipArchive')) {
        throw new RuntimeException('Brak rozszerzenia ZipArchive na serwerze.');
    }

    $context = stream_context_create([
        'http' => [
            'timeout' => 30,
            'user_agent' => 'MikroCMS-CoreUpdater/1.0',
            'header' => "Cache-Control: no-cache\r\nPragma: no-cache\r\n",
        ],
        'ssl' => [
            'verify_peer' => true,
            'verify_peer_name' => true,
        ],
    ]);

    $zipRaw = @file_get_contents($downloadUrl, false, $context);
    if ($zipRaw === false || $zipRaw === '') {
        throw new RuntimeException('Nie udalo sie pobrac paczki aktualizacji CMS.');
    }

    $tmpBase = cms_core_temp_dir() . '/core-update-' . bin2hex(random_bytes(4));
    $zipFile = $tmpBase . '.zip';
    $extractDir = $tmpBase . '-extract';
    $backupDir = $tmpBase . '-backup';
    file_put_contents($zipFile, $zipRaw);
    mkdir($extractDir, 0750, true);
    mkdir($backupDir, 0750, true);

    $zip = new ZipArchive();
    if ($zip->open($zipFile) !== true) {
        @unlink($zipFile);
        cms_core_delete_path($extractDir);
        cms_core_delete_path($backupDir);
        throw new RuntimeException('Pobrana paczka CMS nie jest poprawnym ZIP.');
    }

    for ($i = 0; $i < $zip->numFiles; $i++) {
        $entryName = (string) $zip->getNameIndex($i);
        if ($entryName === '') {
            continue;
        }
        if (str_starts_with($entryName, '/') || str_contains($entryName, '..')) {
            $zip->close();
            @unlink($zipFile);
            cms_core_delete_path($extractDir);
            cms_core_delete_path($backupDir);
            throw new RuntimeException('Paczka aktualizacji CMS zawiera niedozwolone sciezki.');
        }
    }

    $zip->extractTo($extractDir);
    $zip->close();
    @unlink($zipFile);

    $targetRoot = realpath(__DIR__ . '/..');
    if (!is_string($targetRoot) || $targetRoot === '') {
        cms_core_delete_path($extractDir);
        cms_core_delete_path($backupDir);
        throw new RuntimeException('Nie mozna ustalic katalogu glownego CMS.');
    }

    $sourceRoot = cms_core_find_project_root_in_extracted($extractDir);
    // plugins/ i themes/ maja wlasny mechanizm aktualizacji — nie dotykamy ich przy core update.
    $protected = ['.git', 'storage', 'uploads', 'data', 'plugins', 'themes', 'releases'];

    $entries = scandir($sourceRoot);
    if (!is_array($entries)) {
        cms_core_delete_path($extractDir);
        cms_core_delete_path($backupDir);
        throw new RuntimeException('Nie mozna odczytac paczki aktualizacji CMS.');
    }

    $copyEntries = [];
    foreach ($entries as $entry) {
        if ($entry === '.' || $entry === '..' || in_array($entry, $protected, true)) {
            continue;
        }
        $copyEntries[] = $entry;
    }

    // Chroni przed zastosowaniem niepelnej paczki (np. uszkodzony ZIP lub zly katalog zrodla).
    $requiredEntries = ['index.php', 'includes', 'admin'];
    foreach ($requiredEntries as $requiredEntry) {
        if (!in_array($requiredEntry, $copyEntries, true)) {
            cms_core_delete_path($extractDir);
            cms_core_delete_path($backupDir);
            throw new RuntimeException('Paczka aktualizacji CMS jest niepelna i zostala odrzucona.');
        }
    }

    $existingBefore = [];
    $backedUpEntries = [];
    $replacedEntries = [];

    try {
        foreach ($copyEntries as $entry) {
            $targetPath = $targetRoot . '/' . $entry;
            $backupPath = $backupDir . '/' . $entry;
            $existingBefore[$entry] = file_exists($targetPath);

            if ($existingBefore[$entry]) {
                cms_core_copy_path($targetPath, $backupPath);
                $backedUpEntries[$entry] = true;
            }
        }

        foreach ($copyEntries as $entry) {
            $sourcePath = $sourceRoot . '/' . $entry;
            $targetPath = $targetRoot . '/' . $entry;

            // Kopiujemy nadpisujaco, bez wstepnego usuwania katalogu docelowego.
            // To eliminuje ryzyko "wyczyszczenia" projektu, gdy kopiowanie przerwie sie w polowie.
            cms_core_copy_path($sourcePath, $targetPath);
            $replacedEntries[] = $entry;
        }

        $remoteVersion = (string) ($coreUpdate['remote_version'] ?? CMS_CODE_VERSION);
        cms_set_setting('cms_core_version', $remoteVersion);

        return [
            'from' => (string) ($coreUpdate['current_version'] ?? ''),
            'to' => $remoteVersion,
            'download_url' => $downloadUrl,
        ];
    } catch (Throwable $e) {
        foreach ($replacedEntries as $entry) {
            $targetPath = $targetRoot . '/' . $entry;
            $backupPath = $backupDir . '/' . $entry;
            if (!empty($backedUpEntries[$entry]) && file_exists($backupPath)) {
                // Przywracamy tylko wpisy, ktore istnialy przed aktualizacja.
                cms_core_copy_path($backupPath, $targetPath);
            } else {
                // Usuwamy wyłącznie nowe wpisy dodane przez nieudana aktualizacje.
                cms_core_delete_path($targetPath);
            }
        }
        throw $e;
    } finally {
        cms_core_delete_path($extractDir);
        cms_core_delete_path($backupDir);
    }
}
