<?php

// Reaproveita runner, configuração isolada, fixtures e suíte backend sem modificá-los.
require __DIR__ . '/cte-list-backend.php';
if (getenv('CTE_QUERY_ISOLATED') !== '1') {
    fwrite(STDERR, "Regressões exigem o MySQL descartável CTE_QUERY_ISOLATED=1.\n");
    exit(2);
}
if (!in_array('--mysql', $argv, true)) {
    require_once __DIR__ . '/Support/CteListBackendMysqlChecks.php';
    runCteListBackendMysqlChecks();
}

use App\Http\Controllers\CteController;
use App\Utils\CteListPresenter;
use App\Utils\CteListQuery;
use Tests\Support\CteListContractProbe;

$collationFailures = [];
$collationChecks = 0;
$collationOnly = null;
foreach ($argv as $argument) {
    if (strpos($argument, '--only=') === 0) { $collationOnly = substr($argument, 7); }
}
function cteCollationCheck($issue, $name, callable $test)
{
    global $collationFailures, $collationChecks, $collationOnly;
    if ($collationOnly !== null && $collationOnly !== $issue) { return; }
    try {
        $test();
        ++$collationChecks;
        echo 'PASS ' . $issue . ' ' . $name . PHP_EOL;
    } catch (Throwable $error) {
        $collationFailures[] = $issue . ' ' . $name;
        fwrite(STDERR, 'FAIL ' . $issue . ' ' . $name . ': ' . $error->getMessage() . PHP_EOL);
    }
}
function cteCollationRequest($term = '', $column = 'documentos')
{
    $session = app('session');
    $session->put('user.business_id', 77);
    $names = ['numero_cte','remetente','destinatario','valor_transporte','valor_receber','valor_carga','produto_predominante','data_previsata_entrega','estado','created_at','documentos','cidade_destino','peso_kg','tomador_servico','action'];
    $columns = [];
    foreach ($names as $name) {
        $columns[] = ['data'=>$name,'name'=>$name,'searchable'=>$name === 'action' ? 'false' : 'true','orderable'=>$name === 'action' ? 'false' : 'true','search'=>['value'=>$name === $column ? $term : '', 'regex'=>'false']];
    }
    $http = \Illuminate\Http\Request::create('/cte', 'GET', [
        'draw'=>1,'start'=>0,'length'=>100,'search'=>['value'=>$column === null ? $term : '', 'regex'=>'false'],
        'columns'=>$columns,'order'=>[['column'=>0,'dir'=>'asc']],
    ], [], [], ['HTTP_X_REQUESTED_WITH'=>'XMLHttpRequest']);
    $http->setLaravelSession($session);
    app()->instance('request', $http);
    app()->forgetInstance('datatables.request');
    return (new ReflectionClass(CteController::class))->newInstanceWithoutConstructor()->index()->getData(true);
}
function cteCollationIds($response)
{
    $ids = array_map(function ($row) {
        preg_match('/id="cte([0-9]+)"/', $row['action'], $matches);
        return (int) $matches[1];
    }, $response['data']);
    sort($ids);
    return $ids;
}
function cteCollationParity($id)
{
    $row = (new CteListQuery(77))->query()->where('ctes.id', $id)->firstOrFail();
    $cell = CteListPresenter::documents($row, $row->documentosAnteriores);
    $expected = array_merge(CteListContractProbe::keys($row->chave_nfe, 'nfe_carga'), CteListContractProbe::other($row->toArray()), CteListContractProbe::previous($row->documentosAnteriores->toArray()));
    backendSame(CteListContractProbe::documentCell($expected), $cell, 'Documentos PHP = contrato independente ID ' . $id);
    backendSame($cell['count'], (int) $row->document_count, 'Contagem SQL = PHP ID ' . $id);
    return $cell;
}

