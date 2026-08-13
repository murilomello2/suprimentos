
/* ═════════ BILHETE DE IDENTIDADE — anexado a TODA chamada ao servidor ═════════
   O bilhete é emitido pelo index.php depois de o servidor perguntar ao Bitrix de quem é o
   AUTH_ID. Daqui para frente ele viaja em toda requisição, e é o id de DENTRO dele que o
   servidor usa — o `me` da requisição vira enfeite.

   Interceptar o fetch UMA vez, em vez de editar as dezenas de chamadas espalhadas pelos nove
   arquivos: mexer em cada uma garante esquecer alguma, e a que ficar de fora é exatamente o
   furo que a gente está fechando.

   O bilhete fica em sessionStorage porque um F5 é GET — o Bitrix só manda AUTH_ID no POST de
   abertura do app. Sem guardar, recarregar a página perderia a identidade. sessionStorage e não
   localStorage: morre com a aba, que é o tempo de vida certo para uma credencial. */
(function(){
  const K='sup_tk';
  if (window.APP_TK) { try{ sessionStorage.setItem(K, window.APP_TK); }catch(e){} }
  window.supTk = function(){
    if (window.APP_TK) return window.APP_TK;
    try{ return sessionStorage.getItem(K) || ''; }catch(e){ return ''; }
  };

  /* AUTO-RECUPERAÇÃO: aba aberta desde ANTES desta versão não tem bilhete guardado e, no modo
     estrito, travaria na próxima ação sem a pessoa entender por quê. Então: se o servidor recusar
     e nós não tivermos bilhete, tenta trocar UMA vez e repete a chamada. Uma só — repetir em
     laço transformaria um 403 legítimo (sem permissão) em tempestade de requisições. */
  let tentandoBilhete = false;
  async function recuperarBilhete(){
    if (tentandoBilhete || !window.BX24 || typeof authTrocarBilhete !== 'function') return false;
    tentandoBilhete = true;
    try{ const b = await authTrocarBilhete(); return !!(b && b.tk); }
    finally { tentandoBilhete = false; }
  }

  const orig = window.fetch;
  window.fetch = async function(entrada, opcoes){
    const semBilheteAntes = !window.supTk();
    const ehAction = (typeof entrada === 'string') && /(^|\/)actions\//.test(entrada);
    const alvoOriginal = entrada;
    try{
      const tk = window.supTk();
      const url = (typeof entrada === 'string') ? entrada : (entrada && entrada.url) || '';
      if (tk && typeof entrada === 'string' && /(^|\/)actions\//.test(url)) {
        // 1) sempre na querystring: cobre GET e também POST cujo corpo não seja JSON nosso
        entrada += (entrada.indexOf('?') >= 0 ? '&' : '?') + 'tk=' + encodeURIComponent(tk);
        // 2) e no corpo, quando for o POST JSON que o cockpit usa em tudo
        if (opcoes && opcoes.method && String(opcoes.method).toUpperCase() === 'POST'
            && typeof opcoes.body === 'string' && opcoes.body.charAt(0) === '{') {
          try{ const b = JSON.parse(opcoes.body); if (b && b.tk === undefined) { b.tk = tk; opcoes = Object.assign({}, opcoes, {body: JSON.stringify(b)}); } }catch(e){}
        }
      }
    }catch(e){}
    const r = await orig.call(this, entrada, opcoes);
    if (ehAction && semBilheteAntes && (r.status === 401 || r.status === 403)) {
      if (await recuperarBilhete()) return window.fetch(alvoOriginal, opcoes);   // 1 nova tentativa
    }
    return r;
  };
})();

/* Cockpit de Suprimentos — parte 1 de 6 do aplicativo.
   Gerado a partir do bloco unico que vivia dentro do index.php: 857 KB num arquivo so faziam
   cada deploy levar de 5 a 10 minutos e falhar calado. O corte respeita fronteiras de nivel
   superior e cada parte foi validada pelo parser antes de existir. A ORDEM importa: os
   arquivos sao carregados na sequencia em que foram cortados. */

