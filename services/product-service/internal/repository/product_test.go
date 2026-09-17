package repository

import (
	"testing"
	"time"

	"github.com/invoiceshelf/product-service/internal/models"
	"github.com/stretchr/testify/assert"
	"gorm.io/driver/sqlite"
	"gorm.io/gorm"
)

func setupTestDB(t *testing.T) *gorm.DB {
	db, err := gorm.Open(sqlite.Open(":memory:"), &gorm.Config{})
	assert.NoError(t, err)

	err = db.AutoMigrate(&models.Product{})
	assert.NoError(t, err)

	return db
}

func TestProductRepository_Create(t *testing.T) {
	db := setupTestDB(t)
	repo := NewProductRepository(db)

	product := &models.Product{
		CompanyID:    1,
		Name:         "Test Product",
		SKU:          "TEST-001",
		UnitPrice:    99.99,
		Quantity:     10,
		Status:       "active",
		ReorderLevel: 5,
	}

	err := repo.Create(product)
	assert.NoError(t, err)
	assert.NotZero(t, product.ID)

	// Verify it was created
	retrieved, err := repo.GetByID(product.ID, 1)
	assert.NoError(t, err)
	assert.Equal(t, product.Name, retrieved.Name)
	assert.Equal(t, product.SKU, retrieved.SKU)
}

func TestProductRepository_GetByID(t *testing.T) {
	db := setupTestDB(t)
	repo := NewProductRepository(db)

	product := &models.Product{
		CompanyID: 1,
		Name:      "Test Product",
		SKU:       "TEST-001",
		UnitPrice: 99.99,
		Status:    "active",
	}

	repo.Create(product)

	// Test successful retrieval
	retrieved, err := repo.GetByID(product.ID, 1)
	assert.NoError(t, err)
	assert.Equal(t, product.Name, retrieved.Name)

	// Test non-existent product
	_, err = repo.GetByID(9999, 1)
	assert.Error(t, err)

	// Test company isolation
	_, err = repo.GetByID(product.ID, 999)
	assert.Error(t, err)
}

func TestProductRepository_GetBySKU(t *testing.T) {
	db := setupTestDB(t)
	repo := NewProductRepository(db)

	product := &models.Product{
		CompanyID: 1,
		Name:      "Test Product",
		SKU:       "TEST-001",
		UnitPrice: 99.99,
		Status:    "active",
	}

	repo.Create(product)

	// Test successful retrieval
	retrieved, err := repo.GetBySKU("TEST-001", 1)
	assert.NoError(t, err)
	assert.Equal(t, product.Name, retrieved.Name)

	// Test non-existent SKU
	_, err = repo.GetBySKU("NON-EXISTENT", 1)
	assert.Error(t, err)
}

func TestProductRepository_List(t *testing.T) {
	db := setupTestDB(t)
	repo := NewProductRepository(db)

	// Create multiple products
	for i := 1; i <= 5; i++ {
		product := &models.Product{
			CompanyID: 1,
			Name:      "Product " + string(rune(i)),
			SKU:       "TEST-00" + string(rune(i+'0'-1)),
			UnitPrice: float64(i * 10),
			Status:    "active",
		}
		repo.Create(product)
	}

	// Create product for different company
	product := &models.Product{
		CompanyID: 2,
		Name:      "Other Company Product",
		SKU:       "OTHER-001",
		UnitPrice: 50.00,
		Status:    "active",
	}
	repo.Create(product)

	// Test listing
	products, total, err := repo.List(1, 10, 0, "all")
	assert.NoError(t, err)
	assert.Equal(t, int64(5), total)
	assert.Equal(t, 5, len(products))

	// Test pagination
	products, total, err = repo.List(1, 2, 0, "all")
	assert.NoError(t, err)
	assert.Equal(t, int64(5), total)
	assert.Equal(t, 2, len(products))

	// Test status filter
	products, total, err = repo.List(1, 10, 0, "active")
	assert.NoError(t, err)
	assert.Equal(t, int64(5), total)
	assert.Equal(t, 5, len(products))

	// Test company isolation
	products, total, err = repo.List(2, 10, 0, "all")
	assert.NoError(t, err)
	assert.Equal(t, int64(1), total)
	assert.Equal(t, 1, len(products))
}

func TestProductRepository_Update(t *testing.T) {
	db := setupTestDB(t)
	repo := NewProductRepository(db)

	product := &models.Product{
		CompanyID: 1,
		Name:      "Original Name",
		SKU:       "TEST-001",
		UnitPrice: 99.99,
		Status:    "active",
	}

	repo.Create(product)

	// Update product
	product.Name = "Updated Name"
	product.UnitPrice = 149.99
	product.Status = "inactive"

	err := repo.Update(product)
	assert.NoError(t, err)

	// Verify update
	retrieved, _ := repo.GetByID(product.ID, 1)
	assert.Equal(t, "Updated Name", retrieved.Name)
	assert.Equal(t, 149.99, retrieved.UnitPrice)
	assert.Equal(t, "inactive", retrieved.Status)
}

func TestProductRepository_Delete(t *testing.T) {
	db := setupTestDB(t)
	repo := NewProductRepository(db)

	product := &models.Product{
		CompanyID: 1,
		Name:      "Test Product",
		SKU:       "TEST-001",
		UnitPrice: 99.99,
		Status:    "active",
	}

	repo.Create(product)

	// Delete product
	err := repo.Delete(product.ID, 1)
	assert.NoError(t, err)

	// Verify deletion
	_, err = repo.GetByID(product.ID, 1)
	assert.Error(t, err)
}

func TestProductRepository_SKUExists(t *testing.T) {
	db := setupTestDB(t)
	repo := NewProductRepository(db)

	product := &models.Product{
		CompanyID: 1,
		Name:      "Test Product",
		SKU:       "TEST-001",
		UnitPrice: 99.99,
		Status:    "active",
	}

	repo.Create(product)

	// Test SKU exists
	exists, err := repo.SKUExists("TEST-001", 1, nil)
	assert.NoError(t, err)
	assert.True(t, exists)

	// Test SKU doesn't exist
	exists, err = repo.SKUExists("NON-EXISTENT", 1, nil)
	assert.NoError(t, err)
	assert.False(t, exists)

	// Test SKU exists but excluded ID
	exists, err = repo.SKUExists("TEST-001", 1, &product.ID)
	assert.NoError(t, err)
	assert.False(t, exists)

	// Test company isolation
	exists, err = repo.SKUExists("TEST-001", 2, nil)
	assert.NoError(t, err)
	assert.False(t, exists)
}

func TestProductRepository_GetLowStockProducts(t *testing.T) {
	db := setupTestDB(t)
	repo := NewProductRepository(db)

	// Create low stock product
	lowStock := &models.Product{
		CompanyID:    1,
		Name:         "Low Stock Product",
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
		Name:         "Normal Stock Product",
		SKU:          "NORMAL-001",
		UnitPrice:    75.00,
		Quantity:     100,
		ReorderLevel: 5,
		Status:       "active",
	}
	repo.Create(normalStock)

	// Create discontinued product
	discontinued := &models.Product{
		CompanyID:    1,
		Name:         "Discontinued Product",
		SKU:          "DISC-001",
		UnitPrice:    25.00,
		Quantity:     1,
		ReorderLevel: 5,
		Status:       "discontinued",
	}
	repo.Create(discontinued)

	// Get low stock products
	products, err := repo.GetLowStockProducts(1)
	assert.NoError(t, err)
	assert.Equal(t, 1, len(products))
	assert.Equal(t, "Low Stock Product", products[0].Name)
}