$db = \Illuminate\Database\Capsule\Manager::connection();
$fixtureRows = [
    ['id'=>201,'chave_nfe'=>backendKey('55',201),'tpDoc'=>'99','descOutros'=>'MERCADORIA ÁGUA 100%_!'],
    ['id'=>202,'chave_nfe'=>'','tpDoc'=>'00','descOutros'=>'Descrição secundária'],
    ['id'=>203,'chave_nfe'=>'','tpDoc'=>'99','descOutros'=>"\u{200B}"],
    ['id'=>204,'chave_nfe'=>'','tpDoc'=>'99','descOutros'=>"\u{FEFF}"],
    ['id'=>205,'chave_nfe'=>''], ['id'=>206,'chave_nfe'=>''],
    ['id'=>207,'chave_nfe'=>'','tpDoc'=>'９９','descOutros'=>'Confundível marcador'],
    ['id'=>208,'chave_nfe'=>''], ['id'=>209,'chave_nfe'=>''],
    ['id'=>210,'chave_nfe'=>''], ['id'=>211,'chave_nfe'=>''],
    ['id'=>212,'chave_nfe'=>"RUIM;ruim;réf;ref;\u{200B};\u{FEFF}",'tpDoc'=>'00','descOutros'=>" \t\r\n\v\f"],
    ['id'=>213,'chave_nfe'=>''], ['id'=>214,'chave_nfe'=>''],
    ['id'=>215,'chave_nfe'=>''], ['id'=>217,'chave_nfe'=>''],
];
foreach ($fixtureRows as $row) { $db->table('ctes')->insert(array_merge(['business_id'=>77,'nDoc'=>0,'vDocFisc'=>'0.00'], $row)); }
$db->table('ctes')->insert(['id'=>216,'business_id'=>78,'chave_nfe'=>backendKey('55',216),'tpDoc'=>'99','descOutros'=>'MERCADORIA ÁGUA externa']);
foreach ([205=>"\u{200B}",206=>"\u{FEFF}"] as $id=>$issuer) {
    foreach ([$issuer, 'Posterior indevido ' . $id] as $name) {
        $db->table('cte_documentos_anteriores')->insert(['cte_id'=>$id,'chave_cte'=>backendKey('57',$id),'nome_emitente'=>$name]);
    }
}
foreach ([['cte_id'=>213,'chave_cte'=>"\u{200B}",'nome_emitente'=>'Emitente invisível'],['cte_id'=>214,'chave_cte'=>backendKey('57',214),'nome_emitente'=>'ÁGUA Emitente']] as $row) {
    $db->table('cte_documentos_anteriores')->insert($row);
}
foreach ([[208,'０１','PESO BRUTO','1.0000'],[209,'０２','PESO BRUTO','1.0000'],[210,'01','ＰＥＳＯ ＢＲＵＴＯ','1.0000'],[211,'02',"\tpeso bruto\r",'1.2500'],[215,'01','PÉSO BRUTO','1.0000'],[217,'01','peſo bruto','1.0000']] as $measure) {
    $db->table('medida_ctes')->insert(['cte_id'=>$measure[0],'cod_unidade'=>$measure[1],'tipo_medida'=>$measure[2],'quantidade_carga'=>$measure[3]]);
}

