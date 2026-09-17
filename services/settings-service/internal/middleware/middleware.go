package middleware

import (
	"fmt"
	"net/http"
	"strings"
	"time"

	"github.com/gin-gonic/gin"
	"github.com/golang-jwt/jwt/v5"
	"github.com/sirupsen/logrus"
)

const (
	CompanyIDKey = "company_id"
	UserIDKey    = "user_id"
	TokenKey     = "token"
)

// JWTClaims represents the JWT token claims
type JWTClaims struct {
	UserID    uint   `json:"user_id"`
	CompanyID uint   `json:"company_id"`
	Email     string `json:"email"`
	Roles     []string `json:"roles"`
	jwt.RegisteredClaims
}

// JWTMiddleware validates JWT tokens
func JWTMiddleware(secretKey string, logger *logrus.Logger) gin.HandlerFunc {
	return func(c *gin.Context) {
		authHeader := c.GetHeader("Authorization")
		if authHeader == "" {
			logger.Warn("Missing Authorization header")
			c.JSON(http.StatusUnauthorized, gin.H{
				"error": "Missing authorization header",
			})
			c.Abort()
			return
		}

		// Extract token from "Bearer <token>"
		parts := strings.SplitN(authHeader, " ", 2)
		if len(parts) != 2 || parts[0] != "Bearer" {
			logger.Warn("Invalid authorization header format")
			c.JSON(http.StatusUnauthorized, gin.H{
				"error": "Invalid authorization header format",
			})
			c.Abort()
			return
		}

		tokenString := parts[1]

		// Parse and validate token
		claims := &JWTClaims{}
		token, err := jwt.ParseWithClaims(tokenString, claims, func(token *jwt.Token) (interface{}, error) {
			if _, ok := token.Method.(*jwt.SigningMethodHMAC); !ok {
				return nil, fmt.Errorf("unexpected signing method: %v", token.Header["alg"])
			}
			return []byte(secretKey), nil
		})

		if err != nil || !token.Valid {
			logger.WithError(err).Warn("Invalid or expired token")
			c.JSON(http.StatusUnauthorized, gin.H{
				"error": "Invalid or expired token",
			})
			c.Abort()
			return
		}

		// Validate required fields
		if claims.CompanyID == 0 || claims.UserID == 0 {
			logger.Warn("Missing required claims in token")
			c.JSON(http.StatusUnauthorized, gin.H{
				"error": "Invalid token claims",
			})
			c.Abort()
			return
		}

		// Store in context
		c.Set(CompanyIDKey, claims.CompanyID)
		c.Set(UserIDKey, claims.UserID)
		c.Set(TokenKey, tokenString)

		logger.WithFields(logrus.Fields{
			"user_id":    claims.UserID,
			"company_id": claims.CompanyID,
			"email":      claims.Email,
		}).Debug("JWT validation successful")

		c.Next()
	}
}

// CORSMiddleware enables CORS
func CORSMiddleware() gin.HandlerFunc {
	return func(c *gin.Context) {
		c.Writer.Header().Set("Access-Control-Allow-Origin", "*")
		c.Writer.Header().Set("Access-Control-Allow-Credentials", "true")
		c.Writer.Header().Set("Access-Control-Allow-Headers", "Content-Type, Content-Length, Accept-Encoding, X-CSRF-Token, Authorization, accept, origin, Cache-Control, X-Requested-With")
		c.Writer.Header().Set("Access-Control-Allow-Methods", "POST, OPTIONS, GET, PUT, DELETE")

		if c.Request.Method == "OPTIONS" {
			c.AbortWithStatus(http.StatusNoContent)
			return
		}

		c.Next()
	}
}

// LoggingMiddleware logs HTTP requests
func LoggingMiddleware(logger *logrus.Logger) gin.HandlerFunc {
	return func(c *gin.Context) {
		startTime := time.Now()

		// Process request
		c.Next()

		// Calculate duration
		duration := time.Since(startTime)

		// Log request details
		logger.WithFields(logrus.Fields{
			"method":           c.Request.Method,
			"path":             c.Request.RequestURI,
			"status_code":      c.Writer.Status(),
			"response_size":    c.Writer.Size(),
			"duration_ms":      duration.Milliseconds(),
			"ip":               c.ClientIP(),
			"user_agent":       c.Request.UserAgent(),
			"company_id":       getCompanyIDFromContext(c),
			"user_id":          getUserIDFromContext(c),
		}).Info("HTTP request processed")
	}
}

// RecoveryMiddleware recovers from panics
func RecoveryMiddleware(logger *logrus.Logger) gin.HandlerFunc {
	return func(c *gin.Context) {
		defer func() {
			if err := recover(); err != nil {
				logger.WithField("panic", err).Error("Panic recovered")
				c.JSON(http.StatusInternalServerError, gin.H{
					"error": "Internal server error",
				})
				c.Abort()
			}
		}()

		c.Next()
	}
}

// GetCompanyID retrieves company ID from context
func GetCompanyID(c *gin.Context) uint {
	if companyID, exists := c.Get(CompanyIDKey); exists {
		if id, ok := companyID.(uint); ok {
			return id
		}
	}
	return 0
}

// GetUserID retrieves user ID from context
func GetUserID(c *gin.Context) uint {
	if userID, exists := c.Get(UserIDKey); exists {
		if id, ok := userID.(uint); ok {
			return id
		}
	}
	return 0
}

// GetToken retrieves JWT token from context
func GetToken(c *gin.Context) string {
	if token, exists := c.Get(TokenKey); exists {
		if tokenStr, ok := token.(string); ok {
			return tokenStr
		}
	}
	return ""
}

// Helper functions for logging

func getCompanyIDFromContext(c *gin.Context) uint {
	if companyID, exists := c.Get(CompanyIDKey); exists {
		if id, ok := companyID.(uint); ok {
			return id
		}
	}
	return 0
}

func getUserIDFromContext(c *gin.Context) uint {
	if userID, exists := c.Get(UserIDKey); exists {
		if id, ok := userID.(uint); ok {
			return id
		}
	}
	return 0
}
