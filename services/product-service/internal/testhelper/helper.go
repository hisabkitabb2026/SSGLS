package testhelper

import (
	"testing"

	"github.com/invoiceshelf/product-service/internal/models"
	"github.com/invoiceshelf/product-service/internal/repository"
	"go.uber.org/zap"
	"gorm.io/driver/sqlite"
	"gorm.io/gorm"
)

// SetupTestDatabase creates an in-memory SQLite database for testing
func SetupTestDatabase(t *testing.T) *gorm.DB {
	db, err := gorm.Open(sqlite.Open(":memory:"), &gorm.Config{})
	if err != nil {
		t.Fatalf("Failed to create test database: %v", err)
	}

	if err := db.AutoMigrate(&models.Product{}); err != nil {
		t.Fatalf("Failed to migrate test database: %v", err)
	}

	return db
}

// SetupTestRepository creates a test product repository
func SetupTestRepository(t *testing.T) *repository.ProductRepository {
	db := SetupTestDatabase(t)
	return repository.NewProductRepository(db)
}

// SetupTestLogger creates a test logger
func SetupTestLogger(t *testing.T) *zap.Logger {
	logger, err := zap.NewProduction()
	if err != nil {
		t.Fatalf("Failed to create test logger: %v", err)
	}
	return logger
}

// CreateTestProduct creates a test product in the database
func CreateTestProduct(t *testing.T, repo *repository.ProductRepository, companyID int64, name string, sku string) *models.Product {
	product := &models.Product{
		CompanyID:    companyID,
		Name:         name,
		SKU:          sku,
		UnitPrice:    99.99,
		Quantity:     50,
		ReorderLevel: 10,
		Status:       "active",
	}

	if err := repo.Create(product); err != nil {
		t.Fatalf("Failed to create test product: %v", err)
	}

	return product
}

// CreateTestProducts creates multiple test products
func CreateTestProducts(t *testing.T, repo *repository.ProductRepository, companyID int64, count int) []*models.Product {
	products := make([]*models.Product, count)

	for i := 0; i < count; i++ {
		name := "Test Product " + string(rune(i+1+'0'-1))
		sku := "TEST-SKU-" + string(rune(i+1+'0'-1))
		products[i] = CreateTestProduct(t, repo, companyID, name, sku)
	}

	return products
}
