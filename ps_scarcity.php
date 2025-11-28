<?php
if (!defined('_PS_VERSION_')) { exit; }

class Ps_Scarcity extends Module
{
    /* Config keys */
    const CFG_TEXT_ONE         = 'PS_SCARCITY_TEXT_ONE';
    const CFG_TEXT_LT10        = 'PS_SCARCITY_TEXT_LT10';
    const CFG_TEXT_LT20        = 'PS_SCARCITY_TEXT_LT20';
    const CFG_AUTO_AFTER_PRICE = 'PS_SCARCITY_AUTO_AFTER_PRICE';
    const CFG_SINGLETON        = 'PS_SCARCITY_SINGLETON';

    public function __construct()
    {
        $this->name = 'ps_scarcity';
        $this->version = '1.1.2';
        $this->author = 'TuNombre';
        $this->tab = 'front_office_features';
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('Scarcity Banner (CE + Smarty)');
        $this->description = $this->l('Banner de escasez configurable: 1 unidad, <10 y <20 con %count% como unidades reales. Compatible con Creative Elements y plantillas Smarty.');
        $this->ps_versions_compliancy = ['min' => '8.0.0', 'max' => _PS_VERSION_];
    }

    public function install()
    {
        $langs = Language::getIDs(false);
        $defOne  = [];
        $defLt10 = [];
        $defLt20 = [];
        foreach ($langs as $id_lang) {
            $defOne[$id_lang]  = '¡Última unidad con envío inmediato!';
            $defLt10[$id_lang] = '¡Quedan %count% unidades — casi agotado!';
            $defLt20[$id_lang] = '¡Quedan %count% unidades — no lo dejes pasar!';
        }

        return parent::install()
            /* hooks para insertar el banner */
            && $this->registerHook('displayScarcityBanner')    // uso general (Smarty/CE)
            && $this->registerHook('displayScarcitySpecial')   // “especial” a demanda
            && $this->registerHook('displayProductPriceBlock') // after_price
            && $this->registerHook('displayAfterPrice')        // compat. algunos temas
            && $this->registerHook('actionFrontControllerSetMedia')
            /* configuración por defecto */
            && Configuration::updateValue(self::CFG_TEXT_ONE,  $defOne,  false)
            && Configuration::updateValue(self::CFG_TEXT_LT10, $defLt10, false)
            && Configuration::updateValue(self::CFG_TEXT_LT20, $defLt20, false)
            && Configuration::updateValue(self::CFG_AUTO_AFTER_PRICE, 1)
            && Configuration::updateValue(self::CFG_SINGLETON, 1);
    }

    public function uninstall()
    {
        return Configuration::deleteByName(self::CFG_TEXT_ONE)
            && Configuration::deleteByName(self::CFG_TEXT_LT10)
            && Configuration::deleteByName(self::CFG_TEXT_LT20)
            && Configuration::deleteByName(self::CFG_AUTO_AFTER_PRICE)
            && Configuration::deleteByName(self::CFG_SINGLETON)
            && parent::uninstall();
    }

    /* ----------------- Back Office ----------------- */

    public function getContent()
    {
        $output = '';

        if (Tools::isSubmit('submitPsScarcity')) {
            $langs = Language::getIDs(false);
            $vOne = []; $v10 = []; $v20 = [];
            foreach ($langs as $id_lang) {
                $vOne[$id_lang]  = (string)Tools::getValue(self::CFG_TEXT_ONE.'_'.$id_lang,  '');
                $v10[$id_lang]   = (string)Tools::getValue(self::CFG_TEXT_LT10.'_'.$id_lang, '');
                $v20[$id_lang]   = (string)Tools::getValue(self::CFG_TEXT_LT20.'_'.$id_lang, '');
            }
            Configuration::updateValue(self::CFG_TEXT_ONE,  $vOne, false);
            Configuration::updateValue(self::CFG_TEXT_LT10, $v10,  false);
            Configuration::updateValue(self::CFG_TEXT_LT20, $v20,  false);

            Configuration::updateValue(self::CFG_AUTO_AFTER_PRICE, (int)Tools::getValue(self::CFG_AUTO_AFTER_PRICE));
            Configuration::updateValue(self::CFG_SINGLETON,        (int)Tools::getValue(self::CFG_SINGLETON));

            $output .= $this->displayConfirmation($this->l('Configuración guardada'));
        }

        $helper = new HelperForm();
        $helper->show_toolbar = false;
        $helper->default_form_language = (int)$this->context->language->id;
        $helper->allow_employee_form_lang = true;
        $helper->identifier = $this->identifier;
        $helper->submit_action = 'submitPsScarcity';
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false)
            .'&configure='.$this->name.'&tab_module='.$this->tab.'&module_name='.$this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');

        $languages = Language::getLanguages(false);

