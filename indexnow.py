#!/usr/bin/env python3
"""Prévient Bing (IndexNow) des pages nouvelles ou modifiées de ce déploiement.

Lancé par GitHub Actions après l'envoi vers OVH (jamais depuis le navigateur des visiteurs).
Variables : INDEXNOW_HOST (ex. bilan-mdph.fr), INDEXNOW_KEY, BEFORE (commit précédent).
Une panne d'IndexNow ne bloque jamais la mise en ligne. Python standard uniquement.
"""
import json, os, re, subprocess, sys, urllib.request
from urllib.parse import urlparse

hote, cle, avant = os.environ['INDEXNOW_HOST'], os.environ['INDEXNOW_KEY'], os.environ.get('BEFORE', '')
# tant que snowshoe.fr n'est pas servi par ce site (cle absente en ligne), rien n'est envoye
if os.environ.get('INDEXNOW_VERIFIER_CLE'):
    try:
        with urllib.request.urlopen('https://%s/%s.txt' % (hote, cle), timeout=20) as r:
            ok = r.read().decode('utf-8', 'replace').strip() == cle
    except Exception:
        ok = False
    if not ok:
        print('IndexNow : %s ne sert pas encore ce site, rien a signaler' % hote)
        sys.exit(0)
urls = re.findall(r'<loc>([^<]+)</loc>', open('sitemap.xml', encoding='utf-8').read())

def modifies():
    if not re.fullmatch(r'[0-9a-f]{40}', avant) or set(avant) == {'0'}:
        return None
    r = subprocess.run(['git', 'diff', '--name-only', avant, 'HEAD'], capture_output=True, text=True)
    return set(r.stdout.split()) if r.returncode == 0 else None

def correspond(u, chg):
    """Une page est signalée si son fichier a changé (ou, pour les pages fabriquées, un fichier source du même nom)."""
    p = urlparse(u).path.strip('/')
    if p.endswith('.html') or not p:
        cand = {p or 'index.html'}
    else:
        cand = {p + '.html', p + '/index.html'}
    nom = p.rsplit('/', 1)[-1].removesuffix('.html')
    return any(f in cand or (nom and os.path.splitext(os.path.basename(f))[0] == nom) for f in chg)

def anciennes():
    """Pages déjà connues : plan du site actuellement en ligne (avant ce déploiement)."""
    try:
        with urllib.request.urlopen('https://%s/sitemap.xml' % hote, timeout=20) as r:
            return set(re.findall(r'<loc>([^<]+)</loc>', r.read().decode('utf-8')))
    except Exception:
        return None

chg = modifies()
vieux = anciennes()
if chg is None or vieux is None:
    envoi = urls  # premier déploiement, relance manuelle ou historique introuvable : tout le plan
else:
    envoi = [u for u in urls if u not in vieux or correspond(u, chg)]
if not envoi:
    print('IndexNow : aucune page à signaler')
    sys.exit(0)
envoi = envoi[:10000]
corps = json.dumps({'host': hote, 'key': cle, 'keyLocation': 'https://%s/%s.txt' % (hote, cle), 'urlList': envoi}).encode()
req = urllib.request.Request('https://api.indexnow.org/IndexNow', corps, {'Content-Type': 'application/json; charset=utf-8'})
try:
    with urllib.request.urlopen(req, timeout=30) as r:
        print('IndexNow : %d page(s) signalée(s), réponse %s' % (len(envoi), r.status))
except Exception as e:
    print('IndexNow : échec non bloquant (%s)' % e)
