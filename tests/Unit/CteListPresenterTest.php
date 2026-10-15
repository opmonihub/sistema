<?php

namespace Tests\Unit;

use App\Utils\CteListPresenter;
use PHPUnit\Framework\TestCase;

// Sem Tests\TestCase, providers, conexão ou bootstrap Laravel.
require_once dirname(__DIR__, 2) . '/app/Utils/CteListPresenter.php';

final class CteListPresenterTest extends TestCase
{
    public function unicodeContentProvider()
    {
        return ['zero width space'=>["\u{200B}"], 'byte order mark'=>["\u{FEFF}"]];
    }

    /** @dataProvider unicodeContentProvider */
    public function testOnlyAsciiWhitespaceIsEmpty($unicode)
    {
        $cell = CteListPresenter::documents(['tpDoc'=>'99','descOutros'=>" \t" . $unicode . "\r\n",'nDoc'=>0,'vDocFisc'=>'0.00'], []);
        $this->assertSame(1, $cell['count']);
        $this->assertSame($unicode, $cell['items'][0]['descricao']);
        $this->assertSame('valid', $cell['status']);
    }

    /** @dataProvider unicodeContentProvider */
    public function testFirstUnicodeIssuerIsNotReplaced($unicode)
    {
        $cell = CteListPresenter::documents([], [
            ['id'=>2,'chave_cte'=>'referencia','nome_emitente'=>'Posterior indevido'],
            ['id'=>1,'chave_cte'=>'referencia','nome_emitente'=>$unicode],
        ]);
        $this->assertSame(1, $cell['count']);
        $this->assertSame($unicode, $cell['items'][0]['emitente']);
        $this->assertStringNotContainsString('Posterior indevido', $cell['export']);
    }

    public function testOtherDocumentValueIsBrazilianWithoutChangingRawValue()
    {
        foreach (['5.00'=>'5,00','1234.50'=>'1.234,50','99999999.99'=>'99.999.999,99','-1234.50'=>'-1.234,50'] as $raw=>$display) {
            $cell = CteListPresenter::documents(['tpDoc'=>'99','vDocFisc'=>$raw], []);
            $this->assertSame($raw, $cell['items'][0]['raw']['vDocFisc']);
            $this->assertSame('Outros / valor ' . $display, $cell['export']);
        }
    }

    public function testNumericSearchUsesBrazilianSeparators()
    {
        foreach (['1.234'=>'1234','1,234'=>'1.234','1.234.567'=>'1234567','1.234,5678'=>'1234.5678','0,0000'=>'0.0000'] as $input=>$expected) {
            $this->assertSame($expected, CteListPresenter::numericSearch($input));
        }
        foreach (['12.34','1234.5678','1,234.5','1.23.456','1,23456'] as $input) {
            $this->assertNull(CteListPresenter::numericSearch($input));
        }
    }

    public function testFullwidthFiscalCodeRemainsUnknown()
    {
        $cell = CteListPresenter::documents(['tpDoc'=>'９９','descOutros'=>'Conteúdo'], []);
        $this->assertSame('Tipo desconhecido (９９)', $cell['items'][0]['tipo']);
        $this->assertSame('malformed', $cell['status']);
    }
}
