{**
 * 2007-2026 PrestaShop — lowestshipping module (product page block).
 *
 * @author    Maxsoft
 * @copyright 2007-2026 Maxsoft
 * @license   https://opensource.org/licenses/MIT MIT License
 *
 * Karta produktu — Smarty (nazwa pliku mała litera dla zgodności z Linux).
 *}
<div
  id="lowestshipping-product-block"
  class="lowest-shipping-box product-extra-info"
  data-id-product="{$lowestshipping_id_product|intval}"
  data-id-product-attribute="{$lowestshipping_id_product_attribute|intval}"
  data-ajax-url="{$lowestshipping_ajax_url|escape:'html':'UTF-8'}"
  data-token="{$lowestshipping_token|escape:'html':'UTF-8'}"
>
  <div class="lowest-shipping-main">
    <strong class="lowest-shipping-prefix">{$lowestshipping_prefix|escape:'html':'UTF-8'}</strong>
    <span id="lowest-shipping-price" class="lowest-shipping-price-value">
      {if $lowestshipping_available}{$lowestshipping_formatted|escape:'html':'UTF-8'}{else}{$lowestshipping_hint|escape:'html':'UTF-8'}{/if}
    </span>
  </div>
  {if $lowestshipping_description}
    <small id="lowest-shipping-desc-below" class="lowest-shipping-desc">{$lowestshipping_description|escape:'htmlall':'UTF-8'}</small>
  {/if}
  <small id="lowest-shipping-carrier" class="lowest-shipping-carrier"{if !$lowestshipping_carrier_line} style="display:none"{/if}>{$lowestshipping_carrier_line|escape:'html':'UTF-8'}</small>
</div>
