-- ==========================================
-- LuloCustoms Shop - Seed Data (Dane testowe)
-- ==========================================
-- UWAGA: Te dane są tylko do testów!
-- Po pierwszym logowaniu ZMIEŃ hasło admina!
-- ==========================================

-- Czyszczenie starych danych (UWAGA: to usuwa wszystko!)
-- Odkomentuj tylko jeśli chcesz zresetować bazę
-- TRUNCATE TABLE `order_items`;
-- TRUNCATE TABLE `orders`;
-- TRUNCATE TABLE `products`;
-- TRUNCATE TABLE `admin_users`;

-- ==========================================
-- 1. ADMINISTRATOR
-- ==========================================
-- Email: admin@lulocustoms.pl
-- Hasło: Admin123!
-- WAŻNE: Po pierwszym logowaniu ZMIEŃ to hasło!
-- ==========================================

INSERT INTO `admin_users` (`email`, `password_hash`, `created_at`) VALUES
('admin@lulocustoms.pl', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', NOW());

-- Hasło zahashowane przez PHP password_hash('Admin123!', PASSWORD_BCRYPT)
-- W prawdziwym systemie wygeneruj nowe hasło!

-- ==========================================
-- 2. PRODUKTY TESTOWE
-- ==========================================
-- 5 przykładowych produktów custom
-- Zdjęcia: placeholdery (zmień po wdrożeniu)
-- ==========================================

INSERT INTO `products` (`name`, `description`, `price`, `image_url`, `stock`, `active`, `created_at`) VALUES
(
  'Custom Mug - Personalizowany Kubek',
  'Ceramiczny kubek z własnym nadrukiem. Wysoka jakość druku, odporny na zmywarkę. Pojemność 330ml. Idealny na prezent!',
  49.99,
  '/images/products/mug-placeholder.jpg',
  25,
  1,
  NOW()
),
(
  'Custom T-Shirt - Koszulka z Nadrukiem',
  'Bawełniana koszulka 100% cotton z Twoim projektem. Dostępne rozmiary: S-XXL. Nadruk DTG wysokiej jakości.',
  89.99,
  '/images/products/tshirt-placeholder.jpg',
  15,
  1,
  NOW()
),
(
  'Custom Phone Case - Etui na Telefon',
  'Spersonalizowane etui na telefon. Dopasowane do większości modeli iPhone i Samsung. Twarde plecy, miękkie boki.',
  59.99,
  '/images/products/phone-case-placeholder.jpg',
  30,
  1,
  NOW()
),
(
  'Custom Poster - Plakat A3',
  'Wysokiej jakości plakat A3 (297 x 420 mm) z Twoim wzorem. Druk na papierze fotograficznym 250g. Wysyłka w tubie.',
  39.99,
  '/images/products/poster-placeholder.jpg',
  50,
  1,
  NOW()
),
(
  'Custom Hoodie - Bluza z Kapturem',
  'Ciepła bluza kangurka z własnym nadrukiem. 80% bawełna, 20% poliester. Dostępne kolory: czarna, szara, granatowa.',
  129.99,
  '/images/products/hoodie-placeholder.jpg',
  10,
  1,
  NOW()
);

-- ==========================================
-- 3. ZAMÓWIENIE TESTOWE (opcjonalnie)
-- ==========================================
-- Przykładowe zamówienie do testowania
-- Możesz odkomentować jeśli chcesz mieć dane testowe
-- ==========================================

-- INSERT INTO `orders` (`order_number`, `customer_name`, `customer_email`, `customer_phone`, `customer_address`, `total_price`, `payment_status`, `created_at`) VALUES
-- ('ORD-2025-00001', 'Jan Kowalski', 'jan.kowalski@example.com', '+48 123 456 789', 'ul. Testowa 1, 00-001 Warszawa', 149.98, 'paid', NOW());

-- INSERT INTO `order_items` (`order_id`, `product_id`, `product_name`, `product_price`, `quantity`) VALUES
-- (1, 1, 'Custom Mug - Personalizowany Kubek', 49.99, 2),
-- (1, 2, 'Custom T-Shirt - Koszulka z Nadrukiem', 89.99, 1);

-- ==========================================
-- Koniec danych testowych
-- ==========================================

-- Sprawdzenie co zostało dodane:
SELECT 'Admin users:' as info, COUNT(*) as count FROM admin_users
UNION ALL
SELECT 'Products:', COUNT(*) FROM products
UNION ALL
SELECT 'Orders:', COUNT(*) FROM orders
UNION ALL
SELECT 'Order items:', COUNT(*) FROM order_items;
