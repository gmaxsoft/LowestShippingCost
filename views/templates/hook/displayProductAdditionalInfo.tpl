{**
 * Product page block — native Smarty template (FO). Variables assigned in lowestshipping.php.
 *}
<div
  id="lowestshipping-product-block"
  class="lowestshipping-product-block product-extra-info"
  data-id-product="{$lowestshipping_id_product|intval}"
  data-id-product-attribute="{$lowestshipping_id_product_attribute|intval}"
  data-ajax-url="{$lowestshipping_ajax_url|escape:'html':'UTF-8'}"
  data-token="{$lowestshipping_token|escape:'html':'UTF-8'}"
>
  <p class="lowestshipping-line">
    <span class="lowestshipping-prefix">{$lowestshipping_prefix|escape:'html':'UTF-8'}</span>
    <span class="lowestshipping-price">{$lowestshipping_formatted nofilter}</span>
  </p>
</div>
