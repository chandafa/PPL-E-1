<?php

namespace Database\Seeders\Support;

final class D2SourceDataset
{
    public static function snapshot(): array
    {
        return json_decode(file_get_contents(database_path('data/d2-source-demo.json')), true, flags: JSON_THROW_ON_ERROR);
    }

    public static function rows(): array
    {
        $tables = self::snapshot()['tables'];
        $termins = array_column($tables['termins'], null, 'termin_id');
        $contracts = array_column($tables['contracts'], null, 'contract_id');

        return array_map(static function (array $row) use ($termins, $contracts): array {
            $contract = $contracts[$termins[$row['termin_id']]['contract_id']];

            return [
                'invoiceId' => $row['invoice_id'], 'invoiceNumber' => $row['invoice_number'],
                'terminId' => $row['termin_id'], 'taxParamId' => $row['tax_param_id'],
                'invoiceDate' => $row['invoice_date'], 'dppAmount' => $row['dpp_amount'],
                'ppnAmount' => $row['ppn_amount'], 'pphAmount' => $row['pph_amount'],
                'totalAmount' => $row['total_amount'], 'status' => $row['status'],
                'projectId' => $contract['project_id'], 'projectName' => null,
                'clientId' => null, 'clientName' => null, 'items' => [],
            ];
        }, $tables['invoices']);
    }
}
