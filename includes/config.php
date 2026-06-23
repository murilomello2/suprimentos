<?php
// ============================================================
// Webhook Bitrix24 — APENAS aqui (camada servidor / PHP).
// NUNCA expor no front-end (JS/HTML servido ao navegador).
// ============================================================
define('BITRIX_WEBHOOK', 'https://caprem.tech/rest/20/i8u0l05ctu1qf1w1/');

// ============================================================
// Credenciais do Aplicativo Local Bitrix24 (fornecidas pelo TI
// após o registro). Usadas no fluxo OAuth do install.php.
// ============================================================
define('BITRIX_CLIENT_ID',     'local.6a30601e1cf1c4.75983551');
define('BITRIX_CLIENT_SECRET', 'oHysqaDAIQdjxDtbS56A5ILSRwLXhvqPIc5KzE4qd2IqEc49OT');

// ============================================================
// Cockpit de Obras (Supabase) — SOMENTE LEITURA (planejamento).
// A senha do usuário de serviço NUNCA pode ir ao front-end.
// Em produção, prefira ler de variável de ambiente / .env.
// ============================================================
define('SUPABASE_URL',      'https://jeqiitobuplxoezsegis.supabase.co');
define('SUPABASE_ANON_KEY', 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6ImplcWlpdG9idXBseG9lenNlZ2lzIiwicm9sZSI6ImFub24iLCJpYXQiOjE3NTU4NzQwNjMsImV4cCI6MjA3MTQ1MDA2M30.AMawowrp_vuwQk4njwblK8iXgZxVC6DEVHHbroCPDBo');
define('SUPABASE_SVC_EMAIL','api-externo@caprem.com.br');
// Senha: usa env CAPREM_API_PASSWORD se existir; senão cai no literal (rotacionar depois).
define('CAPREM_API_PASSWORD', getenv('CAPREM_API_PASSWORD') ?: 'CapremApi#2026!vQz9LpXr');

// Caminho da base local (SQLite interina; portará para MySQL quando o TI liberar).
define('DB_PATH', __DIR__ . '/../data/cockpit.sqlite');
define('TOKEN_CACHE', __DIR__ . '/../data/.token_cache.json');
define('SEED_DIR', __DIR__ . '/../data/seed');
