# Design — Importação automática de XML na devolução (autofill total)

- **Data:** 2026-09-17
- **Status:** aprovado pelo dono (4 seções, chat)
- **Ponto seguro:** branch/tag `pre-devolucao-20260917` (= `5971623`)
- **Base:** 2 edições parciais não commitadas, mantidas por decisão do dono:
  `app/Models/Devolucao.php` (`getTrib()` + `cfopDevolucao()`) e
  `app/Http/Controllers/DevolucaoController.php` (item do `verXml`)

## 1. Contexto

XML de referência: `NFE-35260730262709000126550010000611701005840276`
(venda `VTZZ/SP CRT 3` → `JAIRO NIELES/MA`, `CFOP 6101`, `idDest 2`,
19 itens `ICMS00 orig 0 modBC 3 pICMS 7%`, IPI `CST 50 6.50/9.75`,
PIS/COFINS `CST 01` com base menor que a do ICMS).

Emitente da devolução: **JAIRO/MA, Simples Nacional (CRT 1)**,
devolvendo para VTZZ/SP. Decisão decorrente: `CST 00` da origem
**não pode** sair na devolução — vira **`CSOSN 900`** copiando
`orig/modBC/vBC/pICMS/vICMS` (lib suporta: `Make.php:4949-5003`).

Abordagem do IPI: **B — espelho puro** (recomendada e aprovada).
Item com `IPITrib` igual à origem, sem `tagimpostoDevol`,
`vIPIDevol = 0`. Resultado: `vNF` idêntico ao da origem (`12256.41`).
A abordagem A (`impostoDevol`, `vNF = origem + IPI`) foi descartada
por agora; vira flag futura se o contador exigir.

## 2. Seção 1 — Leitura (aprovada)

- `Devolucao::getTrib()` lê ICMS/PIS/COFINS/IPI por `children()`
  com fallback para cast; extrai `modBC`, `vIPI`, `cEnq`;
  corrige `pCOFINS` e o parse do `IPITrib` (o antigo devolvia
  `cst 99 / pIPI 0` para este XML).
- `Devolucao::cfopDevolucao()` mapeia saída→devolução
  (`6101→1202`, regra `5xxx→1201` / `6xxx→1202`).
- `verXml` captura `CEST`, `vUnTrib`, `modBC`, `vIPI`;
  sugere `CFOP 1202`; defaulta `tipo 0` (entrada), `finNFe 4`,
  `refNFe` = chave de origem; pré-seleciona natureza de devolução
  quando existir (naturezas são dado do tenant — existência não
  confirmável por código; fallback: aviso na tela, sem falhar).
- Conversão SN já na importação: emitente `CRT 1` + item `CST` →
  sugere `CSOSN 900` preservando valores.

## 3. Seção 2 — Transporte (aprovada)

- `view_xml.blade.php`: 4 `hidden` por item
  (`modBC`, `cest`, `vUnTrib`, `vIPI`); `CFOP` sugerido visível
  com origem como referência; `CSOSN 900` pré-selecionado (editável).
- Migration nova em `item_devolucaos` (sem tocar antigas):
  `modBC int default 3`, `cest varchar(7) default ''`,
  `vUnTrib decimal(10,4) default 0`, `vIPI decimal(12,2) default 0`.
- `ItemDevolucao::$fillable` += 4 campos.
- `store()` e `update()`: salvam os 4 campos; corrigem swaps
  existentes (`uf_cons <= uf_cons`, `perc_glp <= perc_glp`).

## 4. Seção 3 — Geração (aprovada)

- ICMS: `modBC` do item (fallback `3`); copia `vICMS` importado
  quando `|vBC×pICMS − vICMS| ≤ 0.05`, recalcula só se vazio;
  SN → `ICMSSN900`, normal → `ICMS00`; tags ST só para
  `CST 10/30/60/70`.
- Produto: emite `CEST` quando presente; usa `vUnTrib/qTrib`.
- IPI/PIS/COFINS: `IPITrib` espelho; totais por soma dos itens
  (remover os `0` explícitos que anulam o auto-total da lib);
  `vIPIDevol = 0`; sem `tagimpostoDevol`.
- `vNF = valor_devolvido + frete + seguro + outro + somaIPI − desconto`.
- Regime pelo `CRT` do emitente (`Business`), não pelo 1º item.

## 5. Seção 4 — Validação e rollout (aprovada)

- Sem PHP no container: script Python compara tela×origem
  (item 1: `691.20/48.38/44.93/10.61/48.85`;
  totais `11310.96/791.76/945.45/12256.41`).
- Com PHP: `php -l` + import real conferindo `1202`/`900`.
- `php artisan migrate` (só adiciona colunas); commits por fase
  sobre o ponto seguro; homologação SEFAZ antes de produção.

## 6. Fora do escopo

- Flag futura para abordagem A (`impostoDevol`).
- Devolução parcial (`pDevol` proporcional) — só total (`100%`).
- `NFCeService1.php` duplicado; reforma do fluxo de pagamento.
