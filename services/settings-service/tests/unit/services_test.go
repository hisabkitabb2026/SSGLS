package unit

import (
	"encoding/json"
	"testing"
	"time"

	"github.com/invoiceshelf/settings-service/internal/events"
	"github.com/invoiceshelf/settings-service/internal/models"
	"github.com/invoiceshelf/settings-service/internal/repositories"
	"github.com/invoiceshelf/settings-service/internal/services"
	"github.com/sirupsen/logrus"
	"github.com/stretchr/testify/assert"
	"github.com/stretchr/testify/mock"
	"gorm.io/gorm"
)

// MockSettingRepository is a mock for SettingRepository
type MockSettingRepository struct {
	mock.Mock
}

func (m *MockSettingRepository) Create(setting *models.Setting) error {
	args := m.Called(setting)
	return args.Error(0)
}

func (m *MockSettingRepository) GetByID(id string, companyID uint) (*models.Setting, error) {
	args := m.Called(id, companyID)
	if args.Get(0) == nil {
		return nil, args.Error(1)
	}
	return args.Get(0).(*models.Setting), args.Error(1)
}

func (m *MockSettingRepository) GetByKey(key string, companyID uint) (*models.Setting, error) {
	args := m.Called(key, companyID)
	if args.Get(0) == nil {
		return nil, args.Error(1)
	}
	return args.Get(0).(*models.Setting), args.Error(1)
}

func (m *MockSettingRepository) List(companyID uint, module string, page, pageSize int) ([]models.Setting, int64, error) {
	args := m.Called(companyID, module, page, pageSize)
	if args.Get(0) == nil {
		return nil, args.Get(1).(int64), args.Error(2)
	}
	return args.Get(0).([]models.Setting), args.Get(1).(int64), args.Error(2)
}

func (m *MockSettingRepository) Update(setting *models.Setting) error {
	args := m.Called(setting)
	return args.Error(0)
}

func (m *MockSettingRepository) Delete(id string, companyID uint) error {
	args := m.Called(id, companyID)
	return args.Error(0)
}

func (m *MockSettingRepository) GetByModule(companyID uint, module string) ([]models.Setting, error) {
	args := m.Called(companyID, module)
	if args.Get(0) == nil {
		return nil, args.Error(1)
	}
	return args.Get(0).([]models.Setting), args.Error(1)
}

func (m *MockSettingRepository) CreateMany(settings []models.Setting) error {
	args := m.Called(settings)
	return args.Error(0)
}

func (m *MockSettingRepository) UpdateMany(settings []models.Setting) error {
	args := m.Called(settings)
	return args.Error(0)
}

// MockAuditLogRepository is a mock for AuditLogRepository
type MockAuditLogRepository struct {
	mock.Mock
}

func (m *MockAuditLogRepository) Create(log *models.AuditLog) error {
	args := m.Called(log)
	return args.Error(0)
}

func (m *MockAuditLogRepository) GetBySettingID(settingID string, companyID uint) ([]models.AuditLog, error) {
	args := m.Called(settingID, companyID)
	if args.Get(0) == nil {
		return nil, args.Error(1)
	}
	return args.Get(0).([]models.AuditLog), args.Error(1)
}

func (m *MockAuditLogRepository) GetByCompany(companyID uint, page, pageSize int) ([]models.AuditLog, int64, error) {
	args := m.Called(companyID, page, pageSize)
	if args.Get(0) == nil {
		return nil, args.Get(1).(int64), args.Error(2)
	}
	return args.Get(0).([]models.AuditLog), args.Get(1).(int64), args.Error(2)
}

func (m *MockAuditLogRepository) PurgeOldLogs(companyID uint, days int) error {
	args := m.Called(companyID, days)
	return args.Error(0)
}

func (m *MockAuditLogRepository) GetByAction(companyID uint, action string) ([]models.AuditLog, error) {
	args := m.Called(companyID, action)
	if args.Get(0) == nil {
		return nil, args.Error(1)
	}
	return args.Get(0).([]models.AuditLog), args.Error(1)
}