        $fields_form = [
            'form' => [
                'legend' => [
                    'title' => $this->l('Banner de escasez'),
                    'icon'  => 'icon-cogs'
                ],
                'description' => $this->l('Define mensajes distintos para 1 unidad, menos de 10 y menos de 20. Usa %count% como marcador del número real.'),
                'input' => [
                    [
                        'type' => 'text',
                        'label' => $this->l('Mensaje para 1 unidad'),
                        'name' => self::CFG_TEXT_ONE,
                        'lang' => true,
                        'required' => true,
                        'desc' => $this->l('Ej.: "¡Última unidad con envío inmediato!" (no usa %count%).'),
                    ],
                    [
                        'type' => 'text',
                        'label' => $this->l('Mensaje para < 10 unidades'),
                        'name' => self::CFG_TEXT_LT10,
                        'lang' => true,
                        'required' => true,
                        'desc' => $this->l('Ej.: "¡Quedan %count% unidades — casi agotado!"'),
                    ],
                    [
                        'type' => 'text',
                        'label' => $this->l('Mensaje para < 20 unidades'),
                        'name' => self::CFG_TEXT_LT20,
                        'lang' => true,
                        'required' => true,
                        'desc' => $this->l('Ej.: "¡Quedan %count% unidades — no lo dejes pasar!"'),
                    ],
                    [
                        'type' => 'switch',
                        'label' => $this->l('Insertar automáticamente bajo el precio'),
                        'name' => self::CFG_AUTO_AFTER_PRICE,
                        'is_bool' => true,
                        'values' => [
                            ['id' => 'auto_on',  'value' => 1, 'label' => $this->l('Sí')],
                            ['id' => 'auto_off', 'value' => 0, 'label' => $this->l('No')],
                        ],
                        'desc' => $this->l('Si vas a insertarlo con Creative Elements o Smarty, desactívalo para evitar duplicados.'),
                    ],
                    [
                        'type' => 'switch',
                        'label' => $this->l('Evitar duplicados (mostrar una sola vez)'),
                        'name' => self::CFG_SINGLETON,
                        'is_bool' => true,
                        'values' => [
                            ['id' => 'single_on',  'value' => 1, 'label' => $this->l('Sí')],
                            ['id' => 'single_off', 'value' => 0, 'label' => $this->l('No')],
                        ],
                        'desc' => $this->l('Si está activado, el banner se mostrará solo la primera vez que se invoque en la página.'),
                    ],
                ],
                'submit' => [
                    'title' => $this->l('Guardar'),
                ],
            ],
        ];

        // Precarga correcta (multilenguaje como arrays id_lang => valor)
        $helper->fields_value = $this->getConfigValues();

        // Idiomas para el helper
        $helper->tpl_vars = [
            'languages'   => $languages,
            'id_language' => $this->context->language->id,
        ];

        $htmlForm  = $helper->generateForm([$fields_form]);
        $htmlHowTo = $this->renderHowToWithPreview($languages);

