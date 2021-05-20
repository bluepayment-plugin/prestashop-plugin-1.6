<?php
/**
 * BlueMedia_BluePayment extension
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the GNU General Public License
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * https://www.gnu.org/licenses/gpl-3.0.html
 *
 * @category       BlueMedia
 * @package        BlueMedia_BluePayment
 * @copyright      Copyright (c) 2015-2016
 * @license        https://www.gnu.org/licenses/gpl-3.0.html GNU General Public License
 */

class BluePaymentPaymentModuleFrontController extends ModuleFrontController
{
    public $ssl = true;
    public $display_column_left = false;

    public function initContent()
    {
        parent::initContent();

        $cart = $this->context->cart;

        // Identyfikator koszyka
        $cart_id = $cart->id;

        if ($cart->id_customer == 0 || $cart->id_address_delivery == 0 || $cart->id_address_invoice == 0 || ! $this->module->active) {
            Tools::redirect('index.php?controller=order&step=1');
        }

        // Sprawdzenie czy opcja płatności jest nadal aktywna w przypadku kiedy klient dokona zmiany adresu
        // przed finalizacją zamówienia
        $authorized = false;
        foreach (Module::getPaymentModules() as $module) {
            if ($module['name'] == 'bluepayment') {
                $authorized = true;
                break;
            }
        }

        if ( ! $authorized) {
            die($this->module->l('This payment method is not available.', 'bluepayment'));
        }

        // Stworzenie obiektu klienta na podstawie danych z koszyka
        $customer = new Customer($cart->id_customer);

        // Jeśli nie udało się stworzyć i załadować obiektu klient, przekieruj na 1 krok
        if ( ! Validate::isLoadedObject($customer)) {
            Tools::redirect('index.php?controller=order&step=1');
        }

        // Całkowita suma zamówienia
        $total_paid = (float)$cart->getOrderTotal(true, Cart::BOTH);
        $amount     = number_format(round($total_paid, 2), 2, '.', '');

        // Id statusu zamówienia
        $id_order_state = Configuration::get($this->module->name_upper.'_STATUS_WAIT_PAY_ID');

        // Walidacja zamówienia
        $this->module->validateOrder(
            $cart_id, $id_order_state, $amount, $this->module->displayName,
            null, [], null, false, $customer->secure_key
        );

        // Idenfyfikator zamówienia
        $order_id = $this->module->currentOrder . '-' . time();

        // Adres bramki
        $form_url = $this->module->getUrlGateway();

        // Identyfikator serwisu partnera
        $service_id = $this->module->parseConfigByCurrency($this->module->name_upper.'_SERVICE_PARTNER_ID', $this->context->currency->iso_code);

        // Adres email klienta
        $customer_email = $customer->email;

        // Język
        $lang = $this->module->setLanguage();

        // Klucz współdzielony
        $shared_key = $this->module->parseConfigByCurrency($this->module->name_upper.'_SHARED_KEY', $this->context->currency->iso_code);

        // Parametry dla klucza hash
        $gateway_id = (int) Tools::getValue('gateway_id', 0);

        if ($gateway_id !== 0) {
            $hash_data = array($service_id, $order_id, $amount, $gateway_id, $this->context->currency->iso_code, $customer_email, $lang, $shared_key);
        } else {
            $hash_data = array($service_id, $order_id, $amount, $this->context->currency->iso_code, $customer_email, $lang, $shared_key);
        }

        // Klucz hash
        $hash_local = $this->module->generateAndReturnHash($hash_data, $this->context->currency->iso_code);

        $params = array(
            'ServiceID' => $service_id,
            'OrderID' => $order_id,
            'Amount' => $amount,
            'Currency' => $this->context->currency->iso_code,
            'CustomerEmail' => $customer_email,
            'Language' => $lang,
            'Hash' => $hash_local
        );

        $this->module->logParams($params);

        // Parametry dla formularza wysyłane do bramki
        if ($gateway_id !== 0) {
            $params['GatewayID'] = $gateway_id;
        }

        $this->context->smarty->assign(array(
            'params' => $params,
            'module_dir' => $this->module->getPathUri(),
            'form_url' => $form_url,
        ));

        $this->setTemplate('payment.tpl');
    }

}
