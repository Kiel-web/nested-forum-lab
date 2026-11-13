<?php
declare(strict_types=1);

$config = [
    'db' => [
        'host' => getenv('DB_HOST') ?: '127.0.0.1',
        'port' => (int) (getenv('DB_PORT') ?: 3306),
        'name' => getenv('DB_NAME') ?: 'nested_forum',
        'user' => getenv('DB_USER') ?: 'root',
        'pass' => getenv('DB_PASS') !== false ? (string) getenv('DB_PASS') : '',
    ],
    'forum' => [
        'indent_cap'        => 8,    
        'max_reply_depth'   => 30,   
        'max_render_depth'  => 60,   
        'max_render_nodes'  => 2000, 
        'comment_max_chars' => 2000,
    ],
];

$local = __DIR__ . '/local.php';
if (is_file($local)) {
    $config = array_replace_recursive($config, require $local);
}

return $config;
