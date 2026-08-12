/* Cockpit de Suprimentos — parte 4 de 6 do aplicativo.
   Gerado a partir do bloco unico que vivia dentro do index.php: 857 KB num arquivo so faziam
   cada deploy levar de 5 a 10 minutos e falhar calado. O corte respeita fronteiras de nivel
   superior e cada parte foi validada pelo parser antes de existir. A ORDEM importa: os
   arquivos sao carregados na sequencia em que foram cortados. */
async function cotDetectarPedido(c){ const CAN_EDIT=cotEditavel();
  const host=document.getElementById('cotPedDetect'); if(!host||!c||!c.num_solicitacao)return;
  try{ const r=await (await fetch('actions/pedidos.php?solicitacao='+encodeURIComponent(c.num_solicitacao)+'&coligada='+encodeURIComponent(c.solic_coligada||'')+'&colidmov='+encodeURIComponent(c.solic_colidmov||'')+'&me='+encodeURIComponent((EU&&EU.bitrix_id)||''))).json();
    const peds=(r&&r.pedidos)||[];
    const salvo=(c.num_pedido&&String(c.num_pedido).trim())?String(c.num_pedido).trim():'';
    if(!peds.length){
      // sem PC vinculado (a SC ainda está em aberto). Se há um PC salvo, é provavelmente o vínculo ANTIGO errado (outra coligada) → avisa, não apaga.
      host.innerHTML = salvo ? `<span class="dchip" style="background:#fff3e0;color:#a15c00;font-weight:700" title="O nº de PC salvo não corresponde a esta solicitação/coligada. A SC provavelmente ainda está em aberto (sem pedido). Confira e, se for o caso, limpe o campo Pedido.">⚠ PC salvo (${esc(salvo)}) não confere com esta solicitação — a SC parece estar em aberto</span>` : '';
      return;
    }
    const nums=peds.map(p=>p.pedido_numero);
    // autopreenche o campo PC só se estiver vazio (agora o vínculo é seguro por colidmov)
    if(CAN_EDIT && !salvo){
      const joined=nums.join(', '); c.num_pedido=joined; const inp=document.getElementById('cotDetPC'); if(inp)inp.value=joined;
      try{ await fetch('actions/cotacoes.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({acao:'numeros_salvar',me:EU&&EU.bitrix_id,cotacao_id:c.id,num_solicitacao:c.num_solicitacao,num_pedido:joined})}); }catch(e){}
      toast(nums.length>1?(nums.length+' pedidos vinculados pela solicitação'):('Pedido '+nums[0].replace(/^0+/,'')+' vinculado pela solicitação'));
    }
    host.innerHTML='<span class="muted" style="font-size:11px;font-weight:700">Pedido(s) desta solicitação:</span> '+peds.map(p=>`<span class="dchip" style="background:#eef4f0;color:var(--verde-d);font-weight:700;cursor:pointer;margin-right:5px" onclick="cotPedidoVer('${esc(p.pedido_numero)}','${esc(p.coligada_cod||'')}')" title="${p.n_itens} item(ns)${p.coligada?' · '+esc(p.coligada):''} · ver detalhes">PC ${esc(String(p.pedido_numero).replace(/^0+/,''))}${p.status?' · '+esc(p.status):''} <span class="material-icons" style="font-size:12px;vertical-align:-2px">visibility</span></span>`).join('');
  }catch(e){}
}
/* ===== E-MAIL FASE 4 — ler respostas (inbound): buscar na caixa, listar, usar rascunho, marcar lido ===== */
async function cotInboxBuscar(){
  const c=(COT.cur||{}).cotacao; if(!c)return;
  toast('Buscando respostas na caixa…');
  try{ const r=await (await fetch('actions/inbox.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({acao:'varrer',me:EU&&EU.bitrix_id})})).json();
    if(r.error){toast(r.error);return;}
    if(r.throttled){toast(r.msg||'Verifiquei agora há pouco.'); if(c)cotOpen(c.id); return;}
    const parts=[]; if(r.novas)parts.push(r.novas+' nova(s)'); if(r.casadas)parts.push(r.casadas+' casada(s)'); if(r.cotacoes)parts.push(r.cotacoes+' cotação'); if(r.duvidas)parts.push(r.duvidas+' dúvida(s)'); if(r.sem_match)parts.push(r.sem_match+' sem vínculo');
    toast((r.lidas!=null?('Caixa: '+r.lidas+' lida(s)'):'Busca concluída')+(parts.length?' · '+parts.join(' · '):(r.novas?'':' · nada novo')));
    if(r.avisos&&r.avisos.length) setTimeout(()=>toast(r.avisos[0]),1500);
    cotOpen(c.id);   // recarrega: os cards atualizam o estado e o painel da caixa recarrega
  }catch(e){toast('Falha: '+e.message);}
}
async function cotInboxLoad(cid){ const CAN_EDIT=cotEditavel();
  const host=document.getElementById('cotInboxPanel'); if(!host||!cid)return;
  try{ const r=await (await fetch('actions/inbox.php?listar=1&cotacao='+cid+'&me='+encodeURIComponent((EU&&EU.bitrix_id)||''))).json();
    if((((COT.cur||{}).cotacao||{}).id)!=cid) return;   // o usuário já trocou de cotação — não sobrescreve o painel/estado da atual
    const its=(r&&r.itens)||[]; if(!its.length){ host.innerHTML=''; return; }
    COT.inbox=its; const meB=(EU&&EU.bitrix_id)||'';
    const novos=its.filter(m=>m.status==='novo').length, col=cotColapsado('inbox');
    const head=cotSecHead('inbox','Respostas por e-mail','a IA leu a caixa e classificou — valide antes de usar',(novos?`<span class="dchip" style="background:var(--pend);color:#fff">${novos} não processada(s)</span> `:'')+cotChevron('inbox'));
    if(col){ host.innerHTML=`<div class="panel" style="margin-bottom:10px">${head}<div style="font-size:12.5px;color:var(--muted)"><b>${its.length}</b> e-mail(s)${novos?` · <b style="color:var(--pend)">${novos}</b> não processada(s) — expanda p/ ver e incluir no mapa`:' · todas tratadas'}</div></div>`; return; }
    const tipoChip=t=>t==='cotacao'?'<span class="dchip" style="background:#1f7a44;color:#fff">COTAÇÃO</span>':(t==='duvida'?'<span class="dchip" style="background:var(--pend);color:#fff">DÚVIDA</span>':(t==='fora_de_escopo'?'<span class="dchip" style="background:#8a9299;color:#fff">FORA DE ESCOPO</span>':'<span class="dchip" style="background:#5b6b7a;color:#fff">'+esc(String(t||'?').toUpperCase())+'</span>'));
    // 1 e-mail = 1 card; AGRUPADOS por fornecedor (sequência), mais recente primeiro
    const rowOf=(m,i)=>{ const anx=String(m.anexos_ids||'').split(',').filter(Boolean); const done=(m.status==='lido'||m.status==='convertido'||m.status==='ignorado');
      return `<div style="${done?'opacity:.6;':''}">
        <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap">
          ${tipoChip(m.tipo)}
          <span class="muted" style="font-size:11px;flex:1;min-width:80px">${m.data_email?D(String(m.data_email).slice(0,10)):''}${m.match_metodo==='heuristica'?' · vínculo '+esc(m.match_confianca||''):''}</span>
          ${m.tem_anexo?`<span class="dchip" style="background:#eef4f0;color:var(--verde-d)"><span class="material-icons" style="font-size:11px;vertical-align:-2px">attach_file</span> ${anx.length||1}</span>`:''}
          ${done?`<span class="dchip" style="background:#8a9299;color:#fff">✓ ${esc(m.status)}</span>`:''}
        </div>
        ${m.resumo?`<div style="font-size:12.5px;margin-top:4px">${esc(m.resumo)}</div>`:''}
        <div style="display:flex;gap:6px;flex-wrap:wrap;margin-top:6px">
          ${m.tem_rascunho&&CAN_EDIT?`<button class="${done?'btn-ghost':'btn-prim'}" style="padding:3px 11px" onclick="cotInboxUsarRascunho(${i})" title="abre a proposta pré-preenchida pela IA (rascunho — confira e salve)"><span class="material-icons" style="font-size:13px;vertical-align:-2px">auto_awesome</span> ${done?'usar de novo':'Usar rascunho'}</button>`:''}
          ${anx.map(id=>`<a class="btn-ghost" style="padding:3px 9px;text-decoration:none" href="actions/cotacao_anexo.php?download=${id}&me=${encodeURIComponent(meB)}" target="_blank" rel="noopener"><span class="material-icons" style="font-size:13px;vertical-align:-2px">description</span> anexo</a>`).join('')}
          ${m.corpo_preview?`<button class="btn-ghost" style="padding:3px 9px" onclick="cotInboxVerCorpo(${i})">ver e-mail</button>`:''}
          ${!done?`<button class="btn-ghost" style="padding:3px 9px;color:var(--muted)" onclick="cotInboxMarcar(${m.id},'marcar_lido')">marcar lido</button>`:''}
        </div></div>`; };
    const groups={}, order=[];
    its.forEach((m,i)=>{ const k=(m.fornecedor_nome||m.from_email||'—'); if(!groups[k]){groups[k]=[];order.push(k);} groups[k].push({m,i}); });
    const groupsHtml=order.map(k=>{ const arr=groups[k].slice().sort((a,b)=>String(b.m.data_email||'').localeCompare(String(a.m.data_email||''))); const latest=arr[0].m, nn=arr.filter(x=>x.m.status==='novo').length;
      return `<div style="border:1px solid var(--line);border-radius:10px;background:#fff;overflow:hidden">
        <div style="display:flex;align-items:center;gap:8px;padding:8px 11px;background:#f6f9f7;border-bottom:1px solid var(--line)">
          <span class="dgm" style="background:${latest.tipo==='cotacao'?'#1f7a44':(latest.tipo==='duvida'?'var(--pend)':'#8a9299')}"></span>
          <b style="flex:1;min-width:120px">${esc(k)}</b>
          <span class="muted" style="font-size:11px">${arr.length} e-mail(s)</span>
          ${nn?`<span class="dchip" style="background:var(--pend);color:#fff">${nn} nova(s)</span>`:''}
        </div>
        <div style="display:flex;flex-direction:column;gap:8px;padding:9px 11px">${arr.map(x=>rowOf(x.m,x.i)).join('')}</div>
      </div>`; }).join('');
    host.innerHTML=`<div class="panel" style="margin-bottom:10px">${head}<div style="display:flex;flex-direction:column;gap:9px">${groupsHtml}</div>
      <div class="dmini" style="margin-top:7px">⚠ Vínculo e classificação são sugestões da IA sobre e-mails (conteúdo não confiável). Confira antes de gerar a proposta; dúvidas nunca viram rascunho.</div></div>`;
  }catch(e){ host.innerHTML=''; }
}
function cotInboxUsarRascunho(i){ const m=(COT.inbox||[])[i]; if(!m||!m.draft){toast('Sem rascunho neste e-mail');return;}
  if(m.id) fetch('actions/inbox.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({acao:'converter',me:EU&&EU.bitrix_id,id:m.id})}).catch(()=>{});   // usei o rascunho → marca tratado
  m.status='convertido'; cotIAAplicar(m.fornecedor_nome||'', m.draft, {}); }
function cotInboxVerCorpo(i){ const m=(COT.inbox||[])[i]; if(!m)return; alert('De: '+(m.from_nome||'')+' <'+(m.from_email||'')+'>\nAssunto: '+(m.assunto||'')+'\n\n'+(m.corpo_preview||'(sem corpo)')); }
async function cotInboxMarcar(id,acao){ try{ const r=await (await fetch('actions/inbox.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({acao,me:EU&&EU.bitrix_id,id})})).json(); if(r&&r.error){toast(r.error);return;} const c=(COT.cur||{}).cotacao; if(c)cotInboxLoad(c.id); }catch(e){toast('Falha: '+e.message);} }
function upCard(label,val,sub,color){ return `<div style="border:1px solid var(--line);border-radius:9px;padding:10px 12px;background:#fff">
  <div style="font-size:10px;text-transform:uppercase;letter-spacing:.4px;color:#889;font-weight:700">${esc(label)}</div>
  <div style="font-size:17px;font-weight:800;color:${color};margin-top:3px">${val}</div>
  ${sub?`<div style="font-size:10.5px;color:#889;margin-top:1px">${esc(sub)}</div>`:''}</div>`; }
// OPÇÃO do fornecedor (quando ele mandou a proposta de mais de uma forma) e OBSERVAÇÃO GERAL dele —
// as duas saem junto do nome no comparativo impresso, como no mapa antigo.
function upOpc(p){ return (p.opcao||1)>1?`<div style="font-weight:700;font-size:9px;color:#2a5d8f">opção ${p.opcao}${p.opcao_rotulo?' · '+esc(p.opcao_rotulo):''}</div>`:''; }
// nome da coluna quando o fornecedor tem mais de uma opção (senão duas colunas ficariam com o mesmo nome)
function cotPropNome(p){ return esc(p.fornecedor_nome)+((p.opcao||1)>1?` <span style="font-weight:400;font-size:9.5px;color:#2a5d8f">· opção ${p.opcao}${p.opcao_rotulo?' ('+esc(p.opcao_rotulo)+')':''}</span>`:'')+upDesq(p); }
/* Selo impresso da proposta DESQUALIFICADA. No papel não há tooltip nem cor de fundo confiável:
   o motivo vai escrito por extenso embaixo do nome, senão o mapa impresso "escolhe o caro" sem explicar. */
function upDesq(p){ if(!cotDesq(p)) return '';
  return `<div style="font-weight:800;font-size:8.5px;color:#b3261e;margin-top:2px">DESQUALIFICADA</div>`
    + (p.desq_texto?`<div style="font-weight:400;font-size:8px;color:#8a5b57;line-height:1.3;white-space:normal">${esc(p.desq_texto)}</div>`:''); }
function upObsG(p){ const o=(p.observacoes||'').trim(); return o?`<div style="margin-top:3px;background:#eef1f3;border:1px solid #dde2e6;border-radius:5px;padding:4px 6px;font-size:8.5px;font-weight:400;color:#4a5560;text-align:left;line-height:1.35;white-space:pre-wrap">${esc(o)}</div>`:''; }
// Comparativo de PREÇOS adaptativo: se há mais FORNECEDORES que itens, vira a tabela (fornecedores nas LINHAS,
// ranqueados pelo total) — fica uma lista vertical que cabe na página; senão itens nas linhas (estilo clássico).
function upPrecos(itens,props,m,best,verba){
  const melhor=Number(m.melhor_total)||0;
  let h=`<div style="font-weight:800;font-size:14px;margin:14px 0 8px;color:var(--verde-d)">Comparativo de Preços</div><div style="overflow-x:auto"><table class="up-tbl">`;
  if(props.length>itens.length && props.length>=5){
    // ---- FORNECEDORES nas linhas (ranking por total) ----
    // desqualificadas vão para o fim do ranking e não disputam o troféu (não são a "mais barata")
    const ranked=props.slice().sort((a,b)=>((cotDesq(a)?1:0)-(cotDesq(b)?1:0))||((a.total==null?Infinity:a.total)-(b.total==null?Infinity:b.total)));
    let cheapest=null; ranked.forEach(p=>{ if(!cotDesq(p)&&p.total!=null&&(cheapest==null||p.total<cheapest))cheapest=p.total; });
    h+=`<thead><tr><th style="width:26px">#</th><th style="text-align:left;min-width:150px">Fornecedor</th>`;
    itens.forEach(it=>{ const dsc=String(it.descricao||''); h+=`<th style="min-width:88px" title="${esc(dsc)}">${esc(dsc.slice(0,36))}${dsc.length>36?'…':''}<div style="font-weight:400;font-size:9px;color:#889">${cotNum(it.quantidade)} ${esc(it.unidade||'')}</div></th>`; });
    h+=`<th>Total</th>${verba>0?'<th>vs verba</th>':''}</tr></thead><tbody>`;
    ranked.forEach((p,idx)=>{ const dq=cotDesq(p), win=!dq&&p.total!=null&&p.total===cheapest;
      h+=`<tr style="${win?'background:#eafaf0':(dq?'background:#fdf6f6;color:#8a9299':'')}"><td style="font-weight:700;text-align:center">${win?'🏆':(dq?'—':(idx+1))}</td><td style="text-align:left;font-weight:${win?'800':'600'}"><span style="${dq?'text-decoration:line-through':''}">${esc(p.fornecedor_nome)}</span>${upOpc(p)}${upDesq(p)}${p.prazo?`<div style="font-weight:400;font-size:9px;color:#889">${esc(p.prazo)}</div>`:''}${upObsG(p)}</td>`;
      itens.forEach(it=>{ const pi=(p.itens||{})[it.id], bb=best[it.id], isBI=!dq&&bb&&bb.proposta_id===p.id;
        h+=`<td style="${isBI?'background:#d9f2e3;font-weight:700':(dq?'text-decoration:line-through':'')};vertical-align:top">${pi&&pi.preco_unit!=null?`${BRLp(pi.preco_unit)}${pi.observacao?`<div style="margin-top:3px;background:#eef1f3;border:1px solid #dde2e6;border-radius:5px;padding:4px 6px;font-size:8.5px;font-weight:400;color:#4a5560;text-align:left;line-height:1.35;white-space:normal">${esc(pi.observacao)}</div>`:''}`:'<span style="color:#bbb">—</span>'}</td>`; });
      const vv=(verba>0&&p.total!=null)?verba-p.total:null;
      h+=`<td style="font-weight:800">${p.total!=null?BRL(p.total):'—'}</td>${verba>0?`<td style="font-size:10.5px;color:${vv==null?'#889':(vv>=0?'var(--ok)':'var(--pend)')}">${vv==null?'—':(vv>=0?'+':'')+BRL(vv)}</td>`:''}</tr>`; });
    if(itens.length>1){ h+=`<tr style="background:#f4f7f5;font-weight:800"><td style="text-align:center">★</td><td style="text-align:left">Melhor por item</td>`;
      itens.forEach(it=>{ const bb=best[it.id]; h+=`<td style="color:var(--verde-d)">${bb?BRLp(bb.preco_unit):'—'}</td>`; });
      h+=`<td style="color:var(--verde-d)">${melhor?BRL(melhor):'—'}</td>${verba>0?'<td></td>':''}</tr>`; }
  } else {
    // ---- ITENS nas linhas (poucos fornecedores) ----
    h+=`<thead><tr><th style="text-align:left;min-width:150px;max-width:260px">Item</th><th style="width:40px">Qtd</th><th style="width:34px">Un</th>`;
    props.forEach(p=>{ const dq=cotDesq(p); h+=`<th style="min-width:92px;${dq?'color:#8a9299':''}"><span style="${dq?'text-decoration:line-through':''}">${esc(p.fornecedor_nome)}</span>${upOpc(p)}${upDesq(p)}${p.prazo?`<div style="font-weight:400;font-size:9px;color:#889">${esc(p.prazo)}</div>`:''}${upObsG(p)}</th>`; });
    h+=`<th style="background:#eafaf0;color:var(--verde-d)">Melhor preço</th></tr></thead><tbody>`;
    itens.forEach(it=>{ const b=best[it.id];
      h+=`<tr><td style="text-align:left">${esc(it.descricao)}</td><td>${cotNum(it.quantidade)}</td><td>${esc(it.unidade||'')}</td>`;
      props.forEach(p=>{ const pi=(p.itens||{})[it.id], dq=cotDesq(p), isB=!dq&&b&&b.proposta_id===p.id;
        h+=`<td style="${isB?'background:#d9f2e3;font-weight:700':(dq?'color:#8a9299;text-decoration:line-through':'')};vertical-align:top">${pi&&pi.preco_total!=null?`${BRLp(pi.preco_unit)}${isB?' 🏆':''}<div style="font-size:9.5px;color:#889;font-weight:400">${BRL(pi.preco_total)}</div>${pi.observacao?`<div style="margin-top:3px;background:#eef1f3;border:1px solid #dde2e6;border-radius:5px;padding:4px 6px;font-size:8.5px;font-weight:400;color:#4a5560;text-align:left;line-height:1.35;white-space:normal">${esc(pi.observacao)}</div>`:''}`:'<span style="color:#bbb">—</span>'}</td>`; });
      h+=`<td style="background:#eafaf0">${b?`<b>${BRLp(b.preco_unit)}</b><div style="font-size:9.5px;color:#889">${BRL(b.preco_total)} · ${esc(b.fornecedor)}</div>`:'—'}</td></tr>`; });
    h+=`<tr style="background:#f4f7f5;font-weight:800"><td style="text-align:left">TOTAL GERAL</td><td></td><td></td>`;
    props.forEach(p=>{ const dq=cotDesq(p), isBS=!dq&&m.fornecedor_destaque===p.fornecedor_nome; h+=`<td style="${isBS?'color:var(--verde-d)':(dq?'color:#8a9299;text-decoration:line-through':'')}">${p.total!=null?BRL(p.total):'—'}</td>`; });
    h+=`<td style="background:#eafaf0;color:var(--verde-d)">${melhor?BRL(melhor):'—'}</td></tr>`;
  }
  h+=`</tbody></table></div>`;
  // no papel, a legenda é o que evita a leitura errada de "escolheram o mais caro"
  if(props.some(cotDesq)) h+=`<div style="font-size:9px;color:#8a5b57;margin-top:5px">Proposta riscada = <b>desqualificada</b>: registrada no mapa, fora do julgamento (não disputa o melhor preço). O motivo está sob o nome do fornecedor.</div>`;
  return h;
}
// Equalização adaptativa: pontos nas linhas (padrão) OU, se há mais fornecedores que pontos, fornecedores nas linhas.
function upEqualiza(props,pontos){
  let h=`<div style="font-weight:800;font-size:14px;margin:16px 0 8px;color:var(--verde-d)">Comparativo de Equalização</div><div style="overflow-x:auto"><table class="up-tbl">`;
  if(props.length>pontos.length && props.length>=5){
    h+=`<thead><tr><th style="text-align:left;min-width:150px">Fornecedor</th>`;
    pontos.forEach(pt=>{ h+=`<th style="min-width:90px" title="${esc(pt)}">${esc(pt.slice(0,34))}${pt.length>34?'…':''}</th>`; });
    h+=`</tr></thead><tbody>`;
    props.forEach(p=>{ h+=`<tr><td style="text-align:left;font-weight:600">${cotPropNome(p)}</td>`;
      pontos.forEach(pt=>{ const v=((p.equaliza||{})[pt])||''; h+=`<td style="text-align:left">${v?esc(v):'<span style="color:#bbb">—</span>'}</td>`; });
      h+='</tr>'; });
  } else {
    h+=`<thead><tr><th style="text-align:left;min-width:180px">Ponto a conferir</th>`;
    props.forEach(p=>{ h+=`<th style="min-width:92px">${cotPropNome(p)}</th>`; });
    h+=`</tr></thead><tbody>`;
    pontos.forEach(pt=>{ h+=`<tr><td style="text-align:left;font-weight:600">${esc(pt)}</td>`;
      props.forEach(p=>{ const v=((p.equaliza||{})[pt])||''; h+=`<td style="text-align:left">${v?esc(v):'<span style="color:#bbb">—</span>'}</td>`; });
      h+='</tr>'; });
  }
  return h+`</tbody></table></div>`;
}
function cotUmaPagina(){
  const d=COT.cur,c=d.cotacao,itens=d.itens||[],props=d.propostas||[],m=d.mapa||{},best=m.melhor_por_item||{},w=document.getElementById('cotwrap');
  const pontos=cotEqPontos(c);
  const dataC=c.created_at?D(String(c.created_at).slice(0,10)):'—';
  const verba=Number(c.verba)||0, melhor=Number(m.melhor_total)||0;
  const economia=(verba>0&&melhor>0)?verba-melhor:null, ecoPct=(economia!=null&&verba>0)?Math.round(economia/verba*100):null;
  const vOrig={curada:'curada ✓',auto:'auto 🤖',definida:'definida',manual:'manual'}[c.verba_origem]||c.verba_origem||'';
  let h=`<div class="up-noprint" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;margin-bottom:12px">
    <button class="btn-ghost" onclick="cotRenderDetalhe()"><span class="material-icons" style="font-size:16px;vertical-align:-3px">arrow_back</span> Voltar ao mapa</button>
    <button class="btn-prim" style="padding:6px 14px" onclick="window.print()"><span class="material-icons" style="font-size:15px;vertical-align:-3px">print</span> Imprimir / PDF</button>
    <span class="muted" style="font-size:11.5px">Resumo de uma página (paisagem) — pronto pra imprimir ou salvar em PDF.</span></div>`;
  h+=`<div id="cotUmaPagina" style="background:#fff;color:#1e2b24;padding:6px 2px">`;
  h+=`<div style="border:1px solid var(--line);border-radius:10px;padding:14px 16px;margin-bottom:12px;background:#f7faf8">
    <div style="font-size:19px;font-weight:800;color:var(--verde-d)"><span class="material-icons" style="font-size:20px;vertical-align:-3px;color:var(--dourado)">request_quote</span> Mapa de Cotações — ${esc(c.titulo||'')}</div>
    <div style="font-size:12px;margin-top:5px;color:#667"><b>Obra:</b> ${esc(c.obra_nome||'—')} &nbsp;·&nbsp; <b>Data:</b> ${dataC}${c.criado_nome?` &nbsp;·&nbsp; <b>Criado por:</b> ${esc(c.criado_nome)}`:''}${c.categoria?` &nbsp;·&nbsp; <b>Categoria:</b> ${esc(c.categoria)}`:''}${props.length?` &nbsp;·&nbsp; <b>${props.length}</b> proposta(s)`:''}${c.num_solicitacao?` &nbsp;·&nbsp; <b>SC:</b> ${esc(c.num_solicitacao)}`:''}${c.num_pedido?` &nbsp;·&nbsp; <b>Pedido:</b> ${esc(c.num_pedido)}`:''}</div>
    ${c.descricao?`<div style="font-size:12.5px;margin-top:8px;line-height:1.5;color:#334">${esc(c.descricao)}</div>`:''}</div>`;
  h+=`<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:10px;margin-bottom:4px">
    ${upCard('Verba prevista', verba?BRL(verba):'—', vOrig, 'var(--muted)')}
    ${upCard('Melhor compra', melhor?BRL(melhor):'—', 'menor preço por item', 'var(--ok)')}
    ${upCard('Melhor fornecedor único', m.melhor_oferta?BRL(m.melhor_oferta):'—', m.fornecedor_destaque||'', 'var(--dourado)')}
    ${economia!=null?upCard('Economia vs verba', BRL(economia), (ecoPct!=null?ecoPct+'% da verba':''), economia>=0?'var(--ok)':'var(--pend)'):''}</div>`;
  h+= props.length ? upPrecos(itens,props,m,best,verba) : `<div class="dmini" style="margin-top:12px">Sem propostas ainda — cadastre propostas para montar o comparativo.</div>`;
  if(pontos.length&&props.length) h+=upEqualiza(props,pontos);
  h+=`<div style="font-size:10px;margin-top:14px;text-align:right;color:#99a">Cockpit de Suprimentos · Caprem · gerado em ${D(new Date().toISOString().slice(0,10))}</div></div>`;
  w.innerHTML=h; window.scrollTo(0,0);
}
// EQUALIZAÇÃO — pontos a conferir por proposta (diesel? faturamento mín., mobilização, retenção, ISS, ART…)
const DEFAULT_EQ=['Frete','Condição de pagamento','Descarregamento'];   // pontos padrão em TODA cotação (em branco até preencher)
function cotEqPontos(c){ const p=((c&&c.equalizacao)||'').split(/\r?\n|\|/).map(s=>s.trim()).filter(Boolean); return p.length?p:DEFAULT_EQ.slice(); }
/* CONFERÊNCIA DE CONTATOS do convidado (e-mail/telefone/WhatsApp) — indicador de preenchido + última atualização + edição inline (base p/ o disparo) */
function cotConvContatos(cf){ const CAN_EDIT=cotEditavel();
  const at=cf.contatos_at||{}, fid=cf.fornecedor_id, editable=fid&&CAN_EDIT;
  const fld=(icon,key,val,ph,w)=>{ const v=(val||''), filled=!!String(v).trim(), when=at[key]?` <span class="muted" style="font-size:9.5px" title="última atualização">${D(String(at[key]).slice(0,10))}</span>`:'';
    return `<div style="display:flex;align-items:center;gap:3px"><span class="material-icons" style="font-size:13px;color:${filled?'var(--ok)':'var(--pend)'}" title="${filled?'preenchido':'faltando'}">${icon}</span>${editable?`<input data-ct="${key}" value="${esc(v)}" placeholder="${ph}" style="font-size:11px;padding:2px 5px;width:${w}px;border:1px solid ${filled?'var(--line)':'var(--pend)'};border-radius:5px">`:`<span style="font-size:11px;${filled?'':'color:var(--pend)'}">${filled?esc(v):'faltando'}</span>`}${when}</div>`; };
  return `<div class="cotconv-ct" style="display:flex;flex-wrap:wrap;gap:10px;align-items:center;margin-top:7px;padding-left:16px">
    ${fld('mail','email',cf.email,'email@fornecedor',175)}
    ${fld('call','telefone',cf.telefone,'(19) 0000-0000',115)}
    ${fld('chat','whatsapp',cf.whatsapp,'WhatsApp',115)}
    ${editable?`<button class="btn-ghost" style="padding:2px 8px;font-size:11px" onclick="cotContatoSalvar(${fid},this)"><span class="material-icons" style="font-size:12px;vertical-align:-2px">save</span> salvar contatos</button>`:(!fid?'<span class="dmini" style="font-size:10px">fornecedor manual — sem cadastro p/ editar</span>':'')}</div>`;
}
async function cotContatoSalvar(fid,btn){
  const row=btn.closest('.cotconv-ct'); if(!row)return; const body={acao:'contato_salvar',me:EU&&EU.bitrix_id,id:fid};
  row.querySelectorAll('input[data-ct]').forEach(inp=>{ body[inp.getAttribute('data-ct')]=inp.value; });
  try{ const r=await (await fetch('actions/fornecedores.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(body)})).json();
    if(r.error){toast(r.error);return;} toast('Contatos salvos'); cotOpen(COT.cur.cotacao.id);
  }catch(e){toast('Falha: '+e.message);}
}
function cotEqualizaPanel(d){ const CAN_EDIT=cotEditavel();
  const c=d.cotacao||{}, props=d.propostas||[], pontos=cotEqPontos(c), editV=!!COT.eqEdit;
  const eqActions=`${CAN_EDIT?`<button class="btn-ghost" style="padding:3px 9px" onclick="cotEqualizaEdit()"><span class="material-icons" style="font-size:14px;vertical-align:-3px">edit_note</span> Editar pontos</button>`:''}${(CAN_EDIT&&props.length&&pontos.length)?(editV?`<button class="btn-prim" style="padding:3px 10px" onclick="cotEqValoresSave()"><span class="material-icons" style="font-size:14px;vertical-align:-3px">check</span> Salvar valores</button><button class="btn-ghost" style="padding:3px 9px" onclick="cotEqValoresCancel()">Cancelar</button>`:`<button class="btn-ghost" style="padding:3px 9px" onclick="cotEqValoresEdit()"><span class="material-icons" style="font-size:14px;vertical-align:-3px">edit</span> Editar valores</button>`):''}`;
  let h=`<div class="panel" style="margin-bottom:12px;padding:15px 18px">${cotSecHead('rule','Equalização','pontos a conferir por proposta',eqActions)}
    <div id="cotEqEdit" style="display:none;margin-top:8px"><textarea id="cotEqPontos" rows="6" style="width:100%;font-size:12.5px" placeholder="Um ponto por linha…">${esc(pontos.join('\n'))}</textarea>
      <div style="margin-top:6px"><button class="btn-prim" style="padding:5px 12px" onclick="cotEqualizaPontosSave()">Salvar pontos</button> <button class="btn-ghost" style="padding:5px 12px" onclick="document.getElementById('cotEqEdit').style.display='none'">Cancelar</button></div></div>`;
  if(!props.length){ h+='<ul style="margin:8px 0 0 18px;padding:0">'+pontos.map(p=>`<li style="font-size:12.5px;margin-bottom:3px">${esc(p)}</li>`).join('')+'</ul><div class="dmini" style="margin-top:6px">Cadastre propostas para preencher cada ponto por fornecedor.</div></div>'; return h; }
  h+=(editV?'<div class="dmini" style="margin-top:8px;color:var(--verde-d)">Modo edição — ajuste os valores e clique <b>Salvar valores</b>.</div>':'')+'<div style="overflow-x:auto;margin-top:8px"><table class="mtable" style="border:none"><thead><tr><th class="svc-h" style="text-align:left;min-width:200px">Ponto a conferir</th>';
  props.forEach(p=>{ h+=`<th style="min-width:140px">${cotPropNome(p)}</th>`; });
  h+='</tr></thead><tbody>';
  pontos.forEach(pt=>{ h+=`<tr><td class="svc-c" style="text-align:left;font-size:12px">${esc(pt)}</td>`;
    props.forEach(p=>{ const v=((p.equaliza||{})[pt])||''; h+=`<td style="padding:4px 6px">${editV?`<input data-eqpid="${p.id}" data-eqpt="${esc(pt)}" value="${esc(v)}" style="width:100%;font-size:11.5px;padding:3px 5px;border:1px solid var(--line);border-radius:5px" placeholder="—">`:`<span style="font-size:11.5px">${esc(v||'—')}</span>`}</td>`; });
    h+='</tr>'; });
  h+='</tbody></table></div></div>';
  return h;
}
function cotEqValoresEdit(){ COT.eqEdit=true; cotRenderDetalhe(); }
function cotEqValoresCancel(){ COT.eqEdit=false; cotRenderDetalhe(); }
async function cotEqValoresSave(){
  const props=(COT.cur&&COT.cur.propostas)||[]; let n=0;
  for(const p of props){ const map={}; document.querySelectorAll('input[data-eqpid="'+p.id+'"]').forEach(inp=>{ const val=inp.value.trim(); if(val) map[inp.getAttribute('data-eqpt')]=val; });
    p.equaliza=map;
    try{ await fetch('actions/cotacoes.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({acao:'equaliza_salvar',me:EU&&EU.bitrix_id,cotacao_id:COT.cur.cotacao.id,proposta_id:p.id,equaliza:map})}); n++; }catch(e){}
  }
  COT.eqEdit=false; cotRenderDetalhe(); toast('Equalização salva ('+n+' proposta(s))');
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
function cotProposta(pid){
  const d=COT.cur; let ex=null; if(pid) ex=(d.propostas||[]).find(p=>String(p.id)===String(pid));   // id vem STRING do PDO MySQL
  COT.prop={id:pid||0, precos:{}, opcao:ex?(ex.opcao||1):1, opcao_rotulo:ex?(ex.opcao_rotulo||''):''};
  (d.itens||[]).forEach(it=>{ const pi=ex?(ex.itens||{})[it.id]:null; COT.prop.precos[it.id]={preco_unit:pi&&pi.preco_unit!=null?pi.preco_unit:'',preco_total:pi&&pi.preco_total!=null?pi.preco_total:'',observacao:(pi&&pi.observacao)||''}; });
  COT.prop.fornecedor_nome=ex?ex.fornecedor_nome:''; COT.prop.prazo=ex?ex.prazo:''; COT.prop.observacoes=ex?ex.observacoes:'';
  /* EDITANDO: restaura o vínculo com o cadastro. Sem isto, abrir e salvar uma proposta já
     vinculada a transformava em "manual" — o UPDATE grava fornecedor_id=NULL quando não vem nada.
     Guarda também o _fornPick para a regra de "trocou o texto, perdeu o vínculo" valer aqui. */
  COT.prop.fornecedor_id = ex && ex.fornecedor_id ? ex.fornecedor_id : null;
  COT.prop._fornPick = COT.prop.fornecedor_id ? {id:COT.prop.fornecedor_id, nome:ex.fornecedor_nome} : null;
  COT.mode='proposta'; cotRenderProposta();
}
// Autocomplete próprio do fornecedor (substitui o <datalist> nativo, que renderizava preto e filtrava mal).
// Busca no SERVIDOR (?q= casa nome/itens/categoria/cidade) — por isso "life" acha "LIFE CONSTRUCOES".
let _prFT=null;
function cotFornSearch(el){
  COT.prop.fornecedor_nome=el.value;                 // mantém o texto livre p/ salvar
  /* Se o texto deixou de ser exatamente o do fornecedor escolhido, o vínculo morre. Sem isto,
     escolher "Mauro Terraplenagem" e depois editar o nome salvaria a proposta com o ID do Mauro. */
  const esc0=COT.prop._fornPick;
  if(esc0 && (el.value||'').trim()!==esc0.nome){ COT.prop.fornecedor_id=null; COT.prop._fornPick=null; }
  const q=(el.value||'').trim();
  clearTimeout(_prFT);
  _prFT=setTimeout(async()=>{
    const drop=document.getElementById('prFDrop'); if(!drop)return;
    if(q.length<2){ drop.style.display='none'; drop.innerHTML=''; return; }
    try{
      const d=await (await fetch('actions/fornecedores.php?limit=40&q='+encodeURIComponent(q))).json();
      const L=d.fornecedores||[]; COT.prop._fornList=L;
      if(!L.length){ drop.innerHTML='<div class="muted" style="padding:10px 12px;font-size:12px">Nenhum fornecedor cadastrado com esse termo. Digite o nome livre e salve — ele entra na Concorrência.</div>'; drop.style.display='block'; return; }
      drop.innerHTML=L.map((f,i)=>`<div onmousedown="cotFornPick(${i})" style="padding:8px 12px;cursor:pointer;border-bottom:1px solid #f2f4f3" onmouseover="this.style.background='#f3f7f5'" onmouseout="this.style.background='#fff'">
        <b style="font-size:12.5px">${esc(f.nome)}</b>${fornSeloMini(f)}
        <div class="muted" style="font-size:10.5px">${esc(f.categoria||'sem categoria')}${f.cidade?' · '+esc(f.cidade):''}${f.tipo?' · '+esc(f.tipo):''}${f.itens?' · '+esc((''+f.itens).slice(0,60)):''}</div></div>`).join('');
      drop.style.display='block';
    }catch(e){ drop.style.display='none'; }
  },220);
}
function cotFornPick(i){
  const f=(COT.prop._fornList||[])[i]; if(!f)return;
  const el=document.getElementById('prF'); if(el)el.value=f.nome;
  COT.prop.fornecedor_nome=f.nome;
  /* GUARDA O VÍNCULO. Antes daqui só o NOME era levado: a proposta nascia sem fornecedor_id e o
     card da Concorrência virava "fornecedor manual — sem cadastro p/ editar", com e-mail, telefone
     e CNPJ em "faltando", mesmo o fornecedor tendo cadastro completo. Guardamos o objeto inteiro
     porque o convite (cot_insert_convidados) também aceita categoria/contato/e-mail/telefone. */
  COT.prop.fornecedor_id=f.id||null;
  COT.prop._fornPick=f;
  const drop=document.getElementById('prFDrop'); if(drop){ drop.style.display='none'; drop.innerHTML=''; }
}
function cotFornBlur(){ setTimeout(()=>{ const drop=document.getElementById('prFDrop'); if(drop) drop.style.display='none'; },160); }
function cotRenderProposta(){
  const d=COT.cur,c=d.cotacao,itens=d.itens||[],pr=COT.prop;
  const ehRev=!!pr.revisarDe, ehOpc=!!pr.novaOpcao;
  const opcTag=(pr.opcao||1)>1?(' · opção '+pr.opcao):'';
  const titulo=ehOpc?('Nova opção (opção '+(pr.opcao||2)+')')
    :(ehRev?('Nova revisão (rev '+((pr.revisaoBase||0)+1)+')'+opcTag):((pr.id?'Editar':'Cadastrar')+' proposta'+opcTag));
  const banner=ehOpc
    ? `<div class="dmini" style="margin-bottom:10px;background:#eef4fb;border:1px solid #cfe0f2;padding:8px 12px;border-radius:9px">🗂️ Você está cadastrando a <b>opção ${pr.opcao||2}</b> de <b>${esc(pr.fornecedor_nome||'')}</b> — outra forma que ele apresentou a mesma proposta. Ela <b>não substitui</b> a opção anterior: as duas ficam vigentes e concorrem lado a lado no mapa. Dê um nome curto à opção para diferenciar (ex.: “com bomba inclusa”, “global”).</div>`
    : (ehRev?`<div class="dmini" style="margin-bottom:10px;background:#fff8ec;border:1px solid #f0e2c2;padding:8px 12px;border-radius:9px">📝 Você está registrando a <b>revisão ${(pr.revisaoBase||0)+1}</b>${(pr.opcao||1)>1?` da <b>opção ${pr.opcao}</b>${pr.opcao_rotulo?' ('+esc(pr.opcao_rotulo)+')':''}`:''} de <b>${esc(pr.fornecedor_nome||'')}</b>. Os preços vieram da revisão anterior — ajuste o que o fornecedor mudou. A anterior fica guardada no histórico (não se perde).</div>`:'');
  document.getElementById('cotwrap').innerHTML=`<div class="panel">
    <div style="display:flex;align-items:center;gap:8px;margin-bottom:12px"><button class="btn-ghost" onclick="cotRenderDetalhe()"><span class="material-icons" style="font-size:16px;vertical-align:-3px">arrow_back</span> Voltar ao mapa</button><b style="font-size:15px">${titulo} · ${esc(c.titulo)}</b></div>
    ${banner}
    <div style="max-width:860px">
    <div style="display:grid;grid-template-columns:1fr 220px;gap:10px">
      ${cotFld('Fornecedor *','<div style="position:relative"><input id="prF" autocomplete="off" oninput="cotFornSearch(this)" onfocus="cotFornSearch(this)" onblur="cotFornBlur()" style="width:100%" value="'+esc(pr.fornecedor_nome||'')+'" placeholder="Digite o nome do fornecedor…"><div id="prFDrop" style="display:none;position:absolute;left:0;right:0;top:calc(100% + 3px);z-index:60;background:#fff;border:1px solid var(--line);border-radius:10px;box-shadow:0 12px 34px rgba(0,0,0,.16);max-height:290px;overflow:auto"></div></div>')}
      ${cotFld('Prazo de entrega','<input id="prP" style="width:100%" value="'+esc(pr.prazo||'')+'" placeholder="Ex.: 15 dias">')}
    </div>
    ${((pr.opcao||1)>1||ehOpc)?cotFld('Nome desta opção'+(ehOpc?' *':''),'<input id="prOpR" style="width:100%" value="'+esc(pr.opcao_rotulo||'')+'" placeholder="Ex.: com bomba inclusa · preço global · sem mobilização">','margin-top:8px'):''}
    ${cotFld('Observação geral do fornecedor','<textarea id="prO" rows="2" style="width:100%" placeholder="Vale para a proposta inteira — aparece no cabeçalho da coluna dele no mapa. Ex.: só consegue iniciar em outubro/2026.">'+esc(pr.observacoes||'')+'</textarea>','margin-top:8px')}
    <div style="margin-top:14px"><b style="font-size:13px">Preços por item</b> <span class="muted" style="font-size:11px">(preencha o unitário — o total calcula pela quantidade; use <span class="material-icons" style="font-size:12px;vertical-align:-2px">sticky_note_2</span> para anotar um detalhe do item)</span></div>
    <div style="margin-top:8px;border:1px solid var(--line);border-radius:10px;overflow:hidden">
      <div style="display:grid;grid-template-columns:minmax(0,1fr) 130px 150px;gap:10px;padding:7px 12px;background:#fafbfb;border-bottom:1px solid var(--line);font-size:10.5px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.3px"><span>Item</span><span style="text-align:right">Preço unit.</span><span style="text-align:right">Preço total</span></div>
      ${itens.map((it,ix)=>{ const ob=pr.precos[it.id].observacao||'';
      return `<div style="padding:9px 12px;${ix<itens.length-1?'border-bottom:1px solid #f1f3f2':''}">
      <div style="display:grid;grid-template-columns:minmax(0,1fr) 130px 150px;gap:10px;align-items:center">
      <div><b style="font-size:12.5px">${esc(it.descricao)}</b> <span class="muted" style="font-size:11px">· ${cotNum(it.quantidade)} ${esc(it.unidade||'')}</span>
        <span class="material-icons" id="prObsB${it.id}" onclick="cotObsItemToggle(${it.id})" title="observação DESTE item na proposta deste fornecedor (ex.: até 40 m³ por diária) — aparece no mapa" style="font-size:14px;cursor:pointer;vertical-align:-3px;color:${ob?'var(--verde-d)':'#c3cbd1'}">sticky_note_2</span>
        ${it.observacao?`<div class="muted" style="font-size:10.5px;margin-top:1px">${esc(it.observacao)}</div>`:''}</div>
      <input type="text" inputmode="decimal" id="prU${it.id}" value="${pr.precos[it.id].preco_unit!==''?fmtMoneyN(pr.precos[it.id].preco_unit,4):''}" oninput="cotPrecoIn(${it.id},'u',this)" onblur="moneyBlurN(this,4)" placeholder="0,00" title="aceita até 4 casas decimais" style="width:100%;text-align:right">
      <input type="text" inputmode="decimal" id="prT${it.id}" value="${pr.precos[it.id].preco_total!==''?fmtMoneyN(pr.precos[it.id].preco_total,4):''}" oninput="cotPrecoIn(${it.id},'t',this)" onblur="moneyBlurN(this,4)" placeholder="0,00" style="width:100%;text-align:right"></div>
      <div id="prObsW${it.id}" style="display:${ob?'block':'none'};margin-top:6px">
        <textarea id="prObs${it.id}" rows="2" oninput="cotObsItemIn(${it.id},this)" placeholder="Detalhe deste item nesta proposta — ex.: bombeamento por diária de R$ 2.000,00 até 40 m³" style="width:100%;font-size:11.5px">${esc(ob)}</textarea>
      </div></div>`; }).join('')}</div>
    <div style="margin-top:14px"><button id="prSalvarBtn" class="btn-prim" onclick="cotSalvarProposta()"><span class="material-icons" style="font-size:16px;vertical-align:-3px">check</span> Salvar proposta</button></div>
    </div></div>`;
}
/* OBSERVAÇÃO POR ITEM (item × fornecedor) — o "quadro cinza" do mapa antigo. Fica escondida até
   clicar no bloquinho: a lista de preços continua limpa para quem só vai digitar valor. */
function cotObsItemToggle(iid){
  const w=document.getElementById('prObsW'+iid); if(!w)return;
  const abrir=w.style.display==='none';
  w.style.display=abrir?'block':'none';
  if(abrir){ const t=document.getElementById('prObs'+iid); if(t)t.focus(); }
  else { const t=document.getElementById('prObs'+iid); if(t&&!t.value.trim()) cotObsItemIn(iid,t); }   // fechou vazia = sem observação
}
function cotObsItemIn(iid,el){
  const p=COT.prop.precos[iid]; if(!p)return;
  p.observacao=el.value;
  const b=document.getElementById('prObsB'+iid); if(b) b.style.color=el.value.trim()?'var(--verde-d)':'#c3cbd1';
}
function cotPrecoIn(iid,which,el){
  maskMoneyInputN(el,4);                            // reformata ao vivo, aceitando até 4 casas decimais
  const n=parseBRLInput(el.value);                 // número (ou null)
  const p=COT.prop.precos[iid], it=(COT.cur.itens||[]).find(x=>x.id===iid), q=it&&it.quantidade?Number(it.quantidade):null;
  if(which==='u'){ p.preco_unit=(n==null?'':n); if(q&&n!=null){ p.preco_total=+(n*q).toFixed(4); const el2=document.getElementById('prT'+iid); if(el2)el2.value=fmtMoneyN(p.preco_total,4); } }
  else { p.preco_total=(n==null?'':n); if(q&&n!=null){ p.preco_unit=+(n/q).toFixed(4); const el2=document.getElementById('prU'+iid); if(el2)el2.value=fmtMoneyN(p.preco_unit,4); } }   // total digitado → deriva o unitário (÷ qtde), simétrico ao unitário→total
}
async function cotSalvarProposta(){
  if(COT._savingProp) return;   // trava DUPLO-SUBMIT (duplo-clique criava 2 propostas iguais 1s de diferença)
  const forn=val('prF').trim(); if(!forn){toast('Informe o fornecedor');return;}
  const rotulo=(document.getElementById('prOpR')?val('prOpR').trim():(COT.prop.opcao_rotulo||''));
  if(COT.prop.novaOpcao && !rotulo){ toast('Dê um nome à opção (ex.: "com bomba inclusa") para diferenciar da anterior'); const e=document.getElementById('prOpR'); if(e)e.focus(); return; }
  const itens=Object.entries(COT.prop.precos).map(([iid,p])=>({cotacao_item_id:Number(iid),preco_unit:p.preco_unit!==''?Number(p.preco_unit):'',preco_total:p.preco_total!==''?Number(p.preco_total):'',observacao:p.observacao||''}));
  const body=COT.prop.revisarDe
    ? {acao:'proposta_revisar',me:EU&&EU.bitrix_id,cotacao_id:COT.cur.cotacao.id,proposta_id:COT.prop.revisarDe,fornecedor_nome:forn,fornecedor_id:COT.prop.fornecedor_id||undefined,prazo:val('prP'),observacoes:val('prO'),opcao_rotulo:rotulo,itens}
    // fornecedor_id ia SÓ no ramo de revisão; sem ele aqui, toda proposta nova saía "manual"
    : {acao:'proposta',me:EU&&EU.bitrix_id,cotacao_id:COT.cur.cotacao.id,proposta_id:COT.prop.id||undefined,fornecedor_nome:forn,fornecedor_id:COT.prop.fornecedor_id||undefined,prazo:val('prP'),observacoes:val('prO'),nova_opcao:COT.prop.novaOpcao?1:undefined,opcao_rotulo:rotulo,itens};
  COT._savingProp=true; const _sb=document.getElementById('prSalvarBtn'); if(_sb){_sb.disabled=true;_sb.style.opacity='.6';}
  try{ const r=await (await fetch('actions/cotacoes.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(body)})).json();
    if(r.error){toast(r.error); COT._savingProp=false; if(_sb){_sb.disabled=false;_sb.style.opacity='';} return;}
    // garante que o fornecedor da proposta esteja na Concorrência (p/ editar/excluir a proposta ali)
    /* leva o CADASTRO junto (id/categoria/contato/e-mail/telefone) quando veio da busca — era só
       {nome} e por isso o card nascia sem dado nenhum, com os três "faltando" em vermelho */
    try{ const nz=s=>String(s||'').trim().toLowerCase();
      if(!((COT.cur&&COT.cur.convidados)||[]).some(cv=>nz(cv.fornecedor_nome)===nz(forn))){
        const fp=COT.prop._fornPick;
        const conv = (fp && nz(fp.nome)===nz(forn))
          ? {nome:fp.nome, id:fp.id, categoria:fp.categoria||'', contato:fp.contato||'', email:fp.email||'', telefone:fp.telefone||''}
          : {nome:forn};
        await fetch('actions/cotacoes.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({acao:'convidar',me:EU&&EU.bitrix_id,cotacao_id:COT.cur.cotacao.id,convidados:[conv]})});
      } }catch(e){}
    // aplica a equalização pré-preenchida pela IA nesta proposta (mescla com o que já houver, sem apagar valores manuais)
    if(COT.prop.eqIA && Object.keys(COT.prop.eqIA).length && r.proposta_id){
      const src=(COT.cur.propostas||[]).find(p=>p.id===r.proposta_id), base=(src&&src.equaliza)?Object.assign({},src.equaliza):{};
      const merged=Object.assign(base,COT.prop.eqIA);
      try{ await fetch('actions/cotacoes.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({acao:'equaliza_salvar',me:EU&&EU.bitrix_id,cotacao_id:COT.cur.cotacao.id,proposta_id:r.proposta_id,equaliza:merged})}); }catch(e){}
    }
    COT._savingProp=false;
    toast(COT.prop.revisarDe?('Revisão '+(r.revisao||'')+' registrada'):(COT.prop.novaOpcao?('Opção '+(r.opcao||'')+' cadastrada'):'Proposta salva')); cotOpen(COT.cur.cotacao.id);
  }catch(e){toast('Falha: '+e.message); COT._savingProp=false; if(_sb){_sb.disabled=false;_sb.style.opacity='';}}
}
// abre o form pré-preenchido com a proposta VIGENTE p/ registrar a próxima revisão (a anterior fica no histórico)
function cotPropostaRevisar(pid){
  const d=COT.cur, ex=(d.propostas||[]).find(p=>String(p.id)===String(pid)); if(!ex){toast('Proposta não encontrada');return;}   // id STRING no MySQL
  // _fornPick p/ a regra "trocou o texto, perdeu o vínculo" valer também na revisão
  COT.prop={id:0, revisarDe:pid, revisaoBase:ex.revisao||0, fornecedor_id:ex.fornecedor_id||null, precos:{},
            opcao:ex.opcao||1, opcao_rotulo:ex.opcao_rotulo||'',
            _fornPick: ex.fornecedor_id?{id:ex.fornecedor_id, nome:ex.fornecedor_nome}:null};
  (d.itens||[]).forEach(it=>{ const pi=(ex.itens||{})[it.id]; COT.prop.precos[it.id]={preco_unit:pi&&pi.preco_unit!=null?pi.preco_unit:'',preco_total:pi&&pi.preco_total!=null?pi.preco_total:'',observacao:(pi&&pi.observacao)||''}; });
  COT.prop.fornecedor_nome=ex.fornecedor_nome; COT.prop.prazo=ex.prazo||''; COT.prop.observacoes=ex.observacoes||'';
  COT.mode='proposta'; cotRenderProposta();
}
/* NOVA OPÇÃO — o mesmo fornecedor apresentou a proposta de outra forma (com/sem bomba, global x diária).
   Diferente da REVISÃO: nada é arquivado. A opção nasce como uma proposta vigente própria (opção 2, 3…),
   com sua própria cadeia de revisões, e entra no mapa como mais uma coluna concorrendo com a opção 1.
   Os preços vêm da opção de origem só como ponto de partida — o rótulo é obrigatório p/ diferenciar. */
function cotPropostaNovaOpcao(pid){
  const d=COT.cur, ex=(d.propostas||[]).find(p=>String(p.id)===String(pid)); if(!ex){toast('Proposta não encontrada');return;}
  const nz=s=>String(s||'').trim().toLowerCase();
  const irmas=(d.propostas||[]).filter(p=>(ex.fornecedor_id&&String(p.fornecedor_id)===String(ex.fornecedor_id))||nz(p.fornecedor_nome)===nz(ex.fornecedor_nome));
  const prox=Math.max(...irmas.map(p=>Number(p.opcao)||1),1)+1;
  COT.prop={id:0, novaOpcao:true, opcao:prox, opcao_rotulo:'', fornecedor_id:ex.fornecedor_id||null, precos:{},
            _fornPick: ex.fornecedor_id?{id:ex.fornecedor_id, nome:ex.fornecedor_nome}:null};
  (d.itens||[]).forEach(it=>{ const pi=(ex.itens||{})[it.id]; COT.prop.precos[it.id]={preco_unit:pi&&pi.preco_unit!=null?pi.preco_unit:'',preco_total:pi&&pi.preco_total!=null?pi.preco_total:'',observacao:(pi&&pi.observacao)||''}; });
  COT.prop.fornecedor_nome=ex.fornecedor_nome; COT.prop.prazo=ex.prazo||''; COT.prop.observacoes='';
  COT.mode='proposta'; cotRenderProposta();
}
// linha do tempo das revisões de um fornecedor, com % que subiu/desceu por item vs a revisão anterior
function cotHistorico(pid){
  const d=COT.cur, cur=(d.propostas||[]).find(p=>String(p.id)===String(pid)); if(!cur){toast('Proposta não encontrada');return;}   // id STRING no MySQL
  const itens=d.itens||[];
  const chain=[...(cur.historico||[]).map(h=>Object.assign({},h)), {id:cur.id,revisao:cur.revisao||0,total:cur.total,created_at:cur.created_at,itens:cur.itens||{},desq:cur.desq,desq_texto:cur.desq_texto,vigente:true}];
  chain.sort((a,b)=>(a.revisao||0)-(b.revisao||0));
  const pct=(nv,ov)=>{ if(ov==null||ov===0||nv==null) return null; return (nv-ov)/ov*100; };
  const delta=p=>{ if(p==null) return ''; const dn=p<-0.05, up=p>0.05; const col=dn?'#1a8a4a':(up?'#c0392b':'#8a9299'), ar=dn?'▼':(up?'▲':'='); return ` <span style="color:${col};font-size:9.5px">${ar}${Math.abs(p).toFixed(1)}%</span>`; };
  let h='<table class="mtable" style="border:none;width:100%"><thead><tr><th style="text-align:left">Item</th>'+chain.map(r=>`<th>rev ${r.revisao}${r.vigente?' <span style="color:var(--verde-d)">•vigente</span>':''}${cotDesq(r)?`<div title="${esc(r.desq_texto||'')}" style="font-size:8.5px;font-weight:800;color:#b3261e">DESQUALIFICADA</div>`:''}${r.created_at?`<div class="muted" style="font-size:9px;font-weight:400">${cotFmtDT(r.created_at)}</div>`:''}</th>`).join('')+'</tr></thead><tbody>';
  itens.forEach(it=>{ h+=`<tr><td style="text-align:left">${esc(it.descricao)}</td>`; let prev=null;
    chain.forEach(r=>{ const pi=(r.itens||{})[it.id]; const u=pi&&pi.preco_unit!=null?pi.preco_unit:null; const dl=prev!=null?delta(pct(u,prev)):''; h+=`<td style="text-align:center;white-space:nowrap">${u!=null?BRLp(u):'—'}${dl}</td>`; prev=u; });
    h+='</tr>'; });
  h+='<tr style="background:#f7faf8;font-weight:800"><td style="text-align:left">TOTAL</td>'; let pt=null;
  chain.forEach(r=>{ const t=r.total; const dl=pt!=null?delta(pct(t,pt)):''; h+=`<td style="text-align:center;white-space:nowrap">${t!=null?BRL(t):'—'}${dl}</td>`; pt=t; });
  h+='</tr></tbody></table>';
  let ov=document.getElementById('cotHistOv'); if(!ov){ov=document.createElement('div');ov.id='cotHistOv';ov.style.cssText='position:fixed;inset:0;background:rgba(15,25,20,.45);z-index:9999;display:flex;align-items:flex-start;justify-content:center;padding:24px;overflow:auto';document.body.appendChild(ov);} ov.onclick=e=>{if(e.target===ov)ov.remove();};
  ov.innerHTML=`<div style="background:#fff;border-radius:14px;padding:18px 20px;max-width:940px;width:100%;box-shadow:0 12px 44px rgba(0,0,0,.22)" onclick="event.stopPropagation()"><div style="display:flex;justify-content:space-between;align-items:center"><b style="font-size:16px">Histórico de revisões · ${esc(cur.fornecedor_nome)}${(cur.opcao||1)>1?` <span style="font-size:12px;color:var(--muted)">· opção ${cur.opcao}${cur.opcao_rotulo?' ('+esc(cur.opcao_rotulo)+')':''}</span>`:''}</b><span class="material-icons" style="cursor:pointer;color:var(--muted)" onclick="document.getElementById('cotHistOv').remove()">close</span></div><div class="muted" style="font-size:12px;margin:4px 0 10px">Cada revisão que o fornecedor enviou. <span style="color:#1a8a4a">▼ caiu</span> · <span style="color:#c0392b">▲ subiu</span> vs a revisão anterior. A <b>vigente</b> é a que entra no mapa comparativo.</div><div style="overflow:auto;max-height:70vh">${h}</div></div>`;
}
async function cotFinalizar(){ const c=COT.cur.cotacao, novo=c.status==='finalizada'?'aguardando':'finalizada';
  let numPedido;
  // multi-coligada: NÃO pede PC único — o servidor exige 1 PC POR COLIGADA (preenchidos no painel por coligada).
  if(novo==='finalizada' && !c.servico_id && c.multi_coligada){
    const cols=c.coligadas_itens||[]; const faltam=cols.filter(cc=>!(cc.num_pedido&&String(cc.num_pedido).trim()));
    if(faltam.length && !IS_ADMIN){ toast('Preencha o Nº do PEDIDO de cada coligada (faltam '+faltam.length+') antes de finalizar.'); return; }
    if(faltam.length && IS_ADMIN && !confirm('Finalizar com '+faltam.length+' coligada(s) SEM nº de pedido? (exceção de admin)')) return;
  }
  // TRAVA (coligada única / avulsa): só finaliza com nº do pedido de compra — admin pode furar
  else if(novo==='finalizada' && !c.servico_id && !(c.num_pedido&&String(c.num_pedido).trim())){
    const pc=prompt('Nº do PEDIDO DE COMPRA (obrigatório para finalizar esta cotação avulsa):', c.num_pedido||'');
    if(pc===null) return;
    if(pc.trim()===''){
      if(!IS_ADMIN){ toast('Informe o nº do pedido de compra para finalizar.'); return; }
      if(!confirm('Finalizar SEM o nº do pedido de compra? (exceção de admin)')) return;
    }
    numPedido=pc.trim();
  }
  try{ const body={acao:'status',me:EU&&EU.bitrix_id,cotacao_id:c.id,status:novo};
    if(numPedido!==undefined) body.num_pedido=numPedido;
    const r=await (await fetch('actions/cotacoes.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(body)})).json();
    if(r&&r.error){ toast(r.error); return; }
    cotOpen(c.id);
  }catch(e){toast('Falha');} }
async function cotNumerosSalvar(){ const c=COT.cur.cotacao; const sc=val('cotDetSC'); const pcEl=document.getElementById('cotDetPC');
  const body={acao:'numeros_salvar',me:EU&&EU.bitrix_id,cotacao_id:c.id,num_solicitacao:sc};
  if(pcEl) body.num_pedido=pcEl.value;   // multi-coligada NÃO tem campo de PC único (é por coligada) — não mexe no num_pedido
  try{ const r=await (await fetch('actions/cotacoes.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(body)})).json();
    if(r&&r.error){toast(r.error);return;} c.num_solicitacao=sc; if(pcEl)c.num_pedido=pcEl.value; toast('Números salvos'); }catch(e){toast('Falha: '+e.message);} }
// apelido / descrição curta da cotação (ex.: "Pregos") — o criador acha fácil na lista + busca
async function cotApelidoEditar(){ const c=COT.cur.cotacao;
  const v=prompt('Apelido / descrição curta desta cotação (ex.: "Pregos") — pra você achar fácil na lista:', c.apelido||'');
  if(v===null)return;
  try{ const r=await (await fetch('actions/cotacoes.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({acao:'apelido_salvar',me:EU&&EU.bitrix_id,cotacao_id:c.id,apelido:v.trim()})})).json();
    if(r&&r.error){toast(r.error);return;} c.apelido=r.apelido||''; toast(c.apelido?'Apelido salvo':'Apelido removido'); cotOpen(c.id); }catch(e){toast('Falha: '+e.message);} }
/* "Fotinha" do pedido de compra (dados do TOTVS via Supabase): fornecedor(es), itens, preços unit e total */
/* Faixa de APROVAÇÃO no topo do popup: a mesma leitura da tabela, com espaço p/ o texto inteiro
   (na tabela o motivo da reprovação vai truncado; aqui cabe). */
function bpAprovFaixa(p){
  const raw=(p.aprovacao||'').toLowerCase();
  let k='sem';
  if(raw.indexOf('aprovad')===0) k='aprovado'; else if(raw.indexOf('reprov')===0) k='reprovado'; else if(raw.indexOf('pend')===0) k='pendente';
  const a=BP_APROV[k];
  let etapa=(p.aprovacao_etapa||'').trim();
  if(/^sem v/i.test(etapa)||/^aprovado$/i.test(etapa)||/^reprovado$/i.test(etapa)) etapa='';
  etapa=etapa.replace(/^aguardando\s+/i,'');
  const reg=(p.aprovacao_reg||'').trim();
  const m=reg.match(/^aprovado\s+por\s+(.+)$/i);
  const por=m?m[1]:''; const obs=(!m&&reg&&reg!=='.')?reg:'';
  const linhas=[];
  if(k==='pendente'&&etapa) linhas.push('parado em <b>'+esc(etapa)+'</b>');
  if(por) linhas.push('último registro: aprovado por <b>'+esc(por)+'</b>');
  if(obs) linhas.push((k==='reprovado'?'motivo: ':'observação: ')+'<b>'+esc(obs)+'</b>');
  return '<div style="display:flex;align-items:flex-start;gap:8px;background:'+a.bg+';border-radius:9px;padding:9px 12px;margin-bottom:10px">'
    +'<span class="material-icons" style="font-size:20px;color:'+a.cor+'">'+a.ic+'</span>'
    +'<div><div style="font-weight:800;font-size:13px;color:'+a.cor+'">'+esc(a.t)+'</div>'
    +(linhas.length?'<div style="font-size:11.5px;color:#4a5560;margin-top:2px">'+linhas.join(' · ')+'</div>'
      :'<div style="font-size:11px;color:var(--muted);margin-top:2px">'+(k==='sem'?'este pedido não passou pelo fluxo de alçadas do Fluig':'sem registro adicional no Fluig')+'</div>')
    +'</div></div>';
}
async function cotPedidoVer(numero,coligadaCod,obraId){
  // ⚠️ o nº do PC NÃO é único entre coligadas — SEMPRE mandar a coligada (ou a obra, que o servidor resolve).
  numero=String(numero||'').split(',')[0].trim(); if(!numero){toast('Sem nº de pedido');return;}
  let ov=document.getElementById('pedOverlay'); if(!ov){ ov=document.createElement('div'); ov.id='pedOverlay'; ov.style.cssText='position:fixed;inset:0;background:rgba(15,25,20,.42);z-index:9999;display:flex;align-items:center;justify-content:center;padding:20px'; document.body.appendChild(ov); }
  ov.onclick=()=>ov.remove();
  const shell=b=>`<div style="background:#fff;border-radius:14px;padding:18px;max-width:740px;width:100%;max-height:85vh;overflow:auto;box-shadow:0 12px 44px rgba(0,0,0,.22)" onclick="event.stopPropagation()">${b}</div>`;
  const close=`<span class="material-icons" onclick="document.getElementById('pedOverlay').remove()" style="cursor:pointer;color:var(--muted)">close</span>`;
  ov.innerHTML=shell(`<div class="dempty">Buscando o pedido ${esc(numero)} no TOTVS…</div>`);
  try{ const r=await (await fetch('actions/pedidos.php?numero='+encodeURIComponent(numero)
      +(coligadaCod?'&coligada_cod='+encodeURIComponent(coligadaCod):'')
      +(obraId?'&obra_id='+encodeURIComponent(obraId):'')
      +'&me='+encodeURIComponent((EU&&EU.bitrix_id)||''))).json();
    if(r.error){ ov.innerHTML=shell(`<div style="display:flex;justify-content:space-between;align-items:center"><b>Pedido ${esc(numero)}</b>${close}</div><div class="empty" style="margin-top:10px">${esc(r.error)}</div>`); return; }
    // DESAMBIGUAÇÃO: mesmo nº de PC em várias coligadas (ou não achado na coligada da obra) → escolher, NUNCA misturar
    if(r.desambiguar){ const d=r.desambiguar, ops=d.opcoes||[];
      const aviso=d.nao_encontrado_na_coligada
        ? `Não achei o PC <b>${esc(numero)}</b> na coligada da obra (<b>${esc(d.coligada||d.coligada_cod)}</b>). Ele existe em ${ops.length} outra(s) — confira se o número está certo:`
        : `O nº <b>${esc(numero)}</b> existe em <b>${ops.length} coligadas</b> (o número de PC se repete entre obras). Escolha a obra certa:`;
      ov.innerHTML=shell(`<div style="display:flex;justify-content:space-between;align-items:center"><b style="font-size:15px">Pedido ${esc(numero)}</b>${close}</div>
        <div style="margin:8px 0 10px;font-size:12.5px;color:#6b5d1f;background:#fff9e6;border:1px solid #efe3b0;border-radius:8px;padding:9px 12px">${aviso}</div>
        ${ops.map(o=>`<div style="border:1px solid var(--line);border-radius:9px;padding:9px 12px;margin-bottom:7px;display:flex;align-items:center;gap:9px;flex-wrap:wrap;cursor:pointer" onclick="cotPedidoVer('${esc(numero)}','${esc(o.coligada_cod)}')">
            <b style="font-size:13px">${esc(o.coligada||o.coligada_cod)}</b>
            <span class="muted" style="font-size:11px">${o.n_itens} item(ns)${o.ccusto_cod?' · c.custo '+esc(o.ccusto_cod):''}</span>
            <span class="muted" style="font-size:11px">${esc((o.fornecedores||[]).join(', '))}</span>
            <b style="margin-left:auto;color:var(--verde-d)">${BRL(o.total)}</b></div>`).join('')}`);
      return; }
    const p=r.pedido, forn=(p.fornecedores||[]).join(', ')||'—';
    const multiForn=(p.fornecedores||[]).length>1;   // 1 fornecedor só? o nome já está no cabeçalho — libera espaço p/ a observação
    ov.innerHTML=shell(`<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:4px"><b style="font-size:15px"><span class="material-icons" style="font-size:17px;vertical-align:-3px;color:var(--verde-d)">receipt_long</span> Pedido de compra ${esc(p.numero)}</b>${close}</div>
      <div class="muted" style="font-size:11.5px;margin-bottom:10px"><b>${esc(p.coligada||'')}</b>${p.ccusto_cod?' · c.custo '+esc(p.ccusto_cod):''}${p.data?' · '+D(String(p.data).slice(0,10)):''}${p.status?' · TOTVS '+esc(p.status):''} · ${p.n_itens} item(ns)${p.solic_numeros?' · SC '+esc(String(p.solic_numeros).replace(/^0+/,'')):''}${p.usuario?' · criado por <b>'+esc(p.usuario)+'</b>':''}</div>
      ${bpAprovFaixa(p)}
      <div style="margin-bottom:9px;font-size:12.5px"><span class="muted">Fornecedor(es):</span> <b>${esc(forn)}</b></div>
      <div style="overflow-x:auto"><table class="mtable" style="border:none;table-layout:fixed;width:100%"><thead><tr><th class="svc-h" style="text-align:left;width:${multiForn?'26%':'34%'}">Item</th><th style="text-align:left;width:${multiForn?'28%':'36%'}">Observação</th>${multiForn?'<th style="text-align:left;width:18%">Fornecedor</th>':''}<th style="text-align:right;width:10%">Qtde</th><th style="text-align:right;width:13%">Preço unit.</th><th style="text-align:right;width:13%">Total</th></tr></thead><tbody>
      ${p.itens.map(it=>`<tr><td class="svc-c" style="text-align:left;font-size:12px">${esc(it.produto)}<small>${it.codprd?esc(it.codprd):''}</small></td><td style="text-align:left;font-size:11px;color:#4a5560;white-space:pre-wrap;word-break:break-word;line-height:1.35">${it.observacao?esc(it.observacao):'<span class="muted">—</span>'}</td>${multiForn?`<td style="text-align:left;font-size:11px">${esc(it.fornecedor_fantasia||it.fornecedor_nome||('cód. '+(it.fornecedor_cod||'—')))}</td>`:''}<td style="text-align:right">${cotNum(it.qtd)} ${esc(it.und||'')}</td><td style="text-align:right">${BRLp(it.preco_unit)}</td><td style="text-align:right"><b>${BRL(it.total)}</b></td></tr>`).join('')}
      <tr style="background:#f7faf8"><td class="svc-c" style="text-align:left;font-weight:800" colspan="${multiForn?4:3}">TOTAL</td><td></td><td style="text-align:right;font-weight:800;color:var(--verde-d)">${BRL(p.total)}</td></tr>
      </tbody></table></div>
      <div class="dmini" style="margin-top:8px">Dados do TOTVS (somente leitura), filtrados pela coligada desta obra. O total usa preço unit × qtde quando o valor líquido ainda não foi gravado no TOTVS.</div>`);
  }catch(e){ ov.innerHTML=shell('<div class="empty">Falha ao buscar o pedido.</div>'); }
}
/* Apaga SÓ a proposta — o fornecedor continua convidado (é o caso "lancei errado, quero refazer").
   Motivo só quando há valor lançado: exigir justificativa para apagar proposta zerada seria atrito
   sem retorno. Quem quer tirar o fornecedor inteiro usa o × da Concorrência. */
async function cotExcluirProposta(pid){
  const p=((COT.cur&&COT.cur.propostas)||[]).find(x=>String(x.id)===String(pid));
  const tot=p?(+p.total||0):0, nome=p?p.fornecedor_nome:'';
  if(tot>0){ cotExcluirPropostaMotivo(pid,nome,tot); return; }
  if(!confirm('Excluir esta proposta'+(nome?(' de "'+nome+'"'):'')+'? O fornecedor continua na concorrência.')) return;
  cotExcluirPropostaEnviar(pid,'');
}
function cotExcluirPropostaMotivo(pid,nome,tot){
  dlgAbrir('Cotações','Excluir proposta',
    '<div style="max-width:500px">'
   + '<div class="dmini" style="margin-bottom:10px">Apagando a proposta'+(nome?(' de <b>'+esc(nome)+'</b>'):'')
   + ' — <b>'+BRL(tot)+'</b>, com as revisões dela. O fornecedor <b>continua</b> na concorrência.</div>'
   + cotFld('Motivo (fica no histórico da cotação)','<input id="cotExcMot" placeholder="ex.: lancei o preço errado, vou refazer" style="width:100%">')
   + '<div class="bar" style="justify-content:flex-end;gap:8px;margin-top:14px">'
   + '<button class="btn-ghost" onclick="closeModal(true)">Cancelar</button>'
   + '<button class="btn-prim" style="background:var(--pend)" onclick="cotExcluirPropostaOk('+pid+')">Excluir proposta</button></div></div>');
}
function cotExcluirPropostaOk(pid){
  const m=((document.getElementById('cotExcMot')||{}).value||'').trim();
  if(!m){ toast('Escreva o motivo'); return; }
  closeModal(true); cotExcluirPropostaEnviar(pid,m);
}
async function cotExcluirPropostaEnviar(pid,motivo){
  try{ const r=await (await fetch('actions/cotacoes.php',{method:'POST',headers:{'Content-Type':'application/json'},
        body:JSON.stringify({acao:'excluir_proposta',me:EU&&EU.bitrix_id,proposta_id:pid,motivo:motivo})})).json();
    if(r&&r.error){ toast(r.error); return; }
    toast('Proposta excluída'); cotOpen(COT.cur.cotacao.id);
  }catch(e){toast('Falha: '+e.message);} }
/* ───────── DESQUALIFICAR PROPOSTA ─────────
   Excluir apaga; desqualificar REGISTRA. São coisas diferentes e a diferença importa: quando a
   proposta mais barata não pode ser contratada (prazo, especificação, condição de pagamento…),
   quem abrir o mapa daqui a um ano precisa ver que ela existiu e por que não venceu — senão o
   mapa parece que "escolheu o caro". A proposta fica no mapa, marcada, fora do julgamento.
   E é a PROPOSTA que é desqualificada, nunca o fornecedor: ele segue convidado e pode revisar. */
function cotDesqAbrir(pid){
  const d=COT.cur||{}, p=(d.propostas||[]).find(x=>String(x.id)===String(pid));
  if(!p){toast('Proposta não encontrada');return;}
  const ms=d.desq_motivos||[];
  if(!ms.length){toast('Recarregue a página — a lista de motivos não veio do servidor');return;}
  const quem=esc(p.fornecedor_nome||'')+((p.opcao||1)>1?(' · opção '+p.opcao+(p.opcao_rotulo?' ('+esc(p.opcao_rotulo)+')':'')):'');
  dlgAbrir('Cotações','Desqualificar proposta',
    '<div style="max-width:560px">'
  + '<div class="dmini" style="margin-bottom:10px;background:#fdf6f6;border:1px solid #f2d7d4;padding:9px 12px;border-radius:9px">'
  + 'Desqualificando a proposta de <b>'+quem+'</b>'+(p.total!=null?(' — <b>'+BRL(p.total)+'</b>'):'')+'.<br>'
  + 'Ela <b>continua no mapa</b>, marcada com o motivo, mas sai do julgamento: não disputa o melhor preço por item nem a melhor oferta. '
  + '<b>O fornecedor não é desqualificado</b> — ele segue na concorrência e pode mandar uma nova revisão a qualquer momento.</div>'
  + cotFld('Motivo da desqualificação *','<select id="cotDesqM" onchange="cotDesqMotivoIn()" style="width:100%">'
      + '<option value="">— escolha o motivo —</option>'
      + ms.map(m=>'<option value="'+esc(m.cod)+'">'+esc(m.label)+'</option>').join('')+'</select>')
  + cotFld('Justificativa <span id="cotDesqJL" class="muted">(opcional — detalhe o que aconteceu)</span>',
      '<textarea id="cotDesqJ" rows="3" style="width:100%" placeholder="Ex.: prazo de 45 dias contra os 20 exigidos pela obra."></textarea>','margin-top:8px')
  + '<div class="dmini" style="margin-top:8px">Fica registrado no <b>Histórico</b> da cotação com seu nome, data e hora.</div>'
  + '<div class="bar" style="justify-content:flex-end;gap:8px;margin-top:14px">'
  + '<button class="btn-ghost" onclick="closeModal(true)">Cancelar</button>'
  + '<button class="btn-prim" style="background:#b3261e" onclick="cotDesqSalvar('+pid+')">Desqualificar proposta</button></div></div>');
}
// "outro" não tem rótulo próprio: ali a justificativa É o motivo, então vira obrigatória
function cotDesqMotivoIn(){
  const v=((document.getElementById('cotDesqM')||{}).value||''), lb=document.getElementById('cotDesqJL'), ta=document.getElementById('cotDesqJ');
  if(lb) lb.innerHTML=(v==='outro')?'<b style="color:#b3261e">(obrigatória — descreva o motivo)</b>':'(opcional — detalhe o que aconteceu)';
  if(ta&&v==='outro') ta.focus();
}
async function cotDesqSalvar(pid){
  const motivo=((document.getElementById('cotDesqM')||{}).value||'').trim();
  const just=((document.getElementById('cotDesqJ')||{}).value||'').trim();
  if(!motivo){toast('Escolha o motivo');return;}
  if(motivo==='outro'&&just.length<5){toast('Em "outro motivo", escreva a justificativa');const e=document.getElementById('cotDesqJ');if(e)e.focus();return;}
  try{ const r=await (await fetch('actions/cotacoes.php',{method:'POST',headers:{'Content-Type':'application/json'},
        body:JSON.stringify({acao:'proposta_desqualificar',me:EU&&EU.bitrix_id,proposta_id:pid,motivo:motivo,justificativa:just})})).json();
    if(r&&r.error){toast(r.error);return;}
    closeModal(true); toast('Proposta desqualificada — o fornecedor continua na concorrência'); cotOpen(COT.cur.cotacao.id);
  }catch(e){toast('Falha: '+e.message);}
}
async function cotDesqDesfazer(pid){
  const p=((COT.cur&&COT.cur.propostas)||[]).find(x=>String(x.id)===String(pid));
  if(!confirm('Requalificar a proposta'+(p?(' de "'+(p.fornecedor_nome||'')+'"'):'')+'?\nEla volta a concorrer no mapa. A desqualificação e a requalificação ficam no histórico.')) return;
  try{ const r=await (await fetch('actions/cotacoes.php',{method:'POST',headers:{'Content-Type':'application/json'},
        body:JSON.stringify({acao:'proposta_desqualificar',me:EU&&EU.bitrix_id,proposta_id:pid,desfazer:1})})).json();
    if(r&&r.error){toast(r.error);return;}
    toast('Proposta requalificada — voltou a concorrer'); cotOpen(COT.cur.cotacao.id);
  }catch(e){toast('Falha: '+e.message);}
}
// ícone por tipo de anexo
function cotAnexoIcon(mime,nome){ const m=(mime||'')+' '+(nome||'');
  if(/pdf/i.test(m))return'picture_as_pdf'; if(/png|jpe?g|image/i.test(m))return'image'; if(/sheet|excel|xls/i.test(m))return'table_view'; return'insert_drive_file'; }
// modal de anexo multi-formato (PDF/Excel/imagem) POR fornecedor — arrastar, colar (Ctrl+V) ou clicar
function cotAnexarAbrir(fornId,fornNome){ COT.anexo={fornId:(fornId&&fornId!=='null')?fornId:null,fornNome:fornNome||'',files:[]};
  let ov=document.getElementById('anexOverlay'); if(!ov){ ov=document.createElement('div'); ov.id='anexOverlay'; ov.style.cssText='position:fixed;inset:0;background:rgba(15,25,20,.42);z-index:9999;display:flex;align-items:center;justify-content:center;padding:20px'; document.body.appendChild(ov); }
  document.addEventListener('paste',cotAnexarPaste); cotAnexarRender(); }
function cotAnexarFechar(){ const ov=document.getElementById('anexOverlay'); if(ov)ov.remove(); document.removeEventListener('paste',cotAnexarPaste); COT.anexo=null; }
function cotAnexarPaste(e){ if(!COT.anexo)return; const items=((e.clipboardData||{}).items)||[]; let n=0;
  for(const it of items){ if(it.kind==='file'){ const f=it.getAsFile(); if(f){ COT.anexo.files.push(f.name?f:new File([f],'print-'+Date.now()+'.png',{type:f.type||'image/png'})); n++; } } }
  if(n){ e.preventDefault(); cotAnexarRender(); toast(n+' print colado'); } }
function cotAnexarDrop(e){ e.preventDefault(); if(!COT.anexo)return; for(const f of (((e.dataTransfer||{}).files)||[]))COT.anexo.files.push(f); cotAnexarRender(); }
function cotAnexarPick(input){ if(!COT.anexo)return; for(const f of (input.files||[]))COT.anexo.files.push(f); input.value=''; cotAnexarRender(); }
function cotAnexarRender(){ const a=COT.anexo, ov=document.getElementById('anexOverlay'); if(!a||!ov)return;
  ov.onclick=cotAnexarFechar;
  ov.innerHTML=`<div style="background:#fff;border-radius:14px;padding:18px;box-shadow:0 12px 44px rgba(0,0,0,.22);width:100%;max-width:470px" onclick="event.stopPropagation()">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px"><b style="font-size:14px">Anexar para ${esc(a.fornNome)||'fornecedor'}</b><span onclick="cotAnexarFechar()" class="material-icons" style="cursor:pointer;color:var(--muted)">close</span></div>
    <label ondragover="event.preventDefault()" ondrop="cotAnexarDrop(event)" style="display:block;border:2px dashed var(--line);border-radius:12px;padding:22px;text-align:center;cursor:pointer;background:#fafbfb">
      <span class="material-icons" style="font-size:30px;color:var(--verde)">upload_file</span>
      <div style="font-size:12.5px;margin-top:4px">Arraste, <b>cole (Ctrl+V)</b> ou clique</div>
      <div class="muted" style="font-size:11px;margin-top:2px">PDF, Excel (xlsx/xls) ou imagem (PNG/JPG) · até 25 MB</div>
      <input type="file" accept=".pdf,.xlsx,.xls,image/png,image/jpeg,application/pdf" multiple style="display:none" onchange="cotAnexarPick(this)"></label>
    <div style="margin-top:10px">${a.files.length?a.files.map((f,i)=>`<div style="display:flex;align-items:center;gap:7px;padding:4px 0"><span class="material-icons" style="font-size:16px;color:var(--muted)">${cotAnexoIcon(f.type,f.name)}</span><span style="flex:1;font-size:12px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">${esc(f.name)}</span><span class="muted" style="font-size:10.5px">${(f.size/1024).toFixed(0)} KB</span><span onclick="COT.anexo.files.splice(${i},1);cotAnexarRender()" class="material-icons" style="cursor:pointer;color:var(--pend);font-size:16px">close</span></div>`).join(''):'<div class="dmini">Nenhum arquivo ainda.</div>'}</div>
    <div style="display:flex;gap:8px;justify-content:flex-end;margin-top:14px">
      <button class="btn-ghost" onclick="cotAnexarFechar()">Cancelar</button>
      <button class="btn-ghost" onclick="cotAnexarEnviar(true)" ${a.files.length?'':'disabled style=\"opacity:.5\"'} title="anexa e já manda a IA preencher a proposta"><span class="material-icons" style="font-size:14px;vertical-align:-3px;color:var(--verde-d)">auto_awesome</span> Anexar + IA</button>
      <button class="btn-prim" onclick="cotAnexarEnviar(false)" ${a.files.length?'':'disabled style=\"opacity:.5\"'}><span class="material-icons" style="font-size:15px;vertical-align:-3px">attach_file</span> Anexar${a.files.length?' '+a.files.length:''}</button>
    </div></div>`; }
async function cotAnexarEnviar(runIA){ const a=COT.anexo; if(!a||!a.files.length)return; const files=a.files.slice(), fornId=a.fornId, fornNome=a.fornNome;
  toast('Enviando '+files.length+' arquivo(s)…'); let ok=0,fail=0;
  for(const f of files){ if(await cotUploadAnexoFile(f,fornId,fornNome))ok++; else fail++; }
  cotAnexarFechar(); toast(ok+' anexado(s)'+(fail?' · '+fail+' falharam':''));
  if(ok){ await cotOpen(COT.cur.cotacao.id); if(runIA) cotIAPreencher(fornId,fornNome); } }
async function cotUploadAnexoFile(file,fornId,fornNome,propostaId){
  if(file.size>25*1024*1024){ toast('"'+file.name+'": máx 25 MB'); return false; }
  const fd=new FormData(); fd.append('arquivo',file); fd.append('cotacao_id',COT.cur.cotacao.id);
  if(fornId)fd.append('fornecedor_id',fornId); if(fornNome)fd.append('fornecedor_nome',fornNome); if(propostaId)fd.append('proposta_id',propostaId);
  fd.append('me',(EU&&EU.bitrix_id)||'');
  try{ const r=await (await fetch('actions/cotacao_anexo.php',{method:'POST',body:fd})).json(); if(r.error){ toast(file.name+': '+r.error); return null; } return r; }
  catch(e){ toast('Falha: '+e.message); return null; } }
/* --- Motor de IA: lê os anexos do fornecedor e preenche a proposta (RASCUNHO p/ validação humana) --- */
function cotIAForn(fornId,fornNome){ const d=COT.cur||{}, nz=s=>String(s||'').trim().toLowerCase().replace(/\s+/g,' ');
  return (d.anexos||[]).filter(a=>((a.fornecedor_id&&fornId&&String(a.fornecedor_id)===String(fornId))||(a.fornecedor_nome&&nz(a.fornecedor_nome)===nz(fornNome)))); }
function cotIAPreencher(fornId,fornNome){ fornId=(fornId&&fornId!=='null')?fornId:null;
  const ax=cotIAForn(fornId,fornNome); if(!ax.length){ toast('Anexe um PDF, Excel ou print desse fornecedor primeiro'); return; }
  COT.ia={fornId,fornNome,sel:ax.map(a=>a.id),anexos:ax,busy:false}; cotIARender(); }
function cotIAFechar(){ const ov=document.getElementById('iaOverlay'); if(ov)ov.remove(); COT.ia=null; }
function cotIAToggle(id,on){ const s=COT.ia; if(!s)return; s.sel=on?[...new Set([...s.sel,id])]:s.sel.filter(x=>x!==id); cotIARender(); }
function cotIARender(){ const s=COT.ia; if(!s)return; let ov=document.getElementById('iaOverlay');
  if(!ov){ ov=document.createElement('div'); ov.id='iaOverlay'; ov.style.cssText='position:fixed;inset:0;background:rgba(15,25,20,.42);z-index:9999;display:flex;align-items:center;justify-content:center;padding:20px'; document.body.appendChild(ov); }
  ov.onclick=()=>{ if(!s.busy)cotIAFechar(); };
  ov.innerHTML=`<div style="background:#fff;border-radius:14px;padding:18px;box-shadow:0 12px 44px rgba(0,0,0,.22);width:100%;max-width:470px" onclick="event.stopPropagation()">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px"><b style="font-size:14px"><span class="material-icons" style="font-size:16px;vertical-align:-3px;color:var(--verde-d)">auto_awesome</span> Preencher proposta com IA</b><span onclick="cotIAFechar()" class="material-icons" style="cursor:pointer;color:var(--muted)">close</span></div>
    <div class="muted" style="font-size:11.5px;margin-bottom:10px">${esc(s.fornNome)||'fornecedor'} — escolha o(s) anexo(s) que a IA vai ler:</div>
    <div style="display:flex;flex-direction:column;gap:4px;max-height:230px;overflow:auto">${s.anexos.map(a=>`<label style="display:flex;align-items:center;gap:8px;font-size:12.5px;cursor:pointer;padding:4px 2px"><input type="checkbox" ${s.sel.includes(a.id)?'checked':''} onchange="cotIAToggle(${a.id},this.checked)"><span class="material-icons" style="font-size:16px;color:var(--muted)">${cotAnexoIcon(a.mime,a.nome)}</span><span style="flex:1;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">${esc(a.nome)}</span></label>`).join('')}</div>
    <div style="background:#fbf7e8;border:1px solid #eadfb0;border-radius:8px;padding:7px 10px;margin-top:10px;font-size:11px;color:#6b5a1e">A IA gera um <b>rascunho</b> — você confere e ajusta os valores antes de salvar.</div>
    <div style="display:flex;gap:8px;justify-content:flex-end;margin-top:12px">${s.busy?'<span class="muted" style="font-size:12px;align-self:center">🧠 lendo os anexos…</span>':`<button class="btn-ghost" onclick="cotIAFechar()">Cancelar</button><button class="btn-prim" onclick="cotIAExecutar()" ${s.sel.length?'':'disabled style=\"opacity:.5\"'}><span class="material-icons" style="font-size:15px;vertical-align:-3px">auto_awesome</span> Preencher</button>`}</div>
  </div>`; }
async function cotIAExecutar(){ const s=COT.ia; if(!s||!s.sel.length||s.busy)return; s.busy=true; cotIARender();
  try{ const r=await (await fetch('actions/cotacao_ia.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({acao:'preencher',me:EU&&EU.bitrix_id,cotacao_id:COT.cur.cotacao.id,fornecedor_id:s.fornId,fornecedor_nome:s.fornNome,anexo_ids:s.sel})})).json();
    if(r.error){ toast(r.error); s.busy=false; cotIARender(); return; }
    const fn=s.fornNome; cotIAFechar(); cotIAAplicar(fn,r.draft,r,s.fornId);   // leva o id: a proposta da IA também tem que nascer vinculada
  }catch(e){ toast('Falha: '+e.message); s.busy=false; cotIARender(); } }
async function cotIAAplicar(fornNome,draft,meta,fornId){ const d=COT.cur; draft=draft||{}; const nz=s=>String(s||'').trim().toLowerCase().replace(/\s+/g,' ');
  const ex=(d.propostas||[]).find(p=>nz(p.fornecedor_nome)===nz(fornNome));   // já tem proposta? edita; senão cria
  const fidIA = fornId || (ex&&ex.fornecedor_id) || null;
  COT.prop={id:ex?ex.id:0, precos:{}, fornecedor_id:fidIA,
            _fornPick: fidIA?{id:fidIA, nome:fornNome}:null};
  COT.prop.opcao=ex?(ex.opcao||1):1; COT.prop.opcao_rotulo=ex?(ex.opcao_rotulo||''):'';
  (d.itens||[]).forEach(it=>{ COT.prop.precos[it.id]={preco_unit:'',preco_total:'',observacao:''}; });
  const byId={}; (draft.itens||[]).forEach(x=>{ if(x&&x.item_id!=null)byId[x.item_id]=x; });
  let preench=0;
  (d.itens||[]).forEach(it=>{ const x=byId[it.id]; if(!x)return;
    if(x.observacao) COT.prop.precos[it.id].observacao=String(x.observacao);   // detalhe do item vai p/ o campo do item (aparece no mapa), não p/ o texto geral
    if(x.preco_unit!=null&&x.preco_unit!==''){ const u=Number(x.preco_unit);
    if(!isNaN(u)){ COT.prop.precos[it.id].preco_unit=u; const q=it.quantidade?Number(it.quantidade):null; if(q)COT.prop.precos[it.id].preco_total=+(u*q).toFixed(4); preench++; } } });
  COT.prop.fornecedor_nome=fornNome; COT.prop.prazo=draft.prazo_entrega||'';
  const partes=[];
  if(Array.isArray(draft.extras)&&draft.extras.length) partes.push('Custos adicionais: '+draft.extras.map(e=>`${e.descricao||'extra'}${(e.valor!=null&&e.valor!=='')?' '+BRL(Number(e.valor)):''}`).join('; '));
  if(draft.condicao_pagamento) partes.push('Pagamento: '+draft.condicao_pagamento);
  if(draft.validade) partes.push('Validade: '+draft.validade);
  if(draft.observacao_geral) partes.push(draft.observacao_geral);
  let obs='⚠ Rascunho gerado por IA'+(meta&&meta.usados&&meta.usados.length?' (fonte: '+meta.usados.join(', ')+')':'')+' — confira os valores antes de salvar.';
  if(partes.length) obs+='\n\n'+partes.join('\n');
  COT.prop.observacoes=obs;
  // EQUALIZAÇÃO da IA: cria pontos novos na cotação (ex.: Imposto) + guarda os valores p/ aplicar quando a proposta for salva
  COT.prop.eqIA={}; let novos=0;
  if(Array.isArray(draft.equalizacao)){ const pts=cotEqPontos(d.cotacao), znz=s=>String(s||'').trim().toLowerCase(), add=[];
    draft.equalizacao.forEach(e=>{ if(!e||!e.ponto)return; const ponto=String(e.ponto).trim(), valor=(e.valor==null?'':String(e.valor).trim()); if(!ponto)return;
      if(valor) COT.prop.eqIA[ponto]=valor;
      if(!pts.some(p=>znz(p)===znz(ponto)) && !add.some(p=>znz(p)===znz(ponto))) add.push(ponto); });
    if(add.length){ d.cotacao.equalizacao=[...pts,...add].join('\n'); novos=add.length;
      try{ await fetch('actions/cotacoes.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({acao:'equaliza_salvar',me:EU&&EU.bitrix_id,cotacao_id:d.cotacao.id,equalizacao:d.cotacao.equalizacao})}); }catch(e){} }
  }
  COT.mode='proposta'; cotRenderProposta();
  toast(preench+' item(ns) preenchido(s) pela IA'+(novos?' · '+novos+' ponto(s) de equalização novo(s)':'')+((meta&&meta.avisos&&meta.avisos.length)?' · '+meta.avisos.length+' aviso(s)':'')); }
/* ===== E-MAIL DE COTAÇÃO — Fase 2: compositor (prévia editável, individual por fornecedor; envio real na próxima fase) ===== */
async function cotEmailAbrir(cid){
  let ov=document.getElementById('emailOverlay'); if(!ov){ ov=document.createElement('div'); ov.id='emailOverlay'; ov.style.cssText='position:fixed;inset:0;background:rgba(15,25,20,.42);z-index:9999;display:flex;align-items:center;justify-content:center;padding:20px'; document.body.appendChild(ov); }
  ov.onclick=()=>ov.remove();
  const shell=b=>`<div style="background:#fff;border-radius:14px;padding:18px;max-width:720px;width:100%;max-height:88vh;overflow:auto;box-shadow:0 12px 44px rgba(0,0,0,.22)" onclick="event.stopPropagation()">${b}</div>`;
  ov.innerHTML=shell('<div class="dempty">Montando o e-mail…</div>');
  try{ const meq=encodeURIComponent((EU&&EU.bitrix_id)||'');
    const [g,cfg]=await Promise.all([ (await fetch('actions/email.php?compor='+cid+'&me='+meq)).json(), (await fetch('actions/email.php?config=1&me='+meq)).json() ]);
    const close=`<span class="material-icons" onclick="document.getElementById('emailOverlay').remove()" style="cursor:pointer;color:var(--muted)">close</span>`;
    if(g.error){ ov.innerHTML=shell(`<div style="display:flex;justify-content:space-between"><b>E-mail de cotação</b>${close}</div><div class="empty" style="margin-top:10px">${esc(g.error)}</div>`); return; }
    const semEmail=(g.destinatarios||[]).filter(d=>!d.tem_email).length;
    const dchips=(g.destinatarios||[]).map(d=>`<span class="dchip" style="background:${d.tem_email?'#eef4f0':'#fbeae8'};color:${d.tem_email?'var(--verde-d)':'var(--pend)'};font-weight:600;margin:2px 4px 2px 0"><span class="material-icons" style="font-size:12px;vertical-align:-2px">${d.tem_email?'check':'error'}</span> ${esc(d.fornecedor_nome)}${d.email?' · '+esc(d.email):' · sem e-mail'}</span>`).join('')||'<span class="dmini">Nenhum fornecedor convidado.</span>';
    ov.innerHTML=shell(`<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:4px"><b style="font-size:15px"><span class="material-icons" style="font-size:17px;vertical-align:-3px;color:var(--verde-d)">mail</span> E-mail de cotação — prévia</b>${close}</div>
      <div class="muted" style="font-size:11.5px;margin-bottom:10px">Modelo <b>${g.variante==='radar'?'do radar (com carta anexa)':'de solicitação (itens no corpo)'}</b> · remetente <b>${esc(g.remetente)}</b> · assinatura de <b>${esc(g.remetente_nome||'—')}</b>. Cada fornecedor recebe <b>individualmente</b>.</div>
      <div style="font-size:11px;font-weight:700;color:var(--muted)">DESTINATÁRIOS ${semEmail?`<span style="color:var(--pend)">· ${semEmail} sem e-mail (preencha na Concorrência)</span>`:'✓'}</div>
      <div style="margin:5px 0 10px">${dchips}</div>
      ${cotFld('Assunto','<input id="emAssunto" value="'+esc(g.assunto)+'" style="width:100%">')}
      ${cotFld('Corpo do e-mail (edite à vontade antes de disparar)','<textarea id="emCorpo" rows="9" style="width:100%;font-size:12.5px;font-family:inherit">'+esc(g.corpo)+'</textarea>','margin-top:8px')}
      ${g.tem_carta?'<div class="dmini" style="margin-top:6px">📎 A carta de cotação vai anexada em PDF.</div>':''}
      <div style="display:flex;align-items:flex-end;gap:8px;flex-wrap:wrap;margin-top:12px;border-top:1px solid var(--line);padding-top:12px">
        <div style="flex:1;min-width:190px">${cotFld('Enviar um TESTE para (só você recebe)','<input id="emTeste" value="'+esc((EU&&EU.email)||'')+'" placeholder="seu@email.com" style="width:100%">')}</div>
        <button class="btn-ghost" style="padding:7px 13px" onclick="cotEmailTeste()"><span class="material-icons" style="font-size:15px;vertical-align:-3px">outbox</span> Enviar teste</button>
        <button class="btn-prim" style="padding:7px 15px;font-weight:700" onclick="cotEmailDisparar()" ${g.configurada?'':'disabled style=\"opacity:.5\" title=\"configure a conta em Configurações › E-mail\"'}><span class="material-icons" style="font-size:16px;vertical-align:-3px">send</span> Disparar p/ ${(g.destinatarios||[]).filter(d=>d.tem_email).length} fornecedor(es)</button>
      </div>
      <div class="dmini" style="margin-top:6px">Cada fornecedor recebe individualmente (sem cópia). Faça o teste pra você antes de disparar.</div>
      ${cfg.is_admin?`<details style="margin-top:14px;border:1px solid var(--line);border-radius:10px;padding:8px 10px"><summary style="cursor:pointer;font-size:12px;color:var(--muted)"><span class="material-icons" style="font-size:14px;vertical-align:-3px">settings</span> Conta de envio (admin) — ${cfg.configurada?'<b style="color:var(--ok)">configurada ✓</b>':'<b style="color:var(--pend)">falta a senha</b>'} <span class="muted">· também em Configurações › E-mail</span></summary>
        <div style="display:grid;grid-template-columns:1fr 90px;gap:8px;margin-top:8px">${cotFld('Servidor SMTP','<input id="emHost" value="'+esc(cfg.host||'')+'" style="width:100%">')}${cotFld('Porta','<input id="emPort" type="number" value="'+esc(cfg.port||465)+'" style="width:100%">')}</div>
        <div style="display:grid;grid-template-columns:1fr;gap:8px;margin-top:6px">${cotFld('Usuário (e-mail)','<input id="emUser" value="'+esc(cfg.user||'')+'" style="width:100%">')}${cotFld('Senha (fica só no servidor; vazio mantém a atual)','<input id="emSenha" type="password" autocomplete="new-password" placeholder="••••••••" style="width:100%">')}</div>
        <div style="margin-top:8px"><button class="btn-prim" style="padding:5px 12px" onclick="cotEmailConfigSalvar()">Salvar conta</button></div></div></details>`:''}`);
  }catch(e){ ov.innerHTML=shell('<div class="empty">Falha ao montar o e-mail.</div>'); }
}
function cotEmailBody(){ return {cotacao_id:(COT.cur&&COT.cur.cotacao&&COT.cur.cotacao.id)||0, assunto:(document.getElementById('emAssunto')||{}).value||'', corpo:(document.getElementById('emCorpo')||{}).value||''}; }
async function cotEmailConfigSalvar(){ const g=id=>((document.getElementById(id)||{}).value||'');
  try{ const r=await (await fetch('actions/email.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({acao:'config',me:EU&&EU.bitrix_id,host:g('emHost'),port:Number(g('emPort'))||465,user:g('emUser'),senha:g('emSenha')})})).json();
    if(r.error){toast(r.error);return;} toast('Conta salva'); cotEmailAbrir((COT.cur&&COT.cur.cotacao&&COT.cur.cotacao.id)); }catch(e){toast('Falha: '+e.message);} }
async function cotEmailTeste(){ const to=((document.getElementById('emTeste')||{}).value||'').trim(); if(!to){toast('Informe um e-mail para o teste');return;}
  const b=cotEmailBody(); toast('Enviando teste…');
  try{ const r=await (await fetch('actions/email.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(Object.assign({acao:'enviar',me:EU&&EU.bitrix_id,teste:to},b))})).json();
    if(r.error){toast(r.error);return;} toast(r.msg||'Teste enviado'); }catch(e){toast('Falha: '+e.message);} }
async function cotEmailDisparar(){ const b=cotEmailBody();
  const conv=(COT.cur&&COT.cur.convidados)||[]; const comEmail=conv.filter(c=>c.email&&String(c.email).trim()).length;
  if(!comEmail){ toast('Nenhum fornecedor com e-mail preenchido'); return; }
  if(!confirm('Disparar este e-mail INDIVIDUALMENTE para '+comEmail+' fornecedor(es)?\nCada um recebe a sua própria cópia. Isso envia de verdade.')) return;
  toast('Disparando…');
  try{ const r=await (await fetch('actions/email.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(Object.assign({acao:'enviar',me:EU&&EU.bitrix_id},b))})).json();
    if(r.error){toast(r.error);return;}
    const ov=document.getElementById('emailOverlay'); if(ov)ov.remove();
    toast(r.enviados+' enviado(s)'+((r.falhas&&r.falhas.length)?' · '+r.falhas.length+' falha(s)':''));
    if(r.falhas&&r.falhas.length) setTimeout(()=>alert('Falhas:\n'+r.falhas.join('\n')),300);
    cotOpen(b.cotacao_id);
  }catch(e){toast('Falha: '+e.message);} }
/* ===== ITEM B: cadastrar proposta a partir de um PDF/print SEM escolher fornecedor — IA lê, IDENTIFICA o fornecedor e preenche ===== */
function cotPropIAAbrir(){ COT.propIA={files:[],busy:false}; let ov=document.getElementById('propiaOverlay');
  if(!ov){ ov=document.createElement('div'); ov.id='propiaOverlay'; ov.style.cssText='position:fixed;inset:0;background:rgba(15,25,20,.42);z-index:9999;display:flex;align-items:center;justify-content:center;padding:20px'; document.body.appendChild(ov); }
  document.addEventListener('paste',cotPropIAPaste); cotPropIARender(); }
function cotPropIAFechar(){ const ov=document.getElementById('propiaOverlay'); if(ov)ov.remove(); document.removeEventListener('paste',cotPropIAPaste); COT.propIA=null; COT.propIAres=null; }
function cotPropIAPaste(e){ if(!COT.propIA)return; const items=((e.clipboardData||{}).items)||[]; let n=0; for(const it of items){ if(it.kind==='file'){ const f=it.getAsFile(); if(f){ COT.propIA.files.push(f.name?f:new File([f],'print-'+Date.now()+'.png',{type:f.type||'image/png'})); n++; } } } if(n){ e.preventDefault(); cotPropIARender(); } }
function cotPropIADrop(e){ e.preventDefault(); if(!COT.propIA)return; for(const f of (((e.dataTransfer||{}).files)||[]))COT.propIA.files.push(f); cotPropIARender(); }
function cotPropIAPick(input){ if(!COT.propIA)return; for(const f of (input.files||[]))COT.propIA.files.push(f); input.value=''; cotPropIARender(); }
function cotPropIARender(){ const s=COT.propIA, ov=document.getElementById('propiaOverlay'); if(!s||!ov)return; ov.onclick=()=>{ if(!s.busy)cotPropIAFechar(); };
  ov.innerHTML=`<div style="background:#fff;border-radius:14px;padding:18px;box-shadow:0 12px 44px rgba(0,0,0,.22);width:100%;max-width:480px" onclick="event.stopPropagation()">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px"><b style="font-size:14px"><span class="material-icons" style="font-size:16px;vertical-align:-3px;color:var(--verde-d)">auto_awesome</span> Cadastrar proposta com IA</b><span onclick="cotPropIAFechar()" class="material-icons" style="cursor:pointer;color:var(--muted)">close</span></div>
    <div class="muted" style="font-size:11.5px;margin-bottom:10px">Anexe o PDF/print da proposta — a IA lê, <b>identifica o fornecedor</b> e preenche os preços (rascunho p/ conferir). Não precisa ter convidado o fornecedor.</div>
    ${s.busy?'<div class="dempty">🧠 lendo a proposta e identificando o fornecedor…</div>':`
    <label ondragover="event.preventDefault()" ondrop="cotPropIADrop(event)" style="display:block;border:2px dashed var(--line);border-radius:12px;padding:20px;text-align:center;cursor:pointer;background:#fafbfb">
      <span class="material-icons" style="font-size:28px;color:var(--verde)">upload_file</span>
      <div style="font-size:12.5px;margin-top:4px">Arraste, <b>cole (Ctrl+V)</b> ou clique — PDF, Excel ou imagem</div>
      <input type="file" accept=".pdf,.xlsx,.xls,image/png,image/jpeg,application/pdf" multiple style="display:none" onchange="cotPropIAPick(this)"></label>
    <div style="margin-top:10px">${s.files.length?s.files.map((f,i)=>`<div style="display:flex;align-items:center;gap:7px;padding:3px 0;font-size:12px"><span class="material-icons" style="font-size:15px;color:var(--muted)">${cotAnexoIcon(f.type,f.name)}</span><span style="flex:1;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">${esc(f.name)}</span><span onclick="COT.propIA.files.splice(${i},1);cotPropIARender()" class="material-icons" style="cursor:pointer;color:var(--pend);font-size:15px">close</span></div>`).join(''):'<div class="dmini">Nenhum arquivo ainda.</div>'}</div>
    <div style="display:flex;gap:8px;justify-content:flex-end;margin-top:12px"><button class="btn-ghost" onclick="cotPropIAFechar()">Cancelar</button><button class="btn-prim" onclick="cotPropIALer()" ${s.files.length?'':'disabled style=\"opacity:.5\"'}><span class="material-icons" style="font-size:15px;vertical-align:-3px">auto_awesome</span> Ler com IA</button></div>`}
  </div>`; }
async function cotPropIALer(){ const s=COT.propIA; if(!s||!s.files.length||s.busy)return; s.busy=true; cotPropIARender();
  try{ const ids=[]; for(const f of s.files){ const r=await cotUploadAnexoFile(f,null,''); if(r&&r.id)ids.push(r.id); }
    if(!ids.length){ toast('Falha ao anexar'); s.busy=false; cotPropIARender(); return; }
    const rr=await (await fetch('actions/cotacao_ia.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({acao:'preencher',me:EU&&EU.bitrix_id,cotacao_id:COT.cur.cotacao.id,anexo_ids:ids})})).json();
    if(rr.error){ toast(rr.error); s.busy=false; cotPropIARender(); return; }
    document.removeEventListener('paste',cotPropIAPaste); COT.propIA=null;
    cotPropIAForn((rr.draft&&rr.draft.fornecedor)||rr.fornecedor||{}, rr.draft||{}, ids, rr);   // o fornecedor vem DENTRO do draft
  }catch(e){ toast('Falha: '+e.message); s.busy=false; cotPropIARender(); } }
const _dig=s=>String(s||'').replace(/\D/g,'');
async function cotPropIAForn(fornData, draft, anexoIds, meta){
  const cnpj=_dig(fornData.cnpj), nome=(fornData.nome||'').trim(); const cands=[], seen={};
  const push=arr=>(arr||[]).forEach(f=>{ if(!seen[f.id]){ seen[f.id]=1; cands.push(f); } });
  try{ if(cnpj.length>=8){ const r=await (await fetch('actions/fornecedores.php?q='+encodeURIComponent(cnpj)+'&limit=8')).json(); push((r.fornecedores||[]).filter(f=>_dig(f.cnpj)===cnpj)); } }catch(e){}
  try{ if(nome){ const r=await (await fetch('actions/fornecedores.php?q='+encodeURIComponent(nome)+'&limit=8')).json(); push(r.fornecedores||[]); } }catch(e){}
  COT.propIAres={fornData,draft,anexoIds,meta,cands,sel:undefined}; cotPropIAResRender();
}
function cotPropIAResRender(){ const R=COT.propIAres; if(!R)return; let ov=document.getElementById('propiaOverlay');
  if(!ov){ ov=document.createElement('div'); ov.id='propiaOverlay'; ov.style.cssText='position:fixed;inset:0;background:rgba(15,25,20,.42);z-index:9999;display:flex;align-items:center;justify-content:center;padding:20px'; document.body.appendChild(ov); }
  ov.onclick=()=>ov.remove();
  const fd=R.fornData, cnpjD=_dig(fd.cnpj), exact=R.cands.find(c=>_dig(c.cnpj)===cnpjD && cnpjD.length>=8);
  if(R.sel===undefined) R.sel = exact?('id:'+exact.id):(R.cands.length?('id:'+R.cands[0].id):'novo');
  ov.innerHTML=`<div style="background:#fff;border-radius:14px;padding:18px;box-shadow:0 12px 44px rgba(0,0,0,.22);width:100%;max-width:520px;max-height:85vh;overflow:auto" onclick="event.stopPropagation()">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:4px"><b style="font-size:14px">Qual é o fornecedor desta proposta?</b><span onclick="document.getElementById('propiaOverlay').remove()" class="material-icons" style="cursor:pointer;color:var(--muted)">close</span></div>
    <div class="muted" style="font-size:11.5px;margin-bottom:10px">A IA leu: <b>${esc(fd.nome||'—')}</b>${fd.cnpj?' · CNPJ '+esc(fd.cnpj):''}${fd.telefone?' · '+esc(fd.telefone):''}${fd.email?' · '+esc(fd.email):''}</div>
    ${R.cands.length?`<div style="font-size:11px;font-weight:700;color:var(--muted);margin-bottom:4px">FORNECEDORES PARECIDOS NA BASE</div>${R.cands.map(c=>`<label style="display:flex;align-items:center;gap:8px;padding:5px 2px;font-size:12.5px;cursor:pointer"><input type="radio" name="piaf" ${R.sel==='id:'+c.id?'checked':''} onchange="COT.propIAres.sel='id:${c.id}';cotPropIAResRender()"><span style="flex:1">${esc(c.nome)}${c.cnpj?` <span class="muted">· ${esc(c.cnpj)}</span>`:''}${(_dig(c.cnpj)===cnpjD&&cnpjD.length>=8)?' <span class="dchip" style="background:var(--ok)">CNPJ igual</span>':''}</span></label>`).join('')}`:'<div class="dmini">Nenhum fornecedor parecido na base.</div>'}
    <label style="display:flex;align-items:center;gap:8px;padding:6px 2px;font-size:12.5px;cursor:pointer;border-top:1px solid var(--line);margin-top:6px"><input type="radio" name="piaf" ${R.sel==='novo'?'checked':''} onchange="COT.propIAres.sel='novo';cotPropIAResRender()"><b style="color:var(--verde-d)">➕ Cadastrar novo fornecedor</b></label>
    ${R.sel==='novo'?`<div style="background:#fafbfb;border:1px solid var(--line);border-radius:10px;padding:10px;margin-top:6px;display:grid;grid-template-columns:1fr 1fr;gap:8px">
      ${cotFld('Nome / razão social *','<input id="piaN" value="'+esc(fd.nome||'')+'" style="width:100%">','grid-column:1/3')}
      ${cotFld('CNPJ','<input id="piaC" value="'+esc(fd.cnpj||'')+'" style="width:100%">')}
      ${cotFld('Categoria','<input id="piaCat" value="'+esc((COT.cur.cotacao||{}).categoria||'')+'" style="width:100%">')}
      ${cotFld('Telefone','<input id="piaT" value="'+esc(fd.telefone||'')+'" style="width:100%">')}
      ${cotFld('E-mail','<input id="piaE" value="'+esc(fd.email||'')+'" style="width:100%">')}</div>`:''}
    <div style="display:flex;gap:8px;justify-content:flex-end;margin-top:12px"><button class="btn-ghost" onclick="document.getElementById('propiaOverlay').remove()">Cancelar</button><button class="btn-prim" onclick="cotPropIAResConfirm()"><span class="material-icons" style="font-size:15px;vertical-align:-3px">check</span> Continuar</button></div>
  </div>`; }
async function cotPropIAResConfirm(){ const R=COT.propIAres; if(!R)return; let fid=null, fnome='';
  if(R.sel==='novo'){ const g=id=>((document.getElementById(id)||{}).value||'').trim(); fnome=g('piaN'); if(!fnome){toast('Informe o nome do fornecedor');return;}
    try{ const r=await (await fetch('actions/fornecedores.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({acao:'fornecedor_salvar',me:EU&&EU.bitrix_id,nome:fnome,cnpj:g('piaC'),categoria:g('piaCat'),telefone:g('piaT'),email:g('piaE')})})).json();
      if(r.error){toast(r.error);return;} fid=r.id; toast(r.dedup?'Fornecedor já existia — reaproveitado (sem duplicar)':'Fornecedor cadastrado'); }catch(e){toast('Falha ao cadastrar: '+e.message);return;}
  } else { const id=Number(String(R.sel).split(':')[1]); const c=R.cands.find(x=>x.id===id); if(!c){toast('Selecione o fornecedor');return;} fid=c.id; fnome=c.nome; }
  try{
    const jaConv=(COT.cur.convidados||[]).some(cv=>String(cv.fornecedor_id)===String(fid)||(cv.fornecedor_nome||'').trim().toLowerCase()===fnome.trim().toLowerCase());
    if(!jaConv) await fetch('actions/cotacoes.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({acao:'convidar',me:EU&&EU.bitrix_id,cotacao_id:COT.cur.cotacao.id,convidados:[{id:fid,nome:fnome}]})});
    if(R.anexoIds&&R.anexoIds.length) await fetch('actions/cotacao_anexo.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({acao:'set_fornecedor',me:EU&&EU.bitrix_id,cotacao_id:COT.cur.cotacao.id,ids:R.anexoIds,fornecedor_id:fid,fornecedor_nome:fnome})});
    const draft=R.draft, meta=R.meta; cotPropIAFechar();
    await cotOpen(COT.cur.cotacao.id);
    cotIAAplicar(fnome, draft, meta);
  }catch(e){ toast('Falha: '+e.message); }
}
async function cotDelAnexo(id){ if(!confirm('Excluir este anexo?'))return;
  try{ await fetch('actions/cotacao_anexo.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({acao:'excluir',me:EU&&EU.bitrix_id,id})}); cotOpen(COT.cur.cotacao.id); }catch(e){toast('Falha');} }
/* --- Concorrência: convidar / desconvidar / lançar proposta de um convidado --- */
function cotPropostaDe(ci){ const cf=((COT.cur||{}).convidados||[])[ci]; if(!cf)return; cotProposta(0); COT.prop.fornecedor_nome=cf.fornecedor_nome; cotRenderProposta(); }
/* REMOVER DA CONCORRÊNCIA leva as propostas do fornecedor junto — o mapa desenha a partir das
   PROPOSTAS, então tirar só o card deixava o fornecedor no comparativo, agora sem card e sem como
   excluir por lá. Como apagar proposta é destrutivo, o servidor não apaga de primeira: devolve
   quantas e quanto está em jogo, e só então perguntamos, exigindo motivo (vai para o Histórico). */
async function cotDesconvidar(id,nome,motivo,comPropostas){
  const body={acao:'desconvidar',me:EU&&EU.bitrix_id,id};
  if(comPropostas){ body.com_propostas=1; body.motivo=motivo||''; }
  else if(!confirm('Tirar '+(nome?('"'+nome+'"'):'este fornecedor')+' da concorrência desta cotação?')) return;
  try{
    const r=await (await fetch('actions/cotacoes.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(body)})).json();
    if(r&&r.precisa_confirmar){ cotDesconvidarConfirmar(id,r); return; }
    if(r&&r.error){toast(r.error);return;}
    try{ closeModal(true); }catch(e){}
    toast('Fornecedor removido'+(r.propostas_apagadas?(' — '+r.propostas_apagadas+' proposta(s) saíram do mapa'):''));
    await cotOpen(COT.cur.cotacao.id);
  }catch(e){toast('Falha: '+e.message);} }
function cotDesconvidarConfirmar(id,info){
  dlgAbrir('Cotações','Remover da concorrência',
    '<div style="max-width:520px">'
   + '<div style="border-left:4px solid var(--pend);background:#fdf1ef;padding:10px 13px;border-radius:0 8px 8px 0;font-size:12.5px;margin-bottom:12px">'
   + '<b>'+esc(info.fornecedor||'Este fornecedor')+'</b> tem <b>'+info.propostas+' proposta(s)</b> lançada(s)'
   + (info.total?(', somando <b>'+BRL(info.total)+'</b>'):'')+'. Remover da concorrência <b>apaga essas propostas</b> '
   + 'e elas somem do mapa comparativo. Não tem como desfazer.</div>'
   + '<div class="dmini" style="margin-bottom:8px">Se você só quer corrigir o preço, feche isto e use '
   + '<b>editar</b> na proposta; para trocar o valor mantendo o histórico, use <b>nova revisão</b>.</div>'
   + cotFld('Motivo (fica no histórico da cotação)','<input id="cotDescMot" placeholder="ex.: cadastrei errado, vou refazer" style="width:100%">')
   + '<div class="bar" style="justify-content:flex-end;gap:8px;margin-top:14px">'
   + '<button class="btn-ghost" onclick="closeModal(true)">Cancelar</button>'
   + '<button class="btn-prim" style="background:var(--pend)" onclick="cotDesconvidarOk('+id+')">Remover e apagar as propostas</button>'
   + '</div></div>');
}
function cotDesconvidarOk(id){
  const m=((document.getElementById('cotDescMot')||{}).value||'').trim();
  if(!m){ toast('Escreva o motivo'); return; }
  cotDesconvidar(id,'',m,true);
}
let _cotCB;
function cotConvBuscaInput(){ clearTimeout(_cotCB); const q=(document.getElementById('cotConvBusca').value||'').trim(), box=document.getElementById('cotConvSug'); if(!box)return; if(q.length<2){box.style.display='none';box.innerHTML='';return;}
  _cotCB=setTimeout(async()=>{ try{ const d=await (await fetch('actions/fornecedores.php?q='+encodeURIComponent(q)+'&limit=14')).json(); COT.convBusca=d.fornecedores||[];
    box.innerHTML=COT.convBusca.length?COT.convBusca.map((f,i)=>`<div onclick="cotConvidar(${i})" style="padding:7px 10px;cursor:pointer;font-size:12.5px;border-bottom:1px solid #f1f3f2" onmouseover="this.style.background='#eff7f1'" onmouseout="this.style.background=''"><b>${esc(f.nome)}</b>${fornSeloMini(f)} <span class="muted" style="font-size:10.5px">· ${esc(f.categoria||'')}${f.cidade?' · '+esc(f.cidade):''}</span></div>`).join(''):'<div class="dmini" style="padding:8px">nenhum fornecedor casa "'+esc(q)+'"</div>'; box.style.display='block';
  }catch(e){} },300); }
async function cotConvidar(idx){ const f=(COT.convBusca||[])[idx]; if(!f)return;
  try{ const r=await (await fetch('actions/cotacoes.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({acao:'convidar',me:EU&&EU.bitrix_id,cotacao_id:COT.cur.cotacao.id,convidados:[{id:f.id,nome:f.nome,categoria:f.categoria,contato:f.contato,email:f.email,telefone:f.telefone}]})})).json();
    if(r.error){toast(r.error);return;} cotOpen(COT.cur.cotacao.id);
  }catch(e){toast('Falha: '+e.message);} }
document.addEventListener('click',e=>{ if(!(e.target.closest&&e.target.closest('#cotConvBusca,#cotConvSug'))){ const b=document.getElementById('cotConvSug'); if(b) b.style.display='none'; } });

/* ---------- Modelos de Carta Convite (sub-aba) + geração ---------- */
const CART={modelos:[],config:null,servicos:[],mode:'list',cur:null,gen:null};
async function cartaLoad(){
  const w=document.getElementById('cotwrap'); w.innerHTML='<div class="dempty">Carregando modelos de carta…</div>';
  try{ const j=await (await fetch('actions/cartas.php?me='+encodeURIComponent((EU&&EU.bitrix_id)||''))).json();
    CART.modelos=j.modelos||[]; CART.config=j.config||null; CART.servicos=j.servicos||[]; CART.mode='list'; cartaRender();
  }catch(e){ w.innerHTML='<div class="empty">Falha ao carregar modelos.</div>'; }
}
function cartaServNome(id){ if(!id)return ''; const s=CART.servicos.find(x=>String(x.id)===String(id)); return s?s.nome:('#'+id); }
function cartaRender(){
  if(CART.mode==='edit') return cartaRenderEdit();
  if(CART.mode==='config') return cartaRenderConfig();
  const w=document.getElementById('cotwrap');
  const rows=CART.modelos.map(m=>`<tr style="cursor:pointer" onclick="cartaEditar(${m.id})">
    <td><b>${esc(m.servico_nome||'')}</b></td><td class="muted" style="font-size:12px">${esc(m.tipo||'')}</td>
    <td>${m.servico_id?`<span class="dchip" style="background:#eef4f0;color:var(--verde-d)">${esc(cartaServNome(m.servico_id))}</span>`:'<span class="dchip" style="background:var(--pend)">atribuir serviço</span>'}</td>
    <td class="muted" style="font-size:11px">${esc(m.pes_ref||'—')}</td>
    <td style="text-align:center">${Number(m.tem_medicao)?'<span title="tem critérios de medição" style="color:var(--ok);font-weight:700">✓</span>':'<span style="color:var(--pend)">—</span>'}</td>
    <td style="text-align:right">${m.origem==='seed'?'<span class="dchip" style="background:#8a9299;font-size:10px">seed IA</span>':'<span class="dchip" style="background:var(--ok);font-size:10px">curado</span>'} <span class="material-icons" style="color:var(--muted);vertical-align:-4px">chevron_right</span></td></tr>`).join('');
  w.innerHTML=`<div class="panel" style="margin-bottom:10px"><div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px">
      <div><b style="font-size:14px">Modelos de carta convite</b> <span class="muted" style="font-size:11.5px">— o conteúdo padrão por serviço (escopo, critérios de medição, equalização). Editar aqui vale p/ toda cotação do serviço.</span></div>
      ${IS_ADMIN?`<button class="btn-ghost" style="padding:6px 12px" onclick="cartaConfigAbrir()"><span class="material-icons" style="font-size:15px;vertical-align:-3px">gavel</span> Bloco padrão Caprem</button>`:''}
    </div></div>
    <div class="wrap"><table><thead><tr><th>Serviço (modelo)</th><th>Tipo</th><th>Serviço vinculado</th><th>PES</th><th style="text-align:center">Medição</th><th></th></tr></thead>
    <tbody>${rows||'<tr><td colspan="6" class="empty">Nenhum modelo cadastrado.</td></tr>'}</tbody></table></div>`;
}
async function cartaEditar(id){
  try{ const j=await (await fetch('actions/cartas.php?modelo='+id+'&me='+encodeURIComponent((EU&&EU.bitrix_id)||''))).json();
    if(!j.modelo){toast('Modelo não encontrado');return;} CART.cur=j.modelo; CART.mode='edit'; cartaRender();
  }catch(e){toast('Falha');}
}
function cartaRenderEdit(){
  const m=CART.cur, w=document.getElementById('cotwrap'), L=a=>(a||[]).join('\n');
  const servOpts='<option value="">— nenhum (atribuir) —</option>'+CART.servicos.map(s=>`<option value="${s.id}" ${String(s.id)===String(m.servico_id)?'selected':''}>${esc(s.nome)}</option>`).join('');
  w.innerHTML=`<div class="panel">
    <div style="display:flex;align-items:center;gap:8px;margin-bottom:12px;flex-wrap:wrap"><button class="btn-ghost" onclick="CART.mode='list';cartaRender()"><span class="material-icons" style="font-size:16px;vertical-align:-3px">arrow_back</span> Voltar</button>
      <b style="font-size:15px">Modelo · ${esc(m.servico_nome||'')}</b> ${m.origem==='seed'?'<span class="dchip" style="background:#8a9299;font-size:10px">seed IA — confira e cure</span>':''}</div>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px">
      ${cotFld('Nome do serviço (modelo)','<input id="cm_nome" value="'+esc(m.servico_nome||'')+'">')}
      ${cotFld('Serviço vinculado <span class="muted" style="font-weight:400">(usado na geração da carta)</span>','<select id="cm_serv">'+servOpts+'</select>')}
      ${cotFld('Tipo','<input id="cm_tipo" value="'+esc(m.tipo||'')+'" placeholder="Mão de obra / Empreitada / Material + MO…">')}
      ${cotFld('PES (procedimento de inspeção)','<input id="cm_pes" value="'+esc(m.pes_ref||'')+'">')}
    </div>
    ${cotFld('Objeto (1-2 frases)','<textarea id="cm_obj" rows="2" style="width:100%">'+esc(m.objeto||'')+'</textarea>','margin-top:8px')}
    ${cotFld('Normas de referência (uma por linha)','<textarea id="cm_norma" rows="2" style="width:100%">'+esc(L(m.norma_referencia))+'</textarea>','margin-top:8px')}
    ${cotFld('Escopo — incluso / da obra (um por linha)','<textarea id="cm_escopo" rows="7" style="width:100%">'+esc(L(m.escopo))+'</textarea>','margin-top:8px')}
    ${cotFld('Critérios de medição (um por linha) — o coração','<textarea id="cm_med" rows="7" style="width:100%">'+esc(L(m.criterios_medicao))+'</textarea>','margin-top:8px')}
    ${cotFld('Campos de equalização — o que a proposta declara (um por linha)','<textarea id="cm_eq" rows="5" style="width:100%">'+esc(L(m.equalizacao_campos))+'</textarea>','margin-top:8px')}
    ${cotFld('Quantitativos-modelo (item | unidade, um por linha)','<textarea id="cm_quant" rows="4" style="width:100%">'+esc((m.quantitativos_modelo||[]).map(q=>(q.item||'')+' | '+(q.unidade||'')).join('\n'))+'</textarea>','margin-top:8px')}
    ${cotFld('Observações','<textarea id="cm_obs" rows="2" style="width:100%">'+esc(m.observacoes||'')+'</textarea>','margin-top:8px')}
    <div style="margin-top:12px"><button class="btn-prim" onclick="cartaSalvar()"><span class="material-icons" style="font-size:16px;vertical-align:-3px">check</span> Salvar modelo</button></div>
  </div>`;
}
async function cartaSalvar(){
  const g=id=>((document.getElementById(id)||{}).value||''), lines=id=>g(id).split(/\r?\n/).map(s=>s.trim()).filter(Boolean);
  const quant=g('cm_quant').split(/\r?\n/).map(l=>{const p=l.split('|');return (p[0]||'').trim()?{item:p[0].trim(),unidade:(p[1]||'').trim()}:null;}).filter(Boolean);
  const modelo={id:CART.cur.id,servico_nome:g('cm_nome'),servico_id:Number(g('cm_serv'))||null,tipo:g('cm_tipo'),pes_ref:g('cm_pes'),objeto:g('cm_obj'),norma_referencia:lines('cm_norma'),escopo:lines('cm_escopo'),criterios_medicao:lines('cm_med'),equalizacao_campos:lines('cm_eq'),quantitativos_modelo:quant,observacoes:g('cm_obs')};
  try{ const r=await (await fetch('actions/cartas.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({acao:'save_modelo',me:EU&&EU.bitrix_id,modelo})})).json();
    if(r.error){toast(r.error);return;} toast('Modelo salvo'); cartaLoad(); }catch(e){toast('Falha: '+e.message);}
}
function cartaConfigAbrir(){ CART.mode='config'; cartaRender(); }
function cartaRenderConfig(){
  const c=CART.config||{}, s=c.seguranca||{}, w=document.getElementById('cotwrap'), L=a=>(a||[]).join('\n');
  w.innerHTML=`<div class="panel">
    <div style="display:flex;align-items:center;gap:8px;margin-bottom:6px"><button class="btn-ghost" onclick="CART.mode='list';cartaRender()"><span class="material-icons" style="font-size:16px;vertical-align:-3px">arrow_back</span> Voltar</button><b style="font-size:15px">Bloco padrão Caprem</b></div>
    <div class="dmini" style="margin-bottom:10px">O texto FIXO que entra em toda carta (obrigações, SST, julgamento, faturamento, contatos). Editar aqui vale para todas.</div>
    ${cotFld('Obrigações da contratada (uma por linha)','<textarea id="cc_obr" rows="6" style="width:100%">'+esc(L(c.obrigacoes))+'</textarea>')}
    <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:10px;margin-top:8px">
      ${cotFld('SST · a cada medição','<textarea id="cc_s1" rows="4" style="width:100%">'+esc(s.a_cada_medicao||'')+'</textarea>')}
      ${cotFld('SST · da empresa','<textarea id="cc_s2" rows="4" style="width:100%">'+esc(s.da_empresa||'')+'</textarea>')}
      ${cotFld('SST · dos empregados','<textarea id="cc_s3" rows="4" style="width:100%">'+esc(s.dos_empregados||'')+'</textarea>')}
    </div>
    ${cotFld('SST · nota (EPI / atraso de pagamento)','<textarea id="cc_snota" rows="2" style="width:100%">'+esc(s.nota||'')+'</textarea>','margin-top:8px')}
    ${cotFld('Julgamento das propostas (uma por linha)','<textarea id="cc_julg" rows="3" style="width:100%">'+esc(L(c.julgamento))+'</textarea>','margin-top:8px')}
    ${cotFld('Faturamento e pagamento','<textarea id="cc_fat" rows="3" style="width:100%">'+esc(c.faturamento||'')+'</textarea>','margin-top:8px')}
    <div class="dmini" style="margin-top:8px">O <b>Responsável do Departamento de Suprimentos</b> na carta é sempre o usuário que criou a cotação — não precisa configurar aqui.</div>
    ${cotFld('Declaração final','<textarea id="cc_decl" rows="2" style="width:100%">'+esc(c.declaracao||'')+'</textarea>','margin-top:8px')}
    ${cotFld('Validade da proposta (dias)','<input id="cc_val" type="number" style="width:120px" value="'+esc(c.validade_dias||30)+'">','margin-top:8px')}
    <div style="margin-top:12px"><button class="btn-prim" onclick="cartaConfigSalvar()">Salvar bloco Caprem</button></div>
  </div>`;
}
async function cartaConfigSalvar(){
  const g=id=>((document.getElementById(id)||{}).value||''), lines=id=>g(id).split(/\r?\n/).map(s=>s.trim()).filter(Boolean);
  const config={obrigacoes:lines('cc_obr'),seguranca:{a_cada_medicao:g('cc_s1'),da_empresa:g('cc_s2'),dos_empregados:g('cc_s3'),nota:g('cc_snota')},julgamento:lines('cc_julg'),faturamento:g('cc_fat'),contatos:{gestor_suprimentos:''},declaracao:g('cc_decl'),validade_dias:Number(g('cc_val'))||30};
  try{ const r=await (await fetch('actions/cartas.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({acao:'save_config',me:EU&&EU.bitrix_id,config})})).json();
    if(r.error){toast(r.error);return;} toast('Bloco Caprem salvo'); cartaLoad(); }catch(e){toast('Falha');}
}
/* --- GERAÇÃO da carta a partir de uma cotação --- */
async function cartaGerar(cid){
  const w=document.getElementById('cotwrap'); w.innerHTML='<div class="dempty">Montando a carta…</div>';
  const enc=encodeURIComponent((EU&&EU.bitrix_id)||'');
  try{ const g=await (await fetch('actions/cartas.php?gerar='+cid+'&me='+enc)).json();
    if(g.error){w.innerHTML='<div class="empty">'+esc(g.error)+'</div>';return;} CART.gen=g; CART.savedHTML=null;
    // já existe carta salva nesta cotação? carrega a ÚLTIMA pra continuar editando (não refaz do zero)
    const gl=await (await fetch('actions/cartas.php?geradas='+cid+'&me='+enc)).json();
    if(gl.geradas&&gl.geradas.length){ const one=await (await fetch('actions/cartas.php?gerada='+gl.geradas[0].id+'&me='+enc)).json(); if(one.gerada&&one.gerada.html) CART.savedHTML=one.gerada.html; }
    cartaRenderGerar();
  }catch(e){ w.innerHTML='<div class="empty">Falha ao gerar.</div>'; }
}
function cartaGerarZero(){ if(!confirm('Descartar a carta salva e gerar uma NOVA a partir do modelo? (as suas edições serão perdidas)'))return; CART.savedHTML=null; cartaRenderGerar(); }
function cvList(a){ return (a&&a.length)?'<ul>'+a.map(x=>'<li>'+esc(x)+'</li>').join('')+'</ul>':''; }
function cartaMontarHTML(g){
  if(g&&g.cotacao&&g.cotacao.tipo==='material') return cartaMontarHTMLMaterial(g);   // cotação nascida de solicitação → carta de COTAÇÃO (material)
  const c=g.cotacao||{}, m=g.modelo||null, cf=g.config||{}, s=cf.seguranca||{};
  const titulo=(c.servico_nome||(m&&m.servico_nome)||c.titulo||'').replace(/^(Execução de|Execução|MO)\s*/i,'').trim()||c.titulo||'Serviço';
  const norma=m&&m.norma_referencia&&m.norma_referencia.length?m.norma_referencia.join(' / '):'—';
  const eqCampos=[...new Set([...(m?m.equalizacao_campos:[]),...(g.equalizacao_cotacao||[])])];
  const quant=g.quantitativos||[];
  let h=`<div class="cvdoc" id="cvInner" contenteditable="true">
    <div class="cvmast"><div class="br">◤ Caprem Construtora · Engenharia &amp; Fundações</div>
      <div class="kick">Carta Convite</div><h2>${esc(titulo)}</h2></div>
    <div class="cvinfo">
      <div><div class="k">Obra</div><div>${esc(c.obra_nome||'—')}</div></div>
      <div><div class="k">Norma de referência</div><div>${esc(norma)}</div></div>
      <div><div class="k">Procedimento (PES)</div><div>${esc((m&&m.pes_ref)||'—')}</div></div>
    </div>
    <div class="cvbody">`;
  if(!m) h+=`<div class="cvnote cv-noprint">⚠️ Não há modelo vinculado a este serviço — a carta saiu só com os blocos padrão + quantitativos. Atribua um modelo na aba <b>Modelos de carta</b>.</div>`;
  // 00 Apresentação
  h+=`<div class="cvsec"><div class="cvsh"><span class="cvsn">00</span><span class="cvst">Apresentação</span></div>
    <p>${esc((m&&m.objeto)||c.descricao||'')||'<span class="cvph">[objeto — descreva o serviço]</span>'}</p></div>`;
  // 01 Especificação
  h+=`<div class="cvsec"><div class="cvsh"><span class="cvsn">01</span><span class="cvst">Especificação e critérios de medição</span></div>
    ${m&&m.escopo.length?'<p><b>Escopo</b></p>'+cvList(m.escopo):''}
    ${m&&m.criterios_medicao.length?'<p><b>Critérios de medição</b></p>'+cvList(m.criterios_medicao):'<p class="cvph">[defina o escopo e os critérios de medição no modelo do serviço]</p>'}</div>`;
  // 02 Quantitativos
  h+=`<div class="cvsec"><div class="cvsh"><span class="cvsn">02</span><span class="cvst">Quantitativos</span></div>
    <table><thead><tr><th>Item</th><th style="width:70px">Unid.</th><th style="width:110px;text-align:right">Qtde</th></tr></thead><tbody>
    ${quant.length?quant.map(q=>`<tr><td>${esc(q.item||'')}</td><td>${esc(q.unidade||'')}</td><td style="text-align:right">${q.qtde!=null&&q.qtde!==''?esc(cotNum(q.qtde)):'—'}</td></tr>`).join(''):'<tr><td colspan="3" class="cvph">[itens da cotação]</td></tr>'}
    </tbody></table></div>`;
  // 03 Planilha de preços / equalização
  h+=`<div class="cvsec"><div class="cvsh"><span class="cvsn">03</span><span class="cvst">Planilha de preços e equalização</span></div>
    <p>A proponente deve cotar e declarar expressamente:</p>
    <table><thead><tr><th>Campo a declarar / cotar</th><th style="width:150px">Resposta da proponente</th></tr></thead><tbody>
    ${(eqCampos.length?eqCampos:['Preço unitário','Prazo de execução','Validade da proposta']).map(x=>`<tr><td>${esc(x)}</td><td style="color:#9aa">____________________</td></tr>`).join('')}
    </tbody></table></div>`;
  // 04 Obrigações
  h+=`<div class="cvsec"><div class="cvsh"><span class="cvsn">04</span><span class="cvst">Obrigações da contratada</span></div>${cvList(cf.obrigacoes)}</div>`;
  // 05 Segurança
  h+=`<div class="cvsec"><div class="cvsh"><span class="cvsn">05</span><span class="cvst">Segurança e documentação</span></div>
    <div class="cvgrid3"><div class="cvcard"><h5>A cada medição</h5><p>${esc(s.a_cada_medicao||'')}</p></div>
      <div class="cvcard"><h5>Da empresa</h5><p>${esc(s.da_empresa||'')}</p></div>
      <div class="cvcard"><h5>Dos empregados</h5><p>${esc(s.dos_empregados||'')}</p></div></div>
    ${s.nota?`<div class="cvnote">${esc(s.nota)}</div>`:''}</div>`;
  // 06 Julgamento
  h+=`<div class="cvsec"><div class="cvsh"><span class="cvsn">06</span><span class="cvst">Julgamento das propostas</span></div>${cvList(cf.julgamento)}</div>`;
  // 07 Faturamento
  h+=`<div class="cvsec"><div class="cvsh"><span class="cvsn">07</span><span class="cvst">Faturamento e pagamento</span></div><p>${esc(cf.faturamento||'')}</p></div>`;
  // 08 Contatos
  h+=`<div class="cvsec"><div class="cvsh"><span class="cvsn">08</span><span class="cvst">Esclarecimentos e contatos</span></div>
    <p><b>Responsável do Departamento de Suprimentos:</b> ${esc(c.criado_nome||'')}</p>
    <p><b>Validade da proposta:</b> mínimo ${esc(cf.validade_dias||30)} dias · <b>Distribuição:</b> <span class="cvph">__/__/____</span> · <b>Retorno até:</b> <span class="cvph">__/__/____</span></p>
    ${cf.declaracao?`<p style="font-style:italic;border-left:2px solid #cbb26a;padding-left:10px;color:#455">"${esc(cf.declaracao)}"</p>`:''}</div>`;
  h+=`</div></div>`;
  return h;
}
/* Carta de COTAÇÃO (material) — nasce de uma solicitação de compra. Mesmo visual da convite, mas sem cláusula de contrato:
   pede preço + condições comerciais, traz dados/CNPJ da obra e comprador responsável. */
function cartaMontarHTMLMaterial(g){
  const c=g.cotacao||{}, cf=g.config||{}, quant=g.quantitativos||[];
  const obra=c.obra_nome||'—', cnpj=c.obra_cnpj||'', endereco=c.obra_endereco||'', comp=c.comprador_resp||c.criado_nome||'', sc=c.num_solicitacao||'', validade=cf.validade_dias||30;
  const phCnpj='<span class="cvph">[preencha o CNPJ em Solicitações › Obras &amp; compradores]</span>';
  let h=`<div class="cvdoc" id="cvInner" contenteditable="true">
    <div class="cvmast"><div class="br">◤ Caprem Construtora · Engenharia &amp; Fundações</div>
      <div class="kick">Solicitação de Cotação</div><h2>${esc(obra)}</h2></div>
    <div class="cvinfo">
      <div><div class="k">Obra</div><div>${esc(obra)}</div></div>
      <div><div class="k">CNPJ da obra</div><div>${cnpj?esc(cnpj):phCnpj}</div></div>
      <div><div class="k">Nº da solicitação</div><div>${sc?esc(sc):'—'}</div></div>
    </div>
    <div class="cvbody">`;
  h+=`<div class="cvsec"><div class="cvsh"><span class="cvsn">00</span><span class="cvst">Apresentação</span></div>
    <p>Prezado fornecedor, a <b>Caprem Construtora</b> solicita a cotação dos materiais relacionados abaixo, destinados à obra <b>${esc(obra)}</b>. Pedimos a gentileza de preencher os preços e as condições comerciais e retornar esta cotação ao comprador responsável.</p></div>`;
  h+=`<div class="cvsec"><div class="cvsh"><span class="cvsn">01</span><span class="cvst">Itens para cotação</span></div>
    <table><thead><tr><th style="width:32px">#</th><th>Material / especificação</th><th style="width:54px">Unid.</th><th style="width:78px;text-align:right">Qtde</th><th style="width:108px;text-align:right">Preço unit. (R$)</th><th style="width:108px;text-align:right">Preço total (R$)</th></tr></thead><tbody>
    ${quant.length?quant.map((q,i)=>`<tr><td style="text-align:center">${i+1}</td><td>${esc(q.item||'')}${q.obs?`<div style="font-size:10px;color:#667;margin-top:2px">${esc(q.obs)}</div>`:''}</td><td>${esc(q.unidade||'')}</td><td style="text-align:right">${q.qtde!=null&&q.qtde!==''?esc(cotNum(q.qtde)):'—'}</td><td style="text-align:right;color:#9aa">__________</td><td style="text-align:right;color:#9aa">__________</td></tr>`).join(''):'<tr><td colspan="6" class="cvph">[itens da cotação]</td></tr>'}
    </tbody></table></div>`;
  const cond=['Prazo de entrega','Condição de pagamento','Validade da proposta','Frete (incluso / CIF / FOB)','Prazo de faturamento'];
  h+=`<div class="cvsec"><div class="cvsh"><span class="cvsn">02</span><span class="cvst">Condições comerciais (a informar pelo fornecedor)</span></div>
    <table><thead><tr><th>Condição</th><th style="width:200px">Resposta do fornecedor</th></tr></thead><tbody>
    ${cond.map(x=>`<tr><td>${esc(x)}</td><td style="color:#9aa">____________________</td></tr>`).join('')}
    </tbody></table></div>`;
  h+=`<div class="cvsec"><div class="cvsh"><span class="cvsn">03</span><span class="cvst">Dados para faturamento e entrega</span></div>
    <div class="cvgrid3"><div class="cvcard"><h5>Obra</h5><p>${esc(obra)}</p></div>
      <div class="cvcard"><h5>CNPJ da obra</h5><p>${cnpj?esc(cnpj):phCnpj}</p></div>
      <div class="cvcard"><h5>Nº da solicitação</h5><p>${sc?esc(sc):'—'}</p></div></div>
    <p style="margin-top:8px"><b>Endereço da obra:</b> ${endereco?esc(endereco):'<span class="cvph">[preencha o endereço em Solicitações › Obras &amp; compradores]</span>'}</p></div>`;
  h+=`<div class="cvsec"><div class="cvsh"><span class="cvsn">04</span><span class="cvst">Contato e retorno</span></div>
    <p><b>Comprador responsável:</b> ${comp?esc(comp):'<span class="cvph">[comprador]</span>'}</p>
    <p><b>Validade mínima da proposta:</b> ${esc(validade)} dias · <b>Retorno até:</b> <span class="cvph">__/__/____</span></p>
    ${cf.declaracao?`<p style="font-style:italic;border-left:2px solid #cbb26a;padding-left:10px;color:#455">"${esc(cf.declaracao)}"</p>`:''}</div>`;
  h+=`</div></div>`;
  return h;
}
function cartaRenderGerar(){
  const w=document.getElementById('cotwrap'), g=CART.gen, cid=g.cotacao.id;
  let bodyHTML;
  if(CART.savedHTML){ try{ const doc=new DOMParser().parseFromString(CART.savedHTML,'text/html'); const cv=doc.querySelector('#cvInner')||doc.querySelector('.cvdoc'); bodyHTML=cv?cv.outerHTML:cartaMontarHTML(g); }catch(e){ bodyHTML=cartaMontarHTML(g); } }
  else bodyHTML=cartaMontarHTML(g);
  w.innerHTML=`<div class="cv-noprint" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;margin-bottom:12px">
      <button class="btn-ghost" onclick="cotOpen(${cid})"><span class="material-icons" style="font-size:16px;vertical-align:-3px">arrow_back</span> Voltar ao mapa</button>
      <button class="btn-prim" style="padding:6px 13px" onclick="window.print()"><span class="material-icons" style="font-size:15px;vertical-align:-3px">print</span> Imprimir / PDF</button>
      <button class="btn-ghost" style="padding:6px 13px" onclick="cartaWord()"><span class="material-icons" style="font-size:15px;vertical-align:-3px">description</span> Baixar Word</button>
      <button class="btn-prim" style="padding:6px 13px" onclick="cartaAnexarGerada()"><span class="material-icons" style="font-size:15px;vertical-align:-3px">save</span> Salvar na cotação</button>
      ${CART.savedHTML?`<button class="btn-ghost" style="padding:6px 13px" onclick="cartaGerarZero()" title="descartar e refazer do modelo"><span class="material-icons" style="font-size:15px;vertical-align:-3px">refresh</span> Gerar do zero</button><span class="dchip" style="background:#eef4f0;color:var(--verde-d);font-size:10px">editando a carta salva</span>`:''}
      <span class="muted" style="font-size:11.5px">Edite o texto direto na carta (clique e digite) e clique <b>Salvar na cotação</b>.</span></div>
    <div id="cvGerada">${bodyHTML}</div>`;
  window.scrollTo(0,0);
}
function cartaExportHTML(){
  const inner=document.getElementById('cvInner'); if(!inner)return '';
  const css=[...document.styleSheets].map(ss=>{try{return [...ss.cssRules].filter(r=>/\.cv|cvdoc/.test(r.selectorText||'')).map(r=>r.cssText).join('')}catch(e){return''}}).join('');
  return `<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:w="urn:schemas-microsoft-com:office:word"><head><meta charset="utf-8"><style>${css}</style></head><body>${inner.outerHTML}</body></html>`;
}
function cartaWord(){
  const html=cartaExportHTML(); const blob=new Blob(['﻿'+html],{type:'application/msword'});
  const a=document.createElement('a'); a.href=URL.createObjectURL(blob);
  const isMat=CART.gen&&CART.gen.cotacao&&CART.gen.cotacao.tipo==='material';
  a.download=(isMat?'Carta_Cotacao_':'Carta_Convite_')+((CART.gen.cotacao.servico_nome||CART.gen.cotacao.titulo||(isMat?'cotacao':'servico')).replace(/[^\w]+/g,'_').slice(0,40))+'.doc';
  document.body.appendChild(a); a.click(); a.remove();
}
// jsPDF + html2canvas carregados SOB DEMANDA (CDN) — só quando gera o PDF da carta
let _pdfLibs=null;
function cotLoadPdfLibs(){ if(_pdfLibs)return _pdfLibs; _pdfLibs=new Promise((res,rej)=>{ let n=0; const done=()=>{ if(++n===2)res(true); };
  const a=document.createElement('script'); a.src='https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js'; a.onload=done; a.onerror=()=>rej(new Error('html2canvas não carregou'));
  const b=document.createElement('script'); b.src='https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js'; b.onload=done; b.onerror=()=>rej(new Error('jspdf não carregou'));
  document.head.appendChild(a); document.head.appendChild(b); }); return _pdfLibs; }
async function cotCartaPDFBlob(el){ await cotLoadPdfLibs();
  const canvas=await html2canvas(el,{scale:2,useCORS:true,backgroundColor:'#ffffff'});
  const jsPDF=(window.jspdf||{}).jsPDF; const pdf=new jsPDF('p','mm','a4');
  const pw=210, ph=297, iw=pw, ih=canvas.height*pw/canvas.width, img=canvas.toDataURL('image/jpeg',0.92);
  let left=ih, pos=0; pdf.addImage(img,'JPEG',0,pos,iw,ih); left-=ph;
  while(left>0){ pos=left-ih; pdf.addPage(); pdf.addImage(img,'JPEG',0,pos,iw,ih); left-=ph; }
  return pdf.output('blob'); }
async function cartaAnexarGerada(){
  const inner=document.getElementById('cvInner'); if(!inner)return;
  const html=cartaExportHTML();
  const isMat=CART.gen&&CART.gen.cotacao&&CART.gen.cotacao.tipo==='material', cid=CART.gen.cotacao.id;
  try{ const r=await (await fetch('actions/cartas.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({acao:'salvar_carta',me:EU&&EU.bitrix_id,cotacao_id:cid,servico_nome:CART.gen.cotacao.servico_nome||'',titulo:(isMat?'Carta de Cotação · ':'Carta Convite · ')+(CART.gen.cotacao.titulo||''),html})})).json();
    if(r.error){toast(r.error);return;} }catch(e){toast('Falha: '+e.message);return;}
  toast('Carta salva — gerando o PDF p/ anexo…');
  try{ const blob=await cotCartaPDFBlob(inner);
    const fd=new FormData(); fd.append('arquivo',new File([blob],'Carta de cotacao.pdf',{type:'application/pdf'})); fd.append('cotacao_id',cid); fd.append('fornecedor_nome','__CARTA__'); fd.append('me',(EU&&EU.bitrix_id)||'');
    const rr=await (await fetch('actions/cotacao_anexo.php',{method:'POST',body:fd})).json();
    toast(rr&&rr.id?'Carta salva + PDF pronto para o e-mail ✓':('Carta salva (o PDF do anexo falhou: '+((rr&&rr.error)||'?')+')'));
  }catch(e){ toast('Carta salva (não gerou o PDF: '+e.message+')'); }
}
/* ---------- Preços Tabelados (sub-aba) ---------- */
const PREC={tabelas:[],mode:'home',busca:'',grupos:[],cur:null,itens:[],insumos:[]};
function precMe(){ return encodeURIComponent((EU&&EU.bitrix_id)||''); }
async function precLoad(){
  const w=document.getElementById('cotwrap'); w.innerHTML='<div class="dempty">Carregando preços tabelados…</div>';
  try{ const j=await (await fetch('actions/precos.php?me='+precMe())).json(); PREC.tabelas=j.tabelas||[]; PREC.mode='home'; precRender(); }
  catch(e){ w.innerHTML='<div class="empty">Falha ao carregar.</div>'; }
}
function precVenc(o){ return o.vigente?'':'opacity:.45;text-decoration:line-through'; }
function precOfertaTbl(g){
  const ofs=g.ofertas.slice().sort((a,b)=>((a.preco==null?9e15:a.preco)-(b.preco==null?9e15:b.preco)));
  return `<table class="up-tbl" style="margin-top:6px"><thead><tr><th style="text-align:left">Fornecedor</th><th style="text-align:right">Preço</th><th>Unid.</th><th>Frete</th><th>Validade</th><th style="text-align:left">Observação</th></tr></thead><tbody>
    ${ofs.map((o,i)=>`<tr style="${precVenc(o)}"><td style="text-align:left">${i===0&&o.vigente?'🏆 ':''}${esc(o.fornecedor||'—')}${o.descricao_original&&o.descricao_original!==g.nome?`<div style="font-size:9px;color:#99a">"${esc(o.descricao_original)}"</div>`:''}</td>
      <td style="text-align:right;font-weight:700">${o.preco!=null?BRL(o.preco):'—'}</td><td>${esc(o.unidade||'')}</td>
      <td>${o.frete_incluso?'✓ incluso':'—'}</td><td style="white-space:nowrap">${o.validade_fim?D(o.validade_fim):'—'}</td>
      <td style="text-align:left;font-size:11px">${esc(o.obs||'')}</td></tr>`).join('')}</tbody></table>`;
}
function precRender(){
  if(PREC.mode==='nova') return precRenderNova();
  const w=document.getElementById('cotwrap');
  const tabs=PREC.tabelas.map(t=>`<tr><td><b>${esc(t.fornecedor_nome||'—')}</b>${t.titulo?` <span class="muted">· ${esc(t.titulo)}</span>`:''}</td>
    <td class="muted" style="font-size:12px">${t.validade_inicio?D(t.validade_inicio)+' – ':''}${t.validade_fim?D(t.validade_fim):'sem validade'}</td>
    <td style="text-align:center">${t.n_itens}</td><td>${Number(t.vigente)?'<span class="dchip" style="background:var(--ok);font-size:10px">vigente</span>':'<span class="dchip" style="background:var(--pend);font-size:10px">vencida</span>'}</td>
    <td class="muted" style="font-size:11px">${esc(t.observacao||'')}</td>
    <td style="text-align:right">${CAN_EDIT?`<button class="btn-ghost" style="padding:2px 8px" onclick="precEditar(${t.id})">Editar</button><button class="btn-ghost" style="padding:2px 8px;color:var(--pend)" onclick="precExcluir(${t.id})">×</button>`:''}</td></tr>`).join('');
  w.innerHTML=`<div class="panel" style="margin-bottom:10px">
      <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap"><b style="font-size:14px">Consultar preço tabelado</b>
        <div class="search" style="flex:1;min-width:240px"><span class="material-icons" style="color:var(--muted)">search</span><input id="precBusca" placeholder="Buscar insumo… (ex.: barra de aço, bloco cerâmico)" value="${esc(PREC.busca)}" oninput="precBuscarIn(this.value)"></div>
        ${CAN_EDIT?`<button class="btn-prim" style="padding:7px 13px" onclick="precNova()"><span class="material-icons" style="font-size:16px;vertical-align:-3px">add</span> Nova tabela</button>`:''}</div>
      <div id="precResult" style="margin-top:10px"></div></div>
    <div class="panel"><b style="font-size:13px">Tabelas cadastradas</b> <span class="muted" style="font-size:11.5px">— contratos/tabelas por fornecedor</span>
      <div class="wrap" style="margin-top:8px"><table><thead><tr><th>Fornecedor</th><th>Validade</th><th style="text-align:center">Itens</th><th>Situação</th><th>Observação</th><th></th></tr></thead>
      <tbody>${tabs||'<tr><td colspan="6" class="empty">Nenhuma tabela ainda. Clique em “Nova tabela”.</td></tr>'}</tbody></table></div></div>`;
  if(PREC.busca) precBuscar(PREC.busca);
}
let _precT;
function precBuscarIn(q){ PREC.busca=q; clearTimeout(_precT); _precT=setTimeout(()=>precBuscar(q),250); }
async function precBuscar(q){
  const box=document.getElementById('precResult'); if(!box)return;
  if(!q.trim()){ box.innerHTML='<div class="dmini">Digite um insumo para comparar os preços tabelados dos fornecedores.</div>'; return; }
  try{ const j=await (await fetch('actions/precos.php?buscar='+encodeURIComponent(q)+'&me='+precMe())).json();
    PREC.grupos=j.grupos||[];
    box.innerHTML=PREC.grupos.length? PREC.grupos.map(g=>`<div style="border:1px solid var(--line);border-radius:9px;padding:10px 12px;margin-bottom:8px">
        <div style="font-weight:700;color:var(--verde-d)">${esc(g.nome)} <span class="muted" style="font-weight:400;font-size:11px">${esc(g.unidade||'')} · ${g.ofertas.length} oferta(s)</span></div>${precOfertaTbl(g)}</div>`).join('')
      : `<div class="dmini">Nenhum preço tabelado casa "${esc(q)}".</div>`;
  }catch(e){ box.innerHTML='<div class="dmini">Falha na busca.</div>'; }
}
async function precNova(){ PREC.cur=null; PREC.itens=[{descricao_original:'',insumo_nome:'',unidade:'',preco:'',frete_incluso:0,observacao:''}]; await precCarregaInsumos(); PREC.mode='nova'; precRender(); }
async function precEditar(id){
  try{ const j=await (await fetch('actions/precos.php?tabela='+id+'&me='+precMe())).json();
    if(j.error){toast(j.error);return;} PREC.cur=j.tabela;
    PREC.itens=(j.itens||[]).map(it=>({id:it.id,descricao_original:it.descricao_original||'',insumo_nome:it.insumo_nome||'',unidade:it.unidade||'',preco:it.preco!=null?it.preco:'',frete_incluso:Number(it.frete_incluso)||0,observacao:it.observacao||''}));
    if(!PREC.itens.length) PREC.itens=[{descricao_original:'',insumo_nome:'',unidade:'',preco:'',frete_incluso:0,observacao:''}];
    await precCarregaInsumos(); PREC.mode='nova'; precRender();
  }catch(e){toast('Falha');}
}
async function precCarregaInsumos(){ try{ const j=await (await fetch('actions/precos.php?insumos=&me='+precMe())).json(); PREC.insumos=j.insumos||[]; }catch(e){ PREC.insumos=[]; } }
function precRenderNova(){
  const t=PREC.cur||{}, w=document.getElementById('cotwrap');
  const dl='<datalist id="precInsDL">'+PREC.insumos.map(i=>`<option value="${esc(i.nome)}">`).join('')+'</datalist>';
  w.innerHTML=`<div class="panel">
    <div style="display:flex;align-items:center;gap:8px;margin-bottom:12px"><button class="btn-ghost" onclick="PREC.mode='home';precRender()"><span class="material-icons" style="font-size:16px;vertical-align:-3px">arrow_back</span> Voltar</button><b style="font-size:15px">${t.id?'Editar':'Nova'} tabela de preços</b></div>
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:10px">
      ${cotFld('Fornecedor *','<input id="pt_forn" value="'+esc(t.fornecedor_nome||'')+'" placeholder="Nome do fornecedor">')}
      ${cotFld('Título/contrato','<input id="pt_tit" value="'+esc(t.titulo||'')+'" placeholder="Ex.: Tabela 2026 / Contrato XPTO">')}
      ${cotFld('Validade — início','<input id="pt_vi" type="date" value="'+esc(t.validade_inicio||'')+'">')}
      ${cotFld('Validade — fim','<input id="pt_vf" type="date" value="'+esc(t.validade_fim||'')+'">')}
    </div>
    ${cotFld('Observação (ex.: “atende obras da região de Americana”)','<input id="pt_obs" style="width:100%" value="'+esc(t.observacao||'')+'">','margin-top:8px')}
    <div class="dmini" style="margin-top:10px">📎 O PDF da tabela/contrato e a leitura por IA entram no próximo passo. Por ora, cadastre os itens abaixo.</div>
    <div style="margin-top:12px;display:flex;justify-content:space-between;align-items:center"><b style="font-size:13px">Itens</b>
      <span class="muted" style="font-size:11px">“Item canônico” agrupa a mesma coisa entre fornecedores (dedup)</span></div>
    <div id="pt_itens" style="margin-top:8px"></div>
    <button class="btn-ghost" style="margin-top:6px" onclick="precAddItem()"><span class="material-icons" style="font-size:15px;vertical-align:-3px">add</span> Adicionar item</button>
    <div style="margin-top:14px"><button class="btn-prim" onclick="precSalvar()"><span class="material-icons" style="font-size:16px;vertical-align:-3px">check</span> Salvar tabela</button></div>
    ${dl}</div>`;
  precRenderItens();
}
function precRenderItens(){
  const box=document.getElementById('pt_itens'); if(!box)return;
  box.innerHTML=PREC.itens.map((it,i)=>`<div style="display:grid;grid-template-columns:minmax(0,1.4fr) minmax(0,1.2fr) 70px 110px 62px 34px;gap:6px;align-items:center;margin-bottom:6px">
    <input placeholder="Descrição do fornecedor" value="${esc(it.descricao_original)}" oninput="PREC.itens[${i}].descricao_original=this.value" style="font-size:12px">
    <input list="precInsDL" placeholder="Item canônico (dedup)" value="${esc(it.insumo_nome)}" oninput="PREC.itens[${i}].insumo_nome=this.value" style="font-size:12px" title="agrupa a mesma coisa entre fornecedores">
    <input placeholder="unid" value="${esc(it.unidade)}" oninput="PREC.itens[${i}].unidade=this.value" style="font-size:12px">
    <input inputmode="decimal" placeholder="preço" value="${it.preco!==''&&it.preco!=null?esc(fmtMoney(it.preco)):''}" oninput="maskMoneyInput(this);PREC.itens[${i}].preco=parseBRLInput(this.value)" onblur="moneyBlur(this)" style="font-size:12px;text-align:right">
    <label style="font-size:10.5px;display:flex;align-items:center;gap:3px;justify-content:center" title="frete incluso"><input type="checkbox" ${it.frete_incluso?'checked':''} onchange="PREC.itens[${i}].frete_incluso=this.checked?1:0"> frete</label>
    <button class="btn-ghost" style="padding:2px 6px;color:var(--pend)" onclick="PREC.itens.splice(${i},1);precRenderItens()" title="remover">×</button>
  </div>`).join('')||'<div class="dmini">Sem itens.</div>';
}
function precAddItem(){ PREC.itens.push({descricao_original:'',insumo_nome:'',unidade:'',preco:'',frete_incluso:0,observacao:''}); precRenderItens(); }
async function precSalvar(){
  const v=id=>((document.getElementById(id)||{}).value||'');
  const forn=v('pt_forn').trim(); if(!forn){toast('Informe o fornecedor');return;}
  const itens=PREC.itens.filter(it=>(it.descricao_original||'').trim()).map(it=>({id:it.id,descricao_original:it.descricao_original,insumo_nome:it.insumo_nome,unidade:it.unidade,preco:(it.preco===''||it.preco==null)?'':Number(it.preco),frete_incluso:it.frete_incluso?1:0,observacao:it.observacao}));
  const body={acao:'salvar_tabela',me:EU&&EU.bitrix_id,tabela:{id:PREC.cur&&PREC.cur.id,fornecedor_nome:forn,titulo:v('pt_tit'),validade_inicio:v('pt_vi'),validade_fim:v('pt_vf'),observacao:v('pt_obs')},itens};
  try{ const r=await (await fetch('actions/precos.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(body)})).json();
    if(r.error){toast(r.error);return;} toast('Tabela salva'); precLoad(); }catch(e){toast('Falha: '+e.message);}
}
async function precExcluir(id){ if(!confirm('Excluir esta tabela de preços?'))return;
  try{ const r=await (await fetch('actions/precos.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({acao:'excluir_tabela',me:EU&&EU.bitrix_id,id})})).json();
    if(r.error){toast(r.error);return;} toast('Excluída'); precLoad(); }catch(e){toast('Falha');}
}
/* ========== SOLICITAÇÕES DE COMPRA (fila TOTVS ao vivo + de-para + overlay) ========== */
const SOL={tab:'dashboard',data:null,obras:null,filt:{obra:'',comprador:'',status:'',bucket:'',cobertura:'',busca:''},exp:{}};
const SOL_ST={pendente:['var(--neu)','Pendente','var(--neubg)'],em_cotacao:['var(--cot)','Em cotação','var(--cotbg)'],cotacoes_recebidas:['var(--and)','Cotações recebidas','var(--andbg)'],pedido_criado:['var(--ok)','Pedido criado','var(--okbg)'],cancelado:['var(--pend)','Cancelado','var(--pendbg)']};
const SOL_BK={r:['#eafaf0','#1f7a44','No prazo'],a:['#fdf4d9','#8a6d12','Atenção'],l:['#fde8cf','#b5610f','Atrasado'],c:['#fbe4e4','#b02020','Crítico']};
// FASE 2 — cobertura de cotação (cinza=sem cotação · amarelo=em cotação/parcial · verde=finalizada/com PC)
const SOL_COT={vazio:['#eef1f4','#8a9299','Sem cotação'],cotando:['#fdf4d9','#a5811a','Em cotação'],parcial:['#fdf4d9','#a5811a','Parcial'],coberto:['#eafaf0','#1f7a44','Cotada'],total:['#eafaf0','#1f7a44','Cotada']};
function solCotDot(k,extra){ const c=SOL_COT[k]||SOL_COT.vazio; return `<span title="Cotação: ${c[2]}${extra?' · '+extra:''}" style="display:inline-block;width:9px;height:9px;border-radius:50%;background:${c[1]};margin-right:6px;vertical-align:0;flex:0 0 auto"></span>`; }
function solMe(){ return encodeURIComponent((EU&&EU.bitrix_id)||''); }
function solInit(){ solTab(SOL.tab||'dashboard'); }
function solTab(t){ SOL.tab=t; ['dashboard','lista','obras'].forEach(x=>{const b=document.getElementById('stab-'+x); if(b)b.classList.toggle('on',x===t);});
  if(t==='obras') solObrasLoad(); else if(SOL.data) solRender(); else solLoad(); }
async function solLoad(){
  const w=document.getElementById('solwrap'); if(!SOL.data) w.innerHTML='<div class="dempty">Lendo a fila de solicitações ao vivo…</div>';
  try{ const j=await (await fetch('actions/solicitacoes.php?me='+solMe())).json();
    if(j.error){w.innerHTML='<div class="empty">'+esc(j.error)+'</div>';return;} SOL.data=j; solRender();
  }catch(e){ w.innerHTML='<div class="empty">Falha ao ler a fila.</div>'; }
}
function solRender(){ if(SOL.tab==='lista') return solRenderLista(); return solRenderDash(); }
function solPill(l){ const b=SOL_BK[l.bucket]||SOL_BK.r; return `<span class="dchip" style="background:${b[0]};color:${b[1]};font-weight:700" title="${b[2]}">${l.dias!=null?l.dias+' dias':'—'}</span>`; }
function solRenderDash(){
  const w=document.getElementById('solwrap'), d=SOL.data.dashboard, b=d.b;
  const card=(lbl,val,sub,cor)=>`<div class="kpi" style="min-width:150px"><div class="v" style="color:${cor||'inherit'}">${val}</div><div class="l">${lbl}${sub?` · ${sub}`:''}</div></div>`;
  const bkCard=(k,lbl)=>{const x=SOL_BK[k];return `<div style="flex:1;min-width:130px;border:1px solid ${x[1]}33;background:${x[0]};border-radius:10px;padding:12px 14px"><div style="font-size:24px;font-weight:800;color:${x[1]}">${b[k]}</div><div style="font-size:11.5px;color:${x[1]}">${lbl}</div></div>`;};
  const obras=Object.entries(d.por_obra), comps=Object.entries(d.por_comprador);
  w.innerHTML=`<div class="panel" style="margin-bottom:10px"><div class="kpis">
      ${card('Solicitações pendentes',d.total,'',null)}
      ${card('Obras com pendência',obras.length,'',null)}
      ${card('Compradores',comps.filter(c=>c[0]!=='(sem comprador)').length,'',null)}
      ${card('Críticos (+30 dias)',b.c,'precisam atenção','var(--pend)')}
    </div>
    <div style="display:flex;gap:10px;flex-wrap:wrap;margin-top:12px">${bkCard('r','0 a 7 dias')}${bkCard('a','8 a 14 dias')}${bkCard('l','15 a 30 dias')}${bkCard('c','+30 dias')}</div></div>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px">
      <div class="panel"><b style="font-size:13px">Resumo por obra</b><div class="wrap" style="margin-top:6px;max-height:360px;overflow:auto"><table><thead><tr><th>Obra</th><th style="text-align:center">Total</th><th style="text-align:center;color:var(--ok)">Recentes</th><th style="text-align:center;color:var(--pend)">Críticos</th></tr></thead><tbody>
        ${obras.map(([n,v])=>`<tr style="cursor:pointer" onclick="SOL.filt={obra:'${esc(n).replace(/'/g,"")}',comprador:'',status:'',bucket:'',busca:''};solTab('lista')"><td>${esc(n)}</td><td style="text-align:center"><b>${v.total}</b></td><td style="text-align:center;color:var(--ok)">${v.recentes||''}</td><td style="text-align:center;color:${v.criticos?'var(--pend)':'#bbb'}">${v.criticos||'0'}</td></tr>`).join('')}
      </tbody></table></div></div>
      <div class="panel"><b style="font-size:13px">Resumo por comprador</b><div class="wrap" style="margin-top:6px;max-height:360px;overflow:auto"><table><thead><tr><th>Comprador</th><th style="text-align:center">Total</th><th style="text-align:center;color:var(--ok)">0-7</th><th style="text-align:center;color:#8a6d12">8-14</th><th style="text-align:center;color:#b5610f">15-30</th><th style="text-align:center;color:var(--pend)">+30</th></tr></thead><tbody>
        ${comps.map(([n,v])=>`<tr style="cursor:pointer" onclick="SOL.filt={obra:'',comprador:'${esc(n).replace(/'/g,"")}',status:'',bucket:'',busca:''};solTab('lista')"><td>${esc(n)}</td><td style="text-align:center"><b>${v.total}</b></td><td style="text-align:center;color:var(--ok)">${v.r||''}</td><td style="text-align:center;color:#8a6d12">${v.a||''}</td><td style="text-align:center;color:#b5610f">${v.l||''}</td><td style="text-align:center;color:${v.c?'var(--pend)':'#bbb'}">${v.c||'0'}</td></tr>`).join('')}
      </tbody></table></div></div></div>`;
}
function solRenderLista(){
  const w=document.getElementById('solwrap'), all=SOL.data.solicitacoes||[], f=SOL.filt, qn=(f.busca||'').toLowerCase();
  const obras=[...new Set(all.map(s=>s.nome_obra))].sort(), comps=[...new Set(all.map(s=>s.comprador_nome).filter(Boolean))].sort();
  let rows=all.filter(s=>(!f.obra||s.nome_obra===f.obra)&&(!f.comprador||s.comprador_nome===f.comprador)&&(!f.status||s.status===f.status)&&(!f.bucket||s.bucket===f.bucket)&&(!f.cobertura||(s.cobertura||'vazio')===f.cobertura)&&(!qn||((s.numero+' '+s.primeiro+' '+(s.cotacoes||[]).map(x=>'#'+x.id+' '+(x.titulo||'')).join(' ')).toLowerCase().includes(qn))));
  rows.sort((a,b)=>(b.dias||0)-(a.dias||0));
  let html=`<div class="panel" style="margin-bottom:10px"><div class="bar" style="gap:8px;flex-wrap:wrap;align-items:center">
     <div class="search" style="min-width:180px"><span class="material-icons" style="color:var(--muted)">search</span><input id="solBusca" placeholder="Buscar nº ou item…" value="${esc(f.busca)}" oninput="SOL.filt.busca=this.value;solRenderLista()"></div>
     <select onchange="SOL.filt.obra=this.value;solRenderLista()" style="font-size:12px;padding:6px"><option value="">Todas as obras</option>${obras.map(o=>`<option value="${esc(o)}" ${o===f.obra?'selected':''}>${esc(o)}</option>`).join('')}</select>
     <select onchange="SOL.filt.comprador=this.value;solRenderLista()" style="font-size:12px;padding:6px"><option value="">Todos compradores</option>${comps.map(c=>`<option value="${esc(c)}" ${c===f.comprador?'selected':''}>${esc(c)}</option>`).join('')}</select>
     <select onchange="SOL.filt.bucket=this.value;solRenderLista()" style="font-size:12px;padding:6px"><option value="">Todos prazos</option><option value="r" ${f.bucket==='r'?'selected':''}>No prazo (0-7)</option><option value="a" ${f.bucket==='a'?'selected':''}>Atenção (8-14)</option><option value="l" ${f.bucket==='l'?'selected':''}>Atrasado (15-30)</option><option value="c" ${f.bucket==='c'?'selected':''}>Crítico (+30)</option></select>
     <select onchange="SOL.filt.status=this.value;solRenderLista()" style="font-size:12px;padding:6px"><option value="">Todos status</option>${Object.entries(SOL_ST).map(([k,v])=>`<option value="${k}" ${f.status===k?'selected':''}>${v[1]}</option>`).join('')}</select>
     <select onchange="SOL.filt.cobertura=this.value;solRenderLista()" style="font-size:12px;padding:6px" title="cobertura de cotação"><option value="">Toda cobertura</option><option value="vazio" ${f.cobertura==='vazio'?'selected':''}>⚪ Sem cotação</option><option value="parcial" ${f.cobertura==='parcial'?'selected':''}>🟡 Parcial</option><option value="total" ${f.cobertura==='total'?'selected':''}>🟢 Cotada</option></select>
     <span class="muted" style="font-size:11.5px">${rows.length} de ${all.length}</span>
     <button class="btn-ghost" style="margin-left:auto;padding:5px 10px" onclick="SOL.data=null;SOL.filt={obra:'',comprador:'',status:'',bucket:'',busca:''};solLoad()"><span class="material-icons" style="font-size:15px;vertical-align:-3px">refresh</span> Atualizar fila</button>
   </div></div><div class="wrap"><table><thead><tr><th style="width:26px;text-align:center"><input type="checkbox" title="selecionar todas do filtro" onchange="solSelAll(this.checked)"></th><th>Pedido</th><th style="text-align:center">Itens</th><th>Descrição</th><th>Obra</th><th>Emissão</th><th style="text-align:center">Dias</th><th>Status</th><th>Comprador</th><th>Observações</th><th>Ações</th></tr></thead><tbody>`;
  SOL.sel=SOL.sel||{}; SOL._rowsKeys=rows.map(s=>s.coligada+'|'+s.numero);
  for(const s of rows){ const key=s.coligada+'|'+s.numero, ex=SOL.exp[key];
    const st=SOL_ST[s.status]||['var(--neu)','?','var(--neubg)'], obs=s.observacoes||'';
    html+=`<tr${SOL.sel[key]?' style="background:#eef6f0"':''}><td style="text-align:center"><input type="checkbox" ${SOL.sel[key]?'checked':''} onchange="solSelToggle('${esc(key)}')"></td><td><b style="cursor:pointer" onclick="SOL.exp['${esc(key)}']=${ex?'false':'true'};solRenderLista()">${ex?'▾':'▸'} ${solCotDot(s.cobertura,(s.cot_cob||0)+'/'+s.n_itens+' itens')}${esc(String(s.numero).replace(/^0+/,'')||s.numero)}</b></td>
      <td style="text-align:center" title="${s.n_atendidos?esc(s.n_atendidos+' item(ns) ja viraram pedido de compra'):'todos pendentes'}">${s.n_atendidos?`<b>${s.n_pendentes}</b><span class="muted" style="font-size:10px">/${s.n_itens}</span>`:s.n_itens}</td><td style="max-width:220px"><span title="${esc(s.primeiro)}">${esc((s.primeiro||'').slice(0,40))}</span></td>
      <td class="muted" style="font-size:11.5px">${esc(s.nome_obra)}</td><td class="muted" style="font-size:11.5px;white-space:nowrap">${s.emissao?D(s.emissao):'—'}</td>
      <td style="text-align:center">${solPill(s)}</td>
      <td style="background:${st[2]};border-left:3px solid ${st[0]}">${CAN_COT?`<select onchange="solStatus('${esc(key)}',this.value,this)" style="font-size:11px;padding:3px 4px;font-weight:700;color:${st[0]};background:${st[2]};border:1px solid ${st[0]};border-radius:6px;cursor:pointer">${Object.entries(SOL_ST).map(([k,v])=>`<option value="${k}" ${s.status===k?'selected':''}>${v[1]}</option>`).join('')}</select>`:`<span class="dchip" style="background:${st[0]};color:#fff;font-weight:700">${st[1]}</span>`}</td>
      <td class="muted" style="font-size:11.5px">${esc(s.comprador_nome||'—')}</td>
      <td>${CAN_COT?`<input value="${esc(obs)}" title="${esc(obs)}" oninput="this.title=this.value" onchange="solObs('${esc(key)}',this.value,this)" placeholder="anotação…" style="width:150px;font-size:11px;padding:3px 5px">`:`<span title="${esc(obs)}">${esc(obs.slice(0,32))}${obs.length>32?'…':''}</span>`}</td>
      <td style="white-space:nowrap"><button class="btn-ghost" style="padding:2px 6px" title="Copiar mensagem para orçamento" onclick="solCopiar('${esc(key)}')"><span class="material-icons" style="font-size:15px">content_copy</span></button>
        ${(s.cotacoes&&s.cotacoes.length)
          ? s.cotacoes.map(x=>`<button class="btn-ghost" style="padding:2px 7px;color:var(--verde-d);font-weight:700;font-size:11px" title="Ver cotação #${x.id}: ${esc(x.titulo||'')}" onclick="showView('cotacoes');setTimeout(()=>cotAbrir(${x.id}),200)"><span class="material-icons" style="font-size:13px;vertical-align:-2px">request_quote</span>#${x.id}</button>`).join('')
          : (s.cotacao_id?`<button class="btn-ghost" style="padding:2px 6px;color:var(--verde-d)" title="Ver cotação gerada" onclick="showView('cotacoes');setTimeout(()=>cotAbrir(${s.cotacao_id}),200)"><span class="material-icons" style="font-size:15px">request_quote</span></button>`:'')}
        ${/* UMA SC PODE VIRAR VÁRIAS COTAÇÕES (ex.: prego direto de fábrica numa, arame com distribuidor
             noutra). Antes, o botão de gerar sumia assim que existia a primeira e a segunda ficava
             impossível pela lista. Agora ele fica sempre: o seletor de itens já vem com os que estão
             cobertos DESMARCADOS, então a próxima cotação nasce só com o que sobrou. */''}
        ${CAN_COT?`<button class="btn-ghost" style="padding:2px 6px" title="${(s.cotacoes&&s.cotacoes.length)||s.cotacao_id?'Gerar OUTRA cotação desta solicitação (itens já cotados vêm desmarcados)':'Gerar cotação desta solicitação'}" onclick="solGerar('${esc(key)}')"><span class="material-icons" style="font-size:15px;color:var(--verde)">playlist_add</span></button>`:''}</td></tr>`;
    if(ex) html+=`<tr><td colspan="11" style="background:#fafbfb;padding:8px 14px"><b style="font-size:11px;color:var(--muted)">ITENS</b> <span class="muted" style="font-size:10px">⚪ sem cotação · 🟡 em cotação · 🟢 finalizada</span>${s.itens.map(it=>{
          /* Item que ja virou PEDIDO fica RISCADO, com o numero do PC. Nao some: sumir sem explicacao
             e o que faz o comprador desconfiar da tela — e se o cruzamento errar, some um item que
             estava pendente de verdade. */
          const pc=it.pc_atendido||'';
          const est=pc?'text-decoration:line-through;opacity:.55':'';
          return `<div style="font-size:12px;padding:2px 0;${est}">${pc?'<span class="material-icons" style="font-size:13px;vertical-align:-2px;color:var(--ok)">shopping_cart_checkout</span> ':solCotDot(it.cot)}${cotNum(it.qtd)} ${esc(it.und)} — ${esc(it.produto)}${pc?` <b style="color:var(--verde-d);text-decoration:none;font-size:10.5px">ja no PC ${esc(pc)}</b>`:''}${(!pc&&it.cot_cid)?` <button class="btn-ghost" style="padding:0 5px;color:var(--verde-d);font-size:10px;font-weight:700;vertical-align:1px" title="Ver cotação #${it.cot_cid}${it.cot_ctit?': '+esc(it.cot_ctit):''}" onclick="showView('cotacoes');setTimeout(()=>cotAbrir(${it.cot_cid}),200)">#${it.cot_cid}</button>`:''}${it.observacao?` <span class="muted">(${esc(it.observacao)})</span>`:''}</div>`;
        }).join('')}</td></tr>`;
  }
  if(!rows.length) html+='<tr><td colspan="11" class="empty">Nenhuma solicitação nesse filtro.</td></tr>';
  const nSel=Object.keys(SOL.sel).filter(k=>SOL.sel[k]).length;
  const bar=nSel?`<div style="position:sticky;bottom:0;z-index:5;display:flex;align-items:center;gap:10px;background:var(--verde-d);color:#fff;padding:10px 16px;border-radius:10px;margin-top:8px;box-shadow:0 6px 20px rgba(0,0,0,.18)"><b>${nSel} solicitação(ões) selecionada(s)</b><button class="btn-prim" style="margin-left:auto;background:#fff;color:var(--verde-d)" onclick="solGerarMulti()"><span class="material-icons" style="font-size:16px;vertical-align:-3px">playlist_add</span> Gerar cotação com itens escolhidos</button><button class="btn-ghost" style="background:transparent;color:#fff;border-color:rgba(255,255,255,.6)" onclick="SOL.sel={};solRenderLista()">Limpar</button></div>`:'';
  const foc=document.activeElement, wasB=foc&&foc.id==='solBusca', car=wasB?foc.selectionStart:null;
  w.innerHTML=html+'</tbody></table></div>'+bar;
  if(wasB){const ni=document.getElementById('solBusca'); if(ni){ni.focus(); try{ni.setSelectionRange(car,car);}catch(e){}}}
}
/* ===== Fase 1: seleção múltipla de solicitações → tela de itens → cotação multi-obra ===== */
function solSelToggle(key){ SOL.sel=SOL.sel||{}; if(SOL.sel[key])delete SOL.sel[key]; else SOL.sel[key]=true; solRenderLista(); }
function solSelAll(on){ SOL.sel=SOL.sel||{}; (SOL._rowsKeys||[]).forEach(k=>{ if(on)SOL.sel[k]=true; else delete SOL.sel[k]; }); solRenderLista(); }
function solGerarMulti(){ const keys=Object.keys(SOL.sel||{}).filter(k=>SOL.sel[k]); const sols=(SOL.data.solicitacoes||[]).filter(s=>keys.includes(s.coligada+'|'+s.numero)); if(!sols.length){toast('Selecione ao menos uma solicitação');return;}
  SOL._pick={}; sols.forEach(s=>{ const k=s.coligada+'|'+s.numero; (s.itens||[]).forEach((it,idx)=>SOL._pick[k+'#'+idx]=((it.cot||'vazio')==='vazio')); });  // pré-marca só o que ainda NÃO está em cotação
  let ov=document.getElementById('solPickOv'); if(!ov){ov=document.createElement('div');ov.id='solPickOv';ov.style.cssText='position:fixed;inset:0;background:rgba(15,25,20,.42);z-index:9999;display:flex;align-items:flex-start;justify-content:center;padding:24px;overflow:auto';document.body.appendChild(ov);} ov.onclick=e=>{if(e.target===ov)ov.remove();};
  solPickRender(sols); }
function solPickRefresh(){ const keys=Object.keys(SOL.sel||{}).filter(k=>SOL.sel[k]); solPickRender((SOL.data.solicitacoes||[]).filter(s=>keys.includes(s.coligada+'|'+s.numero))); }
function solPickIt(k,idx,on){ SOL._pick[k+'#'+idx]=on; const b=document.getElementById('solPickBtn'); if(b){const n=Object.values(SOL._pick).filter(Boolean).length; b.innerHTML=`<span class="material-icons" style="font-size:15px;vertical-align:-3px">check</span> Criar cotação (${n} itens)`;} }
function solPickSC(k,on){ const s=(SOL.data.solicitacoes||[]).find(x=>x.coligada+'|'+x.numero===k); if(s)(s.itens||[]).forEach((it,idx)=>SOL._pick[k+'#'+idx]=on); solPickRefresh(); }
function solPickRender(sols){ const ov=document.getElementById('solPickOv'); if(!ov)return; const titVal=(document.getElementById('solPickTit')||{}).value||''; const nItens=Object.values(SOL._pick).filter(Boolean).length;
  const body=sols.map(s=>{ const k=s.coligada+'|'+s.numero; const allc=(s.itens||[]).length&&(s.itens||[]).every((it,idx)=>SOL._pick[k+'#'+idx]);
    return `<div style="border:1px solid var(--line);border-radius:10px;margin-top:10px;overflow:hidden">
      <div style="background:#f3f7f5;padding:8px 12px;display:flex;align-items:center;gap:8px"><b style="font-size:13px">SC ${esc(String(s.numero).replace(/^0+/,'')||s.numero)}</b><span class="muted" style="font-size:11.5px">${esc(s.nome_obra||'')} · ${esc(String(s.coligada||'').slice(0,28))}</span><label style="margin-left:auto;font-size:11px;cursor:pointer"><input type="checkbox" ${allc?'checked':''} onchange="solPickSC('${esc(k)}',this.checked)"> todos</label></div>
      <div style="padding:6px 12px">${(s.itens||[]).map((it,idx)=>`<label style="display:flex;gap:8px;align-items:flex-start;padding:3px 0;font-size:12.5px;cursor:pointer"><input type="checkbox" ${SOL._pick[k+'#'+idx]?'checked':''} onchange="solPickIt('${esc(k)}',${idx},this.checked)"><span>${cotNum(it.qtd)} ${esc(it.und)} — ${esc(it.produto)}${(it.cot&&it.cot!=='vazio')?` <span class="dchip" style="background:#fdf4d9;color:#a5811a;font-size:9.5px;font-weight:700" title="Este item já está na cotação${it.cot_cid?' #'+it.cot_cid:''}${it.cot_ctit?' ('+esc(it.cot_ctit)+')':''} — desmarcado por padrão para não duplicar">⚠ já na cotação${it.cot_cid?' #'+it.cot_cid:''}</span>`:''}${it.observacao?` <span class="muted">(${esc(it.observacao)})</span>`:''}</span></label>`).join('')}</div></div>`; }).join('');
  ov.innerHTML=`<div style="background:#fff;border-radius:14px;padding:18px 20px;max-width:720px;width:100%;box-shadow:0 12px 44px rgba(0,0,0,.22)" onclick="event.stopPropagation()">
    <div style="display:flex;justify-content:space-between;align-items:center"><b style="font-size:17px">Montar cotação — escolha os itens</b><span class="material-icons" style="cursor:pointer;color:var(--muted)" onclick="document.getElementById('solPickOv').remove()">close</span></div>
    <div class="muted" style="font-size:12px;margin-top:2px">${sols.length} solicitação(ões). Desmarque o que não vai (ex.: só o madeirite). A obra de cada item é resolvida pelo cadastro único; se houver mais de uma obra, a cotação fica multi-obra.</div>
    <label style="display:block;margin-top:10px"><span style="font-size:10px;font-weight:700;text-transform:uppercase;color:var(--muted)">Título (opcional)</span><input id="solPickTit" value="${esc(titVal)}" placeholder="em branco = gera automático" style="width:100%;padding:6px 8px;margin-top:2px;box-sizing:border-box"></label>
    ${body}
    <div style="display:flex;justify-content:flex-end;gap:8px;margin-top:14px;position:sticky;bottom:-18px;background:#fff;padding:10px 0"><button class="btn-ghost" onclick="document.getElementById('solPickOv').remove()">Cancelar</button><button class="btn-prim" id="solPickBtn" onclick="solMultiCriar()"><span class="material-icons" style="font-size:15px;vertical-align:-3px">check</span> Criar cotação (${nItens} itens)</button></div>
  </div>`; }
async function solMultiCriar(){ const keys=Object.keys(SOL.sel||{}).filter(k=>SOL.sel[k]); const sols=(SOL.data.solicitacoes||[]).filter(s=>keys.includes(s.coligada+'|'+s.numero));
  const itens=[]; sols.forEach(s=>{ const k=s.coligada+'|'+s.numero; (s.itens||[]).forEach((it,idx)=>{ if(SOL._pick[k+'#'+idx]) itens.push({coligada:s.coligada,numero:s.numero,obra_cod:s.obra_cod,produto:it.produto,und:it.und,qtd:it.qtd,observacao:it.observacao||'',seq:it.seq,codprd:it.codprd,colidmov:it.colidmov}); }); });
  if(!itens.length){toast('Escolha ao menos um item');return;}
  const titulo=(document.getElementById('solPickTit')||{}).value||'';
  try{ const r=await (await fetch('actions/solicitacoes.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({acao:'gerar_cotacao_multi',me:EU&&EU.bitrix_id,titulo,itens})})).json();
    if(r.error){toast(r.error);return;} const ov=document.getElementById('solPickOv'); if(ov)ov.remove(); SOL.sel={};
    toast(`Cotação criada: ${r.itens} itens · ${r.solicitacoes} SC · ${r.obras} obra(s)`); showView('cotacoes'); setTimeout(()=>cotAbrir(r.cotacao_id),250);
  }catch(e){toast('Falha: '+e.message);} }
function solFind(key){ return (SOL.data.solicitacoes||[]).find(s=>(s.coligada+'|'+s.numero)===key); }
async function solStatus(key,v,el){ const s=solFind(key); if(!s)return; const prev=s.status; s.status=v;
  const st=SOL_ST[v]||['var(--neu)','?','var(--neubg)'];
  if(el){ el.style.color=st[0]; el.style.background=st[2]; el.style.borderColor=st[0]; const td=el.closest('td'); if(td){ td.style.background=st[2]; td.style.borderLeft='3px solid '+st[0]; } }
  try{ const r=await (await fetch('actions/solicitacoes.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({acao:'salvar_overlay',me:EU&&EU.bitrix_id,coligada:s.coligada,numero:s.numero,status:v})})).json();
    if(r&&r.error){ s.status=prev; solRenderLista(); toast('Não salvou: '+r.error); return; }
    toast('Status salvo');
  }catch(e){ s.status=prev; solRenderLista(); toast('Falha ao salvar status — tente de novo'); } }
async function solObs(key,v,el){ const s=solFind(key); if(!s)return; const prev=s.observacoes||''; s.observacoes=v;
  try{ const r=await (await fetch('actions/solicitacoes.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({acao:'salvar_overlay',me:EU&&EU.bitrix_id,coligada:s.coligada,numero:s.numero,observacoes:v})})).json();
    if(r&&r.error){ s.observacoes=prev; if(el){el.value=prev;el.title=prev;} toast('Não salvou: '+r.error); return; }
    toast('Anotação salva');
  }catch(e){ s.observacoes=prev; if(el){el.value=prev;el.title=prev;} toast('Falha ao salvar anotação — tente de novo'); } }
function solCopiar(key){ const s=solFind(key); if(!s)return;
  const sub=/CAPRETZ/i.test(s.coligada)?(s.nome_obra||'Geral'):'Geral';
  const num=String(s.numero).replace(/^0+/,'')||s.numero;
  /* O fornecedor precisa do CNPJ (nota) e do endereco de entrega (frete) para orcar. O endereco vem
     da MESMA configuracao que alimenta o e-mail do pedido e o PDF — tres telas com enderecos
     diferentes e como material chega no lugar errado. */
  const cab=['Por favor cotar os itens abaixo para obra:','',s.coligada+' - '+sub];
  if(s.cnpj_obra) cab.push('CNPJ: '+s.cnpj_obra);
  if(s.endereco_entrega) cab.push('Endereço de entrega: '+s.endereco_entrega);
  const txt=cab.join('\n')+'\n\nSolicitação nº '+num+'\n\nItens:\n\n'+s.itens.filter(it=>!it.pc_atendido).map(it=>'- '+cotNum(it.qtd)+' '+it.und+' - '+it.produto+(it.observacao?' ('+it.observacao+')':'')).join('\n');
  if(!s.itens.filter(it=>!it.pc_atendido).length){ toast('Todos os itens desta solicitação já viraram pedido de compra'); return; }
  navigator.clipboard.writeText(txt).then(()=>toast('Mensagem copiada!'),()=>{ const t=document.createElement('textarea');t.value=txt;document.body.appendChild(t);t.select();document.execCommand('copy');t.remove();toast('Mensagem copiada!'); }); }