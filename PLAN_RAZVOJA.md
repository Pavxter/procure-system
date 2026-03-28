# Koloid Nabavka — Plan razvoja sa Claude Code

**Repo**: https://github.com/Pavxter/procure-system.git
**Domen**: https://nabavkakld.com/
**Rok**: 15. april 2026.

---

## Korak 0: Priprema repozitorijuma (10 minuta)

Otvori terminal i pokreni:

```bash
git clone https://github.com/Pavxter/procure-system.git
cd procure-system
```

Zatim kopiraj fajlove iz starter paketa u taj folder:
- CLAUDE.md (u root)
- .gitignore (u root)
- api/config.example.php (u api/ folder)

Komituj početno stanje:
```bash
git add .
git commit -m "Inicijalni setup: CLAUDE.md, .gitignore, config.example"
git push origin main
```

---

## Korak 1: Osnovna struktura — pokreni Claude Code

```bash
cd procure-system
claude
```

### Prompt 1 — Backend i baza:

```
Pročitaj CLAUDE.md i kreiraj backend za aplikaciju.

Kreiraj:
1. api/config.example.php — PDO konekcija na MySQL, helper funkcije
   (jsonResponse, jsonError, getInput, getDB)
2. api/setup.php — PHP skript koji kreira sve tabele (users, sirovine,
   dobavljaci, narudzbine, kontroling, activity_log) i unosi početne
   podatke (6 korisnika sa password_hash, 3 test dobavljača, 4 test
   sirovine, 7 kontroling oblasti). Prikazuje HTML stranicu sa
   rezultatom instalacije.
3. api/auth.php — login (POST), logout, session check (/me),
   change-password, lista korisnika (admin only)
4. api/data.php — CRUD za sirovine, dobavljaci, narudzbine, kontroling,
   stats endpoint za dashboard. Viewer uloga = samo GET.
   Svaka promena loguje se u activity_log.
5. api/.htaccess — zabrani direktan pristup config.php

Korisničke uloge: admin, nabavka, viewer.
Svi upiti koriste prepared statements.
```

### Prompt 2 — Frontend:

```
Kreiraj index.html — kompletna SPA aplikacija sa React 18 (CDN, bez
build stepa). Babel standalone za JSX transpilaciju.

Sadrži:
- Login ekran sa autentikacijom preko api/auth.php
- Header sa logoom "K", nazivom "Koloid — Nabavka", prikazom korisnika
  i dugmetom za odjavu
- 5 tabova: Pregled, Sirovine, Dobavljači, Narudžbine, Kontroling
- Dashboard: kartice sa statistikom, tabela kritičnih sirovina,
  lista poslednjih aktivnosti
- Sirovine: tabela sa pretragom, dodavanje/izmena kroz modal forme,
  brisanje sa potvrdom, CSV export, statusne boje (zelena/žuta/crvena)
- Dobavljači: kartice sa kontaktima, zvezdice za ocenu (1-5),
  status ugovora (da/ne), napomene
- Narudžbine: tabela sa statusima (naručeno/čekanje/isporučeno/otkazano)
- Kontroling: 7 oblasti sa po 10 input polja, auto-save na blur
- Viewer uloga ne vidi dugmad za dodavanje/izmenu/brisanje
- Toast notifikacije za uspeh/grešku
- Dugme za osvežavanje podataka

Stilovi: DM Sans font, tamno-plavi header (#1a1a2e), svetla pozadina
(#f0f2f5), responsive grid layout.
```

Posle ovoga — testiraj lokalno:
```bash
php -S localhost:8000
```
Otvori http://localhost:8000 u browseru.

---

## Korak 2: Deploy na nabavkakld.com

