<?php

// Protocolo JSON por stdin/stdout, apenas no executor descartável (sem bootstrap/.env).
if (getenv('CTE_QUERY_ISOLATED') !== '1') { throw new RuntimeException('Executor sintético obrigatório'); }
$argv = [__FILE__, '--mysql'];
ob_start();
require dirname(__DIR__) . '/cte-list-backend.php';
$backendOutput = ob_get_clean();
$app = \Illuminate\Container\Container::getInstance();
$db = \Illuminate\Database\Capsule\Manager::connection();
$controller = (new ReflectionClass(\App\Http\Controllers\CteController::class))->newInstanceWithoutConstructor();

// Acrescenta entradas difíceis de exportar ao schema sintético já validado.
$db->table('ctes')->where('id', 2)->update(['descOutros' => "<img src=x onerror=alert(1)> & carga\nsegunda linha"]);
$db->table('contacts')->where('id', 5)->update(['name' => "=HYPERLINK(\"x\")\n<svg onload=alert(1)>"]);
$db->table('ctes')->where('id', 6)->update(['remetente_id'=>5, 'destinatario_id'=>5, 'produto_predominante'=>'=1+1']);
$db->table('ctes')->where('id', 7)->update(['valor_transporte'=>'1234567.89','valor_receber'=>'1234.50','valor_carga'=>'98765.43']);
$db->table('cities')->where('id', 1)->update(['nome' => "Cidade <b>literal</b>\nLinha"]);
foreach ([1,2,3,4,6,7,8,9,10,11,12,13,14,15] as $id) { $db->table('ctes')->where('id',$id)->update(['cte_numero'=>1000+$id]); }

// BladeCompiler/Factory reais: só o layout externo é uma casca sintética.
$files = new \Illuminate\Filesystem\Filesystem;
$files->makeDirectory('/tmp/cte-integrated-views/layouts',0755,true,true);
$files->makeDirectory('/tmp/cte-integrated-compiled',0755,true,true);
$files->put('/tmp/cte-integrated-views/layouts/app.blade.php', '<!doctype html><html><head><title>@yield("title")</title></head><body>@yield("content")@yield("javascript")</body></html>');
$app->instance('files',$files);
$app->instance('events',new \Illuminate\Events\Dispatcher($app));
$app['config']->set('view.paths',['/tmp/cte-integrated-views','/source/resources/views']);
$app['config']->set('view.compiled','/tmp/cte-integrated-compiled');
(new \Illuminate\View\ViewServiceProvider($app))->register();
$loader = new \Illuminate\Translation\ArrayLoader;
$loader->addMessages('pt_BR','messages',['add'=>'Adicionar']);
$app->instance('translator',new \Illuminate\Translation\Translator($loader,'pt_BR'));
$permissions = ['view'=>true,'create'=>true];
$gate = new \Illuminate\Auth\Access\Gate($app,function () { return (object)['id'=>1]; });
foreach (['view','create'] as $permission) {
    $gate->define('user.'.$permission,function () use (&$permissions,$permission) { return $permissions[$permission]; });
}
$app->instance(\Illuminate\Contracts\Auth\Access\Gate::class,$gate);
$route = new \Illuminate\Routing\Route(['GET'],'cte/create',function () {});
$route->name('cte.create');
$routeCollection = new ReflectionProperty($app['url'],'routes');
$routeCollection->setAccessible(true);
$routeCollection->getValue($app['url'])->add($route);
$html = null;
foreach ([[true,true],[true,false],[false,true],[false,false]] as $combination) {
    $permissions = ['view'=>$combination[0],'create'=>$combination[1]];
    $rendered = $app['view']->make('cte.index',['asset_v'=>'synthetic'])->render();
    $dom = new DOMDocument;
    @$dom->loadHTML($rendered);
    $xpath = new DOMXPath($dom);
    backendSame($permissions['view'] ? 1 : 0,$xpath->query('//*[@id="users_table"]')->length,'Blade respeita user.view');
    backendSame($permissions['view'] ? 15 : 0,$xpath->query('//*[@id="users_table"]/thead/tr/th')->length,'Blade tem 15 colunas');
    backendSame($permissions['view'] ? 1 : 0,$xpath->query('//*[@id="cte_documents_modal"]')->length,'Modal único condicionado à permissão');
    if ($combination === [true,true]) { $html = $rendered; }
}
$permissions = ['view'=>true,'create'=>true];
echo json_encode(['ready'=>true,'html'=>$html,'backendOutput'=>$backendOutput,'bladeChecks'=>4],JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR).PHP_EOL;
while (($line = fgets(STDIN)) !== false) {
    try {
        $input = json_decode($line,true,512,JSON_THROW_ON_ERROR);
        $request = \Illuminate\Http\Request::create('/cte','GET',$input,[],[],['HTTP_X_REQUESTED_WITH'=>'XMLHttpRequest']);
        $request->setLaravelSession($app['session']);
        $app->instance('request',$request);
        $app->forgetInstance('datatables.request');
        $response = $controller->index()->getData(true);
        echo json_encode(['response'=>$response],JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR).PHP_EOL;
    } catch (Throwable $error) {
        echo json_encode(['error'=>get_class($error).': '.$error->getMessage().' at '.$error->getFile().':'.$error->getLine()],JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR).PHP_EOL;
    }
}
