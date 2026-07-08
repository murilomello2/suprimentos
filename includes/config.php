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

// ============================================================
// Solicitações de Compra (Supabase alimentado pelo Power Automate/TOTVS).
// SOMENTE LEITURA (RLS anon). Fila item-a-item em `solicitacoes_fila`.
// ============================================================
define('SOLIC_SUPABASE_URL', 'https://casfxrtkhcbbexoagxvd.supabase.co');
define('SOLIC_SUPABASE_KEY', getenv('SOLIC_SUPABASE_KEY') ?: 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6ImNhc2Z4cnRraGNiYmV4b2FneHZkIiwicm9sZSI6ImFub24iLCJpYXQiOjE3NzYyNTQ4ODMsImV4cCI6MjA5MTgzMDg4M30.-CwNK8yALyjqrNgglT9lNggw32EIYHjnuPhOqw70RQQ');

// Caminho da base local (SQLite interina; portará para MySQL quando o TI liberar).
define('DB_PATH', __DIR__ . '/../data/cockpit.sqlite');
define('TOKEN_CACHE', __DIR__ . '/../data/.token_cache.json');
define('SEED_DIR', __DIR__ . '/../data/seed');

// Driver do banco: LOCAL (preview/localhost/CLI com DB_DRIVER=sqlite) usa SQLite como sandbox;
// SERVIDOR usa MySQL. Decidido ANTES do secrets (secrets só traz credenciais) e robusto a deploy
// parcial: se só o secrets subir, ele mesmo define 'mysql' por padrão; se só o config subir, o
// secrets antigo já define 'mysql'. Nunca cai pra sqlite no servidor por acidente.
if (!defined('DB_DRIVER')) {
    $__local = (isset($_SERVER['HTTP_HOST']) && preg_match('#^(localhost|127\.0\.0\.1|\[::1\])#', $_SERVER['HTTP_HOST']))
            || getenv('DB_DRIVER') === 'sqlite';
    if ($__local) define('DB_DRIVER', 'sqlite');
}
// Segredos fora do git (MySQL do TI etc.). Carrega se presente (define DB_DRIVER='mysql' se ainda não veio).
@include __DIR__ . '/secrets.php';
if (!defined('DB_DRIVER')) define('DB_DRIVER', 'sqlite');
