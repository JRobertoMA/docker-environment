<?php
/**
 * Panel de diagnóstico — verifica en vivo la configuración de php.ini
 * (zona horaria, seguridad, OPcache, límites de recursos) y el estado
 * de extensiones/funciones del contenedor. Se ejecuta bajo la SAPI real
 * de Apache, no CLI, para reflejar lo que realmente ven las apps web.
 */

$hostname = gethostname();
$sapi = php_sapi_name();

function bool_str(string|bool $iniValue): string {
    return filter_var($iniValue, FILTER_VALIDATE_BOOLEAN) ? 'On' : 'Off';
}

function human_bytes(int|false|null $bytes): string {
    if ($bytes === null) return 'sin límite';
    if ($bytes === false) return 'no disponible';
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $i = $bytes > 0 ? (int) floor(log($bytes, 1024)) : 0;
    $i = min($i, count($units) - 1);
    return round($bytes / (1024 ** $i), 2) . ' ' . $units[$i];
}

function cgroup_memory_limit(): int|false|null {
    $candidates = [
        '/sys/fs/cgroup/memory.max',                   // cgroup v2
        '/sys/fs/cgroup/memory/memory.limit_in_bytes', // cgroup v1
    ];
    foreach ($candidates as $path) {
        if (is_readable($path)) {
            $raw = trim((string) @file_get_contents($path));
            if ($raw === 'max') return null;
            if (is_numeric($raw)) {
                $val = (int) $raw;
                if ($val > PHP_INT_MAX / 2) return null; // sentinel = sin límite
                return $val;
            }
        }
    }
    return false;
}

function check_row(string $label, string $value, string $expected, string $note = ''): array {
    $ok = strtolower(trim((string) $value)) === strtolower(trim((string) $expected));
    return compact('label', 'value', 'expected', 'ok', 'note');
}

$checks = [
    'Zona horaria' => [
        check_row('date.timezone (php.ini)', ini_get('date.timezone'), 'America/Mexico_City'),
        check_row('Zona activa (date_default_timezone_get)', date_default_timezone_get(), 'America/Mexico_City'),
    ],
    'Seguridad' => [
        check_row('expose_php', bool_str(ini_get('expose_php')), 'Off', 'Oculta la versión de PHP en las cabeceras HTTP'),
        check_row('session.cookie_httponly', bool_str(ini_get('session.cookie_httponly')), 'On', 'Bloquea el acceso a la cookie de sesión desde JS'),
        check_row('session.cookie_samesite', ini_get('session.cookie_samesite') ?: '(vacío)', 'Lax', 'Mitigación base de CSRF'),
        check_row('session.use_strict_mode', bool_str(ini_get('session.use_strict_mode')), 'On', 'Evita session fixation'),
        check_row('display_errors', bool_str(ini_get('display_errors')), 'Off', 'No debe mostrar errores en producción'),
        check_row('allow_url_include', bool_str(ini_get('allow_url_include')), 'Off', 'Evita RFI (Remote File Inclusion)'),
    ],
    'OPcache' => [
        check_row('opcache.enable', bool_str(ini_get('opcache.enable')), 'On'),
        check_row('opcache.revalidate_freq', ini_get('opcache.revalidate_freq') . 's', '2s', 'Antes 0s = stat() de cada archivo en cada request'),
        check_row('opcache.interned_strings_buffer', ini_get('opcache.interned_strings_buffer') . 'M', '24M'),
        check_row('opcache.validate_timestamps', bool_str(ini_get('opcache.validate_timestamps')), 'On', 'Necesario para que los proyectos se editen en vivo'),
    ],
    'Límites de recursos' => [
        check_row('memory_limit', ini_get('memory_limit'), '256M', 'Antes 4G — desajustado frente al límite de 768M del contenedor'),
        check_row('max_execution_time', ini_get('max_execution_time') . 's', '300s', 'Antes 72000s (20h) para peticiones web'),
    ],
];

$sectionStatus = [];
foreach ($checks as $section => $rows) {
    $sectionStatus[$section] = !in_array(false, array_column($rows, 'ok'), true);
}
$totalChecks = array_sum(array_map('count', $checks));
$passedChecks = array_sum(array_map(fn($rows) => count(array_filter($rows, fn($r) => $r['ok'])), $checks));

$opcacheStatus = function_exists('opcache_get_status') ? @opcache_get_status(false) : false;

