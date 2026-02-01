<?php
if (!defined('_PS_VERSION_')) { exit; }

class Ps_Scarcity extends Module
{
    const CFG_TEXT_ONE         = 'PS_SCARCITY_TEXT_ONE';
    const CFG_TEXT_LT10        = 'PS_SCARCITY_TEXT_LT10';
    const CFG_TEXT_LT20        = 'PS_SCARCITY_TEXT_LT20';
    const CFG_AUTO_AFTER_PRICE = 'PS_SCARCITY_AUTO_AFTER_PRICE';
    const CFG_SINGLETON        = 'PS_SCARCITY_SINGLETON';
    const CFG_LIMIT_LT10       = 'PS_SCARCITY_LIMIT_LT10';
    const CFG_LIMIT_LT20       = 'PS_SCARCITY_LIMIT_LT20';
    const CFG_CUSTOM_CSS       = 'PS_SCARCITY_CUSTOM_CSS';

    public function __construct()
    {
        $this->name      = 'ps_scarcity';
        $this->version   = '1.3.0';
        $this->author    = 'LaqueP';
        $this->tab       = 'front_office_features';
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('Scarcity Banner (CE + Smarty)');
        $this->description = $this->l('Banner de escasez configurable: 1 unidad, <10 y <20 con %count% como unidades reales. Compatible con Creative Elements y plantillas Smarty.');
        $this->ps_versions_compliancy = ['min' => '8.0.0', 'max' => _PS_VERSION_];
    }

    public function install()
    {
        $langs = Language::getIDs(false);
        $defOne = []; $defLt10 = []; $defLt20 = [];
        foreach ($langs as $id_lang) {
            $defOne[$id_lang]  = 'Last unit available for immediate dispatch!';
            $defLt10[$id_lang] = '<10 - Only %count% units left for immediate dispatch!';
            $defLt20[$id_lang] = '<20 - Less than %count% units left for immediate dispatch!';
        }

        return parent::install()
            && $this->registerHook('displayScarcityBanner')
            && $this->registerHook('displayScarcitySpecial')
            && $this->registerHook('displayProductPriceBlock')
            && $this->registerHook('displayAfterPrice')
            && $this->registerHook('actionFrontControllerSetMedia')
            && $this->registerHook('displayHeader')
            && $this->registerHook('displayBackOfficeHeader')
            && Configuration::updateValue(self::CFG_TEXT_ONE,  $defOne,  false)
            && Configuration::updateValue(self::CFG_TEXT_LT10, $defLt10, false)
            && Configuration::updateValue(self::CFG_TEXT_LT20, $defLt20, false)
            && Configuration::updateValue(self::CFG_AUTO_AFTER_PRICE, 1)
            && Configuration::updateValue(self::CFG_SINGLETON, 1)
            && Configuration::updateValue(self::CFG_LIMIT_LT10, 10)
            && Configuration::updateValue(self::CFG_LIMIT_LT20, 20)
            && Configuration::updateValue(self::CFG_CUSTOM_CSS, '');
    }

    public function uninstall()
    {
        return Configuration::deleteByName(self::CFG_TEXT_ONE)
            && Configuration::deleteByName(self::CFG_TEXT_LT10)
            && Configuration::deleteByName(self::CFG_TEXT_LT20)
            && Configuration::deleteByName(self::CFG_AUTO_AFTER_PRICE)
            && Configuration::deleteByName(self::CFG_SINGLETON)
            && Configuration::deleteByName(self::CFG_LIMIT_LT10)
            && Configuration::deleteByName(self::CFG_LIMIT_LT20)
            && Configuration::deleteByName(self::CFG_CUSTOM_CSS)
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
                $vOne[$id_lang] = (string)Tools::getValue(self::CFG_TEXT_ONE.'_'.$id_lang, '');
                $v10[$id_lang]  = (string)Tools::getValue(self::CFG_TEXT_LT10.'_'.$id_lang, '');
                $v20[$id_lang]  = (string)Tools::getValue(self::CFG_TEXT_LT20.'_'.$id_lang, '');
            }
            Configuration::updateValue(self::CFG_TEXT_ONE,  $vOne, false);
            Configuration::updateValue(self::CFG_TEXT_LT10, $v10,  false);
            Configuration::updateValue(self::CFG_TEXT_LT20, $v20,  false);

            Configuration::updateValue(self::CFG_AUTO_AFTER_PRICE, (int)Tools::getValue(self::CFG_AUTO_AFTER_PRICE));
            Configuration::updateValue(self::CFG_SINGLETON,        (int)Tools::getValue(self::CFG_SINGLETON));
            Configuration::updateValue(self::CFG_LIMIT_LT10, (int)Tools::getValue(self::CFG_LIMIT_LT10, 10));
            Configuration::updateValue(self::CFG_LIMIT_LT20, (int)Tools::getValue(self::CFG_LIMIT_LT20, 20));

