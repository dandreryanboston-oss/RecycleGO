<?php
require_once __DIR__ . '/helpers.php';

$path   = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$method = $_SERVER['REQUEST_METHOD'];
$pdo    = rg_pdo();

/* === Health === */
if ($path === '/api' && $method === 'GET') rg_json(['ok'=>true,'name'=>'RecycleGO API','time'=>time()]);

/* === Auth: Register === */
if ($path === '/api/auth/register' && $method === 'POST') {
  $b = rg_body();
  $name = trim($b['name'] ?? '');
  $email = strtolower(trim($b['email'] ?? ''));
  $pass = $b['password'] ?? '';
  if(!$name || !$email || !$pass) rg_json(['error'=>'Missing fields'],422);

  $st = $pdo->prepare('SELECT id FROM users WHERE email=?'); $st->execute([$email]);
  if ($st->fetch()) rg_json(['error'=>'Email already registered'],409);

  $hash = password_hash($pass, PASSWORD_DEFAULT);
  $pdo->prepare('INSERT INTO users(name,email,password_hash,avatar_url,points) VALUES (?,?,?,?,0)')
      ->execute([$name,$email,$hash,'https://i.pravatar.cc/100?u='.urlencode($email)]);
  $uid = (int)$pdo->lastInsertId();
  $token = rg_make_token(['uid'=>$uid,'email'=>$email,'exp'=>time()+RG_TOKEN_TTL]);
  rg_json(['token'=>$token,'user'=>['id'=>$uid,'name'=>$name,'email'=>$email]]);
}

/* === Auth: Login === */
if ($path === '/api/auth/login' && $method === 'POST') {
  $b = rg_body();
  $email = strtolower(trim($b['email'] ?? ''));
  $pass = $b['password'] ?? '';
  if(!$email || !$pass) rg_json(['error'=>'Missing credentials'],422);
  $st = $pdo->prepare('SELECT id,password_hash,name,avatar_url,points FROM users WHERE email=?');
  $st->execute([$email]); $u = $st->fetch();
  if(!$u || !password_verify($pass, $u['password_hash'])) rg_json(['error'=>'Invalid credentials'],401);
  $token = rg_make_token(['uid'=>$u['id'],'email'=>$email,'exp'=>time()+RG_TOKEN_TTL]);
  rg_json(['token'=>$token,'user'=>['id'=>$u['id'],'name'=>$u['name'],'email'=>$email,'avatar_url'=>$u['avatar_url'],'points'=>$u['points']]]);
}

/* === User actual === */
if ($path === '/api/users/me' && $method === 'GET') {
  $u = rg_require_auth();
  $u['points'] = rg_user_points((int)$u['id']);
  rg_json(['user'=>$u]);
}

/* === Crear log === */
if ($path === '/api/logs' && $method === 'POST') {
  $u = rg_require_auth();
  $b = rg_body();
  $kg = (float)($b['weight_kg'] ?? 0);
  $mat = trim($b['material'] ?? 'mixed');
  if($kg <= 0) rg_json(['error'=>'Invalid weight'],422);
  $st = $pdo->prepare('INSERT INTO recycle_logs(user_id,material,weight_kg,created_at) VALUES (?,?,?,NOW())');
  $st->execute([$u['id'],$mat,$kg]);
  rg_user_points((int)$u['id']); rg_award_medals_if_any((int)$u['id']);
  rg_json(['ok'=>true]);
}

/* === Listar mis logs === */
if ($path === '/api/logs' && $method === 'GET') {
  $u = rg_require_auth();
  $st = $pdo->prepare('SELECT id,material,weight_kg,created_at FROM recycle_logs WHERE user_id=? ORDER BY created_at DESC LIMIT 20');
  $st->execute([$u['id']]);
  rg_json(['logs'=>$st->fetchAll()]);
}

/* === Stats resumen === */
if ($path === '/api/stats/summary' && $method === 'GET') {
  $u = rg_require_auth();
  $st=$pdo->prepare('SELECT COALESCE(SUM(weight_kg),0) m FROM recycle_logs WHERE user_id=? AND YEAR(created_at)=YEAR(NOW()) AND MONTH(created_at)=MONTH(NOW())');
  $st->execute([$u['id']]); $month=(float)$st->fetch()['m'];
  $st=$pdo->prepare('SELECT COALESCE(SUM(weight_kg),0) t FROM recycle_logs WHERE user_id=?'); $st->execute([$u['id']]);
  $total=(float)$st->fetch()['t'];
  $st=$pdo->prepare('SELECT material, ROUND(SUM(weight_kg),2) w FROM recycle_logs WHERE user_id=? GROUP BY material ORDER BY w DESC');
  $st->execute([$u['id']]); $by=$st->fetchAll();
  $points = rg_user_points((int)$u['id']);
  rg_json(['month_kg'=>$month,'total_kg'=>$total,'by_material'=>$by,'points'=>$points]);
}

/* === Medallas === */
if ($path === '/api/medals' && $method === 'GET') {
  $u = rg_require_auth();
  $st=$pdo->prepare('SELECT m.id,m.name,m.icon,m.threshold_kg,(um.medal_id IS NOT NULL) earned,um.awarded_at
                     FROM medals m LEFT JOIN user_medals um
                     ON um.medal_id=m.id AND um.user_id=? ORDER BY m.threshold_kg ASC');
  $st->execute([$u['id']]);
  rg_json(['medals'=>$st->fetchAll()]);
}

/* === Leaderboard === */
if ($path === '/api/leaderboard' && $method === 'GET') {
  $st = $pdo->query('SELECT u.id,u.name,u.avatar_url, ROUND(COALESCE(SUM(l.weight_kg),0),2) total_kg
                     FROM users u LEFT JOIN recycle_logs l ON l.user_id=u.id
                     GROUP BY u.id ORDER BY total_kg DESC LIMIT 10');
  rg_json(['leaders'=>$st->fetchAll()]);
}

/* === Tip del día === */
if ($path === '/api/tips/random' && $method === 'GET') {
  $st = $pdo->query('SELECT id,text FROM tips ORDER BY RAND() LIMIT 1');
  $tip = $st->fetch() ?: ['id'=>0,'text'=>'Recycling 1 bottle saves enough energy to light a room for 3 hours.'];
  rg_json(['tip'=>$tip]);
}

/* === 404 === */
rg_json(['error'=>'Not Found','path'=>$path,'method'=>$method],404);
