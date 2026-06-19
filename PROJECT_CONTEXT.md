# LOGG — Contexte Projet

> **But de ce fichier** : donner à Claude (ou tout dev) tout le contexte nécessaire pour reprendre le projet sans tout relire. À fournir en début de session.

---

## 1. Vue d'ensemble

**LOGG** est une application web PHP interne de gestion des résultats de tests automatisés (QA/testing). Elle affiche les exécutions de tests par produit/branche/environnement, permet de relancer des tests via Jenkins, et suit les déploiements VM.

- **Stack** : PHP, MySQL (base `testcomplete`), Bootstrap 5, PDO
- **Environnement** : XAMPP sur Windows
- **Chemin local** : `C:\xampp\htdocs\logg\`
- **URL locale** : `http://localhost/logg/public/`
- **URL prod** : `https://sqs-sel-cent1.cas-software.dev/logg/public/`
- **Repo GitHub** : `jidey/logseite` (public)
- **Raw GitHub** : `https://raw.githubusercontent.com/jidey/logseite/refs/heads/main/[path]`

---

## 2. Structure du projet

```
logg/
├── config/
│   └── config.php          # Connexion PDO ($pdo), base "testcomplete"
├── src/
│   └── TestLogRepository.php  # Classe d'accès aux données
├── inc/
│   └── db_connect.php      # (legacy mysqli, en cours d'abandon)
├── public/                 # ★ Racine web
│   ├── index.php           # Page principale (liste TestSets)
│   ├── details.php         # Détail scénarios d'un TestSet
│   ├── rerun.php           # Confirmation + relance test via Jenkins
│   ├── check.php           # Met à jour running/checked (JSON)
│   ├── post.php            # Enregistre résultats (appelé par Jenkins/TestComplete)
│   ├── dash.php            # Dashboard global (Bootstrap 4)
│   ├── vm_config.php       # Config déploiements VM nightly
│   ├── cache_management.php # Admin cache
│   ├── css/
│   │   ├── styles.css / styles.min.css  # Styles principaux
│   │   └── theme.css       # ★ Thème dark/light partagé
│   ├── js/
│   │   ├── app.js          # Tri tableau, notes, lazy load
│   │   └── theme.js        # ★ Toggle thème partagé
│   ├── deployedVM/         # Fichiers .txt des builds déployés
│   ├── builds/             # Fichiers .txt des derniers builds
│   └── nightly/            # save_column.php, load_states.php (checkboxes vm_config)
├── icons/                  # (anciennes images clock.png, running.jpg — plus utilisées)
└── doc/
```

---

## 3. Conventions critiques

### 3.1 Mapping testType → table MySQL

Le nom de table dépend du **testType** ET du **product** :

| Product | testType | Table |
|---------|----------|-------|
| gWWebSel | rc_x17 | x17_rc |
| gWWebSel | hf_x17 | x17_hf |
| gWWebSel | dev_x17 | x17_dev |
| weWebSel (SmartWe) | rc_x17 | we_rc |
| weWebSel (SmartWe) | hf_x17 | we_hf |
| weWebSel (SmartWe) | dev_x18 | we_dev |
| gWClient (Desktop) | hf_x17 | x17_gwhf |
| gWClient (Desktop) | rc_x17 | x17_gwrc |
| gWClient (Desktop) | dev_x16 | x16_gwdev |

> SmartWe utilise toujours `we_*` quelle que soit la version x.

### 3.2 Détection des produits

```php
$isSmartWe = strpos($product, 'weWebSel'|'weClient'|'smartWe'|'SmartWe') !== false;
$isGwDesktop = strpos($product, 'gWClient') !== false;
```

### 3.3 Normalisation SmartWe (dans index.php ET details.php)

Faite **tôt**, juste après lecture des params GET :
- `hf` → `hf_x17` (forcé)
- `rc` → `rc_x17` (forcé)
- `dev` → garde sa version (`dev_x18`)

Le sélecteur Branch SmartWe affiche des labels simplifiés (dev/rc/hf) via `$smartWeMapping` mais envoie le vrai testType.

### 3.4 Branches par produit (index.php)

