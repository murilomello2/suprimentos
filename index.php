<?php /* Cockpit de Suprimentos — front. Sem segredos aqui; consome actions/*.php. */ ?>
<!doctype html>
<html lang="pt-br">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Cockpit de Suprimentos — Caprem</title>
<link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
<style>
  :root{
    --verde:#1f6b3b; --verde-d:#16502c; --dourado:#c9a227;
    --bg:#f5f7f6; --card:#fff; --line:#e6e9e7; --txt:#1f2937; --muted:#6b7280;
    --ok:#1f8f4e; --okbg:#e9f6ee; --and:#c8821a; --andbg:#fdf3e3;
    --pend:#c0392b; --pendbg:#fbeae8; --neu:#9aa3ab; --neubg:#f1f3f4;
    --cot:#2563eb; --cotbg:#e8effe;
  }
  *{box-sizing:border-box}
  body{margin:0;font-family:-apple-system,Segoe UI,Arial,sans-serif;background:var(--bg);color:var(--txt)}
  .app{display:flex;min-height:100vh}
  /* sidebar */
  .side{width:240px;background:var(--verde);color:#eafaef;flex-shrink:0;display:flex;flex-direction:column}
  .brand{padding:18px 18px 8px;font-size:17px;font-weight:800;letter-spacing:.2px;display:flex;align-items:center;gap:8px}
  .brand .material-icons{color:var(--dourado)}
  .navlabel{font-size:11px;text-transform:uppercase;letter-spacing:.8px;color:#bfe6cd;padding:16px 18px 6px;opacity:.85}
  .nav a{display:flex;align-items:center;gap:10px;padding:10px 18px;color:#eafaef;text-decoration:none;font-size:14px;border-left:3px solid transparent}
  .nav a .material-icons{font-size:19px}
  .nav a:hover{background:rgba(255,255,255,.06)}
  .nav a.active{background:rgba(255,255,255,.12);border-left-color:var(--dourado);font-weight:700}
  /* main */
  .main{flex:1;min-width:0;display:flex;flex-direction:column}
  .top{padding:20px 26px 6px}
  .h1{font-size:24px;font-weight:800;color:var(--verde-d);margin:0;display:flex;align-items:center;gap:10px}
  .sub{color:var(--muted);font-size:13px;margin:4px 0 0}
  .kpis{display:flex;gap:12px;flex-wrap:wrap;padding:14px 26px 6px}
  .kpi{background:var(--card);border:1px solid var(--line);border-radius:12px;padding:12px 16px;min-width:140px}
  .kpi .v{font-size:22px;font-weight:800;color:var(--verde-d)}
  .kpi .l{font-size:12px;color:var(--muted);margin-top:2px}
  .bar{padding:12px 26px;display:flex;gap:10px;align-items:center;flex-wrap:wrap}
  .search{flex:1;min-width:200px;display:flex;align-items:center;gap:8px;background:var(--card);border:1px solid var(--line);border-radius:10px;padding:9px 12px}
  .search input{border:0;outline:0;flex:1;font-size:14px;background:transparent}
  select{border:1px solid var(--line);border-radius:10px;padding:9px 12px;font-size:13px;background:var(--card);color:var(--txt)}
  .wrap{padding:6px 26px 30px;overflow:auto}
  table{width:100%;border-collapse:separate;border-spacing:0;background:var(--card);border:1px solid var(--line);border-radius:12px;overflow:hidden;font-size:13px}
  thead th{background:#fafbfb;text-align:left;padding:11px 12px;font-size:11px;text-transform:uppercase;letter-spacing:.4px;color:var(--muted);border-bottom:1px solid var(--line);position:sticky;top:0;white-space:nowrap}
  tbody td{padding:10px 12px;border-bottom:1px solid #f1f3f2;vertical-align:middle}
  tbody tr:hover{background:#fafdfb}
  .svc{font-weight:600;color:#111827}
  .fase{display:inline-block;font-size:11px;color:var(--muted);margin-top:2px}
  .pill{display:inline-flex;align-items:center;gap:5px;padding:3px 10px;border-radius:999px;font-size:12px;font-weight:700;white-space:nowrap}
  .p-ok{background:var(--okbg);color:var(--ok)} .p-and{background:var(--andbg);color:var(--and)}
  .p-pend{background:var(--pendbg);color:var(--pend)} .p-neu{background:var(--neubg);color:var(--neu)}
  .p-cot{background:var(--cotbg);color:var(--cot)}
  .dot{width:8px;height:8px;border-radius:50%;background:currentColor;display:inline-block}
  .money{font-variant-numeric:tabular-nums;white-space:nowrap}
  .muted{color:var(--muted)}
  .date{white-space:nowrap;font-variant-numeric:tabular-nums}
  .date.alert{color:var(--pend);font-weight:700}
  .curva{display:inline-block;width:20px;height:20px;line-height:20px;text-align:center;border-radius:6px;font-size:11px;font-weight:800;color:#fff}
  .c-A{background:#c0392b}.c-B{background:#c8821a}.c-C{background:#1f8f4e}
  .empty{padding:40px;text-align:center;color:var(--muted)}
  .badge{font-size:11px;color:var(--muted)}
  .toast{position:fixed;bottom:16px;left:50%;transform:translateX(-50%);background:#111827;color:#fff;padding:10px 16px;border-radius:8px;font-size:13px;display:none}
</style>
</head>
<body>
<div class="app">
  <aside class="side">
    <div class="brand"><span class="material-icons">inventory_2</span> Cockpit de Suprimentos</div>
    <div class="navlabel">Aquisições</div>
    <nav class="nav">
      <a href="#" class="active"><span class="material-icons">radar</span> Radar de Aquisições</a>
      <a href="#" onclick="toast('Mapa de Cotações — em breve (Fase 3)');return false"><span class="material-icons">request_quote</span> Mapa de Cotações</a>
    </nav>
    <div class="navlabel">Planejamento</div>
    <nav class="nav">
      <a href="#" onclick="toast('Puxado do Cockpit de Obras (Supabase)');return false"><span class="material-icons">event</span> Cronograma</a>
      <a href="#" onclick="toast('Em breve');return false"><span class="material-icons">payments</span> Orçamento</a>
    </nav>
  </aside>

  <main class="main">
    <div class="top">
      <h1 class="h1"><span class="material-icons" style="color:var(--dourado)">radar</span> Radar de Aquisições</h1>
      <p class="sub" id="sub">Carregando obra…</p>
    </div>

    <div class="kpis" id="kpis"></div>

    <div class="bar">
      <div class="search"><span class="material-icons" style="color:var(--muted)">search</span>
        <input id="q" placeholder="Buscar serviço…" oninput="render()"></div>
      <select id="ffase" onchange="render()"><option value="">Todas as fases</option></select>
      <select id="fstatus" onchange="render()"><option value="">Todos os status</option></select>
    </div>

    <div class="wrap">
      <table>
        <thead><tr>
          <th>#</th><th>Serviço</th><th>Cv</th><th>Status</th>
          <th>Início cotação<br><span class="badge">(gatilho)</span></th>
          <th>Necessário em obra</th><th>Lead</th><th>Verba estim.</th>
          <th>Responsável</th><th>Fornecedor</th>
        </tr></thead>
        <tbody id="tb"><tr><td colspan="10" class="empty">Carregando…</td></tr></tbody>
      </table>
    </div>
  </main>
</div>
<div class="toast" id="toastEl"></div>

<script>
let DATA = {itens:[]};
const BRL = n => n ? n.toLocaleString('pt-BR',{style:'currency',currency:'BRL',maximumFractionDigits:0}) : '—';
const fmtDate = s => { if(!s) return '—'; const p=String(s).split('-'); return p.length===3? p[2]+'/'+p[1]+'/'+p[0] : s; };
function toast(m){const t=document.getElementById('toastEl');t.textContent=m;t.style.display='block';clearTimeout(t._);t._=setTimeout(()=>t.style.display='none',2600);}

const STATUS_CLASS = {
  'Finalizado':'p-ok','Cotação Iniciada':'p-cot','Com Pendências':'p-pend',
  'Em Andamento':'p-and','Não Iniciado':'p-neu'
};
function statusPill(s){ const c=STATUS_CLASS[s]||'p-neu'; return `<span class="pill ${c}"><span class="dot"></span>${s||'Não Iniciado'}</span>`; }

async function load(){
  try{
    const r = await fetch('actions/matriz.php'); const d = await r.json();
    if(d.error){ document.getElementById('tb').innerHTML=`<tr><td colspan="10" class="empty">Erro: ${d.error}</td></tr>`; return; }
    DATA = d;
    const o=d.obra, rs=d.resumo;
    document.getElementById('sub').innerHTML =
      `Obra <b>${o.nome}</b> <span class="muted">(${o.codinome})</span> · ${rs.total} serviços`
      + (rs.crono_erro? ` · <span style="color:var(--pend)">cronograma offline</span>`:` · datas do cronograma ao vivo`);
    // KPIs
    const conc = rs.por_status['Finalizado']||0;
    const pct = Math.round(conc/rs.total*100);
    const cot = rs.por_status['Cotação Iniciada']||0;
    document.getElementById('kpis').innerHTML = `
      <div class="kpi"><div class="v">${rs.total}</div><div class="l">Serviços</div></div>
      <div class="kpi"><div class="v">${pct}%</div><div class="l">${conc} finalizados</div></div>
      <div class="kpi"><div class="v">${cot}</div><div class="l">Cotações iniciadas</div></div>
      <div class="kpi"><div class="v">${BRL(o.orcamento_total)}</div><div class="l">Orçamento da obra</div></div>`;
    // filtros
    const fases=[...new Set(d.itens.map(i=>i.fase).filter(Boolean))];
    document.getElementById('ffase').innerHTML='<option value="">Todas as fases</option>'+fases.map(f=>`<option>${f}</option>`).join('');
    const sts=[...new Set(d.itens.map(i=>i.status||'Não Iniciado'))];
    document.getElementById('fstatus').innerHTML='<option value="">Todos os status</option>'+sts.map(s=>`<option>${s}</option>`).join('');
    render();
  }catch(e){ document.getElementById('tb').innerHTML=`<tr><td colspan="10" class="empty">Falha: ${e.message}</td></tr>`; }
}

function render(){
  const q=(document.getElementById('q').value||'').toLowerCase();
  const ff=document.getElementById('ffase').value;
  const fs=document.getElementById('fstatus').value;
  const today=new Date().toISOString().slice(0,10);
  const rows = DATA.itens.filter(i=>
    (!q || i.nome.toLowerCase().includes(q)) &&
    (!ff || i.fase===ff) &&
    (!fs || (i.status||'Não Iniciado')===fs)
  );
  if(!rows.length){ document.getElementById('tb').innerHTML='<tr><td colspan="10" class="empty">Nenhum serviço.</td></tr>'; return; }
  document.getElementById('tb').innerHTML = rows.map(i=>{
    const gatAlert = i.data_gatilho && i.data_gatilho < today && (i.status||'')!=='Finalizado';
    return `<tr>
      <td class="muted">${i.ordem}</td>
      <td><div class="svc">${i.nome}</div><div class="fase">${i.fase||''}</div></td>
      <td><span class="curva c-${i.curva||'C'}">${i.curva||'—'}</span></td>
      <td>${statusPill(i.status)}</td>
      <td class="date ${gatAlert?'alert':''}">${fmtDate(i.data_gatilho)}</td>
      <td class="date" title="${i.marco_casado? 'Marco: '+i.marco_casado.replace(/"/g,'')+' · '+(i.confianca||'') : 'Sem match no cronograma'}">
        ${fmtDate(i.data_necessaria)}${i.marco_casado?` <span class="material-icons mk" style="font-size:13px;color:${i.confianca&&i.confianca.includes('secund')?'var(--and)':'var(--neu)'}">info</span>`:''}
      </td>
      <td class="muted">${i.lead_dias?i.lead_dias+'d':'—'}</td>
      <td class="money">${BRL(i.verba_estim)}</td>
      <td>${i.responsavel||'<span class="muted">—</span>'}</td>
      <td>${i.fornecedor||'<span class="muted">—</span>'}</td>
    </tr>`;
  }).join('');
}
load();
</script>
</body>
</html>
