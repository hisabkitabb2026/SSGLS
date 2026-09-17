package handler

import (
	"encoding/json"
	"net/http"
	"strconv"

	"github.com/gorilla/mux"
	"github.com/invoiceshelf/product-service/internal/middleware"
	"github.com/invoiceshelf/product-service/internal/models"
	"github.com/invoiceshelf/product-service/internal/service"
	"go.uber.org/zap"
)

type ProductHandler struct {
	service *service.ProductService
	logger  *zap.Logger
}

func NewProductHandler(service *service.ProductService, logger *zap.Logger) *ProductHandler {
	return &ProductHandler{
		service: service,
		logger:  logger,
	}
}

func (h *ProductHandler) Register(router *mux.Router) {
	router.HandleFunc("/api/v1/products", h.CreateProduct).Methods("POST")
	router.HandleFunc("/api/v1/products", h.ListProducts).Methods("GET")
	router.HandleFunc("/api/v1/products/{id}", h.GetProduct).Methods("GET")
	router.HandleFunc("/api/v1/products/{id}", h.UpdateProduct).Methods("PUT", "PATCH")
	router.HandleFunc("/api/v1/products/{id}", h.DeleteProduct).Methods("DELETE")
	router.HandleFunc("/api/v1/products/low-stock", h.GetLowStockProducts).Methods("GET")
}

func (h *ProductHandler) CreateProduct(w http.ResponseWriter, r *http.Request) {
	companyID := middleware.GetCompanyID(r)
	if companyID == 0 {
		h.logger.Warn("missing company_id in context")
		http.Error(w, "missing company_id", http.StatusUnauthorized)
		return
	}

	var req models.ProductRequest
	if err := json.NewDecoder(r.Body).Decode(&req); err != nil {
		h.logger.Warn("failed to decode request body", zap.Error(err))
		http.Error(w, "invalid request body", http.StatusBadRequest)
		return
	}

	product, err := h.service.CreateProduct(r.Context(), companyID, &req)
	if err != nil {
		h.logger.Error("failed to create product", zap.Error(err))
		http.Error(w, err.Error(), http.StatusBadRequest)
		return
	}

	w.Header().Set("Content-Type", "application/json")
	w.WriteHeader(http.StatusCreated)
	json.NewEncoder(w).Encode(map[string]interface{}{
		"success": true,
		"data":    product,
	})
}

func (h *ProductHandler) GetProduct(w http.ResponseWriter, r *http.Request) {
	companyID := middleware.GetCompanyID(r)
	if companyID == 0 {
		h.logger.Warn("missing company_id in context")
		http.Error(w, "missing company_id", http.StatusUnauthorized)
		return
	}

	vars := mux.Vars(r)
	productID, err := strconv.ParseInt(vars["id"], 10, 64)
	if err != nil {
		h.logger.Warn("invalid product id", zap.Error(err))
		http.Error(w, "invalid product id", http.StatusBadRequest)
		return
	}

	product, err := h.service.GetProduct(r.Context(), companyID, productID)
	if err != nil {
		h.logger.Error("failed to get product", zap.Error(err))
		http.Error(w, err.Error(), http.StatusNotFound)
		return
	}

	w.Header().Set("Content-Type", "application/json")
	json.NewEncoder(w).Encode(map[string]interface{}{
		"success": true,
		"data":    product,
	})
}

func (h *ProductHandler) ListProducts(w http.ResponseWriter, r *http.Request) {
	companyID := middleware.GetCompanyID(r)
	if companyID == 0 {
		h.logger.Warn("missing company_id in context")
		http.Error(w, "missing company_id", http.StatusUnauthorized)
		return
	}

	limit := 20
	offset := 0
	status := "all"

	if l := r.URL.Query().Get("limit"); l != "" {
		if parsed, err := strconv.Atoi(l); err == nil && parsed > 0 {
			limit = parsed
		}
	}

	if o := r.URL.Query().Get("offset"); o != "" {
		if parsed, err := strconv.Atoi(o); err == nil && parsed >= 0 {
			offset = parsed
		}
	}

	if s := r.URL.Query().Get("status"); s != "" {
		status = s
	}

	products, total, err := h.service.ListProducts(r.Context(), companyID, limit, offset, status)
	if err != nil {
		h.logger.Error("failed to list products", zap.Error(err))
		http.Error(w, err.Error(), http.StatusInternalServerError)
		return
	}

	w.Header().Set("Content-Type", "application/json")
	json.NewEncoder(w).Encode(map[string]interface{}{
		"success": true,
		"data":    products,
		"pagination": map[string]interface{}{
			"total":  total,
			"limit":  limit,
			"offset": offset,
		},
	})
}

func (h *ProductHandler) UpdateProduct(w http.ResponseWriter, r *http.Request) {
	companyID := middleware.GetCompanyID(r)
	if companyID == 0 {
		h.logger.Warn("missing company_id in context")
		http.Error(w, "missing company_id", http.StatusUnauthorized)
		return
	}

	vars := mux.Vars(r)
	productID, err := strconv.ParseInt(vars["id"], 10, 64)
	if err != nil {
		h.logger.Warn("invalid product id", zap.Error(err))
		http.Error(w, "invalid product id", http.StatusBadRequest)
		return
	}

	var req models.ProductRequest
	if err := json.NewDecoder(r.Body).Decode(&req); err != nil {
		h.logger.Warn("failed to decode request body", zap.Error(err))
		http.Error(w, "invalid request body", http.StatusBadRequest)
		return
	}

	product, err := h.service.UpdateProduct(r.Context(), companyID, productID, &req)
	if err != nil {
		h.logger.Error("failed to update product", zap.Error(err))
		http.Error(w, err.Error(), http.StatusBadRequest)
		return
	}

	w.Header().Set("Content-Type", "application/json")
	json.NewEncoder(w).Encode(map[string]interface{}{
		"success": true,
		"data":    product,
	})
}

func (h *ProductHandler) DeleteProduct(w http.ResponseWriter, r *http.Request) {
	companyID := middleware.GetCompanyID(r)
	if companyID == 0 {
		h.logger.Warn("missing company_id in context")
		http.Error(w, "missing company_id", http.StatusUnauthorized)
		return
	}

	vars := mux.Vars(r)
	productID, err := strconv.ParseInt(vars["id"], 10, 64)
	if err != nil {
		h.logger.Warn("invalid product id", zap.Error(err))
		http.Error(w, "invalid product id", http.StatusBadRequest)
		return
	}

	if err := h.service.DeleteProduct(r.Context(), companyID, productID); err != nil {
		h.logger.Error("failed to delete product", zap.Error(err))
		http.Error(w, err.Error(), http.StatusInternalServerError)
		return
	}

	w.Header().Set("Content-Type", "application/json")
	json.NewEncoder(w).Encode(map[string]interface{}{
		"success": true,
		"message": "product deleted successfully",
	})
}

func (h *ProductHandler) GetLowStockProducts(w http.ResponseWriter, r *http.Request) {
	companyID := middleware.GetCompanyID(r)
	if companyID == 0 {
		h.logger.Warn("missing company_id in context")
		http.Error(w, "missing company_id", http.StatusUnauthorized)
		return
	}

	products, err := h.service.GetLowStockProducts(r.Context(), companyID)
	if err != nil {
		h.logger.Error("failed to get low stock products", zap.Error(err))
		http.Error(w, err.Error(), http.StatusInternalServerError)
		return
	}

	w.Header().Set("Content-Type", "application/json")
	json.NewEncoder(w).Encode(map[string]interface{}{
		"success": true,
		"data":    products,
	})
}
