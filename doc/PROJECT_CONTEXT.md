# LOGG — Project Context

> **Purpose of this file**: give Claude (or any dev) all the context needed to pick the project back up without re-reading everything. Provide it at the start of a session.

---

## 1. Overview

**LOGG** is an internal PHP web application for managing automated test results (QA/testing). It displays test runs by product/branch/environment, lets you rerun tests via Jenkins, and tracks VM deployments.

- **Stack**: PHP, MySQL (`testcomplete` database), Bootstrap 5, PDO
- **Environment**: XAMPP on Windows
- **Local path**: `C:\xampp\htdocs\logg\`
- **Local URL**: `http://localhost/logg/public/`
- **Prod URL**: `https://sqs-sel-cent1.cas-software.dev/logg/public/`
- **GitHub repo**: `jidey/logseite` (public)
- **Raw GitHub**: `https://raw.githubusercontent.com/jidey/logseite/refs/heads/main/[path]`

---

## 2. Project structure

```
logg/
├── config/
│   ├── config.php          # PDO connection ($pdo), "testcomplete" database
│   └── versions_config.php # ★ CENTRAL versions/branches file (see §3.5)
├── src/
│   └── TestLogRepository.php  # Data access class
├── inc/
│   └── db_connect.php      # (legacy mysqli, being phased out)
├── public/                 # ★ Web root
│   ├── index.php           # Main page (TestSets list)
│   ├── details.php         # Scenario detail for a TestSet
│   ├── rerun.php           # Confirmation + rerun test via Jenkins
│   ├── check.php           # Updates running/checked (JSON)
│   ├── post.php            # Stores results (called by Jenkins/TestComplete)
│   ├── dash.php             # Global dashboard (Bootstrap 4) — ★ driven by versions_config.php (see §3.6)
│   ├── vm_config.php       # Nightly VM deployment config
│   ├── cache_management.php # Cache admin
│   ├── css/
│   │   ├── styles.css / styles.min.css  # Main styles
│   │   └── theme.css       # ★ Shared dark/light theme
│   ├── js/
│   │   ├── app.js          # Table sorting, notes, lazy load
│   │   └── theme.js        # ★ Shared theme toggle
│   ├── deployedVM/         # .txt files for deployed builds
│   ├── builds/             # .txt files for latest builds
│   └── nightly/            # save_column.php, load_states.php (vm_config checkboxes)
├── icons/                  # (old clock.png, running.jpg images — no longer used)
└── doc/
```

---

## 3. Critical conventions

### 3.1 testType → MySQL table mapping

The table name depends on **both testType and product**. Since
**08/28/2026**, this mapping is no longer duplicated by hand in every file:
it's centralized in **`config/versions_config.php`** (see §3.5). Current
state (generated from that file):

| Product | testType | Table |
|---------|----------|-------|
| gWWebSel | rc_x17 | x17_rc |
| gWWebSel | hf_x17 | x17_hf |
| gWWebSel | dev_x17 | x17_dev |
| gWWebSel | dev_x19 / rc_x19 / hf_x19 | x19_dev / x19_rc / x19_hf (new) |
| weWebSel (SmartWe) | rc_x18 | we_rc |
| weWebSel (SmartWe) | hf_x18 | we_hf |
| weWebSel (SmartWe) | dev_x18 | we_dev |
| gWClient (Desktop) | hf_x17 | x17_gwhf |
| gWClient (Desktop) | rc_x17 | x17_gwrc |
| gWClient (Desktop) | dev_x16 | x16_gwdev |

> SmartWe always uses `we_*` regardless of the x version. smartWe's
> "current" x version (currently `hf_x18`/`rc_x18`) is a single setting:
> `$SMARTWE_CURRENT_VERSION` in `config/versions_config.php`.

### 3.2 Product detection

```php
$isSmartWe = strpos($product, 'weWebSel'|'weClient'|'smartWe'|'SmartWe') !== false;
$isGwDesktop = strpos($product, 'gWClient') !== false;
```

