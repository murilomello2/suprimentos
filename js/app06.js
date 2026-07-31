/* Cockpit de Suprimentos — parte 6 de 6 do aplicativo.
   Gerado a partir do bloco unico que vivia dentro do index.php: 857 KB num arquivo so faziam
   cada deploy levar de 5 a 10 minutos e falhar calado. O corte respeita fronteiras de nivel
   superior e cada parte foi validada pelo parser antes de existir. A ORDEM importa: os
   arquivos sao carregados na sequencia em que foram cortados. */
/* ---- acoes em massa ---- */
async function envLoteGerarPdf(){
  const sel=envSelecionados().filter(e=>(e.sem_pdf||0)>0);
  if(!sel.length){ toast('Todos os PDFs dos marcados já estão prontos'); return; }
  const total=sel.reduce((a,b)=>a+(b.sem_pdf||0),0);
  toast('Gerando '+total+' PDF(s)...');
  let ok=0; const err=[];
  for(const e of sel){
    const faltam=(e.pedidos||[]).filter(p=>!p.tem_pdf);
    try{ const r=await (await fetch('actions/envio.php',{method:'POST',headers:{'Content-Type':'application/json'},
      body:JSON.stringify({acao:'gerar_pdf_lote',me:(EU&&EU.bitrix_id),ficha_id:e.ficha_id,obra:e.obra,
        so_obra:(e.destino==='obra'?1:0),
        pedidos:faltam.map(p=>({coligada:p.coligada_cod,numero:p.numero}))})})).json();
      if(r.error) err.push(e.obra+'/'+e.forn_nome+': '+r.error); else ok+=(r.gerados||0);
      (r.falhas||[]).forEach(f=>err.push('PC '+f.numero+': '+f.motivo));
    }catch(x){ err.push(e.forn_nome+': '+x.message); }
  }
  toast(ok+' PDF(s) gerado(s)'+(err.length?(' — '+err.length+' com problema'):''));
  if(err.length) setTimeout(()=>toast(err[0]),2600);
  ENV.d=null; envCarregar();
}
async function envLoteSoObra(){
  const sel=envSelecionados();
  if(!sel.length) return;
  dlgAbrir('Envio de Pedidos','Marcar '+sel.length+' e-mail(s) como só para a obra',
    '<div style="max-width:540px"><div class="dmini" style="margin-bottom:10px">'
   + 'O e-mail vai <b>só para a obra</b>, para lançamento. O fornecedor <b>não recebe nada</b> — é a trava '
   + 'do material que já foi entregue.</div>'
   + cotFld('Motivo (fica registrado com o seu nome)','<input id="envLoteMot" placeholder="ex.: material já entregue, pedido só para lançamento" style="width:100%">')
   + '<div class="bar" style="justify-content:flex-end;gap:8px;margin-top:14px">'
   + '<button class="btn-ghost" onclick="closeModal(true)">Cancelar</button>'
   + '<button class="btn-prim" onclick="envLoteSoObraSalvar()">Confirmar</button></div></div>');
}
async function envLoteSoObraSalvar(){
  const m=((document.getElementById('envLoteMot')||{}).value||'').trim();
  if(!m){ toast('Escreva o motivo'); return; }
  const sel=envSelecionados(); let n=0;
  for(const e of sel) for(const p of (e.pedidos||[])){
    try{ await fetch('actions/envio.php',{method:'POST',headers:{'Content-Type':'application/json'},
      body:JSON.stringify({acao:'decidir',me:(EU&&EU.bitrix_id),me_nome:(EU&&EU.nome)||'',
                           decisao:'so_obra',coligada:p.coligada_cod,numero:p.numero,motivo:m})}); n++; }catch(x){}
  }
  closeModal(true); toast(n+' pedido(s) marcados para ir só à obra');
  ENV.sel={}; ENV.d=null; envCarregar();
}
async function envLoteEnviar(){
  const sel=envSelecionados().filter(e=>!(e.sem_pdf||0));
  if(!sel.length){ toast('Nenhum marcado com o PDF pronto'); return; }
  const comAlerta=sel.filter(e=>e.alerta||e.forn_travado).length;
  /* De onde o lote vai sair. No envio unitario o servidor PARA e pergunta quando o remetente nao e
     pedidos@caprem.com.br; aqui isso precisa aparecer ANTES, porque um lote nao tem onde parar no
     meio — sairiam N e-mails de um endereco que o fornecedor nao reconhece. */
  const ct=(ENV.d&&ENV.d.conta)||{};
  dlgAbrir('Envio de Pedidos','Enviar '+sel.length+' e-mail(s)',
    '<div style="max-width:640px">'
   + '<div class="dmini" style="margin-bottom:10px;padding:7px 11px;border-radius:8px;background:#f8faf9;border:1px solid var(--line)">'
   + 'Remetente: <b>'+esc(ct.de||'(nao configurado)')+'</b></div>'
   + (ct.e_pedidos?'':('<div style="border-left:4px solid var(--dourado);background:#fdf9ec;padding:9px 12px;border-radius:0 8px 8px 0;'
      + 'font-size:12.5px;margin-bottom:10px">Esta <b>nao</b> e a conta dos pedidos. Os fornecedores conhecem '
      + '<b>pedidos@caprem.com.br</b>; sairao '+sel.length+' e-mail(s) de <b>'+esc(ct.de||'?')+'</b>.'
      + '<label style="display:flex;gap:6px;align-items:center;margin-top:7px;font-weight:700">'
      + '<input type="checkbox" id="envLoteConta"> Enviar assim mesmo</label></div>'))
   + (comAlerta?('<div style="border-left:4px solid #c0392b;background:#fdf1ef;padding:9px 12px;border-radius:0 8px 8px 0;'
      + 'font-size:12.5px;margin-bottom:10px"><b>'+comAlerta+' com sinal de material já em obra ou CNPJ na observação.</b> '
      + 'Se algum for regularização, cancele e use <b>Só para a obra</b> — o fornecedor pode entregar de novo.</div>'):'')
   + '<div style="max-height:250px;overflow:auto;border:1px solid var(--line);border-radius:8px;margin-bottom:12px">'
   + '<table class="dtable" style="width:100%;font-size:12px"><tbody>'
   + sel.map(x=>'<tr'+((x.alerta||x.forn_travado)?' style="background:#fdf3f3"':'')+'><td>'+esc(x.obra)+'</td><td>'+esc(x.forn_nome)+'</td>'
       + '<td class="muted">'+esc(x.para)+'</td><td style="text-align:right">'+(x.pedidos||[]).length+' PC</td></tr>').join('')
   + '</tbody></table></div>'
   + '<div id="envLoteMsg" class="dmini" style="margin-bottom:8px"></div>'
   + '<div class="bar" style="justify-content:flex-end;gap:8px">'
   + '<button class="btn-ghost" onclick="closeModal(true)">Cancelar</button>'
   + '<button class="btn-prim" id="envLoteBtn" onclick="envLoteEnviarConfirmado()">Enviar os '+sel.length+'</button></div></div>');
}
async function envLoteEnviarConfirmado(){
  const sel=envSelecionados().filter(e=>!(e.sem_pdf||0));
  const b=document.getElementById('envLoteBtn'), m=document.getElementById('envLoteMsg');
  /* So repassa o "aceito a conta geral" se o Murilo tiver marcado a caixa. Mandar 1 fixo, como
     estava, fazia o lote atravessar calado a trava que o envio unitario respeita. */
  const ct=(ENV.d&&ENV.d.conta)||{};
  const aceita=ct.e_pedidos?1:((document.getElementById('envLoteConta')||{}).checked?1:0);
  if(!ct.e_pedidos&&!aceita){
    if(m) m.innerHTML='<span style="color:var(--pend)">Marque <b>Enviar assim mesmo</b> para sair de '+esc(ct.de||'outra conta')+'.</span>';
    return;
  }
  if(b) b.disabled=true;
  let ok=0; const err=[];
  for(let i=0;i<sel.length;i++){
    if(m) m.textContent='Enviando '+(i+1)+' de '+sel.length+'...';
    try{ const r=await (await fetch('actions/envio.php',{method:'POST',headers:{'Content-Type':'application/json'},
      body:JSON.stringify({acao:'enviar',me:(EU&&EU.bitrix_id),me_nome:(EU&&EU.nome)||'',
                           envelope:sel[i].chave, aceito_conta_geral:aceita})})).json();
      if(r.error) err.push(sel[i].obra+' / '+sel[i].forn_nome+': '+String(r.error).replace('CONTA_GERAL:','')); else ok++;
    }catch(e){ err.push(sel[i].forn_nome+': '+e.message); }
  }
  closeModal(true);
  toast(ok+' e-mail(s) enviado(s)'+(err.length?(' — '+err.length+' com problema'):''));
  if(err.length) setTimeout(()=>toast(err[0]),2600);
  ENV.sel={}; ENV.d=null; envCarregar();
}

function envAchar(ch){ return (ENV.d.envelopes||[]).find(x=>x.chave===ch); }

/* ---- bloqueados: agrupados pelo MOTIVO, porque o conserto e por motivo ---- */
const ENV_BLOQ={obra:['Obra nao identificada','apartment','Sem ficha vinculada nao existe endereco de entrega - e assim que um pedido iria para a obra errada.'],
                config:['Obra sem dados de envio','settings','Falta CNO, endereco, contato do almoxarifado ou e-mail de NF. Preencha em Configuracoes > E-mail do pedido > Por obra.'],
                email:['Fornecedor sem e-mail','mail_off','Nao temos e-mail deste fornecedor no cadastro. Preencha na tela de Fornecedores.'],
                sede:['Compras da sede (sem canteiro)','business','Administrativo, Marketing, Comercial, TI: nao tem obra, nao tem CNO nem almoxarifado. Precisam de um texto proprio — hoje nao entram na fila.']};
function envBloq(){ const bs=(ENV.d.bloqueados||[]);
  if(!bs.length) return '<div class="panel"><div class="dempty">Nada bloqueado. Todo pedido aprovado tem obra, endereco e destinatario.</div></div>';
  const grupos={}; bs.forEach(b=>{ (grupos[b.bloqueio]=grupos[b.bloqueio]||[]).push(b); });
  const res=ENV.d.bloq_resumo||{};
  /* Arquivar em lote tambem aqui: boa parte do que trava sao pedidos antigos que nao vao mais sair,
     e obrigar a voltar na aba Fila para arquiva-los seria um passo a toa. */
  let h='<div class="panel" style="padding:10px 14px;margin-bottom:10px"><div class="bar" style="justify-content:space-between;flex-wrap:wrap;gap:8px">'
   + '<div class="dmini">Use <b>Ver</b> para conferir os itens e a observacao antes de decidir. <b>Arquivar</b> tira da tela sem apagar nada.</div>'
   + '<button class="btn-ghost" onclick="envArqLoteForm()" style="padding:6px 13px"><span class="material-icons" style="font-size:16px;vertical-align:-4px">inventory_2</span> Arquivar antigos em lote</button>'
   + '</div></div>';
  Object.keys(grupos).forEach(k=>{ const meta=ENV_BLOQ[k]||[k,'block',''], lista=grupos[k];
    const r=res[k]||{total:lista.length,mostrando:lista.length,valor:0};
    h+='<div class="panel" style="margin-bottom:10px;border-left:4px solid #c0392b">'
     + cotSecHead(meta[1], meta[0]+' ('+r.total+')', meta[2],
         '<span class="dchip" style="background:var(--muted)">'+BRLc(r.valor||0)+' parados</span>');
    h+='<div style="overflow-x:auto"><table class="dtable" style="width:100%;font-size:12.5px"><thead><tr>'
     + '<th style="text-align:left">Pedido</th><th style="text-align:left">Obra (como o TOTVS manda)</th>'
     + '<th style="text-align:left">Fornecedor</th><th style="text-align:right">Valor</th><th style="text-align:right">Parado ha</th><th></th></tr></thead><tbody>';
    lista.forEach(b=>{ h+='<tr><td><b>'+esc(b.numero)+'</b> <span class="dmini" style="color:var(--muted)">'+esc(b.coligada||('col. '+b.coligada_cod))+'</span></td>'
     + '<td>'+esc(b.obra||'—')+'</td><td>'+esc(b.forn_nome||'—')+'</td>'
     + '<td style="text-align:right">'+BRL(b.valor)+'</td>'
     + '<td style="text-align:right">'+(b.dias!=null?(b.dias+'d'):'—')+'</td>'
     + '<td style="text-align:right;white-space:nowrap">'
     + (k==='email' ? '<button class="btn-prim" style="padding:2px 9px;font-size:11.5px" onclick="envEmailForm('+esc(JSON.stringify({cod:b.forn_cod||'',cnpj:b.forn_cnpj_fmt||'',nome:b.forn_nome||''}))+')">Cadastrar e-mail</button> ' : '')
     + '<button class="btn-ghost" style="padding:2px 8px;font-size:11.5px" onclick="envVerPedido(\''+b.coligada_cod+'\',\''+b.numero+'\')">Ver</button> '
     + '<button class="btn-ghost" style="padding:2px 8px;font-size:11.5px" onclick="envArqUm(\''+b.coligada_cod+'\',\''+b.numero+'\')">Arquivar</button>'
     + '</td></tr>'; });
    h+='</tbody></table></div>';
    if(r.total>r.mostrando) h+='<div class="dmini" style="margin-top:7px;color:var(--muted)">Mostrando os '
      + r.mostrando+' mais recentes de <b>'+r.total+'</b>. Os outros '+(r.total-r.mostrando)
      + ' tem o mesmo motivo — resolver a causa acima resolve todos de uma vez.</div>';
    h+='</div>'; });
  return h;
}

