# API de Suprimentos — Caprem

Porta de **leitura** do Cockpit de Suprimentos para outros sistemas.
Nenhum endpoint desta API grava, altera ou apaga qualquer coisa. É só consulta.

- **Endereço:** `https://appdemo.capremconstrutora.com.br/suprimentos/actions/api.php`
- **Formato:** JSON (UTF-8)
- **Autenticação:** header `X-API-Key: <a chave que a Caprem te enviar>`
- **Versão:** 1.0

Chamar o endereço **sem nenhum parâmetro** devolve o índice da API com a lista de recursos e filtros.
É a única rota que não exige chave — serve para conferir que a conexão está de pé.

```bash
curl "https://appdemo.capremconstrutora.com.br/suprimentos/actions/api.php"
```

---

## Como chamar

Todo recurso é um `GET` com o parâmetro `recurso`:

```bash
curl -H "X-API-Key: SUA_CHAVE" \
  "https://appdemo.capremconstrutora.com.br/suprimentos/actions/api.php?recurso=radar&obra=Diamond"
```

Se a chave faltar ou estiver errada, a resposta é `401` com `{"ok":false,"erro":"..."}`.

### Formato da resposta

Toda listagem vem no mesmo envelope:

```json
{
  "ok": true,
  "recurso": "radar",
  "total": 157,
  "pagina": 1,
  "paginas": 2,
  "por_pagina": 100,
  "gerado_em": "2026-07-28T21:40:11-03:00",
  "dados": [ ... ]
}
```

`pagina` e `por_pagina` funcionam em todos os recursos. Padrão 100 por página, máximo 500.

---

## Recurso 1 — `?recurso=obras`

A lista de obras. **Comece por aqui**: o `obra_id` que aparece nesta lista é o que os outros
recursos aceitam no filtro.

| campo | o que é |
|---|---|
| `obra_id` | identificador da obra nesta API |
| `obra` | nome da obra |
| `cidade`, `estado` | localização |
| `situacao` | Em Andamento / Iniciando / Finalizada |
| `no_radar` | `false` = a obra existe no cadastro mas ainda não tem itens de compra acompanhados |

---

## Recurso 2 — `?recurso=radar`

O **radar de compras**: cada linha é um item que a Caprem precisa comprar para uma obra, com
quem é o responsável, em que pé está e os prazos.

**Filtros:** `obra_id` · `obra` (nome) · `status` · `responsavel` · `alerta` · `grupo` · `q` (busca livre) · `com_cotacao=1|0`

| campo | o que é |
|---|---|
| `obra_id`, `obra` | a obra |
| `item_id`, `item`, `grupo` | o que está sendo comprado |
| `curva` | curva ABC do item |
| `responsavel` | o comprador dono do item |
| `status` | ver tabela abaixo |
| `status_automatico` | `true` = o status virou "Cotação Iniciada" sozinho porque já existe cotação |
| `inicio_cotacao` | quando a cotação **deveria começar** |
| `fim_cotacao` | quando a cotação **precisa estar fechada** |
| `data_em_obra` | quando o material precisa estar no canteiro |
| `data_em_obra_origem` | `curada` (definida à mão) · `cronograma` (veio do cronograma vivo) · `sem data` |
| `marco_cronograma` | a tarefa do cronograma que ancora a data |
| `lead_dias` | prazo de fabricação/entrega considerado |
| `alerta`, `alerta_texto` | o semáforo de prazo — ver tabela abaixo |
| `verba_definida` | a verba confirmada. `null` = ainda não foi definida |
| `verba_estimada` | estimativa preliminar do orçamento. **Não é a mesma coisa** que a definida |
| `quantidade`, `unidade` | o quantitativo a comprar |
| `fornecedor` | fornecedor anotado à mão (quando não há cotação) |
| `cotacao` | bloco da cotação vinculada, ou `null` — ver abaixo |

### Valores de `status`

| valor | significado |
|---|---|
| `Não Iniciado` | a cotação ainda não começou |
| `Cotação Iniciada` | já existe mapa de cotação aberto |
| `Com Pendências` | travado esperando alguma coisa |
| `Em Andamento` | negociação rolando |
| `Finalizado` | concluído |
| `Não se aplica` | o item não vale para esta obra |

### Valores de `alerta`