            $cssContent = Tools::getValue(self::CFG_CUSTOM_CSS, '');
            Configuration::updateValue(self::CFG_CUSTOM_CSS, $cssContent);
            file_put_contents(_PS_MODULE_DIR_.$this->name.'/views/css/custom_scarcity.css', $cssContent);

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
                        'type'    => 'text',
                        'label'   => $this->l('Mensaje para 1 unidad'),
                        'name'    => self::CFG_TEXT_ONE,
                        'lang'    => true,
                        'required'=> true,
                    ],
                    [
                        'type'    => 'text',
                        'label'   => $this->l('Mensaje para < 10 unidades'),
                        'name'    => self::CFG_TEXT_LT10,
                        'lang'    => true,
                        'required'=> true,
                    ],
                    [
                        'type'    => 'text',
                        'label'   => $this->l('Mensaje para < 20 unidades'),
                        'name'    => self::CFG_TEXT_LT20,
                        'lang'    => true,
                        'required'=> true,
                    ],
                    [
                        'type'  => 'text',
                        'label' => $this->l('Umbral unidades < 10'),
                        'name'  => self::CFG_LIMIT_LT10,
                        'required' => true,
                    ],
                    [
                        'type'  => 'text',
                        'label' => $this->l('Umbral unidades < 20'),
                        'name'  => self::CFG_LIMIT_LT20,
                        'required' => true,
                    ],
                    [
                        'type'  => 'textarea',
                        'label' => $this->l('CSS personalizado para el banner'),
                        'name'  => self::CFG_CUSTOM_CSS,
                        'cols'  => 50,
                        'rows'  => 10,
                        'desc'  => $this->l('Escribe CSS que se aplicará al banner de escasez. Se guardará en un fichero y se cargará en el front-office.'),
                    ],
                    [
                        'type'   => 'switch',
                        'label'  => $this->l('Insertar automáticamente bajo el precio'),
                        'name'   => self::CFG_AUTO_AFTER_PRICE,
                        'is_bool'=> true,
                        'values' => [
                            ['id' => 'auto_on',  'value' => 1, 'label' => $this->l('Sí')],
                            ['id' => 'auto_off', 'value' => 0, 'label' => $this->l('No')],
                        ],
                    ],
                    [
                        'type'   => 'switch',
                        'label'  => $this->l('Evitar duplicados (mostrar una sola vez)'),
                        'name'   => self::CFG_SINGLETON,
                        'is_bool'=> true,
                        'values' => [
                            ['id' => 'single_on',  'value' => 1, 'label' => $this->l('Sí')],
                            ['id' => 'single_off', 'value' => 0, 'label' => $this->l('No')],
                        ],
                    ],
                ],
                'submit' => ['title' => $this->l('Guardar')],
            ],
        ];

        $helper->fields_value = $this->getConfigValues();
        $helper->tpl_vars = [
            'languages'   => $languages,
            'id_language' => $this->context->language->id,
        ];

        // Bloque de ayuda para hooks manuales
        $htmlHowTo = '
        <div class="panel">
            <h3><i class="icon-info-circle"></i> '.$this->l('Cómo insertar el banner manualmente').'</h3>
            <ol>
              <li><strong>'.$this->l('Automático bajo el precio').'</strong> — '.$this->l('Activa “Insertar automáticamente bajo el precio” si quieres que aparezca sin modificar la plantilla.').'</li>
              <li><strong>'.$this->l('En tu plantilla (Smarty) o Creative Elements)').'</strong><br>
                <pre>{hook h="displayScarcitySpecial" mod="'.$this->name.'" product=$product}</pre>
              </li>
              <li><strong>'.$this->l('Sin contexto de producto (CMS/Landing)').'</strong><br>
                <pre>{hook h="displayScarcitySpecial" mod="'.$this->name.'" id_product=123 id_product_attribute=0}</pre>
              </li>
            </ol>
        </div>';

        return $output . $helper->generateForm([$fields_form]) . $htmlHowTo;
    }

    protected function getConfigValues()
    {
        $values = [];
        $languages = Language::getLanguages(false);
        foreach ($languages as $lang) {
            $id_lang = (int)$lang['id_lang'];
            $values[self::CFG_TEXT_ONE][$id_lang]  = Configuration::get(self::CFG_TEXT_ONE,  $id_lang);
            $values[self::CFG_TEXT_LT10][$id_lang] = Configuration::get(self::CFG_TEXT_LT10, $id_lang);
            $values[self::CFG_TEXT_LT20][$id_lang] = Configuration::get(self::CFG_TEXT_LT20, $id_lang);
        }
        $values[self::CFG_AUTO_AFTER_PRICE] = (int)Configuration::get(self::CFG_AUTO_AFTER_PRICE);
        $values[self::CFG_SINGLETON]        = (int)Configuration::get(self::CFG_SINGLETON);
        $values[self::CFG_LIMIT_LT10]       = (int)Configuration::get(self::CFG_LIMIT_LT10);
        $values[self::CFG_LIMIT_LT20]       = (int)Configuration::get(self::CFG_LIMIT_LT20);
        $values[self::CFG_CUSTOM_CSS]       = Configuration::get(self::CFG_CUSTOM_CSS);
        return $values;
    }

    /* ----------------- Render core ----------------- */
    protected function computeBand($qty)
    {
        $q = (int)$qty;
        if ($q <= 0) return null;
        if ($q === 1) return 'one';
        $limitLt10 = (int)Configuration::get(self::CFG_LIMIT_LT10);
        $limitLt20 = (int)Configuration::get(self::CFG_LIMIT_LT20);
        if ($q < $limitLt10) return 'lt10';
        if ($q >= $limitLt10 && $q < $limitLt20) return 'lt20';
        return null;
    }

    protected function renderScarcity($params, $source = '')
    {
        static $printed = false;
        if (Configuration::get(self::CFG_SINGLETON) && $printed) return '';

        $idProduct = (int)($params['id_product'] ?? Tools::getValue('id_product'));
        $idPA      = (int)($params['id_product_attribute'] ?? Tools::getValue('id_product_attribute'));

        $qtyReal = 0;
        if (isset($params['product']['quantity'])) $qtyReal = (int)$params['product']['quantity'];
        elseif ($idProduct) $qtyReal = (int)StockAvailable::getQuantityAvailableByProduct($idProduct, $idPA, (int)$this->context->shop->id);

        $band = $this->computeBand($qtyReal);

        $id_lang = (int)$this->context->language->id;
        $msgOne  = Configuration::get(self::CFG_TEXT_ONE,  $id_lang);
        $msg10   = Configuration::get(self::CFG_TEXT_LT10, $id_lang);
        $msg20   = Configuration::get(self::CFG_TEXT_LT20, $id_lang);

        $msgOneSafe = Tools::safeOutput($msgOne);
        $msg10Safe  = Tools::safeOutput($msg10);
        $msg20Safe  = Tools::safeOutput($msg20);

        $finalHtml = '';
        if ($band === 'one') $finalHtml = $msgOneSafe;
        elseif ($band === 'lt10') $finalHtml = str_replace('%count%', '<strong class="ps-scarcity-count">'.$qtyReal.'</strong>', $msg10Safe);
        elseif ($band === 'lt20') $finalHtml = str_replace('%count%', '<strong class="ps-scarcity-count">'.$qtyReal.'</strong>', $msg20Safe);

        $this->context->smarty->assign([
            'psscarcity_qty'        => $qtyReal,
            'psscarcity_band'       => $band,
            'psscarcity_msg_one'    => $msgOneSafe,
            'psscarcity_msg_10'     => $msg10Safe,
            'psscarcity_msg_20'     => $msg20Safe,
            'psscarcity_final'      => $finalHtml,
            'psscarcity_limit_lt10' => Configuration::get(self::CFG_LIMIT_LT10),
            'psscarcity_limit_lt20' => Configuration::get(self::CFG_LIMIT_LT20),
            'psscarcity_custom_css' => Configuration::get(self::CFG_CUSTOM_CSS),
        ]);

        $html = $this->fetch('module:'.$this->name.'/views/templates/hook/scarcity.tpl');
        if ($html) $printed = true;

        return $html;
    }

    /* ----------------- Hooks FO / JS / CSS ----------------- */
    protected function addFrontJs($file)
    {
        $c = $this->context->controller ?? null;
        if (!$c) return;

        if (method_exists($c,'registerJavascript')) {
            $c->registerJavascript(
                'module-'.$this->name.'-scarcity',
                $file,
                ['position'=>'bottom','priority'=>999,'server'=>'local']
            );
        } elseif (method_exists($c,'addJS')) {
            $c->addJS($file);
        }
    }

    public function hookActionFrontControllerSetMedia($params)
    {
        $this->addFrontJs($this->_path.'views/js/psscarcity.js?v='.$this->version);

        $cssPath = _PS_MODULE_DIR_.$this->name.'/views/css/custom_scarcity.css';
        if (file_exists($cssPath)) {
            $this->context->controller->registerStylesheet(
                'module-'.$this->name.'-custom-css',
                $this->_path.'views/css/custom_scarcity.css',
                ['media'=>'all','priority'=>1000]
            );
        }
    }

    public function hookDisplayHeader($params)
    {
        $this->addFrontJs($this->_path.'views/js/psscarcity.js?v='.$this->version);
    }

    public function hookDisplayBackOfficeHeader($params)
    {
        if (Tools::getValue('configure') !== $this->name) return;
    }


public function hookDisplayProductPriceBlock($params)
{
    if (!Configuration::get(self::CFG_AUTO_AFTER_PRICE)) return '';
    
    // Solo ejecutar si el hook es AFTER_PRICE
    if (isset($params['type']) && $params['type'] === 'after_price') {
        return '<div class="ps-scarcity-after-price-container">'
               . $this->renderScarcity($params, 'displayProductPriceBlock')
               . '</div>';
    }
    return '';
}



    public function hookDisplayScarcityBanner($params)
    {
        return $this->renderScarcity($params,'displayScarcityBanner');
    }

    public function hookDisplayScarcitySpecial($params)
    {
        return $this->renderScarcity($params,'displayScarcitySpecial');
    }
}