/* ---- segurados ---- */
function envSeg(){ const ss=(ENV.d.segurados||[]);
  if(!ss.length) return '<div class="panel"><div class="dempty">Ninguem segurou nenhum pedido.</div></div>';
  let h='<div class="panel">'+cotSecHead('pan_tool','Segurados de proposito','continuam aqui, visiveis, ate alguem liberar - nao somem da fila','');
  ss.forEach(p=>{ h+='<div style="border:1px solid var(--line);border-radius:10px;padding:9px 12px;margin-bottom:7px">'
   + '<div class="bar" style="justify-content:space-between;gap:8px;flex-wrap:wrap">'
   + '<div><b>'+esc(p.numero)+'</b> - '+esc(p.obra||'—')+' - '+esc(p.forn_nome||'—')+' - '+BRL(p.valor)+'</div>'
   + '<button class="btn-ghost" style="padding:4px 11px" onclick="envLiberar(\''+p.coligada_cod+'\',\''+p.numero+'\')">Liberar</button></div>'
   + '<div class="dmini" style="margin-top:4px">Motivo: '+esc(p.motivo||'—')+(p.por?(' - '+esc(p.por)):'')+'</div></div>'; });
  return h+'</div>';
}

/* ---- historico: o livro-caixa ---- */
async function envHist(){ const w=document.getElementById('envHistWrap'); if(!w) return;
  let d; try{ d=await (await fetch('actions/envio.php?historico=1&me='+envMe()+'&_='+Date.now())).json(); }
  catch(e){ w.innerHTML='<div class="empty">Falha ao carregar.</div>'; return; }
  if(!(d.itens||[]).length){ w.innerHTML=cotSecHead('history','Historico de envios','todo disparo fica registrado aqui: para quem, quando, por quem e com quais anexos','')
    + '<div class="dempty">Nenhum envio registrado ainda. Assim que o primeiro sair, ele aparece aqui e <b>nunca mais</b> volta para a fila.</div>'; return; }
  let h=cotSecHead('history','Historico de envios','todo disparo fica registrado: para quem, quando, por quem e com quais anexos','<span class="dchip" style="background:var(--muted)">'+d.total+' registro(s)</span>');
  h+='<div style="overflow-x:auto"><table class="dtable" style="width:100%;font-size:12.5px"><thead><tr>'
   + '<th style="text-align:left">Quando</th><th style="text-align:left">Pedido</th><th style="text-align:left">Obra</th>'
   + '<th style="text-align:left">Para</th><th style="text-align:left">Enviado por</th><th style="text-align:right">Valor</th></tr></thead><tbody>';
  (d.itens||[]).forEach(r=>{ let q=r.enviado_em; try{ q=new Date(r.enviado_em).toLocaleString('pt-BR'); }catch(e){}
    h+='<tr><td>'+esc(q)+'</td><td><b>'+esc(r.pedido_numero)+'</b></td><td>'+esc(r.obra_nome||'—')+'</td>'
     + '<td>'+esc(r.para||'—')+'</td><td>'+esc(r.enviado_por_nome||r.enviado_por||'—')+'</td>'
     + '<td style="text-align:right">'+BRL(r.valor)+'</td></tr>'; });
  w.innerHTML=h+'</tbody></table></div>';
}

/* ---- previa: chama o MESMO compositor do servidor que vai montar o disparo ---- */
async function envVerEmail(ch){ const e=envAchar(ch); if(!e) return;
  toast('Montando o e-mail...');
  const pcs=(e.pedidos||[]).map(p=>p.numero).join(',');
  const u='actions/envio_config.php?previa='+e.ficha_id+'&tipo='+(e.destino==='obra'?'obra':'fornecedor')
        + '&pcs='+encodeURIComponent(pcs)+'&fornecedor='+encodeURIComponent(e.forn_nome)
        + '&comprador='+encodeURIComponent(e.assina||'')+'&me='+envMe();   // assina quem esta logado
  let p; try{ p=await (await fetch(u)).json(); }catch(err){ toast('Falha: '+err.message); return; }
  if(p.error){ toast(p.error); return; }
  dlgAbrir('Envio de Pedidos - '+esc(e.obra), 'Previa do e-mail',
    '<div class="dmini" style="margin-bottom:3px">Para</div>'
   + '<div style="border:1px solid var(--line);border-radius:8px;padding:7px 11px;font-size:13px;background:#f8faf9;margin-bottom:8px">'+esc(e.para)+'</div>'
   + '<div class="dmini" style="margin-bottom:3px">Com copia</div>'
   + '<div style="border:1px solid var(--line);border-radius:8px;padding:7px 11px;font-size:12.5px;background:#f8faf9;margin-bottom:8px">'+((p.cc||[]).length?esc(p.cc.join(', ')):'<span style="color:var(--muted)">ninguem</span>')+'</div>'
   + '<div class="dmini" style="margin-bottom:3px">Assunto</div>'
   + '<div style="border:1px solid var(--line);border-radius:8px;padding:7px 11px;font-size:13px;background:#f8faf9;margin-bottom:8px"><b>'+esc(p.assunto||'')+'</b></div>'
   /* O estado do anexo e POR PEDIDO — a versao anterior escrevia "ainda nao anexado" fixo, entao
      um PDF ja gerado aparecia como faltando. */
   + '<div class="dmini" style="margin-bottom:3px">Anexos</div>'
   + '<div style="border:1px solid var(--line);border-radius:8px;padding:7px 11px;font-size:12.5px;background:#f8faf9;margin-bottom:8px">'
   + (e.pedidos||[]).map(x=> x.tem_pdf
        ? ('<a href="actions/envio.php?baixar_pdf='+encodeURIComponent(x.coligada_cod+'|'+x.numero)+'&me='+envMe()
           +'" target="_blank" style="color:var(--verde-d);text-decoration:none">&#10003; PC '+esc(x.numero)+'.pdf</a>')
        : ('<span style="color:#8e44ad">&#9679; PC '+esc(x.numero)+'.pdf (falta gerar)</span>')).join(' &nbsp;&middot;&nbsp; ')
   + ((e.sem_pdf||0) ? ' <button class="btn-ghost" style="padding:2px 9px;font-size:11px;margin-left:6px" onclick="closeModal(true);envGerarPdfLote(\''+e.chave+'\')">Gerar os que faltam</button>' : '')
   + '</div>'
   + '<div class="dmini" style="margin-bottom:3px">Mensagem</div>'
   + '<div style="border:1px solid var(--line);border-radius:10px;padding:16px 18px;background:#fff">'+p.html+'</div>'
   + '<div class="bar" style="justify-content:flex-end;gap:8px;margin-top:14px">'
   + '<button class="btn-ghost" onclick="closeModal(true)">Fechar</button>'
   + '<button class="btn-prim" onclick="closeModal(true);envEnviar(\''+ch+'\')">Enviar este e-mail</button></div>');
}

/* ---- decisoes humanas: sempre com motivo, sempre com nome ---- */
function envDecidir(ch,dec){ const e=envAchar(ch); if(!e) return;
  const TIT={segurar:'Segurar estes pedidos', so_obra:'Marcar como regularizacao', arquivado:'Arquivar estes pedidos'};
  const TXT={segurar:'Eles continuam visiveis na aba <b>Segurados</b> ate alguem liberar. Nao somem da fila.',
             so_obra:'O e-mail vai <b>so para a obra</b>, para lancamento. O fornecedor nao recebe nada - e a trava do material que ja foi entregue.',
             arquivado:'Somem da fila e das contagens, mas <b>nada e apagado</b>: o pedido continua no TOTVS e volta pela aba <b>Arquivados</b>.'};
  const tit=TIT[dec]||'Decidir', txt=TXT[dec]||'';
  dlgAbrir('Envio de Pedidos - '+esc(e.obra), tit,
    '<div style="max-width:520px"><div class="dmini" style="margin-bottom:10px">'+txt+'</div>'
   + '<div style="border:1px solid var(--line);border-radius:8px;padding:8px 11px;font-size:12.5px;background:#f8faf9;margin-bottom:10px">'
   + 'Pedido(s) <b>'+esc((e.pedidos||[]).map(p=>p.numero).join(', '))+'</b> - '+esc(e.obra)+' - '+esc(e.forn_nome)+'</div>'
   + cotFld('Motivo (fica registrado com o seu nome)','<textarea id="envMot" rows="3" style="width:100%;resize:vertical" placeholder="ex.: material ja entregue em 12/07, pedido so para lancamento"></textarea>')
   + '<div class="bar" style="justify-content:flex-end;gap:8px;margin-top:14px">'
   + '<button class="btn-ghost" onclick="closeModal(true)">Cancelar</button>'
   + '<button class="btn-prim" onclick="envDecidirSalvar(\''+ch+'\',\''+dec+'\')">Confirmar</button></div></div>');
}
async function envDecidirSalvar(ch,dec){ const e=envAchar(ch); if(!e) return;
  const m=((document.getElementById('envMot')||{}).value||'').trim();
  if(!m){ toast('Escreva o motivo — ele fica registrado'); return; }
  let erro='';
  for(const p of (e.pedidos||[])){
    try{ const r=await (await fetch('actions/envio.php',{method:'POST',headers:{'Content-Type':'application/json'},
      body:JSON.stringify({acao:'decidir',me:(EU&&EU.bitrix_id),me_nome:(EU&&EU.nome)||'',decisao:dec,
                           coligada:p.coligada_cod,numero:p.numero,motivo:m})})).json();
      if(r.error) erro=r.error; }catch(err){ erro=err.message; } }
  closeModal(true);
  if(erro){ toast(erro); return; }
  toast(dec==='segurar'?'Pedidos segurados':'Marcado para ir so a obra');
  ENV.d=null; envCarregar();
}
async function envLiberar(col,num){
  try{ await fetch('actions/envio.php',{method:'POST',headers:{'Content-Type':'application/json'},
       body:JSON.stringify({acao:'decidir',me:(EU&&EU.bitrix_id),decisao:'liberar',coligada:col,numero:num})});
    toast('Liberado — voltou para a fila'); ENV.d=null; envCarregar(); }catch(e){ toast('Falha: '+e.message); }
}

/* ---- O DISPARO: tela de CONFERENCIA ----
   Clicar em Enviar abre o e-mail inteiro, do jeito que vai sair: destinatario, copia, assunto, os
   anexos (abriveis) e a mensagem renderizada com a assinatura. Os tres primeiros sao EDITAVEIS —
   o contato do fornecedor muda, as vezes se quer incluir o engenheiro nesta compra.

   O servidor reconfere tudo de novo e grava no livro-caixa o endereco que SAIU, nao o que estava
   configurado: "para quem foi este pedido?" nao pode ter duas respostas. */
