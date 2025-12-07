# Instalacja Bazy Danych

## 📋 Wymagania

- MySQL 8.0+ na hostingu
- Dostęp do phpMyAdmin (panel hostingu)
- Dane dostępowe do bazy MySQL

## 🚀 Instrukcja krok po kroku

### 1. Zaloguj się do phpMyAdmin

1. Wejdź do panelu hostingu
2. Znajdź phpMyAdmin (zazwyczaj w sekcji "Bazy danych")
3. Zaloguj się

### 2. Utwórz nową bazę danych

1. W phpMyAdmin kliknij **"Nowa"** (New) w lewym menu
2. Nazwa bazy: `lulocustoms_shop`
3. Kodowanie: `utf8mb4_unicode_ci`
4. Kliknij **"Utwórz"** (Create)

### 3. Zaimportuj schemat bazy

1. Wybierz bazę `lulocustoms_shop` z lewego menu
2. Kliknij zakładkę **"Import"**
3. Kliknij **"Wybierz plik"** (Choose File)
4. Wybierz plik: `database/schema.sql`
5. Kliknij **"Wykonaj"** (Go) na dole strony
6. ✅ Powinno pokazać: "Import zakończony pomyślnie"

### 4. Zaimportuj dane testowe (opcjonalnie)

1. W tej samej zakładce **"Import"**
2. Wybierz plik: `database/seed.sql`
3. Kliknij **"Wykonaj"** (Go)
4. ✅ To doda:
   - 1 konto admina (email: `admin@lulocustoms.pl`, hasło: `Admin123!`)
   - 5 produktów testowych

### 5. Sprawdź czy wszystko działa

1. Kliknij na bazę `lulocustoms_shop` w lewym menu
2. Powinieneś zobaczyć 4 tabele:
   - ✅ `admin_users` (1 wiersz)
   - ✅ `products` (5 wierszy)
   - ✅ `orders` (0 wierszy)
   - ✅ `order_items` (0 wierszy)

### 6. Zapisz dane dostępowe

**WAŻNE!** Zapisz te informacje - będą potrzebne w `api/config.php`:

```
DB_HOST: localhost (lub adres z panelu hostingu)
DB_NAME: lulocustoms_shop
DB_USER: [twój user z phpMyAdmin]
DB_PASS: [twoje hasło z phpMyAdmin]
```

## 🔐 Pierwsze logowanie do panelu admina

Po wdrożeniu aplikacji:

**Email:** `admin@lulocustoms.pl`
**Hasło:** `Admin123!`

⚠️ **NATYCHMIAST ZMIEŃ HASŁO** po pierwszym logowaniu!

## 🛠️ Opcje zaawansowane

### Reset bazy danych

Jeśli chcesz zresetować bazę:

1. Otwórz `seed.sql`
2. Odkomentuj linie z `TRUNCATE TABLE` na początku pliku
3. Zaimportuj ponownie `seed.sql`

### Backup bazy

**Przed każdą większą zmianą zrób backup:**

1. phpMyAdmin → wybierz bazę
2. Zakładka **"Eksport"** (Export)
3. Metoda: **"Szybka"** (Quick)
4. Format: **SQL**
5. Kliknij **"Wykonaj"** (Go)
6. Zapisz plik `.sql` w bezpiecznym miejscu

## ❓ Problemy?

### "Access denied for user..."
- Sprawdź dane logowania do MySQL
- Upewnij się że user ma uprawnienia do bazy

### "Table already exists"
- Baza już istnieje
- Usuń starą bazę lub użyj innej nazwy

### "Import failed"
- Sprawdź czy plik SQL nie jest zbyt duży (limit w phpMyAdmin)
- Sprawdź kodowanie pliku (powinno być UTF-8)

## 📝 Notatki

- Baza używa `utf8mb4` - obsługuje emoji i znaki specjalne
- Hasła są hashowane przez PHP `password_hash()` (bcrypt)
- Foreign keys zapewniają integralność danych
- Wszystkie daty w formacie `YYYY-MM-DD HH:MM:SS`
