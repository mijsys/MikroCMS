# MikroCMS

## Roadmap (Next Update Path)

### 1.1.4 - PRO Page Builder: Grid Canvas (no-code)
- Cel: przejsc z listy sekcji na pelny edytor DnD typu canvas.
- Kazda nowa sekcja pojawia sie jako "kafelek" (kwadracik) na siatce.
- Przeciaganie sekcji bezposrednio po stronie (canvas), z podgladem rozmieszczenia w czasie rzeczywistym.
- Snap do siatki (np. 12 kolumn) i kontrola zajmowanego obszaru (x, y, width, height).
- Resize sekcji w poziomie i pionie z uchwytami.

### 1.1.5 - Live Preview Existing Content
- Edytor od razu laduje i pokazuje tresc strony, ktora juz istnieje (aktualne bloki + fallback content).
- Tryb "edytuj na zywo": user widzi realny uklad strony i przesuwa sekcje po aktualnym layoucie.
- Zmiany robocze (draft) + autozapis, bez utraty pracy po odswiezeniu.

### 1.1.6 - UX PRO + Safety
- Historia operacji (undo/redo) dla drag, resize, delete, duplicate.
- Blokowanie nakladania sekcji (collision rules) i automatyczne porzadkowanie.
- Warstwy/outline sekcji (lista po lewej + zaznaczanie na canvasie).
- Presety sekcji (hero, faq, cta, gallery, pricing) dodawane z palety.
-  dodanie edytora tekstu zrób własne rozwiązanie
- dodanie pluginu galeri
- dodanie opcji dodania pluginów na róźne strony ale zaznaczyć czy mają dziediczyć opcje np galeria na stronie a i b mają mieć inne zdjęcia albo takie same, w edytorze postów także dodaj opcję dodannia zdięć opcja z galerii albo dodanie poptrzez upload i tutaj też możemy ustawić zdjęcie gdzie chcemy a tekst ma się dostosować do obrazka aby obrazek nie zakrywał tekstu ok

### 1.1.7 - Production Ready
- Rewizje buildera per strona (wersje ukladu) + szybkie przywracanie.
- Import/eksport layoutu z mapowaniem sekcji.
- Wydajnosc: wirtualizacja paneli i optymalizacje przy wiekszych stronach.

### 1.1.7.1 - Website Builder Page UX
- Domyslny tryb prosty (no-code): sekcje ukladaja sie automatycznie.
- Szybkie ustawianie szerokosci sekcji bez recznego wpisywania grid X/Y.
- Przelacznik trybu prosty/zaawansowany dla bardziej technicznej edycji.
- Uproszczony canvas i instrukcja krok po kroku dla edytora stron.

### 1.1.7.2 - Landing Templates + Section Styles + Page Navigator
- Dwa pelne szablony landing page: Landing Classic i Landing Product.
- Panel stylow sekcji: motywy kolorow + skala typografii dla kazdej sekcji.
- Navigator stron z miniaturami oraz duplikowaniem calej strony jednym kliknieciem.
- Duplikacja strony kopiuje tresc, layout, tlumaczenia i plugin placements.

### 1.1.7.3 - DnD Studio Layout
- Przebudowa buildera na uklad studia: lewy panel komponentow, centralny canvas/live preview, prawa kolumna inspektora.
- Przeciagnij i upusc bezposrednio na canvas z czytelnym stanem drop.
- Lepszy workflow drag and drop: wybierz element, upusc, konfiguruj i od razu widz wynik.

### 1.1.7.4 - DnD UX Polish (Start)
- Inline toolbar na sekcji (duplikuj/usun/przesun) bez wchodzenia do listy.
- Uchwyt przeciagania sekcji bezposrednio w live preview.
- Snapowane strefy drop miedzy sekcjami (gora/dol) dla szybszego ukladu.

### 1.1.7.8 - Builder Rebuild + Settings Fix
- Kompletny rebuild buildera stron: palette + canvas + inspector (styl Wix/Elementor).
- Nowe typy blokow i uproszczony workflow dodawania/edycji elementow.
- Upload obrazow z panelu admin i aktualizacja renderowania blokow.
- Naprawa strony ustawien: usuniecie rozjazdu i dodanie zakladek sekcji.

### 1.1.8 - DB Engine Switch (Admin)
- Przelaczanie silnika bazy danych miedzy MySQL i SQLite z poziomu panelu admin.
- Sekcja zabezpieczona dodatkowo: haslo + 2FA (TOTP + plik .mijauth) przed odblokowaniem akcji.
- Walidacja bezpieczenstwa przed przelaczeniem: docelowa baza musi zawierac tabele CMS i aktualnego admina.

### 1.1.9 - Responsywność strony admina jak i dla użytkownika, pluginy i wszystko musi współgrać
   - responsywność
   - napisanie api dla tworzenia pluginów

## Definition Of Done (dla PRO DnD)
- Uzytkownik bez znajomosci programowania moze:
	- dodac sekcje jako kafelek,
	- przesunac ja na canvasie,
	- zmienic jej rozmiar,
	- zobaczyc istniejąca tresc strony w edytorze,
	- zapisac i opublikowac layout bez edycji kodu/JSON.