let DATA={itens:[],obra:{}}, CUR=null, TAB='Resumo';
let RESUMO_BY_OBRA={};   // resumo (cobertura/verba) por obra — p/ recalcular KPIs sem recarregar a matriz inteira
// KPIs do radar (recalcula a partir de DATA.itens + RESUMO_BY_OBRA — usado no load e no save de 1 item)
function renderKpis(){
  const el=document.getElementById('kpis'); if(!el) return;
  const itens=(DATA&&DATA.itens)||[]; const sel=(OBRA_SEL&&OBRA_SEL.length?OBRA_SEL:[1]);
  let covVal=0, covLeaf=0; sel.forEach(oid=>{ const r=RESUMO_BY_OBRA[oid]; if(r){ covVal+=r.cobertura_valor||0; covLeaf+=r.cobertura_total_leaf||0; } });
  const cobertura=covLeaf?Math.round(covVal/covLeaf*1000)/10:null, nObras=sel.length;
  const comData=itens.filter(i=>i.data_necessaria).length;
  const criticos=itens.filter(i=>alertLevel(i)==='critico').length;
  const atrasados=itens.filter(i=>alertLevel(i)==='atrasado').length;
  const cv=k=>itens.filter(i=>i.curva===k).length;
  el.innerHTML=`
      <div class="kpi" title="Itens no radar${nObras>1?' ('+nObras+' obras)':''}"><div class="v">${itens.length}</div><div class="l">Itens${nObras>1?' · '+nObras+' obras':''}</div></div>
      <div class="kpi" title="Itens com data em obra definida (cronograma)"><div class="v">${comData}/${itens.length}</div><div class="l">Com data</div></div>
      <div class="kpi" title="Críticos: o fim do prazo de cotação já venceu${atrasados?' · '+atrasados+' atrasados':''}"><div class="v ${criticos?'alert':''}">${criticos}</div><div class="l">Críticos${atrasados?` · ${atrasados} atras.`:''}</div></div>
      <div class="kpi" title="Itens por curva ABC (A / B / C)"><div class="v">${cv('A')}·${cv('B')}·${cv('C')}</div><div class="l">Curva A·B·C</div></div>
      <div class="kpi" title="Cobertura REAL do orçamento: coberto ${BRL(covVal)} de ${BRL(covLeaf)} em folhas do(s) orçamento(s)."><div class="v gold">${cobertura!=null?cobertura.toLocaleString('pt-BR')+'%':'—'}</div><div class="l">Cobertura orç.</div></div>`;
}
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
// ---- variantes com N casas decimais (min 2, até `dec`) — p/ PREÇO UNITÁRIO da cotação, que aceita até 4 casas ----
const fmtMoneyN=(n,dec)=>{ dec=Math.max(2,dec||2); return (n===0||n)?Number(n).toLocaleString('pt-BR',{minimumFractionDigits:2,maximumFractionDigits:dec}):''; };
function maskMoneyInputN(el,dec){ dec=Math.max(2,dec||2); let v=el.value.replace(/[^\d,]/g,''); const k=v.indexOf(','); let ip,dp=null;
  if(k>=0){ ip=v.slice(0,k).replace(/\D/g,''); dp=v.slice(k+1).replace(/\D/g,'').slice(0,dec); } else ip=v.replace(/\D/g,'');
  ip=ip.replace(/^0+(?=\d)/,''); const ii=ip===''?(dp!==null?'0':''):Number(ip).toLocaleString('pt-BR'); el.value=ii+(dp!==null?(','+dp):''); }
