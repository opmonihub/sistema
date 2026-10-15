<?php

// Executor descartável SQLite em memória. Sem .env, aplicação ou conexão real.
$autoload = getenv('MDFE_TEST_AUTOLOAD') ?: dirname(__DIR__) . '/vendor/autoload.php';
if (!is_file($autoload)) { fwrite(STDERR,"Instale as dependências com composer install ou informe MDFE_TEST_AUTOLOAD.\n"); exit(2); }
require $autoload;
spl_autoload_register(function ($class) {
    if (strpos($class, 'App\\') === 0) {
        $path = dirname(__DIR__) . '/app/' . str_replace('\\', '/', substr($class, 4)) . '.php';
        if (is_file($path)) { require $path; }
    }
});
use Illuminate\Container\Container;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Support\Facades\Facade;
use App\Http\Controllers\MdfeController;

$app = new Container;
Container::setInstance($app);
$app->instance('config',new Illuminate\Config\Repository);
$capsule = new Capsule($app);
$capsule->addConnection(['driver'=>'sqlite','database'=>':memory:','prefix'=>'','foreign_key_constraints'=>true]);
$capsule->setAsGlobal(); $capsule->bootEloquent();
$app->instance('db',$capsule->getDatabaseManager());
Facade::setFacadeApplication($app);
$translator = new Illuminate\Translation\Translator(new Illuminate\Translation\ArrayLoader,'pt_BR');
$app->instance('translator',$translator);
$app->instance('validator',new Illuminate\Validation\Factory($translator,$app));
Illuminate\Http\Request::macro('validate', function ($rules) use ($app) {return $app['validator']->make($this->all(),$rules)->validate();});
$app->instance('session',new Illuminate\Session\Store('synthetic',new Illuminate\Session\ArraySessionHandler(60)));
$app['session']->put('user.business_id',1); $app['session']->put('user.id',1);
$routes = new Illuminate\Routing\RouteCollection;
$route = new Illuminate\Routing\Route(['GET'],'mdfe',function(){}); $route->name('mdfe.index'); $routes->add($route);
$app->instance('url',new Illuminate\Routing\UrlGenerator($routes,Illuminate\Http\Request::create('http://synthetic.test')));
$redirect = new Illuminate\Routing\Redirector($app['url']); $redirect->setSession($app['session']); $app->instance('redirect',$redirect);
$app->instance('log',new class { public function __call($method,$args) {} });
$db = Capsule::connection();
$db->statement('PRAGMA foreign_keys=ON');
$db->statement('CREATE TABLE business (id INTEGER PRIMARY KEY)'); $db->table('business')->insert(['id'=>1]);
$db->statement('CREATE TABLE mdves (id INTEGER PRIMARY KEY,business_id INTEGER,uf_inicio TEXT,uf_fim TEXT,encerrado INTEGER,data_inicio_viagem TEXT,carga_posterior INTEGER,veiculo_tracao_id INTEGER,veiculo_reboque1_id INTEGER,veiculo_reboque2_id INTEGER,veiculo_reboque3_id INTEGER,estado TEXT,seguradora_nome TEXT,seguradora_cnpj TEXT,numero_apolice TEXT,numero_averbacao TEXT,valor_carga TEXT,quantidade_carga TEXT,info_complementar TEXT,info_adicional_fisco TEXT,cnpj_contratante TEXT,mdfe_numero INTEGER,condutor_nome TEXT,condutor_cpf TEXT,tp_emit TEXT,tp_transp TEXT,lac_rodo TEXT,chave TEXT,protocolo TEXT,produto_pred_nome TEXT,produto_pred_ncm TEXT,produto_pred_cod_barras TEXT,cep_carrega TEXT,cep_descarrega TEXT,tp_carga TEXT,location_id INTEGER,responsavel_pag_nome TEXT,responsavel_pag_cpf_cnpj TEXT,valor_frete TEXT,tipo_componente TEXT,valor_contrato TEXT,indicador_pagamento TEXT,numero_parcela TEXT,data_vencimento TEXT,valor_parcela TEXT,codigo_banco TEXT,codigo_agencia TEXT,cnpj_ipef TEXT,chave_pix TEXT,created_at TEXT,updated_at TEXT)');
foreach (['municipio_carregamentos'=>'cidade_id INTEGER','percursos'=>'uf TEXT','ciots'=>'cpf_cnpj TEXT,codigo TEXT','vale_pedagios'=>'cnpj_fornecedor TEXT,cnpj_fornecedor_pagador TEXT,numero_compra TEXT,valor TEXT'] as $table=>$columns) {
    $db->statement('CREATE TABLE '.$table.' (id INTEGER PRIMARY KEY AUTOINCREMENT,mdfe_id INTEGER,'.$columns.',created_at TEXT,updated_at TEXT)');
}
$db->statement('CREATE TABLE info_descargas (id INTEGER PRIMARY KEY AUTOINCREMENT,mdfe_id INTEGER,tp_unid_transp INTEGER,id_unid_transp TEXT,quantidade_rateio TEXT,cidade_id INTEGER,created_at TEXT,updated_at TEXT)');
foreach (['n_fe_descargas'=>'chave TEXT,seg_cod_barras TEXT','c_te_descargas'=>'chave TEXT,seg_cod_barras TEXT','unidade_cargas'=>'id_unidade_carga TEXT,quantidade_rateio TEXT','lacre_transportes'=>'numero TEXT','lacre_unidade_cargas'=>'numero TEXT'] as $table=>$columns) {
    $db->statement('CREATE TABLE '.$table.' (id INTEGER PRIMARY KEY AUTOINCREMENT,info_id INTEGER REFERENCES info_descargas(id) ON DELETE CASCADE,'.$columns.',created_at TEXT,updated_at TEXT)');
}
foreach ([749=>1,750=>2] as $id=>$business) {
    $db->table('mdves')->insert(['id'=>$id,'business_id'=>$business,'estado'=>'NOVO','info_complementar'=>'original']);
    $info=$db->table('info_descargas')->insertGetId(['mdfe_id'=>$id,'cidade_id'=>10]);
    $db->table('c_te_descargas')->insert(['info_id'=>$info,'chave'=>str_repeat('2',44),'seg_cod_barras'=>'']);
}
$controller=(new ReflectionClass(MdfeController::class))->newInstanceWithoutConstructor();
function sameMdfe($expected,$actual,$message) { if($expected!==$actual) {throw new RuntimeException($message.' esperado='.json_encode($expected).' obtido='.json_encode($actual));} }
function snapshotMdfe() {
    $result=[];
    foreach (['mdves','info_descargas','n_fe_descargas','c_te_descargas','unidade_cargas','lacre_transportes','lacre_unidade_cargas','municipio_carregamentos','percursos','ciots','vale_pedagios'] as $table) {
        $result[$table]=Capsule::table($table)->orderBy('id')->get()->map(function($r){return (array)$r;})->all();
    }
    return $result;
}
function fiscalKeyMdfe($model='57') {
    $body='35261000000000000100'.$model.'001000000001112345678';
    $sum=0; $weight=2;
    for($i=42;$i>=0;--$i) {$sum+=(int)$body[$i]*$weight;$weight=$weight===9?2:$weight+1;}
    $digit=11-$sum%11;
    return $body.($digit>=10?0:$digit);
}
function rowMdfe($key=null) {
    return ['tipo_unidade_transporte'=>1,'id_unidade_transporte'=>'ABC1234','qtd_rateio_transporte'=>'1.00','id_unidade_carga'=>'CARGA1','qtd_rateio_unidade'=>'1.00','chave_nfe'=>'','segunda_nfe'=>'','chave_cte'=>$key ?? fiscalKeyMdfe(),'segunda_cte'=>'','municipio_descarregamento'=>10,'lacres_transporte'=>['LT1'],'lacres_unidade_carga'=>['LU1']];
}
function updateMdfe($descargas,$id=749,$hiddenId=null) {
    global $controller,$app;
    $request=Illuminate\Http\Request::create('/mdfe/'.$id,'PUT',[
        'mdfe_id'=>$hiddenId ?? $id,'select_location_id'=>1,'uf_inicio'=>'SP','uf_fim'=>'RJ','data_inicio_viagem'=>'15/10/2026','valor_carga'=>'100.00','quantidade_carga'=>'1.0000','info_complementar'=>'alterado',
        'descargas'=>is_string($descargas) ? $descargas : json_encode($descargas),
        'municipios_descarregamentos'=>'[10]','percurso'=>'[]','ciots'=>'[]','vales'=>'[]',
    ]);
    $request->setLaravelSession($app['session']); $app->instance('request',$request);
    return $controller->update($request,$id);
}
$checks=0;
try {
    foreach (['[]','','{json inválido','null','{}',json_encode([rowMdfe(),null]),json_encode([['chave_cte'=>str_repeat('2',44)]])] as $payload) {
        $before=snapshotMdfe();
        ob_start();
        try {updateMdfe($payload);} catch(Throwable $expected) {}
        ob_end_clean();
        sameMdfe($before,snapshotMdfe(),'Payload vazio/inválido nunca altera nem exclui documentos: '.$payload);
        sameMdfe(0,$db->transactionLevel(),'Sem transação pendente');
        ++$checks;
    }
    echo "PASS payloads inválidos preservam todas as tabelas\n";
    $before=snapshotMdfe();
    foreach ([['municipios_descarregamentos'=>'null'],['municipios_descarregamentos'=>'[]'],['percurso'=>'{}'],['percurso'=>'["XX"]'],['ciots'=>'[null]'],['vales'=>'[{}]']] as $invalidList) {
        $invalidRequest=Illuminate\Http\Request::create('/mdfe/749','PUT',array_merge([
            'descargas'=>json_encode([rowMdfe()]),'select_location_id'=>1,'uf_inicio'=>'SP','uf_fim'=>'RJ',
            'data_inicio_viagem'=>'15/10/2026','valor_carga'=>'100.00','quantidade_carga'=>'1.0000',
            'municipios_descarregamentos'=>'[10]','percurso'=>'[]','ciots'=>'[]','vales'=>'[]',
        ],$invalidList));
        $invalidRequest->setLaravelSession($app['session']);$app->instance('request',$invalidRequest);
        $controller->update($invalidRequest,749);
        sameMdfe($before,snapshotMdfe(),'Lista auxiliar inválida nunca exclui vínculos ou salva parcialmente');
        sameMdfe(false,$app['session']->get('status')['success'],'Lista auxiliar inválida retorna validação');
        ++$checks;
    }
    try {updateMdfe([rowMdfe()],750);} catch(Throwable $expected) {}
    sameMdfe($before,snapshotMdfe(),'Empresa externa não é alterada'); ++$checks;
    try {updateMdfe([rowMdfe()],749,750);} catch(Throwable $expected) {}
    sameMdfe($before['mdves'][1],snapshotMdfe()['mdves'][1],'ID oculto não direciona update para outra empresa'); ++$checks;
    // Restaura cenário original via transação de teste antes de cada falha injetada.
    $db->beginTransaction();
    $before=snapshotMdfe();
    $db->statement("CREATE TRIGGER fail_document BEFORE INSERT ON c_te_descargas BEGIN SELECT RAISE(ABORT,'synthetic failure'); END");
    try {updateMdfe([rowMdfe()]);} catch(Throwable $expected) {}
    sameMdfe($before,snapshotMdfe(),'Falha após exclusão faz rollback dos vínculos e manifesto');
    sameMdfe(1,$db->transactionLevel(),'Rollback respeita transação externa');
    $db->rollBack(); ++$checks;
    foreach (['D'.substr(fiscalKeyMdfe(),1),substr(fiscalKeyMdfe(),0,43),fiscalKeyMdfe().'0',fiscalKeyMdfe('55'),substr(fiscalKeyMdfe(),0,43).((int)substr(fiscalKeyMdfe(),-1)===9?'0':'9')] as $invalidKey) {
        $before=snapshotMdfe();
        updateMdfe([rowMdfe($invalidKey)]);
        sameMdfe($before,snapshotMdfe(),'Chave inválida bloqueada antes de alterar vínculos');
        ++$checks;
    }
    foreach ([rowMdfe('D'.substr(fiscalKeyMdfe(),1)),rowMdfe(substr(fiscalKeyMdfe(),0,43)),rowMdfe(fiscalKeyMdfe('55'))] as $invalid) {
        $before=snapshotMdfe();
        $request=Illuminate\Http\Request::create('/mdfe','POST',['descargas'=>json_encode([$invalid])]);
        $request->setLaravelSession($app['session']);$app->instance('request',$request);
        $controller->store($request);
        sameMdfe($before,snapshotMdfe(),'Criação valida documentos antes de qualquer INSERT');
        sameMdfe(false,$app['session']->get('status')['success'],'Mensagem de validação na criação');
        ++$checks;
    }
    $pending = [array_merge(rowMdfe(), ['qtd_rateio_transporte'=>'1,50','qtd_rateio_unidade'=>'2,25'])];
    updateMdfe($pending);
    sameMdfe(true,$app['session']->get('status')['success'],'Rateios brasileiros são normalizados antes da validação');
    sameMdfe('1.50',$db->table('info_descargas')->where('mdfe_id',749)->value('quantidade_rateio'),'Rateio decimal persistido');
    sameMdfe('2.25',$db->table('unidade_cargas')->join('info_descargas','info_descargas.id','=','unidade_cargas.info_id')->where('mdfe_id',749)->value('unidade_cargas.quantidade_rateio'),'Rateio da unidade normalizado');
    ++$checks;
    $invalidPending = [rowMdfe('D'.substr(fiscalKeyMdfe(),1))];
    updateMdfe($invalidPending);
    sameMdfe(json_encode($invalidPending),$app['session']->getOldInput('descargas'),'Validação preserva a lista enviada para correção');
    ++$checks;
    $response=updateMdfe([rowMdfe(fiscalKeyMdfe()),array_merge(rowMdfe(),['chave_cte'=>'','chave_nfe'=>fiscalKeyMdfe('55')])]);
    sameMdfe(true,$app['session']->get('status')['success'],'Update válido retorna sucesso');
    sameMdfe(2,$db->table('info_descargas')->where('mdfe_id',749)->count(),'Recria todas descargas');
    $keys=$db->table('c_te_descargas')->join('info_descargas','info_descargas.id','=','c_te_descargas.info_id')->where('mdfe_id',749)->pluck('chave')->all();
    sameMdfe([fiscalKeyMdfe()],$keys,'Chave válida salva sem alteração');
    sameMdfe(0,$db->transactionLevel(),'Update encerra transação'); ++$checks;
    $storeInput=[
        'descargas'=>json_encode([rowMdfe()]),'select_location_id'=>1,'uf_inicio'=>'SP','uf_fim'=>'RJ',
        'data_inicio_viagem'=>'15/10/2026','valor_carga'=>'100.00','quantidade_carga'=>'1.0000',
        'municipios_descarregamentos'=>'[10]','percurso'=>'[]','ciots'=>'[]','vales'=>'[]',
    ];
    $storeRequest=function() use ($storeInput,$app) {
        $request=Illuminate\Http\Request::create('/mdfe','POST',$storeInput);
        $request->setLaravelSession($app['session']);$app->instance('request',$request);return $request;
    };
    $before=snapshotMdfe();
    $db->statement("CREATE TRIGGER fail_store_document BEFORE INSERT ON c_te_descargas BEGIN SELECT RAISE(ABORT,'synthetic store failure'); END");
    $controller->store($storeRequest());
    sameMdfe($before,snapshotMdfe(),'Criação interrompida reverte manifesto e todos os vínculos');
    sameMdfe(false,$app['session']->get('status')['success'],'Criação com erro retorna falha');
    sameMdfe(0,$db->transactionLevel(),'Store falho não deixa transação aberta');
    $db->statement('DROP TRIGGER fail_store_document');++$checks;
    $controller->store($storeRequest());
    sameMdfe(3,$db->table('mdves')->count(),'Criação válida mantém manifesto e documentos');
    sameMdfe(3,$db->table('c_te_descargas')->count(),'Criação válida vincula CT-e');
    ++$checks;
    echo 'OK mdfe_backend_checks='.$checks.PHP_EOL;
} catch(Throwable $error) {fwrite(STDERR,'FAIL '.$error->getMessage().PHP_EOL);exit(1);}
