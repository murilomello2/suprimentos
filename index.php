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
      <a id="nav-cotacoes" data-menu="cotacoes" title="Mapa de Cotações" onclick="showView('cotacoes')"><span class="material-icons">request_quote</span> <span class="navtxt">Mapa de Cotações</span></a>
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

   <section id="view-cotacoes" style="display:none">
    <div class="top">
      <h1 class="h1"><span class="material-icons" style="color:var(--dourado)">request_quote</span> Mapa de Cotações</h1>
      <p class="sub">Monte a concorrência: itens a cotar → propostas dos fornecedores → mapa comparativo (melhor preço por item).</p>
    </div>
    <div class="dtabs" id="cottabs" style="margin-bottom:12px">
      <button class="dtab on" id="ctab-cotacoes" onclick="cotTab('cotacoes')"><span class="material-icons">request_quote</span> Cotações</button>
      <button class="dtab" id="ctab-fornecedores" onclick="cotTab('fornecedores')"><span class="material-icons">groups</span> Fornecedores</button>
    </div>
    <div id="cotwrap"><div class="dempty">Carregando…</div></div>
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
    </div>
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
const BRL=n=>n?Number(n).toLocaleString('pt-BR',{style:'currency',currency:'BRL',maximumFractionDigits:0}):'—';
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
  ['radar','matriz','oportunidades','dashboards','cotacoes','config','audit','updates'].forEach(x=>{
    document.getElementById('view-'+x).style.display=v===x?'':'none';
    const nav=document.getElementById('nav-'+x); if(nav) nav.classList.toggle('active',v===x);
  });
  if(v==='cotacoes') cotInit();
  if(v==='dashboards') dashInit();
  if(v==='matriz') loadMatriz();
  if(v==='oportunidades') renderOportunidades();
  if(v==='config') renderConfig();
  if(v==='radar') fitRadarHeight();
  if(v==='audit') renderAudit();
  if(v==='updates') renderUpdates();
}

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
        <div class="muted" style="font-size:10.5px;margin-top:1px">${QNUM(e.area)} × ${QNUM(e.coef)} × R$${QNUM(e.rs_unit)}${e.q?' · define o quantitativo':''}</div></div>`;
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
      `<td style="padding:2px 4px;text-align:right;color:var(--muted);white-space:nowrap">${QNUM(x.qtde)} ${esc(x.unidade||'')} × R$${QNUM(x.rs_unit)}</td>`+
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
    <div><div>${esc(c.descricao)}</div><small class="muted">${QNUM(c.qtde_total)} ${esc(c.unidade||'')} · R$${QNUM(c.rs_unit)}/un</small></div></div>`).join('')+'</div>';
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
        <span class="tval">${QNUM(in_.coef)} ${esc(in_.unidade||'')} × R$${QNUM(in_.rs_unit)}</span>
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
          <small class="muted">${esc((s.compdesc||'').slice(0,38))} · ${QNUM(s.coef)}×R$${QNUM(s.rs_unit)}</small></div>
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
  const body={acao:'novo', nome:val('niNome'), grupo, tipo:val('niTipo'), curva:val('niCurva'), responsavel:resp, copy_from:val('niCopy')||null, me:EU&&EU.bitrix_id, obra:OBRA_SEL[0]||1};
  if(!body.nome){toast('Informe o nome');return;}
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
      <svg viewBox="0 0 120 120" width="120" height="120">${(function(){const cir=2*Math.PI*54;let off=0;return donut.filter(s=>s.v>0).map(s=>{const len=cir*s.v/(totRiscoV||1);const el=`<circle cx="60" cy="60" r="54" fill="none" stroke="${s.color}" stroke-width="12" stroke-dasharray="${len} ${cir-len}" stroke-dashoffset="${-off}" transform="rotate(-90 60 60)"/>`;off+=len;return el;}).join('');})()}<text x="60" y="64" text-anchor="middle" font-size="13" font-weight="800" fill="#1e3a2e">${BRL(totRiscoV).replace('R$','')}</text></svg>
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
function cotTab(t){ COT.tab=t; ['cotacoes','fornecedores'].forEach(x=>{const b=document.getElementById('ctab-'+x); if(b)b.classList.toggle('on',x===t);});
  if(t==='fornecedores') fornLoad(); else cotLoad(); }
