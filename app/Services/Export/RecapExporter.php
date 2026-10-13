<?php

namespace App\Services\Export;

use App\Exceptions\ApiException;
use App\Services\Tax\RecapService;
use Barryvdh\DomPDF\Facade\Pdf;
use Maatwebsite\Excel\Excel;
use Maatwebsite\Excel\Facades\Excel as ExcelFacade;

/**
 * Ekspor rekap ke Excel atau PDF (FTR-KE01-06, Q-15: hanya xlsx dan pdf).
 */
class RecapExporter
{
    public const XLSX_TYPE = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';

    public function __construct(private readonly RecapService $recap) {}

    /**
     * @return array{content: string, contentType: string, filename: string}
     *
     * @throws ApiException 400 INVALID_TAX_PERIOD / INVALID_FORMAT
     */
    public function export(mixed $taxPeriod, mixed $format): array
    {
        $taxPeriod = $this->recap->assertPeriod($taxPeriod);

        if (! in_array($format, ['xlsx', 'pdf'], true)) {
            throw ApiException::badRequest('INVALID_FORMAT', 'Format ekspor hanya xlsx atau pdf');
        }

        $report = RecapReport::build($this->recap, $taxPeriod);
        $filename = "rekap-pajak-{$taxPeriod}.{$format}";

        if ($format === 'xlsx') {
            return [
                'content' => ExcelFacade::raw(new RecapWorkbook($report), Excel::XLSX),
                'contentType' => self::XLSX_TYPE,
                'filename' => $filename,
            ];
        }

        return [
            'content' => Pdf::loadView('exports.tax-recap', ['report' => $report])
                ->setPaper('a4', 'landscape')
                ->output(),
            'contentType' => 'application/pdf',
            'filename' => $filename,
        ];
    }
}