async function envEnviar(ch){
  const e=envAchar(ch); if(!e) return;
  if((e.sem_pdf||0)>0){ toast('Faltam '+e.sem_pdf+' PDF(s) — gere antes de enviar'); return; }

  dlgAbrir('Envio de Pedidos - '+esc(e.obra),'Conferir e enviar','<div class="dempty">Montando o e-mail…</div>');
  const pcs=(e.pedidos||[]).map(p=>p.numero).join(',');
  const u='actions/envio_config.php?previa='+e.ficha_id+'&tipo='+(e.destino==='obra'?'obra':'fornecedor')
        + '&pcs='+encodeURIComponent(pcs)+'&fornecedor='+encodeURIComponent(e.forn_nome)
        /* A sigla vem PRONTA do servidor. Montar aqui a partir de e.pedidos[0].coligada dava vazio
           (a base nao preenche o nome da coligada) e o assunto saia "Pedido de Compra -  - Diamond".
           E como esta tela devolve o assunto como override, o furo ia junto no envio. */
        + '&sigla='+encodeURIComponent((e.pedidos[0]||{}).coligada_sigla||'')
        + '&me='+envMe();
  let p; try{ p=await (await fetch(u)).json(); }catch(err){ toast('Falha: '+err.message); closeModal(true); return; }
  if(p.error){ toast(p.error); closeModal(true); return; }

  const rot=t=>'<div class="dmini" style="margin:10px 0 3px;font-weight:600">'+t+'</div>';
  let h='<div style="max-width:780px">';
  if(e.alerta||e.forn_travado) h+='<div style="border-left:4px solid #c0392b;background:#fdf1ef;padding:9px 12px;'
   + 'border-radius:0 8px 8px 0;font-size:12.5px;margin-bottom:10px">'
   + (e.alerta?'<b>A descrição indica material já em obra.</b> ':'<b>A observação traz o CNPJ do fornecedor.</b> ')
   + 'Se for regularização, feche e use <b>Só para a obra</b> — o fornecedor pode entregar de novo.</div>';

  h+='<div style="display:grid;grid-template-columns:1fr 1fr;gap:10px">'
   + '<div>'+rot('Para')+'<input id="envCPara" value="'+esc(e.para)+'" style="width:100%"></div>'
   + '<div>'+rot('Obra / pedidos')+'<div style="padding:6px 10px;border:1px solid var(--line);border-radius:8px;'
   + 'background:#f8faf9;font-size:12.5px"><b>'+esc(e.obra)+'</b> &middot; PC '+esc((e.pedidos||[]).map(x=>x.numero).join(', '))
   + ' &middot; '+BRL(e.valor)+'</div></div></div>';
  h+=rot('Com cópia (separe por vírgula)')
   + '<input id="envCCc" value="'+esc((p.cc||[]).join(', '))+'" style="width:100%">';
  h+=rot('Assunto')+'<input id="envCAss" value="'+esc(p.assunto||'')+'" style="width:100%">';
  h+=rot('Anexos')
   + '<div style="border:1px solid var(--line);border-radius:8px;padding:7px 11px;background:#f8faf9;font-size:12.5px">'
   + (e.pedidos||[]).map(x=>'<a href="actions/envio.php?baixar_pdf='+encodeURIComponent(x.coligada_cod+'|'+x.numero)
       + '&me='+envMe()+'" target="_blank" style="color:var(--verde-d);text-decoration:none;margin-right:14px">'
       + '<span class="material-icons" style="font-size:14px;vertical-align:-3px">picture_as_pdf</span> PC '
       + esc(x.numero)+'.pdf</a>').join('')
   + '</div>';
  h+=rot('Mensagem (com a sua assinatura)')
   + '<div style="border:1px solid var(--line);border-radius:10px;padding:16px 18px;background:#fff;max-height:340px;overflow:auto">'
   + p.html+'</div>';
  h+='<div id="envCMsg" class="dmini" style="margin-top:10px"></div>';
  h+='<div class="bar" style="justify-content:space-between;gap:8px;margin-top:12px;flex-wrap:wrap">'
   + '<span class="dmini" style="color:var(--muted)">Depois de enviado, estes pedidos entram no livro-caixa e '
   + '<b>não voltam</b> para a fila.</span>'
   + '<span class="bar" style="gap:8px"><button class="btn-ghost" onclick="closeModal(true)">Cancelar</button>'
   + '<button class="btn-prim" id="envCBtn" onclick="envEnviarConfirmado('+jsArg(ch)+',0)">'
   + '<span class="material-icons" style="font-size:15px;vertical-align:-3px">send</span> Enviar agora</button></span></div></div>';

  dlgAbrir('Envio de Pedidos - '+esc(e.obra),'Conferir e enviar', h);
}
async function envEnviarConfirmado(ch, aceitaContaGeral){
  const b=document.getElementById('envCBtn'), m=document.getElementById('envCMsg');
  const g=x=>((document.getElementById(x)||{}).value||'').trim();
  const para=g('envCPara');
  if(!para || para.indexOf('@')<0){ if(m) m.innerHTML='<span style="color:var(--pend)">Destinatário inválido.</span>'; return; }
  if(b){ b.disabled=true; b.textContent='Enviando...'; }
  try{ const r=await (await fetch('actions/envio.php',{method:'POST',headers:{'Content-Type':'application/json'},
       body:JSON.stringify({acao:'enviar',me:(EU&&EU.bitrix_id),me_nome:(EU&&EU.nome)||'',
                            envelope:ch, para:para, cc:g('envCCc'), assunto:g('envCAss'),
                            aceito_conta_geral:aceitaContaGeral?1:0})})).json();
    if(r.error){
      if(b){ b.disabled=false; b.innerHTML='<span class="material-icons" style="font-size:15px;vertical-align:-3px">send</span> Enviar agora'; }
      if(String(r.error).indexOf('CONTA_GERAL:')===0){
        if(m) m.innerHTML='<div style="border-left:4px solid var(--dourado);background:#fdf9ec;padding:9px 12px;border-radius:0 8px 8px 0">'
          + esc(String(r.error).slice(12))+'</div>';
        if(b) b.setAttribute('onclick','envEnviarConfirmado('+jsArg(ch)+',1)');
        return;
      }
      if(m) m.innerHTML='<span style="color:var(--pend)">'+esc(r.error)+'</span>';
      return;
    }
    closeModal(true);
    toast('Enviado para '+r.para+' — '+r.pedidos+' pedido(s), '+r.anexos+' anexo(s)');
    ENV.sel={}; ENV.d=null; envCarregar();
  }catch(err){
    if(b){ b.disabled=false; b.textContent='Enviar agora'; }
    if(m) m.innerHTML='<span style="color:var(--pend)">Falha: '+esc(err.message)+'</span>';
  }
}
/* ---- ENVIO DE TESTE ----
   Unico lugar do sistema que dispara e-mail de verdade hoje, e mesmo assim so para um endereco
   DIGITADO na hora — nunca escolhido de uma lista de fornecedores, para que um clique errado nao
   alcance ninguem. Nao entra no livro-caixa: se entrasse, "queimaria" o pedido e a regra 4
   (nunca deixar de enviar um aprovado) quebraria calada la na frente. */
function pmTesteForm(){ const o=(PM.d.obras||[]).find(x=>String(x.id)===String(PM.obra))||{};
  dlgAbrir('E-mail do pedido','Enviar um teste',
    '<div style="max-width:560px">'
   + '<div style="border-left:4px solid var(--dourado);background:#fdf9ec;padding:9px 12px;border-radius:0 8px 8px 0;font-size:12.5px;margin-bottom:12px">'
   + 'Vai sair um e-mail <b>de verdade</b> para o endereco abaixo, com o texto ja montado para a obra '
   + '<b>'+esc(o.nome||'')+'</b> e um PDF de exemplo anexado. O assunto e o corpo saem carimbados como TESTE, '
   + 'e <b>nada</b> e gravado no historico de envios.</div>'
   + cotFld('Enviar para (digite o endereco)','<input id="pmTPara" placeholder="voce@empresa.com.br" style="width:100%">')
   + '<div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-top:9px">'
   + cotFld('Nome do fornecedor (aparece no texto)','<input id="pmTForn" placeholder="ex.: Murilo Mello Servicos" style="width:100%">')
   + cotFld('Numeros de pedido de exemplo','<input id="pmTPcs" value="9001,9002" style="width:100%">')
   + '</div>'
   + '<div class="bar" style="gap:16px;margin-top:10px;font-size:13px">'
   + '<label style="display:flex;gap:6px;align-items:center"><input type="checkbox" id="pmTAnexo" checked> Anexar um PDF por pedido</label>'
   + '<label style="display:flex;gap:6px;align-items:center"><input type="checkbox" id="pmTObra"> Usar o texto de <b>lancamento</b> (obra) em vez do de fornecedor</label>'
   + '</div>'
   + '<div class="bar" style="justify-content:flex-end;gap:8px;margin-top:14px">'
   + '<button class="btn-ghost" onclick="closeModal(true)">Cancelar</button>'
   + '<button class="btn-prim" onclick="pmTesteEnviar()">Enviar o teste</button></div></div>');
}
async function pmTesteEnviar(){ const g=x=>((document.getElementById(x)||{}).value||'').trim();
  const para=g('pmTPara');
  if(!para || para.indexOf('@')<0){ toast('Digite o e-mail de destino'); return; }
  toast('Enviando o teste...');
  try{ const r=await (await fetch('actions/envio.php',{method:'POST',headers:{'Content-Type':'application/json'},
       body:JSON.stringify({acao:'teste', me:(EU&&EU.bitrix_id), me_nome:(EU&&EU.nome)||'',
         obra_id:PM.obra, para:para, fornecedor:g('pmTForn'), pcs:g('pmTPcs'),
         tipo:((document.getElementById('pmTObra')||{}).checked?'obra':'fornecedor'),
         com_anexo:((document.getElementById('pmTAnexo')||{}).checked?1:0)})})).json();
    if(r.error){ toast(r.error); return; }
    closeModal(true);
    if(!r.ok){ toast('Nao saiu: '+(r.erro||'falha no servidor de e-mail')); return; }
    let m='Teste enviado para '+r.para+(r.anexos?(' com '+r.anexos+' anexo(s)'):' sem anexo');
    toast(m);
    if((r.faltando||[]).length) setTimeout(()=>toast('Atencao: esta obra ainda nao tem '+r.faltando.join(', ')),2600);
  }catch(e){ toast('Falha: '+e.message); }
}

/* Modal generico reaproveitando o overlay que ja existe (o mesmo do card do radar). */
function dlgAbrir(crumb, titulo, corpo){
  document.getElementById('modal').innerHTML =
    '<div class="mhead"><button class="mclose" onclick="closeModal(true)">&times;</button>'
    + '<div class="crumb">'+crumb+'</div><div class="mt">'+titulo+'</div></div>'
    + '<div class="tabbody">'+corpo+'</div>';
  document.getElementById('ov').classList.add('open');
}

/* ===================== E-MAIL DO PEDIDO DE COMPRA (Configuracoes) =====================
   O que os compradores mandam hoje e um texto colado a mao, e-mail a e-mail. Foi assim que
   nasceram os defeitos que achamos na caixa: paragrafo de faturamento repetido, link do ISSQN
   com o prefixo do leitor de PDF do Chrome grudado na frente, e regra nova que entrou em
   algumas obras e nao em outras.

   Esta tela troca isso por QUATRO CAMADAS, e a regra e sempre a mesma: campo vazio HERDA de cima.
     Padrao    -> vale para todas as obras
     Avisos    -> texto com data de inicio/fim, entra e sai sozinho (bloqueio contabil dos dias 25-31)
     Cidade    -> o que muda por municipio (aliquota de ISSQN)
     Obra      -> CNO, endereco, contatos, e-mail de NF, copias

   "Mudar para todos" = mexer no Padrao. "Mudar so nessa obra" = preencher na Obra.
   A previa nao e uma imitacao: ela chama o MESMO compositor do servidor que vai montar o envio. */
let PM={d:null, sub:'padrao', cidade:'', obra:'', previa:null, tipo:'fornecedor'};
function pmMe(){ return encodeURIComponent((EU&&EU.bitrix_id)||''); }
async function pmLoad(){ const w=document.getElementById('cfgPedMailWrap'); if(!w) return;
  w.innerHTML='<div class="dempty">Carregando a configuracao dos e-mails...</div>';
  try{ PM.d=await (await fetch('actions/envio_config.php?me='+pmMe()+'&_='+Date.now())).json(); }
  catch(e){ w.innerHTML='<div class="panel"><div class="empty">Falha ao carregar.</div></div>'; return; }
  if(PM.d.error){ w.innerHTML='<div class="panel"><div class="empty">'+esc(PM.d.error)+'</div></div>'; return; }
  if(!PM.cidade) PM.cidade=(PM.d.cidades||[])[0]||'';
  if(!PM.obra) PM.obra=String(((PM.d.obras||[])[0]||{}).id||'');
  pmRender();
}
function pmSubBtn(k,ic,lbl){ const on=PM.sub===k;
  return '<button class="btn-ghost" onclick="pmSub(\''+k+'\')" style="padding:6px 13px'+(on?';background:var(--verde);color:#fff':'')+'"><span class="material-icons" style="font-size:16px;vertical-align:-4px">'+ic+'</span> '+lbl+'</button>'; }
function pmSub(k){ PM.sub=k; PM.previa=null; pmRender(); }
function pmArea(escopo,campo,val,ph,linhas){ const id='pm_'+escopo+'_'+campo;
  return (linhas>1? '<textarea id="'+id+'" rows="'+linhas+'" placeholder="'+esc(ph||'')+'" style="width:100%;resize:vertical">'+esc(val||'')+'</textarea>'
                  : '<input id="'+id+'" value="'+esc(val||'')+'" placeholder="'+esc(ph||'')+'" style="width:100%">'); }

function pmRender(){ const w=document.getElementById('cfgPedMailWrap'), d=PM.d; if(!w||!d) return;
  let corpo='';
  if(PM.sub==='padrao') corpo=pmPadrao();
  else if(PM.sub==='avisos') corpo=pmAvisos();
  else if(PM.sub==='cidade') corpo=pmCidade();
  else if(PM.sub==='assin') corpo=pmAssin();
  else if(PM.sub==='conta') corpo='<div class="panel" id="pmContaWrap"><div class="dempty">Carregando...</div></div>';
  else corpo=pmObra();
  if(PM.sub==='conta') setTimeout(pmConta,0);
  w.innerHTML='<div class="panel" style="padding-bottom:10px">'
    + cotSecHead('mark_email_read','E-mail do pedido de compra','o texto que sai junto com o PDF — muda para todas as obras de uma vez, ou so para uma','')
    + '<div class="bar" style="gap:6px;margin:2px 0 0">'
    + pmSubBtn('padrao','public','Padrao (todas)') + pmSubBtn('avisos','campaign','Avisos com prazo')
    + pmSubBtn('cidade','location_city','Por cidade') + pmSubBtn('obra','apartment','Por obra')
    + pmSubBtn('assin','draw','Assinaturas') + pmSubBtn('conta','alternate_email','Conta de envio')
    + '</div></div>' + corpo;
}

