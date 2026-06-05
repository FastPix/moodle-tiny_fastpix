# Changelog

All notable changes to **tiny_fastpix** (FastPix video for TinyMCE) are documented
here. This project follows [Semantic Versioning](https://semver.org/).

## 1.0.0 — 2026-06-05

First stable release.

### Added
- **"Insert FastPix video"** TinyMCE toolbar button (and **Insert** menu item),
  shown only to users who hold `mod/fastpix:uploadmedia`.
- **Picker modal** listing the author's own videos. Server-side filtered to
  **ready**, **embeddable** (has a playback id) and **non-DRM** assets, and
  **owner-scoped** to the current user.
- **Whole-library search** by activity name and asset title (debounced,
  owner-scoped, capped).
- **Friendly display names** resolved from the FastPix activity, falling back to
  a localised "Untitled video" rather than a raw id.
- **Shortcode insertion** — inserts `{fastpix:pb_<id>}` at the cursor for
  `filter_fastpix` to render.
- **Privacy:** `null` provider — stores no personal data.
- Hard dependency on `filter_fastpix` (renderer), plus `mod_fastpix` and
  `local_fastpix`.

### Known limitations
- **TinyMCE only** — the button is not added to Atto or other editors.
- **No upload from the picker** — videos are uploaded via a `mod_fastpix`
  activity and appear here once ready. (Upload-from-picker is planned.)
- A video only appears for the teacher whose user id is recorded as its owner.
