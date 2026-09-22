---
name: GACONT Sistema
description: ERP fiscal brasileiro denso e confiável para balcão e backoffice
colors:
  primary: "#6777EF"
  primary-deep: "#357ca5"
  success: "#66bb6a"
  info: "#3abaf4"
  warning: "#ffa426"
  danger: "#fc544b"
  text: "#757575"
  dark: "#5a5c69"
  light-border: "#e3eaef"
  bg-soft: "#f8f8f8"
  table-odd: "#DCE6F1"
typography:
  display:
    fontFamily: "Nunito, -apple-system, BlinkMacSystemFont, Segoe UI, Roboto, Helvetica Neue, Arial, sans-serif"
    fontWeight: 800
    lineHeight: 1.2
  headline:
    fontFamily: "Nunito, -apple-system, BlinkMacSystemFont, Segoe UI, Roboto, Helvetica Neue, Arial, sans-serif"
    fontWeight: 700
    lineHeight: 1.3
  title:
    fontFamily: "Nunito, -apple-system, BlinkMacSystemFont, Segoe UI, Roboto, Helvetica Neue, Arial, sans-serif"
    fontWeight: 700
    lineHeight: 1.35
  body:
    fontFamily: "Nunito, -apple-system, BlinkMacSystemFont, Segoe UI, Roboto, Helvetica Neue, Arial, sans-serif"
    fontSize: "1rem"
    fontWeight: 400
    lineHeight: 1.5
  label:
    fontFamily: "Nunito, -apple-system, BlinkMacSystemFont, Segoe UI, Roboto, Helvetica Neue, Arial, sans-serif"
    fontWeight: 700
    fontSize: "0.875rem"
rounded:
  sm: "3px"
  md: "4px"
  lg: "8px"
spacing:
  sm: "8px"
  md: "16px"
  lg: "24px"
components:
  button-primary:
    backgroundColor: "{colors.primary}"
    textColor: "#ffffff"
    rounded: "{rounded.sm}"
    padding: "8px 16px"
  button-primary-hover:
    backgroundColor: "{colors.primary-deep}"
    textColor: "#ffffff"
    rounded: "{rounded.sm}"
    padding: "8px 16px"
  input-default:
    backgroundColor: "#ffffff"
    textColor: "{colors.text}"
    rounded: "{rounded.sm}"
    padding: "8px 12px"
  card-default:
    backgroundColor: "#ffffff"
    textColor: "{colors.text}"
    rounded: "{rounded.md}"
    padding: "16px"
---

# Design System: GACONT Sistema

## Overview

**Creative North Star: "Balcão Confiável"**

Sistema denso e funcional herdado de UltimatePOS + AdminLTE, adaptado ao fiscal brasileiro. Personalidade técnica, direta, sem ornamento: tabelas, filtros e ações fiscais dominam. Densidade alta para caber itens, impostos e XMLs por tela, com leitura rápida no caixa.

Anti-referência confirmada: nada de ruptura moderna/ousada com o AdminLTE; nada de pills, gradientes ou vidro.

**Key Characteristics:**
- Denso e funcional, não minimalista
- Plano por padrão, borda e tom separam
- Ação primária sempre índigo, resto neutro
- Nunito em tudo, pesos definem hierarquia

## Colors

Paleta operacional fria: um índigo de ação sobre neutros cinzentos, com semáforo fiscal completo.

