<?php

namespace app\backend\controllers;

use app\core\entities\Printer;
use app\core\exceptions\RedirectException;
use app\core\helpers\View;
use app\core\models\Printers as PrintersModel;

class Printers extends AuthController
{
    /** @throws RedirectException */
    public function beforeAction($action)
    {
        parent::beforeAction($action);
        $this->mobile = true;
    }

    public function index()
    {
        $data = [
            'printers' => PrintersModel::getPrinters($this->warehouse_id),
        ];
        $this->render($data, 'warehouse/printers');
    }

    /** @throws */
    public function lockPrinter()
    {
        if (empty($_POST))
            throw new RedirectException('/');
        $printer = PrintersModel::getById($_POST['printer_id']);
        if (empty($printer) || !in_array((int)$_POST['printer_lock'], [Printer::PRINTER_BLOCKED, Printer::PRINTER_ACTIVE, Printer::PRINTER_DEFAULT])) {
            View::addFlash('printers', 'danger', 'Invalid information');
            throw new RedirectException('/printers');
        }
        PrintersModel::setStatus($printer, $_POST['printer_lock'] == Printer::PRINTER_BLOCKED ? Printer::PRINTER_ACTIVE : Printer::PRINTER_BLOCKED);
        $printer->save(['id', 'state']);
        View::addFlash('printers', 'success', 'Printer status is changed');
        throw new RedirectException('/printers');
    }

    /** @throws */
    public function defaultPrinter()
    {
        if (empty($_POST))
            throw new RedirectException('/');
        $printer = PrintersModel::getById($_POST['printer_id']);
        if (empty($printer) || $printer->getState() != Printer::PRINTER_ACTIVE) {
            View::addFlash('printers', 'danger', 'Invalid information');
            throw new RedirectException('/printers');
        }
        PrintersModel::setDefault($printer);
        View::addFlash('printers', 'success', 'Printer set as default');
        throw new RedirectException('/printers');
    }

    /** @throws */
    public function editPrinter()
    {
        if (empty($_POST) || empty($_POST['printer']))
            throw new RedirectException('/');
        $printer_data = $_POST['printer'];
        if (empty($printer_data['id'])) {
            $printer_data['warehouse_id'] = $this->warehouse_id;
            $printer = new Printer($printer_data);
            unset($printer_data['id']);
            $printer->save(array_keys($printer_data));
            View::addFlash('printers', 'success', 'Printer added');
        } else {
            $printer = PrintersModel::getById($printer_data['id']);
            if (empty($printer))
                View::addFlash('printers', 'danger', 'Invalid information');
            elseif ($printer->getName() == $printer_data['name'] && $printer->getSourceId() == $printer_data['source_id'])
                View::addFlash('printers', 'warning', 'Data not changed');
            else {
                $printer->setName($printer_data['name']);
                $printer->setSourceId($printer_data['source_id']);
                $printer->save(['id', 'name', 'source_id']);
                View::addFlash('printers', 'success', 'Data changed');
            }
        }
        throw new RedirectException('/printers');
    }
}