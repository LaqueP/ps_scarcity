(function () {
  'use strict';

  // Versión visible SIEMPRE, incluso si ya estaba cargado
  window.__PS_SCARCITY_VERSION = '1.2.3';
  if (window.__PS_SCARCITY_LOADED) return;
  window.__PS_SCARCITY_LOADED = true;

  var lastEventQty = null;

  /* ------------ CE stock gate (mostrar/ocultar por estado) ------------ */
  function ceStockNode(){ return document.querySelector('.ce-product-stock'); }

  function isBackorderCE(){
    var el = ceStockNode(); if (!el || !el.classList) return false;
    return el.classList.contains('ce-product-stock--backorder')
        || el.classList.contains('ce-product-stock--preorder')
        || el.classList.contains('ce-product-stock--out-of-stock')
        || el.classList.contains('ce-product-stock--not-available');
  }

  function isClearlyInStockCE(){
    var el = ceStockNode(); if (!el || !el.classList) return null; // desconocido
    if (isBackorderCE()) return false;
    if (el.classList.contains('ce-product-stock--in-stock')) return true;
    return null;
  }

  /* ------------------------ Bandas y render --------------------------- */
  function band(q){ q=parseInt(q,10)||0; if(q<=0)return null; if(q===1)return'one'; if(q<10)return'lt10'; if(q<20)return'lt20'; return null; }

  function hideBox(box){
    box.style.display='none';
    box.setAttribute('data-band','');
    box.setAttribute('data-qty','0');
  }
  function setHtmlForBand(box,b,q){
    var msg;
    if(b==='one'){ msg=box.getAttribute('data-msg-one')||'¡Última unidad!'; box.innerHTML=msg; }
    else if(b==='lt10'){ msg=box.getAttribute('data-msg-10')||'¡Quedan %count% unidades — casi agotado!'; box.innerHTML=msg.replace('%count%','<strong class="ps-scarcity-count">'+q+'</strong>'); }
    else if(b==='lt20'){ msg=box.getAttribute('data-msg-20')||'¡Quedan %count% unidades — no lo dejes pasar!'; box.innerHTML=msg.replace('%count%','<strong class="ps-scarcity-count">'+q+'</strong>'); }
    box.style.display=''; box.setAttribute('data-band',b||''); box.setAttribute('data-qty',q);
  }
  function updateCountOnly(box,q){
    var num=box.querySelector('.ps-scarcity-count');
    if(num) num.textContent=q;
    box.setAttribute('data-qty',q);
  }
  function renderBox(box,q){
    var b=band(q);
    if(!b){ hideBox(box); return; }
    var last=box.getAttribute('data-band')||'';
    if(b!==last || !box.querySelector('.ps-scarcity-count')) setHtmlForBand(box,b,q);
    else { updateCountOnly(box,q); box.style.display=''; }
  }
  function applyAll(q){ document.querySelectorAll('[data-psscarcity]').forEach(function(b){ renderBox(b,q); }); }
  function hideAll(){ document.querySelectorAll('[data-psscarcity]').forEach(hideBox); }

  /* --------------------- Lectores de cantidad ------------------------- */
  function pick(obj, keys){
    for (var i=0;i<keys.length;i++){
      var v = obj ? obj[keys[i]] : undefined;
      var n = parseInt(v,10);
      if (!isNaN(n)) return n;
    }
    return null;
  }

  // Evento Prestashop/CE
  function readFromEventPayload(e){
    if(!e) return null;
    var q = null;
    if (e.product) q = pick(e.product, ['quantity','available_quantity','quantity_available','stock','stock_quantity']);
    if (q===null && e.resp && e.resp.product) q = pick(e.resp.product, ['quantity','available_quantity','quantity_available','stock','stock_quantity']);
    return q;
  }

  // DOM visible (agnóstico de idioma: solo dígitos)
  function readFromQuantitiesDom(){
    var sels=['.product-quantities span','.product-quantities .js-qty','#product-availability .product-quantities span'];
    for (var i=0;i<sels.length;i++){
      var el=document.querySelector(sels[i]); if(!el) continue;
      var text=(el.getAttribute('data-stock')||el.textContent||'').trim();
      var m=text.match(/-?\d+/);
      if(m){ var n=parseInt(m[0],10); if(!isNaN(n)) return n; }
    }
    return null;
  }

  // Dataset del producto (mejor detección de IPA)
  function currentIPAFromDOMorData(data){
    // 1) IPA desde inputs/selects clásicos
    var cand = document.querySelector('[name="id_product_attribute"]:checked')
           || document.querySelector('select[name="id_product_attribute"]')
           || document.querySelector('input[name="id_product_attribute"]')
           || document.querySelector('[name="id_product_attribute"]');
    if (cand && cand.value !== undefined && cand.value !== '') {
      var v = parseInt(cand.value,10);
      return isNaN(v) ? String(cand.value) : v;
    }
    // 2) IPA embebido en el dataset (temas/CE)
    var k = pick(data, ['id_product_attribute','ipa']); // devuelve número si existe
    if (k !== null) return k;
    // 3) Nada
    return null;
  }

  function readFromDataset(){
    var el=document.querySelector('#product-details[data-product]');
    if(!el || !el.dataset || !el.dataset.product) return null;
    var data; try { data = JSON.parse(el.dataset.product); } catch(_){ return null; }

    var ipa = currentIPAFromDOMorData(data);
    if (ipa != null && data.combinations){
      var combo = data.combinations[ipa] || data.combinations[String(ipa)];
      var qc = pick(combo, ['quantity','available_quantity','quantity_available','stock','stock_quantity']);
      if (qc !== null) return qc;
    }
    return pick(data, ['quantity','available_quantity','quantity_available','stock','stock_quantity']);
  }

  // SSR container (solo inicial)
  function readFromContainer(){
    var box=document.querySelector('[data-psscarcity][data-qty]');
    if(!box) return null;
    var n=parseInt(box.getAttribute('data-qty')||'NaN',10);
    return isNaN(n)?null:n;
  }

  /* --------------- Estrategia + CE gate aplicado ---------------------- */
  function gateAndReadInitial(){
    var gate = isClearlyInStockCE();
    if (gate === false){ hideAll(); return null; } // backorder/sin stock → ocultar
    var q = readFromQuantitiesDom(); if(q!==null) return q;
    q = readFromDataset();          if(q!==null) return q;
    q = readFromContainer();        return q; // puede ser null
  }

  // FIX: si el evento trae cantidad explícita, úsala (incluye 0) aunque gate sea ambiguo
  function gateAndReadRuntime(){
    if (lastEventQty !== null){
      if (lastEventQty > 0) return lastEventQty;
      if (lastEventQty === 0){ hideAll(); return null; }
    }
    var gate = isClearlyInStockCE();
    if (gate === false){ hideAll(); return null; }
    var q = readFromQuantitiesDom(); if(q!==null) return q;
    q = readFromDataset();          if(q!==null) return q;
    return null;
  }

  /* --------------------------- Ciclos --------------------------------- */
  var debounceTimer=null;
  function scheduleTick(ms){ if(debounceTimer) clearTimeout(debounceTimer); debounceTimer=setTimeout(tick,ms||50); }
  function tick(){ var q=gateAndReadRuntime(); if(q!==null) applyAll(q); }

  (function init(){
    var q = gateAndReadInitial();
    if (q !== null) applyAll(q);
    // Rechecks por render tardío de CE
    setTimeout(tick,60); setTimeout(tick,200);
  })();

  // Eventos PS/CE
  function onProductUpdatedCE(e){
    var qEv = readFromEventPayload(e);
    if (qEv !== null) lastEventQty = qEv;
    [0,20,60,120,200,400].forEach(function(ms){ setTimeout(tick, ms); });
  }
  if (window.prestashop && prestashop.on){
    prestashop.on('updatedProduct', onProductUpdatedCE);
    prestashop.on('updateProduct',  onProductUpdatedCE);
  }

  // Observadores (clases CE / cantidades / banner)
  if (window.MutationObserver){
    new MutationObserver(function(muts){
      for (var i=0;i<muts.length;i++){
        var m=muts[i], t=m.target;
        if (m.type==='attributes' && t && t.classList && t.classList.contains('ce-product-stock')){ scheduleTick(20); return; }
        if (t && t.closest && (t.closest('.product-quantities') || t.closest('[data-psscarcity]') || t.closest('.ce-product-stock'))){ scheduleTick(30); return; }
        if (m.addedNodes && m.addedNodes.length){
          for (var j=0;j<m.addedNodes.length;j++){
            var n=m.addedNodes[j];
            if (n.querySelector && (n.querySelector('.product-quantities') || n.querySelector('[data-psscarcity]') || n.querySelector('.ce-product-stock'))){ scheduleTick(30); return; }
          }
        }
      }
    }).observe(document.body,{subtree:true,childList:true,characterData:true,attributes:true});
  }
})();
