<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Session;
use App\Core\Url;
use App\Core\Validator;
use App\Core\View;
use App\Models\Comment;
use App\Models\Discussion;

final class CommentController
{
    public function store(string $rawId): void
    {
        // 1. authentication
        $userId = Auth::id();
        if ($userId === null) {
            View::error(401, 'You must be logged in to post a reply.', ['show_login_link' => true]);
            return;
        }

        // 2. CSRF
        if (!Csrf::verify($_POST['_csrf'] ?? null)) {
            View::error(403, 'Invalid or missing CSRF token. Reload the page and try again.');
            return;
        }

        // 3. discussion
        $discussionId = Validator::positiveInt($rawId);
        if ($discussionId === null) {
            View::error(400, 'Bad request: the discussion ID must be a positive whole number.');
            return;
        }
        if ((new Discussion())->find($discussionId) === null) {
            View::error(404, 'Discussion not found.');
            return;
        }

        $cfg      = $GLOBALS['forum_config'];
        $comments = new Comment();

        // 4. parent (optional). The browser's parent_id is untrusted: verify it.
        $parentId  = null;
        $rawParent = trim(Validator::post('parent_id'));
        $depth     = 0;
        if ($rawParent !== '') {
            $parentId = Validator::positiveInt($rawParent);
            if ($parentId === null || $comments->findInDiscussion($parentId, $discussionId) === null) {
                View::error(422, 'The comment you are replying to does not exist in this discussion.');
                return;
            }
            $parentDepth = $comments->depthOf($parentId, $cfg['max_reply_depth']);
            if ($parentDepth === null || $parentDepth + 1 > $cfg['max_reply_depth']) {
                Session::flash('error', 'This thread is too deep to reply to (limit: depth ' . $cfg['max_reply_depth'] . ').');
                Url::redirect('/discussions/' . $discussionId);
            }
        }

        // 5. content
        $body = trim(Validator::post('body'));
        $len  = Validator::length($body);
        if ($len < 1 || $len > $cfg['comment_max_chars']) {
            Session::flash('error', 'A comment must be between 1 and ' . $cfg['comment_max_chars'] . ' characters.');
            Url::redirect('/discussions/' . $discussionId);
        }

        // 6. save - user_id from the session; any "user_id" field in the form is ignored
        $newId = $comments->create($discussionId, $userId, $parentId, $body);

        Session::flash('success', 'Your comment was posted.');
        Url::redirect('/discussions/' . $discussionId . '#comment-' . $newId);
    }
}
