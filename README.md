# ps_scarcity — Banner de escasez para PrestaShop 8 (Smarty + Creative Elements)

Banner de urgencia/escasez que muestra **el stock real** del producto y cambia dinámicamente al seleccionar combinaciones.  
Funciona con **Smarty** (SSR) y con **Creative Elements** (shortcode). Incluye tres mensajes configurables:

- **1 unidad** (mensaje exclusivo)
- **< 10 unidades** (usa `%count%`)
- **< 20 unidades** (usa `%count%`)

> `%count%` se sustituye siempre por **la cantidad real** disponible de la combinación actual.

---

## Características

- ✅ **SSR** (render del lado servidor): el mensaje se ve aunque el JS no cargue.
- ✅ **Dinámico** al cambiar combinaciones (eventos `updatedProduct` / `updateProduct`).
- ✅ **Lectura robusta del stock** en CE: prioriza el **DOM visible** (`.product-quantities span`), luego dataset, luego SSR.
- ✅ **Configuración multilenguaje** en Back Office.
- ✅ **Shortcode** para CE y **hook** para Smarty.
- ✅ Opción **“Insertar automáticamente bajo el precio”** (after_price).
- ✅ Opción **“Evitar duplicados (mostrar una sola vez)”**.
- ✅ **CSP-friendly** (sin inline scripts; fallback opcional).

---

## Requisitos

- PrestaShop **8.x**
- PHP **8.1+ / 8.2**
- (Opcional) Creative Elements

---

## Estructura

modules/
└─ ps_scarcity/
├─ ps_scarcity.php
└─ views/
├─ js/
│ └─ psscarcity.js
└─ templates/
└─ hook/
└─ scarcity.tpl

---

## Instalación

1. Copia la carpeta `ps_scarcity` a `modules/`.
2. En BO: **Módulos → Módulos** → busca **ps_scarcity** → **Instalar**.
3. (Recomendado durante pruebas) BO → **Parámetros avanzados → Rendimiento**:
   - Desactiva caché.
   - Activa **Forzar compilación**.
   - Desactiva **CCC JS** temporalmente.
4. Configura el módulo (ver apartado siguiente).

---

## Configuración (Back Office)

- **Mensaje 1 unidad** (no usa `%count%`).
- **Mensaje < 10 unidades** (usa `%count%`).
- **Mensaje < 20 unidades** (usa `%count%`).
- **Insertar automáticamente bajo el precio** (usa hooks nativos `displayProductPriceBlock:after_price` y `displayAfterPrice`).
- **Evitar duplicados (mostrar una sola vez)**.

Incluye un panel de **Ayuda + Preview**:
- Mini “playground” para probar mensajes por idioma y stock simulado.
- Botones para **copiar shortcodes**.

---

## Uso

### A) Inserción automática (sin tocar plantillas)
Activa **Insertar automáticamente bajo el precio** en BO.  
El módulo se mostrará debajo del precio en la ficha de producto.

### B) Smarty (tema)
Donde quieras mostrarlo:
```smarty
{hook h='displayScarcitySpecial' mod='ps_scarcity' product=$product}
Por ID (p.ej. en CMS/landing sin contexto de producto):
{hook h='displayScarcitySpecial' mod='ps_scarcity' id_product=123 id_product_attribute=0}
C) Creative Elements (Shortcode)
Añade un Shortcode y pega:


{hook h='displayScarcitySpecial' mod='ps_scarcity' product=$product}

O por ID:
{hook h='displayScarcitySpecial' mod='ps_scarcity' id_product=123 id_product_attribute=0}

Nota: Si insertas manualmente por hook/shortcode y también tienes la inserción automática activada, puedes activar “Evitar duplicados” o desactivar la automática.

Cómo funciona (Front)
SSR: el texto final (con <strong>) se imprime en servidor.

JS (compatible CE): al cambiar la combinación, re-lee el stock en este orden:

.product-quantities span (DOM visible).

#product-details[data-product] (dataset).

data-qty del propio banner (SSR) como último recurso.

Además:

Reintentos a 20/60/120/200 ms tras el evento (por si CE re-renderiza más tarde).

MutationObserver sobre .product-quantities para reaccionar a cambios del DOM.

Seguridad
Los textos del BO se escapan con Tools::safeOutput() y se inyectan en data-*.

%count% solo se sustituye por un entero envuelto en <strong>.

JS sin eval, sin orígenes externos y sin concatenaciones peligrosas.

CSP: el módulo funciona sin inline; el fallback inline del TPL es opcional.
Para ser 100% CSP-friendly, no lo uses y confía en registerJavascript con versionado en URL.

Solución de problemas
Veo un número incorrecto (p. ej. “15”) en CE
Borra cualquier snippet antiguo de tu tema (busca “15 unidades”, id="ps-scarcity").

Asegúrate de estar usando el JS actual (verifica versión en consola):


window.__PS_SCARCITY_VERSION // debería mostrar "1.1.3" (o la que tengas)
Comprueba que el JS está cargando:


Array.from(document.scripts).map(s => s.src).filter(s => s.includes('psscarcity.js'));
Revisa el stock visible que pinta el tema:


document.querySelector('.product-quantities span')?.textContent
El banner debe coincidir exactamente con ese número.

No cambia al seleccionar combinaciones
Algunos temas/CE no actualizan el dataset; por eso el módulo lee del DOM visible.

Si tu tema usa otro selector para el stock, ajusta el JS (lista de selectores en readFromQuantitiesDom()).

El JS no se actualiza (caché)
Asegura “cache-busting”: el módulo registra el JS con ?v=<versión> en la URL.

Desactiva CCC JS durante pruebas y vacía caché.

No aparece el banner
Verifica que el hook/shortcode se está insertando.

Si usas “Evitar duplicados”, asegúrate de que no lo estás invocando en varios sitios a la vez.

Comandos útiles (Consola)

// ¿Está cargado el JS del módulo?
Array.from(document.scripts).map(s => s.src).filter(s => s.includes('psscarcity.js'));

// Versión del script activo
window.__PS_SCARCITY_VERSION;

// ¿Cuántos banners hay en DOM?
document.querySelectorAll('[data-psscarcity]').length;

// Stock visible que pinta el tema
document.querySelector('.product-quantities span')?.textContent;
Changelog
1.1.3

JS “CE-proof” (prioriza DOM, reintentos, MutationObserver).

Marca window.__PS_SCARCITY_VERSION.

Cache-busting en registerJavascript.

1.1.0

Mensajes separados: one, <10, <20 con %count% real.

Shortcode + Smarty hook especial.

Opciones BO (auto after_price, evitar duplicados).

Panel de ayuda + preview en vivo.

Licencia
MIT. Puedes adaptarla a las necesidades de tu proyecto.

Créditos
Desarrollado por ps_scarcity — módulo de ejemplo pensado para tiendas con PrestaShop 8 y Creative Elements.


::contentReference[oaicite:0]{index=0}