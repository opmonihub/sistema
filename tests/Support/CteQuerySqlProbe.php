<?php

namespace Tests\Support;

// Consultas candidatas somente para testes em MySQL temporário. Não usadas pela aplicação.
final class CteQuerySqlProbe
{
    public static function schema()
    {
        return [
            'DROP TABLE IF EXISTS medida_ctes,cte_documentos_anteriores,ctes,contacts,cities',
            'CREATE TABLE ctes (id INT UNSIGNED PRIMARY KEY,business_id INT UNSIGNED NOT NULL,chave_nfe VARCHAR(450) NOT NULL,tpDoc VARCHAR(2),nDoc INT,descOutros VARCHAR(100),vDocFisc DECIMAL(10,2),municipio_fim INT,tomador INT,remetente_id INT,expedidor_id INT,recebedor_id INT,destinatario_id INT,tomador_outros_id BIGINT UNSIGNED,KEY ctes_business_id_foreign (business_id)) ENGINE=InnoDB',
            'CREATE TABLE cities (id INT UNSIGNED PRIMARY KEY,nome VARCHAR(40),uf VARCHAR(2)) ENGINE=InnoDB',
            'CREATE TABLE contacts (id INT UNSIGNED PRIMARY KEY,business_id INT UNSIGNED,name VARCHAR(255),deleted_at DATETIME) ENGINE=InnoDB',
            'CREATE TABLE medida_ctes (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,cte_id INT UNSIGNED,cod_unidade VARCHAR(2),tipo_medida VARCHAR(20),quantidade_carga DECIMAL(10,4),KEY medida_ctes_cte_id_foreign (cte_id)) ENGINE=InnoDB',
            'CREATE TABLE cte_documentos_anteriores (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,cte_id INT UNSIGNED,chave_cte VARCHAR(44),nome_emitente VARCHAR(255),KEY cte_documentos_anteriores_cte_id_foreign (cte_id)) ENGINE=InnoDB',
        ];
    }

    private static function compact($expression)
    {
        foreach ([32, 9, 10, 13, 11, 12] as $ascii) {
            $expression = 'REPLACE(' . $expression . ',CHAR(' . $ascii . "),'')";
        }
        return $expression;
    }

    private static function trimmed($expression)
    {
        foreach ([9, 10, 13, 11, 12] as $ascii) {
            $expression = 'REPLACE(' . $expression . ',CHAR(' . $ascii . "),' ')";
        }
        return 'TRIM(' . $expression . ')';
    }

    private static function ordinals()
    {
        $digits = '(SELECT 0 AS d UNION ALL SELECT 1 UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4 UNION ALL SELECT 5 UNION ALL SELECT 6 UNION ALL SELECT 7 UNION ALL SELECT 8 UNION ALL SELECT 9)';
        return '(SELECT u.d+10*t.d+100*h.d+1 AS n FROM ' . $digits . ' u CROSS JOIN ' . $digits . ' t CROSS JOIN (SELECT 0 AS d UNION ALL SELECT 1 UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4) h WHERE u.d+10*t.d+100*h.d+1<=451)';
    }

