<?php
// Outils communs de la mesure d'audience (mesure.php, statistiques.php).
const MESURE_HOTES = ['snowshoe.fr'];
const MESURE_SLUG = 'snowshoe';
const MESURE_GARDER_JOURS = 400;
const MESURE_MAX_CLES = 3000;
date_default_timezone_set('Europe/Paris');

// Dossier des compteurs : de préférence HORS du dossier public ; sinon dossier protégé.
function mesure_dossier() {
    static $dos = null;
    if ($dos !== null) { return $dos; }
    foreach ([dirname(__DIR__) . '/donnees-mesure-' . MESURE_SLUG, __DIR__ . '/_donnees-mesure'] as $d) {
        if (!is_dir($d)) { @mkdir($d, 0700, true); }
        if (is_dir($d) && is_writable($d)) {
            if (!is_file($d . '/.htaccess')) { @file_put_contents($d . '/.htaccess', "Require all denied\nDeny from all\n"); }
            if (!is_file($d . '/index.html')) { @file_put_contents($d . '/index.html', ''); }
            return $dos = $d;
        }
    }
    return $dos = '';
}

// Page lue : adresse d'une page qui existe vraiment sur le site (fichier .html ou dossier avec index.html), sinon null.
function mesure_page($p) {
    $p = substr($p, 0, 160);
    if ($p === '/' || $p === '/index.html') { return '/'; }
    $p = rawurldecode(preg_replace('/(\.html|\/)$/', '', $p));
    if (!preg_match('/^(\/[\p{L}0-9][\p{L}0-9\-_]{0,100}){1,4}$/u', $p)) { return null; }
    return (is_file(__DIR__ . $p . '.html') || is_file(__DIR__ . $p . '/index.html') || is_file(__DIR__ . $p)) ? $p : null;
}

// Origine d'une visite : moteur de recherche, réseau social, autre site ou accès direct.
function mesure_origine($o) {
    $o = strtolower(substr($o, 0, 80));
    if ($o === 'direct' || $o === 'interne') { return $o; }
    if (!preg_match('/^[a-z0-9][a-z0-9.\-]{1,78}$/', $o)) { return 'direct'; }
    $h = preg_replace('/^(www|m|l|lm|out)\./', '', $o);
    if (in_array($h, MESURE_HOTES, true)) { return 'interne'; }
    $familles = [
        'Google' => '/(^|\.)google\.[a-z.]+$|googleusercontent|android-app/',
        'Bing' => '/(^|\.)bing\.com$/',
        'Ecosia' => '/(^|\.)ecosia\.org$/',
        'Qwant' => '/(^|\.)qwant\.com$/',
        'DuckDuckGo' => '/(^|\.)duckduckgo\.com$/',
        'Yahoo' => '/(^|\.)yahoo\./',
        'Facebook' => '/(^|\.)facebook\.com$|(^|\.)fb\.me$/',
        'Instagram' => '/(^|\.)instagram\.com$/',
        'LinkedIn' => '/(^|\.)linkedin\.com$|(^|\.)lnkd\.in$/',
        'WhatsApp' => '/whatsapp/',
        'Doctolib' => '/(^|\.)doctolib\./',
        'ChatGPT et assistants' => '/chatgpt\.com$|openai\.com$|perplexity\.ai$|claude\.ai$|gemini\.google/',
    ];
    foreach ($familles as $nom => $re) { if (preg_match($re, $h)) { return $nom; } }
    return $h;
}

function mesure_inc(&$tab, $cle) {
    if (!is_array($tab)) { $tab = []; }
    if (!isset($tab[$cle]) && count($tab) >= MESURE_MAX_CLES) { $cle = '(autres)'; }
    $tab[$cle] = (isset($tab[$cle]) ? $tab[$cle] : 0) + 1;
}

// Ajoute au fichier du jour, sous verrou (plusieurs visiteurs en même temps).
function mesure_ajouter($fn) {
    $d = mesure_dossier();
    if ($d === '') { return; }
    $f = $d . '/' . date('Y-m-d') . '.json';
    $neuf = !is_file($f);
    $h = @fopen($f, 'c+');
    if (!$h) { return; }
    if (flock($h, LOCK_EX)) {
        $j = json_decode((string)stream_get_contents($h), true);
        if (!is_array($j)) { $j = []; }
        $fn($j);
        ftruncate($h, 0); rewind($h);
        fwrite($h, json_encode($j, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        fflush($h); flock($h, LOCK_UN);
    }
    fclose($h);
    if ($neuf) {
        @chmod($f, 0600);
        foreach ((array)glob($d . '/*.json') as $g) {
            if (filemtime($g) < time() - MESURE_GARDER_JOURS * 86400) { @unlink($g); }
        }
    }
}

// Lecture : les N derniers jours (aujourd'hui compris), du plus ancien au plus récent.
// $dossier : dossier de compteurs d'un site (par défaut celui de ce site).
function mesure_jours($n, $dossier = null) {
    $d = $dossier === null ? mesure_dossier() : $dossier;
    $res = [];
    for ($i = $n - 1; $i >= 0; $i--) {
        $jour = date('Y-m-d', strtotime("-$i day"));
        $f = $d === '' ? '' : $d . '/' . $jour . '.json';
        $j = ($f !== '' && is_file($f)) ? json_decode((string)file_get_contents($f), true) : null;
        $res[$jour] = is_array($j) ? $j : [];
    }
    return $res;
}

// Dossier de compteurs d'un autre site hébergé au même endroit : ['slug' => ['Nom', 'dossier sur l'hébergement']].
function mesure_sites() {
    return ['psypsy' => ['psy-psy.fr', 'psy-psy-git'], 'bilan' => ['bilan-mdph.fr', 'bilan-mdph-git'], 'burnout' => ['burnout-parental.fr', 'burnout-parental']];
}
function mesure_dossier_site($slug) {
    $sites = mesure_sites();
    if (!isset($sites[$slug])) { return ''; }
    $racine = dirname(__DIR__);
    foreach ([$racine . '/donnees-mesure-' . $slug, $racine . '/' . $sites[$slug][1] . '/_donnees-mesure'] as $d) {
        if (is_dir($d)) { return $d; }
    }
    return '';
}
