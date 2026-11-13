<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Url;
use App\Core\Validator;
use App\Core\View;
use App\Models\Comment;
use App\Models\Discussion;
use DateTimeImmutable;
use DateTimeZone;

final class DiscussionController
{
    private bool $truncated = false;

    public function index(): void
    {
        $rows = (new Discussion())->all();
        $items = array_map(fn (array $r): array => [
            'id'            => (int) $r['id'],
            'url'           => Url::to('/discussions/' . (int) $r['id']),
            'title'         => $r['title'],
            'author'        => $r['author'],
            'comment_count' => (int) $r['comment_count'],
            'created_at'    => self::utc($r['created_at']),
        ], $rows);

        View::render('home', ['title' => 'Discussions', 'discussions' => $items, 'has_discussions' => $items !== []]);
    }

    public function show(string $rawId): void
    {
        // 1. validate the ID before touching the database
        $id = Validator::positiveInt($rawId);
        if ($id === null) {
            View::error(400, 'Bad request: the discussion ID must be a positive whole number.');
            return;
        }

        // 2. data-access layer
        $discussion = (new Discussion())->find($id);
        if ($discussion === null) {
            View::error(404, 'Discussion not found.');
            return;
        }
        $cfg = $GLOBALS['forum_config'];
        $rows = (new Comment())->forDiscussion($id, $cfg['max_render_nodes']);

        // 3. group comments by parent id (null parent = top level, stored under key 0)
        $byParent = [];
        foreach ($rows as $row) {
            $byParent[$row['parent_id'] === null ? 0 : (int) $row['parent_id']][] = $row;
        }

        // 4. walk the tree from the roots (with cycle guard + traversal limits)
        $visited = [];
        $count = 0;
        $tree = $this->buildBranch($byParent, 0, 0, $visited, $count, $cfg);

        // 5. prepare view data
        View::render('discussion', [
            'title'      => $discussion['title'],
            'discussion' => [
                'id'         => (int) $discussion['id'],
                'title'      => $discussion['title'],
                'body'       => $discussion['body'],
                'author'     => $discussion['author'],
                'avatar_url' => Url::asset('avatar-default.svg'),
                'created_at' => self::utc($discussion['created_at']),
                'iso'        => self::iso($discussion['created_at']),
            ],
            'comments'        => $tree,
            'has_comments'    => $tree !== [],
            'truncated'       => $this->truncated || count($rows) > $cfg['max_render_nodes'],
            'post_url'        => Url::to('/discussions/' . (int) $discussion['id'] . '/comments'),
            'max_chars'       => $cfg['comment_max_chars'],
            'avatar_url'      => Url::asset('avatar-default.svg'),
        ]);
    }

    private function buildBranch(array $byParent, int $parentId, int $depth, array &$visited, int &$count, array $cfg): array
    {
        $out = [];
        foreach ($byParent[$parentId] ?? [] as $row) {
            $id = (int) $row['id'];
            if (isset($visited[$id])) {
                continue;
            }
            if ($count >= $cfg['max_render_nodes'] || $depth > $cfg['max_render_depth']) {
                $this->truncated = true;
                continue;
            }
            $visited[$id] = true;
            $count++;

            $children = $this->buildBranch($byParent, $id, $depth + 1, $visited, $count, $cfg);

            $out[] = [
                'id'           => $id,
                'author'       => $row['author'],
                'avatar_url'   => Url::asset('avatar-default.svg'),
                'body'         => $row['body'],
                'created_at'   => self::utc($row['created_at']),
                'iso'          => self::iso($row['created_at']),
                'depth'        => $depth,
                'indent'       => $depth >= 1 && $depth <= $cfg['indent_cap'],
                'has_children' => $children !== [],
                'children'     => $children,
                'replies_id'   => 'replies-' . $id,
            ];
        }
        return $out;
    }

    private static function utc(string $dbDatetime): string
    {
        return (new DateTimeImmutable($dbDatetime, new DateTimeZone('UTC')))->format('Y-m-d H:i') . ' UTC';
    }

    private static function iso(string $dbDatetime): string
    {
        return (new DateTimeImmutable($dbDatetime, new DateTimeZone('UTC')))->format('Y-m-d\TH:i:s\Z');
    }
}
