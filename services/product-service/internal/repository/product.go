package repository

import (
	"fmt"

	"github.com/invoiceshelf/product-service/internal/models"
	"gorm.io/gorm"
)

type ProductRepository struct {
	db *gorm.DB
}

func NewProductRepository(db *gorm.DB) *ProductRepository {
	return &ProductRepository{db: db}
}

func (r *ProductRepository) Create(product *models.Product) error {
	if err := r.db.Create(product).Error; err != nil {
		return fmt.Errorf("failed to create product: %w", err)
	}
	return nil
}

func (r *ProductRepository) GetByID(id, companyID int64) (*models.Product, error) {
	var product models.Product
	if err := r.db.Where("id = ? AND company_id = ?", id, companyID).First(&product).Error; err != nil {
		if err == gorm.ErrRecordNotFound {
			return nil, fmt.Errorf("product not found")
		}
		return nil, fmt.Errorf("failed to fetch product: %w", err)
	}
	return &product, nil
}

func (r *ProductRepository) GetBySKU(sku string, companyID int64) (*models.Product, error) {
	var product models.Product
	if err := r.db.Where("sku = ? AND company_id = ?", sku, companyID).First(&product).Error; err != nil {
		if err == gorm.ErrRecordNotFound {
			return nil, fmt.Errorf("product not found")
		}
		return nil, fmt.Errorf("failed to fetch product: %w", err)
	}
	return &product, nil
}

func (r *ProductRepository) List(companyID int64, limit, offset int, status string) ([]*models.Product, int64, error) {
	var products []*models.Product
	var total int64

	query := r.db.Where("company_id = ?", companyID)
	if status != "" && status != "all" {
		query = query.Where("status = ?", status)
	}

	// Get total count
	if err := query.Model(&models.Product{}).Count(&total).Error; err != nil {
		return nil, 0, fmt.Errorf("failed to count products: %w", err)
	}

	// Get paginated results
	if err := query.Limit(limit).Offset(offset).Find(&products).Error; err != nil {
		return nil, 0, fmt.Errorf("failed to list products: %w", err)
	}

	return products, total, nil
}

func (r *ProductRepository) Update(product *models.Product) error {
	if err := r.db.Model(product).Updates(product).Error; err != nil {
		return fmt.Errorf("failed to update product: %w", err)
	}
	return nil
}

func (r *ProductRepository) Delete(id, companyID int64) error {
	if err := r.db.Where("id = ? AND company_id = ?", id, companyID).Delete(&models.Product{}).Error; err != nil {
		return fmt.Errorf("failed to delete product: %w", err)
	}
	return nil
}

func (r *ProductRepository) SKUExists(sku string, companyID int64, excludeID *int64) (bool, error) {
	var count int64
	query := r.db.Where("sku = ? AND company_id = ?", sku, companyID)
	if excludeID != nil {
		query = query.Where("id != ?", *excludeID)
	}

	if err := query.Model(&models.Product{}).Count(&count).Error; err != nil {
		return false, fmt.Errorf("failed to check sku existence: %w", err)
	}

	return count > 0, nil
}

func (r *ProductRepository) GetLowStockProducts(companyID int64) ([]*models.Product, error) {
	var products []*models.Product
	if err := r.db.Where(
		"company_id = ? AND status = 'active' AND quantity <= reorder_level",
		companyID,
	).Find(&products).Error; err != nil {
		return nil, fmt.Errorf("failed to fetch low stock products: %w", err)
	}
	return products, nil
}
