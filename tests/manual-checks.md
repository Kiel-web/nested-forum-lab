# Manual checks (and screenshot checklist)

Start fresh: import `database/schema.sql`, run `composer serve`, open <http://localhost:8000>.
Automated version of most of these: `composer test`.

| # | Do this | Expected result |
|---|---|---|
| 1 | Open `/discussions/1` as a guest | Tree: Alice (0) > Ben (1) > Cara (2) > Ben (3) > Eli (4); Dee (1) under Alice; Eli (0). Each shows author, avatar, UTC time, `Depth N`. No reply forms. |
| 2 | Open `/discussions/abc`, `/discussions/0` | **400** |
| 3 | Open `/discussions/999` | **404** |
| 4 | Register a new account (e.g. `student_demo`) | Redirected home, header says "Signed in as ..." |
| 5 | Register again with the same username | Error "already taken", no new user |
| 6 | Log out, then log in with a wrong password | "Invalid username/email or password" |
| 7 | Log in as `alice` with any password | Rejected (seed users have a NULL hash) |
| 8 | Log in correctly. Open Reply under Eli's "Add replies next." and post | New comment shows **Depth 5**, your username, correct indentation |
| 9 | Post a comment in the "Post a top-level comment" box | Appears at **Depth 0** at the bottom of the list |
| 10 | Log out. Reload the discussion | No reply forms, "Log in to reply" links |
| 11 | Guest direct POST (see below) | **401**, no new row (check with `SELECT COUNT(*) FROM comments;`) |
| 12 | Invalid CSRF POST (see below) | **403**, no new row |
| 13 | Cross-discussion parent: reply to discussion 1 with `parent_id=8` | **422**, no new row |
| 14 | Post a comment containing `<script>alert(1)</script>` | Shown as literal text; no alert; page source has `&lt;script&gt;` |
| 15 | Optional: click **Hide replies** on Alice | Ben, Cara, Dee hidden; Alice and Eli visible; button says **Show replies**. Click again to restore. Collapse Ben: Cara hidden, Dee visible. Use Tab + Enter/Space too. |

## Direct POST requests (for items 11-13)

Guest, no session (item 11):

```bash
curl -i -X POST http://localhost:8000/discussions/1/comments -d "body=hello&parent_id=1"
```

Logged-in browser but forged token (item 12): in the browser dev tools, edit the hidden `_csrf` field of a reply form to `abc`, then submit. Or with curl, send a wrong `_csrf` using a valid session cookie.

Cross-discussion parent (item 13): in dev tools, change the hidden `parent_id` of a reply form to `8`, then submit.

On Windows PowerShell use `curl.exe` instead of `curl`.

## Screenshot checklist for the Google Doc

1. Project structure (folders) and `config/routes.php`
2. Controller, model and template files side by side (separation)
3. Registration and login screens (and success state)
4. Nested thread showing your depth-5 reply and second top-level comment
5. Rejected guest POST (curl output with `401` and unchanged row count)
6. `/discussions/abc` (400), `/discussions/999` (404)
7. Invalid CSRF result (403)
8. HTML-escaping check (script text shown literally; also page source)

Do not show passwords, database credentials or session cookies in screenshots.
