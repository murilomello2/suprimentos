/* Cockpit de Suprimentos — parte 7: TELAS DE CONSULTA DA OBRA.
   Público: engenheiros e coordenadores de obra (papel 'obra'). São telas MAGRAS de leitura —
   respondem "meu material vai chegar a tempo?", "minha SC virou cotação?", "em que pé está a compra?".
   NÃO têm nenhum botão de ação: quem opera, opera nas telas de suprimentos.
   Toda a leitura vem de actions/obra_consulta.php, que reusa as funções do api.php — a régua de
   alerta, cobertura e melhor-preço é a MESMA da API externa, não uma segunda conta. */

const OV = { radar:{d:null, filt:{obra:'',status:'',alerta:'',grupo:'',resp:'',cot:'',q:''}, page:1, sort:{col:'data_em_obra',dir:1}, obras:null} };
const OV_POR_PAGINA = 30;

function ovMe(){ return encodeURIComponent((typeof EU!=='undefined' && EU && EU.bitrix_id) || ''); }
/* data ISO -> dd/mm/aa (as telas da obra não precisam de hora) */
function ovData(s){ if(!s) return '—'; const d=new Date(String(s).length<=10?(s+'T00:00:00'):s);
  if(isNaN(d.getTime())) return '—'; const p=n=>('0'+n).slice(-2);
  return p(d.getDate())+'/'+p(d.getMonth()+1)+'/'+String(d.getFullYear()).slice(2); }
/* dias entre hoje e uma data (negativo = já passou) */
function ovDias(s){ if(!s) return null; const d=new Date(String(s).length<=10?(s+'T00:00:00'):s);
  if(isNaN(d.getTime())) return null; const h=new Date(); h.setHours(0,0,0,0);
  return Math.round((d-h)/86400000); }

const OV_ALERTA = {
  critico:   ['#c0392b', 'Prazo estourado',  'a data de começar a cotar já passou — o material corre risco de atrasar'],
  atrasado:  ['#e67e22', 'Atrasado',         'deveria ter começado a cotação'],
  proximo:   ['var(--dourado)', 'Começar agora', 'entrou na janela de iniciar a cotação'],
  ok:        ['var(--ok)', 'No prazo',       'dentro do prazo previsto'],
  finalizado:['#8a9299', 'Concluído',        'item já finalizado'],
};
function ovChipAlerta(a){
  const x = OV_ALERTA[a] || ['#8a9299', a||'—', ''];
  return '<span class="dchip" style="background:'+x[0]+'" title="'+esc(x[2])+'">'+esc(x[1])+'</span>';
}

/* ─────────────────────────── RADAR (o que vem por aí) ─────────────────────────── */

function ovRadarInit(){ if(!OV.radar.d) ovRadarLoad(); else ovRadarRender(); }

async function ovRadarLoad(recarregar){
  const w=document.getElementById('ovRadarWrap'); if(!w) return;
  w.innerHTML='<div class="dempty">Lendo o radar de aquisições…</div>';
  const f=OV.radar.filt;
  /* por_pagina=0 = a lista INTEIRA. Busca, filtros e ordenação abaixo são client-side e têm que
     varrer tudo; com fatia do servidor o contador diria "de 500" e o card, 2.458. */
  const p=new URLSearchParams({tela:'radar', me:decodeURIComponent(ovMe()), por_pagina:'0'});
  if(f.obra) p.set('obra_id', f.obra);
  if(recarregar) p.set('recarregar','1');
  try{
    const d=await (await fetch('actions/obra_consulta.php?'+p.toString())).json();
    /* o api.php (reusado como biblioteca) responde erro na chave `erro`; o obra_consulta.php usa
       `error`. Tratar só uma deixava a tela presa em "Carregando…" sem dizer o que houve. */
    if(d.error||d.erro){ w.innerHTML='<div class="dempty">'+esc(d.error||d.erro)+'</div>'; return; }
    OV.radar.d=d;
    /* A 1ª varredura com cache frio estoura o orçamento de tempo do servidor e volta INCOMPLETA.
       Sem isto a pessoa veria menos itens do que existem sem saber. A 2ª chamada é rápida. */
    if(d.parcial){ toast('Lista incompleta ('+(d.obras_nao_processadas||[]).length+' obra(s) faltando) — buscando o resto…');
                   setTimeout(()=>ovRadarLoad(), 400); }
    ovRadarRender();
  }catch(e){ w.innerHTML='<div class="dempty">Falha ao carregar: '+esc(e.message)+'</div>'; }
}

async function ovObrasCarrega(){
  if(OV.radar.obras) return OV.radar.obras;
  try{ const d=await (await fetch('actions/obra_consulta.php?tela=obras&me='+ovMe())).json();
       OV.radar.obras=(d.dados||[]).filter(o=>o.no_radar); }catch(e){ OV.radar.obras=[]; }
  return OV.radar.obras;
}

function ovRadarSet(campo,valor){ OV.radar.filt[campo]=valor; OV.radar.page=1;
  if(campo==='obra') ovRadarLoad(); else ovRadarRender(); }
function ovRadarSort(col){ const s=OV.radar.sort;
  if(s.col===col) s.dir=-s.dir; else { s.col=col; s.dir=(col==='data_em_obra'||col==='inicio_cotacao')?1:1; }
  OV.radar.page=1; ovRadarRender(); }
function ovRadarPagina(p){ OV.radar.page=Math.max(1,p|0); ovRadarRender();
  const w=document.getElementById('ovRadarWrap'); if(w) w.scrollIntoView({block:'start',behavior:'smooth'}); }
function ovRadarLimpar(){ OV.radar.filt={obra:OV.radar.filt.obra,status:'',alerta:'',grupo:'',resp:'',cot:'',q:''};
  OV.radar.page=1; ovRadarRender(); }