// MockPublisher is a mock for events.Publisher
type MockPublisher struct {
	mock.Mock
}

func (m *MockPublisher) Publish(eventType string, data interface{}) error {
	args := m.Called(eventType, data)
	return args.Error(0)
}

func (m *MockPublisher) Close() error {
	args := m.Called()
	return args.Error(0)
}

func (m *MockPublisher) PublishBatch(events map[string]interface{}) error {
	args := m.Called(events)
	return args.Error(0)
}

// Test Cases

func TestCreateSetting_Success(t *testing.T) {
	mockSettingRepo := new(MockSettingRepository)
	mockAuditRepo := new(MockAuditLogRepository)
	mockPublisher := new(MockPublisher)
	logger := logrus.New()

	companyID := uint(1)
	userID := uint(10)

	req := &models.SettingCreateRequest{
		Key:       "app_name",
		Value:     "My App",
		Module:    "general",
		Type:      "string",
		IsActive:  true,
		CreatedBy: userID,
	}

	// Mock expectations
	mockSettingRepo.On("Create", mock.MatchedBy(func(s *models.Setting) bool {
		return s.Key == req.Key && s.CompanyID == companyID
	})).Return(nil).Run(func(args mock.Arguments) {
		setting := args.Get(0).(*models.Setting)
		setting.ID = "test-id-123"
	})

	mockAuditRepo.On("Create", mock.MatchedBy(func(log *models.AuditLog) bool {
		return log.Action == "create"
	})).Return(nil)

	mockPublisher.On("Publish", "setting.created", mock.Anything).Return(nil)

	service := services.NewSettingService(mockSettingRepo, mockAuditRepo, mockPublisher, logger)
	result, err := service.Create(req, companyID)

	assert.NoError(t, err)
	assert.NotNil(t, result)
	assert.Equal(t, companyID, result.CompanyID)
	assert.Equal(t, req.Key, result.Key)

	mockSettingRepo.AssertExpectations(t)
	mockAuditRepo.AssertExpectations(t)
	mockPublisher.AssertExpectations(t)
}

func TestGetSetting_Success(t *testing.T) {
	mockSettingRepo := new(MockSettingRepository)
	mockAuditRepo := new(MockAuditLogRepository)
	mockPublisher := new(MockPublisher)
	logger := logrus.New()

	companyID := uint(1)
	settingID := "test-id-123"

	expectedSetting := &models.Setting{
		ID:        settingID,
		CompanyID: companyID,
		Key:       "app_name",
		Value:     json.RawMessage(`"My App"`),
		Module:    "general",
		Type:      "string",
		IsActive:  true,
		CreatedAt: time.Now(),
	}

	mockSettingRepo.On("GetByID", settingID, companyID).Return(expectedSetting, nil)

	service := services.NewSettingService(mockSettingRepo, mockAuditRepo, mockPublisher, logger)
	result, err := service.Get(settingID, companyID)

	assert.NoError(t, err)
	assert.Equal(t, expectedSetting, result)

	mockSettingRepo.AssertExpectations(t)
}

func TestGetByKey_Success(t *testing.T) {
	mockSettingRepo := new(MockSettingRepository)
	mockAuditRepo := new(MockAuditLogRepository)
	mockPublisher := new(MockPublisher)
	logger := logrus.New()

	companyID := uint(1)
	key := "app_name"

	expectedSetting := &models.Setting{
		ID:        "test-id-123",
		CompanyID: companyID,
		Key:       key,
		Value:     json.RawMessage(`"My App"`),
		Module:    "general",
		Type:      "string",
		IsActive:  true,
	}

	mockSettingRepo.On("GetByKey", key, companyID).Return(expectedSetting, nil)

	service := services.NewSettingService(mockSettingRepo, mockAuditRepo, mockPublisher, logger)
	result, err := service.GetByKey(key, companyID)

	assert.NoError(t, err)
	assert.Equal(t, expectedSetting, result)

	mockSettingRepo.AssertExpectations(t)
}

