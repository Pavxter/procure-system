# Koloid Nabavka — Uputstvo za korišćenje

## Sadržaj
1. [Pristup aplikaciji](#1-pristup-aplikaciji)
2. [Pregled (Dashboard)](#2-pregled-dashboard)
3. [Sirovine](#3-sirovine)
4. [Dobavljači](#4-dobavljaci)
5. [Narudžbine](#5-narudzbine)
6. [Kontroling](#6-kontroling)
7. [Plan nabavke](#7-plan-nabavke)
8. [Plan ugovora](#8-plan-ugovora)
9. [Korisnici](#9-korisnici-samo-admin)
10. [Uloge i dozvole](#10-uloge-i-dozvole)

---

## 1. Pristup aplikaciji

Otvorite browser i idite na **https://nabavkakld.com**

Prijavite se sa korisničkim imenom i lozinkom. Svaki korisnik ima jednu od tri uloge:
- **Admin** — pun pristup, upravljanje korisnicima
- **Nabavka** — pun pristup svim podacima
- **Viewer** — samo pregled, bez izmena

Nakon prijave, navigacija između modula je putem tabova na vrhu ekrana.

---

## 2. Pregled (Dashboard)

Početna strana koja prikazuje trenutno stanje sistema.

### Statistika (4 kartice):
| Kartica | Šta prikazuje |
|---|---|
| Sirovine | Ukupan broj sirovina u sistemu |
| Kritično | Broj sirovina ispod minimuma |
| Narudžbine | Broj aktivnih narudžbina (status "Naručeno" ili "Čekanje") |
| Dobavljači | Ukupan broj dobavljača |

### Kritične sirovine:
Tabela sirovina čije je stanje na zalihi ispod definisanog minimuma. Kolone: Naziv, Kategorija, Stanje, Minimum, JM, Dobavljač, Status.

### Poslednje aktivnosti:
Hronološki log poslednjih 15 akcija u sistemu — ko je šta uradio i kada. Tipovi akcija su označeni bojama: CREATE (zeleno), UPDATE (plavo), DELETE (crveno), LOGIN/LOGOUT (sivo).

---

## 3. Sirovine

Centralni modul za upravljanje svim sirovinama i materijalima.

### Tabela sirovina
Prikazuje sve sirovine sa kolonama: Naziv, Kategorija, JM, Stanje, Minimum, Cena (RSD), Dobavljač, Status.

**Status sirovine** se određuje automatski:
- **OK** (zeleno) — stanje je iznad minimuma
- **Nisko** (žuto) — stanje je blizu minimuma
- **Kritično** (crveno) — stanje je ispod minimuma

### Pretraga i filtriranje
- **Pretraga** — unos teksta filtrira po nazivu sirovine
- **Filter kategorije** — prikazuje samo sirovine iz odabrane kategorije

### Dodavanje sirovine
Kliknite **"+ Nova sirovina"**. Popunite formu:

| Polje | Opis | Obavezno |
|---|---|---|
| Naziv | Ime sirovine ili materijala | Da |
| Kategorija | Biranje iz liste kategorija | Ne |
| Jedinica mere | kg, g, L, mL, kom, m, m², m³, t | Ne |
| Stanje | Trenutna količina na zalihi | Ne |
| Minimum | Kritičan nivo — ispod ovoga sistem javlja upozorenje | Ne |
| Cena (RSD) | Cena po jedinici mere | Ne |
| Dobavljač | Veza sa dobavljačem iz sistema | Ne |
| Napomena | Slobodan tekst | Ne |

### Izmena sirovine
Kliknite ikonicu olovke u redu sirovine. Ista forma kao pri dodavanju, sva polja su popunjena postojećim vrednostima.

### Brisanje sirovine
Kliknite ikonicu kante. Sistem traži potvrdu pre brisanja.

### CSV izvoz
Dugme **"Izvezi CSV"** preuzima sve sirovine (sa primenjenim filterima) kao Excel-kompatibilnu datoteku.

### CSV uvoz
Dugme **"Uvezi CSV"** otvara modal za masovni uvoz:
1. Preuzmite template klikom na "Preuzmi template"
2. Popunite template u Excelu (kolone: naziv, kategorija, jedinica_mere, stanje, minimum, cena, napomena)
3. Sačuvajte kao CSV
4. Uploadujte fajl — sistem prikazuje preview
5. Kliknite "Uvezi" — progress bar prati napredak

### Upravljanje kategorijama
Dugme **"Kategorije"** otvara modal za upravljanje listom kategorija:
- **Dodavanje** — unesite naziv i kliknite "+ Dodaj" (dozvoljeno ulogama: nabavka i admin)
- **Brisanje** — kliknite X pored kategorije (samo admin)

---

## 4. Dobavljači

Modul za evidenciju dobavljača sa sistemom ocenjivanja.

### Tabela dobavljača
Kolone: Naziv, Kontakt, Telefon, Email, Ugovor (Da/Ne), Ocena (zvezdice).

### Pretraga
Unos teksta filtrira po nazivu dobavljača.

### Dodavanje dobavljača
Kliknite **"+ Novi dobavljač"**. Popunite formu:

| Polje | Opis | Obavezno |
|---|---|---|
| Naziv | Naziv firme dobavljača | Da |
| Kontakt osoba | Ime i prezime kontakta | Ne |
| Telefon | Broj telefona | Ne |
| Email | Email adresa | Ne |
| Adresa | Adresa sedišta | Ne |
| Ugovor | Da li postoji potpisan ugovor | Ne |
| Napomena | Slobodan tekst | Ne |

### Ocenjivanje dobavljača
U formi za dodavanje/izmenu, ocenite dobavljača po 5 kriterijuma (1-5 zvezdica):

| Kriterijum | Šta se ocenjuje |
|---|---|
| Kvalitet | Kvalitet isporučenih sirovina |
| Cena | Konkurentnost cena |
| Rokovi | Poštovanje dogovorenih rokova isporuke |
| Plaćanje | Uslovi plaćanja |
| Reklamacije | Efikasnost rešavanja reklamacija |

**Ukupna ocena** se izračunava automatski kao prosek svih 5 kriterijuma.

### Radar chart
Na kartici dobavljača prikazuje se SVG grafikon (radar/paučina) koji vizuelno prikazuje ocene po svih 5 osa. Korisno za brzo poređenje dobavljača.

### CSV izvoz
Preuzima sve dobavljače sa svim podacima i ocenama.

---

## 5. Narudžbine

Praćenje svih narudžbina od kreiranja do isporuke.

### Tabela narudžbina
Kolone: #, Sirovina, Dobavljač, Količina, Cena/JM, Ukupno, Status, Datum narudžbe, Datum isporuke.

### Pretraga i filtriranje
- **Pretraga** — filtrira po nazivu sirovine ili dobavljača
- **Filter statusa** — prikazuje samo narudžbine sa odabranim statusom

### Statusi narudžbine
| Status | Boja | Značenje |
|---|---|---|
| Naručeno | Plavo | Narudžbina je poslata dobavljaču |
| Čekanje | Žuto | Čeka se isporuka ili potvrda |
| Isporučeno | Zeleno | Roba je primljena — stanje sirovine se automatski povećava |
| Otkazano | Sivo | Narudžbina je otkazana |

> **Važno:** Kada promenite status na **"Isporučeno"**, sistem automatski povećava stanje sirovine za naručenu količinu.

### Dodavanje narudžbine
Kliknite **"+ Nova narudžbina"**. Popunite formu:

| Polje | Opis | Obavezno |
|---|---|---|
| Sirovina | Biranje sirovine iz sistema | Da |
| Dobavljač | Biranje dobavljača | Ne |
| Količina | Naručena količina (mora biti > 0) | Da |
| Cena/JM | Cena po jedinici mere | Ne |
| Ukupno | Izračunava se automatski (količina × cena) | — |
| Status | Početni status narudžbine | Ne |
| Datum narudžbe | Datum slanja narudžbine | Ne |
| Datum isporuke | Očekivani datum isporuke | Ne |
| Napomena | Slobodan tekst | Ne |

### CSV izvoz
Preuzima sve narudžbine (sa primenjenim filterima).

---

## 6. Kontroling

Strateški modul za dokumentovanje procesa nabavke. Funkcioniše kao živi interni dokument.

### Struktura
Matrica **7 oblasti × 10 polja = 70 tekstualnih polja**.

**7 oblasti:**
1. Strategija nabavke
2. Upravljanje dobavljačima
3. Planiranje i prognoziranje
4. Upravljanje zalihama
5. Procesna efikasnost
6. Upravljanje rizicima
7. Razvoj i inovacije

**10 polja po svakoj oblasti:**

| Polje | Šta upisati |
|---|---|
| Šta radimo | Opis aktivnosti ili prakse |
| Zašto radimo | Cilj i svrha aktivnosti |
| Ko je odgovoran | Ime osobe ili uloge |
| Kako merimo | Metod merenja rezultata |
| KPI vrednost | Trenutna vrednost ključnog indikatora |
| Cilj do kraja godine | Ciljana vrednost |
| Trenutni status | Kratka ocena stanja (npr. "Na planu", "Kasni") |
| Rok realizacije | Datum ili period |
| Potrebni resursi | Šta je potrebno za realizaciju |
| Napomena | Slobodan tekst |

### Auto-save
Nema dugmeta "Sačuvaj". Svako polje se **automatski čuva** kada kliknete van njega (blur):
- **Žuta boja** — čuvanje u toku
- **Zelena boja** — uspešno sačuvano

### Primer popunjavanja
Za oblast "Upravljanje dobavljačima":
- *Šta radimo:* "Kvartalana ocena dobavljača po 5 kriterijuma"
- *Ko je odgovoran:* "Goran P."
- *KPI vrednost:* "Prosečna ocena 4.1"
- *Cilj do kraja godine:* "Prosečna ocena 4.5"

---

## 7. Plan nabavke

Godišnje planiranje nabavki po sirovinama i mesecima.

### Prikaz tabele
Svaka sirovina je jedan red. Kolone: Sirovina, JM, Jan, Feb, Mar, Apr, Maj, Jun, Jul, Avg, Sep, Okt, Nov, Dec, Ukupno, Realizovano, %.

### Dva načina prikaza
- **Količine** — planiranje u jedinicama mere (kg, L, kom...)
- **Budžet** — planiranje u novcu (RSD)

Prebacujte se između prikaza dugmadima u toolbar-u.

### Filtriranje
- **Godina** — biranje između 2024, 2025, 2026, 2027
- **Kategorija** — prikazuje sirovine samo iz odabrane kategorije

### Unos podataka
Kliknite na bilo koje polje u tabeli i unesite broj. Sistem automatski čuva vrednost nakon 800ms od poslednje izmene:
- **Žuta boja polja** — čuvanje u toku
- **Zelena boja polja** — uspešno sačuvano

### Kolone realizacije
- **Realizovano** — automatski se puni iz stvarnih isporuka (narudžbine sa statusom "Isporučeno")
- **%** — procenat realizacije plana (Realizovano / Ukupno plan × 100)

### CSV izvoz
Preuzima plan za odabranu godinu sa svim mesecima, planom, realizacijom i procentom.

---

## 8. Plan ugovora

Praćenje ugovora sa dobavljačima.

### Prikaz kartica
Svaki ugovor se prikazuje kao kartica sa obojenim levim rubom prema statusu:
- **Zelena** — Potpisan
- **Žuta** — U pregovorima
- **Crvena** — Nema ugovora
- **Siva** — Istekao

### Filtriranje
Filter po statusu ugovora prikazuje samo kartice sa odabranim statusom.

### Dodavanje ugovora
Kliknite **"+ Novi ugovor"**. Napomena: jedan dobavljač može imati samo jedan ugovor — u padajućem meniju se prikazuju samo dobavljači bez ugovora.

| Polje | Opis |
|---|---|
| Dobavljač | Biranje dobavljača (obavezno) |
| Status | Nema ugovora / U pregovorima / Potpisan / Istekao |
| Prioritet | 1 — Nizak, 2 — Srednji, 3 — Visok |
| Datum potpisa | Datum potpisivanja ugovora |
| Datum isteka | Kada ugovor ističe |
| Rok plaćanja | Uslovi plaćanja (npr. "30 dana") |
| Rabat | Dogovoreni popust (npr. "5%") |
| Min. količina | Minimalna narudžbina |
| Napomena | Slobodan tekst |

### CSV izvoz
Preuzima sve ugovore sa svim detaljima.

---

## 9. Korisnici (samo Admin)

Tab vidljiv samo korisnicima sa ulogom Admin.

### Tabela korisnika
Kolone: Korisničko ime, Ime, Uloga, Status.

### Izmena korisnika
Kliknite ikonicu olovke. Admin može da menja:
- Ime i prezime
- Ulogu (Admin / Nabavka / Viewer)
- Status (Aktivan / Neaktivan)
- Lozinku (opciono — ostavi prazno da ne menjaš)

### Promena sopstvene lozinke
Svaki korisnik (bez obzira na ulogu) može promeniti svoju lozinku klikom na dugme u header-u aplikacije:
- Unesi staru lozinku
- Unesi novu lozinku (min. 6 karaktera)
- Potvrdi novu lozinku

---

## 10. Uloge i dozvole

| Akcija | Viewer | Nabavka | Admin |
|---|---|---|---|
| Pregled svih podataka | Da | Da | Da |
| Dodavanje sirovina, dobavljača, narudžbina | Ne | Da | Da |
| Izmena sirovina, dobavljača, narudžbina | Ne | Da | Da |
| Brisanje sirovina, dobavljača, narudžbina | Ne | Da | Da |
| Dodavanje kategorija | Ne | Da | Da |
| Brisanje kategorija | Ne | Ne | Da |
| Unos u Kontroling | Ne | Da | Da |
| Unos u Plan nabavke | Ne | Da | Da |
| Dodavanje/izmena ugovora | Ne | Da | Da |
| Brisanje ugovora | Ne | Ne | Da |
| CSV uvoz sirovina | Ne | Da | Da |
| Upravljanje korisnicima | Ne | Ne | Da |

---

*Koloid d.o.o. — Sistem za praćenje nabavke*
