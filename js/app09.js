/* ═══════════ ASSISTENTE DE WHATSAPP ═══════════
   DOIS NÍVEIS, e a ordem importa:

   1) PAINEL — um card por COTAÇÃO. É assim que o comprador pensa: "disparei a SC 3609 para 10
      fornecedores, 4 já responderam". A 1ª versão listava cada conversa solta e o Murilo cortou
      na hora — dez cartões sem parentesco não dizem nada sobre a negociação.
   2) NEGOCIAÇÃO ABERTA — fornecedores à esquerda (com o valor de cada proposta), conversa no
      meio, detalhes/proposta à direita, e o mapa se montando conforme as respostas chegam.

   O estado da cotação é DERIVADO das conversas: basta uma pedir socorro para a cotação inteira
   aparecer em "Precisa de você". */

let WA = { tela:'painel', painel:null, neg:null, convId:0, conv:null, q:'', carregando:false, enviando:false };

const WA_COR = { em_fila:'#8a9299', aguardando:'#6b8fb5', ativa:'var(--verde)',
                 duvida_ia:'var(--pend)', parada:'#c0392b', concluida:'#5a6b60', falhou:'#c0392b' };
const WA_NCOR = { atencao:'var(--pend)', andamento:'var(--verde)', aguardando:'#6b8fb5', concluida:'#5a6b60' };
const WA_NICO = { atencao:'priority_high', andamento:'trending_up', aguardando:'hourglass_empty', concluida:'check_circle' };
const WA_EST  = { em_fila:'Em fila', aguardando:'Aguardando', ativa:'Ativa', duvida_ia:'Dúvida IA',
                  parada:'Parada', concluida:'Concluída', falhou:'Falhou' };

function waInit(){ WA.tela='painel'; waPainel(); }

function waQuando(iso){
  if(!iso) return '';
  const d=new Date(iso); if(isNaN(d)) return '';
  const min=Math.floor((Date.now()-d.getTime())/60000);
  if(min<1) return 'agora'; if(min<60) return min+' min';
  const h=Math.floor(min/60); if(h<24) return h+'h';
  return Math.floor(h/24)+'d';
}
function waRS(v){ return v===null||v===undefined ? '—' : 'R$ '+(+v).toLocaleString('pt-BR',{minimumFractionDigits:2,maximumFractionDigits:2}); }

/* ─────────────── NÍVEL 1: PAINEL DE NEGOCIAÇÕES ─────────────── */
async function waPainel(){
  WA.tela='painel'; WA.carregando=true; waRender();
  try{
    const p=new URLSearchParams({negociacoes:'1', me:(EU&&EU.bitrix_id)||''});
    if(WA.q) p.set('q',WA.q);
    WA.painel=await (await fetch('actions/whats.php?'+p)).json();
  }catch(e){ WA.painel={error:'Falha ao carregar: '+e.message}; }
  WA.carregando=false; waRender();
}
function waBusca(){ const e=document.getElementById('waQ'); WA.q=e?e.value.trim():''; waPainel(); }

