"""Real multipart requests through the owned module's native Studio entry method."""
from pathlib import Path
import base64, contextlib, hashlib, http.cookiejar, os, re, socket, subprocess, tempfile, time, urllib.error, urllib.request

ROOT = Path(__file__).resolve().parents[1]
PNG = base64.b64decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=')
checks = 0
def check(value, message):
    global checks
    assert value, message
    checks += 1
def multipart(fields, image=None, filename='logo.png', mime='image/png'):
    boundary = 'BrandingBoundary20261118'
    parts = []
    for name, value in fields.items():
        parts.append((f'--{boundary}\r\nContent-Disposition: form-data; name="{name}"\r\n\r\n{value}\r\n').encode())
    if image is not None:
        parts.append((f'--{boundary}\r\nContent-Disposition: form-data; name="brand_logo"; filename="{filename}"\r\nContent-Type: {mime}\r\n\r\n').encode() + image + b'\r\n')
    parts.append(f'--{boundary}--\r\n'.encode())
    return b''.join(parts), 'multipart/form-data; boundary=' + boundary

with tempfile.TemporaryDirectory(prefix='native-branding-http-') as temporary:
    tmp = Path(temporary)
    (tmp / 'public_html').mkdir()
    private = tmp / 'private'
    with socket.socket() as sock:
        sock.bind(('127.0.0.1', 0))
        port = sock.getsockname()[1]
    env = dict(os.environ, BRANDING_HTTP_ROOT=temporary)
    env['MY_UNA_DOCTOR_BRANDING_DIR'] = str(private)
    base = f'http://127.0.0.1:{port}'
    opener = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
    def request(path='/?role=admin', fields=None, image=None, filename='logo.png', mime='image/png'):
        data, headers = None, {}
        if fields is not None:
            data, kind = multipart(fields, image, filename, mime)
            headers['Content-Type'] = kind
        try:
            with opener.open(urllib.request.Request(base + path, data=data, headers=headers), timeout=10) as response:
                return response.status, response.read().decode()
        except urllib.error.HTTPError as error:
            return error.code, error.read().decode()
    def digest():
        return {str(p.relative_to(private)): hashlib.sha256(p.read_bytes()).hexdigest() for p in private.rglob('*') if p.is_file()}
    with (tmp / 'server.log').open('wb') as log:
        server = subprocess.Popen([os.environ.get('RW_PHP', 'php'), '-d', 'upload_max_filesize=3M', '-d', 'post_max_size=4M', '-S', f'127.0.0.1:{port}', str(ROOT / 'tests/fixtures/branding_http.php')], cwd=ROOT, env=env, stdout=log, stderr=subprocess.STDOUT)
        try:
            for _ in range(100):
                if server.poll() is not None: raise AssertionError('PHP fixture failed: ' + (tmp / 'server.log').read_text())
                try:
                    status, html = request()
                    break
                except urllib.error.URLError: time.sleep(.05)
            else: raise AssertionError('PHP fixture did not start')
            check(status == 200 and 'name="branding_csrf"' in html, 'operator reaches native branding tab')
            token = re.search(r'name="branding_csrf" value="([a-f0-9]{64})"', html).group(1)
            check(not private.exists(), 'viewing the form performs no file writes')
            fields = {'branding_action': 'save', 'branding_csrf': token, 'brand_name': 'Client <Community>'}
            status, html = request(fields={**fields, 'branding_csrf': 'wrong'}, image=PNG)
            check(status == 403 and not private.exists(), 'bad CSRF cannot save a genuine multipart upload')
            for role in ('guest', 'member', 'disabled'):
                status, html = request('/?role=' + role, fields=fields, image=PNG)
                check('name="brand_name"' not in html and not private.exists(), role + ' cannot edit branding')
            status, html = request(fields=fields, image=PNG, filename='disguised.svg', mime='text/plain')
            check(status == 200 and 'Client &lt;Community&gt;' in html, 'operator upload is validated by bytes and saved')
            check((private / 'branding.json').is_file(), 'metadata persists privately')
            status, public = request('/?role=member&surface=public')
            check('Client &lt;Community&gt;' in public and 'Client <Community>' not in public, 'actual public renderer escapes the configured name')
            check('data:image/png;base64,' + base64.b64encode(PNG).decode() in public, 'actual public renderer displays the selected raster')
            check('My UNA Doctor' in public and 'data-start="1700000000"' in public and 'data-end="1700000300"' in public, 'maintenance timing and product attribution are preserved')
            before = digest()
            for image in (b'<svg><script>alert(1)</script></svg>', b'x' * 2000001):
                status, html = request(fields=fields, image=image, filename='spoofed.png')
                check(status == 422 and digest() == before, 'invalid or oversized logo leaves saved branding untouched')
            status, html = request(fields={**fields, 'brand_name': 'x' * 81})
            check(status == 422 and digest() == before, 'invalid name preserves saved branding')
            for role in ('guest', 'member', 'disabled'):
                request('/?role=' + role, fields=fields, image=PNG)
                check(digest() == before, role + ' cannot replace existing branding')
            status, html = request(fields={**fields, 'brand_name': 'Renamed'})
            check(status == 200 and 'data:image/png;base64,' in html, 'name-only save retains selected logo')
            status, html = request(fields={**fields, 'remove_logo': '1'})
            check(status == 200 and 'data:image/png;base64,' not in html, 'remove control restores the default mark')
            status, html = request(fields={**fields, 'branding_action': 'reset'})
            check(status == 200 and 'Client &lt;Community&gt;' not in html, 'reset restores default name')
            status, public = request('/?role=member&surface=public')
            check('data:image/png;base64,' not in public and 'My UNA Doctor' in public, 'reset is reflected by the actual public renderer')
        finally:
            server.terminate()
            server.wait(timeout=10)
print(f'PASS: {checks} native operator, CSRF, multipart and public-renderer checks')
