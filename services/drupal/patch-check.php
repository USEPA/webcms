<?php
/**
 * patch-check2.php
 *
 * Checks ALL patches listed under "patches" in composer.patches.json (or composer.json extra.patches).
 * For each package:
 *  - Finds install path via vendor/composer/installed.json or installed.php
 *  - Runs patch -p1 --dry-run to see if it applies (non-interactive via --batch)
 *  - Also detects "already applied" via patch -R --dry-run (non-interactive via --batch)
 *
 * Usage (DDEV):
 *   ddev exec -- bash -lc 'php patch-check2.php'
 *
 * Optional env vars:
 *   DOCROOT=web                    (default web)
 *   PATCHES_FILE=composer.patches.json (default composer.patches.json)
 *   PATCHES_DIR=patches            (default patches)
 *   ONLY_PACKAGE=drupal/group      (check only one package)
 */

function out($s) { fwrite(STDOUT, $s . PHP_EOL); }
function err($s) { fwrite(STDERR, "ERROR: " . $s . PHP_EOL); exit(1); }

$projectRoot = realpath(getcwd());
if (!$projectRoot) err("Cannot determine project root (run from your project directory).");

$docroot     = getenv('DOCROOT') ?: 'web';
$patchesFile = getenv('PATCHES_FILE') ?: 'composer.patches.json';
$patchesDir  = getenv('PATCHES_DIR') ?: 'patches';
$onlyPackage = getenv('ONLY_PACKAGE') ?: '';

function load_json($path) {
  if (!file_exists($path)) return null;
  $data = json_decode(file_get_contents($path), true);
  return is_array($data) ? $data : null;
}

function is_url($s) {
  return (bool) preg_match('#^https?://#i', $s);
}

function safe_basename_from_url($url) {
  $p = parse_url($url, PHP_URL_PATH);
  $b = $p ? basename($p) : 'patch';
  if ($b === '' || $b === '/' || $b === '.') $b = 'patch';
  return preg_replace('/[^a-zA-Z0-9_.-]+/', '_', $b);
}

function resolve_local_patch($projectRoot, $patchesDir, $src) {
  // Absolute path
  if (strlen($src) && ($src[0] === '/' || preg_match('/^[A-Za-z]:\\\\/', $src))) {
    return file_exists($src) ? $src : null;
  }

  // Relative candidates
  $candidates = [];
  $candidates[] = $projectRoot . DIRECTORY_SEPARATOR . ltrim($src, '/');
  $candidates[] = $projectRoot . DIRECTORY_SEPARATOR . $patchesDir . DIRECTORY_SEPARATOR . basename($src);
  $candidates[] = $projectRoot . DIRECTORY_SEPARATOR . $patchesDir . DIRECTORY_SEPARATOR . ltrim($src, '/');

  foreach ($candidates as $p) {
    if (file_exists($p)) return $p;
  }
  return null;
}

function download_patch($url, $tmpDir) {
  $name  = safe_basename_from_url($url);
  $local = $tmpDir . DIRECTORY_SEPARATOR . $name;

  // curl first
  $cmd = "curl -Ls " . escapeshellarg($url) . " -o " . escapeshellarg($local) . " 2>/dev/null";
  exec($cmd, $o, $rc);
  if ($rc === 0 && file_exists($local) && filesize($local) > 0) return $local;

  // fallback: php stream
  $data = @file_get_contents($url);
  if ($data !== false && strlen($data) > 0) {
    file_put_contents($local, $data);
    if (file_exists($local) && filesize($local) > 0) return $local;
  }

  return null;
}

/**
 * Run patch non-interactively. Return:
 *  - OK       => patch applies cleanly
 *  - ALREADY  => patch is already applied (reverse applies cleanly)
 *  - FAIL     => neither applies
 */
function patch_status($targetDir, $patchFile) {
  // Important: --batch prevents interactive prompts
  $applyCmd = "cd " . escapeshellarg($targetDir) .
    " && patch -p1 --batch --dry-run < " . escapeshellarg($patchFile) . " >/dev/null 2>&1";
  exec($applyCmd, $o1, $rc1);
  if ($rc1 === 0) return 'OK';

  $reverseCmd = "cd " . escapeshellarg($targetDir) .
    " && patch -p1 --batch -R --dry-run < " . escapeshellarg($patchFile) . " >/dev/null 2>&1";
  exec($reverseCmd, $o2, $rc2);
  if ($rc2 === 0) return 'ALREADY';

  return 'FAIL';
}

/**
 * Build a map of package name => absolute install path.
 * Uses vendor/composer/installed.json OR vendor/composer/installed.php.
 */
