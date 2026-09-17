# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Comunicação

**Sempre se comunique com o usuário em português do Brasil.** Toda explicação, resposta, mensagem de status, pergunta de esclarecimento e resumo de fim de turno deve ser em pt-BR. Isso vale mesmo quando o código, a documentação técnica ou as mensagens de erro estiverem em inglês — traduza/explique em português ao falar com o usuário. Mantenha identificadores de código, nomes de variáveis, comandos e termos técnicos consagrados (ex.: `business_id`, `migrate`, `middleware`) no original; o idioma se aplica ao texto que você escreve para o usuário, não ao código.

## What this is

Brazilian fiscal/ERP SaaS (deployed at `sistema.gacont.com.br`) built on Laravel 8.54 / PHP 7.3+|8.0+. The codebase is a heavily customized derivative of the UltimatePOS family, extended with full Brazilian fiscal document support (NFe, NFCe, CTe, MDFe, Devolução, Inutilização, Manifesto) via the `nfephp-org/sped-*` libraries, plus a built-in e-commerce, restaurant module, and contingency flows. It is multi-tenant: every business is identified by `business_id` and isolation is enforced query-by-query, not by middleware/scope.

## Common commands

```bash
composer install                                # PHP deps
npm install && npm run dev                      # JS/CSS via Laravel Mix (not Vite)
npm run watch                                   # rebuild on change
npm run prod                                    # minified prod assets

php artisan key:generate                        # required after first clone (.env must exist)
php artisan migrate                             # standard schema migrations
php artisan module:migrate                      # nwidart/laravel-modules migrations (per-module)
php artisan serve                               # local dev server; production runs Apache (see apache-config/, entrypoint.sh)

vendor/bin/phpunit                              # full suite
vendor/bin/phpunit --testsuite Unit             # only Unit (./tests/Unit)
vendor/bin/phpunit --testsuite Feature          # only Feature (./tests/Feature)
vendor/bin/phpunit --filter SomeTestName        # single test or method
```

There is no linter/formatter wired in (no Pint, PHP-CS-Fixer, or PHPStan config). `.styleci.yml` exists but no StyleCI run is configured locally.

## Architecture

### Routing & controller resolution

- `routes/web.php` (~820 lines) is the spine of the app. Most authenticated routes live inside one giant group:
  ```php
  Route::middleware(['authh', 'auth', 'SetSessionData', 'language', 'timezone',
                     'AdminSidebarMenu', 'CheckUserLogin', 'CheckPayment'])->group(...)
  ```
  When adding routes, place them inside this group unless they are explicitly public (payment callbacks, install, ecommerce storefront).
- `routes/install_r.php` is `include_once`'d from `web.php` and exposes the installer.
- Controllers are referenced as **strings** (`'CteController@importarXml'`), not FQNs. The default namespace `App\Http\Controllers` is set in `app/Providers/RouteServiceProvider.php`. Don't switch to `[Controller::class, 'method']` syntax in existing route files unless you also handle the namespace prefix.
- **Gotcha**: `authh` is **NOT** authentication. It maps to `App\Http\Middleware\IsInstalled` (see `app/Http/Kernel.php`). Real authentication is the standard `auth` middleware. Both are usually used together.

### Multi-tenancy

- `SetSessionData` (`app/Http/Middleware/SetSessionData.php`) populates `session('user')`, `session('business')`, `session('currency')`, and `session('financial_year')` on first request after login.
- Tenant scoping is **manual**, not via global scopes. The pervasive pattern is:
  ```php
  $business_id = request()->session()->get('user.business_id');
  Model::where('business_id', $business_id)->...
  ```
  When adding queries, you must scope by `business_id` explicitly. Cross-tenant data leaks are a real risk if you forget.

### Modules (nwidart/laravel-modules)

