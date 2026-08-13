/* Cockpit de Suprimentos — BUSCA DE NOTAS FISCAIS (tela de consulta, só leitura).
   Irmã da Busca de Pedidos: lá a pergunta é "com quem a gente COMPRA isto?", aqui é "o que
   chegou, quanto foi pago e bateu com o pedido?". A fonte é a apropriacoes_compras (APROPS/TOTVS),
   que já vem com a cadeia costurada — SOLICITAÇÃO → PEDIDO → NOTA → apropriação na tarefa da obra.
   Ver actions/notas_fiscais.php p/ as armadilhas do grão (a nota NÃO é a soma das linhas). */
let BN={data:null, carregou:false, sort:'data', dir:'desc', obras:[], obraPorLabel:{}, obraKey:''};

async function bnInit(){
  if(BN.carregou) return; BN.carregou=true;
  // a lista de obras vem das PRÓPRIAS notas — assim nada que tem nota fica de fora do filtro
  try{ const d=await (await fetch('actions/notas_fiscais.php?obras=1&me='+encodeURIComponent((EU&&EU.bitrix_id)||'')+'&_='+Date.now())).json();
    BN.obras=(d.obras||[]);
    BN.obraPorLabel={}; BN.obras.forEach(o=>{ BN.obraPorLabel[o.label.toLowerCase()]=o.chave; });
    const dl=document.getElementById('bnObraList');
    if(dl) dl.innerHTML=BN.obras.map(o=>'<option value="'+esc(o.label)+'">'+o.n+' linha(s)</option>').join('');
  }catch(e){}
}
/* casa o que foi digitado com a obra; texto vazio = todas. Sem casar exato, aceita o único que contém. */
function bnObraPick(){
  const el=document.getElementById('bnObraTxt'); if(!el) return;
  const t=(el.value||'').trim().toLowerCase();
  const x=document.getElementById('bnObraX'); if(x) x.style.display=t?'block':'none';
  if(!t){ BN.obraKey=''; el.style.color=''; return; }
  if(BN.obraPorLabel && BN.obraPorLabel[t]){ BN.obraKey=BN.obraPorLabel[t]; el.style.color=''; return; }
  const hits=(BN.obras||[]).filter(o=>o.label.toLowerCase().indexOf(t)>=0);
  if(hits.length===1){ BN.obraKey=hits[0].chave; el.style.color=''; }
  else { BN.obraKey=''; el.style.color=hits.length?'':'#c0392b'; }
}
function bnObraLimpar(){ const el=document.getElementById('bnObraTxt'); if(el){el.value='';el.style.color='';} BN.obraKey='';
  const x=document.getElementById('bnObraX'); if(x)x.style.display='none'; bnBuscar(1); }
function bnSort(campo){
  if(BN.sort===campo) BN.dir=(BN.dir==='asc'?'desc':'asc');
  else { BN.sort=campo; BN.dir=(campo==='data'||campo==='valor'||campo==='numero'||campo==='itens'||campo==='diverg')?'desc':'asc'; }
  bnBuscar(1);
}
async function bnBuscar(pagina){
  const w=document.getElementById('bnWrap'); if(!w) return;
  bnObraPick();
  // obra digitada que não casou: avisa em vez de buscar TUDO calado
  const txt=(document.getElementById('bnObraTxt')||{}).value||'';
  if(txt.trim() && !BN.obraKey){
    const hits=(BN.obras||[]).filter(o=>o.label.toLowerCase().indexOf(txt.trim().toLowerCase())>=0);
    w.innerHTML='<div class="empty">'+(hits.length
      ? 'A obra <b>'+esc(txt)+'</b> está ambígua — escolha uma:<br><span class="dmini">'+hits.slice(0,12).map(o=>esc(o.label)).join(' · ')+(hits.length>12?' …':'')+'</span>'
      : 'Nenhuma obra chamada <b>'+esc(txt)+'</b>.<br><span class="dmini">Apague o campo para ver todas.</span>')+'</div>';
    return;
  }
  const p=new URLSearchParams({q:val('bnQ')||'', obra:BN.obraKey||'', periodo:val('bnPeriodo')||'3m',
    tipo:val('bnTipo')||'', rastro:val('bnRastro')||'', diverg:val('bnDiverg')||'', entrega:val('bnEntrega')||'',
    sort:BN.sort, dir:BN.dir, pagina:(pagina||1), me:(EU&&EU.bitrix_id)||'', _:Date.now()});
  w.innerHTML='<div class="dempty">Consultando as notas no TOTVS…</div>';
  let d; try{ d=await (await fetch('actions/notas_fiscais.php?'+p.toString())).json(); }
  catch(e){ w.innerHTML='<div class="empty">Falha ao consultar o TOTVS.</div>'; return; }
  if(d.error){ w.innerHTML='<div class="empty">'+esc(d.error)+'</div>'; return; }
  BN.data=d; bnRender();
}

