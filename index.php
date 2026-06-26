<?php /* Cockpit de Suprimentos — front. Sem segredos aqui; consome actions/*.php. (republicado) */ ?>
<!doctype html>
<html lang="pt-br">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Cockpit de Suprimentos — Caprem</title>
<link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
<script src="//api.bitrix24.com/api/v1/"></script>
<style>
  :root{
    --verde:#1f6b3b; --verde-d:#16502c; --dourado:#c9a227;
    --bg:#f4f6f5; --card:#fff; --line:#e6e9e7; --txt:#1f2937; --muted:#6b7280;
    --ok:#1f8f4e; --okbg:#e9f6ee; --and:#c8821a; --andbg:#fdf3e3;
    --pend:#c0392b; --pendbg:#fbeae8; --neu:#8a949c; --neubg:#eef0f1;
    --cot:#2563eb; --cotbg:#e8effe;
  }
  *{box-sizing:border-box}
  body{margin:0;font-family:-apple-system,Segoe UI,Arial,sans-serif;background:var(--bg);color:var(--txt);font-size:14px}
  .app{display:flex;min-height:100vh}
  .side{width:230px;background:var(--verde);color:#eafaef;flex-shrink:0;position:sticky;top:0;height:100vh;display:flex;flex-direction:column}
  .brand{padding:18px;font-size:16px;font-weight:800;display:flex;align-items:center;gap:8px;border-bottom:1px solid rgba(255,255,255,.12)}
  .brand .material-icons{color:var(--dourado)}
  .navlabel{font-size:10.5px;text-transform:uppercase;letter-spacing:.8px;color:#bfe6cd;padding:16px 18px 6px;opacity:.85}
  .nav a{display:flex;align-items:center;gap:10px;padding:10px 18px;color:#eafaef;text-decoration:none;font-size:13.5px;border-left:3px solid transparent;cursor:pointer}
  .nav a .material-icons{font-size:19px}
  .nav a:hover{background:rgba(255,255,255,.06)}
  .nav a.active{background:rgba(255,255,255,.12);border-left-color:var(--dourado);font-weight:700}
  .whoami{margin-top:auto;padding:12px 18px;border-top:1px solid rgba(255,255,255,.12);font-size:11.5px;color:#bfe6cd;line-height:1.55}
  .whoami .wname{font-weight:800;color:#eafaef}
  .whoami .wsrc{opacity:.6;font-size:10px;margin-top:2px}
  .whoami .wsrc.bad{color:#ffd5cf;opacity:.95}
  .side{transition:width .15s ease}
  .sidetoggle{margin-left:auto;flex-shrink:0;width:28px;height:28px;display:flex;align-items:center;justify-content:center;background:rgba(255,255,255,.14);border:0;border-radius:7px;color:#eafaef;cursor:pointer;padding:0}
  .sidetoggle:hover{background:rgba(255,255,255,.26)}
  .sidetoggle .material-icons{font-size:18px;color:#eafaef;transition:transform .15s}
  .app.sidecollapsed .side{width:60px}
  .app.sidecollapsed .brandicon,.app.sidecollapsed .brandtext,.app.sidecollapsed .navtxt,.app.sidecollapsed .navlabel,.app.sidecollapsed .whoami,.app.sidecollapsed .navbadge{display:none}
  .app.sidecollapsed .brand{justify-content:center;padding:14px 0;gap:0}
  .app.sidecollapsed .sidetoggle{margin-left:0}
  .app.sidecollapsed .sidetoggle .material-icons{transform:rotate(180deg)}
  .app.sidecollapsed .nav a{justify-content:center;padding:11px 0}
  .main{flex:1;min-width:0}
  .top{padding:20px 26px 4px}
  .h1{font-size:23px;font-weight:800;color:var(--verde-d);margin:0;display:flex;align-items:center;gap:10px}
  .sub{color:var(--muted);font-size:13px;margin:5px 0 0}
  .kpis{display:flex;gap:12px;flex-wrap:wrap;padding:14px 26px 4px}
  .kpi{background:var(--card);border:1px solid var(--line);border-radius:12px;padding:12px 16px;min-width:130px;flex:1}
  .kpi .v{font-size:21px;font-weight:800;color:var(--verde-d)}
  .kpi .v.alert{color:var(--pend)} .kpi .v.gold{color:var(--dourado)}
  .kpi .l{font-size:11.5px;color:var(--muted);margin-top:2px}
  .panel{background:var(--card);border:1px solid var(--line);border-radius:12px;margin:12px 26px}
  .panel h3{font-size:13px;margin:0;padding:13px 16px 0;color:var(--verde-d)}
  .panel .hint{font-size:12px;color:var(--muted);padding:2px 16px 0}
  .bar{display:flex;gap:10px;align-items:center;flex-wrap:wrap;padding:12px 16px}
  .bar select,.search{border:1px solid var(--line);border-radius:9px;padding:9px 11px;font-size:13px;background:#fff;color:var(--txt)}
  .search{flex:1;min-width:220px;display:flex;align-items:center;gap:8px}
  .search input{border:0;outline:0;flex:1;font-size:13px;background:transparent}
  .toggle{display:flex;align-items:center;gap:8px;font-size:12.5px;color:var(--muted);cursor:pointer}
  .wrap{margin:0 26px 30px;overflow-x:auto}
  #view-radar .wrap{overflow:auto;margin-bottom:14px;border:1px solid var(--line);border-radius:12px}
  #view-radar table{overflow:visible;border:0;border-radius:0}
  #view-radar thead th{position:sticky;top:0;z-index:5;box-shadow:inset 0 -1px 0 var(--line)}
  table{width:100%;border-collapse:separate;border-spacing:0;background:var(--card);border:1px solid var(--line);border-radius:12px;overflow:hidden}
  thead th{background:#fafbfb;text-align:left;padding:10px 12px;font-size:10.5px;text-transform:uppercase;letter-spacing:.4px;color:var(--muted);border-bottom:1px solid var(--line);white-space:nowrap}
  thead th.srt{cursor:pointer;user-select:none}
  thead th.srt:hover{color:var(--verde)}
  thead th.srt.on{color:var(--verde-d)}
  .sar{color:var(--verde);font-size:11px;font-weight:800}
  tbody td{padding:9px 12px;border-bottom:1px solid #f1f3f2;vertical-align:middle}
  tbody tr.item{cursor:pointer}
  tbody tr.item:hover{background:#f7fbf8}
  .grp td{background:#eef4f0;font-weight:800;color:var(--verde-d);font-size:12px;text-transform:uppercase;letter-spacing:.5px;padding:8px 12px}
  .grp{cursor:pointer}
  .grp .gcount{font-weight:600;color:var(--muted);text-transform:none;letter-spacing:0}
  .gwrap{display:flex;align-items:center;gap:8px}
  .gcaret{font-size:18px;color:var(--verde-d);flex:0 0 auto}
  .gctl{display:inline-flex;gap:4px;align-items:center;margin:0 2px 0 8px}
  #filtBadge{font-weight:800;color:var(--verde)}
  .grp .gname{cursor:pointer}
  .gbtn{border:1px solid var(--line);background:#fff;border-radius:6px;min-width:24px;height:22px;cursor:pointer;font-size:11px;color:var(--muted);line-height:1;display:inline-flex;align-items:center;justify-content:center;padding:0 4px}
  .gbtn:hover:not([disabled]){border-color:var(--verde);color:var(--verde)}
  .gbtn[disabled]{opacity:.35;cursor:default}
  .resp-miss{background:var(--pendbg);color:var(--pend);border:0;border-radius:6px;padding:2px 8px;font-size:11px;font-weight:700;cursor:pointer}
  .resp-miss:hover{filter:brightness(.96)}
  .svc{font-weight:600;color:#111827}
  .svc-sub{font-size:11.5px;color:var(--muted);margin-top:1px;max-width:340px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
  .money{font-variant-numeric:tabular-nums;white-space:nowrap}
  .qcell{max-width:96px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-variant-numeric:tabular-nums;cursor:default}
  .qcell .muted{font-size:11.5px}
  .date{white-space:nowrap;font-variant-numeric:tabular-nums}
  .tag-venc{background:var(--pendbg);color:var(--pend);font-size:10px;font-weight:700;padding:1px 6px;border-radius:6px;margin-left:5px}
  .tag-al{font-size:10px;font-weight:700;padding:1px 6px;border-radius:6px;margin-left:5px;white-space:nowrap}
  .tag-al.crit{background:var(--pendbg);color:var(--pend)}
  .tag-al.atras{background:var(--andbg);color:var(--and)}
  .tag-al.prox{background:var(--cotbg);color:var(--cot)}
  .tag-al.fin{background:var(--okbg);color:var(--ok)}
  .curva{display:inline-block;width:19px;height:19px;line-height:19px;text-align:center;border-radius:6px;font-size:11px;font-weight:800;color:#fff}
  .c-A{background:#c0392b}.c-B{background:#c8821a}.c-C{background:#1f8f4e}
  .muted{color:var(--muted)}
  /* status select-pill */
  .stsel{border:0;border-radius:999px;padding:4px 10px;font-size:12px;font-weight:700;cursor:pointer;-webkit-appearance:none;appearance:none;text-align:center}
  .st-Finalizado{background:var(--okbg);color:var(--ok)} .st-CotacaoIniciada{background:var(--cotbg);color:var(--cot)}
  .st-ComPendencias{background:var(--pendbg);color:var(--pend)} .st-EmAndamento{background:var(--andbg);color:var(--and)}
  .st-NaoIniciado{background:var(--neubg);color:var(--neu)}
  .mapa-on{color:var(--ok);font-size:12px;font-weight:600}
  .eye{border:1px solid var(--line);background:#fff;border-radius:8px;width:30px;height:28px;cursor:pointer;color:var(--muted)}
  .eye:hover{border-color:var(--verde);color:var(--verde)}
  /* modal */
  .ov{position:fixed;inset:0;background:rgba(15,30,20,.45);display:none;align-items:flex-start;justify-content:center;padding:18px 16px;z-index:50;overflow:auto}
  .ov.open{display:flex}
  .modal{background:#fff;border-radius:16px;width:min(1080px,96%);box-shadow:0 20px 60px rgba(0,0,0,.3);overflow:hidden}
  .mhead{background:linear-gradient(135deg,var(--verde) 0%,var(--verde-d) 100%);color:#fff;padding:18px 22px;position:relative}
  .mhead .crumb{font-size:11px;letter-spacing:.6px;text-transform:uppercase;color:#bfe6cd;font-weight:700}
  .mhead .mt{font-size:21px;font-weight:800;margin:3px 0 8px}
  .mhead .meta{display:flex;gap:18px;flex-wrap:wrap;font-size:12.5px;color:#dcf3e4}
  .mhead .meta span{display:inline-flex;align-items:center;gap:5px}
  .mhead .meta .material-icons{font-size:15px;color:var(--dourado)}
  .mclose{position:absolute;top:14px;right:16px;background:rgba(255,255,255,.15);border:0;color:#fff;width:30px;height:30px;border-radius:8px;cursor:pointer;font-size:18px}
  .tabs{display:flex;gap:2px;border-bottom:1px solid var(--line);padding:0 14px;background:#fff;flex-wrap:wrap}
  .tab{padding:12px 14px;font-size:13px;font-weight:600;color:var(--muted);border-bottom:2px solid transparent;cursor:pointer;background:none;border-top:0;border-left:0;border-right:0}
  .tab.active{color:var(--verde-d);border-bottom-color:var(--dourado)}
  .tabbody{padding:20px 22px;max-height:78vh;overflow:auto}
  .box{background:#fafbfa;border:1px solid var(--line);border-radius:10px;padding:13px 15px;margin-bottom:12px}
  .box .bl{font-size:11px;text-transform:uppercase;letter-spacing:.5px;color:var(--dourado);font-weight:800;margin-bottom:5px}
  .box .bv{font-size:13.5px;line-height:1.5}
  .fld{margin-bottom:14px}
  .fld label{display:block;font-size:11.5px;text-transform:uppercase;letter-spacing:.4px;color:var(--muted);font-weight:700;margin-bottom:5px}
  .fld input,.fld select,.fld textarea{width:100%;border:1px solid var(--line);border-radius:9px;padding:9px 11px;font-size:13.5px;font-family:inherit;background:#fff}
  .fld textarea{resize:vertical;min-height:58px}
  .grid2{display:grid;grid-template-columns:1fr 1fr;gap:12px}
  .saved{font-size:11.5px;color:var(--ok);opacity:0;transition:opacity .2s}
  .saved.show{opacity:1}
  .note{background:#fbf7ea;border-left:3px solid var(--dourado);border-radius:8px;padding:11px 13px;font-size:12.5px;color:#7a611a;margin:8px 0}
  .pickrow{display:flex;gap:9px;align-items:flex-start;padding:8px 8px;border-bottom:1px solid #f1f3f2;font-size:13px;cursor:pointer;border-radius:7px}
  .pickrow:hover{background:#f7fbf8}
  .pickrow .material-icons{margin-top:1px;cursor:pointer}
  .pickrow small{font-size:11.5px}
  .btn-prim{background:var(--verde);color:#fff;border:0;border-radius:9px;padding:9px 14px;font-size:13px;font-weight:700;cursor:pointer;display:inline-flex;align-items:center;gap:6px}
  .btn-prim:hover{background:var(--verde-d)}
  .btn-ghost{background:#fff;color:var(--muted);border:1px solid var(--line);border-radius:9px;padding:9px 12px;font-size:13px;cursor:pointer}
  .btn-ghost:hover{border-color:var(--verde);color:var(--verde)}
  /* árvore (seletores de orçamento/cronograma) */
  .tree{border:1px solid var(--line);border-radius:10px;max-height:300px;overflow:auto;background:#fff}
  .tnode{display:flex;align-items:center;gap:6px;padding:5px 8px;border-bottom:1px solid #f4f6f5;font-size:12.5px;white-space:nowrap}
  .tnode:hover{background:#f7fbf8}
  .tnode.tparent{font-weight:600;color:var(--verde-d)}
  .caret{font-size:18px;color:var(--muted);cursor:pointer;width:18px;flex:0 0 18px}
  .caret-sp{width:18px;flex:0 0 18px;display:inline-block}
  .chk{font-size:17px;cursor:pointer;width:18px;flex:0 0 18px}
  .tcode{font-variant-numeric:tabular-nums;color:var(--muted);font-size:11px;min-width:54px;flex:0 0 auto}
  .tname{flex:1;overflow:hidden;text-overflow:ellipsis;cursor:pointer}
  .tval,.tdate{flex:0 0 auto;color:var(--muted);font-variant-numeric:tabular-nums;font-size:11.5px;margin-left:8px}
  .tval{color:var(--verde-d);font-weight:600}
  .pin{font-size:17px;color:var(--muted);cursor:pointer;opacity:0;flex:0 0 auto}
  .tnode:hover .pin{opacity:1}
  .pin:hover{color:var(--verde)}
  .pin.pinon{opacity:1;color:var(--ok)}                 /* tarefa selecionada: check verde sempre visível */
  .selflag{color:var(--ok);font-weight:700;font-size:11px;margin-left:6px;white-space:nowrap}
  .mk-tag{font-size:10px;background:var(--cotbg);color:var(--cot);padding:1px 5px;border-radius:5px;margin-left:4px}
  .srbox{border:1px solid var(--line);border-radius:10px;max-height:300px;overflow:auto;margin-bottom:8px;background:#fbfdfb}
  .tnode.tsel{background:var(--okbg);outline:2px solid var(--ok);border-radius:6px;font-weight:600}
  .ckl{display:inline-flex;align-items:center;gap:6px;font-size:13px;cursor:pointer}
  .chkbox{margin-top:6px;display:flex;flex-direction:column;gap:5px;padding:8px;border:1px solid var(--line);border-radius:8px;background:#fafbfa}
  .pctw{display:inline-flex;align-items:center;gap:6px} .pctbar{width:42px;height:6px;border-radius:4px;background:#e6e9e7;overflow:hidden;display:inline-block} .pctfill{display:block;height:100%} .pctn{font-size:11px;color:var(--muted);font-variant-numeric:tabular-nums}
  .pendbar{font-size:13px;color:var(--verde-d);display:flex;align-items:center;gap:6px}
  .pendbar:empty{display:none}
  .pendbar:not(:empty){background:var(--okbg);border:1px solid var(--ok);border-radius:8px;padding:8px 12px;font-weight:600;margin:6px 0}
  .badge-tp{flex:0 0 auto;font-size:9.5px;font-weight:800;padding:1px 5px;border-radius:5px;width:34px;text-align:center}
  .badge-tp.material{background:var(--cotbg);color:var(--cot)} .badge-tp.mo{background:var(--andbg);color:var(--and)}
  .tp-chip{display:inline-block;font-size:9.5px;font-weight:800;padding:1px 5px;border-radius:5px;vertical-align:1px;letter-spacing:.3px}
  .tp-mat{background:var(--cotbg);color:var(--cot)} .tp-mo{background:var(--andbg);color:var(--and)}
  .tp-emp{background:#efe7fb;color:#7c3aed} .tp-mat-mo{background:var(--okbg);color:var(--ok)} .tp-loc{background:#e7f0fb;color:#1e4fa3} .tp-none{background:#fbeae8;color:var(--pend)}
  .toast{position:fixed;bottom:16px;left:50%;transform:translateX(-50%);background:#111827;color:#fff;padding:10px 16px;border-radius:8px;font-size:13px;display:none;z-index:99}
  .empty{padding:40px;text-align:center;color:var(--muted)}
  /* matriz */
  .lg{display:inline-flex;align-items:center;gap:6px;font-size:12px;color:var(--txt)}
  .sw{width:14px;height:14px;border-radius:4px;display:inline-block;border:1px solid rgba(0,0,0,.08)}
  .c-fin{background:var(--ok)} .c-cot{background:var(--cot)} .c-prop{background:var(--dourado)}
  .c-atras{background:var(--pend)} .c-pend{background:var(--and)} .c-noprazo{background:#cfd6da} .c-none{background:#f0f2f3}
  .c-andamento{background:#0d9488}
  #mobra{min-width:170px;height:auto}
  .mtable{width:100%;border-collapse:separate;border-spacing:0;background:var(--card);border:1px solid var(--line);border-radius:12px;overflow:hidden}
  .mtable th{background:#fafbfb;padding:9px 10px;font-size:11px;color:var(--muted);border-bottom:1px solid var(--line);text-align:center;white-space:nowrap}
  .mtable th.svc-h{text-align:left;min-width:240px;position:sticky;left:0;background:#fafbfb;z-index:2}
  .mtable td{border-bottom:1px solid #f1f3f2;padding:0}
  .mtable td.svc-c{padding:8px 10px;font-size:13px;position:sticky;left:0;background:#fff;border-right:1px solid var(--line)}
  .mtable tr:hover td.svc-c{background:#f7fbf8}
  .mtable .grp-h td{background:#eef4f0;font-weight:800;color:var(--verde-d);font-size:11.5px;text-transform:uppercase;letter-spacing:.4px;padding:7px 10px;position:sticky;left:0}
  .cell{width:100%;height:34px;cursor:pointer;display:flex;align-items:center;justify-content:center;border-left:1px solid #f1f3f2}
  .cell:hover{outline:2px solid var(--verde);outline-offset:-2px}
  .cell .material-icons{font-size:15px;color:#fff;opacity:.9}
  .mtable .svc-c small{color:var(--muted);display:block;font-size:11px}
</style>
</head>
<body>
<div class="app" id="app">
  <script>try{if(localStorage.getItem('sideCollapsed')==='1')document.getElementById('app').classList.add('sidecollapsed');}catch(e){}</script>
  <aside class="side">
    <div class="brand">
      <span class="material-icons brandicon">inventory_2</span>
      <span class="brandtext">Cockpit de Suprimentos</span>
      <button class="sidetoggle" onclick="toggleSide()" title="Recolher / expandir menu"><span class="material-icons">chevron_left</span></button>
    </div>
    <div class="navlabel">Aquisições</div>
    <nav class="nav">
      <a id="nav-dashboard" data-menu="dashboard" title="Dashboard" onclick="toast('Dashboard — próxima etapa')"><span class="material-icons">dashboard</span> <span class="navtxt">Dashboard</span></a>
      <a id="nav-radar" data-menu="radar" class="active" title="Radar de Aquisições" onclick="showView('radar')"><span class="material-icons">radar</span> <span class="navtxt">Radar de Aquisições</span></a>
      <a id="nav-matriz" data-menu="matriz" title="Matriz" onclick="showView('matriz')"><span class="material-icons">grid_on</span> <span class="navtxt">Matriz</span></a>
      <a id="nav-cotacoes" data-menu="cotacoes" title="Mapa de Cotações" onclick="toast('Mapa de Cotações — Fase 3')"><span class="material-icons">request_quote</span> <span class="navtxt">Mapa de Cotações</span></a>
    </nav>
    <div class="navlabel">Administração</div>
    <nav class="nav">
      <a id="nav-config" data-menu="config" title="Configurações" onclick="showView('config')"><span class="material-icons">settings</span> <span class="navtxt">Configurações</span></a>
      <a id="nav-updates" data-menu="updates" title="Atualizações" onclick="showView('updates')"><span class="material-icons">history</span> <span class="navtxt">Atualizações</span> <span class="navbadge" style="font-size:9px;background:var(--dourado);color:#fff;padding:1px 5px;border-radius:5px;margin-left:auto">temp</span></a>
      <a id="nav-audit" data-menu="audit" title="Auditoria" onclick="showView('audit')"><span class="material-icons">fact_check</span> <span class="navtxt">Auditoria</span> <span class="navbadge" style="font-size:9px;background:var(--dourado);color:#fff;padding:1px 5px;border-radius:5px;margin-left:auto">temp</span></a>
    </nav>
    <div class="whoami" id="whoami"></div>
  </aside>

  <main class="main">
   <section id="view-radar">
    <div class="top" style="display:flex;justify-content:space-between;align-items:flex-start;gap:12px">
      <div>
        <h1 class="h1"><span class="material-icons" style="color:var(--dourado)">radar</span> Radar de Aquisições</h1>
        <p class="sub" id="sub">Carregando…</p>
      </div>
      <div style="display:flex;gap:8px;flex:0 0 auto;margin-top:4px">
        <button class="btn-ghost" onclick="recarregar()" title="Recarregar do servidor — evita trabalhar com dado que outra pessoa já curou"><span class="material-icons" style="font-size:18px">refresh</span> Atualizar</button>
        <button id="btnNovo" class="btn-prim" onclick="novoItem()"><span class="material-icons" style="font-size:18px">add</span> Novo item</button>
      </div>
    </div>
    <div class="kpis" id="kpis"></div>

    <div class="panel" style="margin-top:8px">
      <div class="bar" style="padding:8px 12px;gap:8px">
        <div class="search" style="min-width:180px"><span class="material-icons" style="color:var(--muted)">search</span>
          <input id="q" placeholder="Buscar item, contratação ou responsável…" oninput="render()"></div>
        <label class="toggle" style="gap:6px">Ver
          <select id="fview" onchange="render()"><option value="agrupado">Agrupado</option><option value="lista">Lista</option></select></label>
        <span class="toggle" style="gap:4px;color:var(--muted)"><span class="material-icons" style="font-size:15px">swap_vert</span>clique numa coluna p/ ordenar</span>
        <button class="btn-ghost" id="filtBtn" onclick="toggleFiltros()"><span class="material-icons" style="font-size:16px;vertical-align:-3px">tune</span> Filtros<span id="filtBadge"></span></button>
        <button class="btn-ghost" id="collapseBtn" onclick="toggleAllGroups()" style="margin-left:auto"></button>
      </div>
      <div class="bar" id="advFilters" style="padding:0 12px 10px;gap:8px;display:none">
        <select id="fobra" onchange="render()"><option value="">Todas as obras</option></select>
        <select id="fgrupo" onchange="render()"><option value="">Todos os grupos</option></select>
        <select id="fcurva" onchange="render()"><option value="">Todas as curvas</option><option>A</option><option>B</option><option>C</option></select>
        <select id="fstatus" onchange="render()"><option value="">Todos os status</option></select>
        <select id="fresp" onchange="render()"><option value="">Todos os responsáveis</option></select>
        <label class="toggle"><input type="checkbox" id="onlyalert" onchange="render()"> Somente em alerta</label>
        <select id="fcurada" onchange="render()" title="filtrar pela verba curada"><option value="">Verba: todas</option><option value="sim">Só curadas ✓</option><option value="nao">Só não curadas</option></select>
      </div>
    </div>

    <div class="wrap">
      <table>
        <thead><tr>
          <th class="srt" onclick="sortBy('nome')">Item<span class="sar" id="sar-nome"></span></th>
          <th class="srt" onclick="sortBy('curva')">Cv<span class="sar" id="sar-curva"></span></th>
          <th class="srt" onclick="sortBy('resp')">Resp.<span class="sar" id="sar-resp"></span></th>
          <th class="srt" onclick="sortBy('verba')">Verba (R$)<span class="sar" id="sar-verba"></span></th>
          <th class="srt" onclick="sortBy('quant')">Quant.<span class="sar" id="sar-quant"></span></th>
          <th class="srt" onclick="sortBy('obra')">Data em obra<span class="sar" id="sar-obra"></span></th>
          <th class="srt" onclick="sortBy('pct')">% obra<span class="sar" id="sar-pct"></span></th>
          <th class="srt" onclick="sortBy('gatilho')">Início cotação<span class="sar" id="sar-gatilho"></span></th>
          <th class="srt" onclick="sortBy('fim')">Fim cotação<span class="sar" id="sar-fim"></span></th>
          <th class="srt" onclick="sortBy('status')">Status<span class="sar" id="sar-status"></span></th>
          <th>Mapa</th><th></th>
        </tr></thead>
        <tbody id="tb"><tr><td colspan="12" class="empty">Carregando…</td></tr></tbody>
      </table>
    </div>
   </section>

   <section id="view-matriz" style="display:none">
    <div class="top">
      <h1 class="h1"><span class="material-icons" style="color:var(--dourado)">grid_on</span> Matriz de Aquisições</h1>
      <p class="sub" id="msub">Serviços × obras — status de cada aquisição por obra.</p>
    </div>
    <div class="panel" style="margin-bottom:8px">
      <div class="bar" style="gap:16px">
        <span class="lg"><span class="sw c-fin"></span> Finalizado</span>
        <span class="lg"><span class="sw c-cot"></span> Em cotação (no prazo)</span>
        <span class="lg"><span class="sw c-andamento"></span> Em andamento</span>
        <span class="lg"><span class="sw c-prop"></span> Proposta recebida</span>
        <span class="lg"><span class="sw c-atras"></span> Atrasado (passou do gatilho)</span>
        <span class="lg"><span class="sw c-pend"></span> Com pendências</span>
        <span class="lg"><span class="sw c-noprazo"></span> No prazo, não iniciado</span>
        <span class="lg"><span class="sw c-none"></span> N/A</span>
      </div>
    </div>
    <div class="panel">
      <div class="bar">
        <select id="mobra" multiple size="1" onchange="renderMatriz()" title="Segure Ctrl para escolher várias obras"></select>
        <select id="mgrupo" onchange="renderMatriz()"><option value="">Todos os grupos</option></select>
        <select id="mcurva" onchange="renderMatriz()"><option value="">Todas as curvas</option><option>A</option><option>B</option><option>C</option></select>
        <div class="search"><span class="material-icons" style="color:var(--muted)">search</span>
          <input id="mq" placeholder="Filtrar serviço…" oninput="renderMatriz()"></div>
      </div>
    </div>
    <div class="wrap" id="mwrap"></div>
   </section>

   <section id="view-config" style="display:none">
    <div class="top" style="display:flex;justify-content:space-between;align-items:flex-start;gap:12px">
      <div>
        <h1 class="h1"><span class="material-icons" style="color:var(--dourado)">settings</span> Configurações — Usuários e Permissões</h1>
        <p class="sub">Controle de acesso atrelado ao Bitrix24. Quem não estiver aqui não vê nada no sistema.</p>
      </div>
      <button class="btn-prim" onclick="userForm()" style="flex:0 0 auto;margin-top:4px"><span class="material-icons" style="font-size:18px">person_add</span> Adicionar usuário</button>
    </div>
    <div class="panel">
      <h3>O que cada papel faz</h3>
      <div class="bar" style="flex-wrap:wrap;gap:14px;font-size:12.5px">
        <span><b>Administrador</b> — tudo + esta tela</span>
        <span><b>Diretor</b> — vê todas as obras (leitura)</span>
        <span><b>Suprimentos</b> — pode ser responsável por itens; vê todas, edita as obras liberadas</span>
        <span><b>Coordenador</b> — vê só as obras liberadas (leitura)</span>
        <span><b>Personalizado</b> — você define tudo</span>
      </div>
    </div>
    <div class="wrap" id="cfgwrap"></div>
   </section>

   <section id="view-audit" style="display:none">
    <div class="top">
      <h1 class="h1"><span class="material-icons" style="color:var(--dourado)">fact_check</span> Auditoria de Orçamento</h1>
      <p class="sub">Linhas do orçamento usadas em <b>2+ itens</b> (verba contada em dobro). Cronograma não entra aqui — datas/marcos podem ser compartilhados. <b>Ferramenta temporária</b> de limpeza desta obra.</p>
    </div>
    <div id="auditwrap" style="margin:8px 26px 30px"><div class="empty">Carregando…</div></div>
   </section>

   <section id="view-updates" style="display:none">
    <div class="top" style="display:flex;justify-content:space-between;align-items:flex-start;gap:12px">
      <div>
        <h1 class="h1"><span class="material-icons" style="color:var(--dourado)">history</span> Atualizações</h1>
        <p class="sub">Últimas curadorias da equipe — cronograma, orçamento, quantitativo e criação de itens. Use pra não trabalhar num item que outra pessoa já mexeu. <b>Tela temporária</b>.</p>
      </div>
      <button class="btn-ghost" onclick="renderUpdates()" style="flex:0 0 auto;margin-top:4px"><span class="material-icons" style="font-size:18px">refresh</span> Atualizar</button>
    </div>
    <div id="updwrap" style="margin:8px 26px 30px"><div class="empty">Carregando…</div></div>
   </section>
  </main>
</div>

<!-- modal -->
<div class="ov" id="ov">
  <div class="modal" id="modal"></div>
</div>
<div class="toast" id="toastEl"></div>

<script>
let DATA={itens:[],obra:{}}, CUR=null, TAB='Resumo';
let RESP=[];                       // responsáveis possíveis (papel "comprador" = Suprimentos)
let GORDER=[];                     // ordem atual dos grupos (preenchida no render)
let COLLAPSED=new Set();           // grupos recolhidos (persistido em localStorage)
try{ COLLAPSED=new Set(JSON.parse(localStorage.getItem('sup_collapsed')||'[]')); }catch(e){}
function saveCollapsed(){ try{ localStorage.setItem('sup_collapsed',JSON.stringify([...COLLAPSED])); }catch(e){} }
const BRL=n=>n?Number(n).toLocaleString('pt-BR',{style:'currency',currency:'BRL',maximumFractionDigits:0}):'—';
const D=s=>{if(!s)return'—';const p=String(s).split('-');return p.length===3?p[2]+'/'+p[1]+'/'+p[0]:s;};
const esc=s=>(s==null?'':String(s)).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
const today=new Date().toISOString().slice(0,10);
const STK={'Finalizado':'st-Finalizado','Cotação Iniciada':'st-CotacaoIniciada','Com Pendências':'st-ComPendencias','Em Andamento':'st-EmAndamento','Não Iniciado':'st-NaoIniciado'};
const STATUSES=['Não Iniciado','Cotação Iniciada','Com Pendências','Em Andamento','Finalizado'];
function toast(m){const t=document.getElementById('toastEl');t.textContent=m;t.style.display='block';clearTimeout(t._);t._=setTimeout(()=>t.style.display='none',2400);}
const byOrdem=o=>DATA.itens.find(i=>i.ordem==o);
function daysBetween(a,b){ return Math.round((new Date(b)-new Date(a))/86400000); }
/* nível de alerta da cotação: 'critico' (fim venceu, não finalizado) > 'atrasado' (início venceu, não iniciou)
   > 'proximo' (faltam ≤7d p/ iniciar) > 'ok'. Item finalizado saiu do radar de Suprimentos = ok. */
function alertLevel(i){
  const st=i.status||'Não Iniciado';
  if(st==='Finalizado') return 'finalizado';                  // concluído (fica no radar p/ consulta; sem alerta)
  const F=i.fim_cotacao, I=i.inicio_cotacao||i.data_gatilho;
  if(F && F<today) return 'critico';                          // passou o FIM e não está finalizado
  if(st==='Não Iniciado'){
    if(I && I<today) return 'atrasado';                       // passou o INÍCIO e ainda não começou
    if(I && daysBetween(today,I)<=7) return 'proximo';        // início chegando (≤7 dias)
  }
  return 'ok';
}
const isAlert=i=>['critico','atrasado','proximo'].includes(alertLevel(i)); // 'finalizado'/'ok' não são alerta

async function load(){
  try{
    const d=await (await fetch('actions/matriz.php')).json();
    if(d.error){document.getElementById('tb').innerHTML=`<tr><td colspan="12" class="empty">Erro: ${esc(d.error)}</td></tr>`;return;}
    DATA=d; const o=d.obra,rs=d.resumo;
    const obras=[...new Set(d.itens.map(i=>i.obra_nome).filter(Boolean))];
    const obraTxt=obras.length>1?`${obras.length} obras`:`<b>${esc(o.nome)}</b> · ${esc(o.codinome)} — ${esc(o.local||'')}`;
    document.getElementById('sub').innerHTML=`Mostrando: ${obraTxt} · ligado ao cronograma, orçamento e dicionário · hoje: ${D(today)}`+(rs.crono_erro?` · <span style="color:var(--pend)">cronograma offline</span>`:'');
    // KPIs
    const comData=d.itens.filter(i=>i.data_necessaria).length;
    const criticos=d.itens.filter(i=>alertLevel(i)==='critico').length;
    const atrasados=d.itens.filter(i=>alertLevel(i)==='atrasado').length;
    const cv=k=>d.itens.filter(i=>i.curva===k).length;
    document.getElementById('kpis').innerHTML=`
      <div class="kpi"><div class="v">${rs.total}</div><div class="l">Itens no radar</div></div>
      <div class="kpi"><div class="v">${comData} / ${rs.total}</div><div class="l">Com data definida</div></div>
      <div class="kpi"><div class="v ${criticos?'alert':''}">${criticos}</div><div class="l">Críticos (fim da cotação venceu)${atrasados?` · ${atrasados} atrasados`:''}</div></div>
      <div class="kpi"><div class="v">${cv('A')} · ${cv('B')} · ${cv('C')}</div><div class="l">Curva A / B / C</div></div>
      <div class="kpi" title="${rs.cobertura_real!=null?`Cobertura REAL, sem contar verba em dobro: ${rs.cobertura_analitico}% por vínculo analítico (linhas distintas) + ${rs.cobertura_composicao}% por composição. Coberto ${BRL(rs.cobertura_valor)} de ${BRL(rs.cobertura_total_leaf)} em folhas.`:'sem dados'}"><div class="v gold">${rs.cobertura_real!=null?rs.cobertura_real.toLocaleString('pt-BR')+'%':'—'}</div><div class="l">Cobertura real do orçamento</div></div>`;
    // filtros dinâmicos (grupos em ordem lógica = ordem de aparição; demais ordenados)
    fillOrdered('fgrupo',[...new Set(d.itens.map(i=>i.grupo).filter(Boolean))]);
    fill('fobra',obras);
    fill('fstatus',[...new Set(d.itens.map(i=>i.status||'Não Iniciado'))]);
    fill('fresp',[...new Set(d.itens.map(i=>i.responsavel).filter(Boolean))]);
    // filtros da Matriz
    fillOrdered('mgrupo',[...new Set(d.itens.map(i=>i.grupo).filter(Boolean))]);
    fillMulti('mobra',obras);
    render(); renderMatriz();
  }catch(e){document.getElementById('tb').innerHTML=`<tr><td colspan="12" class="empty">Falha: ${esc(e.message)}</td></tr>`;}
}
function fill(id,arr){const el=document.getElementById(id);const keep=el.value;el.innerHTML=el.children[0].outerHTML+arr.slice().sort().map(v=>`<option>${esc(v)}</option>`).join('');el.value=keep;}
function fillOrdered(id,arr){const el=document.getElementById(id);const keep=el.value;el.innerHTML=el.children[0].outerHTML+arr.map(v=>`<option>${esc(v)}</option>`).join('');el.value=keep;}
function fillMulti(id,arr){const el=document.getElementById(id);el.innerHTML=arr.map(v=>`<option selected>${esc(v)}</option>`).join('');el.size=Math.min(Math.max(arr.length,1),4);}

/* ---------- view switch ---------- */
function showView(v){
  ['radar','matriz','config','audit','updates'].forEach(x=>{
    document.getElementById('view-'+x).style.display=v===x?'':'none';
    document.getElementById('nav-'+x).classList.toggle('active',v===x);
  });
  if(v==='matriz') renderMatriz();
  if(v==='config') renderConfig();
  if(v==='radar') fitRadarHeight();
  if(v==='audit') renderAudit();
  if(v==='updates') renderUpdates();
}

/* ===== Auditoria de Orçamento (temporária) — duplicação de vínculo de verba ===== */
async function renderAudit(){
  const box=document.getElementById('auditwrap');
  box.innerHTML='<div class="empty">Rodando auditoria na base…</div>';
  let d,u;
  try{
    d=await (await fetch('actions/audit_orcamento.php')).json();
    u=await (await fetch('actions/verba_usos.php?_='+Date.now())).json();
  }
  catch(e){ box.innerHTML='<div class="empty">Falha: '+esc(e.message)+'</div>'; return; }
  if(d.error){ box.innerHTML='<div class="empty">Erro: '+esc(d.error)+'</div>'; return; }
  if(u.error){ box.innerHTML='<div class="empty">Erro (usos): '+esc(u.error)+'</div>'; return; }
  const dups=(u&&u.duplicatas)||[];
  const inflado=dups.reduce((s,x)=>s+(x.valor||0)*((x.n||1)-1),0);
  const pct=d.cobertura_distinta_pct_folhas;
  let html=`<div class="kpis" style="padding:0 0 14px">
    <div class="kpi"><div class="v gold">${pct}%</div><div class="l">Cobertura real (analítico, distinto)</div></div>
    <div class="kpi"><div class="v">${BRL(d.valor_coberto_distinto)}</div><div class="l">de ${BRL(d.total_leaf)} em folhas</div></div>
    <div class="kpi"><div class="v ${dups.length?'alert':''}">${dups.length}</div><div class="l">Linhas em 2+ itens (analítico + composição)</div></div>
    <div class="kpi"><div class="v ${inflado?'alert':''}">${BRL(inflado)}</div><div class="l">Verba inflada por duplicação</div></div>
    <div class="kpi"><div class="v">${d.composicao_itens}</div><div class="l">Itens por composição</div></div>
  </div>`;
  if(!dups.length){
    html+='<div class="panel" style="padding:18px 16px"><b style="color:var(--ok)">✓ Sem duplicação.</b> Cada linha do orçamento está em no máximo um item — a verba não está contada em dobro.</div>';
  } else {
    html+='<div class="note">Cada linha abaixo compõe a verba de 2+ itens (conta em dobro). Deixe em <b>um</b>: nos usos <b>analíticos</b> clique “remover daqui”; nos que usam por <b>composição</b>, abra o item e ajuste os locais.</div>';
    html+='<div class="wrap" style="margin:0"><table><thead><tr><th>Linha do orçamento</th><th>R$ × usos</th><th>Itens que a usam</th></tr></thead><tbody>';
    for(const dup of dups){
      html+=`<tr>
        <td><div class="svc">${esc((dup.descricao||'').slice(0,90))}</div><div class="svc-sub">${esc(dup.path||'')}</div></td>
        <td class="money">${BRL(dup.valor)} <span class="muted">×${dup.n}</span></td>
        <td>${dup.itens.map(it=>{ const vias=it.vias||[]; const ana=vias.some(v=>v==='analítico'); const comp=vias.some(v=>v.indexOf('composição')===0);
          return `<div style="display:flex;align-items:center;gap:8px;padding:3px 0">
          <span class="tp-chip ${comp&&!ana?'tp-mat-mo':'tp-mat'}">${ana&&comp?'ambos':comp?'composição':'analítico'}</span>
          <span style="flex:1">${esc(it.nome)} <span class="muted" style="font-size:11px">· ${esc(vias.join(' · '))}</span></span>
          ${ana?`<button class="btn-ghost" style="padding:3px 9px;font-size:12px;color:var(--pend)" onclick="auditRemover(${it.ordem},${dup.id})">remover daqui</button>`
               :`<button class="btn-ghost" style="padding:3px 9px;font-size:12px" onclick="openModal(${it.ordem})">abrir item</button>`}
        </div>`;}).join('')}</td></tr>`;
    }
    html+='</tbody></table></div>';
  }
  box.innerHTML=html;
}
/* ===== Atualizações (temporária) — feed das últimas curadorias da equipe ===== */
function fmtDateTime(s){ if(!s)return'—'; const d=new Date(s); if(isNaN(d))return String(s); const p=n=>String(n).padStart(2,'0'); return `${p(d.getDate())}/${p(d.getMonth()+1)} ${p(d.getHours())}:${p(d.getMinutes())}`; }
function updCat(c){ c=c||'';
  if(/^Verba/i.test(c)) return ['orçamento','tp-mat'];
  if(/^Quantitativo/i.test(c)) return ['quantitativo','tp-mo'];
  if(/cronograma|data em obra|âncora|vínculo de cronograma/i.test(c)) return ['cronograma','tp-emp'];
  if(/criar|criou|desdobr|exclu|^item|^nome/i.test(c)) return ['item','tp-loc'];
  return ['edição','tp-none'];
}
function progCard(label,done,total,icon){
  const falta=Math.max(total-done,0), pct=total?Math.round(done/total*100):0;
  return `<div class="kpi" style="min-width:210px">
    <div class="l" style="display:flex;align-items:center;gap:6px;margin-bottom:2px"><span class="material-icons" style="font-size:16px;color:var(--dourado)">${icon}</span>${label}</div>
    <div class="v">${done} <span class="muted" style="font-size:15px;font-weight:600">/ ${total}</span> curados</div>
    <div style="height:7px;background:var(--line);border-radius:5px;overflow:hidden;margin:7px 0 4px"><div style="height:100%;width:${pct}%;background:var(--ok);transition:width .3s"></div></div>
    <div class="l">${pct}% feito · <b style="color:var(--pend)">faltam ${falta}</b></div>
  </div>`;
}
async function renderUpdates(){
  const box=document.getElementById('updwrap');
  box.innerHTML='<div class="empty">Carregando…</div>';
  try{ await load(); }catch(e){}                 // recarrega o matriz p/ os contadores ficarem frescos
  const its=DATA.itens||[], tot=its.length;
  const cards=`<div style="font-size:13px;color:var(--verde-d);font-weight:800;margin:0 0 8px">Progresso da curadoria</div>
    <div class="kpis" style="padding:0 0 16px">
      ${progCard('Cronograma', its.filter(i=>i.curado_data).length, tot, 'event')}
      ${progCard('Orçamento (verba)', its.filter(i=>i.curado_verba).length, tot, 'request_quote')}
      ${progCard('Quantitativo', its.filter(i=>i.curado_quant).length, tot, 'straighten')}
    </div>`;
  let d;
  try{ d=await (await fetch('actions/historico.php?_='+Date.now())).json(); }
  catch(e){ box.innerHTML=cards+'<div class="empty">Falha ao carregar o histórico: '+esc(e.message)+'</div>'; return; }
  const hs=(d&&d.historico)||[];
  let feed='<div style="font-size:13px;color:var(--verde-d);font-weight:800;margin:4px 0 6px">Últimas alterações</div>';
  if(!hs.length){ feed+='<div class="empty">Nenhuma alteração registrada ainda.</div>'; }
  else {
    feed+=`<div class="note">As ${hs.length} mais recentes — quem · quando · item · o quê. Clique numa linha pra abrir o item.</div>
      <div class="wrap" style="margin:0"><table><thead><tr><th>Quando</th><th>Quem</th><th>Item (grupo)</th><th>O que mudou</th></tr></thead><tbody>`;
    for(const h of hs){
      const [lbl,cls]=updCat(h.campo);
      const v=(h.valor_depois!=null&&String(h.valor_depois)!=='')?`: <b>${esc(String(h.valor_depois).slice(0,70))}</b>`:'';
      const it=byOrdem(h.servico_id);
      feed+=`<tr ${it?`onclick="openModal(${h.servico_id})" style="cursor:pointer"`:''}>
        <td class="muted" style="white-space:nowrap;font-size:12px">${fmtDateTime(h.created_at)}</td>
        <td style="white-space:nowrap">${esc(h.usuario_nome||('#'+(h.bitrix_id||'')))}</td>
        <td><div class="svc">${esc(h.item_nome||'—')}</div><div class="svc-sub">${esc(h.grupo||'')}</div></td>
        <td><span class="tp-chip ${cls}">${lbl}</span> ${esc(h.campo||'')}${v}</td>
      </tr>`;
    }
    feed+='</tbody></table></div>';
  }
  box.innerHTML=cards+feed;
}
async function recarregar(){ await load(); toast('Radar atualizado'); }
async function auditRemover(ordem,lineId){
  const it=byOrdem(ordem); if(!it){toast('item não encontrado — recarregue');return;}
  const cur=(it.orcamento_refs||[]).map(Number);
  const novo=cur.filter(x=>x!==Number(lineId));
  if(novo.length===cur.length){toast('essa linha não está mais nesse item');renderAudit();return;}
  if(!confirm(`Remover esta linha do orçamento de "${it.nome}"?\nA verba dele será recalculada sem essa linha.`))return;
  try{
    const d=await (await fetch('actions/item_update.php',{method:'POST',headers:{'Content-Type':'application/json'},
      body:JSON.stringify({ordem,campos:{orcamento_refs:novo},me:EU&&EU.bitrix_id})})).json();
    if(d.error){toast('Erro: '+d.error);return;}
  }catch(e){toast('Falha ao salvar');return;}
  VERBA_USOS=null; await load(); renderAudit(); toast('Linha removida de '+it.nome);
}

/* ---------- matriz ---------- */
function cellClass(i){
  if(!i) return 'c-none';
  const st=i.status||'Não Iniciado';
  if(st==='Finalizado') return 'c-fin';
  if(i.propostas>0) return 'c-prop';
  if(st==='Cotação Iniciada') return 'c-cot';
  if(st==='Em Andamento') return 'c-andamento';
  if(st==='Com Pendências') return 'c-pend';
  if(isAlert(i)) return 'c-atras';
  return 'c-noprazo';
}
const CELL_TXT={'c-fin':'Finalizado','c-cot':'Em cotação','c-andamento':'Em andamento','c-prop':'Proposta recebida','c-atras':'Atrasado','c-pend':'Com pendências','c-noprazo':'No prazo, não iniciado','c-none':'N/A'};
function renderMatriz(){
  const sel=[...document.getElementById('mobra').selectedOptions].map(o=>o.value);
  const allObras=[...new Set(DATA.itens.map(i=>i.obra_nome).filter(Boolean))];
  const obras=sel.length?sel:allObras;
  const fg=document.getElementById('mgrupo').value,fc=document.getElementById('mcurva').value;
  const q=(document.getElementById('mq').value||'').toLowerCase();
  // serviços (linhas) distintos por ordem, na ordem lógica de grupo
  const filt=DATA.itens.filter(i=>(!fg||i.grupo===fg)&&(!fc||i.curva===fc)&&(!q||i.nome.toLowerCase().includes(q)));
  const seen=new Map();
  for(const i of filt){ if(!seen.has(i.ordem)) seen.set(i.ordem,{ordem:i.ordem,nome:i.nome,grupo:i.grupo,curva:i.curva}); }
  const servicos=[...seen.values()];
  if(!servicos.length||!obras.length){document.getElementById('mwrap').innerHTML='<div class="empty">Sem dados para os filtros.</div>';return;}
  // índice (ordem|obra) -> item
  const idx={}; DATA.itens.forEach(i=>idx[i.ordem+'|'+i.obra_nome]=i);
  let html='<table class="mtable"><thead><tr><th class="svc-h">Serviço</th>'+
    obras.map(o=>`<th>${esc(o)}</th>`).join('')+'</tr></thead><tbody>';
  let grupo=null;
  for(const s of servicos){
    if(s.grupo!==grupo){grupo=s.grupo;
      html+=`<tr class="grp-h"><td colspan="${obras.length+1}">${esc(grupo)}</td></tr>`;}
    html+=`<tr><td class="svc-c">${esc(s.nome)}<small>Curva ${esc(s.curva||'—')}</small></td>`;
    for(const o of obras){
      const i=idx[s.ordem+'|'+o]; const cls=cellClass(i);
      const tip=i?`${esc(o)} · ${esc(s.nome)}\n${CELL_TXT[cls]}`+(i.data_necessaria?` · obra ${D(i.data_necessaria)}`:''):'N/A';
      const click=i?`onclick="openModal(${i.ordem})"`:'';
      html+=`<td><div class="cell ${cls}" title="${tip}" ${click}></div></td>`;
    }
    html+='</tr>';
  }
  html+='</tbody></table>';
  document.getElementById('mwrap').innerHTML=html;
}

/* ---------- responsáveis / grupos / ordenação (ajuste 1) ---------- */
async function loadResponsaveis(){
  try{ const d=await (await fetch('actions/usuarios.php?responsaveis=1')).json(); RESP=d.responsaveis||[]; }
  catch(e){ RESP=[]; }
}
function respOptions(current){
  const names=RESP.map(r=>r.nome);
  let o=`<option value="">— escolher responsável —</option>`;
  if(current && !names.includes(current)) o+=`<option value="${esc(current)}" selected>${esc(current)} (não cadastrado)</option>`;
  o+=RESP.map(r=>`<option value="${esc(r.nome)}" ${r.nome===current?'selected':''}>${esc(r.nome)}</option>`).join('');
  return o;
}
function grupoOptions(current){
  const gs=[...new Set(DATA.itens.map(i=>i.grupo).filter(Boolean))];
  if(current && !gs.includes(current)) gs.unshift(current);
  return gs.map(g=>`<option ${g===current?'selected':''}>${esc(g)}</option>`).join('')
       + `<option value="__novo__">➕ Novo grupo…</option>`;
}
/* ordenação por coluna (clicável). def = sentido padrão no 1º clique (1=asc, -1=desc) */
const COLS={
  nome:   {val:i=>(i.nome||'').toLowerCase(),                         def:1},   // texto A→Z
  curva:  {val:i=>i.curva||'',                                        def:1},   // A→C
  resp:   {val:i=>(i.responsavel||'').toLowerCase(),                  def:1},
  verba:  {val:i=>(i.verba!=null&&i.verba!==0)?i.verba:null,          def:-1},  // maior→menor
  quant:  {val:i=>i.quantitativo!=null?i.quantitativo:null,           def:-1},
  obra:   {val:i=>i.data_necessaria||null,                            def:1},   // mais antiga→recente
  pct:    {val:i=>i.cronograma_pct!=null?i.cronograma_pct:null,       def:-1},
  gatilho:{val:i=>i.data_gatilho||null,                               def:1},   // início cotação
  fim:    {val:i=>i.fim_cotacao||null,                                def:1},
  status: {val:i=>i.status||'',                                       def:1},
};
let SORT={key:'gatilho', dir:1};               // padrão: início da cotação, mais antiga primeiro
function cmpItems(a,b){
  const c=COLS[SORT.key]; if(!c) return 0;
  const va=c.val(a), vb=c.val(b);
  const na=(va==null||va===''), nb=(vb==null||vb==='');
  if(na&&nb) return 0; if(na) return 1; if(nb) return -1;   // vazio sempre por último
  return va<vb?-1*SORT.dir:(va>vb?1*SORT.dir:0);
}
function sortBy(key){
  if(!COLS[key]) return;
  if(SORT.key===key) SORT.dir=-SORT.dir;       // mesmo: inverte o sentido
  else { SORT.key=key; SORT.dir=COLS[key].def; }
  render();
}
function updateSortArrows(){
  Object.keys(COLS).forEach(k=>{
    const e=document.getElementById('sar-'+k); if(e) e.textContent='';
    const th=e?e.parentElement:null; if(th) th.classList.remove('on');
  });
  const e=document.getElementById('sar-'+SORT.key);
  if(e){ e.textContent=SORT.dir>0?' ▲':' ▼'; if(e.parentElement) e.parentElement.classList.add('on'); }
}
/* recolher / expandir */
function toggleGroup(idx){ const g=GORDER[idx]; if(g==null)return; COLLAPSED.has(g)?COLLAPSED.delete(g):COLLAPSED.add(g); saveCollapsed(); render(); }
function toggleAllGroups(){
  const groups=[...new Set(DATA.itens.map(i=>i.grupo||'—'))];
  const anyOpen=groups.some(g=>!COLLAPSED.has(g));
  if(anyOpen) groups.forEach(g=>COLLAPSED.add(g)); else COLLAPSED.clear();
  saveCollapsed(); render();
}
function updateCollapseBtn(){
  const b=document.getElementById('collapseBtn'); if(!b)return;
  const flat=document.getElementById('fview').value==='lista';
  b.style.display=flat?'none':'';
  const groups=[...new Set(DATA.itens.map(i=>i.grupo||'—'))];
  const anyOpen=groups.some(g=>!COLLAPSED.has(g));
  b.innerHTML=`<span class="material-icons" style="font-size:16px;vertical-align:-3px">${anyOpen?'unfold_less':'unfold_more'}</span> ${anyOpen?'Recolher tudo':'Expandir tudo'}`;
}
function toggleFiltros(){ const a=document.getElementById('advFilters'); if(a) a.style.display=(a.style.display==='none'?'flex':'none'); fitRadarHeight(); }
// dá altura à área da tabela do radar pra o cabeçalho poder ficar fixo (sticky) ao rolar
function fitRadarHeight(){
  const w=document.querySelector('#view-radar .wrap'); if(!w) return;
  const top=w.getBoundingClientRect().top;
  if(top<=0) return;                 // view do radar oculta — ajusta quando voltar
  w.style.maxHeight=Math.max(window.innerHeight-top-24,220)+'px';
}
/* reordenar / renomear grupos (admin) */
async function grupoMover(idx,dir){
  const arr=GORDER.slice(); const j=idx+dir;
  if(j<0||j>=arr.length)return;
  [arr[idx],arr[j]]=[arr[j],arr[idx]];
  const order=arr.filter(g=>g!=='—');   // não envia o pseudo-grupo "sem grupo" ao reorder
  try{ await fetch('actions/grupos.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({acao:'reorder',ordem:order,me:EU&&EU.bitrix_id})}); }
  catch(e){ toast('Falha ao reordenar'); return; }
  await load();
}
async function grupoRenomear(idx){
  const g=GORDER[idx]; if(g==null)return;
  const to=(prompt('Renomear grupo:',g)||'').trim();
  if(!to||to===g)return;
  try{ await fetch('actions/grupos.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({acao:'rename',from:g,to,me:EU&&EU.bitrix_id})}); }
  catch(e){ toast('Falha ao renomear'); return; }
  if(COLLAPSED.has(g)){ COLLAPSED.delete(g); COLLAPSED.add(to); saveCollapsed(); }
  await load(); toast('Grupo renomeado');
}
function groupHeaderHtml(g,items,idx){
  const collapsed=COLLAPSED.has(g);
  const n=items.length;
  const verba=items.reduce((s,i)=>s+(i.verba||0),0);
  const datas=items.map(i=>i.data_gatilho).filter(Boolean).sort();
  const prox=datas.length?` · próx. início ${D(datas[0])}`:'';
  const adm=(IS_ADMIN && g!=='—')?`<span class="gctl">
      <button class="gbtn" title="subir grupo" ${idx<=0?'disabled':''} onclick="event.stopPropagation();grupoMover(${idx},-1)">▲</button>
      <button class="gbtn" title="descer grupo" ${idx>=GORDER.length-1?'disabled':''} onclick="event.stopPropagation();grupoMover(${idx},1)">▼</button>
      <button class="gbtn" title="renomear grupo" onclick="event.stopPropagation();grupoRenomear(${idx})"><span class="material-icons" style="font-size:14px">edit</span></button>
    </span>`:'';
  return `<tr class="grp" onclick="toggleGroup(${idx})"><td colspan="12"><span class="gwrap">
      <span class="material-icons gcaret">${collapsed?'chevron_right':'expand_more'}</span>
      <span class="gname">${esc(g)}</span>${adm}
      <span class="gcount">· ${n} ${n>1?'itens':'item'} · ${BRL(verba)}${prox}</span>
    </span></td></tr>`;
}
function render(){
  const q=(document.getElementById('q').value||'').toLowerCase();
  const fg=document.getElementById('fgrupo').value,fo=document.getElementById('fobra').value;
  const fc=document.getElementById('fcurva').value;
  const fs=document.getElementById('fstatus').value,fr=document.getElementById('fresp').value;
  const oa=document.getElementById('onlyalert').checked;
  const fcd=document.getElementById('fcurada')?document.getElementById('fcurada').value:'';
  const flat=document.getElementById('fview').value==='lista';
  const _naf=[fo,fg,fc,fs,fr].filter(Boolean).length+(oa?1:0)+(fcd?1:0);
  const _fb=document.getElementById('filtBadge'); if(_fb) _fb.textContent=_naf?` ·${_naf}`:'';
  const rows=DATA.itens.filter(i=>
    (!q||(i.nome+' '+(i.forma_contratacao||'')+' '+(i.responsavel||'')).toLowerCase().includes(q))&&
    (!fg||i.grupo===fg)&&(!fo||i.obra_nome===fo)&&(!fc||i.curva===fc)&&
    (!fs||(i.status||'Não Iniciado')===fs)&&(!fr||i.responsavel===fr)&&(!oa||isAlert(i))&&
    (!fcd||(fcd==='sim'?i.curado_verba:!i.curado_verba)));
  // ordem completa dos grupos (segue grupo_ordem do backend) — base p/ reordenar
  GORDER=[...new Set(DATA.itens.map(i=>i.grupo||'—'))];
  const tb=document.getElementById('tb');
  if(!rows.length){ tb.innerHTML='<tr><td colspan="12" class="empty">Nenhum item.</td></tr>'; updateCollapseBtn(); return; }
  let html='';
  if(flat){
    html=rows.slice().sort(cmpItems).map(rowHtml).join('');
  } else {
    const map=new Map();
    for(const i of rows){ const g=i.grupo||'—'; if(!map.has(g))map.set(g,[]); map.get(g).push(i); }
    GORDER.forEach((g,idx)=>{
      if(!map.has(g))return;
      const items=map.get(g).slice().sort(cmpItems);
      html+=groupHeaderHtml(g,items,idx);
      if(!COLLAPSED.has(g)) html+=items.map(rowHtml).join('');
    });
  }
  tb.innerHTML=html;
  updateCollapseBtn(); updateSortArrows(); fitRadarHeight();
}
function rowHtml(i){
  const st=i.status||'Não Iniciado';
  const lvl=alertLevel(i);
  const chipIni=lvl==='atrasado'?`<span class="tag-al atras">atrasado</span>`:lvl==='proximo'?`<span class="tag-al prox">iniciar</span>`:'';
  const chipFim=lvl==='critico'?`<span class="tag-al crit">crítico</span>`:lvl==='finalizado'?`<span class="tag-al fin">✓ concluído</span>`:'';
  return `<tr class="item" onclick="openModal(${i.ordem})">
    <td><div class="svc">${esc(i.nome)} ${tipoChip(i.tipo)}</div><div class="svc-sub">${esc(i.forma_contratacao||'')}</div></td>
    <td><span class="curva c-${i.curva||'C'}">${esc(i.curva||'—')}</span></td>
    <td>${i.responsavel?esc(i.responsavel):`<button class="resp-miss" onclick="event.stopPropagation();openModal(${i.ordem})">definir</button>`}</td>
    <td class="money">${BRL(i.verba)}${i.curado_verba?' <span class="material-icons" title="verba curada" style="font-size:13px;color:var(--ok);vertical-align:-2px">verified</span>':''}</td>
    <td>${i.quantitativo!=null?`<div class="qcell" title="${esc(QNUM(i.quantitativo)+' '+(i.quantitativo_unidade||''))}"><b>${QNUM(i.quantitativo)}</b> <span class="muted">${esc(i.quantitativo_unidade||'')}</span>${i.curado_quant?' <span class="material-icons" title="quantitativo curado" style="font-size:13px;color:var(--ok);vertical-align:-2px">verified</span>':''}</div>`:'<span class="muted">—</span>'}</td>
    <td class="date">${D(i.data_necessaria)}${i.curado_data?' <span class="material-icons" title="data curada" style="font-size:12px;color:var(--ok);vertical-align:-2px">verified</span>':''}</td>
    <td>${pctChip(i.cronograma_pct)}</td>
    <td class="date">${D(i.inicio_cotacao)}${chipIni}</td>
    <td class="date">${D(i.fim_cotacao)}${chipFim}</td>
    <td>${statusSelect(i)}</td>
    <td>${i.fornecedor?`<span class="mapa-on">● cotando</span>`:'<span class="muted">—</span>'}</td>
    <td onclick="event.stopPropagation()"><button class="eye" onclick="openModal(${i.ordem})"><span class="material-icons" style="font-size:17px;line-height:28px">visibility</span></button></td>
  </tr>`;
}
const TIPO_AB={'Material':['MAT','tp-mat'],'Mão de obra':['MO','tp-mo'],'Empreitada':['EMP','tp-emp'],'Material + MO':['M+MO','tp-mat-mo'],'Locação':['LOC','tp-loc']};
function tipoChip(t){ if(!t) return '<span class="tp-chip tp-none" title="a classificar">?</span>'; const a=TIPO_AB[t]||['?','tp-none']; return `<span class="tp-chip ${a[1]}" title="${esc(t)}">${a[0]}</span>`; }
function pctChip(p){ if(p==null) return '<span class="muted">—</span>'; const v=Math.round(p); const c=v>=100?'var(--ok)':v>0?'var(--cot)':'var(--neu)';
  return `<span class="pctw" title="conclusão da tarefa no cronograma (ao vivo)"><span class="pctbar"><span class="pctfill" style="width:${Math.min(v,100)}%;background:${c}"></span></span><span class="pctn">${v}%</span></span>`; }
function statusSelect(i){
  const st=i.status||'Não Iniciado';
  // estático: status é alterado pelo botão Editar dentro do item (com permissão + histórico)
  return `<span class="stsel ${STK[st]}" style="cursor:pointer" title="abra o item e clique em Editar para alterar">${esc(st)}</span>`;
}

async function saveField(ordem,campo,valor){
  try{
    const r=await fetch('actions/item_update.php',{method:'POST',headers:{'Content-Type':'application/json'},
      body:JSON.stringify({ordem,campos:{[campo]:valor},me:EU&&EU.bitrix_id})});
    const d=await r.json();
    if(d.error){toast('Erro: '+d.error);return false;}
    Object.assign(byOrdem(ordem),d.item); // reflete na memória
    fill('fstatus',[...new Set(DATA.itens.map(i=>i.status||'Não Iniciado'))]);
    fill('fresp',[...new Set(DATA.itens.map(i=>i.responsavel).filter(Boolean))]);
    render(); renderMatriz(); // reflete cor/valores nas duas visões na hora
    return true;
  }catch(e){toast('Falha ao salvar');return false;}
}

/* ---------- modal ---------- */
let EDITC=false, EDITO=false, EDITQ=false, EDITD=false, EDITR=false; // modos "Editar" (cronograma/orçamento/quantitativo/dicionário/resumo)
let IS_ADMIN=false;                       // fail-closed; vira true só quando getCurrentUser confirma perm_admin
let CAN_EDIT=false;                       // editor geral da obra (status/fornecedor/observação)
let CAN_CRONO=false, CAN_ORC=false, CAN_QUANT=false, CAN_DIC=false; // permissões específicas (vínculos + dicionário)
let EU=null;                             // usuário logado + permissões efetivas
function openModal(o){CUR=byOrdem(o);TAB='Resumo';EDITC=EDITO=EDITQ=EDITD=EDITR=false;drawModal();document.getElementById('ov').classList.add('open');}
function closeModal(){document.getElementById('ov').classList.remove('open');render();renderMatriz();}
function setTab(t){TAB=t;EDITC=EDITO=EDITQ=EDITD=EDITR=false;drawModal();}
function drawModal(){
  const i=CUR;if(!i)return;
  const tabs=['Resumo','Cronograma','Orçamento','Quantitativo','Dicionário','Mapa de cotação','Histórico'];
  document.getElementById('modal').innerHTML=`
    <div class="mhead">
      <button class="mclose" onclick="closeModal()">×</button>
      <div class="crumb">${esc(i.grupo||'')} · Curva ${esc(i.curva||'—')}</div>
      <div class="mt">${esc(i.nome)}</div>
      <div class="meta">
        <span><span class="material-icons">person</span>${esc(i.responsavel||'sem responsável')}</span>
        <span><span class="material-icons">straighten</span>${esc((i.curado_quant&&i.quantitativo_unidade)?i.quantitativo_unidade:(i.unidade||'—'))}</span>
        <span><span class="material-icons">event</span>obra: ${D(i.data_necessaria)}</span>
        <span><span class="material-icons">schedule</span>lead: ${i.lead_efetivo?i.lead_efetivo+' d':'—'}</span>
      </div>
    </div>
    <div class="tabs">${tabs.map(t=>`<button class="tab ${t===TAB?'active':''}" onclick="setTab('${t}')">${t}</button>`).join('')}</div>
    <div class="tabbody">${tabBody(i)}</div>`;
  postDraw(i);
}
function postDraw(i){
  if(TAB==='Orçamento'){ orcShowCurrent(i); orcLoadLastChange(i.ordem); if(EDITO) orcRenderFonte(); }
  if(TAB==='Cronograma'){ cronoLoadLastChange(i.ordem); if(EDITC) cronoInit(); }
  if(TAB==='Quantitativo'){ quantShowCurrent(i); if(EDITQ) qntRenderFonte(); }
  if(TAB==='Histórico'){ loadHist(i.ordem); }
}
function tabBody(i){
  if(TAB==='Resumo') return resumoTab(i);
  if(TAB==='Cronograma') return cronoTab(i);
  if(TAB==='Quantitativo') return quantTab(i);
  if(TAB==='Orçamento') return orcTab(i);
  if(TAB==='Dicionário') return dicTab(i);
  if(TAB==='Histórico') return histTab(i);
  // Mapa de cotação
  const itens=(i.variaveis_cotar||'').split('|').map(s=>s.trim()).filter(Boolean);
  return `
    <p>Template de equalização gerado a partir do dicionário — pontos a conferir em cada proposta:</p>
    ${itens.length?itens.map(t=>`<label style="display:flex;gap:9px;align-items:flex-start;padding:7px 0;border-bottom:1px solid #f1f3f2;font-size:13px"><input type="checkbox" style="margin-top:3px"> <span>${esc(t)}</span></label>`).join(''):'<div class="muted">Sem template no dicionário.</div>'}
    <div class="note">⚡ Próximo passo do sistema: jogar o PDF da proposta aqui — a IA lê, compara com o dicionário e preenche estes itens, sinalizando cláusulas divergentes do padrão Caprem.</div>`;
}

/* ===== Cronograma — vínculo (read-only) + Editar vínculo → árvore ===== */
let CRONO_NODES=[], CRONO_SEARCH=[], CRONO_PENDING=null;
function cronoTab(i){
  const path = i.marco_path||[];
  const crumb = path.length
    ? path.map((p,ix)=>ix===path.length-1?`<b>${esc(p)}</b>`:`<span class="muted">${esc(p)}</span>`).join(' <span style="opacity:.4">›</span> ')
    : (i.marco_casado?`<b>${esc(i.marco_casado)}</b>`:'');
  let h=`
    <div class="box"><div class="bl">Tarefa-âncora atual ${i.curado_data?'(curada ✓)':''}</div>
      ${i.marco_casado
        ? `<div class="bv" style="line-height:1.7">${crumb}</div>
           <div class="muted" style="font-size:12.5px;margin-top:3px">→ necessário em obra: <b style="color:var(--txt)">${D(i.data_necessaria)}</b> · ${esc(i.confianca||'')}</div>`
        : `<div class="bv muted">Sem tarefa casada automaticamente — clique em Editar vínculo e selecione a linha do cronograma.</div>`}
      <div id="cronoLastChange" style="font-size:11.5px;margin-top:6px;color:var(--muted)"></div>
    </div>`;
  if(!EDITC){
    h+=`<div style="display:flex;gap:8px;margin-top:6px">`;
    if(CAN_CRONO){
      h+=`<button class="btn-prim" onclick="cronoEditar()"><span class="material-icons" style="font-size:16px">link</span> Editar vínculo</button>`;
      if(i.curado_data) h+=`<button class="btn-ghost" onclick="cronoLimpar()">↺ Voltar ao automático</button>`;
    } else h+=`<span class="muted" style="font-size:12.5px">Você não tem permissão para editar o vínculo de cronograma.</span>`;
    h+=`</div>`;
  } else {
    h+=`
    <div id="cronoPending" class="pendbar"></div>
    <div class="fld" style="margin-top:8px"><label>Buscar tarefa por nome</label>
      <div class="search" style="border:1px solid var(--line)"><span class="material-icons" style="color:var(--muted)">search</span>
        <input id="cronoQ" placeholder="ex.: sondagem, pilar 5º pav, contenção…" oninput="cronoBuscar()"></div></div>
    <div id="cronoSearch"></div>
    <div class="fld" style="margin-bottom:4px"><label>Ou navegue a árvore (WBS)</label></div>
    <div class="tree" id="cronoTree">Carregando…</div>
    <div style="margin-top:10px;display:flex;gap:8px">
      <button class="btn-prim" id="cronoSave" onclick="cronoSalvar()" disabled>Salvar vínculo</button>
      <button class="btn-ghost" onclick="cronoCancelar()">Cancelar</button>
    </div>`;
  }
  h+=`<div class="note">A data da tarefa fixada vira a "necessária em obra" e recalcula o gatilho. Pode ancorar num nó-resumo (ex.: "ESTRUTURA PILAR") ou numa tarefa de pavimento.</div>`;
  return h;
}
function cronoEditar(){ EDITC=true; CRONO_PENDING=null; drawModal(); }
function cronoCancelar(){ EDITC=false; CRONO_PENDING=null; drawModal(); }
async function cronoInit(){
  const box=document.getElementById('cronoTree'); if(!box)return;
  const d=await (await fetch('actions/crono_tree.php')).json();
  CRONO_NODES=(d.nos||[]).map(n=>({...n,expanded:false}));
  cronoRenderTree();
}
function cronoRenderTree(){
  const box=document.getElementById('cronoTree'); if(!box)return;
  box.innerHTML=CRONO_NODES.map((n,ix)=>{
    const ind=(n.nivel-1)*16;
    const car=n.expansivel?`<span class="caret material-icons" onclick="cronoExpand(${ix})">${n.expanded?'expand_more':'chevron_right'}</span>`:'<span class="caret-sp"></span>';
    const tag=n.is_milestone?'<span class="mk-tag">marco</span>':'';
    const sel=(CRONO_PENDING&&CRONO_PENDING.outline===n.outline);
    return `<div class="tnode${sel?' tsel':''}" style="padding-left:${ind}px">
      ${car}
      <span class="pin material-icons${sel?' pinon':''}" onclick="cronoSelecionar('${esc(n.outline)}')" title="${sel?'selecionado':'selecionar'}">${sel?'check_circle':'radio_button_unchecked'}</span>
      <span class="tcode">${esc(n.outline)}</span>
      <span class="tname" onclick="cronoSelecionar('${esc(n.outline)}')" title="selecionar como tarefa-âncora">${esc(n.nome)} ${tag}${sel?' <span class="selflag">✓ selecionado</span>':''}</span>
      <span class="tdate">${D(n.start)}</span>
    </div>`;
  }).join('');
}
async function cronoExpand(ix){
  const n=CRONO_NODES[ix]; if(!n)return;
  if(n.expanded){ // colapsa: remove descendentes
    let j=ix+1; while(j<CRONO_NODES.length && CRONO_NODES[j].nivel>n.nivel) j++;
    CRONO_NODES.splice(ix+1,j-(ix+1)); n.expanded=false; cronoRenderTree(); return;
  }
  const d=await (await fetch('actions/crono_tree.php?children_of='+encodeURIComponent(n.outline))).json();
  const filhos=(d.nos||[]).map(x=>({...x,expanded:false}));
  CRONO_NODES.splice(ix+1,0,...filhos); n.expanded=true; cronoRenderTree();
}
function cronoSelecionar(outline){
  const n=CRONO_NODES.find(x=>x.outline===outline)||CRONO_SEARCH.find(x=>x.outline===outline);
  if(!n)return;
  if(!n.start){toast('Essa tarefa não tem data de início');return;}
  CRONO_PENDING=n; cronoRenderTree();
  const pb=document.getElementById('cronoPending');
  if(pb) pb.innerHTML=`<span class="material-icons" style="font-size:15px;color:var(--verde)">push_pin</span> Selecionado: <b>${esc(n.nome)}</b> → ${D(n.start)}`;
  const sv=document.getElementById('cronoSave'); if(sv) sv.disabled=false;
}
async function cronoSalvar(){
  if(!CRONO_PENDING){toast('Selecione uma tarefa');return;}
  const n=CRONO_PENDING; EDITC=false; CRONO_PENDING=null;
  await saveAndReload({crono_marco_override:n.nome, data_necessaria_override:n.start});
  toast('Vínculo salvo: '+D(n.start));
}
async function cronoBuscar(){
  const q=document.getElementById('cronoQ').value.trim();
  const box=document.getElementById('cronoSearch');
  if(q.length<2){box.innerHTML='';return;}
  box.innerHTML='<div class="muted" style="font-size:12px;padding:4px">Buscando…</div>';
  try{
    const d=await (await fetch('actions/crono_search.php?q='+encodeURIComponent(q))).json();
    if(d.error){box.innerHTML='<div class="muted" style="font-size:12px;padding:4px;color:var(--pend)">Erro na busca: '+esc(d.error)+'</div>';return;}
    CRONO_SEARCH=(d.tarefas||[]).map(t=>({outline:t.outline_number||t.wbs,nome:t.nome,start:t.start,wbs:t.wbs}));
    if(!CRONO_SEARCH.length){box.innerHTML='<div class="muted" style="font-size:12px;padding:4px">Nada encontrado.</div>';return;}
    box.innerHTML='<div class="srbox">'+CRONO_SEARCH.map(t=>`
      <div class="pickrow" onclick="cronoSelecionar('${esc(t.outline)}')">
        <span class="material-icons" style="font-size:16px;color:var(--verde)">radio_button_checked</span>
        <div><div>${esc(t.nome)}</div><small class="muted">WBS ${esc(t.wbs||'—')} · ${D(t.start)}</small></div>
      </div>`).join('')+'</div>';
  }catch(e){box.innerHTML='<div class="muted" style="font-size:12px;padding:4px">Falha na busca.</div>';}
}
async function cronoLimpar(){ EDITC=false; await saveAndReload({crono_marco_override:'', data_necessaria_override:''}); toast('Voltou ao automático'); }

/* ===== Quantitativo — vínculo (read-only) + Editar → árvore (soma qtde) / manual ===== */
let QNT_SEL=new Set(), QNT_NODES=[];
let QNTFONTE='manual', QCOMP_DATA=null, QCOMP_AREA=0, QCOMP_SEL=[];   // quantitativo por composição (cesta)
const QNUM=n=>n!=null?Number(n).toLocaleString('pt-BR',{maximumFractionDigits:2}):'—';
function quantTab(i){
  const _qf = i.quantitativo_fonte==='composicao'?'por composição de insumos':(i.quantitativo_fonte==='orcamento'?'do orçamento (linhas)':'manual');
  const atual = i.quantitativo!=null
    ? `<b style="font-size:16px">${QNUM(i.quantitativo)} ${esc(i.quantitativo_unidade||'')}</b> <span class="muted" style="font-size:12px">— ${_qf}</span>`
    : '<span class="muted">Sem quantitativo definido.</span>';
  const editBar = !EDITQ ? `<div style="display:flex;gap:8px;margin:0 0 10px">${
    CAN_QUANT ? `<button class="btn-prim" onclick="quantEditar()"><span class="material-icons" style="font-size:16px">link</span> Editar quantitativo</button>`+(i.curado_quant?`<button class="btn-ghost" onclick="qntLimpar()">↺ Limpar</button>`:'')
              : `<span class="muted" style="font-size:12.5px"><span class="material-icons" style="font-size:15px;vertical-align:-3px">lock</span> Você não tem permissão para editar o quantitativo.</span>`
  }</div>` : '';
  let h=`
    ${editBar}
    <div class="box"><div class="bl">Quantitativo atual ${i.curado_quant?'(curado ✓)':''}${EDITQ?'':' <span class="muted" style="text-transform:none;letter-spacing:0;font-weight:400">— somente leitura</span>'}</div><div class="bv" id="qntSel">${atual}</div><div id="qntTotal" style="margin-top:6px;font-weight:700"></div></div>`;
  if(EDITQ){
    h+=`
    <div class="fld" style="margin-top:8px"><label>Fonte do quantitativo</label>
      <select id="qntFonte" onchange="qntSetFonte(this.value)">
        <option value="manual" ${QNTFONTE==='manual'?'selected':''}>Manual</option>
        <option value="analitico" ${QNTFONTE==='analitico'?'selected':''}>Orçamento analítico (somar quantidades das linhas)</option>
        <option value="composicao" ${QNTFONTE==='composicao'?'selected':''}>Composição (cesta de insumos — ex.: contar blocos)</option>
      </select></div>
    <div id="qntFonteBox"></div>
    <div style="margin-top:8px"><button class="btn-ghost" onclick="quantCancelar()">Cancelar</button></div>`;
  }
  h+=`<div class="note">O quantitativo vira aprendizado por tipo de serviço (replicável p/ obra nova) sem alterar obras passadas. Cuidado com unidades diferentes ao somar linhas.</div>`;
  return h;
}
function quantEditar(){ EDITQ=true; VERBA_USOS=null; QNT_NODES=[];
  QNTFONTE=(CUR.quantitativo_fonte==='composicao'?'composicao':(CUR.quantitativo_fonte==='orcamento'?'analitico':'manual'));
  // pré-carrega a seleção atual — inclusive quando o quantitativo foi DERIVADO da verba (refs/cesta moram na verba):
  let refs=CUR.quantitativo_refs||[];
  if(!refs.length && CUR.quantitativo_fonte==='orcamento') refs=CUR.orcamento_refs||[];               // veio da verba analítica
  QNT_SEL=new Set(refs.map(Number));
  let qcs=CUR.quant_comp_sel||[];
  if(!qcs.length && CUR.quantitativo_fonte==='composicao') qcs=(CUR.composicao_sel||[]).filter(s=>s.q); // veio da verba composição (insumos "define quantitativo")
  QCOMP_SEL=qcs.map(s=>({...s})); QCOMP_DATA=null;
  drawModal(); }
function quantCancelar(){ EDITQ=false; drawModal(); }
function qntSetFonte(v){ QNTFONTE=v; qntRenderFonte(); }
function qntRenderFonte(){
  const box=document.getElementById('qntFonteBox'); if(!box)return;
  if(QNTFONTE==='manual'){
    box.innerHTML=`<div class="grid2">
      <div class="fld"><label>Quantitativo manual</label><input id="qntManV" type="number" step="any" placeholder="valor" value="${CUR.quantitativo!=null?CUR.quantitativo:''}"></div>
      <div class="fld"><label>Unidade</label><input id="qntManU" placeholder="m², m³, kg, un…" value="${esc(CUR.quantitativo_unidade||'')}"></div></div>
      <div style="margin-top:6px"><button class="btn-prim" onclick="qntManualSalvar()">Salvar quantitativo manual</button></div>`;
  } else if(QNTFONTE==='analitico'){
    box.innerHTML=`<div class="fld"><label>Buscar linha do orçamento por nome (soma as quantidades)</label>
      <div class="search" style="border:1px solid var(--line)"><span class="material-icons" style="color:var(--muted)">search</span>
        <input id="qntQ" placeholder="ex.: bloco, contrapiso, concreto laje…" oninput="qntBuscar()"></div></div>
      <div id="qntSearch"></div>
      <div style="margin:10px 0 0"><button class="btn-ghost" id="qntTreeBtn" onclick="qntTreeToggle()" style="padding:6px 11px;font-size:12.5px"><span class="material-icons" style="font-size:15px;vertical-align:-3px;color:var(--verde)">account_tree</span> Navegar a árvore <span class="material-icons mtcaret" style="font-size:16px;vertical-align:-3px">expand_more</span></button></div>
      <div id="qntTreeWrap" style="display:none;margin-top:8px"><div class="tree" id="qntTree">Carregando…</div></div>
      <div style="margin-top:12px"><button class="btn-prim" onclick="qntSalvar()">Salvar do orçamento</button></div>`;
  } else {
    box.innerHTML=`<div class="fld"><label>Busque composições e marque os insumos — soma área × consumo (ex.: bloco 14 + bloco 19 = total de blocos)</label>
      <div class="search" style="border:1px solid var(--line)"><span class="material-icons" style="color:var(--muted)">search</span>
        <input id="qcompQ" placeholder="ex.: alvenaria bloco, concreto…" oninput="qcompBuscar()"></div></div>
      <div id="qcompSearch"></div><div id="qcompDetail"></div><div id="qcompBasket" style="margin-top:8px"></div><div id="qcompTotals"></div>`;
    qcompRenderBasket();
  }
}
async function qcompBuscar(){
  const q=document.getElementById('qcompQ').value.trim();
  const box=document.getElementById('qcompSearch'); if(!box)return;
  if(q.length<2){box.innerHTML='';return;}
  box.innerHTML='<div class="muted" style="font-size:12px;padding:4px">Buscando…</div>';
  const d=await (await fetch('actions/composicao.php?q='+encodeURIComponent(q))).json();
  const list=d.composicoes||[];
  if(!list.length){box.innerHTML='<div class="muted" style="font-size:12px;padding:4px">Nada encontrado.</div>';return;}
  box.innerHTML='<div class="srbox">'+list.map(c=>`<div class="pickrow" onclick="qcompEscolher(${c.id})">
    <span class="material-icons" style="font-size:16px;color:var(--verde)">playlist_add</span>
    <div><div>${esc(c.descricao)}</div><small class="muted">${QNUM(c.qtde_total)} ${esc(c.unidade||'')}</small></div></div>`).join('')+'</div>';
}
async function qcompEscolher(id){
  QCOMP_DATA=await (await fetch('actions/composicao.php?id='+id)).json();
  QCOMP_AREA=QCOMP_DATA.qtde_total||0;
  document.getElementById('qcompSearch').innerHTML='';
  qcompRenderDetail();
}
function qcompRenderDetail(){
  const box=document.getElementById('qcompDetail'); if(!box||!QCOMP_DATA)return;
  const c=QCOMP_DATA;
  box.innerHTML=`<div class="box"><div class="bl">${esc(c.descricao)}</div><div class="bv muted" style="font-size:12px">total ${QNUM(c.qtde_total)} ${esc(c.unidade||'')}</div></div>
    <div class="fld"><label>Área/quantidade desta composição (padrão = total)</label><input type="number" step="any" value="${QCOMP_AREA}" oninput="QCOMP_AREA=parseFloat(this.value)||0"></div>
    <div class="tree" style="max-height:200px">${c.insumos.map((in_,ix)=>{const on=QCOMP_SEL.some(s=>s.cid===c.id&&s.idx===ix);
      return `<div class="tnode"><span class="material-icons chk" onclick="qcompToggleInsumo(${ix})" style="color:${on?'var(--ok)':'var(--muted)'}">${on?'check_box':'check_box_outline_blank'}</span>
      <span class="badge-tp ${in_.tipo}">${in_.tipo==='mo'?'MO':'MAT'}</span>
      <span class="tname">${esc(in_.descricao)}</span><span class="tval">${QNUM(in_.coef)} ${esc(in_.unidade||'')}/un</span></div>`;}).join('')}</div>
    <div class="muted" style="font-size:11.5px;margin-top:4px">Marque o(s) insumo(s) cuja contagem é o quantitativo (ex.: o bloco). Pode abrir outra composição e marcar mais.</div>`;
}
function qcompToggleInsumo(ix){
  const c=QCOMP_DATA; const in_=c&&c.insumos[ix]; if(!in_)return;
  const i=QCOMP_SEL.findIndex(s=>s.cid===c.id&&s.idx===ix);
  if(i>=0) QCOMP_SEL.splice(i,1);
  else QCOMP_SEL.push({cid:c.id, idx:ix, area:QCOMP_AREA||c.qtde_total||0, desc:in_.descricao, tipo:in_.tipo, unidade:in_.unidade, coef:+in_.coef, compdesc:c.descricao});
  qcompRenderDetail(); qcompRenderBasket();
}
function qcompRenderBasket(){
  const box=document.getElementById('qcompBasket'), tot=document.getElementById('qcompTotals'); if(!box)return;
  if(!QCOMP_SEL.length){ box.innerHTML='<div class="muted" style="font-size:12px;padding:6px 2px">Nenhum insumo selecionado — marque insumos das composições acima.</div>'; if(tot)tot.innerHTML=''; return; }
  let qval=0, qun='';
  box.innerHTML='<div class="bl" style="margin-bottom:4px">Quantitativo composto destes insumos</div>'+QCOMP_SEL.map((s,k)=>{
    const qq=(s.area||0)*(s.coef||0); qval+=qq; if(!qun)qun=s.unidade;
    return `<div class="pickrow" style="gap:8px;align-items:center">
      <span class="badge-tp ${s.tipo}">${s.tipo==='mo'?'MO':'MAT'}</span>
      <div style="flex:1;min-width:0"><div style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap">${esc(s.desc)}</div><small class="muted">${esc((s.compdesc||'').slice(0,38))} · ${QNUM(s.coef)} ${esc(s.unidade||'')}/un</small></div>
      <input type="number" step="any" style="width:84px;border:1px solid var(--line);border-radius:7px;padding:4px 6px" value="${s.area}" oninput="QCOMP_SEL[${k}].area=parseFloat(this.value)||0;qcompRenderBasket()" title="área">
      <span class="money" style="min-width:96px;text-align:right">${QNUM(qq)} ${esc(s.unidade||'')}</span>
      <span class="material-icons" style="cursor:pointer;color:var(--pend);font-size:18px" onclick="QCOMP_SEL.splice(${k},1);qcompRenderDetail();qcompRenderBasket()" title="remover">close</span>
    </div>`;
  }).join('');
  if(tot) tot.innerHTML=`<div class="box" style="margin-top:8px"><div class="bv"><b>Quantitativo total:</b> ${QNUM(qval)} ${esc(qun)}</div></div>
    <div style="margin-top:8px"><button class="btn-prim" onclick="qcompSalvar()">Salvar quantitativo por composição</button></div>`;
}
async function qcompSalvar(){
  if(!QCOMP_SEL.length){toast('Marque ao menos um insumo');return;}
  EDITQ=false;
  await saveAndReload({quant_comp_sel: QCOMP_SEL.map(s=>({cid:s.cid, idx:s.idx, area:s.area}))});
  toast('Quantitativo por composição salvo ('+QCOMP_SEL.length+' insumo(s))');
}
async function quantShowCurrent(i){
  const el=document.getElementById('qntSel'), tot=document.getElementById('qntTotal');
  // MEMORIAL de conferência — descobre a origem do quantitativo p/ mostrar o cálculo e os itens:
  // 1) cesta própria do quantitativo (fonte composição direta)
  let cesta=(i.quant_comp_sel||[]), origem='composição de insumos — área × consumo';
  // 2) senão, a verba por composição já definiu o quantitativo (insumos marcados "define quantitativo")
  if(!cesta.length){
    const qs=(i.composicao_sel||[]).filter(s=>s.q);
    const qsum=qs.reduce((a,s)=>a+(s.area||0)*(s.coef||0),0);
    if(qs.length && i.quantitativo!=null && Math.abs(qsum-i.quantitativo)<Math.max(1,Math.abs(i.quantitativo)*0.005)){
      cesta=qs; origem='insumos da composição da verba marcados "define quantitativo" — área × consumo';
    }
  }
  if(cesta.length){
    const locMap=await loadCompLocais(cesta);
    const el2=document.getElementById('qntSel'), tot2=document.getElementById('qntTotal'); if(!el2) return;
    let qval=0;
    el2.innerHTML=`<div style="margin-bottom:6px"><b style="font-size:16px">${QNUM(i.quantitativo)} ${esc(i.quantitativo_unidade||'')}</b> <span class="muted" style="font-size:12px">— ${origem}:</span></div>`+
      cesta.map(s=>{const qq=(s.area||0)*(s.coef||0); qval+=qq; const ld=insumoLocaisDet(s, locMap);
        return `<div class="pickrow" style="align-items:flex-start"><span class="badge-tp ${s.tipo}">${s.tipo==='mo'?'MO':'MAT'}</span>
          <div style="flex:1;min-width:0"><div>${esc(s.desc)}</div>
            <small class="muted">${QNUM(s.area)} × ${QNUM(s.coef)} = <b>${QNUM(qq)} ${esc(s.unidade||'')}</b>${s.compdesc?' · '+esc(s.compdesc.slice(0,40)):''}</small>${locDet(ld.det, ld.todos)}</div></div>`;}).join('');
    if(tot2) tot2.textContent='Soma: '+QNUM(qval)+' '+(i.quantitativo_unidade||'');
    return;
  }
  // 3) analítico — linhas do orçamento selecionadas (caminho + qtde)
  QNT_SEL=new Set((i.quantitativo_refs||[]).map(Number));
  if(QNT_SEL.size) await qntRenderSel();
}
async function qntLoadTree(){
  const box=document.getElementById('qntTree'); if(!box)return;
  const d=await (await fetch('actions/orcamento.php')).json();
  QNT_NODES=(d.linhas||[]).map(n=>({...n,expanded:false}));
  qntRenderTree();
}
function qntTreeToggle(){ const w=document.getElementById('qntTreeWrap'), b=document.getElementById('qntTreeBtn'); if(!w)return;
  const open=w.style.display==='none'; w.style.display=open?'block':'none';
  const ic=b&&b.querySelector('.mtcaret'); if(ic) ic.textContent=open?'expand_less':'expand_more';
  if(open && !QNT_NODES.length) qntLoadTree();
}
function qntRenderTree(){
  const box=document.getElementById('qntTree'); if(!box)return;
  box.innerHTML=QNT_NODES.map((n,ix)=>{
    const ind=(n.depth-1)*16;
    const car=n.expansivel?`<span class="caret material-icons" onclick="qntExpand(${ix})">${n.expanded?'expand_more':'chevron_right'}</span>`:'<span class="caret-sp"></span>';
    const chk=n.folha?`<span class="material-icons chk" onclick="qntToggleSel(${n.id})" style="color:${QNT_SEL.has(n.id)?'var(--ok)':'var(--muted)'}">${QNT_SEL.has(n.id)?'check_box':'check_box_outline_blank'}</span>`:'<span class="caret-sp"></span>';
    const q=n.folha&&n.qtde!=null?`<span class="tval">${QNUM(n.qtde)} ${esc(n.unidade||'')}</span>`:`<span class="tval">${n.valor!=null?BRL(n.valor):''}</span>`;
    return `<div class="tnode ${n.folha?'':'tparent'}" style="padding-left:${ind}px">${car}${chk}
      <span class="tname">${esc(n.descricao)}</span>${q}</div>`;
  }).join('');
}
async function qntExpand(ix){
  const n=QNT_NODES[ix]; if(!n)return;
  if(n.expanded){ let j=ix+1; while(j<QNT_NODES.length && QNT_NODES[j].depth>n.depth) j++;
    QNT_NODES.splice(ix+1,j-(ix+1)); n.expanded=false; qntRenderTree(); return; }
  const d=await (await fetch('actions/orcamento.php?children_of='+encodeURIComponent(n.codigo))).json();
  QNT_NODES.splice(ix+1,0,...(d.linhas||[]).map(x=>({...x,expanded:false}))); n.expanded=true; qntRenderTree();
}
function qntToggleSel(id){
  const tr=document.getElementById('qntTree'), sr=document.querySelector('#qntSearch .srbox');
  const ts=tr?tr.scrollTop:0, ss=sr?sr.scrollTop:0;
  QNT_SEL.has(id)?QNT_SEL.delete(id):QNT_SEL.add(id);
  qntRenderTree(); qntRenderSel(); qntRenderSearch();
  const tr2=document.getElementById('qntTree'); if(tr2)tr2.scrollTop=ts;        // mantém a posição da lista
  const sr2=document.querySelector('#qntSearch .srbox'); if(sr2)sr2.scrollTop=ss;
}
async function qntRenderSel(){
  const el=document.getElementById('qntSel'); if(!el)return;
  if(!QNT_SEL.size){ const t=document.getElementById('qntTotal'); if(t)t.textContent=''; return; }
  const d=await (await fetch('actions/orcamento.php?ids='+[...QNT_SEL].join(','))).json();
  const byU={}; let html='';
  d.linhas.forEach(l=>{ byU[l.unidade]=(byU[l.unidade]||0)+(l.qtde||0);
    html+=`<div class="pickrow"><span class="material-icons" style="font-size:16px;color:var(--ok)${EDITQ?';cursor:pointer':''}" ${EDITQ?`onclick="qntToggleSel(${l.id})" title="remover"`:''}>${EDITQ?'check_box':'check_circle'}</span>
      <div><div>${esc(l.descricao)}</div><small class="muted">${esc(l.path_str||'')} · ${QNUM(l.qtde)} ${esc(l.unidade||'')}</small></div></div>`; });
  el.innerHTML=html||'<span class="muted">—</span>';
  const tot=document.getElementById('qntTotal');
  if(tot) tot.textContent='Soma: '+Object.entries(byU).map(([u,v])=>`${QNUM(v)} ${u||''}`).join(' · ');
}
let QNT_LAST=[];
async function qntBuscar(){
  const q=document.getElementById('qntQ').value.trim();
  const box=document.getElementById('qntSearch'); if(!box)return;
  if(q.length<2){QNT_LAST=[];box.innerHTML='';return;}
  box.innerHTML='<div class="muted" style="font-size:12px;padding:4px">Buscando…</div>';
  const d=await (await fetch('actions/orcamento.php?q='+encodeURIComponent(q))).json();
  QNT_LAST=d.linhas||[];
  if(!QNT_LAST.length){box.innerHTML='<div class="muted" style="font-size:12px;padding:4px">Nada encontrado.</div>';return;}
  qntRenderSearch();
}
function qntRenderSearch(){
  const box=document.getElementById('qntSearch'); if(!box)return;
  if(!QNT_LAST.length){box.innerHTML='';return;}
  box.innerHTML='<div class="srbox">'+QNT_LAST.map(l=>{const on=QNT_SEL.has(l.id);return `<div class="pickrow" onclick="qntToggleSel(${l.id})">
    <span class="material-icons" style="font-size:16px;color:${on?'var(--ok)':'var(--muted)'}">${on?'check_box':'check_box_outline_blank'}</span>
    <div><div>${esc(l.descricao)}</div><small class="muted">${esc(l.path_str||'')} · ${QNUM(l.qtde)} ${esc(l.unidade||'')}</small></div></div>`;}).join('')+'</div>';
}
async function qntSalvar(){ EDITQ=false; await saveAndReload({quant_refs:[...QNT_SEL]}); toast('Quantitativo do orçamento salvo'); }
async function qntManualSalvar(){
  const v=document.getElementById('qntManV').value, u=document.getElementById('qntManU').value;
  if(v===''){toast('Informe o valor');return;}
  EDITQ=false; await saveAndReload({quantitativo_valor:v, quantitativo_unidade:u, quantitativo_fonte:'manual'});
  toast('Quantitativo manual salvo');
}
async function qntLimpar(){ EDITQ=false; QNT_SEL.clear(); await saveAndReload({quantitativo_valor:'', quantitativo_unidade:'', quantitativo_fonte:'', quant_refs:[]}); toast('Quantitativo limpo'); }

/* ===== Orçamento — árvore navegável (Grupo → Disciplina → Elemento → item) ===== */
let ORC_SEL=new Set(), ORC_NODES=[];
function orcTab(i){
  const MET={analitico:'linhas do orçamento (analítico)', composicao:'composição de insumos', manual:'manual'};
  const metodo = MET[i.verba_metodo] || 'estimativa preliminar (a curar)';
  const editBar = !EDITO ? `<div style="display:flex;gap:8px;flex-wrap:wrap;margin:6px 0 10px">${
    CAN_ORC ? `<button class="btn-prim" onclick="orcEditar()"><span class="material-icons" style="font-size:16px">link</span> Editar vínculo de verba</button>`+(i.verba_metodo?`<button class="btn-ghost" onclick="orcLimpar()">↺ Limpar</button>`:'')+(i.verba_metodo==='analitico'?`<button class="btn-ghost" onclick="separarMO()" title="Tira a mão de obra embutida nas linhas inteiras, deixando o item só com o material — a MO fica livre pro item de Mão de Obra"><span class="material-icons" style="font-size:15px;vertical-align:-3px">content_cut</span> Separar material × MO</button>`:'')
            : `<span class="muted" style="font-size:12.5px"><span class="material-icons" style="font-size:15px;vertical-align:-3px">lock</span> Você não tem permissão para editar a verba.</span>`
  }</div>` : '';
  let h=`
    <div class="box"><div class="bl">Verba atual</div>
      <div class="bv"><b style="font-size:16px">${BRL(i.verba)}</b> <span class="muted" style="font-size:12px">— método: ${metodo}</span>${i.curado_verba?'<span style="color:var(--ok);font-weight:700;font-size:12px"> · curada ✓</span>':'<span style="color:var(--and);font-size:12px"> · a curar</span>'}</div>
      <div id="orcLastChange" style="font-size:11.5px;margin-top:5px;color:var(--muted)"></div></div>
    ${editBar}
    <div class="box"><div class="bl">Composição selecionada${EDITO?'':' <span class="muted" style="text-transform:none;letter-spacing:0;font-weight:400">— somente leitura (clique em Editar pra alterar)</span>'}</div>
      <div class="bv" id="orcSel">—</div><div id="orcTotal" style="margin-top:6px;font-weight:700"></div></div>`;
  if(EDITO){
    h+=`
    <div class="fld" style="margin-top:8px"><label>Fonte da verba</label>
      <select id="orcFonte" onchange="orcSetFonte(this.value)">
        <option value="analitico" ${ORCFONTE==='analitico'?'selected':''}>Orçamento analítico (selecionar linhas)</option>
        <option value="composicao" ${ORCFONTE==='composicao'?'selected':''}>Composição (separa material × MO + quantitativo)</option>
      </select></div>
    <div id="orcFonteBox"></div>`;
  }
  h+=`<div class="note">No orçamento a Torre soma todos os pavimentos (ex.: "Pilares Torre 1"); o cronograma é por pavimento. A <b>composição</b> separa material × MO e usa o coeficiente para o quantitativo (área × consumo).</div>`;
  return h;
}
let ORCFONTE='analitico';
function orcEditar(){ EDITO=true; VERBA_USOS=null; ORC_NODES=[]; ORCFONTE=(CUR.verba_metodo==='composicao'?'composicao':'analitico'); COMP_SEL=(CUR.composicao_sel||[]).map(s=>({...s})); COMP_DATA=null; drawModal(); }
function orcCancelar(){ EDITO=false; drawModal(); }
async function orcLoadLastChange(ordem){
  const box=document.getElementById('orcLastChange'); if(!box)return;
  try{
    const d=await (await fetch('actions/historico.php?ordem='+ordem)).json();
    const v=(d.historico||[]).find(h=>/^Verba/.test(h.campo||''));  // histórico vem do mais recente p/ o mais antigo
    if(v){
      let q=v.created_at; try{ q=new Date(v.created_at).toLocaleString('pt-BR'); }catch(e){}
      box.innerHTML=`<span class="material-icons" style="font-size:14px;vertical-align:-3px;color:var(--verde)">history</span> Última alteração da verba por <b>${esc(v.usuario_nome||('#'+v.bitrix_id))}</b> · ${esc(q)}`;
    } else box.innerHTML='<span class="muted">Sem alteração de verba registrada ainda — a verba será marcada como curada quando alguém editar e salvar.</span>';
  }catch(e){ box.innerHTML=''; }
}
async function cronoLoadLastChange(ordem){
  const box=document.getElementById('cronoLastChange'); if(!box)return;
  try{
    const d=await (await fetch('actions/historico.php?ordem='+ordem)).json();
    const v=(d.historico||[]).find(h=>/^(cronograma|data em obra)/i.test(h.campo||''));  // mais recente primeiro
    if(v){
      let q=v.created_at; try{ q=new Date(v.created_at).toLocaleString('pt-BR'); }catch(e){}
      box.innerHTML=`<span class="material-icons" style="font-size:14px;vertical-align:-3px;color:var(--verde)">history</span> Última alteração do cronograma por <b>${esc(v.usuario_nome||('#'+v.bitrix_id))}</b> · ${esc(q)}`;
    } else box.innerHTML='<span class="muted">Sem alteração de cronograma registrada ainda.</span>';
  }catch(e){ box.innerHTML=''; }
}
function orcSetFonte(v){ ORCFONTE=v; orcRenderFonte(); }
function orcRenderFonte(){
  const box=document.getElementById('orcFonteBox'); if(!box)return;
  if(ORCFONTE==='composicao'){
    box.innerHTML=`
      <div class="fld"><label>Buscar composição por nome (ex.: contrapiso, alvenaria) — marque os insumos</label>
        <div class="search" style="border:1px solid var(--line)"><span class="material-icons" style="color:var(--muted)">search</span>
          <input id="compQ" placeholder="digite o serviço…" oninput="compBuscar()"></div></div>
      <div id="compSearch"></div>
      <div style="margin:10px 0 0"><button class="btn-ghost" id="insMassaBtn" onclick="insMassaToggle()" style="padding:6px 11px;font-size:12.5px"><span class="material-icons" style="font-size:15px;vertical-align:-3px;color:var(--dourado)">groups</span> Busca em massa por insumo <span class="material-icons mtcaret" style="font-size:16px;vertical-align:-3px">expand_more</span></button></div>
      <div id="insMassaPanel" style="display:none;margin-top:8px">
        <div class="box" style="background:#fbfdf9;border-color:var(--ok)">
          <div class="muted" style="font-size:11.5px;margin-bottom:6px">Pra insumo/MO pulverizado em muitas composições (ex.: encanador dentro de cada peça). Busca o insumo e traz de TODAS as composições, agrupado por sistema. Já usado em outro item = 🔒.</div>
          <div style="display:flex;gap:6px;flex-wrap:wrap;margin-bottom:6px;align-items:center">
            <span class="muted" style="font-size:11.5px">Atalho:</span>
            <button class="btn-ghost" style="padding:5px 10px" onclick="insMassaPreset('encanador')">👷 MO hidráulica (encanador)</button>
            <button class="btn-ghost" style="padding:5px 10px" onclick="insMassaPreset('eletricista')">⚡ MO elétrica (eletricista)</button>
          </div>
          <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center">
            <input id="insMassaTermos" style="flex:1;min-width:170px;border:1px solid var(--line);border-radius:8px;padding:7px 9px;font-size:12.5px" placeholder="ex.: encanador">
            <button class="btn-prim" style="padding:6px 12px" onclick="insMassaBuscar()"><span class="material-icons" style="font-size:15px;vertical-align:-3px">search</span> Buscar</button>
          </div>
          <div id="insMassaRes" style="margin-top:8px"></div>
        </div>
      </div>
      <div id="compDetail"></div>
      <div id="compBasket" style="margin-top:8px"></div>
      <div id="compTotals"></div>`;
    compRenderBasket();
    // já tem composição na cesta? abre a 1ª de cara (com os LOCAIS) p/ ajustar sem precisar re-buscar
    if(COMP_SEL.length && !COMP_DATA) compEscolher(COMP_SEL[0].cid);
  } else {
    box.innerHTML=`
      <div class="fld"><label>Buscar linha do orçamento por nome</label>
        <div class="search" style="border:1px solid var(--line)"><span class="material-icons" style="color:var(--muted)">search</span>
          <input id="orcQ" placeholder="ex.: tubo pvc, concreto pilar, aço viga…" oninput="orcBuscar()"></div></div>
      <div id="orcSearch"></div>
      <div style="display:flex;gap:8px;flex-wrap:wrap;margin:10px 0 0">
        <button class="btn-ghost" id="massaBtn" onclick="massaToggle()" style="padding:6px 11px;font-size:12.5px"><span class="material-icons" style="font-size:15px;vertical-align:-3px;color:var(--dourado)">bolt</span> Busca em massa <span class="material-icons mtcaret" style="font-size:16px;vertical-align:-3px">expand_more</span></button>
        <button class="btn-ghost" id="orcTreeBtn" onclick="orcTreeToggle()" style="padding:6px 11px;font-size:12.5px"><span class="material-icons" style="font-size:15px;vertical-align:-3px;color:var(--verde)">account_tree</span> Navegar a árvore <span class="material-icons mtcaret" style="font-size:16px;vertical-align:-3px">expand_more</span></button>
      </div>
      <div id="massaPanel" style="display:none;margin-top:8px">
        <div class="box" style="background:#fbfdf9;border-color:var(--ok)">
          <div class="muted" style="font-size:11.5px;margin-bottom:6px">Pra itens com muitos insumos (ex.: tubos e conexões). Atalho por fornecedor ou edite os termos; confira por <b>material</b> e adicione de uma vez. Já usado em outro item = 🔒.</div>
          <div style="display:flex;gap:6px;flex-wrap:wrap;margin-bottom:6px;align-items:center">
            <span class="muted" style="font-size:11.5px">Atalho:</span>
            <button class="btn-ghost" style="padding:5px 10px" onclick="massaPreset('pvc')">💧 PVC e CPVC</button>
            <button class="btn-ghost" style="padding:5px 10px" onclick="massaPreset('pex')">🔵 PEX</button>
            <button class="btn-ghost" style="padding:5px 10px" onclick="massaPreset('metal')">🔧 Registros / Metais</button>
          </div>
          <textarea id="massaTermos" style="width:100%;border:1px solid var(--line);border-radius:8px;padding:7px 9px;font-size:12.5px;min-height:40px" placeholder="tubo, luva, joelho, …">tubo, luva, joelho, cotovelo, junção, conexão, tê, adaptador, redução, niple, bucha, tampão</textarea>
          <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:6px;align-items:center">
            <select id="massaEscopo" style="border:1px solid var(--line);border-radius:8px;padding:6px 9px;font-size:12px"><option value="hidr">Escopo: Instalações (hidr/sanit)</option><option value="tudo">Escopo: orçamento inteiro</option></select>
            <select id="massaMaterial" style="border:1px solid var(--line);border-radius:8px;padding:6px 9px;font-size:12px"><option value="">Todos os materiais</option><option value="pvc,cpvc">Só PVC + CPVC</option><option value="pex">Só PEX</option><option value="metal">Só Metais/Registros</option><option value="cobre">Só Cobre</option></select>
            <button class="btn-prim" style="padding:6px 12px" onclick="massaBuscar()"><span class="material-icons" style="font-size:15px;vertical-align:-3px">search</span> Buscar</button>
          </div>
          <div id="massaRes" style="margin-top:8px"></div>
        </div>
      </div>
      <div id="orcTreeWrap" style="display:none;margin-top:8px">
        <div class="tree" id="orcTree">Carregando…</div>
      </div>
      <div style="margin-top:12px;display:flex;gap:8px"><button class="btn-prim" onclick="orcSalvar()">Salvar verba</button>
        <button class="btn-ghost" onclick="orcCancelar()">Cancelar</button></div>`;
  }
}
function locDet(det, todos){ if(!det||!det.length) return '';
  return `<div class="muted" style="font-size:11px;margin-top:3px;line-height:1.6"><span class="material-icons" style="font-size:12px;vertical-align:-2px;color:var(--dourado)">place</span> ${todos?'<b>todos os locais</b> · ':''}${det.map(l=>esc(l.local)+' ('+QNUM(l.qtde)+(l.unidade?' '+esc(l.unidade):'')+')').join(' · ')}</div>`; }
async function loadCompLocais(compSel){   // baixa os locais de cada composição envolvida (1 fetch por composição)
  const cids=[...new Set((compSel||[]).map(s=>s.cid).filter(Boolean))]; const m={};
  await Promise.all(cids.map(async cid=>{ try{ m[cid]=await (await fetch('actions/composicao_locais.php?id='+cid)).json(); }catch(e){} }));
  return m;
}
function insumoLocaisDet(s, locMap){      // detalhe salvo (filtrado) OU todos os locais da composição (se não filtrou)
  if(s.locais_det&&s.locais_det.length) return {det:s.locais_det, todos:false};
  const L=locMap&&locMap[s.cid];
  if(L&&L.grupos&&L.grupos.length) return {det:L.grupos.map(g=>({local:g.local,qtde:g.qtde,unidade:g.unidade||''})), todos:true};
  return {det:null, todos:false};
}
async function orcShowCurrent(i){
  // composição: mostra a cesta de insumos (read-only) + os LOCAIS de cada um (filtrados ou todos)
  if(i.verba_metodo==='composicao' && (i.composicao_sel||[]).length){
    const locMap=await loadCompLocais(i.composicao_sel);
    const el=document.getElementById('orcSel'); if(el){
      let vmat=0,vmo=0;
      el.innerHTML=i.composicao_sel.map(s=>{const c=(s.area||0)*(s.coef||0)*(s.rs_unit||0); if(s.tipo==='mo')vmo+=c;else vmat+=c;
        const ld=insumoLocaisDet(s, locMap);
        return `<div class="pickrow" style="align-items:flex-start"><span class="badge-tp ${s.tipo}">${s.tipo==='mo'?'MO':'MAT'}</span>
          <div style="min-width:0"><div>${esc(s.desc)}</div><small class="muted">${QNUM(s.area)} × ${QNUM(s.coef)} × R$${QNUM(s.rs_unit)} = ${BRL(c)}${s.q?' · define quantitativo':''}</small>${locDet(ld.det, ld.todos)}</div></div>`;}).join('');
      const t=document.getElementById('orcTotal'); if(t) t.textContent='Material '+BRL(vmat)+' · MO '+BRL(vmo);
    }
    return;
  }
  ORC_SEL=new Set((i.orcamento_refs||[]).map(Number));
  await orcRenderSel();
}
async function orcLoadTree(){
  const box=document.getElementById('orcTree'); if(!box)return;
  await loadVerbaUsos();   // pra travar as linhas já usadas em outro item
  const d=await (await fetch('actions/orcamento.php')).json();
  ORC_NODES=(d.linhas||[]).map(n=>({...n,expanded:false}));
  orcRenderTree();
}
function orcRenderTree(){
  const box=document.getElementById('orcTree'); if(!box)return;
  box.innerHTML=ORC_NODES.map((n,ix)=>{
    const ind=(n.depth-1)*16;
    const car=n.expansivel?`<span class="caret material-icons" onclick="orcExpand(${ix})">${n.expanded?'expand_more':'chevron_right'}</span>`:'<span class="caret-sp"></span>';
    const uso=n.folha?usadoPorOutro(n.id):[];
    let chk;
    if(!n.folha) chk='<span class="caret-sp"></span>';
    else if(uso.length && !ORC_SEL.has(n.id)) chk='<span class="material-icons" style="color:var(--pend);font-size:18px" title="Já usado em outro item — não pode entrar em 2">lock</span>';
    else chk=`<span class="material-icons chk" onclick="orcToggleSel(${n.id})" style="color:${ORC_SEL.has(n.id)?'var(--ok)':'var(--muted)'}">${ORC_SEL.has(n.id)?'check_box':'check_box_outline_blank'}</span>`;
    return `<div class="tnode ${n.folha?'':'tparent'}" style="padding-left:${ind}px${(uso.length&&!ORC_SEL.has(n.id))?';opacity:.6':''}">
      ${car}${chk}
      <span class="tname">${esc(n.descricao)}${uso.length?` <span style="color:var(--pend);font-size:11px">· já em “${esc(uso[0].nome)}”</span>`:''}</span>
      <span class="tval">${n.valor!=null?BRL(n.valor):''}</span>
    </div>`;
  }).join('');
}
async function orcExpand(ix){
  const n=ORC_NODES[ix]; if(!n)return;
  if(n.expanded){
    let j=ix+1; while(j<ORC_NODES.length && ORC_NODES[j].depth>n.depth) j++;
    ORC_NODES.splice(ix+1,j-(ix+1)); n.expanded=false; orcRenderTree(); return;
  }
  const d=await (await fetch('actions/orcamento.php?children_of='+encodeURIComponent(n.codigo))).json();
  const filhos=(d.linhas||[]).map(x=>({...x,expanded:false}));
  ORC_NODES.splice(ix+1,0,...filhos); n.expanded=true; orcRenderTree();
}
function orcToggleSel(id){
  if(!ORC_SEL.has(id)){ const u=usadoPorOutro(id); if(u.length){ toast('Essa linha já está na verba de “'+u[0].nome+'” — não pode entrar em 2 itens. Veja a Auditoria.'); return; } }
  const tr=document.getElementById('orcTree'), sr=document.querySelector('#orcSearch .srbox');
  const ts=tr?tr.scrollTop:0, ss=sr?sr.scrollTop:0;
  ORC_SEL.has(id)?ORC_SEL.delete(id):ORC_SEL.add(id);
  orcRenderTree(); orcRenderSel(); orcRenderSearch();
  const tr2=document.getElementById('orcTree'); if(tr2)tr2.scrollTop=ts;
  const sr2=document.querySelector('#orcSearch .srbox'); if(sr2)sr2.scrollTop=ss;
}
async function orcRenderSel(){
  const el=document.getElementById('orcSel'); if(!el)return;
  if(!ORC_SEL.size){el.innerHTML='<span class="muted">Nenhum item selecionado.</span>';document.getElementById('orcTotal').textContent='';return;}
  const d=await (await fetch('actions/orcamento.php?ids='+[...ORC_SEL].join(','))).json();
  let tot=0;
  el.innerHTML=d.linhas.map(l=>{tot+=(l.valor||0);return `<div class="pickrow">
    <span class="material-icons" style="font-size:16px;color:var(--ok)${EDITO?';cursor:pointer':''}" ${EDITO?`onclick="orcToggleSel(${l.id})" title="remover"`:''}>${EDITO?'check_box':'check_circle'}</span>
    <div><div>${esc(l.descricao)}</div><small class="muted">${esc(l.path_str||'')} · ${BRL(l.valor)}</small></div></div>`;}).join('');
  document.getElementById('orcTotal').textContent='Total: '+BRL(tot);
}
let ORC_LAST=[];
async function orcBuscar(){
  const q=document.getElementById('orcQ').value.trim();
  const box=document.getElementById('orcSearch'); if(!box)return;
  if(q.length<2){ORC_LAST=[];box.innerHTML='';return;}
  box.innerHTML='<div class="muted" style="font-size:12px;padding:4px">Buscando…</div>';
  await loadVerbaUsos();
  const d=await (await fetch('actions/orcamento.php?q='+encodeURIComponent(q))).json();
  ORC_LAST=d.linhas||[];
  if(!ORC_LAST.length){box.innerHTML='<div class="muted" style="font-size:12px;padding:4px">Nada encontrado.</div>';return;}
  orcRenderSearch();
}
function orcRenderSearch(){
  const box=document.getElementById('orcSearch'); if(!box)return;
  if(!ORC_LAST.length){box.innerHTML='';return;}
  box.innerHTML='<div class="srbox">'+ORC_LAST.map(l=>{const on=ORC_SEL.has(l.id); const uso=usadoPorOutro(l.id);
    if(uso.length && !on) return `<div class="pickrow" style="opacity:.6" title="Já usado — não pode entrar em 2 itens">
      <span class="material-icons" style="font-size:16px;color:var(--pend)">lock</span>
      <div><div>${esc(l.descricao)}</div><small style="color:var(--pend)">já em “${esc(uso[0].nome)}”${uso.length>1?' +'+(uso.length-1):''} · ${esc(l.path_str||'')}</small></div></div>`;
    return `<div class="pickrow" onclick="orcToggleSel(${l.id})">
    <span class="material-icons" style="font-size:16px;color:${on?'var(--ok)':'var(--muted)'}">${on?'check_box':'check_box_outline_blank'}</span>
    <div><div>${esc(l.descricao)}</div><small class="muted">${esc(l.path_str||'')} · ${BRL(l.valor)}</small></div></div>`;}).join('')+'</div>';
}
async function orcSalvar(){ EDITO=false; await saveAndReload({orcamento_refs:[...ORC_SEL]}); toast('Verba composta ('+ORC_SEL.size+' itens)'); }
async function orcLimpar(){ EDITO=false; ORC_SEL.clear(); await saveAndReload({orcamento_refs:[]}); toast('Composição limpa'); }
// Separar material × MO: converte verba analítica (linha inteira) em composição SÓ material, liberando a MO
async function separarMO(){
  if(!CUR) return;
  let d; try{ d=await (await fetch('actions/separar_mo.php?ordem='+CUR.ordem)).json(); }
  catch(e){ toast('Falha ao calcular'); return; }
  if(d.error){ toast(d.error); return; }
  const r=d.resumo;
  let msg='Separar material × MO de “'+CUR.nome+'”:\n\n'
    +'• Verba hoje (linha inteira, com MO):  '+BRL(r.verba_antes)+'\n'
    +'• Vira SÓ MATERIAL:  '+BRL(r.verba_depois)+'\n'
    +'• Libera de MÃO DE OBRA:  '+BRL(r.mo_liberada)+'  (pra alocar no item de MO)\n'
    +'• '+r.n_composicoes+' composições · '+r.n_insumos_mat+' insumos de material\n';
  if(r.sem_composicao && r.sem_composicao.length)
    msg+='\n⚠️ '+r.sem_composicao.length+' linha(s) SEM composição serão removidas (não dá pra separar):\n- '+r.sem_composicao.slice(0,6).join('\n- ')+(r.sem_composicao.length>6?'\n…':'')+'\n';
  msg+='\nO item passa a ser por composição (só material). Confirmar?';
  if(!confirm(msg)) return;
  const sel=(d.composicao_sel||[]).map(s=>({cid:s.cid, idx:s.idx, area:s.area, q:0, locais:s.locais||null}));
  EDITO=false;
  await saveAndReload({composicao_sel:sel, orcamento_refs:[]});
  toast('Material × MO separados — '+BRL(r.mo_liberada)+' de MO liberados. Agora monte o item de Mão de Obra.');
}

/* ===== Motor de USO da verba (uma linha do orçamento não pode compor 2 itens) ===== */
let VERBA_USOS=null;
async function loadVerbaUsos(force){
  if(VERBA_USOS && !force) return VERBA_USOS;
  try{ VERBA_USOS=await (await fetch('actions/verba_usos.php?_='+Date.now())).json(); }
  catch(e){ VERBA_USOS={usos:{},nomes:{}}; }
  return VERBA_USOS;
}
// linha já usada na verba de OUTRO item (≠ o atual)? devolve [{ordem,nome}]
function usadoPorOutro(lineId){
  if(!VERBA_USOS||!VERBA_USOS.usos) return [];
  const ords=VERBA_USOS.usos[lineId]||VERBA_USOS.usos[String(lineId)]||[];
  const cur=(typeof CUR!=='undefined'&&CUR)?Number(CUR.ordem):-1;
  return ords.filter(o=>Number(o)!==cur).map(o=>({ordem:o, nome:(VERBA_USOS.nomes&&(VERBA_USOS.nomes[o]||VERBA_USOS.nomes[String(o)]))||('item '+o)}));
}
// claims detalhadas por linha (trava da CESTA de composição: nível insumo × local)
function _curOrdem(){ return (typeof CUR!=='undefined'&&CUR)?Number(CUR.ordem):-1; }
function nomeItem(o){ return (VERBA_USOS&&VERBA_USOS.nomes&&(VERBA_USOS.nomes[o]||VERBA_USOS.nomes[String(o)]))||('item '+o); }
function lineClaims(L){ return (VERBA_USOS&&VERBA_USOS.linhas&&(VERBA_USOS.linhas[L]||VERBA_USOS.linhas[String(L)]))||{}; }
// linha tomada INTEIRA (analítico) por outro item → bloqueia QUALQUER insumo dela
function compLocalBloqueado(L){ const w=((lineClaims(L).w)||[]).filter(o=>Number(o)!==_curOrdem()); return w.length?{item:nomeItem(w[0])}:null; }
// separa um conjunto de linhas em livres × em conflito p/ o insumo (cid#idx): conflito = linha inteira por outro OU MESMO insumo por outro
function compInsumoSplit(cid, idx, lineIds){
  const key=cid+'#'+idx, livres=[], conf=[], items=new Set();
  (lineIds||[]).forEach(L=>{ const c=lineClaims(L);
    const w=((c.w)||[]).filter(o=>Number(o)!==_curOrdem());
    const ins=(((c.i)&&c.i[key])||[]).filter(o=>Number(o)!==_curOrdem());
    if(w.length||ins.length){ conf.push(L); [...w,...ins].forEach(o=>items.add(o)); } else livres.push(L);
  });
  return {livres, conf, items:[...items].map(nomeItem)};
}
function compTodasLinhas(){ return ((COMP_LOCAIS&&COMP_LOCAIS.grupos)||[]).flatMap(g=>g.linhas.map(l=>l.id)); }
function compCandidato(){ const g=(COMP_LOCAIS&&COMP_LOCAIS.grupos)||[]; return g.length?[...COMP_LOCAIS_SEL]:compTodasLinhas(); }
function somaQtdeDeLinhas(ids){ const set=new Set(ids); let a=0; ((COMP_LOCAIS&&COMP_LOCAIS.grupos)||[]).forEach(g=>g.linhas.forEach(l=>{ if(set.has(l.id)) a+=(l.qtde||0); })); return a; }

/* ===== Busca em massa (vários termos → agrupa por material/peça → adiciona à verba) ===== */
let MASSA=null, MASSA_SEL=new Set(), MASSA_OPEN=new Set(), MASSA_GROUP='material';
const MAT_INFO={pvc:{lbl:'PVC',ico:'💧'},cpvc:{lbl:'CPVC (água quente)',ico:'🔥'},pex:{lbl:'PEX',ico:'🔵'},cobre:{lbl:'Cobre',ico:'🟤'},metal:{lbl:'Metais / Registros (ferro, latão)',ico:'🔧'},outro:{lbl:'Outros',ico:'⚪'}};
const MAT_ORDER=['pvc','cpvc','pex','cobre','metal','outro'];
const MASSA_PRESETS={
  pvc:{termos:'tubo, luva, joelho, cotovelo, curva, junção, conexão, tê, adaptador, redução, bucha, niple, cap, tampão, caixa, sifonada, ralo, esgoto', escopo:'hidr', material:'pvc,cpvc'},
  pex:{termos:'tubo, conexão, luva, joelho, cotovelo, tê, adaptador, curva, redução, registro, kit', escopo:'hidr', material:'pex'},
  metal:{termos:'registro, misturador, válvula, valvula, adaptador', escopo:'tudo', material:'metal'}
};
function massaPreset(k){ const p=MASSA_PRESETS[k]; if(!p)return;
  const t=document.getElementById('massaTermos'); if(t) t.value=p.termos;
  const e=document.getElementById('massaEscopo'); if(e) e.value=p.escopo;
  const m=document.getElementById('massaMaterial'); if(m) m.value=p.material||'';
  massaBuscar();
}
async function massaBuscar(){
  const termos=(document.getElementById('massaTermos')?.value||'').trim();
  const escopo=document.getElementById('massaEscopo')?.value||'hidr';
  const material=document.getElementById('massaMaterial')?.value||'';
  const box=document.getElementById('massaRes'); if(!box)return;
  if(!termos){ box.innerHTML='<div class="muted" style="font-size:12px">Informe os termos.</div>'; return; }
  box.innerHTML='<div class="muted" style="font-size:12px;padding:4px">Buscando…</div>';
  await loadVerbaUsos();   // garante o mapa de uso pra travar duplicadas
  let d; try{ d=await (await fetch('actions/orcamento_massa.php?escopo='+escopo+'&material='+encodeURIComponent(material)+'&termos='+encodeURIComponent(termos))).json(); }
  catch(e){ box.innerHTML='<div class="muted" style="font-size:12px;color:var(--pend)">Falha: '+esc(e.message)+'</div>'; return; }
  if(d.error){ box.innerHTML='<div class="muted" style="font-size:12px;color:var(--pend)">Erro: '+esc(d.error)+'</div>'; return; }
  MASSA=d; MASSA_SEL=new Set(); MASSA_OPEN=new Set();
  (d.linhas||[]).forEach(l=>{ if(!usadoPorOutro(l.id).length) MASSA_SEL.add(l.id); });   // marca tudo, MENOS o já usado em outro item
  massaRender();
}
function massaGrupos(){
  const by={};
  (MASSA.linhas||[]).forEach(l=>{ const k=(MASSA_GROUP==='material'?l.material:l.termo)||'outro'; (by[k]=by[k]||[]).push(l); });
  let keys=Object.keys(by);
  if(MASSA_GROUP==='material'){ const oi=x=>{const i=MAT_ORDER.indexOf(x); return i<0?99:i;}; keys.sort((a,b)=>oi(a)-oi(b)); }
  else keys.sort((a,b)=>by[b].reduce((s,l)=>s+l.valor,0)-by[a].reduce((s,l)=>s+l.valor,0));
  return keys.map(k=>({key:k, label:(MASSA_GROUP==='material'&&MAT_INFO[k])?(MAT_INFO[k].ico+' '+MAT_INFO[k].lbl):k, linhas:by[k]}));
}
function massaRender(){
  const box=document.getElementById('massaRes'); if(!box)return;
  if(!MASSA||!(MASSA.linhas||[]).length){ box.innerHTML='<div class="muted" style="font-size:12px">Nada encontrado.</div>'; return; }
  const grupos=massaGrupos(); let selN=0, selV=0;
  const gh=grupos.map(g=>{
    const livres=g.linhas.filter(l=>!usadoPorOutro(l.id).length);
    const allOn=livres.length&&livres.every(l=>MASSA_SEL.has(l.id)), someOn=livres.some(l=>MASSA_SEL.has(l.id));
    let gV=0, nUsadas=0; g.linhas.forEach(l=>{ if(MASSA_SEL.has(l.id)){selN++;selV+=l.valor;} gV+=l.valor; if(usadoPorOutro(l.id).length)nUsadas++; });
    const open=MASSA_OPEN.has(g.key);
    return `<div class="tnode tparent">
      <span class="material-icons chk" onclick="massaToggleGrupo('${g.key}')" style="color:${allOn?'var(--ok)':someOn?'var(--and)':'var(--muted)'}">${allOn?'check_box':someOn?'indeterminate_check_box':'check_box_outline_blank'}</span>
      <span class="caret material-icons" onclick="massaExpand('${g.key}')">${open?'expand_more':'chevron_right'}</span>
      <span class="tname">${esc(g.label)} <span class="muted">(${g.linhas.length}${nUsadas?' · <span style="color:var(--pend)">'+nUsadas+' 🔒</span>':''})</span></span>
      <span class="tval">${BRL(gV)}</span></div>`+
    (open?g.linhas.map(l=>{ const uso=usadoPorOutro(l.id), on=MASSA_SEL.has(l.id);
      if(uso.length) return `<div class="tnode" style="padding-left:30px;opacity:.6" title="Já usado — não pode entrar em 2 itens">
        <span class="material-icons" style="color:var(--pend);font-size:16px">lock</span>
        <span class="tname" style="font-size:11px">${esc((l.desc||'').slice(0,44))} <span style="color:var(--pend)">· já em “${esc(uso[0].nome)}”${uso.length>1?' +'+(uso.length-1):''}</span></span>
        <span class="tval">${BRL(l.valor)}</span></div>`;
      return `<div class="tnode" style="padding-left:30px">
        <span class="material-icons chk" onclick="massaToggleLinha(${l.id})" style="color:${on?'var(--ok)':'var(--muted)'};font-size:16px">${on?'check_box':'check_box_outline_blank'}</span>
        <span class="tname" style="font-size:11px">${esc((l.desc||'').slice(0,44))} <span class="muted">· ${esc(l.local)}${MASSA_GROUP==='material'?' · '+esc(l.termo):''}</span></span>
        <span class="tval">${BRL(l.valor)}</span></div>`;}).join(''):'');
  }).join('');
  box.innerHTML=`<div style="display:flex;gap:6px;align-items:center;margin-bottom:5px;font-size:11.5px">
      <span class="muted">Agrupar por:</span>
      <button class="btn-ghost" style="padding:3px 9px${MASSA_GROUP==='material'?';background:var(--azul);color:#fff':''}" onclick="massaSetGroup('material')">Material / fornecedor</button>
      <button class="btn-ghost" style="padding:3px 9px${MASSA_GROUP==='termo'?';background:var(--azul);color:#fff':''}" onclick="massaSetGroup('termo')">Tipo de peça</button></div>
    <div class="tree" style="max-height:300px">${gh}</div>
    <div class="box" style="margin-top:6px;padding:8px 12px"><div class="bv"><b>Selecionado: ${selN} linhas · ${BRL(selV)}</b> <span class="muted" style="font-size:11.5px">de ${MASSA.n_linhas} · ${BRL(MASSA.total)} encontrados</span></div></div>
    <div style="margin-top:6px"><button class="btn-prim" onclick="massaAdd()"><span class="material-icons" style="font-size:15px;vertical-align:-3px">add</span> Adicionar à verba (${selN} linhas)</button></div>`;
}
function massaSetGroup(g){ MASSA_GROUP=g; MASSA_OPEN=new Set(); massaRender(); }
function massaToggleGrupo(key){ const g=massaGrupos().find(x=>x.key===key); if(!g)return;
  const livres=g.linhas.filter(l=>!usadoPorOutro(l.id).length);
  const allOn=livres.length&&livres.every(l=>MASSA_SEL.has(l.id));
  livres.forEach(l=>{ allOn?MASSA_SEL.delete(l.id):MASSA_SEL.add(l.id); }); massaRender(); }
function massaToggleLinha(id){ if(usadoPorOutro(id).length)return; MASSA_SEL.has(id)?MASSA_SEL.delete(id):MASSA_SEL.add(id); massaRender(); }
function massaExpand(key){ MASSA_OPEN.has(key)?MASSA_OPEN.delete(key):MASSA_OPEN.add(key); massaRender(); }
function massaAdd(){
  const add=[...MASSA_SEL].filter(id=>!usadoPorOutro(id).length);   // nunca adiciona linha travada
  if(!add.length){ toast('Marque ao menos uma linha livre'); return; }
  add.forEach(id=>ORC_SEL.add(id));
  orcRenderSel();
  MASSA=null; MASSA_SEL=new Set(); const box=document.getElementById('massaRes'); if(box) box.innerHTML='';
  toast(add.length+' linhas adicionadas à verba. Clique em Salvar verba.');
}
// expandir/recolher os painéis avançados (ficam fechados por padrão p/ não poluir)
function massaToggle(){ const p=document.getElementById('massaPanel'), b=document.getElementById('massaBtn'); if(!p)return;
  const open=p.style.display==='none'; p.style.display=open?'block':'none';
  const ic=b&&b.querySelector('.mtcaret'); if(ic) ic.textContent=open?'expand_less':'expand_more';
}
function orcTreeToggle(){ const w=document.getElementById('orcTreeWrap'), b=document.getElementById('orcTreeBtn'); if(!w)return;
  const open=w.style.display==='none'; w.style.display=open?'block':'none';
  const ic=b&&b.querySelector('.mtcaret'); if(ic) ic.textContent=open?'expand_less':'expand_more';
  if(open && !ORC_NODES.length) orcLoadTree();   // carrega a árvore só na 1ª vez que abrir
}

/* ----- Composição — CESTA de insumos (de 1+ composições): verba = soma do que você marcar ----- */
let COMP_DATA=null, COMP_AREA=0, COMP_LAST=[], COMP_SEL=[];
let COMP_LOCAIS=null, COMP_LOCAIS_SEL=new Set();   // locais (linhas do orçamento) da composição aberta + selecionados
async function compBuscar(){
  const q=document.getElementById('compQ').value.trim();
  const box=document.getElementById('compSearch'); if(!box)return;
  if(q.length<2){box.innerHTML='';return;}
  box.innerHTML='<div class="muted" style="font-size:12px;padding:4px">Buscando…</div>';
  const d=await (await fetch('actions/composicao.php?q='+encodeURIComponent(q))).json();
  COMP_LAST=d.composicoes||[];
  if(!COMP_LAST.length){box.innerHTML='<div class="muted" style="font-size:12px;padding:4px">Nada encontrado.</div>';return;}
  box.innerHTML='<div class="srbox">'+COMP_LAST.map(c=>`<div class="pickrow" onclick="compEscolher(${c.id})">
    <span class="material-icons" style="font-size:16px;color:var(--verde)">playlist_add</span>
    <div><div>${esc(c.descricao)}</div><small class="muted">${QNUM(c.qtde_total)} ${esc(c.unidade||'')} · R$${QNUM(c.rs_unit)}/un</small></div></div>`).join('')+'</div>';
}
async function compEscolher(id){
  await loadVerbaUsos();   // pra travar locais/insumos já usados em outro item
  COMP_DATA=await (await fetch('actions/composicao.php?id='+id)).json();
  COMP_LOCAIS=await (await fetch('actions/composicao_locais.php?id='+id)).json();
  const allIds=(COMP_LOCAIS.grupos||[]).flatMap(g=>g.linhas.map(l=>l.id));
  // se já há insumo desta composição na cesta com locais salvos, reusa a seleção; senão, todos os locais
  const ex=COMP_SEL.find(s=>s.cid===id && Array.isArray(s.locais) && s.locais.length);
  COMP_LOCAIS_SEL=new Set((ex?ex.locais:allIds).filter(L=>!compLocalBloqueado(L)));  // não marca locais tomados inteiros por outro item
  compRecalcArea();
  document.getElementById('compSearch').innerHTML='';
  compRenderDetail();
}
function compRecalcArea(){
  const grupos=(COMP_LOCAIS&&COMP_LOCAIS.grupos)||[];
  if(!grupos.length){ COMP_AREA=COMP_DATA?(COMP_DATA.qtde_total||0):0; return; }
  let a=0; grupos.forEach(g=>g.linhas.forEach(l=>{ if(COMP_LOCAIS_SEL.has(l.id)) a+=(l.qtde||0); }));
  COMP_AREA=a;
}
function compSyncSelArea(){   // re-restringe a área/locais de cada insumo desta composição (exclui o que já está em outro item)
  if(!COMP_DATA)return;
  const hasLocais=!!(COMP_LOCAIS&&COMP_LOCAIS.grupos&&COMP_LOCAIS.grupos.length);
  const cand=compCandidato();
  COMP_SEL.forEach(s=>{ if(s.cid===COMP_DATA.id){
    if(hasLocais){ const sp=compInsumoSplit(s.cid,s.idx,cand); s.locais=sp.livres; s.area=somaQtdeDeLinhas(sp.livres); }
    else { s.locais=null; s.area=COMP_AREA; }
  }});
}
function compLocalToggle(id){
  if(!COMP_LOCAIS_SEL.has(id)){ const b=compLocalBloqueado(id); if(b){ toast('Esse local já está usado INTEIRO em “'+b.item+'” — não dá pra usar aqui.'); return; } }
  COMP_LOCAIS_SEL.has(id)?COMP_LOCAIS_SEL.delete(id):COMP_LOCAIS_SEL.add(id); compRecalcArea(); compSyncSelArea(); compRenderDetail(); compRenderBasket();
}
function compLocalToggleGroup(gi){
  const g=((COMP_LOCAIS&&COMP_LOCAIS.grupos)||[])[gi]; if(!g)return;
  const livres=g.linhas.filter(l=>!compLocalBloqueado(l.id));
  const allOn=livres.length&&livres.every(l=>COMP_LOCAIS_SEL.has(l.id));
  livres.forEach(l=>{ allOn?COMP_LOCAIS_SEL.delete(l.id):COMP_LOCAIS_SEL.add(l.id); });
  compRecalcArea(); compSyncSelArea(); compRenderDetail(); compRenderBasket();
}
function compRenderDetail(){
  const box=document.getElementById('compDetail'); if(!box||!COMP_DATA)return;
  const c=COMP_DATA; const un=esc(c.unidade||'');
  const grupos=(COMP_LOCAIS&&COMP_LOCAIS.grupos)||[];
  const locaisHtml = grupos.length ? `
    <div class="fld" style="margin-top:6px;margin-bottom:2px"><label><span class="material-icons" style="font-size:14px;vertical-align:-3px;color:var(--dourado)">place</span> Locais desta composição — desmarque o que NÃO entra (ex.: tirar muros/áreas comuns, manter só a fachada das torres)</label></div>
    <div class="tree" style="max-height:210px">
      ${grupos.map((g,gi)=>{
        const free=g.linhas.filter(l=>!compLocalBloqueado(l.id));
        const allOn=free.length&&free.every(l=>COMP_LOCAIS_SEL.has(l.id)), someOn=free.some(l=>COMP_LOCAIS_SEL.has(l.id));
        const gico=allOn?'check_box':(someOn?'indeterminate_check_box':'check_box_outline_blank');
        const nBloq=g.linhas.length-free.length;
        return `<div class="tnode tparent">
          <span class="material-icons chk" onclick="compLocalToggleGroup(${gi})" style="color:${allOn?'var(--ok)':(someOn?'var(--and)':'var(--muted)')}">${gico}</span>
          <span class="tname">${esc(g.local)}${nBloq?` <span style="color:var(--pend);font-size:11px">· ${nBloq} 🔒</span>`:''}</span><span class="tval">${QNUM(g.qtde)} ${un}</span></div>`+
          g.linhas.map(l=>{ const b=compLocalBloqueado(l.id); const on=COMP_LOCAIS_SEL.has(l.id);
            if(b) return `<div class="tnode" style="padding-left:26px;opacity:.55" title="linha usada inteira em outro item">
              <span class="material-icons" style="color:var(--pend);font-size:16px">lock</span>
              <span class="tname" style="font-size:11.5px">${esc(l.sub)} <span style="color:var(--pend)">· já em “${esc(b.item)}”</span></span><span class="tval">${QNUM(l.qtde)} ${un}</span></div>`;
            return `<div class="tnode" style="padding-left:26px">
            <span class="material-icons chk" onclick="compLocalToggle(${l.id})" style="color:${on?'var(--ok)':'var(--muted)'};font-size:17px">${on?'check_box':'check_box_outline_blank'}</span>
            <span class="tname" style="font-size:11.5px">${esc(l.sub)}</span><span class="tval">${QNUM(l.qtde)} ${un}</span></div>`;}).join('');
      }).join('')}
    </div>
    <div class="muted" style="font-size:12px;margin:5px 0 2px">Área selecionada: <b style="color:var(--verde-d)">${QNUM(COMP_AREA)} ${un}</b> <span style="opacity:.7">de ${QNUM(COMP_LOCAIS.total||c.qtde_total)} ${un} (todos os locais)</span></div>
  ` : `
    <div class="fld"><label>Área/quantidade desta composição (vale para os insumos que marcar; padrão = total)</label>
      <input id="compArea" type="number" step="any" value="${COMP_AREA}" oninput="COMP_AREA=parseFloat(this.value)||0;compSyncSelArea();compRenderBasket()"></div>`;
  box.innerHTML=`
    <div class="box"><div class="bl">${esc(c.descricao)}</div>
      <div class="bv muted" style="font-size:12px">unidade ${un} · total no orçamento ${QNUM(c.qtde_total)} ${un}</div></div>
    ${locaisHtml}
    <div class="fld" style="margin:6px 0 2px"><label>Insumos — marque o que entra na verba (a área vem dos locais acima)</label></div>
    <div class="tree" style="max-height:170px">
      ${(()=>{ const cand=compCandidato(); return c.insumos.map((in_,ix)=>{ const on=COMP_SEL.some(s=>s.cid===c.id&&s.idx===ix);
        const sp=compInsumoSplit(c.id,ix,cand); const fully=cand.length>0&&sp.livres.length===0; const partial=!fully&&sp.conf.length>0;
        if(fully&&!on) return `<div class="tnode" style="opacity:.55" title="esse insumo já está em outro item em todos os locais selecionados">
          <span class="material-icons" style="color:var(--pend)">lock</span>
          <span class="badge-tp ${in_.tipo}">${in_.tipo==='mo'?'MO':'MAT'}</span>
          <span class="tname">${esc(in_.descricao)} <span style="color:var(--pend);font-size:11px">· já em “${esc(sp.items[0]||'')}” (todos os locais)</span></span></div>`;
        return `<div class="tnode">
        <span class="material-icons chk" onclick="compToggleInsumo(${ix})" style="color:${on?'var(--ok)':'var(--muted)'}">${on?'check_box':'check_box_outline_blank'}</span>
        <span class="badge-tp ${in_.tipo}">${in_.tipo==='mo'?'MO':'MAT'}</span>
        <span class="tname">${esc(in_.descricao)}${partial?` <span style="color:var(--and);font-size:11px">· ⚠️ ${sp.conf.length} local(is) já em “${esc(sp.items[0]||'')}” (não conta)</span>`:''}</span>
        <span class="tval">${QNUM(in_.coef)} ${esc(in_.unidade||'')} × R$${QNUM(in_.rs_unit)}</span>
      </div>`;}).join(''); })()}
    </div>
    <div class="muted" style="font-size:11.5px;margin-top:4px">Ex.: marque só a MO do reboco. Insumo/local já usado em outro item aparece 🔒/⚠️ e não conta de novo.</div>`;
}
function compToggleInsumo(ix){
  const c=COMP_DATA; const in_=c&&c.insumos[ix]; if(!in_)return;
  const i=COMP_SEL.findIndex(s=>s.cid===c.id&&s.idx===ix);
  if(i>=0){ COMP_SEL.splice(i,1); compRenderDetail(); compRenderBasket(); return; }
  const hasLocais=!!(COMP_LOCAIS&&COMP_LOCAIS.grupos&&COMP_LOCAIS.grupos.length);
  const cand=compCandidato(); const sp=compInsumoSplit(c.id, ix, cand);
  if(cand.length>0 && sp.livres.length===0){ toast('“'+in_.descricao+'” já está em “'+(sp.items[0]||'outro item')+'” em todos os locais — não dá pra contar de novo.'); return; }
  const area=hasLocais?somaQtdeDeLinhas(sp.livres):(COMP_AREA||c.qtde_total||0);
  COMP_SEL.push({cid:c.id, idx:ix, area, q:!COMP_SEL.some(s=>s.cid===c.id&&s.q),
    locais:hasLocais?sp.livres:null,
    desc:in_.descricao, tipo:in_.tipo, unidade:in_.unidade, coef:+in_.coef, rs_unit:+in_.rs_unit, compdesc:c.descricao});
  if(sp.conf.length) toast(sp.conf.length+' local(is) já em “'+(sp.items[0]||'')+'” não entraram neste insumo.');
  compRenderDetail(); compRenderBasket();
}
let COMP_BASKET_GROUPS=[];
function compRemoverGrupo(i){ const g=COMP_BASKET_GROUPS[i]; if(!g)return; COMP_SEL=COMP_SEL.filter(s=>!(s.desc===g.desc&&s.tipo===g.tipo)); compRenderDetail(); compRenderBasket(); }
function compRenderBasket(){
  const box=document.getElementById('compBasket'), tot=document.getElementById('compTotals');
  if(!box)return;
  if(!COMP_SEL.length){ box.innerHTML='<div class="muted" style="font-size:12px;padding:6px 2px">Nenhum insumo na verba ainda — marque insumos das composições acima (de quantas composições quiser).</div>'; if(tot)tot.innerHTML=''; return; }
  let vmat=0,vmo=0,qval=0,qun='';
  COMP_SEL.forEach(s=>{ const c=(s.area||0)*(s.coef||0)*(s.rs_unit||0); if(s.tipo==='mo')vmo+=c; else vmat+=c; if(s.q){ qval+=(s.area||0)*(s.coef||0); if(!qun)qun=s.unidade; } });
  if(COMP_SEL.length>25){
    // RESUMO: muitos insumos (ex.: 364 encanadores) — agrupa por tipo/descrição pra não virar lista gigante
    const by={}; COMP_SEL.forEach(s=>{ const key=s.desc+'|'+s.tipo; if(!by[key])by[key]={desc:s.desc,tipo:s.tipo,n:0,custo:0}; by[key].n++; by[key].custo+=(s.area||0)*(s.coef||0)*(s.rs_unit||0); });
    COMP_BASKET_GROUPS=Object.values(by).sort((a,b)=>b.custo-a.custo);
    box.innerHTML=`<div class="bl" style="margin-bottom:4px">Verba composta — ${COMP_SEL.length} insumos (resumo)</div>`+
      COMP_BASKET_GROUPS.map((g,i)=>`<div class="pickrow" style="gap:8px;align-items:center">
        <span class="badge-tp ${g.tipo}">${g.tipo==='mo'?'MO':'MAT'}</span>
        <div style="flex:1;min-width:0">${esc(g.desc)} <span class="muted">· de ${g.n} composições</span></div>
        <span class="money" style="min-width:96px;text-align:right">${BRL(g.custo)}</span>
        <span class="material-icons" style="cursor:pointer;color:var(--pend);font-size:18px" onclick="compRemoverGrupo(${i})" title="remover todos deste tipo">close</span>
      </div>`).join('')+
      `<div class="muted" style="font-size:11px;margin-top:4px">Resumido (muitos insumos). Pra ajustar local/área de um específico, abra a composição dele na busca acima.</div>`;
  } else {
    box.innerHTML='<div class="bl" style="margin-bottom:4px">Verba composta destes insumos</div>'+COMP_SEL.map((s,k)=>{
      const custo=(s.area||0)*(s.coef||0)*(s.rs_unit||0);
      return `<div class="pickrow" style="gap:8px;align-items:center">
        <span class="badge-tp ${s.tipo}">${s.tipo==='mo'?'MO':'MAT'}</span>
        <div style="flex:1;min-width:0"><div style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap">${esc(s.desc)}</div>
          <small class="muted">${esc((s.compdesc||'').slice(0,38))} · ${QNUM(s.coef)}×R$${QNUM(s.rs_unit)}</small></div>
        <input type="number" step="any" style="width:84px;border:1px solid var(--line);border-radius:7px;padding:4px 6px" value="${s.area}" oninput="COMP_SEL[${k}].area=parseFloat(this.value)||0;compRenderBasket()" title="área/quantidade">
        <span class="money" style="min-width:88px;text-align:right">${BRL(custo)}</span>
        <label class="ckl" style="font-size:11px" title="usar pro quantitativo"><input type="checkbox" ${s.q?'checked':''} onchange="COMP_SEL[${k}].q=this.checked;compRenderBasket()"> qtd</label>
        <span class="material-icons" style="cursor:pointer;color:var(--pend);font-size:18px" onclick="COMP_SEL.splice(${k},1);compRenderDetail();compRenderBasket()" title="remover">close</span>
      </div>`;
    }).join('');
  }
  if(tot) tot.innerHTML=`<div class="box" style="margin-top:8px"><div class="bv">
      <b>Material:</b> ${BRL(vmat)} &nbsp;·&nbsp; <b>MO:</b> ${BRL(vmo)} &nbsp;·&nbsp; <b>Total verba:</b> ${BRL(vmat+vmo)}<br>
      <span class="muted" style="font-size:12px">Quantitativo: ${qval>0?QNUM(qval)+' '+esc(qun):'— (marque "qtd" em algum insumo)'}</span></div></div>
    <div style="margin-top:10px;display:flex;gap:8px"><button class="btn-prim" onclick="compSalvar()">Salvar verba por composição</button>
      <button class="btn-ghost" onclick="orcCancelar()">Cancelar</button></div>`;
}
async function compSalvar(){
  if(!COMP_SEL.length){toast('Marque ao menos um insumo');return;}
  // tira insumos que ficaram sem nenhum local livre (100% já em outro item) — não contam
  const validos=COMP_SEL.filter(s=>!(Array.isArray(s.locais)&&s.locais.length===0));
  const removidos=COMP_SEL.length-validos.length;
  if(!validos.length){ toast('Os insumos marcados já estão em outro item em todos os locais — nada pra salvar sem duplicar.'); return; }
  COMP_SEL=validos; EDITO=false;
  await saveAndReload({composicao_sel: COMP_SEL.map(s=>({cid:s.cid, idx:s.idx, area:s.area, q:s.q?1:0, locais:s.locais||null}))});
  toast('Verba por composição salva ('+COMP_SEL.length+' insumo(s))'+(removidos?' · '+removidos+' já usado(s) removido(s)':''));
}

/* ===== Busca em massa por INSUMO (MO/insumo pulverizado em muitas composições) ===== */
let INSMASSA=null, INSMASSA_SEL=new Set(), INSMASSA_OPEN=new Set();
function insMassaToggle(){ const p=document.getElementById('insMassaPanel'), b=document.getElementById('insMassaBtn'); if(!p)return;
  const open=p.style.display==='none'; p.style.display=open?'block':'none';
  const ic=b&&b.querySelector('.mtcaret'); if(ic) ic.textContent=open?'expand_less':'expand_more';
}
function insMassaPreset(k){ const t=document.getElementById('insMassaTermos'); if(t) t.value=k; insMassaBuscar(); }
function insMassaKey(m){ return m.cid+'#'+m.idx; }
function insMassaFully(m){ const ids=m.locais.map(l=>l.id); const sp=compInsumoSplit(m.cid,m.idx,ids); return (ids.length>0 && sp.livres.length===0); }
function insMassaGrupos(){ const by={}; (INSMASSA.matches||[]).forEach(m=>{ (by[m.sistema]=by[m.sistema]||[]).push(m); });
  return Object.keys(by).sort((a,b)=>by[b].reduce((s,m)=>s+m.valor,0)-by[a].reduce((s,m)=>s+m.valor,0)).map(k=>({sis:k, matches:by[k]})); }
async function insMassaBuscar(){
  const termos=(document.getElementById('insMassaTermos')?.value||'').trim();
  const box=document.getElementById('insMassaRes'); if(!box)return;
  if(!termos){ box.innerHTML='<div class="muted" style="font-size:12px">Informe o insumo (ex.: encanador).</div>'; return; }
  box.innerHTML='<div class="muted" style="font-size:12px;padding:4px">Buscando…</div>';
  await loadVerbaUsos();
  let d; try{ d=await (await fetch('actions/composicao_insumo_massa.php?termos='+encodeURIComponent(termos))).json(); }
  catch(e){ box.innerHTML='<div class="muted" style="font-size:12px;color:var(--pend)">Falha: '+esc(e.message)+'</div>'; return; }
  if(d.error){ box.innerHTML='<div class="muted" style="font-size:12px;color:var(--pend)">Erro: '+esc(d.error)+'</div>'; return; }
  INSMASSA=d; INSMASSA_OPEN=new Set(); INSMASSA_SEL=new Set();
  (d.matches||[]).forEach(m=>{ if(!insMassaFully(m)) INSMASSA_SEL.add(insMassaKey(m)); });   // marca tudo, menos o já usado em outro item
  insMassaRender();
}
function insMassaRender(){
  const box=document.getElementById('insMassaRes'); if(!box)return;
  if(!INSMASSA||!(INSMASSA.matches||[]).length){ box.innerHTML='<div class="muted" style="font-size:12px">Nada encontrado.</div>'; return; }
  const grupos=insMassaGrupos(); let selN=0, selV=0;
  const gh=grupos.map((g,gi)=>{
    const livres=g.matches.filter(m=>!insMassaFully(m));
    const allOn=livres.length&&livres.every(m=>INSMASSA_SEL.has(insMassaKey(m))), someOn=livres.some(m=>INSMASSA_SEL.has(insMassaKey(m)));
    let gV=0,nBloq=0; g.matches.forEach(m=>{ if(INSMASSA_SEL.has(insMassaKey(m))){selN++;selV+=m.valor;} gV+=m.valor; if(insMassaFully(m))nBloq++; });
    const open=INSMASSA_OPEN.has(g.sis);
    return `<div class="tnode tparent">
      <span class="material-icons chk" onclick="insMassaToggleGrupo(${gi})" style="color:${allOn?'var(--ok)':someOn?'var(--and)':'var(--muted)'}">${allOn?'check_box':someOn?'indeterminate_check_box':'check_box_outline_blank'}</span>
      <span class="caret material-icons" onclick="insMassaExpand(${gi})">${open?'expand_more':'chevron_right'}</span>
      <span class="tname">${esc(g.sis)} <span class="muted">(${g.matches.length}${nBloq?' · <span style="color:var(--pend)">'+nBloq+' 🔒</span>':''})</span></span>
      <span class="tval">${BRL(gV)}</span></div>`+
    (open?g.matches.map(m=>{ const on=INSMASSA_SEL.has(insMassaKey(m));
      if(insMassaFully(m)) return `<div class="tnode" style="padding-left:30px;opacity:.6"><span class="material-icons" style="color:var(--pend);font-size:16px">lock</span>
        <span class="tname" style="font-size:11px">${esc(m.ins)} <span class="muted">· ${esc((m.comp||'').slice(0,28))}</span> <span style="color:var(--pend)">· já em outro item</span></span><span class="tval">${BRL(m.valor)}</span></div>`;
      return `<div class="tnode" style="padding-left:30px"><span class="material-icons chk" onclick="insMassaToggleLinha('${insMassaKey(m)}')" style="color:${on?'var(--ok)':'var(--muted)'};font-size:16px">${on?'check_box':'check_box_outline_blank'}</span>
        <span class="tname" style="font-size:11px"><span class="badge-tp ${m.tipo}">${m.tipo==='mo'?'MO':'MAT'}</span> ${esc(m.ins)} <span class="muted">· ${esc((m.comp||'').slice(0,28))} · ${QNUM(m.area)}${esc(m.unidade||'')}×${QNUM(m.coef)}</span></span><span class="tval">${BRL(m.valor)}</span></div>`;
    }).join(''):'');
  }).join('');
  box.innerHTML=`<div class="tree" style="max-height:300px">${gh}</div>
    <div class="box" style="margin-top:6px;padding:8px 12px"><div class="bv"><b>Selecionado: ${selN} insumos · ${BRL(selV)}</b> <span class="muted" style="font-size:11.5px">de ${INSMASSA.n} · ${BRL(INSMASSA.total)} encontrados</span></div></div>
    <div style="margin-top:6px"><button class="btn-prim" onclick="insMassaAdd()"><span class="material-icons" style="font-size:15px;vertical-align:-3px">add</span> Adicionar à verba (${selN})</button></div>`;
}
function insMassaToggleGrupo(gi){ const g=insMassaGrupos()[gi]; if(!g)return; const livres=g.matches.filter(m=>!insMassaFully(m));
  const allOn=livres.length&&livres.every(m=>INSMASSA_SEL.has(insMassaKey(m))); livres.forEach(m=>{ allOn?INSMASSA_SEL.delete(insMassaKey(m)):INSMASSA_SEL.add(insMassaKey(m)); }); insMassaRender(); }
function insMassaToggleLinha(key){ INSMASSA_SEL.has(key)?INSMASSA_SEL.delete(key):INSMASSA_SEL.add(key); insMassaRender(); }
function insMassaExpand(gi){ const g=insMassaGrupos()[gi]; if(!g)return; INSMASSA_OPEN.has(g.sis)?INSMASSA_OPEN.delete(g.sis):INSMASSA_OPEN.add(g.sis); insMassaRender(); }
function insMassaAdd(){
  if(!INSMASSA) return;
  const sel=(INSMASSA.matches||[]).filter(m=>INSMASSA_SEL.has(insMassaKey(m)));
  let added=0, restr=0, skip=0;
  sel.forEach(m=>{
    if(COMP_SEL.some(s=>s.cid===m.cid&&s.idx===m.idx)){ skip++; return; }       // já está na cesta
    const ids=m.locais.map(l=>l.id); const sp=compInsumoSplit(m.cid,m.idx,ids);
    if(ids.length>0 && sp.livres.length===0) return;                            // 100% em conflito → pula
    const freeSet=new Set(sp.livres);
    const area=ids.length ? m.locais.filter(l=>freeSet.has(l.id)).reduce((a,l)=>a+l.q,0) : m.area;
    if(sp.conf.length) restr++;
    COMP_SEL.push({cid:m.cid, idx:m.idx, area, q:0, locais:ids.length?sp.livres:null,
      desc:m.ins, tipo:m.tipo, unidade:m.unidade, coef:+m.coef, rs_unit:+m.rs_unit, compdesc:m.comp});
    added++;
  });
  INSMASSA=null; const box=document.getElementById('insMassaRes'); if(box) box.innerHTML='';
  compRenderBasket();
  toast(added+' insumos adicionados à verba'+(restr?(' · '+restr+' com locais já usados restringidos'):'')+(skip?(' · '+skip+' já na cesta'):'')+'. Clique em Salvar verba por composição.');
}

/* ----- Dicionário (template/aprendizado, editável → reflete em obra nova) ----- */
function dicTab(i){
  if(!EDITD){
    return `
    <div class="box"><div class="bl">Escopo</div><div class="bv">${esc(i.escopo||'—')}</div></div>
    <div class="box"><div class="bl">Variáveis a cotar (template)</div><div class="bv">${esc(i.variaveis_cotar||'—')}</div></div>
    <div class="box"><div class="bl">Lições / armadilhas</div><div class="bv">${esc(i.licoes||'—')}</div></div>
    <div class="box"><div class="bl">Documentos / exigências</div><div class="bv">${esc(i.documentos||'—')}</div></div>
    <div style="margin-top:6px">${CAN_DIC?`<button class="btn-prim" onclick="EDITD=true;drawModal()"><span class="material-icons" style="font-size:16px">edit</span> Editar dicionário</button>`:`<span class="muted" style="font-size:12.5px">Você não tem permissão para editar o dicionário.</span>`}</div>
    <div class="note">📚 Esta inteligência é o aprendizado por tipo de serviço — levada para a PRÓXIMA obra (sem alterar obras passadas). Editar aqui melhora o De-Para das próximas cargas.</div>`;
  }
  return `
    <div class="fld"><label>Escopo</label><textarea id="dEscopo">${esc(i.escopo||'')}</textarea></div>
    <div class="fld"><label>Variáveis a cotar (template) — separe por " | "</label><textarea id="dVar">${esc(i.variaveis_cotar||'')}</textarea></div>
    <div class="fld"><label>Lições / armadilhas</label><textarea id="dLic">${esc(i.licoes||'')}</textarea></div>
    <div class="fld"><label>Documentos / exigências</label><textarea id="dDoc">${esc(i.documentos||'')}</textarea></div>
    <div style="display:flex;gap:8px"><button class="btn-prim" onclick="dicSalvar()">Salvar dicionário</button>
      <button class="btn-ghost" onclick="EDITD=false;drawModal()">Cancelar</button></div>
    <div class="note">Vale para o tipo de serviço (template). Reflete nas próximas obras; a curadoria das datas/verba/quantitativo continua por obra.</div>`;
}
async function dicSalvar(){
  const dic={ escopo:val('dEscopo'), variaveis_cotar:val('dVar'), licoes:val('dLic'), documentos:val('dDoc') };
  EDITD=false; await saveAndReload({dicionario:dic}); toast('Dicionário atualizado');
}
const val=id=>{const e=document.getElementById(id);return e?e.value:'';};

/* ----- Resumo (status/forn/resp/obs + lead editável) ----- */
function resumoTab(i){
  const st=i.status||'Não Iniciado';
  const TIPOS=['','Material','Mão de obra','Empreitada','Material + MO','Locação'];
  const tp=i.tipo||'';
  const verbaLbl = (i.verba_material!=null||i.verba_mo!=null)
    ? `Material ${BRL(i.verba_material||0)} + MO ${BRL(i.verba_mo||0)}` : BRL(i.verba);
  // blocos read-only (verba/datas/quant são editados nas abas próprias)
  const ro = `
    <div class="fld"><label>Verba ${i.curado_verba?'(curada ✓)':'(estimada)'} ${i.verba_metodo?'· '+esc(i.verba_metodo):''}</label><input value="${esc(verbaLbl)}" disabled></div>
    <div class="grid2">
      <div class="fld"><label>Início da cotação</label><input value="${D(i.inicio_cotacao)}" disabled></div>
      <div class="fld"><label>Fim da cotação <span class="muted" style="text-transform:none;letter-spacing:0;font-weight:400">— prazo de Suprimentos</span></label><input value="${D(i.fim_cotacao)}" disabled></div>
    </div>
    <div class="fld"><label>Necessário em obra ${i.curado_data?'(curado ✓)':''} <span class="muted" style="text-transform:none;letter-spacing:0;font-weight:400">— lead ${i.lead_efetivo??60}d → fim → −30d → início</span></label><input value="${D(i.data_necessaria)}" disabled></div>
    <div class="fld"><label>Quantitativo ${i.curado_quant?'(curado ✓)':''}</label><input value="${i.quantitativo!=null?esc(QNUM(i.quantitativo)+' '+(i.quantitativo_unidade||'')):'—'}" disabled></div>`;

  if(!EDITR){
    // ---------- MODO LEITURA (campos travados) ----------
    return `
      <div class="grid2">
        <div class="fld"><label>Grupo</label><input value="${esc(i.grupo||'—')}" disabled></div>
        <div class="fld"><label>Tipo do item</label><input value="${esc(tp||'— a classificar —')}" disabled></div>
        <div class="fld"><label>Status</label><input value="${esc(st)}" disabled></div>
        <div class="fld"><label>Responsável</label><input value="${esc(i.responsavel||'—')}" disabled></div>
        <div class="fld"><label>Fornecedor</label><input value="${esc(i.fornecedor||'—')}" disabled></div>
        <div class="fld"><label>Lead time (dias)</label><input value="${i.lead_efetivo??'—'}" disabled></div>
      </div>
      ${ro}
      <div class="fld"><label>Observações</label><textarea disabled>${esc(i.observacoes||'')}</textarea></div>
      <div style="margin-top:4px">
        ${CAN_EDIT?`<button class="btn-prim" onclick="EDITR=true;drawModal()"><span class="material-icons" style="font-size:16px;vertical-align:-3px">edit</span> Editar</button>`
                  :`<span class="muted" style="font-size:12.5px"><span class="material-icons" style="font-size:15px;vertical-align:-3px">lock</span> Você tem acesso somente leitura.</span>`}
      </div>
      ${IS_ADMIN?`<div style="margin-top:14px;padding-top:12px;border-top:1px solid var(--line);display:flex;gap:8px">
        <button class="btn-ghost" onclick="desdobrarItem()"><span class="material-icons" style="font-size:15px">call_split</span> Desdobrar em Material + MO</button>
        <button class="btn-ghost" onclick="excluirItem()" style="color:var(--pend)"><span class="material-icons" style="font-size:15px">delete</span> Excluir item</button>
      </div>`:''}`;
  }
  // ---------- MODO EDIÇÃO ---------- (editor geral: status/fornecedor/observações; demais campos = admin)
  const a=IS_ADMIN;
  return `
    ${a?`<div class="fld"><label>Nome do item</label><input id="rNome" value="${esc(i.nome||'')}"></div>`
       :`<div class="fld"><label>Nome do item</label><input value="${esc(i.nome||'')}" disabled></div>`}
    <div class="grid2">
      ${a?`<div class="fld"><label>Grupo <span class="muted" style="text-transform:none;letter-spacing:0;font-weight:400">— mover / criar novo</span></label><select id="rGrupo">${grupoOptions(i.grupo)}</select></div>`
         :`<div class="fld"><label>Grupo</label><input value="${esc(i.grupo||'')}" disabled></div>`}
      ${a?`<div class="fld"><label>Tipo do item</label><select id="rTipo">${TIPOS.map(t=>`<option value="${t}" ${t===tp?'selected':''}>${t||'— a classificar —'}</option>`).join('')}</select></div>`
         :`<div class="fld"><label>Tipo do item</label><input value="${esc(tp||'— a classificar —')}" disabled></div>`}
      <div class="fld"><label>Status</label>
        <select id="rStatus">${STATUSES.map(s=>`<option ${s===st?'selected':''}>${s}</option>`).join('')}</select></div>
      ${a?`<div class="fld"><label>Responsável <span style="color:var(--pend)">*</span></label><select id="rResp">${respOptions(i.responsavel)}</select></div>`
         :`<div class="fld"><label>Responsável</label><input value="${esc(i.responsavel||'—')}" disabled></div>`}
    </div>
    <div class="grid2">
      <div class="fld"><label>Fornecedor</label><input id="rForn" value="${esc(i.fornecedor||'')}" placeholder="fornecedor cotado/contratado"></div>
      ${a?`<div class="fld"><label>Lead time (dias)</label><input id="rLead" type="number" min="0" value="${i.lead_efetivo??''}" placeholder="dias entre disparar e precisar"></div>`
         :`<div class="fld"><label>Lead time (dias)</label><input value="${i.lead_efetivo??''}" disabled></div>`}
    </div>
    ${ro}
    <div class="fld"><label>Observações</label><textarea id="rObs" placeholder="anotações da curadoria…">${esc(i.observacoes||'')}</textarea></div>
    <div style="display:flex;gap:8px"><button class="btn-prim" onclick="resumoSalvar()"><span class="material-icons" style="font-size:16px;vertical-align:-3px">save</span> Salvar</button>
      <button class="btn-ghost" onclick="EDITR=false;drawModal()">Cancelar</button></div>
    <div class="note">${a?'':'Como editor, você altera <b>status, fornecedor e observações</b>. Grupo, tipo, nome, responsável e lead são de administrador. '}Verba, datas e quantitativo são editados nas abas próprias (Orçamento, Cronograma, Quantitativo). Toda alteração fica registrada na aba Histórico.</div>`;
}
async function resumoSalvar(){
  // editor geral salva só status/fornecedor/observações
  const campos={ status:val('rStatus'), fornecedor:val('rForn'), observacoes:val('rObs') };
  if(IS_ADMIN){
    const resp=val('rResp');
    if(!resp){ toast('Responsável é obrigatório'); return; }
    campos.nome=val('rNome'); campos.tipo=val('rTipo'); campos.responsavel=resp;
    const lead=val('rLead');
    if(lead!==String(CUR.lead_efetivo??'')) campos.lead_override=lead; // só grava override se mudou (senão congela o lead do template)
    let g=val('rGrupo');
    if(g==='__novo__'){ g=(prompt('Nome do novo grupo')||'').trim(); if(!g){ toast('Informe o grupo'); return; } }
    if(g) campos.grupo=g;
  }
  EDITR=false;
  await saveAndReload(campos);
  toast('Alterações salvas');
}
/* ----- Histórico de alterações (por item) ----- */
function histTab(i){ return `<div id="histBox"><div class="empty">Carregando histórico…</div></div>`; }
async function loadHist(ordem){
  const box=document.getElementById('histBox'); if(!box)return;
  let d;
  try{ d=await (await fetch('actions/historico.php?ordem='+ordem)).json(); }
  catch(e){ box.innerHTML='<div class="empty">Falha ao carregar o histórico.</div>'; return; }
  const hs=(d&&d.historico)||[];
  if(!hs.length){ box.innerHTML='<div class="muted" style="padding:10px 2px">Nenhuma alteração registrada ainda neste item.</div>'; return; }
  box.innerHTML='<div class="note" style="margin-top:0">Toda alteração feita por qualquer usuário fica registrada aqui (mais recente primeiro).</div>'+
    hs.map(h=>{
      let quando=h.created_at||'';
      try{ quando=new Date(h.created_at).toLocaleString('pt-BR'); }catch(e){}
      const antes=(h.valor_antes!=null&&h.valor_antes!=='')?`<span class="muted">${esc(h.valor_antes)}</span> → `:'';
      return `<div style="padding:9px 2px;border-bottom:1px solid #f1f3f2;font-size:13px">
        <div><b>${esc(h.campo)}</b>: ${antes}<b>${esc(h.valor_depois||'—')}</b></div>
        <div class="muted" style="font-size:11.5px;margin-top:2px"><span class="material-icons" style="font-size:13px;vertical-align:-2px">person</span> ${esc(h.usuario_nome||('#'+h.bitrix_id))} · ${esc(quando)}</div>
      </div>`;
    }).join('');
}
async function modalSave(campo,valor){
  const ok=await saveField(CUR.ordem,campo,valor);
  if(ok){const t=document.getElementById('savedTag');if(t){t.classList.add('show');setTimeout(()=>t.classList.remove('show'),1400);}}
}
async function modalSaveReload(campo,valor){ await saveAndReload({[campo]:valor}); }
async function modalSaveResp(v){
  if(!v){ toast('Responsável é obrigatório'); drawModal(); return; }  // drawModal restaura o select ao valor atual
  await modalSave('responsavel',v);
}
async function modalGrupo(v){
  let g=v;
  if(v==='__novo__'){ g=(prompt('Nome do novo grupo')||'').trim(); if(!g){ drawModal(); return; } }
  await saveAndReload({grupo:g});
  toast('Grupo atualizado');
}
// salva campos e recarrega a matriz (recalcula verba/datas/gatilho no servidor), mantendo o modal aberto
async function saveAndReload(campos){
  try{
    const d=await (await fetch('actions/item_update.php',{method:'POST',headers:{'Content-Type':'application/json'},
      body:JSON.stringify({ordem:CUR.ordem,campos,me:EU&&EU.bitrix_id})})).json();
    if(d.error){toast('Erro: '+d.error);return;}
    VERBA_USOS=null;            // verba mudou → recarrega o mapa de uso na próxima leitura
    await load();
    CUR=byOrdem(CUR.ordem); drawModal();
  }catch(e){toast('Falha ao salvar');}
}
/* ----- Criar / desdobrar / excluir itens ----- */
function novoItem(){
  const grupos=[...new Set(DATA.itens.map(i=>i.grupo).filter(Boolean))];
  const TIPOS=['Material','Mão de obra','Empreitada','Material + MO','Locação'];
  document.getElementById('modal').innerHTML=`
    <div class="mhead"><button class="mclose" onclick="closeModal()">×</button>
      <div class="crumb">Radar de Aquisições</div><div class="mt">Novo item</div></div>
    <div class="tabbody">
      <div class="fld"><label>Nome do serviço</label><input id="niNome" placeholder="ex.: Contrapiso"></div>
      <div class="grid2">
        <div class="fld"><label>Grupo</label><select id="niGrupo">${grupos.map(g=>`<option>${esc(g)}</option>`).join('')}<option value="__novo__">➕ Novo grupo…</option></select></div>
        <div class="fld"><label>Tipo</label><select id="niTipo"><option value="">— a classificar —</option>${TIPOS.map(t=>`<option>${t}</option>`).join('')}</select></div>
      </div>
      <div class="grid2">
        <div class="fld"><label>Responsável <span class="muted" style="text-transform:none;letter-spacing:0;font-weight:400">— opcional, define depois</span></label><select id="niResp">${respOptions('')}</select></div>
        <div class="fld"><label>Curva (opcional)</label><select id="niCurva"><option value="">auto</option><option>A</option><option>B</option><option>C</option></select></div>
      </div>
      <div class="fld"><label>Copiar dicionário de (opcional)</label><select id="niCopy"><option value="">— nenhum —</option>${DATA.itens.map(i=>`<option value="${i.ordem}">${esc(i.nome)}</option>`).join('')}</select></div>
      <div class="note">O sufixo (MAT)/(MO)/(MAT + MO) é adicionado conforme o tipo. Copiar dicionário herda escopo, variáveis a cotar, lições e termos de match.</div>
      <div style="display:flex;gap:8px"><button class="btn-prim" onclick="novoItemSalvar()">Criar item</button>
        <button class="btn-ghost" onclick="closeModal()">Cancelar</button></div>
    </div>`;
  document.getElementById('ov').classList.add('open');
}
async function novoItemSalvar(){
  let grupo=val('niGrupo');
  if(grupo==='__novo__'){ grupo=(prompt('Nome do novo grupo')||'').trim(); if(!grupo){toast('Informe o grupo');return;} }
  const resp=val('niResp');
  const body={acao:'novo', nome:val('niNome'), grupo, tipo:val('niTipo'), curva:val('niCurva'), responsavel:resp, copy_from:val('niCopy')||null, me:EU&&EU.bitrix_id};
  if(!body.nome){toast('Informe o nome');return;}
  // responsável NÃO é obrigatório na criação — pode ser atribuído depois (inclusive em massa por grupo/categoria)
  const d=await (await fetch('actions/item_create.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(body)})).json();
  if(d.error){toast('Erro: '+d.error);return;}
  closeModal(); await load(); toast('Item criado');
}
async function desdobrarItem(){
  if(!CUR)return;
  const d=await (await fetch('actions/item_create.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({acao:'desdobrar',ordem:CUR.ordem,me:EU&&EU.bitrix_id})})).json();
  if(d.error){toast('Erro: '+d.error);return;}
  closeModal(); await load(); toast('Desdobrado em (MAT) e (MO)');
}
async function excluirItem(){
  if(!CUR)return;
  if(!confirm('Excluir o item "'+CUR.nome+'" do radar? Esta ação não pode ser desfeita.'))return;
  const d=await (await fetch('actions/item_create.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({acao:'excluir',ordem:CUR.ordem,me:EU&&EU.bitrix_id})})).json();
  if(d.error){toast('Erro: '+d.error);return;}
  closeModal(); await load(); toast('Item excluído');
}
/* ===== Configuração / Permissões (Bloco 2) ===== */
let CFG={usuarios:[],obras:[]}, NUSER=null;
const MENUS=[['dashboard','Dashboard'],['radar','Radar de Aquisições'],['matriz','Matriz'],['cotacoes','Mapa de Cotações'],['updates','Atualizações'],['config','Configurações']];
const PAPEL_LABEL={admin:'Administrador',diretor:'Diretor',comprador:'Suprimentos',coordenador:'Coordenador',personalizado:'Personalizado'};
const PRESETS={
  admin:{ver:'todas',edit:'todas',menus:['dashboard','radar','matriz','cotacoes','config'],adm:1},
  diretor:{ver:'todas',edit:'nenhuma',menus:['dashboard','radar','matriz','cotacoes'],adm:0},
  comprador:{ver:'todas',edit:'sel',menus:['radar','matriz','cotacoes'],adm:0},
  coordenador:{ver:'sel',edit:'nenhuma',menus:['radar','matriz'],adm:0},
  personalizado:null,
};

async function getCurrentUser(){
  let bid=null, via='fallback';
  const isLocal=(location.hostname==='localhost'||location.hostname==='127.0.0.1');
  if(!isLocal && window.BX24){
    try{ bid=await new Promise(r=>{ BX24.init(()=>BX24.callMethod('user.current',{},x=>r((x.data()||{}).ID))); setTimeout(()=>r(null),5000); }); }catch(e){}
    if(bid) via='bx24';
  }
  if(!bid) bid='20'; // localhost dev OU não identificado → admin Murilo (provisório — ver indicador "Você" na barra)
  try{ const p=await (await fetch('actions/usuarios.php?me='+encodeURIComponent(bid))).json(); EU=Object.assign({bitrix_id:bid,via},p); IS_ADMIN=!!p.perm_admin; }
  catch(e){ EU={bitrix_id:bid,via,autorizado:true,perm_admin:1,editar_escopo:'todas',menus:MENUS.map(m=>m[0])}; IS_ADMIN=true; }
  CAN_EDIT = IS_ADMIN || (EU && (EU.editar_escopo==='todas'
              || (EU.editar_escopo==='sel' && (EU.obras_editar||[]).map(Number).includes(1))));
  // permissões específicas: exigem ser editor da obra (CAN_EDIT) + a flag; admin tem tudo
  CAN_CRONO = IS_ADMIN || (CAN_EDIT && !!(EU && EU.perm_crono));
  CAN_ORC   = IS_ADMIN || (CAN_EDIT && !!(EU && EU.perm_orcamento));
  CAN_QUANT = IS_ADMIN || (CAN_EDIT && !!(EU && EU.perm_quant));
  CAN_DIC   = IS_ADMIN || (CAN_EDIT && !!(EU && EU.perm_dicionario));
  applyMenus(); updateWhoami();
}
function updateWhoami(){
  const el=document.getElementById('whoami'); if(!el)return;
  if(!EU){ el.innerHTML=''; return; }
  const papel = EU.autorizado===false ? 'sem acesso' : (PAPEL_LABEL[EU.papel]||EU.papel||(EU.perm_admin?'Administrador':'—'));
  const ok = EU.via==='bx24';
  el.innerHTML=`<div class="wname">${esc(EU.nome||('Usuário '+EU.bitrix_id))}</div>
    <div>#${esc(EU.bitrix_id)} · ${esc(papel)}</div>
    <div class="wsrc${ok?'':' bad'}">${ok?'identificado via Bitrix':'fallback — não identificado'}</div>`;
}
function applyMenus(){
  const allow = (EU&&EU.autorizado)?(EU.menus||[]):[];
  document.querySelectorAll('.nav a[data-menu]').forEach(a=>{
    const m=a.getAttribute('data-menu');
    a.style.display=(IS_ADMIN||allow.includes(m))?'':'none';
  });
  const bn=document.getElementById('btnNovo'); if(bn) bn.style.display=CAN_EDIT?'':'none'; // só quem edita cria item
}
function toggleSide(){
  const app=document.getElementById('app');
  const c=!app.classList.contains('sidecollapsed');
  app.classList.toggle('sidecollapsed', c);
  try{ localStorage.setItem('sideCollapsed', c?'1':'0'); }catch(e){}
}
async function renderConfig(){
  const box=document.getElementById('cfgwrap'); box.innerHTML='<div class="empty">Carregando…</div>';
  CFG=await (await fetch('actions/usuarios.php')).json();
  if(!CFG.usuarios.length){ box.innerHTML='<div class="empty">Nenhum usuário autorizado ainda. Clique em "Adicionar usuário".</div>'; return; }
  box.innerHTML=`<table><thead><tr><th>Usuário</th><th>Papel</th><th>Vê obras</th><th>Edita</th><th>Menus</th><th>Ativo</th><th></th></tr></thead><tbody>`+
    CFG.usuarios.map(u=>`<tr>
      <td><b>${esc(u.nome)}</b> <span class="muted">#${esc(u.bitrix_id)}</span></td>
      <td><span class="tp-chip tp-mat-mo">${esc(PAPEL_LABEL[u.papel]||u.papel)}</span></td>
      <td>${u.ver_escopo==='todas'?'Todas':((u.obras_ver||[]).length+' selec.')}</td>
      <td>${u.editar_escopo==='todas'?'Todas':u.editar_escopo==='nenhuma'?'—':((u.obras_editar||[]).length+' selec.')}</td>
      <td class="muted">${(u.menus||[]).length}</td>
      <td>${u.ativo?'<span class="mapa-on">● sim</span>':'<span class="muted">não</span>'}</td>
      <td style="white-space:nowrap">
        <button class="eye" onclick="userForm('${esc(u.bitrix_id)}')" title="editar"><span class="material-icons" style="font-size:16px;line-height:28px">edit</span></button>
        <button class="eye" onclick="userDelete('${esc(u.bitrix_id)}')" title="remover" style="color:var(--pend)"><span class="material-icons" style="font-size:16px;line-height:28px">delete</span></button>
      </td></tr>`).join('')+`</tbody></table>`;
}
function userForm(bid){
  const u = bid ? CFG.usuarios.find(x=>String(x.bitrix_id)===String(bid)) : null;
  NUSER = u ? {bitrix_id:u.bitrix_id,nome:u.nome,cargo:u.cargo} : null;
  const papel=u?u.papel:'coordenador';
  const ver=u?u.ver_escopo:'sel', edit=u?u.editar_escopo:'nenhuma';
  const menus=u?(u.menus||[]):PRESETS.coordenador.menus;
  const obrasVer=u?(u.obras_ver||[]):[], obrasEdit=u?(u.obras_editar||[]):[];
  const adm=u?u.perm_admin:0, ativo=u?u.ativo:1;
  const pc=u?u.perm_crono:0, po=u?u.perm_orcamento:0, pq=u?u.perm_quant:0, pd=u?u.perm_dicionario:0;
  const obrasChk=(pref,sel)=>CFG.obras.map(o=>`<label class="ckl"><input type="checkbox" id="${pref}-${o.id}" ${sel.includes(o.id)?'checked':''}> ${esc(o.nome)}</label>`).join('');
  document.getElementById('modal').innerHTML=`
    <div class="mhead"><button class="mclose" onclick="closeModal()">×</button>
      <div class="crumb">Configurações</div><div class="mt">${u?'Editar usuário':'Novo usuário'}</div></div>
    <div class="tabbody">
      ${u?`<div class="box"><div class="bv"><b>${esc(u.nome)}</b> <span class="muted">#${esc(u.bitrix_id)}</span></div></div>`
         :`<div class="fld"><label>Buscar usuário no Bitrix</label>
            <div class="search" style="border:1px solid var(--line)"><span class="material-icons" style="color:var(--muted)">search</span>
              <input id="uQ" placeholder="nome ou ID…" oninput="userBuscar()"></div></div>
          <div id="uRes"></div>
          <div id="uSel" class="box" style="display:none"></div>`}
      <div class="grid2">
        <div class="fld"><label>Papel</label><select id="uPapel" onchange="userPreset()">${Object.keys(PAPEL_LABEL).map(p=>`<option value="${p}" ${p===papel?'selected':''}>${PAPEL_LABEL[p]}</option>`).join('')}</select></div>
        <div class="fld"><label>Ativo</label><select id="uAtivo"><option value="1" ${ativo?'selected':''}>Sim</option><option value="0" ${!ativo?'selected':''}>Não</option></select></div>
      </div>
      <div class="grid2">
        <div class="fld"><label>Vê obras</label><select id="uVer" onchange="userToggleObras()"><option value="todas" ${ver==='todas'?'selected':''}>Todas</option><option value="sel" ${ver==='sel'?'selected':''}>Selecionadas</option></select>
          <div id="uVerObras" class="chkbox" style="display:${ver==='sel'?'block':'none'}">${obrasChk('ov',obrasVer)}</div></div>
        <div class="fld"><label>Edita obras</label><select id="uEdit" onchange="userToggleObras()"><option value="nenhuma" ${edit==='nenhuma'?'selected':''}>Nenhuma (só leitura)</option><option value="todas" ${edit==='todas'?'selected':''}>Todas</option><option value="sel" ${edit==='sel'?'selected':''}>Selecionadas</option></select>
          <div id="uEditObras" class="chkbox" style="display:${edit==='sel'?'block':'none'}">${obrasChk('oe',obrasEdit)}</div></div>
      </div>
      <div class="fld"><label>Menus visíveis</label><div class="chkbox" style="display:flex;flex-wrap:wrap;gap:12px">
        ${MENUS.map(m=>`<label class="ckl"><input type="checkbox" id="mn-${m[0]}" ${menus.includes(m[0])?'checked':''}> ${m[1]}</label>`).join('')}</div></div>
      <div class="fld"><label>Permissões específicas <span class="muted" style="text-transform:none;letter-spacing:0;font-weight:400">— além de editar status / fornecedor / observação</span></label>
        <div class="chkbox" style="display:flex;flex-wrap:wrap;gap:14px">
          <label class="ckl"><input type="checkbox" id="pCrono" ${pc?'checked':''}> Vínculo de cronograma</label>
          <label class="ckl"><input type="checkbox" id="pOrc" ${po?'checked':''}> Vínculo de orçamento (verba)</label>
          <label class="ckl"><input type="checkbox" id="pQuant" ${pq?'checked':''}> Vínculo de quantitativo</label>
          <label class="ckl"><input type="checkbox" id="pDic" ${pd?'checked':''}> Editar dicionário</label>
        </div></div>
      <label class="ckl" style="margin:4px 0 12px"><input type="checkbox" id="uAdmin" ${adm?'checked':''}> É administrador (acessa Configurações e edita tudo)</label>
      <div style="display:flex;gap:8px"><button class="btn-prim" onclick="userSave()">Salvar usuário</button>
        <button class="btn-ghost" onclick="closeModal()">Cancelar</button></div>
    </div>`;
  document.getElementById('ov').classList.add('open');
}
function userPreset(){
  const p=PRESETS[val('uPapel')]; if(!p)return; // 'personalizado' (null) mantém o que está marcado
  document.getElementById('uVer').value=p.ver; document.getElementById('uEdit').value=p.edit;
  document.getElementById('uAdmin').checked=!!p.adm;
  ['pCrono','pOrc','pQuant','pDic'].forEach(id=>{const e=document.getElementById(id); if(e)e.checked=false;}); // presets definidos zeram as específicas
  MENUS.forEach(m=>{const e=document.getElementById('mn-'+m[0]); if(e)e.checked=p.menus.includes(m[0]);});
  userToggleObras();
}
function userToggleObras(){
  document.getElementById('uVerObras').style.display=val('uVer')==='sel'?'block':'none';
  document.getElementById('uEditObras').style.display=val('uEdit')==='sel'?'block':'none';
}
async function userBuscar(){
  const q=val('uQ'); const box=document.getElementById('uRes');
  if(q.length<2){box.innerHTML='';return;}
  box.innerHTML='<div class="muted" style="font-size:12px;padding:4px">Buscando…</div>';
  const d=await (await fetch('actions/bx_users.php?q='+encodeURIComponent(q))).json();
  box.innerHTML='<div class="srbox">'+(d.usuarios||[]).map(u=>`<div class="pickrow" onclick="userPick('${esc(u.id)}','${esc((u.nome||'').replace(/'/g,'’'))}','${esc((u.cargo||'').replace(/'/g,'’'))}')">
    <span class="material-icons" style="font-size:16px;color:var(--verde)">person</span>
    <div><div>${esc(u.nome)} <span class="muted">#${esc(u.id)}</span></div></div></div>`).join('')+'</div>';
}
function userPick(id,nome,cargo){
  NUSER={bitrix_id:id,nome,cargo};
  document.getElementById('uRes').innerHTML=''; document.getElementById('uQ').value='';
  const s=document.getElementById('uSel'); s.style.display='block';
  s.innerHTML=`<div class="bv">Selecionado: <b>${esc(nome)}</b> <span class="muted">#${esc(id)}</span></div>`;
}
async function userSave(){
  if(!NUSER){toast('Escolha um usuário do Bitrix');return;}
  const menus=MENUS.filter(m=>document.getElementById('mn-'+m[0]).checked).map(m=>m[0]);
  const ver=val('uVer'),edit=val('uEdit');
  const obras_ver=ver==='sel'?CFG.obras.filter(o=>document.getElementById('ov-'+o.id).checked).map(o=>o.id):[];
  const obras_editar=edit==='sel'?CFG.obras.filter(o=>document.getElementById('oe-'+o.id).checked).map(o=>o.id):[];
  const body={acao:'save',me:EU&&EU.bitrix_id,bitrix_id:NUSER.bitrix_id,nome:NUSER.nome,cargo:NUSER.cargo,papel:val('uPapel'),
    ver_escopo:ver,editar_escopo:edit,obras_ver,obras_editar,menus,
    perm_admin:document.getElementById('uAdmin').checked?1:0,
    perm_crono:document.getElementById('pCrono').checked?1:0,
    perm_orcamento:document.getElementById('pOrc').checked?1:0,
    perm_quant:document.getElementById('pQuant').checked?1:0,
    perm_dicionario:document.getElementById('pDic').checked?1:0,
    ativo:parseInt(val('uAtivo'))};
  const d=await (await fetch('actions/usuarios.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(body)})).json();
  if(d.error){
    const dbg=d.debug?` · (servidor recebeu me=${JSON.stringify(d.debug.me_recebido)}; eu enviei=${JSON.stringify(EU&&EU.bitrix_id)})`:'';
    console.warn('userSave erro:',d,'EU=',EU); toast('Erro: '+d.error+dbg); return;
  }
  closeModal(); await renderConfig(); toast('Usuário salvo');
}
async function userDelete(bid){
  if(!confirm('Remover o acesso deste usuário?'))return;
  await fetch('actions/usuarios.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({acao:'delete',bitrix_id:bid,me:EU&&EU.bitrix_id})});
  await renderConfig(); toast('Acesso removido');
}

document.addEventListener('keydown',e=>{if(e.key==='Escape')closeModal();});
window.addEventListener('resize',fitRadarHeight);
// serializa: define IS_ADMIN + carrega responsáveis ANTES do 1º render (evita vazar controles admin / select vazio)
(async()=>{ try{ await Promise.all([getCurrentUser(), loadResponsaveis()]); }catch(e){} await load(); })();
</script>
</body>
</html>
