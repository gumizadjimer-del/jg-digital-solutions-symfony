# AGENTS.md --- JNG Digital Solutions

## Project identity

-   **Project name:** JNG Digital Solutions (some existing snippets use
    JG Digital Solutions; verify before standardizing).
-   **Tagline:** Turning Ideas Into Digital Solutions.
-   **Project type:** School Web Programming project.
-   **System:** Web-Based Service Request and Management System.
-   **Stack:** PHP, MySQL, HTML, CSS, and JavaScript.
-   **Locale/currency context:** Philippines; Philippine Peso (PHP).

The website is presented as a small digital-solutions business website,
not a personal portfolio. It introduces the business and its services,
accepts contact messages, supports user registration and login, and
provides service-request management for users and administrators.

## Critical instructions for Codex

1.  Inspect the actual VS Code workspace, file tree, and relevant source
    files before making changes. This document describes the
    conversation's known context, not a guarantee that every proposed
    feature was implemented.
2.  Preserve the existing project. Do not replace whole files,
    restructure folders, rename routes, or rewrite working functionality
    unless explicitly requested.
3.  Make focused, incremental changes. The user prefers small code
    additions or replacements that can be reviewed and manually
    inserted.
4.  Always identify the exact file and section to edit. Explain whether
    code should be added, replaced, or removed.
5.  Prefer straightforward PHP, MySQLi prepared statements, HTML, and
    CSS that fit the existing codebase and the user's school-project
    level.
6.  Distinguish implemented features from proposed features. Verify
    source files and database schema before claiming a feature works.
7.  Preserve security controls: authentication, authorization, CSRF
    validation, prepared statements, output escaping, and ownership
    checks. Do not weaken or remove them.
8.  Do not run destructive SQL, delete user data, or perform broad
    migrations without explaining the impact and receiving approval.
9.  Test changes where possible: PHP syntax, file paths, SQL
    compatibility, and relevant user flows. Explain any
    browser/phpMyAdmin tests the user must perform.
10. Never expose passwords, database credentials, session tokens, or
    other secrets.
11. Keep the branding consistent with the existing project. Do not
    globally change JG/JNG naming without asking.

## User preferences

-   Give direct, step-by-step instructions.
-   Use simple explanations and avoid unnecessary abstractions.
-   Prefer manual, incremental edits over full-project rewrites.
-   Show the exact insertion location and the complete code block
    needed.
-   Keep existing naming conventions unless a change is necessary.
-   Prioritize functionality and security, then styling.
-   Use the existing brand palette:
    -   Orange: `#FAAF43`
    -   Charcoal: `#231F20`
    -   White: `#FFFFFF`
    -   Black: `#000000`
-   Design direction: simple, minimal, professional, business-oriented,
    with dark sections and orange accents.

## Known project structure

The latest VS Code tree shown in conversation was:

``` text
project-root/
├── admin/
│   ├── dashboard.php
│   ├── edit_user.php
│   ├── login.php
│   ├── logout.php
│   ├── messages.php
│   ├── register.php
│   └── users.php
├── config/
│   ├── admin_auth.php
│   ├── database.php
│   └── security.php
├── CSS/
│   └── style.css
├── image/
│   ├── 1x/
│   ├── Asset 1.png
│   └── Asset 2.png
├── JS/
│   └── script.js
├── .gitignore
├── index.php
└── process_contact.php
```

