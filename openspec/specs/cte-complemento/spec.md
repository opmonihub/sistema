# cte-complemento

## Purpose

Permitir a emissão de CT-e Complemento de valores para complementar ICMS ou valores de um CT-e autorizado, referenciando 1 documento complementado conforme o leiaute CT-e 4.00.

## Requirements

### Requirement: Emissão de CT-e Complemento de valores
O sistema SHALL permitir criar um CT-e do tipo Complemento informando 1 chave de CT-e complementado autorizado do mesmo emitente — selecionando o original na lista (o sistema preenche a chave) ou colando manualmente a chave de 44 dígitos — contendo apenas os valores/ICMS complementares.

#### Scenario: Criar complemento de um CT-e
- **WHEN** o usuário seleciona tipo Complemento, escolhe o CT-e na lista ou cola uma chave válida de 44 dígitos de CT-e autorizado do mesmo CNPJ e informa os valores complementares
- **THEN** o sistema gera e transmite o CT-e com finalidade Complemento referenciando o complementado

#### Scenario: Chave inválida
- **WHEN** o usuário informa chave fora do padrão de 44 dígitos, ou chave de outro emitente/não autorizada
- **THEN** o sistema rejeita a operação com mensagem orientando a correção antes da transmissão à SEFAZ

### Requirement: Complemento sem dados de transporte
O sistema SHALL exigir no Complemento apenas emitente, tomador/partes conforme regra fiscal, valores e ICMS complementares e as chaves referenciadas, sem exigir carga, documentos de NF-e, modal ou documentos anteriores.

#### Scenario: Formulário simplificado de complemento
- **WHEN** o usuário escolhe tipo Complemento
- **THEN** o sistema oculta ou desobriga os campos de carga, NF-e/outros, modal e documentos anteriores, mantendo-os obrigatórios apenas no Normal/Substituto

### Requirement: Exibição do vínculo de complemento
O sistema SHALL exibir no detalhe do CT-e complementar a chave complementada e permitir navegar até o documento de origem quando existir na base.

#### Scenario: Consulta de complemento
- **WHEN** o usuário abre o detalhe de um CT-e complementar autorizado
- **THEN** o sistema mostra a chave complementada com o estado do documento de origem
