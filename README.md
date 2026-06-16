# FastPix video picker for TinyMCE (tiny_fastpix)

Pick one of your FastPix videos from inside the editor and drop it into the page —
no playback ids, no hand-typed short codes.

`tiny_fastpix` is the **authoring** half of the FastPix suite. Where
`filter_fastpix` turns a short code into a player for *readers*, this plugin gives
*authors* a button that finds the right video and writes the short code for them.
It uploads nothing and plays nothing itself; it only helps you reference a video
you already have.

---

## The 30-second tour

1. A teacher opens any TinyMCE editor — a forum post, a Page, a Book chapter, a
   label.
2. They click **Insert FastPix video** (or **Insert → FastPix video**).
3. A dialog shows *their* videos for **this course**.
4. They click one. `{fastpix:pb_<id>}` appears at the cursor.

When someone later reads that content, `filter_fastpix` swaps the short code for a
player. The author never has to know the playback id existed.

## What lands in the editor

The plugin inserts a single short code wherever the cursor is:

```
{fastpix:pb_96df2713-bd26-4815-8b8d-8ab7674aa751}
```

You can type around it, put several on a page, or delete it like any other text.
The matching `pb_<id>` always points at the exact video the author chose.

## What shows up in the picker

The list is deliberately narrow, so an author can only insert a video that will
actually play:

| Rule | Why |
| --- | --- |
| **Your videos only** | The dialog is scoped to the signed-in user — you never see another teacher's library. |
| **This course only** | Only videos uploaded through a FastPix activity *in the current course* are listed, so a course's editors see just that course's media. |
| **Ready only** | Videos still uploading or processing are hidden until FastPix finishes them. |
| **Playable only** | DRM-protected videos are left out — `filter_fastpix` can't embed them, so offering them would only insert a dead short code. |
| **Named, not numbered** | Each result shows the video's activity name (falling back to *Untitled video*), not a raw UUID. |

## Setting it up

**You need the rest of the FastPix suite first.** This is a picker, not a
standalone tool:

| Plugin | Role for the picker | Minimum |
| --- | --- | --- |
| `local_fastpix` | Stores the videos and answers "what does this user own?" | 1.0.0 |
| `mod_fastpix` | Defines `mod/fastpix:uploadmedia`, the permission that gates the button | 1.0.0 |
| `filter_fastpix` | Renders the short code the picker inserts — **must be enabled** | 1.0.0 |

Also: Moodle 4.5 LTS or newer with the TinyMCE editor, and PHP 8.1+ (tested to
8.3). The picker itself adds no tables, holds no FastPix credentials, and pulls in
no Composer packages.

**FastPix account & credentials.** Using FastPix requires a FastPix account and a
FastPix **API Key** (created in the FastPix Dashboard under *Settings → API Keys*;
see [Activate your account](https://fastpix.com/docs/getting-started/activate-your-account)).
This picker never asks for or stores the key — it is configured **once** in
`local_fastpix` under *Site administration → Plugins → Local plugins → FastPix*,
and every plugin in the suite (including this one) reads through `local_fastpix`
from there. If listings aren't working, check the credentials in `local_fastpix`
first.

**Install it** the usual Moodle way — upload the ZIP under *Site administration →
Plugins → Install plugins*, or drop the folder at
`lib/editor/tiny/plugins/fastpix/` and finish the upgrade at *Notifications*.
Moodle will refuse the install until the three plugins above are present.

**After it's in**, there's nothing to switch on for the button — it appears in the
toolbar automatically for anyone holding `mod/fastpix:uploadmedia`. Do double-check
that the FastPix filter is turned **on** under *Site administration → Plugins →
Filters → Manage filters*, otherwise the videos authors insert will show up to
readers as plain `{fastpix:…}` text.

## Who can use it

The button and the web service behind it share one gate — there is no separate
capability to manage:

| Capability | Effect | Owned by |
| --- | --- | --- |
| `mod/fastpix:uploadmedia` | Checked at the **course context**: holders see the toolbar button and can list their own videos in that course; everyone else gets no button at all. | `mod_fastpix` |

## Good to know

- **TinyMCE only.** The button isn't added to Atto or other editors. That's an
  *authoring* limit — `filter_fastpix` still renders short codes whatever produced
  them, so content written elsewhere (or by hand) still plays.
- **No uploading here.** New videos come in through a `mod_fastpix` activity and
  surface in the picker once they're ready. An upload tab is on the roadmap.
- A video only appears for the teacher recorded as its owner, and only in the
  course it was uploaded to.

## Privacy

The picker keeps no personal data of its own. It ships a `null_provider` and
leaves all storage to `local_fastpix`. The detail lives in
`classes/privacy/provider.php`.

## Help and links

- Questions or bugs → the [issue tracker](https://github.com/FastPix/moodle-tiny_fastpix/issues).
- Step-by-step setup → the [TinyMCE picker guide](https://fastpix.com/docs/moodle/tiny-plugin).
- New to FastPix on Moodle? Start with the [foundation plugin](https://fastpix.com/docs/moodle/local-plugin).
- What changed and when → the [changelog](https://github.com/FastPix/moodle-tiny_fastpix/blob/main/CHANGELOG.md).

## License

© 2026 FastPix Inc. Distributed under the
[GNU GPL v3.0 or later](https://www.gnu.org/licenses/gpl-3.0.html); full text in
[`LICENSE`](https://github.com/FastPix/moodle-tiny_fastpix/blob/main/LICENSE).