| valor | `alerta_texto` | quando acontece |
|---|---|---|
| `critico` | Prazo de cotação estourado | passou do `fim_cotacao` e não fechou |
| `atrasado` | Atrasado para iniciar | passou do `inicio_cotacao` e ainda está "Não Iniciado" |
| `proximo` | Começar a cotar agora | faltam 7 dias ou menos para o `inicio_cotacao` |
| `ok` | No prazo | tudo certo |
| `finalizado` | Concluído | item finalizado ou não aplicável |

### O bloco `cotacao` (o "botão de ver a cotação")

Aparece preenchido quando o item já tem cotação. É o que permite montar o botão:

```json
"cotacao": {
  "cotacao_id": 78,
  "titulo": "Martelete de 11kg",
  "status": "finalizada",
  "status_texto": "Finalizada",
  "finalizada": true,
  "fornecedores_convidados": 3,
  "fornecedores_disparados": 0,
  "propostas_recebidas": 3,
  "melhor_oferta": 5111.14,
  "quantas_cotacoes": 1,
  "detalhe_url": "api.php?recurso=cotacao&id=78"
}
```

- **convidados** = fornecedores chamados para a concorrência
- **disparados** = para quantos o e-mail de cotação realmente saiu
- **propostas_recebidas** = quantos já mandaram preço

---

## Recurso 3 — `?recurso=solicitacoes`

As **solicitações de compra (SC)** que vêm do TOTVS, com o andamento da cotação **item a item**.

**Filtros:** `obra_id` · `obra` · `status` · `comprador` · `situacao` · `q`

| campo | o que é |
|---|---|
| `numero` | nº da SC no TOTVS (com zeros à esquerda) |
| `coligada` | razão social da empresa que emitiu |
| `centro_custo` | centro de custo da SC |
| `obra_id`, `obra` | a obra |
| `comprador` | comprador responsável |
| `emissao`, `dias_em_aberto` | quando foi pedida e há quantos dias |
| `status`, `status_texto` | ver tabela abaixo |
| `tem_cotacao` | `true` = já existe cotação tocando esta SC |
| `cotacao_situacao` | `vazio` · `parcial` · `total` |
| `itens_total`, `itens_cotados` | quantos itens da SC já estão cotados |
| `cotacoes` | as cotações que atendem esta SC (mesmo formato do recurso 4) |
| `itens[]` | cada linha da SC |

Cada item traz `seq`, `codigo`, `produto`, `quantidade`, `unidade`, `observacao` e:

| `situacao` | `situacao_texto` |
|---|---|
| `vazio` | Sem cotação |
| `cotando` | Em cotação |
| `coberto` | Cotada (cotação finalizada ou já virou pedido de compra) |

### Valores de `status` da SC

`pendente` · `em_cotacao` · `cotacoes_recebidas` · `pedido_criado` · `cancelado`

> **Atenção:** a fila só traz SCs **pendentes** no TOTVS. Se uma SC sumiu da lista, ela foi
> atendida ou cancelada lá — não é erro da API.

---

## Recurso 4 — `?recurso=cotacoes`

As cotações. Inclui as três origens: nascidas do radar, nascidas de uma SC, e **criadas do zero**.

**Filtros:** `obra_id` · `obra` · `status` · `origem` · `criado_por` · `q`

| campo | o que é |
|---|---|
| `cotacao_id` | número da cotação |
| `titulo`, `apelido`, `descricao` | identificação |
| `obra_id`, `obra` | a obra |
| `origem` | `radar` · `solicitacao` · `zero` |
| `origem_texto` | a mesma coisa por extenso |
| `item_radar`, `item_id` | o item do radar, quando a origem é o radar |
| `status`, `status_texto`, `finalizada` | ver tabela abaixo |
| `criado_por`, `criado_em` | quem abriu e quando |
| `itens` | quantos itens estão sendo cotados |
| `fornecedores_convidados` / `_disparados` | convidados × e-mail efetivamente enviado |
| `propostas_recebidas` | quantos fornecedores já responderam com preço |
| `melhor_oferta` | menor total entre as propostas |
| `verba` | verba prevista, para comparar com a oferta |
| `num_solicitacao`, `num_pedido` | SC de origem e pedido de compra gerado |
| `detalhe_url` | o link do recurso 5 |

### Valores de `status` da cotação

| valor | `status_texto` |
|---|---|
| `aberta` | Em cotação (ainda sem proposta) |
| `aguardando` | Aguardando decisão (já tem proposta) |
| `finalizada` | Finalizada |

---

## Recurso 5 — `?recurso=cotacao&id=N`

Uma cotação **em detalhe**. Traz tudo do recurso 4 mais:

