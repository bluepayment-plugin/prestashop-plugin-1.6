{*
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
 *}
<p style="text-align: center;"><img src="{$module_dir}img/ajax-loader-big.gif" /></p>
<p style="text-align: center;"><b>{l s='Please wait..' mod='bluepayment'}</b></p>
<p style="text-align: center;"><img src="{$module_dir}logo.jpg" /></p>
<form action="{$form_url}" method="POST" id="bluepaymentForm" name="bluepaymentForm" target="_parent">
{foreach from=$params key=k item=v}
<input type="hidden" name="{$k}" value="{$v}"/>
{/foreach}
</form>
{literal}
<script language="JavaScript">
document.bluepaymentForm.submit();
</script>
{/literal}