function ovRadarRender(){
  const w=document.getElementById('ovRadarWrap'); if(!w) return;
  const d=OV.radar.d; if(!d) return;
  const f=OV.radar.filt, todos=d.dados||[];

  // filtros CLIENT-SIDE sobre a lista inteira (a obra é server-side, no carregamento)
  const qn=(typeof opNorm==='function')?opNorm(f.q||''):(f.q||'').toLowerCase();
  let rows=todos.filter(l=>{
    if(f.status && l.status!==f.status) return false;
    if(f.alerta && l.alerta!==f.alerta) return false;
    if(f.grupo  && l.grupo!==f.grupo) return false;
    if(f.resp   && l.responsavel!==f.resp) return false;
    if(f.cot==='1' && !l.cotacao) return false;
    if(f.cot==='0' && l.cotacao)  return false;
    if(qn){ const alvo=(typeof opNorm==='function')?opNorm((l.item||'')+' '+(l.grupo||'')+' '+(l.fornecedor||'')):
              ((l.item||'')+' '+(l.grupo||'')+' '+(l.fornecedor||'')).toLowerCase();
            if(alvo.indexOf(qn)<0) return false; }
    return true;
  });

  const sc=OV.radar.sort.col, dir=OV.radar.sort.dir;
  const ORD_ALERTA={critico:0,atrasado:1,proximo:2,ok:3,finalizado:4};
  const val=l=>({item:(l.item||'').toLowerCase(), obra:(l.obra||'').toLowerCase(), grupo:(l.grupo||'').toLowerCase(),
    status:(l.status||''), alerta:(ORD_ALERTA[l.alerta]!==undefined?ORD_ALERTA[l.alerta]:9),
    responsavel:(l.responsavel||'zzz').toLowerCase(),
    /* sem data vai para o FIM em qualquer direção: "não sei quando" não é "chega logo" */
    data_em_obra:(l.data_em_obra||'9999-12-31'), inicio_cotacao:(l.inicio_cotacao||'9999-12-31'),
    fim_cotacao:(l.fim_cotacao||'9999-12-31'),
    verba:(l.verba_definida!=null?+l.verba_definida:(l.verba_estimada!=null?+l.verba_estimada:-1))}[sc]);
  rows=rows.slice().sort((a,b)=>{ const x=val(a),y=val(b); return (x<y?-1:x>y?1:0)*dir; });

  const tot=rows.length, pgs=Math.max(1,Math.ceil(tot/OV_POR_PAGINA));
  OV.radar.page=Math.min(Math.max(1,OV.radar.page),pgs);
  const ini=(OV.radar.page-1)*OV_POR_PAGINA, pageRows=rows.slice(ini,ini+OV_POR_PAGINA);
  const c=d.contadores||{};

  const card=(cor,n,tit,sub,onclick)=>'<div class="panel" style="flex:1;min-width:150px;border-left:4px solid '+cor+';cursor:'
    +(onclick?'pointer':'default')+'" '+(onclick?('onclick="'+onclick+'"'):'')+'>'
    +'<div style="font-size:23px;font-weight:800;line-height:1.1">'+n+'</div>'
    +'<div style="font-weight:700;font-size:12.5px">'+tit+'</div>'
    +'<div class="dmini" style="color:var(--muted)">'+sub+'</div></div>';

  let h=cotSecHead('radar','Status - Curva A e B',
    'itens de compra da obra: quando a cotação precisa começar e quando o material é necessário',
    '<button class="btn-ghost" style="padding:5px 12px" onclick="ovRadarLoad(1)" title="refaz a leitura ignorando o cache">'
    +'<span class="material-icons" style="font-size:15px;vertical-align:-3px">refresh</span> Atualizar</button>');

  h+='<div class="bar" style="gap:10px;flex-wrap:wrap;margin-bottom:10px">'
   + card('var(--verde)', c.total||0, 'Itens no radar', 'total da(s) obra(s) selecionada(s)')
   + card('#c0392b', c.atrasados||0, 'Atrasados', 'a cotação já deveria ter começado', "ovRadarSet('alerta','critico')")
   + card('var(--dourado)', c.agora||0, 'Começar agora', 'entraram na janela de cotar', "ovRadarSet('alerta','proximo')")
   + card('var(--ok)', c.com_cotacao||0, 'Já em cotação', 'têm mapa de cotação aberto', "ovRadarSet('cot','1')")
   + card('#8a9299', c.sem_data||0, 'Sem data em obra', 'não dá para calcular o prazo', "ovRadarSet('alerta','')")
   + '</div>';

  const opts=(lista,sel)=>lista.map(x=>'<option value="'+esc(x)+'"'+(String(sel)===String(x)?' selected':'')+'>'+esc(x)+'</option>').join('');
  const obrasSel=(OV.radar.obras||[]).map(o=>'<option value="'+o.obra_id+'"'+(String(f.obra)===String(o.obra_id)?' selected':'')+'>'+esc(o.obra)+'</option>').join('');

  h+='<div class="panel" style="margin-bottom:10px"><div class="bar" style="gap:8px;flex-wrap:wrap;align-items:center">'
   + '<div class="search" style="min-width:210px"><span class="material-icons" style="color:var(--muted)">search</span>'
   + '<input id="ovRadarQ" placeholder="Buscar item, grupo ou fornecedor…" value="'+esc(f.q)+'" '
   + 'oninput="OV.radar.filt.q=this.value;OV.radar.page=1;ovRadarRender()"></div>'
   + '<label class="muted" style="font-size:12px">Obra <select onchange="ovRadarSet(\'obra\',this.value)" style="margin-left:4px">'
   + '<option value="">Todas as obras</option>'+obrasSel+'</select></label>'
   + '<select onchange="ovRadarSet(\'alerta\',this.value)" style="font-size:12px;padding:6px"><option value="">Qualquer prazo</option>'
   + Object.keys(OV_ALERTA).map(k=>'<option value="'+k+'"'+(f.alerta===k?' selected':'')+'>'+OV_ALERTA[k][1]+'</option>').join('')+'</select>'
   + '<select onchange="ovRadarSet(\'status\',this.value)" style="font-size:12px;padding:6px"><option value="">Qualquer status</option>'
   + opts([...new Set(todos.map(l=>l.status).filter(Boolean))].sort(), f.status)+'</select>'
   + '<select onchange="ovRadarSet(\'grupo\',this.value)" style="font-size:12px;padding:6px"><option value="">Todos os grupos</option>'
   + opts(d.grupos||[], f.grupo)+'</select>'
   + '<select onchange="ovRadarSet(\'resp\',this.value)" style="font-size:12px;padding:6px" title="comprador responsável pelo item">'
   + '<option value="">Todos os compradores</option>'+opts(d.responsaveis||[], f.resp)+'</select>'
   + '<select onchange="ovRadarSet(\'cot\',this.value)" style="font-size:12px;padding:6px">'
   + '<option value="">Com ou sem cotação</option>'
   + '<option value="1"'+(f.cot==='1'?' selected':'')+'>Só com cotação</option>'
   + '<option value="0"'+(f.cot==='0'?' selected':'')+'>Só sem cotação</option></select>'
   + '<button class="btn-ghost" style="padding:6px 11px;font-size:12px" onclick="ovRadarLimpar()">Limpar filtros</button>'
   + '<button class="btn-ghost" style="padding:6px 11px;font-size:12px" onclick="ovColsAbrir()" title="escolher quais colunas aparecem">'
   + '<span class="material-icons" style="font-size:14px;vertical-align:-3px">view_column</span> Colunas</button>'
   + '<span class="muted" style="font-size:11.5px;margin-left:auto">'+(tot?(ini+1):0)+'–'+(ini+pageRows.length)+' de '+tot
   + (tot!==todos.length?(' <span style="opacity:.75">(de '+todos.length+')</span>'):'')+'</span>'
   + '</div></div>';

  OV.radar._pag = pageRows;                       // os "olhinhos" leem daqui pelo índice da linha
  const VIS = ovColsGet();
  const arw=col=>OV.radar.sort.col===col?(OV.radar.sort.dir>0?' ▲':' ▼'):'';
  const th=(lbl,col,extra)=>'<th '+(extra||'')+' onclick="ovRadarSort(\''+col+'\')" style="cursor:pointer;user-select:none;white-space:nowrap">'+lbl+arw(col)+'</th>';
  /* cabeçalho e corpo saem da MESMA lista de colunas visíveis — some a chance de a ordem divergir */
  const CAB={
    item:   ()=>th('Item','item'),
    obra:   ()=>th('Obra','obra'),
    grupo:  ()=>th('Grupo','grupo'),
    status: ()=>th('Status','status','title="em que pé está a compra"'),
    prazo:  ()=>th('Prazo','alerta','title="se a cotação está dentro do prazo"'),
    cotar:  ()=>th('Cotar até','fim_cotacao','title="prazo final da cotação — abaixo, quando ela deveria começar"'),
    emobra: ()=>th('Prazo em obra','data_em_obra','title="quando o item precisa estar em obra (material, serviço, equipamento ou empreitada)"'),
    verba:  ()=>th('Verba','verba','style="text-align:right"'),
    resp:   ()=>th('Responsável','responsavel'),
    cot:    ()=>'<th>Cotação</th>',
  };
  h+='<div class="wrap" style="overflow-x:auto"><table style="width:100%;font-size:12px"><thead><tr>'
   + VIS.map(k=>CAB[k]?CAB[k]():'').join('') + '</tr></thead><tbody>';

  pageRows.forEach((l,ix)=>{
    const dias=ovDias(l.data_em_obra);
    const corDias = dias===null ? 'var(--muted)' : (dias<0 ? '#c0392b' : (dias<=30 ? 'var(--dourado)' : 'var(--muted)'));
    const txtDias = dias===null ? '' : (dias<0 ? ('há '+Math.abs(dias)+'d') : ('em '+dias+'d'));
    const cot=l.cotacao;
    const verba = l.verba_definida!=null ? l.verba_definida : l.verba_estimada;
    const olho = fn=>'<span class="material-icons" onclick="event.stopPropagation();'+fn+'" title="ver como isto foi apurado" style="font-size:14px;vertical-align:-3px;cursor:pointer;color:var(--verde);margin-left:3px">visibility</span>';
    const CEL={
      item:  ()=>'<td style="max-width:270px"><div style="font-weight:700">'+esc(l.item)+'</div>'
                + (l.fornecedor?('<div class="dmini" style="color:var(--muted)">'+esc(l.fornecedor)+'</div>'):'')+'</td>',
      obra:  ()=>'<td class="muted">'+esc(l.obra||'—')+'</td>',
      grupo: ()=>'<td class="muted" style="max-width:140px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="'+esc(l.grupo||'')+'">'+esc(l.grupo||'—')+'</td>',
      status:()=>'<td>'+ovChipStatus(l)+'</td>',
      /* item concluído não tem prazo a cumprir — mostrar "Concluído" aqui era o que embaralhava
         as duas leituras. O status já diz isso na coluna ao lado. */
      prazo: ()=>'<td>'+(l.alerta==='finalizado'
                ? '<span class="muted" title="item concluído — prazo não se aplica">—</span>'
                : ovChipAlerta(l.alerta))+'</td>',
      cotar: ()=>'<td style="white-space:nowrap"><b>'+ovData(l.fim_cotacao)+'</b>'
                + '<div class="dmini" style="color:var(--muted)">início '+ovData(l.inicio_cotacao)+'</div></td>',
      emobra:()=>'<td style="white-space:nowrap"><b>'+ovData(l.data_em_obra)+'</b>'+olho('ovVerCrono('+ix+')')
                + (txtDias?('<div class="dmini" style="color:'+corDias+'">'+txtDias+'</div>'):'')
                + (l.data_em_obra_origem==='sem data'?'<div class="dmini" style="color:var(--muted)">sem cronograma</div>':'')+'</td>',
      verba: ()=>'<td style="text-align:right;white-space:nowrap">'
                + (verba!=null?('<b>'+BRL(verba)+'</b>'+olho('ovVerVerba('+ix+')')):'<span class="muted">—</span>')
                + (verba!=null&&l.verba_definida==null?'<div class="dmini" style="color:var(--muted)">estimada</div>':'')+'</td>',
      resp:  ()=>'<td class="muted" style="white-space:nowrap">'+esc(l.responsavel||'—')+'</td>',
      cot:   ()=>'<td style="white-space:nowrap">'+(cot
                ? ('<button class="btn-ghost" style="padding:2px 8px;font-size:11px" onclick="ovCotAbrir('+cot.cotacao_id+')" '
                  +'title="'+esc(cot.titulo||'')+' — '+esc(cot.status_texto||'')+'">'
                  +cot.propostas_recebidas+'/'+cot.fornecedores_convidados+' propostas'
                  +(cot.melhor_oferta?(' · '+BRL(cot.melhor_oferta)):'')+'</button>')
                : '<span class="muted">—</span>')+'</td>',
    };
    h+='<tr>'+VIS.map(k=>CEL[k]?CEL[k]():'').join('')+'</tr>';
  });
  if(!rows.length) h+='<tr><td colspan="'+VIS.length+'" class="empty">'+(todos.length?'Nenhum item casa os filtros.':'Nenhum item no radar desta obra.')+'</td></tr>';
  h+='</tbody></table></div>';

  if(pgs>1){
    const b=(p,lbl,cur)=>'<button class="'+(cur?'btn-prim':'btn-ghost')+'" style="padding:5px 10px;font-size:12px;min-width:34px" onclick="ovRadarPagina('+p+')">'+lbl+'</button>';
    const n=[], a=Math.max(1,OV.radar.page-2), z=Math.min(pgs,OV.radar.page+2);
    if(a>1){ n.push(b(1,'1',false)); if(a>2)n.push('<span class="muted" style="padding:0 2px">…</span>'); }
    for(let p=a;p<=z;p++) n.push(b(p,String(p),p===OV.radar.page));
    if(z<pgs){ if(z<pgs-1)n.push('<span class="muted" style="padding:0 2px">…</span>'); n.push(b(pgs,String(pgs),false)); }
    h+='<div class="bar" style="justify-content:center;gap:6px;margin-top:10px;flex-wrap:wrap;align-items:center">'
     + b(Math.max(1,OV.radar.page-1),'‹',false)+n.join('')+b(Math.min(pgs,OV.radar.page+1),'›',false)
     + '<span class="muted" style="font-size:11.5px;margin-left:8px">página '+OV.radar.page+' de '+pgs+'</span></div>';
  }
  h+='<div class="dmini" style="margin-top:10px;color:var(--muted)">Somente consulta — quem altera é o comprador responsável.'
   + (d.gerado_em?(' Atualizado às '+new Date(d.gerado_em).toLocaleTimeString('pt-BR').slice(0,5)+'.'):'')+'</div>';

  // preserva foco/caret da busca (o innerHTML recria o input a cada tecla)
  const foc=document.activeElement, eraQ=foc&&foc.id==='ovRadarQ', car=eraQ?foc.selectionStart:null;
  w.innerHTML=h;
  if(eraQ){ const ni=document.getElementById('ovRadarQ'); if(ni){ ni.focus(); try{ ni.setSelectionRange(car,car); }catch(e){} } }
}


