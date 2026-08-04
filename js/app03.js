/* Cockpit de Suprimentos — parte 3 de 6 do aplicativo.
   Gerado a partir do bloco unico que vivia dentro do index.php: 857 KB num arquivo so faziam
   cada deploy levar de 5 a 10 minutos e falhar calado. O corte respeita fronteiras de nivel
   superior e cada parte foi validada pelo parser antes de existir. A ORDEM importa: os
   arquivos sao carregados na sequencia em que foram cortados. */
let BP={data:null, carregou:false, sort:'data', dir:'desc', obras:[], obraPorLabel:{}, obraKey:'', etapa:''};
async function bpInit(){
  if(!BP.carregou){ BP.carregou=true;
    // a lista vem dos PRÓPRIOS PEDIDOS (não da ficha de obras): assim não falta nenhuma obra que
    // tenha PC, e ainda aparecem as áreas da sede da CAPRETZ (Administrativo, Marketing…).
    try{ const d=await (await fetch('actions/busca_pedidos.php?obras=1&me='+encodeURIComponent((EU&&EU.bitrix_id)||'')+'&_='+Date.now())).json();
      BP.obras=(d.obras||[]);
      BP.obraPorLabel={}; BP.obras.forEach(o=>{ BP.obraPorLabel[o.label.toLowerCase()]=o.chave; });
      const dl=document.getElementById('bpObraList');
      if(dl) dl.innerHTML=BP.obras.map(o=>'<option value="'+esc(o.label)+'">'+o.n+' item(ns)</option>').join('');
    }catch(e){}
  }
}
/* casa o que foi digitado com a obra; texto vazio = todas. Sem casar exato, tenta o único que contém. */
function bpObraPick(){
  const el=document.getElementById('bpObraTxt'); if(!el) return;
  const t=(el.value||'').trim().toLowerCase();
  const x=document.getElementById('bpObraX'); if(x) x.style.display=t?'block':'none';
  if(!t){ BP.obraKey=''; return; }
  if(BP.obraPorLabel && BP.obraPorLabel[t]){ BP.obraKey=BP.obraPorLabel[t]; el.style.color=''; return; }
  const hits=(BP.obras||[]).filter(o=>o.label.toLowerCase().indexOf(t)>=0);
  if(hits.length===1){ BP.obraKey=hits[0].chave; el.style.color=''; }
  else { BP.obraKey=''; el.style.color=hits.length?'':'#c0392b'; }
}
function bpObraLimpar(){ const el=document.getElementById('bpObraTxt'); if(el){el.value='';el.style.color='';} BP.obraKey=''; const x=document.getElementById('bpObraX'); if(x)x.style.display='none'; bpBuscar(1); }
function bpSort(campo){
  if(BP.sort===campo) BP.dir=(BP.dir==='asc'?'desc':'asc');
  else { BP.sort=campo; BP.dir=(campo==='data'||campo==='valor'||campo==='numero'||campo==='itens')?'desc':'asc'; }
  bpBuscar(1);
}
async function bpBuscar(pagina){
  const w=document.getElementById('bpWrap'); if(!w) return;
  bpObraPick();
  // texto digitado que não casou com nenhuma obra: avisa em vez de buscar TUDO calado
  const txt=(document.getElementById('bpObraTxt')||{}).value||'';
  if(txt.trim() && !BP.obraKey){
    const hits=(BP.obras||[]).filter(o=>o.label.toLowerCase().indexOf(txt.trim().toLowerCase())>=0);
    w.innerHTML='<div class="empty">'+(hits.length
      ? 'A obra <b>'+esc(txt)+'</b> está ambígua — escolha uma:<br><span class="dmini">'+hits.slice(0,12).map(o=>esc(o.label)).join(' · ')+(hits.length>12?' …':'')+'</span>'
      : 'Nenhuma obra chamada <b>'+esc(txt)+'</b>.<br><span class="dmini">Apague o campo para ver todas.</span>')+'</div>';
    return;
  }
  const q=val('bpQ'), obra=BP.obraKey||'', per=val('bpPeriodo'), st=val('bpStatus'), us=val('bpUsuario'), ap=val('bpAprov');
  w.innerHTML='<div class="dempty">Consultando os pedidos no TOTVS…</div>';
  let d; try{ d=await (await fetch('actions/busca_pedidos.php?q='+encodeURIComponent(q)+'&obra='+encodeURIComponent(obra||'')
      +'&periodo='+encodeURIComponent(per)+'&status='+encodeURIComponent(st||'')+'&usuario='+encodeURIComponent(us||'')
      +'&aprovacao='+encodeURIComponent(ap||'')+'&etapa='+encodeURIComponent(BP.etapa||'')
      +'&sort='+encodeURIComponent(BP.sort)+'&dir='+encodeURIComponent(BP.dir)+'&pagina='+(pagina||1)
      +'&me='+encodeURIComponent((EU&&EU.bitrix_id)||'')+'&_='+Date.now())).json(); }
  catch(e){ w.innerHTML='<div class="empty">Falha ao consultar o TOTVS.</div>'; return; }
  if(d.error){ w.innerHTML='<div class="empty">'+esc(d.error)+'</div>'; return; }
  BP.data=d;
  // filtro de usuário: preenche com quem aparece no recorte, preservando a escolha atual
  const su=document.getElementById('bpUsuario');
  if(su&&d.usuarios){ const atual=su.value;
    su.innerHTML='<option value="">Todos os usuários</option>'+d.usuarios.map(u=>'<option value="'+esc(u)+'">'+esc(u)+'</option>').join('');
    if(atual&&d.usuarios.indexOf(atual)>=0) su.value=atual; else if(d.usuario) su.value=d.usuario; }
  bpRender();
}
/* APROVAÇÃO (Fluig) — a informação que o Murilo quer ver de relance.
   ✓ verde aprovado · ✕ vermelho reprovado · ⏳ âmbar parado (com QUEM) · ⊘ cinza fora do fluxo.
   O texto miúdo embaixo é o "com quem está" ou o motivo da reprovação — o que exige ação. */
const BP_APROV={
  aprovado :{ic:'check_circle', cor:'#1F6B3B', bg:'#e8f5ee', t:'Aprovado'},
  reprovado:{ic:'cancel',       cor:'#c0392b', bg:'#fdeaea', t:'Reprovado'},
  pendente :{ic:'schedule',     cor:'#a4761c', bg:'#fdf4e3', t:'Em aprovação'},
  sem      :{ic:'remove_circle_outline', cor:'#8a9299', bg:'#f4f5f6', t:'Sem fluxo'}
};
function bpDataHora(s){ try{ return new Date(s).toLocaleString('pt-BR'); }catch(e){ return s||''; } }
function bpDataCurta(s){ try{ const d=new Date(s); return ('0'+d.getDate()).slice(-2)+'/'+('0'+(d.getMonth()+1)).slice(-2); }catch(e){ return ''; } }
function bpAprovCel(p){
  const a=BP_APROV[p.aprovacao]||BP_APROV.sem;
  // 2ª linha: quem trava (pendente) ou por que caiu (reprovado). É o que muda a decisão.
  let sub='';
  if(p.aprovacao==='pendente'&&p.aprovacao_etapa) sub=p.aprovacao_etapa;
  else if(p.aprovacao==='reprovado'&&p.aprovacao_obs) sub=p.aprovacao_obs;
  else if(p.aprovacao==='aprovado'&&p.aprovado_por) sub='por '+p.aprovado_por;
  const tip=[p.aprovacao_label, p.aprovado_por?('último registro: aprovado por '+p.aprovado_por):'', p.aprovacao_obs?('observação: '+p.aprovacao_obs):'']
    .filter(Boolean).join(' — ');
  return '<div title="'+esc(tip)+'" style="display:flex;align-items:flex-start;gap:4px">'
    +'<span class="material-icons" style="font-size:15px;color:'+a.cor+';flex:none;margin-top:1px">'+a.ic+'</span>'
    +'<div style="min-width:0"><div style="font-size:11px;font-weight:800;color:'+a.cor+';line-height:1.15">'+esc(a.t)+'</div>'
    +(sub?'<div style="font-size:9.5px;color:var(--muted);overflow:hidden;text-overflow:ellipsis;white-space:nowrap;line-height:1.2">'+esc(sub)+'</div>':'')
    +'</div></div>';
}
function bpStCor(l){ return {'Pendente':'#a4761c','Em separação':'#2b6cb0','Em faturamento':'#2b6cb0','Parcialmente faturado':'#a4761c','Faturado':'#1F6B3B','Quitado':'#1F6B3B','Baixado':'#6a737b','Normal':'#6a737b','Cancelado':'#c0392b'}[l]||'#8a9299'; }
/* Chips com a conta do recorte INTEIRO por situação de aprovação; clicar filtra.
   Reprovado e Em aprovação vêm primeiro por serem os que pedem ação. */
function bpAprovChips(d){
  const R=d.resumo_aprovacao||{}, atual=(document.getElementById('bpAprov')||{}).value||'';
  const ordem=[['reprovado',R.reprovado],['pendente',R.pendente],['aprovado',R.aprovado],['sem',R.sem]];
  return ordem.filter(([k,n])=>n>0).map(([k,n])=>{ const a=BP_APROV[k], on=atual===k;
    return '<span onclick="bpFiltroAprov(\''+k+'\')" title="clique p/ '+(on?'limpar':'ver só estes')+'" '
      +'style="cursor:pointer;display:inline-flex;align-items:center;gap:3px;background:'+a.bg+';color:'+a.cor
      +';border-radius:20px;padding:2px 9px;font-size:10.5px;font-weight:800;'+(on?'box-shadow:0 0 0 2px '+a.cor:'')+'">'
      +'<span class="material-icons" style="font-size:12px">'+a.ic+'</span>'+n+' '+esc(a.t.toLowerCase())+'</span>';
  }).join(' ');
}
function bpFiltroAprov(k){
  const el=document.getElementById('bpAprov'); if(!el) return;
  el.value = (el.value===k ? '' : k);
  BP.etapa=''; bpBuscar(1);
}
/* CARDS DE ETAPA — "com quem estao os pedidos parados". So aparecem quando ha algo em aprovacao no
   recorte. Clicar filtra pela etapa; clicar de novo limpa. Alem da contagem mostram o VALOR parado,
   que e o que muda a conversa: 39 pedidos no Diretor pode ser troco ou pode ser milhao. */
function bpEtapaCards(d){
  const E=d.etapas||[]; if(!E.length) return '';
  const tot=E.reduce((a,x)=>a+x.n,0), totV=E.reduce((a,x)=>a+Number(x.valor||0),0);
  const M=v=>v>=1e6?('R$ '+(v/1e6).toFixed(1).replace('.',',')+' mi'):(v>=1e3?('R$ '+Math.round(v/1e3)+' mil'):('R$ '+Math.round(v||0)));
  const card=(x)=>{ const on=BP.etapa===x.etapa_raw;
    return '<div onclick="bpFiltroEtapa('+jsArg(x.etapa_raw)+')" '
      +'title="'+esc(x.n+' pedido(s) parado(s) em '+x.etapa+' — '+M(x.valor))+(on?' · clique p/ limpar':' · clique p/ ver so estes')+'" '
      +'style="cursor:pointer;flex:1;min-width:132px;background:'+(on?'#fdf4e3':'#fbfdfb')+';border:1px solid '+(on?'#a4761c':'var(--line)')+';border-radius:9px;padding:8px 11px">'
      +'<div style="font-size:19px;font-weight:800;color:#a4761c;line-height:1">'+x.n+'</div>'
      +'<div style="font-size:11px;font-weight:700;color:#33404a;margin-top:2px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">'+esc(x.etapa)+'</div>'
      +'<div style="font-size:10.5px;color:var(--muted)">'+M(x.valor)+'</div></div>'; };
  return '<div style="padding:10px 16px 4px;border-bottom:1px solid var(--line);background:#fbfdfb">'
    +'<div style="display:flex;align-items:center;gap:8px;margin-bottom:7px">'
    +'<span class="material-icons" style="font-size:16px;color:#a4761c">schedule</span>'
    +'<b style="font-size:12.5px">Parados em aprovacao — com quem estao</b>'
    +'<span class="dmini">'+tot+' pedido(s) · '+M(totV)+'</span>'
    +(BP.etapa?'<button class="btn-ghost" style="margin-left:auto;padding:3px 9px;font-size:11px;color:var(--pend);font-weight:700" onclick="bpEtapaLimpar()">limpar etapa</button>':'')
    +'</div><div style="display:flex;gap:8px;flex-wrap:wrap">'+E.map(card).join('')+'</div></div>';
}
function bpEtapaLimpar(){ BP.etapa=''; bpBuscar(1); }
function bpFiltroEtapa(raw){
  BP.etapa = (BP.etapa===raw ? '' : raw);
  // filtrar por etapa ja implica "em aprovacao" — deixar o seletor em outra coisa daria recorte vazio
  const el=document.getElementById('bpAprov'); if(el && BP.etapa) el.value='';
  bpBuscar(1);
}
function bpRender(){
  const w=document.getElementById('bpWrap'), d=BP.data; if(!w||!d) return;
  const ps=d.pedidos||[];
  if(!ps.length){ w.innerHTML='<div class="empty">Nenhum pedido encontrado com esses filtros.<br><span class="dmini">Tente ampliar o período, tirar o filtro de status/usuário ou usar outra palavra (a busca cobre a descrição do item, a <b>observação digitada</b>, o fornecedor, o nº do pedido e o usuário).</span></div>'; return; }
  const totalV=ps.reduce((a,p)=>a+(p.total||0),0);
  const th=(campo,lbl,al)=>{ const on=d.sort===campo, ar=on?(d.dir==='asc'?' ▲':' ▼'):'';
    return '<th style="text-align:'+(al||'left')+';cursor:pointer;'+(on?'color:var(--verde-d)':'')+'" onclick="bpSort(\''+campo+'\')" title="clique p/ ordenar TODOS os '+d.total+' pedidos">'+lbl+'<span style="font-size:9px">'+ar+'</span></th>'; };
  // larguras FIXAS somando 100% → cabe na tela, sem rolagem lateral (texto longo trunca com … e tooltip)
  let h='<div class="panel" style="padding:0;overflow:hidden">'
   +'<div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;padding:11px 16px;border-bottom:1px solid var(--line);background:#fbfdfb">'
   +'<b style="font-size:13px">'+d.total+' pedido(s)</b>'
   +'<span class="muted" style="font-size:11.5px">página '+d.pagina+' de '+d.paginas+' · '+BRL(totalV)+' nesta página</span>'
   +(d.truncado?'<span class="dchip" style="background:#fff9e6;color:#6b5d1f;font-size:10px" title="a consulta bateu no teto de leitura — estreite o período ou a busca">resultado parcial</span>':'')
   +bpAprovChips(d)
   +'<span class="muted" style="font-size:11px;margin-left:auto">ordenado por <b>'+esc(d.sort)+'</b> '+(d.dir==='asc'?'↑':'↓')+'</span>'
   +'</div>'
   +bpEtapaCards(d)
   +'<table class="dtable" style="width:100%;table-layout:fixed">'
   +'<colgroup><col style="width:7%"><col style="width:12%"><col style="width:12%"><col style="width:14%"><col style="width:24%"><col style="width:9%"><col style="width:8%"><col style="width:11%"><col style="width:3%"></colgroup>'
   +'<thead><tr>'+th('numero','Pedido')+th('aprovacao','Aprovação')+th('obra','Obra')+th('fornecedor','Fornecedor')+th('itens','Item / observação')
   +th('usuario','Usuário')+th('data','Data','center')+th('valor','Valor','right')+'<th></th></tr></thead><tbody>';
  const cut='overflow:hidden;text-overflow:ellipsis;white-space:nowrap';
  ps.forEach(p=>{
    const forn=(p.fornecedores||[]).join(', ')||'—';
    const itensTxt=(p.amostra||[]).join(' · ');
    const obsTxt=(p.obs||[]).join(' · ');
    h+='<tr>'
      +'<td style="text-align:left;'+cut+'"><b>'+esc(String(p.numero).replace(/^0+/,''))+'</b>'
        /* Marca de ENVIADO. Vem do livro-caixa do modulo Envio — a MESMA tabela que impede o
           segundo envio. Ler dali garante que as duas telas nunca discordem. */
        +(p.enviado?('<div style="font-size:9px;font-weight:800;color:#1f8f4e;letter-spacing:.2px" title="'
            +esc('Enviado por e-mail em '+bpDataHora(p.enviado.em)+(p.enviado.para?(' para '+p.enviado.para):'')
                 +(p.enviado.por?(' — por '+p.enviado.por):'')
                 +(p.enviado.destino==='obra'?' (copia para lancamento, nao foi ao fornecedor)':''))
            +'">&#10003; ENVIADO '+esc(bpDataCurta(p.enviado.em))+'</div>'):'')
        +(p.repartido_n?'<div style="font-size:9px;font-weight:800;color:#a4761c;letter-spacing:.2px" title="'+esc('Mesmo fornecedor, mesmo valor e mesma data em '+p.repartido_n+' obras ('+(p.repartido_obras||[]).join(' · ')+'). Normalmente e uma compra unica repartida entre obras — confira a observacao do item.')+'">⇄ '+p.repartido_i+'/'+p.repartido_n+' OBRAS</div>':'')
      +'</td>'
      +'<td style="text-align:left;padding-right:4px">'+bpAprovCel(p)+'</td>'
      +'<td style="text-align:left;font-size:11px;'+cut+'" title="'+esc((p.obra||p.coligada||'')+(p.obra_fonte==='RATEIO_CAPRETZ'?' — compra da CAPRETZ rateada p/ esta obra':'')+(p.centro_custo?' · c.custo '+p.centro_custo:'')+(p.ccusto_nome?' ('+p.ccusto_nome+')':''))+'">'+(p.obra?esc(p.obra):'<span class="muted">'+esc(p.coligada||'—')+'</span>')+'</td>'
      +'<td style="text-align:left;font-size:11px;'+cut+'" title="'+esc(forn)+'">'+esc(forn)+'</td>'
      +'<td style="text-align:left;font-size:11px;overflow:hidden" title="'+esc(itensTxt+(obsTxt?(' — '+obsTxt):''))+'">'
        +'<div style="'+cut+'">'+esc(itensTxt||(p.n_itens+' item(ns)'))+'</div>'
        +(obsTxt?'<div style="'+cut+';color:var(--muted);font-size:10px">'+esc(obsTxt)+'</div>':'')
      +'</td>'
      +'<td style="text-align:left;font-size:11px;'+cut+'" title="quem criou o pedido no TOTVS">'+esc(p.usuario||'—')+'</td>'
      +'<td style="text-align:center;font-size:11px;'+cut+'">'+(p.data?D(String(p.data).slice(0,10)):'—')+'</td>'
      /* faturamento REBAIXADO a subtítulo: continua visível e filtrável, mas quem manda na tela agora
         é a aprovação — foi o que o Murilo pediu ("o status de faturamento não é o mais importante") */
      +'<td class="r" style="'+cut+'"><b style="font-size:11.5px">'+BRL(p.total)+'</b>'
        +'<div style="font-size:9px;font-weight:700;color:'+bpStCor(p.status_label)+'">'+esc(p.status_label||'')+'</div></td>'
      +'<td style="text-align:center"><button class="btn-ghost" style="padding:2px 5px" onclick="cotPedidoVer(\''+esc(p.numero)+'\',\''+esc(p.coligada_cod)+'\')" title="ver o pedido completo"><span class="material-icons" style="font-size:15px;vertical-align:-3px">visibility</span></button></td>'
      +'</tr>';
  });
  h+='</tbody></table>';
  if(d.paginas>1){
    const btn=(n,lbl,on)=>'<button class="btn-ghost" style="padding:4px 10px;font-size:12px;'+(on?'background:var(--verde);color:#fff;font-weight:700':'')+'" onclick="bpBuscar('+n+')">'+lbl+'</button>';
    let nav='<div style="display:flex;gap:5px;align-items:center;justify-content:center;flex-wrap:wrap;padding:11px;border-top:1px solid var(--line)">';
    if(d.pagina>1) nav+=btn(d.pagina-1,'‹ anterior');
    const ini=Math.max(1,d.pagina-2), fim=Math.min(d.paginas,ini+4);
    for(let i=ini;i<=fim;i++) nav+=btn(i,String(i),i===d.pagina);
    if(d.pagina<d.paginas) nav+=btn(d.pagina+1,'próxima ›');
    h+=nav+'</div>';
  }
  h+='</div><div class="note"><b>Aprovação</b> = fluxo de alçadas do Fluig: <b style="color:#1F6B3B">✓ aprovado</b> · <b style="color:#c0392b">✕ reprovado</b> (o texto miúdo é o motivo) · <b style="color:#a4761c">⏳ em aprovação</b> (o texto miúdo é com QUEM está parado) · <b style="color:#8a9299">⊘ sem fluxo</b> (pedido que não passou pelo Fluig). O status de <b>faturamento</b> agora fica embaixo do valor. Consulta ao TOTVS (somente leitura). A busca cobre <b>descrição do item, observação digitada, fornecedor, nº do pedido e usuário</b>. <b>Clique no cabeçalho</b> p/ ordenar todos os pedidos da busca. Texto longo aparece truncado — passe o mouse pra ver inteiro, ou clique no 👁 pro pedido completo. <b>⇄ n/N obras</b> = o mesmo fornecedor, valor e data aparecem em N obras — quase sempre uma compra única repartida (a observação do item costuma dizer o valor cheio). A <b>obra</b> vem do TOTVS já resolvida: <b>CAPRETZ/&lt;obra&gt;</b> = compra da CAPRETZ rateada para aquela obra; <b>CAPRETZ</b> sozinho = compra da própria CAPRETZ.</div>';
  w.innerHTML=h;
}
/* ===================== DASHBOARDS ===================== */
let DASH={tab:null, D:null, oppByObra:{}, cots:null, sols:null, carregandoF:false,
         gfiltro:null, csub:'radar', cobra:'', cstatus:''};
