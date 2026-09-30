# Moje peníze

Soukromá finanční, bezpečnostní a klientská aplikace pro mojepenize.jirijanousek.cz.

## Moduly
- 💰 Moje peníze
- 🛡️ Moje bezpečí
- ⚖️ Rebalancování portfolia
- ❤️ Péče o klienta

## Nasazení
Web root obsahuje statický bezpečný start aplikace. SQL migrace jsou v adresáři `sql/` a musí být spuštěny proti PostgreSQL v pořadí podle prefixu. Tajné údaje patří pouze do serverového `.env`, nikdy do GitHubu.

> Produkční klientská data nepatří do repozitáře.
