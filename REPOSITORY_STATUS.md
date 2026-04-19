# Repository Status - Feed to Blogroll Plugin

## État actuel

- **Dépôt distant** : GitHub (https://github.com/jaz-on/feed-to-blogroll.git)
- **`main`** : branche stable ; releases versionnées (tags `v*`) ; en-tête plugin `Primary Branch: main` pour Git Updater.
- **`dev`** : intégration continue ; en-tête plugin `Primary Branch: dev` pour tester les mises à jour pré-release via Git Updater.
- **Dernier alignement version** : **1.1.0** (header WordPress, constante, `block.json`, `plugin.json`)

## Tags de version

- **v1.1.0** : Git Updater (branches `main` / `dev`), CI PHPCS, `.gitattributes` pour archives propres, documentation branches.
- **v1.0.x** (historique) : versions documentées antérieurement dans l’historique Git (CHANGELOG/refactor).

## Structure du projet

```
feed-to-blogroll/
├── .distignore             # Fichiers exclus du packaging manuel
├── .gitattributes          # Fichiers exclus de `git archive` (ZIP release)
├── CHANGELOG.md
├── README.md
├── LICENSE
├── feed-to-blogroll.php
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
