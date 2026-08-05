/* ═════════════ CAIXA DE E-MAIL — suprimentos@ (enviados e recebidos) ═════════════
   A conta manda carta de cotação E pedido de compra, e até agora o único jeito de ver isso era
   entrar na cotação. Aqui é a caixa como caixa: quem mandou, para quem, o quê, com que anexo.

   SÓ LEITURA, e não por decisão de tela: o servidor não tem endpoint de apagar, e a conexão IMAP
   é aberta em OP_READONLY com FT_PEEK em todo fetch. Nem marcar como lida a gente marca — a caixa
   é lida por gente no webmail e não pode ser bagunçada por nós. */

let CX = { dir:'in', pagina:1, itens:[], total:0, conta:'', carregando:false, sincronizando:false,
           f:{ q:'', de:'', para:'', desde:'', ate:'', anexo:false, origem:'' } };

function caixaInit(){
  if(CX.itens.length) { caixaRender(); return; }
  caixaCarregar();
  caixaSync(false);          // 1ª abertura: puxa o que há de novo, sem travar a tela
}

function caixaQS(){
  const f=CX.f, p=new URLSearchParams();
  p.set('listar','1'); p.set('dir',CX.dir); p.set('pagina',CX.pagina);
  p.set('me',(EU&&EU.bitrix_id)||'');
  if(f.q) p.set('q',f.q);
  if(f.de) p.set('de',f.de);
  if(f.para) p.set('para',f.para);
  if(f.desde) p.set('desde',f.desde);
  if(f.ate) p.set('ate',f.ate);
  if(f.anexo) p.set('anexo','1');
  if(f.origem) p.set('origem',f.origem);
  return p.toString();
}

async function caixaCarregar(){
  CX.carregando=true; caixaRender();
  try{
    const d=await (await fetch('actions/caixa.php?'+caixaQS())).json();
    if(d.error){ CX.erro=d.error; CX.itens=[]; CX.total=0; }
    else { CX.erro=null; CX.itens=d.itens||[]; CX.total=d.total||0; CX.conta=d.conta||''; CX.porPagina=d.por_pagina||40; }
  }catch(e){ CX.erro='Falha ao carregar: '+e.message; }
  CX.carregando=false; caixaRender();
}

/* A varredura é o que traz o que chegou/saiu desde a última vez. Roda calada na abertura;
   com aviso quando a pessoa aperta o botão (aí ela quer saber o que aconteceu). */
async function caixaSync(explicito){
  if(CX.sincronizando) return;
  CX.sincronizando=true; if(explicito) caixaRender();
  try{
    const d=await (await fetch('actions/caixa.php',{method:'POST',headers:{'Content-Type':'application/json'},
      body:JSON.stringify({acao:'sync',me:EU&&EU.bitrix_id,forcar:explicito?1:0})})).json();
    CX.sincronizando=false;
    if(d.error){ if(explicito) toast(d.error); return; }
    if(d.throttled){ if(explicito) toast('Acabei de varrer a caixa.'); return; }
    if(d.sem_config||d.sem_imap||d.erro_imap){ CX.avisoSync=d.aviso||d.erro_imap; caixaRender(); return; }
    CX.avisoSync=(d.avisos&&d.avisos.length)?d.avisos.join(' · '):'';
    if(explicito) toast(d.novas?(d.novas+' mensagem(ns) nova(s)'):'Nada novo na caixa');
    if(d.novas) caixaCarregar(); else caixaRender();
  }catch(e){ CX.sincronizando=false; if(explicito) toast('Falha: '+e.message); }
}

function caixaAba(dir){ if(CX.dir===dir) return; CX.dir=dir; CX.pagina=1; CX.itens=[]; caixaCarregar(); }
function caixaFiltrar(){
  const g=id=>{const e=document.getElementById(id); return e?e.value.trim():'';};
  CX.f.q=g('cxQ'); CX.f.de=g('cxDe'); CX.f.para=g('cxPara'); CX.f.desde=g('cxDesde'); CX.f.ate=g('cxAte');
  const a=document.getElementById('cxAnexo'); CX.f.anexo=!!(a&&a.checked);
  CX.f.origem=g('cxOrigem');
  CX.pagina=1; caixaCarregar();
}
function caixaLimpar(){ CX.f={q:'',de:'',para:'',desde:'',ate:'',anexo:false,origem:''}; CX.pagina=1; caixaCarregar(); }
function caixaPag(p){ CX.pagina=p; caixaCarregar(); }

