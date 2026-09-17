# Devolucao Import Autofill Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Importar o XML `NFE-35260730262709000126550010000611701005840276` na devolução e sair com todos os itens preenchidos (ICMS, IPI, PIS/COFINS, CEST, CFOP 1202, CSOSN 900) sem digitação.

**Architecture:** Espelho da origem com conversão SN (CST→CSOSN 900 quando emitente CRT 1) e abordagem B no IPI (IPITrib espelho, sem impostoDevol, vNF igual à origem). Novos campos via migration aditiva; sem alterar tabelas ou rotas existentes.

**Tech Stack:** Laravel 8, Blade, MySQL, nfephp-org/sped-nfe (Make: tagICMS, tagICMSSN CSOSN 900, tagIPI, tagICMSTot, tagprod com CEST).

**Spec:** `docs/superpowers/specs/2026-09-17-devolucao-import-autofill-design.md`

## Global Constraints

- Não expor segredos em PR/issue; nunca commitar `.env`, `bd.sql`, XMLs fiscais.
- Rotas continuam com sintaxe string + namespace `App\Http\Controllers`.
- Não renomear campos SEFAZ (`emit/dest/cMun/infNFe/det/vProd`).
- Migrations: só criar novas, nunca editar as aplicadas.
- `vNF` final tem que ser `12256.41` (igual à origem) na devolução total.
- Sem binário PHP neste container: verificação executável via `python3`; `php -l` só em ambiente com PHP.

---

### Task 1: Travar getTrib + cfopDevolucao contra o XML real

**Files:**
- Modify: `app/Models/Devolucao.php:88-260` (método `getTrib` + `cfopDevolucao` — já editados, travar com verificação)
- Test: `/tmp/kilo/check_gettrib.py` (script throwaway de verificação, não commitar)

**Interfaces:**
- Consumes: `NFE-35260730262709000126550010000611701005840276.xml` (raiz do repo, não commitar)
- Produces: `getTrib($itemImposto)` retorna array com chaves `cst_csosn, pICMS, cst_pis, pPIS, cst_cofins, pCOFINS, cst_ipi, pIPI, pRedBC, modBC, vBCSTRet, vBC, vICMS, modBCST, vBCST, pICMSST, vICMSST, pMVAST, pST, vICMSSubstituto, vICMSSTRet, orig, vbcPis, vbcCofins, vbcIpi, vIPI, cEnq`; `cfopDevolucao('6101') === '1202'`

- [ ] **Step 1: Escrever o script de verificação**

```python
# /tmp/kilo/check_gettrib.py
import xml.etree.ElementTree as ET
NS = {'n': 'http://www.portalfiscal.inf.br/nfe'}
t = ET.parse('/home/sistema/NFE-35260730262709000126550010000611701005840276.xml')
dets = t.getroot().find('n:NFe/n:infNFe', NS).findall('n:det', NS)
assert len(dets) == 19, f'esperava 19 itens, achou {len(dets)}'
ok = True
for d in dets:
    imp = d.find('n:imposto', NS)
    icms = list(imp.find('n:ICMS', NS))[0]
    ipi = imp.find('n:IPI/n:IPITrib', NS)
    pis = imp.find('n:PIS/n:PISAliq', NS)
    cof = imp.find('n:COFINS/n:COFINSAliq', NS)
    def g(el, tag):
        n = el.find(f'n:{tag}', NS)
        return n.text if n is not None else None
    checks = [
        (g(icms, 'CST') == '00', 'CST ICMS 00'),
        (g(icms, 'modBC') == '3', 'modBC 3'),
        (g(icms, 'pICMS') == '7.0000', 'pICMS 7'),
        (g(icms, 'vBC') == g(d.find('n:prod', NS), 'vProd'), 'vBC==vProd'),
        (ipi is not None and g(ipi, 'CST') == '50', 'IPI CST 50'),
        (pis is not None and g(pis, 'CST') == '01', 'PIS 01'),
        (cof is not None and g(cof, 'CST') == '01', 'COFINS 01'),
        (float(g(icms, 'vBC')) != float(g(pis, 'vBC')), 'base ICMS != base PIS'),
    ]
    for passed, label in checks:
        if not passed:
            ok = False
            print('FALHOU item', d.find('n:prod/n:cProd', NS).text, label)
print('getTrib: premissas do XML', 'OK' if ok else 'COM DIVERGENCIA')
```

