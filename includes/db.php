<?php
declare(strict_types=1);

const CMS_SQLITE_DEFAULT_PATH = __DIR__ . '/../storage/cms.sqlite';

function cms_config_path(): string
{
    return __DIR__ . '/../storage/config.php';
}

function cms_default_config(): array
{
    return [
        'driver' => 'sqlite',
        'sqlite_path' => CMS_SQLITE_DEFAULT_PATH,
        'mysql_host' => '127.0.0.1',
        'mysql_port' => '3306',
        'mysql_database' => '',
        'mysql_username' => '',
        'mysql_password' => '',
        'mysql_charset' => 'utf8mb4',
    ];
}

function cms_load_config(bool $refresh = false): array
{
    static $config = null;
    if ($refresh) {
        $config = null;
    }
    if (is_array($config)) {
        return $config;
    }

    $config = cms_default_config();
    $path = cms_config_path();
    if (is_file($path)) {
        $loaded = require $path;
        if (is_array($loaded)) {
            $config = array_merge($config, $loaded);
        }
    }

    return $config;
}

function cms_write_config(array $config): void
{
    $normalized = array_merge(cms_default_config(), $config);
    $normalized['driver'] = ($normalized['driver'] ?? 'sqlite') === 'mysql' ? 'mysql' : 'sqlite';
    $normalized['sqlite_path'] = trim((string) ($normalized['sqlite_path'] ?? CMS_SQLITE_DEFAULT_PATH)) ?: CMS_SQLITE_DEFAULT_PATH;
    $normalized['mysql_host'] = trim((string) ($normalized['mysql_host'] ?? '127.0.0.1')) ?: '127.0.0.1';
    $normalized['mysql_port'] = trim((string) ($normalized['mysql_port'] ?? '3306')) ?: '3306';
    $normalized['mysql_database'] = trim((string) ($normalized['mysql_database'] ?? ''));
    $normalized['mysql_username'] = trim((string) ($normalized['mysql_username'] ?? ''));
    $normalized['mysql_password'] = (string) ($normalized['mysql_password'] ?? '');
    $normalized['mysql_charset'] = trim((string) ($normalized['mysql_charset'] ?? 'utf8mb4')) ?: 'utf8mb4';

    $directory = dirname(cms_config_path());
    if (!is_dir($directory)) {
        mkdir($directory, 0750, true);
    }

    $export = var_export($normalized, true);
    file_put_contents(cms_config_path(), "<?php\nreturn " . $export . ";\n");
    cms_load_config(true);
}

function cms_db_driver(): string
{
    return cms_load_config()['driver'] === 'mysql' ? 'mysql' : 'sqlite';
}

function cms_db(bool $refresh = false): PDO
{
    static $db = null;
    if ($refresh) {
        $db = null;
    }
    if ($db instanceof PDO) {
        return $db;
    }

    $config = cms_load_config();
    if (($config['driver'] ?? 'sqlite') === 'mysql') {
        if (!extension_loaded('pdo_mysql')) {
            throw new RuntimeException('Brak rozszerzenia pdo_mysql na serwerze.');
        }

        $database = trim((string) ($config['mysql_database'] ?? ''));
        $username = trim((string) ($config['mysql_username'] ?? ''));
        if ($database === '' || $username === '') {
            throw new RuntimeException('Konfiguracja MySQL jest niepelna.');
        }

        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=%s',
            $config['mysql_host'],
            $config['mysql_port'],
            $database,
            $config['mysql_charset']
        );
        $db = new PDO($dsn, $username, (string) ($config['mysql_password'] ?? ''));
    } else {
        $sqlitePath = trim((string) ($config['sqlite_path'] ?? CMS_SQLITE_DEFAULT_PATH)) ?: CMS_SQLITE_DEFAULT_PATH;
        if (!is_dir(dirname($sqlitePath))) {
            mkdir(dirname($sqlitePath), 0750, true);
        }
        $db = new PDO('sqlite:' . $sqlitePath);
    }

    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    if (cms_db_driver() === 'sqlite') {
        $db->exec('PRAGMA journal_mode=WAL');
    }

    cms_init_db($db);
    return $db;
}

