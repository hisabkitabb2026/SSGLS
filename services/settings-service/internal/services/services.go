package services

import (
	"encoding/json"
	"fmt"
	"time"

	"github.com/invoiceshelf/settings-service/internal/events"
	"github.com/invoiceshelf/settings-service/internal/models"
	"github.com/invoiceshelf/settings-service/internal/repositories"
	"github.com/sirupsen/logrus"
)

// SettingService handles business logic for settings
type SettingService struct {
	settingRepo   *repositories.SettingRepository
	auditLogRepo  *repositories.AuditLogRepository
	eventPublisher *events.Publisher
	logger        *logrus.Logger
}

// NewSettingService creates a new setting service
func NewSettingService(
	settingRepo *repositories.SettingRepository,
	auditLogRepo *repositories.AuditLogRepository,
	eventPublisher *events.Publisher,
	logger *logrus.Logger,
) *SettingService {
	return &SettingService{
		settingRepo:    settingRepo,
		auditLogRepo:   auditLogRepo,
		eventPublisher: eventPublisher,
		logger:         logger,
	}
}

// Create creates a new setting and publishes event
func (s *SettingService) Create(req *models.SettingCreateRequest, companyID uint) (*models.Setting, error) {
	// Convert value to JSON
	valueJSON, err := json.Marshal(req.Value)
	if err != nil {
		s.logger.WithError(err).Error("Failed to marshal setting value")
		return nil, fmt.Errorf("invalid value format: %w", err)
	}

	setting := &models.Setting{
		CompanyID: companyID,
		Key:       req.Key,
		Value:     valueJSON,
		Module:    req.Module,
		Type:      req.Type,
		IsActive:  req.IsActive,
		CreatedBy: req.CreatedBy,
	}

	if err := s.settingRepo.Create(setting); err != nil {
		s.logger.WithError(err).WithFields(logrus.Fields{
			"company_id": companyID,
			"key":        req.Key,
		}).Error("Failed to create setting")
		return nil, err
	}

	// Log audit entry
	s.logAudit(&models.AuditLog{
		CompanyID: companyID,
		SettingID: setting.ID,
		Action:    "create",
		NewValue:  valueJSON,
		ChangedBy: req.CreatedBy,
	})

	// Publish event
	s.publishEvent("setting.created", map[string]interface{}{
		"id":         setting.ID,
		"company_id": companyID,
		"key":        setting.Key,
		"module":     setting.Module,
	})

	s.logger.WithFields(logrus.Fields{
		"setting_id": setting.ID,
		"company_id": companyID,
		"key":        setting.Key,
	}).Info("Setting created successfully")

	return setting, nil
}

// Get retrieves a setting by ID
func (s *SettingService) Get(id string, companyID uint) (*models.Setting, error) {
	setting, err := s.settingRepo.GetByID(id, companyID)
	if err != nil {
		s.logger.WithError(err).WithFields(logrus.Fields{
			"setting_id": id,
			"company_id": companyID,
		}).Error("Failed to get setting")
		return nil, err
	}

	if setting == nil {
		return nil, fmt.Errorf("setting not found")
	}

	return setting, nil
}

// GetByKey retrieves a setting by key
func (s *SettingService) GetByKey(key string, companyID uint) (*models.Setting, error) {
	setting, err := s.settingRepo.GetByKey(key, companyID)
	if err != nil {
		s.logger.WithError(err).WithFields(logrus.Fields{
			"key":        key,
			"company_id": companyID,
		}).Error("Failed to get setting by key")
		return nil, err
	}

	if setting == nil {
		return nil, fmt.Errorf("setting not found")
	}

	return setting, nil
}