- [ ] **Step 2: Rodar e conferir**

Run: `python3 /tmp/kilo/check_gettrib.py`
Expected: `getTrib: premissas do XML OK`

- [ ] **Step 3: Revisar o método contra as premissas**

Abrir `app/Models/Devolucao.php:88-260` e conferir linha a linha:
`children()` no grupo ICMS/PIS/COFINS com fallback de cast;
IPI via `IPI->IPITrib` (nunca por índice de array);
`modBC` default `3`; retorno inclui `vIPI` e `cEnq`;
`cfopDevolucao('6101')` retorna `'1202'`.
Nenhuma edição se já conforme; se divergir, ajustar o mínimo.

- [ ] **Step 4: Commit**

```bash
git add app/Models/Devolucao.php
git commit -m "Devolucao: getTrib robusto (modBC, IPI, pCOFINS) + mapa CFOP devolucao"
```

### Task 2: Migration + fillable dos 4 campos novos

**Files:**
- Create: `database/migrations/2026_09_17_000001_add_devolucao_import_fields_to_item_devolucaos_table.php`
- Modify: `app/Models/ItemDevolucao.php:9-16`

**Interfaces:**
- Consumes: nada (migration independente)
- Produces: colunas `modBC`, `cest`, `vUnTrib`, `vIPI` em `item_devolucaos`; fillable atualizado

- [ ] **Step 1: Criar a migration**

```php
<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddDevolucaoImportFieldsToItemDevolucaosTable extends Migration
{
    public function up()
    {
        Schema::table('item_devolucaos', function (Blueprint $table) {
            $table->integer('modBC')->default(3)->after('pRedBC');
            $table->string('cest', 7)->default('')->after('ncm');
            $table->decimal('vUnTrib', 10, 4)->default(0)->after('valor_unit');
            $table->decimal('vIPI', 12, 2)->default(0)->after('vbcIpi');
        });
    }

    public function down()
    {
        Schema::table('item_devolucaos', function (Blueprint $table) {
            $table->dropColumn(['modBC', 'cest', 'vUnTrib', 'vIPI']);
        });
    }
}
```

- [ ] **Step 2: Atualizar o fillable**

Em `app/Models/ItemDevolucao.php`, trocar a linha do `$fillable`
para incluir os 4 campos (ordem alfabética do bloco existente):

```php
'unidade_medida', 'cst_csosn', 'cst_pis', 'cst_cofins', 'cst_ipi', 'perc_icms', 'perc_pis',
'perc_cofins', 'perc_ipi', 'pRedBC', 'modBC', 'vBC', 'vICMS', 'vIPI', 'cest', 'vUnTrib',
```

Resultado exato do array (substituir `'pRedBC', 'vBC', 'vICMS',` por
`'pRedBC', 'modBC', 'vBC', 'vICMS', 'vIPI', 'cest', 'vUnTrib',` e manter o resto).

- [ ] **Step 3: Conferir em ambiente com PHP/MySQL (quando disponível)**

Run: `php artisan migrate --pretend`
Expected: mostra apenas `AddDevolucaoImportFields...` como pendente, sem erro de sintaxe.

- [ ] **Step 4: Commit**

```bash
git add database/migrations/2026_09_17_000001_add_devolucao_import_fields_to_item_devolucaos_table.php app/Models/ItemDevolucao.php
git commit -m "Devolucao: migration e fillable para modBC, cest, vUnTrib, vIPI"
```

### Task 3: Hidden inputs na tela de importação

**Files:**
- Modify: `resources/views/devolucao/view_xml.blade.php:194-206` (bloco de hiddens) e `:296` (valor ICMS readonly, sem mudança)

**Interfaces:**
- Consumes: chaves `modBC, CEST, vUnTrib, vIPI` do array `$itens` (Task 1 + `verXml` já editado)
- Produces: `POST /devolucao/store` recebe `modBC[], cest[], vUnTrib[], vIPI[]`

- [ ] **Step 1: Adicionar os 4 hiddens após a linha do `vICMS`**

Após esta linha existente:

```blade
<input type="hidden" name="vICMS[]" value="{{$i['vICMS']}}">
```

inserir:

```blade
<input type="hidden" name="modBC[]" value="{{$i['modBC']}}">
<input type="hidden" name="cest[]" value="{{$i['CEST']}}">
<input type="hidden" name="vUnTrib[]" value="{{$i['vUnTrib']}}">
<input type="hidden" name="vIPI[]" value="{{$i['vIPI']}}">
```