        return $output.$htmlForm.$htmlHowTo;
    }

    protected function getConfigValues()
    {
        $values = [];
        $values[self::CFG_TEXT_ONE]  = [];
        $values[self::CFG_TEXT_LT10] = [];
        $values[self::CFG_TEXT_LT20] = [];

        $languages = Language::getLanguages(false);
        foreach ($languages as $lang) {
            $id_lang = (int)$lang['id_lang'];
            $values[self::CFG_TEXT_ONE][$id_lang]  = (string)Configuration::get(self::CFG_TEXT_ONE,  $id_lang);
            $values[self::CFG_TEXT_LT10][$id_lang] = (string)Configuration::get(self::CFG_TEXT_LT10, $id_lang);
            $values[self::CFG_TEXT_LT20][$id_lang] = (string)Configuration::get(self::CFG_TEXT_LT20, $id_lang);
        }

        $values[self::CFG_AUTO_AFTER_PRICE] = (int)Configuration::get(self::CFG_AUTO_AFTER_PRICE);
        $values[self::CFG_SINGLETON]        = (int)Configuration::get(self::CFG_SINGLETON);

        return $values;
    }

    protected function renderHowToWithPreview(array $languages)
    {
        $mod = pSQL($this->name);
        $langOptions = '';
        foreach ($languages as $lang) {
            $id = (int)$lang['id_lang'];
            $iso = Tools::safeOutput($lang['iso_code']);
            $name = Tools::safeOutput($lang['name']);
            $selected = ($id == (int)$this->context->language->id) ? ' selected' : '';
            $langOptions .= "<option value=\"{$id}\"{$selected}>{$name} ({$iso})</option>";
        }

        $shortSmarty = "{hook h='displayScarcitySpecial' mod='{$mod}' product=\$product}";
        $shortId     = "{hook h='displayScarcitySpecial' mod='{$mod}' id_product=123 id_product_attribute=0}";

        $CFG_ONE  = self::CFG_TEXT_ONE;
        $CFG_10   = self::CFG_TEXT_LT10;
        $CFG_20   = self::CFG_TEXT_LT20;

        $html = '
        <div class="panel">
            <h3><i class="icon-info-circle"></i> '.$this->l('Cómo insertar el banner').'</h3>
            <ol>
              <li><strong>'.$this->l('Automático bajo el precio').'</strong> — '.$this->l('Activa “Insertar automáticamente bajo el precio”.').'</li>
              <li><strong>'.$this->l('En tu plantilla (Smarty) o Creative Elements (Shortcode)').'</strong><br>
                <pre style="margin:8px 0;">'.Tools::safeOutput($shortSmarty).'</pre>
                <button type="button" class="btn btn-default" id="psscarcity-copy-smarty"><i class="icon-copy"></i> '.$this->l('Copiar shortcode').'</button>
              </li>
              <li><strong>'.$this->l('Sin contexto de producto (landing/CMS)').'</strong><br>
                <pre style="margin:8px 0;">'.Tools::safeOutput($shortId).'</pre>
                <button type="button" class="btn btn-default" id="psscarcity-copy-id"><i class="icon-copy"></i> '.$this->l('Copiar shortcode por ID').'</button>
              </li>
            </ol>
            <p><em>'.$this->l('Nota: el hook “special” no anula los otros. Para evitar duplicados, desactiva la inserción automática o activa “Evitar duplicados (mostrar una sola vez)”.').'</em></p>
            <hr>
            <h4>'.$this->l('Previsualización en vivo').'</h4>
            <div class="row">
              <div class="col-lg-3">
                <label>'.$this->l('Idioma').'</label>
                <select id="psscarcity-lang" class="form-control">'.$langOptions.'</select>
              </div>
              <div class="col-lg-3">
                <label>'.$this->l('Stock de prueba').': <span id="psscarcity-q-label">12</span></label>
                <input id="psscarcity-q" type="range" min="0" max="30" step="1" value="12" class="form-control" style="width:100%;">
              </div>
            </div>
            <div id="psscarcity-preview" class="alert alert-warning" style="margin-top:15px; display:none;"></div>
            <p class="help-block" style="margin-top:8px;">'.$this->l('SSR: el servidor renderiza el texto y %count% se sustituye por las unidades reales. El JS solo refresca cuando cambias de combinación.').'</p>
        </div>

        <script>
        (function(){
          var CFG_ONE = "'.$CFG_ONE.'";
          var CFG_10  = "'.$CFG_10.'";
          var CFG_20  = "'.$CFG_20.'";

          function getVal(cfg, idLang){
            var sel = "[name=\'"+cfg+"_"+idLang+"\']";
            var el = document.querySelector(sel);
            return el ? el.value : "";
          }

          function band(q){
            q = parseInt(q,10) || 0;
            if (q <= 0) return null;
            if (q === 1) return "one";
            if (q < 10) return "lt10";
            if (q < 20) return "lt20";
            return null;
          }

          var $lang = document.getElementById("psscarcity-lang");
          var $q = document.getElementById("psscarcity-q");
          var $ql = document.getElementById("psscarcity-q-label");
          var $prev = document.getElementById("psscarcity-preview");

          function render(){
            var idLang = parseInt($lang.value,10);
            var q = parseInt($q.value,10);
            $ql.textContent = q;
            var b = band(q);
            if (!b){
              $prev.style.display = "none";
              $prev.innerHTML = "";
              return;
            }
            var msg;
            if (b === "one") {
              msg = getVal(CFG_ONE, idLang) || "¡Última unidad con envío inmediato!";
              $prev.innerHTML = msg;
            } else if (b === "lt10") {
              msg = getVal(CFG_10, idLang) || "¡Quedan %count% unidades — casi agotado!";
              $prev.innerHTML = msg.replace("%count%", "<strong>"+q+"</strong>");
            } else {
              msg = getVal(CFG_20, idLang) || "¡Quedan %count% unidades — no lo dejes pasar!";
              $prev.innerHTML = msg.replace("%count%", "<strong>"+q+"</strong>");
            }
            $prev.style.display = "";
          }

          document.addEventListener("input", function(e){
            var n = e.target && e.target.name || "";
            if (n.indexOf(CFG_ONE+"_") === 0 || n.indexOf(CFG_10+"_") === 0 || n.indexOf(CFG_20+"_") === 0){
              render();
            }
          });
          $lang.addEventListener("change", render);
          $q.addEventListener("input", render);

          function copy(txt){
            if (navigator.clipboard && navigator.clipboard.writeText){
              navigator.clipboard.writeText(txt);
            } else {
              var ta = document.createElement("textarea");
              ta.value = txt; document.body.appendChild(ta);
              ta.select(); document.execCommand("copy");
              document.body.removeChild(ta);
            }
          }
          document.getElementById("psscarcity-copy-smarty").addEventListener("click", function(){ copy("'.addslashes($shortSmarty).'"); });
          document.getElementById("psscarcity-copy-id").addEventListener("click", function(){ copy("'.addslashes($shortId).'"); });

          render();
        })();
        </script>';

        return $html;
    }

    /* ----------------- Hooks front ----------------- */

    public function hookActionFrontControllerSetMedia($params)
    {
        if (!isset($this->context->controller)) { return; }
        // JS solo es necesario en la ficha de producto (cambios de combinación)
        if ($this->context->controller->php_self === 'product') {
            $this->context->controller->registerJavascript(
                'module-'.$this->name.'-scarcity',
                'modules/'.$this->name.'/views/js/psscarcity.js',
                ['position' => 'bottom', 'priority' => 150]
            );
        }
    }

    public function hookDisplayProductPriceBlock($params)
    {
        if (!Configuration::get(self::CFG_AUTO_AFTER_PRICE)) {
            return '';
        }
        if (isset($params['type']) && $params['type'] === 'after_price') {
            return $this->renderScarcity($params, 'displayProductPriceBlock');
        }
        return '';
    }

    public function hookDisplayAfterPrice($params)
    {
        if (!Configuration::get(self::CFG_AUTO_AFTER_PRICE)) {
            return '';
        }
        return $this->renderScarcity($params, 'displayAfterPrice');
    }

    public function hookDisplayScarcityBanner($params)
    {
        return $this->renderScarcity($params, 'displayScarcityBanner');
    }

    public function hookDisplayScarcitySpecial($params)
    {
        return $this->renderScarcity($params, 'displayScarcitySpecial');
    }

    /* ----------------- Render core ----------------- */

    protected function computeBand($qty)
    {
        $q = (int)$qty;
        if ($q <= 0) return null;
        if ($q === 1) return 'one';
        if ($q < 10)  return 'lt10';
        if ($q < 20)  return 'lt20';
        return null;
    }

    protected function renderScarcity($params, $source = '')
    {
        static $printed = false;
        if (Configuration::get(self::CFG_SINGLETON) && $printed) {
            return '';
        }

        // 1) Obtener $product desde el hook o variable Smarty
        $product = $params['product'] ?? $this->context->smarty->getTemplateVars('product');

        // 2) Fallback: id_product explícito (CMS/landing/CE)
        if (!is_array($product) || !isset($product['quantity'])) {
            $idProduct = (int)($params['id_product'] ?? Tools::getValue('id_product'));
            $idPA      = (int)($params['id_product_attribute'] ?? Tools::getValue('id_product_attribute'));
            if ($idProduct) {
                $qtyReal = (int)StockAvailable::getQuantityAvailableByProduct($idProduct, $idPA, (int)$this->context->shop->id);
                $product = ['quantity' => $qtyReal];
            }
        }

        if (!is_array($product) || !isset($product['quantity'])) {
            return '';
        }

        // SIEMPRE usar la cantidad real
        $qty  = (int)$product['quantity'];
        $band = $this->computeBand($qty);
        if ($band === null) {
            return '';
        }

        $id_lang = (int)$this->context->language->id;
        $msgOne  = (string)Configuration::get(self::CFG_TEXT_ONE,  $id_lang);
        $msg10   = (string)Configuration::get(self::CFG_TEXT_LT10, $id_lang);
        $msg20   = (string)Configuration::get(self::CFG_TEXT_LT20, $id_lang);

        // Sanitizar y preparar SSR
        $msgOneSafe = Tools::safeOutput($msgOne);
        $msg10Safe  = Tools::safeOutput($msg10);
        $msg20Safe  = Tools::safeOutput($msg20);

        if ($band === 'one') {
            $finalHtml = $msgOneSafe; // sin %count%
        } elseif ($band === 'lt10') {
            $finalHtml = str_replace('%count%', '<strong class="ps-scarcity-count">'.$qty.'</strong>', $msg10Safe);
        } else { // lt20
            $finalHtml = str_replace('%count%', '<strong class="ps-scarcity-count">'.$qty.'</strong>', $msg20Safe);
        }

        $this->context->smarty->assign([
            'psscarcity_qty'     => $qty,
            'psscarcity_band'    => $band,
            'psscarcity_msg_one' => $msgOneSafe,
            'psscarcity_msg_10'  => $msg10Safe,
            'psscarcity_msg_20'  => $msg20Safe,
            'psscarcity_final'   => $finalHtml,
        ]);

        $html = $this->fetch('module:'.$this->name.'/views/templates/hook/scarcity.tpl');
        if ($html) { $printed = true; }
        return $html;
    }
}
