<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Session;
use App\Core\Url;
use App\Core\Validator;
use App\Core\View;
use App\Models\User;
use PDOException;


final class AuthController
{
   
    private const DUMMY_HASH = '$2y$10$MwRJnfz7MhpmxSLofnymyO5Hlwtbrh00CG7u9oBb//h2oI5UZJhwe';

    public function showRegister(): void
    {
        if (Auth::check()) {
            Url::redirect('/');
        }
        View::render('register', ['title' => 'Register']);
    }

    public function register(): void
    {
        if (!Csrf::verify($_POST['_csrf'] ?? null)) {
            View::error(403, 'Invalid or missing CSRF token. Reload the page and try again.');
            return;
        }

        $username = trim(Validator::post('username'));
        $email    = trim(Validator::post('email'));
        $password = Validator::post('password');
        $confirm  = Validator::post('password_confirm');

        $errors = [];
        if (!preg_match('/^[A-Za-z0-9_]{3,30}$/', $username)) {
            $errors[] = 'Username must be 3-30 characters: letters, numbers and underscore only.';
        }
        if (strlen($email) > 255 || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $errors[] = 'Enter a valid email address.';
        }
        if (strlen($password) < 8) {
            $errors[] = 'Password must be at least 8 characters.';
        } elseif (strlen($password) > 72) {
            $errors[] = 'Password must be at most 72 bytes (bcrypt limit).';
        }
        if ($password !== $confirm) {
            $errors[] = 'Passwords do not match.';
        }

        $users = new User();
        if ($errors === []) {
            if ($users->usernameExists($username)) {
                $errors[] = 'That username is already taken.';
            }
            if ($users->emailExists($email)) {
                $errors[] = 'That email is already registered.';
            }
        }

        if ($errors === []) {
            try {
                $userId = $users->create($username, $email, password_hash($password, PASSWORD_DEFAULT));
            } catch (PDOException $e) {
                if ($e->getCode() === '23000') {         
                    $errors[] = 'That username or email is already registered.';
                } else {
                    throw $e;
                }
            }
        }

        if ($errors !== []) {
            View::render('register', [
                'title'    => 'Register',
                'errors'   => array_map(fn (string $m): array => ['message' => $m], $errors),
                'username' => $username,
                'email'    => $email,
            ], 422);
            return;
        }

        Auth::login($userId);
        Session::flash('success', 'Welcome, ' . $username . '! Your account was created.');
        Url::redirect('/');
    }

    public function showLogin(): void
    {
        if (Auth::check()) {
            Url::redirect('/');
        }
        View::render('login', ['title' => 'Log in']);
    }

    public function login(): void
    {
        if (!Csrf::verify($_POST['_csrf'] ?? null)) {
            View::error(403, 'Invalid or missing CSRF token. Reload the page and try again.');
            return;
        }

        $identifier = trim(Validator::post('identifier'));
        $password   = Validator::post('password');

        $row  = $identifier !== '' ? (new User())->findForLogin($identifier) : null;
        $hash = $row['password_hash'] ?? null;

     
        $hasUsableHash = is_string($hash) && $hash !== '';
        $ok = password_verify($password, $hasUsableHash ? $hash : self::DUMMY_HASH);

        if ($row === null || !$hasUsableHash || !$ok) {
            View::render('login', [
                'title'      => 'Log in',
                'errors'     => [['message' => 'Invalid username/email or password.']], 
                'identifier' => $identifier,
            ], 401);
            return;
        }

        Auth::login((int) $row['id']);
        Session::flash('success', 'Logged in as ' . $row['username'] . '.');
        Url::redirect('/');
    }

    public function logout(): void
    {
        if (!Csrf::verify($_POST['_csrf'] ?? null)) {
            View::error(403, 'Invalid or missing CSRF token.');
            return;
        }
        Auth::logout();
        Session::flash('success', 'You have been logged out.');
        Url::redirect('/');
    }
}
