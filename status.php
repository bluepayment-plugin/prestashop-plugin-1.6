<?php

/**
 * Autopay_BluePayment extension
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the GNU General Public License
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * https://www.gnu.org/licenses/gpl-3.0.html
 *
 * @category       Autopay
 * @package        Autopay_BluePayment
 * @copyright      Copyright (c) 2015-2016
 * @license        https://www.gnu.org/licenses/gpl-3.0.html GNU General Public License
 */

include(dirname(__FILE__) . '/../../config/config.inc.php');
include(dirname(__FILE__) . '/../../init.php');

include_once(_PS_MODULE_DIR_ . 'bluepayment/bluepayment.php');

class StatusController extends FrontController {

    public $ssl = true;
    public $display_column_left = false;

    public function displayContent() {
        parent::displayContent();

        $bp = new BluePayment();

        // Parametry z request
        $param_transactions = Tools::getValue('transactions');

        // Jeśli parametr 'transactions' istnieje i zawiera przynajmniej jedną transakcję
        if (isset($param_transactions)) {
            // Odkodowanie parametru transakcji
            $base64transactions = base64_decode($param_transactions);

            // Odczytanie parametrów z xml-a
            $simple_xml = simplexml_load_string($base64transactions);

            $bp->processStatusPayment($simple_xml);
        }
    }

}

$statusController = new StatusController();
$statusController->init();
$statusController->preProcess();
$statusController->displayContent();
$statusController->process();
exit;
