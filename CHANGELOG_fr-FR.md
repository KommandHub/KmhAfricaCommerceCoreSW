# 0.1.0

Première version.

- Import des données de référence, depuis la configuration du plugin (bouton « Run reference-data import ») ou avec `bin/console africa:reference:import` : active 26 pays africains, ajoute 443 régions/États ISO 3166-2 et fixe les décimales ISO 4217 de 18 devises africaines (XOF, XAF, RWF, UGX et d'autres à 0 décimale, TND à 3).
- Règles d'adresse par pays appliquées comme données : le Ghana n'exige plus de code postal, le Nigeria affiche le champ État à l'inscription.
- L'import peut être relancé sans risque : un second passage ne change rien. Les devises non installées sont listées dans la colonne « Missing » et jamais créées, car une devise nécessite votre vrai taux de change ; créez-les dans Paramètres > Devises, puis relancez l'import.
- Champ « Remplacé manuellement » sur les pays, devises, États et divisions : coché, l'import ne modifie jamais cet enregistrement.
- Divisions locales optionnelles sous l'État (par exemple les LGA et quartiers du Nigeria), avec une liste déroulante en cascade « Division locale » dans les formulaires d'adresse de la boutique. Inclut un exemple pour Lagos.
- Champs d'adresse supplémentaires optionnels à l'inscription et dans le compte client : point de repère, zone/quartier, indications, code d'adresse numérique et division locale. Les valeurs enregistrées sont conservées quand un client modifie l'adresse sans envoyer ces champs.
- Vérification optionnelle du numéro de téléphone en direct dans les formulaires d'adresse : le numéro s'affiche au format international, ou un avertissement apparaît s'il semble invalide. La commande n'est jamais bloquée.
- Points d'accès Store API pour les boutiques headless : `GET /store-api/kmh-af/divisions/{countryIso}` et `POST /store-api/kmh-af/phone/normalize`.
- Chaque fonctionnalité a son propre interrupteur dans la configuration du plugin, par canal de vente.
- Réglage « Country rules » pour la profondeur de divisions attendue, utilisée par le validateur d'adresse pour suggérer le choix d'une division.
- Journalisation de débogage optionnelle, par canal de vente.
- Une désinstallation sans conservation des données supprime les tables et champs propres au plugin ; pays, États et devises restent en place, les commandes et adresses existantes ne sont pas affectées.
- Boutique en anglais, allemand et français ; administration en anglais et allemand. Compatible avec Shopware 6.6 et 6.7.