const DASH_TABS=[['comprador','Comprador','person'],['gerente','Gerente de Compras','groups'],['diretor','Diretor','insights']];   // aba "Oportunidades" DELETADA (23/jul, pedido do Murilo — não servia; a tela Oportunidades do MENU continua)
function dashAllowed(){ const papel=(EU&&EU.papel)||''; if(IS_ADMIN||papel==='diretor') return DASH_TABS.map(t=>t[0]);
  let a; if(papel==='gerente') a=['gerente','comprador']; else a=['comprador'];
  const d=(EU&&EU.dashboard)||''; if(d&&!a.includes(d)&&DASH_TABS.some(t=>t[0]===d)) a.unshift(d);   // painel ATRIBUÍDO pelo admin sempre entra
  return a; }
function dashInit(){
  const allowed=dashAllowed(), tb=document.getElementById('dtabs');
  if(tb) tb.innerHTML=DASH_TABS.filter(t=>allowed.includes(t[0])).map(t=>`<button class="dtab" id="dtab-${t[0]}" onclick="dashTab('${t[0]}')"><span class="material-icons">${t[2]}</span> ${t[1]}</button>`).join('');
  if(!DASH.tab){ const d=(EU&&EU.dashboard)||''; if(d&&allowed.includes(d)) DASH.tab=d; }   // aba inicial = painel atribuído
  if(!DASH.tab||!allowed.includes(DASH.tab)) DASH.tab=allowed[0]||'comprador';
  dashActive(); dashLoad();
}
function dashActive(){ DASH_TABS.forEach(t=>{ const b=document.getElementById('dtab-'+t[0]); if(b) b.classList.toggle('on',t[0]===DASH.tab); }); }
function dashTab(t){ DASH.tab=t; DASH.gfiltro=null; DASH.cfiltro=null;
  // os filtros de obra/status são POR PAINEL: a lista de obras do gerente e a do comprador não
  // são as mesmas, e carregar a escolha de um pro outro deixava a tela vazia sem explicação.
  DASH.cobra=''; DASH.cstatus='';
  dashActive(); renderDash(); }
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
  dashLoadFunil();
}
/* Cotações e Solicitações são as duas pontas do funil (radar -> SC -> cotação). São pesadas — a de
   solicitações vai ao TOTVS — então carregam UMA vez, em paralelo, e a tela se redesenha quando chegam.
   Servem tanto ao painel do gerente quanto à aba "Solicitações" do comprador. */
