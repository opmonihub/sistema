# Refatoração Anti-Log-Erros Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Eliminar as 4 classes de erro recorrentes nos logs 2026-09-17..22 (NFe xProd, CTe CEP, Blade null, rota NuvemShop).

**Architecture:** Centralizar sanitização fiscal em `App\Helpers\FiscalHelper` (onlyDigits/cep/truncate) e aplicar nos Services; corrigir origem (controllers) + defesa na view; remover bloco de rotas mortas.

**Tech Stack:** Laravel 8.54, PHP 7.3+/8.0+, nfephp-org/sped-*, Blade, phpunit.

**Spec:** Evidências em `storage/logs/laravel-2026-09-22.log:3` (xProd 122>120), `:678` (CEP 61762-470), `:98-100` (in_array NULL), `:288` (business on null), `:490,557` (NuvemShopAuthController). Investigações dos 4 agentes paralelos (2026-09-22).

## Global Constraints

- Escopar toda query nova por `business_id` (multi-tenancy manual, sem global scope).
- Não mudar sintaxe de rotas string `'Controller@method'` em `routes/web.php`.
- Usar `mb_substr` UTF-8 para campos SEFAZ (contagem é por caractere, não byte).
- Commits pequenos, um por Task.

---

### Task 1: Criar FiscalHelper central

**Files:**
- Create: `app/Helpers/FiscalHelper.php`
- Modify: `composer.json` (verificar autoload de `app/Helpers/*` — BoletoHelper já é autoloaded, seguir mesmo padrão)
- Test: `tests/Unit/FiscalHelperTest.php`

**Interfaces:**
- Consumes: nada (novo).
- Produces: `App\Helpers\FiscalHelper::onlyDigits(?string): string`, `::cep(?string): string`, `::truncate(?string,int): string` usadas pelas Tasks 2-3.

- [ ] **Step 1: Write the failing test**

```php
<?php
namespace Tests\Unit;
use PHPUnit\Framework\TestCase;
use App\Helpers\FiscalHelper;

class FiscalHelperTest extends TestCase {
    public function test_cep_strips_mask() {
        $this->assertEquals('61762470', FiscalHelper::cep('61762-470'));
        $this->assertEquals('61762470', FiscalHelper::cep('61.762-470'));
    }
    public function test_truncate_counts_chars_not_bytes() {
        $this->assertEquals('ação', FiscalHelper::truncate('açãoXYZ', 4));
        $this->assertEquals(120, mb_strlen(FiscalHelper::truncate(str_repeat('a', 122), 120)));
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/phpunit --filter FiscalHelperTest`
Expected: FAIL with "Class App\Helpers\FiscalHelper not found"

- [ ] **Step 3: Write minimal implementation**

```php
<?php
namespace App\Helpers;

class FiscalHelper {
    public static function onlyDigits(?string $v): string {
        return preg_replace('/\D/', '', (string) $v);
    }
    public static function cep(?string $v): string {
        return self::onlyDigits($v);
    }
    public static function truncate(?string $v, int $max): string {
        return mb_substr(trim((string) $v), 0, $max, 'UTF-8');
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `vendor/bin/phpunit --filter FiscalHelperTest`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add app/Helpers/FiscalHelper.php tests/Unit/FiscalHelperTest.php
git commit -m "feat(fiscal): adiciona FiscalHelper onlyDigits/cep/truncate"
```

### Task 2: Truncar xProd em 5 Services

**Files:**
- Modify: `app/Services/NFeService.php:299` (antes de `tagprod:414`), `app/Services/NFCeService.php:244,250-252`, `app/Services/NFCeService1.php:243,249-251`, `app/Services/NFeEntradaService.php:185`, `app/Services/DevolucaoService.php:223`
- Test: `tests/Unit/FiscalHelperTest.php` (reuse truncate)

**Interfaces:**
- Consumes: `FiscalHelper::truncate()` da Task 1.
- Produces: xProd sempre <=120 chars antes de `tagprod`.

- [ ] **Step 1: Write the failing test**

```php
public function test_xprod_long_name_truncates() {
    $name = str_repeat('a', 122);
    $this->assertEquals(120, mb_strlen(\App\Helpers\FiscalHelper::truncate($name, 120)));
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/phpunit --filter test_xprod_long_name_truncates`
Expected: FAIL (método ainda não usado nos Services — teste de contrato; se passar, seguir mesmo assim para aplicar nos Services)

- [ ] **Step 3: Write minimal implementation**

```php
// NFeService.php:299
$stdProd->xProd = \App\Helpers\FiscalHelper::truncate($i->product->name, 120);
// NFCeService.php:244 + após concat 250-252:
$stdProd->xProd = \App\Helpers\FiscalHelper::truncate($stdProd->xProd, 120);
// mesmo padrão em NFCeService1.php, NFeEntradaService.php:185, DevolucaoService.php:223 ($i->nome)
```

- [ ] **Step 4: Run test to verify it passes**

Run: `vendor/bin/phpunit --filter FiscalHelperTest`
Expected: PASS + `git diff --check` limpo

- [ ] **Step 5: Commit**

```bash
git add app/Services/NFeService.php app/Services/NFCeService.php app/Services/NFCeService1.php app/Services/NFeEntradaService.php app/Services/DevolucaoService.php
git commit -m "fix(fiscal): trunca xProd em 120 chars antes do tagprod"
```

### Task 3: Sanitizar CEP CTe + MDFe via helper

**Files:**
- Modify: `app/Services/CTeService.php:203,231-233,270-271,305-306,340-341,376-377`, `app/Services/MDFeService.php:123-125,481,488`
- Test: reuse `FiscalHelperTest::test_cep_strips_mask`