### U cPanel-u:
1. MySQL Databases → kreiraj bazu i korisnika sa ALL PRIVILEGES
2. Kopiraj config.example.php u config.php i unesi prave podatke
3. Upload fajlove na server (Git deploy, FTP ili File Manager)
4. Otvori https://nabavkakld.com/api/setup.php — kreira tabele
5. OBRIŠI setup.php sa servera
6. Otvori https://nabavkakld.com/ — loguj se kao "gp"

### .htaccess za HTTPS (ako imaš SSL):
```apache
RewriteEngine On
RewriteCond %{HTTPS} off
RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]
```

---

## Korak 3: Unos pravih podataka

Ovo radiš direktno na https://nabavkakld.com/ — loguj se i počni:

1. Obriši test dobavljače, unesi pravih 10-30
2. Obriši test sirovine, unesi prave (sve što nabavljate)
3. Za svaku sirovinu definiši minimalne zalihe (sa DM-om)
4. Oceni svakog dobavljača
5. Unesi aktivne narudžbine

---

## Korak 4: Plan nabavke modul

Nazad u Claude Code:

```bash
cd procure-system
claude
```

```
Dodaj novi modul "Plan nabavke". Ovo je novi tab u aplikaciji.

Backend:
- Nova tabela `plan_nabavke` (id, sirovina_id, godina, mesec_1 do
  mesec_12 kao DECIMAL za planirane količine, budzet_1 do budzet_12
  za planirani budžet po mesecima, created_by, updated_at)
- CRUD endpoint u data.php: entity=plan_nabavke
- GET vraća plan sa join na sirovine (naziv, jm, kategorija)

Frontend:
- Nov tab "Plan" posle Kontroling-a
- Tabela: redovi = sirovine, kolone = meseci (jan-dec)
- Za svaku ćeliju: input za količinu i budžet
- Kolona "Ukupno" sa sumom
- Kolona "Realizovano" koja računa iz narudžbina sa statusom
  "isporuceno" za tu sirovinu u tom mesecu
- Kolona "%" realizacije (realizovano/planirano × 100)
- Auto-save na blur (isto kao kontroling)
- CSV export cele tabele
- Filter po kategoriji sirovina
- Viewer uloga: samo pregled, bez izmena
```

---

## Korak 5: Plan ugovora modul

```
Dodaj modul "Plan ugovora" kao novi tab.

Backend:
- Nova tabela `plan_ugovora` (id, dobavljac_id, status ENUM
  'nema_ugovor'/'u_pregovorima'/'potpisan'/'istekao',
  datum_potpisa DATE, datum_isteka DATE, rok_placanja VARCHAR,
  rabat VARCHAR, min_kolicina VARCHAR, prioritet TINYINT 1-3,
  napomena TEXT, created_by, updated_at)
- CRUD endpoint u data.php: entity=plan_ugovora
- GET sa join na dobavljaci (naziv, kontakt, ocena)

Frontend:
- Nov tab "Ugovori" posle Plana
- Kartice po dobavljačima sa statusom, datumima, uslovima
- Statusne boje: crvena=nema/istekao, žuta=u pregovorima,
  zelena=potpisan
- Filter po statusu
- Sortiranje po prioritetu
- Modal za izmenu uslova
- CSV export
- Na dashboardu: dodaj karticu "Ugovori: X/Y potpisano"
```

---

## Korak 6: Proširena ocena dobavljača

```
Proširi sistem ocene dobavljača sa višekriterijumskim ocenjivanjem.

Backend:
- Dodaj kolone u tabelu dobavljaci: ocena_kvalitet, ocena_cena,
  ocena_rokovi, ocena_placanje, ocena_reklamacije (svi TINYINT 1-5)
- Kolona 'ocena' postaje računsko polje (prosek svih ocena)

Frontend:
- U formi dobavljača: 5 slider/stars polja za svaki kriterijum
- Na kartici: prikaži radar/spider chart sa 5 osa (koristi SVG)
- Ukupna ocena se računa automatski
- Na dashboardu: rangiranje dobavljača po ukupnoj oceni
```

---

