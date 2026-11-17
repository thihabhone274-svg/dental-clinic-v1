# V2 first increment — registration and shared form controls

Date: 16 November 2026. Separate development copy; not the complete V2 clinic workflow.

## Implemented
- Region codes 1–14; dependent NRC township selection, English/Myanmar name search, card type and six-digit serial; live preview.
- Server checks region/township membership, type and serial. Myanmar serial/phone digits normalize to ASCII, retaining leading zeros.
- No default NRC region or card type. Explicit Not available mode permits registration without an NRC.
- Manual entry for unlisted/older formats. This is not government identity verification.
- Edit defaults to Keep existing NRC; original text is read from the database, never a browser hidden field. No existing data rewrite.
- Gender dropdown with existing nonstandard values retained when editing.
- Common nationality dropdown plus Other/free entry; no default nationality and no ethnic-group assumptions.
- Telephone keyboard, date maximums, grouped sections, focus indicators, responsive NRC layout and error/hint associations on shared text/select/textarea controls.
- Fixed duplicate-patient confirmation outside the form by associating the checkbox with the form ID.
- Existing clinical narrative fields, occupation and address remain free text. Existing medical history radios and brushing-frequency dropdown remain.

## Storage and scope
The NRC picker composes the existing nrc_number column. No schema migration is needed for this increment. Separate structured database columns remain future work. Setup SQL targets dental_clinic_v2_demo; the source V1 archive is untouched. A separate session cookie prevents V1/V2 sign-in collisions.

Appointments still use V1 duration/conflict behavior. The waiting queue, Quick/Standard visits, billing, full UI redesign and more extensive medical form revisions are NOT implemented in this increment. These require the next workflow changes, not merely changing a text field to a select.

## Reference provenance
Retrieved 16 November 2026:
- https://github.com/chuuhtetnaing/myanmar-nrc-format-dataset/blob/main/nrc.json
- https://github.com/chuuhtetnaing/myanmar-nrc-format-dataset/blob/main/README.md
The author permits personal, commercial and educational use and reports sourcing from KBZ MPU. This is a community-maintained reference, not an independently verified official complete registry.
- https://github.com/wai-lin/mm-nrc/blob/main/src/assets/NRC_Data.json
- https://github.com/wai-lin/mm-nrc/blob/main/README.md
Used to cross-check card-type labels and NRC-specific handling; its README warns that Nay Pyi Taw's administrative 9* should not become an NRC prefix, and that some township codes have multiple place labels.

The primary list contains 426 rows, merged into 423 distinct region/code choices. Multiple place captions for a shared code are preserved. All prefixes remain 1–14. Example: 14/PATANA, ပတန, Pantanaw. Source labels/transliterations can differ from a particular card; manual mode exists for that reason. Reference values are bundled in assets/data/nrc.json; no external service receives patient data.

## Acceptance checklist on Windows/XAMPP
- Create with 14 / PATANA / N / 001234; verify saved and editable value.
- Try Myanmar digits; ensure leading zeros remain.
- Change region after selecting township; old selection must clear.
- Submit a mismatched region/code directly; reject it server-side.
- Register a child using Not available.
- Edit an old free-text NRC with Keep selected; value must not change.
- Trigger a duplicate warning; tick confirmation; registration should proceed.
- Retry a validation error; input values and readable field errors should remain.
- Check narrow mobile width and keyboard-only operation.
- Confirm other V1 forms still save through the shared field helpers.

## Validation performed for this delivery
- PHP 8.4.24 isolated CLI: 60 PHP files linted, zero syntax errors.
- 443 focused checks passed, including every bundled region/code choice, invalid combinations, Myanmar digits, leading zeros, optional/manual NRC, server-owned legacy preservation, gender, nationality and future birth dates.
- JavaScript syntax check passed.
- Browser automation was attempted but the environment prevented Chromium from starting (socket permission error); no visual/browser pass is claimed.
- Full database/HTTP regression tests and Windows PHP 8.2/XAMPP checks have NOT been run. The previously reported V1 results are not results for this build.
