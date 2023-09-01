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


class BluePaymentBackModuleFrontController extends ModuleFrontController
{
    public function init()
    {
        $this->page_name            = 'opinion'; // page_name and body id
        $this->display_column_left  = true;
        $this->display_column_right = true;
        parent::init();
    }

    public function initContent()
    {
        parent::initContent();

        // Id serwisu partnera
        $service_id = $this->module->parseConfigByCurrency($this->module->name_upper.'_SERVICE_PARTNER_ID', $this->context->currency->iso_code);

        // Id zamówienia
        $order_id = Tools::getValue('OrderID');
        $order = explode('-', $order_id)[0];

        // Klucz współdzielony
        $shared_key = $this->module->parseConfigByCurrency($this->module->name_upper.'_SHARED_KEY', $this->context->currency->iso_code);


        $hash = Tools::getValue('Hash');

        // Tablica danych z których wygenerować hash
        $hash_data  = [$service_id, $order_id, $shared_key];
        $hash_local = $this->module->generateAndReturnHash($hash_data, $this->context->currency->iso_code);

        // Jeśli klucz hash jest prawidłowy przekieruj na stronę zamówień
        $valid = $hash == $hash_local;

        if ($valid && $this->context->customer->isLogged()) {
            Tools::redirect('index.php?controller=order-confirmation&id_module='.$this->module->id.'&id_order='.$order);
        }

        $this->context->smarty->assign(
            [
                'hash_valid' => $valid,
                'order'      => new OrderCore($order),
            ]
        );

        $this->setTemplate("payment_return.tpl");
    }

}
