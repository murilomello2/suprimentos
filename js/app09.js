/* ═══════════ ASSISTENTE DE WHATSAPP — kanban + conversa ═══════════
   O kanban não é funil de venda: as colunas são ESTADOS DA CONVERSA. "Em fila" existe porque o
   WhatsApp tem uma thread por número — se o mesmo fornecedor está em duas cotações, uma espera.
   "Dúvida IA" é onde a assistente desiste e chama gente, que é o comportamento que a gente quer:
   melhor ela parar do que improvisar em nome da Caprem. */

let WA = { kanban:null, conv:null, q:'', carregando:false, enviando:false };

const WA_COR = { em_fila:'#8a9299', aguardando:'#6b8fb5', ativa:'var(--verde)',
                 duvida_ia:'var(--pend)', parada:'#c0392b', concluida:'#5a6b60', falhou:'#c0392b' };
const WA_ICONE = { em_fila:'schedule', aguardando:'hourglass_empty', ativa:'chat',
                   duvida_ia:'priority_high', parada:'pause_circle', concluida:'check_circle', falhou:'error' };

function waInit(){ waKanban(); }

async function waKanban(){
  WA.carregando=true; waRender();
  try{
    const p=new URLSearchParams({kanban:'1', me:(EU&&EU.bitrix_id)||''});
    if(WA.q) p.set('q',WA.q);
    WA.kanban=await (await fetch('actions/whats.php?'+p)).json();
  }catch(e){ WA.kanban={error:'Falha ao carregar: '+e.message}; }
  WA.carregando=false; waRender();
}

function waBusca(){ const e=document.getElementById('waQ'); WA.q=e?e.value.trim():''; waKanban(); }

function waQuando(iso){
  if(!iso) return '';
  const d=new Date(iso); if(isNaN(d)) return '';
  const min=Math.floor((Date.now()-d.getTime())/60000);
  if(min<1) return 'agora'; if(min<60) return min+' min';
  const h=Math.floor(min/60); if(h<24) return h+'h';
  return Math.floor(h/24)+'d';
}