/* ---- camada 1: padrao ---- */
function pmPadrao(){ const c=PM.d.config.global||{}, campos=PM.d.campos.global;
  const linhas={faturamento:3, combinar:2, abertura_forn:2, abertura_obra:2, cno:2, copia_nf:2, atraso:2};
  let h='<div class="panel">'+cotSecHead('public','Texto padrao','vale para TODAS as obras — mexer aqui muda o e-mail de todo mundo na proxima rodada','<button class="btn-prim" onclick="pmSalvar(\'global\',\'\')" style="padding:5px 13px"><span class="material-icons" style="font-size:15px;vertical-align:-3px">save</span> Salvar padrao</button>');
  h+='<div class="dmini" style="margin:-2px 0 10px">Use <b>{obra}</b>, <b>{cno}</b>, <b>{almox_nome}</b>, <b>{almox_fone}</b>, <b>{email_nf}</b>, <b>{pcs}</b>, <b>{sigla}</b>, <b>{comprador}</b> — o sistema troca na hora do envio. Se o campo da obra estiver vazio, a linha inteira nao sai (nao manda "CNO da obra" em branco).</div>';
  h+='<div style="display:grid;grid-template-columns:1fr 1fr;gap:10px 14px">';
  Object.keys(campos).forEach((k,i)=>{ const meta=campos[k], n=linhas[k]||1;
    const largo=(n>1||k==='cc_fixo'||k==='assinatura_img');
    h+='<div style="'+(largo?'grid-column:1/-1':'')+'">'+cotFld(meta[0], pmArea('global',k,c[k],meta[1],n))+'</div>'; });
  h+='</div></div>';
  return h;
}

/* ---- camada 2: avisos com vigencia ---- */
function pmAvisoEstado(a,hoje){ if(!Number(a.ativo)) return ['desligado','var(--muted)'];
  if(a.de && a.de>hoje) return ['comeca em '+pmData(a.de),'var(--pend)'];
  if(a.ate && a.ate<hoje) return ['venceu em '+pmData(a.ate),'var(--muted)'];
  return ['no ar agora','var(--ok)']; }
