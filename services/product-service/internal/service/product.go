package service

import (
	"context"
	"encoding/json"
	"fmt"

	"github.com/invoiceshelf/product-service/internal/events"
	"github.com/invoiceshelf/product-service/internal/models"
	"github.com/invoiceshelf/product-service/internal/repository"
	"go.uber.org/zap"
)

type ProductService struct {
	repo      *repository.ProductRepository
	publisher *events.Publisher
	logger    *zap.Logger
}

func NewProductService(repo *repository.ProductRepository, publisher *events.Publisher, logger *zap.Logger) *ProductService {
	return &ProductService{
		repo:      repo,
		publisher: publisher,
		logger:    logger,
	}
}

func (s *ProductService) CreateProduct(ctx context.Context, companyID int64, req *models.ProductRequest) (*models.ProductResponse, error) {
	req.SetDefaults()

	// Check if SKU already exists
	exists, err := s.repo.SKUExists(req.SKU, companyID, nil)
	if err != nil {
		s.logger.Error("failed to check SKU existence", zap.Error(err))
		return nil, err
	}
	if exists {
		return nil, fmt.Errorf("SKU already exists")
	}

	product := req.ToModel(companyID)
	if err := s.repo.Create(product); err != nil {
		s.logger.Error("failed to create product", zap.Error(err))
		return nil, err
	}

	// Publish event
	eventData := map[string]interface{}{
		"product_id": product.ID,
		"name":       product.Name,
		"sku":        product.SKU,
	}
	_ = s.publisher.Publish(ctx, events.EventProductCreated, companyID, eventData)

	s.logger.Info("product created", zap.Int64("product_id", product.ID), zap.Int64("company_id", companyID))
	return product.ToResponse(), nil
}

func (s *ProductService) GetProduct(ctx context.Context, companyID, productID int64) (*models.ProductResponse, error) {
	product, err := s.repo.GetByID(productID, companyID)
	if err != nil {
		s.logger.Error("failed to get product", zap.Error(err), zap.Int64("product_id", productID))
		return nil, err
	}

	return product.ToResponse(), nil
}

func (s *ProductService) ListProducts(ctx context.Context, companyID int64, limit, offset int, status string) ([]models.ProductResponse, int64, error) {
	products, total, err := s.repo.List(companyID, limit, offset, status)
	if err != nil {
		s.logger.Error("failed to list products", zap.Error(err))
		return nil, 0, err
	}

	responses := make([]models.ProductResponse, len(products))
	for i, p := range products {
		responses[i] = *p.ToResponse()
	}

	return responses, total, nil
}

func (s *ProductService) UpdateProduct(ctx context.Context, companyID, productID int64, req *models.ProductRequest) (*models.ProductResponse, error) {
	product, err := s.repo.GetByID(productID, companyID)
	if err != nil {
		s.logger.Error("failed to get product", zap.Error(err))
		return nil, err
	}

	// Check if SKU is being changed and already exists
	if req.SKU != product.SKU {
		exists, err := s.repo.SKUExists(req.SKU, companyID, &productID)
		if err != nil {
			return nil, err
		}
		if exists {
			return nil, fmt.Errorf("SKU already exists")
		}
		product.SKU = req.SKU
	}

	product.Name = req.Name
	product.Description = req.Description
	product.UnitPrice = req.UnitPrice
	product.TaxType = req.TaxType
	product.TaxValue = req.TaxValue
	product.Quantity = req.Quantity
	product.ReorderLevel = req.ReorderLevel
	if req.Status != "" {
		product.Status = req.Status
	}

	if req.Metadata != nil {
		metadataJSON, _ := json.Marshal(req.Metadata)
		product.Metadata = metadataJSON
	}

	if err := s.repo.Update(product); err != nil {
		s.logger.Error("failed to update product", zap.Error(err))
		return nil, err
	}

	// Publish event
	eventData := map[string]interface{}{
		"product_id": product.ID,
		"name":       product.Name,
		"sku":        product.SKU,
	}
	_ = s.publisher.Publish(ctx, events.EventProductUpdated, companyID, eventData)

	s.logger.Info("product updated", zap.Int64("product_id", productID))
	return product.ToResponse(), nil
}

func (s *ProductService) DeleteProduct(ctx context.Context, companyID, productID int64) error {
	if err := s.repo.Delete(productID, companyID); err != nil {
		s.logger.Error("failed to delete product", zap.Error(err))
		return err
	}

	// Publish event
	eventData := map[string]interface{}{
		"product_id": productID,
	}
	_ = s.publisher.Publish(ctx, events.EventProductDeleted, companyID, eventData)

	s.logger.Info("product deleted", zap.Int64("product_id", productID))
	return nil
}

func (s *ProductService) GetLowStockProducts(ctx context.Context, companyID int64) ([]models.ProductResponse, error) {
	products, err := s.repo.GetLowStockProducts(companyID)
	if err != nil {
		s.logger.Error("failed to get low stock products", zap.Error(err))
		return nil, err
	}

	responses := make([]models.ProductResponse, len(products))
	for i, p := range products {
		responses[i] = *p.ToResponse()
	}

	return responses, nil
}
