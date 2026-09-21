# Sidrene cijene

WordPress/WooCommerce dodatak za isticanje **sidrene (dodatne) cijene** i objavu **cjenika u XML/CSV formatu**, prema Odlukama objavljenima u Narodnim novinama 101/2026, koje se u Hrvatskoj primjenjuju **od 1. listopada 2026.**

> ⚠️ **Ovaj dodatak nije pravni savjet i ne jamči usklađenost s propisima.**
> Odgovornost za točnost cijena i usklađenost poslovanja snosi korisnik.
> Cijelo odricanje od odgovornosti je u [readme.txt](sidrene-cijene/readme.txt).

---

## Što rješava

Odluke uvode dvije odvojene obveze:

| | Obveza | Na koga se odnosi |
|---|---|---|
| **A** | Uz aktualnu cijenu prikazati i **sidrenu cijenu** | trgovci na malo i pružatelji usluga |
| **B** | Objaviti **cjenik u XML ili CSV** formatu | svi koji imaju mrežnu stranicu |

**Sidrena cijena** je maloprodajna cijena koja je za taj proizvod ili uslugu vrijedila na referentni datum, **bez posebnih oblika prodaje**. Dakle redovna, ne akcijska.

Referentni datumi:

- **10.09.2026.** — većina proizvoda i usluga
- **02.05.2025.** — hrana, piće, kozmetika, sredstva za čišćenje, toaletne potrepštine i proizvodi za kućanstvo, za trgovce koji su za te kategorije već isticali dodatnu cijenu po ranijoj odluci

---

## Mogućnosti

- Prikaz sidrene cijene ispod cijene na stranici proizvoda i u katalogu
- Podrška za **jednostavne, varijabilne i grupirane** proizvode te **komplete** (WooCommerce Product Bundles)
- **Zaključavanje** sidrenih cijena u bazu — jednim klikom, uz obaveznu potvrdu
- Zaseban **cjenik usluga** za djelatnosti koje nisu u WooCommerceu (rođendani, termini, najam, servisi)
- Generiranje **CSV i XML** po propisanoj shemi imenovanja datoteka
- **Automatsko osvježavanje** svaki dan u zadano vrijeme (WP‑Cron)
- **Arhiva 30 dana** s automatskim čišćenjem
- Pregledna tablica koja pokazuje gdje nedostaje sidrena cijena ili barkod
- Čista deinstalacija; podaci se brišu samo ako to izričito uključiš

---

## Zašto je zaključavanje važno

Ovo je jedina stvar koju vrijedi razumjeti prije uporabe.

Dok sidrena cijena **nije zaključana**, računa se iz trenutne redovne cijene. To znači da se **pomakne čim promijeniš cijenu** — a Odluka traži upravo suprotno, da ostane zamrznuta na referentni datum.

Zaključavanje upisuje iznos u bazu i time ga trajno fiksira. Nakon toga možeš mijenjati cijene koliko hoćeš.

**Prije zaključavanja provjeri da su cijene u trgovini one koje su vrijedile na referentni datum.** Ako nisu, prvo ih ispravi ili tim proizvodima ručno upiši sidrenu cijenu. Dodatak traži izričitu potvrdu prije nego išta upiše, i ima gumb za poništavanje.

---

## Instalacija

**Iz Releasea (preporučeno)**

1. Preuzmi `sidrene-cijene.zip` iz [Releases](../../releases)
2. WordPress → Dodaci → Dodaj novi → Pošalji dodatak
3. Aktiviraj

> Ne koristi GitHubov gumb „Download ZIP" na glavnoj stranici repozitorija — on pakira mapu s imenom grane (`...-main`), što mijenja putanju dodatka.

**Iz izvora**

```bash
git clone https://github.com/<korisnik>/sidrene-cijene.git
cd sidrene-cijene
bash build.sh          # napravi sidrene-cijene.zip spreman za WordPress
```

---

## Postavljanje

1. **Sidrene cijene → Postavke** — naziv tvrtke, OIB, adresa objekta, referentni datum
2. Provjeri da su cijene u trgovini one od referentnog datuma
3. **Pregled** → potvrdi kvačicu → **Zaključaj sidrene cijene**
4. **Usluge** → unesi usluge ako ih imaš
5. **Cjenici** → Generiraj cjenike sada
6. Objavi adresu mape s cjenicima na stranici, npr. u podnožju

Cjenici se spremaju u `wp-content/uploads/sidrene-cijene/`. Mapa je namjerno javno dostupna jer Odluka traži da podaci budu dohvatljivi automatiziranim alatima.

---

## Zahtjevi

- WordPress 6.0+
- WooCommerce 7.0+
- PHP 7.4+

---

## Što dodatak ne radi

- Ne upisuje barkodove — njih unosiš u WooCommerceu (*Proizvod → Inventory → GTIN, UPC, EAN, ISBN*)
- Ne pokriva isticanje cijena izvan mrežne stranice — letke, plakate, cjenike u poslovnom prostoru
- Ne zamjenjuje obvezu isticanja **najniže cijene u prethodnih 30 dana** kod akcija, koja proizlazi iz Zakona o zaštiti potrošača i primjenjuje se istodobno

---

## Doprinosi

Prijave grešaka i prijedlozi idu u [Issues](../../issues). Dodatak je testiran na ograničenom broju instalacija, pa su povratne informacije o drugim temama, valutama i konfiguracijama osobito korisne.

## Licenca

GPL‑2.0‑or‑later. Vidi [LICENSE](LICENSE).

## Izvori

- [Odluka o isticanju dodatne cijene — NN 101/2026](https://narodne-novine.nn.hr/clanci/sluzbeni/2026_09_101_1212.html)
- [Odluka o objavi cjenika — NN 101/2026](https://narodne-novine.nn.hr/clanci/sluzbeni/2026_09_101_1213.html)
- [Zakon o zaštiti potrošača — NN 59/2026](https://narodne-novine.nn.hr/clanci/sluzbeni/2026_06_59_728.html)