- `itens_detalhe[]` — cada item cotado, com o campo `melhor` (fornecedor e preço vencedor daquele item)
- `fornecedores[]` — por fornecedor: `convidado_em`, `disparado`, `disparado_em`, `respondeu`,
  `proposta_total`, `proposta_prazo`, `email_recebido_em`
- `propostas[]` — cada proposta com `total`, `prazo`, `recebida_em` e os `precos[]` por item
- `soma_dos_melhores` — quanto custaria comprando cada item de quem ofereceu o melhor preço
- `pedidos_de_compra[]` — nº do PC por empresa emissora

> O melhor preço por item é escolhido pelo **preço total** da linha. Item sem preço total
> preenchido não concorre.

---

## Detalhes que evitam retrabalho

1. **Use sempre o `obra_id` desta API.** Ele vem do cadastro mestre de obras. Os identificadores
   internos dos outros módulos não são expostos de propósito — eles se cruzam entre si e
   apontariam para a obra errada.

2. **`inicio_cotacao` e `fim_cotacao` são calculados**, não digitados. A conta é:
   `fim = data_em_obra − lead_dias` e `inicio = fim − 30 dias`. Como a `data_em_obra` acompanha
   o cronograma vivo do Planejamento, esses prazos **mudam sozinhos** quando a obra reprograma.
   Não guarde esses valores como se fossem fixos — releia.

3. **`verba_definida` e `verba_estimada` são coisas diferentes.** A definida é a que a equipe
   confirmou; a estimada é o palpite inicial do orçamento. Nunca some uma com a outra e não
   trate `verba_definida: null` como zero — é "ainda não definida".

4. **Item sem cronograma fica sem prazo.** Se a obra não tem cronograma publicado, o item vem
   com `data_em_obra: null` e `alerta: "ok"`. Isso significa *sem informação*, não *sem problema*.

5. **Cache.** O radar é recalculado a cada 30 minutos. Para consulta em tela isso é transparente;
   se precisar do valor do instante, acrescente `&recarregar=1` (mais lento — use com parcimônia).

6. **Primeira chamada do radar sem filtro de obra pode demorar.** Se a resposta vier com
   `"parcial": true`, o campo `obras_nao_processadas` diz quais ficaram de fora — basta chamar
   de novo, que aí já está em cache. Filtrar por obra evita isso.

7. **Acentuação.** As respostas são UTF-8. Filtros de texto (`obra`, `responsavel`, `q`) ignoram
   acento e maiúscula/minúscula — `?obra=jacarandas` acha "Jacarandás".

---

## Erros

| HTTP | quando |
|---|---|
| `401` | chave ausente, inválida ou revogada |
| `404` | recurso desconhecido, obra inexistente ou cotação inexistente |
| `503` | a API ainda não foi liberada (nenhuma chave criada) |
| `500` | erro inesperado — o campo `erro` traz a mensagem |

O corpo é sempre `{"ok": false, "erro": "mensagem em português"}`.

---

## Exemplos prontos

```bash
# 1) lista de obras
curl -H "X-API-Key: SUA_CHAVE" \
  ".../actions/api.php?recurso=obras"

# 2) o que está atrasado numa obra
curl -H "X-API-Key: SUA_CHAVE" \
  ".../actions/api.php?recurso=radar&obra=Diamond&alerta=atrasado"

# 3) itens de um comprador, em todas as obras
curl -H "X-API-Key: SUA_CHAVE" \
  ".../actions/api.php?recurso=radar&responsavel=Paloma%20Alonso"

# 4) procurar um item pelo nome
curl -H "X-API-Key: SUA_CHAVE" \
  ".../actions/api.php?recurso=radar&q=elevador"

# 5) solicitações ainda sem cotação
curl -H "X-API-Key: SUA_CHAVE" \
  ".../actions/api.php?recurso=solicitacoes&situacao=vazio"

# 6) cotações criadas do zero que ainda não fecharam
curl -H "X-API-Key: SUA_CHAVE" \
  ".../actions/api.php?recurso=cotacoes&origem=zero&status=aguardando"

# 7) uma cotação inteira
curl -H "X-API-Key: SUA_CHAVE" \
  ".../actions/api.php?recurso=cotacao&id=78"
```

---

**Contato:** Murilo Mello — Head de Suprimentos, Caprem.
Peça a chave de acesso a ele; cada sistema recebe a sua, e ela pode ser revogada a qualquer momento.
