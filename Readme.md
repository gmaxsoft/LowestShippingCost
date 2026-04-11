# lowestshipping (PrestaShop 9)

Moduł wyświetla na **karcie produktu** szacunek **najniższego kosztu dostawy** dla bieżącego produktu i kombinacji, korzystając z **natywnych mechanizmów koszyka i przewoźników** PrestaShop (`Cart::getDeliveryOptionList` po krótkotrwałej symulacji koszyka).

## Wymagania

- PrestaShop **9.0+**
- PHP **8.1+**
- Composer (tylko do wygenerowania autoloadu w katalogu modułu)

## Instalacja

1. Skopiuj folder modułu do `modules/lowestshipping/` w katalogu sklepu.
2. W katalogu modułu uruchom:
   ```bash
   composer dump-autoload -o
   ```
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

## Licencja

[MIT](LICENSE) — Copyright (c) 2026 Maxsoft.
