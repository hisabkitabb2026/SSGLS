package middleware

import (
	"context"
	"net/http"
	"strings"

	"github.com/golang-jwt/jwt/v5"
	"github.com/invoiceshelf/product-service/pkg/logger"
)

type Claims struct {
	UserID    int64  `json:"user_id"`
	CompanyID int64  `json:"company_id"`
	Email     string `json:"email"`
	jwt.RegisteredClaims
}

type ContextKey string

const (
	ContextKeyUserID    ContextKey = "user_id"
	ContextKeyCompanyID ContextKey = "company_id"
	ContextKeyEmail     ContextKey = "email"
)

func JWTMiddleware(secret string, log *logger.Logger) func(http.Handler) http.Handler {
	return func(next http.Handler) http.Handler {
		return http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
			authHeader := r.Header.Get("Authorization")
			if authHeader == "" {
				log.Warn("missing authorization header")
				http.Error(w, "missing authorization header", http.StatusUnauthorized)
				return
			}

			parts := strings.Split(authHeader, " ")
			if len(parts) != 2 || parts[0] != "Bearer" {
				log.Warn("invalid authorization header format")
				http.Error(w, "invalid authorization header", http.StatusUnauthorized)
				return
			}

			tokenString := parts[1]
			claims := &Claims{}

			token, err := jwt.ParseWithClaims(tokenString, claims, func(token *jwt.Token) (interface{}, error) {
				if _, ok := token.Method.(*jwt.SigningMethodHMAC); !ok {
					return nil, jwt.ErrSigningMethodInvalid
				}
				return []byte(secret), nil
			})

			if err != nil || !token.Valid {
				log.WithError(err).Warn("invalid token")
				http.Error(w, "invalid token", http.StatusUnauthorized)
				return
			}

			ctx := context.WithValue(r.Context(), ContextKeyUserID, claims.UserID)
			ctx = context.WithValue(ctx, ContextKeyCompanyID, claims.CompanyID)
			ctx = context.WithValue(ctx, ContextKeyEmail, claims.Email)

			next.ServeHTTP(w, r.WithContext(ctx))
		})
	}
}

func GetUserID(r *http.Request) int64 {
	userID, ok := r.Context().Value(ContextKeyUserID).(int64)
	if !ok {
		return 0
	}
	return userID
}

func GetCompanyID(r *http.Request) int64 {
	companyID, ok := r.Context().Value(ContextKeyCompanyID).(int64)
	if !ok {
		return 0
	}
	return companyID
}

func GetEmail(r *http.Request) string {
	email, ok := r.Context().Value(ContextKeyEmail).(string)
	if !ok {
		return ""
	}
	return email
}
