# Moje peníze

Soukromá finanční, bezpečnostní a klientská aplikace pro mojepenize.jirijanousek.cz.

## Moduly
- 💰 Moje peníze
- 🛡️ Moje bezpečí
- ⚖️ Rebalancování portfolia
- ❤️ Péče o klienta

## Nasazení
Produkce používá PHP 8.1+ s PDO MySQL a MySQL 8.0+. Konfigurace se načítá z existujícího serverového `config.php`; `.env` se tímto kódem nenačítá. Heslo při nasazování neměňte ani nevkládejte do repozitáře.

Používejte pouze `sql/mysql/001_schema.sql`, `002_modules.sql` a `003_dashboard.sql` v tomto pořadí. Starší PostgreSQL skripty v `sql/` nejsou určeny pro tuto PHP aplikaci. Podrobný postup je v [DEPLOYMENT.md](DEPLOYMENT.md).

> Produkční klientská data nepatří do repozitáře.

## Pracovní obrazovky

Čtyři sekce mají navigaci, přidávání a úpravy cílů, kontrol zabezpečení a servisních úkolů; rebalancování počítá rozdíly proti uživatelem zadaným podílům. Záznamy této verze se ukládají pouze v localStorage daného prohlížeče. Nejsou napojeny na serverové klientské tabulky, přihlášení ani synchronizaci. Formuláře žádná data na server neposílají.

Ověření výpočtů: `node tests/modules.cjs`. Ověření v prohlížeči: navigace všech sekcí, přidání a úprava cíle, zachování po obnovení, odebrání a vrácení, přidání bezpečnostní kontroly, dokončení a znovuotevření úkolu, odmítnutí součtu podílů odlišného od 100 %.
