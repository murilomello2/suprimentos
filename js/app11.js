/* Cockpit de Suprimentos — CADASTRO DE FORNECEDOR EM LOTE (a lista que o comprador achou de uma vez).

   Por que existe: o comprador pesquisa fornecedor na IA e volta com uma tabela de 5, 10, 20
   empresas. Cadastrar isso um por um, campo por campo, é onde a pesquisa morria — dava trabalho,
   ninguém cadastrava, e no mês seguinte outra pessoa pesquisava a mesma coisa.

   Dois caminhos que terminam no MESMO rascunho conferível:
     • IA      — cola o print (Ctrl+V) ou o texto; nossa IA transcreve e classifica.
     • MÁSCARA — planilha com as colunas fixas; nenhuma IA envolvida na leitura. Para quem quiser
                 usar a IA dela (ChatGPT/Gemini), o botão entrega o pedido pronto no formato certo.

   E nunca grava sem alguém olhar: o rascunho mostra o que é novo, o que JÁ existe (e o que o
   cadastro antigo ganharia), qual número é celular de verdade e qual categoria não existe na base.
   Detalhes do servidor: actions/forn_lote.php. */

let FLOT = null;

function fornLoteAbrir() {
  FLOT = { aba: 'ia', contexto: '', texto: '', tab: '', imgs: [], linhas: null, lendo: false,
           gravando: false, avisos: [], modelo: '', custo: 0, resultado: null, mascara: null, promptAberto: false };
  FORN.lote = 1; fornRender();
  fornLoteMascara();
}
function fornLoteFechar(recarregar) { FLOT = null; FORN.lote = 0; if (recarregar) fornLoad(); else fornRender(); }
async function fornLoteMascara() {
  try {
    const r = await (await fetch('actions/forn_lote.php?me=' + encodeURIComponent((EU && EU.bitrix_id) || ''))).json();
    if (r.error) { toast(r.error); return; }
    FLOT.mascara = r; if (FLOT.aba === 'tabela') fornRender();
  } catch (e) { }
}

/* ---------- leitura ---------- */
async function fornLoteLer() {
  if (!FLOT || FLOT.lendo) return;
  const ia = FLOT.aba === 'ia';
  if (ia && !FLOT.imgs.length && !FLOT.texto.trim()) { toast('Cole o print da lista (Ctrl+V) ou o texto dela'); return; }
  if (!ia && !FLOT.tab.trim()) { toast('Cole a tabela'); return; }
  FLOT.lendo = true; FLOT.linhas = null; FLOT.resultado = null; fornRender();
  try {
    const r = await (await fetch('actions/forn_lote.php', { method: 'POST', headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ acao: 'ler', me: EU && EU.bitrix_id, origem: ia ? 'ia' : 'tabela',
        contexto: FLOT.contexto, texto: ia ? FLOT.texto : FLOT.tab, imagens: ia ? FLOT.imgs.map(i => i.du) : [] }) })).json();
    if (r.error) { toast(r.error); FLOT.lendo = false; fornRender(); return; }
    FLOT.linhas = r.linhas || []; FLOT.avisos = r.avisos || []; FLOT.modelo = r.modelo || ''; FLOT.custo = r.custo || 0;
    if (r.categorias) { FLOT.cats = r.categorias; FORN.cats = r.categorias.map(n => ({ nome: n })); }
    FLOT.tipos = r.tipos || []; FLOT.fontes = r.fontes || FORN.fontes || [];
    toast(FLOT.linhas.length + ' fornecedor(es) lidos — confira antes de cadastrar');
  } catch (e) { toast('Falha: ' + e.message); }
  FLOT.lendo = false; fornRender();
}

/* colar print: o clipboard traz a imagem como File; guardamos o data URL (o servidor confere os
   bytes de verdade — nome e tipo declarado não valem nada) */
