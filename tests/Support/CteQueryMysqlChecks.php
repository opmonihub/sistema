<?php

use Tests\Support\CteListContractProbe as Probe;
use Tests\Support\CteQuerySqlProbe as Sql;

function runMysqlChecks()
{
    // DSN fixo deliberadamente: nunca aceitar configurações de ambiente/banco da aplicação.
    if (getenv('CTE_QUERY_ISOLATED') !== '1') {
        throw new RuntimeException('DDL permitido somente no executor descartável: definir CTE_QUERY_ISOLATED=1 no container de testes');
    }
    $pdo = new PDO('mysql:host=127.0.0.1;port=3306;dbname=cte_synthetic_validation;charset=utf8mb4', 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false]);
    $pdo->exec('SET SESSION max_execution_time=10000');
    foreach (Sql::schema() as $ddl) {
        $pdo->exec($ddl);
    }
    $insert = $pdo->prepare('INSERT INTO ctes (id,business_id,chave_nfe,tpDoc,nDoc,descOutros,vDocFisc,municipio_fim,tomador,remetente_id,expedidor_id,recebedor_id,destinatario_id,tomador_outros_id) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
    $fixtures = [
        [1, 1, '', '00', 0, '', '0.00', 1, 0, 1, null, null, 2, null],
        [2, 1, syntheticKey('55', 42) . ';' . syntheticKey('55', 43) . ';' . syntheticKey('55', 42) . ';ruim;ruim', '99', 77, '<b>carga sintética 100%_!</b>', '10.00', 2, 4, 1, null, null, 2, 3],
        [3, 1, str_repeat(';', 406) . syntheticKey('55', 99), '00', 0, '', '0.00', null, 1, 1, 4, null, 2, null],
        [4, 1, str_repeat(',', 450), '00', 0, '', '0.00', 1, null, 1, null, null, 2, null],
        [5, 2, syntheticKey('55', 987), '00', 0, '', '0.00', 1, 0, 3, null, null, 3, null],
        [6, 1, syntheticKey('57', 8) . ';RUIM;ruim;' . substr(syntheticKey('55', 9), 0, 20) . "\u{00A0}" . substr(syntheticKey('55', 9), 20), '00', 0, '', '0.00', 1, 2, 1, null, 5, 2, null],
        [7, 1, syntheticKey('55', 42, '008'), '10', 0, '', '5.00', 1, 3, 1, null, null, 2, null],
    ];
    foreach ($fixtures as $row) {
        $insert->execute($row);
    }
    $pdo->exec("INSERT INTO cities VALUES (1,'Cidade fim','SP'),(2,'Destino distinto','MG')");
    $pdo->exec("INSERT INTO contacts VALUES (1,1,'Remetente sintético',NULL),(2,1,'Destinatário sintético',NULL),(3,2,'Contato externo',NULL),(4,1,'Contato excluído','2026-01-01'),(5,1,'Recebedor sintético',NULL)");
    $previousInsert = $pdo->prepare('INSERT INTO cte_documentos_anteriores (cte_id,chave_cte,nome_emitente) VALUES (?,?,?)');
    foreach ([[2,syntheticKey('57', 11),'Emitente A'],[2,syntheticKey('57', 11),'Duplicado'],[2,'ref;anterior,ruim','Emitente B'],[5,syntheticKey('57', 222),'Não pode vazar']] as $row) {
        $previousInsert->execute($row);
    }
    $pdo->exec("INSERT INTO medida_ctes (cte_id,cod_unidade,tipo_medida,quantidade_carga) VALUES (2,'01','PESO BRUTO',2.0000),(2,'02','PESO BRUTO',1.0000),(2,'00','PESO BRUTO',9.0000),(3,'02','PESO BRUTO',1.2345),(6,'01','PESO BRUTO',-1.0000),(7,'01','PESO BRUTO',1234.5678)");

    check('schema sintético e ordinais compatíveis com campo real do repositório', function () use ($pdo) {
        $length = $pdo->query("SELECT CHARACTER_MAXIMUM_LENGTH FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='ctes' AND COLUMN_NAME='chave_nfe'")->fetchColumn();
        same(450, (int) $length, 'Schema suportado');
        echo 'MYSQL version=' . $pdo->query('SELECT VERSION()')->fetchColumn() . ' chave_nfe=' . $length . PHP_EOL;
    });
    check('SQL e PHP concordam sobre todos os documentos e referências inválidas', function () use ($pdo, $fixtures) {
        $documents = $pdo->query(Sql::documents(1))->fetchAll(PDO::FETCH_ASSOC);
        foreach ($fixtures as $fixture) {
            if ($fixture[1] !== 1) { continue; }
            $expected = Probe::keys($fixture[2], 'nfe_carga');
            $expected = array_merge($expected, Probe::other(['tpDoc'=>$fixture[3], 'nDoc'=>$fixture[4], 'descOutros'=>$fixture[5], 'vDocFisc'=>$fixture[6]]));
            $previous = $pdo->query('SELECT id,chave_cte,nome_emitente FROM cte_documentos_anteriores WHERE cte_id=' . (int) $fixture[0] . ' ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
            $expected = array_merge($expected, Probe::previous($previous));
            $actual = array_values(array_filter($documents, function ($row) use ($fixture) { return (int) $row['cte_id'] === $fixture[0]; }));
            $project = function ($item) { return [$item['categoria'], $item['chave'], $item['numero'], $item['serie'], $item['status']]; };
            $expected = array_map($project, $expected);
            $actual = array_map($project, $actual);
            sort($expected); sort($actual);
            same($expected, $actual, 'Mesma identidade/status/número para CT-e ' . $fixture[0]);
        }
    });
    check('agregados não multiplicam linhas nem contam documentos externos', function () use ($pdo) {
        $rows = $pdo->query(Sql::listing(1))->fetchAll(PDO::FETCH_ASSOC);
        same(6, count($rows), 'Uma linha por CT-e da empresa');
        $byId = array_column($rows, null, 'id');
        same(6, (int) $byId[2]['document_count'], '3 NF-e (inclui malformada), 1 outro e 2 anteriores deduplicados');
        same(1, (int) $byId[3]['document_count'], 'Ordinal 407 incluído');
        same('multiple', $byId[2]['weight_status'], 'Dois pesos brutos válidos');
        same(null, $byId[2]['peso_kg'], 'Peso múltiplo não soma');
        same('1234.5000', $byId[3]['peso_kg'], 'TON convertido em SQL');
        same('invalid', $byId[6]['weight_status'], 'Peso negativo inválido');
        same('Destino distinto', $byId[2]['cidade_nome'], 'Município fim, não destinatário');
        same(null, $byId[3]['cidade_nome'], 'Sem fallback de cidade');
        same(null, $byId[2]['tomador_nome'], 'Contato de outro tenant');
        same(null, $byId[3]['tomador_nome'], 'Soft delete respeitado');
        same(null, $byId[4]['tomador_papel'], 'Código null não vira zero');
    });
    check('filtros globais e contagens usam documentos não primeiros', function () use ($pdo) {
        foreach (['43', 'ref;anterior,ruim', 'carga sintética', '100%_!', '%', '_', "' OR 1=1 --", 'CF-e SAT', 'nada-encontrado'] as $term) {
            $statement = $pdo->prepare(Sql::filteredIds(1));
            $pattern = '%' . strtr($term, ['!' => '!!', '%' => '!%', '_' => '!_']) . '%';
            $statement->execute([$pattern]);
            $actual = array_map('intval', $statement->fetchAll(PDO::FETCH_COLUMN));
            $expected = in_array($term, ['43','ref;anterior,ruim','carga sintética','100%_!','%','_'], true) ? [2] : [];
            same($expected, $actual, 'Busca por ' . $term);
        }
        same(6, (int) $pdo->query('SELECT COUNT(*) FROM (' . Sql::listing(1) . ') q')->fetchColumn(), 'recordsTotal não conta linhas explodidas');
        $rows = $pdo->query(Sql::listing(1) . ' ORDER BY document_count DESC,c.id DESC LIMIT 2')->fetchAll(PDO::FETCH_ASSOC);
        same([2,6], array_map('intval', array_column($rows, 'id')), 'Ordenação global antes da paginação');
        $rows = $pdo->query(Sql::listing(1) . ' ORDER BY (peso_kg IS NULL) ASC,peso_kg DESC,c.id DESC')->fetchAll(PDO::FETCH_ASSOC);
        same([7,3], array_slice(array_map('intval', array_column($rows, 'id')), 0, 2), 'Peso DESC mantém nulos ao final');
        $rows = $pdo->query(Sql::listing(1) . ' ORDER BY (peso_kg IS NULL) ASC,peso_kg ASC,c.id DESC')->fetchAll(PDO::FETCH_ASSOC);
        same([3,7], array_slice(array_map('intval', array_column($rows, 'id')), 0, 2), 'Peso ASC mantém nulos ao final');
    });
    check('cidade tomador e peso são pesquisáveis antes da paginação', function () use ($pdo) {
        $cases = [
            ["cidade_nome LIKE ? OR cidade_uf LIKE ?", ['%distinto%', '%distinto%'], [2]],
            ["cidade_nome LIKE ? OR cidade_uf LIKE ?", ['%MG%', '%MG%'], [2]],
            ["tomador_nome LIKE ? OR tomador_papel LIKE ?", ['%Recebedor%', '%Recebedor%'], [6]],
            ["peso_kg = CAST(? AS DECIMAL(15,4))", [Probe::numericSearch('1.234,5')], [3]],
        ];
        foreach ($cases as [$predicate, $bindings, $expected]) {
            $query = $pdo->prepare('SELECT id FROM (' . Sql::listing(1) . ') q WHERE ' . $predicate . ' ORDER BY id');
            $query->execute($bindings);
            same($expected, array_map('intval', $query->fetchAll(PDO::FETCH_COLUMN)), 'Filtro SQL ' . $predicate);
        }
    });
    benchmarkMysql($pdo);
}

function benchmarkMysql(PDO $pdo)
{
    // Benchmark reproduzível apenas no banco sintético, sem limite rígido dependente do hardware.
    $pdo->beginTransaction();
    $insert = $pdo->prepare("INSERT INTO ctes (id,business_id,chave_nfe,tpDoc,nDoc,descOutros,vDocFisc,tomador,remetente_id,municipio_fim) VALUES (?,?,?,'00',0,'',0,0,1,1)");
    for ($id = 100; $id < 5100; ++$id) {
        $insert->execute([$id, $id % 2 === 0 ? 1 : 2, syntheticKey('55', $id) . ';' . syntheticKey('55', $id + 10000)]);
    }
    $pdo->commit();
    $analysis = $pdo->query('ANALYZE TABLE ctes,medida_ctes,cte_documentos_anteriores');
    $analysis->fetchAll();
    $analysis->closeCursor();
    $queries = [
        'records_total' => 'SELECT COUNT(*) FROM ctes WHERE business_id=1',
        'document_count_order_page' => Sql::listing(1) . ' ORDER BY document_count DESC,c.id DESC LIMIT 25',
        'filtered_count' => "SELECT COUNT(*) FROM ctes c WHERE c.business_id=1 AND c.id IN (SELECT d.cte_id FROM (" . Sql::documents(1) . ") d WHERE d.search_text LIKE '%10100%')",
    ];
    foreach ($queries as $name => $query) {
        $timings = [];
        $resultCount = 0;
        for ($run = 0; $run < 3; ++$run) {
            $start = microtime(true);
            $result = $pdo->query($query)->fetchAll(PDO::FETCH_ASSOC);
            $timings[] = round((microtime(true) - $start) * 1000, 2);
            $resultCount = $name === 'document_count_order_page' ? count($result) : (int) reset($result[0]);
            same($name === 'document_count_order_page' ? 25 : ($name === 'records_total' ? 2506 : 1), $resultCount, 'Resultado do benchmark ' . $name);
        }
        echo 'BENCH ' . $name . ' rows_or_count=' . $resultCount . ' ms=' . json_encode($timings) . PHP_EOL;
        $plan = $pdo->query('EXPLAIN ' . $query)->fetchAll(PDO::FETCH_ASSOC);
        echo 'EXPLAIN ' . $name . ' ' . json_encode(array_map(function ($step) { return array_intersect_key($step, array_flip(['select_type','table','type','possible_keys','key','rows','Extra'])); }, $plan), JSON_UNESCAPED_UNICODE) . PHP_EOL;
        if ($name === 'document_count_order_page') {
            $actualPlan = $pdo->query('EXPLAIN ANALYZE ' . $query)->fetchColumn();
            echo 'EXPLAIN_ANALYZE ' . json_encode(array_values(array_filter(explode("\n", $actualPlan), function ($line) { return strpos($line, 'rows=') !== false && (strpos($line, 'Limit:') !== false || strpos($line, 'Aggregate using temporary') !== false || strpos($line, 'Materialize') !== false); })), JSON_UNESCAPED_UNICODE) . PHP_EOL;
        }
    }
    check('benchmark mantém contagem e página estáveis', function () use ($pdo) {
        same(2506, (int) $pdo->query('SELECT COUNT(*) FROM ctes WHERE business_id=1')->fetchColumn(), 'Tenant com 2500 fixtures adicionais');
        same(2506, (int) $pdo->query('SELECT COUNT(*) FROM (' . Sql::listing(1) . ') q')->fetchColumn(), 'Cardinalidade global não muda');
    });
}