function waRender(){
  const w=document.getElementById('waWrap'); if(!w) return;
  const k=WA.kanban;
  if(WA.carregando && !k){ w.innerHTML='<div class="dempty">Carregando…</div>'; return; }
  if(!k || k.error){ w.innerHTML='<div class="dempty">'+esc((k&&k.error)||'Falha')+'</div>'; return; }

  let h='';
  if(k.modo==='simulador')
    h+='<div style="border-left:4px solid var(--dourado);background:#fdf9ec;padding:9px 12px;border-radius:0 8px 8px 0;font-size:12.5px;margin-bottom:12px">'
     + '<b>Modo simulador.</b> Nenhuma mensagem sai de verdade — dá para testar a conversa inteira antes de o número da Meta existir. '
     + 'Dentro da conversa você pode escrever <b>como se fosse o fornecedor</b> e ver a assistente responder. '
     + (IS_ADMIN?'O modo real liga em Configurações › IA &amp; WhatsApp.':'')+'</div>';

  h+='<div style="display:flex;gap:8px;align-items:center;margin-bottom:12px;flex-wrap:wrap">'
   + '<div class="search" style="border:1px solid var(--line);flex:1 1 260px;max-width:420px"><span class="material-icons" style="color:var(--muted)">search</span>'
   + '<input id="waQ" value="'+esc(WA.q)+'" placeholder="fornecedor, número ou cotação…" onkeydown="if(event.key===\'Enter\')waBusca()"></div>'
   + '<button class="btn-ghost" style="padding:6px 12px;font-size:12.5px" onclick="waBusca()">Filtrar</button>'
   + '<button class="btn-ghost" style="padding:6px 12px;font-size:12.5px" onclick="waKanban()"><span class="material-icons" style="font-size:15px;vertical-align:-3px">refresh</span> Atualizar</button>'
   + '</div>';

  const ordem=['duvida_ia','ativa','aguardando','em_fila','parada','concluida','falhou'];
  h+='<div style="display:flex;gap:10px;overflow-x:auto;padding-bottom:6px;align-items:flex-start">';
  let vazio=true;
  for(const est of ordem){
    const cs=(k.colunas&&k.colunas[est])||[];
    if(!cs.length && ['falhou','parada','concluida'].includes(est)) continue;   // colunas frias só aparecem com conteúdo
    if(cs.length) vazio=false;
    const cor=WA_COR[est]||'#8a9299';
    h+='<div style="flex:0 0 268px;background:#f6f8f7;border-radius:11px;padding:9px;max-height:66vh;display:flex;flex-direction:column">'
     + '<div style="display:flex;align-items:center;gap:6px;padding:2px 4px 9px">'
     +   '<span class="material-icons" style="font-size:16px;color:'+cor+'">'+(WA_ICONE[est]||'chat')+'</span>'
     +   '<b style="font-size:12.5px">'+esc(k.rotulos[est]||est)+'</b>'
     +   '<span style="margin-left:auto;background:'+cor+';color:#fff;border-radius:9px;padding:0 7px;font-size:11px;font-weight:700">'+cs.length+'</span></div>'
     + '<div style="overflow-y:auto;display:flex;flex-direction:column;gap:7px">';
    for(const c of cs){
      h+='<div onclick="waAbrir('+c.id+')" style="background:#fff;border:1px solid var(--line);border-left:3px solid '+cor+';border-radius:9px;padding:8px 10px;cursor:pointer">'
       + '<div style="display:flex;align-items:center;gap:5px"><b style="font-size:12.5px;flex:1;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">'+esc(c.fornecedor||'—')+'</b>'
       +   (c.nao_lidas?'<span style="background:var(--verde);color:#fff;border-radius:9px;padding:0 6px;font-size:10.5px;font-weight:700">'+c.nao_lidas+'</span>':'')+'</div>'
       + '<div class="dmini" style="color:var(--muted);margin-top:2px">'+esc(c.numero||'')+'</div>'
       + (c.cotacao?'<div class="dmini" style="color:var(--muted);margin-top:2px">'+esc(String(c.cotacao).slice(0,34))+(c.obra?(' · '+esc(c.obra)):'')+'</div>':'')
       + (c.fila_pos!==null&&c.fila_pos!==undefined?'<div class="dmini" style="color:var(--dourado);margin-top:3px">aguardando a vez · posição '+c.fila_pos+'</div>':'')
       + (est==='duvida_ia'&&c.motivo?'<div class="dmini" style="color:var(--pend);margin-top:3px">'+esc(String(c.motivo).slice(0,70))+'</div>':'')
       + '<div style="display:flex;align-items:center;gap:6px;margin-top:5px">'
       +   (c.dono==='humano'?'<span class="dchip" style="background:#6b8fb5">na mão</span>':'')
       +   (!c.janela_aberta&&['ativa','aguardando'].includes(est)?'<span class="dchip" style="background:#8a9299" title="fora das 24h: só template aprovado passa">janela fechada</span>':'')
       +   '<span class="dmini" style="margin-left:auto;color:var(--muted)">'+esc(waQuando(c.ultima))+'</span></div>'
       + '</div>';
    }
    if(!cs.length) h+='<div class="dmini" style="color:var(--muted);padding:6px 4px">vazio</div>';
    h+='</div></div>';
  }
  h+='</div>';
  if(vazio) h+='<div class="dempty" style="margin-top:14px">Nenhuma conversa ainda. Abra uma cotação, convide os fornecedores e use <b>“Disparar no WhatsApp”</b>.</div>';
  w.innerHTML=h;
}

/* ───────────── CONVERSA ───────────── */
async function waAbrir(id){
  dlgAbrir('Assistente','Conversa','<div style="max-width:820px"><div class="dempty">Abrindo…</div></div>');
  try{ WA.conv=await (await fetch('actions/whats.php?conversa='+id+'&me='+encodeURIComponent((EU&&EU.bitrix_id)||''))).json(); }
  catch(e){ WA.conv={error:e.message}; }
  waConvRender();
}

