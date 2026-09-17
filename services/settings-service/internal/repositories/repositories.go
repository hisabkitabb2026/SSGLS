package repositories

import (
	"errors"
	"fmt"

	"github.com/invoiceshelf/settings-service/internal/models"
	"gorm.io/gorm"
)

// SettingRepository handles database operations for settings
type SettingRepository struct {
	db *gorm.DB
}

// AuditLogRepository handles database operations for audit logs
type AuditLogRepository struct {
	db *gorm.DB
}

// NewSettingRepository creates a new setting repository
func NewSettingRepository(db *gorm.DB) *SettingRepository {
	return &SettingRepository{db: db}
}

// NewAuditLogRepository creates a new audit log repository
func NewAuditLogRepository(db *gorm.DB) *AuditLogRepository {
	return &AuditLogRepository{db: db}
}

// ============= Setting Repository Methods =============

// Create creates a new setting
func (r *SettingRepository) Create(setting *models.Setting) error {
	result := r.db.Create(setting)
	if result.Error != nil {
		return fmt.Errorf("failed to create setting: %w", result.Error)
	}
	return nil
}

// GetByID retrieves a setting by ID
func (r *SettingRepository) GetByID(id string, companyID uint) (*models.Setting, error) {
	var setting models.Setting
	result := r.db.Where("id = ? AND company_id = ?", id, companyID).First(&setting)
	if result.Error != nil {
		if errors.Is(result.Error, gorm.ErrRecordNotFound) {
			return nil, nil
		}
		return nil, fmt.Errorf("failed to get setting by id: %w", result.Error)
	}
	return &setting, nil
}

// GetByKey retrieves a setting by key for a company
func (r *SettingRepository) GetByKey(key string, companyID uint) (*models.Setting, error) {
	var setting models.Setting
	result := r.db.Where("key = ? AND company_id = ?", key, companyID).First(&setting)
	if result.Error != nil {
		if errors.Is(result.Error, gorm.ErrRecordNotFound) {
			return nil, nil
		}
		return nil, fmt.Errorf("failed to get setting by key: %w", result.Error)
	}
	return &setting, nil
}

// List retrieves settings for a company with pagination
func (r *SettingRepository) List(companyID uint, module string, page, pageSize int) ([]models.Setting, int64, error) {
	var settings []models.Setting
	var total int64

	query := r.db.Where("company_id = ?", companyID)
	if module != "" {
		query = query.Where("module = ?", module)
	}

	// Get total count
	if err := query.Model(&models.Setting{}).Count(&total).Error; err != nil {
		return nil, 0, fmt.Errorf("failed to count settings: %w", err)
	}

	// Get paginated results
	offset := (page - 1) * pageSize
	result := query.Offset(offset).Limit(pageSize).Find(&settings)
	if result.Error != nil {
		return nil, 0, fmt.Errorf("failed to list settings: %w", result.Error)
	}

	return settings, total, nil
}

// Update updates a setting
func (r *SettingRepository) Update(setting *models.Setting) error {
	result := r.db.Model(setting).Updates(setting)
	if result.Error != nil {
		return fmt.Errorf("failed to update setting: %w", result.Error)
	}
	return nil
}

// Delete soft deletes a setting
func (r *SettingRepository) Delete(id string, companyID uint) error {
	result := r.db.Where("id = ? AND company_id = ?", id, companyID).Delete(&models.Setting{})
	if result.Error != nil {
		return fmt.Errorf("failed to delete setting: %w", result.Error)
	}
	if result.RowsAffected == 0 {
		return fmt.Errorf("setting not found")
	}
	return nil
}

// GetByModule retrieves all settings for a module
func (r *SettingRepository) GetByModule(companyID uint, module string) ([]models.Setting, error) {
	var settings []models.Setting
	result := r.db.Where("company_id = ? AND module = ?", companyID, module).Find(&settings)
	if result.Error != nil {
		return nil, fmt.Errorf("failed to get settings by module: %w", result.Error)
	}
	return settings, nil
}

// CreateMany creates multiple settings
func (r *SettingRepository) CreateMany(settings []models.Setting) error {
	result := r.db.CreateInBatches(settings, 100)
	if result.Error != nil {
		return fmt.Errorf("failed to create settings: %w", result.Error)
	}
	return nil
}

// UpdateMany updates multiple settings
func (r *SettingRepository) UpdateMany(settings []models.Setting) error {
	for _, setting := range settings {
		if err := r.Update(&setting); err != nil {
			return err
		}
	}
	return nil
}

// ============= Audit Log Repository Methods =============

// Create creates a new audit log entry
func (r *AuditLogRepository) Create(log *models.AuditLog) error {
	result := r.db.Create(log)
	if result.Error != nil {
		return fmt.Errorf("failed to create audit log: %w", result.Error)
	}
	return nil
}

// GetBySettingID retrieves audit logs for a setting
func (r *AuditLogRepository) GetBySettingID(settingID string, companyID uint) ([]models.AuditLog, error) {
	var logs []models.AuditLog
	result := r.db.Where("setting_id = ? AND company_id = ?", settingID, companyID).
		Order("created_at DESC").
		Find(&logs)
	if result.Error != nil {
		return nil, fmt.Errorf("failed to get audit logs: %w", result.Error)
	}
	return logs, nil
}

// GetByCompany retrieves all audit logs for a company with pagination
func (r *AuditLogRepository) GetByCompany(companyID uint, page, pageSize int) ([]models.AuditLog, int64, error) {
	var logs []models.AuditLog
	var total int64

	query := r.db.Where("company_id = ?", companyID)

	// Get total count
	if err := query.Model(&models.AuditLog{}).Count(&total).Error; err != nil {
		return nil, 0, fmt.Errorf("failed to count audit logs: %w", err)
	}

	// Get paginated results
	offset := (page - 1) * pageSize
	result := query.Offset(offset).Limit(pageSize).Order("created_at DESC").Find(&logs)
	if result.Error != nil {
		return nil, 0, fmt.Errorf("failed to get audit logs: %w", result.Error)
	}

	return logs, total, nil
}

// PurgeOldLogs deletes audit logs older than specified days
func (r *AuditLogRepository) PurgeOldLogs(companyID uint, days int) error {
	result := r.db.Where("company_id = ? AND created_at < NOW() - INTERVAL '? days'", companyID, days).
		Delete(&models.AuditLog{})
	if result.Error != nil {
		return fmt.Errorf("failed to purge old audit logs: %w", result.Error)
	}
	return nil
}

// GetByAction retrieves audit logs by action type
func (r *AuditLogRepository) GetByAction(companyID uint, action string) ([]models.AuditLog, error) {
	var logs []models.AuditLog
	result := r.db.Where("company_id = ? AND action = ?", companyID, action).
		Order("created_at DESC").
		Find(&logs)
	if result.Error != nil {
		return nil, fmt.Errorf("failed to get audit logs by action: %w", result.Error)
	}
	return logs, nil
}
