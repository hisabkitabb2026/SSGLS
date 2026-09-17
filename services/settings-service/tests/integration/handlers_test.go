package integration

import (
	"bytes"
	"encoding/json"
	"fmt"
	"net/http"
	"net/http/httptest"
	"testing"
	"time"

	"github.com/gin-gonic/gin"
	"github.com/golang-jwt/jwt/v5"
	"github.com/invoiceshelf/settings-service/internal/handlers"
	"github.com/invoiceshelf/settings-service/internal/middleware"
	"github.com/invoiceshelf/settings-service/internal/models"
	"github.com/invoiceshelf/settings-service/internal/repositories"
	"github.com/invoiceshelf/settings-service/internal/services"
	"github.com/sirupsen/logrus"
	"github.com/stretchr/testify/assert"
	"github.com/stretchr/testify/suite"
	"gorm.io/driver/sqlite"
	"gorm.io/gorm"
)

// HandlerTestSuite is the test suite for handlers
type HandlerTestSuite struct {
	suite.Suite
	db          *gorm.DB
	router      *gin.Engine
	jwtSecret   string
	companyID   uint
	userID      uint
	bearerToken string
}

// SetupSuite initializes the test suite
func (suite *HandlerTestSuite) SetupSuite() {
	suite.jwtSecret = "test-secret-key"
	suite.companyID = 1
	suite.userID = 10

	// Initialize in-memory SQLite database
	db, err := gorm.Open(sqlite.Open("file::memory:?cache=shared"), &gorm.Config{})
	suite.NoError(err)
	suite.db = db

	// Run migrations
	err = db.AutoMigrate(&models.Setting{}, &models.AuditLog{})
	suite.NoError(err)

	// Create JWT token
	token := jwt.NewWithClaims(jwt.SigningMethodHS256, &middleware.JWTClaims{
		UserID:    suite.userID,
		CompanyID: suite.companyID,
		Email:     "test@example.com",
		Roles:     []string{"admin"},
		RegisteredClaims: jwt.RegisteredClaims{
			ExpiresAt: jwt.NewNumericDate(time.Now().Add(1 * time.Hour)),
		},
	})

	tokenString, err := token.SignedString([]byte(suite.jwtSecret))
	suite.NoError(err)
	suite.bearerToken = fmt.Sprintf("Bearer %s", tokenString)

	// Setup router
	suite.setupRouter()
}

// setupRouter configures the router for testing
func (suite *HandlerTestSuite) setupRouter() {
	gin.SetMode(gin.TestMode)
	suite.router = gin.New()

	// Initialize repositories and services
	settingRepo := repositories.NewSettingRepository(suite.db)
	auditLogRepo := repositories.NewAuditLogRepository(suite.db)

	// Create mock publisher
	mockPublisher := &MockEventPublisher{}

	logger := logrus.New()
	logger.SetLevel(logrus.ErrorLevel) // Suppress logs in tests

	settingService := services.NewSettingService(settingRepo, auditLogRepo, mockPublisher, logger)
	settingHandler := handlers.NewSettingHandler(settingService, logger)
	healthHandler := handlers.NewHealthHandler(suite.db, logger)

	// Health endpoints
	suite.router.GET("/health", healthHandler.Health)
	suite.router.GET("/ready", healthHandler.Ready)

	// API endpoints with JWT middleware
	api := suite.router.Group("/api/v1")
	api.Use(middleware.JWTMiddleware(suite.jwtSecret, logger))

	api.POST("/settings", settingHandler.Create)
	api.GET("/settings", settingHandler.List)
	api.GET("/settings/:id", settingHandler.Get)
	api.PUT("/settings/:id", settingHandler.Update)
	api.DELETE("/settings/:id", settingHandler.Delete)
	api.GET("/settings/key/:key", settingHandler.GetByKey)
	api.PUT("/settings/key/:key", settingHandler.UpdateByKey)
	api.POST("/settings/batch", settingHandler.BatchCreate)
	api.PUT("/settings/batch", settingHandler.BatchUpdate)
	api.GET("/settings/audit-logs", settingHandler.GetAuditLogs)
}

// TearDownSuite cleans up after the suite
func (suite *HandlerTestSuite) TearDownSuite() {
	// Clean up if needed
}

// TestCreateSetting tests the Create handler
func (suite *HandlerTestSuite) TestCreateSetting() {
	req := models.SettingCreateRequest{
		Key:       "test_key",
		Value:     "test_value",
		Module:    "test_module",
		Type:      "string",
		IsActive:  true,
		CreatedBy: suite.userID,
	}

	body, _ := json.Marshal(req)
	httpReq := httptest.NewRequest("POST", "/api/v1/settings", bytes.NewReader(body))
	httpReq.Header.Set("Authorization", suite.bearerToken)
	httpReq.Header.Set("Content-Type", "application/json")

	w := httptest.NewRecorder()
	suite.router.ServeHTTP(w, httpReq)

	suite.Equal(http.StatusCreated, w.Code)

	var response map[string]interface{}
	_ = json.Unmarshal(w.Body.Bytes(), &response)
	suite.True(response["success"].(bool))
	suite.NotNil(response["data"])
}

