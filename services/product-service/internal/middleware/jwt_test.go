package middleware

import (
	"net/http"
	"net/http/httptest"
	"testing"
	"time"

	"github.com/golang-jwt/jwt/v5"
	"github.com/stretchr/testify/assert"
	"go.uber.org/zap"
)

func TestJWTMiddleware_ValidToken(t *testing.T) {
	secret := "test-secret"
	logger, _ := zap.NewProduction()

	// Create valid token
	token := jwt.NewWithClaims(jwt.SigningMethodHS256, &Claims{
		UserID:    123,
		CompanyID: 456,
		Email:     "test@example.com",
		RegisteredClaims: jwt.RegisteredClaims{
			ExpiresAt: jwt.NewNumericDate(time.Now().Add(time.Hour)),
		},
	})

	tokenString, _ := token.SignedString([]byte(secret))

	// Create test handler
	handlerCalled := false
	testHandler := http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		handlerCalled = true
		userID := GetUserID(r)
		companyID := GetCompanyID(r)
		email := GetEmail(r)

		assert.Equal(t, int64(123), userID)
		assert.Equal(t, int64(456), companyID)
		assert.Equal(t, "test@example.com", email)
		w.WriteHeader(http.StatusOK)
	})

	// Create request with valid token
	req := httptest.NewRequest("GET", "/", nil)
	req.Header.Set("Authorization", "Bearer "+tokenString)

	// Apply middleware
	middleware := JWTMiddleware(secret, logger)
	wrappedHandler := middleware(testHandler)

	w := httptest.NewRecorder()
	wrappedHandler.ServeHTTP(w, req)

	assert.True(t, handlerCalled)
	assert.Equal(t, http.StatusOK, w.Code)
}

func TestJWTMiddleware_MissingAuthHeader(t *testing.T) {
	secret := "test-secret"
	logger, _ := zap.NewProduction()

	testHandler := http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		w.WriteHeader(http.StatusOK)
	})

	// Create request without auth header
	req := httptest.NewRequest("GET", "/", nil)

	middleware := JWTMiddleware(secret, logger)
	wrappedHandler := middleware(testHandler)

	w := httptest.NewRecorder()
	wrappedHandler.ServeHTTP(w, req)

	assert.Equal(t, http.StatusUnauthorized, w.Code)
}

func TestJWTMiddleware_InvalidAuthFormat(t *testing.T) {
	secret := "test-secret"
	logger, _ := zap.NewProduction()

	testHandler := http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		w.WriteHeader(http.StatusOK)
	})

	// Create request with invalid auth format
	req := httptest.NewRequest("GET", "/", nil)
	req.Header.Set("Authorization", "InvalidFormat")

	middleware := JWTMiddleware(secret, logger)
	wrappedHandler := middleware(testHandler)

	w := httptest.NewRecorder()
	wrappedHandler.ServeHTTP(w, req)

	assert.Equal(t, http.StatusUnauthorized, w.Code)
}

func TestJWTMiddleware_ExpiredToken(t *testing.T) {
	secret := "test-secret"
	logger, _ := zap.NewProduction()

	// Create expired token
	token := jwt.NewWithClaims(jwt.SigningMethodHS256, &Claims{
		UserID:    123,
		CompanyID: 456,
		Email:     "test@example.com",
		RegisteredClaims: jwt.RegisteredClaims{
			ExpiresAt: jwt.NewNumericDate(time.Now().Add(-time.Hour)),
		},
	})

	tokenString, _ := token.SignedString([]byte(secret))

	testHandler := http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		w.WriteHeader(http.StatusOK)
	})

	// Create request with expired token
	req := httptest.NewRequest("GET", "/", nil)
	req.Header.Set("Authorization", "Bearer "+tokenString)

	middleware := JWTMiddleware(secret, logger)
	wrappedHandler := middleware(testHandler)

	w := httptest.NewRecorder()
	wrappedHandler.ServeHTTP(w, req)

	assert.Equal(t, http.StatusUnauthorized, w.Code)
}

func TestJWTMiddleware_InvalidSignature(t *testing.T) {
	secret := "test-secret"
	wrongSecret := "wrong-secret"
	logger, _ := zap.NewProduction()

	// Create token with wrong secret
	token := jwt.NewWithClaims(jwt.SigningMethodHS256, &Claims{
		UserID:    123,
		CompanyID: 456,
		Email:     "test@example.com",
	})

	tokenString, _ := token.SignedString([]byte(wrongSecret))

	testHandler := http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		w.WriteHeader(http.StatusOK)
	})

	// Create request with token signed with wrong secret
	req := httptest.NewRequest("GET", "/", nil)
	req.Header.Set("Authorization", "Bearer "+tokenString)

	middleware := JWTMiddleware(secret, logger)
	wrappedHandler := middleware(testHandler)

	w := httptest.NewRecorder()
	wrappedHandler.ServeHTTP(w, req)

	assert.Equal(t, http.StatusUnauthorized, w.Code)
}