function cxData(iso){
  if(!iso) return '—';
  const d=new Date(iso); if(isNaN(d)) return String(iso).slice(0,10);
  const hoje=new Date(); const mesmoDia=d.toDateString()===hoje.toDateString();
  return mesmoDia ? d.toLocaleTimeString('pt-BR',{hour:'2-digit',minute:'2-digit'})
                  : d.toLocaleDateString('pt-BR')+' '+d.toLocaleTimeString('pt-BR',{hour:'2-digit',minute:'2-digit'});
}
/* "Fulano <f@x.com>, outro@y.com" → mostra o nome quando existe, senão o e-mail. Lista longa
   vira "primeiro +N": o pedido de compra vai com muita gente em cópia e estouraria a linha. */
function cxPessoas(txt, max){
  const p=String(txt||'').split(',').map(x=>x.trim()).filter(Boolean);
  if(!p.length) return '—';
  const nome=x=>{ const m=x.match(/^(.*?)\s*<(.+)>$/); return m?(m[1].replace(/^"|"$/g,'').trim()||m[2]):x; };
  const vis=p.slice(0,max||2).map(nome);
  return esc(vis.join(', '))+(p.length>vis.length?('<span class="muted"> +'+(p.length-vis.length)+'</span>'):'');
}

function caixaRender(){
  const w=document.getElementById('caixaWrap'); if(!w) return;
  const aba=(k,lbl,ic)=>`<button class="btn-ghost" style="padding:7px 16px;border-radius:9px 9px 0 0;${CX.dir===k?'background:#fff;border-bottom:2px solid var(--verde);font-weight:700;color:var(--verde-d)':'color:var(--muted)'}" onclick="caixaAba('${k}')"><span class="material-icons" style="font-size:16px;vertical-align:-3px">${ic}</span> ${lbl}</button>`;

  let h=`<div style="display:flex;gap:4px;border-bottom:1px solid var(--line);margin-bottom:12px;align-items:center">
    ${aba('in','Recebidos','inbox')}${aba('out','Enviados','send')}
    <div style="margin-left:auto;display:flex;align-items:center;gap:10px">
      ${CX.conta?`<span class="muted" style="font-size:11.5px">${esc(CX.conta)}</span>`:''}
      <button class="btn-ghost" style="padding:6px 12px;font-size:12.5px" onclick="caixaSync(true)" ${CX.sincronizando?'disabled':''}>
        <span class="material-icons" style="font-size:15px;vertical-align:-3px">${CX.sincronizando?'hourglass_top':'refresh'}</span> ${CX.sincronizando?'Varrendo…':'Buscar novos'}</button>
    </div></div>`;

  if(CX.avisoSync) h+=`<div style="border-left:4px solid var(--dourado);background:#fdf9ec;padding:8px 12px;border-radius:0 8px 8px 0;font-size:12.5px;margin-bottom:10px">${esc(CX.avisoSync)}</div>`;

  // ── filtros ──
  const f=CX.f;
  h+=`<div style="display:flex;gap:8px;flex-wrap:wrap;align-items:flex-end;border:1px solid var(--line);border-radius:10px;padding:10px 12px;margin-bottom:12px">
    <label style="flex:2 1 220px"><span style="font-size:10px;font-weight:700;text-transform:uppercase;color:var(--muted)">Buscar</span>
      <input id="cxQ" value="${esc(f.q)}" placeholder="assunto, texto ou nome do anexo…" style="width:100%;padding:5px 8px;font-size:12.5px;box-sizing:border-box" onkeydown="if(event.key==='Enter')caixaFiltrar()"></label>
    <label style="flex:1 1 150px"><span style="font-size:10px;font-weight:700;text-transform:uppercase;color:var(--muted)">Remetente</span>
      <input id="cxDe" value="${esc(f.de)}" placeholder="quem mandou" style="width:100%;padding:5px 8px;font-size:12.5px;box-sizing:border-box" onkeydown="if(event.key==='Enter')caixaFiltrar()"></label>
    <label style="flex:1 1 150px"><span style="font-size:10px;font-weight:700;text-transform:uppercase;color:var(--muted)">Destinatário</span>
      <input id="cxPara" value="${esc(f.para)}" placeholder="para / cópia" style="width:100%;padding:5px 8px;font-size:12.5px;box-sizing:border-box" onkeydown="if(event.key==='Enter')caixaFiltrar()"></label>
    <label><span style="font-size:10px;font-weight:700;text-transform:uppercase;color:var(--muted)">De</span>
      <input id="cxDesde" type="date" value="${esc(f.desde)}" style="padding:5px 8px;font-size:12.5px"></label>
    <label><span style="font-size:10px;font-weight:700;text-transform:uppercase;color:var(--muted)">Até</span>
      <input id="cxAte" type="date" value="${esc(f.ate)}" style="padding:5px 8px;font-size:12.5px"></label>
    ${CX.dir==='out'?`<label><span style="font-size:10px;font-weight:700;text-transform:uppercase;color:var(--muted)">Origem</span>
      <select id="cxOrigem" style="padding:5px 8px;font-size:12.5px"><option value="">todas</option>
        <option value="cockpit" ${f.origem==='cockpit'?'selected':''}>pelo cockpit</option>
        <option value="webmail" ${f.origem==='webmail'?'selected':''}>pelo webmail</option></select></label>`:''}
    <label class="ckl" style="align-self:center"><input type="checkbox" id="cxAnexo" ${f.anexo?'checked':''}> com anexo</label>
    <button class="btn-prim" style="padding:6px 14px;font-size:12.5px" onclick="caixaFiltrar()">Filtrar</button>
    <button class="btn-ghost" style="padding:6px 12px;font-size:12.5px" onclick="caixaLimpar()">Limpar</button>
  </div>`;

  if(CX.erro){ w.innerHTML=h+`<div class="dempty">${esc(CX.erro)}</div>`; return; }
  if(CX.carregando && !CX.itens.length){ w.innerHTML=h+'<div class="dempty">Carregando…</div>'; return; }
  if(!CX.itens.length){
    w.innerHTML=h+`<div class="dempty">Nenhuma mensagem${(f.q||f.de||f.para||f.desde||f.ate||f.anexo||f.origem)?' com esses filtros':''}.${CX.dir==='out'?' Se a caixa acabou de ser ligada, aperte “Buscar novos”.':''}</div>`;
    return;
  }

  const outro = CX.dir==='in' ? 'De' : 'Para';
  h+=`<div class="wrap"><table style="width:100%;font-size:12.5px"><thead><tr>
      <th style="width:210px">${outro}</th><th>Assunto</th>
      <th style="width:150px">${CX.dir==='out'?'Disparado por':'Vínculo'}</th>
      <th style="width:130px;text-align:right">Quando</th></tr></thead><tbody>`;
  for(const m of CX.itens){
    const pessoa = CX.dir==='in'
      ? esc(m.de_nome||m.de_email||'—')+(m.de_nome&&m.de_email?`<div class="dmini" style="color:var(--muted)">${esc(m.de_email)}</div>`:'')
      : cxPessoas(m.para,2)+(m.cc?`<div class="dmini" style="color:var(--muted)">cc: ${cxPessoas(m.cc,1)}</div>`:'');
    let terceira='';
    if(CX.dir==='out'){
      terceira = m.origem==='cockpit'
        ? `<span class="dchip" style="background:var(--verde)">cockpit</span>`
          + `<div class="dmini" style="color:var(--muted)">${esc(m.disparado_por||'—')}`
          + (m.ref_tipo?` · ${esc(m.ref_tipo==='pedido'?'pedido '+(m.ref_valor||''):'cotação '+(m.ref_valor||''))}`:'')+`</div>`
        : `<span class="dchip" style="background:#8a9299">webmail</span>`
          + `<div class="dmini" style="color:var(--muted)">escrito direto na conta</div>`;
    } else {
      terceira = m.cotacao_id ? `<span class="dchip" style="background:var(--verde)">cotação ${m.cotacao_id}</span>` : '<span class="muted">—</span>';
    }
    h+=`<tr style="cursor:pointer" onclick="caixaAbrir(${m.id})">
      <td>${pessoa}</td>
      <td><b>${esc(m.assunto||'(sem assunto)')}</b>
        ${(+m.tem_anexo)?`<span class="material-icons" style="font-size:14px;vertical-align:-2px;color:var(--muted)" title="${esc(m.anexos_nomes||'')}">attach_file</span>`:''}
        <div class="dmini" style="color:var(--muted)">${esc(String(m.preview||'').replace(/\s+/g,' ').slice(0,110))}</div></td>
      <td style="font-size:11.5px">${terceira}</td>
      <td style="text-align:right;white-space:nowrap" class="muted">${esc(cxData(m.data_email))}</td></tr>`;
  }
  h+='</tbody></table></div>';

  const pp=CX.porPagina||40, pags=Math.ceil(CX.total/pp);
  if(pags>1){
    h+=`<div style="display:flex;gap:6px;align-items:center;justify-content:center;margin-top:12px;font-size:12.5px">
      <button class="btn-ghost" style="padding:4px 10px" onclick="caixaPag(${Math.max(1,CX.pagina-1)})" ${CX.pagina<=1?'disabled':''}>anterior</button>
      <span class="muted">página <b>${CX.pagina}</b> de ${pags} · ${CX.total} mensagem(ns)</span>
      <button class="btn-ghost" style="padding:4px 10px" onclick="caixaPag(${Math.min(pags,CX.pagina+1)})" ${CX.pagina>=pags?'disabled':''}>próxima</button></div>`;
  } else {
    h+=`<div class="dmini" style="text-align:center;margin-top:10px;color:var(--muted)">${CX.total} mensagem(ns)</div>`;
  }
  w.innerHTML=h;
}