function fornLotePaste(ev) {
  const it = (ev.clipboardData && ev.clipboardData.items) || [];
  let achou = 0;
  for (const i of it) {
    if (String(i.type || '').indexOf('image/') !== 0) continue;
    const f = i.getAsFile(); if (!f) continue;
    achou++; fornLoteAddImg(f);
  }
  if (achou) { ev.preventDefault(); }
}
function fornLoteArquivos(input) { for (const f of (input.files || [])) fornLoteAddImg(f); input.value = ''; }
function fornLoteAddImg(f) {
  if (FLOT.imgs.length >= 4) { toast('Máximo de 4 prints por leitura'); return; }
  if (f.size > 6 * 1024 * 1024) { toast('Imagem acima de 6 MB — recorte o print'); return; }
  const fr = new FileReader();
  fr.onload = () => { FLOT.imgs.push({ du: String(fr.result), nome: f.name || 'print colado', kb: Math.round(f.size / 1024) }); fornLoteThumbs(); };
  fr.readAsDataURL(f);
}
function fornLoteDelImg(i) { FLOT.imgs.splice(i, 1); fornLoteThumbs(); }
/* redesenha SÓ as miniaturas — um fornRender() aqui apagaria o que a pessoa está digitando no
   contexto. E sai calado se a área não está na tela (aba da planilha não tem miniatura): chamar
   fornRender() daqui fazia render → thumbs → render, recursão até estourar a pilha. */
function fornLoteThumbs() {
  const b = document.getElementById('flotThumbs'); if (!b) return;
  b.innerHTML = FLOT.imgs.map((im, i) => `<div style="position:relative;display:inline-block;margin:0 7px 7px 0">
    <img src="${im.du}" style="height:76px;border:1px solid var(--line);border-radius:8px;display:block">
    <div class="dmini" style="text-align:center;max-width:120px;overflow:hidden;text-overflow:ellipsis">${esc(im.nome)} · ${im.kb} KB</div>
    <span class="material-icons" title="remover" onclick="fornLoteDelImg(${i})"
      style="position:absolute;top:-7px;right:-7px;font-size:17px;background:#fff;border-radius:50%;cursor:pointer;color:var(--pend);box-shadow:0 1px 4px rgba(0,0,0,.2)">cancel</span>
  </div>`).join('');
}

/* ---------- edição do rascunho ---------- */
function fornLoteSet(i, campo, v) { if (FLOT && FLOT.linhas && FLOT.linhas[i]) FLOT.linhas[i][campo] = v; }
function fornLoteAcao(i, v) { FLOT.linhas[i].acao = v; fornRender(); }
function fornLoteFonte(i, v) { FLOT.linhas[i].fonte = v; fornRender(); }
/* "aplicar a todos": categoria e fonte quase sempre valem para a lista inteira (é UMA pesquisa) —
   digitar 20 vezes a mesma coisa é o tipo de trabalho que faz a pessoa desistir no meio */
function fornLoteTodos(campo) {
  const v = val('flotTodos_' + campo);
  if (v === '') { toast('Escolha o valor primeiro'); return; }
  let n = 0;
  for (const l of FLOT.linhas) { if (l.acao === 'pular') continue; l[campo] = v; if (campo === 'categoria') l.categoria_nova = 0; n++; }
  fornRender(); toast(n + ' linha(s) atualizada(s)');
}
function fornLoteMarcarTodos(acao) {
  for (const l of FLOT.linhas) l.acao = (acao === 'pular') ? 'pular' : (l.existe ? (l.existe.ganha.length ? 'complementar' : 'pular') : 'criar');
  fornRender();
}

async function fornLoteGravar() {
  if (!FLOT || FLOT.gravando) return;
  const vao = FLOT.linhas.filter(l => l.acao !== 'pular');
  if (!vao.length) { toast('Nenhuma linha marcada para entrar'); return; }
  const semNome = vao.filter(l => !String(l.nome || '').trim()).length;
  if (semNome) { toast(semNome + ' linha(s) sem nome — preencha ou marque como "não entra"'); return; }
  const criar = vao.filter(l => !l.existe).length, compl = vao.length - criar;
  if (!confirm(`Cadastrar ${criar} fornecedor(es) novo(s)` + (compl ? ` e complementar ${compl} já existente(s)` : '') +
    `?\n\nNos que já existem, só os campos VAZIOS são preenchidos — nada que já estava lá é sobrescrito.`)) return;
  FLOT.gravando = true; fornRender();
  try {
    const r = await (await fetch('actions/forn_lote.php', { method: 'POST', headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ acao: 'gravar', me: EU && EU.bitrix_id, linhas: FLOT.linhas }) })).json();
    if (r.error) { toast(r.error); FLOT.gravando = false; fornRender(); return; }
    FLOT.resultado = r; FLOT.linhas = null;
    toast(`${r.criados} cadastrado(s) · ${r.complementados} complementado(s)${r.pulados ? ' · ' + r.pulados + ' fora' : ''}`);
  } catch (e) { toast('Falha: ' + e.message); }
  FLOT.gravando = false; fornRender();
}

