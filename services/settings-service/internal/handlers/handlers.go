package handlers

import (
	"errors"
	"net/http"
	"strconv"
	"time"

	"github.com/gin-gonic/gin"
	"github.com/invoiceshelf/settings-service/internal/middleware"
	"github.com/invoiceshelf/settings-service/internal/models"
	"github.com/invoiceshelf/settings-service/internal/services"
	"github.com/sirupsen/logrus"
	"gorm.io/gorm"
)

// SettingHandler handles setting-related HTTP requests
type SettingHandler struct {
	service *services.SettingService
	logger  *logrus.Logger
}

// HealthHandler handles health check requests
type HealthHandler struct {
	db     *gorm.DB
	logger *logrus.Logger
}

// NewSettingHandler creates a new setting handler
func NewSettingHandler(service *services.SettingService, logger *logrus.Logger) *SettingHandler {
	return &SettingHandler{
		service: service,
		logger:  logger,
	}
}

// NewHealthHandler creates a new health handler
func NewHealthHandler(db *gorm.DB, logger *logrus.Logger) *HealthHandler {
	return &HealthHandler{
		db:     db,
		logger: logger,
	}
}

// ============= Setting Handlers =============

// Create handles POST /api/v1/settings
func (h *SettingHandler) Create(c *gin.Context) {
	var req models.SettingCreateRequest

	if err := c.ShouldBindJSON(&req); err != nil {
		h.logger.WithError(err).Error("Failed to bind request")
		h.errorResponse(c, http.StatusBadRequest, "Invalid request body", err.Error())
		return
	}

	companyID := middleware.GetCompanyID(c)

	setting, err := h.service.Create(&req, companyID)
	if err != nil {
		h.logger.WithError(err).Error("Failed to create setting")
		h.errorResponse(c, http.StatusInternalServerError, "Failed to create setting", err.Error())
		return
	}

	c.JSON(http.StatusCreated, gin.H{
		"success": true,
		"data":    h.toResponse(setting),
		"message": "Setting created successfully",
	})
}

// Get handles GET /api/v1/settings/:id
func (h *SettingHandler) Get(c *gin.Context) {
	id := c.Param("id")
	companyID := middleware.GetCompanyID(c)

	setting, err := h.service.Get(id, companyID)
	if err != nil {
		if errors.Is(err, gorm.ErrRecordNotFound) || err.Error() == "setting not found" {
			h.errorResponse(c, http.StatusNotFound, "Setting not found", "")
			return
		}
		h.logger.WithError(err).Error("Failed to get setting")
		h.errorResponse(c, http.StatusInternalServerError, "Failed to get setting", err.Error())
		return
	}

	c.JSON(http.StatusOK, gin.H{
		"success": true,
		"data":    h.toResponse(setting),
	})
}

// GetByKey handles GET /api/v1/settings/key/:key
func (h *SettingHandler) GetByKey(c *gin.Context) {
	key := c.Param("key")
	companyID := middleware.GetCompanyID(c)

	setting, err := h.service.GetByKey(key, companyID)
	if err != nil {
		if errors.Is(err, gorm.ErrRecordNotFound) || err.Error() == "setting not found" {
			h.errorResponse(c, http.StatusNotFound, "Setting not found", "")
			return
		}
		h.logger.WithError(err).Error("Failed to get setting by key")
		h.errorResponse(c, http.StatusInternalServerError, "Failed to get setting", err.Error())
		return
	}

	c.JSON(http.StatusOK, gin.H{
		"success": true,
		"data":    h.toResponse(setting),
	})
}

// List handles GET /api/v1/settings
func (h *SettingHandler) List(c *gin.Context) {
	companyID := middleware.GetCompanyID(c)
	module := c.Query("module")
	page, _ := strconv.Atoi(c.DefaultQuery("page", "1"))
	pageSize, _ := strconv.Atoi(c.DefaultQuery("page_size", "20"))

	settings, total, err := h.service.List(companyID, module, page, pageSize)
	if err != nil {
		h.logger.WithError(err).Error("Failed to list settings")
		h.errorResponse(c, http.StatusInternalServerError, "Failed to list settings", err.Error())
		return
	}

	responses := make([]models.SettingResponse, len(settings))
	for i, setting := range settings {
		responses[i] = h.toResponse(&setting)
	}

	totalPages := int((total + int64(pageSize) - 1) / int64(pageSize))

	c.JSON(http.StatusOK, gin.H{
		"success": true,
		"data":    responses,
		"pagination": gin.H{
			"total":       total,
			"page":        page,
			"page_size":   pageSize,
			"total_pages": totalPages,
		},
	})
}

