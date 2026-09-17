package main

import (
	"fmt"
	"net/http"
	"os"
	"os/signal"
	"syscall"

	"github.com/gin-gonic/gin"
	"github.com/invoiceshelf/settings-service/internal/config"
	"github.com/invoiceshelf/settings-service/internal/database"
	"github.com/invoiceshelf/settings-service/internal/events"
	"github.com/invoiceshelf/settings-service/internal/handlers"
	"github.com/invoiceshelf/settings-service/internal/middleware"
	"github.com/invoiceshelf/settings-service/internal/repositories"
	"github.com/invoiceshelf/settings-service/internal/services"
	"github.com/prometheus/client_golang/prometheus/promhttp"
	"github.com/sirupsen/logrus"
)

func main() {
	// Load configuration
	cfg, err := config.LoadConfig()
	if err != nil {
		fmt.Fprintf(os.Stderr, "Failed to load configuration: %v\n", err)
		os.Exit(1)
	}

	// Initialize logger
	logger := logrus.New()
	logger.SetFormatter(&logrus.JSONFormatter{})
	if cfg.Debug {
		logger.SetLevel(logrus.DebugLevel)
	} else {
		logger.SetLevel(logrus.InfoLevel)
	}

	logger.WithFields(logrus.Fields{
		"version": "1.0.0",
		"port":    cfg.Port,
		"env":     cfg.Environment,
	}).Info("Starting Settings Microservice")

	// Initialize database
	db, err := database.Initialize(cfg)
	if err != nil {
		logger.WithError(err).Fatal("Failed to initialize database")
	}
	logger.Info("Database connection established")

	// Run migrations
	if err := database.RunMigrations(db); err != nil {
		logger.WithError(err).Fatal("Failed to run migrations")
	}
	logger.Info("Migrations completed successfully")

	// Initialize RabbitMQ event publisher
	eventPublisher, err := events.NewPublisher(cfg)
	if err != nil {
		logger.WithError(err).Fatal("Failed to initialize RabbitMQ publisher")
	}
	defer eventPublisher.Close()
	logger.Info("RabbitMQ publisher initialized")

	// Initialize repositories
	settingRepo := repositories.NewSettingRepository(db)
	auditLogRepo := repositories.NewAuditLogRepository(db)

	// Initialize services
	settingService := services.NewSettingService(settingRepo, auditLogRepo, eventPublisher, logger)

	// Initialize handlers
	settingHandler := handlers.NewSettingHandler(settingService, logger)
	healthHandler := handlers.NewHealthHandler(db, logger)

	// Set up Gin router
	if !cfg.Debug {
		gin.SetMode(gin.ReleaseMode)
	}

	router := gin.New()

	// Apply middleware
	router.Use(middleware.LoggingMiddleware(logger))
	router.Use(middleware.RecoveryMiddleware(logger))
	router.Use(middleware.CORSMiddleware())

	// Health check endpoint (no auth required)
	router.GET("/health", healthHandler.Health)
	router.GET("/ready", healthHandler.Ready)

	// Metrics endpoint (no auth required)
	router.GET("/metrics", gin.WrapH(promhttp.Handler()))

	// API routes with JWT authentication
	api := router.Group("/api/v1")
	api.Use(middleware.JWTMiddleware(cfg.JWTSecret, logger))

	// Settings endpoints
	api.POST("/settings", settingHandler.Create)
	api.GET("/settings", settingHandler.List)
	api.GET("/settings/:id", settingHandler.Get)
	api.PUT("/settings/:id", settingHandler.Update)
	api.DELETE("/settings/:id", settingHandler.Delete)

	// Settings by key endpoints
	api.GET("/settings/key/:key", settingHandler.GetByKey)
	api.PUT("/settings/key/:key", settingHandler.UpdateByKey)

	// Batch operations
	api.POST("/settings/batch", settingHandler.BatchCreate)
	api.PUT("/settings/batch", settingHandler.BatchUpdate)

	// Audit log endpoints
	api.GET("/settings/audit-logs", settingHandler.GetAuditLogs)

	// Health checks
	hc := make(chan error, 1)
	sc := make(chan os.Signal, 1)
	signal.Notify(sc, syscall.SIGINT, syscall.SIGTERM)

	// Start HTTP server
	go func() {
		addr := fmt.Sprintf(":%d", cfg.Port)
		logger.WithField("address", addr).Info("HTTP server starting")
		hc <- router.Run(addr)
	}()

	// Wait for shutdown signal or error
	select {
	case err := <-hc:
		if err != nil && err != http.ErrServerClosed {
			logger.WithError(err).Fatal("HTTP server error")
		}
	case sig := <-sc:
		logger.WithField("signal", sig).Info("Shutdown signal received")
	}

	logger.Info("Settings Microservice stopped")
}
