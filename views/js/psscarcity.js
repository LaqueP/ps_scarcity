(function () {
  // --- Tramos
  function band(q){
    q = parseInt(q,10) || 0;
    if (q <= 0) return null;
    if (q === 1) return 'one';
    if (q < 10)  return 'lt10';
    if (q < 20)  return 'lt20';
    return null;
  }

  // --- Render helpers
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
    document.querySelectorAll('[data-psscarcity]').forEach(function(box){
      renderBox(box, q);
    });
  }

  // --- Lecturas: CE puede no refrescar el dataset, así que priorizamos el DOM visible del stock
  function pick(obj, keys){ for (var i=0;i<keys.length;i++){ var v = obj ? obj[keys[i]] : undefined; var n = parseInt(v,10); if (!isNaN(n)) return n; } return null; }

  function readFromDataset(){
    var el = document.querySelector('#product-details[data-product]');
    if (!el || !el.dataset || !el.dataset.product) return null;
    var data;
    try { data = JSON.parse(el.dataset.product); } catch(_){ return null; }

    var ipaEl = document.querySelector('input[name="id_product_attribute"]');
    var ipa = ipaEl ? (isNaN(parseInt(ipaEl.value,10)) ? ipaEl.value : parseInt(ipaEl.value,10)) : null;

    // 1) Por combinación actual
    if (ipa != null && data.combinations){
      var combo = data.combinations[ipa] || data.combinations[String(ipa)];
      var qCombo = pick(combo, ['quantity','available_quantity','quantity_available','stock','stock_quantity']);
      if (qCombo !== null) return qCombo;
    }
    // 2) Global (a veces es la de la combinación por defecto -> poco fiable en CE)
    return pick(data, ['quantity','available_quantity','quantity_available','stock','stock_quantity']);
  }

  // LECTOR ROBUSTO DEL DOM VISIBLE (no genérico): solo lee el número de .product-quantities span
  function readFromQuantitiesDom(){
    var candidates = [
      '.product-quantities span',           // PS 1.7/8
      '.product-quantities .js-qty',        // algunos temas
      '#product-availability .product-quantities span'
    ];
    for (var i=0;i<candidates.length;i++){
      var el = document.querySelector(candidates[i]);
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
    var v = parseInt(box.getAttribute('data-qty') || 'NaN',10);
    return isNaN(v) ? null : v;
  }

  // Preferencia: DOM visible > dataset CE > SSR
  function readQtyPreferDom(){
    return readFromQuantitiesDom() ?? readFromDataset() ?? readFromContainer();
  }

  // --- Inicial
  (function init(){
    var q = readQtyPreferDom();
    if (q !== null) applyAll(q);
  })();

  // --- Reactivo tras evento de PS/CE: espera un poco y vuelve a leer el DOM
  function onProductUpdatedCE(){
    // varios ticks por si CE pinta tarde
    [20, 60, 120, 200].forEach(function(ms){
      setTimeout(function(){
        var q = readQtyPreferDom();
        if (q !== null) applyAll(q);
      }, ms);
    });
  }

  if (window.prestashop && prestashop.on){
    prestashop.on('updatedProduct', onProductUpdatedCE);
    prestashop.on('updateProduct',  onProductUpdatedCE);
  }

  // --- PLUS: si CE reescribe solo el bloque de cantidades, observamos ese nodo
  var qWrap = document.querySelector('.product-quantities');
  if (qWrap && window.MutationObserver){
    new MutationObserver(function(){
      var q = readFromQuantitiesDom();
      if (q !== null) applyAll(q);
    }).observe(qWrap, {childList:true, subtree:true, characterData:true});
  }
})();