function waConvRender(){
  const d=WA.conv;
  if(!d || d.error){ dlgAbrir('Assistente','Conversa','<div style="max-width:600px"><div class="dempty">'+esc((d&&d.error)||'Falha')+'</div></div>'); return; }
  const c=d.conversa;
  let h='<div style="max-width:840px">';

  h+='<div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;border:1px solid var(--line);border-radius:10px;padding:10px 12px;margin-bottom:10px">'
   + '<div style="min-width:0"><b style="font-size:14.5px">'+esc(c.fornecedor||'—')+'</b>'
   +   '<div class="dmini" style="color:var(--muted)">'+esc(c.numero)+(c.cotacao?(' · '+esc(c.cotacao)):'')+(c.obra?(' · '+esc(c.obra)):'')+'</div></div>'
   + '<div style="margin-left:auto;display:flex;gap:6px;align-items:center;flex-wrap:wrap">'
   +   '<span class="dchip" style="background:'+(WA_COR[c.estado]||'#8a9299')+'">'+esc((WA.kanban&&WA.kanban.rotulos&&WA.kanban.rotulos[c.estado])||c.estado)+'</span>'
   +   (c.dono==='humano'?'<span class="dchip" style="background:#6b8fb5">você assumiu</span>':'<span class="dchip" style="background:var(--verde)">assistente</span>')
   + '</div></div>';

  if(!c.janela_aberta)
    h+='<div style="border-left:4px solid var(--dourado);background:#fdf9ec;padding:8px 12px;border-radius:0 8px 8px 0;font-size:12.5px;margin-bottom:10px">'
     + '<b>Janela de 24h fechada.</b> A Meta só deixa mandar texto livre até 24h depois da última mensagem do fornecedor. '
     + 'Agora, só um <b>template aprovado</b> reabre a conversa.</div>';
  if(c.motivo)
    h+='<div style="border-left:4px solid var(--pend);background:#fdeeee;padding:8px 12px;border-radius:0 8px 8px 0;font-size:12.5px;margin-bottom:10px">'
     + '<b>A assistente pediu ajuda:</b> '+esc(c.motivo)+'</div>';

  // ── histórico ──
  h+='<div id="waMsgs" style="border:1px solid var(--line);border-radius:10px;padding:12px;max-height:44vh;overflow:auto;background:#f7f9f8">';
  for(const m of (d.mensagens||[])){
    if(m.tipo==='sistema'){
      h+='<div class="dmini" style="text-align:center;color:var(--muted);margin:8px 0">'+esc(m.texto)+'</div>'; continue;
    }
    const meu=m.direcao==='out';
    const bg = meu ? (m.autor==='ia'?'#e3f0e8':'#dbe7f3') : '#fff';
    h+='<div style="display:flex;justify-content:'+(meu?'flex-end':'flex-start')+';margin-bottom:7px">'
     + '<div style="max-width:74%;background:'+bg+';border:1px solid var(--line);border-radius:11px;padding:7px 11px">'
     +   (meu?'<div class="dmini" style="color:var(--muted);margin-bottom:2px">'+esc(m.autor==='ia'?'Assistente':(m.autor_nome||'você'))+(m.tipo==='template'?' · template':'')+'</div>':'')
     +   '<div style="font-size:13px;white-space:pre-wrap">'+esc(m.texto||'')+'</div>'
     +   '<div class="dmini" style="color:var(--muted);margin-top:3px;text-align:right">'+esc(waQuando(m.quando))
     +     (m.status==='falhou'?' · <span style="color:var(--pend)">falhou</span>':'')+'</div>'
     +   (m.erro?'<div class="dmini" style="color:var(--pend)">'+esc(m.erro)+'</div>':'')
     + '</div></div>';
  }
  h+='</div>';

  // ── proposta coletada ──
  if(c.proposta && (c.proposta.total || c.proposta.prazo_entrega)){
    const p=c.proposta;
    h+='<div style="border:1px solid var(--line);border-radius:10px;padding:10px 12px;margin-top:10px;background:#fbfdfc">'
     + '<b style="font-size:12.5px">O que a assistente coletou</b>'
     + '<div style="display:flex;gap:16px;flex-wrap:wrap;margin-top:6px;font-size:12.5px">'
     +   (p.total?'<div><span class="muted">total</span> <b>R$ '+esc(String(p.total))+'</b></div>':'')
     +   (p.prazo_entrega?'<div><span class="muted">entrega</span> <b>'+esc(p.prazo_entrega)+'</b></div>':'')
     +   (p.pagamento?'<div><span class="muted">pagamento</span> <b>'+esc(p.pagamento)+'</b></div>':'')
     +   (p.frete?'<div><span class="muted">frete</span> <b>'+esc(p.frete)+'</b></div>':'')
     + '</div>'
     + '<div class="dmini" style="color:var(--muted);margin-top:6px">Ainda não entrou no mapa — a decisão de lançar é do comprador.</div></div>';
  }

  // ── ações ──
  const podeEscrever = c.estado!=='concluida';
  h+='<div style="margin-top:10px">';
  if(podeEscrever){
    h+='<div style="display:flex;gap:6px;align-items:flex-end">'
     + '<textarea id="waTxt" rows="2" placeholder="Escrever como Caprem…" style="flex:1;padding:7px 9px;font-size:13px;resize:vertical;box-sizing:border-box"></textarea>'
     + '<button class="btn-prim" style="padding:8px 14px" onclick="waResponder()">Enviar</button></div>';
    if(d.modo==='simulador')
      h+='<div style="display:flex;gap:6px;align-items:flex-end;margin-top:8px;border-top:1px dashed var(--line);padding-top:8px">'
       + '<textarea id="waSim" rows="2" placeholder="Escrever COMO SE FOSSE o fornecedor (só no simulador)…" style="flex:1;padding:7px 9px;font-size:13px;background:#fffdf5;resize:vertical;box-sizing:border-box"></textarea>'
       + '<button class="btn-ghost" style="padding:8px 14px" onclick="waSimular()">Responder como fornecedor</button></div>';
  }
  h+='<div class="bar" style="gap:6px;margin-top:12px;flex-wrap:wrap">'
   + (c.dono==='ia'
      ? '<button class="btn-ghost" style="padding:5px 12px;font-size:12.5px" onclick="waAcao(\'assumir\')">Assumir na mão (para a assistente)</button>'
      : '<button class="btn-ghost" style="padding:5px 12px;font-size:12.5px" onclick="waAcao(\'devolver_ia\')">Devolver para a assistente</button>')
   + (c.estado!=='parada'?'<button class="btn-ghost" style="padding:5px 12px;font-size:12.5px" onclick="waAcao(\'parar\')">Parar</button>':'<button class="btn-ghost" style="padding:5px 12px;font-size:12.5px" onclick="waAcao(\'retomar\')">Retomar</button>')
   + (c.estado!=='concluida'?'<button class="btn-ghost" style="padding:5px 12px;font-size:12.5px" onclick="waAcao(\'concluir\')">Encerrar</button>':'')
   + '<button class="btn-prim" style="margin-left:auto;padding:5px 12px;font-size:12.5px" onclick="closeModal(true);waKanban()">Fechar</button></div>';
  h+='</div></div>';
  dlgAbrir('Assistente','Conversa', h);
  const box=document.getElementById('waMsgs'); if(box) box.scrollTop=box.scrollHeight;
}

