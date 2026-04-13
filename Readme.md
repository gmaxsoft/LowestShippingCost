# lowestshipping (PrestaShop 9)

Moduł wyświetla na **karcie produktu** szacunek **najniższego kosztu dostawy** dla bieżącego produktu i kombinacji, korzystając z **natywnych mechanizmów koszyka i przewoźników** PrestaShop (`Cart::getDeliveryOptionList` po krótkotrwałej symulacji koszyka).

## Wymagania

- PrestaShop **9.0+**
- PHP **8.1+**
- Composer (w katalogu **`lowestshipping/`** — autoload i narzędzia dev)

## Instalacja

1. Skopiuj folder **`lowestshipping/`** (zawartość repozytorium to katalog modułu) do `modules/lowestshipping/` w katalogu sklepu.
2. W katalogu modułu (`modules/lowestshipping/`) uruchom:
   ```bash
   composer install --no-dev --no-interaction --prefer-dist -o
   ```
   (w środowisku developerskim z testami: `composer install`.)
3. W back office: **Moduły → Menedżer modułów** — znajdź **Lowest shipping estimate**, zainstaluj i włącz.
4. Otwórz **Konfiguruj** — zostaniesz przekierowany na stronę ustawień Symfony.

## Konfiguracja (multistore)

Ustawienia zapisuje standardowy mechanizm `Configuration` w kontekście **aktywnego sklepu** w BO. Przy multistore przełącz sklep w nagłówku BO i zapisz formularz osobno dla każdej konfiguracji.

Dostępne pola:

- **Domyślny kraj** — dla gości i klientów bez adresu dostawy.
- **Koszt z podatkiem** — przełącznik.
- **Prefiks przed ceną** — np. `Od `.
- **Dodatkowy opis pod ceną** — opcjonalny tekst pod kwotą.
- **Włącz na karcie produktu** — globalne wyłączenie bloku na produkcie.

## Działanie

1. Hook **`displayProductAdditionalInfo`** renderuje blok (Smarty: `views/templates/hook/displayproductadditionalinfo.tpl`).
2. Metoda **`getLowestShippingCost`** tworzy tymczasowy rekord koszyka, dodaje linię z produktem (ilość, kombinacja), ustawia adres (zalogowany: pierwszy adres dostawy; inaczej kraj z konfiguracji), wywołuje **`CartRule::autoAddToCart`** oraz **`getDeliveryOptionList`**, po czym usuwa koszyk i ewentualny tymczasowy adres.
3. Wybierana jest **najtańsza opcja dostawy** z listy (zgodnie z regułami przewoźników, stref, wag, cen, modułów podpiętych pod te same hooki co checkout).
4. JavaScript **`views/js/lowestshipping.js`** nasłuchuje `updatedProduct` / `updateProduct` i odświeża cenę przez front controller **`ajax`** (JSON).

## Ograniczenia

- Pełna zgodność z każdą regułą koszyka „darmowa dostawa” zależy od tego, czy reguła jest automatycznie dodawana do koszyka symulacji (jak w checkout).
- Szablon karty produktu to **Smarty** (standard FO PrestaShop); rozszerzenie `.tpl` przy nazwie pliku jest wymagane przez `Module::fetch()`.

## Testy

[![PHPUnit](https://github.com/OWNER/REPO/actions/workflows/phpunit.yml/badge.svg)](https://github.com/OWNER/REPO/actions/workflows/phpunit.yml)

W klonie repozytorium przejdź do katalogu modułu i zainstaluj zależności:

```bash
cd lowestshipping
composer install
```

Skrót (wszystkie zestawy zdefiniowane w `phpunit.xml.dist`):

```bash
composer test
```

Styl kodu (PHP CS Fixer, PER-CS2.0 + reguły phpdoc zbliżone do PSR-5). Konfiguracja leży w **katalogu nadrzędnym** repozytorium (`.php-cs-fixer.dist.php`); skrypty `composer` z modułu ją ładują automatycznie.

```bash
composer cs-check   # tylko podgląd
composer cs-fix     # zapis poprawek
```

Analiza statyczna (**PHPStan** przez [prestashop/php-dev-tools](https://github.com/PrestaShop/php-dev-tools)) — wymaga **działającej kopii PrestaShop** i zmiennej **`_PS_ROOT_DIR_`** wskazującej na katalog sklepu (ten sam co przy testach integracyjnych: katalog z `config/config.inc.php`):

```bash
# PowerShell
$env:_PS_ROOT_DIR_ = "C:\sciezka\do\prestashop"
composer phpstan

# Linux / macOS
export _PS_ROOT_DIR_=/ścieżka/do/prestashop
composer phpstan
```

Skrypt `composer phpstan:init` odtwarza domyślny szablon `phpstan.neon` z pakietu (obecna konfiguracja w repozytorium jest już dostosowana pod ten moduł).

Testy jednostkowe:

```bash
./vendor/bin/phpunit -c phpunit.xml.dist --testsuite Unit
```

Testy integracyjne (wymagają działającej bazy oraz kopii modułu w `{PRESTASHOP_ROOT}/modules/lowestshipping/`; przed uruchomieniem ustaw zmienną środowiskową `PRESTASHOP_ROOT` na katalog sklepu z plikiem `config/config.inc.php`):

```bash
./vendor/bin/phpunit -c phpunit.xml.dist --testsuite Integration
```

W powyższym badge należy zamienić `OWNER/REPO` na właściwą ścieżkę repozytorium GitHub.

## Licencja

[MIT](lowestshipping/LICENSE) — Copyright (c) 2026 Maxsoft.
Kontakt: www.maxsoft.pl
