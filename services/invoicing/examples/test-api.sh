#!/bin/bash

# API Testing Script for Invoicing Microservice

BASE_URL="http://localhost:8001"
COMPANY_ID="test-company-$(date +%s)"
JWT_SECRET="your-super-secret-jwt-key-change-in-production"

# Colors for output
GREEN='\033[0;32m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

echo -e "${BLUE}=== Invoicing Microservice API Tests ===${NC}\n"

# Function to make JWT token
generate_token() {
  local user_id="user-$(date +%s)"
  local payload=$(cat <<EOF
{
  "userId": "$user_id",
  "companyId": "$COMPANY_ID",
  "email": "test@example.com"
}
EOF
)
  # For testing, we use a simple approach (in production, use proper JWT generation)
  echo "Generated token for company: $COMPANY_ID"
}

# Generate test token
TOKEN="eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJ1c2VySWQiOiJ0ZXN0LXVzZXIiLCJjb21wYW55SWQiOiIkQ09NUEFOWV9JRCIsImVtYWlsIjoidGVzdEBleGFtcGxlLmNvbSJ9.signature"

echo -e "${GREEN}1. Health Check${NC}"
curl -s -X GET "$BASE_URL/health" | jq '.'

echo -e "\n${GREEN}2. Readiness Check${NC}"
curl -s -X GET "$BASE_URL/ready" | jq '.'

echo -e "\n${GREEN}3. Create Invoice${NC}"
INVOICE_RESPONSE=$(curl -s -X POST "$BASE_URL/api/v1/invoices" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer $TOKEN" \
  -H "company-id: $COMPANY_ID" \
  -d '{
    "customerId": "customer-001",
    "customerName": "John Doe",
    "customerEmail": "john@example.com",
    "amount": 1000,
    "taxAmount": 100,
    "currency": "USD",
    "invoiceDate": "'$(date -u +%Y-%m-%dT%H:%M:%SZ)'",
    "dueDate": "'$(date -u -d '+30 days' +%Y-%m-%dT%H:%M:%SZ 2>/dev/null || date -u -v+30d +%Y-%m-%dT%H:%M:%SZ)'",
    "items": [
      {
        "description": "Service",
        "quantity": 1,
        "unitPrice": 1000,
        "totalPrice": 1000
      }
    ],
    "notes": "Test invoice"
  }')

echo "$INVOICE_RESPONSE" | jq '.'
INVOICE_ID=$(echo "$INVOICE_RESPONSE" | jq -r '.data.id')

echo -e "\n${GREEN}4. Get Invoice${NC}"
curl -s -X GET "$BASE_URL/api/v1/invoices/$INVOICE_ID" \
  -H "Authorization: Bearer $TOKEN" \
  -H "company-id: $COMPANY_ID" | jq '.'

echo -e "\n${GREEN}5. List Invoices${NC}"
curl -s -X GET "$BASE_URL/api/v1/invoices?limit=10&offset=0" \
  -H "Authorization: Bearer $TOKEN" \
  -H "company-id: $COMPANY_ID" | jq '.'

echo -e "\n${GREEN}6. Update Invoice${NC}"
curl -s -X PATCH "$BASE_URL/api/v1/invoices/$INVOICE_ID" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer $TOKEN" \
  -H "company-id: $COMPANY_ID" \
  -d '{
    "status": "sent",
    "customerEmail": "newemail@example.com"
  }' | jq '.'

echo -e "\n${GREEN}7. Get Invoice Statistics${NC}"
curl -s -X GET "$BASE_URL/api/v1/invoices/stats/summary" \
  -H "Authorization: Bearer $TOKEN" \
  -H "company-id: $COMPANY_ID" | jq '.'

echo -e "\n${GREEN}8. Get Prometheus Metrics${NC}"
curl -s -X GET "$BASE_URL/metrics" | head -20

echo -e "\n${GREEN}9. Test Company Isolation (Should Fail with 403)${NC}"
OTHER_COMPANY="other-company-$(date +%s)"
curl -s -X GET "$BASE_URL/api/v1/invoices" \
  -H "Authorization: Bearer $TOKEN" \
  -H "company-id: $OTHER_COMPANY" | jq '.'

echo -e "\n${GREEN}=== Tests Complete ===${NC}"