/* ─────────────────────────── COTAÇÕES (em que pé está a compra) ─────────────────────────── */

OV.cot = { d:null, det:null, filt:{obra:'',status:'',origem:'',cat:'',criador:'',prop:'',q:''}, page:1,
           sort:{col:'criado_em',dir:-1} };

const OV_ORIGEM = { radar:['#2d7d5a','do radar'], solicitacao:['#6b7c93','de uma SC'], zero:['#8a9299','avulsa'] };
const OV_COTST  = { aberta:['var(--dourado)','Em cotação'], aguardando:['#6b7c93','Aguardando decisão'],
                    finalizada:['var(--ok)','Finalizada'] };

function ovCotInit(){ if(OV.cot.det) ovCotDetRender(); else if(!OV.cot.d) ovCotLoad(); else ovCotRender(); }
async function ovCotLoad(){
  const w=document.getElementById('ovCotWrap'); if(!w) return;
  w.innerHTML='<div class="dempty">Lendo as cotações…</div>';
  const p=new URLSearchParams({tela:'cotacoes', me:decodeURIComponent(ovMe()), por_pagina:'0'});
  if(OV.cot.filt.obra) p.set('obra_id', OV.cot.filt.obra);
  try{
    const d=await (await fetch('actions/obra_consulta.php?'+p.toString())).json();
    /* o api.php (reusado como biblioteca) responde erro na chave `erro`; o obra_consulta.php usa
       `error`. Tratar só uma deixava a tela presa em "Carregando…" sem dizer o que houve. */
    if(d.error||d.erro){ w.innerHTML='<div class="dempty">'+esc(d.error||d.erro)+'</div>'; return; }
    OV.cot.d=d; ovCotRender();
  }catch(e){ w.innerHTML='<div class="dempty">Falha ao carregar: '+esc(e.message)+'</div>'; }
}
function ovCotSet(c,v){ OV.cot.filt[c]=v; OV.cot.page=1; OV.cot.det=null; if(c==='obra') ovCotLoad(); else ovCotRender(); }
function ovCotSort(col){ const s=OV.cot.sort; if(s.col===col) s.dir=-s.dir;
  else { s.col=col; s.dir=(col==='criado_em'||col==='melhor_oferta'||col==='propostas_recebidas')?-1:1; }
  OV.cot.page=1; ovCotRender(); }
function ovCotPagina(p){ OV.cot.page=Math.max(1,p|0); ovCotRender();
  const w=document.getElementById('ovCotWrap'); if(w) w.scrollIntoView({block:'start',behavior:'smooth'}); }
function ovCotLimpar(){ OV.cot.filt={obra:OV.cot.filt.obra,status:'',origem:'',cat:'',criador:'',prop:'',q:''};
  OV.cot.page=1; ovCotRender(); }

