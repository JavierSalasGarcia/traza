<!-- PWA Meta Tags -->
<meta name="theme-color" content="#0099ff">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="apple-mobile-web-app-title" content="TrazaFI">

<!-- Manifest -->
<link rel="manifest" href="<?= base_url('public/manifest.json') ?>">

<!-- iOS Icons -->
<link rel="apple-touch-icon" sizes="72x72" href="<?= base_url('public/icons/icon-72x72.png') ?>">
<link rel="apple-touch-icon" sizes="96x96" href="<?= base_url('public/icons/icon-96x96.png') ?>">
<link rel="apple-touch-icon" sizes="128x128" href="<?= base_url('public/icons/icon-128x128.png') ?>">
<link rel="apple-touch-icon" sizes="144x144" href="<?= base_url('public/icons/icon-144x144.png') ?>">
<link rel="apple-touch-icon" sizes="152x152" href="<?= base_url('public/icons/icon-152x152.png') ?>">
<link rel="apple-touch-icon" sizes="192x192" href="<?= base_url('public/icons/icon-192x192.png') ?>">
<link rel="apple-touch-icon" sizes="384x384" href="<?= base_url('public/icons/icon-384x384.png') ?>">
<link rel="apple-touch-icon" sizes="512x512" href="<?= base_url('public/icons/icon-512x512.png') ?>">

<!-- Favicon -->
<link rel="icon" type="image/png" sizes="32x32" href="<?= base_url('public/icons/icon-72x72.png') ?>">
<link rel="icon" type="image/png" sizes="16x16" href="<?= base_url('public/icons/icon-72x72.png') ?>">

<!-- Service Worker Registration -->
<script>
if ('serviceWorker' in navigator) {
    window.addEventListener('load', function() {
        navigator.serviceWorker.register('<?= base_url('public/sw.js') ?>')
            .then(function(registration) {
                console.log('[PWA] Service Worker registrado exitosamente:', registration.scope);

                // Revisar actualizaciones cada hora
                setInterval(() => {
                    registration.update();
                }, 3600000);
            })
            .catch(function(error) {
                console.log('[PWA] Error al registrar Service Worker:', error);
            });
    });

    // Detectar cuando hay una actualización disponible
    navigator.serviceWorker.addEventListener('controllerchange', () => {
        console.log('[PWA] Nueva versión disponible');
        // Opcional: mostrar notificación de actualización
    });
}

// Detectar cuando la app está instalada
window.addEventListener('appinstalled', (evt) => {
    console.log('[PWA] App instalada exitosamente');
    // Opcional: enviar analytics
});

// Botón de instalación PWA
let deferredPrompt;

window.addEventListener('beforeinstallprompt', (e) => {
    // Prevenir que Chrome 67 y versiones anteriores muestren el prompt automáticamente
    e.preventDefault();
    // Guardar el evento para poder dispararlo después
    deferredPrompt = e;

    // Mostrar botón de instalación si existe
    const installButton = document.getElementById('pwa-install-btn');
    if (installButton) {
        installButton.style.display = 'block';

        installButton.addEventListener('click', () => {
            // Ocultar el botón
            installButton.style.display = 'none';
            // Mostrar el prompt
            deferredPrompt.prompt();
            // Esperar a que el usuario responda
            deferredPrompt.userChoice.then((choiceResult) => {
                if (choiceResult.outcome === 'accepted') {
                    console.log('[PWA] Usuario aceptó instalar la app');
                } else {
                    console.log('[PWA] Usuario rechazó instalar la app');
                }
                deferredPrompt = null;
            });
        });
    }
});

// Detectar modo standalone (app instalada)
function isPWA() {
    return window.matchMedia('(display-mode: standalone)').matches ||
           window.navigator.standalone === true;
}

if (isPWA()) {
    console.log('[PWA] App ejecutándose en modo standalone');
    document.body.classList.add('pwa-mode');
}

// Manejar estado de conexión
window.addEventListener('online', () => {
    console.log('[PWA] Conexión restaurada');
    document.body.classList.remove('offline-mode');

    // Mostrar notificación
    const notification = document.createElement('div');
    notification.className = 'connection-notification online';
    notification.innerHTML = '<i class="fas fa-wifi"></i> Conexión restaurada';
    document.body.appendChild(notification);

    setTimeout(() => {
        notification.remove();
    }, 3000);
});

window.addEventListener('offline', () => {
    console.log('[PWA] Sin conexión');
    document.body.classList.add('offline-mode');

    // Mostrar notificación
    const notification = document.createElement('div');
    notification.className = 'connection-notification offline';
    notification.innerHTML = '<i class="fas fa-wifi-slash"></i> Sin conexión a Internet';
    document.body.appendChild(notification);

    setTimeout(() => {
        notification.remove();
    }, 3000);
});
</script>

<style>
/* Estilos para el botón de instalación PWA */
#pwa-install-btn {
    display: none;
    position: fixed;
    bottom: 20px;
    right: 20px;
    padding: 14px 24px;
    background: var(--color-primary);
    color: var(--color-white);
    border: none;
    border-radius: var(--radius-xl);
    font-weight: var(--font-weight-semibold);
    font-size: var(--font-size-sm);
    cursor: pointer;
    box-shadow: 0 4px 12px rgba(0, 153, 255, 0.4);
    z-index: 1000;
    transition: all var(--transition-fast);
}

#pwa-install-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 16px rgba(0, 153, 255, 0.5);
}

#pwa-install-btn i {
    margin-right: 8px;
}

/* Notificaciones de conexión */
.connection-notification {
    position: fixed;
    top: 80px;
    right: 20px;
    padding: 12px 20px;
    border-radius: var(--radius-lg);
    font-size: var(--font-size-sm);
    font-weight: var(--font-weight-semibold);
    z-index: 10000;
    animation: slideInRight 0.3s ease;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
}

@keyframes slideInRight {
    from {
        transform: translateX(100%);
        opacity: 0;
    }
    to {
        transform: translateX(0);
        opacity: 1;
    }
}

.connection-notification.online {
    background: rgba(0, 255, 136, 0.1);
    border: 1px solid rgba(0, 255, 136, 0.3);
    color: var(--color-success);
}

.connection-notification.offline {
    background: rgba(255, 68, 68, 0.1);
    border: 1px solid rgba(255, 68, 68, 0.3);
    color: var(--color-error);
}

.connection-notification i {
    margin-right: 8px;
}

/* Modo offline */
body.offline-mode {
    /* Opcional: cambiar estilos cuando está offline */
}

/* Modo PWA standalone */
body.pwa-mode {
    /* Opcional: ajustar estilos cuando está instalada como PWA */
}

@media (max-width: 768px) {
    #pwa-install-btn {
        bottom: 80px;
        right: 10px;
        left: 10px;
        width: calc(100% - 20px);
    }

    .connection-notification {
        right: 10px;
        left: 10px;
        width: calc(100% - 20px);
    }
}
</style>
