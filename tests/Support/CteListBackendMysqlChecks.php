<?php

use App\Http\Controllers\CteController;
use App\Utils\CteListPresenter;
use App\Utils\CteListQuery;
use Illuminate\Container\Container;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Support\Facades\Facade;

function runCteListBackendMysqlChecks()
{
    if (getenv('CTE_QUERY_ISOLATED') !== '1') { throw new RuntimeException('Executor MySQL descartável obrigatório'); }
    $app = new Container;
    Container::setInstance($app);
    $config = require dirname(getenv('CTE_BACKEND_AUTOLOAD'), 2) . '/vendor/yajra/laravel-datatables-oracle/src/config/datatables.php';
    $config['error'] = 'throw';
    $config['search']['multi_term'] = true;
    $app->instance('config', new \Illuminate\Config\Repository(['app'=>['debug'=>false], 'datatables'=>$config]));
    $capsule = new Capsule($app);
    $capsule->addConnection(['driver'=>'mysql','host'=>'127.0.0.1','port'=>3306,'database'=>'cte_synthetic_validation','username'=>'root','password'=>'','charset'=>'utf8mb4','collation'=>'utf8mb4_unicode_ci','prefix'=>'','strict'=>true]);
    $capsule->setAsGlobal();
    $capsule->bootEloquent();
    $app->instance('db', $capsule->getDatabaseManager());
    Facade::setFacadeApplication($app);
    $app->bind('datatables', function () { return new \Yajra\DataTables\DataTables; });
    $app->bind('datatables.request', function () { return new \Yajra\DataTables\Utilities\Request; });
    $app->bind('datatables.config', function () use ($app) { return new \Yajra\DataTables\Utilities\Config($app['config']); });
    $db = $capsule->getConnection();
    $db->statement('SET SESSION max_execution_time=10000');
    require __DIR__ . '/CteQuerySqlProbe.php';
    foreach (\Tests\Support\CteQuerySqlProbe::schema() as $ddl) { $db->statement($ddl); }
    $db->statement("ALTER TABLE ctes ADD cte_numero INT DEFAULT 0, ADD valor_transporte DECIMAL(10,2) DEFAULT 0, ADD valor_receber DECIMAL(10,2) DEFAULT 0, ADD valor_carga DECIMAL(10,2) DEFAULT 0, ADD produto_predominante VARCHAR(30) DEFAULT '', ADD data_previsata_entrega DATE DEFAULT '2026-10-14', ADD estado VARCHAR(20) DEFAULT 'NOVO', ADD created_at DATETIME DEFAULT '2026-10-14 10:00:00', ADD cte_original_id INT NULL");
    $fixtures = [
        ['id'=>1,'business_id'=>1,'chave_nfe'=>'','tomador'=>0,'remetente_id'=>1,'destinatario_id'=>2,'municipio_fim'=>1],
        ['id'=>2,'business_id'=>1,'chave_nfe'=>backendKey('55',42) . ';' . backendKey('55',43) . ';' . backendKey('55',42) . ';ruim;ruim','tpDoc'=>'99','nDoc'=>77,'descOutros'=>'<b>carga sintética 100%_!</b>','vDocFisc'=>'10.00','tomador'=>4,'tomador_outros_id'=>3,'remetente_id'=>1,'destinatario_id'=>2,'municipio_fim'=>2],
        ['id'=>3,'business_id'=>1,'chave_nfe'=>str_repeat(';',406) . backendKey('55',99),'tomador'=>1,'expedidor_id'=>4,'municipio_fim'=>null,'cte_numero'=>11],
        ['id'=>4,'business_id'=>1,'chave_nfe'=>str_repeat(',',450),'tomador'=>null,'municipio_fim'=>1],
        ['id'=>5,'business_id'=>2,'chave_nfe'=>backendKey('55',987),'tomador'=>0,'remetente_id'=>3,'destinatario_id'=>3,'municipio_fim'=>1],
        ['id'=>6,'business_id'=>1,'chave_nfe'=>backendKey('57',8) . ';RUIM;ruim;' . substr(backendKey('55',9),0,20) . "\u{00A0}" . substr(backendKey('55',9),20),'tomador'=>2,'recebedor_id'=>5,'municipio_fim'=>1],
        ['id'=>7,'business_id'=>1,'chave_nfe'=>backendKey('55',42,'008'),'tpDoc'=>'10','vDocFisc'=>'5.00','tomador'=>3,'destinatario_id'=>2,'municipio_fim'=>1,'cte_numero'=>12],
        ['id'=>8,'business_id'=>1,'chave_nfe'=>'','tomador'=>8,'remetente_id'=>3,'destinatario_id'=>4,'cte_original_id'=>2],
        ['id'=>9,'business_id'=>1,'chave_nfe'=>'','tomador'=>0,'remetente_id'=>1,'descOutros'=>'0','tpDoc'=>'99'],
    ];
    foreach ($fixtures as $fixture) { $db->table('ctes')->insert($fixture); }
    $db->table('cities')->insert([['id'=>1,'nome'=>'Cidade fim','uf'=>'SP'],['id'=>2,'nome'=>'Destino distinto','uf'=>'MG']]);
    $db->table('contacts')->insert([
        ['id'=>1,'business_id'=>1,'name'=>'<script>Remetente</script>','deleted_at'=>null],
        ['id'=>2,'business_id'=>1,'name'=>'Destinatário sintético','deleted_at'=>null],
        ['id'=>3,'business_id'=>2,'name'=>'Contato externo','deleted_at'=>null],
        ['id'=>4,'business_id'=>1,'name'=>'Contato excluído','deleted_at'=>'2026-01-01'],
        ['id'=>5,'business_id'=>1,'name'=>'Recebedor sintético','deleted_at'=>null],
    ]);
    foreach ([[2,backendKey('57',11),null],[2,backendKey('57',11),'Primeiro informado'],[2,backendKey('57',11),'Duplicado'],[2,'ref;anterior,ruim','Emitente B'],[5,backendKey('57',222),'Não pode vazar']] as $previous) {
        $db->table('cte_documentos_anteriores')->insert(['cte_id'=>$previous[0],'chave_cte'=>$previous[1],'nome_emitente'=>$previous[2]]);
    }
    foreach ([[2,'01','PESO BRUTO','2.0000'],[2,'02','PESO BRUTO','1.0000'],[2,'00','PESO BRUTO','9.0000'],[3,'02',"\tPESO BRUTO\r",'1.2345'],[6,'01','PESO BRUTO','-1.0000'],[7,'01','PESO BRUTO','1234.5678'],[1,'01','PESO DECLARADO','99.0000'],[4,'00','PESO BRUTO','99.0000']] as $measure) {
        $db->table('medida_ctes')->insert(['cte_id'=>$measure[0],'cod_unidade'=>$measure[1],'tipo_medida'=>$measure[2],'quantidade_carga'=>$measure[3]]);
    }
    $session = new \Illuminate\Session\Store('cte-test', new \Illuminate\Session\ArraySessionHandler(60));
    $session->put('user.business_id',1);
    $session->put('user.id',1);
    $app->instance('session', $session);
    $app->instance('auth', new class {
        public function user() { return new class { public function can($permission) { return true; } }; }
    });
    $app->alias('auth', \Illuminate\Contracts\Auth\Factory::class);
    $app->alias('url', \Illuminate\Contracts\Routing\UrlGenerator::class);
    $routes = new \Illuminate\Routing\RouteCollection;
    foreach (['destroy','ver','gerar','edit'] as $name) {
        $route = new \Illuminate\Routing\Route(['GET'], 'cte/' . $name . '/{id}', function () {});
        $route->name('cte.' . $name);
        $routes->add($route);
    }
    $app->instance('url', new \Illuminate\Routing\UrlGenerator($routes, \Illuminate\Http\Request::create('http://synthetic.test')));
    $controller = (new ReflectionClass(CteController::class))->newInstanceWithoutConstructor();
    $request = function ($options = []) use ($app,$session,$controller) {
        $names = ['numero_cte','remetente','destinatario','valor_transporte','valor_receber','valor_carga','produto_predominante','data_previsata_entrega','estado','created_at','documentos','cidade_destino','peso_kg','tomador_servico','action'];
        $columns = [];
        foreach ($names as $name) {
            $columns[] = ['data'=>$name,'name'=>$name,'searchable'=>$name==='action' ? 'false' : 'true','orderable'=>$name==='action' ? 'false' : 'true','search'=>['value'=>$options['columnSearch'][$name] ?? '', 'regex'=>'false']];
        }
        $order = [];
        foreach ($options['orders'] ?? [[$options['order'] ?? 'documentos', $options['dir'] ?? 'desc']] as $item) {
            $order[] = ['column'=>array_search($item[0],$names,true),'dir'=>$item[1]];
        }
        $input = ['draw'=>3,'start'=>$options['start'] ?? 0,'length'=>$options['length'] ?? 25,'search'=>['value'=>$options['search'] ?? '','regex'=>'false'],'columns'=>$columns,'order'=>$order];
        $http = \Illuminate\Http\Request::create('/cte','GET',$input,[],[],['HTTP_X_REQUESTED_WITH'=>'XMLHttpRequest']);
        $http->setLaravelSession($session);
        $app->instance('request',$http);
        $app->forgetInstance('datatables.request');
        return $controller->index()->getData(true);
    };
    $ids = function ($response) { return array_map(function ($row) { preg_match('/id="cte([0-9]+)"/', $row['action'], $matches); return (int)$matches[1]; }, $response['data']); };

    backendCheck('controller real entrega envelopes e ações preservadas sem N+1', function () use ($request,$ids,$db) {
        $db->enableQueryLog(); $db->flushQueryLog();
        $response = $request();
        backendSame(8,$response['recordsTotal'],'recordsTotal por empresa');
        backendSame(8,$response['recordsFiltered'],'Uma linha por CT-e');
        backendSame([2,6,7,9,3,8,4,1],$ids($response),'Quantidade global com ID desc no empate');
        $row = $response['data'][0];
        foreach (['documentos','cidade_destino','peso_kg','tomador_servico'] as $name) {
            foreach (['display','status','raw','export'] as $field) { backendSame(true,array_key_exists($field,$row[$name]),'Envelope ' . $name . '.' . $field); }
        }
        backendSame(6,$row['documentos']['count'],'Deduplicação idêntica à consulta');
        backendSame('<b>carga sintética 100%_!</b>',$row['documentos']['items'][3]['descricao'],'Envelope não sofre duplo escape');
        backendSame('Primeiro informado',$row['documentos']['items'][4]['emitente'],'Emitente deduplicado');
        backendSame('multiple',$row['peso_kg']['status'],'Peso ambíguo');
        backendSame('missing_contact',$row['tomador_servico']['status'],'Contato externo não vaza');
        backendSame('&lt;script&gt;Remetente&lt;/script&gt;',$row['remetente'],'Coluna antiga agora escapada');
        backendSame(true,strpos($row['action'],'Gerar CTe')!==false,'Ação gerar preservada');
        backendSame(true,strpos($response['data'][2]['action'],'Ver CTe')!==false,'Ação ver preservada');
        foreach (['chave_nfe','documentosAnteriores','documentos_anteriores','tomador_nome','cte_original_id'] as $internal) { backendSame(false,array_key_exists($internal,$row),'Não expor campos internos'); }
        $queries = $db->getQueryLog();
        backendSame(true,count($queries)<=6,'Número constante de consultas, recebido ' . count($queries));
        $db->flushQueryLog(); $request(['length'=>1]);
        backendSame(count($queries),count($db->getQueryLog()),'Tamanho da página não muda quantidade de queries');
        $db->disableQueryLog();
    });
    backendCheck('filtros reais buscam todos documentos e escapam curingas', function () use ($request,$ids) {
        foreach (['43','ref;anterior,ruim','carga sintética','100%_!','%','_','Primeiro informado'] as $term) {
            $response = $request(['columnSearch'=>['documentos'=>$term], 'length'=>1]);
            backendSame(1,$response['recordsFiltered'],'Contagem filtrada para ' . $term);
            backendSame([2],$ids($response),'Filtro anterior à paginação: ' . $term);
        }
        foreach (["' OR 1=1 --",'222','987','CF-e SAT','Duplicado'] as $term) { backendSame(0,$request(['columnSearch'=>['documentos'=>$term]])['recordsFiltered'],'Não vazar/injetar: ' . $term); }
        backendSame([2],$ids($request(['search'=>'carga sintética'])),'Busca global Yajra');
        foreach (['%','_'] as $literal) {
            $response = $request(['search'=>$literal,'length'=>1]);
            backendSame(1,$response['recordsFiltered'],'Busca global literal não expande ' . $literal . ' nas colunas antigas');
            backendSame([2],$ids($response),'Somente documento com literal ' . $literal);
        }
        $db = Capsule::connection();
        $db->beginTransaction();
        try {
            $db->table('ctes')->where('id',1)->update(['produto_predominante'=>'Carga %_! brasileira']);
            backendSame([2,1],$ids($request(['search'=>'%'])),'Busca global também encontra literal no produto');
            backendSame([1],$ids($request(['columnSearch'=>['produto_predominante'=>'%_!']])),'Filtro literal na coluna antiga');
        } finally { $db->rollBack(); }
    });
    backendCheck('ordenação paginada real por documentos respeita prioridade e desempates', function () use ($request,$ids) {
        backendSame([2,6],$ids($request(['length'=>2])),'Top 2 antes de paginar');
        backendSame([7,9],$ids($request(['length'=>2,'start'=>2])),'Página seguinte estável');
        backendSame([8,4,1],$ids($request(['dir'=>'asc','length'=>3])),'Ascendente não é anulado por ID desc');
        backendSame([3,9,7],array_slice($ids($request(['orders'=>[['documentos','asc'],['numero_cte','desc']]])),3,3),'Ordenação secundária precede ID');
    });
    backendCheck('cidade fim tomador e peso reais pesquisáveis e isolados', function () use ($request,$ids) {
        foreach ([['cidade_destino','MG',[2]],['cidade_destino','distinto',[2]],['tomador_servico','Recebedor',[6]],['tomador_servico','Destinatário',[7]],['peso_kg','1.234,5',[3]],['peso_kg','1234,5678',[7]]] as $case) {
            backendSame($case[2],$ids($request(['columnSearch'=>[$case[0]=>$case[1]]])),'Filtro ' . $case[0] . ':' . $case[1]);
        }
        $db = Capsule::connection();
        $db->beginTransaction();
        try {
            $db->table('medida_ctes')->where('cte_id',7)->update(['quantidade_carga'=>'1234.0000']);
            backendSame([7],$ids($request(['columnSearch'=>['peso_kg'=>'1.234']])),'Milhares brasileiros pesquisam o peso inteiro');
            $db->table('medida_ctes')->where('cte_id',7)->update(['quantidade_carga'=>'1.2340']);
            backendSame([7],$ids($request(['columnSearch'=>['peso_kg'=>'1,234']])),'Vírgula brasileira pesquisa o peso fracionado');
            backendSame([],$ids($request(['columnSearch'=>['peso_kg'=>'1.234']])),'Ponto não é interpretado como decimal');
        } finally { $db->rollBack(); }
        backendSame(0,$request(['columnSearch'=>['tomador_servico'=>'Contato externo']])['recordsFiltered'],'Tenant do contato');
        backendSame(0,$request(['columnSearch'=>['tomador_servico'=>'Contato excluído']])['recordsFiltered'],'Soft delete');
        backendSame([3,7],array_slice($ids($request(['order'=>'peso_kg','dir'=>'asc'])),0,2),'Peso ASC com nulos no final');
        backendSame([7,3],array_slice($ids($request(['order'=>'peso_kg','dir'=>'desc'])),0,2),'Peso DESC com nulos no final');
        backendSame(0,$request(['columnSearch'=>['peso_kg'=>'9999999999999999999']])['recordsFiltered'],'Busca extrema não estoura precisão SQL');
        $rows = $request(['order'=>'numero_cte','dir'=>'asc'])['data'];
        $byId = array_combine($ids(['data'=>$rows]),$rows);
        backendSame('1234.5000',$byId[3]['peso_kg']['raw'],'TON × 1000 em SQL decimal');
        backendSame('invalid',$byId[6]['peso_kg']['status'],'Peso negativo');
        backendSame('missing',$byId[8]['peso_kg']['status'],'Sem herança do original');
        backendSame(0,$byId[8]['documentos']['count'],'Complemento não herda documentos');
        backendSame('invalid',$byId[4]['tomador_servico']['status'],'Null não vira 0');
        backendSame('invalid',$byId[8]['tomador_servico']['status'],'Código inválido explícito');
        backendSame('—',$byId[8]['remetente'],'Contato externo também oculto na coluna antiga');
        backendSame('—',$byId[8]['destinatario'],'Soft delete na coluna antiga');
    });
    backendCheck('empresa alternada e empresa sem registros mantêm isolamento', function () use ($request,$ids,$session) {
        try {
            $session->put('user.business_id',2);
            $response = $request();
            backendSame(1,$response['recordsTotal'],'Contagem da segunda empresa');
            backendSame([5],$ids($response),'CT-e da segunda empresa');
            backendSame('Contato externo',$response['data'][0]['tomador_servico']['raw']['nome'],'Contato pertence ao tenant agora consultado');
            backendSame(2,$response['data'][0]['documentos']['count'],'Documentos anteriores somente do novo tenant');
            backendSame(0,$request(['columnSearch'=>['documentos'=>'43']])['recordsFiltered'],'Documento da primeira empresa não vaza');
            $session->put('user.business_id',999);
            backendSame([],$request()['data'],'Empresa vazia não retorna outra base');
        } finally { $session->put('user.business_id',1); }
    });
    backendCheck('projeção real concorda com contrato independente e peso decimal extremo', function () use ($db) {
        require __DIR__ . '/CteListContractProbe.php';
        foreach ([10,11,12,13,14] as $id) { $db->table('ctes')->insert(['id'=>$id,'business_id'=>1,'chave_nfe'=>'']); }
        foreach ([[10,'0.0000'],[11,'999999.9999'],[12,null],[13,'1.0000'],[13,'-1.0000'],[14,'1.0000'],[14,'2.0000'],[14,'-1.0000']] as $measure) {
            $db->table('medida_ctes')->insert(['cte_id'=>$measure[0],'cod_unidade'=>$measure[0] === 11 ? '02' : '01','tipo_medida'=>'PESO BRUTO','quantidade_carga'=>$measure[1]]);
        }
        $rows = (new CteListQuery(1))->query()->get();
        foreach ($rows as $row) {
            $actual = CteListPresenter::documents($row,$row->documentosAnteriores);
            $expected = array_merge(\Tests\Support\CteListContractProbe::keys($row->chave_nfe,'nfe_carga'),\Tests\Support\CteListContractProbe::other($row->toArray()),\Tests\Support\CteListContractProbe::previous($row->documentosAnteriores->toArray()));
            backendSame(\Tests\Support\CteListContractProbe::documentCell($expected),$actual,'Contrato original de documentos: ' . $row->id);
            backendSame(count($actual['items']),(int)$row->document_count,'Contagem SQL = itens PHP: ' . $row->id);
            $measures = $db->table('medida_ctes')->where('cte_id',$row->id)->get()->map(function ($item) { return (array)$item; })->all();
            backendSame(\Tests\Support\CteListContractProbe::weight($measures),CteListPresenter::weight($row->weight_status,$row->peso_valor),'Peso SQL = contrato independente: ' . $row->id);
        }
        $byId = $rows->keyBy('id');
        backendSame('0.0000',$byId[10]->peso_valor,'Zero é massa válida');
        backendSame('999999999.9000',$byId[11]->peso_valor,'TON máximo sem float/truncamento');
        backendSame('invalid',$byId[12]->weight_status,'Quantidade nula inválida');
        backendSame('invalid',$byId[13]->weight_status,'Válido + inválido não escolhe válido');
        backendSame('multiple',$byId[14]->weight_status,'Múltiplos válidos prevalecem sobre inválido');
        // Exercitar transições de dezenas/centenas e os extremos do VARCHAR(450).
        $db->beginTransaction();
        try {
            $positions = [0,9,10,99,100,199,200,299,300,399,400,406];
            foreach ($positions as $index=>$empty) {
                $db->table('ctes')->insert(['id'=>10000+$index,'business_id'=>67,'chave_nfe'=>str_repeat(';',$empty) . backendKey('55',42)]);
            }
            $db->table('ctes')->insert(['id'=>10020,'business_id'=>67,'chave_nfe'=>str_repeat(';',449) . 'x']);
            $db->table('ctes')->insert(['id'=>10021,'business_id'=>67,'chave_nfe'=>str_repeat(';',450)]);
            foreach ((new CteListQuery(67))->query()->get() as $row) {
                $documents = CteListPresenter::documents($row,$row->documentosAnteriores);
                backendSame($row->id === 10021 ? 0 : 1,$documents['count'],'Poda ordinal preserva posição ID ' . $row->id);
                backendSame($documents['count'],(int)$row->document_count,'Paridade ordinal SQL/PHP ID ' . $row->id);
            }
        } finally { $db->rollBack(); }
    });
    backendCheck('ordenação real de cidade nome/UF e tomador nome/papel', function () use ($db,$request,$ids) {
        $db->table('cities')->insert(['id'=>3,'nome'=>'Cidade fim','uf'=>'RJ']);
        $db->table('ctes')->where('id',4)->update(['municipio_fim'=>3]);
        $cityAsc = $ids($request(['order'=>'cidade_destino','dir'=>'asc']));
        backendSame(true,array_search(4,$cityAsc,true)<array_search(1,$cityAsc,true),'Mesmo nome desempata UF asc');
        $cityDesc = $ids($request(['order'=>'cidade_destino','dir'=>'desc']));
        backendSame(true,array_search(1,$cityDesc,true)<array_search(4,$cityDesc,true),'Mesmo nome desempata UF desc');
        $db->table('ctes')->insert(['id'=>15,'business_id'=>1,'chave_nfe'=>'','tomador'=>1,'expedidor_id'=>1]);
        $takerAsc = $ids($request(['order'=>'tomador_servico','dir'=>'asc']));
        backendSame(true,array_search(15,$takerAsc,true)<array_search(1,$takerAsc,true),'Mesmo nome desempata papel asc');
        $takerDesc = $ids($request(['order'=>'tomador_servico','dir'=>'desc']));
        backendSame(true,array_search(1,$takerDesc,true)<array_search(15,$takerDesc,true),'Mesmo nome desempata papel desc');
    });
    backendCheck('consulta real verifica schema maior sem truncamento silencioso', function () use ($db,$request) {
        $db->statement('ALTER TABLE ctes MODIFY chave_nfe VARCHAR(451) NOT NULL');
        try {
            $request();
            throw new RuntimeException('Schema maior não foi rejeitado');
        } catch (LengthException $expected) {
        } finally { $db->statement('ALTER TABLE ctes MODIFY chave_nfe VARCHAR(450) NOT NULL'); }
    });
    echo 'MYSQL_BACKEND version=' . $db->selectOne('SELECT VERSION() AS version')->version . ' sql_mode=' . $db->selectOne('SELECT @@sql_mode AS mode')->mode . PHP_EOL;
    if (in_array('--benchmark',$GLOBALS['argv'],true)) { benchmarkCteListBackend($db,$request); }
}