function ovCotRender(){
  const w=document.getElementById('ovCotWrap'); if(!w) return;
  const d=OV.cot.d; if(!d) return;
  const f=OV.cot.filt, todos=d.dados||[];
  const qn=(typeof opNorm==='function')?opNorm(f.q||''):(f.q||'').toLowerCase();
  let rows=todos.filter(c=>{
    if(f.status && c.status!==f.status) return false;
    if(f.origem && c.origem!==f.origem) return false;
    if(f.cat    && c.categoria!==f.cat) return false;
    if(f.criador&& c.criado_por!==f.criador) return false;
    if(f.prop==='1' && !(+c.propostas_recebidas)) return false;
    if(f.prop==='0' && (+c.propostas_recebidas))  return false;
    if(qn){ const alvo=(c.apelido||'')+' '+(c.titulo||'')+' '+(c.item_radar||'')+' '+(c.categoria||'')+' '
                      +(c.num_solicitacao||'')+' '+(c.num_pedido||'')+' '+(c.obra||'');
            const nz=(typeof opNorm==='function')?opNorm(alvo):alvo.toLowerCase();
            if(nz.indexOf(qn)<0) return false; }
    return true;
  });
  const sc=OV.cot.sort.col, dir=OV.cot.sort.dir;
  const val=c=>({titulo:((c.apelido||c.titulo)||'').toLowerCase(), obra:(c.obra||'').toLowerCase(),
    origem:(c.origem||''), status:(c.status||''), itens:+c.itens||0,
    propostas_recebidas:+c.propostas_recebidas||0, melhor_oferta:+c.melhor_oferta||0,
    criado_por:(c.criado_por||'').toLowerCase(), criado_em:(c.criado_em||'')}[sc]);
  rows=rows.slice().sort((a,b)=>{ const x=val(a),y=val(b); return (x<y?-1:x>y?1:0)*dir; });

  const tot=rows.length, pgs=Math.max(1,Math.ceil(tot/OV_POR_PAGINA));
  OV.cot.page=Math.min(Math.max(1,OV.cot.page),pgs);
  const ini=(OV.cot.page-1)*OV_POR_PAGINA, pageRows=rows.slice(ini,ini+OV_POR_PAGINA);
  const c=d.contadores||{};
  const card=(cor,n,tit,sub,onclick)=>'<div class="panel" style="flex:1;min-width:150px;border-left:4px solid '+cor
    +';cursor:'+(onclick?'pointer':'default')+'" '+(onclick?('onclick="'+onclick+'"'):'')+'>'
    +'<div style="font-size:23px;font-weight:800;line-height:1.1">'+n+'</div>'
    +'<div style="font-weight:700;font-size:12.5px">'+tit+'</div>'
    +'<div class="dmini" style="color:var(--muted)">'+sub+'</div></div>';

  let h=cotSecHead('request_quote','Cotações',
    'em que pé está a compra de cada item — quantos fornecedores responderam e por quanto',
    '<button class="btn-ghost" style="padding:5px 12px" onclick="OV.cot.d=null;ovCotLoad()">'
    +'<span class="material-icons" style="font-size:15px;vertical-align:-3px">refresh</span> Atualizar</button>');
  h+='<div class="bar" style="gap:10px;flex-wrap:wrap;margin-bottom:10px">'
   + card('var(--verde)', c.total||0, 'Cotações', 'total da(s) obra(s) selecionada(s)')
   + card('var(--dourado)', c.em_cotacao||0, 'Em cotação', 'ainda recebendo propostas', "ovCotSet('status','aberta')")
   + card('#6b7c93', c.aguardando||0, 'Aguardando decisão', 'propostas recebidas, falta escolher', "ovCotSet('status','aguardando')")
   + card('var(--ok)', c.finalizadas||0, 'Finalizadas', 'compra decidida', "ovCotSet('status','finalizada')")
   + card('#c0392b', c.sem_proposta||0, 'Sem proposta', 'nenhum fornecedor respondeu ainda', "ovCotSet('prop','0')")
   + '</div>';

  const opts=(l,sel)=>l.map(x=>'<option value="'+esc(x)+'"'+(String(sel)===String(x)?' selected':'')+'>'+esc(x)+'</option>').join('');
  const obrasSel=(OV.radar.obras||[]).map(o=>'<option value="'+o.obra_id+'"'+(String(f.obra)===String(o.obra_id)?' selected':'')+'>'+esc(o.obra)+'</option>').join('');
  h+='<div class="panel" style="margin-bottom:10px"><div class="bar" style="gap:8px;flex-wrap:wrap;align-items:center">'
   + '<div class="search" style="min-width:220px"><span class="material-icons" style="color:var(--muted)">search</span>'
   + '<input id="ovCotQ" placeholder="Buscar cotação, item, nº de SC ou de pedido…" value="'+esc(f.q)+'" '
   + 'oninput="OV.cot.filt.q=this.value;OV.cot.page=1;ovCotRender()"></div>'
   + '<label class="muted" style="font-size:12px">Obra <select onchange="ovCotSet(\'obra\',this.value)" style="margin-left:4px">'
   + '<option value="">Todas as obras</option>'+obrasSel+'</select></label>'
   + '<select onchange="ovCotSet(\'status\',this.value)" style="font-size:12px;padding:6px"><option value="">Qualquer status</option>'
   + Object.keys(OV_COTST).map(k=>'<option value="'+k+'"'+(f.status===k?' selected':'')+'>'+OV_COTST[k][1]+'</option>').join('')+'</select>'
   + '<select onchange="ovCotSet(\'origem\',this.value)" style="font-size:12px;padding:6px"><option value="">Qualquer origem</option>'
   + Object.keys(OV_ORIGEM).map(k=>'<option value="'+k+'"'+(f.origem===k?' selected':'')+'>'+OV_ORIGEM[k][1]+'</option>').join('')+'</select>'
   + '<select onchange="ovCotSet(\'cat\',this.value)" style="font-size:12px;padding:6px"><option value="">Todas categorias</option>'
   + opts(d.categorias||[], f.cat)+'</select>'
   + '<select onchange="ovCotSet(\'criador\',this.value)" style="font-size:12px;padding:6px" title="comprador que abriu a cotação">'
   + '<option value="">Todos os compradores</option>'+opts(d.criadores||[], f.criador)+'</select>'
   + '<select onchange="ovCotSet(\'prop\',this.value)" style="font-size:12px;padding:6px"><option value="">Com ou sem proposta</option>'
   + '<option value="1"'+(f.prop==='1'?' selected':'')+'>Só com proposta</option>'
   + '<option value="0"'+(f.prop==='0'?' selected':'')+'>Só sem proposta</option></select>'
   + '<button class="btn-ghost" style="padding:6px 11px;font-size:12px" onclick="ovCotLimpar()">Limpar filtros</button>'
   + '<span class="muted" style="font-size:11.5px;margin-left:auto">'+(tot?(ini+1):0)+'–'+(ini+pageRows.length)+' de '+tot
   + (tot!==todos.length?(' <span style="opacity:.75">(de '+todos.length+')</span>'):'')+'</span>'
   + '</div></div>';

  const arw=col=>OV.cot.sort.col===col?(OV.cot.sort.dir>0?' ▲':' ▼'):'';
  const th=(lbl,col,ex)=>'<th '+(ex||'')+' onclick="ovCotSort(\''+col+'\')" style="cursor:pointer;user-select:none;white-space:nowrap">'+lbl+arw(col)+'</th>';
  h+='<div class="wrap" style="overflow-x:auto"><table style="width:100%;font-size:12px"><thead><tr>'
   + th('Cotação','titulo') + th('Obra','obra') + th('Origem','origem')
   + th('Status','status') + th('Itens','itens','style="text-align:center"')
   + th('Respostas','propostas_recebidas','style="text-align:center" title="propostas recebidas / fornecedores convidados"')
   + th('Melhor oferta','melhor_oferta','style="text-align:right"')
   + '<th>SC / Pedido</th>' + th('Aberta em','criado_em') + '<th></th></tr></thead><tbody>';
  for(const c2 of pageRows){
    const or=OV_ORIGEM[c2.origem]||['#8a9299',c2.origem||'—'];
    const st=OV_COTST[c2.status]||['#8a9299',c2.status_texto||c2.status];
    h+='<tr style="cursor:pointer" onclick="ovCotAbrir('+c2.cotacao_id+')">'
     + '<td style="max-width:270px"><div style="font-weight:700;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">'
     +   esc(c2.apelido||c2.titulo)+'</div>'
     +   (c2.apelido?('<div class="dmini" style="color:var(--muted);overflow:hidden;text-overflow:ellipsis;white-space:nowrap">'+esc(c2.titulo)+'</div>'):'')
     +   (c2.categoria?('<div class="dmini" style="color:var(--muted)">'+esc(c2.categoria)+'</div>'):'')+'</td>'
     + '<td class="muted">'+esc(c2.obra||'—')+'</td>'
     + '<td><span class="dchip" style="background:'+or[0]+'">'+or[1]+'</span></td>'
     + '<td><span class="dchip" style="background:'+st[0]+'">'+st[1]+'</span></td>'
     + '<td style="text-align:center">'+c2.itens+'</td>'
     + '<td style="text-align:center"><b>'+c2.propostas_recebidas+'</b><span class="muted">/'+c2.fornecedores_convidados+'</span></td>'
     + '<td style="text-align:right;white-space:nowrap">'+(c2.melhor_oferta?BRL(c2.melhor_oferta):'—')+'</td>'
     + '<td class="muted" style="font-size:11px;white-space:nowrap">'
     +   (c2.num_solicitacao?('SC '+esc(c2.num_solicitacao)):'')+(c2.num_solicitacao&&c2.num_pedido?'<br>':'')
     +   (c2.num_pedido?('<b style="color:var(--verde-d)">PC '+esc(c2.num_pedido)+'</b>'):'')
     +   (!c2.num_solicitacao&&!c2.num_pedido?'—':'')+'</td>'
     + '<td class="muted" style="white-space:nowrap">'+ovData(c2.criado_em)
     +   '<div class="dmini">'+esc(c2.criado_por||'—')+'</div></td>'
     + '<td style="text-align:center"><span class="material-icons" style="color:var(--muted)">chevron_right</span></td></tr>';
  }
  if(!rows.length) h+='<tr><td colspan="10" class="empty">'+(todos.length?'Nenhuma cotação casa os filtros.':'Nenhuma cotação nesta obra.')+'</td></tr>';
  h+='</tbody></table></div>';
  if(pgs>1){
    const b=(p,lbl,cur)=>'<button class="'+(cur?'btn-prim':'btn-ghost')+'" style="padding:5px 10px;font-size:12px;min-width:34px" onclick="ovCotPagina('+p+')">'+lbl+'</button>';
    const n=[], a=Math.max(1,OV.cot.page-2), z=Math.min(pgs,OV.cot.page+2);
    if(a>1){ n.push(b(1,'1',false)); if(a>2)n.push('<span class="muted" style="padding:0 2px">…</span>'); }
    for(let p=a;p<=z;p++) n.push(b(p,String(p),p===OV.cot.page));
    if(z<pgs){ if(z<pgs-1)n.push('<span class="muted" style="padding:0 2px">…</span>'); n.push(b(pgs,String(pgs),false)); }
    h+='<div class="bar" style="justify-content:center;gap:6px;margin-top:10px;flex-wrap:wrap;align-items:center">'
     + b(Math.max(1,OV.cot.page-1),'‹',false)+n.join('')+b(Math.min(pgs,OV.cot.page+1),'›',false)
     + '<span class="muted" style="font-size:11.5px;margin-left:8px">página '+OV.cot.page+' de '+pgs+'</span></div>';
  }
  h+='<div class="dmini" style="margin-top:10px;color:var(--muted)">Somente consulta — clique numa linha para ver os fornecedores e o comparativo de preços.</div>';
  const foc=document.activeElement, eraQ=foc&&foc.id==='ovCotQ', car=eraQ?foc.selectionStart:null;
  w.innerHTML=h;
  if(eraQ){ const ni=document.getElementById('ovCotQ'); if(ni){ ni.focus(); try{ ni.setSelectionRange(car,car); }catch(e){} } }
}

/* ---- detalhe: fornecedores + mapa comparativo (leitura) ---- */
async function ovCotAbrir(id){
  try{ const v=document.getElementById('view-ovcot'); if(v && v.style.display==='none') showView('ovcot'); }catch(e){}
  const w=document.getElementById('ovCotWrap'); if(!w) return;
  w.innerHTML='<div class="dempty">Abrindo a cotação…</div>';
  try{
    const r=await (await fetch('actions/obra_consulta.php?tela=cotacao&id='+id+'&me='+ovMe())).json();
    if(r.error||r.erro){ w.innerHTML='<div class="dempty">'+esc(r.error||r.erro)+'</div>'
      +'<div class="bar" style="margin-top:10px"><button class="btn-ghost" onclick="ovCotVoltar()">Voltar para a lista</button></div>'; return; }
    OV.cot.det=r.dados; ovCotDetRender();
  }catch(e){ w.innerHTML='<div class="dempty">Falha: '+esc(e.message)+'</div>'; }
}
function ovCotVoltar(){ OV.cot.det=null; if(!OV.cot.d) ovCotLoad(); else ovCotRender(); }