async function dashLoadFunil(){
  if(DASH.carregandoF || (DASH.cots && DASH.sols)) return;
  DASH.carregandoF=true;
  const me=encodeURIComponent((EU&&EU.bitrix_id)||'');
  try{
    const [c,x]=await Promise.all([
      DASH.cots?Promise.resolve(null):fetch('actions/cotacoes.php?me='+me+'&_='+Date.now()).then(r=>r.json()).catch(()=>null),
      DASH.sols?Promise.resolve(null):fetch('actions/solicitacoes.php?me='+me+'&_='+Date.now()).then(r=>r.json()).catch(()=>null),
    ]);
    if(c&&c.cotacoes) DASH.cots=c.cotacoes;
    if(x&&x.solicitacoes) DASH.sols=x.solicitacoes;
  }catch(e){}
  DASH.carregandoF=false;
  renderDash();
}
function dashRefresh(){ DASH.items=null; DASH.oppByObra={}; DASH.cots=null; DASH.sols=null; DASH.gfiltro=null; dashLoad(); }
function renderDash(){
  const w=document.getElementById('dwrap'), D=DASH.D; if(!w)return;
  if(!D){ w.innerHTML='<div class="dempty">Sem dados.</div>'; return; }
  const meta=document.getElementById('dmeta'); if(meta) meta.textContent=`${D.obras.length} obra(s) · ${D.totalItens} itens · hoje ${D.hojeBR}`;
  const f={comprador:renderDashComprador,gerente:renderDashGerente,diretor:renderDashDiretor}[DASH.tab];
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
  const cm={}; items.forEach(i=>{ const r=nrmResp(i.responsavel); if(!r)return; (cm[r]=cm[r]||{nome:r,itens:0,criticos:0,exposta:0}); cm[r].itens++;
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
  /* PAINEL PESSOAL do comprador.
     - DUAS ABAS: o que está com ele no RADAR e as SOLICITAÇÕES DE COMPRA da(s) obra(s) dele.
     - FILTROS por obra e por status valem para as duas abas, e são aplicados ANTES dos cards,
       para o número do card sempre bater com a tabela de baixo.
     - CARDS CLICÁVEIS: clicar num card filtra a tabela ("Detalhar ›"; clicar de novo desfaz). */
  const base=(DASH.items&&DASH.items.length)?DASH.items:(MAT||[]);
  const papel=(EU&&EU.papel)||'';
  const podeEscolher=IS_ADMIN||papel==='gerente'||papel==='diretor';
  const eu=((EU&&EU.nome)||'').trim();
  const nomes=[...new Set(base.map(i=>nrmResp(i.responsavel)).filter(Boolean))].sort();
  const alvo=(DASH.comprador||eu||nomes[0]||'').trim();
  const val=i=>Number(i.verba||0), lvl=i=>alertLevel(i);
  const dDiff=f=>f?Math.round((new Date(f+'T00:00:00')-new Date(D.hoje+'T00:00:00'))/864e5):null;
  const M=v=>v>=1e6?('R$ '+(v/1e6).toFixed(1).replace('.',',')+' mi'):(v>=1e3?('R$ '+Math.round(v/1e3)+' mil'):('R$ '+Math.round(v||0)));

  const meusTodos=base.filter(i=>nrmResp(i.responsavel)===nrmResp(alvo));
  const sols=DASH.sols||[];
  const minhasSC=sols.filter(x=>nrmResp(x.comprador_nome)===nrmResp(alvo));

  const sub=DASH.csub==='sc'?'sc':'radar';
  const selPainel=podeEscolher
    ?`<div style="display:flex;align-items:center;gap:8px"><span class="dmini">Painel de:</span><select onchange="DASH.comprador=this.value;DASH.cfiltro=null;DASH.cobra='';renderDash()" style="padding:5px 10px;border:1px solid var(--line);border-radius:7px;font-size:13px">${nomes.map(n=>`<option ${n===alvo?'selected':''}>${esc(n)}</option>`).join('')}</select></div>`
    :`<div class="dmini">Painel pessoal de <b>${esc(alvo||'—')}</b></div>`;

  /* ---- abas ---- */
  const aba=(k,ic,lbl,n)=>`<button class="dtab ${sub===k?'on':''}" style="padding:6px 13px;font-size:12.5px" onclick="dashCSub('${k}')"><span class="material-icons" style="font-size:15px">${ic}</span> ${lbl}${n!=null?` <b>${n}</b>`:''}</button>`;
  const abas=`<div style="display:flex;gap:6px;flex-wrap:wrap">${aba('radar','radar','Radar de aquisições',meusTodos.length)}${aba('sc','inbox','Solicitações de compra',DASH.sols?minhasSC.length:null)}</div>`;

  /* ---- filtros (obra + status), dependentes da aba ---- */
  const obrasRadar=[...new Set(meusTodos.map(i=>i.obra_nome).filter(Boolean))].sort();
  const obrasSC=[...new Set(minhasSC.map(x=>x.nome_obra).filter(Boolean))].sort();
  const obrasOpc=sub==='sc'?obrasSC:obrasRadar;
  const statusOpc=sub==='sc'
    ? [['pendente','Pendente'],['em_cotacao','Em cotação'],['cotacoes_recebidas','Cotações recebidas'],['pedido_criado','Pedido criado'],['cancelado','Cancelado']]
    : STATUSES.map(x=>[x,x]);
  const selEstilo='padding:5px 9px;border:1px solid var(--line);border-radius:7px;font-size:12.5px;max-width:190px';
  const filtros=`<div style="display:flex;gap:7px;flex-wrap:wrap;align-items:center">
    <select onchange="DASH.cobra=this.value;renderDash()" style="${selEstilo}"><option value="">Todas as obras</option>${obrasOpc.map(o=>`<option ${DASH.cobra===o?'selected':''}>${esc(o)}</option>`).join('')}</select>
    <select onchange="DASH.cstatus=this.value;renderDash()" style="${selEstilo}"><option value="">Todos os status</option>${statusOpc.map(o=>`<option value="${esc(o[0])}" ${DASH.cstatus===o[0]?'selected':''}>${esc(o[1])}</option>`).join('')}</select>
    ${(DASH.cobra||DASH.cstatus)?`<button class="btn-ghost" style="padding:4px 10px;font-size:11.5px;color:var(--pend);font-weight:700" onclick="DASH.cobra='';DASH.cstatus='';renderDash()">✕ limpar filtros</button>`:''}
  </div>`;
  const topo=`<div style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;margin-bottom:11px">${selPainel}${filtros}</div>${abas}<div style="height:10px"></div>`;

  /* ═══════════════ ABA SOLICITAÇÕES ═══════════════ */
  if(sub==='sc'){
    if(!DASH.sols) { dashLoadFunil(); return topo+'<div class="dempty">Carregando as solicitações do TOTVS…</div>'; }
    let lista=minhasSC;
    if(DASH.cobra)   lista=lista.filter(x=>x.nome_obra===DASH.cobra);
    if(DASH.cstatus) lista=lista.filter(x=>x.status===DASH.cstatus);
    if(!minhasSC.length) return topo+`<div class="dempty">Nenhuma solicitação de compra está com <b>${esc(alvo||'você')}</b>.<br><span class="dmini">O comprador da SC vem do de-para da obra (Solicitações › atribuição de obra), não do item do radar.</span></div>`;

    const semCot=lista.filter(x=>x.cobertura==='vazio');
    const parcial=lista.filter(x=>x.cobertura==='parcial');
    const velhas=lista.filter(x=>(x.dias||0)>=15&&x.cobertura==='vazio');
    const FSC={
      todas:{t:'Todas as minhas solicitações', list:lista.slice().sort((a,b)=>(b.dias||0)-(a.dias||0))},
      semcot:{t:'Sem nenhuma cotação — precisam começar', list:semCot.slice().sort((a,b)=>(b.dias||0)-(a.dias||0))},
      parcial:{t:'Parcialmente cotadas — faltam itens', list:parcial.slice().sort((a,b)=>(b.dias||0)-(a.dias||0))},
      velhas:{t:'Paradas há 15 dias ou mais, sem cotação', list:velhas.slice().sort((a,b)=>(b.dias||0)-(a.dias||0))},
    };
    const at=FSC[DASH.cfiltro]?DASH.cfiltro:'todas';
    const cardSC=(k,v,l)=>`<div class="dkpi" onclick="dashCFiltro('${k}')" style="cursor:pointer;${at===k?'border:1.5px solid var(--verde);box-shadow:0 0 0 2px #e6f4ea':''}"><div class="v">${v}</div><div class="l">${l}</div>
      <div style="margin-top:6px;font-size:10.5px;font-weight:800;color:${at===k?'var(--verde-d)':'var(--verde)'}">${at===k?'▼ NA TABELA':'DETALHAR ›'}</div></div>`;
    const cur=FSC[at], capped=cur.list.slice(0,60);
    const cobChip=c=>({vazio:['#8a9299','sem cotação'],parcial:['var(--dourado)','parcial'],total:['var(--ok)','cotada']}[c]||['#8a9299','—']);
    return topo+`
    <div class="dkpis">
      ${cardSC('todas',`${lista.length}`,`solicitações comigo`)}
      ${cardSC('semcot',`<span class="gold">${semCot.length}</span>`,`sem cotação ainda`)}
      ${cardSC('parcial',`${parcial.length}`,`parcialmente cotadas`)}
      ${cardSC('velhas',`<span class="red">${velhas.length}</span>`,`paradas há 15+ dias`)}
    </div>
    <div class="dcard wide" style="margin-top:10px">${cotSecHead('inbox',cur.t,cur.list.length+' registro(s)','')}
      <div style="overflow-x:auto"><table class="dtable"><thead><tr><th>SC</th><th>Obra</th><th class="r">Aberta há</th><th class="r">Itens</th><th>Situação</th><th>Status</th><th>Primeiro item</th><th></th></tr></thead><tbody>
      ${capped.map(x=>{const cc=cobChip(x.cobertura);
        return `<tr><td><b>${esc(String(x.numero||'').replace(/^0+/,''))}</b></td>
        <td style="white-space:nowrap">${esc(x.nome_obra||'—')}</td>
        <td class="r"><b style="color:${(x.dias||0)>=30?'var(--pend)':((x.dias||0)>=15?'#c77f1a':'inherit')}">${x.dias!=null?x.dias+'d':'—'}</b></td>
        <td class="r">${x.n_itens||0}${x.cot_cob?` <span class="dmini">(${x.cot_cob} cotados)</span>`:''}</td>
        <td><span class="dchip" style="background:${cc[0]};font-size:9.5px">${cc[1]}</span></td>
        <td>${esc(SOL_ST_LBL(x.status))}</td>
        <td style="max-width:280px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="${esc(x.primeiro||'')}">${esc(x.primeiro||'')}</td>
        <td class="r">${(x.cotacoes&&x.cotacoes.length)?x.cotacoes.map(c=>`<button class="btn-ghost" style="padding:2px 7px;font-size:11px;color:var(--verde-d);font-weight:700" onclick="showView('cotacoes');setTimeout(()=>cotAbrir(${c.id}),200)">#${c.id}</button>`).join(''):'<span class="dmini">—</span>'}</td></tr>`;}).join('')
        ||'<tr><td colspan="8" class="dempty" style="padding:18px">nada neste recorte 🎉</td></tr>'}
      </tbody></table></div>
      ${cur.list.length>60?`<div class="dmini" style="margin-top:6px">mostrando 60 de ${cur.list.length}</div>`:''}
      <div class="dmini" style="margin-top:6px">A fila do TOTVS só traz SC <b>pendente</b>: o que sumiu daqui já foi atendido ou cancelado lá.</div>
    </div>`;
  }

  /* ═══════════════ ABA RADAR ═══════════════ */
  let meus=meusTodos;
  if(DASH.cobra)   meus=meus.filter(i=>i.obra_nome===DASH.cobra);
  if(DASH.cstatus) meus=meus.filter(i=>(i.status||'Não Iniciado')===DASH.cstatus);
  if(!meusTodos.length) return topo+`<div class="dempty">Nenhum item do radar está atribuído a <b>${esc(alvo||'você')}</b>.<br><span class="dmini">O responsável de cada item é definido no Radar (coluna Responsável) ou em Configurações › Responsáveis.</span></div>`;
  if(!meus.length) return topo+`<div class="dempty">Nenhum item de <b>${esc(alvo)}</b> passa nos filtros escolhidos.<br><span class="dmini">Limpe o filtro de obra ou de status para ver tudo.</span></div>`;

  const abertos=meus.filter(i=>i.status!=='Finalizado'&&i.status!=='Não se aplica');
  const atras=abertos.filter(i=>['critico','atrasado'].includes(lvl(i)));
  const v7=abertos.filter(i=>{const d=dDiff(i.fim_cotacao); return d!==null&&d>=0&&d<=7;});
  const v30=abertos.filter(i=>{const d=dDiff(i.fim_cotacao); return d!==null&&d>7&&d<=30;});
  const emCotItens=abertos.filter(i=>/cota/i.test(i.status||''));
  const verbaTot=abertos.reduce((a,i)=>a+val(i),0), verbaAtras=atras.reduce((a,i)=>a+val(i),0);
  const nivelOrd={critico:0,atrasado:1,proximo:2};
  const byPrio=(a,b)=>(((nivelOrd[lvl(a)]??3)-(nivelOrd[lvl(b)]??3)))||(val(b)-val(a));
  const byPrazo=(a,b)=>((a.fim_cotacao||'9999').localeCompare(b.fim_cotacao||'9999'))||(val(b)-val(a));
  const byVerba=(a,b)=>val(b)-val(a);
  const FDEF={
    abertos:{t:'Todos os meus itens abertos', list:abertos.slice().sort(byPrio)},
    atrasados:{t:'Atrasados / vencidos — maior verba primeiro', list:atras.slice().sort(byVerba)},
    v7:{t:'Vencem em até 7 dias', list:v7.slice().sort(byPrazo)},
    v30:{t:'Vencem em 8–30 dias', list:v30.slice().sort(byPrazo)},
    cotacao:{t:'Em cotação agora — maior verba primeiro', list:emCotItens.slice().sort(byVerba)},
    verba:{t:'Maiores verbas sob minha gestão', list:abertos.slice().sort(byVerba)},
  };
  const ativo=FDEF[DASH.cfiltro]?DASH.cfiltro:'';
  const card=(key,vHtml,lHtml)=>`<div class="dkpi" onclick="dashCFiltro('${key}')" title="${ativo===key?'clique p/ limpar o filtro':'clique p/ detalhar na tabela abaixo'}" style="cursor:pointer;${ativo===key?'border:1.5px solid var(--verde);box-shadow:0 0 0 2px #e6f4ea':''}">
    <div class="v">${vHtml}</div><div class="l">${lHtml}</div>
    <div style="margin-top:6px;font-size:10.5px;font-weight:800;letter-spacing:.2px;color:${ativo===key?'var(--pend)':'var(--verde)'}">${ativo===key?'✕ LIMPAR FILTRO':'DETALHAR ›'}</div></div>`;
  const cur=FDEF[ativo]||null;
  const listaBase=cur?cur.list:abertos.slice().sort(byPrio).slice(0,15);
  const capped=cur?cur.list.slice(0,60):listaBase;
  const titulo=cur?`${cur.t} — ${cur.list.length} item(ns)`:'Ações prioritárias — atacar nesta ordem (atrasado primeiro · maior verba primeiro)';
  const acaoDe=i=>{const st=i.status||'Não Iniciado'; if(/cota/i.test(st))return'Cobrar propostas'; if(/proposta/i.test(st))return'Aprovar fornecedor'; if(/negocia/i.test(st))return'Fechar negociação'; if(/pend/i.test(st))return'Resolver pendência'; return'Iniciar cotação';};
  const chipNivel=i=>{const l=lvl(i); return l==='critico'?'<span class="dchip" style="background:var(--pend)">VENCIDO</span>':(l==='atrasado'?'<span class="dchip" style="background:#e67e22">atrasado</span>':(l==='proximo'?'<span class="dchip" style="background:var(--dourado);color:#333">próximo</span>':''));};
  const nFiltro=(DASH.cobra||DASH.cstatus)?`<span class="dmini"> · recorte de ${meus.length} de ${meusTodos.length} itens</span>`:'';
  return topo+`
  <div class="dkpis">
    ${card('abertos',`${abertos.length}`,`itens ABERTOS comigo<br><span class="dmini">${meus.length} no total (com finalizados)</span>`)}
    ${card('atrasados',`<span class="red">${atras.length}</span>`,`atrasados / vencidos<br><span class="dmini">${M(verbaAtras)} expostos</span>`)}
    ${card('v7',`<span class="gold">${v7.length}</span>`,`vencem em até 7 dias`)}
    ${card('v30',`<span class="blue">${v30.length}</span>`,`vencem em 8–30 dias`)}
    ${card('cotacao',`${emCotItens.length}`,`em cotação agora`)}
    ${card('verba',`${M(verbaTot)}`,`verba sob minha gestão`)}
  </div>
  <div class="dcard wide" style="margin-top:10px">${cotSecHead('flag',titulo,'','')}${nFiltro}
    <div style="overflow-x:auto"><table class="dtable"><thead><tr><th></th><th>Item</th><th>Obra</th><th>Próxima ação</th><th>Prazo</th><th>Status</th><th class="r">Verba</th></tr></thead><tbody>
    ${capped.map(i=>`<tr style="cursor:pointer" onclick="openModal(${Number(i.ordem)||0},${Number(i.obra_id)||1})" title="clique p/ abrir o item">
      <td>${chipNivel(i)}</td><td><b>${esc(i.nome)}</b></td><td style="white-space:nowrap"><span class="dgm" style="background:${obraCor(i.obra_id)}"></span>${esc(i.obra_nome||'')}</td>
      <td>${acaoDe(i)}</td><td style="white-space:nowrap">${D2(i.fim_cotacao)}</td><td>${esc(i.status||'Não Iniciado')}</td><td class="r"><b>${val(i)?M(val(i)):'—'}</b></td></tr>`).join('')}
    ${capped.length?'':'<tr><td colspan="7" class="dempty" style="padding:18px">nenhum item neste recorte 🎉</td></tr>'}
    </tbody></table></div>
    ${cur&&cur.list.length>60?`<div class="dmini" style="margin-top:6px">mostrando 60 de ${cur.list.length} — refine no Radar se precisar da lista completa</div>`:''}
    ${!cur&&abertos.length>15?`<div class="dmini" style="margin-top:6px">mostrando os 15 mais prioritários de ${abertos.length} abertos — clique num card acima pra detalhar um recorte</div>`:''}
  </div>`;
}
function dashCSub(k){ DASH.csub=k; DASH.cfiltro=null; DASH.cobra=''; DASH.cstatus=''; renderDash(); if(k==='sc') dashLoadFunil(); }
function SOL_ST_LBL(s){ return {pendente:'Pendente',em_cotacao:'Em cotação',cotacoes_recebidas:'Cotações recebidas',pedido_criado:'Pedido criado',cancelado:'Cancelado'}[s]||s||'—'; }
function dashCFiltro(k){ DASH.cfiltro=(DASH.cfiltro===k?null:k); renderDash(); }
function D2(s){ if(!s)return'—'; const p=String(s).split('-'); return p.length===3?p[2]+'/'+p[1]:s; }

/* ---------- 2) GERENTE DE COMPRAS ---------- */
function renderDashGerente(D){
  /* PAINEL DO GERENTE v2 — o gerente não toca item a item: ele cuida de PESSOAS e do FUNIL.
     Por isso a tela mudou de eixo. Sai o retrato do radar (que o Diretor já dá), entram
     "quem está com o quê" e "onde o processo travou" — com os mesmos cards clicáveis do comprador. */
  const base=(DASH.items&&DASH.items.length)?DASH.items:(MAT||[]);
  const val=i=>Number(i.verba||0), lvl=i=>alertLevel(i);
  const hojeD=new Date(D.hoje+'T00:00:00');
  const dDiff=f=>f?Math.round((new Date(f+'T00:00:00')-hojeD)/864e5):null;
  const diasDe=iso=>{ if(!iso)return null; const t=new Date(String(iso).slice(0,10)+'T00:00:00'); return isNaN(t)?null:Math.round((hojeD-t)/864e5); };
  const M=v=>v>=1e6?('R$ '+(v/1e6).toFixed(1).replace('.',',')+' mi'):(v>=1e3?('R$ '+Math.round(v/1e3)+' mil'):('R$ '+Math.round(v||0)));
  const byVerba=(a,b)=>val(b)-val(a);

  /* filtro de obra vale para o painel inteiro */
  const obrasTodas=[...new Set(base.map(i=>i.obra_nome).filter(Boolean))].sort();
  const fObra=DASH.cobra||'';
  const itens=fObra?base.filter(i=>i.obra_nome===fObra):base;

  const abertos=itens.filter(i=>i.status!=='Finalizado'&&i.status!=='Não se aplica');
  const atras=abertos.filter(i=>['critico','atrasado'].includes(lvl(i)));
  const semDono=abertos.filter(i=>!nrmResp(i.responsavel));

  const cots0=DASH.cots||[], sols0=DASH.sols||[];
  const cots=fObra?cots0.filter(c=>(c.obra_nome||'')===fObra):cots0;
  const sols=fObra?sols0.filter(x=>(x.nome_obra||'')===fObra):sols0;
  const carregando=(!DASH.cots||!DASH.sols);

  /* ---- EQUIPE: uma linha por comprador ---- */
  const eq={};
  abertos.forEach(i=>{ const r=nrmResp(i.responsavel); if(!r)return;
    const e=(eq[r]=eq[r]||{nome:r,abertos:0,atras:0,exposto:0,d7:0,cots:0,decidir:0,scs:0});
    e.abertos++;
    if(['critico','atrasado'].includes(lvl(i))){ e.atras++; e.exposto+=val(i); }
    const d=dDiff(i.fim_cotacao); if(d!==null&&d>=0&&d<=7) e.d7++;
  });
  cots.forEach(c=>{ const r=nrmResp(c.criado_nome); if(!r||!eq[r])return;
    if(c.status!=='finalizada') eq[r].cots++;
    if(c.status==='aguardando'&&Number(c.n_propostas)>0) eq[r].decidir++;
  });
  sols.forEach(x=>{ const r=nrmResp(x.comprador_nome); if(!r||!eq[r])return; if(x.cobertura==='vazio') eq[r].scs++; });
  const time=Object.values(eq).sort((a,b)=>b.exposto-a.exposto||b.abertos-a.abertos);

  /* ---- FUNIL: onde travou ---- */
  const cotParadas=cots.filter(c=>c.status!=='finalizada'&&!Number(c.n_propostas)).map(c=>({...c,dias:diasDe(c.created_at)})).sort((a,b)=>(b.dias||0)-(a.dias||0));
  const cotDecidir=cots.filter(c=>c.status==='aguardando'&&Number(c.n_propostas)>0).map(c=>({...c,dias:diasDe(c.created_at)})).sort((a,b)=>(b.dias||0)-(a.dias||0));
  const scParadas=sols.filter(x=>x.cobertura==='vazio'&&x.status!=='cancelado').slice().sort((a,b)=>(b.dias||0)-(a.dias||0));
  const scVelhas=scParadas.filter(x=>(x.dias||0)>=15);
  const semConvidado=cots.filter(c=>c.status!=='finalizada'&&!Number(c.n_convidados)).length;

  const FG={
    equipe:{t:'A equipe — carga de cada comprador', tipo:'equipe', list:time, ic:'groups'},
    atrasados:{t:'Atrasados / vencidos de toda a equipe', tipo:'itens', list:atras.slice().sort(byVerba), ic:'warning'},
    semdono:{t:'Itens abertos SEM responsável — precisam de dono', tipo:'itens', list:semDono.slice().sort(byVerba), ic:'person_off'},
    cotparada:{t:'Cotações sem nenhuma proposta — a concorrência não andou', tipo:'cots', list:cotParadas, ic:'hourglass_empty'},
    cotdecidir:{t:'Cotações com proposta na mesa esperando decisão', tipo:'cots', list:cotDecidir, ic:'gavel'},
    scparada:{t:'Solicitações de compra ainda sem cotação', tipo:'scs', list:scParadas, ic:'inbox'},
  };
  const ativo=FG[DASH.gfiltro]?DASH.gfiltro:'equipe';
  const cur=FG[ativo];
  const card=(key,v,l)=>`<div class="dkpi" onclick="dashGFiltro('${key}')" title="${ativo===key?'já é o recorte da tabela abaixo':'clique p/ detalhar na tabela abaixo'}" style="cursor:pointer;${ativo===key?'border:1.5px solid var(--verde);box-shadow:0 0 0 2px #e6f4ea':''}">
    <div class="v">${v}</div><div class="l">${l}</div>
    <div style="margin-top:6px;font-size:10.5px;font-weight:800;letter-spacing:.2px;color:${ativo===key?'var(--verde-d)':'var(--verde)'}">${ativo===key?'▼ NA TABELA':'DETALHAR ›'}</div></div>`;

  const chipNivel=i=>{const l=lvl(i); return l==='critico'?'<span class="dchip" style="background:var(--pend)">VENCIDO</span>':(l==='atrasado'?'<span class="dchip" style="background:#e67e22">atrasado</span>':(l==='proximo'?'<span class="dchip" style="background:var(--dourado);color:#333">próximo</span>':''));};
  const acaoDe=i=>{const st=i.status||'Não Iniciado'; if(/cota/i.test(st))return'Cobrar propostas'; if(/proposta/i.test(st))return'Aprovar fornecedor'; if(/negocia/i.test(st))return'Fechar negociação'; if(/pend/i.test(st))return'Resolver pendência'; return'Iniciar cotação';};
  const cotSt=st=>({aberta:['#8a9299','em cotação'],aguardando:['var(--dourado)','aguardando'],finalizada:['var(--ok)','fechada']}[st]||['#8a9299',st||'—']);

  let tabela='';
  if(cur.tipo==='equipe'){
    tabela = time.length ? `<table class="dtable"><thead><tr><th>Comprador</th><th class="r">Abertos</th><th class="r">Atrasados</th><th class="r">Exposto</th><th class="r">Vencem ≤7d</th><th class="r">Cotações</th><th class="r">A decidir</th><th class="r">SC sem cotação</th><th></th></tr></thead><tbody>
      ${time.map(e=>`<tr>
        <td><b>${esc(e.nome)}</b></td>
        <td class="r">${e.abertos}</td>
        <td class="r">${e.atras?`<b style="color:var(--pend)">${e.atras}</b>`:'<span class="dmini">0</span>'}</td>
        <td class="r">${e.exposto?`<b>${M(e.exposto)}</b>`:'<span class="dmini">—</span>'}</td>
        <td class="r">${e.d7||'<span class="dmini">0</span>'}</td>
        <td class="r">${carregando?'<span class="dmini">…</span>':(e.cots||'<span class="dmini">0</span>')}</td>
        <td class="r">${carregando?'<span class="dmini">…</span>':(e.decidir?`<b style="color:var(--dourado)">${e.decidir}</b>`:'<span class="dmini">0</span>')}</td>
        <td class="r">${carregando?'<span class="dmini">…</span>':(e.scs||'<span class="dmini">0</span>')}</td>
        <td class="r"><button class="btn-ghost" data-n="${esc(e.nome)}" style="padding:3px 9px;font-size:11.5px;color:var(--verde-d);font-weight:700" onclick="dashVerComprador(this.dataset.n)" title="abrir o painel pessoal deste comprador">painel ›</button></td>
      </tr>`).join('')}</tbody></table>
      ${semDono.length?`<div class="dmini" style="margin-top:7px">⚠️ ${semDono.length} item(ns) aberto(s) ainda <b>sem responsável</b> — não aparecem no painel de ninguém.</div>`:''}`
      : '<div class="dempty" style="padding:18px">Nenhum item do radar tem responsável atribuído neste recorte.</div>';
  } else if(cur.tipo==='itens'){
    const capped=cur.list.slice(0,60);
    tabela = `<table class="dtable"><thead><tr><th></th><th>Item</th><th>Obra</th><th>Responsável</th><th>Próxima ação</th><th>Prazo</th><th class="r">Verba</th></tr></thead><tbody>
      ${capped.map(i=>`<tr style="cursor:pointer" onclick="openModal(${Number(i.ordem)||0},${Number(i.obra_id)||1})" title="clique p/ abrir o item">
        <td>${chipNivel(i)}</td><td><b>${esc(i.nome)}</b></td>
        <td style="white-space:nowrap"><span class="dgm" style="background:${obraCor(i.obra_id)}"></span>${esc(i.obra_nome||'')}</td>
        <td>${nrmResp(i.responsavel)?esc(i.responsavel):'<span class="dchip" style="background:var(--pend);font-size:9.5px">sem dono</span>'}</td>
        <td>${acaoDe(i)}</td><td style="white-space:nowrap">${D2(i.fim_cotacao)}</td>
        <td class="r"><b>${val(i)?M(val(i)):'—'}</b></td></tr>`).join('')
        ||'<tr><td colspan="7" class="dempty" style="padding:18px">nada neste recorte 🎉</td></tr>'}
      </tbody></table>${cur.list.length>60?`<div class="dmini" style="margin-top:6px">mostrando 60 de ${cur.list.length}</div>`:''}`;
  } else if(cur.tipo==='cots'){
    if(carregando) tabela='<div class="dempty" style="padding:18px">Carregando as cotações…</div>';
    else { const capped=cur.list.slice(0,60);
      tabela = `<table class="dtable"><thead><tr><th>Cotação</th><th>Obra</th><th>Responsável</th><th class="r">Parada há</th><th class="r">Convidados</th><th class="r">Propostas</th><th class="r">Melhor oferta</th><th></th></tr></thead><tbody>
      ${capped.map(c=>{const st=cotSt(c.status);
        return `<tr><td><b>${esc(c.apelido||c.titulo||('#'+c.id))}</b> <span class="dmini">#${c.id}</span><br><span class="dchip" style="background:${st[0]};font-size:9.5px">${st[1]}</span></td>
        <td style="white-space:nowrap">${esc(c.obra_nome||'—')}</td>
        <td>${esc(c.criado_nome||'—')}</td>
        <td class="r">${c.dias!=null?`<b style="color:${c.dias>=21?'var(--pend)':(c.dias>=10?'#c77f1a':'inherit')}">${c.dias}d</b>`:'<span class="dmini">—</span>'}</td>
        <td class="r">${Number(c.n_convidados)?c.n_convidados:'<span class="dchip" style="background:var(--pend);font-size:9.5px">nenhum</span>'}</td>
        <td class="r">${Number(c.n_propostas)||'<span class="dmini">0</span>'}</td>
        <td class="r">${c.melhor_oferta?`<b>${M(Number(c.melhor_oferta))}</b>`:'<span class="dmini">—</span>'}</td>
        <td class="r"><button class="btn-ghost" style="padding:3px 8px;color:var(--verde-d);font-weight:700;font-size:11.5px" onclick="showView('cotacoes');setTimeout(()=>cotAbrir(${c.id}),200)">abrir ›</button></td></tr>`;}).join('')
        ||'<tr><td colspan="8" class="dempty" style="padding:18px">nenhuma cotação neste recorte 🎉</td></tr>'}
      </tbody></table>${cur.list.length>60?`<div class="dmini" style="margin-top:6px">mostrando 60 de ${cur.list.length}</div>`:''}`; }
  } else {
    if(carregando) tabela='<div class="dempty" style="padding:18px">Carregando as solicitações do TOTVS…</div>';
    else { const capped=cur.list.slice(0,60);
      tabela = `<table class="dtable"><thead><tr><th>SC</th><th>Obra</th><th>Comprador</th><th class="r">Aberta há</th><th class="r">Itens</th><th>Primeiro item</th></tr></thead><tbody>
      ${capped.map(x=>`<tr><td><b>${esc(String(x.numero||'').replace(/^0+/,''))}</b></td>
        <td style="white-space:nowrap">${esc(x.nome_obra||'—')}</td>
        <td>${x.comprador_nome?esc(x.comprador_nome):'<span class="dchip" style="background:var(--pend);font-size:9.5px">sem comprador</span>'}</td>
        <td class="r"><b style="color:${(x.dias||0)>=30?'var(--pend)':((x.dias||0)>=15?'#c77f1a':'inherit')}">${x.dias!=null?x.dias+'d':'—'}</b></td>
        <td class="r">${x.n_itens||0}</td>
        <td style="max-width:340px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="${esc(x.primeiro||'')}">${esc(x.primeiro||'')}</td></tr>`).join('')
        ||'<tr><td colspan="6" class="dempty" style="padding:18px">nenhuma solicitação parada 🎉</td></tr>'}
      </tbody></table>${cur.list.length>60?`<div class="dmini" style="margin-top:6px">mostrando 60 de ${cur.list.length}</div>`:''}
      <div class="dmini" style="margin-top:6px">A fila do TOTVS só traz SC <b>pendente</b>: o que sumiu daqui já foi atendido ou cancelado lá.</div>`; }
  }

  const g=D.gatilhos, nd=carregando?'…':'';
  const selObra=`<div style="display:flex;gap:7px;align-items:center;flex-wrap:wrap">
    <select onchange="DASH.cobra=this.value;renderDash()" style="padding:5px 9px;border:1px solid var(--line);border-radius:7px;font-size:12.5px;max-width:210px"><option value="">Todas as obras</option>${obrasTodas.map(o=>`<option ${fObra===o?'selected':''}>${esc(o)}</option>`).join('')}</select>
    ${fObra?`<button class="btn-ghost" style="padding:4px 10px;font-size:11.5px;color:var(--pend);font-weight:700" onclick="DASH.cobra='';renderDash()">✕ limpar</button>`:''}</div>`;

  return `
  <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;margin-bottom:11px">
    <div class="dmini">Painel da <b>equipe de Suprimentos</b>${fObra?` · obra <b>${esc(fObra)}</b>`:''}</div>${selObra}
  </div>
  <div class="dkpis">
    ${card('equipe',`${time.length}`,`compradores com carga<br><span class="dmini">${abertos.length} itens abertos</span>`)}
    ${card('atrasados',`<span class="red">${atras.length}</span>`,`atrasados / vencidos<br><span class="dmini">${M(atras.reduce((a,i)=>a+val(i),0))} expostos</span>`)}
    ${card('semdono',`${semDono.length?`<span class="gold">${semDono.length}</span>`:'0'}`,`itens sem responsável<br><span class="dmini">${semDono.length?'ninguém está olhando':'todos têm dono'}</span>`)}
    ${card('cotparada',`${nd||cotParadas.length}`,`cotações sem proposta<br><span class="dmini">${nd||(semConvidado?semConvidado+' sem nenhum convidado':'a concorrência travou')}</span>`)}
    ${card('cotdecidir',`${nd||`<span class="gold">${cotDecidir.length}</span>`}`,`esperando decisão<br><span class="dmini">${nd||'já tem proposta na mesa'}</span>`)}
    ${card('scparada',`${nd||scParadas.length}`,`solicitações sem cotação<br><span class="dmini">${nd||(scVelhas.length?scVelhas.length+' há 15+ dias':'nenhuma parada')}</span>`)}
  </div>

  <div class="dcard wide" style="margin-top:10px">
    ${cotSecHead(cur.ic, cur.t, cur.tipo==='equipe'?'':(cur.list.length+' registro(s)'), '')}
    <div style="overflow-x:auto">${tabela}</div>
  </div>

  <div class="dgrid" style="margin-top:10px">
    <div class="dcard">${cotSecHead('speed','Quando os prazos vencem','','')}
      <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:8px;text-align:center">
        <div><div style="font-size:20px;font-weight:800;color:var(--pend)">${g.atras.n}</div><div class="dmini">Atrasados</div><div class="dmini">${M(g.atras.v)}</div></div>
        <div><div style="font-size:20px;font-weight:800;color:#c77f1a">${g.d7.n}</div><div class="dmini">≤ 7 dias</div><div class="dmini">${M(g.d7.v)}</div></div>
        <div><div style="font-size:20px;font-weight:800;color:var(--dourado)">${g.d15.n}</div><div class="dmini">8–15 dias</div><div class="dmini">${M(g.d15.v)}</div></div>
        <div><div style="font-size:20px;font-weight:800;color:var(--verde)">${g.d30.n}</div><div class="dmini">15+ dias</div><div class="dmini">${M(g.d30.v)}</div></div>
      </div>
      <div class="dmini" style="margin-top:9px">Prazo = data em obra menos o lead. Item sem cronograma não entra nesta conta (e por isso não acende alerta).</div></div>
    <div class="dcard">${cotSecHead('apartment','Obras que mais preocupam','por valor exposto','')}
      <table class="dtable"><thead><tr><th>Obra</th><th class="r">Itens</th><th class="r">Críticos</th><th class="r">Exposto</th></tr></thead><tbody>
      ${D.porObra.slice(0,8).map(o=>`<tr><td><span class="dgm" style="background:${o.cor}"></span> ${esc(o.nome)}</td><td class="r">${o.itens}</td><td class="r">${o.criticos?`<b style="color:var(--pend)">${o.criticos}</b>`:'0'}</td><td class="r">${o.exposta?M(o.exposta):'<span class="dmini">—</span>'}</td></tr>`).join('')}
      </tbody></table></div>
  </div>`;
}
function dashGFiltro(k){ DASH.gfiltro=k; renderDash(); }
/* leva o gerente ao painel PESSOAL do comprador escolhido */
function dashVerComprador(nome){ DASH.comprador=nome; DASH.cfiltro=null; DASH.cobra=''; DASH.cstatus=''; DASH.csub='radar'; DASH.tab='comprador'; dashActive(); renderDash(); }

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
  if(t==='fornecedores') fornFiltro(); else if(t==='cartas') cartaLoad(); else if(t==='precos') precLoad(); else cotLoad(); }
function cotStChip(s){ const m={aberta:['#8a9299','Aberta'],aguardando:['var(--dourado)','Aguardando'],finalizada:['var(--ok)','Finalizada']}; const x=m[s]||['#8a9299',s]; return `<span class="dchip" style="background:${x[0]}">${x[1]}</span>`; }
function cotStLabel(s){ return ({aberta:'Aberta',aguardando:'Aguardando',finalizada:'Finalizada'})[s]||s; }
function cotFmtDT(iso){ if(!iso)return '—'; const d=new Date(iso); if(isNaN(d.getTime()))return '—'; const p=n=>('0'+n).slice(-2); return p(d.getDate())+'/'+p(d.getMonth()+1)+'/'+String(d.getFullYear()).slice(2)+' '+p(d.getHours())+':'+p(d.getMinutes()); }
function cotSort(col){ COT.sort=COT.sort||{col:'created_at',dir:-1}; if(COT.sort.col===col) COT.sort.dir=-COT.sort.dir; else COT.sort={col, dir:(col==='created_at'||col==='n_propostas'||col==='n_itens'||col==='melhor_oferta')?-1:1}; COT.page=1; cotRender(); }
// paginação da lista (30 por página) — com o histórico do sistema antigo importado são ~850 cotações
const COT_POR_PAGINA=30;
function cotPagina(p){ COT.page=Math.max(1,p|0); cotRender(); const w=document.getElementById('cotwrap'); if(w)w.scrollIntoView({block:'start',behavior:'smooth'}); }
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
  COT.filt=COT.filt||{q:'',categoria:'',status:'',criador:''}; if(COT.filt.criador==null)COT.filt.criador=''; COT.sort=COT.sort||{col:'created_at',dir:-1};
  const all=COT.list||[];
  const inboxNovoTotal=all.reduce((s,c)=>s+(+c.n_inbound_novo||0),0);   // e-mails de fornecedor ainda não processados (global)
  const cats=[...new Set(all.map(c=>c.categoria).filter(Boolean))].sort();
  const sts=[...new Set(all.map(c=>c.status).filter(Boolean))];
  // criadores distintos (id→nome) p/ o filtro por responsável
  const creators=[...new Map(all.filter(c=>c.criado_por!=null&&c.criado_por!=='').map(c=>[String(c.criado_por),{id:String(c.criado_por),nome:c.criado_nome||('#'+c.criado_por)}])).values()].sort((a,b)=>a.nome.localeCompare(b.nome));
  // filtro que não existe mais nesta obra → limpa (evita lista vazia enganosa com o dropdown mostrando "Todas")
  if(COT.filt.categoria && !cats.includes(COT.filt.categoria)) COT.filt.categoria='';
  if(COT.filt.status && !sts.includes(COT.filt.status)) COT.filt.status='';
  if(COT.filt.criador && !creators.some(x=>x.id===COT.filt.criador)) COT.filt.criador='';
  // filtros CLIENT-SIDE (a obra continua server-side no cotLoad)
  const qn=opNorm(COT.filt.q||'');
  let rows=all.filter(c=>
    (!qn || opNorm((c.apelido||'')+' '+(c.titulo||'')+' '+(c.categoria||'')+' '+(c.obra_nome||'')+' '+(c.num_solicitacao||'')+' '+(c.num_pedido||'')+' '+(c.criado_nome||'')).includes(qn)) &&
    (!COT.filt.categoria || (c.categoria||'')===COT.filt.categoria) &&
    (!COT.filt.criador || String(c.criado_por)===COT.filt.criador) &&
    (!COT.filt.status || (c.status||'')===COT.filt.status));
  // ordenação
  const sc=COT.sort.col, dir=COT.sort.dir;
  const sval=c=>({titulo:(c.titulo||'').toLowerCase(),apelido:(c.apelido||'').toLowerCase(),obra_nome:(c.obra_nome||'').toLowerCase(),categoria:(c.categoria||'').toLowerCase(),criado_nome:(c.criado_nome||'').toLowerCase(),
      n_itens:+c.n_itens||0,n_propostas:+c.n_propostas||0,melhor_oferta:+c.melhor_oferta||0,status:(c.status||''),created_at:(c.created_at||''),id:+c.id||0}[sc]);
  rows=rows.slice().sort((a,b)=>{ const x=sval(a),y=sval(b); return (x<y?-1:x>y?1:0)*dir; });
  // paginação CLIENT-SIDE — corta só o que vai para a tela; filtro/busca/ordenação acima varrem a lista INTEIRA
  const _tot=rows.length, _pgs=Math.max(1,Math.ceil(_tot/COT_POR_PAGINA));
  COT.page=Math.min(Math.max(1,COT.page||1),_pgs);
  const _ini=(COT.page-1)*COT_POR_PAGINA, pageRows=rows.slice(_ini,_ini+COT_POR_PAGINA);
  const arw=col=>COT.sort.col===col?(COT.sort.dir>0?' ▲':' ▼'):'';
  const th=(lbl,col,extra)=>`<th ${extra||''} onclick="cotSort('${col}')" style="cursor:pointer;user-select:none;white-space:nowrap">${lbl}${arw(col)}</th>`;
  let html=`<div class="panel" style="margin-bottom:10px"><div class="bar" style="gap:8px;flex-wrap:wrap;align-items:center">
     <div class="search" style="min-width:170px"><span class="material-icons" style="color:var(--muted)">search</span><input id="cotListBusca" placeholder="Buscar cotação, nº solicitação ou pedido…" value="${esc(COT.filt.q)}" oninput="COT.filt.q=this.value;COT.page=1;cotRender()"></div>
     <label class="muted" style="font-size:12px">Obra <select onchange="COT.obra=this.value;cotLoad()" style="margin-left:4px">${cotObraOpts(COT.obra)}</select></label>
     <select onchange="COT.filt.categoria=this.value;COT.page=1;cotRender()" style="font-size:12px;padding:6px"><option value="">Todas categorias</option>${cats.map(c=>`<option value="${esc(c)}" ${c===COT.filt.categoria?'selected':''}>${esc(c)}</option>`).join('')}</select>
     <select onchange="COT.filt.status=this.value;COT.page=1;cotRender()" style="font-size:12px;padding:6px"><option value="">Todos status</option>${sts.map(s=>`<option value="${esc(s)}" ${s===COT.filt.status?'selected':''}>${esc(cotStLabel(s))}</option>`).join('')}</select>
     <select onchange="COT.filt.criador=this.value;COT.page=1;cotRender()" style="font-size:12px;padding:6px" title="filtrar pelo criador (responsável) da cotação"><option value="">Todos criadores</option>${creators.map(u=>`<option value="${esc(u.id)}" ${u.id===COT.filt.criador?'selected':''}>${esc(u.nome)}</option>`).join('')}</select>
     <span class="muted" style="font-size:11.5px">${_tot?(_ini+1):0}–${_ini+pageRows.length} de ${_tot}${_tot!==all.length?` <span style="opacity:.75">(filtrado de ${all.length})</span>`:''}</span>
     ${(COT.totalServidor&&COT.totalServidor>all.length)?`<span class="dchip" style="background:var(--pend);color:#fff;font-size:10px" title="o servidor devolve no máximo ${COT.limiteServidor} cotações por vez">mostrando ${all.length} de ${COT.totalServidor}</span>`:''}
     <span style="margin-left:auto;display:flex;gap:8px;align-items:center;flex-wrap:wrap">
       ${inboxNovoTotal?`<span class="dchip" style="background:var(--pend);color:#fff" title="e-mails de fornecedores ainda não processados — clique em Buscar respostas">📨 ${inboxNovoTotal} não processada(s)</span>`:''}
       ${CAN_EDIT?`<button class="btn-ghost" style="padding:7px 12px" onclick="cotBuscarRespostasLista(this)" title="ler a caixa suprimentos@ agora (enquanto o cron de hora em hora não roda)"><span class="material-icons" style="font-size:15px;vertical-align:-3px">mark_email_unread</span> Buscar respostas</button>`:''}
       ${IS_ADMIN?`<button class="btn-ghost" style="padding:7px 12px" onclick="cotReprocessarObras()" title="preencher a obra das cotações sem obra, pela solicitação vinculada (cadastro único)"><span class="material-icons" style="font-size:15px;vertical-align:-3px">auto_fix_high</span> Reprocessar obras</button>`:''}
       ${CAN_COT?`<button class="btn-prim" style="padding:7px 14px" onclick="cotNovo()"><span class="material-icons" style="font-size:16px;vertical-align:-3px">add</span> Nova cotação</button>`:''}
     </span>
   </div></div><div class="wrap" style="overflow-x:auto"><table style="table-layout:fixed;width:100%;font-size:12px"><colgroup><col style="width:25%"><col style="width:10%"><col style="width:12%"><col style="width:9%"><col style="width:5%"><col style="width:6.5%"><col style="width:10%"><col style="width:8%"><col style="width:11%"><col style="width:8%"><col style="width:3%"></colgroup><thead><tr>${th('Cotação','titulo')}${th('Obra','obra_nome')}${th('Cat./Tipo','categoria')}<th title="Nº da solicitação de compra / nº do pedido de compra">SC/PC</th>${th('Itens','n_itens','style="text-align:center"')}${th('Prop.','n_propostas','style="text-align:center" title="propostas recebidas / convidados"')}${th('Melhor','melhor_oferta','style="text-align:right" title="melhor oferta"')}${th('Criada','created_at')}${th('Criador','criado_nome')}${th('Status','status')}<th></th></tr></thead><tbody>`;
  const _ell='overflow:hidden;text-overflow:ellipsis;white-space:nowrap';   // trunca c/ reticências (table-layout:fixed)
  for(const c of pageRows){
    const _inbox=(+c.n_inbound_novo)?` <span class="dchip" style="background:var(--pend);color:#fff;font-size:9px" title="${c.n_inbound_novo} e-mail(s) de fornecedor não processado(s)">📨${c.n_inbound_novo}</span>`:'';
    html+=`<tr style="cursor:pointer" onclick="cotOpen(${c.id})">`
      +`<td style="overflow:hidden" title="${esc(c.apelido?(c.apelido+' — '+c.titulo):c.titulo)}">${c.apelido
          ? `<div style="${_ell};font-weight:700"><span class="dchip" style="background:#eef6f0;color:var(--verde-d);font-size:9px;font-weight:700;padding:0 4px;vertical-align:1px"><span class="material-icons" style="font-size:9px;vertical-align:-1px">sell</span></span> ${esc(c.apelido)}${_inbox}</div><div class="muted" style="font-size:10.5px;${_ell}">${esc(c.titulo)}</div>`
          : `<div style="${_ell};font-weight:700">${esc(c.titulo)}${_inbox}</div>`}</td>`
      +`<td class="muted" style="${_ell}" title="${esc(c.obra_nome||'')}">${esc(c.obra_nome||'—')}</td>`
      +`<td class="muted" style="overflow:hidden;line-height:1.25" title="${esc((c.categoria||'')+(c.tipo_servico?' · '+c.tipo_servico:''))}"><div style="${_ell}">${esc(c.categoria||'—')}</div>${c.tipo_servico?`<div style="font-size:10px;${_ell}">${esc(c.tipo_servico)}</div>`:''}</td>`
      +`<td class="muted" style="font-size:11px;white-space:nowrap;line-height:1.3">${c.num_solicitacao?('SC '+esc(c.num_solicitacao)):''}${c.num_solicitacao&&c.num_pedido?'<br>':''}${c.num_pedido?('<b style="color:var(--verde-d)">PC '+esc(c.num_pedido)+'</b>'):''}${!c.num_solicitacao&&!c.num_pedido?'—':''}</td>`
      +`<td style="text-align:center">${c.n_itens}</td>`
      +`<td style="text-align:center" title="${c.n_propostas} recebida(s) de ${c.n_convidados||0} convidado(s)"><b>${c.n_propostas}</b><span class="muted">/${c.n_convidados||0}</span></td>`
      +`<td style="text-align:right;white-space:nowrap">${c.melhor_oferta?BRL(c.melhor_oferta):'—'}</td>`
      +`<td class="muted" style="font-size:11px;white-space:nowrap">${cotFmtDT(c.created_at)}</td>`
      +`<td class="muted" style="font-size:11px;${_ell}" title="${esc(c.criado_nome||'')}">${esc(c.criado_nome||'—')}</td>`
      +`<td>${cotStChip(c.status)}</td>`
      +`<td style="text-align:center"><span class="material-icons" style="color:var(--muted)">chevron_right</span></td></tr>`;
  }
  if(!rows.length) html+=`<tr><td colspan="11" class="empty">${all.length?'Nenhuma cotação casa os filtros.':'Nenhuma cotação ainda. Crie a primeira.'}</td></tr>`;
  // preserva foco/caret da busca — o innerHTML recria o input a cada tecla e mataria o foco
  const _foc=document.activeElement, _wasBusca=_foc&&_foc.id==='cotListBusca', _car=_wasBusca?_foc.selectionStart:null;
  let _pager='';
  if(_pgs>1){
    const _b=(p,lbl,dis,cur)=>`<button class="${cur?'btn-prim':'btn-ghost'}" style="padding:5px 10px;font-size:12px;min-width:34px${dis?';opacity:.35;pointer-events:none':''}" onclick="cotPagina(${p})">${lbl}</button>`;
    const _n=[], _a=Math.max(1,COT.page-2), _z=Math.min(_pgs,COT.page+2);
    if(_a>1){ _n.push(_b(1,'1',false,false)); if(_a>2)_n.push('<span class="muted" style="padding:0 2px">…</span>'); }
    for(let p=_a;p<=_z;p++) _n.push(_b(p,String(p),false,p===COT.page));
    if(_z<_pgs){ if(_z<_pgs-1)_n.push('<span class="muted" style="padding:0 2px">…</span>'); _n.push(_b(_pgs,String(_pgs),false,false)); }
    _pager=`<div class="bar" style="justify-content:center;gap:6px;margin-top:10px;flex-wrap:wrap;align-items:center">`
      +_b(COT.page-1,'‹',COT.page<=1,false)+_n.join('')+_b(COT.page+1,'›',COT.page>=_pgs,false)
      +`<span class="muted" style="font-size:11.5px;margin-left:8px">página ${COT.page} de ${_pgs}</span></div>`;
  }
  w.innerHTML=html+'</tbody></table></div>'+_pager;
  if(_wasBusca){ const ni=document.getElementById('cotListBusca'); if(ni){ ni.focus(); try{ ni.setSelectionRange(_car,_car); }catch(e){} } }
}
function cotNovo(){ COT.mode='novo'; COT.novoServico=null; COT.novoPre=null; COT.novoConvidados=[]; COT.novoItens=[{descricao:'',unidade:'',quantidade:'',observacao:''}]; cotRender(); }
// iniciar cotação A PARTIR de um item do radar: puxa o dicionário de cotação do serviço (itens EDITÁVEIS) + pré-preenche
async function cotIniciar(sid, obra, nome, grupo){
  await ensureFull();   // garante composicao_sel (a cotação puxa itens da composição do radar)
  ['radar','matriz','oportunidades','top20','dashboards','cotacoes','config','audit','updates'].forEach(x=>{ const v=document.getElementById('view-'+x); if(v)v.style.display=x==='cotacoes'?'':'none'; const n=document.getElementById('nav-'+x); if(n)n.classList.toggle('active',x==='cotacoes'); });
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
  ['radar','matriz','oportunidades','top20','dashboards','cotacoes','config','audit','updates'].forEach(x=>{ const v=document.getElementById('view-'+x); if(v)v.style.display=x==='cotacoes'?'':'none'; const n=document.getElementById('nav-'+x); if(n)n.classList.toggle('active',x==='cotacoes'); });
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
function cotObraLabel(c,podeGerir){ if(c.multi_obra){ const os=Object.values(c.obras_itens||{}); return `<span title="cotação com itens de várias obras — a obra vai por item">🏗️ multi-obra: ${esc(os.slice(0,4).join(' · '))}${os.length>4?' +'+(os.length-4):''}</span>`; }
  return `${esc(c.obra_nome||'sem obra')}${podeGerir?` <span class="material-icons" onclick="event.stopPropagation();cotObraPick(${c.id})" title="mudar a obra desta cotação" style="font-size:13px;cursor:pointer;vertical-align:-2px;color:var(--verde)">edit</span>`:''}`; }
async function cotObraPick(cid){ await obrasUniEnsure(); const c=((COT.cur||{}).cotacao)||{}; const w=document.getElementById('cotObraWrap'); if(!w)return;
  w.innerHTML=`<select onchange="cotSetObra(${cid},this.value)" style="font-size:12px;padding:2px 4px;max-width:220px">${obrasUniOpts(obrasUniFichaDoRadar(c.obra_id),'— sem obra —')}</select> <span onclick="cotOpen(${cid})" style="cursor:pointer;color:var(--muted)" title="cancelar">✕</span>`; }
async function cotSetObra(cid,fichaId){ try{ const b={acao:'set_obra',me:EU&&EU.bitrix_id,cotacao_id:cid}; if(fichaId) b.obra_ficha_id=Number(fichaId); else b.obra_id=0;
  const r=await (await fetch('actions/cotacoes.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(b)})).json();
  if(r.error){toast(r.error);return;} toast('Obra da cotação atualizada'); cotOpen(cid); }catch(e){toast('Falha: '+e.message);} }
async function cotReprocessarObras(){ if(!confirm('Tentar preencher a obra das cotações que estão sem obra, pela solicitação vinculada?'))return;
  try{ const r=await (await fetch('actions/cotacoes.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({acao:'reprocessar_obras',me:EU&&EU.bitrix_id})})).json();
  if(r.error){toast(r.error);return;} toast(`Obras resolvidas: ${r.resolvidas} de ${r.total} (${r.nao_resolvidas} sem par)`); cotLoad(); }catch(e){toast('Falha: '+e.message);} }
async function cotOpen(id){
  const w=document.getElementById('cotwrap'); w.innerHTML='<div class="dempty">Abrindo mapa…</div>';
  try{ const d=await (await fetch('actions/cotacoes.php?id='+id+'&_='+Date.now())).json();
    if(d.error){w.innerHTML='<div class="dempty">'+esc(d.error)+'</div>';return;}
    COT.cur=d; COT.mode='detalhe'; COT.editItens=false; COT.eqEdit=false; cotRenderDetalhe();
  }catch(e){w.innerHTML='<div class="dempty">Falha: '+esc(e.message)+'</div>';}
}
function cotNum(x){ return x!=null&&x!==''?Number(x).toLocaleString('pt-BR'):''; }
// --- Itens a cotar: exibição (com observação = complemento) + edição (add/editar/excluir) ---
function cotEditavel(){ const c=(COT.cur&&COT.cur.cotacao)||{}; if(IS_ADMIN) return true; if(!EU) return false;
  if(((EU.papel)||'')==='gerente') return true;   // GERENTE DE SUPRIMENTOS edita qualquer cotação (tudo no Histórico)
  if(c.criado_por!=null&&c.criado_por!==''&&String(c.criado_por)===String(EU.bitrix_id)) return true;
  return (c.colaboradores||[]).some(b=>String(b)===String(EU.bitrix_id));   // COLABORADOR compartilhado edita também (férias do criador)
}
/* ===== COMPARTILHAR cotação (colaboradores) + HISTÓRICO de alterações (23/jul/2026) ===== */
async function cotColabOpen(){
  const c=(COT.cur&&COT.cur.cotacao)||{}; if(!c.id) return;
  let usuarios=[]; try{ const d=await (await fetch('actions/usuarios.php')).json(); usuarios=(d.usuarios||[]).filter(u=>u.ativo); }catch(e){}
  if(!usuarios.length){toast('Não consegui carregar a lista de usuários');return;}
  const atuais=new Set((c.colaboradores||[]).map(String));
  document.getElementById('modal').innerHTML=`
    <div class="mhead"><button class="mclose" onclick="closeModal()">×</button>
      <div class="crumb">Cotação · ${esc(c.apelido||c.titulo||'')}</div><div class="mt">Compartilhar edição</div></div>
    <div class="tabbody">
      <div style="margin-bottom:12px;padding:9px 12px;background:#eef6f0;border:1px solid #dcebe1;border-radius:8px;font-size:12.5px">
        Criador: <b>${esc(c.criado_nome||('#'+(c.criado_por||'—')))}</b> — continua como criador. Quem você marcar abaixo ganha os <b>mesmos poderes de edição</b> (propostas, itens, status, tudo). Cada alteração fica registrada no <b>Histórico</b> com nome, data e hora.</div>
      <div class="ckgrid">${usuarios.filter(u=>String(u.bitrix_id)!==String(c.criado_por)).map(u=>`<label class="ckl"><input type="checkbox" id="cb-colab-${esc(String(u.bitrix_id))}" ${atuais.has(String(u.bitrix_id))?'checked':''}> ${esc(u.nome)} <span class="muted" style="font-size:10.5px">${esc(PAPEL_LABEL[u.papel]||u.papel||'')}</span></label>`).join('')}</div>
      <div style="display:flex;gap:8px;margin-top:14px"><button class="btn-prim" onclick="cotColabSalvar()"><span class="material-icons" style="font-size:15px;vertical-align:-3px">group_add</span> Salvar compartilhamento</button>
        <button class="btn-ghost" onclick="closeModal()">Cancelar</button></div>
    </div>`;
  COT._colabUsers=usuarios; document.getElementById('ov').classList.add('open');
}
async function cotColabSalvar(){
  const c=(COT.cur&&COT.cur.cotacao)||{};
  const sel=(COT._colabUsers||[]).filter(u=>{const e=document.getElementById('cb-colab-'+String(u.bitrix_id));return e&&e.checked;}).map(u=>String(u.bitrix_id));
  try{ const r=await (await fetch('actions/cotacoes.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({acao:'colaborador_salvar',me:EU&&EU.bitrix_id,cotacao_id:c.id,colaboradores:sel})})).json();
    if(r.error){toast(r.error);return;}
    toast(sel.length?('Compartilhada com '+sel.length+' colaborador(es)'):'Compartilhamento removido'); closeModal(); cotOpen(c.id);
  }catch(e){toast('Falha: '+e.message);}
}
/* Abre o ITEM DO RADAR que originou a cotação — POR CIMA da tela de cotação (fechou o popup, está de volta).
   Se a obra do item não está carregada no radar/matriz, busca a matriz dela e alimenta o fallback do byOrdem. */
async function cotVerItemRadar(){
  const c=(COT.cur&&COT.cur.cotacao)||{}; if(!c.servico_id){toast('Esta cotação não tem vínculo com o radar');return;}
  const ord=Number(c.servico_id), ob=Number(c.obra_id)||1;
  if(!byOrdem(ord,ob)){
    try{
      const d=await (await fetch('actions/matriz.php'+(ob!==1?('?obra='+ob+'&'):'?')+'_='+Date.now())).json();
      if(d&&d.itens){ const nome=(d.obra&&d.obra.nome)||c.obra_nome||('obra '+ob);
        d.itens.forEach(i=>{ i.obra_id=i.obra_id||ob; i.obra_nome=i.obra_nome||nome; });
        MAT=(typeof MAT!=='undefined'&&Array.isArray(MAT)&&MAT.length)?MAT.concat(d.itens.filter(i=>!MAT.some(m=>m.ordem==i.ordem&&m.obra_id==i.obra_id))):d.itens;
      }
    }catch(e){}
  }
  if(!byOrdem(ord,ob)){toast('Não consegui carregar o item do radar — abra o Radar com a obra '+esc(c.obra_nome||ob)+' selecionada');return;}
  openModal(ord,ob);   // popup por cima da cotação — fechar volta exatamente pra cá
}
async function cotHistOpen(){
  const c=(COT.cur&&COT.cur.cotacao)||{}; if(!c.id) return;
  let hs=[]; try{ const d=await (await fetch('actions/cotacoes.php?historico='+c.id+'&_='+Date.now())).json(); hs=d.historico||[]; }catch(e){}
  const fmt=s=>{ if(!s) return '—'; const d=new Date(s); return isNaN(d)?String(s):d.toLocaleDateString('pt-BR')+' '+d.toLocaleTimeString('pt-BR',{hour:'2-digit',minute:'2-digit'}); };
  document.getElementById('modal').innerHTML=`
    <div class="mhead"><button class="mclose" onclick="closeModal()">×</button>
      <div class="crumb">Cotação · ${esc(c.apelido||c.titulo||'')}</div><div class="mt">Histórico de alterações</div></div>
    <div class="tabbody">
      ${hs.length?`<div style="max-height:62vh;overflow:auto">${hs.map(h=>`<div style="display:flex;gap:10px;padding:9px 4px;border-bottom:1px solid #f0f2f1;align-items:flex-start">
        <span class="muted" style="font-size:11px;white-space:nowrap;min-width:106px">${fmt(h.created_at)}</span>
        <span style="font-size:11px;font-weight:800;color:var(--verde-d);background:#eef6f0;border-radius:6px;padding:2px 8px;white-space:nowrap">${esc(h.acao||'')}</span>
        <span style="font-size:12.5px;min-width:0"><b>${esc(h.usuario_nome||('#'+(h.bitrix_id||'')))}</b> — ${esc(h.detalhe||'')}</span></div>`).join('')}</div>`
      :'<div class="dempty">Sem alterações registradas ainda.<br><span class="dmini">O histórico passou a valer em 23/07/2026 — mudanças anteriores a isso não aparecem.</span></div>'}
    </div>`;
  document.getElementById('ov').classList.add('open');
}
function cotItensPanel(d){
  const c=d.cotacao, itens=d.itens||[], CAN_EDIT=cotEditavel(), podeGerir=CAN_EDIT;
  if(COT.editItens){
    return `<div class="panel" style="margin-bottom:10px"><div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:6px"><b style="font-size:13px">Itens a cotar — editando</b>
        <span><button class="btn-prim" style="padding:4px 11px" onclick="cotItensSalvar()">Salvar itens</button> <button class="btn-ghost" style="padding:4px 11px" onclick="COT.editItens=false;cotRenderDetalhe()">Cancelar</button></span></div>
      <div class="dmini" style="margin:4px 0 8px">Descrição = o item. Complemento = a observação/histórico (detalhe do item).</div>
      <div id="cotItEd"></div>
      <button class="btn-ghost" style="margin-top:6px" onclick="cotItAdd()"><span class="material-icons" style="font-size:15px;vertical-align:-3px">add</span> Adicionar item</button></div>`;
  }
  // BLOCOS por obra (vindos do servidor): agrupa a lista por obra + SC + CNPJ. Fallback = 1 bloco "geral".
  const blocos=(c.blocos_obra&&c.blocos_obra.length)?c.blocos_obra
    :[{chave:'geral',obra_nome:'',cidade:'',coligada:'',cnpj:'',scs:[],itens:itens.map(it=>({id:it.id,descricao:it.descricao,unidade:it.unidade,quantidade:it.quantidade,observacao:it.observacao,sc:''}))}];
  const agrupar = blocos.length>1 || (blocos[0] && (blocos[0].obra_nome||blocos[0].coligada));
  // linha em lista (não tabela): pill VERDE da quantidade à esquerda + descrição/complemento (mais limpo de ler)
  const linhas=b=>b.itens.map((it,i)=>`<div style="display:flex;align-items:center;gap:14px;padding:11px 2px;${i<b.itens.length-1?'border-bottom:1px solid #f1f3f2':''}">
      <span style="flex-shrink:0;display:inline-flex;align-items:baseline;gap:5px;background:#eef6f0;border:1px solid #dcebe1;border-radius:9px;padding:6px 11px;min-width:78px;justify-content:center"><b style="font-size:14px;color:var(--verde-d)">${cotNum(it.quantidade)}</b><span class="muted" style="font-size:9.5px;font-weight:700;letter-spacing:.3px;text-transform:uppercase">${esc(it.unidade||'')}</span></span>
      <div style="min-width:0"><b style="font-size:13px">${esc(it.descricao)}</b>${it.observacao?`<div class="muted" style="font-size:11px;margin-top:1px">${esc(it.observacao)}</div>`:''}</div></div>`).join('');
  const chip=(txt,bg,cor)=>`<span style="background:${bg};color:${cor};font-size:10px;font-weight:700;padding:1px 7px;border-radius:6px">${esc(txt)}</span>`;
  let corpo;
  if(!itens.length){ corpo='<div class="dmini">Nenhum item. Clique em “Editar itens” para adicionar.</div>'; }
  else if(agrupar){ corpo=blocos.map(b=>`<div style="margin-top:12px;border:1px solid var(--line);border-radius:10px;padding:6px 12px 4px;background:#fcfdfc">
      <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;padding:5px 0 7px;border-bottom:1px solid #eef1ef;margin-bottom:2px">
        <span style="width:30px;height:30px;border-radius:50%;background:#eef6f0;display:flex;align-items:center;justify-content:center;flex-shrink:0"><span class="material-icons" style="font-size:16px;color:var(--verde)">apartment</span></span>
        <b style="font-size:13px">${esc(b.obra_nome||b.coligada||'Sem obra')}</b>
        ${b.cidade?`<span class="muted" style="font-size:11px">📍 ${esc(b.cidade)}</span>`:''}
        ${b.cnpj?`<span class="muted" style="font-size:11px">CNPJ ${esc(b.cnpj)}</span>`:''}
        ${(b.scs||[]).map(sc=>chip('SC '+sc,'#eef4f0','var(--verde-d)')).join(' ')}
        <span class="muted" style="font-size:11px;margin-left:auto">${b.itens.length} item(ns)</span></div>
      ${linhas(b)}</div>`).join(''); }
  else { corpo=`<div style="margin-top:2px">${linhas(blocos[0])}</div>`; }
  const acoes=(itens.length?`<button class="btn-ghost" style="padding:4px 11px" onclick="cotCopiarItens()" title="copia o texto separado por obra/CNPJ p/ mandar ao fornecedor (WhatsApp)"><span class="material-icons" style="font-size:14px;vertical-align:-3px">content_copy</span> Copiar texto</button> `:'')
    +(podeGerir?`<button class="btn-ghost" style="padding:4px 11px" onclick="cotEditItens()"><span class="material-icons" style="font-size:14px;vertical-align:-3px">edit</span> Editar itens</button>`:'');
  return `<div class="panel" style="margin-bottom:12px;padding:15px 18px">${cotSecHead('list_alt','Itens a cotar','('+itens.length+')',acoes)}${corpo}</div>`;
}
// texto pré-cotação separado por obra (WhatsApp / e-mail) — SEM preço (é pedido de cotação ao fornecedor)
function cotTextoItens(){
  const d=COT.cur, c=d.cotacao;
  const blocos=(c.blocos_obra&&c.blocos_obra.length)?c.blocos_obra
    :[{obra_nome:'',cidade:'',coligada:'',cnpj:'',scs:[],itens:(d.itens||[])}];
  let t='*Solicitação de cotação — '+(c.titulo||'')+'*\n';
  blocos.forEach(b=>{
    t+='\n📍 *'+((b.obra_nome||b.coligada||c.obra_nome||c.obra_livre||'Obra'))+'*'+(b.cidade?' — '+b.cidade:'')+'\n';
    if(b.cnpj) t+='CNPJ: '+b.cnpj+'\n';
    if(b.scs&&b.scs.length) t+='Solicitação (SC): '+b.scs.join(', ')+'\n';
    (b.itens||[]).forEach(it=>{ const q=(it.quantidade!=null&&it.quantidade!=='')?(cotNum(it.quantidade)+' '+(it.unidade||'')+' — '):''; t+='• '+q+(it.descricao||'')+'\n'; });
  });
  t+='\nFavor cotar os itens acima. Obrigado!';
  return t;
}
function cotCopiarItens(){
  const t=cotTextoItens();
  if(navigator.clipboard&&navigator.clipboard.writeText){ navigator.clipboard.writeText(t).then(()=>toast('Texto copiado — cole no WhatsApp')).catch(()=>cotCopiarFallback(t)); }
  else cotCopiarFallback(t);
}
function cotCopiarFallback(t){
  let ov=document.getElementById('cotCopyOv'); if(!ov){ ov=document.createElement('div'); ov.id='cotCopyOv'; ov.style.cssText='position:fixed;inset:0;background:rgba(15,25,20,.45);z-index:9999;display:flex;align-items:center;justify-content:center;padding:24px'; document.body.appendChild(ov); }
  ov.onclick=e=>{ if(e.target===ov) ov.remove(); };
  ov.innerHTML='<div style="background:#fff;border-radius:12px;padding:16px 18px;max-width:640px;width:100%" onclick="event.stopPropagation()"><div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px"><b>Copiar texto</b><span class="material-icons" style="cursor:pointer;color:var(--muted)" onclick="document.getElementById(\'cotCopyOv\').remove()">close</span></div><div class="muted" style="font-size:12px;margin-bottom:6px">Selecione tudo (Ctrl+A) e copie (Ctrl+C).</div><textarea readonly style="width:100%;height:260px;font-size:12px;font-family:ui-monospace,Consolas,monospace"></textarea></div>';
  const ta=ov.querySelector('textarea'); ta.value=t; ta.focus(); ta.select();
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
// card de KPI do topo do detalhe: ícone em círculo + valor grande + rótulo (design limpo, fundo branco)
function cotKpi(icon,val,label){ return `<div style="flex:1 1 210px;min-width:186px;border:1px solid var(--line);border-radius:12px;padding:13px 16px;background:#fff;display:flex;align-items:center;gap:13px">
  <span style="width:44px;height:44px;border-radius:50%;background:#eef6f0;display:flex;align-items:center;justify-content:center;flex-shrink:0"><span class="material-icons" style="color:var(--verde);font-size:22px">${icon}</span></span>
  <div style="min-width:0;line-height:1.2"><div style="font-size:20px;font-weight:800;color:var(--verde-d)">${val}</div><div class="muted" style="font-size:11.5px;margin-top:2px">${label}</div></div></div>`; }
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
// FASE 2 — multi-PC por coligada: rótulo curto + cor estável por coligada, e o painel de PC por coligada
function colCurta(n){ return String(n||'').replace(/\s+(EMPREENDIMENTO|EMPREENDIMENTOS).*/i,'').replace(/\s+SPE\b.*/i,'').trim().slice(0,16)||String(n||'').slice(0,16); }
function coligadaCor(seed){ let s=String(seed||''),h=0; for(let i=0;i<s.length;i++)h=(h*31+s.charCodeAt(i))>>>0; return `hsl(${h%360},42%,40%)`; }
function cotPCColigadas(c){ const CAN_EDIT=cotEditavel();
  const cols=c.coligadas_itens||[];
  return `<div style="margin-top:10px;border-top:1px dashed var(--line);padding-top:9px">
    <div class="muted" style="font-size:11px;font-weight:700;margin-bottom:7px">SOLICITAÇÃO E PEDIDO POR COLIGADA <span style="font-weight:400">— cada coligada tem sua SC e seu nº de PC</span></div>
    <div style="display:flex;flex-direction:column;gap:7px">${cols.map((cc,i)=>`<div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap">
        <span class="dchip" style="background:${coligadaCor(cc.colidmov||cc.coligada)};color:#fff;font-size:10px" title="${esc(cc.coligada)}${cc.coligada_cod?(' · cod '+cc.coligada_cod):''}">${esc(colCurta(cc.coligada))}</span>
        <span class="muted" style="font-size:11px">${cc.n} ${cc.n>1?'itens':'item'}</span>
        ${(cc.numeros&&cc.numeros.length)?`<span class="dchip" style="background:#eef1f4;color:#5b6b7a;font-size:10px" title="Nº da solicitação de compra (SC) desta coligada">SC ${cc.numeros.map(n=>String(n).replace(/^0+/,'')||n).join(' · ')}</span>`:''}
        <input id="cotPCcol${i}" value="${esc(cc.num_pedido||'')}" placeholder="Nº do PC" style="width:130px;padding:3px 7px;font-size:12px" ${CAN_EDIT?'':'disabled'}>
        ${CAN_EDIT?`<button class="btn-ghost" style="padding:3px 9px" onclick="cotPCColSalvar(${i})"><span class="material-icons" style="font-size:13px;vertical-align:-2px">save</span> salvar</button>`:''}
        ${cc.num_pedido?`<button class="btn-ghost" style="padding:3px 9px;color:var(--verde-d)" onclick="cotPedidoVer('${esc(String(cc.num_pedido).replace(/'/g,''))}','${esc(cc.coligada_cod||'')}')"><span class="material-icons" style="font-size:13px;vertical-align:-2px">receipt_long</span> ver</button>`:''}
        <span id="cotPCcolDet${i}" style="font-size:10.5px"></span>
      </div>`).join('')}</div></div>`;
}
async function cotPCColSalvar(i){ const c=(COT.cur||{}).cotacao; const cc=(c.coligadas_itens||[])[i]; if(!cc)return; const v=((document.getElementById('cotPCcol'+i)||{}).value||'').trim();
  try{ const r=await (await fetch('actions/cotacoes.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({acao:'pedido_coligada_salvar',me:EU&&EU.bitrix_id,cotacao_id:c.id,coligada:cc.coligada,coligada_cod:cc.coligada_cod||0,colidmov:cc.colidmov||'',num_pedido:v})})).json();
    if(r.error){toast(r.error);return;} cc.num_pedido=v; toast('PC da '+colCurta(cc.coligada)+' salvo'); cotOpen(c.id);
  }catch(e){toast('Falha ao salvar');} }
// auto-detecta o PC de CADA coligada pelo colidmov (sem achatar tudo num campo só)
async function cotDetectarPedidosColigada(c){ const CAN_EDIT=cotEditavel(); const cols=c.coligadas_itens||[];
  for(let i=0;i<cols.length;i++){ const cc=cols[i]; if(!cc.colidmov)continue;
    try{ const r=await (await fetch('actions/pedidos.php?solicitacao='+encodeURIComponent(c.num_solicitacao||'')+'&coligada='+encodeURIComponent(cc.coligada||'')+'&colidmov='+encodeURIComponent(cc.colidmov)+'&me='+encodeURIComponent((EU&&EU.bitrix_id)||''))).json();
      const peds=(r&&r.pedidos)||[], det=document.getElementById('cotPCcolDet'+i);
      if(!peds.length){ if(det&&!(cc.num_pedido&&String(cc.num_pedido).trim())) det.innerHTML='<span style="color:#a15c00">SC em aberto (sem PC no TOTVS)</span>'; continue; }
      const nums=peds.map(p=>p.pedido_numero);
      if(CAN_EDIT && !(cc.num_pedido&&String(cc.num_pedido).trim())){ const joined=nums.join(', '); cc.num_pedido=joined; const inp=document.getElementById('cotPCcol'+i); if(inp)inp.value=joined;
        try{ await fetch('actions/cotacoes.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({acao:'pedido_coligada_salvar',me:EU&&EU.bitrix_id,cotacao_id:c.id,coligada:cc.coligada,coligada_cod:cc.coligada_cod||0,colidmov:cc.colidmov,num_pedido:joined})}); }catch(e){} }
      if(det) det.innerHTML='<span class="muted" style="font-weight:700">→</span> '+peds.map(p=>`<span style="color:var(--verde-d);cursor:pointer;font-weight:700" onclick="cotPedidoVer('${esc(p.pedido_numero)}','${esc(p.coligada_cod||'')}')" title="${p.n_itens} item(ns) · ver no TOTVS">PC ${esc(String(p.pedido_numero).replace(/^0+/,''))}${p.status?' · '+esc(p.status):''}</span>`).join(' · ');
    }catch(e){}
  }
}
function cotRenderDetalhe(){ const CAN_EDIT=cotEditavel();
  const d=COT.cur,c=d.cotacao,itens=d.itens||[],props=d.propostas||[],m=d.mapa||{},best=m.melhor_por_item||{},w=document.getElementById('cotwrap');
  const podeGerir=CAN_EDIT;   // admin | gerente | criador | colaborador
  const podeCompartilhar=IS_ADMIN||((EU&&EU.papel)||'')==='gerente'||(c.criado_por!=null&&c.criado_por!==''&&EU&&String(c.criado_por)===String(EU.bitrix_id));
  const dtCriada=c.created_at?cotFmtDT(c.created_at):'';
  const btn=(ic,lbl,fn,tt,cor)=>'<button class="btn-ghost" style="padding:6px 11px;font-size:12.5px'+(cor?';color:'+cor:'')+'" onclick="'+fn+'" title="'+(tt||lbl)+'"><span class="material-icons" style="font-size:15px;vertical-align:-3px">'+ic+'</span> '+lbl+'</button>';
  const sep='<span style="width:1px;height:22px;background:var(--line);margin:0 3px"></span>';
  const apelidoChip=(c.apelido||CAN_EDIT)
    ? '<span '+(CAN_EDIT?'onclick="cotApelidoEditar()"':'')+' title="'+(CAN_EDIT?'apelido curto p/ achar fácil na lista (ex.: Pregos)':'apelido')+'" style="display:inline-flex;align-items:center;gap:4px;font-size:11.5px;font-weight:700;padding:2px 9px;border-radius:7px;'+(CAN_EDIT?'cursor:pointer;':'')+(c.apelido?'background:#eef6f0;color:var(--verde-d)':'background:#fff;color:#8a9299;border:1px dashed #cfd6da')+'"><span class="material-icons" style="font-size:12px">sell</span>'+(c.apelido?esc(c.apelido):'apelido')+'</span>'
    : '';
  const origem=c.servico_id
    ? '<span onclick="cotVerItemRadar()" title="Nasceu do item do radar &ldquo;'+esc(c.servico_nome||'')+'&rdquo; — clique p/ abrir (verba, curadoria, cronograma)" style="display:inline-flex;align-items:center;gap:4px;cursor:pointer;color:var(--verde-d);font-weight:700"><span class="material-icons" style="font-size:13px">radar</span>do radar: '+esc(c.servico_nome||('item #'+c.servico_id))+'<span class="material-icons" style="font-size:12px;opacity:.6">open_in_new</span></span>'
    /* Sem vínculo: quem pode gerir consegue AMARRAR AGORA, mesmo anos depois. Era o buraco das
       cotações criadas do zero e das importadas do sistema antigo — nasciam órfãs e ficavam. */
    : (podeGerir
      ? '<span onclick="cotVincTardio()" title="esta cotação não nasceu de um item do radar — clique para vinculá-la agora" style="display:inline-flex;align-items:center;gap:4px;cursor:pointer;color:#8a9299;border-bottom:1px dashed #cfd6da"><span class="material-icons" style="font-size:13px">add_link</span>criada do zero — <b style="color:var(--verde-d)">vincular ao radar</b></span>'
      : '<span title="cotação criada do zero — sem vínculo a item do radar" style="display:inline-flex;align-items:center;gap:4px;color:#8a9299"><span class="material-icons" style="font-size:13px">edit_note</span>criada do zero</span>');
  const meta=[
    '<span style="display:inline-flex;align-items:center;gap:4px"><span class="material-icons" style="font-size:13px;color:var(--muted)">apartment</span><span id="cotObraWrap">'+cotObraLabel(c,podeGerir)+'</span></span>',
    (c.categoria||c.tipo_servico)?'<span>'+esc([c.categoria,c.tipo_servico].filter(Boolean).join(' · '))+'</span>':'',
    c.criado_nome?'<span style="display:inline-flex;align-items:center;gap:4px" title="quem criou esta cotação"><span class="material-icons" style="font-size:13px;color:var(--muted)">person</span>'+esc(c.criado_nome)+'</span>':'',
    dtCriada?'<span title="criada em">'+esc(dtCriada)+'</span>':'',
    origem
  ].filter(Boolean).join('<span style="color:#d7dbde">&bull;</span>');
  let html='<div class="panel" style="margin-bottom:12px;padding:16px 20px">'
   +'<div style="display:flex;align-items:flex-start;gap:12px;flex-wrap:wrap">'
   +'<button class="btn-ghost" onclick="cotLoad()" style="margin-top:3px;padding:5px 10px" title="voltar à lista"><span class="material-icons" style="font-size:16px;vertical-align:-3px">arrow_back</span> Voltar</button>'
   +'<div style="min-width:0;flex:1">'
   +'<div style="display:flex;align-items:center;gap:9px;flex-wrap:wrap"><b style="font-size:21px;letter-spacing:-.2px">'+esc(c.titulo)+'</b>'+cotStChip(c.status)+apelidoChip+'</div>'
   +'<div style="display:flex;align-items:center;gap:7px;flex-wrap:wrap;margin-top:5px;font-size:11.5px;color:var(--muted)">'+meta+'</div>'
   +'</div></div>'
   +'<div style="display:flex;gap:5px;flex-wrap:wrap;align-items:center;margin-top:12px;padding-top:11px;border-top:1px solid var(--line)">'
   +(CAN_EDIT?'<button class="btn-prim" style="padding:7px 13px;font-size:12.5px" onclick="cotProposta()"><span class="material-icons" style="font-size:15px;vertical-align:-3px">add</span> Cadastrar proposta</button>':'')
   +(CAN_EDIT?btn('auto_awesome','Proposta via IA','cotPropIAAbrir()','a IA lê um PDF/print de proposta, identifica o fornecedor e preenche os preços','var(--verde-d)'):'')
   +(CAN_EDIT?btn(c.status==='finalizada'?'lock_open':'flag',c.status==='finalizada'?'Reabrir':'Finalizar','cotFinalizar()',c.status==='finalizada'?'reabrir a cotação':'encerrar a concorrência'):'')
   +sep
   +(CAN_EDIT?btn('mail','E-mail','cotEmailAbrir('+c.id+')','montar o e-mail de cotação para os fornecedores convidados'):'')
   +(CAN_EDIT?btn('description','Carta','cartaGerar('+c.id+')',(c.num_solicitacao&&!c.servico_id)?'Carta de cotação (material) — SC, itens e preço a preencher':'Carta convite (serviço) — escopo e obrigações'):'')
   +btn('summarize','Mapa em 1 página','cotUmaPagina()','resumo do mapa em uma página, pronto pra imprimir/PDF')
   +sep
   +btn('history','Histórico','cotHistOpen()','quem alterou o quê nesta cotação, com data e hora')
   +(podeCompartilhar?btn('group_add','Compartilhar'+((c.colaboradores||[]).length?' ('+(c.colaboradores||[]).length+')':''),'cotColabOpen()',(c.colaboradores||[]).length?('Colaboradores: '+esc((c.colaboradores_nomes||[]).join(', '))):'dar acesso de edição a outra pessoa (ex.: férias do criador)'):'')
   +(podeGerir?'<span style="margin-left:auto">'+btn('delete','Excluir','cotExcluir()','excluir esta cotação','var(--pend)')+'</span>':'')
   +'</div>'
   +'<div style="display:flex;gap:12px;flex-wrap:wrap;padding:14px 0 2px">'
   +cotKpi('inbox', props.length+'/'+(d.convidados||[]).length, 'propostas recebidas')
   +cotKpi('emoji_events', (m.melhor_oferta?BRL(m.melhor_oferta):'—'), 'melhor fornecedor'+(m.fornecedor_destaque?' &middot; <b style="color:var(--tx)">'+esc(m.fornecedor_destaque)+'</b>':''))
   +cotKpi('savings', (c.verba?BRL(c.verba):'—')+(cotVerbaInfoBtn(c)?' '+cotVerbaInfoBtn(c):'')+(CAN_EDIT?' <span class="material-icons" onclick="cotVerbaEditar()" title="editar a verba prevista" style="font-size:13px;cursor:pointer;color:var(--verde);vertical-align:-2px">edit</span>':''), 'verba prevista')
   +'</div>'
   +'<div style="display:flex;gap:14px;align-items:center;flex-wrap:wrap;margin-top:10px;padding-top:10px;border-top:1px solid var(--line)">'
   +'<span style="font-size:10px;font-weight:800;letter-spacing:.6px;text-transform:uppercase;color:var(--muted)">Documentos</span>'
   +(!c.multi_coligada?'<div style="display:flex;align-items:center;gap:6px"><span class="muted" style="font-size:11px;font-weight:700">SC</span><input id="cotDetSC" value="'+esc(c.num_solicitacao||'')+'" placeholder="—" '+(CAN_EDIT?'':'disabled')+' style="width:96px;padding:4px 8px;font-size:12px"></div>':'')
   +'<div style="display:flex;align-items:center;gap:6px"><span class="muted" style="font-size:11px;font-weight:700">Pedido de compra</span><input id="cotDetPC" value="'+esc(c.num_pedido||'')+'" placeholder="—" '+((CAN_EDIT&&!c.multi_coligada)?'':'disabled')+' style="width:110px;padding:4px 8px;font-size:12px"></div>'
   +(c.num_pedido?'<button class="btn-ghost" style="padding:4px 11px;color:var(--verde-d)" onclick="cotPedidoVer(\''+esc(String(c.num_pedido)).replace(/'/g,'')+'\',\'\','+(Number(c.obra_id)||0)+')" title="ver o pedido no TOTVS (filtrado pela obra desta cotação): fornecedor, itens e total"><span class="material-icons" style="font-size:14px;vertical-align:-3px">receipt_long</span> Ver pedido</button>':'')
   +((CAN_EDIT&&!c.multi_coligada)?'<button class="btn-ghost" style="padding:4px 11px" onclick="cotNumerosSalvar()"><span class="material-icons" style="font-size:14px;vertical-align:-3px">save</span> Salvar nºs</button>':'')
   +(c.multi_coligada?'<span class="dchip" style="background:#eef4f0;color:var(--verde-d);font-size:10px" title="a cotação atravessa mais de uma coligada — cada uma tem seu pedido">multi-coligada: 1 PC por coligada</span>':'')
   +'</div>'+(c.multi_coligada?cotPCColigadas(c):'')+'<div id="cotPedDetect" style="margin-top:8px"></div></div>';
  html+=cotItensPanel(d);
  // ---- Concorrência (fornecedores convidados) + anexos POR fornecedor (anexar antes de cadastrar proposta) ----
  const conv=d.convidados||[], anx=d.anexos||[], meB=(EU&&EU.bitrix_id)||'';
  const anxNorm=s=>String(s||'').trim().toLowerCase().replace(/\s+/g,' ');
  const anexosDoForn=cf=>anx.filter(a=>((a.fornecedor_id&&cf.fornecedor_id&&String(a.fornecedor_id)===String(cf.fornecedor_id))||(a.fornecedor_nome&&anxNorm(a.fornecedor_nome)===anxNorm(cf.fornecedor_nome))));
  const anexoChip=a=>`<span class="dchip" style="background:#eef4f0;color:var(--verde-d);font-weight:600;display:inline-flex;align-items:center;gap:4px;max-width:190px"><span class="material-icons" style="font-size:13px">${a.url?'link':cotAnexoIcon(a.mime,a.nome)}</span><a href="${a.url?esc(a.url):('actions/cotacao_anexo.php?download='+a.id+'&me='+encodeURIComponent(meB))}" target="_blank" rel="noopener" style="color:var(--verde-d);text-decoration:none;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="${a.url?'abrir PDF (link do sistema antigo)':esc(a.nome)}">${esc(a.nome)}</a>${CAN_EDIT?` <span onclick="cotDelAnexo(${a.id})" style="cursor:pointer;color:var(--pend)" title="excluir anexo">×</span>`:''}</span>`;
  const respN=conv.filter(x=>x.respondeu||x.inbound_em).length, enviadoN=conv.filter(x=>x.enviado_em).length, concCol=cotColapsado('conc');
  html+=`<div class="panel" style="margin-bottom:12px;padding:15px 18px">${cotSecHead('groups','Concorrência','fornecedores convidados',(CAN_EDIT?'<button class="btn-ghost" style="padding:3px 10px" onclick="cotInboxBuscar()" title="ler as respostas dos fornecedores na caixa suprimentos@ (IMAP)"><span class="material-icons" style="font-size:14px;vertical-align:-3px">mark_email_unread</span> Buscar respostas</button> ':'')+'<span class="dchip" style="background:'+(conv.length&&conv.every(x=>x.respondeu)?'var(--ok)':'var(--dourado)')+'">'+conv.filter(x=>x.respondeu).length+' de '+conv.length+' responderam</span> '+cotChevron('conc'))}`;
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
        ${CAN_EDIT&&cf.respondeu&&cf.proposta_id?`<button class="btn-ghost" style="padding:2px 9px;color:var(--verde-d)" onclick="cotPropostaRevisar(${cf.proposta_id})" title="o fornecedor mandou preço novo — registra a próxima revisão sem perder a atual"><span class="material-icons" style="font-size:13px;vertical-align:-2px">published_with_changes</span> nova revisão</button><button class="btn-ghost" style="padding:2px 9px" onclick="cotProposta(${cf.proposta_id})" title="corrigir a revisão atual (não cria revisão nova)"><span class="material-icons" style="font-size:13px;vertical-align:-2px">edit</span> editar</button><button class="btn-ghost" style="padding:2px 8px;color:var(--pend)" onclick="cotExcluirProposta(${cf.proposta_id})" title="apaga SÓ a proposta (preço e revisões). O fornecedor continua na concorrência.">excluir proposta</button>`:''}
        ${CAN_EDIT?`<button class="btn-ghost" style="padding:2px 6px;color:var(--pend)" onclick="cotDesconvidar(${cf.id},'${esc(String(cf.fornecedor_nome||'')).replace(/'/g,'')}')" title="tira o fornecedor da concorrência E apaga as propostas dele do mapa">remover fornecedor</button>`:''}
      </div>
      ${cotConvContatos(cf)}
      ${ax.length?`<div style="display:flex;flex-wrap:wrap;gap:6px;margin-top:7px;padding-left:16px">${ax.map(anexoChip).join('')}</div>`:''}
    </div>`; }).join('')+'</div>';
  else html+='<div class="dmini" style="margin-top:6px">Nenhum fornecedor convidado ainda — convide abaixo.</div>';
  if(CAN_EDIT) html+=`<div style="margin-top:10px"><button class="btn-ghost" style="padding:5px 12px" onclick="cotFornPickerOpen('convite')"><span class="material-icons" style="font-size:15px;vertical-align:-3px;color:var(--verde)">group_add</span> Convidar fornecedores</button></div>`;
  html+='</div>'; }
  html+='<div id="cotInboxPanel"></div>';   // Fase 4: respostas recebidas por e-mail (preenchido async por cotInboxLoad)
  html+=cotEqualizaPanel(d);
  if(!props.length){ html+='<div class="panel" style="padding:15px 18px"><div class="empty">Nenhuma proposta ainda. Clique em "Cadastrar proposta" ou "Lançar proposta" de um convidado para montar o mapa.</div></div>'; }
  else{
    html+='<div class="panel" style="padding:15px 18px">'+cotSecHead('table_view','Mapa de cotações','comparativo · melhor preço por item','<button class="btn-ghost" style="padding:4px 10px" onclick="cotUmaPagina()" title="ver este mapa em uma página"><span class="material-icons" style="font-size:14px;vertical-align:-3px">description</span> uma página</button>')+'<div style="overflow-x:auto"><table class="mtable" style="border:none"><thead><tr><th class="svc-h" style="text-align:left">Item</th>';
    props.forEach(p=>{ const rev=p.revisao||0, hist=(p.historico||[]);
      const revChip=(rev>0||hist.length)?`<div style="margin-top:2px"><span style="background:#eef4f0;color:var(--verde-d);font-size:8.5px;font-weight:700;padding:1px 6px;border-radius:5px">rev ${rev}</span>${hist.length?` <span onclick="cotHistorico(${p.id})" style="font-size:9px;color:#5c7b8a;cursor:pointer;text-decoration:underline">histórico</span>`:''}</div>`:'';
      html+=`<th style="min-width:120px">${esc(p.fornecedor_nome)}${CAN_EDIT?` <span onclick="cotExcluirProposta(${p.id})" title="excluir esta proposta do mapa" style="cursor:pointer;color:var(--pend);font-weight:700">×</span>`:''}${p.prazo?`<div class="muted" style="font-size:9.5px;font-weight:400">${esc(p.prazo)}</div>`:''}${revChip}</th>`; });
    html+='<th style="min-width:140px;color:var(--verde-d)">🏆 Melhor Compra</th></tr></thead><tbody>';
    itens.forEach(it=>{ const b=best[it.id];
      html+=`<tr><td class="svc-c" style="text-align:left">${(c.multi_obra&&it.obra_nome)?`<span class="dchip" style="background:${obraCor(it.obra_id)};color:#fff;font-size:9px;margin-right:4px">${esc(String(it.obra_nome).slice(0,12))}</span>`:''}${(c.multi_coligada&&it.solic_coligada)?`<span class="dchip" style="background:${coligadaCor(it.solic_colidmov||it.solic_coligada)};color:#fff;font-size:9px;margin-right:4px" title="${esc(it.solic_coligada)}">${esc(colCurta(it.solic_coligada))}</span>`:''}${esc(it.descricao)}<small>${cotNum(it.quantidade)} ${esc(it.unidade||'')}${it.observacao?' · '+esc(it.observacao):''}</small></td>`;
      props.forEach(p=>{ const pi=(p.itens||{})[it.id]; const isB=b&&b.proposta_id===p.id;
        html+=`<td style="text-align:center;padding:6px 8px;${isB?'background:#e7f6ee':''}">${pi&&pi.preco_total!=null?`<b>${BRLp(pi.preco_unit)}</b>${isB?' 🏆':''}${pi.observacao?` <span class="material-icons" title="${esc(pi.observacao)}" data-obs="${esc(pi.observacao)}" data-forn="${esc(p.fornecedor_nome)}" data-item="${esc(it.descricao)}" style="font-size:13px;color:#5c7b8a;cursor:help;vertical-align:-2px" onclick="event.stopPropagation();cotObsShow(this)">info</span>`:''}<div class="muted" style="font-size:10px">${BRL(pi.preco_total)}</div>`:'<span class="muted">—</span>'}</td>`; });
      html+=`<td style="text-align:center;padding:6px 8px;background:#eafaf0">${b?`<b>${BRL(b.preco_total)}</b><div class="muted" style="font-size:10px">${esc(b.fornecedor)}</div>`:'—'}</td></tr>`;
    });
    html+='<tr style="background:#f7faf8"><td class="svc-c" style="text-align:left;font-weight:800">TOTAL</td>';
    props.forEach(p=>{ const isBS=m.fornecedor_destaque===p.fornecedor_nome; html+=`<td style="text-align:center;font-weight:800;${isBS?'color:var(--verde-d)':''}">${p.total!=null?BRL(p.total):'—'}</td>`; });
    html+=`<td style="text-align:center;font-weight:800;background:#eafaf0;color:var(--verde-d)">${m.melhor_total?BRL(m.melhor_total):'—'}</td></tr></tbody></table></div></div>`;
  }
  w.innerHTML=html;
  if(c.multi_coligada) cotDetectarPedidosColigada(c);            // multi-coligada → um PC por coligada (sem achatar)
  else if(c.num_solicitacao) cotDetectarPedido(c);              // nasceu de 1 solicitação → detecta/autopreenche o PC único
  cotInboxLoad(c.id);                            // Fase 4: carrega as respostas de e-mail desta cotação (se houver)
}
// detecta os pedidos de compra que nasceram desta solicitação (vínculo EXATO por colidmov, que embute a coligada)
/* ───────── VINCULAR AO RADAR DEPOIS DE CRIADA ─────────
   Até aqui o vínculo só existia no instante da criação: cotação nascida "do zero" — ou importada
   do sistema antigo — ficava órfã para sempre. Quem pode é a mesma regra de gerir a cotação
   (admin | gerente | criador | colaborador); o servidor confere de novo em set_servico. */
let VT = { itens:[], obra:'', escolhido:null };

function cotVincTardio(){
  const c=(COT.cur&&COT.cur.cotacao)||{};
  VT={itens:[], obra:c.obra_id?String(c.obra_id):'', escolhido:null};
  dlgAbrir('Cotações','Vincular ao radar de aquisições',
    '<div style="max-width:620px">'
   + '<div class="dmini" style="margin-bottom:10px">Amarra esta cotação a um item do radar. Serve para '
   + 'reconciliar o que foi criado do zero (ou veio do sistema antigo) com a programação da obra — '
   + 'o item passa a mostrar que tem mapa, e daqui você chega no quantitativo, na verba e no cronograma dele.</div>'
   + cotFld('Obra','<select id="vtObra" onchange="cotVincTObra()" style="width:100%">'+cotObraOpts(VT.obra)+'</select>')
   + cotFld('Item do radar','<input id="vtBusca" placeholder="Digite ao menos 2 letras do item…" '
       + (VT.obra?'':'disabled ')+'oninput="cotVincTBusca()" style="width:100%">'
       + '<div id="vtSug"></div>', 'margin-top:8px')
   + '<div id="vtEscolha" class="dmini" style="margin-top:10px"></div>'
   + '<div class="bar" style="justify-content:space-between;gap:8px;margin-top:14px;flex-wrap:wrap">'
   + (c.servico_id?'<button class="btn-ghost" style="color:#c0392b" onclick="cotVincTSalvar(0)">Remover o vínculo atual</button>':'<span></span>')
   + '<span class="bar" style="gap:8px"><button class="btn-ghost" onclick="closeModal(true)">Cancelar</button>'
   + '<button class="btn-prim" id="vtBtn" disabled style="opacity:.5" onclick="cotVincTSalvar()">Vincular</button></span>'
   + '</div></div>');
  if(VT.obra) cotVincTObra();
}

async function cotVincTObra(){
  const sel=document.getElementById('vtObra'), inp=document.getElementById('vtBusca'), s=document.getElementById('vtSug');
  VT.obra=(sel&&sel.value)||''; VT.itens=[]; VT.escolhido=null;
  if(s)s.innerHTML=''; const ev=document.getElementById('vtEscolha'); if(ev)ev.innerHTML='';
  const b=document.getElementById('vtBtn'); if(b){b.disabled=true;b.style.opacity='.5';}
  if(inp){ inp.disabled=!VT.obra; inp.value=''; }
  if(!VT.obra) return;
  if(inp) inp.placeholder='Carregando os itens da obra…';
  try{
    const url='actions/matriz.php'+(String(VT.obra)!=='1'?('?obra='+VT.obra+'&'):'?')+'_='+Date.now();
    const d=await (await fetch(url)).json();
    VT.itens=d.itens||[];
    if(inp){ inp.placeholder='Digite ao menos 2 letras do item… ('+VT.itens.length+' itens nesta obra)'; inp.focus(); }
  }catch(e){ VT.itens=[]; if(inp) inp.placeholder='Falha ao carregar os itens desta obra'; }
}

let _vtT;
function cotVincTBusca(){
  clearTimeout(_vtT);
  _vtT=setTimeout(()=>{
    const raw=(document.getElementById('vtBusca')||{}).value||'', q=opNorm(raw), s=document.getElementById('vtSug');
    if(!s)return;
    if(q.length<2){ s.innerHTML=''; return; }
    const its=(VT.itens||[]).filter(it=>opNorm((it.nome||'')+' '+(it.grupo||'')).includes(q)).slice(0,14);
    s.innerHTML=its.length
      ? '<div style="background:#fff;border:1px solid var(--line);border-radius:8px;box-shadow:0 6px 20px rgba(0,0,0,.12);max-height:250px;overflow:auto;margin-top:4px">'
        + its.map(it=>'<div onclick="cotVincTPick('+it.ordem+')" style="padding:7px 10px;cursor:pointer;font-size:12.5px;border-bottom:1px solid #f1f3f2" onmouseover="this.style.background=\'#eff7f1\'" onmouseout="this.style.background=\'\'">'
          + '<b>'+esc(it.nome)+'</b> <span class="muted" style="font-size:10.5px">· '+esc(it.grupo||'')
          + (it.cotacao?' · <span style="color:var(--dourado);font-weight:700">já tem mapa</span>':'')+'</span></div>').join('')
        + '</div>'
      : '<div class="dmini" style="padding:6px">nenhum item casa "'+esc(raw)+'" nesta obra</div>';
  },160);
}

function cotVincTPick(ordem){
  const it=(VT.itens||[]).find(x=>Number(x.ordem)===Number(ordem)); if(!it)return;
  VT.escolhido=it;
  const s=document.getElementById('vtSug'); if(s)s.innerHTML='';
  const i=document.getElementById('vtBusca'); if(i)i.value=it.nome;
  const ev=document.getElementById('vtEscolha');
  if(ev) ev.innerHTML='<span style="color:var(--verde-d)"><span class="material-icons" style="font-size:14px;vertical-align:-3px">link</span> '
    + 'Vai vincular a <b>'+esc(it.nome)+'</b>'+(it.grupo?(' <span class="muted">· '+esc(it.grupo)+'</span>'):'')
    + (it.cotacao?'<div style="color:var(--dourado);margin-top:3px">Atenção: este item já tem outro mapa de cotação vinculado. Vincular aqui não apaga o outro — o item passa a ter dois.</div>':'')
    + '</span>';
  const b=document.getElementById('vtBtn'); if(b){b.disabled=false;b.style.opacity='';}
}

async function cotVincTSalvar(remover){
  const c=(COT.cur&&COT.cur.cotacao)||{};
  const sid = (remover===0) ? 0 : (VT.escolhido?Number(VT.escolhido.ordem):0);
  if(remover!==0 && !sid){ toast('Escolha um item do radar'); return; }
  const b=document.getElementById('vtBtn'); if(b){b.disabled=true;b.textContent='Salvando…';}
  try{
    const body={acao:'set_servico',me:EU&&EU.bitrix_id,cotacao_id:c.id,servico_id:sid};
    if(sid && VT.obra) body.obra_id=Number(VT.obra);      // só é usado se a cotação ainda não tiver obra
    const r=await (await fetch('actions/cotacoes.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(body)})).json();
    if(r.error){ toast(r.error); if(b){b.disabled=false;b.textContent='Vincular';} return; }
    closeModal(true);
    toast(sid?('Vinculada ao radar: '+(r.servico_nome||'')):'Vínculo com o radar removido');
    cotOpen(c.id);
  }catch(e){ toast('Falha: '+e.message); if(b){b.disabled=false;b.textContent='Vincular';} }
}

/* ───────── VÍNCULO PELO OUTRO LADO: do ITEM DO RADAR para uma COTAÇÃO QUE JÁ EXISTE ─────────
   Mesmo vínculo do cotVincTardio, só que partindo de onde a pessoa está quando percebe que não
   precisa criar cotação nova — o mapa já existe, criado do zero ou importado do sistema antigo.
   Candidatas = cotações SEM vínculo com o radar, da mesma obra (ou ainda sem obra nenhuma:
   nesse caso o set_servico aproveita a obra do item, sem sobrescrever nada). */
let RV = { ordem:null, obra:null, item:'', cands:[], escolhida:null };

async function radVincCot(ordem, obraId, nomeItem){
  RV={ordem:ordem, obra:obraId, item:nomeItem||('item #'+ordem), cands:[], escolhida:null};
  dlgAbrir('Radar de Aquisições','Vincular a uma cotação existente',
    '<div style="max-width:660px">'
   + '<div class="dmini" style="margin-bottom:10px">Amarrando <b>'+esc(RV.item)+'</b> a um mapa que já existe. '
   + 'Aparecem só as cotações <b>sem vínculo com o radar</b> — desta obra ou ainda sem obra definida.</div>'
   + '<div class="search" style="width:100%;margin-bottom:8px"><span class="material-icons" style="color:var(--muted)">search</span>'
   + '<input id="rvQ" placeholder="Filtrar por título, apelido, nº de SC ou de pedido…" oninput="radVincRender()"></div>'
   + '<div id="rvLista"><div class="dempty">Procurando cotações…</div></div>'
   + '<div class="bar" style="justify-content:flex-end;gap:8px;margin-top:14px">'
   + '<button class="btn-ghost" onclick="closeModal(true)">Cancelar</button></div></div>');
  try{
    const d=await (await fetch('actions/cotacoes.php?_='+Date.now())).json();
    RV.cands=(d.cotacoes||[]).filter(c=>!c.servico_id
      && (String(c.obra_id||'')===String(obraId) || !c.obra_id));
  }catch(e){ RV.cands=[]; }
  radVincRender();
}

function radVincRender(){
  const box=document.getElementById('rvLista'); if(!box)return;
  const raw=((document.getElementById('rvQ')||{}).value||'').trim(), q=opNorm(raw);
  let L=RV.cands||[];
  if(q.length>=2) L=L.filter(c=>opNorm((c.apelido||'')+' '+(c.titulo||'')+' '+(c.categoria||'')+' '
      +(c.num_solicitacao||'')+' '+(c.num_pedido||'')).includes(q));
  if(!(RV.cands||[]).length){
    box.innerHTML='<div class="dempty">Nenhuma cotação sem vínculo nesta obra. '
      + 'Se a cotação que você quer já está amarrada a outro item, abra ela e troque o vínculo por lá.</div>'; return;
  }
  if(!L.length){ box.innerHTML='<div class="dempty">Nenhuma casa "'+esc(raw)+'".</div>'; return; }
  box.innerHTML='<div style="max-height:330px;overflow:auto;border:1px solid var(--line);border-radius:9px">'
    + L.slice(0,60).map(c=>{
        const org = c.num_solicitacao ? 'de uma SC' : 'criada do zero';
        return '<div onclick="radVincEscolher('+c.id+')" style="padding:9px 12px;cursor:pointer;border-bottom:1px solid #f1f3f2" '
        + 'onmouseover="this.style.background=\'#eff7f1\'" onmouseout="this.style.background=\'\'">'
        + '<div style="font-weight:700;font-size:12.5px">'+esc(c.apelido||c.titulo)+'</div>'
        + (c.apelido?'<div class="muted" style="font-size:10.5px">'+esc(c.titulo)+'</div>':'')
        + '<div class="muted" style="font-size:10.5px;margin-top:2px">'+esc(org)
        + (c.obra_nome?(' · '+esc(c.obra_nome)):' · <span style="color:var(--dourado)">sem obra — vai herdar a deste item</span>')
        + ' · '+c.n_propostas+' proposta(s)'
        + (c.melhor_oferta?(' · melhor '+BRL(c.melhor_oferta)):'')
        + (c.criado_nome?(' · '+esc(c.criado_nome)):'')
        + (c.created_at?(' · '+cotFmtDT(c.created_at)):'')+'</div></div>';
      }).join('')
    + '</div>'
    + (L.length>60?'<div class="dmini" style="margin-top:6px">mostrando 60 de '+L.length+' — refine a busca</div>':'');
}

async function radVincEscolher(cid){
  const c=(RV.cands||[]).find(x=>String(x.id)===String(cid)); if(!c)return;
  if(!confirm('Vincular a cotação "'+(c.apelido||c.titulo)+'" ao item "'+RV.item+'"?')) return;
  try{
    const r=await (await fetch('actions/cotacoes.php',{method:'POST',headers:{'Content-Type':'application/json'},
      body:JSON.stringify({acao:'set_servico',me:EU&&EU.bitrix_id,cotacao_id:c.id,servico_id:RV.ordem,obra_id:RV.obra})})).json();
    if(r.error){ toast(r.error); return; }
    closeModal(true);
    toast('Item vinculado à cotação "'+(c.apelido||c.titulo)+'"');
    /* recarrega o radar p/ a coluna Mapa e o modal do item refletirem o vínculo na hora */
    if(typeof recarregar==='function') recarregar(); else if(typeof load==='function') load();
  }catch(e){ toast('Falha: '+e.message); }
}