### 3.3 SmartWe normalization (in both index.php AND details.php)

Done **early**, right after reading the GET params:
- `hf` → `hf_x18` (forced)
- `rc` → `rc_x18` (forced)
- `dev` → keeps its version (`dev_x18`)

The SmartWe Branch selector shows simplified labels (dev/rc/hf) via
`$smartWeMapping` but sends the real testType.

### 3.4 Branches per product (index.php)

- **gW Desktop**: forced list, derived from `$LOGG_GW_DESKTOP_LIST` (the
  `gw_desktop_list` flag per branch in `config/versions_config.php`) —
  currently `hf_x16, rc_x17, hf_x17, rc_x18, hf_x18` (always shown, even with
  no data; `dev` branches have historically not been included).
- **gW Web**: every non-`retired` branch from the central file is shown,
  with no preliminary count query (see §3.5). Those with status `'future'`
  are shown in parentheses `(rc_x19)`.
- **SmartWe**: simplified dev/rc/hf

### 3.5 Central version configuration file (`config/versions_config.php`)

Added on 08/28/2026 to replace the `testType => table` arrays copy-pasted
by hand into 10 files (`TestLogRepository.php`, `index.php`,
`details.php`, `rerun.php`, `vm_config.php`, `post.php`, `check.php`,
`sync_main.php`, `update_testset_stats.php`, `update_validation.php`).

**To add a version** (e.g. gW Web x19): add one block per branch
(`dev_x19`, `rc_x19`, `hf_x19`) to `$LOGG_VERSIONS`, with `status` (`active`
/ `future` / `retired`), `tables` (table name per product), `jenkins_branch`
(the Git branch used by `rerun.php`/Jenkins), and `gw_desktop_list` (whether
it shows in the fixed gW Desktop selector). Nothing else to change — every
file listed above **plus `dash.php` (see §3.6, since 08/31/2026)** reads
this file (either directly, or via `config/config.php`, which `require`s it
and populates `$GLOBALS['product_table_map']`).

**To retire an obsolete version**: set its `status` to `'retired'` (or
delete the block). It instantly disappears from every selector and table
mapping, **including the `dash.php` dashboard**.

**smartWe**: only one "current" x version at a time, set by
`$SMARTWE_CURRENT_VERSION` (e.g. `'x18'`) — replaces separate hardcoded
values in `index.php`, `details.php`, `sync_main.php` **and `dash.php`**.

**⚠️ Cache**: `TestLogRepository::getAvailableTestTypesForProduct()` caches
its result for 1 hour (`src/Cache.php`). After editing the central file,
clear the cache via the **💾 Cache** page (`cache_management.php`) if the
new version doesn't show up right away.

**Added on 08/28/2026 (gW Web x19)**: branches `dev_x19`/`rc_x19`/`hf_x19`
created with status `active` (gWWebSel only). TODO JD:
- Confirm the Jenkins branch names (`jenkins_branch`, currently
  `dev/15.x`/`rc/15.x`/`hotfix/15.x` by extrapolating the x15→x18 pattern).
- Create/verify the MySQL tables `x19_dev`/`x19_rc`/`x19_hf` (and their
  `_daily` variants used by `dash.php`, e.g. `x19_hf_daily`).
- Uncomment the `weWebSel`/`gWClient` entries in the x19 block if smartWe
  or gW Desktop also need to cover x19.
- Confirm the cosmetic build-number prefix for x19 in
  `$LOGG_VERSION_LABEL_PREFIX` (see §3.6) — currently extrapolated to `'29.'`.

**Update (08/31/2026)**: only `dev_x19` is currently `active`; `rc_x19` and
`hf_x19` are set to `retired` until their branches/tables are confirmed and
ready.

**Not covered by this centralization** (left as-is, too much risk/complexity
for a first pass):
- `vm_config.php`, the **smartWe VMs** and **Testcomplete VMs** tabs: still
  hardcoded lists (specific login URLs / individually named VMs). The
  **Selenium VMs** and **Release VMs** tabs are derived from the central
  file.