function ovCotDetRender(){
  const w=document.getElementById('ovCotWrap'); const c=OV.cot.det; if(!w||!c) return;
  const st=OV_COTST[c.status]||['#8a9299',c.status_texto||c.status];
  const or=OV_ORIGEM[c.origem]||['#8a9299',c.origem||''];
  const itens=c.itens_detalhe||[], props=c.propostas||[], forn=c.fornecedores||[];

  let h='<div class="bar" style="margin-bottom:10px"><button class="btn-ghost" style="padding:5px 12px" onclick="ovCotVoltar()">'
   + '<span class="material-icons" style="font-size:15px;vertical-align:-3px">arrow_back</span> Voltar para a lista</button></div>';
  h+='<div class="panel" style="margin-bottom:10px">'
   + '<div style="font-size:16px;font-weight:800">'+esc(c.apelido||c.titulo)+'</div>'
   + (c.apelido?('<div class="muted" style="font-size:12.5px">'+esc(c.titulo)+'</div>'):'')
   + '<div class="bar" style="gap:8px;flex-wrap:wrap;margin-top:8px;align-items:center">'
   + '<span class="dchip" style="background:'+st[0]+'">'+st[1]+'</span>'
   + '<span class="dchip" style="background:'+or[0]+'">'+or[1]+'</span>'
   + (c.obra?('<span class="muted" style="font-size:12.5px">'+esc(c.obra)+'</span>'):'')
   + (c.categoria?('<span class="muted" style="font-size:12.5px">· '+esc(c.categoria)+'</span>'):'')
   + '<span class="muted" style="font-size:12.5px">· aberta em '+ovData(c.criado_em)+' por '+esc(c.criado_por||'—')+'</span>'
   + (c.num_solicitacao?('<span class="muted" style="font-size:12.5px">· SC '+esc(c.num_solicitacao)+'</span>'):'')
   + (c.num_pedido?('<span class="dchip" style="background:var(--verde-d)">PC '+esc(c.num_pedido)+'</span>'):'')
   + '</div>'
   + (c.descricao?('<div class="dmini" style="margin-top:8px;white-space:pre-wrap">'+esc(c.descricao)+'</div>'):'')
   + '</div>';

  h+='<div class="panel" style="margin-bottom:10px">'+cotSecHead('groups','Fornecedores',
     'quem foi convidado, quem recebeu o pedido de cotação e quem respondeu','');
  if(!forn.length) h+='<div class="dempty">Nenhum fornecedor convidado ainda.</div>';
  else{
    h+='<div class="wrap" style="overflow-x:auto"><table style="width:100%;font-size:12px"><thead><tr><th>Fornecedor</th>'
     + '<th>Convidado</th><th>Recebeu</th><th>Respondeu</th><th style="text-align:right">Proposta</th><th>Prazo</th></tr></thead><tbody>';
    for(const fo of forn){
      const sim='<span style="color:var(--ok);font-weight:700">sim</span>', nao='<span class="muted">não</span>';
      h+='<tr><td><b>'+esc(fo.fornecedor)+'</b>'+(fo.categoria?('<div class="dmini" style="color:var(--muted)">'+esc(fo.categoria)+'</div>'):'')+'</td>'
       + '<td class="muted">'+ovData(fo.convidado_em)+'</td>'
       + '<td>'+(fo.disparado?(sim+'<div class="dmini" style="color:var(--muted)">'+ovData(fo.disparado_em)+'</div>'):nao)+'</td>'
       + '<td>'+(fo.respondeu?sim:nao)+'</td>'
       + '<td style="text-align:right;white-space:nowrap">'+(fo.proposta_total?BRL(fo.proposta_total):'—')+'</td>'
       + '<td class="muted">'+esc(fo.proposta_prazo||'—')+'</td></tr>';
    }
    h+='</tbody></table></div>';
  }
  h+='</div>';

  h+='<div class="panel" style="margin-bottom:10px">'+cotSecHead('table_chart','Comparativo de preços',
      'preço de cada fornecedor por item — o troféu marca o menor de cada linha','');
  if(!props.length || !itens.length){
    h+='<div class="dempty">Ainda não há propostas para comparar.</div>';
  }else{
    /* Com muito mais fornecedor do que item a tabela vira uma tira larga e ilegível: nesse caso
       TRANSPÕE (fornecedores nas linhas). Mesma regra da "uma página" das telas de suprimentos. */
    const transpor = props.length>=5 && props.length>itens.length;
    const preco=(p,itemId)=>((p.precos||[]).find(z=>String(z.item_id)===String(itemId))||null);
    if(!transpor){
      h+='<div class="wrap" style="overflow-x:auto"><table style="width:100%;font-size:12px"><thead><tr>'
       + '<th style="min-width:170px">Item</th><th style="text-align:right">Qtd</th>'
       + props.map(p=>'<th style="text-align:right">'+esc(p.fornecedor)+(p.revisao?(' <span class="dmini">rev '+p.revisao+'</span>'):'')+'</th>').join('')
       + '<th style="text-align:right">Melhor</th></tr></thead><tbody>';
      for(const it of itens){
        h+='<tr><td><b>'+esc(it.descricao)+'</b>'+(it.observacao?('<div class="dmini" style="color:var(--muted)">'+esc(it.observacao)+'</div>'):'')+'</td>'
         + '<td style="text-align:right;white-space:nowrap" class="muted">'+(it.quantidade!==null?(it.quantidade+' '+esc(it.unidade||'')):'—')+'</td>';
        for(const p of props){
          const x=preco(p,it.item_id);
          const melhor = it.melhor && String(it.melhor.proposta_id)===String(p.proposta_id);
          h+='<td style="text-align:right;white-space:nowrap'+(melhor?';background:#eef6f0;font-weight:700':'')+'">'
           + (x&&x.preco_total?((melhor?'🏆 ':'')+BRL(x.preco_total)):'<span class="muted">—</span>')
           + (x&&x.observacao?('<div class="dmini" style="color:var(--muted);font-weight:400">'+esc(x.observacao)+'</div>'):'')+'</td>';
        }
        h+='<td style="text-align:right;white-space:nowrap">'+(it.melhor?('<b>'+BRL(it.melhor.preco_total)+'</b>'
           +'<div class="dmini" style="color:var(--muted)">'+esc(it.melhor.fornecedor)+'</div>'):'—')+'</td></tr>';
      }
      h+='<tr style="border-top:2px solid var(--line)"><td colspan="2"><b>TOTAL</b></td>'
       + props.map(p=>'<td style="text-align:right;white-space:nowrap"><b>'+(p.total?BRL(p.total):'—')+'</b></td>').join('')
       + '<td style="text-align:right"><b>'+(c.soma_dos_melhores?BRL(c.soma_dos_melhores):'—')+'</b></td></tr>';
      h+='</tbody></table></div>';
    }else{
      const ord=props.slice().sort((a,b)=>((+a.total||Infinity)-(+b.total||Infinity)));
      h+='<div class="wrap" style="overflow-x:auto"><table style="width:100%;font-size:12px"><thead><tr>'
       + '<th style="min-width:190px">Fornecedor</th>'
       + itens.map(it=>'<th style="text-align:right">'+esc(it.descricao)+'</th>').join('')
       + '<th style="text-align:right">Total</th></tr></thead><tbody>';
      ord.forEach((p,i)=>{
        h+='<tr><td><b>'+(i===0?'🏆 ':'')+esc(p.fornecedor)+'</b>'
         + (p.prazo?('<div class="dmini" style="color:var(--muted)">prazo: '+esc(p.prazo)+'</div>'):'')+'</td>';
        for(const it of itens){
          const x=preco(p,it.item_id);
          const melhor = it.melhor && String(it.melhor.proposta_id)===String(p.proposta_id);
          h+='<td style="text-align:right;white-space:nowrap'+(melhor?';background:#eef6f0;font-weight:700':'')+'">'
           + (x&&x.preco_total?BRL(x.preco_total):'<span class="muted">—</span>')+'</td>';
        }
        h+='<td style="text-align:right;white-space:nowrap"><b>'+(p.total?BRL(p.total):'—')+'</b></td></tr>';
      });
      h+='</tbody></table></div>';
    }
    h+='<div class="dmini" style="margin-top:8px;color:var(--muted)">"Melhor" soma o menor preço de cada item — '
     + 'pode ficar acima do total de um fornecedor que só cotou parte dos itens.</div>';
  }
  h+='</div>';

  h+='<div class="panel">'+cotSecHead('list_alt','Itens a cotar','o que foi pedido aos fornecedores','');
  h+='<div class="wrap" style="overflow-x:auto"><table style="width:100%;font-size:12px"><thead><tr><th>Item</th>'
   + '<th style="text-align:right">Quantidade</th><th>SC</th></tr></thead><tbody>';
  for(const it of itens) h+='<tr><td><b>'+esc(it.descricao)+'</b>'
    +(it.observacao?('<div class="dmini" style="color:var(--muted)">'+esc(it.observacao)+'</div>'):'')+'</td>'
    +'<td style="text-align:right;white-space:nowrap" class="muted">'+(it.quantidade!==null?(it.quantidade+' '+esc(it.unidade||'')):'—')+'</td>'
    +'<td class="muted">'+esc(it.num_solicitacao||'—')+'</td></tr>';
  if(!itens.length) h+='<tr><td colspan="3" class="empty">Sem itens cadastrados.</td></tr>';
  h+='</tbody></table></div></div>';

  w.innerHTML=h;
}

/* ─────────────────────── SOLICITAÇÕES DE COMPRA (a minha SC virou o quê?) ───────────────────────
   O número que muda a conversa entre obra e suprimentos é DIAS EM ABERTO: é ele que transforma
   "cadê meu material" em "a SC 1533 está há 22 dias sem cotação". Por isso ele tem coluna própria,
   cor por faixa e um atalho de "paradas há mais de 15 dias". */

