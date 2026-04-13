/**
 * lowestshipping — karta produktu: kombinacja + dynamiczna ilość (#quantity_wanted).
 * Koszt dostawy liczony na żądanie (getcost); nie cache'ujemy — zależy od quantity.
 * Link do endpointu: getModuleLink w Smarty (data-getcost-url); nie składamy ręcznie z prestashop.urls.
 *
 * @author Maxsoft
 * @license MIT
 */
(function () {
  'use strict';

  var DEBOUNCE_MS = 150;

  function debounce(fn, wait) {
    var t;
    return function () {
      var ctx = this;
      var args = arguments;
      clearTimeout(t);
      t = setTimeout(function () {
        fn.apply(ctx, args);
      }, wait);
    };
  }

  function getBlock() {
    return document.getElementById('lowest-shipping-box');
  }

  function getQuantityWanted() {
    var input = document.querySelector('#quantity_wanted');
    if (!input || input.value === '') {
      return 1;
    }
    var n = parseInt(String(input.value), 10);
    if (!n || n < 1) {
      return 1;
    }
    return n;
  }

  function setLoading(block, on) {
    if (!block) {
      return;
    }
    if (on) {
      block.classList.add('is-loading');
    } else {
      block.classList.remove('is-loading');
    }
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

  function applyPayload(block, data) {
    if (!data || !data.success) {
      if (data && data.message) {
        var main = block.querySelector('#lowest-shipping-main-inner');
        if (main) {
          main.innerHTML =
            '<span id="lowest-shipping-price" class="lowest-shipping-price-value"></span>';
          var pe = main.querySelector('#lowest-shipping-price');
          if (pe) {
            pe.textContent = data.message;
          }
        }
      }
      setCarrierLine(block, '', false);
      return;
    }

    var mainInner = block.querySelector('#lowest-shipping-main-inner');
    if (mainInner && typeof data.html === 'string' && data.html !== '') {
      mainInner.innerHTML = data.html;
    }

    setCarrierLine(
      block,
      data.carrier_line || '',
      !!(data.available && data.carrier_line),
    );

    var show =
      (data.available && (data.formatted || data.cost)) ||
      (!!data.html && !data.available) ||
      (data.available === false && data.html);
    block.style.display = show ? '' : 'none';
  }

  function updateLowestShipping() {
    var block = getBlock();
    if (!block) {
      return;
    }

    var baseUrl = block.getAttribute('data-getcost-url');
    var token = block.getAttribute('data-token') || '';
    if (!baseUrl) {
      return;
    }

    var idProduct = parseInt(block.getAttribute('data-id-product') || '0', 10) || 0;
    var idPa = parseInt(block.getAttribute('data-id-product-attribute') || '0', 10) || 0;
    var quantity = getQuantityWanted();

    if (!idProduct) {
      return;
    }

    var url =
      baseUrl +
      (baseUrl.indexOf('?') >= 0 ? '&' : '?') +
      'id_product=' +
      encodeURIComponent(String(idProduct)) +
      '&id_product_attribute=' +
      encodeURIComponent(String(idPa)) +
      '&quantity=' +
      encodeURIComponent(String(quantity)) +
      '&token=' +
      encodeURIComponent(token);

    setLoading(block, true);

    fetch(url, {
      method: 'GET',
      credentials: 'same-origin',
      cache: 'no-store',
      headers: { Accept: 'application/json' },
    })
      .then(function (r) {
        return r.json();
      })
      .then(function (data) {
        applyPayload(block, data);
      })
      .catch(function () {
        setCarrierLine(block, '', false);
      })
      .finally(function () {
        setLoading(block, false);
      });
  }

  var updateLowestShippingDebounced = debounce(updateLowestShipping, DEBOUNCE_MS);

  function onUpdatedProduct(event) {
    var block = getBlock();
    if (block && event && event.detail) {
      var idProduct =
        parseInt(String(event.detail.id_product || block.getAttribute('data-id-product') || '0'), 10) ||
        0;
      var idPa = parseInt(String(event.detail.id_product_attribute || '0'), 10) || 0;
      if (idProduct) {
        block.setAttribute('data-id-product', String(idProduct));
      }
      if (event.detail.id_product_attribute !== undefined) {
        block.setAttribute('data-id-product-attribute', String(idPa));
      }
    }
    updateLowestShipping();
  }

  function bindQuantityField() {
    var qty = document.querySelector('#quantity_wanted');
    if (!qty) {
      return;
    }
    if (typeof jQuery !== 'undefined') {
      jQuery(qty).on('change', updateLowestShipping);
      jQuery(qty).on('input', updateLowestShippingDebounced);
    } else {
      qty.addEventListener('change', updateLowestShipping);
      qty.addEventListener('input', updateLowestShippingDebounced);
    }
  }

  document.addEventListener('DOMContentLoaded', function () {
    var block = getBlock();
    if (!block || typeof prestashop === 'undefined') {
      return;
    }

    prestashop.on('updatedProduct', onUpdatedProduct);
    prestashop.on('updateProduct', onUpdatedProduct);

    bindQuantityField();

    updateLowestShipping();
  });
})();
