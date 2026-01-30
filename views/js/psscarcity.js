(function () {
  'use strict';

  if (window.__PS_SCARCITY_LOADED) return;
  window.__PS_SCARCITY_LOADED = true;

  var lastEventQty = null;

  function hideBox(box){
    box.style.display = 'none';
    box.setAttribute('data-band', '');
    box.setAttribute('data-qty', '0');
  }

  function band(box, qty){
    qty = parseInt(qty, 10) || 0;
    if(qty <= 0) return null; // 0 unidades → ocultar
    if(qty === 1) return 'one';

    var lim10 = parseInt(box.dataset.limitLt10) || 10;
    var lim20 = parseInt(box.dataset.limitLt20) || 20;

    if(qty < lim10) return 'lt10';
    if(qty >= lim10 && qty < lim20) return 'lt20';
    return null; // > limitLt20 → ocultar
  }

  function setHtmlForBand(box, b, qty){
    var msg;
    if(b === 'one') msg = box.dataset.msgOne || 'Last unit available for immediate dispatch!';
    else if(b === 'lt10') msg = (box.dataset.msg10 || '<10 - Only %count% units left for immediate dispatch!').replace('%count%', '<strong>'+qty+'</strong>');
    else if(b === 'lt20') msg = (box.dataset.msg20 || '<20 - Less than %count% units left for immediate dispatch!').replace('%count%', '<strong>'+qty+'</strong>');
    box.innerHTML = msg;
    box.setAttribute('data-band', b || '');
    box.setAttribute('data-qty', qty);
    box.style.display = '';
  }

  function updateCountOnly(box, qty){
    var num = box.querySelector('.ps-scarcity-count');
    if(num) num.textContent = qty;
    box.setAttribute('data-qty', qty);
  }

  function renderBox(box, qty){
    // Prioridad: si está integrado manualmente, no tocar
    if(box.dataset.hookManual === "1") return;

    var b = band(box, qty);
    if(!b){ hideBox(box); return; }

    var lastBand = box.getAttribute('data-band') || '';
    if(b !== lastBand || !box.querySelector('.ps-scarcity-count')){
      setHtmlForBand(box, b, qty);
    } else {
      updateCountOnly(box, qty);
      box.style.display = '';
    }
  }

  function applyAll(qty){
    document.querySelectorAll('[data-psscarcity]').forEach(function(box){
      renderBox(box, qty);
    });
  }

  function hideAll(){
    document.querySelectorAll('[data-psscarcity]').forEach(hideBox);
  }

  function pick(obj, keys){
    for(var i=0;i<keys.length;i++){
      var v = obj ? obj[keys[i]] : undefined;
      var n = parseInt(v,10);
      if(!isNaN(n)) return n;
    }
    return null;
  }

  function readFromQuantitiesDom(){
    var selectors = ['.product-quantities span', '.product-quantities .js-qty', '#product-availability .product-quantities span'];
    for(var i=0;i<selectors.length;i++){
      var el = document.querySelector(selectors[i]);
      if(!el) continue;
      var text = (el.getAttribute('data-stock') || el.textContent || '').trim();
      var m = text.match(/-?\d+/);
      if(m){ var n=parseInt(m[0],10); if(!isNaN(n)) return n; }
    }
    return null;
  }

  function readFromDataset(){
    var el = document.querySelector('#product-details[data-product]');
    if(!el || !el.dataset || !el.dataset.product) return null;
    var data; 
    try { data = JSON.parse(el.dataset.product); } 
    catch(_) { return null; }

    var ipaEl = document.querySelector('[name="id_product_attribute"]:checked')
             || document.querySelector('select[name="id_product_attribute"]')
             || document.querySelector('input[name="id_product_attribute"]');
    var ipa = ipaEl ? parseInt(ipaEl.value,10) : null;

    if(ipa != null && data.combinations){
      var combo = data.combinations[ipa] || data.combinations[String(ipa)];
      var qty = pick(combo, ['quantity','available_quantity','quantity_available','stock','stock_quantity']);
      if(qty !== null) return qty;
    }

    return pick(data, ['quantity','available_quantity','quantity_available','stock','stock_quantity']);
  }

  function readFromContainer(){
    var box = document.querySelector('[data-psscarcity][data-qty]');
    if(!box) return null;
    var n = parseInt(box.getAttribute('data-qty') || 'NaN',10);
    return isNaN(n) ? null : n;
  }

  function gateAndReadRuntime(){
    if(lastEventQty !== null){
      if(lastEventQty > 0) return lastEventQty;
      if(lastEventQty === 0){ hideAll(); return null; }
    }
    var qty = readFromQuantitiesDom(); if(qty !== null) return qty;
    qty = readFromDataset(); if(qty !== null) return qty;
    return readFromContainer();
  }

  function scheduleTick(ms){ setTimeout(tick, ms || 50); }
  function tick(){ var qty = gateAndReadRuntime(); if(qty !== null) applyAll(qty); }

  function init(){
    var qty = gateAndReadRuntime(); if(qty !== null) applyAll(qty);

    if(window.prestashop && prestashop.on){
      prestashop.on('updatedProduct', function(e){
        lastEventQty = pick(e.product,['quantity','available_quantity','quantity_available','stock','stock_quantity']);
        [0,20,60,120,200,400].forEach(function(ms){ scheduleTick(ms); });
      });
      prestashop.on('updateProduct', function(e){
        lastEventQty = pick(e.product,['quantity','available_quantity','quantity_available','stock','stock_quantity']);
        [0,20,60,120,200,400].forEach(function(ms){ scheduleTick(ms); });
      });
    }

    if(window.MutationObserver){
      new MutationObserver(function(muts){
        muts.forEach(function(m){
          var t = m.target;
          if(m.type==='attributes' && t && t.classList && t.classList.contains('ce-product-stock')) scheduleTick(20);
          if(t && t.closest && (t.closest('.product-quantities') || t.closest('[data-psscarcity]') || t.closest('.ce-product-stock'))) scheduleTick(30);
        });
      }).observe(document.body,{subtree:true,childList:true,characterData:true,attributes:true});
    }
  }

  if(document.readyState === 'loading') document.addEventListener('DOMContentLoaded',init);
  else init();

})();