function moneyBlurN(el,dec){ const n=parseBRLInput(el.value); el.value=(n==null)?'':fmtMoneyN(n,dec); }
// R$ do PREÇO UNITÁRIO: mostra 2 a 4 casas (não arredonda 0,0325 p/ 0,03). Totais seguem em BRL (2 casas).
const BRLp=n=>(n||n===0)?Number(n).toLocaleString('pt-BR',{style:'currency',currency:'BRL',minimumFractionDigits:2,maximumFractionDigits:4}):'—';
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
const STK={'Finalizado':'st-Finalizado','Cotação Iniciada':'st-CotacaoIniciada','Com Pendências':'st-ComPendencias','Em Andamento':'st-EmAndamento','Não Iniciado':'st-NaoIniciado','Não se aplica':'st-NaoSeAplica'};
const STATUSES=['Não Iniciado','Cotação Iniciada','Com Pendências','Em Andamento','Finalizado','Não se aplica'];
function toast(m){const t=document.getElementById('toastEl');t.textContent=m;t.style.display='block';clearTimeout(t._);t._=setTimeout(()=>t.style.display='none',2400);}
// multi-obra: a mesma ordem existe em 2+ obras. Procura no RADAR (DATA.itens = só OBRA_SEL) e, se não achar,
// cai na MATRIZ (MAT = TODAS as obras) — assim clicar numa célula da matriz abre o item mesmo se a obra
// não estiver selecionada no radar (as duas telas são independentes).
// nome de pessoa NORMALIZADO (colapsa espaço duplo/invisível + trim) — o <option> do navegador colapsa o
// valor ao selecionar, então TODA comparação de responsável tem que passar por aqui ("João  Nogueira" ≠ "João Nogueira")
const nrmResp=s=>String(s||'').replace(/\s+/g,' ').trim();
const byOrdem=(o,ob)=>DATA.itens.find(i=>i.ordem==o && (ob==null || i.obra_id==ob))
  || ((typeof MAT!=='undefined' && MAT) ? MAT.find(i=>i.ordem==o && (ob==null || i.obra_id==ob)) : null);
const OBQ=()=>((CUR&&CUR.obra_id)||OBRA_SEL[0]||1);   // obra do MODAL aberto (ou a primária) — vai em todo fetch do modal
function daysBetween(a,b){ return Math.round((new Date(b)-new Date(a))/86400000); }
/* nível de alerta da cotação: 'critico' (fim venceu, não finalizado) > 'atrasado' (início venceu, não iniciou)
   > 'proximo' (faltam ≤7d p/ iniciar) > 'ok'. Item finalizado saiu do radar de Suprimentos = ok. */
