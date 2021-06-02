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


if ( ! defined('_PS_VERSION_')) {
    exit;
}

include_once __DIR__.'/classes/BlueGateway.php';

class BluePayment extends PaymentModule
{

    private $html = '';

    public $name_upper;
    /**
     * Haki używane przez moduł
     *
     * @var array
     */
    protected $hooks
        = [
            'header',
            'payment',
            'paymentReturn',
        ];
    private $_checkHashArray = [];

    /**
     * Stałe statusów płatności
     */
    const PAYMENT_STATUS_PENDING = 'PENDING';
    const PAYMENT_STATUS_SUCCESS = 'SUCCESS';
    const PAYMENT_STATUS_FAILURE = 'FAILURE';

    /**
     * Stałe potwierdzenia autentyczności transakcji
     */
    const TRANSACTION_CONFIRMED = "CONFIRMED";
    const TRANSACTION_NOTCONFIRMED = "NOTCONFIRMED";

    const PAYMENT_ACTION_REGULATIONS_GET = '/webapi/regulationsGet';

    private $langs_available
        = [
            'PL',
            'EN',
            'DE',
            'CS',
            'ES',
            'FR',
            'IT',
            'SK',
            'RO',
            'HU',
            'HU'
        ];

    protected $currencies_available
        = [
            'PLN',
            'EUR',
            'USD',
            'GBP',
        ];

    protected $hash_algorithm_available
        = [
            ['method' => 'md5'],
            ['method' => 'sha256'],
        ];

    /**
     * Konstruktor
     */
    public function __construct()
    {
        $this->name       = 'bluepayment';
        $this->name_upper = strtoupper($this->name);

        // Kompatybilność wstecz dla wersji 1.4
        if (_PS_VERSION_ < '1.5') {
            require(_PS_MODULE_DIR_.$this->name.'/backward_compatibility/backward.php');
            require_once(_PS_MODULE_DIR_.$this->name.'/config/config.inc.php');
        } else {
            require_once(__DIR__.'/config/config.inc.php');
        }

        $this->tab                    = 'payments_gateways';
        $this->version                = BP_VERSION;
        $this->author                 = 'Blue Media';
        $this->need_instance          = 0;
        $this->ps_versions_compliancy = ['min' => '1.4.5', 'max' => '1.6'];
        $this->currencies             = true;
        $this->currencies_mode        = 'checkbox';

        parent::__construct();

        $this->displayName = $this->l('Online payment BM');
        $this->description = $this->l('Plugin supports online payments implemented by payment gateway Blue Media company.');

        $this->confirmUninstall = $this->l('Are you sure you want to uninstall?');
    }

    /**
     * Instalacja dodatku
     *
     * @return bool
     */
    public function install()
    {

        if (parent::install()) {
            foreach ($this->hooks as $hook) {
                if ( ! $this->registerHook($hook)) {
                    return false;
                }
            }

            Configuration::updateValue($this->name_upper.'_SHOW_PAYWAY', 0);
            Configuration::updateValue($this->name_upper.'_SHOW_PAYWAY_LOGO', 1);
            Configuration::updateValue($this->name_upper.'_SHOW_BANER', 0);
            Configuration::updateValue($this->name_upper.'_PAYMENT_NAME', 'Zapłać przez system Blue Media');
            Configuration::updateValue($this->name_upper.'_PAYMENT_NAME_EXTRA', 'Po złożeniu zamówienia zostaniesz przekierowany do bezpiecznego systemu płatności Blue Media.');
            $this->installTab();
            $this->installDb();

            return true;
        }

        return false;
    }


    /**
     * Usunięcie dodatku
     *
     * @return bool
     */
    public function uninstall()
    {
        $this->uninstallTab();
        $this->uninstallDb();
        if (parent::uninstall()) {
            foreach ($this->hooks as $hook) {
                if ( ! $this->unregisterHook($hook)) {
                    return false;
                }
            }

            foreach ($this->configFields() as $configField) {
                Configuration::deleteByName($configField);
            }

            // Usunięcie aktualnych wartości konfiguracyjnych
            Configuration::deleteByName($this->name_upper.'_HASH_ALGORITHM');
            Configuration::deleteByName($this->name_upper.'_SHARED_KEY');
            Configuration::deleteByName($this->name_upper.'_SERVICE_PARTNER_ID');

            return true;
        }

        return false;
    }

