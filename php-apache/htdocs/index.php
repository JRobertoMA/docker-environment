<?php
/**
 * Panel de proyectos — landing del contenedor Apache/PHP
 * Lista subcarpetas de proyectos y archivos .php/.html sueltos en la raíz.
 */

$root = __DIR__;
$hostname = gethostname();
$excludedDirs = ['.', '..', 'assets', 'vendor', 'node_modules', '.git', '.idea', 'cgi-bin'];
$selfFile = basename(__FILE__);

function human_time_diff($timestamp) {
    $diff = time() - $timestamp;
    if ($diff < 60) return 'hace segundos';
    if ($diff < 3600) return 'hace ' . floor($diff / 60) . ' min';
    if ($diff < 86400) return 'hace ' . floor($diff / 3600) . ' h';
    if ($diff < 2592000) return 'hace ' . floor($diff / 86400) . ' d';
    return date('d M Y', $timestamp);
}

function detect_type($path) {
    if (file_exists($path . '/docker-compose.yml') || file_exists($path . '/docker-compose.yaml') || file_exists($path . '/Dockerfile')) {
        return ['label' => 'DOCKER', 'glyph' => '▣'];
    }
    if (file_exists($path . '/package.json')) {
        return ['label' => 'NODE', 'glyph' => '◆'];
    }
    if (file_exists($path . '/composer.json') || file_exists($path . '/index.php')) {
        return ['label' => 'PHP', 'glyph' => '◈'];
    }
    if (file_exists($path . '/index.html')) {
        return ['label' => 'STATIC', 'glyph' => '▢'];
    }
    return ['label' => 'DIR', 'glyph' => '▫'];
}

function git_info($path) {
    if (!is_dir($path . '/.git')) return null;
    $head = @file_get_contents($path . '/.git/HEAD');
    if ($head === false) return null;
    if (preg_match('/ref: refs\/heads\/(.+)/', $head, $m)) {
        return trim($m[1]);
    }
    return substr(trim($head), 0, 7);
}

function human_filesize($bytes) {
    if ($bytes < 1024) return $bytes . ' B';
    if ($bytes < 1048576) return round($bytes / 1024, 1) . ' KB';
    return round($bytes / 1048576, 1) . ' MB';
}

$projects = [];
$files = [];

foreach (scandir($root) as $item) {
    $full = $root . '/' . $item;

    if (is_dir($full)) {
        if (in_array($item, $excludedDirs) || strpos($item, '.') === 0) continue;
        $projects[] = [
            'name'   => $item,
            'path'   => $item,
            'mtime'  => filemtime($full),
            'type'   => detect_type($full),
            'branch' => git_info($full),
        ];
        continue;
    }

    if (is_file($full)) {
        if ($item === $selfFile) continue;
        $ext = strtolower(pathinfo($item, PATHINFO_EXTENSION));
        if (!in_array($ext, ['php', 'html', 'htm'])) continue;
        $files[] = [
            'name'  => $item,
            'ext'   => $ext,
            'size'  => filesize($full),
            'mtime' => filemtime($full),
        ];
    }
}

