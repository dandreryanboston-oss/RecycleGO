<?php
require_once __DIR__ . '/config.php';

function rg_json($data, int $code=200){ http_response_code($code); echo json_encode($data); exit; }
function rg_body(){ $raw=file_get_contents('php://input'); $d=json_decode($raw,true); return is_array($d)?$d:[]; }

function rg_make_token(array $payload): string {
  $h = base64_encode(json_encode(['alg'=>'HS256','typ'=>'JWT']));
  $b = base64_encode(json_encode($payload));
  $s = base64_encode(hash_hmac('sha256', "$h.$b", RG_JWT_SECRET, true));
  return "$h.$b.$s";
}
function rg_verify_token(string $token) {
  $p = explode('.', $token); if(count($p)!==3)return false;
  [$h,$b,$s] = $p;
  $exp = base64_encode(hash_hmac('sha256', "$h.$b", RG_JWT_SECRET, true));
  if(!hash_equals($exp,$s)) return false;
  $payload = json_decode(base64_decode($b), true);
  if(!is_array($payload)) return false;
  if(isset($payload['exp']) && time() > $payload['exp']) return false;
  return $payload;
}
function rg_auth_user() {
  $hdr = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
  if (!preg_match('/Bearer\s+(.+)/i', $hdr, $m)) return null;
  $pay = rg_verify_token(trim($m[1])); if(!$pay || empty($pay['uid'])) return null;
  $pdo = rg_pdo();
  $st = $pdo->prepare('SELECT id,name,email,avatar_url,points FROM users WHERE id=?');
  $st->execute([$pay['uid']]);
  return $st->fetch() ?: null;
}
function rg_require_auth(){ $u = rg_auth_user(); if(!$u) rg_json(['error'=>'Unauthorized'],401); return $u; }

function rg_user_points(int $uid): int {
  $pdo = rg_pdo();
  $st = $pdo->prepare('SELECT COALESCE(SUM(weight_kg)*10,0) pts FROM recycle_logs WHERE user_id=?');
  $st->execute([$uid]);
  $pts = (int)$st->fetch()['pts'];
  $pdo->prepare('UPDATE users SET points=? WHERE id=?')->execute([$pts,$uid]);
  return $pts;
}
function rg_award_medals_if_any(int $uid){
  $pdo = rg_pdo();
  $st = $pdo->prepare('SELECT COALESCE(SUM(weight_kg),0) t FROM recycle_logs WHERE user_id=?');
  $st->execute([$uid]); $total = (float)$st->fetch()['t'];
  foreach ($pdo->query('SELECT id,threshold_kg FROM medals') as $m){
    if($total >= (float)$m['threshold_kg']){
      $chk = $pdo->prepare('SELECT 1 FROM user_medals WHERE user_id=? AND medal_id=?');
      $chk->execute([$uid,$m['id']]);
      if(!$chk->fetch()){
        $pdo->prepare('INSERT INTO user_medals(user_id,medal_id,awarded_at) VALUES (?,?,NOW())')
            ->execute([$uid,$m['id']]);
      }
    }
  }
}
