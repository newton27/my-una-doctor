<?php
declare(strict_types=1);

namespace MyUnaDoctorBranding;

const LOGO_MAX_BYTES = 2000000;
const STORAGE_ENV = 'MY_UNA_DOCTOR_BRANDING_DIR';
const STORAGE_PREFIX = 'my-una-doctor-branding-';
const LANGUAGE_PREFIX = '_my_una_doctor_branding_';

/** This store contains presentation only. It never reads credentials or module data. */
class HttpError extends \RuntimeException
{
    public function __construct(public readonly int $status, public readonly string $errorCode, string $message)
    {
        parent::__construct($message);
    }
}

function privateDirectory(): string
{
    $root = realpath(BX_DIRECTORY_PATH_ROOT);
    $web = realpath($_SERVER['DOCUMENT_ROOT'] ?? BX_DIRECTORY_PATH_ROOT);
    if ($root === false || $web === false || $web === DIRECTORY_SEPARATOR) {
        throw new HttpError(503, 'branding_storage', 'A private branding folder is required.');
    }
    $override = getenv(STORAGE_ENV);
    $path = is_string($override) && $override !== ''
        ? rtrim($override, DIRECTORY_SEPARATOR)
        : dirname($web) . '/' . STORAGE_PREFIX . substr(hash('sha256', $root), 0, 12);
    if (!str_starts_with($path, DIRECTORY_SEPARATOR) || str_contains($path, '/..') || is_link($path)) {
        throw new HttpError(503, 'branding_storage', 'Use an absolute private branding folder.');
    }
    $resolved = realpath($path);
    if ($resolved === false) {
        $parent = realpath(dirname($path));
        if ($parent === false) throw new HttpError(503, 'branding_storage', 'The private parent folder is unavailable.');
        $resolved = $parent . '/' . basename($path);
    }
    foreach ([$root, $web] as $public) {
        if ($resolved === $public || str_starts_with($resolved, $public . '/')) {
            throw new HttpError(503, 'branding_storage', 'The branding folder must be outside the website.');
        }
    }
    return $resolved;
}

function ensureStorage(string $directory): void
{
    if (is_link($directory) || (!is_dir($directory) && !@mkdir($directory, 0700))
        || !is_writable($directory) || !@chmod($directory, 0700)) {
        throw new HttpError(503, 'branding_storage', 'The private branding folder is unavailable.');
    }
}

function defaultBranding(): array
{
    return ['version' => 1, 'brand_name' => '', 'logo_hash' => '', 'logo_mime' => ''];
}

function brandingName(mixed $value): string
{
    if (!is_string($value) || !mb_check_encoding($value, 'UTF-8')
        || mb_strlen($value, 'UTF-8') > 80 || preg_match('/[\x00-\x1f\x7f]/', $value)) {
        throw new HttpError(422, 'invalid_branding', 'Use a name of up to 80 characters without line breaks.');
    }
    return trim($value);
}

function loadBranding(string $directory): array
{
    $path = $directory . '/branding.json';
    if (!file_exists($path) && !is_link($path)) return defaultBranding();
    if (is_link($directory) || is_link($path) || !is_file($path) || filesize($path) > 4096) {
        throw new HttpError(503, 'branding_storage', 'The saved branding could not be read.');
    }
    try {
        $data = json_decode((string)file_get_contents($path), true, 8, JSON_THROW_ON_ERROR);
        if (!is_array($data) || ($data['version'] ?? null) !== 1) throw new \RuntimeException();
        $brand = defaultBranding();
        $brand['brand_name'] = brandingName($data['brand_name'] ?? null);
        $hash = $data['logo_hash'] ?? null;
        $mime = $data['logo_mime'] ?? null;
        if (!is_string($hash) || !is_string($mime)
            || ($hash !== '' && !preg_match('/^[a-f0-9]{64}$/D', $hash))
            || ($hash === '' ? $mime !== '' : !in_array($mime, ['image/png', 'image/jpeg', 'image/webp'], true))) {
            throw new \RuntimeException();
        }
        $brand['logo_hash'] = $hash;
        $brand['logo_mime'] = $mime;
        return $brand;
    } catch (\Throwable $error) {
        throw new HttpError(503, 'branding_storage', 'The saved branding is invalid.');
    }
}

