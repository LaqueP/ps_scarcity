(function () {
  'use strict';

  // IDs usados en el HTML del panel
  var ID_LANG   = 'psscarcity-lang';
  var ID_Q      = 'psscarcity-q';
  var ID_QLABEL = 'psscarcity-q-label';
  var ID_PREV   = 'psscarcity-preview';
  var ID_SMARTY = 'psscarcity-short-smarty';
  var ID_ID     = 'psscarcity-short-id';

  // Claves de config (coinciden con las constantes del módulo)
  var CFG_ONE = 'PS_SCARCITY_TEXT_ONE';
  var CFG_10  = 'PS_SCARCITY_TEXT_LT10';
  var CFG_20  = 'PS_SCARCITY_TEXT_LT20';

  function $(id){ return document.getElementById(id); }

  function band(q){
    q = parseInt(q,10) || 0;
    if (q <= 0) return null;
    if (q === 1) return 'one';
    if (q < 10)  return 'lt10';
    if (q < 20)  return 'lt20';
    return null;
  }

  function getVal(cfg, idLang){
    var sel = "[name='" + cfg + "_" + idLang + "']";
    var el = document.querySelector(sel);
    return el ? el.value : "";
  }

  function copyToClipboard(text){
    try{
      if (navigator.clipboard && navigator.clipboard.writeText){
        navigator.clipboard.writeText(text);
      } else {
        var ta = document.createElement('textarea');
        ta.value = text; document.body.appendChild(ta);
        ta.select(); document.execCommand('copy'); document.body.removeChild(ta);
      }
    }catch(_){}
  }

  function render(){
    var langSel = $(ID_LANG), qInput = $(ID_Q), qLabel = $(ID_QLABEL), prev = $(ID_PREV);
    if (!langSel || !qInput || !qLabel || !prev) return;

    var idLang = parseInt(langSel.value,10);
    var q = parseInt(qInput.value,10);
    qLabel.textContent = q;

    var b = band(q), msg = '';
    if (!b){
      prev.style.display = 'none';
      prev.innerHTML = '';
      return;
    }

    if (b === 'one') {
      msg = getVal(CFG_ONE, idLang) || '¡Última unidad con envío inmediato!';
      prev.innerHTML = msg;
    } else if (b === 'lt10') {
      msg = getVal(CFG_10, idLang) || '¡Quedan %count% unidades — casi agotado!';
      prev.innerHTML = msg.replace('%count%', '<strong>'+q+'</strong>');
    } else {
      msg = getVal(CFG_20, idLang) || '¡Quedan %count% unidades — no lo dejes pasar!';
      prev.innerHTML = msg.replace('%count%', '<strong>'+q+'</strong>');
    }
    prev.style.display = '';
  }

  function init(){
    // Sólo si estamos en la página del módulo (elementos presentes)
    if (!$(ID_PREV)) return;

    // Render inicial
    render();

    // Cambios en idioma / slider
    $(ID_LANG).addEventListener('change', render);
    $(ID_Q).addEventListener('input', render);

    // Escuchar cambios en inputs multilenguaje para refrescar preview
    document.addEventListener('input', function(e){
      var n = (e.target && e.target.name) || '';
      if (n.indexOf(CFG_ONE+'_') === 0 || n.indexOf(CFG_10+'_') === 0 || n.indexOf(CFG_20+'_') === 0){
        render();
      }
    });

    // Copiar shortcodes
    var btnSmarty = document.getElementById('psscarcity-copy-smarty');
    var btnId     = document.getElementById('psscarcity-copy-id');
    if (btnSmarty && $(ID_SMARTY)) {
      btnSmarty.addEventListener('click', function(){
        copyToClipboard($(ID_SMARTY).textContent.trim());
      });
    }
    if (btnId && $(ID_ID)) {
      btnId.addEventListener('click', function(){
        copyToClipboard($(ID_ID).textContent.trim());
      });
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