$requiredExtensions = [
    'gd' => 'Manipulación de imágenes',
    'imagick' => 'ImageMagick',
    'pdo_mysql' => 'MySQL (PDO)',
    'mysqli' => 'MySQL (MySQLi)',
    'zip' => 'Compresión ZIP',
    'intl' => 'Internacionalización',
    'soap' => 'Servicios SOAP',
    'curl' => 'Cliente HTTP',
    'mbstring' => 'Cadenas multibyte',
    'fileinfo' => 'Información de archivos',
    'xml' => 'Procesamiento XML',
    'json' => 'JSON',
    'openssl' => 'Encriptación SSL',
    'bcmath' => 'Matemáticas de precisión',
    'memcached' => 'Cliente Memcached',
    'Zend OPcache' => 'Caché de opcode',
];

$functionalTests = [];
try {
    imagecreatetruecolor(10, 10);
    $functionalTests[] = ['ok' => true, 'msg' => 'GD: creación de imágenes funciona'];
} catch (Throwable $e) {
    $functionalTests[] = ['ok' => false, 'msg' => 'GD: error — ' . $e->getMessage()];
}
$functionalTests[] = ['ok' => function_exists('curl_init'), 'msg' => 'cURL: ' . (function_exists('curl_init') ? 'disponible' : 'no disponible')];
$functionalTests[] = ['ok' => json_encode(['t' => 1]) !== false, 'msg' => 'JSON: codificación funcional'];
$functionalTests[] = ['ok' => function_exists('mb_strlen'), 'msg' => 'mbstring: ' . (function_exists('mb_strlen') ? 'funcional' : 'no disponible')];
$functionalTests[] = ['ok' => class_exists('ZipArchive'), 'msg' => 'ZIP: ' . (class_exists('ZipArchive') ? 'clase disponible' : 'no disponible')];
$functionalTests[] = ['ok' => class_exists('IntlDateFormatter'), 'msg' => 'Intl: ' . (class_exists('IntlDateFormatter') ? 'disponible' : 'no disponible')];
$functionalTests[] = ['ok' => class_exists('Imagick'), 'msg' => 'Imagick: ' . (class_exists('Imagick') ? 'disponible' : 'no disponible')];
$functionalTests[] = ['ok' => class_exists('Memcached'), 'msg' => 'Memcached: ' . (class_exists('Memcached') ? 'disponible' : 'no disponible')];
$testFile = sys_get_temp_dir() . '/php_test_' . uniqid() . '.txt';
$writeOk = file_put_contents($testFile, 'test') !== false;
if ($writeOk) @unlink($testFile);
$functionalTests[] = ['ok' => $writeOk, 'msg' => 'Escritura en disco (' . sys_get_temp_dir() . '): ' . ($writeOk ? 'funcional' : 'error')];

