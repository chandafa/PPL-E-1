<?php

declare(strict_types=1);
use App\Support\Integration\Runtime;

require dirname(__DIR__).'/vendor/autoload.php';

$runtime = Runtime::ready(dirname(__DIR__), $argv[1] ?? 'http');
$runtime['e1Environment'] = Runtime::e1Environment(dirname(__DIR__), $argv[1] ?? 'http');
echo ($argv[2] ?? '') === 'f1-path'
    ? $runtime['setup']['repositories']['f1']['path']
    : json_encode($runtime, JSON_THROW_ON_ERROR);