### 3.6 `dash.php` driven by `versions_config.php` (since 08/31/2026)

The dashboard (the "sd.png" columns = gW Web x16→x19) used to be written
with hardcoded per-version named variables (`$WebDevVersion`,
`$Web2RCVersion`, `$Web1HFVersion`...) and one `<td>` per column written by
hand — adding x19 meant duplicating ~15 lines. It's now driven dynamically:

- `dash.php` builds a `$webBranches` array from
  `$LOGG_VM_BRANCHES` / `$product_table_map` / `logg_branch_vm_parts()`
  (exposed by `config/versions_config.php`), grouped by increasing version
  (x16, x17, x18, x19...) and ordered **HF, RC, DEV** within each version —
  same order as the previous display.
- A branch missing for a given version (e.g. `rc_x16`/`dev_x16`, retired)
  is simply skipped, as before.
- Column color: the existing CSS classes (`.tg-x16/.tg-x17/.tg-x18`,
  defined in the `<style>` block at the top of the file) are reused as-is.
  A version with no dedicated class (e.g. x19) automatically gets a color
  from a small fallback palette (`$webFallbackPalette` in `dash.php`) —
  **no CSS edit is required for a new version to display**; a `.tg-x19`
  class can be added later in the `<style>` block if a color consistent
  with x16/x17/x18 is wanted.
- The cosmetic build-number prefix (`"28.1.2.3"` → `"x18.1.2.3"`) is now
  read from `$LOGG_VERSION_LABEL_PREFIX` (computed automatically in
  `versions_config.php`, pattern = version number + 10). Not confirmed
  beyond x18 (see the x19 TODO in §3.5).
- The header `colspan` (`<th colspan="...">`) is now
  `count($webBranches)` instead of a hardcoded `7`.
- `getBranchVersion()` (reads the `deployedVM/lastSel*Deploy.txt` files)
  no longer does `echo "File not found: ..."` directly into the page when
  a file is missing (that broke the layout — see pitfall #5 in §11):
  it now just returns an empty string. Mostly relevant for a version that
  was just added and whose deployment files don't exist yet (the current
  x19 case).
- The 3 functions that query the database (`isItTimeToGetLastRuns`,
  `readLastRunResults`, `getLastResults`) remain protected by try/catch
  (hardened on 08/28/2026, after the `x16_dev_daily` not-found crash): a
  missing `_daily` table for a version not yet created in the database now
  just shows an empty cell instead of crashing the whole page — tested with
  **zero tables in the database** (worse than the originally reported
  crash) with no fatal error.
- Bonus (same logic): the 2 hardcoded SmartWe URLs `hf_x18`/`rc_x18`/
  `dev_x18` (the "Failed" row, clickable buttons to index.php) now use
  `$LOGG_SMARTWE_HF`/`$LOGG_SMARTWE_RC`/`'dev_' . $SMARTWE_CURRENT_VERSION`.

**To add a version to the dashboard**: nothing to do in `dash.php` — adding
the version to `config/versions_config.php` (§3.5) is enough. It appears
automatically in the columns, with a fallback color if no dedicated CSS
class exists yet.

---

## 4. Jenkins / rerunning tests (rerun.php → check.php)

**Critical point**: Jenkins reads parameters from the **query string (GET)**, not POST.
- Use `CURLOPT_HTTPGET = true` (not `CURLOPT_POST`)
- `sendGetRequest()` with `CURLOPT_FOLLOWLOCATION`, 15s timeout
- Symptom if POST is used: "tests don't start after confirmation" even
  though pasting the URL in the browser (= GET) works