    /**
     * Zwraca zawartość strony konfiguracyjnej
     *
     * @return string
     */
    public function getContent()
    {
        $output = null;

        // Kompatybilność wstecz dla wersji 1.4
        if (_PS_VERSION_ < '1.5') {
            $this->postProcess();

            return $this->displayForm();
        }

        if (Tools::isSubmit('submit'.$this->name)) {
            foreach ($this->configFields() as $configField) {
                $value = Tools::getValue($configField, Configuration::get($configField));

                if ($configField === $this->name_upper.'_PAYMENT_DOMAIN') {
                    $removeProtocols = ['http://', 'https://'];
                    $newValue        = str_replace($removeProtocols, '', $value);

                    $value = rtrim($newValue, '/');
                }

                Configuration::updateValue($configField, $value);
            }

            $paymentName      = [];
            $paymentNameExtra = [];
            foreach (Language::getLanguages(true) as $lang) {
                $paymentName[$lang['id_lang']]      = Tools::getValue($this->name_upper.'_PAYMENT_NAME_'.$lang['id_lang']);
                $paymentNameExtra[$lang['id_lang']] = Tools::getValue($this->name_upper.'_PAYMENT_NAME_EXTRA_'.$lang['id_lang']);
            }

            $serviceId = [];
            $sharedKey = [];

            foreach (Currency::getCurrencies() as $currency) {
                $serviceId[$currency['iso_code']]     = Tools::getValue($this->name_upper.'_SERVICE_PARTNER_ID_'.$currency['iso_code']);
                $sharedKey[$currency['iso_code']]     = Tools::getValue($this->name_upper.'_SHARED_KEY_'.$currency['iso_code']);
                $hashAlgorithm[$currency['iso_code']] = Tools::getValue($this->name_upper.'_HASH_ALGORITHM_'.$currency['iso_code']);
            }

            Configuration::updateValue($this->name_upper.'_PAYMENT_NAME', $paymentName);
            Configuration::updateValue($this->name_upper.'_PAYMENT_NAME_EXTRA', $paymentNameExtra);
            Configuration::updateValue($this->name_upper.'_SERVICE_PARTNER_ID', serialize($serviceId));
            Configuration::updateValue($this->name_upper.'_SHARED_KEY', serialize($sharedKey));
            Configuration::updateValue($this->name_upper.'_HASH_ALGORITHM', serialize($hashAlgorithm));

            $gateway = new BlueGateway();
            $gateway->syncGateways();

            $output .= $this->displayConfirmation($this->l('Settings updated'));
        }

        return $output.$this->renderForm();
    }

    public function installTab()
    {
        $tab             = new Tab();
        $tab->active     = 1;
        $tab->class_name = 'AdminBluepayment';
        $tab->name       = [];
        foreach (Language::getLanguages(true) as $lang) {
            $tab->name[$lang['id_lang']] = $this->l('Blue Payments Gateway Manager');
        }
        $tab->id_parent = (int)Tab::getIdFromClassName('AdminAdmin');
        $tab->module    = $this->name;

        return $tab->add();
    }

    public function uninstallTab()
    {
        $id_tab = (int)Tab::getIdFromClassName('AdminBluepayment');
        if ($id_tab) {
            $tab = new Tab($id_tab);

            return $tab->delete();
        } else {
            return false;
        }
    }

    public function installDb()
    {
        Db::getInstance()->execute(
            'CREATE TABLE IF NOT EXISTS `'._DB_PREFIX_.'blue_gateways` (
                        `id` int(11) NOT NULL AUTO_INCREMENT,
                        `gateway_id` int(11) NOT NULL,
                        `gateway_status` int(11) NOT NULL,
                        `bank_name` varchar(100) NOT NULL,
                        `gateway_name` varchar(100) NOT NULL,
                        `gateway_description` varchar(1000) DEFAULT NULL,
                        `gateway_currency` varchar(50) NOT NULL,
                        `gateway_type` varchar(50) NOT NULL,
                        `gateway_logo_url` varchar(500) DEFAULT NULL,
                        PRIMARY KEY (`id`)
                      ) ENGINE='._MYSQL_ENGINE_.'  DEFAULT CHARSET=utf8;');
    }

    public function uninstallDb()
    {
        try {
            Db::getInstance()->execute('DROP TABLE `'._DB_PREFIX_.'blue_gateways`');
        } catch (Exception $exception) {
            PrestaShopLogger::addLog('Nie mozna usunac bazy danych', 5);
        }
    }

