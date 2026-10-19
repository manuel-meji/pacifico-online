# Sistema de Diseño Oficial - Pacíficos Online (`design.md`)

Este documento define los estándares visuales, componentes y reglas de diseño de interfaz para **Pacíficos Online**. Todos los agentes de IA y desarrolladores **DEBEN** seguir estas directrices al crear, modificar o refactorizar prototipos, interfaces y componentes UI en la aplicación.

> [!TIP]
> **Showcase Interactivo en Vivo**: Puedes abrir en tu navegador el catálogo visual interactivo de componentes en [design_system.html](file:///c:/pacifico-online/design_system.html) o [guia_componentes.html](file:///c:/pacifico-online/frontend/prototipos/guia_componentes.html) para ver todos los colores, tipografías y componentes renderizados con Tailwind CSS y Lucide Icons (sin emojis).

---

## 1. Reglas Críticas para Agentes de IA

### 🚫 Prohibición Estricta de Emojis como Íconos
- **ESTÁ TOTALMENTE PROHIBIDO usar emojis como íconos de interfaz de usuario** (por ejemplo: ❌ `📍`, `📞`, `🛒`, `🔍`, `⭐`, `📦`, `🚚`, `⚙️`, `✅`, `❌`, `👤`, `❤️`, etc.).
- Los emojis producen inconsistencias visuales entre navegadores y sistemas operativos (Windows, macOS, iOS, Android), carecen de control de estilo (stroke, color, tamaño exacto) y transmiten una imagen poco profesional e informal.

### ✅ Uso Obligatorio de Librerías de Íconos Vectoriales
- **En aplicaciones React:** Usar librerías de íconos estándar como **`lucide-react`** (recomendada) o **`react-icons`** (Heroicons, Feather, etc.).
- **En prototipos HTML / Blade:** Usar íconos vectoriales SVG limpios con atributos `stroke="currentColor"` y dimensiones controladas (`w-4 h-4`, `w-5 h-5`), o la librería **Lucide Icons** vía CDN (`<script src="https://unpkg.com/lucide@latest"></script>`).

#### Comparación Rápida:
| Elemento | ❌ Incorrecto (Prohibido) | ✅ Correcto (Obligatorio) |
| :--- | :--- | :--- |
| **Ubicación / Envíos** | `<span>📍 Costa Rica</span>` | `<MapPin className="w-4 h-4 text-brandBlue" /> <span>Costa Rica</span>` |
| **Teléfono / Contacto** | `<span>📞 +506 2000-0000</span>` | `<Phone className="w-4 h-4 text-white" /> <span>+506 2000-0000</span>` |
| **Carrito de Compras** | `<button>🛒 Carrito</button>` | `<ShoppingCart className="w-5 h-5" /> <span>Carrito</span>` |
| **Búsqueda** | `<button>🔍</button>` | `<Search className="w-4 h-4" />` |
| **Calificación / Estrellas** | `<span>⭐⭐⭐⭐☆</span>` | `<Star className="w-4 h-4 fill-yellow-400 text-yellow-400" />` |
| **Verificado / Stock** | `<span>✅ Disponible</span>` | `<CheckCircle2 className="w-4 h-4 text-green-600" /> <span>Disponible</span>` |
| **Eliminar / Quitar** | `<button>🗑️</button>` | `<Trash2 className="w-4 h-4 text-red-500 hover:text-red-700" />` |

---

## 2. Paleta de Colores

### 2.1 Colores Principales de la Marca (Brand Colors)
Definidos en la configuración de diseño y Tailwind CSS:

| Token | Hex | Muestra | Uso Principal |
| :--- | :--- | :---: | :--- |
| **`brandDarkBlue`** | `#1e3a8a` | `■` | **Azul Oscuro / Principal:** Encabezados (`header`), barra superior de navegación, títulos principales de página, botones primarios institucionales. |
| **`brandBlue`** | `#3b82f6` | `■` | **Azul Brillante / Secundario:** Bordes de foco (`focus:border-brandBlue`), enlaces interactivos, estados `hover`, botones secundarios y tabs activos. |
| **`brandGreen`** | `#a3be4d` | `■` | **Verde Lima / Oliva:** Color de identidad (extraído de la "P" del isotipo). Usado en el isotipo de la marca, badges de oferta/descuento, etiquetas de disponibilidad y botón de llamada a la acción ("Agregar al Carrito"). |
| **`brandLightBg`** | `#f8fafc` | `■` | **Gris de Fondo Ultra Claro:** Fondo global de la aplicación (`body.bg-brandLightBg`), provee contraste sutil y suave con tarjetas y modales blancos. |

### 2.2 Colores Secundarios y Neutros (Tailwind CSS)

| Token / Clase | Hex | Uso Principal |
| :--- | :--- | :--- |
| **Gris Oscuro (`text-gray-800` / `text-gray-900`)** | `#1f2937` | Texto principal, encabezados de tarjeta, nombres de productos. |
| **Gris Medio (`text-gray-600` / `text-gray-500`)** | `#4b5563` | Texto secundario, subtítulos, etiquetas, descripciones y especificaciones. |
| **Gris Claro de Borde (`border-gray-200`)** | `#e5e7eb` | Separadores, bordes de tarjetas, inputs de formulario. |
| **Amarillo Calificación (`text-yellow-400`)** | `#facc15` | Estrellas de puntuación y valoraciones de clientes. |
| **Verde Éxito (`text-green-600` / `bg-green-500`)** | `#16a34a` | Indicadores de stock confirmado, validaciones exitosas, insignias de verificado. |
| **Rojo Alerta (`text-red-600` / `bg-red-500`)** | `#dc2626` | Errores de validación, productos agotados, acción de eliminar producto. |
| **Blanco (`bg-white`)** | `#ffffff` | Fondo de tarjetas (`cards`), modales, barra de navegación central y contenedores elevados. |

---

## 3. Tipografía y Jerarquía Visual

### 3.1 Familia Tipográfica
- **Pila Sans-serif del Sistema:** Limpia, moderna y de alto rendimiento nativo.
```css
font-family: ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
```
En Tailwind CSS, aplicar mediante la clase: `font-sans`.

### 3.2 Escala de Pesos de Fuente (Font Weights)

| Peso | Clase Tailwind | Usos Obligatorios |
| :--- | :--- | :--- |
| **400 (Normal / Regular)** | `font-normal` | Textos descriptivos, cuerpo general de lectura, especificaciones técnicas de producto. |
| **500 (Medio)** | `font-medium` | Enlaces del menú, etiquetas secundarias, metadatos, textos en inputs y placeholders. |
| **600 (Semibold)** | `font-semibold` | Títulos de tablas, encabezados de subsecciones, categorías de productos, subtotales. |
| **700 (Bold)** | `font-bold` | Títulos de productos, encabezados de sección principales (H2), etiquetas de botones de acción. |
| **800 (Extrabold)** | `font-extrabold` | Precios destacados de productos, letra "P" del logotipo, promociones hero. |

---

## 4. Configuración de Tailwind CSS

### Opción A: Tailwind CSS v4 (`index.css` o CSS principal)
```css
@import "tailwindcss";

@theme {
  --color-brandDarkBlue: #1e3a8a;
  --color-brandBlue: #3b82f6;
  --color-brandGreen: #a3be4d;
  --color-brandLightBg: #f8fafc;
}
```

### Opción B: Tailwind CSS v3 / CDN (`tailwind.config.js` o script CDN)
```javascript
tailwind.config = {
  theme: {
    extend: {
      colors: {
        brandDarkBlue: '#1e3a8a',
        brandBlue: '#3b82f6',
        brandGreen: '#a3be4d',
        brandLightBg: '#f8fafc',
      },
      fontFamily: {
        sans: ['ui-sans-serif', 'system-ui', '-apple-system', 'BlinkMacSystemFont', '"Segoe UI"', 'Roboto', '"Helvetica Neue"', 'Arial', 'sans-serif'],
      }
    }
  }
}
```

---

## 5. Patrones de Componentes UI

### 5.1 Botones
1. **Botón Principal / Institucional:**
   - Clase: `bg-brandDarkBlue hover:bg-brandBlue text-white font-bold py-2.5 px-4 rounded-lg transition duration-200 flex items-center justify-center gap-2`
2. **Botón de Conversión / "Agregar al Carrito":**
   - Clase: `bg-brandGreen hover:brightness-105 text-white font-bold py-3 px-6 rounded-lg transition shadow-sm flex items-center justify-center gap-2`
3. **Botón Secundario / Outline:**
   - Clase: `border border-brandBlue text-brandBlue hover:bg-blue-50 font-semibold py-2 px-4 rounded-lg transition flex items-center gap-2`
4. **Botón Deshabilitado:**
   - Clase: `bg-gray-300 text-gray-500 cursor-not-allowed py-2.5 px-4 rounded-lg`

### 5.2 Tarjetas de Producto / Contenedores
- **Estructura base:**
  ```html
  <div class="bg-white rounded-xl shadow-sm border border-gray-100 hover:shadow-md transition p-4">
    <!-- Contenido -->
  </div>
  ```

### 5.3 Badges y Etiquetas de Estado
- **En Stock / Disponible:**
  ```html
  <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold bg-green-50 text-green-700 border border-green-200">
    <!-- Ícono SVG CheckCircle / Check -->
    Disponible
  </span>
  ```
- **Oferta / Promoción Especial:**
  ```html
  <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-bold bg-brandGreen text-white shadow-xs">
    <!-- Ícono SVG Tag -->
    -20% OFF
  </span>
  ```
- **Agotado / Sin Existencias:**
  ```html
  <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold bg-red-50 text-red-700 border border-red-200">
    <!-- Ícono SVG AlertCircle -->
    Agotado
  </span>
  ```

---

## 6. Checklist de Validación para Agentes de IA

Antes de finalizar cualquier tarea visual o cambio en el frontend, el agente debe verificar:
- [ ] ¿Hay algún emoji utilizado como ícono en el código? Si lo hay, reemplazarlo de inmediato por un componente de `lucide-react`, `react-icons` o SVG.
- [ ] ¿Se están respetando los nombres y códigos de color de la marca (`brandDarkBlue`, `brandBlue`, `brandGreen`, `brandLightBg`)?
- [ ] ¿Los pesos de fuente corresponden a la escala oficial (400 cuerpo, 600 subtítulos, 700 títulos, 800 precios)?
- [ ] ¿Los botones y campos de texto cuentan con estados interactivos (`hover`, `focus`, `active`, `disabled`)?
- [ ] ¿El fondo general utiliza `bg-brandLightBg` y las tarjetas `bg-white` para mantener el contraste limpio?