foreach (['NF-e','nf-e','NF-E','nF-e'] as $term) {
    foreach (['documentos',null] as $column) {
        cteCollationCheck('I1', 'tipo ' . $term . ' ' . ($column ?? 'global'), function () use ($term,$column) {
            backendSame([201,212], cteCollationIds(cteCollationRequest($term,$column)), 'Busca case-insensitive de NF-e e escopo do tenant');
        });
    }
}
foreach ([['mercadoria',[201]],['mercadoria agua',[201]],['Declaração',[202]],['declaração',[202]],['declaracao',[202]],['DESCRICAO',[202]],['agua emitente',[214]]] as $case) {
    foreach (['documentos',null] as $column) {
        cteCollationCheck('I1', 'texto ' . $case[0] . ' ' . ($column ?? 'global'), function () use ($case,$column) {
            backendSame($case[1], cteCollationIds(cteCollationRequest($case[0],$column)), 'Busca textual case/accent-insensitive');
        });
    }
}
foreach (['100%_!','%','_',"' OR 1=1 --"] as $term) {
    cteCollationCheck('I1', 'parâmetro literal ' . $term, function () use ($term) {
        backendSame($term === "' OR 1=1 --" ? [] : [201], cteCollationIds(cteCollationRequest($term)), 'Binding e escape preservados');
    });
}
foreach ([203,204] as $id) {
    cteCollationCheck('I2', 'descrição Unicode presente ID ' . $id, function () use ($id) {
        $cell = cteCollationParity($id);
        backendSame(1,$cell['count'],'Whitespace não ASCII é conteúdo');
        backendSame(true,in_array($id,cteCollationIds(cteCollationRequest('Outros')),true),'Tipo pesquisável por coluna');
        backendSame(true,in_array($id,cteCollationIds(cteCollationRequest('Outros',null)),true),'Tipo pesquisável global');
    });
}
foreach ([205=>"\u{200B}",206=>"\u{FEFF}"] as $id=>$issuer) {
    cteCollationCheck('I2', 'emitente Unicode é primeiro ID ' . $id, function () use ($id,$issuer) {
        backendSame($issuer,cteCollationParity($id)['items'][0]['emitente'],'Primeiro emitente não vazio estrito');
        backendSame([],cteCollationIds(cteCollationRequest('Posterior indevido ' . $id)),'Não pesquisar emitente descartado');
        backendSame([],cteCollationIds(cteCollationRequest('Posterior indevido ' . $id,null)),'Global não pesquisa emitente descartado');
    });
}
cteCollationCheck('I2','identidade binária e referência Unicode não vazia',function () {
    backendSame(6,cteCollationParity(212)['count'],'Case/acento/ignoráveis distinguem identidade');
    backendSame(1,cteCollationParity(213)['count'],'Referência Unicode malformada não desaparece');
    backendSame([213],cteCollationIds(cteCollationRequest('Emitente invisível')),'Emitente de chave Unicode pesquisável');
});
foreach ([208,209,210,211,215,217] as $id) {
    cteCollationCheck('I3', 'unidade/tipo fiscal estrito ID ' . $id, function () use ($id,$db) {
        $row = (new CteListQuery(77))->query()->where('ctes.id',$id)->firstOrFail();
        $measures = $db->table('medida_ctes')->where('cte_id',$id)->get()->map(function ($item) { return (array)$item; })->all();
        backendSame(CteListContractProbe::weight($measures),CteListPresenter::weight($row->weight_status,$row->peso_valor),'SQL segue enum ASCII do contrato');
        backendSame($id === 211 ? 'single' : 'missing',$row->weight_status,'Não converter confusable em massa');
    });
}
cteCollationCheck('I3','tpDoc fullwidth permanece desconhecido',function () {
    $cell = cteCollationParity(207);
    backendSame('Tipo desconhecido (９９)',$cell['items'][0]['tipo'],'PHP não aceita código confusável');
    backendSame('malformed',$cell['status'],'Documento identificável como inválido');
    backendSame([207],cteCollationIds(cteCollationRequest('Tipo desconhecido')),'SQL pesquisa mesmo tipo do PHP');
    backendSame([207],cteCollationIds(cteCollationRequest('tipo desconhecido',null)),'Global pesquisa tipo desconhecido');
    backendSame(false,in_array(207,cteCollationIds(cteCollationRequest('Outros')),true),'Não atribuir tipo Outros ao fullwidth');
});
cteCollationCheck('M1','somente chave_nfe em charset legado utf8mb3',function () use ($db) {
    $db->statement('ALTER TABLE ctes MODIFY chave_nfe VARCHAR(450) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL');
    try {
        backendSame([201,212],cteCollationIds(cteCollationRequest('nf-e')),'Parser com conversão charset-safe');
        cteCollationParity(212);
    } finally { $db->statement('ALTER TABLE ctes MODIFY chave_nfe VARCHAR(450) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL'); }
});
cteCollationCheck('M1','todos os intermediários legados utf8mb3',function () use ($db) {
    $tables = ['ctes','cte_documentos_anteriores','medida_ctes','contacts','cities'];
    try {
        foreach ($tables as $table) { $db->statement('ALTER TABLE ' . $table . ' CONVERT TO CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci'); }
        backendSame([201],cteCollationIds(cteCollationRequest('mercadoria agua')),'Busca em intermediários utf8mb3');
        backendSame([202],cteCollationIds(cteCollationRequest('declaracao',null)),'Label com acento em UNION legado');
        backendSame([214],cteCollationIds(cteCollationRequest('agua emitente')),'Comparação emitente legado');
        foreach ([203,204,205,206,207,212,213] as $id) { cteCollationParity($id); }
        $weight = (new CteListQuery(77))->query()->where('ctes.id',211)->firstOrFail();
        backendSame('1250.0000',$weight->peso_valor,'Peso estrito com tipo legado');
        backendSame('missing',(new CteListQuery(77))->query()->where('ctes.id',208)->firstOrFail()->weight_status,'Fullwidth legado continua ignorado');
    } finally {
        foreach ($tables as $table) { $db->statement('ALTER TABLE ' . $table . ' CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci'); }
    }
});
echo 'COLLATION_RESULT passed=' . $collationChecks . ' failed=' . count($collationFailures) . ' mysql=' . $db->selectOne('SELECT VERSION() AS version')->version . PHP_EOL;
if ($collationFailures !== []) { exit(1); }