The user later mentioned creating a separate admin stylesheet. Verify
whether `admin/style.css` exists before using it; it was not visible in
the last shared tree. The current workspace path must also be verified.
An earlier local path was `C:\xampp\htdocs\my project\`, but do not
assume that is still the active folder.

## Technology and architecture

### Front end

-   HTML for page structure.
-   CSS for the public website, authentication pages, and admin pages.
-   JavaScript for client-side interactions and confirmations.
-   The public stylesheet was shown as `CSS/style.css`.
-   Inspect each page's `<link rel="stylesheet">` path before editing
    styles. Relative paths differ between root-level and `admin/` pages.

### Back end

-   PHP handles registration, login, logout, contact submissions, admin
    functions, and service requests.
-   MySQL stores user accounts, service requests, and contact messages.
-   The existing database connection uses MySQLi.
-   Use prepared statements for SQL statements containing user-supplied
    values.
-   Escape dynamic HTML output with `htmlspecialchars()`.

### Database connection

The database has been referred to as `jng_digital`; the known connection
file is `config/database.php`. It previously used MySQLi with local
settings such as `localhost`, user `root`, and an empty password. These
settings may have changed. Read the local file without exposing
credentials and do not overwrite the user's settings.

## Known database design

Verify the actual database schema, indexes, and foreign-key constraints
before changing anything.

### `users`

Known columns:

  Column         Purpose
  -------------- -----------------------------------------
  `id`           Primary key, auto-increment
  `username`     Username; intended to be unique
  `email`        Email address; intended to be unique
  `password`     Password hash, never plaintext
  `created_at`   Registration timestamp
  `role`         Account role, such as `user` or `admin`

### `service_requests`

Known columns:

  -----------------------------------------------------------------------
  Column                              Purpose
  ----------------------------------- -----------------------------------
  `id`                                Primary key, auto-increment

  `user_id`                           ID of the user who submitted the
                                      request

  `service`                           Requested service name

  `description`                       Request description

  `status`                            Request status

  `created_at`                        Submission timestamp

  `admin_note`                        Discussed as a possible field;
                                      verify whether it actually exists
  -----------------------------------------------------------------------

Intended relationship: **one user to many service requests**. `users.id`
is the parent key and `service_requests.user_id` identifies the owner of
each request. A `user_id` column alone does not prove that a
database-level foreign-key constraint exists. Inspect the schema and
existing data before proposing a safe foreign-key migration.

### `messages`

Known columns:

  Column         Purpose
  -------------- -----------------------------
  `id`           Primary key, auto-increment
  `name`         Sender name
  `email`        Sender email
  `message`      Contact message
  `created_at`   Submission timestamp

The current design treats contact messages as independent of registered
accounts because visitors can use Contact Us without logging in.

## Authentication and security

The following structure has been discussed; verify the current
implementation:

-   `admin/register.php` handles account registration.
-   Store passwords using `password_hash($password, PASSWORD_DEFAULT)`.
-   Verify passwords using `password_verify()`.
-   `admin/login.php` handles login and stores session values such as
    `user_id`, `username`, `email`, and `role`.
-   Regenerate the session ID after successful login using
    `session_regenerate_id(true)`.
-   `admin/logout.php` clears and destroys the session.
-   `config/security.php` has been discussed as the location for:
    -   `getCsrfToken()`
    -   `verifyCsrfToken($token)`
-   State-changing forms should submit and validate a CSRF token.
-   `config/admin_auth.php` has been discussed as the location for:
    -   `requireLogin()`
    -   `requireAdmin()`

Ensure `session_start()` runs before output and only once per request.
Admin-only pages must check the administrator role, not merely whether
someone is logged in. Never trust a user ID or role submitted by a form.

## Functionality and development status

The following features were discussed. Some may be implemented; others
were proposed in conversation. Inspect the files before deciding what
remains to be done.

### Public website

-   Business homepage.
-   Navigation to Home, Services, Our Work, Contact, and Login.
-   Services discussed:
    -   Web Development
    -   Software Development
    -   Graphic & Brand Design
    -   Data & Simulation
-   Contact Us form submits to `process_contact.php`.
-   Contact messages are stored in `messages`.

### Accounts

-   User registration.
-   User login and logout.
-   Role distinction between normal users and administrators.
-   Admin user listing, editing, and deletion.

### Intended user service-request flow

The user wants this flow:

``` text
Login
  |
  v
main_dashboard.php
  |-- View own service requests and statuses
  |
  `-- + New Service Request
          |
          v
      dashboard.php
      (service request form)
```

A separate `admin/main_dashboard.php` was proposed as the normal user's
home page. The existing `admin/dashboard.php` was intended to remain the
service-request form for regular users, despite the potentially
confusing filename.

Changing the normal user's login redirect to `main_dashboard.php` was
proposed. Verify current role-based redirects first. Administrators
should continue to reach the admin dashboard.

The main user dashboard should retrieve only the logged-in user's
requests using the session user ID and a prepared statement. Conceptual
query:

``` sql
SELECT id, service, description, status, created_at
FROM service_requests
WHERE user_id = ?
ORDER BY created_at DESC
```

### Request cancellation