// List lists settings with pagination
func (s *SettingService) List(companyID uint, module string, page, pageSize int) ([]models.Setting, int64, error) {
	if page < 1 {
		page = 1
	}
	if pageSize < 1 || pageSize > 100 {
		pageSize = 20
	}

	settings, total, err := s.settingRepo.List(companyID, module, page, pageSize)
	if err != nil {
		s.logger.WithError(err).WithFields(logrus.Fields{
			"company_id": companyID,
			"module":     module,
			"page":       page,
			"page_size":  pageSize,
		}).Error("Failed to list settings")
		return nil, 0, err
	}

	return settings, total, nil
}

// Update updates a setting
func (s *SettingService) Update(id string, req *models.SettingUpdateRequest, companyID uint) (*models.Setting, error) {
	// Get existing setting
	existing, err := s.settingRepo.GetByID(id, companyID)
	if err != nil {
		return nil, err
	}

	if existing == nil {
		return nil, fmt.Errorf("setting not found")
	}

	// Store old value for audit
	oldValue := existing.Value

	// Update fields if provided
	if req.Key != "" {
		existing.Key = req.Key
	}
	if req.Module != "" {
		existing.Module = req.Module
	}
	if req.Type != "" {
		existing.Type = req.Type
	}

	// Handle value update
	if req.Value != nil {
		valueJSON, err := json.Marshal(req.Value)
		if err != nil {
			s.logger.WithError(err).Error("Failed to marshal setting value")
			return nil, fmt.Errorf("invalid value format: %w", err)
		}
		existing.Value = valueJSON
	}

	existing.IsActive = req.IsActive
	existing.UpdatedBy = req.UpdatedBy

	if err := s.settingRepo.Update(existing); err != nil {
		s.logger.WithError(err).WithFields(logrus.Fields{
			"setting_id": id,
			"company_id": companyID,
		}).Error("Failed to update setting")
		return nil, err
	}

	// Log audit entry
	s.logAudit(&models.AuditLog{
		CompanyID: companyID,
		SettingID: id,
		Action:    "update",
		OldValue:  oldValue,
		NewValue:  existing.Value,
		ChangedBy: req.UpdatedBy,
	})

	// Publish event
	s.publishEvent("setting.updated", map[string]interface{}{
		"id":         id,
		"company_id": companyID,
		"key":        existing.Key,
		"module":     existing.Module,
	})

	s.logger.WithFields(logrus.Fields{
		"setting_id": id,
		"company_id": companyID,
	}).Info("Setting updated successfully")

	return existing, nil
}

// Delete deletes a setting
func (s *SettingService) Delete(id string, companyID uint, deletedBy uint) error {
	// Get setting before deletion
	setting, err := s.settingRepo.GetByID(id, companyID)
	if err != nil {
		return err
	}

	if setting == nil {
		return fmt.Errorf("setting not found")
	}

	if err := s.settingRepo.Delete(id, companyID); err != nil {
		s.logger.WithError(err).WithFields(logrus.Fields{
			"setting_id": id,
			"company_id": companyID,
		}).Error("Failed to delete setting")
		return err
	}

	// Log audit entry
	s.logAudit(&models.AuditLog{
		CompanyID: companyID,
		SettingID: id,
		Action:    "delete",
		OldValue:  setting.Value,
		ChangedBy: deletedBy,
	})

	// Publish event
	s.publishEvent("setting.deleted", map[string]interface{}{
		"id":         id,
		"company_id": companyID,
		"key":        setting.Key,
	})

	s.logger.WithFields(logrus.Fields{
		"setting_id": id,
		"company_id": companyID,
	}).Info("Setting deleted successfully")

	return nil
}

