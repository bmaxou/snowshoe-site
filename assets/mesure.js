/* Mesure d'audience du site : chiffres d'ensemble, sans cookie ni stockage sur l'appareil du visiteur.
   Envoie à /mesure.php (même site) la page lue, l'origine de la visite, le type d'appareil et quelques actions.
   Aucune donnée n'est envoyée à un service extérieur. Voir mesure.php et statistiques.php. */
(function () {
  var B = '/mesure.php';
  function envoi(o) {
    try {
      var s = JSON.stringify(o);
      if (navigator.sendBeacon && navigator.sendBeacon(B, new Blob([s], { type: 'text/plain' }))) { return; }
      var x = new XMLHttpRequest(); x.open('POST', B, true); x.send(s);
    } catch (e) {}
  }
  function hote(u) {
    try { return new URL(u, location.href).hostname.replace(/^www\./, '').toLowerCase(); } catch (e) { return ''; }
  }
  var ici = hote(location.href);
  var r = document.referrer ? hote(document.referrer) : '';
  var origine = !r ? 'direct' : (r === ici ? 'interne' : r);
  var mobile = /Mobi|Android|iPhone|iPad/i.test(navigator.userAgent) || (window.matchMedia && matchMedia('(max-width: 760px)').matches);
  envoi({ t: 'v', p: location.pathname, o: origine, a: mobile ? 'mobile' : 'ordinateur' });

  function action(nom) { envoi({ t: 'e', p: location.pathname, n: String(nom).toLowerCase() }); }
  window.mesureAction = action;

  // contacts directs
  document.addEventListener('click', function (e) {
    var a = e.target && e.target.closest ? e.target.closest('a[href]') : null;
    if (!a) { return; }
    var h = a.getAttribute('href') || '';
    if (/^tel:/i.test(h)) { action('contact:telephone'); }
    else if (/^mailto:/i.test(h)) { action('contact:e-mail'); }
    else if (/^sms:/i.test(h)) { action('contact:sms'); }
    else if (/^https?:/i.test(h)) {
      var x = hote(h);
      if (x && x !== ici) { action(/(^|\.)doctolib\./.test(x) ? 'rendez-vous:doctolib' : 'sortie:' + x); }
    }
  }, true);

  // page de passage : site extérieur ouvert (Doctolib = prise de rendez-vous)
  if (/^\/passage(\.html)?$/.test(location.pathname)) {
    setTimeout(function () {
      var go = document.getElementById('pas-go');
      if (!go || go.hidden) { return; }
      var h = hote(go.href);
      if (h) { action(/doctolib\./.test(h) ? 'rendez-vous:doctolib' : 'sortie:' + h); }
    }, 300);
  }

  // quiz des dossiers terminé : le résultat (.td-result) apparaît, comme pour les particules de tdah.js
  var racine = document.getElementById('td-root');
  if (racine && 'MutationObserver' in window) {
    new MutationObserver(function (muts) {
      muts.forEach(function (m) {
        Array.prototype.forEach.call(m.addedNodes, function (n) {
          if (n.nodeType === 1 && (n.matches('.td-result') || (n.querySelector && n.querySelector('.td-result')))) { action('quiz-termine'); }
        });
      });
    }).observe(racine, { childList: true, subtree: true });
  }
})();
