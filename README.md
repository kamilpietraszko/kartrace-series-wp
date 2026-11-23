# KartRace Series — strona WordPress i wtyczka „KRS Wyniki”

Kod strony **[kartraceseries.pl](https://kartraceseries.pl)**, czyli amatorskiej ligi kartingowej KartRace Series. Liga organizuje wyścigi kartingowe na torach w Warszawie i okolicach: Pole Position Janki i Bemowo, A1Karting, Kartingowy PGE Narodowy, S8 Race, Racing Center Warsaw. Wyniki z każdej rundy sumują się w klasyfikacji generalnej sezonu.

Repozytorium zawiera:

- **`krs-wyniki/`** — wtyczkę WordPress *KRS Wyniki* 2.1.0. To serce strony: wyniki, kalendarz, tory i SEO.
- **`theme/global-styles.css`** — globalny CSS strony, wklejany w Edytorze witryny (motyw Twenty Twenty-Five, Style → Dodatkowy CSS).

Projekt i wykonanie: **[SCS Web](https://scsweb.pl)**.

## Funkcje

- **Automatyczna klasyfikacja z Excela.**
  - Wtyczka pobiera arkusz `.xlsx` udostępniony na OneDrive i czyta go bez zewnętrznych bibliotek (ZipArchive + SimpleXML).
  - Z arkusza buduje klasyfikację generalną (z podium) i wyniki każdej rundy.
  - Odświeża dane co 15 minut przez WP-Cron. Arkusz jest tylko odczytywany, nigdy zmieniany.
- **Wydarzenia i tory w panelu WordPress.**
  - Dwa własne typy treści: *Wydarzenia KRS* (runda CUP, Endurance, Track Day, inne) oraz *Tory* (miejscowość, grafika nitki toru, źródło grafiki).
  - Nowy tor można dodać bezpośrednio z formularza wydarzenia.
- **Wszystko połączone.**
  - Kalendarz, strona torów i klasyfikacja linkują do siebie nawzajem: z rundy do toru, z toru do wyników rundy, z kalendarza do klasyfikacji.
  - Kafel „Następna runda” na stronie głównej aktualizuje się sam.
- **Grafiki do relacji (Stories) 1080×1920.**
  - Przy każdym zawodniku jest przycisk „Udostępnij”. Generuje grafikę z miejscem, punktami i best lapem, a dla podium dodaje medalowy wieniec.
  - Grafika powstaje w przeglądarce (canvas), bez serwera.
  - Na telefonie wysyła ją przez Web Share API, na komputerze pozwala ją pobrać.
- **SEO.** Dane strukturalne JSON-LD:
  - `SportsOrganization`;
  - `SportsEvent` dla każdego wydarzenia;
  - `FAQPage` dla sekcji FAQ na stronie głównej.

### Shortcody

| Shortcode | Co wyświetla |
|---|---|
| `[krs_klasyfikacja]` | klasyfikacja generalna + zakładki z wynikami rund |
| `[krs_kalendarz]` | kalendarz: najbliższe i minione wydarzenia |
| `[krs_tory]` | tory z grafikami nitek i listą rozegranych rund |
| `[krs_nastepna]` | kafel „Następna runda” (tor, godzina, data, zapisy) |
| `[krs_liderzy]` | top 3 klasyfikacji generalnej |
| `[krs_faq]` | sekcja FAQ (ta sama treść trafia do JSON-LD) |
| `[krs_runda_link nr="3"]` | przycisk do wyników rundy (pokazuje się, gdy runda ma dane) |
| `[krs_zapisy do="2026-11-30 19:25"]` | przycisk zapisów widoczny do podanego terminu |

## Instalacja

1. Spakuj katalog `krs-wyniki` do pliku `krs-wyniki.zip`. Gotowa paczka jest też w zakładce **Releases** (v2.1.0).
2. W panelu WordPress wejdź w **Wtyczki → Dodaj nową → Wyślij wtyczkę na serwer**, wybierz plik zip i kliknij **Zainstaluj**, potem **Włącz**.
3. Opcjonalnie wklej `theme/global-styles.css` w **Wygląd → Edytor → Style → Dodatkowy CSS**. Tam są style nagłówka, hero, kart i tabel.

## Konfiguracja

- **Ustawienia → KRS Wyniki:**
  - wklej link do udostępnionego arkusza OneDrive (tylko odczyt);
  - opcjonalnie wklej link bezpośredni do pobrania;
  - ustaw częstotliwość odświeżania.
  - Przycisk „Odśwież teraz” pobiera dane od razu i pokazuje diagnostykę.
- **Arkusz:**
  - zakładka z „Klasyfikacja” w nazwie (kolumny *Kierowca*, *Punkty*, *Miejsce*);
  - zakładki „Runda N” z grupami (Finał A/B…, nagłówek *Kierowca*).
- **Wydarzenia KRS → Dodaj wydarzenie:** data i godzina, rodzaj, numer rundy (musi pasować do zakładki „Runda N”), link do wydarzenia na Facebooku, tor.
- **Telefon ligi:** wpisz w `wp-config.php`, np. `define( 'KRS_PHONE', '+48 000 000 000' );`. Bez tej linii telefon nie pojawia się w FAQ ani w JSON-LD.

> **Uwaga — ID mediów na sztywno.** Kod korzysta z ID załączników tej konkretnej instalacji:
> - **5001** — logo, używane w grafikach do relacji i w JSON-LD;
> - **5057** — tło grafik do relacji.
>
> Przy innej instalacji podmień te ID w `krs-wyniki.php` i `krs-seo.php`. Funkcja `krs_seed` w `krs-events.php` to jednorazowe wypełnienie danymi sezonu 2026 (ID grafik torów 51xx) i przy nowej instalacji można ją pominąć.

## Wymagania

- WordPress 6.5+
- PHP 7.4+ z rozszerzeniem **zip** (ZipArchive)
- Motyw blokowy (strona działa na Twenty Twenty-Five; style odwołują się do fontów Bebas Neue, Space Mono i Space Grotesk z ustawień motywu)

## Struktura

```
krs-wyniki/
  krs-wyniki.php   główny plik: ustawienia, pobieranie i czytanie XLSX, cron, klasyfikacja, panel admina
  krs-events.php   wydarzenia i tory (typy treści, kalendarz, tory, „Następna runda”)
  krs-seo.php      JSON-LD (organizacja, wydarzenia, FAQ) i shortcode [krs_faq]
  krs-share.js     generator grafik 1080×1920 do relacji
  krs-wyniki.js    zakładki rund, rozwijanie wyników zawodnika, przycisk udostępniania
  krs-wyniki.css   style wtyczki
theme/
  global-styles.css  globalny CSS strony (Edytor witryny)
```

## Autor i licencja

Projekt i wykonanie: **SCS Web** — [scsweb.pl](https://scsweb.pl) · strona ligi: [kartraceseries.pl](https://kartraceseries.pl)

Licencja: [GPL-2.0-or-later](LICENSE), tak jak WordPress.
