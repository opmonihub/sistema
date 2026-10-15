# cte-listagem Specification

## Purpose

Permitir a consulta dos documentos vinculados, destino do transporte, peso bruto e tomador na listagem de CT-e, com busca, ordenação e exportação consistentes, preservando isolamento entre empresas e sem alterar a persistência fiscal.

## Requirements

### Requirement: Listagem compatível e isolada por empresa
O sistema SHALL acrescentar Documentos, Cidade Destino, Peso KG e Tomador do Serviço em `/cte`, preservando as colunas, ações, permissões, responsividade e controle de visibilidade existentes. Resultados e contagens SHALL pertencer exclusivamente à empresa da sessão; contatos SHALL pertencer à mesma empresa e respeitar exclusão lógica. O sistema SHALL manter uma linha por CT-e e aplicar filtros e ordenação antes da paginação, sem carregar a base inteira para processá-la em memória.

#### Scenario: Contatos externos ou excluídos
- **WHEN** um CT-e referencia contato de outra empresa ou excluído logicamente
- **THEN** o nome desse contato não aparece na listagem, no modal, nas buscas ou nas exportações, e o tomador é tratado como contato ausente

#### Scenario: Paginação e desempate
- **WHEN** o usuário ordena e filtra uma base com mais registros que o tamanho da página
- **THEN** os critérios são aplicados globalmente antes da seleção da página, as contagens permanecem corretas e o ID do CT-e é o último desempate estável, sem prevalecer sobre a ordenação solicitada

### Requirement: Contrato consistente de documentos vinculados
O sistema SHALL apresentar NF-e da carga, outros documentos da carga e CT-e anteriores cadastrados. Número e série de NF-e e CT-e SHALL ser derivados apenas de chaves válidas da categoria, sem ler XML. Outros documentos SHALL mostrar tipo, número e descrição disponíveis; CT-e anteriores SHALL incluir emitente quando disponível. Delimitadores `;` e `,` e espaços ASCII SHALL ser normalizados nas chaves, entradas vazias ignoradas e referências deduplicadas por categoria e chave completa, nunca por número. Referências malformadas SHALL permanecer identificáveis, sem derivar número ou série. Contagem, busca, modal e exportação SHALL usar o mesmo conjunto de documentos. O tipo de outro documento sozinho SHALL ser insuficiente: exige número, descrição ou valor efetivamente informado, considerando zero numérico como padrão não informado.

#### Scenario: Deduplicação sem perder documentos distintos
- **WHEN** há delimitadores repetidos, espaços, duplicatas da mesma chave e chaves distintas com o mesmo número
- **THEN** vazios são ignorados, a mesma chave na mesma categoria conta uma vez e chaves distintas continuam separadas, inclusive após segmentos vazios além da décima posição

#### Scenario: Referência malformada
- **WHEN** um vínculo contém uma referência incompleta ou de modelo incompatível com a categoria
- **THEN** a referência continua visível, contável, pesquisável e exportável, identificada como inválida e sem número ou série inventados

#### Scenario: Tipo padrão sem conteúdo
- **WHEN** outro documento possui somente o tipo selecionado e nenhum número, descrição ou valor informado
- **THEN** ele não compõe a contagem nem cria um documento no modal ou na exportação

### Requirement: Resumo e modal reutilizável de documentos
O sistema SHALL mostrar `—` para nenhum vínculo, tipo e número para um documento identificado e um botão `N documentos` para vários documentos. Referências inválidas SHALL permanecer identificáveis mesmo no caso de documento único. O botão SHALL abrir um único modal reutilizável, categorizado em NF-e da carga, outros documentos e CT-e anteriores, com número, série, chave, descrição e emitente quando aplicáveis. Abrir o modal SHALL usar dados da linha atual sem nova requisição. Conteúdo cadastrado SHALL ser exibido como texto, sem executar HTML.

#### Scenario: Modal após redesenho
- **WHEN** o usuário pagina, busca ou reordena e abre os documentos de uma linha
- **THEN** o modal apresenta somente os documentos atuais dessa linha, sem consulta adicional e sem conteúdo residual da linha anterior

