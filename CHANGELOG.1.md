# Change Log (v1.0.0 to v2.0.0 excluded)
All notable changes to this project will be documented in this file.

## [1.12.0] - 2016-04-02
- ValidateForm: přidáno medové zabezpečení formuláře (honeypot)
- ValidateForm: odstraněno časové omezení formuláře pro admina
- ContactForm: vizuální zjednodušení náhledu zprávy
- ContactForm: přidána výchozí zpráva do základního formuláře
- LogViewer: rozdělení na uživatelské, systémové a mailové logy
- Logger: nový formát logovacích souborů

## [1.11.0] - 2016-02-27
- SyntaxCodeMirror: přidána klávesová zkratka (dvojhmat) Ctrl+K Ctrl+D
- SyntaxCodeMirror: přidán odkaz na klávesové zkratky
- Deaktivace dynamiky pro naktivní verzi CMS
- ContactForm: zobrazení hlášky o neodeslání formuláře - přihlášenému uživateli
- Přesunutí souboru s historií verzí na CHANGELOG
- Automatizace a změna formátu historie verzí

## [1.10] - 2016-02-01
- Globální vylepšení výchozích stylů pro tisk
- ContactForm: v administračním režimu zprávu zobrazí (neodesílá)

## [1.9.5] - 2016-01-07
- ContactForm: přihlášenému uživateli odeslanou zprávu jen zobrazí
- Admin: upozornění na aktualizaci souborové mezipaměti
- Admin: upozornění na aktualizaci serverové mezipaměti
- Htmlplus: povinný atribut value pro checkbox a radio
- Admin: oprava práce s různými soubory, issue #28
- admin: oprava hlášení o úspěšném uložení, issue #30
- fileHandler: efektivní kontrola mezipaměti

## [1.9.3] - 2015-12-21
- contactForm: oprava umístění skryté položky formuláře, issue #39
- Convertor: oprava názvu lokálního importu
- Convertor: oprava importu, issue #35
- BasicLayout.css: oprava pravé mezery vedle form input

## [1.9.2] - 2015-12-15
- Admin: přidán odkaz pro ignorování mezipaměti
- Admin: zachování parametrů při volbě souboru
- Admin: možnost ignorování serverové mezipaměti
- perzistentní URL parametr cache
- FileHandler: změna adresáře náhledu z thumb na thumbs
- Vlastní knihovny pro optimalizaci zdrojů CSS/JS
- global: zrušení technologie Grunt, zdrojové soubory pomocí findex.php

## [1.9.1] - 2015-12-02
- fileHandler: opraveno mazání souborové mezipaměti
- FileHandler: vylepšení práce se zdrojovými soubory
- global: uživatelské mazání serverové mezipaměti
- global: ladící režim
- GlobalMenu: minimálně jedna úroveň
- global: zvláštní souborová mezipaměť pro různé verze systému

## [1.9.0] - 2015-11-28
- global: cesta ke zdrojovým souborům podle verze systému
- Admin: zobrazení automatických oprav
- completable.js: podpora zástupnéh znaku *
- CodeMirror: oprava vyhledávání vzhledem k pozici kurzoru
- LogViewer: oprava počtu zobrazených výsledků
- UrlHandler: oprava konfiguračního souboru
- SyntaxCodeMirror: oprava označení nalezeného textu, issue #36
- cms-default.css vylepšení stylů
- fileHandler: kontrola souborové mezipaměti
- domBuilder: cache xml files, check cache mtime

## [1.8.8] - 2015-11-19
- ielte7.xsl: hláška pro starý prohlížeč IE7-
- completable.js: přidána podpora pro deaktivované soubory #disabled
- Admin: deaktivované soubory do nabídky, issue #29
- Admin: varování o neaktuální mezipaměti
- adminmenu.xsl: přidán odkaz na zpětnou vazbu
- InputVar: odkaz na zpštnou vazbud jako nová proměnná
- EmailBreaker: oprava složeného zápisu, issue #32
- ContentLink: oprava řeči

## [1.8.6] - 2015-11-11
- úprava výchozích stylů, issue #21 a issue #26
- Codemirror: chytré tlačítko [home]
- addressable.js: opravy issue #25 a issue #27 (7af0b5f, 0d9c5ed)
- přidána podpora různých jazyků obsahu
- Convertor: oprava pro neexistující soubory, issue #22
- rng: oprava definice elementu form, issue #1 (8e0bcd0, df8c0d2)

## [1.8.5] - 2015-10-01
- completable.js: šipka dolů a kliknutí otevře nabídku
- admin.js: podpora igcms.js
- completable.js: podpora igcms.js
- editable.js: podpora igcms.js
- addressable.js: podpora igcms.js
- eventable.js: podpora igcms.js
- hideable.js: podpora igcms.js
- selectable.js: podpora igcms.js
- filterable.js: podpora igcms.js
- toc.js: přejmenováno na tocable.js
- scrolltop.js: přejmenováno na scrolltopable.js, omezení na body.scrolltopable
- global: výchozí třída body.fragmentable
- fragmentable.js: omezení na body.fragmentable
- fragments.js: přejmenováno na fragmentable.js, přidán igcms.js
- completable.js: nezávislost na velikosti písmen při vyplňování
- filterable.js: třídy do elementů (124b32c, ff26946, 49d064a)
- filterable.js: oprava historie prohlížeče
- Sitemap: odstraněn parametr lastmod (296dde1, 7306a12)
- filterable.js: přidána podpora GA
- filterable.js: vylepšení a opravy

## [1.8.4] - 2015-08-24
- global: drobné opravy souborů javascript
- EmailBreaker: element span místo a, přidáno podtržítko
- filterable.js: filtrování seznamů podle klíčových slov

