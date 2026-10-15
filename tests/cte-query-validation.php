<?php

// Runner autônomo: não inicializa Laravel, não carrega .env e usa somente dados sintéticos.
require __DIR__ . '/Support/CteListContractProbe.php';

use Tests\Support\CteListContractProbe as Probe;

function same($expected, $actual, $message)
{
    if ($expected !== $actual) {
        throw new RuntimeException($message . "\nEsperado: " . json_encode($expected, JSON_UNESCAPED_UNICODE) . "\nObtido: " . json_encode($actual, JSON_UNESCAPED_UNICODE));
    }
}

function syntheticKey($model, $number, $series = '007', $suffix = '1234567890')
{
    return '35261000000000000100' . $model . $series . str_pad((string) $number, 9, '0', STR_PAD_LEFT) . $suffix;
}

$checks = 0;
function check($name, callable $test)
{
    global $checks;
    $test();
    ++$checks;
    echo 'PASS ' . $name . PHP_EOL;
}

try {
    $key = syntheticKey('55', 42);
    check('normalização e deduplicação por categoria/chave', function () use ($key) {
        $items = Probe::keys(' ; ' . $key . ', ' . substr($key, 0, 22) . " \t\n" . substr($key, 22) . ';;', 'nfe_carga');
        same(1, count($items), 'A mesma chave normalizada deve aparecer uma vez');
        same('42', $items[0]['numero'], 'Número derivado da posição fiscal');
        same('7', $items[0]['serie'], 'Série derivada da posição fiscal');
        same('valid', $items[0]['status'], 'Chave estruturalmente válida');
    });
    check('malformadas não desaparecem nem inventam número', function () use ($key) {
        $items = Probe::keys('invalida;invalida;123;' . syntheticKey('57', 9) . ';' . str_replace('0', '０', $key), 'nfe_carga');
        same(4, count($items), 'Duplicata malformada deduplicada, demais preservadas');
        foreach ($items as $item) {
            same('malformed', $item['status'], 'Modelo incorreto e caracteres não ASCII são malformados');
            same(null, $item['numero'], 'Não derivar número de referência inválida');
            same(null, $item['serie'], 'Não derivar série de referência inválida');
        }
    });
    check('ordinais cobrem separadores vazios e limite de 450 caracteres', function () use ($key) {
        same(1, count(Probe::keys(str_repeat(';', 406) . $key, 'nfe_carga')), 'Chave na posição 407 não pode sumir');
        same([], Probe::keys(str_repeat(',', 450), 'nfe_carga'), '451 segmentos vazios');
        try {
            Probe::keys(str_repeat(';', 407) . $key, 'nfe_carga');
            throw new RuntimeException('Deveria rejeitar schema acima de 450 caracteres');
        } catch (LengthException $expected) {
        }
    });
    check('mesmo número não deduplica chaves distintas e whitespace não ASCII é inválido', function () use ($key) {
        same(2, count(Probe::keys($key . ';' . syntheticKey('55', 42, '008'), 'nfe_carga')), 'Identidade não é o número');
        same('malformed', Probe::keys(substr($key, 0, 20) . "\u{00A0}" . substr($key, 20), 'nfe_carga')[0]['status'], 'NBSP não é removido silenciosamente');
        same(1, count(Probe::keys('ref;anterior,ruim', 'cte_anterior')), 'Cada registro anterior é uma referência, não lista serializada');
        same('malformed', Probe::keys('ref;anterior,ruim', 'cte_anterior')[0]['status'], 'Delimitador em chave anterior é preservado');
    });
    check('contrato usa as quatro colunas com nomes fixos', function () {
        same(['documentos', 'cidade_destino', 'peso_kg', 'tomador_servico'], Probe::columnNames(), 'Nomes para DataTables data/name');
    });
    check('outros documentos exigem conteúdo além de tpDoc', function () {
        same([], Probe::other(['tpDoc' => '00', 'nDoc' => 0, 'vDocFisc' => '0.00', 'descOutros' => '  ']), 'Seleção padrão não é documento');
        same(1, count(Probe::other(['tpDoc' => '99', 'nDoc' => 0, 'vDocFisc' => '1.00', 'descOutros' => ''])), 'Valor efetivo caracteriza documento');
        same(1, count(Probe::other(['tpDoc' => '00', 'nDoc' => 12, 'vDocFisc' => '0.00', 'descOutros' => ''])), 'Número informado caracteriza documento');
    });
    check('descrição zero e emitente anterior não desaparecem', function () {
        same('0', Probe::other(['tpDoc'=>'99','nDoc'=>0,'vDocFisc'=>'0.00','descOutros'=>'0'])[0]['descricao'], 'Zero textual é conteúdo informado');
        $key = syntheticKey('57', 11);
        $items = Probe::previous([
            ['id'=>9,'chave_cte'=>$key,'nome_emitente'=>'Segundo'],
            ['id'=>1,'chave_cte'=>$key,'nome_emitente'=>null],
            ['id'=>2,'chave_cte'=>$key,'nome_emitente'=>'Primeiro informado'],
        ]);
        same(1, count($items), 'Mesma chave anterior aparece uma vez');
        same('Primeiro informado', $items[0]['emitente'], 'Menor ID com emitente não vazio');
        same(true, strpos($items[0]['export'], 'Primeiro informado') !== false, 'Emitente disponível na exportação');
    });
    check('peso mantém precisão e não agrega medidas ambíguas', function () {
        $kg = Probe::weight([['tipo_medida' => 'PESO BRUTO', 'cod_unidade' => '01', 'quantidade_carga' => '1234.5678']]);
        same('1234.5678', $kg['raw'], 'Decimal sem conversão para float');
        same('1.234,5678', $kg['display'], 'Quatro casas do schema preservadas');
        $ton = Probe::weight([['tipo_medida' => 'PESO BRUTO', 'cod_unidade' => '02', 'quantidade_carga' => '1.2345']]);
        same('1234.5000', $ton['raw'], 'TON para KG com decimal exato');
        same('multiple', Probe::weight(array_fill(0, 2, ['tipo_medida' => 'PESO BRUTO', 'cod_unidade' => '01', 'quantidade_carga' => '2.0000']))['status'], 'Não somar pesos');
        same('missing', Probe::weight([['tipo_medida' => 'PESO BRUTO', 'cod_unidade' => '00', 'quantidade_carga' => '9.0000']])['status'], 'Volume não vira massa');
        same('invalid', Probe::weight([['tipo_medida' => 'PESO BRUTO', 'cod_unidade' => '01', 'quantidade_carga' => '-1.0000']])['status'], 'Negativo identificável');
        same('1234.5678', Probe::numericSearch('1.234,5678'), 'Busca pt-BR');
        same(null, Probe::numericSearch('1234.5678'), 'Decimal técnico não é entrada humana pt-BR');
        same('1234', Probe::numericSearch('1.234'), 'Ponto agrupa milhares');
        same('1.234', Probe::numericSearch('1,234'), 'Vírgula separa decimais');
        same(null, Probe::numericSearch('1,234.5'), 'Mistura ambígua não aceita');
    });
    check('tomador valida papel, tenant e soft delete', function () {
        $contact = ['business_id' => 1, 'deleted_at' => null, 'name' => '<script>texto</script>'];
        same('invalid', Probe::taker(null, $contact, 1)['status'], 'Null não vira remetente');
        same('invalid', Probe::taker(8, $contact, 1)['status'], 'Código desconhecido');
        foreach (['Remetente', 'Expedidor', 'Recebedor', 'Destinatário', 'Outros'] as $code => $role) {
            same($role, Probe::taker($code, $contact, 1)['raw']['papel'], 'Papel fiscal ' . $code);
        }
        same('missing_contact', Probe::taker(0, $contact, 2)['status'], 'Contato externo não é exibido');
        $contact['deleted_at'] = '2026-01-01';
        same('missing_contact', Probe::taker(4, $contact, 1)['status'], 'Contato excluído não é exibido');
    });
    check('cidade e JSON mínimo preservam textos sem HTML interpolado', function () {
        same('missing', Probe::city(null)['status'], 'Cidade inexistente');
        $row = [
            'documentos' => Probe::documentCell(Probe::other(['tpDoc'=>'99','descOutros'=>'<script>texto</script>','nDoc'=>1,'vDocFisc'=>'0.00'])),
            'cidade_destino' => Probe::city(['nome'=>'Cidade fim','uf'=>'SP']),
            'peso_kg' => Probe::weight([]),
            'tomador_servico' => Probe::taker(0, null, 1),
        ];
        same(Probe::columnNames(), array_keys($row), 'Envelope das quatro colunas');
        foreach ($row as $cell) {
            foreach (['display','status','raw','export'] as $field) {
                same(true, array_key_exists($field, $cell), 'Campo comum ' . $field);
            }
        }
        same('Cidade fim / SP', $row['cidade_destino']['display'], 'Cidade + UF');
        same(true, strpos($row['documentos']['items'][0]['descricao'], '<script>') !== false, 'Valor original: frontend deve inserir via textContent');
        if (in_array('--contract', $GLOBALS['argv'], true)) {
            echo 'CONTRACT ' . json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
        }
    });
    check('envelope de documentos mantém array/status/raw/export', function () use ($key) {
        $cell = Probe::documentCell(Probe::keys($key . ';ruim', 'nfe_carga'));
        same(2, $cell['count'], 'Contagem do mesmo array usado no modal');
        same('mixed', $cell['status'], 'Referência malformada explícita');
        same('2 documentos', $cell['display'], 'Resumo compacto');
        same(2, count($cell['items']), 'Dados locais do modal');
        same(true, strpos($cell['export'], 'ruim') !== false, 'Exportação preserva referência malformada');
    });
    if (in_array('--mysql', $argv, true)) {
        require __DIR__ . '/Support/CteQuerySqlProbe.php';
        require __DIR__ . '/Support/CteQueryMysqlChecks.php';
        runMysqlChecks();
    }
    echo 'OK checks=' . $checks . PHP_EOL;
} catch (Throwable $error) {
    fwrite(STDERR, 'FAIL ' . $error->getMessage() . PHP_EOL);
    exit(1);
}
