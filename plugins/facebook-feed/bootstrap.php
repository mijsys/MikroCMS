<?php
declare(strict_types=1);

function cms_facebook_plugin_fetch_items(): array
{
    $pageId = trim(cms_get_setting('facebook_plugin_page_id', ''));
    $token = trim(cms_get_setting('facebook_plugin_access_token', ''));
    $mode = cms_get_setting('facebook_plugin_mode', 'posts') === 'events' ? 'events' : 'posts';
    $limit = max(1, min(20, (int) cms_get_setting('facebook_plugin_limit', '5')));

    if ($pageId === '' || $token === '') {
        return [];
    }

    $fields = $mode === 'events'
        ? 'name,start_time,place,description'
        : 'message,created_time,permalink_url,full_picture';

    $url = sprintf(
        'https://graph.facebook.com/v22.0/%s/%s?fields=%s&limit=%d&access_token=%s',
        rawurlencode($pageId),
        $mode,
        rawurlencode($fields),
        $limit,
        rawurlencode($token)
    );

    $context = stream_context_create([
        'http' => [
            'timeout' => 8,
            'user_agent' => 'MikroCMS-FacebookPlugin/1.0',
        ],
        'ssl' => [
            'verify_peer' => true,
            'verify_peer_name' => true,
        ],
    ]);

    $raw = @file_get_contents($url, false, $context);
    if ($raw === false || $raw === '') {
        return [];
    }

    $decoded = json_decode($raw, true);
    if (!is_array($decoded) || !isset($decoded['data']) || !is_array($decoded['data'])) {
        return [];
    }

    return $decoded['data'];
}

function cms_facebook_plugin_render(array $page): string
{
    $mode = cms_get_setting('facebook_plugin_mode', 'posts') === 'events' ? 'events' : 'posts';
    $items = cms_facebook_plugin_fetch_items();

    ob_start();
    ?>
    <section class="plugin-note" style="margin-top:14px">
        <strong>Facebook <?= $mode === 'events' ? 'Events' : 'Posts' ?></strong>
        <?php if ($items === []): ?>
            <div style="color:#64748b;margin-top:8px">Brak danych. Ustaw token i page id w panelu Pluginy.</div>
        <?php else: ?>
            <div style="display:grid;gap:10px;margin-top:10px">
                <?php foreach ($items as $item): ?>
                    <article style="padding:10px;border:1px solid #bfdbfe;border-radius:10px;background:#fff">
                        <?php if ($mode === 'events'): ?>
                            <strong><?= htmlspecialchars((string) ($item['name'] ?? 'Wydarzenie')) ?></strong>
                            <?php if (!empty($item['start_time'])): ?><div style="font-size:12px;color:#64748b"><?= htmlspecialchars((string) $item['start_time']) ?></div><?php endif; ?>
                            <?php if (!empty($item['description'])): ?><p style="margin:8px 0 0"><?= nl2br(htmlspecialchars((string) $item['description'])) ?></p><?php endif; ?>
                        <?php else: ?>
                            <?php if (!empty($item['message'])): ?><p style="margin:0 0 8px"><?= nl2br(htmlspecialchars((string) $item['message'])) ?></p><?php endif; ?>
                            <?php if (!empty($item['full_picture'])): ?><img src="<?= htmlspecialchars((string) $item['full_picture']) ?>" alt="Post image" style="max-width:100%;border-radius:8px"><?php endif; ?>
                            <?php if (!empty($item['permalink_url'])): ?><div style="margin-top:8px"><a href="<?= htmlspecialchars((string) $item['permalink_url']) ?>" target="_blank" rel="noopener noreferrer">Zobacz post</a></div><?php endif; ?>
                        <?php endif; ?>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
    <?php
    return (string) ob_get_clean();
}

cms_add_hook('plugin_render', static function (string $slug, array $page, string $position): void {
    if ($slug !== 'facebook-feed') {
        return;
    }

    echo cms_facebook_plugin_render($page);
});
