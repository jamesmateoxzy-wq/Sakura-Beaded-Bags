<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/config.php';
function sbb_admin_guard(): void {
 if (session_status() === PHP_SESSION_NONE) session_start();
 if (empty($_SESSION['user_id']) || $_SESSION['user_id']==='demo' || !in_array(strtolower((string)($_SESSION['user_email']??'')),array_map('strtolower',ALLOWED_ADMIN_EMAILS),true)) { header('Location: login.php'); exit; }
}
function sbb_form_token(): string { return $_SESSION['final_csrf'] ??= bin2hex(random_bytes(32)); }
function sbb_check_token(): void { if (!hash_equals(sbb_form_token(),(string)($_POST['csrf']??''))) throw new InvalidArgumentException('Session expired. Refresh and retry.'); }
function sbb_activity(PDO $db,string $action): void { $db->prepare('INSERT INTO sbb_activity (actor_email,action_name) VALUES (?,?)')->execute([$_SESSION['user_email'],$action]); }
function sbb_settings(PDO $db): array { return array_replace(['boutique_name'=>'Sakura Beaded Bags','contact'=>'','address'=>'','labor_rate'=>100,'packaging'=>50,'markup'=>100,'dp_policy'=>50],$db->query('SELECT setting_key,setting_value FROM sbb_settings')->fetchAll(PDO::FETCH_KEY_PAIR)); }
function sbb_text($value,int $max,bool $required=false): string { if (!is_string($value)) throw new InvalidArgumentException('Invalid text.'); $value=trim($value); if (($required && $value==='') || strlen($value)>$max) throw new InvalidArgumentException('Check the required fields and text length.'); return $value; }
function sbb_phone($value): string { $value=sbb_text($value,20); if ($value!=='' && !preg_match('/^[0-9+ -]{7,20}$/D',$value)) throw new InvalidArgumentException('Enter a valid contact number.'); return $value; }

function sbb_optional_settings(PDO $db): array { try { return sbb_settings($db); } catch (Throwable $e) { error_log('SBB settings: '.$e->getMessage()); return []; } }
function sbb_log_success(string $module, $action): void {
 if (!is_string($action) || !preg_match('/^[a-z_]{1,50}$/D',$action) || empty($_SESSION['user_email']) || ($_SESSION['user_id']??'')==='demo') return;
 try { sbb_activity(sbb_db(),$module.': '.str_replace('_',' ',$action)); } catch(Throwable $e) { error_log('SBB audit: '.$e->getMessage()); }
}