// Update handles PUT /api/v1/settings/:id
func (h *SettingHandler) Update(c *gin.Context) {
	id := c.Param("id")
	var req models.SettingUpdateRequest

	if err := c.ShouldBindJSON(&req); err != nil {
		h.logger.WithError(err).Error("Failed to bind request")
		h.errorResponse(c, http.StatusBadRequest, "Invalid request body", err.Error())
		return
	}

	companyID := middleware.GetCompanyID(c)

	setting, err := h.service.Update(id, &req, companyID)
	if err != nil {
		if err.Error() == "setting not found" {
			h.errorResponse(c, http.StatusNotFound, "Setting not found", "")
			return
		}
		h.logger.WithError(err).Error("Failed to update setting")
		h.errorResponse(c, http.StatusInternalServerError, "Failed to update setting", err.Error())
		return
	}

	c.JSON(http.StatusOK, gin.H{
		"success": true,
		"data":    h.toResponse(setting),
		"message": "Setting updated successfully",
	})
}

// UpdateByKey handles PUT /api/v1/settings/key/:key
func (h *SettingHandler) UpdateByKey(c *gin.Context) {
	key := c.Param("key")
	companyID := middleware.GetCompanyID(c)

	// Get setting by key first
	setting, err := h.service.GetByKey(key, companyID)
	if err != nil || setting == nil {
		h.errorResponse(c, http.StatusNotFound, "Setting not found", "")
		return
	}

	var req models.SettingUpdateRequest
	if err := c.ShouldBindJSON(&req); err != nil {
		h.logger.WithError(err).Error("Failed to bind request")
		h.errorResponse(c, http.StatusBadRequest, "Invalid request body", err.Error())
		return
	}

	updated, err := h.service.Update(setting.ID, &req, companyID)
	if err != nil {
		h.logger.WithError(err).Error("Failed to update setting")
		h.errorResponse(c, http.StatusInternalServerError, "Failed to update setting", err.Error())
		return
	}

	c.JSON(http.StatusOK, gin.H{
		"success": true,
		"data":    h.toResponse(updated),
		"message": "Setting updated successfully",
	})
}

// Delete handles DELETE /api/v1/settings/:id
func (h *SettingHandler) Delete(c *gin.Context) {
	id := c.Param("id")
	companyID := middleware.GetCompanyID(c)
	userID := middleware.GetUserID(c)

	if err := h.service.Delete(id, companyID, userID); err != nil {
		if err.Error() == "setting not found" {
			h.errorResponse(c, http.StatusNotFound, "Setting not found", "")
			return
		}
		h.logger.WithError(err).Error("Failed to delete setting")
		h.errorResponse(c, http.StatusInternalServerError, "Failed to delete setting", err.Error())
		return
	}

	c.JSON(http.StatusOK, gin.H{
		"success": true,
		"message": "Setting deleted successfully",
	})
}

// BatchCreate handles POST /api/v1/settings/batch
func (h *SettingHandler) BatchCreate(c *gin.Context) {
	var req models.BatchSettingRequest

	if err := c.ShouldBindJSON(&req); err != nil {
		h.logger.WithError(err).Error("Failed to bind request")
		h.errorResponse(c, http.StatusBadRequest, "Invalid request body", err.Error())
		return
	}

	companyID := middleware.GetCompanyID(c)

	if err := h.service.BatchCreate(req.Settings, companyID); err != nil {
		h.logger.WithError(err).Error("Failed to batch create settings")
		h.errorResponse(c, http.StatusInternalServerError, "Failed to batch create settings", err.Error())
		return
	}

	c.JSON(http.StatusCreated, gin.H{
		"success": true,
		"message": "Settings created successfully",
		"count":   len(req.Settings),
	})
}

// BatchUpdate handles PUT /api/v1/settings/batch
func (h *SettingHandler) BatchUpdate(c *gin.Context) {
	var req models.BatchSettingUpdateRequest

	if err := c.ShouldBindJSON(&req); err != nil {
		h.logger.WithError(err).Error("Failed to bind request")
		h.errorResponse(c, http.StatusBadRequest, "Invalid request body", err.Error())
		return
	}

	companyID := middleware.GetCompanyID(c)

	if err := h.service.BatchUpdate(req.Settings, companyID, req.UpdatedBy); err != nil {
		h.logger.WithError(err).Error("Failed to batch update settings")
		h.errorResponse(c, http.StatusInternalServerError, "Failed to batch update settings", err.Error())
		return
	}

	c.JSON(http.StatusOK, gin.H{
		"success": true,
		"message": "Settings updated successfully",
		"count":   len(req.Settings),
	})
}

