# Changelog

All notable changes to **tiny_fastpix** (FastPix video for TinyMCE) are documented
here. This project follows [Semantic Versioning](https://semver.org/).

## Unreleased

### Added
- **Search box in the picker.** The modal now has a search field that filters the
  listed videos by title as you type. It is a client-side filter over the
  already-loaded, course-scoped list — no extra web service call.
- **Usage events.** The picker now records two Moodle events for the site log:
  `\tiny_fastpix\event\picker_opened` (fired when the picker loads its list) and
  `\tiny_fastpix\event\shortcode_inserted` (fired when an author inserts a
  shortcode, via the new `tiny_fastpix_log_shortcode_inserted` web service).
- **`.gitattributes`** to keep CI/dev scaffolding out of the released package.

## 1.1.0 — 2026-06-10

### Changed
- **Course-scoped picker.** The picker now lists only the signed-in user's own
  videos that belong to the **current course** (referenced by a FastPix activity
  in it) — previously it listed the user's videos across all courses. The listing
  stays owner-scoped, ready-only and embeddable-only (public, non-DRM).
- **Course-context capability gating.** `mod/fastpix:uploadmedia` is now resolved
  at the editor's **course context**, both for showing the toolbar button
  (`plugininfo`) and in the `get_my_videos` web service. Students (no capability)
  get no button and a rejected service call; they can still **watch** embeds via
  `filter_fastpix` (`mod/fastpix:view`).

### Removed
- **Whole-library search.** With the list scoped to a single course it is no
  longer needed; the `query` parameter and the modal search box are gone.

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