function waRender(){
  const w=document.getElementById('waWrap'); if(!w) return;
  if(WA.tela==='negociacao'){ waNegRender(); return; }
  const k=WA.painel;
  if(WA.carregando && !k){ w.innerHTML='<div class="dempty">Carregando…</div>'; return; }
  if(!k || k.error){ w.innerHTML='<div class="dempty">'+esc((k&&k.error)||'Falha')+'</div>'; return; }

  let h='';
  if(k.modo==='simulador')
    h+='<div style="border-left:4px solid var(--dourado);background:#fdf9ec;padding:9px 12px;border-radius:0 8px 8px 0;font-size:12.5px;margin-bottom:12px">'
     + '<b>Modo simulador.</b> Nenhuma mensagem sai de verdade. Dentro de cada conversa você pode responder <b>como se fosse o fornecedor</b> e ver a assistente trabalhar. '
     + (IS_ADMIN?'O modo real liga em Configurações › IA &amp; WhatsApp.':'')+'</div>';

  h+='<div style="display:flex;gap:8px;align-items:center;margin-bottom:12px;flex-wrap:wrap">'
   + '<div class="search" style="border:1px solid var(--line);flex:1 1 260px;max-width:420px"><span class="material-icons" style="color:var(--muted)">search</span>'
   + '<input id="waQ" value="'+esc(WA.q)+'" placeholder="cotação, solicitação ou obra…" onkeydown="if(event.key===\'Enter\')waBusca()"></div>'
   + '<button class="btn-ghost" style="padding:6px 12px;font-size:12.5px" onclick="waBusca()">Filtrar</button>'
   + '<button class="btn-ghost" style="padding:6px 12px;font-size:12.5px" onclick="waPainel()"><span class="material-icons" style="font-size:15px;vertical-align:-3px">refresh</span> Atualizar</button></div>';

  const ordem=['atencao','andamento','aguardando','concluida'];
  h+='<div style="display:flex;gap:10px;overflow-x:auto;padding-bottom:6px;align-items:flex-start">';
  let vazio=true;
  for(const est of ordem){
    const cs=(k.colunas&&k.colunas[est])||[];
    if(!cs.length && est==='concluida') continue;
    if(cs.length) vazio=false;
    const cor=WA_NCOR[est];
    h+='<div style="flex:0 0 300px;background:#f6f8f7;border-radius:11px;padding:9px;max-height:68vh;display:flex;flex-direction:column">'
     + '<div style="display:flex;align-items:center;gap:6px;padding:2px 4px 9px">'
     +   '<span class="material-icons" style="font-size:16px;color:'+cor+'">'+WA_NICO[est]+'</span>'
     +   '<b style="font-size:12.5px">'+esc(k.rotulos[est])+'</b>'
     +   '<span style="margin-left:auto;background:'+cor+';color:#fff;border-radius:9px;padding:0 7px;font-size:11px;font-weight:700">'+cs.length+'</span></div>'
     + '<div style="overflow-y:auto;display:flex;flex-direction:column;gap:8px">';
    for(const g of cs){
      const pct = g.total ? Math.round(g.responderam/g.total*100) : 0;
      h+='<div onclick="waAbrirNegociacao('+g.cotacao_id+')" style="background:#fff;border:1px solid var(--line);border-left:3px solid '+cor+';border-radius:9px;padding:9px 11px;cursor:pointer">'
       + '<div style="display:flex;align-items:center;gap:5px"><b style="font-size:13px;flex:1;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">'+esc(g.titulo)+'</b>'
       +   (g.nao_lidas?'<span style="background:var(--verde);color:#fff;border-radius:9px;padding:0 6px;font-size:10.5px;font-weight:700">'+g.nao_lidas+'</span>':'')+'</div>'
       + '<div class="dmini" style="color:var(--muted);margin-top:2px">'
       +   (g.obra?esc(g.obra):'')+(g.solicitacao?(' · SC '+esc(g.solicitacao)):'')+'</div>'
       // barra de respostas: é o número que o comprador procura primeiro
       + '<div style="margin-top:7px"><div style="display:flex;justify-content:space-between;font-size:11px;margin-bottom:3px">'
       +   '<span class="muted"><b style="color:var(--texto)">'+g.responderam+'</b> de '+g.total+' responderam</span>'
       +   (g.propostas?('<span class="muted">'+g.propostas+' proposta(s)</span>'):'')+'</div>'
       +   '<div style="height:5px;background:#e8ecea;border-radius:3px;overflow:hidden"><div style="height:100%;width:'+pct+'%;background:'+cor+'"></div></div></div>'
       + (g.melhor!==null?('<div style="margin-top:7px;font-size:12px"><span class="muted">melhor até agora</span> <b>'+esc(waRS(g.melhor))+'</b>'
       +   (g.economia?('<span class="dchip" style="background:var(--verde);margin-left:6px">'+g.economia+'% abaixo do maior</span>'):'')+'</div>'):'')
       + '<div style="display:flex;align-items:center;gap:6px;margin-top:6px">'
       +   (g.duvida?('<span class="dchip" style="background:var(--pend)">'+g.duvida+' pedindo ajuda</span>'):'')
       +   (g.fila?('<span class="dchip" style="background:#8a9299">'+g.fila+' na fila</span>'):'')
       +   '<span class="dmini" style="margin-left:auto;color:var(--muted)">'+esc(waQuando(g.ultima))+'</span></div>'
       + '</div>';
    }
    if(!cs.length) h+='<div class="dmini" style="color:var(--muted);padding:6px 4px">vazio</div>';
    h+='</div></div>';
  }
  h+='</div>';
  if(vazio) h+='<div class="dempty" style="margin-top:14px">Nenhuma negociação ainda. Abra uma cotação, convide os fornecedores e use <b>“Disparar no WhatsApp”</b>.</div>';
  w.innerHTML=h;
}

/* ─────────────── NÍVEL 2: A NEGOCIAÇÃO ABERTA ─────────────── */
async function waAbrirNegociacao(cotacaoId){
  WA.tela='negociacao'; WA.neg=null; WA.conv=null; WA.convId=0;
  const w=document.getElementById('waWrap'); if(w) w.innerHTML='<div class="dempty">Abrindo negociação…</div>';
  try{ WA.neg=await (await fetch('actions/whats.php?negociacao='+cotacaoId+'&me='+encodeURIComponent((EU&&EU.bitrix_id)||''))).json(); }
  catch(e){ WA.neg={error:e.message}; }
  const f=(WA.neg&&WA.neg.fornecedores)||[];
  if(f.length){ const alvo=f.find(x=>x.nao_lidas>0)||f.find(x=>x.estado==='duvida_ia')||f[0]; await waCarregarConversa(alvo.id); }
  else waNegRender();
}