### Primary
- **Azul Fiscal** (#6777EF): ação principal em toda tela — salvar, filtrar, emitir. Botões `btn-primary`, links ativos.
- **Azul Petróleo Fiscal** (#357ca5): cabeçalhos de relatório e tabelas PDF (`.blue-heading`, `.table-pdf thead`).

### Secondary (optional; omit if the project has only one accent)

### Tertiary (optional)

### Neutral
- **Texto Operacional** (#757575): corpo, labels secundários, sidebar.
- **Grafite** (#5a5c69): títulos, header dark, texto forte.
- **Borda Clara** (#e3eaef): bordas de card, divisores, inputs.
- **Fundo Suave** (#f8f8f8): faixas, fundos alternados (`.bg-light-gray`).
- **Linha Fiscal** (#DCE6F1): zebra de tabela PDF (`.table-pdf .odd`).
- **Sucesso** (#66bb6a), **Info** (#3abaf4), **Alerta** (#ffa426), **Perigo** (#fc544b): status SEFAZ, estoque e validações.

### Named Rules (optional, powerful)
**The Acento Raro Rule.** Primário em ≤1 ação dominante por bloco; tabelas e texto permanecem neutros. Raridade é o sinal.

## Typography

**Display Font:** Nunito (com -apple-system, Segoe UI, Roboto, Arial fallback)
**Body Font:** Nunito (mesma pilha)
**Label/Mono Font:** Pilha mono do Bootstrap (SFMono-Regular, Menlo, Consolas) para códigos, XML, chaves.

**Character:** Geométrica arredondada mas técnica; peso cria hierarquia, não tamanho.

### Hierarchy
- **Display** (800, 1.2): títulos de módulo e totais do PDV.
- **Headline** (700, 1.3): cabeçalhos de card e seção.
- **Title** (700, 1.35): `.catalogue-title` (18px), `.card-title`.
- **Body** (400, 1rem/1.5): tabelas densas (12px em `.product-info-table`), formulários fiscais.
- **Label** (700, 0.875rem): filtros, badges (`.discount-badge` 18px em destaque, small 12px).

### Named Rules (optional)
**The Peso Antes de Tamanho Rule.** Diferencie com 700/800 antes de aumentar px; densidade manda.

## Layout

Modelo AdminLTE clássico: `wrapper > main-header + sidebar + content-wrapper + footer`. Sidebar colapsável com persistência (`upos_sidebar_collapse`), modo POS em `lockscreen` sem sidebar (`header-pos`). Breakpoints 576/768/992/1200px; mobile empilha navbar e tabelas rolam horizontal. Ritmo 4/8/16/24px; utilitários `.mb-10`, `.p-5-5`, `.m-4` para ajuste fino. Densidade alta, container fluido.

## Elevation & Depth

Sistema plano por padrão. Sem sombras no repouso; profundidade por borda (`#e3eaef`) e faixas tonais (`#f8f8f8`, `#DCE6F1`). Sombra só como resposta a estado: hover de botão, foco de input, modal `view_modal`.

### Shadow Vocabulary (if applicable)

### Named Rules (optional)
**The Flat-By-Default Rule.** Superfícies planas no repouso. Sombra só em hover, foco ou modal.

## Shapes

Linguagem reta e pequena: `btn-flat` zera canto em ações fiscais; cards 4px; nada acima de 8px. Bordas 1px sólidas claras; sem clipes ou silhuetas. Badges de desconto absolutos e retangulares.

## Components

### Buttons
- **Shape:** reto a levemente curvo (3px).
- **Primary:** fundo Azul Fiscal (#6777EF), texto branco, padding 8px 16px.
- **Hover / Focus:** escurece para Azul Petróleo (#357ca5), sem elevação.
- **Secondary / Ghost / Tertiary (if applicable):** `btn-block` para filtros; `btn-lg` para Imprimir/Visualizar DANFE; `btn-sm/xs` em tabelas; `btn-flat` no fiscal.

### Chips (if used)

### Cards / Containers
- **Corner Style:** levemente curvo (4px).
- **Background:** branco sobre fundo suave.
- **Shadow Strategy:** plano; ver Elevation.
- **Border:** 1px `#e3eaef`.
- **Internal Padding:** 16px (`.card-body`).

### Inputs / Fields
- **Style:** fundo branco, borda `#e3eaef`, raio 3px, texto `#757575`.
- **Focus:** borda primária, sem glow.
- **Error / Disabled:** vermelho Perigo (#fc544b) para erro; cinza para disabled.

### Navigation
Sidebar AdminLTE `sidebar-mini`, texto 14-15px Nunito 400/700, ativo em índigo, hover cinza. Header `main-header` com `navbar-custom-menu`; mobile empilha. POS usa `header-pos` minimalista.

### [Signature Component] (optional; if the project has a distinctive custom component worth documenting)
Tabela fiscal densa: header Azul Petróleo com texto branco, zebra `#DCE6F1`, 12px, ações em botões xs primários. Filtros em linha com botão `btn-block btn-primary`.

## Do's and Don'ts

Concrete visual guardrails grounded in the incumbent implementation or the user's chosen world. Lead each with "Do" or "Don't" and include exact values only when established. Do not turn a task-specific concept or surface strategy into a system-wide prohibition.

### Do:
- **Do** usar primário (#6777EF) só na ação principal por bloco.
- **Do** manter tabelas densas 12-14px com zebra quando PDF.
- **Do** preservar termos SEFAZ verbatim com estilo neutro.

### Don't:
- **Don't** introduzir pills, gradientes, glass ou raio >8px.
- **Don't** recolorir status fiscal (verde/aprovado, vermelho/rejeitado, amarelo/contingência).
- **Don't** trocar Nunito sem aprovação; peso, não família, cria hierarquia.
