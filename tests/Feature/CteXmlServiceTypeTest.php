<?php

namespace Tests\Feature;

use App\Models\Cte;
use DOMDocument;
use DOMXPath;
use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

class CteXmlServiceTypeTest extends TestCase
{
    public function test_import_form_offers_all_service_types_with_normal_selected()
    {
        $xpath = $this->renderCargoFields();
        $select = $xpath->query('//select[@name="tipo_servico"]');

        $this->assertCount(1, $select, 'O formulário de importação deve exibir Tipo de Serviço.');
        $this->assertSame('tipo_servico', $select->item(0)->getAttribute('id'));
        $this->assertTrue($select->item(0)->hasAttribute('required'));
        $this->assertStringContainsString('select2', $select->item(0)->getAttribute('class'));
        $this->assertCount(1, $xpath->query('//label[@for="tipo_servico"]'));

        $options = [];
        foreach ($xpath->query('//select[@name="tipo_servico"]/option') as $option) {
            $options[$option->getAttribute('value')] = $option->textContent;
        }
        $this->assertSame(Cte::tiposServico(), $options);
        $this->assertSame('0', $xpath->query('//select[@name="tipo_servico"]/option[@selected]')->item(0)->getAttribute('value'));
    }

    public function test_import_form_has_previous_transport_document_fields()
    {
        $xpath = $this->renderCargoFields();

        $this->assertCount(1, $xpath->query('//*[@id="div-doc-anterior" and contains(@style, "display: none")]'));
        $this->assertCount(1, $xpath->query('//input[@id="chave_cte_anterior"]'));
        $this->assertCount(1, $xpath->query('//input[@id="ie_emitente_anterior"]'));
        $this->assertCount(1, $xpath->query('//*[@id="addDocAnterior"]'));
        $this->assertCount(1, $xpath->query('//input[@type="hidden" and @name="documentos_anteriores" and @id="docs_anteriores"]'));
        $this->assertCount(1, $xpath->query('//table[@id="tbl-docs-anteriores"]/tbody'));
    }

    public function test_all_forms_render_old_lists_in_hidden_inputs_including_explicit_empty_lists()
    {
        $this->app['request']->setLaravelSession($this->app['session']->driver());
        $lists = [
            'documentos_anteriores' => [['chave' => 'chave corrigível', 'ie' => '<img src=x onerror=alert(1)>']],
            'medidas' => [['unidade_medida' => '01', 'tipo_medida' => '<script>1</script>', 'quantidade' => '4,5']],
            'componentes' => [['nome' => '</input><script>alert(1)</script>', 'valor' => '45,00']],
        ];
        foreach (['create', 'edit', 'register_xml'] as $view) {
            foreach ([$lists, array_fill_keys(array_keys($lists), []), null] as $payload) {
                $old = $payload === null ? array_fill_keys(array_keys($lists), ['payload não textual']) : array_map('json_encode', $payload);
                $this->app['session']->flashInput($old);
                $cte = (object) [
                    'documentosAnteriores' => [['chave_cte' => 'persistido']],
                    'medidas' => [['cod_unidade' => '02', 'quantidade_carga' => '99']],
                    'componentes' => [['nome' => 'persistido', 'valor' => '99']],
                ];
                $source = file_get_contents(resource_path('views/cte/' . $view . '.blade.php'));
                preg_match_all('/^.*<input[^\n]*(?:id="docs_anteriores"|id="meds"|id="comps")[^\n]*$/m', $source, $matches);
                $this->assertCount(3, $matches[0], $view);
                ob_start();
                try {
                    eval('?>' . Blade::compileString(implode("\n", $matches[0])));
                    $html = ob_get_contents();
                } finally {
                    ob_end_clean();
                }
                $document = new DOMDocument();
                $document->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_NOERROR | LIBXML_NOWARNING);
                $xpath = new DOMXPath($document);
                $this->assertCount(0, $xpath->query('//script|//img'), 'Old input nunca pode criar HTML');
                foreach ($old as $name => $json) {
                    $this->assertSame(is_string($json) ? $json : '[]', $xpath->query('//input[@name="' . $name . '"]')->item(0)->getAttribute('value'), $view . ': ' . $name);
                }
            }
        }
    }

    public function test_invalid_import_payload_returns_to_get_create_preserving_old_input()
    {
        $payload = [
            '_cte_xml_import' => '1', 'tipo_servico' => '2', 'documentos_anteriores' => '[]',
            'medidas' => '[{"unidade_medida":"01","tipo_medida":"PESO","quantidade":"12"}]',
            'componentes' => '[{"nome":"Frete","valor":"40,00"}]',
            'remetente_id' => '8', 'destinatario_id' => '9', 'prod_predominante' => 'Carga importada',
        ];
        foreach (['/importXml', '/cte/importarXml'] as $origin) {
            $request = \Illuminate\Http\Request::create('/cte', 'POST', $payload);
            $request->headers->set('referer', url($origin));
            $request->setLaravelSession($this->app['session']->driver());
            $this->app->instance('request', $request);
            $this->app['url']->setRequest($request);
            $controller = $this->app->make(\App\Http\Controllers\CteController::class);
            \Illuminate\Testing\TestResponse::fromBaseResponse($controller->store($request))
                ->assertRedirect('/cte/create')
                ->assertSessionHasInput('medidas', $payload['medidas'])
                ->assertSessionHasInput('componentes', $payload['componentes'])
                ->assertSessionHasInput('documentos_anteriores', '[]')
                ->assertSessionHasInput('remetente_id', '8')
                ->assertSessionHasInput('prod_predominante', 'Carga importada')
                ->assertSessionHas('status.success', 0);
        }
    }

    private function renderCargoFields()
    {
        // Renderiza o trecho real do Blade sem layout, consultas ou gravações no banco.
        $source = file_get_contents(resource_path('views/cte/register_xml.blade.php'));
        $start = strpos($source, '<h4>INFORMAÇÕES DA CARGA</h4>');
        $end = strpos($source, '<h5 class="text-primary">INFORMAÇÕES DE QUANTIDADE</h5>', $start);
        $template = substr($source, $start, $end - $start);
        $veiculos = [];
        $clientes = [];
        $tiposTomador = Cte::tiposTomador();
        $tiposServico = Cte::tiposServico();
        $modals = Cte::modals();
        $dadosDaNFe = ['produto_predominante' => 'Carga de teste', 'valor_carga' => '100.00'];

        ob_start();
        try {
            eval('?>' . Blade::compileString($template));
            $html = ob_get_contents();
        } finally {
            ob_end_clean();
        }

        $document = new DOMDocument();
        $document->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_NOERROR | LIBXML_NOWARNING);

        return new DOMXPath($document);
    }
}
