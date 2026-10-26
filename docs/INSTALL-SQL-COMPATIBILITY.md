# UNA 15 installation SQL regression

Client report: My UNA Doctor 0.6.1, UNA 15, MySQL on AWS RDS.
The client successfully installed after consolidating SQL statements onto single lines.

## Source findings

The recorded 0.6.1 source b30c0b909b07cff7da30002a58a9927d696bb6b4 has multiline SQL. UNA executeSQL trims and concatenates input lines without adding whitespace. This joins `utf8mb4` and `COLLATE` and also joins identifiers/keywords in settings subqueries.

Baseline main af75bc9199f264df63779a73dc1c484681200fe3 already contains the single-line formatting correction. Its Studio page, widget and placement inserts were unguarded, however. A retry can fail on duplicate registration or create duplicate records depending on the core schema constraints.

The fix uses guarded inserts and explicit existing-ID lookups. It preserves existing settings, module-owned tables and records. It does not remove duplicates left by earlier installations. Sequential retry safety does not claim concurrent installer safety.

## Regression commands

Offline parser/format checks:

```sh
python3 -m unittest discover -s tests -p 'test_*.py' -v
```

Database checks require PyMySQL and a dedicated disposable service:

```sh
DOCTOR_TEST_MYSQL=1 MYSQL_PASSWORD=regression-only python3 -m unittest discover -s tests -p 'test_*.py' -v
```

The database test drops and recreates only `doctor_install_regression`. Never run it against a customer database. The CI workflow supplies disposable MySQL 8.0 and MariaDB 10.11 containers. Tests execute SQL after reproducing UNA's parser, then interrupt after every completed statement and rerun the installer. They verify a single page/widget/placement, unchanged existing registrations, retained configured setting values and retained scan data.

The settings/Studio schema is a minimal test fixture, not a full UNA installation. Native Studio fresh installation, a native retry, and AWS RDS remain separate acceptance checks. No core or database-configuration change is required for the formatting correction.

## Customer recovery and release handling

For the client whose install already succeeded, this report alone does not justify uninstalling the module or rerunning the full installer. Preserve the client's data and request the exact installed version and error details if any symptoms remain.

Prepare a newly versioned full package after native acceptance checks. Do not silently replace v0.6.1 or any published release artifact. Successful existing installations do not need a database migration solely for this formatting/retry correction; a native updater, if supplied, must not rerun the full installation SQL.
