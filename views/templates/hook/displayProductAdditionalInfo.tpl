{**
 * Karta produktu — Smarty (PrestaShop FO).
 *}
<div
  id="lowestshipping-product-block"
  class="lowest-shipping-box product-extra-info"
  data-id-product="{$lowestshipping_id_product|intval}"
  data-id-product-attribute="{$lowestshipping_id_product_attribute|intval}"
  data-ajax-url="{$lowestshipping_ajax_url|escape:'html':'UTF-8'}"
  data-token="{$lowestshipping_token|escape:'html':'UTF-8'}"
>
  <p class="lowest-shipping-line">
    <strong class="lowest-shipping-prefix">{$lowestshipping_prefix|escape:'html':'UTF-8'}</strong>
    <span id="lowest-shipping-price" class="lowest-shipping-price-value">
      {if $lowestshipping_available}{$lowestshipping_formatted nofilter}{else}{$lowestshipping_hint|escape:'html':'UTF-8'}{/if}
    </span>
  </p>
  {if $lowestshipping_description}
    <small class="lowest-shipping-desc">{$lowestshipping_description|escape:'htmlall':'UTF-8'}</small>
  {/if}
  {if $lowestshipping_carrier_line}
    <small class="lowest-shipping-desc lowest-shipping-carrier">{$lowestshipping_carrier_line|escape:'html':'UTF-8'}</small>
  {/if}
</div>
