<?php

// Executor autônomo: dependências externas, sem bootstrap da aplicação ou leitura de .env.
$autoload = getenv('CTE_BACKEND_AUTOLOAD');
if (!$autoload || !is_file($autoload)) {
    fwrite(STDERR, "Defina CTE_BACKEND_AUTOLOAD para o autoload do executor isolado.\n");
    exit(2);
}
require $autoload;
spl_autoload_register(function ($class) {
    if (strpos($class, 'App\\') === 0) {
        $path = dirname(__DIR__) . '/app/' . str_replace('\\', '/', substr($class, 4)) . '.php';
        if (is_file($path)) { require $path; }
    }
});

$checks = 0;
function backendSame($expected, $actual, $message)
{
    if ($expected !== $actual) {
        throw new RuntimeException($message . '\nEsperado: ' . json_encode($expected, JSON_UNESCAPED_UNICODE) . '\nObtido: ' . json_encode($actual, JSON_UNESCAPED_UNICODE));
    }
}
function backendCheck($name, callable $test)
{
    global $checks;
    $test();
    ++$checks;
    echo 'PASS ' . $name . PHP_EOL;
}
function backendKey($model, $number, $series = '007')
{
    return '35261000000000000100' . $model . $series . str_pad((string) $number, 9, '0', STR_PAD_LEFT) . '1234567890';
}

try {
    backendCheck('implementação real disponível', function () {
        backendSame(true, class_exists(\App\Utils\CteListPresenter::class), 'Apresentador real da listagem não implementado');
        backendSame(true, class_exists(\App\Utils\CteListQuery::class), 'Consulta real da listagem não implementada');
    });
    require __DIR__ . '/Unit/CteListPresenterChecks.php';
    runCteListPresenterChecks();
    if (in_array('--mysql', $argv, true)) {
        require __DIR__ . '/Support/CteListBackendMysqlChecks.php';
        runCteListBackendMysqlChecks();
    }
    echo 'OK backend_checks=' . $checks . PHP_EOL;
} catch (Throwable $error) {
    fwrite(STDERR, 'FAIL ' . $error->getMessage() . PHP_EOL);
    exit(1);
}