- [ ] **Step 2: Conferir que nenhum `name=` duplicou**

Run: `grep -c 'name="modBC\[\]"' resources/views/devolucao/view_xml.blade.php`
Expected: `19` ocorrências? Não — o Blade tem 1 template dentro de `@foreach`; esperado: `1`.

Run: `grep -c 'name="modBC\[\]"' resources/views/devolucao/view_xml.blade.php`
Expected: `1`

- [ ] **Step 3: Commit**

```bash
git add resources/views/devolucao/view_xml.blade.php
git commit -m "Devolucao: hidden modBC, cest, vUnTrib, vIPI na tela de importacao"
```

### Task 4: store/update salvam os campos + corrigem swaps

**Files:**
- Modify: `app/Http/Controllers/DevolucaoController.php:562-614` (`store`) e `:679-740` (`update`, mesmo bloco de item)

**Interfaces:**
- Consumes: `modBC[], cest[], vUnTrib[], vIPI[]` do request (Task 3)
- Produces: `ItemDevolucao` persistido com os 4 campos; `uf_cons/perc_glp` corretos

- [ ] **Step 1: No `store()`, trocar o bloco de ANP/glp** (linhas ~586-592)

De:

```php
'codigo_anp' => $request->codigo_anp[$i] ?? '',
'descricao_anp' => $request->descricao_anp[$i] ?? '',
'uf_cons' => $request->descricao_anp[$i] ?? '',
'valor_partida' => $request->valor_partida[$i] ?? 0,
'perc_glp' => $request->perc_gnn[$i],
'perc_gnn' => $request->perc_gnn[$i],
'perc_gni' => $request->perc_gni[$i],
```

Para:

```php
'codigo_anp' => $request->codigo_anp[$i] ?? '',
'descricao_anp' => $request->descricao_anp[$i] ?? '',
'uf_cons' => $request->uf_cons[$i] ?? '',
'valor_partida' => $request->valor_partida[$i] ?? 0,
'perc_glp' => $request->perc_glp[$i],
'perc_gnn' => $request->perc_gnn[$i],
'perc_gni' => $request->perc_gni[$i],
```

- [ ] **Step 2: No `store()`, adicionar os 4 campos ao `$dataItem`** (após `'vICMS' => $request->vICMS[$i],`)

```php
'modBC' => (int) ($request->modBC[$i] ?? 3),
'cest' => $request->cest[$i] ?? '',
'vUnTrib' => $this->parseNumberDatabase($request->vUnTrib[$i] ?? $request->value_unit[$i]),
'vIPI' => $this->parseNumberDatabase($request->vIPI[$i] ?? 0),
```

- [ ] **Step 3: Repetir steps 1-2 no `update()`** (bloco espelho ~linhas 693-728; mesmos nomes de campo)

- [ ] **Step 4: Commit**

```bash
git add app/Http/Controllers/DevolucaoController.php
git commit -m "Devolucao: store/update salvam modBC, cest, vUnTrib, vIPI; corrige swaps ANP"
```

### Task 5: Service — ICMS SN 900, modBC, vICMS copiado, CEST

**Files:**
- Modify: `app/Services/DevolucaoService.php:94-96` (regime), `:217-228` (produto), `:309-422` (ICMS)

**Interfaces:**
- Consumes: `$i->modBC, $i->cest, $i->vICMS, $i->perc_icms, $i->vBC` (Tasks 2+4); `$config` (Business emitente)
- Produces: tag `ICMS00` (CRT 3) ou `ICMSSN900` (CRT 1) com valores da origem

- [ ] **Step 1: Regime pelo CRT do emitente**

De (linhas 94-96):

```php
$regime = $devolucao->itens[0]->cst_csosn >= 101 ? 1 : 3;
```

Para (padrão do codebase: `$config->regime`, cf. `NFeService.php:128`):

```php
$regime = (int) $config->regime === 1 ? 1 : 3;
```

- [ ] **Step 2: modBC do item em vez de fixo**

Linha 321, de `$stdICMS->modBC = 0;` para:

```php
$stdICMS->modBC = (int) ($i->modBC > 0 ? $i->modBC : 3);
```

- [ ] **Step 3: Copiar vICMS importado quando consistente**

Linhas 366-367, de:

