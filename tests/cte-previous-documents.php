<?php

// Executor sem Laravel, .env, banco ou rede: exercita o controller real antes da persistência.
namespace Illuminate\Http {
    class Request {
        private $data;
        public function __construct(array $data) { $this->data = $data; }
        public function __get($key) { return $this->data[$key] ?? null; }
        public function __isset($key) { return isset($this->data[$key]); }
        public function session() { return $this; }
        public function get($key) { return 1; }
    }
}
namespace App\Http\Controllers { class Controller {} }
namespace App\Utils { class ModuleUtil {} }
namespace App\Models {
    class WriteTrap {
        public static $calls = 0;
        public static function __callStatic($method, $args) {
            ++self::$calls;
            throw new \RuntimeException('Acesso inesperado à persistência: ' . $method);
        }
    }
    class Business extends WriteTrap {}
    class Cte extends WriteTrap {}
}
namespace {
    class RedirectProbe {
        public $target;
        public $input = false;
        public $status;
        public function __construct($target) { $this->target = $target; }
        public function back() { $this->target = 'back'; return $this; }
        public function withInput() { $this->input = true; return $this; }
        public function with($key, $value) { $this->$key = $value; return $this; }
    }
    function redirect($target = null) { return new RedirectProbe($target); }
    function request() { return new \Illuminate\Http\Request([]); }
    function same($expected, $actual, $message) {
        if ($expected !== $actual) { throw new \RuntimeException($message . ': ' . var_export($actual, true)); }
    }
    function keyFor($uf = '35', $model = '57', $number = '000000123') {
        $base = $uf . '2610' . '12345678000195' . $model . '001' . $number . '1' . '12345678';
        $sum = 0;
        for ($i = 42, $weight = 2; $i >= 0; --$i) {
            $sum += (int) $base[$i] * $weight;
            $weight = $weight === 9 ? 2 : $weight + 1;
        }
        $digit = 11 - ($sum % 11);
        return $base . ($digit >= 10 ? 0 : $digit);
    }
    require __DIR__ . '/../app/Utils/CtePreviousDocumentValidator.php';
    require __DIR__ . '/../app/Http/Controllers/CteController.php';
    $controller = new \App\Http\Controllers\CteController(new \App\Utils\ModuleUtil());
    $valid = ['chave' => keyFor(), 'ie' => '12345', 'cnpj' => '<script>', 'uf' => 'ZZ'];
    $badDigit = substr(keyFor(), 0, 43) . ((int) substr(keyFor(), -1) + 1) % 10;
    $invalid = [
        ['documentos_anteriores' => '{'],
        ['documentos_anteriores' => '{}'],
        ['documentos_anteriores' => 'null'],
        ['documentos_anteriores' => 'true'],
        ['documentos_anteriores' => '{"0":' . json_encode($valid) . '}'],
        ['documentos_anteriores' => '[null]'],
        ['documentos_anteriores' => '["doc"]'],
        ['documentos_anteriores' => json_encode([['chave' => [], 'ie' => '12345']])],
        ['documentos_anteriores' => json_encode([['chave' => keyFor(), 'ie' => []]])],
        ['documentos_anteriores' => json_encode([['chave' => $badDigit, 'ie' => '12345']])],
        ['documentos_anteriores' => json_encode([['chave' => keyFor('35', '55'), 'ie' => '12345']])],
        ['documentos_anteriores' => json_encode([['chave' => keyFor('99'), 'ie' => '12345']])],
        ['documentos_anteriores' => json_encode([['chave' => str_repeat('a', 44), 'ie' => '12345']])],
        ['documentos_anteriores' => json_encode([['chave' => keyFor(), 'ie' => '<img src=x>']])],
        ['documentos_anteriores' => json_encode([['chave' => keyFor(), 'ie' => '1']])],
        ['documentos_anteriores' => json_encode([['chave' => keyFor(), 'ie' => str_repeat('1', 15)]])],
        ['documentos_anteriores' => json_encode([['chave' => keyFor()]])],
        ['tipo_servico' => '1'], ['tipo_servico' => '2'], ['tipo_servico' => '3'],
        ['tipo_servico' => '2', 'tp_cte' => '3'],
        ['medidas' => '{'], ['medidas' => '{}'], ['medidas' => '[{}]'],
        ['componentes' => '{'], ['componentes' => '{}'], ['componentes' => '[{}]'],
    ];
    try {
        foreach (['store', 'update'] as $method) {
            foreach ($invalid as $index => $data) {
                \App\Models\WriteTrap::$calls = 0;
                $response = $controller->$method(new \Illuminate\Http\Request($data), 123);
                same(0, \App\Models\WriteTrap::$calls, "$method caso $index deve validar antes de acessar modelos");
                same('back', $response->target, "$method caso $index deve voltar");
                same(true, $response->input, "$method caso $index deve preservar input");
                same(0, $response->status['success'], "$method caso $index deve informar erro de validação");
                if (empty($response->status['msg'])) { throw new \RuntimeException('Mensagem ausente'); }
            }
            echo 'PASS ' . $method . ': ' . count($invalid) . ' rejeições antes de persistência, com input/status' . PHP_EOL;
        }
        $class = \App\Utils\CtePreviousDocumentValidator::class;
        foreach (['0', '1', '2', '3', '4'] as $type) {
            $docs = $class::validate(json_encode([$valid]), $type, '0');
            same([['chave' => keyFor(), 'cnpj' => '12345678000195', 'uf' => 'SP', 'ie' => '12345']], $docs, 'Derivar identidade exclusivamente da chave');
        }
        foreach (['0', '4'] as $type) { same([], $class::validate('', $type, '0'), 'Tipos opcionais sem documentos'); }
        foreach (['0', '1', '2', '3', '4'] as $type) { same([], $class::validate('[]', $type, '1'), 'Complemento dispensa documentos anteriores'); }
        foreach (['0', '3'] as $tp) {
            foreach (['1', '2', '3'] as $type) {
                try { $class::validate('[]', $type, $tp); throw new \LogicException('Obrigatoriedade ausente'); }
                catch (\InvalidArgumentException $expected) {}
            }
        }
        foreach (['12', str_repeat('1', 14), ' ISENTO '] as $ie) {
            $docs = $class::validate(json_encode([array_merge($valid, ['ie' => $ie])]), '2', '3');
            same(trim($ie), $docs[0]['ie'], 'Limites da IE e ISENTO');
        }
        same(27, count($invalid), 'Número de casos inválidos');
        echo 'PASS validação real: identidade derivada, DV/modelo/UF/IE, tipos 0/4, complemento e substituição' . PHP_EOL;
    } catch (\Throwable $error) {
        fwrite(STDERR, 'FAIL ' . $error->getMessage() . PHP_EOL);
        exit(1);
    }
}