## [1.8.3] - 2015-08-23
- Convertor: podpora víceřádkového elementu code
- ContactForm: varování, pokud není definovaný příjemce
- Admin: přesměrování link editovaného HTML souboru

## [1.8.2] - 2015-08-18
- completable.js: vylepšení UX, filtrování, řazení
- UrlHandler: přesměrování mezi http/https
- html+ přidána podpora atributu pattern pro select

## [1.8.1] - 2015-08-07
- Sitemap: nové rozšíření generující /sitemap.xml
- Convertor: podpora řádkových elementů v definičních seznamech
- Convertor: podpora řádkových elementů ve shrnutí
- Admin: přidána volba smazání mezipaměti (cache) při ukládání změn

## [1.8.0] - 2015-07-08
- Agregator: přidána podpora hromadného vkládání obrázků
- ValidateForm: vlastní prodleva mezi odesláním formuláře pomocí třídy
- Admin: pole s výběrem souboru na první stisknutí tabulátoru
- SyntaxCodeMirrror.js: globální zkratka Shift+F11 s fokusem
- cms-default.css: flexibilní šířka elementů input na malých obrazovkách
- basic.css: podtržení odkazů v globální navigaci zápatí
- admin/convertor: ukládání záloh veškerých změn
- přidána podpora pro input type number
- Basket: nové rozšíření nákupní košík
- vypnutí PageSpeed rozšřeno o Grunt na Režim ladění
- cms-print.css: skrytí hodnot placeholder pro tisk
- CodeMirror: přidány funkce a klávesové zkratky
- ContactForm: změna výchozí konfigurace

## [1.7.0] - 2015-06-27
- HtmlOutput: přidán browserdetect.js a iefixes.js
- FileHandler: podpora technologie Grunt
- výchozí CSS: kompatibilita prohlížečů
- HtmlOutput: přidán atribut 'if' pro elementy stylesheet a jsFile
- ValidateForm: 2 minuty prodleva pomocí souboru IP v názvu, možnost banu pomocí .IP souboru
- EmailBreaker: nové rozšíření na boření e-mailových adres

## [1.6.2] - 2015-06-05
- HtmlOutput: systémové favicony jsou nastavitelné
- LinkList: kontrola existence lokálních odkazů
- LinkList: zobrazuje skutečné názvy lokálních odkazů
- Indikace systémového hlášení zbarvením ikony prohlížeče
- LinkList: nové rozšíření pro zobrazení seznamu odkazů
- Možnost zasílání událostí do GA, eventable.js
- Přidání basic.css
- Admin: potvrzení uložení neaktivního souboru
- InputVar: přidání nepovinného hesla
- HtmlOutput: přidání nastavení robots
- CodeMirror: klávesová zkratka ctrl+g
- SyntaxCodeMirror: tlačítko pro de/aktivaci
- InputVar: přidáno uživatelské rozhranní
- ContactForm: logování poslaných zpráv

## [1.6.1] - 2015-05-19
- ValidateForm: nové rozšíření pro kontrolu formuláře
- FileHandler: přidán odkaz na smazání dočasných souborů
- addressable.js: podpora polí typu email a search
- Administrátorské nabídka jako první prvek v zápatí
- Admin: přidání pole pro výběr editovaného souboru
- Odkaz na smazání mezipaměti
- DOMElementPlus: smazání elementu pomocí prázdné proměnné
- ContactForm: podpora formulářových prvků HTML5
- HtmlOutput: výstupní kód stánek v HTML5
- Agregator: přidání volitelného atributu wrapper

## [1.6.0] - 2015-05-15
- InputVar: název systému v zápatí jako proměnná

## [1.5.3] - 2015-05-09
- Vylepšené rozhraní administrace
- Nová funkcionalita addressable pro odkázání vyplněného formuláře
- Nové rozšíření FillForm vyplňující špatně vyplněné formuláře
- FileHandler: nový formát obrázku "preview"
- Admin: uložit a přejít na editovaný soubor, pokud cesta existuje
- ContentLink: hodnota drobečkové navigace na úvodní stránce podle atributu title hlavního nadpisu

## [1.5.2] - 2015-04-18
- TOC: přidání parametru maximální hloubky
- Agregator: transformace pro umístění informací o dokumentu agregator.xsl
- Agregator: konfigurovatelné informace o dokumentu

## [1.5.0] - 2015-04-12
- Agregátor informace o dokumentu docinfo
- Odkaz na přesunutí stránky nahoru scrolltop.js
- Administrace dostupná pouze přes HTTPS

## [1.4.2] - 2015-03-31
- Vylepšení globálních stylů
- Odkaz na vypnutí PageSpeed

## [1.4.0] - 2015-03-15
- Auth: přidána možnost zakázat všem kromě admina

## [1.3.0] - 2015-03-10
- Komentáře v kódu se zobrazí pouze vlastníkovi webu
- InputVar: nová struktura uživatelských proměnných
- GlobalMenu: přidává třídu current do všech položek v cestě

## [1.2.0] - 2015-03-06
- Agregátor: přidány odkazy pro editaci

## [1.1.0] - 2015-02-21
- ContentBalancer: přidána možnost nezobrazovat
- ContentBalancer: vlastní sestavy s odkazy
- Agregátor: přidána podpora prefixů odkazů

## [1.0.1] - 2015-02-15
- Admin: vedle XML zobrazuje i HTML soubory rozšíření
- Přidáno automatické generování titulků k odkazům

## [1.0.0] - 2015-02-13
- cms-default.css: barvy a výchozí font systémových hlášení
- Explicitně zobrazená verze vyžaduje přihlášení
- Podpora zobrazení webu na všech dostupných verzí přímo z URL
- Xhtml11: možnost vlastního souboru robots.txt
- Každá nová poddoména vyžaduje přihlášení
- Změna adresářové struktury