async function waCarregarConversa(id){
  WA.convId=id;
  try{ WA.conv=await (await fetch('actions/whats.php?conversa='+id+'&me='+encodeURIComponent((EU&&EU.bitrix_id)||''))).json(); }
  catch(e){ WA.conv={error:e.message}; }
  waNegRender();
}

function waNegRender(){
  const w=document.getElementById('waWrap'); if(!w) return;
  const n=WA.neg;
  if(!n || n.error){ w.innerHTML='<div class="dempty">'+esc((n&&n.error)||'Falha')+'</div>'; return; }
  const c=n.cotacao, forn=n.fornecedores||[];
  const respondeu=forn.filter(f=>f.total!==null||['ativa','concluida'].includes(f.estado)).length;

  let h='';
  // ── cabeçalho da negociação ──
  h+='<div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;margin-bottom:12px">'
   + '<button class="btn-ghost" style="padding:6px 11px" onclick="waPainel()"><span class="material-icons" style="font-size:17px;vertical-align:-4px">arrow_back</span></button>'
   + '<div style="min-width:0"><b style="font-size:16px">'+esc(c.titulo)+'</b>'
   +   '<div class="dmini" style="color:var(--muted)">'+(c.obra?esc(c.obra):'')+(c.solicitacao?(' · SC '+esc(c.solicitacao)):'')+' · '+n.itens.length+' item(ns)</div></div>'
   + '<div style="margin-left:auto;display:flex;gap:8px;align-items:center;flex-wrap:wrap">'
   +   '<div style="text-align:right"><div class="dmini" style="color:var(--muted)">respostas</div><b style="font-size:14px">'+respondeu+' de '+forn.length+'</b></div>'
   +   (n.melhor!==null?'<div style="text-align:right;padding-left:14px;border-left:1px solid var(--line)"><div class="dmini" style="color:var(--muted)">melhor proposta</div><b style="font-size:14px;color:var(--verde-d)">'+esc(waRS(n.melhor))+'</b></div>':'')
   +   '<button class="btn-prim" style="padding:7px 13px;font-size:12.5px" onclick="cotAbrir('+c.id+')"><span class="material-icons" style="font-size:15px;vertical-align:-3px">table_chart</span> Mapa de cotação</button>'
   + '</div></div>';

  // ── três colunas: fornecedores | conversa | proposta ──
  h+='<div style="display:flex;gap:12px;align-items:stretch;height:calc(100vh - 250px);min-height:440px">';

  // 1) lista de fornecedores
  h+='<div style="flex:0 0 262px;display:flex;flex-direction:column;border:1px solid var(--line);border-radius:11px;overflow:hidden;background:#fff">'
   + '<div style="padding:9px 11px;border-bottom:1px solid var(--line);background:#f6f8f7"><b style="font-size:12.5px">Fornecedores</b>'
   +   '<span class="muted" style="font-size:11.5px;margin-left:6px">'+forn.length+'</span></div>'
   + '<div style="overflow-y:auto;flex:1">';
  for(const f of forn){
    const sel=f.id===WA.convId;
    h+='<div onclick="waCarregarConversa('+f.id+')" style="padding:9px 11px;border-bottom:1px solid var(--line);cursor:pointer;'
     +   (sel?'background:#eef5f1;border-left:3px solid var(--verde)':'border-left:3px solid transparent')+'">'
     + '<div style="display:flex;align-items:center;gap:5px">'
     +   '<b style="font-size:12.5px;flex:1;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">'+esc(f.fornecedor)+'</b>'
     +   (f.nao_lidas?'<span style="background:var(--verde);color:#fff;border-radius:9px;padding:0 5px;font-size:10px;font-weight:700">'+f.nao_lidas+'</span>':'')+'</div>'
     + (f.total!==null?('<div style="font-size:12.5px;margin-top:2px"><b>'+esc(waRS(f.total))+'</b>'
     +   (f.melhor?'<span class="dchip" style="background:var(--verde);margin-left:5px">melhor</span>':'')+'</div>')
     :  '<div class="dmini" style="color:var(--muted);margin-top:2px">sem proposta ainda</div>')
     + '<div style="display:flex;align-items:center;gap:5px;margin-top:4px">'
     +   '<span class="dchip" style="background:'+(WA_COR[f.estado]||'#8a9299')+'">'+esc(WA_EST[f.estado]||f.estado)+'</span>'
     +   (f.fila_pos!==null&&f.fila_pos!==undefined?'<span class="dmini" style="color:var(--dourado)">vez '+f.fila_pos+'</span>':'')
     +   (f.nao_fornece&&f.nao_fornece.length?'<span class="dmini" style="color:var(--muted)">'+f.nao_fornece.length+' item(ns) não</span>':'')
     + '</div></div>';
  }
  if(!forn.length) h+='<div class="dmini" style="color:var(--muted);padding:12px">nenhum fornecedor nesta negociação</div>';
  h+='</div></div>';

  // 2) conversa
  h+='<div style="flex:1;min-width:0;display:flex;flex-direction:column;border:1px solid var(--line);border-radius:11px;overflow:hidden;background:#fff">'
   + waConvPainel()
   + '</div>';

  // 3) detalhes da proposta
  h+='<div style="flex:0 0 258px;display:flex;flex-direction:column;border:1px solid var(--line);border-radius:11px;overflow:hidden;background:#fff">'
   + waPropostaPainel()
   + '</div>';

  h+='</div>';
  w.innerHTML=h;
  const box=document.getElementById('waMsgs'); if(box) box.scrollTop=box.scrollHeight;
}

