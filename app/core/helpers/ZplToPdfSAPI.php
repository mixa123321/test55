<?php

namespace app\core\helpers;

use app\core\models\Messages;

class ZplToPdfSAPI
{
    private $template = [
        'dpi' => File::SMALL_PRINTER_DPI,
        'zplCommands' => '',
        'zplCommandsCodePage' => 850,
        'isZplCommandsBase64' => false,
        'renderOutputRotation' => 0,
        'antiAlias' => true,
        'labelWidthInchUnit' => 4,
        'labelHeightInchUnit' => 6,
        'labelBackColorHex' => '',
        'ribbonColorHex' => '',
        'backgroundImageBase64' => '',
        'watermarkImageBase64' => '',
        'watermarkOpacity' => 50,
        'drawRFID' => true,
        'thumbnailSizePixelUnit' => 0,
        'pdfMetadataAuthor' => '',
        'pdfMetadataCreator' => '',
        'pdfMetadataProducer' => '',
        'pdfMetadataSubject' => '',
        'pdfMetadataTitle' => '',
        'forceLabelHeight' => false,
        'forceLabelWidth' => false,
    ];

    private $dpi = File::SMALL_PRINTER_DPI;

    private $zpl_commands = '';

    public function setDpi(int $dpi): ZplToPdfSAPI
    {
        $this->dpi = $dpi;
        return $this;
    }

    public function setZplCommands(string $zplCommands): ZplToPdfSAPI
    {
        $this->zpl_commands = $zplCommands;
        return $this;
    }

    public function execute(): ?string
    {
        if (!$this->zpl_commands)
            throw new \Exception('Zpl Commands not set');
        $body = json_encode($this->getExecuteBody());
        $curl = new CURL(ZPL_PDF_SAPI_URL, 'zpl_as_pdf');
        $curl->setHeader('Content-Type', 'application/json');
        $curl->setHeader('Accept', 'application/pdf');
        $curl->setBody($body);
        $result = $curl->exec();
        if ((int)$curl->getResultCode() !== 200 || !$result) {
            Messages::addDebugInfo('Can not convert zpl to pdf', json_encode(['http_code' => $curl->getResultCode(), 'request_body' => $body, 'response_body' => $result]), Messages::GROUP_WAREHOUSE, Messages::TEST_PARAMETERS);
            return null;
        }
        return $result;
    }

    private function getExecuteBody(): array
    {
        $params = $this->template;
        $params['dpi'] = $this->dpi;
        $params['zplCommands'] = $this->zpl_commands;
        return $params;
    }
}