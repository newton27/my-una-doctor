"""UNA executeSQL boundary and disposable MySQL installation regressions.

Run without dependencies: python3 -m unittest discover -s tests -p 'test_*.py'
Set DOCTOR_TEST_MYSQL=1 and install PyMySQL for database execution.
Only the dedicated database doctor_install_regression is created/dropped.
"""
import os
from pathlib import Path
import unittest

ROOT = Path(__file__).resolve().parents[1]


def una_statements(sql):
    """Mirror executeSQL's trim, comments, delimiters and no-space concatenation."""
    pending, delimiter, statements = '', ';', []
    for raw in sql.splitlines():
        line = raw.strip()
        if not line or line.startswith(('#', '--')):
            continue
        if 'DELIMITER //' in line or 'DELIMITER ;' in line:
            delimiter = line.replace('DELIMITER', '').strip()
            continue
        pending += line
        if line.endswith(delimiter):
            statements.append(pending if delimiter == ';' else pending.replace(delimiter, ''))
            pending = ''
    if pending:
        raise AssertionError('Unterminated SQL statement')
    return statements


class InstallerFormatTests(unittest.TestCase):
    def test_parser_reproduces_client_charset_failure(self):
        self.assertIn('utf8mb4COLLATE', una_statements(
            'CREATE TABLE t (id INT) DEFAULT CHARSET=utf8mb4\nCOLLATE=utf8mb4_general_ci;')[0])

    def test_parser_reproduces_client_limit_failure(self):
        self.assertIn('LIMIT1)', una_statements('SET @x = (SELECT id FROM t LIMIT\n1);')[0])

    def test_all_module_sql_has_one_complete_statement_per_line(self):
        for path in ROOT.rglob('*.sql'):
            with self.subTest(path=str(path.relative_to(ROOT))):
                sql = path.read_text()
                active = [line.strip() for line in sql.splitlines()
                          if line.strip() and not line.strip().startswith(('--', '#'))]
                self.assertTrue(all(line.endswith(';') for line in active))
                self.assertEqual(una_statements(sql), active)

    def test_studio_registration_inserts_are_retry_guarded(self):
        statements = una_statements((ROOT / 'install/sql/install.sql').read_text())
        inserts = [s for s in statements if s.startswith('INSERT INTO `sys_std_')]
        self.assertEqual(len(inserts), 3)
        for statement in inserts:
            with self.subTest(statement=statement[:70]):
                self.assertIn('NOT EXISTS', statement)


@unittest.skipUnless(os.environ.get('DOCTOR_TEST_MYSQL') == '1',
                     'Requires disposable MySQL/MariaDB service and PyMySQL')
class InstallerDatabaseTests(unittest.TestCase):
    def setUp(self):
        import pymysql
        self.db = pymysql.connect(host=os.environ.get('MYSQL_HOST', '127.0.0.1'),
                                 port=int(os.environ.get('MYSQL_PORT', '3306')),
                                 user=os.environ.get('MYSQL_USER', 'root'),
                                 password=os.environ['MYSQL_PASSWORD'], autocommit=True,
                                 charset='utf8mb4')
        self.addCleanup(self.db.close)
        self.statements = una_statements((ROOT / 'install/sql/install.sql').read_text())

    def execute(self, statement):
        with self.db.cursor() as cursor:
            cursor.execute(statement)
            return cursor.fetchall()

    def reset(self):
        self.execute('DROP DATABASE IF EXISTS doctor_install_regression')
        self.execute('CREATE DATABASE doctor_install_regression CHARACTER SET utf8mb4')
        self.db.select_db('doctor_install_regression')
        for statement in una_statements((ROOT / 'tests/fixtures/una-settings-studio.sql').read_text()):
            self.execute(statement)

    def run_install(self, statements=None):
        # Session variables must not leak from an interrupted prior connection.
        self.execute('SET @sName=NULL, @sPath=NULL, @iPageId=NULL, @iWidgetId=NULL, @iParentPageId=NULL, @iParentPageOrder=NULL, @iDoctorOptionsTypeId=NULL, @iDoctorOptionsCategoryId=NULL')
        for number, statement in enumerate(self.statements if statements is None else statements, 1):
            try:
                self.execute(statement)
            except Exception as error:
                self.fail(f'Statement {number} failed: {error}\n{statement}')

    def snapshot(self):
        tables = ['sys_options_types', 'sys_options_categories', 'sys_options',
                  'sys_std_pages', 'sys_std_widgets', 'sys_std_pages_widgets']
        return {table: self.execute(f'SELECT * FROM `{table}` ORDER BY 1, 2') for table in tables}

    def assert_registration(self):
        page = self.execute("SELECT id FROM sys_std_pages WHERE name='my_una_doctor'")
        self.assertEqual(len(page), 1)
        widget = self.execute("SELECT id, page_id FROM sys_std_widgets WHERE module='my_una_doctor' AND type='manage'")
        self.assertEqual(len(widget), 1)
        self.assertEqual(widget[0][1], page[0][0])
        self.assertEqual(self.execute('SELECT page_id, widget_id FROM sys_std_pages_widgets WHERE widget_id=%s' % widget[0][0]), ((1, widget[0][0]),))
        self.assertEqual(self.execute("SELECT COUNT(*) FROM sys_options WHERE name LIKE 'my_una_doctor_%'"), ((11,),))

    def test_fresh_install_then_repeat_preserves_data_and_settings(self):
        self.reset()
        self.run_install()
        self.assert_registration()
        self.execute("UPDATE sys_options SET value='20' WHERE name='my_una_doctor_work_cycle_size'")
        self.execute("INSERT INTO my_una_doctor_scans (result_json) VALUES ('{\"retained\":true}')")
        before = self.snapshot()
        self.run_install()
        self.assert_registration()
        self.assertEqual(before, self.snapshot())
        self.assertEqual(self.execute('SELECT result_json FROM my_una_doctor_scans'), (('{"retained":true}',),))

    def test_retry_after_every_completed_statement(self):
        for prefix_length in range(len(self.statements) + 1):
            with self.subTest(completed_statements=prefix_length):
                self.reset()
                self.run_install(self.statements[:prefix_length])
                self.run_install()
                self.assert_registration()
                before = self.snapshot()
                self.run_install()
                self.assertEqual(before, self.snapshot())


if __name__ == '__main__':
    unittest.main(verbosity=2)
