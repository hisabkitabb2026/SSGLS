package models

import (
	"database/sql/driver"
	"encoding/json"
	"time"

	"gorm.io/datatypes"
	"gorm.io/gorm"
)

type Product struct {
	ID          int64          `gorm:"primaryKey" json:"id"`
	CompanyID   int64          `gorm:"index;not null" json:"company_id"`
	Name        string         `gorm:"not null" json:"name"`
	Description string         `json:"description"`
	SKU         string         `gorm:"uniqueIndex:idx_sku_company;not null" json:"sku"`
	UnitPrice   float64        `gorm:"not null;type:decimal(15,2)" json:"unit_price"`
	TaxType     string         `json:"tax_type"` // percentage, fixed, etc.
	TaxValue    float64        `gorm:"type:decimal(15,2)" json:"tax_value"`
	Quantity    int            `gorm:"not null;default:0" json:"quantity"`
	ReorderLevel int           `gorm:"default:0" json:"reorder_level"`
	Status      string         `gorm:"index;default:'active'" json:"status"` // active, inactive, discontinued
	Metadata    datatypes.JSON `json:"metadata"`
	CreatedAt   time.Time      `json:"created_at"`
	UpdatedAt   time.Time      `json:"updated_at"`
	DeletedAt   gorm.DeletedAt `gorm:"index" json:"-"`
}

type ProductRequest struct {
	Name         string                 `json:"name" validate:"required,min=1,max=255"`
	Description  string                 `json:"description" validate:"max=1000"`
	SKU          string                 `json:"sku" validate:"required,min=1,max=100"`
	UnitPrice    float64                `json:"unit_price" validate:"required,min=0"`
	TaxType      string                 `json:"tax_type" validate:"omitempty,oneof=percentage fixed"`
	TaxValue     float64                `json:"tax_value" validate:"min=0"`
	Quantity     int                    `json:"quantity" validate:"min=0"`
	ReorderLevel int                    `json:"reorder_level" validate:"min=0"`
	Status       string                 `json:"status" validate:"omitempty,oneof=active inactive discontinued"`
	Metadata     map[string]interface{} `json:"metadata"`
}

type ProductResponse struct {
	ID          int64                  `json:"id"`
	CompanyID   int64                  `json:"company_id"`
	Name        string                 `json:"name"`
	Description string                 `json:"description"`
	SKU         string                 `json:"sku"`
	UnitPrice   float64                `json:"unit_price"`
	TaxType     string                 `json:"tax_type"`
	TaxValue    float64                `json:"tax_value"`
	Quantity    int                    `json:"quantity"`
	ReorderLevel int                   `json:"reorder_level"`
	Status      string                 `json:"status"`
	Metadata    map[string]interface{} `json:"metadata"`
	CreatedAt   time.Time              `json:"created_at"`
	UpdatedAt   time.Time              `json:"updated_at"`
}

func (p *Product) ToResponse() *ProductResponse {
	var metadata map[string]interface{}
	if len(p.Metadata) > 0 {
		_ = json.Unmarshal(p.Metadata, &metadata)
	}

	return &ProductResponse{
		ID:          p.ID,
		CompanyID:   p.CompanyID,
		Name:        p.Name,
		Description: p.Description,
		SKU:         p.SKU,
		UnitPrice:   p.UnitPrice,
		TaxType:     p.TaxType,
		TaxValue:    p.TaxValue,
		Quantity:    p.Quantity,
		ReorderLevel: p.ReorderLevel,
		Status:      p.Status,
		Metadata:    metadata,
		CreatedAt:   p.CreatedAt,
		UpdatedAt:   p.UpdatedAt,
	}
}

func (p *ProductRequest) SetDefaults() {
	if p.Status == "" {
		p.Status = "active"
	}
	if p.TaxType == "" {
		p.TaxType = "percentage"
	}
}

func (p *ProductRequest) ToModel(companyID int64) *Product {
	metadataBytes, _ := json.Marshal(p.Metadata)

	return &Product{
		CompanyID:    companyID,
		Name:         p.Name,
		Description:  p.Description,
		SKU:          p.SKU,
		UnitPrice:    p.UnitPrice,
		TaxType:      p.TaxType,
		TaxValue:     p.TaxValue,
		Quantity:     p.Quantity,
		ReorderLevel: p.ReorderLevel,
		Status:       p.Status,
		Metadata:     metadataBytes,
	}
}

// Scan implements sql.Scanner interface
func (p *Product) Scan(value interface{}) error {
	return nil
}

// Value implements driver.Valuer interface
func (p *Product) Value() (driver.Value, error) {
	return json.Marshal(p)
}
