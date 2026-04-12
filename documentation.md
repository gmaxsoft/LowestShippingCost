# Dokumentacja modułu **lowestshipping**

> Moduł **PrestaShop 9** wyświetlający na **karcie produktu** szacunek **najniższego kosztu dostawy** z wykorzystaniem natywnych mechanizmów **Cart**, **Carrier** i **CartRule**.

**Spis treści**

1. [Nazwa modułu i opis](#1-nazwa-modułu-i-opis)
2. [Główne funkcje / Features](#2-główne-funkcje--features)
3. [Jak działa moduł](#3-jak-działa-moduł)
4. [Struktura folderów](#4-struktura-folderów)
5. [Użyte hooki](#5-użyte-hooki)
6. [Wzorce projektowe / Architektura](#6-wzorce-projektowe--architektura)
7. [Zabezpieczenia i dobre praktyki](#7-zabezpieczenia-i-dobre-praktyki)
8. [Konfiguracja w Back Office](#8-konfiguracja-w-back-office)
9. [Instalacja](#9-instalacja)
10. [Aktualizacja modułu](#10-aktualizacja-modułu)
11. [Znane problemy i ograniczenia](#11-znane-problemy-i-ograniczenia-known-issues--limitations)
12. [Edge cases](#12-edge-cases)
13. [Testy](#13-testy)
14. [Kompatybilność](#14-kompatybilność)
15. [Changelog](#15-changelog)
16. [Licencja](#16-licencja)
17. [Autor](#17-autor)

---

## 1. Nazwa modułu i opis

| | |
|---|---|
| **Technical name** | `lowestshipping` |
| **Wersja (kod)** | `2.1.0` (`lowestshipping.php`, `config.xml`) |
| **Autor** | Maxsoft |
| **Zakres wersji PrestaShop** | `9.0.0` – `9.99.99` (`ps_versions_compliancy`) |

**Opis (PL)**  
Moduł pokazuje klientowi na stronie produktu **orientacyjny, najniższy możliwy koszt dostawy** dla bieżącego produktu i **kombinacji** (atrybutów). Wycena nie jest „własnym kalkulatorem” modułu — korzysta z **tej samej ścieżki co checkout**: krótkotrwała symulacja **koszyka** (`Cart`), wywołanie **`Cart::getDeliveryOptionList`**, uwzględnienie **przewoźników** (**Carriers**), stref, wag, ograniczeń produktu oraz **reguł koszyka** (**Cart Rules**), o ile są dodawane automatycznie tak jak w standardowym koszyku.

**Display name / opis w kodzie (tłumaczenia XLF)**  
W interfejsie administratora nazwa i opis pochodzą z domeny `Modules.Lowestshipping.Admin` (np. *Lowest shipping estimate* — dokładne brzmienie zależy od języka BO).

---

## 2. Główne funkcje / Features

- 📌 **Szacunek najtańszej dostawy** na **product page** (hook `displayProductAdditionalInfo`).
- 🛒 **Symulacja koszyka** przez natywne API: `Cart::add`, `Cart::updateQty`, `Cart::getDeliveryOptionList`.
- 🎟️ **Cart rules**: wywołanie `CartRule::autoAddToCart` na symulowanym kontekście (jak przy realnym koszyku).
- 🌍 **Adres dostawy**: dla zalogowanego — pierwszy adres klienta; dla gościa — **tymczasowy** rekord `Address` w kraju z konfiguracji (lub domyślnego kraju sklepu), usuwany po wycenie.
- 💶 **Cena z podatkiem lub netto** — przełącznik w konfiguracji (`LOWESTSHIPPING_PRICE_WITH_TAX`).
- ✏️ **Prefiks tekstowy** i **dodatkowy opis** pod kwotą (HTML w BO przez `TextareaType` — patrz sekcja bezpieczeństwa).
- 🔁 **Odświeżanie po zmianie kombinacji** — `prestashop` events + żądanie AJAX do `ModuleFrontController` (`ajax`).
- ⚙️ **Konfiguracja w BO** — **Symfony Form** + dedykowany **Admin Controller**, routing YAML, szablon Twig.
- 🧪 **Testy PHPUnit** — warstwa **Unit** (m.in. `LowestShippingCalculator`) oraz **Integration** (opcjonalnie z `PRESTASHOP_ROOT`).

---

## 3. Jak działa moduł

### 3.1. Przepływ wysokopoziomowy

1. **FO — pierwsze renderowanie**  
   Hook `hookDisplayProductAdditionalInfo` (w klasie `Lowestshipping`) po serii warunków (`ProductAdditionalInfoHookGate`) wywołuje prywatną metodę **`getLowestShippingCost`**, która deleguje do **`LowestShippingEstimator::estimateDetailed`**.

2. **Estymacja (`LowestShippingEstimator`)**  
   - Wczytuje **`Product`** i odrzuca: brak/nieaktywny produkt (`invalid_product`), produkt **wirtualny** (`virtual`).  
   - Normalizuje ilość (`quantity < 1` → `1`).  
   - **`resolveDeliveryAddress`**:  
     - Zalogowany klient → `Address::getFirstCustomerAddressId` (adres **trwały**, nie jest usuwany).  
     - Gość / brak adresu → tworzy **ephemeral** `Address` w kraju: `LOWESTSHIPPING_DEFAULT_COUNTRY` lub `PS_COUNTRY_DEFAULT`; dla krajów ze stanami ustawia pierwszy stan z `State::getStatesByIdCountry`.  
   - Tworzy nowy **`Cart`**, ustawia `id_shop`, `id_shop_group`, `id_currency`, `id_lang`, `id_customer`, `id_guest`, `secure_key`, adresy dostawy/faktury.  
   - **`$cart->add()`** — zapis w bazie (wymagany przez silnik dostaw).  
   - **`updateQty`** — dodanie linii z produktem i **kombinacją** (`id_product_attribute`).  
   - Tymczasowo **`Context->cart`** wskazuje na symulowany koszyk.  
   - **`CartRule::autoAddToCart($context, false)`** (jeśli klasa istnieje).  
   - **`getDeliveryOptionList(null, true)`** — zwraca strukturę opcji dostawy z cenami i listą przewoźników.  
   - Przywrócenie starego koszyka w `Context`, **`$simCart->delete()`**, usunięcie adresu tymczasowego jeśli był ephemeral.

3. **Wybór najniższej ceny (`LowestShippingCalculator`)**  
   - Iteruje po wyniku `getDeliveryOptionList`.  
   - Dla każdej poprawnej opcji z kluczami `total_price_with_tax` / `total_price_without_tax` wybiera **minimum** (wg `withTax`).  
   - Z `carrier_list` zbiera instancje **`Carrier`** i buduje nazwę (wielojęzyczna tablica `name` + `id_lang`).  
   - Uwzględnia flagę **`is_free`** (darmowa dostawa w ramach danej opcji).

4. **Prezentacja (`Lowestshipping` + `LowestShippingQuoteBuilder`)**  
   - Sukces: `Tools::displayPrice`, tłumaczenie linii „Carrier: …”, `buildAvailableRow`.  
   - Brak opcji / błąd: `unavailableWithHint` + `buildShippingUnavailableHint` / `LowestShippingUnavailableHints::resolve` (różne komunikaty wg `reason`).  
   - Przy `no_address` możliwy **drugi** `estimate` z fallbackiem kraju `PS_COUNTRY_DEFAULT` dla tekstu typu „Shipping from …”.

5. **Zmiana kombinacji (FO)**  
   `views/js/lowestshipping.js` nasłuchuje **`updatedProduct`** i **`updateProduct`**, wywołuje endpoint modułu z `id_product`, `id_product_attribute`, **tokenem** (`data-token`).

### 3.2. Co jest „źródłem prawdy” dla ceny?

| Element | Rola |
|--------|------|
| **`Cart::getDeliveryOptionList`** | Agreguje reguły **Carrier**, strefy, wagi, ceny, moduły podpięte pod te same mechanizmy co checkout. |
| **`LowestShippingCalculator`** | Tylko **wybór minimum** z już policzonej listy — nie duplikuje logiki przewoźników. |
| **`CartRule::autoAddToCart`** | Może zmienić dostępność/ceny (np. darmowa dostawa), o ile reguła jest dodawana automatycznie w tym kontekście. |

### 3.3. Ilość (`quantity`)

W aktualnej implementacji hook i AJAX przekazują **`quantity = 1`** do `getLowestShippingCost` / `getProductShippingEstimate`. Szacunek dotyczy **jednej sztuki** (z wybraną kombinacją).

---

## 4. Struktura folderów

Repozytorium Git zawiera **katalog modułu** `lowestshipping/` (do skopiowania w `modules/lowestshipping/`). W środku modułu nie ma `.git` — metadane CI leżą w korzeniu repozytorium.

Poniżej drzewo **katalogu modułu** (bez `vendor/`, cache PHPUnit / PHP CS Fixer). Pliki **`index.php`** w podfolderach — standard PrestaShop (ochrona przed listowaniem katalogów).

```text
repozytorium/
├── .github/workflows/phpunit.yml    # CI: cs-check + PHPUnit (Unit), working-directory: lowestshipping
├── .php-cs-fixer.dist.php           # Konfiguracja PHP CS Fixer (ścieżka: lowestshipping/)
├── Readme.md
├── documentation.md
└── lowestshipping/                  # kopiuj do modules/lowestshipping/
    ├── config/
    │   ├── index.php
    │   ├── routes.yml
    │   └── services.yml
    ├── controllers/
    │   ├── index.php
    │   └── front/
    │       ├── index.php
    │       └── ajax.php
    ├── sql/
    │   ├── index.php
    │   ├── install.php
    │   └── uninstall.php
    ├── src/
    │   ├── index.php
    │   ├── Controller/
    │   ├── Form/
    │   ├── Hook/
    │   └── Shipping/
    ├── tests/
    │   ├── bootstrap.php
    │   ├── index.php
    │   ├── Integration/
    │   └── Unit/
    ├── translations/
    ├── upgrade/
    ├── views/
    ├── composer.json
    ├── composer.lock
    ├── config.xml
    ├── index.php
    ├── LICENSE
    ├── lowestshipping.php
    └── phpunit.xml.dist
```

---

## 5. Użyte hooki

| Hook | Metoda | Cel |
|------|--------|-----|
| **`displayProductAdditionalInfo`** | `hookDisplayProductAdditionalInfo` | Render **HTML** bloku z ceną / podpowiedzią (Smarty `displayproductadditionalinfo.tpl`), przekazanie danych do AJAX (`ajax_url`, `token`, id produktu i kombinacji). |
| **`actionFrontControllerSetMedia`** | `hookActionFrontControllerSetMedia` | Rejestracja **`lowestshipping.js`** i **`lowestshipping.css`** wyłącznie na kontrolerze **`product`** (`php_self === 'product'`). |

**Rejestracja przy instalacji**  
`registerHook('displayProductAdditionalInfo')` oraz `registerHook('actionFrontControllerSetMedia')` w `install()`.

**Uwaga (Symfony / BO)**  
W `config/services.yml` **Form Handler** otrzymuje `@prestashop.core.hook.dispatcher` — zgodnie z konwencją PrestaShop hooki mogą rozszerzać zachowanie formularza konfiguracji (np. inne moduły). Nie jest to osobny hook „w kodzie modułu”, lecz **infrastruktura core**.

---

## 6. Wzorce projektowe / Architektura

| Wzorzec / styl | Gdzie w module |
|----------------|----------------|
| **Hook-based module** | Główna integracja FO przez `displayProductAdditionalInfo` + media przez `actionFrontControllerSetMedia`. |
| **Service layer (lekka)** | Klasy w `src/Shipping/*` i `src/Hook/*` — logika wyceny i reguł prezentacji poza monolitem `Module`, z możliwością testów jednostkowych. |
| **Symfony MVC (BO)** | `LowestShippingConfigurationController` + routing `routes.yml` + Twig `configure.html.twig`. |
| **Form + Data Mapper** | `FormType` + `FormDataProviderInterface` + `DataConfigurationInterface` + `PrestaShop\Core\Form\Handler`. |
| **Dependency Injection** | `config/services.yml` — autowire, jawne argumenty dla `Configuration` adaptera. |
| **Front Controller (legacy)** | `controllers/front/ajax.php` — endpoint JSON dla dynamicznej kombinacji (wzorcowy moduł PS). |
| **Gate / Guard** | `ProductAdditionalInfoHookGate` — scentralizowane warunki wczesnego wyjścia z hooka. |

---

## 7. Zabezpieczenia i dobre praktyki

### 7.1. Back Office

- **`#[AdminSecurity("is_granted('update', 'AdminModules')")]`** na akcji konfiguracji — dostęp tylko dla uprawnień aktualizacji modułów.
- **Symfony Form** — walidacja po stronie serwera (`NotBlank` dla kraju, `validateConfiguration` w `LowestShippingConfigurationDataConfiguration`).
- **Flash messages** — mapowanie kodów błędów na bezpieczne komunikaty dla użytkownika.

### 7.2. Front Office / AJAX

- **`$this->isTokenValid()`** w `LowestshippingAjaxModuleFrontController` — odrzucenie żądań bez poprawnego **tokena** (por. `Tools::getToken` przekazywany do szablonu).
- **`$ssl = true`** na kontrolerze frontowym — preferencja HTTPS.
- Walidacja **`id_product > 0`**; przy wyłączonej wycenie zwracany jest „pusty” sukces JSON (bez ujawniania szczegółów błędów biznesowych).

### 7.3. Widoki (escaping)

- Szablon Smarty używa **`escape:'html'`** / **`escape:'htmlall'`** dla pól tekstowych i URL; **`formatted_price`** renderowany jest z **`nofilter`** (zawartość z `Tools::displayPrice` — typowy kompromis PS; **administrator nie powinien wklejać niezaufanego HTML do pól wyświetlanych jako „prefix”**, jeśli theme traktuje go jako HTML).
- **AJAX**: dla dostępnej ceny klient ustawia **`innerHTML`** z `formatted_price` — ten sam model zaufania co w pierwszym renderze.

### 7.4. SQL i dane

- Kod w **`src/`** nie wykonuje **własnych** zapytań SQL — operuje na **`ObjectModel`** / API PrestaShop (`Cart`, `Address`, `Product`, itd.).
- Plik **`sql/install.php`** definiuje tabelę `PREFIX_lowestshipping`, ale **metoda `install()` w `lowestshipping.php` nie dołącza tego skryptu** — w bieżącej wersji moduł **nie wymaga** tej tabeli do działania (potencjalny **dead code** / szablon pod przyszłe rozszerzenia).
- **`uninstall()`** usuwa klucze `Configuration`; dodatkowo czyści **`LOWESTSHIPPING_ENABLE_VISIBILITY_FILTER`**, **`LOWESTSHIPPING_EXCLUDED_*`** (klucze **legacy** — usunięte z UI w migracji **2.1.0**).

### 7.5. Multistore

- Zapis konfiguracji przez **`prestashop.adapter.legacy.configuration`** — standardowy mechanizm **zakresu sklepu** aktywnego w BO. Przy **Multistore** należy powtórzyć konfigurację per kontekst sklepu.

### 7.6. Composer

- `"prepend-autoload": false` — zgodnie z praktyką modułów PS, aby nie nadpisywać autoloadera rdzenia.

---

## 8. Konfiguracja w Back Office

Po kliknięciu **Konfiguruj** moduł przekierowuje do trasy Symfony **`lowestshipping_configuration`** (`/lowestshipping/configuration`).

| Klucz `Configuration` | Pole formularza | Opis |
|------------------------|-----------------|------|
| `LOWESTSHIPPING_DEFAULT_COUNTRY` | **Domyślny kraj dostawy** (`default_country`) | Kraj dla **gości** i klientów **bez** zapisanego adresu dostawy; używany przy tworzeniu tymczasowego `Address`. **Wymagany** (`NotBlank`, walidacja `> 0` w `DataConfiguration`). |
| `LOWESTSHIPPING_PRICE_WITH_TAX` | **Pokaż koszt z podatkiem** (`price_with_tax`) | Switch: cena z `total_price_with_tax` vs `total_price_without_tax` w wyniku dostaw. |
| `LOWESTSHIPPING_TEXT_PREFIX` | **Prefiks przed ceną** (`text_prefix`) | Tekst przed kwotą (np. „Od ”). |
| `LOWESTSHIPPING_DESCRIPTION` | **Dodatkowy tekst pod ceną** (`description`) | `Textarea` — opcjonalny opis pod blokiem. |
| `LOWESTSHIPPING_ENABLE_PRODUCT_PAGE` | **Pokaż blok na karcie produktu** (`enable_product_page`) | Globalne wyłączenie widoku + AJAX zwraca „pusty” wynik gdy wyłączone. |

**Wartości domyślne przy `install()`**  
Ustawiane w `install()`: kraj = `PS_COUNTRY_DEFAULT` (jeśli > 0), `PRICE_WITH_TAX = true`, puste teksty, włączony blok na produkcie.

---

## 9. Instalacja

### 9.1. Z poziomu Back Office

1. Skopiować folder modułu do **`{root_sklepu}/modules/lowestshipping/`**.
2. W katalogu modułu wykonać (PHP z CLI w środowisku sklepu):  
   `composer install` **lub** `composer dump-autoload -o` — aby powstał **`vendor/autoload.php`** (moduł go warunkowo ładuje w `lowestshipping.php`).
3. BO → **Moduły → Menedżer modułów** → wyszukać **Lowest shipping estimate** / `lowestshipping` → **Zainstaluj**.
4. **Konfiguruj** — ustawić kraj i pozostałe pola.

### 9.2. Instalacja ręczna (deployment)

- Wdrożyć ten sam katalog `modules/lowestshipping/` (np. rsync, artifact z CI).
- Upewnić się, że na serwerze wykonano **`composer install --no-dev`** (produkcja) lub **`composer dump-autoload -o`** jeśli `vendor` jest budowany w pipeline.
- Uprawnienia plików zgodnie z polityką hostingu (odczyt dla PHP).
- Po pierwszej instalacji zweryfikować hooki: **Tak** przy `displayProductAdditionalInfo` i `actionFrontControllerSetMedia` (zakładka pozycji modułu w BO).

---

## 10. Aktualizacja modułu

1. **Kopia zapasowa** sklepu (pliki + baza) przed aktualizacją.
2. Nadpisanie plików modułu nowszą wersją (zachować `config/settings.inc.php` sklepu — nie dotyczy modułu).
3. W BO: **Moduły → Menedżer modułów** → przy `lowestshipping` wybrać **Aktualizuj** (PrestaShop uruchomi skrypty **`upgrade/upgrade-*.php`** według wersji).
4. Dla wersji **2.1.0** skrypt `upgrade_module_2_1_0` m.in.:  
   - dodaje brakujące klucze `LOWESTSHIPPING_ENABLE_PRODUCT_PAGE`, `LOWESTSHIPPING_DESCRIPTION`;  
   - **usuwa** z `Configuration` klucze filtrów widoczności (`LOWESTSHIPPING_ENABLE_VISIBILITY_FILTER`, `LOWESTSHIPPING_EXCLUDED_PRODUCT_IDS`, `LOWESTSHIPPING_EXCLUDED_CATEGORY_IDS`).
5. Ponownie uruchomić **`composer install`** / **`dump-autoload`** jeśli zmienił się autoload lub zależności.
6. Wyczyścić cache PrestaShop (BO → Zaawansowane parametry → Wydajność) po większych zmianach.

---

## 11. Znane problemy i ograniczenia (Known Issues / Limitations)

- **Szacunek ≠ gwarancja ceny checkout** — inny adres, inna ilość, kody rabatowe wpisane ręcznie, inna waluta kontekstu lub zmiany koszyka mogą dać inną cenę dostawy.
- **Ilość = 1** — brak konfigurowalnej ilości w UI; wielosztuki mogą mieć inne stawki (waga / próg darmowej dostawy).
- **Darmowa dostawa (Cart rules)** — zależy od tego, czy reguła jest **automatycznie** dodawana w symulowanym koszyku (`CartRule::autoAddToCart`) tak jak u klienta w sklepie; nietypowe warunki mogą nie odwzorować się 1:1.
- **Pierwszy adres zalogowanego klienta** — nie zawsze jest to ten sam adres, który klient wybierze przy checkout; wycena może odbiegać od finalnej.
- **Tymczasowy adres gościa** — uproszczone pola (`address1`, `city`, itd.); niektóre moduły przewoźników mogą polegać na dodatkowych polach adresu (ryzyko innej dostępności niż u realnego klienta).
- **Wydajność** — każda wycena tworzy i usuwa rekord **`Cart`** (i ewentualnie **`Address`**); przy dużym ruchu i wolnej bazie może to być kosztowne.
- **Równoległe żądania** — krótkotrwała podmiana `Context->cart` jest lokalna dla żądania PHP; jednak intensywny AJAX przy szybkim przełączaniu kombinacji zwiększa obciążenie.
- **Tabela SQL z `sql/install.php`** — **nie jest** tworzona przez bieżący `install()` — jeśli dokumentacja zewnętrzna sugeruje migracje tabeli, należy to zweryfikować w kodzie przed poleganiem na niej.
- **Skórki / theme** — jeśli theme nie emituje zdarzeń `updatedProduct` / `updateProduct`, dynamiczna aktualizacja po kombinacji może nie zadziałać (pozostaje pierwszy render SSR).

---

## 12. Edge cases

| Sytuacja | Zachowanie modułu |
|----------|-------------------|
| Produkt nieistniejący / nieaktywny | `reason = invalid_product`, brak ceny, komunikat przez `LowestShippingUnavailableHints`. |
| Produkt wirtualny | Wczesne wyjście z hooka (`ProductAdditionalInfoHookGate`); AJAX zwraca pusty wynik bez ceny. |
| Brak kraju (0 lub brak `PS_COUNTRY_DEFAULT`) | `resolveDeliveryAddress` → `null` → `no_address` (hint z fallbackiem lub komunikat checkout). |
| `Cart::add()` lub `Address::add()` zawiedzie | `cart_error` lub brak adresu — komunikat „brak dostawy” / checkout (zależnie od ścieżki). |
| Pusta lub uszkodzona struktura `getDeliveryOptionList` | `LowestShippingCalculator` zwraca `null` → `no_carriers`. |
| Wszyscy przewoźnicy odrzucą produkt (waga / wymiary / strefa) | Jak wyżej — brak dostępnej opcji. |
| Wiele przewoźników w jednej opcji | Nazwy łączone jako `A + B` w kalkulatorze. |
| `carrier_list` pusta, ale cena istnieje | Możliwa **pusta** nazwa przewoźnika przy zachowanej cenie. |
| Darmowa dostawa w opcji | `is_free_shipping = true`, cena zwykle `0`. |
| Wyłączony blok (`LOWESTSHIPPING_ENABLE_PRODUCT_PAGE`) | Hook zwraca pusty string; AJAX zwraca JSON z `success: true` i pustymi polami. |
| Zły token AJAX | `success: false`, `error: bad_token`. |

---

## 13. Testy

### 13.1. Wymagania deweloperskie

W klonie repozytorium polecenia Composer i PHPUnit uruchamiaj z katalogu **`lowestshipping/`** (katalog modułu).

```bash
cd lowestshipping
composer install
```

Styl kodu: `composer cs-check` / `composer cs-fix` (PHP CS Fixer).

Analiza statyczna: pakiet **`prestashop/php-dev-tools`** + **`phpstan/phpstan`** — `composer phpstan` z plikiem `phpstan.neon` (poziom 8, ścieżki: `src/`, `lowestshipping.php`, `controllers/`). Wymaga zmiennej środowiskowej **`_PS_ROOT_DIR_`** (katalog PrestaShop z `config/config.inc.php`), tak jak bootstrap opisany w dokumentacji PHPStan dla modułów PrestaShop.

### 13.2. Testy jednostkowe (Unit)

```bash
cd lowestshipping
./vendor/bin/phpunit -c phpunit.xml.dist --testsuite Unit
# lub
composer test   # uruchamia wszystkie zestawy z phpunit.xml.dist
```

- `tests/bootstrap.php` — jeśli **nie** ustawiono `PRESTASHOP_ROOT`, ładowany jest **minimalny stub** klasy `Carrier` dla testów kalkulatora.

### 13.3. Testy integracyjne (Integration)

- Ustawić **`PRESTASHOP_ROOT`** na katalog sklepu z **`config/config.inc.php`**.
- Moduł powinien znajdować się w **`{PRESTASHOP_ROOT}/modules/lowestshipping/`**.

```bash
cd lowestshipping

# Windows (PowerShell)
$env:PRESTASHOP_ROOT = "C:\sciezka\do\prestashop"
./vendor/bin/phpunit -c phpunit.xml.dist --testsuite Integration

# Linux / macOS
export PRESTASHOP_ROOT=/ścieżka/do/prestashop
./vendor/bin/phpunit -c phpunit.xml.dist --testsuite Integration
```

- Część scenariuszy (darmowa dostawa, „za ciężki”, brak przewoźników) jest **opcjonalna** i uruchamiana tylko przy dodatkowych zmiennych środowiskowych — szczegóły w `tests/Integration/LowestShippingIntegrationTest.php`.

### 13.4. CI

Workflow **GitHub Actions** (`.github/workflows/phpunit.yml`) w katalogu **`lowestshipping/`** uruchamia **`composer cs-check`** oraz **PHPUnit — zestaw Unit** (bez pełnego PrestaShop na runnerze).

---

## 14. Kompatybilność

| Obszar | Wymaganie |
|--------|-----------|
| **PrestaShop** | **9.0.x – 9.99.99** (zgodnie z `ps_versions_compliancy` w module) |
| **PHP** | **≥ 8.1** (`composer.json`) |
| **Moduły dodatkowe** | Brak **twardej** zależności od innych modułów w `composer.json`; integracja z logiką **Carrier** / **Cart Rule** zależy od konfiguracji sklepu. |
| **Baza danych** | Zgodna z wersją PrestaShop 9 (MySQL/MariaDB wspierana przez rdzeń). |

---

## 15. Changelog

> Sekcja szablonowa — uzupełniaj przy każdym wydaniu zgodnie z [Keep a Changelog](https://keepachangelog.com/pl/1.0.0/).

```markdown
## [Unreleased]

### Added
- …

### Changed
- …

### Fixed
- …

### Removed
- …

## [2.1.0] - RRRR-MM-DD
- Pierwsza udokumentowana wersja w tym repozytorium (Symfony BO form, estimator, AJAX, PHPUnit).
```

---

## 16. Licencja

Moduł jest udostępniony na licencji **[MIT](LICENSE)**.

Pełny tekst licencji znajduje się w pliku **`LICENSE`** w katalogu głównym modułu.

---

## 17. Autor

**Maxsoft** — rozwój i utrzymanie modułu **lowestshipping** (PrestaShop 9).

*Dokument wygenerowany na podstawie faktycznej implementacji w repozytorium (wersja modułu **2.1.0**). W razie rozbieżności między dokumentacją a kodem **źródłem prawdy jest kod źródłowy**.*