/** Inspect raster headers; an upload filename or browser MIME is never authoritative. */
function inspectLogo(string $bytes): array
{
    if ($bytes === '' || strlen($bytes) > LOGO_MAX_BYTES) {
        throw new HttpError(422, 'invalid_logo', 'Choose a raster logo no larger than 2 MB.');
    }
    $info = @getimagesizefromstring($bytes);
    if (!is_array($info) || !in_array($info['mime'] ?? '', ['image/png', 'image/jpeg', 'image/webp'], true)
        || $info[0] < 1 || $info[1] < 1 || $info[0] > 4096 || $info[1] > 4096
        || $info[0] * $info[1] > 16777216) {
        throw new HttpError(422, 'invalid_logo', 'Choose PNG, JPEG or WebP up to 4096 pixels on each side.');
    }
    return ['hash' => hash('sha256', $bytes), 'mime' => $info['mime']];
}

function uploadedLogo(mixed $upload): ?string
{
    if ($upload === null) return null;
    if (!is_array($upload) || !is_int($upload['error'] ?? null)) {
        throw new HttpError(422, 'invalid_logo', 'The logo upload could not be read.');
    }
    if ($upload['error'] === UPLOAD_ERR_NO_FILE) return null;
    if ($upload['error'] !== UPLOAD_ERR_OK || !is_string($upload['tmp_name'] ?? null)
        || !is_uploaded_file($upload['tmp_name']) || !is_int($upload['size'] ?? null)
        || $upload['size'] < 1 || $upload['size'] > LOGO_MAX_BYTES) {
        throw new HttpError(422, 'invalid_logo', 'The logo upload could not be accepted.');
    }
    $bytes = file_get_contents($upload['tmp_name'], false, null, 0, LOGO_MAX_BYTES + 1);
    if (!is_string($bytes)) throw new HttpError(422, 'invalid_logo', 'The logo upload could not be read.');
    inspectLogo($bytes);
    return $bytes;
}