- **gW Desktop** : liste forcée `['hf_x15','dev_x16','rc_x16','hf_x16','rc_x17','hf_x17']` (toujours affichées, même sans données)
- **gW Web** : exclut `dev_x15` (obsolète) ; ajoute `rc_x18`/`hf_x18` affichées entre parenthèses `(rc_x18)` (pas encore dispo, valeur réelle sans parenthèses)
- **SmartWe** : dev/rc/hf simplifiés

---

## 4. Jenkins / relance de tests (rerun.php → check.php)

**Point crucial** : Jenkins lit les paramètres dans la **query string (GET)**, pas en POST.
- Utiliser `CURLOPT_HTTPGET = true` (pas `CURLOPT_POST`)
- `sendGetRequest()` avec `CURLOPT_FOLLOWLOCATION`, timeout 15s
- Symptôme si POST : "les tests ne se lancent pas après confirmation" alors que coller l'URL dans le navigateur (= GET) fonctionne

**check.php** :
- Résout la table via `Testtype` + `Product` (gère SmartWe → we_rc)
- `value=2` ou `field=running` → met à jour la colonne `running`
- `field=running` force la mise à jour même si `value < 2` (utilisé pour reset à 0)
- `value < 2` (sans field=running) → met à jour `checked`
- Toujours répondre en JSON (`display_errors=0` pour ne pas corrompre le JSON)
- Nom de table validé par regex `/^[a-z0-9_]+$/i`

**URL check.php construite dynamiquement** (local, pas /logs/) :
```php
$protocol://$host . dirname($_SERVER['PHP_SELF']) . "/check.php?value=2&autoid=...&Testtype=...&Product=..."
```

**Mapping branches SmartWe pour Jenkins (rerun.php ConfirmAndRun)** :
- dev → `dev/14.x`, rc → `rc/13.x`, hf → `hotfix/13.x`

---

## 5. Système de thème (dark/light)

Fichiers partagés : `css/theme.css` + `js/theme.js`.
- Clé localStorage : **`logg-theme`** (`'dark'` / `'light'`)
- Synchronisé entre toutes les pages (index, dash, vm_config, cache_management)
- Script chargé **avant** le CSS pour éviter le flash
- Bouton `#themeToggle` flottant (position fixed top-right) sur les pages secondaires

**Règles spéciales theme.css** :
- Cellules colorées dash (`.tg-green/.tg-red/.tg-warn` + leurs `<a>`) → texte foncé `#1a1a1a` (sinon illisible sur fonds pâles)
- vm_config : `bg-success` → vert foncé `#2e7d32` texte blanc ; `bg-warning` → orange texte foncé ; `table-light` → fond `bg-tertiary`
- **Checkboxes** : agrandies 1.3em, bordure 2px grise (non cochée), vert vif (cochée) — sinon invisibles en dark

---

## 6. Cache des préférences (localStorage)

| Clé | Contenu |
|-----|---------|
| `logg-prefs` | JSON : {theme, text_size, error_only, product, testtype, test_browser, team_tag} |
| `logg-theme` | 'dark' / 'light' |
| `logg-error-only` | '0' / '1' |
| `logg-sort` | JSON : {column, order} |

**Principe** :
1. **Redirection précoce** (script `<head>`) : si l'URL n'a aucun filtre, lit `logg-prefs` et redirige UNE fois avec tous les params (Product/Testtype/TeamTag/ErrorOnly) → PHP charge directement les bonnes données (pas de flash)
2. **Sauvegarde sur chaque changement** : `savePreferences()` appelé AVANT `form.submit()` (synchrone, pas de setTimeout)
3. Tri sauvegardé dans `logg-sort`, réappliqué par `applySavedSort()` (sauf si = défaut date desc)

---

## 7. UI — état actuel