## Korak 7: Izveštaj za štampu

```
Dodaj dugme "Generiši izveštaj" na dashboardu.

Otvara novi tab sa print-friendly HTML stranicom koja sadrži:
- Naslov: "Koloid d.o.o. — Izveštaj nabavke" sa datumom
- Sekcija 1: Stanje zaliha (tabela svih sirovina sa statusima)
- Sekcija 2: Kritične sirovine (stanje < minimum)
- Sekcija 3: Dobavljači sa ocenama i statusom ugovora
- Sekcija 4: Aktivne narudžbine
- Sekcija 5: Plan nabavke — realizacija po mesecima
- Sekcija 6: Kontroling matrica — popunjenost

Koristi @media print CSS za A4 format, page-break-before za sekcije,
sakrivanje navigacije. Samo tabele i tekst, bez interaktivnih elemenata.
```

---

## Korak 8: Testiranje i finalizacija

```
Proveri celu aplikaciju:
1. Testiraj login sa sva 3 tipa uloga (admin, nabavka, viewer)
2. Testiraj CRUD za svaki modul (sirovine, dobavljači, narudžbine)
3. Proveri da viewer ne može da menja podatke
4. Testiraj CSV export — otvori u Excelu, proveri srpske karaktere
5. Testiraj na mobilnom telefonu (responsive layout)
6. Proveri da dashboard statistike odgovaraju stvarnim podacima
7. Testiraj izveštaj za štampu
8. Popravi sve bugove koje nađeš
```

---

## Vremenski raspored

| Nedelja | Dani | Šta radim | Rezultat |
|---------|------|-----------|----------|
| 1 (25-31.3.) | 1-2 | Koraci 0-1: setup + osnova | Radi login, CRUD, dashboard |
| 1 | 3 | Korak 2: deploy na nabavkakld.com | Aplikacija živa na domenu |
| 1 | 4-7 | Korak 3: unos pravih podataka | Pravi dobavljači i sirovine |
| 2 (1-7.4.) | 8-9 | Korak 4: plan nabavke | Mesečni plan sa realizacijom |
| 2 | 10-11 | Korak 5: plan ugovora | Praćenje pregovora |
| 2 | 12 | Korak 6: proširena ocena | Višekriterijumsko ocenjivanje |
| 2 | 13-14 | Korak 7: izveštaji | Print-ready izveštaj |
| 3 (8-15.4.) | 15-16 | Tim testira, feedback | ML, BT, DM dobiju pristup |
| 3 | 17-18 | Korak 8: popravke | Bugfix na osnovu feedback-a |
| 3 | 19 | Popuni kontroling matricu | 7 oblasti kompletno |
| 3 | 20 | Generiši Word dokumente | Formalni planovi za menadžment |
| 3 | 21 (15.4.) | Predaja | Dokumenti + link na sistem ✓ |

---

## Git workflow za svaki dan rada

```bash
cd procure-system
git pull origin main          # preuzmi ako je neko menjao
claude                        # pokreni Claude Code, radi na kodu
# ... kad završiš sesiju ...
git add .
git commit -m "Dodat modul plan nabavke"
git push origin main          # pošalji na GitHub
# deploy na hosting (pull u cPanel-u ili FTP)
```

---

## Korisni Claude Code saveti

- **Plan Mode**: Shift+Tab dva puta — Claude napravi plan pre nego što piše kod
- **/clear** — očisti kontekst između nepovezanih zadataka
- **/compact** — sažmi kad kontekst postane prevelik
- **Specifični bugfix**: "Kad kliknem Sačuvaj na formi za dobavljača, dobijem grešku 500. Proveri api/data.php sekciju za dobavljaci POST."
- **Review koda**: "Proveri bezbednost auth.php — da li su sesije dobro implementirane?"
- **Git iz Claude Code**: "Commituj promene sa porukom 'Dodat CSV export za plan nabavke'"