    public static function documents($businessId)
    {
        $tenant = (int) $businessId;
        $serialized = "REPLACE(" . self::compact("COALESCE(c.chave_nfe,'')") . ",',',';')";
        // LIMIT máximo impede derived-merge: normaliza uma vez por CT-e, não 451 vezes.
        $prepared = 'SELECT normalized.*,1+CHAR_LENGTH(normalized.serialized)-CHAR_LENGTH(REPLACE(normalized.serialized,\';\',\'\')) AS segment_count FROM (SELECT c.id,' . $serialized . ' AS serialized FROM ctes c WHERE c.business_id=' . $tenant . ' LIMIT 18446744073709551615) normalized LIMIT 18446744073709551615';
        $nfe = "SELECT p.id AS cte_id,'nfe_carga' AS categoria,SUBSTRING_INDEX(SUBSTRING_INDEX(p.serialized,';',o.n),';',-1) COLLATE utf8mb4_bin AS chave FROM (" . $prepared . ') p STRAIGHT_JOIN ' . self::ordinals() . ' o ON o.n<=p.segment_count';
        $previous = "SELECT a.cte_id,'cte_anterior' AS categoria," . self::compact("COALESCE(a.chave_cte,'')") . ' COLLATE utf8mb4_bin AS chave FROM cte_documentos_anteriores a JOIN ctes owner ON owner.id=a.cte_id AND owner.business_id=' . $tenant;
        $dedup = "SELECT k.cte_id,k.categoria,k.chave FROM (" . $nfe . ' UNION ALL ' . $previous . ") k WHERE k.chave<>'' GROUP BY k.cte_id,k.categoria,k.chave";
        $valid = "CHAR_LENGTH(k.chave)=44 AND k.chave REGEXP '^[0-9]{44}$' AND SUBSTRING(k.chave,21,2)=CASE k.categoria WHEN 'nfe_carga' THEN '55' ELSE '57' END";
        $number = "COALESCE(NULLIF(TRIM(LEADING '0' FROM SUBSTRING(k.chave,26,9)),''),'0')";
        $series = "COALESCE(NULLIF(TRIM(LEADING '0' FROM SUBSTRING(k.chave,23,3)),''),'0')";
        $classified = 'SELECT k.*,CASE WHEN ' . $valid . ' THEN ' . $number . ' END AS numero,CASE WHEN ' . $valid . ' THEN ' . $series . " END AS serie,CASE WHEN " . $valid . " THEN 'valid' ELSE 'malformed' END AS status FROM (" . $dedup . ') k';
        $keys = "SELECT d.*,CONCAT(CASE d.categoria WHEN 'nfe_carga' THEN 'NF-e' ELSE 'CT-e anterior' END,' ',COALESCE(d.numero,''),' ',COALESCE(d.serie,''),' ',d.chave) AS search_text FROM (" . $classified . ') d';
        $type = "CASE c.tpDoc WHEN '00' THEN 'Declaração' WHEN '10' THEN 'Dutoviário' WHEN '59' THEN 'CF-e SAT' WHEN '65' THEN 'NFC-e' WHEN '99' THEN 'Outros' ELSE CONCAT('Tipo desconhecido (',COALESCE(c.tpDoc,''),')') END";
        $other = "SELECT c.id AS cte_id,'outros_carga' AS categoria,NULL AS chave,CASE WHEN COALESCE(c.nDoc,0)<>0 THEN CAST(c.nDoc AS CHAR) END AS numero,NULL AS serie,CASE WHEN c.tpDoc IN ('00','10','59','65','99') AND COALESCE(c.nDoc,0)>=0 AND COALESCE(c.vDocFisc,0)>=0 THEN 'valid' ELSE 'malformed' END AS status,CONCAT(" . $type . ",' ',COALESCE(NULLIF(c.nDoc,0),''),' ',COALESCE(c.descOutros,''),' ',COALESCE(NULLIF(c.vDocFisc,0),'')) AS search_text FROM ctes c WHERE c.business_id=" . $tenant . ' AND (COALESCE(c.nDoc,0)<>0 OR COALESCE(c.vDocFisc,0)<>0 OR ' . self::trimmed("COALESCE(c.descOutros,'')") . "<>'')";
        return $keys . ' UNION ALL ' . $other;
    }

    public static function listing($businessId)
    {
        $tenant = (int) $businessId;
        $mass = "UPPER(" . self::trimmed('m.tipo_medida') . ") COLLATE utf8mb4_bin='PESO BRUTO' AND m.cod_unidade IN ('01','02')";
        $validMass = $mass . ' AND m.quantidade_carga>=0';
        $weights = 'SELECT m.cte_id,SUM(CASE WHEN ' . $validMass . ' THEN 1 ELSE 0 END) AS valid_count,SUM(CASE WHEN ' . $mass . ' AND (m.quantidade_carga<0 OR m.quantidade_carga IS NULL) THEN 1 ELSE 0 END) AS invalid_count,MAX(CASE WHEN ' . $validMass . " THEN CAST(m.quantidade_carga*CASE m.cod_unidade WHEN '02' THEN 1000 ELSE 1 END AS DECIMAL(15,4)) END) AS kg FROM medida_ctes m JOIN ctes owner ON owner.id=m.cte_id AND owner.business_id=" . $tenant . ' GROUP BY m.cte_id';
        $counts = 'SELECT d.cte_id,COUNT(*) AS document_count FROM (' . self::documents($tenant) . ') d GROUP BY d.cte_id';
        $contact = 'CASE c.tomador WHEN 0 THEN c.remetente_id WHEN 1 THEN c.expedidor_id WHEN 2 THEN c.recebedor_id WHEN 3 THEN c.destinatario_id WHEN 4 THEN c.tomador_outros_id END';
        return "SELECT c.id,COALESCE(dc.document_count,0) AS document_count,CASE WHEN w.valid_count=1 AND w.invalid_count=0 THEN w.kg END AS peso_kg,CASE WHEN w.valid_count>1 THEN 'multiple' WHEN w.invalid_count>0 THEN 'invalid' WHEN w.valid_count=1 THEN 'single' ELSE 'missing' END AS weight_status,city.nome AS cidade_nome,city.uf AS cidade_uf,taker.name AS tomador_nome,CASE c.tomador WHEN 0 THEN 'Remetente' WHEN 1 THEN 'Expedidor' WHEN 2 THEN 'Recebedor' WHEN 3 THEN 'Destinatário' WHEN 4 THEN 'Outros' END AS tomador_papel FROM ctes c LEFT JOIN (" . $counts . ') dc ON dc.cte_id=c.id LEFT JOIN (' . $weights . ') w ON w.cte_id=c.id LEFT JOIN cities city ON city.id=c.municipio_fim LEFT JOIN contacts taker ON taker.id=' . $contact . ' AND taker.business_id=c.business_id AND taker.deleted_at IS NULL WHERE c.business_id=' . $tenant;
    }

    public static function filteredIds($businessId)
    {
        return 'SELECT cte_id FROM (' . self::documents($businessId) . ") d WHERE search_text LIKE ? ESCAPE '!' GROUP BY cte_id ORDER BY cte_id";
    }
}
