# Progressive Web App (PWA) - TrazaFI

## Implementación Completa

TrazaFI ahora funciona como una **Progressive Web App** instalable en dispositivos móviles y de escritorio.

## Características Implementadas

### 📱 Instalación
- La app puede instalarse en dispositivos iOS, Android, Windows, macOS y Linux
- Botón de instalación automático que aparece cuando el navegador lo permite
- Acceso directo desde la pantalla de inicio del dispositivo

### 🔄 Service Worker
- Cache estratégico de recursos críticos
- Funcionamiento offline para páginas previamente visitadas
- Actualización automática de cache cada hora
- Estrategia "Network First" con fallback a cache

### 🎨 Iconos y Branding
- 8 tamaños de iconos (72x72 hasta 512x512)
- Compatibilidad con iOS, Android y escritorio
- Diseño Starlink con branding TrazaFI
- Soporte para modo oscuro nativo

### 📊 Manifest
- Nombre completo y corto configurado
- Tema y colores del diseño Starlink
- Shortcuts a secciones principales:
  - Dashboard
  - Propuestas
  - Encuestas
  - Notificaciones
- Orientación portrait optimizada para móviles

### 🌐 Funcionalidad Offline
- Página offline.html personalizada
- Detección automática de pérdida/recuperación de conexión
- Notificaciones visuales de estado de conexión
- Auto-refresh al recuperar conexión

### 🔔 Preparado para Push Notifications
- Infraestructura lista para notificaciones push (implementación futura)
- Background sync configurado
- Evento `notificationclick` implementado

## Archivos Creados

```
public/
├── manifest.json              # Configuración PWA
├── sw.js                      # Service Worker
├── offline.html               # Página offline
├── generate-icons.php         # Generador de iconos (ejecutar una vez)
├── icons/                     # Iconos en todos los tamaños
│   ├── icon-72x72.png
│   ├── icon-96x96.png
│   ├── icon-128x128.png
│   ├── icon-144x144.png
│   ├── icon-152x152.png
│   ├── icon-192x192.png
│   ├── icon-384x384.png
│   └── icon-512x512.png
└── screenshots/
    └── screenshot1.png        # Screenshot para tiendas de apps

core/includes/
└── pwa-head.php              # Componente reutilizable de PWA
```

## Cómo Usar

### Para Desarrolladores

**1. Incluir PWA en nuevas páginas:**

```php
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tu Página - TrazaFI</title>
    <link rel="stylesheet" href="<?= base_url('main.css') ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <!-- Incluir PWA -->
    <?php include __DIR__ . '/../core/includes/pwa-head.php'; ?>
</head>
<body>
    <!-- Tu contenido -->

    <!-- Opcional: Botón de instalación -->
    <button id="pwa-install-btn">
        <i class="fas fa-download"></i> Instalar TrazaFI
    </button>
</body>
</html>
```

**2. Regenerar iconos (si es necesario):**

```bash
php public/generate-icons.php
```

### Para Usuarios

**Instalar en Android:**
1. Abrir TrazaFI en Chrome
2. Tocar el menú (⋮) → "Instalar app" o "Añadir a pantalla de inicio"
3. Confirmar instalación

**Instalar en iOS:**
1. Abrir TrazaFI en Safari
2. Tocar el botón de compartir (□↑)
3. Seleccionar "Añadir a pantalla de inicio"
4. Confirmar

**Instalar en Escritorio (Chrome/Edge):**
1. Abrir TrazaFI
2. Buscar el icono de instalación en la barra de direcciones
3. O usar el botón "Instalar TrazaFI" que aparece en la página
4. Confirmar instalación

## Características Técnicas

### Cache Strategy
- **Network First:** Intenta obtener recursos de la red primero
- **Cache Fallback:** Si falla la red, usa la versión en cache
- **Offline Page:** Si no hay cache, muestra página offline personalizada

### Recursos Cacheados Automáticamente
- Dashboard principal
- Archivos CSS (main.css, Font Awesome)
- Página offline
- Todas las páginas visitadas (cache dinámico)

### Recursos NO Cacheados
- Endpoints de API (siempre frescos)
- Formularios POST
- Página de logout
- Recursos de otros dominios (excepto CDN permitidos)

### Actualización de Service Worker
- Revisa actualizaciones cada hora automáticamente
- Actualización manual con `registration.update()`
- Cache limpiado en cada actualización

## Eventos y Logs

El PWA registra eventos en la consola del navegador:

```
[PWA] Service Worker registrado exitosamente
[PWA] App instalada exitosamente
[PWA] App ejecutándose en modo standalone
[PWA] Conexión restaurada
[PWA] Sin conexión
```

## Detección de Estado

### Detectar si está instalada:
```javascript
function isPWA() {
    return window.matchMedia('(display-mode: standalone)').matches ||
           window.navigator.standalone === true;
}
```

### Detectar estado de conexión:
```javascript
window.addEventListener('online', () => {
    console.log('Conectado');
});

window.addEventListener('offline', () => {
    console.log('Sin conexión');
});
```

## Mejoras Futuras Posibles

- [ ] Push Notifications del servidor
- [ ] Background sync para acciones offline
- [ ] Precarga inteligente de contenido
- [ ] Cache de imágenes de perfil
- [ ] Modo offline completo con base de datos local (IndexedDB)
- [ ] Compartir contenido vía Web Share API
- [ ] Geolocalización para eventos del campus

## Testing

### Verificar PWA:
1. Abrir DevTools en Chrome
2. Ir a pestaña "Application"
3. Verificar:
   - Manifest cargado correctamente
   - Service Worker activo
   - Cache Storage poblado
   - Installability cumple criterios

### Lighthouse Audit:
```bash
# Ejecutar desde Chrome DevTools
Lighthouse → Progressive Web App
```

### Criterios de Instalación:
- ✅ Manifest válido con todos los campos
- ✅ Service Worker registrado
- ✅ Servido sobre HTTPS (requerido en producción)
- ✅ Iconos en múltiples tamaños
- ✅ start_url accesible

## Notas Importantes

1. **HTTPS Requerido:** En producción, el PWA solo funciona sobre HTTPS
2. **Tamaño del Cache:** El service worker cachea páginas visitadas, puede crecer
3. **Limpieza de Cache:** Se limpia automáticamente al actualizar versiones
4. **Compatibilidad:** Funciona en todos los navegadores modernos
5. **iOS Limitaciones:** Safari en iOS tiene soporte limitado de service workers

## Soporte de Navegadores

| Navegador | Instalación | Service Worker | Offline | Push Notifications |
|-----------|-------------|----------------|---------|-------------------|
| Chrome    | ✅          | ✅             | ✅      | ✅ (preparado)    |
| Firefox   | ✅          | ✅             | ✅      | ✅ (preparado)    |
| Safari    | ✅          | ⚠️ Limitado    | ⚠️      | ❌                |
| Edge      | ✅          | ✅             | ✅      | ✅ (preparado)    |
| Opera     | ✅          | ✅             | ✅      | ✅ (preparado)    |

## Recursos Adicionales

- [PWA Documentation - MDN](https://developer.mozilla.org/en-US/docs/Web/Progressive_web_apps)
- [Service Worker API](https://developer.mozilla.org/en-US/docs/Web/API/Service_Worker_API)
- [Web App Manifest](https://developer.mozilla.org/en-US/docs/Web/Manifest)
- [Google PWA Checklist](https://web.dev/pwa-checklist/)

---

**Implementado por:** Claude Code
**Fecha:** Diciembre 2024
**Versión PWA:** 1.0
**Cache Version:** trazafi-v1