- `Modules/` contains: `Crm`, `Essentials`, `Manufacturing`, `ProductCatalogue`, `Repair`, `Superadmin`, `Woocommerce`. Each is a self-contained Laravel package with its own `composer.json`, routes, controllers, migrations, and views.
- Module classes are autoloaded under the `Modules\` PSR-4 namespace.
- Don't add module-specific code to `app/`; use the appropriate module directory.

### Brazilian fiscal layer

- `app/Services/` wraps the `nfephp-org` libraries: `NFeService.php`, `NFCeService.php`, `CTeService.php`, `MDFeService.php`, `DFeService.php`, `NFeEntradaService.php`, `DevolucaoService.php`. These are the SEFAZ integration points (issue, consult, cancel, etc.).
- Note: `app/Services/EnviarXmlController.php` is mis-named (it's a service, not a controller).
- XML files (issued NFes, CTes, MDFes) are persisted under `public/xml_*` and `public/extract/xml_nfe/`, namespaced by CNPJ.
- Brazilian field names are used verbatim from the SEFAZ schemas in code: `emit` (issuer), `dest` (recipient), `enderEmit`/`enderDest` (addresses), `cMun` (IBGE city code), `infNFe`/`infCte` (document body), `det` (line items), `vProd`/`vFrete` (totals), `Id` attribute on `infNFe`.
- **Convention for XML import**: when parsing uploaded fiscal XML with `simplexml_load_file`, always null-guard before chained property access. The repeating pattern from `PurchaseXmlController::verXml`, `DevolucaoController::verXml`, and `ImportXmlController::getCliente` is:
  ```php
  if (!$xml || !$xml->NFe || !$xml->NFe->infNFe) {
      return redirect()->back()->with('status', ['success' => 0, 'msg' => '...']);
  }
  ```
  PHP 8 turns missing-property access into a fatal error, and the uploaded XML may be unwrapped (`<NFe>` root), wrapped (`<nfeProc>` root), or a different document type entirely (CTe, MDFe). `CteController::importarXml` historically lacked this guard and is a known crash site.

### Domain helpers

- `app/Utils/` contains the heavy domain logic: `BusinessUtil`, `ContactUtil`, `ProductUtil`, `TransactionUtil`, `CashRegisterUtil`, `TaxUtil`, `ModuleUtil`, `RestaurantUtil`, `NotificationUtil`, `InstallUtil`, `Util`. Controllers commonly inject these via the constructor — when adding business logic, look here for an existing helper before writing new code in a controller.
- Two global helper files are autoloaded from `composer.json`:
  - `app/Http/helpers.php` — generic helpers (`humanFilesize`, `isFileImage`, etc.)
  - `app/Helpers/BoletoHelper.php` — boleto/payment-slip helpers

### Identifier language

- The codebase mixes English and Portuguese names freely: `BankController`/`CteController`, `City`/`Cidade`, `Brand`/`Marca`, `Sale`/`Devolucao`. When searching for a domain concept, try both languages. Routes follow the same pattern (`/cities` and `/naturezas` coexist).
- Comments and user-facing strings are predominantly Portuguese. Variable names within fiscal flows mirror the SEFAZ schema names directly (don't rename them — they map 1:1 to XML elements).

### Frontend

- Blade views under `resources/views/`, organized by domain (`cte/`, `nfe/`, `purchase/`, `import_xml/`, `ecommerce/`, etc.).
- Assets compiled with Laravel Mix (`webpack.mix.js`); the `package.json` has no Vite config — do not introduce one without coordination.
- The admin theme is the DASHMIN Bootstrap template (see `READ-ME.txt`); raw template HTML lives at the repo root (`*.html`) for reference but is not served.

## Notable gotchas

- `/business-migrate` is a public route that runs `php artisan migrate --force` over HTTP. Treat as legacy; do not rely on it and avoid extending it.
- `dompdf/dompdf` is pinned to `1.0` and `composer.json` declares `"minimum-stability": "dev"` with several `dev-master` Brazilian fiscal packages — `composer update` can move those targets unpredictably. Prefer `composer install` against the lockfile.
- The repo contains a committed `.env` and `bd.sql` dump at the root. Be careful not to expose them in PRs/issues.
- Production runs as `www-data` via Apache (`entrypoint.sh` does `chown -R www-data:www-data /var/www/html`). File-write code must produce paths writable by that user (typically under `storage/` or `public/`).
