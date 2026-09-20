# CLAUDE.md

Guidance for Claude Code (or any developer) working in this repository.

## Known non-version-controlled fix: `asset()` helper in vendor

`vendor/laravel/framework/src/Illuminate/Foundation/helpers.php` has been manually
patched. The stock `asset()` and a custom `asset_upload()` helper both used to
hardcode an extra `'public/'` prefix:

```php
// WRONG (previous state, broke every asset URL)
function asset($path, $secure = null)
{
    return app('url')->asset('public/' . $path, $secure);
}
```

This was a leftover hack from an old deployment where the web server's document
root pointed at the project root instead of `public/`. The current document root
(confirmed via cPanel: `/home/khadpxff/eyana2026-dev/public`) is correctly
`public/`, so the hardcoded prefix caused every `{{ asset(...) }}` URL in every
Blade view to 404/503 (e.g. `/public/assets/js/app.js` instead of
`/assets/js/app.js`), breaking all CSS/JS on every page.

**Fixed** by removing the hardcoded `'public/'` prefix from both `asset()` and
`asset_upload()`, restoring Laravel's stock behavior:

```php
function asset($path, $secure = null)
{
    return app('url')->asset($path, $secure);
}
```

### Why this matters going forward

`vendor/` is `.gitignore`d, so **this fix is not tracked in git and will be lost**
if:
- `composer install` or `composer update` is run fresh (reinstalls vendor/ from
  `composer.lock`, restoring the stock — now-correct — upstream `asset()`, so
  this specific issue won't recur from a clean install)
- `vendor/` is restored/reset from a backup or snapshot taken *before* this fix
  (2026-09-20) — **this would reintroduce the broken `/public/` prefix**

If asset URLs (CSS/JS/images) start 404ing again with an erroneous `/public/`
segment after a deployment, redeploy, or vendor restore, check this file first —
it likely means a stale/pre-fix copy of `vendor/laravel/framework` came back.
The correct permanent fix would be to stop hand-patching vendor and either
(a) confirm no code still depends on the old hack, or (b) move any legitimately
needed prefix logic into the application layer (a custom helper or a
`config('app.asset_url')` override) instead of editing framework source, so it
survives `composer install`.

There is no `asset_upload()` call anywhere in the current application code
(`app/`, `resources/views/`) — it's unused, but was patched for consistency
since it shared the same bug.
