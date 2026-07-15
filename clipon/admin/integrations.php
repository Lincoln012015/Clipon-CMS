<?php
require_once __DIR__ . '/../lib/Auth.php';
if (!$session->has('user') || (string)$session->get('role', '') !== 'admin') { header('Location: index.php'); exit; }
$providerId = strtolower(trim((string)$request->query('provider', '')));
$key = 'integration.admin.' . $providerId;
if (!preg_match('/^[a-z0-9_-]+$/', $providerId) || !registry()->has($key)) { http_response_code(404); exit('Integration provider not found.'); }
$adminService = registry()->get($key); $settings = $adminService->settings(); $recent = $adminService->recentLog();
$base = c_site_url() . '/clipon/api/integrations.php?provider=' . rawurlencode($providerId);
$role = (string)$session->get('role', '');
?>
<!doctype html><html lang="<?= htmlspecialchars(Translation::getLang(), ENT_QUOTES) ?>"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Seonix Integration - Admin</title><link rel="stylesheet" href="<?= C_ASSETS_URL ?>/css/admin.css?v=15">
<style>.integration-grid{display:grid;grid-template-columns:1fr 1fr;gap:20px}.integration-card{background:#fff;border:1px solid var(--border-color);border-radius:12px;padding:20px}.integration-card label{display:block;margin:0 0 14px}.integration-card input[type=text],.integration-card select,.integration-card textarea{width:100%}.integration-url{display:block;overflow-wrap:anywhere;background:#f1f5f9;padding:10px;border-radius:6px}.token-box{display:none;margin-top:14px;padding:12px;background:#fff7ed;border:1px solid #fdba74;overflow-wrap:anywhere}.log-table{width:100%;border-collapse:collapse}.log-table th,.log-table td{text-align:left;padding:8px;border-bottom:1px solid var(--border-color);font-size:13px}@media(max-width:800px){.integration-grid{grid-template-columns:1fr}}</style>
</head><body><div class="admin-container"><?php include __DIR__ . '/nav.php'; ?><main class="main-content">
<header class="header"><div class="header-title"><h1>Seonix</h1><p>Automatic article publishing through the Clipon Integration API.</p></div></header>
<div class="integration-grid"><section class="integration-card"><h2>Connection</h2>
<p><strong>Publish URL</strong><code class="integration-url"><?= htmlspecialchars($base, ENT_QUOTES) ?></code></p>
<p><strong>Delete URL</strong><code class="integration-url"><?= htmlspecialchars($base . '&id={id}', ENT_QUOTES) ?></code></p>
<button class="btn" id="rotate-token" type="button">Generate / rotate token</button><div class="token-box" id="token-box"></div>
</section><section class="integration-card"><h2>Settings</h2><form id="settings-form">
<?= Csrf::inputField() ?><input type="hidden" name="provider" value="seonix"><input type="hidden" name="action" value="save">
<label><input type="checkbox" name="enabled" value="1" <?= !empty($settings['enabled']) ? 'checked' : '' ?>> Enable integration</label>
<label>Publishing mode<select name="publish_mode"><option value="draft" <?= $settings['publish_mode'] === 'draft' ? 'selected' : '' ?>>Draft</option><option value="publish" <?= $settings['publish_mode'] === 'publish' ? 'selected' : '' ?>>Publish immediately</option></select></label>
<label>Blog template<input type="text" name="template" value="<?= htmlspecialchars((string)$settings['template'], ENT_QUOTES) ?>"></label>
<label>HTML content key<input type="text" name="content_key" value="<?= htmlspecialchars((string)$settings['content_key'], ENT_QUOTES) ?>"></label>
<label>Language map (JSON)<textarea name="language_map" rows="3"><?= htmlspecialchars(json_encode($settings['language_map'], JSON_UNESCAPED_UNICODE), ENT_QUOTES) ?></textarea></label>
<label><input type="checkbox" name="allow_delete" value="1" <?= !empty($settings['allow_delete']) ? 'checked' : '' ?>> Allow remote deletion</label>
<button class="btn btn-primary" type="submit">Save settings</button></form><p id="message"></p></section></div>
<section class="integration-card" style="margin-top:20px"><h2>Recent requests</h2><table class="log-table"><thead><tr><th>Time</th><th>Operation</th><th>Status</th><th>External ID</th><th>ms</th></tr></thead><tbody><?php foreach ($recent as $row): ?><tr><td><?= htmlspecialchars((string)$row['time']) ?></td><td><?= htmlspecialchars((string)$row['operation']) ?></td><td><?= (int)$row['status'] ?></td><td><?= htmlspecialchars((string)$row['external_id']) ?></td><td><?= (int)$row['duration_ms'] ?></td></tr><?php endforeach; ?></tbody></table></section>
</main></div><script>
const api='api/integrations.php'; const form=document.getElementById('settings-form'); const message=document.getElementById('message');
async function send(data){const response=await fetch(api,{method:'POST',body:data,headers:{'X-Requested-With':'XMLHttpRequest'}});return response.json();}
form.addEventListener('submit',async e=>{e.preventDefault();const data=new FormData(form);if(!form.enabled.checked)data.set('enabled','0');if(!form.allow_delete.checked)data.set('allow_delete','0');const result=await send(data);message.textContent=result.message||result.status;});
document.getElementById('rotate-token').addEventListener('click',async()=>{if(!confirm('The previous token will stop working. Continue?'))return;const data=new FormData();data.set('csrf_token',form.csrf_token.value);data.set('provider','seonix');data.set('action','rotate_token');const result=await send(data);const box=document.getElementById('token-box');box.style.display='block';box.textContent=result.token||result.message;});
</script></body></html>
