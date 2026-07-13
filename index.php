<?php /* Cockpit de Suprimentos — front. Sem segredos aqui; consome actions/*.php. (republicado) */ ?>
<?php /* build: cotacoes-concorrencia-2026-07-07 */ ?>
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
  .kpi-fill{background:#5c7b8a;border-color:#5c7b8a;box-shadow:0 1px 3px rgba(40,60,70,.18)}
  .kpi-fill .v{color:#fff} .kpi-fill .l{color:#d9e6ec}
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
  .gcur{margin-left:auto;display:inline-flex;align-items:center;gap:6px;flex:0 0 auto;font-weight:700;font-size:11px;text-transform:none;letter-spacing:0;padding:3px 9px;border-radius:999px;border:1px solid var(--line);background:#fff;color:var(--muted);white-space:nowrap}
  .gcur.ok{background:var(--okbg);border-color:#bfe3cc;color:var(--ok)}
  .gcur.mid{background:var(--andbg);border-color:#f0d9af;color:var(--and)}
  .gcur small{font-weight:600;opacity:.85}
  .gbar{display:inline-block;width:44px;height:6px;border-radius:3px;background:rgba(0,0,0,.10);overflow:hidden;flex:0 0 auto}
  .gbar>span{display:block;height:100%;border-radius:3px;background:currentColor}
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
  .editbar-top{position:sticky;top:0;z-index:6;display:flex;gap:8px;align-items:center;background:#f4faf0;border:1px solid var(--ok);border-radius:10px;padding:8px 12px;margin-bottom:12px;box-shadow:0 3px 8px rgba(0,0,0,.07)}
  .savedlg-ov{position:fixed;inset:0;background:rgba(0,0,0,.4);display:flex;align-items:center;justify-content:center;z-index:2000;padding:16px}
  .savedlg{background:#fff;border-radius:14px;padding:20px 22px;max-width:440px;width:100%;box-shadow:0 14px 44px rgba(0,0,0,.28)}
  .savedlg-t{font-weight:800;font-size:15px;margin-bottom:8px}
  .savedlg-m{color:var(--muted);font-size:13px;margin-bottom:16px;line-height:1.5}
  .savedlg-b{display:flex;gap:8px;flex-wrap:wrap}
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
  .pctw{display:inline-flex;align-items:center;gap:4px} .pctbar{width:26px;height:5px;border-radius:4px;background:#e6e9e7;overflow:hidden;display:inline-block} .pctfill{display:block;height:100%} .pctn{font-size:10px;color:var(--muted);font-variant-numeric:tabular-nums}
  .pendbar{font-size:13px;color:var(--verde-d);display:flex;align-items:center;gap:6px}
  .pendbar:empty{display:none}
  .pendbar:not(:empty){background:var(--okbg);border:1px solid var(--ok);border-radius:8px;padding:8px 12px;font-weight:600;margin:6px 0}
  .badge-tp{flex:0 0 auto;font-size:9.5px;font-weight:800;padding:1px 5px;border-radius:5px;min-width:34px;text-align:center}
  .badge-tp.material{background:var(--cotbg);color:var(--cot)} .badge-tp.mo{background:var(--andbg);color:var(--and)}
  .badge-tp.mat_mo{background:#e7f0e2;color:#3a6b2a} .badge-tp.equip{background:#e6eef7;color:#2f5d8f}
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
  .c-empty{background:#fff;cursor:default} .c-empty:hover{outline:none} .cell-x{color:#d3d9d6;font-size:12px;font-weight:700}
  #mobra{min-width:170px;height:auto}
  #mwrap{max-height:calc(100vh - 220px);overflow:auto;border:1px solid var(--line);border-radius:12px}
  /* base .mtable — compartilhada com as tabelas do Mapa de Cotações; o específico da matriz é escopado em #mwrap */
  .mtable{width:100%;border-collapse:separate;border-spacing:0;background:var(--card);border:1px solid var(--line);border-radius:12px;overflow:hidden}
  #mwrap .mtable{border:0;border-radius:0;table-layout:fixed;overflow:visible}   /* colunas iguais + overflow:visible p/ o cabeçalho sticky funcionar (overflow:hidden do .mtable base quebra o sticky) */
  .mtable th{background:#fafbfb;padding:9px 10px;font-size:11px;color:var(--muted);border-bottom:1px solid var(--line);text-align:center;white-space:nowrap}
  #mwrap .mtable th{padding:9px 8px;overflow:hidden;text-overflow:ellipsis;position:sticky;top:0;z-index:3}   /* cabeçalho FIXO só na matriz */
  #mwrap .mtable th:not(.svc-h),#mwrap .mtable td:not(.svc-c){width:118px}   /* TODAS as colunas de obra do MESMO tamanho */
  .mtable th.svc-h{text-align:left;min-width:240px;position:sticky;left:0;background:#fafbfb;z-index:2}
  #mwrap .mtable th.svc-h{min-width:0;width:250px;top:0;z-index:5}
  .mtable td{border-bottom:1px solid #f1f3f2;padding:0}
  .mtable td.svc-c{padding:8px 10px;font-size:13px;position:sticky;left:0;background:#fff;border-right:1px solid var(--line)}
  #mwrap .mtable td.svc-c{z-index:1}
  .mtable tr:hover td.svc-c{background:#f7fbf8}
  .mtable .grp-h td{background:#eef4f0;font-weight:800;color:var(--verde-d);font-size:11.5px;text-transform:uppercase;letter-spacing:.4px;padding:7px 10px;position:sticky;left:0}
  .mo-th{cursor:grab} .mo-th.mo-drag{outline:2px dashed var(--dourado);outline-offset:-2px;background:#fff7e6}
  .mexp-c{padding:6px 8px;vertical-align:top;background:#f7faf8;border-left:1px solid #eef1ef}
  .mexpb{font-size:10px;line-height:1.55;color:var(--txt)} .mexpb b{color:var(--muted);font-weight:600;font-size:8.5px;letter-spacing:.2px;display:block;text-transform:uppercase} .mexpb div{margin-bottom:3px}
  .cell{width:100%;height:34px;cursor:pointer;display:flex;align-items:center;justify-content:center;border-left:1px solid #f1f3f2}
  .cell:hover{outline:2px solid var(--verde);outline-offset:-2px}
  .cell .material-icons{font-size:15px;color:#fff;opacity:.9}
  .cell-off{background:transparent!important;cursor:default}
  .cell-off:hover{outline:none}
  .cell-dt{background:rgba(255,255,255,.72);color:#2f2f2f;font-size:9.5px;font-weight:700;padding:0 5px;border-radius:5px;letter-spacing:.2px;line-height:1.65}
  /* ===== Dashboards ===== */
  .dtabs{display:flex;gap:4px;flex-wrap:wrap;border-bottom:1px solid var(--line);margin-bottom:14px}
  .dtab{padding:9px 15px;font-size:13px;font-weight:700;color:var(--muted);cursor:pointer;border:none;background:none;border-bottom:2px solid transparent;display:inline-flex;align-items:center;gap:6px}
  .dtab .material-icons{font-size:16px}
  .dtab:hover{color:var(--txt)}
  .dtab.on{color:var(--verde-d);border-bottom-color:var(--dourado)}
  .dgrid{display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%,300px),1fr));gap:12px}
  .dcard{background:var(--card);border:1px solid var(--line);border-radius:12px;padding:14px 16px}
  .dcard h3{font-size:12px;font-weight:800;color:var(--verde-d);text-transform:uppercase;letter-spacing:.4px;margin:0 0 10px}
  .dcard.wide{grid-column:1/-1}
  .dcard.col2{grid-column:span 2}
  @media(max-width:900px){.dcard.col2{grid-column:1/-1}}
  .dkpis{display:grid;grid-template-columns:repeat(auto-fit,minmax(120px,1fr));gap:10px;margin-bottom:14px}
  .dkpi{background:var(--card);border:1px solid var(--line);border-radius:12px;padding:12px 14px}
  .dkpi .v{font-size:24px;font-weight:800;color:var(--verde-d);line-height:1.1}
  .dkpi .v.red{color:var(--pend)} .dkpi .v.gold{color:var(--dourado)} .dkpi .v.blue{color:#2b5fa8}
  .dkpi .l{font-size:11px;color:var(--muted);margin-top:3px;line-height:1.3}
  .dtable{width:100%;border-collapse:collapse;font-size:12px}
  .dtable th{text-align:left;color:var(--muted);font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.3px;padding:5px 8px;border-bottom:1px solid var(--line)}
  .dtable td{padding:6px 8px;border-bottom:1px solid #f3f5f4;vertical-align:middle}
  .dtable tr:last-child td{border-bottom:none}
  .dtable td.r,.dtable th.r{text-align:right}
  .drow{display:flex;align-items:center;gap:8px;font-size:12.5px;padding:5px 0}
  .dbar-bg{flex:1;height:8px;background:#eef1f0;border-radius:5px;overflow:hidden}
  .dbar-fi{height:100%;border-radius:5px}
  .dchip{display:inline-block;font-size:10px;font-weight:800;padding:1px 7px;border-radius:20px;color:#fff}
  .dchip.a{background:var(--pend)} .dchip.b{background:var(--dourado)} .dchip.c{background:#8a9299}
  .dleg{display:flex;gap:12px;flex-wrap:wrap;font-size:11px;color:var(--txt);margin-top:8px}
  .dleg span{display:inline-flex;align-items:center;gap:5px}
  .dleg i{width:10px;height:10px;border-radius:3px;display:inline-block}
  .dgm{display:inline-block;width:9px;height:9px;border-radius:50%}
  .dmini{font-size:10.5px;color:var(--muted)}
  .dempty{padding:26px;text-align:center;color:var(--muted);font-size:13px}
  .oracintro{display:flex;align-items:center;justify-content:center;gap:38px;flex-wrap:wrap;width:100%;height:100%;padding:10px 26px;box-sizing:border-box}
  .oracintro-av{flex:0 0 auto}
  .oracintro-av img{height:224px;border-radius:18px;box-shadow:0 10px 30px rgba(0,0,0,.20);display:block}
  .oracintro-tx{flex:1 1 300px;max-width:480px;text-align:left}
  @media(max-width:860px){.oracintro{flex-direction:column;gap:14px;text-align:center;padding:16px 10px;height:auto}.oracintro-tx{text-align:center}.oracintro-av img{height:148px}}
  /* Carta Convite — conferência + PDF/Word */
  .cvdoc{max-width:900px;margin:0 auto;background:#fff;color:#1b221e;font-size:13.5px;line-height:1.6;border:1px solid var(--line);border-radius:6px;overflow:hidden}
  .cvmast{background:linear-gradient(160deg,#1e3a2e,#25493a);color:#eef4ef;padding:24px 34px}
  .cvmast .br{font-weight:800;letter-spacing:.12em;font-size:11px;text-transform:uppercase;color:#cfe0d6}
  .cvmast .kick{margin-top:14px;font-size:11px;font-weight:700;letter-spacing:.3em;text-transform:uppercase;color:#cbb26a}
  .cvmast h2{font-family:Georgia,serif;font-size:23px;margin:4px 0 0;color:#fff;line-height:1.15}
  .cvinfo{display:flex;flex-wrap:wrap;border-bottom:1px solid var(--line)}
  .cvinfo>div{flex:1 1 170px;padding:10px 34px}
  .cvinfo .k{font-size:10px;font-weight:800;letter-spacing:.1em;text-transform:uppercase;color:var(--verde-d)}
  .cvbody{padding:20px 34px 30px}
  .cvsec{margin-top:20px}
  .cvsh{display:flex;align-items:baseline;gap:12px;border-bottom:2px solid #cbb26a;padding-bottom:6px;margin-bottom:10px}
  .cvsn{font-family:Georgia,serif;font-size:24px;font-weight:700;color:#b0862a;line-height:1}
  .cvst{font-size:15px;font-weight:800;color:var(--verde-d)}
  .cvdoc ul{margin:0 0 10px;padding-left:18px} .cvdoc li{margin-bottom:6px}
  .cvdoc table{width:100%;border-collapse:collapse;font-size:12.5px;margin:6px 0}
  .cvdoc th{background:#1e3a2e;color:#fff;text-align:left;padding:6px 9px;font-size:11px;text-transform:uppercase;font-weight:700}
  .cvdoc td{padding:6px 9px;border-bottom:1px solid var(--line);vertical-align:top}
  .cvph{background:#f5eed6;color:#7a5e12;padding:0 4px;border-radius:3px}
  .cvnote{border-left:3px solid #b0862a;background:#f5eed6;padding:9px 13px;border-radius:0 6px 6px 0;color:#5f4b12;font-size:12.5px;margin:6px 0}
  .cvgrid3{display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px}
  .cvcard{border:1px solid var(--line);border-radius:8px;padding:11px 13px} .cvcard h5{margin:0 0 5px;font-size:10.5px;text-transform:uppercase;color:var(--verde-d)} .cvcard p{margin:0;font-size:12px}
  .cvdoc [contenteditable=true]:focus{outline:2px solid rgba(203,178,106,.5);border-radius:3px}
  @media(max-width:640px){.cvmast,.cvbody,.cvinfo>div{padding-left:18px;padding-right:18px}.cvgrid3{grid-template-columns:1fr}}
  .cvdoc,.cvdoc *{-webkit-print-color-adjust:exact;print-color-adjust:exact}
  @media print{ body *{visibility:hidden!important} #cvGerada,#cvGerada *{visibility:visible!important;-webkit-print-color-adjust:exact!important;print-color-adjust:exact!important} #cvGerada{position:absolute;left:0;top:0;width:100%} #cvGerada .cvdoc{border:none;max-width:none;box-shadow:none} .cv-noprint{display:none!important} .cvsec{break-inside:avoid} .cvmast{background:#1e3a2e!important} @page{size:A4;margin:10mm} }
  /* Mapa em UMA PÁGINA (resumo imprimível) */
  .up-tbl{width:100%;border-collapse:collapse;font-size:11px}
  .up-tbl th,.up-tbl td{border:1px solid #e3e8e6;padding:5px 7px;text-align:center;vertical-align:top}
  .up-tbl thead th{background:#f0f4f2;font-size:10px;text-transform:uppercase;letter-spacing:.3px;color:#556}
  @media print{
    body *{visibility:hidden!important}
    #cotUmaPagina, #cotUmaPagina *{visibility:visible!important}
    #cotUmaPagina{position:absolute;left:0;top:0;width:100%}
    #cotUmaPagina div{overflow:visible!important}   /* não clipar tabelas na impressão */
    .up-noprint{display:none!important}
    .up-tbl{font-size:9px} .up-tbl th,.up-tbl td{padding:3px 5px}
    .up-tbl tr,.up-tbl thead{break-inside:avoid}
    #cotUmaPagina{page:upland}   /* SÓ a uma-página é paisagem (named page); o @page padrão (retrato) vale p/ carta e demais */
    @page upland{size:A4 landscape;margin:8mm}
  }
  .gantt-row{display:grid;grid-template-columns:130px 1fr;gap:8px;align-items:center;margin-bottom:7px;font-size:11.5px}
  .gantt-track{position:relative;height:16px;background:#f1f4f3;border-radius:8px}
  .gantt-bar{position:absolute;top:0;height:16px;border-radius:8px;opacity:.9}
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
      <a id="nav-dashboards" data-menu="dashboard" title="Dashboards" onclick="showView('dashboards')"><span class="material-icons">dashboard</span> <span class="navtxt">Dashboards</span></a>
      <a id="nav-radar" data-menu="radar" class="active" title="Radar de Aquisições" onclick="showView('radar')"><span class="material-icons">radar</span> <span class="navtxt">Radar de Aquisições</span></a>
      <a id="nav-matriz" data-menu="matriz" title="Matriz" onclick="showView('matriz')"><span class="material-icons">grid_on</span> <span class="navtxt">Matriz</span></a>
      <a id="nav-cotacoes" data-menu="cotacoes" title="Cotações" onclick="showView('cotacoes')"><span class="material-icons">request_quote</span> <span class="navtxt">Cotações</span></a>
      <a id="nav-solicitacoes" data-menu="solicitacoes" title="Solicitações de Compra" onclick="showView('solicitacoes')"><span class="material-icons">inbox</span> <span class="navtxt">Solicitações</span></a>
      <a id="nav-obras" data-menu="obras" title="Obras — ficha, características e de-para" onclick="showView('obras')"><span class="material-icons">apartment</span> <span class="navtxt">Obras</span></a>
      <a id="nav-oraculo" data-menu="oraculo" title="Radar IA — oráculo de suprimentos" onclick="showView('oraculo')"><span class="material-icons">auto_awesome</span> <span class="navtxt">Radar IA</span></a>
    </nav>
    <div class="navlabel">Administração</div>
    <nav class="nav">
      <a id="nav-oportunidades" data-menu="oportunidades" title="Oportunidades (Curva ABC)" onclick="showView('oportunidades')"><span class="material-icons">insights</span> <span class="navtxt">Oportunidades</span></a>
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
      </div>
      <div style="display:flex;gap:8px;flex:0 0 auto;margin-top:4px">
        <button class="btn-ghost" onclick="recarregar()" title="Recarregar do servidor — evita trabalhar com dado que outra pessoa já curou"><span class="material-icons" style="font-size:18px">refresh</span> Atualizar</button>
        <button id="btnNovo" class="btn-prim" onclick="novoItem()"><span class="material-icons" style="font-size:18px">add</span> Novo item</button>
      </div>
    </div>
    <div class="kpis" id="kpis"></div>

    <div class="panel" style="margin-top:8px">
      <div class="bar" style="padding:8px 12px;gap:8px">
        <div id="obraPick" style="position:relative;flex:0 0 auto">
          <button type="button" id="obraPickBtn" onclick="obraMenuToggle(event)" title="Selecionar obra(s) a exibir"
            style="display:flex;align-items:center;gap:6px;border:1.5px solid var(--verde);border-radius:10px;padding:6px 12px;background:#f6faf6;cursor:pointer;font-weight:800;color:var(--verde-d);font-size:12.5px;white-space:nowrap">
            <span>🏗️</span><span id="obraPickLbl">Trinity</span>
            <span class="material-icons" style="font-size:18px">expand_more</span>
          </button>
          <div id="obraMenu" style="display:none;position:absolute;top:calc(100% + 5px);left:0;z-index:60;background:#fff;border:1px solid var(--line);border-radius:11px;box-shadow:0 10px 28px rgba(0,0,0,.16);padding:7px;min-width:250px;max-height:340px;overflow:auto"></div>
        </div>
        <div class="search" style="min-width:180px"><span class="material-icons" style="color:var(--muted)">search</span>
          <input id="q" placeholder="Buscar item, contratação ou responsável…" oninput="render()"></div>
        <label class="toggle" style="gap:6px">Ver
          <select id="fview" onchange="render()"><option value="agrupado">Agrupado</option><option value="lista">Lista</option></select></label>
        <span class="toggle" style="gap:4px;color:var(--muted)"><span class="material-icons" style="font-size:15px">swap_vert</span>clique numa coluna p/ ordenar</span>
        <button class="btn-ghost" id="filtBtn" onclick="toggleFiltros()"><span class="material-icons" style="font-size:16px;vertical-align:-3px">tune</span> Filtros<span id="filtBadge"></span></button>
        <button class="btn-ghost" id="collapseBtn" onclick="toggleAllGroups()" style="margin-left:auto"></button>
      </div>
      <div class="bar" id="advFilters" style="padding:0 12px 10px;gap:8px;display:none">
        <select id="fgrupo" onchange="render()"><option value="">Todos os grupos</option></select>
        <select id="fcurva" onchange="render()"><option value="">Todas as curvas</option><option>A</option><option>B</option><option>C</option></select>
        <select id="fstatus" onchange="render()"><option value="">Todos os status</option></select>
        <select id="fresp" onchange="render()"><option value="">Todos os responsáveis</option></select>
        <label class="toggle"><input type="checkbox" id="onlyalert" onchange="render()"> Somente em alerta</label>
        <select id="fcurada" onchange="render()" title="filtrar pela verba curada"><option value="">Verba: todas</option><option value="sim">Só curadas ✓</option><option value="nao">Só não curadas</option></select>
        <select id="fcrono" onchange="render()" title="filtrar pelo cronograma curado"><option value="">Cronograma: todos</option><option value="sim">Só curados ✓</option><option value="nao">Só não curados</option></select>
        <select id="fquant" onchange="render()" title="filtrar pelo quantitativo curado"><option value="">Quantitativo: todos</option><option value="sim">Só curados ✓</option><option value="nao">Só não curados</option></select>
        <select id="frespo" onchange="render()" title="filtrar pelo responsável"><option value="">Responsável: todos</option><option value="com">Com responsável</option><option value="sem">Sem responsável</option><option value="naocad">Não cadastrado</option></select>
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
      <div class="bar" id="mlegend" style="gap:16px;flex-wrap:wrap"></div>
    </div>
    <div class="panel">
      <div class="bar" style="flex-wrap:wrap;gap:8px">
        <div id="matObraPick" style="position:relative">
          <button type="button" class="btn-ghost" onclick="matObraToggle(event)" style="min-width:150px;display:inline-flex;align-items:center;gap:8px;justify-content:space-between;padding:7px 11px">
            <span style="display:inline-flex;align-items:center;gap:5px"><span class="material-icons" style="font-size:15px;color:var(--dourado)">apartment</span> <span id="matObraLbl">Todas as obras</span></span>
            <span style="font-size:10px;color:var(--muted)">▾</span>
          </button>
          <div id="matObraMenu" style="display:none;position:absolute;top:100%;left:0;z-index:60;background:#fff;border:1px solid var(--line);border-radius:10px;box-shadow:0 8px 24px rgba(0,0,0,.14);min-width:210px;padding:6px;margin-top:3px"></div>
        </div>
        <select id="mgrupo" onchange="renderMatriz()"><option value="">Todos os grupos</option></select>
        <select id="mcurva" onchange="renderMatriz()"><option value="">Todas as curvas</option><option>A</option><option>B</option><option>C</option></select>
        <select id="mstatus" onchange="renderMatriz()"><option value="">Todos os status</option></select>
        <select id="mresp" onchange="renderMatriz()"><option value="">Todos os responsáveis</option><option value="__sem__">— sem responsável —</option></select>
        <label class="ckl" style="font-size:12px"><input type="checkbox" id="malert" onchange="renderMatriz()"> só em alerta</label>
        <span style="width:1px;height:22px;background:var(--line);align-self:center"></span>
        <label class="muted" style="font-size:12px;align-self:center">Colorir <select id="mcolor" onchange="renderMatriz()" style="margin-left:4px"><option value="status">Status</option><option value="prazo">Prazo de cotação</option></select></label>
        <label class="muted" style="font-size:12px;align-self:center">Organizar <select id="morder" onchange="renderMatriz()" style="margin-left:4px"><option value="grupo">Por grupo</option><option value="prazo">Por prazo (urgente 1º)</option><option value="nome">Por nome</option></select></label>
        <div class="search"><span class="material-icons" style="color:var(--muted)">search</span>
          <input id="mq" placeholder="Filtrar serviço…" oninput="renderMatriz()"></div>
      </div>
    </div>
    <div id="mctrl" style="margin:0 26px 8px"></div>
    <div class="wrap" id="mwrap"></div>
   </section>

   <section id="view-oportunidades" style="display:none">
    <div class="top">
      <h1 class="h1"><span class="material-icons" style="color:var(--dourado)">insights</span> Oportunidades — Curva ABC</h1>
      <p class="sub">Grandes itens do orçamento que o radar ainda NÃO cobre — agrupe os parecidos e transforme num item de aquisição.</p>
    </div>
    <div class="panel" style="margin-bottom:8px">
      <div class="bar" style="gap:10px;flex-wrap:wrap;align-items:center">
        <label class="muted" style="font-size:12px">Obra <select id="opObra" onchange="opLoad()" style="margin-left:6px"></select></label>
        <select id="opCurva" onchange="opRender()"><option value="">Curva A + B + C</option><option value="A">Só curva A</option><option value="AB">Curva A + B</option></select>
        <select id="opGrupo" onchange="opRender()"><option value="">Todos os grupos</option></select>
        <div class="search" style="min-width:180px"><span class="material-icons" style="color:var(--muted)">search</span><input id="opQ" placeholder="Buscar descrição…" oninput="opRender()"></div>
      </div>
      <div id="opKpis" class="kpis" style="padding:10px 0 0"></div>
    </div>
    <div class="panel" style="margin-bottom:8px">
      <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap">
        <b id="opSel" style="font-size:13px">0 selecionados · R$ 0</b>
        <span class="muted" style="font-size:11.5px">— marque os itens do orçamento abaixo e escolha:</span>
      </div>
      <div style="display:flex;gap:18px;flex-wrap:wrap;margin-top:10px;align-items:flex-end">
        <div>
          <div style="font-size:10px;font-weight:800;letter-spacing:.5px;color:var(--muted);margin-bottom:4px">VINCULAR A UM ITEM QUE JÁ EXISTE</div>
          <div style="display:flex;gap:6px;align-items:center">
            <div style="position:relative">
              <input id="opItemBusca" oninput="opItemBuscaInput()" placeholder="Buscar item do radar (ex.: forma pronta)…" style="width:280px" autocomplete="off">
              <div id="opItemSug" style="display:none;position:absolute;top:100%;left:0;right:0;z-index:60;background:#fff;border:1px solid var(--line);border-radius:8px;box-shadow:0 6px 20px rgba(0,0,0,.14);max-height:280px;overflow:auto;margin-top:2px"></div>
            </div>
            <button class="btn-prim" style="padding:6px 12px" onclick="opVincular()"><span class="material-icons" style="font-size:15px;vertical-align:-3px">link</span> Vincular</button>
          </div>
        </div>
        <div style="width:1px;align-self:stretch;background:var(--line)"></div>
        <div>
          <div style="font-size:10px;font-weight:800;letter-spacing:.5px;color:var(--muted);margin-bottom:4px">OU CRIAR UM ITEM NOVO</div>
          <div style="display:flex;gap:6px;align-items:center;flex-wrap:wrap">
            <input id="opNome" placeholder="Nome (ex.: Esquadrias de Alumínio)" style="width:225px">
            <input id="opGrupoNovo" list="opGrupos" placeholder="Grupo" style="width:150px" autocomplete="off"><datalist id="opGrupos"></datalist>
            <select id="opCurvaNovo" title="curva ABC"><option>A</option><option>B</option><option>C</option></select>
            <button class="btn-ghost" style="padding:6px 12px" onclick="opCriar()"><span class="material-icons" style="font-size:15px;vertical-align:-3px">add_task</span> Criar</button>
          </div>
        </div>
      </div>
    </div>
    <div class="wrap" id="opwrap"><div class="empty">Selecione uma obra.</div></div>
   </section>

   <section id="view-dashboards" style="display:none">
    <div class="top">
      <h1 class="h1"><span class="material-icons" style="color:var(--dourado)">dashboard</span> Dashboards</h1>
      <p class="sub" id="dsub">Visão consolidada das obras — cotações, riscos, exposição e oportunidades.</p>
    </div>
    <div class="panel" style="margin-bottom:10px">
      <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px">
        <div class="dtabs" id="dtabs" style="border:none;margin:0"></div>
        <div class="dmini" id="dmeta">—</div>
      </div>
    </div>
    <div id="dwrap"><div class="dempty">Carregando…</div></div>
   </section>

   <section id="view-solicitacoes" style="display:none">
    <div class="head">
      <h1 class="h1"><span class="material-icons" style="color:var(--dourado)">inbox</span> Solicitações de Compra</h1>
      <p class="sub">Fila de solicitações pendentes (TOTVS, ao vivo) — priorize os atrasos, atribua compradores e vire cotação com 1 clique.</p>
    </div>
    <div class="dtabs" style="padding:0 26px">
      <button class="dtab on" id="stab-dashboard" onclick="solTab('dashboard')"><span class="material-icons">insights</span> Painel</button>
      <button class="dtab" id="stab-lista" onclick="solTab('lista')"><span class="material-icons">list_alt</span> Solicitações</button>
      <button class="dtab" id="stab-obras" onclick="solTab('obras')"><span class="material-icons">apartment</span> Obras &amp; compradores</button>
    </div>
    <div id="solwrap"><div class="dempty">Carregando…</div></div>
   </section>
   <section id="view-obras" style="display:none">
    <div class="top" style="display:flex;justify-content:space-between;align-items:flex-start;gap:12px">
      <div><h1 class="h1"><span class="material-icons" style="color:var(--dourado)">apartment</span> Obras</h1>
        <div class="muted" style="font-size:12.5px;margin-top:-4px">Ficha das obras — características, endereço, comprador e o de-para entre os sistemas (radar · TOTVS · solicitações).</div></div>
    </div>
    <div id="obrasWrap"><div class="dempty">Carregando…</div></div>
   </section>
   <section id="view-cotacoes" style="display:none">
    <div class="top">
      <h1 class="h1"><span class="material-icons" style="color:var(--dourado)">request_quote</span> Cotações</h1>
      <p class="sub">Monte a concorrência: itens a cotar → propostas dos fornecedores → mapa comparativo (melhor preço por item).</p>
    </div>
    <div class="dtabs" id="cottabs" style="margin-bottom:12px">
      <button class="dtab on" id="ctab-cotacoes" onclick="cotTab('cotacoes')"><span class="material-icons">request_quote</span> Cotações</button>
      <button class="dtab" id="ctab-fornecedores" onclick="cotTab('fornecedores')"><span class="material-icons">groups</span> Fornecedores</button>
      <button class="dtab" id="ctab-cartas" onclick="cotTab('cartas')"><span class="material-icons">description</span> Modelos de carta</button>
      <button class="dtab" id="ctab-precos" onclick="cotTab('precos')"><span class="material-icons">sell</span> Preços tabelados</button>
    </div>
    <div id="cotwrap"><div class="dempty">Carregando…</div></div>
   </section>

   <section id="view-oraculo" style="display:none">
    <div class="top">
      <h1 class="h1"><span class="material-icons" style="color:var(--dourado)">auto_awesome</span> Radar IA <span style="font-size:12.5px;font-weight:600;color:var(--muted);letter-spacing:0">· oráculo de suprimentos</span></h1>
      <p class="sub">Pergunte sobre a sua programação, cotações, prazos e oportunidades — a IA analisa os dados do cockpit e responde.</p>
    </div>
    <div id="oracwrap"><div class="dempty">Carregando…</div></div>
   </section>

   <section id="view-config" style="display:none">
    <div class="top" style="display:flex;justify-content:space-between;align-items:flex-start;gap:12px">
      <div>
        <h1 class="h1"><span class="material-icons" style="color:var(--dourado)">settings</span> Configurações</h1>
        <p class="sub">Área administrativa — acesso, permissões e o dicionário de aprendizado das obras.</p>
      </div>
      <button class="btn-prim" id="cfgAddBtn" onclick="userForm()" style="flex:0 0 auto;margin-top:4px"><span class="material-icons" style="font-size:18px">person_add</span> Adicionar usuário</button>
    </div>
    <div class="bar" style="gap:6px;padding:0 2px 8px">
      <button class="btn-ghost" id="cfgtab-users" onclick="cfgTab('users')" style="padding:6px 14px">👥 Usuários &amp; Permissões</button>
      <button class="btn-ghost" id="cfgtab-resp" onclick="cfgTab('resp')" style="padding:6px 14px">🛒 Responsáveis</button>
      <button class="btn-ghost" id="cfgtab-receitas" onclick="cfgTab('receitas')" style="padding:6px 14px">📚 Aprendizado (receitas)</button>
      <button class="btn-ghost" id="cfgtab-email" onclick="cfgTab('email')" style="padding:6px 14px">📧 E-mail (disparo)</button>
    </div>
    <div id="cfg-email" style="display:none"><div class="wrap" id="cfgEmailWrap"></div></div>
    <div id="cfg-users">
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
    </div>
    <div id="cfg-receitas" style="display:none">
      <style>
        #cfg-receitas .rcrule{border:1px solid var(--line,#e6ebe6);border-radius:8px;padding:10px 12px;margin-bottom:8px;background:#fff}
        #cfg-receitas .rchead{display:flex;align-items:center;gap:7px;font-weight:700;font-size:12.5px;margin-bottom:8px}
        #cfg-receitas .rchead .material-icons{font-size:18px}
        #cfg-receitas .rclab{display:block;font-size:11.5px;color:var(--muted,#6b7280)}
        #cfg-receitas .rclab input,#cfg-receitas .rclab select,#cfg-receitas .rclab textarea{display:block;width:100%;margin-top:3px;font-size:13px}
        #cfg-receitas .rcpick{position:relative;display:inline-block}
        #cfg-receitas .rcmenu{position:absolute;top:100%;right:0;margin-top:4px;background:#fff;border:1px solid var(--line,#e6ebe6);border-radius:8px;box-shadow:0 6px 20px rgba(0,0,0,.12);min-width:190px;z-index:50;padding:4px;max-height:340px;overflow:auto}
        #cfg-receitas .rcmi{padding:7px 10px;border-radius:6px;cursor:pointer;font-size:12.5px}
        #cfg-receitas .rcmi:hover{background:#eff7f1}
      </style>
      <div class="panel">
        <div class="bar" style="justify-content:space-between;flex-wrap:wrap;gap:10px;align-items:center">
          <div class="search" style="flex:1;min-width:200px"><span class="material-icons" style="color:var(--muted)">search</span>
            <input id="rcq" placeholder="Buscar item…" oninput="renderReceitas()"></div>
          <div class="bar" style="gap:8px;align-items:center;flex-wrap:wrap">
            <select id="rcmetodo" onchange="rcMetodoChange()" title="Variante de método construtivo" style="max-width:230px"></select>
            <button class="btn-ghost" style="padding:6px 12px" onclick="rcNovoItem()"><span class="material-icons" style="font-size:16px;vertical-align:-3px">add</span> Novo item</button>
            <div class="rcpick"><button class="btn-ghost" style="padding:6px 12px" onclick="rcMenu('aprender',event)"><span class="material-icons" style="font-size:16px;vertical-align:-3px">school</span> Aprender de uma obra <span class="material-icons" style="font-size:15px;vertical-align:-3px">expand_more</span></button><div id="rcmenu-aprender" class="rcmenu" style="display:none"></div></div>
            <div class="rcpick"><button class="btn-prim" style="padding:6px 12px" onclick="rcMenu('aplicar',event)"><span class="material-icons" style="font-size:16px;vertical-align:-3px;color:var(--dourado)">smart_toy</span> Aplicar em uma obra <span class="material-icons" style="font-size:15px;vertical-align:-3px">expand_more</span></button><div id="rcmenu-aplicar" class="rcmenu" style="display:none"></div></div>
          </div>
        </div>
      </div>
      <div class="wrap" id="rcwrap"><div class="empty">Carregando…</div></div>
    </div>
    <div id="cfg-resp" style="display:none">
      <div class="panel">
        <div class="bar" style="gap:10px;flex-wrap:wrap;align-items:center">
          <div id="rlObraPick" style="position:relative">
            <button type="button" class="btn-ghost" onclick="rlObraToggle(event)" style="min-width:150px;display:inline-flex;align-items:center;gap:8px;justify-content:space-between;padding:7px 11px">
              <span style="display:inline-flex;align-items:center;gap:5px"><span class="material-icons" style="font-size:15px;color:var(--dourado)">apartment</span> <span id="rlObraLbl">Obras</span></span>
              <span style="font-size:10px;color:var(--muted)">▾</span>
            </button>
            <div id="rlObraMenu" style="display:none;position:absolute;top:100%;left:0;z-index:60;background:#fff;border:1px solid var(--line);border-radius:10px;box-shadow:0 8px 24px rgba(0,0,0,.14);min-width:220px;padding:6px;margin-top:3px"></div>
          </div>
          <select id="rlGrupo" onchange="rlRender()"><option value="">Todos os grupos</option></select>
          <select id="rlStatus" onchange="rlRender()"><option value="">Todos</option><option value="sem">Sem responsável</option><option value="com">Com responsável</option></select>
          <div class="search" style="min-width:180px"><span class="material-icons" style="color:var(--muted)">search</span><input id="rlQ" placeholder="Buscar item…" oninput="rlRender()"></div>
        </div>
        <div id="rlKpi" class="kpis" style="margin-top:10px"></div>
      </div>
      <div class="panel" style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;margin-bottom:8px">
        <b id="rlSelCount" style="font-size:13px">0 selecionados</b>
        <label id="rlPadraoWrap" class="ckl" style="display:none;font-size:12px" title="Também grava como padrão do serviço — obras novas já nascem com esse responsável"><input type="checkbox" id="rlPadrao"> tornar padrão (novas obras herdam)</label>
        <span style="flex:1"></span>
        <button class="btn-ghost" style="padding:6px 12px" onclick="rlPreencherPadrao()" title="Preenche os itens SEM responsável com o padrão do serviço"><span class="material-icons" style="font-size:15px;vertical-align:-3px">auto_fix_high</span> Preencher vazios c/ padrão</button>
        <select id="rlResp" style="min-width:210px"></select>
        <button class="btn-prim" style="padding:6px 12px" onclick="rlAtribuir()"><span class="material-icons" style="font-size:15px;vertical-align:-3px">how_to_reg</span> Atribuir aos selecionados</button>
        <button class="btn-ghost" style="padding:6px 12px" onclick="rlLimpar()">Limpar responsável</button>
      </div>
      <div class="wrap" id="rlwrap"><div class="empty">Selecione uma obra.</div></div>
    </div>
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
const BRL=n=>n?Number(n).toLocaleString('pt-BR',{style:'currency',currency:'BRL',minimumFractionDigits:2,maximumFractionDigits:2}):'—';
// Moeda BR: número -> "1.500.000,00" (sem R$, p/ inputs/totais). fmtMoney(0) mostra "0,00".
const fmtMoney=n=>(n===0||n)?Number(n).toLocaleString('pt-BR',{minimumFractionDigits:2,maximumFractionDigits:2}):'';
// Lê o valor de um input mascarado (pt-BR: ponto=milhar, vírgula=centavo) -> número (ou null).
function parseBRLInput(s){ if(s==null)return null; s=String(s).replace(/[^\d,]/g,''); if(s==='')return null; s=s.replace(/\./g,'').replace(',','.'); const n=Number(s); return isFinite(n)?n:null; }
// Máscara AO VIVO de moeda num <input type=text inputmode=decimal>: 150000 -> 150.000 ; 150000,5 -> 150.000,5
function maskMoneyInput(el){ let v=el.value.replace(/[^\d,]/g,''); const k=v.indexOf(','); let ip,dp=null;
  if(k>=0){ ip=v.slice(0,k).replace(/\D/g,''); dp=v.slice(k+1).replace(/\D/g,'').slice(0,2); } else ip=v.replace(/\D/g,'');
  ip=ip.replace(/^0+(?=\d)/,''); const ii=ip===''?(dp!==null?'0':''):Number(ip).toLocaleString('pt-BR'); el.value=ii+(dp!==null?(','+dp):''); }
// Ao sair do campo, normaliza p/ 2 casas: "150.000" -> "150.000,00"
function moneyBlur(el){ const n=parseBRLInput(el.value); el.value=(n==null)?'':fmtMoney(n); }
// Moeda COMPACTA p/ espaços apertados (donut/badges): R$ 4,2 mi ; R$ 350 mil ; senão o valor cheio.
const BRLc=n=>{n=Number(n)||0;const a=Math.abs(n);if(a>=1e6)return 'R$ '+(n/1e6).toLocaleString('pt-BR',{maximumFractionDigits:1})+' mi';if(a>=1e3)return 'R$ '+(n/1e3).toLocaleString('pt-BR',{maximumFractionDigits:0})+' mil';return BRL(n);};
const D=s=>{if(!s)return'—';const p=String(s).split('-');return p.length===3?p[2]+'/'+p[1]+'/'+p[0]:s;};
const esc=s=>(s==null?'':String(s)).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
// classe do insumo: material | mo | mat_mo (material+MO) | equip (equipamento)
const TP_LABEL={mo:'MO', mat_mo:'M+MO', equip:'EQUIP', material:'MAT'};
const TP_FULL={mo:'Mão de obra', mat_mo:'Material + MO', equip:'Equipamento', material:'Material'};
const tpCls=t=>(['mo','mat_mo','equip','material'].includes(t)?t:'material');
const tpLabel=t=>TP_LABEL[t]||'MAT';
const tpBadge=t=>`<span class="badge-tp ${tpCls(t)}">${tpLabel(t)}</span>`;
function tpSubtotais(list){ const t={material:0,mo:0,mat_mo:0,equip:0}; let total=0;
  (list||[]).forEach(s=>{ const c=(s.area||0)*(s.coef||0)*(s.rs_unit||0); t[tpCls(s.tipo)]+=c; total+=c; }); return {t,total}; }
function tpSubHtml(list){ const {t,total}=tpSubtotais(list); const p=[];
  ['material','mo','mat_mo','equip'].forEach(k=>{ if(t[k]>0.5) p.push('<b>'+TP_FULL[k]+':</b> '+BRL(t[k])); });
  return p.join(' &nbsp;·&nbsp; ')+' &nbsp;·&nbsp; <b>Total:</b> '+BRL(total); }
const today=new Date().toISOString().slice(0,10);
const STK={'Finalizado':'st-Finalizado','Cotação Iniciada':'st-CotacaoIniciada','Com Pendências':'st-ComPendencias','Em Andamento':'st-EmAndamento','Não Iniciado':'st-NaoIniciado'};
const STATUSES=['Não Iniciado','Cotação Iniciada','Com Pendências','Em Andamento','Finalizado'];
function toast(m){const t=document.getElementById('toastEl');t.textContent=m;t.style.display='block';clearTimeout(t._);t._=setTimeout(()=>t.style.display='none',2400);}
// multi-obra: a mesma ordem existe em 2+ obras. Procura no RADAR (DATA.itens = só OBRA_SEL) e, se não achar,
// cai na MATRIZ (MAT = TODAS as obras) — assim clicar numa célula da matriz abre o item mesmo se a obra
// não estiver selecionada no radar (as duas telas são independentes).
const byOrdem=(o,ob)=>DATA.itens.find(i=>i.ordem==o && (ob==null || i.obra_id==ob))
  || ((typeof MAT!=='undefined' && MAT) ? MAT.find(i=>i.ordem==o && (ob==null || i.obra_id==ob)) : null);
const OBQ=()=>((CUR&&CUR.obra_id)||OBRA_SEL[0]||1);   // obra do MODAL aberto (ou a primária) — vai em todo fetch do modal
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

/* ===== multi-obra: seleção de obras (chips) — 1 obra por default, persiste no navegador ===== */
let OBRAS=[];                                    // todas as obras do sistema [{id,nome,codinome,...}]
let OBRA_SEL=(()=>{ try{ const v=JSON.parse(localStorage.getItem('sup_obras')||'[1]'); return (Array.isArray(v)&&v.length)?v.map(Number):[1]; }catch(e){ return [1]; } })();
let MAT=null;   // dataset da MATRIZ = TODAS as obras (independente do OBRA_SEL do Radar) — carregado sob demanda
const OBRA_CORES={1:'var(--verde)',2:'#2b5fa8',3:'#7b5ea7',4:'#b5651d',5:'#3a3a3a'};      // cor por obra (badge/chip)
function obraCor(id){ return OBRA_CORES[id]||'#555'; }
// dropdown de obras: abre/fecha o menu de checkboxes
function obraMenuToggle(e){ if(e) e.stopPropagation(); const m=document.getElementById('obraMenu'); if(!m)return;
  const abrir=m.style.display==='none'||!m.style.display; m.style.display=abrir?'block':'none'; if(abrir) obraMenuRender(); }
document.addEventListener('click',e=>{ const p=document.getElementById('obraPick'), m=document.getElementById('obraMenu');
  if(m && m.style.display==='block' && p && !p.contains(e.target)) m.style.display='none'; });
function obraMenuRender(){
  const m=document.getElementById('obraMenu'); if(!m)return;
  m.innerHTML=`<div style="display:flex;justify-content:space-between;align-items:center;padding:2px 6px 6px;border-bottom:1px solid var(--line);margin-bottom:4px">
      <span style="font-size:10px;font-weight:800;letter-spacing:.6px;color:var(--muted)">SELECIONE 1 OU MAIS OBRAS</span>
      <button class="btn-ghost" style="padding:2px 8px;font-size:11px" onclick="obraSelTodas(event)">Todas</button></div>`+
    (OBRAS.length?OBRAS.map(o=>{ const on=OBRA_SEL.includes(Number(o.id));
      return `<label style="display:flex;align-items:center;gap:9px;padding:6px 8px;border-radius:7px;cursor:pointer;font-size:12.5px" onmouseover="this.style.background='#eff7f1'" onmouseout="this.style.background=''">
        <input type="checkbox" ${on?'checked':''} onchange="obraSet(${o.id},this.checked)">
        <span style="width:9px;height:9px;border-radius:50%;background:${obraCor(o.id)};flex:0 0 auto"></span>
        <span style="flex:1"><b>${esc(o.nome)}</b></span>
      </label>`; }).join(''):'<div class="muted" style="padding:8px;font-size:12px">carregando…</div>');
}
function obraSet(id,checked){
  id=Number(id);
  if(!checked){ if(OBRA_SEL.length===1){ toast('Pelo menos uma obra selecionada'); obraMenuRender(); return; } OBRA_SEL=OBRA_SEL.filter(x=>x!==id); }
  else if(!OBRA_SEL.includes(id)) OBRA_SEL=[...OBRA_SEL,id].sort((a,b)=>a-b);
  localStorage.setItem('sup_obras',JSON.stringify(OBRA_SEL));
  load();
}
function obraSelTodas(e){ if(e) e.stopPropagation(); if(!OBRAS.length)return;
  OBRA_SEL=OBRAS.map(o=>Number(o.id)); localStorage.setItem('sup_obras',JSON.stringify(OBRA_SEL)); load(); }
// atualiza o rótulo do botão + o menu (se aberto) — chamado no fim do load()
function obraUpdateUI(){
  const lbl=document.getElementById('obraPickLbl');
  if(lbl){ const nomes=OBRA_SEL.map(id=>{ const o=OBRAS.find(x=>Number(x.id)===id); return o?o.nome:('#'+id); });
    lbl.textContent = nomes.length===1 ? nomes[0] : (nomes.length+' obras selecionadas'); }
  const m=document.getElementById('obraMenu'); if(m && m.style.display==='block') obraMenuRender();
}

/* ===== Matriz: dropdown de obras (próprio, independente do Radar) — null = todas ===== */
let MAT_SEL=(()=>{ try{ const v=JSON.parse(localStorage.getItem('sup_mat_obras')||'null'); return Array.isArray(v)?v:null; }catch(e){ return null; } })();
// matriz: grupos recolhidos, serviços expandidos (detalhe), ordem custom das colunas de obra, item arrastado
let MAT_COLLAPSED=new Set(), MAT_EXP=new Set(), _matDrag=null, MAT_OBRAS_CUR=[], MAT_SVCS_CUR=[];
let MAT_OBRA_ORDER=(()=>{ try{ const v=JSON.parse(localStorage.getItem('sup_matobra_ord')||'null'); return Array.isArray(v)?v:null; }catch(e){ return null; } })();
// arg seguro p/ string em atributo HTML de evento (aspas simples no JS + &quot; no atributo)
function jsArg(s){ return "'"+String(s==null?'':s).replace(/\\/g,'\\\\').replace(/'/g,"\\'").replace(/"/g,'&quot;').replace(/\n/g,' ')+"'"; }
// bloco de detalhe (quantitativo/verba/responsável/status) mostrado quando o serviço é expandido na matriz
// selo de curadoria (✓ curado manual / 🤖 sugerido pelo auto-vínculo) — hover explica, clique abre o item p/ editar
function matCurIcon(kind, i){
  const map={verba:['curado_verba','verba','verba'],quant:['curado_quant','quant','quantitativo'],crono:['curado_data','crono','data']};
  const m=map[kind]; if(!m) return ''; const cur=i[m[0]], auto=i.auto&&i.auto[m[1]];
  const open=`onclick="event.stopPropagation();openModal(${i.ordem},${i.obra_id||1})"`;
  if(cur) return ` <span class="material-icons" title="${m[2]} CURADA — confirmada manualmente. Clique p/ abrir e editar." style="font-size:12px;color:var(--ok);cursor:pointer;vertical-align:-2px" ${open}>verified</span>`;
  if(auto) return ` <span title="${m[2]} SUGERIDA pelo auto-vínculo (receita) — confira. Clique p/ abrir e confirmar." style="font-size:11px;cursor:pointer" ${open}>🤖</span>`;
  return '';
}
function matExpBlock(i){
  const qt=i.quantitativo!=null?`${QNUM(i.quantitativo)} ${esc(i.quantitativo_unidade||'')}`:'—';
  const vb=verbaDefinida(i)?BRL(verbaDef(i)):'<span style="color:var(--pend)">a definir</span>';
  const rs=i.responsavel?esc(i.responsavel):'<span style="color:var(--pend)">sem resp.</span>';
  const forn=(i.fornecedor&&(''+i.fornecedor).trim())?`<div><b>Fornec.</b>${esc(i.fornecedor)}</div>`:'';   // só quando houver
  return `<div class="mexpb"><div><b>Qtd</b>${qt}${matCurIcon('quant',i)}</div><div><b>Verba</b>${vb}${matCurIcon('verba',i)}</div><div><b>Resp</b>${rs}</div><div><b>Status</b>${esc(i.status||'Não Iniciado')}</div>${forn}</div>`;
}
function matGrpToggle(g){ if(MAT_COLLAPSED.has(g))MAT_COLLAPSED.delete(g); else MAT_COLLAPSED.add(g); renderMatriz(); }
function matSvcToggle(ordem){ ordem=Number(ordem); if(MAT_EXP.has(ordem))MAT_EXP.delete(ordem); else MAT_EXP.add(ordem); renderMatriz(); }
function matExpandAll(on){ if(on)(MAT_SVCS_CUR||[]).forEach(s=>MAT_EXP.add(Number(s.ordem))); else MAT_EXP.clear(); renderMatriz(); }
function matGrpAll(on){ if(on) MAT_COLLAPSED.clear(); else [...new Set((MAT_SVCS_CUR||[]).map(s=>s.grupo))].forEach(g=>MAT_COLLAPSED.add(g)); renderMatriz(); }
function matDragStart(e,i){ _matDrag=(MAT_OBRAS_CUR||[])[i]; try{ e.dataTransfer.effectAllowed='move'; e.dataTransfer.setData('text/plain', _matDrag||''); }catch(_){} }
function matDrop(e,i){ e.preventDefault(); const cur=MAT_OBRAS_CUR||[], target=cur[i];
  if(!_matDrag||!target||_matDrag===target){ _matDrag=null; renderMatriz(); return; }
  let order=(MAT_OBRA_ORDER&&MAT_OBRA_ORDER.length)?MAT_OBRA_ORDER.slice():cur.slice();
  cur.forEach(o=>{ if(!order.includes(o)) order.push(o); });      // inclui obras novas ainda sem posição
  order=order.filter(o=>o!==_matDrag); order.splice(order.indexOf(target),0,_matDrag);
  MAT_OBRA_ORDER=order; _matDrag=null; try{ localStorage.setItem('sup_matobra_ord',JSON.stringify(order)); }catch(_){}
  renderMatriz();
}
function matObraMeta(){ const seen=new Map(); (MAT||[]).forEach(i=>{ if(i.obra_nome && !seen.has(i.obra_nome)) seen.set(i.obra_nome,i.obra_id); }); return [...seen].map(([nome,id])=>({nome,id})); }
function matObraToggle(e){ if(e) e.stopPropagation(); const m=document.getElementById('matObraMenu'); if(!m)return;
  const abrir=m.style.display==='none'||!m.style.display; m.style.display=abrir?'block':'none'; if(abrir) matObraRender(); }
document.addEventListener('click',e=>{ const p=document.getElementById('matObraPick'), m=document.getElementById('matObraMenu');
  if(m && m.style.display==='block' && p && !p.contains(e.target)) m.style.display='none'; });
function matObraRender(){
  const m=document.getElementById('matObraMenu'); if(!m)return; const metas=matObraMeta();
  const selAll=!MAT_SEL||!MAT_SEL.length;
  m.innerHTML=`<div style="display:flex;justify-content:space-between;align-items:center;padding:2px 6px 6px;border-bottom:1px solid var(--line);margin-bottom:4px">
      <span style="font-size:10px;font-weight:800;letter-spacing:.6px;color:var(--muted)">SELECIONE 1 OU MAIS OBRAS</span>
      <button class="btn-ghost" style="padding:2px 8px;font-size:11px" onclick="matObraTodas(event)">Todas</button></div>`+
    (metas.length?metas.map(o=>{ const on=selAll||MAT_SEL.includes(o.nome);
      return `<label style="display:flex;align-items:center;gap:9px;padding:6px 8px;border-radius:7px;cursor:pointer;font-size:12.5px" onmouseover="this.style.background='#eff7f1'" onmouseout="this.style.background=''">
        <input type="checkbox" ${on?'checked':''} onchange="matObraSet('${encodeURIComponent(o.nome)}',this.checked)">
        <span style="width:9px;height:9px;border-radius:50%;background:${obraCor(o.id)};flex:0 0 auto"></span>
        <span style="flex:1"><b>${esc(o.nome)}</b></span></label>`; }).join(''):'<div class="muted" style="padding:8px;font-size:12px">—</div>');
}
function matObraSet(nomeEnc,checked){ const nome=decodeURIComponent(nomeEnc), all=matObraMeta().map(o=>o.nome);
  let cur=(!MAT_SEL||!MAT_SEL.length)?all.slice():MAT_SEL.slice();
  if(checked){ if(!cur.includes(nome)) cur.push(nome); }
  else { cur=cur.filter(x=>x!==nome); if(!cur.length){ toast('Pelo menos uma obra selecionada'); matObraRender(); return; } }
  MAT_SEL=(cur.length>=all.length)?null:cur;
  localStorage.setItem('sup_mat_obras',JSON.stringify(MAT_SEL));
  matObraLbl(); matObraRender(); renderMatriz();
}
function matObraTodas(e){ if(e) e.stopPropagation(); MAT_SEL=null; localStorage.setItem('sup_mat_obras','null'); matObraLbl(); matObraRender(); renderMatriz(); }
function matObraLbl(){ const l=document.getElementById('matObraLbl'); if(!l)return; const all=matObraMeta();
  if(!MAT_SEL||!MAT_SEL.length||MAT_SEL.length>=all.length) l.textContent='Todas as obras';
  else l.textContent=MAT_SEL.length===1?MAT_SEL[0]:(MAT_SEL.length+' obras'); }
async function load(){
  try{
    // busca a matriz de CADA obra selecionada em paralelo e mescla (item ganha obra_id/obra_nome)
    const rs0=await Promise.all(OBRA_SEL.map(async oid=>{
      const d=await (await fetch('actions/matriz.php'+(oid!==1?('?obra='+oid):''))).json(); return {oid,d};
    }));
    const oks=rs0.filter(x=>x.d && !x.d.error && x.d.itens);
    if(!oks.length){document.getElementById('tb').innerHTML=`<tr><td colspan="12" class="empty">Erro: ${esc((rs0[0]&&rs0[0].d&&rs0[0].d.error)||'sem dados')}</td></tr>`;return;}
    DATA=oks[0].d;
    OBRAS=DATA.obras||OBRAS;
    const itens=[]; let covVal=0, covLeaf=0, cronoErro=null;
    for(const {oid,d} of oks){
      (d.itens||[]).forEach(i=>{ i.obra_id=oid; i.obra_nome=(d.obra&&d.obra.nome)||('obra '+oid); itens.push(i); });
      covVal+=(d.resumo&&d.resumo.cobertura_valor)||0; covLeaf+=(d.resumo&&d.resumo.cobertura_total_leaf)||0;
      if(d.resumo&&d.resumo.crono_erro) cronoErro=d.resumo.crono_erro;
    }
    DATA.itens=itens;
    const cobertura=covLeaf?Math.round(covVal/covLeaf*1000)/10:null;
    // obra(s) selecionada(s) aparecem SÓ no dropdown (botão + checkboxes) — sem linha de texto no topo
    obraUpdateUI();
    // KPIs (sobre o conjunto selecionado)
    const comData=itens.filter(i=>i.data_necessaria).length;
    const criticos=itens.filter(i=>alertLevel(i)==='critico').length;
    const atrasados=itens.filter(i=>alertLevel(i)==='atrasado').length;
    const cv=k=>itens.filter(i=>i.curva===k).length;
    document.getElementById('kpis').innerHTML=`
      <div class="kpi"><div class="v">${itens.length}</div><div class="l">Itens no radar${oks.length>1?' ('+oks.length+' obras)':''}</div></div>
      <div class="kpi"><div class="v">${comData} / ${itens.length}</div><div class="l">Com data definida</div></div>
      <div class="kpi"><div class="v ${criticos?'alert':''}">${criticos}</div><div class="l">Críticos (fim da cotação venceu)${atrasados?` · ${atrasados} atrasados`:''}</div></div>
      <div class="kpi"><div class="v">${cv('A')} · ${cv('B')} · ${cv('C')}</div><div class="l">Curva A / B / C</div></div>
      <div class="kpi" title="Cobertura REAL combinada: coberto ${BRL(covVal)} de ${BRL(covLeaf)} em folhas do(s) orçamento(s)."><div class="v gold">${cobertura!=null?cobertura.toLocaleString('pt-BR')+'%':'—'}</div><div class="l">Cobertura real do orçamento</div></div>`;
    // filtros dinâmicos (grupos em ordem lógica = ordem de aparição; demais ordenados)
    fillOrdered('fgrupo',[...new Set(itens.map(i=>i.grupo).filter(Boolean))]);
    fill('fstatus',[...new Set(itens.map(i=>i.status||'Não Iniciado'))]);
    fill('fresp',[...new Set(itens.map(i=>i.responsavel).filter(Boolean))]);
    render();
    // a MATRIZ tem fonte PRÓPRIA (todas as obras, independente do Radar) — invalida o cache e refresca se estiver aberta
    MAT=null;
    if(document.getElementById('view-matriz').style.display!=='none') loadMatriz(true);
  }catch(e){document.getElementById('tb').innerHTML=`<tr><td colspan="12" class="empty">Falha: ${esc(e.message)}</td></tr>`;}
}
function fill(id,arr){const el=document.getElementById(id);const keep=el.value;el.innerHTML=el.children[0].outerHTML+arr.slice().sort().map(v=>`<option>${esc(v)}</option>`).join('');el.value=keep;}
function fillOrdered(id,arr){const el=document.getElementById(id);const keep=el.value;el.innerHTML=el.children[0].outerHTML+arr.map(v=>`<option>${esc(v)}</option>`).join('');el.value=keep;}
function fillMulti(id,arr){const el=document.getElementById(id);el.innerHTML=arr.map(v=>`<option selected>${esc(v)}</option>`).join('');el.size=Math.min(Math.max(arr.length,1),4);}
// Carrega a MATRIZ com TODAS as obras do sistema — independente do filtro de obra do Radar (OBRA_SEL).
async function loadMatriz(force){
  try{
    if(MAT && !force){ renderMatriz(); return; }
    let obras=(OBRAS&&OBRAS.length)?OBRAS.map(o=>Number(o.id)):null;
    if(!obras){ const d0=await (await fetch('actions/matriz.php')).json(); OBRAS=d0.obras||[]; obras=OBRAS.map(o=>Number(o.id)); }
    const rs=await Promise.all(obras.map(async oid=>{
      const d=await (await fetch('actions/matriz.php'+(oid!==1?('?obra='+oid):''))).json(); return {oid,d};
    }));
    const items=[];
    for(const {oid,d} of rs){ if(!d||d.error||!d.itens) continue;
      if(d.obras) OBRAS=d.obras;
      (d.itens||[]).forEach(i=>{ i.obra_id=oid; i.obra_nome=(d.obra&&d.obra.nome)||('obra '+oid); items.push(i); });
    }
    MAT=items;
    fillOrdered('mgrupo',[...new Set(items.map(i=>i.grupo).filter(Boolean))]);
    matObraLbl();
    fill('mstatus',[...new Set(items.map(i=>i.status||'Não Iniciado'))]);
    const mr=document.getElementById('mresp'); if(mr){ const mk=mr.value;
      mr.innerHTML='<option value="">Todos os responsáveis</option><option value="__sem__">— sem responsável —</option>'+[...new Set(items.map(i=>i.responsavel).filter(Boolean))].sort().map(v=>`<option>${esc(v)}</option>`).join(''); mr.value=mk; }
    renderMatriz();
  }catch(e){ const w=document.getElementById('mwrap'); if(w) w.innerHTML='<div class="empty">Falha ao carregar a matriz: '+esc(e.message)+'</div>'; }
}

/* ---------- view switch ---------- */
function showView(v){
  ['radar','matriz','oportunidades','dashboards','cotacoes','solicitacoes','obras','oraculo','config','audit','updates'].forEach(x=>{
    const el=document.getElementById('view-'+x); if(el) el.style.display=v===x?'':'none';
    const nav=document.getElementById('nav-'+x); if(nav) nav.classList.toggle('active',v===x);
  });
  if(v==='obras') obrasInit();
  if(v==='cotacoes') cotInit();
  if(v==='solicitacoes') solInit();
  if(v==='oraculo') oracInit();
  if(v==='dashboards') dashInit();
  if(v==='matriz') loadMatriz();
  if(v==='oportunidades') renderOportunidades();
  if(v==='config') renderConfig();
  if(v==='radar') fitRadarHeight();
  if(v==='audit') renderAudit();
  if(v==='updates') renderUpdates();
}

/* ===== Radar IA (oráculo de suprimentos) — chat com LLM (OpenAI via servidor) ===== */
let ORAC={msgs:[], loading:false, cfg:null, usadas:null, limite:0, limiteAtingido:false};
const ORAC_AVATAR="data:image/png;base64,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";
const ORAC_SUG=[
  'Qual é a minha programação de compras para este mês?',
  'Quais cotações estão em aberto e ainda sem proposta?',
  'Quais prazos de cotação vencem nos próximos 30 dias?',
  'Quais oportunidades de contratação eu tenho pela frente?',
  'Analise o mapa de cotações e me dê insights de economia.',
  'Resuma o status das aquisições por obra.'
];
async function oracInit(){
  if(!ORAC.cfg){ try{ ORAC.cfg=await (await fetch('actions/oracle.php?_='+Date.now())).json(); }catch(e){ ORAC.cfg={configurado:false}; } }
  if(ORAC.cfg&&ORAC.cfg.limite_dia!=null) ORAC.limite=ORAC.cfg.limite_dia;
  oracRender();
}
// markdown leve → html (negrito, código, títulos, listas, parágrafos)
function oracMd(t){
  const lines=String(t==null?'':t).split('\n'); let out=[], inList=false;
  const inl=s=>esc(s).replace(/\*\*([^*]+)\*\*/g,'<b>$1</b>').replace(/`([^`]+)`/g,'<code style="background:#eef1ef;padding:1px 5px;border-radius:4px;font-size:12px">$1</code>');
  for(const ln of lines){
    if(/^\s*[-*]\s+/.test(ln)){ if(!inList){out.push('<ul style="margin:4px 0 6px 20px;padding:0">');inList=true;} out.push('<li style="margin:2px 0">'+inl(ln.replace(/^\s*[-*]\s+/,''))+'</li>'); continue; }
    if(inList){ out.push('</ul>'); inList=false; }
    if(/^#{1,6}\s+/.test(ln)){ out.push('<div style="font-weight:800;margin:10px 0 4px;color:var(--verde-d);font-size:13.5px">'+inl(ln.replace(/^#{1,6}\s+/,''))+'</div>'); continue; }
    if(ln.trim()===''){ out.push('<div style="height:6px"></div>'); continue; }
    out.push('<div style="margin:2px 0">'+inl(ln)+'</div>');
  }
  if(inList) out.push('</ul>');
  return out.join('');
}
function oracModelSelect(cur){
  cur=(cur||'gpt-4o-mini');
  const opts=[['gpt-4o-mini','gpt-4o-mini — rápido e barato (recomendado)'],['gpt-4o','gpt-4o — mais capaz'],['gpt-4.1-mini','gpt-4.1-mini'],['gpt-4.1','gpt-4.1 — o mais capaz']];
  if(!opts.some(o=>o[0]===cur)) opts.unshift([cur, cur+' (atual)']);
  return '<select id="oracModel" style="width:100%">'+opts.map(o=>'<option value="'+esc(o[0])+'"'+(o[0]===cur?' selected':'')+'>'+esc(o[1])+'</option>').join('')+'</select>';
}
function oracRender(){
  const w=document.getElementById('oracwrap'); if(!w)return; const cfg=ORAC.cfg||{}; const admin=!!(EU&&EU.perm_admin);
  let admincfg='';
  if(admin){ admincfg=`<details style="margin-bottom:10px"><summary style="cursor:pointer;font-size:12px;color:var(--muted)"><span class="material-icons" style="font-size:14px;vertical-align:-3px">settings</span> Configuração do Radar IA (admin) — chave · modelo · limite · prompt</summary>
    <div class="panel" style="margin-top:6px">
      <div class="dmini" style="margin-bottom:8px">A chave fica só no servidor (nunca no navegador). Status: <b style="color:${cfg.configurado?'var(--ok)':'var(--pend)'}">${cfg.configurado?'configurada ✓':'não configurada'}</b> · ${cfg.prompt_custom?'usando <b>prompt personalizado</b>':'usando o <b>prompt padrão</b>'}</div>
      <div style="display:grid;grid-template-columns:1fr 150px 130px;gap:8px;max-width:720px">
        ${cotFld('Chave da OpenAI (sk-…)','<input id="oracKey" type="password" autocomplete="off" style="width:100%" placeholder="vazio mantém a atual">')}
        ${cotFld('Modelo', oracModelSelect(cfg.modelo))}
        ${cotFld('Perguntas/dia','<input id="oracLimite" type="number" min="0" style="width:100%" title="0 = ilimitado; admins não contam" value="'+((cfg.limite_dia!=null)?cfg.limite_dia:2)+'">')}
      </div>
      ${cotFld('Prompt-base do oráculo — ensina o sistema à IA (vazio volta ao padrão)','<textarea id="oracPrompt" rows="10" style="width:100%;font-size:12px;font-family:ui-monospace,Consolas,monospace">'+esc(cfg.prompt_custom?(cfg.prompt||''):'')+'</textarea>','margin-top:8px')}
      ${cotFld('Prompt do MOTOR DE IA — lê o anexo (PDF/Excel/print) e preenche a proposta (vazio volta ao padrão)','<textarea id="oracPromptEx" rows="8" style="width:100%;font-size:12px;font-family:ui-monospace,Consolas,monospace">'+esc(cfg.prompt_extracao_custom?(cfg.prompt_extracao||''):'')+'</textarea>','margin-top:10px')}
      <div class="dmini" style="margin-top:3px">O motor de extração usa o modelo <b>${esc(cfg.modelo_extracao||'gpt-4o')}</b> (com visão, p/ ler imagem e PDF). ${cfg.prompt_extracao_custom?'Usando <b>prompt personalizado</b>.':'Usando o <b>prompt padrão</b>.'}</div>
      <div style="margin-top:8px;display:flex;gap:8px;align-items:center;flex-wrap:wrap"><button class="btn-prim" style="padding:6px 12px" onclick="oracSalvarCfg()">Salvar configuração</button>
        <button class="btn-ghost" style="padding:6px 12px" onclick="oracVerPadrao()"><span class="material-icons" style="font-size:14px;vertical-align:-3px">download</span> Prompt padrão do oráculo</button>
        <button class="btn-ghost" style="padding:6px 12px" onclick="oracVerPadraoEx()"><span class="material-icons" style="font-size:14px;vertical-align:-3px">download</span> Prompt padrão do motor de IA</button></div>
    </div></details>`; }
  const chat=ORAC.msgs.map(m=>m.role==='user'
    ? `<div style="display:flex;justify-content:flex-end;margin:8px 0"><div style="background:var(--verde);color:#fff;padding:8px 12px;border-radius:12px 12px 3px 12px;max-width:78%;font-size:13px;white-space:pre-wrap">${esc(m.content)}</div></div>`
    : `<div style="display:flex;justify-content:flex-start;margin:8px 0"><div style="background:#fff;border:1px solid var(--line);padding:10px 14px;border-radius:12px 12px 12px 3px;max-width:90%;font-size:13px;box-shadow:0 1px 4px rgba(0,0,0,.05)"><div style="display:flex;align-items:center;gap:6px;margin-bottom:4px;color:var(--dourado);font-weight:700;font-size:11px"><span class="material-icons" style="font-size:14px">auto_awesome</span> RADAR IA</div>${oracMd(m.content)}</div></div>`
  ).join('');
  const vazio=!ORAC.msgs.length;
  const sug=`<div style="display:flex;flex-wrap:wrap;gap:7px;margin:${vazio?'8px':'10px'} 0">${ORAC_SUG.map(s=>`<button class="btn-ghost" style="padding:6px 11px;font-size:12px;text-align:left" onclick="oracPergunta(${jsArg(s)})">${esc(s)}</button>`).join('')}</div>`;
  w.innerHTML=`${admincfg}
    <div class="panel" style="display:flex;flex-direction:column;min-height:440px">
      <div id="oracMsgs" style="flex:1;overflow:auto;max-height:calc(100vh - 350px);padding:4px 2px">
        ${vazio?`<div class="oracintro"><div class="oracintro-av"><img src="${ORAC_AVATAR}" alt="Radar IA"></div><div class="oracintro-tx"><div style="font-weight:800;font-size:17px;line-height:1.25">Olá! Sou o <span style="color:var(--dourado)">Radar IA</span> — seu oráculo de suprimentos.</div><div class="muted" style="font-size:12.5px;margin-top:6px">Analiso as aquisições, cotações, prazos e oportunidades das obras. Pergunte à vontade, ou comece por uma sugestão:</div>${sug}</div></div>`:chat}
        ${ORAC.loading?`<div style="display:flex;justify-content:flex-start;margin:8px 0"><div style="background:#fff;border:1px solid var(--line);padding:10px 14px;border-radius:12px;font-size:12.5px;color:var(--muted)"><span class="material-icons" style="font-size:14px;vertical-align:-3px;color:var(--dourado)">auto_awesome</span> analisando os dados…</div></div>`:''}
      </div>
      ${!vazio?sug:''}
      <div style="display:flex;gap:8px;margin-top:8px;border-top:1px solid var(--line);padding-top:10px">
        <input id="oracIn" placeholder="${ORAC.limiteAtingido?'Limite diário atingido — volte amanhã':'Pergunte ao Radar IA…'}" style="flex:1" onkeydown="if(event.key==='Enter')oracEnviar()" ${(ORAC.loading||ORAC.limiteAtingido)?'disabled':''}>
        <button class="btn-prim" onclick="oracEnviar()" ${(ORAC.loading||ORAC.limiteAtingido)?'disabled':''}><span class="material-icons" style="font-size:16px;vertical-align:-3px">send</span> Enviar</button>
      </div>
      ${(!admin && ORAC.limite>0)?`<div class="dmini" style="text-align:right;margin-top:4px">${ORAC.usadas!=null?`${ORAC.usadas} de ${ORAC.limite} pergunta(s) hoje`:`limite: ${ORAC.limite} pergunta(s) por dia`}</div>`:''}
    </div>`;
  const ms=document.getElementById('oracMsgs'); if(ms) ms.scrollTop=ms.scrollHeight;
  const inp=document.getElementById('oracIn'); if(inp&&!ORAC.loading) inp.focus();
}
function oracEnviar(){ const inp=document.getElementById('oracIn'); if(!inp)return; const q=(inp.value||'').trim(); if(!q)return; inp.value=''; oracPergunta(q); }
async function oracPergunta(q){
  if(ORAC.loading||ORAC.limiteAtingido)return;
  ORAC.msgs.push({role:'user',content:q}); ORAC.loading=true; oracRender();
  try{
    const hist=ORAC.msgs.slice(-7,-1).map(m=>({role:m.role,content:m.content}));   // histórico curto (sem a última)
    const r=await (await fetch('actions/oracle.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({acao:'perguntar',me:EU&&EU.bitrix_id,pergunta:q,historico:hist})})).json();
    ORAC.loading=false;
    if(r.usadas!=null){ ORAC.usadas=r.usadas; if(r.limite!=null) ORAC.limite=r.limite; }
    if(r.limite_atingido) ORAC.limiteAtingido=true;
    ORAC.msgs.push({role:'assistant',content:r.error?('⚠️ '+r.error):(r.resposta||'(sem resposta)')});
    if(!(EU&&EU.perm_admin) && ORAC.usadas!=null && ORAC.limite>0 && ORAC.usadas>=ORAC.limite) ORAC.limiteAtingido=true;
  }catch(e){ ORAC.loading=false; ORAC.msgs.push({role:'assistant',content:'⚠️ Falha ao consultar: '+e.message}); }
  oracRender();
}
async function oracSalvarCfg(){
  const g=id=>{const e=document.getElementById(id);return e?e.value:'';};
  const body={acao:'set_key',me:EU&&EU.bitrix_id,key:g('oracKey'),model:g('oracModel'),prompt:g('oracPrompt'),prompt_extracao:g('oracPromptEx')};
  const lim=g('oracLimite'); if(lim!==''&&lim!=null) body.limit_dia=Number(lim);
  try{ const r=await (await fetch('actions/oracle.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(body)})).json();
    if(r.error){toast(r.error);return;} ORAC.cfg=null; await oracInit(); toast('Configuração do Radar IA salva');
  }catch(e){ toast('Falha: '+e.message); }
}
function oracVerPadrao(){ const t=document.getElementById('oracPrompt'); if(t&&ORAC.cfg){ t.value=ORAC.cfg.prompt_padrao||''; toast('Prompt padrão carregado no campo — edite e salve, ou salve como está'); } }
function oracVerPadraoEx(){ const t=document.getElementById('oracPromptEx'); if(t&&ORAC.cfg){ t.value=ORAC.cfg.prompt_extracao_padrao||''; toast('Prompt padrão do motor de IA carregado — edite e salve, ou salve como está'); } }

/* ===== Oportunidades (Curva ABC) — grandes itens do orçamento fora do radar ===== */
let OPP={obra:null, gaps:[], resumo:{}, sel:new Set()};
function renderOportunidades(){
  const os=document.getElementById('opObra'), list=(typeof OBRAS!=='undefined'&&OBRAS)?OBRAS:[];
  if(os && list.length){ const sig=list.map(o=>o.id).join(','); if(os.dataset.k!==sig || !os.options.length){ const keep=os.value; os.innerHTML=list.map(o=>`<option value="${o.id}">${esc(o.nome)}</option>`).join(''); os.dataset.k=sig; if(keep&&list.some(o=>String(o.id)===keep)) os.value=keep; } }
  opLoad();
}
async function opLoad(){
  const os=document.getElementById('opObra'); OPP.obra=Number(os?os.value:OPP.obra)||1; OPP.sel=new Set();
  const box=document.getElementById('opwrap'); box.innerHTML='<div class="empty">Analisando o orçamento…</div>';
  try{
    const d=await (await fetch('actions/oportunidades.php?obra='+OPP.obra+'&_='+Date.now())).json();
    if(d.error){ box.innerHTML='<div class="empty">Erro: '+esc(d.error)+'</div>'; return; }
    OPP.gaps=(d.gaps||[]).map((g,i)=>(g._i=i,g)); OPP.resumo=d.resumo||{};
    const dl=document.getElementById('opGrupos'); if(dl) dl.innerHTML=(d.grupos||[]).map(x=>`<option value="${esc(x)}"></option>`).join('');
    OPP.itens=d.itens||[]; OPP.selItem=null;
    const g=document.getElementById('opGrupo'), keep=g.value;
    g.innerHTML='<option value="">Todos os grupos</option>'+[...new Set(OPP.gaps.flatMap(x=>x.grupos||[]))].sort().map(x=>`<option>${esc(x)}</option>`).join(''); if(keep)g.value=keep;
    opRender();
  }catch(e){ box.innerHTML='<div class="empty">Falha: '+esc(e.message)+'</div>'; }
}
function opFiltered(){
  const fc=val('opCurva'), fg=val('opGrupo'), q=(document.getElementById('opQ').value||'').toLowerCase();
  return OPP.gaps.filter(x=>(!fc||(fc==='AB'?(x.curva==='A'||x.curva==='B'):x.curva===fc))&&(!fg||(x.grupos||[]).includes(fg))&&(!q||(x.descricao||'').toLowerCase().includes(q)));
}
function opRender(){
  const r=OPP.resumo, k=document.getElementById('opKpis');
  const pct=x=>Number(x||0).toFixed(1);
  if(k) k.innerHTML=`
    <div class="kpi"><div class="v gold">${pct(r.coberto_pct)}%</div><div class="l">radar cobre · ${BRL(r.coberto)}</div></div>
    <div class="kpi"><div class="v ${(r.gap_pct||0)>15?'alert':''}">${pct(r.gap_pct)}%</div><div class="l">gap de suprimentos · ${BRL(r.gap)}</div></div>
    <div class="kpi"><div class="v">${pct(r.indiretos_pct)}%</div><div class="l">custos indiretos (fora)</div></div>
    <div class="kpi"><div class="v">${r.n_gaps||0}</div><div class="l">itens descobertos</div></div>`;
  const fi=opFiltered(), box=document.getElementById('opwrap');
  let html='<table><thead><tr><th style="width:30px"><input type="checkbox" id="opAll" onclick="opToggleAll(this.checked)"></th><th>Curva</th><th>Descrição (item do orçamento)</th><th>Grupos</th><th style="text-align:right">Valor</th><th style="text-align:right">%</th></tr></thead><tbody>';
  for(const x of fi){
    html+=`<tr><td><input type="checkbox" ${OPP.sel.has(x.descricao)?'checked':''} onclick="opSel(${x._i},this.checked)"></td>
      <td><span class="tp-chip">${esc(x.curva)}</span></td><td style="font-size:12.5px">${esc(x.descricao)}</td>
      <td class="muted" style="font-size:11px">${esc((x.grupos||[]).join(', '))}</td>
      <td style="text-align:right;font-weight:600">${BRL(x.valor)}</td><td style="text-align:right" class="muted">${Number(x.valor_pct||0).toFixed(1)}%</td></tr>`;
  }
  if(!fi.length) html+='<tr><td colspan="6" class="empty">Nenhum item descoberto nesse filtro.</td></tr>';
  box.innerHTML=html+'</tbody></table>';
  const all=document.getElementById('opAll'); if(all) all.checked=fi.length>0 && fi.every(x=>OPP.sel.has(x.descricao));
  opCount();
}
function opSel(i,on){ const g=OPP.gaps[i]; if(!g)return; on?OPP.sel.add(g.descricao):OPP.sel.delete(g.descricao); opCount();
  const all=document.getElementById('opAll'); if(all){ const fi=opFiltered(); all.checked=fi.length>0 && fi.every(x=>OPP.sel.has(x.descricao)); } }
function opToggleAll(on){ opFiltered().forEach(x=>{ on?OPP.sel.add(x.descricao):OPP.sel.delete(x.descricao); }); opRender(); }
function opCount(){ const v=OPP.gaps.filter(g=>OPP.sel.has(g.descricao)).reduce((s,g)=>s+g.valor,0);
  const el=document.getElementById('opSel'); if(el) el.textContent=OPP.sel.size+' selecionados · '+BRL(v); }
async function opCriar(){
  if(!OPP.sel.size){ toast('Selecione os itens do orçamento a agrupar'); return; }
  const nome=(document.getElementById('opNome').value||'').trim(); if(!nome){ toast('Dê um nome ao item de aquisição'); return; }
  const grupo=(document.getElementById('opGrupoNovo').value||'').trim(); if(!grupo){ toast('Informe o grupo'); return; }
  const curva=val('opCurvaNovo')||'A', descricoes=[...OPP.sel];
  const v=OPP.gaps.filter(g=>OPP.sel.has(g.descricao)).reduce((s,g)=>s+g.valor,0);
  if(!confirm('Criar “'+nome+'” agrupando '+descricoes.length+' descrição(ões) · '+BRL(v)+' e vincular a verba na obra? Entra como sugerido (🤖).')) return;
  try{ const r=await (await fetch('actions/oportunidades.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({acao:'criar',me:EU&&EU.bitrix_id,obra:OPP.obra,nome,grupo,curva,descricoes})})).json();
    if(r.error){ toast(r.error); return; }
    toast('Criado: '+r.nome+' — '+r.linhas+' linhas · '+BRL(r.valor));
    document.getElementById('opNome').value=''; document.getElementById('opGrupoNovo').value='';
    OPP.sel=new Set(); if(typeof MAT!=='undefined')MAT=null; if(typeof RCDATA!=='undefined')RCDATA=null;
    await opLoad();
  }catch(e){ toast('Falha: '+e.message); }
}
function opNorm(s){ return (s||'').toString().toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g,''); }
function opItemMatch(q){   // busca por PALAVRAS: cada token precisa aparecer no nome (tolera plural, ordem, acento)
  const toks=opNorm(q).split(/\s+/).map(t=>t.replace(/[^a-z0-9]/g,'')).filter(t=>t.length>=2).map(t=>(t.length>=4&&t.endsWith('s'))?t.slice(0,-1):t);
  if(!toks.length) return [];
  return (OPP.itens||[]).filter(it=>{ const n=opNorm(it.nome); return toks.every(t=>n.includes(t)); }).slice(0,12);
}
function opItemBuscaInput(){
  OPP.selItem=null;
  const q=document.getElementById('opItemBusca').value, box=document.getElementById('opItemSug'); if(!box)return;
  const ms=q.trim()?opItemMatch(q):[];
  if(!ms.length){ box.style.display='none'; box.innerHTML=''; return; }
  box.innerHTML=ms.map(it=>`<div onclick="opPickItem(${it.id})" style="padding:7px 10px;cursor:pointer;font-size:12.5px;border-bottom:1px solid #f1f3f2" onmouseover="this.style.background='#eff7f1'" onmouseout="this.style.background=''">${esc(it.nome)} <span class="muted" style="font-size:10.5px">· ${esc(it.grupo||'')}</span></div>`).join('');
  box.style.display='block';
}
function opPickItem(id){ const it=(OPP.itens||[]).find(x=>x.id===id); if(!it)return; OPP.selItem=it;
  const inp=document.getElementById('opItemBusca'); if(inp)inp.value=it.nome; const box=document.getElementById('opItemSug'); if(box){box.style.display='none';box.innerHTML='';} }
document.addEventListener('click',e=>{ if(!(e.target.closest&&e.target.closest('#opItemBusca,#opItemSug'))){ const b=document.getElementById('opItemSug'); if(b) b.style.display='none'; } });
async function opVincular(){
  if(!OPP.sel.size){ toast('Marque os itens do orçamento a vincular'); return; }
  const q=(document.getElementById('opItemBusca').value||'').trim();
  const it=OPP.selItem || (OPP.itens||[]).find(x=>(x.nome||'').toLowerCase()===q.toLowerCase()) || opItemMatch(q)[0];
  if(!it){ toast('Digite e escolha um item existente do radar'); return; }
  const descricoes=[...OPP.sel], v=OPP.gaps.filter(g=>OPP.sel.has(g.descricao)).reduce((s,g)=>s+g.valor,0);
  if(!confirm('Vincular '+descricoes.length+' descrição(ões) · '+BRL(v)+' ao item “'+it.nome+'”?\n\nEntra como sugerido (🤖); depois você entra no item e refina os insumos se precisar.')) return;
  try{ const r=await (await fetch('actions/oportunidades.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({acao:'vincular',me:EU&&EU.bitrix_id,obra:OPP.obra,servico_id:it.id,descricoes})})).json();
    if(r.error){ toast(r.error); return; }
    toast('Vinculado a “'+r.nome+'” — +'+r.linhas+' linhas · '+BRL(r.valor));
    document.getElementById('opItemBusca').value='';
    OPP.sel=new Set(); if(typeof MAT!=='undefined')MAT=null; if(typeof RCDATA!=='undefined')RCDATA=null;
    await opLoad();
  }catch(e){ toast('Falha: '+e.message); }
}

/* ===== Auditoria de Orçamento (temporária) — duplicação de vínculo de verba ===== */
async function renderAudit(){
  const box=document.getElementById('auditwrap');
  box.innerHTML='<div class="empty">Rodando auditoria na base…</div>';
  let d,u,co;
  try{
    const aob=OBRA_SEL[0]||1;   // auditoria roda na obra PRIMÁRIA selecionada
    d=await (await fetch('actions/audit_orcamento.php?obra='+aob)).json();
    u=await (await fetch('actions/verba_usos.php?obra='+aob+'&_='+Date.now())).json();
    co=await (await fetch('actions/audit_coerencia.php?obra='+aob+'&_='+Date.now())).json();
  }
  catch(e){ box.innerHTML='<div class="empty">Falha: '+esc(e.message)+'</div>'; return; }
  if(d.error){ box.innerHTML='<div class="empty">Erro: '+esc(d.error)+'</div>'; return; }
  if(u.error){ box.innerHTML='<div class="empty">Erro (usos): '+esc(u.error)+'</div>'; return; }
  AUDIT_CO = co && !co.error ? co : {flagged:[],n:0,total_embutido:0,n_mat_com_mo:0,n_mo_com_mat:0};
  const dups=(u&&u.duplicatas)||[];
  const inflado=dups.reduce((s,x)=>s+(x.valor||0)*((x.n||1)-1),0);
  const P=x=>x==null?'—':Number(x).toLocaleString('pt-BR');
  const pctReal=d.cobertura_real_pct_folhas, pctAna=d.cobertura_analitico_pct_folhas, pctComp=d.cobertura_composicao_pct_folhas;
  let html=`<div class="kpis" style="padding:0 0 14px">
    <div class="kpi" title="Mesmo critério do KPI do Radar: analítico (linhas distintas) + composição, sobre as folhas do orçamento."><div class="v gold">${P(pctReal)}%</div><div class="l">Cobertura real do orçamento</div></div>
    <div class="kpi"><div class="v">${BRL(d.valor_coberto_real)}</div><div class="l">de ${BRL(d.total_leaf)} em folhas</div></div>
    <div class="kpi" title="Como a cobertura real se divide entre os dois caminhos de curadoria."><div class="v">${P(pctAna)}% <span class="muted" style="font-weight:600">+ ${P(pctComp)}%</span></div><div class="l">analítico (distinto) + composição</div></div>
    <div class="kpi"><div class="v ${dups.length?'alert':''}">${dups.length}</div><div class="l">Linhas em 2+ itens (analítico + composição)</div></div>
    <div class="kpi"><div class="v ${inflado?'alert':''}">${BRL(inflado)}</div><div class="l">Verba inflada por duplicação</div></div>
  </div>
  <div class="note" style="margin:0 0 12px">A <b>cobertura real</b> (${P(pctReal)}%) agora bate com a do Radar: <b>${P(pctAna)}%</b> por vínculo analítico (linhas distintas do orçamento) + <b>${P(pctComp)}%</b> por composição (verba dos ${d.composicao_itens} itens curados por cesta de insumos).</div>`;
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

  // ===== Coerência: tipo do item × o que a verba traz =====
  const co2=AUDIT_CO, flagged=(co2.flagged)||[];
  html+=`<div class="bl" style="font-size:14px;margin:22px 0 8px;text-transform:none;color:var(--verde-d)">Coerência — tipo do item × o que a verba traz</div>
    <div class="kpis" style="padding:0 0 12px">
    <div class="kpi"><div class="v ${flagged.length?'alert':''}">${flagged.length}</div><div class="l">Itens incoerentes (tipo × verba)</div></div>
    <div class="kpi"><div class="v ${co2.total_embutido?'alert':''}">${BRL(co2.total_embutido)}</div><div class="l">Valor no lado errado (embutido)</div></div>
    <div class="kpi"><div class="v">${co2.n_mat_com_mo}</div><div class="l">Material com MO embutida</div></div>
    <div class="kpi"><div class="v">${co2.n_mo_com_mat}</div><div class="l">MO com material embutido</div></div>
  </div>`;
  if(!flagged.length){
    html+='<div class="panel" style="padding:16px"><b style="color:var(--ok)">✓ Tudo coerente.</b> Cada item traz só o que o tipo dele diz (material só material, mão de obra só MO).</div>';
  } else {
    html+='<div class="note">Item de <b>material</b> que trouxe <b>mão de obra</b> (ou o contrário) — quase sempre porque pegou a <b>linha inteira</b>. <b>Separar</b> deixa o item só com o lado certo e <b>libera o outro</b> pra ir pro item correto.</div>';
    if(co2.n_mat_com_mo) html+=`<button class="btn-prim" style="margin:0 8px 10px 0" onclick="corrigirTodosCoerencia('mat_com_mo')"><span class="material-icons" style="font-size:15px;vertical-align:-3px">content_cut</span> Separar TODOS os ${co2.n_mat_com_mo} materiais com MO embutida</button>`;
    if(co2.n_mo_com_mat) html+=`<button class="btn-prim" style="margin:0 8px 10px 0" onclick="corrigirTodosCoerencia('mo_com_mat')"><span class="material-icons" style="font-size:15px;vertical-align:-3px">content_cut</span> Separar TODOS os ${co2.n_mo_com_mat} MO com material embutido</button>`;
    html+='<div class="wrap" style="margin:0"><table><thead><tr><th>Item</th><th>Tipo declarado</th><th>Embutido (lado errado)</th><th></th></tr></thead><tbody>';
    for(const f of flagged){
      const lado=f.issue==='mat_com_mo'?'MO':'material';
      html+=`<tr>
        <td><div class="svc">${esc(f.nome)}</div><div class="svc-sub">${f.metodo==='analitico'?'linha inteira (analítico)':('composição · '+f.remover.length+' insumo(s) do lado errado')}</div></td>
        <td><span class="tp-chip ${f.classe==='material'?'tp-mat':'tp-mat-mo'}">${esc(f.tipo||'—')}</span></td>
        <td class="money" style="color:var(--pend)">${BRL(f.embutido)} <span class="muted" style="font-size:11px">de ${lado} · <b>${f.pct}%</b> de ${BRL(f.total)}</span></td>
        <td><div style="display:flex;gap:6px;justify-content:flex-end">
          <button class="btn-ghost" style="padding:3px 9px;font-size:12px" onclick="auditDetalhar(${f.ordem})"><span class="material-icons" style="font-size:14px;vertical-align:-2px">unfold_more</span> detalhar</button>
          <button class="btn-ghost" style="padding:3px 9px;font-size:12px" onclick="corrigirUm(${f.ordem})">separar</button>
          <button class="btn-ghost" style="padding:3px 9px;font-size:12px" onclick="openModal(${f.ordem})">abrir</button></div></td></tr>
        <tr id="audet-${f.ordem}" style="display:none"><td colspan="4" style="padding:0;background:#fafbf8"><div class="audet-body"></div></td></tr>`;
    }
    html+='</tbody></table></div>';
  }
  box.innerHTML=html;
}
let AUDIT_CO=null;
async function postItem(ordem,campos){ return (await (await fetch('actions/item_update.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({ordem,campos,me:EU&&EU.bitrix_id,obra:OBQ()})})).json()); }
async function corrigirCoerencia(f){
  if(f.metodo==='analitico'){
    const d=await (await fetch('actions/separar_mo.php?obra='+(OBRA_SEL[0]||1)+'&manter='+f.classe+'&ordem='+f.ordem)).json();
    if(d.error) return {err:d.error};
    const sel=(d.composicao_sel||[]).map(s=>({cid:s.cid,idx:s.idx,area:s.area,q:0,locais:s.locais||null}));
    if(!sel.length) return {err:'linhas sem composição — não separei pra não zerar a verba'};   // TRAVA anti-wipe
    await postItem(f.ordem,{composicao_sel:sel, orcamento_refs:[]});
  } else {
    const it=byOrdem(f.ordem); if(!it) return {err:'item sumiu'};
    const rem=new Set((f.remover||[]).map(x=>x.cid+'#'+x.idx));
    const keep=(it.composicao_sel||[]).filter(s=>!rem.has(s.cid+'#'+s.idx)).map(s=>({cid:s.cid,idx:s.idx,area:s.area,q:s.q?1:0,locais:s.locais||null}));
    await postItem(f.ordem,{composicao_sel:keep});
  }
  return {ok:true};
}
async function corrigirUm(ordem){
  const f=(AUDIT_CO&&AUDIT_CO.flagged||[]).find(x=>x.ordem===ordem); if(!f){ toast('recarregue a auditoria'); return; }
  const lado=f.issue==='mat_com_mo'?'mão de obra':'material';
  if(!confirm('Separar “'+(byOrdem(ordem)||{}).nome+'”: tira '+BRL(f.embutido)+' de '+lado+' embutido, deixando o item só com o lado certo. Confirmar?')) return;
  const r=await corrigirCoerencia(f); if(r&&r.err){ toast('Erro: '+r.err); return; }
  VERBA_USOS=null; await load(); renderAudit(); toast('Separado · '+BRL(f.embutido)+' de '+lado+' liberado.');
}
async function corrigirTodosCoerencia(issue){
  const list=(AUDIT_CO&&AUDIT_CO.flagged||[]).filter(f=>f.issue===issue);
  if(!list.length){ toast('Nada a corrigir'); return; }
  const totalLib=list.reduce((a,f)=>a+f.embutido,0), lado=issue==='mat_com_mo'?'mão de obra':'material';
  if(!confirm('Separar '+list.length+' itens — tira '+BRL(totalLib)+' de '+lado+' embutido no lugar errado, deixando cada item só com o lado certo (vira composição). Confirmar?')) return;
  const box=document.getElementById('auditwrap'); if(box) box.innerHTML='<div class="empty">Separando '+list.length+' itens…</div>';
  let ok=0; for(const f of list){ try{ const r=await corrigirCoerencia(f); if(r&&!r.err) ok++; }catch(e){} }
  VERBA_USOS=null; await load(); renderAudit();
  toast(ok+'/'+list.length+' itens separados · '+BRL(totalLib)+' de '+lado+' liberados.');
}
async function auditDetalhar(ordem){
  const row=document.getElementById('audet-'+ordem); if(!row)return;
  const show=row.style.display==='none'; row.style.display=show?'table-row':'none';
  if(!show || row.dataset.loaded) return;
  const body=row.querySelector('.audet-body'); body.innerHTML='<div class="muted" style="font-size:12px;padding:8px">Detalhando…</div>';
  let d; try{ d=await (await fetch('actions/audit_detalhe.php?obra='+(OBRA_SEL[0]||1)+'&ordem='+ordem)).json(); }
  catch(e){ body.innerHTML='<div class="muted" style="padding:8px">Falha.</div>'; return; }
  if(d.error){ body.innerHTML='<div class="muted" style="padding:8px">'+esc(d.error)+'</div>'; return; }
  body.innerHTML=auditDetHtml(d); row.dataset.loaded='1';
}
function auditDetHtml(d){
  const certo=TP_FULL[d.classe==='mo'?'mo':'material'];
  const pctErr=d.total>0?Math.round(100*d.tot_errado/d.total):0;
  const tpt=d.tot_por_tipo||{};
  const lin=x=>`<tr style="${x.lado==='errado'?'background:#fff4f4':''}">
      <td style="padding:3px 8px">${esc((x.desc||'').slice(0,46))}</td>
      <td style="padding:3px 8px;color:var(--muted)">${esc((x.comp||'').slice(0,40))}</td>
      <td style="padding:3px 8px;text-align:center">${tpBadge(x.tipo)}${x.lado==='errado'?' <span style="color:var(--pend)" title="sairia ao separar">⚠</span>':''}</td>
      <td style="padding:3px 8px;text-align:right">${BRL(x.valor)}</td></tr>`;
  const grupos=['material','mo','mat_mo','equip'].map(k=>{
    const rows=d.insumos.filter(x=>tpCls(x.tipo)===k); if(!rows.length) return '';
    return `<tr><td colspan="4" style="padding:4px 8px;font-weight:700;background:#f1f4ed">${TP_FULL[k].toUpperCase()} — ${BRL(tpt[k]||0)}</td></tr>`+rows.map(lin).join('');
  }).join('');
  return `<div style="padding:10px 12px">
    <div class="bv" style="font-size:12.5px;margin-bottom:7px">
      Tipo declarado <b>${esc(d.tipo)}</b> · ${d.metodo==='analitico'?'linha inteira':'composição'} · total <b>${BRL(d.total)}</b><br>
      <span style="color:var(--ok)">✓ ${certo} (coerente, FICA): <b>${BRL(d.tot_correto)}</b></span> &nbsp;·&nbsp;
      <span style="color:var(--pend)">✗ fora do tipo (embutido, SAI ao separar): <b>${BRL(d.tot_errado)}</b> (<b>${pctErr}%</b> do total)</span>
      ${d.sem_composicao?` &nbsp;·&nbsp; <span class="muted">⚠ ${d.sem_composicao} linha(s) sem composição (não detalhadas)</span>`:''}
    </div>
    <table style="width:100%;border-collapse:collapse;font-size:11.5px">
      <thead><tr style="border-bottom:1px solid var(--line)"><th style="text-align:left;padding:3px 8px">Insumo</th><th style="text-align:left;padding:3px 8px">Composição</th><th style="padding:3px 8px">Tipo</th><th style="text-align:right;padding:3px 8px">R$</th></tr></thead>
      <tbody>${grupos}</tbody></table>
    <div class="muted" style="font-size:11px;margin-top:5px">Linhas com fundo rosado / ⚠ são o lado <b>errado</b> pro tipo do item — é o que "Separar" remove daqui.</div>
  </div>`;
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
function progCard(label,done,total,icon,filtKey,noun){
  noun=noun||'curados';
  const falta=Math.max(total-done,0), pct=total?Math.round(done/total*100):0;
  const clk=(filtKey&&falta>0)?` onclick="progFiltrar('${filtKey}')" title="clique pra ver os ${falta} que faltam no radar"`:'';
  return `<div class="kpi" style="min-width:210px${filtKey&&falta>0?';cursor:pointer':''}"${clk}>
    <div class="l" style="display:flex;align-items:center;gap:6px;margin-bottom:2px"><span class="material-icons" style="font-size:16px;color:var(--dourado)">${icon}</span>${label}${filtKey&&falta>0?'<span class="material-icons" style="font-size:14px;color:var(--muted);margin-left:auto" title="filtrar o que falta">filter_alt</span>':''}</div>
    <div class="v">${done} <span class="muted" style="font-size:15px;font-weight:600">/ ${total}</span> ${noun}</div>
    <div style="height:7px;background:var(--line);border-radius:5px;overflow:hidden;margin:7px 0 4px"><div style="height:100%;width:${pct}%;background:var(--ok);transition:width .3s"></div></div>
    <div class="l">${pct}% feito · <b style="color:var(--pend)">faltam ${falta}</b></div>
  </div>`;
}
// clique numa card de progresso → vai pro radar, abre os filtros e mostra só o que FALTA naquela dimensão
function progFiltrar(dim){
  const id={crono:'fcrono', verba:'fcurada', quant:'fquant', resp:'frespo'}[dim]; if(!id) return;
  showView('radar');
  const adv=document.getElementById('advFilters'); if(adv) adv.style.display='flex';
  const val=(dim==='resp'?'sem':'nao');   // responsável: "sem"; curadorias: "não curado"
  ['fcrono','fcurada','fquant','frespo'].forEach(x=>{ const e=document.getElementById(x); if(e) e.value=(x===id?val:''); });
  render();
}
async function renderUpdates(){
  const box=document.getElementById('updwrap');
  box.innerHTML='<div class="empty">Carregando…</div>';
  try{ await load(); }catch(e){}                 // recarrega o matriz p/ os contadores ficarem frescos
  const its=DATA.itens||[], tot=its.length;
  const cards=`<div style="font-size:13px;color:var(--verde-d);font-weight:800;margin:0 0 8px">Progresso da curadoria</div>
    <div class="kpis" style="padding:0 0 16px">
      ${progCard('Cronograma', its.filter(i=>i.curado_data).length, tot, 'event', 'crono')}
      ${progCard('Orçamento (verba)', its.filter(i=>i.curado_verba).length, tot, 'request_quote', 'verba')}
      ${progCard('Quantitativo', its.filter(i=>i.curado_quant).length, tot, 'straighten', 'quant')}
      ${progCard('Responsável', its.filter(i=>(i.responsavel||'').trim()).length, tot, 'person', 'resp', 'com dono')}
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
      const it=byOrdem(h.servico_id, h.obra_id);   // obra EXPLÍCITA: senão o fallback MAT abriria a 1ª obra c/ essa ordem
      feed+=`<tr ${it?`onclick="openModal(${h.servico_id},${h.obra_id||1})" style="cursor:pointer"`:''}>
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
      body:JSON.stringify({ordem,campos:{orcamento_refs:novo},me:EU&&EU.bitrix_id,obra:OBRA_SEL[0]||1})})).json();
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
// cor por PRAZO DE COTAÇÃO (fim_cotacao) — reaproveita as classes de cor com semântica de prazo
function prazoClass(i){
  if(!i) return 'c-none';
  const lv=alertLevel(i);
  if(lv==='finalizado') return 'c-fin';
  if(!i.fim_cotacao) return 'c-none';
  if(lv==='critico') return 'c-atras';     // fim da cotação venceu
  if(lv==='atrasado') return 'c-pend';     // devia ter iniciado a cotação
  if(lv==='proximo')  return 'c-prop';     // vence em ≤7 dias
  return 'c-noprazo';                       // no prazo
}
const PRAZO_TXT={'c-fin':'Cotação finalizada','c-atras':'Prazo de cotação venceu','c-pend':'Devia ter iniciado a cotação','c-prop':'Vence em ≤7 dias','c-noprazo':'No prazo','c-none':'Sem data de cotação'};
const LEG_STATUS=[['c-fin','Finalizado'],['c-cot','Em cotação (no prazo)'],['c-andamento','Em andamento'],['c-prop','Proposta recebida'],['c-atras','Atrasado (passou do gatilho)'],['c-pend','Com pendências'],['c-noprazo','No prazo, não iniciado'],['c-none','N/A']];
const LEG_PRAZO=[['c-fin','Cotação finalizada'],['c-atras','Prazo de cotação venceu'],['c-pend','Devia ter iniciado a cotação'],['c-prop','Vence em ≤7 dias'],['c-noprazo','No prazo'],['c-none','Sem data de cotação']];
function renderMatriz(){
  if(!MAT){ loadMatriz(); return; }        // fonte própria da matriz (todas as obras) ainda não carregada
  const src=MAT, gv=id=>{const e=document.getElementById(id);return e?e.value:'';};
  const allObras=[...new Set(src.map(i=>i.obra_nome).filter(Boolean))];
  let obras=(MAT_SEL&&MAT_SEL.length)?allObras.filter(o=>MAT_SEL.includes(o)):allObras;
  if(MAT_OBRA_ORDER){ obras=obras.slice().sort((a,b)=>{ const ia=MAT_OBRA_ORDER.indexOf(a), ib=MAT_OBRA_ORDER.indexOf(b); return (ia<0?999:ia)-(ib<0?999:ib); }); }
  MAT_OBRAS_CUR=obras;   // p/ os handlers de arrastar mapearem índice→nome de obra
  const fg=gv('mgrupo'), fc=gv('mcurva'), fst=gv('mstatus'), fr=gv('mresp');
  const onlyAlert=(document.getElementById('malert')||{}).checked;
  const colorBy=gv('mcolor')||'status', orderBy=gv('morder')||'grupo';
  const q=(gv('mq')||'').toLowerCase();
  const clsFn=colorBy==='prazo'?prazoClass:cellClass, txtMap=colorBy==='prazo'?PRAZO_TXT:CELL_TXT;
  // legenda + subtítulo dinâmicos
  const lg=document.getElementById('mlegend'); if(lg) lg.innerHTML=(colorBy==='prazo'?LEG_PRAZO:LEG_STATUS).map(([c,t])=>`<span class="lg"><span class="sw ${c}"></span> ${esc(t)}</span>`).join('')
    +`<span style="width:1px;height:16px;background:var(--line);align-self:center;margin:0 2px"></span>`
    +`<span class="lg" title="dado confirmado manualmente (curadoria)"><span class="material-icons" style="font-size:14px;color:var(--ok)">verified</span> curado</span>`
    +`<span class="lg" title="dado sugerido pelo auto-vínculo (receita) — ainda não confirmado">🤖 auto-vínculo</span>`;
  const sub=document.getElementById('msub'); if(sub) sub.textContent=(colorBy==='prazo'?'Cor pelo PRAZO DE COTAÇÃO (data limite p/ fechar a cotação).':'Cor pelo STATUS de cada aquisição.')+' A data no centro de cada célula é o FIM DA COTAÇÃO.';
  // índice (ordem|obra) -> item
  const idx={}; src.forEach(i=>idx[i.ordem+'|'+i.obra_nome]=i);
  // serviços distintos (filtros de serviço: grupo/curva/busca), na ordem natural (= por grupo lógico)
  const filt=src.filter(i=>obras.includes(i.obra_nome)&&(!fg||i.grupo===fg)&&(!fc||i.curva===fc)&&(!q||i.nome.toLowerCase().includes(q)));
  const seen=new Map();
  for(const i of filt){ if(!seen.has(i.ordem)) seen.set(i.ordem,{ordem:i.ordem,nome:i.nome,grupo:i.grupo,curva:i.curva}); }
  // filtros de ITEM (status/responsável/alerta): mantém o serviço se ALGUM item das obras exibidas casa
  let servicos=[...seen.values()].filter(s=>{
    const its=obras.map(o=>idx[s.ordem+'|'+o]).filter(Boolean);
    if(fst && !its.some(i=>(i.status||'Não Iniciado')===fst)) return false;
    if(fr){ if(fr==='__sem__'){ if(!its.some(i=>!(i.responsavel||'').trim())) return false; } else if(!its.some(i=>(i.responsavel||'')===fr)) return false; }
    if(onlyAlert && !its.some(i=>isAlert(i))) return false;
    return true;
  });
  // organização
  const earliest=s=>{ const ds=obras.map(o=>idx[s.ordem+'|'+o]).filter(Boolean).map(i=>i.fim_cotacao).filter(Boolean).sort(); return ds.length?ds[0]:'9999-99-99'; };
  let agrupado=true;
  if(orderBy==='prazo'){ servicos.sort((a,b)=>earliest(a).localeCompare(earliest(b))); agrupado=false; }
  else if(orderBy==='nome'){ servicos.sort((a,b)=>a.nome.localeCompare(b.nome,'pt')); agrupado=false; }
  MAT_SVCS_CUR=servicos;   // p/ expandir/recolher todos
  const mc=document.getElementById('mctrl');
  if(mc) mc.innerHTML=(servicos.length&&obras.length)?`<div class="bar" style="gap:6px;flex-wrap:wrap;align-items:center;padding:0">
    <button class="btn-ghost" style="padding:4px 10px" onclick="matExpandAll(true)"><span class="material-icons" style="font-size:15px;vertical-align:-3px">unfold_more</span> Expandir serviços</button>
    <button class="btn-ghost" style="padding:4px 10px" onclick="matExpandAll(false)"><span class="material-icons" style="font-size:15px;vertical-align:-3px">unfold_less</span> Recolher serviços</button>
    <button class="btn-ghost" style="padding:4px 10px" onclick="matGrpAll(false)">Recolher grupos</button>
    <button class="btn-ghost" style="padding:4px 10px" onclick="matGrpAll(true)">Expandir grupos</button>
    <span class="muted" style="font-size:11px">— arraste os nomes das obras p/ reordenar · clique no ▸ de um serviço p/ ver quantitativo / verba / responsável / status</span></div>`:'';
  if(!servicos.length||!obras.length){document.getElementById('mwrap').innerHTML='<div class="empty">Sem dados para os filtros.</div>';return;}
  let html='<table class="mtable"><thead><tr><th class="svc-h">Serviço</th>'+
    obras.map((o,oi)=>`<th class="mo-th" draggable="true" ondragstart="matDragStart(event,${oi})" ondragover="event.preventDefault();this.classList.add('mo-drag')" ondragleave="this.classList.remove('mo-drag')" ondrop="matDrop(event,${oi})" title="arraste p/ reordenar">${esc(o)}</th>`).join('')+'</tr></thead><tbody>';
  let grupo=null, grpCol=false;
  for(const s of servicos){
    if(agrupado && s.grupo!==grupo){ grupo=s.grupo; grpCol=MAT_COLLAPSED.has(grupo);
      const n=servicos.filter(x=>x.grupo===grupo).length;
      html+=`<tr class="grp-h"><td colspan="${obras.length+1}" onclick="matGrpToggle(${jsArg(grupo)})" style="cursor:pointer"><span class="material-icons" style="font-size:15px;vertical-align:-3px">${grpCol?'chevron_right':'expand_more'}</span> ${esc(grupo)} <span class="muted" style="font-weight:400;font-size:10px">(${n})</span></td></tr>`;
    }
    if(agrupado && grpCol) continue;   // grupo recolhido: pula os serviços
    const isExp=MAT_EXP.has(Number(s.ordem));   // ordem pode vir string (MySQL) — normaliza p/ casar o Set
    html+=`<tr><td class="svc-c"><span class="material-icons" onclick="event.stopPropagation();matSvcToggle(${s.ordem})" title="ver detalhes" style="font-size:15px;vertical-align:-3px;cursor:pointer;color:var(--muted)">${isExp?'expand_more':'chevron_right'}</span>${esc(s.nome)}<small>Curva ${esc(s.curva||'—')}</small></td>`;
    for(const o of obras){
      const i=idx[s.ordem+'|'+o];
      if(fr){ const off = fr==='__sem__' ? (!i||(i.responsavel||'').trim()!=='') : (!i||(i.responsavel||'')!==fr);
        if(off){ html+='<td><div class="cell cell-off"></div></td>'; continue; } }
      if(!i){ html+=`<td><div class="cell c-empty" title="${esc(o)} · ${esc(s.nome)}\nsem este serviço nesta obra"><span class="cell-x">✕</span></div></td>`; continue; }
      const cls=clsFn(i);
      const dt=i&&i.fim_cotacao?(p=>p[2]+'/'+p[1]+'/'+p[0].slice(2))(i.fim_cotacao.split('-')):'';
      const tip=i?`${esc(o)} · ${esc(s.nome)}\n${txtMap[cls]||''}`+(i.fim_cotacao?` · fim cotação ${D(i.fim_cotacao)}`:'')+(i.responsavel?`\n${esc(i.responsavel)}`:''):'N/A';
      const click=i?`onclick="openModal(${i.ordem},${i.obra_id||1})"`:'';
      const inner=dt?`<span class="cell-dt">${dt}${matCurIcon('crono',i)}</span>`:'';
      html+=`<td><div class="cell ${cls}" title="${tip}" ${click}>${inner}</div></td>`;
    }
    html+='</tr>';
    // linha de DETALHE (quantitativo/verba/responsável/status por obra) quando o serviço está expandido
    if(isExp){
      html+=`<tr class="mexp"><td class="svc-c" style="background:#f7faf8;font-size:10px;color:var(--muted)">detalhe</td>`;
      for(const o of obras){ const i=idx[s.ordem+'|'+o];
        html+=`<td class="mexp-c">${i?matExpBlock(i):'<span class="muted" style="font-size:10px">—</span>'}</td>`; }
      html+='</tr>';
    }
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
  return gs.map(g=>`<option value="${esc(g)}" ${g===current?'selected':''}>${esc(g)}</option>`).join('')
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
// verba DEFINIDA (vinculada/curada) — 0 quando não há vínculo; a estimativa preliminar NÃO conta como verba (é só referência no modal)
function verbaDefinida(i){ return i.verba_override!=null && i.verba_override!==''; }
function verbaDef(i){ return verbaDefinida(i) ? +i.verba_override : 0; }
function groupHeaderHtml(g,items,idx){
  const collapsed=COLLAPSED.has(g);
  const n=items.length;
  const verba=items.reduce((s,i)=>s+(i.verba||0),0);          // BASE (definida ?? estimativa) — só denominador do chip de curadoria
  const verbaDefSum=items.reduce((s,i)=>s+verbaDef(i),0);      // só a verba DEFINIDA — é o que aparece no total do grupo
  const datas=items.map(i=>i.data_gatilho).filter(Boolean).sort();
  const prox=datas.length?` · próx. início ${D(datas[0])}`:'';
  // progresso de curadoria do grupo: verba curada (itens com verba_curada) / verba total do grupo
  const cur=items.reduce((s,i)=>s+(i.curado_verba?(i.verba||0):0),0);
  const nCur=items.filter(i=>i.curado_verba).length;
  const pctCur=verba>0?Math.round(cur/verba*100):0;
  const ccls=pctCur>=90?'ok':(pctCur>0?'mid':'');
  const chip=`<span class="gcur ${ccls}" title="Curado ${BRL(cur)} de ${BRL(verba)} (${pctCur}%) · ${nCur} de ${n} ${n>1?'itens':'item'} com verba curada">`
    +`<span class="gbar"><span style="width:${pctCur}%"></span></span>${pctCur}% curado${cur>0?` <small>· ${BRL(cur)}</small>`:''}</span>`;
  const adm=(IS_ADMIN && g!=='—')?`<span class="gctl">
      <button class="gbtn" title="subir grupo" ${idx<=0?'disabled':''} onclick="event.stopPropagation();grupoMover(${idx},-1)">▲</button>
      <button class="gbtn" title="descer grupo" ${idx>=GORDER.length-1?'disabled':''} onclick="event.stopPropagation();grupoMover(${idx},1)">▼</button>
      <button class="gbtn" title="renomear grupo" onclick="event.stopPropagation();grupoRenomear(${idx})"><span class="material-icons" style="font-size:14px">edit</span></button>
    </span>`:'';
  return `<tr class="grp" onclick="toggleGroup(${idx})"><td colspan="12"><span class="gwrap">
      <span class="material-icons gcaret">${collapsed?'chevron_right':'expand_more'}</span>
      <span class="gname">${esc(g)}</span>${adm}
      <span class="gcount">· ${n} ${n>1?'itens':'item'} · ${BRL(verbaDefSum)}${prox}</span>
      ${chip}
    </span></td></tr>`;
}
function render(){
  const q=(document.getElementById('q').value||'').toLowerCase();
  const fg=document.getElementById('fgrupo').value,fo='';   // obra agora é seleção de dados (chips), não filtro de linha
  const fc=document.getElementById('fcurva').value;
  const fs=document.getElementById('fstatus').value,fr=document.getElementById('fresp').value;
  const oa=document.getElementById('onlyalert').checked;
  const fcd=document.getElementById('fcurada')?document.getElementById('fcurada').value:'';
  const fcr=document.getElementById('fcrono')?document.getElementById('fcrono').value:'';
  const fqt=document.getElementById('fquant')?document.getElementById('fquant').value:'';
  const fre=document.getElementById('frespo')?document.getElementById('frespo').value:'';
  const flat=document.getElementById('fview').value==='lista';
  const _naf=[fo,fg,fc,fs,fr].filter(Boolean).length+(oa?1:0)+(fcd?1:0)+(fcr?1:0)+(fqt?1:0)+(fre?1:0);
  const _fb=document.getElementById('filtBadge'); if(_fb) _fb.textContent=_naf?` ·${_naf}`:'';
  const _respSet=new Set((typeof RESP!=='undefined'?RESP:[]).map(r=>r.nome));   // nomes de comprador cadastrados (Bitrix)
  const _temResp=i=>!!((i.responsavel||'').trim());
  const rows=DATA.itens.filter(i=>
    (!q||(i.nome+' '+(i.forma_contratacao||'')+' '+(i.responsavel||'')).toLowerCase().includes(q))&&
    (!fg||i.grupo===fg)&&(!fo||i.obra_nome===fo)&&(!fc||i.curva===fc)&&
    (!fs||(i.status||'Não Iniciado')===fs)&&(!fr||i.responsavel===fr)&&(!oa||isAlert(i))&&
    (!fcd||(fcd==='sim'?i.curado_verba:!i.curado_verba))&&
    (!fcr||(fcr==='sim'?i.curado_data:!i.curado_data))&&
    (!fqt||(fqt==='sim'?i.curado_quant:!i.curado_quant))&&
    (!fre||(fre==='com'?_temResp(i):fre==='sem'?!_temResp(i):fre==='naocad'?(_temResp(i)&&!_respSet.has((i.responsavel||'').trim())):true)));
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
  const obTag=(OBRA_SEL.length>1)?`<span style="display:inline-block;font-size:9px;font-weight:800;color:#fff;background:${obraCor(i.obra_id)};border-radius:4px;padding:1px 6px;vertical-align:1px;margin-right:4px">${esc((i.obra_nome||'').slice(0,10))}</span>`:'';
  return `<tr class="item" onclick="openModal(${i.ordem},${i.obra_id||1})">
    <td><div class="svc">${obTag}${esc(i.nome)} ${tipoChip(i.tipo)}</div></td>
    <td><span class="curva c-${i.curva||'C'}">${esc(i.curva||'—')}</span></td>
    <td>${i.responsavel?esc(i.responsavel):`<button class="resp-miss" onclick="event.stopPropagation();openModal(${i.ordem},${i.obra_id||1})">definir</button>`}</td>
    <td class="money">${verbaDefinida(i)?`${BRL(verbaDef(i))}${i.curado_verba?' <span class="material-icons" title="verba curada" style="font-size:13px;color:var(--ok);vertical-align:-2px">verified</span>':(i.auto&&i.auto.verba?' <span title="sugerido pelo auto-vínculo (receita) — confira e salve pra confirmar" style="font-size:11px">🤖</span>':'')}`:`<span class="muted" title="sem verba definida — a estimativa preliminar do orçamento não conta como verba">R$ 0 <span style="font-size:10px">· a definir</span></span>`}</td>
    <td>${i.quantitativo!=null?`<div class="qcell" title="${esc(QNUM(i.quantitativo)+' '+(i.quantitativo_unidade||''))}"><b>${QNUM(i.quantitativo)}</b> <span class="muted">${esc(i.quantitativo_unidade||'')}</span>${i.curado_quant?' <span class="material-icons" title="quantitativo curado" style="font-size:13px;color:var(--ok);vertical-align:-2px">verified</span>':(i.auto&&i.auto.quant?' <span title="sugerido pelo auto-vínculo (receita)" style="font-size:11px">🤖</span>':'')}</div>`:'<span class="muted">—</span>'}</td>
    <td class="date">${D(i.data_necessaria)}${i.curado_data?' <span class="material-icons" title="data curada" style="font-size:12px;color:var(--ok);vertical-align:-2px">verified</span>':(i.auto&&i.auto.crono?' <span title="sugerido pelo auto-vínculo (receita) — abra o Cronograma e salve pra confirmar" style="font-size:11px">🤖</span>':'')}</td>
    <td>${pctChip(i.cronograma_pct)}</td>
    <td class="date">${D(i.inicio_cotacao)}${chipIni?'<br>'+chipIni:''}</td>
    <td class="date">${D(i.fim_cotacao)}${chipFim?'<br>'+chipFim:''}</td>
    <td>${statusSelect(i)}</td>
    <td>${cotCell(i)}</td>
    <td onclick="event.stopPropagation()"><button class="eye" onclick="openModal(${i.ordem},${i.obra_id||1})"><span class="material-icons" style="font-size:17px;line-height:28px">visibility</span></button></td>
  </tr>`;
}
// Coluna "Mapa": AUTOMÁTICA — reflete a existência REAL de um mapa de cotação vinculado ao item (servico_id).
function cotCell(i){
  const c=i.cotacao;
  if(c){
    const stc={aberta:['var(--cot)','em cotação'],aguardando:['var(--dourado)','aguardando'],finalizada:['var(--ok)','fechada']};
    const x=stc[c.status]||['var(--cot)','em cotação'];
    const resp=(c.convidados||c.respostas)?` <span style="font-weight:600;opacity:.85">${c.respostas||0}/${c.convidados||0}</span>`:'';
    return `<span onclick="event.stopPropagation();cotAbrir(${c.id})" title="Abrir mapa de cotação — ${esc(c.titulo||'')}${c.melhor?(' · melhor '+BRL(c.melhor)):''}${c.n>1?(' · '+c.n+' cotações neste item'):''}" style="cursor:pointer;color:${x[0]};font-weight:800;white-space:nowrap;font-size:11.5px">● ${x[1]}${resp}</span>`;
  }
  return i.fornecedor?`<span class="mapa-on" title="fornecedor informado manualmente — ainda sem mapa de cotação vinculado">● ${esc(i.fornecedor)}</span>`:'<span class="muted">—</span>';
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
      body:JSON.stringify({ordem,campos:{[campo]:valor},me:EU&&EU.bitrix_id,obra:OBQ()})});
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
let CAN_CRONO=false, CAN_ORC=false, CAN_QUANT=false, CAN_DIC=false, CAN_RESP=false; // permissões específicas (vínculos + dicionário + responsáveis em lote)
let EU=null;                             // usuário logado + permissões efetivas
function openModal(o,ob){CUR=byOrdem(o,ob);if(!CUR)return;TAB='Resumo';EDITC=EDITO=EDITQ=EDITD=EDITR=false;drawModal();document.getElementById('ov').classList.add('open');}
function closeModal(force){ if(!force && anyEditing()){ confirmSaveDialog(async()=>{ await saveCurrentEdit(); _closeModal(); }, ()=>{ _resetEdits(); _closeModal(); }); return; } _resetEdits(); _closeModal(); }
function _closeModal(){document.getElementById('ov').classList.remove('open');render();renderMatriz();}
function _resetEdits(){ EDITC=EDITO=EDITQ=EDITD=EDITR=false; }
function anyEditing(){ return EDITC||EDITO||EDITQ||EDITD||EDITR; }
// qual função de salvar corresponde à edição ativa (considera a fonte no Orçamento/Quantitativo)
function currentSaveFn(){
  if(EDITR) return resumoSalvar;
  if(EDITC) return cronoSalvar;
  if(EDITO) return ORCFONTE==='composicao'?compSalvar:orcSalvar;
  if(EDITQ) return QNTFONTE==='composicao'?qcompSalvar:(QNTFONTE==='analitico'?qntSalvar:qntManualSalvar);
  if(EDITD) return dicSalvar;
  return null;
}
async function saveCurrentEdit(){ const fn=currentSaveFn(); if(fn){ try{ await fn(); }catch(e){ toast('Falha ao salvar'); } } }
function cancelCurrentEdit(){ _resetEdits(); drawModal(); }
function setTab(t){ if(t===TAB) return;
  if(anyEditing()){ confirmSaveDialog(async()=>{ await saveCurrentEdit(); _setTab(t); }, ()=>{ _resetEdits(); _setTab(t); }); return; }
  _setTab(t); }
function _setTab(t){ TAB=t; EDITC=EDITO=EDITQ=EDITD=EDITR=false; drawModal(); }
// barra FIXA no topo do corpo do modal: quando editando, Salvar/Cancelar sempre à mão (sem rolar até o fim)
function editActionBar(){
  if(!anyEditing()) return '';
  return `<div class="editbar-top">
    <button class="btn-prim" style="padding:7px 14px" onclick="saveCurrentEdit()"><span class="material-icons" style="font-size:16px;vertical-align:-3px">save</span> Salvar</button>
    <button class="btn-ghost" onclick="cancelCurrentEdit()">Cancelar</button>
    <span class="muted" style="font-size:11px;margin-left:auto"><span class="material-icons" style="font-size:13px;vertical-align:-2px;color:var(--and)">edit</span> editando — salve pra não perder</span>
  </div>`;
}
// diálogo 3 opções ao sair da aba/fechar com edição pendente
function confirmSaveDialog(onSave, onDiscard){
  const d=document.createElement('div'); d.className='savedlg-ov';
  d.innerHTML=`<div class="savedlg">
    <div class="savedlg-t"><span class="material-icons" style="font-size:18px;vertical-align:-4px;color:var(--and)">warning</span> Alterações não salvas</div>
    <div class="savedlg-m">Você está editando e ainda não salvou. O que deseja fazer?</div>
    <div class="savedlg-b">
      <button class="btn-prim" data-a="save"><span class="material-icons" style="font-size:16px;vertical-align:-3px">save</span> Salvar e sair</button>
      <button class="btn-ghost" data-a="discard">Sair sem salvar</button>
      <button class="btn-ghost" data-a="stay">Continuar editando</button>
    </div></div>`;
  document.body.appendChild(d);
  d.addEventListener('click',e=>{ const b=e.target.closest('[data-a]'); if(!b && e.target!==d) return; const a=b?b.dataset.a:'stay'; d.remove(); if(a==='save') onSave(); else if(a==='discard') onDiscard(); });
}
function drawModal(){
  const i=CUR;if(!i)return;
  const tabs=['Resumo','Cronograma','Orçamento','Quantitativo','Dicionário','Mapa de cotação','Histórico'];
  document.getElementById('modal').innerHTML=`
    <div class="mhead">
      <button class="mclose" onclick="closeModal()">×</button>
      <div class="crumb"><span style="background:${obraCor(i.obra_id)};border-radius:5px;padding:1px 8px;font-weight:800">${esc(i.obra_nome||'')}</span> · ${esc(i.grupo||'')} · Curva ${esc(i.curva||'—')}</div>
      <div class="mt">${esc(i.nome)}</div>
      <div class="meta">
        <span><span class="material-icons">person</span>${esc(i.responsavel||'sem responsável')}</span>
        <span><span class="material-icons">straighten</span>${esc((i.curado_quant&&i.quantitativo_unidade)?i.quantitativo_unidade:(i.unidade||'—'))}</span>
        <span><span class="material-icons">event</span>obra: ${D(i.data_necessaria)}</span>
        <span><span class="material-icons">schedule</span>lead: ${i.lead_efetivo?i.lead_efetivo+' d':'—'}</span>
      </div>
    </div>
    <div class="tabs">${tabs.map(t=>`<button class="tab ${t===TAB?'active':''}" onclick="setTab('${t}')">${t}</button>`).join('')}</div>
    <div class="tabbody">${editActionBar()}${tabBody(i)}</div>`;
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
  const d=await (await fetch('actions/crono_tree.php?obra='+OBQ())).json();
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
  const d=await (await fetch('actions/crono_tree.php?obra='+OBQ()+'&children_of='+encodeURIComponent(n.outline))).json();
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
let CRONO_DEB=null, CRONO_SEQ=0;
function cronoBuscar(){ clearTimeout(CRONO_DEB); CRONO_DEB=setTimeout(cronoBuscarNow,280); }   // debounce: menos carga no Supabase + evita corrida
async function cronoBuscarNow(){
  const q=(document.getElementById('cronoQ')||{}).value; if(q==null){return;} const qt=q.trim();
  const box=document.getElementById('cronoSearch'); if(!box)return;
  if(qt.length<2){box.innerHTML='';return;}
  const my=++CRONO_SEQ;
  box.innerHTML='<div class="muted" style="font-size:12px;padding:4px">Buscando…</div>';
  try{
    const d=await (await fetch('actions/crono_search.php?obra='+OBQ()+'&q='+encodeURIComponent(qt))).json();
    if(my!==CRONO_SEQ) return;   // resposta atrasada de uma tecla anterior — descarta (senão sobrescreve os resultados certos com lixo)
    if(d.error){box.innerHTML='<div class="muted" style="font-size:12px;padding:4px;color:var(--pend)">Erro na busca: '+esc(d.error)+'</div>';return;}
    CRONO_SEARCH=(d.tarefas||[]).map(t=>({outline:t.outline_number||t.wbs,nome:t.nome,start:t.start,wbs:t.wbs,path:t.path,summary:t.is_summary}));
    if(!CRONO_SEARCH.length){box.innerHTML='<div class="muted" style="font-size:12px;padding:4px">Nada encontrado.</div>';return;}
    box.innerHTML='<div class="srbox">'+CRONO_SEARCH.map(t=>`
      <div class="pickrow" onclick="cronoSelecionar('${esc(t.outline)}')" style="align-items:flex-start">
        <span class="material-icons" style="font-size:16px;color:var(--verde);margin-top:2px">radio_button_checked</span>
        <div style="min-width:0"><div>${esc(t.nome)}${t.summary?' <span style="font-size:9px;font-weight:700;background:var(--verde);color:#fff;border-radius:4px;padding:1px 5px;vertical-align:1px">GRUPO</span>':''}</div>
          ${t.path?`<small class="muted" style="display:block"><span class="material-icons" style="font-size:11px;vertical-align:-1px;color:var(--dourado)">place</span> ${esc(t.path)}</small>`:''}
          <small class="muted">WBS ${esc(t.wbs||'—')} · ${D(t.start)}</small></div>
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
  const d=await (await fetch('actions/composicao.php?obra='+OBQ()+'&q='+encodeURIComponent(q))).json();
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
      ${tpBadge(in_.tipo)}
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
      ${tpBadge(s.tipo)}
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
      cesta.map((s,si)=>{const qq=(s.area||0)*(s.coef||0); qval+=qq; const ld=insumoLocaisDet(s, locMap);
        return `<div class="pickrow" style="align-items:flex-start">${tpBadge(s.tipo)}
          <div style="flex:1;min-width:0"><div>${esc(s.desc)}</div>
            <small class="muted">${QNUM(s.area)} × ${QNUM(s.coef)} = <b>${QNUM(qq)} ${esc(s.unidade||'')}</b>${s.compdesc?' · '+esc(s.compdesc.slice(0,40)):''}</small>${locDet(ld,'q'+si)}</div></div>`;}).join('');
    if(tot2) tot2.textContent='Soma: '+QNUM(qval)+' '+(i.quantitativo_unidade||'');
    return;
  }
  // 3) analítico — linhas do orçamento selecionadas (caminho + qtde)
  QNT_SEL=new Set((i.quantitativo_refs||[]).map(Number));
  if(QNT_SEL.size) await qntRenderSel();
}
async function qntLoadTree(){
  const box=document.getElementById('qntTree'); if(!box)return;
  const d=await (await fetch('actions/orcamento.php?obra='+OBQ())).json();
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
  const d=await (await fetch('actions/orcamento.php?obra='+OBQ()+'&children_of='+encodeURIComponent(n.codigo))).json();
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
  const d=await (await fetch('actions/orcamento.php?obra='+OBQ()+'&ids='+[...QNT_SEL].join(','))).json();
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
  const d=await (await fetch('actions/orcamento.php?obra='+OBQ()+'&q='+encodeURIComponent(q))).json();
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
let ORC_SEL=new Set(), ORC_NODES=[], ORC_EXCL=[];
function orcTab(i){
  const MET={analitico:'linhas do orçamento (analítico)', composicao:'composição de insumos', manual:'manual'};
  const metodo = MET[i.verba_metodo] || 'estimativa preliminar (a curar)';
  const editBar = !EDITO ? `<div style="display:flex;gap:8px;flex-wrap:wrap;margin:6px 0 10px">${
    CAN_ORC ? `<button class="btn-prim" onclick="orcEditar()"><span class="material-icons" style="font-size:16px">link</span> Editar vínculo de verba</button>`+(i.verba_metodo?`<button class="btn-ghost" onclick="orcLimpar()">↺ Limpar</button>`:'')+(i.verba_metodo==='analitico'?`<button class="btn-ghost" onclick="separarMO()" title="Tira a mão de obra embutida nas linhas inteiras, deixando o item só com o material — a MO fica livre pro item de Mão de Obra"><span class="material-icons" style="font-size:15px;vertical-align:-3px">content_cut</span> Separar material × MO</button>`:'')
            : `<span class="muted" style="font-size:12.5px"><span class="material-icons" style="font-size:15px;vertical-align:-3px">lock</span> Você não tem permissão para editar a verba.</span>`
  }</div>` : '';
  const semDef = !i.verba_metodo;   // sem vínculo/definição → o número é só a ESTIMATIVA PRELIMINAR do orçamento, não verba curada
  let h=`
    <div class="box"><div class="bl">Verba atual</div>
      ${semDef
        ? `<div class="bv"><b style="font-size:15px;color:var(--and)">Sem verba definida</b> <span style="color:var(--and);font-size:12px">· a curar</span></div>
           <div class="muted" style="font-size:12px;margin-top:5px;line-height:1.5">Estimativa preliminar do orçamento: <b>${BRL(i.verba)}</b> — apenas <b>referência</b> (NÃO conta como verba enquanto você não vincular; na lista aparece R$ 0). Clique em <b>“Editar vínculo de verba”</b> pra montar a verba do zero.</div>`
        : `<div class="bv"><b style="font-size:16px">${BRL(i.verba)}</b> <span class="muted" style="font-size:12px">— método: ${metodo}</span>${i.curado_verba?'<span style="color:var(--ok);font-weight:700;font-size:12px"> · curada ✓</span>':'<span style="color:var(--and);font-size:12px"> · a curar</span>'}</div>`}
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
function orcEditar(){ EDITO=true; VERBA_USOS=null; ORC_NODES=[]; ORCFONTE=(CUR.verba_metodo==='composicao'?'composicao':'analitico'); COMP_SEL=(CUR.composicao_sel||[]).map(s=>({...s})); ORC_EXCL=(CUR.orcamento_excl||[]).map(e=>({l:Number(e.l),d:e.d})); COMP_DATA=null; drawModal(); }
function orcCancelar(){ EDITO=false; drawModal(); }
async function orcLoadLastChange(ordem){
  const box=document.getElementById('orcLastChange'); if(!box)return;
  try{
    const d=await (await fetch('actions/historico.php?obra='+OBQ()+'&ordem='+ordem)).json();
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
    const d=await (await fetch('actions/historico.php?obra='+OBQ()+'&ordem='+ordem)).json();
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
          <div class="muted" style="font-size:11.5px;margin-bottom:6px">Pra insumo/MO pulverizado em muitas composições (ex.: encanador dentro de cada peça). <b>Recorte por SISTEMA</b> (gás, esgoto, água fria…) <b>e por TIPO</b> (material × mão de obra) pra separar limpo. Já usado em outro item = 🔒.</div>
          <div style="display:flex;gap:6px;flex-wrap:wrap;margin-bottom:6px;align-items:center">
            <span class="muted" style="font-size:11.5px">Atalho:</span>
            <button class="btn-ghost" style="padding:5px 10px" onclick="insMassaPreset('encanador')">👷 MO hidráulica (encanador)</button>
            <button class="btn-ghost" style="padding:5px 10px" onclick="insMassaPreset('eletricista')">⚡ MO elétrica (eletricista)</button>
            <button class="btn-ghost" style="padding:5px 10px" onclick="insMassaPresetSis('Gás','material')">🔥 Materiais de gás</button>
            <button class="btn-ghost" style="padding:5px 10px" onclick="insMassaPresetSis('Gás','mo')">🔥 MO de gás</button>
          </div>
          <div style="display:flex;gap:7px;flex-wrap:wrap;align-items:center">
            <select id="insMassaSis" style="border:1px solid var(--line);border-radius:8px;padding:7px 8px;font-size:12.5px" title="recorta por subsistema (pelo local no orçamento)">
              <option value="">Todos os sistemas</option>
              <option value="Gás">🔥 Gás</option>
              <option value="Água Fria">💧 Água Fria</option>
              <option value="Água Quente">♨️ Água Quente</option>
              <option value="Esgoto / Sanitário">🚽 Esgoto / Sanitário</option>
              <option value="Águas Pluviais">🌧️ Águas Pluviais</option>
              <option value="Incêndio">🧯 Incêndio</option>
              <option value="Hidráulica (geral)">🔧 Hidráulica (geral)</option>
              <option value="Outras">Outras</option>
            </select>
            <select id="insMassaTipo" style="border:1px solid var(--line);border-radius:8px;padding:7px 8px;font-size:12.5px" title="material × mão de obra">
              <option value="">Material + MO</option>
              <option value="material">Só materiais</option>
              <option value="mo">Só mão de obra</option>
            </select>
            <input id="insMassaTermos" style="flex:1;min-width:130px;border:1px solid var(--line);border-radius:8px;padding:7px 9px;font-size:12.5px" placeholder="termo (opcional se escolher um sistema)">
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
          <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:7px;align-items:center">
            <span class="muted" style="font-size:11.5px"><span class="material-icons" style="font-size:14px;vertical-align:-3px;color:var(--dourado)">call_split</span> O que entra na verba:</span>
            <select id="massaTipo" style="border:1px solid var(--line);border-radius:8px;padding:6px 9px;font-size:12px"><option value="inteira">Linha inteira (material + MO)</option><option value="material">Só o material</option><option value="mo">Só a mão de obra</option></select>
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
function locDet(ld, key){
  if(!ld||!ld.grupos||!ld.grupos.length) return '';
  const un=(ld.grupos[0]&&ld.grupos[0].unidade)||'';
  const det=ld.grupos.map(g=>`<div style="margin-top:3px"><b style="font-size:11px;color:var(--verde-d)">${esc(g.local)} — ${QNUM(g.area)} ${esc(g.unidade||un)}</b>`
      +(g.linhas||[]).map(l=>`<div style="font-size:10.5px;color:var(--muted);padding-left:12px;line-height:1.5">• ${esc((l.sub||'').slice(0,84))} — ${QNUM(l.qtde)} ${esc(l.unidade||un)}</div>`).join('')+`</div>`).join('');
  return `<div class="muted" style="font-size:11px;margin-top:4px;line-height:1.5">
      <span class="material-icons" style="font-size:12px;vertical-align:-2px;color:var(--dourado)">place</span>
      ${ld.todos?'<b>todos os locais</b> · ':''}${ld.n} local(is) · <b>${QNUM(ld.total)} ${esc(un)}</b>
      <a onclick="locToggle('${key}')" style="cursor:pointer;color:var(--verde);font-weight:600;white-space:nowrap"> · detalhar ▸</a>
      <div id="locdet-${key}" style="display:none;margin-top:2px">${det}</div></div>`;
}
function locToggle(key){ const e=document.getElementById('locdet-'+key); if(e) e.style.display=e.style.display==='none'?'block':'none'; }
async function loadCompLocais(compSel){   // baixa os locais de cada composição envolvida (1 fetch por composição)
  const cids=[...new Set((compSel||[]).map(s=>s.cid).filter(Boolean))]; const m={};
  await Promise.all(cids.map(async cid=>{ try{ m[cid]=await (await fetch('actions/composicao_locais.php?id='+cid)).json(); }catch(e){} }));
  return m;
}
function insumoLocaisDet(s, locMap){   // resolve os LOCAIS SELECIONADOS (s.locais = ids das linhas) em detalhe agrupado por topo
  const L=locMap&&locMap[s.cid]; const grupos=(L&&L.grupos)||[];
  const sel=(Array.isArray(s.locais)&&s.locais.length)?new Set(s.locais.map(Number)):null;  // null = não restringiu = todos os locais
  const out=[]; let total=0, n=0;
  grupos.forEach(g=>{ const linhas=(g.linhas||[]).filter(l=> sel? sel.has(Number(l.id)) : true);
    if(!linhas.length) return;
    const area=linhas.reduce((a,l)=>a+(l.qtde||0),0); total+=area; n+=linhas.length;
    out.push({local:g.local, area, unidade:(linhas[0]&&linhas[0].unidade)||g.unidade||'', linhas});
  });
  return {grupos:out, total, n, todos:!sel};
}
async function orcShowCurrent(i){
  // composição: RELATÓRIO de conferência (resumo por tipo + agrupado por insumo, cada um com seus locais) — read-only
  if(i.verba_metodo==='composicao' && (i.composicao_sel||[]).length){
    orcRenderComposicaoLeitura(i);
    return;
  }
  ORC_SEL=new Set((i.orcamento_refs||[]).map(Number));
  if(EDITO && ORCFONTE==='analitico') await orcLoadEditConf(i);   // edição: conferência interativa (tirar/incluir insumo)
  else if(!EDITO && ORC_SEL.size) await orcRenderBreakdown(i);    // read-only: quebra por tipo (mat/MO/equip) + por linha
  else await orcRenderSel();
}
// RELATÓRIO da verba por COMPOSIÇÃO (read-only): resumo por tipo + total no topo, agrupado por insumo,
// cada grupo expansível mostrando as composições-mãe e os LOCAIS (a partir do locais_det gravado no servidor).
let ORC_CLEITURA=null;
function orcRenderComposicaoLeitura(i){
  const el=document.getElementById('orcSel'); if(!el)return;
  const sel=i.composicao_sel||[];
  let vmat=0, vmo=0, vout=0; const locaisSet=new Set(); const groups={};
  sel.forEach(s=>{ const c=(s.area||0)*(s.coef||0)*(s.rs_unit||0);
    if(s.tipo==='mo') vmo+=c; else if(s.tipo==='material') vmat+=c; else vout+=c;
    (s.locais_det||[]).forEach(g=>locaisSet.add(g.local));
    const k=(s.desc||'?')+'|'+(s.tipo||''); if(!groups[k]) groups[k]={desc:s.desc||'(insumo)',tipo:s.tipo,total:0,entries:[]};
    groups[k].total+=c; groups[k].entries.push(Object.assign({custo:c}, s)); });
  const total=vmat+vmo+vout;
  ORC_CLEITURA=Object.values(groups).sort((a,b)=>b.total-a.total);
  const chip=(lbl,v,col)=> v>0.5?`<span style="white-space:nowrap">${lbl} <b style="color:${col}">${BRL(v)}</b></span>`:'';
  const resumo=`<div class="box" style="background:#fbfdf9;border-color:var(--ok);margin-bottom:8px">
    <div class="bv" style="font-size:12.5px;display:flex;gap:14px;flex-wrap:wrap;align-items:center">
      ${chip('Material',vmat,'var(--azul)')} ${chip('Mão de obra',vmo,'var(--dourado)')} ${chip('Outros',vout,'var(--muted)')}
      <span style="white-space:nowrap;margin-left:auto">Total <b style="font-size:14px">${BRL(total)}</b></span></div>
    <div class="muted" style="font-size:11px;margin-top:4px">${sel.length} insumo(s) · ${ORC_CLEITURA.length} tipo(s) de insumo · ${locaisSet.size} local(is) — clique num insumo pra ver as composições e locais</div></div>`;
  const linhas=ORC_CLEITURA.map((g,gi)=>{
    const nLoc=new Set(); g.entries.forEach(e=>(e.locais_det||[]).forEach(x=>nLoc.add(x.local)));
    return `<div style="border-bottom:1px solid var(--line)">
      <div class="pickrow" style="cursor:pointer;gap:6px" onclick="orcCLExpand(${gi})">
        <span class="material-icons" id="clcar-${gi}" style="font-size:17px;color:var(--muted)">chevron_right</span>${tpBadge(g.tipo)}
        <div style="flex:1;min-width:0"><div>${esc(g.desc)}</div><small class="muted">${g.entries.length} composição(ões)${nLoc.size?' · '+nLoc.size+' local(is)':''}</small></div>
        <span class="money">${BRL(g.total)}</span></div>
      <div id="clins-${gi}" style="display:none;padding:0 0 8px 30px"></div></div>`;
  }).join('');
  el.innerHTML=resumo+linhas;
  const t=document.getElementById('orcTotal'); if(t) t.innerHTML='';
}
function orcCLExpand(gi){
  const ins=document.getElementById('clins-'+gi), car=document.getElementById('clcar-'+gi); if(!ins||!ORC_CLEITURA)return;
  const show=ins.style.display==='none'; ins.style.display=show?'block':'none'; if(car) car.textContent=show?'expand_more':'chevron_right';
  if(show && !ins.dataset.loaded){
    const g=ORC_CLEITURA[gi];
    ins.innerHTML=g.entries.map(e=>{
      const locs=(e.locais_det||[]).map(x=>`${esc(x.local)} <span class="muted">(${QNUM(x.qtde)} ${esc(x.unidade||'')})</span>`).join(' · ') || '<span class="muted">todos os locais da composição</span>';
      return `<div style="padding:5px 0;border-bottom:1px dashed var(--line)">
        <div style="font-size:11.5px"><span class="material-icons" style="font-size:12px;vertical-align:-2px;color:var(--verde)">category</span> ${esc((e.compdesc||'').slice(0,52)||'(composição)')} <span class="money" style="float:right">${BRL(e.custo)}</span></div>
        <div style="font-size:11px;margin-top:2px"><span class="material-icons" style="font-size:12px;vertical-align:-2px;color:var(--dourado)">place</span> ${locs}</div>
        <div class="muted" style="font-size:10.5px;margin-top:1px">${QNUM(e.area)} × ${QNUM(e.coef)} × ${BRL(e.rs_unit)}${e.q?' · define o quantitativo':''}</div></div>`;
    }).join('');
    ins.dataset.loaded='1';
  }
}
let ORC_BD=null;
// verba analítica (linhas inteiras) → quebra em Material/MO/Equipamento, com total no topo, ▸ por linha e "agrupar por tipo"
async function orcRenderBreakdown(i){
  const el=document.getElementById('orcSel'), tot=document.getElementById('orcTotal'); if(!el)return;
  el.innerHTML='<div class="muted" style="font-size:12px;padding:4px">Detalhando material × MO × equipamento…</div>';
  let d; try{ d=await (await fetch('actions/verba_breakdown.php?obra='+OBQ()+'&ordem='+i.ordem)).json(); }catch(e){ await orcRenderSel(); return; }
  if(d.error){ await orcRenderSel(); return; }
  ORC_BD=d; const tp=d.tot_por_tipo;
  const totHtml=['material','mo','mat_mo','equip'].filter(k=>tp[k]>0.5).map(k=>`${tpBadge(k)} <b>${BRL(tp[k])}</b>`).join(' &nbsp; ')+` &nbsp;·&nbsp; <b>Total ${BRL(d.total)}</b>`;
  const linhas=d.linhas.map((l,li)=>{
    const sub=['material','mo','mat_mo','equip'].filter(k=>l.tot_por_tipo[k]>0.5).map(k=>tpLabel(k)+' '+BRL(l.tot_por_tipo[k])).join(' · ');
    return `<div style="border-bottom:1px solid var(--line)">
      <div class="pickrow" style="cursor:pointer;gap:6px" onclick="orcBdExpand(${li})">
        <span class="material-icons" id="bdcar-${li}" style="font-size:17px;color:var(--muted)">chevron_right</span>
        <div style="flex:1;min-width:0"><div>${esc(l.descricao)}</div><small class="muted">${esc((l.path||'').slice(0,58))} · ${sub}${l.sem_composicao?' · <span style="color:var(--and)">linha direta (sem composição)</span>':''}</small></div>
        <span class="money">${BRL(l.valor)}</span></div>
      <div id="bdins-${li}" style="display:none;padding:0 0 8px 30px"></div></div>`;
  }).join('');
  el.innerHTML=`<div class="box" style="background:#fbfdf9;border-color:var(--ok);margin-bottom:8px"><div class="bv" style="font-size:12.5px">${totHtml}</div></div>
    <div style="margin-bottom:6px"><button class="btn-ghost" style="padding:4px 10px;font-size:12px" onclick="orcBdAgrupar()"><span class="material-icons" style="font-size:15px;vertical-align:-3px">category</span> Ver tudo agrupado por tipo</button></div>
    <div id="bdAgr" style="display:none;margin-bottom:8px"></div>${linhas}`;
  if(tot) tot.innerHTML='';
}
function orcBdExpand(li){
  const ins=document.getElementById('bdins-'+li), car=document.getElementById('bdcar-'+li); if(!ins||!ORC_BD)return;
  const show=ins.style.display==='none'; ins.style.display=show?'block':'none'; if(car) car.textContent=show?'expand_more':'chevron_right';
  if(show && !ins.dataset.loaded){
    const l=ORC_BD.linhas[li];
    ins.innerHTML='<table style="width:100%;font-size:11px;border-collapse:collapse">'+l.insumos.map(x=>
      `<tr style="${x.excl?'opacity:.45':''}"><td style="padding:2px 4px;width:42px">${tpBadge(x.tipo)}</td><td style="padding:2px 4px;${x.excl?'text-decoration:line-through':''}">${esc((x.desc||'').slice(0,42))}${x.excl?' <span style="color:var(--and);font-size:9px;text-decoration:none">· fora</span>':''}</td>`+
      `<td style="padding:2px 4px;text-align:right;color:var(--muted);white-space:nowrap">${QNUM(x.qtde)} ${esc(x.unidade||'')} × ${BRL(x.rs_unit)}</td>`+
      `<td style="padding:2px 6px;text-align:right;white-space:nowrap;${x.excl?'text-decoration:line-through':''}">${BRL(x.valor)}</td></tr>`).join('')+'</table>';
    ins.dataset.loaded='1';
  }
}
function orcBdAgrupar(){
  const box=document.getElementById('bdAgr'); if(!box||!ORC_BD)return;
  const show=box.style.display==='none'; box.style.display=show?'block':'none';
  if(show && !box.dataset.loaded){
    const pt=ORC_BD.por_tipo;
    box.innerHTML=['material','mo','mat_mo','equip'].map(k=>{ const arr=pt[k]||[]; if(!arr.length)return'';
      const sub=arr.reduce((a,x)=>a+x.valor,0);
      return `<div class="box" style="margin-bottom:6px"><div class="bl">${tpBadge(k)} ${TP_FULL[k]} — ${BRL(sub)} <span class="muted" style="font-weight:400">(${arr.length})</span></div>`+
        '<table style="width:100%;font-size:11px;border-collapse:collapse">'+arr.map(x=>
        `<tr><td style="padding:2px 4px">${esc((x.desc||'').slice(0,46))}</td><td style="padding:2px 4px;text-align:right;color:var(--muted);white-space:nowrap">${QNUM(x.qtde)} ${esc(x.unidade||'')}</td><td style="padding:2px 6px;text-align:right;white-space:nowrap">${BRL(x.valor)}</td></tr>`).join('')+'</table></div>';
    }).join('');
    box.dataset.loaded='1';
  }
}
// ===== Conferência interativa de insumos na EDIÇÃO analítica: abre a linha e tira/inclui insumo (ex.: espaçador) =====
async function orcLoadEditConf(i){
  const el=document.getElementById('orcSel'); if(!el)return;
  const t=document.getElementById('orcTotal');
  if(!ORC_SEL.size){ el.innerHTML='<span class="muted">Nenhuma linha na verba ainda. Use a busca ou a árvore abaixo para adicionar linhas do orçamento.</span>'; if(t)t.textContent=''; return; }
  el.innerHTML='<div class="muted" style="font-size:12px;padding:4px">Conferindo insumos…</div>';
  const refs=[...ORC_SEL].join(',');
  const excl=encodeURIComponent(JSON.stringify((ORC_EXCL||[]).filter(e=>ORC_SEL.has(Number(e.l)))));
  let d; try{ d=await (await fetch(`actions/verba_breakdown.php?obra=${OBQ()}&ordem=${i.ordem}&refs=${refs}&excl=${excl}`)).json(); }
  catch(e){ el.innerHTML='<span class="muted">Falha ao carregar insumos.</span>'; return; }
  if(d.error){ el.innerHTML='<span class="muted">'+esc(d.error)+'</span>'; return; }
  ORC_BD=d; orcPaintEditConf();
}
function orcBdRecompute(){
  if(!ORC_BD)return; const T={material:0,mo:0,mat_mo:0,equip:0};
  ORC_BD.linhas.forEach(l=>{ const lt={material:0,mo:0,mat_mo:0,equip:0};
    l.insumos.forEach(x=>{ if(!x.excl){ lt[x.tipo]=(lt[x.tipo]||0)+x.valor; T[x.tipo]+=x.valor; } });
    l.tot_por_tipo=lt; });
  ORC_BD.tot_por_tipo=T; ORC_BD.total=T.material+T.mo+T.mat_mo+T.equip;
}
function orcConfExpand(li){ const ins=document.getElementById('bdins-'+li), car=document.getElementById('bdcar-'+li); if(!ins)return;
  const show=ins.style.display==='none'; ins.style.display=show?'block':'none'; if(car)car.textContent=show?'expand_more':'chevron_right'; }
function orcPaintEditConf(){
  const el=document.getElementById('orcSel'), tot=document.getElementById('orcTotal'); if(!el||!ORC_BD)return;
  const tp=ORC_BD.tot_por_tipo;
  const totHtml=['material','mo','mat_mo','equip'].filter(k=>tp[k]>0.5).map(k=>`${tpBadge(k)} <b>${BRL(tp[k])}</b>`).join(' &nbsp; ')+` &nbsp;·&nbsp; <b>Total ${BRL(ORC_BD.total)}</b>`;
  const nExcl=(ORC_EXCL||[]).filter(e=>ORC_SEL.has(Number(e.l))).length;
  const linhas=ORC_BD.linhas.map((l,li)=>{
    const nx=l.insumos.filter(x=>x.excl).length;
    const lineNet=['material','mo','mat_mo','equip'].reduce((a,k)=>a+(l.tot_por_tipo[k]||0),0);
    const sub=['material','mo','mat_mo','equip'].filter(k=>l.tot_por_tipo[k]>0.5).map(k=>tpLabel(k)+' '+BRL(l.tot_por_tipo[k])).join(' · ');
    const ins=l.insumos.map((x,ii)=>`<div class="pickrow" style="gap:6px;padding:2px 0;${x.excl?'opacity:.55':''}" onclick="orcExclToggle(${li}, ${ii})" title="${x.excl?'incluir de volta na verba':'tirar da verba'}">
        <span class="material-icons" style="font-size:16px;color:${x.excl?'var(--muted)':'var(--ok)'}">${x.excl?'check_box_outline_blank':'check_box'}</span>${tpBadge(x.tipo)}
        <div style="flex:1;min-width:0"><div style="${x.excl?'text-decoration:line-through;color:var(--muted)':''}">${esc((x.desc||'').slice(0,40))}</div></div>
        <span class="muted" style="font-size:11px;white-space:nowrap">${QNUM(x.qtde)} ${esc(x.unidade||'')}</span>
        <span class="money" style="${x.excl?'text-decoration:line-through;color:var(--muted)':''}">${BRL(x.valor)}</span></div>`).join('');
    return `<div style="border-bottom:1px solid var(--line);padding:1px 0">
      <div class="pickrow" style="gap:6px">
        <span class="material-icons" onclick="orcToggleSel(${l.id})" style="font-size:17px;color:var(--ok);cursor:pointer" title="remover a linha inteira da verba">check_box</span>
        <span class="material-icons" id="bdcar-${li}" onclick="orcConfExpand(${li})" style="font-size:17px;color:var(--muted);cursor:pointer">${nx?'expand_more':'chevron_right'}</span>
        <div style="flex:1;min-width:0;cursor:pointer" onclick="orcConfExpand(${li})"><div>${esc(l.descricao)}</div><small class="muted">${esc((l.path||'').slice(0,50))}${sub?' · '+sub:''}${nx?` · <span style="color:var(--and)">−${nx} insumo</span>`:''}</small></div>
        <span class="money">${BRL(lineNet)}</span></div>
      <div id="bdins-${li}" style="display:${nx?'block':'none'};padding:0 0 6px 30px">${ins}</div></div>`;
  }).join('');
  el.innerHTML=`<div class="box" style="background:#fbfdf9;border-color:var(--ok);margin-bottom:8px">
      <div class="bv" style="font-size:12.5px">${totHtml}${nExcl?` <span class="muted" style="font-weight:400">· ${nExcl} insumo(s) fora</span>`:''}</div>
      <div class="muted" style="font-size:11px;margin-top:3px">Abra a linha (▸) e clique num insumo para <b>tirar/incluir</b> na verba (ex.: espaçador). O ✔ da esquerda remove a linha inteira.</div></div>${linhas}`;
  if(tot) tot.innerHTML='';
}
function orcExclToggle(li, ii){
  if(!ORC_BD)return; const l=ORC_BD.linhas[li]; if(!l)return; const insu=l.insumos[ii]; if(!insu)return;
  const lineId=Number(l.id), desc=insu.desc; ORC_EXCL=ORC_EXCL||[];
  const ix=ORC_EXCL.findIndex(e=>Number(e.l)===lineId && e.d===desc);
  const nowExcl=ix<0; if(ix>=0) ORC_EXCL.splice(ix,1); else ORC_EXCL.push({l:lineId, d:desc});
  insu.excl=nowExcl; orcBdRecompute(); orcPaintEditConf();
}
async function orcLoadTree(){
  const box=document.getElementById('orcTree'); if(!box)return;
  await loadVerbaUsos();   // pra travar as linhas já usadas em outro item
  const d=await (await fetch('actions/orcamento.php?obra='+OBQ())).json();
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
  const d=await (await fetch('actions/orcamento.php?obra='+OBQ()+'&children_of='+encodeURIComponent(n.codigo))).json();
  const filhos=(d.linhas||[]).map(x=>({...x,expanded:false}));
  ORC_NODES.splice(ix+1,0,...filhos); n.expanded=true; orcRenderTree();
}
function orcToggleSel(id){
  if(!ORC_SEL.has(id)){ const u=usadoPorOutro(id); if(u.length){ toast('Essa linha já está na verba de “'+u[0].nome+'” — não pode entrar em 2 itens. Veja a Auditoria.'); return; } }
  const tr=document.getElementById('orcTree'), sr=document.querySelector('#orcSearch .srbox');
  const ts=tr?tr.scrollTop:0, ss=sr?sr.scrollTop:0;
  ORC_SEL.has(id)?ORC_SEL.delete(id):ORC_SEL.add(id);
  orcRenderTree(); if(EDITO&&ORCFONTE==='analitico'){ orcLoadEditConf(CUR); } else { orcRenderSel(); } orcRenderSearch();
  const tr2=document.getElementById('orcTree'); if(tr2)tr2.scrollTop=ts;
  const sr2=document.querySelector('#orcSearch .srbox'); if(sr2)sr2.scrollTop=ss;
}
async function orcRenderSel(){
  const el=document.getElementById('orcSel'); if(!el)return;
  if(!ORC_SEL.size){el.innerHTML='<span class="muted">Nenhum item selecionado.</span>';document.getElementById('orcTotal').textContent='';return;}
  const d=await (await fetch('actions/orcamento.php?obra='+OBQ()+'&ids='+[...ORC_SEL].join(','))).json();
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
  const d=await (await fetch('actions/orcamento.php?obra='+OBQ()+'&q='+encodeURIComponent(q))).json();
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
async function orcSalvar(){ EDITO=false; const ex=(ORC_EXCL||[]).filter(e=>ORC_SEL.has(Number(e.l)));
  await saveAndReload({orcamento_refs:[...ORC_SEL], orcamento_excl:ex}); toast('Verba composta ('+ORC_SEL.size+' linhas'+(ex.length?', −'+ex.length+' insumo':'')+')'); }
async function orcLimpar(){ EDITO=false; ORC_SEL.clear(); ORC_EXCL=[]; await saveAndReload({orcamento_refs:[], orcamento_excl:[]}); toast('Composição limpa'); }
// Separar material × MO: converte verba analítica (linha inteira) em composição SÓ material, liberando a MO
async function separarMO(){
  if(!CUR) return;
  let d; try{ d=await (await fetch('actions/separar_mo.php?obra='+OBQ()+'&ordem='+CUR.ordem)).json(); }
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
let VERBA_USOS=null, VERBA_USOS_OB=null;   // cache POR OBRA — modal de outra obra recarrega
async function loadVerbaUsos(force){
  if(VERBA_USOS && VERBA_USOS_OB===OBQ() && !force) return VERBA_USOS;
  try{ VERBA_USOS=await (await fetch('actions/verba_usos.php?obra='+OBQ()+'&_='+Date.now())).json(); VERBA_USOS_OB=OBQ(); }
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
function compLocalBloqueado(L){ const c=lineClaims(L); const wx=c.wx||{};
  // só trava o LOCAL quem tomou a linha INTEIRA sem excluir nada; quem excluiu deixa os insumos livres pra outro item
  const w=((c.w)||[]).filter(o=>Number(o)!==_curOrdem() && !(wx[o]&&wx[o].length)); return w.length?{item:nomeItem(w[0])}:null; }
// separa um conjunto de linhas em livres × em conflito p/ o insumo (cid#idx): conflito = linha inteira por outro OU MESMO insumo por outro
function compInsumoSplit(cid, idx, lineIds, insDesc){
  const key=cid+'#'+idx, livres=[], conf=[], items=new Set();
  // descrição do insumo (pra casar com exclusões guardadas por descrição). Cai no COMP_DATA se não vier.
  const D=(insDesc!=null)?insDesc:((COMP_DATA&&COMP_DATA.id===cid&&COMP_DATA.insumos&&COMP_DATA.insumos[idx])?COMP_DATA.insumos[idx].descricao:null);
  (lineIds||[]).forEach(L=>{ const c=lineClaims(L); const wx=c.wx||{};
    // um item que tomou a linha inteira MAS excluiu este insumo NÃO o reivindica
    const w=((c.w)||[]).filter(o=>Number(o)!==_curOrdem() && !(D!=null && wx[o] && wx[o].indexOf(D)>=0));
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
  let d; try{ d=await (await fetch('actions/orcamento_massa.php?obra='+OBQ()+'&escopo='+escopo+'&material='+encodeURIComponent(material)+'&termos='+encodeURIComponent(termos))).json(); }
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
// preserva a rolagem das árvores (.tree criadas DENTRO do innerHTML) ao reconstruir — senão cada expandir/marcar volta pro topo
function keepTreeScroll(box, html){
  const olds=[...box.querySelectorAll('.tree')].map(t=>t.scrollTop);
  box.innerHTML=html;
  box.querySelectorAll('.tree').forEach((t,i)=>{ if(olds[i]!=null) t.scrollTop=olds[i]; });
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
  keepTreeScroll(box,`<div style="display:flex;gap:6px;align-items:center;margin-bottom:5px;font-size:11.5px">
      <span class="muted">Agrupar por:</span>
      <button class="btn-ghost" style="padding:3px 9px${MASSA_GROUP==='material'?';background:var(--azul);color:#fff':''}" onclick="massaSetGroup('material')">Material / fornecedor</button>
      <button class="btn-ghost" style="padding:3px 9px${MASSA_GROUP==='termo'?';background:var(--azul);color:#fff':''}" onclick="massaSetGroup('termo')">Tipo de peça</button></div>
    <div class="tree" style="max-height:300px">${gh}</div>
    <div class="box" style="margin-top:6px;padding:8px 12px"><div class="bv"><b>Selecionado: ${selN} linhas · ${BRL(selV)}</b> <span class="muted" style="font-size:11.5px">de ${MASSA.n_linhas} · ${BRL(MASSA.total)} encontrados</span></div></div>
    <div style="margin-top:6px"><button class="btn-prim" onclick="massaAdd()"><span class="material-icons" style="font-size:15px;vertical-align:-3px">add</span> Adicionar à verba (${selN} linhas)</button></div>`);
}
function massaSetGroup(g){ MASSA_GROUP=g; MASSA_OPEN=new Set(); massaRender(); }
function massaToggleGrupo(key){ const g=massaGrupos().find(x=>x.key===key); if(!g)return;
  const livres=g.linhas.filter(l=>!usadoPorOutro(l.id).length);
  const allOn=livres.length&&livres.every(l=>MASSA_SEL.has(l.id));
  livres.forEach(l=>{ allOn?MASSA_SEL.delete(l.id):MASSA_SEL.add(l.id); }); massaRender(); }
function massaToggleLinha(id){ if(usadoPorOutro(id).length)return; MASSA_SEL.has(id)?MASSA_SEL.delete(id):MASSA_SEL.add(id); massaRender(); }
function massaExpand(key){ MASSA_OPEN.has(key)?MASSA_OPEN.delete(key):MASSA_OPEN.add(key); massaRender(); }
async function massaAdd(){
  const add=[...MASSA_SEL].filter(id=>!usadoPorOutro(id).length);   // nunca adiciona linha travada
  if(!add.length){ toast('Marque ao menos uma linha livre'); return; }
  const tipo=document.getElementById('massaTipo')?.value||'inteira';
  if(tipo==='inteira'){   // linha inteira = analítico (material + MO)
    add.forEach(id=>ORC_SEL.add(id)); orcRenderSel();
    MASSA=null; MASSA_SEL=new Set(); const b=document.getElementById('massaRes'); if(b) b.innerHTML='';
    toast(add.length+' linhas (material + MO) adicionadas à verba. Clique em Salvar verba.'); return;
  }
  // só material / só mão de obra → converte as linhas nos insumos da composição
  if(ORC_SEL.size){ if(!confirm('Você já tem linhas inteiras selecionadas. Pegar “só '+(tipo==='mo'?'mão de obra':'material')+'” passa a verba pra composição e descarta as linhas inteiras. Continuar?')) return; ORC_SEL.clear(); }
  let d; try{ d=await (await fetch('actions/linhas_composicao.php?tipo='+tipo+'&ids='+add.join(','))).json(); }
  catch(e){ toast('Falha ao converter'); return; }
  if(d.error){ toast(d.error); return; }
  await loadVerbaUsos();
  let added=0, skip=0, restr=0;
  (d.composicao_sel||[]).forEach(s=>{
    if(COMP_SEL.some(x=>x.cid===s.cid&&x.idx===s.idx)){ skip++; return; }
    const ids=(s.locais||[]).map(l=>l.id); const sp=compInsumoSplit(s.cid,s.idx,ids,s.desc);
    if(ids.length>0 && sp.livres.length===0) return;                                 // 100% em conflito → pula
    const freeSet=new Set(sp.livres);
    const area=ids.length ? (s.locais||[]).filter(l=>freeSet.has(l.id)).reduce((a,l)=>a+l.q,0) : s.area;
    if(sp.conf.length) restr++;
    COMP_SEL.push({cid:s.cid, idx:s.idx, area, q:0, locais:ids.length?sp.livres:null,
      desc:s.desc, tipo:s.tipo, unidade:s.unidade, coef:+s.coef, rs_unit:+s.rs_unit, compdesc:s.compdesc});
    added++;
  });
  const semC=(d.resumo&&d.resumo.sem_composicao)||[];
  MASSA=null; MASSA_SEL=new Set();
  ORCFONTE='composicao'; drawModal();   // troca pra fonte composição e mostra a cesta resumida
  toast(added+' insumos de '+(tipo==='mo'?'mão de obra':'material')+' na verba'+(restr?' · '+restr+' restringidos (locais já usados)':'')+(skip?' · '+skip+' já na cesta':'')+(semC.length?' · ⚠️ '+semC.length+' sem composição ignoradas':'')+'. Salve a verba por composição.');
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
let COMPB_SEQ=0;
async function compBuscar(){
  const q=document.getElementById('compQ').value.trim();
  const box=document.getElementById('compSearch'); if(!box)return;
  if(q.length<2){box.innerHTML='';return;}
  const my=++COMPB_SEQ;
  box.innerHTML='<div class="muted" style="font-size:12px;padding:4px">Buscando…</div>';
  const d=await (await fetch('actions/composicao.php?obra='+OBQ()+'&q='+encodeURIComponent(q))).json();
  if(my!==COMPB_SEQ) return;   // resposta atrasada de uma tecla anterior — descarta
  COMP_LAST=d.composicoes||[];
  if(!COMP_LAST.length){box.innerHTML='<div class="muted" style="font-size:12px;padding:4px">Nada encontrado. É <b>mão de obra ou insumo</b> (ex.: eletricista, encanador)? Eles ficam <b>dentro</b> das composições — use a <b>Busca em massa por insumo</b> logo abaixo.</div>';return;}
  box.innerHTML='<div class="srbox">'+COMP_LAST.map(c=>`<div class="pickrow" onclick="compEscolher(${c.id})">
    <span class="material-icons" style="font-size:16px;color:var(--verde)">playlist_add</span>
    <div><div>${esc(c.descricao)}</div><small class="muted">${QNUM(c.qtde_total)} ${esc(c.unidade||'')} · ${BRL(c.rs_unit)}/un</small></div></div>`).join('')+'</div>';
}
let COMP_SEQ=0;
async function compEscolher(id){
  const my=++COMP_SEQ;
  await loadVerbaUsos();   // pra travar locais/insumos já usados em outro item
  const cd=await (await fetch('actions/composicao.php?id='+id)).json();
  const cl=await (await fetch('actions/composicao_locais.php?id='+id)).json();
  if(my!==COMP_SEQ) return;   // outra composição foi clicada enquanto esta carregava — descarta (senão abre a ERRADA)
  COMP_DATA=cd; COMP_LOCAIS=cl;
  const allIds=(COMP_LOCAIS.grupos||[]).flatMap(g=>g.linhas.map(l=>l.id));
  // se já há insumo desta composição na cesta com locais salvos, reusa a seleção; senão, todos os locais
  const ex=COMP_SEL.find(s=>s.cid===id && Array.isArray(s.locais) && s.locais.length);
  COMP_LOCAIS_SEL=new Set((ex?ex.locais:allIds).filter(L=>!compLocalBloqueado(L)));  // não marca locais tomados inteiros por outro item
  compRecalcArea();
  const cs=document.getElementById('compSearch'); if(cs) cs.innerHTML='';
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
    if(hasLocais){ const sp=compInsumoSplit(s.cid,s.idx,cand,s.desc); s.locais=sp.livres; s.area=somaQtdeDeLinhas(sp.livres); }
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
  keepTreeScroll(box,`
    <div class="box"><div class="bl">${esc(c.descricao)}</div>
      <div class="bv muted" style="font-size:12px">unidade ${un} · total no orçamento ${QNUM(c.qtde_total)} ${un}</div></div>
    ${locaisHtml}
    <div class="fld" style="margin:6px 0 2px"><label>Insumos — marque o que entra na verba (a área vem dos locais acima)</label></div>
    ${(()=>{ const cand=compCandidato(); let nLock=0,nFree=0;
      c.insumos.forEach((in_,ix)=>{ const on=COMP_SEL.some(s=>s.cid===c.id&&s.idx===ix); const sp=compInsumoSplit(c.id,ix,cand,in_.descricao); if(cand.length>0&&sp.livres.length===0&&!on)nLock++; else nFree++; });
      return nLock?`<div class="note" style="margin:0 0 5px;font-size:11.5px">🔒 <b>${nLock}</b> insumo(s) desta composição já está(ão) em outro item (por isso trava<b>m só eles</b>). <b>Os outros ${nFree} — inclusive a mão de obra — você marca normalmente aqui.</b></div>`:''; })()}
    <div class="tree" style="max-height:170px">
      ${(()=>{ const cand=compCandidato(); return c.insumos.map((in_,ix)=>{ const on=COMP_SEL.some(s=>s.cid===c.id&&s.idx===ix);
        const sp=compInsumoSplit(c.id,ix,cand,in_.descricao); const fully=cand.length>0&&sp.livres.length===0; const partial=!fully&&sp.conf.length>0;
        if(fully&&!on) return `<div class="tnode" style="opacity:.55" title="esse insumo já está em outro item em todos os locais selecionados">
          <span class="material-icons" style="color:var(--pend)">lock</span>
          ${tpBadge(in_.tipo)}
          <span class="tname">${esc(in_.descricao)} <span style="color:var(--pend);font-size:11px">· já em “${esc(sp.items[0]||'')}” (todos os locais)</span></span></div>`;
        return `<div class="tnode">
        <span class="material-icons chk" onclick="compToggleInsumo(${ix})" style="color:${on?'var(--ok)':'var(--muted)'}">${on?'check_box':'check_box_outline_blank'}</span>
        ${tpBadge(in_.tipo)}
        <span class="tname">${esc(in_.descricao)}${partial?` <span style="color:var(--and);font-size:11px">· ⚠️ ${sp.conf.length} local(is) já em “${esc(sp.items[0]||'')}” (não conta)</span>`:''}</span>
        <span class="tval">${QNUM(in_.coef)} ${esc(in_.unidade||'')} × ${BRL(in_.rs_unit)}</span>
      </div>`;}).join(''); })()}
    </div>
    <div class="muted" style="font-size:11.5px;margin-top:4px">Ex.: marque só a MO do reboco. Insumo/local já usado em outro item aparece 🔒/⚠️ e não conta de novo.</div>`);
}
function compToggleInsumo(ix){
  const c=COMP_DATA; const in_=c&&c.insumos[ix]; if(!in_)return;
  const i=COMP_SEL.findIndex(s=>s.cid===c.id&&s.idx===ix);
  if(i>=0){ COMP_SEL.splice(i,1); compRenderDetail(); compRenderBasket(); return; }
  const hasLocais=!!(COMP_LOCAIS&&COMP_LOCAIS.grupos&&COMP_LOCAIS.grupos.length);
  const cand=compCandidato(); const sp=compInsumoSplit(c.id, ix, cand, in_.descricao);
  if(cand.length>0 && sp.livres.length===0){ toast('“'+in_.descricao+'” já está em “'+(sp.items[0]||'outro item')+'” em todos os locais — não dá pra contar de novo.'); return; }
  const area=hasLocais?somaQtdeDeLinhas(sp.livres):(COMP_AREA||c.qtde_total||0);
  COMP_SEL.push({cid:c.id, idx:ix, area, q:!COMP_SEL.some(s=>s.q),   // 1 driver de quantitativo por ITEM (não por composição) — evita somar em dobro (ex.: elevador + montagem)
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
  let qval=0,qun='';
  COMP_SEL.forEach(s=>{ if(s.q){ qval+=(s.area||0)*(s.coef||0); if(!qun)qun=s.unidade; } });
  if(COMP_SEL.length>25){
    // RESUMO: muitos insumos (ex.: 364 encanadores) — agrupa por tipo/descrição pra não virar lista gigante
    const by={}; COMP_SEL.forEach(s=>{ const key=s.desc+'|'+s.tipo; if(!by[key])by[key]={desc:s.desc,tipo:s.tipo,n:0,custo:0}; by[key].n++; by[key].custo+=(s.area||0)*(s.coef||0)*(s.rs_unit||0); });
    COMP_BASKET_GROUPS=Object.values(by).sort((a,b)=>b.custo-a.custo);
    box.innerHTML=`<div class="bl" style="margin-bottom:4px">Verba composta — ${COMP_SEL.length} insumos (resumo)</div>`+
      COMP_BASKET_GROUPS.map((g,i)=>`<div class="pickrow" style="gap:8px;align-items:center">
        ${tpBadge(g.tipo)}
        <div style="flex:1;min-width:0">${esc(g.desc)} <span class="muted">· de ${g.n} composições</span></div>
        <span class="money" style="min-width:96px;text-align:right">${BRL(g.custo)}</span>
        <span class="material-icons" style="cursor:pointer;color:var(--pend);font-size:18px" onclick="compRemoverGrupo(${i})" title="remover todos deste tipo">close</span>
      </div>`).join('')+
      `<div class="muted" style="font-size:11px;margin-top:4px">Resumido (muitos insumos). Pra ajustar local/área de um específico, abra a composição dele na busca acima.</div>`;
  } else {
    box.innerHTML='<div class="bl" style="margin-bottom:4px">Verba composta destes insumos</div>'+COMP_SEL.map((s,k)=>{
      const custo=(s.area||0)*(s.coef||0)*(s.rs_unit||0);
      return `<div class="pickrow" style="gap:8px;align-items:center">
        ${tpBadge(s.tipo)}
        <div style="flex:1;min-width:0"><div style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap">${esc(s.desc)}</div>
          <small class="muted">${esc((s.compdesc||'').slice(0,38))} · ${QNUM(s.coef)}× ${BRL(s.rs_unit)}</small></div>
        <input type="number" step="any" style="width:84px;border:1px solid var(--line);border-radius:7px;padding:4px 6px" value="${s.area}" oninput="COMP_SEL[${k}].area=parseFloat(this.value)||0;compRenderBasket()" title="área/quantidade">
        <span class="money" style="min-width:88px;text-align:right">${BRL(custo)}</span>
        <label class="ckl" style="font-size:11px" title="usar pro quantitativo"><input type="checkbox" ${s.q?'checked':''} onchange="COMP_SEL[${k}].q=this.checked;compRenderBasket()"> qtd</label>
        <span class="material-icons" style="cursor:pointer;color:var(--pend);font-size:18px" onclick="COMP_SEL.splice(${k},1);compRenderDetail();compRenderBasket()" title="remover">close</span>
      </div>`;
    }).join('');
  }
  if(tot) tot.innerHTML=`<div class="box" style="margin-top:8px"><div class="bv">
      ${tpSubHtml(COMP_SEL)}<br>
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
let INSMASSA=null, INSMASSA_LEAVES=[], INSMASSA_SEL=new Set(), INSMASSA_OPEN=new Set();
function insMassaToggle(){ const p=document.getElementById('insMassaPanel'), b=document.getElementById('insMassaBtn'); if(!p)return;
  const open=p.style.display==='none'; p.style.display=open?'block':'none';
  const ic=b&&b.querySelector('.mtcaret'); if(ic) ic.textContent=open?'expand_less':'expand_more';
}
function insMassaPreset(k){ const t=document.getElementById('insMassaTermos'); if(t) t.value=k; const s=document.getElementById('insMassaSis'); if(s) s.value=''; const tp=document.getElementById('insMassaTipo'); if(tp) tp.value=''; insMassaBuscar(); }
// atalho por SISTEMA (ex.: Gás materiais / Gás MO) — seta os filtros e busca sem termo
function insMassaPresetSis(sis, tipo){ const s=document.getElementById('insMassaSis'); if(s) s.value=sis; const tp=document.getElementById('insMassaTipo'); if(tp) tp.value=tipo||''; const t=document.getElementById('insMassaTermos'); if(t) t.value=''; insMassaBuscar(); }
// uma FOLHA = um insumo (cid#idx) numa linha do orçamento (local). Trava por linha: linha inteira (w) OU mesmo insumo (i) em outro item.
function insMassaBuildLeaves(){
  const lv=[], cur=_curOrdem();
  (INSMASSA&&INSMASSA.matches||[]).forEach(m=>{
    (m.locais||[]).forEach(l=>{
      const c=lineClaims(l.id);
      const w=((c.w)||[]).filter(o=>Number(o)!==cur && !(c.wx && c.wx[o] && c.wx[o].indexOf(m.ins)>=0));  // 'inteiro' que excluiu este insumo não trava
      const ins=(((c.i)&&c.i[m.cid+'#'+m.idx])||[]).filter(o=>Number(o)!==cur);
      const blk=[...new Set([...w,...ins])];
      lv.push({key:m.cid+'#'+m.idx+'|'+l.id, cid:m.cid, idx:m.idx, lineId:l.id, q:+l.q||0,
        local:l.local||'(sem local)', sub:l.sub||'—', sis:l.sis||m.sistema||'', ins:m.ins, comp:m.comp, tipo:m.tipo, unidade:m.unidade||'',
        coef:+m.coef, rs:+m.rs_unit, valor:(+l.q||0)*(+m.coef)*(+m.rs_unit),
        locked:blk.length>0, blocker:blk.length?nomeItem(blk[0]):null, blockerOrdem:blk.length?blk[0]:null});
    });
  });
  return lv;
}
function insMassaTree(){
  const byL={}; INSMASSA_LEAVES.forEach(x=>{ (byL[x.local]=byL[x.local]||[]).push(x); });
  const tot=a=>a.reduce((s,x)=>s+x.valor,0);
  return Object.keys(byL).sort((a,b)=>tot(byL[b])-tot(byL[a])).map(local=>{
    const lvs=byL[local], byS={}; lvs.forEach(x=>{ (byS[x.sub]=byS[x.sub]||[]).push(x); });
    return {local, leaves:lvs, subs:Object.keys(byS).sort((a,b)=>tot(byS[b])-tot(byS[a])).map(sub=>({sub, leaves:byS[sub]}))};
  });
}
async function insMassaBuscar(){
  const termos=(document.getElementById('insMassaTermos')?.value||'').trim();
  const sis=(document.getElementById('insMassaSis')?.value||'');
  const tipo=(document.getElementById('insMassaTipo')?.value||'');
  const box=document.getElementById('insMassaRes'); if(!box)return;
  if(!termos && !sis){ box.innerHTML='<div class="muted" style="font-size:12px">Escolha um <b>sistema</b> (ex.: 🔥 Gás) ou digite um <b>termo</b> (ex.: encanador).</div>'; return; }
  box.innerHTML='<div class="muted" style="font-size:12px;padding:4px">Buscando…</div>';
  await loadVerbaUsos();
  const qs='obra='+OBQ()+'&termos='+encodeURIComponent(termos)+'&sistema='+encodeURIComponent(sis)+'&tipo='+encodeURIComponent(tipo);
  let d; try{ d=await (await fetch('actions/composicao_insumo_massa.php?'+qs)).json(); }
  catch(e){ box.innerHTML='<div class="muted" style="font-size:12px;color:var(--pend)">Falha: '+esc(e.message)+'</div>'; return; }
  if(d.error){ box.innerHTML='<div class="muted" style="font-size:12px;color:var(--pend)">Erro: '+esc(d.error)+'</div>'; return; }
  INSMASSA=d; INSMASSA_OPEN=new Set(); INSMASSA_SEL=new Set();
  INSMASSA_LEAVES=insMassaBuildLeaves();
  INSMASSA_LEAVES.forEach(x=>{ if(!x.locked) INSMASSA_SEL.add(x.key); });   // marca tudo que está LIVRE
  insMassaRender();
}
function insMassaNodeLeaves(path){ const t=insMassaTree(); const p=path.split('.').map(Number); const L=t[p[0]]; if(!L)return[];
  if(p.length===1) return L.leaves; const S=L.subs[p[1]]; return S?S.leaves:[]; }
function insMassaToggleNode(path){ const lvs=insMassaNodeLeaves(path).filter(x=>!x.locked);
  const allOn=lvs.length&&lvs.every(x=>INSMASSA_SEL.has(x.key)); lvs.forEach(x=>{ allOn?INSMASSA_SEL.delete(x.key):INSMASSA_SEL.add(x.key); }); insMassaRender(); }
function insMassaToggleLeaf(key){ INSMASSA_SEL.has(key)?INSMASSA_SEL.delete(key):INSMASSA_SEL.add(key); insMassaRender(); }
function insMassaExpandNode(path){ INSMASSA_OPEN.has(path)?INSMASSA_OPEN.delete(path):INSMASSA_OPEN.add(path); insMassaRender(); }
function insMassaAbrir(ordem){ if(ordem!=null) openModal(Number(ordem), OBQ()); }   // o item bloqueador é da MESMA obra
function insMassaChk(leaves,path){ const free=leaves.filter(x=>!x.locked); const a=free.length&&free.every(x=>INSMASSA_SEL.has(x.key)), s=free.some(x=>INSMASSA_SEL.has(x.key));
  return `<span class="material-icons chk" onclick="insMassaToggleNode('${path}')" style="color:${a?'var(--ok)':s?'var(--and)':'var(--muted)'}">${a?'check_box':s?'indeterminate_check_box':'check_box_outline_blank'}</span>`; }
function insMassaRender(){
  const box=document.getElementById('insMassaRes'); if(!box)return;
  if(!INSMASSA_LEAVES.length){ box.innerHTML='<div class="muted" style="font-size:12px">Nada encontrado.</div>'; return; }
  const tree=insMassaTree();
  let selN=0, selV=0, lockN=0; const blk={}; const bySis={};
  INSMASSA_LEAVES.forEach(x=>{ if(x.locked){ lockN++; if(x.blocker) blk[x.blocker]=(blk[x.blocker]||0)+1; } else { if(INSMASSA_SEL.has(x.key)){ selN++; selV+=x.valor; } const k=x.sis||'—'; (bySis[k]=bySis[k]||{n:0,v:0}); bySis[k].n++; bySis[k].v+=x.valor; } });
  const sisKeys=Object.keys(bySis).sort((a,b)=>bySis[b].v-bySis[a].v);
  const sisHtml=sisKeys.length>1?`<div class="muted" style="font-size:11px;margin-bottom:5px;line-height:1.6">⚠️ resultado tem <b>${sisKeys.length} sistemas</b> misturados — use o filtro acima pra separar: ${sisKeys.map(k=>`<b style="color:var(--verde-d)">${esc(k)}</b> ${BRL(bySis[k].v)}`).join(' · ')}</div>`:'';
  const sum=a=>a.reduce((s,x)=>s+x.valor,0), lk=a=>a.filter(x=>x.locked).length;
  const html=tree.map((L,li)=>{
    const open=INSMASSA_OPEN.has(''+li), nl=lk(L.leaves);
    let h=`<div class="tnode tparent">${insMassaChk(L.leaves,''+li)}
      <span class="caret material-icons" onclick="insMassaExpandNode('${li}')">${open?'expand_more':'chevron_right'}</span>
      <span class="tname"><b>${esc(L.local)}</b> <span class="muted">(${L.leaves.length}${nl?' · <span style="color:var(--pend)">'+nl+' 🔒</span>':''})</span></span>
      <span class="tval">${BRL(sum(L.leaves))}</span></div>`;
    if(open) h+=L.subs.map((S,si)=>{
      const sp=li+'.'+si, sopen=INSMASSA_OPEN.has(sp), sl=lk(S.leaves);
      let sh=`<div class="tnode" style="padding-left:22px">${insMassaChk(S.leaves,sp)}
        <span class="caret material-icons" onclick="insMassaExpandNode('${sp}')">${sopen?'expand_more':'chevron_right'}</span>
        <span class="tname">${esc(S.sub)} <span class="muted">(${S.leaves.length}${sl?' · <span style="color:var(--pend)">'+sl+' 🔒</span>':''})</span></span>
        <span class="tval">${BRL(sum(S.leaves))}</span></div>`;
      if(sopen) sh+=S.leaves.map(x=>{
        if(x.locked) return `<div class="tnode" style="padding-left:46px;opacity:.62">
          <span class="material-icons" style="color:var(--pend);font-size:15px">lock</span>
          <span class="tname" style="font-size:11px">${esc(x.ins)} <span class="muted">· ${esc((x.comp||'').slice(0,22))}</span> <span style="color:var(--pend)">· já em “${esc(x.blocker||'?')}”</span></span>
          <span class="tval">${BRL(x.valor)} <span class="material-icons" title="abrir o item que está usando" style="font-size:15px;cursor:pointer;vertical-align:-3px;color:var(--azul)" onclick="insMassaAbrir(${x.blockerOrdem})">open_in_new</span></span></div>`;
        const on=INSMASSA_SEL.has(x.key);
        return `<div class="tnode" style="padding-left:46px"><span class="material-icons chk" onclick="insMassaToggleLeaf('${x.key}')" style="color:${on?'var(--ok)':'var(--muted)'};font-size:15px">${on?'check_box':'check_box_outline_blank'}</span>
          <span class="tname" style="font-size:11px">${tpBadge(x.tipo)} ${esc(x.ins)} <span class="muted">· ${esc((x.comp||'').slice(0,22))}</span></span><span class="tval">${BRL(x.valor)}</span></div>`;
      }).join('');
      return sh;
    }).join('');
    return h;
  }).join('');
  const blkArr=Object.entries(blk).sort((a,b)=>b[1]-a[1]);
  const blkHtml=lockN?`<div class="note" style="margin:6px 0;font-size:11.5px">🔒 ${lockN} já em outro item — ${blkArr.slice(0,4).map(e=>esc(e[0])+' ('+e[1]+')').join(' · ')}${blkArr.length>4?' …':''}. Pra liberar: abra o item (ícone ↗) e use “Separar material × MO” (se for item de material) ou tire de lá.</div>`:'';
  keepTreeScroll(box,`${sisHtml}<div class="muted" style="font-size:11px;margin-bottom:4px">Navegue por local → subsistema e marque o que entra (ex.: só as Torres › Instalações). 🔒 = já em outro item.</div>
    <div class="tree" style="max-height:320px">${html}</div>${blkHtml}
    <div class="box" style="margin-top:6px;padding:8px 12px"><div class="bv"><b>Selecionado: ${selN} · ${BRL(selV)}</b> <span class="muted" style="font-size:11.5px">de ${INSMASSA_LEAVES.length} linhas · ${lockN} travadas</span></div></div>
    <div style="margin-top:6px"><button class="btn-prim" onclick="insMassaAdd()"><span class="material-icons" style="font-size:15px;vertical-align:-3px">add</span> Adicionar à verba (${selN})</button></div>`);
}
function insMassaAdd(){
  const sel=INSMASSA_LEAVES.filter(x=>!x.locked && INSMASSA_SEL.has(x.key));
  if(!sel.length){ toast('Marque ao menos um insumo livre'); return; }
  const byK={};
  sel.forEach(x=>{ const k=x.cid+'#'+x.idx; if(!byK[k]) byK[k]={cid:x.cid,idx:x.idx,ins:x.ins,comp:x.comp,tipo:x.tipo,unidade:x.unidade,coef:x.coef,rs:x.rs,lines:[],area:0}; byK[k].lines.push(x.lineId); byK[k].area+=x.q; });
  let added=0, skip=0;
  Object.values(byK).forEach(g=>{
    if(COMP_SEL.some(s=>s.cid===g.cid&&s.idx===g.idx)){ skip++; return; }   // já está na cesta
    COMP_SEL.push({cid:g.cid, idx:g.idx, area:g.area, q:0, locais:g.lines,
      desc:g.ins, tipo:g.tipo, unidade:g.unidade, coef:+g.coef, rs_unit:+g.rs, compdesc:g.comp});
    added++;
  });
  INSMASSA=null; INSMASSA_LEAVES=[]; const box=document.getElementById('insMassaRes'); if(box) box.innerHTML='';
  compRenderBasket();
  toast(added+' insumos na verba'+(skip?(' · '+skip+' já estavam na cesta'):'')+'. Clique em Salvar verba por composição.');
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
    ? `Material ${BRL(i.verba_material||0)} + MO ${BRL(i.verba_mo||0)}` : (verbaDefinida(i)?BRL(verbaDef(i)):'R$ 0 · a definir');
  // blocos read-only (verba/datas/quant são editados nas abas próprias)
  const ro = `
    <div class="fld"><label>Verba ${i.curado_verba?'(curada ✓)':(verbaDefinida(i)?'(definida)':'(a definir)')} ${i.verba_metodo?'· '+esc(i.verba_metodo):''}</label><input value="${esc(verbaLbl)}" disabled></div>
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
      <div style="margin-top:4px;display:flex;gap:8px;flex-wrap:wrap;align-items:center">
        ${CAN_EDIT?`<button class="btn-prim" onclick="EDITR=true;drawModal()"><span class="material-icons" style="font-size:16px;vertical-align:-3px">edit</span> Editar</button>`
                  :`<span class="muted" style="font-size:12.5px"><span class="material-icons" style="font-size:15px;vertical-align:-3px">lock</span> Você tem acesso somente leitura.</span>`}
        ${i.cotacao?`<button class="btn-ghost" onclick="cotAbrir(${i.cotacao.id})" title="${esc(i.cotacao.titulo||'')}"><span class="material-icons" style="font-size:16px;vertical-align:-3px;color:var(--verde)">request_quote</span> Ver mapa de cotação</button><span class="muted" style="font-size:11.5px">· ${i.cotacao.respostas}/${i.cotacao.convidados} responderam${i.cotacao.melhor?' · melhor '+BRL(i.cotacao.melhor):''}${i.cotacao.n>1?' · '+i.cotacao.n+' cotações':''}</span>`:''}
        ${CAN_EDIT?`<button class="btn-ghost" onclick="cotIniciar(${i.ordem},${i.obra_id||1})" title="Abre uma cotação já com os itens do dicionário deste serviço (editáveis)"><span class="material-icons" style="font-size:16px;vertical-align:-3px;color:var(--dourado)">request_quote</span> ${i.cotacao?'Nova cotação':'Iniciar cotação'}</button>`:''}
      </div>
      ${IS_ADMIN?`<div style="margin-top:14px;padding-top:12px;border-top:1px solid var(--line);display:flex;gap:8px">
        <button class="btn-ghost" onclick="desdobrarItem()"><span class="material-icons" style="font-size:15px">call_split</span> Desdobrar em Material + MO</button>
        <button class="btn-ghost" onclick="excluirItem()" style="color:var(--pend)"><span class="material-icons" style="font-size:15px">delete</span> Remover desta obra</button>
        <button class="btn-ghost" onclick="excluirItemCatalogo()" style="color:var(--pend);opacity:.65" title="Remove de TODAS as obras — irreversível"><span class="material-icons" style="font-size:15px">delete_forever</span> Excluir do catálogo</button>
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
      ${a?`<div class="fld"><label>Responsável <span class="muted" style="font-weight:400;font-size:11px">(recomendado)</span></label><select id="rResp">${respOptions(i.responsavel)}</select></div>`
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
    campos.nome=val('rNome'); campos.tipo=val('rTipo');
    const resp=val('rResp');
    if(resp) campos.responsavel=resp;   // responsável NÃO é obrigatório (pode atribuir depois); só grava se escolheu, não zera o atual
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
  try{ d=await (await fetch('actions/historico.php?obra='+OBQ()+'&ordem='+ordem)).json(); }
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
      body:JSON.stringify({ordem:CUR.ordem,campos,me:EU&&EU.bitrix_id,obra:CUR.obra_id||1})})).json();
    if(d.error){toast('Erro: '+d.error);return;}
    VERBA_USOS=null;            // verba mudou → recarrega o mapa de uso na próxima leitura
    await load();
    CUR=byOrdem(CUR.ordem, CUR.obra_id); drawModal();
  }catch(e){toast('Falha ao salvar');}
}
/* ----- Criar / desdobrar / excluir itens ----- */
// ---- cadastro ÚNICO de obras (obra_ficha) p/ os seletores de qualquer módulo ----
let OBRAS_UNI=[], OBRAS_UNI_LOADED=false;
async function obrasUniEnsure(){ if(OBRAS_UNI_LOADED) return OBRAS_UNI; try{ const r=await (await fetch('actions/obras.php?picker=1&me='+encodeURIComponent((EU&&EU.bitrix_id)||''))).json(); OBRAS_UNI=r.obras||[]; }catch(e){} OBRAS_UNI_LOADED=true; return OBRAS_UNI; }
function obrasUniOpts(sel,ph){ return (ph!==false?`<option value="">${esc(ph||'— escolher obra —')}</option>`:'')+OBRAS_UNI.map(o=>`<option value="${o.ficha_id}" ${String(sel)===String(o.ficha_id)?'selected':''}>${esc(o.nome)}${o.cidade?' · '+esc(o.cidade):''}</option>`).join(''); }
function obrasUniFichaDoRadar(radarId){ const o=OBRAS_UNI.find(x=>String(x.radar_obra_id)===String(radarId)); return o?o.ficha_id:''; }
function niEscopoToggle(){ const e=document.getElementById('niEscopo'), w=document.getElementById('niObraWrap'); if(e&&w) w.style.display=(e.value==='obra')?'':'none'; }
async function novoItem(){
  await obrasUniEnsure();
  const grupos=[...new Set(DATA.itens.map(i=>i.grupo).filter(Boolean))];
  const TIPOS=['Material','Mão de obra','Empreitada','Material + MO','Locação'];
  const defFicha=obrasUniFichaDoRadar(OBRA_SEL[0]||1);
  document.getElementById('modal').innerHTML=`
    <div class="mhead"><button class="mclose" onclick="closeModal()">×</button>
      <div class="crumb">Radar de Aquisições</div><div class="mt">Novo item</div></div>
    <div class="tabbody">
      <div class="fld"><label>Nome do serviço</label><input id="niNome" placeholder="ex.: Contrapiso"></div>
      <div class="grid2">
        <div class="fld"><label>Adicionar em</label><select id="niEscopo" onchange="niEscopoToggle()"><option value="todas">Todas as obras (catálogo padrão)</option><option value="obra">Só uma obra</option></select></div>
        <div class="fld" id="niObraWrap" style="display:none"><label>Obra</label><select id="niObra">${obrasUniOpts(defFicha)}</select></div>
      </div>
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
  const escopo=val('niEscopo')||'todas';
  const body={acao:'novo', nome:val('niNome'), grupo, tipo:val('niTipo'), curva:val('niCurva'), responsavel:resp, copy_from:val('niCopy')||null, me:EU&&EU.bitrix_id, obra:OBRA_SEL[0]||1, escopo};
  if(!body.nome){toast('Informe o nome');return;}
  if(escopo==='obra'){ const fid=val('niObra'); if(!fid){toast('Escolha a obra');return;} body.obra_ficha_id=Number(fid); }
  // responsável NÃO é obrigatório na criação — pode ser atribuído depois (inclusive em massa por grupo/categoria)
  const d=await (await fetch('actions/item_create.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(body)})).json();
  if(d.error){toast('Erro: '+d.error);return;}
  closeModal(true); await load(); toast('Item criado');
}
async function desdobrarItem(){
  if(!CUR)return;
  const d=await (await fetch('actions/item_create.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({acao:'desdobrar',ordem:CUR.ordem,me:EU&&EU.bitrix_id,obra:CUR.obra_id||1})})).json();
  if(d.error){toast('Erro: '+d.error);return;}
  closeModal(true); await load(); toast('Desdobrado em (MAT) e (MO)');
}
async function excluirItem(){
  if(!CUR)return;
  const ob=CUR.obra_nome||('obra '+(CUR.obra_id||1));
  if(!confirm('Remover "'+CUR.nome+'" da obra '+ob+'?\n\nO item CONTINUA nas outras obras e no catálogo — some só desta obra.'))return;
  const d=await (await fetch('actions/item_create.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({acao:'excluir',ordem:CUR.ordem,me:EU&&EU.bitrix_id,obra:CUR.obra_id||1})})).json();
  if(d.error){toast('Erro: '+d.error);return;}
  closeModal(true); if(typeof MAT!=='undefined')MAT=null; await load();
  toast('Removido da obra '+ob+(d.restam_obras!=null?(' · ainda em '+d.restam_obras+' obra(s)'):''));
}
async function excluirItemCatalogo(){
  if(!CUR)return;
  if(!confirm('⚠️ EXCLUIR "'+CUR.nome+'" do CATÁLOGO INTEIRO?\n\nRemove o item e toda a curadoria dele de TODAS as obras (Trinity, Imperiale, ADARA, ...). Use só para item que é lixo de verdade. NÃO pode ser desfeito.'))return;
  if(!confirm('Confirma remover "'+CUR.nome+'" de TODAS as obras? Última chance.'))return;
  const d=await (await fetch('actions/item_create.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({acao:'excluir',catalogo:true,ordem:CUR.ordem,me:EU&&EU.bitrix_id,obra:CUR.obra_id||1})})).json();
  if(d.error){toast('Erro: '+d.error);return;}
  closeModal(true); if(typeof MAT!=='undefined')MAT=null; await load(); toast('Excluído do catálogo (todas as obras)');
}
/* ===================== DASHBOARDS ===================== */
let DASH={tab:null, D:null, oppByObra:{}};
const DASH_TABS=[['comprador','Comprador','person'],['gerente','Gerente de Compras','groups'],['diretor','Diretor','insights'],['oportunidades','Oportunidades','savings']];
function dashAllowed(){ const papel=(EU&&EU.papel)||''; if(IS_ADMIN||papel==='diretor') return DASH_TABS.map(t=>t[0]);
  if(papel==='comprador') return ['comprador','oportunidades']; return ['comprador','oportunidades']; }
function dashInit(){
  const allowed=dashAllowed(), tb=document.getElementById('dtabs');
  if(tb) tb.innerHTML=DASH_TABS.filter(t=>allowed.includes(t[0])).map(t=>`<button class="dtab" id="dtab-${t[0]}" onclick="dashTab('${t[0]}')"><span class="material-icons">${t[2]}</span> ${t[1]}</button>`).join('');
  if(!DASH.tab||!allowed.includes(DASH.tab)) DASH.tab=allowed[0]||'comprador';
  dashActive(); dashLoad();
}
function dashActive(){ DASH_TABS.forEach(t=>{ const b=document.getElementById('dtab-'+t[0]); if(b) b.classList.toggle('on',t[0]===DASH.tab); }); }
function dashTab(t){ DASH.tab=t; dashActive(); renderDash(); }
async function dashLoad(){
  const w=document.getElementById('dwrap');
  w.innerHTML='<div class="dempty">Carregando dados das obras…</div>';
  // FONTE PRÓPRIA (não depende do MAT global, que o load() do Radar zera de forma assíncrona)
  if(!DASH.items || !DASH.items.length){
    try{
      let obras=(OBRAS&&OBRAS.length)?OBRAS.map(o=>Number(o.id)):null;
      if(!obras){ const d0=await (await fetch('actions/matriz.php')).json(); OBRAS=d0.obras||[]; obras=OBRAS.map(o=>Number(o.id)); }
      const rs=await Promise.all(obras.map(async oid=>{ const u='actions/matriz.php'+(oid!==1?('?obra='+oid+'&'):'?')+'_='+Date.now(); const d=await (await fetch(u)).json().catch(()=>null); return {oid,d}; }));
      const items=[];
      for(const {oid,d} of rs){ if(!d||d.error||!d.itens) continue; if(d.obras) OBRAS=d.obras;
        d.itens.forEach(i=>{ i.obra_id=oid; i.obra_nome=(d.obra&&d.obra.nome)||('obra '+oid); items.push(i); }); }
      DASH.items=items;
    }catch(e){ DASH.items=DASH.items||[]; }
  }
  const obras=[...new Set(DASH.items.map(i=>i.obra_id))];
  const need=obras.filter(id=>!DASH.oppByObra[id]);
  if(need.length){ w.innerHTML='<div class="dempty">Analisando cobertura e oportunidades…</div>';
    await Promise.all(need.map(async id=>{ try{ DASH.oppByObra[id]=await (await fetch('actions/oportunidades.php?obra='+id+'&_='+Date.now())).json(); }catch(e){ DASH.oppByObra[id]={gaps:[],resumo:{}}; } })); }
  DASH.D=dashCompute(); renderDash();
}
function dashRefresh(){ DASH.items=null; DASH.oppByObra={}; dashLoad(); }
function renderDash(){
  const w=document.getElementById('dwrap'), D=DASH.D; if(!w)return;
  if(!D){ w.innerHTML='<div class="dempty">Sem dados.</div>'; return; }
  const meta=document.getElementById('dmeta'); if(meta) meta.textContent=`${D.obras.length} obra(s) · ${D.totalItens} itens · hoje ${D.hojeBR}`;
  const f={comprador:renderDashComprador,gerente:renderDashGerente,diretor:renderDashDiretor,oportunidades:renderDashOpp}[DASH.tab];
  w.innerHTML=f?f(D):'<div class="dempty">—</div>';
}
/* ---------- cálculo das métricas (client-side, a partir do MAT + oportunidades) ---------- */
function dashCompute(){
  const items=(DASH.items&&DASH.items.length)?DASH.items:(MAT||[]), hoje=today, _now=new Date(hoje+'T00:00:00');
  const val=i=>Number(i.verba||0), lvl=i=>alertLevel(i);
  const dDiff=f=>f?Math.round((new Date(f+'T00:00:00')-_now)/864e5):null;
  const obrasMap={}; (OBRAS||[]).forEach(o=>obrasMap[o.id]=o.nome);
  const D={hoje, hojeBR:hoje.split('-').reverse().join('/'),
    obras:[...new Set(items.map(i=>i.obra_id))].map(id=>({id,nome:obrasMap[id]||('Obra '+id),cor:obraCor(id)}))};
  D.totalItens=items.length; D.verbaTotal=items.reduce((a,i)=>a+val(i),0);
  D.porStatus={}; items.forEach(i=>{const s=i.status||'Não Iniciado'; D.porStatus[s]=(D.porStatus[s]||0)+1;});
  D.finalizados=items.filter(i=>i.status==='Finalizado').length;
  D.emCotacao=items.filter(i=>/cota/i.test(i.status||'')).length;
  D.propostas=items.filter(i=>/proposta|negocia/i.test(i.status||'')).length;
  D.comData=items.filter(i=>i.fim_cotacao).length; D.pctComData=D.totalItens?Math.round(100*D.comData/D.totalItens):0;
  const isAtras=i=>['critico','atrasado'].includes(lvl(i));
  D.criticos=items.filter(i=>lvl(i)==='critico').length;
  D.expostoAtraso=items.filter(isAtras).reduce((a,i)=>a+val(i),0);
  D.emergencial=items.filter(i=>lvl(i)==='critico').reduce((a,i)=>a+val(i),0);
  // gatilhos (não finalizados, com fim de cotação)
  const g={atras:{n:0,v:0},d7:{n:0,v:0},d15:{n:0,v:0},d30:{n:0,v:0}};
  items.forEach(i=>{ if(i.status==='Finalizado'||!i.fim_cotacao)return; const d=dDiff(i.fim_cotacao), v=val(i);
    if(d<0){g.atras.n++;g.atras.v+=v;} else if(d<=7){g.d7.n++;g.d7.v+=v;} else if(d<=15){g.d15.n++;g.d15.v+=v;} else {g.d30.n++;g.d30.v+=v;} });
  D.gatilhos=g;
  // por comprador
  const cm={}; items.forEach(i=>{ const r=(i.responsavel||'').trim(); if(!r)return; (cm[r]=cm[r]||{nome:r,itens:0,criticos:0,exposta:0}); cm[r].itens++;
    if(lvl(i)==='critico')cm[r].criticos++; if(isAtras(i))cm[r].exposta+=val(i); });
  D.compradores=Object.values(cm).sort((a,b)=>b.exposta-a.exposta);
  const maxExp=Math.max(1,...D.compradores.map(c=>c.exposta)); D.compradores.forEach(c=>c.risco=Math.round(100*c.exposta/maxExp));
  // por obra
  const ob={}; items.forEach(i=>{ const id=i.obra_id; (ob[id]=ob[id]||{id,nome:obrasMap[id]||('Obra '+id),cor:obraCor(id),itens:0,criticos:0,verba:0,contratado:0}); const o=ob[id];
    o.itens++; o.verba+=val(i); if(lvl(i)==='critico')o.criticos++; if(i.status==='Finalizado')o.contratado+=val(i); });
  Object.values(ob).forEach(o=>{ const opp=DASH.oppByObra[o.id]||{}; o.coberturaPct=(opp.resumo&&opp.resumo.coberto_pct)||null; o.orcado=(opp.resumo&&opp.resumo.total)||null;
    o.exposta=items.filter(i=>i.obra_id===o.id&&isAtras(i)).reduce((a,i)=>a+val(i),0); });
  const maxR=Math.max(1,...Object.values(ob).map(o=>o.exposta)); Object.values(ob).forEach(o=>o.risco=Math.round(100*o.exposta/maxR));
  D.porObra=Object.values(ob).sort((a,b)=>b.exposta-a.exposta);
  // curva ABC em risco (itens em alerta) — curva por VALOR (mesma régua do radar)
  const curva=i=>{const v=val(i);return v>=2e5?'A':(v>=1e5?'B':'C');};
  const cr={A:{n:0,v:0},B:{n:0,v:0},C:{n:0,v:0}}; items.filter(isAtras).forEach(i=>{const c=curva(i);cr[c].n++;cr[c].v+=val(i);}); D.curvaRisco=cr;
  // listas
  const nivelOrd={critico:0,atrasado:1,proximo:2}; const alertItems=items.filter(i=>['critico','atrasado','proximo'].includes(lvl(i)));
  const sortAlert=(a,b)=>(nivelOrd[lvl(a)]-nivelOrd[lvl(b)])||((a.fim_cotacao||'9999').localeCompare(b.fim_cotacao||'9999'))||(val(b)-val(a));
  D.itensCriticos=alertItems.slice().sort(sortAlert).map(i=>({obra:i.obra_nome,nome:i.nome,resp:i.responsavel||'',fim:i.fim_cotacao,verba:val(i),nivel:lvl(i)}));
  D.proximos=items.filter(i=>i.fim_cotacao&&dDiff(i.fim_cotacao)>=0&&i.status!=='Finalizado').sort((a,b)=>a.fim_cotacao.localeCompare(b.fim_cotacao))
    .map(i=>({obra:i.obra_nome,nome:i.nome,fim:i.fim_cotacao,verba:val(i),dias:dDiff(i.fim_cotacao)}));
  const acaoDe=i=>{const s=i.status||'Não Iniciado'; if(/cota/i.test(s))return'Cobrar propostas'; if(/proposta/i.test(s))return'Aprovar fornecedor'; if(/negocia/i.test(s))return'Fechar negociação'; return'Iniciar cotação';};
  D.atuacao=alertItems.slice().sort(sortAlert).map(i=>({obra:i.obra_nome,nome:i.nome,resp:i.responsavel||'—',acao:acaoDe(i),nivel:lvl(i)}));
  // OPORTUNIDADES: lote por categoria (grupo) presente em 2+ obras + gaps de curva A/B
  const grp={}; items.forEach(i=>{ const c=i.grupo||'Outros'; (grp[c]=grp[c]||{cat:c,obras:new Set(),valor:0,itens:0,ini:null,fim:null});
    const G=grp[c]; G.obras.add(i.obra_id); G.valor+=val(i); G.itens++;
    if(i.inicio_cotacao){ if(!G.ini||i.inicio_cotacao<G.ini)G.ini=i.inicio_cotacao; } if(i.fim_cotacao){ if(!G.fim||i.fim_cotacao>G.fim)G.fim=i.fim_cotacao; } });
  let cats=Object.values(grp).map(G=>({cat:G.cat,obras:G.obras.size,valor:G.valor,itens:G.itens,ini:G.ini,fim:G.fim})).sort((a,b)=>b.valor-a.valor);
  D.opp={ categorias:cats, lotes:cats.filter(c=>c.obras>=2),
    valorPotencial:cats.reduce((a,c)=>a+c.valor,0),
    obrasEnvolvidas:D.obras.length };
  // janela 60 dias: categorias cujo início de cotação cai nos próximos 60 dias
  D.opp.janela60=cats.filter(c=>c.ini&&dDiff(c.ini)!==null&&dDiff(c.ini)<=60&&dDiff(c.ini)>=-30).length;
  D.opp.valorLote=D.opp.lotes.reduce((a,c)=>a+c.valor,0);
  // gaps de curva A/B do orçamento (todas as obras) p/ a matriz de oportunidades
  const gapCat={}; Object.entries(DASH.oppByObra).forEach(([id,d])=>{ (d.gaps||[]).forEach(gp=>{ if(gp.curva==='C')return; (gp.grupos||['—']).forEach(cat=>{ (gapCat[cat]=gapCat[cat]||{cat,valor:0,n:0,obras:new Set()}); gapCat[cat].valor+=Number(gp.valor||0)/(gp.grupos||['—']).length; gapCat[cat].n++; gapCat[cat].obras.add(id); }); }); });
  D.opp.gapCategorias=Object.values(gapCat).map(g=>({cat:g.cat,valor:Math.round(g.valor),n:g.n,obras:g.obras.size})).sort((a,b)=>b.valor-a.valor).slice(0,10);
  D.opp.gapTotal=Object.values(DASH.oppByObra).reduce((a,d)=>a+((d.resumo&&d.resumo.gap)||0),0);
  return D;
}
/* ---------- helpers de gráfico ---------- */
function dashDonut(segs,size){ size=size||120; const tot=segs.reduce((a,s)=>a+s.v,0)||1, r=size/2-6, c=size/2, cir=2*Math.PI*r; let off=0;
  const arcs=segs.filter(s=>s.v>0).map(s=>{ const len=cir*s.v/tot, el=`<circle cx="${c}" cy="${c}" r="${r}" fill="none" stroke="${s.color}" stroke-width="12" stroke-dasharray="${len} ${cir-len}" stroke-dashoffset="${-off}" transform="rotate(-90 ${c} ${c})"/>`; off+=len; return el; }).join('');
  return `<svg viewBox="0 0 ${size} ${size}" width="${size}" height="${size}">${arcs}<text x="${c}" y="${c-2}" text-anchor="middle" font-size="20" font-weight="800" fill="#1e3a2e">${tot}</text><text x="${c}" y="${c+14}" text-anchor="middle" font-size="9" fill="#889">itens</text></svg>`; }
function dashBars(rows,fmt){ const max=Math.max(1,...rows.map(r=>r.v)); return rows.map(r=>`<div class="drow"><span style="width:auto;min-width:96px;max-width:150px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">${esc(r.label)}</span><div class="dbar-bg"><div class="dbar-fi" style="width:${Math.round(100*r.v/max)}%;background:${r.color||'var(--verde)'}"></div></div><b style="min-width:64px;text-align:right;font-size:11.5px">${fmt?fmt(r.v):r.v}${r.sub?` <span class="dmini">${esc(r.sub)}</span>`:''}</b></div>`).join(''); }
function dashGantt(rows){ const ds=rows.flatMap(r=>[r.ini,r.fim]).filter(Boolean).sort(); if(!ds.length)return'<div class="dmini">sem datas de cotação</div>';
  const min=new Date(ds[0]+'T00:00:00'), max=new Date(ds[ds.length-1]+'T00:00:00'), span=Math.max(1,(max-min)/864e5);
  return rows.map(r=>{ if(!r.ini||!r.fim)return`<div class="gantt-row"><span>${esc(r.cat)}</span><div class="gantt-track"></div></div>`;
    const a=Math.max(0,(new Date(r.ini+'T00:00:00')-min)/864e5), b=(new Date(r.fim+'T00:00:00')-min)/864e5;
    const l=100*a/span, wd=Math.max(2,100*(b-a)/span);
    return `<div class="gantt-row"><span title="${esc(r.cat)}" style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap">${esc(r.cat)}</span><div class="gantt-track"><div class="gantt-bar" style="left:${l}%;width:${wd}%;background:${r.color||'var(--verde)'}" title="${D(r.ini)}–${D(r.fim)}"></div></div></div>`; }).join(''); }
function nivelChip(n){ const m={critico:['var(--pend)','Crítico'],atrasado:['#c77f1a','Atrasado'],proximo:['var(--dourado)','Em breve']}; const x=m[n]||['#8a9299','—']; return `<span class="dchip" style="background:${x[0]}">${x[1]}</span>`; }
const ST_COR={'Finalizado':'var(--ok)','Contratado':'var(--ok)','Em cotação':'#2b5fa8','Em andamento':'var(--and)','Proposta recebida':'var(--dourado)','Em negociação':'var(--dourado)','Não Iniciado':'#cfd6da'};
function stCor(s){ return ST_COR[s]||'#8a9299'; }

/* ---------- 1) COMPRADOR ---------- */
function renderDashComprador(D){
  const base=(DASH.items&&DASH.items.length)?DASH.items:(MAT||[]);
  const eu=(EU&&EU.nome)||''; const meus=base.filter(i=>(i.responsavel||'').trim() && (!eu||(i.responsavel||'')===eu));
  const escopo = eu && meus.length ? eu : 'toda a equipe';
  const src = (eu && meus.length) ? meus : base;
  const val=i=>Number(i.verba||0), lvl=i=>alertLevel(i);
  const emCot=src.filter(i=>/cota/i.test(i.status||'')).length;
  const crit=src.filter(i=>lvl(i)==='critico');
  const prazo7=src.filter(i=>{const d=i.fim_cotacao?Math.round((new Date(i.fim_cotacao+'T00:00:00')-new Date(D.hoje+'T00:00:00'))/864e5):null; return d!==null&&d>=0&&d<=7&&i.status!=='Finalizado';});
  const props=src.filter(i=>/proposta|negocia/i.test(i.status||'')).length;
  const stc={}; src.forEach(i=>{const s=i.status||'Não Iniciado'; stc[s]=(stc[s]||0)+1;});
  const donutSegs=Object.entries(stc).map(([s,n])=>({v:n,color:stCor(s),label:s}));
  const crList=crit.slice().sort((a,b)=>(a.fim_cotacao||'9999').localeCompare(b.fim_cotacao||'9999')).slice(0,6);
  const prox=src.filter(i=>i.fim_cotacao&&i.status!=='Finalizado').sort((a,b)=>a.fim_cotacao.localeCompare(b.fim_cotacao)).slice(0,6);
  const tab=src.slice().sort((a,b)=>(a.fim_cotacao||'9999').localeCompare(b.fim_cotacao||'9999')).slice(0,8);
  return `
  <div class="dkpis">
    <div class="dkpi"><div class="v">${src.length}</div><div class="l">itens sob responsabilidade<br><span class="dmini">${esc(escopo)}</span></div></div>
    <div class="dkpi"><div class="v blue">${emCot}</div><div class="l">em cotação</div></div>
    <div class="dkpi"><div class="v red">${crit.length}</div><div class="l">críticos (fim venceu)</div></div>
    <div class="dkpi"><div class="v gold">${prazo7.length}</div><div class="l">prazo ≤ 7 dias</div></div>
    <div class="dkpi"><div class="v">${props}</div><div class="l">propostas / negociação</div></div>
  </div>
  <div class="dgrid">
    <div class="dcard"><h3>Status das minhas cotações</h3>
      <div style="display:flex;gap:14px;align-items:center;flex-wrap:wrap">${dashDonut(donutSegs)}
        <div style="flex:1;min-width:120px">${Object.entries(stc).sort((a,b)=>b[1]-a[1]).map(([s,n])=>`<div class="drow" style="padding:3px 0"><span class="dgm" style="background:${stCor(s)}"></span><span style="flex:1">${esc(s)}</span><b>${n}</b> <span class="dmini">${Math.round(100*n/(src.length||1))}%</span></div>`).join('')}</div>
      </div></div>
    <div class="dcard"><h3>Meus itens críticos</h3>${crList.length?crList.map(i=>`<div class="drow"><span class="material-icons" style="font-size:16px;color:var(--pend)">warning</span><span style="flex:1;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">${esc(i.nome)} <span class="dmini">· ${esc(i.obra_nome||'')}</span></span><span class="dmini">${D2(i.fim_cotacao)}</span> <b style="min-width:66px;text-align:right">${BRL(val(i))}</b></div>`).join(''):'<div class="dmini">Nenhum item crítico. 👏</div>'}</div>
    <div class="dcard"><h3>Próximos vencimentos de cotação</h3>${prox.length?prox.map(i=>`<div class="drow"><span class="dgm" style="background:${obraCor(i.obra_id)}"></span><span style="flex:1;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">${esc(i.nome)}</span><span class="dmini">${D2(i.fim_cotacao)}</span> <b style="min-width:66px;text-align:right">${BRL(val(i))}</b></div>`).join(''):'<div class="dmini">—</div>'}</div>
    <div class="dcard wide"><h3>Minhas aquisições</h3>
      <div style="overflow-x:auto"><table class="dtable"><thead><tr><th>Item</th><th>Obra</th><th>Fim cotação</th><th>Status</th><th class="r">Verba</th></tr></thead><tbody>
      ${tab.map(i=>`<tr><td>${esc(i.nome)}</td><td>${esc(i.obra_nome||'')}</td><td>${D2(i.fim_cotacao)} ${lvl(i)==='critico'?'<span class="dchip" style="background:var(--pend)">vencido</span>':''}</td><td><span class="dchip" style="background:${stCor(i.status||'Não Iniciado')}">${esc(i.status||'Não Iniciado')}</span></td><td class="r">${BRL(val(i))}</td></tr>`).join('')}
      </tbody></table></div></div>
  </div>`;
}
function D2(s){ if(!s)return'—'; const p=String(s).split('-'); return p.length===3?p[2]+'/'+p[1]:s; }

/* ---------- 2) GERENTE DE COMPRAS ---------- */
function renderDashGerente(D){
  const cobPct=(()=>{ let cov=0,tot=0; Object.values(DASH.oppByObra).forEach(d=>{ if(d.resumo){cov+=d.resumo.coberto||0;tot+=d.resumo.total||0;} }); return tot?Math.round(100*cov/tot):null; })();
  const g=D.gatilhos;
  return `
  <div class="dkpis">
    <div class="dkpi"><div class="v">${D.totalItens}</div><div class="l">itens no radar</div></div>
    <div class="dkpi"><div class="v ${D.pctComData>=80?'':'gold'}">${D.pctComData}%</div><div class="l">com data definida</div></div>
    <div class="dkpi"><div class="v red">${D.criticos}</div><div class="l">críticos</div></div>
    <div class="dkpi"><div class="v gold">${BRL(D.expostoAtraso)}</div><div class="l">valor exposto (atraso)</div></div>
    <div class="dkpi"><div class="v blue">${cobPct!=null?cobPct+'%':'—'}</div><div class="l">cobertura da verba</div></div>
  </div>
  <div class="dgrid">
    <div class="dcard"><h3>Ranking de compradores (por exposição)</h3>${D.compradores.length?dashBars(D.compradores.slice(0,6).map(c=>({label:c.nome,v:c.exposta,color:c.risco>66?'var(--pend)':(c.risco>33?'var(--dourado)':'var(--verde)'),sub:c.criticos?c.criticos+' crít.':''})),BRL):'<div class="dmini">sem responsáveis atribuídos</div>'}</div>
    <div class="dcard"><h3>Semáforo por obra</h3><table class="dtable"><thead><tr><th>Obra</th><th class="r">Itens</th><th class="r">Críticos</th><th class="r">Risco</th></tr></thead><tbody>
      ${D.porObra.map(o=>`<tr><td><span class="dgm" style="background:${o.cor}"></span> ${esc(o.nome)}</td><td class="r">${o.itens}</td><td class="r">${o.criticos}</td><td class="r"><span class="dgm" style="background:${o.risco>66?'var(--pend)':(o.risco>33?'var(--dourado)':'var(--ok)')}"></span></td></tr>`).join('')}</tbody></table></div>
    <div class="dcard"><h3>Linha do tempo de gatilhos</h3>
      <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:8px;text-align:center">
        <div><div style="font-size:20px;font-weight:800;color:var(--pend)">${g.atras.n}</div><div class="dmini">Atrasados</div><div class="dmini">${BRL(g.atras.v)}</div></div>
        <div><div style="font-size:20px;font-weight:800;color:#c77f1a">${g.d7.n}</div><div class="dmini">≤ 7 dias</div><div class="dmini">${BRL(g.d7.v)}</div></div>
        <div><div style="font-size:20px;font-weight:800;color:var(--dourado)">${g.d15.n}</div><div class="dmini">8–15 dias</div><div class="dmini">${BRL(g.d15.v)}</div></div>
        <div><div style="font-size:20px;font-weight:800;color:var(--verde)">${g.d30.n}</div><div class="dmini">15+ dias</div><div class="dmini">${BRL(g.d30.v)}</div></div>
      </div></div>
    <div class="dcard wide"><h3>O que precisa de atuação hoje</h3><div style="overflow-x:auto"><table class="dtable"><thead><tr><th>Item</th><th>Obra</th><th>Responsável</th><th>Nível</th><th>Próxima ação</th></tr></thead><tbody>
      ${D.atuacao.slice(0,10).map(a=>`<tr><td>${esc(a.nome)}</td><td>${esc(a.obra)}</td><td>${esc(a.resp)}</td><td>${nivelChip(a.nivel)}</td><td>${esc(a.acao)}</td></tr>`).join('')||'<tr><td colspan="5" class="dmini">Nada urgente. 👍</td></tr>'}
      </tbody></table></div></div>
  </div>`;
}

/* ---------- 3) DIRETOR ---------- */
function renderDashDiretor(D){
  const contratado=D.porObra.reduce((a,o)=>a+o.contratado,0);
  const cr=D.curvaRisco, totRiscoV=cr.A.v+cr.B.v+cr.C.v;
  const donut=[{v:cr.A.v,color:'var(--pend)'},{v:cr.B.v,color:'var(--dourado)'},{v:cr.C.v,color:'#8a9299'}];
  const dpct=v=>totRiscoV?Math.round(100*v/totRiscoV):0;
  return `
  <div class="dkpis">
    <div class="dkpi"><div class="v">${BRL(D.verbaTotal)}</div><div class="l">verba total no radar</div></div>
    <div class="dkpi"><div class="v red">${BRL(D.expostoAtraso)}</div><div class="l">exposição em atraso</div></div>
    <div class="dkpi"><div class="v">${D.porObra.filter(o=>o.risco>50).length}</div><div class="l">obras em risco</div></div>
    <div class="dkpi"><div class="v blue">${BRL(contratado)}</div><div class="l">já contratado (finalizado)</div></div>
    <div class="dkpi"><div class="v gold">${BRL(D.emergencial)}</div><div class="l">compras emergenciais</div></div>
  </div>
  <div class="dgrid">
    <div class="dcard"><h3>Ranking de obras por risco</h3>${dashBars(D.porObra.slice(0,8).map(o=>({label:o.nome,v:o.exposta,color:o.cor,sub:o.criticos?o.criticos+' crít.':''})),BRL)}</div>
    <div class="dcard"><h3>Curva ABC em risco</h3><div style="display:flex;gap:14px;align-items:center;flex-wrap:wrap">
      <svg viewBox="0 0 120 120" width="120" height="120">${(function(){const cir=2*Math.PI*54;let off=0;return donut.filter(s=>s.v>0).map(s=>{const len=cir*s.v/(totRiscoV||1);const el=`<circle cx="60" cy="60" r="54" fill="none" stroke="${s.color}" stroke-width="12" stroke-dasharray="${len} ${cir-len}" stroke-dashoffset="${-off}" transform="rotate(-90 60 60)"/>`;off+=len;return el;}).join('');})()}<text x="60" y="64" text-anchor="middle" font-size="13" font-weight="800" fill="#1e3a2e">${BRLc(totRiscoV).replace('R$ ','')}</text></svg>
      <div style="flex:1;min-width:130px">
        <div class="drow"><span class="dchip a">A</span><span style="flex:1">≥ R$200 mil</span><b>${cr.A.n}</b> <span class="dmini">${dpct(cr.A.v)}%</span></div>
        <div class="drow"><span class="dchip b">B</span><span style="flex:1">R$100–200 mil</span><b>${cr.B.n}</b> <span class="dmini">${dpct(cr.B.v)}%</span></div>
        <div class="drow"><span class="dchip c">C</span><span style="flex:1">&lt; R$100 mil</span><b>${cr.C.n}</b> <span class="dmini">${dpct(cr.C.v)}%</span></div>
      </div></div></div>
    <div class="dcard"><h3>Verba × contratado × exposto</h3>
      ${dashBars([{label:'Verba total',v:D.verbaTotal,color:'#8a9299'},{label:'Contratado',v:contratado,color:'var(--ok)'},{label:'Em cotação/aberto',v:D.verbaTotal-contratado,color:'#2b5fa8'},{label:'Exposto (atraso)',v:D.expostoAtraso,color:'var(--pend)'}],BRL)}
      <div class="dmini" style="margin-top:8px">Exposto = verba de itens que passaram do gatilho de cotação e ainda não fecharam.</div></div>
    <div class="dcard wide"><h3>Top exposições financeiras</h3><div style="overflow-x:auto"><table class="dtable"><thead><tr><th>Item</th><th>Obra</th><th>Nível</th><th class="r">Valor exposto</th></tr></thead><tbody>
      ${D.itensCriticos.slice(0,8).map(i=>`<tr><td>${esc(i.nome)}</td><td>${esc(i.obra)}</td><td>${nivelChip(i.nivel)}</td><td class="r">${BRL(i.verba)}</td></tr>`).join('')||'<tr><td colspan="4" class="dmini">Sem exposição. 👍</td></tr>'}
      </tbody></table></div></div>
  </div>`;
}

/* ---------- 4) OPORTUNIDADES ---------- */
function renderDashOpp(D){
  const o=D.opp;
  const janelas=o.categorias.filter(c=>c.ini&&c.fim).slice(0,10).map(c=>({cat:c.cat+(c.obras>=2?' ('+c.obras+' obras)':''),ini:c.ini,fim:c.fim,color:c.obras>=2?'var(--dourado)':'var(--verde)'}));
  return `
  <div class="dkpis">
    <div class="dkpi"><div class="v gold">${o.lotes.length}</div><div class="l">categorias em ≥2 obras (lote)</div></div>
    <div class="dkpi"><div class="v">${BRL(o.valorLote)}</div><div class="l">valor agrupável (lote)</div></div>
    <div class="dkpi"><div class="v blue">${o.obrasEnvolvidas}</div><div class="l">obras no radar</div></div>
    <div class="dkpi"><div class="v">${o.janela60}</div><div class="l">categorias c/ janela ≤60d</div></div>
    <div class="dkpi"><div class="v red">${BRL(o.gapTotal)}</div><div class="l">gap de suprimentos (curva A/B)</div></div>
  </div>
  <div class="dgrid">
    <div class="dcard col2"><h3>Negociações em lote — mesma categoria em várias obras</h3><div style="overflow-x:auto"><table class="dtable"><thead><tr><th>Categoria</th><th class="r">Obras</th><th class="r">Itens</th><th>Janela de cotação</th><th class="r">Valor agrupável</th></tr></thead><tbody>
      ${o.lotes.slice(0,10).map(c=>`<tr><td><b>${esc(c.cat)}</b></td><td class="r">${c.obras}</td><td class="r">${c.itens}</td><td class="dmini">${c.ini?D2(c.ini):'—'} → ${c.fim?D2(c.fim):'—'}</td><td class="r">${BRL(c.valor)}</td></tr>`).join('')||'<tr><td colspan="5" class="dmini">Nenhuma categoria repetida em 2+ obras ainda.</td></tr>'}
      </tbody></table></div><div class="dmini" style="margin-top:8px">Categorias que aparecem em 2+ obras com janelas próximas = poder de negociação em volume.</div></div>
    <div class="dcard"><h3>Próximas janelas de contratação</h3>${dashGantt(janelas)}<div class="dleg"><span><i style="background:var(--dourado)"></i> lote (2+ obras)</span><span><i style="background:var(--verde)"></i> obra única</span></div></div>
    <div class="dcard col2"><h3>Maiores gaps do orçamento por categoria (curva A/B)</h3>${o.gapCategorias.length?dashBars(o.gapCategorias.map(g=>({label:g.cat,v:g.valor,color:'var(--pend)',sub:g.obras+' obra(s)'})),BRL):'<div class="dmini">Sem gaps de curva A/B.</div>'}<div class="dmini" style="margin-top:8px">Grandes itens do orçamento que o radar ainda não cobre — candidatos a novo item/negociação (ver aba Oportunidades).</div></div>
  </div>`;
}

/* ===================== MAPA DE COTAÇÕES ===================== */
let COT={mode:'list', tab:'cotacoes', list:[], obra:'', cur:null, novoItens:[], prop:null};
function cotInit(){ cotTab(COT.tab||'cotacoes'); }
function cotTab(t){ COT.tab=t; ['cotacoes','fornecedores','cartas','precos'].forEach(x=>{const b=document.getElementById('ctab-'+x); if(b)b.classList.toggle('on',x===t);});
  if(t==='fornecedores') fornLoad(); else if(t==='cartas') cartaLoad(); else if(t==='precos') precLoad(); else cotLoad(); }
function cotStChip(s){ const m={aberta:['#8a9299','Aberta'],aguardando:['var(--dourado)','Aguardando'],finalizada:['var(--ok)','Finalizada']}; const x=m[s]||['#8a9299',s]; return `<span class="dchip" style="background:${x[0]}">${x[1]}</span>`; }
function cotStLabel(s){ return ({aberta:'Aberta',aguardando:'Aguardando',finalizada:'Finalizada'})[s]||s; }
function cotFmtDT(iso){ if(!iso)return '—'; const d=new Date(iso); if(isNaN(d.getTime()))return '—'; const p=n=>('0'+n).slice(-2); return p(d.getDate())+'/'+p(d.getMonth()+1)+'/'+String(d.getFullYear()).slice(2)+' '+p(d.getHours())+':'+p(d.getMinutes()); }
function cotSort(col){ COT.sort=COT.sort||{col:'created_at',dir:-1}; if(COT.sort.col===col) COT.sort.dir=-COT.sort.dir; else COT.sort={col, dir:(col==='created_at'||col==='n_propostas'||col==='n_itens'||col==='melhor_oferta')?-1:1}; cotRender(); }
function cotObraOpts(sel){ return '<option value="">— obra —</option>'+((typeof OBRAS!=='undefined'&&OBRAS)||[]).map(o=>`<option value="${o.id}" ${String(sel)===String(o.id)?'selected':''}>${esc(o.nome)}</option>`).join(''); }
async function cotLoad(){
  const w=document.getElementById('cotwrap'); w.innerHTML='<div class="dempty">Carregando cotações…</div>';
  try{ const d=await (await fetch('actions/cotacoes.php'+(COT.obra?('?obra='+COT.obra+'&'):'?')+'_='+Date.now())).json();
    COT.list=d.cotacoes||[]; COT.mode='list'; cotRender(); cotInboxSweepAuto();
  }catch(e){ w.innerHTML='<div class="dempty">Falha: '+esc(e.message)+'</div>'; }
}
// varredura OPORTUNISTA da caixa ao abrir Cotações (no máx 1x/10min no cliente; o servidor tb tem trava de 30s).
// Enquanto o cron horário não estiver ligado, isto já mantém as respostas chegando quando alguém usa o sistema.
let INBOX_SWEEP_TS=0;
function cotInboxSweepAuto(){ const now=Date.now(); if(now-INBOX_SWEEP_TS<600000)return; INBOX_SWEEP_TS=now;
  fetch('actions/inbox.php?sync=1&me='+encodeURIComponent((EU&&EU.bitrix_id)||'')).then(r=>r.json()).then(r=>{
    if(r&&r.novas){ toast('📨 '+r.novas+' nova(s) resposta(s) na caixa'+(r.casadas?' · '+r.casadas+' casada(s)':'')); if(COT.cur&&COT.cur.cotacao&&COT.mode==='detalhe')cotOpen(COT.cur.cotacao.id); }
  }).catch(()=>{});
}
// botão manual da LISTA de cotações (enquanto o cron horário não roda) — varre a caixa e recarrega os contadores
async function cotBuscarRespostasLista(btn){ if(btn)btn.disabled=true; toast('Buscando respostas na caixa…');
  try{ const r=await (await fetch('actions/inbox.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({acao:'varrer',me:EU&&EU.bitrix_id})})).json();
    INBOX_SWEEP_TS=Date.now();
    if(r.error){toast(r.error);}
    else if(r.throttled){toast(r.msg||'Verifiquei agora há pouco.');}
    else{ const p=[]; if(r.novas)p.push(r.novas+' nova(s)'); if(r.casadas)p.push(r.casadas+' casada(s)'); if(r.cotacoes)p.push(r.cotacoes+' cotação'); if(r.duvidas)p.push(r.duvidas+' dúvida(s)'); toast(p.length?('Caixa: '+p.join(' · ')):'Nada novo'); if(r.avisos&&r.avisos.length)setTimeout(()=>toast(r.avisos[0]),1500); }
    cotLoad();   // recarrega a lista → atualiza os contadores 📨 por cotação (e o total)
  }catch(e){toast('Falha: '+e.message);}
}
function cotRender(){
  if(COT.mode==='novo') return cotRenderNovo();
  if(COT.mode==='detalhe') return cotRenderDetalhe();
  if(COT.mode==='proposta') return cotRenderProposta();
  const w=document.getElementById('cotwrap');
  COT.filt=COT.filt||{q:'',categoria:'',status:''}; COT.sort=COT.sort||{col:'created_at',dir:-1};
  const all=COT.list||[];
  const inboxNovoTotal=all.reduce((s,c)=>s+(+c.n_inbound_novo||0),0);   // e-mails de fornecedor ainda não processados (global)
  const cats=[...new Set(all.map(c=>c.categoria).filter(Boolean))].sort();
  const sts=[...new Set(all.map(c=>c.status).filter(Boolean))];
  // filtro que não existe mais nesta obra → limpa (evita lista vazia enganosa com o dropdown mostrando "Todas")
  if(COT.filt.categoria && !cats.includes(COT.filt.categoria)) COT.filt.categoria='';
  if(COT.filt.status && !sts.includes(COT.filt.status)) COT.filt.status='';
  // filtros CLIENT-SIDE (a obra continua server-side no cotLoad)
  const qn=opNorm(COT.filt.q||'');
  let rows=all.filter(c=>
    (!qn || opNorm((c.titulo||'')+' '+(c.categoria||'')+' '+(c.obra_nome||'')+' '+(c.num_solicitacao||'')+' '+(c.num_pedido||'')).includes(qn)) &&
    (!COT.filt.categoria || (c.categoria||'')===COT.filt.categoria) &&
    (!COT.filt.status || (c.status||'')===COT.filt.status));
  // ordenação
  const sc=COT.sort.col, dir=COT.sort.dir;
  const sval=c=>({titulo:(c.titulo||'').toLowerCase(),obra_nome:(c.obra_nome||'').toLowerCase(),categoria:(c.categoria||'').toLowerCase(),
      n_itens:+c.n_itens||0,n_propostas:+c.n_propostas||0,melhor_oferta:+c.melhor_oferta||0,status:(c.status||''),created_at:(c.created_at||''),id:+c.id||0}[sc]);
  rows=rows.slice().sort((a,b)=>{ const x=sval(a),y=sval(b); return (x<y?-1:x>y?1:0)*dir; });
  const arw=col=>COT.sort.col===col?(COT.sort.dir>0?' ▲':' ▼'):'';
  const th=(lbl,col,extra)=>`<th ${extra||''} onclick="cotSort('${col}')" style="cursor:pointer;user-select:none;white-space:nowrap">${lbl}${arw(col)}</th>`;
  let html=`<div class="panel" style="margin-bottom:10px"><div class="bar" style="gap:8px;flex-wrap:wrap;align-items:center">
     <div class="search" style="min-width:170px"><span class="material-icons" style="color:var(--muted)">search</span><input id="cotListBusca" placeholder="Buscar cotação, nº solicitação ou pedido…" value="${esc(COT.filt.q)}" oninput="COT.filt.q=this.value;cotRender()"></div>
     <label class="muted" style="font-size:12px">Obra <select onchange="COT.obra=this.value;cotLoad()" style="margin-left:4px">${cotObraOpts(COT.obra)}</select></label>
     <select onchange="COT.filt.categoria=this.value;cotRender()" style="font-size:12px;padding:6px"><option value="">Todas categorias</option>${cats.map(c=>`<option value="${esc(c)}" ${c===COT.filt.categoria?'selected':''}>${esc(c)}</option>`).join('')}</select>
     <select onchange="COT.filt.status=this.value;cotRender()" style="font-size:12px;padding:6px"><option value="">Todos status</option>${sts.map(s=>`<option value="${esc(s)}" ${s===COT.filt.status?'selected':''}>${esc(cotStLabel(s))}</option>`).join('')}</select>
     <span class="muted" style="font-size:11.5px">${rows.length} de ${all.length}</span>
     <span style="margin-left:auto;display:flex;gap:8px;align-items:center;flex-wrap:wrap">
       ${inboxNovoTotal?`<span class="dchip" style="background:var(--pend);color:#fff" title="e-mails de fornecedores ainda não processados — clique em Buscar respostas">📨 ${inboxNovoTotal} não processada(s)</span>`:''}
       ${CAN_EDIT?`<button class="btn-ghost" style="padding:7px 12px" onclick="cotBuscarRespostasLista(this)" title="ler a caixa suprimentos@ agora (enquanto o cron de hora em hora não roda)"><span class="material-icons" style="font-size:15px;vertical-align:-3px">mark_email_unread</span> Buscar respostas</button>`:''}
       ${CAN_EDIT?`<button class="btn-prim" style="padding:7px 14px" onclick="cotNovo()"><span class="material-icons" style="font-size:16px;vertical-align:-3px">add</span> Nova cotação</button>`:''}
     </span>
   </div></div><div class="wrap"><table><thead><tr>${th('Cotação','titulo')}${th('Obra','obra_nome')}${th('Categoria','categoria')}<th>Tipo</th><th title="Nº da solicitação de compra / nº do pedido de compra">SC / Pedido</th>${th('Itens','n_itens','style="text-align:center"')}${th('Propostas','n_propostas','style="text-align:center" title="recebidas / convidados"')}${th('Melhor oferta','melhor_oferta','style="text-align:right"')}${th('Criada em','created_at')}${th('Status','status')}<th></th></tr></thead><tbody>`;
  for(const c of rows){
    html+=`<tr style="cursor:pointer" onclick="cotOpen(${c.id})"><td><b>${esc(c.titulo)}</b>${(+c.n_inbound_novo)?` <span class="dchip" style="background:var(--pend);color:#fff" title="${c.n_inbound_novo} e-mail(s) de fornecedor não processado(s) nesta cotação">📨 ${c.n_inbound_novo}</span>`:''}</td><td>${esc(c.obra_nome||'—')}</td><td class="muted">${esc(c.categoria||'')}</td><td class="muted">${esc(c.tipo_servico||'')}</td>
      <td class="muted" style="font-size:11px;white-space:nowrap;line-height:1.35">${c.num_solicitacao?('SC '+esc(c.num_solicitacao)):''}${c.num_solicitacao&&c.num_pedido?'<br>':''}${c.num_pedido?('<b style="color:var(--verde-d)">PC '+esc(c.num_pedido)+'</b>'):''}${!c.num_solicitacao&&!c.num_pedido?'—':''}</td>
      <td style="text-align:center">${c.n_itens}</td><td style="text-align:center" title="${c.n_propostas} recebida(s) de ${c.n_convidados||0} convidado(s)"><b>${c.n_propostas}</b><span class="muted">/${c.n_convidados||0}</span></td><td style="text-align:right">${c.melhor_oferta?BRL(c.melhor_oferta):'—'}</td><td class="muted" style="font-size:11.5px;white-space:nowrap">${cotFmtDT(c.created_at)}</td><td>${cotStChip(c.status)}</td>
      <td><span class="material-icons" style="color:var(--muted)">chevron_right</span></td></tr>`;
  }
  if(!rows.length) html+=`<tr><td colspan="11" class="empty">${all.length?'Nenhuma cotação casa os filtros.':'Nenhuma cotação ainda. Crie a primeira.'}</td></tr>`;
  // preserva foco/caret da busca — o innerHTML recria o input a cada tecla e mataria o foco
  const _foc=document.activeElement, _wasBusca=_foc&&_foc.id==='cotListBusca', _car=_wasBusca?_foc.selectionStart:null;
  w.innerHTML=html+'</tbody></table></div>';
  if(_wasBusca){ const ni=document.getElementById('cotListBusca'); if(ni){ ni.focus(); try{ ni.setSelectionRange(_car,_car); }catch(e){} } }
}
function cotNovo(){ COT.mode='novo'; COT.novoServico=null; COT.novoPre=null; COT.novoConvidados=[]; COT.novoItens=[{descricao:'',unidade:'',quantidade:'',observacao:''}]; cotRender(); }
// iniciar cotação A PARTIR de um item do radar: puxa o dicionário de cotação do serviço (itens EDITÁVEIS) + pré-preenche
async function cotIniciar(sid, obra, nome, grupo){
  ['radar','matriz','oportunidades','dashboards','cotacoes','config','audit','updates'].forEach(x=>{ const v=document.getElementById('view-'+x); if(v)v.style.display=x==='cotacoes'?'':'none'; const n=document.getElementById('nav-'+x); if(n)n.classList.toggle('active',x==='cotacoes'); });
  if(typeof closeModal==='function'){ try{ closeModal(); }catch(e){} }
  COT.tab='cotacoes'; ['cotacoes','fornecedores'].forEach(x=>{ const b=document.getElementById('ctab-'+x); if(b)b.classList.toggle('on',x==='cotacoes'); });
  // puxa o ITEM COMPLETO da obra (quantitativo/escopo/variáveis) + o dicionário do serviço (fallback + nome/grupo)
  let it=null, dic={itens:[]};
  try{ const url='actions/matriz.php'+(String(obra||1)!=='1'?('?obra='+obra+'&'):'?')+'_='+Date.now(); const d=await (await fetch(url)).json(); it=(d.itens||[]).find(x=>Number(x.ordem)===Number(sid))||null; }catch(e){}
  try{ dic=await (await fetch('actions/cotacoes.php?dicionario='+sid+'&_='+Date.now())).json(); }catch(e){}
  const svNome=nome||(it&&it.nome)||(dic.servico&&dic.servico.nome)||'';
  const svGrupo=grupo||(it&&it.grupo)||(dic.servico&&dic.servico.grupo)||'';
  COT.novoServico=sid; COT.novoServicoNome=svNome; COT.novoConvidados=[]; COT.novoVincItem=it; COT.novoVincObra=obra?String(obra):'';
  COT.novoPre={obra:obra?String(obra):'', titulo:svNome, categoria:svGrupo, descricao:(it&&it.escopo)||'', equalizacao:it?cotEqTexto(it):'', verba:(it&&it.verba&&it.verba>0)?it.verba:'', verba_origem:it?cotVerbaOrigem(it):''};
  // itens a cotar: quantitativo REAL da obra → dicionário do serviço → 1 item vazio
  let itens=it?await cotItensFromQuant(it, obra):[]; let src=itens.length?'quantitativo da obra':'';
  if(!itens.length&&dic.itens&&dic.itens.length){ itens=dic.itens.map(i=>({descricao:i.descricao,unidade:i.unidade||'',quantidade:'',observacao:i.nota||''})); src='dicionário do serviço'; }
  if(!itens.length) itens=[{descricao:svNome,unidade:'',quantidade:'',observacao:''}];
  COT.novoItens=itens;
  COT.mode='novo'; cotRenderNovo();
  toast(src?(itens.length+' item(ns) do '+src+' — edite como precisar'):'Monte os itens a cotar (sem quantitativo/dicionário p/ este serviço ainda)');
}
async function cotAbrir(id){
  ['radar','matriz','oportunidades','dashboards','cotacoes','config','audit','updates'].forEach(x=>{ const v=document.getElementById('view-'+x); if(v)v.style.display=x==='cotacoes'?'':'none'; const n=document.getElementById('nav-'+x); if(n)n.classList.toggle('active',x==='cotacoes'); });
  if(typeof closeModal==='function'){ try{ closeModal(); }catch(e){} }
  COT.tab='cotacoes'; ['cotacoes','fornecedores'].forEach(x=>{ const b=document.getElementById('ctab-'+x); if(b)b.classList.toggle('on',x==='cotacoes'); });
  await cotOpen(id);
}
function cotRenderNovo(){
  if(!OBRAS_UNI_LOADED){ obrasUniEnsure().then(cotRenderNovo); return; }   // garante o cadastro único antes de montar o dropdown de obra
  const pre=COT.novoPre||{}, vinc=COT.novoServico;
  document.getElementById('cotwrap').innerHTML=`<div class="panel">
    <div style="display:flex;align-items:center;gap:8px;margin-bottom:12px"><button class="btn-ghost" onclick="cotLoad()"><span class="material-icons" style="font-size:16px;vertical-align:-3px">arrow_back</span> Voltar</button><b style="font-size:15px">Nova cotação</b>
      ${vinc?`<span class="dchip" style="background:#eef4f0;color:var(--verde-d)"><span class="material-icons" style="font-size:12px;vertical-align:-2px">link</span> vinculada ao radar: ${esc(COT.novoServicoNome||'')}</span>`:'<span id="cotVincChip"></span>'}</div>
    ${vinc?`<div class="dmini" style="margin:-6px 0 10px">Itens puxados do dicionário de cotação do serviço — <b>edite à vontade</b> (a puxada automática é só um ponto de partida).</div>`
          :`<div id="cotVincBox" style="margin:-4px 0 12px;padding:8px 10px;background:#f7faf8;border:1px dashed var(--line);border-radius:8px">
      <div id="cotVincClosed"><button class="btn-ghost" style="padding:3px 9px" onclick="cotVincOpen()"><span class="material-icons" style="font-size:15px;vertical-align:-3px;color:var(--verde)">link</span> Vincular a um item do radar</button>
        <span class="muted" style="font-size:11px">— opcional; liga o status "em cotação" no radar e mostra o mapa dentro do item</span></div>
      <div id="cotVincPick" style="display:none">
        <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center">
          <select id="cotVincO" onchange="cotVincObra()" style="padding:5px 6px;border:1px solid var(--line);border-radius:7px">${cotObraOpts(pre.obra||'')}</select>
          <div class="search" style="min-width:220px;max-width:340px"><span class="material-icons" style="color:var(--muted)">search</span><input id="cotVincBusca" placeholder="Buscar item do radar por nome…" oninput="cotVincBuscaInput()" autocomplete="off" ${pre.obra?'':'disabled'}></div>
          <button class="btn-ghost" style="padding:3px 8px" onclick="cotVincCancel()">cancelar</button></div>
        <div id="cotVincSug"></div></div></div>`}
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:10px">
      ${cotFld('Título *','<input id="cotT" value="'+esc(pre.titulo||'')+'" placeholder="Ex.: MO Forro de Gesso">')}
      ${cotFld('Obra','<select id="cotO">'+obrasUniOpts(pre.obra?obrasUniFichaDoRadar(pre.obra):'')+'</select>')}
      ${cotFld('Categoria','<input id="cotC" value="'+esc(pre.categoria||'')+'" placeholder="Ex.: M.O. Gesso">')}
      ${cotFld('Tipo','<select id="cotTipo"><option>Material</option><option>M.O.</option><option>Material + MO</option><option>Locação</option><option>Serviço</option></select>')}
      ${cotFld('Verba (R$) <span id="cotVerbaChip">'+cotVerbaChip(pre.verba_origem||'')+'</span>','<input id="cotV" type="text" inputmode="decimal" placeholder="0,00" oninput="maskMoneyInput(this)" onblur="moneyBlur(this)" value="'+(pre.verba!=null&&pre.verba!==''?esc(fmtMoney(pre.verba)):'')+'">')}
      ${cotFld('Nº Solicitação de compra','<input id="cotSC" value="'+esc(pre.num_solicitacao||'')+'" placeholder="opcional — se nasceu de uma SC">')}
    </div>
    ${cotFld('Descrição / escopo (vai na carta ao fornecedor)','<textarea id="cotD" rows="5" style="width:100%" placeholder="Escopo / informações gerais da cotação">'+esc(pre.descricao||'')+'</textarea>','margin-top:8px')}
    ${cotFld('Pontos a conferir por proposta — equalização (1 por linha)','<textarea id="cotEq" rows="8" style="width:100%" placeholder="Ex.: Diesel incluso? · Faturamento mínimo diário · Mobilização/desmobilização · Retenção · ISS · ART">'+esc(pre.equalizacao||DEFAULT_EQ.join('\n'))+'</textarea>','margin-top:8px')}
    <div style="display:flex;justify-content:space-between;align-items:center;margin-top:12px;flex-wrap:wrap;gap:6px"><b style="font-size:13px">Itens a cotar *</b>
      <span style="display:flex;gap:6px">${vinc?`<button class="btn-ghost" style="padding:4px 10px" onclick="cotSalvarDicionario()" title="Grava estes itens como padrão do serviço — as próximas cotações deste serviço já vêm com eles"><span class="material-icons" style="font-size:15px;vertical-align:-3px">menu_book</span> Salvar como padrão do serviço</button>`:''}
      <label class="btn-ghost" style="padding:4px 10px;cursor:pointer;color:var(--verde-d)" title="a IA lê um orçamento (PDF/Excel/imagem) e cria os itens a cotar"><span class="material-icons" style="font-size:15px;vertical-align:-3px">auto_awesome</span> Importar de PDF (IA)<input type="file" accept=".pdf,.xlsx,.xls,image/png,image/jpeg,application/pdf" style="display:none" onchange="cotImportarItensIA(this)"></label>
      <button class="btn-ghost" style="padding:4px 10px" onclick="cotImportarTexto()"><span class="material-icons" style="font-size:15px;vertical-align:-3px">content_paste</span> Importar via texto</button></span></div>
    <div id="cotItens" style="margin-top:8px"></div>
    <button class="btn-ghost" style="margin-top:6px" onclick="cotAddItem()"><span class="material-icons" style="font-size:15px;vertical-align:-3px">add</span> Adicionar item</button>
    <div style="margin-top:16px;display:flex;align-items:center;gap:10px;flex-wrap:wrap"><b style="font-size:13px">Fornecedores convidados (concorrência)</b> <span class="muted" style="font-size:11px">— quem vai participar; depois você acompanha quem respondeu</span>
      <button class="btn-ghost" style="padding:4px 11px" onclick="cotFornPickerOpen('novo')"><span class="material-icons" style="font-size:15px;vertical-align:-3px;color:var(--verde)">group_add</span> Convidar fornecedores</button></div>
    <div id="cotConvidados" style="margin-top:8px"></div>
    <div style="margin-top:16px"><button class="btn-prim" onclick="cotCriar()"><span class="material-icons" style="font-size:16px;vertical-align:-3px">check</span> Criar cotação</button></div>
  </div>`;
  cotRenderItens(); cotRenderConvidados();
}
let _cotFT;
function cotFornBuscaInput(){
  clearTimeout(_cotFT);
  const q=(document.getElementById('cotFornBusca').value||'').trim(), box=document.getElementById('cotFornSug'); if(!box)return;
  if(q.length<2){ box.style.display='none'; box.innerHTML=''; return; }
  _cotFT=setTimeout(async()=>{
    // busca AMPLA por nome/itens/categoria/cidade — categoria NÃO filtra (evita zerar por taxonomia divergente)
    try{ const d=await (await fetch('actions/fornecedores.php?q='+encodeURIComponent(q)+'&limit=14')).json();
      COT.fornBusca=d.fornecedores||[];
      box.innerHTML=COT.fornBusca.length?COT.fornBusca.map((f,i)=>`<div onclick="cotAddConvidado(${i})" style="padding:7px 10px;cursor:pointer;font-size:12.5px;border-bottom:1px solid #f1f3f2" onmouseover="this.style.background='#eff7f1'" onmouseout="this.style.background=''"><b>${esc(f.nome)}</b> <span class="muted" style="font-size:10.5px">· ${esc(f.categoria||'')}${f.cidade?' · '+esc(f.cidade):''}${f.tipo?' · '+esc(f.tipo):''}</span></div>`).join(''):'<div class="dmini" style="padding:8px">nenhum fornecedor casa "'+esc(q)+'"</div>';
      box.style.display='block';
    }catch(e){}
  },300);
}
function cotAddConvidado(idx){
  const f=(COT.fornBusca||[])[idx]; if(!f)return; COT.novoConvidados=COT.novoConvidados||[];
  if(!COT.novoConvidados.some(c=>(c.id&&c.id===f.id)||c.nome===f.nome)) COT.novoConvidados.push({id:f.id,nome:f.nome,categoria:f.categoria,contato:f.contato,email:f.email,telefone:f.telefone});
  const inp=document.getElementById('cotFornBusca'); if(inp)inp.value=''; const b=document.getElementById('cotFornSug'); if(b){b.style.display='none';b.innerHTML='';}
  cotRenderConvidados();
}
function cotDelConvidado(idx){ COT.novoConvidados.splice(idx,1); cotRenderConvidados(); }
function cotRenderConvidados(){
  const box=document.getElementById('cotConvidados'); if(!box)return; const cv=COT.novoConvidados||[];
  box.innerHTML=cv.length?('<div style="display:flex;flex-wrap:wrap;gap:6px">'+cv.map((c,i)=>`<span class="dchip" style="background:#eef4f0;color:var(--verde-d);font-weight:600;display:inline-flex;align-items:center;gap:5px"><span class="material-icons" style="font-size:13px">business</span>${esc(c.nome)}<span onclick="cotDelConvidado(${i})" style="cursor:pointer;color:var(--pend)" title="tirar">×</span></span>`).join('')+`</div><div class="dmini" style="margin-top:4px">${cv.length} convidado(s)</div>`):'<div class="dmini">Nenhum convidado ainda — busque acima (dá pra adicionar depois também).</div>';
}
document.addEventListener('click',e=>{ if(!(e.target.closest&&e.target.closest('#cotFornBusca,#cotFornSug'))){ const b=document.getElementById('cotFornSug'); if(b) b.style.display='none'; } });
/* ====== Picker de fornecedores — multi-seleção por nome/item OU por categoria (usado no criar e no mapa) ====== */
let COT_PICK={mode:'novo', sel:{}, list:[], cats:[]}; let _cotPickT;
async function cotFornPickerOpen(mode){
  COT_PICK={mode, sel:{}, list:[], cats:COT_PICK.cats||[]};
  let ov=document.getElementById('cotPickOverlay');
  if(!ov){ ov=document.createElement('div'); ov.id='cotPickOverlay'; document.body.appendChild(ov); }
  ov.style.cssText='position:fixed;inset:0;z-index:250;background:rgba(18,28,22,.5);display:flex;align-items:center;justify-content:center;padding:20px';
  ov.onclick=(e)=>{ if(e.target===ov) cotPickClose(); };
  ov.innerHTML=`<div style="background:#fff;border-radius:12px;width:min(700px,96vw);max-height:88vh;display:flex;flex-direction:column;box-shadow:0 24px 70px rgba(0,0,0,.35)" onclick="event.stopPropagation()">
    <div style="padding:14px 18px;border-bottom:1px solid var(--line);display:flex;align-items:center;gap:10px">
      <span class="material-icons" style="color:var(--verde)">group_add</span><b style="font-size:15px">Convidar fornecedores</b>
      <span class="muted" style="font-size:11px">busque por nome/item, ou escolha uma categoria — marque vários e adicione de uma vez</span>
      <button class="btn-ghost" style="margin-left:auto;padding:3px 9px" onclick="cotPickClose()">✕</button></div>
    <div style="padding:12px 18px 8px;display:flex;gap:8px;flex-wrap:wrap;align-items:center">
      <div class="search" style="flex:1;min-width:200px"><span class="material-icons" style="color:var(--muted)">search</span><input id="cotPickQ" placeholder="Nome, item ou categoria…" oninput="cotPickSearch()" autocomplete="off"></div>
      <select id="cotPickCat" onchange="cotPickSearch()" style="padding:7px 8px;border:1px solid var(--line);border-radius:8px;max-width:240px;font-size:12.5px"><option value="">— navegar por categoria —</option></select>
    </div>
    <div id="cotPickList" style="flex:1;overflow:auto;padding:0 18px 4px;min-height:120px"><div class="dmini" style="padding:16px 0">Digite ao menos 2 letras ou escolha uma categoria.</div></div>
    <div style="padding:12px 18px;border-top:1px solid var(--line);display:flex;align-items:center;gap:10px">
      <span id="cotPickCount" class="muted" style="font-size:12.5px">0 selecionados</span>
      <button class="btn-prim" style="margin-left:auto;padding:8px 16px" onclick="cotPickAdd()"><span class="material-icons" style="font-size:15px;vertical-align:-3px">check</span> Adicionar selecionados</button></div>
  </div>`;
  if(!COT_PICK.cats.length){ try{ const d=await (await fetch('actions/fornecedores.php?categorias=1')).json(); COT_PICK.cats=d.categorias||[]; }catch(e){} }
  const sel=document.getElementById('cotPickCat'); if(sel) sel.innerHTML='<option value="">— navegar por categoria —</option>'+COT_PICK.cats.map(c=>`<option value="${esc(c.nome)}">${esc(c.nome)}</option>`).join('');
  const q=document.getElementById('cotPickQ'); if(q) q.focus();
}
function cotPickClose(){ const ov=document.getElementById('cotPickOverlay'); if(ov) ov.remove(); }
function cotPickSearch(){
  clearTimeout(_cotPickT);
  _cotPickT=setTimeout(async()=>{
    const q=(document.getElementById('cotPickQ')||{}).value||'', cat=(document.getElementById('cotPickCat')||{}).value||'', box=document.getElementById('cotPickList'); if(!box)return;
    if(q.trim().length<2 && !cat){ box.innerHTML='<div class="dmini" style="padding:16px 0">Digite ao menos 2 letras ou escolha uma categoria.</div>'; return; }
    box.innerHTML='<div class="dmini" style="padding:16px 0">Buscando…</div>';
    const p=new URLSearchParams(); p.set('limit','300'); if(q.trim())p.set('q',q.trim()); if(cat)p.set('categoria',cat);
    try{ const d=await (await fetch('actions/fornecedores.php?'+p.toString())).json(); COT_PICK.list=d.fornecedores||[]; cotPickRenderList(); }
    catch(e){ box.innerHTML='<div class="dmini" style="padding:16px 0">Falha na busca.</div>'; }
  },250);
}
function cotPickKey(f){ return f.id?('id:'+f.id):('n:'+(''+(f.nome||'')).toLowerCase().trim()); }
function cotPickRenderList(){
  const box=document.getElementById('cotPickList'); if(!box)return; const L=COT_PICK.list;
  if(!L.length){ box.innerHTML='<div class="dmini" style="padding:16px 0">Nenhum fornecedor encontrado.</div>'; cotPickCount(); return; }
  const allSel=L.every(f=>!!COT_PICK.sel[cotPickKey(f)]);
  box.innerHTML=`<div class="dmini" style="padding:4px 0;display:flex;align-items:center;gap:10px;justify-content:space-between;position:sticky;top:0;background:#fff;z-index:1">
      <span>${L.length} fornecedor(es)${L.length>=300?'+ (refine a busca)':''} — marque os que vão participar</span>
      <button class="btn-ghost" style="padding:2px 10px;font-size:11px" onclick="cotPickAll(${!allSel})"><span class="material-icons" style="font-size:13px;vertical-align:-2px">${allSel?'remove_done':'done_all'}</span> ${allSel?'Limpar seleção':'Selecionar todos ('+L.length+')'}</button></div>`+L.map((f,i)=>{ const on=!!COT_PICK.sel[cotPickKey(f)];
    return `<label style="display:flex;align-items:center;gap:9px;padding:7px 4px;border-bottom:1px solid #f2f4f3;cursor:pointer">
      <input type="checkbox" ${on?'checked':''} onchange="cotPickToggle(${i})" style="width:16px;height:16px">
      <span style="flex:1;font-size:12.5px"><b>${esc(f.nome)}</b> <span class="muted" style="font-size:10.5px">· ${esc(f.categoria||'sem categoria')}${f.cidade?' · '+esc(f.cidade):''}${f.tipo?' · '+esc(f.tipo):''}${f.itens?' · '+esc((''+f.itens).slice(0,40)):''}</span></span></label>`;
  }).join('');
  cotPickCount();
}
function cotPickToggle(i){ const f=COT_PICK.list[i]; if(!f)return; const k=cotPickKey(f); if(COT_PICK.sel[k])delete COT_PICK.sel[k]; else COT_PICK.sel[k]=f; cotPickCount(); }
// marca/desmarca TODOS os fornecedores do filtro atual de uma vez
function cotPickAll(on){ (COT_PICK.list||[]).forEach(f=>{ const k=cotPickKey(f); if(on)COT_PICK.sel[k]=f; else delete COT_PICK.sel[k]; }); cotPickRenderList(); }
function cotPickCount(){ const n=Object.keys(COT_PICK.sel).length, el=document.getElementById('cotPickCount'); if(el)el.textContent=n+' selecionado'+(n===1?'':'s'); }
async function cotPickAdd(){
  const chosen=Object.values(COT_PICK.sel); if(!chosen.length){ toast('Marque ao menos um fornecedor'); return; }
  const norm=f=>({id:f.id,nome:f.nome,categoria:f.categoria,contato:f.contato,email:f.email,telefone:f.telefone});
  if(COT_PICK.mode==='convite'){
    try{ const r=await (await fetch('actions/cotacoes.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({acao:'convidar',me:EU&&EU.bitrix_id,cotacao_id:COT.cur.cotacao.id,convidados:chosen.map(norm)})})).json();
      if(r.error){toast(r.error);return;} cotPickClose(); toast(chosen.length+' fornecedor(es) convidado(s)'); cotOpen(COT.cur.cotacao.id);
    }catch(e){ toast('Falha: '+e.message); }
  } else {
    COT.novoConvidados=COT.novoConvidados||[]; let add=0;
    chosen.forEach(f=>{ if(!COT.novoConvidados.some(c=>(c.id&&c.id===f.id)||c.nome===f.nome)){ COT.novoConvidados.push(norm(f)); add++; } });
    cotPickClose(); cotRenderConvidados(); toast(add+' convidado(s) adicionado(s)');
  }
}
async function cotSalvarDicionario(){
  if(!COT.novoServico){ toast('Sem serviço vinculado'); return; }
  const itens=COT.novoItens.filter(it=>(it.descricao||'').trim()).map(it=>({descricao:it.descricao,unidade:it.unidade,nota:it.observacao}));
  if(!itens.length){ toast('Nenhum item para salvar'); return; }
  if(!confirm('Salvar estes '+itens.length+' itens como padrão do serviço "'+(COT.novoServicoNome||'')+'"?\n\nAs próximas cotações iniciadas deste serviço já virão com eles.')) return;
  try{ const r=await (await fetch('actions/cotacoes.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({acao:'dicionario_salvar',me:EU&&EU.bitrix_id,servico_id:COT.novoServico,itens})})).json();
    if(r.error){ toast(r.error); return; } toast(r.n+' itens salvos no dicionário do serviço');
  }catch(e){ toast('Falha: '+e.message); }
}
function cotFld(label,inner,extra){ return `<div style="${extra||''}"><div class="muted" style="font-size:11px;margin-bottom:2px">${label}</div>${inner}</div>`; }
function cotRenderItens(){
  const box=document.getElementById('cotItens'); if(!box)return;
  box.innerHTML=COT.novoItens.map((it,i)=>`<div style="display:grid;grid-template-columns:1fr 80px 80px 1fr 30px;gap:6px;margin-bottom:6px;align-items:center">
    <input placeholder="Descrição do item" value="${esc(it.descricao)}" oninput="COT.novoItens[${i}].descricao=this.value">
    <input placeholder="Unid." value="${esc(it.unidade)}" oninput="COT.novoItens[${i}].unidade=this.value">
    <input placeholder="Qtde" type="number" value="${esc(it.quantidade)}" oninput="COT.novoItens[${i}].quantidade=this.value">
    <input placeholder="Observação" value="${esc(it.observacao)}" oninput="COT.novoItens[${i}].observacao=this.value">
    <button class="btn-ghost" style="padding:2px" onclick="COT.novoItens.splice(${i},1);cotRenderItens()" title="Remover"><span class="material-icons" style="font-size:16px;color:var(--pend)">close</span></button></div>`).join('');
}
function cotAddItem(){ COT.novoItens.push({descricao:'',unidade:'',quantidade:'',observacao:''}); cotRenderItens(); }
/* ---- Vincular cotação standalone a um item do radar (Fase 2) ---- */
function cotVincOpen(){ const c=document.getElementById('cotVincClosed'),p=document.getElementById('cotVincPick'); if(c)c.style.display='none'; if(p)p.style.display='block'; const o=document.getElementById('cotVincO'); if(o&&o.value){ cotVincObra(); } const i=document.getElementById('cotVincBusca'); if(i&&!i.disabled)i.focus(); }
function cotVincCancel(){ const c=document.getElementById('cotVincClosed'),p=document.getElementById('cotVincPick'),s=document.getElementById('cotVincSug'); if(p)p.style.display='none'; if(c)c.style.display='block'; if(s)s.innerHTML=''; }
async function cotVincObra(){
  const oid=(document.getElementById('cotVincO')||{}).value||'', inp=document.getElementById('cotVincBusca'), s=document.getElementById('cotVincSug');
  COT.novoVincItens=[]; COT.novoVincObra=oid; if(s)s.innerHTML='';
  if(inp){ inp.disabled=!oid; inp.value=''; if(oid)inp.focus(); }
  if(!oid)return;
  try{ const url='actions/matriz.php'+(String(oid)!=='1'?('?obra='+oid+'&'):'?')+'_='+Date.now(); const d=await (await fetch(url)).json(); COT.novoVincItens=(d.itens||[]); }catch(e){ COT.novoVincItens=[]; }
}
let _cotVT;
function cotVincBuscaInput(){
  clearTimeout(_cotVT);
  _cotVT=setTimeout(()=>{
    const raw=(document.getElementById('cotVincBusca')||{}).value||'', q=opNorm(raw), s=document.getElementById('cotVincSug'); if(!s)return;
    if(q.length<2){ s.innerHTML=''; return; }
    const its=(COT.novoVincItens||[]).filter(it=>opNorm(it.nome||'').includes(q)).slice(0,12);
    s.innerHTML=its.length?`<div style="background:#fff;border:1px solid var(--line);border-radius:8px;box-shadow:0 6px 20px rgba(0,0,0,.12);max-height:260px;overflow:auto;margin-top:4px">`+its.map(it=>`<div onclick="cotVincPick(${it.ordem})" style="padding:7px 10px;cursor:pointer;font-size:12.5px;border-bottom:1px solid #f1f3f2" onmouseover="this.style.background='#eff7f1'" onmouseout="this.style.background=''"><b>${esc(it.nome)}</b> <span class="muted" style="font-size:10.5px">· ${esc(it.grupo||'')}${it.cotacao?' · <span style="color:var(--cot);font-weight:700">já tem mapa</span>':''}</span></div>`).join('')+`</div>`:`<div class="dmini" style="padding:6px">nenhum item casa "${esc(raw)}"</div>`;
  },160);
}
function cotVincPick(ordem){
  const it=(COT.novoVincItens||[]).find(x=>Number(x.ordem)===Number(ordem)); if(!it)return;
  COT.novoServico=it.ordem; COT.novoServicoNome=it.nome; COT.novoVincItem=it;
  const set=(id,v,onlyIfEmpty)=>{ const e=document.getElementById(id); if(e&&(!onlyIfEmpty||!(''+e.value).trim())&&v) e.value=v; };
  set('cotT',it.nome,true);
  const O=document.getElementById('cotO'); if(O&&COT.novoVincObra)O.value=COT.novoVincObra;
  set('cotC',it.grupo,true);
  set('cotD',it.escopo,true);                                                  // ESCOPO do dicionário → Descrição (carta)
  set('cotEq',cotEqTexto(it),true);                                            // VARIÁVEIS A COTAR → pontos de equalização
  set('cotV',(it.verba&&it.verba>0)?fmtMoney(it.verba):'',true);               // VERBA do vínculo do orçamento (mascarada)
  const vo=cotVerbaOrigem(it); COT.novoPre=Object.assign(COT.novoPre||{},{verba_origem:vo});
  const vc=document.getElementById('cotVerbaChip'); if(vc) vc.innerHTML=cotVerbaChip(vo);
  const box=document.getElementById('cotVincBox');
  if(box) box.outerHTML=`<div id="cotVincBox" class="dmini" style="margin:-4px 0 12px;color:var(--verde-d)"><span class="material-icons" style="font-size:13px;vertical-align:-3px">link</span> Vinculada ao radar: <b>${esc(it.nome)}</b> — puxei quantitativo, escopo e pontos de equalização (edite à vontade). <a onclick="cotVincClear()" style="cursor:pointer;text-decoration:underline">remover</a></div>`;
  const chip=document.getElementById('cotVincChip'); if(chip) chip.innerHTML=`<span class="dchip" style="background:#eef4f0;color:var(--verde-d)"><span class="material-icons" style="font-size:12px;vertical-align:-2px">link</span> vinculada: ${esc(it.nome)}</span>`;
  cotVincApply(it);
  toast('Vinculado ao item do radar: '+it.nome);
}
// VARIÁVEIS A COTAR (texto do dicionário) → pontos de equalização, 1 por linha.
// Separa por "|" (padrão Trinity); se vier em UM bloco, cai pra ";" (padrão de algumas obras, ex. DIAMOND).
function cotEqTexto(it){ let raw=(it&&it.variaveis_cotar||'').trim(); if(!raw)return ''; let p=raw.split('|'); if(p.length<2)p=raw.split(';'); return p.map(s=>s.trim()).filter(Boolean).join('\n'); }
// Procedência da verba herdada do item do radar (p/ o botão de info no mapa)
function cotVerbaOrigem(it){ if(!it)return ''; if(it.curado_verba)return 'curada'; if(it.auto&&it.auto.verba)return 'auto'; if(it.verba)return 'definida'; return ''; }
function cotVerbaChip(origem){ const m={curada:['var(--ok)','verified','curada (confirmada)'],auto:['var(--dourado)','smart_toy','sugerida pelo auto-vínculo'],definida:['var(--muted)','check','definida no item']}; const x=m[origem]; return x?`<span class="dchip" title="Verba ${esc(x[2])}" style="background:${x[0]};font-size:10px"><span class="material-icons" style="font-size:11px;vertical-align:-2px">${x[1]}</span> ${esc(x[2])}</span>`:''; }
function cotVerbaInfoBtn(c){ const o=c&&c.verba_origem; if(!o)return ''; const m={curada:['var(--ok)','verified','Verba CURADA — confirmada manualmente no item do radar.'],auto:['var(--dourado)','smart_toy','Verba SUGERIDA pelo auto-vínculo (receita) — confira e cure no item.'],definida:['var(--muted)','info','Verba definida no item do radar (vínculo do orçamento).']}; const x=m[o]||m.definida; return `<span class="material-icons" title="${esc(x[2])}" style="font-size:14px;vertical-align:-2px;color:${x[0]};cursor:help">${x[1]}</span>`; }
// aceita "463664", "463.664", "463.664,00" (formato BR) → número
function cotParseNum(v){ v=String(v==null?'':v).replace(/\s/g,'').replace(/R\$/gi,''); v=v.replace(/\.(?=\d{3}(\D|$))/g,''); v=v.replace(',', '.'); v=v.replace(/[^0-9.]/g,''); return Number(v)||0; }
async function cotBuscaItem(obra,sid){ try{ const url='actions/matriz.php'+(String(obra||1)!=='1'?('?obra='+obra+'&'):'?')+'_='+Date.now(); const d=await (await fetch(url)).json(); return (d.itens||[]).find(x=>Number(x.ordem)===Number(sid))||null; }catch(e){ return null; } }
async function cotVerbaEditar(){
  const c=COT.cur.cotacao, podePux=!!c.servico_id;
  const v=prompt('Verba prevista (R$):'+(podePux?'\n\n(deixe VAZIO e clique OK para PUXAR a verba do item do radar vinculado)':''), c.verba!=null?c.verba:'');
  if(v===null) return;
  let verba, origem;
  if(String(v).trim()==='' && podePux){
    const it=await cotBuscaItem(c.obra_id||1, c.servico_id);
    if(!it || !(it.verba>0)){ toast('O item vinculado não tem verba definida'); return; }
    verba=it.verba; origem=cotVerbaOrigem(it)||'definida';
  } else {
    verba=cotParseNum(v); origem='manual';
  }
  try{ const r=await (await fetch('actions/cotacoes.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({acao:'verba_salvar',me:EU&&EU.bitrix_id,cotacao_id:c.id,verba:verba,verba_origem:origem})})).json();
    if(r.error){toast(r.error);return;} c.verba=verba; c.verba_origem=origem; cotRenderDetalhe(); toast('Verba atualizada: '+BRL(verba));
  }catch(e){ toast('Falha: '+e.message); }
}
// preenche os "itens a cotar" — PREFERE o quantitativo real da obra; senão o dicionário do serviço
async function cotVincApply(it){
  const vazio=!COT.novoItens||COT.novoItens.every(x=>!(x.descricao||'').trim()); if(!vazio)return;
  const q=await cotItensFromQuant(it, COT.novoVincObra);
  if(q.length){ COT.novoItens=q; cotRenderItens(); toast(q.length+' item(ns) do quantitativo da obra'); return; }
  try{ const dic=await (await fetch('actions/cotacoes.php?dicionario='+it.ordem+'&_='+Date.now())).json();
    if(dic&&dic.itens&&dic.itens.length){ COT.novoItens=dic.itens.map(i=>({descricao:i.descricao,unidade:i.unidade||'',quantidade:'',observacao:i.nota||''})); cotRenderItens(); toast(dic.itens.length+' item(ns) do dicionário do serviço'); }
  }catch(e){}
}
// Deriva os itens a cotar do QUANTITATIVO do item (mesma precedência da aba Quantitativo):
// quant_comp_sel → composicao_sel(q) → quantitativo_refs(orçamento) → manual.
async function cotItensFromQuant(it, obra){
  if(!it) return [];
  const N=x=>{ const n=Number(x); return isFinite(n)?n:0; };
  const Q=x=>{ const n=N(x); return n?Math.round(n*100)/100:''; };
  const qc=(it.quant_comp_sel||[]);
  if(qc.length) return qc.map(s=>({descricao:s.desc||s.compdesc||'',unidade:s.unidade||'',quantidade:Q(N(s.area)*N(s.coef)),observacao:(s.compdesc&&s.compdesc!==s.desc)?s.compdesc:''})).filter(x=>x.descricao);
  const cs=(it.composicao_sel||[]).filter(s=>s.q);
  if(cs.length) return cs.map(s=>({descricao:s.desc||'',unidade:s.unidade||'',quantidade:Q(N(s.area)*N(s.coef)),observacao:(s.compdesc&&s.compdesc!==s.desc)?s.compdesc:''})).filter(x=>x.descricao);
  const refs=it.quantitativo_refs||[];
  if(refs.length){
    const ob=obra||it.obra_id||1;   // obra CERTA p/ resolver os ids do orçamento (ids são PK global; orcamento.php filtra por obra)
    try{ const d=await (await fetch('actions/orcamento.php?obra='+ob+'&ids='+refs.join(',')+'&_='+Date.now())).json();
      const L=d.linhas||[];
      if(L.length) return L.map(l=>({descricao:l.descricao||'',unidade:l.unidade||'',quantidade:(l.qtde!=null&&l.qtde!=='')?Number(l.qtde):'',observacao:l.path_str||''})).filter(x=>x.descricao);
    }catch(e){}
  }
  if(it.quantitativo!=null&&it.quantitativo!=='') return [{descricao:it.nome||'',unidade:it.quantitativo_unidade||'',quantidade:Number(it.quantitativo)||'',observacao:''}];
  return [];
}
function cotVincClear(){
  const g=id=>{const e=document.getElementById(id);return e?e.value:'';};
  COT.novoPre=Object.assign(COT.novoPre||{},{titulo:g('cotT'),obra:g('cotO'),categoria:g('cotC')});
  COT.novoServico=null; COT.novoServicoNome=''; cotRenderNovo();
}
function cotImportarTexto(){
  const t=prompt('Cole os itens, um por linha.\nFormato: descrição ; unidade ; quantidade'); if(!t)return;
  t.split('\n').map(l=>l.trim()).filter(Boolean).forEach(l=>{ const p=l.split(/[;\t]/).map(x=>x.trim()); COT.novoItens.push({descricao:p[0]||'',unidade:p[1]||'',quantidade:p[2]||'',observacao:''}); });
  COT.novoItens=COT.novoItens.filter(it=>(it.descricao||'').trim()); if(!COT.novoItens.length)COT.novoItens=[{descricao:'',unidade:'',quantidade:'',observacao:''}]; cotRenderItens();
}
/* ITEM A: IA lê um orçamento (PDF/Excel/imagem) e cria os itens a cotar (rascunho — confira antes de salvar) */
async function cotImportarItensIA(input){ const f=input.files&&input.files[0]; if(!f){return;} input.value='';
  if(f.size>25*1024*1024){toast('Máximo 25 MB');return;}
  toast('🧠 lendo o orçamento…');
  const fd=new FormData(); fd.append('arquivo',f); fd.append('acao','extrair_itens'); fd.append('me',(EU&&EU.bitrix_id)||'');
  try{ const r=await (await fetch('actions/cotacao_ia.php',{method:'POST',body:fd})).json();
    if(r.error){toast(r.error);return;}
    const its=(r.itens||[]).filter(x=>x&&(x.descricao||'').trim());
    if(!its.length){toast('A IA não encontrou itens nesse arquivo');return;}
    COT.novoItens=(COT.novoItens||[]).filter(x=>(x.descricao||'').trim());   // tira a linha vazia inicial
    its.forEach(x=>COT.novoItens.push({descricao:x.descricao||'',unidade:x.unidade||'',quantidade:(x.quantidade!=null&&x.quantidade!=='')?x.quantidade:'',observacao:x.observacao||''}));
    if(!COT.novoItens.length)COT.novoItens=[{descricao:'',unidade:'',quantidade:'',observacao:''}];
    cotRenderItens(); toast(its.length+' item(ns) importado(s) pela IA — confira antes de salvar');
  }catch(e){toast('Falha: '+e.message);} }
async function cotCriar(){
  const titulo=val('cotT').trim(); if(!titulo){toast('Dê um título à cotação');return;}
  const itens=COT.novoItens.filter(it=>(it.descricao||'').trim()); if(!itens.length){toast('Inclua ao menos um item');return;}
  const body={acao:'criar',me:EU&&EU.bitrix_id,obra_ficha_id:Number(val('cotO'))||null,servico_id:COT.novoServico||null,titulo,categoria:val('cotC'),tipo_servico:val('cotTipo'),verba:parseBRLInput(val('cotV'))||0,verba_origem:(COT.novoPre&&COT.novoPre.verba_origem)||'',num_solicitacao:val('cotSC'),descricao:val('cotD'),equalizacao:val('cotEq'),itens,convidados:COT.novoConvidados||[]};
  try{ const r=await (await fetch('actions/cotacoes.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(body)})).json();
    if(r.error){toast(r.error);return;} toast('Cotação criada'); cotOpen(r.id);
  }catch(e){toast('Falha: '+e.message);}
}
async function cotOpen(id){
  const w=document.getElementById('cotwrap'); w.innerHTML='<div class="dempty">Abrindo mapa…</div>';
  try{ const d=await (await fetch('actions/cotacoes.php?id='+id+'&_='+Date.now())).json();
    if(d.error){w.innerHTML='<div class="dempty">'+esc(d.error)+'</div>';return;}
    COT.cur=d; COT.mode='detalhe'; COT.editItens=false; COT.eqEdit=false; cotRenderDetalhe();
  }catch(e){w.innerHTML='<div class="dempty">Falha: '+esc(e.message)+'</div>';}
}
function cotNum(x){ return x!=null&&x!==''?Number(x).toLocaleString('pt-BR'):''; }
// --- Itens a cotar: exibição (com observação = complemento) + edição (add/editar/excluir) ---
function cotItensPanel(d){
  const c=d.cotacao, itens=d.itens||[], podeGerir=!!(IS_ADMIN||CAN_EDIT||(c.criado_por&&EU&&String(c.criado_por)===String(EU.bitrix_id)));
  if(COT.editItens){
    return `<div class="panel" style="margin-bottom:10px"><div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:6px"><b style="font-size:13px">Itens a cotar — editando</b>
        <span><button class="btn-prim" style="padding:4px 11px" onclick="cotItensSalvar()">Salvar itens</button> <button class="btn-ghost" style="padding:4px 11px" onclick="COT.editItens=false;cotRenderDetalhe()">Cancelar</button></span></div>
      <div class="dmini" style="margin:4px 0 8px">Descrição = o item. Complemento = a observação/histórico (detalhe do item).</div>
      <div id="cotItEd"></div>
      <button class="btn-ghost" style="margin-top:6px" onclick="cotItAdd()"><span class="material-icons" style="font-size:15px;vertical-align:-3px">add</span> Adicionar item</button></div>`;
  }
  const rows=itens.map(it=>`<tr><td style="text-align:left"><b>${esc(it.descricao)}</b>${it.observacao?`<div class="muted" style="font-size:11px;margin-top:1px">${esc(it.observacao)}</div>`:''}</td><td style="text-align:right;white-space:nowrap">${cotNum(it.quantidade)} ${esc(it.unidade||'')}</td></tr>`).join('');
  return `<div class="panel" style="margin-bottom:10px">${cotSecHead('list_alt','Itens a cotar','('+itens.length+')',podeGerir?`<button class="btn-ghost" style="padding:4px 11px" onclick="cotEditItens()"><span class="material-icons" style="font-size:14px;vertical-align:-3px">edit</span> Editar itens</button>`:'')}
    ${itens.length?`<div class="wrap"><table><thead><tr><th>Item</th><th style="text-align:right;width:110px">Qtde</th></tr></thead><tbody>${rows}</tbody></table></div>`:'<div class="dmini">Nenhum item. Clique em “Editar itens” para adicionar.</div>'}</div>`;
}
function cotEditItens(){ COT.itensEdit=(COT.cur.itens||[]).map(it=>({id:it.id,descricao:it.descricao||'',unidade:it.unidade||'',quantidade:it.quantidade!=null?it.quantidade:'',observacao:it.observacao||''})); if(!COT.itensEdit.length) COT.itensEdit=[{descricao:'',unidade:'',quantidade:'',observacao:''}]; COT.editItens=true; cotRenderDetalhe(); cotItRenderEd(); }
function cotItRenderEd(){ const box=document.getElementById('cotItEd'); if(!box)return;
  box.innerHTML=COT.itensEdit.map((it,i)=>`<div style="display:grid;grid-template-columns:minmax(0,2fr) 56px 74px minmax(0,2fr) 30px;gap:6px;align-items:center;margin-bottom:6px">
    <input placeholder="Descrição do item" value="${esc(it.descricao)}" oninput="COT.itensEdit[${i}].descricao=this.value" style="font-size:12px">
    <input placeholder="un" value="${esc(it.unidade)}" oninput="COT.itensEdit[${i}].unidade=this.value" style="font-size:12px">
    <input placeholder="qtd" value="${esc(it.quantidade)}" oninput="COT.itensEdit[${i}].quantidade=this.value" style="font-size:12px;text-align:right">
    <input placeholder="Complemento (observação / histórico)" value="${esc(it.observacao)}" oninput="COT.itensEdit[${i}].observacao=this.value" style="font-size:12px">
    <button class="btn-ghost" style="padding:2px 6px;color:var(--pend)" onclick="COT.itensEdit.splice(${i},1);cotItRenderEd()" title="remover">×</button></div>`).join('')||'<div class="dmini">Sem itens.</div>';
}
function cotItAdd(){ COT.itensEdit.push({descricao:'',unidade:'',quantidade:'',observacao:''}); cotItRenderEd(); }
async function cotItensSalvar(){ const itens=COT.itensEdit.filter(it=>(it.descricao||'').trim()).map(it=>({id:it.id,descricao:it.descricao,unidade:it.unidade,quantidade:(it.quantidade===''||it.quantidade==null)?'':Number(it.quantidade),observacao:it.observacao}));
  try{ const r=await (await fetch('actions/cotacoes.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({acao:'itens_salvar',me:EU&&EU.bitrix_id,cotacao_id:COT.cur.cotacao.id,itens})})).json();
    if(r&&r.error){toast(r.error);return;} COT.editItens=false; toast('Itens salvos'); cotOpen(COT.cur.cotacao.id); }catch(e){toast('Falha: '+e.message);} }
async function cotExcluir(){ const c=COT.cur.cotacao; if(!confirm('Excluir a cotação "'+(c.titulo||'')+'"?\nIsso apaga o mapa, propostas, convidados e itens. Não dá pra desfazer.'))return;
  try{ const r=await (await fetch('actions/cotacoes.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({acao:'excluir',me:EU&&EU.bitrix_id,cotacao_id:c.id})})).json();
    if(r&&r.error){toast(r.error);return;} toast('Cotação excluída'); COT.mode='list'; cotLoad(); }catch(e){toast('Falha: '+e.message);} }
/* Cabeçalho de SEÇÃO — padrão visual do sistema: ícone + título fonte maior + subtítulo + ações à direita */
function cotSecHead(icon,title,sub,actions){ return `<div style="display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap;margin-bottom:11px">
  <div style="display:flex;align-items:center;gap:8px;min-width:0"><span class="material-icons" style="font-size:20px;color:var(--verde)">${icon}</span><b style="font-size:15.5px;letter-spacing:.2px">${title}</b>${sub?`<span class="muted" style="font-size:11.5px;font-weight:400">${sub}</span>`:''}</div>
  ${actions?`<div style="display:flex;gap:6px;align-items:center;flex-wrap:wrap">${actions}</div>`:''}</div>`; }
/* Seções recolhíveis (o Murilo tem ~40 fornecedores) — estado por-cotação em localStorage; recolhido mostra só um resuminho */
function cotColapsado(key){ try{ return localStorage.getItem('cotcol_'+key)==='1'; }catch(e){ return false; } }
function cotToggleSec(key){ try{ localStorage.setItem('cotcol_'+key, cotColapsado(key)?'0':'1'); }catch(e){} cotRenderDetalhe(); }
function cotChevron(key){ const col=cotColapsado(key); return `<span class="material-icons" style="font-size:20px;cursor:pointer;color:var(--muted)" onclick="cotToggleSec('${key}')" title="${col?'expandir':'recolher'}">${col?'unfold_more':'unfold_less'}</span>`; }
// popup da OBSERVAÇÃO de um item×fornecedor (o "quadro cinza" do mapa antigo)
function cotObsShow(el){ const obs=el.getAttribute('data-obs')||'', forn=el.getAttribute('data-forn')||'', item=el.getAttribute('data-item')||'';
  let ov=document.getElementById('obsOverlay'); if(!ov){ov=document.createElement('div');ov.id='obsOverlay';ov.style.cssText='position:fixed;inset:0;background:rgba(15,25,20,.42);z-index:9999;display:flex;align-items:center;justify-content:center;padding:20px';document.body.appendChild(ov);} ov.onclick=()=>ov.remove();
  ov.innerHTML=`<div style="background:#fff;border-radius:12px;padding:16px 18px;max-width:520px;width:100%;box-shadow:0 12px 44px rgba(0,0,0,.22)" onclick="event.stopPropagation()">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px"><b style="font-size:14px">${esc(forn)}</b><span class="material-icons" onclick="document.getElementById('obsOverlay').remove()" style="cursor:pointer;color:var(--muted)">close</span></div>
    <div class="muted" style="font-size:11.5px;margin-bottom:8px">${esc(item)}</div>
    <div style="background:#f4f7f5;border:1px solid var(--line);border-radius:8px;padding:11px 13px;font-size:13px;white-space:pre-wrap;line-height:1.5">${esc(obs)}</div></div>`; }
function cotRenderDetalhe(){
  const d=COT.cur,c=d.cotacao,itens=d.itens||[],props=d.propostas||[],m=d.mapa||{},best=m.melhor_por_item||{},w=document.getElementById('cotwrap');
  const podeGerir=!!(IS_ADMIN||CAN_EDIT||(c.criado_por&&EU&&String(c.criado_por)===String(EU.bitrix_id)));   // admin, edita a obra, ou criador
  let html=`<div class="panel" style="margin-bottom:10px"><div style="display:flex;align-items:flex-start;gap:10px;flex-wrap:wrap">
      <button class="btn-ghost" onclick="cotLoad()" style="margin-top:2px"><span class="material-icons" style="font-size:16px;vertical-align:-3px">arrow_back</span> Voltar</button>
      <div style="min-width:0"><div style="font-size:10px;font-weight:700;letter-spacing:.7px;text-transform:uppercase;color:var(--muted)">Descrição da cotação</div>
        <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin-top:2px"><b style="font-size:18px">${esc(c.titulo)}</b> ${cotStChip(c.status)}<span class="muted" style="font-size:12px">${esc(c.obra_nome||'sem obra')}${c.categoria?' · '+esc(c.categoria):''}${c.tipo_servico?' · '+esc(c.tipo_servico):''}</span></div></div>
      <span style="margin-left:auto;display:flex;gap:6px;flex-wrap:wrap">
        ${CAN_EDIT?`<button class="btn-ghost" style="padding:6px 12px" onclick="cartaGerar(${c.id})" title="${(c.num_solicitacao&&!c.servico_id)?'Carta de cotação (material) desta cotação':'Carta convite desta cotação'} — PDF / Word"><span class="material-icons" style="font-size:15px;vertical-align:-3px">mail</span> ${(d.cartas_geradas&&d.cartas_geradas.length)?'Ver/editar carta':'Gerar carta'}</button>${(d.cartas_geradas&&d.cartas_geradas.length)?`<span class="dchip" style="background:#eef4f0;color:var(--verde-d);font-size:10px" title="carta salva em ${D(String(d.cartas_geradas[0].created_at).slice(0,10))}"><span class="material-icons" style="font-size:11px;vertical-align:-2px">description</span> carta salva</span>`:''}`:''}
        <button class="btn-ghost" style="padding:6px 12px" onclick="cotUmaPagina()" title="Resumo do mapa em uma página, pronto pra imprimir/PDF"><span class="material-icons" style="font-size:15px;vertical-align:-3px">description</span> Mapa em uma página</button>
        ${CAN_EDIT?`<button class="btn-ghost" style="padding:6px 12px" onclick="cotEmailAbrir(${c.id})" title="montar o e-mail de cotação para os fornecedores convidados"><span class="material-icons" style="font-size:15px;vertical-align:-3px">mail</span> E-mail</button>`:''}
        ${CAN_EDIT?`<button class="btn-ghost" style="padding:6px 12px;color:var(--verde-d)" onclick="cotPropIAAbrir()" title="a IA lê um PDF/print de proposta, identifica o fornecedor e preenche os preços"><span class="material-icons" style="font-size:15px;vertical-align:-3px">auto_awesome</span> Proposta via IA</button>`:''}
        ${CAN_EDIT?`<button class="btn-prim" style="padding:6px 12px" onclick="cotProposta()"><span class="material-icons" style="font-size:15px;vertical-align:-3px">add</span> Cadastrar proposta</button>`:''}
        ${CAN_EDIT?`<button class="btn-ghost" style="padding:6px 12px" onclick="cotFinalizar()">${c.status==='finalizada'?'Reabrir':'Finalizar'}</button>`:''}
        ${podeGerir?`<button class="btn-ghost" style="padding:6px 12px;color:var(--pend)" onclick="cotExcluir()" title="Excluir esta cotação (admin ou quem criou)"><span class="material-icons" style="font-size:15px;vertical-align:-3px">delete</span> Excluir</button>`:''}
      </span></div>
    <div class="kpis" style="padding:12px 0 0">
      <div class="kpi kpi-fill"><div class="v">${props.length}/${(d.convidados||[]).length}</div><div class="l">propostas recebidas</div></div>
      <div class="kpi kpi-fill"><div class="v">${m.melhor_oferta?BRL(m.melhor_oferta):'—'}</div><div class="l">melhor fornecedor único${m.fornecedor_destaque?' · '+esc(m.fornecedor_destaque):''}</div></div>
      <div class="kpi kpi-fill"><div class="v">${m.melhor_total?BRL(m.melhor_total):'—'}</div><div class="l">melhor compra (menor por item)</div></div>
      <div class="kpi"><div class="v">${c.verba?BRL(c.verba):'—'} ${cotVerbaInfoBtn(c)} ${CAN_EDIT?`<span class="material-icons" onclick="cotVerbaEditar()" title="editar / puxar a verba" style="font-size:14px;cursor:pointer;color:var(--muted);vertical-align:-2px">edit</span>`:''}</div><div class="l">verba prevista${c.verba_origem?' · '+esc({curada:'curada ✓',auto:'auto 🤖',definida:'definida'}[c.verba_origem]||c.verba_origem):''}</div></div>
    </div>
    <div style="display:flex;gap:16px;align-items:center;flex-wrap:wrap;margin-top:10px;padding-top:9px;border-top:1px solid var(--line)">
      <div style="display:flex;align-items:center;gap:6px"><span class="muted" style="font-size:11px;font-weight:700">Nº Solicitação</span><input id="cotDetSC" value="${esc(c.num_solicitacao||'')}" placeholder="—" style="width:120px;padding:3px 7px;font-size:12px" ${CAN_EDIT?'':'disabled'}></div>
      <div style="display:flex;align-items:center;gap:6px"><span class="muted" style="font-size:11px;font-weight:700">Nº Pedido de compra</span><input id="cotDetPC" value="${esc(c.num_pedido||'')}" placeholder="${!c.servico_id?'obrigatório p/ finalizar':'—'}" style="width:150px;padding:3px 7px;font-size:12px" ${CAN_EDIT?'':'disabled'}></div>
      ${CAN_EDIT?`<button class="btn-ghost" style="padding:4px 11px" onclick="cotNumerosSalvar()"><span class="material-icons" style="font-size:14px;vertical-align:-3px">save</span> Salvar nºs</button>`:''}
      ${c.num_pedido?`<button class="btn-ghost" style="padding:4px 11px;color:var(--verde-d)" onclick="cotPedidoVer('${esc(String(c.num_pedido)).replace(/'/g,'')}')" title="ver o pedido no TOTVS: fornecedor, itens, preços e total"><span class="material-icons" style="font-size:14px;vertical-align:-3px">receipt_long</span> Ver pedido</button>`:''}
      ${!c.servico_id?`<span class="dchip" style="background:#8a9299;font-size:10px" title="cotação criada do zero, sem vínculo ao radar de aquisições">avulsa</span>`:'<span class="dchip" style="background:#eef4f0;color:var(--verde-d);font-size:10px" title="cotação vinculada a um item do radar">do radar</span>'}
    </div><div id="cotPedDetect" style="margin-top:8px"></div></div>`;
  html+=cotItensPanel(d);
  // ---- Concorrência (fornecedores convidados) + anexos POR fornecedor (anexar antes de cadastrar proposta) ----
  const conv=d.convidados||[], anx=d.anexos||[], meB=(EU&&EU.bitrix_id)||'';
  const anxNorm=s=>String(s||'').trim().toLowerCase().replace(/\s+/g,' ');
  const anexosDoForn=cf=>anx.filter(a=>((a.fornecedor_id&&cf.fornecedor_id&&String(a.fornecedor_id)===String(cf.fornecedor_id))||(a.fornecedor_nome&&anxNorm(a.fornecedor_nome)===anxNorm(cf.fornecedor_nome))));
  const anexoChip=a=>`<span class="dchip" style="background:#eef4f0;color:var(--verde-d);font-weight:600;display:inline-flex;align-items:center;gap:4px;max-width:190px"><span class="material-icons" style="font-size:13px">${a.url?'link':cotAnexoIcon(a.mime,a.nome)}</span><a href="${a.url?esc(a.url):('actions/cotacao_anexo.php?download='+a.id+'&me='+encodeURIComponent(meB))}" target="_blank" rel="noopener" style="color:var(--verde-d);text-decoration:none;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="${a.url?'abrir PDF (link do sistema antigo)':esc(a.nome)}">${esc(a.nome)}</a>${CAN_EDIT?` <span onclick="cotDelAnexo(${a.id})" style="cursor:pointer;color:var(--pend)" title="excluir anexo">×</span>`:''}</span>`;
  const respN=conv.filter(x=>x.respondeu||x.inbound_em).length, enviadoN=conv.filter(x=>x.enviado_em).length, concCol=cotColapsado('conc');
  html+=`<div class="panel" style="margin-bottom:10px">${cotSecHead('groups','Concorrência','fornecedores convidados',(CAN_EDIT?'<button class="btn-ghost" style="padding:3px 10px" onclick="cotInboxBuscar()" title="ler as respostas dos fornecedores na caixa suprimentos@ (IMAP)"><span class="material-icons" style="font-size:14px;vertical-align:-3px">mark_email_unread</span> Buscar respostas</button> ':'')+'<span class="dchip" style="background:'+(conv.length&&conv.every(x=>x.respondeu)?'var(--ok)':'var(--dourado)')+'">'+conv.filter(x=>x.respondeu).length+' de '+conv.length+' responderam</span> '+cotChevron('conc'))}`;
  if(concCol){ html+=`<div style="font-size:12.5px;color:var(--muted)"><b>${conv.length}</b> fornecedor(es) · <b style="color:var(--verde-d)">${respN}</b> responderam · ${enviadoN} com e-mail enviado${(conv.length-respN)>0?` · ${conv.length-respN} aguardando`:''}</div></div>`; }
  else { html+=(conv.length?'':'');
  if(conv.length) html+='<div style="margin-top:10px;display:flex;flex-direction:column;gap:8px">'+conv.map((cf,ci)=>{ const ax=anexosDoForn(cf);
    return `<div style="border:1px solid var(--line);border-radius:10px;padding:9px 11px">
      <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap">
        <span class="dgm" style="background:${cf.respondeu?'var(--ok)':'#cfd6da'}"></span>
        <span style="flex:1;min-width:130px;font-weight:600">${esc(cf.fornecedor_nome)}${cf.categoria?` <span class="muted" style="font-size:11px;font-weight:400">· ${esc(cf.categoria)}</span>`:''}</span>
        ${cf.enviado_em?`<span class="dchip" style="background:var(--verde-d);color:#fff" title="e-mail enviado em ${D(String(cf.enviado_em).slice(0,10))}"><span class="material-icons" style="font-size:11px;vertical-align:-2px">outbox</span> enviado</span>`:''}
        <span class="dchip" style="background:${cf.respondeu?'var(--ok)':'#8a9299'}">${cf.respondeu?('respondeu · '+BRL(cf.proposta_total)):'aguardando'}</span>
        ${cf.inbound_em?`<span class="dchip" style="background:${cf.inbound_tipo==='cotacao'?'#1f7a44':(cf.inbound_tipo==='duvida'?'var(--pend)':'#5b6b7a')};color:#fff" title="${esc(cf.inbound_resumo||'')}"><span class="material-icons" style="font-size:11px;vertical-align:-2px">mail</span> e-mail · ${cf.inbound_tipo==='cotacao'?'cotação':(cf.inbound_tipo==='duvida'?'dúvida':'resposta')}</span>`:''}
        ${CAN_EDIT?`<button class="btn-ghost" style="padding:2px 9px" onclick="cotAnexarAbrir(${cf.fornecedor_id||'null'},'${esc(String(cf.fornecedor_nome||'')).replace(/'/g,'')}')" title="anexar PDF, Excel ou print — arraste, cole (Ctrl+V) ou clique"><span class="material-icons" style="font-size:14px;vertical-align:-2px">attach_file</span> anexar${ax.length?` (${ax.length})`:''}</button>`:''}
        ${CAN_EDIT&&ax.length?`<button class="btn-ghost" style="padding:2px 9px;color:var(--verde-d)" onclick="cotIAPreencher(${cf.fornecedor_id||'null'},'${esc(String(cf.fornecedor_nome||'')).replace(/'/g,'')}')" title="a IA lê os anexos e preenche a proposta (rascunho para você conferir)"><span class="material-icons" style="font-size:14px;vertical-align:-2px">auto_awesome</span> preencher com IA</button>`:''}
        ${CAN_EDIT&&!cf.respondeu?`<button class="btn-ghost" style="padding:2px 9px" onclick="cotPropostaDe(${ci})">Lançar proposta</button>`:''}
        ${CAN_EDIT&&cf.respondeu&&cf.proposta_id?`<button class="btn-ghost" style="padding:2px 9px" onclick="cotProposta(${cf.proposta_id})" title="editar a proposta"><span class="material-icons" style="font-size:13px;vertical-align:-2px">edit</span> editar</button><button class="btn-ghost" style="padding:2px 8px;color:var(--pend)" onclick="cotExcluirProposta(${cf.proposta_id})" title="excluir a proposta">excluir</button>`:''}
        ${CAN_EDIT?`<button class="btn-ghost" style="padding:2px 6px;color:var(--pend)" onclick="cotDesconvidar(${cf.id})" title="tirar da concorrência">×</button>`:''}
      </div>
      ${cotConvContatos(cf)}
      ${ax.length?`<div style="display:flex;flex-wrap:wrap;gap:6px;margin-top:7px;padding-left:16px">${ax.map(anexoChip).join('')}</div>`:''}
    </div>`; }).join('')+'</div>';
  else html+='<div class="dmini" style="margin-top:6px">Nenhum fornecedor convidado ainda — convide abaixo.</div>';
  if(CAN_EDIT) html+=`<div style="margin-top:10px"><button class="btn-ghost" style="padding:5px 12px" onclick="cotFornPickerOpen('convite')"><span class="material-icons" style="font-size:15px;vertical-align:-3px;color:var(--verde)">group_add</span> Convidar fornecedores</button></div>`;
  html+='</div>'; }
  html+='<div id="cotInboxPanel"></div>';   // Fase 4: respostas recebidas por e-mail (preenchido async por cotInboxLoad)
  html+=cotEqualizaPanel(d);
  if(!props.length){ html+='<div class="panel"><div class="empty">Nenhuma proposta ainda. Clique em "Cadastrar proposta" ou "Lançar proposta" de um convidado para montar o mapa.</div></div>'; }
  else{
    html+='<div class="panel">'+cotSecHead('table_view','Mapa de cotações','comparativo · melhor preço por item','<button class="btn-ghost" style="padding:4px 10px" onclick="cotUmaPagina()" title="ver este mapa em uma página"><span class="material-icons" style="font-size:14px;vertical-align:-3px">description</span> uma página</button>')+'<div style="overflow-x:auto"><table class="mtable" style="border:none"><thead><tr><th class="svc-h" style="text-align:left">Item</th>';
    props.forEach(p=>{ html+=`<th style="min-width:120px">${esc(p.fornecedor_nome)}${p.prazo?`<div class="muted" style="font-size:9.5px;font-weight:400">${esc(p.prazo)}</div>`:''}</th>`; });
    html+='<th style="min-width:140px;color:var(--verde-d)">🏆 Melhor Compra</th></tr></thead><tbody>';
    itens.forEach(it=>{ const b=best[it.id];
      html+=`<tr><td class="svc-c" style="text-align:left">${esc(it.descricao)}<small>${cotNum(it.quantidade)} ${esc(it.unidade||'')}${it.observacao?' · '+esc(it.observacao):''}</small></td>`;
      props.forEach(p=>{ const pi=(p.itens||{})[it.id]; const isB=b&&b.proposta_id===p.id;
        html+=`<td style="text-align:center;padding:6px 8px;${isB?'background:#e7f6ee':''}">${pi&&pi.preco_total!=null?`<b>${BRL(pi.preco_unit)}</b>${isB?' 🏆':''}${pi.observacao?` <span class="material-icons" title="${esc(pi.observacao)}" data-obs="${esc(pi.observacao)}" data-forn="${esc(p.fornecedor_nome)}" data-item="${esc(it.descricao)}" style="font-size:13px;color:#5c7b8a;cursor:help;vertical-align:-2px" onclick="event.stopPropagation();cotObsShow(this)">info</span>`:''}<div class="muted" style="font-size:10px">${BRL(pi.preco_total)}</div>`:'<span class="muted">—</span>'}</td>`; });
      html+=`<td style="text-align:center;padding:6px 8px;background:#eafaf0">${b?`<b>${BRL(b.preco_total)}</b><div class="muted" style="font-size:10px">${esc(b.fornecedor)}</div>`:'—'}</td></tr>`;
    });
    html+='<tr style="background:#f7faf8"><td class="svc-c" style="text-align:left;font-weight:800">TOTAL</td>';
    props.forEach(p=>{ const isBS=m.fornecedor_destaque===p.fornecedor_nome; html+=`<td style="text-align:center;font-weight:800;${isBS?'color:var(--verde-d)':''}">${p.total!=null?BRL(p.total):'—'}</td>`; });
    html+=`<td style="text-align:center;font-weight:800;background:#eafaf0;color:var(--verde-d)">${m.melhor_total?BRL(m.melhor_total):'—'}</td></tr></tbody></table></div></div>`;
  }
  w.innerHTML=html;
  if(c.num_solicitacao) cotDetectarPedido(c);   // nasceu de solicitação → detecta/autopreenche o PC pelo vínculo exato (solic_numeros)
  cotInboxLoad(c.id);                            // Fase 4: carrega as respostas de e-mail desta cotação (se houver)
}
// detecta os pedidos de compra que nasceram desta solicitação (vínculo EXATO por colidmov, que embute a coligada)
async function cotDetectarPedido(c){
  const host=document.getElementById('cotPedDetect'); if(!host||!c||!c.num_solicitacao)return;
  try{ const r=await (await fetch('actions/pedidos.php?solicitacao='+encodeURIComponent(c.num_solicitacao)+'&coligada='+encodeURIComponent(c.solic_coligada||'')+'&colidmov='+encodeURIComponent(c.solic_colidmov||'')+'&me='+encodeURIComponent((EU&&EU.bitrix_id)||''))).json();
    const peds=(r&&r.pedidos)||[];
    const salvo=(c.num_pedido&&String(c.num_pedido).trim())?String(c.num_pedido).trim():'';
    if(!peds.length){
      // sem PC vinculado (a SC ainda está em aberto). Se há um PC salvo, é provavelmente o vínculo ANTIGO errado (outra coligada) → avisa, não apaga.
      host.innerHTML = salvo ? `<span class="dchip" style="background:#fff3e0;color:#a15c00;font-weight:700" title="O nº de PC salvo não corresponde a esta solicitação/coligada. A SC provavelmente ainda está em aberto (sem pedido). Confira e, se for o caso, limpe o campo Pedido.">⚠ PC salvo (${esc(salvo)}) não confere com esta solicitação — a SC parece estar em aberto</span>` : '';
      return;
    }
    const nums=peds.map(p=>p.pedido_numero);
    // autopreenche o campo PC só se estiver vazio (agora o vínculo é seguro por colidmov)
    if(CAN_EDIT && !salvo){
      const joined=nums.join(', '); c.num_pedido=joined; const inp=document.getElementById('cotDetPC'); if(inp)inp.value=joined;
      try{ await fetch('actions/cotacoes.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({acao:'numeros_salvar',me:EU&&EU.bitrix_id,cotacao_id:c.id,num_solicitacao:c.num_solicitacao,num_pedido:joined})}); }catch(e){}
      toast(nums.length>1?(nums.length+' pedidos vinculados pela solicitação'):('Pedido '+nums[0].replace(/^0+/,'')+' vinculado pela solicitação'));
    }
    host.innerHTML='<span class="muted" style="font-size:11px;font-weight:700">Pedido(s) desta solicitação:</span> '+peds.map(p=>`<span class="dchip" style="background:#eef4f0;color:var(--verde-d);font-weight:700;cursor:pointer;margin-right:5px" onclick="cotPedidoVer('${esc(p.pedido_numero)}','${esc(p.coligada_cod||'')}')" title="${p.n_itens} item(ns)${p.coligada?' · '+esc(p.coligada):''} · ver detalhes">PC ${esc(String(p.pedido_numero).replace(/^0+/,''))}${p.status?' · '+esc(p.status):''} <span class="material-icons" style="font-size:12px;vertical-align:-2px">visibility</span></span>`).join('');
  }catch(e){}
}
/* ===== E-MAIL FASE 4 — ler respostas (inbound): buscar na caixa, listar, usar rascunho, marcar lido ===== */
async function cotInboxBuscar(){
  const c=(COT.cur||{}).cotacao; if(!c)return;
  toast('Buscando respostas na caixa…');
  try{ const r=await (await fetch('actions/inbox.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({acao:'varrer',me:EU&&EU.bitrix_id})})).json();
    if(r.error){toast(r.error);return;}
    if(r.throttled){toast(r.msg||'Verifiquei agora há pouco.'); if(c)cotOpen(c.id); return;}
    const parts=[]; if(r.novas)parts.push(r.novas+' nova(s)'); if(r.casadas)parts.push(r.casadas+' casada(s)'); if(r.cotacoes)parts.push(r.cotacoes+' cotação'); if(r.duvidas)parts.push(r.duvidas+' dúvida(s)'); if(r.sem_match)parts.push(r.sem_match+' sem vínculo');
    toast((r.lidas!=null?('Caixa: '+r.lidas+' lida(s)'):'Busca concluída')+(parts.length?' · '+parts.join(' · '):(r.novas?'':' · nada novo')));
    if(r.avisos&&r.avisos.length) setTimeout(()=>toast(r.avisos[0]),1500);
    cotOpen(c.id);   // recarrega: os cards atualizam o estado e o painel da caixa recarrega
  }catch(e){toast('Falha: '+e.message);}
}
async function cotInboxLoad(cid){
  const host=document.getElementById('cotInboxPanel'); if(!host||!cid)return;
  try{ const r=await (await fetch('actions/inbox.php?listar=1&cotacao='+cid+'&me='+encodeURIComponent((EU&&EU.bitrix_id)||''))).json();
    if((((COT.cur||{}).cotacao||{}).id)!=cid) return;   // o usuário já trocou de cotação — não sobrescreve o painel/estado da atual
    const its=(r&&r.itens)||[]; if(!its.length){ host.innerHTML=''; return; }
    COT.inbox=its; const meB=(EU&&EU.bitrix_id)||'';
    const novos=its.filter(m=>m.status==='novo').length, col=cotColapsado('inbox');
    const head=cotSecHead('inbox','Respostas por e-mail','a IA leu a caixa e classificou — valide antes de usar',(novos?`<span class="dchip" style="background:var(--pend);color:#fff">${novos} não processada(s)</span> `:'')+cotChevron('inbox'));
    if(col){ host.innerHTML=`<div class="panel" style="margin-bottom:10px">${head}<div style="font-size:12.5px;color:var(--muted)"><b>${its.length}</b> e-mail(s)${novos?` · <b style="color:var(--pend)">${novos}</b> não processada(s) — expanda p/ ver e incluir no mapa`:' · todas tratadas'}</div></div>`; return; }
    const tipoChip=t=>t==='cotacao'?'<span class="dchip" style="background:#1f7a44;color:#fff">COTAÇÃO</span>':(t==='duvida'?'<span class="dchip" style="background:var(--pend);color:#fff">DÚVIDA</span>':(t==='fora_de_escopo'?'<span class="dchip" style="background:#8a9299;color:#fff">FORA DE ESCOPO</span>':'<span class="dchip" style="background:#5b6b7a;color:#fff">'+esc(String(t||'?').toUpperCase())+'</span>'));
    // 1 e-mail = 1 card; AGRUPADOS por fornecedor (sequência), mais recente primeiro
    const rowOf=(m,i)=>{ const anx=String(m.anexos_ids||'').split(',').filter(Boolean); const done=(m.status==='lido'||m.status==='convertido'||m.status==='ignorado');
      return `<div style="${done?'opacity:.6;':''}">
        <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap">
          ${tipoChip(m.tipo)}
          <span class="muted" style="font-size:11px;flex:1;min-width:80px">${m.data_email?D(String(m.data_email).slice(0,10)):''}${m.match_metodo==='heuristica'?' · vínculo '+esc(m.match_confianca||''):''}</span>
          ${m.tem_anexo?`<span class="dchip" style="background:#eef4f0;color:var(--verde-d)"><span class="material-icons" style="font-size:11px;vertical-align:-2px">attach_file</span> ${anx.length||1}</span>`:''}
          ${done?`<span class="dchip" style="background:#8a9299;color:#fff">✓ ${esc(m.status)}</span>`:''}
        </div>
        ${m.resumo?`<div style="font-size:12.5px;margin-top:4px">${esc(m.resumo)}</div>`:''}
        <div style="display:flex;gap:6px;flex-wrap:wrap;margin-top:6px">
          ${m.tem_rascunho&&CAN_EDIT?`<button class="${done?'btn-ghost':'btn-prim'}" style="padding:3px 11px" onclick="cotInboxUsarRascunho(${i})" title="abre a proposta pré-preenchida pela IA (rascunho — confira e salve)"><span class="material-icons" style="font-size:13px;vertical-align:-2px">auto_awesome</span> ${done?'usar de novo':'Usar rascunho'}</button>`:''}
          ${anx.map(id=>`<a class="btn-ghost" style="padding:3px 9px;text-decoration:none" href="actions/cotacao_anexo.php?download=${id}&me=${encodeURIComponent(meB)}" target="_blank" rel="noopener"><span class="material-icons" style="font-size:13px;vertical-align:-2px">description</span> anexo</a>`).join('')}
          ${m.corpo_preview?`<button class="btn-ghost" style="padding:3px 9px" onclick="cotInboxVerCorpo(${i})">ver e-mail</button>`:''}
          ${!done?`<button class="btn-ghost" style="padding:3px 9px;color:var(--muted)" onclick="cotInboxMarcar(${m.id},'marcar_lido')">marcar lido</button>`:''}
        </div></div>`; };
    const groups={}, order=[];
    its.forEach((m,i)=>{ const k=(m.fornecedor_nome||m.from_email||'—'); if(!groups[k]){groups[k]=[];order.push(k);} groups[k].push({m,i}); });
    const groupsHtml=order.map(k=>{ const arr=groups[k].slice().sort((a,b)=>String(b.m.data_email||'').localeCompare(String(a.m.data_email||''))); const latest=arr[0].m, nn=arr.filter(x=>x.m.status==='novo').length;
      return `<div style="border:1px solid var(--line);border-radius:10px;background:#fff;overflow:hidden">
        <div style="display:flex;align-items:center;gap:8px;padding:8px 11px;background:#f6f9f7;border-bottom:1px solid var(--line)">
          <span class="dgm" style="background:${latest.tipo==='cotacao'?'#1f7a44':(latest.tipo==='duvida'?'var(--pend)':'#8a9299')}"></span>
          <b style="flex:1;min-width:120px">${esc(k)}</b>
          <span class="muted" style="font-size:11px">${arr.length} e-mail(s)</span>
          ${nn?`<span class="dchip" style="background:var(--pend);color:#fff">${nn} nova(s)</span>`:''}
        </div>
        <div style="display:flex;flex-direction:column;gap:8px;padding:9px 11px">${arr.map(x=>rowOf(x.m,x.i)).join('')}</div>
      </div>`; }).join('');
    host.innerHTML=`<div class="panel" style="margin-bottom:10px">${head}<div style="display:flex;flex-direction:column;gap:9px">${groupsHtml}</div>
      <div class="dmini" style="margin-top:7px">⚠ Vínculo e classificação são sugestões da IA sobre e-mails (conteúdo não confiável). Confira antes de gerar a proposta; dúvidas nunca viram rascunho.</div></div>`;
  }catch(e){ host.innerHTML=''; }
}
function cotInboxUsarRascunho(i){ const m=(COT.inbox||[])[i]; if(!m||!m.draft){toast('Sem rascunho neste e-mail');return;}
  if(m.id) fetch('actions/inbox.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({acao:'converter',me:EU&&EU.bitrix_id,id:m.id})}).catch(()=>{});   // usei o rascunho → marca tratado
  m.status='convertido'; cotIAAplicar(m.fornecedor_nome||'', m.draft, {}); }
function cotInboxVerCorpo(i){ const m=(COT.inbox||[])[i]; if(!m)return; alert('De: '+(m.from_nome||'')+' <'+(m.from_email||'')+'>\nAssunto: '+(m.assunto||'')+'\n\n'+(m.corpo_preview||'(sem corpo)')); }
async function cotInboxMarcar(id,acao){ try{ const r=await (await fetch('actions/inbox.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({acao,me:EU&&EU.bitrix_id,id})})).json(); if(r&&r.error){toast(r.error);return;} const c=(COT.cur||{}).cotacao; if(c)cotInboxLoad(c.id); }catch(e){toast('Falha: '+e.message);} }
function upCard(label,val,sub,color){ return `<div style="border:1px solid var(--line);border-radius:9px;padding:10px 12px;background:#fff">
  <div style="font-size:10px;text-transform:uppercase;letter-spacing:.4px;color:#889;font-weight:700">${esc(label)}</div>
  <div style="font-size:17px;font-weight:800;color:${color};margin-top:3px">${val}</div>
  ${sub?`<div style="font-size:10.5px;color:#889;margin-top:1px">${esc(sub)}</div>`:''}</div>`; }
// Comparativo de PREÇOS adaptativo: se há mais FORNECEDORES que itens, vira a tabela (fornecedores nas LINHAS,
// ranqueados pelo total) — fica uma lista vertical que cabe na página; senão itens nas linhas (estilo clássico).
function upPrecos(itens,props,m,best,verba){
  const melhor=Number(m.melhor_total)||0;
  let h=`<div style="font-weight:800;font-size:14px;margin:14px 0 8px;color:var(--verde-d)">Comparativo de Preços</div><div style="overflow-x:auto"><table class="up-tbl">`;
  if(props.length>itens.length && props.length>=5){
    // ---- FORNECEDORES nas linhas (ranking por total) ----
    const ranked=props.slice().sort((a,b)=>((a.total==null?Infinity:a.total)-(b.total==null?Infinity:b.total)));
    let cheapest=null; ranked.forEach(p=>{ if(p.total!=null&&(cheapest==null||p.total<cheapest))cheapest=p.total; });
    h+=`<thead><tr><th style="width:26px">#</th><th style="text-align:left;min-width:150px">Fornecedor</th>`;
    itens.forEach(it=>{ const dsc=String(it.descricao||''); h+=`<th style="min-width:88px" title="${esc(dsc)}">${esc(dsc.slice(0,36))}${dsc.length>36?'…':''}<div style="font-weight:400;font-size:9px;color:#889">${cotNum(it.quantidade)} ${esc(it.unidade||'')}</div></th>`; });
    h+=`<th>Total</th>${verba>0?'<th>vs verba</th>':''}</tr></thead><tbody>`;
    ranked.forEach((p,idx)=>{ const win=p.total!=null&&p.total===cheapest;
      h+=`<tr style="${win?'background:#eafaf0':''}"><td style="font-weight:700;text-align:center">${win?'🏆':(idx+1)}</td><td style="text-align:left;font-weight:${win?'800':'600'}">${esc(p.fornecedor_nome)}${p.prazo?`<div style="font-weight:400;font-size:9px;color:#889">${esc(p.prazo)}</div>`:''}</td>`;
      itens.forEach(it=>{ const pi=(p.itens||{})[it.id], bb=best[it.id], isBI=bb&&bb.proposta_id===p.id;
        h+=`<td style="${isBI?'background:#d9f2e3;font-weight:700':''};vertical-align:top">${pi&&pi.preco_unit!=null?`${BRL(pi.preco_unit)}${pi.observacao?`<div style="margin-top:3px;background:#eef1f3;border:1px solid #dde2e6;border-radius:5px;padding:4px 6px;font-size:8.5px;font-weight:400;color:#4a5560;text-align:left;line-height:1.35;white-space:normal">${esc(pi.observacao)}</div>`:''}`:'<span style="color:#bbb">—</span>'}</td>`; });
      const vv=(verba>0&&p.total!=null)?verba-p.total:null;
      h+=`<td style="font-weight:800">${p.total!=null?BRL(p.total):'—'}</td>${verba>0?`<td style="font-size:10.5px;color:${vv==null?'#889':(vv>=0?'var(--ok)':'var(--pend)')}">${vv==null?'—':(vv>=0?'+':'')+BRL(vv)}</td>`:''}</tr>`; });
    if(itens.length>1){ h+=`<tr style="background:#f4f7f5;font-weight:800"><td style="text-align:center">★</td><td style="text-align:left">Melhor por item</td>`;
      itens.forEach(it=>{ const bb=best[it.id]; h+=`<td style="color:var(--verde-d)">${bb?BRL(bb.preco_unit):'—'}</td>`; });
      h+=`<td style="color:var(--verde-d)">${melhor?BRL(melhor):'—'}</td>${verba>0?'<td></td>':''}</tr>`; }
  } else {
    // ---- ITENS nas linhas (poucos fornecedores) ----
    h+=`<thead><tr><th style="text-align:left;min-width:150px;max-width:260px">Item</th><th style="width:40px">Qtd</th><th style="width:34px">Un</th>`;
    props.forEach(p=>{ h+=`<th style="min-width:92px">${esc(p.fornecedor_nome)}${p.prazo?`<div style="font-weight:400;font-size:9px;color:#889">${esc(p.prazo)}</div>`:''}</th>`; });
    h+=`<th style="background:#eafaf0;color:var(--verde-d)">Melhor preço</th></tr></thead><tbody>`;
    itens.forEach(it=>{ const b=best[it.id];
      h+=`<tr><td style="text-align:left">${esc(it.descricao)}</td><td>${cotNum(it.quantidade)}</td><td>${esc(it.unidade||'')}</td>`;
      props.forEach(p=>{ const pi=(p.itens||{})[it.id], isB=b&&b.proposta_id===p.id;
        h+=`<td style="${isB?'background:#d9f2e3;font-weight:700':''};vertical-align:top">${pi&&pi.preco_total!=null?`${BRL(pi.preco_unit)}${isB?' 🏆':''}<div style="font-size:9.5px;color:#889;font-weight:400">${BRL(pi.preco_total)}</div>${pi.observacao?`<div style="margin-top:3px;background:#eef1f3;border:1px solid #dde2e6;border-radius:5px;padding:4px 6px;font-size:8.5px;font-weight:400;color:#4a5560;text-align:left;line-height:1.35;white-space:normal">${esc(pi.observacao)}</div>`:''}`:'<span style="color:#bbb">—</span>'}</td>`; });
      h+=`<td style="background:#eafaf0">${b?`<b>${BRL(b.preco_unit)}</b><div style="font-size:9.5px;color:#889">${BRL(b.preco_total)} · ${esc(b.fornecedor)}</div>`:'—'}</td></tr>`; });
    h+=`<tr style="background:#f4f7f5;font-weight:800"><td style="text-align:left">TOTAL GERAL</td><td></td><td></td>`;
    props.forEach(p=>{ const isBS=m.fornecedor_destaque===p.fornecedor_nome; h+=`<td style="${isBS?'color:var(--verde-d)':''}">${p.total!=null?BRL(p.total):'—'}</td>`; });
    h+=`<td style="background:#eafaf0;color:var(--verde-d)">${melhor?BRL(melhor):'—'}</td></tr>`;
  }
  return h+`</tbody></table></div>`;
}
// Equalização adaptativa: pontos nas linhas (padrão) OU, se há mais fornecedores que pontos, fornecedores nas linhas.
function upEqualiza(props,pontos){
  let h=`<div style="font-weight:800;font-size:14px;margin:16px 0 8px;color:var(--verde-d)">Comparativo de Equalização</div><div style="overflow-x:auto"><table class="up-tbl">`;
  if(props.length>pontos.length && props.length>=5){
    h+=`<thead><tr><th style="text-align:left;min-width:150px">Fornecedor</th>`;
    pontos.forEach(pt=>{ h+=`<th style="min-width:90px" title="${esc(pt)}">${esc(pt.slice(0,34))}${pt.length>34?'…':''}</th>`; });
    h+=`</tr></thead><tbody>`;
    props.forEach(p=>{ h+=`<tr><td style="text-align:left;font-weight:600">${esc(p.fornecedor_nome)}</td>`;
      pontos.forEach(pt=>{ const v=((p.equaliza||{})[pt])||''; h+=`<td style="text-align:left">${v?esc(v):'<span style="color:#bbb">—</span>'}</td>`; });
      h+='</tr>'; });
  } else {
    h+=`<thead><tr><th style="text-align:left;min-width:180px">Ponto a conferir</th>`;
    props.forEach(p=>{ h+=`<th style="min-width:92px">${esc(p.fornecedor_nome)}</th>`; });
    h+=`</tr></thead><tbody>`;
    pontos.forEach(pt=>{ h+=`<tr><td style="text-align:left;font-weight:600">${esc(pt)}</td>`;
      props.forEach(p=>{ const v=((p.equaliza||{})[pt])||''; h+=`<td style="text-align:left">${v?esc(v):'<span style="color:#bbb">—</span>'}</td>`; });
      h+='</tr>'; });
  }
  return h+`</tbody></table></div>`;
}
function cotUmaPagina(){
  const d=COT.cur,c=d.cotacao,itens=d.itens||[],props=d.propostas||[],m=d.mapa||{},best=m.melhor_por_item||{},w=document.getElementById('cotwrap');
  const pontos=cotEqPontos(c);
  const dataC=c.created_at?D(String(c.created_at).slice(0,10)):'—';
  const verba=Number(c.verba)||0, melhor=Number(m.melhor_total)||0;
  const economia=(verba>0&&melhor>0)?verba-melhor:null, ecoPct=(economia!=null&&verba>0)?Math.round(economia/verba*100):null;
  const vOrig={curada:'curada ✓',auto:'auto 🤖',definida:'definida',manual:'manual'}[c.verba_origem]||c.verba_origem||'';
  let h=`<div class="up-noprint" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;margin-bottom:12px">
    <button class="btn-ghost" onclick="cotRenderDetalhe()"><span class="material-icons" style="font-size:16px;vertical-align:-3px">arrow_back</span> Voltar ao mapa</button>
    <button class="btn-prim" style="padding:6px 14px" onclick="window.print()"><span class="material-icons" style="font-size:15px;vertical-align:-3px">print</span> Imprimir / PDF</button>
    <span class="muted" style="font-size:11.5px">Resumo de uma página (paisagem) — pronto pra imprimir ou salvar em PDF.</span></div>`;
  h+=`<div id="cotUmaPagina" style="background:#fff;color:#1e2b24;padding:6px 2px">`;
  h+=`<div style="border:1px solid var(--line);border-radius:10px;padding:14px 16px;margin-bottom:12px;background:#f7faf8">
    <div style="font-size:19px;font-weight:800;color:var(--verde-d)"><span class="material-icons" style="font-size:20px;vertical-align:-3px;color:var(--dourado)">request_quote</span> Mapa de Cotações — ${esc(c.titulo||'')}</div>
    <div style="font-size:12px;margin-top:5px;color:#667"><b>Obra:</b> ${esc(c.obra_nome||'—')} &nbsp;·&nbsp; <b>Data:</b> ${dataC}${c.criado_nome?` &nbsp;·&nbsp; <b>Criado por:</b> ${esc(c.criado_nome)}`:''}${c.categoria?` &nbsp;·&nbsp; <b>Categoria:</b> ${esc(c.categoria)}`:''}${props.length?` &nbsp;·&nbsp; <b>${props.length}</b> proposta(s)`:''}${c.num_solicitacao?` &nbsp;·&nbsp; <b>SC:</b> ${esc(c.num_solicitacao)}`:''}${c.num_pedido?` &nbsp;·&nbsp; <b>Pedido:</b> ${esc(c.num_pedido)}`:''}</div>
    ${c.descricao?`<div style="font-size:12.5px;margin-top:8px;line-height:1.5;color:#334">${esc(c.descricao)}</div>`:''}</div>`;
  h+=`<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:10px;margin-bottom:4px">
    ${upCard('Verba prevista', verba?BRL(verba):'—', vOrig, 'var(--muted)')}
    ${upCard('Melhor compra', melhor?BRL(melhor):'—', 'menor preço por item', 'var(--ok)')}
    ${upCard('Melhor fornecedor único', m.melhor_oferta?BRL(m.melhor_oferta):'—', m.fornecedor_destaque||'', 'var(--dourado)')}
    ${economia!=null?upCard('Economia vs verba', BRL(economia), (ecoPct!=null?ecoPct+'% da verba':''), economia>=0?'var(--ok)':'var(--pend)'):''}</div>`;
  h+= props.length ? upPrecos(itens,props,m,best,verba) : `<div class="dmini" style="margin-top:12px">Sem propostas ainda — cadastre propostas para montar o comparativo.</div>`;
  if(pontos.length&&props.length) h+=upEqualiza(props,pontos);
  h+=`<div style="font-size:10px;margin-top:14px;text-align:right;color:#99a">Cockpit de Suprimentos · Caprem · gerado em ${D(new Date().toISOString().slice(0,10))}</div></div>`;
  w.innerHTML=h; window.scrollTo(0,0);
}
// EQUALIZAÇÃO — pontos a conferir por proposta (diesel? faturamento mín., mobilização, retenção, ISS, ART…)
const DEFAULT_EQ=['Frete','Condição de pagamento','Descarregamento'];   // pontos padrão em TODA cotação (em branco até preencher)
function cotEqPontos(c){ const p=((c&&c.equalizacao)||'').split(/\r?\n|\|/).map(s=>s.trim()).filter(Boolean); return p.length?p:DEFAULT_EQ.slice(); }
/* CONFERÊNCIA DE CONTATOS do convidado (e-mail/telefone/WhatsApp) — indicador de preenchido + última atualização + edição inline (base p/ o disparo) */
function cotConvContatos(cf){
  const at=cf.contatos_at||{}, fid=cf.fornecedor_id, editable=fid&&CAN_EDIT;
  const fld=(icon,key,val,ph,w)=>{ const v=(val||''), filled=!!String(v).trim(), when=at[key]?` <span class="muted" style="font-size:9.5px" title="última atualização">${D(String(at[key]).slice(0,10))}</span>`:'';
    return `<div style="display:flex;align-items:center;gap:3px"><span class="material-icons" style="font-size:13px;color:${filled?'var(--ok)':'var(--pend)'}" title="${filled?'preenchido':'faltando'}">${icon}</span>${editable?`<input data-ct="${key}" value="${esc(v)}" placeholder="${ph}" style="font-size:11px;padding:2px 5px;width:${w}px;border:1px solid ${filled?'var(--line)':'var(--pend)'};border-radius:5px">`:`<span style="font-size:11px;${filled?'':'color:var(--pend)'}">${filled?esc(v):'faltando'}</span>`}${when}</div>`; };
  return `<div class="cotconv-ct" style="display:flex;flex-wrap:wrap;gap:10px;align-items:center;margin-top:7px;padding-left:16px">
    ${fld('mail','email',cf.email,'email@fornecedor',175)}
    ${fld('call','telefone',cf.telefone,'(19) 0000-0000',115)}
    ${fld('chat','whatsapp',cf.whatsapp,'WhatsApp',115)}
    ${editable?`<button class="btn-ghost" style="padding:2px 8px;font-size:11px" onclick="cotContatoSalvar(${fid},this)"><span class="material-icons" style="font-size:12px;vertical-align:-2px">save</span> salvar contatos</button>`:(!fid?'<span class="dmini" style="font-size:10px">fornecedor manual — sem cadastro p/ editar</span>':'')}</div>`;
}
async function cotContatoSalvar(fid,btn){
  const row=btn.closest('.cotconv-ct'); if(!row)return; const body={acao:'contato_salvar',me:EU&&EU.bitrix_id,id:fid};
  row.querySelectorAll('input[data-ct]').forEach(inp=>{ body[inp.getAttribute('data-ct')]=inp.value; });
  try{ const r=await (await fetch('actions/fornecedores.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(body)})).json();
    if(r.error){toast(r.error);return;} toast('Contatos salvos'); cotOpen(COT.cur.cotacao.id);
  }catch(e){toast('Falha: '+e.message);}
}
function cotEqualizaPanel(d){
  const c=d.cotacao||{}, props=d.propostas||[], pontos=cotEqPontos(c), editV=!!COT.eqEdit;
  const eqActions=`${CAN_EDIT?`<button class="btn-ghost" style="padding:3px 9px" onclick="cotEqualizaEdit()"><span class="material-icons" style="font-size:14px;vertical-align:-3px">edit_note</span> Editar pontos</button>`:''}${(CAN_EDIT&&props.length&&pontos.length)?(editV?`<button class="btn-prim" style="padding:3px 10px" onclick="cotEqValoresSave()"><span class="material-icons" style="font-size:14px;vertical-align:-3px">check</span> Salvar valores</button><button class="btn-ghost" style="padding:3px 9px" onclick="cotEqValoresCancel()">Cancelar</button>`:`<button class="btn-ghost" style="padding:3px 9px" onclick="cotEqValoresEdit()"><span class="material-icons" style="font-size:14px;vertical-align:-3px">edit</span> Editar valores</button>`):''}`;
  let h=`<div class="panel" style="margin-bottom:10px">${cotSecHead('rule','Equalização','pontos a conferir por proposta',eqActions)}
    <div id="cotEqEdit" style="display:none;margin-top:8px"><textarea id="cotEqPontos" rows="6" style="width:100%;font-size:12.5px" placeholder="Um ponto por linha…">${esc(pontos.join('\n'))}</textarea>
      <div style="margin-top:6px"><button class="btn-prim" style="padding:5px 12px" onclick="cotEqualizaPontosSave()">Salvar pontos</button> <button class="btn-ghost" style="padding:5px 12px" onclick="document.getElementById('cotEqEdit').style.display='none'">Cancelar</button></div></div>`;
  if(!props.length){ h+='<ul style="margin:8px 0 0 18px;padding:0">'+pontos.map(p=>`<li style="font-size:12.5px;margin-bottom:3px">${esc(p)}</li>`).join('')+'</ul><div class="dmini" style="margin-top:6px">Cadastre propostas para preencher cada ponto por fornecedor.</div></div>'; return h; }
  h+=(editV?'<div class="dmini" style="margin-top:8px;color:var(--verde-d)">Modo edição — ajuste os valores e clique <b>Salvar valores</b>.</div>':'')+'<div style="overflow-x:auto;margin-top:8px"><table class="mtable" style="border:none"><thead><tr><th class="svc-h" style="text-align:left;min-width:200px">Ponto a conferir</th>';
  props.forEach(p=>{ h+=`<th style="min-width:140px">${esc(p.fornecedor_nome)}</th>`; });
  h+='</tr></thead><tbody>';
  pontos.forEach(pt=>{ h+=`<tr><td class="svc-c" style="text-align:left;font-size:12px">${esc(pt)}</td>`;
    props.forEach(p=>{ const v=((p.equaliza||{})[pt])||''; h+=`<td style="padding:4px 6px">${editV?`<input data-eqpid="${p.id}" data-eqpt="${esc(pt)}" value="${esc(v)}" style="width:100%;font-size:11.5px;padding:3px 5px;border:1px solid var(--line);border-radius:5px" placeholder="—">`:`<span style="font-size:11.5px">${esc(v||'—')}</span>`}</td>`; });
    h+='</tr>'; });
  h+='</tbody></table></div></div>';
  return h;
}
function cotEqValoresEdit(){ COT.eqEdit=true; cotRenderDetalhe(); }
function cotEqValoresCancel(){ COT.eqEdit=false; cotRenderDetalhe(); }
async function cotEqValoresSave(){
  const props=(COT.cur&&COT.cur.propostas)||[]; let n=0;
  for(const p of props){ const map={}; document.querySelectorAll('input[data-eqpid="'+p.id+'"]').forEach(inp=>{ const val=inp.value.trim(); if(val) map[inp.getAttribute('data-eqpt')]=val; });
    p.equaliza=map;
    try{ await fetch('actions/cotacoes.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({acao:'equaliza_salvar',me:EU&&EU.bitrix_id,cotacao_id:COT.cur.cotacao.id,proposta_id:p.id,equaliza:map})}); n++; }catch(e){}
  }
  COT.eqEdit=false; cotRenderDetalhe(); toast('Equalização salva ('+n+' proposta(s))');
}
function cotEqualizaEdit(){ const e=document.getElementById('cotEqEdit'); if(e) e.style.display=(e.style.display==='none'?'block':'none'); }
async function cotEqualizaPontosSave(){
  const txt=(document.getElementById('cotEqPontos')||{}).value||'';
  const oldP=cotEqPontos(COT.cur.cotacao);                                    // pontos ANTES (valores por proposta são chaveados pelo texto)
  const newP=txt.split(/\r?\n|\|/).map(s=>s.trim()).filter(Boolean);
  try{ const r=await (await fetch('actions/cotacoes.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({acao:'equaliza_salvar',me:EU&&EU.bitrix_id,cotacao_id:COT.cur.cotacao.id,equalizacao:txt})})).json();
    if(r.error){toast(r.error);return;}
    COT.cur.cotacao.equalizacao=txt;
    // Renomeou/reordenou mantendo a mesma quantidade de pontos? Remapeia POSICIONALMENTE os valores já preenchidos
    // por proposta, senão eles ficariam órfãos (chaveados pelo texto antigo) e apareceriam como "—".
    if(oldP.length===newP.length && oldP.some((k,i)=>k!==newP[i])){
      for(const p of (COT.cur.propostas||[])){
        const old=p.equaliza||{}, nm={}; let mudou=false;
        newP.forEach((k,i)=>{ const v=old[oldP[i]]; if(v!=null&&v!=='') { nm[k]=v; mudou=true; } });
        p.equaliza=nm;
        if(mudou){ try{ await fetch('actions/cotacoes.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({acao:'equaliza_salvar',me:EU&&EU.bitrix_id,cotacao_id:COT.cur.cotacao.id,proposta_id:p.id,equaliza:nm})}); }catch(e){} }
      }
    }
    cotRenderDetalhe(); toast('Pontos de equalização salvos');
  }catch(e){ toast('Falha ao salvar'); }
}
function cotProposta(pid){
  const d=COT.cur; let ex=null; if(pid) ex=(d.propostas||[]).find(p=>p.id===pid);
  COT.prop={id:pid||0, precos:{}};
  (d.itens||[]).forEach(it=>{ const pi=ex?(ex.itens||{})[it.id]:null; COT.prop.precos[it.id]={preco_unit:pi&&pi.preco_unit!=null?pi.preco_unit:'',preco_total:pi&&pi.preco_total!=null?pi.preco_total:''}; });
  COT.prop.fornecedor_nome=ex?ex.fornecedor_nome:''; COT.prop.prazo=ex?ex.prazo:''; COT.prop.observacoes=ex?ex.observacoes:'';
  COT.mode='proposta'; cotRenderProposta(); cotFornDatalist(((COT.cur||{}).cotacao||{}).categoria);
}
async function cotFornDatalist(categoria){
  // datalist de sugestão (input é texto livre) — NÃO filtra por categoria (evita lista vazia por taxonomia divergente)
  try{ const d=await (await fetch('actions/fornecedores.php?limit=500')).json();
    const dl=document.getElementById('prFForn'); if(dl) dl.innerHTML=(d.fornecedores||[]).map(f=>`<option value="${esc(f.nome)}">${esc(f.categoria||'')}</option>`).join('');
  }catch(e){}
}
function cotRenderProposta(){
  const d=COT.cur,c=d.cotacao,itens=d.itens||[],pr=COT.prop;
  document.getElementById('cotwrap').innerHTML=`<div class="panel">
    <div style="display:flex;align-items:center;gap:8px;margin-bottom:12px"><button class="btn-ghost" onclick="cotRenderDetalhe()"><span class="material-icons" style="font-size:16px;vertical-align:-3px">arrow_back</span> Voltar ao mapa</button><b style="font-size:15px">${pr.id?'Editar':'Cadastrar'} proposta · ${esc(c.titulo)}</b></div>
    <div style="max-width:860px">
    <div style="display:grid;grid-template-columns:1fr 220px;gap:10px">
      ${cotFld('Fornecedor *','<input id="prF" list="prFForn" autocomplete="off" style="width:100%" value="'+esc(pr.fornecedor_nome||'')+'" placeholder="Fornecedor (sugestões por categoria)"><datalist id="prFForn"></datalist>')}
      ${cotFld('Prazo de entrega','<input id="prP" style="width:100%" value="'+esc(pr.prazo||'')+'" placeholder="Ex.: 15 dias">')}
    </div>
    ${cotFld('Observações','<textarea id="prO" rows="2" style="width:100%">'+esc(pr.observacoes||'')+'</textarea>','margin-top:8px')}
    <div style="margin-top:14px"><b style="font-size:13px">Preços por item</b> <span class="muted" style="font-size:11px">(preencha o unitário — o total calcula pela quantidade)</span></div>
    <div style="margin-top:8px;border:1px solid var(--line);border-radius:10px;overflow:hidden">
      <div style="display:grid;grid-template-columns:minmax(0,1fr) 130px 150px;gap:10px;padding:7px 12px;background:#fafbfb;border-bottom:1px solid var(--line);font-size:10.5px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.3px"><span>Item</span><span style="text-align:right">Preço unit.</span><span style="text-align:right">Preço total</span></div>
      ${itens.map((it,ix)=>`<div style="display:grid;grid-template-columns:minmax(0,1fr) 130px 150px;gap:10px;align-items:center;padding:9px 12px;${ix<itens.length-1?'border-bottom:1px solid #f1f3f2':''}">
      <div><b style="font-size:12.5px">${esc(it.descricao)}</b> <span class="muted" style="font-size:11px">· ${cotNum(it.quantidade)} ${esc(it.unidade||'')}</span>${it.observacao?`<div class="muted" style="font-size:10.5px;margin-top:1px">${esc(it.observacao)}</div>`:''}</div>
      <input type="text" inputmode="decimal" id="prU${it.id}" value="${pr.precos[it.id].preco_unit!==''?fmtMoney(pr.precos[it.id].preco_unit):''}" oninput="cotPrecoIn(${it.id},'u',this)" onblur="moneyBlur(this)" placeholder="0,00" style="width:100%;text-align:right">
      <input type="text" inputmode="decimal" id="prT${it.id}" value="${pr.precos[it.id].preco_total!==''?fmtMoney(pr.precos[it.id].preco_total):''}" oninput="cotPrecoIn(${it.id},'t',this)" onblur="moneyBlur(this)" placeholder="0,00" style="width:100%;text-align:right"></div>`).join('')}</div>
    <div style="margin-top:14px"><button class="btn-prim" onclick="cotSalvarProposta()"><span class="material-icons" style="font-size:16px;vertical-align:-3px">check</span> Salvar proposta</button></div>
    </div></div>`;
}
function cotPrecoIn(iid,which,el){
  maskMoneyInput(el);                              // reformata ao vivo (150000 -> 150.000)
  const n=parseBRLInput(el.value);                 // número (ou null)
  const p=COT.prop.precos[iid], it=(COT.cur.itens||[]).find(x=>x.id===iid), q=it&&it.quantidade?Number(it.quantidade):null;
  if(which==='u'){ p.preco_unit=(n==null?'':n); if(q&&n!=null){ p.preco_total=+(n*q).toFixed(2); const el2=document.getElementById('prT'+iid); if(el2)el2.value=fmtMoney(p.preco_total); } }
  else p.preco_total=(n==null?'':n);
}
async function cotSalvarProposta(){
  const forn=val('prF').trim(); if(!forn){toast('Informe o fornecedor');return;}
  const itens=Object.entries(COT.prop.precos).map(([iid,p])=>({cotacao_item_id:Number(iid),preco_unit:p.preco_unit!==''?Number(p.preco_unit):'',preco_total:p.preco_total!==''?Number(p.preco_total):''}));
  const body={acao:'proposta',me:EU&&EU.bitrix_id,cotacao_id:COT.cur.cotacao.id,proposta_id:COT.prop.id||undefined,fornecedor_nome:forn,prazo:val('prP'),observacoes:val('prO'),itens};
  try{ const r=await (await fetch('actions/cotacoes.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(body)})).json();
    if(r.error){toast(r.error);return;}
    // garante que o fornecedor da proposta esteja na Concorrência (p/ editar/excluir a proposta ali)
    try{ const nz=s=>String(s||'').trim().toLowerCase(); if(!((COT.cur&&COT.cur.convidados)||[]).some(cv=>nz(cv.fornecedor_nome)===nz(forn))) await fetch('actions/cotacoes.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({acao:'convidar',me:EU&&EU.bitrix_id,cotacao_id:COT.cur.cotacao.id,convidados:[{nome:forn}]})}); }catch(e){}
    // aplica a equalização pré-preenchida pela IA nesta proposta (mescla com o que já houver, sem apagar valores manuais)
    if(COT.prop.eqIA && Object.keys(COT.prop.eqIA).length && r.proposta_id){
      const src=(COT.cur.propostas||[]).find(p=>p.id===r.proposta_id), base=(src&&src.equaliza)?Object.assign({},src.equaliza):{};
      const merged=Object.assign(base,COT.prop.eqIA);
      try{ await fetch('actions/cotacoes.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({acao:'equaliza_salvar',me:EU&&EU.bitrix_id,cotacao_id:COT.cur.cotacao.id,proposta_id:r.proposta_id,equaliza:merged})}); }catch(e){}
    }
    toast('Proposta salva'); cotOpen(COT.cur.cotacao.id);
  }catch(e){toast('Falha: '+e.message);}
}
async function cotFinalizar(){ const c=COT.cur.cotacao, novo=c.status==='finalizada'?'aguardando':'finalizada';
  let numPedido;
  // TRAVA: cotação AVULSA (sem vínculo ao radar) só finaliza com nº do pedido de compra — admin pode furar
  if(novo==='finalizada' && !c.servico_id && !(c.num_pedido&&String(c.num_pedido).trim())){
    const pc=prompt('Nº do PEDIDO DE COMPRA (obrigatório para finalizar esta cotação avulsa):', c.num_pedido||'');
    if(pc===null) return;
    if(pc.trim()===''){
      if(!IS_ADMIN){ toast('Informe o nº do pedido de compra para finalizar.'); return; }
      if(!confirm('Finalizar SEM o nº do pedido de compra? (exceção de admin)')) return;
    }
    numPedido=pc.trim();
  }
  try{ const body={acao:'status',me:EU&&EU.bitrix_id,cotacao_id:c.id,status:novo};
    if(numPedido!==undefined) body.num_pedido=numPedido;
    const r=await (await fetch('actions/cotacoes.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(body)})).json();
    if(r&&r.error){ toast(r.error); return; }
    cotOpen(c.id);
  }catch(e){toast('Falha');} }
async function cotNumerosSalvar(){ const c=COT.cur.cotacao;
  const body={acao:'numeros_salvar',me:EU&&EU.bitrix_id,cotacao_id:c.id,num_solicitacao:val('cotDetSC'),num_pedido:val('cotDetPC')};
  try{ const r=await (await fetch('actions/cotacoes.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(body)})).json();
    if(r&&r.error){toast(r.error);return;} c.num_solicitacao=val('cotDetSC'); c.num_pedido=val('cotDetPC'); toast('Números salvos'); }catch(e){toast('Falha: '+e.message);} }
/* "Fotinha" do pedido de compra (dados do TOTVS via Supabase): fornecedor(es), itens, preços unit e total */
async function cotPedidoVer(numero,coligadaCod){
  numero=String(numero||'').split(',')[0].trim(); if(!numero){toast('Sem nº de pedido');return;}
  let ov=document.getElementById('pedOverlay'); if(!ov){ ov=document.createElement('div'); ov.id='pedOverlay'; ov.style.cssText='position:fixed;inset:0;background:rgba(15,25,20,.42);z-index:9999;display:flex;align-items:center;justify-content:center;padding:20px'; document.body.appendChild(ov); }
  ov.onclick=()=>ov.remove();
  const shell=b=>`<div style="background:#fff;border-radius:14px;padding:18px;max-width:740px;width:100%;max-height:85vh;overflow:auto;box-shadow:0 12px 44px rgba(0,0,0,.22)" onclick="event.stopPropagation()">${b}</div>`;
  ov.innerHTML=shell(`<div class="dempty">Buscando o pedido ${esc(numero)} no TOTVS…</div>`);
  try{ const r=await (await fetch('actions/pedidos.php?numero='+encodeURIComponent(numero)+(coligadaCod?'&coligada_cod='+encodeURIComponent(coligadaCod):'')+'&me='+encodeURIComponent((EU&&EU.bitrix_id)||''))).json();
    const close=`<span class="material-icons" onclick="document.getElementById('pedOverlay').remove()" style="cursor:pointer;color:var(--muted)">close</span>`;
    if(r.error){ ov.innerHTML=shell(`<div style="display:flex;justify-content:space-between;align-items:center"><b>Pedido ${esc(numero)}</b>${close}</div><div class="empty" style="margin-top:10px">${esc(r.error)}</div>`); return; }
    const p=r.pedido, forn=(p.fornecedores||[]).join(', ')||'—';
    ov.innerHTML=shell(`<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:4px"><b style="font-size:15px"><span class="material-icons" style="font-size:17px;vertical-align:-3px;color:var(--verde-d)">receipt_long</span> Pedido de compra ${esc(p.numero)}</b>${close}</div>
      <div class="muted" style="font-size:11.5px;margin-bottom:10px">${esc(p.coligada||'')}${p.data?' · '+D(String(p.data).slice(0,10)):''}${p.status?' · TOTVS '+esc(p.status):''} · ${p.n_itens} item(ns) · Fornecedor(es): ${esc(forn)}</div>
      <div style="overflow-x:auto"><table class="mtable" style="border:none"><thead><tr><th class="svc-h" style="text-align:left">Item</th><th style="text-align:right">Qtde</th><th style="text-align:right">Preço unit.</th><th style="text-align:right">Total</th></tr></thead><tbody>
      ${p.itens.map(it=>`<tr><td class="svc-c" style="text-align:left;font-size:12px">${esc(it.produto)}<small>${it.codprd?esc(it.codprd)+' · ':''}forn. ${esc(it.fornecedor_cod||'—')}</small></td><td style="text-align:right">${cotNum(it.qtd)} ${esc(it.und||'')}</td><td style="text-align:right">${BRL(it.preco_unit)}</td><td style="text-align:right"><b>${BRL(it.total)}</b></td></tr>`).join('')}
      <tr style="background:#f7faf8"><td class="svc-c" style="text-align:left;font-weight:800">TOTAL</td><td></td><td></td><td style="text-align:right;font-weight:800;color:var(--verde-d)">${BRL(p.total)}</td></tr>
      </tbody></table></div>
      <div class="dmini" style="margin-top:8px">Dados do TOTVS (somente leitura). O total usa preço unit × qtde quando o valor líquido ainda não foi gravado no TOTVS.</div>`);
  }catch(e){ ov.innerHTML=shell('<div class="empty">Falha ao buscar o pedido.</div>'); }
}
async function cotExcluirProposta(pid){ if(!confirm('Excluir esta proposta?'))return;
  try{ await fetch('actions/cotacoes.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({acao:'excluir_proposta',me:EU&&EU.bitrix_id,proposta_id:pid})}); cotOpen(COT.cur.cotacao.id); }catch(e){toast('Falha');} }
// ícone por tipo de anexo
function cotAnexoIcon(mime,nome){ const m=(mime||'')+' '+(nome||'');
  if(/pdf/i.test(m))return'picture_as_pdf'; if(/png|jpe?g|image/i.test(m))return'image'; if(/sheet|excel|xls/i.test(m))return'table_view'; return'insert_drive_file'; }
// modal de anexo multi-formato (PDF/Excel/imagem) POR fornecedor — arrastar, colar (Ctrl+V) ou clicar
function cotAnexarAbrir(fornId,fornNome){ COT.anexo={fornId:(fornId&&fornId!=='null')?fornId:null,fornNome:fornNome||'',files:[]};
  let ov=document.getElementById('anexOverlay'); if(!ov){ ov=document.createElement('div'); ov.id='anexOverlay'; ov.style.cssText='position:fixed;inset:0;background:rgba(15,25,20,.42);z-index:9999;display:flex;align-items:center;justify-content:center;padding:20px'; document.body.appendChild(ov); }
  document.addEventListener('paste',cotAnexarPaste); cotAnexarRender(); }
function cotAnexarFechar(){ const ov=document.getElementById('anexOverlay'); if(ov)ov.remove(); document.removeEventListener('paste',cotAnexarPaste); COT.anexo=null; }
function cotAnexarPaste(e){ if(!COT.anexo)return; const items=((e.clipboardData||{}).items)||[]; let n=0;
  for(const it of items){ if(it.kind==='file'){ const f=it.getAsFile(); if(f){ COT.anexo.files.push(f.name?f:new File([f],'print-'+Date.now()+'.png',{type:f.type||'image/png'})); n++; } } }
  if(n){ e.preventDefault(); cotAnexarRender(); toast(n+' print colado'); } }
function cotAnexarDrop(e){ e.preventDefault(); if(!COT.anexo)return; for(const f of (((e.dataTransfer||{}).files)||[]))COT.anexo.files.push(f); cotAnexarRender(); }
function cotAnexarPick(input){ if(!COT.anexo)return; for(const f of (input.files||[]))COT.anexo.files.push(f); input.value=''; cotAnexarRender(); }
function cotAnexarRender(){ const a=COT.anexo, ov=document.getElementById('anexOverlay'); if(!a||!ov)return;
  ov.onclick=cotAnexarFechar;
  ov.innerHTML=`<div style="background:#fff;border-radius:14px;padding:18px;box-shadow:0 12px 44px rgba(0,0,0,.22);width:100%;max-width:470px" onclick="event.stopPropagation()">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px"><b style="font-size:14px">Anexar para ${esc(a.fornNome)||'fornecedor'}</b><span onclick="cotAnexarFechar()" class="material-icons" style="cursor:pointer;color:var(--muted)">close</span></div>
    <label ondragover="event.preventDefault()" ondrop="cotAnexarDrop(event)" style="display:block;border:2px dashed var(--line);border-radius:12px;padding:22px;text-align:center;cursor:pointer;background:#fafbfb">
      <span class="material-icons" style="font-size:30px;color:var(--verde)">upload_file</span>
      <div style="font-size:12.5px;margin-top:4px">Arraste, <b>cole (Ctrl+V)</b> ou clique</div>
      <div class="muted" style="font-size:11px;margin-top:2px">PDF, Excel (xlsx/xls) ou imagem (PNG/JPG) · até 25 MB</div>
      <input type="file" accept=".pdf,.xlsx,.xls,image/png,image/jpeg,application/pdf" multiple style="display:none" onchange="cotAnexarPick(this)"></label>
    <div style="margin-top:10px">${a.files.length?a.files.map((f,i)=>`<div style="display:flex;align-items:center;gap:7px;padding:4px 0"><span class="material-icons" style="font-size:16px;color:var(--muted)">${cotAnexoIcon(f.type,f.name)}</span><span style="flex:1;font-size:12px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">${esc(f.name)}</span><span class="muted" style="font-size:10.5px">${(f.size/1024).toFixed(0)} KB</span><span onclick="COT.anexo.files.splice(${i},1);cotAnexarRender()" class="material-icons" style="cursor:pointer;color:var(--pend);font-size:16px">close</span></div>`).join(''):'<div class="dmini">Nenhum arquivo ainda.</div>'}</div>
    <div style="display:flex;gap:8px;justify-content:flex-end;margin-top:14px">
      <button class="btn-ghost" onclick="cotAnexarFechar()">Cancelar</button>
      <button class="btn-ghost" onclick="cotAnexarEnviar(true)" ${a.files.length?'':'disabled style=\"opacity:.5\"'} title="anexa e já manda a IA preencher a proposta"><span class="material-icons" style="font-size:14px;vertical-align:-3px;color:var(--verde-d)">auto_awesome</span> Anexar + IA</button>
      <button class="btn-prim" onclick="cotAnexarEnviar(false)" ${a.files.length?'':'disabled style=\"opacity:.5\"'}><span class="material-icons" style="font-size:15px;vertical-align:-3px">attach_file</span> Anexar${a.files.length?' '+a.files.length:''}</button>
    </div></div>`; }
async function cotAnexarEnviar(runIA){ const a=COT.anexo; if(!a||!a.files.length)return; const files=a.files.slice(), fornId=a.fornId, fornNome=a.fornNome;
  toast('Enviando '+files.length+' arquivo(s)…'); let ok=0,fail=0;
  for(const f of files){ if(await cotUploadAnexoFile(f,fornId,fornNome))ok++; else fail++; }
  cotAnexarFechar(); toast(ok+' anexado(s)'+(fail?' · '+fail+' falharam':''));
  if(ok){ await cotOpen(COT.cur.cotacao.id); if(runIA) cotIAPreencher(fornId,fornNome); } }
async function cotUploadAnexoFile(file,fornId,fornNome,propostaId){
  if(file.size>25*1024*1024){ toast('"'+file.name+'": máx 25 MB'); return false; }
  const fd=new FormData(); fd.append('arquivo',file); fd.append('cotacao_id',COT.cur.cotacao.id);
  if(fornId)fd.append('fornecedor_id',fornId); if(fornNome)fd.append('fornecedor_nome',fornNome); if(propostaId)fd.append('proposta_id',propostaId);
  fd.append('me',(EU&&EU.bitrix_id)||'');
  try{ const r=await (await fetch('actions/cotacao_anexo.php',{method:'POST',body:fd})).json(); if(r.error){ toast(file.name+': '+r.error); return null; } return r; }
  catch(e){ toast('Falha: '+e.message); return null; } }
/* --- Motor de IA: lê os anexos do fornecedor e preenche a proposta (RASCUNHO p/ validação humana) --- */
function cotIAForn(fornId,fornNome){ const d=COT.cur||{}, nz=s=>String(s||'').trim().toLowerCase().replace(/\s+/g,' ');
  return (d.anexos||[]).filter(a=>((a.fornecedor_id&&fornId&&String(a.fornecedor_id)===String(fornId))||(a.fornecedor_nome&&nz(a.fornecedor_nome)===nz(fornNome)))); }
function cotIAPreencher(fornId,fornNome){ fornId=(fornId&&fornId!=='null')?fornId:null;
  const ax=cotIAForn(fornId,fornNome); if(!ax.length){ toast('Anexe um PDF, Excel ou print desse fornecedor primeiro'); return; }
  COT.ia={fornId,fornNome,sel:ax.map(a=>a.id),anexos:ax,busy:false}; cotIARender(); }
function cotIAFechar(){ const ov=document.getElementById('iaOverlay'); if(ov)ov.remove(); COT.ia=null; }
function cotIAToggle(id,on){ const s=COT.ia; if(!s)return; s.sel=on?[...new Set([...s.sel,id])]:s.sel.filter(x=>x!==id); cotIARender(); }
function cotIARender(){ const s=COT.ia; if(!s)return; let ov=document.getElementById('iaOverlay');
  if(!ov){ ov=document.createElement('div'); ov.id='iaOverlay'; ov.style.cssText='position:fixed;inset:0;background:rgba(15,25,20,.42);z-index:9999;display:flex;align-items:center;justify-content:center;padding:20px'; document.body.appendChild(ov); }
  ov.onclick=()=>{ if(!s.busy)cotIAFechar(); };
  ov.innerHTML=`<div style="background:#fff;border-radius:14px;padding:18px;box-shadow:0 12px 44px rgba(0,0,0,.22);width:100%;max-width:470px" onclick="event.stopPropagation()">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px"><b style="font-size:14px"><span class="material-icons" style="font-size:16px;vertical-align:-3px;color:var(--verde-d)">auto_awesome</span> Preencher proposta com IA</b><span onclick="cotIAFechar()" class="material-icons" style="cursor:pointer;color:var(--muted)">close</span></div>
    <div class="muted" style="font-size:11.5px;margin-bottom:10px">${esc(s.fornNome)||'fornecedor'} — escolha o(s) anexo(s) que a IA vai ler:</div>
    <div style="display:flex;flex-direction:column;gap:4px;max-height:230px;overflow:auto">${s.anexos.map(a=>`<label style="display:flex;align-items:center;gap:8px;font-size:12.5px;cursor:pointer;padding:4px 2px"><input type="checkbox" ${s.sel.includes(a.id)?'checked':''} onchange="cotIAToggle(${a.id},this.checked)"><span class="material-icons" style="font-size:16px;color:var(--muted)">${cotAnexoIcon(a.mime,a.nome)}</span><span style="flex:1;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">${esc(a.nome)}</span></label>`).join('')}</div>
    <div style="background:#fbf7e8;border:1px solid #eadfb0;border-radius:8px;padding:7px 10px;margin-top:10px;font-size:11px;color:#6b5a1e">A IA gera um <b>rascunho</b> — você confere e ajusta os valores antes de salvar.</div>
    <div style="display:flex;gap:8px;justify-content:flex-end;margin-top:12px">${s.busy?'<span class="muted" style="font-size:12px;align-self:center">🧠 lendo os anexos…</span>':`<button class="btn-ghost" onclick="cotIAFechar()">Cancelar</button><button class="btn-prim" onclick="cotIAExecutar()" ${s.sel.length?'':'disabled style=\"opacity:.5\"'}><span class="material-icons" style="font-size:15px;vertical-align:-3px">auto_awesome</span> Preencher</button>`}</div>
  </div>`; }
async function cotIAExecutar(){ const s=COT.ia; if(!s||!s.sel.length||s.busy)return; s.busy=true; cotIARender();
  try{ const r=await (await fetch('actions/cotacao_ia.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({acao:'preencher',me:EU&&EU.bitrix_id,cotacao_id:COT.cur.cotacao.id,fornecedor_id:s.fornId,fornecedor_nome:s.fornNome,anexo_ids:s.sel})})).json();
    if(r.error){ toast(r.error); s.busy=false; cotIARender(); return; }
    const fn=s.fornNome; cotIAFechar(); cotIAAplicar(fn,r.draft,r);
  }catch(e){ toast('Falha: '+e.message); s.busy=false; cotIARender(); } }
async function cotIAAplicar(fornNome,draft,meta){ const d=COT.cur; draft=draft||{}; const nz=s=>String(s||'').trim().toLowerCase().replace(/\s+/g,' ');
  const ex=(d.propostas||[]).find(p=>nz(p.fornecedor_nome)===nz(fornNome));   // já tem proposta? edita; senão cria
  COT.prop={id:ex?ex.id:0, precos:{}};
  (d.itens||[]).forEach(it=>{ COT.prop.precos[it.id]={preco_unit:'',preco_total:''}; });
  const byId={}; (draft.itens||[]).forEach(x=>{ if(x&&x.item_id!=null)byId[x.item_id]=x; });
  let preench=0;
  (d.itens||[]).forEach(it=>{ const x=byId[it.id]; if(x&&x.preco_unit!=null&&x.preco_unit!==''){ const u=Number(x.preco_unit);
    if(!isNaN(u)){ COT.prop.precos[it.id].preco_unit=u; const q=it.quantidade?Number(it.quantidade):null; if(q)COT.prop.precos[it.id].preco_total=+(u*q).toFixed(2); preench++; } } });
  COT.prop.fornecedor_nome=fornNome; COT.prop.prazo=draft.prazo_entrega||'';
  const partes=[];
  if(Array.isArray(draft.extras)&&draft.extras.length) partes.push('Custos adicionais: '+draft.extras.map(e=>`${e.descricao||'extra'}${(e.valor!=null&&e.valor!=='')?' '+BRL(Number(e.valor)):''}`).join('; '));
  if(draft.condicao_pagamento) partes.push('Pagamento: '+draft.condicao_pagamento);
  if(draft.validade) partes.push('Validade: '+draft.validade);
  const itObs=(draft.itens||[]).filter(x=>x&&x.observacao&&x.item_id!=null).map(x=>{ const it=(d.itens||[]).find(i=>String(i.id)===String(x.item_id)); return '• '+((it&&it.descricao?it.descricao.slice(0,32)+': ':''))+x.observacao; });
  if(itObs.length) partes.push('Observações por item:\n'+itObs.join('\n'));
  if(draft.observacao_geral) partes.push(draft.observacao_geral);
  let obs='⚠ Rascunho gerado por IA'+(meta&&meta.usados&&meta.usados.length?' (fonte: '+meta.usados.join(', ')+')':'')+' — confira os valores antes de salvar.';
  if(partes.length) obs+='\n\n'+partes.join('\n');
  COT.prop.observacoes=obs;
  // EQUALIZAÇÃO da IA: cria pontos novos na cotação (ex.: Imposto) + guarda os valores p/ aplicar quando a proposta for salva
  COT.prop.eqIA={}; let novos=0;
  if(Array.isArray(draft.equalizacao)){ const pts=cotEqPontos(d.cotacao), znz=s=>String(s||'').trim().toLowerCase(), add=[];
    draft.equalizacao.forEach(e=>{ if(!e||!e.ponto)return; const ponto=String(e.ponto).trim(), valor=(e.valor==null?'':String(e.valor).trim()); if(!ponto)return;
      if(valor) COT.prop.eqIA[ponto]=valor;
      if(!pts.some(p=>znz(p)===znz(ponto)) && !add.some(p=>znz(p)===znz(ponto))) add.push(ponto); });
    if(add.length){ d.cotacao.equalizacao=[...pts,...add].join('\n'); novos=add.length;
      try{ await fetch('actions/cotacoes.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({acao:'equaliza_salvar',me:EU&&EU.bitrix_id,cotacao_id:d.cotacao.id,equalizacao:d.cotacao.equalizacao})}); }catch(e){} }
  }
  COT.mode='proposta'; cotRenderProposta(); cotFornDatalist(((COT.cur||{}).cotacao||{}).categoria);
  toast(preench+' item(ns) preenchido(s) pela IA'+(novos?' · '+novos+' ponto(s) de equalização novo(s)':'')+((meta&&meta.avisos&&meta.avisos.length)?' · '+meta.avisos.length+' aviso(s)':'')); }
/* ===== E-MAIL DE COTAÇÃO — Fase 2: compositor (prévia editável, individual por fornecedor; envio real na próxima fase) ===== */
async function cotEmailAbrir(cid){
  let ov=document.getElementById('emailOverlay'); if(!ov){ ov=document.createElement('div'); ov.id='emailOverlay'; ov.style.cssText='position:fixed;inset:0;background:rgba(15,25,20,.42);z-index:9999;display:flex;align-items:center;justify-content:center;padding:20px'; document.body.appendChild(ov); }
  ov.onclick=()=>ov.remove();
  const shell=b=>`<div style="background:#fff;border-radius:14px;padding:18px;max-width:720px;width:100%;max-height:88vh;overflow:auto;box-shadow:0 12px 44px rgba(0,0,0,.22)" onclick="event.stopPropagation()">${b}</div>`;
  ov.innerHTML=shell('<div class="dempty">Montando o e-mail…</div>');
  try{ const meq=encodeURIComponent((EU&&EU.bitrix_id)||'');
    const [g,cfg]=await Promise.all([ (await fetch('actions/email.php?compor='+cid+'&me='+meq)).json(), (await fetch('actions/email.php?config=1&me='+meq)).json() ]);
    const close=`<span class="material-icons" onclick="document.getElementById('emailOverlay').remove()" style="cursor:pointer;color:var(--muted)">close</span>`;
    if(g.error){ ov.innerHTML=shell(`<div style="display:flex;justify-content:space-between"><b>E-mail de cotação</b>${close}</div><div class="empty" style="margin-top:10px">${esc(g.error)}</div>`); return; }
    const semEmail=(g.destinatarios||[]).filter(d=>!d.tem_email).length;
    const dchips=(g.destinatarios||[]).map(d=>`<span class="dchip" style="background:${d.tem_email?'#eef4f0':'#fbeae8'};color:${d.tem_email?'var(--verde-d)':'var(--pend)'};font-weight:600;margin:2px 4px 2px 0"><span class="material-icons" style="font-size:12px;vertical-align:-2px">${d.tem_email?'check':'error'}</span> ${esc(d.fornecedor_nome)}${d.email?' · '+esc(d.email):' · sem e-mail'}</span>`).join('')||'<span class="dmini">Nenhum fornecedor convidado.</span>';
    ov.innerHTML=shell(`<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:4px"><b style="font-size:15px"><span class="material-icons" style="font-size:17px;vertical-align:-3px;color:var(--verde-d)">mail</span> E-mail de cotação — prévia</b>${close}</div>
      <div class="muted" style="font-size:11.5px;margin-bottom:10px">Modelo <b>${g.variante==='radar'?'do radar (com carta anexa)':'de solicitação (itens no corpo)'}</b> · remetente <b>${esc(g.remetente)}</b> · assinatura de <b>${esc(g.remetente_nome||'—')}</b>. Cada fornecedor recebe <b>individualmente</b>.</div>
      <div style="font-size:11px;font-weight:700;color:var(--muted)">DESTINATÁRIOS ${semEmail?`<span style="color:var(--pend)">· ${semEmail} sem e-mail (preencha na Concorrência)</span>`:'✓'}</div>
      <div style="margin:5px 0 10px">${dchips}</div>
      ${cotFld('Assunto','<input id="emAssunto" value="'+esc(g.assunto)+'" style="width:100%">')}
      ${cotFld('Corpo do e-mail (edite à vontade antes de disparar)','<textarea id="emCorpo" rows="9" style="width:100%;font-size:12.5px;font-family:inherit">'+esc(g.corpo)+'</textarea>','margin-top:8px')}
      ${g.tem_carta?'<div class="dmini" style="margin-top:6px">📎 A carta de cotação vai anexada em PDF.</div>':''}
      <div style="display:flex;align-items:flex-end;gap:8px;flex-wrap:wrap;margin-top:12px;border-top:1px solid var(--line);padding-top:12px">
        <div style="flex:1;min-width:190px">${cotFld('Enviar um TESTE para (só você recebe)','<input id="emTeste" value="'+esc((EU&&EU.email)||'')+'" placeholder="seu@email.com" style="width:100%">')}</div>
        <button class="btn-ghost" style="padding:7px 13px" onclick="cotEmailTeste()"><span class="material-icons" style="font-size:15px;vertical-align:-3px">outbox</span> Enviar teste</button>
        <button class="btn-prim" style="padding:7px 15px;font-weight:700" onclick="cotEmailDisparar()" ${g.configurada?'':'disabled style=\"opacity:.5\" title=\"configure a conta em Configurações › E-mail\"'}><span class="material-icons" style="font-size:16px;vertical-align:-3px">send</span> Disparar p/ ${(g.destinatarios||[]).filter(d=>d.tem_email).length} fornecedor(es)</button>
      </div>
      <div class="dmini" style="margin-top:6px">Cada fornecedor recebe individualmente (sem cópia). Faça o teste pra você antes de disparar.</div>
      ${cfg.is_admin?`<details style="margin-top:14px;border:1px solid var(--line);border-radius:10px;padding:8px 10px"><summary style="cursor:pointer;font-size:12px;color:var(--muted)"><span class="material-icons" style="font-size:14px;vertical-align:-3px">settings</span> Conta de envio (admin) — ${cfg.configurada?'<b style="color:var(--ok)">configurada ✓</b>':'<b style="color:var(--pend)">falta a senha</b>'} <span class="muted">· também em Configurações › E-mail</span></summary>
        <div style="display:grid;grid-template-columns:1fr 90px;gap:8px;margin-top:8px">${cotFld('Servidor SMTP','<input id="emHost" value="'+esc(cfg.host||'')+'" style="width:100%">')}${cotFld('Porta','<input id="emPort" type="number" value="'+esc(cfg.port||465)+'" style="width:100%">')}</div>
        <div style="display:grid;grid-template-columns:1fr;gap:8px;margin-top:6px">${cotFld('Usuário (e-mail)','<input id="emUser" value="'+esc(cfg.user||'')+'" style="width:100%">')}${cotFld('Senha (fica só no servidor; vazio mantém a atual)','<input id="emSenha" type="password" autocomplete="new-password" placeholder="••••••••" style="width:100%">')}</div>
        <div style="margin-top:8px"><button class="btn-prim" style="padding:5px 12px" onclick="cotEmailConfigSalvar()">Salvar conta</button></div></div></details>`:''}`);
  }catch(e){ ov.innerHTML=shell('<div class="empty">Falha ao montar o e-mail.</div>'); }
}
function cotEmailBody(){ return {cotacao_id:(COT.cur&&COT.cur.cotacao&&COT.cur.cotacao.id)||0, assunto:(document.getElementById('emAssunto')||{}).value||'', corpo:(document.getElementById('emCorpo')||{}).value||''}; }
async function cotEmailConfigSalvar(){ const g=id=>((document.getElementById(id)||{}).value||'');
  try{ const r=await (await fetch('actions/email.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({acao:'config',me:EU&&EU.bitrix_id,host:g('emHost'),port:Number(g('emPort'))||465,user:g('emUser'),senha:g('emSenha')})})).json();
    if(r.error){toast(r.error);return;} toast('Conta salva'); cotEmailAbrir((COT.cur&&COT.cur.cotacao&&COT.cur.cotacao.id)); }catch(e){toast('Falha: '+e.message);} }
async function cotEmailTeste(){ const to=((document.getElementById('emTeste')||{}).value||'').trim(); if(!to){toast('Informe um e-mail para o teste');return;}
  const b=cotEmailBody(); toast('Enviando teste…');
  try{ const r=await (await fetch('actions/email.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(Object.assign({acao:'enviar',me:EU&&EU.bitrix_id,teste:to},b))})).json();
    if(r.error){toast(r.error);return;} toast(r.msg||'Teste enviado'); }catch(e){toast('Falha: '+e.message);} }
async function cotEmailDisparar(){ const b=cotEmailBody();
  const conv=(COT.cur&&COT.cur.convidados)||[]; const comEmail=conv.filter(c=>c.email&&String(c.email).trim()).length;
  if(!comEmail){ toast('Nenhum fornecedor com e-mail preenchido'); return; }
  if(!confirm('Disparar este e-mail INDIVIDUALMENTE para '+comEmail+' fornecedor(es)?\nCada um recebe a sua própria cópia. Isso envia de verdade.')) return;
  toast('Disparando…');
  try{ const r=await (await fetch('actions/email.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(Object.assign({acao:'enviar',me:EU&&EU.bitrix_id},b))})).json();
    if(r.error){toast(r.error);return;}
    const ov=document.getElementById('emailOverlay'); if(ov)ov.remove();
    toast(r.enviados+' enviado(s)'+((r.falhas&&r.falhas.length)?' · '+r.falhas.length+' falha(s)':''));
    if(r.falhas&&r.falhas.length) setTimeout(()=>alert('Falhas:\n'+r.falhas.join('\n')),300);
    cotOpen(b.cotacao_id);
  }catch(e){toast('Falha: '+e.message);} }
/* ===== ITEM B: cadastrar proposta a partir de um PDF/print SEM escolher fornecedor — IA lê, IDENTIFICA o fornecedor e preenche ===== */
function cotPropIAAbrir(){ COT.propIA={files:[],busy:false}; let ov=document.getElementById('propiaOverlay');
  if(!ov){ ov=document.createElement('div'); ov.id='propiaOverlay'; ov.style.cssText='position:fixed;inset:0;background:rgba(15,25,20,.42);z-index:9999;display:flex;align-items:center;justify-content:center;padding:20px'; document.body.appendChild(ov); }
  document.addEventListener('paste',cotPropIAPaste); cotPropIARender(); }
function cotPropIAFechar(){ const ov=document.getElementById('propiaOverlay'); if(ov)ov.remove(); document.removeEventListener('paste',cotPropIAPaste); COT.propIA=null; COT.propIAres=null; }
function cotPropIAPaste(e){ if(!COT.propIA)return; const items=((e.clipboardData||{}).items)||[]; let n=0; for(const it of items){ if(it.kind==='file'){ const f=it.getAsFile(); if(f){ COT.propIA.files.push(f.name?f:new File([f],'print-'+Date.now()+'.png',{type:f.type||'image/png'})); n++; } } } if(n){ e.preventDefault(); cotPropIARender(); } }
function cotPropIADrop(e){ e.preventDefault(); if(!COT.propIA)return; for(const f of (((e.dataTransfer||{}).files)||[]))COT.propIA.files.push(f); cotPropIARender(); }
function cotPropIAPick(input){ if(!COT.propIA)return; for(const f of (input.files||[]))COT.propIA.files.push(f); input.value=''; cotPropIARender(); }
function cotPropIARender(){ const s=COT.propIA, ov=document.getElementById('propiaOverlay'); if(!s||!ov)return; ov.onclick=()=>{ if(!s.busy)cotPropIAFechar(); };
  ov.innerHTML=`<div style="background:#fff;border-radius:14px;padding:18px;box-shadow:0 12px 44px rgba(0,0,0,.22);width:100%;max-width:480px" onclick="event.stopPropagation()">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px"><b style="font-size:14px"><span class="material-icons" style="font-size:16px;vertical-align:-3px;color:var(--verde-d)">auto_awesome</span> Cadastrar proposta com IA</b><span onclick="cotPropIAFechar()" class="material-icons" style="cursor:pointer;color:var(--muted)">close</span></div>
    <div class="muted" style="font-size:11.5px;margin-bottom:10px">Anexe o PDF/print da proposta — a IA lê, <b>identifica o fornecedor</b> e preenche os preços (rascunho p/ conferir). Não precisa ter convidado o fornecedor.</div>
    ${s.busy?'<div class="dempty">🧠 lendo a proposta e identificando o fornecedor…</div>':`
    <label ondragover="event.preventDefault()" ondrop="cotPropIADrop(event)" style="display:block;border:2px dashed var(--line);border-radius:12px;padding:20px;text-align:center;cursor:pointer;background:#fafbfb">
      <span class="material-icons" style="font-size:28px;color:var(--verde)">upload_file</span>
      <div style="font-size:12.5px;margin-top:4px">Arraste, <b>cole (Ctrl+V)</b> ou clique — PDF, Excel ou imagem</div>
      <input type="file" accept=".pdf,.xlsx,.xls,image/png,image/jpeg,application/pdf" multiple style="display:none" onchange="cotPropIAPick(this)"></label>
    <div style="margin-top:10px">${s.files.length?s.files.map((f,i)=>`<div style="display:flex;align-items:center;gap:7px;padding:3px 0;font-size:12px"><span class="material-icons" style="font-size:15px;color:var(--muted)">${cotAnexoIcon(f.type,f.name)}</span><span style="flex:1;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">${esc(f.name)}</span><span onclick="COT.propIA.files.splice(${i},1);cotPropIARender()" class="material-icons" style="cursor:pointer;color:var(--pend);font-size:15px">close</span></div>`).join(''):'<div class="dmini">Nenhum arquivo ainda.</div>'}</div>
    <div style="display:flex;gap:8px;justify-content:flex-end;margin-top:12px"><button class="btn-ghost" onclick="cotPropIAFechar()">Cancelar</button><button class="btn-prim" onclick="cotPropIALer()" ${s.files.length?'':'disabled style=\"opacity:.5\"'}><span class="material-icons" style="font-size:15px;vertical-align:-3px">auto_awesome</span> Ler com IA</button></div>`}
  </div>`; }
async function cotPropIALer(){ const s=COT.propIA; if(!s||!s.files.length||s.busy)return; s.busy=true; cotPropIARender();
  try{ const ids=[]; for(const f of s.files){ const r=await cotUploadAnexoFile(f,null,''); if(r&&r.id)ids.push(r.id); }
    if(!ids.length){ toast('Falha ao anexar'); s.busy=false; cotPropIARender(); return; }
    const rr=await (await fetch('actions/cotacao_ia.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({acao:'preencher',me:EU&&EU.bitrix_id,cotacao_id:COT.cur.cotacao.id,anexo_ids:ids})})).json();
    if(rr.error){ toast(rr.error); s.busy=false; cotPropIARender(); return; }
    document.removeEventListener('paste',cotPropIAPaste); COT.propIA=null;
    cotPropIAForn((rr.draft&&rr.draft.fornecedor)||rr.fornecedor||{}, rr.draft||{}, ids, rr);   // o fornecedor vem DENTRO do draft
  }catch(e){ toast('Falha: '+e.message); s.busy=false; cotPropIARender(); } }
const _dig=s=>String(s||'').replace(/\D/g,'');
async function cotPropIAForn(fornData, draft, anexoIds, meta){
  const cnpj=_dig(fornData.cnpj), nome=(fornData.nome||'').trim(); const cands=[], seen={};
  const push=arr=>(arr||[]).forEach(f=>{ if(!seen[f.id]){ seen[f.id]=1; cands.push(f); } });
  try{ if(cnpj.length>=8){ const r=await (await fetch('actions/fornecedores.php?q='+encodeURIComponent(cnpj)+'&limit=8')).json(); push((r.fornecedores||[]).filter(f=>_dig(f.cnpj)===cnpj)); } }catch(e){}
  try{ if(nome){ const r=await (await fetch('actions/fornecedores.php?q='+encodeURIComponent(nome)+'&limit=8')).json(); push(r.fornecedores||[]); } }catch(e){}
  COT.propIAres={fornData,draft,anexoIds,meta,cands,sel:undefined}; cotPropIAResRender();
}
function cotPropIAResRender(){ const R=COT.propIAres; if(!R)return; let ov=document.getElementById('propiaOverlay');
  if(!ov){ ov=document.createElement('div'); ov.id='propiaOverlay'; ov.style.cssText='position:fixed;inset:0;background:rgba(15,25,20,.42);z-index:9999;display:flex;align-items:center;justify-content:center;padding:20px'; document.body.appendChild(ov); }
  ov.onclick=()=>ov.remove();
  const fd=R.fornData, cnpjD=_dig(fd.cnpj), exact=R.cands.find(c=>_dig(c.cnpj)===cnpjD && cnpjD.length>=8);
  if(R.sel===undefined) R.sel = exact?('id:'+exact.id):(R.cands.length?('id:'+R.cands[0].id):'novo');
  ov.innerHTML=`<div style="background:#fff;border-radius:14px;padding:18px;box-shadow:0 12px 44px rgba(0,0,0,.22);width:100%;max-width:520px;max-height:85vh;overflow:auto" onclick="event.stopPropagation()">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:4px"><b style="font-size:14px">Qual é o fornecedor desta proposta?</b><span onclick="document.getElementById('propiaOverlay').remove()" class="material-icons" style="cursor:pointer;color:var(--muted)">close</span></div>
    <div class="muted" style="font-size:11.5px;margin-bottom:10px">A IA leu: <b>${esc(fd.nome||'—')}</b>${fd.cnpj?' · CNPJ '+esc(fd.cnpj):''}${fd.telefone?' · '+esc(fd.telefone):''}${fd.email?' · '+esc(fd.email):''}</div>
    ${R.cands.length?`<div style="font-size:11px;font-weight:700;color:var(--muted);margin-bottom:4px">FORNECEDORES PARECIDOS NA BASE</div>${R.cands.map(c=>`<label style="display:flex;align-items:center;gap:8px;padding:5px 2px;font-size:12.5px;cursor:pointer"><input type="radio" name="piaf" ${R.sel==='id:'+c.id?'checked':''} onchange="COT.propIAres.sel='id:${c.id}';cotPropIAResRender()"><span style="flex:1">${esc(c.nome)}${c.cnpj?` <span class="muted">· ${esc(c.cnpj)}</span>`:''}${(_dig(c.cnpj)===cnpjD&&cnpjD.length>=8)?' <span class="dchip" style="background:var(--ok)">CNPJ igual</span>':''}</span></label>`).join('')}`:'<div class="dmini">Nenhum fornecedor parecido na base.</div>'}
    <label style="display:flex;align-items:center;gap:8px;padding:6px 2px;font-size:12.5px;cursor:pointer;border-top:1px solid var(--line);margin-top:6px"><input type="radio" name="piaf" ${R.sel==='novo'?'checked':''} onchange="COT.propIAres.sel='novo';cotPropIAResRender()"><b style="color:var(--verde-d)">➕ Cadastrar novo fornecedor</b></label>
    ${R.sel==='novo'?`<div style="background:#fafbfb;border:1px solid var(--line);border-radius:10px;padding:10px;margin-top:6px;display:grid;grid-template-columns:1fr 1fr;gap:8px">
      ${cotFld('Nome / razão social *','<input id="piaN" value="'+esc(fd.nome||'')+'" style="width:100%">','grid-column:1/3')}
      ${cotFld('CNPJ','<input id="piaC" value="'+esc(fd.cnpj||'')+'" style="width:100%">')}
      ${cotFld('Categoria','<input id="piaCat" value="'+esc((COT.cur.cotacao||{}).categoria||'')+'" style="width:100%">')}
      ${cotFld('Telefone','<input id="piaT" value="'+esc(fd.telefone||'')+'" style="width:100%">')}
      ${cotFld('E-mail','<input id="piaE" value="'+esc(fd.email||'')+'" style="width:100%">')}</div>`:''}
    <div style="display:flex;gap:8px;justify-content:flex-end;margin-top:12px"><button class="btn-ghost" onclick="document.getElementById('propiaOverlay').remove()">Cancelar</button><button class="btn-prim" onclick="cotPropIAResConfirm()"><span class="material-icons" style="font-size:15px;vertical-align:-3px">check</span> Continuar</button></div>
  </div>`; }
async function cotPropIAResConfirm(){ const R=COT.propIAres; if(!R)return; let fid=null, fnome='';
  if(R.sel==='novo'){ const g=id=>((document.getElementById(id)||{}).value||'').trim(); fnome=g('piaN'); if(!fnome){toast('Informe o nome do fornecedor');return;}
    try{ const r=await (await fetch('actions/fornecedores.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({acao:'fornecedor_salvar',me:EU&&EU.bitrix_id,nome:fnome,cnpj:g('piaC'),categoria:g('piaCat'),telefone:g('piaT'),email:g('piaE')})})).json();
      if(r.error){toast(r.error);return;} fid=r.id; toast('Fornecedor cadastrado'); }catch(e){toast('Falha ao cadastrar: '+e.message);return;}
  } else { const id=Number(String(R.sel).split(':')[1]); const c=R.cands.find(x=>x.id===id); if(!c){toast('Selecione o fornecedor');return;} fid=c.id; fnome=c.nome; }
  try{
    const jaConv=(COT.cur.convidados||[]).some(cv=>String(cv.fornecedor_id)===String(fid)||(cv.fornecedor_nome||'').trim().toLowerCase()===fnome.trim().toLowerCase());
    if(!jaConv) await fetch('actions/cotacoes.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({acao:'convidar',me:EU&&EU.bitrix_id,cotacao_id:COT.cur.cotacao.id,convidados:[{id:fid,nome:fnome}]})});
    if(R.anexoIds&&R.anexoIds.length) await fetch('actions/cotacao_anexo.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({acao:'set_fornecedor',me:EU&&EU.bitrix_id,cotacao_id:COT.cur.cotacao.id,ids:R.anexoIds,fornecedor_id:fid,fornecedor_nome:fnome})});
    const draft=R.draft, meta=R.meta; cotPropIAFechar();
    await cotOpen(COT.cur.cotacao.id);
    cotIAAplicar(fnome, draft, meta);
  }catch(e){ toast('Falha: '+e.message); }
}
async function cotDelAnexo(id){ if(!confirm('Excluir este anexo?'))return;
  try{ await fetch('actions/cotacao_anexo.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({acao:'excluir',me:EU&&EU.bitrix_id,id})}); cotOpen(COT.cur.cotacao.id); }catch(e){toast('Falha');} }
/* --- Concorrência: convidar / desconvidar / lançar proposta de um convidado --- */
function cotPropostaDe(ci){ const cf=((COT.cur||{}).convidados||[])[ci]; if(!cf)return; cotProposta(0); COT.prop.fornecedor_nome=cf.fornecedor_nome; cotRenderProposta(); cotFornDatalist(((COT.cur||{}).cotacao||{}).categoria); }
async function cotDesconvidar(id){ if(!confirm('Tirar este fornecedor da concorrência?'))return;
  try{ await fetch('actions/cotacoes.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({acao:'desconvidar',me:EU&&EU.bitrix_id,id})}); cotOpen(COT.cur.cotacao.id); }catch(e){toast('Falha');} }
let _cotCB;
function cotConvBuscaInput(){ clearTimeout(_cotCB); const q=(document.getElementById('cotConvBusca').value||'').trim(), box=document.getElementById('cotConvSug'); if(!box)return; if(q.length<2){box.style.display='none';box.innerHTML='';return;}
  _cotCB=setTimeout(async()=>{ try{ const d=await (await fetch('actions/fornecedores.php?q='+encodeURIComponent(q)+'&limit=14')).json(); COT.convBusca=d.fornecedores||[];
    box.innerHTML=COT.convBusca.length?COT.convBusca.map((f,i)=>`<div onclick="cotConvidar(${i})" style="padding:7px 10px;cursor:pointer;font-size:12.5px;border-bottom:1px solid #f1f3f2" onmouseover="this.style.background='#eff7f1'" onmouseout="this.style.background=''"><b>${esc(f.nome)}</b> <span class="muted" style="font-size:10.5px">· ${esc(f.categoria||'')}${f.cidade?' · '+esc(f.cidade):''}</span></div>`).join(''):'<div class="dmini" style="padding:8px">nenhum fornecedor casa "'+esc(q)+'"</div>'; box.style.display='block';
  }catch(e){} },300); }
async function cotConvidar(idx){ const f=(COT.convBusca||[])[idx]; if(!f)return;
  try{ const r=await (await fetch('actions/cotacoes.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({acao:'convidar',me:EU&&EU.bitrix_id,cotacao_id:COT.cur.cotacao.id,convidados:[{id:f.id,nome:f.nome,categoria:f.categoria,contato:f.contato,email:f.email,telefone:f.telefone}]})})).json();
    if(r.error){toast(r.error);return;} cotOpen(COT.cur.cotacao.id);
  }catch(e){toast('Falha: '+e.message);} }
document.addEventListener('click',e=>{ if(!(e.target.closest&&e.target.closest('#cotConvBusca,#cotConvSug'))){ const b=document.getElementById('cotConvSug'); if(b) b.style.display='none'; } });

/* ---------- Modelos de Carta Convite (sub-aba) + geração ---------- */
const CART={modelos:[],config:null,servicos:[],mode:'list',cur:null,gen:null};
async function cartaLoad(){
  const w=document.getElementById('cotwrap'); w.innerHTML='<div class="dempty">Carregando modelos de carta…</div>';
  try{ const j=await (await fetch('actions/cartas.php?me='+encodeURIComponent((EU&&EU.bitrix_id)||''))).json();
    CART.modelos=j.modelos||[]; CART.config=j.config||null; CART.servicos=j.servicos||[]; CART.mode='list'; cartaRender();
  }catch(e){ w.innerHTML='<div class="empty">Falha ao carregar modelos.</div>'; }
}
function cartaServNome(id){ if(!id)return ''; const s=CART.servicos.find(x=>String(x.id)===String(id)); return s?s.nome:('#'+id); }
function cartaRender(){
  if(CART.mode==='edit') return cartaRenderEdit();
  if(CART.mode==='config') return cartaRenderConfig();
  const w=document.getElementById('cotwrap');
  const rows=CART.modelos.map(m=>`<tr style="cursor:pointer" onclick="cartaEditar(${m.id})">
    <td><b>${esc(m.servico_nome||'')}</b></td><td class="muted" style="font-size:12px">${esc(m.tipo||'')}</td>
    <td>${m.servico_id?`<span class="dchip" style="background:#eef4f0;color:var(--verde-d)">${esc(cartaServNome(m.servico_id))}</span>`:'<span class="dchip" style="background:var(--pend)">atribuir serviço</span>'}</td>
    <td class="muted" style="font-size:11px">${esc(m.pes_ref||'—')}</td>
    <td style="text-align:center">${Number(m.tem_medicao)?'<span title="tem critérios de medição" style="color:var(--ok);font-weight:700">✓</span>':'<span style="color:var(--pend)">—</span>'}</td>
    <td style="text-align:right">${m.origem==='seed'?'<span class="dchip" style="background:#8a9299;font-size:10px">seed IA</span>':'<span class="dchip" style="background:var(--ok);font-size:10px">curado</span>'} <span class="material-icons" style="color:var(--muted);vertical-align:-4px">chevron_right</span></td></tr>`).join('');
  w.innerHTML=`<div class="panel" style="margin-bottom:10px"><div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px">
      <div><b style="font-size:14px">Modelos de carta convite</b> <span class="muted" style="font-size:11.5px">— o conteúdo padrão por serviço (escopo, critérios de medição, equalização). Editar aqui vale p/ toda cotação do serviço.</span></div>
      ${IS_ADMIN?`<button class="btn-ghost" style="padding:6px 12px" onclick="cartaConfigAbrir()"><span class="material-icons" style="font-size:15px;vertical-align:-3px">gavel</span> Bloco padrão Caprem</button>`:''}
    </div></div>
    <div class="wrap"><table><thead><tr><th>Serviço (modelo)</th><th>Tipo</th><th>Serviço vinculado</th><th>PES</th><th style="text-align:center">Medição</th><th></th></tr></thead>
    <tbody>${rows||'<tr><td colspan="6" class="empty">Nenhum modelo cadastrado.</td></tr>'}</tbody></table></div>`;
}
async function cartaEditar(id){
  try{ const j=await (await fetch('actions/cartas.php?modelo='+id+'&me='+encodeURIComponent((EU&&EU.bitrix_id)||''))).json();
    if(!j.modelo){toast('Modelo não encontrado');return;} CART.cur=j.modelo; CART.mode='edit'; cartaRender();
  }catch(e){toast('Falha');}
}
function cartaRenderEdit(){
  const m=CART.cur, w=document.getElementById('cotwrap'), L=a=>(a||[]).join('\n');
  const servOpts='<option value="">— nenhum (atribuir) —</option>'+CART.servicos.map(s=>`<option value="${s.id}" ${String(s.id)===String(m.servico_id)?'selected':''}>${esc(s.nome)}</option>`).join('');
  w.innerHTML=`<div class="panel">
    <div style="display:flex;align-items:center;gap:8px;margin-bottom:12px;flex-wrap:wrap"><button class="btn-ghost" onclick="CART.mode='list';cartaRender()"><span class="material-icons" style="font-size:16px;vertical-align:-3px">arrow_back</span> Voltar</button>
      <b style="font-size:15px">Modelo · ${esc(m.servico_nome||'')}</b> ${m.origem==='seed'?'<span class="dchip" style="background:#8a9299;font-size:10px">seed IA — confira e cure</span>':''}</div>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px">
      ${cotFld('Nome do serviço (modelo)','<input id="cm_nome" value="'+esc(m.servico_nome||'')+'">')}
      ${cotFld('Serviço vinculado <span class="muted" style="font-weight:400">(usado na geração da carta)</span>','<select id="cm_serv">'+servOpts+'</select>')}
      ${cotFld('Tipo','<input id="cm_tipo" value="'+esc(m.tipo||'')+'" placeholder="Mão de obra / Empreitada / Material + MO…">')}
      ${cotFld('PES (procedimento de inspeção)','<input id="cm_pes" value="'+esc(m.pes_ref||'')+'">')}
    </div>
    ${cotFld('Objeto (1-2 frases)','<textarea id="cm_obj" rows="2" style="width:100%">'+esc(m.objeto||'')+'</textarea>','margin-top:8px')}
    ${cotFld('Normas de referência (uma por linha)','<textarea id="cm_norma" rows="2" style="width:100%">'+esc(L(m.norma_referencia))+'</textarea>','margin-top:8px')}
    ${cotFld('Escopo — incluso / da obra (um por linha)','<textarea id="cm_escopo" rows="7" style="width:100%">'+esc(L(m.escopo))+'</textarea>','margin-top:8px')}
    ${cotFld('Critérios de medição (um por linha) — o coração','<textarea id="cm_med" rows="7" style="width:100%">'+esc(L(m.criterios_medicao))+'</textarea>','margin-top:8px')}
    ${cotFld('Campos de equalização — o que a proposta declara (um por linha)','<textarea id="cm_eq" rows="5" style="width:100%">'+esc(L(m.equalizacao_campos))+'</textarea>','margin-top:8px')}
    ${cotFld('Quantitativos-modelo (item | unidade, um por linha)','<textarea id="cm_quant" rows="4" style="width:100%">'+esc((m.quantitativos_modelo||[]).map(q=>(q.item||'')+' | '+(q.unidade||'')).join('\n'))+'</textarea>','margin-top:8px')}
    ${cotFld('Observações','<textarea id="cm_obs" rows="2" style="width:100%">'+esc(m.observacoes||'')+'</textarea>','margin-top:8px')}
    <div style="margin-top:12px"><button class="btn-prim" onclick="cartaSalvar()"><span class="material-icons" style="font-size:16px;vertical-align:-3px">check</span> Salvar modelo</button></div>
  </div>`;
}
async function cartaSalvar(){
  const g=id=>((document.getElementById(id)||{}).value||''), lines=id=>g(id).split(/\r?\n/).map(s=>s.trim()).filter(Boolean);
  const quant=g('cm_quant').split(/\r?\n/).map(l=>{const p=l.split('|');return (p[0]||'').trim()?{item:p[0].trim(),unidade:(p[1]||'').trim()}:null;}).filter(Boolean);
  const modelo={id:CART.cur.id,servico_nome:g('cm_nome'),servico_id:Number(g('cm_serv'))||null,tipo:g('cm_tipo'),pes_ref:g('cm_pes'),objeto:g('cm_obj'),norma_referencia:lines('cm_norma'),escopo:lines('cm_escopo'),criterios_medicao:lines('cm_med'),equalizacao_campos:lines('cm_eq'),quantitativos_modelo:quant,observacoes:g('cm_obs')};
  try{ const r=await (await fetch('actions/cartas.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({acao:'save_modelo',me:EU&&EU.bitrix_id,modelo})})).json();
    if(r.error){toast(r.error);return;} toast('Modelo salvo'); cartaLoad(); }catch(e){toast('Falha: '+e.message);}
}
function cartaConfigAbrir(){ CART.mode='config'; cartaRender(); }
function cartaRenderConfig(){
  const c=CART.config||{}, s=c.seguranca||{}, w=document.getElementById('cotwrap'), L=a=>(a||[]).join('\n');
  w.innerHTML=`<div class="panel">
    <div style="display:flex;align-items:center;gap:8px;margin-bottom:6px"><button class="btn-ghost" onclick="CART.mode='list';cartaRender()"><span class="material-icons" style="font-size:16px;vertical-align:-3px">arrow_back</span> Voltar</button><b style="font-size:15px">Bloco padrão Caprem</b></div>
    <div class="dmini" style="margin-bottom:10px">O texto FIXO que entra em toda carta (obrigações, SST, julgamento, faturamento, contatos). Editar aqui vale para todas.</div>
    ${cotFld('Obrigações da contratada (uma por linha)','<textarea id="cc_obr" rows="6" style="width:100%">'+esc(L(c.obrigacoes))+'</textarea>')}
    <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:10px;margin-top:8px">
      ${cotFld('SST · a cada medição','<textarea id="cc_s1" rows="4" style="width:100%">'+esc(s.a_cada_medicao||'')+'</textarea>')}
      ${cotFld('SST · da empresa','<textarea id="cc_s2" rows="4" style="width:100%">'+esc(s.da_empresa||'')+'</textarea>')}
      ${cotFld('SST · dos empregados','<textarea id="cc_s3" rows="4" style="width:100%">'+esc(s.dos_empregados||'')+'</textarea>')}
    </div>
    ${cotFld('SST · nota (EPI / atraso de pagamento)','<textarea id="cc_snota" rows="2" style="width:100%">'+esc(s.nota||'')+'</textarea>','margin-top:8px')}
    ${cotFld('Julgamento das propostas (uma por linha)','<textarea id="cc_julg" rows="3" style="width:100%">'+esc(L(c.julgamento))+'</textarea>','margin-top:8px')}
    ${cotFld('Faturamento e pagamento','<textarea id="cc_fat" rows="3" style="width:100%">'+esc(c.faturamento||'')+'</textarea>','margin-top:8px')}
    <div class="dmini" style="margin-top:8px">O <b>Responsável do Departamento de Suprimentos</b> na carta é sempre o usuário que criou a cotação — não precisa configurar aqui.</div>
    ${cotFld('Declaração final','<textarea id="cc_decl" rows="2" style="width:100%">'+esc(c.declaracao||'')+'</textarea>','margin-top:8px')}
    ${cotFld('Validade da proposta (dias)','<input id="cc_val" type="number" style="width:120px" value="'+esc(c.validade_dias||30)+'">','margin-top:8px')}
    <div style="margin-top:12px"><button class="btn-prim" onclick="cartaConfigSalvar()">Salvar bloco Caprem</button></div>
  </div>`;
}
async function cartaConfigSalvar(){
  const g=id=>((document.getElementById(id)||{}).value||''), lines=id=>g(id).split(/\r?\n/).map(s=>s.trim()).filter(Boolean);
  const config={obrigacoes:lines('cc_obr'),seguranca:{a_cada_medicao:g('cc_s1'),da_empresa:g('cc_s2'),dos_empregados:g('cc_s3'),nota:g('cc_snota')},julgamento:lines('cc_julg'),faturamento:g('cc_fat'),contatos:{gestor_suprimentos:''},declaracao:g('cc_decl'),validade_dias:Number(g('cc_val'))||30};
  try{ const r=await (await fetch('actions/cartas.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({acao:'save_config',me:EU&&EU.bitrix_id,config})})).json();
    if(r.error){toast(r.error);return;} toast('Bloco Caprem salvo'); cartaLoad(); }catch(e){toast('Falha');}
}
/* --- GERAÇÃO da carta a partir de uma cotação --- */
async function cartaGerar(cid){
  const w=document.getElementById('cotwrap'); w.innerHTML='<div class="dempty">Montando a carta…</div>';
  const enc=encodeURIComponent((EU&&EU.bitrix_id)||'');
  try{ const g=await (await fetch('actions/cartas.php?gerar='+cid+'&me='+enc)).json();
    if(g.error){w.innerHTML='<div class="empty">'+esc(g.error)+'</div>';return;} CART.gen=g; CART.savedHTML=null;
    // já existe carta salva nesta cotação? carrega a ÚLTIMA pra continuar editando (não refaz do zero)
    const gl=await (await fetch('actions/cartas.php?geradas='+cid+'&me='+enc)).json();
    if(gl.geradas&&gl.geradas.length){ const one=await (await fetch('actions/cartas.php?gerada='+gl.geradas[0].id+'&me='+enc)).json(); if(one.gerada&&one.gerada.html) CART.savedHTML=one.gerada.html; }
    cartaRenderGerar();
  }catch(e){ w.innerHTML='<div class="empty">Falha ao gerar.</div>'; }
}
function cartaGerarZero(){ if(!confirm('Descartar a carta salva e gerar uma NOVA a partir do modelo? (as suas edições serão perdidas)'))return; CART.savedHTML=null; cartaRenderGerar(); }
function cvList(a){ return (a&&a.length)?'<ul>'+a.map(x=>'<li>'+esc(x)+'</li>').join('')+'</ul>':''; }
function cartaMontarHTML(g){
  if(g&&g.cotacao&&g.cotacao.tipo==='material') return cartaMontarHTMLMaterial(g);   // cotação nascida de solicitação → carta de COTAÇÃO (material)
  const c=g.cotacao||{}, m=g.modelo||null, cf=g.config||{}, s=cf.seguranca||{};
  const titulo=(c.servico_nome||(m&&m.servico_nome)||c.titulo||'').replace(/^(Execução de|Execução|MO)\s*/i,'').trim()||c.titulo||'Serviço';
  const norma=m&&m.norma_referencia&&m.norma_referencia.length?m.norma_referencia.join(' / '):'—';
  const eqCampos=[...new Set([...(m?m.equalizacao_campos:[]),...(g.equalizacao_cotacao||[])])];
  const quant=g.quantitativos||[];
  let h=`<div class="cvdoc" id="cvInner" contenteditable="true">
    <div class="cvmast"><div class="br">◤ Caprem Construtora · Engenharia &amp; Fundações</div>
      <div class="kick">Carta Convite</div><h2>${esc(titulo)}</h2></div>
    <div class="cvinfo">
      <div><div class="k">Obra</div><div>${esc(c.obra_nome||'—')}</div></div>
      <div><div class="k">Norma de referência</div><div>${esc(norma)}</div></div>
      <div><div class="k">Procedimento (PES)</div><div>${esc((m&&m.pes_ref)||'—')}</div></div>
    </div>
    <div class="cvbody">`;
  if(!m) h+=`<div class="cvnote cv-noprint">⚠️ Não há modelo vinculado a este serviço — a carta saiu só com os blocos padrão + quantitativos. Atribua um modelo na aba <b>Modelos de carta</b>.</div>`;
  // 00 Apresentação
  h+=`<div class="cvsec"><div class="cvsh"><span class="cvsn">00</span><span class="cvst">Apresentação</span></div>
    <p>${esc((m&&m.objeto)||c.descricao||'')||'<span class="cvph">[objeto — descreva o serviço]</span>'}</p></div>`;
  // 01 Especificação
  h+=`<div class="cvsec"><div class="cvsh"><span class="cvsn">01</span><span class="cvst">Especificação e critérios de medição</span></div>
    ${m&&m.escopo.length?'<p><b>Escopo</b></p>'+cvList(m.escopo):''}
    ${m&&m.criterios_medicao.length?'<p><b>Critérios de medição</b></p>'+cvList(m.criterios_medicao):'<p class="cvph">[defina o escopo e os critérios de medição no modelo do serviço]</p>'}</div>`;
  // 02 Quantitativos
  h+=`<div class="cvsec"><div class="cvsh"><span class="cvsn">02</span><span class="cvst">Quantitativos</span></div>
    <table><thead><tr><th>Item</th><th style="width:70px">Unid.</th><th style="width:110px;text-align:right">Qtde</th></tr></thead><tbody>
    ${quant.length?quant.map(q=>`<tr><td>${esc(q.item||'')}</td><td>${esc(q.unidade||'')}</td><td style="text-align:right">${q.qtde!=null&&q.qtde!==''?esc(cotNum(q.qtde)):'—'}</td></tr>`).join(''):'<tr><td colspan="3" class="cvph">[itens da cotação]</td></tr>'}
    </tbody></table></div>`;
  // 03 Planilha de preços / equalização
  h+=`<div class="cvsec"><div class="cvsh"><span class="cvsn">03</span><span class="cvst">Planilha de preços e equalização</span></div>
    <p>A proponente deve cotar e declarar expressamente:</p>
    <table><thead><tr><th>Campo a declarar / cotar</th><th style="width:150px">Resposta da proponente</th></tr></thead><tbody>
    ${(eqCampos.length?eqCampos:['Preço unitário','Prazo de execução','Validade da proposta']).map(x=>`<tr><td>${esc(x)}</td><td style="color:#9aa">____________________</td></tr>`).join('')}
    </tbody></table></div>`;
  // 04 Obrigações
  h+=`<div class="cvsec"><div class="cvsh"><span class="cvsn">04</span><span class="cvst">Obrigações da contratada</span></div>${cvList(cf.obrigacoes)}</div>`;
  // 05 Segurança
  h+=`<div class="cvsec"><div class="cvsh"><span class="cvsn">05</span><span class="cvst">Segurança e documentação</span></div>
    <div class="cvgrid3"><div class="cvcard"><h5>A cada medição</h5><p>${esc(s.a_cada_medicao||'')}</p></div>
      <div class="cvcard"><h5>Da empresa</h5><p>${esc(s.da_empresa||'')}</p></div>
      <div class="cvcard"><h5>Dos empregados</h5><p>${esc(s.dos_empregados||'')}</p></div></div>
    ${s.nota?`<div class="cvnote">${esc(s.nota)}</div>`:''}</div>`;
  // 06 Julgamento
  h+=`<div class="cvsec"><div class="cvsh"><span class="cvsn">06</span><span class="cvst">Julgamento das propostas</span></div>${cvList(cf.julgamento)}</div>`;
  // 07 Faturamento
  h+=`<div class="cvsec"><div class="cvsh"><span class="cvsn">07</span><span class="cvst">Faturamento e pagamento</span></div><p>${esc(cf.faturamento||'')}</p></div>`;
  // 08 Contatos
  h+=`<div class="cvsec"><div class="cvsh"><span class="cvsn">08</span><span class="cvst">Esclarecimentos e contatos</span></div>
    <p><b>Responsável do Departamento de Suprimentos:</b> ${esc(c.criado_nome||'')}</p>
    <p><b>Validade da proposta:</b> mínimo ${esc(cf.validade_dias||30)} dias · <b>Distribuição:</b> <span class="cvph">__/__/____</span> · <b>Retorno até:</b> <span class="cvph">__/__/____</span></p>
    ${cf.declaracao?`<p style="font-style:italic;border-left:2px solid #cbb26a;padding-left:10px;color:#455">"${esc(cf.declaracao)}"</p>`:''}</div>`;
  h+=`</div></div>`;
  return h;
}
/* Carta de COTAÇÃO (material) — nasce de uma solicitação de compra. Mesmo visual da convite, mas sem cláusula de contrato:
   pede preço + condições comerciais, traz dados/CNPJ da obra e comprador responsável. */
function cartaMontarHTMLMaterial(g){
  const c=g.cotacao||{}, cf=g.config||{}, quant=g.quantitativos||[];
  const obra=c.obra_nome||'—', cnpj=c.obra_cnpj||'', endereco=c.obra_endereco||'', comp=c.comprador_resp||c.criado_nome||'', sc=c.num_solicitacao||'', validade=cf.validade_dias||30;
  const phCnpj='<span class="cvph">[preencha o CNPJ em Solicitações › Obras &amp; compradores]</span>';
  let h=`<div class="cvdoc" id="cvInner" contenteditable="true">
    <div class="cvmast"><div class="br">◤ Caprem Construtora · Engenharia &amp; Fundações</div>
      <div class="kick">Solicitação de Cotação</div><h2>${esc(obra)}</h2></div>
    <div class="cvinfo">
      <div><div class="k">Obra</div><div>${esc(obra)}</div></div>
      <div><div class="k">CNPJ da obra</div><div>${cnpj?esc(cnpj):phCnpj}</div></div>
      <div><div class="k">Nº da solicitação</div><div>${sc?esc(sc):'—'}</div></div>
    </div>
    <div class="cvbody">`;
  h+=`<div class="cvsec"><div class="cvsh"><span class="cvsn">00</span><span class="cvst">Apresentação</span></div>
    <p>Prezado fornecedor, a <b>Caprem Construtora</b> solicita a cotação dos materiais relacionados abaixo, destinados à obra <b>${esc(obra)}</b>. Pedimos a gentileza de preencher os preços e as condições comerciais e retornar esta cotação ao comprador responsável.</p></div>`;
  h+=`<div class="cvsec"><div class="cvsh"><span class="cvsn">01</span><span class="cvst">Itens para cotação</span></div>
    <table><thead><tr><th style="width:32px">#</th><th>Material / especificação</th><th style="width:54px">Unid.</th><th style="width:78px;text-align:right">Qtde</th><th style="width:108px;text-align:right">Preço unit. (R$)</th><th style="width:108px;text-align:right">Preço total (R$)</th></tr></thead><tbody>
    ${quant.length?quant.map((q,i)=>`<tr><td style="text-align:center">${i+1}</td><td>${esc(q.item||'')}${q.obs?`<div style="font-size:10px;color:#667;margin-top:2px">${esc(q.obs)}</div>`:''}</td><td>${esc(q.unidade||'')}</td><td style="text-align:right">${q.qtde!=null&&q.qtde!==''?esc(cotNum(q.qtde)):'—'}</td><td style="text-align:right;color:#9aa">__________</td><td style="text-align:right;color:#9aa">__________</td></tr>`).join(''):'<tr><td colspan="6" class="cvph">[itens da cotação]</td></tr>'}
    </tbody></table></div>`;
  const cond=['Prazo de entrega','Condição de pagamento','Validade da proposta','Frete (incluso / CIF / FOB)','Prazo de faturamento'];
  h+=`<div class="cvsec"><div class="cvsh"><span class="cvsn">02</span><span class="cvst">Condições comerciais (a informar pelo fornecedor)</span></div>
    <table><thead><tr><th>Condição</th><th style="width:200px">Resposta do fornecedor</th></tr></thead><tbody>
    ${cond.map(x=>`<tr><td>${esc(x)}</td><td style="color:#9aa">____________________</td></tr>`).join('')}
    </tbody></table></div>`;
  h+=`<div class="cvsec"><div class="cvsh"><span class="cvsn">03</span><span class="cvst">Dados para faturamento e entrega</span></div>
    <div class="cvgrid3"><div class="cvcard"><h5>Obra</h5><p>${esc(obra)}</p></div>
      <div class="cvcard"><h5>CNPJ da obra</h5><p>${cnpj?esc(cnpj):phCnpj}</p></div>
      <div class="cvcard"><h5>Nº da solicitação</h5><p>${sc?esc(sc):'—'}</p></div></div>
    <p style="margin-top:8px"><b>Endereço da obra:</b> ${endereco?esc(endereco):'<span class="cvph">[preencha o endereço em Solicitações › Obras &amp; compradores]</span>'}</p></div>`;
  h+=`<div class="cvsec"><div class="cvsh"><span class="cvsn">04</span><span class="cvst">Contato e retorno</span></div>
    <p><b>Comprador responsável:</b> ${comp?esc(comp):'<span class="cvph">[comprador]</span>'}</p>
    <p><b>Validade mínima da proposta:</b> ${esc(validade)} dias · <b>Retorno até:</b> <span class="cvph">__/__/____</span></p>
    ${cf.declaracao?`<p style="font-style:italic;border-left:2px solid #cbb26a;padding-left:10px;color:#455">"${esc(cf.declaracao)}"</p>`:''}</div>`;
  h+=`</div></div>`;
  return h;
}
function cartaRenderGerar(){
  const w=document.getElementById('cotwrap'), g=CART.gen, cid=g.cotacao.id;
  let bodyHTML;
  if(CART.savedHTML){ try{ const doc=new DOMParser().parseFromString(CART.savedHTML,'text/html'); const cv=doc.querySelector('#cvInner')||doc.querySelector('.cvdoc'); bodyHTML=cv?cv.outerHTML:cartaMontarHTML(g); }catch(e){ bodyHTML=cartaMontarHTML(g); } }
  else bodyHTML=cartaMontarHTML(g);
  w.innerHTML=`<div class="cv-noprint" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;margin-bottom:12px">
      <button class="btn-ghost" onclick="cotOpen(${cid})"><span class="material-icons" style="font-size:16px;vertical-align:-3px">arrow_back</span> Voltar ao mapa</button>
      <button class="btn-prim" style="padding:6px 13px" onclick="window.print()"><span class="material-icons" style="font-size:15px;vertical-align:-3px">print</span> Imprimir / PDF</button>
      <button class="btn-ghost" style="padding:6px 13px" onclick="cartaWord()"><span class="material-icons" style="font-size:15px;vertical-align:-3px">description</span> Baixar Word</button>
      <button class="btn-prim" style="padding:6px 13px" onclick="cartaAnexarGerada()"><span class="material-icons" style="font-size:15px;vertical-align:-3px">save</span> Salvar na cotação</button>
      ${CART.savedHTML?`<button class="btn-ghost" style="padding:6px 13px" onclick="cartaGerarZero()" title="descartar e refazer do modelo"><span class="material-icons" style="font-size:15px;vertical-align:-3px">refresh</span> Gerar do zero</button><span class="dchip" style="background:#eef4f0;color:var(--verde-d);font-size:10px">editando a carta salva</span>`:''}
      <span class="muted" style="font-size:11.5px">Edite o texto direto na carta (clique e digite) e clique <b>Salvar na cotação</b>.</span></div>
    <div id="cvGerada">${bodyHTML}</div>`;
  window.scrollTo(0,0);
}
function cartaExportHTML(){
  const inner=document.getElementById('cvInner'); if(!inner)return '';
  const css=[...document.styleSheets].map(ss=>{try{return [...ss.cssRules].filter(r=>/\.cv|cvdoc/.test(r.selectorText||'')).map(r=>r.cssText).join('')}catch(e){return''}}).join('');
  return `<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:w="urn:schemas-microsoft-com:office:word"><head><meta charset="utf-8"><style>${css}</style></head><body>${inner.outerHTML}</body></html>`;
}
function cartaWord(){
  const html=cartaExportHTML(); const blob=new Blob(['﻿'+html],{type:'application/msword'});
  const a=document.createElement('a'); a.href=URL.createObjectURL(blob);
  const isMat=CART.gen&&CART.gen.cotacao&&CART.gen.cotacao.tipo==='material';
  a.download=(isMat?'Carta_Cotacao_':'Carta_Convite_')+((CART.gen.cotacao.servico_nome||CART.gen.cotacao.titulo||(isMat?'cotacao':'servico')).replace(/[^\w]+/g,'_').slice(0,40))+'.doc';
  document.body.appendChild(a); a.click(); a.remove();
}
// jsPDF + html2canvas carregados SOB DEMANDA (CDN) — só quando gera o PDF da carta
let _pdfLibs=null;
function cotLoadPdfLibs(){ if(_pdfLibs)return _pdfLibs; _pdfLibs=new Promise((res,rej)=>{ let n=0; const done=()=>{ if(++n===2)res(true); };
  const a=document.createElement('script'); a.src='https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js'; a.onload=done; a.onerror=()=>rej(new Error('html2canvas não carregou'));
  const b=document.createElement('script'); b.src='https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js'; b.onload=done; b.onerror=()=>rej(new Error('jspdf não carregou'));
  document.head.appendChild(a); document.head.appendChild(b); }); return _pdfLibs; }
async function cotCartaPDFBlob(el){ await cotLoadPdfLibs();
  const canvas=await html2canvas(el,{scale:2,useCORS:true,backgroundColor:'#ffffff'});
  const jsPDF=(window.jspdf||{}).jsPDF; const pdf=new jsPDF('p','mm','a4');
  const pw=210, ph=297, iw=pw, ih=canvas.height*pw/canvas.width, img=canvas.toDataURL('image/jpeg',0.92);
  let left=ih, pos=0; pdf.addImage(img,'JPEG',0,pos,iw,ih); left-=ph;
  while(left>0){ pos=left-ih; pdf.addPage(); pdf.addImage(img,'JPEG',0,pos,iw,ih); left-=ph; }
  return pdf.output('blob'); }
async function cartaAnexarGerada(){
  const inner=document.getElementById('cvInner'); if(!inner)return;
  const html=cartaExportHTML();
  const isMat=CART.gen&&CART.gen.cotacao&&CART.gen.cotacao.tipo==='material', cid=CART.gen.cotacao.id;
  try{ const r=await (await fetch('actions/cartas.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({acao:'salvar_carta',me:EU&&EU.bitrix_id,cotacao_id:cid,servico_nome:CART.gen.cotacao.servico_nome||'',titulo:(isMat?'Carta de Cotação · ':'Carta Convite · ')+(CART.gen.cotacao.titulo||''),html})})).json();
    if(r.error){toast(r.error);return;} }catch(e){toast('Falha: '+e.message);return;}
  toast('Carta salva — gerando o PDF p/ anexo…');
  try{ const blob=await cotCartaPDFBlob(inner);
    const fd=new FormData(); fd.append('arquivo',new File([blob],'Carta de cotacao.pdf',{type:'application/pdf'})); fd.append('cotacao_id',cid); fd.append('fornecedor_nome','__CARTA__'); fd.append('me',(EU&&EU.bitrix_id)||'');
    const rr=await (await fetch('actions/cotacao_anexo.php',{method:'POST',body:fd})).json();
    toast(rr&&rr.id?'Carta salva + PDF pronto para o e-mail ✓':('Carta salva (o PDF do anexo falhou: '+((rr&&rr.error)||'?')+')'));
  }catch(e){ toast('Carta salva (não gerou o PDF: '+e.message+')'); }
}
/* ---------- Preços Tabelados (sub-aba) ---------- */
const PREC={tabelas:[],mode:'home',busca:'',grupos:[],cur:null,itens:[],insumos:[]};
function precMe(){ return encodeURIComponent((EU&&EU.bitrix_id)||''); }
async function precLoad(){
  const w=document.getElementById('cotwrap'); w.innerHTML='<div class="dempty">Carregando preços tabelados…</div>';
  try{ const j=await (await fetch('actions/precos.php?me='+precMe())).json(); PREC.tabelas=j.tabelas||[]; PREC.mode='home'; precRender(); }
  catch(e){ w.innerHTML='<div class="empty">Falha ao carregar.</div>'; }
}
function precVenc(o){ return o.vigente?'':'opacity:.45;text-decoration:line-through'; }
function precOfertaTbl(g){
  const ofs=g.ofertas.slice().sort((a,b)=>((a.preco==null?9e15:a.preco)-(b.preco==null?9e15:b.preco)));
  return `<table class="up-tbl" style="margin-top:6px"><thead><tr><th style="text-align:left">Fornecedor</th><th style="text-align:right">Preço</th><th>Unid.</th><th>Frete</th><th>Validade</th><th style="text-align:left">Observação</th></tr></thead><tbody>
    ${ofs.map((o,i)=>`<tr style="${precVenc(o)}"><td style="text-align:left">${i===0&&o.vigente?'🏆 ':''}${esc(o.fornecedor||'—')}${o.descricao_original&&o.descricao_original!==g.nome?`<div style="font-size:9px;color:#99a">"${esc(o.descricao_original)}"</div>`:''}</td>
      <td style="text-align:right;font-weight:700">${o.preco!=null?BRL(o.preco):'—'}</td><td>${esc(o.unidade||'')}</td>
      <td>${o.frete_incluso?'✓ incluso':'—'}</td><td style="white-space:nowrap">${o.validade_fim?D(o.validade_fim):'—'}</td>
      <td style="text-align:left;font-size:11px">${esc(o.obs||'')}</td></tr>`).join('')}</tbody></table>`;
}
function precRender(){
  if(PREC.mode==='nova') return precRenderNova();
  const w=document.getElementById('cotwrap');
  const tabs=PREC.tabelas.map(t=>`<tr><td><b>${esc(t.fornecedor_nome||'—')}</b>${t.titulo?` <span class="muted">· ${esc(t.titulo)}</span>`:''}</td>
    <td class="muted" style="font-size:12px">${t.validade_inicio?D(t.validade_inicio)+' – ':''}${t.validade_fim?D(t.validade_fim):'sem validade'}</td>
    <td style="text-align:center">${t.n_itens}</td><td>${Number(t.vigente)?'<span class="dchip" style="background:var(--ok);font-size:10px">vigente</span>':'<span class="dchip" style="background:var(--pend);font-size:10px">vencida</span>'}</td>
    <td class="muted" style="font-size:11px">${esc(t.observacao||'')}</td>
    <td style="text-align:right">${CAN_EDIT?`<button class="btn-ghost" style="padding:2px 8px" onclick="precEditar(${t.id})">Editar</button><button class="btn-ghost" style="padding:2px 8px;color:var(--pend)" onclick="precExcluir(${t.id})">×</button>`:''}</td></tr>`).join('');
  w.innerHTML=`<div class="panel" style="margin-bottom:10px">
      <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap"><b style="font-size:14px">Consultar preço tabelado</b>
        <div class="search" style="flex:1;min-width:240px"><span class="material-icons" style="color:var(--muted)">search</span><input id="precBusca" placeholder="Buscar insumo… (ex.: barra de aço, bloco cerâmico)" value="${esc(PREC.busca)}" oninput="precBuscarIn(this.value)"></div>
        ${CAN_EDIT?`<button class="btn-prim" style="padding:7px 13px" onclick="precNova()"><span class="material-icons" style="font-size:16px;vertical-align:-3px">add</span> Nova tabela</button>`:''}</div>
      <div id="precResult" style="margin-top:10px"></div></div>
    <div class="panel"><b style="font-size:13px">Tabelas cadastradas</b> <span class="muted" style="font-size:11.5px">— contratos/tabelas por fornecedor</span>
      <div class="wrap" style="margin-top:8px"><table><thead><tr><th>Fornecedor</th><th>Validade</th><th style="text-align:center">Itens</th><th>Situação</th><th>Observação</th><th></th></tr></thead>
      <tbody>${tabs||'<tr><td colspan="6" class="empty">Nenhuma tabela ainda. Clique em “Nova tabela”.</td></tr>'}</tbody></table></div></div>`;
  if(PREC.busca) precBuscar(PREC.busca);
}
let _precT;
function precBuscarIn(q){ PREC.busca=q; clearTimeout(_precT); _precT=setTimeout(()=>precBuscar(q),250); }
async function precBuscar(q){
  const box=document.getElementById('precResult'); if(!box)return;
  if(!q.trim()){ box.innerHTML='<div class="dmini">Digite um insumo para comparar os preços tabelados dos fornecedores.</div>'; return; }
  try{ const j=await (await fetch('actions/precos.php?buscar='+encodeURIComponent(q)+'&me='+precMe())).json();
    PREC.grupos=j.grupos||[];
    box.innerHTML=PREC.grupos.length? PREC.grupos.map(g=>`<div style="border:1px solid var(--line);border-radius:9px;padding:10px 12px;margin-bottom:8px">
        <div style="font-weight:700;color:var(--verde-d)">${esc(g.nome)} <span class="muted" style="font-weight:400;font-size:11px">${esc(g.unidade||'')} · ${g.ofertas.length} oferta(s)</span></div>${precOfertaTbl(g)}</div>`).join('')
      : `<div class="dmini">Nenhum preço tabelado casa "${esc(q)}".</div>`;
  }catch(e){ box.innerHTML='<div class="dmini">Falha na busca.</div>'; }
}
async function precNova(){ PREC.cur=null; PREC.itens=[{descricao_original:'',insumo_nome:'',unidade:'',preco:'',frete_incluso:0,observacao:''}]; await precCarregaInsumos(); PREC.mode='nova'; precRender(); }
async function precEditar(id){
  try{ const j=await (await fetch('actions/precos.php?tabela='+id+'&me='+precMe())).json();
    if(j.error){toast(j.error);return;} PREC.cur=j.tabela;
    PREC.itens=(j.itens||[]).map(it=>({id:it.id,descricao_original:it.descricao_original||'',insumo_nome:it.insumo_nome||'',unidade:it.unidade||'',preco:it.preco!=null?it.preco:'',frete_incluso:Number(it.frete_incluso)||0,observacao:it.observacao||''}));
    if(!PREC.itens.length) PREC.itens=[{descricao_original:'',insumo_nome:'',unidade:'',preco:'',frete_incluso:0,observacao:''}];
    await precCarregaInsumos(); PREC.mode='nova'; precRender();
  }catch(e){toast('Falha');}
}
async function precCarregaInsumos(){ try{ const j=await (await fetch('actions/precos.php?insumos=&me='+precMe())).json(); PREC.insumos=j.insumos||[]; }catch(e){ PREC.insumos=[]; } }
function precRenderNova(){
  const t=PREC.cur||{}, w=document.getElementById('cotwrap');
  const dl='<datalist id="precInsDL">'+PREC.insumos.map(i=>`<option value="${esc(i.nome)}">`).join('')+'</datalist>';
  w.innerHTML=`<div class="panel">
    <div style="display:flex;align-items:center;gap:8px;margin-bottom:12px"><button class="btn-ghost" onclick="PREC.mode='home';precRender()"><span class="material-icons" style="font-size:16px;vertical-align:-3px">arrow_back</span> Voltar</button><b style="font-size:15px">${t.id?'Editar':'Nova'} tabela de preços</b></div>
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:10px">
      ${cotFld('Fornecedor *','<input id="pt_forn" value="'+esc(t.fornecedor_nome||'')+'" placeholder="Nome do fornecedor">')}
      ${cotFld('Título/contrato','<input id="pt_tit" value="'+esc(t.titulo||'')+'" placeholder="Ex.: Tabela 2026 / Contrato XPTO">')}
      ${cotFld('Validade — início','<input id="pt_vi" type="date" value="'+esc(t.validade_inicio||'')+'">')}
      ${cotFld('Validade — fim','<input id="pt_vf" type="date" value="'+esc(t.validade_fim||'')+'">')}
    </div>
    ${cotFld('Observação (ex.: “atende obras da região de Americana”)','<input id="pt_obs" style="width:100%" value="'+esc(t.observacao||'')+'">','margin-top:8px')}
    <div class="dmini" style="margin-top:10px">📎 O PDF da tabela/contrato e a leitura por IA entram no próximo passo. Por ora, cadastre os itens abaixo.</div>
    <div style="margin-top:12px;display:flex;justify-content:space-between;align-items:center"><b style="font-size:13px">Itens</b>
      <span class="muted" style="font-size:11px">“Item canônico” agrupa a mesma coisa entre fornecedores (dedup)</span></div>
    <div id="pt_itens" style="margin-top:8px"></div>
    <button class="btn-ghost" style="margin-top:6px" onclick="precAddItem()"><span class="material-icons" style="font-size:15px;vertical-align:-3px">add</span> Adicionar item</button>
    <div style="margin-top:14px"><button class="btn-prim" onclick="precSalvar()"><span class="material-icons" style="font-size:16px;vertical-align:-3px">check</span> Salvar tabela</button></div>
    ${dl}</div>`;
  precRenderItens();
}
function precRenderItens(){
  const box=document.getElementById('pt_itens'); if(!box)return;
  box.innerHTML=PREC.itens.map((it,i)=>`<div style="display:grid;grid-template-columns:minmax(0,1.4fr) minmax(0,1.2fr) 70px 110px 62px 34px;gap:6px;align-items:center;margin-bottom:6px">
    <input placeholder="Descrição do fornecedor" value="${esc(it.descricao_original)}" oninput="PREC.itens[${i}].descricao_original=this.value" style="font-size:12px">
    <input list="precInsDL" placeholder="Item canônico (dedup)" value="${esc(it.insumo_nome)}" oninput="PREC.itens[${i}].insumo_nome=this.value" style="font-size:12px" title="agrupa a mesma coisa entre fornecedores">
    <input placeholder="unid" value="${esc(it.unidade)}" oninput="PREC.itens[${i}].unidade=this.value" style="font-size:12px">
    <input inputmode="decimal" placeholder="preço" value="${it.preco!==''&&it.preco!=null?esc(fmtMoney(it.preco)):''}" oninput="maskMoneyInput(this);PREC.itens[${i}].preco=parseBRLInput(this.value)" onblur="moneyBlur(this)" style="font-size:12px;text-align:right">
    <label style="font-size:10.5px;display:flex;align-items:center;gap:3px;justify-content:center" title="frete incluso"><input type="checkbox" ${it.frete_incluso?'checked':''} onchange="PREC.itens[${i}].frete_incluso=this.checked?1:0"> frete</label>
    <button class="btn-ghost" style="padding:2px 6px;color:var(--pend)" onclick="PREC.itens.splice(${i},1);precRenderItens()" title="remover">×</button>
  </div>`).join('')||'<div class="dmini">Sem itens.</div>';
}
function precAddItem(){ PREC.itens.push({descricao_original:'',insumo_nome:'',unidade:'',preco:'',frete_incluso:0,observacao:''}); precRenderItens(); }
async function precSalvar(){
  const v=id=>((document.getElementById(id)||{}).value||'');
  const forn=v('pt_forn').trim(); if(!forn){toast('Informe o fornecedor');return;}
  const itens=PREC.itens.filter(it=>(it.descricao_original||'').trim()).map(it=>({id:it.id,descricao_original:it.descricao_original,insumo_nome:it.insumo_nome,unidade:it.unidade,preco:(it.preco===''||it.preco==null)?'':Number(it.preco),frete_incluso:it.frete_incluso?1:0,observacao:it.observacao}));
  const body={acao:'salvar_tabela',me:EU&&EU.bitrix_id,tabela:{id:PREC.cur&&PREC.cur.id,fornecedor_nome:forn,titulo:v('pt_tit'),validade_inicio:v('pt_vi'),validade_fim:v('pt_vf'),observacao:v('pt_obs')},itens};
  try{ const r=await (await fetch('actions/precos.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(body)})).json();
    if(r.error){toast(r.error);return;} toast('Tabela salva'); precLoad(); }catch(e){toast('Falha: '+e.message);}
}
async function precExcluir(id){ if(!confirm('Excluir esta tabela de preços?'))return;
  try{ const r=await (await fetch('actions/precos.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({acao:'excluir_tabela',me:EU&&EU.bitrix_id,id})})).json();
    if(r.error){toast(r.error);return;} toast('Excluída'); precLoad(); }catch(e){toast('Falha');}
}
/* ========== SOLICITAÇÕES DE COMPRA (fila TOTVS ao vivo + de-para + overlay) ========== */
const SOL={tab:'dashboard',data:null,obras:null,filt:{obra:'',comprador:'',status:'',bucket:'',busca:''},exp:{}};
const SOL_ST={pendente:['var(--neu)','Pendente','var(--neubg)'],em_cotacao:['var(--cot)','Em cotação','var(--cotbg)'],cotacoes_recebidas:['var(--and)','Cotações recebidas','var(--andbg)'],pedido_criado:['var(--ok)','Pedido criado','var(--okbg)'],cancelado:['var(--pend)','Cancelado','var(--pendbg)']};
const SOL_BK={r:['#eafaf0','#1f7a44','No prazo'],a:['#fdf4d9','#8a6d12','Atenção'],l:['#fde8cf','#b5610f','Atrasado'],c:['#fbe4e4','#b02020','Crítico']};
function solMe(){ return encodeURIComponent((EU&&EU.bitrix_id)||''); }
function solInit(){ solTab(SOL.tab||'dashboard'); }
function solTab(t){ SOL.tab=t; ['dashboard','lista','obras'].forEach(x=>{const b=document.getElementById('stab-'+x); if(b)b.classList.toggle('on',x===t);});
  if(t==='obras') solObrasLoad(); else if(SOL.data) solRender(); else solLoad(); }
async function solLoad(){
  const w=document.getElementById('solwrap'); if(!SOL.data) w.innerHTML='<div class="dempty">Lendo a fila de solicitações ao vivo…</div>';
  try{ const j=await (await fetch('actions/solicitacoes.php?me='+solMe())).json();
    if(j.error){w.innerHTML='<div class="empty">'+esc(j.error)+'</div>';return;} SOL.data=j; solRender();
  }catch(e){ w.innerHTML='<div class="empty">Falha ao ler a fila.</div>'; }
}
function solRender(){ if(SOL.tab==='lista') return solRenderLista(); return solRenderDash(); }
function solPill(l){ const b=SOL_BK[l.bucket]||SOL_BK.r; return `<span class="dchip" style="background:${b[0]};color:${b[1]};font-weight:700" title="${b[2]}">${l.dias!=null?l.dias+' dias':'—'}</span>`; }
function solRenderDash(){
  const w=document.getElementById('solwrap'), d=SOL.data.dashboard, b=d.b;
  const card=(lbl,val,sub,cor)=>`<div class="kpi" style="min-width:150px"><div class="v" style="color:${cor||'inherit'}">${val}</div><div class="l">${lbl}${sub?` · ${sub}`:''}</div></div>`;
  const bkCard=(k,lbl)=>{const x=SOL_BK[k];return `<div style="flex:1;min-width:130px;border:1px solid ${x[1]}33;background:${x[0]};border-radius:10px;padding:12px 14px"><div style="font-size:24px;font-weight:800;color:${x[1]}">${b[k]}</div><div style="font-size:11.5px;color:${x[1]}">${lbl}</div></div>`;};
  const obras=Object.entries(d.por_obra), comps=Object.entries(d.por_comprador);
  w.innerHTML=`<div class="panel" style="margin-bottom:10px"><div class="kpis">
      ${card('Solicitações pendentes',d.total,'',null)}
      ${card('Obras com pendência',obras.length,'',null)}
      ${card('Compradores',comps.filter(c=>c[0]!=='(sem comprador)').length,'',null)}
      ${card('Críticos (+30 dias)',b.c,'precisam atenção','var(--pend)')}
    </div>
    <div style="display:flex;gap:10px;flex-wrap:wrap;margin-top:12px">${bkCard('r','0 a 7 dias')}${bkCard('a','8 a 14 dias')}${bkCard('l','15 a 30 dias')}${bkCard('c','+30 dias')}</div></div>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px">
      <div class="panel"><b style="font-size:13px">Resumo por obra</b><div class="wrap" style="margin-top:6px;max-height:360px;overflow:auto"><table><thead><tr><th>Obra</th><th style="text-align:center">Total</th><th style="text-align:center;color:var(--ok)">Recentes</th><th style="text-align:center;color:var(--pend)">Críticos</th></tr></thead><tbody>
        ${obras.map(([n,v])=>`<tr style="cursor:pointer" onclick="SOL.filt={obra:'${esc(n).replace(/'/g,"")}',comprador:'',status:'',bucket:'',busca:''};solTab('lista')"><td>${esc(n)}</td><td style="text-align:center"><b>${v.total}</b></td><td style="text-align:center;color:var(--ok)">${v.recentes||''}</td><td style="text-align:center;color:${v.criticos?'var(--pend)':'#bbb'}">${v.criticos||'0'}</td></tr>`).join('')}
      </tbody></table></div></div>
      <div class="panel"><b style="font-size:13px">Resumo por comprador</b><div class="wrap" style="margin-top:6px;max-height:360px;overflow:auto"><table><thead><tr><th>Comprador</th><th style="text-align:center">Total</th><th style="text-align:center;color:var(--ok)">0-7</th><th style="text-align:center;color:#8a6d12">8-14</th><th style="text-align:center;color:#b5610f">15-30</th><th style="text-align:center;color:var(--pend)">+30</th></tr></thead><tbody>
        ${comps.map(([n,v])=>`<tr style="cursor:pointer" onclick="SOL.filt={obra:'',comprador:'${esc(n).replace(/'/g,"")}',status:'',bucket:'',busca:''};solTab('lista')"><td>${esc(n)}</td><td style="text-align:center"><b>${v.total}</b></td><td style="text-align:center;color:var(--ok)">${v.r||''}</td><td style="text-align:center;color:#8a6d12">${v.a||''}</td><td style="text-align:center;color:#b5610f">${v.l||''}</td><td style="text-align:center;color:${v.c?'var(--pend)':'#bbb'}">${v.c||'0'}</td></tr>`).join('')}
      </tbody></table></div></div></div>`;
}
function solRenderLista(){
  const w=document.getElementById('solwrap'), all=SOL.data.solicitacoes||[], f=SOL.filt, qn=(f.busca||'').toLowerCase();
  const obras=[...new Set(all.map(s=>s.nome_obra))].sort(), comps=[...new Set(all.map(s=>s.comprador_nome).filter(Boolean))].sort();
  let rows=all.filter(s=>(!f.obra||s.nome_obra===f.obra)&&(!f.comprador||s.comprador_nome===f.comprador)&&(!f.status||s.status===f.status)&&(!f.bucket||s.bucket===f.bucket)&&(!qn||((s.numero+' '+s.primeiro).toLowerCase().includes(qn))));
  rows.sort((a,b)=>(b.dias||0)-(a.dias||0));
  let html=`<div class="panel" style="margin-bottom:10px"><div class="bar" style="gap:8px;flex-wrap:wrap;align-items:center">
     <div class="search" style="min-width:180px"><span class="material-icons" style="color:var(--muted)">search</span><input id="solBusca" placeholder="Buscar nº ou item…" value="${esc(f.busca)}" oninput="SOL.filt.busca=this.value;solRenderLista()"></div>
     <select onchange="SOL.filt.obra=this.value;solRenderLista()" style="font-size:12px;padding:6px"><option value="">Todas as obras</option>${obras.map(o=>`<option value="${esc(o)}" ${o===f.obra?'selected':''}>${esc(o)}</option>`).join('')}</select>
     <select onchange="SOL.filt.comprador=this.value;solRenderLista()" style="font-size:12px;padding:6px"><option value="">Todos compradores</option>${comps.map(c=>`<option value="${esc(c)}" ${c===f.comprador?'selected':''}>${esc(c)}</option>`).join('')}</select>
     <select onchange="SOL.filt.bucket=this.value;solRenderLista()" style="font-size:12px;padding:6px"><option value="">Todos prazos</option><option value="r" ${f.bucket==='r'?'selected':''}>No prazo (0-7)</option><option value="a" ${f.bucket==='a'?'selected':''}>Atenção (8-14)</option><option value="l" ${f.bucket==='l'?'selected':''}>Atrasado (15-30)</option><option value="c" ${f.bucket==='c'?'selected':''}>Crítico (+30)</option></select>
     <select onchange="SOL.filt.status=this.value;solRenderLista()" style="font-size:12px;padding:6px"><option value="">Todos status</option>${Object.entries(SOL_ST).map(([k,v])=>`<option value="${k}" ${f.status===k?'selected':''}>${v[1]}</option>`).join('')}</select>
     <span class="muted" style="font-size:11.5px">${rows.length} de ${all.length}</span>
     <button class="btn-ghost" style="margin-left:auto;padding:5px 10px" onclick="SOL.data=null;SOL.filt={obra:'',comprador:'',status:'',bucket:'',busca:''};solLoad()"><span class="material-icons" style="font-size:15px;vertical-align:-3px">refresh</span> Atualizar fila</button>
   </div></div><div class="wrap"><table><thead><tr><th>Pedido</th><th style="text-align:center">Itens</th><th>Descrição</th><th>Obra</th><th>Emissão</th><th style="text-align:center">Dias</th><th>Status</th><th>Comprador</th><th>Observações</th><th>Ações</th></tr></thead><tbody>`;
  for(const s of rows){ const key=s.coligada+'|'+s.numero, ex=SOL.exp[key];
    const st=SOL_ST[s.status]||['var(--neu)','?','var(--neubg)'], obs=s.observacoes||'';
    html+=`<tr><td><b style="cursor:pointer" onclick="SOL.exp['${esc(key)}']=${ex?'false':'true'};solRenderLista()">${ex?'▾':'▸'} ${esc(String(s.numero).replace(/^0+/,'')||s.numero)}</b></td>
      <td style="text-align:center">${s.n_itens}</td><td style="max-width:220px"><span title="${esc(s.primeiro)}">${esc((s.primeiro||'').slice(0,40))}</span></td>
      <td class="muted" style="font-size:11.5px">${esc(s.nome_obra)}</td><td class="muted" style="font-size:11.5px;white-space:nowrap">${s.emissao?D(s.emissao):'—'}</td>
      <td style="text-align:center">${solPill(s)}</td>
      <td style="background:${st[2]};border-left:3px solid ${st[0]}">${CAN_EDIT?`<select onchange="solStatus('${esc(key)}',this.value,this)" style="font-size:11px;padding:3px 4px;font-weight:700;color:${st[0]};background:${st[2]};border:1px solid ${st[0]};border-radius:6px;cursor:pointer">${Object.entries(SOL_ST).map(([k,v])=>`<option value="${k}" ${s.status===k?'selected':''}>${v[1]}</option>`).join('')}</select>`:`<span class="dchip" style="background:${st[0]};color:#fff;font-weight:700">${st[1]}</span>`}</td>
      <td class="muted" style="font-size:11.5px">${esc(s.comprador_nome||'—')}</td>
      <td>${CAN_EDIT?`<input value="${esc(obs)}" title="${esc(obs)}" oninput="this.title=this.value" onchange="solObs('${esc(key)}',this.value,this)" placeholder="anotação…" style="width:150px;font-size:11px;padding:3px 5px">`:`<span title="${esc(obs)}">${esc(obs.slice(0,32))}${obs.length>32?'…':''}</span>`}</td>
      <td style="white-space:nowrap"><button class="btn-ghost" style="padding:2px 6px" title="Copiar mensagem para orçamento" onclick="solCopiar('${esc(key)}')"><span class="material-icons" style="font-size:15px">content_copy</span></button>
        ${s.cotacao_id?`<button class="btn-ghost" style="padding:2px 6px;color:var(--verde-d)" title="Ver cotação gerada" onclick="showView('cotacoes');setTimeout(()=>cotAbrir(${s.cotacao_id}),200)"><span class="material-icons" style="font-size:15px">request_quote</span></button>`:(CAN_EDIT?`<button class="btn-ghost" style="padding:2px 6px" title="Gerar cotação desta solicitação" onclick="solGerar('${esc(key)}')"><span class="material-icons" style="font-size:15px;color:var(--verde)">playlist_add</span></button>`:'')}</td></tr>`;
    if(ex) html+=`<tr><td colspan="10" style="background:#fafbfb;padding:8px 14px"><b style="font-size:11px;color:var(--muted)">ITENS</b>${s.itens.map(it=>`<div style="font-size:12px;padding:2px 0">• ${cotNum(it.qtd)} ${esc(it.und)} — ${esc(it.produto)}${it.observacao?` <span class="muted">(${esc(it.observacao)})</span>`:''}</div>`).join('')}</td></tr>`;
  }
  if(!rows.length) html+='<tr><td colspan="10" class="empty">Nenhuma solicitação nesse filtro.</td></tr>';
  const foc=document.activeElement, wasB=foc&&foc.id==='solBusca', car=wasB?foc.selectionStart:null;
  w.innerHTML=html+'</tbody></table></div>';
  if(wasB){const ni=document.getElementById('solBusca'); if(ni){ni.focus(); try{ni.setSelectionRange(car,car);}catch(e){}}}
}
function solFind(key){ return (SOL.data.solicitacoes||[]).find(s=>(s.coligada+'|'+s.numero)===key); }
async function solStatus(key,v,el){ const s=solFind(key); if(!s)return; const prev=s.status; s.status=v;
  const st=SOL_ST[v]||['var(--neu)','?','var(--neubg)'];
  if(el){ el.style.color=st[0]; el.style.background=st[2]; el.style.borderColor=st[0]; const td=el.closest('td'); if(td){ td.style.background=st[2]; td.style.borderLeft='3px solid '+st[0]; } }
  try{ const r=await (await fetch('actions/solicitacoes.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({acao:'salvar_overlay',me:EU&&EU.bitrix_id,coligada:s.coligada,numero:s.numero,status:v})})).json();
    if(r&&r.error){ s.status=prev; solRenderLista(); toast('Não salvou: '+r.error); return; }
    toast('Status salvo');
  }catch(e){ s.status=prev; solRenderLista(); toast('Falha ao salvar status — tente de novo'); } }
async function solObs(key,v,el){ const s=solFind(key); if(!s)return; const prev=s.observacoes||''; s.observacoes=v;
  try{ const r=await (await fetch('actions/solicitacoes.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({acao:'salvar_overlay',me:EU&&EU.bitrix_id,coligada:s.coligada,numero:s.numero,observacoes:v})})).json();
    if(r&&r.error){ s.observacoes=prev; if(el){el.value=prev;el.title=prev;} toast('Não salvou: '+r.error); return; }
    toast('Anotação salva');
  }catch(e){ s.observacoes=prev; if(el){el.value=prev;el.title=prev;} toast('Falha ao salvar anotação — tente de novo'); } }
function solCopiar(key){ const s=solFind(key); if(!s)return;
  const sub=/CAPRETZ/i.test(s.coligada)?(s.nome_obra||'Geral'):'Geral';
  const num=String(s.numero).replace(/^0+/,'')||s.numero;
  const txt='Por favor cotar os itens abaixo para obra:\n\n'+s.coligada+' - '+sub+'\n\nPedido nº '+num+'\n\nItens:\n\n'+s.itens.map(it=>'- '+cotNum(it.qtd)+' '+it.und+' - '+it.produto+(it.observacao?' ('+it.observacao+')':'')).join('\n');
  navigator.clipboard.writeText(txt).then(()=>toast('Mensagem copiada!'),()=>{ const t=document.createElement('textarea');t.value=txt;document.body.appendChild(t);t.select();document.execCommand('copy');t.remove();toast('Mensagem copiada!'); }); }
async function solGerar(key){ const s=solFind(key); if(!s)return; if(!confirm('Gerar uma cotação no Mapa com os '+s.n_itens+' itens desta solicitação?'))return;
  try{ const r=await (await fetch('actions/solicitacoes.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({acao:'gerar_cotacao',me:EU&&EU.bitrix_id,coligada:s.coligada,numero:s.numero})})).json();
    if(r.error){toast(r.error);return;} toast('Cotação gerada!'); s.cotacao_id=r.cotacao_id; s.status='em_cotacao'; showView('cotacoes'); setTimeout(()=>cotAbrir(r.cotacao_id),250);
  }catch(e){toast('Falha: '+e.message);} }
async function solObrasLoad(){
  const w=document.getElementById('solwrap'); w.innerHTML='<div class="dempty">Carregando obras…</div>';
  try{ SOL.obras=await (await fetch('actions/solicitacoes.php?obras&me='+solMe())).json(); solRenderObras(); }catch(e){ w.innerHTML='<div class="empty">Falha.</div>'; }
}
function solRenderObras(){
  const w=document.getElementById('solwrap'), o=SOL.obras, uOpts=id=>'<option value="">— comprador —</option>'+(o.usuarios||[]).map(u=>`<option value="${esc(u.bitrix_id)}" ${String(u.bitrix_id)===String(id)?'selected':''}>${esc(u.nome)}</option>`).join('');
  const rOpts=id=>'<option value="">— vincular à obra do radar (opcional) —</option>'+(o.radar_obras||[]).map(r=>`<option value="${r.id}" ${String(r.id)===String(id)?'selected':''}>${esc(r.nome)}</option>`).join('');
  const semComp=(o.obras||[]).filter(x=>!x.comprador_id).length;
  w.innerHTML=`<div class="panel" style="margin-bottom:10px"><b style="font-size:14px">Obras &amp; compradores</b>
      <span class="muted" style="font-size:11.5px"> — cada obra (coligada + centro de custo) tem 1 comprador; a solicitação entra já atribuída. ${semComp?`<b style="color:var(--pend)">${semComp} sem comprador</b>`:'todas atribuídas ✓'}</span></div>
    <div class="wrap"><table><thead><tr><th>Obra (nome comercial)</th><th>CNPJ da obra</th><th>Endereço da obra</th><th>Coligada (TOTVS)</th><th style="text-align:center">CC</th><th style="text-align:center">Pend.</th><th>Comprador responsável</th><th>Obra do radar (opcional)</th></tr></thead><tbody>
    ${(o.obras||[]).map((x,i)=>`<tr>
      <td><input value="${esc(x.nome_comercial)}" onchange="SOL.obras.obras[${i}].nome_comercial=this.value;solObraSave(${i})" style="width:150px;font-size:12px"></td>
      <td><input value="${esc(x.cnpj||'')}" onchange="SOL.obras.obras[${i}].cnpj=this.value;solObraSave(${i})" placeholder="00.000.000/0001-00" title="CNPJ que vai na carta de cotação de material desta obra" style="width:145px;font-size:11.5px"></td>
      <td><input value="${esc(x.endereco||'')}" onchange="SOL.obras.obras[${i}].endereco=this.value;solObraSave(${i})" placeholder="rua, nº, bairro, cidade/UF" title="Endereço que vai na carta de cotação e no e-mail ao fornecedor" style="width:210px;font-size:11.5px"></td>
      <td class="muted" style="font-size:11px">${esc(x.coligada)}</td><td style="text-align:center" class="muted">${esc(x.obra_cod)}</td>
      <td style="text-align:center"><b>${x.n}</b></td>
      <td><select onchange="SOL.obras.obras[${i}].comprador_id=this.value;solObraSave(${i})" style="font-size:12px;padding:3px;${x.comprador_id?'':'border-color:var(--pend)'}">${uOpts(x.comprador_id)}</select></td>
      <td><select onchange="SOL.obras.obras[${i}].radar_obra_id=this.value;solObraSave(${i})" style="font-size:11.5px;padding:3px">${rOpts(x.radar_obra_id)}</select></td></tr>`).join('')}
    </tbody></table></div>`;
}
async function solObraSave(i){ const x=SOL.obras.obras[i];
  try{ const r=await (await fetch('actions/solicitacoes.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({acao:'salvar_obra',me:EU&&EU.bitrix_id,obra:{coligada:x.coligada,obra_cod:x.obra_cod,nome_comercial:x.nome_comercial,cnpj:x.cnpj||'',endereco:x.endereco||'',comprador_id:x.comprador_id,radar_obra_id:x.radar_obra_id||null}})})).json();
    if(r.error){toast(r.error);return;} toast('Atribuição salva'); SOL.data=null; }catch(e){toast('Falha');} }
/* ---------- Fornecedores (sub-aba do Mapa de Cotações) ---------- */
let FORN={list:[],cats:[],tipos:[],total:0,f:{nome:'',categoria:'',tipo:'',itens:''},edit:null};
async function fornLoad(){
  const w=document.getElementById('cotwrap'); w.innerHTML='<div class="dempty">Carregando fornecedores…</div>';
  const q=new URLSearchParams(); Object.entries(FORN.f).forEach(([k,v])=>{ if(v) q.set(k,v); }); q.set('limit','80');
  try{ const d=await (await fetch('actions/fornecedores.php?'+q.toString())).json();
    FORN.list=d.fornecedores||[]; FORN.cats=d.categorias||[]; FORN.tipos=d.tipos||[]; FORN.total=d.total||0; fornRender();
  }catch(e){ w.innerHTML='<div class="dempty">Falha: '+esc(e.message)+'</div>'; }
}
let _fornT; function fornDeb(){ clearTimeout(_fornT); _fornT=setTimeout(fornLoad,350); }
function fornCatOpts(sel){ return '<option value="">Todas as categorias</option>'+FORN.cats.map(c=>`<option value="${esc(c.nome)}" ${c.nome===sel?'selected':''}>${esc(c.nome)}</option>`).join(''); }
function fornRender(){
  if(FORN.edit) return fornRenderEdit();
  const w=document.getElementById('cotwrap');
  let html=`<div class="panel" style="margin-bottom:10px"><div class="bar" style="gap:8px;flex-wrap:wrap;align-items:center">
    <div class="search" style="min-width:150px"><span class="material-icons" style="color:var(--muted)">search</span><input placeholder="Buscar nome…" value="${esc(FORN.f.nome)}" oninput="FORN.f.nome=this.value;fornDeb()"></div>
    <select onchange="FORN.f.categoria=this.value;fornLoad()">${fornCatOpts(FORN.f.categoria)}</select>
    <select onchange="FORN.f.tipo=this.value;fornLoad()"><option value="">Todos os tipos</option>${FORN.tipos.map(t=>`<option value="${esc(t)}" ${t===FORN.f.tipo?'selected':''}>${esc(t)}</option>`).join('')}</select>
    <input placeholder="Filtrar por itens…" value="${esc(FORN.f.itens)}" oninput="FORN.f.itens=this.value;fornDeb()" style="min-width:130px">
    <span class="muted" style="font-size:12px">${FORN.total} fornecedor(es)</span>
    ${CAN_EDIT?'<button class="btn-prim" style="margin-left:auto;padding:7px 12px" onclick="fornNovo()"><span class="material-icons" style="font-size:15px;vertical-align:-3px">add</span> Novo</button>':''}
  </div></div><div class="wrap"><table><thead><tr><th>Nome</th><th>Categoria</th><th>Cidade</th><th>Contato</th><th>Telefone</th><th>Itens</th><th>Tipo</th><th></th></tr></thead><tbody>`;
  for(const f of FORN.list){
    html+=`<tr><td><b>${esc(f.nome)}</b>${f.email?`<div class="muted" style="font-size:11px">${esc(f.email)}</div>`:''}</td><td class="muted">${esc(f.categoria||'')}</td><td class="muted">${esc(f.cidade||'')}</td><td>${esc(f.contato||'')}</td><td>${esc(f.telefone||'')}</td><td class="muted" style="font-size:11px">${esc((f.itens||'').slice(0,42))}</td><td>${esc(f.tipo||'')}</td>
      <td>${CAN_EDIT?`<button class="btn-ghost" style="padding:2px 8px" onclick="fornNovo(${f.id})"><span class="material-icons" style="font-size:15px">edit</span></button>`:''}</td></tr>`;
  }
  if(!FORN.list.length) html+='<tr><td colspan="8" class="empty">Nenhum fornecedor. Importe do sistema antigo (Excel) ou cadastre um novo.</td></tr>';
  w.innerHTML=html+'</tbody></table></div>';
}
function fornNovo(id){ FORN.edit = id ? Object.assign({}, (FORN.list.find(f=>f.id===id)||{id})) : {}; fornRender(); }
function fornRenderEdit(){
  const f=FORN.edit, w=document.getElementById('cotwrap');
  const F=(label,key,ph)=>cotFld(label,`<input id="fe_${key}" value="${esc(f[key]||'')}" placeholder="${ph||''}">`);
  w.innerHTML=`<div class="panel"><div style="display:flex;align-items:center;gap:8px;margin-bottom:12px"><button class="btn-ghost" onclick="FORN.edit=null;fornRender()"><span class="material-icons" style="font-size:16px;vertical-align:-3px">arrow_back</span> Voltar</button><b style="font-size:15px">${f.id?'Editar':'Novo'} fornecedor</b></div>
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:10px">
      ${F('Nome *','nome','Razão social / nome')}
      ${cotFld('Categoria',`<input id="fe_categoria" list="feCats" value="${esc(f.categoria||'')}" placeholder="Categoria"><datalist id="feCats">${FORN.cats.map(c=>`<option value="${esc(c.nome)}">`).join('')}</datalist>`)}
      ${cotFld('Tipo',`<select id="fe_tipo">${['','Fabricante','M.O.','Atacadista','Varejista','Locadora','Distribuidor','Prestador'].map(t=>`<option ${t===(f.tipo||'')?'selected':''}>${t}</option>`).join('')}</select>`)}
      ${F('Cidade','cidade')} ${F('Contato','contato')} ${F('Telefone','telefone')} ${F('WhatsApp','whatsapp')} ${F('E-mail','email')} ${F('CNPJ','cnpj')}
    </div>
    ${cotFld('Itens que fornece','<input id="fe_itens" value="'+esc(f.itens||'')+'" placeholder="Ex.: forro, gesso, revestimentos">','margin-top:8px')}
    <div style="margin-top:14px;display:flex;gap:8px"><button class="btn-prim" onclick="fornSalvar()"><span class="material-icons" style="font-size:16px;vertical-align:-3px">check</span> Salvar</button>${f.id?`<button class="btn-ghost" style="color:var(--pend)" onclick="fornExcluir(${f.id})">Excluir</button>`:''}</div></div>`;
}
async function fornSalvar(){
  const g=id=>val('fe_'+id); const nome=g('nome').trim(); if(!nome){toast('Nome obrigatório');return;}
  const body={acao:'fornecedor_salvar',me:EU&&EU.bitrix_id,id:FORN.edit.id||undefined,nome,categoria:g('categoria'),cidade:g('cidade'),contato:g('contato'),telefone:g('telefone'),whatsapp:g('whatsapp'),email:g('email'),cnpj:g('cnpj'),itens:g('itens'),tipo:g('tipo')};
  try{ const r=await (await fetch('actions/fornecedores.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(body)})).json();
    if(r.error){toast(r.error);return;} toast('Fornecedor salvo'); FORN.edit=null; fornLoad();
  }catch(e){toast('Falha: '+e.message);}
}
async function fornExcluir(id){ if(!confirm('Excluir este fornecedor?'))return;
  try{ await fetch('actions/fornecedores.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({acao:'fornecedor_excluir',me:EU&&EU.bitrix_id,id})}); FORN.edit=null; fornLoad(); }catch(e){toast('Falha');} }

/* ===== Configuração / Permissões (Bloco 2) ===== */
let CFG={usuarios:[],obras:[]}, NUSER=null;
/* ===================== MÓDULO OBRAS — ficha das obras + de-para entre sistemas ===================== */
let OBRAS_M={tab:'ficha', list:[], is_admin:false, filt:'', fstatus:''};
function obrasInit(){ if(OBRAS_M.list.length){obrasRender();} else obrasLoad(); }
async function obrasLoad(){ const w=document.getElementById('obrasWrap'); if(!w)return; w.innerHTML='<div class="dempty">Carregando obras…</div>';
  try{ const r=await (await fetch('actions/obras.php?lista=1&me='+encodeURIComponent((EU&&EU.bitrix_id)||''))).json();
    if(r.error){w.innerHTML='<div class="dempty">'+esc(r.error)+'</div>';return;}
    OBRAS_M.list=r.obras||[]; OBRAS_M.is_admin=!!r.is_admin; obrasRender();
    if(CAN_EDIT && !OBRAS_M.coligadas){ try{ const rl=await (await fetch('actions/obras.php?coligadas=1&me='+encodeURIComponent((EU&&EU.bitrix_id)||''))).json(); OBRAS_M.coligadas=rl.coligadas||[]; OBRAS_M.capretz_cc=rl.capretz_cc||{}; }catch(e){ OBRAS_M.coligadas=[]; } }
    if(OBRAS_M.is_admin && !OBRAS_M.cronos){ try{ const rc=await (await fetch('actions/obras.php?cronogramas=1&me='+encodeURIComponent((EU&&EU.bitrix_id)||''))).json(); OBRAS_M.cronos=rc.cronogramas||[]; }catch(e){ OBRAS_M.cronos=[]; } }
  }catch(e){w.innerHTML='<div class="dempty">Falha: '+esc(e.message)+'</div>';}
}
function obrStatusChip(s){ const c={'Em Andamento':'var(--verde)','Iniciando':'var(--dourado)','Finalizada':'#8a9299'}[s]||'#8a9299'; return s?`<span class="dchip" style="background:${c}">${esc(s)}</span>`:''; }
function obrCarResumo(o){ const p=[]; if(+o.torres)p.push(o.torres+(+o.torres===1?' torre':' torres')); if(+o.pavimentos)p.push(o.pavimentos+' pav'); if(+o.unidades)p.push(o.unidades+' un'); return p.join(' · '); }
function obrasRender(){ const w=document.getElementById('obrasWrap'); if(!w)return;
  const tab=(t,lbl,ic)=>`<button class="btn-ghost" style="padding:7px 14px;border-radius:9px 9px 0 0;${OBRAS_M.tab===t?'background:#fff;border-bottom:2px solid var(--verde);font-weight:700;color:var(--verde-d)':'color:var(--muted)'}" onclick="OBRAS_M.tab='${t}';obrasRender()"><span class="material-icons" style="font-size:15px;vertical-align:-3px">${ic}</span> ${lbl}</button>`;
  w.innerHTML=`<div style="display:flex;gap:4px;border-bottom:1px solid var(--line);margin-bottom:12px">${tab('ficha','Ficha das Obras','apartment')}${tab('depara','De-para & Configuração','link')}</div>`+(OBRAS_M.tab==='ficha'?obrasTabFicha():obrasTabDepara());
}
/* ===== SELO ILUSTRADO DA OBRA (SVG gerado dos dados: torres/pav/subsolos/áreas comuns) ===== */
function seloAmen(txt){ const t=(txt||'').toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g,'');
  const M=[[/piscina/,'🏊'],[/quadra|beach|poliespor|tenis|squash|society|campo/,'🎾'],[/fitness|academ|crossfit|workout|ginastica/,'💪'],[/biciclet|\bbike\b/,'🚲'],[/\bpet\b/,'🐾'],[/salao|festa/,'🎉'],[/playground|play |brinquedoteca|\bkids\b/,'🛝'],[/cowork/,'💻'],[/\bspa\b|sauna|zen|beaut|massag/,'🧖'],[/jogos|\bgame/,'🎮'],[/churrasq|gourmet|fogo de chao/,'🔥'],[/horta/,'🌱'],[/leitura/,'📚'],[/cinema/,'🎬'],[/lavanderia/,'🧺']];
  const out=[]; M.forEach(a=>{ if(a[0].test(t)&&out.indexOf(a[1])<0)out.push(a[1]); }); return out; }
function seloWins(x,y,w,h,cols,rows){ let s='',pad=7,gx=(w-pad*2)/cols,gy=h/rows,ww=Math.min(gx-4,18),wh=Math.min(gy-6,12); for(let r=0;r<rows;r++)for(let c=0;c<cols;c++){s+=`<rect x="${(x+pad+c*gx+(gx-ww)/2).toFixed(1)}" y="${(y+r*gy+3).toFixed(1)}" width="${ww.toFixed(1)}" height="${wh.toFixed(1)}" rx="2"/>`;} return s; }
function seloTower(cx,topY,groundY,tw,pav){ const x=cx-tw/2,bh=groundY-topY,rows=Math.max(4,Math.min(12,Math.round(bh/18))),cols=tw>84?4:3;
  return `<rect x="${x-4}" y="${topY-8}" width="${tw+8}" height="10" rx="4" fill="#c3ccca"/><rect x="${x}" y="${topY}" width="${tw}" height="${bh}" fill="#e8edf0"/><g fill="#2f6fb0">${seloWins(x,topY+2,tw,bh-6,cols,rows)}</g>`+(pav?`<rect x="${cx-26}" y="${topY-40}" width="52" height="28" rx="8" fill="#173a4c"/><text x="${cx}" y="${topY-20}" font-size="15" font-weight="800" fill="#fff" text-anchor="middle">${pav}</text><path d="M${cx-6} ${topY-12} h12 l-6 6 z" fill="#173a4c"/>`:''); }
function seloHouse(cx,baseY,w){ const h=w*0.62,x=cx-w/2,y=baseY-h; return `<rect x="${x}" y="${(y+h*0.42).toFixed(1)}" width="${w}" height="${(h*0.58).toFixed(1)}" fill="#e8edf0"/><path d="M${x-3} ${(y+h*0.42).toFixed(1)} L${cx} ${y} L${x+w+3} ${(y+h*0.42).toFixed(1)} Z" fill="#cf8a4a"/><rect x="${(cx-w*0.12).toFixed(1)}" y="${(y+h*0.62).toFixed(1)}" width="${(w*0.24).toFixed(1)}" height="${(h*0.38).toFixed(1)}" fill="#2f6fb0"/>`; }
function seloHoriz(o){ return /horizontal|casas/i.test(((o.observacoes||'')+' '+(o.tipologias||''))); }
function obraSeloFull(o){
  const T=+o.torres||0,P=+o.pavimentos||0,S=+o.subsolos||0,pct=o.pct_fisico,horiz=seloHoriz(o),ams=seloAmen(o.areas_comuns);
  if(!(T>0||P>0||horiz||ams.length)) return '';
  const W=480,groundY=250; let build='';
  if(horiz){ for(let i=0;i<4;i++) build+=seloHouse(96+i*96,groundY,74); }
  else { const n=Math.max(1,Math.min(T||1,4)),tw=n>=4?68:(n===3?82:96),span=W-160,step=n>1?span/(n-1):0; for(let i=0;i<n;i++){ const cx=n>1?(80+i*step):W/2; build+=seloTower(cx,72,groundY,tw,P);} if(T>n) build+=`<text x="${W-34}" y="150" font-size="13" font-weight="800" fill="#8a988f" text-anchor="middle">+${T-n}</text>`; }
  let sub='',sn=Math.min(S,3); for(let i=0;i<sn;i++){ const y=groundY+16+i*24; sub+=`<rect x="104" y="${y}" width="272" height="22" rx="3" fill="${i%2?'#2a2e30':'#33383a'}"/><circle cx="240" cy="${y+11}" r="9" fill="#fff"/><text x="240" y="${y+15}" font-size="11" font-weight="800" fill="#33383a" text-anchor="middle">-${i+1}</text>`; }
  if(S>3) sub+=`<text x="392" y="${groundY+40}" font-size="11" fill="#8a988f">+${S-3}</text>`;
  const amsY=groundY+16+sn*24+16; let amc='',cols=['#0f8a8a','#caa32e','#6a5acd','#2f6fb0','#d1495b','#e07a3f','#3a9d5d'],show=ams.slice(0,7);
  show.forEach((ic,i)=>{ amc+=`<g transform="translate(${30+i*54},${amsY})"><rect width="44" height="44" rx="12" fill="${cols[i%cols.length]}"/><text x="22" y="30" font-size="22" text-anchor="middle">${ic}</text></g>`; });
  if(ams.length>7) amc+=`<text x="${30+7*54+4}" y="${amsY+28}" font-size="12" font-weight="700" fill="#8a988f">+${ams.length-7}</text>`;
  const H=amsY+(ams.length?58:0)+8, cap=[T>0?T+(T>1?' torres':' torre'):(horiz?'casas':''),P>0?P+' pav':'',S>0?S+(S>1?' subsolos':' subsolo'):''].filter(Boolean).join(' · ');
  return `<svg viewBox="0 0 ${W} ${H}" style="width:100%;max-width:${W}px;display:block;margin:2px auto" xmlns="http://www.w3.org/2000/svg">
    <rect x="12" y="8" width="${W-24}" height="${groundY-4}" rx="14" fill="#f3f7f5"/>
    ${cap?`<text x="30" y="30" font-size="12.5" font-weight="800" fill="#5a6b62">${esc(cap)}</text>`:''}
    ${pct!=null?`<g transform="translate(${W-150},18)"><rect width="128" height="26" rx="13" fill="var(--verde,#1d9e75)"/><text x="64" y="18" font-size="12" font-weight="800" fill="#fff" text-anchor="middle">🏗️ ${(+pct).toFixed(0)}% executado</text></g>`:''}
    ${build}<rect x="40" y="${groundY}" width="400" height="16" fill="#cdae7a"/><rect x="104" y="${groundY}" width="272" height="16" fill="#3b3f3d"/>${sub}${amc}</svg>`;
}
function obraSeloMini(o){
  const T=+o.torres||0,horiz=seloHoriz(o),ams=seloAmen(o.areas_comuns);
  if(!(T>0||horiz||ams.length)) return '';
  const W=260,H=60,gy=50; let b='';
  if(horiz){ for(let i=0;i<3;i++) b+=seloHouse(22+i*24,gy,20); }
  else { const n=Math.max(1,Math.min(T||1,3)); for(let i=0;i<n;i++){ const cx=22+i*26,tw=20; b+=`<rect x="${cx-tw/2}" y="12" width="${tw}" height="${gy-12}" rx="1" fill="#dfe6ea"/><g fill="#2f6fb0">${seloWins(cx-tw/2,14,tw,gy-16,2,4)}</g>`; } }
  const shown=Math.min(ams.length,5); let am=''; ams.slice(0,5).forEach((ic,i)=>{ am+=`<text x="${W-18-(shown-1-i)*26}" y="32" font-size="18" text-anchor="middle">${ic}</text>`; });
  return `<svg viewBox="0 0 ${W} ${H}" preserveAspectRatio="xMidYMid meet" style="width:100%;height:100%;display:block" xmlns="http://www.w3.org/2000/svg"><rect x="0" y="${gy}" width="${W}" height="3" fill="#cdae7a"/>${b}${am}</svg>`;
}
function obrasTabFicha(){
  const qn=opNorm(OBRAS_M.filt||''); const sts=[...new Set(OBRAS_M.list.map(o=>o.status).filter(Boolean))];
  const rows=OBRAS_M.list.filter(o=>(!qn||opNorm((o.nome||'')+' '+(o.cidade||'')+' '+(o.coligada_nome||'')+' '+(o.comprador_nome||'')+' '+(o.solic_nome||'')).includes(qn))&&(!OBRAS_M.fstatus||o.status===OBRAS_M.fstatus));
  let h=`<div class="panel"><div class="bar" style="gap:8px;flex-wrap:wrap;align-items:center">
    <div class="search" style="min-width:200px"><span class="material-icons" style="color:var(--muted)">search</span><input placeholder="Buscar obra, cidade, coligada, comprador…" value="${esc(OBRAS_M.filt)}" oninput="OBRAS_M.filt=this.value;obrasRender()"></div>
    <select onchange="OBRAS_M.fstatus=this.value;obrasRender()" style="font-size:12px;padding:6px"><option value="">Todos status</option>${sts.map(s=>`<option value="${esc(s)}" ${s===OBRAS_M.fstatus?'selected':''}>${esc(s)}</option>`).join('')}</select>
    <span class="muted" style="font-size:11.5px">${rows.length} de ${OBRAS_M.list.length} obras</span>
    ${CAN_EDIT?'<button class="btn-prim" style="margin-left:auto;padding:7px 13px" onclick="obrasFichaAbrir(0)"><span class="material-icons" style="font-size:15px;vertical-align:-3px">add</span> Nova obra</button>':''}
  </div></div>
  <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(290px,1fr));gap:10px;margin-top:2px">`;
  h+=rows.map(o=>{ const car=obrCarResumo(o); const selo=obraSeloMini(o);
    return `<div class="panel" style="margin:0;cursor:pointer;padding:0;overflow:hidden" onclick="obrasFichaAbrir(${o.id})">
      ${selo?`<div style="height:62px;padding:6px 12px 0;background:#f3f7f5;border-bottom:1px solid var(--line)">${selo}</div>`:''}
      <div style="padding:11px 14px;border-bottom:1px solid var(--line);background:linear-gradient(180deg,#f7faf8,#fff)">
        <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:8px"><b style="font-size:15px">${esc(o.nome)}</b>${obrStatusChip(o.status)}</div>
        <div class="muted" style="font-size:11.5px;margin-top:2px"><span class="material-icons" style="font-size:12px;vertical-align:-2px">place</span> ${esc(o.cidade||'—')}${o.comprador_nome?' · '+esc(o.comprador_nome):''}</div>
      </div>
      <div style="padding:9px 14px">
        <div style="font-size:12px;color:var(--verde-d);font-weight:600;min-height:16px">${car||'<span class="muted" style="font-weight:400">características a preencher</span>'}</div>
        ${o.pct_fisico!=null?`<div style="margin-top:7px"><div style="display:flex;justify-content:space-between;font-size:10.5px;color:var(--muted)"><span>avanço físico</span><span style="font-weight:800;color:var(--verde-d)">${(+o.pct_fisico).toFixed(1).replace('.',',')}%</span></div><div style="height:6px;background:#e6ebe8;border-radius:4px;overflow:hidden;margin-top:2px"><div style="height:100%;width:${Math.max(0,Math.min(100,+o.pct_fisico))}%;background:var(--verde)"></div></div>${o.crono_fim?`<div class="muted" style="font-size:10px;margin-top:3px"><span class="material-icons" style="font-size:11px;vertical-align:-2px">event</span> entrega prev. ${D(String(o.crono_fim).slice(0,10))}</div>`:''}</div>`:''}
        <div class="muted" style="font-size:11px;margin-top:6px">${o.coligada_cod?('Coligada '+o.coligada_cod+(o.coligada_nome?' · '+esc(String(o.coligada_nome).slice(0,28)):'')):'<span style="color:var(--pend)">sem coligada — confira o de-para</span>'}${o.solic_nome&&opNorm(o.solic_nome)!==opNorm(o.nome)?' · <span title="nome diferente nas solicitações">≈ '+esc(o.solic_nome)+'</span>':''}</div>
      </div>
    </div>`; }).join('')||'<div class="dmini" style="padding:10px">Nenhuma obra ainda. Clique em "Nova obra" ou peça ao assistente pra semear do conector.</div>';
  return h+'</div>';
}
function obrasTabDepara(){
  let h=`<div class="panel">${cotSecHead('link','De-para entre sistemas','conector ↔ TOTVS/coligada ↔ solicitações ↔ radar','')}
    <div class="dmini" style="margin-bottom:8px">Casamento automático pelo nome (a razão fantasia do TOTVS traz o codinome, ex.: "PEDRA AZUL - DIAMOND"). Confira; clique na linha p/ ajustar e marcar "conferido".</div>
    <div style="overflow-x:auto"><table class="mtable"><thead><tr><th style="text-align:left">Obra (conector)</th><th>Coligada</th><th style="text-align:left">Razão social (TOTVS)</th><th style="text-align:left">Compra (SC/PC)</th><th>CNPJ</th><th>CODLOC</th><th style="text-align:left">Nome nas solicitações</th><th>OK</th></tr></thead><tbody>`;
  h+=OBRAS_M.list.map(o=>`<tr style="cursor:pointer" onclick="obrasFichaAbrir(${o.id})">
    <td style="text-align:left"><b>${esc(o.nome)}</b></td>
    <td style="text-align:center">${o.coligada_cod?('<b>'+o.coligada_cod+'</b>'):'<span style="color:var(--pend)">?</span>'}</td>
    <td style="text-align:left;font-size:11px">${esc(String(o.coligada_nome||'').slice(0,40))||'—'}</td>
    <td style="text-align:left;font-size:11px">${o.compra_coligada_cod?(String(o.compra_coligada_cod)==='1'?'<span class="dchip" style="background:#e8f0fe;color:#1a56c4" title="compra guarda-chuva pela CAPREM/CAPRETZ">CAPREM</span>':esc(String(o.compra_coligada_nome||('col.'+o.compra_coligada_cod)).slice(0,18)))+(o.centro_custo?' · <b>'+esc(o.centro_custo)+'</b>':''):'<span class="muted">—</span>'}</td>
    <td style="font-size:11px;white-space:nowrap">${esc(o.cnpj||'—')}</td>
    <td>${esc(o.solic_obra_cod||'—')}</td>
    <td style="text-align:left;font-size:11px">${o.solic_nome?esc(o.solic_nome):'<span class="muted">—</span>'}${o.solic_nome&&opNorm(o.solic_nome)!==opNorm(o.nome)?' <span class="dchip" style="background:#fff3e0;color:#a15c00" title="nome diferente do conector">≠</span>':''}</td>
    <td style="text-align:center">${+o.de_para_ok?'<span class="dchip" style="background:var(--ok)">✓</span>':(CAN_EDIT?`<button class="btn-ghost" style="padding:2px 8px" onclick="event.stopPropagation();obrasReresolver(${o.id})" title="refazer o casamento automático">↻</button>`:'—')}</td>
  </tr>`).join('');
  return h+'</tbody></table></div></div>';
}
function obrasFichaAbrir(id){
  const o=id?(OBRAS_M.list.find(x=>x.id===id)||{id:0}):{id:0}; const ed=CAN_EDIT;
  let ov=document.getElementById('obraOverlay'); if(!ov){ov=document.createElement('div');ov.id='obraOverlay';ov.style.cssText='position:fixed;inset:0;background:rgba(15,25,20,.42);z-index:9999;display:flex;align-items:flex-start;justify-content:center;padding:24px;overflow:auto';document.body.appendChild(ov);} ov.onclick=()=>ov.remove();
  const fld=(lbl,i2,val,tipo,ph)=>`<label style="display:block"><span style="font-size:10px;font-weight:700;letter-spacing:.3px;text-transform:uppercase;color:var(--muted)">${lbl}</span>${ed?`<input id="${i2}" value="${esc(val==null?'':val)}" ${tipo==='num'?'type="number"':''} placeholder="${esc(ph||'')}" style="width:100%;margin-top:2px;padding:5px 8px;font-size:13px;box-sizing:border-box">`:`<div style="font-size:13.5px;margin-top:2px">${esc(val==null||val===''?'—':val)}</div>`}</label>`;
  const area=(lbl,i2,val,ph)=>`<label style="display:block"><span style="font-size:10px;font-weight:700;letter-spacing:.3px;text-transform:uppercase;color:var(--muted)">${lbl}</span>${ed?`<textarea id="${i2}" rows="2" placeholder="${esc(ph||'')}" style="width:100%;margin-top:2px;padding:5px 8px;font-size:13px;font-family:inherit;box-sizing:border-box">${esc(val||'')}</textarea>`:`<div style="font-size:13px;margin-top:2px;white-space:pre-wrap">${esc(val||'—')}</div>`}</label>`;
  const bloco=(ic,tit,inner)=>`<div style="border:1px solid var(--line);border-radius:10px;padding:12px 14px;margin-top:10px"><div style="display:flex;align-items:center;gap:6px;margin-bottom:9px"><span class="material-icons" style="font-size:17px;color:var(--verde)">${ic}</span><b style="font-size:13.5px">${tit}</b></div>${inner}</div>`;
  const g=(cols,inner)=>`<div style="display:grid;grid-template-columns:${cols};gap:9px">${inner}</div>`;
  const sel=(lbl,i2,val,opts,ph,onch)=>`<label style="display:block"><span style="font-size:10px;font-weight:700;letter-spacing:.3px;text-transform:uppercase;color:var(--muted)">${lbl}</span>${ed?`<select id="${i2}" ${onch?`onchange="${onch}"`:''} style="width:100%;margin-top:2px;padding:5px 8px;font-size:12.5px;box-sizing:border-box"><option value="">${esc(ph||'—')}</option>${opts.map(op=>`<option value="${esc(op.v)}" ${String(op.v)===String(val==null?'':val)?'selected':''}>${esc(op.t)}</option>`).join('')}</select>`:`<div style="font-size:13.5px;margin-top:2px">${esc((opts.find(op=>String(op.v)===String(val))||{}).t||(val==null||val===''?'—':val))}</div>`}</label>`;
  const colOpts=(OBRAS_M.coligadas||[]).map(c=>({v:c.cod,t:c.fantasia+' ('+c.cod+')'}));
  const ccHint=Object.entries(OBRAS_M.capretz_cc||{}).map(([k,v])=>k+'='+v).join(' · ');
  ov.innerHTML=`<div style="background:#fff;border-radius:14px;padding:18px 20px;max-width:720px;width:100%;box-shadow:0 12px 44px rgba(0,0,0,.22)" onclick="event.stopPropagation()">
    <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:10px">
      <div>${ed?`<input id="obf_nome" value="${esc(o.nome||'')}" placeholder="Nome da obra" style="font-size:18px;font-weight:800;border:none;border-bottom:1px solid var(--line);padding:2px 0;max-width:340px;width:100%">`:`<b style="font-size:18px">${esc(o.nome||'')}</b>`} ${obrStatusChip(o.status)}</div>
      <span class="material-icons" onclick="document.getElementById('obraOverlay').remove()" style="cursor:pointer;color:var(--muted)">close</span></div>
    ${(function(){const s=obraSeloFull(o);return s?`<div style="margin-top:10px;border:1px solid var(--line);border-radius:12px;overflow:hidden">${s}</div>`:'';})()}
    ${bloco('badge','Identificação',g('1fr 80px',fld('Cidade','obf_cidade',o.cidade)+fld('UF','obf_estado',o.estado))+`<div style="margin-top:9px">${fld('Endereço','obf_endereco',o.endereco)}</div>`+g('1fr 1fr',`<div style="margin-top:9px">${fld('Comprador responsável','obf_comprador_nome',o.comprador_nome)}</div><div style="margin-top:9px">${fld('Status','obf_status',o.status)}</div>`))}
    ${(o.pct_fisico!=null||o.crono_fim||(ed&&OBRAS_M.is_admin))?`<div style="border:1px solid var(--line);border-radius:10px;padding:12px 14px;margin-top:10px;background:#f7faf8">
      <div style="display:flex;align-items:center;gap:6px;margin-bottom:9px"><span class="material-icons" style="font-size:17px;color:var(--verde)">timeline</span><b style="font-size:13.5px">Cronograma & avanço físico</b>${o.crono_live?`<span title="lido em tempo real do Supabase do Planejamento" style="margin-left:auto;display:inline-flex;align-items:center;gap:4px;font-size:10px;font-weight:800;letter-spacing:.3px;color:var(--verde-d)"><span style="width:7px;height:7px;border-radius:50%;background:var(--verde);box-shadow:0 0 0 2px rgba(74,140,90,.22)"></span>AO VIVO · PLANEJAMENTO</span>`:`<span class="muted" style="font-size:10.5px;margin-left:auto">${o.pct_fisico!=null?('snapshot'+(o.cronograma_at?' · '+D(String(o.cronograma_at).slice(0,10)):'')):'sem cronograma vinculado'}</span>`}</div>
      ${o.pct_fisico!=null?`<div style="display:flex;align-items:center;gap:10px"><b style="font-size:22px;color:var(--verde-d)">${(+o.pct_fisico).toFixed(1).replace('.',',')}%</b><div style="flex:1;height:9px;background:#e6ebe8;border-radius:5px;overflow:hidden"><div style="height:100%;width:${Math.max(0,Math.min(100,+o.pct_fisico))}%;background:var(--verde)"></div></div></div>`:''}
      ${(o.pct_fisico!=null||o.crono_fim)?`<div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:9px;margin-top:10px">
        <div><span style="font-size:10px;font-weight:700;text-transform:uppercase;color:var(--muted)">Início</span><div style="font-size:13px">${o.crono_inicio?D(String(o.crono_inicio).slice(0,10)):'—'}</div></div>
        <div><span style="font-size:10px;font-weight:700;text-transform:uppercase;color:var(--muted)">Entrega prevista</span><div style="font-size:13px;font-weight:600">${o.crono_fim?D(String(o.crono_fim).slice(0,10)):'—'}</div></div>
        <div><span style="font-size:10px;font-weight:700;text-transform:uppercase;color:var(--muted)">Última medição</span><div style="font-size:13px">${o.crono_medicao?D(String(o.crono_medicao).slice(0,10)):'—'}</div></div>
      </div>`:''}
      ${o.cronograma_nome?`<div class="muted" style="font-size:10.5px;margin-top:8px"><span class="material-icons" style="font-size:12px;vertical-align:-2px">description</span> ${esc(o.cronograma_nome)}</div>`:''}
      ${(ed&&OBRAS_M.is_admin)?`<div style="margin-top:10px;padding-top:9px;border-top:1px dashed var(--line)"><label style="display:block"><span style="font-size:10px;font-weight:700;text-transform:uppercase;color:var(--muted)">Vincular cronograma (Planejamento)</span><select id="obf_crono_obra_id" style="width:100%;margin-top:2px;padding:5px 8px;font-size:12.5px;box-sizing:border-box"><option value="">— automático (casar por nome) —</option>${(OBRAS_M.cronos||[]).map(c=>`<option value="${esc(c.obra_id)}" ${String(c.obra_id)===String(o.crono_obra_id||'')?'selected':''}>${esc(c.nome)}${c.pct!=null?' — '+(+c.pct).toFixed(1).replace('.',',')+'%':''}</option>`).join('')}</select></label><div class="muted" style="font-size:10px;margin-top:4px">Use p/ obras cujo nome não casa sozinho (VS2, VS4, LTB-3, Café Filho…). Deixe automático se já casou certo.</div></div>`:''}
    </div>`:''}
    ${bloco('link','De-para (sistemas)',
        g('1fr 150px',sel('Coligada da obra (TOTVS)','obf_coligada_cod',o.coligada_cod,colOpts,'— escolher —','obrasColigadaChange(this)')+fld('CNPJ','obf_cnpj',o.cnpj))
        +`<div class="muted" style="font-size:10px;margin-top:3px" id="obf_coligada_razao">${esc(o.coligada_nome||'')}</div>`
        +`<div style="margin-top:11px;padding-top:9px;border-top:1px dashed var(--line)"><div style="font-size:10px;font-weight:700;text-transform:uppercase;color:var(--muted);margin-bottom:5px">Como a compra é emitida (SC/PC)</div>`
          +g('1fr 130px',sel('Compra emitida pela coligada','obf_compra_coligada_cod',o.compra_coligada_cod,colOpts,'— igual à obra —')+fld('Centro de custo','obf_centro_custo',o.centro_custo,'text','001'))
          +(ed?`<div class="muted" style="font-size:10px;margin-top:4px">Ex.: Cajá/Espazo/Prades/Piamonte/Licel compram pela <b>CAPREM (CAPRETZ, col.1)</b> com centro de custo. CAPRETZ: ${esc(ccHint)}</div>`:'')
        +`</div>`
        +g('1fr 110px 90px',`<div style="margin-top:11px">${fld('Nome nas solicitações','obf_solic_nome',o.solic_nome)}</div><div style="margin-top:11px">${fld('CODLOC','obf_solic_obra_cod',o.solic_obra_cod)}</div><div style="margin-top:11px">${fld('Radar id','obf_radar_obra_id',o.radar_obra_id,'num')}</div>`)
        +(ed?`<label style="display:flex;align-items:center;gap:6px;margin-top:11px;font-size:12px"><input type="checkbox" id="obf_de_para_ok" ${+o.de_para_ok?'checked':''}> de-para conferido ✓ ${o.id?`<button class="btn-ghost" style="padding:2px 8px;margin-left:6px" onclick="obrasReresolver(${o.id})">↻ re-resolver automático</button>`:''}</label>`:''))}
    <div style="border:1px solid var(--line);border-radius:10px;padding:12px 14px;margin-top:10px">
      <div style="display:flex;align-items:center;gap:6px;margin-bottom:9px"><span class="material-icons" style="font-size:17px;color:var(--verde)">foundation</span><b style="font-size:13.5px">Características do empreendimento</b>${(ed&&OBRAS_M.is_admin&&o.id&&(o.crono_live||o.crono_obra_id))?`<button class="btn-ghost" id="obfExtrairBtn" style="margin-left:auto;padding:3px 10px;font-size:11.5px" onclick="obrasExtrairCrono(${o.id})"><span class="material-icons" style="font-size:14px;vertical-align:-3px">auto_awesome</span> Extrair do cronograma</button>`:''}</div>
      ${g('1fr 1fr 1fr 1fr',fld('Torres','obf_torres',o.torres,'num')+fld('Pavimentos','obf_pavimentos',o.pavimentos,'num')+fld('Subsolos','obf_subsolos',o.subsolos,'num')+fld('Unidades','obf_unidades',o.unidades,'num'))}
      <div style="margin-top:9px">${area('Tipologias / metragens','obf_tipologias',o.tipologias,'ex.: 2 e 3 dorms · 55–78 m²')}</div>
      ${g('1fr 1fr',`<div style="margin-top:9px">${fld('Padrão','obf_padrao',o.padrao,'text','alto / médio / econômico')}</div><div></div>`)}
      <div style="margin-top:9px">${area('Método construtivo','obf_metodo_construtivo',o.metodo_construtivo,'ex.: alvenaria estrutural, concreto armado…')}</div>
      <div style="margin-top:9px">${area('Itens de áreas comuns','obf_areas_comuns',o.areas_comuns,'ex.: piscina, salão de festas, academia, playground…')}</div>
    </div>
    ${bloco('description','Links & observações',g('1fr 1fr',fld('Link do cronograma','obf_link_cronograma',o.link_cronograma)+fld('Pasta de projetos','obf_link_projetos',o.link_projetos))+`<div style="margin-top:9px">${fld('Localização (maps)','obf_link_local',o.link_local)}</div><div style="margin-top:9px">${area('Observações','obf_observacoes',o.observacoes)}</div>`)}
    ${ed?`<div style="display:flex;justify-content:flex-end;gap:8px;margin-top:14px"><button class="btn-ghost" onclick="document.getElementById('obraOverlay').remove()">Cancelar</button><button class="btn-prim" onclick="obrasSalvar(${o.id||0})"><span class="material-icons" style="font-size:15px;vertical-align:-3px">save</span> Salvar ficha</button></div>`:''}
  </div>`;
}
async function obrasSalvar(id){ const v=i=>{const e=document.getElementById(i);return e?(e.type==='checkbox'?(e.checked?1:0):e.value):undefined;};
  const ficha={id}; ['nome','cidade','estado','status','coligada_cod','coligada_nome','cnpj','compra_coligada_cod','centro_custo','solic_nome','solic_obra_cod','radar_obra_id','endereco','comprador_nome','torres','pavimentos','subsolos','unidades','tipologias','metodo_construtivo','areas_comuns','padrao','observacoes','link_cronograma','link_projetos','link_local','de_para_ok','crono_obra_id'].forEach(k=>{const val=v('obf_'+k);if(val!==undefined)ficha[k]=val;});
  if(!(ficha.nome||'').trim()){toast('Informe o nome da obra');return;}
  try{ const r=await (await fetch('actions/obras.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({acao:'salvar',me:EU&&EU.bitrix_id,ficha})})).json();
    if(r.error){toast(r.error);return;} toast('Ficha salva'); const ov=document.getElementById('obraOverlay');if(ov)ov.remove(); obrasLoad();
  }catch(e){toast('Falha: '+e.message);} }
async function obrasReresolver(id){ try{ const r=await (await fetch('actions/obras.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({acao:'reresolver',me:EU&&EU.bitrix_id,id})})).json();
  if(r.error){toast(r.error);return;} toast('De-para refeito automaticamente'); const ov=document.getElementById('obraOverlay');if(ov)ov.remove(); obrasLoad();
  }catch(e){toast('Falha: '+e.message);} }
function obrasColigadaChange(selEl){ const c=(OBRAS_M.coligadas||[]).find(x=>String(x.cod)===String(selEl.value)); const cn=document.getElementById('obf_cnpj'), rz=document.getElementById('obf_coligada_razao'); if(c){ if(cn)cn.value=c.cnpj||''; if(rz)rz.textContent=c.nome||''; } else { if(rz)rz.textContent=''; } }
async function obrasExtrairCrono(id){ const b=document.getElementById('obfExtrairBtn'); if(b){b.disabled=true;b.innerHTML='<span class="material-icons" style="font-size:14px;vertical-align:-3px">hourglass_top</span> Lendo cronograma…';}
  try{ const r=await (await fetch('actions/obras.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({acao:'extrair_caracteristicas',me:EU&&EU.bitrix_id,id})})).json();
    if(r.error){toast(r.error); if(b){b.disabled=false;b.innerHTML='<span class="material-icons" style="font-size:14px;vertical-align:-3px">auto_awesome</span> Extrair do cronograma';} return;}
    const d=r.draft||{}; const set=(i,val)=>{const e=document.getElementById(i); if(e&&val!=null&&String(val).trim()!=='')e.value=val;};
    set('obf_torres',d.torres); set('obf_pavimentos',d.pavimentos); set('obf_subsolos',d.subsolos); set('obf_unidades',d.unidades);
    set('obf_tipologias',d.tipologias); set('obf_metodo_construtivo',d.metodo_construtivo); set('obf_areas_comuns',d.areas_comuns); set('obf_padrao',d.padrao);
    toast('Preenchido do cronograma ('+(r.n_tarefas||0)+' tarefas · confiança '+(d.confianca||'—')+'). Revise e salve.');
    if(b){b.disabled=false;b.innerHTML='<span class="material-icons" style="font-size:14px;vertical-align:-3px">auto_awesome</span> Extrair de novo';}
  }catch(e){toast('Falha: '+e.message); if(b){b.disabled=false;b.innerHTML='<span class="material-icons" style="font-size:14px;vertical-align:-3px">auto_awesome</span> Extrair do cronograma';}} }
const MENUS=[['dashboard','Dashboard'],['radar','Radar de Aquisições'],['matriz','Matriz'],['cotacoes','Cotações'],['solicitacoes','Solicitações'],['obras','Obras'],['oportunidades','Oportunidades'],['updates','Atualizações'],['audit','Auditoria'],['config','Configurações']];
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
  CAN_RESP  = IS_ADMIN || !!(EU && EU.perm_responsaveis);   // atribuir responsável em lote (independe de editar_escopo)
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
  const auth = !!(EU&&EU.autorizado);
  const allow = auth?(EU.menus||[]):[];
  // Admin com uma SELEÇÃO de menus definida → respeita a seleção dele (pode esconder itens de si mesmo p/ "pintar a tela").
  // Config e Radar IA ficam sempre visíveis p/ admin (evita se trancar / oráculo é leitura).
  const adminSel = IS_ADMIN && Array.isArray(EU&&EU.menus);
  document.querySelectorAll('.nav a[data-menu]').forEach(a=>{
    const m=a.getAttribute('data-menu');
    let show;
    if(m==='oraculo'||m==='solicitacoes'||m==='obras') show = auth;        // Radar IA + Solicitações + Obras (referência) p/ todo autorizado
    else if(m==='config') show = IS_ADMIN||allow.includes('config')||CAN_RESP;  // Config nunca some p/ admin
    else if(adminSel) show = allow.includes(m);                            // admin escolheu → mostra só o marcado
    else show = IS_ADMIN||allow.includes(m);
    a.style.display = show?'':'none';
  });
  const bn=document.getElementById('btnNovo'); if(bn) bn.style.display=CAN_EDIT?'':'none'; // só quem edita cria item
}
function toggleSide(){
  const app=document.getElementById('app');
  const c=!app.classList.contains('sidecollapsed');
  app.classList.toggle('sidecollapsed', c);
  try{ localStorage.setItem('sideCollapsed', c?'1':'0'); }catch(e){}
}
/* ===== Config » sub-aba Aprendizado (receitas) ===== */
let RCDATA=null, RC_OPEN=new Set();
function cfgTab(t){
  const canR = IS_ADMIN || (typeof CAN_RESP!=='undefined' && CAN_RESP);
  // só admin vê Usuários & Aprendizado; Responsáveis abre p/ admin OU perm_responsaveis
  document.getElementById('cfgtab-users').style.display = IS_ADMIN?'':'none';
  document.getElementById('cfgtab-receitas').style.display = IS_ADMIN?'':'none';
  document.getElementById('cfgtab-resp').style.display = canR?'':'none';
  const eb=document.getElementById('cfgtab-email'); if(eb) eb.style.display = IS_ADMIN?'':'none';
  const permitida={users:IS_ADMIN, receitas:IS_ADMIN, resp:canR, email:IS_ADMIN};
  if(!permitida[t]) t = IS_ADMIN?'users':(canR?'resp':'users');
  document.getElementById('cfg-users').style.display = t==='users'?'':'none';
  document.getElementById('cfg-receitas').style.display = t==='receitas'?'':'none';
  document.getElementById('cfg-resp').style.display = t==='resp'?'':'none';
  const ce=document.getElementById('cfg-email'); if(ce) ce.style.display = t==='email'?'':'none';
  const ab=document.getElementById('cfgAddBtn'); if(ab) ab.style.display = (t==='users'&&IS_ADMIN)?'':'none';
  ['users','resp','receitas','email'].forEach(x=>{ const b=document.getElementById('cfgtab-'+x); if(b){ b.style.background = x===t?'var(--verde)':''; b.style.color = x===t?'#fff':''; } });
  if(t==='receitas') renderReceitas();
  if(t==='resp') renderRespLote();
  if(t==='email') cfgEmailLoad();
}
/* ===== Configurações › E-mail (disparo): conta SMTP + envio-teste ===== */
async function cfgEmailLoad(){ const w=document.getElementById('cfgEmailWrap'); if(!w)return; w.innerHTML='<div class="dempty">Carregando…</div>';
  try{ const cfg=await (await fetch('actions/email.php?config=1&me='+encodeURIComponent((EU&&EU.bitrix_id)||''))).json();
    if(cfg.error){ w.innerHTML='<div class="panel"><div class="empty">'+esc(cfg.error)+'</div></div>'; return; }
    const cronUrl=cfg.cron_token?new URL('actions/inbox.php?cron='+encodeURIComponent(cfg.cron_token),location.href).href:'';
    w.innerHTML=`<div class="panel" style="max-width:640px">
      ${cotSecHead('mail','Conta de e-mail (envio + leitura)','SMTP dispara as cotações; IMAP lê as respostas — mesma conta/senha (fica só no servidor)','<span class="dchip" style="background:'+(cfg.configurada?'var(--ok)':'var(--pend)')+'">'+(cfg.configurada?'configurada ✓':'falta a senha')+'</span>')}
      <div style="display:grid;grid-template-columns:1fr 100px 110px;gap:10px">${cotFld('Servidor','<input id="ceHost" value="'+esc(cfg.host||'')+'" style="width:100%">')}${cotFld('Porta SMTP','<input id="cePort" type="number" value="'+esc(cfg.port||465)+'" style="width:100%">')}${cotFld('Porta IMAP','<input id="ceImapPort" type="number" value="'+esc(cfg.imap_port||993)+'" style="width:100%" title="leitura das respostas (Fase 4)">')}</div>
      <div style="margin-top:8px">${cotFld('Usuário (e-mail remetente)','<input id="ceUser" value="'+esc(cfg.user||'')+'" style="width:100%">')}</div>
      <div style="margin-top:8px">${cotFld('Senha (vazio mantém a atual)','<input id="ceSenha" type="password" autocomplete="new-password" placeholder="••••••••" style="width:100%">')}</div>
      <div style="margin-top:10px"><button class="btn-prim" onclick="cfgEmailSalvar()"><span class="material-icons" style="font-size:15px;vertical-align:-3px">save</span> Salvar conta</button></div>
      <div style="margin-top:16px;border-top:1px solid var(--line);padding-top:12px">${cotSecHead('outbox','Enviar um teste (SMTP)','manda um e-mail de teste pra você conferir se o envio funciona','')}
        <div style="display:flex;gap:8px;align-items:flex-end;flex-wrap:wrap"><div style="flex:1;min-width:220px">${cotFld('Para (seu e-mail)','<input id="ceTeste" placeholder="voce@email.com" style="width:100%">')}</div>
        <button class="btn-prim" onclick="cfgEmailTeste()"><span class="material-icons" style="font-size:15px;vertical-align:-3px">send</span> Enviar teste</button></div></div>
      <div style="margin-top:14px;border-top:1px solid var(--line);padding-top:12px">${cotSecHead('mark_email_unread','Testar leitura (IMAP)','conecta na caixa e conta as mensagens (incl. Spam/Lixo) — não lê conteúdo nem usa IA','<button class="btn-ghost" onclick="cfgImapTeste()"><span class="material-icons" style="font-size:15px;vertical-align:-3px">sync</span> Testar leitura</button>')}<div id="ceImapRes" class="dmini" style="margin-top:2px"></div></div>
      ${cronUrl?`<div style="margin-top:14px;border-top:1px solid var(--line);padding-top:12px">${cotSecHead('schedule','Varredura automática (cron)','o servidor busca respostas sozinho — configure UMA vez no cPanel','')}
        <div class="dmini">No cPanel › <b>Cron Jobs</b>, adicione uma tarefa <b>a cada 1 hora</b> com este comando:</div>
        <div id="cronCmd" style="margin-top:5px;background:#f4f7f5;border:1px solid var(--line);border-radius:8px;padding:8px 10px;font-size:11px;word-break:break-all;font-family:monospace">wget -q -O /dev/null "${esc(cronUrl)}"</div>
        <div style="margin-top:6px"><button class="btn-ghost" style="padding:4px 10px" onclick="navigator.clipboard.writeText(document.getElementById('cronCmd').textContent).then(()=>toast('Comando copiado')).catch(()=>toast('Copie manualmente'))"><span class="material-icons" style="font-size:14px;vertical-align:-3px">content_copy</span> Copiar comando</button></div>
        <div class="dmini" style="margin-top:6px;color:var(--muted)">É um link secreto (token) — não compartilhe. Enquanto o cron não roda, a busca também dispara sozinha ao abrir o módulo Cotações.</div></div>`:''}
    </div>`;
  }catch(e){ w.innerHTML='<div class="panel"><div class="empty">Falha ao carregar.</div></div>'; } }
async function cfgEmailSalvar(){ const g=id=>((document.getElementById(id)||{}).value||'');
  try{ const r=await (await fetch('actions/email.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({acao:'config',me:EU&&EU.bitrix_id,host:g('ceHost'),port:Number(g('cePort'))||465,imap_port:Number(g('ceImapPort'))||993,user:g('ceUser'),senha:g('ceSenha')})})).json();
    if(r.error){toast(r.error);return;} toast('Conta salva'); cfgEmailLoad(); }catch(e){toast('Falha: '+e.message);} }
async function cfgEmailTeste(){ const to=((document.getElementById('ceTeste')||{}).value||'').trim(); if(!to){toast('Informe seu e-mail');return;}
  toast('Enviando teste…');
  try{ const r=await (await fetch('actions/email.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({acao:'enviar',me:EU&&EU.bitrix_id,teste:to,cotacao_id:0,assunto:'Teste de envio — Cockpit de Suprimentos',corpo:'Este é um e-mail de teste do disparo de cotações. Se você recebeu, o envio por SMTP está funcionando.\n\nCockpit de Suprimentos — Caprem'})})).json();
    if(r.error){toast(r.error);return;} toast(r.msg||'Teste enviado'); }catch(e){toast('Falha: '+e.message);} }
async function cfgImapTeste(){ const el=document.getElementById('ceImapRes'); if(el){el.textContent='Conectando…';el.style.color='var(--muted)';}
  try{ const r=await (await fetch('actions/inbox.php?probe=1&me='+encodeURIComponent((EU&&EU.bitrix_id)||''))).json();
    if(r.error){ if(el){el.textContent='❌ '+r.error;el.style.color='var(--pend)';} return; }
    if(el){ el.textContent='✅ Conectou em '+(r.host||'')+':'+(r.porta||993)+' — '+r.mensagens+' mensagem(ns) na caixa.'; el.style.color='var(--verde-d)'; } }
  catch(e){ if(el){el.textContent='Falha: '+e.message;el.style.color='var(--pend)';} } }

/* ===== Responsáveis EM LOTE (Configurações) — atribui comprador por obra/grupo/seleção ===== */
let RL={obras:[], itens:[], sel:new Set()};
function rlObras(){ return (typeof OBRAS!=='undefined'&&OBRAS)?OBRAS.slice():[]; }
function rlObrasEdit(){   // obras que o usuário PODE editar (o endpoint exige can_edit_obra) — admin/'todas' = todas
  const all=rlObras();
  if(IS_ADMIN || (EU&&EU.editar_escopo==='todas')) return all;
  const ed=((EU&&EU.obras_editar)||[]).map(Number);
  return all.filter(o=>ed.includes(Number(o.id)));
}
function renderRespLote(){
  const list=rlObrasEdit();
  if(!list.length){ document.getElementById('rlwrap').innerHTML='<div class="empty">Você não tem obras liberadas para editar responsáveis. Peça ao administrador acesso de edição às obras.</div>';
    const k=document.getElementById('rlKpi'); if(k)k.innerHTML=''; const l=document.getElementById('rlObraLbl'); if(l)l.textContent='—'; return; }
  // default: TODAS as obras editáveis (o caso do usuário: atribuir um grupo em lote em todas)
  if(!RL.obras.length) RL.obras=list.map(o=>Number(o.id));
  else { RL.obras=RL.obras.filter(id=>list.some(o=>Number(o.id)===id)); if(!RL.obras.length) RL.obras=[Number(list[0].id)]; }
  const rs=document.getElementById('rlResp');
  if(rs) rs.innerHTML='<option value="">— escolher responsável —</option>'+(RESP||[]).map(u=>`<option value="${esc(u.nome)}">${esc(u.nome)}</option>`).join('');
  // "tornar padrão" é mudança GLOBAL (template) → só p/ admin ou quem edita TODAS as obras
  const pw=document.getElementById('rlPadraoWrap'); if(pw) pw.style.display=(IS_ADMIN||(CAN_RESP&&EU&&EU.editar_escopo==='todas'))?'':'none';
  rlObraLbl(); rlLoad();
}
// ---- dropdown MULTI-OBRA ----
function rlObraToggle(e){ if(e)e.stopPropagation(); const m=document.getElementById('rlObraMenu'); if(!m)return; const ab=m.style.display==='none'||!m.style.display; m.style.display=ab?'block':'none'; if(ab) rlObraMenu(); }
document.addEventListener('click',e=>{ const p=document.getElementById('rlObraPick'),m=document.getElementById('rlObraMenu'); if(m&&m.style.display==='block'&&p&&!p.contains(e.target)) m.style.display='none'; });
function rlObraMenu(){ const m=document.getElementById('rlObraMenu'); if(!m)return; const list=rlObrasEdit();
  m.innerHTML=`<div style="display:flex;justify-content:space-between;align-items:center;padding:2px 6px 6px;border-bottom:1px solid var(--line);margin-bottom:4px"><span style="font-size:10px;font-weight:800;letter-spacing:.6px;color:var(--muted)">SELECIONE AS OBRAS</span><button class="btn-ghost" style="padding:2px 8px;font-size:11px" onclick="rlObraTodas(event)">Todas</button></div>`+
    list.map(o=>{ const on=RL.obras.includes(Number(o.id));
      return `<label style="display:flex;align-items:center;gap:9px;padding:6px 8px;border-radius:7px;cursor:pointer;font-size:12.5px" onmouseover="this.style.background='#eff7f1'" onmouseout="this.style.background=''"><input type="checkbox" ${on?'checked':''} onchange="rlObraSet(${o.id},this.checked)"><span style="width:9px;height:9px;border-radius:50%;background:${obraCor(o.id)};flex:0 0 auto"></span><span style="flex:1"><b>${esc(o.nome)}</b></span></label>`; }).join('');
}
function rlObraSet(id,on){ id=Number(id);
  if(on){ if(!RL.obras.includes(id)) RL.obras.push(id); }
  else { RL.obras=RL.obras.filter(x=>x!==id); if(!RL.obras.length){ toast('Ao menos uma obra'); RL.obras=[id]; rlObraMenu(); return; } }
  rlObraLbl(); rlObraMenu(); rlLoad();
}
function rlObraTodas(e){ if(e)e.stopPropagation(); RL.obras=rlObrasEdit().map(o=>Number(o.id)); rlObraLbl(); rlObraMenu(); rlLoad(); }
function rlObraLbl(){ const l=document.getElementById('rlObraLbl'); if(!l)return; const list=rlObrasEdit();
  if(RL.obras.length>=list.length) l.textContent='Todas as obras';
  else if(RL.obras.length===1){ const o=list.find(x=>Number(x.id)===RL.obras[0]); l.textContent=o?o.nome:'1 obra'; }
  else l.textContent=RL.obras.length+' obras'; }
async function rlLoad(){
  RL.sel=new Set(); const box=document.getElementById('rlwrap'); box.innerHTML='<div class="empty">Carregando…</div>';
  try{
    const onome={}; rlObrasEdit().forEach(o=>onome[Number(o.id)]=o.nome);
    const rs=await Promise.all(RL.obras.map(async oid=>{ const u='actions/matriz.php'+(oid!==1?('?obra='+oid+'&'):'?')+'_='+Date.now(); const d=await (await fetch(u)).json().catch(()=>null); return {oid,d}; }));
    const itens=[];
    for(const {oid,d} of rs){ if(!d||d.error||!d.itens) continue;
      d.itens.forEach(i=>itens.push({obra_id:oid, obra_nome:(d.obra&&d.obra.nome)||onome[oid]||('obra '+oid), ordem:i.ordem, nome:i.nome, grupo:i.grupo, curva:i.curva, responsavel:(i.responsavel||'').trim(), padrao:(i.responsavel_padrao||'').trim(), temData:!!(i.data_necessaria||i.fim_cotacao)})); }
    RL.itens=itens;
    const g=document.getElementById('rlGrupo'), keep=g.value;
    g.innerHTML='<option value="">Todos os grupos</option>'+[...new Set(itens.map(i=>i.grupo).filter(Boolean))].sort().map(x=>`<option>${esc(x)}</option>`).join(''); if(keep) g.value=keep;
    rlRender();
  }catch(e){ box.innerHTML='<div class="empty">Falha: '+esc(e.message)+'</div>'; }
}
function rlKey(i){ return i.obra_id+':'+i.ordem; }
function rlFiltered(){
  const fg=val('rlGrupo'), fs=val('rlStatus'), q=(document.getElementById('rlQ').value||'').toLowerCase();
  return RL.itens.filter(i=>(!fg||i.grupo===fg)&&(!fs||(fs==='sem'?!i.responsavel:!!i.responsavel))&&(!q||i.nome.toLowerCase().includes(q)));
}
function rlRender(){
  const box=document.getElementById('rlwrap'), fi=rlFiltered();
  // ---- CARDS (todas as obras selecionadas) ----
  const tot=RL.itens.length, com=RL.itens.filter(i=>i.responsavel).length, semd=tot-com, pct=tot?Math.round(100*com/tot):0;
  const comData=RL.itens.filter(i=>i.temData).length;
  const compradores={}; RL.itens.forEach(i=>{ if(i.responsavel) compradores[i.responsavel]=(compradores[i.responsavel]||0)+1; });
  const nComp=Object.keys(compradores).length, media=nComp?Math.round(com/nComp):0;
  const k=document.getElementById('rlKpi');
  if(k) k.innerHTML=`
    <div class="kpi"><div class="v">${tot}</div><div class="l">itens · ${RL.obras.length} obra${RL.obras.length>1?'s':''}</div></div>
    <div class="kpi"><div class="v gold">${pct}%</div><div class="l">atribuídos · ${com} de ${tot}</div></div>
    <div class="kpi"><div class="v ${semd?'alert':''}">${semd}</div><div class="l">sem dono (faltam)</div></div>
    <div class="kpi"><div class="v">${nComp}</div><div class="l">compradores · ~${media}/pessoa</div></div>
    <div class="kpi"><div class="v">${comData}</div><div class="l">com data de cronograma</div></div>`;
  // ---- tabela (agrupada por grupo; coluna Obra quando multi) ----
  const multi=RL.obras.length>1, cols=multi?7:6;
  let html='<table><thead><tr><th style="width:34px"><input type="checkbox" id="rlAll" onclick="rlToggleAll(this.checked)" title="selecionar os filtrados"></th><th>Item</th>'+(multi?'<th>Obra</th>':'')+'<th>Grupo</th><th>Curva</th><th>Responsável atual</th><th>Padrão</th></tr></thead><tbody>';
  const byG={}; fi.forEach(i=>{ (byG[i.grupo||'—']=byG[i.grupo||'—']||[]).push(i); });
  Object.keys(byG).forEach(gr=>{
    html+=`<tr class="grp-h"><td colspan="${cols}">${esc(gr)}</td></tr>`;
    byG[gr].forEach(i=>{ const key=rlKey(i);
      html+=`<tr><td><input type="checkbox" ${RL.sel.has(key)?'checked':''} onclick="rlSel('${key}',this.checked)"></td>
        <td>${esc(i.nome)}</td>${multi?`<td><span class="dgm" style="background:${obraCor(i.obra_id)};margin-right:5px"></span><span class="muted" style="font-size:11.5px">${esc(i.obra_nome)}</span></td>`:''}<td class="muted">${esc(i.grupo||'')}</td><td>${esc(i.curva||'')}</td>
        <td>${i.responsavel?esc(i.responsavel):'<span class="muted">— sem —</span>'}</td>
        <td class="muted" title="padrão do serviço (novas obras herdam)">${i.padrao?esc(i.padrao):'—'}</td></tr>`; });
  });
  if(!fi.length) html+=`<tr><td colspan="${cols}" class="empty">Nenhum item nesse filtro.</td></tr>`;
  box.innerHTML=html+'</tbody></table>';
  const all=document.getElementById('rlAll'); if(all) all.checked=fi.length>0 && fi.every(i=>RL.sel.has(rlKey(i)));
  rlCount();
}
function rlSel(key,on){ on?RL.sel.add(key):RL.sel.delete(key);
  const all=document.getElementById('rlAll'); if(all){ const fi=rlFiltered(); all.checked=fi.length>0 && fi.every(i=>RL.sel.has(rlKey(i))); }
  rlCount(); }
function rlToggleAll(on){ rlFiltered().forEach(i=>{ on?RL.sel.add(rlKey(i)):RL.sel.delete(rlKey(i)); }); rlRender(); }
function rlCount(){ const el=document.getElementById('rlSelCount'); if(el) el.textContent=RL.sel.size+' selecionado'+(RL.sel.size===1?'':'s'); }
async function rlAtribuir(){ const nome=val('rlResp'); if(!nome){ toast('Escolha um responsável'); return; }
  await rlAssign(nome, `Atribuir “${nome}” a ${RL.sel.size} item(ns)?`); }
async function rlLimpar(){ await rlAssign('', `Limpar o responsável de ${RL.sel.size} item(ns)?`); }
async function rlAssign(nome, msg){
  if(!RL.sel.size){ toast('Selecione ao menos um item'); return; }
  if(!confirm(msg)) return;
  const pchk=document.getElementById('rlPadrao');
  const tornarPadrao = !!(nome && pchk && pchk.checked && pchk.offsetParent!==null);  // só ao ATRIBUIR e se visível+marcado
  // agrupa a seleção por OBRA (o endpoint é por obra)
  const porObra={}; [...RL.sel].forEach(key=>{ const p=key.split(':'); const ob=Number(p[0]), ordem=Number(p[1]); (porObra[ob]=porObra[ob]||[]).push(ordem); });
  try{
    let n=0, padr=0;
    for(const ob of Object.keys(porObra)){
      const r=await (await fetch('actions/responsaveis_lote.php',{method:'POST',headers:{'Content-Type':'application/json'},
        body:JSON.stringify({acao:'atribuir',me:EU&&EU.bitrix_id,obra:Number(ob),servico_ids:porObra[ob],responsavel:nome,tornar_padrao:tornarPadrao?1:0})})).json();
      if(r.error){ toast(r.error); return; } n+=(r.n||0); padr+=(r.padrao||0);
    }
    toast(n+' item(ns) atualizado(s)'+(padr?(' · '+padr+' viraram padrão'):''));
    RL.itens.forEach(i=>{ if(RL.sel.has(rlKey(i))){ i.responsavel=nome; if(tornarPadrao) i.padrao=nome; } }); if(pchk) pchk.checked=false; RL.sel=new Set();
    if(typeof MAT!=='undefined') MAT=null; if(typeof load==='function') load();
    rlRender();
  }catch(e){ toast('Falha: '+e.message); }
}
async function rlPreencherPadrao(){
  const alvo=RL.itens.filter(i=>!i.responsavel && i.padrao).length;
  if(!alvo){ toast('Nenhum item vazio COM padrão nas obras selecionadas'); return; }
  if(!confirm('Preencher '+alvo+' item(ns) sem responsável com o padrão do serviço, nas '+RL.obras.length+' obra(s) selecionada(s)?')) return;
  try{ let n=0;
    for(const ob of RL.obras){
      const r=await (await fetch('actions/responsaveis_lote.php',{method:'POST',headers:{'Content-Type':'application/json'},
        body:JSON.stringify({acao:'preencher_padrao',me:EU&&EU.bitrix_id,obra:ob})})).json();
      if(!r.error) n+=(r.n||0);
    }
    toast(n+' item(ns) preenchido(s) com o padrão');
    if(typeof MAT!=='undefined') MAT=null; if(typeof load==='function') load(); await rlLoad();
  }catch(e){ toast('Falha: '+e.message); }
}
function rcMetodoSel(){ const el=document.getElementById('rcmetodo'); return (el&&el.value)||'concreto armado convencional'; }
function rcMetodoChange(){ renderReceitas(); }
function rcObras(){ return (typeof OBRAS!=='undefined'&&OBRAS)?OBRAS.slice():[]; }
// dropdowns "Aprender de uma obra" / "Aplicar em uma obra"
function rcMenu(kind,e){ if(e)e.stopPropagation();
  document.querySelectorAll('#cfg-receitas .rcmenu').forEach(x=>{ if(x.id!=='rcmenu-'+kind) x.style.display='none'; });
  const m=document.getElementById('rcmenu-'+kind); if(!m)return;
  if(m.style.display==='block'){ m.style.display='none'; return; }
  const obras=rcObras().filter(o=> kind==='aplicar' ? Number(o.id)>=2 : true);
  m.innerHTML = obras.length ? obras.map(o=>`<div class="rcmi" onclick="rc${kind==='aprender'?'Aprender':'Aplicar'}(${Number(o.id)})">${esc(o.nome)}</div>`).join('')
    : `<div class="rcmi muted">${rcObras().length?'nenhuma obra elegível':'carregando…'}</div>`;
  m.style.display='block';
}
document.addEventListener('click',e=>{ if(!(e.target.closest&&e.target.closest('#cfg-receitas .rcpick'))) document.querySelectorAll('#cfg-receitas .rcmenu').forEach(x=>x.style.display='none'); });
async function rcAprender(obraId){
  document.querySelectorAll('#cfg-receitas .rcmenu').forEach(x=>x.style.display='none');
  const nome=(rcObras().find(o=>Number(o.id)===Number(obraId))||{}).nome||('obra '+obraId);
  if(!confirm('Aprender as receitas a partir da curadoria atual de “'+nome+'”?\n\nRE-DERIVA a regra de cada item dessa obra. As anotações/cuidados são preservados; ajustes manuais de âncora/método podem ser sobrescritos.')) return;
  toast('Aprendendo de '+nome+'…');
  try{ const r=await (await fetch('actions/receitas.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({acao:'derivar',obra_id:Number(obraId),me:EU&&EU.bitrix_id})})).json();
    if(r.error){ toast(r.error); return; }
    toast((r.derivadas||0)+' receitas aprendidas de '+r.obra); RCDATA=null; renderReceitas();
  }catch(e){ toast('Falha: '+e.message); }
}
async function rcAplicar(obraId){
  document.querySelectorAll('#cfg-receitas .rcmenu').forEach(x=>x.style.display='none');
  if(Number(obraId)<2){ toast('A obra de origem do aprendizado não recebe auto-vínculo.'); return; }
  const nome=(rcObras().find(o=>Number(o.id)===Number(obraId))||{}).nome||('obra '+obraId);
  if(!confirm('Aplicar o dicionário em “'+nome+'”?\n\nPreenche só o que está VAZIO; tudo entra como sugerido 🤖 (não curado).')) return;
  toast('Aplicando o dicionário em '+nome+'… (pode levar ~1 min)');
  try{ const r=await (await fetch('actions/aplicar_receitas.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({acao:'aplicar',obra_id:Number(obraId),me:EU&&EU.bitrix_id})})).json();
    if(r.error){ toast(r.error); return; }
    MAT=null;   // a matriz muda → invalida o cache
    toast(`Auto-vínculo em ${r.obra}: ${r.sugeridos.crono} cronogramas · ${r.sugeridos.verba} verbas · ${r.sugeridos.quant} quantitativos`);
  }catch(e){ toast('Falha: '+e.message); }
}
function rcNovoItem(){
  const nome=(prompt('Nome do novo item (aquisição):')||'').trim(); if(!nome)return;
  const grupos=[...new Set((RCDATA&&RCDATA.receitas||[]).map(r=>r.grupo).filter(Boolean))];
  const grupo=(prompt('Grupo do item:'+(grupos.length?('\n\nexistentes: '+grupos.join(' · ')):''))||'').trim(); if(!grupo)return;
  const curva=((prompt('Curva (A / B / C):','C')||'C').trim().toUpperCase())||'C';
  (async()=>{
    try{ const r=await (await fetch('actions/receitas.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({acao:'criar_item',nome,grupo,curva,metodo_construtivo:rcMetodoSel(),me:EU&&EU.bitrix_id})})).json();
      if(r.error){ toast(r.error); return; }
      toast('Item criado: '+r.nome); RC_OPEN.add('sid:'+r.servico_id); RCDATA=null; renderReceitas();
    }catch(e){ toast('Falha: '+e.message); }
  })();
}
function rcPillTxt(dim,r){
  if(dim==='crono'){ const c=r.crono||{}; if(!c.ancora_nome) return 'automático'; const p=c.ancora_nome.split(';').map(s=>s.trim()).filter(Boolean); return '“'+esc(p[0]||'')+'”'+(p.length>1?(' +'+(p.length-1)):''); }
  if(dim==='verba'){ const v=r.verba||{}; if(!v.metodo) return '—';
    if(v.metodo==='composicao') return 'composição · '+((v.insumos||[]).length)+' insumo(s)';
    if(v.metodo==='analitico') return 'analítico · '+((v.linhas||[]).length)+' linha(s)';
    return 'manual'; }
  if(dim==='quant'){ const q=r.quant||{}; return q.fonte?esc(q.fonte):'—'; }
  return '';
}
function rcList(arr,fn){ return (arr&&arr.length)?('<ul style="margin:5px 0 0;padding-left:18px">'+arr.map(fn).join('')+'</ul>'):''; }
function rcEditor(r){
  const sid=r.servico_id, c=r.crono||{}, v=r.verba||{}, qd=r.quant||{};
  const exs=(v.exclusoes||[]).map(e=>e.insumo).join('; ');
  const opt=(cur,val,lab)=>`<option value="${val}" ${cur===val?'selected':''}>${lab}</option>`;
  const met=v.metodo||'';
  const itens = met==='composicao' ? (v.insumos||[]).map(x=>x.insumo).join('\n')
              : met==='analitico'  ? (v.linhas||[]).map(x=>x.descricao).join('\n') : '';
  const itensLbl = met==='composicao' ? 'insumos que entram — um por linha (adicione / remova / edite)'
                 : 'linhas do orçamento — uma por linha (adicione / remova / edite)';
  const driver = (qd.driver_na_verba&&qd.driver_na_verba.length)?qd.driver_na_verba.join('; ')
               : (qd.insumos&&qd.insumos.length)?qd.insumos.join('; ') : '';
  const cronoResumo = c.ancora_nome?`âncora fixa “${esc(c.ancora_nome)}”`:'automático (marco principal do serviço)';
  const recNote = v.recorte_sugerido?`<div class="muted" style="font-size:11px;margin-top:5px"><span class="material-icons" style="font-size:13px;vertical-align:-2px">bolt</span> recorte aprendido: pega o sistema “${esc(v.recorte_sugerido.sistema)}”${v.recorte_sugerido.tipo?(' ('+esc(v.recorte_sugerido.tipo)+')'):''} inteiro — se você listar insumos acima, eles valem no lugar do recorte.</div>`:'';
  return `<div style="padding:12px 14px">
    <div class="rcrule">
      <div class="rchead"><span class="material-icons" style="color:#185fa5">event</span> Cronograma — qual data usar</div>
      <div style="display:flex;gap:12px;flex-wrap:wrap">
        <label class="rclab" style="flex:1;min-width:220px">tarefa-âncora <span class="muted">(vazio = automático; “;” p/ alternativas)</span>
          <input id="rc_anc_${sid}" value="${esc(c.ancora_nome||'')}" placeholder="ex.: Louças e Metais; Acabamento fino"></label>
        <label class="rclab" style="flex:1;min-width:220px">termos que o cronograma procura
          <input id="rc_ct_${sid}" value="${esc(c.termos_template||'')}" placeholder="ex.: louças; sanitário"></label>
      </div>
      <div class="muted" style="font-size:11px;margin-top:5px">${c.marco_template?esc(c.marco_template)+' · ':''}atual: ${cronoResumo}</div>
    </div>
    <div class="rcrule">
      <div class="rchead"><span class="material-icons" style="color:#b5651d">payments</span> Verba — de onde vem o valor</div>
      <div style="display:flex;gap:12px;flex-wrap:wrap">
        <label class="rclab" style="flex:0 0 230px">como casar
          <select id="rc_vm_${sid}">${opt(met,'','—')}${opt(met,'composicao','composição (cesta de insumos)')}${opt(met,'analitico','orçamento analítico (linhas)')}${opt(met,'manual','manual')}</select></label>
        <label class="rclab" style="flex:1;min-width:220px">não incluir (exclusões, separadas por “;”)
          <input id="rc_ex_${sid}" value="${esc(exs)}" placeholder="ex.: mão de obra empreitada"></label>
      </div>
      ${(met==='composicao'||met==='analitico')?`<label class="rclab" style="margin-top:8px">${itensLbl}
        <textarea id="rc_it_${sid}" style="width:100%;min-height:74px;font-size:12.5px">${esc(itens)}</textarea></label>
      <label class="rclab" style="margin-top:6px">termos que casam (sinônimos)
        <input id="rc_vt_${sid}" value="${esc(v.termos_template||'')}" placeholder="ex.: louça; bacia; caixa acoplada"></label>${recNote}`
      :(met==='manual'?`<div class="muted" style="font-size:11.5px;margin-top:6px">Valor manual — definido item a item na obra.</div>`:'<div class="muted" style="font-size:11.5px;margin-top:6px">Escolha o método acima pra listar os insumos/linhas.</div>')}
    </div>
    <div class="rcrule">
      <div class="rchead"><span class="material-icons" style="color:#7b5ea7">straighten</span> Quantitativo — como contar</div>
      <div style="display:flex;gap:12px;flex-wrap:wrap">
        <label class="rclab" style="flex:0 0 190px">fonte
          <select id="rc_qf_${sid}">${opt(qd.fonte||'','','—')}${opt(qd.fonte,'composicao','composição')}${opt(qd.fonte,'orcamento','orçamento')}${opt(qd.fonte,'manual','manual')}</select></label>
        <label class="rclab" style="flex:0 0 130px">unidade
          <input id="rc_qu_${sid}" value="${esc(qd.unidade||'')}" placeholder="un, m²…"></label>
        <label class="rclab" style="flex:1;min-width:200px">conta pela quantidade de (separe por “;”)
          <input id="rc_qd_${sid}" value="${esc(driver)}" placeholder="ex.: Caixa acoplada de louça"></label>
      </div>
    </div>
    <div class="rcrule">
      <div class="rchead"><span class="material-icons" style="color:var(--muted)">sticky_note_2</span> Cuidados, sinônimos, o que não fazer na próxima obra</div>
      <textarea id="rc_nt_${sid}" style="width:100%;min-height:56px;font-size:13px" placeholder="ex.: em alvenaria estrutural, conferir se a bacia é suspensa — muda o kit.">${esc(r.nota||'')}</textarea>
    </div>
    <div style="display:flex;justify-content:space-between;align-items:center;margin-top:10px">
      <span class="muted" style="font-size:11px">${r.obra_origem?('última origem: '+esc(r.obra_origem)):'sem origem ainda'} · ${esc(r.metodo_construtivo||'')}</span>
      <button class="btn-prim" style="padding:6px 14px" onclick="rcSalvar(${sid})"><span class="material-icons" style="font-size:15px;vertical-align:-3px">check</span> Salvar aprendizado</button>
    </div>
  </div>`;
}
async function rcSalvar(sid){
  const g=id=>{ const el=document.getElementById(id); return el?el.value:''; };
  const verba={ metodo:g('rc_vm_'+sid), exclusoes:g('rc_ex_'+sid).split(';').map(s=>s.trim()).filter(Boolean) };
  const itEl=document.getElementById('rc_it_'+sid); if(itEl) verba.itens=itEl.value.split('\n').map(s=>s.trim()).filter(Boolean);
  const vtEl=document.getElementById('rc_vt_'+sid); if(vtEl) verba.termos_template=vtEl.value;
  const body={ acao:'salvar', me:EU&&EU.bitrix_id, servico_id:sid, metodo_construtivo:rcMetodoSel(),
    crono:{ ancora_nome:g('rc_anc_'+sid), termos_template:g('rc_ct_'+sid) },
    verba,
    quant:{ fonte:g('rc_qf_'+sid), unidade:g('rc_qu_'+sid), driver:g('rc_qd_'+sid).split(';').map(s=>s.trim()).filter(Boolean) },
    nota:g('rc_nt_'+sid) };
  try{ const r=await (await fetch('actions/receitas.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(body)})).json();
    if(r.error){ toast(r.error); return; }
    toast('Aprendizado salvo'); RCDATA=null; renderReceitas();
  }catch(e){ toast('Falha: '+e.message); }
}
async function renderReceitas(){
  const box=document.getElementById('rcwrap'); if(!box) return;
  if(!RCDATA){ box.innerHTML='<div class="empty">Carregando…</div>';
    try{ RCDATA=await (await fetch('actions/receitas.php?_='+Date.now())).json(); }catch(e){ box.innerHTML='<div class="empty">Falha ao carregar.</div>'; return; } }
  const all=RCDATA.receitas||[];
  // seletor de método construtivo (variantes do dicionário)
  const metsel=document.getElementById('rcmetodo');
  let metodos=[...new Set(all.map(r=>r.metodo_construtivo).filter(Boolean))]; if(!metodos.length) metodos=['concreto armado convencional'];
  if(metsel && metsel.dataset.k!==metodos.join('|')){ const keep=metsel.value; metsel.innerHTML=metodos.map(m=>`<option>${esc(m)}</option>`).join(''); metsel.dataset.k=metodos.join('|'); if(keep&&metodos.includes(keep)) metsel.value=keep; }
  const met=rcMetodoSel();
  const q=(document.getElementById('rcq')?document.getElementById('rcq').value:'').toLowerCase();
  let rs=all.filter(r=>(r.metodo_construtivo||'concreto armado convencional')===met);
  if(q) rs=rs.filter(r=>((r.nome||'')+' '+(r.grupo||'')).toLowerCase().includes(q));
  if(!rs.length){ box.innerHTML='<div class="empty">Nenhum item nesse filtro. Use “Aprender de uma obra” pra puxar de uma curadoria, ou “Novo item”.</div>'; return; }
  let html='<table><thead><tr><th style="width:26px"></th><th>Item</th><th>Grupo</th><th>Cronograma</th><th>Verba</th><th>Quant.</th></tr></thead><tbody>';
  let grupo=null;
  rs.forEach(r=>{
    const key='sid:'+r.servico_id, open=RC_OPEN.has(key);
    if(r.grupo!==grupo){ grupo=r.grupo; html+=`<tr class="grp-h"><td colspan="6">${esc(grupo||'—')}</td></tr>`; }
    html+=`<tr class="item" onclick="rcToggle('${key}')" style="cursor:pointer">
      <td><span class="material-icons" style="font-size:16px;color:var(--muted)">${open?'expand_more':'chevron_right'}</span></td>
      <td><b>${esc(r.nome)}</b></td><td class="muted">${esc(r.grupo||'')}</td>
      <td style="font-size:12px">${rcPillTxt('crono',r)}</td>
      <td style="font-size:12px">${rcPillTxt('verba',r)}</td>
      <td style="font-size:12px">${rcPillTxt('quant',r)}</td></tr>`;
    if(open) html+=`<tr><td></td><td colspan="5" style="background:#fbfdf9;padding:0">${rcEditor(r)}</td></tr>`;
  });
  box.innerHTML=html+'</tbody></table>';
}
function rcToggle(key){ RC_OPEN.has(key)?RC_OPEN.delete(key):RC_OPEN.add(key); renderReceitas(); }

async function renderConfig(){
  cfgTab(IS_ADMIN?'users':(CAN_RESP?'resp':'users'));
  if(!IS_ADMIN) return;   // não-admin (só responsáveis em lote) não carrega a lista de usuários
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
  const pc=u?u.perm_crono:0, po=u?u.perm_orcamento:0, pq=u?u.perm_quant:0, pd=u?u.perm_dicionario:0, pr=u?u.perm_responsaveis:0;
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
          <label class="ckl"><input type="checkbox" id="pRespLote" ${pr?'checked':''}> Atribuir responsáveis em lote</label>
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
  ['pCrono','pOrc','pQuant','pDic','pRespLote'].forEach(id=>{const e=document.getElementById(id); if(e)e.checked=false;}); // presets definidos zeram as específicas
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
    perm_responsaveis:document.getElementById('pRespLote').checked?1:0,
    ativo:parseInt(val('uAtivo'))};
  const d=await (await fetch('actions/usuarios.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(body)})).json();
  if(d.error){
    const dbg=d.debug?` · (servidor recebeu me=${JSON.stringify(d.debug.me_recebido)}; eu enviei=${JSON.stringify(EU&&EU.bitrix_id)})`:'';
    console.warn('userSave erro:',d,'EU=',EU); toast('Erro: '+d.error+dbg); return;
  }
  closeModal(true); await renderConfig(); toast('Usuário salvo');
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
