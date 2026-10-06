# Changelog

All notable changes to this project are documented here.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and this project adheres to
[Semantic Versioning](https://semver.org/).

## [Unreleased]

## [1.2.1] - 2026-10-06

### Fixed

- **Every published view (`login`, `register`, `forgot-password`, `reset-password`, `two-factor-challenge`,
  `verify-email`) failed with `Twig\Error\LoaderError: Unable to find template "layouts/auth.html.twig"`** —
  introduced in 1.2.0's shared-layout change. `AuthModule::boot()` registers the module's own `Views/` directory
  under the `@auth` Twig namespace (`registerViewNamespace('auth', ...)`), so `Views/layouts/auth.html.twig`
  only resolves as `@auth/layouts/auth.html.twig` — a bare `"layouts/auth.html.twig"` resolves against the
  *default* namespace instead, which maps to the app's own `resources/views` (where `layouts/app.html.twig`,
  the skeleton's base layout, actually lives). Every `{% extends "layouts/auth.html.twig" %}` is now
  `{% extends "@auth/layouts/auth.html.twig" %}`. An app that ran `warden:install` on 1.2.0 needs the same
  one-line fix applied by hand to its already-published `modules/Auth/Views/*.html.twig` (the package never
  re-touches a published file) — the extends line is the first line of each.

## [1.2.0] - 2026-10-05

### Changed

- **Every published view now shares one layout, `Views/layouts/auth.html.twig`** — a brand mark, a soft
  background glow, and a real elevated Card panel (border, rounded corners, shadow) wrapping the form, instead
  of bare text floating directly on the page background. Each view only fills in a
  `card_title`/`card_description`/`card_content`/`card_footer` block, so restyling the shared chrome is a
  one-file edit instead of six. `warden:install` now publishes this layout alongside the existing views.
- **Published views rewritten to use `marrow/ui`'s HTML-like `<mui-x>` tags** (`<mui-card>`, `<mui-form
  :form="form">`, `<mui-field>`, `<mui-button>`, ...) instead of `{{ component(...) }}` calls — purely a
  notation change, no behavioral difference. Still only requires `marrow/ui` ^1.0 — the Card family's `class`
  prop used throughout the new layout already existed at that version.

Only affects `warden:install` run from this version onward — an app that already scaffolded `modules/Auth/`
from an earlier release is untouched; the package never reaches into that directory again.

## [1.1.0]

First release with the marrow/form-builder bridge (declarative `Forms/*.php` published alongside each
controller) — see the main README for the full feature set at this point; not individually detailed here.