func TestListSettings_Success(t *testing.T) {
	mockSettingRepo := new(MockSettingRepository)
	mockAuditRepo := new(MockAuditLogRepository)
	mockPublisher := new(MockPublisher)
	logger := logrus.New()

	companyID := uint(1)
	page := 1
	pageSize := 20

	settings := []models.Setting{
		{
			ID:        "id-1",
			CompanyID: companyID,
			Key:       "setting1",
			Value:     json.RawMessage(`"value1"`),
		},
		{
			ID:        "id-2",
			CompanyID: companyID,
			Key:       "setting2",
			Value:     json.RawMessage(`"value2"`),
		},
	}

	mockSettingRepo.On("List", companyID, "", page, pageSize).Return(settings, int64(2), nil)

	service := services.NewSettingService(mockSettingRepo, mockAuditRepo, mockPublisher, logger)
	results, total, err := service.List(companyID, "", page, pageSize)

	assert.NoError(t, err)
	assert.Equal(t, len(settings), len(results))
	assert.Equal(t, int64(2), total)

	mockSettingRepo.AssertExpectations(t)
}

func TestUpdateSetting_Success(t *testing.T) {
	mockSettingRepo := new(MockSettingRepository)
	mockAuditRepo := new(MockAuditLogRepository)
	mockPublisher := new(MockPublisher)
	logger := logrus.New()

	companyID := uint(1)
	settingID := "test-id-123"
	userID := uint(10)

	existingSetting := &models.Setting{
		ID:        settingID,
		CompanyID: companyID,
		Key:       "app_name",
		Value:     json.RawMessage(`"Old Value"`),
		Module:    "general",
		Type:      "string",
		IsActive:  true,
	}

	req := &models.SettingUpdateRequest{
		Value:     "Updated Value",
		UpdatedBy: userID,
	}

	mockSettingRepo.On("GetByID", settingID, companyID).Return(existingSetting, nil)
	mockSettingRepo.On("Update", mock.MatchedBy(func(s *models.Setting) bool {
		return s.ID == settingID
	})).Return(nil)
	mockAuditRepo.On("Create", mock.MatchedBy(func(log *models.AuditLog) bool {
		return log.Action == "update"
	})).Return(nil)
	mockPublisher.On("Publish", "setting.updated", mock.Anything).Return(nil)

	service := services.NewSettingService(mockSettingRepo, mockAuditRepo, mockPublisher, logger)
	result, err := service.Update(settingID, req, companyID)

	assert.NoError(t, err)
	assert.NotNil(t, result)

	mockSettingRepo.AssertExpectations(t)
	mockAuditRepo.AssertExpectations(t)
	mockPublisher.AssertExpectations(t)
}

func TestDeleteSetting_Success(t *testing.T) {
	mockSettingRepo := new(MockSettingRepository)
	mockAuditRepo := new(MockAuditLogRepository)
	mockPublisher := new(MockPublisher)
	logger := logrus.New()

	companyID := uint(1)
	settingID := "test-id-123"
	userID := uint(10)

	existingSetting := &models.Setting{
		ID:        settingID,
		CompanyID: companyID,
		Key:       "app_name",
		Value:     json.RawMessage(`"My App"`),
	}

	mockSettingRepo.On("GetByID", settingID, companyID).Return(existingSetting, nil)
	mockSettingRepo.On("Delete", settingID, companyID).Return(nil)
	mockAuditRepo.On("Create", mock.MatchedBy(func(log *models.AuditLog) bool {
		return log.Action == "delete"
	})).Return(nil)
	mockPublisher.On("Publish", "setting.deleted", mock.Anything).Return(nil)

	service := services.NewSettingService(mockSettingRepo, mockAuditRepo, mockPublisher, logger)
	err := service.Delete(settingID, companyID, userID)

	assert.NoError(t, err)

	mockSettingRepo.AssertExpectations(t)
	mockAuditRepo.AssertExpectations(t)
	mockPublisher.AssertExpectations(t)
}