function waConvPainel(){
  const d=WA.conv;
  if(!d) return '<div class="dempty" style="margin:auto">Escolha um fornecedor à esquerda.</div>';
  if(d.error) return '<div class="dempty" style="margin:auto">'+esc(d.error)+'</div>';
  const c=d.conversa;
  let h='';
  h+='<div style="padding:9px 12px;border-bottom:1px solid var(--line);background:#f6f8f7;display:flex;align-items:center;gap:8px;flex-wrap:wrap">'
   + '<div style="min-width:0"><b style="font-size:13.5px">'+esc(c.fornecedor)+'</b>'
   +   '<div class="dmini" style="color:var(--muted)">'+esc(c.numero)+'</div></div>'
   + '<div style="margin-left:auto;display:flex;gap:5px;align-items:center;flex-wrap:wrap">'
   +   (c.dono==='humano'?'<span class="dchip" style="background:#6b8fb5">você assumiu</span>':'<span class="dchip" style="background:var(--verde)">assistente</span>')
   +   (c.dono==='ia'
        ? '<button class="btn-ghost" style="padding:3px 9px;font-size:11px" onclick="waAcao(\'assumir\')">Assumir</button>'
        : '<button class="btn-ghost" style="padding:3px 9px;font-size:11px" onclick="waAcao(\'devolver_ia\')">Devolver p/ IA</button>')
   +   (c.estado!=='parada'?'<button class="btn-ghost" style="padding:3px 9px;font-size:11px" onclick="waAcao(\'parar\')">Parar</button>'
                           :'<button class="btn-ghost" style="padding:3px 9px;font-size:11px" onclick="waAcao(\'retomar\')">Retomar</button>')
   +   (c.estado!=='concluida'?'<button class="btn-ghost" style="padding:3px 9px;font-size:11px" onclick="waAcao(\'concluir\')">Encerrar</button>':'')
   + '</div></div>';

  if(c.motivo)
    h+='<div style="border-left:4px solid var(--pend);background:#fdeeee;padding:7px 11px;font-size:12px"><b>A assistente pediu ajuda:</b> '+esc(c.motivo)+'</div>';
  if(!c.janela_aberta)
    h+='<div style="border-left:4px solid var(--dourado);background:#fdf9ec;padding:7px 11px;font-size:12px">'
     + '<b>Janela de 24h fechada</b> — só um template aprovado reabre a conversa.</div>';

  h+='<div id="waMsgs" style="flex:1;overflow-y:auto;padding:12px;background:#f7f9f8">';
  for(const m of (d.mensagens||[])){
    if(m.tipo==='sistema'){ h+='<div class="dmini" style="text-align:center;color:var(--muted);margin:8px 0">'+esc(m.texto)+'</div>'; continue; }
    const meu=m.direcao==='out';
    const bg=meu?(m.autor==='ia'?'#e3f0e8':'#dbe7f3'):'#fff';
    h+='<div style="display:flex;justify-content:'+(meu?'flex-end':'flex-start')+';margin-bottom:7px">'
     + '<div style="max-width:78%;background:'+bg+';border:1px solid var(--line);border-radius:11px;padding:7px 11px">'
     +   (meu?'<div class="dmini" style="color:var(--muted);margin-bottom:2px">'+esc(m.autor==='ia'?'Assistente':(m.autor_nome||'você'))+(m.tipo==='template'?' · template':'')+'</div>':'')
     +   '<div style="font-size:13px;white-space:pre-wrap">'+esc(m.texto||'')+'</div>'
     +   '<div class="dmini" style="color:var(--muted);margin-top:3px;text-align:right">'+esc(waQuando(m.quando))
     +     (m.status==='falhou'?' · <span style="color:var(--pend)">falhou</span>':'')+'</div>'
     + '</div></div>';
  }
  h+='</div>';

  if(c.estado!=='concluida'){
    h+='<div style="padding:9px;border-top:1px solid var(--line)">'
     + '<div style="display:flex;gap:6px"><input id="waTxt" placeholder="Escrever como Caprem…" style="flex:1;padding:7px 9px;font-size:13px" onkeydown="if(event.key===\'Enter\')waResponder()">'
     + '<button class="btn-prim" style="padding:7px 13px" onclick="waResponder()">Enviar</button></div>';
    if(d.modo==='simulador')
      h+='<div style="display:flex;gap:6px;margin-top:7px"><input id="waSim" placeholder="Responder COMO SE FOSSE o fornecedor…" style="flex:1;padding:7px 9px;font-size:13px;background:#fffdf5" onkeydown="if(event.key===\'Enter\')waSimular()">'
       + '<button class="btn-ghost" style="padding:7px 13px;white-space:nowrap" onclick="waSimular()">Como fornecedor</button></div>';
    h+='</div>';
  }
  return h;
}