function cms_init_db(PDO $db): void
{
    if (cms_db_driver() === 'mysql') {
        $db->exec("CREATE TABLE IF NOT EXISTS cms_settings (
            `key` VARCHAR(191) PRIMARY KEY,
            `value` LONGTEXT NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $db->exec("CREATE TABLE IF NOT EXISTS cms_users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(120) NOT NULL UNIQUE,
            email VARCHAR(191) NOT NULL DEFAULT '',
            password_hash VARCHAR(255) NOT NULL,
            role VARCHAR(50) NOT NULL DEFAULT 'admin',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $db->exec("CREATE TABLE IF NOT EXISTS cms_pages (
            id INT AUTO_INCREMENT PRIMARY KEY,
            parent_id INT NULL,
            title VARCHAR(191) NOT NULL,
            slug VARCHAR(191) NOT NULL UNIQUE,
            excerpt TEXT NOT NULL,
            content LONGTEXT NOT NULL,
            builder_data LONGTEXT NOT NULL,
            status VARCHAR(30) NOT NULL DEFAULT 'draft',
            is_homepage TINYINT(1) NOT NULL DEFAULT 0,
            sort_order INT NOT NULL DEFAULT 0,
            template VARCHAR(80) NOT NULL DEFAULT 'default',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $db->exec("CREATE TABLE IF NOT EXISTS cms_plugins (
            id INT AUTO_INCREMENT PRIMARY KEY,
            slug VARCHAR(191) NOT NULL UNIQUE,
            name VARCHAR(191) NOT NULL,
            version VARCHAR(30) NOT NULL DEFAULT '0.0.0',
            description TEXT NOT NULL,
            entry_file VARCHAR(191) NOT NULL DEFAULT 'bootstrap.php',
            enabled TINYINT(1) NOT NULL DEFAULT 0,
            source VARCHAR(40) NOT NULL DEFAULT 'local',
            homepage VARCHAR(255) NOT NULL DEFAULT '',
            installed_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $db->exec("CREATE TABLE IF NOT EXISTS cms_plugin_placements (
            id INT AUTO_INCREMENT PRIMARY KEY,
            page_id INT NOT NULL,
            plugin_slug VARCHAR(191) NOT NULL,
            position VARCHAR(30) NOT NULL DEFAULT 'after_content',
            sort_order INT NOT NULL DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY unique_page_plugin_position (page_id, plugin_slug, position)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        foreach ([
            "ALTER TABLE cms_pages ADD COLUMN parent_id INT NULL AFTER id",
            "ALTER TABLE cms_pages ADD COLUMN builder_data LONGTEXT NOT NULL AFTER content",
            "ALTER TABLE cms_pages ADD COLUMN sort_order INT NOT NULL DEFAULT 0 AFTER is_homepage",
        ] as $sql) {
            try {
                $db->exec($sql);
            } catch (Throwable $e) {
            }
        }
    } else {
        $db->exec("CREATE TABLE IF NOT EXISTS cms_settings (
            key TEXT PRIMARY KEY,
            value TEXT NOT NULL DEFAULT ''
        )");

        $db->exec("CREATE TABLE IF NOT EXISTS cms_users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            username TEXT NOT NULL UNIQUE,
            email TEXT NOT NULL DEFAULT '',
            password_hash TEXT NOT NULL,
            role TEXT NOT NULL DEFAULT 'admin',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");

        $db->exec("CREATE TABLE IF NOT EXISTS cms_pages (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            parent_id INTEGER DEFAULT NULL,
            title TEXT NOT NULL,
            slug TEXT NOT NULL UNIQUE,
            excerpt TEXT NOT NULL DEFAULT '',
            content TEXT NOT NULL DEFAULT '',
            builder_data TEXT NOT NULL DEFAULT '[]',
            status TEXT NOT NULL DEFAULT 'draft',
            is_homepage INTEGER NOT NULL DEFAULT 0,
            sort_order INTEGER NOT NULL DEFAULT 0,
            template TEXT NOT NULL DEFAULT 'default',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");

        $db->exec("CREATE TABLE IF NOT EXISTS cms_plugins (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            slug TEXT NOT NULL UNIQUE,
            name TEXT NOT NULL,
            version TEXT NOT NULL DEFAULT '0.0.0',
            description TEXT NOT NULL DEFAULT '',
            entry_file TEXT NOT NULL DEFAULT 'bootstrap.php',
            enabled INTEGER NOT NULL DEFAULT 0,
            source TEXT NOT NULL DEFAULT 'local',
            homepage TEXT NOT NULL DEFAULT '',
            installed_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");

        $db->exec("CREATE TABLE IF NOT EXISTS cms_plugin_placements (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            page_id INTEGER NOT NULL,
            plugin_slug TEXT NOT NULL,
            position TEXT NOT NULL DEFAULT 'after_content',
            sort_order INTEGER NOT NULL DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");

        foreach ([
            "ALTER TABLE cms_pages ADD COLUMN parent_id INTEGER DEFAULT NULL",
            "ALTER TABLE cms_pages ADD COLUMN builder_data TEXT NOT NULL DEFAULT '[]'",
            "ALTER TABLE cms_pages ADD COLUMN sort_order INTEGER NOT NULL DEFAULT 0",
        ] as $sql) {
            try {
                $db->exec($sql);
            } catch (Throwable $e) {
            }
        }
    }

    if (cms_db_driver() === 'mysql') {
        $stmt = $db->prepare('INSERT IGNORE INTO cms_settings (`key`, `value`) VALUES (?, ?)');
    } else {
        $stmt = $db->prepare('INSERT OR IGNORE INTO cms_settings (key, value) VALUES (?, ?)');
    }

    foreach ([
        ['cms_installed', '0'],
        ['site_name', 'My CMS'],
        ['site_tagline', 'Nowy system CMS oparty o portfolio'],
        ['theme', 'default'],
        ['cms_core_version', '1.0.0'],
        ['site_mode', 'multipage'],
        ['theme_variant', 'multipage'],
        ['cms_update_manifest_url', CMS_DEFAULT_UPDATE_MANIFEST_URL],
        ['store_db_manifest_url', CMS_DEFAULT_STORE_MANIFEST_URL],
        ['plugin_store_manifest_url', CMS_DEFAULT_PLUGIN_MANIFEST_URL],
        ['plugin_store_catalog_key', 'plugins'],
        ['plugin_store_directory', 'plugins'],
        // Theme customizer defaults
        ['theme_accent', '#2563eb'],
        ['theme_bg', '#f3f6fb'],
        ['theme_text', '#0f172a'],
        ['theme_panel', '#ffffff'],
        ['theme_muted', '#475569'],
        ['theme_border', '#dbe4f0'],
        ['theme_radius', '20'],
        ['theme_font_body', 'system'],
        ['theme_font_heading', 'system'],
        ['theme_font_size', '16'],
        ['theme_header_style', 'glass'],
        ['theme_header_bg', '#ffffff'],
        ['theme_bg_type', 'gradient'],
        ['theme_bg_color', '#f3f6fb'],
        ['theme_bg_gradient_from', '#e0eaff'],
        ['theme_bg_gradient_to', '#f3f6fb'],
        ['theme_bg_image', ''],
        ['theme_bg_attachment', 'scroll'],
        ['theme_container_width', '1120'],
        ['theme_footer_bg', '#ffffff'],
    ] as $setting) {
        $stmt->execute($setting);
    }
}

function cms_get_setting(string $key, string $default = ''): string
{
    $column = cms_db_driver() === 'mysql' ? '`value`' : 'value';
    $keyColumn = cms_db_driver() === 'mysql' ? '`key`' : 'key';
    $stmt = cms_db()->prepare('SELECT ' . $column . ' FROM cms_settings WHERE ' . $keyColumn . ' = ?');
    $stmt->execute([$key]);
    $value = $stmt->fetchColumn();

    return $value !== false ? (string) $value : $default;
}

function cms_set_setting(string $key, string $value): void
{
    if (cms_db_driver() === 'mysql') {
        cms_db()->prepare('INSERT INTO cms_settings (`key`, `value`) VALUES (?, ?) ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)')->execute([$key, $value]);
        return;
    }

    cms_db()->prepare('INSERT OR REPLACE INTO cms_settings (key, value) VALUES (?, ?)')->execute([$key, $value]);
}

function cms_is_installed(): bool
{
    return cms_get_setting('cms_installed', '0') === '1';
}