// BatchCreate creates multiple settings
func (s *SettingService) BatchCreate(requests []models.SettingCreateRequest, companyID uint) error {
	settings := make([]models.Setting, len(requests))

	for i, req := range requests {
		valueJSON, err := json.Marshal(req.Value)
		if err != nil {
			s.logger.WithError(err).Error("Failed to marshal setting value")
			return fmt.Errorf("invalid value format for key %s: %w", req.Key, err)
		}

		settings[i] = models.Setting{
			CompanyID: companyID,
			Key:       req.Key,
			Value:     valueJSON,
			Module:    req.Module,
			Type:      req.Type,
			IsActive:  req.IsActive,
			CreatedBy: req.CreatedBy,
		}
	}

	if err := s.settingRepo.CreateMany(settings); err != nil {
		s.logger.WithError(err).WithFields(logrus.Fields{
			"company_id": companyID,
			"count":      len(requests),
		}).Error("Failed to batch create settings")
		return err
	}

	// Publish batch event
	s.publishEvent("settings.batch_created", map[string]interface{}{
		"company_id": companyID,
		"count":      len(settings),
	})

	s.logger.WithFields(logrus.Fields{
		"company_id": companyID,
		"count":      len(settings),
	}).Info("Batch create settings completed")

	return nil
}

// BatchUpdate updates multiple settings
func (s *SettingService) BatchUpdate(updates map[string]interface{}, companyID uint, updatedBy uint) error {
	var updateModels []models.Setting

	for key, value := range updates {
		setting, err := s.settingRepo.GetByKey(key, companyID)
		if err != nil || setting == nil {
			s.logger.WithFields(logrus.Fields{
				"key":        key,
				"company_id": companyID,
			}).Warn("Setting not found for batch update")
			continue
		}

		oldValue := setting.Value
		valueJSON, err := json.Marshal(value)
		if err != nil {
			s.logger.WithError(err).WithField("key", key).Error("Failed to marshal setting value")
			continue
		}

		setting.Value = valueJSON
		setting.UpdatedBy = updatedBy
		setting.UpdatedAt = time.Now()

		updateModels = append(updateModels, *setting)

		// Log audit entry
		s.logAudit(&models.AuditLog{
			CompanyID: companyID,
			SettingID: setting.ID,
			Action:    "update",
			OldValue:  oldValue,
			NewValue:  valueJSON,
			ChangedBy: updatedBy,
		})
	}

	if len(updateModels) > 0 {
		if err := s.settingRepo.UpdateMany(updateModels); err != nil {
			s.logger.WithError(err).WithFields(logrus.Fields{
				"company_id": companyID,
				"count":      len(updateModels),
			}).Error("Failed to batch update settings")
			return err
		}

		// Publish batch event
		s.publishEvent("settings.batch_updated", map[string]interface{}{
			"company_id": companyID,
			"count":      len(updateModels),
		})

		s.logger.WithFields(logrus.Fields{
			"company_id": companyID,
			"count":      len(updateModels),
		}).Info("Batch update settings completed")
	}

	return nil
}

// GetAuditLogs retrieves audit logs for a company
func (s *SettingService) GetAuditLogs(companyID uint, page, pageSize int) ([]models.AuditLog, int64, error) {
	if page < 1 {
		page = 1
	}
	if pageSize < 1 || pageSize > 100 {
		pageSize = 20
	}

	logs, total, err := s.auditLogRepo.GetByCompany(companyID, page, pageSize)
	if err != nil {
		s.logger.WithError(err).WithFields(logrus.Fields{
			"company_id": companyID,
			"page":       page,
		}).Error("Failed to get audit logs")
		return nil, 0, err
	}

	return logs, total, nil
}

// Helper methods

func (s *SettingService) logAudit(audit *models.AuditLog) {
	if err := s.auditLogRepo.Create(audit); err != nil {
		s.logger.WithError(err).WithFields(logrus.Fields{
			"company_id": audit.CompanyID,
			"setting_id": audit.SettingID,
			"action":     audit.Action,
		}).Error("Failed to log audit entry")
	}
}

func (s *SettingService) publishEvent(eventType string, data map[string]interface{}) {
	payload := map[string]interface{}{
		"type":      eventType,
		"data":      data,
		"timestamp": time.Now().UTC().Format(time.RFC3339),
	}

	if err := s.eventPublisher.Publish(eventType, payload); err != nil {
		s.logger.WithError(err).WithField("event_type", eventType).Error("Failed to publish event")
	}
}