function waPropostaPainel(){
  const d=WA.conv;
  if(!d||d.error||!d.conversa) return '<div class="dmini" style="color:var(--muted);padding:12px">—</div>';
  const c=d.conversa, p=c.proposta||{};
  const n=WA.neg, itens=(n&&n.itens)||[];
  const f=(n&&n.fornecedores||[]).find(x=>x.id===c.id)||{};
  let h='<div style="padding:10px 12px;border-bottom:1px solid var(--line);background:#f6f8f7"><b style="font-size:12.5px">Proposta do fornecedor</b></div>'
      + '<div style="overflow-y:auto;flex:1;padding:11px 12px">';
  if(!p.total && !p.prazo_entrega){
    h+='<div class="dmini" style="color:var(--muted)">Nada coletado ainda. O que a assistente extrair da conversa aparece aqui.</div>';
  } else {
    const linha=(r,v)=>v?'<div style="display:flex;justify-content:space-between;gap:8px;font-size:12.5px;margin-bottom:6px"><span class="muted">'+r+'</span><b style="text-align:right">'+esc(String(v))+'</b></div>':'';
    h+='<div style="background:#eef5f1;border-radius:9px;padding:10px;margin-bottom:10px;text-align:center">'
     + '<div class="dmini" style="color:var(--muted)">valor total</div>'
     + '<b style="font-size:19px;color:var(--verde-d)">'+esc(waRS(p.total))+'</b>'
     + (f.melhor?'<div><span class="dchip" style="background:var(--verde);margin-top:5px">melhor proposta</span></div>':'')
     + '</div>'
     + linha('Prazo de entrega',p.prazo_entrega) + linha('Pagamento',p.pagamento) + linha('Frete',p.frete);
  }
  if(f.nao_fornece&&f.nao_fornece.length){
    h+='<div style="border-top:1px dashed var(--line);margin-top:10px;padding-top:9px">'
     + '<div class="dmini" style="color:var(--muted);margin-bottom:5px">Não trabalha com</div>';
    for(const i of f.nao_fornece){
      const it=itens[(+i)-1];
      h+='<div class="dmini" style="color:var(--pend)">• '+esc(it?String(it.descricao).slice(0,44):('item '+i))+'</div>';
    }
    h+='</div>';
  }
  h+='<div class="dmini" style="color:var(--muted);margin-top:12px;border-top:1px dashed var(--line);padding-top:9px">'
   + 'Ainda <b>não entrou no mapa</b> — lançar a proposta é decisão do comprador.</div>';
  h+='</div>';
  return h;
}

/* ─────────────── AÇÕES ─────────────── */
async function waPost(body){
  return await (await fetch('actions/whats.php',{method:'POST',headers:{'Content-Type':'application/json'},
    body:JSON.stringify(Object.assign({me:EU&&EU.bitrix_id}, body))})).json();
}
async function waRecarregar(){
  const cid=WA.neg&&WA.neg.cotacao&&WA.neg.cotacao.id;
  if(!cid) return;
  try{ WA.neg=await (await fetch('actions/whats.php?negociacao='+cid+'&me='+encodeURIComponent((EU&&EU.bitrix_id)||''))).json(); }catch(e){}
  await waCarregarConversa(WA.convId);
}
async function waResponder(){
  const t=document.getElementById('waTxt'); const txt=t?t.value.trim():'';
  if(!txt||WA.enviando) return; WA.enviando=true; t.value='';
  const r=await waPost({acao:'responder', id:WA.convId, texto:txt});
  WA.enviando=false;
  if(r.error||r.erro){ toast(r.error||r.erro); return; }
  waRecarregar();
}
async function waSimular(){
  const t=document.getElementById('waSim'); const txt=t?t.value.trim():'';
  if(!txt||WA.enviando) return; WA.enviando=true; t.value=''; t.disabled=true;
  toast('A assistente está pensando…');
  const r=await waPost({acao:'simular_entrada', id:WA.convId, texto:txt});
  WA.enviando=false;
  if(r.error){ toast(r.error); t.disabled=false; return; }
  const a=r.assistente;
  if(a&&a.erro) toast('A assistente falhou: '+a.erro);
  else if(a&&a.acao==='chamar_humano') toast('A assistente pediu ajuda.');
  else if(a&&a.acao==='concluir') toast('A assistente encerrou com este fornecedor.');
  waRecarregar();
}
async function waAcao(acao){
  const rot={assumir:'assumir',devolver_ia:'devolver para a assistente',parar:'parar',retomar:'retomar',concluir:'encerrar'}[acao]||acao;
  if((acao==='parar'||acao==='concluir') && !confirm('Confirma '+rot+' esta conversa?')) return;
  const r=await waPost({acao, id:WA.convId});
  if(r.error){ toast(r.error); return; }
  if(r.promovida) toast('Encerrada — a próxima da fila com este número foi liberada.');
  waRecarregar();
}

