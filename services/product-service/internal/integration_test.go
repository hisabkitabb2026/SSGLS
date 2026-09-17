// +build integration

package internal

import (
	"context"
	"fmt"
	"net/http"
	"net/http/httptest"
	"testing"
	"time"

	"github.com/invoiceshelf/product-service/config"
	"github.com/invoiceshelf/product-service/internal/db"
	"github.com/invoiceshelf/product-service/internal/middleware"
	"github.com/invoiceshelf/product-service/internal/models"
	"github.com/invoiceshelf/product-service/internal/repository"
	"github.com/invoiceshelf/product-service/internal/service"
	"github.com/golang-jwt/jwt/v5"
	"github.com/stretchr/testify/assert"
	"go.uber.org/zap"
	"gorm.io/driver/sqlite"
	"gorm.io/gorm"
)

func TestIntegration_ProductAPI(t *testing.T) {
	// Setup
	cfg := &config.Config{
		Port:       8003,
		AppEnv:     "test",
		LogLevel:   "error",
		DBHost:     "localhost",
		DBPort:     5432,
		DBUser:     "product_service",
		DBPassword: "product_password",
		DBName:     "product_service_test",
		DBSSLMode: "disable",
		JWTSecret:  "test-secret",
	}

	// This would need a running PostgreSQL instance
	// For the test suite, we'll use SQLite instead
	// database, err := db.New(cfg)
	// if err != nil {
	// 	t.Skipf("PostgreSQL not available: %v", err)
	// }

	// For now, we'll skip this integration test
	t.Skip("Integration test requires running PostgreSQL")
}

func TestIntegrationMock_ProductCRUD(t *testing.T) {
	// Setup in-memory database
	database, _ := gorm.Open(sqlite.Open(":memory:"), &gorm.Config{})

	db.RunMigrations(database)

	repo := repository.NewProductRepository(database)
	logger, _ := zap.NewProduction()

	// Mock publisher
	mockPublisher := &MockEventPublisher{}
	productService := service.NewProductService(repo, mockPublisher, logger)

	// Test Create
	createReq := &models.ProductRequest{
		Name:         "Integration Test Product",
		Description:  "This is a test product",
		SKU:          "INT-TEST-001",
		UnitPrice:    99.99,
		TaxType:      "percentage",
		TaxValue:     10,
		Quantity:     100,
		ReorderLevel: 20,
		Status:       "active",
	}

	ctx := context.Background()
	product, err := productService.CreateProduct(ctx, 1, createReq)
	assert.NoError(t, err)
	assert.NotNil(t, product)
	assert.Equal(t, createReq.Name, product.Name)
	assert.NotZero(t, product.ID)

	// Test Read
	retrieved, err := productService.GetProduct(ctx, 1, product.ID)
	assert.NoError(t, err)
	assert.Equal(t, product.Name, retrieved.Name)

	// Test Update
	updateReq := &models.ProductRequest{
		Name:         "Updated Product Name",
		Description:  "Updated description",
		SKU:          "INT-TEST-001",
		UnitPrice:    149.99,
		TaxType:      "percentage",
		TaxValue:     15,
		Quantity:     50,
		ReorderLevel: 10,
		Status:       "active",
	}

	updated, err := productService.UpdateProduct(ctx, 1, product.ID, updateReq)
	assert.NoError(t, err)
	assert.Equal(t, updateReq.Name, updated.Name)
	assert.Equal(t, updateReq.UnitPrice, updated.UnitPrice)

	// Test List
	products, total, err := productService.ListProducts(ctx, 1, 10, 0, "all")
	assert.NoError(t, err)
	assert.Equal(t, int64(1), total)
	assert.Equal(t, 1, len(products))

	// Test Delete
	err = productService.DeleteProduct(ctx, 1, product.ID)
	assert.NoError(t, err)

	// Verify deletion
	products, total, err = productService.ListProducts(ctx, 1, 10, 0, "all")
	assert.NoError(t, err)
	assert.Equal(t, int64(0), total)
}

