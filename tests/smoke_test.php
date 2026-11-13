<?php
declare(strict_types=1);

/**
 * Automated end-to-end checks for the lab (section 8.1, step 8).
 *
 * Usage (server must already be running, database loaded):
 *     php tests/smoke_test.php [base_url]
 *     php tests/smoke_test.php http://127.0.0.1:8000
 *
 * Uses the same DB settings as the app (config/config.php: env vars or config/local.php).
 * It creates one throw-away user per run (random name, example.test email).
 * Re-import database/schema.sql to reset the data afterwards.
 */

$root = dirname(__DIR__);
$config = require $root . '/config/config.php';
$base = rtrim($argv[1] ?? 'http://127.0.0.1:8000', '/');

$c = $config['db'];
$pdo = new PDO(
    sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $c['host'], $c['port'], $c['name']),
    $c['user'], $c['pass'],
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
);

$pass = 0;
$fail = 0;
function check(string $name, bool $ok, string $detail = ''): void
{
    global $pass, $fail;
    $ok ? $pass++ : $fail++;
    printf("  [%s] %s%s\n", $ok ? 'PASS' : 'FAIL', $name, (!$ok && $detail !== '') ? "  -> $detail" : '');
}

/** Tiny HTTP client with its own cookie jar (= one browser). */
final class Browser
{
    private string $jar;
    public function __construct(private string $base)
    {
        $this->jar = tempnam(sys_get_temp_dir(), 'jar');
    }
    public function request(string $method, string $path, array $post = []): array
    {
        $ch = curl_init($this->base . $path);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER         => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_COOKIEJAR      => $this->jar,
            CURLOPT_COOKIEFILE     => $this->jar,
            CURLOPT_TIMEOUT        => 15,
        ]);
        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post));
        }
        $raw = (string) curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $headerSize = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        curl_close($ch);
        return ['status' => $status, 'headers' => substr($raw, 0, $headerSize), 'body' => substr($raw, $headerSize)];
    }
    public function get(string $path): array { return $this->request('GET', $path); }
    public function post(string $path, array $data): array { return $this->request('POST', $path, $data); }
    public function csrf(string $path): string
    {
        $html = $this->get($path)['body'];
        preg_match('/name="_csrf" value="([a-f0-9]{64})"/', $html, $m);
        return $m[1] ?? '';
    }
    public function sessionId(): string
    {
        foreach (file($this->jar) as $line) {
            if (str_contains($line, "forum_sid")) {
                return trim(substr($line, strrpos($line, "\t") + 1));
            }
        }
        return '';
    }
}

$commentCount = fn (): int => (int) $pdo->query('SELECT COUNT(*) FROM comments')->fetchColumn();

$suffix   = bin2hex(random_bytes(3));
$username = "tester_$suffix";
$email    = "tester_$suffix@example.test";
$password = 'correct horse battery ' . $suffix;

echo "\n== 1. Public reading and tree display ==\n";
$guest = new Browser($base);
$r = $guest->get('/');
check('Home page lists discussions (200)', $r['status'] === 200 && str_contains($r['body'], 'How do I learn PHP?'));
$r = $guest->get('/discussions/1');
check('Discussion 1 renders (200)', $r['status'] === 200 && str_contains($r['body'], 'How do I learn PHP?'));
check('Deepest seed comment shows "Depth 4"', str_contains($r['body'], 'Depth 4'));
check('Seed comment 7 nested at data-depth="4"', str_contains($r['body'], 'id="comment-7" data-depth="4"'));
check('Guest sees no reply form', !str_contains($r['body'], 'name="parent_id"'));

