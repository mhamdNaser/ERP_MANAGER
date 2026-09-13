# CND Manager — i18n conversion convention

Goal: every user-visible **static** string must come from the translation
system, so the app is fully bilingual (Arabic default, English via the toggle).

## How translation works

- `src/i18n/translations.js` exports `{ ar: {...}, en: {...} }` — flat key → string.
- Components read strings via the hook:
  ```jsx
  import { useLanguage } from "../../Provider/LanguageContext"; // adjust depth
  const { t } = useLanguage();
  ...
  <button>{t("save")}</button>
  ```
- Interpolation uses `:name` placeholders: `t("greeting", { name })` where the
  value is `"صباح الخير، :name"` / `"Good morning, :name"`.

## Your job (per assigned file)

Replace every hardcoded Arabic UI string — visible text, `placeholder`,
`title`, `aria-label`, `alt`, toast/confirm messages — with `t("key")`.

Do NOT translate: `data-guide` values, CSS classes, enum/DB values sent to the
API, console logs, import paths.

## Key naming

- Namespace every NEW key with your assigned prefix, e.g. `drive_`, `task_`,
  `formal_`, `guide_`, `dash_`, `common2_`. This guarantees no collisions
  between agents.
- REUSE these existing shared keys when the meaning matches exactly (do not
  re-create them): `save, cancel, delete, edit, close, search, loading,
  refresh, submit, reset, results, yes, no`. If unsure whether an existing key
  fits, make a new namespaced one — duplicates are safer than wrong reuse.

## Where new keys go — YOUR OWN part file (no shared-file edits)

Create ONE file `src/i18n/parts/<yourDomain>.js` shaped exactly like this:

```js
// Translations for the <domain> screens.
export default {
  ar: {
    drive_uploadFiles: "رفع الملفات",
    drive_newFolder: "مجلد جديد",
    // ...every new key you introduced, Arabic value = the original literal
  },
  en: {
    drive_uploadFiles: "Upload files",
    drive_newFolder: "New folder",
    // ...same keys, natural English
  },
};
```

Rules for the part file:
- The Arabic value MUST be the exact original literal you replaced.
- Provide a correct, natural English translation for every key (not a
  transliteration). This is a professional admin app — keep English concise.
- `ar` and `en` MUST contain the identical set of keys.
- Do NOT edit `src/i18n/translations.js` — the parent will merge your part.

## Data-mapping files (taskMeta.jsx, formalUtils.js, driveUtils.jsx, taskUtils.js)

These export module-level constants mapping enum keys → Arabic labels. They run
outside React, so they cannot call `t()` directly. Convert them to **return
translation keys**, and translate at the usage site. Example:

```js
// before
export const priorityLabels = { low: "منخفضة", high: "عالية" };
// after
export const priorityLabelKeys = { low: "task_prioLow", high: "task_prioHigh" };
```
Then at the call site: `t(priorityLabelKeys[priority])`. Add the keys to your
part file. Update every consumer of the old constant. If a consumer is in a
file owned by another agent, note it in your report instead of editing it.

## Finish

Run `npx vite build` in FrontEnd; it must succeed (your part file isn't wired
in yet, so build success only proves your component edits are syntactically
valid — that's fine). Report: files changed, your part-file path, the count of
keys you added, and any cross-file consumer you could not update.
