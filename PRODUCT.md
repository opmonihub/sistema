# Product

<!-- impeccable:product-schema 1 -->

## Platform

web

## Users

Usuário primário confirmado: Superadmin SaaS — dono da plataforma que gerencia tenants (business_id), assinaturas, módulos (Crm, Essentials, Manufacturing, Repair, Woocommerce) e permissões.

Público operado pelos tenants (não primário nesta rodada): donos/atendentes de PMEs brasileiras (varejo, restaurante, estoque) que emitem documentos fiscais e operam vendas/caixa.

## Product Purpose

Permitir operação fiscal e gerencial brasileira num ERP SaaS único: emitir NFe, NFCe, CTe, MDFe, Devolução, Inutilização e Manifesto via libs nfephp-org, com contingência, além de vendas, estoque, caixa, restaurante e e-commerce.

Sucesso = tenant emite e consulta documentos na SEFAZ sem sair do ERP, e superadmin mantém tenants isolados e cobrados.

## Positioning

Fiscal BR integrado ao ERP via `app/Services` (NFeService, NFCeService, CTeService, MDFeService, DFeService) — concorrente genérico derivado do UltimatePOS não entrega SEFAZ, contingência e XMLs por CNPJ em `public/xml_*` no mesmo fluxo de venda.

## Operating Context

Uso diário em balcão/caixa e backoffice: login → `SetSessionData` popula sessão (business, currency, financial_year) → operação escopada por `business_id` query a query. Produção em Apache como `www-data` (`entrypoint.sh`), escrita apenas sob `storage/` e `public/`. Rotas centrais em `routes/web.php` dentro do grupo `authh, auth, SetSessionData, language, timezone, AdminSidebarMenu`.

## Capabilities and Constraints

Confirmado: manter Laravel 8 Blade + template DASHMIN Bootstrap, Laravel Mix (sem Vite), namespace default `App\Http\Controllers` com rotas em string, multi-tenancy manual por `business_id`, nomes de campos SEFAZ verbatim (`emit`, `dest`, `infNFe`, `cMun`, `vProd`).

Fiscal: importação de XML com `simplexml_load_file` exige null-guard (`!$xml || !$xml->NFe || !$xml->NFe->infNFe`) — padrão de `PurchaseXmlController::verXml`.

Restrições: `composer install` a partir do lockfile (minimum-stability dev, fiscais em dev-master); sem linter local; `.env` e `bd.sql` commitados — não expor.

Indeciso: se lojista/operador deve virar co-primário em futura rodada.

## Brand Commitments

Nome operacional: sistema.gacont.com.br. Tema admin DASHMIN existente. Voz e strings predominantemente em pt-BR. Mistura EN/PT em domínios (`City/Cidade`, `Sale/Devolucao`) — buscar conceitos nas duas línguas.

## Evidence on Hand

Código: `app/Services/*`, `app/Utils/*`, `Modules/*`, `resources/views/cte,nfe,purchase,import_xml,ecommerce`. Sem depoimentos, cases ou assets de marketing — não fabricar prova social.

## Product Principles

1. Isolamento por tenant acima de conveniência — esquecer `business_id` é vazamento.
2. Fiscal primeiro: documento válido na SEFAZ vale mais que tela bonita.
3. Preservar convenções SEFAZ e rotas legadas ao evoluir UX.
4. Operação de balcão legível e rápida, marca em detalhes precisos.
