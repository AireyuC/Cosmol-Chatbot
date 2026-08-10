<?php

declare(strict_types=1);

/**
 * Registra el Autoloader siguiendo el estándar PSR-4 de forma nativa.
 * 
 * Esto evita tener que usar decenas de "require_once" en cada archivo,
 * ya que PHP buscará e incluirá automáticamente las clases cuando se necesiten.
 */
spl_autoload_register(function (string $class): void {
    // 1. Definimos el namespace base de nuestra aplicación
    $prefix = 'App\\';

    // 2. Verificamos si la clase solicitada usa nuestro namespace base "App\"
    // Si no lo usa (por ejemplo, es una clase nativa de PHP), ignoramos y salimos.
    if (strpos($class, $prefix) !== 0) {
        return;
    }

    // 3. Quitamos el prefijo "App\" para obtener la ruta relativa de la clase
    // Ejemplo: "App\Core\Database" se convierte en "Core\Database"
    $relative_class = substr($class, strlen($prefix));

    // 4. Definimos el directorio base real de nuestros archivos.
    // Como este archivo está en "app/Core/", usamos dirname(__DIR__) para apuntar a "app/"
    $base_dir = dirname(__DIR__) . '/';

    // 5. Transformamos el namespace en una ruta de archivo real.
    // Reemplazamos las barras invertidas "\" por barras normales "/" y añadimos ".php"
    // Ejemplo: "Core\Database" -> "Core/Database.php"
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';

    // 6. Si el archivo existe físicamente en el servidor, lo importamos.
    if (file_exists($file)) {
        require_once $file;
    }
});
