/* Cockpit de Suprimentos — parte 5 de 6 do aplicativo.
   Gerado a partir do bloco unico que vivia dentro do index.php: 857 KB num arquivo so faziam
   cada deploy levar de 5 a 10 minutos e falhar calado. O corte respeita fronteiras de nivel
   superior e cada parte foi validada pelo parser antes de existir. A ORDEM importa: os
   arquivos sao carregados na sequencia em que foram cortados. */
async function solGerar(key){ const s=solFind(key); if(!s)return; if(!confirm('Gerar uma cotação no Mapa com os '+s.n_itens+' itens desta solicitação?'))return;
  try{ const r=await (await fetch('actions/solicitacoes.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({acao:'gerar_cotacao',me:EU&&EU.bitrix_id,coligada:s.coligada,numero:s.numero})})).json();
    if(r.error){toast(r.error);return;} toast('Cotação gerada!'); s.cotacao_id=r.cotacao_id; s.status='em_cotacao'; showView('cotacoes'); setTimeout(()=>cotAbrir(r.cotacao_id),250);
  }catch(e){toast('Falha: '+e.message);} }
async function solObrasLoad(){
  const w=document.getElementById('solwrap'); w.innerHTML='<div class="dempty">Carregando obras…</div>';
  try{ SOL.obras=await (await fetch('actions/solicitacoes.php?obras&me='+solMe())).json(); solRenderObras(); }catch(e){ w.innerHTML='<div class="empty">Falha.</div>'; }
}
function solRenderObras(){
  const w=document.getElementById('solwrap'), o=SOL.obras, uOpts=id=>'<option value="">— comprador —</option>'+(o.usuarios||[]).map(u=>`<option value="${esc(u.bitrix_id)}" ${String(u.bitrix_id)===String(id)?'selected':''}>${esc(u.nome)}</option>`).join('');
  const rOpts=id=>'<option value="">— vincular à obra do radar (opcional) —</option>'+(o.radar_obras||[]).map(r=>`<option value="${r.id}" ${String(r.id)===String(id)?'selected':''}>${esc(r.nome)}</option>`).join('');
  const semComp=(o.obras||[]).filter(x=>!x.comprador_id).length;
  w.innerHTML=`<div class="panel" style="margin-bottom:10px"><b style="font-size:14px">Obras &amp; compradores</b>
      <span class="muted" style="font-size:11.5px"> — cada obra (coligada + centro de custo) tem 1 comprador; a solicitação entra já atribuída. ${semComp?`<b style="color:var(--pend)">${semComp} sem comprador</b>`:'todas atribuídas ✓'}</span></div>
    <div class="wrap"><table><thead><tr><th>Obra (nome comercial)</th><th>CNPJ da obra</th><th>Endereço da obra</th><th>Coligada (TOTVS)</th><th style="text-align:center">CC</th><th style="text-align:center">Pend.</th><th>Comprador responsável</th><th>Obra do radar (opcional)</th></tr></thead><tbody>
    ${(o.obras||[]).map((x,i)=>`<tr>
      <td><input value="${esc(x.nome_comercial)}" onchange="SOL.obras.obras[${i}].nome_comercial=this.value;solObraSave(${i})" style="width:150px;font-size:12px"></td>
      <td><input value="${esc(x.cnpj||'')}" onchange="SOL.obras.obras[${i}].cnpj=this.value;solObraSave(${i})" placeholder="00.000.000/0001-00" title="CNPJ que vai na carta de cotação de material desta obra" style="width:145px;font-size:11.5px"></td>
      <td><input value="${esc(x.endereco||'')}" onchange="SOL.obras.obras[${i}].endereco=this.value;solObraSave(${i})" placeholder="rua, nº, bairro, cidade/UF" title="Endereço que vai na carta de cotação e no e-mail ao fornecedor" style="width:210px;font-size:11.5px"></td>
      <td class="muted" style="font-size:11px">${esc(x.coligada)}</td><td style="text-align:center" class="muted">${esc(x.obra_cod)}</td>
      <td style="text-align:center"><b>${x.n}</b></td>
      <td><select onchange="SOL.obras.obras[${i}].comprador_id=this.value;solObraSave(${i})" style="font-size:12px;padding:3px;${x.comprador_id?'':'border-color:var(--pend)'}">${uOpts(x.comprador_id)}</select></td>
      <td><select onchange="SOL.obras.obras[${i}].radar_obra_id=this.value;solObraSave(${i})" style="font-size:11.5px;padding:3px">${rOpts(x.radar_obra_id)}</select></td></tr>`).join('')}
    </tbody></table></div>`;
}
async function solObraSave(i){ const x=SOL.obras.obras[i];
  try{ const r=await (await fetch('actions/solicitacoes.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({acao:'salvar_obra',me:EU&&EU.bitrix_id,obra:{coligada:x.coligada,obra_cod:x.obra_cod,nome_comercial:x.nome_comercial,cnpj:x.cnpj||'',endereco:x.endereco||'',comprador_id:x.comprador_id,radar_obra_id:x.radar_obra_id||null}})})).json();
    if(r.error){toast(r.error);return;} toast('Atribuição salva'); SOL.data=null; }catch(e){toast('Falha');} }
/* ---------- Fornecedores (sub-aba do Mapa de Cotações) ---------- */
/* Os filtros SEMPRE foram server-side (o total já era o do recorte inteiro), mas a tela pedia
   limit=80 e nenhum offset: de 1.466 fornecedores dava p/ ver só os 80 primeiros, sem navegação.
   Agora pagina de verdade, e qualquer mudança de filtro volta p/ a página 1 — senão você filtra
   uma categoria com 12 itens estando na página 7 e a tela aparece vazia sem explicar por quê. */
const FORN_POR_PAGINA=60;
let FORN={list:[],cats:[],tipos:[],total:0,pag:1,f:{nome:'',categoria:'',tipo:'',itens:''},edit:null};
function fornQS(){ const q=new URLSearchParams(); Object.entries(FORN.f).forEach(([k,v])=>{ if(v) q.set(k,v); }); return q; }
async function fornLoad(){
  const w=document.getElementById('cotwrap'); w.innerHTML='<div class="dempty">Carregando fornecedores…</div>';
  const q=fornQS(); q.set('limit',String(FORN_POR_PAGINA)); q.set('offset',String((FORN.pag-1)*FORN_POR_PAGINA));
  try{ const d=await (await fetch('actions/fornecedores.php?'+q.toString())).json();
    FORN.list=d.fornecedores||[]; FORN.cats=d.categorias||[]; FORN.tipos=d.tipos||[]; FORN.total=d.total||0;
    const paginas=Math.max(1,Math.ceil(FORN.total/FORN_POR_PAGINA));
    if(FORN.pag>paginas){ FORN.pag=paginas; return fornLoad(); }   // filtro encolheu o recorte
    fornRender();
  }catch(e){ w.innerHTML='<div class="dempty">Falha: '+esc(e.message)+'</div>'; }
}
function fornPag(n){ FORN.pag=Math.max(1,n); fornLoad(); }
function fornFiltro(){ FORN.pag=1; fornLoad(); }          // troca de filtro sempre volta à página 1
let _fornT; function fornDeb(){ clearTimeout(_fornT); _fornT=setTimeout(fornFiltro,350); }
/* CSV do RECORTE ATUAL: leva os mesmos filtros ao servidor, que devolve TODAS as linhas (não a página) */
function fornCSV(){
  const q=fornQS(); q.set('csv','1'); q.set('me',(EU&&EU.bitrix_id)||'');
  window.location.href='actions/fornecedores.php?'+q.toString();
}
function fornCatOpts(sel){ return '<option value="">Todas as categorias</option>'+FORN.cats.map(c=>`<option value="${esc(c.nome)}" ${c.nome===sel?'selected':''}>${esc(c.nome)}</option>`).join(''); }
function fornRender(){
  if(FORN.edit) return fornRenderEdit();
  const w=document.getElementById('cotwrap');
  const paginas=Math.max(1,Math.ceil(FORN.total/FORN_POR_PAGINA));
  const temFiltro=!!(FORN.f.nome||FORN.f.categoria||FORN.f.tipo||FORN.f.itens);
  let html=`<div class="panel" style="margin-bottom:10px"><div class="bar" style="gap:8px;flex-wrap:wrap;align-items:center">
    <div class="search" style="min-width:150px"><span class="material-icons" style="color:var(--muted)">search</span><input placeholder="Buscar nome…" value="${esc(FORN.f.nome)}" oninput="FORN.f.nome=this.value;fornDeb()"></div>
    <select onchange="FORN.f.categoria=this.value;fornFiltro()">${fornCatOpts(FORN.f.categoria)}</select>
    <select onchange="FORN.f.tipo=this.value;fornFiltro()"><option value="">Todos os tipos</option>${FORN.tipos.map(t=>`<option value="${esc(t)}" ${t===FORN.f.tipo?'selected':''}>${esc(t)}</option>`).join('')}</select>
    <input placeholder="Filtrar por itens…" value="${esc(FORN.f.itens)}" oninput="FORN.f.itens=this.value;fornDeb()" style="min-width:130px">
    ${temFiltro?`<button class="btn-ghost" style="padding:5px 10px;font-size:11.5px;color:var(--pend);font-weight:700" onclick="FORN.f={nome:'',categoria:'',tipo:'',itens:''};fornFiltro()">✕ limpar</button>`:''}
    ${IS_ADMIN?`<button class="btn-ghost" style="padding:5px 10px;font-size:11.5px" onclick="fornDups()" title="fornecedores com o MESMO CNPJ cadastrados mais de uma vez"><span class="material-icons" style="font-size:14px;vertical-align:-3px">join_full</span> Duplicados</button>`:''}
    <span class="muted" style="font-size:12px"><b>${FORN.total}</b> fornecedor(es)${temFiltro?' no filtro':''}${paginas>1?` · página ${FORN.pag} de ${paginas}`:''}</span>
    <button class="btn-ghost" style="margin-left:auto;padding:7px 12px" onclick="fornCSV()" title="baixa em CSV TODAS as ${FORN.total} linha(s) do recorte atual — não só esta página">
      <span class="material-icons" style="font-size:15px;vertical-align:-3px">download</span> Exportar CSV</button>
    ${CAN_FORN?'<button class="btn-prim" style="padding:7px 12px" onclick="fornNovo()"><span class="material-icons" style="font-size:15px;vertical-align:-3px">add</span> Novo</button>':''}
  </div></div><div class="wrap"><table><thead><tr><th>Nome</th><th>Categoria</th><th>Cidade</th><th>Contato</th><th>Telefone</th><th>Itens</th><th>Tipo</th><th></th></tr></thead><tbody>`;
  for(const f of FORN.list){
    /* Selo do TOTVS: o Murilo abriu dois cadastros da Comercial Ararense e nao tinha como saber qual
       deles o TOTVS conhece — e e o codigo do TOTVS (CODCFO) que casa o pedido com o cadastro. */
    const selo = f.totvs_cod ? ` <span class="dchip" style="background:var(--verde);font-size:9.5px;vertical-align:1px" title="cadastrado no TOTVS — código ${esc(f.totvs_cod)}">TOTVS ${esc(f.totvs_cod)}</span>` : '';
    html+=`<tr><td><b>${esc(f.nome)}</b>${selo}${f.email?`<div class="muted" style="font-size:11px">${esc(f.email)}</div>`:''}${f.cnpj?`<div class="muted" style="font-size:10.5px">CNPJ ${esc(f.cnpj)}</div>`:''}</td><td class="muted">${esc(f.categoria||'')}</td><td class="muted">${esc(f.cidade||'')}</td><td>${esc(f.contato||'')}</td><td>${esc(f.telefone||'')}</td><td class="muted" style="font-size:11px">${esc((f.itens||'').slice(0,42))}</td><td>${esc(f.tipo||'')}</td>
      <td>${CAN_FORN?`<button class="btn-ghost" style="padding:2px 8px" onclick="fornNovo(${f.id})"><span class="material-icons" style="font-size:15px">edit</span></button>`:''}</td></tr>`;
  }
  if(!FORN.list.length) html+=`<tr><td colspan="8" class="empty">${temFiltro?'Nenhum fornecedor com esses filtros. <span class="dmini">Tente limpar a categoria ou o tipo.</span>':'Nenhum fornecedor. Importe do sistema antigo (Excel) ou cadastre um novo.'}</td></tr>`;
  html+='</tbody></table></div>';
  if(paginas>1){
    const b=(n,lbl,on)=>`<button class="btn-ghost" style="padding:4px 10px;font-size:12px;${on?'background:var(--verde);color:#fff;font-weight:700':''}" onclick="fornPag(${n})">${lbl}</button>`;
    let nav='<div style="display:flex;gap:5px;align-items:center;justify-content:center;flex-wrap:wrap;padding:11px">';
    if(FORN.pag>1) nav+=b(1,'« primeira')+b(FORN.pag-1,'‹ anterior');
    const ini=Math.max(1,FORN.pag-2), fim=Math.min(paginas,ini+4);
    for(let i=ini;i<=fim;i++) nav+=b(i,String(i),i===FORN.pag);
    if(FORN.pag<paginas) nav+=b(FORN.pag+1,'próxima ›')+b(paginas,'última »');
    nav+=`<span class="dmini" style="margin-left:8px">${FORN.total} no total</span></div>`;
    html+=nav;
  }
  w.innerHTML=html;
}
/* DUPLICADOS — mesmo CNPJ em mais de um cadastro. A tela ordena pelos FÁCEIS primeiro: grupo em que
   o cadastro a ser removido não tem NENHUM histórico (nem convite, nem proposta, nem anexo, nem tabela
   de preço) é fusão sem perda. Os que têm histórico dos dois lados ficam por último, com o peso à vista. */
async function fornDups(){
  const w=document.getElementById('cotwrap'); w.innerHTML='<div class="dempty">Procurando duplicados…</div>';
  try{ FORN.dups=await (await fetch('actions/fornecedores.php?duplicados=1&me='+encodeURIComponent((EU&&EU.bitrix_id)||'')+'&_='+Date.now())).json(); }
  catch(e){ w.innerHTML='<div class="dempty">Falha ao carregar.</div>'; return; }
  fornDupsRender();
}
function fornDupsRender(){
  const w=document.getElementById('cotwrap'), d=FORN.dups; if(!w||!d) return;
  const G=d.grupos||[];
  const triviais=G.filter(g=>g.trivial).length;
  let h=`<div class="panel" style="margin-bottom:10px"><div class="bar" style="gap:9px;flex-wrap:wrap;align-items:center">
    <button class="btn-ghost" style="padding:5px 11px" onclick="FORN.dups=null;fornFiltro()"><span class="material-icons" style="font-size:15px;vertical-align:-3px">arrow_back</span> Voltar</button>
    <b style="font-size:14px">${G.length} CNPJ(s) cadastrados mais de uma vez</b>
    <span class="dmini">${triviais} são fusão sem perda — o cadastro que sai não tem histórico nenhum</span>
  </div></div>`;
  if(!G.length) return void(w.innerHTML=h+'<div class="dempty" style="padding:24px">Nenhum CNPJ duplicado. 🎉</div>');
  G.forEach((g,gi)=>{
    const cn=String(g.cnpj).replace(/^(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})$/,'$1.$2.$3/$4-$5');
    h+=`<div class="dcard wide" style="margin-bottom:10px;padding:12px 15px">
      <div style="display:flex;align-items:center;gap:9px;flex-wrap:wrap;margin-bottom:8px">
        <span class="material-icons" style="font-size:17px;color:${g.trivial?'var(--ok)':'var(--dourado)'}">${g.trivial?'check_circle':'help'}</span>
        <b style="font-size:13px">${esc(cn)}</b>
        <span class="dchip" style="background:${g.trivial?'#e8f5ee':'#fdf4e3'};color:${g.trivial?'var(--verde-d)':'#a4761c'};font-size:10px">${g.trivial?'fusão sem perda':'os dois têm histórico'}</span>
        <span class="dmini">${g.n} cadastros</span>
      </div>
      <table class="dtable" style="width:100%"><thead><tr><th style="width:30px"></th><th>Cadastro</th><th>Categoria</th><th>Contato</th><th class="r">Histórico</th><th class="r">Compras 2026</th></tr></thead><tbody>`;
    g.cadastros.forEach((f,i)=>{
      const uso=Object.entries(f.uso||{}).map(([k,v])=>v+' '+k).join(' · ')||'—';
      h+=`<tr>
        <td><input type="radio" name="dup${gi}" ${i===0?'checked':''} onchange="FORN.dupSel=FORN.dupSel||{};FORN.dupSel[${gi}]=${f.id}" style="width:auto"></td>
        <td><b>${esc(f.nome)}</b>${f.razao_social&&f.razao_social!==f.nome?`<div class="dmini">${esc(f.razao_social)}</div>`:''}<div class="dmini">#${f.id} · criado ${f.created_at?D(String(f.created_at).slice(0,10)):'—'}</div></td>
        <td class="muted" style="font-size:11px">${esc(f.categoria||'—')}<br>${esc(f.tipo||'')}</td>
        <td style="font-size:11px">${esc(f.contato||'')}${f.telefone?'<br>'+esc(f.telefone):''}${f.email?'<br><span class="muted">'+esc(f.email)+'</span>':''}</td>
        <td class="r" style="font-size:11px;${f.uso_total?'font-weight:700':'color:var(--muted)'}">${esc(uso)}</td>
        <td class="r" style="font-size:11px">${f.totvs_compras_2026?f.totvs_compras_2026+' PCs<br>'+BRL(f.totvs_valor_2026):'<span class="muted">—</span>'}</td>
      </tr>`;
    });
    h+=`</tbody></table>
      <div style="display:flex;align-items:center;gap:9px;margin-top:9px">
        <button class="btn-prim" style="padding:5px 13px;font-size:12.5px" onclick="fornFundir(${gi})">Manter o marcado e juntar o resto</button>
        <span class="dmini">o histórico dos outros passa para o marcado; os demais cadastros são apagados</span>
      </div></div>`;
  });
  w.innerHTML=h;
}
async function fornFundir(gi){
  const g=(FORN.dups.grupos||[])[gi]; if(!g) return;
  const fica=(FORN.dupSel&&FORN.dupSel[gi])||g.cadastros[0].id;
  const vao=g.cadastros.map(f=>f.id).filter(i=>i!==fica);
  const nomeFica=(g.cadastros.find(f=>f.id===fica)||{}).nome||'';
  if(!confirm('Manter "'+nomeFica+'" e apagar '+vao.length+' cadastro(s)? O historico (convites, propostas, anexos, tabelas de preco) passa para o que fica, e nao da pra desfazer.')) return;
  try{
    const r=await (await fetch('actions/fornecedores.php',{method:'POST',headers:{'Content-Type':'application/json'},
      body:JSON.stringify({acao:'fundir_fornecedores',me:EU&&EU.bitrix_id,manter_id:fica,remover_ids:vao})})).json();
    if(r.error){ toast(r.error); return; }
    const mv=Object.entries(r.historico_movido||{}).map(([k,v])=>v+' de '+k).join(', ');
    toast('Fundido: ficou "'+nomeFica+'"'+(mv?' · movido '+mv:''));
    fornDups();
  }catch(e){ toast('Falha ao fundir'); }
}
function fornNovo(id){ FORN.edit = id ? Object.assign({}, (FORN.list.find(f=>f.id===id)||{id})) : {}; fornRender(); }
function fornRenderEdit(){
  const f=FORN.edit, w=document.getElementById('cotwrap');
  const F=(label,key,ph)=>cotFld(label,`<input id="fe_${key}" value="${esc(f[key]||'')}" placeholder="${ph||''}">`);
  w.innerHTML=`<div class="panel"><div style="display:flex;align-items:center;gap:8px;margin-bottom:12px"><button class="btn-ghost" onclick="FORN.edit=null;fornRender()"><span class="material-icons" style="font-size:16px;vertical-align:-3px">arrow_back</span> Voltar</button><b style="font-size:15px">${f.id?'Editar':'Novo'} fornecedor</b></div>
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:10px">
      ${F('Nome *','nome','Razão social / nome')}
      ${cotFld('Categoria',`<input id="fe_categoria" list="feCats" value="${esc(f.categoria||'')}" placeholder="Categoria"><datalist id="feCats">${FORN.cats.map(c=>`<option value="${esc(c.nome)}">`).join('')}</datalist>`)}
      ${cotFld('Tipo',`<select id="fe_tipo">${['','Fabricante','M.O.','Atacadista','Varejista','Locadora','Distribuidor','Prestador'].map(t=>`<option ${t===(f.tipo||'')?'selected':''}>${t}</option>`).join('')}</select>`)}
      ${F('Cidade','cidade')} ${F('Contato','contato')} ${F('Telefone','telefone')} ${F('WhatsApp','whatsapp')} ${F('E-mail','email')} ${F('CNPJ','cnpj')}
    </div>
    ${cotFld('Itens que fornece','<input id="fe_itens" value="'+esc(f.itens||'')+'" placeholder="Ex.: forro, gesso, revestimentos">','margin-top:8px')}
    <div style="margin-top:14px;display:flex;gap:8px"><button class="btn-prim" onclick="fornSalvar()"><span class="material-icons" style="font-size:16px;vertical-align:-3px">check</span> Salvar</button>${f.id&&(IS_ADMIN||((EU&&EU.papel)||'')==='gerente')?`<button class="btn-ghost" style="color:var(--pend)" onclick="fornExcluir(${f.id})">Excluir</button>`:''}</div></div>`;
}
async function fornSalvar(){
  const g=id=>val('fe_'+id); const nome=g('nome').trim(); if(!nome){toast('Nome obrigatório');return;}
  const body={acao:'fornecedor_salvar',me:EU&&EU.bitrix_id,id:FORN.edit.id||undefined,nome,categoria:g('categoria'),cidade:g('cidade'),contato:g('contato'),telefone:g('telefone'),whatsapp:g('whatsapp'),email:g('email'),cnpj:g('cnpj'),itens:g('itens'),tipo:g('tipo')};
  try{ const r=await (await fetch('actions/fornecedores.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(body)})).json();
    if(r.error){toast(r.error);return;} toast(r.dedup?'Já existia um fornecedor com esse nome/CNPJ — reaproveitado':'Fornecedor salvo'); FORN.edit=null; fornLoad();
  }catch(e){toast('Falha: '+e.message);}
}
async function fornExcluir(id){ if(!confirm('Excluir este fornecedor?'))return;
  try{ await fetch('actions/fornecedores.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({acao:'fornecedor_excluir',me:EU&&EU.bitrix_id,id})}); FORN.edit=null; fornLoad(); }catch(e){toast('Falha');} }

/* ===== Configuração / Permissões (Bloco 2) ===== */
let CFG={usuarios:[],obras:[]}, NUSER=null;
/* ===================== MÓDULO OBRAS — ficha das obras + de-para entre sistemas ===================== */
let OBRAS_M={tab:'ficha', list:[], is_admin:false, filt:'', fstatus:''};
function obrasInit(){ if(OBRAS_M.list.length){obrasRender();} else obrasLoad(); }
async function obrasLoad(){ const w=document.getElementById('obrasWrap'); if(!w)return; w.innerHTML='<div class="dempty">Carregando obras…</div>';
  try{ const r=await (await fetch('actions/obras.php?lista=1&me='+encodeURIComponent((EU&&EU.bitrix_id)||''))).json();
    if(r.error){w.innerHTML='<div class="dempty">'+esc(r.error)+'</div>';return;}
    OBRAS_M.list=r.obras||[]; OBRAS_M.is_admin=!!r.is_admin; obrasRender();
    if(CAN_EDIT && !OBRAS_M.coligadas){ try{ const rl=await (await fetch('actions/obras.php?coligadas=1&me='+encodeURIComponent((EU&&EU.bitrix_id)||''))).json(); OBRAS_M.coligadas=rl.coligadas||[]; OBRAS_M.capretz_cc=rl.capretz_cc||{}; }catch(e){ OBRAS_M.coligadas=[]; } }
    if(OBRAS_M.is_admin && !OBRAS_M.cronos){ try{ const rc=await (await fetch('actions/obras.php?cronogramas=1&me='+encodeURIComponent((EU&&EU.bitrix_id)||''))).json(); OBRAS_M.cronos=rc.cronogramas||[]; }catch(e){ OBRAS_M.cronos=[]; } }
  }catch(e){w.innerHTML='<div class="dempty">Falha: '+esc(e.message)+'</div>';}
}
function obrStatusChip(s){ const c={'Em Andamento':'var(--verde)','Iniciando':'var(--dourado)','Finalizada':'#8a9299'}[s]||'#8a9299'; return s?`<span class="dchip" style="background:${c}">${esc(s)}</span>`:''; }
function obrCarResumo(o){ const p=[]; if(+o.torres)p.push(o.torres+(+o.torres===1?' torre':' torres')); if(+o.pavimentos)p.push(o.pavimentos+' pav'); if(+o.unidades)p.push(o.unidades+' un'); return p.join(' · '); }
function obrasRender(){ const w=document.getElementById('obrasWrap'); if(!w)return;
  const tab=(t,lbl,ic)=>`<button class="btn-ghost" style="padding:7px 14px;border-radius:9px 9px 0 0;${OBRAS_M.tab===t?'background:#fff;border-bottom:2px solid var(--verde);font-weight:700;color:var(--verde-d)':'color:var(--muted)'}" onclick="OBRAS_M.tab='${t}';obrasRender()"><span class="material-icons" style="font-size:15px;vertical-align:-3px">${ic}</span> ${lbl}</button>`;
  w.innerHTML=`<div style="display:flex;gap:4px;border-bottom:1px solid var(--line);margin-bottom:12px;align-items:center">${tab('ficha','Ficha das Obras','apartment')}${tab('depara','De-para & Configuração','link')}${IS_ADMIN?'<button class="btn-ghost" style="margin-left:auto;padding:6px 12px" onclick="obrasVerificarCrono()" title="Detecta obras cujo cronograma do Planejamento foi reprogramado (XML novo) e re-aponta — mantendo os vínculos"><span class="material-icons" style="font-size:16px;vertical-align:-3px;color:var(--verde)">sync</span> Verificar cronogramas</button>':''}</div>`+(OBRAS_M.tab==='ficha'?obrasTabFicha():obrasTabDepara());
}
/* ===== Verificar/atualizar cronogramas reprogramados (XML novo do Planejamento) — 24/jul/2026 ===== */
async function obrasVerificarCrono(){
  let ov=document.getElementById('vcOv'); if(!ov){ov=document.createElement('div');ov.id='vcOv';ov.style.cssText='position:fixed;inset:0;background:rgba(15,25,20,.45);z-index:9999;display:flex;align-items:center;justify-content:center;padding:20px';ov.onclick=e=>{if(e.target===ov)ov.remove();};document.body.appendChild(ov);}
  ov.innerHTML='<div style="background:#fff;border-radius:14px;padding:18px 20px;max-width:820px;width:100%;box-shadow:0 12px 44px rgba(0,0,0,.22)" onclick="event.stopPropagation()"><div class="dempty">Consultando o Planejamento…</div></div>';
  let d; try{ d=await (await fetch('actions/obras.php?verificar_cronogramas=1&me='+encodeURIComponent(EU&&EU.bitrix_id)+'&_='+Date.now())).json(); }catch(e){ d={error:'Falha ao consultar'}; }
  obrasVcRender(d);
}
function obrasVcRender(d){
  const ov=document.getElementById('vcOv'); if(!ov)return;
  const box='<div style="background:#fff;border-radius:14px;padding:18px 20px;max-width:820px;width:100%;box-shadow:0 12px 44px rgba(0,0,0,.22);max-height:86vh;overflow:auto" onclick="event.stopPropagation()">';
  const head='<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:4px"><b style="font-size:16px"><span class="material-icons" style="font-size:18px;vertical-align:-4px;color:var(--verde)">sync</span> Cronogramas reprogramados</b><span class="material-icons" style="cursor:pointer;color:var(--muted)" onclick="document.getElementById(\'vcOv\').remove()">close</span></div>';
  if(d.error){ ov.innerHTML=box+head+'<div class="dempty">'+esc(d.error)+'</div></div>'; return; }
  if(d.erro_fonte){ ov.innerHTML=box+head+'<div class="dempty">'+esc(d.erro_fonte)+'</div></div>'; return; }
  const ups=d.atualizacoes||[];
  if(!ups.length){ ov.innerHTML=box+head+'<div class="dempty" style="padding:24px">✅ Tudo em dia — nenhuma obra tem cronograma novo do Planejamento pra atualizar.</div></div>'; return; }
  const certas=ups.filter(u=>u.mesma_obra && !(u.orfaos||[]).length);
  const card=u=>{
    const orf=u.orfaos||[]; const semTarefas=orf[0]==='__sem_tarefas__';
    const tag=u.mesma_obra?'<span style="font-size:9px;font-weight:800;padding:1px 6px;border-radius:5px;background:#e6f4ea;color:var(--verde-d)">✅ MESMA OBRA</span>':'<span style="font-size:9px;font-weight:800;padding:1px 6px;border-radius:5px;background:#fdf1dd;color:#a4761c" title="casei por nome — confira antes de aplicar">⚠️ CONFERIR (por nome)</span>';
    const av=semTarefas?'<div style="font-size:11px;color:#a4761c;margin-top:3px">⚠️ não consegui ler o XML novo agora — a conferência de vínculos não rodou</div>'
      :(orf.length?'<div style="font-size:11px;color:var(--pend);margin-top:3px">⚠️ '+orf.length+' vínculo(s) sumiriam no XML novo (nome mudou): '+esc(orf.slice(0,4).join(', '))+(orf.length>4?'…':'')+' — vão ficar sem data até você remarcar</div>':'<div style="font-size:11px;color:var(--verde-d);margin-top:3px">✓ todos os vínculos existem no XML novo — nada quebra</div>');
    return '<div style="border:1px solid var(--line);border-radius:10px;padding:11px 13px;margin-bottom:9px">'
      +'<div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap"><b style="font-size:14px">'+esc(u.obra)+'</b>'+tag+'<span class="muted" style="font-size:11px">'+(u.novo_pct!=null?u.novo_pct+'% · medição '+esc(u.novo_medicao||'—'):'')+'</span>'
      +'<button class="btn-prim" style="margin-left:auto;padding:5px 12px;font-size:12.5px" onclick="obrasCronoAtualizar('+u.obra_id+',\''+esc(u.novo_id)+'\',\''+esc(u.obra.replace(/\x27/g,""))+'\')">Atualizar</button></div>'
      +'<div style="font-size:12px;margin-top:5px;color:#556"><span class="muted">de:</span> '+esc(u.atual_nome)+' <span class="muted">→ para:</span> <b>'+esc(u.novo_nome)+'</b></div>'+av+'</div>';
  };
  const btnTodas=certas.length>1?'<button class="btn-prim" style="padding:6px 13px" onclick="obrasCronoAtualizarTodas()"><span class="material-icons" style="font-size:15px;vertical-align:-3px">done_all</span> Atualizar as '+certas.length+' certas (sem órfão)</button>':'';
  window._vcData=ups;
  ov.innerHTML=box+head
    +'<div class="dmini" style="margin:2px 0 10px">Vínculos são por <b>nome de tarefa</b> → ao atualizar, tudo carrega e só as <b>datas</b> mudam. As <b>✅ mesma obra + sem órfão</b> são seguras.</div>'
    +(btnTodas?'<div style="margin-bottom:10px">'+btnTodas+'</div>':'')
    +ups.map(card).join('')+'</div>';
}
async function obrasCronoAtualizar(obraId,novoId,nome){
  try{ const r=await (await fetch('actions/obras.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({acao:'atualizar_cronograma',me:EU&&EU.bitrix_id,obra_id:obraId,cronograma_id:novoId})})).json();
    if(r.error){toast(r.error);return;}
    const orf=(r.orfaos||[]).filter(x=>x!=='__sem_tarefas__');
    toast((nome||'Obra')+': cronograma atualizado'+(orf.length?' · ⚠️ '+orf.length+' vínculo(s) sem par':' · vínculos ok'));
    obrasVerificarCrono();   // recarrega a lista (a obra atualizada some)
    OBRAS_M.list=[]; try{ if(typeof T20!=='undefined'){T20.data=null;} }catch(e){}   // força recarga do Top 20 na próxima abertura
  }catch(e){toast('Falha ao atualizar');}
}
async function obrasCronoAtualizarTodas(){
  const certas=(window._vcData||[]).filter(u=>u.mesma_obra && !(u.orfaos||[]).length);
  if(!certas.length) return;
  if(!confirm('Atualizar '+certas.length+' obra(s) para o cronograma novo? (todas são a mesma obra e sem vínculo órfão)'))return;
  let ok=0; for(const u of certas){ try{ const r=await (await fetch('actions/obras.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({acao:'atualizar_cronograma',me:EU&&EU.bitrix_id,obra_id:u.obra_id,cronograma_id:u.novo_id})})).json(); if(!r.error)ok++; }catch(e){} }
  toast(ok+' obra(s) atualizada(s)'); try{ if(typeof T20!=='undefined')T20.data=null; }catch(e){} obrasVerificarCrono();
}
/* ===== SELO ILUSTRADO DA OBRA (SVG gerado dos dados: torres/pav/subsolos/áreas comuns) ===== */
function seloAmen(txt){ const t=(txt||'').toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g,'');
  const M=[[/piscina/,'🏊'],[/quadra|beach|poliespor|tenis|squash|society|campo/,'🎾'],[/fitness|academ|crossfit|workout|ginastica/,'💪'],[/biciclet|\bbike\b/,'🚲'],[/\bpet\b/,'🐾'],[/salao|festa/,'🎉'],[/playground|play |brinquedoteca|\bkids\b/,'🛝'],[/cowork/,'💻'],[/\bspa\b|sauna|zen|beaut|massag/,'🧖'],[/jogos|\bgame/,'🎮'],[/churrasq|gourmet|fogo de chao/,'🔥'],[/horta/,'🌱'],[/leitura/,'📚'],[/cinema/,'🎬'],[/lavanderia/,'🧺']];
  const out=[]; M.forEach(a=>{ if(a[0].test(t)&&out.indexOf(a[1])<0)out.push(a[1]); }); return out; }
function seloWins(x,y,w,h,cols,rows){ let s='',pad=7,gx=(w-pad*2)/cols,gy=h/rows,ww=Math.min(gx-4,18),wh=Math.min(gy-6,12); for(let r=0;r<rows;r++)for(let c=0;c<cols;c++){s+=`<rect x="${(x+pad+c*gx+(gx-ww)/2).toFixed(1)}" y="${(y+r*gy+3).toFixed(1)}" width="${ww.toFixed(1)}" height="${wh.toFixed(1)}" rx="2"/>`;} return s; }
function seloTower(cx,topY,groundY,tw,pav){ const x=cx-tw/2,bh=groundY-topY,rows=Math.max(4,Math.min(12,Math.round(bh/18))),cols=tw>84?4:3;
  return `<rect x="${x-4}" y="${topY-8}" width="${tw+8}" height="10" rx="4" fill="#c3ccca"/><rect x="${x}" y="${topY}" width="${tw}" height="${bh}" fill="#e8edf0"/><g fill="#2f6fb0">${seloWins(x,topY+2,tw,bh-6,cols,rows)}</g>`+(pav?`<rect x="${cx-26}" y="${topY-40}" width="52" height="28" rx="8" fill="#173a4c"/><text x="${cx}" y="${topY-20}" font-size="15" font-weight="800" fill="#fff" text-anchor="middle">${pav}</text><path d="M${cx-6} ${topY-12} h12 l-6 6 z" fill="#173a4c"/>`:''); }
function seloHouse(cx,baseY,w){ const h=w*0.62,x=cx-w/2,y=baseY-h; return `<rect x="${x}" y="${(y+h*0.42).toFixed(1)}" width="${w}" height="${(h*0.58).toFixed(1)}" fill="#e8edf0"/><path d="M${x-3} ${(y+h*0.42).toFixed(1)} L${cx} ${y} L${x+w+3} ${(y+h*0.42).toFixed(1)} Z" fill="#cf8a4a"/><rect x="${(cx-w*0.12).toFixed(1)}" y="${(y+h*0.62).toFixed(1)}" width="${(w*0.24).toFixed(1)}" height="${(h*0.38).toFixed(1)}" fill="#2f6fb0"/>`; }
function seloHoriz(o){ return /horizontal|casas/i.test(((o.observacoes||'')+' '+(o.tipologias||''))); }
function obraSeloFull(o){
  const T=+o.torres||0,P=+o.pavimentos||0,S=+o.subsolos||0,pct=o.pct_fisico,horiz=seloHoriz(o),ams=seloAmen(o.areas_comuns);
  if(!(T>0||P>0||horiz||ams.length)) return '';
  const W=480,groundY=250; let build='';
  if(horiz){ for(let i=0;i<4;i++) build+=seloHouse(96+i*96,groundY,74); }
  else { const n=Math.max(1,Math.min(T||1,4)),tw=n>=4?68:(n===3?82:96),span=W-160,step=n>1?span/(n-1):0; for(let i=0;i<n;i++){ const cx=n>1?(80+i*step):W/2; build+=seloTower(cx,72,groundY,tw,P);} if(T>n) build+=`<text x="${W-34}" y="150" font-size="13" font-weight="800" fill="#8a988f" text-anchor="middle">+${T-n}</text>`; }
  let sub='',sn=Math.min(S,3); for(let i=0;i<sn;i++){ const y=groundY+16+i*24; sub+=`<rect x="104" y="${y}" width="272" height="22" rx="3" fill="${i%2?'#2a2e30':'#33383a'}"/><circle cx="240" cy="${y+11}" r="9" fill="#fff"/><text x="240" y="${y+15}" font-size="11" font-weight="800" fill="#33383a" text-anchor="middle">-${i+1}</text>`; }
  if(S>3) sub+=`<text x="392" y="${groundY+40}" font-size="11" fill="#8a988f">+${S-3}</text>`;
  const amsY=groundY+16+sn*24+16; let amc='',cols=['#0f8a8a','#caa32e','#6a5acd','#2f6fb0','#d1495b','#e07a3f','#3a9d5d'],show=ams.slice(0,7);
  show.forEach((ic,i)=>{ amc+=`<g transform="translate(${30+i*54},${amsY})"><rect width="44" height="44" rx="12" fill="${cols[i%cols.length]}"/><text x="22" y="30" font-size="22" text-anchor="middle">${ic}</text></g>`; });
  if(ams.length>7) amc+=`<text x="${30+7*54+4}" y="${amsY+28}" font-size="12" font-weight="700" fill="#8a988f">+${ams.length-7}</text>`;
  const H=amsY+(ams.length?58:0)+8, cap=[T>0?T+(T>1?' torres':' torre'):(horiz?'casas':''),P>0?P+' pav':'',S>0?S+(S>1?' subsolos':' subsolo'):''].filter(Boolean).join(' · ');
  return `<svg viewBox="0 0 ${W} ${H}" style="width:100%;max-width:${W}px;display:block;margin:2px auto" xmlns="http://www.w3.org/2000/svg">
    <rect x="12" y="8" width="${W-24}" height="${groundY-4}" rx="14" fill="#f3f7f5"/>
    ${cap?`<text x="30" y="30" font-size="12.5" font-weight="800" fill="#5a6b62">${esc(cap)}</text>`:''}
    ${pct!=null?`<g transform="translate(${W-150},18)"><rect width="128" height="26" rx="13" fill="var(--verde,#1d9e75)"/><text x="64" y="18" font-size="12" font-weight="800" fill="#fff" text-anchor="middle">🏗️ ${(+pct).toFixed(0)}% executado</text></g>`:''}
    ${build}<rect x="40" y="${groundY}" width="400" height="16" fill="#cdae7a"/><rect x="104" y="${groundY}" width="272" height="16" fill="#3b3f3d"/>${sub}${amc}</svg>`;
}
function obraSeloMini(o){
  const T=+o.torres||0,horiz=seloHoriz(o),ams=seloAmen(o.areas_comuns);
  if(!(T>0||horiz||ams.length)) return '';
  const W=260,H=60,gy=50; let b='';
  if(horiz){ for(let i=0;i<3;i++) b+=seloHouse(22+i*24,gy,20); }
  else { const n=Math.max(1,Math.min(T||1,3)); for(let i=0;i<n;i++){ const cx=22+i*26,tw=20; b+=`<rect x="${cx-tw/2}" y="12" width="${tw}" height="${gy-12}" rx="1" fill="#dfe6ea"/><g fill="#2f6fb0">${seloWins(cx-tw/2,14,tw,gy-16,2,4)}</g>`; } }
  const shown=Math.min(ams.length,5); let am=''; ams.slice(0,5).forEach((ic,i)=>{ am+=`<text x="${W-18-(shown-1-i)*26}" y="32" font-size="18" text-anchor="middle">${ic}</text>`; });
  return `<svg viewBox="0 0 ${W} ${H}" preserveAspectRatio="xMidYMid meet" style="width:100%;height:100%;display:block" xmlns="http://www.w3.org/2000/svg"><rect x="0" y="${gy}" width="${W}" height="3" fill="#cdae7a"/>${b}${am}</svg>`;
}
function obrasTabFicha(){
  const qn=opNorm(OBRAS_M.filt||''); const sts=[...new Set(OBRAS_M.list.map(o=>o.status).filter(Boolean))];
  const rows=OBRAS_M.list.filter(o=>(!qn||opNorm((o.nome||'')+' '+(o.cidade||'')+' '+(o.coligada_nome||'')+' '+(o.comprador_nome||'')+' '+(o.solic_nome||'')).includes(qn))&&(!OBRAS_M.fstatus||o.status===OBRAS_M.fstatus));
  let h=`<div class="panel"><div class="bar" style="gap:8px;flex-wrap:wrap;align-items:center">
    <div class="search" style="min-width:200px"><span class="material-icons" style="color:var(--muted)">search</span><input placeholder="Buscar obra, cidade, coligada, comprador…" value="${esc(OBRAS_M.filt)}" oninput="OBRAS_M.filt=this.value;obrasRender()"></div>
    <select onchange="OBRAS_M.fstatus=this.value;obrasRender()" style="font-size:12px;padding:6px"><option value="">Todos status</option>${sts.map(s=>`<option value="${esc(s)}" ${s===OBRAS_M.fstatus?'selected':''}>${esc(s)}</option>`).join('')}</select>
    <span class="muted" style="font-size:11.5px">${rows.length} de ${OBRAS_M.list.length} obras</span>
    ${CAN_EDIT?'<button class="btn-prim" style="margin-left:auto;padding:7px 13px" onclick="obrasFichaAbrir(0)"><span class="material-icons" style="font-size:15px;vertical-align:-3px">add</span> Nova obra</button>':''}
  </div></div>
  <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(290px,1fr));gap:10px;margin-top:2px">`;
  h+=rows.map(o=>{ const car=obrCarResumo(o); const selo=obraSeloMini(o);
    return `<div class="panel" style="margin:0;cursor:pointer;padding:0;overflow:hidden" onclick="obrasFichaAbrir(${o.id})">
      ${selo?`<div style="height:62px;padding:6px 12px 0;background:#f3f7f5;border-bottom:1px solid var(--line)">${selo}</div>`:''}
      <div style="padding:11px 14px;border-bottom:1px solid var(--line);background:linear-gradient(180deg,#f7faf8,#fff)">
        <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:8px"><b style="font-size:15px">${esc(o.nome)}</b>${obrStatusChip(o.status)}</div>
        <div class="muted" style="font-size:11.5px;margin-top:2px"><span class="material-icons" style="font-size:12px;vertical-align:-2px">place</span> ${esc(o.cidade||'—')}${o.comprador_nome?' · '+esc(o.comprador_nome):''}</div>
      </div>
      <div style="padding:9px 14px">
        <div style="font-size:12px;color:var(--verde-d);font-weight:600;min-height:16px">${car||'<span class="muted" style="font-weight:400">características a preencher</span>'}</div>
        ${o.pct_fisico!=null?`<div style="margin-top:7px"><div style="display:flex;justify-content:space-between;font-size:10.5px;color:var(--muted)"><span>avanço físico</span><span style="font-weight:800;color:var(--verde-d)">${(+o.pct_fisico).toFixed(1).replace('.',',')}%</span></div><div style="height:6px;background:#e6ebe8;border-radius:4px;overflow:hidden;margin-top:2px"><div style="height:100%;width:${Math.max(0,Math.min(100,+o.pct_fisico))}%;background:var(--verde)"></div></div>${o.crono_fim?`<div class="muted" style="font-size:10px;margin-top:3px"><span class="material-icons" style="font-size:11px;vertical-align:-2px">event</span> entrega prev. ${D(String(o.crono_fim).slice(0,10))}</div>`:''}</div>`:''}
        <div class="muted" style="font-size:11px;margin-top:6px">${o.coligada_cod?('Coligada '+o.coligada_cod+(o.coligada_nome?' · '+esc(String(o.coligada_nome).slice(0,28)):'')):'<span style="color:var(--pend)">sem coligada — confira o de-para</span>'}${o.solic_nome&&opNorm(o.solic_nome)!==opNorm(o.nome)?' · <span title="nome diferente nas solicitações">≈ '+esc(o.solic_nome)+'</span>':''}</div>
      </div>
    </div>`; }).join('')||'<div class="dmini" style="padding:10px">Nenhuma obra ainda. Clique em "Nova obra" ou peça ao assistente pra semear do conector.</div>';
  return h+'</div>';
}
function obrasTabDepara(){
  let h=`<div class="panel">${cotSecHead('link','De-para entre sistemas','conector ↔ TOTVS/coligada ↔ solicitações ↔ radar','')}
    <div class="dmini" style="margin-bottom:8px">Casamento automático pelo nome (a razão fantasia do TOTVS traz o codinome, ex.: "PEDRA AZUL - DIAMOND"). Confira; clique na linha p/ ajustar e marcar "conferido".</div>
    <div style="overflow-x:auto"><table class="mtable"><thead><tr><th style="text-align:left">Obra (conector)</th><th>Coligada</th><th style="text-align:left">Razão social (TOTVS)</th><th style="text-align:left">Compra (SC/PC)</th><th>CNPJ</th><th>CODLOC</th><th style="text-align:left">Nome nas solicitações</th><th>OK</th></tr></thead><tbody>`;
  h+=OBRAS_M.list.map(o=>`<tr style="cursor:pointer" onclick="obrasFichaAbrir(${o.id})">
    <td style="text-align:left"><b>${esc(o.nome)}</b></td>
    <td style="text-align:center">${o.coligada_cod?('<b>'+o.coligada_cod+'</b>'):'<span style="color:var(--pend)">?</span>'}</td>
    <td style="text-align:left;font-size:11px">${esc(String(o.coligada_nome||'').slice(0,40))||'—'}</td>
    <td style="text-align:left;font-size:11px">${o.compra_coligada_cod?(String(o.compra_coligada_cod)==='1'?'<span class="dchip" style="background:#e8f0fe;color:#1a56c4" title="compra guarda-chuva pela CAPREM/CAPRETZ">CAPREM</span>':esc(String(o.compra_coligada_nome||('col.'+o.compra_coligada_cod)).slice(0,18)))+(o.centro_custo?' · <b>'+esc(o.centro_custo)+'</b>':''):'<span class="muted">—</span>'}</td>
    <td style="font-size:11px;white-space:nowrap">${esc(o.cnpj||'—')}</td>
    <td>${esc(o.solic_obra_cod||'—')}</td>
    <td style="text-align:left;font-size:11px">${o.solic_nome?esc(o.solic_nome):'<span class="muted">—</span>'}${o.solic_nome&&opNorm(o.solic_nome)!==opNorm(o.nome)?' <span class="dchip" style="background:#fff3e0;color:#a15c00" title="nome diferente do conector">≠</span>':''}</td>
    <td style="text-align:center">${+o.de_para_ok?'<span class="dchip" style="background:var(--ok)">✓</span>':(CAN_EDIT?`<button class="btn-ghost" style="padding:2px 8px" onclick="event.stopPropagation();obrasReresolver(${o.id})" title="refazer o casamento automático">↻</button>`:'—')}</td>
  </tr>`).join('');
  return h+'</tbody></table></div></div>';
}
function obrasFichaAbrir(id){
  const o=id?(OBRAS_M.list.find(x=>x.id===id)||{id:0}):{id:0}; const ed=CAN_EDIT;
  let ov=document.getElementById('obraOverlay'); if(!ov){ov=document.createElement('div');ov.id='obraOverlay';ov.style.cssText='position:fixed;inset:0;background:rgba(15,25,20,.42);z-index:9999;display:flex;align-items:flex-start;justify-content:center;padding:24px;overflow:auto';document.body.appendChild(ov);} ov.onclick=()=>ov.remove();
  const fld=(lbl,i2,val,tipo,ph)=>`<label style="display:block"><span style="font-size:10px;font-weight:700;letter-spacing:.3px;text-transform:uppercase;color:var(--muted)">${lbl}</span>${ed?`<input id="${i2}" value="${esc(val==null?'':val)}" ${tipo==='num'?'type="number"':''} placeholder="${esc(ph||'')}" style="width:100%;margin-top:2px;padding:5px 8px;font-size:13px;box-sizing:border-box">`:`<div style="font-size:13.5px;margin-top:2px">${esc(val==null||val===''?'—':val)}</div>`}</label>`;
  const area=(lbl,i2,val,ph)=>`<label style="display:block"><span style="font-size:10px;font-weight:700;letter-spacing:.3px;text-transform:uppercase;color:var(--muted)">${lbl}</span>${ed?`<textarea id="${i2}" rows="2" placeholder="${esc(ph||'')}" style="width:100%;margin-top:2px;padding:5px 8px;font-size:13px;font-family:inherit;box-sizing:border-box">${esc(val||'')}</textarea>`:`<div style="font-size:13px;margin-top:2px;white-space:pre-wrap">${esc(val||'—')}</div>`}</label>`;
  const bloco=(ic,tit,inner)=>`<div style="border:1px solid var(--line);border-radius:10px;padding:12px 14px;margin-top:10px"><div style="display:flex;align-items:center;gap:6px;margin-bottom:9px"><span class="material-icons" style="font-size:17px;color:var(--verde)">${ic}</span><b style="font-size:13.5px">${tit}</b></div>${inner}</div>`;
  const g=(cols,inner)=>`<div style="display:grid;grid-template-columns:${cols};gap:9px">${inner}</div>`;
  const sel=(lbl,i2,val,opts,ph,onch)=>`<label style="display:block"><span style="font-size:10px;font-weight:700;letter-spacing:.3px;text-transform:uppercase;color:var(--muted)">${lbl}</span>${ed?`<select id="${i2}" ${onch?`onchange="${onch}"`:''} style="width:100%;margin-top:2px;padding:5px 8px;font-size:12.5px;box-sizing:border-box"><option value="">${esc(ph||'—')}</option>${opts.map(op=>`<option value="${esc(op.v)}" ${String(op.v)===String(val==null?'':val)?'selected':''}>${esc(op.t)}</option>`).join('')}</select>`:`<div style="font-size:13.5px;margin-top:2px">${esc((opts.find(op=>String(op.v)===String(val))||{}).t||(val==null||val===''?'—':val))}</div>`}</label>`;
  const colOpts=(OBRAS_M.coligadas||[]).map(c=>({v:c.cod,t:c.fantasia+' ('+c.cod+')'}));
  const ccHint=Object.entries(OBRAS_M.capretz_cc||{}).map(([k,v])=>k+'='+v).join(' · ');
  ov.innerHTML=`<div style="background:#fff;border-radius:14px;padding:18px 20px;max-width:720px;width:100%;box-shadow:0 12px 44px rgba(0,0,0,.22)" onclick="event.stopPropagation()">
    <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:10px">
      <div>${ed?`<input id="obf_nome" value="${esc(o.nome||'')}" placeholder="Nome da obra" style="font-size:18px;font-weight:800;border:none;border-bottom:1px solid var(--line);padding:2px 0;max-width:340px;width:100%">`:`<b style="font-size:18px">${esc(o.nome||'')}</b>`} ${obrStatusChip(o.status)}</div>
      <span class="material-icons" onclick="document.getElementById('obraOverlay').remove()" style="cursor:pointer;color:var(--muted)">close</span></div>
    ${(function(){const s=obraSeloFull(o);return s?`<div style="margin-top:10px;border:1px solid var(--line);border-radius:12px;overflow:hidden">${s}</div>`:'';})()}
    ${bloco('badge','Identificação',g('1fr 80px',fld('Cidade','obf_cidade',o.cidade)+fld('UF','obf_estado',o.estado))+`<div style="margin-top:9px">${fld('Endereço','obf_endereco',o.endereco)}</div>`+g('1fr 1fr',`<div style="margin-top:9px">${fld('Comprador responsável','obf_comprador_nome',o.comprador_nome)}</div><div style="margin-top:9px">${fld('Status','obf_status',o.status)}</div>`))}
    ${(o.pct_fisico!=null||o.crono_fim||(ed&&OBRAS_M.is_admin))?`<div style="border:1px solid var(--line);border-radius:10px;padding:12px 14px;margin-top:10px;background:#f7faf8">
      <div style="display:flex;align-items:center;gap:6px;margin-bottom:9px"><span class="material-icons" style="font-size:17px;color:var(--verde)">timeline</span><b style="font-size:13.5px">Cronograma & avanço físico</b>${o.crono_live?`<span title="lido em tempo real do Supabase do Planejamento" style="margin-left:auto;display:inline-flex;align-items:center;gap:4px;font-size:10px;font-weight:800;letter-spacing:.3px;color:var(--verde-d)"><span style="width:7px;height:7px;border-radius:50%;background:var(--verde);box-shadow:0 0 0 2px rgba(74,140,90,.22)"></span>AO VIVO · PLANEJAMENTO</span>`:`<span class="muted" style="font-size:10.5px;margin-left:auto">${o.pct_fisico!=null?('snapshot'+(o.cronograma_at?' · '+D(String(o.cronograma_at).slice(0,10)):'')):'sem cronograma vinculado'}</span>`}</div>
      ${o.pct_fisico!=null?`<div style="display:flex;align-items:center;gap:10px"><b style="font-size:22px;color:var(--verde-d)">${(+o.pct_fisico).toFixed(1).replace('.',',')}%</b><div style="flex:1;height:9px;background:#e6ebe8;border-radius:5px;overflow:hidden"><div style="height:100%;width:${Math.max(0,Math.min(100,+o.pct_fisico))}%;background:var(--verde)"></div></div></div>`:''}
      ${(o.pct_fisico!=null||o.crono_fim)?`<div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:9px;margin-top:10px">
        <div><span style="font-size:10px;font-weight:700;text-transform:uppercase;color:var(--muted)">Início</span><div style="font-size:13px">${o.crono_inicio?D(String(o.crono_inicio).slice(0,10)):'—'}</div></div>
        <div><span style="font-size:10px;font-weight:700;text-transform:uppercase;color:var(--muted)">Entrega prevista</span><div style="font-size:13px;font-weight:600">${o.crono_fim?D(String(o.crono_fim).slice(0,10)):'—'}</div></div>
        <div><span style="font-size:10px;font-weight:700;text-transform:uppercase;color:var(--muted)">Última medição</span><div style="font-size:13px">${o.crono_medicao?D(String(o.crono_medicao).slice(0,10)):'—'}</div></div>
      </div>`:''}
      ${o.cronograma_nome?`<div class="muted" style="font-size:10.5px;margin-top:8px"><span class="material-icons" style="font-size:12px;vertical-align:-2px">description</span> ${esc(o.cronograma_nome)}</div>`:''}
      ${(ed&&OBRAS_M.is_admin)?`<div style="margin-top:10px;padding-top:9px;border-top:1px dashed var(--line)"><label style="display:block"><span style="font-size:10px;font-weight:700;text-transform:uppercase;color:var(--muted)">Vincular cronograma (Planejamento)</span><select id="obf_crono_obra_id" style="width:100%;margin-top:2px;padding:5px 8px;font-size:12.5px;box-sizing:border-box"><option value="">— automático (casar por nome) —</option>${(OBRAS_M.cronos||[]).map(c=>`<option value="${esc(c.obra_id)}" ${String(c.obra_id)===String(o.crono_obra_id||'')?'selected':''}>${esc(c.nome)}${c.pct!=null?' — '+(+c.pct).toFixed(1).replace('.',',')+'%':''}</option>`).join('')}</select></label><div class="muted" style="font-size:10px;margin-top:4px">Use p/ obras cujo nome não casa sozinho (VS2, VS4, LTB-3, Café Filho…). Deixe automático se já casou certo.</div></div>`:''}
    </div>`:''}
    ${bloco('link','De-para (sistemas)',
        g('1fr 150px',sel('Coligada da obra (TOTVS)','obf_coligada_cod',o.coligada_cod,colOpts,'— escolher —','obrasColigadaChange(this)')+fld('CNPJ','obf_cnpj',o.cnpj))
        +`<div class="muted" style="font-size:10px;margin-top:3px" id="obf_coligada_razao">${esc(o.coligada_nome||'')}</div>`
        +`<div style="margin-top:11px;padding-top:9px;border-top:1px dashed var(--line)"><div style="font-size:10px;font-weight:700;text-transform:uppercase;color:var(--muted);margin-bottom:5px">Como a compra é emitida (SC/PC)</div>`
          +g('1fr 130px',sel('Compra emitida pela coligada','obf_compra_coligada_cod',o.compra_coligada_cod,colOpts,'— igual à obra —')+fld('Centro de custo','obf_centro_custo',o.centro_custo,'text','001'))
          +(ed?`<div class="muted" style="font-size:10px;margin-top:4px">Ex.: Cajá/Espazo/Prades/Piamonte/Licel compram pela <b>CAPREM (CAPRETZ, col.1)</b> com centro de custo. CAPRETZ: ${esc(ccHint)}</div>`:'')
        +`</div>`
        +g('1fr 110px 90px',`<div style="margin-top:11px">${fld('Nome nas solicitações','obf_solic_nome',o.solic_nome)}</div><div style="margin-top:11px">${fld('CODLOC','obf_solic_obra_cod',o.solic_obra_cod)}</div><div style="margin-top:11px">${fld('Radar id','obf_radar_obra_id',o.radar_obra_id,'num')}</div>`)
        +(ed?`<label style="display:flex;align-items:center;gap:6px;margin-top:11px;font-size:12px"><input type="checkbox" id="obf_de_para_ok" ${+o.de_para_ok?'checked':''}> de-para conferido ✓ ${o.id?`<button class="btn-ghost" style="padding:2px 8px;margin-left:6px" onclick="obrasReresolver(${o.id})">↻ re-resolver automático</button>`:''}</label>`:''))}
    <div style="border:1px solid var(--line);border-radius:10px;padding:12px 14px;margin-top:10px">
      <div style="display:flex;align-items:center;gap:6px;margin-bottom:9px"><span class="material-icons" style="font-size:17px;color:var(--verde)">foundation</span><b style="font-size:13.5px">Características do empreendimento</b>${(ed&&OBRAS_M.is_admin&&o.id&&(o.crono_live||o.crono_obra_id))?`<button class="btn-ghost" id="obfExtrairBtn" style="margin-left:auto;padding:3px 10px;font-size:11.5px" onclick="obrasExtrairCrono(${o.id})"><span class="material-icons" style="font-size:14px;vertical-align:-3px">auto_awesome</span> Extrair do cronograma</button>`:''}</div>
      ${g('1fr 1fr 1fr 1fr',fld('Torres','obf_torres',o.torres,'num')+fld('Pavimentos','obf_pavimentos',o.pavimentos,'num')+fld('Subsolos','obf_subsolos',o.subsolos,'num')+fld('Unidades','obf_unidades',o.unidades,'num'))}
      <div style="margin-top:9px">${area('Tipologias / metragens','obf_tipologias',o.tipologias,'ex.: 2 e 3 dorms · 55–78 m²')}</div>
      ${g('1fr 1fr',`<div style="margin-top:9px">${fld('Padrão','obf_padrao',o.padrao,'text','alto / médio / econômico')}</div><div></div>`)}
      <div style="margin-top:9px">${area('Método construtivo','obf_metodo_construtivo',o.metodo_construtivo,'ex.: alvenaria estrutural, concreto armado…')}</div>
      <div style="margin-top:9px">${area('Itens de áreas comuns','obf_areas_comuns',o.areas_comuns,'ex.: piscina, salão de festas, academia, playground…')}</div>
    </div>
    ${bloco('description','Links & observações',g('1fr 1fr',fld('Link do cronograma','obf_link_cronograma',o.link_cronograma)+fld('Pasta de projetos','obf_link_projetos',o.link_projetos))+`<div style="margin-top:9px">${fld('Localização (maps)','obf_link_local',o.link_local)}</div><div style="margin-top:9px">${area('Observações','obf_observacoes',o.observacoes)}</div>`)}
    ${ed?`<div style="display:flex;justify-content:flex-end;gap:8px;margin-top:14px"><button class="btn-ghost" onclick="document.getElementById('obraOverlay').remove()">Cancelar</button><button class="btn-prim" onclick="obrasSalvar(${o.id||0})"><span class="material-icons" style="font-size:15px;vertical-align:-3px">save</span> Salvar ficha</button></div>`:''}
  </div>`;
}
async function obrasSalvar(id){ const v=i=>{const e=document.getElementById(i);return e?(e.type==='checkbox'?(e.checked?1:0):e.value):undefined;};
  const ficha={id}; ['nome','cidade','estado','status','coligada_cod','coligada_nome','cnpj','compra_coligada_cod','centro_custo','solic_nome','solic_obra_cod','radar_obra_id','endereco','comprador_nome','torres','pavimentos','subsolos','unidades','tipologias','metodo_construtivo','areas_comuns','padrao','observacoes','link_cronograma','link_projetos','link_local','de_para_ok','crono_obra_id'].forEach(k=>{const val=v('obf_'+k);if(val!==undefined)ficha[k]=val;});
  if(!(ficha.nome||'').trim()){toast('Informe o nome da obra');return;}
  try{ const r=await (await fetch('actions/obras.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({acao:'salvar',me:EU&&EU.bitrix_id,ficha})})).json();
    if(r.error){toast(r.error);return;} toast('Ficha salva'); const ov=document.getElementById('obraOverlay');if(ov)ov.remove(); obrasLoad();
  }catch(e){toast('Falha: '+e.message);} }
async function obrasReresolver(id){ try{ const r=await (await fetch('actions/obras.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({acao:'reresolver',me:EU&&EU.bitrix_id,id})})).json();
  if(r.error){toast(r.error);return;} toast('De-para refeito automaticamente'); const ov=document.getElementById('obraOverlay');if(ov)ov.remove(); obrasLoad();
  }catch(e){toast('Falha: '+e.message);} }
function obrasColigadaChange(selEl){ const c=(OBRAS_M.coligadas||[]).find(x=>String(x.cod)===String(selEl.value)); const cn=document.getElementById('obf_cnpj'), rz=document.getElementById('obf_coligada_razao'); if(c){ if(cn)cn.value=c.cnpj||''; if(rz)rz.textContent=c.nome||''; } else { if(rz)rz.textContent=''; } }
async function obrasExtrairCrono(id){ const b=document.getElementById('obfExtrairBtn'); if(b){b.disabled=true;b.innerHTML='<span class="material-icons" style="font-size:14px;vertical-align:-3px">hourglass_top</span> Lendo cronograma…';}
  try{ const r=await (await fetch('actions/obras.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({acao:'extrair_caracteristicas',me:EU&&EU.bitrix_id,id})})).json();
    if(r.error){toast(r.error); if(b){b.disabled=false;b.innerHTML='<span class="material-icons" style="font-size:14px;vertical-align:-3px">auto_awesome</span> Extrair do cronograma';} return;}
    const d=r.draft||{}; const set=(i,val)=>{const e=document.getElementById(i); if(e&&val!=null&&String(val).trim()!=='')e.value=val;};
    set('obf_torres',d.torres); set('obf_pavimentos',d.pavimentos); set('obf_subsolos',d.subsolos); set('obf_unidades',d.unidades);
    set('obf_tipologias',d.tipologias); set('obf_metodo_construtivo',d.metodo_construtivo); set('obf_areas_comuns',d.areas_comuns); set('obf_padrao',d.padrao);
    toast('Preenchido do cronograma ('+(r.n_tarefas||0)+' tarefas · confiança '+(d.confianca||'—')+'). Revise e salve.');
    if(b){b.disabled=false;b.innerHTML='<span class="material-icons" style="font-size:14px;vertical-align:-3px">auto_awesome</span> Extrair de novo';}
  }catch(e){toast('Falha: '+e.message); if(b){b.disabled=false;b.innerHTML='<span class="material-icons" style="font-size:14px;vertical-align:-3px">auto_awesome</span> Extrair do cronograma';}} }
/* ========== TOP 20 — volumes consolidados p/ negociação (grupos × 12 meses) ========== */
let T20={data:null,modo:'quant',fin:false,tab:'material',cat:null,cfgSel:null,grupoFiltro:null,exp:new Set(),expData:{}};
function t20Tab(t){ T20.tab=t; T20.grupoFiltro=null; T20.exp=new Set(); const m=document.getElementById('t20TabMat'),s=document.getElementById('t20TabSrv');
  if(m)m.style.fontWeight=t==='material'?'700':'400'; if(s)s.style.fontWeight=t==='servico'?'700':'400';
  if(m)m.style.background=t==='material'?'#fff':''; if(s)s.style.background=t==='servico'?'#fff':'';
  t20Render(); }
async function t20Init(){ if(T20.data) t20Render(); else t20Load(); }
async function t20Load(){
  const w=document.getElementById('t20wrap'); if(w&&!T20.data) w.innerHTML='<div class="empty">Consolidando todas as obras…</div>';
  T20.exp=new Set(); T20.expData={};   // recarregou → dados de expansão viram stale
  try{ T20.data=await (await fetch('actions/top20.php?_='+Date.now()+(T20.fin?'&fin=1':''),{cache:'no-store'})).json(); }
  catch(e){ if(w)w.innerHTML='<div class="empty">Falha ao carregar.</div>'; return; }
  if(T20.data&&T20.data.error){ w.innerHTML='<div class="empty">'+esc(T20.data.error)+'</div>'; T20.data=null; return; }
  t20Render();
}
function t20FinToggle(on){ T20.fin=on; T20.data=null; t20Load(); }
function t20Modo(){ T20.modo=T20.modo==='quant'?'verba':'quant'; const t=document.getElementById('t20ModoTxt'); if(t)t.textContent=T20.modo==='quant'?'Ver R$':'Ver quantitativo'; t20Render(); }
function t20Q(q){ const ks=Object.keys(q||{}); if(!ks.length) return ['','']; const fmt=(v,u)=>Number(v).toLocaleString('pt-BR',{maximumFractionDigits:1})+' '+u;
  ks.sort((a,b)=>(q[b]||0)-(q[a]||0)); const full=ks.map(k=>fmt(q[k],k)).join(' + ');
  return [fmt(q[ks[0]],ks[0])+(ks.length>1?' +':''), full]; }
function t20MesLbl(m){ if(m==='12+')return '12+'; if(m==='sem')return 'sem data'; const N=['jan','fev','mar','abr','mai','jun','jul','ago','set','out','nov','dez']; return N[(+m.split('-')[1])-1]+'/'+m.split('-')[0].slice(2); }
function t20Render(){
  const w=document.getElementById('t20wrap'); if(!w||!T20.data) return;
  const d=T20.data, meses=d.meses||[], M=d.matriz||{}, mesAtual=d.mes_atual||'';
  const lbl=document.getElementById('t20CatLbl'); if(lbl)lbl.textContent=T20.tab==='servico'?'· SERVIÇOS & EQUIPAMENTOS':'· MATERIAIS';
  const tot=g=>{ let v=0; const gm=M[g.id]||{}; for(const k in gm) v+=gm[k].verba||0; return v; };
  const todos=(d.grupos||[]).filter(g=>(g.categoria||'material')===T20.tab).sort((a,b)=>tot(b)-tot(a));
  let gs=T20.grupoFiltro?todos.filter(g=>g.id===T20.grupoFiltro):todos;
  // barra: FILTRAR por 1 grupo (ver só a tabelinha dele)
  const bar='<div style="display:flex;align-items:center;gap:8px;margin-bottom:8px;flex-wrap:wrap"><span class="dmini">Filtrar grupo:</span>'
    +'<select onchange="T20.grupoFiltro=this.value?Number(this.value):null;t20Render()" style="padding:5px 9px;border:1px solid var(--line);border-radius:7px;font-size:12.5px;max-width:360px">'
    +'<option value="">Todos os grupos ('+todos.length+')</option>'+todos.map(g=>'<option value="'+g.id+'" '+(T20.grupoFiltro===g.id?'selected':'')+'>'+esc(g.nome)+(g.modo==='data_final'?' — por data final':'')+'</option>').join('')+'</select>'
    +(T20.grupoFiltro?'<button class="btn-ghost" style="padding:3px 9px;font-size:12px" onclick="T20.grupoFiltro=null;t20Render()">✕ limpar</button>':'')
    +'<span class="dmini" style="margin-left:auto">clique na linha do grupo p/ <b>expandir</b> os itens</span></div>';
  if(!gs.length){ w.innerHTML=bar+'<div class="empty">Nenhum grupo na aba '+(T20.tab==='servico'?'Serviços &amp; Equipamentos':'Materiais')+'.'+(IS_ADMIN?' Use <b>Configurar grupos</b> p/ mover um grupo p/ cá ou criar um novo.':'')+'</div>'; return; }
  const ncols=meses.length+5;
  let h=bar+'<div class="wrap" style="overflow-x:auto"><table class="mtable" style="border:none;min-width:1150px"><thead><tr><th class="svc-h" style="text-align:left;min-width:240px">Grupo de negociação</th>';
  meses.forEach(m=>h+='<th style="min-width:64px">'+t20MesLbl(m)+'</th>');
  h+='<th style="min-width:56px" title="início além de 12 meses">12+</th><th style="min-width:56px" title="itens sem data no cronograma">sem data</th><th style="min-width:92px;background:#eafaf0">TOTAL quant.</th><th style="min-width:104px;background:#eafaf0">TOTAL R$</th></tr></thead><tbody>';
  gs.forEach(g=>{
    const gm=M[g.id]||{}; let tv=0; const tq={}; const df=g.modo==='data_final'; const open=T20.exp.has(g.id);
    const badge=df?'<span style="font-size:8.5px;font-weight:800;padding:1px 5px;border-radius:5px;background:#fdf1dd;color:#a4761c;margin-left:6px" title="valor CHEIO da obra no mês de fechar a cotação (não distribui)">◷ DATA FINAL</span>':'';
    let row='<tr><td class="svc-c" style="text-align:left;cursor:pointer" onclick="t20Expand('+g.id+')"><span class="material-icons" style="font-size:15px;vertical-align:-3px;color:var(--muted)">'+(open?'expand_more':'chevron_right')+'</span><b>'+esc(g.nome)+'</b>'+badge+'<small>'+g.n_servicos+' serviços · '+(df?'por data final':'por consumo')+'</small></td>';
    [...meses,'12+','sem'].forEach(mk=>{
      const c=gm[mk];
      if(!c){ row+='<td class="t20c">—</td>'; return; }
      tv+=c.verba||0; for(const u in (c.quant||{})) tq[u]=(tq[u]||0)+c.quant[u];
      const qr=t20Q(c.quant);
      const val=T20.modo==='quant'?(qr[0]||'<span class="muted" style="font-size:10px">só R$</span>'):BRL(c.verba);
      const alerta=(c.ni>0 && /^\d{4}-\d{2}$/.test(mk) && mk<=mesAtual);   // Não Iniciado em mês já vencido/atual = hora de fechar
      const dot=alerta?' <span title="'+c.ni+' item(ns) Não Iniciado neste mês — hora de fechar" style="display:inline-block;width:6px;height:6px;border-radius:50%;background:var(--pend);vertical-align:1px"></span>':'';
      row+='<td class="t20c t20click" style="'+(alerta?'background:#fff4f4;':'')+'" title="'+esc((qr[1]?qr[1]+' · ':'')+BRL(c.verba)+' · '+c.n+' item(ns)'+(c.ni?' ('+c.ni+' não iniciado)':''))+' — clique p/ a conta" onclick="event.stopPropagation();t20Drill('+g.id+',\''+mk+'\')">'+val+dot+'</td>';
    });
    const tqr=t20Q(tq);
    row+='<td style="background:#f2faf5;font-weight:700;text-align:center" title="'+esc(tqr[1])+'">'+(tqr[0]||'—')+'</td><td style="background:#f2faf5;font-weight:800;text-align:center">'+BRL(tv)+'</td></tr>';
    h+=row;
    if(open) h+='<tr class="t20exp"><td colspan="'+ncols+'" style="background:#fbfdfb;padding:0;border-bottom:2px solid #e6efe9">'+t20ExpBox(g.id)+'</td></tr>';
  });
  h+='</tbody></table></div><div class="note t20-noprint"><b>Por consumo</b> = o que falta consumir, espalhado nos meses (o % já executado sai da conta). <b>◷ Por data final</b> = valor cheio da obra no mês de <b>fechar a cotação</b> (data em obra − lead), sem espalhar — pra elevador, grua, esquadria, M.O. O <b>ponto vermelho</b> = item Não Iniciado num mês que já venceu. Finalizado/Não se aplica ficam fora'+(T20.fin?' — <b>incluídos agora</b>':'')+'. kg em <b>toneladas</b>.</div>';
  w.innerHTML=h;
}
async function t20Expand(gid){
  if(T20.exp.has(gid)){ T20.exp.delete(gid); t20Render(); return; }
  T20.exp.add(gid); t20Render();   // mostra "carregando"
  if(T20.expData[gid]===undefined){
    try{ const d=await (await fetch('actions/top20.php?detalhe='+gid+'&_='+Date.now()+(T20.fin?'&fin=1':''))).json(); T20.expData[gid]=d.detalhe||[]; }
    catch(e){ T20.expData[gid]=[]; }
    if(T20.exp.has(gid)) t20Render();
  }
}
function t20StCor(s){ return {'Finalizado':'#1F6B3B','Em Andamento':'#2b6cb0','Cotação Iniciada':'#a4761c','Com Pendências':'#c0392b','Não se aplica':'#8a9299','Não Iniciado':'#8a9299'}[s]||'#8a9299'; }
/* FILA DE FECHAMENTO — a visão certa para o que se compra DE UMA VEZ (elevador, grua, esquadria, M.O.).
   Aqui a grade de meses não diz nada: o que importa é "qual obra eu preciso fechar primeiro, com quantas
   unidades e quanto de verba". Então vira uma grade IGUAL À DA MATRIZ — uma linha por item, uma coluna por
   obra — só que as obras ordenadas pela DATA DE FECHAR, da mais próxima para a mais distante. */
function t20Fila(gid){
  const its=T20.expData[gid]||[], hoje=today;
  const cor=x=>{ const st=x.status||'Não Iniciado';
    if(st==='Finalizado') return ['#e8f5ee','#1F6B3B'];
    if(st==='Não se aplica') return ['#f4f5f6','#8a9299'];
    if(!x.fim) return ['#f7f8f9','#8a9299'];
    if(x.fim<hoje) return ['#fdeaea','#c0392b'];                                  // já devia ter fechado
    if((new Date(x.fim)-new Date(hoje))/864e5<=60) return ['#fdf4e3','#a4761c'];  // fecha em até 60 dias
    return ['#eef4fb','#2b5fa8']; };
  const qtd=x=>x.quant_total!=null?Number(x.quant_total).toLocaleString('pt-BR',{maximumFractionDigits:1})+' '+(x.unidade||''):null;

  // UMA TABELA POR ITEM. Cada item tem a SUA fila de obras — juntar tudo numa ordem só fazia o cabeçalho
  // ("Licel fecha 24/05/24") contradizer a célula ("28/12/25"), porque a data é por item × obra.
  const porItem={}; its.forEach(x=>{ (porItem[x.item]=porItem[x.item]||[]).push(x); });
  const itens=Object.keys(porItem).sort((a,b)=>{
    const ma=Math.min(...porItem[a].map(x=>x.fim?+new Date(x.fim):Infinity));
    const mb=Math.min(...porItem[b].map(x=>x.fim?+new Date(x.fim):Infinity));
    return ma-mb || a.localeCompare(b,'pt'); });

  let h='<div style="padding:10px 14px 14px">'
    +'<div style="display:flex;align-items:center;gap:9px;flex-wrap:wrap;margin-bottom:10px">'
    +'<span class="material-icons" style="font-size:18px;color:var(--dourado)">timeline</span>'
    +'<b style="font-size:13.5px">Fila de fechamento</b>'
    +'<span class="dmini">este grupo fecha de uma vez — as obras vêm na ordem de <b>quem precisa fechar primeiro</b></span></div>';

  itens.forEach(it=>{
    const linhas=porItem[it].slice().sort((a,b)=>((a.fim||'9999-99-99').localeCompare(b.fim||'9999-99-99'))||a.obra.localeCompare(b.obra,'pt'));
    let tv=0; const tq={}; let nVenc=0;
    linhas.forEach(x=>{ tv+=Number(x.verba_total||0);
      if(x.quant_total!=null){ const u=x.unidade||'un'; tq[u]=(tq[u]||0)+Number(x.quant_total); }
      if(x.fim&&x.fim<hoje&&x.status!=='Finalizado'&&x.status!=='Não se aplica') nVenc++; });
    const qs=Object.entries(tq).map(([u,v])=>v.toLocaleString('pt-BR',{maximumFractionDigits:1})+' '+u).join(' · ');

    h+='<div style="margin-bottom:14px">'
      +'<div style="display:flex;align-items:baseline;gap:9px;flex-wrap:wrap;margin-bottom:5px">'
      +'<b style="font-size:13px">'+esc(it)+'</b>'
      +(qs?'<span class="dchip" style="background:#eef4fb;color:#2b5fa8;font-size:10.5px">'+esc(qs)+'</span>':'')
      +'<span class="dchip" style="background:#eafaf0;color:var(--verde-d);font-size:10.5px">'+BRL(tv)+'</span>'
      +(nVenc?'<span class="dchip" style="background:var(--pend);font-size:10.5px">'+nVenc+' já venceram</span>':'')
      +'<span class="dmini">'+linhas.length+' obra(s)</span></div>'
      +'<div style="overflow-x:auto"><table class="mtable" style="border:none;min-width:'+Math.max(560,linhas.length*128)+'px"><thead><tr>';
    linhas.forEach(x=>{ const atras=x.fim&&x.fim<hoje&&x.status!=='Finalizado'&&x.status!=='Não se aplica';
      h+='<th style="min-width:122px;line-height:1.3">'+esc(x.obra)
        +'<div style="font-size:10px;font-weight:800;color:'+(atras?'var(--pend)':'var(--verde-d)')+';margin-top:2px">'+(x.fim?D(x.fim):'sem data')+'</div></th>'; });
    h+='<th style="min-width:112px;background:#eafaf0">TOTAL</th></tr></thead><tbody><tr>';
    linhas.forEach(x=>{ const [bg,fg]=cor(x);
      h+='<td class="t20c" style="background:'+bg+';padding:7px 6px;vertical-align:top" title="'+esc(it+' · '+x.obra+' · '+(x.status||'')+(x.data_em_obra?' · em obra '+x.data_em_obra:''))+'">'
        +'<div style="font-size:11.5px;font-weight:700">'+(qtd(x)||'<span style="color:#aab">qtd —</span>')+'</div>'
        +'<div style="font-size:12px;font-weight:800;margin-top:1px">'+(Number(x.verba_total)?BRL(x.verba_total):'<span style="color:#aab">verba —</span>')+'</div>'
        +'<div style="font-size:10px;font-weight:700;color:'+fg+';margin-top:3px">'+esc(x.status||'')+'</div>'
        +(x.responsavel?'<div style="font-size:9.5px;color:var(--muted);overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="'+esc(x.responsavel)+'">'+esc(x.responsavel)+'</div>':'<div style="font-size:9.5px;color:var(--pend)">sem responsável</div>')
        +'</td>'; });
    h+='<td style="background:#f2faf5;text-align:center;vertical-align:top;padding:7px 6px">'
      +(qs?'<div style="font-size:11.5px;font-weight:700">'+esc(qs)+'</div>':'')
      +'<div style="font-size:12.5px;font-weight:800">'+BRL(tv)+'</div></td></tr></tbody></table></div></div>';
  });

  const abertos=its.filter(x=>x.status!=='Finalizado'&&x.status!=='Não se aplica');
  const vencidos=abertos.filter(x=>x.fim&&x.fim<hoje);
  const d90=abertos.filter(x=>x.fim&&x.fim>=hoje&&(new Date(x.fim)-new Date(hoje))/864e5<=90);
  const semData=abertos.filter(x=>!x.fim);
  const som=a=>a.reduce((t,x)=>t+Number(x.verba_total||0),0);
  h+='<div style="display:flex;gap:9px;flex-wrap:wrap;margin-top:4px">'
    +t20Pill('var(--pend)','#fdeaea',vencidos.length+' já venceram',BRL(som(vencidos)))
    +t20Pill('#a4761c','#fdf4e3',d90.length+' fecham em 90 dias',BRL(som(d90)))
    +t20Pill('var(--verde-d)','#eafaf0',abertos.length+' em aberto',BRL(som(abertos)))
    +(semData.length?t20Pill('#6a737b','#f2f4f5',semData.length+' sem data',BRL(som(semData))):'')
    +'</div>'
    +'<div class="dmini" style="margin-top:9px">Cada célula é a compra INTEIRA daquela obra. <b>Vermelho</b> já passou da data de fechar · <b>âmbar</b> fecha em até 60 dias · <b>azul</b> mais à frente · <b>verde</b> fechado. Item sem data no cronograma não acende alerta — é ausência de informação, não folga.</div>'
    +'</div>';
  return h;
}
function t20Pill(cor,bg,tit,sub){ return '<div style="background:'+bg+';border-radius:9px;padding:7px 12px;min-width:150px">'
  +'<div style="font-size:12px;font-weight:800;color:'+cor+'">'+esc(tit)+'</div>'
  +'<div style="font-size:11.5px;font-weight:700;color:#33404a">'+sub+'</div></div>'; }

function t20ExpBox(gid){
  const its=T20.expData[gid];
  if(its===undefined) return '<div class="dmini" style="padding:11px 14px">Carregando os itens…</div>';
  if(!its.length) return '<div class="dmini" style="padding:11px 14px">Sem itens neste grupo (no recorte atual).</div>';
  const g=((T20.data&&T20.data.grupos)||[]).find(x=>x.id===gid);
  if(g && g.modo==='data_final' && its.some(x=>x.fim)) return t20Fila(gid);
  const rows=its.map(x=>'<tr><td style="text-align:left">'+esc(x.item)+'</td><td style="text-align:left">'+esc(x.obra)+'</td><td style="white-space:nowrap">'+t20MesLbl(x.mes)+'</td>'
    +'<td style="text-align:left;white-space:nowrap"><span style="color:'+t20StCor(x.status)+';font-weight:700">'+esc(x.status||'—')+'</span></td>'
    +'<td class="muted" style="text-align:left;font-size:11px">'+esc(x.janela||'')+(x.pct_fonte==='data final'?'':(x.consumido?' · andou '+x.consumido+'%':''))+'</td>'
    +'<td class="r">'+(x.alocado_quant!=null?Number(x.alocado_quant).toLocaleString('pt-BR',{maximumFractionDigits:1})+' '+esc(x.unidade||''):'—')+'</td>'
    +'<td class="r"><b>'+BRL(x.alocado_verba)+'</b></td></tr>').join('');
  return '<div style="padding:8px 14px 12px"><table class="dtable" style="width:100%"><thead><tr><th style="text-align:left">Item</th><th style="text-align:left">Obra</th><th>Mês</th><th style="text-align:left">Status</th><th style="text-align:left">Janela / fecha</th><th class="r">Quant.</th><th class="r">Verba</th></tr></thead><tbody>'+rows+'</tbody></table></div>';
}
async function t20Drill(gid,mes){
  const g=(T20.data.grupos||[]).find(x=>x.id===gid);
  let d; try{ d=await (await fetch('actions/top20.php?detalhe='+gid+'&mes='+encodeURIComponent(mes)+'&_='+Date.now()+(T20.fin?'&fin=1':''))).json(); }catch(e){ toast('Falha'); return; }
  const its=d.detalhe||[];
  let ov=document.getElementById('t20Ov'); if(!ov){ov=document.createElement('div');ov.id='t20Ov';ov.style.cssText='position:fixed;inset:0;background:rgba(15,25,20,.45);z-index:9999;display:flex;align-items:flex-start;justify-content:center;padding:24px;overflow:auto';document.body.appendChild(ov);} ov.onclick=e=>{if(e.target===ov)ov.remove();};
  const andou=x=>{ if(x.pct_fonte==='cronograma') return 'andou <b>'+x.consumido+'%</b>'; if(x.pct_fonte==='tempo') return '~'+x.consumido+'% (tempo)'; return x.consumido>0?x.consumido+'%':'0%'; };
  const rows=its.map(x=>'<tr><td>'+esc(x.obra)+'</td><td style="text-align:left">'+esc(x.item)+'</td><td style="text-align:right">'+(x.alocado_quant!=null?Number(x.alocado_quant).toLocaleString('pt-BR',{maximumFractionDigits:1})+' '+esc(x.unidade||''):'—')+'</td><td style="text-align:right">'+BRL(x.alocado_verba)+'</td><td style="font-size:11px" class="muted">'+esc(x.janela)+'<br><span style="font-size:10px">'+esc(x.fonte||'')+'</span></td><td style="font-size:11px;text-align:center">'+andou(x)+(x.fracao<1?'<br><span class="muted" style="font-size:10px">'+Math.round(x.fracao*100)+'% do item neste mês</span>':'')+'</td><td style="text-align:right;font-size:11px" class="muted">'+(x.quant_total!=null?Number(x.quant_total).toLocaleString('pt-BR',{maximumFractionDigits:1})+' '+esc(x.unidade||''):'—')+' · '+BRL(x.verba_total)+'</td></tr>').join('');
  ov.innerHTML='<div style="background:#fff;border-radius:14px;padding:18px 20px;max-width:1060px;width:100%;box-shadow:0 12px 44px rgba(0,0,0,.22)" onclick="event.stopPropagation()">'
   +'<div style="display:flex;justify-content:space-between;align-items:center"><b style="font-size:16px">'+esc(g?g.nome:'Grupo')+' — '+t20MesLbl(mes)+' · a conta</b><span class="material-icons" style="cursor:pointer;color:var(--muted)" onclick="document.getElementById(\'t20Ov\').remove()">close</span></div>'
   +'<div class="muted" style="font-size:12px;margin:4px 0 10px">'+its.length+' aporte(s). A janela é o <b>grande marco</b> (fase inteira no cronograma vivo — nunca só uma data). O % já andado sai da conta; o restante distribui do mês atual até o fim da janela.</div>'
   +'<div style="overflow:auto;max-height:70vh"><table class="mtable" style="border:none;width:100%"><thead><tr><th>Obra</th><th style="text-align:left">Item</th><th>Quant. no mês</th><th>R$ no mês</th><th>Janela do marco</th><th>Andamento</th><th>Total do item</th></tr></thead><tbody>'+(rows||'<tr><td colspan="7" class="empty">Nada neste mês.</td></tr>')+'</tbody></table></div></div>';
}
async function t20Cfg(){
  if(!IS_ADMIN){ toast('Só administradores configuram os grupos'); return; }
  if(!T20.cat){ try{ T20.cat=(await (await fetch('actions/top20.php?catalogo=1')).json()).servicos||[]; }catch(e){ toast('Falha ao carregar catálogo'); return; } }
  t20CfgRender();
}
function t20CfgRender(selId){
  const gs=(T20.data&&T20.data.grupos)||[];
  let ov=document.getElementById('t20Cf'); if(!ov){ov=document.createElement('div');ov.id='t20Cf';ov.style.cssText='position:fixed;inset:0;background:rgba(15,25,20,.45);z-index:9999;display:flex;align-items:flex-start;justify-content:center;padding:20px;overflow:auto';document.body.appendChild(ov);} ov.onclick=e=>{if(e.target===ov)ov.remove();};
  const g=gs.find(x=>x.id===selId)||gs[0];
  T20.cfgSel=g?{id:g.id,nome:g.nome,ordem:g.ordem,categoria:g.categoria||'material',modo:g.modo||'consumo',servicos:(g.servicos||[]).slice()}:null;
  T20.cfgFiltro=''; T20.cfgSoSel=false;   // reset do filtro/toggle ao trocar de grupo
  t20CfgDraw();
}
function t20CfgDraw(){
  const ov=document.getElementById('t20Cf'); if(!ov)return; const gs=(T20.data&&T20.data.grupos)||[]; const cur=T20.cfgSel;
  const list=gs.map(x=>'<div class="pickrow" style="'+(cur&&x.id===cur.id?'background:#eef6f0;':'')+'cursor:pointer" onclick="t20CfgRender('+x.id+')"><b style="font-size:12px">'+esc(x.nome)+'</b><span style="font-size:9px;font-weight:800;padding:1px 5px;border-radius:6px;margin-left:6px;'+((x.categoria||'material')==='servico'?'background:#fdf1dd;color:#a4761c':'background:#e7f2ff;color:#2b6cb0')+'">'+((x.categoria||'material')==='servico'?'SRV':'MAT')+'</span><span class="muted" style="font-size:11px;margin-left:auto">'+x.n_servicos+'</span></div>').join('');
  const nSel=(cur&&cur.servicos||[]).length;
  const right=cur?('<div style="display:grid;grid-template-columns:1fr 200px;gap:8px;margin-bottom:6px"><label><span style="font-size:10px;font-weight:700;color:var(--muted)">NOME DO GRUPO</span><input id="t20gNome" value="'+esc(cur.nome)+'" oninput="if(T20.cfgSel)T20.cfgSel.nome=this.value" style="width:100%;padding:6px 8px;box-sizing:border-box"></label>'
   +'<label><span style="font-size:10px;font-weight:700;color:var(--muted)">ABA</span><select id="t20gCat" onchange="if(T20.cfgSel)T20.cfgSel.categoria=this.value" style="width:100%;padding:6px 8px;box-sizing:border-box"><option value="material"'+(cur.categoria!=='servico'?' selected':'')+'>Materiais</option><option value="servico"'+(cur.categoria==='servico'?' selected':'')+'>Serviços &amp; Equipamentos</option></select></label></div>'
   +'<label style="display:block;margin-bottom:6px"><span style="font-size:10px;font-weight:700;color:var(--muted)">CONSIDERAÇÃO NO TOP 20</span><select id="t20gModo" onchange="if(T20.cfgSel)T20.cfgSel.modo=this.value" style="width:100%;padding:6px 8px;box-sizing:border-box"><option value="consumo"'+((cur.modo||"consumo")!=="data_final"?" selected":"")+'>Por consumo — distribui o valor nos meses conforme o cronograma (aço, concreto, tinta, piso, porcelanato…)</option><option value="data_final"'+((cur.modo||"consumo")==="data_final"?" selected":"")+'>Por data final — valor CHEIO da obra no mês de fechar a cotação (elevador, grua, esquadria, mão de obra…)</option></select></label>'
   +'<div style="display:flex;gap:8px;margin:6px 0;align-items:center;flex-wrap:wrap"><div class="search" style="flex:1;min-width:180px"><span class="material-icons" style="color:var(--muted)">search</span><input id="t20CfgFiltro" value="'+esc(T20.cfgFiltro||'')+'" placeholder="filtrar serviços do catálogo…" oninput="T20.cfgFiltro=this.value;t20CfgSvcRender()"></div>'
   +'<label style="display:flex;align-items:center;gap:5px;font-size:12px;cursor:pointer;white-space:nowrap" title="mostrar só os serviços marcados neste grupo"><input type="checkbox" '+(T20.cfgSoSel?'checked':'')+' onchange="T20.cfgSoSel=this.checked;t20CfgSvcRender()"> só considerados <b id="t20CfgCount" style="color:var(--verde-d)">('+nSel+')</b></label></div>'
   +'<div id="t20CfgSvcs" style="max-height:44vh;overflow:auto;border:1px solid var(--line);border-radius:8px;padding:6px"></div>'
   +'<div style="display:flex;gap:8px;margin-top:10px"><button class="btn-prim" onclick="t20CfgSalvar()">Salvar grupo</button>'+(cur.id?'<button class="btn-ghost" style="color:var(--pend)" onclick="t20CfgExcluir()">Excluir</button>':'')+'</div>')
   :'<div class="empty">Escolha um grupo à esquerda.</div>';
  ov.innerHTML='<div style="background:#fff;border-radius:14px;padding:18px 20px;max-width:1040px;width:100%;box-shadow:0 12px 44px rgba(0,0,0,.22)" onclick="event.stopPropagation()">'
   +'<div style="display:flex;justify-content:space-between;align-items:center"><b style="font-size:16px">Configurar grupos de negociação</b><span class="material-icons" style="cursor:pointer;color:var(--muted)" onclick="document.getElementById(\'t20Cf\').remove()">close</span></div>'
   +'<div style="display:grid;grid-template-columns:290px 1fr;gap:14px;margin-top:10px">'
   +'<div><div style="max-height:50vh;overflow:auto">'+list+'</div><div style="display:flex;gap:6px;margin-top:8px;flex-wrap:wrap"><button class="btn-ghost" onclick="t20CfgNovo()">+ Novo grupo</button><button class="btn-ghost" style="color:var(--pend)" onclick="t20Reseed()" title="apaga TUDO e volta aos 20 grupos sugeridos">↺ Padrão</button></div></div>'
   +'<div>'+right+'</div></div></div>';
  t20CfgSvcRender();   // popula a lista de serviços (container próprio — filtro não recria o modal)
}
// renderiza SÓ a lista de serviços (#t20CfgSvcs) — filtro por texto + "só considerados"; não recria o modal (foco preservado)
function t20CfgSvcRender(){
  const cur=T20.cfgSel; if(!cur)return; const box=document.getElementById('t20CfgSvcs'); if(!box)return;
  const sel=new Set(cur.servicos||[]); const f=(T20.cfgFiltro||'').toLowerCase().trim();
  let svs=(T20.cat||[]).filter(s=>!f||((s.nome+' '+(s.grupo||'')).toLowerCase().includes(f)));
  if(T20.cfgSoSel) svs=svs.filter(s=>sel.has(s.id));
  box.innerHTML=svs.length?svs.map(s=>'<label style="display:flex;gap:7px;align-items:flex-start;font-size:12px;padding:2px 0;cursor:pointer"><input type="checkbox" '+(sel.has(s.id)?'checked':'')+' onchange="t20CfgTog('+s.id+',this.checked)"><span>'+esc(s.nome)+' <span class="muted" style="font-size:10px">· '+esc(s.grupo||'')+'</span></span></label>').join('')
    :'<div class="dmini" style="padding:8px">'+(T20.cfgSoSel?'Nenhum serviço marcado neste grupo ainda.':'Nenhum serviço casa o filtro.')+'</div>';
}
function t20CfgTog(id,on){ const c=T20.cfgSel; if(!c)return; const i=c.servicos.indexOf(id); if(on&&i<0)c.servicos.push(id); if(!on&&i>=0)c.servicos.splice(i,1);
  const cnt=document.getElementById('t20CfgCount'); if(cnt)cnt.textContent='('+c.servicos.length+')';
  if(T20.cfgSoSel) t20CfgSvcRender();   // no modo "só considerados", desmarcar tira da lista na hora
}
function t20CfgNovo(){ T20.cfgSel={id:0,nome:'Novo grupo',ordem:99,categoria:T20.tab,modo:T20.tab==='servico'?'data_final':'consumo',servicos:[]}; t20CfgDraw(); }
async function t20CfgSalvar(){ const c=T20.cfgSel; if(!c)return; const nome=(document.getElementById('t20gNome')||{}).value||c.nome;
  const categoria=(document.getElementById('t20gCat')||{}).value||c.categoria||'material';
  const modo=(document.getElementById('t20gModo')||{}).value||c.modo||'consumo';
  try{ const r=await (await fetch('actions/top20.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({acao:'salvar_grupo',me:EU&&EU.bitrix_id,id:c.id||null,nome,categoria,modo,servicos:c.servicos,ordem:c.ordem||0})})).json();
    if(r&&r.error){toast(r.error);return;} toast('Grupo salvo'); const ov=document.getElementById('t20Cf'); if(ov)ov.remove(); T20.data=null; t20Load();
  }catch(e){toast('Falha ao salvar');} }
async function t20CfgExcluir(){ const c=T20.cfgSel; if(!c||!c.id)return; if(!confirm('Excluir o grupo "'+c.nome+'"?'))return;
  try{ await fetch('actions/top20.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({acao:'excluir_grupo',me:EU&&EU.bitrix_id,id:c.id})}); const ov=document.getElementById('t20Cf'); if(ov)ov.remove(); T20.data=null; t20Load(); }catch(e){toast('Falha');} }
async function t20Reseed(){ if(!confirm('Apagar TODOS os grupos e voltar aos 20 sugeridos?'))return;
  try{ await fetch('actions/top20.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({acao:'reseed',me:EU&&EU.bitrix_id})}); const ov=document.getElementById('t20Cf'); if(ov)ov.remove(); T20.data=null; t20Load(); }catch(e){toast('Falha');} }

const MENUS=[['dashboard','Dashboard'],['radar','Radar de Aquisições'],['matriz','Matriz'],['cotacoes','Cotações'],['solicitacoes','Solicitações'],['envio','Envio de Pedidos'],['buscaped','Busca Pedidos'],['obras','Obras'],['oportunidades','Oportunidades'],['top20','Top 20'],['updates','Atualizações'],['audit','Auditoria'],['config','Configurações'],
  /* telas de CONSULTA da obra (papel 'obra') — leitura, sem nenhuma ação */
  ['ov_radar','Obra: O que vem por aí'],['ov_cotacoes','Obra: Cotações'],['ov_solicitacoes','Obra: Solicitações']];
const PAPEL_LABEL={admin:'Administrador',diretor:'Diretor',gerente:'Gerente de Suprimentos',comprador:'Suprimentos',coordenador:'Coordenador',obra:'Obra (consulta)',personalizado:'Personalizado'};
const PRESETS={
  admin:{ver:'todas',edit:'todas',menus:['dashboard','radar','matriz','cotacoes','config'],adm:1},
  diretor:{ver:'todas',edit:'nenhuma',menus:['dashboard','radar','matriz','cotacoes'],adm:0},
  gerente:{ver:'todas',edit:'todas',menus:['dashboard','radar','matriz','cotacoes','solicitacoes','obras','oportunidades','top20'],adm:0},
  comprador:{ver:'todas',edit:'sel',menus:['radar','matriz','cotacoes'],adm:0},
  coordenador:{ver:'sel',edit:'nenhuma',menus:['radar','matriz'],adm:0},
  /* OBRA = consulta pura (engenheiro/coordenador de obra). Vê todas as obras; menus vazio até as 3
     telas de consulta existirem. A trava de escrita é no SERVIDOR (sup_veta_leitor_em_post no
     db.php) — esconder menu no cliente nunca foi trava. */
  obra:{ver:'todas',edit:'nenhuma',menus:['ov_radar','ov_cotacoes','ov_solicitacoes'],adm:0},
  personalizado:null,
};

async function getCurrentUser(){
  let bid=null, via='fallback';
  const isLocal=(location.hostname==='localhost'||location.hostname==='127.0.0.1');
  if(!isLocal && window.BX24){
    try{ bid=await new Promise(r=>{ BX24.init(()=>BX24.callMethod('user.current',{},x=>r((x.data()||{}).ID))); setTimeout(()=>r(null),5000); }); }catch(e){}
    if(bid) via='bx24';
  }
  if(!bid) bid='20'; // localhost dev OU não identificado → admin Murilo (provisório — ver indicador "Você" na barra)
  try{ const p=await (await fetch('actions/usuarios.php?me='+encodeURIComponent(bid))).json(); EU=Object.assign({bitrix_id:bid,via},p); IS_ADMIN=!!p.perm_admin; }
  catch(e){ EU={bitrix_id:bid,via,autorizado:true,perm_admin:1,editar_escopo:'todas',menus:MENUS.map(m=>m[0])}; IS_ADMIN=true; }
  CAN_EDIT = IS_ADMIN || (EU && (EU.editar_escopo==='todas'
              || (EU.editar_escopo==='sel' && (EU.obras_editar||[]).map(Number).includes(1))));
  // fornecedor é LISTA-MESTRE compartilhada: liberado por PAPEL (compradores reclamavam do botão sumido
  // porque estavam sem edição de obra — coisa que não deveria mandar no cadastro de fornecedor)
  CAN_FORN = IS_ADMIN || ['gerente','comprador'].includes((EU&&EU.papel)||'') || CAN_EDIT;
  /* CAN_COT = trabalho de SUPRIMENTOS: cotar, anotar, mudar o status de uma solicitação. É por
     PAPEL, não pelo escopo de edição de obra — comprador sem obra atribuída continua sendo
     comprador. Era isso que sumia o campo de anotação e o botão de cotação para o Gabriel; mesma
     decisão já tomada em fornecedores (lista-mestre compartilhada). */
  CAN_COT  = IS_ADMIN || ['gerente','comprador'].includes((EU&&EU.papel)||'') || CAN_EDIT;
  // permissões específicas de vínculo/curadoria: valem pela PRÓPRIA flag (a permissão já É o grão fino) —
  // NÃO exigem "Edita obras" (decisão 23/jul: editar_escopo é só p/ o menu Obras/estrutura). Gerente e admin têm tudo.
  const _ger = ((EU&&EU.papel)||'')==='gerente';
  CAN_CRONO = IS_ADMIN || _ger || !!(EU && EU.perm_crono);
  CAN_ORC   = IS_ADMIN || _ger || !!(EU && EU.perm_orcamento);
  CAN_QUANT = IS_ADMIN || _ger || !!(EU && EU.perm_quant);
  CAN_DIC   = IS_ADMIN || _ger || !!(EU && EU.perm_dicionario);
  CAN_RESP  = IS_ADMIN || _ger || !!(EU && EU.perm_responsaveis);   // atribuir responsável em lote (independe de editar_escopo)
  applyMenus(); updateWhoami();
}
function updateWhoami(){
  const el=document.getElementById('whoami'); if(!el)return;
  if(!EU){ el.innerHTML=''; return; }
  const papel = EU.autorizado===false ? 'sem acesso' : (PAPEL_LABEL[EU.papel]||EU.papel||(EU.perm_admin?'Administrador':'—'));
  const ok = EU.via==='bx24';
  el.innerHTML=`<div class="wname">${esc(EU.nome||('Usuário '+EU.bitrix_id))}</div>
    <div>#${esc(EU.bitrix_id)} · ${esc(papel)}</div>
    <div class="wsrc${ok?'':' bad'}">${ok?'identificado via Bitrix':'fallback — não identificado'}</div>`;
}
function applyMenus(){
  const auth = !!(EU&&EU.autorizado);
  const allow = auth?(EU.menus||[]):[];
  // Admin com uma SELEÇÃO de menus definida → respeita a seleção dele (pode esconder itens de si mesmo p/ "pintar a tela").
  // Config e Radar IA ficam sempre visíveis p/ admin (evita se trancar / oráculo é leitura).
  const adminSel = IS_ADMIN && Array.isArray(EU&&EU.menus);
  document.querySelectorAll('.nav a[data-menu]').forEach(a=>{
    const m=a.getAttribute('data-menu');
    let show;
    if(m==='oraculo'||m==='solicitacoes'||m==='obras') show = auth;        // Radar IA + Solicitações + Obras (referência) p/ todo autorizado
    else if(m==='config') show = IS_ADMIN||allow.includes('config')||CAN_RESP;  // Config nunca some p/ admin
    else if(adminSel) show = allow.includes(m);                            // admin escolheu → mostra só o marcado
    else show = IS_ADMIN||allow.includes(m);
    a.style.display = show?'':'none';
  });
  const bn=document.getElementById('btnNovo'); if(bn) bn.style.display=CAN_EDIT?'':'none'; // só quem edita cria item
  // LANDING (pedido 23/jul): a TELA INICIAL é o DASHBOARD pra todo mundo que tem esse menu liberado — não o Radar.
  // O painel atribuído (EU.dashboard) segue definindo a ABA inicial; sem atribuição cai na 1ª aba permitida do papel.
  const dashVis = (adminSel ? allow.includes('dashboard') : (IS_ADMIN || allow.includes('dashboard')));
  if(!window._dashLanded && EU && EU.autorizado && dashVis){ window._dashLanded=1; try{ showView('dashboards'); }catch(e){} }
}
function toggleSide(){
  const app=document.getElementById('app');
  const c=!app.classList.contains('sidecollapsed');
  app.classList.toggle('sidecollapsed', c);
  try{ localStorage.setItem('sideCollapsed', c?'1':'0'); }catch(e){}
}
/* ===== Config » sub-aba Aprendizado (receitas) ===== */
let RCDATA=null, RC_OPEN=new Set();
function cfgTab(t){
  const canR = IS_ADMIN || (typeof CAN_RESP!=='undefined' && CAN_RESP);
  // só admin vê Usuários & Aprendizado; Responsáveis abre p/ admin OU perm_responsaveis
  document.getElementById('cfgtab-users').style.display = IS_ADMIN?'':'none';
  document.getElementById('cfgtab-receitas').style.display = IS_ADMIN?'':'none';
  document.getElementById('cfgtab-resp').style.display = canR?'':'none';
  const eb=document.getElementById('cfgtab-email'); if(eb) eb.style.display = IS_ADMIN?'':'none';
  const pb=document.getElementById('cfgtab-pedmail'); if(pb) pb.style.display = IS_ADMIN?'':'none';
  const ac=document.getElementById('cfgtab-acessos'); if(ac) ac.style.display = IS_ADMIN?'':'none';
  const ak=document.getElementById('cfgtab-api'); if(ak) ak.style.display = IS_ADMIN?'':'none';
  const permitida={users:IS_ADMIN, receitas:IS_ADMIN, resp:canR, email:IS_ADMIN, pedmail:IS_ADMIN, acessos:IS_ADMIN, api:IS_ADMIN};
  if(!permitida[t]) t = IS_ADMIN?'users':(canR?'resp':'users');
  document.getElementById('cfg-users').style.display = t==='users'?'':'none';
  document.getElementById('cfg-receitas').style.display = t==='receitas'?'':'none';
  document.getElementById('cfg-resp').style.display = t==='resp'?'':'none';
  const ce=document.getElementById('cfg-email'); if(ce) ce.style.display = t==='email'?'':'none';
  const cp=document.getElementById('cfg-pedmail'); if(cp) cp.style.display = t==='pedmail'?'':'none';
  const ca=document.getElementById('cfg-acessos'); if(ca) ca.style.display = t==='acessos'?'':'none';
  const ck=document.getElementById('cfg-api'); if(ck) ck.style.display = t==='api'?'':'none';
  const ab=document.getElementById('cfgAddBtn'); if(ab) ab.style.display = (t==='users'&&IS_ADMIN)?'':'none';
  const lb=document.getElementById('cfgLoteBtn'); if(lb) lb.style.display = (t==='users'&&IS_ADMIN)?'':'none';
  ['users','resp','receitas','pedmail','email','acessos','api'].forEach(x=>{ const b=document.getElementById('cfgtab-'+x); if(b){ b.style.background = x===t?'var(--verde)':''; b.style.color = x===t?'#fff':''; } });
  if(t==='receitas') renderReceitas();
  if(t==='resp') renderRespLote();
  if(t==='email') cfgEmailLoad();
  if(t==='pedmail') pmLoad();
  if(t==='acessos') cfgAcessosLoad();
  if(t==='api') cfgApiLoad();
}

/* ===== Config » Chaves de API =====
   A API (actions/api.php) é SOMENTE LEITURA e serve sistemas externos — hoje o Cockpit de Obras.
   O segredo aparece UMA ÚNICA VEZ, na criação: depois só fica no arquivo do servidor
   (data/.api_keys.json, fora do git). Não existe "ver de novo" de propósito — se perdeu, revoga e
   cria outra. É por isso que esta tela mostra só os 6 últimos caracteres.
   Uma chave dá acesso a TODOS os dados de suprimentos de TODAS as obras: trate como senha. */
let APIK=null;
async function cfgApiLoad(){
  const w=document.getElementById('cfgApiWrap'); if(!w) return;
  w.innerHTML='<div class="dempty">Carregando chaves…</div>';
  try{
    const r=await (await fetch('actions/api.php',{method:'POST',headers:{'Content-Type':'application/json'},
      body:JSON.stringify({me:(EU&&EU.bitrix_id),acao:'chave_listar'})})).json();
    if(r.error){ w.innerHTML='<div class="dempty">'+esc(r.error)+'</div>'; return; }
    APIK=r.chaves||[]; cfgApiRender();
  }catch(e){ w.innerHTML='<div class="dempty">Falha: '+esc(e.message)+'</div>'; }
}
function cfgApiRender(){
  const w=document.getElementById('cfgApiWrap');
  const ativas=(APIK||[]).filter(c=>!c.revogada);
  let h=cotSecHead('vpn_key','Chaves de API',
    'acesso de LEITURA aos dados de suprimentos por sistemas externos — trate cada chave como uma senha',
    '<button class="btn-prim" onclick="cfgApiCriar()" style="padding:5px 13px">'
    +'<span class="material-icons" style="font-size:15px;vertical-align:-3px">add</span> Nova chave</button>');
  h+='<div class="dmini" style="margin:0 0 12px;padding:9px 12px;border-left:4px solid var(--dourado);background:#fdf9ec;border-radius:0 8px 8px 0">'
   + 'A chave vale para <b>todas as obras</b> e para todos os recursos de leitura da API. Ela deve viver '
   + '<b>no servidor</b> do sistema que consome (nunca no JavaScript do navegador, nunca em query string). '
   + 'O segredo aparece <b>uma única vez</b>, na criação — guarde na hora.</div>';
  if(!(APIK||[]).length){
    h+='<div class="dempty">Nenhuma chave criada. A API responde 503 enquanto não existir nenhuma.</div>';
    w.innerHTML=h; return;
  }
  h+='<div class="wrap"><table class="dtable" style="width:100%;font-size:12.5px"><thead><tr>'
   + '<th>Nome</th><th>Final</th><th>Criada em</th><th>Último uso</th><th>Situação</th><th></th></tr></thead><tbody>';
  for(const c of APIK){
    h+='<tr'+(c.revogada?' style="opacity:.5"':'')+'>'
     + '<td><b>'+esc(c.nome||'—')+'</b></td>'
     + '<td class="muted" style="font-family:monospace">'+esc(c.final||'')+'</td>'
     + '<td class="muted">'+(c.criada_em?cotFmtDT(c.criada_em):'—')+'</td>'
     + '<td class="muted">'+(c.ultimo_uso?esc(c.ultimo_uso):'<i>nunca usada</i>')+'</td>'
     + '<td>'+(c.revogada?'<span class="dchip" style="background:#8a9299">Revogada</span>'
                        :'<span class="dchip" style="background:var(--ok)">Ativa</span>')+'</td>'
     + '<td style="text-align:right">'+(c.revogada?''
        :'<button class="btn-ghost" style="padding:3px 9px;font-size:11px;color:#c0392b" '
        +'onclick="cfgApiRevogar('+jsArg(c.id)+','+jsArg(c.nome||'')+')">Revogar</button>')+'</td></tr>';
  }
  h+='</tbody></table></div>';
  h+='<div class="dmini" style="margin-top:10px">'+ativas.length+' chave(s) ativa(s). '
   + 'Revogar é imediato e não tem volta: quem estiver usando aquela chave para de receber dados na hora.</div>';
  w.innerHTML=h;
}
function cfgApiCriar(){
  dlgAbrir('Configurações','Nova chave de API',
    '<div style="max-width:520px"><div class="dmini" style="margin-bottom:10px">'
   + 'Dê um nome que diga QUEM vai usar — é por ele que você vai saber o que revogar depois '
   + '(ex.: "Cockpit de Obras", "Power BI").</div>'
   + cotFld('Nome do sistema','<input id="apikNome" placeholder="ex.: Cockpit de Obras" style="width:100%">')
   + '<div class="bar" style="justify-content:flex-end;gap:8px;margin-top:14px">'
   + '<button class="btn-ghost" onclick="closeModal(true)">Cancelar</button>'
   + '<button class="btn-prim" onclick="cfgApiCriarSalvar()">Criar chave</button></div></div>');
}
async function cfgApiCriarSalvar(){
  const nome=((document.getElementById('apikNome')||{}).value||'').trim();
  if(!nome){ toast('Dê um nome à chave'); return; }
  try{
    const r=await (await fetch('actions/api.php',{method:'POST',headers:{'Content-Type':'application/json'},
      body:JSON.stringify({me:(EU&&EU.bitrix_id),acao:'chave_criar',nome:nome})})).json();
    if(r.error){ toast(r.error); return; }
    /* ÚNICA vez que o segredo existe fora do servidor. Sem "copiei" não fecha: fechar sem copiar
       significa criar outra chave, e chave órfã ativa é exatamente o que não se quer. */
    dlgAbrir('Configurações','Chave criada — copie agora',
      '<div style="max-width:600px">'
     + '<div style="border-left:4px solid #c0392b;background:#fdf1ef;padding:10px 13px;border-radius:0 8px 8px 0;font-size:12.5px;margin-bottom:12px">'
     + '<b>Esta chave não será mostrada de novo.</b> Se fechar sem copiar, ela fica ativa e inútil — '
     + 'aí o certo é revogar e criar outra.</div>'
     + cotFld('Chave de '+esc(nome),
         '<input id="apikVal" readonly value="'+esc(r.chave)+'" style="width:100%;font-family:monospace;font-size:12px" onclick="this.select()">')
     + '<div class="dmini" style="margin-top:8px">Guarde no servidor que vai consumir (ex.: uma constante '
     + 'no <code>config.php</code> daquele sistema). Envie no header <code>X-API-Key</code>.</div>'
     + '<div class="bar" style="justify-content:flex-end;gap:8px;margin-top:14px">'
     + '<button class="btn-prim" onclick="cfgApiCopiar()">Copiar chave</button>'
     + '<button class="btn-ghost" onclick="closeModal(true);cfgApiLoad()">Já copiei, fechar</button></div></div>');
  }catch(e){ toast('Falha: '+e.message); }
}
function cfgApiCopiar(){
  const i=document.getElementById('apikVal'); if(!i) return;
  i.select();
  const ok=()=>toast('Chave copiada — cole agora no sistema que vai usar');
  if(navigator.clipboard&&navigator.clipboard.writeText) navigator.clipboard.writeText(i.value).then(ok).catch(()=>{try{document.execCommand('copy');ok();}catch(e){toast('Copie manualmente (Ctrl+C)');}});
  else { try{ document.execCommand('copy'); ok(); }catch(e){ toast('Copie manualmente (Ctrl+C)'); } }
}
async function cfgApiRevogar(id,nome){
  if(!confirm('Revogar a chave "'+nome+'"?\n\nQuem estiver usando ela para de receber dados imediatamente. Não tem volta.')) return;
  try{
    const r=await (await fetch('actions/api.php',{method:'POST',headers:{'Content-Type':'application/json'},
      body:JSON.stringify({me:(EU&&EU.bitrix_id),acao:'chave_revogar',id:id})})).json();
    if(r.error){ toast(r.error); return; }
    toast('Chave "'+nome+'" revogada'); cfgApiLoad();
  }catch(e){ toast('Falha: '+e.message); }
}

/* ===================== CONTROLE DE ACESSOS =====================
   O cockpit não registrava NADA de uso até hoje. O ping abaixo é o que passa a alimentar a aba
   "Acessos" em Configurações. Regras que ele respeita, por ordem de importância:
   1) NUNCA atrapalhar a navegação — é fire-and-forget, erro é engolido, ninguém espera resposta;
   2) não repetir a mesma tela em sequência (trocar de aba dentro da tela não conta de novo);
   3) o servidor agrega por (usuário × tela × dia), então o volume fica pequeno de propósito. */
let ACC_ULTIMA='';
function accPing(tela){
  if(!tela || tela===ACC_ULTIMA) return;
  ACC_ULTIMA=tela;
  const me=(EU&&EU.bitrix_id)||''; if(!me) return;
  try{ fetch('actions/acessos.php',{method:'POST',headers:{'Content-Type':'application/json'},
       body:JSON.stringify({acao:'ping',me,tela}),keepalive:true}).catch(()=>{}); }catch(e){}
}

const ACC_DIAS_LBL={7:'últimos 7 dias',30:'últimos 30 dias',90:'últimos 90 dias'};
let ACC={dias:30, data:null, aberto:null};
async function cfgAcessosLoad(){
  const w=document.getElementById('cfgAcessosWrap'); if(!w) return;
  w.innerHTML='<div class="dempty">Carregando o uso do sistema…</div>';
  try{ ACC.data=await (await fetch('actions/acessos.php?relatorio=1&dias='+ACC.dias+'&me='+encodeURIComponent((EU&&EU.bitrix_id)||'')+'&_='+Date.now())).json(); }
  catch(e){ w.innerHTML='<div class="empty">Falha ao carregar os acessos.</div>'; return; }
  if(ACC.data&&ACC.data.error){ w.innerHTML='<div class="empty">'+esc(ACC.data.error)+'</div>'; return; }
  cfgAcessosRender();
}
function accDiasSel(n){ ACC.dias=n; cfgAcessosLoad(); }
function accToggle(bid){ ACC.aberto=(ACC.aberto===bid?null:bid); cfgAcessosRender(); }
function accQuando(iso){
  if(!iso) return '—';
  const d=new Date(iso); if(isNaN(d)) return '—';
  const dias=Math.floor((new Date(today+'T00:00:00')-new Date(String(iso).slice(0,10)+'T00:00:00'))/864e5);
  const hora=String(d.getHours()).padStart(2,'0')+':'+String(d.getMinutes()).padStart(2,'0');
  if(dias<=0) return 'hoje '+hora;
  if(dias===1) return 'ontem '+hora;
  return D(String(iso).slice(0,10))+' · há '+dias+'d';
}
function cfgAcessosRender(){
  const w=document.getElementById('cfgAcessosWrap'), d=ACC.data; if(!w||!d) return;
  const U=d.usuarios||[], N=d.nunca_entraram||[], T=d.telas||[], S=d.serie||[];
  const btn=(n)=>`<button class="btn-ghost" style="padding:5px 12px;font-size:12.5px;${ACC.dias===n?'background:var(--verde);color:#fff;font-weight:700':''}" onclick="accDiasSel(${n})">${ACC_DIAS_LBL[n]}</button>`;

  // sem NENHUM dado = a medição acabou de começar. Dizer isso é mais útil que mostrar zeros.
  if(!d.total_aberturas){
    w.innerHTML=`<div style="display:flex;gap:6px;margin-bottom:11px">${btn(7)}${btn(30)}${btn(90)}</div>
      <div class="dempty" style="padding:26px">Ainda não há nenhum acesso registrado.<br>
      <span class="dmini">O cockpit não guardava uso até agora — a contagem começa a partir de hoje, conforme as pessoas forem entrando. Volte aqui em alguns dias.</span></div>`;
    return;
  }

  const dashUsa=U.filter(u=>u.usa_dashboard>0).length;
  const topTela=T[0]?T[0].label:'—';
  const kpi=(v,l,cor)=>`<div class="dkpi"><div class="v" ${cor?`style="color:${cor}"`:''}>${v}</div><div class="l">${l}</div></div>`;

  let h=`<div style="display:flex;gap:6px;align-items:center;flex-wrap:wrap;margin-bottom:11px">${btn(7)}${btn(30)}${btn(90)}
    <span class="dmini" style="margin-left:auto">medindo desde ${d.medindo_desde?D(d.medindo_desde):'—'}</span></div>
  <div class="dkpis">
    ${kpi(d.pessoas_ativas+' de '+d.cadastrados,'pessoas que entraram')}
    ${kpi(d.total_aberturas,'telas abertas')}
    ${kpi(dashUsa+' de '+d.pessoas_ativas, 'abriram o Dashboard', dashUsa===d.pessoas_ativas?'var(--ok)':'var(--dourado)')}
    ${kpi(N.length, 'nunca entraram', N.length?'var(--pend)':'var(--ok)')}
    ${kpi(esc(topTela),'tela mais usada')}
  </div>`;

  // telas mais usadas
  h+=`<div class="dcard wide" style="margin-top:12px">${cotSecHead('bar_chart','Telas mais abertas','no período','')}
    ${dashBars(T.map(t=>({label:t.label, v:t.n, color:t.tela==='dashboards'?'var(--dourado)':'var(--verde)', sub:t.pct+'%'})))}
    <div class="dmini" style="margin-top:8px">Em dourado o <b>Dashboards</b> — é a tela em que as pessoas caem ao entrar, então ela naturalmente aparece alto. O que diz se está sendo <i>usada</i> é a coluna "% no Dashboard" da tabela abaixo: se a pessoa abre o painel e sai direto pro Radar, o número dela fica baixo.</div></div>`;

  // por pessoa
  h+=`<div class="dcard wide" style="margin-top:12px">${cotSecHead('person','Quem está usando','clique numa linha p/ ver as telas dela','')}
    <div style="overflow-x:auto"><table class="dtable"><thead><tr><th>Pessoa</th><th>Papel</th><th class="r">Telas abertas</th><th class="r">Dias ativos</th><th class="r">% no Dashboard</th><th>Último acesso</th></tr></thead><tbody>`;
  U.forEach(u=>{
    const ab=ACC.aberto===u.bitrix_id;
    h+=`<tr style="cursor:pointer" onclick="accToggle('${esc(u.bitrix_id)}')">
      <td><span class="material-icons" style="font-size:14px;vertical-align:-3px;color:var(--muted)">${ab?'expand_more':'chevron_right'}</span> <b>${esc(u.nome)}</b></td>
      <td><span class="dchip" style="background:#eef4fb;color:#2b5fa8;font-size:10px">${esc(u.papel||'—')}</span></td>
      <td class="r">${u.aberturas}</td>
      <td class="r">${u.dias_ativos}</td>
      <td class="r"><b style="color:${u.pct_dashboard>=20?'var(--ok)':(u.usa_dashboard?'#c77f1a':'var(--pend)')}">${u.usa_dashboard?u.pct_dashboard+'%':'nunca abriu'}</b></td>
      <td style="white-space:nowrap">${esc(accQuando(u.ultimo_em))}</td></tr>`;
    if(ab) h+=`<tr><td colspan="6" style="background:#fbfdfb;padding:9px 16px">
      ${dashBars((u.telas||[]).map(t=>({label:t.label, v:t.n, color:t.tela==='dashboards'?'var(--dourado)':'var(--verde)', sub:t.pct+'%'})))}</td></tr>`;
  });
  h+=`</tbody></table></div></div>`;

  // nunca entraram — a informação mais acionável da tela
  if(N.length){
    h+=`<div class="dcard wide" style="margin-top:12px">${cotSecHead('person_off','Cadastrados que não entraram no período',N.length+' pessoa(s)','')}
      <div style="display:flex;gap:7px;flex-wrap:wrap">${N.map(u=>`<span class="dchip" style="background:${u.ativo?'var(--pend)':'#8a9299'};font-size:11px" title="${u.ativo?'usuário ativo que não usou o sistema':'usuário inativo'}">${esc(u.nome)}${u.ativo?'':' (inativo)'}</span>`).join('')}</div>
      <div class="dmini" style="margin-top:8px">Usuário ativo que nunca abriu nenhuma tela: ou não sabe que o cockpit existe, ou não achou o link. Inativos aparecem em cinza e são esperados.</div></div>`;
  }

  // movimento por dia
  if(S.length>1){
    const max=Math.max(...S.map(x=>x.aberturas))||1;
    h+=`<div class="dcard wide" style="margin-top:12px">${cotSecHead('show_chart','Movimento por dia','telas abertas · pessoas distintas','')}
      <div style="display:flex;align-items:flex-end;gap:3px;height:92px;overflow-x:auto;padding-top:4px">
      ${S.map(x=>`<div title="${D(x.dia)} — ${x.aberturas} tela(s), ${x.pessoas} pessoa(s)" style="min-width:11px;flex:1;background:var(--verde);border-radius:3px 3px 0 0;height:${Math.max(3,Math.round(100*x.aberturas/max))}%"></div>`).join('')}
      </div>
      <div style="display:flex;justify-content:space-between" class="dmini"><span>${D(S[0].dia)}</span><span>${D(S[S.length-1].dia)}</span></div></div>`;
  }

  h+=`<div class="note">Registro de <b>uso de tela</b>: guarda que a pessoa abriu a tela X no dia Y, quantas vezes e a que horas — não o que ela fez dentro. Fica ${180} dias e some. Só administrador vê esta aba.</div>`;
  w.innerHTML=h;
}
/* ===== Configurações › E-mail (disparo): conta SMTP + envio-teste ===== */
async function cfgEmailLoad(){ const w=document.getElementById('cfgEmailWrap'); if(!w)return; w.innerHTML='<div class="dempty">Carregando…</div>';
  try{ const cfg=await (await fetch('actions/email.php?config=1&me='+encodeURIComponent((EU&&EU.bitrix_id)||''))).json();
    if(cfg.error){ w.innerHTML='<div class="panel"><div class="empty">'+esc(cfg.error)+'</div></div>'; return; }
    const cronUrl=cfg.cron_token?new URL('actions/inbox.php?cron='+encodeURIComponent(cfg.cron_token),location.href).href:'';
    w.innerHTML=`<div class="panel" style="max-width:640px">
      ${cotSecHead('mail','Conta de e-mail (envio + leitura)','SMTP dispara as cotações; IMAP lê as respostas — mesma conta/senha (fica só no servidor)','<span class="dchip" style="background:'+(cfg.configurada?'var(--ok)':'var(--pend)')+'">'+(cfg.configurada?'configurada ✓':'falta a senha')+'</span>')}
      <div style="display:grid;grid-template-columns:1fr 100px 110px;gap:10px">${cotFld('Servidor','<input id="ceHost" value="'+esc(cfg.host||'')+'" style="width:100%">')}${cotFld('Porta SMTP','<input id="cePort" type="number" value="'+esc(cfg.port||465)+'" style="width:100%">')}${cotFld('Porta IMAP','<input id="ceImapPort" type="number" value="'+esc(cfg.imap_port||993)+'" style="width:100%" title="leitura das respostas (Fase 4)">')}</div>
      <div style="margin-top:8px">${cotFld('Usuário (e-mail remetente)','<input id="ceUser" value="'+esc(cfg.user||'')+'" style="width:100%">')}</div>
      <div style="margin-top:8px">${cotFld('Senha (vazio mantém a atual)','<input id="ceSenha" type="password" autocomplete="new-password" placeholder="••••••••" style="width:100%">')}</div>
      <div style="margin-top:10px"><button class="btn-prim" onclick="cfgEmailSalvar()"><span class="material-icons" style="font-size:15px;vertical-align:-3px">save</span> Salvar conta</button></div>
      <div style="margin-top:16px;border-top:1px solid var(--line);padding-top:12px">${cotSecHead('outbox','Enviar um teste (SMTP)','manda um e-mail de teste pra você conferir se o envio funciona','')}
        <div style="display:flex;gap:8px;align-items:flex-end;flex-wrap:wrap"><div style="flex:1;min-width:220px">${cotFld('Para (seu e-mail)','<input id="ceTeste" placeholder="voce@email.com" style="width:100%">')}</div>
        <button class="btn-prim" onclick="cfgEmailTeste()"><span class="material-icons" style="font-size:15px;vertical-align:-3px">send</span> Enviar teste</button></div></div>
      <div style="margin-top:14px;border-top:1px solid var(--line);padding-top:12px">${cotSecHead('mark_email_unread','Testar leitura (IMAP)','conecta na caixa e conta as mensagens (incl. Spam/Lixo) — não lê conteúdo nem usa IA','<button class="btn-ghost" onclick="cfgImapTeste()"><span class="material-icons" style="font-size:15px;vertical-align:-3px">sync</span> Testar leitura</button>')}<div id="ceImapRes" class="dmini" style="margin-top:2px"></div></div>
      ${cronUrl?`<div style="margin-top:14px;border-top:1px solid var(--line);padding-top:12px">${cotSecHead('schedule','Varredura automática (cron)','o servidor busca respostas sozinho — configure UMA vez no cPanel','')}
        <div class="dmini">No cPanel › <b>Cron Jobs</b>, adicione uma tarefa <b>a cada 1 hora</b> com este comando:</div>
        <div id="cronCmd" style="margin-top:5px;background:#f4f7f5;border:1px solid var(--line);border-radius:8px;padding:8px 10px;font-size:11px;word-break:break-all;font-family:monospace">wget -q -O /dev/null "${esc(cronUrl)}"</div>
        <div style="margin-top:6px"><button class="btn-ghost" style="padding:4px 10px" onclick="navigator.clipboard.writeText(document.getElementById('cronCmd').textContent).then(()=>toast('Comando copiado')).catch(()=>toast('Copie manualmente'))"><span class="material-icons" style="font-size:14px;vertical-align:-3px">content_copy</span> Copiar comando</button></div>
        <div class="dmini" style="margin-top:6px;color:var(--muted)">É um link secreto (token) — não compartilhe. Enquanto o cron não roda, a busca também dispara sozinha ao abrir o módulo Cotações.</div></div>`:''}
    </div>`;
  }catch(e){ w.innerHTML='<div class="panel"><div class="empty">Falha ao carregar.</div></div>'; } }
async function cfgEmailSalvar(){ const g=id=>((document.getElementById(id)||{}).value||'');
  try{ const r=await (await fetch('actions/email.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({acao:'config',me:EU&&EU.bitrix_id,host:g('ceHost'),port:Number(g('cePort'))||465,imap_port:Number(g('ceImapPort'))||993,user:g('ceUser'),senha:g('ceSenha')})})).json();
    if(r.error){toast(r.error);return;} toast('Conta salva'); cfgEmailLoad(); }catch(e){toast('Falha: '+e.message);} }
async function cfgEmailTeste(){ const to=((document.getElementById('ceTeste')||{}).value||'').trim(); if(!to){toast('Informe seu e-mail');return;}
  toast('Enviando teste…');
  try{ const r=await (await fetch('actions/email.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({acao:'enviar',me:EU&&EU.bitrix_id,teste:to,cotacao_id:0,assunto:'Teste de envio — Cockpit de Suprimentos',corpo:'Este é um e-mail de teste do disparo de cotações. Se você recebeu, o envio por SMTP está funcionando.\n\nCockpit de Suprimentos — Caprem'})})).json();
    if(r.error){toast(r.error);return;} toast(r.msg||'Teste enviado'); }catch(e){toast('Falha: '+e.message);} }
async function cfgImapTeste(){ const el=document.getElementById('ceImapRes'); if(el){el.textContent='Conectando…';el.style.color='var(--muted)';}
  try{ const r=await (await fetch('actions/inbox.php?probe=1&me='+encodeURIComponent((EU&&EU.bitrix_id)||''))).json();
    if(r.error){ if(el){el.textContent='❌ '+r.error;el.style.color='var(--pend)';} return; }
    if(el){ el.textContent='✅ Conectou em '+(r.host||'')+':'+(r.porta||993)+' — '+r.mensagens+' mensagem(ns) na caixa.'; el.style.color='var(--verde-d)'; } }
  catch(e){ if(el){el.textContent='Falha: '+e.message;el.style.color='var(--pend)';} } }




/* =========================== ENVIO DE PEDIDOS DE COMPRA ===========================
   Hoje isso e manual: o comprador confere o TOTVS duas ou tres vezes por semana, separa os PDFs,
   monta e-mail por e-mail e depois move o arquivo de "Emitidos" para "Enviados". Medindo a caixa
   pedidos@ a gente achou o custo disso: 52 pedidos foram enviados DUAS vezes e 6 pedidos de
   regularizacao ("lancar") chegaram ao fornecedor, que pode entregar o material de novo.

   A tela obedece as quatro regras do Murilo, e cada uma virou um mecanismo diferente:

     1. nunca enviar nao aprovado  -> a fila NASCE do filtro "Aprovado" no Fluig. Nao ha botao que
                                      alcance um pendente: ele nem chega ao navegador.
     2. nunca enviar pra obra errada -> obra sem ficha ou sem endereco vai para BLOQUEADOS. O
                                      sistema nunca chuta a obra, e a identidade do pedido e
                                      COLIGADA+NUMERO (o numero sozinho se repete entre coligadas).
     3. nunca enviar 2x            -> livro-caixa no banco com chave unica. O que ja saiu some da
                                      fila e so volta por reenvio explicito, com justificativa.
     4. nunca deixar de enviar     -> ATRASADOS e o primeiro numero da tela. Nada sai da fila pelo
                                      tempo: so sai enviado, ou segurado por alguem, com motivo.

   Por isso a unidade da tela e o E-MAIL (obra x fornecedor), e nao o pedido solto: e assim que o
   comprador ja trabalha ("mando um email com 3 anexos se for a mesma obra"). */
let ENV={d:null, aba:'fila', obra:'', busca:'', sel:{}};   // obra = filtro; sel = selecao em massa
function envMe(){ return encodeURIComponent((EU&&EU.bitrix_id)||''); }
async function envInit(){ if(ENV.d) { envRender(); return; } envCarregar(); }
async function envCarregar(){ const w=document.getElementById('envWrap'); if(!w) return;
  /* voltar para a lista encerra qualquer assistente de lote em aberto (ex.: fechou o modal no meio) */
  ENV.wiz=null;
  w.innerHTML='<div class="dempty">Lendo os pedidos aprovados no TOTVS e conferindo as travas...</div>';
  try{ ENV.d=await (await fetch('actions/envio.php?me='+envMe()+'&_='+Date.now())).json(); }
  catch(e){ w.innerHTML='<div class="panel"><div class="empty">Falha ao carregar: '+esc(e.message)+'</div></div>'; return; }
  if(ENV.d.error){ w.innerHTML='<div class="panel"><div class="empty">'+esc(ENV.d.error)+'</div></div>'; return; }
  envRender();
}
function envAba(a){ ENV.aba=a; envRender(); }

function envCard(cor,valor,rot,sub,aba,ativo){
  return '<div onclick="envAba(\''+aba+'\')" style="cursor:pointer;flex:1;min-width:150px;border:1px solid '
   + (ativo?cor:'var(--line)')+';border-left:5px solid '+cor+';border-radius:10px;padding:10px 14px;background:'
   + (ativo?'#f6faf8':'#fff')+'">'
   + '<div style="font-size:23px;font-weight:700;line-height:1.1;color:'+cor+'">'+valor+'</div>'
   + '<div style="font-size:12.5px;font-weight:600;margin-top:2px">'+rot+'</div>'
   + '<div class="dmini" style="color:var(--muted);margin-top:1px">'+sub+'</div></div>';
}

function envRender(){ const w=document.getElementById('envWrap'), d=ENV.d; if(!w||!d) return;
  const c=d.contadores||{};
  let h='<div class="bar" style="justify-content:space-between;align-items:flex-start;margin-bottom:10px"><div>'
   + '<h1 class="h1"><span class="material-icons" style="color:var(--dourado)">outgoing_mail</span> Envio de Pedidos de Compra</h1>'
   + '<p class="sub">Sai daqui so o que esta <b>aprovado no Fluig</b>, para a <b>obra certa</b>, uma <b>unica vez</b>. Nada some da fila sozinho.</p></div>'
   + '<button class="btn-ghost" onclick="ENV.d=null;envCarregar()" style="flex:0 0 auto;margin-top:4px"><span class="material-icons" style="font-size:18px;vertical-align:-4px">refresh</span> Atualizar</button></div>';

  h+=envMarco();

  h+='<div class="bar" style="gap:10px;flex-wrap:wrap;margin-bottom:10px">'
   + envCard('var(--ok)', (c.envelopes||0), 'E-mails prontos', (c.pedidos||0)+' pedidos - '+BRLc(c.valor||0), 'fila', ENV.aba==='fila')
   + envCard('var(--dourado)', (c.atrasados||0), 'Aprovados atrasados', 'parados ha mais de '+(d.atraso_dias||3)+' dias', 'fila', false)
   + envCard('#c0392b', (c.bloqueados||0), 'Bloqueados', 'nao podem sair - veja o motivo', 'bloq', ENV.aba==='bloq')
   + envCard('var(--muted)', (c.segurados||0), 'Segurados', 'alguem segurou de proposito', 'seg', ENV.aba==='seg')
   + envCard('#6b7c93', (c.sede||0), 'Compras da sede', 'sem canteiro - fluxo proprio', 'bloq', false)
   /* MEDIÇÃO (ainda não decide nada): quantos da fila já têm nota lançada no TOTVS. Mandar um
      desses ao fornecedor é pedir segunda entrega — é o mesmo risco do "(LANÇAR)", só que aqui
      vem do status do pedido, que é fato, e não de adivinhar pela descrição. */
   + envCard('#8a6d1f', (c.pedidos_faturados||0), 'Ja faturados no TOTVS',
       (c.envelopes_faturados||0)+' e-mail(s) so com faturados'
       + ((c.pedidos_parciais||0)?(' - '+c.pedidos_parciais+' parcial(is)'):''), 'fila', false)
   + '</div>';

  /* Legenda das QUATRO bolinhas que aparecem em cada linha. A versao anterior explicava as regras
     internas do sistema ("livro-caixa impede o segundo envio"), que e jargao meu e nao ajuda quem
     olha a fila: o que a pessoa precisa e saber o que cada icone da LINHA quer dizer. */
  const lg=(t)=>'<span style="display:inline-flex;align-items:center;gap:5px;font-size:11.5px">'
    + '<span class="material-icons" style="font-size:15px;color:var(--ok)">check_circle</span>'+t+'</span>';
  h+='<div class="panel" style="padding:9px 14px;margin-bottom:10px"><div class="bar" style="gap:20px;flex-wrap:wrap">'
   + '<span class="dmini" style="font-weight:600">As quatro marcas de cada linha:</span>'
   + lg('aprovado no Fluig') + lg('obra conferida') + lg('nunca enviado ao fornecedor')
   + lg('PDF do pedido gerado')
   + '<span class="dmini" style="color:var(--muted)">a marca vazia mostra o que ainda falta</span>'
   + '</div></div>';

  h+='<div class="bar" style="gap:6px;margin-bottom:10px">'
   + envAbaBtn('fila','outbox','Fila de envio',c.envelopes||0)
   + envAbaBtn('bloq','block','Bloqueados',c.bloqueados||0)
   + envAbaBtn('seg','pan_tool','Segurados',c.segurados||0)
   + envAbaBtn('arq','inventory_2','Arquivados',c.arquivados||0)
   + envAbaBtn('hist','history','Historico',null)
   + '</div>';

  if(ENV.aba==='fila') h+=envFila();
  else if(ENV.aba==='bloq') h+=envBloq();
  else if(ENV.aba==='seg') h+=envSeg();
  else if(ENV.aba==='arq') h+='<div class="panel" id="envArqWrap"><div class="dempty">Carregando...</div></div>';
  else h+='<div class="panel" id="envHistWrap"><div class="dempty">Carregando o historico...</div></div>';
  w.innerHTML=h;
  if(ENV.aba==='hist') envHist();
  if(ENV.aba==='arq') envArq();
}
function envAbaBtn(k,ic,lbl,n){ const on=ENV.aba===k;
  return '<button class="btn-ghost" onclick="envAba(\''+k+'\')" style="padding:6px 13px'+(on?';background:var(--verde);color:#fff':'')+'">'
   + '<span class="material-icons" style="font-size:16px;vertical-align:-4px">'+ic+'</span> '+lbl+(n!==null?' ('+n+')':'')+'</button>'; }

/* ---- MARCO ZERO ----
   Medimos: so nos ultimos 120 dias existem 4.049 pedidos aprovados, e quase todos ja sairam a mao.
   O livro-caixa, porem, nasce vazio. Ligar o disparo sem um corte reenviaria tudo — a violacao mais
   cara da regra 3. Reconciliar o passado pelo e-mail nao resolve (o numero do PC se repete entre
   coligadas, e 2.067 dos 2.651 e-mails colhidos nao casaram). Entao o corte e por DATA e explicito. */
function envMarco(){ const d=ENV.d, m=d.marco||'';
  if(!m) return '<div class="panel" style="border-left:4px solid #c0392b;background:#fdf1ef;margin-bottom:10px">'
   + cotSecHead('flag','Falta definir o marco zero','sem ele a automacao nao pode ser ligada','')
   + '<div style="font-size:13px;line-height:1.55">A fila esta enxergando <b>tudo o que foi aprovado nos ultimos '
   + (d.janela_dias||120)+' dias</b> — e quase tudo isso <b>ja foi enviado a mao</b> pelos compradores. '
   + 'O registro de envios comeca vazio, entao o sistema nao tem como saber sozinho o que ja saiu.<br><br>'
   + 'Escolha a data a partir da qual <b>o sistema assume o envio</b>. Tudo aprovado antes dela fica como '
   + 'processo manual e <b>nunca</b> entra nesta fila.</div>'
   + '<div class="bar" style="gap:8px;margin-top:11px;align-items:flex-end">'
   + '<div style="max-width:190px">'+cotFld('O sistema envia a partir de','<input type="date" id="envMarcoD" value="'+esc(new Date().toISOString().slice(0,10))+'" style="width:100%">')+'</div>'
   + '<button class="btn-prim" onclick="envMarcoSalvar()" style="padding:6px 14px">Definir marco zero</button></div></div>';
  return '<div class="panel" style="padding:8px 14px;margin-bottom:10px"><div class="bar" style="justify-content:space-between;gap:10px;flex-wrap:wrap">'
   + '<div class="dmini"><span class="material-icons" style="font-size:15px;vertical-align:-3px;color:var(--verde)">flag</span> '
   + 'A fila so considera pedidos aprovados a partir de <b>'+esc(pmData(m))+'</b>. O que veio antes e do processo manual.</div>'
   + '<button class="btn-ghost" style="padding:3px 10px" onclick="envMarcoEditar()">Mudar</button></div></div>';
}
function envMarcoEditar(){
  dlgAbrir('Envio de Pedidos','Marco zero',
    '<div style="max-width:520px"><div class="dmini" style="margin-bottom:10px">'
   + 'Pedidos aprovados <b>antes</b> desta data nunca entram na fila — eles sao do processo manual. '
   + 'Adiantar a data faz a fila crescer com pedidos que talvez ja tenham sido enviados; atrasar pode '
   + 'deixar um pedido aprovado sem sair (regra 4).</div>'
   + cotFld('O sistema envia a partir de','<input type="date" id="envMarcoD" value="'+esc(ENV.d.marco||'')+'" style="width:100%;max-width:190px">')
   + '<div class="bar" style="justify-content:flex-end;gap:8px;margin-top:14px">'
   + '<button class="btn-ghost" onclick="closeModal(true)">Cancelar</button>'
   + '<button class="btn-prim" onclick="envMarcoSalvar()">Salvar</button></div></div>');
}
async function envMarcoSalvar(){ const v=((document.getElementById('envMarcoD')||{}).value||'').trim();
  if(!v){ toast('Escolha uma data'); return; }
  try{ const r=await (await fetch('actions/envio.php',{method:'POST',headers:{'Content-Type':'application/json'},
       body:JSON.stringify({acao:'marco',me:(EU&&EU.bitrix_id),data:v})})).json();
    if(r.error){ toast(r.error); return; } closeModal(true); toast('Marco zero definido');
    ENV.d=null; envCarregar(); }catch(e){ toast('Falha: '+e.message); }
}



/* ---- ASSINATURAS ----
   Quem assina o e-mail e QUEM ENVIA: o usuario do Bitrix logado que clicou em Enviar. Nao e o
   "responsavel pela obra" — o fornecedor vai responder para a pessoa que mandou, e e o nome dela
   que tem de estar ali.

   Por usuario: nome como aparece na assinatura, cargo, telefone e uma imagem propria. Se a imagem
   existir, ela substitui o bloco de texto inteiro — varios compradores ja tem a assinatura pronta
   em PNG e nao faz sentido remontar. */
function pmAssin(){
  const ass=(PM.d.config.assinatura||{}), lista=(PM.d.assinantes||[]);
  const PAPEL={admin:'Administrador',gerente:'Gerente',comprador:'Comprador',diretor:'Diretor',
               coordenador:'Coordenador',obra:'Obra (consulta)',personalizado:'Personalizado'};
  let h='<div class="panel">'+cotSecHead('draw','Assinaturas dos compradores',
    'quem assina e quem envia — o usuario do Bitrix logado no momento do disparo',
    '<button class="btn-prim" onclick="pmAssinSalvarTodos()" style="padding:5px 13px"><span class="material-icons" style="font-size:15px;vertical-align:-3px">save</span> Salvar</button>');
  if(!lista.length) return h+'<div class="dempty">Nenhum usuario carregado.</div></div>';
  h+='<div style="overflow-x:auto"><table class="dtable" style="width:100%;font-size:12.5px"><thead><tr>'
   + '<th style="text-align:left">Usuario (Bitrix)</th><th style="text-align:left">Nome na assinatura</th>'
   + '<th style="text-align:left">Cargo</th><th style="text-align:left">Telefone</th>'
   + '<th style="text-align:left">Imagem propria</th></tr></thead><tbody>';
  lista.forEach(function(u){
    const id=String(u.bitrix_id), a=ass[id]||{};
    const img=(a.imagem||'').trim();
    h+='<tr><td><b>'+esc(u.nome)+'</b><div class="dmini" style="color:var(--muted)">#'+esc(id)+' &middot; '
     + esc(PAPEL[u.papel]||u.papel||'')+(u.cargo?(' &middot; '+esc(u.cargo)):'')+'</div></td>'
     + '<td><input id="pma_n_'+id+'" value="'+esc(a.nome||u.nome||'')+'" style="width:100%;min-width:150px"></td>'
     + '<td><input id="pma_c_'+id+'" value="'+esc(a.cargo||u.cargo||'Suprimentos')+'" style="width:100%;min-width:130px"></td>'
     + '<td><input id="pma_t_'+id+'" value="'+esc(a.telefone||'')+'" placeholder="(19) 9....-...." style="width:100%;min-width:120px"></td>'
     + '<td style="white-space:nowrap">'
     + (img
        ? ('<img src="'+esc(img)+'?v='+Date.now()+'" style="max-height:34px;vertical-align:middle;border:1px solid var(--line);border-radius:4px">'
           + ' <button class="btn-ghost" style="padding:2px 8px;font-size:11px" onclick="pmAssinImgRemover(\''+id+'\')">remover</button>')
        : ('<button class="btn-ghost" style="padding:3px 10px;font-size:11.5px" onclick="pmAssinImgForm(\''+id+'\',\''+esc(u.nome).replace(/'/g,"")+'\')">'
           + '<span class="material-icons" style="font-size:14px;vertical-align:-3px">upload</span> anexar</button>'))
     + '</td></tr>';
  });
  h+='</tbody></table></div>';
  h+='<div class="dmini" style="margin-top:10px;color:var(--muted)">Com imagem, o e-mail usa a imagem e ignora os campos de texto. '
   + 'Sem imagem, monta o bloco: nome em negrito, cargo abaixo, telefone e caprem.com.br.</div>';
  h+='<div style="margin-top:14px;border-top:1px solid var(--line);padding-top:12px">'
   + '<div class="dmini" style="margin-bottom:6px">Previa de quem esta logado ('+esc((EU&&EU.nome)||'')+')</div>'
   + pmAssinPrevia(String((EU&&EU.bitrix_id)||''), ass)+'</div>';
  return h+'</div>';
}
function pmAssinPrevia(id, ass){
  const a=ass[id]||{}, img=(a.imagem||'').trim();
  if(img) return '<div style="border:1px solid var(--line);border-radius:10px;padding:14px;background:#fff;display:inline-block">'
    + '<img src="'+esc(img)+'?v='+Date.now()+'" style="max-width:360px"></div>';
  const logo=((PM.d.config.global||{}).assinatura_img||'').trim();
  return '<div style="border:1px solid var(--line);border-radius:10px;padding:16px 18px;background:#fff;display:inline-block">'
   + '<div style="font-family:Arial,sans-serif;display:flex;align-items:center;gap:18px">'
   + '<div><div style="font-size:15px;font-weight:700">'+esc(a.nome||(EU&&EU.nome)||'(nome)')+'</div>'
   + '<div style="font-size:12px;color:#777">'+esc(a.cargo||'Suprimentos')+'</div>'
   + '<div style="font-size:12px;color:#333;margin-top:8px">'+esc(a.telefone||'(telefone)')+' &nbsp;&nbsp; caprem.com.br</div></div>'
   + (logo?('<div style="border-left:1px solid #d8d8d8;padding-left:18px"><img src="'+esc(logo)+'" style="max-height:56px"></div>'):'')
   + '</div></div>';
}
function pmAssinImgForm(id, nome){
  dlgAbrir('Assinaturas','Imagem de assinatura de '+esc(nome),
    '<div style="max-width:520px"><div class="dmini" style="margin-bottom:10px">'
   + 'PNG, JPG ou GIF, ate 2 MB. Quando houver imagem, o e-mail usa ela no lugar do bloco de texto. '
   + 'Fica guardada no servidor e servida por um endereco proprio — o fornecedor consegue ver.</div>'
   + '<input type="file" id="pmAssinFile" accept="image/png,image/jpeg,image/gif" style="width:100%;font-size:13px">'
   + '<div id="pmAssinMsg" class="dmini" style="margin-top:9px"></div>'
   + '<div class="bar" style="justify-content:flex-end;gap:8px;margin-top:14px">'
   + '<button class="btn-ghost" onclick="closeModal(true)">Cancelar</button>'
   + '<button class="btn-prim" onclick="pmAssinImgEnviar(\''+id+'\')">Anexar</button></div></div>');
}
async function pmAssinImgEnviar(id){
  const f=(document.getElementById('pmAssinFile')||{}).files, m=document.getElementById('pmAssinMsg');
  if(!f||!f.length){ if(m) m.innerHTML='<span style="color:var(--pend)">Escolha uma imagem.</span>'; return; }
  if(m) m.textContent='Enviando...';
  const fd=new FormData();
  fd.append('acao','assinatura_img'); fd.append('me',(EU&&EU.bitrix_id)||''); fd.append('bitrix_id',id); fd.append('img',f[0]);
  try{ const r=await (await fetch('actions/envio_config.php',{method:'POST',body:fd})).json();
    if(r.error){ if(m) m.innerHTML='<span style="color:var(--pend)">'+esc(r.error)+'</span>'; return; }
    closeModal(true); toast('Assinatura anexada');
    const sub=PM.sub; await pmLoad(); PM.sub=sub; pmRender();
  }catch(e){ if(m) m.innerHTML='<span style="color:var(--pend)">Falha: '+esc(e.message)+'</span>'; }
}
async function pmAssinImgRemover(id){
  if(!confirm('Remover a imagem de assinatura deste usuario?')) return;
  try{ await fetch('actions/envio_config.php',{method:'POST',headers:{'Content-Type':'application/json'},
       body:JSON.stringify({acao:'assinatura_img_remover',me:(EU&&EU.bitrix_id),bitrix_id:id})});
    toast('Imagem removida');
    const sub=PM.sub; await pmLoad(); PM.sub=sub; pmRender();
  }catch(e){ toast('Falha: '+e.message); }
}
async function pmAssinSalvarTodos(){
  const lista=(PM.d.assinantes||[]);
  let n=0;
  for(const u of lista){
    const id=String(u.bitrix_id);
    const g=x=>((document.getElementById(x)||{}).value||'').trim();
    const campos={nome:g('pma_n_'+id), cargo:g('pma_c_'+id), telefone:g('pma_t_'+id)};
    if(!campos.nome && !campos.telefone) continue;
    try{ const r=await (await fetch('actions/envio_config.php',{method:'POST',headers:{'Content-Type':'application/json'},
      body:JSON.stringify({acao:'salvar',me:(EU&&EU.bitrix_id),escopo:'assinatura',ref:id,campos:campos})})).json();
      if(!r.error) n++; }catch(e){}
  }
  toast(n+' assinatura(s) salva(s)');
  const sub=PM.sub; await pmLoad(); PM.sub=sub; pmRender();
}

/* ---- CONTA DE ENVIO DOS PEDIDOS ----
   Separada da conta das cotacoes de proposito. A aba "E-mail (disparo)" guarda
   suprimentos@capremconstrutora.com.br, que dispara COTACAO. Pedido de compra sempre saiu de
   pedidos@caprem.com.br: o fornecedor conhece esse remetente, as respostas dele (confirmacao, nota,
   prazo) precisam voltar para a caixa certa, e o historico de 3.083 e-mails de pedido esta la.
   O primeiro teste saiu pela conta errada justamente por nao existir esta separacao. */
async function pmConta(){ const w=document.getElementById('pmContaWrap'); if(!w) return;
  let c; try{ c=await (await fetch('actions/envio_config.php?conta=1&me='+pmMe()+'&_='+Date.now())).json(); }
  catch(e){ w.innerHTML='<div class="empty">Falha ao carregar.</div>'; return; }
  const okc=!!c.configurada;
  let h=cotSecHead('alternate_email','Conta que envia os PEDIDOS',
    'diferente da conta que dispara cotacoes — o fornecedor conhece este remetente',
    '<span class="dchip" style="background:'+(okc?'var(--ok)':'var(--pend)')+'">'+(okc?'configurada':'falta configurar')+'</span>');
  if(!okc) h+='<div style="border-left:4px solid var(--dourado);background:#fdf9ec;padding:9px 12px;border-radius:0 8px 8px 0;font-size:12.5px;margin-bottom:11px">'
    + 'Enquanto esta conta nao existir, o teste sai por <b>'+esc(c.fonte_user||'(conta das cotacoes)')+'</b> — '
    + 'que nao e o remetente que os fornecedores conhecem.</div>';
  h+='<div style="display:grid;grid-template-columns:1fr 100px 110px;gap:10px">'
   + cotFld('Servidor','<input id="pmcHost" value="'+esc(c.host||'mail.caprem.com.br')+'" style="width:100%">')
   + cotFld('Porta SMTP','<input id="pmcPort" type="number" value="'+esc(c.port||465)+'" style="width:100%">')
   + cotFld('Porta IMAP','<input id="pmcImap" type="number" value="'+esc(c.imap_port||993)+'" style="width:100%">')+'</div>';
  h+='<div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-top:9px">'
   + cotFld('Usuario (remetente dos pedidos)','<input id="pmcUser" value="'+esc(c.user||'')+'" placeholder="pedidos@caprem.com.br" style="width:100%">')
   + cotFld('Nome que aparece para o fornecedor','<input id="pmcNome" value="'+esc(c.nome||'Caprem - Suprimentos')+'" style="width:100%">')+'</div>';
  h+='<div style="margin-top:9px;max-width:420px">'+cotFld('Senha (vazio mantem a atual)','<input id="pmcSenha" type="password" autocomplete="new-password" placeholder="&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;" style="width:100%">')+'</div>';
  h+='<div class="bar" style="margin-top:11px;gap:8px"><button class="btn-prim" onclick="pmContaSalvar()"><span class="material-icons" style="font-size:15px;vertical-align:-3px">save</span> Salvar conta dos pedidos</button>'
   + '<button class="btn-ghost" onclick="pmContaTestar()" title="abre a conexao com o servidor de e-mail e mostra o que responde — nao envia nada">'
   + '<span class="material-icons" style="font-size:15px;vertical-align:-3px">network_check</span> Testar conexão</button></div>';
  h+='<div id="pmcTeste"></div>';
  h+='<div class="dmini" style="margin-top:10px;color:var(--muted)">A senha fica num arquivo do servidor protegido (403), nunca no banco e nunca no que chega ao navegador.</div>';
  w.innerHTML=h;
}
/* Testar a conexao ANTES de descobrir pelo pedido que nao saiu. O sintoma engana: saida bloqueada
   chega como "Connection timed out", que parece porta errada e faz conferir numeros que ja estavam
   certos. Aqui vem o veredito em portugues, com as tres medidas por tras dele. */
async function pmContaTestar(){
  const w=document.getElementById('pmcTeste'); if(!w) return;
  w.innerHTML='<div class="dmini" style="margin-top:10px;color:var(--muted)">Abrindo as conexões… (até 15s)</div>';
  let r; try{ r=await (await fetch('actions/envio_config.php?testar_conexao=1&me='+(EU&&EU.bitrix_id))).json(); }
  catch(e){ w.innerHTML='<div class="dmini" style="margin-top:10px;color:var(--pend)">Falha: '+esc(e.message)+'</div>'; return; }
  if(r.error){ w.innerHTML='<div class="dmini" style="margin-top:10px;color:var(--pend)">'+esc(r.error)+'</div>'; return; }
  const cor={ok:'var(--ok)',bloqueio:'#c0392b',host:'var(--dourado)',nada:'#c0392b'}[r.veredito.nivel]||'var(--muted)';
  w.innerHTML='<div style="margin-top:12px;border-left:4px solid '+cor+';background:#f8faf9;padding:10px 13px;border-radius:0 8px 8px 0">'
   + '<div style="font-size:12.5px;margin-bottom:8px">'+esc(r.veredito.texto)+'</div>'
   + '<table class="dtable" style="width:100%;font-size:11.5px"><tbody>'
   + r.testes.map(t=>'<tr><td style="width:16px">'+(t.ok?'<span class="material-icons" style="font-size:14px;color:var(--ok)">check_circle</span>'
       :'<span class="material-icons" style="font-size:14px;color:#c0392b">cancel</span>')+'</td>'
   + '<td><b>'+esc(t.alvo)+'</b><div class="muted">'+esc(t.o_que)+'</div></td>'
   + '<td style="text-align:right;white-space:nowrap">'+(t.ok?(t.ms+' ms'):esc(t.erro+' ('+t.errno+')'))+'</td></tr>').join('')
   + '</tbody></table></div>';
}
async function pmContaSalvar(){ const g=x=>((document.getElementById(x)||{}).value||'').trim();
  if(!g('pmcUser')){ toast('Informe o e-mail remetente'); return; }
  try{ const r=await (await fetch('actions/envio_config.php',{method:'POST',headers:{'Content-Type':'application/json'},
       body:JSON.stringify({acao:'conta',me:(EU&&EU.bitrix_id),host:g('pmcHost'),port:Number(g('pmcPort'))||465,
         imap_port:Number(g('pmcImap'))||993,user:g('pmcUser'),nome:g('pmcNome'),senha:g('pmcSenha')})})).json();
    if(r.error){ toast(r.error); return; } toast('Conta dos pedidos salva'); pmConta();
  }catch(e){ toast('Falha: '+e.message); } }

/* ---- VER O PEDIDO INTEIRO ----
   A fila carrega so um resumo (4 produtos, observacao cortada) porque sao milhares de linhas. Mas
   para DECIDIR se arquiva, o comprador precisa ver os itens e principalmente a observacao completa —
   e onde mora o "(lancar)". Entao o detalhe e uma consulta sob demanda, de um pedido so. */
async function envVerPedido(col,num){
  dlgAbrir('Pedido de compra','Carregando...','<div class="dempty">Buscando os itens no TOTVS...</div>');
  let d; try{ d=await (await fetch('actions/envio.php?pedido='+encodeURIComponent(col+'|'+num)+'&me='+envMe()+'&_='+Date.now())).json(); }
  catch(e){ dlgAbrir('Pedido de compra','Falha','<div class="empty">'+esc(e.message)+'</div>'); return; }
  if(d.error){ dlgAbrir('Pedido de compra','Nao encontrado','<div class="empty">'+esc(d.error)+'</div>'); return; }
  const COR={aprovado:'var(--ok)',reprovado:'#c0392b',pendente:'var(--dourado)',sem:'var(--muted)'};
  let h='<div class="bar" style="gap:8px;flex-wrap:wrap;margin-bottom:10px">'
   + '<span class="dchip" style="background:'+(COR[d.aprov_k]||'var(--muted)')+'">'+esc(d.aprovacao)+'</span>'
   + (d.aprov_por?'<span class="dmini">por '+esc(d.aprov_por)+'</span>':'')
   + (d.regulariza?'<span class="dchip" style="background:var(--dourado)">sinal de regularizacao</span>':'')
   + '</div>';
  h+='<table cellpadding="0" cellspacing="0" style="font-size:13px;margin-bottom:12px">';
  [['Obra',d.obra],['Fornecedor',d.fornecedor+(d.fornecedor_cod?(' (cod '+d.fornecedor_cod+')'):'')],
   ['Centro de custo',d.ccusto],['Emitido em',(d.data||'').split('T')[0].split('-').reverse().join('/')],
   ['Comprador',d.comprador],['Solicitacoes',d.scs||'—'],['Valor total',BRL(d.valor)]].forEach(x=>{
    if(!x[1]) return;
    h+='<tr><td style="padding:1px 12px 1px 0;color:#666;white-space:nowrap">'+x[0]+':</td><td style="padding:1px 0"><b>'+esc(String(x[1]))+'</b></td></tr>'; });
  h+='</table>';
  /* A observacao e DE CADA ITEM — juntar tudo num campo so misturava a descricao de produtos
     diferentes. Vira coluna ao lado do produto, como no PDF. */
  if(d.regulariza) h+='<div style="border-left:4px solid var(--dourado);background:#fdf9ec;padding:8px 12px;'
   + 'border-radius:0 8px 8px 0;font-size:12.5px;margin-bottom:12px"><b>Sinal de regularizacao.</b> '
   + 'A descricao indica material ja entregue — confira antes de mandar ao fornecedor.</div>';
  h+='<div class="dmini" style="margin-bottom:3px">Itens ('+d.itens.length+')</div>';
  h+='<div style="overflow:auto;max-height:330px;border:1px solid var(--line);border-radius:8px">'
   + '<table class="dtable" style="width:100%;font-size:12.5px"><thead><tr>'
   + '<th style="text-align:left">Produto</th><th style="text-align:left">Descricao / observacao</th>'
   + '<th style="text-align:left">Entrega</th><th style="text-align:right">Qtd</th><th>Und</th>'
   + '<th style="text-align:right">Unit.</th><th style="text-align:right">Total</th></tr></thead><tbody>';
  d.itens.forEach(i=>{
    const zero=(Number(i.total)===0 && Number(i.qtd)*Number(i.preco)>0.009);
    h+='<tr><td style="max-width:190px"><b>'+esc(i.produto)+'</b></td>'
     + '<td style="max-width:250px;color:#555;font-size:11.5px">'+esc(i.obs||'—')+'</td>'
     + '<td style="white-space:nowrap">'+(i.entrega?esc(String(i.entrega).slice(0,10).split('-').reverse().join('/')):'—')+'</td>'
     + '<td style="text-align:right">'+QNUM(i.qtd)+'</td>'
     + '<td style="text-align:center">'+esc(i.und)+'</td><td style="text-align:right">'+BRL(i.preco)+'</td>'
     + '<td style="text-align:right'+(zero?';color:var(--pend);font-weight:700':'')+'" '
     + (zero?'title="o valor total deste item veio ZERADO na base do TOTVS — o PDF fica bloqueado ate corrigirem"':'')+'>'
     + BRL(i.total)+(zero?' !':'')+'</td></tr>'; });
  h+='</tbody></table></div>';
  const fid=(ENV.d&&(ENV.d.envelopes||[]).flatMap(x=>x.pedidos||[]).find(p=>String(p.numero)===String(num))||{}).ficha_id||0;
  h+='<div class="bar" style="justify-content:space-between;gap:8px;margin-top:14px;flex-wrap:wrap">'
   + '<span class="bar" style="gap:7px">'+(d.tem_pdf
       ? '<a href="actions/envio.php?baixar_pdf='+encodeURIComponent(col+'|'+num)+'&me='+envMe()+'" target="_blank" class="btn-prim" style="padding:5px 13px;text-decoration:none"><span class="material-icons" style="font-size:15px;vertical-align:-3px">picture_as_pdf</span> Ver o PDF</a>'
         + '<button class="btn-ghost" style="padding:5px 11px;font-size:11.5px" onclick="envGerarPdf(\''+esc(col)+'\',\''+esc(num)+'\','+fid+')">gerar de novo</button>'
         + '<button class="btn-ghost" style="padding:5px 11px;font-size:11.5px" onclick="envAnexoRemover(\''+esc(col)+'\',\''+esc(num)+'\')">remover</button>'
       : '<button class="btn-prim" style="padding:5px 13px" onclick="envGerarPdf(\''+esc(col)+'\',\''+esc(num)+'\','+fid+')"><span class="material-icons" style="font-size:15px;vertical-align:-3px">auto_awesome</span> Gerar o PDF</button>'
         + '<button class="btn-ghost" style="padding:5px 12px;font-size:11.5px" onclick="envAnexarForm(\''+esc(col)+'\',\''+esc(num)+'\','+fid+',\''+esc(d.fornecedor_cnpj||'')+'\')">ou anexar um arquivo</button>')+'</span>'
   + '<span class="bar" style="gap:8px"><button class="btn-ghost" onclick="closeModal(true)">Fechar</button>'
   + '<button class="btn-ghost" style="padding:5px 13px" onclick="envArqUm(\''+esc(col)+'\',\''+esc(num)+'\')">'
   + '<span class="material-icons" style="font-size:15px;vertical-align:-3px">inventory_2</span> Arquivar</button></span></div>';
  dlgAbrir('Pedido '+esc(num)+' &middot; '+esc(d.coligada||('coligada '+col)), esc(d.fornecedor||'Pedido de compra'), h);
}

/* Arquiva UM pedido (serve tanto para a fila quanto para a lista de bloqueados). */
function envArqUm(col,num){
  dlgAbrir('Envio de Pedidos','Arquivar o pedido '+esc(num),
    '<div style="max-width:520px"><div class="dmini" style="margin-bottom:10px">'
   + 'Some da tela e das contagens, mas <b>nada e apagado</b>: o pedido continua no TOTVS e volta pela aba <b>Arquivados</b>.</div>'
   + cotFld('Motivo (fica com o seu nome)','<input id="envArq1" placeholder="ex.: material ja entregue, pedido so para lancamento" style="width:100%">')
   + '<div class="bar" style="justify-content:flex-end;gap:8px;margin-top:14px">'
   + '<button class="btn-ghost" onclick="closeModal(true)">Cancelar</button>'
   + '<button class="btn-prim" onclick="envArqUmSalvar(\''+esc(col)+'\',\''+esc(num)+'\')">Arquivar</button></div></div>');
}
async function envArqUmSalvar(col,num){
  const m=((document.getElementById('envArq1')||{}).value||'').trim();
  if(!m){ toast('Escreva o motivo'); return; }
  try{ const r=await (await fetch('actions/envio.php',{method:'POST',headers:{'Content-Type':'application/json'},
       body:JSON.stringify({acao:'decidir',me:(EU&&EU.bitrix_id),me_nome:(EU&&EU.nome)||'',
                            decisao:'arquivado',coligada:col,numero:num,motivo:m})})).json();
    if(r.error){ toast(r.error); return; }
    closeModal(true); toast('Pedido arquivado'); ENV.d=null; envCarregar();
  }catch(e){ toast('Falha: '+e.message); } }



/* ---- CADASTRAR O E-MAIL SEM SAIR DA FILA ----
   Pedido do Murilo: na lista de bloqueados por "fornecedor sem e-mail", um botao que abra o cadastro,
   preencha e volte. Sem isso a pessoa larga a fila, vai na tela de Fornecedores, procura pelo nome
   (que as vezes esta escrito diferente) e perde o fio. A chave gravada e o CODCFO — o mesmo que
   amarra o pedido ao cadastro, entao o pedido sai do bloqueio na hora. */
function envEmailForm(f){
  dlgAbrir('Envio de Pedidos','Cadastrar o e-mail do fornecedor',
    '<div style="max-width:540px">'
   + '<div style="border:1px solid var(--line);border-radius:8px;padding:9px 12px;background:#f8faf9;margin-bottom:12px">'
   + '<div style="font-size:14px;font-weight:700">'+esc(f.nome||'')+'</div>'
   + '<div class="dmini" style="margin-top:3px">'+(f.cnpj?('CNPJ '+esc(f.cnpj)+'   &middot;   '):'')
   + 'cod. TOTVS '+esc(f.cod||'—')+'</div></div>'
   + cotFld('E-mail para receber os pedidos','<input id="envEmailV" type="email" placeholder="vendas@fornecedor.com.br" style="width:100%">')
   + '<div class="dmini" style="margin-top:8px;color:var(--muted)">Fica gravado no nosso cadastro pelo codigo do TOTVS. '
   + 'Assim que salvar, o pedido sai de Bloqueados e entra na fila.</div>'
   + '<div id="envEmailMsg" class="dmini" style="margin-top:8px"></div>'
   + '<div class="bar" style="justify-content:flex-end;gap:8px;margin-top:14px">'
   + '<button class="btn-ghost" onclick="closeModal(true)">Cancelar</button>'
   + '<button class="btn-prim" onclick="envEmailSalvar('+esc(JSON.stringify(f))+')">Salvar e voltar</button></div></div>');
  setTimeout(function(){ var i=document.getElementById('envEmailV'); if(i) i.focus(); },80);
}
async function envEmailSalvar(f){
  const v=((document.getElementById('envEmailV')||{}).value||'').trim();
  const m=document.getElementById('envEmailMsg');
  if(!v||v.indexOf('@')<0){ if(m) m.innerHTML='<span style="color:var(--pend)">Digite um e-mail valido.</span>'; return; }
  if(m) m.textContent='Salvando...';
  try{ const r=await (await fetch('actions/fornecedores.php',{method:'POST',headers:{'Content-Type':'application/json'},
       body:JSON.stringify({acao:'email_rapido',me:(EU&&EU.bitrix_id),cod:f.cod,cnpj:f.cnpj,nome:f.nome,email:v})})).json();
    if(r.error){ if(m) m.innerHTML='<span style="color:var(--pend)">'+esc(r.error)+'</span>'; return; }
    closeModal(true);
    toast(r.criado?'Fornecedor cadastrado com o e-mail':'E-mail atualizado');
    ENV.d=null; envCarregar();
  }catch(e){ if(m) m.innerHTML='<span style="color:var(--pend)">Falha: '+esc(e.message)+'</span>'; }
}


/* ---- GERAR O PDF ----
   Este e o caminho normal; anexar arquivo e o escape. Gerado aqui, o PDF nasce do MESMO registro que
   decidiu a obra e o destinatario — nao ha como divergir do pedido. */
async function envGerarPdf(col,num,fichaId){
  toast('Gerando o PDF...');
  try{ const r=await (await fetch('actions/envio.php',{method:'POST',headers:{'Content-Type':'application/json'},
       body:JSON.stringify({acao:'gerar_pdf',me:(EU&&EU.bitrix_id),coligada:col,numero:num,ficha_id:fichaId||0})})).json();
    if(r.error){
      dlgAbrir('Pedido '+esc(num),'Nao consegui gerar o PDF',
        '<div style="max-width:560px"><div style="border-left:4px solid var(--pend);background:#fdf1ef;padding:10px 13px;'
        + 'border-radius:0 8px 8px 0;font-size:13px;margin-bottom:10px">'+esc(r.error)+'</div>'
        + ((r.divergencias||[]).length?('<div class="dmini" style="margin-bottom:4px">Itens com problema:</div><ul style="margin:0 0 0 18px;font-size:12.5px;line-height:1.6">'
            + r.divergencias.map(x=>'<li>'+esc(x)+'</li>').join('')+'</ul>'):'')
        + '<div class="dmini" style="margin-top:10px;color:var(--muted)">O campo de valor total desses itens veio zerado no TOTVS. '
        + 'Enquanto nao for corrigido na origem, o pedido nao sai — mandar um PDF com R$ 0,00 num item e pior do que nao mandar.</div>'
        + '<div class="bar" style="justify-content:flex-end;margin-top:14px"><button class="btn-prim" onclick="closeModal(true)">Entendi</button></div></div>');
      return;
    }
    closeModal(true); toast('PDF gerado ('+r.itens+' itens)'); ENV.d=null; envCarregar();
  }catch(e){ toast('Falha: '+e.message); }
}
/* Gera de uma vez todos os PDFs que faltam num envelope. */
async function envGerarPdfLote(ch){
  const e=envAchar(ch); if(!e) return;
  const faltam=(e.pedidos||[]).filter(p=>!p.tem_pdf);
  if(!faltam.length){ toast('Todos os PDFs ja estao prontos'); return; }
  toast('Gerando '+faltam.length+' PDF(s)...');
  try{ const r=await (await fetch('actions/envio.php',{method:'POST',headers:{'Content-Type':'application/json'},
       body:JSON.stringify({acao:'gerar_pdf_lote',me:(EU&&EU.bitrix_id),ficha_id:e.ficha_id,obra:e.obra,
         so_obra:(e.destino==='obra'?1:0),
         pedidos:faltam.map(p=>({coligada:p.coligada_cod,numero:p.numero}))})})).json();
    if(r.error){ toast(r.error); return; }
    toast(r.gerados+' PDF(s) gerado(s)'+((r.falhas||[]).length?(' — '+r.falhas.length+' com problema'):''));
    if((r.falhas||[]).length) setTimeout(()=>toast('PC '+r.falhas[0].numero+': '+r.falhas[0].motivo),2600);
    ENV.d=null; envCarregar();
  }catch(e2){ toast('Falha: '+e2.message); }
}

/* ---- ANEXO MANUAL DO PDF ----
   Enquanto o gerador nao puder montar o pedido sozinho (faltam data de entrega e condicao de
   pagamento no export), o arquivo vem da mao. Mas o servidor ABRE o PDF, le o numero de DENTRO e
   confere o CNPJ da empresa: arquivo trocado e RECUSADO, nao aceito com aviso. Foi assim que 9 dos
   1.303 PDFs da pasta ficaram com o nome errado — e um deles no e-mail e a obra errada. */
function envAnexarForm(col,num,fichaId,cnpjForn){
  dlgAbrir('Pedido '+esc(num),'Anexar o PDF do pedido',
    '<div style="max-width:520px"><div class="dmini" style="margin-bottom:10px">'
   + 'Escolha o PDF que o TOTVS gerou para <b>este</b> pedido. O sistema abre o arquivo, le o numero '
   + 'de dentro dele e confere com a coligada — se for de outro pedido, <b>recusa</b>.</div>'
   + '<input type="file" id="envPdfFile" accept="application/pdf,.pdf" style="width:100%;font-size:13px">'
   + '<div id="envPdfMsg" class="dmini" style="margin-top:9px"></div>'
   + '<div class="bar" style="justify-content:flex-end;gap:8px;margin-top:14px">'
   + '<button class="btn-ghost" onclick="closeModal(true)">Cancelar</button>'
   + '<button class="btn-prim" onclick="envAnexarEnviar(\'' + col + '\',\'' + num + '\',' + (fichaId||0) + ',\'' + (cnpjForn||'') + '\')">Anexar e conferir</button></div></div>');
}
async function envAnexarEnviar(col,num,fichaId,cnpjForn){
  const f=(document.getElementById('envPdfFile')||{}).files;
  const msg=document.getElementById('envPdfMsg');
  if(!f||!f.length){ if(msg) msg.innerHTML='<span style="color:var(--pend)">Escolha um arquivo.</span>'; return; }
  if(msg) msg.textContent='Conferindo o arquivo...';
  const fd=new FormData();
  fd.append('acao','anexo'); fd.append('me',(EU&&EU.bitrix_id)||''); fd.append('coligada',col);
  fd.append('numero',num); fd.append('ficha_id',fichaId||0); fd.append('cnpj_forn',cnpjForn||'');
  fd.append('pdf',f[0]);
  try{ const r=await (await fetch('actions/envio.php',{method:'POST',body:fd})).json();
    if(r.error){ if(msg) msg.innerHTML='<span style="color:var(--pend)"><b>Recusado.</b> '+esc(r.error)+'</span>'; return; }
    closeModal(true);
    toast('PDF anexado e conferido'+(r.aviso?(' — '+r.aviso):''));
    ENV.d=null; envCarregar();
  }catch(e){ if(msg) msg.innerHTML='<span style="color:var(--pend)">Falha: '+esc(e.message)+'</span>'; }
}
async function envAnexoRemover(col,num){
  if(!confirm('Remover o PDF anexado deste pedido?')) return;
  try{ await fetch('actions/envio.php',{method:'POST',headers:{'Content-Type':'application/json'},
       body:JSON.stringify({acao:'anexo_remover',me:(EU&&EU.bitrix_id),coligada:col,numero:num})});
    closeModal(true); toast('Anexo removido'); ENV.d=null; envCarregar(); }catch(e){ toast('Falha: '+e.message); }
}

/* ---- ARQUIVAR: tirar da tela sem apagar nada ----
   Pedido antigo aprovado nao e mais para enviar, mas tirar um a um seriam milhares de cliques — e
   apagar de verdade destruiria a resposta de "por que este pedido aprovado nunca saiu?". Entao
   arquiva-se por CRITERIO (data limite + obra opcional), com motivo, e o lote volta inteiro.
   Diferente de SEGURAR, que deixa o pedido a vista de proposito. */
function envArqLoteForm(){
  const obras=[...new Set((ENV.d.envelopes||[]).map(e=>e.obra).concat((ENV.d.bloqueados||[]).map(b=>b.obra)))].filter(Boolean).sort();
  dlgAbrir('Envio de Pedidos','Arquivar pedidos antigos',
    '<div style="max-width:560px">'
   + '<div class="dmini" style="margin-bottom:11px">Some da fila, dos bloqueados e das contagens — mas <b>nao apaga nada</b>. '
   + 'O pedido continua no TOTVS, e o lote inteiro volta com um clique na aba <b>Arquivados</b>.</div>'
   + '<div style="display:grid;grid-template-columns:1fr 1fr;gap:10px">'
   + cotFld('Arquivar tudo aprovado <b>ate</b>','<input type="date" id="envArqAte" style="width:100%">')
   + cotFld('So desta obra (vazio = todas)','<select id="envArqObra" style="width:100%"><option value="">todas as obras</option>'
     + obras.map(x=>'<option value="'+esc(x)+'">'+esc(x)+'</option>').join('')+'</select>')
   + '</div>'
   + '<div style="margin-top:9px">'+cotFld('Motivo (identifica o lote e permite desfazer)','<input id="envArqMot" placeholder="ex.: pedidos ja enviados a mao antes do cockpit" style="width:100%">')+'</div>'
   + '<div class="bar" style="justify-content:flex-end;gap:8px;margin-top:14px">'
   + '<button class="btn-ghost" onclick="closeModal(true)">Cancelar</button>'
   + '<button class="btn-prim" onclick="envArqLoteSalvar()">Arquivar</button></div></div>');
}
async function envArqLoteSalvar(){ const g=x=>((document.getElementById(x)||{}).value||'').trim();
  const ate=g('envArqAte'), mot=g('envArqMot');
  if(!ate){ toast('Escolha a data limite'); return; }
  if(!mot){ toast('Escreva o motivo — ele identifica o lote'); return; }
  toast('Arquivando...');
  try{ const r=await (await fetch('actions/envio.php',{method:'POST',headers:{'Content-Type':'application/json'},
       body:JSON.stringify({acao:'arquivar_lote',me:(EU&&EU.bitrix_id),me_nome:(EU&&EU.nome)||'',
                            ate:ate,obra:g('envArqObra'),motivo:mot})})).json();
    if(r.error){ toast(r.error); return; }
    closeModal(true); toast(r.arquivados+' pedido(s) arquivado(s)'); ENV.d=null; envCarregar();
  }catch(e){ toast('Falha: '+e.message); }
}
async function envArq(){ const w=document.getElementById('envArqWrap'); if(!w) return;
  let d; try{ d=await (await fetch('actions/envio.php',{method:'POST',headers:{'Content-Type':'application/json'},
    body:JSON.stringify({acao:'arquivados',me:(EU&&EU.bitrix_id)})})).json(); }
  catch(e){ w.innerHTML='<div class="empty">Falha ao carregar.</div>'; return; }
  const l=(d.lotes||[]);
  let h=cotSecHead('inventory_2','Lotes arquivados','fora da fila, mas nada foi apagado — devolva quando quiser','');
  if(!l.length){ w.innerHTML=h+'<div class="dempty">Nenhum lote arquivado.</div>'; return; }
  l.forEach(x=>{ let q=x.em; try{ q=new Date(x.em).toLocaleDateString('pt-BR'); }catch(e){}
    h+='<div style="border:1px solid var(--line);border-radius:10px;padding:10px 12px;margin-bottom:8px">'
     + '<div class="bar" style="justify-content:space-between;gap:10px;flex-wrap:wrap">'
     + '<div><b style="font-size:13.5px">'+esc(x.motivo||'(sem motivo)')+'</b>'
     + '<div class="dmini" style="margin-top:3px;color:var(--muted)">'+x.n+' pedido(s) - por '+esc(x.por_nome||'—')+' em '+esc(q)+'</div></div>'
     + '<button class="btn-ghost" style="padding:4px 11px" onclick="envDesarquivar('+esc(JSON.stringify(x.motivo))+')">Devolver a fila</button>'
     + '</div></div>'; });
  w.innerHTML=h;
}
async function envDesarquivar(motivo){
  if(!confirm('Devolver este lote para a fila de envio?')) return;
  try{ const r=await (await fetch('actions/envio.php',{method:'POST',headers:{'Content-Type':'application/json'},
       body:JSON.stringify({acao:'desarquivar_lote',me:(EU&&EU.bitrix_id),motivo:motivo})})).json();
    if(r.error){ toast(r.error); return; }
    toast(r.devolvidos+' pedido(s) de volta na fila'); ENV.d=null; envCarregar();
  }catch(e){ toast('Falha: '+e.message); }
}

function envLegenda(){
  /* As bordas tinham dois tons de laranja quase iguais para motivos DIFERENTES — o Murilo perguntou
     e tinha razao. Agora cada cor tem um motivo so, e a legenda fica a vista. */
  const it=(cor,txt)=>'<span style="display:inline-flex;align-items:center;gap:5px;font-size:11.5px">'
    +'<span style="width:11px;height:11px;border-radius:3px;background:'+cor+';display:inline-block"></span>'+txt+'</span>';
  return '<div class="panel" style="padding:8px 14px;margin-bottom:10px"><div class="bar" style="gap:16px;flex-wrap:wrap">'
   + '<span class="dmini" style="font-weight:600">O que a cor da borda quer dizer:</span>'
   + it('var(--ok)','pronto — pode enviar')
   + it('var(--dourado)','confira antes: sinal de material ja entregue')
   + it('#8e44ad','falta o PDF do pedido')
   + it('#c0392b','aprovado ha mais de '+(ENV.d.atraso_dias||3)+' dias')
   + '</div></div>';
}

/* ---- FILA: uma LINHA por e-mail, agrupada por obra ----
   Selecao em massa porque gerar PDF um a um nao escala: numa obra com 9 e-mails eram 9 cliques e 9
   esperas. Agora marca-se a obra inteira (ou linhas soltas) e uma barra fixa no rodape age sobre
   tudo de uma vez.

   A linha com "CNPJ na observacao" ganha fundo rosa: e o sinal de que o fornecedor ja foi
   escolhido/contratado na solicitacao, e em parte dos casos significa material ja em obra. Nao
   bloqueia (aparece em 28,7% dos pedidos), mas tem de saltar aos olhos. */
function envObrasDaFila(){
  const m={};
  (ENV.d.envelopes||[]).forEach(e=>{ m[e.obra]=(m[e.obra]||0)+1; });
  return Object.keys(m).sort().map(o=>({obra:o, n:m[o]}));
}
function envFiltroObra(v){ ENV.obra=v; envRender(); }

/* ---- selecao ---- */
function envSelToggle(ch){ ENV.sel=ENV.sel||{}; ENV.sel[ch]=!ENV.sel[ch]; envRender(); }
function envSelObra(obra){
  ENV.sel=ENV.sel||{};
  const g=(ENV.d.envelopes||[]).filter(e=>e.obra===obra);
  const todosMarcados=g.every(e=>ENV.sel[e.chave]);
  g.forEach(e=>{ ENV.sel[e.chave]=!todosMarcados; });
  envRender();
}
function envSelLimpar(){ ENV.sel={}; envRender(); }
function envSelecionados(){
  ENV.sel=ENV.sel||{};
  return (ENV.d.envelopes||[]).filter(e=>ENV.sel[e.chave]);
}

function envFila(){
  const todos=(ENV.d.envelopes||[]);
  const obras=envObrasDaFila();
  const es = ENV.obra ? todos.filter(e=>e.obra===ENV.obra) : todos;

  let h='<div class="panel" style="padding:9px 14px;margin-bottom:10px"><div class="bar" style="justify-content:space-between;flex-wrap:wrap;gap:8px">'
   + '<span class="bar" style="gap:8px;flex-wrap:wrap">'
   + '<select onchange="envFiltroObra(this.value)" style="min-width:190px">'
   + '<option value="">Todas as obras ('+todos.length+')</option>'
   + obras.map(o=>'<option value="'+esc(o.obra)+'"'+(ENV.obra===o.obra?' selected':'')+'>'+esc(o.obra)+' ('+o.n+')</option>').join('')
   + '</select>'
   + (ENV.obra?('<button class="btn-ghost" style="padding:4px 10px;font-size:11.5px;color:var(--pend);font-weight:700" onclick="envFiltroObra(\'\')">&times; limpar</button>'):'')
   + '<span class="dmini">Marque as linhas (ou a obra inteira) e use a barra que aparece embaixo.</span>'
   + '</span>'
   + '<button class="btn-ghost" onclick="envArqLoteForm()" style="padding:6px 13px"><span class="material-icons" style="font-size:16px;vertical-align:-4px">inventory_2</span> Arquivar antigos</button>'
   + '</div></div>';

  if(!es.length) return h+'<div class="panel"><div class="dempty">Nenhum pedido aprovado esperando envio'
    + (ENV.obra?(' na obra <b>'+esc(ENV.obra)+'</b>'):'')+'. Se voce esperava algum, ele esta em <b>Bloqueados</b>.</div></div>';

  h+=envLegenda();

  const grupos={};
  es.forEach(e=>{ (grupos[e.obra]=grupos[e.obra]||[]).push(e); });
  Object.keys(grupos).sort().forEach(obra=>{
    const g=grupos[obra].sort((a,b)=>(b.dias-a.dias));
    const val=g.reduce((a,b)=>a+b.valor,0), nped=g.reduce((a,b)=>a+(b.pedidos||[]).length,0);
    const marcados=g.filter(e=>(ENV.sel||{})[e.chave]).length;
    h+='<div class="panel" style="margin-bottom:10px;padding-top:10px">'
     + '<div class="bar" style="justify-content:space-between;gap:10px;flex-wrap:wrap;margin-bottom:7px">'
     + '<span class="bar" style="gap:8px">'
     + '<input type="checkbox" title="marcar todos desta obra" onchange="envSelObra('+jsArg(obra)+')"'
     + (marcados===g.length?' checked':'')+(marcados&&marcados<g.length?' data-parcial="1"':'')+'>'
     + '<span class="material-icons" style="font-size:19px;color:var(--verde)">apartment</span>'
     + '<b style="font-size:15px">'+esc(obra)+'</b>'
     + '<span class="dmini" style="color:var(--muted)">'+g.length+' e-mail(s) &middot; '+nped+' pedido(s) &middot; '+BRLc(val)
     + (marcados?(' &middot; <b style="color:var(--verde-d)">'+marcados+' marcado(s)</b>'):'')+'</span></span>'
     + '</div>'
     + '<div style="overflow-x:auto"><table class="dtable" style="width:100%;font-size:12.5px">'
     + '<thead><tr>'
     + '<th style="width:26px"></th>'
     + '<th style="text-align:left">FORNECEDOR / DESTINATÁRIO</th>'
     + '<th style="text-align:left">PEDIDOS (PC)</th>'
     + '<th style="text-align:center" title="aprovado no Fluig · obra conferida · nunca enviado · PDF gerado">CONFERÊNCIA</th>'
     + '<th style="text-align:right">VALOR</th>'
     + '<th style="text-align:right">PARADO</th>'
     + '<th style="text-align:right">AÇÕES</th>'
     + '</tr></thead><tbody>';
    g.forEach(e=>{ h+=envLinha(e); });
    h+='</tbody></table></div></div>';
  });

  h+=envBarraSelecao();
  if(envSelecionados().length) h+='<div style="height:64px"></div>';   // a barra fixa nao pode cobrir a ultima linha
  return h;
}

/* Barra fixa no rodape — so aparece quando ha selecao. */
function envBarraSelecao(){
  const sel=envSelecionados();
  if(!sel.length) return '';
  const semPdf=sel.reduce((a,b)=>a+(b.sem_pdf||0),0);
  const prontos=sel.filter(e=>!(e.sem_pdf||0));
  const nped=sel.reduce((a,b)=>a+(b.pedidos||[]).length,0);
  const val=sel.reduce((a,b)=>a+b.valor,0);
  /* FIXA na tela, nao sticky. Sticky no ULTIMO elemento so aparece quando se rola ate o fim — o
     Murilo marcou quatro linhas e a barra ficou fora da vista. */
  return '<div style="position:fixed;left:0;right:0;bottom:0;z-index:60;display:flex;align-items:center;gap:10px;'
   + 'flex-wrap:wrap;background:var(--verde-d);color:#fff;padding:11px 20px;'
   + 'box-shadow:0 -4px 20px rgba(0,0,0,.22)">'
   + '<b>'+sel.length+' e-mail(s) &middot; '+nped+' pedido(s) &middot; '+BRLc(val)+'</b>'
   + '<span style="margin-left:auto"></span>'
   + (semPdf?('<button class="btn-prim" style="background:#fff;color:var(--verde-d)" onclick="envLoteGerarPdf()">'
       + '<span class="material-icons" style="font-size:16px;vertical-align:-3px">auto_awesome</span> Gerar '+semPdf+' PDF(s)</button>'):'')
   /* "Marcar como só para a obra" fazia parecer AÇÃO DE ENVIO. É uma classificação: o pedido é
      regularização de material que já chegou. O destino (só a obra) é consequência, e está no diálogo. */
   + '<button class="btn-ghost" style="background:transparent;color:#fff;border-color:rgba(255,255,255,.6)" onclick="envLoteSoObra()">'
   + 'Marcar como regularização</button>'
   /* "Conferir e enviar": o lote agora abre um e-mail por vez para conferência, não dispara tudo de uma vez. */
   + (prontos.length?('<button class="btn-prim" style="background:#fff;color:var(--verde-d)" onclick="envLoteEnviar()" '
       + 'title="abre os '+prontos.length+' e-mails um a um para você conferir e enviar">'
       + '<span class="material-icons" style="font-size:16px;vertical-align:-3px">send</span> Conferir e enviar '+prontos.length+'</button>'):'')
   + '<button class="btn-ghost" style="background:transparent;color:#fff;border-color:rgba(255,255,255,.6)" onclick="envSelLimpar()">Limpar</button>'
   + '</div>';
}

/* Selo de FATURAMENTO (TOTVS). Por enquanto é só informação — não muda o que o botão Enviar faz.
   Faturado/quitado/baixado = a nota já foi lançada, o pedido já terminou fora daqui: mandar ao
   fornecedor arrisca segunda entrega. Parcialmente faturado é o caso ambíguo e vem em cor própria. */
function envSeloFat(e){
  const f=+e.n_faturado||0, g=+e.n_parcial||0, n=(e.pedidos||[]).length;
  if(!f&&!g) return '';
  const txt=[];
  if(f) txt.push(f===n?'já faturado no TOTVS':(f+' de '+n+' já faturado(s)'));
  if(g) txt.push(g+' parcialmente faturado(s)');
  return '<div class="dmini" style="color:#8a6d1f;font-weight:700" '
       + 'title="status do pedido no TOTVS — se a nota já foi lançada, o fornecedor não deveria receber o pedido de novo">'
       + '&#128220; '+txt.join(' · ')+'</div>';
}

/* Uma linha = um e-mail. */
function envLinha(e){
  const atras=e.dias>(ENV.d.atraso_dias||3), semPdf=(e.sem_pdf||0)>0;
  const cor = e.alerta ? 'var(--dourado)' : (semPdf ? '#8e44ad' : (atras ? '#c0392b' : 'var(--ok)'));
  const marcado=(ENV.sel||{})[e.chave];
  /* fundo rosa claro quando ha CNPJ na observacao ou sinal de material ja em obra */
  const rosa = (e.alerta || e.forn_travado);
  const fundo = marcado ? '#eef6f0' : (rosa ? '#fdf3f3' : '');
  const ic=(ok,t)=>'<span class="material-icons" title="'+esc(t)+'" style="font-size:14px;vertical-align:-3px;color:'
    +(ok?'var(--ok)':'#8e44ad')+'">'+(ok?'check_circle':'radio_button_unchecked')+'</span>';
  let h='<tr style="border-left:4px solid '+cor+(fundo?(';background:'+fundo):'')+'">';
  h+='<td style="padding:7px 0 7px 8px;vertical-align:top"><input type="checkbox"'+(marcado?' checked':'')
   + ' onchange="envSelToggle('+jsArg(e.chave)+')"></td>';
  h+='<td style="padding:7px 8px;max-width:260px">'
   + '<div style="font-weight:700;font-size:12.5px">'+esc(e.forn_nome)+'</div>'
   + '<div class="dmini" style="color:var(--muted)">'+(e.destino==='obra'?'<b>cópia p/ lançamento</b>':esc(e.para))+'</div>'
   + (e.alerta?'<div class="dmini" style="color:#c0392b;font-weight:700">&#9888; material pode já estar em obra — confira</div>':'')
   + (!e.alerta&&e.forn_travado?'<div class="dmini" style="color:#c0392b;font-weight:700">&#9888; CNPJ na observação — pode ser só para a obra</div>':'')
   + envSeloFat(e)
   + '</td>';
  h+='<td style="padding:7px 8px;white-space:nowrap">'
   + (e.pedidos||[]).slice(0,4).map(p=>'<button class="btn-ghost" style="padding:1px 7px;font-size:11px;margin:1px'
       + (p.tem_pdf?'':';color:#8e44ad;font-weight:700')+'" title="'+(p.tem_pdf?'PDF pronto — clique para ver':'sem PDF ainda')+'" '
       + 'onclick="envVerPedido(\''+p.coligada_cod+'\',\''+p.numero+'\')">'+esc(p.numero)+'</button>').join('')
   + ((e.pedidos||[]).length>4?('<span class="dmini"> +'+((e.pedidos||[]).length-4)+'</span>'):'')
   + '</td>';
  h+='<td style="padding:7px 8px;white-space:nowrap;text-align:center">'
   + ic(true,'Aprovado no Fluig') + ic(true,'Obra conferida') + ic(true,'Nunca enviado')
   + ic(!semPdf, semPdf?('falta o PDF de '+e.sem_pdf+' pedido(s)'):'PDF gerado')
   + '</td>';
  h+='<td style="padding:7px 8px;text-align:right;white-space:nowrap"><b>'+BRL(e.valor)+'</b></td>';
  h+='<td style="padding:7px 8px;text-align:right;white-space:nowrap">'
   + '<span class="dchip" style="background:'+(atras?'var(--dourado)':'var(--muted)')+'">'
   + (e.dias===0?'hoje':(e.dias+'d'))+'</span></td>';
  h+='<td style="padding:5px 8px;text-align:right;white-space:nowrap">'
   + (semPdf?('<button class="btn-prim" style="padding:3px 9px;font-size:11px" onclick="envGerarPdfLote(\''+e.chave+'\')">Gerar PDF</button> '):'')
   + '<button class="btn-ghost" style="padding:3px 9px;font-size:11px" onclick="envVerEmail(\''+e.chave+'\')">Ver</button> '
   + '<button class="btn-ghost" style="padding:3px 9px;font-size:11px" onclick="envDecidir(\''+e.chave+'\',\'so_obra\')" title="marca como REGULARIZAÇÃO (material já entregue): o fornecedor não recebe nada e o e-mail passa a ir só para a obra, para lançamento. Não envia nada agora.">Só obra</button> '
   + '<button class="btn-ghost" style="padding:3px 9px;font-size:11px" onclick="envDecidir(\''+e.chave+'\',\'segurar\')" title="retém de propósito, com motivo e seu nome; fica visível na aba Segurados até alguém liberar">Segurar</button> '
   + (semPdf?'':'<button class="btn-prim" style="padding:3px 11px;font-size:11px" onclick="envEnviar(\''+e.chave+'\')">Enviar</button>')
   + '</td></tr>';
  return h;
}