usort($projects, fn($a, $b) => $b['mtime'] <=> $a['mtime']);
usort($files, fn($a, $b) => $b['mtime'] <=> $a['mtime']);
?>
<!DOCTYPE html>
<html lang="es" data-bs-theme="dark">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($hostname) ?> :: panel</title>
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
  .card-link-block { color: inherit; display: block; }
  .card-link-block:hover { color: inherit; }
  .led {
    width: 7px; height: 7px; border-radius: 50%;
    background: #33ff33; box-shadow: 0 0 6px #33ff33; flex-shrink: 0;
  }
  .text-console-dim { color: #1f8a1f; }
  .badge-console {
    background: #001a00; color: #33ff33; border: 1px solid #145214;
    font-weight: 500; letter-spacing: .5px;
  }
  .form-control-console {
    background: #001500; color: #33ff33; border: 1px solid #145214;
  }
  .form-control-console:focus {
    background: #001500; color: #33ff33; border-color: #33ff33;
    box-shadow: 0 0 0 .2rem rgba(51,255,51,.25);
  }
  .form-control-console::placeholder { color: #1f8a1f; }
  ::selection { background: #145214; color: #a6ffa6; }
  hr { border-color: #145214; opacity: 1; }
</style>
</head>
<body>
<div class="container py-4">

  <div class="border-start border-2 ps-3 mb-4 small text-console-dim" style="border-color:#145214 !important;">
    <div><span class="text-console">[boot]</span> host: <?= htmlspecialchars($hostname) ?></div>
    <div>[ ok ] document root montado en <?= htmlspecialchars($root) ?></div>
    <div>[ ok ] <?= count($projects) ?> proyecto(s) · <?= count($files) ?> archivo(s) suelto(s)</div>
  </div>

  <div class="d-flex justify-content-between align-items-baseline border-bottom pb-3 mb-4">
    <h1 class="h4 mb-0">~/proyectos</h1>
    <span class="small text-console-dim"><?= date('Y-m-d H:i') ?></span>
  </div>

  <div class="mb-4">
    <input type="text" id="filter" class="form-control form-control-console" placeholder="filtrar..." autocomplete="off">
  </div>

  <?php if (!empty($projects)): ?>
  <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-3 mb-5" id="grid-projects">
    <?php foreach ($projects as $p): ?>
      <div class="col filter-item" data-name="<?= htmlspecialchars(strtolower($p['name'])) ?>">
        <a href="/<?= rawurlencode($p['path']) ?>/" class="card-link-block h-100">
          <div class="card h-100">
            <div class="card-body">
              <div class="d-flex justify-content-between align-items-start mb-2">
                <h2 class="h6 mb-0"><?= htmlspecialchars($p['name']) ?></h2>
                <span class="led mt-1"></span>
              </div>
              <div class="d-flex justify-content-between align-items-center">
                <span class="badge badge-console"><?= $p['type']['glyph'] ?> <?= $p['type']['label'] ?></span>
                <span class="small text-console-dim"><?= human_time_diff($p['mtime']) ?></span>
              </div>
              <?php if ($p['branch']): ?>
                <div class="small text-console-dim mt-2">git · <?= htmlspecialchars($p['branch']) ?></div>
              <?php endif; ?>
            </div>
          </div>
        </a>
      </div>
    <?php endforeach; ?>
  </div>
  <?php else: ?>
    <p class="text-console-dim border border-dashed rounded p-4 mb-5" style="border-style:dashed !important;">No se encontraron subcarpetas de proyectos.</p>
  <?php endif; ?>

  <?php if (!empty($files)): ?>
    <h2 class="h6 text-console-dim mb-3">// archivos en la raíz</h2>
    <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-3 mb-4" id="grid-files">
      <?php foreach ($files as $f): ?>
        <div class="col filter-item" data-name="<?= htmlspecialchars(strtolower($f['name'])) ?>">
          <a href="/<?= rawurlencode($f['name']) ?>" class="card-link-block h-100">
            <div class="card h-100">
              <div class="card-body py-2 px-3 d-flex justify-content-between align-items-center">
                <span>
                  <span class="badge badge-console me-2"><?= strtoupper($f['ext']) ?></span><?= htmlspecialchars($f['name']) ?>
                </span>
                <span class="small text-console-dim"><?= human_filesize($f['size']) ?></span>
              </div>
            </div>
          </a>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <hr>
  <footer class="small text-console-dim">panel generado dinámicamente · <?= count($projects) + count($files) ?> entradas</footer>

</div>

<script>
  const filterInput = document.getElementById('filter');
  const items = document.querySelectorAll('.filter-item');
  if (filterInput) {
    filterInput.addEventListener('input', () => {
      const q = filterInput.value.toLowerCase();
      items.forEach(el => {
        el.style.display = el.dataset.name.includes(q) ? '' : 'none';
      });
    });
  }
</script>
</body>
</html>