/* RASTREABILIDADE — o que esta tela tem de mais valioso: a nota veio de um pedido? e o pedido veio
   de uma solicitação? "Nota sem pedido" é compra que entrou por fora do processo. */
const BN_RASTRO={
  completa  :{ic:'link',        cor:'#1F6B3B', bg:'#e8f5ee', t:'Cadeia completa'},
  sem_solic :{ic:'link_off',    cor:'#a4761c', bg:'#fdf4e3', t:'Sem solicitação'},
  sem_pedido:{ic:'report',      cor:'#c0392b', bg:'#fdeaea', t:'Sem pedido'},
  outro     :{ic:'help_outline',cor:'#8a9299', bg:'#f4f5f6', t:'—'}
};
/* DIVERGÊNCIA DE PREÇO (NF × pedido). Verde não é "bom", é "sem novidade": o que pede olho é o que
   veio ACIMA do pedido. Abaixo do pedido aparece em azul porque também é informação (desconto). */
const BN_DIV={
  /* SUSPEITA vem antes de tudo: diferença acima de 70% no unitário não é negociação, é quase sempre
     unidade diferente entre o pedido e a nota (o pedido em SACO, a nota em KG). O R$ dessas notas
     fica FORA do impacto do recorte — senão um erro de lançamento vira "ganho" no número. */
  suspeita :{cor:'#7a2fa0', ic:'straighten',    t:'Confira a unidade'},
  acima5   :{cor:'#c0392b', ic:'trending_up',   t:'Acima +5%'},
  acima    :{cor:'#a4761c', ic:'trending_up',   t:'Acima'},
  sem_preco:{cor:'#8a9299', ic:'help_outline',  t:'Sem preço no PC'},
  abaixo5  :{cor:'#2b6cb0', ic:'trending_down', t:'Abaixo -5%'},
  abaixo   :{cor:'#2b6cb0', ic:'trending_down', t:'Abaixo'},
  mantido  :{cor:'#1F6B3B', ic:'check',         t:'Preço mantido'},
  outro    :{cor:'#8a9299', ic:'help_outline',  t:'—'}
};
const BN_TIPO={material:'#2b6cb0', servico:'#7a5cc4', locacao:'#a4761c', frete:'#0f766e', outro:'#8a9299'};
function bnDivCel(n){
  const d=BN_DIV[n.diverg]||BN_DIV.outro;
  const susp=n.diverg==='suspeita';
  // na nota suspeita o R$ que interessa é o do balde separado — o outro é o das linhas normais dela
  const imp=Number((susp?n.impacto_susp:n.impacto)||0);
  return '<div title="'+esc((n.diverg_label||'—')
      +(imp?((susp?' — R$ ':' — impacto de ')+BRL(imp)+(susp?' de diferença, FORA da conta do impacto até alguém conferir':' nesta nota')):''))
      +'" style="display:flex;align-items:center;gap:3px">'
    +'<span class="material-icons" style="font-size:14px;color:'+d.cor+'">'+d.ic+'</span>'
    +'<div style="min-width:0"><div style="font-size:10.5px;font-weight:800;color:'+d.cor+';line-height:1.15">'+esc(d.t)+'</div>'
    +(Math.abs(imp)>=0.01?'<div style="font-size:9.5px;color:var(--muted);line-height:1.2">'+BRL(imp)+'</div>':'')
    +'</div></div>';
}
/* Chips do recorte INTEIRO. Clicar filtra; clicar de novo limpa — mesma mecânica da Busca Pedidos. */
function bnChips(d){
  const R=d.resumo||{}, A=d.alerta||{};
  const atualR=(document.getElementById('bnRastro')||{}).value||'';
  const atualD=(document.getElementById('bnDiverg')||{}).value||'';
  const atualE=(document.getElementById('bnEntrega')||{}).value||'';
  const chip=(txt,ic,cor,bg,on,acao)=>'<span onclick="'+acao+'" title="clique p/ '+(on?'limpar o filtro':'ver só estas')+'" '
    +'style="cursor:pointer;display:inline-flex;align-items:center;gap:3px;background:'+bg+';color:'+cor
    +';border-radius:20px;padding:2px 9px;font-size:10.5px;font-weight:800;'+(on?'box-shadow:0 0 0 2px '+cor:'')+'">'
    +'<span class="material-icons" style="font-size:12px">'+ic+'</span>'+txt+'</span>';
  let h='';
  if(A.suspeita)  h+=chip(A.suspeita+' confira a unidade','straighten','#7a2fa0','#f3eafa',atualD==='suspeita','bnFiltro(\'bnDiverg\',\'suspeita\')')+' ';
  [['sem_pedido',R.sem_pedido],['sem_solic',R.sem_solic],['completa',R.completa]].forEach(([k,n])=>{
    if(!n) return; const a=BN_RASTRO[k];
    h+=chip(n+' '+a.t.toLowerCase(), a.ic, a.cor, a.bg, atualR===k, 'bnFiltro(\'bnRastro\',\''+k+'\')')+' ';
  });
  if(A.acima)     h+=chip(A.acima+' acima do pedido','trending_up','#c0392b','#fdeaea',atualD==='acima','bnFiltro(\'bnDiverg\',\'acima\')')+' ';
  if(A.abaixo)    h+=chip(A.abaixo+' abaixo do pedido','trending_down','#2b6cb0','#eaf1fb',atualD==='abaixo','bnFiltro(\'bnDiverg\',\'abaixo\')')+' ';
  if(A.parcial)   h+=chip(A.parcial+' entrega parcial','inventory','#a4761c','#fdf4e3',atualE==='parcial','bnFiltro(\'bnEntrega\',\'parcial\')')+' ';
  return h;
}
function bnFiltro(campoId,valor){
  const el=document.getElementById(campoId); if(!el) return;
  el.value=(el.value===valor?'':valor); bnBuscar(1);
}