$cgroupLimit = cgroup_memory_limit();
$now = new DateTime();
?>
<!DOCTYPE html>
<html lang="es" data-bs-theme="dark">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($hostname) ?> :: diagnóstico</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">
<style>
  :root, [data-bs-theme="dark"] {
    --bs-body-bg: #000000;
    --bs-body-color: #33ff33;
    --bs-border-color: #145214;
    --bs-card-bg: #001500;
    --bs-card-border-color: #145214;
    --bs-link-color: #33ff33;
    --bs-link-hover-color: #a6ffa6;
    --bs-secondary-color: #1f8a1f;
    --bs-tertiary-bg: #001a00;
    --bs-emphasis-color: #a6ffa6;
  }
  body {
    font-family: ui-monospace, SFMono-Regular, "Courier New", monospace;
    background-image: repeating-linear-gradient(0deg, rgba(51,255,51,0.02) 0px, rgba(51,255,51,0.02) 1px, transparent 1px, transparent 2px);
  }
  a { text-decoration: none; }
  .card { transition: box-shadow .15s ease, border-color .15s ease; }
  .card:hover { border-color: #33ff33; box-shadow: 0 0 10px rgba(51,255,51,.35); }
  .text-console-dim { color: #1f8a1f; }
  .badge-console {
    background: #001a00; color: #33ff33; border: 1px solid #145214;
    font-weight: 500; letter-spacing: .5px;
  }
  .led {
    width: 7px; height: 7px; border-radius: 50%; flex-shrink: 0;
    background: #33ff33; box-shadow: 0 0 6px #33ff33;
  }
  .led-ok { background: #33ff33; box-shadow: 0 0 6px #33ff33; }
  .led-bad { background: #ff4d4d; box-shadow: 0 0 6px #ff4d4d; }
  .badge-ok { border-color: #33ff33 !important; color: #33ff33; }
  .badge-bad { border-color: #ff4d4d !important; color: #ff4d4d; }
  .check-row { border-color: #145214 !important; }
  .check-row:last-child { border-bottom: none !important; }
  .section-title { color: #33ff33; letter-spacing: .5px; }
  ::selection { background: #145214; color: #a6ffa6; }
  hr { border-color: #145214; opacity: 1; }
</style>
</head>
<body>
<div class="container py-4">

  <div class="border-start border-2 ps-3 mb-4 small text-console-dim" style="border-color:#145214 !important;">
    <div><span class="text-console">[boot]</span> host: <?= htmlspecialchars($hostname) ?> · sapi: <?= htmlspecialchars($sapi) ?></div>
    <div>[ ok ] PHP <?= PHP_VERSION ?> · <?= $now->format('Y-m-d H:i:s') ?> (<?= date_default_timezone_get() ?>)</div>
    <div>[ <?= $passedChecks === $totalChecks ? 'ok' : 'warn' ?> ] <?= $passedChecks ?>/<?= $totalChecks ?> verificaciones de php.ini en orden</div>
  </div>

  <div class="d-flex justify-content-between align-items-baseline border-bottom pb-3 mb-4">
    <h1 class="h4 mb-0">~/diagnóstico</h1>
    <a href="/" class="small text-console-dim">&larr; volver al panel</a>
  </div>

  <div class="row row-cols-2 row-cols-md-4 g-2 mb-4">
    <?php foreach ($sectionStatus as $section => $ok): ?>
      <div class="col">
        <div class="card h-100">
          <div class="card-body py-2 px-3 d-flex align-items-center gap-2">
            <span class="led <?= $ok ? 'led-ok' : 'led-bad' ?>"></span>
            <span class="small"><?= htmlspecialchars($section) ?></span>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>

  <?php foreach ($checks as $section => $rows): ?>
    <div class="card mb-4">
      <div class="card-body">
        <h2 class="h6 section-title mb-3">// <?= htmlspecialchars($section) ?></h2>
        <?php foreach ($rows as $row): ?>
          <div class="check-row d-flex justify-content-between align-items-center py-2 border-bottom">
            <div class="d-flex align-items-center gap-2">
              <span class="led <?= $row['ok'] ? 'led-ok' : 'led-bad' ?>"></span>
              <span><?= htmlspecialchars($row['label']) ?></span>
            </div>
            <div class="text-end">
              <span class="badge badge-console <?= $row['ok'] ? 'badge-ok' : 'badge-bad' ?>">
                <?= htmlspecialchars($row['value']) ?><?= $row['ok'] ? '' : ' (esperado: ' . htmlspecialchars($row['expected']) . ')' ?>
              </span>
              <?php if ($row['note']): ?>
                <div class="small text-console-dim mt-1"><?= htmlspecialchars($row['note']) ?></div>
              <?php endif; ?>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  <?php endforeach; ?>

  <div class="card mb-4">
    <div class="card-body">
      <h2 class="h6 section-title mb-3">// otros valores de recursos (informativo)</h2>
      <div class="row row-cols-2 row-cols-md-4 g-3">
        <?php
        $infoLimits = [
            'post_max_size' => ini_get('post_max_size'),
            'upload_max_filesize' => ini_get('upload_max_filesize'),
            'max_input_time' => ini_get('max_input_time') . 's',
            'max_input_vars' => ini_get('max_input_vars'),
        ];
        foreach ($infoLimits as $label => $value):
        ?>
          <div class="col">
            <div class="small text-console-dim"><?= htmlspecialchars($label) ?></div>
            <div><?= htmlspecialchars($value) ?></div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

  <div class="card mb-4">
    <div class="card-body">
      <h2 class="h6 section-title mb-3">// memoria: php.ini vs. límite real del contenedor (cgroup)</h2>
      <div class="row row-cols-2 row-cols-md-4 g-3">
        <div class="col">
          <div class="small text-console-dim">memory_limit (php.ini)</div>
          <div><?= htmlspecialchars(ini_get('memory_limit')) ?></div>
        </div>
        <div class="col">
          <div class="small text-console-dim">límite del contenedor (cgroup)</div>
          <div><?= htmlspecialchars(human_bytes($cgroupLimit)) ?></div>
        </div>
        <div class="col">
          <div class="small text-console-dim">uso actual (memory_get_usage)</div>
          <div><?= htmlspecialchars(human_bytes(memory_get_usage(true))) ?></div>
        </div>
        <div class="col">
          <div class="small text-console-dim">pico de esta petición</div>
          <div><?= htmlspecialchars(human_bytes(memory_get_peak_usage(true))) ?></div>
        </div>
      </div>
    </div>
  </div>

  <?php if (is_array($opcacheStatus) && !empty($opcacheStatus)): ?>
    <div class="card mb-4">
      <div class="card-body">
        <h2 class="h6 section-title mb-3">// estadísticas de OPcache</h2>
        <div class="row row-cols-2 row-cols-md-4 g-3">
          <div class="col">
            <div class="small text-console-dim">scripts en caché</div>
            <div><?= (int) ($opcacheStatus['opcache_statistics']['num_cached_scripts'] ?? 0) ?></div>
          </div>
          <div class="col">
            <div class="small text-console-dim">hit rate</div>
            <div><?= round((float) ($opcacheStatus['opcache_statistics']['opcache_hit_rate'] ?? 0), 2) ?>%</div>
          </div>
          <div class="col">
            <div class="small text-console-dim">memoria usada</div>
            <div><?= htmlspecialchars(human_bytes($opcacheStatus['memory_usage']['used_memory'] ?? false)) ?></div>
          </div>
          <div class="col">
            <div class="small text-console-dim">memoria libre</div>
            <div><?= htmlspecialchars(human_bytes($opcacheStatus['memory_usage']['free_memory'] ?? false)) ?></div>
          </div>
        </div>
      </div>
    </div>
  <?php endif; ?>

  <div class="card mb-4">
    <div class="card-body">
      <h2 class="h6 section-title mb-3">// info general</h2>
      <div class="row row-cols-2 row-cols-md-4 g-3">
        <div class="col">
          <div class="small text-console-dim">Sistema operativo</div>
          <div><?= htmlspecialchars(PHP_OS) ?></div>
        </div>
        <div class="col">
          <div class="small text-console-dim">Arquitectura</div>
          <div><?= htmlspecialchars(php_uname('m')) ?></div>
        </div>
        <div class="col">
          <div class="small text-console-dim">Servidor</div>
          <div><?= htmlspecialchars($_SERVER['SERVER_SOFTWARE'] ?? 'N/D') ?></div>
        </div>
        <div class="col">
          <div class="small text-console-dim">Zend Engine</div>
          <div><?= htmlspecialchars(zend_version()) ?></div>
        </div>
      </div>
    </div>
  </div>

  <div class="card mb-4">
    <div class="card-body">
      <h2 class="h6 section-title mb-3">// extensiones PHP (<?= count(array_filter(array_keys($requiredExtensions), 'extension_loaded')) ?>/<?= count($requiredExtensions) ?>)</h2>
      <div class="row row-cols-2 row-cols-md-3 g-2">
        <?php foreach ($requiredExtensions as $ext => $desc): $loaded = extension_loaded($ext); ?>
          <div class="col">
            <div class="d-flex align-items-center gap-2 small">
              <span class="led <?= $loaded ? 'led-ok' : 'led-bad' ?>"></span>
              <span><strong><?= htmlspecialchars($ext) ?></strong> — <?= htmlspecialchars($desc) ?></span>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

  <div class="card mb-4">
    <div class="card-body">
      <h2 class="h6 section-title mb-3">// pruebas funcionales</h2>
      <?php foreach ($functionalTests as $test): ?>
        <div class="check-row d-flex align-items-center gap-2 py-2 border-bottom">
          <span class="led <?= $test['ok'] ? 'led-ok' : 'led-bad' ?>"></span>
          <span class="small"><?= htmlspecialchars($test['msg']) ?></span>
        </div>
      <?php endforeach; ?>
    </div>
  </div>

  <hr>
  <footer class="small text-console-dim">diagnóstico generado dinámicamente · sapi: <?= htmlspecialchars($sapi) ?> · <?= $now->format('Y-m-d H:i:s') ?></footer>

</div>
</body>
</html>