// GetAuditLogs handles GET /api/v1/settings/audit-logs
func (h *SettingHandler) GetAuditLogs(c *gin.Context) {
	companyID := middleware.GetCompanyID(c)
	page, _ := strconv.Atoi(c.DefaultQuery("page", "1"))
	pageSize, _ := strconv.Atoi(c.DefaultQuery("page_size", "20"))

	logs, total, err := h.service.GetAuditLogs(companyID, page, pageSize)
	if err != nil {
		h.logger.WithError(err).Error("Failed to get audit logs")
		h.errorResponse(c, http.StatusInternalServerError, "Failed to get audit logs", err.Error())
		return
	}

	auditResponses := make([]models.AuditLogResponse, len(logs))
	for i, log := range logs {
		auditResponses[i] = h.toAuditLogResponse(&log)
	}

	totalPages := int((total + int64(pageSize) - 1) / int64(pageSize))

	c.JSON(http.StatusOK, gin.H{
		"success": true,
		"data":    auditResponses,
		"pagination": gin.H{
			"total":       total,
			"page":        page,
			"page_size":   pageSize,
			"total_pages": totalPages,
		},
	})
}

// ============= Health Handlers =============

// Health handles GET /health
func (h *HealthHandler) Health(c *gin.Context) {
	c.JSON(http.StatusOK, gin.H{
		"status":    "healthy",
		"service":   "settings-microservice",
		"version":   "1.0.0",
		"timestamp": time.Now().UTC().Format(time.RFC3339),
	})
}

// Ready handles GET /ready
func (h *HealthHandler) Ready(c *gin.Context) {
	sqlDB, err := h.db.DB()
	if err != nil {
		h.logger.WithError(err).Error("Failed to get database connection")
		c.JSON(http.StatusServiceUnavailable, gin.H{
			"ready":      false,
			"service":    "settings-microservice",
			"database":   false,
			"timestamp":  time.Now().UTC().Format(time.RFC3339),
		})
		return
	}

	if err := sqlDB.Ping(); err != nil {
		h.logger.WithError(err).Error("Database ping failed")
		c.JSON(http.StatusServiceUnavailable, gin.H{
			"ready":      false,
			"service":    "settings-microservice",
			"database":   false,
			"timestamp":  time.Now().UTC().Format(time.RFC3339),
		})
		return
	}

	c.JSON(http.StatusOK, gin.H{
		"ready":      true,
		"service":    "settings-microservice",
		"database":   true,
		"timestamp":  time.Now().UTC().Format(time.RFC3339),
	})
}

// ============= Helper Methods =============

func (h *SettingHandler) toResponse(setting *models.Setting) models.SettingResponse {
	var value interface{}
	if err := setting.Value.UnmarshalJSON(&value); err != nil {
		value = string(setting.Value)
	}

	return models.SettingResponse{
		ID:        setting.ID,
		CompanyID: setting.CompanyID,
		Key:       setting.Key,
		Value:     value,
		Module:    setting.Module,
		Type:      setting.Type,
		IsActive:  setting.IsActive,
		CreatedBy: setting.CreatedBy,
		UpdatedBy: setting.UpdatedBy,
		CreatedAt: setting.CreatedAt,
		UpdatedAt: setting.UpdatedAt,
	}
}

func (h *SettingHandler) toAuditLogResponse(log *models.AuditLog) models.AuditLogResponse {
	var oldValue, newValue interface{}
	_ = log.OldValue.UnmarshalJSON(&oldValue)
	_ = log.NewValue.UnmarshalJSON(&newValue)

	return models.AuditLogResponse{
		ID:        log.ID,
		CompanyID: log.CompanyID,
		SettingID: log.SettingID,
		Action:    log.Action,
		OldValue:  oldValue,
		NewValue:  newValue,
		ChangedBy: log.ChangedBy,
		IPAddress: log.IPAddress,
		UserAgent: log.UserAgent,
		CreatedAt: log.CreatedAt,
	}
}

func (h *SettingHandler) errorResponse(c *gin.Context, statusCode int, message, details string) {
	c.JSON(statusCode, gin.H{
		"success":     false,
		"error":       message,
		"status_code": statusCode,
		"timestamp":   time.Now().UTC().Format(time.RFC3339),
		"details":     details,
	})
}