    /**
     * Zwraca formularz
     *
     * @return mixed
     */
    public function renderForm()
    {
        // Domyślny język
        $id_default_lang = (int)Configuration::get('PS_LANG_DEFAULT');

        // Dostępne statusy
        $statuses = OrderState::getOrderStates($id_default_lang);

        // Pola do konfiguracji
        $fields_form[0]['form'] = [
            'legend' => [
                'title' => $this->l('Settings'),
                'icon'  => 'icon-cogs',
            ],
            'input'  => [
                [
                    'type'     => 'text',
                    'label'    => $this->l('Payment Domain'),
                    'required' => true,
                    'name'     => $this->name_upper.'_PAYMENT_DOMAIN',
                ],
                [
                    'type'     => 'switch',
                    'label'    => $this->l('Show payway in shop'),
                    'required' => true,
                    'name'     => $this->name_upper.'_SHOW_PAYWAY',
                    'values'   => [
                        [
                            'id'    => 'active_on',
                            'value' => 1,
                            'label' => $this->l('Yes'),
                        ],
                        [
                            'id'    => 'active_off',
                            'value' => 0,
                            'label' => $this->l('No'),
                        ],
                    ],
                ],
                [
                    'type'     => 'switch',
                    'label'    => $this->l('Show logo payways'),
                    'required' => true,
                    'name'     => $this->name_upper.'_SHOW_PAYWAY_LOGO',
                    'values'   => [
                        [
                            'id'    => 'active_on',
                            'value' => 1,
                            'label' => $this->l('Yes'),
                        ],
                        [
                            'id'    => 'active_off',
                            'value' => 0,
                            'label' => $this->l('No'),
                        ],
                    ],
                ],
                [
                    'type'     => 'switch',
                    'label'    => $this->l('Show baner'),
                    'required' => true,
                    'name'     => $this->name_upper.'_SHOW_BANER',
                    'values'   => [
                        [
                            'id'    => 'active_on',
                            'value' => 1,
                            'label' => $this->l('Yes'),
                        ],
                        [
                            'id'    => 'active_off',
                            'value' => 0,
                            'label' => $this->l('No'),
                        ],
                    ],
                ],
                [
                    'type'    => 'select',
                    'name'    => $this->name_upper.'_STATUS_WAIT_PAY_ID',
                    'label'   => $this->l('Status waiting payment'),
                    'options' => [
                        'query' => $statuses,
                        'id'    => 'id_order_state',
                        'name'  => 'name',
                    ],
                ],
                [
                    'type'    => 'select',
                    'name'    => $this->name_upper.'_STATUS_ACCEPT_PAY_ID',
                    'label'   => $this->l('Status accept payment'),
                    'options' => [
                        'query' => $statuses,
                        'id'    => 'id_order_state',
                        'name'  => 'name',
                    ],
                ],
                [
                    'type'    => 'select',
                    'name'    => $this->name_upper.'_STATUS_ERROR_PAY_ID',
                    'label'   => $this->l('Status error payment'),
                    'options' => [
                        'query' => $statuses,
                        'id'    => 'id_order_state',
                        'name'  => 'name',
                    ],
                ],
                [
                    'type'     => 'text',
                    'label'    => $this->l('Payment name'),
                    'name'     => $this->name_upper.'_PAYMENT_NAME',
                    'size'     => 40,
                    'required' => true,
                ],
                [
                    'type'     => 'text',
                    'label'    => $this->l('Payment name extra'),
                    'name'     => $this->name_upper.'_PAYMENT_NAME_EXTRA',
                    'size'     => 40,
                    'required' => true,
                ],
            ],
            'submit' => [
                'title' => $this->l('Save'),
                'class' => 'button',
            ],
        ];

        foreach (Currency::getCurrencies() as $currency) {
            $fields_form['currency_'.$currency['iso_code']] = [
                'form' => [
                    'legend' => [
                        'title' => $this->l('Ustawienia waluty: ').$currency['name'].' ('.$currency['iso_code'].')',
                        'icon'  => 'icon-cog',
                    ],
                    'input'  => [
                        [
                            'type'  => 'text',
                            'label' => $this->l('Service partner ID'),
                            'name'  => $this->name_upper.'_SERVICE_PARTNER_ID_'.$currency['iso_code'],
                        ],
                        [
                            'type'  => 'text',
                            'label' => $this->l('Shared key'),
                            'name'  => $this->name_upper.'_SHARED_KEY_'.$currency['iso_code'],
                        ],
                        [
                            'type'    => 'select',
                            'label'   => $this->l('Hash method'),
                            'name'    => $this->name_upper.'_HASH_ALGORITHM_'.$currency['iso_code'],
                            'options' => [
                                'query' => $this->hash_algorithm_available,
                                'id'    => 'method',
                                'name'  => 'method',
                            ],
                        ],
                    ],
                    'submit' => [
                        'title' => $this->l('Save'),
                    ],
                ],
            ];
        }

        $helper = new HelperForm();

        // Moduł, token i currentIndex
        $helper->module          = $this;
        $helper->name_controller = $this->name;
        $helper->token           = Tools::getAdminTokenLite('AdminModules');
        $helper->currentIndex    = AdminController::$currentIndex.'&configure='.$this->name;

        // Domyślny język
        $helper->default_form_language    = $id_default_lang;
        $helper->allow_employee_form_lang = $id_default_lang;

        // Tytuł i belka narzędzi
        $helper->title          = $this->displayName;
        $helper->show_toolbar   = true;
        $helper->toolbar_scroll = true;
        $helper->submit_action  = 'submit'.$this->name;
        $helper->toolbar_btn    = [
            'save' =>
                [
                    'desc' => $this->l('Save'),
                    'href' => AdminController::$currentIndex.'&configure='.$this->name.'&save'.$this->name.
                        '&token='.Tools::getAdminTokenLite('AdminModules'),
                ],
            'back' =>
                [
                    'href' => AdminController::$currentIndex.'&token='.Tools::getAdminTokenLite('AdminModules'),
                    'desc' => $this->l('Back to list'),
                ],
        ];
        $helper->tpl_vars       = [
            'fields_value' => $this->getConfigFieldsValues(),
            'languages'    => $this->context->controller->getLanguages(),
            'id_language'  => $this->context->language->id,
        ];

        return $helper->generateForm($fields_form);
    }

    /**
     * Zwraca tablicę pól konfiguracyjnych
     *
     * @return array
     */
    public function getConfigFieldsValues()
    {
        $data = [];

        foreach ($this->configFields() as $configField) {
            $data[$configField] = Tools::getValue($configField, Configuration::get($configField));
        }

        foreach (Currency::getCurrencies() as $currency) {
            $data[$this->name_upper.'_SERVICE_PARTNER_ID_'.$currency['iso_code']] = $this->parseConfigByCurrency($this->name_upper.'_SERVICE_PARTNER_ID',
                $currency['iso_code']);
            $data[$this->name_upper.'_SHARED_KEY_'.$currency['iso_code']]         = $this->parseConfigByCurrency($this->name_upper.'_SHARED_KEY',
                $currency['iso_code']);
            $data[$this->name_upper.'_HASH_ALGORITHM_'.$currency['iso_code']]     = $this->parseConfigByCurrency($this->name_upper.'_HASH_ALGORITHM',
                $currency['iso_code']);
        }

        return $data;
    }