function benchmarkCteListBackend($db,$request)
{
    $db->transaction(function () use ($db) {
        for ($start=100;$start<5100;$start+=250) {
            $batch = [];
            for ($id=$start;$id<$start+250;++$id) {
                $batch[] = ['id'=>$id,'business_id'=>$id%2===0 ? 1 : 2,'chave_nfe'=>backendKey('55',$id) . ';' . backendKey('55',$id+10000),'municipio_fim'=>1,'tomador'=>0,'remetente_id'=>1];
            }
            $db->table('ctes')->insert($batch);
        }
    });
    $db->select('ANALYZE TABLE ctes,medida_ctes,cte_documentos_anteriores');
    foreach (['document_order_page'=>[], 'filtered_document_count'=>['columnSearch'=>['documentos'=>'10100']], 'global_document_search'=>['search'=>'10100']] as $name=>$options) {
        $timings = [];
        for ($run=0;$run<3;++$run) {
            $db->enableQueryLog(); $db->flushQueryLog();
            $start = microtime(true);
            $response = $request($options);
            $timings[] = round((microtime(true)-$start)*1000,2);
            backendSame(2514,$response['recordsTotal'],'Benchmark tenant');
            backendSame($name==='document_order_page' ? 25 : 1,count($response['data']),'Página benchmark');
            backendSame($name==='document_order_page' ? 2514 : 1,$response['recordsFiltered'],'Contagem benchmark');
        }
        $queries = $db->getQueryLog();
        $db->disableQueryLog();
        echo 'BACKEND_BENCH ' . $name . ' request_ms=' . json_encode($timings) . ' queries=' . count($queries) . ' sql_ms=' . json_encode(array_column($queries,'time')) . PHP_EOL;
        // Gate opt-in para comparar regressões no mesmo ambiente, não um SLA de produção.
        $budget = getenv('CTE_BENCH_MAX_MEDIAN_MS');
        if ($budget !== false && $budget !== '') {
            if (!ctype_digit($budget) || (int)$budget <= 0) {
                throw new RuntimeException('CTE_BENCH_MAX_MEDIAN_MS exige inteiro positivo.');
            }
            $sorted = $timings;
            sort($sorted, SORT_NUMERIC);
            backendSame(true, $sorted[1] <= (int)$budget, 'Mediana de ' . $name . ' excede orçamento sintético de ' . $budget . ' ms: ' . $sorted[1]);
        }
        foreach ($queries as $query) {
            if (strpos($query['query'],'document_count') === false) { continue; }
            $plan = $db->select('EXPLAIN ' . $query['query'],$query['bindings']);
            echo 'BACKEND_EXPLAIN ' . $name . ' ' . json_encode(array_map(function ($step) { return array_intersect_key((array)$step,array_flip(['select_type','table','type','key','rows','Extra'])); },$plan),JSON_UNESCAPED_UNICODE) . PHP_EOL;
        }
    }
}
