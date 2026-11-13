# Nested Forum Lab (PHP, PDO, MySQL)

A small forum that shows replies as a tree. Anyone can read; only a logged-in user can post.
Built with a front controller, a router, controllers, PDO models and Mustache templates.

## Libraries and design choices

| Concern | Choice | Why |
|---|---|---|
| Routing | [Bramus Router](https://github.com/bramus/router) | Lecture library; simple `Controller@method` routes |
| Data access | Plain **PDO** with prepared statements | Keeps the lab small; all SQL is inside `app/Models/` (none in controllers/templates) |
| Templates | [Mustache](https://github.com/bobthecow/mustache.php) | Lecture library; `{{ }}` HTML-escapes by default |
| Passwords | `password_hash()` / `password_verify()` | Built into PHP, bcrypt by default |

Eloquent was **not** used (substitution: PDO instead of Eloquent, allowed by the lab).

## Requirements

PHP 8.2+ with `pdo_mysql`, MySQL 8 or MariaDB 10.4+, Composer, a web server (PHP's built-in server is enough).

## Install and run (step by step)

```bash
# 1. install dependencies (creates vendor/ and composer.lock)
composer install

# 2. create the database + tables + fictional seed data
#    (command line)   mysql -u root -p < database/schema.sql
#    (XAMPP/phpMyAdmin) Import -> choose database/schema.sql -> Go

# 3. only if your MySQL user/password are not root + empty password:
cp config/local.example.php config/local.php     # then edit it (git-ignored)
#    ...or set environment variables DB_HOST, DB_PORT, DB_NAME, DB_USER, DB_PASS

# 4. start the app
composer serve                                   # = php -S localhost:8000 -t public
```

Open <http://localhost:8000/> and then <http://localhost:8000/discussions/1>.

Re-importing `database/schema.sql` **resets** the lab data.

### Seed users cannot log in
`alice`, `ben`, `cara`, `dee`, `eli` are fictional authors with a `NULL` password hash, so they never authenticate. Use **Register** to create a real account.

## Routes

| Method | URL | Controller | Notes |
|---|---|---|---|
| GET | `/` | `DiscussionController@index` | list discussions |
| GET | `/discussions/{id}` | `DiscussionController@show` | tree view. Malformed ID -> **400**, unknown ID -> **404** |
| GET/POST | `/register` | `AuthController` | validation, duplicate check, `password_hash` |
| GET/POST | `/login` | `AuthController` | `password_verify`, session ID regenerated |
| POST | `/logout` | `AuthController@logout` | CSRF-protected |
| POST | `/discussions/{id}/comments` | `CommentController@store` | top-level comment (no `parent_id`) or reply (`parent_id`) |

**ID policy:** the route accepts any text for `{id}`; the controller returns **400** for anything that is not a positive whole number (`abc`, `0`, `-1`, `1.5`) and **404** for a valid number with no discussion. (PHP's built-in dev server itself answers 404 for IDs containing a dot such as `1.5`, because it mistakes them for files; under Apache with the rewrite below the app returns 400.)

## How a request flows

```
Browser -> public/index.php (front controller)
        -> Bramus Router (config/routes.php) matches URL + method
        -> Controller (app/Controllers)   validate ID, check login/CSRF, group comments by parent
        -> Model (app/Models)              prepared PDO queries, returns arrays
        -> View (app/Views/*.mustache)     escapes and renders HTML
        -> Browser
```

## Data model: `discussion_id` and `parent_id`

* Every comment has a `discussion_id` (which thread it is in).
* `parent_id` is `NULL` for a top-level comment (depth 0), otherwise it is the `id` of the comment being replied to.
* Comments with the same parent are siblings. **Depth is calculated while rendering, never stored.**
* The composite foreign key `(discussion_id, parent_id) -> comments(discussion_id, id)` forces a parent to be in the **same** discussion. When `parent_id` is `NULL` the key is not checked, so top-level comments are allowed.
* The foreign key does not stop every cycle, so the application never edits `parent_id` after insert and a parent must already exist when a reply is created. The renderer also tracks visited IDs, so a bad row could not loop forever.

### Depth and limits (`config/config.php`)

| Setting | Value | Meaning |
|---|---|---|
| `indent_cap` | 8 | indentation stops growing after depth 8; the `Depth N` label stays accurate |
| `max_reply_depth` | 30 | the server refuses replies that would be deeper than 30 |
| `max_render_depth` / `max_render_nodes` | 60 / 2000 | traversal limits for rendering |
| `comment_max_chars` | 2000 | maximum comment length |

## Security notes

* **Login required on the server** for every write (`CommentController::store` checks `Auth::id()` first -> 401). Hiding the form is only a convenience.
* **user_id comes from the session**, never from form data (a forged `user_id` field is ignored).
* **CSRF:** a session-backed random token is in a hidden `_csrf` field of *every* POST form (register, login, logout, reply) and checked with `hash_equals` -> 403 if wrong.
* **Parameter binding:** every query uses PDO prepared statements with real (non-emulated) server-side preparation. User input is sent as *data* and is never concatenated into SQL, so input like `' OR '1'='1` is just text.
* **HTML escaping:** templates print user text only with `{{ }}`, which runs `htmlspecialchars(..., ENT_QUOTES)`. `<script>` is displayed as text. Triple braces are used only for the already-escaped page body in `layout.mustache`. A Content-Security-Policy header adds a second layer.
* **Sessions:** cookie is `HttpOnly`, `SameSite=Lax`, `Secure` over HTTPS; `session.use_strict_mode` on; ID regenerated on login and logout.
* **Passwords:** hashed with `password_hash`; a `NULL`/empty hash never authenticates; the same error message is used for unknown user and wrong password; passwords are never logged.
* No credentials are committed: `config/local.php`, `.env` and `vendor/` are git-ignored.

## Tests

Automated end-to-end checks (server running, database loaded):

```bash
composer test            # = php tests/smoke_test.php http://localhost:8000
```

It covers: tree display and `Depth 4`; 400/404 handling; guest POST rejected (form and direct POST); registration and duplicate rejection; wrong password; seed users with `NULL` hash cannot log in; session ID regenerated; depth-5 reply and second top-level comment saved with the right author and parent; forged `user_id` ignored; invalid/missing CSRF; cross-discussion parent; HTML/script comment escaped; logout then direct POST rejected; collapse-button markup.

Manual checks and screenshot checklist: [`tests/manual-checks.md`](tests/manual-checks.md).

## Optional challenge: collapse / expand replies (implemented)

* Each comment with children has a `<button type="button" class="toggle-replies" aria-controls="replies-ID" aria-expanded="true">`; the child list is `<ul id="replies-ID">`.
* The buttons are rendered with the `hidden` attribute and **revealed by `public/assets/app.js`**, so with JavaScript disabled everything stays expanded and no dead buttons appear.
* The script toggles the `hidden` attribute of that one branch, updates `aria-expanded`, and switches the label between "Hide replies" and "Show replies". It only uses `textContent`/attributes, never inserts comment text as HTML.

## Production note: URL rewriting (explanation only)

The local test needs no rewrite. On Apache, `public/.htaccess` sends every non-file URL to `index.php`:

```apache
RewriteEngine On
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^ index.php [L]
```

On Nginx the equivalent is `location / { try_files $uri /index.php?$query_string; }`. Point the web root at `public/` so `app/`, `config/` and `vendor/` are never served. Use HTTPS in production.

## Known limitations

* All comments of one discussion are loaded into memory and rendered recursively: fine for lab data, not for a large forum (would need pagination or lazy loading).
* No rate limiting, account recovery, moderation, avatar upload or Gravatar (not required in this lab).
* A disconnected cycle in the database would be unreachable from a root and therefore not displayed; the renderer's visited-set is not a database-wide integrity check.

## Project layout

```
public/index.php          front controller        public/assets/   css, js, default avatar
app/Controllers/          HTTP handling           app/Models/      PDO data access
app/Views/                Mustache templates      app/Core/        session, CSRF, auth, view helpers
config/                   config + routes         database/schema.sql   schema + seed data
tests/                    smoke_test.php + manual-checks.md
```
