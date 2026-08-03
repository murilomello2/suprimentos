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
  const p=new URLSearchParams({tela:'radar', me:decodeURIComponent(ovMe()), por_pagina:'500'});
  if(f.obra) p.set('obra_id', f.obra);
  if(recarregar) p.set('recarregar','1');
  try{
    const d=await (await fetch('actions/obra_consulta.php?'+p.toString())).json();
    if(d.error){ w.innerHTML='<div class="dempty">'+esc(d.error)+'</div>'; return; }
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
    data_em_obra:(l.data_em_obra||'9999-12-31'), inicio_cotacao:(l.inicio_cotacao||'9999-12-31')}[sc]);
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

  let h=cotSecHead('radar','O que vem por aí',
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
   + '<span class="muted" style="font-size:11.5px;margin-left:auto">'+(tot?(ini+1):0)+'–'+(ini+pageRows.length)+' de '+tot
   + (tot!==todos.length?(' <span style="opacity:.75">(de '+todos.length+')</span>'):'')+'</span>'
   + '</div></div>';

  const arw=col=>OV.radar.sort.col===col?(OV.radar.sort.dir>0?' ▲':' ▼'):'';
  const th=(lbl,col,extra)=>'<th '+(extra||'')+' onclick="ovRadarSort(\''+col+'\')" style="cursor:pointer;user-select:none;white-space:nowrap">'+lbl+arw(col)+'</th>';

  h+='<div class="wrap" style="overflow-x:auto"><table style="width:100%;font-size:12px"><thead><tr>'
   + th('Item','item') + th('Obra','obra') + th('Grupo','grupo')
   + th('Prazo','alerta','title="situação do prazo de cotação"')
   + th('Começar a cotar','inicio_cotacao')
   + th('Material em obra','data_em_obra','title="data em que o material precisa estar na obra"')
   + th('Comprador','responsavel')
   + '<th>Cotação</th></tr></thead><tbody>';

  for(const l of pageRows){
    const dias=ovDias(l.data_em_obra);
    const corDias = dias===null ? 'var(--muted)' : (dias<0 ? '#c0392b' : (dias<=30 ? 'var(--dourado)' : 'var(--muted)'));
    const txtDias = dias===null ? '' : (dias<0 ? ('há '+Math.abs(dias)+'d') : ('em '+dias+'d'));
    const cot=l.cotacao;
    h+='<tr>'
     + '<td style="max-width:280px"><div style="font-weight:700">'+esc(l.item)+'</div>'
     +   (l.fornecedor?('<div class="dmini" style="color:var(--muted)">'+esc(l.fornecedor)+'</div>'):'')+'</td>'
     + '<td class="muted">'+esc(l.obra||'—')+'</td>'
     + '<td class="muted" style="max-width:150px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="'+esc(l.grupo||'')+'">'+esc(l.grupo||'—')+'</td>'
     + '<td>'+ovChipAlerta(l.alerta)+'</td>'
     + '<td class="muted" style="white-space:nowrap">'+ovData(l.inicio_cotacao)+'</td>'
     + '<td style="white-space:nowrap"><b>'+ovData(l.data_em_obra)+'</b>'
     +   (txtDias?('<div class="dmini" style="color:'+corDias+'">'+txtDias+'</div>'):'')
     +   (l.data_em_obra_origem==='sem data'?'<div class="dmini" style="color:var(--muted)">sem cronograma</div>':'')+'</td>'
     + '<td class="muted" style="white-space:nowrap">'+esc(l.responsavel||'—')+'</td>'
     + '<td style="white-space:nowrap">'+(cot
        ? ('<button class="btn-ghost" style="padding:2px 8px;font-size:11px" onclick="ovCotAbrir('+cot.cotacao_id+')" '
           +'title="'+esc(cot.titulo||'')+' — '+esc(cot.status_texto||'')+'">'
           +cot.propostas_recebidas+'/'+cot.fornecedores_convidados+' propostas'
           +(cot.melhor_oferta?(' · '+BRL(cot.melhor_oferta)):'')+'</button>')
        : '<span class="muted">—</span>')+'</td></tr>';
  }
  if(!rows.length) h+='<tr><td colspan="8" class="empty">'+(todos.length?'Nenhum item casa os filtros.':'Nenhum item no radar desta obra.')+'</td></tr>';
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

/* placeholder até a tela de cotações da obra existir (etapa 2.2) */
function ovCotAbrir(id){ toast('Detalhe da cotação #'+id+' — em construção'); }