/* Abrir busca o CORPO INTEIRO no IMAP na hora — a lista guarda só a prévia. Se a caixa estiver
   fora do ar, mostra a prévia e diz por quê, em vez de uma tela vazia sem explicação. */
async function caixaAbrir(id){
  dlgAbrir('Caixa de E-mail','Mensagem','<div style="max-width:900px"><div class="dempty">Buscando a mensagem na caixa…</div></div>');
  let d;
  try{ d=await (await fetch('actions/caixa.php?abrir='+id+'&me='+encodeURIComponent((EU&&EU.bitrix_id)||''))).json(); }
  catch(e){ dlgAbrir('Caixa de E-mail','Mensagem','<div style="max-width:600px"><div class="dempty">Falha: '+esc(e.message)+'</div></div>'); return; }
  if(d.error){ dlgAbrir('Caixa de E-mail','Mensagem','<div style="max-width:600px"><div class="dempty">'+esc(d.error)+'</div></div>'); return; }
  const m=d.msg;
  const linha=(rot,val)=>val?`<div style="display:flex;gap:8px;font-size:12.5px;margin-bottom:3px"><span class="muted" style="min-width:74px">${rot}</span><span>${val}</span></div>`:'';
  let h='<div style="max-width:900px">';
  h+=`<div style="border:1px solid var(--line);border-radius:10px;padding:12px 14px;margin-bottom:12px">
      <div style="font-size:15.5px;font-weight:700;margin-bottom:8px">${esc(m.assunto||'(sem assunto)')}</div>
      ${linha('De', esc(m.de_nome? (m.de_nome+' <'+m.de_email+'>') : m.de_email))}
      ${linha('Para', cxPessoas(m.para,20))}
      ${linha('Cópia', m.cc?cxPessoas(m.cc,20):'')}
      ${linha('Quando', esc(cxData(m.data_email)))}
      ${m.origem==='cockpit'?linha('Origem','<span class="dchip" style="background:var(--verde)">cockpit</span> '+esc(m.disparado_por||'')+(m.ref_tipo?(' · '+esc(m.ref_tipo==='pedido'?('pedido '+(m.ref_valor||'')):('cotação '+(m.ref_valor||'')))):'')):''}
      ${m.origem==='webmail'?linha('Origem','<span class="dchip" style="background:#8a9299">webmail</span> <span class="muted">escrito direto na conta, fora do cockpit</span>'):''}
    </div>`;
  if(d.aviso) h+=`<div style="border-left:4px solid var(--dourado);background:#fdf9ec;padding:8px 12px;border-radius:0 8px 8px 0;font-size:12.5px;margin-bottom:10px">${esc(d.aviso)}</div>`;
  if(d.anexos&&d.anexos.length){
    h+='<div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:12px">';
    for(const a of d.anexos)
      h+=`<a class="btn-ghost" style="padding:5px 11px;font-size:12px;text-decoration:none" target="_blank"
            href="actions/caixa.php?anexo=${m.id}&i=${a.i}&me=${encodeURIComponent((EU&&EU.bitrix_id)||'')}">
            <span class="material-icons" style="font-size:14px;vertical-align:-3px">attach_file</span> ${esc(a.nome)}
            <span class="muted">(${Math.max(1,Math.round(a.bytes/1024))} KB)</span></a>`;
    if(d.embutidos) h+=`<span class="muted" style="font-size:11.5px;align-self:center">+${d.embutidos} imagem(ns) da assinatura</span>`;
    h+='</div>';
  } else if(d.embutidos){
    h+=`<div class="dmini" style="color:var(--muted);margin-bottom:10px">Sem anexo — as ${d.embutidos} imagens desta mensagem são da assinatura do remetente.</div>`;
  }
  const corpo=(d.corpo!==null&&d.corpo!==undefined&&d.corpo!=='')?d.corpo:(d.preview||'(sem texto)');
  h+=`<div style="white-space:pre-wrap;font-size:13px;line-height:1.55;border:1px solid var(--line);border-radius:10px;padding:13px 15px;max-height:52vh;overflow:auto">${esc(corpo)}</div>`;
  h+=`<div class="dmini" style="margin-top:9px;color:var(--muted)">Esta tela é só leitura — nada aqui apaga, move ou marca mensagem na caixa.</div>`;
  h+='<div class="bar" style="justify-content:flex-end;margin-top:12px"><button class="btn-prim" onclick="closeModal(true)">Fechar</button></div></div>';
  dlgAbrir('Caixa de E-mail','Mensagem',h);
}