function fornLoteCopiarPrompt() {
  const t = (FLOT.mascara && FLOT.mascara.prompt) || '';
  const el = document.getElementById('flotPrompt');
  try { if (el) { el.focus(); el.select(); } navigator.clipboard.writeText(t); toast('Pedido copiado — cole na IA que você usa'); }
  catch (e) { try { document.execCommand('copy'); toast('Pedido copiado'); } catch (e2) { toast('Selecione o texto e copie com Ctrl+C'); } }
}

/* ---------- tela ---------- */
function fornLoteRender() {
  const w = document.getElementById('cotwrap'); if (!w) return;
  if (!FLOT) { FORN.lote = 0; return fornRender(); }   // sem rascunho não há tela de lote — volta à lista
  const tab = (k, lbl, ic) => `<button class="btn-ghost" style="padding:7px 14px;border-radius:9px 9px 0 0;${FLOT.aba === k ? 'background:#fff;border-bottom:2px solid var(--verde);font-weight:700;color:var(--verde-d)' : 'color:var(--muted)'}" onclick="FLOT.aba='${k}';fornRender()"><span class="material-icons" style="font-size:15px;vertical-align:-3px">${ic}</span> ${lbl}</button>`;
  let h = `<div class="panel" style="margin-bottom:10px"><div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap">
    <button class="btn-ghost" onclick="fornLoteFechar(1)"><span class="material-icons" style="font-size:16px;vertical-align:-3px">arrow_back</span> Voltar</button>
    <b style="font-size:15px">Cadastrar fornecedores em lote</b>
    <span class="muted" style="font-size:11.5px">— a lista inteira de uma vez, com conferência antes de gravar</span>
  </div></div>`;

  if (FLOT.resultado) return void (w.innerHTML = h + fornLoteResultado());

  h += `<div style="display:flex;gap:4px;border-bottom:1px solid var(--line);margin-bottom:12px">
    ${tab('ia', 'Colar print ou texto (IA)', 'auto_awesome')}${tab('tabela', 'Planilha / máscara', 'table_view')}</div>`;
  h += '<div class="panel">' + (FLOT.aba === 'ia' ? fornLoteAbaIA() : fornLoteAbaTabela()) + '</div>';
  if (FLOT.linhas) h += fornLoteRevisao();
  w.innerHTML = h;
  const p = document.getElementById('flotPasteBox');
  if (p) { p.onpaste = fornLotePaste; fornLoteThumbs(); }
}

function fornLoteCtx() {
  return `${fornSecHead('psychology', 'O que essa gente fornece?', 'vale para a lista toda — é isto que orienta a categoria e os itens de cada um')}
    <textarea id="flotCtx" rows="2" oninput="FLOT.contexto=this.value" placeholder="Ex.: laboratórios de controle tecnológico de concreto — rompimento de corpo de prova, extração de testemunho, ensaio de agregados. Interior de SP."
      style="width:100%;resize:vertical;font-family:inherit;font-size:13px;padding:8px 10px;border:1px solid var(--line);border-radius:8px">${esc(FLOT.contexto)}</textarea>`;
}

