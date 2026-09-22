=== Sidrene cijene ===
Contributors: geekgarden
Tags: woocommerce, cijene, sidrena cijena, cjenik, compliance
Requires at least: 6.0
Tested up to: 6.8
Requires PHP: 7.4
WC requires at least: 7.0
Stable tag: 1.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Isticanje sidrene (dodatne) cijene i objava cjenika u XML/CSV formatu, prema Odlukama iz NN 101/2026 koje se u Hrvatskoj primjenjuju od 1. listopada 2026.

== Description ==

Plugin pokriva obje obveze koje je Vlada RH propisala u Narodnim novinama 101/2026:

1. **Isticanje dodatne (sidrene) cijene** uz aktualnu maloprodajnu cijenu
2. **Objava cjenika** u strojno čitljivom XML ili CSV formatu

= Što radi =

* Prikazuje sidrenu cijenu ispod cijene na stranici proizvoda i u katalogu
* Podržava jednostavne, varijabilne, grupirane proizvode i komplete (Product Bundles)
* **Zaključava** sidrene cijene u bazu jednim klikom, pa se ne mijenjaju kad mijenjaš cijene
* Vodi zaseban cjenik usluga za djelatnosti koje nisu u WooCommerceu
* Generira CSV i XML po propisanoj shemi imenovanja datoteka
* Automatski osvježava cjenik svaki dan u zadano vrijeme
* Čuva arhivu objavljenih cjenika 30 dana i sam briše starije
* Pregledna tablica koja pokazuje gdje nedostaje sidrena cijena ili barkod
* Javna stranica s poveznicama na cjenike, koju plugin sam napravi pri aktivaciji
* Prikaz sidrene cijene u Elementorovim „Price Table" widgetima, za cjenike usluga
* Izuzimanje kategorija iz cjenika proizvoda (npr. najam, koji je pravno usluga)

= Zašto je zaključavanje važno =

Sidrena cijena je po Odluci zamrznuta na referentni datum. Ako se računa iz
trenutne redovne cijene, pomakne se čim promijeniš cijenu — i podatak postaje
neispravan a da to nitko ne primijeti. Zaključavanje upisuje iznos u bazu i
time ga trajno fiksira.

= Referentni datumi =

* **10.09.2026.** — većina proizvoda i usluga
* **02.05.2025.** — hrana, piće, kozmetika, sredstva za čišćenje, toaletne
  potrepštine i proizvodi za kućanstvo, za trgovce koji su za te kategorije
  već isticali dodatnu cijenu po ranijoj odluci

Datum se bira u postavkama.

= Što plugin ne radi =

* Ne upisuje barkodove — njih unosiš u WooCommerceu
* Ne mijenja cijene u letcima, na plakatima i u oglasima, a obveza vrijedi i ondje
* Ne zamjenjuje obvezu isticanja najniže cijene u 30 dana kod akcija, koja
  proizlazi iz Zakona o zaštiti potrošača i primjenjuje se istodobno

== Odricanje od odgovornosti ==

**Ovaj plugin nije pravni savjet i ne jamči usklađenost s propisima.**

Plugin je pomoćni alat koji olakšava tehničku provedbu obveza iz Odluka
objavljenih u Narodnim novinama 101/2026. Odgovornost za ispravnost podataka,
za točnost sidrenih cijena i za usklađenost poslovanja s propisima u cijelosti
snosi korisnik, odnosno trgovac ili pružatelj usluge.

Konkretno:

* Autor ne jamči da izlazne datoteke zadovoljavaju zahtjeve nadležnih tijela
  u svakom pojedinom slučaju. Odluke ne razrađuju svaku poslovnu situaciju, a
  službena pojašnjenja se mogu mijenjati i nakon objave ovog plugina.
* Plugin čita cijene iz WooCommercea. Ako su cijene u trgovini netočne ili ne
  odgovaraju onima koje su vrijedile na referentni datum, bit će netočne i u
  cjeniku i na prikazu.
* Funkcija zaključavanja upisuje podatke u bazu. Prije uporabe napravite
  sigurnosnu kopiju.
* Plugin ne pokriva isticanje cijena izvan mrežne stranice — letke, plakate,
  cjenike u poslovnom prostoru i druge oblike oglašavanja.
* Plugin ne zamjenjuje obvezu isticanja najniže cijene u prethodnih 30 dana
  kod posebnih oblika prodaje, koja proizlazi iz Zakona o zaštiti potrošača.
* Autor nije povezan s Vladom Republike Hrvatske, Ministarstvom gospodarstva
  ni bilo kojim nadležnim tijelom, niti od njih ima odobrenje ili potvrdu.

Softver se isporučuje „kakav jest", bez ikakvih jamstava, u skladu s uvjetima
GPL licence. Za tumačenje propisa obratite se knjigovođi ili pravnom savjetniku.

== Installation ==

1. Dodaci → Dodaj novi → Pošalji dodatak → odaberi ZIP
2. Aktiviraj
3. Sidrene cijene → Postavke → ispuni podatke o obvezniku
4. Sidrene cijene → Pregled → **Zaključaj sidrene cijene**
5. Sidrene cijene → Cjenici → Generiraj cjenike sada

== Frequently Asked Questions ==

= Mijenja li plugin moje cijene? =

Ne. Čita ih i prikazuje dodatni redak. Zaključavanje upisuje samo novo meta
polje `_sidrena_cijena`; redovna i akcijska cijena ostaju netaknute.

= Kako vratiti sve na staro? =

Pregled → „Poništi zaključavanje" briše sve zaključane vrijednosti.
Deaktivacija plugina uklanja prikaz i zaustavlja zakazane poslove.
Podaci se pri deinstalaciji brišu samo ako to uključiš u postavkama.

= Gdje je javna stranica s cjenicima? =

Plugin je pri aktivaciji sam stvara na adresi /cjenik-podaci/ i na nju stavlja
shortcode `[sidrene_cijene_popis]`. Poveznicu na tu stranicu dodaj u podnožje
(Izgled → Izbornici). Adresa je ispisana u kartici Cjenici.

Ako stranica s tim slugom već postoji, plugin je posvoji umjesto da radi novu.
Deaktivacija plugina stranicu ne dira.

= Gdje završe generirani cjenici? =

U `wp-content/uploads/sidrene-cijene/`. Adresa mape je ispisana u kartici
Cjenici. Mapa je namjerno javno dostupna jer Odluka traži da podaci budu
dohvatljivi automatiziranim alatima.

= Što s varijabilnim proizvodima? =

U cjenik ulazi svaka varijacija kao zaseban redak, sa svojom šifrom i cijenom.
Na trgovini se kod nadređenog proizvoda prikazuje raspon.

= Radi li s WooCommerce Product Bundles? =

Da. Za komplete se sidrena cijena računa iz redovnih cijena stavki, jer sam
komplet nema vlastitu redovnu cijenu.

== Changelog ==

= 1.1.0 =
* Plugin pri aktivaciji sam stvara javnu stranicu s poveznicama na cjenike.
* Stranica razdvaja aktualne cjenike od arhive, pa popis ostaje pregledan.
* Prikaz sidrene cijene u Elementorovim „Price Table" widgetima.
* Postavka za izuzimanje kategorija iz cjenika proizvoda.
* Usluge dostupne kroz REST (skupni unos i uređivanje izvana).
* Zaključavanje traži izričitu potvrdu prije upisa u bazu.

= 1.0.0 =
* Prva verzija.
