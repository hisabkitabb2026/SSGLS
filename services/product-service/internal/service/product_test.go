package service

import (
	"context"
	"testing"

	"github.com/invoiceshelf/product-service/internal/events"
	"github.com/invoiceshelf/product-service/internal/models"
	"github.com/invoiceshelf/product-service/internal/repository"
	"github.com/stretchr/testify/assert"
	"github.com/stretchr/testify/mock"
	"go.uber.org/zap"
	"gorm.io/driver/sqlite"
	"gorm.io/gorm"
)

type MockPublisher struct {
	mock.Mock
}

func (m *MockPublisher) Publish(ctx context.Context, eventType string, companyID int64, data map[string]interface{}) error {
	args := m.Called(ctx, eventType, companyID, data)
	return args.Error(0)
}

func (m *MockPublisher) Close() error {
	return nil
}

func setupTestService(t *testing.T) (*ProductService, *repository.ProductRepository) {
	db, _ := gorm.Open(sqlite.Open(":memory:"), &gorm.Config{})
	db.AutoMigrate(&models.Product{})

	repo := repository.NewProductRepository(db)

	mockPublisher := &MockPublisher{}
	mockPublisher.On("Publish", mock.Anything, mock.Anything, mock.Anything, mock.Anything).Return(nil)

	logger, _ := zap.NewProduction()
	service := NewProductService(repo, mockPublisher, logger)

	return service, repo
}

func TestProductService_CreateProduct(t *testing.T) {
	service, _ := setupTestService(t)

	req := &models.ProductRequest{
		Name:         "Test Product",
		Description:  "Test Description",
		SKU:          "TEST-001",
		UnitPrice:    99.99,
		TaxType:      "percentage",
		TaxValue:     10,
		Quantity:     50,
		ReorderLevel: 10,
		Status:       "active",
	}

	response, err := service.CreateProduct(context.Background(), 1, req)
	assert.NoError(t, err)
	assert.NotNil(t, response)
	assert.Equal(t, req.Name, response.Name)
	assert.Equal(t, req.SKU, response.SKU)
	assert.NotZero(t, response.ID)
}

func TestProductService_CreateProduct_DuplicateSKU(t *testing.T) {
	service, repo := setupTestService(t)

	req := &models.ProductRequest{
		Name:      "Test Product",
		SKU:       "TEST-001",
		UnitPrice: 99.99,
		Status:    "active",
	}

	// Create first product
	service.CreateProduct(context.Background(), 1, req)

	// Try to create with same SKU
	_, err := service.CreateProduct(context.Background(), 1, req)
	assert.Error(t, err)
	assert.Contains(t, err.Error(), "SKU already exists")
}

func TestProductService_GetProduct(t *testing.T) {
	service, repo := setupTestService(t)

	// Create a product
	product := &models.Product{
		CompanyID: 1,
		Name:      "Test Product",
		SKU:       "TEST-001",
		UnitPrice: 99.99,
		Status:    "active",
	}
	repo.Create(product)

	// Get the product
	response, err := service.GetProduct(context.Background(), 1, product.ID)
	assert.NoError(t, err)
	assert.NotNil(t, response)
	assert.Equal(t, product.Name, response.Name)
	assert.Equal(t, product.SKU, response.SKU)
}

func TestProductService_GetProduct_NotFound(t *testing.T) {
	service, _ := setupTestService(t)

	_, err := service.GetProduct(context.Background(), 1, 9999)
	assert.Error(t, err)
}

func TestProductService_ListProducts(t *testing.T) {
	service, repo := setupTestService(t)

	// Create multiple products
	for i := 1; i <= 5; i++ {
		product := &models.Product{
			CompanyID: 1,
			Name:      "Product",
			SKU:       "SKU-00" + string(rune(i+'0'-1)),
			UnitPrice: float64(i * 10),
			Status:    "active",
		}
		repo.Create(product)
	}

	// List products
	products, total, err := service.ListProducts(context.Background(), 1, 10, 0, "all")
	assert.NoError(t, err)
	assert.Equal(t, int64(5), total)
	assert.Equal(t, 5, len(products))
}

func TestProductService_UpdateProduct(t *testing.T) {
	service, repo := setupTestService(t)

	// Create a product
	product := &models.Product{
		CompanyID: 1,
		Name:      "Original Name",
		SKU:       "TEST-001",
		UnitPrice: 99.99,
		Status:    "active",
	}
	repo.Create(product)

	// Update the product
	updateReq := &models.ProductRequest{
		Name:      "Updated Name",
		SKU:       "TEST-001",
		UnitPrice: 149.99,
		Status:    "active",
	}

	response, err := service.UpdateProduct(context.Background(), 1, product.ID, updateReq)
	assert.NoError(t, err)
	assert.Equal(t, "Updated Name", response.Name)
	assert.Equal(t, 149.99, response.UnitPrice)
}

func TestProductService_UpdateProduct_ChangeSKU(t *testing.T) {
	service, repo := setupTestService(t)

	// Create two products
	product1 := &models.Product{
		CompanyID: 1,
		Name:      "Product 1",
		SKU:       "SKU-001",
		UnitPrice: 99.99,
		Status:    "active",
	}
	repo.Create(product1)

	product2 := &models.Product{
		CompanyID: 1,
		Name:      "Product 2",
		SKU:       "SKU-002",
		UnitPrice: 149.99,
		Status:    "active",
	}
	repo.Create(product2)

	// Try to update product2 with product1's SKU
	updateReq := &models.ProductRequest{
		Name:      "Product 2 Updated",
		SKU:       "SKU-001",
		UnitPrice: 149.99,
		Status:    "active",
	}

	_, err := service.UpdateProduct(context.Background(), 1, product2.ID, updateReq)
	assert.Error(t, err)
	assert.Contains(t, err.Error(), "SKU already exists")
}

func TestProductService_DeleteProduct(t *testing.T) {
	service, repo := setupTestService(t)

	// Create a product
	product := &models.Product{
		CompanyID: 1,
		Name:      "Test Product",
		SKU:       "TEST-001",
		UnitPrice: 99.99,
		Status:    "active",
	}
	repo.Create(product)

	// Delete the product
	err := service.DeleteProduct(context.Background(), 1, product.ID)
	assert.NoError(t, err)

	// Verify deletion
	_, err = service.GetProduct(context.Background(), 1, product.ID)
	assert.Error(t, err)
}

func TestProductService_GetLowStockProducts(t *testing.T) {
	service, repo := setupTestService(t)

	// Create low stock product
	lowStock := &models.Product{
		CompanyID:    1,
		Name:         "Low Stock",
		SKU:          "LOW-001",
		UnitPrice:    50.00,
		Quantity:     2,
		ReorderLevel: 5,
		Status:       "active",
	}
	repo.Create(lowStock)

	// Create normal stock product
	normalStock := &models.Product{
		CompanyID:    1,
		Name:         "Normal Stock",
		SKU:          "NORMAL-001",
		UnitPrice:    75.00,
		Quantity:     100,
		ReorderLevel: 5,
		Status:       "active",
	}
	repo.Create(normalStock)

	// Get low stock products
	products, err := service.GetLowStockProducts(context.Background(), 1)
	assert.NoError(t, err)
	assert.Equal(t, 1, len(products))
	assert.Equal(t, "Low Stock", products[0].Name)
}
