<?php

namespace Tests\Support;

// Protótipo exclusivo de testes. Não é código do backend da aplicação.
final class CteListContractProbe
{
    public static function keys($serialized, $category)
    {
        if ($category === 'nfe_carga' && mb_strlen((string) $serialized, 'UTF-8') > 450) {
            throw new \LengthException('chave_nfe suporta até 450 caracteres; ampliar exige rever os ordinais SQL');
        }
        if (!in_array($category, ['nfe_carga', 'cte_anterior'], true)) {
            throw new \InvalidArgumentException('Categoria de chave não suportada');
        }
        $items = [];
        $seen = [];
        $segments = $category === 'nfe_carga' ? explode(';', str_replace(',', ';', (string) $serialized)) : [(string) $serialized];
        foreach ($segments as $raw) {
            $key = str_replace([" ", "\t", "\n", "\r", "\v", "\f"], '', $raw);
            if ($key === '' || isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $valid = preg_match('/^[0-9]{44}$/D', $key) === 1
                && substr($key, 20, 2) === ($category === 'nfe_carga' ? '55' : '57');
            $number = $valid ? (ltrim(substr($key, 25, 9), '0') ?: '0') : null;
            $series = $valid ? (ltrim(substr($key, 22, 3), '0') ?: '0') : null;
            $type = $category === 'nfe_carga' ? 'NF-e' : 'CT-e anterior';
            $text = $valid ? $type . ' ' . $number . ' / série ' . $series : $type . ' — referência inválida (' . $key . ')';
            $items[] = [
                'categoria' => $category, 'tipo' => $type, 'numero' => $number, 'serie' => $series,
                'chave' => $key, 'descricao' => null, 'emitente' => null,
                'status' => $valid ? 'valid' : 'malformed', 'raw' => $raw,
                'export' => $text . ($valid ? ' / chave ' . $key : ''),
            ];
        }
        return $items;
    }

    public static function other(array $row)
    {
        $description = trim((string) ($row['descOutros'] ?? ''), " \t\n\r\v\f");
        $number = (int) ($row['nDoc'] ?? 0);
        $value = (string) ($row['vDocFisc'] ?? '0');
        if ($description === '' && $number === 0 && (float) $value == 0) {
            return [];
        }
        $types = ['00' => 'Declaração', '10' => 'Dutoviário', '59' => 'CF-e SAT', '65' => 'NFC-e', '99' => 'Outros'];
        $code = (string) ($row['tpDoc'] ?? '');
        $type = $types[$code] ?? 'Tipo desconhecido (' . $code . ')';
        return [[
            'categoria' => 'outros_carga', 'tipo' => $type, 'numero' => $number !== 0 ? (string) $number : null,
            'serie' => null, 'chave' => null, 'descricao' => $description !== '' ? $description : null, 'emitente' => null,
            'status' => isset($types[$code]) && $number >= 0 && (float) $value >= 0 ? 'valid' : 'malformed',
            'raw' => ['tpDoc' => $code, 'nDoc' => $row['nDoc'] ?? null, 'descOutros' => $row['descOutros'] ?? null, 'vDocFisc' => $value],
            'export' => $type . ($number !== 0 ? ' ' . $number : '') . ($description !== '' ? ' / ' . $description : '') . ((float) $value != 0 ? ' / valor ' . number_format((float)$value, 2, ',', '.') : ''),
        ]];
    }

    public static function previous(array $rows)
    {
        usort($rows, function ($left, $right) { return $left['id'] <=> $right['id']; });
        $items = [];
        foreach ($rows as $row) {
            foreach (self::keys($row['chave_cte'], 'cte_anterior') as $item) {
                $key = $item['chave'];
                $items[$key] = $items[$key] ?? $item;
                $issuer = trim((string) ($row['nome_emitente'] ?? ''), " \t\n\r\v\f");
                if ($items[$key]['emitente'] === null && $issuer !== '') {
                    $items[$key]['emitente'] = $issuer;
                    $items[$key]['export'] .= ' / emitente ' . $issuer;
                }
            }
        }
        return array_values($items);
    }

    public static function weight(array $measures)
    {
        $valid = [];
        $invalid = false;
        foreach ($measures as $measure) {
            $type = strtoupper(trim(str_replace(["\t", "\n", "\r", "\v", "\f"], ' ', $measure['tipo_medida']), ' '));
            if ($type !== 'PESO BRUTO' || !in_array($measure['cod_unidade'], ['01', '02'], true)) {
                continue;
            }
            if (!preg_match('/^[0-9]{1,6}(?:\.[0-9]{1,4})?$/D', (string) $measure['quantidade_carga'])) {
                $invalid = true;
                continue;
            }
            $parts = explode('.', (string) $measure['quantidade_carga']);
            $scaled = ((int) $parts[0]) * 10000 + (int) str_pad($parts[1] ?? '', 4, '0');
            if ($measure['cod_unidade'] === '02') {
                $scaled *= 1000;
            }
            $valid[] = intdiv($scaled, 10000) . '.' . str_pad((string) ($scaled % 10000), 4, '0', STR_PAD_LEFT);
        }
        if (count($valid) > 1) {
            return ['display' => 'Múltiplas medidas', 'status' => 'multiple', 'raw' => null, 'export' => 'Múltiplas medidas'];
        }
        if ($invalid) {
            return ['display' => 'Medida inválida', 'status' => 'invalid', 'raw' => null, 'export' => 'Medida inválida'];
        }
        if ($valid === []) {
            return ['display' => '—', 'status' => 'missing', 'raw' => null, 'export' => ''];
        }
        [$integer, $fraction] = explode('.', $valid[0]);
        $display = preg_replace('/\B(?=(\d{3})+(?!\d))/', '.', $integer) . ',' . $fraction;
        return ['display' => $display, 'status' => 'single', 'raw' => $valid[0], 'export' => $display];
    }

    public static function numericSearch($value)
    {
        $value = trim((string) $value);
        $parts = explode(',', $value);
        if (count($parts) > 2 || (isset($parts[1]) && !preg_match('/^[0-9]{1,4}$/D', $parts[1]))) { return null; }
        $groups = explode('.', $parts[0]);
        foreach ($groups as $index=>$group) {
            if (!ctype_digit($group) || (count($groups)>1 && ($index===0 ? strlen($group)>3 : strlen($group)!==3))) { return null; }
        }
        return implode('', $groups) . (isset($parts[1]) ? '.' . $parts[1] : '');
    }

    public static function taker($code, $contact, $businessId)
    {
        $roles = ['Remetente', 'Expedidor', 'Recebedor', 'Destinatário', 'Outros'];
        if (!in_array($code, [0, 1, 2, 3, 4, '0', '1', '2', '3', '4'], true)) {
            return ['display' => 'Tomador inválido', 'status' => 'invalid', 'raw' => null, 'export' => 'Tomador inválido'];
        }
        $role = $roles[(int) $code];
        $name = $contact && (string) $contact['business_id'] === (string) $businessId && $contact['deleted_at'] === null ? $contact['name'] : null;
        $display = $name !== null ? $name . ' — ' . $role : $role . ' — contato ausente';
        return ['display' => $display, 'status' => $name !== null ? 'resolved' : 'missing_contact', 'raw' => ['codigo' => (string) $code, 'papel' => $role, 'nome' => $name], 'export' => $display];
    }

    public static function documentCell(array $items)
    {
        $count = count($items);
        $malformed = count(array_filter($items, function ($item) { return $item['status'] === 'malformed'; }));
        return [
            'display' => $count === 0 ? '—' : ($count === 1 ? explode(' / ', $items[0]['export'])[0] : $count . ' documentos'),
            'status' => $count === 0 ? 'empty' : ($malformed === 0 ? 'valid' : ($malformed === $count ? 'malformed' : 'mixed')),
            'count' => $count, 'raw' => $count, 'items' => $items,
            'export' => implode(' | ', array_column($items, 'export')),
        ];
    }

    public static function city($city)
    {
        $display = $city ? $city['nome'] . ' / ' . $city['uf'] : '—';
        return ['display' => $display, 'status' => $city ? 'resolved' : 'missing', 'raw' => $city ? ['nome' => $city['nome'], 'uf' => $city['uf']] : null, 'export' => $city ? $display : ''];
    }

    public static function columnNames()
    {
        return ['documentos', 'cidade_destino', 'peso_kg', 'tomador_servico'];
    }
}