async function waPost(body){
  const r=await (await fetch('actions/whats.php',{method:'POST',headers:{'Content-Type':'application/json'},
    body:JSON.stringify(Object.assign({me:EU&&EU.bitrix_id}, body))})).json();
  return r;
}

async function waResponder(){
  const t=document.getElementById('waTxt'); const txt=t?t.value.trim():'';
  if(!txt) return;
  if(WA.enviando) return; WA.enviando=true;
  const r=await waPost({acao:'responder', id:WA.conv.conversa.id, texto:txt});
  WA.enviando=false;
  if(r.error||r.erro){ toast(r.error||r.erro); return; }
  waAbrir(WA.conv.conversa.id);
}

async function waSimular(){
  const t=document.getElementById('waSim'); const txt=t?t.value.trim():'';
  if(!txt) return;
  if(WA.enviando) return; WA.enviando=true;
  t.disabled=true; t.value='';
  toast('A assistente está pensando…');
  const r=await waPost({acao:'simular_entrada', id:WA.conv.conversa.id, texto:txt});
  WA.enviando=false;
  if(r.error){ toast(r.error); t.disabled=false; return; }
  const a=r.assistente;
  if(a&&a.erro) toast('A assistente falhou: '+a.erro);
  else if(a&&a.acao==='chamar_humano') toast('A assistente pediu ajuda — a conversa foi para “Dúvida IA”.');
  else if(a&&a.acao==='concluir') toast('A assistente encerrou a conversa.');
  waAbrir(WA.conv.conversa.id);
}

async function waAcao(acao){
  const rot={assumir:'assumir a conversa',devolver_ia:'devolver para a assistente',parar:'parar',retomar:'retomar',concluir:'encerrar'}[acao]||acao;
  if((acao==='parar'||acao==='concluir') && !confirm('Confirma '+rot+' esta conversa?')) return;
  const r=await waPost({acao, id:WA.conv.conversa.id});
  if(r.error){ toast(r.error); return; }
  if(r.promovida) toast('Conversa encerrada — a próxima da fila com este número foi liberada.');
  waAbrir(WA.conv.conversa.id);
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