func TestBatchCreateSettings_Success(t *testing.T) {
	mockSettingRepo := new(MockSettingRepository)
	mockAuditRepo := new(MockAuditLogRepository)
	mockPublisher := new(MockPublisher)
	logger := logrus.New()

	companyID := uint(1)

	requests := []models.SettingCreateRequest{
		{
			Key:       "setting1",
			Value:     "value1",
			CreatedBy: 10,
		},
		{
			Key:       "setting2",
			Value:     "value2",
			CreatedBy: 10,
		},
	}

	mockSettingRepo.On("CreateMany", mock.MatchedBy(func(settings []models.Setting) bool {
		return len(settings) == 2
	})).Return(nil)
	mockPublisher.On("Publish", "settings.batch_created", mock.Anything).Return(nil)

	service := services.NewSettingService(mockSettingRepo, mockAuditRepo, mockPublisher, logger)
	err := service.BatchCreate(requests, companyID)

	assert.NoError(t, err)

	mockSettingRepo.AssertExpectations(t)
	mockPublisher.AssertExpectations(t)
}

func TestGetSetting_NotFound(t *testing.T) {
	mockSettingRepo := new(MockSettingRepository)
	mockAuditRepo := new(MockAuditLogRepository)
	mockPublisher := new(MockPublisher)
	logger := logrus.New()

	companyID := uint(1)
	settingID := "non-existent-id"

	mockSettingRepo.On("GetByID", settingID, companyID).Return(nil, nil)

	service := services.NewSettingService(mockSettingRepo, mockAuditRepo, mockPublisher, logger)
	result, err := service.Get(settingID, companyID)

	assert.Error(t, err)
	assert.Nil(t, result)
	assert.Equal(t, "setting not found", err.Error())

	mockSettingRepo.AssertExpectations(t)
}

func TestListSettings_WithModule(t *testing.T) {
	mockSettingRepo := new(MockSettingRepository)
	mockAuditRepo := new(MockAuditLogRepository)
	mockPublisher := new(MockPublisher)
	logger := logrus.New()

	companyID := uint(1)
	module := "billing"
	page := 1
	pageSize := 20

	settings := []models.Setting{
		{
			ID:        "id-1",
			CompanyID: companyID,
			Key:       "setting1",
			Module:    module,
			Value:     json.RawMessage(`"value1"`),
		},
	}

	mockSettingRepo.On("List", companyID, module, page, pageSize).Return(settings, int64(1), nil)

	service := services.NewSettingService(mockSettingRepo, mockAuditRepo, mockPublisher, logger)
	results, total, err := service.List(companyID, module, page, pageSize)

	assert.NoError(t, err)
	assert.Equal(t, 1, len(results))
	assert.Equal(t, int64(1), total)

	mockSettingRepo.AssertExpectations(t)
}

func TestGetAuditLogs_Success(t *testing.T) {
	mockSettingRepo := new(MockSettingRepository)
	mockAuditRepo := new(MockAuditLogRepository)
	mockPublisher := new(MockPublisher)
	logger := logrus.New()

	companyID := uint(1)
	page := 1
	pageSize := 20

	logs := []models.AuditLog{
		{
			ID:        "log-1",
			CompanyID: companyID,
			Action:    "create",
			CreatedAt: time.Now(),
		},
	}

	mockAuditRepo.On("GetByCompany", companyID, page, pageSize).Return(logs, int64(1), nil)

	service := services.NewSettingService(mockSettingRepo, mockAuditRepo, mockPublisher, logger)
	results, total, err := service.GetAuditLogs(companyID, page, pageSize)

	assert.NoError(t, err)
	assert.Equal(t, 1, len(results))
	assert.Equal(t, int64(1), total)

	mockAuditRepo.AssertExpectations(t)
}
