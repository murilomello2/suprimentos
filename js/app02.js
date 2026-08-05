/* Cockpit de Suprimentos — parte 2 de 6 do aplicativo.
   Gerado a partir do bloco unico que vivia dentro do index.php: 857 KB num arquivo so faziam
   cada deploy levar de 5 a 10 minutos e falhar calado. O corte respeita fronteiras de nivel
   superior e cada parte foi validada pelo parser antes de existir. A ORDEM importa: os
   arquivos sao carregados na sequencia em que foram cortados. */
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
  const selecionaveis=items.filter(rselPode);
  const todosSel=selecionaveis.length>0&&selecionaveis.every(i=>RSEL.has((i.obra_id||1)+':'+i.ordem));
  const gcb=selecionaveis.length?`<input type="checkbox" ${todosSel?'checked':''} title="marcar/desmarcar o grupo inteiro (${selecionaveis.length} selecionáveis)" onclick="event.stopPropagation();rselGrupoIdx(${idx},this.checked)" style="width:auto;accent-color:var(--verde);margin-right:4px;vertical-align:-2px">`:'';
  return `<tr class="grp" onclick="toggleGroup(${idx})"><td colspan="12"><span class="gwrap">
      ${gcb}<span class="material-icons gcaret">${collapsed?'chevron_right':'expand_more'}</span>
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
    (!fs||(i.status||'Não Iniciado')===fs)&&(!fr||nrmResp(i.responsavel)===nrmResp(fr))&&(!oa||isAlert(i))&&
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
  RVIS=rows;   // itens VISÍVEIS (pós-filtro) — base do "marcar todos" e do marcar-grupo
  tb.innerHTML=html;
  updateCollapseBtn(); updateSortArrows(); fitRadarHeight(); rselBarUpd();
}
/* ===== SELEÇÃO EM LOTE no radar (conferência de status — 21/jul/2026) =====
   Comprador não-admin/não-gerente só seleciona (e o servidor só grava) itens onde ELE é o responsável. */
let RSEL=new Map(), RVIS=[];
// pode editar status/fornecedor/observação de um ITEM do radar = admin | gerente | RESPONSÁVEL do item.
// NÃO depende de "Edita obras" (decisão 23/jul: edição de obra é só p/ o menu Obras/estrutura, não p/ o comprador).
function podeEditarItem(i){
  if(IS_ADMIN||(((EU&&EU.papel)||'')==='gerente')) return true;
  const eu=nrmResp((EU&&EU.nome)||'').toLowerCase();
  return !!eu && nrmResp(i&&i.responsavel).toLowerCase()===eu;
}
function rselPode(i){ return podeEditarItem(i); }
function rselToggle(k,on){
  if(on){ const [ob,ord]=k.split(':'); RSEL.set(k,{obra_id:Number(ob),ordem:Number(ord)}); }
  else RSEL.delete(k);
  rselBarUpd();
}
function rselGrupoIdx(idx,on){
  const g=GORDER[idx]; RVIS.filter(i=>(i.grupo||'—')===g&&rselPode(i)).forEach(i=>{
    const k=(i.obra_id||1)+':'+i.ordem; if(on) RSEL.set(k,{obra_id:i.obra_id||1,ordem:i.ordem}); else RSEL.delete(k); });
  render();
}
function rselTodosVisiveis(on){
  RVIS.filter(rselPode).forEach(i=>{ const k=(i.obra_id||1)+':'+i.ordem; if(on) RSEL.set(k,{obra_id:i.obra_id||1,ordem:i.ordem}); else RSEL.delete(k); });
  render();
}
function rselLimpar(){ RSEL.clear(); const a=document.getElementById('rselAll'); if(a) a.checked=false; render(); }
function rselBarUpd(){
  const b=document.getElementById('rselBar'); if(!b) return;
  const n=RSEL.size;
  if(!n){ b.style.display='none'; return; }
  const podeResp=IS_ADMIN||(((EU&&EU.papel)||'')==='gerente');
  const STS=['Não Iniciado','Cotação Iniciada','Com Pendências','Em Andamento','Finalizado','Não se aplica'];
  const respOpts=((typeof RESP!=='undefined'&&RESP)||[]).map(r=>`<option>${esc(r.nome)}</option>`).join('');
  const stSel=document.getElementById('rselSt')?val('rselSt'):'Finalizado';   // preserva escolha entre re-renders
  const foVal=document.getElementById('rselFo')?document.getElementById('rselFo').value:'';
  const reVal=document.getElementById('rselRe')?val('rselRe'):'';
  b.style.display='flex';
  b.innerHTML=`
    <b style="font-size:13.5px">${n} selecionado${n>1?'s':''}</b>
    <button class="btn-ghost" style="padding:4px 9px;font-size:12px" onclick="rselLimpar()">✕ limpar</button>
    <span style="width:1px;height:24px;background:var(--line)"></span>
    <select id="rselSt" style="padding:6px 8px;border:1px solid var(--line);border-radius:7px;font-size:12.5px">${STS.map(s=>`<option ${s===stSel?'selected':''}>${s}</option>`).join('')}</select>
    <button class="btn-prim" style="padding:6px 11px;font-size:12.5px" onclick="rselAplicar('status')">Aplicar status</button>
    <span style="width:1px;height:24px;background:var(--line)"></span>
    <input id="rselFo" value="${esc(foVal)}" placeholder="fornecedor…" style="width:150px;padding:6px 8px;border:1px solid var(--line);border-radius:7px;font-size:12.5px">
    <button class="btn-ghost" style="padding:6px 10px;font-size:12.5px" onclick="rselAplicar('fornecedor')">Aplicar</button>
    ${podeResp?`<span style="width:1px;height:24px;background:var(--line)"></span>
    <select id="rselRe" style="padding:6px 8px;border:1px solid var(--line);border-radius:7px;font-size:12.5px"><option value="">responsável…</option>${respOpts}</select>
    <button class="btn-ghost" style="padding:6px 10px;font-size:12.5px" onclick="rselAplicar('responsavel')">Atribuir</button>`:''}`;
  if(reVal){ const e=document.getElementById('rselRe'); if(e) e.value=reVal; }
}
async function rselAplicar(campo){
  const itens=[...RSEL.values()].map(x=>({obra_id:x.obra_id,servico_id:x.ordem}));
  if(!itens.length) return;
  const campos={};
  if(campo==='status') campos.status=val('rselSt');
  else if(campo==='fornecedor'){ const f=(document.getElementById('rselFo').value||'').trim(); if(!f){toast('Digite o fornecedor');return;} campos.fornecedor=f; }
  else { campos.responsavel=val('rselRe'); if(!campos.responsavel){toast('Escolha o responsável');return;} }
  const rot=campo==='status'?('status → '+campos.status):campo==='fornecedor'?('fornecedor → '+campos.fornecedor):('responsável → '+campos.responsavel);
  if(!confirm('Aplicar '+rot+' em '+itens.length+' item(ns)? Cada mudança fica no histórico.')) return;
  try{
    const r=await (await fetch('actions/status_lote.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({me:EU&&EU.bitrix_id,itens,campos})})).json();
    if(r.error){toast(r.error);return;}
    const ks=new Set(itens.map(x=>x.obra_id+':'+x.servico_id));
    DATA.itens.forEach(i=>{ if(ks.has((i.obra_id||1)+':'+i.ordem)) Object.assign(i,campos); });
    toast(`${r.aplicados} aplicado(s)`+(r.sem_permissao?` · ${r.sem_permissao} sem permissão (não é o responsável)`:'')+(r.sem_mudanca?` · ${r.sem_mudanca} já estavam assim`:''));
    RSEL.clear(); const a=document.getElementById('rselAll'); if(a) a.checked=false;
    render(); try{ renderKpis(); }catch(e){} try{ renderMatriz(); }catch(e){}
  }catch(e){ toast('Falha: '+e.message); }
}
function rowHtml(i){
  const st=i.status||'Não Iniciado';
  const lvl=alertLevel(i);
  const chipIni=lvl==='atrasado'?`<span class="tag-al atras">atrasado</span>`:lvl==='proximo'?`<span class="tag-al prox">iniciar</span>`:'';
  const chipFim=lvl==='critico'?`<span class="tag-al crit">crítico</span>`:lvl==='finalizado'?`<span class="tag-al fin">✓ concluído</span>`:'';
  const obTag=(OBRA_SEL.length>1)?`<span style="display:inline-block;font-size:9px;font-weight:800;color:#fff;background:${obraCor(i.obra_id)};border-radius:4px;padding:1px 6px;vertical-align:1px;margin-right:4px">${esc((i.obra_nome||'').slice(0,10))}</span>`:'';
  const _rk=(i.obra_id||1)+':'+i.ordem, _rp=rselPode(i);
  const _rcb=`<input type="checkbox" class="rselcb" ${RSEL.has(_rk)?'checked':''} ${_rp?'':'disabled'} title="${_rp?'selecionar p/ ação em lote':('só o responsável altera — '+(i.responsavel?esc(i.responsavel):'sem responsável definido'))}" onclick="event.stopPropagation();rselToggle('${_rk}',this.checked)" style="width:auto;accent-color:var(--verde);margin-right:7px;vertical-align:-2px;${_rp?'':'opacity:.35'}">`;
  return `<tr class="item" onclick="openModal(${i.ordem},${i.obra_id||1})">
    <td><div class="svc">${_rcb}${obTag}${esc(i.nome)} ${tipoChip(i.tipo)}</div></td>
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
/* ───────── UM ITEM, VÁRIAS COTAÇÕES ─────────
   O item do radar quase nunca vira uma cotação só: "Grua com operador" costuma virar uma cotação de
   LOCAÇÃO e outra de OPERADOR; "prego + arame" vira uma direto de FÁBRICA e outra de DISTRIBUIDOR.
   O vínculo (cotacao.servico_id) sempre aceitou vários — o que faltava era a tela dizer que existem e
   deixar escolher. Quem diferencia é o TÍTULO (ou o apelido) de cada cotação. */