function pmData(s){ if(!s) return ''; const p=String(s).split('-'); return p.length===3?(p[2]+'/'+p[1]+'/'+p[0]):s; }
function pmAvisos(){ const hoje=PM.d.hoje;
  let h='<div class="panel">'+cotSecHead('campaign','Avisos com prazo','texto que entra e sai sozinho na data — o bloqueio contabil dos dias 25 a 31 e o caso classico','<button class="btn-prim" onclick="pmAvisoNovo()" style="padding:5px 13px"><span class="material-icons" style="font-size:15px;vertical-align:-3px">add</span> Novo aviso</button>');
  const av=PM.d.avisos||[];
  if(!av.length) h+='<div class="dempty">Nenhum aviso. Enquanto nao houver, o e-mail sai so com o texto padrao.</div>';
  av.forEach(a=>{ const st=pmAvisoEstado(a,hoje);
    h+='<div style="border:1px solid var(--line);border-radius:10px;padding:10px 12px;margin-bottom:8px;background:'+(String(a.destaque)==='forte'?'#fdf1ef':'#fff')+'">'
     + '<div class="bar" style="justify-content:space-between;gap:8px"><b style="font-size:13.5px">'+esc(a.titulo||'(sem titulo)')+'</b>'
     + '<span class="bar" style="gap:6px"><span class="dchip" style="background:'+st[1]+'">'+st[0]+'</span>'
     + '<button class="btn-ghost" style="padding:3px 9px" onclick="pmAvisoForm('+a.id+')">Editar</button>'
     + '<button class="btn-ghost" style="padding:3px 9px;color:var(--pend)" onclick="pmAvisoExcluir('+a.id+')">Excluir</button></span></div>'
     + '<div class="dmini" style="margin-top:5px;white-space:pre-wrap">'+esc(a.texto||'')+'</div>'
     + '<div class="dmini" style="margin-top:5px;color:var(--muted)">'+(a.de||a.ate?('vigencia: '+(a.de?pmData(a.de):'sempre')+' ate '+(a.ate?pmData(a.ate):'sem fim')):'sem data — fica no ar ate desligar')+'</div></div>'; });
  h+='</div>';
  return h;
}
function pmAvisoNovo(){ pmAvisoForm(0); }
function pmAvisoForm(id){ const a=(PM.d.avisos||[]).find(x=>Number(x.id)===Number(id))||{ativo:1,destaque:'normal'};
  dlgAbrir('E-mail do pedido', (id?'Editar aviso':'Novo aviso'), '<div style="max-width:560px">'+cotSecHead('campaign','Vigencia do aviso','ele entra e sai do e-mail sozinho, nas datas abaixo','')
   + cotFld('Titulo','<input id="pmavT" value="'+esc(a.titulo||'')+'" placeholder="ex.: Bloqueio contabil — emissao de nota fiscal" style="width:100%">')
   + '<div style="margin-top:8px">'+cotFld('Texto','<textarea id="pmavX" rows="4" style="width:100%;resize:vertical" placeholder="ex.: Nao emitir nota fiscal entre os dias 25 e 31. Notas so entre os dias 1 e 24.">'+esc(a.texto||'')+'</textarea>')+'</div>'
   + '<div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-top:8px">'
   + cotFld('Comeca em (vazio = ja)','<input id="pmavD" type="date" value="'+esc(a.de||'')+'" style="width:100%">')
   + cotFld('Termina em (vazio = sem fim)','<input id="pmavA" type="date" value="'+esc(a.ate||'')+'" style="width:100%">')+'</div>'
   + '<div class="bar" style="gap:16px;margin-top:10px;font-size:13px">'
   + '<label style="display:flex;gap:6px;align-items:center"><input type="checkbox" id="pmavF" '+(String(a.destaque)==='forte'?'checked':'')+'> Destacar em vermelho</label>'
   + '<label style="display:flex;gap:6px;align-items:center"><input type="checkbox" id="pmavO" '+(Number(a.ativo)!==0?'checked':'')+'> Ativo</label></div>'
   + '<div class="bar" style="justify-content:flex-end;gap:8px;margin-top:14px"><button class="btn-ghost" onclick="closeModal(true)">Cancelar</button>'
   + '<button class="btn-prim" onclick="pmAvisoSalvar('+(id||0)+')">Salvar aviso</button></div></div>');
}
async function pmAvisoSalvar(id){ const g=x=>((document.getElementById(x)||{}).value||'').trim();
  const t=g('pmavT'); if(!t){ toast('Coloque um titulo'); return; }
  const b={acao:'aviso_salvar', me:(EU&&EU.bitrix_id), id:id, titulo:t, texto:g('pmavX'), de:g('pmavD'), ate:g('pmavA'),
           destaque:(document.getElementById('pmavF')||{}).checked?'forte':'normal', ativo:(document.getElementById('pmavO')||{}).checked?1:0};
  try{ const r=await (await fetch('actions/envio_config.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(b)})).json();
    if(r.error){ toast(r.error); return; } closeModal(true); toast('Aviso salvo'); pmLoad(); }catch(e){ toast('Falha: '+e.message); } }
async function pmAvisoExcluir(id){ if(!confirm('Excluir este aviso? Ele sai dos proximos e-mails.')) return;
  try{ await fetch('actions/envio_config.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({acao:'aviso_excluir',me:(EU&&EU.bitrix_id),id:id})});
    toast('Aviso excluido'); pmLoad(); }catch(e){ toast('Falha: '+e.message); } }

/* ---- camada 3: cidade ---- */
function pmCidade(){ const cid=PM.cidade, c=(PM.d.config.cidade||{})[cid]||{}, campos=PM.d.campos.cidade;
  const obras=(PM.d.obras||[]).filter(o=>String(o.cidade||'').trim()===cid);
  let h='<div class="panel">'+cotSecHead('location_city','Por cidade','o que muda por municipio — a aliquota de ISSQN e o exemplo real','<button class="btn-prim" onclick="pmSalvar(\'cidade\',PM.cidade)" style="padding:5px 13px"><span class="material-icons" style="font-size:15px;vertical-align:-3px">save</span> Salvar cidade</button>');
  h+='<div style="max-width:300px">'+cotFld('Cidade','<select id="pmCidSel" onchange="PM.cidade=this.value;pmRender()" style="width:100%">'
    + (PM.d.cidades||[]).map(x=>'<option value="'+esc(x)+'"'+(x===cid?' selected':'')+'>'+esc(x)+'</option>').join('')+'</select>')+'</div>';
  h+='<div class="dmini" style="margin:8px 0 4px">Vale para <b>'+obras.length+'</b> obra(s): '+esc(obras.map(o=>o.nome).join(', ')||'nenhuma')+'</div>';
  Object.keys(campos).forEach(k=>{ h+='<div style="margin-top:8px">'+cotFld(campos[k][0], pmArea('cidade',k,c[k],campos[k][1],3))+'</div>'; });
  h+='<div class="dmini" style="margin-top:6px;color:var(--muted)">Pode colar o link da lei junto do texto — o sistema transforma em link e limpa o prefixo <code>chrome-extension://</code> que hoje vai colado por engano.</div>';
  h+='</div>';
  return h;
}

/* ---- camada 4: obra ---- */
function pmObra(){ const oid=String(PM.obra), c=(PM.d.config.obra||{})[oid]||{}, campos=PM.d.campos.obra;
  const o=(PM.d.obras||[]).find(x=>String(x.id)===oid)||{};
  const linhas={complemento:2};
  let h='<div class="panel">'+cotSecHead('apartment','Por obra','CNO, endereco, contatos e copias — campo vazio herda o padrao','<button class="btn-prim" onclick="pmSalvar(\'obra\',PM.obra)" style="padding:5px 13px"><span class="material-icons" style="font-size:15px;vertical-align:-3px">save</span> Salvar obra</button>');
  h+='<div class="bar" style="gap:10px;align-items:flex-end"><div style="flex:1;max-width:340px">'
   + cotFld('Obra','<select id="pmObraSel" onchange="PM.obra=this.value;PM.previa=null;pmRender()" style="width:100%">'
   + (PM.d.obras||[]).map(x=>'<option value="'+x.id+'"'+(String(x.id)===oid?' selected':'')+'>'+esc(x.nome)+(x.cidade?' — '+esc(x.cidade):'')+'</option>').join('')+'</select>')+'</div>'
   + '<button class="btn-ghost" onclick="pmPreviaVer()" style="padding:6px 13px"><span class="material-icons" style="font-size:16px;vertical-align:-4px">visibility</span> Ver o e-mail desta obra</button>'
   + '<button class="btn-ghost" onclick="pmTesteForm()" style="padding:6px 13px"><span class="material-icons" style="font-size:16px;vertical-align:-4px">forward_to_inbox</span> Enviar um teste</button></div>';
  h+='<div style="display:grid;grid-template-columns:1fr 1fr;gap:10px 14px;margin-top:10px">';
  Object.keys(campos).forEach(k=>{ const n=linhas[k]||1, largo=(n>1||k==='endereco'||k==='cc_obra');
    const herda=(k==='horario'&&!(c[k]||'').trim());
    h+='<div style="'+(largo?'grid-column:1/-1':'')+'">'+cotFld(campos[k][0]+(herda?' <span class="dmini" style="color:var(--muted)">(herdando o padrao)</span>':''), pmArea('obra',k,c[k],campos[k][1],n))+'</div>'; });
  h+='</div>';
  h+='<div class="dmini" style="margin-top:8px;color:var(--muted)">Endereco e mapa saem da <b>ficha da obra</b> quando estiverem vazios aqui — preencha nesta tela so se a ENTREGA for em lugar diferente do cadastro. Quem assina e o comprador responsavel da obra'+(o.nome?'':'')+', que ja vem do de-para das solicitacoes.</div>';
  h+='</div>';
  if(PM.previa) h+=pmPreviaBox();
  return h;
}
async function pmPreviaVer(){ toast('Montando o e-mail...');
  try{ const r=await (await fetch('actions/envio_config.php?previa='+encodeURIComponent(PM.obra)+'&tipo='+PM.tipo+'&me='+pmMe()+'&_='+Date.now())).json();
    if(r.error){ toast(r.error); return; } PM.previa=r; pmRender();
    const el=document.getElementById('pmPreviaBox'); if(el) el.scrollIntoView({behavior:'smooth',block:'start'}); }
  catch(e){ toast('Falha: '+e.message); } }
function pmPreviaTipo(t){ PM.tipo=t; pmPreviaVer(); }
function pmPreviaBox(){ const p=PM.previa;
  let h='<div class="panel" id="pmPreviaBox">'+cotSecHead('visibility','Previa do e-mail','montado pelo MESMO codigo que vai disparar — o que voce le aqui e o que o fornecedor recebe','');
  h+='<div class="bar" style="gap:6px;margin-bottom:10px">'
   + '<button class="btn-ghost" onclick="pmPreviaTipo(\'fornecedor\')" style="padding:5px 12px'+(PM.tipo==='fornecedor'?';background:var(--verde);color:#fff':'')+'">Para o fornecedor</button>'
   + '<button class="btn-ghost" onclick="pmPreviaTipo(\'obra\')" style="padding:5px 12px'+(PM.tipo==='obra'?';background:var(--verde);color:#fff':'')+'">Para a obra (lancamento)</button></div>';
  if((p.faltando||[]).length) h+='<div style="border-left:4px solid var(--pend);background:#fdf1ef;padding:8px 12px;border-radius:0 8px 8px 0;margin-bottom:10px;font-size:12.5px">Esta obra ainda nao pode entrar no envio automatico. Falta: <b>'+esc(p.faltando.join(', '))+'</b>.</div>';
  h+='<div class="dmini" style="margin-bottom:3px">Assunto</div><div style="border:1px solid var(--line);border-radius:8px;padding:7px 11px;font-size:13px;background:#f8faf9;margin-bottom:8px"><b>'+esc(p.assunto||'(vazio)')+'</b></div>';
  h+='<div class="dmini" style="margin-bottom:3px">Com copia para</div><div style="border:1px solid var(--line);border-radius:8px;padding:7px 11px;font-size:12.5px;background:#f8faf9;margin-bottom:8px">'+((p.cc||[]).length?esc(p.cc.join(', ')):'<span style="color:var(--muted)">ninguem</span>')+'</div>';
  h+='<div class="dmini" style="margin-bottom:3px">Mensagem</div><div style="border:1px solid var(--line);border-radius:10px;padding:16px 18px;background:#fff">'+p.html+'</div>';
  h+='<div class="dmini" style="margin-top:8px;color:var(--muted)">Os numeros de pedido e o nome do fornecedor sao de exemplo. Os anexos em PDF entram no disparo.</div>';
  h+='</div>';
  return h;
}
async function pmSalvar(escopo,ref){ const campos={}, defs=PM.d.campos[escopo]||{};
  Object.keys(defs).forEach(k=>{ const el=document.getElementById('pm_'+escopo+'_'+k); if(el) campos[k]=el.value; });
  try{ const r=await (await fetch('actions/envio_config.php',{method:'POST',headers:{'Content-Type':'application/json'},
        body:JSON.stringify({acao:'salvar',me:(EU&&EU.bitrix_id),escopo:escopo,ref:String(ref||''),campos:campos})})).json();
    if(r.error){ toast(r.error); return; }
    toast(escopo==='global'?'Padrao salvo — vale para todas as obras':'Salvo');
    const guardaSub=PM.sub, guardaObra=PM.obra, guardaCid=PM.cidade;
    await pmLoad(); PM.sub=guardaSub; PM.obra=guardaObra; PM.cidade=guardaCid;
    if(PM.previa) pmPreviaVer(); else pmRender();
  }catch(e){ toast('Falha: '+e.message); } }

/* ===== Responsáveis EM LOTE (Configurações) — atribui comprador por obra/grupo/seleção ===== */
let RL={obras:[], itens:[], sel:new Set()};
function rlObras(){ return (typeof OBRAS!=='undefined'&&OBRAS)?OBRAS.slice():[]; }
function rlObrasEdit(){   // obras que o usuário PODE editar (o endpoint exige can_edit_obra) — admin/'todas' = todas
  const all=rlObras();
  if(IS_ADMIN || (EU&&EU.editar_escopo==='todas')) return all;
  const ed=((EU&&EU.obras_editar)||[]).map(Number);
  return all.filter(o=>ed.includes(Number(o.id)));
}
function renderRespLote(){
  const list=rlObrasEdit();
  if(!list.length){ document.getElementById('rlwrap').innerHTML='<div class="empty">Você não tem obras liberadas para editar responsáveis. Peça ao administrador acesso de edição às obras.</div>';
    const k=document.getElementById('rlKpi'); if(k)k.innerHTML=''; const l=document.getElementById('rlObraLbl'); if(l)l.textContent='—'; return; }
  // default: TODAS as obras editáveis (o caso do usuário: atribuir um grupo em lote em todas)
  if(!RL.obras.length) RL.obras=list.map(o=>Number(o.id));
  else { RL.obras=RL.obras.filter(id=>list.some(o=>Number(o.id)===id)); if(!RL.obras.length) RL.obras=[Number(list[0].id)]; }
  const rs=document.getElementById('rlResp');
  if(rs) rs.innerHTML='<option value="">— escolher responsável —</option>'+(RESP||[]).map(u=>`<option value="${esc(u.nome)}">${esc(u.nome)}</option>`).join('');
  // "tornar padrão" é mudança GLOBAL (template) → só p/ admin ou quem edita TODAS as obras
  const pw=document.getElementById('rlPadraoWrap'); if(pw) pw.style.display=(IS_ADMIN||(CAN_RESP&&EU&&EU.editar_escopo==='todas'))?'':'none';
  rlObraLbl(); rlLoad();
}
// ---- dropdown MULTI-OBRA ----
function rlObraToggle(e){ if(e)e.stopPropagation(); const m=document.getElementById('rlObraMenu'); if(!m)return; const ab=m.style.display==='none'||!m.style.display; m.style.display=ab?'block':'none'; if(ab) rlObraMenu(); }
document.addEventListener('click',e=>{ const p=document.getElementById('rlObraPick'),m=document.getElementById('rlObraMenu'); if(m&&m.style.display==='block'&&p&&!p.contains(e.target)) m.style.display='none'; });
function rlObraMenu(){ const m=document.getElementById('rlObraMenu'); if(!m)return; const list=rlObrasEdit();
  m.innerHTML=`<div style="display:flex;justify-content:space-between;align-items:center;padding:2px 6px 6px;border-bottom:1px solid var(--line);margin-bottom:4px"><span style="font-size:10px;font-weight:800;letter-spacing:.6px;color:var(--muted)">SELECIONE AS OBRAS</span><button class="btn-ghost" style="padding:2px 8px;font-size:11px" onclick="rlObraTodas(event)">Todas</button></div>`+
    list.map(o=>{ const on=RL.obras.includes(Number(o.id));
      return `<label style="display:flex;align-items:center;gap:9px;padding:6px 8px;border-radius:7px;cursor:pointer;font-size:12.5px" onmouseover="this.style.background='#eff7f1'" onmouseout="this.style.background=''"><input type="checkbox" ${on?'checked':''} onchange="rlObraSet(${o.id},this.checked)"><span style="width:9px;height:9px;border-radius:50%;background:${obraCor(o.id)};flex:0 0 auto"></span><span style="flex:1"><b>${esc(o.nome)}</b></span></label>`; }).join('');
}
function rlObraSet(id,on){ id=Number(id);
  if(on){ if(!RL.obras.includes(id)) RL.obras.push(id); }
  else { RL.obras=RL.obras.filter(x=>x!==id); if(!RL.obras.length){ toast('Ao menos uma obra'); RL.obras=[id]; rlObraMenu(); return; } }
  rlObraLbl(); rlObraMenu(); rlLoad();
}
function rlObraTodas(e){ if(e)e.stopPropagation(); RL.obras=rlObrasEdit().map(o=>Number(o.id)); rlObraLbl(); rlObraMenu(); rlLoad(); }
function rlObraLbl(){ const l=document.getElementById('rlObraLbl'); if(!l)return; const list=rlObrasEdit();
  if(RL.obras.length>=list.length) l.textContent='Todas as obras';
  else if(RL.obras.length===1){ const o=list.find(x=>Number(x.id)===RL.obras[0]); l.textContent=o?o.nome:'1 obra'; }
  else l.textContent=RL.obras.length+' obras'; }
async function rlLoad(){
  RL.sel=new Set(); const box=document.getElementById('rlwrap'); box.innerHTML='<div class="empty">Carregando…</div>';
  try{
    const onome={}; rlObrasEdit().forEach(o=>onome[Number(o.id)]=o.nome);
    const rs=await Promise.all(RL.obras.map(async oid=>{ const u='actions/matriz.php'+(oid!==1?('?obra='+oid+'&'):'?')+'_='+Date.now(); const d=await (await fetch(u)).json().catch(()=>null); return {oid,d}; }));
    const itens=[];
    for(const {oid,d} of rs){ if(!d||d.error||!d.itens) continue;
      d.itens.forEach(i=>itens.push({obra_id:oid, obra_nome:(d.obra&&d.obra.nome)||onome[oid]||('obra '+oid), ordem:i.ordem, nome:i.nome, grupo:i.grupo, curva:i.curva, responsavel:(i.responsavel||'').trim(), padrao:(i.responsavel_padrao||'').trim(), temData:!!(i.data_necessaria||i.fim_cotacao)})); }
    RL.itens=itens;
    const g=document.getElementById('rlGrupo'), keep=g.value;
    g.innerHTML='<option value="">Todos os grupos</option>'+[...new Set(itens.map(i=>i.grupo).filter(Boolean))].sort().map(x=>`<option>${esc(x)}</option>`).join(''); if(keep) g.value=keep;
    rlRender();
  }catch(e){ box.innerHTML='<div class="empty">Falha: '+esc(e.message)+'</div>'; }
}
function rlKey(i){ return i.obra_id+':'+i.ordem; }
function rlFiltered(){
  const fg=val('rlGrupo'), fs=val('rlStatus'), q=(document.getElementById('rlQ').value||'').toLowerCase();
  return RL.itens.filter(i=>(!fg||i.grupo===fg)&&(!fs||(fs==='sem'?!i.responsavel:!!i.responsavel))&&(!q||i.nome.toLowerCase().includes(q)));
}
function rlRender(){
  const box=document.getElementById('rlwrap'), fi=rlFiltered();
  // ---- CARDS (todas as obras selecionadas) ----
  const tot=RL.itens.length, com=RL.itens.filter(i=>i.responsavel).length, semd=tot-com, pct=tot?Math.round(100*com/tot):0;
  const comData=RL.itens.filter(i=>i.temData).length;
  const compradores={}; RL.itens.forEach(i=>{ if(i.responsavel) compradores[i.responsavel]=(compradores[i.responsavel]||0)+1; });
  const nComp=Object.keys(compradores).length, media=nComp?Math.round(com/nComp):0;
  const k=document.getElementById('rlKpi');
  if(k) k.innerHTML=`
    <div class="kpi"><div class="v">${tot}</div><div class="l">itens · ${RL.obras.length} obra${RL.obras.length>1?'s':''}</div></div>
    <div class="kpi"><div class="v gold">${pct}%</div><div class="l">atribuídos · ${com} de ${tot}</div></div>
    <div class="kpi"><div class="v ${semd?'alert':''}">${semd}</div><div class="l">sem dono (faltam)</div></div>
    <div class="kpi"><div class="v">${nComp}</div><div class="l">compradores · ~${media}/pessoa</div></div>
    <div class="kpi"><div class="v">${comData}</div><div class="l">com data de cronograma</div></div>`;
  // ---- tabela (agrupada por grupo; coluna Obra quando multi) ----
  const multi=RL.obras.length>1, cols=multi?7:6;
  let html='<table><thead><tr><th style="width:34px"><input type="checkbox" id="rlAll" onclick="rlToggleAll(this.checked)" title="selecionar os filtrados"></th><th>Item</th>'+(multi?'<th>Obra</th>':'')+'<th>Grupo</th><th>Curva</th><th>Responsável atual</th><th>Padrão</th></tr></thead><tbody>';
  const byG={}; fi.forEach(i=>{ (byG[i.grupo||'—']=byG[i.grupo||'—']||[]).push(i); });
  Object.keys(byG).forEach(gr=>{
    html+=`<tr class="grp-h"><td colspan="${cols}">${esc(gr)}</td></tr>`;
    byG[gr].forEach(i=>{ const key=rlKey(i);
      html+=`<tr><td><input type="checkbox" ${RL.sel.has(key)?'checked':''} onclick="rlSel('${key}',this.checked)"></td>
        <td>${esc(i.nome)}</td>${multi?`<td><span class="dgm" style="background:${obraCor(i.obra_id)};margin-right:5px"></span><span class="muted" style="font-size:11.5px">${esc(i.obra_nome)}</span></td>`:''}<td class="muted">${esc(i.grupo||'')}</td><td>${esc(i.curva||'')}</td>
        <td>${i.responsavel?esc(i.responsavel):'<span class="muted">— sem —</span>'}</td>
        <td class="muted" title="padrão do serviço (novas obras herdam)">${i.padrao?esc(i.padrao):'—'}</td></tr>`; });
  });
  if(!fi.length) html+=`<tr><td colspan="${cols}" class="empty">Nenhum item nesse filtro.</td></tr>`;
  box.innerHTML=html+'</tbody></table>';
  const all=document.getElementById('rlAll'); if(all) all.checked=fi.length>0 && fi.every(i=>RL.sel.has(rlKey(i)));
  rlCount();
}
function rlSel(key,on){ on?RL.sel.add(key):RL.sel.delete(key);
  const all=document.getElementById('rlAll'); if(all){ const fi=rlFiltered(); all.checked=fi.length>0 && fi.every(i=>RL.sel.has(rlKey(i))); }
  rlCount(); }
function rlToggleAll(on){ rlFiltered().forEach(i=>{ on?RL.sel.add(rlKey(i)):RL.sel.delete(rlKey(i)); }); rlRender(); }
function rlCount(){ const el=document.getElementById('rlSelCount'); if(el) el.textContent=RL.sel.size+' selecionado'+(RL.sel.size===1?'':'s'); }
async function rlAtribuir(){ const nome=val('rlResp'); if(!nome){ toast('Escolha um responsável'); return; }
  await rlAssign(nome, `Atribuir “${nome}” a ${RL.sel.size} item(ns)?`); }
async function rlLimpar(){ await rlAssign('', `Limpar o responsável de ${RL.sel.size} item(ns)?`); }
async function rlAssign(nome, msg){
  if(!RL.sel.size){ toast('Selecione ao menos um item'); return; }
  if(!confirm(msg)) return;
  const pchk=document.getElementById('rlPadrao');
  const tornarPadrao = !!(nome && pchk && pchk.checked && pchk.offsetParent!==null);  // só ao ATRIBUIR e se visível+marcado
  // agrupa a seleção por OBRA (o endpoint é por obra)
  const porObra={}; [...RL.sel].forEach(key=>{ const p=key.split(':'); const ob=Number(p[0]), ordem=Number(p[1]); (porObra[ob]=porObra[ob]||[]).push(ordem); });
  try{
    let n=0, padr=0;
    for(const ob of Object.keys(porObra)){
      const r=await (await fetch('actions/responsaveis_lote.php',{method:'POST',headers:{'Content-Type':'application/json'},
        body:JSON.stringify({acao:'atribuir',me:EU&&EU.bitrix_id,obra:Number(ob),servico_ids:porObra[ob],responsavel:nome,tornar_padrao:tornarPadrao?1:0})})).json();
      if(r.error){ toast(r.error); return; } n+=(r.n||0); padr+=(r.padrao||0);
    }
    toast(n+' item(ns) atualizado(s)'+(padr?(' · '+padr+' viraram padrão'):''));
    RL.itens.forEach(i=>{ if(RL.sel.has(rlKey(i))){ i.responsavel=nome; if(tornarPadrao) i.padrao=nome; } }); if(pchk) pchk.checked=false; RL.sel=new Set();
    if(typeof MAT!=='undefined') MAT=null; if(typeof load==='function') load();
    rlRender();
  }catch(e){ toast('Falha: '+e.message); }
}
async function rlPreencherPadrao(){
  const alvo=RL.itens.filter(i=>!i.responsavel && i.padrao).length;
  if(!alvo){ toast('Nenhum item vazio COM padrão nas obras selecionadas'); return; }
  if(!confirm('Preencher '+alvo+' item(ns) sem responsável com o padrão do serviço, nas '+RL.obras.length+' obra(s) selecionada(s)?')) return;
  try{ let n=0;
    for(const ob of RL.obras){
      const r=await (await fetch('actions/responsaveis_lote.php',{method:'POST',headers:{'Content-Type':'application/json'},
        body:JSON.stringify({acao:'preencher_padrao',me:EU&&EU.bitrix_id,obra:ob})})).json();
      if(!r.error) n+=(r.n||0);
    }
    toast(n+' item(ns) preenchido(s) com o padrão');
    if(typeof MAT!=='undefined') MAT=null; if(typeof load==='function') load(); await rlLoad();
  }catch(e){ toast('Falha: '+e.message); }
}
function rcMetodoSel(){ const el=document.getElementById('rcmetodo'); return (el&&el.value)||'concreto armado convencional'; }
function rcMetodoChange(){ renderReceitas(); }
function rcObras(){ return (typeof OBRAS!=='undefined'&&OBRAS)?OBRAS.slice():[]; }
// dropdowns "Aprender de uma obra" / "Aplicar em uma obra"
function rcMenu(kind,e){ if(e)e.stopPropagation();
  document.querySelectorAll('#cfg-receitas .rcmenu').forEach(x=>{ if(x.id!=='rcmenu-'+kind) x.style.display='none'; });
  const m=document.getElementById('rcmenu-'+kind); if(!m)return;
  if(m.style.display==='block'){ m.style.display='none'; return; }
  const obras=rcObras().filter(o=> kind==='aplicar' ? Number(o.id)>=2 : true);
  m.innerHTML = obras.length ? obras.map(o=>`<div class="rcmi" onclick="rc${kind==='aprender'?'Aprender':'Aplicar'}(${Number(o.id)})">${esc(o.nome)}</div>`).join('')
    : `<div class="rcmi muted">${rcObras().length?'nenhuma obra elegível':'carregando…'}</div>`;
  m.style.display='block';
}
document.addEventListener('click',e=>{ if(!(e.target.closest&&e.target.closest('#cfg-receitas .rcpick'))) document.querySelectorAll('#cfg-receitas .rcmenu').forEach(x=>x.style.display='none'); });
async function rcAprender(obraId){
  document.querySelectorAll('#cfg-receitas .rcmenu').forEach(x=>x.style.display='none');
  const nome=(rcObras().find(o=>Number(o.id)===Number(obraId))||{}).nome||('obra '+obraId);
  if(!confirm('Aprender as receitas a partir da curadoria atual de “'+nome+'”?\n\nRE-DERIVA a regra de cada item dessa obra. As anotações/cuidados são preservados; ajustes manuais de âncora/método podem ser sobrescritos.')) return;
  toast('Aprendendo de '+nome+'…');
  try{ const r=await (await fetch('actions/receitas.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({acao:'derivar',obra_id:Number(obraId),me:EU&&EU.bitrix_id})})).json();
    if(r.error){ toast(r.error); return; }
    toast((r.derivadas||0)+' receitas aprendidas de '+r.obra); RCDATA=null; renderReceitas();
  }catch(e){ toast('Falha: '+e.message); }
}
async function rcAplicar(obraId){
  document.querySelectorAll('#cfg-receitas .rcmenu').forEach(x=>x.style.display='none');
  if(Number(obraId)<2){ toast('A obra de origem do aprendizado não recebe auto-vínculo.'); return; }
  const nome=(rcObras().find(o=>Number(o.id)===Number(obraId))||{}).nome||('obra '+obraId);
  if(!confirm('Aplicar o dicionário em “'+nome+'”?\n\nPreenche só o que está VAZIO; tudo entra como sugerido 🤖 (não curado).')) return;
  toast('Aplicando o dicionário em '+nome+'… (pode levar ~1 min)');
  try{ const r=await (await fetch('actions/aplicar_receitas.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({acao:'aplicar',obra_id:Number(obraId),me:EU&&EU.bitrix_id})})).json();
    if(r.error){ toast(r.error); return; }
    MAT=null;   // a matriz muda → invalida o cache
    toast(`Auto-vínculo em ${r.obra}: ${r.sugeridos.crono} cronogramas · ${r.sugeridos.verba} verbas · ${r.sugeridos.quant} quantitativos`);
  }catch(e){ toast('Falha: '+e.message); }
}
function rcNovoItem(){
  const nome=(prompt('Nome do novo item (aquisição):')||'').trim(); if(!nome)return;
  const grupos=[...new Set((RCDATA&&RCDATA.receitas||[]).map(r=>r.grupo).filter(Boolean))];
  const grupo=(prompt('Grupo do item:'+(grupos.length?('\n\nexistentes: '+grupos.join(' · ')):''))||'').trim(); if(!grupo)return;
  const curva=((prompt('Curva (A / B / C):','C')||'C').trim().toUpperCase())||'C';
  (async()=>{
    try{ const r=await (await fetch('actions/receitas.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({acao:'criar_item',nome,grupo,curva,metodo_construtivo:rcMetodoSel(),me:EU&&EU.bitrix_id})})).json();
      if(r.error){ toast(r.error); return; }
      toast('Item criado: '+r.nome); RC_OPEN.add('sid:'+r.servico_id); RCDATA=null; renderReceitas();
    }catch(e){ toast('Falha: '+e.message); }
  })();
}
function rcPillTxt(dim,r){
  if(dim==='crono'){ const c=r.crono||{}; if(!c.ancora_nome) return 'automático'; const p=c.ancora_nome.split(';').map(s=>s.trim()).filter(Boolean); return '“'+esc(p[0]||'')+'”'+(p.length>1?(' +'+(p.length-1)):''); }
  if(dim==='verba'){ const v=r.verba||{}; if(!v.metodo) return '—';
    if(v.metodo==='composicao') return 'composição · '+((v.insumos||[]).length)+' insumo(s)';
    if(v.metodo==='analitico') return 'analítico · '+((v.linhas||[]).length)+' linha(s)';
    return 'manual'; }
  if(dim==='quant'){ const q=r.quant||{}; return q.fonte?esc(q.fonte):'—'; }
  return '';
}
function rcList(arr,fn){ return (arr&&arr.length)?('<ul style="margin:5px 0 0;padding-left:18px">'+arr.map(fn).join('')+'</ul>'):''; }
function rcEditor(r){
  const sid=r.servico_id, c=r.crono||{}, v=r.verba||{}, qd=r.quant||{};
  const exs=(v.exclusoes||[]).map(e=>e.insumo).join('; ');
  const opt=(cur,val,lab)=>`<option value="${val}" ${cur===val?'selected':''}>${lab}</option>`;
  const met=v.metodo||'';
  const itens = met==='composicao' ? (v.insumos||[]).map(x=>x.insumo).join('\n')
              : met==='analitico'  ? (v.linhas||[]).map(x=>x.descricao).join('\n') : '';
  const itensLbl = met==='composicao' ? 'insumos que entram — um por linha (adicione / remova / edite)'
                 : 'linhas do orçamento — uma por linha (adicione / remova / edite)';
  const driver = (qd.driver_na_verba&&qd.driver_na_verba.length)?qd.driver_na_verba.join('; ')
               : (qd.insumos&&qd.insumos.length)?qd.insumos.join('; ') : '';
  const cronoResumo = c.ancora_nome?`âncora fixa “${esc(c.ancora_nome)}”`:'automático (marco principal do serviço)';
  const recNote = v.recorte_sugerido?`<div class="muted" style="font-size:11px;margin-top:5px"><span class="material-icons" style="font-size:13px;vertical-align:-2px">bolt</span> recorte aprendido: pega o sistema “${esc(v.recorte_sugerido.sistema)}”${v.recorte_sugerido.tipo?(' ('+esc(v.recorte_sugerido.tipo)+')'):''} inteiro — se você listar insumos acima, eles valem no lugar do recorte.</div>`:'';
  return `<div style="padding:12px 14px">
    <div class="rcrule">
      <div class="rchead"><span class="material-icons" style="color:#185fa5">event</span> Cronograma — qual data usar</div>
      <div style="display:flex;gap:12px;flex-wrap:wrap">
        <label class="rclab" style="flex:1;min-width:220px">tarefa-âncora <span class="muted">(vazio = automático; “;” p/ alternativas)</span>
          <input id="rc_anc_${sid}" value="${esc(c.ancora_nome||'')}" placeholder="ex.: Louças e Metais; Acabamento fino"></label>
        <label class="rclab" style="flex:1;min-width:220px">termos que o cronograma procura
          <input id="rc_ct_${sid}" value="${esc(c.termos_template||'')}" placeholder="ex.: louças; sanitário"></label>
      </div>
      <div class="muted" style="font-size:11px;margin-top:5px">${c.marco_template?esc(c.marco_template)+' · ':''}atual: ${cronoResumo}</div>
    </div>
    <div class="rcrule">
      <div class="rchead"><span class="material-icons" style="color:#b5651d">payments</span> Verba — de onde vem o valor</div>
      <div style="display:flex;gap:12px;flex-wrap:wrap">
        <label class="rclab" style="flex:0 0 230px">como casar
          <select id="rc_vm_${sid}">${opt(met,'','—')}${opt(met,'composicao','composição (cesta de insumos)')}${opt(met,'analitico','orçamento analítico (linhas)')}${opt(met,'manual','manual')}</select></label>
        <label class="rclab" style="flex:1;min-width:220px">não incluir (exclusões, separadas por “;”)
          <input id="rc_ex_${sid}" value="${esc(exs)}" placeholder="ex.: mão de obra empreitada"></label>
      </div>
      ${(met==='composicao'||met==='analitico')?`<label class="rclab" style="margin-top:8px">${itensLbl}
        <textarea id="rc_it_${sid}" style="width:100%;min-height:74px;font-size:12.5px">${esc(itens)}</textarea></label>
      <label class="rclab" style="margin-top:6px">termos que casam (sinônimos)
        <input id="rc_vt_${sid}" value="${esc(v.termos_template||'')}" placeholder="ex.: louça; bacia; caixa acoplada"></label>${recNote}`
      :(met==='manual'?`<div class="muted" style="font-size:11.5px;margin-top:6px">Valor manual — definido item a item na obra.</div>`:'<div class="muted" style="font-size:11.5px;margin-top:6px">Escolha o método acima pra listar os insumos/linhas.</div>')}
    </div>
    <div class="rcrule">
      <div class="rchead"><span class="material-icons" style="color:#7b5ea7">straighten</span> Quantitativo — como contar</div>
      <div style="display:flex;gap:12px;flex-wrap:wrap">
        <label class="rclab" style="flex:0 0 190px">fonte
          <select id="rc_qf_${sid}">${opt(qd.fonte||'','','—')}${opt(qd.fonte,'composicao','composição')}${opt(qd.fonte,'orcamento','orçamento')}${opt(qd.fonte,'manual','manual')}</select></label>
        <label class="rclab" style="flex:0 0 130px">unidade
          <input id="rc_qu_${sid}" value="${esc(qd.unidade||'')}" placeholder="un, m²…"></label>
        <label class="rclab" style="flex:1;min-width:200px">conta pela quantidade de (separe por “;”)
          <input id="rc_qd_${sid}" value="${esc(driver)}" placeholder="ex.: Caixa acoplada de louça"></label>
      </div>
    </div>
    <div class="rcrule">
      <div class="rchead"><span class="material-icons" style="color:var(--muted)">sticky_note_2</span> Cuidados, sinônimos, o que não fazer na próxima obra</div>
      <textarea id="rc_nt_${sid}" style="width:100%;min-height:56px;font-size:13px" placeholder="ex.: em alvenaria estrutural, conferir se a bacia é suspensa — muda o kit.">${esc(r.nota||'')}</textarea>
    </div>
    <div style="display:flex;justify-content:space-between;align-items:center;margin-top:10px">
      <span class="muted" style="font-size:11px">${r.obra_origem?('última origem: '+esc(r.obra_origem)):'sem origem ainda'} · ${esc(r.metodo_construtivo||'')}</span>
      <button class="btn-prim" style="padding:6px 14px" onclick="rcSalvar(${sid})"><span class="material-icons" style="font-size:15px;vertical-align:-3px">check</span> Salvar aprendizado</button>
    </div>
  </div>`;
}
async function rcSalvar(sid){
  const g=id=>{ const el=document.getElementById(id); return el?el.value:''; };
  const verba={ metodo:g('rc_vm_'+sid), exclusoes:g('rc_ex_'+sid).split(';').map(s=>s.trim()).filter(Boolean) };
  const itEl=document.getElementById('rc_it_'+sid); if(itEl) verba.itens=itEl.value.split('\n').map(s=>s.trim()).filter(Boolean);
  const vtEl=document.getElementById('rc_vt_'+sid); if(vtEl) verba.termos_template=vtEl.value;
  const body={ acao:'salvar', me:EU&&EU.bitrix_id, servico_id:sid, metodo_construtivo:rcMetodoSel(),
    crono:{ ancora_nome:g('rc_anc_'+sid), termos_template:g('rc_ct_'+sid) },
    verba,
    quant:{ fonte:g('rc_qf_'+sid), unidade:g('rc_qu_'+sid), driver:g('rc_qd_'+sid).split(';').map(s=>s.trim()).filter(Boolean) },
    nota:g('rc_nt_'+sid) };
  try{ const r=await (await fetch('actions/receitas.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(body)})).json();
    if(r.error){ toast(r.error); return; }
    toast('Aprendizado salvo'); RCDATA=null; renderReceitas();
  }catch(e){ toast('Falha: '+e.message); }
}
async function renderReceitas(){
  const box=document.getElementById('rcwrap'); if(!box) return;
  if(!RCDATA){ box.innerHTML='<div class="empty">Carregando…</div>';
    try{ RCDATA=await (await fetch('actions/receitas.php?_='+Date.now())).json(); }catch(e){ box.innerHTML='<div class="empty">Falha ao carregar.</div>'; return; } }
  const all=RCDATA.receitas||[];
  // seletor de método construtivo (variantes do dicionário)
  const metsel=document.getElementById('rcmetodo');
  let metodos=[...new Set(all.map(r=>r.metodo_construtivo).filter(Boolean))]; if(!metodos.length) metodos=['concreto armado convencional'];
  if(metsel && metsel.dataset.k!==metodos.join('|')){ const keep=metsel.value; metsel.innerHTML=metodos.map(m=>`<option>${esc(m)}</option>`).join(''); metsel.dataset.k=metodos.join('|'); if(keep&&metodos.includes(keep)) metsel.value=keep; }
  const met=rcMetodoSel();
  const q=(document.getElementById('rcq')?document.getElementById('rcq').value:'').toLowerCase();
  let rs=all.filter(r=>(r.metodo_construtivo||'concreto armado convencional')===met);
  if(q) rs=rs.filter(r=>((r.nome||'')+' '+(r.grupo||'')).toLowerCase().includes(q));
  if(!rs.length){ box.innerHTML='<div class="empty">Nenhum item nesse filtro. Use “Aprender de uma obra” pra puxar de uma curadoria, ou “Novo item”.</div>'; return; }
  let html='<table><thead><tr><th style="width:26px"></th><th>Item</th><th>Grupo</th><th>Cronograma</th><th>Verba</th><th>Quant.</th></tr></thead><tbody>';
  let grupo=null;
  rs.forEach(r=>{
    const key='sid:'+r.servico_id, open=RC_OPEN.has(key);
    if(r.grupo!==grupo){ grupo=r.grupo; html+=`<tr class="grp-h"><td colspan="6">${esc(grupo||'—')}</td></tr>`; }
    html+=`<tr class="item" onclick="rcToggle('${key}')" style="cursor:pointer">
      <td><span class="material-icons" style="font-size:16px;color:var(--muted)">${open?'expand_more':'chevron_right'}</span></td>
      <td><b>${esc(r.nome)}</b></td><td class="muted">${esc(r.grupo||'')}</td>
      <td style="font-size:12px">${rcPillTxt('crono',r)}</td>
      <td style="font-size:12px">${rcPillTxt('verba',r)}</td>
      <td style="font-size:12px">${rcPillTxt('quant',r)}</td></tr>`;
    if(open) html+=`<tr><td></td><td colspan="5" style="background:#fbfdf9;padding:0">${rcEditor(r)}</td></tr>`;
  });
  box.innerHTML=html+'</tbody></table>';
}
function rcToggle(key){ RC_OPEN.has(key)?RC_OPEN.delete(key):RC_OPEN.add(key); renderReceitas(); }

async function renderConfig(){
  cfgTab(IS_ADMIN?'users':(CAN_RESP?'resp':'users'));
  if(!IS_ADMIN) return;   // não-admin (só responsáveis em lote) não carrega a lista de usuários
  const box=document.getElementById('cfgwrap'); box.innerHTML='<div class="empty">Carregando…</div>';
  CFG=await (await fetch('actions/usuarios.php')).json();
  if(!CFG.usuarios.length){ box.innerHTML='<div class="empty">Nenhum usuário autorizado ainda. Clique em "Adicionar usuário".</div>'; return; }
  box.innerHTML=`<table><thead><tr><th>Usuário</th><th>Papel</th><th>Vê obras</th><th>Edita</th><th>Menus</th><th>Ativo</th><th></th></tr></thead><tbody>`+
    CFG.usuarios.map(u=>`<tr>
      <td><b>${esc(u.nome)}</b> <span class="muted">#${esc(u.bitrix_id)}</span></td>
      <td><span class="tp-chip tp-mat-mo">${esc(PAPEL_LABEL[u.papel]||u.papel)}</span></td>
      <td>${u.ver_escopo==='todas'?'Todas':((u.obras_ver||[]).length+' selec.')}</td>
      <td>${u.editar_escopo==='todas'?'Todas':u.editar_escopo==='nenhuma'?'—':((u.obras_editar||[]).length+' selec.')}</td>
      <td class="muted">${(u.menus||[]).length}</td>
      <td>${u.ativo?'<span class="mapa-on">● sim</span>':'<span class="muted">não</span>'}</td>
      <td style="white-space:nowrap">
        <button class="eye" onclick="userForm('${esc(u.bitrix_id)}')" title="editar"><span class="material-icons" style="font-size:16px;line-height:28px">edit</span></button>
        <button class="eye" onclick="userDelete('${esc(u.bitrix_id)}')" title="remover" style="color:var(--pend)"><span class="material-icons" style="font-size:16px;line-height:28px">delete</span></button>
      </td></tr>`).join('')+`</tbody></table>`;
}
function userForm(bid){
  const u = bid ? CFG.usuarios.find(x=>String(x.bitrix_id)===String(bid)) : null;
  NUSER = u ? {bitrix_id:u.bitrix_id,nome:u.nome,cargo:u.cargo} : null;
  const papel=u?u.papel:'coordenador';
  const ver=u?u.ver_escopo:'sel', edit=u?u.editar_escopo:'nenhuma';
  const menus=u?(u.menus||[]):PRESETS.coordenador.menus;
  const obrasVer=u?(u.obras_ver||[]):[], obrasEdit=u?(u.obras_editar||[]):[];
  const adm=u?u.perm_admin:0, ativo=u?u.ativo:1;
  const pc=u?u.perm_crono:0, po=u?u.perm_orcamento:0, pq=u?u.perm_quant:0, pd=u?u.perm_dicionario:0, pr=u?u.perm_responsaveis:0;
  const dash=u?(u.dashboard||''):'';
  const obrasChk=(pref,sel)=>CFG.obras.map(o=>`<label class="ckl"><input type="checkbox" id="${pref}-${o.id}" ${sel.includes(o.id)?'checked':''}> ${esc(o.nome)}</label>`).join('');
  document.getElementById('modal').innerHTML=`
    <div class="mhead"><button class="mclose" onclick="closeModal()">×</button>
      <div class="crumb">Configurações</div><div class="mt">${u?'Editar usuário':'Novo usuário'}</div></div>
    <div class="tabbody">
      ${u?`<div class="box"><div class="bv"><b>${esc(u.nome)}</b> <span class="muted">#${esc(u.bitrix_id)}</span></div></div>`
         :`<div class="fld"><label>Buscar usuário no Bitrix</label>
            <div class="search" style="border:1px solid var(--line)"><span class="material-icons" style="color:var(--muted)">search</span>
              <input id="uQ" placeholder="nome ou ID…" oninput="userBuscar()"></div></div>
          <div id="uRes"></div>
          <div id="uSel" class="box" style="display:none"></div>`}
      <div class="grid2">
        <div class="fld"><label>Papel</label><select id="uPapel" onchange="userPreset()">${Object.keys(PAPEL_LABEL).map(p=>`<option value="${p}" ${p===papel?'selected':''}>${PAPEL_LABEL[p]}</option>`).join('')}</select></div>
        <div class="fld"><label>Ativo</label><select id="uAtivo"><option value="1" ${ativo?'selected':''}>Sim</option><option value="0" ${!ativo?'selected':''}>Não</option></select></div>
      </div>
      <div class="fld"><label>Dashboard inicial <span class="muted" style="text-transform:none;letter-spacing:0;font-weight:400">— o painel em que a pessoa CAI ao entrar no sistema</span></label>
        <select id="uDash">
          <option value="" ${dash===''?'selected':''}>Nenhum (entra no Radar, dashboard geral por aba)</option>
          <option value="comprador" ${dash==='comprador'?'selected':''}>Painel do comprador — minhas tarefas e prioridades</option>
          <option value="gerente" ${dash==='gerente'?'selected':''}>Painel do gerente — visão do time</option>
          <option value="diretor" ${dash==='diretor'?'selected':''}>Painel do diretor — visão ampla</option>
        </select></div>
      <div class="grid2">
        <div class="fld"><label>Vê obras</label><select id="uVer" onchange="userToggleObras()"><option value="todas" ${ver==='todas'?'selected':''}>Todas</option><option value="sel" ${ver==='sel'?'selected':''}>Selecionadas</option></select>
          <div id="uVerObras" class="ckgrid" style="display:${ver==='sel'?'grid':'none'}">${obrasChk('ov',obrasVer)}</div></div>
        <div class="fld"><label>Edita obras</label><select id="uEdit" onchange="userToggleObras()"><option value="nenhuma" ${edit==='nenhuma'?'selected':''}>Nenhuma (só leitura)</option><option value="todas" ${edit==='todas'?'selected':''}>Todas</option><option value="sel" ${edit==='sel'?'selected':''}>Selecionadas</option></select>
          <div id="uEditObras" class="ckgrid" style="display:${edit==='sel'?'grid':'none'}">${obrasChk('oe',obrasEdit)}</div></div>
      </div>
      <div class="fld"><label>Menus visíveis</label><div class="ckgrid">
        ${MENUS.map(m=>`<label class="ckl"><input type="checkbox" id="mn-${m[0]}" ${menus.includes(m[0])?'checked':''}> ${m[1]}</label>`).join('')}</div></div>
      <div class="fld"><label>Permissões específicas <span class="muted" style="text-transform:none;letter-spacing:0;font-weight:400">— além de editar status / fornecedor / observação</span></label>
        <div class="ckgrid">
          <label class="ckl"><input type="checkbox" id="pCrono" ${pc?'checked':''}> Vínculo de cronograma</label>
          <label class="ckl"><input type="checkbox" id="pOrc" ${po?'checked':''}> Vínculo de orçamento (verba)</label>
          <label class="ckl"><input type="checkbox" id="pQuant" ${pq?'checked':''}> Vínculo de quantitativo</label>
          <label class="ckl"><input type="checkbox" id="pDic" ${pd?'checked':''}> Editar dicionário</label>
          <label class="ckl"><input type="checkbox" id="pRespLote" ${pr?'checked':''}> Atribuir responsáveis em lote</label>
        </div></div>
      <label class="ckl" style="margin:4px 0 12px"><input type="checkbox" id="uAdmin" ${adm?'checked':''}> É administrador (acessa Configurações e edita tudo)</label>
      <div style="display:flex;gap:8px"><button class="btn-prim" onclick="userSave()">Salvar usuário</button>
        <button class="btn-ghost" onclick="closeModal()">Cancelar</button></div>
    </div>`;
  document.getElementById('ov').classList.add('open');
}
function userPreset(){
  const p=PRESETS[val('uPapel')]; if(!p)return; // 'personalizado' (null) mantém o que está marcado
  document.getElementById('uVer').value=p.ver; document.getElementById('uEdit').value=p.edit;
  document.getElementById('uAdmin').checked=!!p.adm;
  ['pCrono','pOrc','pQuant','pDic','pRespLote'].forEach(id=>{const e=document.getElementById(id); if(e)e.checked=false;}); // presets definidos zeram as específicas
  MENUS.forEach(m=>{const e=document.getElementById('mn-'+m[0]); if(e)e.checked=p.menus.includes(m[0]);});
  userToggleObras();
}
function userToggleObras(){
  document.getElementById('uVerObras').style.display=val('uVer')==='sel'?'grid':'none';
  document.getElementById('uEditObras').style.display=val('uEdit')==='sel'?'grid':'none';
}
/* ===== CONFIGURAR EM LOTE: aplica um pacote (menus/escopo de obras/permissões) a um grupo de usuários
   (todos de um PAPEL, ou seleção manual). Só as seções MARCADAS são aplicadas — o resto de cada cadastro
   fica intacto. Papel e flag admin ficam de fora do lote (segurança). ===== */
async function userLote(){
  if(!CFG||!CFG.usuarios||!CFG.usuarios.length){ try{ CFG=await (await fetch('actions/usuarios.php')).json(); }catch(e){} }   // auto-carrega (clique antes da lista terminar de carregar)
  if(!CFG||!CFG.usuarios||!CFG.usuarios.length){toast('Não consegui carregar os usuários');return;}
  const ativos=CFG.usuarios.filter(u=>u.ativo);
  const papeis=Object.keys(PAPEL_LABEL).map(p=>({p,n:ativos.filter(u=>u.papel===p).length})).filter(x=>x.n>0);
  const sec=(id,titulo,inner)=>`<div class="fld" style="border:1px solid var(--line);border-radius:10px;padding:12px;margin-bottom:12px">
    <label class="ckl" style="font-weight:700;font-size:13px;margin-bottom:8px"><input type="checkbox" id="${id}" onchange="userLoteDim()"> ${titulo}</label>
    <div id="${id}Box" style="opacity:.45;pointer-events:none">${inner}</div></div>`;
  document.getElementById('modal').innerHTML=`
    <div class="mhead"><button class="mclose" onclick="closeModal()">×</button>
      <div class="crumb">Configurações</div><div class="mt">Configurar em lote</div></div>
    <div class="tabbody">
      <div class="fld"><label>Aplicar a</label>
        <select id="ltAlvo" onchange="userLoteAlvo()">
          ${papeis.map(x=>`<option value="papel:${x.p}">Papel: ${PAPEL_LABEL[x.p]} — ${x.n} usuário(s) ativo(s)</option>`).join('')}
          <option value="sel">Escolher usuários…</option>
        </select>
        <div id="ltUsers" class="ckgrid" style="display:none">${ativos.map(u=>`<label class="ckl"><input type="checkbox" id="lu-${esc(String(u.bitrix_id))}"> ${esc(u.nome)} <span class="muted">#${esc(String(u.bitrix_id))}</span></label>`).join('')}</div></div>
      <div style="margin:10px 0 14px;padding:9px 12px;background:#fff9e6;border:1px solid #efe3b0;border-radius:8px;font-size:12.5px;color:#6b5d1f">
        Só as seções <b>marcadas</b> abaixo são aplicadas ao grupo — o resto do cadastro de cada usuário fica como está.
        <b>Papel</b> e <b>administrador</b> não mudam em lote (ajuste individualmente).</div>
      ${sec('ltApMenus','Aplicar: Menus visíveis',`<div class="ckgrid" style="margin-top:0">
        ${MENUS.map(m=>`<label class="ckl"><input type="checkbox" id="lm-${m[0]}"> ${m[1]}</label>`).join('')}</div>`)}
      ${sec('ltApEscopo','Aplicar: Escopo de obras',`<div class="grid2">
        <div class="fld"><label>Vê obras</label><select id="ltVer" onchange="userLoteObras()"><option value="todas">Todas</option><option value="sel">Selecionadas</option></select>
          <div id="ltVerObras" class="ckgrid" style="display:none">${CFG.obras.map(o=>`<label class="ckl"><input type="checkbox" id="lov-${o.id}"> ${esc(o.nome)}</label>`).join('')}</div></div>
        <div class="fld"><label>Edita obras</label><select id="ltEdit" onchange="userLoteObras()"><option value="nenhuma">Nenhuma (só leitura)</option><option value="todas">Todas</option><option value="sel">Selecionadas</option></select>
          <div id="ltEditObras" class="ckgrid" style="display:none">${CFG.obras.map(o=>`<label class="ckl"><input type="checkbox" id="loe-${o.id}"> ${esc(o.nome)}</label>`).join('')}</div></div></div>`)}
      ${sec('ltApPerms','Aplicar: Permissões específicas',`<div class="ckgrid" style="margin-top:0">
        <label class="ckl"><input type="checkbox" id="lp-crono"> Vínculo de cronograma</label>
        <label class="ckl"><input type="checkbox" id="lp-orc"> Vínculo de orçamento (verba)</label>
        <label class="ckl"><input type="checkbox" id="lp-quant"> Vínculo de quantitativo</label>
        <label class="ckl"><input type="checkbox" id="lp-dic"> Editar dicionário</label>
        <label class="ckl"><input type="checkbox" id="lp-resp"> Atribuir responsáveis em lote</label></div>`)}
      ${sec('ltApDash','Aplicar: Dashboard inicial',`<select id="ltDash" style="max-width:420px">
        <option value="">Nenhum (entra no Radar)</option>
        <option value="comprador">Painel do comprador — minhas tarefas e prioridades</option>
        <option value="gerente">Painel do gerente — visão do time</option>
        <option value="diretor">Painel do diretor — visão ampla</option></select>`)}
      <div style="display:flex;gap:8px"><button class="btn-prim" onclick="userLoteSave()"><span class="material-icons" style="font-size:16px;vertical-align:-3px">done_all</span> Aplicar ao grupo</button>
        <button class="btn-ghost" onclick="closeModal()">Cancelar</button></div>
    </div>`;
  document.getElementById('ov').classList.add('open');
}
function userLoteAlvo(){ document.getElementById('ltUsers').style.display = val('ltAlvo')==='sel'?'grid':'none'; }
function userLoteObras(){
  document.getElementById('ltVerObras').style.display = val('ltVer')==='sel'?'grid':'none';
  document.getElementById('ltEditObras').style.display = val('ltEdit')==='sel'?'grid':'none';
}
function userLoteDim(){
  [['ltApMenus'],['ltApEscopo'],['ltApPerms'],['ltApDash']].forEach(([id])=>{
    const on=document.getElementById(id).checked, box=document.getElementById(id+'Box');
    if(box){ box.style.opacity=on?'1':'.45'; box.style.pointerEvents=on?'auto':'none'; }
  });
}
async function userLoteSave(){
  const ck=id=>{const e=document.getElementById(id);return e&&e.checked;};
  // alvo
  let papel_alvo=null, bitrix_ids=null, n=0, rotulo='';
  const alvo=val('ltAlvo');
  if(alvo==='sel'){
    bitrix_ids=CFG.usuarios.filter(u=>u.ativo&&ck('lu-'+String(u.bitrix_id))).map(u=>String(u.bitrix_id));
    n=bitrix_ids.length; rotulo=n+' usuário(s) escolhido(s)';
    if(!n){toast('Marque ao menos um usuário');return;}
  } else {
    papel_alvo=alvo.slice(6); n=CFG.usuarios.filter(u=>u.ativo&&u.papel===papel_alvo).length;
    rotulo='todos os '+n+' ativos do papel '+(PAPEL_LABEL[papel_alvo]||papel_alvo);
  }
  // pacote: só as seções marcadas entram
  const campos={};
  if(ck('ltApMenus')) campos.menus=MENUS.filter(m=>ck('lm-'+m[0])).map(m=>m[0]);
  if(ck('ltApEscopo')){
    campos.ver_escopo=val('ltVer'); campos.obras_ver=campos.ver_escopo==='sel'?CFG.obras.filter(o=>ck('lov-'+o.id)).map(o=>o.id):[];
    campos.editar_escopo=val('ltEdit'); campos.obras_editar=campos.editar_escopo==='sel'?CFG.obras.filter(o=>ck('loe-'+o.id)).map(o=>o.id):[];
  }
  if(ck('ltApPerms')){ campos.perm_crono=ck('lp-crono')?1:0; campos.perm_orcamento=ck('lp-orc')?1:0; campos.perm_quant=ck('lp-quant')?1:0; campos.perm_dicionario=ck('lp-dic')?1:0; campos.perm_responsaveis=ck('lp-resp')?1:0; }
  if(ck('ltApDash')) campos.dashboard=val('ltDash');
  if(!Object.keys(campos).length){toast('Marque ao menos uma seção pra aplicar');return;}
  if(!confirm('Aplicar este pacote a '+rotulo+'?')) return;
  try{
    const r=await (await fetch('actions/usuarios.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({acao:'save_lote',me:EU&&EU.bitrix_id,papel_alvo,bitrix_ids,campos})})).json();
    if(r.error){toast(r.error);return;}
    toast((r.afetados??0)+' usuário(s) atualizados'); closeModal(); renderConfig();
  }catch(e){toast('Falha: '+e.message);}
}
async function userBuscar(){
  const q=val('uQ'); const box=document.getElementById('uRes');
  if(q.length<2){box.innerHTML='';return;}
  box.innerHTML='<div class="muted" style="font-size:12px;padding:4px">Buscando…</div>';
  const d=await (await fetch('actions/bx_users.php?q='+encodeURIComponent(q))).json();
  box.innerHTML='<div class="srbox">'+(d.usuarios||[]).map(u=>`<div class="pickrow" onclick="userPick('${esc(u.id)}','${esc((u.nome||'').replace(/'/g,'’'))}','${esc((u.cargo||'').replace(/'/g,'’'))}')">
    <span class="material-icons" style="font-size:16px;color:var(--verde)">person</span>
    <div><div>${esc(u.nome)} <span class="muted">#${esc(u.id)}</span></div></div></div>`).join('')+'</div>';
}
function userPick(id,nome,cargo){
  NUSER={bitrix_id:id,nome,cargo};
  document.getElementById('uRes').innerHTML=''; document.getElementById('uQ').value='';
  const s=document.getElementById('uSel'); s.style.display='block';
  s.innerHTML=`<div class="bv">Selecionado: <b>${esc(nome)}</b> <span class="muted">#${esc(id)}</span></div>`;
}
async function userSave(){
  if(!NUSER){toast('Escolha um usuário do Bitrix');return;}
  const menus=MENUS.filter(m=>document.getElementById('mn-'+m[0]).checked).map(m=>m[0]);
  const ver=val('uVer'),edit=val('uEdit');
  const obras_ver=ver==='sel'?CFG.obras.filter(o=>document.getElementById('ov-'+o.id).checked).map(o=>o.id):[];
  const obras_editar=edit==='sel'?CFG.obras.filter(o=>document.getElementById('oe-'+o.id).checked).map(o=>o.id):[];
  const body={acao:'save',me:EU&&EU.bitrix_id,bitrix_id:NUSER.bitrix_id,nome:NUSER.nome,cargo:NUSER.cargo,papel:val('uPapel'),
    ver_escopo:ver,editar_escopo:edit,obras_ver,obras_editar,menus,
    perm_admin:document.getElementById('uAdmin').checked?1:0,
    perm_crono:document.getElementById('pCrono').checked?1:0,
    perm_orcamento:document.getElementById('pOrc').checked?1:0,
    perm_quant:document.getElementById('pQuant').checked?1:0,
    perm_dicionario:document.getElementById('pDic').checked?1:0,
    perm_responsaveis:document.getElementById('pRespLote').checked?1:0,
    dashboard:val('uDash'),
    ativo:parseInt(val('uAtivo'))};
  const d=await (await fetch('actions/usuarios.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(body)})).json();
  if(d.error){
    const dbg=d.debug?` · (servidor recebeu me=${JSON.stringify(d.debug.me_recebido)}; eu enviei=${JSON.stringify(EU&&EU.bitrix_id)})`:'';
    console.warn('userSave erro:',d,'EU=',EU); toast('Erro: '+d.error+dbg); return;
  }
  closeModal(true); await renderConfig(); toast('Usuário salvo');
}
async function userDelete(bid){
  if(!confirm('Remover o acesso deste usuário?'))return;
  await fetch('actions/usuarios.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({acao:'delete',bitrix_id:bid,me:EU&&EU.bitrix_id})});
  await renderConfig(); toast('Acesso removido');
}

document.addEventListener('keydown',e=>{if(e.key==='Escape')closeModal();});
window.addEventListener('resize',fitRadarHeight);
// serializa: define IS_ADMIN + carrega responsáveis ANTES do 1º render (evita vazar controles admin / select vazio)
(async()=>{ try{ await Promise.all([getCurrentUser(), loadResponsaveis()]); }catch(e){} await load(); })();
