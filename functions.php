<?php
declare(strict_types=1);
function h($value): string { return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function redirect(string $page): void { header('Location: ?page=' . rawurlencode($page)); exit; }
function initial($value, int $start = 0, int $length = 1): string { $value = (string)$value; if (function_exists('mb_substr')) return mb_substr($value, $start, $length, 'UTF-8'); if (preg_match('/^./u', $value, $match)) return $match[0]; return substr($value, 0, 1); }
function csrf_field(): string { if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32)); return '<input type="hidden" name="_csrf" value="' . h($_SESSION['csrf']) . '">'; }
function verify_csrf(): void { if (!isset($_POST['_csrf'], $_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], (string)$_POST['_csrf'])) { http_response_code(419); exit('Your session expired. Refresh the page and try again.'); } }
function icon(string $name): string { $icons=['dashboard'=>'◫','products'=>'▤','orders'=>'▧','customers'=>'♙','categories'=>'▦','suppliers'=>'⌂'];return $icons[$name]??'•'; }