### index.php (page principale)
- Colonnes : TestSet | Tested version | Passed | Failed | Testcases | Log | Run | Duration | Date | Notes | Team
- **Boutons** (Bootstrap, plus d'images) :
  - Testcases : `Details` (btn-primary)
  - Log : `Allure` (btn-info) ou `N/A` (btn-secondary disabled)
  - Run : `Run` (btn-success) / `Running` (btn-warning + spinner, cliquable pour reset)
- **Filtres** : Product, Branch, Team, Search Testset, Errors Only (Browser caché = chrome)
- **Errors Only** : garde les tests Failed > 0 **ET** les tests running == 2
- Tri par date desc par défaut (PHP trie déjà ; `data-sort-value` = timestamp pour tri JS)
- Liens vers : "Release Dashboard" (dash.php), "Deployments" (vm_config.php), "Cache"

### details.php
- Colonne "Trigger" renommée **"Run"** (mêmes boutons que index)
- Log en bouton (`📋 Log` / `N/A`)
- Préserve Failed Only dans les deux sens :
  - index "Errors Only" → details `&OnlyFailed=1`
  - details "Back to TestSets" → index `&ErrorOnly=<0|1>`

### dash.php (Bootstrap 4)
- Cellules Failed cliquables → index.php avec bon Product/Testtype + `&ErrorOnly=1` (ajouté auto par `generateResultCell`)
- URLs au format moderne : `?Product=weWebSel&Testtype=dev_x18&TestBrowser=chrome` (PAS l'ancien `LogVersion=we_dev&Filter=yes`)
- Robustesse : `getBranchVersionWe()` gère réponse API vide/SSL fail (vérifie `file_get_contents !== false`, `isset(...[0])`, destructuration `?? ['','','']`)

### vm_config.php
- 4 onglets : Selenium / Release / smartWe / Testcomplete VMs
- Liens "Logs" → `logg/public/index.php` (PAS `/logs/`)
- Checkboxes Nightly Update (save via `nightly/save_column.php`, load via `nightly/load_states.php`)

---

## 8. post.php (enregistrement résultats Jenkins)

- Migré de mysqli (`inc/db_connect.php`) → **PDO** (`config/config.php`)
- Requêtes préparées (sécurité)
- `unquote()` retire les quotes simples englobantes que Jenkins envoie
- Logique préservée : décodage TCProj (Web/SmartWe urldecode, x10 spécial), gWClient sans Browser + mapping table, `Grid`→`Grid-x.7`, DELETE anciens runs (même TCProj+Build) avant INSERT, tag/teamtag défaut `-`, gWClient sans colonnes tag/teamtag
- ⚠️ **Vérifier côté Jenkins** : appel doit pointer vers `logg/public/post.php`

---

## 9. Habitudes de travail

- JD travaille sur des **fichiers complets** (pas des snippets partiels) — retourner le fichier entier modifié
- Conversations parfois en **français**
- Fichiers de sortie dans `/outputs/public/` (structure projet) et parfois mirror à la racine `/outputs/`
- Régénérer `styles.min.css` si `styles.css` change ; `app.min.js` si `app.js` change (vérifier quel fichier la page charge)
- ⚠️ **Sécurité** : un token GitHub a été partagé par accident dans une session passée — rester vigilant sur l'exposition de credentials dans le code/configs collés

---

## 10. TODO / points ouverts

- [ ] Vérifier que Jenkins/TestComplete appelle `post.php` au bon endroit (`logg/public/`)
- [ ] `generateAnalyseCell` (dash "To check") ignore actuellement l'URL — cellules non cliquables (activable si besoin)
- [ ] Confirmer si `app.min.js` / `styles.min.css` sont chargés (sinon régénérer après edits)
- [ ] rerun.php : vérifier que `ConfirmAndRun` reçoit bien `$Product` pour le mapping branches SmartWe

---

## 11. Pièges connus (à ne pas refaire)

1. **POST vs GET pour Jenkins** : toujours GET (voir §4)
2. **Déclaration JS dupliquée** : `const textSizeSlider` déclaré 2× → SyntaxError qui casse tout le script (ex: `resetScenarioRunning undefined`)
3. **array_intersect sur branches** : filtrer les branches par les données réelles cache celles sans données — pour gW Desktop, forcer la liste complète
4. **Texte blanc sur fond coloré pâle** en dark mode (cellules dash/vm_config) → forcer texte foncé
5. **echo en plein HTML** (ex: "Invalid JSON" dans dash) casse la mise en page → utiliser `error_log()` à la place
6. **setTimeout(savePreferences)** après submit : risque de navigation avant sauvegarde → sauvegarder synchrone AVANT submit