function alertLevel(i){
  const st=i.status||'Não Iniciado';
  if(st==='Finalizado'||st==='Não se aplica') return 'finalizado';   // concluído ou N/A (fica no radar, sem alerta)
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
      <span style="display:flex;gap:4px"><button class="btn-ghost" style="padding:2px 8px;font-size:11px" onclick="obraSelLimpar(event)">Desmarcar</button><button class="btn-ghost" style="padding:2px 8px;font-size:11px" onclick="obraSelTodas(event)">Todas</button></span></div>`+
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
// desmarca TODAS (permite zero — o radar mostra um estado vazio pedindo p/ selecionar; depois é só marcar as que quer)
function obraSelLimpar(e){ if(e) e.stopPropagation(); OBRA_SEL=[]; localStorage.setItem('sup_obras',JSON.stringify(OBRA_SEL)); obraMenuRender(); load(); }
// atualiza o rótulo do botão + o menu (se aberto) — chamado no fim do load()
function obraUpdateUI(){
  const lbl=document.getElementById('obraPickLbl');
  if(lbl){ const nomes=OBRA_SEL.map(id=>{ const o=OBRAS.find(x=>Number(x.id)===id); return o?o.nome:('#'+id); });
    lbl.textContent = nomes.length===0 ? 'Selecione a obra' : (nomes.length===1 ? nomes[0] : (nomes.length+' obras selecionadas')); }
  const m=document.getElementById('obraMenu'); if(m && m.style.display==='block') obraMenuRender();
}

/* ===== Matriz: dropdown de obras (próprio, independente do Radar) — null = todas ===== */
let MAT_SEL=(()=>{ try{ const v=JSON.parse(localStorage.getItem('sup_mat_obras')||'null'); return Array.isArray(v)?v:null; }catch(e){ return null; } })();
// matriz: grupos recolhidos, serviços expandidos (detalhe), ordem custom das colunas de obra, item arrastado
let MAT_COLLAPSED=new Set(), MAT_EXP=new Set(), _matDrag=null, MAT_OBRAS_CUR=[], MAT_SVCS_CUR=[];
let MAT_OBRA_ORDER=(()=>{ try{ const v=JSON.parse(localStorage.getItem('sup_matobra_ord')||'null'); return Array.isArray(v)?v:null; }catch(e){ return null; } })();
// arg seguro p/ string em atributo HTML de evento (aspas simples no JS + &quot; no atributo)
/* Argumento de string para dentro de um atributo HTML (onclick="f(...)").
   NUNCA use JSON.stringify aqui: ele devolve "texto" com ASPAS DUPLAS, que fecham o atributo no
   meio — o navegador le onchange="envSelToggle(" e o handler simplesmente nao existe. Foi o que
   quebrou as caixas de selecao e o botao "Enviar agora": a tela parecia certa e o clique nao fazia
   nada. Aqui a string sai entre aspas SIMPLES, escapando primeiro o que quebra o literal JS e
   depois o que quebra o HTML. */
function jsArg(s){ return "'"+String(s==null?'':s).replace(/\\/g,'\\\\').replace(/'/g,"\\'").replace(/&/g,'&amp;').replace(/"/g,'&quot;').replace(/</g,'&lt;').replace(/\n/g,' ')+"'"; }
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
let _loadSeq=0;   // versão da requisição: evita que um load() antigo (obra anterior) sobrescreva um mais novo (corrida)
async function load(){
  const seq=++_loadSeq;
  const selNoInicio=OBRA_SEL.slice();   // congela a seleção desta chamada
  if(!selNoInicio.length){   // nenhuma obra marcada (após "Desmarcar") → estado vazio amigável, sem fetch
    DATA={itens:[]}; RESUMO_BY_OBRA={};
    const tb=document.getElementById('tb'); if(tb) tb.innerHTML=`<tr><td colspan="12" class="empty">Nenhuma obra selecionada. Marque ao menos uma obra no seletor acima para ver o radar.</td></tr>`;
    try{ renderKpis(); }catch(_){} obraUpdateUI(); return;
  }
  try{
    // busca a matriz de CADA obra selecionada em paralelo e mescla (item ganha obra_id/obra_nome)
    const rs0=await Promise.all(selNoInicio.map(async oid=>{
      const d=await (await fetch('actions/matriz.php?_='+Date.now()+(oid!==1?('&obra='+oid):''),{cache:'no-store'})).json(); return {oid,d};
    }));
    if(seq!==_loadSeq) return;   // uma seleção mais NOVA começou a carregar — descarta este resultado obsoleto
    const oks=rs0.filter(x=>x.d && !x.d.error && x.d.itens);
    if(!oks.length){document.getElementById('tb').innerHTML=`<tr><td colspan="12" class="empty">Erro: ${esc((rs0[0]&&rs0[0].d&&rs0[0].d.error)||'sem dados')}</td></tr>`;return;}
    DATA=oks[0].d;
    OBRAS=DATA.obras||OBRAS;
    const itens=[]; let cronoErro=null; RESUMO_BY_OBRA={};
    for(const {oid,d} of oks){
      (d.itens||[]).forEach(i=>{ i.obra_id=oid; i.obra_nome=(d.obra&&d.obra.nome)||('obra '+oid); itens.push(i); });
      RESUMO_BY_OBRA[oid]=d.resumo||{};
      if(d.resumo&&d.resumo.crono_erro) cronoErro=d.resumo.crono_erro;
    }
    DATA.itens=itens;
    // obra(s) selecionada(s) aparecem SÓ no dropdown (botão + checkboxes) — sem linha de texto no topo
    obraUpdateUI();
    renderKpis();   // KPIs sobre o conjunto selecionado (recalcula de DATA.itens + RESUMO_BY_OBRA)
    // filtros dinâmicos (grupos em ordem lógica = ordem de aparição; demais ordenados)
    fillOrdered('fgrupo',[...new Set(itens.map(i=>i.grupo).filter(Boolean))]);
    fill('fstatus',[...new Set(itens.map(i=>i.status||'Não Iniciado'))]);
    fill('fresp',[...new Set(itens.map(i=>nrmResp(i.responsavel)).filter(Boolean))]);
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
  ['radar','matriz','oportunidades','top20','dashboards','cotacoes','fechamentos','solicitacoes','envio','buscaped','buscanf','obras','caixa','whats','oraculo','config','audit','updates','ovradar','ovcot','ovsc'].forEach(x=>{
    const el=document.getElementById('view-'+x); if(el) el.style.display=v===x?'':'none';
    const nav=document.getElementById('nav-'+x); if(nav) nav.classList.toggle('active',v===x);
  });
  if(v==='obras') obrasInit();
  if(v==='cotacoes') cotInit();
  if(v==='solicitacoes') solInit();
  if(v==='oraculo') oracInit();
  if(v==='envio') envInit();
  if(v==='caixa') caixaInit();
  if(v==='whats') waInit();
  if(v==='buscaped') bpInit();
  if(v==='buscanf') bnInit();
  if(v==='fechamentos') fecInit();
  if(v==='top20') t20Init();
  if(v==='dashboards') dashInit();
  if(v==='matriz') loadMatriz();
  if(v==='oportunidades') renderOportunidades();
  if(v==='config') renderConfig();
  if(v==='radar') fitRadarHeight();
  if(v==='ovradar'){ ovObrasCarrega().then(()=>ovRadarInit()); }
  if(v==='ovcot'){   ovObrasCarrega().then(()=>ovCotInit()); }
  if(v==='ovsc'){    ovObrasCarrega().then(()=>ovScInit()); }
  if(v==='audit') renderAudit();
  accPing(v);   // registra o uso da tela (fire-and-forget; ver accPing)
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
  /* COLUNAS COMO LINHA DO TEMPO (pedido do Murilo): com "Obras › Por data de fechar", a obra que precisa
     fechar primeiro vira a 1ª coluna. A data é POR SERVIÇO × OBRA, então a chave de cada obra é a MENOR
     data de fim de cotação entre os serviços VISÍVEIS — filtre um item (ex.: "elevad") e a ordem passa a
     ser exatamente a fila daquele item. Obra sem data vai pro fim. */
  if(gv('mobraord')==='prazo'){
    const keyObra=o=>{ let min=null;
      for(const sv of servicos){ const it=idx[sv.ordem+'|'+o]; const f=it&&it.fim_cotacao;
        if(f && (min===null || f<min)) min=f; }
      return min||'9999-99-99'; };
    const chave={}; obras.forEach(o=>chave[o]=keyObra(o));
    obras=obras.slice().sort((a,b)=>chave[a].localeCompare(chave[b])||a.localeCompare(b,'pt'));
    MAT_OBRA_ORDER=null;   // a ordem por data manda enquanto estiver ligada (o arrastar volta a valer ao desligar)
  }
  MAT_SVCS_CUR=servicos;   // p/ expandir/recolher todos
  MAT_OBRAS_CUR=obras;     // reatribui: a ordem pode ter mudado acima
  const mc=document.getElementById('mctrl');
  if(mc) mc.innerHTML=(servicos.length&&obras.length)?`<div class="bar" style="gap:6px;flex-wrap:wrap;align-items:center;padding:0">
    <button class="btn-ghost" style="padding:4px 10px" onclick="matExpandAll(true)"><span class="material-icons" style="font-size:15px;vertical-align:-3px">unfold_more</span> Expandir serviços</button>
    <button class="btn-ghost" style="padding:4px 10px" onclick="matExpandAll(false)"><span class="material-icons" style="font-size:15px;vertical-align:-3px">unfold_less</span> Recolher serviços</button>
    <button class="btn-ghost" style="padding:4px 10px" onclick="matGrpAll(false)">Recolher grupos</button>
    <button class="btn-ghost" style="padding:4px 10px" onclick="matGrpAll(true)">Expandir grupos</button>
    <span class="muted" style="font-size:11px">— arraste os nomes das obras p/ reordenar · clique no ▸ de um serviço p/ ver quantitativo / verba / responsável / status</span></div>`:'';
  if(!servicos.length||!obras.length){document.getElementById('mwrap').innerHTML='<div class="empty">Sem dados para os filtros.</div>';return;}
  let html='<table class="mtable"><thead><tr><th class="svc-h">Serviço</th>'+
    obras.map((o,oi)=>{
      let sub='';
      if(gv('mobraord')==='prazo'){ let min=null;
        for(const sv of servicos){ const it=idx[sv.ordem+'|'+o]; const f=it&&it.fim_cotacao; if(f&&(min===null||f<min)) min=f; }
        if(min) sub=`<div style="font-size:9.5px;font-weight:600;color:${min<today?'var(--pend)':'var(--muted)'};margin-top:1px">fecha ${D(min)}</div>`;
        else sub='<div style="font-size:9.5px;color:var(--muted);margin-top:1px">sem data</div>';
      }
      return `<th class="mo-th" draggable="true" ondragstart="matDragStart(event,${oi})" ondragover="event.preventDefault();this.classList.add('mo-drag')" ondragleave="this.classList.remove('mo-drag')" ondrop="matDrop(event,${oi})" title="arraste p/ reordenar">${esc(o)}${sub}</th>`;
    }).join('')+'</tr></thead><tbody>';
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
/* ═════════ VERSÃO NOVA PUBLICADA → A ABA SE ATUALIZA ═════════
   O ETag no index.php resolve quem ABRE o cockpit. Não resolve quem deixou a aba aberta a
   semana toda — e é assim que o time trabalha. Aqui a aba pergunta o carimbo do build de vez
   em quando; se mudou, ela se recarrega.

   Recarregar na cara de quem está no meio de uma cotação perde trabalho, então só recarrega
   sozinha quando é seguro (aba em segundo plano, ou sem modal aberto e sem edição pendente).
   Caso contrário aparece uma barra discreta e a pessoa decide a hora. */
let VER_ULT = 0;

async function verCheck(){
  if(!window.APP_VER) return;
  const agora = Date.now();
  if(agora - VER_ULT < 120000) return;          // no máx. 1 consulta a cada 2 min
  VER_ULT = agora;
  let v;
  try{ v = (await (await fetch('actions/versao.php?_='+agora, {cache:'no-store'})).json()).v; }
  catch(e){ return; }                            // sem rede/servidor fora: não é problema nosso agora
  if(!v || String(v) === String(window.APP_VER)) return;

  const modalAberto = !!document.querySelector('#ov.open')
                   || !!document.getElementById('caOv') || !!document.getElementById('vcOv');
  const editando = (typeof anyEditing === 'function') && anyEditing();
  if(document.hidden || (!modalAberto && !editando)){ location.reload(); return; }
  verBarra();
}

function verBarra(){
  if(document.getElementById('verBar')) return;
  const b = document.createElement('div');
  b.id = 'verBar';
  b.style.cssText = 'position:fixed;left:50%;transform:translateX(-50%);bottom:18px;z-index:99999;'
    + 'background:var(--verde,#1e5b3f);color:#fff;padding:10px 14px;border-radius:10px;'
    + 'box-shadow:0 8px 28px rgba(0,0,0,.28);display:flex;align-items:center;gap:12px;font-size:13px';
  b.innerHTML = '<span>Saiu uma versão nova do cockpit.</span>'
    + '<button onclick="location.reload()" style="background:#fff;color:var(--verde-d,#14402c);border:0;'
    + 'padding:5px 12px;border-radius:7px;font-weight:700;cursor:pointer;font-size:12.5px">Atualizar agora</button>'
    + '<span onclick="this.parentNode.remove()" style="cursor:pointer;opacity:.75;font-size:17px;line-height:1">&times;</span>';
  document.body.appendChild(b);
}

/* Duas oportunidades de perceber: de hora em hora, e quando a pessoa volta pra aba —
   que é o momento natural de a tela se refazer sem incomodar ninguém. */
setInterval(verCheck, 3600000);
document.addEventListener('visibilitychange', () => { if(!document.hidden) verCheck(); });
