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

### 1.1.7 - Production Ready
- Rewizje buildera per strona (wersje ukladu) + szybkie przywracanie.
- Import/eksport layoutu z mapowaniem sekcji.
- Wydajnosc: wirtualizacja paneli i optymalizacje przy wiekszych stronach.

### 1.1.8 - DB Engine Switch (Admin)
- Przelaczanie silnika bazy danych miedzy MySQL i SQLite z poziomu panelu admin.
- Sekcja zabezpieczona dodatkowo: haslo + 2FA (TOTP + plik .mijauth) przed odblokowaniem akcji.
- Walidacja bezpieczenstwa przed przelaczeniem: docelowa baza musi zawierac tabele CMS i aktualnego admina.

## Definition Of Done (dla PRO DnD)
- Uzytkownik bez znajomosci programowania moze:
	- dodac sekcje jako kafelek,
	- przesunac ja na canvasie,
	- zmienic jej rozmiar,
	- zobaczyc istniejąca tresc strony w edytorze,
	- zapisac i opublikowac layout bez edycji kodu/JSON.