# Testy Backend - LuloCustoms Shop

Automatyczne testy dla PHP backendu.

## 📋 Dostępne testy

### 1. `test-backend.php` - Testy PHP (Unit tests)

Testuje bezpośrednio kod PHP bez uruchamiania serwera:
- ✅ Połączenie z bazą danych
- ✅ Konfiguracja (constants, functions)
- ✅ Klasa Database (CRUD operations)
- ✅ Funkcje autentykacji
- ✅ Struktura tabel w bazie
- ✅ Walidacja danych

**Wymagania:**
- PHP 7.4+
- MySQL database skonfigurowana
- Poprawne dane w `api/config.php`

**Uruchomienie:**
```bash
php tests/test-backend.php
```

**Przykładowy output:**
```
🧪 LuloCustoms Shop - Backend Tests
=====================================

📦 Database Connection Tests
----------------------------------------
✓ config.php file exists
✓ database.php file exists
✓ Database constants are defined
✓ Can connect to MySQL database

⚙️  Configuration Tests
----------------------------------------
✓ Przelewy24 constants are defined
✓ Helper functions are defined
✓ Email validation works
✓ String sanitization works

... [więcej testów] ...

=====================================
📊 Test Summary
=====================================

Total tests: 24
Passed: 24
Success rate: 100.0%

🎉 All tests passed!
```

---

### 2. `test-api.sh` - Testy API (Integration tests)

Testuje API endpoints przez HTTP (curl):
- ✅ Products API (GET, POST, PUT, DELETE)
- ✅ Auth API (login, logout, check)
- ✅ Orders API (create, list)
- ✅ Payment API (init, verify, status)
- ✅ Security (unauthorized access, invalid params)

**Wymagania:**
- bash/sh
- curl
- Działający serwer PHP

**Uruchomienie lokalnie:**

1. **Uruchom PHP built-in server:**
   ```bash
   cd /path/to/lulocustoms-shop
   php -S localhost:8000
   ```

2. **W drugim terminalu uruchom testy:**
   ```bash
   bash tests/test-api.sh http://localhost:8000
   ```

**Uruchomienie na hostingu:**
```bash
bash tests/test-api.sh https://sklep.lulocustoms.pl
```

**Przykładowy output:**
```
🧪 LuloCustoms Shop - API Tests
=====================================
Testing API at: http://localhost:8000

📦 Products API Tests
----------------------------------------
Testing: GET all products... ✓ PASSED (HTTP 200)
Testing: GET single product... ✓ PASSED (HTTP 200)
Testing: GET non-existent product... ✓ PASSED (HTTP 404)

🔐 Auth API Tests
----------------------------------------
Testing: Check auth status... ✓ PASSED (HTTP 200)
Testing: Login with invalid credentials... ✓ PASSED (HTTP 401)

... [więcej testów] ...

=====================================
📊 Test Summary
=====================================

Total tests: 14
Passed: 14
Success rate: 100.0%

🎉 All tests passed!
```

---

## 🚀 Przed wdrożeniem na hosting

**Musisz wykonać te testy:**

### Lokalnie (przed uplodem):
```bash
# 1. Sprawdź kod PHP
php tests/test-backend.php

# 2. Uruchom serwer lokalny
php -S localhost:8000

# 3. Testuj API
bash tests/test-api.sh http://localhost:8000
```

### Na hostingu (po uploadzie):
```bash
# Testuj API na żywym serwerze
bash tests/test-api.sh https://sklep.lulocustoms.pl
```

---

## ⚠️ Troubleshooting

### test-backend.php

**Problem:** "Cannot connect to database"
- Sprawdź `api/config.php` - czy dane MySQL są poprawne?
- Czy baza `lulocustoms_shop` istnieje?
- Czy zaimportowałeś `database/schema.sql`?

**Problem:** "Table 'xxx' does not exist"
- Zaimportuj schemat: `mysql < database/schema.sql`

**Problem:** "No admin user found"
- Zaimportuj dane testowe: `mysql < database/seed.sql`

### test-api.sh

**Problem:** "Connection refused"
- Czy serwer PHP działa? (`php -S localhost:8000`)
- Czy podałeś poprawny URL?

**Problem:** "HTTP 500 errors"
- Sprawdź logi PHP (`php -S localhost:8000` pokaże błędy)
- Sprawdź czy baza danych działa
- Sprawdź `api/config.php`

**Problem:** Na Windows bash nie działa
- Użyj Git Bash lub WSL
- Lub testuj ręcznie przez curl:
  ```bash
  curl http://localhost:8000/api/products.php
  ```

---

## 📝 Dodawanie nowych testów

### Do test-backend.php:

```php
$this->test("Your test name", function() {
    // Twój kod testowy
    if (/* warunek sukcesu */) {
        return true;
    } else {
        return "Error message";
    }
});
```

### Do test-api.sh:

```bash
test_api "Test name" "METHOD" "/endpoint" "json_data" "expected_status"

# Przykład:
test_api "Create product" "POST" "/api/products.php" \
    '{"name":"Test","price":99.99}' "201"
```

---

## ✅ Co powinno przejść przed deploymentem?

- [x] test-backend.php - wszystkie testy ✓
- [x] test-api.sh - wszystkie testy ✓
- [ ] Testy manualne:
  - [ ] Logowanie do panelu admina
  - [ ] Dodanie produktu przez panel
  - [ ] Złożenie testowego zamówienia
  - [ ] Płatność testowa (Przelewy24 sandbox)

---

## 🔄 CI/CD (przyszłość)

W przyszłości można dodać:
- GitHub Actions - automatyczne testy przy push
- PHPUnit - bardziej zaawansowane testy
- Code coverage reports
- Automatyczne deploymenty po przejściu testów
