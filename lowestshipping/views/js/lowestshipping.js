/**
 * lowestshipping — dynamiczna aktualizacja po zmianie kombinacji (PrestaShop 9).
 * @author Maxsoft
 * @license MIT
 */
(function () {
  'use strict';

  function getBlock() {
    return document.getElementById('lowestshipping-product-block');
  }

  function setCarrierLine(block, text, visible) {
    var el = block.querySelector('#lowest-shipping-carrier');
    if (!el) {
      return;
    }
    if (visible && text) {
      el.textContent = text;
      el.style.display = '';
    } else {
      el.textContent = '';
      el.style.display = 'none';
    }
  }

  function fetchLowest(block, idProduct, idProductAttribute) {
    if (!block) {
      return;
    }

    var ajaxUrl = block.getAttribute('data-ajax-url');
    var token = block.getAttribute('data-token') || '';
    if (!ajaxUrl) {
      return;
    }

    var url =
      ajaxUrl +
      (ajaxUrl.indexOf('?') >= 0 ? '&' : '?') +
      'ajax=1&action=lowestshipping&id_product=' +
      encodeURIComponent(String(idProduct)) +
      '&id_product_attribute=' +
      encodeURIComponent(String(idProductAttribute || 0)) +
      '&token=' +
      encodeURIComponent(token);

    fetch(url, {
      method: 'GET',
      credentials: 'same-origin',
      headers: { Accept: 'application/json' },
    })
      .then(function (r) {
        return r.json();
      })
      .then(function (data) {
        if (!data || !data.success) {
          return;
        }

        var prefixEl = block.querySelector('.lowest-shipping-prefix');
        var priceEl = block.querySelector('#lowest-shipping-price');

        if (prefixEl && typeof data.prefix === 'string') {
          prefixEl.textContent = data.prefix;
        }

        if (priceEl) {
          if (data.available && data.formatted_price) {
            priceEl.innerHTML = data.formatted_price;
          } else if (data.hint) {
            priceEl.textContent = data.hint;
          } else {
            priceEl.innerHTML = '';
          }
        }

        setCarrierLine(block, data.carrier_line || '', !!(data.available && data.carrier_line));

        var show =
          (data.available && !!data.formatted_price) || (!!data.hint && !data.available);
        block.style.display = show ? '' : 'none';
      })
      .catch(function () {});
  }

  function onCombinationChange(event) {
    var block = getBlock();
    if (!block || !event || !event.detail) {
      return;
    }

    var idProduct =
      parseInt(String(event.detail.id_product || block.getAttribute('data-id-product') || '0'), 10) || 0;
    var idPa =
      parseInt(String(event.detail.id_product_attribute || '0'), 10) || 0;

    if (!idProduct) {
      return;
    }

    block.setAttribute('data-id-product', String(idProduct));
    block.setAttribute('data-id-product-attribute', String(idPa));
    fetchLowest(block, idProduct, idPa);
  }

  document.addEventListener('DOMContentLoaded', function () {
    var block = getBlock();
    if (!block || typeof prestashop === 'undefined') {
      return;
    }

    prestashop.on('updatedProduct', onCombinationChange);
    prestashop.on('updateProduct', onCombinationChange);
  });
})();