```php
$stdICMS->pICMS = $this->format($i->perc_icms);
$stdICMS->vICMS = $stdICMS->vBC * ($stdICMS->pICMS/100);
```

Para:

```php
$stdICMS->pICMS = $this->format($i->perc_icms);
$calcICMS = (float) $stdICMS->vBC * ((float) $stdICMS->pICMS / 100);
$stdICMS->vICMS = (abs($calcICMS - (float) $i->vICMS) <= 0.05 && (float) $i->vICMS > 0)
    ? $this->format($i->vICMS)
    : $this->format($calcICMS);
```

- [ ] **Step 4: Ramo SN emite ICMSSN900 com valores da origem**

No bloco `else` do regime simples (linhas 410-422), de:

```php
$stdICMS = new \stdClass();

$stdICMS->item = $itemCont;
$stdICMS->orig = $i->orig;
$stdICMS->CSOSN = $i->cst_csosn;
$stdICMS->pCredSN = $this->format($i->perc_icms);
$stdICMS->vCredICMSSN = $this->format($i->perc_icms);
$ICMS = $nfe->tagICMSSN($stdICMS);

$somaICMS = 0;
```

Para:

```php
$stdICMS = new \stdClass();

$stdICMS->item = $itemCont;
$stdICMS->orig = $i->orig;
$stdICMS->CSOSN = '900';
$stdICMS->modBC = (int) ($i->modBC > 0 ? $i->modBC : 3);
$stdICMS->vBC = $this->format($i->vBC);
$stdICMS->pRedBC = $this->format($i->pRedBC);
$stdICMS->pICMS = $this->format($i->perc_icms);
$calcICMS = (float) $i->vBC * ((float) $i->perc_icms / 100);
$stdICMS->vICMS = (abs($calcICMS - (float) $i->vICMS) <= 0.05 && (float) $i->vICMS > 0)
    ? $this->format($i->vICMS)
    : $this->format($calcICMS);
$ICMS = $nfe->tagICMSSN($stdICMS);

$somaICMS += (float) $stdICMS->vICMS;
```

- [ ] **Step 5: ST só fora do 00/900**

Envolver o bloco das linhas 356-364 (`if($config->UF != ...)`)
com guarda de CST:

```php
if ($config->UF != $devolucao->contact->cidade->uf
    && !in_array((string) $i->cst_csosn, ['00', '900'], true)) {
```

(mantém o corpo interno inalterado).

- [ ] **Step 6: vUnTrib do item com fallback**

Linha 227, de:

```php
$stdProd->vUnTrib = $this->format($i->valor_unit, $config->casas_decimais_valor);
```

Para:

```php
$vUnTrib = (float) $i->vUnTrib > 0 ? $i->vUnTrib : $i->valor_unit;
$stdProd->vUnTrib = $this->format($vUnTrib, $config->casas_decimais_valor);
```

- [ ] **Step 7: Emitir CEST no produto**

Após `$prod = $nfe->tagprod($stdProd);` (linha 300), inserir:

```php
if (!empty($i->cest)) {
    $stdCEST = new \stdClass();
    $stdCEST->item = $itemCont;
    $stdCEST->CEST = $i->cest;
    $nfe->tagCEST($stdCEST);
}
```

- [ ] **Step 8: Commit**

```bash
git add app/Services/DevolucaoService.php
git commit -m "Devolucao: ICMS espelho (SN900, modBC, vICMS, CEST)"
```

### Task 6: Service — totais abordagem B (vNF igual à origem)

**Files:**
- Modify: `app/Services/DevolucaoService.php:424-458` (PIS/COFINS/IPI por item), `:493-517` (ICMSTot)

**Interfaces:**
- Consumes: `$somaICMS` (Task 5), `$i->vbcPis/vbcCofins/vbcIpi/perc_*`, `$devolucao->valor_devolvido/vFrete/vSeguro/vOutro/vDesc`
- Produces: `ICMSTot` com `vBC/vICMS/vPIS/vCOFINS/vIPI` somados, `vIPIDevol 0`, `vNF 12256.41` no XML cheio

- [ ] **Step 1: Acumular PIS/COFINS por item**

Após `$PIS = $nfe->tagPIS($stdPIS);` (linha 432), inserir:

```php
$somaPIS += (float) $stdPIS->vPIS;
```

Declarar `$somaPIS = 0; $somaCOFINS = 0;` junto aos acumuladores
(linhas 172-189, ao lado de `$somaIPI = 0;`).

