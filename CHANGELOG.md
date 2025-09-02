# Changelog

Tous les changements notables de ce projet seront documentés dans ce fichier.

## [1.0.0] - 2025-01-27

### Ajouté
- Intégration complète avec l'API Feedbin pour la synchronisation automatique des blogrolls
- Type de post personnalisé 'blogroll' avec champs ACF
- Interface d'administration complète avec tableau de bord et paramètres
- Shortcodes `[blogroll]` et `[blogroll_grid]` pour l'affichage
- Bloc WordPress natif avec support des attributs personnalisables
- Export OPML pour sauvegarde et partage
- Support des catégories et tags pour l'organisation
- API REST pour l'accès programmatique aux données
- Synchronisation automatique via cron WordPress
- Interface responsive avec grille adaptative

### Sécurité
- Vérification des nonces pour toutes les actions AJAX
- Contrôles de capacités utilisateur
- Sanitisation et validation des données
- Support des constantes wp-config.php pour les identifiants

### Performance
- Système de cache optimisé pour les requêtes
- Chargement conditionnel des assets
- Requêtes de base de données optimisées
- Support du cache OPML

### Accessibilité
- Conformité WCAG 2.1 AA
- Support des lecteurs d'écran
- Navigation au clavier
- Attributs ARIA appropriés
- Structure sémantique HTML

### Technique
- Architecture modulaire avec séparation des préoccupations
- Support PHP 8.2+
- Standards de codage WordPress
- Documentation complète
- Tests et validation automatiques

## [0.1.0] - 2025-01-20

### Ajouté
- Version initiale du plugin
- Structure de base et architecture
- Support des fonctionnalités essentielles