func TestIntegrationMock_MultiTenant(t *testing.T) {
	database, _ := gorm.Open(sqlite.Open(":memory:"), &gorm.Config{})

	db.RunMigrations(database)

	repo := repository.NewProductRepository(database)
	logger, _ := zap.NewProduction()

	mockPublisher := &MockEventPublisher{}
	productService := service.NewProductService(repo, mockPublisher, logger)

	ctx := context.Background()

	// Create product for company 1
	req1 := &models.ProductRequest{
		Name:      "Company 1 Product",
		SKU:       "C1-001",
		UnitPrice: 50.00,
		Status:    "active",
	}
	product1, _ := productService.CreateProduct(ctx, 1, req1)

	// Create product for company 2
	req2 := &models.ProductRequest{
		Name:      "Company 2 Product",
		SKU:       "C2-001",
		UnitPrice: 75.00,
		Status:    "active",
	}
	product2, _ := productService.CreateProduct(ctx, 2, req2)

	// List products for company 1 should only show company 1 products
	company1Products, total1, _ := productService.ListProducts(ctx, 1, 10, 0, "all")
	assert.Equal(t, int64(1), total1)
	assert.Equal(t, "Company 1 Product", company1Products[0].Name)

	// List products for company 2 should only show company 2 products
	company2Products, total2, _ := productService.ListProducts(ctx, 2, 10, 0, "all")
	assert.Equal(t, int64(1), total2)
	assert.Equal(t, "Company 2 Product", company2Products[0].Name)

	// Company 1 shouldn't be able to access company 2's products
	_, err := productService.GetProduct(ctx, 1, product2.ID)
	assert.Error(t, err)

	// Company 2 shouldn't be able to access company 1's products
	_, err = productService.GetProduct(ctx, 2, product1.ID)
	assert.Error(t, err)
}

func TestIntegrationMock_JWTMiddleware(t *testing.T) {
	jwtSecret := "test-secret"
	logger, _ := zap.NewProduction()

	// Create valid JWT token
	token := jwt.NewWithClaims(jwt.SigningMethodHS256, jwt.MapClaims{
		"user_id":    int64(123),
		"company_id": int64(1),
		"email":      "test@example.com",
		"exp":        time.Now().Add(time.Hour).Unix(),
	})

	tokenString, _ := token.SignedString([]byte(jwtSecret))

	// Create request with valid token
	req := httptest.NewRequest("GET", "/api/v1/products", nil)
	req.Header.Set("Authorization", fmt.Sprintf("Bearer %s", tokenString))

	// Apply JWT middleware
	middlewareFunc := middleware.JWTMiddleware(jwtSecret, logger)
	handler := middlewareFunc(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		userID := middleware.GetUserID(r)
		companyID := middleware.GetCompanyID(r)

		assert.Equal(t, int64(123), userID)
		assert.Equal(t, int64(1), companyID)

		w.WriteHeader(http.StatusOK)
	}))

	w := httptest.NewRecorder()
	handler.ServeHTTP(w, req)

	assert.Equal(t, http.StatusOK, w.Code)
}

func TestIntegrationMock_LowStockAlert(t *testing.T) {
	database, _ := gorm.Open(sqlite.Open(":memory:"), &gorm.Config{})

	db.RunMigrations(database)

	repo := repository.NewProductRepository(database)
	logger, _ := zap.NewProduction()
	mockPublisher := &MockEventPublisher{}
	productService := service.NewProductService(repo, mockPublisher, logger)

	ctx := context.Background()

	// Create low stock product
	req := &models.ProductRequest{
		Name:         "Low Stock Product",
		SKU:          "LOW-STOCK-001",
		UnitPrice:    100.00,
		Quantity:     3,
		ReorderLevel: 10,
		Status:       "active",
	}
	productService.CreateProduct(ctx, 1, req)

	// Get low stock products
	lowStockProducts, err := productService.GetLowStockProducts(ctx, 1)
	assert.NoError(t, err)
	assert.Equal(t, 1, len(lowStockProducts))
	assert.Equal(t, "Low Stock Product", lowStockProducts[0].Name)

	// Verify event was published
	assert.True(t, mockPublisher.PublishCalled)
}

// Mock Publisher for testing
type MockEventPublisher struct {
	PublishCalled bool
}

func (m *MockEventPublisher) Publish(ctx context.Context, eventType string, companyID int64, data map[string]interface{}) error {
	m.PublishCalled = true
	return nil
}

func (m *MockEventPublisher) Close() error {
	return nil
}
