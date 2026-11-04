"""Keep native Doctor icons within the pinned UNA Font Awesome aliases."""
import json
import os
from pathlib import Path
import re
import subprocess
import unittest

ROOT = Path(__file__).resolve().parents[1]


class NativeIconAliasesTests(unittest.TestCase):
    def test_rendered_native_menu_and_scan_icons_have_official_glyphs(self):
        fixture = json.loads((ROOT / 'tests/fixtures/una-icon-aliases.json').read_text())
        aliases = fixture['aliases']
        output = subprocess.check_output([os.environ.get('DOCTOR_PHP_BIN', 'php'),
            str(ROOT / 'tests/fixtures/native-icons-render.php')], text=True)
        rendered = json.loads(output)
        self.assertIn('Run Full Health Scan', rendered['overview_html'])
        scan_icon = re.search(r'<a class="bx-btn mud-run".*?<i class="sys-icon\s+([\w-]+)"', rendered['overview_html'])
        self.assertIsNotNone(scan_icon, 'Full Health Scan must expose an icon')
        self.assertTrue(rendered['menu_icons'])
        icons = rendered['menu_icons'] + [scan_icon.group(1)]
        failures = [icon for icon in icons if icon not in aliases]
        self.assertFalse(failures, 'Unsupported native icon aliases: ' + ', '.join(failures))


if __name__ == '__main__':
    unittest.main()