echo "\n== 2. Invalid and missing IDs ==\n";
check('Malformed ID "abc" -> 400', $guest->get('/discussions/abc')['status'] === 400);
check('Zero ID -> 400', $guest->get('/discussions/0')['status'] === 400);
check('Negative ID -> 400', $guest->get('/discussions/-1')['status'] === 400);
// PHP's built-in dev server answers 404 itself for paths containing a dot ("1.5" looks like a file);
// under Apache + public/.htaccess the request reaches the app, which answers 400. Both reject it.
check('Decimal ID rejected (400 via app, or 404 from the dev server)', in_array($guest->get('/discussions/1.5')['status'], [400, 404], true));
check('Missing discussion 999 -> 404', $guest->get('/discussions/999')['status'] === 404);
check('Unknown route -> 404', $guest->get('/no-such-page')['status'] === 404);

echo "\n== 3. Guest cannot post (UI and direct POST) ==\n";
$before = $commentCount();
$guestToken = $guest->csrf('/discussions/1');
$r = $guest->post('/discussions/1/comments', ['_csrf' => $guestToken, 'body' => 'guest attempt', 'parent_id' => '']);
check('Guest POST with valid CSRF token -> 401', $r['status'] === 401, "got {$r['status']}");
$r = (new Browser($base))->post('/discussions/1/comments', ['body' => 'no session at all']);
check('Direct POST with no session/token -> 401', $r['status'] === 401, "got {$r['status']}");
check('No row created by guest POSTs', $commentCount() === $before);

