<?php

declare(strict_types=1);

function exportLocations(PDO $db, string $province, string $provinceName): never
{
    $query = $db->prepare("SELECT id, datos FROM atenciones WHERE estado='Cerrada' AND provincia=? AND eliminado IS NULL ORDER BY id");
    $query->execute([$province]);
    $features = [];
    foreach ($query as $record) {
        $data = json_decode($record['datos'], true, 512, JSON_THROW_ON_ERROR);
        foreach (array_keys(LOCATIONS) as $type) {
            $lat = $data[$type.'_lat'] ?? null;
            $lon = $data[$type.'_lon'] ?? null;
            if ($lat === null || $lon === null) {
                continue;
            }
            $features[] = [
                'type' => 'Feature',
                'id' => $record['id'].'-'.$type,
                'geometry' => ['type' => 'Point', 'coordinates' => [$lon, $lat]],
                'properties' => ['tipo' => $type, 'provincia' => $provinceName, 'referencia_aproximada' => true],
            ];
        }
    }
    aphHeader('Content-Type: application/geo+json');
    aphHeader('Content-Disposition: attachment; filename="APH-'.$province.'-ubicaciones.geojson"');
    echo json_encode(['type' => 'FeatureCollection', 'features' => $features], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    aphExit();
}

function exportWorkbook(PDO $db, string $province, string $provinceName): never
{
    $path = tempnam(sys_get_temp_dir(), 'aph');
    if ($path === false) {
        throw new RuntimeException('No se pudo crear el archivo de exportación.');
    }
    try {
        $zip = new ZipArchive;
        if ($zip->open($path, ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('No se pudo abrir el archivo de exportación.');
        }
        $office = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';
        $sheet = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';
        $relationships = 'http://schemas.openxmlformats.org/package/2006/relationships';
        $zip->addFromString('[Content_Types].xml', '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            .'<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            .'<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/></Types>');
        $zip->addFromString('_rels/.rels', "<Relationships xmlns=\"$relationships\"><Relationship Id=\"rId1\" Type=\"$office/officeDocument\" Target=\"xl/workbook.xml\"/></Relationships>");
        $sheetName = htmlspecialchars('BDAPH-'.($province === 'santo-domingo-de-los-tsachilas' ? 'SANTO DOMINGO' : strtoupper($provinceName)), ENT_XML1 | ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $zip->addFromString('xl/workbook.xml', "<workbook xmlns=\"$sheet\" xmlns:r=\"$office\"><sheets><sheet name=\"$sheetName\" sheetId=\"1\" r:id=\"rId1\"/></sheets></workbook>");
        $zip->addFromString('xl/_rels/workbook.xml.rels', "<Relationships xmlns=\"$relationships\"><Relationship Id=\"rId1\" Type=\"$office/worksheet\" Target=\"worksheets/sheet1.xml\"/></Relationships>");
        $xml = "<worksheet xmlns=\"$sheet\"><sheetData>".sheetRow(FIELDS, 1);
        $row = 2;
        $query = $db->prepare("SELECT datos FROM atenciones WHERE estado='Cerrada' AND provincia=? AND eliminado IS NULL ORDER BY id");
        $query->execute([$province]);
        foreach ($query as $record) {
            $xml .= sheetRow(json_decode($record['datos'], true, 512, JSON_THROW_ON_ERROR), $row++);
        }
        $zip->addFromString('xl/worksheets/sheet1.xml', $xml.'</sheetData></worksheet>');
        if (! $zip->close()) {
            throw new RuntimeException('No se pudo finalizar el archivo de exportación.');
        }
        aphHeader('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        aphHeader('Content-Disposition: attachment; filename="BDAPH-'.$province.'-piloto.xlsx"');
        readfile($path);
    } finally {
        unlink($path);
    }
    aphExit();
}

function sheetRow(array $values, int $row): string
{
    $xml = "<row r=\"$row\">";
    foreach (FIELDS as $column => $header) {
        $value = $values[$column] ?? '';
        $reference = $column.$row;
        if ($row > 1 && in_array($column, ['P', 'Z', 'AA'], true) && $value !== '') {
            $xml .= "<c r=\"$reference\"><v>".(float) $value.'</v></c>';

            continue;
        }
        $text = htmlspecialchars($value, ENT_XML1 | ENT_QUOTES | ENT_SUBSTITUTE | ENT_DISALLOWED, 'UTF-8');
        $xml .= "<c r=\"$reference\" t=\"inlineStr\"><is><t xml:space=\"preserve\">$text</t></is></c>";
    }

    return $xml.'</row>';
}
