#!/usr/bin/env python3
"""Ajoute le compteur de visites (sans cookie, voir assets/mesure.js) en fin de chaque page .html.

Lance par GitHub Actions avant l'envoi vers OVH : les pages nouvelles reçoivent le compteur sans rien faire.
Ignore la copie de validation, les copies de téléchargement du navigateur et les fichiers de vérification Google.
"""
import os, re

SCRIPT = '<script src="/assets/mesure.js" defer></script>'
IGNORER_DOSSIERS = {'.git', '.github', 'assets', 'css', 'images', 'img', '_t', 'node_modules'}
n = 0
for racine, dossiers, fichiers in os.walk('.'):
    dossiers[:] = sorted(d for d in dossiers if d not in IGNORER_DOSSIERS and not d.startswith('download'))
    for f in sorted(fichiers):
        # pages .html, et pages de libelles sans extension (search/label/Sante)
        ext = os.path.splitext(f)[1].lower()
        if ext not in ('.html', '') or f.startswith('.') or f.startswith('passage-') or re.match(r'google[0-9a-f]+\.html$', f):
            continue
        chemin = os.path.join(racine, f)
        try:
            t = open(chemin, encoding='utf-8').read()
        except UnicodeDecodeError:
            continue
        if SCRIPT in t or '</body>' not in t:
            continue
        k = t.rfind('</body>')
        open(chemin, 'w', encoding='utf-8').write(t[:k] + SCRIPT + '\n' + t[k:])
        n += 1
print(n, 'page(s) avec le compteur de visites')