#### Scenario: Texto malicioso e navegação por teclado
- **WHEN** um documento contém HTML ou o usuário abre e fecha o modal por teclado
- **THEN** o conteúdo permanece texto literal, o modal possui nome acessível e o foco retorna ao botão de origem se ele continuar conectado e visível

### Requirement: Busca e ordenação dos documentos
O sistema SHALL buscar em qualquer documento vinculado por número, série, chave, tipo, descrição e emitente disponíveis, inclusive documentos que não sejam o primeiro. Busca textual SHALL ignorar diferenças de caixa e acento sem alterar a identidade estrita usada na deduplicação. Curingas e conteúdo semelhante a SQL SHALL ser tratados como texto de busca. A ordenação da coluna SHALL considerar a quantidade total de documentos.

#### Scenario: Documento não inicial e ordenação global
- **WHEN** o termo corresponde à segunda NF-e, a um CT-e anterior ou a outro documento da carga
- **THEN** o CT-e é encontrado antes da paginação e a ordenação por quantidade considera todas as categorias deduplicadas

#### Scenario: Busca literal e identidade
- **WHEN** a busca contém `%`, `_`, aspas ou variações de caixa e acento
- **THEN** não ocorre injeção nem expansão indevida de curingas, a busca humana é insensível a caixa/acento e referências diferentes não são fundidas por essa regra

### Requirement: Cidade de término do transporte
O sistema SHALL exibir nome e UF do município de término, buscar pelos dois e ordenar por nome seguido de UF. A ausência dessa relação SHALL resultar em `—`, sem usar a cidade do destinatário como substituição. Os valores SHALL refletir os cadastros atuais, não snapshots históricos.

#### Scenario: Destino distinto do destinatário
- **WHEN** o município de término difere da cidade do destinatário
- **THEN** a coluna, busca, ordenação e exportação usam o município de término

#### Scenario: Município ausente
- **WHEN** não existe município de término relacionado
- **THEN** a coluna mostra `—` e não inventa um destino

### Requirement: Peso bruto em quilogramas
O sistema SHALL considerar somente medidas de peso bruto com unidade fiscal `01` (KG) ou `02` (TON), convertendo TON por multiplicação decimal por 1.000. Volume, unidades e outros tipos de medida SHALL ser ignorados. Na ausência de medida de massa aplicável SHALL mostrar `—`; exatamente uma medida válida sem quantidade inválida concorrente SHALL mostrar kg em pt-BR preservando a precisão armazenada; várias medidas válidas SHALL mostrar `Múltiplas medidas`, sem somar ou escolher uma. Quantidades negativas ou nulas em medidas de massa aplicáveis SHALL ser consideradas inválidas e, quando não houver múltiplos válidos, SHALL mostrar `Medida inválida`, sem produzir um peso escolhido silenciosamente quando coexistirem com uma medida válida. Ausência e ambiguidade SHALL ficar ao final nas duas direções de ordenação, e busca numérica SHALL usar exclusivamente separadores brasileiros: ponto agrupa milhares e vírgula separa até quatro casas decimais. `1.234` SHALL pesquisar 1234 kg e `1,234` SHALL pesquisar 1.234 kg; agrupamentos inválidos SHALL ser rejeitados. O ponto decimal canônico SHALL ficar restrito aos dados internos e à serialização numérica do XLSX.

#### Scenario: Conversão precisa e busca
- **WHEN** existe uma única medida válida de `1.2345` TON
- **THEN** o peso é `1.234,5000` kg para precisão armazenada de quatro casas, é pesquisável com separadores normalizados e ordenado como número

#### Scenario: Medidas múltiplas ou incompatíveis
- **WHEN** existem dois pesos brutos válidos, ou existem somente medidas de volume/unidades
- **THEN** o primeiro caso mostra `Múltiplas medidas` sem soma e o segundo mostra `—`, ambos após pesos definidos em ordenação ascendente ou descendente

