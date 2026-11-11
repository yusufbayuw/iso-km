# Role, Permission, and Safe Portal Access Bootstrap

## Operational decision

The ISO-KM permission seeder creates role/permission templates but **does not create
users or automatically give a privileged administrator role to any user**.
Filament Shield remains responsible for role administration.

- `super_admin`: all app permissions, including Shield role CRUD and the two
  explicit global Guided Assessment permissions.
- `iso_manager`: broad ISO operations without Shield role administration or
  automatic cross-system Guided Assessment scope overrides.
- `iso_coordinator` (**new installations**): operational Guided Assessment
  coordination, templates within authorized management systems, reporting,
  consolidations and read access to related ISO registers. This role does
  **not** automatically approve its own evidence, risk or management decision
  and cannot delete standards or administer roles.
- Stage-specific optional presets: `guided_respondent`,
  `guided_consolidator`, `guided_evidence_reviewer`, `guided_assessor`,
  `guided_risk_owner`, `guided_management_approver`.

A **role** grants a capability; Guided Assessment also requires explicit
**membership in each assessment** (`coordinator`, `respondent`,
`consolidator`, `reviewer`, `assessor`, `risk_owner`, `management`).
Having the assessor role alone does not give authority over every assessment.

## Existing installations: non-destructive seeding

Running the seeder normally:

```bash
php artisan db:seed --class=IsoRolePermissionSeeder --force
```

- Creates missing permissions and role templates.
- Preserves all existing role permissions, including custom ones; there is
  **no sync/revocation** for pre-existing roles.
- Adds `portal.access` and Guided permissions applicable to each pre-existing
  template.
- Does **not** automatically fill every other missing legacy permission or
  revoke previously broad permissions.
- On the **first ever creation** of `portal.access`, grants this panel
  entry permission directly to the users already in the database. Before the
  permission existed, the previous `User::canAccessPanel` behavior allowed
  those accounts to access the panel. New users must still be explicitly
  assigned access.
- If `portal.access` already existed from an earlier deployment, users are
  **not** automatically backfilled: doing so could grant access deliberately
  removed by an administrator.

**If existing users have already lost panel access after a prior seeding:**
review who should be able to sign in, then grant `portal.access` using the
Shield role/user UI or use this explicitly controlled one-time setting:

```dotenv
ISO_KM_ROLE_SEEDER_BACKFILL_EXISTING_USERS=true
```

Set this only if you deliberately want to restore the **old all-existing-users
access behavior**. Running the seeder with this flag grants portal entry to
every user currently in the database; review the account list first. Return it
to `false` afterward.

**To grant the full defined baseline of an existing role without removing
custom permissions**, optionally enable:

```dotenv
ISO_KM_ROLE_SEEDER_RECONCILE_EXISTING=true
```

This is additive and may grant capabilities users do not currently have.
Review role permissions before enabling it, and return it to `false` once
finished. Neither option automatically revokes permission.

**Legacy iso_coordinator:** If the role was created by the old seeder, it may
retain unusually broad privileges such as `delete_standard`. The new
least-privilege template applies to newly created roles only. A production
administrator must audit and manually remove unneeded legacy grants in
Filament Shield. This avoids silently removing needed existing access.

## Suggested safe procedure

1. Create a database backup and record the current users, role assignments,
   and permissions before changing anything.
2. Ensure there is at least one known administrator able to sign into the panel.
3. Keep `ISO_KM_ROLE_SEEDER_RECONCILE_EXISTING=false` and
   `ISO_KM_ROLE_SEEDER_BACKFILL_EXISTING_USERS=false` for normal updates.
4. Run the seeder and test a representative account for each operational role.
5. Review `iso_coordinator` legacy grants, administrator role CRUD, and
   memberships on sample Guided Assessment runs.
6. Only after approval, apply any explicit permission reconciliation or
   targeted removal of legacy privileges.

This seeder does not fix Nginx 404 errors. The Nginx virtual host needs a
correct `public/` document root and Laravel rewrite independently.
