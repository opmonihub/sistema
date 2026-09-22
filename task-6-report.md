# Task 6 — Remoção bloco morto `nuvemshop` (routes/web.php)

## O que foi feito
- Removido o grupo `Route::group(['prefix' => 'nuvemshop'], ...)` em `routes/web.php` (antigas linhas 212-247, 38 linhas deletadas, 0 adições).
- Bloco continha 28 rotas apontando para 4 controllers inexistentes:
  - `NuvemShopAuthController` (3 rotas: `/`, `/auth`, `/app`)
  - `NuvemShopController` (8 rotas: config/save/categorias)
  - `NuvemShopProdutoController` (8 rotas: produtos/galeria/imagens)
  - `NuvemShopPedidoController` (9 rotas: pedidos/filtro/clientes/vendas/NFe)
- Nenhum outro arquivo referenciava esses controllers ou rotas nomeadas `nuvemshop.*`.

## Verificações
- `ls app/Http/Controllers/NuvemShop*` → `No such file or directory` (4 controllers confirmadamente ausentes).
- `grep -rni "nuvemshop" --include="*.php" --include="*.blade.php" .` **antes**: ocorrências só em `routes/web.php:213-246`. **Depois**: zero ocorrências em todo o repo.
- `git diff --stat` → `routes/web.php | 38 -------` (só deleções, bloco autocontido; grupos vizinhos `mdfe` e `/manifesto` intactos).
- `php -l` não executado: binário `php` indisponível no ambiente. Diff-check manual confirma remoção de bloco balanceado (abre `Route::group(... function () {`, fecha `});`), sem tocar chaves vizinhas.
- Sem suite de testes aplicável (remoção de rotas mortas; sem linter configurado no repo).

## Commit
- `c3789df` — `chore(routes): remove bloco morto prefix nuvemshop (4 controllers inexistentes)` no branch `refactor/logs-2026-09-22` (worktree `/home/sistema/.worktrees/refactor-logs`).

## Concerns
- Rotas mortas nunca resolviam (controllers ausentes → 500/ReflectionException se acessadas); remoção só elimina código inalcançável, sem mudança de comportamento válido.
- Se a integração NuvemShop for reativada um dia, será preciso recriar os 4 controllers + rotas do zero (nada a recuperar além do `git show c3789df^:routes/web.php`).
- Observação: brief `task-6-brief.md` não encontrado no worktree nem no repo — execução seguiu o escopo da mensagem (sem subagentes).