function fornLoteAbaIA() {
  let h = fornLoteCtx();
  h += fornSecHead('content_paste', 'O print da lista', 'clique na área e dê Ctrl+V — até 4 imagens, 6 MB no total');
  h += `<div id="flotPasteBox" tabindex="0" onclick="this.focus()"
      style="border:2px dashed var(--line);border-radius:11px;padding:16px;text-align:center;background:#fafcfb;outline:none;cursor:text">
      <span class="material-icons" style="font-size:26px;color:var(--muted)">image</span>
      <div class="muted" style="font-size:12.5px;margin-top:2px">Clique aqui e cole o print (Ctrl+V)</div>
      <div class="dmini" style="margin-top:4px">ou <label style="color:var(--verde-d);text-decoration:underline;cursor:pointer">escolha o arquivo<input type="file" accept="image/png,image/jpeg" multiple onchange="fornLoteArquivos(this)" style="display:none"></label> — PNG ou JPG</div>
      <div id="flotThumbs" style="margin-top:10px"></div>
    </div>`;
  h += fornSecHead('subject', 'Ou o texto da lista', 'a resposta da IA em texto, um e-mail, o que estiver na mão — pode ser junto com o print');
  h += `<textarea id="flotTxt" rows="4" oninput="FLOT.texto=this.value" placeholder="Cole aqui o texto da lista de fornecedores…"
      style="width:100%;resize:vertical;font-family:inherit;font-size:12.5px;padding:8px 10px;border:1px solid var(--line);border-radius:8px">${esc(FLOT.texto)}</textarea>`;
  h += `<div style="margin-top:14px;display:flex;gap:9px;align-items:center;flex-wrap:wrap">
      <button class="btn-prim" onclick="fornLoteLer()" ${FLOT.lendo ? 'disabled' : ''}>
        <span class="material-icons" style="font-size:16px;vertical-align:-3px">auto_awesome</span> ${FLOT.lendo ? 'Lendo…' : 'Ler a lista com a IA'}</button>
      <span class="dmini">a IA transcreve e classifica; nada é gravado até você conferir e confirmar</span>
    </div>`;
  return h;
}

function fornLoteAbaTabela() {
  const m = FLOT.mascara;
  let h = fornSecHead('table_view', 'A máscara', 'as colunas que o sistema lê — na planilha ou pedindo à IA que você já usa');
  h += `<div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;margin-bottom:4px">
    <a class="btn-ghost" style="padding:6px 12px;font-size:12.5px;text-decoration:none" href="actions/forn_lote.php?modelo=1&me=${encodeURIComponent((EU && EU.bitrix_id) || '')}">
      <span class="material-icons" style="font-size:15px;vertical-align:-3px">download</span> Baixar o modelo (CSV)</a>
    <button class="btn-ghost" style="padding:6px 12px;font-size:12.5px" onclick="fornLoteCopiarPrompt()">
      <span class="material-icons" style="font-size:15px;vertical-align:-3px">content_copy</span> Copiar o pedido pronto para a IA</button>
    <button class="btn-ghost" style="padding:6px 12px;font-size:12.5px" onclick="FLOT.promptAberto=!FLOT.promptAberto;fornRender()">
      <span class="material-icons" style="font-size:15px;vertical-align:-3px">${FLOT.promptAberto ? 'expand_more' : 'chevron_right'}</span> ${FLOT.promptAberto ? 'Esconder' : 'Ver'} o pedido</button>
    ${m ? `<span class="dmini">colunas: ${esc(m.colunas.join(' · '))}</span>` : '<span class="dmini">carregando a máscara…</span>'}
  </div>`;
  if (FLOT.promptAberto)
    h += `<textarea id="flotPrompt" rows="12" readonly onclick="this.select()"
      style="width:100%;resize:vertical;font-family:ui-monospace,Consolas,monospace;font-size:11.5px;padding:9px 11px;border:1px solid var(--line);border-radius:8px;background:#fafcfb">${esc((m && m.prompt) || '')}</textarea>`;
  h += fornLoteCtx();
  h += fornSecHead('content_paste_go', 'Cole a tabela', 'do Excel (Ctrl+C / Ctrl+V), CSV com ponto e vírgula, ou a tabela que a IA devolveu — o cabeçalho é reconhecido escrito de qualquer jeito');
  h += `<textarea id="flotTab" rows="7" oninput="FLOT.tab=this.value" placeholder="Nome;CNPJ;Contato;E-mail;Telefone;WhatsApp;Cidade;Categoria;Tipo;Itens que fornece;Observação;Fonte&#10;Fornecedor Exemplo Ltda;;João;joao@exemplo.com.br;(19) 3524-0000;(19) 99999-0000;Campinas/SP;Concreto;Fabricante;concreto usinado, bombeamento;;IA"
      style="width:100%;resize:vertical;font-family:ui-monospace,Consolas,monospace;font-size:11.5px;padding:9px 11px;border:1px solid var(--line);border-radius:8px">${esc(FLOT.tab)}</textarea>`;
  h += `<div style="margin-top:14px;display:flex;gap:9px;align-items:center;flex-wrap:wrap">
      <button class="btn-prim" onclick="fornLoteLer()" ${FLOT.lendo ? 'disabled' : ''}>
        <span class="material-icons" style="font-size:16px;vertical-align:-3px">fact_check</span> ${FLOT.lendo ? 'Conferindo…' : 'Conferir a tabela'}</button>
      <span class="dmini">coluna que não é nossa não se perde: vira observação</span>
    </div>`;
  return h;
}