Após `$COFINS = $nfe->tagCOFINS($stdCOFINS);` (linha 441), inserir:

```php
$somaCOFINS += (float) $stdCOFINS->vCOFINS;
```

- [ ] **Step 2: Totais por soma (abordagem B)**

Bloco `493-514`, trocar:

```php
$stdICMSTot->vICMS = $this->format($somaICMS);
```

por:

```php
$stdICMSTot->vBC = $this->format($VBC > 0 ? $VBC : $somaVbc);
$stdICMSTot->vICMS = $this->format($somaICMS);
```

e trocar:

```php
$stdICMSTot->vIPI = 0.00;
$stdICMSTot->vPIS = 0.00;
$stdICMSTot->vCOFINS = 0.00;
```

por:

```php
$stdICMSTot->vIPI = $this->format($somaIPI);
$stdICMSTot->vPIS = $this->format($somaPIS);
$stdICMSTot->vCOFINS = $this->format($somaCOFINS);
$stdICMSTot->vIPIDevol = 0.00;
```

(manter a fórmula de `vNF` da linha 514 inalterada).

- [ ] **Step 3: Commit**

```bash
git add app/Services/DevolucaoService.php
git commit -m "Devolucao: totais por soma (abordagem B, vNF igual a origem)"
```

### Task 7: Validação fim-a-fim + commit final

**Files:**
- Test: `/tmp/kilo/check_devolucao_totals.py` (throwaway, não commitar)

**Interfaces:**
- Consumes: XML de origem; regras `vNF = vProd + vIPI` (frete/seguro/outro/desc zero neste XML)
- Produces: laudo OK/FALHA antes da homologação SEFAZ

- [ ] **Step 1: Escrever o script de totais**

```python
# /tmp/kilo/check_devolucao_totals.py
import xml.etree.ElementTree as ET
NS = {'n': 'http://www.portalfiscal.inf.br/nfe'}
inf = ET.parse('/home/sistema/NFE-35260730262709000126550010000611701005840276.xml').getroot().find('n:NFe/n:infNFe', NS)
tot = {c.tag.split('}')[1]: float(c.text) for c in inf.find('n:total/n:ICMSTot', NS)}
vProd, vIPI, vNF = tot['vProd'], tot['vIPI'], tot['vNF']
print(f"origem vProd={vProd:.2f} vIPI={vIPI:.2f} vNF={vNF:.2f}")
assert abs(vProd - 11310.96) < 0.01, 'vProd divergente'
assert abs(vIPI - 945.45) < 0.01, 'vIPI divergente'
assert abs(vNF - 12256.41) < 0.01, 'vNF divergente'
assert abs(vNF - (vProd + vIPI)) < 0.01, 'vNF != vProd+vIPI'
somaIPI = sum(float(d.find('n:imposto/n:IPI/n:IPITrib/n:vIPI', NS).text)
              for d in inf.findall('n:det', NS))
assert abs(somaIPI - vIPI) < 0.02, 'soma itens != total IPI'
print('devolucao B esperada: vBC=11310.96 vICMS=791.76 vPIS=173.53 vCOFINS=799.44 vIPI=945.45 vIPIDevol=0.00 vNF=12256.41')
print('TOTAIS OK')
```

- [ ] **Step 2: Rodar**

Run: `python3 /tmp/kilo/check_devolucao_totals.py`
Expected: termina com `TOTAIS OK`

- [ ] **Step 3: Checklist em ambiente com PHP (quando disponível)**

Run: `php -l app/Models/Devolucao.php && php -l app/Http/Controllers/DevolucaoController.php && php -l app/Services/DevolucaoService.php && php -l app/Models/ItemDevolucao.php`
Expected: `No syntax errors detected` nos 4.

Importar o XML na tela e conferir antes de salvar: 19 itens, `CFOP 1202`, `CSOSN 900`, item 1 com `vBC 691,20`, `vICMS 48,38`, `vIPI 44,93`, `vPIS 10,61`, `vCOFINS 48,85`.

- [ ] **Step 4: Revisão final do diff e push local**

Run: `git log --oneline pre-devolucao-20260917..HEAD && git status --short`
Expected: só os commits das Tasks 1-6, mais este plano; nenhum `.env`, `bd.sql` ou XML no diff. Rollback, se preciso: `git reset --hard pre-devolucao-20260917`.