function bnRender(){
  const w=document.getElementById('bnWrap'), d=BN.data; if(!w||!d) return;
  const ns=d.notas||[];
  if(!ns.length){ w.innerHTML='<div class="empty">Nenhuma nota encontrada com esses filtros.<br><span class="dmini">Tente ampliar o período ou usar outra palavra — a busca cobre <b>nº da nota, fornecedor, CNPJ, nº do pedido, nº da solicitação, produto e tarefa</b>.</span></div>'; return; }
  const totalPg=ns.reduce((a,n)=>a+(n.valor||0),0);
  const th=(campo,lbl,al)=>{ const on=d.sort===campo, ar=on?(d.dir==='asc'?' ▲':' ▼'):'';
    return '<th style="text-align:'+(al||'left')+';cursor:pointer;'+(on?'color:var(--verde-d)':'')+'" onclick="bnSort(\''+campo+'\')" title="clique p/ ordenar TODAS as '+d.total+' notas">'+lbl+'<span style="font-size:9px">'+ar+'</span></th>'; };
  const imp=Number(d.impacto_total||0);
  // larguras FIXAS somando 100% → cabe na tela sem rolagem lateral (texto longo trunca com … e tooltip)
  let h='<div class="panel" style="padding:0;overflow:hidden">'
   +'<div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;padding:11px 16px;border-bottom:1px solid var(--line);background:#fbfdfb">'
   +'<b style="font-size:13px">'+d.total+' nota(s)</b>'
   +'<span class="muted" style="font-size:11.5px">página '+d.pagina+' de '+d.paginas+' · '+BRL(totalPg)+' nesta página · <b>'+BRLc(d.valor_total)+'</b> no recorte</span>'
   +(Math.abs(imp)>=1?('<span class="dchip" title="'+esc('Soma do impacto de preço das notas do recorte: o quanto a nota veio acima (+) ou abaixo (−) do preço fechado no pedido. Notas com suspeita de unidade ficam fora desta conta.')+'" style="background:'+(imp>0?'#fdeaea':'#e8f5ee')+';color:'+(imp>0?'#c0392b':'#1F6B3B')+';font-size:10px">impacto '+BRL(imp)+'</span>'):'')
   /* O suspeito aparece SEPARADO e nunca somado ao impacto: numa base real ele chegou a ser 80% do
      número, e era erro de unidade — inflava "ganho" que nunca existiu. */
   +(Math.abs(Number(d.impacto_suspeito||0))>=1?('<span class="dchip" title="'+esc('Diferença das notas com suspeita de unidade (mais de '+(d.corte_suspeita||70)+'% fora do preço do pedido). NÃO entra no impacto acima — precisa de conferência.')+'" style="background:#f3eafa;color:#7a2fa0;font-size:10px">'+BRL(d.impacto_suspeito)+' a conferir</span>'):'')
   +(d.truncado?'<span class="dchip" style="background:#fff9e6;color:#6b5d1f;font-size:10px" title="a consulta bateu no teto de leitura — estreite o período ou a busca">resultado parcial</span>':'')
   +bnChips(d)
   +'<span class="muted" style="font-size:11px;margin-left:auto">ordenado por <b>'+esc(d.sort)+'</b> '+(d.dir==='asc'?'↑':'↓')+'</span>'
   +'</div>'
   +'<table class="dtable" style="width:100%;table-layout:fixed">'
   +'<colgroup><col style="width:9%"><col style="width:11%"><col style="width:13%"><col style="width:16%"><col style="width:19%"><col style="width:9%"><col style="width:8%"><col style="width:12%"><col style="width:3%"></colgroup>'
   +'<thead><tr>'+th('numero','Nota')+th('diverg','Preço NF × PC')+th('obra','Obra')+th('fornecedor','Fornecedor')
   +'<th style="text-align:left">Itens</th>'+'<th style="text-align:left">Pedido / SC</th>'+th('data','Data','center')+th('valor','Valor','right')+'<th></th></tr></thead><tbody>';
  const cut='overflow:hidden;text-overflow:ellipsis;white-space:nowrap';
  ns.forEach(n=>{
    const ra=BN_RASTRO[n.rastro]||BN_RASTRO.outro;
    const prods=(n.produtos||[]).join(' · ');
    const obras=(n.obras||[]);
    const peds=(n.pedidos||[]), sols=(n.solicitacoes||[]);
    h+='<tr>'
      +'<td style="text-align:left;'+cut+'" title="'+esc('NF '+n.curto+(n.serie?('/'+n.serie):'')+' · '+(n.tipo_label||'')+' · '+(n.coligada||'')+' · movimento '+n.colidmov)+'">'
        +'<b>'+esc(n.curto)+'</b>'+(n.serie?'<span class="muted" style="font-size:10px">/'+esc(n.serie)+'</span>':'')
        +'<div style="font-size:9px;font-weight:800;color:'+(BN_TIPO[n.tipo]||BN_TIPO.outro)+';letter-spacing:.2px;text-transform:uppercase">'+esc(n.tipo_label)+(n.contrato?' · contrato':'')+'</div>'
      +'</td>'
      +'<td style="text-align:left;padding-right:4px">'+bnDivCel(n)+'</td>'
      +'<td style="text-align:left;font-size:11px;'+cut+'" title="'+esc(obras.join(' · ')+(obras.length>1?(' — nota apropriada em '+obras.length+' obras'):''))+'">'
        +esc(n.obra||'—')+(obras.length>1?'<span class="muted" style="font-size:9px"> +'+(obras.length-1)+'</span>':'')+'</td>'
      +'<td style="text-align:left;font-size:11px;'+cut+'" title="'+esc(n.fornecedor+(n.cnpj?(' — '+n.cnpj):''))+'">'+esc(n.fornecedor||'—')+'</td>'
      +'<td style="text-align:left;font-size:11px;overflow:hidden" title="'+esc(prods)+'">'
        +'<div style="'+cut+'">'+esc(prods||(n.n_itens+' item(ns)'))+'</div>'
        +'<div style="'+cut+';color:var(--muted);font-size:9.5px">'+n.n_itens+' item(ns)'+(n.entrega==='parcial'?' · <b style="color:#a4761c">entrega parcial</b>':(n.entrega==='acima'?' · <b style="color:#c0392b">nota acima do pedido</b>':''))+'</div>'
      +'</td>'
      /* PEDIDO/SC: é o pulo do gato da tela — o nº do PC abre o pedido completo (mesmo popup da
         Busca de Pedidos), e o da SC fica visível pra achar a origem no TOTVS. */
      +'<td style="text-align:left;font-size:11px;'+cut+'" title="'+esc((peds.length?('pedido(s): '+peds.join(', ')):'sem pedido vinculado')+(sols.length?(' · solicitação(ões): '+sols.join(', ')):''))+'">'
        +(peds.length?peds.slice(0,2).map(pn=>'<a onclick="cotPedidoVer('+jsArg(pn)+','+jsArg(n.coligada_cod)+')" style="cursor:pointer;color:var(--verde-d);font-weight:700;text-decoration:underline">'+esc(pn)+'</a>').join(', ')+(peds.length>2?' +'+(peds.length-2):'')
                   :'<span style="color:'+ra.cor+';font-weight:800;font-size:10px">'+esc(ra.t)+'</span>')
        +(sols.length?'<div style="'+cut+';color:var(--muted);font-size:9.5px">SC '+esc(sols.slice(0,2).join(', '))+(sols.length>2?' +'+(sols.length-2):'')+'</div>'
                     :(peds.length?'<div style="font-size:9.5px;color:#a4761c">sem SC</div>':''))
      +'</td>'
      +'<td style="text-align:center;font-size:11px;'+cut+'" title="'+esc(n.dias_solic_nf!=null?('SC → NF em '+n.dias_solic_nf+' dias'):(n.dias_pedido_nf!=null?('PC → NF em '+n.dias_pedido_nf+' dias'):''))+'">'
        +(n.data?D(String(n.data).slice(0,10)):'—')
        +(n.dias_pedido_nf!=null?'<div style="font-size:9px;color:var(--muted)">'+(n.dias_pedido_nf<0?'-':'')+Math.abs(n.dias_pedido_nf)+'d do PC</div>':'')+'</td>'
      +'<td class="r" style="'+cut+'"><b style="font-size:11.5px">'+BRL(n.valor)+'</b>'
        +'<div style="font-size:9px;font-weight:700;color:'+bpStCor(n.status_label)+'">'+esc(n.status_label||'')+'</div></td>'
      +'<td style="text-align:center"><button class="btn-ghost" style="padding:2px 5px" onclick="bnVer('+jsArg(n.colidmov)+')" title="ver a nota inteira e a cadeia SC → PC → NF"><span class="material-icons" style="font-size:15px;vertical-align:-3px">visibility</span></button></td>'
      +'</tr>';
  });
  h+='</tbody></table>';
  if(d.paginas>1){
    const btn=(n,lbl,on)=>'<button class="btn-ghost" style="padding:4px 10px;font-size:12px;'+(on?'background:var(--verde);color:#fff;font-weight:700':'')+'" onclick="bnBuscar('+n+')">'+lbl+'</button>';
    let nav='<div style="display:flex;gap:5px;align-items:center;justify-content:center;flex-wrap:wrap;padding:11px;border-top:1px solid var(--line)">';
    if(d.pagina>1) nav+=btn(d.pagina-1,'‹ anterior');
    const ini=Math.max(1,d.pagina-2), fim=Math.min(d.paginas,ini+4);
    for(let i=ini;i<=fim;i++) nav+=btn(i,String(i),i===d.pagina);
    if(d.pagina<d.paginas) nav+=btn(d.pagina+1,'próxima ›');
    h+=nav+'</div>';
  }
  h+='</div><div class="note"><b>Preço NF × PC</b> compara o preço da nota com o preço fechado no pedido: <b style="color:#c0392b">acima</b> é o que pede olho (o valor embaixo é o impacto em R$ na nota), <b style="color:#2b6cb0">abaixo</b> é desconto, <b style="color:#1F6B3B">mantido</b> é o esperado. <b style="color:#7a2fa0">Confira a unidade</b> = o unitário da nota está mais de '+(d.corte_suspeita||70)+'% fora do pedido, o que quase nunca é negociação e quase sempre é <b>unidade diferente</b> entre o PC e a NF (pedido por saco, nota por quilo) — o R$ dessas notas fica <b>fora</b> da conta do impacto até alguém conferir. <b>Pedido / SC</b> mostra a cadeia: sem pedido = compra que entrou por fora do processo; <b>sem SC</b> = o pedido nasceu sem solicitação. Clique no <b>nº do pedido</b> pra abrir o PC completo, ou no 👁 pra ver a nota item a item com a comparação de preço e quantidade. A <b>data</b> traz embaixo quantos dias separaram o pedido da nota. Consulta ao TOTVS (somente leitura) — a base é a apropriação, então uma nota rateada entre tarefas aparece uma vez só, com o valor cheio.</div>';
  w.innerHTML=h;
}