function cotStChip(s){ const m={aberta:['#8a9299','Aberta'],aguardando:['var(--dourado)','Aguardando'],finalizada:['var(--ok)','Finalizada']}; const x=m[s]||['#8a9299',s]; return `<span class="dchip" style="background:${x[0]}">${x[1]}</span>`; }
function cotStLabel(s){ return ({aberta:'Aberta',aguardando:'Aguardando',finalizada:'Finalizada'})[s]||s; }
function cotFmtDT(iso){ if(!iso)return '—'; const d=new Date(iso); if(isNaN(d.getTime()))return '—'; const p=n=>('0'+n).slice(-2); return p(d.getDate())+'/'+p(d.getMonth()+1)+'/'+String(d.getFullYear()).slice(2)+' '+p(d.getHours())+':'+p(d.getMinutes()); }
function cotSort(col){ COT.sort=COT.sort||{col:'created_at',dir:-1}; if(COT.sort.col===col) COT.sort.dir=-COT.sort.dir; else COT.sort={col, dir:(col==='created_at'||col==='n_propostas'||col==='n_itens'||col==='melhor_oferta')?-1:1}; cotRender(); }
function cotObraOpts(sel){ return '<option value="">— obra —</option>'+((typeof OBRAS!=='undefined'&&OBRAS)||[]).map(o=>`<option value="${o.id}" ${String(sel)===String(o.id)?'selected':''}>${esc(o.nome)}</option>`).join(''); }
async function cotLoad(){
  const w=document.getElementById('cotwrap'); w.innerHTML='<div class="dempty">Carregando cotações…</div>';
  try{ const d=await (await fetch('actions/cotacoes.php'+(COT.obra?('?obra='+COT.obra+'&'):'?')+'_='+Date.now())).json();
    COT.list=d.cotacoes||[]; COT.mode='list'; cotRender();
  }catch(e){ w.innerHTML='<div class="dempty">Falha: '+esc(e.message)+'</div>'; }
}
function cotRender(){
  if(COT.mode==='novo') return cotRenderNovo();
  if(COT.mode==='detalhe') return cotRenderDetalhe();
  if(COT.mode==='proposta') return cotRenderProposta();
  const w=document.getElementById('cotwrap');
  COT.filt=COT.filt||{q:'',categoria:'',status:''}; COT.sort=COT.sort||{col:'created_at',dir:-1};
  const all=COT.list||[];
  const cats=[...new Set(all.map(c=>c.categoria).filter(Boolean))].sort();
  const sts=[...new Set(all.map(c=>c.status).filter(Boolean))];
  // filtro que não existe mais nesta obra → limpa (evita lista vazia enganosa com o dropdown mostrando "Todas")
  if(COT.filt.categoria && !cats.includes(COT.filt.categoria)) COT.filt.categoria='';
  if(COT.filt.status && !sts.includes(COT.filt.status)) COT.filt.status='';
  // filtros CLIENT-SIDE (a obra continua server-side no cotLoad)
  const qn=opNorm(COT.filt.q||'');
  let rows=all.filter(c=>
    (!qn || opNorm((c.titulo||'')+' '+(c.categoria||'')+' '+(c.obra_nome||'')).includes(qn)) &&
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
     <div class="search" style="min-width:170px"><span class="material-icons" style="color:var(--muted)">search</span><input id="cotListBusca" placeholder="Buscar cotação…" value="${esc(COT.filt.q)}" oninput="COT.filt.q=this.value;cotRender()"></div>
     <label class="muted" style="font-size:12px">Obra <select onchange="COT.obra=this.value;cotLoad()" style="margin-left:4px">${cotObraOpts(COT.obra)}</select></label>
     <select onchange="COT.filt.categoria=this.value;cotRender()" style="font-size:12px;padding:6px"><option value="">Todas categorias</option>${cats.map(c=>`<option ${c===COT.filt.categoria?'selected':''}>${esc(c)}</option>`).join('')}</select>
     <select onchange="COT.filt.status=this.value;cotRender()" style="font-size:12px;padding:6px"><option value="">Todos status</option>${sts.map(s=>`<option value="${esc(s)}" ${s===COT.filt.status?'selected':''}>${esc(cotStLabel(s))}</option>`).join('')}</select>
     <span class="muted" style="font-size:11.5px">${rows.length} de ${all.length}</span>
     ${CAN_EDIT?'<button class="btn-prim" style="margin-left:auto;padding:7px 14px" onclick="cotNovo()"><span class="material-icons" style="font-size:16px;vertical-align:-3px">add</span> Nova cotação</button>':''}
   </div></div><div class="wrap"><table><thead><tr>${th('Cotação','titulo')}${th('Obra','obra_nome')}${th('Categoria','categoria')}<th>Tipo</th>${th('Itens','n_itens','style="text-align:center"')}${th('Propostas','n_propostas','style="text-align:center" title="recebidas / convidados"')}${th('Melhor oferta','melhor_oferta','style="text-align:right"')}${th('Criada em','created_at')}${th('Status','status')}<th></th></tr></thead><tbody>`;
  for(const c of rows){
    html+=`<tr style="cursor:pointer" onclick="cotOpen(${c.id})"><td><b>${esc(c.titulo)}</b></td><td>${esc(c.obra_nome||'—')}</td><td class="muted">${esc(c.categoria||'')}</td><td class="muted">${esc(c.tipo_servico||'')}</td>
      <td style="text-align:center">${c.n_itens}</td><td style="text-align:center" title="${c.n_propostas} recebida(s) de ${c.n_convidados||0} convidado(s)"><b>${c.n_propostas}</b><span class="muted">/${c.n_convidados||0}</span></td><td style="text-align:right">${c.melhor_oferta?BRL(c.melhor_oferta):'—'}</td><td class="muted" style="font-size:11.5px;white-space:nowrap">${cotFmtDT(c.created_at)}</td><td>${cotStChip(c.status)}</td>
      <td><span class="material-icons" style="color:var(--muted)">chevron_right</span></td></tr>`;
  }
  if(!rows.length) html+=`<tr><td colspan="10" class="empty">${all.length?'Nenhuma cotação casa os filtros.':'Nenhuma cotação ainda. Crie a primeira.'}</td></tr>`;
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
      ${cotFld('Obra','<select id="cotO">'+cotObraOpts(pre.obra||'')+'</select>')}
      ${cotFld('Categoria','<input id="cotC" value="'+esc(pre.categoria||'')+'" placeholder="Ex.: M.O. Gesso">')}
      ${cotFld('Tipo','<select id="cotTipo"><option>Material</option><option>M.O.</option><option>Material + MO</option><option>Locação</option><option>Serviço</option></select>')}
      ${cotFld('Verba (R$) <span id="cotVerbaChip">'+cotVerbaChip(pre.verba_origem||'')+'</span>','<input id="cotV" type="number" placeholder="0" value="'+(pre.verba!=null&&pre.verba!==''?esc(pre.verba):'')+'">')}
    </div>
    ${cotFld('Descrição / escopo (vai na carta ao fornecedor)','<textarea id="cotD" rows="5" style="width:100%" placeholder="Escopo / informações gerais da cotação">'+esc(pre.descricao||'')+'</textarea>','margin-top:8px')}
    ${cotFld('Pontos a conferir por proposta — equalização (1 por linha)','<textarea id="cotEq" rows="8" style="width:100%" placeholder="Ex.: Diesel incluso? · Faturamento mínimo diário · Mobilização/desmobilização · Retenção · ISS · ART">'+esc(pre.equalizacao||'')+'</textarea>','margin-top:8px')}
    <div style="display:flex;justify-content:space-between;align-items:center;margin-top:12px;flex-wrap:wrap;gap:6px"><b style="font-size:13px">Itens a cotar *</b>
      <span style="display:flex;gap:6px">${vinc?`<button class="btn-ghost" style="padding:4px 10px" onclick="cotSalvarDicionario()" title="Grava estes itens como padrão do serviço — as próximas cotações deste serviço já vêm com eles"><span class="material-icons" style="font-size:15px;vertical-align:-3px">menu_book</span> Salvar como padrão do serviço</button>`:''}
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
  box.innerHTML=`<div class="dmini" style="padding:4px 0">${L.length} fornecedor(es)${L.length>=300?'+ (refine a busca)':''} — marque os que vão participar</div>`+L.map((f,i)=>{ const on=!!COT_PICK.sel[cotPickKey(f)];
    return `<label style="display:flex;align-items:center;gap:9px;padding:7px 4px;border-bottom:1px solid #f2f4f3;cursor:pointer">
      <input type="checkbox" ${on?'checked':''} onchange="cotPickToggle(${i})" style="width:16px;height:16px">
      <span style="flex:1;font-size:12.5px"><b>${esc(f.nome)}</b> <span class="muted" style="font-size:10.5px">· ${esc(f.categoria||'sem categoria')}${f.cidade?' · '+esc(f.cidade):''}${f.tipo?' · '+esc(f.tipo):''}${f.itens?' · '+esc((''+f.itens).slice(0,40)):''}</span></span></label>`;
  }).join('');
  cotPickCount();
}
function cotPickToggle(i){ const f=COT_PICK.list[i]; if(!f)return; const k=cotPickKey(f); if(COT_PICK.sel[k])delete COT_PICK.sel[k]; else COT_PICK.sel[k]=f; cotPickCount(); }
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
  set('cotV',(it.verba&&it.verba>0)?it.verba:'',true);                         // VERBA do vínculo do orçamento
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
async function cotCriar(){
  const titulo=val('cotT').trim(); if(!titulo){toast('Dê um título à cotação');return;}
  const itens=COT.novoItens.filter(it=>(it.descricao||'').trim()); if(!itens.length){toast('Inclua ao menos um item');return;}
  const body={acao:'criar',me:EU&&EU.bitrix_id,obra_id:Number(val('cotO'))||null,servico_id:COT.novoServico||null,titulo,categoria:val('cotC'),tipo_servico:val('cotTipo'),verba:Number(val('cotV'))||0,verba_origem:(COT.novoPre&&COT.novoPre.verba_origem)||'',descricao:val('cotD'),equalizacao:val('cotEq'),itens,convidados:COT.novoConvidados||[]};
  try{ const r=await (await fetch('actions/cotacoes.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(body)})).json();
    if(r.error){toast(r.error);return;} toast('Cotação criada'); cotOpen(r.id);
  }catch(e){toast('Falha: '+e.message);}
}
async function cotOpen(id){
  const w=document.getElementById('cotwrap'); w.innerHTML='<div class="dempty">Abrindo mapa…</div>';
  try{ const d=await (await fetch('actions/cotacoes.php?id='+id+'&_='+Date.now())).json();
    if(d.error){w.innerHTML='<div class="dempty">'+esc(d.error)+'</div>';return;}
    COT.cur=d; COT.mode='detalhe'; cotRenderDetalhe();
  }catch(e){w.innerHTML='<div class="dempty">Falha: '+esc(e.message)+'</div>';}
}
function cotNum(x){ return x!=null&&x!==''?Number(x).toLocaleString('pt-BR'):''; }
function cotRenderDetalhe(){
  const d=COT.cur,c=d.cotacao,itens=d.itens||[],props=d.propostas||[],m=d.mapa||{},best=m.melhor_por_item||{},w=document.getElementById('cotwrap');
  let html=`<div class="panel" style="margin-bottom:10px"><div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap">
      <button class="btn-ghost" onclick="cotLoad()"><span class="material-icons" style="font-size:16px;vertical-align:-3px">arrow_back</span> Voltar</button>
      <b style="font-size:16px">${esc(c.titulo)}</b> ${cotStChip(c.status)}
      <span class="muted" style="font-size:12px">${esc(c.obra_nome||'sem obra')}${c.categoria?' · '+esc(c.categoria):''}${c.tipo_servico?' · '+esc(c.tipo_servico):''}</span>
      <span style="margin-left:auto;display:flex;gap:6px">
        ${CAN_EDIT?`<button class="btn-prim" style="padding:6px 12px" onclick="cotProposta()"><span class="material-icons" style="font-size:15px;vertical-align:-3px">add</span> Cadastrar proposta</button>`:''}
        ${CAN_EDIT?`<button class="btn-ghost" style="padding:6px 12px" onclick="cotFinalizar()">${c.status==='finalizada'?'Reabrir':'Finalizar'}</button>`:''}
      </span></div>
    <div class="kpis" style="padding:10px 0 0">
      <div class="kpi"><div class="v">${props.length}</div><div class="l">propostas recebidas</div></div>
      <div class="kpi"><div class="v gold">${m.melhor_oferta?BRL(m.melhor_oferta):'—'}</div><div class="l">melhor fornecedor único${m.fornecedor_destaque?' · '+esc(m.fornecedor_destaque):''}</div></div>
      <div class="kpi"><div class="v" style="color:var(--ok)">${m.melhor_total?BRL(m.melhor_total):'—'}</div><div class="l">melhor compra (menor por item)</div></div>
      <div class="kpi"><div class="v">${c.verba?BRL(c.verba):'—'} ${cotVerbaInfoBtn(c)} ${CAN_EDIT?`<span class="material-icons" onclick="cotVerbaEditar()" title="editar / puxar a verba" style="font-size:14px;cursor:pointer;color:var(--muted);vertical-align:-2px">edit</span>`:''}</div><div class="l">verba prevista${c.verba_origem?' · '+esc({curada:'curada ✓',auto:'auto 🤖',definida:'definida'}[c.verba_origem]||c.verba_origem):''}</div></div>
    </div></div>`;
  // ---- Concorrência (fornecedores convidados) ----
  const conv=d.convidados||[];
  html+=`<div class="panel" style="margin-bottom:10px"><div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px">
      <b style="font-size:13px">Concorrência — fornecedores convidados</b>
      <span class="dchip" style="background:${conv.length&&conv.every(x=>x.respondeu)?'var(--ok)':'var(--dourado)'}">${conv.filter(x=>x.respondeu).length} de ${conv.length} responderam</span></div>`;
  if(conv.length) html+='<div style="margin-top:8px">'+conv.map((cf,ci)=>`<div class="drow"><span class="dgm" style="background:${cf.respondeu?'var(--ok)':'#cfd6da'}"></span><span style="flex:1">${esc(cf.fornecedor_nome)}${cf.categoria?` <span class="muted" style="font-size:11px">· ${esc(cf.categoria)}</span>`:''}</span>
      <span class="dchip" style="background:${cf.respondeu?'var(--ok)':'#8a9299'}">${cf.respondeu?('respondeu · '+BRL(cf.proposta_total)):'aguardando'}</span>
      ${CAN_EDIT&&!cf.respondeu?`<button class="btn-ghost" style="padding:2px 8px" onclick="cotPropostaDe(${ci})">Lançar proposta</button>`:''}
      ${CAN_EDIT?`<button class="btn-ghost" style="padding:2px 6px;color:var(--pend)" onclick="cotDesconvidar(${cf.id})" title="tirar da concorrência">×</button>`:''}</div>`).join('')+'</div>';
  else html+='<div class="dmini" style="margin-top:6px">Nenhum fornecedor convidado ainda — convide abaixo.</div>';
  if(CAN_EDIT) html+=`<div style="margin-top:8px"><button class="btn-ghost" style="padding:5px 12px" onclick="cotFornPickerOpen('convite')"><span class="material-icons" style="font-size:15px;vertical-align:-3px;color:var(--verde)">group_add</span> Convidar fornecedores</button></div>`;
  html+='</div>';
  html+=cotEqualizaPanel(d);
  if(!props.length){ html+='<div class="panel"><div class="empty">Nenhuma proposta ainda. Clique em "Cadastrar proposta" ou "Lançar proposta" de um convidado para montar o mapa.</div></div>'; }
  else{
    html+='<div class="panel" style="overflow-x:auto;padding:0"><table class="mtable" style="border:none"><thead><tr><th class="svc-h" style="text-align:left">Item</th>';
    props.forEach(p=>{ html+=`<th style="min-width:120px">${esc(p.fornecedor_nome)}${p.prazo?`<div class="muted" style="font-size:9.5px;font-weight:400">${esc(p.prazo)}</div>`:''}</th>`; });
    html+='<th style="min-width:140px;color:var(--verde-d)">🏆 Melhor Compra</th></tr></thead><tbody>';
    itens.forEach(it=>{ const b=best[it.id];
      html+=`<tr><td class="svc-c" style="text-align:left">${esc(it.descricao)}<small>${cotNum(it.quantidade)} ${esc(it.unidade||'')}</small></td>`;
      props.forEach(p=>{ const pi=(p.itens||{})[it.id]; const isB=b&&b.proposta_id===p.id;
        html+=`<td style="text-align:center;padding:6px 8px;${isB?'background:#e7f6ee':''}">${pi&&pi.preco_total!=null?`<b>${BRL(pi.preco_unit)}</b>${isB?' 🏆':''}<div class="muted" style="font-size:10px">${BRL(pi.preco_total)}</div>`:'<span class="muted">—</span>'}</td>`; });
      html+=`<td style="text-align:center;padding:6px 8px;background:#eafaf0">${b?`<b>${BRL(b.preco_total)}</b><div class="muted" style="font-size:10px">${esc(b.fornecedor)}</div>`:'—'}</td></tr>`;
    });
    html+='<tr style="background:#f7faf8"><td class="svc-c" style="text-align:left;font-weight:800">TOTAL</td>';
    props.forEach(p=>{ const isBS=m.fornecedor_destaque===p.fornecedor_nome; html+=`<td style="text-align:center;font-weight:800;${isBS?'color:var(--verde-d)':''}">${p.total!=null?BRL(p.total):'—'}</td>`; });
    html+=`<td style="text-align:center;font-weight:800;background:#eafaf0;color:var(--verde-d)">${m.melhor_total?BRL(m.melhor_total):'—'}</td></tr></tbody></table></div>`;
    const anexos=d.anexos||[], meB=(EU&&EU.bitrix_id)||'';
    html+='<div class="panel" style="margin-top:10px"><b style="font-size:13px">Propostas & anexos (PDF)</b><div style="margin-top:8px">';
    props.forEach(p=>{ const ax=anexos.filter(a=>a.proposta_id===p.id);
      html+=`<div style="border-bottom:1px solid #f1f3f2;padding:7px 0">
        <div class="drow" style="padding:0"><span class="dgm" style="background:${m.fornecedor_destaque===p.fornecedor_nome?'var(--ok)':'#8a9299'}"></span><span style="flex:1"><b>${esc(p.fornecedor_nome)}</b>${p.prazo?` <span class="muted">· ${esc(p.prazo)}</span>`:''}</span><b style="min-width:90px;text-align:right">${p.total!=null?BRL(p.total):'—'}</b>
          ${CAN_EDIT?`<button class="btn-ghost" style="padding:2px 8px" onclick="cotProposta(${p.id})">Editar</button><button class="btn-ghost" style="padding:2px 8px;color:var(--pend)" onclick="cotExcluirProposta(${p.id})">Excluir</button>`:''}</div>
        <div style="display:flex;flex-wrap:wrap;gap:6px;align-items:center;padding:5px 0 0 18px">
          ${ax.map(a=>`<span class="dchip" style="background:#eef4f0;color:var(--verde-d);font-weight:600;display:inline-flex;align-items:center;gap:4px"><span class="material-icons" style="font-size:13px">picture_as_pdf</span><a href="actions/cotacao_anexo.php?download=${a.id}&me=${encodeURIComponent(meB)}" target="_blank" rel="noopener" style="color:var(--verde-d);text-decoration:none">${esc(a.nome)}</a>${CAN_EDIT?` <span onclick="cotDelAnexo(${a.id})" style="cursor:pointer;color:var(--pend)" title="excluir anexo">×</span>`:''}</span>`).join('')||'<span class="dmini">sem anexo</span>'}
          ${CAN_EDIT?`<label class="btn-ghost" style="padding:2px 9px;font-size:11px;cursor:pointer"><span class="material-icons" style="font-size:13px;vertical-align:-2px">attach_file</span> anexar PDF<input type="file" accept="application/pdf" style="display:none" onchange="cotUploadAnexo(${p.id},this)"></label>`:''}
        </div></div>`; });
    if(!props.length) html+='<div class="dmini">—</div>';
    html+='</div></div>';
  }
  w.innerHTML=html;
}
// EQUALIZAÇÃO — pontos a conferir por proposta (diesel? faturamento mín., mobilização, retenção, ISS, ART…)
function cotEqPontos(c){ return ((c&&c.equalizacao)||'').split(/\r?\n|\|/).map(s=>s.trim()).filter(Boolean); }
function cotEqualizaPanel(d){
  const c=d.cotacao||{}, props=d.propostas||[], pontos=cotEqPontos(c);
  let h=`<div class="panel" style="margin-bottom:10px"><div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px">
      <b style="font-size:13px">Equalização — pontos a conferir por proposta</b>
      ${CAN_EDIT?`<button class="btn-ghost" style="padding:3px 9px" onclick="cotEqualizaEdit()"><span class="material-icons" style="font-size:14px;vertical-align:-3px">edit_note</span> Editar pontos</button>`:''}</div>
    <div id="cotEqEdit" style="display:none;margin-top:8px"><textarea id="cotEqPontos" rows="6" style="width:100%;font-size:12.5px" placeholder="Um ponto por linha…">${esc(c.equalizacao||'')}</textarea>
      <div style="margin-top:6px"><button class="btn-prim" style="padding:5px 12px" onclick="cotEqualizaPontosSave()">Salvar pontos</button> <button class="btn-ghost" style="padding:5px 12px" onclick="document.getElementById('cotEqEdit').style.display='none'">Cancelar</button></div></div>`;
  if(!pontos.length){ h+=`<div class="dmini" style="margin-top:6px">Sem pontos de equalização.${CAN_EDIT?' Clique em “Editar pontos”, ou vincule a cotação a um item do radar com “variáveis a cotar”.':''}</div></div>`; return h; }
  if(!props.length){ h+='<ul style="margin:8px 0 0 18px;padding:0">'+pontos.map(p=>`<li style="font-size:12.5px;margin-bottom:3px">${esc(p)}</li>`).join('')+'</ul><div class="dmini" style="margin-top:6px">Cadastre propostas para preencher cada ponto por fornecedor.</div></div>'; return h; }
  h+='<div style="overflow-x:auto;margin-top:8px"><table class="mtable" style="border:none"><thead><tr><th class="svc-h" style="text-align:left;min-width:200px">Ponto a conferir</th>';
  props.forEach(p=>{ h+=`<th style="min-width:140px">${esc(p.fornecedor_nome)}</th>`; });
  h+='</tr></thead><tbody>';
  pontos.forEach(pt=>{ h+=`<tr><td class="svc-c" style="text-align:left;font-size:12px">${esc(pt)}</td>`;
    props.forEach(p=>{ const v=((p.equaliza||{})[pt])||''; h+=`<td style="padding:4px 6px">${CAN_EDIT?`<input data-eqpid="${p.id}" data-eqpt="${esc(pt)}" value="${esc(v)}" onchange="cotEqualizaCell(${p.id})" style="width:100%;font-size:11.5px;padding:3px 5px;border:1px solid var(--line);border-radius:5px" placeholder="—">`:`<span style="font-size:11.5px">${esc(v||'—')}</span>`}</td>`; });
    h+='</tr>'; });
  h+='</tbody></table></div></div>';
  return h;
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
function cotEqualizaCell(pid){
  const map={}; document.querySelectorAll('input[data-eqpid="'+pid+'"]').forEach(inp=>{ map[inp.getAttribute('data-eqpt')]=inp.value; });
  const p=(COT.cur.propostas||[]).find(x=>x.id===pid); if(p) p.equaliza=map;   // mantém local p/ não perder ao re-render
  fetch('actions/cotacoes.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({acao:'equaliza_salvar',me:EU&&EU.bitrix_id,cotacao_id:COT.cur.cotacao.id,proposta_id:pid,equaliza:map})}).then(r=>r.json()).then(r=>{ if(r&&r.error) toast(r.error); }).catch(()=>{});
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
      <div><b style="font-size:12.5px">${esc(it.descricao)}</b> <span class="muted" style="font-size:11px">· ${cotNum(it.quantidade)} ${esc(it.unidade||'')}</span></div>
      <input type="number" step="0.0001" id="prU${it.id}" value="${pr.precos[it.id].preco_unit}" oninput="cotPrecoIn(${it.id},'u',this.value)" placeholder="0,00" style="width:100%;text-align:right">
      <input type="number" step="0.01" id="prT${it.id}" value="${pr.precos[it.id].preco_total}" oninput="cotPrecoIn(${it.id},'t',this.value)" placeholder="0,00" style="width:100%;text-align:right"></div>`).join('')}</div>
    <div style="margin-top:14px"><button class="btn-prim" onclick="cotSalvarProposta()"><span class="material-icons" style="font-size:16px;vertical-align:-3px">check</span> Salvar proposta</button></div>
    </div></div>`;
}
function cotPrecoIn(iid,which,v){
  const p=COT.prop.precos[iid], it=(COT.cur.itens||[]).find(x=>x.id===iid), q=it&&it.quantidade?Number(it.quantidade):null;
  if(which==='u'){ p.preco_unit=v; if(q&&v!==''){ p.preco_total=(Number(v)*q).toFixed(2); const el=document.getElementById('prT'+iid); if(el)el.value=p.preco_total; } }
  else p.preco_total=v;
}
async function cotSalvarProposta(){
  const forn=val('prF').trim(); if(!forn){toast('Informe o fornecedor');return;}
  const itens=Object.entries(COT.prop.precos).map(([iid,p])=>({cotacao_item_id:Number(iid),preco_unit:p.preco_unit!==''?Number(p.preco_unit):'',preco_total:p.preco_total!==''?Number(p.preco_total):''}));
  const body={acao:'proposta',me:EU&&EU.bitrix_id,cotacao_id:COT.cur.cotacao.id,proposta_id:COT.prop.id||undefined,fornecedor_nome:forn,prazo:val('prP'),observacoes:val('prO'),itens};
  try{ const r=await (await fetch('actions/cotacoes.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(body)})).json();
    if(r.error){toast(r.error);return;} toast('Proposta salva'); cotOpen(COT.cur.cotacao.id);
  }catch(e){toast('Falha: '+e.message);}
}
async function cotFinalizar(){ const c=COT.cur.cotacao, novo=c.status==='finalizada'?'aguardando':'finalizada';
  try{ await fetch('actions/cotacoes.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({acao:'status',me:EU&&EU.bitrix_id,cotacao_id:c.id,status:novo})}); cotOpen(c.id); }catch(e){toast('Falha');} }
async function cotExcluirProposta(pid){ if(!confirm('Excluir esta proposta?'))return;
  try{ await fetch('actions/cotacoes.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({acao:'excluir_proposta',me:EU&&EU.bitrix_id,proposta_id:pid})}); cotOpen(COT.cur.cotacao.id); }catch(e){toast('Falha');} }
async function cotUploadAnexo(propostaId, input){
  const file=input.files&&input.files[0]; if(!file){ return; }
  if(file.type!=='application/pdf' && !/\.pdf$/i.test(file.name)){ toast('Somente PDF'); input.value=''; return; }
  if(file.size>25*1024*1024){ toast('Máximo 25 MB'); input.value=''; return; }
  const fd=new FormData(); fd.append('arquivo',file); fd.append('cotacao_id',COT.cur.cotacao.id); if(propostaId)fd.append('proposta_id',propostaId); fd.append('me',(EU&&EU.bitrix_id)||'');
  toast('Enviando anexo…');
  try{ const r=await (await fetch('actions/cotacao_anexo.php',{method:'POST',body:fd})).json();
    if(r.error){ toast(r.error); input.value=''; return; } toast('Anexo salvo'); cotOpen(COT.cur.cotacao.id);
  }catch(e){ toast('Falha: '+e.message); input.value=''; }
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
function fornCatOpts(sel){ return '<option value="">Todas as categorias</option>'+FORN.cats.map(c=>`<option ${c.nome===sel?'selected':''}>${esc(c.nome)}</option>`).join(''); }
function fornRender(){
  if(FORN.edit) return fornRenderEdit();
  const w=document.getElementById('cotwrap');
  let html=`<div class="panel" style="margin-bottom:10px"><div class="bar" style="gap:8px;flex-wrap:wrap;align-items:center">
    <div class="search" style="min-width:150px"><span class="material-icons" style="color:var(--muted)">search</span><input placeholder="Buscar nome…" value="${esc(FORN.f.nome)}" oninput="FORN.f.nome=this.value;fornDeb()"></div>
    <select onchange="FORN.f.categoria=this.value;fornLoad()">${fornCatOpts(FORN.f.categoria)}</select>
    <select onchange="FORN.f.tipo=this.value;fornLoad()"><option value="">Todos os tipos</option>${FORN.tipos.map(t=>`<option ${t===FORN.f.tipo?'selected':''}>${esc(t)}</option>`).join('')}</select>
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
const MENUS=[['dashboard','Dashboard'],['radar','Radar de Aquisições'],['matriz','Matriz'],['cotacoes','Mapa de Cotações'],['oportunidades','Oportunidades'],['updates','Atualizações'],['config','Configurações']];
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
  const allow = (EU&&EU.autorizado)?(EU.menus||[]):[];
  document.querySelectorAll('.nav a[data-menu]').forEach(a=>{
    const m=a.getAttribute('data-menu');
    // Configurações também abre p/ quem tem só a permissão de responsáveis em lote (verá apenas essa aba)
    a.style.display=(IS_ADMIN||allow.includes(m)||(m==='config'&&CAN_RESP))?'':'none';
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
  const permitida={users:IS_ADMIN, receitas:IS_ADMIN, resp:canR};
  if(!permitida[t]) t = IS_ADMIN?'users':(canR?'resp':'users');
  document.getElementById('cfg-users').style.display = t==='users'?'':'none';
  document.getElementById('cfg-receitas').style.display = t==='receitas'?'':'none';
  document.getElementById('cfg-resp').style.display = t==='resp'?'':'none';
  const ab=document.getElementById('cfgAddBtn'); if(ab) ab.style.display = (t==='users'&&IS_ADMIN)?'':'none';
  ['users','resp','receitas'].forEach(x=>{ const b=document.getElementById('cfgtab-'+x); if(b){ b.style.background = x===t?'var(--verde)':''; b.style.color = x===t?'#fff':''; } });
  if(t==='receitas') renderReceitas();
  if(t==='resp') renderRespLote();
}

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
