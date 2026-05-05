<?php
declare(strict_types=1);

function cms_comments_plugin_init_table(): void
{
    $db = cms_db();
    if (cms_db_driver() === 'mysql') {
        $db->exec("CREATE TABLE IF NOT EXISTS cms_plugin_comments (
            id INT AUTO_INCREMENT PRIMARY KEY,
            page_id INT NOT NULL,
            author_name VARCHAR(191) NOT NULL,
            message TEXT NOT NULL,
            status VARCHAR(30) NOT NULL DEFAULT 'approved',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        return;
    }

    $db->exec("CREATE TABLE IF NOT EXISTS cms_plugin_comments (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        page_id INTEGER NOT NULL,
        author_name TEXT NOT NULL,
        message TEXT NOT NULL,
        status TEXT NOT NULL DEFAULT 'approved',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");
}

function cms_comments_plugin_submit_if_needed(): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        return;
    }

    if (isset($_SERVER['SCRIPT_NAME']) && str_contains((string) $_SERVER['SCRIPT_NAME'], '/admin/')) {
        return;
    }

    if ((string) ($_POST['plugin_action'] ?? '') !== 'submit_comment') {
        return;
    }

    if (!cms_verify_csrf($_POST['csrf_token'] ?? null)) {
        cms_flash('error', 'Nieprawidlowy token komentarza.');
        cms_redirect(cms_url('?page=' . urlencode((string) ($_POST['page_slug'] ?? ''))));
    }

    $pageId = (int) ($_POST['page_id'] ?? 0);
    $pageSlug = trim((string) ($_POST['page_slug'] ?? ''));
    $author = trim((string) ($_POST['author_name'] ?? ''));
    $message = trim((string) ($_POST['message'] ?? ''));

    if ($pageId <= 0 || $author === '' || $message === '') {
        cms_flash('error', 'Uzupelnij imie i komentarz.');
        cms_redirect(cms_url('?page=' . urlencode($pageSlug)));
    }

    $stmt = cms_db()->prepare('INSERT INTO cms_plugin_comments (page_id, author_name, message, status) VALUES (?, ?, ?, ?)');
    $stmt->execute([$pageId, $author, $message, 'approved']);

    cms_flash('success', 'Komentarz zostal dodany.');
    cms_redirect(cms_url('?page=' . urlencode($pageSlug)));
}

function cms_comments_plugin_render(array $page): string
{
    $pageId = (int) ($page['id'] ?? 0);
    if ($pageId <= 0) {
        return '';
    }

    $stmt = cms_db()->prepare('SELECT author_name, message, created_at FROM cms_plugin_comments WHERE page_id = ? AND status = ? ORDER BY id DESC LIMIT 50');
    $stmt->execute([$pageId, 'approved']);
    $rows = $stmt->fetchAll();

    ob_start();
    ?>
    <section class="plugin-note" style="margin-top:14px">
        <strong>Komentarze</strong>
        <form method="post" style="margin:10px 0 12px">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(cms_csrf_token()) ?>">
            <input type="hidden" name="plugin_action" value="submit_comment">
            <input type="hidden" name="page_id" value="<?= (int) $pageId ?>">
            <input type="hidden" name="page_slug" value="<?= htmlspecialchars((string) ($page['slug'] ?? '')) ?>">
            <div style="display:grid;gap:8px">
                <input type="text" name="author_name" placeholder="Twoje imie" required style="padding:10px;border:1px solid #bfdbfe;border-radius:10px">
                <textarea name="message" placeholder="Napisz komentarz" required style="min-height:90px;padding:10px;border:1px solid #bfdbfe;border-radius:10px"></textarea>
                <button type="submit" class="builder-button" style="width:max-content">Dodaj komentarz</button>
            </div>
        </form>
        <?php if ($rows): ?>
            <div style="display:grid;gap:10px">
                <?php foreach ($rows as $row): ?>
                    <article style="padding:10px;border:1px solid #bfdbfe;border-radius:10px;background:#fff">
                        <strong><?= htmlspecialchars((string) $row['author_name']) ?></strong>
                        <div style="font-size:12px;color:#64748b"><?= htmlspecialchars((string) $row['created_at']) ?></div>
                        <p style="margin:8px 0 0"><?= nl2br(htmlspecialchars((string) $row['message'])) ?></p>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div style="color:#64748b">Brak komentarzy.</div>
        <?php endif; ?>
    </section>
    <?php
    return (string) ob_get_clean();
}

cms_comments_plugin_init_table();
cms_comments_plugin_submit_if_needed();

cms_add_hook('plugin_render', static function (string $slug, array $page, string $position): void {
    if ($slug !== 'comments') {
        return;
    }

    echo cms_comments_plugin_render($page);
});