OV.sc = { d:null, filt:{obra:'',status:'',comprador:'',situacao:'',aging:'',q:''}, page:1,
          sort:{col:'dias_em_aberto',dir:-1}, aberta:null };

const OV_SCST = { pendente:['#6b7c93','Pendente'], em_cotacao:['var(--dourado)','Em cotação'],
                  cotacoes_recebidas:['#2d7d5a','Cotações recebidas'], pedido_criado:['var(--ok)','Pedido criado'],
                  cancelado:['#8a9299','Cancelado'] };
const OV_COB  = { vazio:['#c0392b','Sem cotação'], parcial:['var(--dourado)','Parcial'], total:['var(--ok)','Cotada'] };

function ovScInit(){ if(!OV.sc.d) ovScLoad(); else ovScRender(); }
async function ovScLoad(){
  const w=document.getElementById('ovScWrap'); if(!w) return;
  w.innerHTML='<div class="dempty">Lendo as solicitações de compra…</div>';
  const p=new URLSearchParams({tela:'solicitacoes', me:decodeURIComponent(ovMe()), por_pagina:'0'});
  if(OV.sc.filt.obra) p.set('obra_id', OV.sc.filt.obra);
  try{
    const d=await (await fetch('actions/obra_consulta.php?'+p.toString())).json();
    if(d.error||d.erro){ w.innerHTML='<div class="dempty">'+esc(d.error||d.erro)+'</div>'; return; }
    OV.sc.d=d; ovScRender();
  }catch(e){ w.innerHTML='<div class="dempty">Falha ao carregar: '+esc(e.message)+'</div>'; }
}
function ovScSet(c,v){ OV.sc.filt[c]=v; OV.sc.page=1; if(c==='obra') ovScLoad(); else ovScRender(); }
function ovScSort(col){ const s=OV.sc.sort; if(s.col===col) s.dir=-s.dir;
  else { s.col=col; s.dir=(col==='dias_em_aberto'||col==='emissao')?-1:1; } OV.sc.page=1; ovScRender(); }
function ovScPagina(p){ OV.sc.page=Math.max(1,p|0); ovScRender();
  const w=document.getElementById('ovScWrap'); if(w) w.scrollIntoView({block:'start',behavior:'smooth'}); }
function ovScLimpar(){ OV.sc.filt={obra:OV.sc.filt.obra,status:'',comprador:'',situacao:'',aging:'',q:''};
  OV.sc.page=1; ovScRender(); }
function ovScToggle(k){ OV.sc.aberta = (OV.sc.aberta===k) ? null : k; ovScRender(); }

