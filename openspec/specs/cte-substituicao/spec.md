# cte-substituicao

## Purpose

Permitir a emissão de CT-e Substituto para corrigir um CT-e autorizado, referenciando o documento original conforme o leiaute CT-e 4.00.

## Requirements

### Requirement: Emissão de CT-e Substituto com vínculo ao original
O sistema SHALL permitir criar um CT-e do tipo Substituto vinculado a um CT-e original autorizado do mesmo emitente, selecionando o original na lista (o sistema preenche a chave de 44 dígitos) ou colando manualmente a chave de acesso de 44 dígitos do original, e indicando alteração de tomador (derivada automaticamente quando o original está na base; via caixinha somente quando a chave for manual).

#### Scenario: Criar substituto escolhendo original na lista
- **WHEN** o usuário seleciona tipo Substituto e escolhe o CT-e original na lista (número/cliente/data)
- **THEN** o sistema preenche a chave e todos os campos do formulário com os dados do original (partes, valores, carga, medidas, componentes, NF-es, documentos anteriores), permitindo edição interativa antes de salvar, e vincula os dois documentos

#### Scenario: Criar substituto com chave de 44 dígitos
- **WHEN** o usuário informa manualmente uma chave de CT-e original autorizado
- **THEN** o sistema gera e transmite o CT-e com finalidade Substituto referenciando o original

#### Scenario: Criar substituto com troca de tomador (original na base)
- **WHEN** o substituto informa tomador diferente do original vinculado na base (tipo ou CNPJ/CPF do pagador)
- **THEN** o sistema registra `indAlteraToma=1` no documento substituto sem perguntar ao usuário, e omite o flag quando o tomador é o mesmo

#### Scenario: Criar substituto com troca de tomador (chave manual)
- **WHEN** o original é chave manual de 44 dígitos (fora da base, sem registro para comparar)
- **THEN** o sistema exibe a caixinha "Alteração de tomador" e registra o indicador conforme marcado (padrão: não marcado, flag omitido)