**check.php**:
- Resolves the table via `Testtype` + `Product` (handles SmartWe → we_rc)
- `value=2` or `field=running` → updates the `running` column
- `field=running` forces the update even if `value < 2` (used to reset to 0)
- `value < 2` (without field=running) → updates `checked`
- Always responds in JSON (`display_errors=0` so errors don't corrupt the JSON)
- Table name validated with regex `/^[a-z0-9_]+$/i`

**check.php URL built dynamically** (local, not /logs/):
```php
$protocol://$host . dirname($_SERVER['PHP_SELF']) . "/check.php?value=2&autoid=...&Testtype=...&Product=..."
```

**SmartWe branch mapping for Jenkins (rerun.php ConfirmAndRun)**:
- SmartWe is now **entirely on x18** (see §12.1): dev → `dev/14.x`,
  rc → `rc/14.x`, hf → `hotfix/14.x`. The `$branchMap` in rerun.php maps
  `rc_x18`/`hf_x18`/`dev_x18` (and `we_rc`/`we_hf`/`we_dev`) accordingly.

---

## 5. Theme system (dark/light)

Shared files: `css/theme.css` + `js/theme.js`.
- localStorage key: **`logg-theme`** (`'dark'` / `'light'`)
- Synced across all pages (index, dash, vm_config, cache_management)
- Script loaded **before** the CSS to avoid the flash
- Floating `#themeToggle` button (fixed position, top-right) on secondary pages

**Special theme.css rules**:
- Colored dash cells (`.tg-green/.tg-red/.tg-warn` + their `<a>`) → dark
  text `#1a1a1a` (otherwise unreadable on pale backgrounds)
- vm_config: `bg-success` → dark green `#2e7d32` white text; `bg-warning`
  → orange with dark text; `table-light` → `bg-tertiary` background
- **Checkboxes**: enlarged to 1.3em, 2px grey border (unchecked), bright
  green (checked) — otherwise invisible in dark mode

---

## 6. Preferences cache (localStorage)

| Key | Content |
|-----|---------|
| `logg-prefs` | JSON: {theme, text_size, error_only, product, testtype, test_browser, team_tag, db_server} |
| `logg-theme` | 'dark' / 'light' |
| `logg-error-only` | '0' / '1' |
| `logg-sort` | JSON: {column, order} |

**How it works**:
1. **Early redirect** (`<head>` script): if the URL has no filter, reads
   `logg-prefs` and redirects ONCE with all the params
   (Product/Testtype/TeamTag/ErrorOnly) → PHP loads the right data directly
   (no flash)
2. **Saved on every change**: `savePreferences()` called BEFORE
   `form.submit()` (synchronous, no setTimeout)
3. Sort order saved in `logg-sort`, reapplied by `applySavedSort()` (unless
   it's the default date-desc)

---

## 7. UI — current state

### index.php (main page)
- Columns: TestSet | Tested version | Passed | Failed | Testcases | Log | Run | Duration | Date | Notes | Team
- **Buttons** (Bootstrap, no more images):
  - Testcases: `Details` (btn-primary)
  - Log: `Allure` (btn-info) or `N/A` (btn-secondary disabled)
  - Run: `Run` (btn-success) / `Running` (btn-warning + spinner, clickable to reset)
- **Filters**: Product, Branch, Team, Search Testset, Errors Only (hidden Browser = chrome)
- **Errors Only**: keeps tests with Failed > 0 **AND** tests with running == 2
- Sorted by date desc by default (PHP already sorts; `data-sort-value` =
  timestamp for JS sorting)
- Links to: "Release Dashboard" (dash.php), "Deployments" (vm_config.php), "Cache"

### details.php
- "Trigger" column renamed to **"Run"** (same buttons as index)
- Log as a button (`📋 Log` / `N/A`)
- Preserves Failed Only both ways:
  - index "Errors Only" → details `&OnlyFailed=1`
  - details "Back to TestSets" → index `&ErrorOnly=<0|1>`

### dash.php (Bootstrap 4)
- Clickable Failed cells → index.php with the right Product/Testtype +
  `&ErrorOnly=1` (added automatically by `generateResultCell`)
- Modern URL format: `?Product=weWebSel&Testtype=dev_x18&TestBrowser=chrome`
  (NOT the old `LogVersion=we_dev&Filter=yes`)
- Robustness: `getBranchVersionWe()` handles an empty/SSL-failed API
  response (checks `file_get_contents !== false`, `isset(...[0])`,
  destructuring `?? ['','','']`)
- **gW Web columns (x16→x19) driven dynamically by
  `config/versions_config.php`** since 08/31/2026 (see §3.6) — adding a
  version no longer requires editing `dash.php`
- 3 DB functions (`isItTimeToGetLastRuns`, `readLastRunResults`,
  `getLastResults`) protected by try/catch (08/28/2026): a missing
  `_daily` table = empty cell, no crash

### vm_config.php
- 4 tabs: Selenium / Release / smartWe / Testcomplete VMs
- "Logs" links → `logg/public/index.php` (NOT `/logs/`)
- Nightly Update checkboxes (saved via `nightly/save_column.php`, loaded
  via `nightly/load_states.php`)

---

## 8. post.php (storing Jenkins results)

- Migrated from mysqli (`inc/db_connect.php`) → **PDO** (`config/config.php`)
- Prepared statements (security)
- `unquote()` strips the surrounding single quotes Jenkins sends
- Preserved logic: TCProj decoding (Web/SmartWe urldecode, special x10),
  gWClient with no Browser + table mapping, `Grid`→`Grid-x.7`, DELETE old
  runs (same TCProj+Build) before INSERT, tag/teamtag default `-`,
  gWClient without tag/teamtag columns
- ⚠️ **Verify on the Jenkins side**: the call must point to
  `logg/public/post.php`

---

## 9. Working habits

- JD works with **complete files** (not partial snippets) — return the
  whole modified file
- Conversations are sometimes in **French**
- **Code comments must always be in English**, even when the conversation
  itself is in French (noted 09/01/2026) — applies to all files, including
  `config/versions_config.php` (already translated) and any future edit
- Output files in `/outputs/public/` (project structure), sometimes
  mirrored at the `/outputs/` root too
- Regenerate `styles.min.css` if `styles.css` changes; `app.min.js` if
  `app.js` changes (check which file the page actually loads)
- ⚠️ **Security**: a GitHub token was accidentally shared in a past
  session — stay alert to credential exposure in pasted code/configs

---

## 10. TODO / open items

- [ ] Verify Jenkins/TestComplete calls `post.php` at the right location (`logg/public/`)
- [ ] `generateAnalyseCell` (dash "To check") currently ignores the URL — cells aren't clickable (can be enabled if needed)
- [ ] Confirm whether `app.min.js` / `styles.min.css` are actually loaded (regenerate after edits otherwise)
- [ ] rerun.php: verify `ConfirmAndRun` actually receives `$Product` for the SmartWe branch mapping
- [ ] gW Web x19: confirm the Jenkins branches, create the MySQL tables (+ `_daily`), and confirm the display prefix `$LOGG_VERSION_LABEL_PREFIX['x19']` (see §3.5/§3.6)
- [x] ~~Extend the centralization to `dash.php`~~ — done on 08/31/2026 (see §3.6)
- [ ] Inconsistency found while centralizing (08/28/2026): before this
      refactor, `TestLogRepository.php` had `rc_x16`/`dev_x16` **retired**
      for gWClient (commented out), while `post.php`, `check.php`,
      `update_testset_stats.php` and `update_validation.php` had them
      **active** (`x16_gwrc`/`x16_gwdev`). The central file was aligned with
      `TestLogRepository.php` (retired) — **implicitly confirmed by JD**:
      the `post.php` file re-uploaded afterward already had
      `rc_x16`/`dev_x16` retired from its own local mapping, consistent
      with this choice.

---

## 11. Known pitfalls (don't repeat these)

1. **POST vs GET for Jenkins**: always GET (see §4)
2. **Duplicate JS declaration**: `const textSizeSlider` declared twice →
   SyntaxError that breaks the whole script (e.g. `resetScenarioRunning undefined`)
3. **array_intersect on branches**: filtering branches by actual data
   hides ones with no data yet — for gW Desktop, force the full list
4. **White text on a pale colored background** in dark mode (dash/vm_config
   cells) → force dark text
5. **Raw echo into HTML** (e.g. "Invalid JSON" in dash, or the old
   `getBranchVersion()` doing `echo "File not found: ..."`) breaks the
   layout → use `error_log()` or return an empty value instead
6. **setTimeout(savePreferences)** after submit: risk of navigating away
   before the save happens → save synchronously BEFORE submit

---

## 12. Session updates (Sept 2026)

This section captures the work done in the long Sept-2026 session, on top of
everything above. When something here contradicts an earlier section, **this
section wins** (it is more recent).

### 12.1 SmartWe fully migrated to x18 (RC and HF)

SmartWe is now **entirely on x18**. Both RC and HF were moved:
- `rc` → **`rc_x18`** (was rc_x17)
- `hf` → **`hf_x18`** (was hf_x17 — HF was the last one still on x17)
- `dev` → `dev_x18`

Nothing is on x17 for SmartWe anymore. This is driven by
`$SMARTWE_CURRENT_VERSION = 'x18'` in `config/versions_config.php`, which
computes `$LOGG_SMARTWE_HF = 'hf_x18'` and `$LOGG_SMARTWE_RC = 'rc_x18'`.

**Files using these vars** (normalization `if ($isSmartWe) { hf→$LOGG_SMARTWE_HF;
rc→$LOGG_SMARTWE_RC }`): `index.php`, `details.php`, `sync_main.php`. The
`$smartWeMapping` in index.php sets `['hf']='hf_x18'`, `['rc']='rc_x18'`,
`['feature']='we_feat'`. `dash.php` SmartWe links use `hf_x18`/`rc_x18`/`dev_x18`.

Table resolution is unchanged (SmartWe always → `we_hf`/`we_rc`/`we_dev`),
confirmed present in both `TestLogRepository` fallback map and `check.php`
`$tableMap` for `hf_x18`/`rc_x18`. Endpoints (`check.php`,
`update_validation.php`, `update_testset_stats.php`, `stats.php`) resolve by
`stripos(..., 'hf'/'rc')` → version-independent, no change needed.

Jenkins Git branches for SmartWe (confirmed by JD): rc → `rc/14.x`,
hf → `hotfix/14.x`, dev → `dev/14.x`. **⚠️ Jenkins pipeline gotcha**: the
`putEnvVars()` smartWE section in `webTests.groovy` had `rc/13.x` hardcoded
while Setup Runner expected `rc/14.x` → `env.TEST_URL` was null → empty test
run + empty Allure report. Fixed to `rc/14.x`.

### 12.2 hf_x18 (gW Web) treated as a normal branch

`hf_x18` for gW Web is no longer forced into parentheses "(hf_x18)".
`$LOGG_FUTURE_TESTTYPES` (from versions_config, the `future`-status list)
now drives the parentheses in the selector; hf_x18 has status `active` so it
shows normally. Requires table `x18_hf` to exist + cache clear.

### 12.3 DBServer (SQL / PGS) — new dimension everywhere

Tests can now run against two DB backends: **SQL** or **PGS**. Added across
the stack:
- **rerun.php**: `$DBServer = $_GET['DBServer'] ?? 'SQL'`; a `<select>` (SQL/PGS)
  in the confirmation form; `ConfirmAndRun($...,$DBServer,...)` appends
  `&DBServer=` to the Jenkins URL (was previously hardcoded "SQL" — fixed to
  use the passed value).
- **DB column**: a `DBServer VARCHAR(10) NOT NULL DEFAULT 'SQL'` column must
  exist on every log table (ALTER TABLE per table). Empty value → treated as
  'SQL' by default in the UI.
- **post.php**: must read `DBServer` from GET and INSERT it (default 'SQL').
  ⚠️ For the value to actually vary, the **Selenium/Java runner
  (`LogExportUtil.java`) must forward the `DBServer` param it receives from
  Jenkins** to its post.php call — otherwise the column stays 'SQL'.
- **index.php**: a "DB Server" filter (`All`/SQL/PGS) in `class="filters"`,
  applied in the `array_filter`, persisted in `logg-prefs.db_server`, and
  wired into the early-redirect + auto-submit list. A **"DB" column** in the
  results table (shows the run's DBServer, default 'SQL'). Column count went
  to 12 → all `colspan` updated to 12.
- **details.php**: same "DB" column in the scenarios table.

### 12.4 "Run All Failed" — batch mode with a single parameter form

Rewritten from the old fire-and-forget fetch. Now:
- **details.php** button navigates to `rerun.php` in **batch mode**, passing
  `&TCProjList=<tcproj1>|||<tcproj2>|||...` **and**
  `&AutoIDList=<id1>|||<id2>|||...` (aligned by index, plain `explode` on
  both sides — no `array_filter`, to keep indexes in sync). Also passes the
  TestSet Main `AutoID` and the deployed `Build`.
- **rerun.php** detects `$isBatch = !empty($TCProjList)`, shows the normal
  parameter form **once** (node, localrun, retry, **DBServer**), and on
  Confirm loops over every TCProj: rebuilds the Jenkins URL per scenario AND
  **rebuilds the check.php URL per scenario with its own AutoID**
  (`$runnOne`), so each failed scenario gets `running=2` individually.
  `ConfirmAndRun(..., $doRedirect=false)` for all but the redirect at the end.
- **Hidden fields** `TCProjList` and `AutoIDList` must be in the form so they
  survive the Confirm submit.
- **Validated scenarios are excluded** from both the "Failed Only" filter and
  the "Run All Failed" collection (`if (!empty($sc['checked'])) continue;`).

### 12.5 rerun.php DryRun debug mode

`&DryRun=1` (with `&Confirm=1`) makes `ConfirmAndRun` **display** the check.php
and Jenkins URLs instead of sending them (nothing executed). Works for single
and batch (one debug block per scenario). Invaluable for diagnosing what
actually goes to Jenkins. Signature: `ConfirmAndRun(..., $doRedirect=true,
$dryRun=false)`.

### 12.6 rerun.php — sendGetRequest MUST be file-scope (recurring bug)

`sendGetRequest()` must be declared **at file scope**, NOT inside
`ConfirmAndRun()`. In batch mode `ConfirmAndRun` is called multiple times; if
`sendGetRequest` is nested, PHP throws **"Fatal error: Cannot redeclare
sendGetRequest()"** on the 2nd call. This bug reappeared several times after
re-uploading older rerun.php versions — always check it. General rule: never
declare a function inside another function that can be called in a loop.

### 12.7 Feature branches (web_feat / we_feat) — grouped by JJob

When `$testType === 'we_feat'` or `'web_feat'` (`$isFeature`), index.php:
- resolves the table via a short-circuit in
  `TestLogRepository::getTableForTestType()`: `if ($testType==='we_feat' ||
  $testType==='web_feat') return $testType;` (the testType IS the table name).
- sorts TestSets by JJob then date desc, and inserts a
  `<tr class="job-group-header"><td colspan="12">📁 JJob</td></tr>` on each
  JJob change (like stats.php).
- `$isFeature` is declared **before** the try block (avoids undefined-var
  warning if no jobs).
- ⚠️ TODO: the JS column sort (`sortTable` in app.js) will scramble the group
  header rows if the user clicks a sortable header in feature mode — consider
  dropping the `sortable` class when `$isFeature`.
- `web_feat` added to the gW Web branch list in index.php; `we_feat` added to
  `$smartWeMapping['feature']`.
- Feature runs are posted by the runner with `Testtype='web_feat'`/`'we_feat'`
  and `Product='gWWebSel'`/`'weWebSel'` respectively.

### 12.8 "Validated" hides the row instantly in "Failed Only"

When "Failed Only" is active and a scenario is validated (checkbox checked),
its row now fades out and is removed immediately (no refresh). **Key gotcha**:
there are **two** `updateScenarioValidation` definitions — one inline in
details.php and one in **`app.js`** (which calls `updateResultDisplay`).
Because app.js loads with `defer`, **app.js wins**. So the row-hiding logic
had to go into `updateResultDisplay` in **app.js**, not into details.php's
inline version. The masking reads either `LOGG_ONLY_FAILED` (a JS const set
from PHP `$onlyFailed`) OR the `#onlyFailedBtn` radio state, so it works
whether the filter came from the URL or from localStorage.

**General reminder** (extends §9): `app.js` loads last (`defer`) and its
function definitions OVERRIDE the inline ones in details.php. Fixes to shared
functions (`updateScenarioValidation`, `updateResultDisplay`, `updateCounters`,
`updateTestSetStats`) must go in **app.js**, not the inline `<script>`.

### 12.9 dev/prod folder switch: logg vs logdev (LOGG_BASE_URL)

A "dev" branch is served from **`logdev/public`** instead of `logg/public`.
To avoid hardcoding the folder in absolute URLs, add to `config/config.php`:

```php
if (!defined('LOGG_BASE_URL')) {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host   = $_SERVER['HTTP_HOST'] ?? 'sqs-sel-cent1.cas-software.dev';
    $script = $_SERVER['SCRIPT_NAME'] ?? '/logg/public/index.php';
    $folder = (strpos($script, '/logdev/') !== false) ? 'logdev' : 'logg';
    define('LOGG_FOLDER', $folder);
    define('LOGG_BASE_URL', $scheme . '://' . $host . '/' . $folder . '/public');
}
```

Then replace hardcoded `https://.../logg/public` with `LOGG_BASE_URL` (26
occurrences across index/details/rerun/dash/vm_config; comments in
clear_cache/stats can stay). In `echo "..."` use `. LOGG_BASE_URL .`; in HTML
use `<?php echo LOGG_BASE_URL; ?>`. **Leave `$basePath =
dirname($_SERVER['PHP_SELF'])` in rerun.php as-is** — it's already
folder-adaptive. **⚠️ Jenkins/Java side is separate**: the dev pipeline must
point its post.php/check.php calls to `logdev/public` itself — `LOGG_BASE_URL`
only affects in-site navigation, not what Jenkins calls.

### 12.10 French → English comment translation

All code comments across the project were translated FR → EN (per §9). Only
comments changed; functional code and **user-facing / JSON message strings**
(e.g. flaky.php JSON responses, app.js alerts) were intentionally left as-is.
`.min.css` / `.min.js` untouched (regenerate from source after edits).

### 12.11 Open items from this session

- [ ] **"Running" state not updating in batch** — root-caused via DryRun: the
      old rerun.php sent `autoid=` empty to check.php. Fixed (§12.4) by adding
      `AutoIDList` + per-scenario `$runnOne`. DryRun confirmed correct
      `autoid=NNNN` per scenario. **To verify without DryRun**: run a real
      "Run All Failed" and confirm the scenarios show "Running" in details.
      (Possible secondary risk: 14 sequential cURL calls for a big batch may
      hit PHP `max_execution_time` — if only the first few get "Running",
      decouple the check.php calls (fast) from the Jenkins calls, or mark
      running via direct SQL first.)
- [ ] **`@nightly` run logs too many tests** — DryRun proved rerun.php sends
      the CORRECT `TestName=@dummy&Testset=@nightly` to Jenkins. So the
      over-logging is in the **Jenkins pipeline** (`webTests.groovy`
      `Setup Runner` stage): the `switch(testset)` case `@nightly` does
      `replaceAll("and not @nightly","and @nightly")` on `JRunner.java`. Likely
      the source text of JRunner.java changed and the replaceAll no longer
      matches (silent no-op → filter not applied). Next step: rerun with
      `DetailedLog=true`, read the printed `########## JRUNNER ###########`
      block, check whether the final tag expression contains `and @nightly`.
- [ ] Consider unifying the duplicated JS (details.php inline vs app.js) — see
      §12.8. Fragile: every change must be made in both places.