/* ---- A NOTA INTEIRA: cabeçalho, a régua SC → PC → NF e o item a item com preço/quantidade ---- */
async function bnVer(colidmov){
  let ov=document.getElementById('nfOverlay');
  if(!ov){ ov=document.createElement('div'); ov.id='nfOverlay';
    ov.style.cssText='position:fixed;inset:0;background:rgba(15,25,20,.42);z-index:9999;display:flex;align-items:center;justify-content:center;padding:20px';
    document.body.appendChild(ov); }
  ov.onclick=()=>ov.remove();
  const shell=b=>'<div style="background:#fff;border-radius:14px;padding:18px;max-width:900px;width:100%;max-height:86vh;overflow:auto;box-shadow:0 12px 44px rgba(0,0,0,.22)" onclick="event.stopPropagation()">'+b+'</div>';
  const close='<span class="material-icons" onclick="document.getElementById(\'nfOverlay\').remove()" style="cursor:pointer;color:var(--muted)">close</span>';
  ov.innerHTML=shell('<div class="dempty">Buscando a nota no TOTVS…</div>');
  let r; try{ r=await (await fetch('actions/notas_fiscais.php?nf='+encodeURIComponent(colidmov)+'&me='+encodeURIComponent((EU&&EU.bitrix_id)||''))).json(); }
  catch(e){ ov.innerHTML=shell('<div class="empty">Falha ao buscar a nota.</div>'); return; }
  if(r.error){ ov.innerHTML=shell('<div style="display:flex;justify-content:space-between;align-items:center"><b>Nota</b>'+close+'</div><div class="empty" style="margin-top:10px">'+esc(r.error)+'</div>'); return; }
  const n=r.nota, itens=r.itens||[], peds=r.pedidos||[], sols=r.solicitacoes||[];
  const ra=BN_RASTRO[n.rastro]||BN_RASTRO.outro;

  /* RÉGUA DA CADEIA — o "imagina que massa" desta tela: em uma linha, quando a obra pediu, quando
     virou pedido e quando a nota chegou, com os dias de cada perna. */
  const eloD=(t,num,data,cor)=>'<div style="flex:1;min-width:120px;border:1px solid var(--line);border-left:3px solid '+cor+';border-radius:9px;padding:7px 11px;background:#fbfdfb">'
      +'<div style="font-size:9.5px;font-weight:800;color:var(--muted);letter-spacing:.4px;text-transform:uppercase">'+t+'</div>'
      +'<div style="font-size:13px;font-weight:800;color:'+cor+'">'+num+'</div>'
      +'<div style="font-size:10.5px;color:var(--muted)">'+(data?D(String(data).slice(0,10)):'—')+'</div></div>';
  const seta=dias=>'<div style="flex:0 0 auto;display:flex;flex-direction:column;align-items:center;justify-content:center;color:var(--muted)">'
      +'<span class="material-icons" style="font-size:17px">arrow_forward</span>'
      +(dias!=null?'<span style="font-size:9.5px;font-weight:700">'+dias+'d</span>':'')+'</div>';
  let regua='<div style="display:flex;gap:7px;align-items:stretch;flex-wrap:wrap;margin:10px 0 12px">';
  if(sols.length) regua+=eloD('Solicitação','#'+esc(sols.map(s=>s.curto).join(', ')),sols[0].data,'#7a5cc4')+seta(n.dias_solic_pedido);
  if(peds.length) regua+=eloD('Pedido de compra','#'+esc(peds.map(p=>p.curto).join(', ')),peds[0].data,'#2b6cb0')+seta(n.dias_pedido_nf);
  regua+=eloD('Nota fiscal','#'+esc(n.curto)+(n.serie?'/'+esc(n.serie):''),n.data,'#1F6B3B');
  if(!peds.length) regua+='<div style="flex:1;min-width:150px;display:flex;align-items:center;gap:6px;color:'+ra.cor+';font-size:11.5px;font-weight:700;background:'+ra.bg+';border-radius:9px;padding:7px 11px">'
      +'<span class="material-icons" style="font-size:16px">'+ra.ic+'</span>'+esc(n.rastro_label)+'</div>';
  regua+='</div>';

  const linha=it=>{
    const dv=BN_DIV[it.diverg]||BN_DIV.outro, difs=(it.dif_pct!=null&&Math.abs(it.dif_pct)>=0.01);
    return '<tr'+(it.rateada?' style="background:#fbfbfd"':'')+'>'
      +'<td class="svc-c" style="text-align:left;font-size:11.5px">'+esc(it.produto||'')
        +'<small>'+esc(it.produto_cod||'')+(it.rateada?' · rateio da mesma linha da nota':'')+'</small></td>'
      +'<td style="text-align:left;font-size:10.5px;color:#4a5560">'+esc(it.tarefa||'—')
        +(it.obra&&it.obra!==n_obraPrincipal(itens)?'<div style="color:var(--muted)">'+esc(it.obra)+'</div>':'')+'</td>'
      +'<td style="text-align:right;font-size:11px">'+cotNum(it.nf_qtd)+' '+esc(it.unidade||'')
        +(it.pedido_qtd!=null&&Math.abs(it.pedido_qtd-it.nf_qtd)>0.001?'<div style="font-size:9.5px;color:#a4761c">PC: '+cotNum(it.pedido_qtd)+'</div>':'')+'</td>'
      +'<td style="text-align:right;font-size:11px">'+BRLp(it.nf_preco)
        +(it.pedido_preco!=null&&difs?'<div style="font-size:9.5px;color:var(--muted)">PC: '+BRLp(it.pedido_preco)+'</div>':'')+'</td>'
      /* O R$ do impacto é do ITEM inteiro e vem repetido em cada pedaço do rateio — mostrar nos dois
         faria parecer o dobro. O % fica (é taxa, não soma). */
      +'<td style="text-align:right;font-size:11px;color:'+dv.cor+';font-weight:'+(difs?'800':'400')+'">'
        +(it.dif_pct!=null?(Number(it.dif_pct)>0?'+':'')+Number(it.dif_pct).toLocaleString('pt-BR',{maximumFractionDigits:2})+'%':'—')
        +(!it.rateada&&it.impacto&&Math.abs(it.impacto)>=0.01?'<div style="font-size:9.5px">'+BRL(it.impacto)+'</div>':'')+'</td>'
      +'<td style="text-align:right"><b>'+BRL(it.rateada?it.valor_apropriado:it.nf_valor)+'</b>'
        +(it.rateada?'<div style="font-size:9px;color:var(--muted)">parte apropriada</div>':'')+'</td></tr>';
  };
  ov.innerHTML=shell('<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:4px">'
    +'<b style="font-size:15px"><span class="material-icons" style="font-size:17px;vertical-align:-3px;color:var(--verde-d)">description</span> Nota fiscal '+esc(n.curto)+(n.serie?'/'+esc(n.serie):'')+'</b>'+close+'</div>'
    +'<div class="muted" style="font-size:11.5px;margin-bottom:2px"><b>'+esc(n.fornecedor||'')+'</b>'+(n.cnpj?' · '+esc(n.cnpj):'')
      +' · '+esc(n.tipo_totvs||n.tipo_label)+(n.status_label?' · '+esc(n.status_label):'')+'</div>'
    +'<div class="muted" style="font-size:11.5px;margin-bottom:2px">'+esc(n.coligada||'')+' · movimento '+esc(n.colidmov)
      +' · competência '+esc(n.competencia)+(n.contrato?' · <b>medição de contrato</b>':'')+'</div>'
    +regua
    +'<div style="overflow-x:auto"><table class="mtable" style="border:none;table-layout:fixed;width:100%">'
    +'<thead><tr><th class="svc-h" style="text-align:left;width:30%">Item</th><th style="text-align:left;width:22%">Apropriado em (tarefa)</th>'
    +'<th style="text-align:right;width:11%">Qtde</th><th style="text-align:right;width:13%">Preço NF</th>'
    +'<th style="text-align:right;width:11%">× pedido</th><th style="text-align:right;width:13%">Total</th></tr></thead><tbody>'
    +itens.map(linha).join('')
    +'<tr style="background:#f7faf8"><td class="svc-c" style="text-align:left;font-weight:800" colspan="5">TOTAL DA NOTA ('+n.n_itens+' item(ns))</td>'
    +'<td style="text-align:right;font-weight:800;color:var(--verde-d)">'+BRL(n.valor)+'</td></tr>'
    +'</tbody></table></div>'
    +'<div class="dmini" style="margin-top:8px">Dados do TOTVS (somente leitura), pela apropriação: cada linha é o pedaço da nota lançado numa tarefa da obra — por isso um mesmo item pode aparecer repartido (linhas em cinza), e o <b>total da nota</b> conta cada item uma vez só. <b>× pedido</b> é a diferença entre o preço da nota e o preço fechado no pedido.'
    +(peds.length?' Pedido: '+peds.map(p=>'<a onclick="cotPedidoVer('+jsArg(p.numero)+','+jsArg(n.coligada_cod)+')" style="cursor:pointer;color:var(--verde-d);font-weight:700;text-decoration:underline">'+esc(p.curto)+'</a> ('+esc(p.status_label)+(p.tipo?' · '+esc(p.tipo):'')+')').join(' · '):'')
    +'</div>');
}
/* obra dominante da nota — só p/ não repetir o nome da obra em toda linha do detalhe */
function n_obraPrincipal(itens){ return (itens && itens.length) ? (itens[0].obra||'') : ''; }
