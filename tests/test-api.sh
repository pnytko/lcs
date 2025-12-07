#!/bin/bash
##############################################
# LuloCustoms Shop - API Tests (curl)
##############################################
# UWAGA: Wymaga działającego serwera PHP!
#
# Uruchom lokalnie:
# 1. php -S localhost:8000
# 2. bash tests/test-api.sh http://localhost:8000
#
# Lub testuj na hostingu:
# bash tests/test-api.sh https://sklep.lulocustoms.pl
##############################################

# Kolory
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Counters
PASSED=0
FAILED=0

# API URL (z argumentu lub localhost)
API_URL="${1:-http://localhost:8000}"

echo -e "${BLUE}🧪 LuloCustoms Shop - API Tests${NC}"
echo -e "${BLUE}=====================================${NC}"
echo -e "Testing API at: ${BLUE}$API_URL${NC}\n"

# Test function
test_api() {
    local name="$1"
    local method="$2"
    local endpoint="$3"
    local data="$4"
    local expected_status="$5"

    echo -n "Testing: $name... "

    if [ "$method" == "GET" ]; then
        response=$(curl -s -w "\n%{http_code}" "$API_URL$endpoint")
    else
        response=$(curl -s -w "\n%{http_code}" -X "$method" \
            -H "Content-Type: application/json" \
            -d "$data" \
            "$API_URL$endpoint")
    fi

    # Extract status code (last line)
    status_code=$(echo "$response" | tail -n1)
    # Extract body (everything except last line)
    body=$(echo "$response" | sed '$d')

    if [ "$status_code" == "$expected_status" ]; then
        echo -e "${GREEN}✓ PASSED${NC} (HTTP $status_code)"
        PASSED=$((PASSED + 1))
        return 0
    else
        echo -e "${RED}✗ FAILED${NC} (Expected HTTP $expected_status, got $status_code)"
        echo -e "${RED}Response: $body${NC}"
        FAILED=$((FAILED + 1))
        return 1
    fi
}

echo -e "${YELLOW}📦 Products API Tests${NC}"
echo "----------------------------------------"

# Test 1: Get all products
test_api "GET all products" "GET" "/api/products.php" "" "200"

# Test 2: Get single product (assuming ID 1 exists)
test_api "GET single product" "GET" "/api/products.php?id=1" "" "200"

# Test 3: Get non-existent product
test_api "GET non-existent product" "GET" "/api/products.php?id=99999" "" "404"

echo ""
echo -e "${YELLOW}🔐 Auth API Tests${NC}"
echo "----------------------------------------"

# Test 4: Check auth status (should be not logged in)
test_api "Check auth status" "GET" "/api/auth.php?action=check" "" "200"

# Test 5: Login with invalid credentials
test_api "Login with invalid credentials" "POST" "/api/auth.php?action=login" \
    '{"email":"wrong@email.com","password":"wrongpassword"}' "401"

# Test 6: Login with missing data
test_api "Login with missing data" "POST" "/api/auth.php?action=login" \
    '{"email":""}' "400"

# Note: We can't test successful login without knowing the actual password
# On production, you'd use test credentials

echo ""
echo -e "${YELLOW}📋 Orders API Tests${NC}"
echo "----------------------------------------"

# Test 7: Create order with invalid data (missing fields)
test_api "Create order - missing fields" "POST" "/api/orders.php" \
    '{"customer_name":""}' "400"

# Test 8: Create order with invalid email
test_api "Create order - invalid email" "POST" "/api/orders.php" \
    '{"customer_name":"Test","customer_email":"invalid","customer_phone":"123","customer_address":"Test","items":[]}' "400"

# Test 9: Create order with no items
test_api "Create order - no items" "POST" "/api/orders.php" \
    '{"customer_name":"Test","customer_email":"test@test.com","customer_phone":"123","customer_address":"Test","items":[]}' "400"

echo ""
echo -e "${YELLOW}💳 Payment API Tests${NC}"
echo "----------------------------------------"

# Test 10: Payment init without order_id
test_api "Payment init - missing order_id" "POST" "/api/payment.php?action=init" \
    '{}' "400"

# Test 11: Payment status without session_id
test_api "Payment status - missing session_id" "GET" "/api/payment.php?action=status" "" "400"

echo ""
echo -e "${YELLOW}🔒 Security Tests${NC}"
echo "----------------------------------------"

# Test 12: Invalid action parameter
test_api "Auth - invalid action" "GET" "/api/auth.php?action=invalid" "" "400"

# Test 13: Products - unauthorized POST (should fail without auth)
test_api "Products - unauthorized POST" "POST" "/api/products.php" \
    '{"name":"Test","price":100}' "401"

# Test 14: Products - unauthorized DELETE
test_api "Products - unauthorized DELETE" "DELETE" "/api/products.php?id=1" "" "401"

echo ""
echo -e "${BLUE}=====================================${NC}"
echo -e "${BLUE}📊 Test Summary${NC}"
echo -e "${BLUE}=====================================${NC}"

TOTAL=$((PASSED + FAILED))
if [ $TOTAL -gt 0 ]; then
    PERCENTAGE=$(awk "BEGIN {printf \"%.1f\", ($PASSED/$TOTAL)*100}")
else
    PERCENTAGE=0
fi

echo ""
echo "Total tests: $TOTAL"
echo -e "${GREEN}Passed: $PASSED${NC}"
if [ $FAILED -gt 0 ]; then
    echo -e "${RED}Failed: $FAILED${NC}"
fi
echo "Success rate: ${PERCENTAGE}%"
echo ""

if [ $FAILED -eq 0 ]; then
    echo -e "${GREEN}🎉 All tests passed!${NC}\n"
    exit 0
else
    echo -e "${RED}❌ Some tests failed.${NC}\n"
    exit 1
fi