    /**
     * Hak do kroku wyboru płatności
     */
    public function hookPayment()
    {
        if ( ! $this->active) {
            return;
        }

        // Kompatybilność wstecz dla wersji 1.4
        if (_PS_VERSION_ < '1.5') {
            global $smarty;
            $this->smarty = $smarty;
        }

        if (method_exists('Link', 'getModuleLink')) {
            $moduleLink = $this->context->link->getModuleLink('bluepayment', 'payment', [], true);
            $tpl        = 'payment.tpl';
        } else {
            $tpl        = '/views/templates/hook/payment.tpl';
            $moduleLink = __PS_BASE_URI__.'modules/'.$this->name.'/payment.php';
        }

        $gateways = [];

        if (Configuration::get($this->name_upper.'_SHOW_PAYWAY')) {
            $gateways = new Collection('BlueGateway', $this->context->language->id);

            $gateway  = new BlueGateway();
            $gateway->syncGateways();

            $gateways->where('gateway_currency', '=', $this->context->currency->iso_code);
            $gateways->where('gateway_type', '=', 1);
            $gateways->where('gateway_status', '=', 1);

            $channelsList = array();
            foreach ($gateways as $gateway) {
                $channelsList[$gateway->gateway_id] = array(
                    'gatewayID'      => $gateway->gateway_id,
                    'gatewayName'    => $gateway->gateway_name,
                    'gatewayLogoUrl' => $gateway->gateway_logo_url,
                );
            }

            $gateways = $this->getPaymentRegulations($this->context->currency->iso_code, $channelsList);
        }

        $this->smarty->assign([
            'module_link'        => $moduleLink,
            'ps_version'         => _PS_VERSION_,
            'module_dir'         => $this->_path,
            'payment_name'       => Configuration::get($this->name_upper.'_PAYMENT_NAME'),
            'payment_name_extra' => Configuration::get($this->name_upper.'_PAYMENT_NAME_EXTRA'),
            'selectPayWay'       => Configuration::get($this->name_upper.'_SHOW_PAYWAY'),
            'showPayWayLogo'     => Configuration::get($this->name_upper.'_SHOW_PAYWAY_LOGO'),
            'showBaner'          => Configuration::get($this->name_upper.'_SHOW_BANER'),
            'gateways'           => $gateways,
        ]);

        return $this->display(__FILE__, $tpl);
    }

    public function getPaymentRegulations($currency, $channelsList)
    {
        $regulationList = array();

        $regulationsGet = $this->connectToAPI(
            self::PAYMENT_ACTION_REGULATIONS_GET,
            array(
                'ServiceID' => $this->parseConfigByCurrency($this->name_upper . '_SERVICE_PARTNER_ID', $currency),
                'MessageID' => md5('admin' . time())
            ),
            $header = '',
            $currency
        );

        if (!empty($regulationsGet['regulations']['regulation'])) {
            foreach ($regulationsGet['regulations']['regulation'] as $regulation) {
                $regulationList[] = $regulation;
            }
        }

        if (!empty($regulationList)) {
            foreach ($channelsList as $channel) {
                foreach ($regulationList as $regulation) {
                    if (isset($regulation['gatewayID']) && ($channel['gatewayID'] == $regulation['gatewayID'])) {
                        $channelsList[$channel['gatewayID']]['regulationID'] = empty($regulation['regulationID']) ? '' : $regulation['regulationID'];
                        $channelsList[$channel['gatewayID']]['regulationUrl'] = empty($regulation['url']) ? '' : $regulation['url'];
                        $channelsList[$channel['gatewayID']]['regulationType'] = empty($regulation['type']) ? '' : $regulation['type'];
                        $channelsList[$channel['gatewayID']]['regulationLanguage'] = empty($regulation['language']) ? '' : $regulation['language'];
                        $channelsList[$channel['gatewayID']]['regulationInputLabel'] = empty($regulation['inputLabel']) ? '' : $regulation['inputLabel'];
                    }
                }
            }
        }

        return $channelsList;
    }

    public function connectToAPI($action, $data, $header = 'BmHeader: pay-bm-continue-transaction-url', $currency = null)
    {
        if (empty($currency)) {
            $currency = $this->context->currency->iso_code;
        }

        self::generateHash(
            $this->parseConfigByCurrency($this->name_upper . '_SHARED_KEY', $currency),
            $this->parseConfigByCurrency($this->name_upper . '_HASH_ALGORITHM', $currency),
            $data
        );

        $fields = (is_array($data)) ? http_build_query($data) : $data;

        $curl = curl_init($this->getActionUrl($action, $currency));
        curl_setopt($curl, CURLOPT_HTTPHEADER, array($header));
        curl_setopt($curl, CURLOPT_POSTFIELDS, $fields);
        curl_setopt($curl, CURLOPT_POST, 1);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, true);
        $responseRaw = curl_exec($curl);
        curl_close($curl);

        if ($action == self::PAYMENT_ACTION_REGULATIONS_GET) {
            $response = $responseRaw;
        } else {
            $response = htmlspecialchars_decode($responseRaw);
        }

