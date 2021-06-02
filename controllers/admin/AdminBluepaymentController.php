<?php
include_once __DIR__.'/../../classes/BlueGateway.php';

class AdminBluepaymentController extends ModuleAdminController
{

    public function __construct()
    {
        $this->bootstrap  = true;
        $this->display    = 'view';
        $this->meta_title = $this->l('Blue Payments Gateway Manager');

        parent::__construct();

        if ( ! $this->module->active) {
            Tools::redirectAdmin($this->context->link->getAdminLink('AdminHome'));
        }

        $this->addRowAction('edit');
    }

    public function initToolBarTitle()
    {
        $this->toolbar_title[] = $this->l('Administration');
        $this->toolbar_title[] = $this->l('Blue Payments Gateway Manager');
    }

    public function initPageHeaderToolbar()
    {
        parent::initPageHeaderToolbar();
        unset($this->page_header_toolbar_btn['back']);

        $this->page_header_toolbar_btn['sync_gateway'] = [
            'href' => self::$currentIndex.'&download_gateway&token='.$this->token,
            'desc' => $this->l('Synchronize gateways'),
            'icon' => 'process-icon-refresh',
        ];
    }

    public function postProcess()
    {
        if (Tools::getIsset('download_gateway')) {
            $gateway = new BlueGateway();
            $gateway->syncGateways();
        }

        return parent::postProcess();
    }

    public function renderView()
    {
        $this->tpl_view_vars = [
            'massage' => [],
            'error'   => [],
        ];

        if (Tools::getIsset('download_gateway')) {
            $gateway = new BlueGateway();
            if ($gateway->syncGateways()) {
                $this->tpl_view_vars['massage'][] = $this->l('Successfull Download Payway');
            } else {
                $this->tpl_view_vars['error'][] = $this->l('Error Download Payway');
            }
        }

        if (Tools::getIsset('change_status')) {
            $gateway->gateway_status = $gateway->gateway_status == 1 ? 0 : 1;
            $gateway->update();
            $this->tpl_view_vars['massage'][] = $this->l('Payway status changed');
        }

        $gateways = new Collection('BlueGateway', $this->context->language->id);
        $gateways->sqlWhere('gateway_type = 1');
        $this->tpl_view_vars['gateways'] = $gateways;

        if (version_compare(_PS_VERSION_, '1.5.6.0', '>')) {
            $this->base_tpl_view = 'view_list.tpl';
        }

        return parent::renderView();
    }

    public function ajaxProcessGatewayStatusBlueGateways()
    {
        if ( ! $gateway_id = (int)Tools::getValue('id')) {
            die(
            json_encode(
                [
                    'success' => false,
                    'error'   => true,
                    'text'    => $this->l('Failed to update the status'),
                ]
            )
            );
        }

        $gateway = new BlueGateway($gateway_id);
        if (Validate::isLoadedObject($gateway)) {
            $gateway->gateway_status = (int)$gateway->gateway_status === 1 ? 0 : 1;
            $gateway->save()
                ?
                die(json_encode(
                    [
                        'success' => true,
                        'text'    => $this->l('The status has been updated successfully'),
                    ]
                ))
                :
                die(json_encode(
                    [
                        'success' => false,
                        'error'   => true,
                        'text'    => $this->l('Failed to update the status'),
                    ])
                );
        }
    }

    public static function displayGatewayLogo($gatewayLogo)
    {
        return '<img src="'.$gatewayLogo.'" />';
    }
}

