package handler

import (
	"encoding/json"
	"net/http"

	"gorm.io/gorm"
)

type HealthHandler struct {
	db *gorm.DB
}

func NewHealthHandler(db *gorm.DB) *HealthHandler {
	return &HealthHandler{db: db}
}

func (h *HealthHandler) Health(w http.ResponseWriter, r *http.Request) {
	sqlDB, err := h.db.DB()
	if err != nil {
		w.WriteHeader(http.StatusInternalServerError)
		json.NewEncoder(w).Encode(map[string]interface{}{
			"status": "unhealthy",
			"error":  err.Error(),
		})
		return
	}

	if err := sqlDB.Ping(); err != nil {
		w.WriteHeader(http.StatusInternalServerError)
		json.NewEncoder(w).Encode(map[string]interface{}{
			"status": "unhealthy",
			"error":  err.Error(),
		})
		return
	}

	w.Header().Set("Content-Type", "application/json")
	w.WriteHeader(http.StatusOK)
	json.NewEncoder(w).Encode(map[string]interface{}{
		"status": "healthy",
		"service": "product-service",
	})
}

func (h *HealthHandler) Ready(w http.ResponseWriter, r *http.Request) {
	sqlDB, err := h.db.DB()
	if err != nil {
		w.WriteHeader(http.StatusInternalServerError)
		json.NewEncoder(w).Encode(map[string]interface{}{
			"ready": false,
		})
		return
	}

	if err := sqlDB.Ping(); err != nil {
		w.WriteHeader(http.StatusServiceUnavailable)
		json.NewEncoder(w).Encode(map[string]interface{}{
			"ready": false,
		})
		return
	}

	w.Header().Set("Content-Type", "application/json")
	w.WriteHeader(http.StatusOK)
	json.NewEncoder(w).Encode(map[string]interface{}{
		"ready": true,
	})
}