const COT_ST={aberta:['var(--cot)','em cotação'],aguardando:['var(--dourado)','aguardando'],finalizada:['var(--ok)','fechada']};
function radCotLista(i){ const c=i&&i.cotacao; if(!c) return []; return (c.lista&&c.lista.length)?c.lista:[c]; }
// clique no chip/botão: com uma cotação abre direto; com várias, pergunta qual
function radCotIr(ordem,obraId){
  const i=byOrdem(ordem,obraId), L=radCotLista(i);
  if(!L.length) return;
  if(L.length===1){ cotAbrir(L[0].id); return; }
  dlgAbrir('Radar de Aquisições','Cotações deste item',
    '<div style="max-width:600px">'
   + '<div class="dmini" style="margin-bottom:10px">Este item tem <b>'+L.length+' cotações</b> vinculadas — '
   + 'cada uma cobre uma parte dele (ex.: locação × operador, fábrica × distribuidor). Escolha qual mapa abrir.</div>'
   + '<div style="display:flex;flex-direction:column;gap:7px">'
   + L.map(c=>{ const x=COT_ST[c.status]||['var(--cot)','em cotação'];
       return '<div onclick="closeModal(true);cotAbrir('+c.id+')" style="border:1px solid var(--line);border-radius:9px;padding:9px 12px;cursor:pointer" onmouseover="this.style.background=\'#f3f7f5\'" onmouseout="this.style.background=\'\'">'
        + '<div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap"><b style="font-size:13px">'+esc(c.titulo||('cotação #'+c.id))+'</b>'
        + (c.apelido?'<span class="dchip" style="background:#eef6f0;color:var(--verde-d)">'+esc(c.apelido)+'</span>':'')
        + '<span style="margin-left:auto;color:'+x[0]+';font-weight:800;font-size:11.5px">● '+x[1]+'</span></div>'
        + '<div class="muted" style="font-size:11px;margin-top:2px">'+(c.respostas||0)+'/'+(c.convidados||0)+' responderam'
        + (c.melhor?(' · melhor '+BRL(c.melhor)):'')+' · #'+c.id+'</div></div>'; }).join('')
   + '</div></div>');
}
// dentro do card do item: com uma cotação, o botão de sempre; com várias, o painel que lista todas
function radCotBotoes(i){
  const L=radCotLista(i); if(!L.length) return '';
  if(L.length===1){ const c=L[0];
    return `<button class="btn-ghost" onclick="cotAbrir(${c.id})" title="${esc(c.titulo||'')}"><span class="material-icons" style="font-size:16px;vertical-align:-3px;color:var(--verde)">request_quote</span> Ver mapa de cotação</button>`
     + `<span class="muted" style="font-size:11.5px">· ${c.respostas||0}/${c.convidados||0} responderam${c.melhor?' · melhor '+BRL(c.melhor):''}</span>`;
  }
  return `<div style="flex-basis:100%;border:1px solid var(--line);border-radius:9px;padding:9px 11px;background:#fbfcfc">
    <div class="muted" style="font-size:11px;font-weight:700;margin-bottom:7px">${L.length} COTAÇÕES NESTE ITEM <span style="font-weight:400">— cada uma cobre uma parte dele (ex.: locação × operador, fábrica × distribuidor)</span></div>
    <div style="display:flex;flex-direction:column;gap:6px">${L.map(c=>{ const x=COT_ST[c.status]||['var(--cot)','em cotação'];
      return `<div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap">
        <span style="color:${x[0]};font-weight:800;font-size:11px;min-width:78px">● ${x[1]}</span>
        <b style="font-size:12.5px">${esc(c.titulo||('cotação #'+c.id))}</b>
        ${c.apelido?`<span class="dchip" style="background:#eef6f0;color:var(--verde-d)">${esc(c.apelido)}</span>`:''}
        <span class="muted" style="font-size:11px">${c.respostas||0}/${c.convidados||0} responderam${c.melhor?' · melhor '+BRL(c.melhor):''}</span>
        <button class="btn-ghost" style="padding:2px 10px;margin-left:auto" onclick="cotAbrir(${c.id})">abrir ›</button>
      </div>`; }).join('')}</div></div>`;
}
// Coluna "Mapa": AUTOMÁTICA — reflete a existência REAL de um mapa de cotação vinculado ao item (servico_id).
function cotCell(i){
  const c=i.cotacao;
  if(c){
    const x=COT_ST[c.status]||['var(--cot)','em cotação'];
    const resp=(c.convidados||c.respostas)?` <span style="font-weight:600;opacity:.85">${c.respostas||0}/${c.convidados||0}</span>`:'';
    // com mais de um mapa, o número aparece no chip — senão o item parecia ter só a última cotação
    const nChip=(c.n>1)?` <span style="background:#eef4fb;color:#2a5d8f;font-size:9px;font-weight:800;padding:1px 5px;border-radius:5px">${c.n} mapas</span>`:'';
    return `<span onclick="event.stopPropagation();radCotIr(${i.ordem},${i.obra_id||1})" title="${c.n>1?('Este item tem '+c.n+' cotações — clique para escolher qual abrir'):('Abrir mapa de cotação — '+esc(c.titulo||''))}${c.melhor?(' · melhor '+BRL(c.melhor)):''}" style="cursor:pointer;color:${x[0]};font-weight:800;white-space:nowrap;font-size:11.5px">● ${x[1]}${resp}${nChip}</span>`;
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
    fill('fresp',[...new Set(DATA.itens.map(i=>nrmResp(i.responsavel)).filter(Boolean))]);
    render(); renderMatriz(); // reflete cor/valores nas duas visões na hora
    return true;
  }catch(e){toast('Falha ao salvar');return false;}
}

