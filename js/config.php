<?php
/**
 * Site configuration served as JavaScript.
 *
 * This file reads all sensitive values from .env on the server and outputs
 * them as JavaScript constants. It is the only place config lives — nothing
 * is hardcoded in HTML or committed to git.
 *
 * Loaded in HTML as: <script src="js/config.php"></script>
 */

header('Content-Type: application/javascript; charset=utf-8');
header('Cache-Control: no-store, must-revalidate');
header('X-Robots-Tag: noindex');
header('X-Content-Type-Options: nosniff');

// Load .env from the project root (one level up from this js/ directory)
$env_loader = __DIR__ . '/../env-loader.php';
if (file_exists($env_loader)) {
    require_once $env_loader;
    load_env();
}

// Define the keys to expose — only what the front-end actually needs.
// Values come from .env; never hardcode anything below.
$config = [
    'TECHLAKE_FORM_KEY' => getenv('TECHLAKE_FORM_KEY') ?: '',
    'CRIBFIN_FORM_KEY'  => getenv('CRIBFIN_FORM_KEY')  ?: '',
    // WhatsApp: digits only, country code included, no + (e.g. 447700900000)
    'CRIBFIN_WHATSAPP'  => getenv('CRIBFIN_WHATSAPP')  ?: '',
    'CONTACT_EMAIL'     => getenv('CONTACT_EMAIL')      ?: '',
    'CRIBFIN_EMAIL'     => getenv('CRIBFIN_EMAIL')      ?: '',
];

// Output each value as a top-level const.
// json_encode() safely escapes all characters — no XSS risk.
foreach ($config as $name => $value) {
    printf("const %s = %s;\n", $name, json_encode((string) $value));
}
?>
