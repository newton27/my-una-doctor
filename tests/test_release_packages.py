"""Release archive contracts; no site installation or database access."""
import importlib.util
import sqlite3
from pathlib import Path
import tempfile
import unittest
import zipfile

ROOT = Path(__file__).resolve().parents[1]


class ReleasePackageTests(unittest.TestCase):
    def test_build_full_and_version_matched_non_destructive_updates(self):
        path = ROOT / 'scripts/build-release.py'
        self.assertTrue(path.is_file(), 'Missing validated release builder')
        spec = importlib.util.spec_from_file_location('doctor_build', path)
        builder = importlib.util.module_from_spec(spec)
        spec.loader.exec_module(builder)
        with tempfile.TemporaryDirectory() as temporary:
            root = Path(temporary) / 'source'
            root.mkdir()
            for relative in builder.PAYLOAD_FILES:
                destination = root / relative
                destination.parent.mkdir(parents=True, exist_ok=True)
                destination.write_bytes((ROOT / relative).read_bytes() if (ROOT / relative).is_file() else b'fixture')
            for relative in ('classes/MyUnaDoctorModule.php', 'classes/MyUnaMaintenanceLease.php', 'classes/MyUnaDoctorCron.php', 'install/config.php',
                             'install/sql/install.sql', 'install/sql/maintenance-recovery.sql', 'template/images/icons/std-icon.svg'):
                destination = root / relative
                destination.parent.mkdir(parents=True, exist_ok=True)
                destination.write_bytes((ROOT / relative).read_bytes())
            out = Path(temporary) / 'dist'
            builder.build(root, out, 'a' * 40)
            archives = sorted(out.glob('*.zip'))
            self.assertEqual(len(archives), len(builder.PREVIOUS_VERSIONS) + 1)
            self.assertTrue((out / 'my-una-doctor-0.6.4-to-0.6.8.zip').is_file())
            self.assertTrue((out / 'my-una-doctor-0.6.7-to-0.6.8.zip').is_file())
            with zipfile.ZipFile(out / f'my-una-doctor-{builder.VERSION}.zip') as archive:
                self.assertIn('my_una_doctor/install/sql/install.sql', archive.namelist())
                self.assertIn('my_una_doctor/template/images/icons/std-icon.svg', archive.namelist())
                self.assertIn('my_una_doctor/classes/MyUnaMaintenanceLease.php', archive.namelist())
                self.assertIn('my_una_doctor/classes/MyUnaDoctorCron.php', archive.namelist())
                self.assertFalse(any('/updates/' in p or '/tests/' in p for p in archive.namelist()))
            for previous in builder.PREVIOUS_VERSIONS:
                prefix = f'update_{previous}_{builder.VERSION}/'
                with zipfile.ZipFile(out / f'my-una-doctor-{previous}-to-{builder.VERSION}.zip') as archive:
                    config = archive.read(prefix + 'install/config.php').decode()
                    self.assertIn(f"'version_from' => '{previous}'", config)
                    self.assertIn(f"'version_to' => '{builder.VERSION}'", config)
                    self.assertIn("'compatible_with' => ['15.0.x']", config)
                    self.assertIn(b"'15.0.x'", archive.read(prefix + 'source/install/config.php'))
                    self.assertIn("'execute_sql' => 1", config)
                    self.assertEqual(archive.read(prefix + 'source/install/sql/install.sql'),
                                     (ROOT / 'install/sql/install.sql').read_bytes())
                    sql = archive.read(prefix + 'install/sql/install.sql').decode()
                    with sqlite3.connect(':memory:') as db:
                        db.execute('CREATE TABLE sys_cron_jobs (name TEXT PRIMARY KEY, time TEXT, class TEXT, file TEXT, service_call TEXT)')
                        db.execute("INSERT INTO sys_cron_jobs VALUES ('unrelated', '0 * * * *', 'OtherCron', 'other.php', '')")
                        db.executescript(sql)
                        db.executescript(sql)
                        self.assertEqual(db.execute("SELECT time,class,file FROM sys_cron_jobs WHERE name='my_una_doctor_maintenance_recovery'").fetchall(),
                                         [('* * * * *', 'MyUnaDoctorCron', 'modules/newton/my_una_doctor/classes/MyUnaDoctorCron.php')])
                        self.assertEqual(db.execute('SELECT COUNT(*) FROM sys_cron_jobs').fetchone()[0], 2)
            self.assertEqual(len((out / 'SHA256SUMS').read_text().splitlines()), len(builder.PREVIOUS_VERSIONS) + 1)


if __name__ == '__main__':
    unittest.main()

