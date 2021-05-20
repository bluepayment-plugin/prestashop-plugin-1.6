<p class="payment_module test_module" {$selectPayWay}>
    {if $ps_version < '1.5'}
        <a href="{$module_link}" title="{$payment_name}" onclick="{if $selectPayWay}return selectBluePayment(){else}return showBlueButtonNext(){/if}">
            <span>
                <img src="{$module_dir}logo.png" alt="{$payment_name}" />
                {$payment_name}
                ({$payment_name_extra})
            </span>
        </a>

    {else}
        <a class="logo_background" href="{$module_link}" title="{$payment_name}" onclick="{if $selectPayWay}return selectBluePayment(){else}return showBlueButtonNext(){/if}">
            &nbsp;{$payment_name}
            <span>({$payment_name_extra})</span>
        </a>
        {if $showBaner}
            <a href="{$module_link}" title="{$payment_name}" onclick="{if $selectPayWay}return selectBluePayment(){else}return showBlueButtonNext(){/if}">
                <img src="{$module_dir}img/baner.png" style="width: 100%;"/>
            </a>
        {/if}
        {if $selectPayWay}
            <div id="blue_payway" style="display:none;">
                <h1 class="page-heading step-num">{l s='Select bank' mod='bluepayment'}</h1>
                <p style="padding:0 10px 5px 10px;">Zlecenie płatnicze składane jest do Twojego banku za pośrednictwem Blue Media S.A. z siedzibą w Sopocie i zostanie zrealizowane zgodnie z warunkami określonymi przez Twój bank. Po wyborze banku dokonasz autoryzacji płatności.</p>
                <div class="row">
                    {foreach from=$gateways item=row}
                    <div class="col-xs-3" style="padding:5px;">
                        <a href="{$module_link}?gateway_id={$row['gatewayID']}" class="thumbnail" onclick="return selectBluePaymentGateway('{$module_link}?gateway_id={$row['gatewayID']}', {$row['gatewayID']})">
                            {if $showPayWayLogo}<img src="{$row['gatewayLogoUrl']}" alt="{$row['gatewayName']}">{/if}
                            <center>{$row['gatewayName']}</center>
                        </a>
                        <span id="bm-regulation-input-label-{$row['gatewayID']}" style="display: none;">{$row['regulationInputLabel']}</span>
                    </div>
                    {/foreach}
                </div>
                <div id="bm-enclosure" class="row" style="padding:20px;display:none;"></div>
            </div>
        {/if}
    {/if}
    <p class="cart_navigation clearfix" id="cart_navigation" style="display:none;">
        <a class="button btn btn-default button-medium" href="{$module_link}" id="blue_payment_next" onclick="return makeBluePaymentGateway();">
            <span id="bm-place-order">Potwierdzam zamówienie<i class="icon-chevron-right right"></i></span>
        </a>
    </p>
</p>
