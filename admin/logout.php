<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';

cms_logout();
cms_flash('success', cms_t('admin.logout.success', 'Zostales wylogowany z CMS.'));
cms_redirect(cms_url('admin/index.php'));