/* Chamado do botão dentro da cotação: cria uma conversa por fornecedor convidado. */
async function waDispararCotacao(cotacaoId){
  if(!confirm('Iniciar a assistente no WhatsApp para os fornecedores convidados desta cotação?')) return;
  const r=await waPost({acao:'iniciar', cotacao_id:cotacaoId});
  if(r.error){ toast(r.error); return; }
  let m=(r.criadas||[]).length+' conversa(s) iniciada(s)';
  if((r.puladas||[]).length) m+=' · '+r.puladas.length+' pulada(s)';
  if(r.modo==='simulador') m+=' (simulador)';
  toast(m);
  if((r.puladas||[]).length){
    let h='<div style="max-width:620px"><div class="dmini" style="margin-bottom:9px">Estes fornecedores não entraram:</div><div class="wrap"><table style="width:100%;font-size:12.5px"><tbody>';
    for(const p of r.puladas) h+='<tr><td><b>'+esc(p.nome)+'</b></td><td class="muted">'+esc(p.motivo)+'</td></tr>';
    h+='</tbody></table></div><div class="bar" style="justify-content:flex-end;margin-top:12px"><button class="btn-prim" onclick="closeModal(true)">Entendi</button></div></div>';
    dlgAbrir('Assistente','Fornecedores pulados',h);
  }
}

/* ═══════════ CONFIGURAÇÃO » IA & WhatsApp (admin) ═══════════
   Duas coisas na mesma aba porque na prática são o mesmo assunto: quem responde o fornecedor
   (modelo) e por onde a mensagem sai (número). Chave e token NUNCA voltam do servidor — o campo
   vazio significa "não mexa", não "apagar". */
let WCFG = null;

async function wcfgLoad(){
  const w=document.getElementById('cfg-ia'); if(!w) return;
  w.innerHTML='<div class="dempty">Carregando…</div>';
  try{ WCFG=await (await fetch('actions/whats.php?config=1&me='+encodeURIComponent((EU&&EU.bitrix_id)||''))).json(); }
  catch(e){ WCFG={error:e.message}; }
  wcfgRender();
}