#### Scenario: Quantidade inválida ou código confundível
- **WHEN** uma quantidade é nula/negativa ou a unidade/tipo usa caracteres Unicode apenas visualmente semelhantes ao código fiscal
- **THEN** ela não gera massa válida nem conversão indevida, e uma combinação inconsistente não resulta na seleção arbitrária de um peso

### Requirement: Nome e papel do tomador do serviço
O sistema SHALL resolver os códigos 0–4 para remetente, expedidor, recebedor, destinatário e outros e mostrar nome e papel, buscar pelos dois e ordenar por nome seguido de papel. Contato ausente SHALL indicar o papel e a ausência de contato. Código ausente ou inválido SHALL ser identificado como inválido sem fallback para remetente. Nomes SHALL refletir cadastros atuais.

#### Scenario: Cinco papéis fiscais
- **WHEN** cada código válido de 0 a 4 é utilizado com contato válido da empresa
- **THEN** a coluna mostra o nome do participante correspondente e seu papel fiscal, ambos pesquisáveis e ordenáveis

#### Scenario: Ausência de contato ou código
- **WHEN** o contato do papel válido está ausente ou o código é inválido/nulo
- **THEN** o primeiro caso informa o papel e contato ausente e o segundo informa tomador inválido, sem assumir remetente

### Requirement: Exportações completas no alcance atual
O sistema SHALL exportar CSV, Excel, PDF e impressão com os documentos identificados e seus detalhes, em vez de apenas `N documentos`, preservando colunas visíveis, filtros, ordenação e alcance atual da página carregada. A personalização SHALL ficar restrita a esta tabela. Textos não SHALL executar HTML em impressão nem fórmulas em CSV/Excel; Excel SHALL preservar peso e valores monetários como números e aplicar formato pt-BR com agrupamento de milhares, quatro casas no peso e duas nos valores. Tela, CSV, PDF e impressão SHALL apresentar valores no padrão brasileiro, sem ampliar a mudança para outras telas. CSV SHALL usar ponto e vírgula para separar campos e UTF-8 com BOM para abertura no Excel brasileiro.

#### Scenario: Documentos e peso exportados
- **WHEN** o usuário exporta a página com vários documentos e um peso decimal
- **THEN** os documentos dessa página são identificados integralmente, o peso mantém o valor correto e nenhuma exportação de toda a base é iniciada

#### Scenario: Visibilidade e conteúdo não confiável
- **WHEN** uma coluna é ocultada ou os dados contêm HTML e prefixos de fórmula
- **THEN** a coluna oculta não é exportada, os textos ficam inertes e os padrões de exportação das demais telas permanecem inalterados

### Requirement: Leitura sem herança nem alteração fiscal
A funcionalidade SHALL usar dados cadastrados no próprio CT-e e suas relações, sem novas tabelas, campos ou migrations, sem alterar emissão SEFAZ e sem herdar automaticamente documentos ou peso do original. Dados copiados eventualmente cadastrados SHALL ser apresentados como vínculos de cadastro, sem afirmar equivalência com o XML autorizado.

#### Scenario: Complemento sem carga própria
- **WHEN** um complemento não possui documentos ou medidas próprios, mesmo que seu original os possua
- **THEN** a listagem não herda carga do original e as regras fiscais existentes de complemento e substituição permanecem inalteradas

### Requirement: Verificação isolada de compatibilidade e custo
A entrega SHALL ter testes sintéticos de regras, consultas e integração, com verificação de schema suportado, versão SQL, planos de execução, paginação, contagens, segurança e custo. A validação SHALL ocorrer sem dados reais, configuração de produção ou alterações na persistência implantada. Schema incompatível SHALL ser identificado sem truncar referências silenciosamente; custo inaceitável SHALL provocar revisão do desenho com o usuário, não criação automática de migration.

#### Scenario: Validação descartável
- **WHEN** são executadas as verificações de consulta e desempenho
- **THEN** usam schema e fixtures sintéticos em banco descartável isolado, documentam versão, planos e tempos, e não acessam dados ou XML de clientes
