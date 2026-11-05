<?php

namespace app\core\services\reportExport;

class ReportExportReceiptReportService extends ReportExportService
{
    /** @var ReportExportReceiptReportASNService $report_ASN */
    private $report_ASN;
    /** @var ReportExportReceiptReportReturnASNService $report_ReturnASN */
    private $report_ReturnASN;

    public function __construct($supplier_id, $warehouse_id)
    {
        $this->report_ASN = new ReportExportReceiptReportASNService($supplier_id, $warehouse_id);
        $this->report_ReturnASN = new ReportExportReceiptReportReturnASNService($supplier_id, $warehouse_id);
    }

    public function setFields()
    {
        $this->fields = [];
    }

    /** @throws \Exception */
    public function execute(): ReportExportService
    {
        $this->report_ASN->execute();
        $this->report_ReturnASN->execute();
        return $this;
    }

    public function getData($last_id): array
    {
        return [];
    }

    /** @throws \Exception */
    public function toSFTP()
    {
        $this->report_ASN->toSFTP();
        $this->report_ReturnASN->toSFTP();
    }
}