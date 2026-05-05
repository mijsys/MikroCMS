<?php
declare(strict_types=1);

const CMS_DEFAULT_UPDATE_MANIFEST_URL = 'https://raw.githubusercontent.com/mijsys/MikroCMS/main/updates/cms-update.json';
const CMS_DEFAULT_STORE_MANIFEST_URL = 'https://raw.githubusercontent.com/mijsys/MikroCMS/main/updates/store-db.json';
const CMS_DEFAULT_PLUGIN_MANIFEST_URL = 'https://raw.githubusercontent.com/mijsys/MikroCMS/main/updates/plugins.json';

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

    $context = stream_context_create([
        'http' => [
            'timeout' => max(2, $timeout),
            'user_agent' => 'PortfolioCMS/1.0',
        ],
        'ssl' => [
            'verify_peer' => true,
            'verify_peer_name' => true,
        ],
    ]);

    $raw = @file_get_contents($url, false, $context);
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
    return cms_normalize_version_string(cms_get_setting('cms_core_version', '1.0.1'), '1.0.1');
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
