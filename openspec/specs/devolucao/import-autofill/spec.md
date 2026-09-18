# devolucao/import-autofill

## Purpose

Autofill total da devolução a partir do XML de origem para eliminar digitação fiscal e emitir NFe espelho aprovada na SEFAZ.

## Requirements

### Requirement: Importação extrai tributos da origem
The system SHALL preencher cada item importado do XML com CST/CSOSN, alíquotas e bases de ICMS, IPI, PIS e COFINS, incluindo `modBC`, `vIPI` e `cEnq`, sem exigir digitação.

#### Scenario: XML de referência com 19 itens
- **WHEN** o usuário importa o XML `NFE-35260730262709000126550010000611701005840276`
- **THEN** o sistema apresenta 19 itens com ICMS `CST 00 modBC 3 pICMS 7.0000`, IPI `CST 50`, PIS/COFINS `CST 01`, e `vBC` do ICMS igual ao `vProd` do item

#### Scenario: Base de PIS distinta da base de ICMS
- **WHEN** um item possui base de PIS diferente da base de ICMS no XML
- **THEN** o sistema preserva as duas bases separadamente (`vbcPis`/`vbcCofins` distintas de `vBC`)

### Requirement: Mapeamento de CFOP de devolução
The system SHALL sugerir CFOP de devolução (entrada) correspondente ao CFOP de saída da origem.

#### Scenario: Venda fora do estado vira devolução fora do estado
- **WHEN** o item de origem tem CFOP `6101`
- **THEN** o sistema sugere CFOP `1202`

#### Scenario: Regra geral por prefixo
- **WHEN** o CFOP de origem é `5xxx` sem mapa explícito
- **THEN** o sistema sugere `1201`
- **WHEN** o CFOP de origem é `6xxx` sem mapa explícito
- **THEN** o sistema sugere `1202`

#### Scenario: CFOP fora do padrão passa intacto
- **WHEN** o CFOP de origem não começa com `5` nem `6` (ex. `1101`)
- **THEN** o sistema mantém o CFOP original sem alterar

### Requirement: Tela de importação transporta campos fiscais até a persistência
The system SHALL transportar `modBC`, `cest`, `vUnTrib` e `vIPI` da tela de conferência (`view_xml`) até o item salvo, em `store` e `update`, e SHALL salvar `uf_cons` e `perc_glp` sem troca com campos vizinhos.

#### Scenario: Conferência exibe valores importados
- **WHEN** o usuário abre a conferência após importar o XML
- **THEN** cada linha exibe `modBC`, `cest`, `vUnTrib` e `vIPI` vindos da origem e o POST contém os arrays `modBC[]`, `cest[]`, `vUnTrib[]`, `vIPI[]`

#### Scenario: Salvamento persiste os quatro campos
- **WHEN** o usuário confirma a devolução
- **THEN** cada `ItemDevolucao` salvo contém `modBC` (default `3` quando ausente), `cest`, `vUnTrib` e `vIPI` correspondentes à linha conferida

#### Scenario: Campos ANP/GLP sem swap
- **WHEN** o item possui `uf_cons` e percentuais `perc_glp`/`perc_gnn`/`perc_gni`
- **THEN** o sistema salva `uf_cons` a partir de `uf_cons` e `perc_glp` a partir de `perc_glp` (sem cruzamento)

### Requirement: Remoção de linha mantém alinhamento dos arrays
The system SHALL, ao remover uma linha na conferência, remover também os campos ocultos daquele item para que os arrays do POST permaneçam alinhados.

#### Scenario: Remover item antes de salvar
- **WHEN** o usuário remove a linha de um item na tela de conferência
- **THEN** nenhum campo oculto daquele item é enviado no POST e os demais itens salvam na ordem exibida

### Requirement: Emissão ICMS espelha a origem por regime do emitente
The system SHALL definir o regime pelo CRT do emitente e emitir ICMS como espelho dos valores importados: emitente Simples Nacional (CRT 1) emite `CSOSN 900` com `orig`/`modBC`/`vBC`/`pICMS`/`vICMS` da origem; emitente normal (CRT 3) emite o CST de origem; tags de ST só aparecem fora de `00`/`900`.

#### Scenario: Emitente SN converte CST 00 em CSOSN 900
- **WHEN** o emitente é CRT 1 e o item importado tem `CST 00` com `vBC` e `vICMS`
- **THEN** a NFe gerada contém `ICMSSN900` com `CSOSN 900`, `modBC` do item (fallback `3`) e `vBC`/`vICMS` copiados da origem

#### Scenario: vICMS copiado sob tolerância
- **WHEN** `|vBC×pICMS − vICMS_importado| ≤ 0.05` e `vICMS_importado > 0`
- **THEN** o sistema emite o `vICMS` importado; caso contrário recalcula `vBC×pICMS`

#### Scenario: ST suprimida em devolução de item tributado integral
- **WHEN** o item tem `CST 00` ou `900` e a UF de destino difere da UF do emitente
- **THEN** a NFe não contém tags de substituição tributária para aquele item

### Requirement: Identidade fiscal do produto
The system SHALL emitir `CEST` quando o item importado possuir CEST e SHALL usar `vUnTrib` importado com fallback para o valor unitário.

#### Scenario: Item com CEST emite tag CEST
- **WHEN** o item salvo possui `cest` não vazio
- **THEN** a NFe gerada contém a tag `CEST` daquele item com o mesmo código

#### Scenario: vUnTrib com fallback
- **WHEN** o item possui `vUnTrib > 0`
- **THEN** o sistema emite esse valor; quando `vUnTrib` é zero ou ausente o sistema emite `valor_unit`

### Requirement: Totais por soma com vNF igual à origem
The system SHALL totalizar `vBC`, `vICMS`, `vPIS`, `vCOFINS` e `vIPI` pela soma dos itens (abordagem B), com `vIPIDevol 0` e sem bloco `impostoDevol`, de modo que a devolução total tenha `vNF` idêntico ao da origem.

#### Scenario: XML de referência totaliza igual à origem
- **WHEN** a devolução total do XML de referência é gerada sem frete/seguro/outro/desconto
- **THEN** os totais são `vBC 11310.96`, `vICMS 791.76`, `vPIS 173.53`, `vCOFINS 799.44`, `vIPI 945.45`, `vIPIDevol 0.00` e `vNF 12256.41`

#### Scenario: Primeiro item confere com a origem
- **WHEN** a conferência exibe o item 1 antes de salvar
- **THEN** os valores são `vBC 691,20`, `vICMS 48,38`, `vIPI 44,93`, `vPIS 10,61` e `vCOFINS 48,85`
