<?php /* Front-end — NUNCA contém o webhook. Apenas consome actions/test.php. */ ?>
<!doctype html>
<html lang="pt-br">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Teste MCP Bitrix — Suprimentos</title>
<link rel="stylesheet" href="https://app.capremconstrutora.com.br/estilo/style.css">
<link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
<style>
  body{background:#f7f8fa;font-family:Arial,Helvetica,sans-serif;margin:0;padding:16px;color:#222}
  .card{background:#fff;border:1px solid #e3e6ea;border-radius:10px;padding:18px 20px;max-width:680px;margin:0 auto 14px;box-shadow:0 1px 3px rgba(0,0,0,.05)}
  .title{display:flex;align-items:center;gap:8px;font-size:18px;font-weight:700;margin:0 0 4px}
  .muted{color:#6b7280;font-size:13px;margin:0 0 14px}
  button{background:#1f6b3b;color:#fff;border:0;border-radius:8px;padding:10px 16px;font-size:14px;cursor:pointer;display:inline-flex;align-items:center;gap:6px}
  button:disabled{opacity:.6;cursor:default}
  .row{display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px solid #f0f1f3;font-size:14px}
  .row b{color:#374151}
  .chip{display:inline-block;background:#eef6f0;color:#1f6b3b;border-radius:999px;padding:3px 10px;margin:3px 4px 0 0;font-size:12px}
  .ok{color:#1f6b3b}.err{color:#b91c1c}
  pre{background:#0f172a;color:#e2e8f0;padding:12px;border-radius:8px;overflow:auto;font-size:12px;max-height:260px}
  .status{font-weight:700;margin-bottom:10px}
  .material-icons{vertical-align:middle}
</style>
</head>
<body>
<div class="card">
  <p class="title"><span class="material-icons">inventory_2</span> Teste de Conexão — MCP Bitrix24</p>
  <p class="muted">App de validação do projeto <b>Suprimentos</b>. Testa a cadeia: front → PHP (actions) → webhook → Bitrix24. O webhook fica somente no servidor.</p>
  <button id="btn"><span class="material-icons" style="font-size:18px">sync</span> Testar conexão</button>
</div>

<div class="card" id="result" style="display:none">
  <p class="title"><span class="material-icons">verified</span> Resultado</p>
  <div id="status" class="status"></div>
  <div id="profile"></div>
  <div id="scopes" style="margin-top:10px"></div>
  <details style="margin-top:12px"><summary class="muted">Resposta bruta (JSON)</summary><pre id="raw"></pre></details>
</div>

<script>
const btn = document.getElementById('btn');
btn.addEventListener('click', async () => {
  btn.disabled = true;
  const icon = btn.querySelector('.material-icons'); icon.textContent = 'hourglass_top';
  const box = document.getElementById('result'); box.style.display = 'block';
  const st = document.getElementById('status');
  try {
    const r = await fetch('actions/test.php', { method: 'POST' });
    const data = await r.json();
    document.getElementById('raw').textContent = JSON.stringify(data, null, 2);
    const p = data.profile && data.profile.result;
    if (p) {
      st.innerHTML = '<span class="ok"><span class="material-icons">check_circle</span> Conexão OK</span>';
      document.getElementById('profile').innerHTML =
        '<div class="row"><span>Usuário</span><b>' + (p.NAME||'') + ' ' + (p.LAST_NAME||'') + '</b></div>' +
        '<div class="row"><span>ID</span><b>' + (p.ID||'') + '</b></div>' +
        '<div class="row"><span>Admin</span><b>' + (p.ADMIN ? 'Sim' : 'Não') + '</b></div>' +
        '<div class="row"><span>Fuso</span><b>' + (p.TIME_ZONE||'') + '</b></div>';
    } else {
      st.innerHTML = '<span class="err"><span class="material-icons">error</span> Falha na conexão</span>';
    }
    const sc = data.scope && data.scope.result;
    if (Array.isArray(sc)) {
      document.getElementById('scopes').innerHTML = '<div class="muted">Escopos liberados:</div>' + sc.map(s => '<span class="chip">' + s + '</span>').join('');
    }
  } catch (e) {
    st.innerHTML = '<span class="err">Erro: ' + e.message + '</span>';
  } finally {
    btn.disabled = false; icon.textContent = 'sync';
  }
});
</script>
</body>
</html>
