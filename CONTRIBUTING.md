# Contributing to IT-Tools

Thank you for your interest in contributing!

## Adding a New Module

Each module lives in `modules/{name}/` and consists of:

- **`module.php`** — returns a PHP array with handlers
- **`runner.php`** — optional, generates a static HTML bookmarklet page

### module.php structure

```php
<?php
return [
    'name'        => 'mymodule',        // unique identifier
    'label'       => 'My Module',       // shown in admin sidebar
    'version'     => '1.0.0',
    'section'     => 'mymodule',        // settings key in DB
    'output_file' => 'my-tool.html',    // generated bookmarklet page

    // Load config with defaults
    'get_config' => function(): array {
        return array_replace_recursive([
            'mySetting' => 'default',
        ], abschnitt_lesen('mymodule') ?: []);
    },

    // Validate and save config
    'save_config' => function(array $data): void {
        abschnitt_speichern('mymodule', $data);
    },

    // Generate the bookmarklet HTML file
    'generate' => function(): void {
        require_once __DIR__ . '/runner.php';
        ausgabe_schreiben('my-tool.html', my_runner_html());
    },

    // Custom API action: POST /api/mymodule/action
    'action' => function(): void {
        $body = json_eingabe();
        // use SnipeIT:: library for all SnipeIT calls
        $asset = SnipeIT::getAsset((int)($body['id'] ?? 0));
        json_ok($asset);
    },
];
```

### API routes

Add routes to `api.php` in the match expression:

```php
$methode === 'POST' && $ressource === 'mymodule' && ($segmente[1] ?? '') === 'action'
    => modul_aufrufen($MODULE, 'mymodule', 'action'),
```

### SnipeIT Library

Always use `SnipeIT::` for SnipeIT API calls. Never call `file_get_contents` directly.

```php
// Read
SnipeIT::getAsset(42)
SnipeIT::getUser(1595)
SnipeIT::getAll('hardware', ['category_id' => 4])

// Write
SnipeIT::createAsset([...])
SnipeIT::updateAsset(42, [...])

// Upload
SnipeIT::uploadToUser(1595, $pdfBytes, 'file.pdf')
SnipeIT::uploadToAsset(42,  $pdfBytes, 'file.pdf')
```

### API response standard

```
/api/proxy/*  →  proxy_pass()  →  raw SnipeIT response (no wrapping)
/api/*        →  json_ok()     →  {"ok": true, "data": {...}}
              →  json_fehler() →  {"ok": false, "error": "..."}
```

## Pull Request Guidelines

- One feature or fix per PR
- Test with a real SnipeIT instance if possible
- Update `CHANGELOG.md` under `## Unreleased`
- Keep German UI strings in generated HTML (end users are German-speaking) — code comments and docs in English

## Reporting Issues

Use the GitHub issue templates. Include:
- IT-Tools version (`cat VERSION`)
- Browser console errors (F12)
- Server log (`docker logs it-tools --tail 30`)

---
*Authored by Chris M.*
