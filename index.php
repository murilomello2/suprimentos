<?php
/* Cockpit de Suprimentos — front. Sem segredos aqui; consome actions/*.php.
   ─────────────────────────────────────────────────────────────────────────────
   CACHE (05/08/2026): este arquivo não mandava cabeçalho de cache NENHUM. Sem instrução, o
   navegador aplica cache heurístico e reusa o HTML antigo — e como é o HTML que carrega o
   ?v= de cada .js, um index velho congela o app INTEIRO numa versão antiga. Era o que o
   Murilo via: cockpit de semanas atrás, só destravando com Ctrl+Shift+R. O versionamento
   dos .js sempre esteve certo; ninguém chegava a lê-lo.

   Correção: revalidação obrigatória + ETag do build. Quando nada mudou o servidor responde
   304 (uns bytes) e o navegador reusa — não custa os 70 KB deste arquivo a cada navegação.
   Quando mudou, vem inteiro, com os ?v= novos. */
require_once __DIR__ . '/includes/versao.php';
$SUP_VER  = sup_versao();
$SUP_ETAG = '"sup-' . $SUP_VER . '"';
header('Cache-Control: no-cache, must-revalidate');
header('ETag: ' . $SUP_ETAG);
if (sup_etag_bate($SUP_ETAG)) { http_response_code(304); exit; }
?>
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
  /* overflow-y no PRÓPRIO menu: ele tem altura fixa de 100vh e, quando os itens passam disso
     (o papel 'obra' somou três telas novas), o excesso era simplesmente cortado — não dava para
     chegar em Configurações. Agora o menu rola sozinho, independente do conteúdo da direita. */
  .side{width:230px;background:var(--verde);color:#eafaef;flex-shrink:0;position:sticky;top:0;height:100vh;display:flex;flex-direction:column;overflow-y:auto;overflow-x:hidden;scrollbar-width:thin;scrollbar-color:rgba(255,255,255,.28) transparent}
  .side::-webkit-scrollbar{width:7px}
  .side::-webkit-scrollbar-thumb{background:rgba(255,255,255,.24);border-radius:4px}
  .side::-webkit-scrollbar-thumb:hover{background:rgba(255,255,255,.4)}
  .side::-webkit-scrollbar-track{background:transparent}
  /* o brand fica preso no topo enquanto o resto rola — sem isso o botão de recolher some */
  .side .brand{position:sticky;top:0;z-index:2;background:var(--verde)}
  .side .whoami{position:sticky;bottom:0;background:var(--verde)}
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
  .kpis{display:flex;gap:8px;flex-wrap:wrap;padding:0;justify-content:flex-start;align-items:center}
  .kpi{background:var(--card);border:1px solid var(--line);border-radius:10px;padding:5px 11px;min-width:74px;flex:0 1 auto;line-height:1.1}
  .kpi .v{font-size:17px;font-weight:800;color:var(--verde-d)}
  .kpi .v.alert{color:var(--pend)} .kpi .v.gold{color:var(--dourado)}
  .kpi .l{font-size:10px;color:var(--muted);margin-top:1px;white-space:nowrap}
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
  .st-NaoSeAplica{background:#eef1f4;color:#8a9299;font-style:italic}
  .t20c{text-align:center;font-size:11.5px;padding:5px 6px} .t20click{cursor:pointer} .t20click:hover{background:#f3f7f5}
  @media print{ body *{visibility:hidden} #view-top20,#view-top20 *{visibility:visible} #view-top20{position:absolute;left:0;top:0;width:100%} .t20-noprint{display:none!important} }
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
  /* dentro de .fld, DUAS regras estragavam o checkbox: ".fld label" (uppercase/block) vencia a .ckl,
     e ".fld input{width:100%}" ALARGAVA o input do checkbox (caixa invisível gigante separando o
     quadradinho do texto — o "layout torto"). Reset explícito dos dois. */
  .fld .ckl{display:flex;align-items:center;gap:8px;text-transform:none;letter-spacing:0;color:var(--ink);font-weight:400;font-size:13px;margin:0;cursor:pointer}
  .fld .ckl input{width:auto;flex:0 0 auto;margin:0}
  .chkbox{margin-top:6px;display:flex;flex-direction:column;gap:5px;padding:8px;border:1px solid var(--line);border-radius:8px;background:#fafbfa}
  /* CHIPS de checkbox (menus/permissões/obras): cada opção é uma PÍLULA com o checkbox colado no texto;
     marcada fica verde — impossível confundir de qual opção é o checkbox */
  .ckgrid{margin-top:6px;display:grid;grid-template-columns:repeat(auto-fill,minmax(185px,1fr));gap:8px;padding:10px;border:1px solid var(--line);border-radius:8px;background:#fafbfa}
  .ckgrid .ckl{display:flex;align-items:center;gap:8px;padding:7px 10px;border:1.5px solid var(--line);border-radius:8px;background:#fff;font-size:12.5px;line-height:1.25;font-weight:500;user-select:none}
  .ckgrid .ckl:hover{border-color:var(--verde)}
  .ckgrid .ckl:has(input:checked){background:#e6f4ea;border-color:var(--verde);color:var(--verde-d)}
  .ckgrid .ckl input{width:auto;flex:0 0 auto;margin:0;accent-color:var(--verde)}
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
      <a id="nav-envio" data-menu="envio" title="Envio de Pedidos de Compra aprovados" onclick="showView('envio')"><span class="material-icons">outgoing_mail</span> <span class="navtxt">Envio de Pedidos</span></a>
      <a id="nav-whats" data-menu="whats" title="Assistente que cota por WhatsApp — kanban das conversas" onclick="showView('whats')"><span class="material-icons">smart_toy</span> <span class="navtxt">Assistente WhatsApp</span></a>
      <a id="nav-caixa" data-menu="caixa" title="Caixa de e-mail do suprimentos@ — enviados e recebidos (só leitura)" onclick="showView('caixa')"><span class="material-icons">mail</span> <span class="navtxt">Caixa de E-mail</span></a>
      <a id="nav-obras" data-menu="obras" title="Obras — ficha, características e de-para" onclick="showView('obras')"><span class="material-icons">apartment</span> <span class="navtxt">Obras</span></a>
      <a id="nav-oraculo" data-menu="oraculo" title="Radar IA — oráculo de suprimentos" onclick="showView('oraculo')"><span class="material-icons">auto_awesome</span> <span class="navtxt">Radar IA</span></a>
      <!-- CONSULTA DA OBRA: telas de leitura para engenheiros/coordenadores (papel 'obra'). -->
      <a id="nav-ovradar" data-menu="ov_radar" title="Status dos itens de compra da obra — Curva A e B" onclick="showView('ovradar')"><span class="material-icons">event_available</span> <span class="navtxt">Status - Curva A e B</span></a>
      <a id="nav-ovcot" data-menu="ov_cotacoes" title="Cotações — em que pé está a compra" onclick="showView('ovcot')"><span class="material-icons">price_check</span> <span class="navtxt">Cotações</span></a>
      <a id="nav-ovsc" data-menu="ov_solicitacoes" title="Solicitações de compra — o que a obra pediu e em que pé está" onclick="showView('ovsc')"><span class="material-icons">assignment</span> <span class="navtxt">Solicitações Totvs</span></a>
    </nav>
    <div class="navlabel">Administração</div>
    <nav class="nav">
      <a id="nav-buscaped" data-menu="buscaped" title="Busca de pedidos de compra (consulta ao TOTVS)" onclick="showView('buscaped')"><span class="material-icons">receipt_long</span> <span class="navtxt">Busca Pedidos</span></a>
      <a id="nav-oportunidades" data-menu="oportunidades" title="Oportunidades (Curva ABC)" onclick="showView('oportunidades')"><span class="material-icons">insights</span> <span class="navtxt">Oportunidades</span></a>
      <a id="nav-top20" data-menu="top20" title="Top 20 — volumes consolidados p/ negociação" onclick="showView('top20')"><span class="material-icons">stacked_bar_chart</span> <span class="navtxt">Top 20</span></a>
      <a id="nav-config" data-menu="config" title="Configurações" onclick="showView('config')"><span class="material-icons">settings</span> <span class="navtxt">Configurações</span></a>
      <a id="nav-updates" data-menu="updates" title="Atualizações" onclick="showView('updates')"><span class="material-icons">history</span> <span class="navtxt">Atualizações</span> <span class="navbadge" style="font-size:9px;background:var(--dourado);color:#fff;padding:1px 5px;border-radius:5px;margin-left:auto">temp</span></a>
      <a id="nav-audit" data-menu="audit" title="Auditoria" onclick="showView('audit')"><span class="material-icons">fact_check</span> <span class="navtxt">Auditoria</span> <span class="navbadge" style="font-size:9px;background:var(--dourado);color:#fff;padding:1px 5px;border-radius:5px;margin-left:auto">temp</span></a>
    </nav>
    <div class="whoami" id="whoami"></div>
  </aside>

  <main class="main">
   <section id="view-radar">
    <div class="top" style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap">
      <h1 class="h1" style="flex:0 0 auto"><span class="material-icons" style="color:var(--dourado)">radar</span> Radar de Aquisições</h1>
      <div class="kpis" id="kpis" style="flex:1 1 320px;min-width:0"></div>
      <div style="display:flex;gap:8px;flex:0 0 auto">
        <button class="btn-ghost" onclick="recarregar()" title="Recarregar do servidor — evita trabalhar com dado que outra pessoa já curou"><span class="material-icons" style="font-size:18px">refresh</span> Atualizar</button>
        <button id="btnNovo" class="btn-prim" onclick="novoItem()"><span class="material-icons" style="font-size:18px">add</span> Novo item</button>
      </div>
    </div>

    <div class="panel" style="margin-top:6px">
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
          <th class="srt" onclick="sortBy('nome')"><input type="checkbox" id="rselAll" title="marcar/desmarcar todos os itens visíveis (respeita os filtros)" onclick="event.stopPropagation();rselTodosVisiveis(this.checked)" style="width:auto;accent-color:var(--verde);margin-right:6px;vertical-align:-2px">Item<span class="sar" id="sar-nome"></span></th>
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
      <!-- barra flutuante de AÇÃO EM LOTE (aparece quando há itens selecionados no radar) -->
      <div id="rselBar" style="display:none;position:fixed;left:50%;transform:translateX(-50%);bottom:18px;z-index:900;background:#fff;border:1.5px solid var(--verde);border-radius:12px;box-shadow:0 8px 30px rgba(0,0,0,.18);padding:10px 14px;align-items:center;gap:10px;flex-wrap:wrap;max-width:94vw"></div>
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
        <label class="muted" style="font-size:12px;align-self:center" title="ordena as COLUNAS: a obra que fecha primeiro vem na frente — lê a tela como uma linha do tempo">Obras <select id="mobraord" onchange="renderMatriz()" style="margin-left:4px"><option value="">Ordem manual</option><option value="prazo">Por data de fechar</option></select></label>
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

   <!-- ===== CONSULTA DA OBRA — somente leitura ===== -->
   <section id="view-ovradar" style="display:none">
    <div class="top">
      <h1 class="h1"><span class="material-icons" style="color:var(--dourado)">event_available</span> Status - Curva A e B <span style="font-size:12.5px;font-weight:600;color:var(--muted);letter-spacing:0">· consulta da obra</span></h1>
      <p class="sub">Os itens de compra da sua obra: quando a cotação precisa começar e quando o material é necessário. Tela de consulta — quem altera é o comprador responsável.</p>
    </div>
    <div id="ovRadarWrap"><div class="dempty">Carregando…</div></div>
   </section>

   <section id="view-ovcot" style="display:none">
    <div class="top">
      <h1 class="h1"><span class="material-icons" style="color:var(--dourado)">price_check</span> Cotações <span style="font-size:12.5px;font-weight:600;color:var(--muted);letter-spacing:0">· consulta</span></h1>
      <p class="sub">Em que pé está a compra de cada item: quais fornecedores foram chamados, quem respondeu e por quanto. Tela de consulta — quem negocia é o comprador.</p>
    </div>
    <div id="ovCotWrap"><div class="dempty">Carregando…</div></div>
   </section>

   <section id="view-ovsc" style="display:none">
    <div class="top">
      <h1 class="h1"><span class="material-icons" style="color:var(--dourado)">assignment</span> Solicitações Totvs <span style="font-size:12.5px;font-weight:600;color:var(--muted);letter-spacing:0">· consulta</span></h1>
      <p class="sub">O que a obra pediu e o que já virou cotação. O tempo em aberto é o número que mais importa aqui — clique numa linha para ver os itens.</p>
    </div>
    <div id="ovScWrap"><div class="dempty">Carregando…</div></div>
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
      <button class="btn-ghost" id="cfgLoteBtn" onclick="userLote()" style="flex:0 0 auto;margin-top:4px"><span class="material-icons" style="font-size:18px;vertical-align:-4px">groups</span> Configurar em lote</button>
      <button class="btn-prim" id="cfgAddBtn" onclick="userForm()" style="flex:0 0 auto;margin-top:4px"><span class="material-icons" style="font-size:18px">person_add</span> Adicionar usuário</button>
    </div>
    <div class="bar" style="gap:6px;padding:0 2px 8px">
      <button class="btn-ghost" id="cfgtab-users" onclick="cfgTab('users')" style="padding:6px 14px">👥 Usuários &amp; Permissões</button>
      <button class="btn-ghost" id="cfgtab-resp" onclick="cfgTab('resp')" style="padding:6px 14px">🛒 Responsáveis</button>
      <button class="btn-ghost" id="cfgtab-receitas" onclick="cfgTab('receitas')" style="padding:6px 14px">📚 Aprendizado (receitas)</button>
      <button class="btn-ghost" id="cfgtab-pedmail" onclick="cfgTab('pedmail')" style="padding:6px 14px">📨 E-mail do pedido</button>
      <button class="btn-ghost" id="cfgtab-email" onclick="cfgTab('email')" style="padding:6px 14px">📧 E-mail (disparo)</button>
      <button class="btn-ghost" id="cfgtab-acessos" onclick="cfgTab('acessos')" style="padding:6px 14px">👁 Acessos</button>
      <button class="btn-ghost" id="cfgtab-api" onclick="cfgTab('api')" style="padding:6px 14px">🔑 Chaves de API</button>
      <button class="btn-ghost" id="cfgtab-ia" onclick="cfgTab('ia')" style="padding:6px 14px">🤖 IA &amp; WhatsApp</button>
    </div>
    <div id="cfg-pedmail" style="display:none"><div class="wrap" id="cfgPedMailWrap"></div></div>
    <div id="cfg-email" style="display:none"><div class="wrap" id="cfgEmailWrap"></div></div>
    <div id="cfg-acessos" style="display:none"><div class="wrap" id="cfgAcessosWrap"></div></div>
    <div id="cfg-api" style="display:none"><div class="wrap" id="cfgApiWrap"></div></div>
    <div id="cfg-ia" style="display:none"></div>
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

   <section id="view-whats" style="display:none">
    <div class="top">
      <h1 class="h1"><span class="material-icons" style="color:var(--verde)">smart_toy</span> Assistente WhatsApp</h1>
    </div>
    <div class="wrap" id="waWrap"></div>
   </section>
   <section id="view-caixa" style="display:none">
    <div class="top">
      <h1 class="h1"><span class="material-icons" style="color:var(--verde)">mail</span> Caixa de E-mail</h1>
    </div>
    <div class="wrap" id="caixaWrap"></div>
   </section>
   <section id="view-envio" style="display:none">
    <div class="wrap" id="envWrap"></div>
   </section>
   <section id="view-buscaped" style="display:none">
    <div class="top">
      <h1 class="h1"><span class="material-icons" style="color:var(--dourado)">receipt_long</span> Busca de Pedidos de Compra</h1>
      <p class="sub">Consulta ao TOTVS: descubra <b>com quem já compramos</b> um item, por obra e período. Busque por item, fornecedor ou nº do pedido.</p>
    </div>
    <div class="panel" style="margin-bottom:10px;padding:14px 18px">
      <div style="display:flex;gap:9px;flex-wrap:wrap;align-items:center">
        <div class="search" style="flex:1;min-width:270px;border:1px solid var(--line)">
          <span class="material-icons" style="color:var(--muted)">search</span>
          <input id="bpQ" placeholder="item, fornecedor ou nº do pedido (ex.: martelete)…" onkeydown="if(event.key==='Enter')bpBuscar(1)">
        </div>
        <div style="position:relative">
          <input id="bpObraTxt" list="bpObraList" placeholder="Todas as obras" autocomplete="off" oninput="bpObraPick()" onfocus="this.select()"
                 style="padding:7px 26px 7px 9px;border:1px solid var(--line);border-radius:8px;font-size:12.5px;width:200px">
          <datalist id="bpObraList"></datalist>
          <span id="bpObraX" onclick="bpObraLimpar()" title="limpar" style="display:none;position:absolute;right:7px;top:50%;transform:translateY(-50%);cursor:pointer;color:var(--muted);font-size:15px;line-height:1">&times;</span>
        </div>
        <select id="bpPeriodo" style="padding:7px 9px;border:1px solid var(--line);border-radius:8px;font-size:12.5px">
          <option value="30d">Últimos 30 dias</option><option value="3m" selected>Últimos 3 meses</option>
          <option value="ano">Este ano</option><option value="tudo">Tudo</option></select>
        <select id="bpStatus" style="padding:7px 9px;border:1px solid var(--line);border-radius:8px;font-size:12.5px">
          <option value="">Todos os status</option>
          <option value="A">Pendente</option><option value="U">Em separação</option><option value="R">Em faturamento</option>
          <option value="G">Parcialmente faturado</option><option value="F">Faturado</option><option value="Q">Quitado</option>
          <option value="B">Baixado</option><option value="N">Normal</option><option value="C">Cancelado</option></select>
        <select id="bpUsuario" style="padding:7px 9px;border:1px solid var(--line);border-radius:8px;font-size:12.5px;max-width:170px"><option value="">Todos os usuários</option></select>
        <select id="bpAprov" style="padding:7px 9px;border:1px solid var(--line);border-radius:8px;font-size:12.5px" title="fluxo de alçadas (Fluig)"><option value="">Toda aprovação</option><option value="aprovado">✓ Aprovados</option><option value="pendente">⏳ Em aprovação</option><option value="reprovado">✕ Reprovados</option><option value="sem">⊘ Sem fluxo</option></select>
        <button class="btn-prim" style="padding:7px 14px" onclick="bpBuscar(1)"><span class="material-icons" style="font-size:16px;vertical-align:-3px">search</span> Buscar</button>
      </div>
    </div>
    <div id="bpWrap"><div class="empty">Escolha os filtros e clique em <b>Buscar</b>. Dica: digite o nome do item (ex.: <b>martelete</b>) com "Todas as obras" pra ver com quem já compramos.</div></div>
   </section>

   <section id="view-top20" style="display:none">
    <div class="top" style="display:flex;justify-content:space-between;align-items:flex-start;gap:12px;flex-wrap:wrap">
      <div>
        <h1 class="h1"><span class="material-icons" style="color:var(--dourado)">stacked_bar_chart</span> Top 20 — Volumes p/ Negociação <span id="t20CatLbl" style="font-size:14px;font-weight:800;color:var(--dourado)"></span></h1>
        <p class="sub">Grupos de negociação consolidando <b>todas as obras do radar</b> × próximos 12 meses. Célula = volume que <b>ainda falta consumir</b>: o % já executado do marco (cronograma vivo) sai da conta e o restante distribui do mês atual até o fim do grande marco. Clique na célula pra ver a conta.</p>
      </div>
      <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:4px" class="t20-noprint">
        <button class="btn-ghost" onclick="t20Modo()"><span class="material-icons" style="font-size:16px">swap_horiz</span> <span id="t20ModoTxt">Ver R$</span></button>
        <label style="display:flex;align-items:center;gap:5px;font-size:12px;cursor:pointer"><input type="checkbox" onchange="t20FinToggle(this.checked)"> incluir finalizados</label>
        <button class="btn-ghost" onclick="t20Cfg()"><span class="material-icons" style="font-size:16px">tune</span> Configurar grupos</button>
        <button class="btn-ghost" onclick="t20ExcelTela()" title="Exporta exatamente o que está na tela: o filtro de grupo e os níveis que você expandiu"><span class="material-icons" style="font-size:16px">download</span> Exportar p/ Excel</button>
        <button class="btn-ghost" onclick="window.print()"><span class="material-icons" style="font-size:16px">print</span> Imprimir</button>
        <button class="btn-ghost" onclick="T20.data=null;t20Init()"><span class="material-icons" style="font-size:16px">refresh</span> Atualizar</button>
      </div>
    </div>
    <div style="display:flex;gap:6px;margin:2px 26px 0" class="t20-noprint">
      <button class="btn-ghost" id="t20TabMat" style="border-radius:9px 9px 0 0;border-bottom:none;font-weight:700" onclick="t20Tab('material')"><span class="material-icons" style="font-size:15px">inventory_2</span> Materiais</button>
      <button class="btn-ghost" id="t20TabSrv" style="border-radius:9px 9px 0 0;border-bottom:none" onclick="t20Tab('servico')"><span class="material-icons" style="font-size:15px">engineering</span> Serviços &amp; Equipamentos</button>
    </div>
    <div id="t20wrap" style="margin:0 26px 30px"><div class="empty">Carregando…</div></div>
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

<?php /* versao do arquivo na URL: o navegador so rebaixa o cache quando o .js muda */
$jsv = function ($p) { $f = __DIR__ . '/' . $p; return $p . '?v=' . (is_file($f) ? filemtime($f) : time()); };
?>
  <script>window.APP_VER = <?= (int)$SUP_VER ?>;</script>
  <script src="<?= $jsv('js/app01.js') ?>"></script>
  <script src="<?= $jsv('js/app02.js') ?>"></script>
  <script src="<?= $jsv('js/app03.js') ?>"></script>
  <script src="<?= $jsv('js/app04.js') ?>"></script>
  <script src="<?= $jsv('js/app05.js') ?>"></script>
  <script src="<?= $jsv('js/app06.js') ?>"></script>
  <script src="<?= $jsv('js/app07.js') ?>"></script>
  <script src="<?= $jsv('js/app08.js') ?>"></script>
  <script src="<?= $jsv('js/app09.js') ?>"></script>   <!-- assistente de whatsapp -->   <!-- caixa de e-mail -->   <!-- telas de consulta da obra -->

</body>
</html>
