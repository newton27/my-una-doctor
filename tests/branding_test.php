<?php
declare(strict_types=1);

$ns = 'MyUnaDoctorBranding';
$moduleRoot = dirname(__DIR__);
$dir = sys_get_temp_dir() . '/client-brand-test-' . bin2hex(random_bytes(6));
mkdir($dir, 0700);
mkdir($dir . '/public_html', 0700);
define('BX_DOL', true);
define('BX_DIRECTORY_PATH_ROOT', $dir . '/public_html/');
$_SERVER['DOCUMENT_ROOT'] = $dir . '/public_html';
require $moduleRoot . '/classes/MyUnaDoctorClientBranding.php';
$strings = [];
$xml = simplexml_load_file($moduleRoot . '/install/langs/en.xml', 'SimpleXMLElement', LIBXML_NONET);
foreach ($xml->string as $string) {
    $key = (string)$string['name'];
    if (isset($strings[$key])) throw new RuntimeException('Duplicate language key: ' . $key);
    $strings[$key] = (string)$string;
}
function _t($key) { return $GLOBALS['strings'][$key] ?? $key; }
function isLogged() { return true; }
function isAdmin() { return true; }
function MsgBox($text) { return $text; }
$call = static fn(string $name, ...$args) => call_user_func($GLOBALS['ns'] . '\\' . $name, ...$args);
$checks = 0;
function check(bool $value, string $message): void {
    if (!$value) throw new RuntimeException('FAIL: ' . $message);
    ++$GLOBALS['checks'];
}
function reject(callable $operation, string $code): void {
    try { $operation(); }
    catch (RuntimeException $error) {
        check(($error->errorCode ?? '') === $code, $code . ' rejected');
        return;
    }
    throw new RuntimeException('FAIL: expected ' . $code);
}
$png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=', true);
$store = $dir . '/private';
try {
    check($call('loadBranding', $store) === $call('defaultBranding'), 'existing site retains default appearance without creating files');
    check(!file_exists($store), 'public read does not create a storage folder');
    reject(fn() => $call('brandingName', str_repeat('x', 81)), 'invalid_branding');
    reject(fn() => $call('brandingName', "Name\nInjected"), 'invalid_branding');
    reject(fn() => $call('brandingName', ['bad']), 'invalid_branding');
    check($call('brandingName', '  مجتمع العائلة  ') === 'مجتمع العائلة', 'multilingual names preserved');
    reject(fn() => $call('inspectLogo', '<svg><script>alert(1)</script></svg>'), 'invalid_logo');
    reject(fn() => $call('inspectLogo', '<html>not an image</html>'), 'invalid_logo');
    reject(fn() => $call('inspectLogo', str_repeat('x', 2000001)), 'invalid_logo');
    $large = $png;
    $large = substr_replace($large, pack('N', 4097), 16, 4);
    reject(fn() => $call('inspectLogo', $large), 'invalid_logo');
    reject(fn() => $call('uploadedLogo', ['error' => 0, 'size' => 1, 'tmp_name' => __FILE__]), 'invalid_logo');
    check($call('uploadedLogo', ['error' => UPLOAD_ERR_NO_FILE]) === null, 'empty file control retains current logo');
    $brand = $call('saveBranding', $store, ['brand_name' => 'Client <Community>'], $png);
    check($call('loadBranding', $store) === $brand, 'brand persists independently');
    check($call('readBrandingLogo', $store, $brand['logo_hash'])['bytes'] === $png, 'selected raster round trip');
    check((fileperms($store) & 0777) === 0700 && (fileperms($store . '/branding.json') & 0777) === 0600, 'private permissions');
    check((fileperms($store . '/brand-logo-' . $brand['logo_hash'] . '.bin') & 0777) === 0600, 'image is not public on disk');
    file_put_contents($store . '/settings.json', '{"sentinel":"preserve settings"}');
    file_put_contents($store . '/ledger.json', '{"sentinel":"preserve records"}');
    $before = [file_get_contents($store . '/settings.json'), file_get_contents($store . '/ledger.json')];
    $kept = $call('saveBranding', $store, ['brand_name' => 'Renamed']);
    check($kept['logo_hash'] === $brand['logo_hash'], 'name change retains image');
    $view = $call('presentationFromDirectory', $store, 'Default');
    check($view['brand_name'] === 'Renamed' && $view['logo_uri'] === 'data:image/png;base64,' . base64_encode($png), 'self-contained presentation uses only verified raster bytes');
    reject(fn() => $call('readBrandingLogo', $store, '../settings.json'), 'invalid_logo');
    reject(fn() => $call('saveBranding', $store, ['remove_logo' => '1'], $png), 'invalid_logo');
    $call('saveBranding', $store, ['remove_logo' => '1', 'brand_name' => 'Keep name']);
    check($call('presentationFromDirectory', $store, 'Default') === ['brand_name' => 'Keep name', 'logo_uri' => ''], 'remove restores default mark while retaining name');
    reject(fn() => $call('readBrandingLogo', $store, $brand['logo_hash']), 'invalid_logo');
    $call('saveBranding', $store, ['reset_branding' => '1']);
    check($call('presentationFromDirectory', $store, 'Default') === ['brand_name' => 'Default', 'logo_uri' => ''], 'reset restores default name and mark');
    check([file_get_contents($store . '/settings.json'), file_get_contents($store . '/ledger.json')] === $before, 'other settings and records are untouched');
    file_put_contents($store . '/branding.json', '{broken');
    check($call('presentationFromDirectory', $store, 'Default')['logo_uri'] === '', 'damaged branding cannot break the public page');
    $call('saveBranding', $store, ['reset_branding' => '1']);
    check($call('loadBranding', $store) === $call('defaultBranding'), 'operator reset can recover damaged metadata');
    unlink($store . '/branding.json');
    symlink($store . '/settings.json', $store . '/branding.json');
    reject(fn() => $call('saveBranding', $store, ['reset_branding' => '1']), 'branding_storage');
    check(file_get_contents($store . '/settings.json') === $before[0], 'symlinked metadata cannot overwrite unrelated settings');
    unlink($store . '/branding.json');
    $env = constant($ns . '\\STORAGE_ENV');
    putenv($env . '=' . $dir . '/public_html/unsafe');
    reject(fn() => $call('privateDirectory'), 'branding_storage');
    putenv($env . '=' . $store);
    $call('saveBranding', $store, ['brand_name' => '<img src=x onerror=bad>'], $png);
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_POST = $_FILES = [];
    $html = $call('renderPage', str_repeat('a', 64), '/studio/module.php?page=branding', 'Default');
    check(str_contains($html, '&lt;img src=x onerror=bad&gt;') && !str_contains($html, '<img src=x'), 'operator preview escapes names');
    check(!str_contains($html, 'CLIENT_BRANDING_LANGUAGE_PREFIX') && !str_contains($html, constant($ns . '\\LANGUAGE_PREFIX')), 'all branding form strings resolve');
    check(str_contains($html, 'multipart/form-data') && str_contains($html, 'name="branding_csrf"'), 'native multipart form retains CSRF');
    putenv($env);
} finally {
    $remove = function(string $path) use (&$remove): void {
        if (is_dir($path) && !is_link($path)) { foreach (scandir($path) as $name) if ($name !== '.' && $name !== '..') $remove($path . '/' . $name); rmdir($path); }
        elseif (file_exists($path) || is_link($path)) unlink($path);
    };
    $remove($dir);
}
echo 'PASS: ' . $checks . " client branding storage and presentation checks.\n";
