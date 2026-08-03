# Perl-Magpie (Web)

A web frontend and collection API for **Perl CPAN distribution test results** — the
same style of data as [cpantesters.org](http://cpantesters.org). Tests are submitted
via a JSON-RPC API, stored in PostgreSQL (with zstd-compressed output bodies), and
surfaced through a browsable, filterable web interface.

This is the *web* half of the Magpie project; test results are submitted by the
Magpie test-runner clients.

## Features

- **Dashboard** with test volume stats (last hour/day/week/month/all-time) and the
  top distributions by test count.
- **Per-distribution pages** showing results grouped by Perl version and OS, with a
  per-version grade breakdown and a configurable test browser.
- **Individual result pages** with the full test output and syntax highlighting of
  PASS/FAIL/NA/UNKNOWN sections.
- **Recent test log** filterable by grade (including inverted `!grade` filters).
- **Search** for distributions/modules (accepts `Foo-Bar` and `Foo::Bar`).
- **JSON-RPC API** (`/api/json-rpc/`) for submitting new test results and querying
  the DB.
- **JSON output** for every page via `?json=1`.
- **Debug output** for every page via `?debug=1` (Krumo).
- **Caching** via Memcached, including HTTP-level fetch of test bodies from
  `api.cpantesters.org` as a fallback when a report isn't stored locally.
- **Bot rate limiting** via Memcached counters for known-abusive user agents.

## Tech Stack

- PHP 8+ (PDO, `pdo_pgsql`, Memcached extension, zstd compression functions)
- PostgreSQL (data store)
- Memcached (caching + rate limiting)
- [Sluz](include/sluz) — custom PHP template engine (templates are `tpls/*.stpl`)
- [DBQuery](include/dbquery) — thin SQL query helper (also ships a Perl port for the client)
- [Krumo](include/krumo) — debugging output
- Bootstrap 5 + jQuery (frontend)

## Installation

### Requirements

- PHP with `pdo_pgsql`, Memcached, and `zstd` support
- PostgreSQL database
- Memcached running on `127.0.0.1:11211`
- A zstd dictionary file (see `include/zstd-dict/`)
- Apache with `mod_rewrite` (or equivalent rewrite support)

### Steps

1. **Configure the database**

   Copy `include/magpie.config.sample.ini` to `include/magpie.config.ini` and set
   your PostgreSQL credentials:

   ```ini
   [db]
   host     = 127.0.0.1
   port     = 5432
   dbname   = magpie_db
   username = magpie_user
   password = change_me
   ```

   `magpie.config.ini` is git-ignored; never commit real credentials.

2. **Load the schema**

   The application expects a PostgreSQL schema containing at least the `test`,
   `tester`, `distribution_info`, `os_arch`, `test_results`, and `dict_info` tables
   (see the SQL used in `results.php`, `dist.php`, and `api/json-rpc/index.php`).

3. **Register the zstd dictionary**

   The dictionary in `include/zstd-dict/magpie-dict-2025` is used to compress new
   test bodies. For decompression to work, a matching row (by `dict_file` name) must
   exist in the `dict_info` table.

4. **Serve the site**

   Point your web server at the repo root and enable the URL rewriting rules in
   `.htaccess`:

   ```
   /dist/...    -> dist.php
   /log/...     -> log.php
   /results/... -> results.php
   /search/...  -> search.php
   ```

## Usage

### Web pages

| URL                          | Page                                             |
| ---------------------------- | ------------------------------------------------ |
| `/`                          | Dashboard with test statistics                   |
| `/dist/Foo-Bar/`             | Test results for a distribution                  |
| `/dist/Foo-Bar/v1.2.3/`      | Results for a specific version                   |
| `/dist/Foo-Bar/v1.2.3/Linux` | Filtered test browser for that version + OS      |
| `/results/<test-uuid>`       | A single test result with full output            |
| `/log/`                      | Recent test log                                  |
| `/log/?grade=FAIL,NA`        | Log filtered by grade (prefix with `!` to invert)|
| `/search/`                   | Search for a distribution                        |

Append `?json=1` to any page for JSON output.

### JSON-RPC API

The API lives at `/api/json-rpc/`. Available methods:

- `dist.add_test($test_epoch, $test_uuid, $dist_name, $dist_version, $tester_name, $grade, $perl_version, $os_name, $os_version, $test_body)` — submit a test result
- `dist.get_test($uuid)` — fetch test metadata by UUID