/* ---------- o rascunho conferível ---------- */
function fornLoteRevisao() {
  const L = FLOT.linhas, F = FLOT.fontes || FORN.fontes || [], T = FLOT.tipos || FORN.tipos || [];
  const novos = L.filter(l => !l.existe && l.acao !== 'pular').length;
  const compl = L.filter(l => l.existe && l.acao === 'complementar').length;
  const fora = L.filter(l => l.acao === 'pular').length;
  const semMail = L.filter(l => !String(l.email || '').trim() && l.acao !== 'pular').length;
  const catsNovas = L.filter(l => l.categoria_nova && l.acao !== 'pular').length;

  let h = `<div class="panel" style="margin-top:12px;margin-bottom:10px"><div style="display:flex;gap:9px;flex-wrap:wrap;align-items:center">
    <b style="font-size:14px">Confira antes de cadastrar</b>
    <span class="dchip" style="background:var(--verde);font-size:10px">${novos} novo(s)</span>
    ${compl ? `<span class="dchip" style="background:#3d7fbf;font-size:10px">${compl} complementa cadastro que já existe</span>` : ''}
    ${fora ? `<span class="dchip" style="background:#8a9299;font-size:10px">${fora} fora</span>` : ''}
    ${semMail ? `<span class="dchip" style="background:var(--dourado);color:#3d3115;font-size:10px">${semMail} sem e-mail</span>` : ''}
    ${catsNovas ? `<span class="dchip" style="background:#a4761c;font-size:10px">${catsNovas} categoria(s) que não existem na base</span>` : ''}
    ${FLOT.modelo ? `<span class="dmini">lido por ${esc(FLOT.modelo)}${FLOT.custo ? ' · US$ ' + Number(FLOT.custo).toFixed(4) : ''}</span>` : ''}
  </div>`;
  if ((FLOT.avisos || []).length)
    h += `<div style="margin-top:8px;background:#fdf4e3;border:1px solid #f0e0bb;border-radius:9px;padding:8px 11px;font-size:12px;color:#7a5f1c">
      <span class="material-icons" style="font-size:14px;vertical-align:-2px">info</span> ${FLOT.avisos.map(esc).join(' · ')}</div>`;
  // aplicar a todos + marcar todos
  h += `<div class="bar" style="gap:8px;flex-wrap:wrap;align-items:center;margin-top:10px;padding-top:9px;border-top:1px solid var(--line)">
    <span class="dmini">aplicar a todos:</span>
    <select id="flotTodos_categoria" style="font-size:12px;max-width:210px"><option value="">— categoria —</option>${(FLOT.cats || []).map(c => `<option value="${esc(c)}">${esc(c)}</option>`).join('')}</select>
    <button class="btn-ghost" style="padding:4px 9px;font-size:11.5px" onclick="fornLoteTodos('categoria')">aplicar</button>
    <select id="flotTodos_tipo" style="font-size:12px"><option value="">— tipo —</option>${T.map(t => `<option value="${esc(t)}">${esc(t)}</option>`).join('')}</select>
    <button class="btn-ghost" style="padding:4px 9px;font-size:11.5px" onclick="fornLoteTodos('tipo')">aplicar</button>
    <select id="flotTodos_fonte" style="font-size:12px"><option value="">— fonte —</option>${F.map(x => `<option value="${esc(x.v)}">${esc(x.lbl)}</option>`).join('')}</select>
    <button class="btn-ghost" style="padding:4px 9px;font-size:11.5px" onclick="fornLoteTodos('fonte')">aplicar</button>
    <span style="margin-left:auto"></span>
    <button class="btn-ghost" style="padding:4px 9px;font-size:11.5px" onclick="fornLoteMarcarTodos('entra')">marcar todos p/ entrar</button>
    <button class="btn-ghost" style="padding:4px 9px;font-size:11.5px;color:var(--pend)" onclick="fornLoteMarcarTodos('pular')">nenhum</button>
  </div></div>`;

  const G = 'display:grid;gap:8px;align-items:end';
  const cmp = (i, campo, ph, lbl) => `<div><div class="muted" style="font-size:10.5px;margin-bottom:2px">${lbl}</div>
    <input value="${esc(FLOT.linhas[i][campo] || '')}" placeholder="${ph}" oninput="fornLoteSet(${i},'${campo}',this.value)" style="width:100%;font-size:12px;padding:5px 7px"></div>`;

  L.forEach((l, i) => {
    const off = l.acao === 'pular';
    const ex = l.existe;
    let selo = ex
      ? `<span class="dchip" style="background:#3d7fbf;font-size:9.5px" title="casou por ${esc(l.existe_por || '')} com o cadastro #${ex.id}">já existe #${ex.id}</span>`
      : `<span class="dchip" style="background:var(--verde);font-size:9.5px">novo</span>`;
    if (ex && ex.ganha.length) selo += ` <span class="dmini">ganha: ${esc(ex.ganha.join(', '))}</span>`;
    if (ex && !ex.ganha.length) selo += ` <span class="dmini">o cadastro atual já tem tudo o que esta linha traz</span>`;
    h += `<div class="dcard wide" style="margin-bottom:8px;padding:11px 13px;${off ? 'opacity:.45' : ''}">
      <div style="display:flex;gap:9px;align-items:center;flex-wrap:wrap;margin-bottom:8px">
        <select onchange="fornLoteAcao(${i},this.value)" style="font-size:11.5px;padding:3px 6px">
          <option value="criar" ${l.acao === 'criar' ? 'selected' : ''} ${ex ? 'disabled' : ''}>entra como novo</option>
          <option value="complementar" ${l.acao === 'complementar' ? 'selected' : ''} ${ex ? '' : 'disabled'}>complementa o que existe</option>
          <option value="pular" ${l.acao === 'pular' ? 'selected' : ''}>não entra</option>
        </select>
        ${selo}
        ${(l.avisos || []).length ? `<span class="dmini" style="color:#a4761c">⚠ ${l.avisos.map(esc).join(' · ')}</span>` : ''}
        ${l.wa_do_telefone ? '<span class="dmini">WhatsApp preenchido a partir do telefone (é celular)</span>' : ''}
      </div>
      <div style="${G};grid-template-columns:2fr 1.2fr 1.2fr">
        ${cmp(i, 'nome', 'Nome do fornecedor', 'Nome *')}
        ${cmp(i, 'cnpj', '00.000.000/0000-00', 'CNPJ')}
        ${cmp(i, 'cidade', 'Cidade/UF', 'Cidade')}
      </div>
      <div style="${G};grid-template-columns:1.2fr 1.6fr 1fr 1fr;margin-top:7px">
        ${cmp(i, 'contato', 'Nome de quem atende', 'Contato')}
        ${cmp(i, 'email', 'contato@fornecedor.com.br', 'E-mail')}
        ${cmp(i, 'telefone', '(00) 0000-0000', 'Telefone')}
        ${cmp(i, 'whatsapp', '(00) 00000-0000', 'WhatsApp')}
      </div>
      <div style="${G};grid-template-columns:1.6fr 1fr 1fr ${l.fonte === 'indicacao' ? '1.2fr' : ''};margin-top:7px">
        <div><div class="muted" style="font-size:10.5px;margin-bottom:2px">Categoria ${l.categoria_nova ? '<b style="color:#a4761c">(não existe na base — será criada)</b>' : ''}</div>
          <input value="${esc(l.categoria || '')}" list="flotCats" placeholder="Categoria" oninput="fornLoteSet(${i},'categoria',this.value)"
            style="width:100%;font-size:12px;padding:5px 7px;${l.categoria_nova ? 'border-color:#e0c98b;background:#fffdf6' : ''}"></div>
        <div><div class="muted" style="font-size:10.5px;margin-bottom:2px">Tipo</div>
          <select onchange="fornLoteSet(${i},'tipo',this.value)" style="width:100%;font-size:12px;padding:5px 7px">
            <option value=""></option>${T.map(t => `<option value="${esc(t)}" ${t === l.tipo ? 'selected' : ''}>${esc(t)}</option>`).join('')}</select></div>
        <div><div class="muted" style="font-size:10.5px;margin-bottom:2px">Fonte</div>
          <select onchange="fornLoteFonte(${i},this.value)" style="width:100%;font-size:12px;padding:5px 7px">
            ${F.map(x => `<option value="${esc(x.v)}" ${x.v === l.fonte ? 'selected' : ''}>${esc(x.lbl)}</option>`).join('')}</select></div>
        ${l.fonte === 'indicacao' ? cmp(i, 'indicado_por', 'Nome de quem indicou', 'Quem indicou') : ''}
      </div>
      <div style="${G};grid-template-columns:1.4fr 1fr;margin-top:7px">
        ${cmp(i, 'itens', 'o que ele vende ou executa — palavras-chave separadas por vírgula', 'Itens que fornece <span class="dmini">(é por aqui que a busca acha)</span>')}
        ${cmp(i, 'observacao', 'certificação, restrição, o que mais importar', 'Observação <span class="dmini">(vai como detalhe da fonte)</span>')}
      </div>
    </div>`;
  });
  h += `<datalist id="flotCats">${(FLOT.cats || []).map(c => `<option value="${esc(c)}">`).join('')}</datalist>`;
  h += `<div class="panel" style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">
    <button class="btn-prim" onclick="fornLoteGravar()" ${FLOT.gravando ? 'disabled' : ''}>
      <span class="material-icons" style="font-size:16px;vertical-align:-3px">check</span>
      ${FLOT.gravando ? 'Gravando…' : `Cadastrar ${novos + compl} fornecedor(es)`}</button>
    <button class="btn-ghost" onclick="FLOT.linhas=null;fornRender()">Descartar este rascunho</button>
    <span class="dmini">nos que já existem, só campo VAZIO é preenchido — nada do que está lá é sobrescrito</span>
  </div>`;
  return h;
}