function wcfgRender(){
  const w=document.getElementById('cfg-ia'); if(!w) return;
  if(!WCFG||WCFG.error){ w.innerHTML='<div class="dempty">'+esc((WCFG&&WCFG.error)||'Falha')+'</div>'; return; }
  const wa=WCFG.wa, llm=WCFG.llm, cat=llm.catalogo||{};

  let h='';
  h+='<div class="dcard wide" style="margin-bottom:14px">'+cotSecHead('psychology','Modelo de IA','qual modelo responde o fornecedor','')
   + '<div class="dmini" style="color:var(--muted);margin-bottom:10px">Cada <b>perfil</b> pode usar um modelo diferente — dá para rodar a assistente num modelo barato e a extração de proposta num mais caro. '
   + 'O preço mostrado é de tabela e serve para <b>comparar</b>, não para conferir fatura.</div>';

  h+='<div style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end;border:1px solid var(--line);border-radius:10px;padding:11px;margin-bottom:10px">'
   + '<label style="flex:1 1 150px"><span style="font-size:10px;font-weight:700;text-transform:uppercase;color:var(--muted)">Provedor</span>'
   +   '<select id="wcProv" onchange="wcfgProvTrocou()" style="width:100%;padding:5px 8px;font-size:12.5px">'
   +     Object.keys(cat).map(k=>'<option value="'+k+'">'+esc(cat[k].nome)+'</option>').join('')+'</select></label>'
   + '<label style="flex:2 1 240px"><span style="font-size:10px;font-weight:700;text-transform:uppercase;color:var(--muted)">Chave / token</span>'
   +   '<input id="wcChave" type="password" placeholder="deixe vazio para manter a atual" style="width:100%;padding:5px 8px;font-size:12.5px;box-sizing:border-box"></label>'
   + '<button class="btn-ghost" style="padding:6px 12px;font-size:12.5px" onclick="wcfgSalvarChave()">Salvar chave</button>'
   + '<div id="wcChaveSt" class="dmini" style="color:var(--muted);align-self:center"></div></div>';

  const perfis={assistente:'Assistente do WhatsApp',oraculo:'Radar IA (oráculo)',extracao:'Extração de proposta',padrao:'Padrão (fallback)'};
  h+='<div class="wrap"><table style="width:100%;font-size:12.5px"><thead><tr><th>Perfil</th><th>Provedor</th><th>Modelo</th><th style="width:170px">Preço /1M tokens</th><th></th></tr></thead><tbody>';
  for(const k of Object.keys(perfis)){
    const p=llm.perfis[k];
    const modelos=(p&&cat[p.provedor]&&cat[p.provedor].modelos)||{};
    const preco=(p&&modelos[p.modelo])?('entrada US$ '+modelos[p.modelo].in+' · saída US$ '+modelos[p.modelo].out):'—';
    h+='<tr><td><b>'+esc(perfis[k])+'</b>'+(p&&p.herdado?'<div class="dmini" style="color:var(--dourado)">herdado do Radar IA</div>':'')+'</td>'
     + '<td>'+esc(p?(cat[p.provedor]?cat[p.provedor].nome:p.provedor):'—')+'</td>'
     + '<td>'+esc(p?p.modelo:'—')+'</td>'
     + '<td class="muted" style="font-size:11.5px">'+esc(preco)+'</td>'
     + '<td style="text-align:right;white-space:nowrap">'
     +   '<button class="btn-ghost" style="padding:3px 9px;font-size:11px" onclick="wcfgEditarPerfil(&quot;'+k+'&quot;)">Trocar</button> '
     +   '<button class="btn-ghost" style="padding:3px 9px;font-size:11px" onclick="wcfgTestar(&quot;'+k+'&quot;)">Testar</button>'
     + '</td></tr>';
  }
  h+='</tbody></table></div><div id="wcTeste" class="dmini" style="margin-top:8px"></div></div>';

  h+='<div class="dcard wide">'+cotSecHead('chat','WhatsApp (API oficial da Meta)','por onde a assistente fala','')
   + '<div style="display:flex;gap:14px;align-items:center;flex-wrap:wrap;margin-bottom:11px">'
   +   '<label style="display:flex;align-items:center;gap:6px;font-size:13px"><input type="radio" name="wcModo" value="simulador" '+(wa.modo==='simulador'?'checked':'')+'> <b>Simulador</b> <span class="muted">— nada sai de verdade</span></label>'
   +   '<label style="display:flex;align-items:center;gap:6px;font-size:13px"><input type="radio" name="wcModo" value="real" '+(wa.modo==='real'?'checked':'')+(wa.pronto_real?'':' disabled')+'> <b>Real</b> '
   +     (wa.pronto_real?'':'<span class="muted">— falta token e phone number id</span>')+'</label></div>';

  const campo=(id,rot,val,ph,tipo)=>'<label style="flex:1 1 210px"><span style="font-size:10px;font-weight:700;text-transform:uppercase;color:var(--muted)">'+rot+'</span>'
    +'<input id="'+id+'" type="'+(tipo||'text')+'" value="'+esc(val===null||val===undefined?'':String(val))+'" placeholder="'+esc(ph||'')+'" style="width:100%;padding:5px 8px;font-size:12.5px;box-sizing:border-box"></label>';
  h+='<div style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:10px">'
   + campo('wcNumero','Número exibido',wa.numero,'(19) 99999-8888')
   + campo('wcEmpresa','Nome da empresa na conversa',wa.empresa,'Caprem Construtora')
   + campo('wcPhoneId','Phone Number ID',wa.phone_number_id,'da Meta')
   + campo('wcWabaId','WABA ID',wa.waba_id,'da Meta')
   + campo('wcToken','Token de acesso','', wa.token_ok?'já cadastrado — vazio mantém':'cole o token permanente','password')
   + campo('wcTplNome','Template de abertura',wa.template_abertura,'nome aprovado na Meta')
   + campo('wcTplIdioma','Idioma do template',wa.template_idioma,'pt_BR')
   + campo('wcLimite','Limite de conversas novas/dia',wa.limite_dia,'250')
   + campo('wcCusto','Custo por template (R$)',wa.custo_template,'0.00')
   + campo('wcHi','Só disparar a partir de',wa.horario_ini,'08:00')
   + campo('wcHf','Só disparar até',wa.horario_fim,'18:00')
   + '</div>';
  h+='<div style="border-left:4px solid var(--dourado);background:#fdf9ec;padding:9px 12px;border-radius:0 8px 8px 0;font-size:12.5px;margin-bottom:10px">'
   + '<b>URL do webhook</b> (cole no painel da Meta):<br><code style="font-size:11.5px">'+esc(WCFG.webhook_url)+'</code>'
   + (wa.verify_token?'<br><b>Token de verificação:</b> <code style="font-size:11.5px">'+esc(wa.verify_token)+'</code>':'')
   + '<br><span class="muted">O número entra na API e <b>deixa de funcionar no WhatsApp normal</b> — use um número exclusivo.</span></div>';
  h+='<div class="bar" style="justify-content:flex-end"><button class="btn-prim" onclick="wcfgSalvarWa()">Salvar configuração</button></div></div>';

  w.innerHTML=h;
  wcfgProvTrocou();
}

function wcfgProvTrocou(){
  const s=document.getElementById('wcProv'), st=document.getElementById('wcChaveSt');
  if(!s||!st||!WCFG) return;
  st.textContent = (WCFG.llm.chaves_ok&&WCFG.llm.chaves_ok[s.value]) ? '✓ chave já cadastrada' : 'sem chave cadastrada';
}

