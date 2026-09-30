# Oprava a ověření nasazení

## Zjištěný stav 30. 9. 2026

- Větev main na commitu `0b372de498c5650e8d9dbb076a196ab2b3c30127` obsahovala původní instalátor s kontrolou `.installed`. Lokální úprava z předchozího chatu v tomto commitu není.
- Repozitář neměl žádný GitHub Actions deploy ani záznam běhu. Změna na GitHubu sama o sobě nedokazuje změnu na hostingu.
- Produkční `/api/health.php` vrátil HTTP 200 a `{"ok":true,"database":"connected","tables":10}`. Počet odpovídá devíti tabulkám a jednomu pohledu, ale původní kontrola jejich názvy ani verze neověřuje.
- Produkční `/install.php` při této kontrole vrátil HTTP 500 s prázdným tělem. Původní hlášku o zámku se nepodařilo reprodukovat. Bez serverového logu a souborů nelze určit, zda jde o jinou cestu, chybu PHP nebo cache.

## Nasazení opravy

1. V administraci hostingu ověřte skutečný document root subdomény `mojepenize.jirijanousek.cz`. Zálohujte současné aplikační soubory i databázi. Záloha konfigurace zůstává pouze na zabezpečeném serveru.
2. Spusťte `python3 scripts/build_release.py`. Archiv `dist/moje-penize.zip` obsahuje explicitní seznam souborů; neobsahuje `config.php`, `.env`, `.installed`, zálohy ani přihlašovací údaje.
3. Nahrajte obsah archivu do ověřeného document root. Zachovejte existující `config.php` a `.installed`. Neodstraňujte obsah serverové složky a nepoužívejte synchronizaci s mazáním. Nový `install.php` nahrajte první: veřejný přístup ihned zakáže nezávisle na značce `.installed`. Knihovny nahrajte před novým `api/health.php`.
4. `/api/health.php` musí obsahovat `release: safe-install-v1`. Pokud stále vrací starý formát s `tables`, nové soubory nejsou aktivní. Porovnejte SHA-256 serverových souborů s `release-manifest.json`, cestu subdomény a cílový adresář přenosu. Pokud se soubory shodují, obnovte PHP/OPcache přes správu hostingu a ověřte znovu. Nepřidávejte veřejný endpoint pro reset cache nebo výpis konfigurace.
5. Po nasazení `/install.php` vždy vrací HTTP 403 `Installer is locked.`. To je očekávaný bezpečný stav, nikoli důkaz starého souboru.

## Jednorázová inicializace nebo oprava neúplné instalace

V příslušném adresáři na serveru spusťte `php install.php`. Jde pouze o kontrolu. Je-li schéma kompletní, skončí úspěšně bez změn, bez ohledu na `.installed`.

Pouze při neúplném schématu a po záloze spusťte `php install.php --apply`. Příkaz načte stávající konfiguraci, zamkne souběžné inicializace, spustí opakovatelné MySQL skripty a ověří objekty, verze migrací, klíče tabulky safety_cases a čitelnost pohledu. Neukládá ani nemění DB heslo. Nevkládá uživatelská data a nemaže existující tabulky. MySQL DDL se automaticky potvrzuje; případná chyba může zanechat částečně hotové schéma. Oprava umožňuje opakování po odstranění příčiny.

Bez SSH/CLI použijte po záloze SQL konzoli hostingu pro tři soubory `sql/mysql/` ve stanoveném pořadí. Neodemykejte veřejný instalátor. SQL konzole a CLI jsou alternativy, nespouštějte je souběžně. Při chybě zastavte postup a zkontrolujte verzi MySQL, oprávnění a existující schéma.

Úspěch potvrzuje HTTP 200 z `/api/health.php` s `ok: true`, `database: connected`, `schema: ready` a `release: safe-install-v1`. HTTP 503 znamená neúplné schéma nebo nedostupnou databázi; veřejná odpověď neobsahuje podrobnosti konfigurace. Instalátor lze po kontrole odstranit; i při jeho ponechání je přes web trvale zakázán.

## Ověření změn

Workflow `Check MySQL installation` používá izolovanou MySQL 8.0 a testovací konfiguraci. Testuje částečnou instalaci, opakování s existujícími daty, obnovu chybějícího pohledu, HTTP 503 před dokončením, HTTP 200 po dokončení a HTTP 403 instalátoru i bez `.installed`. Nevytváří připojení k produkci a nic nenasazuje.