/* ---------- modal ---------- */
let EDITC=false, EDITO=false, EDITQ=false, EDITD=false, EDITR=false; // modos "Editar" (cronograma/orçamento/quantitativo/dicionário/resumo)
let IS_ADMIN=false;                       // fail-closed; vira true só quando getCurrentUser confirma perm_admin
let CAN_EDIT=false;                       // editor geral da obra (status/fornecedor/observação)
let CAN_FORN=false;                       // cadastrar/editar FORNECEDOR (lista-mestre): admin/gerente/comprador — não depende de edição de obra
let CAN_COT=false;                         // criar/gerir cotação: admin/gerente/comprador — dinâmica de suprimentos (não é edição de obra)
let CAN_CRONO=false, CAN_ORC=false, CAN_QUANT=false, CAN_DIC=false, CAN_RESP=false, CAN_EMAIL=false; // permissões específicas (vínculos + dicionário + responsáveis em lote)
let EU=null;                             // usuário logado + permissões efetivas
function openModal(o,ob){CUR=byOrdem(o,ob);if(!CUR)return;TAB='Resumo';EDITC=EDITO=EDITQ=EDITD=EDITR=false;drawModal();document.getElementById('ov').classList.add('open');hydrateCur();}
async function ensureFull(){ if(CUR && !CUR._full) await hydrateCur(); }   // garante os campos pesados antes de editar orçamento/quant
// PERF: a lista vem ENXUTA (sem composicao_sel/dicionário). Ao abrir o modal, hidrata o item completo via ?only=
// (~0,8s) e re-desenha. O Resumo aparece na hora; as abas pesadas (Orçamento/Quant/Dicionário) preenchem em seguida.
async function hydrateCur(){
  const cur=CUR; if(!cur || cur._full) return; const o=cur.ordem, oid=cur.obra_id||1;
  try{
    const mr=await (await fetch('actions/matriz.php?only='+o+'&_='+Date.now()+(oid!==1?('&obra='+oid):''),{cache:'no-store'})).json();
    if(mr&&mr.item){
      ['composicao_sel','quant_comp_sel','escopo','variaveis_cotar','licoes','documentos','quantitativo_txt','verba_linhas'].forEach(k=>{ cur[k]=mr.item[k]; });
      cur._full=true;
      if(CUR===cur && document.getElementById('ov').classList.contains('open')) drawModal();  // re-desenha se o modal ainda está no mesmo item
    }
  }catch(e){}
}
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
      if(!i.curado_data && i.data_necessaria && i.marco_casado) h+=`<button class="btn-ghost" style="color:var(--verde-d)" onclick="cronoConfirmar()" title="Confirma a tarefa-âncora sugerida (marca curado ✓) — sem precisar re-selecionar"><span class="material-icons" style="font-size:15px;vertical-align:-3px">check_circle</span> Confirmar</button>`;
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
// confirma o marco AUTO-sugerido como curado (✓) sem re-buscar — grava o override = a sugestão atual
async function cronoConfirmar(){ if(!CUR||!CUR.data_necessaria||!CUR.marco_casado){toast('Sem tarefa sugerida para confirmar');return;} EDITC=false; await saveAndReload({crono_marco_override:CUR.marco_casado, data_necessaria_override:CUR.data_necessaria}); toast('Cronograma confirmado ✓'); }

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
    CAN_QUANT ? `<button class="btn-prim" onclick="quantEditar()"><span class="material-icons" style="font-size:16px">link</span> Editar quantitativo</button>`+((i.quantitativo!=null||(i.quant_comp_sel&&i.quant_comp_sel.length)||i.quantitativo_fonte||i.curado_quant)?`<button class="btn-ghost" onclick="qntLimpar()">↺ Limpar</button>`:'')
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
async function quantEditar(){ await ensureFull(); EDITQ=true; VERBA_USOS=null; QNT_NODES=[];
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
async function qntLimpar(){ EDITQ=false; QNT_SEL.clear(); QCOMP_SEL=[]; await saveAndReload({quantitativo_valor:'', quantitativo_unidade:'', quantitativo_fonte:'', quant_refs:[], quant_comp_sel:[]}); toast('Quantitativo limpo'); }

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
async function orcEditar(){ await ensureFull(); EDITO=true; VERBA_USOS=null; ORC_NODES=[]; ORCFONTE=(CUR.verba_metodo==='composicao'?'composicao':'analitico'); COMP_SEL=(CUR.composicao_sel||[]).map(s=>({...s})); ORC_EXCL=(CUR.orcamento_excl||[]).map(e=>({l:Number(e.l),d:e.d})); COMP_DATA=null; drawModal(); }
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
async function orcLimpar(){ EDITO=false; ORC_SEL.clear(); ORC_EXCL=[]; COMP_SEL=[]; await saveAndReload({orcamento_refs:[], orcamento_excl:[], composicao_sel:[]}); toast('Composição limpa'); }
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
        ${(CAN_EDIT||podeEditarItem(i))?`<button class="btn-prim" onclick="EDITR=true;drawModal()"><span class="material-icons" style="font-size:16px;vertical-align:-3px">edit</span> Editar</button>`
                  :`<span class="muted" style="font-size:12.5px"><span class="material-icons" style="font-size:15px;vertical-align:-3px">lock</span> Você tem acesso somente leitura${(EU&&EU.papel==='comprador')?' — este item está sob responsabilidade de '+esc(i.responsavel||'ninguém'):''}.</span>`}
        ${radCotBotoes(i)}
        ${CAN_COT?`<button class="btn-ghost" onclick="cotIniciar(${i.ordem},${i.obra_id||1})" title="Abre uma cotação já com os itens do dicionário deste serviço (editáveis)"><span class="material-icons" style="font-size:16px;vertical-align:-3px;color:var(--dourado)">request_quote</span> ${i.cotacao?'Nova cotação':'Iniciar cotação'}</button>`:''}
        ${/* O MESMO vínculo pelo outro lado: muita cotação já existe (criada do zero ou importada do
              sistema antigo) e o que falta é só amarrar. Quem está no item é quem sabe qual é. */''}
        ${CAN_COT?`<button class="btn-ghost" onclick="radVincCot(${i.ordem},${i.obra_id||1},${jsArg(i.nome||'')})" title="A cotação já existe? Amarre este item a ela em vez de criar outra"><span class="material-icons" style="font-size:16px;vertical-align:-3px;color:var(--verde)">add_link</span> Vincular a cotação existente</button>`:''}
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
    ${a?`<div class="fld"><label>Nome do item${i.nome_override?` <span class="muted" style="text-transform:none;letter-spacing:0;font-weight:400">— só nesta obra · base: ${esc(i.nome_base||'')}</span>`:''}</label><input id="rNome" value="${esc(i.nome||'')}"></div>`
       :`<div class="fld"><label>Nome do item</label><input value="${esc(i.nome||'')}" disabled></div>`}
    <div class="grid2">
      ${a?`<div class="fld"><label>Grupo <span class="muted" style="text-transform:none;letter-spacing:0;font-weight:400">— mover / criar novo${i.grupo_override?` · base: ${esc(i.grupo_base||'')}`:''}</span></label><select id="rGrupo">${grupoOptions(i.grupo)}</select></div>`
         :`<div class="fld"><label>Grupo</label><input value="${esc(i.grupo||'')}" disabled></div>`}
      ${a?`<div class="fld"><label>Tipo do item</label><select id="rTipo">${TIPOS.map(t=>`<option value="${t}" ${t===tp?'selected':''}>${t||'— a classificar —'}</option>`).join('')}</select></div>`
         :`<div class="fld"><label>Tipo do item</label><input value="${esc(tp||'— a classificar —')}" disabled></div>`}
      <div class="fld"><label>Status</label>
        <select id="rStatus">${STATUSES.map(s=>`<option ${s===st?'selected':''}>${s}</option>`).join('')}</select></div>
      ${a?`<div class="fld"><label>Responsável <span class="muted" style="font-weight:400;font-size:11px">(recomendado)</span></label><select id="rResp">${respOptions(i.responsavel)}</select></div>`
         :`<div class="fld"><label>Responsável</label><input value="${esc(i.responsavel||'—')}" disabled></div>`}
    </div>
    ${a?`<label class="fld" style="display:flex;flex-direction:row;align-items:center;gap:7px;font-size:12px;font-weight:400;margin:2px 0 0;cursor:pointer;color:var(--muted)">
      <input type="checkbox" id="rNomeBase" style="width:auto;margin:0;flex:0 0 auto"> Aplicar <b style="margin:0 3px">nome/grupo</b> à lista-base (todas as obras) — por padrão a mudança vale <b style="margin:0 3px">só nesta obra</b></label>`:''}
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
  const cb=document.getElementById('rNomeBase');
  const escopo=(IS_ADMIN && cb && cb.checked) ? 'catalogo' : 'obra';   // nome/grupo: base (todas) × só nesta obra
  EDITR=false;
  await saveAndReload(campos, escopo);
  toast(escopo==='catalogo' ? 'Salvo na lista-base (todas as obras)' : 'Alterações salvas (só nesta obra)');
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
// escopo (nome/grupo): 'obra' = override só nesta obra (padrão) · 'catalogo' = muda a lista-base (todas, admin)
async function saveAndReload(campos, escopo){
  try{
    const oid=CUR.obra_id||1;
    const d=await (await fetch('actions/item_update.php',{method:'POST',headers:{'Content-Type':'application/json'},
      body:JSON.stringify({ordem:CUR.ordem,campos,me:EU&&EU.bitrix_id,obra:oid,escopo:escopo||'obra'})})).json();
    if(d.error){toast('Erro: '+d.error);return;}
    VERBA_USOS=null;            // verba mudou → recarrega o mapa de uso na próxima leitura
    // PERF: reload de UM item só (rápido) em vez de recarregar a matriz inteira (7-14s) a cada save.
    // escopo 'catalogo' muda nome/grupo de TODAS as obras → aí recarrega tudo.
    let merged=false;
    if(escopo!=='catalogo'){
      try{
        const mr=await (await fetch('actions/matriz.php?only='+CUR.ordem+'&_='+Date.now()+(oid!==1?('&obra='+oid):''),{cache:'no-store'})).json();
        if(mr&&mr.item){
          mr.item.obra_id=oid; mr.item.obra_nome=(mr.obra&&mr.obra.nome)||('obra '+oid); mr.item._full=true;  // ?only= traz o item completo
          const arr=DATA.itens||(DATA.itens=[]); const idx=arr.findIndex(i=>i.ordem===CUR.ordem&&(i.obra_id||1)===oid);
          if(idx>=0) arr[idx]=mr.item; else arr.push(mr.item);
          if(mr.resumo) RESUMO_BY_OBRA[oid]=mr.resumo;
          try{ fillOrdered('fgrupo',[...new Set(arr.map(i=>i.grupo).filter(Boolean))]); fill('fstatus',[...new Set(arr.map(i=>i.status||'Não Iniciado'))]); fill('fresp',[...new Set(arr.map(i=>nrmResp(i.responsavel)).filter(Boolean))]); }catch(e){}
          renderKpis(); render();
          if(document.getElementById('view-matriz') && document.getElementById('view-matriz').style.display!=='none'){ MAT=null; loadMatriz(true); }
          merged=true;
        }
      }catch(e){}
    }
    if(!merged) await load();   // escopo catálogo, ou fallback se o reload leve falhar
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
/* ===================== BUSCA DE PEDIDOS DE COMPRA (consulta TOTVS) =====================
   "Com quem estamos comprando martelete?" — busca por item/fornecedor/nº, filtra por obra e
   período, lista os PCs (30/página) e abre o pedido completo no popup que já existe. */