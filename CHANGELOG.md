# Changelog

All notable changes to this project are documented here.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and this project adheres to
[Semantic Versioning](https://semver.org/).

## [Unreleased]

## [1.2.0] - 2026-10-05

### Changed

- **Every published view now shares one layout, `Views/layouts/auth.html.twig`** — a brand mark, a soft
  background glow, and a real elevated Card panel (border, rounded corners, shadow) wrapping the form, instead
  of bare text floating directly on the page background. Each view only fills in a
  `card_title`/`card_description`/`card_content`/`card_footer` block, so restyling the shared chrome is a
  one-file edit instead of six. `warden:install` now publishes this layout alongside the existing views.
- **Published views rewritten to use `marrow/ui`'s HTML-like `<mui-x>` tags** (`<mui-card>`, `<mui-form
  :form="form">`, `<mui-field>`, `<mui-button>`, ...) instead of `{{ component(...) }}` calls — purely a
  notation change, no behavioral difference; requires `marrow/ui` ^1.1 (the version that gave every component a
  `class`/`attrs` passthrough, used here for the card family's styling).

Only affects `warden:install` run from this version onward — an app that already scaffolded `modules/Auth/`
from an earlier release is untouched; the package never reaches into that directory again.

## [1.1.0]

First release with the marrow/form-builder bridge (declarative `Forms/*.php` published alongside each
controller) — see the main README for the full feature set at this point; not individually detailed here.
