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

include(dirname(__FILE__).'/../../config/config.inc.php');
include(dirname(__FILE__).'/../../init.php');

include_once(_PS_MODULE_DIR_.'bluepayment/bluepayment.php');

class BackController extends FrontController
{
    public $ssl = true;
    public $display_column_left = false;

    public function setMedia()
    {
        parent::setMedia();
    }

    public function displayContent()
    {
        parent::displayContent();

        $context = Context::getContext();

        $bp = new BluePayment();

        // Id serwisu partnera
        $service_id = $this->module->parseConfigByCurrency($this->module->name_upper.'_SERVICE_PARTNER_ID', $this->context->currency->iso_code);

        // Id zamówienia
        $order_id = Tools::getValue('OrderID');

        // Klucz współdzielony
        $shared_key = $this->module->parseConfigByCurrency($this->module->name_upper.'_SHARED_KEY', $this->context->currency->iso_code);

        // Hash
        $hash = Tools::getValue('Hash');

        // Tablica danych z których wygenerować hash
        $hash_data = [$service_id, $order_id, $shared_key];
        $hash_local = $bp->generateAndReturnHash($hash_data, $this->context->currency->iso_code);

        $order                  = new Order($order_id);
        $currency               = new \Currency($order->id_currency);
        $params                 = [];
        $params['total_to_pay'] = $order->getOrdersTotalPaid();
        $params['currency']     = $currency->sign;
        $params['objOrder']     = $order;
        $params['currencyObj']  = $currency;
        Hook::exec('displayPaymentReturn', $params, $this->module->id);

        $context->smarty->assign(
            [
                'hash_valid' => $hash == $hash_local,
            ]
        );

        $context->smarty->display(_PS_MODULE_DIR_.$bp->name.'/views/templates/front/payment_return.tpl');
    }
}

$backController = new BackController();
$backController->run();
