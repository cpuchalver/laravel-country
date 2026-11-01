# cpuchalver/laravel-country

Modèle `Country`, migration, seeder et commandes Artisan pour Laravel 12 et 13, alimentés par l'API [REST Countries v5](https://restcountries.com/docs/countries).

Chaque pays stocke : codes ISO 3166-1 (alpha-2, alpha-3, numérique), nom, région, capitale, indicatif téléphonique et drapeau (emoji, PNG, SVG).

## Prérequis

- PHP 8.2+ (8.3+ pour Laravel 13)
- Laravel 12 ou 13
- Une clé API REST Countries (gratuite) : <https://restcountries.com/sign-up>

## Installation

```bash
composer require cpuchalver/laravel-country
```

Ajoutez la clé dans `.env` :

```dotenv
COUNTRY_API_KEY=rc_live_xxxxxxxxxxxxxxxx
```

Puis, en une commande (publie la config, migre, synchronise) :

```bash
php artisan country:install --sync
```

Ou étape par étape :

```bash
php artisan vendor:publish --tag=country-config   # optionnel
php artisan migrate
php artisan country:sync
```

## Configuration

`config/country.php` :

| Clé | Défaut | Rôle |
|---|---|---|
| `api.base_url` | `https://api.restcountries.com/countries/v5` | URL de l'API (`COUNTRY_API_URL`) |
| `api.key` | `env('COUNTRY_API_KEY')` | Clé API, envoyée en `Authorization: Bearer` |
| `api.timeout` | `15` | Délai en secondes (`COUNTRY_API_TIMEOUT`) |
| `api.retry.times` / `api.retry.sleep` | `3` / `1000` ms | Nouvelles tentatives sur erreur réseau, 429 et 5xx |
| `api.per_page` | `100` | Taille de page (1–100 en plan gratuit, jusqu'à 500 en payant) |
| `table` | `countries` | Nom de la table |
| `model` | `Cpuchalver\LaravelCountry\Models\Country` | Modèle utilisé (étendez celui du package) |
| `load_migrations` | `true` | Charge la migration du package automatiquement |
| `sync.prune` | `false` | Supprime les pays absents de l'API lors d'une synchro complète |

Pour personnaliser la migration, publiez-la puis passez `load_migrations` à `false` :

```bash
php artisan vendor:publish --tag=country-migrations
```

## Commandes Artisan

| Commande | Description |
|---|---|
| `country:sync` | Synchronise tous les pays (crée / met à jour) |
| `country:sync --code=FR --code=DEU` | Synchronise seulement certains codes (alpha-2 ou alpha-3, séparables par des virgules) |
| `country:sync --prune` | Supprime aussi les pays qui ont disparu de l'API |
| `country:sync --dry-run` | Affiche les changements sans rien écrire |
| `country:status [--remote]` | Config, nombre de pays, date de dernière synchro ; `--remote` compare avec l'API |
| `country:show FR [--remote]` | Affiche un pays depuis la base (ou l'API) |
| `country:install [--migrate] [--sync] [--force]` | Publie la config, migre et synchronise |

`country:sync` renvoie un code de sortie non nul en cas d'erreur API ou de code introuvable, ce qui le rend utilisable dans un script ou un scheduler :

```php
// routes/console.php
use Illuminate\Support\Facades\Schedule;

Schedule::command('country:sync')->weekly();
```

## Seeder

```bash
php artisan db:seed --class="Cpuchalver\LaravelCountry\Database\Seeders\CountrySeeder"
```

ou depuis `DatabaseSeeder` :

```php
$this->call(\Cpuchalver\LaravelCountry\Database\Seeders\CountrySeeder::class);
```

## Utilisation du modèle

```php
use Cpuchalver\LaravelCountry\Models\Country;

$france = Country::findByCode('FR');    // ou 'FRA', insensible à la casse
$france->name;          // "France"
$france->capital;       // "Paris"
$france->dialing_code;  // "+33"
$france->flag_emoji;    // "🇫🇷"

Country::region('Europe')->orderBy('name')->get();
Country::code('JPN')->first();
```

Une factory est fournie pour vos tests : `Country::factory()->create()` (nécessite `fakerphp/faker`).

## Extension

- **Événement** : `Cpuchalver\LaravelCountry\Events\CountriesSynced` est émis après chaque synchronisation écrite, avec le `SyncResult` (`created`, `updated`, `unchanged`, `deleted`, `missing`).
- **Programmatique** : `app(CountrySynchronizer::class)->sync(codes: ['FR'], prune: false, dryRun: true)`.
- **Autre source de données** : implémentez `Cpuchalver\LaravelCountry\Contracts\CountryProvider` et liez-la dans le conteneur.

## Bon à savoir sur l'API

- La synchro complète coûte environ `nombre de pays / per_page` requêtes (≈ 3 avec 100 par page) sur votre quota mensuel ; le plan gratuit offre 1 000 requêtes/mois.
- Les [conditions d'utilisation](https://restcountries.com/docs#acceptable-use) de REST Countries limitent la mise en cache des données à trois jours et réservent le plan gratuit aux usages non commerciaux. Planifiez `country:sync` en conséquence et vérifiez que votre usage est couvert par votre plan.

## Tests

```bash
composer install
composer test
```

Les tests n'appellent jamais l'API réelle (`Http::fake()` + `preventStrayRequests`).

## Licence

MIT
