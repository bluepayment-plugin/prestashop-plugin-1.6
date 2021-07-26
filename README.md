# Moduł Płatności BlueMedia dla platformy PrestaShop 1.6

## Podstawowe informacje
BluePayment to moduł płatności umożliwiający realizację transakcji bezgotówkowych w sklepie opartym na platformie PrestaShop 1.6. UWAGA! Ze względu na zakończenie wsparcia dla PrestaShop 1.6 rekomendujemy aktualizację platformy sprzedażowej do wersji 1.7 oraz instalację modułu płatności z PrestaShop Addons. Wtyczkę PrestaShop 1.7 możesz pobrać [tutaj.](https://github.com/bluepayment-plugin/prestashop-plugin-1.7/archive/refs/heads/master.zip)

### Główne funkcje
Do najważniejszych funkcji modułu zalicza się:
-	realizację płatności online poprzez odpowiednie zbudowanie startu transakcji
-	obsługę powiadomień o statusie transakcji (notyfikacje XML)
-	obsługę wielu sklepów jednocześnie z użyciem jednego modułu
-	obsługę zakupów bez rejestracji w serwisie
-	obsługę dwóch trybów działania – testowego i produkcyjnego (dla każdego z nich wymagane są osobne dane kont, po które zwróć się do nas)
-	wybór banku po stronie sklepu i bezpośrednie przekierowanie do płatności w wybranym banku

### Wymagania
-	Wersja PrestaShop: 1.4.5x - 1.6.1.11
-	Wersja PHP zgodna z wymaganiami względem danej wersji sklepu


## Instalacja wtyczki

1. Pobierz najnowszą wersję wtyczki klikając [tutaj](https://github.com/bluepayment-plugin/prestashop-plugin-1.6/archive/refs/heads/master.zip).
2. Wejdź na http(s)://domena_sklepu.pl/nazwa_katalogu_administratora i zaloguj się do swojego konta administratora używając loginu i hasła.

![Logowanie](https://user-images.githubusercontent.com/87177993/126952274-32347bad-5c63-4aab-bd38-bf78c360c3a5.jpg)

3.	Po zalogowaniu się przejdź do zakładki **Moduły > Moduły i usługi** i: 
-	kliknij **Dodaj nowy moduł** (widoczny w prawym górnym rogu), by wgrać paczkę plików, którą pobrałeś w poprzednim kroku;

![Dodaj nowy moduł](https://user-images.githubusercontent.com/87177993/126952689-01fb8d1f-9218-468f-abc1-0069bf90735e.jpg)
*(Po kliknięciu przycisku pojawi się okno umożliwiające wybór pliku z komputera.)*

●	kliknij **Prześlij moduł**

![Prześlij moduł](https://user-images.githubusercontent.com/87177993/126952792-ae07c4cb-7c3f-49b1-b6aa-a0f5d725b755.jpg)

Po wgraniu modułu – należy go zainstalować. Możesz to zrobić na dwa sposoby:
-	odszukując go za pomocą wyszukiwarki;
-	wybierając kategorię: **Płatności, bramki i operatorzy** i klikając **Instaluj**.

![Instaluj](https://user-images.githubusercontent.com/87177993/126952951-de3394ee-9ee9-45c8-8709-564ca98a6298.png)

Na kolejnym ekranie, który ci się pokaże, musisz potwierdzić chęć instalacji modułu. Wystarczy, że klikniesz: **Kontynuuj instalację**. Gdy instalacje się zakończy, system przeniesie cię automatycznie do Konfiguracji modułu.

![Kontynuuj instalację](https://user-images.githubusercontent.com/87177993/126953651-298d49fa-848e-45be-b9c6-491720965eb3.jpg)