        if (strpos($response, '<!-- PAYWAY FORM BEGIN -->') !== false) {
            return $response;
        }

        $response = simplexml_load_string($response, 'SimpleXMLElement', LIBXML_NOCDATA);
        $response = json_decode(json_encode((array) $response), true);

        return $response;
    }

    public function generateHash($hashKey, $hashAlgorithm, array &$formData)
    {
        $result = '';
        foreach ($formData as $name => $value) {
            if (mb_strtolower($name) == 'hash') {
                continue;
            }
            $result .= $value . '|';
        }

        $defaultMode = 'sha256';
        $hashMode = empty($hashAlgorithm) ? $defaultMode : $hashAlgorithm;
        $formData['Hash'] = hash(mb_strtolower($hashMode), $result . $hashKey);

        return $formData;
    }

    public function getActionUrl($action, $currency)
    {
        $domain = Configuration::get($this->name_upper . '_PAYMENT_DOMAIN');
        switch ($action) {
            case self::PAYMENT_ACTION_REGULATIONS_GET:
                $action = self::PAYMENT_ACTION_REGULATIONS_GET;
                break;
        }

        return sprintf('https://%s%s', $domain, $action);
    }

    /**
     * Hak do kroku płatności zwrotnej/potwierdzenia zamówienia
     *
     * @param $params
     *
     * @return bool|void
     */
    public function hookPaymentReturn($params)
    {
        return true;
    }

    public function parseConfigByCurrency($key, $currencyIsoCode)
    {
        $data = Tools::unSerialize(Configuration::get($key));

        return is_array($data) && array_key_exists($currencyIsoCode, $data) ? $data[$currencyIsoCode] : '';
    }

    public function configFields()
    {
        return [
            $this->name_upper.'_PAYMENT_DOMAIN',
            $this->name_upper.'_STATUS_WAIT_PAY_ID',
            $this->name_upper.'_STATUS_ACCEPT_PAY_ID',
            $this->name_upper.'_STATUS_ERROR_PAY_ID',
            $this->name_upper.'_PAYMENT_NAME',
            $this->name_upper.'_PAYMENT_NAME_EXTRA',
            $this->name_upper.'_SHOW_PAYWAY',
            $this->name_upper.'_SHOW_PAYWAY_LOGO',
            $this->name_upper.'_SHOW_BANER',
        ];
    }

    /**
     * Waliduje zgodność otrzymanego XML'a
     *
     * @param XML $response
     *
     * @return boolen
     */
    public function _validAllTransaction($response)
    {
        $order = explode('-', $response->transactions->transaction->orderID)[0];
        $order          = new OrderCore($order);
        $currency       = new Currency($order->id_currency);
        $service_id     = $this->parseConfigByCurrency($this->name_upper.'_SERVICE_PARTNER_ID',
            $currency->iso_code);
        $shared_key     = $this->parseConfigByCurrency($this->name_upper.'_SHARED_KEY', $currency->iso_code);
        $hash_algorithm = $this->parseConfigByCurrency($this->name_upper.'_HASH_ALGORITHM',
            $currency->iso_code);

        if ($service_id != $response->serviceID) {
            return false;
        }

        $this->_checkHashArray   = [];
        $hash                    = (string)$response->hash;
        $this->_checkHashArray[] = (string)$response->serviceID;

        foreach ($response->transactions->transaction as $trans) {
            $this->_checkInList($trans);
        }
        $this->_checkHashArray[] = $shared_key;
        $localHash               = hash($hash_algorithm, implode(HASH_SEPARATOR, $this->_checkHashArray));

        return $localHash === $hash;
    }

    private function _checkInList($list)
    {
        foreach ((array)$list as $row) {
            if (is_object($row)) {
                $this->_checkInList($row);
            } else {
                $this->_checkHashArray[] = $row;
            }
        }
    }

    /**
     * Generuje i zwraca klucz hash na podstawie wartości pól z tablicy
     *
     * @param array $data
     *
     * @return string
     */
    public function generateAndReturnHash($data, $currency)
    {
        $values_array        = array_values($data);
        $hash_algorithm      = $this->parseConfigByCurrency($this->name_upper.'_HASH_ALGORITHM', $currency);
        $values_array_filter = array_filter($values_array);

        $comma_separated = implode(',', $values_array_filter);

        $replaced = str_replace(',', HASH_SEPARATOR, $comma_separated);

        return hash($hash_algorithm, $replaced);
    }

    /**
     * Zwraca adres bramki
     *
     * @return string
     */
    public function getUrlGateway()
    {
        $paymentDomain = Configuration::get($this->name_upper.'_PAYMENT_DOMAIN');

        return sprintf('https://%s/payment', $paymentDomain);
    }

    /**
     * Haczyk dla nagłówków stron
     */
    public function hookHeader()
    {
        $this->context->controller->addCSS($this->_path.'/css/front.css');
        $this->context->controller->addJS($this->_path.'/js/front.js');
    }

    /**
     * Potwierdzenie w postaci xml o prawidłowej/nieprawidłowej transakcji
     *
     * @param string $order_id
     * @param string $confirmation
     *
     * @return XML
     */
    protected function returnConfirmation($order_id, $confirmation)
    {
        $realOrderId = $order_id;
        $order_id = explode('-', $realOrderId)[0];

        $order    = new Order($order_id);
        $currency = new Currency($order->id_currency);

        // Id serwisu partnera
        $service_id = $this->parseConfigByCurrency($this->name_upper.'_SERVICE_PARTNER_ID', $currency->iso_code);

        // Klucz współdzielony
        $shared_key = $this->parseConfigByCurrency($this->name_upper.'_SHARED_KEY', $currency->iso_code);

        $hash_data = [$service_id, $realOrderId, $confirmation, $shared_key];

        // Klucz hash
        $hash_confirmation = $this->generateAndReturnHash($hash_data, $currency->iso_code);

        $dom = new DOMDocument('1.0', 'UTF-8');

        $confirmation_list = $dom->createElement('confirmationList');

        $dom_service_id = $dom->createElement('serviceID', $service_id);
        $confirmation_list->appendChild($dom_service_id);

        $transactions_confirmations = $dom->createElement('transactionsConfirmations');
        $confirmation_list->appendChild($transactions_confirmations);

        $dom_transaction_confirmed = $dom->createElement('transactionConfirmed');
        $transactions_confirmations->appendChild($dom_transaction_confirmed);

        $dom_order_id = $dom->createElement('orderID', $realOrderId);
        $dom_transaction_confirmed->appendChild($dom_order_id);

        $dom_confirmation = $dom->createElement('confirmation', $confirmation);
        $dom_transaction_confirmed->appendChild($dom_confirmation);

        $dom_hash = $dom->createElement('hash', $hash_confirmation);
        $confirmation_list->appendChild($dom_hash);

        $dom->appendChild($confirmation_list);

        echo $dom->saveXML();
    }

    /**
     * Odczytuje dane oraz sprawdza zgodność danych o transakcji/płatności
     * zgodnie z uzyskaną informacją z kontrolera 'StatusModuleFront'
     *
     * @param $response
     *
     * @throws Exception
     */
    public function processStatusPayment($response)
    {
        // Kompatybilność wstecz dla wersji 1.4
        if (_PS_VERSION_ < '1.5') {
            $logger = new Logger();
        } else {
            $logger = new PrestaShopLogger();
        }

        $transaction_xml = $response->transactions->transaction;

        if ($this->_validAllTransaction($response)) {
            // Aktualizacja statusu zamówienia i transakcji
            $this->updateStatusTransactionAndOrder($transaction_xml);
        } else {
            $message = $this->name_upper.' - Invalid hash: '.$response->hash;
            // Potwierdzenie zwrotne o transakcji nie autentycznej
            PrestaShopLogger::addLog($message, 3, null, 'Order', $transaction_xml->orderID);
            $this->returnConfirmation($transaction_xml->orderID, null, self::TRANSACTION_NOTCONFIRMED);
        }
    }

    /**
     * Sprawdza czy zamówienie zostało anulowane
     *
     * @param object $order
     *
     * @return boolean
     */
    public function isOrderCompleted($order)
    {
        $status        = $order->getCurrentState();
        $stateOrderTab = [Configuration::get('PS_OS_CANCELED')];

        return in_array($status, $stateOrderTab);
    }

    /**
     * Aktualizacja statusu zamówienia, transakcji oraz wysyłka maila do klienta
     *
     * @param $transaction
     *
     * @throws Exception
     */
    protected function updateStatusTransactionAndOrder($transaction)
    {
        // Identyfikatory statusów płatności

        $status_accept_pay_id  = Configuration::get($this->name_upper.'_STATUS_ACCEPT_PAY_ID');
        $status_waiting_pay_id = Configuration::get($this->name_upper.'_STATUS_WAIT_PAY_ID');
        $status_error_pay_id   = Configuration::get($this->name_upper.'_STATUS_ERROR_PAY_ID');

        // Status płatności
        $payment_status = (string)$transaction->paymentStatus;

        // Id transakcji nadany przez bramkę
        $remote_id = (string)$transaction->remoteID;

        // Id zamówienia
        $realOrderId = (string)$transaction->orderID;
        $order_id = explode('-', $realOrderId)[0];

        // Objekt zamówienia
        $order = new OrderCore($order_id);

        // Kompatybilność wstecz dla wersji 1.4
        if (_PS_VERSION_ < '1.5') {
            $logger = new Logger();
            // Obiekt płatności zamówienia
            $order_payment = new PaymentCC();
        } else {
            // Obiekt płatności zamówienia
            $order_payments = $order->getOrderPaymentCollection();
            if (count($order_payments) > 0) {
                $order_payment = $order_payments[0];
            } else {
                $order_payment = new OrderPaymentCore();
            }
            $logger = new PrestaShopLogger();
        }

        if ( ! Validate::isLoadedObject($order)) {
            $message = $this->name_upper.' - Order not found';
            $logger->addLog($message, 3, null, 'Order', $realOrderId);
            $this->returnConfirmation($realOrderId, self::TRANSACTION_NOTCONFIRMED);

            return;
        }

        if ( ! is_object($order_payment)) {
            $message = $this->name_upper.' - Order payment not found';
            $logger->addLog($message, 3, null, 'OrderPayment', $realOrderId);
            $this->returnConfirmation($realOrderId, self::TRANSACTION_NOTCONFIRMED);

            return;
        }

        // Suma zamówienia
        $total_paid = $order->total_paid;
        $amount     = number_format(round($total_paid, 2), 2, '.', '');

        // Jeśli zamówienie jest otwarte i status zamówienia jest różny od pustej wartości
        if ( ! ($this->isOrderCompleted($order)) && $payment_status != '') {
            switch ($payment_status) {
                // Jeśli transakcja została rozpoczęta
                case self::PAYMENT_STATUS_PENDING:
                    // Jeśli aktualny status zamówienia jest różny od ustawionego jako "oczekiwanie na płatność" i "płatność zaakceptowana"
                    if (!in_array($order->current_state, [$status_waiting_pay_id, $status_accept_pay_id])) {
                        $new_history           = new OrderHistory();
                        $new_history->id_order = $order_id;
                        $new_history->changeIdOrderState($status_waiting_pay_id, $order_id);
                        $new_history->addWithemail(true);
                    }
                    break;
                // Jeśli transakcja została zakończona poprawnie
                case self::PAYMENT_STATUS_SUCCESS:
                    if ($order->current_state == $status_waiting_pay_id ||
                        $order->current_state == $status_error_pay_id
                    ) {
                        $new_history           = new OrderHistory();
                        $new_history->id_order = $order_id;
                        $new_history->changeIdOrderState($status_accept_pay_id, $order_id);
                        $new_history->addWithEmail(true);

                        $order_payment->order_reference = $order->reference;
                        $order_payment->id_currency     = $order->id_currency;
                        $order_payment->payment_method  = $this->displayName;
                        $order_payment->amount         = $amount;
                        $order_payment->transaction_id = $remote_id;
                        $order_payment->update();
                    }
                    break;
                // Jeśli transakcja nie została zakończona poprawnie
                case self::PAYMENT_STATUS_FAILURE:
                    // Jeśli aktualny status zamówienia jest równy ustawionemu jako "oczekiwanie na płatność"
                    if ($order->current_state == $status_waiting_pay_id) {
                        $new_history           = new OrderHistory();
                        $new_history->id_order = $order_id;
                        $new_history->changeIdOrderState($status_error_pay_id, $order_id);
                        $new_history->addWithemail(true);
                    }
                    break;
                default:
                    break;
            }

            $this->returnConfirmation($realOrderId, self::TRANSACTION_CONFIRMED);
        } else {
            $message = $this->name_upper.' - Order status is cancel or payment status unknown';
            $logger->addLog($message, 3, null, 'OrderState', $realOrderId);
            $this->returnConfirmation($realOrderId, $message);
        }
    }

    private function displayForm()
    {
        // Opcje wyboru statusu oczekującego
        $options_waiting_status = '';

        // Opcje wyboru statusu prawidłowego
        $options_accept_status = '';

        // Opcje wyboru statusu nieprawidłowego
        $options_error_status = '';

        // Domyślny język
        $id_default_lang = (int)Configuration::get('PS_LANG_DEFAULT');

        // Dostępne statusy
        $statuses = OrderState::getOrderStates($id_default_lang);

        foreach ($statuses as $status) {
            $options_waiting_status .= '<option value="'.$status['id_order_state'].'"'
                .(Configuration::get($this->name_upper.'_STATUS_WAIT_PAY_ID') == $status['id_order_state'] ? 'selected="selected"' : '').'>'
                .$status['name']
                .'</option>';
            $options_accept_status  .= '<option value="'.$status['id_order_state'].'"'
                .(Configuration::get($this->name_upper.'_STATUS_ACCEPT_PAY_ID') == $status['id_order_state'] ? 'selected="selected"' : '').'>'
                .$status['name']
                .'</option>';
            $options_error_status   .= '<option value="'.$status['id_order_state'].'"'
                .(Configuration::get($this->name_upper.'_STATUS_ERROR_PAY_ID') == $status['id_order_state'] ? 'selected="selected"' : '').'>'
                .$status['name']
                .'</option>';
        }

        $this->html .= '<h2>'.$this->displayName.'</h2>';
        $this->html .= '<form action="'.Tools::htmlentitiesUTF8($_SERVER['REQUEST_URI']).'" method="post">
			<fieldset>
			<legend><img src="../img/admin/tab-preferences.gif" />'.$this->l('Settings').'</legend>
				<table border="0" cellpadding="5" cellspacing="5" id="form">
                                        <tr>
						<td style="text-align: right;">'.$this->l('Show payway in shop').'</td>
						<td>
						    <select name="'.$this->name_upper.'_SHOW_PAYWAY">
						        <option value="1"'
            .(Configuration::get($this->name_upper.'_SHOW_PAYWAY') == 1 ? 'selected="selected"' : '')
            .'>'.$this->l('Yes').'</option>
						        <option value="0"'
            .(Configuration::get($this->name_upper.'_SHOW_PAYWAY') == 0 ? 'selected="selected"' : '')
            .'>'.$this->l('No').'</option>
                            </select>
						</td>
					</tr>
                                        <tr>
						<td style="text-align: right;">'.$this->l('Show logo payways').'</td>
						<td>
						    <select name="'.$this->name_upper.'_SHOW_PAYWAY_LOGO">
						        <option value="1"'
            .(Configuration::get($this->name_upper.'_SHOW_PAYWAY_LOGO') == 1 ? 'selected="selected"' : '')
            .'>'.$this->l('Yes').'</option>
						        <option value="0"'
            .(Configuration::get($this->name_upper.'_SHOW_PAYWAY_LOGO') == 0 ? 'selected="selected"' : '')
            .'>'.$this->l('No').'</option>
                            </select>
						</td>
					</tr>
					<tr>
						<td style="text-align: right;">'.$this->l('Show baner').'</td>
						<td>
						    <select name="'.$this->name_upper.'_SHOW_BANER">
						        <option value="1"'
            .(Configuration::get($this->name_upper.'_SHOW_BANER') == 1 ? 'selected="selected"' : '')
            .'>'.$this->l('Yes').'</option>
						        <option value="0"'
            .(Configuration::get($this->name_upper.'_SHOW_BANER') == 0 ? 'selected="selected"' : '')
            .'>'.$this->l('No').'</option>
                            </select>
						</td>
					</tr>
					<tr>
					    <td style="text-align: right;">'.$this->l('Service partner ID').'</td>
					    <td>
					        <input type="text" name="'.$this->name_upper.'_SERVICE_PARTNER_ID"
					        value="'.htmlentities(Tools::getValue($this->name_upper.'_SERVICE_PARTNER_ID', Configuration::get($this->name_upper.'_SERVICE_PARTNER_ID')), ENT_COMPAT, 'UTF-8').'" style="width: 300px;" />
					    </td>
                    </tr>
					<tr>
					    <td style="text-align: right;">'.$this->l('Shared key').'</td>
					    <td>
					        <input type="text" name="'.$this->name_upper.'_SHARED_KEY"
					        value="'.htmlentities(Tools::getValue($this->name_upper.'_SHARED_KEY', Configuration::get($this->name_upper.'_SHARED_KEY')), ENT_COMPAT, 'UTF-8').'" style="width: 300px;" />
					    </td>
                    </tr>
					<tr>
						<td style="text-align: right;">'.$this->l('Status waiting payment').'</td>
						<td>
						    <select name="'.$this->name_upper.'_STATUS_WAIT_PAY_ID">
						        '.$options_waiting_status.'
                            </select>
						</td>
					</tr>
					<tr>
						<td style="text-align: right;">'.$this->l('Status accept payment').'</td>
						<td>
						    <select name="'.$this->name_upper.'_STATUS_ACCEPT_PAY_ID">
						        '.$options_accept_status.'
                            </select>
						</td>
                    </tr>
					</tr>
						<td style="text-align: right;">'.$this->l('Status error payment').'</td>
						<td>
						    <select name="'.$this->name_upper.'_STATUS_ERROR_PAY_ID">
						        '.$options_error_status.'
                            </select>
						</td>
					</tr>
                                        					<tr>
					    <td style="text-align: right;">'.$this->l('Payment name').'</td>
					    <td>
					        <input type="text" name="'.$this->name_upper.'_PAYMENT_NAME"
					        value="'.htmlentities(Tools::getValue($this->name_upper.'_PAYMENT_NAME', Configuration::get($this->name_upper.'_PAYMENT_NAME')), ENT_COMPAT, 'UTF-8').'" style="width: 300px;" />
					    </td>
                    </tr>
                    					<tr>
					    <td style="text-align: right;">'.$this->l('Payment name extra').'</td>
					    <td>
					        <input type="text" name="'.$this->name_upper.'_PAYMENT_NAME_EXTRA"
					        value="'.htmlentities(Tools::getValue($this->name_upper.'_PAYMENT_NAME_EXTRA', Configuration::get($this->name_upper.'_PAYMENT_NAME_EXTRA')), ENT_COMPAT, 'UTF-8').'" style="width: 300px;" />
					    </td>
                    </tr>
					<tr><td colspan="2" align="center"><input class="button" name="submit'.$this->name.'" value="'.$this->l('Save').'" type="submit" /></td></tr>
				</table>
			</fieldset>
		</form>';

        return $this->html;
    }

    /**
     * Pobiera dane z tablicy POST i zapisuje je do tabeli konfiguracyjnej
     *
     */
    private function postProcess()
    {
        if (Tools::isSubmit('submit'.$this->name)) {
            unset($_POST['submitbluepayment']);
            foreach ($_POST as $key => $val) {
                Configuration::updateValue($key, $val);
            }
            $this->html .= '<div class="conf confirm"><img src="../img/admin/ok.gif" alt="'.$this->l('ok').'" /> '.$this->l('Settings updated').'</div>';
        }
    }

    //Sets the language for the payment:
    //iIf the page language is not in the $langs_available array, set EN as the default
    public function setLanguage ()
    {
        $iso_lang = strtoupper($this->context->language->iso_code);
        return (in_array($iso_lang, $this->langs_available)) ? $iso_lang :  'EN';
    }

    //saves transaction data in logs
    // in location [prestashop dashboard / advanced / logs]
    public function logParams ($params)
    {
        $msg = "Bluepayment transaction data: ";
        foreach ($params  as $key => $value) {
            $msg = $msg." [".$key.": ".$value."] ";
        }
        PrestaShopLogger::addLog($msg, 1);
    }

    /**
     * Zwraca uri path dla modułu
     *
     * @return string
     */
    public function getPathUri()
    {
        return $this->_path;
    }

}
