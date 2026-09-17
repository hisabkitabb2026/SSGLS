package models

import (
	"database/sql/driver"
	"encoding/json"
	"time"

	"github.com/google/uuid"
	"gorm.io/datatypes"
	"gorm.io/gorm"
)

// Setting represents a company setting with key-value pairs
type Setting struct {
	ID        string         `gorm:"primaryKey;type:varchar(36)" json:"id"`
	CompanyID uint           `gorm:"index;not null" json:"company_id"`
	Key       string         `gorm:"index;not null;type:varchar(255)" json:"key"`
	Value     datatypes.JSON `gorm:"type:jsonb" json:"value"`
	Module    string         `gorm:"index;type:varchar(100)" json:"module"`
	Type      string         `gorm:"type:varchar(50)" json:"type"`
	IsActive  bool           `gorm:"default:true" json:"is_active"`
	CreatedBy uint           `gorm:"type:integer" json:"created_by"`
	UpdatedBy uint           `gorm:"type:integer" json:"updated_by"`
	CreatedAt time.Time      `json:"created_at"`
	UpdatedAt time.Time      `json:"updated_at"`
	DeletedAt gorm.DeletedAt `gorm:"index" json:"-"`

	// Index for faster lookups
	//gorm:"index:idx_company_key,unique"
}

// TableName specifies the table name for Setting model
func (Setting) TableName() string {
	return "settings"
}

// BeforeCreate generates a UUID for new settings
func (s *Setting) BeforeCreate(tx *gorm.DB) error {
	if s.ID == "" {
		s.ID = uuid.New().String()
	}
	return nil
}

// AuditLog tracks changes to settings for audit trail
type AuditLog struct {
	ID        string         `gorm:"primaryKey;type:varchar(36)" json:"id"`
	CompanyID uint           `gorm:"index;not null" json:"company_id"`
	SettingID string         `gorm:"index;type:varchar(36)" json:"setting_id"`
	Action    string         `gorm:"type:varchar(50);not null" json:"action"` // create, update, delete
	OldValue  datatypes.JSON `gorm:"type:jsonb" json:"old_value"`
	NewValue  datatypes.JSON `gorm:"type:jsonb" json:"new_value"`
	ChangedBy uint           `gorm:"type:integer" json:"changed_by"`
	IPAddress string         `gorm:"type:varchar(50)" json:"ip_address"`
	UserAgent string         `gorm:"type:text" json:"user_agent"`
	CreatedAt time.Time      `json:"created_at"`
	DeletedAt gorm.DeletedAt `gorm:"index" json:"-"`
}

// TableName specifies the table name for AuditLog model
func (AuditLog) TableName() string {
	return "audit_logs"
}

// BeforeCreate generates a UUID for new audit logs
func (a *AuditLog) BeforeCreate(tx *gorm.DB) error {
	if a.ID == "" {
		a.ID = uuid.New().String()
	}
	return nil
}

// DTOs for API requests/responses

// SettingCreateRequest represents the request to create a new setting
type SettingCreateRequest struct {
	Key       string      `json:"key" binding:"required,min=1,max=255"`
	Value     interface{} `json:"value" binding:"required"`
	Module    string      `json:"module" binding:"max=100"`
	Type      string      `json:"type" binding:"max=50"`
	IsActive  bool        `json:"is_active" binding:""`
	CreatedBy uint        `json:"created_by" binding:"required"`
}

// SettingUpdateRequest represents the request to update a setting
type SettingUpdateRequest struct {
	Key      string      `json:"key" binding:"min=1,max=255"`
	Value    interface{} `json:"value"`
	Module   string      `json:"module" binding:"max=100"`
	Type     string      `json:"type" binding:"max=50"`
	IsActive bool        `json:"is_active"`
	UpdatedBy uint       `json:"updated_by" binding:"required"`
}

// SettingResponse represents the response for a setting
type SettingResponse struct {
	ID        string      `json:"id"`
	CompanyID uint        `json:"company_id"`
	Key       string      `json:"key"`
	Value     interface{} `json:"value"`
	Module    string      `json:"module"`
	Type      string      `json:"type"`
	IsActive  bool        `json:"is_active"`
	CreatedBy uint        `json:"created_by"`
	UpdatedBy uint        `json:"updated_by"`
	CreatedAt time.Time   `json:"created_at"`
	UpdatedAt time.Time   `json:"updated_at"`
}

// BatchSettingRequest represents batch operations
type BatchSettingRequest struct {
	Settings []SettingCreateRequest `json:"settings" binding:"required,min=1,dive"`
}

// BatchSettingUpdateRequest represents batch update operations
type BatchSettingUpdateRequest struct {
	Settings map[string]interface{} `json:"settings" binding:"required"`
	UpdatedBy uint                  `json:"updated_by" binding:"required"`
}

// AuditLogResponse represents the response for an audit log
type AuditLogResponse struct {
	ID        string      `json:"id"`
	CompanyID uint        `json:"company_id"`
	SettingID string      `json:"setting_id"`
	Action    string      `json:"action"`
	OldValue  interface{} `json:"old_value"`
	NewValue  interface{} `json:"new_value"`
	ChangedBy uint        `json:"changed_by"`
	IPAddress string      `json:"ip_address"`
	UserAgent string      `json:"user_agent"`
	CreatedAt time.Time   `json:"created_at"`
}

// HealthResponse represents the health check response
type HealthResponse struct {
	Status    string `json:"status"`
	Timestamp string `json:"timestamp"`
	Service   string `json:"service"`
	Version   string `json:"version"`
}

// ReadinessResponse represents the readiness check response
type ReadinessResponse struct {
	Ready    bool                   `json:"ready"`
	Database bool                   `json:"database"`
	Checks   map[string]bool        `json:"checks"`
	Details  map[string]interface{} `json:"details"`
}

// ErrorResponse represents an error response
type ErrorResponse struct {
	Error      string `json:"error"`
	StatusCode int    `json:"status_code"`
	Timestamp  string `json:"timestamp"`
	Path       string `json:"path,omitempty"`
	Message    string `json:"message,omitempty"`
}

// PaginatedResponse represents a paginated response
type PaginatedResponse struct {
	Data       interface{} `json:"data"`
	Total      int64       `json:"total"`
	Page       int         `json:"page"`
	PageSize   int         `json:"page_size"`
	TotalPages int         `json:"total_pages"`
}

// Helper methods

// MarshalJSON implements custom JSON marshaling for Setting
func (s Setting) MarshalJSON() ([]byte, error) {
	type Alias Setting
	var value interface{}
	if err := json.Unmarshal(s.Value, &value); err != nil {
		value = string(s.Value)
	}
	return json.Marshal(&struct {
		Value interface{} `json:"value"`
		*Alias
	}{
		Value: value,
		Alias: (*Alias)(&s),
	})
}

// Value implements the driver.Valuer interface for custom JSONB handling
func (a AuditLog) Value() (driver.Value, error) {
	return json.Marshal(a)
}

// Scan implements the sql.Scanner interface for custom JSONB handling
func (a *AuditLog) Scan(value interface{}) error {
	bytes, ok := value.([]byte)
	if !ok {
		return nil
	}
	return json.Unmarshal(bytes, &a)
}