// TestGetSetting tests the Get handler
func (suite *HandlerTestSuite) TestGetSetting() {
	// First create a setting
	setting := &models.Setting{
		CompanyID: suite.companyID,
		Key:       "test_key",
		Value:     []byte(`"test_value"`),
		Module:    "test_module",
		Type:      "string",
		IsActive:  true,
		CreatedBy: suite.userID,
	}
	suite.db.Create(setting)

	// Get the setting
	httpReq := httptest.NewRequest("GET", fmt.Sprintf("/api/v1/settings/%s", setting.ID), nil)
	httpReq.Header.Set("Authorization", suite.bearerToken)

	w := httptest.NewRecorder()
	suite.router.ServeHTTP(w, httpReq)

	suite.Equal(http.StatusOK, w.Code)

	var response map[string]interface{}
	_ = json.Unmarshal(w.Body.Bytes(), &response)
	suite.True(response["success"].(bool))
	suite.NotNil(response["data"])
}

// TestListSettings tests the List handler
func (suite *HandlerTestSuite) TestListSettings() {
	// Create test settings
	for i := 0; i < 5; i++ {
		setting := &models.Setting{
			CompanyID: suite.companyID,
			Key:       fmt.Sprintf("key_%d", i),
			Value:     []byte(fmt.Sprintf(`"value_%d"`, i)),
			Module:    "test_module",
			Type:      "string",
			IsActive:  true,
			CreatedBy: suite.userID,
		}
		suite.db.Create(setting)
	}

	// List settings
	httpReq := httptest.NewRequest("GET", "/api/v1/settings?page=1&page_size=20", nil)
	httpReq.Header.Set("Authorization", suite.bearerToken)

	w := httptest.NewRecorder()
	suite.router.ServeHTTP(w, httpReq)

	suite.Equal(http.StatusOK, w.Code)

	var response map[string]interface{}
	_ = json.Unmarshal(w.Body.Bytes(), &response)
	suite.True(response["success"].(bool))
	suite.NotNil(response["data"])
}

// TestUpdateSetting tests the Update handler
func (suite *HandlerTestSuite) TestUpdateSetting() {
	// Create a setting
	setting := &models.Setting{
		CompanyID: suite.companyID,
		Key:       "test_key",
		Value:     []byte(`"old_value"`),
		Module:    "test_module",
		Type:      "string",
		IsActive:  true,
		CreatedBy: suite.userID,
	}
	suite.db.Create(setting)

	// Update the setting
	updateReq := models.SettingUpdateRequest{
		Value:     "new_value",
		UpdatedBy: suite.userID,
	}

	body, _ := json.Marshal(updateReq)
	httpReq := httptest.NewRequest("PUT", fmt.Sprintf("/api/v1/settings/%s", setting.ID), bytes.NewReader(body))
	httpReq.Header.Set("Authorization", suite.bearerToken)
	httpReq.Header.Set("Content-Type", "application/json")

	w := httptest.NewRecorder()
	suite.router.ServeHTTP(w, httpReq)

	suite.Equal(http.StatusOK, w.Code)

	var response map[string]interface{}
	_ = json.Unmarshal(w.Body.Bytes(), &response)
	suite.True(response["success"].(bool))
	suite.NotNil(response["data"])
}

// TestDeleteSetting tests the Delete handler
func (suite *HandlerTestSuite) TestDeleteSetting() {
	// Create a setting
	setting := &models.Setting{
		CompanyID: suite.companyID,
		Key:       "test_key",
		Value:     []byte(`"test_value"`),
		Module:    "test_module",
		Type:      "string",
		IsActive:  true,
		CreatedBy: suite.userID,
	}
	suite.db.Create(setting)

	// Delete the setting
	httpReq := httptest.NewRequest("DELETE", fmt.Sprintf("/api/v1/settings/%s", setting.ID), nil)
	httpReq.Header.Set("Authorization", suite.bearerToken)

	w := httptest.NewRecorder()
	suite.router.ServeHTTP(w, httpReq)

	suite.Equal(http.StatusOK, w.Code)

	var response map[string]interface{}
	_ = json.Unmarshal(w.Body.Bytes(), &response)
	suite.True(response["success"].(bool))
}

