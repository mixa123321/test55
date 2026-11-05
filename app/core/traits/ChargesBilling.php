<?php

namespace app\core\traits;

use app\backend\models\Upload;
use app\core\helpers\FileWrapper;

trait ChargesBilling
{
    public static function getFile(string $filename)
    {
        if (!is_file($filename))
            return "File isn't found";
        list($open, $res) = Upload::loadCSV($filename);
        $header = array_map(function ($el) {
            return trim($el, "\x00..\x1F\x80..\xFF");
        }, $res[0]);
        if (!$open || empty($res[0]) || empty($res[1]))
            return "File isn't found or empty";
        if (self::HEADER_SCV != array_intersect(self::HEADER_SCV, $header))
            return "The file has an invalid header. It should contain the following fields: '" .
                implode("', '", self::HEADER_SCV) . "'.";
        return [$header, $res[1]];
    }

    public static function saveCSV(int $id, array $header, array $data)
    {
        $file_wrapper = (new FileWrapper(FileWrapper::getContainerByPath(self::BILLING_IMPORT)))
            ->handleDir(self::BILLING_IMPORT);
        $filename = "$id.csv";
        $output = tmpfile();
        fputcsv($output, $header, ',');
        foreach ($data as $line)
            fputcsv($output, $line, ',');
        $file_wrapper->writeFileResource(self::BILLING_IMPORT . $filename, $output);
    }

    public static function reduction(int $id)
    {
        $data = self::getRanges($id);
        if (empty($data))
            return;
        do {
            [$del_id, $update, $data] = self::reductionData($data);
            if ($del_id)
                self::delete($del_id);
            if ($update)
                self::update($update);
        } while ($del_id || $update);
    }
}