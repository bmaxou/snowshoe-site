<?php
// Mesure d'audience : reçoit les envois de outils/mesure.js et additionne des compteurs par jour.
// Aucun cookie, aucune adresse IP ni identifiant conservé : seulement des totaux (pages, origines, appareils, actions).
require __DIR__ . '/mesure-lib.php';
header('Cache-Control: no-store');
header('X-Robots-Tag: noindex, nofollow');

function fin_mesure() { http_response_code(204); exit; }

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { fin_mesure(); }
$ua = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '';
if ($ua === '' || preg_match('/bot|crawl|spider|slurp|preview|headless|lighthouse|python|curl|wget/i', $ua)) { fin_mesure(); }
// envois venant d'une page du site seulement
$ref = isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : (isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '');
$hr = strtolower((string)parse_url($ref, PHP_URL_HOST));
if (!in_array(preg_replace('/^www\./', '', $hr), MESURE_HOTES, true)) { fin_mesure(); }

$corps = file_get_contents('php://input', false, null, 0, 2048);
$d = json_decode((string)$corps, true);
if (!is_array($d) || !isset($d['t'], $d['p'])) { fin_mesure(); }
$page = mesure_page((string)$d['p']);
if ($page === null) { fin_mesure(); }

if ($d['t'] === 'v') {
    $o = isset($d['o']) ? mesure_origine((string)$d['o']) : 'direct';
    $a = (isset($d['a']) && $d['a'] === 'mobile') ? 'mobile' : 'ordinateur';
    mesure_ajouter(function (&$j) use ($page, $o, $a) {
        $j['vues'] = (isset($j['vues']) ? $j['vues'] : 0) + 1;
        mesure_inc($j['pages'], $page);
        if ($o !== 'interne') {
            // arrivée sur le site : compte comme une visite
            $j['visites'] = (isset($j['visites']) ? $j['visites'] : 0) + 1;
            mesure_inc($j['origines'], $o);
            mesure_inc($j['appareils'], $a);
            mesure_inc($j['entrees'], $page);
        }
    });
} elseif ($d['t'] === 'e' && isset($d['n']) && preg_match('/^[a-z0-9][a-z0-9:.\-]{0,59}$/', (string)$d['n'])) {
    $n = (string)$d['n'];
    mesure_ajouter(function (&$j) use ($page, $n) {
        mesure_inc($j['actions'], $n);
        mesure_inc($j['actions_pages'], $n . ' ← ' . $page);
    });
}
fin_mesure();
