# Repository Status - Feed Blogroll Plugin

## État actuel

- **Dépôt distant** : GitHub (https://github.com/jaz-on/feed-blogroll.git)
- **`main`** : branche stable ; releases versionnées (tags `v*`) ; en-tête plugin `Primary Branch: main` pour Git Updater.
- **`dev`** : intégration continue ; pour suivre cette branche avec Git Updater, configurer la branche `dev` dans l’admin WordPress (l’en-tête du plugin reste `Primary Branch: main`).
- **Dernier alignement version** : **1.2.0** (header WordPress, constante, `block.json`, `plugin.json`)

## Tags de version

- **v1.1.0** : Git Updater (branches `main` / `dev`), CI PHPCS, `.gitattributes` pour archives propres, documentation branches.
- **v1.0.x** (historique) : versions documentées antérieurement dans l’historique Git (CHANGELOG/refactor).

## Structure du projet

```
feed-blogroll/
├── .distignore             # Fichiers exclus du packaging manuel
├── .gitattributes          # Fichiers exclus de `git archive` (ZIP release)
├── CHANGELOG.md
├── README.md
├── LICENSE
├── feed-blogroll.php
├── block.json
├── composer.json
├── phpcs.xml
├── includes/
├── assets/
├── languages/
└── templates/
```

## Synchronisation

- Pousser `dev` et `main` vers `origin` après chaque changement utile aux sites branchés sur GitHub.
- **Git Updater** : incrémenter le numéro `Version` dans le header du plugin pour qu’une nouvelle version apparaisse dans le tableau de bord WordPress.

## Dernière vérification

- **Date** : 2026-04-18
- **Version catalogue** : 1.1.0