function fornLoteResultado() {
  const r = FLOT.resultado;
  const cor = { criado: 'var(--verde)', complementado: '#3d7fbf', pulado: '#8a9299', 'nada a acrescentar': '#8a9299' };
  let h = `<div class="panel"><div style="display:flex;gap:9px;align-items:center;flex-wrap:wrap;margin-bottom:10px">
    <span class="material-icons" style="font-size:20px;color:var(--verde)">task_alt</span>
    <b style="font-size:15px">${r.criados} cadastrado(s)${r.complementados ? ' · ' + r.complementados + ' complementado(s)' : ''}${r.pulados ? ' · ' + r.pulados + ' fora' : ''}</b>
  </div>`;
  if ((r.categorias_criadas || []).length)
    h += `<div style="background:#fdf4e3;border:1px solid #f0e0bb;border-radius:9px;padding:8px 11px;font-size:12px;color:#7a5f1c;margin-bottom:9px">
      categoria(s) criada(s) agora: <b>${r.categorias_criadas.map(esc).join(', ')}</b> — se alguma foi engano, dá para acertar em cada cadastro</div>`;
  h += '<div class="wrap"><table><thead><tr><th>Fornecedor</th><th>O que aconteceu</th><th>Campos preenchidos</th></tr></thead><tbody>';
  for (const x of (r.resultados || []))
    h += `<tr><td><b>${esc(x.nome)}</b>${x.id ? ` <span class="dmini">#${x.id}</span>` : ''}</td>
      <td><span class="dchip" style="background:${cor[x.acao] || '#8a9299'};font-size:10px">${esc(x.acao)}</span></td>
      <td class="muted" style="font-size:11.5px">${esc((x.campos || []).join(', '))}</td></tr>`;
  h += `</tbody></table></div>
    <div style="margin-top:14px;display:flex;gap:9px;flex-wrap:wrap">
      <button class="btn-prim" onclick="fornLoteFechar(1)">Ver a lista de fornecedores</button>
      <button class="btn-ghost" onclick="fornLoteAbrir()">Cadastrar outra lista</button>
    </div></div>`;
  return h;
}
