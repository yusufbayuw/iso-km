# Guided Assessment (white-label foundation)

Status: first implementation slice; not yet production-ready. The initial workflow and
test-suite live on the feature branch. Do not assume certification or complete ISO
coverage from the presence of an assessment record.

## Purpose and architecture

Guided Assessment adds a structured interview front end to the existing ISO-KM
Standard, Requirement, Control, Evidence, Risk, SoA, and Assurance modules.
It is deliberately **organization-neutral**: standard masters and questions are not
hard-coded to any school, foundation, company, or uploaded example workbook.

A deployment can set these environment variables:

- `ISO_KM_BRAND_NAME` (falls back to `APP_NAME`);
- `ISO_KM_BRAND_LOGO` (logo asset URL);
- `ISO_KM_PRIMARY_COLOR` (hex, e.g. `#2563eb`).

These are **per-deployment** settings, not a claim that this application already has
hard-isolated multi-tenant branding or data segregation. Management-system
membership controls Guided Assessment data access. A multi-tenant hosting
architecture would require its own tenancy and isolation review.

## Human-led workflow

1. The bank editor creates a versioned bank, questions, choices, conditional
   branches, and mapped assessment criteria for a specific licensed standard
   master. Bank publication checks coverage against **currently loaded**
   requirements and controls. Missing items must be fixed before publication.
2. The coordinator creates an assessment run tied to a matching management
   system and published bank, then adds role memberships.
3. Respondents receive initial questions; a structured answer can generate
   conditional follow-up assignments. A respondent can submit multiple entries
   for the same question and context. Responses are append-only, with optional
   supersession pointers, so originals remain available.
4. An authorized consolidator selects source answer IDs and records a selected
   answer, merge, or documented summary. A new consolidation has a new version
   and supersedes the prior current consolidation **without deleting source**.
5. A proposed criterion-level decision includes status, applicability, risk score,
   risk owner where required, and rationale. Evidence is verified in the existing
   lifecycle, then accepted or rejected **for that criterion** separately.
6. The assessor approves, followed by an assigned risk owner for high risk and
   management for critical risk. Sensitive/critical N/A also escalates to
   management. A respondent/consolidator cannot approve their own submitted
   source.
7. Publication to the existing Requirement Assessment or SoA is explicit and
   checks the source record's status and timestamp to prevent overwriting
   later manual edits. Final locking requires complete assignments and approved,
   published decisions for all mapped criteria.

Approval of a risk does **not** convert an unimplemented control into an
implemented control. Verified evidence is not automatically sufficient for all
criteria to which it might be mapped.

## Filament navigation

- **Periode Asesmen:** create run, add members, assign initial/manual questions,
  export template, lock final state.
- **Wawancara Saya:** answer assigned questions as many times as needed.
- **Konsolidasi Jawaban:** select one or more original answers and explain the
  conclusion.
- **Hasil Konsolidasi:** propose criterion-level assessments.
- **Review & Persetujuan:** evidence acceptance, staged approval, publishing.
- **Bank Pertanyaan / Pertanyaan & Kriteria:** edit a draft and validate coverage.
- **Template XLSX:** upload and map a workbook's existing cells.

Roles are permissions plus **membership in a specific assessment run**.
Giving someone a global role does not automatically authorize them to approve
every run. The role seeder now preserves customized permissions on existing roles and only
adds new Guided Assessment permission grants. New roles receive the default role
template. Always review role membership and the separate
`guided.evidence_pack.export_sensitive` authorization before rollout.

## Example XLSX field mapping

An organization uploads its own XLSX and specifies cell mappings. Example:

| Sheet | Cell | Source | Code | Field |
|---|---|---|---|---|
| Control Status | E2 | control | A.8.13 | status |
| Control Status | G2 | control | A.8.13 | evidence_refs |
| Cover | B2 | assessment | — | name |

Supported sources: `assessment`, `requirement`, and `control`.
Supported data fields in the UI include display/raw status, rationale,
consolidation summary, risk score, evidence references, approval date, and
applicability. The exporter copies the uploaded XLSX ZIP package and replaces
**only configured input cells** using literal values (including creation of a
missing input cell in an existing row). Formula cells cannot be overwritten.
It marks Excel formulas for recalculation when the file is opened.
All other members, including charts and original workbook parts, are left
in place. Mapping into an Excel formula cell is rejected. A final export requires
a locked run; a draft export leaves missing decisions blank.

The uploaded example workbook is **not** installed as the default template
and its organization labels, worksheet names and fixed row counts are not
hard-coded into ISO-KM. Complete coverage must be configured against a full
standard catalog and an appropriate bank, not inferred from an example sheet.

## Known limitations / unfinished acceptance criteria

- Standards are licensed material: the repository seed currently initializes
  generic ISO 27001 section groups rather than a complete authoritative
  2022 requirement/control catalog or 93 editorially prepared questions.
  **Do not claim 100% ISO 27001 coverage until the organization loads and
  validates its licensed standard catalog.**
- Template administration offers a **header/code-based mapping suggestion** and
  a manual cell-mapping editor. It is not a universal workbook parser; formulas,
  nonstandard table layouts, and dynamic row expansion still require UAT.
  Final GAP Analysis exports require both status and notes/evidence mappings
  for all bank criteria. Generic custom reports may export subsets.
- **Import catalog XLSX** in Standard administration (with a source-license
  declaration). ISO 27001-specific code normalization handles legacy `5.1k`
  clause and `5.1` Annex control labels, while non-ISO standards retain
  their own code syntax. The import never initializes an example organization's
  data, requires privileges, and does not overwrite existing catalog entries.
  Published or actively assessed catalog editions cannot be mutated.
- **Evidence Pack ZIP** contains a manifest, per-evidence SHA-256 and copies
  of currently accepted active/verified evidence. Confidential/restricted
  (and unknown-classification) evidence is excluded by default, with a separate
  sensitive-export permission. Export records and checksums are retained.
- Multiple answers across different objects can be included in one human
  consolidation using the cross-context option; later answers invalidate prior
  active consolidations without discarding the raw evidence.
- Risk approval strength is at least the score of linked open Risk Register
  entries; decisions must be re-reviewed if their linked risk subsequently rises.
- Asset registers, questionnaire-to-risk generation, recurring assessments,
  advanced conflict-resolution UX, per-role dashboard polish, and full
  multi-tenant infrastructure need follow-up work.
- Role membership and evidence security need penetration/UAT testing in the
  actual hosting environment. CI passing is necessary, not sufficient.
- The sample workbook may use Excel extensions that require manual Excel-based
  acceptance testing beyond the synthetic package preservation test.

## Release guardrails

- Additive migrations only; no existing ISO-KM table dropped or rewritten.
- No automatic merge or deployment while the PR is draft.
- Run `php artisan migrate`, then seed reviewed permissions and load the
  intended licensed standard catalog/question bank.
- Run `php artisan test`; verify existing document, evidence, risk, supplier,
  audit, and assurance regressions.
- Perform end-to-end browser review for all roles, verify permissions server-side,
  and open exported files in Excel to confirm chart/validation fidelity.