echo "\n== 4. Registration ==\n";
$reg = new Browser($base);
$before = (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
$r = $reg->post('/register', ['_csrf' => 'bad-token', 'username' => $username, 'email' => $email, 'password' => $password, 'password_confirm' => $password]);
check('Register with invalid CSRF -> 403', $r['status'] === 403, "got {$r['status']}");
$tok = $reg->csrf('/register');
$r = $reg->post('/register', ['_csrf' => $tok, 'username' => 'x', 'email' => 'not-an-email', 'password' => 'short', 'password_confirm' => 'nope']);
check('Register with invalid input -> 422', $r['status'] === 422, "got {$r['status']}");
$r = $reg->post('/register', ['_csrf' => $tok, 'username' => 'alice', 'email' => 'new@example.test', 'password' => $password, 'password_confirm' => $password]);
check('Register with duplicate username -> 422', $r['status'] === 422 && str_contains($r['body'], 'already taken'));
check('No user created by bad registrations', (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn() === $before);
$r = $reg->post('/register', ['_csrf' => $tok, 'username' => $username, 'email' => $email, 'password' => $password, 'password_confirm' => $password]);
check('Valid registration -> 303 redirect', $r['status'] === 303, "got {$r['status']}");
$row = $pdo->prepare('SELECT id, password_hash FROM users WHERE username = ?');
$row->execute([$username]);
$user = $row->fetch();
check('Password stored as a bcrypt/argon hash, not plain text', is_array($user) && $user['password_hash'] !== $password && password_verify($password, (string) $user['password_hash']));
$userId = (int) ($user['id'] ?? 0);

echo "\n== 5. Login / logout ==\n";
$b = new Browser($base);
$tok = $b->csrf('/login');
$sidBefore = $b->sessionId();
$r = $b->post('/login', ['_csrf' => 'wrong', 'identifier' => $username, 'password' => $password]);
check('Login with invalid CSRF -> 403', $r['status'] === 403, "got {$r['status']}");
$r = $b->post('/login', ['_csrf' => $tok, 'identifier' => $username, 'password' => 'wrong password']);
check('Login with wrong password -> 401', $r['status'] === 401 && str_contains($r['body'], 'Invalid username/email or password'), "got {$r['status']}");
$r = $b->post('/login', ['_csrf' => $tok, 'identifier' => 'alice', 'password' => '']);
check('Seed user with NULL hash cannot log in (empty password) -> 401', $r['status'] === 401);
$r = $b->post('/login', ['_csrf' => $tok, 'identifier' => 'alice', 'password' => 'anything']);
check('Seed user with NULL hash cannot log in (any password) -> 401', $r['status'] === 401);
$r = $b->post('/login', ['_csrf' => $tok, 'identifier' => "' OR '1'='1", 'password' => "' OR '1'='1"]);
check("SQL-injection style login -> 401", $r['status'] === 401);
$r = $b->post('/login', ['_csrf' => $tok, 'identifier' => $username, 'password' => $password]);
check('Correct login -> 303', $r['status'] === 303, "got {$r['status']}");
check('Session ID changed after login (regenerated)', $b->sessionId() !== '' && $b->sessionId() !== $sidBefore);
check('Session cookie is HttpOnly + SameSite', (bool) preg_match('/Set-Cookie: forum_sid=[^;]+;.*HttpOnly/i', $r['headers']) && (bool) preg_match('/SameSite=Lax/i', $r['headers']));
$page = $b->get('/discussions/1')['body'];
check('Logged-in user sees reply forms', str_contains($page, 'name="parent_id" value="7"'));

echo "\n== 6. Authenticated replies ==\n";
$tok = $b->csrf('/discussions/1');
$before = $commentCount();

// depth-5 reply: reply to comment 7 (displayed depth 4)
$r = $b->post('/discussions/1/comments', ['_csrf' => $tok, 'parent_id' => '7', 'body' => 'Depth five reply from the test', 'user_id' => '1']);
check('Reply to comment 7 -> 303', $r['status'] === 303, "got {$r['status']}");
$depth5 = $pdo->query("SELECT * FROM comments WHERE body = 'Depth five reply from the test'")->fetch();
check('Depth-5 reply stored under parent 7', is_array($depth5) && (int) $depth5['parent_id'] === 7 && (int) $depth5['discussion_id'] === 1);
check('Author is the SESSION user (forged user_id=1 ignored)', is_array($depth5) && (int) $depth5['user_id'] === $userId, 'user_id=' . ($depth5['user_id'] ?? 'null'));
$page = $b->get('/discussions/1')['body'];
check('Page shows "Depth 5" label for the new reply', str_contains($page, 'data-depth="5"') && str_contains($page, 'Depth 5'));
check('New reply shows author name', str_contains($page, $username));

// second top-level comment
$r = $b->post('/discussions/1/comments', ['_csrf' => $tok, 'parent_id' => '', 'body' => 'Second top-level comment from the test']);
$top = $pdo->query("SELECT * FROM comments WHERE body = 'Second top-level comment from the test'")->fetch();
check('Second top-level comment saved with parent_id NULL', $r['status'] === 303 && is_array($top) && $top['parent_id'] === null && (int) $top['user_id'] === $userId);
$page = $b->get('/discussions/1')['body'];
check('Top-level comment rendered at depth 0', (bool) preg_match('/id="comment-' . (int) $top['id'] . '" data-depth="0"/', $page));

echo "\n== 7. Rejected writes ==\n";
$before = $commentCount();
$r = $b->post('/discussions/1/comments', ['_csrf' => 'forged', 'body' => 'bad csrf', 'parent_id' => '1']);
check('Invalid CSRF token -> 403', $r['status'] === 403, "got {$r['status']}");
$r = $b->post('/discussions/1/comments', ['body' => 'missing csrf']);
check('Missing CSRF token -> 403', $r['status'] === 403, "got {$r['status']}");
$r = $b->post('/discussions/1/comments', ['_csrf' => $tok, 'body' => 'cross discussion', 'parent_id' => '8']);
check('Parent from another discussion (id 8) -> 422', $r['status'] === 422, "got {$r['status']}");
$r = $b->post('/discussions/1/comments', ['_csrf' => $tok, 'body' => 'ghost parent', 'parent_id' => '99999']);
check('Nonexistent parent -> 422', $r['status'] === 422, "got {$r['status']}");
$r = $b->post('/discussions/1/comments', ['_csrf' => $tok, 'body' => 'text parent', 'parent_id' => '1 OR 1=1']);
check('Non-numeric parent_id -> 422', $r['status'] === 422, "got {$r['status']}");
$r = $b->post('/discussions/999/comments', ['_csrf' => $tok, 'body' => 'to a missing discussion']);
check('Post to missing discussion -> 404', $r['status'] === 404, "got {$r['status']}");
$r = $b->post('/discussions/abc/comments', ['_csrf' => $tok, 'body' => 'malformed id']);
check('Post to malformed discussion ID -> 400', $r['status'] === 400, "got {$r['status']}");
$r = $b->post('/discussions/1/comments', ['_csrf' => $tok, 'body' => '   ']);
check('Empty/whitespace body rejected (redirect with error)', $r['status'] === 303);
$r = $b->post('/discussions/1/comments', ['_csrf' => $tok, 'body' => str_repeat('a', 2001)]);
check('Over-long body (2001 chars) rejected', $r['status'] === 303);
$r = $b->post('/discussions/1/comments', ['_csrf' => $tok, 'body' => ['array'], 'parent_id' => '1']);
check('Array instead of string body rejected', $r['status'] === 303);
check('None of the rejected requests created a row', $commentCount() === $before);

echo "\n== 8. HTML / script escaping ==\n";
$xss = '<script>alert("xss")</script> <img src=x onerror=alert(1)> & "quotes"';
$r = $b->post('/discussions/1/comments', ['_csrf' => $tok, 'body' => $xss, 'parent_id' => '5']);
$stored = $pdo->query("SELECT body FROM comments ORDER BY id DESC LIMIT 1")->fetchColumn();
check('Raw text is stored unchanged in the database (escaped on output)', $stored === $xss);
$page = $b->get('/discussions/1')['body'];
check('Page does NOT contain the raw <script> tag', !str_contains($page, '<script>alert("xss")</script>'));
check('Page does NOT contain a raw <img onerror> tag', !str_contains($page, '<img src=x onerror'));
check('Page contains the escaped text &lt;script&gt;', str_contains($page, '&lt;script&gt;alert(&quot;xss&quot;)&lt;/script&gt;'));

echo "\n== 9. Logout, then guest again ==\n";
$tok = $b->csrf('/discussions/1');
$r = $b->post('/logout', ['_csrf' => 'bad']);
check('Logout with invalid CSRF -> 403', $r['status'] === 403);
$r = $b->get('/logout');
check('GET /logout is not a route -> 404', $r['status'] === 404);
$r = $b->post('/logout', ['_csrf' => $tok]);
check('Logout with valid CSRF -> 303', $r['status'] === 303);
$before = $commentCount();
$tok = $b->csrf('/discussions/1');
$r = $b->post('/discussions/1/comments', ['_csrf' => $tok, 'body' => 'after logout', 'parent_id' => '1']);
check('Direct POST after logout -> 401', $r['status'] === 401, "got {$r['status']}");
check('No row created after logout', $commentCount() === $before);
check('Guest UI shows no reply form after logout', !str_contains($b->get('/discussions/1')['body'], 'name="parent_id"'));

echo "\n== 10. Optional collapse challenge (server side) ==\n";
$page = $guest->get('/discussions/1')['body'];
preg_match_all('/<button type="button" class="toggle-replies" hidden aria-controls="(replies-\d+)" aria-expanded="true">Hide replies<\/button>/', $page, $m);
$ids = $m[1];
// comment 1 has children; comment 4 (Dee) is a leaf. (Comments 5 and 7 gain children during sections 6 and 8.)
check('Buttons exist only for comments that have children', in_array('replies-1', $ids, true) && !in_array('replies-4', $ids, true));
$allTargets = true;
foreach ($ids as $id) {
    $allTargets = $allTargets && substr_count($page, 'id="' . $id . '"') === 1;
}
check('Every aria-controls points at exactly one existing container', $ids !== [] && $allTargets);
check('Buttons start hidden and branches start expanded (works without JS)', !str_contains($page, '<ul class="replies" id="replies-1" hidden'));

echo "\nResult: $pass passed, $fail failed\n";
exit($fail === 0 ? 0 : 1);