async function wcfgSalvarChave(){
  const prov=document.getElementById('wcProv').value, chave=document.getElementById('wcChave').value.trim();
  if(!chave){ toast('Cole a chave'); return; }
  const r=await waPost({acao:'salvar_llm', provedor:prov, chave});
  if(r.error){ toast(r.error); return; }
  document.getElementById('wcChave').value=''; toast('Chave salva'); wcfgLoad();
}

function wcfgEditarPerfil(perfil){
  const cat=WCFG.llm.catalogo, p=WCFG.llm.perfis[perfil]||{};
  let h='<div style="max-width:520px">';
  h+='<div class="dmini" style="color:var(--muted);margin-bottom:10px">O modelo é campo livre: se o que você quer não está na lista, digite o id dele. '
   + 'Para provedor “compatível com OpenAI” informe também a URL do endpoint.</div>';
  h+='<label style="display:block;margin-bottom:9px"><span style="font-size:10px;font-weight:700;text-transform:uppercase;color:var(--muted)">Provedor</span>'
   + '<select id="wpProv" onchange="wcfgSugerirModelos()" style="width:100%;padding:6px 8px">'
   +   Object.keys(cat).map(k=>'<option value="'+k+'" '+((p.provedor===k)?'selected':'')+'>'+esc(cat[k].nome)+'</option>').join('')+'</select></label>';
  h+='<label style="display:block;margin-bottom:9px"><span style="font-size:10px;font-weight:700;text-transform:uppercase;color:var(--muted)">Modelo</span>'
   + '<input id="wpModelo" list="wpModelos" value="'+esc(p.modelo||'')+'" style="width:100%;padding:6px 8px;box-sizing:border-box">'
   + '<datalist id="wpModelos"></datalist></label>';
  h+='<label style="display:block;margin-bottom:9px"><span style="font-size:10px;font-weight:700;text-transform:uppercase;color:var(--muted)">URL (só p/ compatível)</span>'
   + '<input id="wpUrl" placeholder="https://…/v1/chat/completions" style="width:100%;padding:6px 8px;box-sizing:border-box"></label>';
  h+='<div class="bar" style="justify-content:flex-end;gap:8px;margin-top:12px">'
   + '<button class="btn-ghost" onclick="closeModal(true)">Cancelar</button>'
   + '<button class="btn-prim" onclick="wcfgSalvarPerfil(&quot;'+perfil+'&quot;)">Salvar</button></div></div>';
  dlgAbrir('Configurações','Modelo do perfil: '+perfil, h);
  wcfgSugerirModelos();
}
function wcfgSugerirModelos(){
  const s=document.getElementById('wpProv'), dl=document.getElementById('wpModelos');
  if(!s||!dl) return;
  const ms=(WCFG.llm.catalogo[s.value]||{}).modelos||{};
  dl.innerHTML=Object.keys(ms).map(k=>'<option value="'+esc(k)+'">'+esc(ms[k].nome)+'</option>').join('');
}
async function wcfgSalvarPerfil(perfil){
  const r=await waPost({acao:'salvar_llm', perfil,
    perfil_provedor:document.getElementById('wpProv').value,
    perfil_modelo:document.getElementById('wpModelo').value.trim(),
    perfil_url:document.getElementById('wpUrl').value.trim()});
  if(r.error){ toast(r.error); return; }
  closeModal(true); toast('Perfil atualizado'); wcfgLoad();
}

async function wcfgTestar(perfil){
  const el=document.getElementById('wcTeste'); if(el) el.textContent='Testando '+perfil+'…';
  const r=await waPost({acao:'testar_llm', perfil});
  if(!el) return;
  if(r.error){ el.innerHTML='<span style="color:var(--pend)">'+esc(r.error)+'</span>'; return; }
  el.innerHTML = r.ok
    ? '<span style="color:var(--verde-d)">✓ <b>'+esc(perfil)+'</b> respondeu por '+esc(r.provedor)+'/'+esc(r.modelo)+' em '+r.ms+' ms · '+r.tokens+' tokens · US$ '+(+r.custo).toFixed(6)+'</span>'
    : '<span style="color:var(--pend)">✗ '+esc(r.erro)+'</span>';
}

async function wcfgSalvarWa(){
  const v=id=>{const e=document.getElementById(id);return e?e.value.trim():'';};
  const modo=(document.querySelector('input[name="wcModo"]:checked')||{}).value||'simulador';
  const r=await waPost({acao:'salvar_cfg', modo,
    numero:v('wcNumero'), empresa:v('wcEmpresa'), phone_number_id:v('wcPhoneId'), waba_id:v('wcWabaId'),
    token:v('wcToken'), template_abertura:v('wcTplNome'), template_idioma:v('wcTplIdioma')||'pt_BR',
    limite_dia:v('wcLimite'), custo_template:v('wcCusto'), horario_ini:v('wcHi'), horario_fim:v('wcHf')});
  if(r.error){ toast(r.error); return; }
  toast('Configuração salva · modo '+r.modo); wcfgLoad();
}