function get_install_paths($projectRoot) {
  $paths = [];

  $installedJson = $projectRoot . '/vendor/composer/installed.json';
  if (file_exists($installedJson)) {
    $data = json_decode(file_get_contents($installedJson), true);
    if (is_array($data)) {
      $packages = $data['packages'] ?? $data;
      if (is_array($packages)) {
        foreach ($packages as $p) {
          if (!is_array($p)) continue;
          $name = $p['name'] ?? null;
          $ip   = $p['install-path'] ?? null;
          if ($name && $ip) {
            $abs = $ip;
            if ($abs[0] !== '/' && !preg_match('/^[A-Za-z]:\\\\/', $abs)) {
              $abs = realpath($projectRoot . '/vendor/composer/' . $abs) ?: realpath($projectRoot . '/' . $abs);
            } else {
              $abs = realpath($abs);
            }
            if ($abs) $paths[$name] = $abs;
          }
        }
      }
    }
  }

  // Fallback: installed.php
  $installedPhp = $projectRoot . '/vendor/composer/installed.php';
  if ((!$paths || count($paths) === 0) && file_exists($installedPhp)) {
    $data = require $installedPhp;
    $versions = $data['versions'] ?? [];
    foreach ($versions as $name => $meta) {
      $ip = $meta['install_path'] ?? null;
      if ($ip) {
        $abs = realpath($projectRoot . '/vendor/composer/' . $ip)
          ?: realpath($projectRoot . '/' . $ip)
          ?: realpath($ip);
        if ($abs) $paths[$name] = $abs;
      }
    }
  }

  return $paths;
}

/**
 * Load patch definitions from composer.patches.json (preferred)
 * or composer.json extra.patches (fallback).
 */
$patchConfig = load_json($projectRoot . '/' . $patchesFile);
if (!$patchConfig) {
  $composer = load_json($projectRoot . '/composer.json');
  if (!$composer) err("Cannot read $patchesFile or composer.json.");

  $patches = $composer['extra']['patches'] ?? null;
  if (!is_array($patches)) err("No patches found in $patchesFile and composer.json extra.patches.");
  $patchConfig = ['patches' => $patches];
}

$allPatches = $patchConfig['patches'] ?? null;
if (!is_array($allPatches) || !$allPatches) err("No patches found under 'patches'.");

$installPaths = get_install_paths($projectRoot);

$tmpDir = sys_get_temp_dir() . '/patchcheck_' . getmypid();
@mkdir($tmpDir);

out("Project root: $projectRoot");
out("Using patches file: $patchesFile");
out("Using patches dir: $patchesDir");
out("");

$totals = ['OK'=>0,'ALREADY'=>0,'FAIL'=>0,'MISSING'=>0,'NOINSTALL'=>0];

foreach ($allPatches as $package => $patchesForPackage) {
  if ($onlyPackage && $package !== $onlyPackage) continue;
  if (!is_array($patchesForPackage) || !$patchesForPackage) continue;

  // Determine target directory
  $targetDir = null;

  /**
   * IMPORTANT FIX:
   * For Drupal core patches, the patch paths are usually like "core/modules/..."
   * so we must run patch from DOCROOT (web), not from web/core.
   */
  if ($package === 'drupal/core' || $package === 'drupal/core-recommended') {
    $candidate = $projectRoot . '/' . $docroot; // <-- FIX HERE
    if (is_dir($candidate)) {
      $targetDir = $candidate;
    }
  }

  // Normal packages: use installed paths map
  if (!$targetDir) {
    $targetDir = $installPaths[$package] ?? null;
  }

  out("📦 $package");
  if (!$targetDir || !is_dir($targetDir)) {
    $totals['NOINSTALL']++;
    out("  ⚠️  install path not found (package not installed?)");
    out("");
    continue;
  }

  foreach ($patchesForPackage as $label => $src) {
    $patchFile = null;

    if (is_url($src)) {
      $patchFile = download_patch($src, $tmpDir);
      if (!$patchFile) {
        $totals['MISSING']++;
        out("  [MISSING] $label");
        out("     $src");
        continue;
      }
    } else {
      $patchFile = resolve_local_patch($projectRoot, $patchesDir, $src);
      if (!$patchFile) {
        $totals['MISSING']++;
        out("  [MISSING] $label");
        out("     $src");
        continue;
      }
    }

    $status = patch_status($targetDir, $patchFile);
    $totals[$status]++;

    if ($status === 'OK') {
      out("  ✅ $label");
    } elseif ($status === 'ALREADY') {
      out("  ♻️  $label (already applied / likely in current code)");
    } else {
      out("  ❌ $label");
    }
    out("     $src");
  }

  out("");
}

out("Summary: OK={$totals['OK']}, ALREADY={$totals['ALREADY']}, FAIL={$totals['FAIL']}, MISSING={$totals['MISSING']}, NOINSTALL={$totals['NOINSTALL']}");