/** The native adapter checks operator permissions and CSRF before saving. */
function saveBranding(string $directory, array $input, ?string $logo = null): array
{
    $reset = ($input['reset_branding'] ?? '') === '1';
    $name = $reset ? '' : brandingName($input['brand_name'] ?? '');
    $remove = $reset || ($input['remove_logo'] ?? '') === '1';
    if ($remove && $logo !== null) throw new HttpError(422, 'invalid_logo', 'Replace the logo or remove it.');
    $image = $logo === null ? null : inspectLogo($logo);
    ensureStorage($directory);
    $lockPath = $directory . '/branding.lock';
    if (is_link($lockPath) || is_link($directory . '/branding.json')) {
        throw new HttpError(503, 'branding_storage', 'The branding path is invalid.');
    }
    $lock = @fopen($lockPath, 'c+b');
    if ($lock === false || !flock($lock, LOCK_EX)) {
        if (is_resource($lock)) fclose($lock);
        throw new HttpError(503, 'branding_storage', 'Branding could not be locked for saving.');
    }
    @chmod($lockPath, 0600);
    $temporary = null;
    try {
        $brand = $reset ? defaultBranding() : loadBranding($directory);
        $brand['brand_name'] = $name;
        if ($remove) { $brand['logo_hash'] = ''; $brand['logo_mime'] = ''; }
        if ($image !== null) {
            $path = $directory . '/brand-logo-' . $image['hash'] . '.bin';
            if (is_link($path)) throw new HttpError(503, 'branding_storage', 'The logo path is invalid.');
            $temporary = tempnam($directory, '.brand-logo-');
            if ($temporary === false || !@chmod($temporary, 0600)
                || file_put_contents($temporary, $logo) !== strlen($logo) || !@rename($temporary, $path)) {
                throw new HttpError(503, 'branding_storage', 'The logo could not be saved.');
            }
            $temporary = null;
            $brand['logo_hash'] = $image['hash'];
            $brand['logo_mime'] = $image['mime'];
        }
        $json = json_encode($brand, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        $temporary = tempnam($directory, '.branding-');
        if ($temporary === false || !@chmod($temporary, 0600)
            || file_put_contents($temporary, $json) !== strlen($json)
            || !@rename($temporary, $directory . '/branding.json')) {
            throw new HttpError(503, 'branding_storage', 'The branding could not be saved.');
        }
        $temporary = null;
        return $brand;
    } finally {
        if (is_string($temporary) && is_file($temporary)) @unlink($temporary);
        flock($lock, LOCK_UN);
        fclose($lock);
    }
}

function readBrandingLogo(string $directory, string $hash): array
{
    $brand = loadBranding($directory);
    if (!preg_match('/^[a-f0-9]{64}$/D', $hash) || !hash_equals($brand['logo_hash'], $hash)) {
        throw new HttpError(404, 'invalid_logo', 'Logo not found.');
    }
    $path = $directory . '/brand-logo-' . $hash . '.bin';
    if (is_link($path) || !is_file($path) || filesize($path) > LOGO_MAX_BYTES) {
        throw new HttpError(404, 'invalid_logo', 'Logo not found.');
    }
    $bytes = file_get_contents($path, false, null, 0, LOGO_MAX_BYTES + 1);
    if (!is_string($bytes)) throw new HttpError(404, 'invalid_logo', 'Logo not found.');
    $image = inspectLogo($bytes);
    if (!hash_equals($hash, $image['hash']) || $brand['logo_mime'] !== $image['mime']) {
        throw new HttpError(404, 'invalid_logo', 'Logo not found.');
    }
    return ['bytes' => $bytes, 'mime' => $image['mime']];
}

/** A damaged presentation file must never prevent the underlying module from working. */
function presentationFromDirectory(string $directory, string $fallback): array
{
    $result = ['brand_name' => $fallback, 'logo_uri' => ''];
    try {
        $brand = loadBranding($directory);
        if ($brand['brand_name'] !== '') $result['brand_name'] = $brand['brand_name'];
        if ($brand['logo_hash'] !== '') {
            $image = readBrandingLogo($directory, $brand['logo_hash']);
            $result['logo_uri'] = 'data:' . $image['mime'] . ';base64,' . base64_encode($image['bytes']);
        }
    } catch (\Throwable $error) {
        // Public pages retain their normal default mark if branding is missing or damaged.
    }
    return $result;
}

function presentation(string $fallback): array
{
    try { return presentationFromDirectory(privateDirectory(), $fallback); }
    catch (\Throwable $error) { return ['brand_name' => $fallback, 'logo_uri' => '']; }
}

function csrfToken(string $key): string
{
    $session = \BxDolSession::getInstance();
    if (!$session->getId()) $session->start(true);
    $token = $session->getValue($key);
    if (!is_string($token) || !preg_match('/^[a-f0-9]{64}$/D', $token)) {
        $token = bin2hex(random_bytes(32));
        $session->setValue($key, $token);
    }
    return $token;
}

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function renderPage(string $token, string $action, string $fallback): string
{
    if (!\isLogged() || !\isAdmin()) return \MsgBox(\_t('_Access denied'));
    $t = static fn(string $key): string => e((string)\_t(LANGUAGE_PREFIX . $key));
    $brand = defaultBranding();
    $preview = ['brand_name' => $fallback, 'logo_uri' => ''];
    $notice = '';
    try {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        if (!in_array($method, ['GET', 'HEAD', 'POST'], true)) throw new HttpError(405, 'method', 'Use the form controls.');
        if ($method === 'POST') {
            if (!is_string($_POST['branding_csrf'] ?? null) || !hash_equals($token, $_POST['branding_csrf'])) {
                throw new HttpError(403, 'csrf', 'Your session changed.');
            }
            if (!in_array($_POST['branding_action'] ?? null, ['save', 'reset'], true)
                || array_diff(array_keys($_POST), ['branding_action', 'branding_csrf', 'brand_name', 'remove_logo'])
                || array_diff(array_keys($_FILES), ['brand_logo'])) {
                throw new HttpError(422, 'input', 'Check the form fields.');
            }
            $reset = $_POST['branding_action'] === 'reset';
            saveBranding(privateDirectory(), $reset ? ['reset_branding' => '1'] : $_POST,
                $reset ? null : uploadedLogo($_FILES['brand_logo'] ?? null));
            $notice = '<p role="status">' . $t('saved') . '</p>';
        }
        $directory = privateDirectory();
        $brand = loadBranding($directory);
        $preview = presentationFromDirectory($directory, $fallback);
    } catch (HttpError $error) {
        http_response_code($error->status);
        $notice = '<p role="alert">' . $t('error_' . $error->errorCode) . '</p>';
    } catch (\Throwable $error) {
        http_response_code(503);
        $notice = '<p role="alert">' . $t('error_branding_storage') . '</p>';
    }
    ob_start(); ?>
<style>
.myu-client-branding{max-width:840px;padding:24px;font:inherit;color:inherit}.myu-client-branding *{box-sizing:border-box}.myu-client-branding h1{font-size:26px;margin:0 0 12px}.myu-client-branding p{line-height:1.6}.myu-client-branding label{display:block;margin:18px 0 8px;font-weight:600}.myu-client-branding input[type=text]{display:block;width:100%;max-width:560px;padding:12px;font:inherit;color:inherit;background:var(--bx-input-bg-color,transparent);border:1px solid #8aa2af;border-radius:8px}.myu-client-branding input[type=file]{max-width:100%;font:inherit}.myu-client-branding .myu-brand-preview{display:flex;align-items:center;gap:16px;border:1px solid #8aa2af80;border-radius:12px;padding:18px;min-height:100px;margin:20px 0;overflow-wrap:anywhere}.myu-client-branding .myu-brand-preview img{display:block;width:80px;height:64px;object-fit:contain}.myu-client-branding .myu-brand-preview strong{font-size:22px}.myu-client-branding .myu-brand-actions{display:flex;flex-wrap:wrap;gap:12px;margin-top:24px}.myu-client-branding button{padding:12px 18px;border:1px solid #0b536c;border-radius:8px;font:inherit;cursor:pointer;color:#fff;background:#0b536c}.myu-client-branding button[value=reset]{color:inherit;background:transparent;border-color:#8aa2af}.myu-client-branding :focus-visible{outline:3px solid #00a2b6;outline-offset:3px}.myu-client-branding label:has(input[type=checkbox]){font-weight:400}
</style>
<section class="myu-client-branding" aria-labelledby="myu-brand-heading">
<h1 id="myu-brand-heading"><?= $t('title') ?></h1>
<p><?= $t('intro') ?></p><?= $notice ?>
<div class="myu-brand-preview" aria-label="<?= $t('preview') ?>"><?php if ($preview['logo_uri'] !== ''): ?><img src="<?= e($preview['logo_uri']) ?>" alt=""><?php endif; ?><strong dir="auto"><?= e($preview['brand_name']) ?></strong></div>
<form method="post" enctype="multipart/form-data" action="<?= e($action) ?>">
<input type="hidden" name="branding_csrf" value="<?= e($token) ?>">
<label for="myu-brand-name"><?= $t('name') ?></label>
<input id="myu-brand-name" type="text" name="brand_name" value="<?= e($brand['brand_name']) ?>" maxlength="80" placeholder="<?= e($fallback) ?>" dir="auto" aria-describedby="myu-brand-name-help">
<p id="myu-brand-name-help"><?= $t('name_help') ?></p>
<label for="myu-brand-logo"><?= $t('logo') ?></label>
<input id="myu-brand-logo" type="file" name="brand_logo" accept="image/png,image/jpeg,image/webp" aria-describedby="myu-brand-logo-help">
<p id="myu-brand-logo-help"><?= $t('logo_help') ?></p>
<label><input type="checkbox" name="remove_logo" value="1"> <?= $t('remove') ?></label>
<div class="myu-brand-actions"><button type="submit" name="branding_action" value="save"><?= $t('save') ?></button><button type="submit" name="branding_action" value="reset"><?= $t('reset') ?></button></div>
</form>
<p><?= $t('scope') ?></p>
</section>
<?php return (string)ob_get_clean();
}