function ovScRender(){
  const w=document.getElementById('ovScWrap'); if(!w) return;
  const d=OV.sc.d; if(!d) return;
  const f=OV.sc.filt, todos=d.dados||[];
  const qn=(typeof opNorm==='function')?opNorm(f.q||''):(f.q||'').toLowerCase();
  let rows=todos.filter(s=>{
    if(f.status && s.status!==f.status) return false;
    if(f.comprador && s.comprador!==f.comprador) return false;
    if(f.situacao && s.cotacao_situacao!==f.situacao) return false;
    if(f.aging==='15' && !(s.dias_em_aberto>15)) return false;
    if(f.aging==='30' && !(s.dias_em_aberto>30)) return false;
    if(qn){ const alvo=(s.numero||'')+' '+(s.obra||'')+' '+(s.comprador||'')+' '
                      +(s.itens||[]).map(i=>i.produto).join(' ');
            const nz=(typeof opNorm==='function')?opNorm(alvo):alvo.toLowerCase();
            if(nz.indexOf(qn)<0) return false; }
    return true;
  });
  const sc=OV.sc.sort.col, dir=OV.sc.sort.dir;
  const val=s=>({numero:(s.numero||''), obra:(s.obra||'').toLowerCase(), comprador:(s.comprador||'zzz').toLowerCase(),
    emissao:(s.emissao||''), dias_em_aberto:(s.dias_em_aberto===null?-1:+s.dias_em_aberto),
    status:(s.status||''), cobertura:((s.itens_total?(s.itens_cotados/s.itens_total):0)),
    itens:(+s.itens_total||0)}[sc]);
  rows=rows.slice().sort((a,b)=>{ const x=val(a),y=val(b); return (x<y?-1:x>y?1:0)*dir; });

  const tot=rows.length, pgs=Math.max(1,Math.ceil(tot/OV_POR_PAGINA));
  OV.sc.page=Math.min(Math.max(1,OV.sc.page),pgs);
  const ini=(OV.sc.page-1)*OV_POR_PAGINA, pageRows=rows.slice(ini,ini+OV_POR_PAGINA);
  const c=d.contadores||{};
  const card=(cor,n,tit,sub,onclick)=>'<div class="panel" style="flex:1;min-width:150px;border-left:4px solid '+cor
    +';cursor:'+(onclick?'pointer':'default')+'" '+(onclick?('onclick="'+onclick+'"'):'')+'>'
    +'<div style="font-size:23px;font-weight:800;line-height:1.1">'+n+'</div>'
    +'<div style="font-weight:700;font-size:12.5px">'+tit+'</div>'
    +'<div class="dmini" style="color:var(--muted)">'+sub+'</div></div>';

  let h=cotSecHead('inbox','Solicitações Totvs',
    'o que a obra pediu e o que já virou cotação — o tempo em aberto é o que mais importa aqui',
    '<button class="btn-ghost" style="padding:5px 12px" onclick="OV.sc.d=null;ovScLoad()">'
    +'<span class="material-icons" style="font-size:15px;vertical-align:-3px">refresh</span> Atualizar</button>');
  h+='<div class="bar" style="gap:10px;flex-wrap:wrap;margin-bottom:10px">'
   + card('var(--verde)', c.total||0, 'Solicitações', 'total da(s) obra(s) selecionada(s)')
   + card('#c0392b', c.sem_cotacao||0, 'Sem nenhuma cotação', 'ninguém começou a cotar ainda', "ovScSet('situacao','vazio')")
   + card('var(--dourado)', c.paradas15||0, 'Há mais de 15 dias', 'abertas e ainda não resolvidas', "ovScSet('aging','15')")
   + card('#e67e22', c.paradas30||0, 'Há mais de 30 dias', 'as mais antigas da fila', "ovScSet('aging','30')")
   + card('#6b7c93', (c.media_dias||0)+'d', 'Tempo médio', 'média de dias em aberto')
   + '</div>';

  const opts=(l,sel)=>l.map(x=>'<option value="'+esc(x)+'"'+(String(sel)===String(x)?' selected':'')+'>'+esc(x)+'</option>').join('');
  const obrasSel=(OV.radar.obras||[]).map(o=>'<option value="'+o.obra_id+'"'+(String(f.obra)===String(o.obra_id)?' selected':'')+'>'+esc(o.obra)+'</option>').join('');
  h+='<div class="panel" style="margin-bottom:10px"><div class="bar" style="gap:8px;flex-wrap:wrap;align-items:center">'
   + '<div class="search" style="min-width:230px"><span class="material-icons" style="color:var(--muted)">search</span>'
   + '<input id="ovScQ" placeholder="Buscar nº da SC, produto ou obra…" value="'+esc(f.q)+'" '
   + 'oninput="OV.sc.filt.q=this.value;OV.sc.page=1;ovScRender()"></div>'
   + '<label class="muted" style="font-size:12px">Obra <select onchange="ovScSet(\'obra\',this.value)" style="margin-left:4px">'
   + '<option value="">Todas as obras</option>'+obrasSel+'</select></label>'
   + '<select onchange="ovScSet(\'status\',this.value)" style="font-size:12px;padding:6px"><option value="">Qualquer status</option>'
   + Object.keys(OV_SCST).map(k=>'<option value="'+k+'"'+(f.status===k?' selected':'')+'>'+OV_SCST[k][1]+'</option>').join('')+'</select>'
   + '<select onchange="ovScSet(\'situacao\',this.value)" style="font-size:12px;padding:6px" title="quanto da SC já foi cotado">'
   + '<option value="">Cotada ou não</option>'
   + Object.keys(OV_COB).map(k=>'<option value="'+k+'"'+(f.situacao===k?' selected':'')+'>'+OV_COB[k][1]+'</option>').join('')+'</select>'
   + '<select onchange="ovScSet(\'comprador\',this.value)" style="font-size:12px;padding:6px">'
   + '<option value="">Todos os compradores</option>'+opts(d.compradores||[], f.comprador)+'</select>'
   + '<select onchange="ovScSet(\'aging\',this.value)" style="font-size:12px;padding:6px"><option value="">Qualquer tempo</option>'
   + '<option value="15"'+(f.aging==='15'?' selected':'')+'>Há mais de 15 dias</option>'
   + '<option value="30"'+(f.aging==='30'?' selected':'')+'>Há mais de 30 dias</option></select>'
   + '<button class="btn-ghost" style="padding:6px 11px;font-size:12px" onclick="ovScLimpar()">Limpar filtros</button>'
   + '<span class="muted" style="font-size:11.5px;margin-left:auto">'+(tot?(ini+1):0)+'–'+(ini+pageRows.length)+' de '+tot
   + (tot!==todos.length?(' <span style="opacity:.75">(de '+todos.length+')</span>'):'')+'</span>'
   + '</div></div>';

  const arw=col=>OV.sc.sort.col===col?(OV.sc.sort.dir>0?' ▲':' ▼'):'';
  const th=(lbl,col,ex)=>'<th '+(ex||'')+' onclick="ovScSort(\''+col+'\')" style="cursor:pointer;user-select:none;white-space:nowrap">'+lbl+arw(col)+'</th>';
  /* Mesmo desenho da tela INTERNA de Solicitações (que já funciona bem), em modo leitura:
     ponto colorido da cobertura + nº da SC, contagem de itens, descrição do 1º item, obra,
     emissão, pílula de dias, status, comprador e a anotação dele. Reusa SOL_COT/SOL_ST/SOL_BK e
     solCotDot/solPill do app04 — duas réguas de cor para a mesma coisa acabariam divergindo. */
  h+='<div class="wrap" style="overflow-x:auto"><table style="width:100%;font-size:12px"><thead><tr>'
   + th('SC','numero') + th('Itens','itens','style="text-align:center"')
   + '<th>Descrição</th>' + th('Obra','obra')
   + th('Emissão','emissao') + th('Dias','dias_em_aberto','style="text-align:center" title="dias desde a emissão da solicitação"')
   + th('Status','status','style="text-align:center"') + th('Comprador','comprador')
   + '<th>Observações</th><th>Cotações</th></tr></thead><tbody>';

  for(const sc of pageRows){
    const key=sc.coligada+'|'+sc.numero, ex=(OV.sc.aberta===key);
    const st=(typeof SOL_ST!=='undefined'&&SOL_ST[sc.status])||['#8a9299',sc.status_texto||sc.status,'#f2f4f5'];
    const dias=sc.dias_em_aberto;
    const bk = dias===null?'r':(dias<7?'r':(dias<15?'a':(dias<30?'l':'c')));
    const pill=(typeof solPill==='function')?solPill({bucket:bk,dias:dias})
      :('<span class="dchip">'+(dias!=null?dias+' dias':'—')+'</span>');
    const dot=(k,extra)=>(typeof solCotDot==='function')?solCotDot(k,extra)
      :'<span style="display:inline-block;width:9px;height:9px;border-radius:50%;background:#8a9299;margin-right:6px"></span>';
    const prim=((sc.itens||[])[0]||{}).produto||'';
    const obs=sc.observacoes||'';
    h+='<tr>'
     + '<td><b style="cursor:pointer;white-space:nowrap" onclick="ovScToggle('+jsArg(key)+')">'+(ex?'▾':'▸')+' '
     +   dot(sc.cotacao_situacao, sc.itens_cotados+'/'+sc.itens_total+' itens')
     +   esc(String(sc.numero).replace(/^0+/,'')||sc.numero)+'</b></td>'
     + '<td style="text-align:center">'+sc.itens_total+'</td>'
     + '<td style="max-width:230px"><span title="'+esc(prim)+'">'+esc(prim.slice(0,44))+(prim.length>44?'…':'')+'</span></td>'
     + '<td class="muted" style="font-size:11.5px">'+esc(sc.obra||'—')+'</td>'
     + '<td class="muted" style="font-size:11.5px;white-space:nowrap">'+ovData(sc.emissao)+'</td>'
     + '<td style="text-align:center">'+pill+'</td>'
     + '<td style="text-align:center;background:'+(st[2]||'')+';border-left:3px solid '+st[0]+'">'
     +   '<span class="dchip" style="background:'+st[0]+';color:#fff;font-weight:700">'+esc(st[1])+'</span></td>'
     + '<td class="muted" style="font-size:11.5px">'+esc(sc.comprador||'—')+'</td>'
     /* anotação do comprador: aqui é SÓ LEITURA — a tela da obra não escreve nada */
     + '<td class="muted" style="font-size:11.5px;max-width:170px"><span title="'+esc(obs)+'">'
     +   (obs?esc(obs.slice(0,34))+(obs.length>34?'…':''):'—')+'</span></td>'
     + '<td style="white-space:nowrap">'+((sc.cotacoes||[]).length
        ? sc.cotacoes.map(x=>'<button class="btn-ghost" style="padding:2px 7px;color:var(--verde-d);font-weight:700;font-size:11px" '
            +'title="'+esc(x.titulo||'')+'" onclick="ovCotAbrir('+x.cotacao_id+')">#'+x.cotacao_id+'</button>').join('')
        : '<span class="muted">—</span>')+'</td></tr>';
    if(ex){
      h+='<tr><td colspan="10" style="background:#fafbfb;padding:8px 14px">'
       + '<b style="font-size:11px;color:var(--muted)">ITENS</b> '
       + '<span class="muted" style="font-size:10px">⚪ sem cotação · 🟡 em cotação · 🟢 cotada</span>'
       + (sc.itens||[]).map(it=>'<div style="font-size:12px;padding:2px 0">'
           + dot(it.situacao==='coberto'?'coberto':(it.situacao==='cotando'?'cotando':'vazio'))
           + (it.quantidade!=null?(cotNum(it.quantidade)+' '+esc(it.unidade||'')+' — '):'')
           + '<b>'+esc(it.produto)+'</b>'
           + (it.cotacao_id?(' <button class="btn-ghost" style="padding:0 5px;color:var(--verde-d);font-size:10px;font-weight:700;vertical-align:1px" '
               +'onclick="ovCotAbrir('+it.cotacao_id+')">#'+it.cotacao_id+'</button>'):'')
           + (it.observacao?(' <span class="muted">('+esc(it.observacao)+')</span>'):'')
           + '</div>').join('')
       + (!(sc.itens||[]).length?'<div class="dmini">Sem itens.</div>':'')
       + '</td></tr>';
    }
  }
  if(!rows.length) h+='<tr><td colspan="10" class="empty">'+(todos.length?'Nenhuma solicitação casa os filtros.':'Nenhuma solicitação em aberto para esta obra.')+'</td></tr>';
  h+='</tbody></table></div>';
  if(pgs>1){
    const b=(p,lbl,cur)=>'<button class="'+(cur?'btn-prim':'btn-ghost')+'" style="padding:5px 10px;font-size:12px;min-width:34px" onclick="ovScPagina('+p+')">'+lbl+'</button>';
    const n=[], a=Math.max(1,OV.sc.page-2), z=Math.min(pgs,OV.sc.page+2);
    if(a>1){ n.push(b(1,'1',false)); if(a>2)n.push('<span class="muted" style="padding:0 2px">…</span>'); }
    for(let p=a;p<=z;p++) n.push(b(p,String(p),p===OV.sc.page));
    if(z<pgs){ if(z<pgs-1)n.push('<span class="muted" style="padding:0 2px">…</span>'); n.push(b(pgs,String(pgs),false)); }
    h+='<div class="bar" style="justify-content:center;gap:6px;margin-top:10px;flex-wrap:wrap;align-items:center">'
     + b(Math.max(1,OV.sc.page-1),'‹',false)+n.join('')+b(Math.min(pgs,OV.sc.page+1),'›',false)
     + '<span class="muted" style="font-size:11.5px;margin-left:8px">página '+OV.sc.page+' de '+pgs+'</span></div>';
  }
  h+='<div class="dmini" style="margin-top:10px;color:var(--muted)">Somente consulta — clique na linha para ver os itens. '
   + 'Quem cota é o comprador responsável.</div>';
  const foc=document.activeElement, eraQ=foc&&foc.id==='ovScQ', car=eraQ?foc.selectionStart:null;
  w.innerHTML=h;
  if(eraQ){ const ni=document.getElementById('ovScQ'); if(ni){ ni.focus(); try{ ni.setSelectionRange(car,car); }catch(e){} } }
}
/* ─────────────────────────── RADAR (Status - Curva A e B) ───────────────────────────
   STATUS e PRAZO são coisas diferentes e agora têm colunas próprias: o status diz em que pé está
   a compra (não iniciou / em cotação / concluído), o prazo diz se está atrasado. Misturar os dois
   num chip só era o que confundia — "Concluído" em cinza no meio de prazos verdes não se lia. */

const OV_STATUS = {
  'Não Iniciado':     ['#8a9299', 'Não iniciado'],
  'Cotação Iniciada': ['var(--dourado)', 'Em cotação'],
  'Em Andamento':     ['#2d7d5a', 'Em andamento'],
  'Com Pendências':   ['#e67e22', 'Com pendências'],
  'Finalizado':       ['var(--ok)', 'Concluído'],
  'Não se aplica':    ['#c9ced2', 'Não se aplica'],
};
function ovChipStatus(l){
  const x = OV_STATUS[l.status] || ['#8a9299', l.status || '—'];
  const auto = l.status_automatico ? ' title="deduzido pela existência de uma cotação — ninguém digitou este status"' : '';
  return '<span class="dchip" style="background:'+x[0]+'"'+auto+'>'+esc(x[1])+(l.status_automatico?' •':'')+'</span>';
}

/* COLUNAS CONFIGURÁVEIS — o pessoal da obra não precisa das mesmas colunas que suprimentos.
   A escolha fica no navegador de cada um (localStorage), não no cadastro: é preferência de tela. */
