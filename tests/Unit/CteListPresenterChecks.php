<?php

use App\Utils\CteListPresenter as Presenter;

function runCteListPresenterChecks()
{
    backendCheck('documentos reais normalizam ASCII e preservam identidade binária', function () {
        $key = backendKey('55', 42);
        $cte = ['chave_nfe' => ' ;' . $key . ',' . substr($key, 0, 20) . " \t\n" . substr($key, 20) . ';RUIM;ruim;' . backendKey('55', 42, '008')];
        $cell = Presenter::documents($cte, []);
        backendSame(4, $cell['count'], 'Duas chaves distintas e duas malformadas');
        backendSame('mixed', $cell['status'], 'Referências inválidas explícitas');
        backendSame(['42', null, null, '42'], array_column($cell['items'], 'numero'), 'Número só para modelo/estrutura válidos');
        backendSame(['7', null, null, '8'], array_column($cell['items'], 'serie'), 'Série fiscal sem zeros artificiais');
        backendSame('4 documentos', $cell['display'], 'Resumo modal');
    });
    backendCheck('ordinais e chave de categoria anterior têm contrato consistente', function () {
        $cell = Presenter::documents(['chave_nfe'=>str_repeat(';', 406) . backendKey('55', 99)], [['id'=>1,'chave_cte'=>'ref;anterior,ruim']]);
        backendSame(2, $cell['count'], 'Posição 407 e referência anterior indivisível');
        backendSame('99', $cell['items'][0]['numero'], 'Última posição válida');
        backendSame('ref;anterior,ruim', $cell['items'][1]['chave'], 'Não dividir documentos anteriores');
        backendSame(null, $cell['items'][1]['numero'], 'Não inventar número inválido');
        backendSame('malformed', Presenter::documents(['chave_nfe'=>substr(backendKey('55', 1),0,20) . "\u{00A0}" . substr(backendKey('55', 1),20)], [])['status'], 'Não remover NBSP');
        try {
            Presenter::documents(['chave_nfe'=>str_repeat(';',407) . backendKey('55',1)], []);
            throw new RuntimeException('Campo maior deve falhar explicitamente');
        } catch (LengthException $expected) {}
    });
    backendCheck('outro documento exige conteúdo e emitente deduplicado permanece exportável', function () {
        backendSame(0, Presenter::documents(['tpDoc'=>'00','nDoc'=>0,'vDocFisc'=>'0.00','descOutros'=>" \t"], [])['count'], 'tpDoc padrão sozinho não conta');
        foreach ([['descOutros'=>'0'],['vDocFisc'=>'1.00'],['nDoc'=>12]] as $content) {
            backendSame(1, Presenter::documents(array_merge(['tpDoc'=>'99'], $content), [])['count'], 'Conteúdo caracteriza documento');
        }
        $key = backendKey('57', 11);
        $cell = Presenter::documents(['chave_nfe'=>backendKey('55',11),'tpDoc'=>'99','descOutros'=>'<script>texto</script>'], [
            ['id'=>9,'chave_cte'=>$key,'nome_emitente'=>'Segundo'],
            ['id'=>1,'chave_cte'=>$key,'nome_emitente'=>null],
            ['id'=>2,'chave_cte'=>$key,'nome_emitente'=>'Primeiro informado'],
        ]);
        backendSame(3, $cell['count'], 'Categoria + chave define identidade');
        backendSame('Primeiro informado', $cell['items'][2]['emitente'], 'Menor ID informado');
        backendSame('<script>texto</script>', $cell['items'][1]['descricao'], 'Texto original sem HTML gerado');
        backendSame(true, strpos($cell['export'], 'Primeiro informado') !== false, 'Emitente exportável');
        foreach ($cell['items'] as $item) {
            backendSame(['categoria','tipo','numero','serie','chave','descricao','emitente','status','raw','export'], array_keys($item), 'Contrato exato do modal');
        }
    });
    backendCheck('peso real usa projeção decimal sem somar ambiguidades', function () {
        backendSame(['display'=>'1.234,5678','status'=>'single','raw'=>'1234.5678','export'=>'1.234,5678'], Presenter::weight('single','1234.5678'), 'Precisão exata em KG');
        backendSame('1.234,5000', Presenter::weight('single','1234.5000')['display'], 'TON já convertido precisamente pela consulta');
        foreach (['missing'=>['—',''], 'multiple'=>['Múltiplas medidas','Múltiplas medidas'], 'invalid'=>['Medida inválida','Medida inválida']] as $status=>$texts) {
            $cell = Presenter::weight($status, null);
            backendSame(null, $cell['raw'], 'Ausências e ambiguidades não são zero');
            backendSame($texts[0], $cell['display'], 'Descrição do status');
            backendSame($texts[1], $cell['export'], 'Exportação do status');
        }
        backendSame('1234.5678', Presenter::numericSearch('1.234,5678'), 'Busca pt-BR');
        backendSame('1234.5678', Presenter::numericSearch('1234,5678'), 'Busca decimal brasileira');
        backendSame('1234', Presenter::numericSearch('1.234'), 'Ponto é agrupamento de milhares');
        backendSame('1234567', Presenter::numericSearch('1.234.567'), 'Milhares sem parte decimal');
        backendSame('1.234', Presenter::numericSearch('1,234'), 'Vírgula é separador decimal');
        backendSame(null, Presenter::numericSearch('1234.5678'), 'Decimal com ponto não é formato brasileiro');
        backendSame(null, Presenter::numericSearch('12.34'), 'Agrupamento inválido não vira decimal');
        backendSame(null, Presenter::numericSearch('1,234.5'), 'Separadores ambíguos rejeitados');
    });
    backendCheck('cidade e cinco papéis reais não fazem fallback implícito', function () {
        backendSame('missing', Presenter::city(null,null)['status'], 'Cidade ausente');
        backendSame('Cidade fim / SP', Presenter::city('Cidade fim','SP')['display'], 'Cidade + UF');
        foreach (['Remetente','Expedidor','Recebedor','Destinatário','Outros'] as $code=>$role) {
            backendSame($role, Presenter::taker($code,'Empresa')['raw']['papel'], 'Papel ' . $code);
            backendSame('Empresa — ' . $role, Presenter::taker((string)$code,'Empresa')['display'], 'Nome + papel');
            backendSame('missing_contact', Presenter::taker($code,null)['status'], 'Contato ausente');
        }
        foreach ([null,'',8,'00'] as $code) { backendSame('invalid', Presenter::taker($code,'Empresa')['status'], 'Código inválido não vira remetente'); }
    });
}
