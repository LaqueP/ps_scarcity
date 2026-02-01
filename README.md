# ps_scarcity — Banner de escasez para PrestaShop 8 (Smarty + Creative Elements)

Banner de urgencia/escasez que muestra **el stock real** del producto y cambia dinámicamente al seleccionar combinaciones.
Funciona con **Smarty** (SSR) y con **Creative Elements** (shortcode). Incluye tres mensajes configurables:

* **1 unidad** (mensaje exclusivo)
* **< 10 unidades** (usa `%count%`)
* **< 20 unidades** (usa `%count%`)

> `%count%` se sustituye siempre por **la cantidad real** disponible de la combinación actual.

---

## Características

* ✅ **SSR**: render del lado servidor, el mensaje se ve aunque el JS no cargue.
* ✅ **Dinámico** al cambiar combinaciones (escucha eventos `updatedProduct` / `updateProduct`).
* ✅ **Lectura robusta del stock**:

  1. `.product-quantities span[data-stock]` (DOM visible)
  2. `#product-details[data-product]` (dataset JSON)
  3. `data-qty` del propio banner (SSR)
* ✅ **Configuración multilenguaje** en Back Office.
* ✅ **Shortcode** para CE y **hook** para Smarty.
* ✅ Opción **“Insertar automáticamente debajo del precio”** usando hook `displayProductPriceBlock` con `type="after_price"` y `displayAfterPrice`.
* ✅ Opción **“Evitar duplicados (mostrar una sola vez)”** para no duplicar banners.
* ✅ **CSP-friendly**: sin inline JS peligroso, registerJavascript con versionado.

---

## Requisitos

* PrestaShop **8.x**
* PHP **8.1+ / 8.2**
* (Opcional) Creative Elements

---

## Estructura

```
modules/
└─ ps_scarcity/
   ├─ ps_scarcity.php
   └─ views/
       ├─ js/
       │  └─ psscarcity.js
       └─ templates/
          └─ hook/
             └─ scarcity.tpl
```

---

## Instalación

1. Copia la carpeta `ps_scarcity` a `modules/`.
2. En BO: **Módulos → Módulos** → busca **ps_scarcity** → **Instalar**.
3. (Recomendado durante pruebas) BO → **Parámetros avanzados → Rendimiento**:

   * Desactiva caché.
   * Activa **Forzar compilación**.
   * Desactiva **CCC JS** temporalmente.
4. Configura el módulo en BO (ver apartado siguiente).

---

## Configuración (Back Office)

* **Mensaje 1 unidad** (no usa `%count%`).
* **Mensaje < 10 unidades** (usa `%count%`).
* **Mensaje < 20 unidades** (usa `%count%`).
* **Insertar automáticamente debajo del precio** (after_price).
* **Evitar duplicados (mostrar una sola vez)**.
* **CSS personalizado** para el banner.

Incluye un panel de **Ayuda + Preview**:

* Mini “playground” para probar mensajes por idioma y stock simulado.
* Botones para **copiar shortcodes**.

---

## Uso

### A) Inserción automática (sin tocar plantillas)

Activa **Insertar automáticamente debajo del precio** en BO.
El módulo se mostrará debajo del bloque de precio en la ficha de producto.

### B) Smarty (tema)

```smarty
{hook h='displayScarcitySpecial' mod='ps_scarcity' product=$product}
```

Por ID (p.ej. en CMS/landing sin contexto de producto):

```smarty
{hook h='displayScarcitySpecial' mod='ps_scarcity' id_product=123 id_product_attribute=0}
```

### C) Creative Elements (Shortcode)

Añade un Shortcode y pega:

```smarty
{hook h='displayScarcitySpecial' mod='ps_scarcity' product=$product}
```

O por ID:

```smarty
{hook h='displayScarcitySpecial' mod='ps_scarcity' id_product=123 id_product_attribute=0}
```

> Nota: si insertas manualmente por hook/shortcode y también tienes la inserción automática activada, puedes activar “Evitar duplicados” o desactivar la automática.

---

## Funcionamiento (Front)

* **SSR**: el texto final (con `<strong>`) se imprime en servidor.
* **JS dinámico**: al cambiar combinaciones, se re-lee el stock:

1. `.product-quantities span[data-stock]` (DOM visible).
2. `#product-details[data-product]` (dataset JSON).
3. `data-qty` del banner (SSR).

* Reintentos a 20/60/120/200 ms tras el evento (`updatedProduct` / `updateProduct`).
* MutationObserver sobre `.product-quantities` y contenedor del banner para detectar cambios dinámicos.
* JS ligero, sin bloqueos ni recargas completas de página.

---

## Seguridad

* Textos del BO escapados con `Tools::safeOutput()`.
* `%count%` sustituido por un entero envuelto en `<strong>`.
* JS sin `eval`, sin orígenes externos y sin concatenaciones peligrosas.
* Compatible con **CSP**: uso de `registerJavascript` con versionado en URL.

---

## Solución de problemas

* **Número incorrecto en CE**: elimina cualquier snippet antiguo, asegúrate que `psscarcity.js` es la versión actual:

```js
window.__PS_SCARCITY_VERSION
Array.from(document.scripts).map(s => s.src).filter(s => s.includes('psscarcity.js'));
```

* **No cambia al seleccionar combinaciones**: el módulo ahora prioriza el DOM visible y reintenta múltiples veces si CE re-renderiza.

* **JS no se actualiza (caché)**: activa versionado en URL y desactiva CCC JS durante pruebas.

* **Banner no aparece**: verifica hook/shortcode y la opción “Evitar duplicados”.

* **Selector de stock personalizado**: puedes adaptar `readFromQuantitiesDom()` en `psscarcity.js`.

---

## Comandos útiles (Consola)

```js
// Banners en DOM
document.querySelectorAll('[data-psscarcity]').length

// Stock visible según tema
document.querySelector('.product-quantities span')?.textContent

// JS cargado y versión
window.__PS_SCARCITY_VERSION
Array.from(document.scripts).map(s => s.src).filter(s => s.includes('psscarcity.js'))
```

---

## Changelog

**v1.2.0**

* Nuevo SSR + JS CE-proof.
* Banner dinámico al cambiar combinaciones.
* Inserción segura **after_price** sin cubrir el precio.
* Evita duplicados correctamente.
* Prioriza DOM para leer stock.
* Reintentos y MutationObserver para CE.
* Versionado JS y cache-busting.

**v1.1.3**

* JS “CE-proof” (DOM + reintentos + MutationObserver).
* `window.__PS_SCARCITY_VERSION`.
* Cache-busting en registerJavascript.

**v1.1.0**

* Mensajes separados: one, <10, <20 con `%count%`.
* Shortcode + Smarty hook especial.
* Opciones BO (auto after_price, evitar duplicados).
* Panel de ayuda + preview en vivo.

---

## Licencia

MIT. Adaptable a tus necesidades.

---

## Créditos

Desarrollado por **ps_scarcity**, módulo de ejemplo para PrestaShop 8 y Creative Elements.