// TestGetByKey tests the GetByKey handler
func (suite *HandlerTestSuite) TestGetByKey() {
	// Create a setting
	setting := &models.Setting{
		CompanyID: suite.companyID,
		Key:       "unique_key",
		Value:     []byte(`"test_value"`),
		Module:    "test_module",
		Type:      "string",
		IsActive:  true,
		CreatedBy: suite.userID,
	}
	suite.db.Create(setting)

	// Get by key
	httpReq := httptest.NewRequest("GET", "/api/v1/settings/key/unique_key", nil)
	httpReq.Header.Set("Authorization", suite.bearerToken)

	w := httptest.NewRecorder()
	suite.router.ServeHTTP(w, httpReq)

	suite.Equal(http.StatusOK, w.Code)

	var response map[string]interface{}
	_ = json.Unmarshal(w.Body.Bytes(), &response)
	suite.True(response["success"].(bool))
	suite.NotNil(response["data"])
}

// TestBatchCreateSettings tests the BatchCreate handler
func (suite *HandlerTestSuite) TestBatchCreateSettings() {
	req := models.BatchSettingRequest{
		Settings: []models.SettingCreateRequest{
			{
				Key:       "batch_key_1",
				Value:     "batch_value_1",
				CreatedBy: suite.userID,
			},
			{
				Key:       "batch_key_2",
				Value:     "batch_value_2",
				CreatedBy: suite.userID,
			},
		},
	}

	body, _ := json.Marshal(req)
	httpReq := httptest.NewRequest("POST", "/api/v1/settings/batch", bytes.NewReader(body))
	httpReq.Header.Set("Authorization", suite.bearerToken)
	httpReq.Header.Set("Content-Type", "application/json")

	w := httptest.NewRecorder()
	suite.router.ServeHTTP(w, httpReq)

	suite.Equal(http.StatusCreated, w.Code)

	var response map[string]interface{}
	_ = json.Unmarshal(w.Body.Bytes(), &response)
	suite.True(response["success"].(bool))
}

// TestHealthCheck tests the Health handler
func (suite *HandlerTestSuite) TestHealthCheck() {
	httpReq := httptest.NewRequest("GET", "/health", nil)
	w := httptest.NewRecorder()
	suite.router.ServeHTTP(w, httpReq)

	suite.Equal(http.StatusOK, w.Code)

	var response map[string]interface{}
	_ = json.Unmarshal(w.Body.Bytes(), &response)
	suite.Equal("healthy", response["status"])
}

// TestReadinessCheck tests the Ready handler
func (suite *HandlerTestSuite) TestReadinessCheck() {
	httpReq := httptest.NewRequest("GET", "/ready", nil)
	w := httptest.NewRecorder()
	suite.router.ServeHTTP(w, httpReq)

	suite.Equal(http.StatusOK, w.Code)

	var response map[string]interface{}
	_ = json.Unmarshal(w.Body.Bytes(), &response)
	suite.True(response["ready"].(bool))
}

// TestUnauthorized tests unauthorized request
func (suite *HandlerTestSuite) TestUnauthorized() {
	httpReq := httptest.NewRequest("GET", "/api/v1/settings", nil)
	// No Authorization header

	w := httptest.NewRecorder()
	suite.router.ServeHTTP(w, httpReq)

	suite.Equal(http.StatusUnauthorized, w.Code)
}

// TestGetAuditLogs tests the GetAuditLogs handler
func (suite *HandlerTestSuite) TestGetAuditLogs() {
	// Create a setting and audit log
	setting := &models.Setting{
		CompanyID: suite.companyID,
		Key:       "test_key",
		Value:     []byte(`"test_value"`),
	}
	suite.db.Create(setting)

	auditLog := &models.AuditLog{
		CompanyID: suite.companyID,
		SettingID: setting.ID,
		Action:    "create",
		NewValue:  []byte(`"test_value"`),
		ChangedBy: suite.userID,
	}
	suite.db.Create(auditLog)

	// Get audit logs
	httpReq := httptest.NewRequest("GET", "/api/v1/settings/audit-logs?page=1&page_size=20", nil)
	httpReq.Header.Set("Authorization", suite.bearerToken)

	w := httptest.NewRecorder()
	suite.router.ServeHTTP(w, httpReq)

	suite.Equal(http.StatusOK, w.Code)

	var response map[string]interface{}
	_ = json.Unmarshal(w.Body.Bytes(), &response)
	suite.True(response["success"].(bool))
}

// RunHandlerTestSuite runs all tests
func TestHandlerTestSuite(t *testing.T) {
	suite.Run(t, new(HandlerTestSuite))
}

// MockEventPublisher is a mock implementation
type MockEventPublisher struct{}

func (m *MockEventPublisher) Publish(eventType string, data interface{}) error {
	return nil
}

func (m *MockEventPublisher) Close() error {
	return nil
}

func (m *MockEventPublisher) PublishBatch(events map[string]interface{}) error {
	return nil
}
