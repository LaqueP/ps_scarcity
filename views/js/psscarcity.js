(function () {
  'use strict';
  if (window.__PS_SCARCITY_LOADED) return;
  window.__PS_SCARCITY_LOADED = true;
  window.__PS_SCARCITY_VERSION = '1.1.3';

  function band(q){
    q = parseInt(q,10) || 0;
    if (q <= 0) return null;
    if (q === 1) return 'one';
    if (q < 10)  return 'lt10';
    if (q < 20)  return 'lt20';
    return null;
  }

  function setHtmlForBand(box, b, q){
    var msg;
    if (b === 'one') {
      msg = box.getAttribute('data-msg-one') || '¡Última unidad!';
      box.innerHTML = msg;
    } else if (b === 'lt10') {
      msg = box.getAttribute('data-msg-10') || '¡Quedan %count% unidades — casi agotado!';
      box.innerHTML = msg.replace('%count%', '<strong class="ps-scarcity-count">'+q+'</strong>');
    } else if (b === 'lt20') {
      msg = box.getAttribute('data-msg-20') || '¡Quedan %count% unidades — no lo dejes pasar!';
      box.innerHTML = msg.replace('%count%', '<strong class="ps-scarcity-count">'+q+'</strong>');
    }
    box.style.display = '';
    box.setAttribute('data-band', b);
    box.setAttribute('data-qty', q);
  }

  function updateCountOnly(box, q){
    var num = box.querySelector('.ps-scarcity-count');
    if (num) num.textContent = q;
    box.setAttribute('data-qty', q);
  }

  function renderBox(box, q){
    var b = band(q);
    if (!b){ box.style.display = 'none'; return; }
    var last = box.getAttribute('data-band') || '';
    if (b !== last || !box.querySelector('.ps-scarcity-count')) {
      setHtmlForBand(box, b, q);
    } else {
      updateCountOnly(box, q);
      box.style.display = '';
    }
  }

  function applyAll(q){
    if (q === null || typeof q === 'undefined') return;
    var boxes = document.querySelectorAll('[data-psscarcity]');
    for (var i=0; i<boxes.length; i++){
      renderBox(boxes[i], q);
    }
  }

  function pick(obj, keys){
    for (var i=0;i<keys.length;i++){
      var v = obj ? obj[keys[i]] : undefined;
      var n = parseInt(v,10);
      if (!isNaN(n)) return n;
    }
    return null;
  }

  function readFromDataset(){
    var el = document.querySelector('#product-details[data-product]');
    if (!el || !el.dataset || !el.dataset.product) return null;
    var data;
    try { data = JSON.parse(el.dataset.product); } catch(_){ return null; }

    var ipaEl = document.querySelector('input[name="id_product_attribute"]');
    var ipa = ipaEl ? (isNaN(parseInt(ipaEl.value,10)) ? ipaEl.value : parseInt(ipaEl.value,10)) : null;

    if (ipa != null && data.combinations){
      var combo = data.combinations[ipa] || data.combinations[String(ipa)];
      var qCombo = pick(combo, ['quantity','available_quantity','quantity_available','stock','stock_quantity']);
      if (qCombo !== null) return qCombo;
    }
    return pick(data, ['quantity','available_quantity','quantity_available','stock','stock_quantity']);
  }

  function readFromQuantitiesDom(){
    var selectors = [
      '.product-quantities span',
      '.product-quantities .js-qty',
      '#product-availability .product-quantities span'
    ];
    for (var i=0;i<selectors.length;i++){
      var el = document.querySelector(selectors[i]);
      if (!el) continue;
      var text = (el.getAttribute('data-stock') || el.textContent || '').trim();
      var m = text.match(/-?\d+/);
      if (m) {
        var n = parseInt(m[0],10);
        if (!isNaN(n)) return n;
      }
    }
    return null;
  }

  function readFromContainer(){
    var box = document.querySelector('[data-psscarcity][data-qty]');
    if (!box) return null;
    var n = parseInt(box.getAttribute('data-qty') || 'NaN',10);
    return isNaN(n) ? null : n;
  }

  function readQtyPreferDom(){
    var q = readFromQuantitiesDom();
    if (q === null) q = readFromDataset();
    if (q === null) q = readFromContainer();
    return q;
  }

  (function init(){
    var q = readQtyPreferDom();
    if (q !== null) applyAll(q);
  })();

  function onProductUpdatedCE(){
    var delays = [20, 60, 120, 200];
    for (var i=0;i<delays.length;i++){
      (function(ms){
        setTimeout(function(){
          var q = readQtyPreferDom();
          if (q !== null) applyAll(q);
        }, ms);
      })(delays[i]);
    }
  }

  if (window.prestashop && prestashop.on){
    prestashop.on('updatedProduct', onProductUpdatedCE);
    prestashop.on('updateProduct',  onProductUpdatedCE);
  }

  var qWrap = document.querySelector('.product-quantities');
  if (qWrap && window.MutationObserver){
    new MutationObserver(function(){
      var q = readFromQuantitiesDom();
      if (q !== null) applyAll(q);
    }).observe(qWrap, {childList:true, subtree:true, characterData:true});
  }
})();
