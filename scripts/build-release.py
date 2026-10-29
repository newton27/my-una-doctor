#!/usr/bin/env python3
"""Build unpublished 0.6.7 installation and version-specific UNA updater archives."""
import argparse
import hashlib
import json
from pathlib import Path
import re
import subprocess
import zipfile

VERSION = '0.6.7'
PREVIOUS_VERSIONS = tuple(f'0.6.{n}' for n in range(1, 7))
PAYLOAD_FILES = (
    'CHANGELOG.md', 'INSTALLATION.txt', 'MARKETPLACE-DESCRIPTION.html',
    'MARKETPLACE.txt', 'README.md', 'cover.jpg', 'request.php',
    'docs/RELEASE-0.6.7.md', 'docs/RELEASE-0.6.6.md', 'docs/INSTALL-SQL-COMPATIBILITY.md',
    'docs/RELEASE-0.6.5.md',
    'docs/REPAIR-BARRIER-2.md', 'docs/RUNTIME-PROTECTION.md',
)


def updater_config(previous):
    return f"""<?php
$aConfig = array(
    'title' => 'My UNA Doctor',
    'version_from' => '{previous}',
    'version_to' => '{VERSION}',
    'vendor' => 'My UNA World',
    'compatible_with' => ['15.0.x'],
    'home_dir' => 'newton/my_una_doctor/updates/update_{previous}_{VERSION}/',
    'home_uri' => 'my_una_doctor_update_{previous.replace('.', '')}_{VERSION.replace('.', '')}',
    'module_dir' => 'newton/my_una_doctor/',
    'module_uri' => 'my_una_doctor',
    'db_prefix' => 'my_una_doctor_',
    'class_prefix' => 'MyUnaDoctor',
    'install' => array('update_files' => 1, 'clear_db_cache' => 1, 'clear_template_cache' => 1),
    'language_category' => 'My UNA Doctor',
    'delete_files' => array(),
);
"""


def write_zip(path, files):
    with zipfile.ZipFile(path, 'w', compression=zipfile.ZIP_DEFLATED, compresslevel=9) as archive:
        for name, content in sorted(files.items()):
            info = zipfile.ZipInfo(name, date_time=(2026, 10, 26, 0, 0, 0))
            info.compress_type = zipfile.ZIP_DEFLATED
            info.external_attr = 0o100644 << 16
            archive.writestr(info, content)
    with zipfile.ZipFile(path) as archive:
        if archive.testzip() is not None:
            raise ValueError(f'Corrupt archive: {path}')
        if set(archive.namelist()) != set(files):
            raise ValueError(f'Inventory mismatch: {path}')
        for name, content in files.items():
            if archive.read(name) != content:
                raise ValueError(f'Byte mismatch: {name}')


def build(root, output, commit):
    if not re.fullmatch(r'[0-9a-f]{40}', commit):
        raise ValueError('An exact source commit is required')
    output.mkdir(parents=True, exist_ok=True)
    if list(output.glob('*.zip')):
        raise ValueError('Refusing to overwrite existing ZIP artifacts')
    paths = [root / name for name in PAYLOAD_FILES]
    for directory in ('classes', 'install', 'template'):
        paths.extend(p for p in (root / directory).rglob('*') if p.is_file())
    payload = {}
    for path in paths:
        if path.is_symlink() or not path.is_file():
            raise ValueError(f'Missing or symlinked payload: {path}')
        relative = path.relative_to(root).as_posix()
        if '__pycache__' in relative or relative.endswith(('.pyc', '.bak')):
            raise ValueError(f'Unexpected payload: {relative}')
        payload[relative] = path.read_bytes()
    if f"'version' => '{VERSION}'".encode() not in payload['install/config.php']:
        raise ValueError('Module version does not match release')
    icon = 'template/images/icons/std-icon.svg'
    if not payload.get(icon):
        raise ValueError('Missing Studio launcher icon')
    write_zip(output / f'my-una-doctor-{VERSION}.zip',
              {'my_una_doctor/' + name: data for name, data in payload.items()})
    installer = b'<?php\nclass MyUnaDoctorUpdater extends BxDolStudioUpdater\n{\n}\n'
    for previous in PREVIOUS_VERSIONS:
        prefix = f'update_{previous}_{VERSION}/'
        files = {prefix + 'source/' + name: data for name, data in payload.items()}
        files[prefix + 'install/config.php'] = updater_config(previous).encode()
        files[prefix + 'install/installer.php'] = installer
        # Deliberately no top-level install/sql: the updater never reruns full SQL.
        write_zip(output / f'my-una-doctor-{previous}-to-{VERSION}.zip', files)
    assets = []
    for path in sorted(output.glob('*.zip')):
        assets.append({'name': path.name, 'bytes': path.stat().st_size,
                       'sha256': hashlib.sha256(path.read_bytes()).hexdigest()})
    (output / 'SHA256SUMS').write_text(''.join(f"{a['sha256']}  {a['name']}\n" for a in assets))
    (output / 'RELEASE-EVIDENCE.json').write_text(json.dumps({
        'version': VERSION, 'source_commit': commit, 'assets': assets,
        'payload_files': len(payload), 'payload_sha256': {
            name: hashlib.sha256(data).hexdigest() for name, data in sorted(payload.items())},
        'native_studio_acceptance': 'NOT TESTED', 'aws_rds_acceptance': 'NOT TESTED',
    }, indent=2) + '\n')
    return assets


if __name__ == '__main__':
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--output', type=Path, default=Path('dist'))
    args = parser.parse_args()
    root = Path(__file__).resolve().parents[1]
    commit = subprocess.check_output(['git', 'rev-parse', 'HEAD'], cwd=root, text=True).strip()
    assets = build(root, args.output.resolve(), commit)
    print(json.dumps({'source_commit': commit, 'assets': assets}, indent=2))

