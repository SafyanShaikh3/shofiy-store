<?php
require dirname(__DIR__) . '/includes/bootstrap.php';
$admin = require_admin();

if (is_post()) {
    csrf_check();
    $id = (int)post('id');
    if (post('action') === 'delete') { q('DELETE FROM messages WHERE id = ?', [$id]); flash('success', 'Message deleted.'); }
    if (post('action') === 'read') q('UPDATE messages SET is_read = 1 WHERE id = ?', [$id]);
    if (post('action') === 'unread') q('UPDATE messages SET is_read = 0 WHERE id = ?', [$id]);
    redirect('admin/messages.php');
}

$total = (int)q_val('SELECT COUNT(*) FROM messages');
$pg = paginate($total, 30, (int)get('page', '1'));
$msgs = q("SELECT * FROM messages ORDER BY is_read ASC, id DESC LIMIT {$pg['limit']} OFFSET {$pg['offset']}")->fetchAll();
$page_title = 'Messages';
include __DIR__ . '/_top.php';
?>
<?php if (!$msgs): ?><div class="panel empty">No messages yet. Messages sent from your Contact page appear here.</div><?php endif; ?>
<div class="msg-list">
<?php foreach ($msgs as $m): ?>
  <article class="panel msg<?= $m['is_read'] ? '' : ' unread' ?>">
    <header>
      <div><b><?= e($m['name']) ?></b> <span class="muted small"><?= e(time_ago($m['created_at'])) ?></span></div>
      <div class="muted small"><a href="mailto:<?= e($m['email']) ?>"><?= e($m['email']) ?></a><?= $m['phone'] ? ' · <a href="tel:' . e($m['phone']) . '">' . e($m['phone']) . '</a>' : '' ?></div>
    </header>
    <div class="msg-body"><?= nl2p($m['message']) ?></div>
    <div class="btn-row">
      <a class="btn btn-sm" href="mailto:<?= e($m['email']) ?>?subject=<?= rawurlencode('Re: your message to ' . setting('store_name')) ?>">Reply</a>
      <form method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$m['id'] ?>"><input type="hidden" name="action" value="<?= $m['is_read'] ? 'unread' : 'read' ?>"><button class="btn btn-sm btn-ghost" type="submit"><?= $m['is_read'] ? 'Mark unread' : 'Mark read' ?></button></form>
      <form method="post" onsubmit="return confirm('Delete this message?')"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$m['id'] ?>"><input type="hidden" name="action" value="delete"><button class="btn btn-sm btn-ghost danger" type="submit">Delete</button></form>
    </div>
  </article>
<?php endforeach; ?>
</div>
<?= render_pagination($pg) ?>
<?php include __DIR__ . '/_bottom.php'; ?>
