<?php

class BlueGateway extends ObjectModel
{
    const FAILED_CONNECTION_RETRY_COUNT = 5;
    const MESSAGE_ID_STRING_LENGTH = 32;

    private $module;

    public $gateway_status = null;
    public $gateway_id = null;
    public $id = null;
    public $bank_name = null;
    public $gateway_name = null;
    public $gateway_description;
    public $gateway_type;
    public $gateway_logo_url;
    /**
     * @see ObjectModel::$definition
     */
    public static $definition
        = [
            'table'   => 'blue_gateways',
            'primary' => 'id',
            'fields'  => [
                'gateway_id'          => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedId', 'copy_post' => false],
                'gateway_status'      => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedId', 'required' => true],
                'bank_name'           => ['type' => self::TYPE_STRING, 'validate' => 'isGenericName', 'required' => true, 'size' => 100],
                'gateway_name'        => ['type' => self::TYPE_STRING, 'validate' => 'isGenericName', 'size' => 100],
                'gateway_description' => ['type' => self::TYPE_STRING, 'validate' => 'isGenericName', 'size' => 1000],
                'gateway_currency'    => ['type' => self::TYPE_STRING],
                'gateway_type'        => ['type' => self::TYPE_STRING, 'validate' => 'isGenericName', 'size' => 50, 'required' => true],
                'gateway_logo_url'    => ['type' => self::TYPE_STRING, 'validate' => 'isGenericName', 'size' => 500],
            ],
        ];

    public function __construct($id = null, $id_lang = null, $id_shop = null)
    {
        parent::__construct($id, $id_lang, $id_shop);
        $this->module = new BluePayment();
    }

    public function syncGateways()
    {
        foreach (Currency::getCurrencies() as $currency) {
            $this->syncGateway($currency['iso_code']);
        }

        return true;
    }

    private function syncGateway($iso_code)
    {
        $hashMethod = $this->module->parseConfigByCurrency(
            $this->module->name_upper.'_HASH_ALGORITHM',
            $iso_code
        );

        $gatewayListAPIUrl = $this->getGatewayListUrl();

        $serviceId = $this->module->parseConfigByCurrency(
            $this->module->name_upper.'_SERVICE_PARTNER_ID',
            $iso_code
        );
        $messageId = $this->randomString(self::MESSAGE_ID_STRING_LENGTH);
        $hashKey   = $this->module->parseConfigByCurrency(
            $this->module->name_upper.'_SHARED_KEY',
            $iso_code
        );

        $loadResult = $this->loadGatewaysFromAPI($hashMethod, $serviceId, $messageId, $hashKey, $gatewayListAPIUrl);

        if ($loadResult) {
            $gatewayIds = [];

            foreach ($loadResult->gateway as $gateway) {
                $payway = self::getByGatewayIdAndCurrency($gateway->gatewayID, $iso_code);

                $payway->gateway_logo_url = $gateway->iconURL;
                $payway->bank_name        = $gateway->bankName;
                $payway->gateway_status   = $payway->gateway_status !== null ? $payway->gateway_status : 1;
                $payway->gateway_name     = $gateway->gatewayName;
                $payway->gateway_type     = 1;
                $payway->gateway_currency = $iso_code;
                $payway->force_id         = true;
                $payway->gateway_id       = $gateway->gatewayID;
                $gatewayIds[]             = $gateway->gatewayID;

                $payway->save();

            }

            Db::getInstance()->delete('blue_gateways', 'gateway_type = 1 AND gateway_id not in ('.implode(', ', $gatewayIds).')');

            return true;
        }

        return false;
    }

    private function getGatewayListUrl()
    {
        $paymentDomain = Configuration::get($this->module->name_upper.'_PAYMENT_DOMAIN');

        return sprintf('https://%s/paywayList', $paymentDomain);
    }

    private function randomString($length)
    {
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $randstring = '';
        for ($i = 0; $i < $length; $i++) {
            $randstring .= $characters[rand(0, strlen($characters) - 1)];
        }

        return $randstring;
    }

    private function loadGatewaysFromAPI($hashMethod, $serviceId, $messageId, $hashKey, $gatewayListAPIUrl)
    {
        $hash   = hash($hashMethod, $serviceId.HASH_SEPARATOR.$messageId.HASH_SEPARATOR.$hashKey);
        $data   = [
            'ServiceID' => $serviceId,
            'MessageID' => $messageId,
            'Hash'      => $hash,
        ];
        $fields = http_build_query($data);
        try {
            $curl = curl_init($gatewayListAPIUrl);
            curl_setopt($curl, CURLOPT_POSTFIELDS, $fields);
            curl_setopt($curl, CURLOPT_POST, 1);
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, true);
            $curlResponse = curl_exec($curl);
            curl_close($curl);
            if ($curlResponse == 'ERROR') {
                return false;
            } else {
                $response = simplexml_load_string($curlResponse);

                return $response;
            }
        } catch (Exception $e) {
            Tools::error_log($e);

            return false;
        }
    }

    public static function gatewayIsActive($gatewayId, $currency, $ignoreStatus = false)
    {
        $query = new DbQuery();
        $query->from('blue_gateways')
            ->where('gateway_id = '.$gatewayId)
            ->where('gateway_currency = "'.$currency.'"')
            ->select('id');

        if ( ! $ignoreStatus) {
            $query->where('gateway_status = 1');
        }

        return Db::getInstance(_PS_USE_SQL_SLAVE_)->getValue($query);
    }

    private static function getByGatewayIdAndCurrency($gatewayId, $currency)
    {
        return new BlueGateway(self::gatewayIsActive($gatewayId, $currency, true));
    }
}