A cancellation feature was proposed: - Show Cancel Request only when
status is `Pending`. - Submit cancellation using POST, not a GET link. -
Include and validate a CSRF token. - Change status to `Cancelled`; do
not delete the request. - Restrict the update by both request ID and the
logged-in user's ID. - Allow cancellation only when the current status
is `Pending`.

Conceptual SQL:

``` sql
UPDATE service_requests
SET status = 'Cancelled'
WHERE id = ?
  AND user_id = ?
  AND status = 'Pending'
```

Verify actual status values and existing code. A user must not be able
to cancel another user's request or a request already being processed.

### Admin service-request management

Discussed requirements: - Admin can view service requests. - Admin can
update request status. - Admin can add admin notes. - Admin can see
cancelled requests. - Admin-only access is required.

Verify whether the relevant pages, routes, fields, and forms exist
before implementing them.

## UI and styling

Brand palette: - `#FAAF43` --- orange accent/buttons - `#231F20` ---
charcoal backgrounds/header - `#FFFFFF` --- white - `#000000` --- black

Design direction: - Minimal, professional business website. - Dark
header/footer and charcoal sections. - Orange accent lines, buttons,
links, and highlights. - Header background should span the viewport
while logo/navigation content stays aligned within a centered
maximum-width inner container. - Authentication, user, and admin pages
should be styled consistently. - Avoid leaving admin pages with
browser-default styling.

Selectors discussed in earlier CSS guidance include `.admin-header`,
`.admin-logo`, `.admin-nav`, `.auth-page`, `.auth-container`,
`.auth-box`, `.auth-button`, `.form-group`, `.user-dashboard`,
`.request-card`, `.new-request-button`, and `.cancel-request-button`.
These are not guaranteed to exist. Inspect the current HTML and CSS
before adding rules to avoid duplicates or selector mismatches.

## Git workflow

The project was previously reported as connected to a Git repository on
branch `main`, tracking `origin/main`. Verify current Git status.

Typical workflow:

``` bash
git status
git add .
git commit -m "Describe the change"
git push
```

Review `.gitignore`. Do not commit credentials, private configuration,
or database dumps containing personal data.

## Testing checklist

### Public website

-   Homepage loads without PHP warnings or notices.
-   CSS and image paths resolve.
-   Navigation links work.
-   Contact form validates fields and stores a message.
-   Invalid CSRF tokens are rejected.

### Registration and login

-   Registration works.
-   Duplicate usernames/emails are rejected.
-   Passwords are hashed in the database.
-   Valid login succeeds; invalid login fails.
-   Session ID is regenerated after login.
-   Normal users and admins reach the correct dashboards.
-   Logout ends the session.

### User service requests

-   A logged-in user can submit a request.
-   Users see only their own requests.
-   Request statuses display correctly.
-   Pending requests can be cancelled.
-   Cancellation changes status to Cancelled without deleting the
    record.
-   Users cannot cancel another user's request.
-   Users cannot cancel requests that are no longer Pending.

### Admin functions

-   Non-admin users cannot access admin-only pages or actions.
-   Admin can view and manage user accounts.
-   Admin can view requests, update statuses, and add notes if
    implemented.
-   Admin can view and delete contact messages if implemented.
-   Destructive actions use POST and CSRF validation.

### Database

-   User-controlled SQL values use prepared statements.
-   Foreign-key relationships and indexes match the actual schema.
-   Errors are logged server-side without exposing database details to
    visitors.

## Recommended continuation order

1.  Inspect the workspace and report the actual file structure.
2.  Identify which discussed features are implemented and which are
    missing.
3.  Fix broken paths, PHP errors, or security issues without broad
    rewrites.
4.  Complete the separate user main dashboard and service-request flow
    if still outstanding.
5.  Complete and test request cancellation if still outstanding.
6.  Verify admin request management, status updates, and admin notes.
7.  Apply consistent styling across public, authentication, user, and
    admin pages.
8.  Verify database relationships and propose safe schema changes only
    when needed.
9.  Run the testing checklist and explain any manual tests the user must
    perform.

## Important: distinguish this from the Symfony/Docker project

The user has also discussed a separate Symfony/Docker setup. Do not
assume this PHP/XAMPP JNG Digital Solutions website is that Symfony
project. Work in the currently opened VS Code workspace and identify its
framework from the actual files before proceeding.