**Interfaces:**
- Consumes: `FiscalHelper::cep()` da Task 1.
- Produces: todo `->CEP` com 8 dígitos antes de `tagender*`.

- [ ] **Step 1: Write the failing test**

Já coberto em Task 1 (`cep('61.762-470')` falha com `str_replace`). Apenas rodar.

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/phpunit --filter test_cep_strips_mask`
Expected: PASS no helper (prova que helper é superior ao `str_replace` atual)

- [ ] **Step 3: Write minimal implementation**

```php
// CTeService.php — trocar todos:
$enderReme->CEP = \App\Helpers\FiscalHelper::cep($cteEmit->remetente->cep);
$enderDest->CEP = \App\Helpers\FiscalHelper::cep($cteEmit->destinatario->cep);
$enderExped->CEP = \App\Helpers\FiscalHelper::cep($cteEmit->expedidor->cep);
$enderReceb->CEP = \App\Helpers\FiscalHelper::cep($cteEmit->recebedor->cep);
$enderEmit->CEP = \App\Helpers\FiscalHelper::cep($config->cep);
// MDFeService.php:481,488 idem para cep_carrega/cep_descarrega
```

- [ ] **Step 4: Run test to verify it passes**

Run: `vendor/bin/phpunit --filter FiscalHelperTest && git diff --check`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add app/Services/CTeService.php app/Services/MDFeService.php
git commit -m "fix(fiscal): sanitiza CEP com onlyDigits em CTe e MDFe"
```

### Task 4: Guard permissions null + namespace Contact

**Files:**
- Modify: `app/Http/Controllers/DocumentAndNoteController.php:157-188`, `resources/views/documents_and_notes/index.blade.php:2`, `resources/views/contact/partials/documents_and_notes_tab.blade.php:2-4`
- Test: manual via `GET /get-document-note-page?notable_type=App\Contact&notable_id=1320`

**Interfaces:**
- Consumes: nada novo.
- Produces: `__getPermission` nunca retorna null.

- [ ] **Step 1: Write the failing test**

```php
// tests/Feature/DocNotePermissionTest.php
public function test_getPermission_unknown_type_returns_empty_array() {
    $c = new \App\Http\Controllers\DocumentAndNoteController();
    $m = new \ReflectionMethod($c, '__getPermission');
    $m->setAccessible(true);
    $this->assertEquals([], $m->invoke($c, 'App\Contact', 1));
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/phpunit --filter DocNotePermissionTest`
Expected: FAIL (retorna null hoje; método pode ser private — ajustar reflexão)

- [ ] **Step 3: Write minimal implementation**

```php
// DocumentAndNoteController.php::__getPermission — fim da função:
$map['App\Contact'] = $map['App\Models\Contact'] ?? null; // aceita legado
// ...
return $permissions ?? [];
// view index.blade.php:2:
@if(!empty($permissions) && in_array('create', $permissions))
```

- [ ] **Step 4: Run test to verify it passes**

Run: `vendor/bin/phpunit --filter DocNotePermissionTest`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/DocumentAndNoteController.php resources/views/documents_and_notes/index.blade.php
git commit -m "fix(docs): __getPermission nunca null, aceita App\\Contact legado"
```

### Task 5: Guard ledger contato null + escopo business

**Files:**
- Modify: `app/Http/Controllers/ContactController.php:1207-1233,1291`, `resources/views/contact/ledger.blade.php:6`
- Test: manual `GET /contacts/ledger?contact_id=999999`

**Interfaces:**
- Consumes: `business_id` da sessão.
- Produces: 404 em vez de 500 para contato inexistente/outro tenant.

- [ ] **Step 1: Write the failing test**

```php
// estender tests/Feature/DocNotePermissionTest.php ou novo LedgerTest
// assert GET /contacts/ledger?contact_id=999999 retorna 404 (hoje dá 500)
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/phpunit --filter LedgerTest`
Expected: FAIL (500 hoje)

- [ ] **Step 3: Write minimal implementation**

```php
// ContactController.php:1219 e :1291
$business_id = request()->session()->get('user.business_id');
$contact = Contact::where('business_id', $business_id)->find($contact_id);
if (empty($contact)) { abort(404); }
// ledger.blade.php:6
<strong>{{ optional($contact->business)->name }}</strong>
```

- [ ] **Step 4: Run test to verify it passes**

Run: `vendor/bin/phpunit --filter LedgerTest`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/ContactController.php resources/views/contact/ledger.blade.php
git commit -m "fix(contacts): ledger 404 escopado por business, view optional"
```

### Task 6: Remover bloco NuvemShop morto

**Files:**
- Modify: `routes/web.php:212-247` (deletar grupo `prefix => nuvemshop`)
- Test: `php artisan route:list | grep nuvemshop` vazio

**Interfaces:**
- Consumes: nada.
- Produces: `route:list` e `route:cache` voltam a funcionar.

- [ ] **Step 1: Write the failing test**

Run: `php artisan route:list 2>&1 | head -n 5`
Expected: FAIL com `BindingResolutionException NuvemShopAuthController`

- [ ] **Step 2: Run test to verify it fails**

Mesmo comando acima — confirma exceção.

- [ ] **Step 3: Write minimal implementation**

```php
// routes/web.php — deletar linhas 212-247 (Route::group prefix nuvemshop + 4 controllers)
// Se integração futura: criar Modules/NuvemShop espelhando Modules/Woocommerce, não controllers soltos.
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan route:list | grep -i nuvemshop; echo exit:$?`
Expected: vazio, exit 1 do grep (nenhum match) + sem exceção

- [ ] **Step 5: Commit**

```bash
git add routes/web.php
git commit -m "chore(routes): remove bloco nuvemshop morto (controllers inexistentes)"
```