const OV_COLS = [
  {k:'item',   lbl:'Item',           fixa:true},
  {k:'obra',   lbl:'Obra'},
  {k:'grupo',  lbl:'Grupo'},
  {k:'status', lbl:'Status'},
  {k:'prazo',  lbl:'Prazo'},
  {k:'cotar',  lbl:'Cotar até'},
  {k:'emobra', lbl:'Prazo em obra'},
  {k:'verba',  lbl:'Verba'},
  {k:'resp',   lbl:'Responsável'},
  {k:'cot',    lbl:'Cotação'},
];
const OV_COLS_PADRAO = ['item','obra','grupo','status','prazo','cotar','emobra','verba','resp','cot'];
function ovColsGet(){
  if(OV.radar.cols) return OV.radar.cols;
  let v=null; try{ v=JSON.parse(localStorage.getItem('sup_ovradar_cols')||'null'); }catch(e){}
  OV.radar.cols = Array.isArray(v)&&v.length ? v.filter(k=>OV_COLS.some(c=>c.k===k)) : OV_COLS_PADRAO.slice();
  if(!OV.radar.cols.includes('item')) OV.radar.cols.unshift('item');
  return OV.radar.cols;
}
function ovColsSalvar(){ try{ localStorage.setItem('sup_ovradar_cols', JSON.stringify(OV.radar.cols||[])); }catch(e){} }
function ovColsVisivel(k){ return ovColsGet().includes(k); }
function ovColsAbrir(){
  const on=ovColsGet();
  dlgAbrir('Status - Curva A e B','Escolher colunas',
    '<div style="max-width:420px"><div class="dmini" style="margin-bottom:10px">Desmarque o que não quiser ver. '
   + 'Vale só para você, neste navegador.</div>'
   + '<div style="display:grid;grid-template-columns:1fr 1fr;gap:6px">'
   + OV_COLS.map(c=>'<label style="display:flex;align-items:center;gap:7px;font-size:12.5px;padding:5px 8px;border:1px solid var(--line);border-radius:8px'
       + (c.fixa?';opacity:.55':'')+'">'
       + '<input type="checkbox" '+(on.includes(c.k)?'checked ':'')+(c.fixa?'disabled ':'')
       + 'onchange="ovColsToggle(\''+c.k+'\',this.checked)"> '+esc(c.lbl)+(c.fixa?' <span class="muted" style="font-size:10px">(fixa)</span>':'')+'</label>').join('')
   + '</div>'
   + '<div class="bar" style="justify-content:space-between;gap:8px;margin-top:14px">'
   + '<button class="btn-ghost" onclick="ovColsReset()">Restaurar padrão</button>'
   + '<button class="btn-prim" onclick="closeModal(true)">Pronto</button></div></div>');
}
function ovColsToggle(k,on){
  const L=ovColsGet();
  if(on){ if(!L.includes(k)) OV.radar.cols=OV_COLS.map(c=>c.k).filter(x=>L.includes(x)||x===k); }
  else   { OV.radar.cols=L.filter(x=>x!==k); }
  ovColsSalvar(); ovRadarRender();
}
function ovColsReset(){ OV.radar.cols=OV_COLS_PADRAO.slice(); ovColsSalvar(); closeModal(true); ovRadarRender(); }

/* ── "olhinho" do prazo em obra: de onde saiu a data e como o resto foi calculado ── */
function ovVerCrono(idx){
  const l=(OV.radar._pag||[])[idx]; if(!l) return;
  const ORIG={curada:'digitada por suprimentos (sobrepõe o cronograma)',
              cronograma:'veio do cronograma vivo do Planejamento',
              'sem data':'não há data — nem no cronograma, nem digitada'};
  dlgAbrir('Status - Curva A e B','De onde vem o prazo · '+esc(l.item),
    '<div style="max-width:560px">'
   + (l.data_em_obra
      ? '<div style="font-size:15px;font-weight:800;margin-bottom:2px">'+ovData(l.data_em_obra)+'</div>'
        + '<div class="dmini" style="margin-bottom:12px">data em que o item precisa estar em obra · '
        + esc(ORIG[l.data_em_obra_origem]||l.data_em_obra_origem||'')+'</div>'
      : '<div style="font-size:15px;font-weight:800;color:#8a9299;margin-bottom:12px">sem data em obra</div>')
   + (l.marco_cronograma?('<div class="dmini" style="margin-bottom:10px"><b>Marco do cronograma:</b> '+esc(l.marco_cronograma)+'</div>'):'')
   + '<div style="border:1px solid var(--line);border-radius:9px;overflow:hidden">'
   + [['Necessário em obra', ovData(l.data_em_obra), 'a data-alvo'],
      ['− lead time de '+(l.lead_dias||0)+' dias', ovData(l.fim_cotacao), 'prazo final para a cotação estar fechada'],
      ['− 30 dias', ovData(l.inicio_cotacao), 'quando a cotação precisa começar']]
      .map((r,i)=>'<div style="display:flex;justify-content:space-between;gap:10px;padding:8px 12px;'
        +(i<2?'border-bottom:1px solid #f1f3f2;':'')+(i===0?'background:#f8faf9':'')+'">'
        +'<span style="font-size:12.5px">'+esc(r[0])+'<div class="dmini" style="color:var(--muted)">'+esc(r[2])+'</div></span>'
        +'<b style="white-space:nowrap">'+r[1]+'</b></div>').join('')
   + '</div>'
   + '<div class="dmini" style="margin-top:10px;color:var(--muted)">O lead time é o tempo entre fechar a compra e o item chegar/começar. '
   + 'Quem ajusta essas datas é suprimentos, no radar.</div>'
   + '<div class="bar" style="justify-content:flex-end;margin-top:12px"><button class="btn-prim" onclick="closeModal(true)">Fechar</button></div></div>');
}

/* ── "olhinho" da verba: quais linhas/insumos do orçamento formaram o valor ── */
async function ovVerVerba(idx){
  const l=(OV.radar._pag||[])[idx]; if(!l) return;
  const v = l.verba_definida!=null ? l.verba_definida : l.verba_estimada;
  dlgAbrir('Status - Curva A e B','Como a verba foi apurada · '+esc(l.item),
    '<div style="max-width:640px"><div class="dempty">Buscando a memória de cálculo…</div></div>');
  let d=null;
  try{ d=await (await fetch('actions/obra_consulta.php?tela=verba&ordem='+l.item_id+'&obra_id='+l.obra_id+'&me='+ovMe())).json(); }catch(e){}
  const bd=(d&&d.dados)||null;
  let h='<div style="max-width:640px">';
  h+='<div style="font-size:17px;font-weight:800">'+(v!=null?BRL(v):'—')+'</div>'
   + '<div class="dmini" style="margin-bottom:12px">'
   + (l.verba_definida!=null
      ? ('verba definida'+(l.verba_confirmada?' e conferida por suprimentos':' (ainda não conferida)'))
      : (l.verba_estimada!=null?'estimativa herdada do orçamento — ainda não definida':'sem verba'))
   + '</div>';
  if(!bd || (!(bd.linhas||[]).length && !bd.total)){
    h+='<div class="dempty">Esta verba não veio do orçamento analítico — foi digitada direto, então não há '
     + 'memória de cálculo para abrir.'+((d&&(d.error||d.erro))?('<div class="dmini" style="margin-top:6px">'+esc(d.error||d.erro)+'</div>'):'')+'</div>';
  }else{
    const T=bd.tot_por_tipo||{};
    const rot={material:'Material', mo:'Mão de obra', mat_mo:'Material + MO', equip:'Equipamento'};
    const tot=Object.keys(rot).filter(k=>+T[k]);
    if(tot.length) h+='<div class="bar" style="gap:8px;flex-wrap:wrap;margin-bottom:10px">'
      + tot.map(k=>'<div class="panel" style="flex:1;min-width:110px;padding:8px 11px"><div class="dmini" style="color:var(--muted)">'
        + rot[k]+'</div><b>'+BRL(T[k])+'</b></div>').join('')+'</div>';
    h+='<div class="wrap" style="max-height:330px;overflow:auto"><table style="width:100%;font-size:12px"><thead><tr>'
     + '<th>Linha do orçamento</th><th style="text-align:right">Valor</th></tr></thead><tbody>';
    for(const ln of (bd.linhas||[])){
      h+='<tr><td><b>'+esc(ln.descricao||'—')+'</b>'
       + (ln.path?('<div class="dmini" style="color:var(--muted)">'+esc(ln.path)+'</div>'):'')
       + (ln.sem_composicao?'<div class="dmini" style="color:var(--dourado)">sem composição detalhada</div>':'')
       + ((ln.insumos||[]).length?('<div class="dmini" style="color:var(--muted)">'+ln.insumos.length+' insumo(s): '
           + esc(ln.insumos.slice(0,4).map(x=>x.desc).join(' · '))+(ln.insumos.length>4?' …':'')+'</div>'):'')
       + '</td><td style="text-align:right;white-space:nowrap"><b>'+BRL(ln.valor||0)+'</b></td></tr>';
    }
    h+='</tbody><tfoot><tr style="border-top:2px solid var(--line)"><td><b>TOTAL</b></td>'
     + '<td style="text-align:right"><b>'+BRL(bd.total||0)+'</b></td></tr></tfoot></table></div>';
  }
  h+='<div class="dmini" style="margin-top:10px;color:var(--muted)">Tela de consulta — quem ajusta a verba é suprimentos.</div>'
   + '<div class="bar" style="justify-content:flex-end;margin-top:12px"><button class="btn-prim" onclick="closeModal(true)">Fechar</button></div></div>';
  dlgAbrir('Status - Curva A e B','Como a verba foi apurada · '+esc(l.item), h);
}
